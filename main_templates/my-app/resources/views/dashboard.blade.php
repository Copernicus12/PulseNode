@extends('layouts.app')

@section('title', 'Dashboard')

@push('head')
    @vite('resources/js/relay-command-toast.ts')
@endpush

@section('content')
@php
    $dashboardCurrency = (string) ($dashboardBilling['currency'] ?? 'RON');
    $dashboardPriceWithTax = (float) ($dashboardBilling['price_per_kwh_with_tax'] ?? 0);
    $dashboardProfileLabel = (string) ($dashboardBilling['profile_label'] ?? 'Current settings');
    $dashboardProfileSource = (string) ($dashboardBilling['profile_source'] ?? 'current_settings');
    $formatMoney = function (float $value) use ($dashboardCurrency): string {
        $decimals = abs($value) >= 1 ? 2 : 4;

        return number_format($value, $decimals, ',', '.') . ' ' . $dashboardCurrency;
    };
@endphp
<div class="space-y-5">
    @include('layouts._relay-command-alert', ['relayCommandGuard' => $relayCommandGuard])

    {{-- ── Row 1: Live Monitoring (full-width, like Camera CCTV) ── --}}
    <div id="dashboard-live-card" class="light-outline-strong rounded-3xl bg-card p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight">Live Monitoring</h2>
                <div class="mt-1.5 flex items-center gap-2">
                    <span id="dashboard-live-dot" class="relative inline-flex h-3.5 w-3.5 shrink-0 items-center justify-center">
                        <span id="dashboard-live-dot-ping" class="{{ $isOnline ? 'absolute inline-flex' : 'hidden' }} inset-0 animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span id="dashboard-live-dot-core" class="{{ $isOnline ? 'relative bg-emerald-500' : 'bg-red-400' }} h-2 w-2 rounded-full"></span>
                    </span>
                    <span id="dashboard-live-status-text" class="text-sm text-muted-foreground">
                        @if($isOnline)
                            {{ $activeRelays }} sockets online
                        @else
                            Offline @if($lastSeen)&middot; {{ $lastSeenAgo }}@endif
                        @endif
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    id="dashboard-help-button"
                    onclick="startGlobalFeatureTour()"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-border/40 bg-background text-sm font-bold text-foreground/80 transition hover:scale-105 hover:bg-muted/40 hover:text-foreground"
                    aria-label="Open dashboard guide"
                    title="Open dashboard guide"
                >
                    ?
                </button>
                <span id="dashboard-active-relays-badge" class="{{ $isOnline ? 'inline-flex' : 'hidden' }} w-fit items-center gap-2 rounded-full bg-primary/15 px-4 py-1.5 text-sm font-medium text-primary">
                    <span class="relative flex h-1.5 w-1.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span><span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-primary"></span></span>
                    <span id="dashboard-active-relays-count">{{ $activeRelays }}/3 active</span>
                </span>
            </div>
        </div>

        <div class="mt-7 grid grid-cols-2 gap-4 xl:grid-cols-5">
            <div class="light-outline rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Voltage</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-voltage">
                    @if($isOnline)
                        {{ $voltage }} <span class="text-sm font-normal text-muted-foreground">V</span>
                    @else
                        <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                    @endif
                </p>
            </div>
            <div class="light-outline rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Total Current</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-current">
                    @if($isOnline)
                        {{ $current }} <span class="text-sm font-normal text-muted-foreground">A</span>
                    @else
                        <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                    @endif
                </p>
            </div>
            <div class="light-outline rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Active Power</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-power">
                    @if($isOnline)
                        {{ $power }} <span class="text-sm font-normal text-muted-foreground">W</span>
                    @else
                        <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                    @endif
                </p>
            </div>
            <div class="light-outline rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Energy</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-energy">
                    @if($isOnline)
                        {{ $energy }} <span class="text-sm font-normal text-muted-foreground">kWh</span>
                    @else
                        <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                    @endif
                </p>
            </div>
            <div class="light-outline rounded-2xl bg-background p-5">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs text-muted-foreground">Today Cost</p>
                    <span id="dash-billing-source" class="rounded-full border border-border/40 px-2.5 py-1 text-[10px] font-medium uppercase tracking-[0.16em] text-muted-foreground">
                        {{ $dashboardProfileSource === 'saved_profile' ? 'saved' : 'current' }}
                    </span>
                </div>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-today-cost">
                    @if($isOnline)
                        {{ $formatMoney((float) ($dashboardBilling['day']['total_cost'] ?? 0)) }}
                    @else
                        <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                    @endif
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground" id="dash-today-cost-context">
                    {{ $isOnline ? $dashboardProfileLabel . ' · ' . $formatMoney($dashboardPriceWithTax) . '/kWh' : 'Waiting for fresh telemetry' }}
                </p>
            </div>
        </div>
    </div>

    {{-- ── Row 2: Bento grid (Socket 1 + 2 | Current Distribution + CTA) ── --}}
    <div id="dashboard-socket-grid" class="grid gap-5 lg:grid-cols-2">

        {{-- Left column --}}
        <div class="flex flex-col gap-5">

            {{-- Socket 1 --}}
            @php $s1 = $sockets[0]; $on1 = $s1['is_on']; @endphp
            <div id="dashboard-socket-1" class="light-outline-strong rounded-3xl bg-card p-7">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ $s1['label'] }}</h3>
                        <p class="text-sm text-muted-foreground">Socket 1 &middot; Relay GPIO 15</p>
                    </div>
                    <button
                        id="dashboard-socket-toggle-1"
                        data-relay-on="{{ $on1 ? '1' : '0' }}"
                        onclick="toggleRelay(1)"
                        class="flex h-11 w-11 items-center justify-center rounded-full transition {{ $on1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    </button>
                </div>
                <div class="mt-7 flex items-baseline justify-between">
                    <span id="dash-socket-current-1" class="text-sm text-muted-foreground">{{ $isOnline ? $s1['current'] . ' A' : 'Unavailable' }}</span>
                    <span id="dash-socket-power-1" class="text-2xl font-bold tabular-nums">
                        @if($isOnline)
                            {{ $s1['power'] }}<span class="ml-0.5 text-sm font-normal text-muted-foreground">W</span>
                        @else
                            <span class="text-sm font-semibold text-muted-foreground">Unavailable</span>
                        @endif
                    </span>
                </div>
                @php $hasLoad1 = $isOnline && $on1 && (abs((float) $s1['current']) >= 0.05); $pct1 = $hasLoad1 ? max(15, min(95, (abs((float) $s1['current']) / max(5, $current ?: 1)) * 100)) : 0; @endphp
                <div class="mt-5 h-11 w-full rounded-full bg-muted">
                    <div id="dash-socket-load-1" class="flex h-full items-center rounded-full px-1.5 transition-all duration-700 {{ $hasLoad1 ? 'bg-primary/15' : '' }}" style="width: max(2.75rem, {{ $pct1 }}%)">
                        <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-full shadow transition-colors duration-700 {{ $on1 ? 'bg-primary/30 ring-1 ring-primary/20' : 'bg-muted-foreground/20' }}">
                            <svg class="h-3.5 w-3.5 {{ $on1 ? 'text-primary' : 'text-muted-foreground' }} transition-colors duration-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </span>
                    </div>
                </div>
            </div>

            {{-- Socket 2 --}}
            @php $s2 = $sockets[1]; $on2 = $s2['is_on']; @endphp
            <div id="dashboard-socket-2" class="light-outline-strong rounded-3xl bg-card p-7">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ $s2['label'] }}</h3>
                        <p class="text-sm text-muted-foreground">Socket 2 &middot; Relay GPIO 16</p>
                    </div>
                    <button
                        id="dashboard-socket-toggle-2"
                        data-relay-on="{{ $on2 ? '1' : '0' }}"
                        onclick="toggleRelay(2)"
                        class="flex h-11 w-11 items-center justify-center rounded-full transition {{ $on2 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    </button>
                </div>
                <div class="mt-7 flex items-baseline justify-between">
                    <span id="dash-socket-current-2" class="text-sm text-muted-foreground">{{ $isOnline ? $s2['current'] . ' A' : 'Unavailable' }}</span>
                    <span id="dash-socket-power-2" class="text-2xl font-bold tabular-nums">
                        @if($isOnline)
                            {{ $s2['power'] }}<span class="ml-0.5 text-sm font-normal text-muted-foreground">W</span>
                        @else
                            <span class="text-sm font-semibold text-muted-foreground">Unavailable</span>
                        @endif
                    </span>
                </div>
                @php $hasLoad2 = $isOnline && $on2 && (abs((float) $s2['current']) >= 0.05); $pct2 = $hasLoad2 ? max(15, min(95, (abs((float) $s2['current']) / max(5, $current ?: 1)) * 100)) : 0; @endphp
                <div class="mt-5 h-11 w-full rounded-full bg-muted">
                    <div id="dash-socket-load-2" class="flex h-full items-center rounded-full px-1.5 transition-all duration-700 {{ $hasLoad2 ? 'bg-primary/15' : '' }}" style="width: max(2.75rem, {{ $pct2 }}%)">
                        <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-full shadow transition-colors duration-700 {{ $on2 ? 'bg-primary/30 ring-1 ring-primary/20' : 'bg-muted-foreground/20' }}">
                            <svg class="h-3.5 w-3.5 {{ $on2 ? 'text-primary' : 'text-muted-foreground' }} transition-colors duration-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </span>
                    </div>
                </div>
            </div>

            {{-- Socket 3 --}}
            @php $s3 = $sockets[2]; $on3 = $s3['is_on']; @endphp
            <div id="dashboard-socket-3" class="light-outline-strong rounded-3xl bg-card p-7">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ $s3['label'] }}</h3>
                        <p class="text-sm text-muted-foreground">Socket 3 &middot; Relay GPIO 17</p>
                    </div>
                    <button
                        id="dashboard-socket-toggle-3"
                        data-relay-on="{{ $on3 ? '1' : '0' }}"
                        onclick="toggleRelay(3)"
                        class="flex h-11 w-11 items-center justify-center rounded-full transition {{ $on3 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    </button>
                </div>
                <div class="mt-7 flex items-baseline justify-between">
                    <span id="dash-socket-current-3" class="text-sm text-muted-foreground">{{ $isOnline ? $s3['current'] . ' A' : 'Unavailable' }}</span>
                    <span id="dash-socket-power-3" class="text-2xl font-bold tabular-nums">
                        @if($isOnline)
                            {{ $s3['power'] }}<span class="ml-0.5 text-sm font-normal text-muted-foreground">W</span>
                        @else
                            <span class="text-sm font-semibold text-muted-foreground">Unavailable</span>
                        @endif
                    </span>
                </div>
                @php $hasLoad3 = $isOnline && $on3 && (abs((float) $s3['current']) >= 0.05); $pct3 = $hasLoad3 ? max(15, min(95, (abs((float) $s3['current']) / max(5, $current ?: 1)) * 100)) : 0; @endphp
                <div class="mt-5 h-11 w-full rounded-full bg-muted">
                    <div id="dash-socket-load-3" class="flex h-full items-center rounded-full px-1.5 transition-all duration-700 {{ $hasLoad3 ? 'bg-primary/15' : '' }}" style="width: max(2.75rem, {{ $pct3 }}%)">
                        <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-full shadow transition-colors duration-700 {{ $on3 ? 'bg-primary/30 ring-1 ring-primary/20' : 'bg-muted-foreground/20' }}">
                            <svg class="h-3.5 w-3.5 {{ $on3 ? 'text-primary' : 'text-muted-foreground' }} transition-colors duration-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column --}}
        <div class="flex flex-col gap-5">

            {{-- Energy Usage — interactive weekly chart --}}
            <div class="light-outline-strong flex-[1.5] rounded-3xl bg-card p-7" id="energy-usage-card">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold">Energy Usage</h3>
                        <p class="text-sm text-muted-foreground">Weekly consumption overview</p>
                    </div>
                    <div class="text-right">
                        <span class="text-lg font-bold tabular-nums" id="dash-energy-total">
                            @if($isOnline)
                                {{ $energy }} <span class="text-sm font-normal text-muted-foreground">kWh</span>
                            @else
                                <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                            @endif
                        </span>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">Instant reading</p>
                    </div>
                </div>

                @php
                    $weekEnergy = $energyUsage['week'] ?? [];
                    $todayProgress = $energyUsage['today_progress_kwh'] ?? 0;
                    $maxDayTotal = max(array_column($weekEnergy, 'total')) ?: 0.001;
                @endphp

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-2xl border border-border/30 bg-background/20 p-3 text-xs">
                        <span class="text-muted-foreground">Today (00:00 → now)</span>
                        <span id="dash-energy-today-progress" class="mt-1 block font-semibold tabular-nums text-primary">{{ number_format($todayProgress, 4) }} kWh</span>
                    </div>
                    <div class="rounded-2xl border border-border/30 bg-background/20 p-3 text-xs">
                        <span class="text-muted-foreground">Cost today</span>
                        <span id="dash-energy-day-cost" class="mt-1 block font-semibold tabular-nums text-primary">{{ $formatMoney((float) ($dashboardBilling['day']['total_cost'] ?? 0)) }}</span>
                    </div>
                    <div class="rounded-2xl border border-border/30 bg-background/20 p-3 text-xs">
                        <span class="text-muted-foreground">Active tariff</span>
                        <span id="dash-billing-profile-label" class="mt-1 block font-semibold text-foreground">{{ $dashboardProfileLabel }}</span>
                        <span id="dash-billing-rate" class="mt-1 block tabular-nums text-muted-foreground">{{ $formatMoney($dashboardPriceWithTax) }}/kWh</span>
                    </div>
                </div>

                <div class="mt-6 flex items-end gap-3" style="height: 260px" id="energy-bars-container">
                    @foreach($weekEnergy as $day)
                        @php
                            $dayTotal = (float) $day['total'];
                            $barPct = max(12, ($dayTotal / $maxDayTotal) * 100);
                        @endphp
                        <button type="button"
                                class="energy-day group flex h-full flex-1 flex-col items-center justify-end gap-3"
                                data-energy-date="{{ $day['date'] }}"
                                data-energy-day="{{ $day['day_short'] }}"
                                data-energy-total="{{ $dayTotal }}"
                                aria-label="Open details for {{ $day['day_short'] }}">
                            <span class="min-h-[2.75rem] text-center text-[12px] font-semibold leading-tight tabular-nums {{ $day['is_today'] ? 'text-primary' : 'text-foreground/85' }}" data-role="day-value">
                                {{ number_format($dayTotal, 4) }}<br><span class="text-[10px] font-medium {{ $day['is_today'] ? 'text-primary/80' : 'text-muted-foreground' }}">kWh</span>
                            </span>
                            <span class="relative block h-[150px] w-full overflow-hidden rounded-[2rem] bg-muted/35 ring-1 ring-white/4">
                                <span class="absolute inset-x-2 bottom-2 top-2 rounded-[1.6rem] bg-background/35"></span>
                                <span data-role="bar-track" class="absolute inset-x-2 bottom-2 top-2 flex items-end overflow-hidden rounded-[1.6rem]">
                                    <span data-role="bar-fill"
                                          class="block w-full rounded-[1.6rem] transition-all duration-500 {{ $day['is_today'] ? 'bg-primary shadow-[0_0_0_1px_rgba(216,228,132,0.24),0_14px_30px_rgba(216,228,132,0.18)]' : 'bg-primary/38' }}"
                                          style="height: {{ $barPct }}%"></span>
                                </span>
                                <span class="pointer-events-none absolute inset-0 rounded-[2rem] opacity-0 transition-opacity duration-200 group-hover:opacity-100 {{ $day['is_today'] ? 'bg-primary/6' : 'bg-white/[0.02]' }}"></span>
                            </span>
                            <span class="text-[13px] font-semibold tracking-[0.16em] {{ $day['is_today'] ? 'text-primary' : 'text-muted-foreground' }}">{{ $day['day_short'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Energy details modal --}}
            <div id="energy-modal" class="fixed inset-0 z-[70] hidden items-end justify-center bg-black/70 p-4 backdrop-blur-sm sm:items-center">
                <div id="energy-modal-panel" class="light-outline-strong w-full max-w-4xl scale-95 rounded-3xl bg-card p-6 opacity-0 transition-all duration-300 sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-bold">Energy Day Details</h3>
                            <p class="text-sm text-muted-foreground" id="energy-modal-subtitle">Loading...</p>
                        </div>
                        <button type="button" id="energy-modal-close" class="rounded-xl bg-muted px-3 py-1.5 text-sm text-muted-foreground transition hover:text-foreground">Close</button>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <div class="light-outline rounded-2xl bg-background p-4">
                            <p class="text-xs text-muted-foreground">Total day consumption</p>
                            <p class="mt-1 text-xl font-bold tabular-nums" id="energy-modal-total">0.0000 kWh</p>
                            <p class="mt-1 text-[11px] text-muted-foreground" id="energy-modal-interval">00:00 - 00:00</p>
                        </div>
                        <div class="light-outline rounded-2xl bg-background p-4">
                            <p class="text-xs text-muted-foreground">Warnings</p>
                            <p class="mt-1 text-xl font-bold tabular-nums"><span id="energy-modal-warning-overload">0</span> overload</p>
                            <p class="text-[11px] text-muted-foreground"><span id="energy-modal-warning-high">0</span> high load</p>
                        </div>
                        <div class="light-outline rounded-2xl bg-background p-4">
                            <p class="text-xs text-muted-foreground">Average voltage</p>
                            <p class="mt-1 text-xl font-bold tabular-nums" id="energy-modal-voltage">0.0 V</p>
                            <p class="text-[11px] text-muted-foreground">Measured from all samples</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        <div class="light-outline rounded-2xl bg-background p-4">
                            <h4 class="text-sm font-semibold">Sockets breakdown</h4>
                            <div class="mt-3 space-y-2 text-sm" id="energy-modal-sockets"></div>
                        </div>

                        <div class="light-outline rounded-2xl bg-background p-4">
                            <h4 class="text-sm font-semibold">Most active intervals</h4>
                            <div class="mt-3 space-y-2 text-sm" id="energy-modal-intervals"></div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- PulseNode CTA — like "Upgrade to Pro" (lime bg) --}}
            <div id="dashboard-cta-card" class="rounded-3xl bg-primary p-7 text-primary-foreground">
                <h3 class="text-lg font-bold">PulseNode</h3>
                <p class="mt-1.5 text-sm opacity-70">ESP32 Smart Power Strip</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button onclick="toggleAllRelays(true)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All On</button>
                    <button onclick="toggleAllRelays(false)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All Off</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 3: Safety alert (only when needed) ── --}}
    @if($safetyLevel !== 'normal')
        <div class="rounded-3xl {{ $safetyLevel === 'overload' ? 'bg-red-500/15 text-red-400' : 'bg-amber-500/15 text-amber-400' }} p-6">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <p class="font-semibold">{{ $safetyLevel === 'overload' ? 'Overload detected!' : 'High load detected' }}</p>
                    <p class="text-sm opacity-70">{{ $safetyLevel === 'overload' ? 'Consumption over 2500W — overheating risk' : 'Consumption over 1800W — approaching the limit' }}</p>
                </div>
            </div>
        </div>
    @endif

