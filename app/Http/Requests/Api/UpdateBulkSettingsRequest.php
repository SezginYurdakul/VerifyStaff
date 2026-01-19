<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBulkSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.required' => 'Settings array is required.',
            'settings.array' => 'Settings must be an array.',
            'settings.min' => 'At least one setting is required.',
            'settings.*.key.required' => 'Each setting must have a key.',
            'settings.*.value.required' => 'Each setting must have a value.',
        ];
    }
}
