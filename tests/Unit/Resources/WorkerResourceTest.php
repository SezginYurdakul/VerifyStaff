<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\WorkerResource;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class WorkerResourceTest extends TestCase
{
    public function test_resource_returns_expected_fields(): void
    {
        $worker = new User([
            'name' => 'Test Worker',
            'email' => 'worker@example.com',
            'secret_token' => 'worker_secret_token',
        ]);
        $worker->id = 1;

        $resource = new WorkerResource($worker);
        $request = Request::create('/api/v1/sync/staff', 'GET');
        $array = $resource->toArray($request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('secret_token', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function test_resource_exposes_secret_token_for_sync(): void
    {
        $worker = new User([
            'name' => 'Test Worker',
            'email' => 'worker@example.com',
            'secret_token' => 'my_secret_token_123',
        ]);
        $worker->id = 1;

        $resource = new WorkerResource($worker);
        $request = Request::create('/api/v1/sync/staff', 'GET');
        $array = $resource->toArray($request);

        $this->assertEquals('my_secret_token_123', $array['secret_token']);
    }

    public function test_resource_returns_correct_values(): void
    {
        $worker = new User([
            'name' => 'Jane Worker',
            'email' => 'jane@example.com',
            'secret_token' => 'jane_token',
        ]);
        $worker->id = 99;

        $resource = new WorkerResource($worker);
        $request = Request::create('/api/v1/sync/staff', 'GET');
        $array = $resource->toArray($request);

        $this->assertEquals(99, $array['id']);
        $this->assertEquals('Jane Worker', $array['name']);
        $this->assertEquals('jane@example.com', $array['email']);
        $this->assertEquals('jane_token', $array['secret_token']);
    }

    public function test_resource_does_not_include_role_or_status(): void
    {
        $worker = new User([
            'name' => 'Test Worker',
            'email' => 'worker@example.com',
            'role' => 'worker',
            'status' => 'active',
        ]);
        $worker->id = 1;

        $resource = new WorkerResource($worker);
        $request = Request::create('/api/v1/sync/staff', 'GET');
        $array = $resource->toArray($request);

        $this->assertArrayNotHasKey('role', $array);
        $this->assertArrayNotHasKey('status', $array);
    }
}
