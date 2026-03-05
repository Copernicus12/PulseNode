<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Esp32ApiController;
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

Route::get('power-strip', [PowerStripController::class, 'index'])
    ->middleware(['auth'])->name('power-strip.index');
Route::get('history', [PowerStripController::class, 'history'])
    ->middleware(['auth'])->name('history.index');
Route::get('power-strip/settings', [PowerStripController::class, 'settings'])
    ->middleware(['auth'])->name('power-strip.settings');

Route::prefix('api')->middleware(['auth'])->group(function (): void {
    Route::get('latest', [Esp32ApiController::class, 'latest'])->name('api.latest');
    Route::get('relay/{relayId}/{state}', [Esp32ApiController::class, 'relay'])->name('api.relay');
    Route::get('energy-history', [Esp32ApiController::class, 'energyHistory'])->name('api.energy-history');
    Route::get('energy-day/{date}', [Esp32ApiController::class, 'energyDay'])->name('api.energy-day');
    Route::get('system/mqtt-listener/restart', [Esp32ApiController::class, 'restartMqttListener'])->name('api.system.mqtt.restart');
});

Route::post('api/ingest', [Esp32ApiController::class, 'ingest'])->name('api.ingest');

require __DIR__.'/settings.php';
