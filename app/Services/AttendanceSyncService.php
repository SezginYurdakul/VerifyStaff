<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Kiosk;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceSyncService
{
    public function __construct(
        private TotpService $totpService
    ) {}

    /**
     * Process a single self-check (kiosk mode, worker scans kiosk QR).
     * Returns array with log data and response info, or throws on duplicate.
     */
    public function processSelfCheck(User $worker, array $validated): array
    {
        $deviceTime = Carbon::parse($validated['device_time']);
        $kiosk = Kiosk::where('code', $validated['kiosk_code'])
            ->where('status', 'active')
            ->first();

        if (!$kiosk) {
            return ['error' => 'Invalid kiosk code or kiosk is not active.', 'status' => 400];
        }

        $isValidTotp = $this->totpService->verifyCode($kiosk->secret_token, $validated['kiosk_totp']);
        if (!$isValidTotp) {
            return ['error' => 'Invalid or expired kiosk code. Please scan again.', 'status' => 401];
        }

        $type = $this->detectAttendanceType($worker->id, $deviceTime);

        $eventId = AttendanceLog::generateEventId(
            $worker->id,
            0,
            $validated['device_time'],
            $type
        );

        if (AttendanceLog::where('event_id', $eventId)->exists()) {
            return ['error' => 'Duplicate scan detected.', 'status' => 409, 'type' => $type];
        }

        $config = Setting::getWorkHoursConfig();
        $flagResult = $this->calculateFlags($worker->id, $type, $deviceTime, $config['duplicate_scan_window_minutes']);

        $logData = [
            'event_id' => $eventId,
            'worker_id' => $worker->id,
            'rep_id' => null,
            'type' => $type,
            'device_time' => $deviceTime,
            'device_timezone' => $validated['device_timezone'] ?? 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
            'flagged' => $flagResult['flagged'],
            'flag_reason' => $flagResult['flag_reason'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'kiosk_id' => $kiosk->code,
        ];

        $workerConfig = $worker->getWorkHoursConfig();
        $this->applyAttendanceCalculations($logData, $type, $worker->id, $deviceTime, $workerConfig);

        $attendanceLog = AttendanceLog::create($logData);
        $this->updatePairing($type, $attendanceLog, $logData);

        return [
            'success' => true,
            'type' => $type,
            'log' => $attendanceLog,
            'kiosk' => $kiosk,
            'log_data' => $logData,
        ];
    }

    /**
     * Process representative sync logs (batch, with transaction).
     */
    public function processRepLogs(array $logs, User $rep): array
    {
        $synced = [];
        $duplicates = [];
        $errors = [];

        $config = Setting::getWorkHoursConfig();
        $duplicateScanWindow = $config['duplicate_scan_window_minutes'];

        // Bulk load all referenced workers
        $workerIds = array_unique(array_column($logs, 'worker_id'));
        $workers = User::with('department')
            ->whereIn('id', $workerIds)
            ->where('role', 'worker')
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($logs, $rep, $duplicateScanWindow, $workers, &$synced, &$duplicates, &$errors) {
            foreach ($logs as $log) {
                $worker = $workers->get($log['worker_id']);

                if (!$worker) {
                    $errors[] = [
                        'worker_id' => $log['worker_id'],
                        'reason' => 'Worker not found',
                    ];
                    continue;
                }

                $deviceTime = Carbon::parse($log['device_time']);
                $type = $log['type'] ?? $this->detectAttendanceType($log['worker_id'], $deviceTime);

                $eventId = AttendanceLog::generateEventId(
                    $log['worker_id'],
                    $rep->id,
                    $log['device_time'],
                    $type
                );

                if (AttendanceLog::where('event_id', $eventId)->exists()) {
                    $duplicates[] = $eventId;
                    continue;
                }

                // TOTP verification for representative mode
                $totpResult = $this->verifyRepTotp(
                    $log['scanned_totp'] ?? null,
                    $worker->secret_token,
                    $deviceTime
                );

                $flagResult = $this->calculateFlags(
                    $log['worker_id'],
                    $type,
                    $deviceTime,
                    $duplicateScanWindow,
                    $totpResult['flagged'],
                    $totpResult['flag_reason']
                );

                $logData = [
                    'event_id' => $eventId,
                    'worker_id' => $log['worker_id'],
                    'rep_id' => $rep->id,
                    'type' => $type,
                    'device_time' => $deviceTime,
                    'device_timezone' => $log['device_timezone'] ?? 'UTC',
                    'sync_time' => now(),
                    'sync_attempt' => $log['sync_attempt'] ?? 1,
                    'offline_duration_seconds' => $log['offline_duration_seconds'] ?? 0,
                    'sync_status' => 'synced',
                    'flagged' => $flagResult['flagged'],
                    'flag_reason' => $flagResult['flag_reason'],
                    'latitude' => $log['latitude'] ?? null,
                    'longitude' => $log['longitude'] ?? null,
                ];

                $workerConfig = $worker->getWorkHoursConfig();
                $this->applyAttendanceCalculations($logData, $type, $log['worker_id'], $deviceTime, $workerConfig, 'date');

                $attendanceLog = AttendanceLog::create($logData);
                $this->updatePairing($type, $attendanceLog, $logData);

                $synced[] = $attendanceLog;
            }
        });

        return [
            'synced' => $synced,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }

    /**
     * Process offline kiosk logs (batch, with transaction).
     */
    public function processOfflineLogs(User $worker, array $logs): array
    {
        $synced = [];
        $duplicates = [];
        $errors = [];

        $config = Setting::getWorkHoursConfig();
        $duplicateScanWindow = $config['duplicate_scan_window_minutes'];
        $workerConfig = $worker->getWorkHoursConfig();

        // Bulk load all referenced kiosks
        $kioskCodes = array_unique(array_column($logs, 'kiosk_code'));
        $kiosks = Kiosk::whereIn('code', $kioskCodes)
            ->where('status', 'active')
            ->get()
            ->keyBy('code');

        DB::transaction(function () use ($logs, $worker, $duplicateScanWindow, $kiosks, $workerConfig, &$synced, &$duplicates, &$errors) {
            foreach ($logs as $log) {
                $kiosk = $kiosks->get($log['kiosk_code']);

                if (!$kiosk) {
                    $errors[] = [
                        'event_id' => $log['event_id'],
                        'reason' => 'Invalid or inactive kiosk: ' . $log['kiosk_code'],
                    ];
                    continue;
                }

                $deviceTime = Carbon::parse($log['device_time']);
                $type = $this->detectAttendanceType($worker->id, $deviceTime);

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

                // TOTP verification for offline kiosk mode
                $totpResult = $this->verifyKioskTotp(
                    $log['scanned_totp'] ?? null,
                    $kiosk->secret_token,
                    $deviceTime
                );

                $flagResult = $this->calculateFlags(
                    $worker->id,
                    $type,
                    $deviceTime,
                    $duplicateScanWindow,
                    $totpResult['flagged'],
                    $totpResult['flag_reason']
                );

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
                    'flagged' => $flagResult['flagged'],
                    'flag_reason' => $flagResult['flag_reason'],
                ];

                $this->applyAttendanceCalculations($logData, $type, $worker->id, $deviceTime, $workerConfig);

                $attendanceLog = AttendanceLog::create($logData);
                $this->updatePairing($type, $attendanceLog, $logData);

                $synced[] = [
                    'event_id' => $log['event_id'],
                    'type' => $type,
                ];
            }
        });

        return [
            'synced' => $synced,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }

    /**
     * Detect attendance type based on worker's last log (toggle mode).
     */
    public function detectAttendanceType(int $workerId, Carbon $deviceTime): string
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

    /**
     * Calculate anomaly flags (future timestamp, duplicate scan window).
     */
    private function calculateFlags(
        int $workerId,
        string $type,
        Carbon $deviceTime,
        int $duplicateScanWindow,
        bool $initialFlagged = false,
        ?string $initialReason = null,
    ): array {
        $flagged = $initialFlagged;
        $flagReason = $initialReason;

        if ($deviceTime->isFuture()) {
            $flagged = true;
            $flagReason = ($flagReason ? $flagReason . '; ' : '') . 'Future timestamp detected';
        }

        $recentScan = AttendanceLog::where('worker_id', $workerId)
            ->where('type', $type)
            ->whereBetween('device_time', [
                $deviceTime->copy()->subMinutes($duplicateScanWindow),
                $deviceTime->copy()->addMinutes($duplicateScanWindow),
            ])
            ->exists();

        if ($recentScan) {
            $flagged = true;
            $flagReason = ($flagReason ? $flagReason . '; ' : '') . "Duplicate scan within {$duplicateScanWindow} minutes";
        }

        return ['flagged' => $flagged, 'flag_reason' => $flagReason];
    }

    /**
     * Verify TOTP for representative mode (worker's secret_token).
     */
    private function verifyRepTotp(?string $scannedTotp, ?string $secretToken, Carbon $deviceTime): array
    {
        $totpVerified = false;

        if ($scannedTotp && $secretToken) {
            $totpVerified = $this->totpService->verifyCodeAtTime(
                $secretToken,
                $scannedTotp,
                $deviceTime->timestamp
            );
        }

        $flagged = !$totpVerified;
        $flagReason = $totpVerified
            ? null
            : ($scannedTotp
                ? 'TOTP mismatch - possible tampering of device_time'
                : 'TOTP not provided');

        return ['flagged' => $flagged, 'flag_reason' => $flagReason];
    }

    /**
     * Verify TOTP for offline kiosk mode (kiosk's secret_token).
     */
    private function verifyKioskTotp(?string $scannedTotp, string $kioskSecretToken, Carbon $deviceTime): array
    {
        $totpVerified = false;

        if ($scannedTotp) {
            $kioskTimeStep = $this->totpService->getKioskTimeStep();
            $totpVerified = $this->totpService->verifyCodeAtTime(
                $kioskSecretToken,
                $scannedTotp,
                $deviceTime->timestamp,
                $kioskTimeStep
            );
        }

        $flagged = !$totpVerified;
        $flagReason = $totpVerified
            ? null
            : ($scannedTotp
                ? 'Offline kiosk sync - TOTP mismatch (possible tampering of device_time)'
                : 'Offline kiosk sync - TOTP not provided');

        return ['flagged' => $flagged, 'flag_reason' => $flagReason];
    }

    /**
     * Apply late/overtime/early departure calculations to log data.
     */
    private function applyAttendanceCalculations(
        array &$logData,
        string $type,
        int $workerId,
        Carbon $deviceTime,
        array $workerConfig,
        string $pairingMode = '24h',
    ): void {
        $workStart = $workerConfig['work_start_time'];
        $workEnd = $workerConfig['work_end_time'];
        $lateThreshold = $workerConfig['late_threshold_minutes'];
        $regularMinutes = $workerConfig['regular_work_minutes'];
        $earlyDepartureThreshold = $workerConfig['early_departure_threshold_minutes'];

        if ($type === 'in') {
            $expectedStart = $deviceTime->copy()->setTimeFromTimeString($workStart);
            $graceEnd = $expectedStart->copy()->addMinutes($lateThreshold);
            $logData['is_late'] = $deviceTime->gt($graceEnd);
        } elseif ($type === 'out') {
            $query = AttendanceLog::where('worker_id', $workerId)
                ->where('type', 'in')
                ->whereNull('paired_log_id')
                ->where('device_time', '<', $deviceTime)
                ->orderBy('device_time', 'desc');

            if ($pairingMode === 'date') {
                $query->whereDate('device_time', $deviceTime->toDateString());
            } else {
                $query->where('device_time', '>=', $deviceTime->copy()->subHours(24));
            }

            $checkIn = $query->first();

            if ($checkIn) {
                $workMinutes = $checkIn->device_time->diffInMinutes($deviceTime);
                $logData['work_minutes'] = $workMinutes;
                $logData['paired_log_id'] = $checkIn->id;

                if ($workMinutes > $regularMinutes) {
                    $logData['is_overtime'] = true;
                    $logData['overtime_minutes'] = $workMinutes - $regularMinutes;
                } else {
                    $logData['is_overtime'] = false;
                    $logData['overtime_minutes'] = 0;
                }

                $expectedEnd = $deviceTime->copy()->setTimeFromTimeString($workEnd);
                $earlyThreshold = $expectedEnd->copy()->subMinutes($earlyDepartureThreshold);
                $logData['is_early_departure'] = $deviceTime->lt($earlyThreshold);
            }
        }
    }

    /**
     * Update paired check-in's paired_log_id after creating a check-out.
     */
    private function updatePairing(string $type, AttendanceLog $attendanceLog, array $logData): void
    {
        if ($type === 'out' && isset($logData['paired_log_id'])) {
            AttendanceLog::where('id', $logData['paired_log_id'])
                ->update(['paired_log_id' => $attendanceLog->id]);
        }
    }
}
