<?php

namespace App\Http\Middleware;

use App\Support\SingleDeviceSessionManager;
use Closure;
use Illuminate\Contracts\Session\Session as SessionContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleDeviceSession
{
    public function __construct(private readonly SingleDeviceSessionManager $singleDeviceSessionManager) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user === null || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();

        if ($this->singleDeviceSessionManager->isCurrent($user, $session)) {
            if ($user->getAttribute(SingleDeviceSessionManager::ATTRIBUTE) === null) {
                $this->singleDeviceSessionManager->syncIfMissing($user, $session);
            }

            return $next($request);
        }

        $this->logoutCurrentDevice($session);
        $session->put([
            'status' => 'Your account was signed in on another device. Please sign in again.',
            'session_replaced' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'session_replaced',
                'message' => 'Your account was signed in on another device. Please sign in again.',
            ], 401);
        }

        return redirect()->route('login');
    }

    private function logoutCurrentDevice(SessionContract $session): void
    {
        auth()->guard('web')->logoutCurrentDevice();
        $session->invalidate();
        $session->regenerateToken();
    }
}
