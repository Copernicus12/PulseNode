@extends('layouts.app')

@section('title', 'Advanced Settings')

@section('content')
<div class="space-y-5">

    {{-- Back link --}}
    <div>
        <a href="{{ route('power-strip.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-foreground">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to Power Strip
        </a>
    </div>

    {{-- ── Connection & MQTT ── --}}
    <div class="rounded-3xl bg-card p-7">
        <h3 class="text-lg font-bold">Connection</h3>
        <p class="text-sm text-muted-foreground">Technical details about device connectivity</p>

        <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl bg-background p-5">
                <p class="text-[11px] text-muted-foreground">MQTT Broker</p>
                <p class="mt-2 text-sm font-bold font-mono tabular-nums">{{ config('esp32.mqtt.host', '127.0.0.1') }}:{{ config('esp32.mqtt.port', 1883) }}</p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-[11px] text-muted-foreground">MQTT Status</p>
                <div class="mt-2 flex items-center gap-2">
                    @if(config('esp32.mqtt.enabled'))
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        <span class="text-sm font-bold">Active</span>
                    @else
                        <span class="h-2 w-2 rounded-full bg-red-400"></span>
                        <span class="text-sm font-bold">Disabled</span>
                    @endif
                </div>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-[11px] text-muted-foreground">Command Topic</p>
                <p class="mt-2 text-sm font-bold font-mono tabular-nums truncate">{{ config('esp32.mqtt.command_topic', 'esp32/cmd') }}</p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-[11px] text-muted-foreground">Telemetry Topic</p>
                <p class="mt-2 text-sm font-bold font-mono tabular-nums truncate">{{ config('esp32.mqtt.telemetry_topic', 'esp32/telemetry') }}</p>
            </div>
        </div>
    </div>

    {{-- ── Device Information ── --}}
    <div class="rounded-3xl bg-card p-7">
        <h3 class="text-lg font-bold">Device Information</h3>
        <p class="text-sm text-muted-foreground">Hardware and firmware details</p>

        <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl bg-background p-5">
                <p class="text-[11px] text-muted-foreground">Device Type</p>
                <p class="mt-2 text-sm font-bold font-mono">ESP32-WROOM-32</p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-[11px] text-muted-foreground">Firmware Version</p>
                <p class="mt-2 text-sm font-bold font-mono">v2.4.1</p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-[11px] text-muted-foreground">Relay Count</p>
                <p class="mt-2 text-sm font-bold">3 (230V / 10A each)</p>
            </div>
            <div class="rounded-2xl bg-background p-5">
                <p class="text-[11px] text-muted-foreground">Ingest Endpoint</p>
                <p class="mt-2 text-sm font-bold font-mono">POST /api/ingest</p>
            </div>
        </div>
    </div>

    {{-- ── Sensor Pinout ── --}}
    <div class="rounded-3xl bg-card p-7">
        <h3 class="text-lg font-bold">Sensor Pinout</h3>
        <p class="text-sm text-muted-foreground">ESP32 pin mapping</p>

        <div class="mt-6 space-y-0">
            @php
                $pins = [
                    ['Voltage (ZMPT101B)', 'GPIO 5',  'Analog'],
                    ['Current 1 (ACS712)', 'GPIO 12', 'Analog'],
                    ['Current 2 (ACS712)', 'GPIO 13', 'Analog'],
                    ['Current 3 (ACS712)', 'GPIO 14', 'Analog'],
                    ['Relay 1',            'GPIO 15', 'Digital'],
                    ['Relay 2',            'GPIO 16', 'Digital'],
                    ['Relay 3',            'GPIO 17', 'Digital'],
                ];
            @endphp
            @foreach($pins as [$name, $pin, $type])
                <div class="flex items-center justify-between border-b border-border/20 py-3 text-sm last:border-0">
                    <span class="text-muted-foreground">{{ $name }}</span>
                    <div class="flex items-center gap-3">
                        <span class="rounded-xl bg-background px-3 py-1.5 text-xs font-medium font-mono">{{ $pin }}</span>
                        <span class="text-xs text-muted-foreground">{{ $type }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ── Sync Status ── --}}
    <div class="rounded-3xl bg-card p-7">
        <h3 class="text-lg font-bold">Sync Status</h3>
        <p class="text-sm text-muted-foreground">Data pipeline health</p>

        <div class="mt-7 grid gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-4 rounded-2xl bg-background p-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <div>
                    <p class="text-sm font-medium">Ingestion</p>
                    <p class="text-xs text-muted-foreground">
                        @if($latest['updated_at'])
                            Last data {{ \Carbon\Carbon::parse($latest['updated_at'])->diffForHumans() }}
                        @else
                            No data received
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-2xl bg-background p-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary/15 text-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
                <div>
                    <p class="text-sm font-medium">Poll Interval</p>
                    <p class="text-xs text-muted-foreground">Every 5 seconds</p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-2xl bg-background p-5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-violet-500/15 text-violet-400">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                </span>
                <div>
                    <p class="text-sm font-medium">Storage</p>
                    <p class="text-xs text-muted-foreground">Local JSON file</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Raw payload ── --}}
    <details class="group rounded-3xl bg-card">
        <summary class="flex cursor-pointer select-none items-center justify-between px-7 py-5 text-sm text-muted-foreground transition hover:text-foreground [&::-webkit-details-marker]:hidden">
            <span class="font-medium">JSON Payload &middot; Raw data</span>
            <svg class="h-4 w-4 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="border-t border-border/20 px-7 py-6">
            <pre class="overflow-x-auto rounded-2xl bg-background p-5 text-[11px] font-mono leading-relaxed text-foreground/70"><code>{{ json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
        </div>
    </details>

    {{-- ── Debug table ── --}}
    <details class="group rounded-3xl bg-card">
        <summary class="flex cursor-pointer select-none items-center justify-between px-7 py-5 text-sm text-muted-foreground transition hover:text-foreground [&::-webkit-details-marker]:hidden">
            <span class="font-medium">Debug &middot; State table</span>
            <svg class="h-4 w-4 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="border-t border-border/20 px-7 py-6">
            <div class="overflow-hidden rounded-2xl bg-background">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/20 text-left text-xs text-muted-foreground">
                            <th class="px-5 py-3.5 font-medium">Key</th>
                            <th class="px-5 py-3.5 font-medium">Value</th>
                            <th class="px-5 py-3.5 font-medium">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latest as $key => $value)
                            <tr class="border-b border-border/15 last:border-0">
                                <td class="px-5 py-3 font-mono text-xs font-medium">{{ $key }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-muted-foreground">{{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</td>
                                <td class="px-5 py-3 text-xs text-muted-foreground">{{ gettype($value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </details>

</div>
@endsection
