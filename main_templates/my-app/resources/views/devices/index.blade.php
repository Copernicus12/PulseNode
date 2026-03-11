@extends('layouts.app')

@section('title', 'My Devices')

@section('content')
@php
    $detectionStateClasses = [
        'matched' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30',
        'unknown' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/30',
        'idle' => 'bg-muted text-muted-foreground ring-1 ring-border/40',
    ];

    $scopeLabel = static function (?int $scope): string {
        return $scope ? 'Socket '.$scope : 'All sockets';
    };

    $currentDevicesRoute = request()->route()?->getName() ?? 'devices.index';
    $deviceSection = $deviceSection ?? 'overview';
    $deviceSectionMeta = $deviceSectionMeta ?? [
        'label' => 'Overview',
        'description' => 'Live signatures, current matches, and quick profile training.',
    ];
@endphp

<div class="mx-auto max-w-[1360px] space-y-5 lg:space-y-6">
    @if(session('devices_success'))
        <div class="rounded-2xl bg-emerald-500/12 px-4 py-3 text-sm text-emerald-300 ring-1 ring-emerald-500/25">
            {{ session('devices_success') }}
        </div>
    @endif

    @if(session('devices_error'))
        <div class="rounded-2xl bg-red-500/12 px-4 py-3 text-sm text-red-300 ring-1 ring-red-500/25">
            {{ session('devices_error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl bg-red-500/12 px-4 py-3 text-sm text-red-300 ring-1 ring-red-500/25">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="relative overflow-hidden rounded-3xl bg-card p-5 sm:p-6">
        <div class="pointer-events-none absolute inset-0 bg-linear-to-r from-primary/5 via-transparent to-transparent"></div>

        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">My Devices</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ $deviceSectionMeta['description'] ?? '' }}</p>
            </div>
            <div class="rounded-2xl bg-background px-4 py-3 text-xs text-muted-foreground ring-1 ring-border/40">
                Last sync: <span id="devices-last-sync" class="font-medium text-foreground">{{ $lastSeen }}</span>
            </div>
        </div>

        <div class="relative mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-xs text-muted-foreground">Profiles</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $detectionStats['trained_profiles'] }}</p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-xs text-muted-foreground">Matched now</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $detectionStats['matched_now'] }}</p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-xs text-muted-foreground">Unknown now</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $detectionStats['unknown_now'] }}</p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-xs text-muted-foreground">Active signatures</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $detectionStats['active_signatures'] }}/3</p>
            </div>
            <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                <p class="text-xs text-muted-foreground">Active plans</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $detectionStats['active_plans'] }}</p>
            </div>
        </div>
    </section>

    @if($deviceSection === 'overview')
        <section class="space-y-4">
            <div class="rounded-3xl bg-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold">Live Detection by Socket</h3>
                        <p class="text-sm text-muted-foreground">Current device guess, confidence, and signature per socket.</p>
                    </div>
                    <p class="text-xs text-muted-foreground">Cards auto-refresh from live telemetry.</p>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($socketCards as $socket)
                        @php
                            $detection = $socket['detection'];
                            $signature = $detection['signature'];
                            $plan = $detection['plan'];
                        @endphp

                        <article class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Socket {{ $socket['index'] }}</p>
                                    <h4 class="mt-1 text-base font-semibold">{{ $socket['label'] }}</h4>
                                </div>
                                <span id="devices-state-badge-{{ $socket['index'] }}" class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $detectionStateClasses[$detection['state']] ?? 'bg-muted text-muted-foreground ring-1 ring-border/40' }}">
                                    {{ ucfirst($detection['state']) }}
                                </span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <p><span id="devices-socket-power-{{ $socket['index'] }}">{{ number_format($socket['power_w'], 1) }}</span> W</p>
                                <p><span id="devices-socket-current-{{ $socket['index'] }}">{{ number_format($socket['current'], 3) }}</span> A</p>
                            </div>

                            <div class="mt-3">
                                <p id="devices-detection-label-{{ $socket['index'] }}" class="text-sm font-semibold">{{ $detection['label'] }}</p>
                                <p id="devices-detection-category-{{ $socket['index'] }}" class="text-xs text-muted-foreground">{{ $detection['category'] }}</p>
                            </div>

                            <div class="mt-3 flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">Confidence</span>
                                <span id="devices-confidence-text-{{ $socket['index'] }}" class="font-medium text-foreground">{{ $detection['confidence'] }}%</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-card ring-1 ring-border/20">
                                <div id="devices-confidence-bar-{{ $socket['index'] }}" class="h-full rounded-full {{ $detection['state'] === 'matched' ? 'bg-emerald-400' : ($detection['state'] === 'unknown' ? 'bg-amber-400' : 'bg-muted-foreground/40') }}" style="width: {{ $detection['confidence'] }}%"></div>
                            </div>

                            <p id="devices-detection-reason-{{ $socket['index'] }}" class="hidden">{{ $detection['reason'] }}</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    data-modal-open="socket-details-modal"
                                    data-socket-index="{{ $socket['index'] }}"
                                    data-socket-label="Socket {{ $socket['index'] }}"
                                    data-plan-name="{{ $plan?->name ?? '' }}"
                                    data-plan-meta="{{ $plan ? ucfirst($plan->strategy).' | '.$scopeLabel($plan->socket_scope).' | threshold '.$plan->match_threshold.'%' : '' }}"
                                    data-plan-empty="No active plan. Default balanced detection is used."
                                    data-signature-available="{{ $signature ? '1' : '0' }}"
                                    data-signature-avg="{{ $signature ? number_format($signature['avg_power_w'], 1).' W' : '' }}"
                                    data-signature-peak="{{ $signature ? number_format($signature['peak_power_w'], 1).' W' : '' }}"
                                    data-signature-variability="{{ $signature ? number_format($signature['variability_pct'], 1).'%' : '' }}"
                                    data-signature-startup="{{ $signature ? number_format($signature['startup_ratio'], 2).'x' : '' }}"
                                    data-required-samples="{{ $detection['required_samples'] ?? 3 }}"
                                    class="rounded-xl bg-card px-3.5 py-2 text-xs font-medium text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground"
                                >
                                    Details
                                </button>
                                <button
                                    type="button"
                                    data-modal-open="train-profile-modal"
                                    data-socket-index="{{ $socket['index'] }}"
                                    data-socket-label="Socket {{ $socket['index'] }}"
                                    class="rounded-xl bg-primary px-3.5 py-2 text-xs font-medium text-primary-foreground transition hover:opacity-90"
                                >
                                    Train profile
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <section class="rounded-3xl bg-card p-5 sm:p-6">
                <h3 class="text-lg font-bold">Latest Events</h3>
                <p class="mt-1 text-sm text-muted-foreground">Last {{ $recentDetections->count() }} recognitions.</p>

                <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($recentDetections as $event)
                        <div class="rounded-2xl bg-background p-3 ring-1 ring-border/30">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold">{{ $event->predicted_label }}</p>
                                    <p class="text-xs text-muted-foreground">Socket {{ $event->socket_index }} · {{ $event->detected_at?->diffForHumans() }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $detectionStateClasses[$event->status] ?? 'bg-muted text-muted-foreground ring-1 ring-border/40' }}">{{ ucfirst($event->status) }}</span>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">Confidence {{ $event->confidence }}% · {{ $event->plan?->name ?? 'Default plan' }}</p>
                        </div>
                    @empty
                        <div class="rounded-2xl bg-background p-4 text-sm text-muted-foreground ring-1 ring-border/30 md:col-span-2 xl:col-span-3">
                            No detection events recorded yet.
                        </div>
                    @endforelse
                </div>
            </section>
        </section>

        <div id="socket-details-modal" data-modal class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
            <div class="w-full max-w-2xl rounded-3xl bg-card p-6 sm:p-7 ring-1 ring-border/50">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Socket details</p>
                        <h4 id="socket-details-title" class="mt-1 text-lg font-semibold">Socket</h4>
                        <p class="mt-1 text-sm text-muted-foreground">Expanded view with detection, plan, and signature details.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="socket-details-state" class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium">Idle</span>
                        <button type="button" data-modal-close class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-background text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">
                            <span class="sr-only">Close</span>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                        <p class="text-xs text-muted-foreground">Power</p>
                        <p id="socket-details-power" class="mt-1 text-sm font-semibold">0.0 W</p>
                    </div>
                    <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                        <p class="text-xs text-muted-foreground">Current</p>
                        <p id="socket-details-current" class="mt-1 text-sm font-semibold">0.000 A</p>
                    </div>
                    <div class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                        <p class="text-xs text-muted-foreground">Confidence</p>
                        <p id="socket-details-confidence" class="mt-1 text-sm font-semibold">0%</p>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-xs font-medium text-muted-foreground">Detection</p>
                    <p id="socket-details-label" class="mt-1 text-sm font-semibold">Unknown device</p>
                    <p id="socket-details-category" class="text-xs text-muted-foreground">Unknown</p>
                    <p id="socket-details-reason" class="mt-2 text-xs text-muted-foreground">No detection reason available.</p>
                </div>

                <div class="mt-4 rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <p class="text-xs font-medium text-muted-foreground">Detection plan</p>
                    <p id="socket-details-plan-name" class="mt-1 text-sm font-semibold">Default plan</p>
                    <p id="socket-details-plan-meta" class="mt-0.5 text-xs text-muted-foreground">No active plan. Default balanced detection is used.</p>
                </div>

                <div class="mt-4 rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-medium text-muted-foreground">Signature snapshot</p>
                        <p id="socket-details-signature-fallback" class="hidden text-xs text-muted-foreground">Not enough samples.</p>
                    </div>
                    <div id="socket-details-signature-grid" class="mt-2.5 grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <p class="text-muted-foreground">Avg power</p>
                            <p id="socket-details-signature-avg" class="font-medium tabular-nums">-</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Peak power</p>
                            <p id="socket-details-signature-peak" class="font-medium tabular-nums">-</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Variability</p>
                            <p id="socket-details-signature-variability" class="font-medium tabular-nums">-</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Startup ratio</p>
                            <p id="socket-details-signature-startup" class="font-medium tabular-nums">-</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-xl bg-background px-3.5 py-2 text-sm text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">Close</button>
                    <button
                        id="socket-details-train-profile-button"
                        type="button"
                        data-modal-open="train-profile-modal"
                        data-socket-index=""
                        data-socket-label=""
                        class="rounded-xl bg-primary px-3.5 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Train profile
                    </button>
                </div>
            </div>
        </div>

        <div id="train-profile-modal" data-modal class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-3xl bg-card p-6 sm:p-7 ring-1 ring-border/50">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Train profile</p>
                        <h4 class="mt-1 text-lg font-semibold">Capture current socket signature</h4>
                        <p class="mt-1 text-sm text-muted-foreground">Source: <span data-train-profile-socket class="font-medium text-foreground">Socket</span></p>
                    </div>
                    <button type="button" data-modal-close class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-background text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">
                        <span class="sr-only">Close</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('devices.profiles.store') }}" class="mt-5 space-y-3">
                    @csrf
                    <input id="train-profile-socket-index" type="hidden" name="socket_index" value="">
                    <input type="hidden" name="redirect_route" value="{{ $currentDevicesRoute }}">

                    <div>
                        <label class="text-xs text-muted-foreground">Device name</label>
                        <input data-modal-initial-focus name="name" type="text" placeholder="Laptop charger" class="mt-1 h-11 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground">Category</label>
                        <select name="category" class="mt-1 h-11 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                            @foreach($profileCategories as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground">Notes</label>
                        <textarea name="notes" rows="3" placeholder="Optional context for this signature" class="mt-1 w-full rounded-xl border border-border/40 bg-background px-3 py-2 text-sm outline-none focus:border-primary/50"></textarea>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 pt-1">
                        <button type="button" data-modal-close class="rounded-xl bg-background px-3.5 py-2 text-sm text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">Cancel</button>
                        <button type="submit" class="rounded-xl bg-primary px-3.5 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">Save profile</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($deviceSection === 'profiles')
        <section class="space-y-4">
            <div class="rounded-3xl bg-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold">Profile Library</h3>
                        <p class="text-sm text-muted-foreground">Saved device signatures used for matching.</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">{{ $profiles->count() }} profiles</span>
                </div>

                @if($profileBreakdown->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($profileBreakdown as $category => $count)
                            <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs text-muted-foreground ring-1 ring-border/30">{{ $category }}: {{ $count }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-3xl bg-card p-3 sm:p-4">
                <div class="overflow-x-auto rounded-2xl bg-background ring-1 ring-border/30">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr class="border-b border-border/30 text-left text-xs text-muted-foreground">
                                <th class="px-3 py-2.5 font-medium">Name</th>
                                <th class="px-3 py-2.5 font-medium">Category</th>
                                <th class="px-3 py-2.5 font-medium">Trained from</th>
                                <th class="px-3 py-2.5 font-medium">Avg / Peak</th>
                                <th class="px-3 py-2.5 font-medium">Range</th>
                                <th class="px-3 py-2.5 font-medium">Updated</th>
                                <th class="px-3 py-2.5 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($profiles as $profile)
                                <tr class="border-b border-border/20 align-top">
                                    <td class="px-3 py-2.5 font-medium">{{ $profile->name }}</td>
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ $profile->category }}</td>
                                    <td class="px-3 py-2.5 text-muted-foreground">Socket {{ $profile->trained_from_socket ?? 'n/a' }}</td>
                                    <td class="px-3 py-2.5 tabular-nums">{{ number_format($profile->avg_power_w, 1) }} / {{ number_format($profile->peak_power_w, 1) }} W</td>
                                    <td class="px-3 py-2.5 tabular-nums text-muted-foreground">{{ number_format($profile->expected_power_min, 1) }} - {{ number_format($profile->expected_power_max, 1) }} W</td>
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ $profile->last_trained_at?->diffForHumans() ?? '-' }}</td>
                                    <td class="px-3 py-2.5">
                                        <form method="POST" action="{{ route('devices.profiles.destroy', $profile) }}" onsubmit="return confirm('Delete this profile?')">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="redirect_route" value="{{ $currentDevicesRoute }}">
                                            <button type="submit" class="rounded-lg bg-red-500/15 px-3 py-1.5 text-xs font-medium text-red-300 transition hover:bg-red-500/25">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-4 text-sm text-muted-foreground">No profiles trained yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    @if($deviceSection === 'plans')
        <section class="space-y-4">
            <div class="rounded-3xl bg-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold">Detection Plans</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Define how strict recognition should be per socket or globally.</p>
                    </div>
                    <button type="button" data-modal-open="create-plan-modal" class="rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                        Create plan
                    </button>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs text-muted-foreground ring-1 ring-border/30">{{ $detectionPlans->count() }} total plans</span>
                    <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs text-muted-foreground ring-1 ring-border/30">{{ $detectionStats['active_plans'] }} active</span>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                @forelse($detectionPlans as $plan)
                    <article class="rounded-3xl bg-card p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-base font-semibold">{{ $plan->name }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ ucfirst($plan->strategy) }} strategy</p>
                            </div>
                            @if($plan->is_active)
                                <span class="inline-flex rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-medium text-emerald-300 ring-1 ring-emerald-500/30">Active</span>
                            @endif
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 rounded-2xl bg-background p-3 text-xs ring-1 ring-border/30 sm:grid-cols-4">
                            <div>
                                <p class="text-muted-foreground">Scope</p>
                                <p class="mt-1 font-medium">{{ $scopeLabel($plan->socket_scope) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Window</p>
                                <p class="mt-1 font-medium tabular-nums">{{ $plan->window_samples }} samples</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Min samples</p>
                                <p class="mt-1 font-medium tabular-nums">{{ $plan->min_samples }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Threshold</p>
                                <p class="mt-1 font-medium tabular-nums">{{ $plan->match_threshold }}%</p>
                            </div>
                        </div>

                        @if($plan->notes)
                            <p class="mt-3 text-xs text-muted-foreground">{{ $plan->notes }}</p>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if(!$plan->is_active)
                                <form method="POST" action="{{ route('devices.plans.activate', $plan) }}">
                                    @csrf
                                    <input type="hidden" name="redirect_route" value="{{ $currentDevicesRoute }}">
                                    <button type="submit" class="rounded-xl bg-primary/15 px-3 py-1.5 text-xs font-medium text-primary transition hover:bg-primary/25">Activate</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('devices.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this detection plan?')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="redirect_route" value="{{ $currentDevicesRoute }}">
                                <button type="submit" class="rounded-xl bg-red-500/15 px-3 py-1.5 text-xs font-medium text-red-300 transition hover:bg-red-500/25">Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl bg-card p-5 text-sm text-muted-foreground ring-1 ring-border/30 md:col-span-2 2xl:col-span-3">
                        No detection plans yet. Create one to control matching behavior.
                    </div>
                @endforelse
            </div>
        </section>

        <div id="create-plan-modal" data-modal class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/65 p-4 backdrop-blur-sm">
            <div class="w-full max-w-2xl rounded-3xl bg-card p-6 sm:p-7 ring-1 ring-border/50">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Detection plan</p>
                        <h4 class="mt-1 text-lg font-semibold">Create new matching profile</h4>
                        <p class="mt-1 text-sm text-muted-foreground">Use this modal to keep the page cleaner while still allowing quick setup.</p>
                    </div>
                    <button type="button" data-modal-close class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-background text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">
                        <span class="sr-only">Close</span>
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('devices.plans.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="redirect_route" value="{{ $currentDevicesRoute }}">

                    <div>
                        <label class="text-xs text-muted-foreground">Plan name</label>
                        <input data-modal-initial-focus name="name" type="text" class="mt-1 h-11 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs text-muted-foreground">Strategy</label>
                            <select name="strategy" class="mt-1 h-11 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                                <option value="fast">Fast</option>
                                <option value="balanced" selected>Balanced</option>
                                <option value="strict">Strict</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Socket scope</label>
                            <select name="socket_scope" class="mt-1 h-11 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50">
                                <option value="">All sockets</option>
                                <option value="1">Socket 1</option>
                                <option value="2">Socket 2</option>
                                <option value="3">Socket 3</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="text-xs text-muted-foreground">Window</label>
                            <input name="window_samples" type="number" min="30" max="240" value="90" class="mt-1 h-11 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Min samples</label>
                            <input name="min_samples" type="number" min="2" max="30" value="3" class="mt-1 h-11 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Threshold %</label>
                            <input name="match_threshold" type="number" min="40" max="95" value="68" class="mt-1 h-11 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-muted-foreground">Notes</label>
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-border/40 bg-background px-3 py-2 text-sm outline-none focus:border-primary/50"></textarea>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-muted-foreground">
                        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-border/40 bg-background text-primary focus:ring-primary/30">
                        Activate immediately
                    </label>

                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" data-modal-close class="rounded-xl bg-background px-3.5 py-2 text-sm text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">Cancel</button>
                        <button type="submit" class="rounded-xl bg-primary px-3.5 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">Create detection plan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($deviceSection === 'activity')
        <section class="space-y-4">
            <div class="rounded-3xl bg-card p-5 sm:p-6">
                <h3 class="text-lg font-bold">Current Socket Status</h3>
                <p class="mt-1 text-sm text-muted-foreground">Live snapshot for each socket before drilling into event history.</p>

                <div class="mt-4 overflow-x-auto pb-1">
                    <div class="mx-auto flex w-max gap-3">
                    @foreach($socketCards as $socket)
                        @php $detection = $socket['detection']; @endphp
                        <article class="w-[300px] shrink-0 rounded-2xl bg-background p-4 ring-1 ring-border/30">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.14em] text-muted-foreground">Socket {{ $socket['index'] }}</p>
                                    <p class="mt-1 text-sm font-semibold" id="devices-detection-label-{{ $socket['index'] }}">{{ $detection['label'] }}</p>
                                    <p class="text-xs text-muted-foreground" id="devices-detection-category-{{ $socket['index'] }}">{{ $detection['category'] }}</p>
                                </div>
                                <span id="devices-state-badge-{{ $socket['index'] }}" class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $detectionStateClasses[$detection['state']] ?? 'bg-muted text-muted-foreground ring-1 ring-border/40' }}">{{ ucfirst($detection['state']) }}</span>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                <span id="devices-socket-power-{{ $socket['index'] }}">{{ number_format($socket['power_w'], 1) }}</span> W
                                |
                                <span id="devices-socket-current-{{ $socket['index'] }}">{{ number_format($socket['current'], 3) }}</span> A
                            </p>
                            <div class="mt-3 flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">Confidence</span>
                                <span class="font-medium text-foreground" id="devices-confidence-text-{{ $socket['index'] }}">{{ $detection['confidence'] }}%</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-card ring-1 ring-border/20">
                                <div id="devices-confidence-bar-{{ $socket['index'] }}" class="h-full rounded-full {{ $detection['state'] === 'matched' ? 'bg-emerald-400' : ($detection['state'] === 'unknown' ? 'bg-amber-400' : 'bg-muted-foreground/40') }}" style="width: {{ $detection['confidence'] }}%"></div>
                            </div>
                            <p class="mt-2.5 text-xs text-muted-foreground" id="devices-detection-reason-{{ $socket['index'] }}">{{ $detection['reason'] }}</p>
                        </article>
                    @endforeach
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-card p-5 sm:p-6">
                <h3 class="text-lg font-bold">Recent Detection Events</h3>
                <p class="mt-1 text-sm text-muted-foreground">Latest recognition updates saved by the detection engine.</p>

                <div class="mt-4 overflow-x-auto rounded-2xl bg-background ring-1 ring-border/30">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b border-border/30 text-left text-xs text-muted-foreground">
                                <th class="px-3 py-2.5 font-medium">Time</th>
                                <th class="px-3 py-2.5 font-medium">Socket</th>
                                <th class="px-3 py-2.5 font-medium">Label</th>
                                <th class="px-3 py-2.5 font-medium">Category</th>
                                <th class="px-3 py-2.5 font-medium">Confidence</th>
                                <th class="px-3 py-2.5 font-medium">Plan</th>
                                <th class="px-3 py-2.5 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentDetections as $event)
                                <tr class="border-b border-border/20">
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ $event->detected_at?->diffForHumans() }}</td>
                                    <td class="px-3 py-2.5">{{ $event->socket_index }}</td>
                                    <td class="px-3 py-2.5 font-medium">{{ $event->predicted_label }}</td>
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ $event->predicted_category ?? '-' }}</td>
                                    <td class="px-3 py-2.5 tabular-nums">{{ $event->confidence }}%</td>
                                    <td class="px-3 py-2.5 text-muted-foreground">{{ $event->plan?->name ?? 'Default' }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $detectionStateClasses[$event->status] ?? 'bg-muted text-muted-foreground ring-1 ring-border/40' }}">{{ $event->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-4 text-sm text-muted-foreground">No detection events recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
