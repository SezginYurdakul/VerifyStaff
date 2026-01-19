<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'attendance_mode' => ['required', 'string', 'in:representative,kiosk'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendance_mode.required' => 'Attendance mode is required.',
            'attendance_mode.in' => 'Attendance mode must be either "representative" or "kiosk".',
        ];
    }
}
