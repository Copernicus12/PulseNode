<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Support\SocketScheduleRunner;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('power-strip:run-schedules', function () {
    $result = app(SocketScheduleRunner::class)->run();

    $this->info(sprintf(
        'Checked %d schedule%s, triggered %d.',
        (int) ($result['checked'] ?? 0),
        (int) ($result['checked'] ?? 0) === 1 ? '' : 's',
        (int) ($result['triggered'] ?? 0),
    ));
})->purpose('Run due power strip socket schedules');

Schedule::command('power-strip:run-schedules')
    ->everyMinute()
    ->withoutOverlapping()
    ->timezone(config('app.timezone'));
