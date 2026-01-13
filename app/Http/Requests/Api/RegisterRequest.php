<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'in:admin,representative,worker'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->email && !$this->phone && !$this->employee_id) {
                $validator->errors()->add('identifier', 'At least one of email, phone, or employee_id is required.');
            }
        });
    }
}
