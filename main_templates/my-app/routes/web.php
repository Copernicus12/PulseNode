<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Esp32ApiController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PowerStripController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth'])->name('dashboard');
Route::prefix('accounts')->middleware(['auth', 'admin'])->name('accounts.')->group(function (): void {
    Route::get('/', [AccountsController::class, 'index'])->name('index');
    Route::post('/', [AccountsController::class, 'store'])->name('store');
    Route::patch('profile', [AccountsController::class, 'updateCurrentProfile'])->name('profile.update');
    Route::put('password', [AccountsController::class, 'updateCurrentPassword'])
        ->middleware('throttle:6,1')
        ->name('password.update');
    Route::patch('{user}', [AccountsController::class, 'update'])->name('update');
    Route::post('{user}/toggle-block', [AccountsController::class, 'toggleBlock'])->name('toggle-block');
    Route::delete('{user}', [AccountsController::class, 'destroy'])->name('destroy');
});

Route::get('power-strip', [PowerStripController::class, 'index'])
    ->middleware(['auth'])->name('power-strip.index');
Route::prefix('devices')->middleware(['auth'])->name('devices.')->group(function (): void {
    Route::get('/', [PowerStripController::class, 'devices'])->name('index');
    Route::get('profiles', [PowerStripController::class, 'deviceProfiles'])->name('profiles.index');
    Route::get('plans', [PowerStripController::class, 'devicePlans'])->name('plans.index');
    Route::get('activity', [PowerStripController::class, 'deviceActivity'])->name('activity.index');

    Route::post('profiles', [PowerStripController::class, 'storeDeviceProfile'])->name('profiles.store');
    Route::delete('profiles/{profile}', [PowerStripController::class, 'destroyDeviceProfile'])->name('profiles.destroy');
    Route::post('plans', [PowerStripController::class, 'storeDetectionPlan'])->name('plans.store');
    Route::post('plans/{plan}/activate', [PowerStripController::class, 'activateDetectionPlan'])->name('plans.activate');
    Route::delete('plans/{plan}', [PowerStripController::class, 'destroyDetectionPlan'])->name('plans.destroy');
});
Route::get('history', [PowerStripController::class, 'history'])
    ->middleware(['auth'])->name('history.index');
Route::get('battery', [PowerStripController::class, 'battery'])
    ->middleware(['auth'])->name('battery.index');
Route::get('notifications', [NotificationController::class, 'index'])
    ->middleware(['auth'])->name('notifications.index');
Route::get('power-strip/settings', function () {
    return redirect()->route('power-strip-diagnostics.edit');
})->middleware(['auth'])->name('power-strip.settings');

Route::prefix('api')->middleware(['auth'])->group(function (): void {
    Route::get('latest', [Esp32ApiController::class, 'latest'])->name('api.latest');
    Route::get('notifications/latest', [NotificationController::class, 'latest'])->name('api.notifications.latest');
    Route::get('relay/{relayId}/{state}', [Esp32ApiController::class, 'relay'])->name('api.relay');
    Route::get('energy-history', [Esp32ApiController::class, 'energyHistory'])->name('api.energy-history');
    Route::get('energy-day/{date}', [Esp32ApiController::class, 'energyDay'])->name('api.energy-day');
    Route::get('devices/live-detections', [Esp32ApiController::class, 'liveDetections'])->name('api.devices.live-detections');
    Route::get('system/mqtt-listener/restart', [Esp32ApiController::class, 'restartMqttListener'])->name('api.system.mqtt.restart');
});

Route::post('api/ingest', [Esp32ApiController::class, 'ingest'])->name('api.ingest');

require __DIR__.'/settings.php';
