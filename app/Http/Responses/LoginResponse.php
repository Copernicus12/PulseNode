<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Response
    {
        // Login form is submitted through Inertia (XHR).
        // Since /dashboard is a Blade page (not Inertia), force a hard visit.
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }
}
