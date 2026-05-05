<?php

namespace App\Listeners;

use App\Support\SingleDeviceSessionManager;
use Illuminate\Auth\Events\Login;

class SyncSingleDeviceSession
{
    public function __construct(private readonly SingleDeviceSessionManager $singleDeviceSessionManager) {}

    public function handle(Login $event): void
    {
        if (! request()->hasSession()) {
            return;
        }

        $this->singleDeviceSessionManager->activate($event->user, request()->session());
    }
}
