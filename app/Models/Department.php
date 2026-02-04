<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'shift_start',
        'shift_end',
        'late_threshold_minutes',
        'early_departure_threshold_minutes',
        'regular_work_minutes',
        'working_days',
        'description',
        'is_active',
    ];

    protected $casts = [
        'shift_start' => 'string',
        'shift_end' => 'string',
        'late_threshold_minutes' => 'integer',
        'early_departure_threshold_minutes' => 'integer',
        'regular_work_minutes' => 'integer',
        'working_days' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get users in this department.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get active users in this department.
     */
    public function activeUsers(): HasMany
    {
        return $this->hasMany(User::class)->where('status', 'active');
    }

    /**
     * Get workers in this department.
     */
    public function workers(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'worker');
    }

    /**
     * Check if a day is a working day for this department.
     */
    public function isWorkingDay(string $dayName): bool
    {
        $workingDays = $this->working_days ?? Setting::getWorkingDays();
        return in_array(strtolower($dayName), $workingDays);
    }

    /**
     * Get work hours config for this department.
     */
    public function getWorkHoursConfig(): array
    {
        return [
            'work_start_time' => $this->shift_start,
            'work_end_time' => $this->shift_end,
            'late_threshold_minutes' => $this->late_threshold_minutes,
            'early_departure_threshold_minutes' => $this->early_departure_threshold_minutes,
            'regular_work_minutes' => $this->regular_work_minutes,
        ];
    }

    /**
     * Scope for active departments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
