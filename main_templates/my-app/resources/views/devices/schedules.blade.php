@extends('layouts.app')

@section('title', 'Schedules')

@push('head')
    @vite('resources/js/schedules-toast.ts')
    @vite('resources/js/schedules-page.ts')
@endpush

@section('content')
@php
    $statusClasses = [
        'matched' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30',
        'unknown' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/30',
        'idle' => 'bg-muted text-muted-foreground ring-1 ring-border/40',
        'off' => 'bg-muted text-muted-foreground ring-1 ring-border/40',
        'high_load' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/30',
        'overload' => 'bg-red-500/15 text-red-300 ring-1 ring-red-500/30',
        'offline' => 'bg-muted text-muted-foreground ring-1 ring-border/40',
    ];

    $dayLabels = [
        'mon' => 'Mon',
        'tue' => 'Tue',
        'wed' => 'Wed',
        'thu' => 'Thu',
        'fri' => 'Fri',
        'sat' => 'Sat',
        'sun' => 'Sun',
    ];

    $actionLabels = [
        'on' => 'Turn on',
        'off' => 'Turn off',
    ];

    $firstSocket = collect($socketOverview ?? [])->first();

    $schedulesPageProps = [
        'csrfToken' => csrf_token(),
        'storeUrl' => route('devices.schedules.store'),
        'redirectRoute' => request()->route()?->getName() ?? 'devices.schedules.index',
        'redirectPage' => max(1, (int) request()->integer('page', 1)),
        'shouldOpenOnMount' => $errors->any(),
        'validationErrors' => $errors->getMessages(),
        'initialForm' => [
            'name' => old('name', ''),
            'socket_index' => (string) old('socket_index', $firstSocket['index'] ?? 1),
            'action' => old('action', 'on'),
            'is_active' => old('is_active') ? true : false,
            'start_time' => old('start_time', ''),
            'end_time' => old('end_time', ''),
            'days_of_week' => old('days_of_week', []),
            'notes' => old('notes', ''),
        ],
        'socketOptions' => collect($socketOverview ?? [])->map(function ($socket) {
            return [
                'value' => (string) $socket['index'],
                'label' => $socket['label'],
                'title' => 'Add schedule for ' . $socket['label'],
            ];
        })->values()->all(),
        'dayOptions' => collect($dayLabels)->map(function ($dayLabel, $dayKey) {
            return [
                'value' => $dayKey,
                'label' => $dayLabel,
            ];
        })->values()->all(),
    ];
@endphp

<div id="schedules-toast-root"></div>
<div id="schedules-page-root"></div>
<script id="schedules-page-props" type="application/json">@json($schedulesPageProps)</script>

@if(session('devices_success'))
    <script>
        window.setTimeout(function () {
            window.dispatchEvent(new CustomEvent('pulsenode:app-toast', {
                detail: {
                    level: 'success',
                    title: 'Schedule updated',
                    message: @json(session('devices_success')),
                }
            }));
        }, 60);
    </script>
@endif

@if(session('devices_error'))
    <script>
        window.setTimeout(function () {
            window.dispatchEvent(new CustomEvent('pulsenode:app-toast', {
                detail: {
                    level: 'error',
                    title: 'Schedule error',
                    message: @json(session('devices_error')),
                }
            }));
        }, 60);
    </script>
@endif

@if($errors->any())
    <script>
        window.setTimeout(function () {
            window.dispatchEvent(new CustomEvent('pulsenode:app-toast', {
                detail: {
                    level: 'error',
                    title: 'Validation failed',
                    message: @json($errors->first()),
                }
            }));
        }, 60);
    </script>
@endif