</div>

<script>
var dashboardRelayState = {
    1: {{ $on1 ? 'true' : 'false' }},
    2: {{ $on2 ? 'true' : 'false' }},
    3: {{ $on3 ? 'true' : 'false' }},
};
var dashboardBillingState = @json($dashboardBilling);
var dashboardLiveIsOnline = @json($isOnline);
var dashboardOfflineAfterMs = {{ max(30, (int) config('esp32.connection.offline_after_seconds', 300)) * 1000 }};
var energyHistoryRefreshTimer = null;

function dashboardIsFresh(data) {
    if (!data || !data.updated_at) return false;
    var timestamp = Date.parse(data.updated_at);
    return Number.isFinite(timestamp) && (Date.now() - timestamp) <= dashboardOfflineAfterMs;
}

function dashboardUnavailableHtml() {
    return '<span class="text-base font-semibold text-muted-foreground">Unavailable</span>';
}

function dashboardSocketUnavailableHtml() {
    return '<span class="text-sm font-semibold text-muted-foreground">Unavailable</span>';
}

function dashboardUnit(unit) {
    return ' <span class="text-sm font-normal text-muted-foreground">' + unit + '</span>';
}

function displayCurrent(value) {
    var current = Number(value ?? 0);
    if (!Number.isFinite(current)) current = 0;
    return Math.abs(current) < 0.05 ? 0 : current;
}

