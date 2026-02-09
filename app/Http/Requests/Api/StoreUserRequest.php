<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'role' => ['required', Rule::in(['admin', 'representative', 'worker'])],
        ];
    }
}
