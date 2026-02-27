@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-5">

    {{-- ── Row 1: Live Monitoring (full-width, like Camera CCTV) ── --}}
    <div class="rounded-3xl bg-card p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight">Live Monitoring</h2>
                <div class="mt-1.5 flex items-center gap-2">
                    @if($isOnline)
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        <span class="text-sm text-muted-foreground">{{ $activeRelays }} sockets online</span>
                    @else
                        <span class="h-2 w-2 rounded-full bg-red-400"></span>
                        <span class="text-sm text-muted-foreground">Offline @if($lastSeen)&middot; {{ $lastSeenAgo }}@endif</span>
                    @endif
                </div>
            </div>
            @if($isOnline)
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-primary/15 px-4 py-1.5 text-sm font-medium text-primary">
                    <span class="relative flex h-1.5 w-1.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span><span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-primary"></span></span>
                    {{ $activeRelays }}/3 active
                </span>
            @endif
        </div>

        <div class="mt-7 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Voltage</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-voltage">{{ $voltage }} <span class="text-sm font-normal text-muted-foreground">V</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Total Current</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-current">{{ $current }} <span class="text-sm font-normal text-muted-foreground">A</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Active Power</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-power">{{ $power }} <span class="text-sm font-normal text-muted-foreground">W</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Energy</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="dash-energy">{{ $energy }} <span class="text-sm font-normal text-muted-foreground">kWh</span></p>
            </div>
        </div>
    </div>

    {{-- ── Row 2: Bento grid (Socket 1 + 2 | Current Distribution + CTA) ── --}}
    <div class="grid gap-5 lg:grid-cols-2">

        {{-- Left column --}}
        <div class="flex flex-col gap-5">

            {{-- Socket 1 — tall card like "Smart Lamp" --}}
            @php $s1 = $sockets[0]; $on1 = $s1['is_on']; @endphp
            <div id="dashboard-socket-1" class="flex-1 rounded-3xl bg-card p-7">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ $s1['label'] }}</h3>
                        <p class="text-sm text-muted-foreground">Socket 1 &middot; Relay GPIO 15</p>
                    </div>
                    <button onclick="toggleRelay(1, {{ $on1 ? 'false' : 'true' }})" class="flex h-11 w-11 items-center justify-center rounded-full transition {{ $on1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    </button>
                </div>
                <div class="mt-7 flex items-baseline justify-between">
                    <span class="text-sm text-muted-foreground">{{ $s1['current'] }} A</span>
                    <span class="text-2xl font-bold tabular-nums">{{ $s1['power'] }}<span class="ml-0.5 text-sm font-normal text-muted-foreground">W</span></span>
                </div>
                @php $hasLoad1 = $on1 && ((float) $s1['current'] > 0.01); $pct1 = $hasLoad1 ? max(15, min(95, ($s1['current'] / max(5, $current ?: 1)) * 100)) : 0; @endphp
                <div class="mt-5 h-11 w-full rounded-full bg-muted">
                    <div class="flex h-full items-center rounded-full px-1.5 transition-all duration-700 {{ $hasLoad1 ? 'bg-primary/15' : '' }}" style="width: max(2.75rem, {{ $pct1 }}%)">
                        <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-full shadow transition-colors duration-700 {{ $on1 ? 'bg-primary/30 ring-1 ring-primary/20' : 'bg-muted-foreground/20' }}">
                            <svg class="h-3.5 w-3.5 {{ $on1 ? 'text-primary' : 'text-muted-foreground' }} transition-colors duration-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </span>
                    </div>
                </div>
            </div>

            {{-- Socket 2 --}}
            @php $s2 = $sockets[1]; $on2 = $s2['is_on']; @endphp
            <div id="dashboard-socket-2" class="rounded-3xl bg-card p-7">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ $s2['label'] }}</h3>
                        <p class="text-sm text-muted-foreground">Socket 2 &middot; Relay GPIO 16</p>
                    </div>
                    <button onclick="toggleRelay(2, {{ $on2 ? 'false' : 'true' }})" class="flex h-11 w-11 items-center justify-center rounded-full transition {{ $on2 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    </button>
                </div>
                <div class="mt-7 flex items-baseline justify-between">
                    <span class="text-sm text-muted-foreground">{{ $s2['current'] }} A</span>
                    <span class="text-2xl font-bold tabular-nums">{{ $s2['power'] }}<span class="ml-0.5 text-sm font-normal text-muted-foreground">W</span></span>
                </div>
                @php $hasLoad2 = $on2 && ((float) $s2['current'] > 0.01); $pct2 = $hasLoad2 ? max(15, min(95, ($s2['current'] / max(5, $current ?: 1)) * 100)) : 0; @endphp
                <div class="mt-5 h-11 w-full rounded-full bg-muted">
                    <div class="flex h-full items-center rounded-full px-1.5 transition-all duration-700 {{ $hasLoad2 ? 'bg-primary/15' : '' }}" style="width: max(2.75rem, {{ $pct2 }}%)">
                        <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-full shadow transition-colors duration-700 {{ $on2 ? 'bg-primary/30 ring-1 ring-primary/20' : 'bg-muted-foreground/20' }}">
                            <svg class="h-3.5 w-3.5 {{ $on2 ? 'text-primary' : 'text-muted-foreground' }} transition-colors duration-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right column --}}
        <div class="flex flex-col gap-5">

            {{-- Energy Usage — interactive weekly chart --}}
            <div class="flex-[1.5] rounded-3xl bg-card p-7" id="energy-usage-card">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold">Energy Usage</h3>
                        <p class="text-sm text-muted-foreground">Average value for the last day</p>
                    </div>
                    <div class="text-right">
                        <span class="text-lg font-bold tabular-nums" id="dash-energy-total">{{ $energy }} <span class="text-sm font-normal text-muted-foreground">kWh</span></span>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">Instant reading</p>
                    </div>
                </div>

                @php
                    $weekEnergy = $energyUsage['week'] ?? [];
                    $todayProgress = $energyUsage['today_progress_kwh'] ?? 0;
                    $maxDayTotal = max(array_column($weekEnergy, 'total')) ?: 0.001;
                @endphp

                <div class="mt-4 rounded-2xl border border-border/30 bg-background/20 p-3 text-xs">
                    <span class="text-muted-foreground">Today (00:00 → now)</span>
                    <span id="dash-energy-today-progress" class="ml-2 font-semibold tabular-nums text-primary">{{ number_format($todayProgress, 4) }} kWh</span>
                </div>

                <div class="mt-6 flex items-end gap-3" style="height: 210px" id="energy-bars-container">
                    @foreach($weekEnergy as $day)
                        @php
                            $dayTotal = (float) $day['total'];
                            $barPct = max(12, ($dayTotal / $maxDayTotal) * 100);
                        @endphp
                        <button type="button"
                                class="energy-day group flex flex-1 flex-col items-center gap-2"
                                data-energy-date="{{ $day['date'] }}"
                                data-energy-day="{{ $day['day_short'] }}"
                                data-energy-total="{{ $dayTotal }}"
                                aria-label="Open details for {{ $day['day_short'] }}">
                            <span class="h-5 text-[11px] font-medium tabular-nums {{ $day['is_today'] ? 'text-primary' : 'text-muted-foreground' }}" data-role="day-value">
                                {{ $day['is_today'] ? number_format($dayTotal, 4).' kWh' : '' }}
                            </span>
                            <span class="relative block h-[145px] w-full rounded-3xl bg-muted/50 p-1">
                                <span data-role="bar-fill"
                                      class="absolute inset-x-1 bottom-1 rounded-[22px] transition-all duration-500 {{ $day['is_today'] ? 'bg-primary/90 shadow-[0_0_0_1px_rgba(220,245,170,0.2)]' : 'bg-muted' }}"
                                      style="height: {{ $barPct }}%"></span>
                                <span class="pointer-events-none absolute inset-0 rounded-3xl opacity-0 transition-opacity duration-200 group-hover:opacity-100 {{ $day['is_today'] ? 'bg-primary/5' : 'bg-white/[0.02]' }}"></span>
                            </span>
                            <span class="text-[11px] font-medium {{ $day['is_today'] ? 'text-primary' : 'text-muted-foreground' }}">{{ $day['day_short'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Energy details modal --}}
            <div id="energy-modal" class="fixed inset-0 z-[70] hidden items-end justify-center bg-black/70 p-4 backdrop-blur-sm sm:items-center">
                <div id="energy-modal-panel" class="w-full max-w-4xl scale-95 rounded-3xl bg-card p-6 opacity-0 transition-all duration-300 sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-bold">Energy Day Details</h3>
                            <p class="text-sm text-muted-foreground" id="energy-modal-subtitle">Loading...</p>
                        </div>
                        <button type="button" id="energy-modal-close" class="rounded-xl bg-muted px-3 py-1.5 text-sm text-muted-foreground transition hover:text-foreground">Close</button>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl bg-background p-4">
                            <p class="text-xs text-muted-foreground">Total day consumption</p>
                            <p class="mt-1 text-xl font-bold tabular-nums" id="energy-modal-total">0.0000 kWh</p>
                            <p class="mt-1 text-[11px] text-muted-foreground" id="energy-modal-interval">00:00 - 00:00</p>
                        </div>
                        <div class="rounded-2xl bg-background p-4">
                            <p class="text-xs text-muted-foreground">Warnings</p>
                            <p class="mt-1 text-xl font-bold tabular-nums"><span id="energy-modal-warning-overload">0</span> overload</p>
                            <p class="text-[11px] text-muted-foreground"><span id="energy-modal-warning-high">0</span> high load</p>
                        </div>
                        <div class="rounded-2xl bg-background p-4">
                            <p class="text-xs text-muted-foreground">Average voltage</p>
                            <p class="mt-1 text-xl font-bold tabular-nums" id="energy-modal-voltage">0.0 V</p>
                            <p class="text-[11px] text-muted-foreground">Measured from all samples</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl bg-background p-4">
                            <h4 class="text-sm font-semibold">Sockets breakdown</h4>
                            <div class="mt-3 space-y-2 text-sm" id="energy-modal-sockets"></div>
                        </div>

                        <div class="rounded-2xl bg-background p-4">
                            <h4 class="text-sm font-semibold">Most active intervals</h4>
                            <div class="mt-3 space-y-2 text-sm" id="energy-modal-intervals"></div>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl bg-background p-4">
                        <h4 class="text-sm font-semibold">Hourly energy / power</h4>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" id="energy-modal-hourly"></div>
                    </div>
                </div>
            </div>

            {{-- PulseNode CTA — like "Upgrade to Pro" (lime bg) --}}
            <div class="rounded-3xl bg-primary p-7 text-primary-foreground">
                <h3 class="text-lg font-bold">PulseNode</h3>
                <p class="mt-1.5 text-sm opacity-70">ESP32 Smart Power Strip</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button onclick="toggleAllRelays(true)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All On</button>
                    <button onclick="toggleAllRelays(false)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All Off</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 3: Socket 3 + Hardware info ── --}}
    <div class="grid gap-5 lg:grid-cols-2">

        {{-- Socket 3 --}}
        @php $s3 = $sockets[2]; $on3 = $s3['is_on']; @endphp
        <div id="dashboard-socket-3" class="rounded-3xl bg-card p-7">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-bold">{{ $s3['label'] }}</h3>
                    <p class="text-sm text-muted-foreground">Socket 3 &middot; Relay GPIO 17</p>
                </div>
                <button onclick="toggleRelay(3, {{ $on3 ? 'false' : 'true' }})" class="flex h-11 w-11 items-center justify-center rounded-full transition {{ $on3 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                </button>
            </div>
            <div class="mt-7 flex items-baseline justify-between">
                <span class="text-sm text-muted-foreground">{{ $s3['current'] }} A</span>
                <span class="text-2xl font-bold tabular-nums">{{ $s3['power'] }}<span class="ml-0.5 text-sm font-normal text-muted-foreground">W</span></span>
            </div>
            @php $hasLoad3 = $on3 && ((float) $s3['current'] > 0.01); $pct3 = $hasLoad3 ? max(15, min(95, ($s3['current'] / max(5, $current ?: 1)) * 100)) : 0; @endphp
            <div class="mt-5 h-11 w-full rounded-full bg-muted">
                <div class="flex h-full items-center rounded-full px-1.5 transition-all duration-700 {{ $hasLoad3 ? 'bg-primary/15' : '' }}" style="width: max(2.75rem, {{ $pct3 }}%)">
                    <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-full shadow transition-colors duration-700 {{ $on3 ? 'bg-primary/30 ring-1 ring-primary/20' : 'bg-muted-foreground/20' }}">
                        <svg class="h-3.5 w-3.5 {{ $on3 ? 'text-primary' : 'text-muted-foreground' }} transition-colors duration-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </span>
                </div>
            </div>
        </div>

        {{-- Hardware --}}
        <div class="rounded-3xl bg-card p-7">
            <h3 class="text-lg font-bold">Device</h3>
            <p class="text-sm text-muted-foreground">Hardware specifications</p>
            <div class="mt-5 space-y-0">
                @php
                    $specs = [
                        ['MCU',      'ESP32-WROOM-32'],
                        ['Voltage',  'ZMPT101B'],
                        ['Current',  'ACS712 &times; 3'],
                        ['Relays',   '3 &times; 230V / 16A'],
                        ['Protocol', 'MQTT'],
                        ['Broker',   config('mqtt.host', 'broker.hivemq.com')],
                    ];
                @endphp
                @foreach($specs as [$k, $v])
                    <div class="flex items-center justify-between border-b border-border/20 py-3 text-sm last:border-0">
                        <span class="text-muted-foreground">{{ $k }}</span>
                        <span class="font-medium">{!! $v !!}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Row 4: Safety alert (only when needed) ── --}}
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

    {{-- ── JSON payload (collapsible) ── --}}
    <details class="group rounded-3xl bg-card">
        <summary class="flex cursor-pointer select-none items-center justify-between px-7 py-5 text-sm text-muted-foreground transition hover:text-foreground [&::-webkit-details-marker]:hidden">
            <span class="font-medium">JSON Payload &middot; Raw data</span>
            <svg class="h-4 w-4 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="border-t border-border/20 px-7 py-6 space-y-5">
            <pre class="overflow-x-auto rounded-2xl bg-background p-5 text-[11px] font-mono leading-relaxed text-foreground/70"><code id="dash-raw-json">{{ json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
            <div class="grid gap-x-8 gap-y-1.5 text-xs sm:grid-cols-2">
                <div class="flex justify-between py-1 text-muted-foreground"><span>Data topic</span><span class="font-mono text-[11px]">{{ config('mqtt.topics.data', 'razvy_esp32_2026/data') }}</span></div>
                <div class="flex justify-between py-1 text-muted-foreground"><span>Command topic</span><span class="font-mono text-[11px]">{{ config('mqtt.topics.cmd', 'razvy_esp32_2026/cmd') }}</span></div>
                <div class="flex justify-between py-1 text-muted-foreground"><span>Publish interval</span><span>10s</span></div>
                <div class="flex justify-between py-1 text-muted-foreground"><span>Dashboard poll</span><span>5s</span></div>
            </div>
        </div>
    </details>

</div>

<script>
function toggleRelay(idx, turnOn) {
    fetch('/api/relay/' + idx + '/' + (turnOn ? 'on' : 'off'), { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function() { location.reload(); })
        .catch(function(e) { console.error('Relay error', e); });
}
function toggleAllRelays(turnOn) {
    var s = turnOn ? 'on' : 'off';
    Promise.all([
        fetch('/api/relay/1/' + s, { credentials: 'same-origin' }),
        fetch('/api/relay/2/' + s, { credentials: 'same-origin' }),
        fetch('/api/relay/3/' + s, { credentials: 'same-origin' })
    ]).then(function() { location.reload(); })
      .catch(function(e) { console.error('Toggle all error', e); });
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
            valueEl.textContent = day.is_today ? kwh(total) : '';
            valueEl.className = 'h-5 text-[11px] font-medium tabular-nums ' + (day.is_today ? 'text-primary' : 'text-muted-foreground');
        }

        var fill = bar.querySelector('[data-role="bar-fill"]');
        if (fill) {
            fill.style.height = pct + '%';
            fill.className = 'absolute inset-x-1 bottom-1 rounded-[22px] transition-all duration-500 ' +
                (day.is_today
                    ? 'bg-primary/90 shadow-[0_0_0_1px_rgba(220,245,170,0.2)]'
                    : 'bg-muted');
        }
    });

    var todayEl = document.getElementById('dash-energy-today-progress');
    if (todayEl) todayEl.textContent = kwh(todayProgress);
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
    var hourly = document.getElementById('energy-modal-hourly');

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

    if (hourly) {
        hourly.innerHTML = (data.hourly || []).map(function(item) {
            var level = item.warnings && item.warnings.overload > 0
                ? 'text-red-400'
                : ((item.warnings && item.warnings.high > 0) ? 'text-amber-400' : 'text-muted-foreground');

            return '<div class="rounded-xl bg-card px-2 py-2 ring-1 ring-border/20">'
                + '<p class="text-[10px] ' + level + '">' + item.hour + '</p>'
                + '<p class="mt-1 text-xs font-medium tabular-nums">' + Number(item.energy_kwh || 0).toFixed(3) + ' kWh</p>'
                + '<p class="text-[10px] text-muted-foreground">avg ' + Number(item.avg_power_w || 0).toFixed(0) + 'W / peak ' + Number(item.peak_power_w || 0).toFixed(0) + 'W</p>'
                + '</div>';
        }).join('');
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

renderEnergyBars(energyState);
bindEnergyBars();

setInterval(function() {
    fetch('/api/latest', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var el = function(id) { return document.getElementById(id); };
            var u = function(unit) { return ' <span class="text-sm font-normal text-muted-foreground">' + unit + '</span>'; };
            if (el('dash-voltage')) el('dash-voltage').innerHTML = parseFloat(d.voltage || 0).toFixed(1) + u('V');
            if (el('dash-current')) el('dash-current').innerHTML = parseFloat(d.current || 0).toFixed(3) + u('A');
            if (el('dash-power'))   el('dash-power').innerHTML   = parseFloat(d.power || 0).toFixed(1) + u('W');
            if (el('dash-energy'))  el('dash-energy').innerHTML  = parseFloat(d.energy || 0).toFixed(4) + u('kWh');
            if (el('dash-energy-total')) el('dash-energy-total').innerHTML = parseFloat(d.energy || 0).toFixed(4) + u('kWh');
            if (el('dash-raw-json')) el('dash-raw-json').textContent = JSON.stringify(d, null, 2);
        })
        .catch(function() {});
}, 5000);

setInterval(refreshEnergyHistory, 20000);
</script>
@endsection
