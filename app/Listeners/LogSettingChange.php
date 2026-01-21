<?php

namespace App\Listeners;

use App\Events\SettingChanged;
use App\Services\AuditLogger;

class LogSettingChange
{
    public function handle(SettingChanged $event): void
    {
        AuditLogger::settingsChange(
            key: $event->key,
            oldValue: $event->oldValue,
            newValue: $event->newValue,
            changedBy: $event->changedBy->id
        );
    }
}
