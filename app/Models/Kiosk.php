<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Kiosk extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'secret_token',
        'location',
        'latitude',
        'longitude',
        'status',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'last_heartbeat_at' => 'datetime',
    ];

    protected $hidden = [
        'secret_token',
    ];

    /**
     * Generate a unique secret token for TOTP.
     */
    public static function generateSecretToken(): string
    {
        return Str::random(64);
    }

    /**
     * Generate a unique kiosk code.
     */
    public static function generateCode(): string
    {
        $lastKiosk = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastKiosk ? ((int) substr($lastKiosk->code, -3)) + 1 : 1;

        return 'KIOSK-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Check if kiosk is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Update heartbeat timestamp.
     */
    public function heartbeat(): void
    {
        $this->update(['last_heartbeat_at' => now()]);
    }

    /**
     * Get attendance logs recorded from this kiosk.
     */
    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class, 'kiosk_id', 'code');
    }
}
