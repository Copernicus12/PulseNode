@extends('layouts.app')

@section('title', 'Power Strip')

@push('head')
    @vite('resources/js/relay-command-toast.ts')
    @vite('resources/js/power-strip-notifications.ts')
    @vite('resources/js/power-strip-safety-guard.ts')
@endpush

@section('content')
@php
    $lastSeen = $latest['updated_at'] ? \Carbon\Carbon::parse($latest['updated_at'])->diffForHumans() : 'Never';
    $isOnline = $systemStatus !== 'offline';
@endphp

<div class="space-y-5">
    @include('layouts._relay-command-alert', ['relayCommandGuard' => $relayCommandGuard])

    <div id="powerstrip-notifications-root"></div>
    <script>
        window.__powerStripCommandLogs = @json($commandLogs ?? []);
    </script>

    @if(session('power_strip_log'))
        <script>
            (function () {
                var logEntry = @json(session('power_strip_log'));
                var existingLogs = Array.isArray(window.__powerStripCommandLogs) ? window.__powerStripCommandLogs : [];

                if (window.pulsenodeShowPowerStripToast) {
                    window.pulsenodeShowPowerStripToast({
                        level: logEntry && logEntry.level ? logEntry.level : 'success',
                        title: logEntry && logEntry.message ? logEntry.message : 'Power Strip updated',
                        detail: 'Power Strip actions were updated successfully.',
                    });
                }

                if (existingLogs.length === 0) {
                    window.__powerStripCommandLogs = [logEntry];
                    if (typeof persistCommandLog === 'function') {
                        persistCommandLog(logEntry).catch(function() {});
                    }
                }
            })();
        </script>
    @endif

    {{-- ── Row 1: Operations header ── --}}
    <div id="powerstrip-command-center" class="light-outline-strong rounded-3xl bg-card p-7">
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
                <a href="{{ route('power-strip-diagnostics.edit') }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-muted px-5 text-sm font-medium text-muted-foreground transition hover:text-foreground">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Hardware & Diagnostics
                </a>
            </div>
        </div>

        {{-- Overview metrics --}}
        <div class="mt-7 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="light-outline rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Instant Load</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="total-power">
                    @if($isOnline)
                        {{ number_format($totalPower, 1) }} <span class="text-sm font-normal text-muted-foreground">W</span>
                    @else
                        <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                    @endif
                </p>
            </div>
            <div class="light-outline rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">System Energy Counter</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="total-energy">
                    @if($isOnline)
                        {{ number_format($totalEnergy, 3) }} <span class="text-sm font-normal text-muted-foreground">kWh</span>
                    @else
                        <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                    @endif
                </p>
            </div>
            <div class="light-outline rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Active Sockets</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums" id="active-sockets">
                    @if($isOnline)
                        {{ $activeSockets }} <span class="text-sm font-normal text-muted-foreground">/ 3</span>
                    @else
                        <span class="text-base font-semibold text-muted-foreground">Unavailable</span>
                    @endif
                </p>
            </div>
            <div class="light-outline rounded-2xl bg-background p-5">
                <p class="text-xs text-muted-foreground">Runtime State</p>
                <p class="mt-2.5 text-2xl font-bold tabular-nums capitalize">{{ $systemStatus }}</p>
            </div>
        </div>
    </div>

    {{-- ── Row 2: Socket control cards ── --}}
    <div id="powerstrip-sockets-grid" class="grid gap-5 lg:grid-cols-3">
        @foreach($sockets as $socket)
            @include('power-strip._socket-card', ['socket' => $socket, 'isOnline' => $isOnline])
        @endforeach
    </div>

    {{-- ── Row 3: Guard + log ── --}}
    <div class="grid gap-5 xl:grid-cols-2 xl:items-start">
        <div class="space-y-5">
            <div id="powerstrip-safety-guard" class="light-outline-strong flex h-full min-h-[842px] flex-col rounded-3xl bg-card p-5 sm:p-6">
                <div
                    id="safety-guard-field-root"
                    class="w-full flex-1"
                    data-policy='@json($guardPolicy)'
                    data-save-url="{{ route('power-strip.guard-policy.store') }}"
                >
                    <p class="text-sm text-muted-foreground">Loading safety guard form...</p>
                </div>
            </div>

        </div>

        <div class="space-y-5">
            <div class="light-outline-strong flex h-full min-h-[620px] flex-col rounded-3xl bg-card p-5 sm:p-6">
                <div class="rounded-2xl border border-border/40 bg-background/40 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold tracking-tight">Command Log</h3>
                    </div>
                    <button type="button" onclick="clearCommandLog()" class="rounded-2xl border border-border/40 bg-background/50 px-3 py-2 text-xs font-medium text-muted-foreground transition hover:text-foreground">
                        Clear log
                    </button>
                    </div>
                </div>

                <div
                    class="mt-4 flex-1 overflow-hidden rounded-2xl border border-border/40 bg-[#111111] shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]"
                    data-command-log-url="{{ route('power-strip.command-log.store') }}"
                    data-command-log-clear-url="{{ route('power-strip.command-log.clear') }}"
                >
                    <div class="border-b border-white/5 px-4 py-2 font-mono text-[11px] uppercase tracking-[0.24em] text-zinc-500">
                        power-strip console
                    </div>
                    <div class="max-h-[420px] overflow-y-auto px-3 py-3">
                        <div id="command-log" class="space-y-1 font-mono text-[12px] leading-6 text-zinc-200"></div>
                        <div id="command-log-empty" class="px-1 py-1 font-mono text-[12px] text-zinc-500">
                            [idle] waiting for power strip actions...
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-2xl border border-border/40 bg-background/40 px-4 py-3">
                        <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-muted-foreground">SUCCES</p>
                        <p id="log-success" class="mt-1 font-mono text-sm font-semibold tabular-nums">0</p>
                    </div>
                    <div class="rounded-2xl border border-border/40 bg-background/40 px-4 py-3">
                        <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-muted-foreground">WARNING</p>
                        <p id="log-warnings" class="mt-1 font-mono text-sm font-semibold tabular-nums">0</p>
                    </div>
                    <div class="rounded-2xl border border-border/40 bg-background/40 px-4 py-3">
                        <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-muted-foreground">ERROR</p>
                        <p id="log-errors" class="mt-1 font-mono text-sm font-semibold tabular-nums">0</p>
                    </div>
                </div>
            </div>

            <div id="powerstrip-service-ops" class="rounded-3xl bg-primary p-6 text-primary-foreground">
                <h3 class="text-lg font-bold">Service Operations</h3>
                <p class="mt-1.5 text-sm opacity-70">Direct maintenance controls</p>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button onclick="toggleAllSockets(true)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All On</button>
                    <button onclick="toggleAllSockets(false)" class="rounded-2xl bg-primary-foreground/15 px-5 py-2.5 text-sm font-medium transition hover:bg-primary-foreground/25">Turn All Off</button>
                </div>
            </div>
        </div>
    </div>

    <div
        id="powerstrip-saved-policies"
        class="light-outline-strong w-full rounded-3xl border-border/40 bg-card shadow-none"
    >
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
            <div>
                <h3 class="text-lg font-bold tracking-tight">
                    Saved policies
                </h3>
                <p class="mt-1 text-sm leading-5 text-muted-foreground">
                    Review guard rules before they appear in the live list below.
                </p>
            </div>

            <span
                class="inline-flex rounded-full px-3 py-1 text-xs {{ count($guardPolicies) > 0 ? 'border border-amber-500/20 bg-amber-500/10 text-amber-200' : 'border border-border/40 bg-muted/30 text-muted-foreground' }}"
            >
                {{ count($guardPolicies) }} waiting
            </span>
        </div>

        <div class="p-4 pt-0 sm:p-5 sm:pt-0">
            @if(count($guardPolicies) > 0)
                <div class="overflow-x-auto rounded-2xl bg-secondary/40 ring-1 ring-border/30">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b border-border/30 bg-secondary/40 text-left text-xs text-muted-foreground">
                                <th class="px-3 py-2.5 font-medium">Policy</th>
                                <th class="px-3 py-2.5 font-medium">Thresholds</th>
                                <th class="px-3 py-2.5 font-medium">Window</th>
                                <th class="px-3 py-2.5 font-medium">Status</th>
                                <th class="px-3 py-2.5 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($guardPolicies as $policy)
                                <tr class="border-b border-border/20 last:border-0">
                                    <td class="px-3 py-2.5">
                                        <div class="font-medium text-foreground">
                                            {{ $policy['scope_mode'] === 'per_socket' ? 'Per-socket guard' : 'Common guard' }}
                                        </div>
                                        <div class="mt-1 text-xs text-muted-foreground">
                                            Action: {{ $policy['action'] === 'off-1' ? 'Turn off socket 1' : ($policy['action'] === 'off-2' ? 'Turn off socket 2' : ($policy['action'] === 'off-3' ? 'Turn off socket 3' : 'Turn off all sockets')) }}
                                        </div>
                                        @if(!empty($policy['notes']))
                                            <div class="mt-2 rounded-xl bg-card px-3 py-2 text-xs text-muted-foreground">
                                                {{ $policy['notes'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        {{ $policy['scope_mode'] === 'per_socket'
                                            ? 'S1 '.number_format((float) $policy['socket_threshold_amps_1'], 1).' A · S2 '.number_format((float) $policy['socket_threshold_amps_2'], 1).' A · S3 '.number_format((float) $policy['socket_threshold_amps_3'], 1).' A'
                                            : 'Common '.number_format((float) $policy['common_threshold_amps'], 1).' A' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        <div>
                                            {{ \Carbon\Carbon::parse($policy['start_date'])->toDateString() }} → {{ !empty($policy['has_end_date']) && !empty($policy['end_date']) ? \Carbon\Carbon::parse($policy['end_date'])->toDateString() : 'No end date' }}
                                        </div>
                                        @if(!empty($policy['last_triggered_at']))
                                            <div class="mt-1 text-xs">
                                                Last trigger: {{ \Carbon\Carbon::parse($policy['last_triggered_at'])->format('M j, Y g:i A') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-medium {{ $policy['status_tone'] ?? 'border-border/40 bg-muted/30 text-muted-foreground' }}">
                                            {{ $policy['status_label'] ?? 'Unknown' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex flex-wrap gap-2">
                                            @if(!empty($policy['enabled']))
                                                <form method="POST" action="{{ $policy['pause_url'] }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="h-8 rounded-xl bg-muted px-3 text-xs font-medium text-foreground transition hover:bg-muted/80">
                                                        Pause
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ $policy['resume_url'] }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="h-8 rounded-xl bg-muted px-3 text-xs font-medium text-foreground transition hover:bg-muted/80">
                                                        Resume
                                                    </button>
                                                </form>
                                            @endif
                                                @php
                                                    $policyActionLabel = $policy['action'] === 'off-1'
                                                        ? 'Turn off socket 1'
                                                        : ($policy['action'] === 'off-2'
                                                            ? 'Turn off socket 2'
                                                            : ($policy['action'] === 'off-3'
                                                                ? 'Turn off socket 3'
                                                                : 'Turn off all sockets'));
                                                    $policySummary = ($policy['scope_mode'] === 'per_socket'
                                                        ? 'Per-socket guard'
                                                        : 'Common guard')
                                                        .' ('
                                                        .(
                                                            $policy['scope_mode'] === 'per_socket'
                                                                ? 'S1 '.number_format((float) $policy['socket_threshold_amps_1'], 1).' A, S2 '.number_format((float) $policy['socket_threshold_amps_2'], 1).' A, S3 '.number_format((float) $policy['socket_threshold_amps_3'], 1).' A'
                                                                : number_format((float) $policy['common_threshold_amps'], 1).' A'
                                                        )
                                                        .' -> '
                                                        .$policyActionLabel
                                                        .')';
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="h-8 rounded-xl bg-red-500/10 px-3 text-xs font-medium text-red-300 transition hover:bg-red-500/20"
                                                    data-delete-url="{{ $policy['delete_url'] }}"
                                                    data-policy-summary="{{ $policySummary }}"
                                                    data-delete-title="Delete guard policy?"
                                                    onclick="openGuardPolicyDeleteDialog(this)"
                                                >
                                                    Delete
                                                </button>
                                            
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-border/40 bg-secondary/20 px-4 py-6 text-sm text-muted-foreground">
                    No guard policies yet. Save the first policy above and it will appear here.
                </div>
            @endif
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

</div>

<script>
function updateCommandLogInsights() {
    var host = document.getElementById('command-log');
    var empty = document.getElementById('command-log-empty');
    var shellState = document.getElementById('log-shell-state');
    var successEl = document.getElementById('log-success');
    var warningEl = document.getElementById('log-warnings');
    var errorEl = document.getElementById('log-errors');
    if (!host) return;

    var rows = [];
    for (var i = 0; i < host.children.length; i++) {
        var row = host.children[i];
        var level = row.dataset.level || row.dataset.type || 'success';
        if (level === 'info') level = 'success';
        rows.push({
            message: (row.dataset.message || row.textContent || '').trim(),
            type: level,
        });
    }

    var total = rows.length;
    var success = 0;
    var warnings = 0;
    var errors = 0;
    for (var j = 0; j < rows.length; j++) {
        if (rows[j].type === 'warning') {
            warnings++;
        }
        if (rows[j].type === 'error') {
            errors++;
        }
        if (rows[j].type === 'success' || rows[j].type === 'info') {
            success++;
        }
    }

    if (successEl) successEl.textContent = String(success);
    if (warningEl) warningEl.textContent = String(warnings);
    if (errorEl) errorEl.textContent = String(errors);
    if (empty) {
        empty.classList.toggle('hidden', total > 0);
    }
    if (shellState) {
        shellState.textContent = total > 0 ? 'live' : 'idle';
    }
}

function resolveLogLevel(isError, tone) {
    if (tone === 'success') return 'success';
    if (tone === 'warning') return 'warning';
    if (tone === 'error') return 'error';
    return isError ? 'error' : 'success';
}

function normalizeCommandLogEntry(entry, fallbackMessage, fallbackTone) {
    var payload = entry && typeof entry === 'object' ? entry : {};
    var level = payload.level || payload.type || fallbackTone || 'success';
    if (level === 'info') level = 'success';
    if (!['success', 'warning', 'error'].includes(level)) {
        level = fallbackTone || 'success';
    }

    return {
        level: level,
        message: typeof payload.message === 'string' && payload.message.trim() !== '' ? payload.message : (fallbackMessage || 'Power Strip event'),
        time: typeof payload.time === 'string' && payload.time.trim() !== '' ? payload.time : (typeof payload.created_at === 'string' && payload.created_at.trim() !== '' ? payload.created_at : new Date().toISOString()),
        source: typeof payload.source === 'string' && payload.source.trim() !== '' ? payload.source : 'power-strip',
        meta: payload.meta && typeof payload.meta === 'object' ? payload.meta : null,
    };
}

function renderLogEntry(entry) {
    var host = document.getElementById('command-log');
    var empty = document.getElementById('command-log-empty');
    if (!host) return;

    var item = normalizeCommandLogEntry(entry);
    if (!host) return;
    var row = document.createElement('div');
    var level = item.level;
    row.dataset.type = level;
    row.dataset.level = level;
    row.dataset.message = item.message;
    row.dataset.time = item.time;
    row.dataset.source = item.source;
    row.className = 'rounded-2xl border border-border/30 bg-background/30 px-3 py-2 transition';

    var headerRow = document.createElement('div');
    headerRow.className = 'flex flex-wrap items-center gap-x-3 gap-y-1 font-mono text-[11px] text-zinc-500';

    var timeCell = document.createElement('span');
    timeCell.className = 'shrink-0 whitespace-nowrap';
    timeCell.textContent = new Date(item.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    var typeCell = document.createElement('span');
    typeCell.className = 'shrink-0 rounded-full border border-border/30 bg-background/40 px-2 py-0.5 text-[10px] font-semibold tracking-[0.18em] text-zinc-400';
    typeCell.textContent = level === 'error' ? 'ERROR' : (level === 'warning' ? 'WARNING' : 'SUCCES');

    var promptCell = document.createElement('span');
    promptCell.className = 'shrink-0';
    promptCell.textContent = 'power-strip@terminal>';

    var messageCell = document.createElement('div');
    messageCell.className = 'mt-2 pl-[1.45rem] font-mono text-[12px] leading-5 text-zinc-100 break-words';
    messageCell.textContent = item.message;

    headerRow.appendChild(promptCell);
    headerRow.appendChild(timeCell);
    headerRow.appendChild(typeCell);
    row.appendChild(headerRow);
    row.appendChild(messageCell);
    host.prepend(row);
    if (empty) empty.classList.add('hidden');
    while (host.children.length > 40) host.removeChild(host.lastChild);
    updateCommandLogInsights();
}

function hydrateCommandLog(entries) {
    var host = document.getElementById('command-log');
    var empty = document.getElementById('command-log-empty');
    if (!host) return;
    var rows = Array.isArray(entries) ? entries : [];
    rows.slice().reverse().forEach(function(entry) {
        renderLogEntry(entry);
    });
    if (empty) empty.classList.toggle('hidden', host.children.length > 0);
    updateCommandLogInsights();
}

async function persistCommandLog(entry) {
    var host = document.getElementById('command-log');
    var logCard = document.querySelector('[data-command-log-url]');
    if (!host || !logCard) return null;

    var response = await fetch(logCard.getAttribute('data-command-log-url') || '', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': (function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? (meta.getAttribute('content') || '') : '';
            })(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(entry),
    });

    var payload = await response.json().catch(function() {
        return {};
    });

    if (!response.ok) {
        throw new Error((payload && payload.message) ? payload.message : 'Failed to save command log');
    }

    return payload && payload.log ? payload.log : null;
}

async function clearCommandLog() {
    var logCard = document.querySelector('[data-command-log-clear-url]');
    var clearUrl = logCard ? (logCard.getAttribute('data-command-log-clear-url') || '') : '';
    if (clearUrl) {
        try {
            await fetch(clearUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (function () {
                        var meta = document.querySelector('meta[name="csrf-token"]');
                        return meta ? (meta.getAttribute('content') || '') : '';
                    })(),
                },
                credentials: 'same-origin',
            });
        } catch (_) {}
    }

    var host = document.getElementById('command-log');
    var empty = document.getElementById('command-log-empty');
    var shellState = document.getElementById('log-shell-state');
    if (host) host.innerHTML = '';
    if (empty) empty.classList.remove('hidden');
    if (shellState) shellState.textContent = 'idle';
    window.__powerStripCommandLogs = [];
    updateCommandLogInsights();
}

function openGuardPolicyDeleteDialog(button) {
    if (!button || !window.pulsenodeOpenPowerStripDeleteDialog) return;

    window.pulsenodeOpenPowerStripDeleteDialog({
        title: button.dataset.deleteTitle || 'Delete guard policy?',
        actionUrl: button.dataset.deleteUrl || '',
        policySummary: button.dataset.policySummary || '',
    });
}

var relayState = {
    1: {{ !empty($sockets[0]['is_on']) ? 'true' : 'false' }},
    2: {{ !empty($sockets[1]['is_on']) ? 'true' : 'false' }},
    3: {{ !empty($sockets[2]['is_on']) ? 'true' : 'false' }},
};
var powerStripLiveIsOnline = @json($isOnline);
var powerStripOfflineAfterMs = {{ max(30, (int) config('esp32.connection.offline_after_seconds', 300)) * 1000 }};

function asNumber(value) {
    var num = Number(value);
    return Number.isFinite(num) ? num : 0;
}

function isPowerStripFresh(data) {
    if (!data || !data.updated_at) return false;
    var timestamp = Date.parse(data.updated_at);
    return Number.isFinite(timestamp) && (Date.now() - timestamp) <= powerStripOfflineAfterMs;
}

function powerStripUnavailableHtml(size) {
    return '<span class="' + (size === 'sm' ? 'text-sm' : 'text-base') + ' font-semibold text-muted-foreground">Unavailable</span>';
}

function powerStripUnit(unit, size) {
    return ' <span class="' + (size === 'xs' ? 'text-xs' : 'text-sm') + ' font-normal text-muted-foreground">' + unit + '</span>';
}

function formatUpdatedLabel(updatedAt) {
    if (!updatedAt) return 'No data';
    var timestamp = Date.parse(updatedAt);
    if (!Number.isFinite(timestamp)) return 'No data';
    var diffSeconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
    if (diffSeconds < 60) return 'just now';
    if (diffSeconds < 3600) return Math.floor(diffSeconds / 60) + ' min ago';
    if (diffSeconds < 86400) return Math.floor(diffSeconds / 3600) + ' h ago';
    return Math.floor(diffSeconds / 86400) + ' d ago';
}

function resolveSocketStatus(isOn, powerW) {
    if (!isOn) return { label: 'Off', dotClass: 'bg-muted-foreground/40' };
    if (powerW >= 2500) return { label: 'Overload', dotClass: 'bg-red-500' };
    if (powerW >= 1800) return { label: 'High Load', dotClass: 'bg-amber-500' };
    return { label: 'Normal', dotClass: 'bg-emerald-500' };
}

function displayCurrent(value) {
    var current = asNumber(value);
    return Math.abs(current) < 0.05 ? 0 : current;
}

function setPowerStripSocketLoad(idx, active, currentValue) {
    var load = document.getElementById('socket-load-' + idx);
    var knob = document.getElementById('socket-load-knob-' + idx);
    var icon = document.getElementById('socket-load-icon-' + idx);
    if (!load) return;

    var pct = active ? Math.max(15, Math.min(95, (Math.abs(currentValue) / 5) * 100)) : 0;
    load.style.width = 'max(2.75rem, ' + pct + '%)';
    load.classList.toggle('bg-primary/15', !!active);

    if (knob) {
        knob.classList.toggle('bg-primary/30', !!active);
        knob.classList.toggle('ring-1', !!active);
        knob.classList.toggle('ring-primary/20', !!active);
        knob.classList.toggle('bg-muted-foreground/20', !active);
    }

    if (icon) {
        icon.classList.toggle('text-primary', !!active);
        icon.classList.toggle('text-muted-foreground', !active);
    }
}

function renderPowerStripUnavailable(data) {
    powerStripLiveIsOnline = false;

    var el = function(id) { return document.getElementById(id); };
    ['total-power', 'total-energy', 'active-sockets'].forEach(function(id) {
        var item = el(id);
        if (item) item.innerHTML = powerStripUnavailableHtml();
    });

    [1, 2, 3].forEach(function(idx) {
        var currentEl = el('socket-current-' + idx);
        var powerEl = el('socket-power-' + idx);
        var voltageEl = el('socket-voltage-' + idx);
        var energyEl = el('socket-energy-' + idx);
        var statusLabelEl = el('socket-status-label-' + idx);
        var statusDotEl = el('socket-status-dot-' + idx);
        var updatedEl = el('socket-updated-' + idx);

        if (currentEl) currentEl.textContent = 'Unavailable';
        if (powerEl) powerEl.innerHTML = powerStripUnavailableHtml('sm');
        if (voltageEl) voltageEl.innerHTML = powerStripUnavailableHtml('sm');
        if (energyEl) energyEl.innerHTML = powerStripUnavailableHtml('sm');
        if (statusLabelEl) statusLabelEl.textContent = 'Offline';
        if (statusDotEl) {
            statusDotEl.classList.remove('bg-emerald-500', 'bg-amber-500', 'bg-muted-foreground/40');
            statusDotEl.classList.add('bg-red-500');
        }
        if (updatedEl) updatedEl.textContent = formatUpdatedLabel(data && data.updated_at);

        setPowerStripSocketLoad(idx, false, 0);
    });
}

function setSocketToggleState(idx, isOn, pending) {
    var button = document.getElementById('socket-toggle-' + idx);
    if (!button) return;
    button.dataset.isOn = isOn ? '1' : '0';
    button.disabled = !!pending;
    button.title = (isOn ? 'Turn off' : 'Turn on') + ' Socket ' + idx;
    button.classList.remove('bg-primary', 'text-primary-foreground', 'bg-muted', 'text-muted-foreground', 'opacity-60', 'cursor-not-allowed');
    button.classList.add(isOn ? 'bg-primary' : 'bg-muted');
    button.classList.add(isOn ? 'text-primary-foreground' : 'text-muted-foreground');
    if (pending) button.classList.add('opacity-60', 'cursor-not-allowed');
}

function publishLatestSnapshot(data) {
    if (!data) return;
    window.__pulsenodeLatest = data;
    window.dispatchEvent(new CustomEvent('pulsenode:latest', { detail: data }));
}

function sendRelayCommand(idx, turnOn) {
    if (window.pulsenodeEnsureRelayCommandAllowed && !window.pulsenodeEnsureRelayCommandAllowed(turnOn)) {
        var blockedGuard = window.__pulsenodeRelayCommandGuard || {};
        return Promise.reject(new Error(blockedGuard.message || 'Socket power-on is unavailable right now.'));
    }

    return fetch('/api/relay/' + idx + '/' + (turnOn ? 'on' : 'off'), { credentials: 'same-origin' })
        .then(function(response) {
            return response.json().catch(function() {
                return {};
            }).then(function(payload) {
                if (!response.ok) {
                    if (payload && payload.guard && window.pulsenodeSetRelayCommandGuard) {
                        window.pulsenodeSetRelayCommandGuard(payload.guard);
                    }
                    if (payload && payload.guard && window.pulsenodeShowRelayCommandNotification) {
                        window.pulsenodeShowRelayCommandNotification(payload.message, payload.guard);
                    }

                    var error = new Error((payload && payload.message) || 'Relay command failed');
                    error.payload = payload;
                    throw error;
                }

                return payload;
            });
        });
}

function toggleSocket(idx, turnOn) {
    var desiredState = typeof turnOn === 'boolean' ? turnOn : !relayState[idx];
    setSocketToggleState(idx, relayState[idx], true);
    sendRelayCommand(idx, desiredState)
        .then(function(payload) {
            var latest = payload && payload.latest ? payload.latest : null;
            relayState[idx] = desiredState;
            setSocketToggleState(idx, desiredState, false);
            if (payload && payload.log) {
                renderLogEntry(payload.log);
            }
            if (latest) publishLatestSnapshot(latest);
        })
        .catch(function(e) {
            setSocketToggleState(idx, relayState[idx], false);
            console.error('Relay error', e);
            if (e && !e.payload && desiredState) {
                persistCommandLog({
                    level: 'warning',
                    message: e && e.message ? e.message : ('Socket ' + idx + ' power-on blocked by guard.'),
                    source: 'ui',
                    meta: { socket_id: idx, state: desiredState ? 'on' : 'off', blocked: true },
                }).then(function(log) {
                    if (log) {
                        renderLogEntry(log);
                    }
                }).catch(function() {
                    renderLogEntry({
                        level: 'warning',
                        message: e && e.message ? e.message : ('Socket ' + idx + ' power-on blocked by guard.'),
                        source: 'ui',
                        time: new Date().toISOString(),
                    });
                });
            } else if (e && e.payload && e.payload.log) {
                renderLogEntry(e.payload.log);
            } else {
                renderLogEntry({
                    level: 'error',
                    message: (e && e.message) ? e.message : ('Socket ' + idx + ' command failed'),
                    source: 'relay',
                    time: new Date().toISOString(),
                });
            }
        });
}

function toggleAllSockets(turnOn) {
    if (turnOn && window.pulsenodeEnsureRelayCommandAllowed && !window.pulsenodeEnsureRelayCommandAllowed(true)) {
        var blockedGuard = window.__pulsenodeRelayCommandGuard || {};
        persistCommandLog({
            level: 'warning',
            message: blockedGuard.message || 'Power-on request blocked by guard.',
            source: 'ui',
            meta: { action: 'toggle_all', turn_on: true, blocked: true },
        }).then(function(log) {
            if (log) {
                renderLogEntry(log);
            }
        }).catch(function() {
            renderLogEntry({
                level: 'warning',
                message: blockedGuard.message || 'Power-on request blocked by guard.',
                source: 'ui',
                time: new Date().toISOString(),
            });
        });
        return;
    }

    setSocketToggleState(1, relayState[1], true);
    setSocketToggleState(2, relayState[2], true);
    setSocketToggleState(3, relayState[3], true);
    Promise.all([
        sendRelayCommand(1, turnOn),
        sendRelayCommand(2, turnOn),
        sendRelayCommand(3, turnOn)
    ]).then(function(payloads) {
        relayState[1] = turnOn;
        relayState[2] = turnOn;
        relayState[3] = turnOn;
        setSocketToggleState(1, turnOn, false);
        setSocketToggleState(2, turnOn, false);
        setSocketToggleState(3, turnOn, false);
        payloads.forEach(function(payload) {
            if (payload && payload.log) {
                renderLogEntry(payload.log);
            }
        });
        var latest = payloads.length ? (payloads[payloads.length - 1].latest || null) : null;
        if (latest) publishLatestSnapshot(latest);
    }).catch(function(e) {
        setSocketToggleState(1, relayState[1], false);
        setSocketToggleState(2, relayState[2], false);
        setSocketToggleState(3, relayState[3], false);
        console.error('Toggle all error', e);
        if (e && e.payload && e.payload.log) {
            renderLogEntry(e.payload.log);
        } else {
            renderLogEntry({
                level: 'error',
                message: (e && e.message) ? e.message : 'All-sockets command failed',
                source: 'relay',
                time: new Date().toISOString(),
            });
        }
    });
}

hydrateCommandLog(window.__powerStripCommandLogs || []);

function applyLatestMetrics(d) {
    if (!isPowerStripFresh(d)) {
        renderPowerStripUnavailable(d);
        return;
    }

    powerStripLiveIsOnline = true;

    var el = function(id) { return document.getElementById(id); };
    if (el('total-power')) el('total-power').innerHTML = Math.max(0, parseFloat(d.power || 0)).toFixed(1) + powerStripUnit('W');
    if (el('total-energy')) el('total-energy').innerHTML = parseFloat(d.energy || 0).toFixed(3) + powerStripUnit('kWh');
    if (el('active-sockets')) {
        var count = 0;
        if (d.relay_1) count++;
        if (d.relay_2) count++;
        if (d.relay_3) count++;
        el('active-sockets').innerHTML = count + '<span class="text-sm font-normal text-muted-foreground">/3</span>';
    }
    var voltage = asNumber(d.voltage);
    [1, 2, 3].forEach(function(idx) {
        var relayOn = Boolean(d['relay_' + idx]);
        relayState[idx] = relayOn;
        setSocketToggleState(idx, relayOn, false);

        var current = displayCurrent(d['current_' + idx]);
        var power = d['power_' + idx] !== undefined ? Math.max(0, asNumber(d['power_' + idx])) : Math.max(0, voltage * current);
        var status = resolveSocketStatus(relayOn, power);

        var currentEl = el('socket-current-' + idx);
        if (currentEl) currentEl.textContent = current.toFixed(3) + ' A';

        var powerEl = el('socket-power-' + idx);
        if (powerEl) powerEl.innerHTML = power.toFixed(1) + powerStripUnit('W');

        var voltageEl = el('socket-voltage-' + idx);
        if (voltageEl) voltageEl.innerHTML = voltage.toFixed(1) + powerStripUnit('V', 'xs');

        var energyEl = el('socket-energy-' + idx);
        if (energyEl) energyEl.innerHTML = asNumber(d.energy).toFixed(3) + powerStripUnit('kWh', 'xs');

        var statusLabelEl = el('socket-status-label-' + idx);
        if (statusLabelEl) statusLabelEl.textContent = status.label;

        var statusDotEl = el('socket-status-dot-' + idx);
        if (statusDotEl) {
            statusDotEl.classList.remove('bg-emerald-500', 'bg-amber-500', 'bg-red-500', 'bg-muted-foreground/40');
            statusDotEl.classList.add(status.dotClass);
        }

        var updatedEl = el('socket-updated-' + idx);
        if (updatedEl) updatedEl.textContent = formatUpdatedLabel(d.updated_at);

        setPowerStripSocketLoad(idx, relayOn && Math.abs(current) >= 0.05, current);
    });
}

window.addEventListener('pulsenode:latest', function(event) {
    applyLatestMetrics(event.detail || {});
});

window.addEventListener('pulsenode:relay-guard', function() {
    setSocketToggleState(1, relayState[1], false);
    setSocketToggleState(2, relayState[2], false);
    setSocketToggleState(3, relayState[3], false);
});

setSocketToggleState(1, relayState[1], false);
setSocketToggleState(2, relayState[2], false);
setSocketToggleState(3, relayState[3], false);

if (window.__pulsenodeLatest) {
    applyLatestMetrics(window.__pulsenodeLatest);
}
</script>
@endsection
