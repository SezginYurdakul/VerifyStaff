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
        if ($type === 'in') {
            $workStartTime = $config['work_start_time'];
            $lateThresholdMinutes = $config['late_threshold_minutes'];
            $expectedStart = $deviceTime->copy()->setTimeFromTimeString($workStartTime);
            $graceEnd = $expectedStart->copy()->addMinutes($lateThresholdMinutes);
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

                $regularWorkMinutes = $config['regular_work_minutes'];
                if ($workMinutes > $regularWorkMinutes) {
                    $logData['is_overtime'] = true;
                    $logData['overtime_minutes'] = $workMinutes - $regularWorkMinutes;
                } else {
                    $logData['is_overtime'] = false;
                    $logData['overtime_minutes'] = 0;
                }

                $workEndTime = $config['work_end_time'];
                $expectedEnd = $deviceTime->copy()->setTimeFromTimeString($workEndTime);
                $logData['is_early_departure'] = $deviceTime->lt($expectedEnd);
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