function setDashboardSocketLoad(idx, active, currentValue, totalCurrent) {
    var load = document.getElementById('dash-socket-load-' + idx);
    if (!load) return;

    load.classList.toggle('bg-primary/15', !!active);

    var pct = 0;
    if (active) {
        pct = Math.max(15, Math.min(95, (Math.abs(currentValue) / Math.max(5, Math.abs(totalCurrent) || 1)) * 100));
    }

    load.style.width = 'max(2.75rem, ' + pct + '%)';
}

function dashboardLastSeenLabel(updatedAt) {
    if (!updatedAt) return '';
    var timestamp = Date.parse(updatedAt);
    if (!Number.isFinite(timestamp)) return '';

    var diffSeconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
    if (diffSeconds < 60) return diffSeconds + ' sec ago';
    if (diffSeconds < 3600) return Math.floor(diffSeconds / 60) + ' min ago';
    if (diffSeconds < 86400) return Math.floor(diffSeconds / 3600) + ' h ago';
    if (diffSeconds < 604800) return Math.floor(diffSeconds / 86400) + ' d ago';

    var weeks = Math.floor(diffSeconds / 604800);
    return weeks + ' week' + (weeks === 1 ? '' : 's') + ' ago';
}

function setDashboardLiveStatus(online, data) {
    dashboardLiveIsOnline = !!online;

    var dot = document.getElementById('dashboard-live-dot');
    var ping = document.getElementById('dashboard-live-dot-ping');
    var core = document.getElementById('dashboard-live-dot-core');
    var label = document.getElementById('dashboard-live-status-text');
    var badge = document.getElementById('dashboard-active-relays-badge');
    var count = document.getElementById('dashboard-active-relays-count');

    if (dot) {
        dot.className = 'relative inline-flex h-3.5 w-3.5 shrink-0 items-center justify-center';
    }

    if (ping) {
        ping.classList.toggle('hidden', !online);
        ping.classList.toggle('absolute', online);
        ping.classList.toggle('inline-flex', online);
    }

    if (core) {
        core.classList.remove('bg-emerald-500', 'bg-red-400');
        core.classList.add(online ? 'bg-emerald-500' : 'bg-red-400');
    }

    if (label) {
        if (online) {
            var active = [1, 2, 3].filter(function(idx) {
                return Boolean(data && data['relay_' + idx]);
            }).length;
            label.textContent = active + ' sockets online';
            if (count) count.textContent = active + '/3 active';
        } else {
            var lastSeen = dashboardLastSeenLabel(data && data.updated_at);
            label.textContent = 'Offline' + (lastSeen ? ' · ' + lastSeen : '');
        }
    }

    if (badge) {
        badge.classList.toggle('hidden', !online);
        badge.classList.toggle('inline-flex', online);
    }
}

