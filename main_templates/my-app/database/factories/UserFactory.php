<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_MODERATOR,
            'guest_expires_at' => null,
            'is_blocked' => false,
            'blocked_at' => null,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            'requested_at' => null,
            'approved_at' => now(),
            'rejected_at' => null,
            'electricity_price_per_wh' => 0.0008,
            'billing_currency' => 'RON',
            'billing_tax_percent' => 21,
            'billing_monthly_base_fee' => 0,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
            'guest_expires_at' => null,
            'is_blocked' => false,
            'blocked_at' => null,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            'requested_at' => null,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);
    }

    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_MODERATOR,
            'guest_expires_at' => null,
            'is_blocked' => false,
            'blocked_at' => null,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            'requested_at' => null,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);
    }

    public function guest(?\DateTimeInterface $expiresAt = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_GUEST,
            'guest_expires_at' => $expiresAt ?? now()->addHour(),
            'is_blocked' => false,
            'blocked_at' => null,
            'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            'requested_at' => null,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blocked' => true,
            'blocked_at' => now(),
        ]);
    }

    public function pendingRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => User::ACCOUNT_STATUS_PENDING,
            'requested_at' => now(),
            'approved_at' => null,
            'rejected_at' => null,
            'is_blocked' => false,
            'blocked_at' => null,
        ]);
    }
}
