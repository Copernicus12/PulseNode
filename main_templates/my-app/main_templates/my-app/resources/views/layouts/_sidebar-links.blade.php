@php $currentRoute = request()->route()?->getName() ?? ''; @endphp

<a href="{{ route('dashboard') }}"
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
    data-search-link="1"
    data-search-label="power strip"
   @class([
       'flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] transition',
       'bg-primary text-primary-foreground font-semibold' => str_starts_with($currentRoute, 'power-strip'),
       'text-muted-foreground hover:bg-muted/50 hover:text-foreground' => !str_starts_with($currentRoute, 'power-strip'),
   ])>
    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v5a6 6 0 0 1-12 0V8z"/></svg>
    Power Strip
</a>

<a href="#"
    data-search-link="1"
    data-search-label="my devices"
   class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] text-muted-foreground transition hover:bg-muted/50 hover:text-foreground">
    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
    My Devices
</a>

<a href="{{ route('history.index') }}"
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

<a href="#"
    data-search-link="1"
    data-search-label="battery level"
   class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[14px] text-muted-foreground transition hover:bg-muted/50 hover:text-foreground">
    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
    Battery Level
</a>
