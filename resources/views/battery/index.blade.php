@extends('layouts.app')

@section('title', 'Battery')

@section('content')
@php
    $score = $battery['overall_score'];
    $efficiency = $battery['efficiency_score'];
    $stability = $battery['stability_score'];
    $waste = $battery['standby_waste_w'];
    $scoreTone = $score >= 76 ? 'text-emerald-300' : ($score >= 56 ? 'text-amber-300' : 'text-red-300');
@endphp

<div class="space-y-5">
    <section class="overflow-hidden rounded-[2rem] bg-card">
        <div class="relative p-7 sm:p-8">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(110,168,255,0.18),transparent_34%),radial-gradient(circle_at_80%_24%,rgba(208,255,120,0.12),transparent_28%),linear-gradient(135deg,rgba(255,255,255,0.03),transparent_58%)]"></div>
            <div class="relative grid gap-6 xl:grid-cols-[1.15fr_0.85fr] xl:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium {{ $isOnline ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/20' : 'bg-red-500/15 text-red-300 ring-1 ring-red-500/20' }}">
                            <span class="h-2 w-2 rounded-full {{ $isOnline ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                            {{ $isOnline ? 'Reserve Board Online' : 'Reserve Board Offline' }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">
                            Efficiency, waste, and load stability
                        </span>
                    </div>

                    <div class="mt-5 max-w-3xl">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Battery is now your energy reserve score.</h2>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground sm:text-base">Instead of pretending the strip has a real battery, this page rates how healthy your energy usage is: how much load is useful, how much is leaking in standby, and how stable each socket behaves over recent samples.</p>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-3xl bg-background/70 p-4 backdrop-blur-sm ring-1 ring-border/30">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-muted-foreground">Reserve score</p>
                            <p class="mt-3 text-3xl font-bold tabular-nums {{ $scoreTone }}">{{ $score }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Composite efficiency health</p>
                        </div>
                        <div class="rounded-3xl bg-background/70 p-4 backdrop-blur-sm ring-1 ring-border/30">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-muted-foreground">Standby waste</p>
                            <p class="mt-3 text-3xl font-bold tabular-nums">{{ number_format($waste, 1) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">W lost in near-idle draw</p>
                        </div>
                        <div class="rounded-3xl bg-background/70 p-4 backdrop-blur-sm ring-1 ring-border/30">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-muted-foreground">Stability</p>
                            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $stability }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Lower volatility means better guard</p>
                        </div>
                        <div class="rounded-3xl bg-background/70 p-4 backdrop-blur-sm ring-1 ring-border/30">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-muted-foreground">Live draw</p>
                            <p class="mt-3 text-3xl font-bold tabular-nums">{{ number_format($battery['live_draw_w'], 1) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Average recent socket demand</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="rounded-3xl border border-primary/15 bg-background/70 p-5 backdrop-blur-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold">Energy reserve ring</p>
                                <p class="mt-1 text-xs text-muted-foreground">Fast visual estimate of overall strip efficiency.</p>
                            </div>
                            <div class="relative flex h-28 w-28 items-center justify-center rounded-full bg-card ring-1 ring-border/30">
                                <div class="absolute inset-2 rounded-full border-[10px] border-muted"></div>
                                <div class="absolute inset-2 rounded-full border-[10px] border-transparent border-t-primary border-r-primary" style="transform: rotate({{ max(15, min(330, $score * 3.3)) }}deg);"></div>
                                <div class="relative text-center">
                                    <p class="text-3xl font-bold {{ $scoreTone }}">{{ $score }}</p>
                                    <p class="text-[11px] text-muted-foreground">/100</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-primary p-5 text-primary-foreground">
                        <p class="text-sm font-semibold">Interpretation</p>
                        <p class="mt-2 text-sm opacity-80">High score means the strip is spending most of its power on useful, stable loads. Low score usually means standby drift, volatile appliances, or inefficient always-on devices.</p>
                        <p class="mt-3 text-xs opacity-70">Last sync {{ $lastSeen }} · System {{ ucfirst($systemStatus) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
        <div class="flex flex-col gap-5">
            <div class="rounded-3xl bg-card p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold">Priority actions</h3>
                        <p class="text-sm text-muted-foreground">What to improve first if you want a better reserve score.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-3">
                    @foreach($battery['priority_actions'] as $action)
                        <div class="rounded-3xl bg-background p-4 ring-1 ring-border/30">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full {{ $action['tone'] === 'good' ? 'bg-emerald-400' : ($action['tone'] === 'warning' ? 'bg-amber-400' : 'bg-sky-400') }}"></span>
                                <div>
                                    <p class="text-sm font-semibold">{{ $action['title'] }}</p>
                                    <p class="mt-1 text-sm text-muted-foreground">{{ $action['description'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-3xl bg-card p-7">
                <h3 class="text-lg font-bold">Score composition</h3>
                <p class="text-sm text-muted-foreground">Three simplified segments behind the reserve board.</p>
                <div class="mt-5 space-y-4">
                    @foreach($battery['segments'] as $segment)
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium">{{ $segment['label'] }}</span>
                                <span class="tabular-nums text-muted-foreground">{{ $segment['value'] }}/100</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-background ring-1 ring-border/20">
                                <div class="h-full rounded-full {{ $segment['label'] === 'Reserve' ? 'bg-primary' : ($segment['label'] === 'Guard' ? 'bg-sky-400' : 'bg-amber-400') }}" style="width: {{ $segment['value'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-3xl bg-card p-7">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">Socket efficiency board</h3>
                    <p class="text-sm text-muted-foreground">Battery-style evaluation per socket: standby leakage, variability, and useful active time.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">{{ count($battery['socket_insights']) }} sockets analyzed</span>
            </div>

            <div class="mt-6 grid gap-5 lg:grid-cols-3">
                @foreach($battery['socket_insights'] as $socket)
                    <article class="overflow-hidden rounded-[2rem] bg-background ring-1 ring-border/30">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.22em] text-muted-foreground">Socket {{ $socket['socket_index'] }}</p>
                                    <h4 class="mt-2 text-lg font-bold">{{ $socket['label'] }}</h4>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ $socket['category'] }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $socket['status'] === 'Efficient' ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/20' : ($socket['status'] === 'Standby leak' ? 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/20' : 'bg-sky-500/15 text-sky-300 ring-1 ring-sky-500/20') }}">{{ $socket['status'] }}</span>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-2xl bg-card p-4 ring-1 ring-border/20">
                                    <p class="text-[11px] text-muted-foreground">Efficiency</p>
                                    <p class="mt-1 text-lg font-bold tabular-nums">{{ $socket['efficiency_score'] }}</p>
                                </div>
                                <div class="rounded-2xl bg-card p-4 ring-1 ring-border/20">
                                    <p class="text-[11px] text-muted-foreground">Stability</p>
                                    <p class="mt-1 text-lg font-bold tabular-nums">{{ $socket['stability_score'] }}</p>
                                </div>
                            </div>

                            <div class="mt-5 space-y-4">
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
                                        <span>Load variability</span>
                                        <span class="tabular-nums">{{ number_format($socket['variability_pct'], 1) }}%</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-card ring-1 ring-border/20">
                                        <div class="h-full rounded-full bg-sky-400" style="width: {{ min(100, $socket['variability_pct']) }}%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3 text-sm border-t border-border/20 pt-4">
                                <div>
                                    <p class="text-muted-foreground">Avg / Peak</p>
                                    <p class="mt-1 font-semibold tabular-nums">{{ number_format($socket['avg_power_w'], 1) }} / {{ number_format($socket['peak_power_w'], 1) }} W</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Active / Standby</p>
                                    <p class="mt-1 font-semibold tabular-nums">{{ number_format($socket['active_minutes'], 1) }} / {{ number_format($socket['standby_minutes'], 1) }} min</p>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endsection
