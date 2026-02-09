<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncLogsRequest;
use App\Http\Resources\WorkerResource;
use App\Jobs\ProcessAttendanceSync;
use App\Models\User;
use App\Services\AttendanceSyncService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private AttendanceSyncService $syncService
    ) {}

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

        $result = $this->syncService->processRepLogs($logs, $user);

        $syncedIds = array_merge(
            array_map(fn ($log) => $log->event_id, $result['synced']),
            $result['duplicates']
        );

        // Log sync operation
        AuditLogger::sync('sync_completed', $user->id, [
            'synced_count' => count($result['synced']),
            'duplicate_count' => count($result['duplicates']),
            'error_count' => count($result['errors']),
        ]);

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

    private function syncLogsAsync(array $logs, User $user): JsonResponse
    {
        ProcessAttendanceSync::dispatch($logs, $user->id);

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

    public function getServerTime(): JsonResponse
    {
        return response()->json([
            'server_time' => now()->toIso8601String(),
            'timestamp' => now()->timestamp,
        ]);
    }
}
