<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\NotificationCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input, ?NotificationCenter $notifications = null): Model
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $authModel */
        $authModel = config('auth.providers.users.model', \App\Models\User::class);
        $user = $authModel::query()->create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => User::ROLE_MODERATOR,
            'guest_expires_at' => null,
            'is_blocked' => false,
            'blocked_at' => null,
            'account_status' => User::ACCOUNT_STATUS_PENDING,
            'requested_at' => now(),
            'approved_at' => null,
            'rejected_at' => null,
        ]);

        $notifications?->accountRequestSubmitted(
            (string) $user->name,
            (string) $user->email,
            (string) $user->getKey(),
        );

        return $user;
    }
}
