<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\WorkSummary;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        private WorkSummaryService $summaryService
    ) {}

    /**
     * Get daily stats for a worker from attendance logs.
     */
    public function getDailyStats(int $workerId, Carbon $date): array
    {
        $checkoutStats = AttendanceLog::where('worker_id', $workerId)
            ->where('type', 'out')
            ->whereNotNull('work_minutes')
            ->whereDate('device_time', $date)
            ->selectRaw('
                SUM(work_minutes) as total_minutes,
                SUM(CASE WHEN is_overtime = 1 THEN overtime_minutes ELSE 0 END) as overtime_minutes,
                SUM(CASE WHEN is_early_departure = 1 THEN 1 ELSE 0 END) as early_departures
            ')
            ->first();

        $checkinStats = AttendanceLog::where('worker_id', $workerId)
            ->where('type', 'in')
            ->whereDate('device_time', $date)
            ->selectRaw('
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_arrivals,
                COUNT(*) as checkin_count
            ')
            ->first();

        $missingCheckouts = AttendanceLog::where('worker_id', $workerId)
            ->where('type', 'in')
            ->whereNull('paired_log_id')
            ->whereDate('device_time', $date)
            ->count();

        $missingCheckins = AttendanceLog::where('worker_id', $workerId)
            ->where('type', 'out')
            ->whereNull('paired_log_id')
            ->whereDate('device_time', $date)
            ->count();

        $totalMinutes = (int) ($checkoutStats->total_minutes ?? 0);
        $overtimeMinutes = (int) ($checkoutStats->overtime_minutes ?? 0);
        $regularMinutes = max(0, $totalMinutes - $overtimeMinutes);

        return [
            'total_hours' => round($totalMinutes / 60, 2),
            'total_minutes' => $totalMinutes,
            'regular_hours' => round($regularMinutes / 60, 2),
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'formatted_time' => sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
            'late_arrivals' => (int) ($checkinStats->late_arrivals ?? 0),
            'early_departures' => (int) ($checkoutStats->early_departures ?? 0),
            'missing_checkouts' => $missingCheckouts,
            'missing_checkins' => $missingCheckins,
        ];
    }

    /**
     * Get or calculate weekly summary for a worker.
     */
    public function getWeeklySummary(User $worker, Carbon $date): WorkSummary
    {
        $weekStart = $date->copy()->startOfWeek();

        $summary = WorkSummary::where('worker_id', $worker->id)
            ->where('period_type', 'weekly')
            ->whereDate('period_start', $weekStart)
            ->first();

        if (!$summary) {
            $summary = $this->summaryService->calculateWeekly($worker, $weekStart);
        }

        return $summary;
    }

    /**
     * Get or calculate monthly summary for a worker.
     */
    public function getMonthlySummary(User $worker, Carbon $monthStart): WorkSummary
    {
        $summary = WorkSummary::where('worker_id', $worker->id)
            ->where('period_type', 'monthly')
            ->whereDate('period_start', $monthStart)
            ->first();

        if (!$summary) {
            $summary = $this->summaryService->calculateMonthly($worker, $monthStart);
        }

        return $summary;
    }

    /**
     * Get or calculate yearly summary for a worker.
     */
    public function getYearlySummary(User $worker, int $year): WorkSummary
    {
        $summary = WorkSummary::where('worker_id', $worker->id)
            ->where('period_type', 'yearly')
            ->whereYear('period_start', $year)
            ->first();

        if (!$summary) {
            $summary = $this->summaryService->calculateYearly($worker, $year);
        }

        return $summary;
    }

    /**
     * Get worker logs with daily summaries for a date range.
     */
    public function getWorkerLogsWithSummaries(int $workerId, Carbon $from, Carbon $to): array
    {
        $logs = AttendanceLog::where('worker_id', $workerId)
            ->whereBetween('device_time', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('device_time', 'asc')
            ->get();

        $dailyData = $logs->groupBy(fn($log) => $log->device_time->format('Y-m-d'))
            ->map(fn($dayLogs, $date) => $this->calculateDaySummaryFromLogs($dayLogs, $date))
            ->sortByDesc('date')
            ->values();

        $periodSummary = $this->calculatePeriodSummaryFromLogs($logs);

        return [
            'logs' => $logs,
            'daily_data' => $dailyData,
            'period_summary' => $periodSummary,
        ];
    }

    /**
     * Calculate summary from a collection of logs for a single day.
     */
    private function calculateDaySummaryFromLogs(Collection $dayLogs, string $date): array
    {
        $checkouts = $dayLogs->where('type', 'out')->whereNotNull('work_minutes');
        $checkins = $dayLogs->where('type', 'in');

        $totalMinutes = (int) $checkouts->sum('work_minutes');
        $overtimeMinutes = (int) $checkouts->where('is_overtime', true)->sum('overtime_minutes');
        $regularMinutes = max(0, $totalMinutes - $overtimeMinutes);

        return [
            'date' => $date,
            'summary' => [
                'total_hours' => round($totalMinutes / 60, 2),
                'total_minutes' => $totalMinutes,
                'regular_hours' => round($regularMinutes / 60, 2),
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'formatted_time' => sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
                'late_arrivals' => $checkins->where('is_late', true)->count(),
                'early_departures' => $checkouts->where('is_early_departure', true)->count(),
                'missing_checkouts' => $checkins->whereNull('paired_log_id')->count(),
                'missing_checkins' => $dayLogs->where('type', 'out')->whereNull('paired_log_id')->count(),
            ],
            'logs' => $dayLogs->sortByDesc('device_time')->values(),
        ];
    }

    /**
     * Calculate period summary from a collection of logs.
     */
    private function calculatePeriodSummaryFromLogs(Collection $logs): array
    {
        $checkouts = $logs->where('type', 'out')->whereNotNull('work_minutes');
        $checkins = $logs->where('type', 'in');

        $totalMinutes = (int) $checkouts->sum('work_minutes');
        $overtimeMinutes = (int) $checkouts->where('is_overtime', true)->sum('overtime_minutes');
        $regularMinutes = max(0, $totalMinutes - $overtimeMinutes);

        $daysWorked = $logs->groupBy(fn($log) => $log->device_time->format('Y-m-d'))->count();

        return [
            'total_hours' => round($totalMinutes / 60, 2),
            'total_minutes' => $totalMinutes,
            'regular_hours' => round($regularMinutes / 60, 2),
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'formatted_time' => sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
            'days_worked' => $daysWorked,
            'late_arrivals' => $checkins->where('is_late', true)->count(),
            'early_departures' => $checkouts->where('is_early_departure', true)->count(),
            'missing_checkouts' => $checkins->whereNull('paired_log_id')->count(),
            'missing_checkins' => $logs->where('type', 'out')->whereNull('paired_log_id')->count(),
        ];
    }

    /**
     * Get daily stats for multiple workers (batch).
     */
    public function getAllWorkersDailyStats(array $workerIds, Carbon $date): array
    {
        $checkoutStats = AttendanceLog::whereIn('worker_id', $workerIds)
            ->where('type', 'out')
            ->whereNotNull('work_minutes')
            ->whereDate('device_time', $date)
            ->selectRaw('
                worker_id,
                SUM(work_minutes) as total_minutes,
                SUM(CASE WHEN is_overtime = 1 THEN overtime_minutes ELSE 0 END) as overtime_minutes,
                SUM(CASE WHEN is_early_departure = 1 THEN 1 ELSE 0 END) as early_departures
            ')
            ->groupBy('worker_id')
            ->get()
            ->keyBy('worker_id');

        $checkinStats = AttendanceLog::whereIn('worker_id', $workerIds)
            ->where('type', 'in')
            ->whereDate('device_time', $date)
            ->selectRaw('
                worker_id,
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_arrivals
            ')
            ->groupBy('worker_id')
            ->get()
            ->keyBy('worker_id');

        return [
            'checkout_stats' => $checkoutStats,
            'checkin_stats' => $checkinStats,
        ];
    }

    /**
     * Get weekly summaries for multiple workers (batch).
     */
    public function getAllWorkersWeeklySummaries(array $workerIds, Carbon $weekStart): Collection
    {
        return WorkSummary::where('period_type', 'weekly')
            ->whereDate('period_start', $weekStart)
            ->whereIn('worker_id', $workerIds)
            ->get()
            ->keyBy('worker_id');
    }

    /**
     * Get monthly summaries for multiple workers (batch).
     */
    public function getAllWorkersMonthlySummaries(array $workerIds, Carbon $monthStart): Collection
    {
        return WorkSummary::where('period_type', 'monthly')
            ->whereDate('period_start', $monthStart)
            ->whereIn('worker_id', $workerIds)
            ->get()
            ->keyBy('worker_id');
    }

    /**
     * Format a WorkSummary for API response.
     */
    public function formatSummaryResponse(WorkSummary $summary): array
    {
        return [
            'total_hours' => $summary->total_hours,
            'total_minutes' => $summary->total_minutes,
            'regular_hours' => $summary->regular_hours,
            'overtime_hours' => $summary->overtime_hours,
            'formatted_time' => $summary->formatted_total_time,
            'days_worked' => $summary->days_worked,
            'days_absent' => $summary->days_absent,
            'late_arrivals' => $summary->late_arrivals,
            'early_departures' => $summary->early_departures,
            'missing_checkouts' => $summary->missing_checkouts,
            'missing_checkins' => $summary->missing_checkins,
        ];
    }

    /**
     * Format worker stats for API response.
     */
    public function formatWorkerStats(User $worker, ?object $checkoutStats, ?object $checkinStats): array
    {
        $totalMinutes = (int) ($checkoutStats->total_minutes ?? 0);
        $overtimeMinutes = (int) ($checkoutStats->overtime_minutes ?? 0);
        $regularMinutes = max(0, $totalMinutes - $overtimeMinutes);

        return [
            'id' => $worker->id,
            'name' => $worker->name,
            'employee_id' => $worker->employee_id,
            'total_hours' => round($totalMinutes / 60, 2),
            'total_minutes' => $totalMinutes,
            'regular_hours' => round($regularMinutes / 60, 2),
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'formatted_time' => sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
            'late_arrivals' => (int) ($checkinStats->late_arrivals ?? 0),
            'early_departures' => (int) ($checkoutStats->early_departures ?? 0),
        ];
    }
}
