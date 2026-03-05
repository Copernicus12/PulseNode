<?php

namespace Tests\Feature;

use App\Models\EnergyReading;
use App\Models\EnergySample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnergyIngestAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_from_latest_updates_existing_daily_reading_without_duplicate_insert_errors()
    {
        EnergyReading::query()->create([
            'date' => now()->startOfDay(),
            'energy_socket_1' => 0.002,
            'energy_socket_2' => 0.001,
            'energy_socket_3' => 0.0005,
            'energy_total' => 0.0035,
        ]);

        EnergySample::recordFromLatest([
            'voltage' => 230.0,
            'current' => 1.2,
            'current_1' => 0.6,
            'current_2' => 0.4,
            'current_3' => 0.2,
            'power' => 276.0,
            'energy' => 1.50000,
            'relay_1' => true,
            'relay_2' => true,
            'relay_3' => true,
        ]);

        EnergySample::recordFromLatest([
            'voltage' => 229.8,
            'current' => 1.35,
            'current_1' => 0.7,
            'current_2' => 0.45,
            'current_3' => 0.2,
            'power' => 310.0,
            'energy' => 1.50025,
            'relay_1' => true,
            'relay_2' => true,
            'relay_3' => true,
        ]);

        $this->assertEquals(1, EnergyReading::query()->whereDate('date', now()->toDateString())->count());
        $this->assertEquals(2, EnergySample::query()->count());
    }
}
