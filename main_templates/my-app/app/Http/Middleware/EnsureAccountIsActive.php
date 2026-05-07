<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ($user->hasExpiredGuestAccess() && ! $user->is_blocked) {
            $user->forceFill([
                'is_blocked' => true,
                'blocked_at' => now(),
            ])->save();

            $user->refresh();
        }

        if (method_exists($user, 'isPendingApproval') && $user->isPendingApproval()) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Your account request is waiting for admin approval.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'pending',
                    'message' => $message,
                ], 423);
            }

            return redirect()->route('login')->with('status', $message);
        }

        if (method_exists($user, 'isRejectedRequest') && $user->isRejectedRequest()) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Your account request was declined. Please contact an administrator or submit a new request.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'rejected',
                    'message' => $message,
                ], 423);
            }

            return redirect()->route('login')->with('status', $message);
        }

        if (! $user->isAccessBlocked()) {
            return $next($request);
        }

        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $user->hasExpiredGuestAccess()
            ? 'Guest access expired. Contact an administrator to extend or reactivate the account.'
            : 'This account is currently blocked. Contact an administrator.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'blocked',
                'message' => $message,
            ], 423);
        }

        return redirect()->route('login')->with('status', $message);
    }
}
