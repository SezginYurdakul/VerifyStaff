<?php

namespace Tests\Feature\Api;

use App\Events\SettingChanged;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    // Settings are seeded automatically via migration

    // ==================== Index Tests ====================

    public function test_admin_can_get_all_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'settings' => [
                    'work_hours',
                    'attendance',
                ],
            ]);
    }

    public function test_representative_cannot_get_all_settings(): void
    {
        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. Admin access required.']);
    }

    public function test_worker_cannot_get_all_settings(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings');

        $response->assertStatus(403);
    }

    // ==================== Group Tests ====================

    public function test_admin_can_get_settings_by_group(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/group/work_hours');

        $response->assertOk()
            ->assertJson([
                'group' => 'work_hours',
            ])
            ->assertJsonStructure([
                'settings' => [
                    'work_start_time',
                    'work_end_time',
                ],
            ]);
    }

    public function test_get_settings_by_invalid_group_returns_error(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/group/invalid_group');

        $response->assertStatus(400)
            ->assertJson(['message' => 'Invalid settings group']);
    }

    // ==================== Show Tests ====================

    public function test_admin_can_get_single_setting(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/work_start_time');

        $response->assertOk()
            ->assertJson([
                'key' => 'work_start_time',
                'value' => '09:00',
                'type' => 'time',
            ]);
    }

    public function test_get_nonexistent_setting_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/nonexistent_key');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Setting not found']);
    }

    // ==================== Update Tests ====================

    public function test_admin_can_update_setting(): void
    {
        Event::fake([SettingChanged::class]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/work_start_time', [
                'value' => '08:30',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Setting updated successfully',
                'key' => 'work_start_time',
                'value' => '08:30',
            ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'work_start_time',
            'value' => '08:30',
        ]);

        Event::assertDispatched(SettingChanged::class, function ($event) {
            return $event->key === 'work_start_time'
                && $event->oldValue === '09:00'
                && $event->newValue === '08:30';
        });
    }

    public function test_update_setting_validates_integer_type(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/late_threshold_minutes', [
                'value' => 'not_a_number',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid value',
            ]);
    }

    public function test_update_setting_validates_time_type(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/work_start_time', [
                'value' => 'invalid_time',
            ]);

        $response->assertStatus(422);
    }

    public function test_update_nonexistent_setting_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/nonexistent_key', [
                'value' => 'test',
            ]);

        $response->assertStatus(404);
    }

    // ==================== Bulk Update Tests ====================

    public function test_admin_can_bulk_update_settings(): void
    {
        Event::fake([SettingChanged::class]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings', [
                'settings' => [
                    ['key' => 'work_start_time', 'value' => '08:00'],
                    ['key' => 'work_end_time', 'value' => '17:00'],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Settings updated',
                'updated_count' => 2,
                'error_count' => 0,
            ]);

        Event::assertDispatchedTimes(SettingChanged::class, 2);
    }

    public function test_bulk_update_reports_errors_for_invalid_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings', [
                'settings' => [
                    ['key' => 'work_start_time', 'value' => '08:00'],
                    ['key' => 'nonexistent_key', 'value' => 'test'],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'updated_count' => 1,
                'error_count' => 1,
            ]);
    }

    // ==================== Work Hours Tests ====================

    public function test_representative_can_get_work_hours_config(): void
    {
        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/work-hours');

        $response->assertOk()
            ->assertJsonStructure([
                'config',
                'working_days',
                'shifts_enabled',
                'shifts',
                'default_shift',
            ]);
    }

    public function test_admin_can_get_work_hours_config(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/work-hours');

        $response->assertOk();
    }

    public function test_worker_cannot_get_work_hours_config(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/work-hours');

        $response->assertStatus(403);
    }

    // ==================== Attendance Mode Tests ====================

    public function test_representative_can_get_attendance_mode(): void
    {
        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/settings/attendance-mode');

        $response->assertOk()
            ->assertJsonStructure([
                'attendance_mode',
                'description',
            ]);
    }

    public function test_admin_can_update_attendance_mode(): void
    {
        Event::fake([SettingChanged::class]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/config/attendance-mode', [
                'attendance_mode' => 'kiosk',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Attendance mode updated successfully',
                'attendance_mode' => 'kiosk',
            ]);

        Event::assertDispatched(SettingChanged::class);
    }

    public function test_update_attendance_mode_validates_value(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/config/attendance-mode', [
                'attendance_mode' => 'invalid_mode',
            ]);

        $response->assertStatus(422);
    }

    // ==================== Working Days Tests ====================

    public function test_admin_can_update_working_days(): void
    {
        Event::fake([SettingChanged::class]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/settings/config/working-days', [
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Working days updated successfully',
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'weekend_days' => ['saturday', 'sunday'],
            ]);

        Event::assertDispatched(SettingChanged::class);
    }

    // ==================== Auth Tests ====================

    public function test_settings_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/settings');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/settings/work_start_time');
        $response->assertStatus(401);

        $response = $this->putJson('/api/v1/settings/work_start_time', ['value' => '08:00']);
        $response->assertStatus(401);
    }
}
