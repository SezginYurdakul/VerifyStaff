<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncOfflineLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logs' => ['required', 'array', 'min:1', 'max:500'],
            'logs.*.kiosk_code' => ['required', 'string', 'max:20'],
            'logs.*.device_time' => ['required', 'date'],
            'logs.*.device_timezone' => ['sometimes', 'string', 'max:50'],
            'logs.*.event_id' => ['required', 'string', 'max:64'],
            'logs.*.scanned_totp' => ['sometimes', 'nullable', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'logs.required' => 'At least one offline log is required.',
            'logs.*.kiosk_code.required' => 'Kiosk code is required for each log.',
            'logs.*.device_time.required' => 'Device time is required for each log.',
            'logs.*.event_id.required' => 'Event ID is required for each log.',
        ];
    }
}
