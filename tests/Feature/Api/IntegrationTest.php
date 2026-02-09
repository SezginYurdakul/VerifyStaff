<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Kiosk;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkSummary;
use App\Services\TotpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new TotpService();
        Mail::fake();
    }

    // ==================== 1. Invite → Login Flow ====================

    public function test_full_invite_to_login_flow(): void
    {
        // Step 1: Admin creates a worker
        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        $createResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Ali Yılmaz',
                'email' => 'ali@example.com',
                'phone' => '5551234567',
                'role' => 'worker',
            ]);

        $createResponse->assertStatus(201);
        $worker = User::where('email', 'ali@example.com')->first();
        $this->assertNotNull($worker->invite_token);
        $this->assertNull($worker->password);

        // Step 2: Validate invite token
        $validateResponse = $this->postJson('/api/v1/invite/validate', [
            'token' => $worker->invite_token,
        ]);

        $validateResponse->assertOk()
            ->assertJson([
                'valid' => true,
                'user' => [
                    'name' => 'Ali Yılmaz',
                    'email' => 'ali@example.com',
                ],
            ]);

        // Step 3: Accept invite and set password
        $acceptResponse = $this->postJson('/api/v1/invite/accept', [
            'token' => $worker->invite_token,
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ]);

        $acceptResponse->assertOk()
            ->assertJson(['message' => 'Password set successfully'])
            ->assertJsonStructure(['token']);

        // Step 4: Verify invite token is cleared
        $worker->refresh();
        $this->assertNull($worker->invite_token);
        $this->assertNotNull($worker->invite_accepted_at);
        $this->assertNotNull($worker->password);

        // Step 5: Use the token from invite/accept to access protected endpoint
        $inviteToken = $acceptResponse->json('token');

        // Reset auth guard state before switching from admin to worker context
        $this->app['auth']->forgetGuards();

        $meResponse = $this->withHeader('Authorization', "Bearer {$inviteToken}")
            ->getJson('/api/v1/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('user.email', 'ali@example.com');

        // Step 6: Login with the new password (separate session)
        // Note: login() deletes all existing tokens, so we must forget guards again
        $this->app['auth']->forgetGuards();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ali@example.com',
            'password' => 'SecurePass123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'ali@example.com');
    }

    // ==================== 2. Representative QR Scan Check-in/out ====================

    public function test_representative_scans_worker_qr_for_checkin_and_checkout(): void
    {
        // Setup: Representative mode
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'representative', 'group' => 'attendance']
        );

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);

        // Step 1: Representative gets staff list (includes worker secret_tokens)
        $staffResponse = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->getJson('/api/v1/sync/staff');

        $staffResponse->assertOk();
        $this->assertGreaterThanOrEqual(1, $staffResponse->json('total'));

        // Step 2: Worker generates TOTP code (shown as QR on their phone)
        // Use the TOTP service directly (simulating QR display on worker's phone)
        $totpCode = $this->totpService->generateCode($worker->secret_token)['code'];

        // Step 3: Representative verifies the TOTP code (scans worker's QR)
        $verifyResponse = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => $worker->id,
                'code' => $totpCode,
            ]);

        $verifyResponse->assertOk()
            ->assertJson(['valid' => true]);

        // Step 4: Representative syncs check-in log
        $checkInTime = now()->setTime(9, 5, 0);
        Carbon::setTestNow($checkInTime);

        $syncInResponse = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'device_time' => $checkInTime->toIso8601String(),
                        'device_timezone' => 'Europe/Istanbul',
                        'scanned_totp' => $totpCode,
                    ],
                ],
            ]);

        $syncInResponse->assertOk()
            ->assertJson([
                'stats' => [
                    'success' => 1,
                    'failed' => 0,
                ],
            ]);

        // Verify check-in log created
        $checkInLog = AttendanceLog::where('worker_id', $worker->id)
            ->where('type', 'in')
            ->first();
        $this->assertNotNull($checkInLog);
        $this->assertEquals($rep->id, $checkInLog->rep_id);

        // Step 5: Representative syncs check-out log (8 hours later)
        $checkOutTime = $checkInTime->copy()->addHours(8);
        Carbon::setTestNow($checkOutTime);

        $syncOutResponse = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'device_time' => $checkOutTime->toIso8601String(),
                        'device_timezone' => 'Europe/Istanbul',
                    ],
                ],
            ]);

        $syncOutResponse->assertOk()
            ->assertJson(['stats' => ['success' => 1]]);

        // Step 6: Verify check-out log with correct work_minutes
        $checkOutLog = AttendanceLog::where('worker_id', $worker->id)
            ->where('type', 'out')
            ->first();
        $this->assertNotNull($checkOutLog);
        $this->assertEquals(480, $checkOutLog->work_minutes); // 8 hours
        $this->assertEquals($checkInLog->id, $checkOutLog->paired_log_id);

        // Step 7: Check-in log is also paired
        $checkInLog->refresh();
        $this->assertEquals($checkOutLog->id, $checkInLog->paired_log_id);

        Carbon::setTestNow(); // Reset
    }

    // ==================== 3. Kiosk Check-in/out → Report ====================

    public function test_kiosk_checkin_checkout_reflects_in_daily_report(): void
    {
        // Setup: Kiosk mode
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'kiosk', 'group' => 'attendance']
        );

        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Main Entrance',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $workerToken = $worker->createToken('auth_token')->plainTextToken;

        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        // Step 1: Worker checks in at 09:00
        $checkInTime = Carbon::today()->setTime(9, 0, 0);
        Carbon::setTestNow($checkInTime);
        $kioskTotp = $this->totpService->generateCode($kiosk->secret_token)['code'];

        $checkInResponse = $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => $checkInTime->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => $kioskTotp,
            ]);

        $checkInResponse->assertOk()
            ->assertJson([
                'type' => 'in',
                'worker_name' => $worker->name,
            ]);

        // Step 2: Worker checks out at 18:00 (9 hours later)
        $checkOutTime = Carbon::today()->setTime(18, 0, 0);
        Carbon::setTestNow($checkOutTime);
        $kioskTotp2 = $this->totpService->generateCode($kiosk->secret_token)['code'];

        $checkOutResponse = $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => $checkOutTime->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => $kioskTotp2,
            ]);

        $checkOutResponse->assertOk()
            ->assertJson([
                'type' => 'out',
                'work_minutes' => 540, // 9 hours
            ]);

        // Step 3: Check attendance status
        $statusResponse = $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->getJson('/api/v1/attendance/status');

        $statusResponse->assertOk()
            ->assertJson([
                'current_status' => 'checked_out',
                'today_summary' => [
                    'total_minutes' => 540,
                    'total_hours' => 9,
                    'formatted_time' => '9:00',
                ],
            ]);

        // Step 4: Admin views daily report — should reflect the attendance
        $reportResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/daily?date=" . Carbon::today()->format('Y-m-d'));

        $reportResponse->assertOk()
            ->assertJson([
                'period' => 'daily',
                'summary' => [
                    'total_minutes' => 540,
                    'total_hours' => 9,
                ],
            ]);

        Carbon::setTestNow();
    }

    // ==================== 4. Offline Kiosk Sync ====================

    public function test_offline_kiosk_logs_sync_and_appear_in_reports(): void
    {
        // Setup: Kiosk mode
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'kiosk', 'group' => 'attendance']
        );

        $kiosk = Kiosk::create([
            'code' => 'KIOSK002',
            'name' => 'Side Entrance',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $workerToken = $worker->createToken('auth_token')->plainTextToken;

        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        // Step 1: Worker scans kiosk while offline (check-in at 08:30)
        $checkInTime = Carbon::today()->setTime(8, 30, 0);

        // Step 2: Worker scans kiosk while offline (check-out at 17:30)
        $checkOutTime = Carbon::today()->setTime(17, 30, 0);

        // Step 3: Worker comes online and syncs both logs at once
        $syncResponse = $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => $checkInTime->toIso8601String(),
                        'device_timezone' => 'Europe/Istanbul',
                        'event_id' => 'offline-checkin-1',
                    ],
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => $checkOutTime->toIso8601String(),
                        'device_timezone' => 'Europe/Istanbul',
                        'event_id' => 'offline-checkout-1',
                    ],
                ],
            ]);

        $syncResponse->assertOk()
            ->assertJson([
                'stats' => [
                    'success' => 2,
                    'failed' => 0,
                    'skipped' => 0,
                ],
            ]);

        // Step 4: Verify logs created correctly
        $logs = AttendanceLog::where('worker_id', $worker->id)
            ->orderBy('device_time')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertEquals('in', $logs[0]->type);
        $this->assertEquals('out', $logs[1]->type);

        // Offline logs should be flagged (no TOTP verification)
        $this->assertTrue($logs[0]->flagged);
        $this->assertTrue($logs[1]->flagged);

        // Check-out should have work_minutes calculated
        $this->assertEquals(540, $logs[1]->work_minutes); // 9 hours

        // Logs should be paired
        $this->assertEquals($logs[0]->id, $logs[1]->paired_log_id);
        $logs[0]->refresh();
        $this->assertEquals($logs[1]->id, $logs[0]->paired_log_id);

        // Step 5: Flagged logs should appear in admin's flagged report
        // Reset auth guard state before switching users
        $this->app['auth']->forgetGuards();

        $flaggedResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/v1/reports/flagged');

        $flaggedResponse->assertOk();
        $this->assertGreaterThanOrEqual(2, $flaggedResponse->json('total'));

        // Step 6: Daily report should also reflect the synced data
        $reportResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/daily?date=" . Carbon::today()->format('Y-m-d'));

        $reportResponse->assertOk()
            ->assertJson([
                'summary' => [
                    'total_minutes' => 540,
                ],
            ]);

        // Step 7: Duplicate sync should be detected
        // Reset auth guard state before switching back from admin to worker
        $this->app['auth']->forgetGuards();

        $duplicateResponse = $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => $checkInTime->toIso8601String(),
                        'event_id' => 'offline-checkin-retry',
                    ],
                ],
            ]);

        $duplicateResponse->assertOk()
            ->assertJson([
                'stats' => [
                    'success' => 0,
                    'skipped' => 1,
                ],
            ]);
    }

    // ==================== 5. Reporting Flow (daily → weekly → monthly) ====================

    public function test_attendance_data_flows_through_all_report_levels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        // Create 5 days of attendance (Mon-Fri of this week)
        $monday = Carbon::today()->startOfWeek();

        for ($i = 0; $i < 5; $i++) {
            $date = $monday->copy()->addDays($i);

            // Check-in at 09:00
            $checkInTime = $date->copy()->setTime(9, 0, 0);
            AttendanceLog::create([
                'event_id' => "week-in-{$i}",
                'worker_id' => $worker->id,
                'rep_id' => $rep->id,
                'type' => 'in',
                'device_time' => $checkInTime,
                'device_timezone' => 'Europe/Istanbul',
                'sync_time' => $checkInTime,
                'sync_status' => 'synced',
                'is_late' => false,
            ]);

            // Check-out at 18:00 (9 hours)
            $checkOutTime = $date->copy()->setTime(18, 0, 0);
            $checkIn = AttendanceLog::where('event_id', "week-in-{$i}")->first();

            AttendanceLog::create([
                'event_id' => "week-out-{$i}",
                'worker_id' => $worker->id,
                'rep_id' => $rep->id,
                'type' => 'out',
                'device_time' => $checkOutTime,
                'device_timezone' => 'Europe/Istanbul',
                'sync_time' => $checkOutTime,
                'sync_status' => 'synced',
                'work_minutes' => 540,
                'paired_log_id' => $checkIn->id,
                'is_overtime' => true,
                'overtime_minutes' => 60, // 540 - 480 = 60 minutes overtime
                'is_early_departure' => false,
            ]);

            // Update check-in paired_log_id
            $checkOut = AttendanceLog::where('event_id', "week-out-{$i}")->first();
            $checkIn->update(['paired_log_id' => $checkOut->id]);
        }

        // Test daily report (Monday)
        $dailyResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/daily?date=" . $monday->format('Y-m-d'));

        $dailyResponse->assertOk()
            ->assertJson([
                'period' => 'daily',
                'summary' => [
                    'total_minutes' => 540,
                    'total_hours' => 9,
                    'overtime_hours' => 1,
                ],
            ]);

        // Test weekly report
        $weeklyResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/weekly?date=" . $monday->format('Y-m-d'));

        $weeklyResponse->assertOk()
            ->assertJson([
                'period' => 'weekly',
            ]);

        $weeklySummary = $weeklyResponse->json('summary');
        $this->assertEquals(2700, $weeklySummary['total_minutes']); // 5 * 540 = 2700
        $this->assertEquals(5, $weeklySummary['days_worked']);

        // Test monthly report
        $monthlyResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/monthly?month=" . $monday->format('Y-m'));

        $monthlyResponse->assertOk()
            ->assertJson(['period' => 'monthly']);

        $monthlySummary = $monthlyResponse->json('summary');
        $this->assertGreaterThanOrEqual(2700, $monthlySummary['total_minutes']);
        $this->assertGreaterThanOrEqual(5, $monthlySummary['days_worked']);

        // Test worker logs endpoint (detailed day-by-day)
        $logsResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/reports/logs/{$worker->id}?from=" . $monday->format('Y-m-d') . "&to=" . $monday->copy()->addDays(4)->format('Y-m-d'));

        $logsResponse->assertOk();
        $this->assertEquals(10, $logsResponse->json('total_logs')); // 5 days * 2 logs
        $this->assertEquals(5, $logsResponse->json('total_days'));

        // Test all workers daily report
        $allDailyResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/reports/all/daily?date=" . $monday->format('Y-m-d'));

        $allDailyResponse->assertOk();
        $workers = $allDailyResponse->json('workers');
        $this->assertCount(1, $workers);
        $this->assertEquals($worker->id, $workers[0]['id']);
    }

    // ==================== 6. Work Summary Calculation ====================

    public function test_work_summary_calculates_correctly_from_attendance_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);

        // Create varied attendance data for the week
        $monday = Carbon::today()->startOfWeek();

        // Monday: Normal day (09:00 - 18:00, 540 min, 60 min overtime)
        $this->createAttendancePair($worker, $rep, $monday, '09:00', '18:00', 540, false, 60);

        // Tuesday: Late arrival (09:20 - 18:00, 520 min, 40 min overtime)
        $this->createAttendancePair($worker, $rep, $monday->copy()->addDay(), '09:20', '18:00', 520, true, 40);

        // Wednesday: Early departure (09:00 - 17:30, 510 min, 30 min overtime)
        $this->createAttendancePair($worker, $rep, $monday->copy()->addDays(2), '09:00', '17:30', 510, false, 30, true);

        // Thursday: Normal (09:00 - 18:00)
        $this->createAttendancePair($worker, $rep, $monday->copy()->addDays(3), '09:00', '18:00', 540, false, 60);

        // Friday: Missing checkout (only check-in at 09:00)
        $friday = $monday->copy()->addDays(4);
        AttendanceLog::create([
            'event_id' => 'summary-fri-in',
            'worker_id' => $worker->id,
            'rep_id' => $rep->id,
            'type' => 'in',
            'device_time' => $friday->copy()->setTimeFromTimeString('09:00'),
            'device_timezone' => 'Europe/Istanbul',
            'sync_time' => now(),
            'sync_status' => 'synced',
            'is_late' => false,
        ]);

        // Test weekly summary via API
        $weeklyResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/weekly?date=" . $monday->format('Y-m-d'));

        $weeklyResponse->assertOk();
        $summary = $weeklyResponse->json('summary');

        // Total minutes: 540 + 520 + 510 + 540 = 2110 (Friday has no checkout)
        $this->assertEquals(2110, $summary['total_minutes']);

        // Days worked: 5 (all 5 days have at least a check-in)
        $this->assertEquals(5, $summary['days_worked']);

        // Late arrivals: 1 (Tuesday)
        $this->assertEquals(1, $summary['late_arrivals']);

        // Early departures: 1 (Wednesday)
        $this->assertEquals(1, $summary['early_departures']);

        // Missing checkouts: 1 (Friday)
        $this->assertEquals(1, $summary['missing_checkouts']);

        // Verify WorkSummary was created in database
        $workSummary = WorkSummary::where('worker_id', $worker->id)
            ->where('period_type', 'weekly')
            ->whereDate('period_start', $monday)
            ->first();

        $this->assertNotNull($workSummary);
        $this->assertEquals(2110, $workSummary->total_minutes);
        $this->assertNotNull($workSummary->calculated_at);
    }

    // ==================== Helper Methods ====================

    private function createAttendancePair(
        User $worker,
        User $rep,
        Carbon $date,
        string $inTime,
        string $outTime,
        int $workMinutes,
        bool $isLate = false,
        int $overtimeMinutes = 0,
        bool $isEarlyDeparture = false,
    ): void {
        $checkInTime = $date->copy()->setTimeFromTimeString($inTime);
        $checkOutTime = $date->copy()->setTimeFromTimeString($outTime);
        $dayStr = $date->format('Y-m-d');

        $checkIn = AttendanceLog::create([
            'event_id' => "pair-in-{$dayStr}",
            'worker_id' => $worker->id,
            'rep_id' => $rep->id,
            'type' => 'in',
            'device_time' => $checkInTime,
            'device_timezone' => 'Europe/Istanbul',
            'sync_time' => now(),
            'sync_status' => 'synced',
            'is_late' => $isLate,
        ]);

        $checkOut = AttendanceLog::create([
            'event_id' => "pair-out-{$dayStr}",
            'worker_id' => $worker->id,
            'rep_id' => $rep->id,
            'type' => 'out',
            'device_time' => $checkOutTime,
            'device_timezone' => 'Europe/Istanbul',
            'sync_time' => now(),
            'sync_status' => 'synced',
            'work_minutes' => $workMinutes,
            'paired_log_id' => $checkIn->id,
            'is_overtime' => $overtimeMinutes > 0,
            'overtime_minutes' => $overtimeMinutes,
            'is_early_departure' => $isEarlyDeparture,
        ]);

        $checkIn->update(['paired_log_id' => $checkOut->id]);
    }
}
