<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TotpVerified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $worker,
        public bool $success,
        public ?User $verifiedBy = null
    ) {}
}
