@extends('layouts.app')

@section('title', 'Power Strip')

@section('content')
@php
    $lastSeen = $latest['updated_at'] ? \Carbon\Carbon::parse($latest['updated_at'])->diffForHumans() : 'Never';
    $isOnline = $systemStatus !== 'offline';
@endphp

<div class="space-y-5">

    {{-- ── Row 1: Operations header ── --}}
    <div class="rounded-3xl bg-card p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight">Power Strip Command Center</h2>
                <div class="mt-1.5 flex items-center gap-2">
                    @if($isOnline)
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        <span class="text-sm text-muted-foreground">Operational view &middot; {{ $activeSockets }} sockets active &middot; {{ $lastSeen }}</span>
                    @else
                        <span class="h-2 w-2 rounded-full bg-red-400"></span>
                        <span class="text-sm text-muted-foreground">Offline &middot; {{ $lastSeen }}</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-3">
                @if($isOnline)
                    <span class="inline-flex w-fit items-center gap-2 rounded-full bg-primary/15 px-4 py-1.5 text-sm font-medium text-primary">
                        <span class="relative flex h-1.5 w-1.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span><span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-primary"></span></span>
                        {{ ucfirst($systemStatus) }}
                    </span>
                @endif
                <a href="{{ route('power-strip.settings') }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-muted px-5 text-sm font-medium text-muted-foreground transition hover:text-foreground">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Settings
                </a>
            </div>
        </div>

        {{-- Overview metrics --}}
        <div class="mt-7 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Instant Load</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="total-power">{{ number_format($totalPower, 1) }} <span class="text-sm font-normal text-muted-foreground">W</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">System Energy Counter</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="total-energy">{{ number_format($totalEnergy, 3) }} <span class="text-sm font-normal text-muted-foreground">kWh</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Active Sockets</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="active-sockets">{{ $activeSockets }} <span class="text-sm font-normal text-muted-foreground">/ 3</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Runtime State</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums capitalize">{{ $systemStatus }}</p>
            </div>
        </div>
    </div>

    {{-- ── Row 2: Socket control cards ── --}}
    <div class="grid gap-5 lg:grid-cols-3">
        @foreach($sockets as $socket)
            @include('power-strip._socket-card', ['socket' => $socket])
        @endforeach
    </div>

    {{-- ── Row 3: Operational scenes + safeguards ── --}}
    <div class="grid gap-5 lg:grid-cols-2">

        {{-- Scene Presets + command log --}}
        <div class="rounded-3xl bg-card p-7">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">Scene Presets</h3>
                    <p class="text-sm text-muted-foreground">Operational shortcuts, not analytics.</p>
                </div>
                <span id="scene-status" class="rounded-full bg-primary/15 px-3 py-1 text-xs font-medium text-primary">Manual mode</span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <button type="button" onclick="applyScene('focus')" class="rounded-2xl bg-background p-4 text-left transition hover:bg-muted">
                    <p class="text-sm font-semibold">Focus</p>
                    <p class="text-xs text-muted-foreground">S1 ON, S2 ON, S3 OFF</p>
                </button>
                <button type="button" onclick="applyScene('night')" class="rounded-2xl bg-background p-4 text-left transition hover:bg-muted">
                    <p class="text-sm font-semibold">Night</p>
                    <p class="text-xs text-muted-foreground">S1 OFF, S2 OFF, S3 ON</p>
                </button>
                <button type="button" onclick="applyScene('away')" class="rounded-2xl bg-background p-4 text-left transition hover:bg-muted">
                    <p class="text-sm font-semibold">Away</p>
                    <p class="text-xs text-muted-foreground">All OFF</p>
                </button>
                <button type="button" onclick="applyScene('boost')" class="rounded-2xl bg-background p-4 text-left transition hover:bg-muted">
                    <p class="text-sm font-semibold">Boost</p>
                    <p class="text-xs text-muted-foreground">All ON</p>
                </button>
            </div>

            <div class="mt-5 rounded-2xl bg-background p-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold">Command Log</p>
                    <button type="button" onclick="clearCommandLog()" class="text-xs text-muted-foreground transition hover:text-foreground">Clear</button>
                </div>
                <div id="command-log" class="mt-3 max-h-40 space-y-2 overflow-auto text-xs"></div>
            </div>
        </div>

        {{-- Safety guard + quick operations --}}
        <div class="flex flex-col gap-5">
            <div class="rounded-3xl bg-card p-7">
                <h3 class="text-lg font-bold">Safety Guard</h3>
                <p class="text-sm text-muted-foreground">Auto action when total power exceeds a threshold.</p>
                <div class="mt-4 space-y-3">
                    <input id="guard-threshold" type="number" min="600" max="2800" step="50" value="1800" class="w-full rounded-xl border border-border/40 bg-background px-3 py-2 text-sm outline-none focus:border-primary/60" />
                    <select id="guard-action" class="w-full rounded-xl border border-border/40 bg-background px-3 py-2 text-sm outline-none focus:border-primary/60">
                        <option value="off-3">Cut socket 3</option>
                        <option value="off-2">Cut socket 2</option>
                        <option value="off-all">Cut all sockets</option>
                    </select>
                    <button type="button" onclick="saveGuardPolicy()" class="w-full rounded-2xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">Save policy</button>
                    <p id="guard-message" class="text-xs text-muted-foreground">No action executed.</p>
                </div>
            </div>

            <div class="rounded-3xl bg-primary p-7 text-primary-foreground">
                <h3 class="text-lg font-bold">Service Operations</h3>
                <p class="mt-1.5 text-sm opacity-70">Direct maintenance controls</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button onclick="toggleAllSockets(true)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All On</button>
                    <button onclick="toggleAllSockets(false)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All Off</button>
                    <button onclick="simulateGuard()" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Test Guard</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Safety alert ── --}}
    @if($systemStatus === 'warning')
        <div class="rounded-3xl bg-amber-500/15 p-6 text-amber-400">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <p class="font-semibold">High load detected</p>
                    <p class="text-sm opacity-70">Consumption over 1800W — approaching the limit</p>
                </div>
            </div>
        </div>
    @endif

    {{-- ── JSON payload ── --}}
    <details class="group rounded-3xl bg-card">
        <summary class="flex cursor-pointer select-none items-center justify-between px-7 py-5 text-sm text-muted-foreground transition hover:text-foreground [&::-webkit-details-marker]:hidden">
            <span class="font-medium">JSON Payload &middot; Raw data</span>
            <svg class="h-4 w-4 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="border-t border-border/20 px-7 py-6">
            <pre class="overflow-x-auto rounded-2xl bg-background p-5 text-[11px] font-mono leading-relaxed text-foreground/70"><code id="raw-json">{{ json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
        </div>
    </details>

</div>

<script>
function addLog(message, isError) {
    var host = document.getElementById('command-log');
    if (!host) return;
    var row = document.createElement('div');
    row.className = 'rounded-lg px-3 py-2 ' + (isError ? 'bg-red-500/10 text-red-300' : 'bg-card text-muted-foreground');
    row.textContent = '[' + new Date().toLocaleTimeString() + '] ' + message;
    host.prepend(row);
    while (host.children.length > 20) host.removeChild(host.lastChild);
    var all = [];
    for (var i = 0; i < host.children.length; i++) all.push(host.children[i].textContent || '');
    localStorage.setItem('powerStripCommandLog', JSON.stringify(all));
}

function restoreLog() {
    var host = document.getElementById('command-log');
    if (!host) return;
    var raw = localStorage.getItem('powerStripCommandLog');
    if (!raw) return;
    try {
        var rows = JSON.parse(raw);
        if (!Array.isArray(rows)) return;
        rows.forEach(function(text) {
            var row = document.createElement('div');
            row.className = 'rounded-lg bg-card px-3 py-2 text-muted-foreground';
            row.textContent = text;
            host.appendChild(row);
        });
    } catch (_) {}
}

function clearCommandLog() {
    localStorage.removeItem('powerStripCommandLog');
    var host = document.getElementById('command-log');
    if (host) host.innerHTML = '';
}

function toggleSocket(idx, turnOn) {
    fetch('/api/relay/' + idx + '/' + (turnOn ? 'on' : 'off'), { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function() {
            addLog('Socket ' + idx + ' -> ' + (turnOn ? 'ON' : 'OFF'));
            setTimeout(function() { location.reload(); }, 120);
        })
        .catch(function(e) { console.error('Relay error', e); addLog('Socket ' + idx + ' command failed', true); });
}

function toggleAllSockets(turnOn) {
    var s = turnOn ? 'on' : 'off';
    Promise.all([
        fetch('/api/relay/1/' + s, { credentials: 'same-origin' }),
        fetch('/api/relay/2/' + s, { credentials: 'same-origin' }),
        fetch('/api/relay/3/' + s, { credentials: 'same-origin' })
    ]).then(function() {
        addLog('All sockets -> ' + (turnOn ? 'ON' : 'OFF'));
        setTimeout(function() { location.reload(); }, 120);
    }).catch(function(e) { console.error('Toggle all error', e); addLog('All sockets command failed', true); });
}

function applyScene(scene) {
    var map = {
        focus: [true, true, false],
        night: [false, false, true],
        away: [false, false, false],
        boost: [true, true, true],
    };
    if (!map[scene]) return;
    var state = map[scene];
    Promise.all([
        fetch('/api/relay/1/' + (state[0] ? 'on' : 'off'), { credentials: 'same-origin' }),
        fetch('/api/relay/2/' + (state[1] ? 'on' : 'off'), { credentials: 'same-origin' }),
        fetch('/api/relay/3/' + (state[2] ? 'on' : 'off'), { credentials: 'same-origin' }),
    ]).then(function() {
        var badge = document.getElementById('scene-status');
        if (badge) badge.textContent = scene.charAt(0).toUpperCase() + scene.slice(1) + ' mode';
        addLog('Scene applied: ' + scene);
        setTimeout(function() { location.reload(); }, 120);
    }).catch(function() { addLog('Scene failed: ' + scene, true); });
}

function saveGuardPolicy() {
    var threshold = parseFloat((document.getElementById('guard-threshold') || {}).value || '1800');
    var action = (document.getElementById('guard-action') || {}).value || 'off-3';
    localStorage.setItem('powerStripGuard', JSON.stringify({ threshold: threshold, action: action }));
    var msg = document.getElementById('guard-message');
    if (msg) msg.textContent = 'Policy saved: ' + threshold.toFixed(0) + 'W / ' + action;
    addLog('Guard policy updated');
}

function loadGuardPolicy() {
    var raw = localStorage.getItem('powerStripGuard');
    if (!raw) return;
    try {
        var policy = JSON.parse(raw);
        if (policy.threshold && document.getElementById('guard-threshold')) document.getElementById('guard-threshold').value = policy.threshold;
        if (policy.action && document.getElementById('guard-action')) document.getElementById('guard-action').value = policy.action;
    } catch (_) {}
}

function simulateGuard() {
    var raw = localStorage.getItem('powerStripGuard');
    if (!raw) {
        addLog('No guard policy saved', true);
        return;
    }
    try {
        var policy = JSON.parse(raw);
        if (policy.action === 'off-3') return toggleSocket(3, false);
        if (policy.action === 'off-2') return toggleSocket(2, false);
        if (policy.action === 'off-all') return toggleAllSockets(false);
    } catch (_) {}
}

restoreLog();
loadGuardPolicy();

function applyLatestMetrics(d) {
    var el = function(id) { return document.getElementById(id); };
    var u = function(unit) { return ' <span class="text-sm font-normal text-muted-foreground">' + unit + '</span>'; };
    if (el('total-power')) el('total-power').innerHTML = parseFloat(d.power || 0).toFixed(1) + u('W');
    if (el('total-energy')) el('total-energy').innerHTML = parseFloat(d.energy || 0).toFixed(3) + u('kWh');
    if (el('active-sockets')) {
        var count = 0;
        if (d.relay_1) count++;
        if (d.relay_2) count++;
        if (d.relay_3) count++;
        el('active-sockets').innerHTML = count + '<span class="text-sm font-normal text-muted-foreground">/3</span>';
    }
    if (el('raw-json')) {
        el('raw-json').textContent = JSON.stringify(d, null, 2);
    }
}

window.addEventListener('pulsenode:latest', function(event) {
    applyLatestMetrics(event.detail || {});
});

if (window.__pulsenodeLatest) {
    applyLatestMetrics(window.__pulsenodeLatest);
}
</script>
@endsection
