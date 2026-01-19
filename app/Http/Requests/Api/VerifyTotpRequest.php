<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTotpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->isRepresentative() || $user->isAdmin());
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'integer', 'exists:users,id'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'worker_id.required' => 'Worker ID is required.',
            'worker_id.integer' => 'Worker ID must be an integer.',
            'worker_id.exists' => 'Worker not found.',
            'code.required' => 'TOTP code is required.',
            'code.size' => 'TOTP code must be exactly 6 characters.',
        ];
    }
}