function renderDashboardLiveUnavailable(data) {
    setDashboardLiveStatus(false, data || {});

    ['dash-voltage', 'dash-current', 'dash-power', 'dash-energy', 'dash-energy-total', 'dash-today-cost'].forEach(function(id) {
        var item = document.getElementById(id);
        if (item) item.innerHTML = dashboardUnavailableHtml();
    });

    [1, 2, 3].forEach(function(idx) {
        var currentEl = document.getElementById('dash-socket-current-' + idx);
        var powerEl = document.getElementById('dash-socket-power-' + idx);

        if (currentEl) currentEl.textContent = 'Unavailable';
        if (powerEl) powerEl.innerHTML = dashboardSocketUnavailableHtml();
        setDashboardSocketLoad(idx, false, 0, 0);
    });

    var contextEl = document.getElementById('dash-today-cost-context');
    if (contextEl) contextEl.textContent = 'Waiting for fresh telemetry';
}

function formatDashboardMoney(value, currency) {
    try {
        return new Intl.NumberFormat('ro-RO', {
            style: 'currency',
            currency: currency || 'RON',
            minimumFractionDigits: Math.abs(value) >= 1 ? 2 : 4,
            maximumFractionDigits: Math.abs(value) >= 1 ? 2 : 4,
        }).format(value || 0);
    } catch (error) {
        var amount = Number(value || 0);
        var decimals = Math.abs(amount) >= 1 ? 2 : 4;
        return amount.toFixed(decimals) + ' ' + (currency || 'RON');
    }
}

