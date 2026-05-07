<?php

namespace App\Http\Controllers;

use App\Models\BillingTariffProfile;
use App\Models\EnergyReading;
use App\Support\EnergyBillingCalculator;
use App\Support\Esp32ConnectionHealth;
use App\Support\Esp32StateStore;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    private const CURRENT_DISPLAY_THRESHOLD_A = 0.05;

    public function __invoke(
        Request $request,
        Esp32StateStore $store,
        Esp32ConnectionHealth $connectionHealth,
        EnergyBillingCalculator $billingCalculator,
    ): View
    {
        $latest = $store->latest();
        $user = $request->user();

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
        $power    = max(0.0, round((float) $latest['power'], 1));
        $energy   = round((float) $latest['energy'], 4);

        // Per-socket currents
        $current1 = $this->displayCurrent((float) ($latest['current_1'] ?? 0));
        $current2 = $this->displayCurrent((float) ($latest['current_2'] ?? 0));
        $current3 = $this->displayCurrent((float) ($latest['current_3'] ?? 0));
        $current = round($current1 + $current2 + $current3, 3);
        $power1 = max(0.0, round((float) ($latest['power_1'] ?? 0), 1));
        $power2 = max(0.0, round((float) ($latest['power_2'] ?? 0), 1));
        $power3 = max(0.0, round((float) ($latest['power_3'] ?? 0), 1));

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
        $todayDetails = EnergyReading::dayDetails(now()->toDateString());
        try {
            $billingProfiles = BillingTariffProfile::query()
                ->where('owner_key', (string) $user?->getAuthIdentifier())
                ->get();
        } catch (Throwable) {
            $billingProfiles = collect();
        }

        $dashboardBilling = $billingCalculator->forDay($user, $todayDetails, $billingProfiles);

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
            'dashboardBilling',
        ));
    }

    public function completeTour(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->noContent();
        }

        $user->forceFill([
            'dashboard_tour_completed_at' => now(),
        ])->save();

        return response()->noContent();
    }

    private function displayCurrent(float $current): float
    {
        if (abs($current) < self::CURRENT_DISPLAY_THRESHOLD_A) {
            return 0.0;
        }

        return round($current, 3);
    }
}
