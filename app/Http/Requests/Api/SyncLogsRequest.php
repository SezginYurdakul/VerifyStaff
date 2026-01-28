<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logs' => ['required', 'array', 'min:1'],
            'logs.*.worker_id' => ['required', 'integer'],
            'logs.*.type' => ['sometimes', 'nullable', 'in:in,out'],  // Optional - auto-detected if not provided
            'logs.*.device_time' => ['required', 'date'],
            'logs.*.device_timezone' => ['sometimes', 'string', 'max:50'],
            'logs.*.sync_attempt' => ['sometimes', 'integer', 'min:1'],
            'logs.*.offline_duration_seconds' => ['sometimes', 'integer', 'min:0'],
            'logs.*.latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'logs.*.longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'logs.*.scanned_totp' => ['sometimes', 'nullable', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'logs.required' => 'At least one attendance log is required.',
            'logs.*.worker_id.required' => 'Worker ID is required for each log.',
            'logs.*.type.in' => 'Attendance type must be either "in" or "out" (or omit for auto-detection).',
            'logs.*.device_time.required' => 'Device time is required for each log.',
        ];
    }
}