function setDashboardToggleState(idx, isOn, pending) {
    var button = document.getElementById('dashboard-socket-toggle-' + idx);
    if (!button) return;
    button.dataset.relayOn = isOn ? '1' : '0';
    button.disabled = !!pending;
    button.classList.remove('bg-primary', 'text-primary-foreground', 'bg-muted', 'text-muted-foreground', 'opacity-60', 'cursor-not-allowed');
    button.classList.add(isOn ? 'bg-primary' : 'bg-muted');
    button.classList.add(isOn ? 'text-primary-foreground' : 'text-muted-foreground');
    if (pending) button.classList.add('opacity-60', 'cursor-not-allowed');
    button.title = isOn ? 'Turn off Socket ' + idx : 'Turn on Socket ' + idx;
}

function publishLatestSnapshot(data) {
    if (!data) return;
    window.__pulsenodeLatest = data;
    window.dispatchEvent(new CustomEvent('pulsenode:latest', { detail: data }));
}

function applyDashboardBilling(billingSummary) {
    if (!billingSummary) return;

    dashboardBillingState = billingSummary;

    var currency = billingSummary.currency || 'RON';
    var totalCost = parseFloat((billingSummary.day && billingSummary.day.total_cost) || 0);
    var priceWithTax = parseFloat(billingSummary.price_per_kwh_with_tax || 0);
    var profileLabel = billingSummary.profile_label || 'Current settings';
    var profileSource = billingSummary.profile_source === 'saved_profile' ? 'saved' : 'current';

    var todayCostEl = document.getElementById('dash-today-cost');
    var dayCostEl = document.getElementById('dash-energy-day-cost');
    var rateEl = document.getElementById('dash-billing-rate');
    var profileLabelEl = document.getElementById('dash-billing-profile-label');
    var profileSourceEl = document.getElementById('dash-billing-source');
    var contextEl = document.getElementById('dash-today-cost-context');

    if (todayCostEl && dashboardLiveIsOnline) todayCostEl.textContent = formatDashboardMoney(totalCost, currency);
    if (dayCostEl) dayCostEl.textContent = formatDashboardMoney(totalCost, currency);
    if (rateEl) rateEl.textContent = formatDashboardMoney(priceWithTax, currency) + '/kWh';
    if (profileLabelEl) profileLabelEl.textContent = profileLabel;
    if (profileSourceEl) profileSourceEl.textContent = profileSource;
    if (contextEl && dashboardLiveIsOnline) contextEl.textContent = profileLabel + ' · ' + formatDashboardMoney(priceWithTax, currency) + '/kWh';
}

