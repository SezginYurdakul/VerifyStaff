<?php

namespace Tests\Feature\Api;

use App\Models\Kiosk;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskTest extends TestCase
{
    use RefreshDatabase;

    private function enableKioskMode(): void
    {
        Setting::updateOrCreate(
            ['key' => 'attendance_mode'],
            ['value' => 'kiosk', 'group' => 'attendance']
        );
    }

    // ==================== Generate Code Tests ====================

    public function test_kiosk_can_generate_code_in_kiosk_mode(): void
    {
        $this->enableKioskMode();

        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v1/kiosk/{$kiosk->code}/code");

        $response->assertOk()
            ->assertJsonStructure([
                'kiosk_code',
                'kiosk_name',
                'totp_code',
                'expires_at',
                'remaining_seconds',
                'qr_data',
            ])
            ->assertJson([
                'kiosk_code' => $kiosk->code,
                'kiosk_name' => $kiosk->name,
            ]);

        $this->assertEquals(6, strlen($response->json('totp_code')));
    }

    public function test_generate_code_fails_in_representative_mode(): void
    {
        // Default is representative mode from migration

        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v1/kiosk/{$kiosk->code}/code");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'System is not in kiosk mode.',
                'attendance_mode' => 'representative',
            ]);
    }

    public function test_generate_code_fails_for_nonexistent_kiosk(): void
    {
        $this->enableKioskMode();

        $response = $this->getJson('/api/v1/kiosk/INVALID/code');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Kiosk not found.']);
    }

    public function test_generate_code_fails_for_inactive_kiosk(): void
    {
        $this->enableKioskMode();

        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'inactive',
        ]);

        $response = $this->getJson("/api/v1/kiosk/{$kiosk->code}/code");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Kiosk is not active.',
                'status' => 'inactive',
            ]);
    }

    public function test_generate_code_updates_heartbeat(): void
    {
        $this->enableKioskMode();

        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $this->assertNull($kiosk->last_heartbeat_at);

        $this->getJson("/api/v1/kiosk/{$kiosk->code}/code");

        $kiosk->refresh();
        $this->assertNotNull($kiosk->last_heartbeat_at);
    }

    // ==================== Index Tests ====================

    public function test_admin_can_list_kiosks(): void
    {
        Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Kiosk 1',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);
        Kiosk::create([
            'code' => 'KIOSK002',
            'name' => 'Kiosk 2',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/kiosks');

        $response->assertOk()
            ->assertJsonStructure([
                'kiosks',
                'total',
            ])
            ->assertJson(['total' => 2]);
    }

    public function test_worker_cannot_list_kiosks(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/kiosks');

        $response->assertStatus(403);
    }

    // ==================== Store Tests ====================

    public function test_admin_can_create_kiosk(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/kiosks', [
                'name' => 'New Kiosk',
                'location' => 'Building A',
                'latitude' => 41.0082,
                'longitude' => 28.9784,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Kiosk created successfully',
            ])
            ->assertJsonStructure([
                'kiosk' => ['id', 'name', 'code', 'location', 'status'],
            ]);

        $this->assertDatabaseHas('kiosks', [
            'name' => 'New Kiosk',
            'location' => 'Building A',
        ]);
    }

    public function test_admin_can_create_kiosk_with_minimal_data(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/kiosks', [
                'name' => 'Simple Kiosk',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('kiosks', [
            'name' => 'Simple Kiosk',
            'status' => 'active',
        ]);
    }

    public function test_create_kiosk_requires_name(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/kiosks', []);

        $response->assertStatus(422);
    }

    public function test_representative_cannot_create_kiosk(): void
    {
        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/kiosks', [
                'name' => 'New Kiosk',
            ]);

        $response->assertStatus(403);
    }

    // ==================== Show Tests ====================

    public function test_admin_can_view_kiosk_details(): void
    {
        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
            'location' => 'Building A',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/kiosks/{$kiosk->code}");

        $response->assertOk()
            ->assertJsonStructure([
                'kiosk' => [
                    'id', 'name', 'code', 'location', 'latitude', 'longitude',
                    'status', 'last_heartbeat_at', 'created_at',
                ],
            ])
            ->assertJson([
                'kiosk' => [
                    'code' => 'KIOSK001',
                    'name' => 'Test Kiosk',
                    'location' => 'Building A',
                ],
            ]);
    }

    public function test_view_nonexistent_kiosk_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/kiosks/INVALID');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Kiosk not found.']);
    }

    // ==================== Update Tests ====================

    public function test_admin_can_update_kiosk(): void
    {
        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Old Name',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/kiosks/{$kiosk->code}", [
                'name' => 'New Name',
                'location' => 'Building B',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Kiosk updated successfully',
                'kiosk' => [
                    'name' => 'New Name',
                    'location' => 'Building B',
                ],
            ]);

        $this->assertDatabaseHas('kiosks', [
            'code' => 'KIOSK001',
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_change_kiosk_status(): void
    {
        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/kiosks/{$kiosk->code}", [
                'status' => 'maintenance',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('kiosks', [
            'code' => 'KIOSK001',
            'status' => 'maintenance',
        ]);
    }

    public function test_update_kiosk_validates_status(): void
    {
        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => Kiosk::generateSecretToken(),
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/kiosks/{$kiosk->code}", [
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
    }

    public function test_update_nonexistent_kiosk_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/kiosks/INVALID', [
                'name' => 'New Name',
            ]);

        $response->assertStatus(404);
    }

    // ==================== Regenerate Token Tests ====================

    public function test_admin_can_regenerate_kiosk_token(): void
    {
        $oldToken = Kiosk::generateSecretToken();
        $kiosk = Kiosk::create([
            'code' => 'KIOSK001',
            'name' => 'Test Kiosk',
            'secret_token' => $oldToken,
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/kiosks/{$kiosk->code}/regenerate-token");

        $response->assertOk()
            ->assertJson([
                'message' => 'Kiosk token regenerated successfully. Kiosk will need to be reconfigured.',
                'kiosk_code' => 'KIOSK001',
            ]);

        $kiosk->refresh();
        $this->assertNotEquals($oldToken, $kiosk->secret_token);
    }

    public function test_regenerate_token_for_nonexistent_kiosk_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/kiosks/INVALID/regenerate-token');

        $response->assertStatus(404);
    }

    // ==================== Auth Tests ====================

    public function test_kiosk_management_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/kiosks');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/kiosks', ['name' => 'Test']);
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/kiosks/KIOSK001');
        $response->assertStatus(401);

        $response = $this->putJson('/api/v1/kiosks/KIOSK001', ['name' => 'Test']);
        $response->assertStatus(401);
    }
}
