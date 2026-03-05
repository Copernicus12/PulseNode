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
@endphp

<div class="space-y-5">
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

    <section class="rounded-3xl bg-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">My Devices</h2>
                <p class="mt-1 text-sm text-muted-foreground">Manage trained profiles and control detection plans used by the recognition engine.</p>
            </div>
            <div class="rounded-2xl bg-background px-4 py-3 text-xs text-muted-foreground ring-1 ring-border/40">
                Last sync: <span id="devices-last-sync" class="font-medium text-foreground">{{ $lastSeen }}</span>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
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

    <section class="grid gap-5 xl:grid-cols-[1.35fr_0.65fr]">
        <div class="rounded-3xl bg-card p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">Live Detection by Socket</h3>
                    <p class="text-sm text-muted-foreground">Current device guess, confidence, and signature per socket.</p>
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
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
                                <p class="text-xs text-muted-foreground">
                                    <span id="devices-socket-power-{{ $socket['index'] }}">{{ number_format($socket['power_w'], 1) }}</span> W
                                    |
                                    <span id="devices-socket-current-{{ $socket['index'] }}">{{ number_format($socket['current'], 3) }}</span> A
                                </p>
                            </div>
                            <span id="devices-state-badge-{{ $socket['index'] }}" class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $detectionStateClasses[$detection['state']] ?? 'bg-muted text-muted-foreground' }}">
                                {{ ucfirst($detection['state']) }}
                            </span>
                        </div>

                        <div class="mt-4 rounded-xl bg-card px-3 py-3 ring-1 ring-border/20">
                            <p id="devices-detection-label-{{ $socket['index'] }}" class="text-sm font-semibold">{{ $detection['label'] }}</p>
                            <p id="devices-detection-category-{{ $socket['index'] }}" class="mt-0.5 text-xs text-muted-foreground">{{ $detection['category'] }}</p>
                            <div class="mt-2 flex items-center justify-between text-xs">
                                <span class="text-muted-foreground">Confidence</span>
                                <span id="devices-confidence-text-{{ $socket['index'] }}" class="font-medium text-foreground">{{ $detection['confidence'] }}%</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-background ring-1 ring-border/20">
                                <div id="devices-confidence-bar-{{ $socket['index'] }}" class="h-full rounded-full {{ $detection['state'] === 'matched' ? 'bg-emerald-400' : ($detection['state'] === 'unknown' ? 'bg-amber-400' : 'bg-muted-foreground/40') }}" style="width: {{ $detection['confidence'] }}%"></div>
                            </div>
                            <p id="devices-detection-reason-{{ $socket['index'] }}" class="mt-2 text-xs text-muted-foreground">{{ $detection['reason'] }}</p>
                        </div>

                        <div class="mt-3 rounded-xl bg-card px-3 py-3 ring-1 ring-border/20">
                            <p class="text-xs font-medium text-muted-foreground">Detection plan</p>
                            @if($plan)
                                <p class="mt-1 text-sm font-semibold">{{ $plan->name }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ ucfirst($plan->strategy) }} | {{ $scopeLabel($plan->socket_scope) }} | threshold {{ $plan->match_threshold }}%
                                </p>
                            @else
                                <p class="mt-1 text-sm text-muted-foreground">No active plan. Default balanced detection is used.</p>
                            @endif
                        </div>

                        <div class="mt-3 rounded-xl bg-card px-3 py-3 ring-1 ring-border/20">
                            <p class="text-xs font-medium text-muted-foreground">Signature snapshot</p>
                            @if($signature)
                                <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <p class="text-muted-foreground">Avg power</p>
                                        <p class="font-medium tabular-nums">{{ number_format($signature['avg_power_w'], 1) }} W</p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground">Peak power</p>
                                        <p class="font-medium tabular-nums">{{ number_format($signature['peak_power_w'], 1) }} W</p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground">Variability</p>
                                        <p class="font-medium tabular-nums">{{ number_format($signature['variability_pct'], 1) }}%</p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground">Startup ratio</p>
                                        <p class="font-medium tabular-nums">{{ number_format($signature['startup_ratio'], 2) }}x</p>
                                    </div>
                                </div>
                            @else
                                <p class="mt-2 text-xs text-muted-foreground">Not enough samples (min {{ $detection['required_samples'] ?? 3 }}) to build a fingerprint.</p>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('devices.profiles.store') }}" class="mt-3 space-y-2 rounded-xl bg-card p-3 ring-1 ring-border/20">
                            @csrf
                            <input type="hidden" name="socket_index" value="{{ $socket['index'] }}">
                            <p class="text-xs font-medium text-muted-foreground">Train profile from current signature</p>
                            <input name="name" type="text" placeholder="Device name" class="h-10 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                            <select name="category" class="h-10 w-full rounded-xl border border-border/40 bg-background px-3 text-sm outline-none focus:border-primary/50" required>
                                @foreach($profileCategories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @endforeach
                            </select>
                            <textarea name="notes" rows="2" placeholder="Optional notes" class="w-full rounded-xl border border-border/40 bg-background px-3 py-2 text-sm outline-none focus:border-primary/50"></textarea>
                            <button type="submit" class="w-full rounded-xl bg-primary px-3 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">Save profile</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-5">
            <section class="rounded-3xl bg-card p-6">
                <h3 class="text-lg font-bold">Detection Plans</h3>
                <p class="mt-1 text-sm text-muted-foreground">Define how strict detection should be per socket or globally.</p>

                <form method="POST" action="{{ route('devices.plans.store') }}" class="mt-4 space-y-3 rounded-2xl bg-background p-4 ring-1 ring-border/30">
                    @csrf
                    <div>
                        <label class="text-xs text-muted-foreground">Plan name</label>
                        <input name="name" type="text" class="mt-1 h-10 w-full rounded-xl border border-border/40 bg-card px-3 text-sm outline-none focus:border-primary/50" required>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-muted-foreground">Strategy</label>
                            <select name="strategy" class="mt-1 h-10 w-full rounded-xl border border-border/40 bg-card px-3 text-sm outline-none focus:border-primary/50" required>
                                <option value="fast">Fast</option>
                                <option value="balanced" selected>Balanced</option>
                                <option value="strict">Strict</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Socket scope</label>
                            <select name="socket_scope" class="mt-1 h-10 w-full rounded-xl border border-border/40 bg-card px-3 text-sm outline-none focus:border-primary/50">
                                <option value="">All sockets</option>
                                <option value="1">Socket 1</option>
                                <option value="2">Socket 2</option>
                                <option value="3">Socket 3</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-xs text-muted-foreground">Window</label>
                            <input name="window_samples" type="number" min="30" max="240" value="90" class="mt-1 h-10 w-full rounded-xl border border-border/40 bg-card px-3 text-sm outline-none focus:border-primary/50" required>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Min samples</label>
                            <input name="min_samples" type="number" min="2" max="30" value="3" class="mt-1 h-10 w-full rounded-xl border border-border/40 bg-card px-3 text-sm outline-none focus:border-primary/50" required>
                        </div>
                        <div>
                            <label class="text-xs text-muted-foreground">Threshold %</label>
                            <input name="match_threshold" type="number" min="40" max="95" value="68" class="mt-1 h-10 w-full rounded-xl border border-border/40 bg-card px-3 text-sm outline-none focus:border-primary/50" required>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground">Notes</label>
                        <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-border/40 bg-card px-3 py-2 text-sm outline-none focus:border-primary/50"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-muted-foreground">
                        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-border/40 bg-card text-primary focus:ring-primary/30">
                        Activate immediately
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-primary px-3 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">Create detection plan</button>
                </form>

                <div class="mt-4 space-y-3">
                    @forelse($detectionPlans as $plan)
                        <article class="rounded-2xl bg-background p-4 ring-1 ring-border/30">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold">{{ $plan->name }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ ucfirst($plan->strategy) }} | {{ $scopeLabel($plan->socket_scope) }} | window {{ $plan->window_samples }} | min {{ $plan->min_samples }} | {{ $plan->match_threshold }}%
                                    </p>
                                </div>
                                @if($plan->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-medium text-emerald-300 ring-1 ring-emerald-500/30">Active</span>
                                @endif
                            </div>

                            @if($plan->notes)
                                <p class="mt-2 text-xs text-muted-foreground">{{ $plan->notes }}</p>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-2">
                                @if(!$plan->is_active)
                                    <form method="POST" action="{{ route('devices.plans.activate', $plan) }}">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-primary/15 px-3 py-1.5 text-xs font-medium text-primary transition hover:bg-primary/25">Activate</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('devices.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this detection plan?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-xl bg-red-500/15 px-3 py-1.5 text-xs font-medium text-red-300 transition hover:bg-red-500/25">Delete</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl bg-background p-4 text-sm text-muted-foreground ring-1 ring-border/30">
                            No detection plans yet. Create one to control matching behavior.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </section>

    <section class="rounded-3xl bg-card p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold">Profile Library</h3>
                <p class="text-sm text-muted-foreground">Saved device signatures used for matching.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">{{ $profiles->count() }} profiles</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="border-b border-border/30 text-left text-xs text-muted-foreground">
                        <th class="px-2 py-2 font-medium">Name</th>
                        <th class="px-2 py-2 font-medium">Category</th>
                        <th class="px-2 py-2 font-medium">Trained from</th>
                        <th class="px-2 py-2 font-medium">Avg / Peak</th>
                        <th class="px-2 py-2 font-medium">Range</th>
                        <th class="px-2 py-2 font-medium">Updated</th>
                        <th class="px-2 py-2 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($profiles as $profile)
                        <tr class="border-b border-border/20">
                            <td class="px-2 py-2 font-medium">{{ $profile->name }}</td>
                            <td class="px-2 py-2 text-muted-foreground">{{ $profile->category }}</td>
                            <td class="px-2 py-2 text-muted-foreground">Socket {{ $profile->trained_from_socket ?? 'n/a' }}</td>
                            <td class="px-2 py-2 tabular-nums">{{ number_format($profile->avg_power_w, 1) }} / {{ number_format($profile->peak_power_w, 1) }} W</td>
                            <td class="px-2 py-2 tabular-nums text-muted-foreground">{{ number_format($profile->expected_power_min, 1) }} - {{ number_format($profile->expected_power_max, 1) }} W</td>
                            <td class="px-2 py-2 text-muted-foreground">{{ $profile->last_trained_at?->diffForHumans() ?? '-' }}</td>
                            <td class="px-2 py-2">
                                <form method="POST" action="{{ route('devices.profiles.destroy', $profile) }}" onsubmit="return confirm('Delete this profile?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg bg-red-500/15 px-2.5 py-1 text-xs font-medium text-red-300 transition hover:bg-red-500/25">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-4 text-sm text-muted-foreground">No profiles trained yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-3xl bg-card p-6">
        <h3 class="text-lg font-bold">Recent Detection Events</h3>
        <p class="mt-1 text-sm text-muted-foreground">Latest recognition updates saved by the detection engine.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr class="border-b border-border/30 text-left text-xs text-muted-foreground">
                        <th class="px-2 py-2 font-medium">Time</th>
                        <th class="px-2 py-2 font-medium">Socket</th>
                        <th class="px-2 py-2 font-medium">Label</th>
                        <th class="px-2 py-2 font-medium">Category</th>
                        <th class="px-2 py-2 font-medium">Confidence</th>
                        <th class="px-2 py-2 font-medium">Plan</th>
                        <th class="px-2 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentDetections as $event)
                        <tr class="border-b border-border/20">
                            <td class="px-2 py-2 text-muted-foreground">{{ $event->detected_at?->diffForHumans() }}</td>
                            <td class="px-2 py-2">{{ $event->socket_index }}</td>
                            <td class="px-2 py-2 font-medium">{{ $event->predicted_label }}</td>
                            <td class="px-2 py-2 text-muted-foreground">{{ $event->predicted_category ?? '-' }}</td>
                            <td class="px-2 py-2 tabular-nums">{{ $event->confidence }}%</td>
                            <td class="px-2 py-2 text-muted-foreground">{{ $event->plan?->name ?? 'Default' }}</td>
                            <td class="px-2 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $detectionStateClasses[$event->status] ?? 'bg-muted text-muted-foreground ring-1 ring-border/40' }}">{{ $event->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-4 text-sm text-muted-foreground">No detection events recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
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
</script>
@endsection
