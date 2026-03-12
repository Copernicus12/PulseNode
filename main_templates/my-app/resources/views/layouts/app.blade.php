<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>html { background-color: hsl(0 0% 7%); }</style>

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
        <div class="min-h-screen bg-background text-foreground">

            {{-- Mobile sheet --}}
            <input id="mobile-sidebar" type="checkbox" class="peer/mobile hidden" />
            <label for="mobile-sidebar"
                   class="fixed inset-0 z-40 hidden bg-black/60 backdrop-blur-sm peer-checked/mobile:block lg:hidden"></label>

            {{-- Mobile sidebar --}}
            <aside class="fixed inset-y-0 left-0 z-50 flex w-[270px] -translate-x-full flex-col p-3 transition-transform duration-300 peer-checked/mobile:translate-x-0 lg:hidden">
                <div class="flex flex-1 flex-col rounded-3xl bg-card p-4">
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
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-muted-foreground transition hover:bg-muted/50 hover:text-foreground">
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            Settings
                        </a>
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
                    <div class="flex w-full flex-col rounded-3xl bg-card p-4">
                        <div class="px-3 pb-8 pt-3">
                            <span class="text-xl font-bold tracking-tight">PulseNode</span>
                        </div>
                        <nav class="flex-1 space-y-1.5 overflow-y-auto lg:overflow-visible">
                            @include('layouts._sidebar-links')
                        </nav>
                        <div class="mt-4 space-y-1.5 border-t border-border/20 pt-4">
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm text-muted-foreground transition hover:bg-muted/50 hover:text-foreground">
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                Settings
                            </a>
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
                @php $isDashboardRoute = request()->routeIs('dashboard'); @endphp
                <div class="flex min-h-0 flex-1 flex-col">
                    <header class="grid h-16 shrink-0 grid-cols-[auto_1fr_auto] items-center gap-3 px-2 pb-2 lg:px-4">
                        <div class="flex items-center">
                            <label for="mobile-sidebar" class="inline-flex h-9 w-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:text-foreground lg:hidden">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
                            </label>
                            <span class="hidden h-9 w-9 lg:inline-block" aria-hidden="true"></span>
                        </div>

                        <div class="hidden sm:flex justify-center">
                            <div class="relative w-full max-w-3xl">
                                <svg class="absolute left-3.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input id="global-search-input" type="text" placeholder="Search pages (Dashboard, Power Strip, Settings...)" autocomplete="off" class="h-11 w-full rounded-2xl bg-card pl-10 pr-4 text-sm text-foreground placeholder:text-muted-foreground/50 focus:outline-none focus:ring-1 focus:ring-primary/30" />
                                <span class="pointer-events-none absolute right-3.5 top-1/2 hidden -translate-y-1/2 rounded-lg bg-background px-2 py-1 text-[10px] font-semibold text-muted-foreground lg:inline-flex">Ctrl/⌘ K</span>

                                <div id="global-search-panel" class="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-50 hidden overflow-hidden rounded-2xl border border-primary/35 bg-card ring-1 ring-primary/25 shadow-2xl shadow-black/60 outline outline-1 outline-border/50">
                                    <div class="border-b border-border/20 px-3 py-2 text-[11px] text-muted-foreground">Command Palette</div>
                                    <div id="global-search-results" class="max-h-[22rem] overflow-y-auto p-1.5"></div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            @unless($isDashboardRoute)
                                <div id="live-telemetry-pill" class="hidden items-center gap-2 rounded-2xl bg-card px-3 py-2 text-xs text-muted-foreground ring-1 ring-border/30 lg:inline-flex">
                                    <span id="live-telemetry-dot" class="h-2 w-2 rounded-full bg-red-400"></span>
                                    <span id="live-telemetry-power" class="font-semibold tabular-nums text-foreground">0.0W</span>
                                    <span id="live-telemetry-current" class="tabular-nums">0.000A</span>
                                </div>
                            @endunless
                            <button title="Users" class="inline-flex h-9 w-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:text-foreground">
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </button>
                            <button title="Messages" class="inline-flex h-9 w-9 items-center justify-center rounded-2xl text-muted-foreground transition hover:text-foreground">
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </button>
                            <details class="relative ml-1">
                                <summary class="flex cursor-pointer list-none">
                                    <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-card text-sm font-semibold">
                                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                                    </span>
                                </summary>
                                <div class="absolute right-0 z-50 mt-2 w-52 rounded-3xl bg-card p-2 shadow-xl animate-in">
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

                    <main class="flex-1 overflow-y-auto px-2 pb-6 pt-1 lg:px-4">
                        @yield('content')
                    </main>
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
                    notify('Raw payload panel not found on this page.', 'error');
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
                { label: 'Go Settings', keywords: 'go settings power strip settings', run: function () { window.location.href = '{{ route('power-strip.settings') }}'; } },
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
            var dot = document.getElementById('live-telemetry-dot');
            var power = document.getElementById('live-telemetry-power');
            var current = document.getElementById('live-telemetry-current');
            if (!dot || !power || !current) return;

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
                dot.classList.remove('bg-red-400', 'bg-emerald-400');
                dot.classList.add(online ? 'bg-emerald-400' : 'bg-red-400');
            }

            function applyLatest(data) {
                var p = asNumber(data && data.power);
                var c = asNumber(data && data.current);
                power.textContent = p.toFixed(1) + 'W';
                current.textContent = c.toFixed(3) + 'A';
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
