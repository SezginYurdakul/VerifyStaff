<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $key,
        public mixed $oldValue,
        public mixed $newValue,
        public User $changedBy
    ) {}
}