<div class="mx-auto max-w-[1360px] space-y-5 lg:space-y-6">
    <section class="light-outline-strong relative overflow-hidden rounded-3xl bg-card p-5 sm:p-6">
        <div class="pointer-events-none absolute inset-0 bg-linear-to-r from-primary/8 via-transparent to-transparent"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="text-[11px] uppercase tracking-[0.24em] text-muted-foreground">Time-based control</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Socket schedules</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                    Add weekly rules per socket, keep the layout clean, and use Sonner for quick feedback after every save.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full bg-primary/15 px-3 py-1 text-xs font-medium text-primary">
                    {{ $scheduleStats['active_rules'] }} active
                </span>
                <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs text-muted-foreground ring-1 ring-border/30">
                    Next: {{ $scheduleStats['next_trigger'] }}
                </span>
                <span class="inline-flex items-center rounded-full bg-background px-3 py-1 text-xs text-muted-foreground ring-1 ring-border/30">
                    {{ $scheduleStats['coverage'] }}
                </span>
            </div>
        </div>
    </section>

    <section class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">Socket boards</h2>
                <p class="text-sm text-muted-foreground">Each socket gets one compact card and one quick-add action.</p>
            </div>
            <button
                type="button"
                data-modal-open="create-schedule-modal"
                class="rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                New schedule
            </button>
        </div>

        <div class="grid gap-3 lg:grid-cols-3">
            @foreach($socketOverview as $socket)
                <article class="rounded-3xl border border-border/40 bg-card p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">Socket {{ $socket['index'] }}</p>
                            <h3 class="mt-1 text-base font-semibold">{{ $socket['label'] }}</h3>
                            <p class="mt-1 text-xs text-muted-foreground">{{ $socket['status_label'] }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $statusClasses[$socket['status']] ?? $statusClasses['idle'] }}">
                            {{ $socket['state_label'] }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                        <div class="rounded-2xl bg-background px-3 py-2 ring-1 ring-border/20">
                            <p class="text-[11px] text-muted-foreground">Power</p>
                            <p class="mt-1 font-semibold tabular-nums">{{ number_format($socket['power'], 1) }} W</p>
                        </div>
                        <div class="rounded-2xl bg-background px-3 py-2 ring-1 ring-border/20">
                            <p class="text-[11px] text-muted-foreground">Current</p>
                            <p class="mt-1 font-semibold tabular-nums">{{ number_format($socket['current'], 3) }} A</p>
                        </div>
                    </div>

                    <div class="mt-3 rounded-2xl bg-background px-3 py-3 ring-1 ring-border/20">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs text-muted-foreground">Schedules</p>
                            <p class="text-xs font-medium text-foreground">{{ $socket['schedule_count'] }}</p>
                        </div>
                        <p class="mt-1 text-sm font-medium text-foreground">
                            {{ $socket['next_schedule'] ? $socket['next_schedule']->name : 'No schedules yet' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        data-modal-open="create-schedule-modal"
                        data-schedule-socket="{{ $socket['index'] }}"
                        data-schedule-title="Add schedule for {{ $socket['label'] }}"
                        class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Add schedule
                    </button>
                </article>
            @endforeach
        </div>
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="text-lg font-bold">Current schedules</h2>
            <p class="text-sm text-muted-foreground">Active rules, pausing, and deletes are all in one simple list.</p>
        </div>

        <div class="light-outline-strong rounded-3xl bg-card p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold">Schedules table</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Sorted by socket, then by time, with 5 rows per page.</p>
                </div>

                @if($scheduleEntriesPage->total() > 0)
                    <div class="rounded-2xl bg-background px-4 py-3 text-xs text-muted-foreground ring-1 ring-border/40">
                        Showing <span class="font-medium text-foreground">{{ $scheduleEntriesPage->firstItem() }}-{{ $scheduleEntriesPage->lastItem() }}</span>
                        of <span class="font-medium text-foreground">{{ $scheduleEntriesPage->total() }}</span>
                    </div>
                @endif
            </div>

            @if($scheduleEntriesPage->total() > 0)
                <div class="mt-4 overflow-x-auto rounded-2xl bg-background ring-1 ring-border/30">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b border-border/30 text-left text-xs text-muted-foreground">
                                <th class="px-3 py-2.5 font-medium">Time</th>
                                <th class="px-3 py-2.5 font-medium">Socket</th>
                                <th class="px-3 py-2.5 font-medium">Schedule</th>
                                <th class="px-3 py-2.5 font-medium">Action</th>
                                <th class="px-3 py-2.5 font-medium">Days</th>
                                <th class="px-3 py-2.5 font-medium">Status</th>
                                <th class="px-3 py-2.5 font-medium">Controls</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($scheduleEntriesPage as $schedule)
                                <tr class="border-b border-border/20 last:border-0">
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        <div class="font-medium text-foreground">{{ $schedule['start_time'] }}</div>
                                        <div class="text-xs text-muted-foreground">to {{ $schedule['end_time'] }}</div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex rounded-full bg-primary/15 px-2.5 py-1 text-[11px] font-medium text-primary">
                                            {{ $schedule['socket_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-medium">{{ $schedule['name'] }}</div>
                                        @if(!empty($schedule['notes']))
                                            <div class="mt-1 max-w-[360px] truncate text-xs text-muted-foreground">{{ $schedule['notes'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex rounded-full bg-background px-2.5 py-1 text-[11px] font-medium text-foreground ring-1 ring-border/40">
                                            {{ $actionLabels[$schedule['action']] ?? ucfirst($schedule['action']) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-muted-foreground">
                                        {{ implode(' · ', $schedule['days']) }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium {{ $schedule['is_active'] ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30' : 'bg-muted text-muted-foreground ring-1 ring-border/40' }}">
                                            {{ $schedule['is_active'] ? 'Active' : 'Paused' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('devices.schedules.toggle', $schedule['id']) }}">
                                                @csrf
                                                <input type="hidden" name="redirect_route" value="{{ request()->route()?->getName() ?? 'devices.schedules.index' }}">
                                                <input type="hidden" name="redirect_page" value="{{ request()->integer('page', 1) }}">
                                                <button type="submit" class="rounded-xl bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">
                                                    {{ $schedule['is_active'] ? 'Pause' : 'Resume' }}
                                                </button>
                                            </form>
                                            <button
                                                type="button"
                                                data-modal-open="delete-schedule-modal"
                                                data-schedule-name="{{ $schedule['name'] }}"
                                                data-schedule-socket="{{ $schedule['socket_label'] }}"
                                                data-schedule-window="{{ $schedule['start_time'] }} - {{ $schedule['end_time'] }}"
                                                data-schedule-days="{{ implode(' · ', $schedule['days']) }}"
                                                data-schedule-delete-url="{{ route('devices.schedules.destroy', $schedule['id']) }}"
                                                class="rounded-xl bg-red-500/15 px-3 py-1.5 text-xs font-medium text-red-300 transition hover:bg-red-500/25"
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
                <div class="mt-4 rounded-2xl bg-background p-4 text-sm text-muted-foreground ring-1 ring-border/30">
                    No schedules yet. Add one from any socket card to start building your routine.
                </div>
            @endif

            @if($scheduleEntriesPage->hasPages())
                @php
                    $currentPage = $scheduleEntriesPage->currentPage();
                    $lastPage = $scheduleEntriesPage->lastPage();
                    $pageStart = max(1, $currentPage - 2);
                    $pageEnd = min($lastPage, $currentPage + 2);
                @endphp
                <div class="mt-6 flex flex-col gap-4 border-t border-border/20 pt-5 lg:flex-row lg:items-center lg:justify-between">
                    <p class="text-sm text-muted-foreground">
                        Showing {{ $scheduleEntriesPage->firstItem() }}-{{ $scheduleEntriesPage->lastItem() }} of {{ $scheduleEntriesPage->total() }} schedules.
                    </p>

                    <nav class="flex flex-wrap items-center gap-2" aria-label="Schedules pagination">
                        <a href="{{ $scheduleEntriesPage->onFirstPage() ? '#' : $scheduleEntriesPage->url(1) }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $scheduleEntriesPage->onFirstPage() ? 'pointer-events-none bg-muted text-muted-foreground/60' : 'bg-background text-foreground hover:bg-muted/40' }}">
                            First
                        </a>
                        <a href="{{ $scheduleEntriesPage->previousPageUrl() ?? '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $scheduleEntriesPage->onFirstPage() ? 'pointer-events-none bg-muted text-muted-foreground/60' : 'bg-background text-foreground hover:bg-muted/40' }}">
                            Previous
                        </a>

                        @for($page = $pageStart; $page <= $pageEnd; $page++)
                            <a href="{{ $scheduleEntriesPage->url($page) }}" class="inline-flex h-10 min-w-10 items-center justify-center rounded-2xl px-3 text-sm font-medium ring-1 transition {{ $page === $currentPage ? 'bg-primary text-primary-foreground ring-primary/30' : 'bg-background text-foreground ring-border/40 hover:bg-muted/40' }}">
                                {{ $page }}
                            </a>
                        @endfor

                        <a href="{{ $scheduleEntriesPage->nextPageUrl() ?? '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $scheduleEntriesPage->hasMorePages() ? 'bg-background text-foreground hover:bg-muted/40' : 'pointer-events-none bg-muted text-muted-foreground/60' }}">
                            Next
                        </a>
                        <a href="{{ $scheduleEntriesPage->hasMorePages() ? $scheduleEntriesPage->url($lastPage) : '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $scheduleEntriesPage->hasMorePages() ? 'bg-background text-foreground hover:bg-muted/40' : 'pointer-events-none bg-muted text-muted-foreground/60' }}">
                            Last
                        </a>
                    </nav>
                </div>
            @endif
        </div>
    </section>
</div>

<div id="delete-schedule-modal" data-modal class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/70 p-4 backdrop-blur-md">
    <div class="relative w-full max-w-xl overflow-hidden rounded-[2rem] border border-red-500/20 bg-card shadow-2xl shadow-black/30">
        <div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-red-400/80 to-transparent"></div>
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.24em] text-muted-foreground">Delete schedule</p>
                    <h4 class="mt-1 text-2xl font-semibold tracking-tight">Delete schedule?</h4>
                    <p class="mt-2 text-sm text-muted-foreground">This action permanently removes the selected rule and it will stop running immediately.</p>
                </div>
                <button type="button" data-modal-close class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-background text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">
                    <span class="sr-only">Close</span>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="mt-6 rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-100/90 ring-1 ring-red-500/20">
                <p class="font-medium">You are about to delete <span id="delete-schedule-modal-name" class="font-semibold text-white">this schedule</span>.</p>
                <div class="mt-3 grid gap-2 text-xs text-red-100/80 sm:grid-cols-3">
                    <div class="rounded-xl bg-black/10 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-red-100/60">Socket</p>
                        <p id="delete-schedule-modal-socket" class="mt-1 font-medium text-red-50">Socket 1</p>
                    </div>
                    <div class="rounded-xl bg-black/10 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-red-100/60">Window</p>
                        <p id="delete-schedule-modal-window" class="mt-1 font-medium text-red-50">00:00 - 00:00</p>
                    </div>
                    <div class="rounded-xl bg-black/10 px-3 py-2">
                        <p class="uppercase tracking-[0.18em] text-red-100/60">Days</p>
                        <p id="delete-schedule-modal-days" class="mt-1 font-medium text-red-50">Mon · Tue · Wed</p>
                    </div>
                </div>
                <p class="mt-3 text-red-100/80">The schedule will be removed from the board and from future automation runs.</p>
            </div>

            <form id="delete-schedule-modal-form" method="POST" action="{{ route('devices.schedules.index') }}" class="mt-6">
                @csrf
                @method('DELETE')
                <input type="hidden" name="redirect_route" value="{{ request()->route()?->getName() ?? 'devices.schedules.index' }}">
                <input type="hidden" name="redirect_page" value="{{ request()->integer('page', 1) }}">

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" data-modal-close data-modal-initial-focus class="rounded-xl bg-background px-3.5 py-2.5 text-sm text-muted-foreground ring-1 ring-border/40 transition hover:text-foreground">Cancel</button>
                    <button type="submit" class="rounded-xl bg-red-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-400">Delete schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var deleteModal = document.getElementById('delete-schedule-modal');
    var deleteForm = document.getElementById('delete-schedule-modal-form');
    var deleteName = document.getElementById('delete-schedule-modal-name');
    var deleteSocket = document.getElementById('delete-schedule-modal-socket');
    var deleteWindow = document.getElementById('delete-schedule-modal-window');
    var deleteDays = document.getElementById('delete-schedule-modal-days');

    function allModals() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-modal]'));
    }

    function isOpen(entry) {
        return entry && !entry.classList.contains('hidden');
    }

    function openModal(entry) {
        if (!entry) return;
        entry.classList.remove('hidden');
        entry.classList.add('flex');
        document.body.classList.add('overflow-hidden');

        var focusTarget = entry.querySelector('[data-modal-initial-focus]');
        if (focusTarget) {
            setTimeout(function () {
                focusTarget.focus();
            }, 20);
        }
    }

    function closeModal(entry) {
        if (!entry) return;
        entry.classList.add('hidden');
        entry.classList.remove('flex');

        var anyOpen = allModals().some(function (item) {
            return isOpen(item);
        });

        if (!anyOpen) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    document.addEventListener('click', function (event) {
        var deleteTrigger = event.target && event.target.closest ? event.target.closest('[data-modal-open="delete-schedule-modal"]') : null;
        if (!deleteTrigger || !deleteModal) {
            return;
        }

        if (deleteForm && deleteTrigger.getAttribute('data-schedule-delete-url')) {
            deleteForm.setAttribute('action', deleteTrigger.getAttribute('data-schedule-delete-url'));
        }

        if (deleteName && deleteTrigger.getAttribute('data-schedule-name')) {
            deleteName.textContent = deleteTrigger.getAttribute('data-schedule-name');
        }

        if (deleteSocket && deleteTrigger.getAttribute('data-schedule-socket')) {
            deleteSocket.textContent = deleteTrigger.getAttribute('data-schedule-socket');
        }

        if (deleteWindow && deleteTrigger.getAttribute('data-schedule-window')) {
            deleteWindow.textContent = deleteTrigger.getAttribute('data-schedule-window');
        }

        if (deleteDays && deleteTrigger.getAttribute('data-schedule-days')) {
            deleteDays.textContent = deleteTrigger.getAttribute('data-schedule-days');
        }

        allModals().forEach(function (entry) {
            if (entry !== deleteModal && isOpen(entry)) {
                closeModal(entry);
            }
        });

        openModal(deleteModal);
    });

    Array.prototype.slice.call(document.querySelectorAll('[data-modal-close]')).forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(button.closest('[data-modal]'));
        });
    });

    if (deleteModal) {
        deleteModal.addEventListener('click', function (event) {
            if (event.target === deleteModal) {
                closeModal(deleteModal);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            allModals().forEach(function (entry) {
                if (isOpen(entry)) {
                    closeModal(entry);
                }
            });
        }
    });
})();
</script>
@endsection
