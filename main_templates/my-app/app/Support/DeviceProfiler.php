<?php

namespace App\Support;

use App\Models\DetectionPlan;
use App\Models\DeviceDetection;
use App\Models\DeviceProfile;
use App\Models\EnergySample;
use Illuminate\Support\Collection;

class DeviceProfiler
{
    public function sampleMinutes(): float
    {
        return (float) config('esp32.analytics.sample_minutes', 0.1667);
    }

    public function buildSocketSignature(int $socketIndex, int $windowSamples = 90, int $minActiveSamples = 3): ?array
    {
        $currentColumn = 'current_'.$socketIndex;
        $powerColumn = 'power_socket_'.$socketIndex;

        $recent = EnergySample::query()
            ->orderByDesc('sampled_at')
            ->limit($windowSamples)
            ->get()
            ->reverse()
            ->values();

        if ($recent->isEmpty()) {
            return null;
        }

        $active = $recent->filter(fn (EnergySample $sample): bool => (float) $sample->{$currentColumn} > 0.03)->values();

        if ($active->count() < $minActiveSamples) {
            return null;
        }

        $powers = $active->map(fn (EnergySample $sample): float => (float) $sample->{$powerColumn});
        $currents = $active->map(fn (EnergySample $sample): float => (float) $sample->{$currentColumn});

        $avgPower = round((float) $powers->avg(), 2);
        $peakPower = round((float) $powers->max(), 2);
        $avgCurrent = round((float) $currents->avg(), 4);
        $minPower = round((float) $powers->min(), 2);
        $powerStd = round($this->standardDeviation($powers), 2);
        $variability = $avgPower > 0 ? round(($powerStd / max($avgPower, 1)) * 100, 1) : 0.0;
        $startupRatio = round($peakPower / max($avgPower, 1), 2);
        $durationMinutes = round($active->count() * $this->sampleMinutes(), 1);
        $energyEstimate = round((float) $active->sum('delta_energy'), 5);
        $lastSeenAt = optional($active->last()->sampled_at)?->toIso8601String();
        $observedFrom = optional($active->first()->sampled_at)?->toIso8601String();

        return [
            'socket_index' => $socketIndex,
            'sample_count' => $active->count(),
            'duration_minutes' => $durationMinutes,
            'avg_power_w' => $avgPower,
            'peak_power_w' => $peakPower,
            'min_power_w' => $minPower,
            'avg_current_a' => $avgCurrent,
            'power_std_w' => $powerStd,
            'variability_pct' => $variability,
            'startup_ratio' => $startupRatio,
            'energy_estimate_kwh' => $energyEstimate,
            'observed_from' => $observedFrom,
            'last_seen_at' => $lastSeenAt,
        ];
    }

    public function detectSocket(int $socketIndex, Collection $profiles, ?DetectionPlan $plan = null): array
    {
        $resolvedPlan = $plan ?? $this->resolvePlanForSocket($socketIndex);
        $windowSamples = $resolvedPlan?->window_samples ?? $this->windowSamplesForStrategy($resolvedPlan?->strategy ?? 'balanced');
        $minSamples = max(2, (int) ($resolvedPlan?->min_samples ?? 3));
        $matchThreshold = (int) ($resolvedPlan?->match_threshold ?? 68);

        $signature = $this->buildSocketSignature($socketIndex, (int) $windowSamples, $minSamples);

        if ($signature === null) {
            return [
                'socket_index' => $socketIndex,
                'state' => 'idle',
                'confidence' => 0,
                'label' => 'No active device',
                'category' => 'Idle socket',
                'reason' => 'Not enough recent activity for this detection plan.',
                'signature' => null,
                'profile' => null,
                'plan' => $resolvedPlan,
                'required_samples' => $minSamples,
            ];
        }

        $bestProfile = null;
        $bestConfidence = 0;

        foreach ($profiles as $profile) {
            $confidence = $this->confidenceForProfile($signature, $profile);
            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestProfile = $profile;
            }
        }

        if ($bestProfile !== null && $bestConfidence >= $matchThreshold) {
            return [
                'socket_index' => $socketIndex,
                'state' => 'matched',
                'confidence' => $bestConfidence,
                'label' => $bestProfile->name,
                'category' => $bestProfile->category,
                'reason' => 'Matched against a saved power signature profile.',
                'signature' => $signature,
                'profile' => $bestProfile,
                'plan' => $resolvedPlan,
                'required_samples' => $minSamples,
            ];
        }

        [$label, $category, $reason] = $this->heuristicGuess($signature);

