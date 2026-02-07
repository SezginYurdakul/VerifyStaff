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
            'role' => $this->role,
            'status' => $this->status,
        ];

        // Include department if loaded
        if ($this->relationLoaded('department') && $this->department) {
            $data['department'] = [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'code' => $this->department->code,
            ];
        }

        // Include additional fields for admin users viewing other users
        if ($request->user()?->isAdmin() && $request->user()->id !== $this->id) {
            $data['created_at'] = $this->created_at?->toIso8601String();

            // Include invite_token for pending invites
            if ($this->invite_accepted_at === null && $this->invite_token) {
                $data['invite_token'] = $this->invite_token;
                $data['invite_pending'] = true;
            }
        }

        return $data;
    }
}
