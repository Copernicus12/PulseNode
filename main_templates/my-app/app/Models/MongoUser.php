<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use MongoDB\Laravel\Auth\User as Authenticatable;

class MongoUser extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_GUEST = 'guest';

    protected $connection = 'mongodb';

    protected $collection = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'guest_expires_at',
        'is_blocked',
        'blocked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'guest_expires_at' => 'datetime',
            'blocked_at' => 'datetime',
            'is_blocked' => 'boolean',
        ];
    }

    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_MODERATOR,
            self::ROLE_GUEST,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isModerator(): bool
    {
        return $this->role === self::ROLE_MODERATOR;
    }

    public function isGuest(): bool
    {
        return $this->role === self::ROLE_GUEST;
    }

    public function hasExpiredGuestAccess(): bool
    {
        return $this->isGuest()
            && $this->guest_expires_at !== null
            && $this->guest_expires_at->isPast();
    }

    public function hasActiveGuestWindow(): bool
    {
        return $this->isGuest()
            && $this->guest_expires_at !== null
            && $this->guest_expires_at->isFuture();
    }

    public function isAccessBlocked(): bool
    {
        return (bool) $this->is_blocked || $this->hasExpiredGuestAccess();
    }
}
