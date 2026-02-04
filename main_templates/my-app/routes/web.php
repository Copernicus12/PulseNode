<?php

use App\Http\Controllers\Esp32ApiController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('dashboard-test', function () {
    return Inertia::render('DashboardTest');
})->middleware(['auth', 'verified'])->name('dashboard.test');

Route::prefix('api')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('latest', [Esp32ApiController::class, 'latest'])->name('api.latest');
    Route::get('relay/{state}', [Esp32ApiController::class, 'relay'])->name('api.relay');
});

Route::post('api/ingest', [Esp32ApiController::class, 'ingest'])->name('api.ingest');

require __DIR__.'/settings.php';
