<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['dark' => ($appearance ?? 'system') === 'dark'])
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            (function() {
                let appearance = '{{ $appearance ?? "system" }}';
                try {
                    const storedAppearance = window.localStorage.getItem('appearance');
                    if (storedAppearance === 'light' || storedAppearance === 'dark' || storedAppearance === 'system') {
                        appearance = storedAppearance;
                    }
                } catch (error) {
                    // Ignore storage access issues and keep the server fallback.
                }

                const resolvedAppearance = appearance === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : appearance;

                document.documentElement.classList.toggle('dark', resolvedAppearance === 'dark');
                document.documentElement.style.colorScheme = resolvedAppearance;
                document.documentElement.dataset.appearance = appearance;
                document.documentElement.dataset.resolvedAppearance = resolvedAppearance;
            })();
        </script>
        <style>
            html {
                background-color: oklch(1 0 0);
                color-scheme: light;
            }

            html.dark {
                background-color: oklch(0.145 0 0);
                color-scheme: dark;
            }
        </style>

        <title>{{ trim($__env->yieldContent('title', 'PulseNode')) }} — {{ config('app.name', 'PulseNode') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css'])
        @stack('head')
    </head>
    <body class="font-sans antialiased">
        @php
            $settingsNavOpen = request()->routeIs(
                'settings.index',
                'appearance.*',
                'electricity-billing.*',
                'power-strip-diagnostics.*',
                'profile.*',
                'user-password.*',
                'two-factor.*',
            );
            $billingSettingsActive = request()->routeIs('settings.index', 'electricity-billing.edit');
            $appearanceSettingsActive = request()->routeIs('appearance.*');
            $powerStripDiagnosticsActive = request()->routeIs('power-strip-diagnostics.*');
        @endphp
        <div class="min-h-screen bg-background text-foreground">

            {{-- Mobile sheet --}}
            <input id="mobile-sidebar" type="checkbox" class="peer/mobile hidden" />
            <label for="mobile-sidebar"
                   class="fixed inset-0 z-40 hidden bg-black/60 backdrop-blur-sm peer-checked/mobile:block lg:hidden"></label>

            {{-- Mobile sidebar --}}
            <aside class="fixed inset-y-0 left-0 z-50 flex w-[270px] -translate-x-full flex-col p-3 transition-transform duration-300 peer-checked/mobile:translate-x-0 lg:hidden">
                <div class="light-outline-strong flex flex-1 flex-col rounded-3xl bg-card p-4">
                    <div class="flex items-center justify-between px-2 pb-6 pt-2">
                        <span class="text-lg font-bold tracking-tight">PulseNode</span>
                        <label for="mobile-sidebar" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-muted-foreground hover:text-foreground">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </label>
                    </div>
                    <nav class="flex-1 space-y-1.5 overflow-y-auto lg:overflow-visible">
                        @include('layouts._sidebar-links')
                    </nav>
                    <div class="mt-4 space-y-1.5 border-t border-border/20 pt-4">
                        <details @if($settingsNavOpen) open @endif class="group rounded-2xl {{ $settingsNavOpen ? 'bg-primary/8 ring-1 ring-primary/20' : 'light-outline-soft' }}">
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-2xl px-4 py-3 text-sm transition [&::-webkit-details-marker]:hidden {{ $settingsNavOpen ? 'font-semibold text-primary' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                                <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span class="flex-1">Settings</span>
                                <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </summary>
                            <div class="space-y-1 px-2 pb-2">
                                <a href="{{ route('electricity-billing.edit') }}"
                                              data-tour="settings-billing-link"
                                   data-search-link="1"
                                   data-search-label="settings billing electricity bill"
                                   class="flex items-center rounded-xl px-3 py-2 text-sm transition {{ $billingSettingsActive ? 'bg-primary font-medium text-primary-foreground' : 'light-outline-soft text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                                    Billing settings
                                </a>
                                <a href="{{ route('power-strip-diagnostics.edit') }}"
                                              data-tour="settings-diagnostics-link"
                                   data-search-link="1"
                                   data-search-label="settings hardware diagnostics power strip payload mqtt esp32"
                                   class="flex items-center rounded-xl px-3 py-2 text-sm transition {{ $powerStripDiagnosticsActive ? 'bg-primary font-medium text-primary-foreground' : 'light-outline-soft text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                                    Hardware & diagnostics
                                </a>
                                <a href="{{ route('appearance.edit') }}"
                                              data-tour="settings-appearance-link"
                                   data-search-link="1"
                                   data-search-label="settings appearance theme dark light"
                                   class="flex items-center rounded-xl px-3 py-2 text-sm transition {{ $appearanceSettingsActive ? 'bg-primary font-medium text-primary-foreground' : 'light-outline-soft text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                                    Appearance
                                </a>
                            </div>
                        </details>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-sm text-muted-foreground transition hover:bg-muted/50 hover:text-foreground">
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                Log Out
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="flex min-h-screen gap-3 p-3">

                {{-- Desktop sidebar — rounded card like Roomy --}}
                <aside class="hidden w-[240px] shrink-0 lg:flex">
                    <div class="light-outline-strong flex w-full flex-col rounded-3xl bg-card p-4">
                        <div class="px-3 pb-8 pt-3">
                            <span class="text-xl font-bold tracking-tight">PulseNode</span>
                        </div>
                        <nav class="flex-1 space-y-1.5 overflow-y-auto lg:overflow-visible">
                            @include('layouts._sidebar-links')
                        </nav>
                        <div class="mt-4 space-y-1.5 border-t border-border/20 pt-4">
                            <details @if($settingsNavOpen) open @endif class="group rounded-2xl {{ $settingsNavOpen ? 'bg-primary/8 ring-1 ring-primary/20' : 'light-outline-soft' }}">
                                <summary class="flex cursor-pointer list-none items-center gap-3 rounded-2xl px-4 py-3 text-sm transition [&::-webkit-details-marker]:hidden {{ $settingsNavOpen ? 'font-semibold text-primary' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                                    <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                    <span class="flex-1">Settings</span>
                                    <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                </summary>
                                <div class="space-y-1 px-2 pb-2">
                                    <a href="{{ route('electricity-billing.edit') }}"
                                                    data-tour="settings-billing-link"
                                       data-search-link="1"
                                       data-search-label="settings billing electricity bill"
                                       class="flex items-center rounded-xl px-3 py-2 text-sm transition {{ $billingSettingsActive ? 'bg-primary font-medium text-primary-foreground' : 'light-outline-soft text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                                        Billing settings
                                    </a>
                                    <a href="{{ route('power-strip-diagnostics.edit') }}"
                                                    data-tour="settings-diagnostics-link"
                                       data-search-link="1"
                                       data-search-label="settings hardware diagnostics power strip payload mqtt esp32"
                                       class="flex items-center rounded-xl px-3 py-2 text-sm transition {{ $powerStripDiagnosticsActive ? 'bg-primary font-medium text-primary-foreground' : 'light-outline-soft text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                                        Hardware & diagnostics
                                    </a>
                                    <a href="{{ route('appearance.edit') }}"
                                                    data-tour="settings-appearance-link"
                                       data-search-link="1"
                                       data-search-label="settings appearance theme dark light"
                                       class="flex items-center rounded-xl px-3 py-2 text-sm transition {{ $appearanceSettingsActive ? 'bg-primary font-medium text-primary-foreground' : 'light-outline-soft text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                                        Appearance
                                    </a>
                                </div>
                            </details>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-sm text-muted-foreground transition hover:bg-muted/50 hover:text-foreground">
                                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>
                </aside>

                {{-- Main content area --}}
                @php
                    $isDashboardRoute = request()->routeIs('dashboard');
                    $authUser = Auth::user();
                    $accountAdminSummary = null;

                    if ($authUser?->isAdmin()) {
                        $accountModel = get_class($authUser);
                        $accountAdminSummary = [
                            'total' => $accountModel::query()->count(),
                            'blocked' => $accountModel::query()->where('is_blocked', true)->count(),
                            'active_guests' => $accountModel::query()
                                ->where('role', $accountModel::ROLE_GUEST)
                                ->where('is_blocked', false)
                                ->whereNotNull('guest_expires_at')
                                ->where('guest_expires_at', '>', now())
                                ->count(),
                        ];
                    }
                @endphp
                <div class="flex min-h-0 flex-1 flex-col">
                    <header id="app-shell-header" class="grid h-16 shrink-0 grid-cols-[auto_1fr_auto] items-center gap-3 px-2 pb-2 lg:px-4">
                        <div class="flex items-center">
                            <label for="mobile-sidebar" class="inline-flex h-9 w-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:text-foreground lg:hidden">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                            </label>
                            <span class="hidden h-9 w-9 lg:inline-block" aria-hidden="true"></span>
                        </div>

                        <div class="hidden sm:flex justify-center">
                            <div class="relative w-full max-w-3xl">
                                <svg class="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input id="global-search-input" type="text" placeholder="Search pages (Dashboard, Power Strip, Settings...)" autocomplete="off" class="light-outline h-11 w-full rounded-2xl bg-card pl-10 pr-4 text-sm text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary/30" />
                                <span class="pointer-events-none absolute right-3.5 top-1/2 hidden -translate-y-1/2 rounded-lg bg-background px-2 py-1 text-[10px] font-semibold text-muted-foreground lg:inline-flex">Ctrl/⌘ K</span>

                                <div id="global-search-panel" class="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-50 hidden overflow-hidden rounded-2xl border border-primary/35 bg-card ring-1 ring-primary/25 shadow-2xl shadow-black/60 outline outline-1 outline-border/50">
                                    <div class="border-b border-border/20 px-3 py-2 text-[11px] text-muted-foreground">Command Palette</div>
                                    <div id="global-search-results" class="max-h-[22rem] overflow-y-auto p-1.5"></div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            @unless($isDashboardRoute)
                                <div id="live-telemetry-pill" class="light-outline-soft hidden items-center gap-2 rounded-2xl bg-card px-3 py-2 text-xs text-muted-foreground ring-1 ring-border/30 lg:inline-flex">
                                    <span id="live-telemetry-dot" class="h-2 w-2 rounded-full bg-red-400"></span>
                                    <span id="live-telemetry-power" class="font-semibold tabular-nums text-foreground">0.0W</span>
                                    <span id="live-telemetry-current" class="tabular-nums">0.000A</span>
                                </div>
                            @endunless
                            @if($accountAdminSummary)
                                <div id="app-accounts-root" class="relative">
                                    <button id="app-accounts-trigger" title="Accounts" aria-expanded="false" aria-controls="app-accounts-panel" class="light-outline-soft inline-flex h-9 w-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:text-foreground">
                                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                        <span id="app-accounts-badge" class="absolute -right-1 -top-1 min-w-[1.15rem] rounded-full bg-red-400 px-1.5 py-0.5 text-center text-[10px] font-bold leading-none text-background {{ $accountAdminSummary['blocked'] > 0 ? '' : 'hidden' }}">
                                            <span id="app-accounts-badge-value">
                                                {{ $accountAdminSummary['blocked'] }}
                                            </span>
                                        </span>
                                    </button>
                                    <div id="app-accounts-panel" class="light-outline-strong absolute right-0 top-[calc(100%+0.75rem)] z-[95] hidden w-[min(24rem,calc(100vw-1.5rem))] overflow-hidden rounded-3xl border border-border/50 bg-card shadow-2xl shadow-black/40 ring-1 ring-border/40">
                                        <div class="border-b border-border/30 px-4 py-3">
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold">Accounts</p>
                                                    <p class="text-[11px] text-muted-foreground">Manage roles, guest expiry, and blocked users.</p>
                                                </div>
                                                <span class="light-outline-soft inline-flex rounded-full bg-background px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground ring-1 ring-border/40">Admin</span>
                                            </div>
                                        </div>
                                        <div class="grid gap-2 p-3 sm:grid-cols-3">
                                            <div class="light-outline rounded-2xl bg-background px-3 py-3 ring-1 ring-border/30">
                                                <p class="text-[10px] uppercase tracking-[0.14em] text-muted-foreground">Accounts</p>
                                                <p id="app-accounts-total" class="mt-1 text-lg font-semibold tabular-nums">{{ $accountAdminSummary['total'] }}</p>
                                            </div>
                                            <div class="light-outline rounded-2xl bg-background px-3 py-3 ring-1 ring-border/30">
                                                <p class="text-[10px] uppercase tracking-[0.14em] text-muted-foreground">Guests</p>
                                                <p id="app-accounts-guests" class="mt-1 text-lg font-semibold tabular-nums">{{ $accountAdminSummary['active_guests'] }}</p>
                                            </div>
                                            <div class="light-outline rounded-2xl bg-background px-3 py-3 ring-1 ring-border/30">
                                                <p class="text-[10px] uppercase tracking-[0.14em] text-muted-foreground">Blocked</p>
                                                <p id="app-accounts-blocked" class="mt-1 text-lg font-semibold tabular-nums">{{ $accountAdminSummary['blocked'] }}</p>
                                            </div>
                                        </div>
                                        <div class="border-t border-border/30 bg-background/70 px-3 py-3">
                                            <a href="{{ route('accounts.index') }}" class="light-outline inline-flex w-full items-center justify-center rounded-2xl bg-card px-4 py-2.5 text-sm font-medium text-foreground ring-1 ring-border/40 transition hover:bg-muted/40">
                                                Open account center
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div id="app-notifications-root" class="relative" data-feed-url="{{ route('api.notifications.latest') }}" data-index-url="{{ route('notifications.index') }}">
                                <button id="relay-command-toast-anchor" title="Notifications" aria-expanded="false" aria-controls="app-notifications-panel" class="light-outline-soft inline-flex h-9 w-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:text-foreground">
                                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.65V4a2 2 0 1 0-4 0v1.35A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M9.73 17a2.99 2.99 0 0 0 4.54 0"/></svg>
                                    <span id="app-notifications-badge" class="absolute -right-1 -top-1 hidden min-w-[1.15rem] rounded-full bg-amber-400 px-1.5 py-0.5 text-center text-[10px] font-bold leading-none text-background">0</span>
                                </button>
                                <div id="app-notifications-panel" class="light-outline-strong absolute right-0 top-[calc(100%+0.75rem)] z-[95] hidden w-[min(26rem,calc(100vw-1.5rem))] overflow-hidden rounded-3xl border border-border/50 bg-card shadow-2xl shadow-black/40 ring-1 ring-border/40">
                                    <div class="border-b border-border/30 px-4 py-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold">Notifications</p>
                                                <p class="text-[11px] text-muted-foreground">Latest 10 events, refreshed live.</p>
                                            </div>
                                            <span class="light-outline-soft inline-flex rounded-full bg-background px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted-foreground ring-1 ring-border/40">Live</span>
                                        </div>
                                    </div>
                                    <div id="app-notifications-list" class="max-h-[28rem] overflow-y-auto p-2"></div>
                                    <div class="border-t border-border/30 bg-background/70 px-3 py-3">
                                        <a href="{{ route('notifications.index') }}" class="light-outline inline-flex w-full items-center justify-center rounded-2xl bg-card px-4 py-2.5 text-sm font-medium text-foreground ring-1 ring-border/40 transition hover:bg-muted/40">
                                            Open full notification history
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <button
                                id="global-tour-trigger"
                                type="button"
                                onclick="startGlobalFeatureTour()"
                                title="Guided tour"
                                aria-label="Start guided tour"
                                class="light-outline-soft inline-flex h-9 w-9 items-center justify-center rounded-2xl text-sm font-bold text-muted-foreground transition hover:text-foreground"
                            >
                                ?
                            </button>
                            <details class="relative ml-1">
                                <summary class="flex cursor-pointer list-none">
                                    <span class="light-outline-soft flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-card text-sm font-semibold">
                                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                                    </span>
                                </summary>
                                <div class="light-outline-strong absolute right-0 z-50 mt-2 w-52 rounded-3xl bg-card p-2 shadow-xl animate-in">
                                    <div class="px-4 py-3">
                                        <p class="text-sm font-medium">{{ Auth::user()->name ?? 'User' }}</p>
                                        <p class="text-xs text-muted-foreground">{{ Auth::user()->email ?? '' }}</p>
                                    </div>
                                    <div class="my-1 h-px bg-border/30"></div>
                                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-2xl px-4 py-2.5 text-sm transition hover:bg-muted/50">Profile</a>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="flex w-full items-center gap-2 rounded-2xl px-4 py-2.5 text-sm text-left transition hover:bg-muted/50">Log out</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </header>

                    <main id="main-content-scroll" class="relative flex-1 overflow-y-auto px-2 pb-6 pt-1 lg:px-4">
                        @yield('content')
                    </main>
                </div>
            </div>
        </div>

        <div id="global-tour-overlay" class="fixed inset-0 z-[120] hidden">
            <div id="global-tour-backdrop" class="absolute inset-0 bg-transparent backdrop-blur-[3px]" style="clip-path: inset(0 0 0 0);"></div>
            <div id="global-tour-spotlight" class="pointer-events-none absolute hidden rounded-[28px] border border-zinc-100/75 shadow-[0_0_0_9999px_rgba(0,0,0,0.16)] transition-all duration-300 ease-out"></div>
            <div class="absolute inset-x-4 bottom-4 mx-auto max-w-md sm:inset-x-auto sm:right-6 sm:bottom-6">
                <div class="rounded-3xl border border-zinc-700/80 bg-zinc-950/96 p-5 text-zinc-100 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-zinc-400">Guided tour</p>
                            <h3 id="global-tour-title" class="mt-2 text-xl font-semibold">Platform walkthrough</h3>
                        </div>
                        <button
                            type="button"
                            id="global-tour-close"
                            class="rounded-full p-2 text-zinc-400 transition-colors hover:bg-zinc-800/80 hover:text-zinc-100"
                            aria-label="Close guided tour"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <p id="global-tour-description" class="mt-3 text-sm leading-6 text-zinc-300"></p>

                    <div class="mt-5 flex items-center justify-between text-xs text-zinc-400">
                        <span id="global-tour-progress">Step 1 / 1</span>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <button
                            type="button"
                            id="global-tour-back"
                            class="rounded-full border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-100 transition-colors hover:bg-zinc-800/80 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Back
                        </button>
                        <div class="ml-auto flex items-center gap-2">
                            <button
                                type="button"
                                id="global-tour-skip"
                                class="rounded-full border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-300 transition-colors hover:bg-zinc-800/80 hover:text-zinc-100"
                            >
                                Skip
                            </button>
                            <button
                                type="button"
                                id="global-tour-next"
                                class="rounded-full bg-zinc-100 px-4 py-2 text-sm font-semibold text-zinc-950 transition-transform hover:scale-[1.02]"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var input = document.getElementById('global-search-input');
            var panel = document.getElementById('global-search-panel');
            var resultsBox = document.getElementById('global-search-results');
            if (!input || !panel || !resultsBox) return;

            var selector = 'a[data-search-link="1"]';
            var recentKey = 'pulsenode.search.recent';
            var activeIndex = 0;
            var renderedResults = [];

            function normalize(v) {
                return (v || '').toLowerCase().trim();
            }

            function notify(message, variant) {
                var el = document.createElement('div');
                el.className = 'fixed bottom-4 right-4 z-[100] rounded-xl px-3 py-2 text-xs shadow-xl ' + (variant === 'error' ? 'bg-red-500/90 text-white' : 'bg-card text-foreground');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () { el.remove(); }, 1800);
            }

            function links() {
                return Array.prototype.slice.call(document.querySelectorAll(selector));
            }

            function saveRecent(label) {
                try {
                    var curr = JSON.parse(localStorage.getItem(recentKey) || '[]');
                    curr = curr.filter(function (x) { return x !== label; });
                    curr.unshift(label);
                    localStorage.setItem(recentKey, JSON.stringify(curr.slice(0, 8)));
                } catch (_) {}
            }

            function recentItems() {
                try {
                    return JSON.parse(localStorage.getItem(recentKey) || '[]');
                } catch (_) {
                    return [];
                }
            }

            function openRawPayload() {
                var details = Array.prototype.slice.call(document.querySelectorAll('details')).find(function (d) {
                    var summary = d.querySelector('summary');
                    return summary && normalize(summary.textContent).indexOf('json payload') !== -1;
                });

                if (!details) {
                    window.location.href = '{{ route('power-strip-diagnostics.edit') }}';
                    return;
                }

                details.open = true;
                details.scrollIntoView({ behavior: 'smooth', block: 'center' });
                notify('Opened raw payload.');
            }

            function toggleAllRelays(turnOn) {
                var state = turnOn ? 'on' : 'off';
                Promise.all([
                    fetch('/api/relay/1/' + state, { credentials: 'same-origin' }),
                    fetch('/api/relay/2/' + state, { credentials: 'same-origin' }),
                    fetch('/api/relay/3/' + state, { credentials: 'same-origin' })
                ]).then(function (responses) {
                    return Promise.all(responses.map(function (response) {
                        if (!response.ok) throw new Error('Relay command failed');
                        return response.json();
                    }));
                }).then(function (payloads) {
                    notify(turnOn ? 'All sockets turned on.' : 'All sockets turned off.');
                    var latest = payloads.length ? (payloads[payloads.length - 1].latest || null) : null;
                    if (latest) {
                        window.__pulsenodeLatest = latest;
                        window.dispatchEvent(new CustomEvent('pulsenode:latest', { detail: latest }));
                    }
                }).catch(function () {
                    notify('Relay command failed.', 'error');
                });
            }

            var quickActions = [
                { label: 'Go Dashboard', keywords: 'go dashboard home', run: function () { window.location.href = '{{ route('dashboard') }}'; } },
                { label: 'Go Power Strip', keywords: 'go power strip sockets', run: function () { window.location.href = '{{ route('power-strip.index') }}'; } },
                { label: 'Go Settings', keywords: 'go settings billing appearance hardware diagnostics', run: function () { window.location.href = '{{ route('settings.index') }}'; } },
                { label: 'Settings: Hardware & Diagnostics', keywords: 'settings hardware diagnostics power strip payload mqtt esp32', run: function () { window.location.href = '{{ route('power-strip-diagnostics.edit') }}'; } },
                { label: 'Go Notifications', keywords: 'go notifications inbox alerts', run: function () { window.location.href = '{{ route('notifications.index') }}'; } },
                @if($accountAdminSummary)
                { label: 'Go Accounts', keywords: 'go accounts users permissions admin', run: function () { window.location.href = '{{ route('accounts.index') }}'; } },
                @endif
                { label: 'Turn all off', keywords: 'turn all off sockets relay', run: function () { toggleAllRelays(false); } },
                { label: 'Turn all on', keywords: 'turn all on sockets relay', run: function () { toggleAllRelays(true); } },
                { label: 'Open raw payload', keywords: 'open raw payload json details', run: openRawPayload },
                {
                    label: 'Restart MQTT listener',
                    keywords: 'restart mqtt listener technical command',
                    run: function () {
                        fetch('/api/system/mqtt-listener/restart', { credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (d) {
                                notify(d && d.message ? d.message : 'Restart command sent.');
                            })
                            .catch(function () { notify('Failed to restart MQTT listener.', 'error'); });
                    }
                }
            ];

            function collectMetricTargets() {
                var selectors = [
                    'main h2',
                    'main h3',
                    'main [id^="dash-"]',
                    'main [id^="total-"]',
                    'main [id^="strip-"]',
                    'main [id^="active-"]',
                    'main [id^="socket-card-"]',
                    'main [id^="dashboard-socket-"]'
                ];

                var seen = new Set();
                var targets = [];
                Array.prototype.slice.call(document.querySelectorAll(selectors.join(','))).forEach(function (el) {
                    var label = (el.getAttribute('data-search-label') || el.textContent || '').replace(/\s+/g, ' ').trim();
                    if (!label) return;
                    if (label.length > 80) label = label.slice(0, 80) + '…';

                    var key = normalize(label);
                    if (seen.has(key)) return;
                    seen.add(key);

                    targets.push({
                        label: 'Find: ' + label,
                        keywords: key,
                        run: function () {
                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            el.classList.add('ring-2', 'ring-primary/50');
                            setTimeout(function () { el.classList.remove('ring-2', 'ring-primary/50'); }, 1200);
                        }
                    });
                });
                return targets;
            }

            function navResults() {
                return links().map(function (a) {
                    var label = (a.getAttribute('data-search-label') || a.textContent || '').trim();
                    return {
                        label: 'Open: ' + label,
                        keywords: normalize(label),
                        run: function () { window.location.href = a.getAttribute('href'); }
                    };
                });
            }

            function fromRecent() {
                return recentItems().map(function (item) {
                    return {
                        label: 'Recent: ' + item,
                        keywords: normalize(item),
                        run: function () {
                            input.value = item;
                            renderResults(item);
                            input.focus();
                        }
                    };
                });
            }

            function mergeAndFilter(query) {
                var q = normalize(query);
                var all = []
                    .concat(quickActions)
                    .concat(navResults())
                    .concat(collectMetricTargets());

                if (q === '') {
                    return fromRecent().concat(quickActions).slice(0, 10);
                }

                return all.filter(function (item) {
                    var hay = normalize(item.label + ' ' + (item.keywords || ''));
                    return hay.indexOf(q) !== -1;
                }).slice(0, 14);
            }

            function setOpen(open) {
                panel.classList.toggle('hidden', !open);
            }

            function execute(item) {
                if (!item || typeof item.run !== 'function') return;
                var clean = item.label.replace(/^Recent:\s*/, '').replace(/^Open:\s*/, '').replace(/^Find:\s*/, '');
                saveRecent(clean);
                item.run();
                setOpen(false);
            }

            function renderResults(query, keepIndex) {
                renderedResults = mergeAndFilter(query);
                if (!keepIndex) {
                    activeIndex = 0;
                } else if (activeIndex >= renderedResults.length) {
                    activeIndex = Math.max(0, renderedResults.length - 1);
                }

                if (renderedResults.length === 0) {
                    resultsBox.innerHTML = '<div class="px-3 py-3 text-sm text-muted-foreground">No matches.</div>';
                    return;
                }

                resultsBox.innerHTML = renderedResults.map(function (r, idx) {
                    var active = idx === activeIndex;
                    return '<button data-search-idx="' + idx + '" class="flex w-full items-center rounded-xl px-3 py-2 text-left text-sm transition ' + (active ? 'bg-primary/15 text-foreground' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground') + '">' +
                        '<span>' + r.label + '</span>' +
                        '</button>';
                }).join('');
            }

            function buttonFromEvent(e) {
                var target = e.target;
                if (!(target instanceof Element)) {
                    target = target && target.parentElement ? target.parentElement : null;
                }
                if (!target) return null;
                return target.closest('button[data-search-idx]');
            }

            resultsBox.addEventListener('pointerdown', function (e) {
                var btn = buttonFromEvent(e);
                if (!btn) return;
                e.preventDefault();
            });

            resultsBox.addEventListener('click', function (e) {
                var btn = buttonFromEvent(e);
                if (!btn) return;

                e.preventDefault();
                e.stopPropagation();

                var idx = Number(btn.getAttribute('data-search-idx')) || 0;
                execute(renderedResults[idx]);
            });

            input.addEventListener('focus', function () {
                setOpen(true);
                renderResults(input.value, false);
            });

            input.addEventListener('input', function () {
                setOpen(true);
                renderResults(input.value, false);
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (renderedResults.length > 0) {
                        activeIndex = (activeIndex + 1) % renderedResults.length;
                        renderResults(input.value, true);
                    }
                    return;
                }

                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (renderedResults.length > 0) {
                        activeIndex = (activeIndex - 1 + renderedResults.length) % renderedResults.length;
                        renderResults(input.value, true);
                    }
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();
                    execute(renderedResults[activeIndex] || renderedResults[0]);
                    return;
                }

                if (e.key === 'Escape') {
                    setOpen(false);
                }
            });

            document.addEventListener('click', function (e) {
                if (!panel.contains(e.target) && e.target !== input) {
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', function (e) {
                var isShortcut = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k';
                if (!isShortcut) return;

                e.preventDefault();
                input.focus();
                input.select();
                setOpen(true);
                renderResults(input.value);
            });
        })();
        </script>
        <script>
        (function () {
            var STORAGE_KEY = 'pulsenode.globalTour.state';
            var REDIRECT_KEY = 'pulsenode.globalTour.redirecting';
            var CURRENT_PATH = window.location.pathname;

            var overlay = document.getElementById('global-tour-overlay');
            var backdrop = document.getElementById('global-tour-backdrop');
            var spotlight = document.getElementById('global-tour-spotlight');
            var titleEl = document.getElementById('global-tour-title');
            var descEl = document.getElementById('global-tour-description');
            var progressEl = document.getElementById('global-tour-progress');
            var backBtn = document.getElementById('global-tour-back');
            var skipBtn = document.getElementById('global-tour-skip');
            var nextBtn = document.getElementById('global-tour-next');
            var closeBtn = document.getElementById('global-tour-close');

            if (!overlay || !backdrop || !spotlight || !titleEl || !descEl || !progressEl || !backBtn || !skipBtn || !nextBtn || !closeBtn) {
                return;
            }

            var tourSteps = [
                {
                    path: '{{ route('dashboard', [], false) }}',
                    selector: '#dashboard-live-card',
                    title: 'Energy at a glance',
                    description: 'This card summarizes live power, current and overall health so you can assess the system instantly.'
                },
                {
                    path: '{{ route('dashboard', [], false) }}',
                    selector: '#dashboard-socket-grid',
                    title: 'Socket snapshot',
                    description: 'Each tile tracks one relay channel and highlights activity changes while telemetry updates stream in.'
                },
                {
                    path: '{{ route('power-strip.index', [], false) }}',
                    selector: '#powerstrip-command-center',
                    title: 'Command center',
                    description: 'This is the control cockpit for relay actions, allowing quick interventions and synchronized commands.'
                },
                {
                    path: '{{ route('power-strip.index', [], false) }}',
                    selector: '#powerstrip-sockets-grid',
                    title: 'Socket control wall',
                    description: 'Use this grid to inspect each outlet state and trigger per-socket actions with immediate feedback.'
                },
                {
                    path: '{{ route('devices.index', [], false) }}',
                    selector: '#devices-overview',
                    title: 'My devices overview',
                    description: 'This section shows live detection status and quick health metrics for connected devices.'
                },
                {
                    path: '{{ route('devices.profiles.index', [], false) }}',
                    selector: '#devices-profiles-library',
                    title: 'My devices: profiles',
                    description: 'Saved device signatures are managed here, including training results and cleanup actions.'
                },
                {
                    path: '{{ route('devices.plans.index', [], false) }}',
                    selector: '#devices-plans-hub',
                    title: 'Auto-detect strategy (spikes)',
                    description: 'Detection plans control sensitivity using sample window and threshold, helping classify spike patterns per socket.'
                },
                {
                    path: '{{ route('devices.activity.index', [], false) }}',
                    selector: '#devices-activity-log',
                    title: 'My devices: activity',
                    description: 'Use this timeline to inspect recent detections, confidence levels and live classifier behavior.'
                },
                {
                    path: '{{ route('history.index', [], false) }}',
                    selector: '#history-overview-card',
                    title: 'History and trends overview',
                    description: 'This card summarizes daily and weekly performance, active tariff and key warning indicators.'
                },
                {
                    path: '{{ route('history.index', [], false) }}',
                    selector: '#history-hourly-map',
                    title: 'History detail drilldown',
                    description: 'Open hourly load details to inspect minute and second-level consumption behavior.'
                },
                {
                    path: '{{ route('electricity-billing.archive', [], false) }}',
                    selector: '#invoice-archive-hero',
                    title: 'Invoice archive',
                    description: 'This page stores historical invoices with period-based navigation and upload actions.'
                },
                {
                    path: '{{ route('electricity-billing.archive', [], false) }}',
                    selector: '#invoice-archive-explorer',
                    title: 'Invoice explorer details',
                    description: 'Here you can browse folders, preview/download files and organize archive structure.'
                },
                {
                    path: '{{ route('accounts.index', [], false) }}',
                    selector: '#accounts-workspace-header',
                    fallbackSelector: '[data-tour="nav-accounts"]',
                    title: 'Accounts workspace',
                    description: 'Manage users, roles and account status from the admin control center.'
                },
                {
                    path: '{{ route('accounts.index', [], false) }}',
                    selector: '#accounts-workspace-detail',
                    fallbackSelector: '[data-tour="nav-accounts"]',
                    title: 'Account details and actions',
                    description: 'Use this section to edit access, block/unblock accounts and handle guest duration.'
                },
                {
                    path: '{{ route('electricity-billing.edit', [], false) }}',
                    selector: '#billing-settings-hero',
                    title: 'Billing settings',
                    description: 'Configure electricity price, tax and currency used in all cost estimations.'
                },
                {
                    path: '{{ route('electricity-billing.edit', [], false) }}',
                    selector: '#billing-settings-profiles',
                    title: 'Price profiles',
                    description: 'Create and apply tariff profiles so price presets can be switched quickly.'
                },
                {
                    path: '{{ route('appearance.edit', [], false) }}',
                    selector: '#appearance-settings-hero',
                    title: 'Appearance settings',
                    description: 'Control interface theme behavior and selected language defaults for the workspace.'
                },
                {
                    path: '{{ route('appearance.edit', [], false) }}',
                    selector: '#appearance-theme-panel',
                    title: 'Theme controls',
                    description: 'Choose light, dark or system mode from this panel.'
                },
                {
                    path: '{{ route('notifications.index', [], false) }}',
                    selector: '#notifications-overview',
                    title: 'Notifications overview',
                    description: 'Critical and informational events are centralized here for fast awareness.'
                },
                {
                    path: '{{ route('notifications.index', [], false) }}',
                    selector: '#notifications-history',
                    title: 'Notifications history',
                    description: 'Review the event timeline in detail and follow operational changes.'
                }
            ];

            var state = {
                active: false,
                index: 0
            };

            var rafHandle = 0;
            var followInterval = 0;

            function clearState() {
                try {
                    window.localStorage.removeItem(STORAGE_KEY);
                    window.localStorage.removeItem(REDIRECT_KEY);
                } catch (_) {}
            }

            function persistState() {
                try {
                    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
                } catch (_) {}
            }

            function readState() {
                try {
                    var raw = window.localStorage.getItem(STORAGE_KEY);
                    if (!raw) return null;
                    var parsed = JSON.parse(raw);
                    if (!parsed || typeof parsed !== 'object') return null;
                    if (!parsed.active) return null;
                    var index = Number(parsed.index || 0);
                    if (!Number.isFinite(index)) return null;
                    return {
                        active: true,
                        index: Math.min(Math.max(0, index), tourSteps.length - 1)
                    };
                } catch (_) {
                    return null;
                }
            }

            function normalizePath(path) {
                if (!path) return '/';
                return path.endsWith('/') && path !== '/' ? path.slice(0, -1) : path;
            }

            function currentStep() {
                return tourSteps[state.index] || null;
            }

            function setOverlayVisible(visible) {
                overlay.classList.toggle('hidden', !visible);
                if (!visible) {
                    spotlight.classList.add('hidden');
                    backdrop.style.clipPath = 'inset(0 0 0 0)';
                }
            }

            function applyBackdropCutout(rect) {
                if (!rect) {
                    backdrop.style.clipPath = 'inset(0 0 0 0)';
                    return;
                }

                var x1 = Math.max(0, Math.floor(rect.left));
                var y1 = Math.max(0, Math.floor(rect.top));
                var x2 = Math.min(window.innerWidth, Math.ceil(rect.right));
                var y2 = Math.min(window.innerHeight, Math.ceil(rect.bottom));

                // Keep blur/dim outside the target while leaving a transparent hole over the component.
                backdrop.style.clipPath = 'polygon(' +
                    '0% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 0%,' +
                    x1 + 'px ' + y1 + 'px,' +
                    x1 + 'px ' + y2 + 'px,' +
                    x2 + 'px ' + y2 + 'px,' +
                    x2 + 'px ' + y1 + 'px,' +
                    x1 + 'px ' + y1 + 'px' +
                ')';
            }

            function positionSpotlight(targetEl) {
                if (!targetEl) {
                    spotlight.classList.add('hidden');
                    return;
                }

                var rect = targetEl.getBoundingClientRect();
                var pad = 10;
                var top = Math.max(6, rect.top - pad);
                var left = Math.max(6, rect.left - pad);
                var width = Math.max(24, rect.width + pad * 2);
                var height = Math.max(24, rect.height + pad * 2);

                spotlight.style.top = top + 'px';
                spotlight.style.left = left + 'px';
                spotlight.style.width = width + 'px';
                spotlight.style.height = height + 'px';
                applyBackdropCutout({
                    left: left,
                    top: top,
                    right: left + width,
                    bottom: top + height,
                });
                spotlight.classList.remove('hidden');
            }

            function findStepTarget(step) {
                if (!step || !step.selector) return null;

                function isVisible(el) {
                    if (!el || !(el instanceof HTMLElement)) return false;
                    var style = window.getComputedStyle(el);
                    if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) {
                        return false;
                    }

                    var rect = el.getBoundingClientRect();
                    if (rect.width < 20 || rect.height < 20) {
                        return false;
                    }

                    if (rect.bottom <= 0 || rect.right <= 0 || rect.top >= window.innerHeight || rect.left >= window.innerWidth) {
                        return false;
                    }

                    var centerX = rect.left + (rect.width / 2);
                    var centerY = rect.top + (rect.height / 2);
                    if (centerX <= 0 || centerX >= window.innerWidth || centerY <= 0 || centerY >= window.innerHeight) {
                        return false;
                    }

                    return true;
                }

                var matches = Array.prototype.slice.call(document.querySelectorAll(step.selector));
                var visible = matches.find(isVisible);
                return visible || matches[0] || null;
            }

            function scheduleSpotlightRefresh() {
                if (rafHandle) {
                    window.cancelAnimationFrame(rafHandle);
                }

                rafHandle = window.requestAnimationFrame(function () {
                    var step = currentStep();
                    var target = findStepTarget(step);
                    positionSpotlight(target);
                });
            }

            function ensureCorrectRoute(step) {
                if (!step) return false;
                var targetPath = normalizePath(step.path);
                var here = normalizePath(CURRENT_PATH);
                if (targetPath === here) return false;

                try {
                    window.localStorage.setItem(REDIRECT_KEY, '1');
                } catch (_) {}

                persistState();
                window.location.href = step.path;
                return true;
            }

            function updateControls() {
                backBtn.disabled = state.index <= 0;
                nextBtn.textContent = state.index >= tourSteps.length - 1 ? 'Finish' : 'Next';
            }

            function renderStep() {
                if (!state.active) return;

                var step = currentStep();
                if (!step) {
                    finishTour();
                    return;
                }

                if (ensureCorrectRoute(step)) {
                    return;
                }

                setOverlayVisible(true);

                titleEl.textContent = step.title;
                descEl.textContent = step.description;
                progressEl.textContent = 'Step ' + (state.index + 1) + ' / ' + tourSteps.length;
                updateControls();

                var target = findStepTarget(step)
                    || (step && step.fallbackSelector ? findStepTarget({ selector: step.fallbackSelector }) : null)
                    || document.getElementById('main-content-scroll');
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                }

                window.setTimeout(scheduleSpotlightRefresh, 120);
                persistState();
            }

            function stopFollowLoop() {
                if (followInterval) {
                    window.clearInterval(followInterval);
                    followInterval = 0;
                }
            }

            function startFollowLoop() {
                stopFollowLoop();
                followInterval = window.setInterval(function () {
                    if (!state.active) return;
                    scheduleSpotlightRefresh();
                }, 220);
            }

            function finishTour() {
                state.active = false;
                setOverlayVisible(false);
                stopFollowLoop();
                clearState();
            }

            function nextStep() {
                if (state.index >= tourSteps.length - 1) {
                    finishTour();
                    return;
                }
                state.index += 1;
                renderStep();
            }

            function prevStep() {
                if (state.index <= 0) {
                    renderStep();
                    return;
                }
                state.index -= 1;
                renderStep();
            }

            function startTourFrom(index) {
                state.active = true;
                state.index = Math.min(Math.max(0, Number(index || 0)), tourSteps.length - 1);
                renderStep();
                startFollowLoop();
            }

            window.startGlobalFeatureTour = function () {
                startTourFrom(0);
            };

            backBtn.addEventListener('click', prevStep);
            nextBtn.addEventListener('click', nextStep);
            skipBtn.addEventListener('click', finishTour);
            closeBtn.addEventListener('click', finishTour);

            document.addEventListener('keydown', function (event) {
                if (!state.active) return;

                if (event.key === 'Escape') {
                    finishTour();
                    return;
                }

                if (event.key === 'ArrowRight' || event.key === 'Enter') {
                    event.preventDefault();
                    nextStep();
                    return;
                }

                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    prevStep();
                }
            });

            window.addEventListener('resize', scheduleSpotlightRefresh);
            window.addEventListener('scroll', scheduleSpotlightRefresh, true);

            var restored = readState();
            var wasRedirecting = false;
            try {
                wasRedirecting = window.localStorage.getItem(REDIRECT_KEY) === '1';
                if (wasRedirecting) {
                    window.localStorage.removeItem(REDIRECT_KEY);
                }
            } catch (_) {}

            if (restored && (wasRedirecting || normalizePath((tourSteps[restored.index] || {}).path) === normalizePath(CURRENT_PATH))) {
                state = restored;
                startTourFrom(state.index);
            }
        })();
        </script>
        @if($accountAdminSummary)
        <script>
        (function () {
            var root = document.getElementById('app-accounts-root');
            if (!root) return;

            var button = document.getElementById('app-accounts-trigger');
            var panel = document.getElementById('app-accounts-panel');
            var badge = document.getElementById('app-accounts-badge');
            var badgeValue = document.getElementById('app-accounts-badge-value');
            var total = document.getElementById('app-accounts-total');
            var guests = document.getElementById('app-accounts-guests');
            var blocked = document.getElementById('app-accounts-blocked');
            if (!button || !panel) return;

            var isOpen = false;

            function setOpen(nextOpen) {
                isOpen = nextOpen;
                panel.classList.toggle('hidden', !nextOpen);
                button.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
            }

            function updateSummary(detail) {
                if (!detail) return;

                if (total) {
                    total.textContent = String(detail.total ?? 0);
                }

                if (guests) {
                    guests.textContent = String(detail.active_guests ?? 0);
                }

                if (blocked) {
                    blocked.textContent = String(detail.blocked ?? 0);
                }

                if (badge && badgeValue) {
                    var blockedCount = Number(detail.blocked ?? 0);
                    badgeValue.textContent = String(blockedCount);
                    badge.classList.toggle('hidden', blockedCount <= 0);
                }
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                setOpen(!isOpen);
            });

            document.addEventListener('click', function (event) {
                if (!root.contains(event.target)) {
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    setOpen(false);
                }
            });

            window.addEventListener('pulsenode:accounts-summary', function (event) {
                updateSummary(event.detail);
            });
        })();
        </script>
        @endif
        <script>
        (function () {
            var root = document.getElementById('app-notifications-root');
            if (!root) return;

            var button = document.getElementById('relay-command-toast-anchor');
            var panel = document.getElementById('app-notifications-panel');
            var list = document.getElementById('app-notifications-list');
            var badge = document.getElementById('app-notifications-badge');
            var feedUrl = root.getAttribute('data-feed-url');
            var lastSeenKey = 'pulsenode.notifications.last_seen_id';

            if (!button || !panel || !list || !badge || !feedUrl) return;

            var items = [];
            var newestId = 0;
            var isOpen = false;

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function lastSeenId() {
                var raw = Number(window.localStorage.getItem(lastSeenKey) || '0');
                return Number.isFinite(raw) ? raw : 0;
            }

            function setLastSeenId(value) {
                window.localStorage.setItem(lastSeenKey, String(value));
            }

            function relativeTime(value) {
                if (!value) return 'Just now';
                var timestamp = Date.parse(value);
                if (!Number.isFinite(timestamp)) return 'Just now';

                var diffSeconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
                if (diffSeconds < 10) return 'Just now';
                if (diffSeconds < 60) return diffSeconds + 's ago';
                if (diffSeconds < 3600) return Math.floor(diffSeconds / 60) + ' min ago';
                if (diffSeconds < 86400) return Math.floor(diffSeconds / 3600) + ' h ago';
                return Math.floor(diffSeconds / 86400) + ' d ago';
            }

            function toneClasses(level) {
                if (level === 'error') return 'bg-red-500/15 text-red-300 ring-red-500/20';
                if (level === 'warning') return 'bg-amber-500/15 text-amber-300 ring-amber-500/20';
                if (level === 'success') return 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/20';
                return 'bg-sky-500/15 text-sky-300 ring-sky-500/20';
            }

            function renderEmpty() {
                list.innerHTML = '<div class="px-2 py-6 text-center text-sm text-muted-foreground">No notifications yet. Important system events will appear here.</div>';
            }

            function renderList() {
                if (!items.length) {
                    renderEmpty();
                    return;
                }

                list.innerHTML = items.map(function (item) {
                    var message = item.message
                        ? '<p class="mt-1.5 text-xs leading-5 text-muted-foreground">' + escapeHtml(item.message) + '</p>'
                        : '';
                    var link = item.action_url
                        ? '<a href="' + escapeHtml(item.action_url) + '" class="mt-3 inline-flex text-xs font-medium text-primary transition hover:opacity-80">Open</a>'
                        : '';

                    return '<article class="light-outline rounded-2xl bg-background px-3 py-3 ring-1 ring-border/30">'
                        + '<div class="flex items-start justify-between gap-3">'
                        + '<div class="min-w-0 flex-1">'
                        + '<div class="flex flex-wrap items-center gap-2">'
                        + '<span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] ring-1 ' + toneClasses(item.level) + '">' + escapeHtml(item.level || 'info') + '</span>'
                        + '<span class="text-[11px] text-muted-foreground">' + escapeHtml(relativeTime(item.created_at)) + '</span>'
                        + '</div>'
                        + '<p class="mt-2 text-sm font-semibold text-foreground">' + escapeHtml(item.title) + '</p>'
                        + message
                        + link
                        + '</div>'
                        + '</div>'
                        + '</article>';
                }).join('');
            }

            function renderBadge() {
                var unseen = items.filter(function (item) {
                    return Number(item.id || 0) > lastSeenId();
                }).length;

                if (unseen > 0) {
                    badge.textContent = unseen > 9 ? '9+' : String(unseen);
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }

            function markAllSeen() {
                if (newestId > 0) {
                    setLastSeenId(newestId);
                    renderBadge();
                }
            }

            function setOpen(nextOpen) {
                isOpen = nextOpen;
                panel.classList.toggle('hidden', !nextOpen);
                button.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');

                if (nextOpen) {
                    markAllSeen();
                }
            }

            function applyPayload(payload) {
                items = Array.isArray(payload && payload.notifications) ? payload.notifications : [];
                newestId = items.length ? Number(items[0].id || 0) : 0;
                renderList();
                renderBadge();
            }

            function fetchLatest() {
                fetch(feedUrl + '?limit=10', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('notifications fetch failed');
                        return response.json();
                    })
                    .then(function (payload) {
                        applyPayload(payload);
                        if (isOpen) {
                            markAllSeen();
                        }
                    })
                    .catch(function () {
                        if (!items.length) {
                            renderEmpty();
                        }
                    });
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                setOpen(!isOpen);
            });

            document.addEventListener('click', function (event) {
                if (!root.contains(event.target)) {
                    setOpen(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    setOpen(false);
                }
            });

            renderEmpty();
            fetchLatest();
            window.setInterval(fetchLatest, 5000);
        })();
        </script>
        <script>
        (function () {
            var dot = document.getElementById('live-telemetry-dot');
            var power = document.getElementById('live-telemetry-power');
            var current = document.getElementById('live-telemetry-current');
            var hasTelemetryWidget = !!(dot && power && current);

            function asNumber(v) {
                var n = Number(v);
                return Number.isFinite(n) ? n : 0;
            }

            function isOnline(updatedAt) {
                if (!updatedAt) return false;
                var t = Date.parse(updatedAt);
                if (!Number.isFinite(t)) return false;
                return (Date.now() - t) <= (5 * 60 * 1000);
            }

            function setOnline(online) {
                if (!dot) return;
                dot.classList.remove('bg-red-400', 'bg-emerald-400');
                dot.classList.add(online ? 'bg-emerald-400' : 'bg-red-400');
            }

            function applyLatest(data) {
                var p = asNumber(data && data.power);
                var c = asNumber(data && data.current);
                if (hasTelemetryWidget) {
                    power.textContent = p.toFixed(1) + 'W';
                    current.textContent = c.toFixed(3) + 'A';
                }
                setOnline(isOnline(data && data.updated_at));
            }

            function publishLatest(data) {
                window.__pulsenodeLatest = data;
                window.dispatchEvent(new CustomEvent('pulsenode:latest', { detail: data }));
            }

            function pollLatest() {
                fetch('/api/latest', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) {
                        if (!r.ok) throw new Error('latest fetch failed');
                        return r.json();
                    })
                    .then(function (data) {
                        applyLatest(data);
                        publishLatest(data);
                    })
                    .catch(function () {
                        setOnline(false);
                    });
            }

            pollLatest();
            setInterval(pollLatest, 2000);
        })();
        </script>
    </body>
</html>
