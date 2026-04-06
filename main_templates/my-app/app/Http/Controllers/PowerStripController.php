<?php

namespace App\Http\Controllers;

use App\Http\Requests\Devices\StoreDetectionPlanRequest;
use App\Http\Requests\Devices\StoreDeviceProfileRequest;
use App\Models\BillingTariffProfile;
use App\Models\DetectionPlan;
use App\Models\DeviceDetection;
use App\Models\DeviceProfile;
use App\Models\EnergyReading;
use App\Support\BatteryInsights;
use App\Support\DeviceProfiler;
use App\Support\EnergyBillingCalculator;
use App\Support\Esp32ConnectionHealth;
use App\Support\Esp32StateStore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PowerStripController extends Controller
{
    /**
     * Main power-strip monitoring dashboard.
     */
    public function index(Esp32StateStore $store, Esp32ConnectionHealth $connectionHealth): View
    {
        $latest = $store->latest();
        [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus] = $this->buildStripViewModel($latest, $connectionHealth);
        $relayCommandGuard = $connectionHealth->relayCommandAvailability($latest);

        return view('power-strip.index', compact(
            'latest',
            'sockets',
            'activeSockets',
            'totalPower',
            'totalEnergy',
            'systemStatus',
            'relayCommandGuard',
        ));
    }

    public function devices(Esp32StateStore $store, DeviceProfiler $profiler, Esp32ConnectionHealth $connectionHealth): View
    {
        return view('devices.index', $this->buildDevicesPageData($store, $profiler, $connectionHealth, 'overview', 6));
    }

    public function deviceProfiles(Esp32StateStore $store, DeviceProfiler $profiler, Esp32ConnectionHealth $connectionHealth): View
    {
        return view('devices.index', $this->buildDevicesPageData($store, $profiler, $connectionHealth, 'profiles', 6));
    }

    public function devicePlans(Esp32StateStore $store, DeviceProfiler $profiler, Esp32ConnectionHealth $connectionHealth): View
    {
        return view('devices.index', $this->buildDevicesPageData($store, $profiler, $connectionHealth, 'plans', 6));
    }

    public function deviceActivity(Esp32StateStore $store, DeviceProfiler $profiler, Esp32ConnectionHealth $connectionHealth): View
    {
        return view('devices.index', $this->buildDevicesPageData($store, $profiler, $connectionHealth, 'activity', 20));
    }

    public function storeDeviceProfile(StoreDeviceProfileRequest $request, DeviceProfiler $profiler): RedirectResponse
    {
        $data = $request->validated();
        $redirectRoute = $this->devicesRedirectRoute($request, 'devices.index');

        $signature = $profiler->buildSocketSignature((int) $data['socket_index']);

        if ($signature === null) {
            return redirect()
                ->route($redirectRoute)
                ->with('devices_error', 'Socket '.$data['socket_index'].' does not have enough recent activity to train a profile yet.');
        }

        DeviceProfile::query()->create($profiler->profilePayloadFromSignature($signature, $data));

        return redirect()
            ->route($redirectRoute)
            ->with('devices_success', 'Profile "'.$data['name'].'" trained from socket '.$data['socket_index'].'.');
    }

    public function destroyDeviceProfile(Request $request, DeviceProfile $profile): RedirectResponse
    {
        $redirectRoute = $this->devicesRedirectRoute($request, 'devices.index');
        $name = $profile->name;
        $profile->delete();

        return redirect()
            ->route($redirectRoute)
            ->with('devices_success', 'Profile "'.$name.'" deleted.');
    }

    public function storeDetectionPlan(StoreDetectionPlanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $redirectRoute = $this->devicesRedirectRoute($request, 'devices.index');

        $isActive = (bool) ($data['is_active'] ?? false);
        $scope = $data['socket_scope'] ?? null;

        if ($isActive) {
            $this->deactivatePlansForScope($scope);
        }

        $plan = DetectionPlan::query()->create([
            'name' => $data['name'],
            'strategy' => $data['strategy'],
            'socket_scope' => $scope,
            'window_samples' => (int) $data['window_samples'],
            'min_samples' => (int) $data['min_samples'],
            'match_threshold' => (int) $data['match_threshold'],
            'is_active' => $isActive,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route($redirectRoute)
            ->with('devices_success', 'Detection plan "'.$plan->name.'" created.');
    }

    public function activateDetectionPlan(Request $request, DetectionPlan $plan): RedirectResponse
    {
        $redirectRoute = $this->devicesRedirectRoute($request, 'devices.index');
        $this->deactivatePlansForScope($plan->socket_scope);

        $plan->update(['is_active' => true]);

        return redirect()
            ->route($redirectRoute)
            ->with('devices_success', 'Detection plan "'.$plan->name.'" activated.');
    }

    public function destroyDetectionPlan(Request $request, DetectionPlan $plan): RedirectResponse
    {
        $redirectRoute = $this->devicesRedirectRoute($request, 'devices.index');
        $name = $plan->name;
        $plan->delete();

        return redirect()
            ->route($redirectRoute)
            ->with('devices_success', 'Detection plan "'.$name.'" removed.');
    }

    public function battery(Esp32StateStore $store, BatteryInsights $insights, Esp32ConnectionHealth $connectionHealth): View
    {
        [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus] = $this->buildStripViewModel($store->latest(), $connectionHealth);

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

    public function history(
        Request $request,
        Esp32StateStore $store,
        Esp32ConnectionHealth $connectionHealth,
        EnergyBillingCalculator $billingCalculator,
    ): View|JsonResponse
    {
        [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus] = $this->buildStripViewModel($store->latest(), $connectionHealth);
        /** @var \App\Models\User $user */
        $user = $request->user();

        $updatedAt = $latest['updated_at'] ?? null;
        $lastSeen = $updatedAt ? Carbon::parse($updatedAt)->diffForHumans() : 'Never';
        $isOnline = $systemStatus !== 'offline';
        $today = now()->startOfDay();
        $oldestReadingDate = EnergyReading::oldestDate();
        $oldestDate = $oldestReadingDate ? Carbon::parse($oldestReadingDate)->startOfDay() : $today->copy();
        $retentionStart = $today->copy()->subYears(5);
        $minSelectableDate = $oldestDate->lt($retentionStart) ? $oldestDate->copy() : $retentionStart;
        $anchorDate = $today->copy();
        $anchorDateInput = (string) $request->string('anchor_date', $today->toDateString());

        try {
            $anchorDate = Carbon::parse($anchorDateInput)->startOfDay();
        } catch (\Throwable) {
            $anchorDate = $today->copy();
        }

        if ($anchorDate->isAfter($today)) {
            $anchorDate = $today->copy();
        }

        if ($anchorDate->isBefore($minSelectableDate)) {
            $anchorDate = $minSelectableDate->copy();
        }

        $windowEnd = $anchorDate->copy();
        $windowStart = $windowEnd->copy()->subDays(6);

        $dayWindow = collect(range(0, 6))->map(function (int $offset) use ($windowStart, $today): array {
            $date = $windowStart->copy()->addDays($offset);
            $dateKey = $date->toDateString();
            $details = EnergyReading::dayDetails($dateKey);

            return [
                'date' => $dateKey,
                'day_short' => strtoupper(substr($date->format('D'), 0, 3)),
                'is_today' => $date->isSameDay($today),
                'total' => round((float) ($details['total_kwh'] ?? 0), 4),
            ];
        });

        $selectedDate = (string) $request->string('date', $windowEnd->toDateString());

        try {
            $selectedDate = Carbon::parse($selectedDate)->toDateString();
        } catch (\Throwable) {
            $selectedDate = $windowEnd->toDateString();
        }

        if (Carbon::parse($selectedDate)->isAfter($today) || Carbon::parse($selectedDate)->isBefore($windowStart) || Carbon::parse($selectedDate)->isAfter($windowEnd)) {
            $selectedDate = $windowEnd->toDateString();
        }

        $selectedDay = EnergyReading::dayDetails($selectedDate);
        $billingProfiles = BillingTariffProfile::query()
            ->where('owner_key', (string) $user->getAuthIdentifier())
            ->get();
        $billingSummary = $billingCalculator->forDay($user, $selectedDay, $billingProfiles);
        $weeklyTotal = round((float) $dayWindow->sum('total'), 4);
        $averageDay = round((float) $dayWindow->avg('total'), 4);
        $peakDay = $dayWindow->sortByDesc('total')->first();
        $activeHours = collect($selectedDay['hourly'] ?? [])->where('energy_kwh', '>', 0)->count();
        $topHour = collect($selectedDay['hourly'] ?? [])->sortByDesc('energy_kwh')->first();
        $totalWarnings = (int) (($selectedDay['warnings']['high'] ?? 0) + ($selectedDay['warnings']['overload'] ?? 0));
        $topSocket = collect($selectedDay['socket_stats'] ?? [])->sortByDesc('energy_kwh')->first();
        $daySelector = [
            'anchor_date' => $windowEnd->toDateString(),
            'min_date' => $minSelectableDate->toDateString(),
            'max_date' => $today->toDateString(),
            'window_start' => $windowStart->toDateString(),
            'window_end' => $windowEnd->toDateString(),
        ];

        $historyPayload = compact(
            'latest',
            'lastSeen',
            'isOnline',
            'dayWindow',
            'daySelector',
            'selectedDate',
            'selectedDay',
            'billingSummary',
            'weeklyTotal',
            'averageDay',
            'peakDay',
            'activeHours',
            'topHour',
            'totalWarnings',
            'topSocket',
        );

        $historyPayload['historyBaseUrl'] = route('history.index');

        if ($request->expectsJson()) {
            return response()->json($historyPayload);
        }

        return view('history.index', [
            'historyProps' => $historyPayload,
        ]);
    }

    private function buildStripViewModel(array $latest, Esp32ConnectionHealth $connectionHealth): array
    {
        $sockets = [
            [
                'index' => 1,
                'label' => 'Socket 1',
                'is_on' => (bool) ($latest['relay_1'] ?? false),
                'voltage' => round((float) ($latest['voltage'] ?? 0), 1),
                'current' => round((float) ($latest['current_1'] ?? 0), 3),
                'power_w' => round((float) ($latest['power_1'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status' => $this->deriveSocketStatus($latest, 1, $connectionHealth),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
            [
                'index' => 2,
                'label' => 'Socket 2',
                'is_on' => (bool) ($latest['relay_2'] ?? false),
                'voltage' => round((float) ($latest['voltage'] ?? 0), 1),
                'current' => round((float) ($latest['current_2'] ?? 0), 3),
                'power_w' => round((float) ($latest['power_2'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status' => $this->deriveSocketStatus($latest, 2, $connectionHealth),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
            [
                'index' => 3,
                'label' => 'Socket 3',
                'is_on' => (bool) ($latest['relay_3'] ?? false),
                'voltage' => round((float) ($latest['voltage'] ?? 0), 1),
                'current' => round((float) ($latest['current_3'] ?? 0), 3),
                'power_w' => round((float) ($latest['power_3'] ?? 0), 1),
                'energy_kwh' => round((float) ($latest['energy'] ?? 0), 3),
                'status' => $this->deriveSocketStatus($latest, 3, $connectionHealth),
                'updated_at' => $latest['updated_at'] ?? null,
            ],
        ];

        $activeSockets = collect($sockets)->where('is_on', true)->count();
        $totalPower = collect($sockets)->sum('power_w');
        $totalEnergy = collect($sockets)->sum('energy_kwh');
        $systemStatus = $this->deriveSystemStatus($latest, $connectionHealth);

        return [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus];
    }

    private function buildDevicesPageData(Esp32StateStore $store, DeviceProfiler $profiler, Esp32ConnectionHealth $connectionHealth, string $section, int $recentDetectionsLimit): array
    {
        return [
            ...$this->buildDevicesViewModel($store, $profiler, $connectionHealth),
            'deviceSection' => $section,
            'deviceSectionMeta' => $this->deviceSectionMeta($section),
            'recentDetections' => DeviceDetection::query()
                ->with(['profile', 'plan'])
                ->latest('detected_at')
                ->limit($recentDetectionsLimit)
                ->get(),
        ];
    }

    private function buildDevicesViewModel(Esp32StateStore $store, DeviceProfiler $profiler, Esp32ConnectionHealth $connectionHealth): array
    {
        [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus] = $this->buildStripViewModel($store->latest(), $connectionHealth);

        $updatedAt = $latest['updated_at'] ?? null;
        $lastSeen = $updatedAt ? Carbon::parse($updatedAt)->diffForHumans() : 'Never';
        $profiles = DeviceProfile::query()->latest('last_trained_at')->get();
        $detectionPlans = DetectionPlan::query()
            ->orderByDesc('is_active')
            ->orderByRaw('CASE WHEN socket_scope IS NULL THEN 0 ELSE 1 END DESC')
            ->latest('updated_at')
            ->get();

        $planBySocket = collect([1, 2, 3])->mapWithKeys(function (int $socketIndex) use ($profiler, $detectionPlans): array {
            return [$socketIndex => $profiler->resolvePlanForSocket($socketIndex, $detectionPlans)];
        });

        $detections = collect([1, 2, 3])
            ->map(fn (int $socketIndex): array => $profiler->detectSocket($socketIndex, $profiles, $planBySocket->get($socketIndex)))
            ->keyBy('socket_index');

        $profiler->syncDetections($detections->values()->all());

        $socketCards = collect($sockets)->map(function (array $socket) use ($detections): array {
            return [
                ...$socket,
                'detection' => $detections->get($socket['index']),
            ];
        })->values();

        $profileCategories = ['Computer', 'Display', 'Accessory', 'Appliance', 'Network', 'Lighting', 'Custom'];
        $recordedEvents = (int) DeviceDetection::query()->count();
        $detectionStats = [
            'trained_profiles' => $profiles->count(),
            'matched_now' => $detections->where('state', 'matched')->count(),
            'unknown_now' => $detections->where('state', 'unknown')->count(),
            'active_signatures' => $detections->filter(fn (array $detection): bool => ! empty($detection['signature']))->count(),
            'active_plans' => $detectionPlans->where('is_active', true)->count(),
            'recorded_events' => $recordedEvents,
        ];
        $profileBreakdown = $profiles
            ->groupBy(fn (DeviceProfile $profile): string => $profile->category ?: 'Custom')
            ->map(fn ($group): int => $group->count())
            ->sortDesc();
        $deviceSections = [
            [
                'key' => 'overview',
                'title' => 'Overview',
                'description' => 'Live status and training',
                'href' => route('devices.index'),
                'badge' => $detectionStats['active_signatures'].' live',
            ],
            [
                'key' => 'profiles',
                'title' => 'Profiles',
                'description' => 'Saved signatures',
                'href' => route('devices.profiles.index'),
                'badge' => $detectionStats['trained_profiles'].' saved',
            ],
            [
                'key' => 'plans',
                'title' => 'Plans',
                'description' => 'Matching rules',
                'href' => route('devices.plans.index'),
                'badge' => $detectionStats['active_plans'].' active',
            ],
            [
                'key' => 'activity',
                'title' => 'Activity',
                'description' => 'Detection timeline',
                'href' => route('devices.activity.index'),
                'badge' => $recordedEvents.' logged',
            ],
        ];

        return compact(
            'latest',
            'sockets',
            'socketCards',
            'activeSockets',
            'totalPower',
            'totalEnergy',
            'systemStatus',
            'lastSeen',
            'profiles',
            'profileCategories',
            'profileBreakdown',
            'detectionStats',
            'detectionPlans',
            'deviceSections',
        );
    }

    private function deviceSectionMeta(string $section): array
    {
        return match ($section) {
            'profiles' => [
                'label' => 'Profiles',
                'description' => 'Saved fingerprints, recent training, and cleanup actions.',
            ],
            'plans' => [
                'label' => 'Plans',
                'description' => 'Detection strategies, scope assignment, and active matching rules.',
            ],
            'activity' => [
                'label' => 'Activity',
                'description' => 'Recent recognitions, confidence levels, and current socket status.',
            ],
            default => [
                'label' => 'Overview',
                'description' => 'Live signatures, current matches, and quick profile training.',
            ],
        };
    }

    private function devicesRedirectRoute(Request $request, string $fallback): string
    {
        return match ((string) $request->string('redirect_route')) {
            'devices.index',
            'devices.profiles.index',
            'devices.plans.index',
            'devices.activity.index' => (string) $request->string('redirect_route'),
            default => $fallback,
        };
    }

    private function deriveSocketStatus(array $latest, int $index, Esp32ConnectionHealth $connectionHealth): string
    {
        if (! $connectionHealth->isOnline($latest)) {
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

    private function deriveSystemStatus(array $latest, Esp32ConnectionHealth $connectionHealth): string
    {
        if (! $connectionHealth->isOnline($latest)) {
            return 'offline';
        }

        $power = (float) ($latest['power'] ?? 0);
        if ($power > 2500) {
            return 'warning';
        }

        return 'healthy';
    }

    private function deactivatePlansForScope(?int $scope): void
    {
        DetectionPlan::query()
            ->where(function (Builder $query) use ($scope): void {
                if ($scope === null) {
                    $query->whereNull('socket_scope');
                } else {
                    $query->where('socket_scope', $scope);
                }
            })
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