</div>

<script>
(function () {
    var detectionFetchInFlight = false;
    var lastDetectionFetchAt = 0;

    function asNumber(v) {
        var n = Number(v);
        return Number.isFinite(n) ? n : 0;
    }

    function stateBadgeClass(state) {
        if (state === 'matched') {
            return 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30';
        }

        if (state === 'unknown') {
            return 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/30';
        }

        return 'bg-muted text-muted-foreground ring-1 ring-border/40';
    }

    function stateFillClass(state) {
        if (state === 'matched') {
            return 'bg-emerald-400';
        }

        if (state === 'unknown') {
            return 'bg-amber-400';
        }

        return 'bg-muted-foreground/40';
    }

    function stateLabel(state) {
        var value = String(state || 'idle');
        return value.charAt(0).toUpperCase() + value.slice(1);
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

    function applyDetection(detection) {
        var socketIndex = asNumber(detection.socket_index);
        if (!socketIndex) {
            return;
        }

        var state = String(detection.state || 'idle');
        var confidence = Math.max(0, Math.min(99, Math.round(asNumber(detection.confidence))));

        var badgeEl = document.getElementById('devices-state-badge-' + socketIndex);
        if (badgeEl) {
            badgeEl.className = 'inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium ' + stateBadgeClass(state);
            badgeEl.textContent = stateLabel(state);
        }

        var labelEl = document.getElementById('devices-detection-label-' + socketIndex);
        if (labelEl) {
            labelEl.textContent = detection.label || 'Unknown device';
        }

        var categoryEl = document.getElementById('devices-detection-category-' + socketIndex);
        if (categoryEl) {
            categoryEl.textContent = detection.category || 'Unknown';
        }

        var confidenceEl = document.getElementById('devices-confidence-text-' + socketIndex);
        if (confidenceEl) {
            confidenceEl.textContent = confidence + '%';
        }

        var barEl = document.getElementById('devices-confidence-bar-' + socketIndex);
        if (barEl) {
            barEl.classList.remove('bg-emerald-400', 'bg-amber-400', 'bg-muted-foreground/40');
            barEl.classList.add(stateFillClass(state));
            barEl.style.width = confidence + '%';
        }

        var reasonEl = document.getElementById('devices-detection-reason-' + socketIndex);
        if (reasonEl) {
            reasonEl.textContent = detection.reason || 'No detection reason available.';
        }
    }

    function fetchLiveDetections(force) {
        var now = Date.now();
        if (!force && now - lastDetectionFetchAt < 3000) {
            return;
        }

        if (detectionFetchInFlight) {
            return;
        }

        detectionFetchInFlight = true;
        lastDetectionFetchAt = now;

        fetch('/api/devices/live-detections', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('live detection fetch failed');
                return response.json();
            })
            .then(function (payload) {
                var detections = Array.isArray(payload && payload.detections) ? payload.detections : [];
                detections.forEach(applyDetection);
            })
            .catch(function () {})
            .finally(function () {
                detectionFetchInFlight = false;
            });
    }

    window.addEventListener('pulsenode:latest', function (event) {
        var data = event.detail || {};
        var voltage = asNumber(data.voltage || 230);

        [1, 2, 3].forEach(function (socketIndex) {
            var current = asNumber(data['current_' + socketIndex]);
            var power = voltage * current;

            var currentEl = document.getElementById('devices-socket-current-' + socketIndex);
            if (currentEl) currentEl.textContent = current.toFixed(3);

            var powerEl = document.getElementById('devices-socket-power-' + socketIndex);
            if (powerEl) powerEl.textContent = power.toFixed(1);
        });

        var lastSyncEl = document.getElementById('devices-last-sync');
        if (lastSyncEl) lastSyncEl.textContent = lastSeenLabel(data.updated_at);

        fetchLiveDetections(false);
    });

    fetchLiveDetections(true);
})();

