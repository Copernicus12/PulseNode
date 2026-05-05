<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session as SessionContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SingleDeviceSessionManager
{
    public const SESSION_KEY = 'single_device_session_token';

    public const ATTRIBUTE = 'single_device_session_token';

    public function activate(Model $user, SessionContract $session): void
    {
        $token = Str::random(64);

        $session->put(self::SESSION_KEY, $token);

        $user->forceFill([
            self::ATTRIBUTE => $token,
        ])->saveQuietly();
    }

    public function isCurrent(Model $user, SessionContract $session): bool
    {
        $storedToken = $user->getAttribute(self::ATTRIBUTE);
        $sessionToken = $session->get(self::SESSION_KEY);

        if (! is_string($storedToken) || $storedToken === '') {
            return true;
        }

        if (! is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($storedToken, $sessionToken);
    }

    public function syncIfMissing(Model $user, SessionContract $session): void
    {
        $storedToken = $user->getAttribute(self::ATTRIBUTE);

        if (is_string($storedToken) && $storedToken !== '') {
            return;
        }

        $this->activate($user, $session);
    }

    public function clearIfCurrent(Model $user, SessionContract $session): void
    {
        $storedToken = $user->getAttribute(self::ATTRIBUTE);
        $sessionToken = $session->get(self::SESSION_KEY);

        if (! is_string($storedToken) || $storedToken === '' || ! is_string($sessionToken) || $sessionToken === '') {
            return;
        }

        if (! hash_equals($storedToken, $sessionToken)) {
            return;
        }

        $user->forceFill([
            self::ATTRIBUTE => null,
        ])->saveQuietly();
    }
}
