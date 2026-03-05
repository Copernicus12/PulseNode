<?php

namespace App\Http\Controllers;

use App\Models\EnergyReading;
use App\Support\Esp32StateStore;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

    public function history(Request $request, Esp32StateStore $store): View
    {
        $latest = $store->latest();

        $sockets = [
            [
                'index' => 1,
                'label' => 'Socket 1',
                'is_on' => (bool) ($latest['relay_1'] ?? false),
                'voltage' => round((float) ($latest['voltage'] ?? 0), 1),
                'current' => round((float) ($latest['current_1'] ?? 0), 3),
                'power_w' => round((float) ($latest['voltage'] ?? 0) * (float) ($latest['current_1'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status' => $this->deriveSocketStatus($latest, 1),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
            [
                'index' => 2,
                'label' => 'Socket 2',
                'is_on' => (bool) ($latest['relay_2'] ?? false),
                'voltage' => round((float) ($latest['voltage'] ?? 0), 1),
                'current' => round((float) ($latest['current_2'] ?? 0), 3),
                'power_w' => round((float) ($latest['voltage'] ?? 0) * (float) ($latest['current_2'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status' => $this->deriveSocketStatus($latest, 2),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
            [
                'index' => 3,
                'label' => 'Socket 3',
                'is_on' => (bool) ($latest['relay_3'] ?? false),
                'voltage' => round((float) ($latest['voltage'] ?? 0), 1),
                'current' => round((float) ($latest['current_3'] ?? 0), 3),
                'power_w' => round((float) ($latest['voltage'] ?? 0) * (float) ($latest['current_3'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status' => $this->deriveSocketStatus($latest, 3),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
        ];

        $activeSockets = collect($sockets)->where('is_on', true)->count();
        $totalPower = collect($sockets)->sum('power_w');
        $totalEnergy = collect($sockets)->sum('energy_kwh');
        $systemStatus = $this->deriveSystemStatus($latest);
        $updatedAt = $latest['updated_at'] ?? null;
        $lastSeen = $updatedAt ? Carbon::parse($updatedAt)->diffForHumans() : 'Never';
        $isOnline = $systemStatus !== 'offline';
        $history = EnergyReading::historyPayload();
        $week = collect($history['week'] ?? []);
        $selectedDate = (string) $request->string('date', (string) data_get($week->firstWhere('is_today', true), 'date', now()->toDateString()));

        try {
            $selectedDate = Carbon::parse($selectedDate)->toDateString();
        } catch (\Throwable) {
            $selectedDate = (string) data_get($week->firstWhere('is_today', true), 'date', now()->toDateString());
        }

        $selectedDay = EnergyReading::dayDetails($selectedDate);
        $weeklyTotal = round((float) $week->sum('total'), 4);
        $averageDay = round((float) $week->avg('total'), 4);
        $peakDay = $week->sortByDesc('total')->first();
        $activeHours = collect($selectedDay['hourly'] ?? [])->where('energy_kwh', '>', 0)->count();
        $topHour = collect($selectedDay['hourly'] ?? [])->sortByDesc('energy_kwh')->first();
        $totalWarnings = (int) (($selectedDay['warnings']['high'] ?? 0) + ($selectedDay['warnings']['overload'] ?? 0));
        $topSocket = collect($selectedDay['socket_stats'] ?? [])->sortByDesc('energy_kwh')->first();
        $researchIdeas = [
            [
                'title' => 'Standby baseline detection',
                'description' => 'Track the normal idle profile for each socket and flag unusual standby increases automatically.',
            ],
            [
                'title' => 'Anomaly and overload timeline',
                'description' => 'Store events separately and show spikes, overloads, and relay reactions as a searchable incident log.',
            ],
            [
                'title' => 'Usage forecasting',
                'description' => 'Estimate end-of-day and end-of-week energy from the current trend so the user can react earlier.',
            ],
            [
                'title' => 'Automation suggestions',
                'description' => 'Recommend schedules like turning off sockets with repeated idle leakage during known inactive hours.',
            ],
        ];

        return view('history.index', compact(
            'latest',
            'sockets',
            'activeSockets',
            'totalPower',
            'totalEnergy',
            'systemStatus',
            'lastSeen',
            'isOnline',
            'history',
            'week',
            'selectedDate',
            'selectedDay',
            'weeklyTotal',
            'averageDay',
            'peakDay',
            'activeHours',
            'topHour',
            'totalWarnings',
            'topSocket',
            'researchIdeas',
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
