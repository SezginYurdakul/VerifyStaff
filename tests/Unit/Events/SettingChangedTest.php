<?php

namespace Tests\Unit\Events;

use App\Events\SettingChanged;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class SettingChangedTest extends TestCase
{
    public function test_event_stores_key(): void
    {
        $user = new User();
        $event = new SettingChanged('work_start_time', '09:00', '08:30', $user);

        $this->assertEquals('work_start_time', $event->key);
    }

    public function test_event_stores_old_value(): void
    {
        $user = new User();
        $event = new SettingChanged('work_start_time', '09:00', '08:30', $user);

        $this->assertEquals('09:00', $event->oldValue);
    }

    public function test_event_stores_new_value(): void
    {
        $user = new User();
        $event = new SettingChanged('work_start_time', '09:00', '08:30', $user);

        $this->assertEquals('08:30', $event->newValue);
    }

    public function test_event_stores_changed_by_user(): void
    {
        $user = new User();
        $user->id = 1;
        $user->name = 'Admin';

        $event = new SettingChanged('attendance_mode', 'representative', 'kiosk', $user);

        $this->assertSame($user, $event->changedBy);
    }

    public function test_event_handles_boolean_values(): void
    {
        $user = new User();
        $event = new SettingChanged('shifts_enabled', false, true, $user);

        $this->assertFalse($event->oldValue);
        $this->assertTrue($event->newValue);
    }

    public function test_event_handles_array_values(): void
    {
        $user = new User();
        $oldDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $newDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        $event = new SettingChanged('working_days', $oldDays, $newDays, $user);

        $this->assertEquals($oldDays, $event->oldValue);
        $this->assertEquals($newDays, $event->newValue);
    }

    public function test_event_handles_integer_values(): void
    {
        $user = new User();
        $event = new SettingChanged('late_threshold_minutes', 15, 10, $user);

        $this->assertEquals(15, $event->oldValue);
        $this->assertEquals(10, $event->newValue);
    }
}
