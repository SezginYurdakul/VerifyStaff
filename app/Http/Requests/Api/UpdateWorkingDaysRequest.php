<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkingDaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
        ];
    }

    public function messages(): array
    {
        return [
            'working_days.required' => 'Working days array is required.',
            'working_days.array' => 'Working days must be an array.',
            'working_days.min' => 'At least one working day is required.',
            'working_days.*.in' => 'Invalid day name. Must be one of: monday, tuesday, wednesday, thursday, friday, saturday, sunday.',
        ];
    }
}
