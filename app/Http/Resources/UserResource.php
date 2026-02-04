<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'employee_id' => $this->employee_id,
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'code' => $this->department->code,
                ];
            }),
            'role' => $this->role,
            'status' => $this->status,
            'invite_accepted_at' => $this->invite_accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];

        // Include invite_token for admin users when user has pending invite
        if ($request->user()?->isAdmin() && $this->invite_accepted_at === null && $this->invite_token) {
            $data['invite_token'] = $this->invite_token;
        }

        return $data;
    }
}
