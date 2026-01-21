<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncLogsRequest;
use App\Http\Resources\AttendanceLogResource;
use App\Http\Resources\WorkerResource;
use App\Jobs\ProcessAttendanceSync;
use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SyncController extends Controller
{

    public function getStaffList(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isRepresentative() && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only representatives can sync staff list.',
            ], 403);
        }

        $workers = User::where('role', 'worker')
            ->where('status', 'active')
            ->select(['id', 'name', 'email', 'secret_token', 'created_at', 'updated_at'])
            ->get();

        return response()->json([
            'message' => 'Staff list synced successfully',
            'server_time' => now()->toIso8601String(),
            'workers' => WorkerResource::collection($workers),
            'total' => $workers->count(),
        ]);
    }

    public function syncLogs(SyncLogsRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isRepresentative() && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only representatives can sync logs.',
            ], 403);
        }

        // Check if representative mode is enabled
        if (Setting::isKioskMode()) {
            return response()->json([
                'message' => 'System is in kiosk mode. Representative sync is disabled.',
                'attendance_mode' => 'kiosk',
            ], 403);
        }

        $logs = $request->validated('logs');
        $async = $request->boolean('async', false);

        // For large batches (>20 logs), use async processing
        if ($async || count($logs) > 20) {
            return $this->syncLogsAsync($logs, $user);
        }

        return $this->syncLogsSync($logs, $user);
    }

    private function syncLogsAsync(array $logs, User $user): JsonResponse
    {
        ProcessAttendanceSync::dispatch($logs, $user->id);

        // Log sync operation
        AuditLogger::sync('async_queued', $user->id, [
            'log_count' => count($logs),
        ]);

        return response()->json([
            'message' => 'Logs queued for processing',
            'server_time' => now()->toIso8601String(),
            'queued_count' => count($logs),
            'processing' => 'async',
        ], 202);
    }

    private function syncLogsSync(array $logs, User $user): JsonResponse
    {
        $synced = [];
        $duplicates = [];
        $errors = [];

        // Get settings from database
        $config = Setting::getWorkHoursConfig();
        $workStartTime = $config['work_start_time'];
        $workEndTime = $config['work_end_time'];
        $regularWorkMinutes = $config['regular_work_minutes'];
        $lateThresholdMinutes = $config['late_threshold_minutes'];
        $duplicateScanWindow = $config['duplicate_scan_window_minutes'];

        foreach ($logs as $log) {
            $worker = User::where('id', $log['worker_id'])
                ->where('role', 'worker')
                ->first();

            if (!$worker) {
                $errors[] = [
                    'worker_id' => $log['worker_id'],
                    'reason' => 'Worker not found',
                ];
                continue;
            }

            $deviceTime = Carbon::parse($log['device_time']);

            // Auto-detect type if not provided (toggle mode)
            $type = $log['type'] ?? $this->detectAttendanceType($log['worker_id'], $deviceTime);

            $eventId = AttendanceLog::generateEventId(
                $log['worker_id'],
                $user->id,
                $log['device_time'],
                $type
            );

            $existing = AttendanceLog::where('event_id', $eventId)->first();

            if ($existing) {
                $duplicates[] = $eventId;
                continue;
            }

            $flagged = false;
            $flagReason = null;

            if ($deviceTime->isFuture()) {
                $flagged = true;
                $flagReason = 'Future timestamp detected';
            }

            // Check for duplicate scan within configured window
            $recentScan = AttendanceLog::where('worker_id', $log['worker_id'])
                ->where('type', $type)
                ->whereBetween('device_time', [
                    $deviceTime->copy()->subMinutes($duplicateScanWindow),
                    $deviceTime->copy()->addMinutes($duplicateScanWindow)
                ])
                ->exists();

            if ($recentScan) {
                $flagged = true;
                $flagReason = "Duplicate scan within {$duplicateScanWindow} minutes";
            }

            $logData = [
                'event_id' => $eventId,
                'worker_id' => $log['worker_id'],
                'rep_id' => $user->id,
                'type' => $type,
                'device_time' => $deviceTime,
                'device_timezone' => $log['device_timezone'] ?? 'UTC',
                'sync_time' => now(),
                'sync_attempt' => $log['sync_attempt'] ?? 1,
                'offline_duration_seconds' => $log['offline_duration_seconds'] ?? 0,
                'sync_status' => 'synced',
                'flagged' => $flagged,
                'flag_reason' => $flagReason,
                'latitude' => $log['latitude'] ?? null,
                'longitude' => $log['longitude'] ?? null,
            ];

            // Calculate fields based on type
            if ($type === 'in') {
                // Check for late arrival using settings
                $expectedStart = $deviceTime->copy()->setTimeFromTimeString($workStartTime);
                $graceEnd = $expectedStart->copy()->addMinutes($lateThresholdMinutes);
                $logData['is_late'] = $deviceTime->gt($graceEnd);
            } elseif ($type === 'out') {
                // Find matching check-in (unpaired, same day, before this check-out)
                $checkIn = AttendanceLog::where('worker_id', $log['worker_id'])
                    ->where('type', 'in')
                    ->whereNull('paired_log_id')
                    ->whereDate('device_time', $deviceTime->toDateString())
                    ->where('device_time', '<', $deviceTime)
                    ->orderBy('device_time', 'desc')
                    ->first();

                if ($checkIn) {
                    // Calculate work duration
                    $workMinutes = $checkIn->device_time->diffInMinutes($deviceTime);
                    $logData['work_minutes'] = $workMinutes;
                    $logData['paired_log_id'] = $checkIn->id;

                    // Check for overtime using settings
                    if ($workMinutes > $regularWorkMinutes) {
                        $logData['is_overtime'] = true;
                        $logData['overtime_minutes'] = $workMinutes - $regularWorkMinutes;
                    } else {
                        $logData['is_overtime'] = false;
                        $logData['overtime_minutes'] = 0;
                    }

                    // Check for early departure using settings
                    $expectedEnd = $deviceTime->copy()->setTimeFromTimeString($workEndTime);
                    $logData['is_early_departure'] = $deviceTime->lt($expectedEnd);
                }
            }

            $attendanceLog = AttendanceLog::create($logData);

            // If this is a check-out with a paired check-in, update the check-in's paired_log_id
            if ($type === 'out' && isset($checkIn)) {
                $checkIn->update(['paired_log_id' => $attendanceLog->id]);
            }

            $synced[] = new AttendanceLogResource($attendanceLog);
        }

        // Log sync operation
        AuditLogger::sync('sync_completed', $user->id, [
            'synced_count' => count($synced),
            'duplicate_count' => count($duplicates),
            'error_count' => count($errors),
        ]);

        return response()->json([
            'message' => 'Logs processed successfully',
            'server_time' => now()->toIso8601String(),
            'synced_count' => count($synced),
            'duplicate_count' => count($duplicates),
            'error_count' => count($errors),
            'synced' => $synced,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ]);
    }

    public function getServerTime(): JsonResponse
    {
        return response()->json([
            'server_time' => now()->toIso8601String(),
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Detect attendance type based on worker's last log.
     * Toggle mode: if last log was 'in', return 'out' and vice versa.
     * Supports night shifts (check-in on day X, check-out on day X+1).
     */
    private function detectAttendanceType(int $workerId, Carbon $deviceTime): string
    {
        // Get the most recent log for this worker (last 24 hours to support night shifts)
        $lastLog = AttendanceLog::where('worker_id', $workerId)
            ->where('device_time', '>=', $deviceTime->copy()->subHours(24))
            ->where('device_time', '<', $deviceTime)
            ->orderBy('device_time', 'desc')
            ->first();

        // If no recent log or last was 'out', this should be 'in'
        // If last was 'in' (still open), this should be 'out'
        if (!$lastLog || $lastLog->type === 'out') {
            return 'in';
        }

        return 'out';
    }
}
