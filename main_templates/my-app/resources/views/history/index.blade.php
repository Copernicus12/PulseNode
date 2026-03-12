@extends('layouts.app')

@section('title', 'History')

@push('head')
    @vite('resources/js/history-page.ts')
@endpush

@section('content')
@php
    $historyProps = [
        'latest' => $latest,
        'dayWindow' => ($dayWindow ?? collect())->values()->all(),
        'daySelector' => $daySelector ?? [
            'anchor_date' => now()->toDateString(),
            'min_date' => now()->subDays(365)->toDateString(),
            'max_date' => now()->toDateString(),
            'window_start' => now()->subDays(6)->toDateString(),
            'window_end' => now()->toDateString(),
        ],
        'selectedDate' => $selectedDate,
        'selectedDay' => $selectedDay,
        'weeklyTotal' => $weeklyTotal,
        'averageDay' => $averageDay,
        'activeHours' => $activeHours,
        'totalWarnings' => $totalWarnings,
        'topHour' => $topHour,
        'topSocket' => $topSocket,
        'peakDay' => $peakDay,
        'lastSeen' => $lastSeen,
        'isOnline' => $isOnline,
        'historyBaseUrl' => route('history.index'),
    ];
@endphp

<div id="history-page-root"></div>
<script id="history-page-props" type="application/json">@json($historyProps)</script>
@endsection
