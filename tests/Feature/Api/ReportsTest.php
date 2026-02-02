<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkerWithLogs(): User
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        // Create some attendance logs for the worker
        $today = Carbon::today();

        // Check-in at 9:00
        AttendanceLog::create([
            'event_id' => 'checkin-' . $worker->id . '-' . $today->format('Y-m-d'),
            'worker_id' => $worker->id,
            'rep_id' => 1,
            'type' => 'in',
            'device_time' => $today->copy()->setTime(9, 0),
            'device_timezone' => 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
        ]);

        // Check-out at 18:00
        AttendanceLog::create([
            'event_id' => 'checkout-' . $worker->id . '-' . $today->format('Y-m-d'),
            'worker_id' => $worker->id,
            'rep_id' => 1,
            'type' => 'out',
            'device_time' => $today->copy()->setTime(18, 0),
            'device_timezone' => 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
            'work_minutes' => 540, // 9 hours
        ]);

        return $worker;
    }

    // ==================== Daily Summary Tests ====================

    public function test_admin_can_get_worker_daily_summary(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/daily");

        $response->assertOk()
            ->assertJsonStructure([
                'worker' => ['id', 'name'],
                'period',
                'date',
                'summary',
            ])
            ->assertJson([
                'period' => 'daily',
                'worker' => ['id' => $worker->id],
            ]);
    }

    public function test_representative_can_get_worker_daily_summary(): void
    {
        $worker = $this->createWorkerWithLogs();

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/daily");

        $response->assertOk();
    }

    public function test_worker_can_get_own_daily_summary(): void
    {
        $worker = $this->createWorkerWithLogs();
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/daily");

        $response->assertOk();
    }

    public function test_worker_cannot_get_other_worker_daily_summary(): void
    {
        $worker1 = $this->createWorkerWithLogs();
        $worker2 = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker2->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker1->id}/daily");

        $response->assertStatus(403);
    }

    public function test_daily_summary_for_nonexistent_worker_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/summary/99999/daily');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Worker not found']);
    }

    // ==================== Weekly Summary Tests ====================

    public function test_admin_can_get_worker_weekly_summary(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/weekly");

        $response->assertOk()
            ->assertJsonStructure([
                'worker' => ['id', 'name'],
                'period',
                'week_start',
                'week_end',
                'summary',
            ])
            ->assertJson([
                'period' => 'weekly',
            ]);
    }

    // ==================== Monthly Summary Tests ====================

    public function test_admin_can_get_worker_monthly_summary(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/monthly");

        $response->assertOk()
            ->assertJsonStructure([
                'worker' => ['id', 'name'],
                'period',
                'month',
                'month_start',
                'month_end',
                'summary',
            ])
            ->assertJson([
                'period' => 'monthly',
            ]);
    }

    public function test_monthly_summary_accepts_month_parameter(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/monthly?month=2025-12");

        $response->assertOk()
            ->assertJson([
                'month' => '2025-12',
            ]);
    }

    // ==================== Yearly Summary Tests ====================

    public function test_admin_can_get_worker_yearly_summary(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/yearly");

        $response->assertOk()
            ->assertJsonStructure([
                'worker' => ['id', 'name'],
                'period',
                'year',
                'year_start',
                'year_end',
                'summary',
            ])
            ->assertJson([
                'period' => 'yearly',
            ]);
    }

    public function test_yearly_summary_accepts_year_parameter(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/summary/{$worker->id}/yearly?year=2025");

        $response->assertOk()
            ->assertJson([
                'year' => 2025,
            ]);
    }

    // ==================== Worker Logs Tests ====================

    public function test_admin_can_get_worker_logs(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/logs/{$worker->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'worker' => ['id', 'name'],
                'period' => ['from', 'to'],
                'summary',
                'total_days',
                'total_logs',
                'days',
            ]);
    }

    public function test_worker_logs_accepts_date_range(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $from = Carbon::today()->subDays(7)->format('Y-m-d');
        $to = Carbon::today()->format('Y-m-d');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/logs/{$worker->id}?from={$from}&to={$to}");

        $response->assertOk()
            ->assertJson([
                'period' => [
                    'from' => $from,
                    'to' => $to,
                ],
            ]);
    }

    public function test_worker_logs_validates_date_range(): void
    {
        $worker = $this->createWorkerWithLogs();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        // End date before start date
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/reports/logs/{$worker->id}?from=2025-01-10&to=2025-01-01");

        $response->assertStatus(422)
            ->assertJson(['message' => 'End date cannot be before start date']);
    }

    // ==================== Flagged Logs Tests ====================

    public function test_admin_can_get_flagged_logs(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        // Create a flagged log
        AttendanceLog::create([
            'event_id' => 'flagged-1',
            'worker_id' => $worker->id,
            'rep_id' => 1,
            'type' => 'in',
            'device_time' => now(),
            'device_timezone' => 'UTC',
            'sync_time' => now(),
            'sync_status' => 'synced',
            'flagged' => true,
            'flag_reason' => 'Future timestamp detected',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/flagged');

        $response->assertOk()
            ->assertJsonStructure([
                'total',
                'per_page',
                'current_page',
                'last_page',
                'logs',
            ])
            ->assertJson([
                'total' => 1,
            ]);
    }

    public function test_representative_cannot_get_flagged_logs(): void
    {
        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/flagged');

        $response->assertStatus(403);
    }

    // ==================== All Workers Reports Tests ====================

    public function test_admin_can_get_all_workers_daily_summary(): void
    {
        $workers = User::factory()->count(3)->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        // Create attendance logs for today for these workers
        $today = now();
        foreach ($workers as $worker) {
            AttendanceLog::factory()->create([
                'worker_id' => $worker->id,
                'type' => 'in',
                'device_time' => $today,
            ]);
        }

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/all/daily');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'date',
                'total',
                'workers',
            ])
            ->assertJson([
                'period' => 'daily',
                'total' => 3,
            ]);
    }

    public function test_representative_can_get_all_workers_daily_summary(): void
    {
        User::factory()->count(2)->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $rep = User::factory()->create([
            'role' => 'representative',
            'status' => 'active',
        ]);
        $token = $rep->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/all/daily');

        $response->assertOk();
    }

    public function test_worker_cannot_get_all_workers_daily_summary(): void
    {
        $worker = User::factory()->create([
            'role' => 'worker',
            'status' => 'active',
        ]);
        $token = $worker->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/all/daily');

        $response->assertStatus(403);
    }

    public function test_admin_can_get_all_workers_weekly_summary(): void
    {
        User::factory()->count(2)->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/all/weekly');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'week_start',
                'week_end',
                'total',
                'workers',
            ])
            ->assertJson([
                'period' => 'weekly',
            ]);
    }

    public function test_admin_can_get_all_workers_monthly_summary(): void
    {
        User::factory()->count(2)->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/all/monthly');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'month',
                'month_start',
                'month_end',
                'total',
                'workers',
            ])
            ->assertJson([
                'period' => 'monthly',
            ]);
    }

    public function test_admin_can_get_all_workers_yearly_summary(): void
    {
        User::factory()->count(2)->create([
            'role' => 'worker',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/all/yearly');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'year',
                'total_workers',
                'data',
            ])
            ->assertJson([
                'period' => 'yearly',
            ]);
    }

    // ==================== Auth Tests ====================

    public function test_reports_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/reports/summary/1/daily');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/reports/logs/1');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/reports/flagged');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/reports/all/daily');
        $response->assertStatus(401);
    }
}
