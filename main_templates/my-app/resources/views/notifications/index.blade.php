@extends('layouts.app')

@section('title', 'Notifications')

@push('head')
    @vite('resources/js/notifications-page.ts')
@endpush

@section('content')
@php
    $levelClasses = [
        'error' => 'bg-red-500/15 text-red-300 ring-1 ring-red-500/20',
        'warning' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/20',
        'success' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/20',
        'info' => 'bg-sky-500/15 text-sky-300 ring-1 ring-sky-500/20',
    ];
    $currentPage = $notifications->currentPage();
    $lastPage = $notifications->lastPage();
    $pageStart = max(1, $currentPage - 2);
    $pageEnd = min($lastPage, $currentPage + 2);
    $filterBarProps = [
        'action' => route('notifications.index'),
        'filters' => [
            'level' => $filters['level'] ?? null,
            'type' => $filters['type'] ?? null,
            'sortBy' => $sortBy,
            'perPage' => $perPage,
        ],
        'levelOptions' => $levelOptions,
        'typeOptions' => $typeOptions,
        'sortOptions' => $sortOptions,
        'perPageOptions' => $perPageOptions,
    ];
@endphp

<div class="space-y-5">
    <section id="notifications-overview" class="light-outline-strong relative overflow-hidden rounded-3xl bg-card p-6">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(144,205,244,0.18),transparent_34%),radial-gradient(circle_at_top_right,rgba(245,158,11,0.12),transparent_28%)]"></div>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="relative">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">
                        Notification center
                    </span>
                </div>

                <h2 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Notifications</h2>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">Relay, telemetry, and service events in one compact feed.</p>
            </div>
        </div>

        <div class="relative mt-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-4">
            <div class="rounded-2xl bg-background/90 p-4 ring-1 ring-border/30 backdrop-blur-sm">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Total notifications</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ $summary['total'] }}</p>
            </div>
            <div class="rounded-2xl bg-background/90 p-4 ring-1 ring-border/30 backdrop-blur-sm">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Warnings</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ $summary['warnings'] }}</p>
            </div>
            <div class="rounded-2xl bg-background/90 p-4 ring-1 ring-border/30 backdrop-blur-sm">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Errors</p>
                <p class="mt-2 text-2xl font-bold tabular-nums">{{ $summary['errors'] }}</p>
            </div>
            <div class="rounded-2xl bg-background/90 p-4 ring-1 ring-border/30 backdrop-blur-sm">
                <p class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Sorting</p>
                <p class="mt-2 text-sm font-semibold text-foreground">{{ $sortOptions[$sortBy] ?? 'Newest first' }}</p>
            </div>
        </div>
    </section>

    <section id="notifications-history" class="light-outline-strong relative overflow-hidden rounded-3xl bg-card p-6">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-[linear-gradient(180deg,rgba(59,130,246,0.08),transparent)]"></div>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative">
                <h3 class="text-lg font-bold">Notification history</h3>
                <p class="text-sm text-muted-foreground">Filter and sort the feed without leaving the page.</p>
            </div>

            <div class="relative w-full max-w-4xl">
                <div id="notifications-filter-root"></div>
                <script id="notifications-filter-props" type="application/json">@json($filterBarProps)</script>
            </div>
        </div>

        @if(($filters['level'] ?? null) || ($filters['type'] ?? null))
            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if($filters['level'] ?? null)
                    <span class="inline-flex rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">
                        Level: {{ ucfirst($filters['level']) }}
                    </span>
                @endif
                @if($filters['type'] ?? null)
                    <span class="inline-flex rounded-full bg-background px-3 py-1 text-xs font-medium text-muted-foreground ring-1 ring-border/40">
                        Type: {{ str_replace('_', ' ', ucfirst($filters['type'])) }}
                    </span>
                @endif
            </div>
        @endif

        <div class="mt-5 space-y-3">
            @forelse($notifications as $notification)
                <article class="rounded-2xl bg-background p-5 ring-1 ring-border/30">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $levelClasses[$notification->level] ?? $levelClasses['info'] }}">
                                    {{ ucfirst($notification->level) }}
                                </span>
                                <span class="inline-flex rounded-full bg-card px-2.5 py-1 text-[11px] font-medium text-muted-foreground ring-1 ring-border/30">
                                    {{ str_replace('_', ' ', ucfirst($notification->type)) }}
                                </span>
                            </div>

                            <h4 class="mt-3 text-base font-semibold text-foreground">{{ $notification->title }}</h4>
                            @if($notification->message)
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ $notification->message }}</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-col items-start gap-3 lg:items-end">
                            <p class="text-sm font-medium text-foreground">{{ $notification->created_at?->diffForHumans() }}</p>
                            <p class="text-xs text-muted-foreground">{{ $notification->created_at?->format('d M Y, H:i:s') }}</p>
                            @if($notification->action_url)
                                <a href="{{ $notification->action_url }}" class="inline-flex items-center justify-center rounded-2xl bg-card px-4 py-2 text-sm font-medium text-foreground ring-1 ring-border/40 transition hover:bg-muted/40">
                                    Open related page
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl bg-background p-6 text-center text-sm text-muted-foreground ring-1 ring-border/30">
                    No notifications yet. Relay actions, telemetry changes, and maintenance events will appear here.
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="mt-6 flex flex-col gap-4 border-t border-border/20 pt-5 lg:flex-row lg:items-center lg:justify-between">
                <p class="text-sm text-muted-foreground">
                    Showing {{ $notifications->firstItem() }}-{{ $notifications->lastItem() }} of {{ $notifications->total() }} notifications.
                </p>

                <nav class="flex flex-wrap items-center gap-2" aria-label="Notifications pagination">
                    <a href="{{ $notifications->onFirstPage() ? '#' : $notifications->url(1) }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $notifications->onFirstPage() ? 'pointer-events-none bg-muted text-muted-foreground/60' : 'bg-background text-foreground hover:bg-muted/40' }}">
                        First
                    </a>
                    <a href="{{ $notifications->previousPageUrl() ?? '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $notifications->onFirstPage() ? 'pointer-events-none bg-muted text-muted-foreground/60' : 'bg-background text-foreground hover:bg-muted/40' }}">
                        Previous
                    </a>

                    @for($page = $pageStart; $page <= $pageEnd; $page++)
                        <a href="{{ $notifications->url($page) }}" class="inline-flex h-10 min-w-10 items-center justify-center rounded-2xl px-3 text-sm font-medium ring-1 transition {{ $page === $currentPage ? 'bg-primary text-primary-foreground ring-primary/30' : 'bg-background text-foreground ring-border/40 hover:bg-muted/40' }}">
                            {{ $page }}
                        </a>
                    @endfor

                    <a href="{{ $notifications->nextPageUrl() ?? '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $notifications->hasMorePages() ? 'bg-background text-foreground hover:bg-muted/40' : 'pointer-events-none bg-muted text-muted-foreground/60' }}">
                        Next
                    </a>
                    <a href="{{ $notifications->hasMorePages() ? $notifications->url($lastPage) : '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $notifications->hasMorePages() ? 'bg-background text-foreground hover:bg-muted/40' : 'pointer-events-none bg-muted text-muted-foreground/60' }}">
                        Last
                    </a>
                </nav>
            </div>
        @endif
    </section>
</div>
@endsection
