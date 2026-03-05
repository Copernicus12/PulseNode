@extends('layouts.app')

@section('title', 'Battery')

@section('content')
@php
    $score = $battery['overall_score'];
    $efficiency = $battery['efficiency_score'];
    $stability = $battery['stability_score'];
    $waste = $battery['standby_waste_w'];
    $liveDraw = $battery['live_draw_w'];
    $scoreTone = $score >= 76 ? 'text-emerald-300' : ($score >= 56 ? 'text-amber-300' : 'text-red-300');
    $scoreBadge = $score >= 76
        ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/20'
        : ($score >= 56 ? 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/20' : 'bg-red-500/15 text-red-300 ring-1 ring-red-500/20');
    $scoreLabel = $score >= 76 ? 'Healthy' : ($score >= 56 ? 'Watch closely' : 'Needs attention');
    $scoreMessage = $score >= 76
        ? 'Recent usage looks efficient and stable.'
        : ($score >= 56
            ? 'The system is fine, but idle waste or unstable loads should be checked.'
            : 'There is enough wasted power or unstable behavior to justify action now.');
    $bestSocket = collect($battery['socket_insights'])->sortByDesc('efficiency_score')->first();
    $watchSocket = collect($battery['socket_insights'])->sortBy('stability_score')->first();
    $statusPalette = [
        'Efficient' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/20',
        'Standby leak' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/20',
        'Volatile load' => 'bg-sky-500/15 text-sky-300 ring-1 ring-sky-500/20',
        'Sleeping' => 'bg-muted text-muted-foreground ring-1 ring-border/40',
    ];
@endphp

<div class="space-y-5">
    <section class="rounded-3xl bg-card p-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium {{ $isOnline ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/20' : 'bg-red-500/15 text-red-300 ring-1 ring-red-500/20' }}">
                        <span class="h-2 w-2 rounded-full {{ $isOnline ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                        {{ $isOnline ? 'Online' : 'Offline' }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">
                        Battery level
                    </span>
                </div>

                <div class="mt-4">
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Battery overview at a glance.</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground sm:text-base">
                        A quick view of efficiency, standby waste, and which socket needs attention.
                    </p>
                </div>
            </div>

            <div class="w-full max-w-md rounded-3xl bg-background p-5 ring-1 ring-border/30">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Overall score</p>
                        <p class="mt-2 text-5xl font-bold tabular-nums {{ $scoreTone }}">{{ $score }}</p>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $scoreBadge }}">{{ $scoreLabel }}</span>
                    </div>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">Last sync</dt>
                            <dd id="battery-last-sync" class="mt-1 font-semibold">{{ $lastSeen }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">System</dt>
                            <dd class="mt-1 font-semibold capitalize">{{ $systemStatus }}</dd>
                        </div>
                    </dl>
                </div>

                <p class="mt-4 text-sm text-muted-foreground">{{ $scoreMessage }}</p>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Efficiency</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ $efficiency }}</p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Stability</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ $stability }}</p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Standby waste</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ number_format($waste, 1) }} <span class="text-sm font-normal text-muted-foreground">W</span></p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Average draw</p>
                <p class="mt-2 text-2xl font-bold tabular-nums"><span id="battery-live-draw">{{ number_format($liveDraw, 1) }}</span> <span class="text-sm font-normal text-muted-foreground">W</span></p>
            </div>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
        <div class="rounded-3xl bg-card p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">What to do next</h3>
                    <p class="text-sm text-muted-foreground">Start with the most useful next step.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">
                    {{ count($battery['priority_actions']) }} items
                </span>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Best efficiency</p>
                    <p class="mt-2 font-semibold">{{ $bestSocket['label'] ?? 'Unavailable' }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ isset($bestSocket['efficiency_score']) ? $bestSocket['efficiency_score'].'/100 efficiency' : 'No recent readings yet.' }}
                    </p>
                </div>
                <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Needs watching</p>
                    <p class="mt-2 font-semibold">{{ $watchSocket['label'] ?? 'Unavailable' }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ isset($watchSocket['stability_score']) ? $watchSocket['stability_score'].'/100 stability' : 'No recent readings yet.' }}
                    </p>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                @forelse($battery['priority_actions'] as $action)
                    <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                        <div class="flex items-start gap-3">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full {{ $action['tone'] === 'good' ? 'bg-emerald-400' : ($action['tone'] === 'warning' ? 'bg-amber-400' : 'bg-sky-400') }}"></span>
                            <div>
                                <p class="text-sm font-semibold">{{ $action['title'] }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ $action['description'] }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl bg-background p-4 text-sm text-muted-foreground ring-1 ring-border/30">
                        No immediate issues were detected from recent samples.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rounded-3xl bg-card p-6">
            <h3 class="text-lg font-bold">How to read the score</h3>
            <p class="mt-1 text-sm text-muted-foreground">A simple summary of reserve, stability, and avoidable loss.</p>

            <div class="mt-4 rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <div class="space-y-4">
                    @foreach($battery['segments'] as $segment)
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium">{{ $segment['label'] }}</span>
                                <span class="tabular-nums text-muted-foreground">{{ $segment['value'] }}/100</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-card ring-1 ring-border/20">
                                <div class="h-full rounded-full {{ $segment['label'] === 'Reserve' ? 'bg-primary' : ($segment['label'] === 'Guard' ? 'bg-sky-400' : 'bg-amber-400') }}" style="width: {{ $segment['value'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-sm font-semibold">Good score</p>
                    <p class="mt-1 text-sm text-muted-foreground">Low idle waste and stable socket behavior.</p>
                </div>
                <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-sm font-semibold">Low score</p>
                    <p class="mt-1 text-sm text-muted-foreground">Too much standby draw or unstable usage patterns.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-3xl bg-card p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold">Socket overview</h3>
                <p class="text-sm text-muted-foreground">Compact diagnostics for each socket.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">
                {{ count($battery['socket_insights']) }} sockets
            </span>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            @foreach($battery['socket_insights'] as $socket)
                <article class="rounded-2xl bg-background p-5 ring-1 ring-border/30">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Socket {{ $socket['socket_index'] }}</p>
                            <h4 class="mt-2 text-base font-semibold">{{ $socket['label'] }}</h4>
                            <p class="mt-1 text-sm text-muted-foreground">{{ $socket['category'] }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $statusPalette[$socket['status']] ?? 'bg-muted text-muted-foreground ring-1 ring-border/40' }}">
                            {{ $socket['status'] }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl bg-card p-4 ring-1 ring-border/20">
                            <p class="text-[11px] text-muted-foreground">Efficiency</p>
                            <p class="mt-1 text-lg font-bold tabular-nums">{{ $socket['efficiency_score'] }}</p>
                        </div>
                        <div class="rounded-2xl bg-card p-4 ring-1 ring-border/20">
                            <p class="text-[11px] text-muted-foreground">Stability</p>
                            <p class="mt-1 text-lg font-bold tabular-nums">{{ $socket['stability_score'] }}</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Standby waste</span>
                                <span class="tabular-nums">{{ number_format($socket['standby_w'], 1) }} W</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-card ring-1 ring-border/20">
                                <div class="h-full rounded-full bg-amber-400" style="width: {{ min(100, $socket['standby_w'] * 18) }}%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between text-xs text-muted-foreground">
                                <span>Variability</span>
                                <span class="tabular-nums">{{ number_format($socket['variability_pct'], 1) }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-card ring-1 ring-border/20">
                                <div class="h-full rounded-full bg-sky-400" style="width: {{ min(100, $socket['variability_pct']) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 border-t border-border/20 pt-4 text-sm">
                        <div>
                            <p class="text-muted-foreground">Avg / Peak</p>
                            <p class="mt-1 font-semibold tabular-nums">{{ number_format($socket['avg_power_w'], 1) }} / {{ number_format($socket['peak_power_w'], 1) }} W</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Active / Idle</p>
                            <p class="mt-1 font-semibold tabular-nums">{{ number_format($socket['active_minutes'], 1) }} / {{ number_format($socket['standby_minutes'], 1) }} min</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
<script>
(function () {
    function asNumber(v) {
        var n = Number(v);
        return Number.isFinite(n) ? n : 0;
    }

    function lastSeenLabel(updatedAt) {
        if (!updatedAt) return 'never';
        var ts = Date.parse(updatedAt);
        if (!Number.isFinite(ts)) return 'unknown';
        var diffSec = Math.max(0, Math.floor((Date.now() - ts) / 1000));
        if (diffSec < 5) return 'just now';
        if (diffSec < 60) return diffSec + ' sec ago';
        if (diffSec < 3600) return Math.floor(diffSec / 60) + ' min ago';
        return Math.floor(diffSec / 3600) + ' h ago';
    }

    window.addEventListener('pulsenode:latest', function (event) {
        var data = event.detail || {};
        var syncEl = document.getElementById('battery-last-sync');
        if (syncEl) syncEl.textContent = lastSeenLabel(data.updated_at);

        var drawEl = document.getElementById('battery-live-draw');
        if (drawEl) drawEl.textContent = asNumber(data.power).toFixed(1);
    });
})();
</script>
@endsection
