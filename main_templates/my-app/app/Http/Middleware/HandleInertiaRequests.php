<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $accountsSummary = null;

        if ($user !== null && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            $authModel = get_class($user);
            $accountsSummary = [
                'total' => $authModel::query()->count(),
                'blocked' => $authModel::query()->where('is_blocked', true)->count(),
                'active_guests' => $authModel::query()
                    ->where('role', $authModel::ROLE_GUEST)
                    ->where('is_blocked', false)
                    ->whereNotNull('guest_expires_at')
                    ->where('guest_expires_at', '>', now())
                    ->count(),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'accountsSummary' => $accountsSummary,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
