<?php

namespace Tests\Unit\Services\Dashboard;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\Dashboard\DashboardOverviewService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardOverviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardOverviewService();
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

    private function createCheckin(User $worker, ?string $time = null, array $extra = []): AttendanceLog
    {
        return AttendanceLog::create(array_merge([
            'event_id' => uniqid('test_', true),
            'worker_id' => $worker->id,
            'type' => 'in',
            'device_time' => $time ? Carbon::parse($time) : Carbon::now(),
            'device_timezone' => 'Europe/Istanbul',
        ], $extra));
    }

    private function createCheckout(User $worker, ?string $time = null, array $extra = []): AttendanceLog
    {
        return AttendanceLog::create(array_merge([
            'event_id' => uniqid('test_', true),
            'worker_id' => $worker->id,
            'type' => 'out',
            'device_time' => $time ? Carbon::parse($time) : Carbon::now(),
            'device_timezone' => 'Europe/Istanbul',
        ], $extra));
    }

    // ==================== Basic Structure Tests ====================

    public function test_get_overview_returns_expected_structure(): void
    {
        $overview = $this->service->getOverview();

        $this->assertIsArray($overview);
        $this->assertArrayHasKey('date', $overview);
        $this->assertArrayHasKey('active_workers', $overview);
        $this->assertArrayHasKey('today', $overview);
        $this->assertArrayHasKey('this_week', $overview);
        $this->assertArrayHasKey('this_month', $overview);
        $this->assertArrayHasKey('alerts', $overview);
    }

    public function test_today_section_has_correct_keys(): void
    {
        $overview = $this->service->getOverview();

        $this->assertArrayHasKey('checkins', $overview['today']);
        $this->assertArrayHasKey('checkouts', $overview['today']);
        $this->assertArrayHasKey('currently_working', $overview['today']);
        $this->assertArrayHasKey('attendance_rate', $overview['today']);
        $this->assertArrayHasKey('missing_checkouts', $overview['today']);
    }

    public function test_week_section_has_correct_keys(): void
    {
        $overview = $this->service->getOverview();

        $this->assertArrayHasKey('total_hours', $overview['this_week']);
        $this->assertArrayHasKey('overtime_hours', $overview['this_week']);
        $this->assertArrayHasKey('unique_workers', $overview['this_week']);
        $this->assertArrayHasKey('late_arrivals', $overview['this_week']);
    }

    public function test_month_section_has_correct_keys(): void
    {
        $overview = $this->service->getOverview();

        $this->assertArrayHasKey('total_hours', $overview['this_month']);
        $this->assertArrayHasKey('overtime_hours', $overview['this_month']);
        $this->assertArrayHasKey('unique_workers', $overview['this_month']);
        $this->assertArrayHasKey('days_with_activity', $overview['this_month']);
    }

    // ==================== Active Workers Tests ====================

    public function test_counts_active_workers_correctly(): void
    {
        $this->createWorker();
        $this->createWorker();
        $this->createWorker(['status' => 'inactive']);
        User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $overview = $this->service->getOverview();

        $this->assertEquals(2, $overview['active_workers']);
    }

    public function test_returns_zero_when_no_active_workers(): void
    {
        $overview = $this->service->getOverview();

        $this->assertEquals(0, $overview['active_workers']);
    }

    // ==================== Today's Attendance Tests ====================

    public function test_counts_todays_checkins_correctly(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();

        $this->createCheckin($worker1, '2026-01-29 08:00:00');
        $this->createCheckin($worker2, '2026-01-29 09:00:00');
        // Yesterday's checkin should not be counted
        $this->createCheckin($worker1, '2026-01-28 08:00:00');

        $overview = $this->service->getOverview();

        $this->assertEquals(2, $overview['today']['checkins']);
    }

    public function test_counts_unique_workers_for_checkins(): void
    {
        $worker = $this->createWorker();

        // Same worker checks in twice today
        $this->createCheckin($worker, '2026-01-29 08:00:00');
        $this->createCheckin($worker, '2026-01-29 12:00:00');

        $overview = $this->service->getOverview();

        $this->assertEquals(1, $overview['today']['checkins']);
    }

    public function test_calculates_attendance_rate_correctly(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();
        $this->createWorker(); // Third worker, no checkin

        $this->createCheckin($worker1, '2026-01-29 08:00:00');
        $this->createCheckin($worker2, '2026-01-29 09:00:00');

        $overview = $this->service->getOverview();

        // 2 out of 3 workers = 66.7%
        $this->assertEquals(66.7, $overview['today']['attendance_rate']);
    }

    public function test_attendance_rate_zero_when_no_workers(): void
    {
        $overview = $this->service->getOverview();

        $this->assertEquals(0, $overview['today']['attendance_rate']);
    }

    // ==================== Currently Working Tests ====================

    public function test_calculates_currently_working_correctly(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();
        $worker3 = $this->createWorker();

        // Worker 1: checked in, not out (currently working)
        $this->createCheckin($worker1, '2026-01-29 08:00:00');

        // Worker 2: checked in and out (not working)
        $this->createCheckin($worker2, '2026-01-29 08:00:00');
        $this->createCheckout($worker2, '2026-01-29 09:00:00');

        // Worker 3: not checked in today

        $overview = $this->service->getOverview();

        $this->assertEquals(1, $overview['today']['currently_working']);
    }

    // ==================== Missing Checkouts Tests ====================

    public function test_counts_missing_checkouts_today(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();

        // Checkin without checkout (missing)
        $this->createCheckin($worker1, '2026-01-29 08:00:00');

        // Checkin with paired checkout (not missing)
        $checkin = $this->createCheckin($worker2, '2026-01-29 08:00:00');
        $checkout = $this->createCheckout($worker2, '2026-01-29 17:00:00');
        $checkin->update(['paired_log_id' => $checkout->id]);

        $overview = $this->service->getOverview();

        $this->assertEquals(1, $overview['today']['missing_checkouts']);
    }

    // ==================== Flagged Records Tests ====================

    public function test_counts_flagged_records(): void
    {
        $worker = $this->createWorker();

        $this->createCheckin($worker, null, [
            'flagged' => true,
            'flag_reason' => 'Test flag',
        ]);

        $this->createCheckout($worker, null, [
            'flagged' => false,
        ]);

        $overview = $this->service->getOverview();

        $this->assertEquals(1, $overview['alerts']['flagged_records']);
    }

    // ==================== Week Stats Tests ====================

    public function test_calculates_week_total_hours(): void
    {
        $worker = $this->createWorker();

        // Monday checkout with 480 minutes (8 hours)
        $this->createCheckout($worker, '2026-01-27 17:00:00', [
            'work_minutes' => 480,
        ]);

        // Tuesday checkout with 420 minutes (7 hours)
        $this->createCheckout($worker, '2026-01-28 17:00:00', [
            'work_minutes' => 420,
        ]);

        $overview = $this->service->getOverview();

        // 15 hours total
        $this->assertEquals(15, $overview['this_week']['total_hours']);
    }

    public function test_calculates_week_overtime_hours(): void
    {
        $worker = $this->createWorker();

        $this->createCheckout($worker, '2026-01-28 19:00:00', [
            'work_minutes' => 600,
            'is_overtime' => true,
            'overtime_minutes' => 120,
        ]);

        $overview = $this->service->getOverview();

        $this->assertEquals(2, $overview['this_week']['overtime_hours']);
    }

    public function test_counts_late_arrivals_this_week(): void
    {
        $worker = $this->createWorker();

        $this->createCheckin($worker, '2026-01-28 09:30:00', [
            'is_late' => true,
        ]);

        $this->createCheckin($worker, '2026-01-29 08:00:00', [
            'is_late' => false,
        ]);

        $overview = $this->service->getOverview();

        $this->assertEquals(1, $overview['this_week']['late_arrivals']);
    }

    // ==================== Month Stats Tests ====================

    public function test_calculates_month_days_with_activity(): void
    {
        $worker = $this->createWorker();

        // Activity on 3 different days
        $this->createCheckout($worker, '2026-01-15 17:00:00', ['work_minutes' => 480]);
        $this->createCheckout($worker, '2026-01-20 17:00:00', ['work_minutes' => 480]);
        $this->createCheckout($worker, '2026-01-29 17:00:00', ['work_minutes' => 480]);

        $overview = $this->service->getOverview();

        $this->assertEquals(3, $overview['this_month']['days_with_activity']);
    }

    public function test_counts_unique_workers_this_month(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();

        $this->createCheckout($worker1, '2026-01-15 17:00:00', ['work_minutes' => 480]);
        $this->createCheckout($worker1, '2026-01-20 17:00:00', ['work_minutes' => 480]);
        $this->createCheckout($worker2, '2026-01-25 17:00:00', ['work_minutes' => 480]);

        $overview = $this->service->getOverview();

        $this->assertEquals(2, $overview['this_month']['unique_workers']);
    }

    // ==================== Date Tests ====================

    public function test_returns_correct_date(): void
    {
        $overview = $this->service->getOverview();

        $this->assertEquals('2026-01-29', $overview['date']);
    }
}
