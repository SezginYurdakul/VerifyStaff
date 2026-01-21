<?php

namespace App\Jobs;

use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public array $logs,
        public int $repId
    ) {}

    public function handle(): void
    {
        $processedWorkers = [];
        $syncTime = now();

        DB::transaction(function () use (&$processedWorkers, $syncTime) {
            foreach ($this->logs as $logData) {
                $deviceTime = Carbon::parse($logData['device_time']);

                // Auto-detect type if not provided (toggle mode)
                $type = $logData['type'] ?? $this->detectAttendanceType($logData['worker_id'], $deviceTime);

                $eventId = AttendanceLog::generateEventId(
                    $logData['worker_id'],
                    $this->repId,
                    $logData['device_time'],
                    $type
                );

                // Skip if already exists (idempotent)
                if (AttendanceLog::where('event_id', $eventId)->exists()) {
                    Log::info('Duplicate attendance log skipped', ['event_id' => $eventId]);
                    continue;
                }

                // Check for anomalies
                $flagged = false;
                $flagReason = null;

                // Future timestamp check
                if ($deviceTime->isFuture()) {
                    $flagged = true;
                    $flagReason = 'Future device timestamp';
                }

                // Check for duplicate scan (same worker, same type, within 5 minutes)
                $recentScan = AttendanceLog::where('worker_id', $logData['worker_id'])
                    ->where('type', $type)
                    ->whereBetween('device_time', [
                        $deviceTime->copy()->subMinutes(5),
                        $deviceTime->copy()->addMinutes(5)
                    ])
                    ->exists();

                if ($recentScan) {
                    $flagged = true;
                    $flagReason = 'Duplicate scan within 5 minutes';
                }

                AttendanceLog::create([
                    'event_id' => $eventId,
                    'worker_id' => $logData['worker_id'],
                    'rep_id' => $this->repId,
                    'type' => $type,
                    'device_time' => $deviceTime,
                    'device_timezone' => $logData['device_timezone'] ?? 'UTC',
                    'sync_time' => $syncTime,
                    'sync_attempt' => $logData['sync_attempt'] ?? 1,
                    'offline_duration_seconds' => $logData['offline_duration_seconds'] ?? 0,
                    'sync_status' => 'synced',
                    'flagged' => $flagged,
                    'flag_reason' => $flagReason,
                    'latitude' => $logData['latitude'] ?? null,
                    'longitude' => $logData['longitude'] ?? null,
                ]);

                // Track unique workers for summary calculation
                $workerDate = $deviceTime->format('Y-m-d');
                $processedWorkers[$logData['worker_id']][$workerDate] = true;
            }
        });

        // Dispatch work summary calculations for affected workers
        foreach ($processedWorkers as $workerId => $dates) {
            foreach (array_keys($dates) as $date) {
                CalculateWorkSummary::dispatch($workerId, $date, 'daily')
                    ->delay(now()->addSeconds(10));
            }
        }
    }

    public function tags(): array
    {
        return ['attendance-sync', 'rep:' . $this->repId];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAttendanceSync failed', [
            'rep_id' => $this->repId,
            'logs_count' => count($this->logs),
            'error' => $exception->getMessage(),
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
