<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ElectricityBillingController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', function (Request $request) {
        return redirect()->route('electricity-billing.edit');
    })->name('settings.index');

    Route::get('settings/profile', function (Request $request, ProfileController $controller) {
        if ($request->user()?->role === 'admin') {
            return redirect()->route('accounts.index');
        }

        return $controller->edit($request);
    })->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', function (Request $request, PasswordController $controller) {
        if ($request->user()?->role === 'admin') {
            return redirect()->route('accounts.index');
        }

        return $controller->edit();
    })->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', function (Request $request, TwoFactorAuthenticationController $controller) {
        if ($request->user()?->role === 'admin') {
            return redirect()->route('accounts.index');
        }

        return $controller->show($request);
    })->name('two-factor.show');

    Route::get('settings/electricity-billing', [ElectricityBillingController::class, 'edit'])
        ->name('electricity-billing.edit');
    Route::patch('settings/electricity-billing', [ElectricityBillingController::class, 'update'])
        ->name('electricity-billing.update');
    Route::post('settings/electricity-billing/profiles', [ElectricityBillingController::class, 'storeProfile'])
        ->name('electricity-billing.profiles.store');
    Route::delete('settings/electricity-billing/profiles/{profileId}', [ElectricityBillingController::class, 'destroyProfile'])
        ->name('electricity-billing.profiles.destroy');
});
