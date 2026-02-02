<?php

namespace Tests\Unit\Services\Dashboard;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\Dashboard\DashboardTrendsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTrendsServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardTrendsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardTrendsService();
        Carbon::setTestNow(Carbon::parse('2026-01-29 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createWorker(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'worker',
            'status' => 'active',
        ], $attributes));
    }

    private function createCheckin(User $worker, string $time, array $extra = []): AttendanceLog
    {
        return AttendanceLog::create(array_merge([
            'event_id' => uniqid('test_', true),
            'worker_id' => $worker->id,
            'type' => 'in',
            'device_time' => Carbon::parse($time),
            'device_timezone' => 'Europe/Istanbul',
        ], $extra));
    }

    private function createCheckout(User $worker, string $time, array $extra = []): AttendanceLog
    {
        return AttendanceLog::create(array_merge([
            'event_id' => uniqid('test_', true),
            'worker_id' => $worker->id,
            'type' => 'out',
            'device_time' => Carbon::parse($time),
            'device_timezone' => 'Europe/Istanbul',
        ], $extra));
    }

    // ==================== Basic Structure Tests ====================

    public function test_get_trends_returns_expected_structure(): void
    {
        $trends = $this->service->getTrends(7);

        $this->assertIsArray($trends);
        $this->assertArrayHasKey('period', $trends);
        $this->assertArrayHasKey('averages', $trends);
        $this->assertArrayHasKey('data', $trends);
    }

    public function test_period_has_correct_keys(): void
    {
        $trends = $this->service->getTrends(7);

        $this->assertArrayHasKey('start', $trends['period']);
        $this->assertArrayHasKey('end', $trends['period']);
        $this->assertArrayHasKey('days', $trends['period']);
    }

    public function test_averages_has_correct_keys(): void
    {
        $trends = $this->service->getTrends(7);

        $this->assertArrayHasKey('daily_checkins', $trends['averages']);
        $this->assertArrayHasKey('daily_hours', $trends['averages']);
        $this->assertArrayHasKey('attendance_rate', $trends['averages']);
    }

    public function test_data_point_has_correct_keys(): void
    {
        $trends = $this->service->getTrends(7);

        $this->assertNotEmpty($trends['data']);

        $dataPoint = $trends['data'][0];
        $this->assertArrayHasKey('date', $dataPoint);
        $this->assertArrayHasKey('day', $dataPoint);
        $this->assertArrayHasKey('checkins', $dataPoint);
        $this->assertArrayHasKey('checkouts', $dataPoint);
        $this->assertArrayHasKey('total_hours', $dataPoint);
        $this->assertArrayHasKey('late_arrivals', $dataPoint);
        $this->assertArrayHasKey('early_departures', $dataPoint);
        $this->assertArrayHasKey('attendance_rate', $dataPoint);
    }

    // ==================== Days Parameter Tests ====================

    public function test_returns_7_days_by_default(): void
    {
        $trends = $this->service->getTrends();

        $this->assertEquals(7, $trends['period']['days']);
        $this->assertCount(7, $trends['data']);
    }

    public function test_returns_requested_days(): void
    {
        $trends = $this->service->getTrends(14);

        $this->assertEquals(14, $trends['period']['days']);
        $this->assertCount(14, $trends['data']);
    }

    public function test_minimum_days_is_7(): void
    {
        $trends = $this->service->getTrends(3);

        $this->assertEquals(7, $trends['period']['days']);
    }

    public function test_maximum_days_is_90(): void
    {
        $trends = $this->service->getTrends(100);

        $this->assertEquals(90, $trends['period']['days']);
    }

    // ==================== Period Tests ====================

    public function test_period_dates_are_correct(): void
    {
        $trends = $this->service->getTrends(7);

        $this->assertEquals('2026-01-23', $trends['period']['start']);
        $this->assertEquals('2026-01-29', $trends['period']['end']);
    }

    public function test_data_includes_all_dates_in_range(): void
    {
        $trends = $this->service->getTrends(7);

        $dates = array_column($trends['data'], 'date');

        $this->assertContains('2026-01-23', $dates);
        $this->assertContains('2026-01-24', $dates);
        $this->assertContains('2026-01-25', $dates);
        $this->assertContains('2026-01-26', $dates);
        $this->assertContains('2026-01-27', $dates);
        $this->assertContains('2026-01-28', $dates);
        $this->assertContains('2026-01-29', $dates);
    }

    // ==================== Checkin/Checkout Counts Tests ====================

    public function test_counts_checkins_per_day(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();

        $this->createCheckin($worker1, '2026-01-28 08:00:00');
        $this->createCheckin($worker2, '2026-01-28 09:00:00');
        $this->createCheckin($worker1, '2026-01-27 08:00:00');

        $trends = $this->service->getTrends(7);

        $jan28 = collect($trends['data'])->firstWhere('date', '2026-01-28');
        $jan27 = collect($trends['data'])->firstWhere('date', '2026-01-27');

        $this->assertEquals(2, $jan28['checkins']);
        $this->assertEquals(1, $jan27['checkins']);
    }

    public function test_counts_unique_workers_for_daily_checkins(): void
    {
        $worker = $this->createWorker();

        // Same worker checks in twice on same day
        $this->createCheckin($worker, '2026-01-28 08:00:00');
        $this->createCheckin($worker, '2026-01-28 13:00:00');

        $trends = $this->service->getTrends(7);

        $jan28 = collect($trends['data'])->firstWhere('date', '2026-01-28');

        $this->assertEquals(1, $jan28['checkins']);
    }

    public function test_counts_checkouts_per_day(): void
    {
        $worker = $this->createWorker();

        $this->createCheckout($worker, '2026-01-28 17:00:00', ['work_minutes' => 480]);
        $this->createCheckout($worker, '2026-01-27 17:00:00', ['work_minutes' => 420]);

        $trends = $this->service->getTrends(7);

        $jan28 = collect($trends['data'])->firstWhere('date', '2026-01-28');
        $jan27 = collect($trends['data'])->firstWhere('date', '2026-01-27');

        $this->assertEquals(1, $jan28['checkouts']);
        $this->assertEquals(1, $jan27['checkouts']);
    }

    // ==================== Hours Tests ====================

    public function test_calculates_total_hours_per_day(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();

        // 8 hours + 7 hours = 15 hours
        $this->createCheckout($worker1, '2026-01-28 17:00:00', ['work_minutes' => 480]);
        $this->createCheckout($worker2, '2026-01-28 17:00:00', ['work_minutes' => 420]);

        $trends = $this->service->getTrends(7);

        $jan28 = collect($trends['data'])->firstWhere('date', '2026-01-28');

        $this->assertEquals(15, $jan28['total_hours']);
    }

    public function test_returns_zero_hours_for_days_without_activity(): void
    {
        $trends = $this->service->getTrends(7);

        $jan23 = collect($trends['data'])->firstWhere('date', '2026-01-23');

        $this->assertEquals(0, $jan23['total_hours']);
    }

    // ==================== Late Arrivals Tests ====================

    public function test_counts_late_arrivals_per_day(): void
    {
        $worker = $this->createWorker();

        $this->createCheckin($worker, '2026-01-28 09:30:00', ['is_late' => true]);
        $this->createCheckin($worker, '2026-01-27 09:45:00', ['is_late' => true]);
        $this->createCheckin($worker, '2026-01-27 10:00:00', ['is_late' => true]);

        $trends = $this->service->getTrends(7);

        $jan28 = collect($trends['data'])->firstWhere('date', '2026-01-28');
        $jan27 = collect($trends['data'])->firstWhere('date', '2026-01-27');

        $this->assertEquals(1, $jan28['late_arrivals']);
        $this->assertEquals(2, $jan27['late_arrivals']);
    }

    // ==================== Early Departures Tests ====================

    public function test_counts_early_departures_per_day(): void
    {
        $worker = $this->createWorker();

        $this->createCheckout($worker, '2026-01-28 15:00:00', ['is_early_departure' => true]);

        $trends = $this->service->getTrends(7);

        $jan28 = collect($trends['data'])->firstWhere('date', '2026-01-28');

        $this->assertEquals(1, $jan28['early_departures']);
    }

    // ==================== Attendance Rate Tests ====================

    public function test_calculates_attendance_rate_per_day(): void
    {
        // Create 4 active workers
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();
        $this->createWorker();
        $this->createWorker();

        // 2 out of 4 check in = 50%
        $this->createCheckin($worker1, '2026-01-28 08:00:00');
        $this->createCheckin($worker2, '2026-01-28 08:00:00');

        $trends = $this->service->getTrends(7);

        $jan28 = collect($trends['data'])->firstWhere('date', '2026-01-28');

        $this->assertEquals(50.0, $jan28['attendance_rate']);
    }

    public function test_attendance_rate_is_zero_when_no_workers(): void
    {
        $trends = $this->service->getTrends(7);

        $jan28 = collect($trends['data'])->firstWhere('date', '2026-01-28');

        $this->assertEquals(0, $jan28['attendance_rate']);
    }

    // ==================== Averages Tests ====================

    public function test_calculates_average_daily_checkins(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();

        // Day 1: 2 checkins, Day 2: 1 checkin = avg 0.43 over 7 days
        $this->createCheckin($worker1, '2026-01-28 08:00:00');
        $this->createCheckin($worker2, '2026-01-28 09:00:00');
        $this->createCheckin($worker1, '2026-01-27 08:00:00');

        $trends = $this->service->getTrends(7);

        // (2 + 1 + 0 + 0 + 0 + 0 + 0) / 7 = 0.4
        $this->assertEquals(0.4, $trends['averages']['daily_checkins']);
    }

    public function test_calculates_average_daily_hours(): void
    {
        $worker = $this->createWorker();

        // 8 hours on one day, 7 hours on another
        $this->createCheckout($worker, '2026-01-28 17:00:00', ['work_minutes' => 480]);
        $this->createCheckout($worker, '2026-01-27 17:00:00', ['work_minutes' => 420]);

        $trends = $this->service->getTrends(7);

        // (8 + 7 + 0 + 0 + 0 + 0 + 0) / 7 = 2.1
        $this->assertEquals(2.1, $trends['averages']['daily_hours']);
    }

    // ==================== Day Name Tests ====================

    public function test_includes_day_name(): void
    {
        $trends = $this->service->getTrends(7);

        // 2026-01-29 is a Thursday
        $jan29 = collect($trends['data'])->firstWhere('date', '2026-01-29');

        $this->assertEquals('Thu', $jan29['day']);
    }
}
