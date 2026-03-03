{{-- Socket Card — Roomy rounded dark theme --}}
@php
    $idx       = $socket['index'];
    $label     = $socket['label'];
    $isOn      = $socket['is_on'];
    $voltage   = $socket['voltage'];
    $current   = $socket['current'];
    $powerW    = $socket['power_w'];
    $energyKwh = $socket['energy_kwh'];
    $status    = $socket['status'];
    $updatedAt = $socket['updated_at'];

    $gpioMap   = [1 => 15, 2 => 16, 3 => 17];
    $gpio      = $gpioMap[$idx] ?? '—';

    $timeAgo = $updatedAt ? \Carbon\Carbon::parse($updatedAt)->diffForHumans() : 'No data';

    $maxCurrent = 5;
    $hasLoad = $isOn && ((float) $current > 0.01);
    $pct = $hasLoad ? max(15, min(95, ($current / $maxCurrent) * 100)) : 0;

    $statusDot = match($status) {
        'normal'    => 'bg-emerald-500',
        'high_load' => 'bg-amber-500',
        'overload'  => 'bg-red-500',
        'off'       => 'bg-muted-foreground/40',
        default     => 'bg-red-500',
    };
    $statusLabel = match($status) {
        'normal'    => 'Normal',
        'high_load' => 'High Load',
        'overload'  => 'Overload',
        'off'       => 'Off',
        'offline'   => 'Offline',
        default     => 'Unknown',
    };
@endphp

<div class="rounded-3xl bg-card p-7" id="socket-card-{{ $idx }}">
    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h3 class="text-lg font-bold">{{ $label }}</h3>
            <p class="text-sm text-muted-foreground">Socket {{ $idx }} &middot; Relay GPIO {{ $gpio }}</p>
        </div>
        <button
            onclick="toggleSocket({{ $idx }}, {{ $isOn ? 'false' : 'true' }})"
            title="{{ $isOn ? 'Turn off' : 'Turn on' }} {{ $label }}"
            class="flex h-11 w-11 items-center justify-center rounded-full transition {{ $isOn ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground' }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
        </button>
    </div>

    {{-- Status --}}
    <div class="mt-4 flex items-center gap-2">
        @if($isOn && $status !== 'offline')
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $statusDot }} opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full {{ $statusDot }}"></span>
            </span>
        @else
            <span class="h-2 w-2 rounded-full {{ $statusDot }}"></span>
        @endif
        <span class="text-xs text-muted-foreground">{{ $statusLabel }}</span>
        <span class="ml-auto text-xs text-muted-foreground">{{ $timeAgo }}</span>
    </div>

    {{-- Values --}}
    <div class="mt-6 flex items-baseline justify-between">
        <span class="text-sm text-muted-foreground">{{ number_format($current, 3) }} A</span>
        <span class="text-2xl font-bold tabular-nums">{{ number_format($powerW, 1) }}<span class="ml-0.5 text-sm font-normal text-muted-foreground">W</span></span>
    </div>

    {{-- Slider bar --}}
    <div class="mt-5 h-11 w-full rounded-full bg-muted">
        <div class="flex h-full items-center rounded-full px-1.5 transition-all duration-700 {{ $hasLoad ? 'bg-primary/15' : '' }}" style="width: max(2.75rem, {{ $pct }}%)">
            <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-full shadow transition-colors duration-700 {{ $isOn ? 'bg-primary/30 ring-1 ring-primary/20' : 'bg-muted-foreground/20' }}">
                <svg class="h-3.5 w-3.5 {{ $isOn ? 'text-primary' : 'text-muted-foreground' }} transition-colors duration-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </span>
        </div>
    </div>

    {{-- Extra metrics --}}
    <div class="mt-6 grid grid-cols-2 gap-4">
        <div class="rounded-2xl bg-background p-4">
            <p class="text-[11px] text-muted-foreground">Voltage</p>
            <p class="mt-1.5 text-sm font-bold tabular-nums">{{ number_format($voltage, 1) }} <span class="text-xs font-normal text-muted-foreground">V</span></p>
        </div>
        <div class="rounded-2xl bg-background p-4">
            <p class="text-[11px] text-muted-foreground">Energy</p>
            <p class="mt-1.5 text-sm font-bold tabular-nums">{{ number_format($energyKwh, 3) }} <span class="text-xs font-normal text-muted-foreground">kWh</span></p>
        </div>
    </div>
</div>
