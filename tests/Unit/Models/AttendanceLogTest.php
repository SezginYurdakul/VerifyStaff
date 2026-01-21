<?php

namespace Tests\Unit\Models;

use App\Models\AttendanceLog;
use PHPUnit\Framework\TestCase;

class AttendanceLogTest extends TestCase
{
    public function test_generate_event_id_returns_sha256_hash(): void
    {
        $eventId = AttendanceLog::generateEventId(1, 2, '2026-01-21 09:00:00', 'in');

        $this->assertEquals(64, strlen($eventId));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $eventId);
    }

    public function test_generate_event_id_is_deterministic(): void
    {
        $workerId = 123;
        $repId = 456;
        $deviceTime = '2026-01-21 09:00:00';
        $type = 'in';

        $eventId1 = AttendanceLog::generateEventId($workerId, $repId, $deviceTime, $type);
        $eventId2 = AttendanceLog::generateEventId($workerId, $repId, $deviceTime, $type);

        $this->assertEquals($eventId1, $eventId2);
    }

    public function test_generate_event_id_differs_for_different_worker(): void
    {
        $repId = 456;
        $deviceTime = '2026-01-21 09:00:00';
        $type = 'in';

        $eventId1 = AttendanceLog::generateEventId(1, $repId, $deviceTime, $type);
        $eventId2 = AttendanceLog::generateEventId(2, $repId, $deviceTime, $type);

        $this->assertNotEquals($eventId1, $eventId2);
    }

    public function test_generate_event_id_differs_for_different_rep(): void
    {
        $workerId = 123;
        $deviceTime = '2026-01-21 09:00:00';
        $type = 'in';

        $eventId1 = AttendanceLog::generateEventId($workerId, 1, $deviceTime, $type);
        $eventId2 = AttendanceLog::generateEventId($workerId, 2, $deviceTime, $type);

        $this->assertNotEquals($eventId1, $eventId2);
    }

    public function test_generate_event_id_differs_for_different_time(): void
    {
        $workerId = 123;
        $repId = 456;
        $type = 'in';

        $eventId1 = AttendanceLog::generateEventId($workerId, $repId, '2026-01-21 09:00:00', $type);
        $eventId2 = AttendanceLog::generateEventId($workerId, $repId, '2026-01-21 09:00:01', $type);

        $this->assertNotEquals($eventId1, $eventId2);
    }

    public function test_generate_event_id_differs_for_different_type(): void
    {
        $workerId = 123;
        $repId = 456;
        $deviceTime = '2026-01-21 09:00:00';

        $eventIdIn = AttendanceLog::generateEventId($workerId, $repId, $deviceTime, 'in');
        $eventIdOut = AttendanceLog::generateEventId($workerId, $repId, $deviceTime, 'out');

        $this->assertNotEquals($eventIdIn, $eventIdOut);
    }

    public function test_generate_event_id_works_with_zero_rep_id(): void
    {
        // For kiosk mode, rep_id is 0
        $eventId = AttendanceLog::generateEventId(123, 0, '2026-01-21 09:00:00', 'in');

        $this->assertEquals(64, strlen($eventId));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $eventId);
    }

    public function test_fillable_attributes_are_configured(): void
    {
        $log = new AttendanceLog();

        $fillable = $log->getFillable();

        $this->assertContains('event_id', $fillable);
        $this->assertContains('worker_id', $fillable);
        $this->assertContains('rep_id', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('device_time', $fillable);
        $this->assertContains('device_timezone', $fillable);
        $this->assertContains('sync_status', $fillable);
        $this->assertContains('flagged', $fillable);
        $this->assertContains('flag_reason', $fillable);
        $this->assertContains('work_minutes', $fillable);
        $this->assertContains('is_late', $fillable);
        $this->assertContains('is_overtime', $fillable);
    }

    public function test_casts_are_configured(): void
    {
        $log = new AttendanceLog();

        $casts = $log->getCasts();

        $this->assertEquals('datetime', $casts['device_time']);
        $this->assertEquals('datetime', $casts['sync_time']);
        $this->assertEquals('boolean', $casts['flagged']);
        $this->assertEquals('boolean', $casts['is_late']);
        $this->assertEquals('boolean', $casts['is_early_departure']);
        $this->assertEquals('boolean', $casts['is_overtime']);
    }
}
