<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkSummary;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class WorkSummaryService
{
    /**
     * Calculate daily summary from attendance_logs.
     */
    public function calculateDaily(User $worker, Carbon $date): WorkSummary
    {
    $startOfDay = $date->copy()->startOfDay();
    $endOfDay = $date->copy()->endOfDay();
    $summary = $this->aggregateFromAttendanceLogs($worker, $startOfDay, $endOfDay);

    return $this->saveSummary($worker, 'daily', $startOfDay, $endOfDay, $summary);
}

    /**
     * Calculate weekly summary from attendance_logs.
     */
    public function calculateWeekly(User $worker, Carbon $weekStart): WorkSummary
    {
        $weekEnd = $weekStart->copy()->endOfWeek();

        $summary = $this->aggregateFromAttendanceLogs($worker, $weekStart, $weekEnd);

        return $this->saveSummary($worker, 'weekly', $weekStart->copy()->startOfWeek(), $weekEnd, $summary);
    }

    /**
     * Calculate monthly summary from attendance_logs.
     * Also ensures weekly summaries exist for all weeks in the month.
     */
    public function calculateMonthly(User $worker, Carbon $monthStart): WorkSummary
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        // First, ensure all weekly summaries exist for this month
        $this->ensureWeeklySummariesExist($worker, $monthStart->copy(), $monthEnd->copy());

        $summary = $this->aggregateFromAttendanceLogs($worker, $monthStart, $monthEnd);

        return $this->saveSummary($worker, 'monthly', $monthStart->copy()->startOfMonth(), $monthEnd, $summary);
    }

    /**
     * Calculate yearly summary from monthly summaries.
     * Uses source_hash to skip recalculation if data hasn't changed.
     */
    public function calculateYearly(User $worker, int $year): WorkSummary
    {
        $yearStart = Carbon::createFromDate($year, 1, 1)->startOfYear();
        $yearEnd = Carbon::createFromDate($year, 12, 31)->endOfYear();

        // For current year, only calculate up to current month
        $currentYear = (int) date('Y');
        $maxMonth = ($year < $currentYear) ? 12 : (int) date('n');

        // Calculate source hash from attendance logs
        $newHash = $this->calculateSourceHash($worker, $yearStart, $yearEnd);

        // Check existing summary
        $existingSummary = WorkSummary::where('worker_id', $worker->id)
            ->where('period_type', 'yearly')
            ->whereDate('period_start', $yearStart)
            ->first();

        // Skip if hash matches (data hasn't changed)
        if ($existingSummary && $existingSummary->source_hash === $newHash && !$existingSummary->is_dirty) {
            return $existingSummary;
        }

        // Aggregate from monthly summaries (Source of Truth)
        $summary = $this->aggregateFromMonthlySummaries($worker, $year, $maxMonth);

        return $this->saveSummary($worker, 'yearly', $yearStart, $yearEnd, $summary, $newHash);
    }

    /**
     * Aggregate data directly from attendance_logs table.
     * This is the new Source of Truth for weekly/monthly calculations.
     */
    private function aggregateFromAttendanceLogs(User $worker, Carbon $periodStart, Carbon $periodEnd): array
    {
        // Get check-out logs with work_minutes (paired with check-ins)
        $checkoutStats = AttendanceLog::where('worker_id', $worker->id)
            ->where('type', 'out')
            ->whereNotNull('work_minutes')
            ->whereBetween('device_time', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->selectRaw('
                SUM(work_minutes) as total_minutes,
                SUM(CASE WHEN is_overtime = 1 THEN overtime_minutes ELSE 0 END) as overtime_minutes,
                SUM(CASE WHEN is_early_departure = 1 THEN 1 ELSE 0 END) as early_departures,
                COUNT(DISTINCT DATE(device_time)) as days_with_checkout
            ')
            ->first();

        // Get check-in stats
        $checkinStats = AttendanceLog::where('worker_id', $worker->id)
            ->where('type', 'in')
            ->whereBetween('device_time', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->selectRaw('
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_arrivals,
                COUNT(DISTINCT DATE(device_time)) as days_with_checkin
            ')
            ->first();

        // Count unpaired check-ins (missing checkouts)
        $missingCheckouts = AttendanceLog::where('worker_id', $worker->id)
            ->where('type', 'in')
            ->whereNull('paired_log_id')
            ->whereBetween('device_time', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->count();

        // Count unpaired check-outs (missing checkins)
        $missingCheckins = AttendanceLog::where('worker_id', $worker->id)
            ->where('type', 'out')
            ->whereNull('paired_log_id')
            ->whereBetween('device_time', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->count();

        // Calculate working days in period using settings
        $workingDayNames = Setting::getWorkingDays();
        $period = CarbonPeriod::create($periodStart, $periodEnd);
        $workingDays = 0;
        foreach ($period as $date) {
            if (in_array(strtolower($date->format('l')), $workingDayNames)) {
                $workingDays++;
            }
        }

        $totalMinutes = (int) ($checkoutStats->total_minutes ?? 0);
        $overtimeMinutes = (int) ($checkoutStats->overtime_minutes ?? 0);
        $regularMinutes = max(0, $totalMinutes - $overtimeMinutes);

        // Days worked = days with at least one check-in
        $daysWorked = (int) ($checkinStats->days_with_checkin ?? 0);
        $daysAbsent = max(0, $workingDays - $daysWorked);

        return [
            'total_minutes' => $totalMinutes,
            'regular_minutes' => $regularMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'days_worked' => $daysWorked,
            'days_absent' => $daysAbsent,
            'late_arrivals' => (int) ($checkinStats->late_arrivals ?? 0),
            'early_departures' => (int) ($checkoutStats->early_departures ?? 0),
            'missing_checkouts' => $missingCheckouts,
            'missing_checkins' => $missingCheckins,
        ];
    }

    /**
     * Aggregate yearly summary from pre-calculated monthly summaries.
     */
    private function aggregateFromMonthlySummaries(User $worker, int $year, int $maxMonth): array
    {
        // First, ensure all monthly summaries exist for this year
        $this->ensureMonthlySummariesExist($worker, $year, $maxMonth);

        // Use SQL SUM for fast aggregation (single query)
        $aggregated = WorkSummary::where('worker_id', $worker->id)
            ->where('period_type', 'monthly')
            ->whereYear('period_start', $year)
            ->whereMonth('period_start', '<=', $maxMonth)
            ->selectRaw('
                SUM(total_minutes) as total_minutes,
                SUM(regular_minutes) as regular_minutes,
                SUM(overtime_minutes) as overtime_minutes,
                SUM(days_worked) as days_worked,
                SUM(days_absent) as days_absent,
                SUM(late_arrivals) as late_arrivals,
                SUM(early_departures) as early_departures,
                SUM(missing_checkouts) as missing_checkouts,
                SUM(missing_checkins) as missing_checkins
            ')
            ->first();

        return [
            'total_minutes' => (int) ($aggregated->total_minutes ?? 0),
            'regular_minutes' => (int) ($aggregated->regular_minutes ?? 0),
            'overtime_minutes' => (int) ($aggregated->overtime_minutes ?? 0),
            'days_worked' => (int) ($aggregated->days_worked ?? 0),
            'days_absent' => (int) ($aggregated->days_absent ?? 0),
            'late_arrivals' => (int) ($aggregated->late_arrivals ?? 0),
            'early_departures' => (int) ($aggregated->early_departures ?? 0),
            'missing_checkouts' => (int) ($aggregated->missing_checkouts ?? 0),
            'missing_checkins' => (int) ($aggregated->missing_checkins ?? 0),
        ];
    }

    /**
     * Ensure weekly summaries exist for all weeks in the month.
     */
    private function ensureWeeklySummariesExist(User $worker, Carbon $monthStart, Carbon $monthEnd): void
    {
        // Get all week start dates that fall within this month
        $weekStarts = [];
        $current = $monthStart->copy()->startOfWeek();

        while ($current->lte($monthEnd)) {
            // Only include weeks that have at least one day in the month
            $weekEnd = $current->copy()->endOfWeek();
            if ($weekEnd->gte($monthStart) && $current->lte($monthEnd)) {
                $weekStarts[] = $current->copy();
            }
            $current->addWeek();
        }

        // Check which weekly summaries already exist
        $existingWeekStarts = WorkSummary::where('worker_id', $worker->id)
            ->where('period_type', 'weekly')
            ->whereBetween('period_start', [$weekStarts[0] ?? $monthStart, $monthEnd->copy()->endOfWeek()])
            ->pluck('period_start')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->toArray();

        // Create missing weekly summaries
        foreach ($weekStarts as $weekStart) {
            if (! in_array($weekStart->format('Y-m-d'), $existingWeekStarts)) {
                $this->calculateWeekly($worker, $weekStart);
            }
        }
    }

    /**
     * Ensure monthly summaries exist for all months in the year.
     */
    private function ensureMonthlySummariesExist(User $worker, int $year, int $maxMonth): void
    {
        $existingSummaries = WorkSummary::where('worker_id', $worker->id)
            ->where('period_type', 'monthly')
            ->whereYear('period_start', $year)
            ->pluck('period_start')
            ->map(fn ($date) => (int) $date->format('n'))
            ->toArray();

        for ($month = 1; $month <= $maxMonth; $month++) {
            if (! in_array($month, $existingSummaries)) {
                $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $this->calculateMonthly($worker, $monthStart);
            }
        }
    }

    /**
     * Calculate a hash of the source attendance logs for change detection.
     * Used primarily for yearly summaries to avoid unnecessary recalculations.
     */
    private function calculateSourceHash(User $worker, Carbon $periodStart, Carbon $periodEnd): string
    {
        $logs = AttendanceLog::where('worker_id', $worker->id)
            ->whereBetween('device_time', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->orderBy('id')
            ->get(['id', 'updated_at']);

        if ($logs->isEmpty()) {
            return hash('xxh3', 'empty');
        }

        $payload = $logs
            ->map(fn ($log) => $log->id . ':' . $log->updated_at->timestamp)
            ->implode('|');

        return hash('xxh3', $payload);
    }

    private function saveSummary(User $worker, string $periodType, Carbon $periodStart, Carbon $periodEnd, array $summary, ?string $sourceHash = null): WorkSummary
    {
        return WorkSummary::updateOrCreate(
            [
                'worker_id' => $worker->id,
                'period_type' => $periodType,
                'period_start' => $periodStart,
            ],
            [
                'period_end' => $periodEnd,
                'total_minutes' => $summary['total_minutes'],
                'regular_minutes' => $summary['regular_minutes'],
                'overtime_minutes' => $summary['overtime_minutes'],
                'days_worked' => $summary['days_worked'],
                'days_absent' => $summary['days_absent'],
                'late_arrivals' => $summary['late_arrivals'],
                'early_departures' => $summary['early_departures'],
                'missing_checkouts' => $summary['missing_checkouts'],
                'missing_checkins' => $summary['missing_checkins'],
                'is_dirty' => false, // Reset dirty flag after recalculation
                'source_hash' => $sourceHash,
                'calculated_at' => now(),
            ]
        );
    }
}
