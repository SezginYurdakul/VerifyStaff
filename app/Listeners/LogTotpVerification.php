<?php

namespace App\Listeners;

use App\Events\TotpVerified;
use App\Services\AuditLogger;

class LogTotpVerification
{
    public function handle(TotpVerified $event): void
    {
        AuditLogger::totpVerification(
            workerId: $event->worker->id,
            success: $event->success,
            verifiedBy: $event->verifiedBy?->id
        );

        // Log security event for failed attempts
        if (!$event->success) {
            AuditLogger::security(
                event: 'totp_verification_failed',
                data: [
                    'worker_id' => $event->worker->id,
                    'worker_name' => $event->worker->name,
                ],
                userId: $event->verifiedBy?->id
            );
        }
    }
}
