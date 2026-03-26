@extends('layouts.app')

@section('title', 'History')

@push('head')
    @vite('resources/js/history-page.ts')
@endpush

@section('content')
<div id="history-page-root"></div>
<script id="history-page-props" type="application/json">@json($historyProps)</script>
@endsection
