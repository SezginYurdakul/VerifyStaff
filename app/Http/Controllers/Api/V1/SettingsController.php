<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAttendanceModeRequest;
use App\Http\Requests\Api\UpdateBulkSettingsRequest;
use App\Http\Requests\Api\UpdateSettingRequest;
use App\Http\Requests\Api\UpdateShiftsRequest;
use App\Http\Requests\Api\UpdateWorkingDaysRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get all settings grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $settings = Setting::all()->groupBy('group')->map(function ($group) {
            return $group->map(function ($setting) {
                return [
                    'key' => $setting->key,
                    'value' => $this->castValue($setting->value, $setting->type),
                    'type' => $setting->type,
                    'description' => $setting->description,
                ];
            })->keyBy('key');
        });

        return response()->json([
            'settings' => $settings,
        ]);
    }

    /**
     * Get settings for a specific group.
     */
    public function group(Request $request, string $group): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $validGroups = ['general', 'work_hours', 'attendance', 'schedule', 'shifts'];

        if (!in_array($group, $validGroups)) {
            return response()->json([
                'message' => 'Invalid settings group',
                'valid_groups' => $validGroups,
            ], 400);
        }

        $settings = Setting::where('group', $group)->get()->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $this->castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'description' => $setting->description,
            ];
        })->keyBy('key');

        return response()->json([
            'group' => $group,
            'settings' => $settings,
        ]);
    }

    /**
     * Get a single setting by key.
     */
    public function show(Request $request, string $key): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        return response()->json([
            'key' => $setting->key,
            'group' => $setting->group,
            'value' => $this->castValue($setting->value, $setting->type),
            'type' => $setting->type,
            'description' => $setting->description,
        ]);
    }

    /**
     * Update a single setting.
     */
    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        $value = $request->validated('value');
        $validationError = $this->validateValueType($value, $setting->type, $key);

        if ($validationError) {
            return response()->json([
                'message' => 'Invalid value',
                'error' => $validationError,
            ], 422);
        }

        $stringValue = $this->valueToString($value, $setting->type);
        $setting->update(['value' => $stringValue]);

        Setting::clearCache();

        return response()->json([
            'message' => 'Setting updated successfully',
            'key' => $setting->key,
            'value' => $this->castValue($setting->value, $setting->type),
        ]);
    }

    /**
     * Update multiple settings at once.
     */
    public function updateBulk(UpdateBulkSettingsRequest $request): JsonResponse
    {
        $updated = [];
        $errors = [];

        foreach ($request->validated('settings') as $item) {
            $setting = Setting::where('key', $item['key'])->first();

            if (!$setting) {
                $errors[] = [
                    'key' => $item['key'],
                    'error' => 'Setting not found',
                ];
                continue;
            }

            $validationError = $this->validateValueType($item['value'], $setting->type, $item['key']);

            if ($validationError) {
                $errors[] = [
                    'key' => $item['key'],
                    'error' => $validationError,
                ];
                continue;
            }

            $stringValue = $this->valueToString($item['value'], $setting->type);
            $setting->update(['value' => $stringValue]);

            $updated[] = [
                'key' => $setting->key,
                'value' => $this->castValue($setting->value, $setting->type),
            ];
        }

        Setting::clearCache();

        return response()->json([
            'message' => 'Settings updated',
            'updated_count' => count($updated),
            'error_count' => count($errors),
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    /**
     * Get work hours configuration (for representatives).
     */
    public function workHours(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isRepresentative()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'config' => Setting::getWorkHoursConfig(),
            'working_days' => Setting::getWorkingDays(),
            'shifts_enabled' => Setting::getValue('shifts_enabled', false),
            'shifts' => Setting::getShifts(),
            'default_shift' => Setting::getValue('default_shift', 'day'),
        ]);
    }

    /**
     * Update shift definitions.
     */
    public function updateShifts(UpdateShiftsRequest $request): JsonResponse
    {
        Setting::setValue('shifts', $request->validated('shifts'));

        return response()->json([
            'message' => 'Shifts updated successfully',
            'shifts' => Setting::getValue('shifts'),
        ]);
    }

    /**
     * Update working days.
     */
    public function updateWorkingDays(UpdateWorkingDaysRequest $request): JsonResponse
    {
        $workingDays = $request->validated('working_days');
        $allDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $weekendDays = array_values(array_diff($allDays, $workingDays));

        Setting::setValue('working_days', $workingDays);
        Setting::setValue('weekend_days', $weekendDays);

        return response()->json([
            'message' => 'Working days updated successfully',
            'working_days' => Setting::getValue('working_days'),
            'weekend_days' => Setting::getValue('weekend_days'),
        ]);
    }

    /**
     * Get attendance mode.
     */
    public function attendanceMode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isRepresentative()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'attendance_mode' => Setting::getAttendanceMode(),
            'description' => Setting::getAttendanceMode() === 'representative'
                ? 'Worker shows QR code, representative scans to record attendance'
                : 'Kiosk displays QR code, worker scans to record their own attendance',
        ]);
    }

    /**
     * Update attendance mode.
     */
    public function updateAttendanceMode(UpdateAttendanceModeRequest $request): JsonResponse
    {
        Setting::setValue('attendance_mode', $request->validated('attendance_mode'));

        return response()->json([
            'message' => 'Attendance mode updated successfully',
            'attendance_mode' => Setting::getAttendanceMode(),
            'description' => Setting::getAttendanceMode() === 'representative'
                ? 'Worker shows QR code, representative scans to record attendance'
                : 'Kiosk displays QR code, worker scans to record their own attendance',
        ]);
    }

    private function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    private function valueToString(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }

    private function validateValueType(mixed $value, string $type, string $key): ?string
    {
        return match ($type) {
            'integer' => is_numeric($value) ? null : "Value must be an integer for '{$key}'",
            'boolean' => is_bool($value) || in_array($value, ['true', 'false', '1', '0', 1, 0], true)
                ? null
                : "Value must be a boolean for '{$key}'",
            'time' => preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)
                ? null
                : "Value must be a valid time (HH:MM) for '{$key}'",
            'json' => (is_array($value) || (is_string($value) && json_decode($value) !== null))
                ? null
                : "Value must be valid JSON for '{$key}'",
            default => null,
        };
    }
}
