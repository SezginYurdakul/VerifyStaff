<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TotpController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('time', [SyncController::class, 'getServerTime']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/refresh', [AuthController::class, 'refreshToken']);

        // Sync - for representatives
        Route::get('sync/staff', [SyncController::class, 'getStaffList']);
        Route::post('sync/logs', [SyncController::class, 'syncLogs']);

        // TOTP
        Route::get('totp/generate', [TotpController::class, 'generateCode']);
        Route::post('totp/verify', [TotpController::class, 'verifyCode']);

        // Reports - Single Worker
        Route::get('reports/summary/{workerId}/daily', [ReportsController::class, 'dailySummary']);
        Route::get('reports/summary/{workerId}/weekly', [ReportsController::class, 'weeklySummary']);
        Route::get('reports/summary/{workerId}/monthly', [ReportsController::class, 'monthlySummary']);
        Route::get('reports/logs/{workerId}', [ReportsController::class, 'workerLogs']);

        // Reports - All Workers
        Route::get('reports/all/daily', [ReportsController::class, 'allWorkersDailySummary']);
        Route::get('reports/all/weekly', [ReportsController::class, 'allWorkersWeeklySummary']);
        Route::get('reports/all/monthly', [ReportsController::class, 'allWorkersMonthlySummary']);
        Route::get('reports/all/yearly', [ReportsController::class, 'allWorkersYearlySummary']);

        // Reports - Flagged
        Route::get('reports/flagged', [ReportsController::class, 'flaggedLogs']);
    });
});
