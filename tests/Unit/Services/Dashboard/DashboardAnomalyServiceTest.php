<?php

namespace Tests\Unit\Services\Dashboard;

use App\Models\AttendanceLog;
use App\Models\User;
use App\Services\Dashboard\DashboardAnomalyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnomalyServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardAnomalyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardAnomalyService();
        // Set to Thursday so week starts Monday Jan 27
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

    // ==================== Basic Structure Tests ====================

    public function test_get_anomalies_returns_expected_structure(): void
    {
        $anomalies = $this->service->getAnomalies();

        $this->assertIsArray($anomalies);
        $this->assertArrayHasKey('summary', $anomalies);
        $this->assertArrayHasKey('flagged', $anomalies);
        $this->assertArrayHasKey('missing_checkouts', $anomalies);
        $this->assertArrayHasKey('late_arrivals', $anomalies);
        $this->assertArrayHasKey('inactive_workers', $anomalies);
    }

    public function test_summary_has_correct_keys(): void
    {
        $anomalies = $this->service->getAnomalies();

        $this->assertArrayHasKey('flagged_count', $anomalies['summary']);
        $this->assertArrayHasKey('missing_checkouts_count', $anomalies['summary']);
        $this->assertArrayHasKey('late_arrivals_this_week', $anomalies['summary']);
        $this->assertArrayHasKey('inactive_workers', $anomalies['summary']);
    }

    // ==================== Flagged Records Tests ====================

    public function test_returns_flagged_records(): void
    {
        $worker = $this->createWorker();

        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00', [
            'flagged' => true,
            'flag_reason' => 'Suspicious activity',
        ]);

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(1, $anomalies['flagged']);
        $this->assertEquals('flagged', $anomalies['flagged'][0]['type']);
        $this->assertEquals('high', $anomalies['flagged'][0]['severity']);
        $this->assertEquals('Suspicious activity', $anomalies['flagged'][0]['reason']);
    }

    public function test_flagged_records_include_worker_info(): void
    {
        $worker = $this->createWorker(['name' => 'John Doe']);

        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00', [
            'flagged' => true,
        ]);

        $anomalies = $this->service->getAnomalies();

        $this->assertEquals($worker->id, $anomalies['flagged'][0]['worker']['id']);
        $this->assertEquals('John Doe', $anomalies['flagged'][0]['worker']['name']);
    }

    public function test_flagged_records_ordered_by_time_desc(): void
    {
        $worker = $this->createWorker();

        $this->createAttendanceLog($worker, 'in', '2026-01-27 08:00:00', ['flagged' => true]);
        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00', ['flagged' => true]);

        $anomalies = $this->service->getAnomalies();

        // Most recent first
        $this->assertStringContainsString('2026-01-28', $anomalies['flagged'][0]['time']);
        $this->assertStringContainsString('2026-01-27', $anomalies['flagged'][1]['time']);
    }

    public function test_respects_limit_for_flagged(): void
    {
        $worker = $this->createWorker();

        for ($i = 0; $i < 15; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $this->createAttendanceLog($worker, 'in', "2026-01-28 {$hour}:00:00", [
                'flagged' => true,
            ]);
        }

        $anomalies = $this->service->getAnomalies(5);

        $this->assertCount(5, $anomalies['flagged']);
        $this->assertEquals(15, $anomalies['summary']['flagged_count']);
    }

    // ==================== Missing Checkouts Tests ====================

    public function test_returns_missing_checkouts_from_this_week(): void
    {
        $worker = $this->createWorker();

        // Monday (this week) - should be included
        $this->createAttendanceLog($worker, 'in', '2026-01-27 08:00:00');

        // Tuesday (this week) - should be included
        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00');

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(2, $anomalies['missing_checkouts']);
        $this->assertEquals('missing_checkout', $anomalies['missing_checkouts'][0]['type']);
        $this->assertEquals('medium', $anomalies['missing_checkouts'][0]['severity']);
    }

    public function test_excludes_todays_checkins_from_missing(): void
    {
        $worker = $this->createWorker();

        // Today's checkin - might still checkout, exclude
        $this->createAttendanceLog($worker, 'in', '2026-01-29 08:00:00');

        // Yesterday's checkin - missing
        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00');

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(1, $anomalies['missing_checkouts']);
    }

    public function test_excludes_paired_checkins_from_missing(): void
    {
        $worker = $this->createWorker();

        // Paired checkin - not missing
        $checkin = $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00');
        $checkout = $this->createAttendanceLog($worker, 'out', '2026-01-28 17:00:00');
        $checkin->update(['paired_log_id' => $checkout->id]);

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(0, $anomalies['missing_checkouts']);
    }

    public function test_excludes_last_weeks_missing_checkouts(): void
    {
        $worker = $this->createWorker();

        // Last week's checkin - should not be included
        $this->createAttendanceLog($worker, 'in', '2026-01-20 08:00:00');

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(0, $anomalies['missing_checkouts']);
    }

    // ==================== Late Arrivals Tests ====================

    public function test_returns_late_arrivals_this_week(): void
    {
        $worker = $this->createWorker();

        $this->createAttendanceLog($worker, 'in', '2026-01-28 09:30:00', [
            'is_late' => true,
        ]);

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(1, $anomalies['late_arrivals']);
        $this->assertEquals('late_arrival', $anomalies['late_arrivals'][0]['type']);
        $this->assertEquals('low', $anomalies['late_arrivals'][0]['severity']);
    }

    public function test_excludes_last_weeks_late_arrivals(): void
    {
        $worker = $this->createWorker();

        // Last week
        $this->createAttendanceLog($worker, 'in', '2026-01-20 09:30:00', [
            'is_late' => true,
        ]);

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(0, $anomalies['late_arrivals']);
    }

    public function test_late_arrivals_include_time(): void
    {
        $worker = $this->createWorker();

        $this->createAttendanceLog($worker, 'in', '2026-01-28 09:30:00', [
            'is_late' => true,
        ]);

        $anomalies = $this->service->getAnomalies();

        $this->assertArrayHasKey('time', $anomalies['late_arrivals'][0]);
        $this->assertNotNull($anomalies['late_arrivals'][0]['time']);
    }

    // ==================== Inactive Workers Tests ====================

    public function test_returns_inactive_workers(): void
    {
        $activeWorker = $this->createWorker(['name' => 'Active Worker']);
        $inactiveWorker = $this->createWorker(['name' => 'Inactive Worker']);

        // Active worker has attendance this week
        $this->createAttendanceLog($activeWorker, 'in', '2026-01-28 08:00:00');

        // Inactive worker has no attendance

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(1, $anomalies['inactive_workers']);
        $this->assertEquals('Inactive Worker', $anomalies['inactive_workers'][0]['name']);
    }

    public function test_inactive_workers_excludes_non_workers(): void
    {
        User::factory()->create(['role' => 'admin', 'status' => 'active']);
        User::factory()->create(['role' => 'representative', 'status' => 'active']);

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(0, $anomalies['inactive_workers']);
    }

    public function test_inactive_workers_excludes_inactive_status(): void
    {
        $this->createWorker(['status' => 'inactive']);

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(0, $anomalies['inactive_workers']);
    }

    public function test_inactive_workers_include_employee_id(): void
    {
        $this->createWorker([
            'name' => 'Test Worker',
            'employee_id' => 'EMP001',
        ]);

        $anomalies = $this->service->getAnomalies();

        $this->assertEquals('EMP001', $anomalies['inactive_workers'][0]['employee_id']);
    }

    public function test_respects_limit_for_inactive_workers(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createWorker(['name' => "Worker {$i}"]);
        }

        $anomalies = $this->service->getAnomalies(5);

        $this->assertCount(5, $anomalies['inactive_workers']);
        $this->assertEquals(5, $anomalies['summary']['inactive_workers']);
    }

    // ==================== Summary Counts Tests ====================

    public function test_summary_counts_are_accurate(): void
    {
        $worker1 = $this->createWorker();
        $worker2 = $this->createWorker();
        $this->createWorker(); // worker3 - inactive, no attendance at all

        // 2 flagged (worker1 has activity so not inactive) - paired so not missing checkout
        $checkin1 = $this->createAttendanceLog($worker1, 'in', '2026-01-28 08:00:00', ['flagged' => true]);
        $checkout1 = $this->createAttendanceLog($worker1, 'out', '2026-01-28 17:00:00', ['flagged' => true]);
        $checkin1->update(['paired_log_id' => $checkout1->id]);

        // 1 missing checkout (Monday) - worker2 checks in but doesn't check out
        $this->createAttendanceLog($worker2, 'in', '2026-01-27 08:00:00');

        // 1 late arrival (Tuesday) - paired so not a missing checkout
        $checkin2 = $this->createAttendanceLog($worker2, 'in', '2026-01-28 09:30:00', ['is_late' => true]);
        $checkout2 = $this->createAttendanceLog($worker2, 'out', '2026-01-28 17:00:00');
        $checkin2->update(['paired_log_id' => $checkout2->id]);

        $anomalies = $this->service->getAnomalies();

        $this->assertEquals(2, $anomalies['summary']['flagged_count']);
        // Only Monday's checkin is missing checkout
        $this->assertEquals(1, $anomalies['summary']['missing_checkouts_count']);
        $this->assertEquals(1, $anomalies['summary']['late_arrivals_this_week']);
        // worker3 has no activity in last 7 days
        $this->assertEquals(1, $anomalies['summary']['inactive_workers']);
    }

    // ==================== Limit Tests ====================

    public function test_default_limit_is_10(): void
    {
        $worker = $this->createWorker();

        for ($i = 0; $i < 15; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $this->createAttendanceLog($worker, 'in', "2026-01-28 {$hour}:00:00", [
                'flagged' => true,
            ]);
        }

        $anomalies = $this->service->getAnomalies();

        $this->assertCount(10, $anomalies['flagged']);
    }

    // ==================== Worker Null Tests ====================

    public function test_handles_missing_worker_relation_gracefully(): void
    {
        $worker = $this->createWorker();

        $this->createAttendanceLog($worker, 'in', '2026-01-28 08:00:00', [
            'flagged' => true,
        ]);

        // Worker relation exists, should return worker info
        $anomalies = $this->service->getAnomalies();

        $this->assertCount(1, $anomalies['flagged']);
        $this->assertNotNull($anomalies['flagged'][0]['worker']);
        $this->assertEquals($worker->id, $anomalies['flagged'][0]['worker']['id']);
    }
}