        return [
            'socket_index' => $socketIndex,
            'state' => 'unknown',
            'confidence' => min(67, max(42, $bestConfidence ?: 52)),
            'label' => $label,
            'category' => $category,
            'reason' => $reason,
            'signature' => $signature,
            'profile' => null,
            'plan' => $resolvedPlan,
            'required_samples' => $minSamples,
        ];
    }

    public function syncDetections(array $detections): void
    {
        foreach ($detections as $detection) {
            $socketIndex = (int) $detection['socket_index'];

            if (($detection['state'] ?? 'idle') === 'idle' || empty($detection['signature'])) {
                DeviceDetection::query()
                    ->where('socket_index', $socketIndex)
                    ->whereNull('released_at')
                    ->update([
                        'released_at' => now(),
                        'status' => 'released',
                    ]);

                continue;
            }

            $open = DeviceDetection::query()
                ->where('socket_index', $socketIndex)
                ->whereNull('released_at')
                ->latest('last_seen_at')
                ->first();

            $payload = [
                'device_profile_id' => $detection['profile']?->id,
                'detection_plan_id' => $detection['plan']?->id,
                'predicted_label' => $detection['label'],
                'predicted_category' => $detection['category'],
                'confidence' => (int) $detection['confidence'],
                'signature_snapshot' => $detection['signature'],
                'last_seen_at' => now(),
                'status' => $detection['state'],
            ];

            if ($open && $open->predicted_label === $detection['label'] && abs($open->confidence - (int) $detection['confidence']) <= 8) {
                $open->update($payload);

                continue;
            }

            if ($open) {
                $open->update([
                    'released_at' => now(),
                    'status' => 'released',
                ]);
            }

            DeviceDetection::query()->create([
                'socket_index' => $socketIndex,
                'detected_at' => now(),
                ...$payload,
            ]);
        }
    }

    public function profilePayloadFromSignature(array $signature, array $attributes): array
    {
        return [
            'name' => $attributes['name'],
            'category' => $attributes['category'],
            'notes' => $attributes['notes'] ?? null,
            'expected_power_min' => max(0, round($signature['avg_power_w'] * 0.7, 2)),
            'expected_power_max' => round(max($signature['peak_power_w'], $signature['avg_power_w'] * 1.3), 2),
            'avg_power_w' => $signature['avg_power_w'],
            'peak_power_w' => $signature['peak_power_w'],
            'avg_current_a' => $signature['avg_current_a'],
            'variability_pct' => $signature['variability_pct'],
            'startup_ratio' => $signature['startup_ratio'],
            'signature_snapshot' => $signature,
            'trained_from_socket' => $signature['socket_index'],
            'last_trained_at' => now(),
        ];
    }

    private function confidenceForProfile(array $signature, DeviceProfile $profile): int
    {
        $avg = $this->normalizedDiff($signature['avg_power_w'], (float) $profile->avg_power_w);
        $peak = $this->normalizedDiff($signature['peak_power_w'], (float) $profile->peak_power_w);
        $current = $this->normalizedDiff($signature['avg_current_a'], (float) $profile->avg_current_a, 0.15);
        $variance = $this->normalizedDiff($signature['variability_pct'], (float) $profile->variability_pct, 10);
        $startup = $this->normalizedDiff($signature['startup_ratio'], (float) $profile->startup_ratio, 0.5);

        $score = 100
            - ($avg * 34)
            - ($peak * 24)
            - ($current * 20)
            - ($variance * 12)
            - ($startup * 10);

        if ($signature['avg_power_w'] < (float) $profile->expected_power_min || $signature['avg_power_w'] > (float) $profile->expected_power_max) {
            $score -= 8;
        }

        return (int) max(0, min(99, round($score)));
    }

    public function resolvePlanForSocket(int $socketIndex, ?Collection $plans = null): ?DetectionPlan
    {
        $planSet = ($plans ?? DetectionPlan::query()->where('is_active', true)->get())
            ->where('is_active', true)
            ->values();

        $socketPlan = $planSet->first(function (DetectionPlan $plan) use ($socketIndex): bool {
            return (int) $plan->socket_scope === $socketIndex;
        });

        if ($socketPlan !== null) {
            return $socketPlan;
        }

        return $planSet->first(function (DetectionPlan $plan): bool {
            return $plan->socket_scope === null;
        });
    }

    private function windowSamplesForStrategy(string $strategy): int
    {
        return match ($strategy) {
            'fast' => 45,
            'strict' => 140,
            default => 90,
        };
    }

    private function heuristicGuess(array $signature): array
    {
        $avg = $signature['avg_power_w'];
        $variability = $signature['variability_pct'];
        $startup = $signature['startup_ratio'];

        if ($avg < 15) {
            return ['Low-power charger', 'Accessory', 'Small stable load; typical of chargers, adapters, or standby electronics.'];
        }

        if ($avg < 90 && $variability < 25) {
            return ['Display or monitor', 'Display', 'Mid-low steady draw with limited variation, typical of screens or network gear.'];
        }

        if ($avg < 200 && $startup > 1.25) {
            return ['Laptop or workstation charger', 'Computer', 'Moderate draw with a noticeable startup spike, common for laptop power bricks.'];
        }

        if ($avg < 900) {
            return ['Active appliance', 'Appliance', 'Sustained medium draw suggests a desktop peripheral or household appliance.'];
        }

        return ['High-draw appliance', 'Appliance', 'Large sustained power profile; likely a resistive or heavy-load device.'];
    }

    private function normalizedDiff(float $left, float $right, float $floor = 1.0): float
    {
        return min(1.4, abs($left - $right) / max($floor, $right, $left, 1));
    }

    private function standardDeviation(Collection $values): float
    {
        $count = $values->count();
        if ($count <= 1) {
            return 0.0;
        }

        $mean = (float) $values->avg();
        $variance = $values
            ->map(fn (float $value): float => ($value - $mean) ** 2)
            ->sum() / $count;

        return sqrt($variance);
    }
}
