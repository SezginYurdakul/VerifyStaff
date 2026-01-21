<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    public function test_resource_returns_expected_fields(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+905551234567',
            'employee_id' => 'EMP001',
            'role' => 'worker',
            'status' => 'active',
        ]);
        $user->id = 1;

        $resource = new UserResource($user);
        $request = Request::create('/api/v1/auth/me', 'GET');
        $array = $resource->toArray($request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('phone', $array);
        $this->assertArrayHasKey('employee_id', $array);
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_resource_does_not_expose_password(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'hashed_password',
        ]);
        $user->id = 1;

        $resource = new UserResource($user);
        $request = Request::create('/api/v1/auth/me', 'GET');
        $array = $resource->toArray($request);

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_resource_does_not_expose_secret_token(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'secret_token' => 'super_secret_token',
        ]);
        $user->id = 1;

        $resource = new UserResource($user);
        $request = Request::create('/api/v1/auth/me', 'GET');
        $array = $resource->toArray($request);

        $this->assertArrayNotHasKey('secret_token', $array);
    }

    public function test_resource_returns_correct_values(): void
    {
        $user = new User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+905551234567',
            'employee_id' => 'EMP123',
            'role' => 'representative',
            'status' => 'active',
        ]);
        $user->id = 42;

        $resource = new UserResource($user);
        $request = Request::create('/api/v1/auth/me', 'GET');
        $array = $resource->toArray($request);

        $this->assertEquals(42, $array['id']);
        $this->assertEquals('John Doe', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
        $this->assertEquals('+905551234567', $array['phone']);
        $this->assertEquals('EMP123', $array['employee_id']);
        $this->assertEquals('representative', $array['role']);
        $this->assertEquals('active', $array['status']);
    }
}
