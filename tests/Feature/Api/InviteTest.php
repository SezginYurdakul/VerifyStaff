<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\InviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Validate Invite Tests ====================

    public function test_can_validate_valid_invite_token(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => null,
            'invite_token' => 'valid_token_123',
            'invite_expires_at' => now()->addDays(7),
            'invite_accepted_at' => null,
        ]);

        $response = $this->postJson('/api/v1/invite/validate', [
            'token' => 'valid_token_123',
        ]);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ]);
    }

    public function test_validate_fails_for_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/invite/validate', [
            'token' => 'nonexistent_token',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'valid' => false,
                'message' => 'Invalid or expired invitation link',
            ]);
    }

    public function test_validate_fails_for_expired_token(): void
    {
        User::factory()->create([
            'password' => null,
            'invite_token' => 'expired_token',
            'invite_expires_at' => now()->subDay(),
            'invite_accepted_at' => null,
        ]);

        $response = $this->postJson('/api/v1/invite/validate', [
            'token' => 'expired_token',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'valid' => false,
            ]);
    }

    public function test_validate_fails_for_already_accepted_invite(): void
    {
        User::factory()->create([
            'password' => 'password123',
            'invite_token' => 'accepted_token',
            'invite_expires_at' => now()->addDays(7),
            'invite_accepted_at' => now()->subHour(),
        ]);

        $response = $this->postJson('/api/v1/invite/validate', [
            'token' => 'accepted_token',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'valid' => false,
            ]);
    }

    // ==================== Accept Invite Tests ====================

    public function test_can_accept_invite_and_set_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => null,
            'invite_token' => 'valid_token_123',
            'invite_expires_at' => now()->addDays(7),
            'invite_accepted_at' => null,
        ]);

        $response = $this->postJson('/api/v1/invite/accept', [
            'token' => 'valid_token_123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ])
            ->assertJson([
                'message' => 'Password set successfully',
            ]);

        // User should now have password set and invite cleared
        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertNull($user->invite_token);
        $this->assertNull($user->invite_expires_at);
        $this->assertNotNull($user->invite_accepted_at);
    }

    public function test_accept_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/invite/accept', [
            'token' => 'nonexistent_token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid or expired invitation link',
            ]);
    }

    public function test_accept_fails_with_password_too_short(): void
    {
        User::factory()->create([
            'password' => null,
            'invite_token' => 'valid_token',
            'invite_expires_at' => now()->addDays(7),
            'invite_accepted_at' => null,
        ]);

        $response = $this->postJson('/api/v1/invite/accept', [
            'token' => 'valid_token',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_accept_fails_with_password_mismatch(): void
    {
        User::factory()->create([
            'password' => null,
            'invite_token' => 'valid_token',
            'invite_expires_at' => now()->addDays(7),
            'invite_accepted_at' => null,
        ]);

        $response = $this->postJson('/api/v1/invite/accept', [
            'token' => 'valid_token',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_user_can_login_after_accepting_invite(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => null,
            'status' => 'active',
            'invite_token' => 'valid_token',
            'invite_expires_at' => now()->addDays(7),
            'invite_accepted_at' => null,
        ]);

        // Accept invite
        $this->postJson('/api/v1/invite/accept', [
            'token' => 'valid_token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // Try to login
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'test@example.com',
            'password' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Login successful']);
    }
}
