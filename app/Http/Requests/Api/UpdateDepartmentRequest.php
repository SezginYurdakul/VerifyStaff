<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $departmentId = $this->route('id');

        return [
            'name' => 'string|max:255',
            'code' => ['string', 'max:50', Rule::unique('departments', 'code')->ignore($departmentId)],
            'shift_start' => 'date_format:H:i',
            'shift_end' => 'date_format:H:i',
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
