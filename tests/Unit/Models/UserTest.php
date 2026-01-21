<?php

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $user = new User();
        $user->role = 'admin';

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_other_roles(): void
    {
        $user = new User();

        $user->role = 'worker';
        $this->assertFalse($user->isAdmin());

        $user->role = 'representative';
        $this->assertFalse($user->isAdmin());
    }

    public function test_is_representative_returns_true_for_representative_role(): void
    {
        $user = new User();
        $user->role = 'representative';

        $this->assertTrue($user->isRepresentative());
    }

    public function test_is_representative_returns_false_for_other_roles(): void
    {
        $user = new User();

        $user->role = 'worker';
        $this->assertFalse($user->isRepresentative());

        $user->role = 'admin';
        $this->assertFalse($user->isRepresentative());
    }

    public function test_is_worker_returns_true_for_worker_role(): void
    {
        $user = new User();
        $user->role = 'worker';

        $this->assertTrue($user->isWorker());
    }

    public function test_is_worker_returns_false_for_other_roles(): void
    {
        $user = new User();

        $user->role = 'admin';
        $this->assertFalse($user->isWorker());

        $user->role = 'representative';
        $this->assertFalse($user->isWorker());
    }

    public function test_is_active_returns_true_for_active_status(): void
    {
        $user = new User();
        $user->status = 'active';

        $this->assertTrue($user->isActive());
    }

    public function test_is_active_returns_false_for_inactive_status(): void
    {
        $user = new User();
        $user->status = 'inactive';

        $this->assertFalse($user->isActive());
    }

    public function test_generate_secret_token_returns_32_character_hex_string(): void
    {
        $token = User::generateSecretToken();

        $this->assertEquals(32, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
    }

    public function test_generate_secret_token_is_random(): void
    {
        $token1 = User::generateSecretToken();
        $token2 = User::generateSecretToken();

        $this->assertNotEquals($token1, $token2);
    }

    public function test_hidden_attributes_are_configured(): void
    {
        $user = new User();

        $hidden = $user->getHidden();

        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
        $this->assertContains('secret_token', $hidden);
    }

    public function test_fillable_attributes_are_configured(): void
    {
        $user = new User();

        $fillable = $user->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('phone', $fillable);
        $this->assertContains('employee_id', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('role', $fillable);
        $this->assertContains('secret_token', $fillable);
        $this->assertContains('status', $fillable);
    }
}
