<?php

namespace Tests\Unit;

use App\Models\EnergyReading;
use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use ReflectionMethod;
use Tests\TestCase;

class EnergyReadingTimezoneTest extends TestCase
{
    public function test_hourly_breakdown_uses_bucharest_time_for_labels(): void
    {
        $samples = collect([
            (object) [
                'sampled_at' => new UTCDateTime(Carbon::create(2026, 1, 15, 0, 30, 0, 'UTC')->toDateTime()),
                'energy_kwh' => 0.12,
                'avg_power_w' => 220.0,
                'peak_power_w' => 250.0,
                'power' => 220.0,
                'delta_energy' => 0.12,
            ],
        ]);

        $hourly = $this->invokePrivateStaticMethod(EnergyReading::class, 'buildHourlyBreakdown', [$samples]);

        $this->assertSame('02:00', $hourly[2]['hour']);
        $this->assertSame(0.12, $hourly[2]['energy_kwh']);
        $this->assertSame('02:30', $hourly[2]['minutes'][30]['minute']);
        $this->assertSame('02:30:00', $hourly[2]['minutes'][30]['seconds'][0]['second']);
    }

    public function test_active_intervals_are_formatted_in_bucharest_time(): void
    {
        $samples = collect([
            (object) [
                'sampled_at' => new UTCDateTime(Carbon::create(2026, 1, 15, 0, 45, 0, 'UTC')->toDateTime()),
                'power' => 180.0,
                'delta_energy' => 0.08,
            ],
            (object) [
                'sampled_at' => new UTCDateTime(Carbon::create(2026, 1, 15, 0, 50, 0, 'UTC')->toDateTime()),
                'power' => 190.0,
                'delta_energy' => 0.09,
            ],
        ]);

        $intervals = $this->invokePrivateStaticMethod(EnergyReading::class, 'buildIntervals', [$samples, 5 / 60]);

        $this->assertSame('02:45', $intervals[0]['start']);
        $this->assertSame('02:50', $intervals[0]['end']);
    }

    /**
     * @template TReturn
     *
     * @param array<int, mixed> $arguments
     * @return TReturn
     */
    private function invokePrivateStaticMethod(string $class, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }
}
