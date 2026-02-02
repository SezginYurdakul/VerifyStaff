<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-01-29 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    private function createRepresentative(): User
    {
        return User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
    }

    private function createWorker(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'worker',
            'status' => 'active',
        ], $attributes));
    }

    private function createAttendanceLog(User $worker, string $type, string $time, array $extra = []): AttendanceLog
    {
        return AttendanceLog::create(array_merge([
            'event_id' => uniqid('test_', true),
            'worker_id' => $worker->id,
            'type' => $type,
            'device_time' => Carbon::parse($time),
            'device_timezone' => 'Europe/Istanbul',
        ], $extra));
    }

    // ==================== Authorization Tests ====================

    public function test_overview_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(401);
    }

    public function test_trends_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/dashboard/trends');

        $response->assertStatus(401);
    }

    public function test_anomalies_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(401);
    }

    public function test_worker_cannot_access_overview(): void
    {
        $worker = $this->createWorker();

        $response = $this->actingAs($worker)->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(403);
    }

    public function test_worker_cannot_access_trends(): void
    {
        $worker = $this->createWorker();

        $response = $this->actingAs($worker)->getJson('/api/v1/dashboard/trends');

        $response->assertStatus(403);
    }

    public function test_worker_cannot_access_anomalies(): void
    {
        $worker = $this->createWorker();

        $response = $this->actingAs($worker)->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_overview(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(200);
    }

    public function test_representative_can_access_overview(): void
    {
        $rep = $this->createRepresentative();

        $response = $this->actingAs($rep)->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_trends(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/trends');

        $response->assertStatus(200);
    }

    public function test_representative_can_access_trends(): void
    {
        $rep = $this->createRepresentative();

        $response = $this->actingAs($rep)->getJson('/api/v1/dashboard/trends');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_anomalies(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(200);
    }

    public function test_representative_can_access_anomalies(): void
    {
        $rep = $this->createRepresentative();

        $response = $this->actingAs($rep)->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(200);
    }

    // ==================== Overview Response Structure Tests ====================

    public function test_overview_returns_correct_structure(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'active_workers',
                'today' => [
                    'checkins',
                    'checkouts',
                    'currently_working',
                    'attendance_rate',
                    'missing_checkouts',
                ],
                'this_week' => [
                    'total_hours',
                    'overtime_hours',
                    'unique_workers',
                    'late_arrivals',
                ],
                'this_month' => [
                    'total_hours',
                    'overtime_hours',
                    'unique_workers',
                    'days_with_activity',
                ],
                'alerts' => [
                    'flagged_records',
                    'missing_checkouts_today',
                ],
            ]);
    }

    public function test_overview_returns_correct_data(): void
    {
        $admin = $this->createAdmin();
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();

        // Create some attendance
        $this->createAttendanceLog($worker1, 'in', '2026-01-29 08:00:00');
        $this->createAttendanceLog($worker2, 'in', '2026-01-29 09:00:00');

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(200)
            ->assertJson([
                'date' => '2026-01-29',
                'active_workers' => 2,
                'today' => [
                    'checkins' => 2,
                    'currently_working' => 2,
                    'attendance_rate' => 100,
                ],
            ]);
    }

    // ==================== Trends Response Structure Tests ====================

    public function test_trends_returns_correct_structure(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/trends');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => [
                    'start',
                    'end',
                    'days',
                ],
                'averages' => [
                    'daily_checkins',
                    'daily_hours',
                    'attendance_rate',
                ],
                'data' => [
                    '*' => [
                        'date',
                        'day',
                        'checkins',
                        'checkouts',
                        'total_hours',
                        'late_arrivals',
                        'early_departures',
                        'attendance_rate',
                    ],
                ],
            ]);
    }

    public function test_trends_accepts_days_parameter(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/trends?days=14');

        $response->assertStatus(200)
            ->assertJson([
                'period' => [
                    'days' => 14,
                ],
            ]);

        $this->assertCount(14, $response->json('data'));
    }

    public function test_trends_enforces_minimum_days(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/trends?days=3');

        $response->assertStatus(200)
            ->assertJson([
                'period' => [
                    'days' => 7,
                ],
            ]);
    }

    public function test_trends_enforces_maximum_days(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/trends?days=100');

        $response->assertStatus(200)
            ->assertJson([
                'period' => [
                    'days' => 90,
                ],
            ]);
    }

    // ==================== Anomalies Response Structure Tests ====================

    public function test_anomalies_returns_correct_structure(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'flagged_count',
                    'missing_checkouts_count',
                    'late_arrivals_this_week',
                    'inactive_workers',
                ],
                'flagged',
                'missing_checkouts',
                'late_arrivals',
                'inactive_workers',
            ]);
    }

    public function test_anomalies_accepts_limit_parameter(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        // Create 15 flagged records
        for ($i = 0; $i < 15; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $this->createAttendanceLog($worker, 'in', "2026-01-28 {$hour}:00:00", [
                'flagged' => true,
            ]);
        }

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/anomalies?limit=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('flagged'));
        $this->assertEquals(15, $response->json('summary.flagged_count'));
    }

    public function test_anomalies_returns_flagged_records(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker(['name' => 'Test Worker']);

        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00', [
            'flagged' => true,
            'flag_reason' => 'Test reason',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(200)
            ->assertJsonPath('flagged.0.type', 'flagged')
            ->assertJsonPath('flagged.0.severity', 'high')
            ->assertJsonPath('flagged.0.reason', 'Test reason')
            ->assertJsonPath('flagged.0.worker.name', 'Test Worker');
    }

    public function test_anomalies_returns_missing_checkouts(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        // Yesterday's checkin without checkout
        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00');

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(200)
            ->assertJsonPath('missing_checkouts.0.type', 'missing_checkout')
            ->assertJsonPath('missing_checkouts.0.severity', 'medium');
    }

    public function test_anomalies_returns_late_arrivals(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        $this->createAttendanceLog($worker, 'in', '2026-01-28 09:30:00', [
            'is_late' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(200)
            ->assertJsonPath('late_arrivals.0.type', 'late_arrival')
            ->assertJsonPath('late_arrivals.0.severity', 'low');
    }

    public function test_anomalies_returns_inactive_workers(): void
    {
        $admin = $this->createAdmin();
        $activeWorker = $this->createWorker(['name' => 'Active']);
        $inactiveWorker = $this->createWorker(['name' => 'Inactive']);

        // Active worker has attendance
        $this->createAttendanceLog($activeWorker, 'in', '2026-01-28 08:00:00');

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/anomalies');

        $response->assertStatus(200);

        $inactiveNames = collect($response->json('inactive_workers'))->pluck('name')->toArray();
        $this->assertContains('Inactive', $inactiveNames);
        $this->assertNotContains('Active', $inactiveNames);
    }

    // ==================== Integration Tests ====================

    public function test_overview_includes_week_stats(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        // Monday's checkout with work hours
        $this->createAttendanceLog($worker, 'out', '2026-01-27 17:00:00', [
            'work_minutes' => 480,
            'is_overtime' => true,
            'overtime_minutes' => 60,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(200)
            ->assertJson([
                'this_week' => [
                    'total_hours' => 8,
                    'overtime_hours' => 1,
                    'unique_workers' => 1,
                ],
            ]);
    }

    public function test_trends_includes_attendance_data(): void
    {
        $admin = $this->createAdmin();
        $worker = $this->createWorker();

        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00');
        $this->createAttendanceLog($worker, 'out', '2026-01-28 17:00:00', [
            'work_minutes' => 540,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/dashboard/trends');

        $response->assertStatus(200);

        $jan28 = collect($response->json('data'))->firstWhere('date', '2026-01-28');

        $this->assertEquals(1, $jan28['checkins']);
        $this->assertEquals(1, $jan28['checkouts']);
        $this->assertEquals(9, $jan28['total_hours']);
    }
}
