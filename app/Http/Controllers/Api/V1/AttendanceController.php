<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SelfCheckRequest;
use App\Http\Requests\Api\SyncOfflineLogsRequest;
use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Services\AttendanceSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceSyncService $syncService
    ) {}

    /**
     * Self check-in/check-out for kiosk mode.
     */
    public function selfCheck(SelfCheckRequest $request): JsonResponse
    {
        if (!Setting::isKioskMode()) {
            return response()->json([
                'message' => 'Kiosk mode is not enabled. Contact your administrator.',
            ], 403);
        }

        $worker = $request->user();
        $worker->load('department');

        if (!$worker->isWorker()) {
            return response()->json([
                'message' => 'Only workers can use self check-in/check-out.',
            ], 403);
        }

        $result = $this->syncService->processSelfCheck($worker, $request->validated());

        if (isset($result['error'])) {
            return response()->json(
                ['message' => $result['error'], 'type' => $result['type'] ?? null],
                $result['status']
            );
        }

        $type = $result['type'];
        $kiosk = $result['kiosk'];
        $logData = $result['log_data'];

        return response()->json([
            'message' => $type === 'in' ? 'Check-in successful' : 'Check-out successful',
            'type' => $type,
            'worker_id' => $worker->id,
            'worker_name' => $worker->name,
            'kiosk_code' => $kiosk->code,
            'kiosk_name' => $kiosk->name,
            'device_time' => Carbon::parse($logData['device_time'])->toIso8601String(),
            'work_minutes' => $logData['work_minutes'] ?? null,
            'is_late' => $logData['is_late'] ?? false,
            'is_early_departure' => $logData['is_early_departure'] ?? false,
        ]);
    }

    /**
     * Get current attendance status for the authenticated worker.
     */
    public function status(): JsonResponse
    {
        $worker = request()->user();

        if (!$worker->isWorker()) {
            return response()->json([
                'message' => 'Only workers can check their attendance status.',
            ], 403);
        }

        $today = Carbon::today();

        $todayLogs = AttendanceLog::where('worker_id', $worker->id)
            ->whereDate('device_time', $today)
            ->orderBy('device_time', 'desc')
            ->get();

        $lastLog = $todayLogs->first();

        $totalMinutes = $todayLogs->where('type', 'out')
            ->whereNotNull('work_minutes')
            ->sum('work_minutes');

        return response()->json([
            'worker_id' => $worker->id,
            'worker_name' => $worker->name,
            'date' => $today->format('Y-m-d'),
            'current_status' => $lastLog ? ($lastLog->type === 'in' ? 'checked_in' : 'checked_out') : 'not_checked_in',
            'last_action' => $lastLog ? [
                'type' => $lastLog->type,
                'time' => $lastLog->device_time->toIso8601String(),
            ] : null,
            'today_summary' => [
                'total_logs' => $todayLogs->count(),
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
                'formatted_time' => sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
            ],
            'attendance_mode' => Setting::getAttendanceMode(),
        ]);
    }

    /**
     * Sync offline kiosk attendance logs.
     */
    public function syncOfflineLogs(SyncOfflineLogsRequest $request): JsonResponse
    {
        $worker = $request->user();
        $worker->load('department');

        if (!$worker->isWorker()) {
            return response()->json([
                'message' => 'Only workers can sync offline kiosk logs.',
            ], 403);
        }

        if (!Setting::isKioskMode()) {
            return response()->json([
                'message' => 'Kiosk mode is not enabled.',
            ], 403);
        }

        $result = $this->syncService->processOfflineLogs($worker, $request->validated('logs'));

        $syncedIds = array_merge(
            array_column($result['synced'], 'event_id'),
            $result['duplicates']
        );

        return response()->json([
            'message' => 'Sync completed.',
            'server_time' => now()->toIso8601String(),
            'stats' => [
                'success' => count($result['synced']),
                'failed' => count($result['errors']),
                'skipped' => count($result['duplicates']),
            ],
            'synced_ids' => $syncedIds,
            'errors' => $result['errors'],
        ]);
    }
}
