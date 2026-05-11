<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['dark' => ($appearance ?? 'system') == 'dark'])
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
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

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
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

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico?v=20260507" sizes="any">
        <link rel="icon" href="/images/pulsenode-logo.png?v=20260507" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=20260507">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        @auth
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
        @endauth

        <script>
        (function () {
            var STORAGE_KEY = 'pulsenode.globalTour.state';
            var REDIRECT_KEY = 'pulsenode.globalTour.redirecting';
            var CURRENT_PATH = window.location.pathname;
            var TOUR_COMPLETE_URL = @json(route('dashboard.tour.complete'));
            var CSRF_TOKEN = @json(csrf_token());

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
                { path: '{{ route('dashboard', [], false) }}', selector: '#dashboard-live-card', title: 'Energy at a glance', description: 'This card summarizes live power, current and overall health so you can assess the system instantly.' },
                { path: '{{ route('dashboard', [], false) }}', selector: '#dashboard-socket-grid', title: 'Socket snapshot', description: 'Each tile tracks one relay channel and highlights activity changes while telemetry updates stream in.' },
                { path: '{{ route('power-strip.index', [], false) }}', selector: '#powerstrip-command-center', title: 'Command center', description: 'This is the control cockpit for relay actions, allowing quick interventions and synchronized commands.' },
                { path: '{{ route('power-strip.index', [], false) }}', selector: '#powerstrip-sockets-grid', title: 'Socket control wall', description: 'Use this grid to inspect each outlet state and trigger per-socket actions with immediate feedback.' },
                { path: '{{ route('devices.index', [], false) }}', selector: '#devices-overview', title: 'My devices overview', description: 'This section shows live detection status and quick health metrics for connected devices.' },
                { path: '{{ route('devices.profiles.index', [], false) }}', selector: '#devices-profiles-library', title: 'My devices: profiles', description: 'Saved device signatures are managed here, including training results and cleanup actions.' },
                { path: '{{ route('devices.plans.index', [], false) }}', selector: '#devices-plans-hub', title: 'Auto-detect strategy (spikes)', description: 'Detection plans control sensitivity using sample window and threshold, helping classify spike patterns per socket.' },
                { path: '{{ route('devices.index', [], false) }}', selector: '#devices-recent-events', title: 'Recent detection events', description: 'Use this section to inspect recent detections, confidence levels and live classifier behavior.' },
                { path: '{{ route('history.index', [], false) }}', selector: '#history-overview-card', title: 'History and trends overview', description: 'This card summarizes daily and weekly performance, active tariff and key warning indicators.' },
                { path: '{{ route('history.index', [], false) }}', selector: '#history-hourly-map', title: 'History detail drilldown', description: 'Open hourly load details to inspect minute and second-level consumption behavior.' },
                { path: '{{ route('electricity-billing.archive', [], false) }}', selector: '#invoice-archive-hero', title: 'Invoice archive', description: 'This page stores historical invoices with period-based navigation and upload actions.' },
                { path: '{{ route('electricity-billing.archive', [], false) }}', selector: '#invoice-archive-explorer', title: 'Invoice explorer details', description: 'Here you can browse folders, preview/download files and organize archive structure.' },
                { path: '{{ route('accounts.index', [], false) }}', selector: '#accounts-workspace-header', fallbackSelector: '[data-tour="nav-accounts"]', title: 'Accounts workspace', description: 'Manage users, roles and account status from the admin control center.' },
                { path: '{{ route('accounts.index', [], false) }}', selector: '#accounts-workspace-detail', fallbackSelector: '[data-tour="nav-accounts"]', title: 'Account details and actions', description: 'Use this section to edit access, block/unblock accounts and handle guest duration.' },
                { path: '{{ route('electricity-billing.edit', [], false) }}', selector: '#billing-settings-hero', title: 'Billing settings', description: 'Configure electricity price, tax and currency used in all cost estimations.' },
                { path: '{{ route('electricity-billing.edit', [], false) }}', selector: '#billing-settings-profiles', title: 'Price profiles', description: 'Create and apply tariff profiles so price presets can be switched quickly.' },
                { path: '{{ route('appearance.edit', [], false) }}', selector: '#appearance-settings-hero', title: 'Appearance settings', description: 'Control interface theme behavior and selected language defaults for the workspace.' },
                { path: '{{ route('appearance.edit', [], false) }}', selector: '#appearance-theme-panel', title: 'Theme controls', description: 'Choose light, dark or system mode from this panel.' },
                { path: '{{ route('notifications.index', [], false) }}', selector: '#notifications-overview', title: 'Notifications overview', description: 'Critical and informational events are centralized here for fast awareness.' },
                { path: '{{ route('notifications.index', [], false) }}', selector: '#notifications-history', title: 'Notifications history', description: 'Review the event timeline in detail and follow operational changes.' }
            ];

            var state = { active: false, index: 0 };
            var rafHandle = 0;
            var followInterval = 0;

            function clearState() { try { window.localStorage.removeItem(STORAGE_KEY); window.localStorage.removeItem(REDIRECT_KEY); } catch (_) {} }
            function persistState() { try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (_) {} }
            function normalizePath(path) { if (!path) return '/'; return path.endsWith('/') && path !== '/' ? path.slice(0, -1) : path; }
            function currentStep() { return tourSteps[state.index] || null; }

            function readState() {
                try {
                    var raw = window.localStorage.getItem(STORAGE_KEY);
                    if (!raw) return null;
                    var parsed = JSON.parse(raw);
                    if (!parsed || typeof parsed !== 'object' || !parsed.active) return null;
                    var index = Number(parsed.index || 0);
                    if (!Number.isFinite(index)) return null;
                    return { active: true, index: Math.min(Math.max(0, index), tourSteps.length - 1) };
                } catch (_) { return null; }
            }

            function setOverlayVisible(visible) {
                overlay.classList.toggle('hidden', !visible);
                if (!visible) {
                    spotlight.classList.add('hidden');
                    backdrop.style.clipPath = 'inset(0 0 0 0)';
                }
            }

            function applyBackdropCutout(rect) {
                if (!rect) { backdrop.style.clipPath = 'inset(0 0 0 0)'; return; }
                var x1 = Math.max(0, Math.floor(rect.left));
                var y1 = Math.max(0, Math.floor(rect.top));
                var x2 = Math.min(window.innerWidth, Math.ceil(rect.right));
                var y2 = Math.min(window.innerHeight, Math.ceil(rect.bottom));
                backdrop.style.clipPath = 'polygon(' +
                    '0% 0%, 100% 0%, 100% 100%, 0% 100%, 0% 0%,' +
                    x1 + 'px ' + y1 + 'px,' + x1 + 'px ' + y2 + 'px,' +
                    x2 + 'px ' + y2 + 'px,' + x2 + 'px ' + y1 + 'px,' +
                    x1 + 'px ' + y1 + 'px)';
            }

            function positionSpotlight(targetEl) {
                if (!targetEl) { spotlight.classList.add('hidden'); return; }
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
                applyBackdropCutout({ left: left, top: top, right: left + width, bottom: top + height });
                spotlight.classList.remove('hidden');
            }

            function findStepTarget(step) {
                if (!step || !step.selector) return null;
                function isVisible(el) {
                    if (!el || !(el instanceof HTMLElement)) return false;
                    var style = window.getComputedStyle(el);
                    if (style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0) return false;
                    var rect = el.getBoundingClientRect();
                    if (rect.width < 20 || rect.height < 20) return false;
                    if (rect.bottom <= 0 || rect.right <= 0 || rect.top >= window.innerHeight || rect.left >= window.innerWidth) return false;
                    var centerX = rect.left + (rect.width / 2);
                    var centerY = rect.top + (rect.height / 2);
                    return !(centerX <= 0 || centerX >= window.innerWidth || centerY <= 0 || centerY >= window.innerHeight);
                }
                var matches = Array.prototype.slice.call(document.querySelectorAll(step.selector));
                var visible = matches.find(isVisible);
                return visible || matches[0] || null;
            }

            function scheduleSpotlightRefresh() {
                if (rafHandle) window.cancelAnimationFrame(rafHandle);
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
                try { window.localStorage.setItem(REDIRECT_KEY, '1'); } catch (_) {}
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
                if (!step) { finishTour(); return; }
                if (ensureCorrectRoute(step)) return;

                setOverlayVisible(true);
                titleEl.textContent = step.title;
                descEl.textContent = step.description;
                progressEl.textContent = 'Step ' + (state.index + 1) + ' / ' + tourSteps.length;
                updateControls();

                var target = findStepTarget(step)
                    || (step && step.fallbackSelector ? findStepTarget({ selector: step.fallbackSelector }) : null)
                    || document.body;
                if (target && typeof target.scrollIntoView === 'function') {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                }

                window.setTimeout(scheduleSpotlightRefresh, 120);
                persistState();
            }

            function stopFollowLoop() { if (followInterval) { window.clearInterval(followInterval); followInterval = 0; } }
            function startFollowLoop() { stopFollowLoop(); followInterval = window.setInterval(function () { if (state.active) scheduleSpotlightRefresh(); }, 220); }
            function markTourCompleted() {
                try {
                    window.fetch(TOUR_COMPLETE_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                        },
                        keepalive: true,
                        body: JSON.stringify({ completed: true }),
                    });
                } catch (_) {}
            }
            function finishTour() { state.active = false; setOverlayVisible(false); stopFollowLoop(); clearState(); markTourCompleted(); }
            function nextStep() { if (state.index >= tourSteps.length - 1) { finishTour(); return; } state.index += 1; renderStep(); }
            function prevStep() { if (state.index <= 0) { renderStep(); return; } state.index -= 1; renderStep(); }
            function startTourFrom(index) { state.active = true; state.index = Math.min(Math.max(0, Number(index || 0)), tourSteps.length - 1); renderStep(); startFollowLoop(); }

            function resumeTourIfNeeded() {
                var redirecting = false;
                try { redirecting = window.localStorage.getItem(REDIRECT_KEY) === '1'; } catch (_) {}
                if (!redirecting) return;

                var savedState = readState();
                if (!savedState) { clearState(); return; }

                state.active = savedState.active;
                state.index = savedState.index;
                persistState();

                window.setTimeout(function () {
                    if (!state.active) return;
                    renderStep();
                    startFollowLoop();

                    try { window.localStorage.removeItem(REDIRECT_KEY); } catch (_) {}
                }, 0);
            }

            window.startGlobalFeatureTour = function () { startTourFrom(0); };
            backBtn.addEventListener('click', prevStep);
            nextBtn.addEventListener('click', nextStep);
            skipBtn.addEventListener('click', finishTour);
            closeBtn.addEventListener('click', finishTour);

            document.addEventListener('keydown', function (event) {
                if (!state.active) return;
                if (event.key === 'Escape') { finishTour(); return; }
                if (event.key === 'ArrowRight' || event.key === 'Enter') { event.preventDefault(); nextStep(); return; }
                if (event.key === 'ArrowLeft') { event.preventDefault(); prevStep(); }
            });

            window.addEventListener('resize', scheduleSpotlightRefresh);
            window.addEventListener('scroll', scheduleSpotlightRefresh, true);
            resumeTourIfNeeded();

            // Keep the guide manual-only so the public pages do not reopen the
            // walkthrough unexpectedly when a browser restores local state.
        })();
        </script>
    </body>
</html>
