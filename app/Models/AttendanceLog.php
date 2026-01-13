<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'worker_id',
        'rep_id',
        'type',
        'device_time',
        'device_timezone',
        'sync_time',
        'sync_attempt',
        'offline_duration_seconds',
        'sync_status',
        'flagged',
        'flag_reason',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'device_time' => 'datetime',
            'sync_time' => 'datetime',
            'flagged' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rep_id');
    }

    public static function generateEventId(int $workerId, int $repId, string $deviceTime, string $type): string
    {
        return hash('sha256', $workerId . $repId . $deviceTime . $type);
    }

    public function flag(string $reason): void
    {
        $this->update([
            'flagged' => true,
            'flag_reason' => $reason,
        ]);
    }
}
