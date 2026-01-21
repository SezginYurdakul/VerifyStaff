<?php

namespace Tests\Feature\Api;

use App\Events\TotpVerified;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TotpTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Generate Code Tests ====================

    public function test_worker_can_generate_totp_code(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/totp/generate');

        $response->assertOk()
            ->assertJsonStructure([
                'code',
                'expires_at',
                'remaining_seconds',
                'qr_data',
            ]);

        $this->assertEquals(6, strlen($response->json('code')));
        $this->assertGreaterThan(0, $response->json('remaining_seconds'));
        $this->assertLessThanOrEqual(30, $response->json('remaining_seconds'));
    }

    public function test_admin_cannot_generate_totp_code(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/totp/generate');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Only workers can generate TOTP codes.']);
    }

    public function test_representative_cannot_generate_totp_code(): void
    {
        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/totp/generate');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Only workers can generate TOTP codes.']);
    }

    public function test_worker_without_secret_token_cannot_generate_code(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => null,
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/totp/generate');

        $response->assertStatus(400)
            ->assertJson(['message' => 'No secret token assigned to this user.']);
    }

    public function test_generate_code_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/totp/generate');

        $response->assertStatus(401);
    }

    public function test_generated_code_is_deterministic_for_same_token(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/totp/generate');

        // Same request within the same 30s window should return same code
        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/totp/generate');

        $this->assertEquals($response1->json('code'), $response2->json('code'));
    }

    // ==================== Verify Code Tests ====================

    public function test_representative_can_verify_valid_totp_code(): void
    {
        Event::fake([TotpVerified::class]);

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        // Generate valid code
        $totpService = new TotpService();
        $validCode = $totpService->generateCode($worker->secret_token)['code'];

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => $worker->id,
                'code' => $validCode,
            ]);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'worker_id' => $worker->id,
                'worker_name' => $worker->name,
            ])
            ->assertJsonStructure(['verified_at']);

        Event::assertDispatched(TotpVerified::class, function ($event) use ($worker, $rep) {
            return $event->worker->id === $worker->id
                && $event->success === true
                && $event->verifiedBy->id === $rep->id;
        });
    }

    public function test_admin_can_verify_totp_code(): void
    {
        Event::fake([TotpVerified::class]);

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $adminToken = $admin->createToken('auth_token')->plainTextToken;

        $totpService = new TotpService();
        $validCode = $totpService->generateCode($worker->secret_token)['code'];

        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => $worker->id,
                'code' => $validCode,
            ]);

        $response->assertOk()
            ->assertJson(['valid' => true]);

        Event::assertDispatched(TotpVerified::class);
    }

    public function test_verify_returns_false_for_invalid_code(): void
    {
        Event::fake([TotpVerified::class]);

        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => $worker->id,
                'code' => '000000', // Invalid code
            ]);

        $response->assertOk()
            ->assertJson([
                'valid' => false,
                'worker_id' => $worker->id,
            ]);

        Event::assertDispatched(TotpVerified::class, function ($event) {
            return $event->success === false;
        });
    }

    public function test_verify_fails_for_nonexistent_worker(): void
    {
        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => 99999,
                'code' => '123456',
            ]);

        // Validation fails because worker_id doesn't exist in users table
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.worker_id.0', 'Worker not found.');
    }

    public function test_verify_fails_for_inactive_worker(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'inactive',
            'secret_token' => User::generateSecretToken(),
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => $worker->id,
                'code' => '123456',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Worker not found or inactive.',
                'valid' => false,
            ]);
    }

    public function test_verify_fails_for_worker_without_secret_token(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'secret_token' => null,
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => $worker->id,
                'code' => '123456',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Worker has no secret token.',
                'valid' => false,
            ]);
    }

    public function test_verify_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/totp/verify', [
            'worker_id' => 1,
            'code' => '123456',
        ]);

        $response->assertStatus(401);
    }

    public function test_verify_requires_worker_id(): void
    {
        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'code' => '123456',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_verify_requires_code(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => $worker->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_verify_code_must_be_6_digits(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/totp/verify', [
                'worker_id' => $worker->id,
                'code' => '12345', // Only 5 digits
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
