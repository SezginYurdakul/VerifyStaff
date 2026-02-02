<?php

namespace App\Services\Dashboard;

use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardAnomalyService
{
    public function getAnomalies(int $limit = 10): array
    {
        $today = Carbon::today();
        $thisWeekStart = $today->copy()->startOfWeek();

        $flaggedLogs = $this->getFlaggedRecords($limit);
        $missingCheckouts = $this->getMissingCheckouts($thisWeekStart, $today, $limit);
        $lateArrivals = $this->getLateArrivals($thisWeekStart, $limit);
        $inactiveWorkers = $this->getInactiveWorkers($thisWeekStart, $today, $limit);

        return [
            'summary' => [
                'flagged_count' => $flaggedLogs->count() < $limit
                    ? $flaggedLogs->count()
                    : AttendanceLog::where('flagged', true)->count(),
                'missing_checkouts_count' => $missingCheckouts->count() < $limit
                    ? $missingCheckouts->count()
                    : $this->getMissingCheckoutsCount($thisWeekStart, $today),
                'late_arrivals_this_week' => $lateArrivals->count() < $limit
                    ? $lateArrivals->count()
                    : $this->getLateArrivalsCount($thisWeekStart),
                'inactive_workers' => count($inactiveWorkers),
            ],
            'flagged' => $flaggedLogs->toArray(),
            'missing_checkouts' => $missingCheckouts->toArray(),
            'late_arrivals' => $lateArrivals->toArray(),
            'inactive_workers' => $inactiveWorkers,
        ];
    }

    private function getFlaggedRecords(int $limit): Collection
    {
        return AttendanceLog::where('flagged', true)
            ->with(['worker:id,name,employee_id'])
            ->orderBy('device_time', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($log) => [
                'type' => 'flagged',
                'severity' => 'high',
                'log_id' => $log->id,
                'worker' => $log->worker ? [
                    'id' => $log->worker->id,
                    'name' => $log->worker->name,
                ] : null,
                'reason' => $log->flag_reason,
                'time' => $log->device_time?->toIso8601String(),
            ]);
    }

    private function getMissingCheckouts(Carbon $weekStart, Carbon $today, int $limit): Collection
    {
        return AttendanceLog::where('type', 'in')
            ->whereNull('paired_log_id')
            ->where('device_time', '>=', $weekStart)
            ->where('device_time', '<', $today)
            ->with(['worker:id,name,employee_id'])
            ->orderBy('device_time', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($log) => [
                'type' => 'missing_checkout',
                'severity' => 'medium',
                'log_id' => $log->id,
                'worker' => $log->worker ? [
                    'id' => $log->worker->id,
                    'name' => $log->worker->name,
                ] : null,
                'checkin_time' => $log->device_time?->toIso8601String(),
            ]);
    }

    private function getMissingCheckoutsCount(Carbon $weekStart, Carbon $today): int
    {
        return AttendanceLog::where('type', 'in')
            ->whereNull('paired_log_id')
            ->where('device_time', '>=', $weekStart)
            ->where('device_time', '<', $today)
            ->count();
    }

    private function getLateArrivals(Carbon $weekStart, int $limit): Collection
    {
        return AttendanceLog::where('type', 'in')
            ->where('is_late', true)
            ->where('device_time', '>=', $weekStart)
            ->with(['worker:id,name,employee_id'])
            ->orderBy('device_time', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($log) => [
                'type' => 'late_arrival',
                'severity' => 'low',
                'log_id' => $log->id,
                'worker' => $log->worker ? [
                    'id' => $log->worker->id,
                    'name' => $log->worker->name,
                ] : null,
                'time' => $log->device_time?->toIso8601String(),
            ]);
    }

    private function getLateArrivalsCount(Carbon $weekStart): int
    {
        return AttendanceLog::where('type', 'in')
            ->where('is_late', true)
            ->where('device_time', '>=', $weekStart)
            ->count();
    }

    private function getInactiveWorkers(Carbon $startDate, Carbon $endDate, int $limit): array
    {
        $allWorkerIds = User::where('role', 'worker')
            ->where('status', 'active')
            ->pluck('id');

        $activeWorkerIds = AttendanceLog::whereBetween(
            'device_time',
            [$startDate->startOfDay(), $endDate->endOfDay()]
        )
            ->distinct('worker_id')
            ->pluck('worker_id');

        $inactiveWorkerIds = $allWorkerIds->diff($activeWorkerIds)->take($limit);

        return User::whereIn('id', $inactiveWorkerIds)
            ->select(['id', 'name', 'employee_id'])
            ->get()
            ->map(fn($worker) => [
                'id' => $worker->id,
                'name' => $worker->name,
                'employee_id' => $worker->employee_id,
            ])
            ->toArray();
    }
}
