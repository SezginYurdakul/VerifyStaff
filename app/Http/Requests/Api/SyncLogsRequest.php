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
            'logs.*.type' => ['required', 'in:in,out'],
            'logs.*.device_time' => ['required', 'date'],
            'logs.*.device_timezone' => ['sometimes', 'string', 'max:50'],
            'logs.*.sync_attempt' => ['sometimes', 'integer', 'min:1'],
            'logs.*.offline_duration_seconds' => ['sometimes', 'integer', 'min:0'],
            'logs.*.latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'logs.*.longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'logs.required' => 'At least one attendance log is required.',
            'logs.*.worker_id.required' => 'Worker ID is required for each log.',
            'logs.*.type.required' => 'Attendance type (in/out) is required.',
            'logs.*.type.in' => 'Attendance type must be either "in" or "out".',
            'logs.*.device_time.required' => 'Device time is required for each log.',
        ];
    }
}
