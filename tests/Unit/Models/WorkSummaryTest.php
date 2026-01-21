<?php

namespace Tests\Unit\Models;

use App\Models\WorkSummary;
use PHPUnit\Framework\TestCase;

class WorkSummaryTest extends TestCase
{
    public function test_fillable_attributes_are_configured(): void
    {
        $summary = new WorkSummary();

        $fillable = $summary->getFillable();

        $this->assertContains('worker_id', $fillable);
        $this->assertContains('period_type', $fillable);
        $this->assertContains('period_start', $fillable);
        $this->assertContains('period_end', $fillable);
        $this->assertContains('total_minutes', $fillable);
        $this->assertContains('regular_minutes', $fillable);
        $this->assertContains('overtime_minutes', $fillable);
        $this->assertContains('days_worked', $fillable);
        $this->assertContains('days_absent', $fillable);
        $this->assertContains('late_arrivals', $fillable);
        $this->assertContains('early_departures', $fillable);
        $this->assertContains('missing_checkouts', $fillable);
        $this->assertContains('missing_checkins', $fillable);
        $this->assertContains('calculated_at', $fillable);
    }

    public function test_casts_are_configured(): void
    {
        $summary = new WorkSummary();

        $casts = $summary->getCasts();

        $this->assertEquals('date', $casts['period_start']);
        $this->assertEquals('date', $casts['period_end']);
        $this->assertEquals('datetime', $casts['calculated_at']);
    }

    public function test_total_hours_attribute_converts_minutes_to_hours(): void
    {
        $summary = new WorkSummary();
        $summary->total_minutes = 480; // 8 hours

        $this->assertEquals(8.0, $summary->total_hours);
    }

    public function test_total_hours_attribute_rounds_to_two_decimals(): void
    {
        $summary = new WorkSummary();
        $summary->total_minutes = 125; // 2 hours 5 minutes = 2.0833...

        $this->assertEquals(2.08, $summary->total_hours);
    }

    public function test_regular_hours_attribute_converts_minutes_to_hours(): void
    {
        $summary = new WorkSummary();
        $summary->regular_minutes = 480;

        $this->assertEquals(8.0, $summary->regular_hours);
    }

    public function test_overtime_hours_attribute_converts_minutes_to_hours(): void
    {
        $summary = new WorkSummary();
        $summary->overtime_minutes = 60;

        $this->assertEquals(1.0, $summary->overtime_hours);
    }

    public function test_formatted_total_time_attribute_returns_hours_and_minutes(): void
    {
        $summary = new WorkSummary();
        $summary->total_minutes = 485; // 8 hours 5 minutes

        $this->assertEquals('8:05', $summary->formatted_total_time);
    }

    public function test_formatted_total_time_attribute_handles_zero_minutes(): void
    {
        $summary = new WorkSummary();
        $summary->total_minutes = 480; // 8 hours 0 minutes

        $this->assertEquals('8:00', $summary->formatted_total_time);
    }

    public function test_formatted_total_time_attribute_handles_zero_hours(): void
    {
        $summary = new WorkSummary();
        $summary->total_minutes = 45; // 0 hours 45 minutes

        $this->assertEquals('0:45', $summary->formatted_total_time);
    }

    public function test_formatted_total_time_attribute_handles_large_values(): void
    {
        $summary = new WorkSummary();
        $summary->total_minutes = 2400; // 40 hours

        $this->assertEquals('40:00', $summary->formatted_total_time);
    }
}
