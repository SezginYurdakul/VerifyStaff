<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'shifts' => ['required', 'array', 'min:1'],
            'shifts.*.name' => ['required', 'string', 'max:50'],
            'shifts.*.code' => ['required', 'string', 'max:20', 'alpha_dash'],
            'shifts.*.start_time' => ['required', 'date_format:H:i'],
            'shifts.*.end_time' => ['required', 'date_format:H:i'],
            'shifts.*.break_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'shifts.required' => 'Shifts array is required.',
            'shifts.array' => 'Shifts must be an array.',
            'shifts.min' => 'At least one shift is required.',
            'shifts.*.name.required' => 'Each shift must have a name.',
            'shifts.*.name.max' => 'Shift name cannot exceed 50 characters.',
            'shifts.*.code.required' => 'Each shift must have a code.',
            'shifts.*.code.max' => 'Shift code cannot exceed 20 characters.',
            'shifts.*.code.alpha_dash' => 'Shift code may only contain letters, numbers, dashes, and underscores.',
            'shifts.*.start_time.required' => 'Each shift must have a start time.',
            'shifts.*.start_time.date_format' => 'Start time must be in HH:MM format.',
            'shifts.*.end_time.required' => 'Each shift must have an end time.',
            'shifts.*.end_time.date_format' => 'End time must be in HH:MM format.',
            'shifts.*.break_minutes.required' => 'Each shift must have break minutes.',
            'shifts.*.break_minutes.integer' => 'Break minutes must be an integer.',
            'shifts.*.break_minutes.min' => 'Break minutes cannot be negative.',
            'shifts.*.break_minutes.max' => 'Break minutes cannot exceed 120.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $shifts = $this->input('shifts', []);
            $codes = array_column($shifts, 'code');

            if (count($codes) !== count(array_unique($codes))) {
                $validator->errors()->add('shifts', 'Duplicate shift codes found.');
            }
        });
    }
}
