@extends('layouts.app')

@section('title', 'Power Strip')

@section('content')
@php
    $lastSeen = $latest['updated_at'] ? \Carbon\Carbon::parse($latest['updated_at'])->diffForHumans() : 'Never';
    $isOnline = $systemStatus !== 'offline';
@endphp

<div class="space-y-5">

    {{-- ── Row 1: Status banner ── --}}
    <div class="rounded-3xl bg-card p-7">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight">Smart Power Strip</h2>
                <div class="mt-1.5 flex items-center gap-2">
                    @if($isOnline)
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        <span class="text-sm text-muted-foreground">{{ $activeSockets }} sockets active &middot; {{ $lastSeen }}</span>
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
                <p class="text-xs text-muted-foreground">Total Power</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="total-power">{{ number_format($totalPower, 1) }} <span class="text-sm font-normal text-muted-foreground">W</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Total Energy</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="total-energy">{{ number_format($totalEnergy, 3) }} <span class="text-sm font-normal text-muted-foreground">kWh</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Active Sockets</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="active-sockets">{{ $activeSockets }} <span class="text-sm font-normal text-muted-foreground">/ 3</span></p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">System Status</p>
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

    {{-- ── Row 3: Current Distribution + Quick actions ── --}}
    <div class="grid gap-5 lg:grid-cols-2">

        {{-- Current Distribution --}}
        <div class="rounded-3xl bg-card p-7">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-bold">Current Distribution</h3>
                    <p class="text-sm text-muted-foreground">Per-socket measurement</p>
                </div>
                <span class="text-lg font-bold tabular-nums" id="strip-current-total">{{ number_format((float)($latest['current'] ?? 0), 3) }} <span class="text-sm font-normal text-muted-foreground">A</span></span>
            </div>

            @php
                $c1 = round((float)($latest['current_1'] ?? 0), 3);
                $c2 = round((float)($latest['current_2'] ?? 0), 3);
                $c3 = round((float)($latest['current_3'] ?? 0), 3);
                $barMax = max($c1, $c2, $c3, 0.001);
                $maxIdx = ($c1 >= $c2 && $c1 >= $c3) ? 1 : (($c2 >= $c3) ? 2 : 3);
                if ($c1 == 0 && $c2 == 0 && $c3 == 0) $maxIdx = 0;
                $bars = [
                    ['label' => 'S1', 'val' => $c1, 'idx' => 1],
                    ['label' => 'S2', 'val' => $c2, 'idx' => 2],
                    ['label' => 'S3', 'val' => $c3, 'idx' => 3],
                ];
            @endphp

            <div class="mt-8 flex items-end gap-5" style="height: 160px">
                @foreach($bars as $bar)
                    @php $pct = $barMax > 0 ? max(10, ($bar['val'] / $barMax) * 100) : 10; @endphp
                    <div class="flex flex-1 flex-col items-center gap-3">
                        <span class="text-xs tabular-nums {{ $bar['idx'] === $maxIdx ? 'text-primary font-semibold' : 'text-muted-foreground' }}">{{ $bar['val'] }} A</span>
                        <div class="relative w-full" style="height: 120px">
                            <div class="absolute inset-x-0 bottom-0 w-full rounded-3xl transition-all duration-700 {{ $bar['idx'] === $maxIdx ? 'bg-primary/80' : 'bg-muted' }}" style="height: {{ $pct }}%"></div>
                        </div>
                        <span class="text-xs font-medium {{ $bar['idx'] === $maxIdx ? 'text-primary' : 'text-muted-foreground' }}">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Quick actions + Hardware --}}
        <div class="flex flex-col gap-5">

            {{-- Quick control CTA (lime) --}}
            <div class="rounded-3xl bg-primary p-7 text-primary-foreground">
                <h3 class="text-lg font-bold">Quick Control</h3>
                <p class="mt-1.5 text-sm opacity-70">Turn all sockets on or off with a single tap</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button onclick="toggleAllSockets(true)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All On</button>
                    <button onclick="toggleAllSockets(false)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All Off</button>
                </div>
            </div>

            {{-- Hardware info --}}
            <div class="flex-1 rounded-3xl bg-card p-7">
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
            <pre class="overflow-x-auto rounded-2xl bg-background p-5 text-[11px] font-mono leading-relaxed text-foreground/70"><code>{{ json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
        </div>
    </details>

</div>

<script>
function toggleSocket(idx, turnOn) {
    fetch('/api/relay/' + idx + '/' + (turnOn ? 'on' : 'off'), { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function() { location.reload(); })
        .catch(function(e) { console.error('Relay error', e); });
}
function toggleAllSockets(turnOn) {
    var s = turnOn ? 'on' : 'off';
    Promise.all([
        fetch('/api/relay/1/' + s, { credentials: 'same-origin' }),
        fetch('/api/relay/2/' + s, { credentials: 'same-origin' }),
        fetch('/api/relay/3/' + s, { credentials: 'same-origin' })
    ]).then(function() { location.reload(); })
      .catch(function(e) { console.error('Toggle all error', e); });
}

setInterval(function() {
    fetch('/api/latest', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var el = function(id) { return document.getElementById(id); };
            var u = function(unit) { return ' <span class="text-sm font-normal text-muted-foreground">' + unit + '</span>'; };
            if (el('total-power')) el('total-power').innerHTML = parseFloat(d.power || 0).toFixed(1) + u('W');
            if (el('total-energy')) el('total-energy').innerHTML = parseFloat(d.energy || 0).toFixed(3) + u('kWh');
            if (el('strip-current-total')) el('strip-current-total').innerHTML = parseFloat(d.current || 0).toFixed(3) + u('A');
            if (el('active-sockets')) {
                var count = 0;
                if (d.relay_1) count++;
                if (d.relay_2) count++;
                if (d.relay_3) count++;
                el('active-sockets').innerHTML = count + u('/ 3');
            }
        })
        .catch(function() {});
}, 5000);
</script>
@endsection
