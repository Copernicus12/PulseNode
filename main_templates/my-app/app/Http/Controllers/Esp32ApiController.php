<?php

namespace App\Http\Controllers;

use App\Models\BillingTariffProfile;
use App\Models\DetectionPlan;
use App\Models\DeviceProfile;
use App\Models\EnergyReading;
use App\Support\EnergyBillingCalculator;
use App\Support\Esp32ConnectionHealth;
use App\Support\DeviceProfiler;
use App\Support\Esp32RelayPublisher;
use App\Support\Esp32StateStore;
use App\Support\NotificationCenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class Esp32ApiController extends Controller
{
    public function latest(Esp32StateStore $store): JsonResponse
    {
        return response()->json($store->latest());
    }

    public function relay(int $relayId, string $state, Esp32StateStore $store, Esp32RelayPublisher $publisher, Esp32ConnectionHealth $connectionHealth, NotificationCenter $notifications): JsonResponse
    {
        if (! in_array($state, ['on', 'off'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'State must be on or off.',
            ], 422);
        }

        if (! in_array($relayId, [1, 2, 3], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Relay must be 1, 2, or 3.',
            ], 422);
        }

        $latestState = $store->latest();
        $relayCommandGuard = $connectionHealth->relayCommandAvailability($latestState);

        if ($state === 'on' && ! $relayCommandGuard['can_turn_on']) {
            $notifications->relayCommandBlocked($relayId, $relayCommandGuard);

            return response()->json([
                'status' => 'unavailable',
                'sent' => strtoupper($state),
                'published' => false,
                'message' => $relayCommandGuard['message'],
                'guard' => $relayCommandGuard,
            ], 409);
        }

        try {
            $publishResult = $publisher->publish($relayId, $state);
        } catch (RuntimeException $exception) {
            $notifications->relayCommandFailed($relayId, $state, $exception->getMessage());

            return response()->json([
                'status' => 'error',
                'sent' => strtoupper($state),
                'published' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        $latest = $store->updateRelayState([
            'relay_1' => $relayId === 1 ? $state === 'on' : (bool) ($latestState['relay_1'] ?? false),
            'relay_2' => $relayId === 2 ? $state === 'on' : (bool) ($latestState['relay_2'] ?? false),
            'relay_3' => $relayId === 3 ? $state === 'on' : (bool) ($latestState['relay_3'] ?? false),
        ]);
        $notifications->relayCommandSent($relayId, $state);

        return response()->json([
            'status' => 'ok',
            ...$publishResult,
            'relay' => $latest["relay_{$relayId}"],
            'latest' => $latest,
        ]);
    }

    public function ingest(Request $request, Esp32StateStore $store, Esp32ConnectionHealth $connectionHealth, NotificationCenter $notifications): JsonResponse
    {
        $configuredToken = (string) config('esp32.ingest.token', '');
        if ($configuredToken !== '' && $request->header('X-ESP32-TOKEN') !== $configuredToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid ingest token.',
            ], 403);
        }

        $payload = $request->validate([
            'voltage' => ['sometimes', 'numeric'],
            'current' => ['sometimes', 'numeric'],
            'current_1' => ['sometimes', 'numeric'],
            'current_2' => ['sometimes', 'numeric'],
            'current_3' => ['sometimes', 'numeric'],
            'power' => ['sometimes', 'numeric'],
            'power_1' => ['sometimes', 'numeric'],
            'power_2' => ['sometimes', 'numeric'],
            'power_3' => ['sometimes', 'numeric'],
            'energy' => ['sometimes', 'numeric'],
            'relay_1' => ['sometimes', 'boolean'],
            'relay_2' => ['sometimes', 'boolean'],
            'relay_3' => ['sometimes', 'boolean'],
        ]);

        $previous = $store->latest();
        $latest = $store->updateTelemetry($payload);
        $notifications->recordTelemetryUpdate($previous, $latest, $connectionHealth);

        return response()->json([
            'status' => 'ok',
            'latest' => $latest,
        ]);
    }

    public function energyHistory(Request $request, EnergyBillingCalculator $billingCalculator): JsonResponse
    {
        $payload = EnergyReading::historyPayload();
        $user = $request->user();

        if ($user !== null) {
            try {
                $billingProfiles = BillingTariffProfile::query()
                    ->where('owner_key', (string) $user->getAuthIdentifier())
                    ->get();
            } catch (Throwable) {
                $billingProfiles = collect();
            }

            $payload['billingSummary'] = $billingCalculator->forDay(
                $user,
                EnergyReading::dayDetails(now()->toDateString()),
                $billingProfiles,
            );
        }

        return response()->json($payload);
    }

    public function energyDay(string $date): JsonResponse
    {
        try {
            $parsed = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid date format. Use YYYY-MM-DD.',
            ], 422);
        }

        return response()->json(EnergyReading::dayDetails($parsed));
    }

    public function liveDetections(DeviceProfiler $profiler): JsonResponse
    {
        $profiles = DeviceProfile::query()->latest('last_trained_at')->get();
        $plans = DetectionPlan::query()
            ->where('is_active', true)
            ->latest('updated_at')
            ->get()
            ->sortByDesc(fn (DetectionPlan $plan): int => $plan->socket_scope === null ? 0 : 1)
            ->values();

        $detections = collect([1, 2, 3])->map(function (int $socketIndex) use ($profiler, $profiles, $plans): array {
            $plan = $profiler->resolvePlanForSocket($socketIndex, $plans);
            $detection = $profiler->detectSocket($socketIndex, $profiles, $plan);

            return [
                'socket_index' => $socketIndex,
                'state' => (string) ($detection['state'] ?? 'idle'),
                'confidence' => (int) ($detection['confidence'] ?? 0),
                'label' => (string) ($detection['label'] ?? 'Unknown'),
                'category' => (string) ($detection['category'] ?? 'Unknown'),
                'reason' => (string) ($detection['reason'] ?? ''),
                'required_samples' => (int) ($detection['required_samples'] ?? 3),
            ];
        })->values();

        return response()->json([
            'status' => 'ok',
            'detections' => $detections,
        ]);
    }

    public function restartMqttListener(NotificationCenter $notifications): JsonResponse
    {
        if (app()->environment('production')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Restart is disabled in production.',
            ], 403);
        }

        $command = sprintf(
            'pkill -f "artisan mqtt:listen" >/dev/null 2>&1; nohup php %s mqtt:listen >/tmp/pulsenode-mqtt-listener.log 2>&1 &',
            escapeshellarg(base_path('artisan'))
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(5);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: 'Failed to restart MQTT listener.';
            $notifications->mqttRestartFailed($message);

            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], 500);
        }

        $notifications->mqttRestarted();

        return response()->json([
            'status' => 'ok',
            'message' => 'MQTT listener restart command sent.',
            'log' => '/tmp/pulsenode-mqtt-listener.log',
        ]);
    }
}
