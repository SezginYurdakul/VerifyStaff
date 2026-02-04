<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SelfCheckRequest;
use App\Models\AttendanceLog;
use App\Models\Kiosk;
use App\Models\Setting;
use App\Models\User;
use App\Services\TotpService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private TotpService $totpService
    ) {}

    /**
     * Self check-in/check-out for kiosk mode.
     * Worker scans kiosk QR code and their phone sends the request.
     */
    public function selfCheck(SelfCheckRequest $request): JsonResponse
    {
        // Check if kiosk mode is enabled
        if (!Setting::isKioskMode()) {
            return response()->json([
                'message' => 'Kiosk mode is not enabled. Contact your administrator.',
            ], 403);
        }

        $worker = $request->user();
        $worker->load('department');

        // Only workers can use self-check
        if (!$worker->isWorker()) {
            return response()->json([
                'message' => 'Only workers can use self check-in/check-out.',
            ], 403);
        }

        // Verify kiosk TOTP code
        $kioskCode = $request->validated('kiosk_code');
        $kioskTotp = $request->validated('kiosk_totp');

        $kiosk = Kiosk::where('code', $kioskCode)
            ->where('status', 'active')
            ->first();

        if (!$kiosk) {
            return response()->json([
                'message' => 'Invalid kiosk code or kiosk is not active.',
            ], 400);
        }

        // Verify TOTP code
        $isValidTotp = $this->totpService->verifyCode($kiosk->secret_token, $kioskTotp);

        if (!$isValidTotp) {
            return response()->json([
                'message' => 'Invalid or expired kiosk code. Please scan again.',
            ], 401);
        }

        $deviceTime = Carbon::parse($request->validated('device_time'));

        // Auto-detect type based on last log (toggle mode)
        $type = $this->detectAttendanceType($worker->id, $deviceTime);

        // Generate event ID (no rep_id for self-check, use 0)
        $eventId = AttendanceLog::generateEventId(
            $worker->id,
            0, // No representative
            $request->validated('device_time'),
            $type
        );

        // Check for duplicate
        if (AttendanceLog::where('event_id', $eventId)->exists()) {
            return response()->json([
                'message' => 'Duplicate scan detected.',
                'type' => $type,
            ], 409);
        }

        // Get settings for calculations
        $config = Setting::getWorkHoursConfig();
        $duplicateScanWindow = $config['duplicate_scan_window_minutes'];

        // Check for anomalies
        $flagged = false;
        $flagReason = null;

        if ($deviceTime->isFuture()) {
            $flagged = true;
            $flagReason = 'Future timestamp detected';
        }

        // Check for duplicate scan within window
        $recentScan = AttendanceLog::where('worker_id', $worker->id)
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
            'worker_id' => $worker->id,
            'rep_id' => null, // Self check - no representative
            'type' => $type,
            'device_time' => $deviceTime,
            'device_timezone' => $request->validated('device_timezone') ?? 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
            'flagged' => $flagged,
            'flag_reason' => $flagReason,
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'kiosk_id' => $kiosk->code,
        ];

        // Calculate fields based on type
        // Use worker's department config if available, otherwise fall back to global settings
        $workerConfig = $worker->getWorkHoursConfig();
        $workerWorkStart = $workerConfig['work_start_time'];
        $workerWorkEnd = $workerConfig['work_end_time'];
        $workerLateThreshold = $workerConfig['late_threshold_minutes'];
        $workerRegularMinutes = $workerConfig['regular_work_minutes'];
        $workerEarlyDepartureThreshold = $workerConfig['early_departure_threshold_minutes'];

        if ($type === 'in') {
            $expectedStart = $deviceTime->copy()->setTimeFromTimeString($workerWorkStart);
            $graceEnd = $expectedStart->copy()->addMinutes($workerLateThreshold);
            $logData['is_late'] = $deviceTime->gt($graceEnd);
        } elseif ($type === 'out') {
            // Find matching check-in
            $checkIn = AttendanceLog::where('worker_id', $worker->id)
                ->where('type', 'in')
                ->whereNull('paired_log_id')
                ->where('device_time', '>=', $deviceTime->copy()->subHours(24))
                ->where('device_time', '<', $deviceTime)
                ->orderBy('device_time', 'desc')
                ->first();

            if ($checkIn) {
                $workMinutes = $checkIn->device_time->diffInMinutes($deviceTime);
                $logData['work_minutes'] = $workMinutes;
                $logData['paired_log_id'] = $checkIn->id;

                if ($workMinutes > $workerRegularMinutes) {
                    $logData['is_overtime'] = true;
                    $logData['overtime_minutes'] = $workMinutes - $workerRegularMinutes;
                } else {
                    $logData['is_overtime'] = false;
                    $logData['overtime_minutes'] = 0;
                }

                $expectedEnd = $deviceTime->copy()->setTimeFromTimeString($workerWorkEnd);
                $earlyThreshold = $expectedEnd->copy()->subMinutes($workerEarlyDepartureThreshold);
                $logData['is_early_departure'] = $deviceTime->lt($earlyThreshold);
            }
        }

        $attendanceLog = AttendanceLog::create($logData);

        // Update paired check-in if this is a check-out
        if ($type === 'out' && isset($checkIn)) {
            $checkIn->update(['paired_log_id' => $attendanceLog->id]);
        }

        return response()->json([
            'message' => $type === 'in' ? 'Check-in successful' : 'Check-out successful',
            'type' => $type,
            'worker_id' => $worker->id,
            'worker_name' => $worker->name,
            'kiosk_code' => $kiosk->code,
            'kiosk_name' => $kiosk->name,
            'device_time' => $deviceTime->toIso8601String(),
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

        // Get today's logs
        $todayLogs = AttendanceLog::where('worker_id', $worker->id)
            ->whereDate('device_time', $today)
            ->orderBy('device_time', 'desc')
            ->get();

        $lastLog = $todayLogs->first();

        // Calculate total work time today
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
     * Worker's device was offline when scanning kiosk QR, logs are synced later.
     */
    public function syncOfflineLogs(Request $request): JsonResponse
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

        $validated = $request->validate([
            'logs' => ['required', 'array', 'min:1'],
            'logs.*.kiosk_code' => ['required', 'string', 'max:20'],
            'logs.*.device_time' => ['required', 'date'],
            'logs.*.device_timezone' => ['sometimes', 'string', 'max:50'],
            'logs.*.event_id' => ['required', 'string', 'max:64'],
            'logs.*.scanned_totp' => ['sometimes', 'nullable', 'string', 'size:6'],
        ]);

        $synced = [];
        $duplicates = [];
        $errors = [];

        // Get global config for duplicate scan window
        $config = Setting::getWorkHoursConfig();
        $duplicateScanWindow = $config['duplicate_scan_window_minutes'];

        // Get worker's department config (or global fallback)
        $workerConfig = $worker->getWorkHoursConfig();
        $workStartTime = $workerConfig['work_start_time'];
        $workEndTime = $workerConfig['work_end_time'];
        $regularWorkMinutes = $workerConfig['regular_work_minutes'];
        $lateThresholdMinutes = $workerConfig['late_threshold_minutes'];
        $earlyDepartureThreshold = $workerConfig['early_departure_threshold_minutes'];

        foreach ($validated['logs'] as $log) {
            $kiosk = Kiosk::where('code', $log['kiosk_code'])
                ->where('status', 'active')
                ->first();

            if (!$kiosk) {
                $errors[] = [
                    'event_id' => $log['event_id'],
                    'reason' => 'Invalid or inactive kiosk: ' . $log['kiosk_code'],
                ];
                continue;
            }

            $deviceTime = Carbon::parse($log['device_time']);

            // Server-side in/out detection
            $type = $this->detectAttendanceType($worker->id, $deviceTime);

            // Generate server-side event_id
            $eventId = AttendanceLog::generateEventId(
                $worker->id,
                0,
                $log['device_time'],
                $type
            );

            if (AttendanceLog::where('event_id', $eventId)->exists()) {
                $duplicates[] = $log['event_id'];
                continue;
            }

            // Verify scanned TOTP against device_time to detect tampering
            $scannedTotp = $log['scanned_totp'] ?? null;
            $totpVerified = false;

            if ($scannedTotp) {
                $kioskTimeStep = $this->totpService->getKioskTimeStep();
                $totpVerified = $this->totpService->verifyCodeAtTime(
                    $kiosk->secret_token,
                    $scannedTotp,
                    $deviceTime->timestamp,
                    $kioskTimeStep
                );
            }

            // Flag based on TOTP verification result
            $flagged = !$totpVerified;
            $flagReason = $totpVerified
                ? null
                : ($scannedTotp
                    ? 'Offline kiosk sync - TOTP mismatch (possible tampering of device_time)'
                    : 'Offline kiosk sync - TOTP not provided');

            if ($deviceTime->isFuture()) {
                $flagged = true;
                $flagReason = ($flagReason ? $flagReason . '; ' : '') . 'Future timestamp detected';
            }

            $recentScan = AttendanceLog::where('worker_id', $worker->id)
                ->where('type', $type)
                ->whereBetween('device_time', [
                    $deviceTime->copy()->subMinutes($duplicateScanWindow),
                    $deviceTime->copy()->addMinutes($duplicateScanWindow)
                ])
                ->exists();

            if ($recentScan) {
                $flagged = true;
                $flagReason = ($flagReason ? $flagReason . '; ' : '') . "Duplicate scan within {$duplicateScanWindow} minutes";
            }

            $logData = [
                'event_id' => $eventId,
                'worker_id' => $worker->id,
                'rep_id' => null,
                'kiosk_id' => $kiosk->code,
                'type' => $type,
                'device_time' => $deviceTime,
                'device_timezone' => $log['device_timezone'] ?? 'UTC',
                'sync_time' => now(),
                'sync_status' => 'synced',
                'flagged' => $flagged,
                'flag_reason' => $flagReason,
            ];

            // Calculate fields based on type
            if ($type === 'in') {
                $expectedStart = $deviceTime->copy()->setTimeFromTimeString($workStartTime);
                $graceEnd = $expectedStart->copy()->addMinutes($lateThresholdMinutes);
                $logData['is_late'] = $deviceTime->gt($graceEnd);
            } elseif ($type === 'out') {
                $checkIn = AttendanceLog::where('worker_id', $worker->id)
                    ->where('type', 'in')
                    ->whereNull('paired_log_id')
                    ->where('device_time', '>=', $deviceTime->copy()->subHours(24))
                    ->where('device_time', '<', $deviceTime)
                    ->orderBy('device_time', 'desc')
                    ->first();

                if ($checkIn) {
                    $workMinutes = $checkIn->device_time->diffInMinutes($deviceTime);
                    $logData['work_minutes'] = $workMinutes;
                    $logData['paired_log_id'] = $checkIn->id;

                    if ($workMinutes > $regularWorkMinutes) {
                        $logData['is_overtime'] = true;
                        $logData['overtime_minutes'] = $workMinutes - $regularWorkMinutes;
                    } else {
                        $logData['is_overtime'] = false;
                        $logData['overtime_minutes'] = 0;
                    }

                    $expectedEnd = $deviceTime->copy()->setTimeFromTimeString($workEndTime);
                    $earlyThreshold = $expectedEnd->copy()->subMinutes($earlyDepartureThreshold);
                    $logData['is_early_departure'] = $deviceTime->lt($earlyThreshold);
                }
            }

            $attendanceLog = AttendanceLog::create($logData);

            if ($type === 'out' && isset($checkIn)) {
                $checkIn->update(['paired_log_id' => $attendanceLog->id]);
            }

            $synced[] = [
                'event_id' => $log['event_id'],
                'type' => $type,
            ];
        }

        return response()->json([
            'message' => 'Offline kiosk logs processed',
            'server_time' => now()->toIso8601String(),
            'synced_count' => count($synced),
            'duplicate_count' => count($duplicates),
            'error_count' => count($errors),
            'synced' => $synced,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ]);
    }

    /**
     * Detect attendance type based on worker's last log.
     */
    private function detectAttendanceType(int $workerId, Carbon $deviceTime): string
    {
        $lastLog = AttendanceLog::where('worker_id', $workerId)
            ->where('device_time', '>=', $deviceTime->copy()->subHours(24))
            ->where('device_time', '<', $deviceTime)
            ->orderBy('device_time', 'desc')
            ->first();

        if (!$lastLog || $lastLog->type === 'out') {
            return 'in';
        }

        return 'out';
    }
}
