<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\WorkSummary;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class WorkSummaryService
{
    // Default work hours (can be moved to config)
    private const REGULAR_WORK_MINUTES = 480; // 8 hours
    private const WORK_START_TIME = '09:00';
    private const WORK_END_TIME = '18:00';
    private const LATE_THRESHOLD_MINUTES = 15; // 15 minutes grace period

    public function calculateDaily(User $worker, Carbon $date): WorkSummary
    {
        $logs = AttendanceLog::where('worker_id', $worker->id)
            ->whereDate('device_time', $date)
            ->orderBy('device_time')
            ->get();

        $summary = $this->calculateFromLogs($logs, $date);

        return $this->saveSummary($worker, 'daily', $date->copy()->startOfDay(), $date->copy()->endOfDay(), $summary);
    }

    public function calculateWeekly(User $worker, Carbon $weekStart): WorkSummary
    {
        $weekEnd = $weekStart->copy()->endOfWeek();

        $logs = AttendanceLog::where('worker_id', $worker->id)
            ->whereBetween('device_time', [$weekStart, $weekEnd])
            ->orderBy('device_time')
            ->get();

        $summary = $this->aggregateByPeriod($logs, $weekStart, $weekEnd);

        return $this->saveSummary($worker, 'weekly', $weekStart->copy()->startOfWeek(), $weekEnd, $summary);
    }

    public function calculateMonthly(User $worker, Carbon $monthStart): WorkSummary
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        $logs = AttendanceLog::where('worker_id', $worker->id)
            ->whereBetween('device_time', [$monthStart, $monthEnd])
            ->orderBy('device_time')
            ->get();

        $summary = $this->aggregateByPeriod($logs, $monthStart, $monthEnd);

        return $this->saveSummary($worker, 'monthly', $monthStart->copy()->startOfMonth(), $monthEnd, $summary);
    }

    private function saveSummary(User $worker, string $periodType, Carbon $periodStart, Carbon $periodEnd, array $summary): WorkSummary
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
                'calculated_at' => now(),
            ]
        );
    }

    private function calculateFromLogs(Collection $logs, Carbon $date): array
    {
        $result = [
            'total_minutes' => 0,
            'regular_minutes' => 0,
            'overtime_minutes' => 0,
            'days_worked' => 0,
            'days_absent' => 1, // Assume absent unless proven otherwise
            'late_arrivals' => 0,
            'early_departures' => 0,
            'missing_checkouts' => 0,
            'missing_checkins' => 0,
        ];

        if ($logs->isEmpty()) {
            return $result;
        }

        $result['days_absent'] = 0;
        $result['days_worked'] = 1;

        // Pair check-ins with check-outs
        $checkIn = null;
        $workMinutes = 0;

        foreach ($logs as $log) {
            if ($log->type === 'in') {
                if ($checkIn !== null) {
                    // Previous check-in without check-out
                    $result['missing_checkouts']++;
                }
                $checkIn = $log;

                // Check for late arrival
                $expectedStart = $date->copy()->setTimeFromTimeString(self::WORK_START_TIME);
                $graceEnd = $expectedStart->copy()->addMinutes(self::LATE_THRESHOLD_MINUTES);
                if (Carbon::parse($log->device_time)->gt($graceEnd)) {
                    $result['late_arrivals']++;
                }
            } elseif ($log->type === 'out') {
                if ($checkIn === null) {
                    // Check-out without check-in
                    $result['missing_checkins']++;
                    continue;
                }

                // Calculate work duration
                $checkInTime = Carbon::parse($checkIn->device_time);
                $checkOutTime = Carbon::parse($log->device_time);
                $workMinutes += $checkInTime->diffInMinutes($checkOutTime);

                // Check for early departure
                $expectedEnd = $date->copy()->setTimeFromTimeString(self::WORK_END_TIME);
                if ($checkOutTime->lt($expectedEnd)) {
                    $result['early_departures']++;
                }

                $checkIn = null;
            }
        }

        // Handle unclosed check-in at end of day
        if ($checkIn !== null) {
            $result['missing_checkouts']++;
        }

        $result['total_minutes'] = $workMinutes;
        $result['regular_minutes'] = min($workMinutes, self::REGULAR_WORK_MINUTES);
        $result['overtime_minutes'] = max(0, $workMinutes - self::REGULAR_WORK_MINUTES);

        return $result;
    }

    private function aggregateByPeriod(Collection $logs, Carbon $periodStart, Carbon $periodEnd): array
    {
        $result = [
            'total_minutes' => 0,
            'regular_minutes' => 0,
            'overtime_minutes' => 0,
            'days_worked' => 0,
            'days_absent' => 0,
            'late_arrivals' => 0,
            'early_departures' => 0,
            'missing_checkouts' => 0,
            'missing_checkins' => 0,
        ];

        // Group logs by date
        $logsByDate = $logs->groupBy(function ($log) {
            return Carbon::parse($log->device_time)->format('Y-m-d');
        });

        // Calculate working days (excluding weekends)
        $period = CarbonPeriod::create($periodStart, $periodEnd);
        $workingDays = 0;

        foreach ($period as $date) {
            if (!$date->isWeekend()) {
                $workingDays++;
                $dateKey = $date->format('Y-m-d');

                if (isset($logsByDate[$dateKey])) {
                    $dailySummary = $this->calculateFromLogs($logsByDate[$dateKey], $date);
                    $result['total_minutes'] += $dailySummary['total_minutes'];
                    $result['regular_minutes'] += $dailySummary['regular_minutes'];
                    $result['overtime_minutes'] += $dailySummary['overtime_minutes'];
                    $result['days_worked'] += $dailySummary['days_worked'];
                    $result['late_arrivals'] += $dailySummary['late_arrivals'];
                    $result['early_departures'] += $dailySummary['early_departures'];
                    $result['missing_checkouts'] += $dailySummary['missing_checkouts'];
                    $result['missing_checkins'] += $dailySummary['missing_checkins'];
                } else {
                    $result['days_absent']++;
                }
            }
        }

        return $result;
    }

    public function recalculateAllForWorker(User $worker, Carbon $fromDate, Carbon $toDate): void
    {
        $period = CarbonPeriod::create($fromDate, $toDate);

        foreach ($period as $date) {
            $this->calculateDaily($worker, $date->copy());
        }

        // Recalculate weekly summaries
        $weekStart = $fromDate->copy()->startOfWeek();
        while ($weekStart->lte($toDate)) {
            $this->calculateWeekly($worker, $weekStart->copy());
            $weekStart->addWeek();
        }

        // Recalculate monthly summaries
        $monthStart = $fromDate->copy()->startOfMonth();
        while ($monthStart->lte($toDate)) {
            $this->calculateMonthly($worker, $monthStart->copy());
            $monthStart->addMonth();
        }
    }
}
