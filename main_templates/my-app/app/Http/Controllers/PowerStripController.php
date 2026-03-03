<?php

namespace App\Http\Controllers;

use App\Models\DeviceDetection;
use App\Models\DeviceProfile;
use App\Support\BatteryInsights;
use App\Support\DeviceProfiler;
use App\Support\Esp32StateStore;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PowerStripController extends Controller
{
    /**
     * Main power-strip monitoring dashboard.
     */
    public function index(Esp32StateStore $store): View
    {
        [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus] = $this->buildStripViewModel($store->latest());

        return view('power-strip.index', compact(
            'latest',
            'sockets',
            'activeSockets',
            'totalPower',
            'totalEnergy',
            'systemStatus',
        ));
    }

    public function devices(Esp32StateStore $store, DeviceProfiler $profiler): View
    {
        [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus] = $this->buildStripViewModel($store->latest());

        $updatedAt = $latest['updated_at'] ?? null;
        $lastSeen = $updatedAt ? Carbon::parse($updatedAt)->diffForHumans() : 'Never';
        $isOnline = $systemStatus !== 'offline';
        $voltage = round((float) ($latest['voltage'] ?? 0), 1);
        $totalCurrent = round((float) ($latest['current'] ?? 0), 3);
        $profiles = DeviceProfile::query()->latest('last_trained_at')->get();

        $detections = collect([1, 2, 3])
            ->map(fn (int $socketIndex): array => $profiler->detectSocket($socketIndex, $profiles))
            ->keyBy('socket_index');

        $profiler->syncDetections($detections->values()->all());

        $socketCards = collect($sockets)->map(function (array $socket) use ($detections): array {
            $detection = $detections->get($socket['index']);

            return [
                ...$socket,
                'detection' => $detection,
            ];
        })->values();

        $recentDetections = DeviceDetection::query()
            ->with('profile')
            ->latest('detected_at')
            ->limit(8)
            ->get();

        $profileCategories = ['Computer', 'Display', 'Accessory', 'Appliance', 'Network', 'Lighting', 'Custom'];
        $detectionStats = [
            'trained_profiles' => $profiles->count(),
            'matched_now' => $detections->where('state', 'matched')->count(),
            'unknown_now' => $detections->where('state', 'unknown')->count(),
            'active_signatures' => $detections->filter(fn (array $detection): bool => ! empty($detection['signature']))->count(),
        ];

        return view('devices.index', compact(
            'latest',
            'sockets',
            'socketCards',
            'activeSockets',
            'totalPower',
            'totalEnergy',
            'systemStatus',
            'lastSeen',
            'isOnline',
            'voltage',
            'totalCurrent',
            'profiles',
            'recentDetections',
            'profileCategories',
            'detectionStats',
        ));
    }

    public function storeDeviceProfile(Request $request, DeviceProfiler $profiler): RedirectResponse
    {
        $data = $request->validate([
            'socket_index' => ['required', 'integer', 'in:1,2,3'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'category' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $signature = $profiler->buildSocketSignature((int) $data['socket_index']);

        if ($signature === null) {
            return redirect()
                ->route('devices.index')
                ->with('devices_error', 'Socket '.$data['socket_index'].' does not have enough recent activity to train a profile yet.');
        }

        DeviceProfile::query()->create($profiler->profilePayloadFromSignature($signature, $data));

        return redirect()
            ->route('devices.index')
            ->with('devices_success', 'Profile "'.$data['name'].'" trained from socket '.$data['socket_index'].'.');
    }

    public function battery(Esp32StateStore $store, BatteryInsights $insights): View
    {
        [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus] = $this->buildStripViewModel($store->latest());

        $updatedAt = $latest['updated_at'] ?? null;
        $lastSeen = $updatedAt ? Carbon::parse($updatedAt)->diffForHumans() : 'Never';
        $isOnline = $systemStatus !== 'offline';
        $battery = $insights->build();

        return view('battery.index', compact(
            'latest',
            'sockets',
            'activeSockets',
            'totalPower',
            'totalEnergy',
            'systemStatus',
            'lastSeen',
            'isOnline',
            'battery',
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

    private function buildStripViewModel(array $latest): array
    {
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

        return [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus];
    }

    private function deriveSocketStatus(array $latest, int $index): string
    {
        if ($latest['updated_at'] === null) {
            return 'offline';
        }

        $updatedAt = Carbon::parse($latest['updated_at']);
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

        $updatedAt = Carbon::parse($latest['updated_at']);
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
