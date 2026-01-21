<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Register Tests ====================

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '5551234567',
            'employee_id' => 'EMP001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'phone', 'employee_id', 'role', 'status'],
                'token',
            ])
            ->assertJson([
                'message' => 'User registered successfully',
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'employee_id' => 'EMP001',
        ]);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'phone' => '5551234567',
            'employee_id' => 'EMP001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.email.0', fn ($value) => str_contains($value, 'taken'));
    }

    public function test_register_fails_with_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '5551234567']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '5551234567',
            'employee_id' => 'EMP001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.phone.0', fn ($value) => str_contains($value, 'taken'));
    }

    public function test_register_fails_with_duplicate_employee_id(): void
    {
        User::factory()->create(['employee_id' => 'EMP001']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '5551234567',
            'employee_id' => 'EMP001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.employee_id.0', fn ($value) => str_contains($value, 'taken'));
    }

    public function test_register_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJson([
                'success' => false,
            ]);

        $details = $response->json('error.details');
        $this->assertArrayHasKey('name', $details);
        $this->assertArrayHasKey('password', $details);
        // At least one identifier (email, phone, or employee_id) is required
        $this->assertArrayHasKey('identifier', $details);
    }

    public function test_register_creates_user_with_default_worker_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '5551234567',
            'employee_id' => 'EMP001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'worker',
        ]);
    }

    // ==================== Login Tests ====================

    public function test_user_can_login_with_email(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ])
            ->assertJson([
                'message' => 'Login successful',
            ]);
    }

    public function test_user_can_login_with_phone(): void
    {
        User::factory()->create([
            'phone' => '5551234567',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '5551234567',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Login successful']);
    }

    public function test_user_can_login_with_employee_id(): void
    {
        User::factory()->create([
            'employee_id' => 'EMP001',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'EMP001',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Login successful']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.identifier.0', 'The provided credentials are incorrect.');
    }

    public function test_login_fails_with_nonexistent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.identifier.0', 'The provided credentials are incorrect.');
    }

    public function test_login_fails_for_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.identifier.0', 'Your account is not active.');
    }

    // ==================== Logout Tests ====================

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_logout_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    // ==================== Me Tests ====================

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone', 'employee_id', 'role', 'status'],
            ])
            ->assertJson([
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ]);
    }

    public function test_me_fails_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_me_does_not_expose_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonMissing(['password'])
            ->assertJsonMissing(['secret_token']);
    }

    // ==================== Refresh Token Tests ====================

    public function test_authenticated_user_can_refresh_token(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['message', 'token'])
            ->assertJson(['message' => 'Token refreshed successfully']);

        // Old token should be deleted
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_refresh_token_fails_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh');

        $response->assertStatus(401);
    }
}
