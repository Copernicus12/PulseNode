@extends('layouts.app')

@section('title', 'Devices')

@section('content')
@php
    $statePalette = [
        'matched' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/20',
        'unknown' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/20',
        'idle' => 'bg-muted text-muted-foreground ring-1 ring-border/40',
    ];
@endphp

<div class="space-y-5">
    @if(session('devices_success'))
        <div class="rounded-3xl bg-emerald-500/12 p-4 text-sm text-emerald-300 ring-1 ring-emerald-500/20">
            {{ session('devices_success') }}
        </div>
    @endif

    @if(session('devices_error'))
        <div class="rounded-3xl bg-red-500/12 p-4 text-sm text-red-300 ring-1 ring-red-500/20">
            {{ session('devices_error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-3xl bg-red-500/12 p-4 text-sm text-red-300 ring-1 ring-red-500/20">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="overflow-hidden rounded-[2rem] bg-card">
        <div class="relative p-7 sm:p-8">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(208,255,120,0.18),transparent_34%),radial-gradient(circle_at_80%_20%,rgba(110,168,255,0.16),transparent_26%),linear-gradient(135deg,rgba(255,255,255,0.03),transparent_58%)]"></div>
            <div class="relative grid gap-6 xl:grid-cols-[1.35fr_0.95fr] xl:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium {{ $isOnline ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/20' : 'bg-red-500/15 text-red-300 ring-1 ring-red-500/20' }}">
                            <span class="h-2 w-2 rounded-full {{ $isOnline ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                            {{ $isOnline ? 'Autodetect Online' : 'Autodetect Offline' }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-background/70 px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">
                            Device profiles + probabilistic recognition
                        </span>
                    </div>

                    <div class="mt-5 max-w-3xl">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Teach PulseNode what is plugged into each socket.</h2>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground sm:text-base">This page is now the semantic layer of the project: it builds device profiles from energy signatures, then tries to auto-detect what is currently connected using power, current, variability, and startup behavior.</p>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-4">
                        <div class="rounded-3xl bg-background/70 p-4 backdrop-blur-sm ring-1 ring-border/30">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-muted-foreground">Profiles</p>
                            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $detectionStats['trained_profiles'] }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Saved device signatures</p>
                        </div>
                        <div class="rounded-3xl bg-background/70 p-4 backdrop-blur-sm ring-1 ring-border/30">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-muted-foreground">Matched now</p>
                            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $detectionStats['matched_now'] }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Sockets recognized with confidence</p>
                        </div>
                        <div class="rounded-3xl bg-background/70 p-4 backdrop-blur-sm ring-1 ring-border/30">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-muted-foreground">Unknown now</p>
                            <p class="mt-3 text-3xl font-bold tabular-nums">{{ $detectionStats['unknown_now'] }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Candidates waiting for training</p>
                        </div>
                        <div class="rounded-3xl bg-background/70 p-4 backdrop-blur-sm ring-1 ring-border/30">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-muted-foreground">Signal</p>
                            <p class="mt-3 text-3xl font-bold tabular-nums">{{ number_format($totalPower, 1) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">W live draw · {{ $lastSeen }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="rounded-3xl border border-primary/15 bg-background/70 p-5 backdrop-blur-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">Recognition engine</p>
                                <p class="mt-1 text-xs text-muted-foreground">Uses recent `EnergySample` windows to extract a socket fingerprint.</p>
                            </div>
                            <span class="inline-flex rounded-full bg-primary/15 px-2.5 py-1 text-[11px] font-medium text-primary">MVP</span>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-card px-4 py-3 ring-1 ring-border/30">
                                <p class="text-[11px] text-muted-foreground">Voltage</p>
                                <p class="mt-1 text-lg font-bold tabular-nums">{{ number_format($voltage, 1) }} V</p>
                            </div>
                            <div class="rounded-2xl bg-card px-4 py-3 ring-1 ring-border/30">
                                <p class="text-[11px] text-muted-foreground">Current</p>
                                <p class="mt-1 text-lg font-bold tabular-nums">{{ number_format($totalCurrent, 3) }} A</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-primary p-5 text-primary-foreground">
                        <p class="text-sm font-semibold">Academic framing</p>
                        <p class="mt-2 text-sm opacity-80">This is not exact hardware identification. It is probabilistic classification based on energy-consumption signatures observed on each socket.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-3xl bg-card p-7">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">Live autodetect</h3>
                    <p class="text-sm text-muted-foreground">Each socket gets a signature from recent samples, then compared against trained profiles.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">{{ $detectionStats['active_signatures'] }}/3 active signatures</span>
            </div>

            <div class="mt-6 grid gap-5 lg:grid-cols-3">
                @foreach($socketCards as $socket)
                    @php
                        $detection = $socket['detection'];
                        $signature = $detection['signature'];
                    @endphp
                    <article class="overflow-hidden rounded-[2rem] bg-background ring-1 ring-border/30">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.22em] text-muted-foreground">Socket {{ $socket['index'] }}</p>
                                    <h4 class="mt-2 text-xl font-bold">{{ $socket['label'] }}</h4>
                                </div>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $statePalette[$detection['state']] ?? 'bg-muted text-muted-foreground' }}">
                                    {{ ucfirst($detection['state']) }}
                                </span>
                            </div>

                            <div class="mt-5 rounded-3xl bg-card p-4 ring-1 ring-border/20">
                                <p class="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Detected device</p>
                                <p class="mt-2 text-lg font-bold">{{ $detection['label'] }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ $detection['category'] }}</p>
                                <div class="mt-4 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>Confidence</span>
                                    <span class="font-semibold text-foreground">{{ $detection['confidence'] }}%</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-background ring-1 ring-border/20">
                                    <div class="h-full rounded-full {{ $detection['state'] === 'matched' ? 'bg-emerald-400' : ($detection['state'] === 'unknown' ? 'bg-amber-400' : 'bg-muted-foreground/40') }}" style="width: {{ $detection['confidence'] }}%"></div>
                                </div>
                                <p class="mt-3 text-xs leading-5 text-muted-foreground">{{ $detection['reason'] }}</p>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-2xl bg-card p-4 ring-1 ring-border/20">
                                    <p class="text-[11px] text-muted-foreground">Instant power</p>
                                    <p class="mt-1 text-lg font-bold tabular-nums">{{ number_format($socket['power_w'], 1) }} W</p>
                                </div>
                                <div class="rounded-2xl bg-card p-4 ring-1 ring-border/20">
                                    <p class="text-[11px] text-muted-foreground">Instant current</p>
                                    <p class="mt-1 text-lg font-bold tabular-nums">{{ number_format($socket['current'], 3) }} A</p>
                                </div>
                            </div>

                            <div class="mt-5 rounded-3xl bg-card p-4 ring-1 ring-border/20">
                                <p class="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">Signature snapshot</p>
                                @if($signature)
                                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <p class="text-muted-foreground">Avg power</p>
                                            <p class="mt-1 font-semibold tabular-nums">{{ number_format($signature['avg_power_w'], 1) }} W</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">Peak power</p>
                                            <p class="mt-1 font-semibold tabular-nums">{{ number_format($signature['peak_power_w'], 1) }} W</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">Variability</p>
                                            <p class="mt-1 font-semibold tabular-nums">{{ number_format($signature['variability_pct'], 1) }}%</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground">Startup ratio</p>
                                            <p class="mt-1 font-semibold tabular-nums">{{ number_format($signature['startup_ratio'], 2) }}x</p>
                                        </div>
                                    </div>
                                @else
                                    <p class="mt-3 text-sm text-muted-foreground">No active signature yet. Plug in a device and let PulseNode observe a few recent samples.</p>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('devices.profiles.store') }}" class="mt-5 space-y-3 rounded-3xl bg-card p-4 ring-1 ring-border/20">
                                @csrf
                                <input type="hidden" name="socket_index" value="{{ $socket['index'] }}">
                                <div>
                                    <p class="text-sm font-semibold">Teach this socket</p>
                                    <p class="mt-1 text-xs text-muted-foreground">Save the current signature as a reusable device profile.</p>
                                </div>
                                <input name="name" type="text" placeholder="Example: Office Monitor" class="h-11 w-full rounded-2xl border border-border/40 bg-background px-4 text-sm outline-none focus:border-primary/50" required>
                                <select name="category" class="h-11 w-full rounded-2xl border border-border/40 bg-background px-4 text-sm outline-none focus:border-primary/50" required>
                                    @foreach($profileCategories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                                <textarea name="notes" rows="3" placeholder="Optional notes about the device behavior or context" class="w-full rounded-2xl border border-border/40 bg-background px-4 py-3 text-sm outline-none focus:border-primary/50"></textarea>
                                <button type="submit" class="w-full rounded-2xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">Save current signature as profile</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-5">
            <div class="rounded-3xl bg-card p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold">Profile library</h3>
                        <p class="text-sm text-muted-foreground">Known device signatures trained from real socket activity.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">{{ $profiles->count() }} saved</span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($profiles as $profile)
                        <div class="rounded-3xl bg-background p-4 ring-1 ring-border/30">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">{{ $profile->name }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ $profile->category }} · trained from socket {{ $profile->trained_from_socket ?? 'n/a' }}</p>
                                </div>
                                <span class="inline-flex rounded-full bg-primary/15 px-2.5 py-1 text-[11px] font-medium text-primary">{{ number_format($profile->avg_power_w, 1) }} W</span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-muted-foreground">Peak</p>
                                    <p class="mt-1 font-semibold tabular-nums">{{ number_format($profile->peak_power_w, 1) }} W</p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Variability</p>
                                    <p class="mt-1 font-semibold tabular-nums">{{ number_format($profile->variability_pct, 1) }}%</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl bg-background p-5 text-sm text-muted-foreground ring-1 ring-border/30">
                            No device profiles yet. Train the first one from any socket card.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl bg-card p-7">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold">Recent detections</h3>
                        <p class="text-sm text-muted-foreground">Latest recognition events recorded by the autodetect engine.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse($recentDetections as $event)
                        <div class="rounded-3xl bg-background p-4 ring-1 ring-border/30">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">Socket {{ $event->socket_index }} · {{ $event->predicted_label }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ $event->predicted_category ?? 'Unknown' }} · {{ $event->detected_at?->diffForHumans() }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $statePalette[$event->status] ?? 'bg-muted text-muted-foreground' }}">{{ $event->confidence }}%</span>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-3xl bg-background p-5 text-sm text-muted-foreground ring-1 ring-border/30">
                            No detection history yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
