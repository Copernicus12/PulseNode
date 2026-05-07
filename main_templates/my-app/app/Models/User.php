<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_GUEST = 'guest';

    public const ACCOUNT_STATUS_ACTIVE = 'active';

    public const ACCOUNT_STATUS_PENDING = 'pending';

    public const ACCOUNT_STATUS_REJECTED = 'rejected';

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
        'account_status',
        'requested_at',
        'approved_at',
        'rejected_at',
        'dashboard_tour_completed_at',
        'electricity_price_per_wh',
        'billing_currency',
        'billing_tax_percent',
        'billing_price_includes_tax',
        'billing_monthly_base_fee',
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
        'single_device_session_token',
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
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'dashboard_tour_completed_at' => 'datetime',
            'is_blocked' => 'boolean',
            'electricity_price_per_wh' => 'decimal:6',
            'billing_tax_percent' => 'decimal:2',
            'billing_price_includes_tax' => 'boolean',
            'billing_monthly_base_fee' => 'decimal:2',
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

    public function isPendingApproval(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_PENDING;
    }

    public function isRejectedRequest(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_REJECTED;
    }

    public function isActiveAccount(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_ACTIVE;
    }

    public function hasCompletedDashboardTour(): bool
    {
        return $this->dashboard_tour_completed_at !== null;
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
        return (bool) $this->is_blocked
            || $this->hasExpiredGuestAccess()
            || $this->isPendingApproval()
            || $this->isRejectedRequest();
    }
}
