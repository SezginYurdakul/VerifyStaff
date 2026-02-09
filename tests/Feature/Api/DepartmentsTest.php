<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->adminToken = $this->admin->createToken('auth_token')->plainTextToken;
    }

    private function createDepartment(array $overrides = []): Department
    {
        return Department::create(array_merge([
            'name' => 'Warehouse',
            'code' => 'WH',
            'shift_start' => '09:00',
            'shift_end' => '18:00',
            'late_threshold_minutes' => 15,
            'early_departure_threshold_minutes' => 15,
            'regular_work_minutes' => 480,
            'is_active' => true,
        ], $overrides));
    }

    // ==================== Index Tests ====================

    public function test_admin_can_list_departments(): void
    {
        $this->createDepartment(['name' => 'Warehouse', 'code' => 'WH']);
        $this->createDepartment(['name' => 'Office', 'code' => 'OFF']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/departments');

        $response->assertOk()
            ->assertJsonStructure([
                'departments',
                'total',
            ])
            ->assertJson(['total' => 2]);
    }

    public function test_departments_are_ordered_by_name(): void
    {
        $this->createDepartment(['name' => 'Zebra', 'code' => 'ZBR']);
        $this->createDepartment(['name' => 'Alpha', 'code' => 'ALP']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/departments');

        $response->assertOk();
        $departments = $response->json('departments');
        $this->assertEquals('Alpha', $departments[0]['name']);
        $this->assertEquals('Zebra', $departments[1]['name']);
    }

    public function test_departments_include_active_worker_count(): void
    {
        $dept = $this->createDepartment();

        // Active worker
        User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
            'department_id' => $dept->id,
        ]);

        // Inactive worker (should not be counted)
        User::factory()->create([
            'role' => 'worker',
            'status' => 'inactive',
            'department_id' => $dept->id,
        ]);

        // Admin in department (not a worker, should not be counted)
        User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'department_id' => $dept->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/departments');

        $response->assertOk();
        $departments = $response->json('departments');
        $this->assertEquals(1, $departments[0]['workers_count']);
    }

    public function test_admin_can_filter_active_departments(): void
    {
        $this->createDepartment(['name' => 'Active Dept', 'code' => 'ACT', 'is_active' => true]);
        $this->createDepartment(['name' => 'Inactive Dept', 'code' => 'INACT', 'is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/departments?active=true');

        $response->assertOk()
            ->assertJson(['total' => 1]);
        $this->assertEquals('Active Dept', $response->json('departments.0.name'));
    }

    public function test_admin_can_filter_inactive_departments(): void
    {
        $this->createDepartment(['name' => 'Active Dept', 'code' => 'ACT', 'is_active' => true]);
        $this->createDepartment(['name' => 'Inactive Dept', 'code' => 'INACT', 'is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/departments?active=false');

        $response->assertOk()
            ->assertJson(['total' => 1]);
        $this->assertEquals('Inactive Dept', $response->json('departments.0.name'));
    }

    public function test_departments_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/departments');
        $response->assertStatus(401);
    }

    // ==================== Store Tests ====================

    public function test_admin_can_create_department(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/departments', [
                'name' => 'Production',
                'code' => 'PROD',
                'shift_start' => '08:00',
                'shift_end' => '17:00',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Department created successfully',
                'department' => [
                    'name' => 'Production',
                    'code' => 'PROD',
                    'shift_start' => '08:00',
                    'shift_end' => '17:00',
                ],
            ]);

        $this->assertDatabaseHas('departments', [
            'name' => 'Production',
            'code' => 'PROD',
        ]);
    }

    public function test_department_code_is_uppercased(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/departments', [
                'name' => 'Warehouse',
                'code' => 'wh',
                'shift_start' => '09:00',
                'shift_end' => '18:00',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('departments', ['code' => 'WH']);
    }

    public function test_department_defaults_are_applied(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/departments', [
                'name' => 'Test Dept',
                'code' => 'TST',
                'shift_start' => '09:00',
                'shift_end' => '18:00',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('departments', [
            'code' => 'TST',
            'late_threshold_minutes' => 15,
            'early_departure_threshold_minutes' => 15,
            'regular_work_minutes' => 480,
            'is_active' => true,
        ]);
    }

    public function test_create_department_validates_required_fields(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/departments', []);

        $response->assertStatus(422);
    }

    public function test_create_department_fails_with_duplicate_code(): void
    {
        $this->createDepartment(['code' => 'WH']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/departments', [
                'name' => 'Another Warehouse',
                'code' => 'WH',
                'shift_start' => '09:00',
                'shift_end' => '18:00',
            ]);

        $response->assertStatus(422);
    }

    public function test_create_department_validates_shift_time_format(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/departments', [
                'name' => 'Test',
                'code' => 'TST',
                'shift_start' => 'invalid',
                'shift_end' => '18:00',
            ]);

        $response->assertStatus(422);
    }

    public function test_create_department_validates_working_days(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/departments', [
                'name' => 'Test',
                'code' => 'TST',
                'shift_start' => '09:00',
                'shift_end' => '18:00',
                'working_days' => ['monday', 'invalid_day'],
            ]);

        $response->assertStatus(422);
    }

    public function test_create_department_with_all_optional_fields(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/departments', [
                'name' => 'Full Dept',
                'code' => 'FULL',
                'shift_start' => '07:00',
                'shift_end' => '16:00',
                'late_threshold_minutes' => 10,
                'early_departure_threshold_minutes' => 20,
                'regular_work_minutes' => 540,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'description' => 'Full department with all fields',
                'is_active' => false,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('departments', [
            'code' => 'FULL',
            'late_threshold_minutes' => 10,
            'early_departure_threshold_minutes' => 20,
            'regular_work_minutes' => 540,
            'description' => 'Full department with all fields',
            'is_active' => false,
        ]);
    }

    // ==================== Show Tests ====================

    public function test_admin_can_get_single_department(): void
    {
        $dept = $this->createDepartment();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/departments/{$dept->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'department' => [
                    'id', 'name', 'code', 'shift_start', 'shift_end',
                    'late_threshold_minutes', 'early_departure_threshold_minutes',
                    'regular_work_minutes', 'is_active', 'workers_count',
                ],
            ])
            ->assertJson([
                'department' => [
                    'id' => $dept->id,
                    'name' => 'Warehouse',
                    'code' => 'WH',
                ],
            ]);
    }

    public function test_show_department_includes_worker_count(): void
    {
        $dept = $this->createDepartment();

        User::factory()->count(3)->create([
            'role' => 'worker',
            'status' => 'active',
            'department_id' => $dept->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/departments/{$dept->id}");

        $response->assertOk();
        $this->assertEquals(3, $response->json('department.workers_count'));
    }

    public function test_show_nonexistent_department_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/departments/99999');

        $response->assertStatus(404);
    }

    // ==================== Update Tests ====================

    public function test_admin_can_update_department(): void
    {
        $dept = $this->createDepartment();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/departments/{$dept->id}", [
                'name' => 'Updated Warehouse',
                'shift_start' => '08:00',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Department updated successfully',
                'department' => [
                    'name' => 'Updated Warehouse',
                    'shift_start' => '08:00',
                ],
            ]);
    }

    public function test_update_department_code_is_uppercased(): void
    {
        $dept = $this->createDepartment();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/departments/{$dept->id}", [
                'code' => 'new',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'code' => 'NEW',
        ]);
    }

    public function test_update_department_code_unique_excludes_self(): void
    {
        $dept = $this->createDepartment(['code' => 'WH']);

        // Updating with the same code should work
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/departments/{$dept->id}", [
                'code' => 'WH',
            ]);

        $response->assertOk();
    }

    public function test_update_department_code_fails_if_taken_by_another(): void
    {
        $this->createDepartment(['code' => 'WH']);
        $dept2 = $this->createDepartment(['name' => 'Office', 'code' => 'OFF']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/departments/{$dept2->id}", [
                'code' => 'WH',
            ]);

        $response->assertStatus(422);
    }

    public function test_update_nonexistent_department_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson('/api/v1/departments/99999', [
                'name' => 'Does Not Exist',
            ]);

        $response->assertStatus(404);
    }

    public function test_admin_can_deactivate_department(): void
    {
        $dept = $this->createDepartment(['is_active' => true]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/departments/{$dept->id}", [
                'is_active' => false,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('departments', [
            'id' => $dept->id,
            'is_active' => false,
        ]);
    }

    // ==================== Destroy Tests ====================

    public function test_admin_can_delete_department_without_users(): void
    {
        $dept = $this->createDepartment();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/departments/{$dept->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Department deleted successfully']);

        $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
    }

    public function test_cannot_delete_department_with_assigned_users(): void
    {
        $dept = $this->createDepartment();

        User::factory()->create([
            'role' => 'worker',
            'department_id' => $dept->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/departments/{$dept->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete department with assigned users. Reassign users first.',
            ])
            ->assertJsonStructure(['users_count']);

        // Department should still exist
        $this->assertDatabaseHas('departments', ['id' => $dept->id]);
    }

    public function test_delete_nonexistent_department_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson('/api/v1/departments/99999');

        $response->assertStatus(404);
    }
}
