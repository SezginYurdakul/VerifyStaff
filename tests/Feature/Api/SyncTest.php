<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private function enableRepresentativeMode(): void
    {
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'representative', 'group' => 'attendance']
        );
    }

    private function enableKioskMode(): void
    {
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'kiosk', 'group' => 'attendance']
        );
    }

    // ==================== Get Server Time Tests ====================

    public function test_anyone_can_get_server_time(): void
    {
        $response = $this->getJson('/api/v1/time');

        $response->assertOk()
            ->assertJsonStructure([
                'server_time',
                'timestamp',
            ]);
    }

    // ==================== Get Staff List Tests ====================

    public function test_representative_can_get_staff_list(): void
    {
        User::factory()->count(3)->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/sync/staff');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'server_time',
                'workers',
                'total',
            ])
            ->assertJson([
                'message' => 'Staff list synced successfully',
                'total' => 3,
            ]);
    }

    public function test_admin_can_get_staff_list(): void
    {
        User::factory()->count(2)->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/sync/staff');

        $response->assertOk()
            ->assertJson(['total' => 2]);
    }

    public function test_worker_cannot_get_staff_list(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/sync/staff');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Only representatives can sync staff list.']);
    }

    public function test_staff_list_only_includes_active_workers(): void
    {
        User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        User::factory()->create([
            'role' => 'worker',
            'status' => 'inactive',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/sync/staff');

        $response->assertOk()
            ->assertJson(['total' => 1]);
    }

    public function test_staff_list_includes_secret_tokens(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/sync/staff');

        $response->assertOk();
        $this->assertArrayHasKey('secret_token', $response->json('workers.0'));
    }

    public function test_get_staff_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/sync/staff');

        $response->assertStatus(401);
    }

    // ==================== Sync Logs Tests ====================

    public function test_representative_can_sync_logs(): void
    {
        $this->enableRepresentativeMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'type' => 'in',
                        'device_time' => now()->subHours(8)->toIso8601String(),
                        'device_timezone' => 'Europe/Istanbul',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'server_time',
                'synced_count',
                'duplicate_count',
                'error_count',
                'synced',
                'duplicates',
                'errors',
            ])
            ->assertJson([
                'synced_count' => 1,
                'duplicate_count' => 0,
                'error_count' => 0,
            ]);

        $this->assertDatabaseHas('attendance_logs', [
            'worker_id' => $worker->id,
            'rep_id' => $rep->id,
            'type' => 'in',
        ]);
    }

    public function test_sync_logs_calculates_work_minutes_for_check_out(): void
    {
        $this->enableRepresentativeMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        // Create check-in
        $checkInTime = now()->subHours(8);
        AttendanceLog::create([
            'event_id' => 'test-event-1',
            'worker_id' => $worker->id,
            'rep_id' => 1,
            'type' => 'in',
            'device_time' => $checkInTime,
            'device_timezone' => 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'type' => 'out',
                        'device_time' => now()->toIso8601String(),
                        'device_timezone' => 'Europe/Istanbul',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson(['synced_count' => 1]);

        $checkOut = AttendanceLog::where('worker_id', $worker->id)
            ->where('type', 'out')
            ->first();

        // Should have work minutes calculated (approximately 480 minutes = 8 hours)
        $this->assertNotNull($checkOut->work_minutes);
        $this->assertGreaterThan(470, $checkOut->work_minutes);
        $this->assertLessThan(490, $checkOut->work_minutes);
    }

    public function test_sync_logs_detects_duplicate(): void
    {
        $this->enableRepresentativeMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $deviceTime = now()->subHours(2)->toIso8601String();

        // First sync
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'type' => 'in',
                        'device_time' => $deviceTime,
                    ],
                ],
            ]);

        // Second sync with same data
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'type' => 'in',
                        'device_time' => $deviceTime,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'synced_count' => 0,
                'duplicate_count' => 1,
            ]);
    }

    public function test_sync_logs_reports_error_for_invalid_worker(): void
    {
        $this->enableRepresentativeMode();

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => 99999,
                        'type' => 'in',
                        'device_time' => now()->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'synced_count' => 0,
                'error_count' => 1,
            ]);

        $errors = $response->json('errors');
        $this->assertEquals('Worker not found', $errors[0]['reason']);
    }

    public function test_sync_logs_works_in_kiosk_mode_but_flags(): void
    {
        $this->enableKioskMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'type' => 'in',
                        'device_time' => now()->toIso8601String(),
                    ],
                ],
            ]);

        // Kiosk mode allows sync but flags the record (no TOTP verification)
        $response->assertOk()
            ->assertJson(['synced_count' => 1]);

        $this->assertDatabaseHas('attendance_logs', [
            'worker_id' => $worker->id,
            'flagged' => true,
        ]);

        // Verify flag_reason contains TOTP warning
        $log = AttendanceLog::where('worker_id', $worker->id)->first();
        $this->assertStringContainsString('TOTP not provided', $log->flag_reason);
    }

    public function test_worker_cannot_sync_logs(): void
    {
        $this->enableRepresentativeMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'type' => 'in',
                        'device_time' => now()->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Only representatives can sync logs.']);
    }

    public function test_sync_logs_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/sync/logs', [
            'logs' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_sync_logs_flags_future_timestamps(): void
    {
        $this->enableRepresentativeMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'type' => 'in',
                        'device_time' => now()->addHours(2)->toIso8601String(),
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson(['synced_count' => 1]);

        // Flag reason may include multiple reasons (TOTP not provided + Future timestamp)
        $log = AttendanceLog::where('worker_id', $worker->id)->first();
        $this->assertTrue($log->flagged);
        $this->assertStringContainsString('Future timestamp detected', $log->flag_reason);
    }

    public function test_sync_logs_auto_detects_type_with_toggle_mode(): void
    {
        $this->enableRepresentativeMode();

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        // First sync without type - should auto-detect as 'in'
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'device_time' => now()->subHours(8)->toIso8601String(),
                    ],
                ],
            ]);

        $response1->assertOk();
        $this->assertDatabaseHas('attendance_logs', [
            'worker_id' => $worker->id,
            'type' => 'in',
        ]);

        // Second sync without type - should auto-detect as 'out'
        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/sync/logs', [
                'logs' => [
                    [
                        'worker_id' => $worker->id,
                        'device_time' => now()->toIso8601String(),
                    ],
                ],
            ]);

        $response2->assertOk();
        $this->assertDatabaseHas('attendance_logs', [
            'worker_id' => $worker->id,
            'type' => 'out',
        ]);
    }
}
