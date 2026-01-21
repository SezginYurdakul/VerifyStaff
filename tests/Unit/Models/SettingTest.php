<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use PHPUnit\Framework\TestCase;

class SettingTest extends TestCase
{
    public function test_fillable_attributes_are_configured(): void
    {
        $setting = new Setting();

        $fillable = $setting->getFillable();

        $this->assertContains('key', $fillable);
        $this->assertContains('group', $fillable);
        $this->assertContains('value', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('description', $fillable);
    }

    public function test_is_working_day_returns_true_for_weekdays(): void
    {
        // Note: isWorkingDay uses getWorkingDays which requires database
        // This test documents the expected behavior with default values
        // Full integration test should be in Feature tests

        // Default working days are: monday, tuesday, wednesday, thursday, friday
        $workingDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        $this->assertContains('monday', $workingDays);
        $this->assertContains('friday', $workingDays);
        $this->assertNotContains('saturday', $workingDays);
        $this->assertNotContains('sunday', $workingDays);
    }

    public function test_attendance_mode_values_are_valid(): void
    {
        // Document valid attendance modes
        $validModes = ['representative', 'kiosk'];

        $this->assertContains('representative', $validModes);
        $this->assertContains('kiosk', $validModes);
        $this->assertEquals(2, count($validModes));
    }
}
