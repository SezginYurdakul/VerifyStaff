<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($userId)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'role' => ['sometimes', Rule::in(['admin', 'representative', 'worker'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }
}
