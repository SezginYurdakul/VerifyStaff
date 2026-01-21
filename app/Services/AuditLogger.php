<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Log an attendance event (check-in/check-out).
     */
    public static function attendance(
        string $action,
        int $workerId,
        array $data = [],
        ?int $performedBy = null
    ): void {
        Log::channel('daily')->info("Attendance: {$action}", [
            'type' => 'attendance',
            'action' => $action,
            'worker_id' => $workerId,
            'performed_by' => $performedBy,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log a TOTP verification event.
     */
    public static function totpVerification(
        int $workerId,
        bool $success,
        ?int $verifiedBy = null
    ): void {
        $level = $success ? 'info' : 'warning';

        Log::channel('daily')->{$level}('TOTP: Verification attempt', [
            'type' => 'totp',
            'action' => 'verify',
            'worker_id' => $workerId,
            'success' => $success,
            'verified_by' => $verifiedBy,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log a settings change.
     */
    public static function settingsChange(
        string $key,
        mixed $oldValue,
        mixed $newValue,
        int $changedBy
    ): void {
        Log::channel('daily')->info('Settings: Changed', [
            'type' => 'settings',
            'action' => 'update',
            'key' => $key,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => $changedBy,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log an authentication event.
     */
    public static function auth(
        string $action,
        int $userId,
        bool $success = true,
        array $data = []
    ): void {
        $level = $success ? 'info' : 'warning';

        Log::channel('daily')->{$level}("Auth: {$action}", [
            'type' => 'auth',
            'action' => $action,
            'user_id' => $userId,
            'success' => $success,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log a security event (failed attempts, suspicious activity).
     */
    public static function security(
        string $event,
        array $data = [],
        ?int $userId = null
    ): void {
        Log::channel('daily')->warning("Security: {$event}", [
            'type' => 'security',
            'event' => $event,
            'user_id' => $userId,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log a work summary calculation.
     */
    public static function workSummary(
        string $action,
        int $workerId,
        string $periodType,
        array $data = []
    ): void {
        Log::channel('daily')->info("WorkSummary: {$action}", [
            'type' => 'work_summary',
            'action' => $action,
            'worker_id' => $workerId,
            'period_type' => $periodType,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log a sync operation.
     */
    public static function sync(
        string $action,
        int $userId,
        array $stats = []
    ): void {
        Log::channel('daily')->info("Sync: {$action}", [
            'type' => 'sync',
            'action' => $action,
            'user_id' => $userId,
            'stats' => $stats,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
