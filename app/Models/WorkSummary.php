<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSummary extends Model
{
    protected $fillable = [
        'worker_id',
        'period_type',
        'period_start',
        'period_end',
        'total_minutes',
        'regular_minutes',
        'overtime_minutes',
        'days_worked',
        'days_absent',
        'late_arrivals',
        'early_departures',
        'missing_checkouts',
        'missing_checkins',
        'is_dirty',
        'source_hash',
        'calculated_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'calculated_at' => 'datetime',
        'is_dirty' => 'boolean',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function getTotalHoursAttribute(): float
    {
        return round($this->total_minutes / 60, 2);
    }

    public function getRegularHoursAttribute(): float
    {
        return round($this->regular_minutes / 60, 2);
    }

    public function getOvertimeHoursAttribute(): float
    {
        return round($this->overtime_minutes / 60, 2);
    }

    public function getFormattedTotalTimeAttribute(): string
    {
        $hours = floor($this->total_minutes / 60);
        $minutes = $this->total_minutes % 60;
        return sprintf('%d:%02d', $hours, $minutes);
    }
}
