<?php

namespace App\Services\Dashboard;

use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;

class DashboardOverviewService
{
    public function getOverview(): array
    {
        $today = Carbon::today();
        $thisWeekStart = $today->copy()->startOfWeek();
        $thisMonthStart = $today->copy()->startOfMonth();

        $activeWorkers = $this->getActiveWorkersCount();
        $todayCheckins = $this->getTodayCheckins($today);
        $todayCheckouts = $this->getTodayCheckouts($today);
        $currentlyWorking = $this->getCurrentlyWorkingCount($today);
        $missingCheckoutsToday = $this->getMissingCheckoutsToday($today);
        $flaggedCount = $this->getFlaggedCount();

        return [
            'date' => $today->format('Y-m-d'),
            'active_workers' => $activeWorkers,
            'today' => [
                'checkins' => $todayCheckins,
                'checkouts' => $todayCheckouts,
                'currently_working' => $currentlyWorking,
                'attendance_rate' => $activeWorkers > 0
                    ? round(($todayCheckins / $activeWorkers) * 100, 1)
                    : 0,
                'missing_checkouts' => $missingCheckoutsToday,
            ],
            'this_week' => $this->getWeekStats($thisWeekStart, $today),
            'this_month' => $this->getMonthStats($thisMonthStart, $today),
            'alerts' => [
                'flagged_records' => $flaggedCount,
                'missing_checkouts_today' => $missingCheckoutsToday,
            ],
        ];
    }

    private function getActiveWorkersCount(): int
    {
        return User::where('role', 'worker')
            ->where('status', 'active')
            ->count();
    }

    private function getTodayCheckins(Carbon $today): int
    {
        return AttendanceLog::where('type', 'in')
            ->whereDate('device_time', $today)
            ->distinct('worker_id')
            ->count('worker_id');
    }

    private function getTodayCheckouts(Carbon $today): int
    {
        return AttendanceLog::where('type', 'out')
            ->whereDate('device_time', $today)
            ->distinct('worker_id')
            ->count('worker_id');
    }

    private function getCurrentlyWorkingCount(Carbon $date): int
    {
        $checkedInWorkers = AttendanceLog::where('type', 'in')
            ->whereDate('device_time', $date)
            ->pluck('worker_id')
            ->unique();

        $checkedOutWorkers = AttendanceLog::where('type', 'out')
            ->whereDate('device_time', $date)
            ->pluck('worker_id')
            ->unique();

        return $checkedInWorkers->diff($checkedOutWorkers)->count();
    }

    private function getMissingCheckoutsToday(Carbon $today): int
    {
        return AttendanceLog::where('type', 'in')
            ->whereDate('device_time', $today)
            ->whereNull('paired_log_id')
            ->count();
    }

    private function getFlaggedCount(): int
    {
        return AttendanceLog::where('flagged', true)->count();
    }

    private function getWeekStats(Carbon $weekStart, Carbon $today): array
    {
        $weekEnd = $weekStart->copy()->endOfWeek();
        if ($weekEnd->gt($today)) {
            $weekEnd = $today;
        }

        $stats = AttendanceLog::where('type', 'out')
            ->whereNotNull('work_minutes')
            ->whereBetween('device_time', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
            ->selectRaw('
                SUM(work_minutes) as total_minutes,
                SUM(CASE WHEN is_overtime = 1 THEN overtime_minutes ELSE 0 END) as overtime_minutes,
                COUNT(DISTINCT worker_id) as unique_workers
            ')
            ->first();

        $lateArrivals = AttendanceLog::where('type', 'in')
            ->where('is_late', true)
            ->whereBetween('device_time', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
            ->count();

        return [
            'total_hours' => round(($stats->total_minutes ?? 0) / 60, 1),
            'overtime_hours' => round(($stats->overtime_minutes ?? 0) / 60, 1),
            'unique_workers' => $stats->unique_workers ?? 0,
            'late_arrivals' => $lateArrivals,
        ];
    }

    private function getMonthStats(Carbon $monthStart, Carbon $today): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth();
        if ($monthEnd->gt($today)) {
            $monthEnd = $today;
        }

        $stats = AttendanceLog::where('type', 'out')
            ->whereNotNull('work_minutes')
            ->whereBetween('device_time', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
            ->selectRaw('
                SUM(work_minutes) as total_minutes,
                SUM(CASE WHEN is_overtime = 1 THEN overtime_minutes ELSE 0 END) as overtime_minutes,
                COUNT(DISTINCT worker_id) as unique_workers,
                COUNT(DISTINCT DATE(device_time)) as days_with_activity
            ')
            ->first();

        return [
            'total_hours' => round(($stats->total_minutes ?? 0) / 60, 1),
            'overtime_hours' => round(($stats->overtime_minutes ?? 0) / 60, 1),
            'unique_workers' => $stats->unique_workers ?? 0,
            'days_with_activity' => $stats->days_with_activity ?? 0,
        ];
    }
}
