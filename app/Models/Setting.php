<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'group',
        'value',
        'type',
        'description',
    ];

    private const CACHE_KEY = 'app_settings';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = self::getAllCached();

        if (!isset($settings[$key])) {
            return $default;
        }

        return self::castValue($settings[$key]['value'], $settings[$key]['type']);
    }

    /**
     * Get multiple settings by keys.
     */
    public static function getValues(array $keys): array
    {
        $settings = self::getAllCached();
        $result = [];

        foreach ($keys as $key) {
            if (isset($settings[$key])) {
                $result[$key] = self::castValue($settings[$key]['value'], $settings[$key]['type']);
            }
        }

        return $result;
    }

    /**
     * Get all settings in a group.
     */
    public static function getGroup(string $group): array
    {
        $settings = self::getAllCached();
        $result = [];

        foreach ($settings as $key => $setting) {
            if ($setting['group'] === $group) {
                $result[$key] = self::castValue($setting['value'], $setting['type']);
            }
        }

        return $result;
    }

    /**
     * Set a setting value.
     */
    public static function setValue(string $key, mixed $value): bool
    {
        $setting = self::where('key', $key)->first();

        if (!$setting) {
            return false;
        }

        // Convert value to string for storage
        $stringValue = self::valueToString($value, $setting->type);

        $setting->update(['value' => $stringValue]);

        self::clearCache();

        return true;
    }

    /**
     * Set multiple settings at once.
     */
    public static function setValues(array $values): int
    {
        $updated = 0;

        foreach ($values as $key => $value) {
            if (self::setValue($key, $value)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Get all settings cached.
     */
    public static function getAllCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::all()->keyBy('key')->map(function ($setting) {
                return [
                    'value' => $setting->value,
                    'type' => $setting->type,
                    'group' => $setting->group,
                    'description' => $setting->description,
                ];
            })->toArray();
        });
    }

    /**
     * Clear the settings cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Cast a value based on its type.
     */
    private static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            'time', 'string' => $value,
            default => $value,
        };
    }

    /**
     * Convert a value to string for storage.
     */
    private static function valueToString(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Get work hours settings for attendance calculations.
     */
    public static function getWorkHoursConfig(): array
    {
        return [
            'work_start_time' => self::getValue('work_start_time', '09:00'),
            'work_end_time' => self::getValue('work_end_time', '18:00'),
            'break_duration_minutes' => self::getValue('break_duration_minutes', 60),
            'regular_work_minutes' => self::getValue('regular_work_minutes', 480),
            'late_threshold_minutes' => self::getValue('late_threshold_minutes', 15),
            'early_departure_threshold_minutes' => self::getValue('early_departure_threshold_minutes', 15),
            'overtime_threshold_minutes' => self::getValue('overtime_threshold_minutes', 30),
            'duplicate_scan_window_minutes' => self::getValue('duplicate_scan_window_minutes', 5),
        ];
    }

    /**
     * Get working days configuration.
     */
    public static function getWorkingDays(): array
    {
        return self::getValue('working_days', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
    }

    /**
     * Check if a day is a working day.
     */
    public static function isWorkingDay(string $dayName): bool
    {
        return in_array(strtolower($dayName), self::getWorkingDays());
    }

    /**
     * Get shift configuration.
     */
    public static function getShifts(): array
    {
        if (!self::getValue('shifts_enabled', false)) {
            return [];
        }

        return self::getValue('shifts', []);
    }

    /**
     * Get a specific shift by code.
     */
    public static function getShift(string $code): ?array
    {
        $shifts = self::getShifts();

        foreach ($shifts as $shift) {
            if ($shift['code'] === $code) {
                return $shift;
            }
        }

        return null;
    }

    /**
     * Get the current attendance mode.
     * Returns 'representative' or 'kiosk'.
     */
    public static function getAttendanceMode(): string
    {
        return self::getValue('attendance_mode', 'representative');
    }

    /**
     * Check if the system is in representative mode.
     */
    public static function isRepresentativeMode(): bool
    {
        return self::getAttendanceMode() === 'representative';
    }

    /**
     * Check if the system is in kiosk mode.
     */
    public static function isKioskMode(): bool
    {
        return self::getAttendanceMode() === 'kiosk';
    }
}
