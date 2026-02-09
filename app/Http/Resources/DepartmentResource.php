<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'shift_start' => $this->shift_start,
            'shift_end' => $this->shift_end,
            'late_threshold_minutes' => $this->late_threshold_minutes,
            'early_departure_threshold_minutes' => $this->early_departure_threshold_minutes,
            'regular_work_minutes' => $this->regular_work_minutes,
            'working_days' => $this->working_days,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'workers_count' => $this->whenCounted('workers'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
