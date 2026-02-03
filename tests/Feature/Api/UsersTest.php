<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->adminToken = $this->admin->createToken('auth_token')->plainTextToken;

        Mail::fake();
    }

    // ==================== Index Tests ====================

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonStructure([
                'users',
                'total',
                'per_page',
                'current_page',
                'last_page',
            ]);

        // 5 created + 1 admin = 6
        $this->assertEquals(6, $response->json('total'));
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        User::factory()->count(3)->create(['role' => 'worker']);
        User::factory()->count(2)->create(['role' => 'representative']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/users?role=worker');

        $response->assertOk();
        $this->assertEquals(3, $response->json('total'));
    }

    public function test_admin_can_filter_users_by_status(): void
    {
        User::factory()->count(3)->create(['status' => 'active']);
        User::factory()->count(2)->create(['status' => 'inactive']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/users?status=inactive');

        $response->assertOk();
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);
        $workerToken = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    // ==================== Store Tests ====================

    public function test_admin_can_create_user(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'phone' => '5551234567',
                'employee_id' => 'EMP001',
                'role' => 'worker',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User created and invitation sent',
                'user' => [
                    'name' => 'New User',
                    'email' => 'newuser@example.com',
                    'role' => 'worker',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'employee_id' => 'EMP001',
        ]);
    }

    public function test_created_user_has_invite_token(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'role' => 'worker',
            ]);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user->invite_token);
        $this->assertNotNull($user->invite_expires_at);
        $this->assertNull($user->password);
    }

    public function test_create_user_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'New User',
                'email' => 'existing@example.com',
                'role' => 'worker',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $rep = User::factory()->create(['role' => 'representative']);
        $repToken = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$repToken}")
            ->postJson('/api/v1/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'role' => 'worker',
            ]);

        $response->assertStatus(403);
    }

    // ==================== Update Tests ====================

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/users/{$user->id}", [
                'name' => 'New Name',
                'status' => 'inactive',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'User updated successfully',
                'user' => [
                    'name' => 'New Name',
                    'status' => 'inactive',
                ],
            ]);
    }

    public function test_admin_can_change_user_role(): void
    {
        $user = User::factory()->create(['role' => 'worker']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/users/{$user->id}", [
                'role' => 'representative',
            ]);

        $response->assertOk();
        $user->refresh();
        $this->assertEquals('representative', $user->role);
    }

    // ==================== Delete Tests ====================

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertOk()
            ->assertJson(['message' => 'User deleted successfully']);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/users/{$this->admin->id}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Cannot delete your own account']);
    }

    // ==================== Resend Invite Tests ====================

    public function test_admin_can_resend_invite(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'invite_token' => 'old_token',
            'invite_expires_at' => now()->addDays(1),
            'invite_accepted_at' => null,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/users/{$user->id}/resend-invite");

        $response->assertOk()
            ->assertJson(['message' => 'Invitation resent successfully']);

        // Token should be regenerated
        $user->refresh();
        $this->assertNotEquals('old_token', $user->invite_token);
    }

    public function test_cannot_resend_invite_to_user_who_accepted(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
            'invite_accepted_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/users/{$user->id}/resend-invite");

        $response->assertStatus(422)
            ->assertJson(['message' => 'User has already accepted the invitation']);
    }
}
