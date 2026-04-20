@php
    $currentRoute = request()->route()?->getName() ?? '';
    $isDevicesRoute = str_starts_with($currentRoute, 'devices.');

    $devicesLinks = [
        [
            'title' => 'Overview',
            'href' => route('devices.index'),
            'route' => 'devices.index',
            'label' => 'my devices overview',
        ],
        [
            'title' => 'Profiles',
            'href' => route('devices.profiles.index'),
            'route' => 'devices.profiles.index',
            'label' => 'my devices profiles',
        ],
        [
            'title' => 'Plans',
            'href' => route('devices.plans.index'),
            'route' => 'devices.plans.index',
            'label' => 'my devices plans',
        ],
        [
            'title' => 'Activity',
            'href' => route('devices.activity.index'),
            'route' => 'devices.activity.index',
            'label' => 'my devices activity',
        ],
    ];
@endphp

<a href="{{ route('dashboard') }}"
    data-tour="nav-dashboard"
    data-search-link="1"
    data-search-label="dashboard"
   @class([
       'flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition',
       'bg-primary text-primary-foreground font-semibold' => $currentRoute === 'dashboard',
       'text-muted-foreground hover:bg-muted/50 hover:text-foreground' => $currentRoute !== 'dashboard',
   ])>
    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Dashboard
</a>

<a href="{{ route('power-strip.index') }}"
    data-tour="nav-power-strip"
    data-search-link="1"
    data-search-label="power strip"
   @class([
       'flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition',
       'bg-primary text-primary-foreground font-semibold' => str_starts_with($currentRoute, 'power-strip'),
       'text-muted-foreground hover:bg-muted/50 hover:text-foreground' => !str_starts_with($currentRoute, 'power-strip'),
   ])>
    <svg class="h-[18px] w-[18px]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path d="M5.127 3.502L5.25 3.5h9.5q.062 0 .123.002A2.25 2.25 0 0 0 12.75 2h-5.5a2.25 2.25 0 0 0-2.123 1.502M1 10.25A2.25 2.25 0 0 1 3.25 8h13.5A2.25 2.25 0 0 1 19 10.25v5.5A2.25 2.25 0 0 1 16.75 18H3.25A2.25 2.25 0 0 1 1 15.75zM3.25 6.5l-.123.002A2.25 2.25 0 0 1 5.25 5h9.5c.98 0 1.814.627 2.123 1.502L16.75 6.5z"/>
    </svg>
    Power Strip
</a>

<details
    data-tour="nav-devices-group"
    @if($isDevicesRoute) open @endif
    @class([
        'group relative rounded-2xl',
        'light-outline-soft' => !$isDevicesRoute,
        'bg-primary/8 ring-1 ring-primary/20' => $isDevicesRoute,
    ])
>
    <summary
        @class([
            'flex cursor-pointer list-none items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition [&::-webkit-details-marker]:hidden',
            'text-primary font-semibold' => $isDevicesRoute,
            'text-muted-foreground hover:bg-muted/50 hover:text-foreground' => !$isDevicesRoute,
        ])
    >
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2.5" y="4" width="14" height="11" rx="2"></rect>
            <path d="M7 19h5"></path>
            <path d="M9.5 15v4"></path>
            <rect x="17" y="7" width="4.5" height="9" rx="1"></rect>
            <circle cx="19.25" cy="14" r="0.6"></circle>
        </svg>
        <span class="flex-1">My Devices</span>
        <svg class="h-4 w-4 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
    </summary>

    <div class="space-y-1 px-2 pb-2 transition lg:absolute lg:left-[calc(100%+0.65rem)] lg:top-0 lg:z-40 lg:w-56 lg:rounded-2xl lg:bg-card lg:p-2 lg:ring-1 lg:ring-border/40 lg:shadow-2xl lg:shadow-black/40 lg:opacity-0 lg:translate-x-2 lg:pointer-events-none lg:group-hover:opacity-100 lg:group-hover:translate-x-0 lg:group-hover:pointer-events-auto lg:group-focus-within:opacity-100 lg:group-focus-within:translate-x-0 lg:group-focus-within:pointer-events-auto lg:light-outline-strong">
        <div class="mb-1 hidden px-2 py-1 text-[11px] uppercase tracking-[0.12em] text-muted-foreground lg:block">
            My Devices
        </div>
        @foreach($devicesLinks as $item)
            <a href="{{ $item['href'] }}"
                data-tour="nav-devices-{{ $item['key'] ?? strtolower($item['title']) }}"
                data-search-link="1"
                data-search-label="{{ $item['label'] }}"
                @class([
                    'flex items-center rounded-xl px-3 py-2 text-[13px] transition lg:px-3.5 lg:py-2.5',
                    'bg-primary text-primary-foreground font-medium' => $currentRoute === $item['route'],
                    'text-muted-foreground hover:bg-muted/50 hover:text-foreground' => $currentRoute !== $item['route'],
                ])
            >
                {{ $item['title'] }}
            </a>
        @endforeach
    </div>
</details>

<a href="{{ route('history.index') }}"
    data-tour="nav-history"
    data-search-link="1"
    data-search-label="history"
   @class([
       'flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition',
       'bg-primary text-primary-foreground font-semibold' => $currentRoute === 'history.index',
       'text-muted-foreground hover:bg-muted/50 hover:text-foreground' => $currentRoute !== 'history.index',
   ])>
    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
    History
</a>

<a href="{{ route('electricity-billing.archive') }}"
    data-tour="nav-invoice-archive"
    data-search-link="1"
    data-search-label="invoice archive invoices bills receipts folders files upload"
   @class([
       'flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition',
       'bg-primary text-primary-foreground font-semibold' => request()->routeIs('electricity-billing.archive', 'electricity-billing.invoices.*'),
       'light-outline-soft text-muted-foreground hover:bg-muted/50 hover:text-foreground' => !request()->routeIs('electricity-billing.archive', 'electricity-billing.invoices.*'),
   ])>
    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h4l2 2h7A2.5 2.5 0 0 1 21 9.5v9A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5z"/>
        <path d="M8 12h8"/>
        <path d="M8 16h5"/>
    </svg>
    Invoice Archive
</a>

@if(Auth::user()?->isAdmin())
    <a href="{{ route('accounts.index') }}"
        data-tour="nav-accounts"
        data-search-link="1"
        data-search-label="accounts permissions users admin"
       @class([
           'flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition',
           'bg-primary text-primary-foreground font-semibold' => $currentRoute === 'accounts.index',
           'light-outline-soft text-muted-foreground hover:bg-muted/50 hover:text-foreground' => $currentRoute !== 'accounts.index',
       ])>
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        Accounts
    </a>
@endif
