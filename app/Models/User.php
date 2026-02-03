<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'employee_id',
        'password',
        'role',
        'secret_token',
        'status',
        'invite_token',
        'invite_expires_at',
        'invite_accepted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'secret_token',
        'invite_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'invite_expires_at' => 'datetime',
            'invite_accepted_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRepresentative(): bool
    {
        return $this->role === 'representative';
    }

    public function isWorker(): bool
    {
        return $this->role === 'worker';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function attendanceLogsAsWorker(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'worker_id');
    }

    public function attendanceLogsAsRep(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'rep_id');
    }

    public static function generateSecretToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function generateInviteToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hasValidInvite(): bool
    {
        return $this->invite_token !== null
            && $this->invite_expires_at !== null
            && $this->invite_expires_at->isFuture()
            && $this->invite_accepted_at === null;
    }

    public function hasPendingInvite(): bool
    {
        return $this->password === null && $this->invite_token !== null;
    }
}
