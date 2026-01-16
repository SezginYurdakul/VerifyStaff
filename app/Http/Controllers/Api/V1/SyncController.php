<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncLogsRequest;
use App\Http\Resources\AttendanceLogResource;
use App\Http\Resources\WorkerResource;
use App\Jobs\CalculateWorkSummary;
use App\Jobs\ProcessAttendanceSync;
use App\Models\AttendanceLog;
use App\Models\User;
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
        $processedWorkers = [];

        foreach ($logs as $log) {
            $eventId = AttendanceLog::generateEventId(
                $log['worker_id'],
                $user->id,
                $log['device_time'],
                $log['type']
            );

            $existing = AttendanceLog::where('event_id', $eventId)->first();

            if ($existing) {
                $duplicates[] = $eventId;
                continue;
            }

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
            $flagged = false;
            $flagReason = null;

            if ($deviceTime->isFuture()) {
                $flagged = true;
                $flagReason = 'Future timestamp detected';
            }

            // Check for duplicate scan within 5 minutes
            $recentScan = AttendanceLog::where('worker_id', $log['worker_id'])
                ->where('type', $log['type'])
                ->whereBetween('device_time', [
                    $deviceTime->copy()->subMinutes(5),
                    $deviceTime->copy()->addMinutes(5)
                ])
                ->exists();

            if ($recentScan) {
                $flagged = true;
                $flagReason = 'Duplicate scan within 5 minutes';
            }

            $attendanceLog = AttendanceLog::create([
                'event_id' => $eventId,
                'worker_id' => $log['worker_id'],
                'rep_id' => $user->id,
                'type' => $log['type'],
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
            ]);

            $synced[] = new AttendanceLogResource($attendanceLog);

            // Track workers for summary calculation
            $workerDate = $deviceTime->format('Y-m-d');
            $processedWorkers[$log['worker_id']][$workerDate] = true;
        }

        // Dispatch work summary calculations
        foreach ($processedWorkers as $workerId => $dates) {
            foreach (array_keys($dates) as $date) {
                CalculateWorkSummary::dispatch($workerId, $date, 'daily')
                    ->delay(now()->addSeconds(5));
            }
        }

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
}
