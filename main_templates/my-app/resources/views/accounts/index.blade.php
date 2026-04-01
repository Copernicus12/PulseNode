@extends('layouts.app')

@section('title', 'Accounts')

@push('head')
    @vite('resources/js/accounts-page.ts')
@endpush

@section('content')
@php
    $authUser = Auth::user();

    $accountsPageProps = [
        'summary' => $summary,
        'roles' => array_values($roles ?? []),
        'csrfToken' => csrf_token(),
        'flash' => [
            'success' => session('accounts_success'),
            'error' => session('accounts_error'),
            'validation' => $errors->first(),
        ],
        'validationErrors' => $errors->getMessages(),
        'routes' => [
            'store' => route('accounts.store'),
            'profile_update' => route('accounts.profile.update'),
            'password_update' => route('accounts.password.update'),
        ],
        'currentUser' => $authUser ? [
            'name' => $authUser->name,
            'email' => $authUser->email,
            'email_verified_at' => $authUser->email_verified_at?->toIso8601String(),
            'must_verify_email' => $authUser instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
            'two_factor_enabled' => method_exists($authUser, 'hasEnabledTwoFactorAuthentication')
                ? $authUser->hasEnabledTwoFactorAuthentication()
                : false,
            'requires_two_factor_confirmation' => \Laravel\Fortify\Features::optionEnabled(
                \Laravel\Fortify\Features::twoFactorAuthentication(),
                'confirmPassword',
            ),
        ] : null,
        'users' => collect($users ?? [])->map(fn ($user) => [
            'id' => (string) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_blocked' => (bool) $user->is_blocked,
            'guest_expires_at' => $user->guest_expires_at?->toIso8601String(),
            'blocked_at' => $user->blocked_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'is_self' => $authUser?->is($user) ?? false,
            'update_url' => route('accounts.update', $user),
            'toggle_block_url' => route('accounts.toggle-block', $user),
            'destroy_url' => route('accounts.destroy', $user),
        ])->values()->all(),
    ];
@endphp

<div id="accounts-page-root"></div>
<script id="accounts-page-props" type="application/json">@json($accountsPageProps)</script>
@endsection
