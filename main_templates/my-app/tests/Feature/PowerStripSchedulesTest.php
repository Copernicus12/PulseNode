<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Esp32ConnectionHealth;
use App\Support\Esp32StateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PowerStripSchedulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedules_page_displays_socket_power_in_watts(): void
    {
        $user = User::factory()->create();

        $stateStore = Mockery::mock(Esp32StateStore::class);
        $stateStore->shouldReceive('latest')
            ->once()
            ->andReturn([
                'voltage' => 230.1,
                'current' => 3.1,
                'current_1' => 0.42,
                'current_2' => 0.83,
                'current_3' => 1.85,
                'power' => 715.2,
                'power_1' => 12.34,
                'power_2' => 45.67,
                'power_3' => 89.01,
                'energy' => 2.5,
                'relay_1' => true,
                'relay_2' => false,
                'relay_3' => true,
                'updated_at' => now()->toIso8601String(),
            ]);

        $connectionHealth = Mockery::mock(Esp32ConnectionHealth::class);
        $connectionHealth->shouldReceive('isOnline')
            ->andReturn(true);

        $this->app->instance(Esp32StateStore::class, $stateStore);
        $this->app->instance(Esp32ConnectionHealth::class, $connectionHealth);

        $this->actingAs($user)
            ->get(route('devices.schedules.index'))
            ->assertOk()
            ->assertSee('12.3 W', false)
            ->assertSee('45.7 W', false)
            ->assertSee('89.0 W', false);
    }
}
