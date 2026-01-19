<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'value' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Setting value is required.',
        ];
    }
}
