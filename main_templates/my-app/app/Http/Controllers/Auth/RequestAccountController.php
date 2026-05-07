<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Support\NotificationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RequestAccountController
{
    public function store(
        Request $request,
        CreateNewUser $creator,
        NotificationCenter $notifications,
    ): RedirectResponse {
        if ($request->user() !== null) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'You are already signed in.');
        }

        $creator->create($request->all(), $notifications);

        return redirect()
            ->route('login')
            ->with('status', 'Your account request was sent. An administrator will review it shortly.');
    }
}
