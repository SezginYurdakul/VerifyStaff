<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'worker_id' => $this->worker_id,
            'worker_name' => $this->whenLoaded('worker', fn() => $this->worker->name),
            'rep_id' => $this->rep_id,
            'rep_name' => $this->whenLoaded('representative', fn() => $this->representative->name),
            'type' => $this->type,
            'device_time' => $this->device_time?->toIso8601String(),
            'device_timezone' => $this->device_timezone,
            'sync_time' => $this->sync_time?->toIso8601String(),
            'sync_status' => $this->sync_status,
            'flagged' => $this->flagged,
            'flag_reason' => $this->flag_reason,
            'location' => $this->when($this->latitude && $this->longitude, [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
