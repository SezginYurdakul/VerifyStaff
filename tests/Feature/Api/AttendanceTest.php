<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\Kiosk;
use App\Models\Setting;
use App\Models\User;
use App\Services\TotpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private TotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new TotpService();
    }

    private function enableKioskMode(): void
    {
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'kiosk', 'group' => 'attendance']
        );
    }

    private function createActiveKiosk(): Kiosk
    {
        return Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);
    }

    // ==================== Self Check Tests ====================

    public function test_worker_can_self_check_in(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        // Generate valid kiosk TOTP
        $kioskTotp = $this->totpService->generateCode($kiosk->secret_token)['code'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => now()->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => $kioskTotp,
                'latitude' => 41.0082,
                'longitude' => 28.9784,
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Check-in successful',
                'type' => 'in',
                'worker_id' => $worker->id,
                'worker_name' => $worker->name,
                'kiosk_code' => $kiosk->code,
            ]);

        $this->assertDatabaseHas('attendance_logs', [
            'worker_id' => $worker->id,
            'type' => 'in',
            'kiosk_id' => $kiosk->code,
        ]);
    }

    public function test_worker_can_self_check_out_after_check_in(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        // Create a check-in log
        $checkInTime = now()->subHours(8);
        AttendanceLog::create([
            'event_id' => AttendanceLog::generateEventId($worker->id, 0, $checkInTime->toIso8601String(), 'in'),
            'worker_id' => $worker->id,
            'rep_id' => null,
            'type' => 'in',
            'device_time' => $checkInTime,
            'device_timezone' => 'Europe/Istanbul',
            'sync_time' => $checkInTime,
            'sync_status' => 'synced',
            'kiosk_id' => $kiosk->code,
        ]);

        // Generate valid kiosk TOTP
        $kioskTotp = $this->totpService->generateCode($kiosk->secret_token)['code'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => now()->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => $kioskTotp,
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Check-out successful',
                'type' => 'out',
            ]);

        $this->assertDatabaseHas('attendance_logs', [
            'worker_id' => $worker->id,
            'type' => 'out',
        ]);
    }

    public function test_self_check_fails_when_kiosk_mode_disabled(): void
    {
        // Don't enable kiosk mode
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'representative', 'group' => 'attendance']
        );

        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $kioskTotp = $this->totpService->generateCode($kiosk->secret_token)['code'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => now()->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => $kioskTotp,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Kiosk mode is not enabled. Contact your administrator.',
            ]);
    }

    public function test_self_check_fails_for_non_worker(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $kioskTotp = $this->totpService->generateCode($kiosk->secret_token)['code'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => now()->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => $kioskTotp,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Only workers can use self check-in/check-out.',
            ]);
    }

    public function test_self_check_fails_with_invalid_kiosk_code(): void
    {
        $this->enableKioskMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => now()->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => 'INVALID',
                'kiosk_totp' => '123456',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid kiosk code or kiosk is not active.',
            ]);
    }

    public function test_self_check_fails_with_inactive_kiosk(): void
    {
        $this->enableKioskMode();

        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'inactive',
        ]);

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $kioskTotp = $this->totpService->generateCode($kiosk->secret_token)['code'];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => now()->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => $kioskTotp,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid kiosk code or kiosk is not active.',
            ]);
    }

    public function test_self_check_fails_with_invalid_totp(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/self-check', [
                'device_time' => now()->toIso8601String(),
                'device_timezone' => 'Europe/Istanbul',
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => '000000', // Invalid TOTP
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid or expired kiosk code. Please scan again.',
            ]);
    }

    public function test_self_check_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/attendance/self-check', [
            'device_time' => now()->toIso8601String(),
            'kiosk_code' => 'KIOSK001',
            'kiosk_totp' => '123456',
        ]);

        $response->assertStatus(401);
    }

    public function test_self_check_requires_device_time(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/self-check', [
                'kiosk_code' => $kiosk->code,
                'kiosk_totp' => '123456',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ==================== Status Tests ====================

    public function test_worker_can_get_attendance_status(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance/status');

        $response->assertOk()
            ->assertJsonStructure([
                'worker_id',
                'worker_name',
                'date',
                'current_status',
                'last_action',
                'today_summary' => [
                    'total_logs',
                    'total_minutes',
                    'total_hours',
                    'formatted_time',
                ],
                'attendance_mode',
            ])
            ->assertJson([
                'worker_id' => $worker->id,
                'worker_name' => $worker->name,
                'current_status' => 'not_checked_in',
            ]);
    }

    public function test_worker_status_shows_checked_in_after_check_in(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        // Create check-in for today
        AttendanceLog::create([
            'event_id' => 'test-event-1',
            'worker_id' => $worker->id,
            'type' => 'in',
            'device_time' => now(),
            'device_timezone' => 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance/status');

        $response->assertOk()
            ->assertJson([
                'current_status' => 'checked_in',
            ]);
    }

    public function test_worker_status_shows_checked_out_after_check_out(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        // Create check-in and check-out for today
        AttendanceLog::create([
            'event_id' => 'test-event-1',
            'worker_id' => $worker->id,
            'type' => 'in',
            'device_time' => now()->subHours(8),
            'device_timezone' => 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
        ]);

        AttendanceLog::create([
            'event_id' => 'test-event-2',
            'worker_id' => $worker->id,
            'type' => 'out',
            'device_time' => now(),
            'device_timezone' => 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
            'work_minutes' => 480,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance/status');

        $response->assertOk()
            ->assertJson([
                'current_status' => 'checked_out',
                'today_summary' => [
                    'total_minutes' => 480,
                    'total_hours' => 8,
                    'formatted_time' => '8:00',
                ],
            ]);
    }

    public function test_admin_cannot_get_worker_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance/status');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Only workers can check their attendance status.',
            ]);
    }

    public function test_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/attendance/status');

        $response->assertStatus(401);
    }

    // ==================== Sync Offline Logs Tests ====================

    public function test_worker_can_sync_offline_kiosk_logs(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $deviceTime = now()->subMinutes(30)->toIso8601String();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => $deviceTime,
                        'device_timezone' => 'Europe/Istanbul',
                        'event_id' => 'offline-event-1',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Sync completed.',
                'stats' => [
                    'success' => 1,
                    'failed' => 0,
                    'skipped' => 0,
                ],
            ]);

        $this->assertDatabaseHas('attendance_logs', [
            'worker_id' => $worker->id,
            'kiosk_id' => $kiosk->code,
            'flagged' => true, // No TOTP provided
        ]);
    }

    public function test_sync_offline_flags_logs_without_totp(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => now()->subMinutes(10)->toIso8601String(),
                        'event_id' => 'offline-no-totp',
                    ],
                ],
            ]);

        $response->assertOk();

        $log = AttendanceLog::where('worker_id', $worker->id)->first();
        $this->assertTrue($log->flagged);
        $this->assertStringContainsString('TOTP not provided', $log->flag_reason);
    }

    public function test_sync_offline_detects_duplicate_event_ids(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $deviceTime = now()->subHours(2)->toIso8601String();

        // First sync
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => $deviceTime,
                        'event_id' => 'offline-dup-1',
                    ],
                ],
            ]);

        // Second sync with same device_time (generates same server event_id)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => $deviceTime,
                        'event_id' => 'offline-dup-2',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'stats' => [
                    'success' => 0,
                    'skipped' => 1,
                ],
            ]);
    }

    public function test_sync_offline_reports_error_for_invalid_kiosk(): void
    {
        $this->enableKioskMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => 'NONEXISTENT',
                        'device_time' => now()->subMinutes(10)->toIso8601String(),
                        'event_id' => 'offline-invalid-kiosk',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'stats' => [
                    'success' => 0,
                    'failed' => 1,
                ],
            ]);
    }

    public function test_sync_offline_fails_when_kiosk_mode_disabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'representative', 'group' => 'attendance']
        );

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => 'KIOSK001',
                        'device_time' => now()->toIso8601String(),
                        'event_id' => 'offline-1',
                    ],
                ],
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Kiosk mode is not enabled.']);
    }

    public function test_sync_offline_fails_for_non_worker(): void
    {
        $this->enableKioskMode();

        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => 'KIOSK001',
                        'device_time' => now()->toIso8601String(),
                        'event_id' => 'offline-1',
                    ],
                ],
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Only workers can sync offline kiosk logs.']);
    }

    public function test_sync_offline_validates_logs_array_required(): void
    {
        $this->enableKioskMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', []);

        $response->assertStatus(422);
    }

    public function test_sync_offline_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/attendance/sync-offline', [
            'logs' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_sync_offline_can_process_multiple_logs(): void
    {
        $this->enableKioskMode();
        $kiosk = $this->createActiveKiosk();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/sync-offline', [
                'logs' => [
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => now()->subHours(8)->toIso8601String(),
                        'event_id' => 'offline-multi-1',
                    ],
                    [
                        'kiosk_code' => $kiosk->code,
                        'device_time' => now()->subMinutes(30)->toIso8601String(),
                        'event_id' => 'offline-multi-2',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'stats' => ['success' => 2],
            ]);

        $this->assertEquals(2, AttendanceLog::where('worker_id', $worker->id)->count());
    }
}
