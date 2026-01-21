<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\KioskController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TotpController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('time', [SyncController::class, 'getServerTime']);

    // Kiosk - public endpoint for kiosk device to generate QR code
    Route::get('kiosk/{kioskCode}/code', [KioskController::class, 'generateCode']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/refresh', [AuthController::class, 'refreshToken']);

        // Sync - for representatives (only works in representative mode)
        Route::get('sync/staff', [SyncController::class, 'getStaffList']);
        Route::post('sync/logs', [SyncController::class, 'syncLogs']);

        // Attendance - for workers (kiosk mode)
        Route::post('attendance/self-check', [AttendanceController::class, 'selfCheck']);
        Route::get('attendance/status', [AttendanceController::class, 'status']);

        // TOTP
        Route::get('totp/generate', [TotpController::class, 'generateCode']);
        Route::post('totp/verify', [TotpController::class, 'verifyCode']);

        // Reports - Single Worker
        Route::get('reports/summary/{workerId}/daily', [ReportsController::class, 'dailySummary']);
        Route::get('reports/summary/{workerId}/weekly', [ReportsController::class, 'weeklySummary']);
        Route::get('reports/summary/{workerId}/monthly', [ReportsController::class, 'monthlySummary']);
        Route::get('reports/summary/{workerId}/yearly', [ReportsController::class, 'yearlySummary']);
        Route::get('reports/logs/{workerId}', [ReportsController::class, 'workerLogs']);

        // Reports - All Workers
        Route::get('reports/all/daily', [ReportsController::class, 'allWorkersDailySummary']);
        Route::get('reports/all/weekly', [ReportsController::class, 'allWorkersWeeklySummary']);
        Route::get('reports/all/monthly', [ReportsController::class, 'allWorkersMonthlySummary']);
        Route::get('reports/all/yearly', [ReportsController::class, 'allWorkersYearlySummary']);

        // Reports - Flagged
        Route::get('reports/flagged', [ReportsController::class, 'flaggedLogs']);

        // Settings - Work hours config (for representatives)
        Route::get('settings/work-hours', [SettingsController::class, 'workHours']);
        Route::get('settings/attendance-mode', [SettingsController::class, 'attendanceMode']);

        // Settings - Admin only
        Route::get('settings', [SettingsController::class, 'index']);
        Route::get('settings/group/{group}', [SettingsController::class, 'group']);
        Route::get('settings/{key}', [SettingsController::class, 'show']);
        Route::put('settings/{key}', [SettingsController::class, 'update']);
        Route::put('settings', [SettingsController::class, 'updateBulk']);
        Route::put('settings/config/shifts', [SettingsController::class, 'updateShifts']);
        Route::put('settings/config/working-days', [SettingsController::class, 'updateWorkingDays']);
        Route::put('settings/config/attendance-mode', [SettingsController::class, 'updateAttendanceMode']);

        // Kiosk management - Admin only
        Route::get('kiosks', [KioskController::class, 'index']);
        Route::post('kiosks', [KioskController::class, 'store']);
        Route::get('kiosks/{kioskCode}', [KioskController::class, 'show']);
        Route::put('kiosks/{kioskCode}', [KioskController::class, 'update']);
        Route::post('kiosks/{kioskCode}/regenerate-token', [KioskController::class, 'regenerateToken']);
    });
});