(function () {
    function allModals() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-modal]'));
    }

    function isOpen(modal) {
        return modal && !modal.classList.contains('hidden');
    }

    function openModal(modal) {
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');

        var focusTarget = modal.querySelector('[data-modal-initial-focus]');
        if (focusTarget) {
            setTimeout(function () { focusTarget.focus(); }, 20);
        }
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        var anyOpen = allModals().some(function (entry) { return isOpen(entry); });
        if (!anyOpen) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    function textFromElement(id, fallback) {
        var el = document.getElementById(id);
        if (!el) return fallback;
        return (el.textContent || '').trim() || fallback;
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-modal-open]')).forEach(function (button) {
        button.addEventListener('click', function () {
            var modalId = button.getAttribute('data-modal-open');
            var modal = document.getElementById(modalId);
            if (!modal) return;

            allModals().forEach(function (entry) {
                if (entry !== modal && isOpen(entry)) {
                    closeModal(entry);
                }
            });

            if (modalId === 'train-profile-modal') {
                var socketInput = modal.querySelector('#train-profile-socket-index');
                var socketLabel = modal.querySelector('[data-train-profile-socket]');
                var socketIndex = button.getAttribute('data-socket-index') || '';
                var readableSocket = button.getAttribute('data-socket-label') || ('Socket ' + socketIndex);

                if (socketInput) socketInput.value = socketIndex;
                if (socketLabel) socketLabel.textContent = readableSocket;
            }

            if (modalId === 'socket-details-modal') {
                var detailsSocketIndex = button.getAttribute('data-socket-index') || '';
                var detailsSocketLabel = button.getAttribute('data-socket-label') || ('Socket ' + detailsSocketIndex);

                var detailsTitleEl = document.getElementById('socket-details-title');
                if (detailsTitleEl) detailsTitleEl.textContent = detailsSocketLabel;

                var detailsStateEl = document.getElementById('socket-details-state');
                var sourceStateEl = document.getElementById('devices-state-badge-' + detailsSocketIndex);
                if (detailsStateEl && sourceStateEl) {
                    detailsStateEl.className = sourceStateEl.className;
                    detailsStateEl.textContent = sourceStateEl.textContent;
                }

                var detailsPowerEl = document.getElementById('socket-details-power');
                if (detailsPowerEl) detailsPowerEl.textContent = textFromElement('devices-socket-power-' + detailsSocketIndex, '0.0') + ' W';

                var detailsCurrentEl = document.getElementById('socket-details-current');
                if (detailsCurrentEl) detailsCurrentEl.textContent = textFromElement('devices-socket-current-' + detailsSocketIndex, '0.000') + ' A';

                var detailsConfidenceEl = document.getElementById('socket-details-confidence');
                if (detailsConfidenceEl) detailsConfidenceEl.textContent = textFromElement('devices-confidence-text-' + detailsSocketIndex, '0%');

                var detailsLabelEl = document.getElementById('socket-details-label');
                if (detailsLabelEl) detailsLabelEl.textContent = textFromElement('devices-detection-label-' + detailsSocketIndex, 'Unknown device');

                var detailsCategoryEl = document.getElementById('socket-details-category');
                if (detailsCategoryEl) detailsCategoryEl.textContent = textFromElement('devices-detection-category-' + detailsSocketIndex, 'Unknown');

                var detailsReasonEl = document.getElementById('socket-details-reason');
                if (detailsReasonEl) detailsReasonEl.textContent = textFromElement('devices-detection-reason-' + detailsSocketIndex, 'No detection reason available.');

                var detailsPlanNameEl = document.getElementById('socket-details-plan-name');
                if (detailsPlanNameEl) {
                    detailsPlanNameEl.textContent = button.getAttribute('data-plan-name') || 'Default plan';
                }

                var detailsPlanMetaEl = document.getElementById('socket-details-plan-meta');
                if (detailsPlanMetaEl) {
                    detailsPlanMetaEl.textContent = button.getAttribute('data-plan-meta') || button.getAttribute('data-plan-empty') || 'No active plan. Default balanced detection is used.';
                }

                var signatureGridEl = document.getElementById('socket-details-signature-grid');
                var signatureFallbackEl = document.getElementById('socket-details-signature-fallback');
                var hasSignature = button.getAttribute('data-signature-available') === '1';
                var requiredSamples = button.getAttribute('data-required-samples') || '3';

                if (signatureGridEl && signatureFallbackEl) {
                    if (hasSignature) {
                        signatureGridEl.classList.remove('hidden');
                        signatureFallbackEl.classList.add('hidden');
                    } else {
                        signatureGridEl.classList.add('hidden');
                        signatureFallbackEl.classList.remove('hidden');
                        signatureFallbackEl.textContent = 'Not enough samples (min ' + requiredSamples + ') to build a fingerprint.';
                    }
                }

                var detailsSignatureAvgEl = document.getElementById('socket-details-signature-avg');
                if (detailsSignatureAvgEl) detailsSignatureAvgEl.textContent = button.getAttribute('data-signature-avg') || '-';

                var detailsSignaturePeakEl = document.getElementById('socket-details-signature-peak');
                if (detailsSignaturePeakEl) detailsSignaturePeakEl.textContent = button.getAttribute('data-signature-peak') || '-';

                var detailsSignatureVariabilityEl = document.getElementById('socket-details-signature-variability');
                if (detailsSignatureVariabilityEl) detailsSignatureVariabilityEl.textContent = button.getAttribute('data-signature-variability') || '-';

                var detailsSignatureStartupEl = document.getElementById('socket-details-signature-startup');
                if (detailsSignatureStartupEl) detailsSignatureStartupEl.textContent = button.getAttribute('data-signature-startup') || '-';

                var detailsTrainButton = document.getElementById('socket-details-train-profile-button');
                if (detailsTrainButton) {
                    detailsTrainButton.setAttribute('data-socket-index', detailsSocketIndex);
                    detailsTrainButton.setAttribute('data-socket-label', detailsSocketLabel);
                }
            }

            openModal(modal);
        });
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-modal-close]')).forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = button.closest('[data-modal]');
            closeModal(modal);
        });
    });

    allModals().forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        allModals().forEach(function (modal) {
            if (isOpen(modal)) closeModal(modal);
        });
    });
})();
</script>
@endsection
