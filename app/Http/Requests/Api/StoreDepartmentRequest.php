<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code',
            'shift_start' => 'required|date_format:H:i',
            'shift_end' => 'required|date_format:H:i',
            'late_threshold_minutes' => 'integer|min:0|max:120',
            'early_departure_threshold_minutes' => 'integer|min:0|max:120',
            'regular_work_minutes' => 'integer|min:60|max:1440',
            'working_days' => 'nullable|array',
            'working_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }
}
