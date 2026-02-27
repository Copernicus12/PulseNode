<?php

namespace App\Http\Controllers;

use App\Support\Esp32StateStore;
use Illuminate\View\View;

class PowerStripController extends Controller
{
    /**
     * Main power-strip monitoring dashboard.
     */
    public function index(Esp32StateStore $store): View
    {
        $latest = $store->latest();

        // Derive per-socket data from the flat state store.
        // In a full implementation these would come from DB models;
        // we build a view-model here so the Blade templates stay clean.
        $sockets = [
            [
                'index'      => 1,
                'label'      => 'Socket 1',
                'is_on'      => (bool) ($latest['relay_1'] ?? false),
                'voltage'    => round((float) ($latest['voltage'] ?? 0), 1),
                'current'    => round((float) ($latest['current_1'] ?? 0), 3),
                'power_w'    => round((float) ($latest['voltage'] ?? 0) * (float) ($latest['current_1'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status'     => $this->deriveSocketStatus($latest, 1),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
            [
                'index'      => 2,
                'label'      => 'Socket 2',
                'is_on'      => (bool) ($latest['relay_2'] ?? false),
                'voltage'    => round((float) ($latest['voltage'] ?? 0), 1),
                'current'    => round((float) ($latest['current_2'] ?? 0), 3),
                'power_w'    => round((float) ($latest['voltage'] ?? 0) * (float) ($latest['current_2'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status'     => $this->deriveSocketStatus($latest, 2),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
            [
                'index'      => 3,
                'label'      => 'Socket 3',
                'is_on'      => (bool) ($latest['relay_3'] ?? false),
                'voltage'    => round((float) ($latest['voltage'] ?? 0), 1),
                'current'    => round((float) ($latest['current_3'] ?? 0), 3),
                'power_w'    => round((float) ($latest['voltage'] ?? 0) * (float) ($latest['current_3'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status'     => $this->deriveSocketStatus($latest, 3),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
        ];

        $activeSockets = collect($sockets)->where('is_on', true)->count();
        $totalPower    = collect($sockets)->sum('power_w');
        $totalEnergy   = collect($sockets)->sum('energy_kwh');
        $systemStatus  = $this->deriveSystemStatus($latest);

        return view('power-strip.index', compact(
            'latest',
            'sockets',
            'activeSockets',
            'totalPower',
            'totalEnergy',
            'systemStatus',
        ));
    }

    /**
     * Settings / Advanced technical view.
     */
    public function settings(Esp32StateStore $store): View
    {
        $latest = $store->latest();

        return view('power-strip.settings', compact('latest'));
    }

    // ─── helpers ──────────────────────────────────────────────────

    private function deriveSocketStatus(array $latest, int $index): string
    {
        if ($latest['updated_at'] === null) {
            return 'offline';
        }

        // If the data is older than 5 minutes, consider offline.
        $updatedAt = \Carbon\Carbon::parse($latest['updated_at']);
        if ($updatedAt->diffInMinutes(now()) > 5) {
            return 'offline';
        }

        $relay = (bool) ($latest["relay_{$index}"] ?? false);
        if (! $relay) {
            return 'off';
        }

        $power = (float) ($latest['power'] ?? 0);
        if ($power > 2500) {
            return 'overload';
        }
        if ($power > 1800) {
            return 'high_load';
        }

        return 'normal';
    }

    private function deriveSystemStatus(array $latest): string
    {
        if ($latest['updated_at'] === null) {
            return 'offline';
        }

        $updatedAt = \Carbon\Carbon::parse($latest['updated_at']);
        if ($updatedAt->diffInMinutes(now()) > 5) {
            return 'offline';
        }

        $power = (float) ($latest['power'] ?? 0);
        if ($power > 2500) {
            return 'warning';
        }

        return 'healthy';
    }
}
