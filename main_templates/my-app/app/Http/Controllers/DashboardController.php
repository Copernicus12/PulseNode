<?php

namespace App\Http\Controllers;

use App\Models\EnergyReading;
use App\Support\Esp32ConnectionHealth;
use App\Support\Esp32StateStore;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Esp32StateStore $store, Esp32ConnectionHealth $connectionHealth): View
    {
        $latest = $store->latest();

        // ── Derive system status ──────────────────────────────────────
        $isOnline = $connectionHealth->isOnline($latest);
        $lastSeen    = null;
        $lastSeenAgo = 'niciodată';
        $relayCommandGuard = $connectionHealth->relayCommandAvailability($latest);

        if ($latest['updated_at'] !== null) {
            $updatedAt   = Carbon::parse($latest['updated_at']);
            $lastSeen    = $updatedAt;
            $lastSeenAgo = $updatedAt->diffForHumans();
        }

        // ── Key metrics ───────────────────────────────────────────────
        $voltage  = round((float) $latest['voltage'], 1);
        $current  = round((float) $latest['current'], 3);
        $power    = round((float) $latest['power'], 1);
        $energy   = round((float) $latest['energy'], 4);

        // Per-socket currents
        $current1 = round((float) ($latest['current_1'] ?? 0), 3);
        $current2 = round((float) ($latest['current_2'] ?? 0), 3);
        $current3 = round((float) ($latest['current_3'] ?? 0), 3);
        $power1 = round((float) ($latest['power_1'] ?? 0), 1);
        $power2 = round((float) ($latest['power_2'] ?? 0), 1);
        $power3 = round((float) ($latest['power_3'] ?? 0), 1);

        // ── Relay states ──────────────────────────────────────────────
        $relays = [
            1 => (bool) $latest['relay_1'],
            2 => (bool) $latest['relay_2'],
            3 => (bool) $latest['relay_3'],
        ];
        $activeRelays = count(array_filter($relays));

        // ── Per-socket view data ──────────────────────────────────────
        $sockets = [
            [
                'index'   => 1,
                'label'   => 'Socket 1',
                'is_on'   => $relays[1],
                'current' => $current1,
                'power'   => $power1,
            ],
            [
                'index'   => 2,
                'label'   => 'Socket 2',
                'is_on'   => $relays[2],
                'current' => $current2,
                'power'   => $power2,
            ],
            [
                'index'   => 3,
                'label'   => 'Socket 3',
                'is_on'   => $relays[3],
                'current' => $current3,
                'power'   => $power3,
            ],
        ];

        // ── Safety level ──────────────────────────────────────────────
        $safetyLevel = 'normal';
        if ($power > 2500) {
            $safetyLevel = 'overload';
        } elseif ($power > 1800) {
            $safetyLevel = 'high';
        }

        // ── Weekly energy usage payload ────────────────────────────────
        $energyUsage = EnergyReading::historyPayload();

        return view('dashboard', compact(
            'latest',
            'isOnline',
            'lastSeen',
            'lastSeenAgo',
            'voltage',
            'current',
            'power',
            'energy',
            'current1',
            'current2',
            'current3',
            'relays',
            'activeRelays',
            'sockets',
            'safetyLevel',
            'relayCommandGuard',
            'energyUsage',
        ));
    }
}