function sendRelayCommand(idx, turnOn) {
    if (window.pulsenodeEnsureRelayCommandAllowed && !window.pulsenodeEnsureRelayCommandAllowed(turnOn)) {
        var blockedGuard = window.__pulsenodeRelayCommandGuard || {};
        return Promise.reject(new Error(blockedGuard.message || 'Socket power-on is unavailable right now.'));
    }

    return fetch('/api/relay/' + idx + '/' + (turnOn ? 'on' : 'off'), { credentials: 'same-origin' })
        .then(function(response) {
            return response.json().catch(function() {
                return {};
            }).then(function(payload) {
                if (!response.ok) {
                    if (payload && payload.guard && window.pulsenodeSetRelayCommandGuard) {
                        window.pulsenodeSetRelayCommandGuard(payload.guard);
                    }
                    if (payload && payload.guard && window.pulsenodeShowRelayCommandNotification) {
                        window.pulsenodeShowRelayCommandNotification(payload.message, payload.guard);
                    }

                    var error = new Error((payload && payload.message) || 'Relay command failed');
                    error.payload = payload;
                    throw error;
                }

                return payload;
            });
        });
}

function toggleRelay(idx, turnOn) {
    var desiredState = typeof turnOn === 'boolean' ? turnOn : !dashboardRelayState[idx];
    setDashboardToggleState(idx, dashboardRelayState[idx], true);
    sendRelayCommand(idx, desiredState)
        .then(function(payload) {
            dashboardRelayState[idx] = desiredState;
            setDashboardToggleState(idx, desiredState, false);
            if (payload && payload.latest) publishLatestSnapshot(payload.latest);
        })
        .catch(function(e) {
            setDashboardToggleState(idx, dashboardRelayState[idx], false);
            console.error('Relay error', e);
        });
}

function toggleAllRelays(turnOn) {
    if (turnOn && window.pulsenodeEnsureRelayCommandAllowed && !window.pulsenodeEnsureRelayCommandAllowed(true)) {
        return;
    }

    setDashboardToggleState(1, dashboardRelayState[1], true);
    setDashboardToggleState(2, dashboardRelayState[2], true);
    setDashboardToggleState(3, dashboardRelayState[3], true);
    Promise.all([
        sendRelayCommand(1, turnOn),
        sendRelayCommand(2, turnOn),
        sendRelayCommand(3, turnOn)
    ]).then(function(payloads) {
        dashboardRelayState[1] = turnOn;
        dashboardRelayState[2] = turnOn;
        dashboardRelayState[3] = turnOn;
        setDashboardToggleState(1, turnOn, false);
        setDashboardToggleState(2, turnOn, false);
        setDashboardToggleState(3, turnOn, false);
        var latest = payloads.length ? (payloads[payloads.length - 1].latest || null) : null;
        if (latest) publishLatestSnapshot(latest);
    }).catch(function(e) {
        setDashboardToggleState(1, dashboardRelayState[1], false);
        setDashboardToggleState(2, dashboardRelayState[2], false);
        setDashboardToggleState(3, dashboardRelayState[3], false);
        console.error('Toggle all error', e);
    });
}
var energyState = @json($energyUsage);

