<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SelfCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_time' => ['required', 'date'],
            'device_timezone' => ['sometimes', 'string', 'max:50'],
            'kiosk_code' => ['required', 'string', 'max:20'],
            'kiosk_totp' => ['required', 'string', 'size:6'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_time.required' => 'Device time is required.',
            'device_time.date' => 'Device time must be a valid date.',
            'kiosk_code.required' => 'Kiosk code is required.',
            'kiosk_totp.required' => 'Kiosk TOTP code is required.',
            'kiosk_totp.size' => 'Kiosk TOTP code must be 6 digits.',
        ];
    }
}
