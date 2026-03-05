<?php

namespace App\Http\Controllers;

use App\Models\DetectionPlan;
use App\Models\DeviceProfile;
use App\Models\EnergyReading;
use App\Models\EnergySample;
use App\Support\DeviceProfiler;
use App\Support\Esp32RelayPublisher;
use App\Support\Esp32StateStore;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\Process\Process;

class Esp32ApiController extends Controller
{
    public function latest(Esp32StateStore $store): JsonResponse
    {
        return response()->json($store->latest());
    }

    public function relay(int $relayId, string $state, Esp32StateStore $store, Esp32RelayPublisher $publisher): JsonResponse
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

        try {
            $publishResult = $publisher->publish($relayId, $state);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'sent' => strtoupper($state),
                'published' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        $latest = $store->update([
            'relay_1' => $relayId === 1 ? $state === 'on' : $store->latest()['relay_1'],
            'relay_2' => $relayId === 2 ? $state === 'on' : $store->latest()['relay_2'],
            'relay_3' => $relayId === 3 ? $state === 'on' : $store->latest()['relay_3'],
        ]);

        return response()->json([
            'status' => 'ok',
            ...$publishResult,
            'relay' => $latest["relay_{$relayId}"],
            'latest' => $latest,
        ]);
    }

    public function ingest(Request $request, Esp32StateStore $store): JsonResponse
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
            'energy' => ['sometimes', 'numeric'],
            'relay_1' => ['sometimes', 'boolean'],
            'relay_2' => ['sometimes', 'boolean'],
            'relay_3' => ['sometimes', 'boolean'],
        ]);

        $latest = $store->update($payload);

        // Record detailed analytics sample + per-day aggregates.
        EnergySample::recordFromLatest($latest);

        return response()->json([
            'status' => 'ok',
            'latest' => $latest,
        ]);
    }

    public function energyHistory(): JsonResponse
    {
        return response()->json(EnergyReading::historyPayload());
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
            ->orderByRaw('CASE WHEN socket_scope IS NULL THEN 0 ELSE 1 END DESC')
            ->latest('updated_at')
            ->get();

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

    public function restartMqttListener(): JsonResponse
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
            return response()->json([
                'status' => 'error',
                'message' => trim($process->getErrorOutput()) ?: 'Failed to restart MQTT listener.',
            ], 500);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'MQTT listener restart command sent.',
            'log' => '/tmp/pulsenode-mqtt-listener.log',
        ]);
    }
}