function kwh(value) {
    return (parseFloat(value || 0)).toFixed(4) + ' kWh';
}

function renderEnergyBars(payload) {
    var week = (payload && payload.week) ? payload.week : [];
    var todayProgress = parseFloat((payload && payload.today_progress_kwh) || 0);
    var bars = document.querySelectorAll('.energy-day');
    var maxTotal = 0.001;

    week.forEach(function(day) {
        var total = parseFloat(day.total || 0);
        if (total > maxTotal) maxTotal = total;
    });

    bars.forEach(function(bar, index) {
        var day = week[index];
        if (!day) return;

        var total = parseFloat(day.total || 0);
        var pct = Math.max(12, (total / maxTotal) * 100);

        bar.dataset.energyDate = day.date;
        bar.dataset.energyTotal = String(total);

        var valueEl = bar.querySelector('[data-role="day-value"]');
        if (valueEl) {
            valueEl.innerHTML = Number(total).toFixed(4) + '<br><span class="text-[10px] font-medium ' + (day.is_today ? 'text-primary/80' : 'text-muted-foreground') + '">kWh</span>';
            valueEl.className = 'min-h-[2.75rem] text-center text-[12px] font-semibold leading-tight tabular-nums ' + (day.is_today ? 'text-primary' : 'text-foreground/85');
        }

        var fill = bar.querySelector('[data-role="bar-fill"]');
        if (fill) {
            fill.style.height = pct + '%';
            fill.className = 'block w-full rounded-[1.6rem] transition-all duration-500 ' +
                (day.is_today
                    ? 'bg-primary shadow-[0_0_0_1px_rgba(216,228,132,0.24),0_14px_30px_rgba(216,228,132,0.18)]'
                    : 'bg-primary/38');
        }
    });

    var todayEl = document.getElementById('dash-energy-today-progress');
    if (todayEl) todayEl.textContent = kwh(todayProgress);

    if (payload && payload.billingSummary) {
        applyDashboardBilling(payload.billingSummary);
    }
}

function populateEnergyModal(data) {
    var subtitle = document.getElementById('energy-modal-subtitle');
    var total = document.getElementById('energy-modal-total');
    var interval = document.getElementById('energy-modal-interval');
    var warningHigh = document.getElementById('energy-modal-warning-high');
    var warningOverload = document.getElementById('energy-modal-warning-overload');
    var voltage = document.getElementById('energy-modal-voltage');
    var sockets = document.getElementById('energy-modal-sockets');
    var intervals = document.getElementById('energy-modal-intervals');

    if (subtitle) subtitle.textContent = data.day_short + ' · ' + data.date;
    if (total) total.textContent = kwh(data.total_kwh || 0);
    if (interval) interval.textContent = data.from_time + ' - ' + data.to_time;
    if (warningHigh) warningHigh.textContent = String((data.warnings && data.warnings.high) || 0);
    if (warningOverload) warningOverload.textContent = String((data.warnings && data.warnings.overload) || 0);
    if (voltage) voltage.textContent = (parseFloat(data.avg_voltage || 0)).toFixed(1) + ' V';

    if (sockets) {
        sockets.innerHTML = (data.socket_stats || []).map(function(item) {
            return '<div class="rounded-xl bg-card px-3 py-2 ring-1 ring-border/20">'
                + '<div class="flex items-center justify-between"><span class="font-medium">' + item.name + '</span><span class="tabular-nums">' + kwh(item.energy_kwh) + '</span></div>'
                + '<div class="mt-1 text-xs text-muted-foreground">'
                + item.percentage + '% · Avg ' + Number(item.avg_power_w || 0).toFixed(1) + 'W · Peak ' + Number(item.peak_power_w || 0).toFixed(1) + 'W · Active ' + Number(item.active_minutes || 0).toFixed(1) + ' min'
                + '</div>'
                + '</div>';
        }).join('');
    }

    if (intervals) {
        var list = data.intervals || [];
        if (list.length === 0) {
            intervals.innerHTML = '<p class="text-xs text-muted-foreground">No significant active interval for this day.</p>';
        } else {
            intervals.innerHTML = list.map(function(item) {
                return '<div class="rounded-xl bg-card px-3 py-2 ring-1 ring-border/20">'
                    + '<div class="flex items-center justify-between text-xs"><span>' + item.start + ' - ' + item.end + '</span><span>' + item.duration_minutes + ' min</span></div>'
                    + '<div class="mt-1 text-xs text-muted-foreground">' + kwh(item.energy_kwh) + ' · Avg ' + Number(item.avg_power_w || 0).toFixed(1) + 'W</div>'
                    + '</div>';
            }).join('');
        }
    }

}

