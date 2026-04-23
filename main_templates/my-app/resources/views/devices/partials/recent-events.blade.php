@php
    $detectionStateClasses = [
        'matched' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30',
        'unknown' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/30',
        'idle' => 'bg-muted text-muted-foreground ring-1 ring-border/40',
    ];
@endphp

<section id="devices-recent-events" class="light-outline-strong rounded-3xl bg-card p-5 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-bold">Recent Detection Events</h3>
            <p class="mt-1 text-sm text-muted-foreground">Latest recognition updates saved by the detection engine.</p>
        </div>

        @if($recentDetections->total() > 0)
            <div class="rounded-2xl bg-background px-4 py-3 text-xs text-muted-foreground ring-1 ring-border/40">
                Showing <span class="font-medium text-foreground">{{ $recentDetections->firstItem() }}-{{ $recentDetections->lastItem() }}</span>
                of <span class="font-medium text-foreground">{{ $recentDetections->total() }}</span>
            </div>
        @endif
    </div>

    @if($recentDetections->total() > 0)
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
                    @foreach($recentDetections as $event)
                        <tr class="border-b border-border/20 last:border-0">
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $event->detected_at?->diffForHumans() }}</td>
                            <td class="px-3 py-2.5">{{ $event->socket_index }}</td>
                            <td class="px-3 py-2.5 font-medium">{{ $event->predicted_label }}</td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $event->predicted_category ?? '-' }}</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $event->confidence }}%</td>
                            <td class="px-3 py-2.5 text-muted-foreground">{{ $event->plan?->name ?? 'Default' }}</td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $detectionStateClasses[$event->status] ?? 'bg-muted text-muted-foreground ring-1 ring-border/40' }}">{{ ucfirst($event->status) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="mt-4 rounded-2xl bg-background p-4 text-sm text-muted-foreground ring-1 ring-border/30">
            No detection events recorded yet.
        </div>
    @endif

    @if($recentDetections->hasPages())
        @php
            $currentPage = $recentDetections->currentPage();
            $lastPage = $recentDetections->lastPage();
            $pageStart = max(1, $currentPage - 2);
            $pageEnd = min($lastPage, $currentPage + 2);
        @endphp
        <div class="mt-6 flex flex-col gap-4 border-t border-border/20 pt-5 lg:flex-row lg:items-center lg:justify-between">
            <p class="text-sm text-muted-foreground">
                Showing {{ $recentDetections->firstItem() }}-{{ $recentDetections->lastItem() }} of {{ $recentDetections->total() }} detection events.
            </p>

            <nav class="flex flex-wrap items-center gap-2" aria-label="Recent detection events pagination">
                <a href="{{ $recentDetections->onFirstPage() ? '#' : $recentDetections->url(1) }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $recentDetections->onFirstPage() ? 'pointer-events-none bg-muted text-muted-foreground/60' : 'bg-background text-foreground hover:bg-muted/40' }}">
                    First
                </a>
                <a href="{{ $recentDetections->previousPageUrl() ?? '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $recentDetections->onFirstPage() ? 'pointer-events-none bg-muted text-muted-foreground/60' : 'bg-background text-foreground hover:bg-muted/40' }}">
                    Previous
                </a>

                @for($page = $pageStart; $page <= $pageEnd; $page++)
                    <a href="{{ $recentDetections->url($page) }}" class="inline-flex h-10 min-w-10 items-center justify-center rounded-2xl px-3 text-sm font-medium ring-1 transition {{ $page === $currentPage ? 'bg-primary text-primary-foreground ring-primary/30' : 'bg-background text-foreground ring-border/40 hover:bg-muted/40' }}">
                        {{ $page }}
                    </a>
                @endfor

                <a href="{{ $recentDetections->nextPageUrl() ?? '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $recentDetections->hasMorePages() ? 'bg-background text-foreground hover:bg-muted/40' : 'pointer-events-none bg-muted text-muted-foreground/60' }}">
                    Next
                </a>
                <a href="{{ $recentDetections->hasMorePages() ? $recentDetections->url($lastPage) : '#' }}" class="inline-flex h-10 items-center justify-center rounded-2xl px-4 text-sm font-medium ring-1 ring-border/40 transition {{ $recentDetections->hasMorePages() ? 'bg-background text-foreground hover:bg-muted/40' : 'pointer-events-none bg-muted text-muted-foreground/60' }}">
                    Last
                </a>
            </nav>
        </div>
    @endif
</section>
