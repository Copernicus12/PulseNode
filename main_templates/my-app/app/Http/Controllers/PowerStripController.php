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
        $lastSeen = $this->telemetryAgeLabel($updatedAt);
        $isOnline = $systemStatus !== 'offline';
        $today = now()->startOfDay();
        $oldestReadingDate = EnergyReading::oldestDate();
        $oldestDate = $oldestReadingDate ? Carbon::parse($oldestReadingDate)->startOfDay() : $today->copy();
        $retentionStart = $today->copy()->subYears(5);
        $minSelectableDate = $oldestDate->lt($retentionStart) ? $oldestDate->copy() : $retentionStart;
        $selectedDateInput = (string) $request->string(
            'date',
            (string) $request->string('anchor_date', $today->toDateString()),
        );

        try {
            $selectedDate = Carbon::parse($selectedDateInput)->startOfDay();
        } catch (\Throwable) {
            $selectedDate = $today->copy();
        }

        if ($selectedDate->isAfter($today)) {
            $selectedDate = $today->copy();
        }

        if ($selectedDate->isBefore($minSelectableDate)) {
            $selectedDate = $minSelectableDate->copy();
        }

        $windowStart = $selectedDate->copy()->subDays(3);
        $windowEnd = $selectedDate->copy()->addDays(3);

        $dayWindow = collect(range(0, 6))->map(function (int $offset) use ($windowStart, $today, $minSelectableDate): array {
            $date = $windowStart->copy()->addDays($offset);
            $dateKey = $date->toDateString();
            $details = EnergyReading::dayDetails($dateKey);
            $isSelectable = $date->greaterThanOrEqualTo($minSelectableDate) && $date->lessThanOrEqualTo($today);

            return [
                'date' => $dateKey,
                'day_short' => strtoupper(substr($date->format('D'), 0, 3)),
                'is_today' => $date->isSameDay($today),
                'is_future' => $date->isAfter($today),
                'is_selectable' => $isSelectable,
                'total' => round((float) ($details['total_kwh'] ?? 0), 4),
            ];
        });

        $selectedDate = $selectedDate->toDateString();

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
            'anchor_date' => $selectedDate,
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

    private function telemetryAgeLabel(?string $updatedAt): string
    {
        if (! $updatedAt) {
            return 'Never';
        }

        try {
            $diffSeconds = max(0, Carbon::parse($updatedAt)->diffInSeconds(now()));
        } catch (\Throwable) {
            return 'Unknown';
        }

        if ($diffSeconds < 5) {
            return 'just now';
        }

        if ($diffSeconds < 60) {
            return floor($diffSeconds).' sec ago';
        }

        if ($diffSeconds < 3600) {
            return floor($diffSeconds / 60).' min ago';
        }

        if ($diffSeconds < 86400) {
            return floor($diffSeconds / 3600).' h ago';
        }

        if ($diffSeconds < 604800) {
            return floor($diffSeconds / 86400).' d ago';
        }

        if ($diffSeconds < 2629800) {
            $weeks = (int) floor($diffSeconds / 604800);

            return $weeks.' week'.($weeks === 1 ? '' : 's').' ago';
        }

        if ($diffSeconds < 31557600) {
            $months = (int) floor($diffSeconds / 2629800);

            return $months.' month'.($months === 1 ? '' : 's').' ago';
        }

        $years = (int) floor($diffSeconds / 31557600);

        return $years.' year'.($years === 1 ? '' : 's').' ago';
    }

    private function buildDevicesPageData(Esp32StateStore $store, DeviceProfiler $profiler, Esp32ConnectionHealth $connectionHealth, string $section, int $recentDetectionsLimit): array
    {
        $recentDetections = collect();

        try {
            $recentDetections = DeviceDetection::query()
                ->with(['profile', 'plan'])
                ->latest('detected_at')
                ->limit($recentDetectionsLimit)
                ->get();
        } catch (\Throwable) {
            $recentDetections = collect();
        }

        return [
            ...$this->buildDevicesViewModel($store, $profiler, $connectionHealth),
            'deviceSection' => $section,
            'deviceSectionMeta' => $this->deviceSectionMeta($section),
            'recentDetections' => $recentDetections,
        ];
    }

    private function buildDevicesViewModel(Esp32StateStore $store, DeviceProfiler $profiler, Esp32ConnectionHealth $connectionHealth): array
    {
        [$latest, $sockets, $activeSockets, $totalPower, $totalEnergy, $systemStatus] = $this->buildStripViewModel($store->latest(), $connectionHealth);

        $updatedAt = $latest['updated_at'] ?? null;
        $lastSeen = $this->telemetryAgeLabel($updatedAt);
        $profiles = collect();
        $detectionPlans = collect();

        try {
            $profiles = DeviceProfile::query()->latest('last_trained_at')->get();
        } catch (\Throwable) {
            $profiles = collect();
        }

        try {
            $detectionPlans = DetectionPlan::query()
                ->orderByDesc('is_active')
                ->latest('updated_at')
                ->get()
                ->sortByDesc(fn (DetectionPlan $plan): int => $plan->socket_scope === null ? 0 : 1)
                ->values();
        } catch (\Throwable) {
            $detectionPlans = collect();
        }

        $planBySocket = collect([1, 2, 3])->mapWithKeys(function (int $socketIndex) use ($profiler, $detectionPlans): array {
            return [$socketIndex => $profiler->resolvePlanForSocket($socketIndex, $detectionPlans)];
        });

        $detections = collect([1, 2, 3])
            ->map(fn (int $socketIndex): array => $profiler->detectSocket($socketIndex, $profiles, $planBySocket->get($socketIndex)))
            ->keyBy('socket_index');

        try {
            $profiler->syncDetections($detections->values()->all());
        } catch (\Throwable) {
            // Keep page rendering even when detection persistence is unavailable.
        }

        $socketCards = collect($sockets)->map(function (array $socket) use ($detections): array {
            return [
                ...$socket,
                'detection' => $detections->get($socket['index']),
            ];
        })->values();

        $profileCategories = ['Computer', 'Display', 'Accessory', 'Appliance', 'Network', 'Lighting', 'Custom'];
        $recordedEvents = 0;

        try {
            $recordedEvents = (int) DeviceDetection::query()->count();
        } catch (\Throwable) {
            $recordedEvents = 0;
        }
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