function openEnergyModal(date, trigger) {
    var modal = document.getElementById('energy-modal');
    var panel = document.getElementById('energy-modal-panel');
    if (!modal || !panel) return;

    // Small zoom-in effect from clicked bar
    if (trigger) {
        var fill = trigger.querySelector('[data-role="bar-fill"]');
        if (fill) {
            fill.classList.add('scale-105');
            setTimeout(function() { fill.classList.remove('scale-105'); }, 220);
        }
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(function() {
        panel.classList.remove('scale-95', 'opacity-0');
    });

    fetch('/api/energy-day/' + encodeURIComponent(date), { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) { populateEnergyModal(data); })
        .catch(function() {
            populateEnergyModal({
                date: date,
                day_short: 'DAY',
                total_kwh: 0,
                from_time: '00:00',
                to_time: '00:00',
                avg_voltage: 0,
                warnings: { high: 0, overload: 0 },
                socket_stats: [],
                intervals: [],
                hourly: [],
            });
        });
}

function closeEnergyModal() {
    var modal = document.getElementById('energy-modal');
    var panel = document.getElementById('energy-modal-panel');
    if (!modal || !panel) return;

    panel.classList.add('scale-95', 'opacity-0');
    setTimeout(function() {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }, 220);
}

function bindEnergyBars() {
    document.querySelectorAll('.energy-day').forEach(function(bar) {
        bar.addEventListener('click', function() {
            openEnergyModal(bar.dataset.energyDate, bar);
        });
    });

    var closeBtn = document.getElementById('energy-modal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeEnergyModal);
    }

    var modal = document.getElementById('energy-modal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeEnergyModal();
            }
        });
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeEnergyModal();
        }
    });
}

function refreshEnergyHistory() {
    fetch('/api/energy-history', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(payload) {
            energyState = payload;
            renderEnergyBars(payload);
        })
        .catch(function() {});
}

function scheduleEnergyHistoryRefresh(delay) {
    if (energyHistoryRefreshTimer) {
        window.clearTimeout(energyHistoryRefreshTimer);
    }

    energyHistoryRefreshTimer = window.setTimeout(function() {
        energyHistoryRefreshTimer = null;
        refreshEnergyHistory();
    }, typeof delay === 'number' ? delay : 1500);
}

renderEnergyBars(energyState);
bindEnergyBars();

function applyLatestDashboard(d) {
    if (!dashboardIsFresh(d)) {
        renderDashboardLiveUnavailable(d);
        return;
    }

    setDashboardLiveStatus(true, d);

    var el = function(id) { return document.getElementById(id); };
    if (el('dash-voltage')) el('dash-voltage').innerHTML = parseFloat(d.voltage || 0).toFixed(1) + dashboardUnit('V');
    if (el('dash-current')) el('dash-current').innerHTML = displayCurrent(d.current).toFixed(3) + dashboardUnit('A');
    if (el('dash-power')) el('dash-power').innerHTML = Math.max(0, parseFloat(d.power || 0)).toFixed(1) + dashboardUnit('W');
    if (el('dash-energy')) el('dash-energy').innerHTML = parseFloat(d.energy || 0).toFixed(4) + dashboardUnit('kWh');
    if (el('dash-energy-total')) el('dash-energy-total').innerHTML = parseFloat(d.energy || 0).toFixed(4) + dashboardUnit('kWh');
    applyDashboardBilling(dashboardBillingState);

    var totalCurrent = 0;

    [1, 2, 3].forEach(function(idx) {
        var relayOn = Boolean(d['relay_' + idx]);
        dashboardRelayState[idx] = relayOn;
        setDashboardToggleState(idx, relayOn, false);

        var current = displayCurrent(d['current_' + idx]);
        var power = Math.max(0, parseFloat(d['power_' + idx] || 0));
        totalCurrent += current;

        var currentEl = el('dash-socket-current-' + idx);
        if (currentEl) currentEl.textContent = current.toFixed(3) + ' A';

        var powerEl = el('dash-socket-power-' + idx);
        if (powerEl) powerEl.innerHTML = power.toFixed(1) + dashboardUnit('W');

        setDashboardSocketLoad(idx, relayOn && Math.abs(current) >= 0.05, current, totalCurrent);
    });
}

window.addEventListener('pulsenode:relay-guard', function() {
    setDashboardToggleState(1, dashboardRelayState[1], false);
    setDashboardToggleState(2, dashboardRelayState[2], false);
    setDashboardToggleState(3, dashboardRelayState[3], false);
});

window.addEventListener('pulsenode:latest', function(event) {
    applyLatestDashboard(event.detail || {});
    scheduleEnergyHistoryRefresh(1500);
});

setDashboardToggleState(1, dashboardRelayState[1], false);
setDashboardToggleState(2, dashboardRelayState[2], false);
setDashboardToggleState(3, dashboardRelayState[3], false);

if (window.__pulsenodeLatest) {
    applyLatestDashboard(window.__pulsenodeLatest);
}

setInterval(refreshEnergyHistory, 20000);
</script>
@endsection
