<?php

namespace Tests\Unit;

use App\Support\DeviceProfiler;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeviceProfilerTest extends TestCase
{
    #[Test]
    public function it_keeps_only_the_dominant_socket_for_each_matched_profile(): void
    {
        $profiler = new DeviceProfiler();
        $profile = (object) ['id' => 11, 'name' => 'Xbox 360'];
        $otherProfile = (object) ['id' => 22, 'name' => 'Laptop Charger'];

        $detections = new Collection([
            [
                'socket_index' => 1,
                'state' => 'matched',
                'confidence' => 99,
                'label' => 'Matched - Xbox 360',
                'category' => 'Computer',
                'reason' => 'Matched profile "Xbox 360" under plan "Default".',
                'signature' => ['avg_power_w' => 56.1, 'peak_power_w' => 58.0, 'avg_current_a' => 0.581, 'variability_pct' => 2.1, 'startup_ratio' => 1.04],
                'profile' => $profile,
                'plan' => null,
                'required_samples' => 3,
                'matched_profile_name' => 'Xbox 360',
                'matched_plan_name' => 'Default',
            ],
            [
                'socket_index' => 2,
                'state' => 'matched',
                'confidence' => 92,
                'label' => 'Matched - Laptop Charger',
                'category' => 'Computer',
                'reason' => 'Matched profile "Laptop Charger" under plan "Default".',
                'signature' => ['avg_power_w' => 64.2, 'peak_power_w' => 69.0, 'avg_current_a' => 0.433, 'variability_pct' => 3.1, 'startup_ratio' => 1.08],
                'profile' => $otherProfile,
                'plan' => null,
                'required_samples' => 3,
                'matched_profile_name' => 'Laptop Charger',
                'matched_plan_name' => 'Default',
            ],
            [
                'socket_index' => 3,
                'state' => 'matched',
                'confidence' => 86,
                'label' => 'Matched - Xbox 360',
                'category' => 'Computer',
                'reason' => 'Matched profile "Xbox 360" under plan "Default".',
                'signature' => ['avg_power_w' => 43.3, 'peak_power_w' => 45.0, 'avg_current_a' => 0.506, 'variability_pct' => 2.1, 'startup_ratio' => 1.02],
                'profile' => $profile,
                'plan' => null,
                'required_samples' => 3,
                'matched_profile_name' => 'Xbox 360',
                'matched_plan_name' => 'Default',
            ],
        ]);

        $normalized = $profiler->normalizeDominantMatches($detections);

        $this->assertSame('matched', $normalized[0]['state']);
        $this->assertSame('matched', $normalized[1]['state']);
        $this->assertSame('unknown', $normalized[2]['state']);
        $this->assertSame('Matched - Xbox 360', $normalized[0]['label']);
        $this->assertSame('Matched - Laptop Charger', $normalized[1]['label']);
        $this->assertNotSame('Matched - Xbox 360', $normalized[2]['label']);
    }

    #[Test]
    public function it_keeps_tied_dominant_matches_for_the_same_profile(): void
    {
        $profiler = new DeviceProfiler();
        $profile = (object) ['id' => 11, 'name' => 'Xbox 360'];

        $detections = new Collection([
            [
                'socket_index' => 1,
                'state' => 'matched',
                'confidence' => 91,
                'label' => 'Matched - Xbox 360',
                'category' => 'Computer',
                'reason' => 'Matched profile "Xbox 360" under plan "Default".',
                'signature' => ['avg_power_w' => 56.1, 'peak_power_w' => 58.0, 'avg_current_a' => 0.581, 'variability_pct' => 2.1, 'startup_ratio' => 1.04],
                'profile' => $profile,
                'plan' => null,
                'required_samples' => 3,
                'matched_profile_name' => 'Xbox 360',
                'matched_plan_name' => 'Default',
            ],
            [
                'socket_index' => 3,
                'state' => 'matched',
                'confidence' => 91,
                'label' => 'Matched - Xbox 360',
                'category' => 'Computer',
                'reason' => 'Matched profile "Xbox 360" under plan "Default".',
                'signature' => ['avg_power_w' => 56.1, 'peak_power_w' => 58.0, 'avg_current_a' => 0.581, 'variability_pct' => 2.1, 'startup_ratio' => 1.04],
                'profile' => $profile,
                'plan' => null,
                'required_samples' => 3,
                'matched_profile_name' => 'Xbox 360',
                'matched_plan_name' => 'Default',
            ],
        ]);

        $normalized = $profiler->normalizeDominantMatches($detections);

        $this->assertSame('matched', $normalized[0]['state']);
        $this->assertSame('matched', $normalized[1]['state']);
        $this->assertSame('Matched - Xbox 360', $normalized[0]['label']);
        $this->assertSame('Matched - Xbox 360', $normalized[1]['label']);
    }
}
