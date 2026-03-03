<?php

namespace App\Support;

use App\Models\DeviceDetection;
use App\Models\EnergySample;
use Illuminate\Support\Collection;

class BatteryInsights
{
    public function build(): array
    {
        $samples = EnergySample::query()
            ->orderByDesc('sampled_at')
            ->limit(180)
            ->get()
            ->reverse()
            ->values();

        $socketInsights = collect([1, 2, 3])
            ->map(fn (int $socketIndex): array => $this->buildSocketInsight($socketIndex, $samples))
            ->values();

        $standbyWaste = round((float) $socketInsights->sum('standby_w'), 1);
        $avgPower = round((float) $socketInsights->sum('avg_power_w'), 1);
        $stability = (int) round($socketInsights->avg('stability_score') ?? 0);
        $efficiency = (int) round($socketInsights->avg('efficiency_score') ?? 0);
        $overall = (int) max(12, min(98, round(($efficiency * 0.55) + ($stability * 0.30) + (max(0, 100 - min(100, $standbyWaste * 3))) * 0.15)));

        return [
            'overall_score' => $overall,
            'efficiency_score' => $efficiency,
            'stability_score' => $stability,
            'standby_waste_w' => $standbyWaste,
            'live_draw_w' => $avgPower,
            'socket_insights' => $socketInsights,
            'priority_actions' => $this->priorityActions($socketInsights, $standbyWaste),
            'segments' => $this->segments($overall),
        ];
    }

    private function buildSocketInsight(int $socketIndex, Collection $samples): array
    {
        $powerColumn = 'power_socket_'.$socketIndex;
        $currentColumn = 'current_'.$socketIndex;
        $powerSeries = $samples->map(fn (EnergySample $sample): float => (float) $sample->{$powerColumn});
        $currentSeries = $samples->map(fn (EnergySample $sample): float => (float) $sample->{$currentColumn});

        $active = $samples->filter(fn (EnergySample $sample): bool => (float) $sample->{$currentColumn} > 0.03)->values();
        $standby = $samples->filter(function (EnergySample $sample) use ($powerColumn, $currentColumn): bool {
            $current = (float) $sample->{$currentColumn};
            $power = (float) $sample->{$powerColumn};

            return $current > 0.005 && $power > 0.8 && $power < 12;
        })->values();

        $avgPower = round((float) $powerSeries->avg(), 1);
        $peakPower = round((float) $powerSeries->max(), 1);
        $standbyAvg = round((float) ($standby->avg($powerColumn) ?? 0), 1);
        $standbyMinutes = round($standby->count() * $this->sampleMinutes(), 1);
        $activeMinutes = round($active->count() * $this->sampleMinutes(), 1);
        $variability = $avgPower > 0 ? round(($this->standardDeviation($powerSeries) / max(1, $avgPower)) * 100, 1) : 0.0;
        $stabilityScore = (int) max(20, min(99, round(100 - min(70, $variability * 1.15))));
        $efficiencyScore = (int) max(18, min(99, round(100 - min(62, $standbyAvg * 4.2) - min(18, max(0, $peakPower - max(15, $avgPower * 2)) * 0.08))));

        $latestDetection = DeviceDetection::query()
            ->where('socket_index', $socketIndex)
            ->latest('last_seen_at')
            ->first();

        return [
            'socket_index' => $socketIndex,
            'label' => $latestDetection?->predicted_label ?? 'Socket '.$socketIndex,
            'category' => $latestDetection?->predicted_category ?? 'Unlabeled',
            'avg_power_w' => $avgPower,
            'peak_power_w' => $peakPower,
            'standby_w' => $standbyAvg,
            'standby_minutes' => $standbyMinutes,
            'active_minutes' => $activeMinutes,
            'variability_pct' => $variability,
            'stability_score' => $stabilityScore,
            'efficiency_score' => $efficiencyScore,
            'status' => $this->socketStatus($standbyAvg, $variability, $avgPower),
        ];
    }

    private function priorityActions(Collection $socketInsights, float $standbyWaste): array
    {
        $actions = [];

        $worstStandby = $socketInsights->sortByDesc('standby_w')->first();
        if ($worstStandby && $worstStandby['standby_w'] >= 2.5) {
            $actions[] = [
                'title' => 'Cut standby drift on socket '.$worstStandby['socket_index'],
                'description' => $worstStandby['label'].' is leaking '.number_format($worstStandby['standby_w'], 1).'W while mostly idle.',
                'tone' => 'warning',
            ];
        }

        $unstable = $socketInsights->sortBy('stability_score')->first();
        if ($unstable && $unstable['stability_score'] <= 55) {
            $actions[] = [
                'title' => 'Review unstable load profile',
                'description' => $unstable['label'].' shows '.number_format($unstable['variability_pct'], 1).'% variability and may need a dedicated profile.',
                'tone' => 'info',
            ];
        }

        if ($standbyWaste <= 1.5) {
            $actions[] = [
                'title' => 'Standby waste is under control',
                'description' => 'The strip is currently wasting only '.number_format($standbyWaste, 1).'W in near-idle draw.',
                'tone' => 'good',
            ];
        }

        return array_slice($actions, 0, 3);
    }

    private function segments(int $overall): array
    {
        return [
            ['label' => 'Reserve', 'value' => $overall],
            ['label' => 'Guard', 'value' => max(0, min(100, $overall - 8))],
            ['label' => 'Waste', 'value' => max(0, min(100, 100 - abs(70 - $overall)))],
        ];
    }

    private function socketStatus(float $standbyAvg, float $variability, float $avgPower): string
    {
        if ($avgPower < 1.0) {
            return 'Sleeping';
        }

        if ($standbyAvg >= 2.5) {
            return 'Standby leak';
        }

        if ($variability >= 45) {
            return 'Volatile load';
        }

        return 'Efficient';
    }

    private function sampleMinutes(): float
    {
        return (float) config('esp32.analytics.sample_minutes', 0.1667);
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
