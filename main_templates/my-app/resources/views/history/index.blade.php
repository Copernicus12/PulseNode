@extends('layouts.app')

@section('title', 'History')

@section('content')
@php
    $selectedWarnings = $selectedDay['warnings'] ?? ['high' => 0, 'overload' => 0];
    $maxWeekTotal = max(0.001, (float) $week->max('total'));
    $maxSocketEnergy = max(0.001, (float) collect($selectedDay['socket_stats'] ?? [])->max('energy_kwh'));
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
                        Energy history
                    </span>
                </div>

                <div class="mt-4">
                    <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Consumption history for analysis and decisions.</h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground sm:text-base">
                        This page supports the thesis direction well: it shows trends, daily breakdowns, active intervals, and the sockets that dominate consumption.
                    </p>
                </div>
            </div>

            <div class="w-full max-w-md rounded-3xl bg-background p-5 ring-1 ring-border/30">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs text-muted-foreground">Selected day</p>
                        <p class="mt-1 text-lg font-semibold">{{ $selectedDay['date'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Last sync</p>
                        <p class="mt-1 text-lg font-semibold">{{ $lastSeen }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Day total</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">{{ number_format($selectedDay['total_kwh'], 4) }} kWh</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Warnings</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">{{ $totalWarnings }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Week total</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ number_format($weeklyTotal, 4) }} <span class="text-sm font-normal text-muted-foreground">kWh</span></p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Average day</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ number_format($averageDay, 4) }} <span class="text-sm font-normal text-muted-foreground">kWh</span></p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Peak day</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ number_format((float) ($peakDay['total'] ?? 0), 4) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ $peakDay['date'] ?? 'No data' }}</p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Most active hour</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ $topHour['hour'] ?? '--:--' }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ number_format((float) ($topHour['energy_kwh'] ?? 0), 4) }} kWh</p>
            </div>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-3xl bg-card p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">Weekly trend</h3>
                    <p class="text-sm text-muted-foreground">Choose a day to inspect detailed behavior and anomalies.</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
                @foreach($week as $day)
                    @php
                        $isSelected = $selectedDate === $day['date'];
                        $barHeight = max(12, ((float) $day['total'] / $maxWeekTotal) * 100);
                    @endphp
                    <a href="{{ route('history.index', ['date' => $day['date']]) }}"
                        @class([
                            'rounded-3xl p-4 transition ring-1',
                            'bg-primary text-primary-foreground ring-primary/30' => $isSelected,
                            'bg-background text-foreground ring-border/30 hover:ring-primary/20' => ! $isSelected,
                        ])>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium">{{ $day['day_short'] }}</span>
                            <span>{{ \Carbon\Carbon::parse($day['date'])->format('d.m') }}</span>
                        </div>
                        <div class="mt-4 flex h-28 items-end">
                            <div class="w-full rounded-2xl bg-card/50 p-1 {{ $isSelected ? 'bg-primary-foreground/10' : '' }}">
                                <div class="rounded-xl {{ $isSelected ? 'bg-primary-foreground/85' : 'bg-primary/80' }}" style="height: {{ $barHeight }}px"></div>
                            </div>
                        </div>
                        <p class="mt-3 text-sm font-semibold tabular-nums">{{ number_format((float) $day['total'], 4) }} kWh</p>
                        <p class="mt-1 text-xs {{ $isSelected ? 'text-primary-foreground/70' : 'text-muted-foreground' }}">
                            {{ $day['is_today'] ? 'Today' : 'Recorded day' }}
                        </p>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="rounded-3xl bg-card p-6">
            <h3 class="text-lg font-bold">Selected day summary</h3>
            <p class="mt-1 text-sm text-muted-foreground">{{ $selectedDay['date'] }} from {{ $selectedDay['from_time'] }} to {{ $selectedDay['to_time'] }}</p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-xs text-muted-foreground">Average voltage</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ number_format($selectedDay['avg_voltage'], 1) }} V</p>
                </div>
                <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-xs text-muted-foreground">Active hours</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ $activeHours }}</p>
                </div>
                <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-xs text-muted-foreground">High-load warnings</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ $selectedWarnings['high'] }}</p>
                </div>
                <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-xs text-muted-foreground">Overload warnings</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">{{ $selectedWarnings['overload'] }}</p>
                </div>
            </div>

            <div class="mt-4 rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-sm font-semibold">Dominant socket</p>
                <p class="mt-2 text-base font-semibold">{{ $topSocket['name'] ?? 'Unavailable' }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ $topSocket ? number_format((float) $topSocket['energy_kwh'], 4).' kWh · '.$topSocket['percentage'].'% of the day total' : 'No data for this day.' }}
                </p>
            </div>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
        <div class="rounded-3xl bg-card p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">Socket contribution</h3>
                    <p class="text-sm text-muted-foreground">A compact breakdown of where the selected day energy went.</p>
                </div>
            </div>

            <div class="mt-5 space-y-3">
                @foreach($selectedDay['socket_stats'] as $socket)
                    <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold">{{ $socket['name'] }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Avg {{ number_format($socket['avg_power_w'], 1) }} W · Peak {{ number_format($socket['peak_power_w'], 1) }} W · Active {{ number_format($socket['active_minutes'], 1) }} min
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold tabular-nums">{{ number_format($socket['energy_kwh'], 4) }} kWh</p>
                                <p class="text-xs text-muted-foreground">{{ $socket['percentage'] }}%</p>
                            </div>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-card ring-1 ring-border/20">
                            <div class="h-full rounded-full bg-primary" style="width: {{ max(6, ((float) $socket['energy_kwh'] / $maxSocketEnergy) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-3xl bg-card p-6">
            <h3 class="text-lg font-bold">High-consumption intervals</h3>
            <p class="mt-1 text-sm text-muted-foreground">Useful for identifying repeated peaks, device sessions, or inefficient behaviors.</p>

            <div class="mt-5 space-y-3">
                @forelse($selectedDay['intervals'] as $interval)
                    <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold">{{ $interval['start'] }} - {{ $interval['end'] }}</p>
                            <p class="text-sm tabular-nums">{{ number_format($interval['energy_kwh'], 4) }} kWh</p>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ $interval['duration_minutes'] }} min · Avg {{ number_format($interval['avg_power_w'], 1) }} W</p>
                    </div>
                @empty
                    <div class="rounded-2xl bg-background p-4 text-sm text-muted-foreground ring-1 ring-border/30">
                        No significant active intervals were detected for this day.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-3xl bg-card p-6">
            <h3 class="text-lg font-bold">Hourly load map</h3>
            <p class="mt-1 text-sm text-muted-foreground">Good for spotting repeated peak hours or inefficient operating windows.</p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($selectedDay['hourly'] as $hour)
                    @php
                        $hasOverload = ($hour['warnings']['overload'] ?? 0) > 0;
                        $hasHigh = ($hour['warnings']['high'] ?? 0) > 0;
                    @endphp
                    <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold">{{ $hour['hour'] }}</p>
                            <span class="text-xs {{ $hasOverload ? 'text-red-300' : ($hasHigh ? 'text-amber-300' : 'text-muted-foreground') }}">
                                {{ $hasOverload ? 'overload' : ($hasHigh ? 'high load' : 'normal') }}
                            </span>
                        </div>
                        <p class="mt-3 text-lg font-semibold tabular-nums">{{ number_format($hour['energy_kwh'], 4) }} kWh</p>
                        <p class="mt-1 text-xs text-muted-foreground">Avg {{ number_format($hour['avg_power_w'], 1) }} W · Peak {{ number_format($hour['peak_power_w'], 1) }} W</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-3xl bg-card p-6">
            <h3 class="text-lg font-bold">Ideas to complete the thesis</h3>
            <p class="mt-1 text-sm text-muted-foreground">Features that would make the project more complete from a functional and academic perspective.</p>

            <div class="mt-5 space-y-3">
                @foreach($researchIdeas as $idea)
                    <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                        <p class="font-semibold">{{ $idea['title'] }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">{{ $idea['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endsection
