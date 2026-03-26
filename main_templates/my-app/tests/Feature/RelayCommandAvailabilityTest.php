<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Esp32RelayPublisher;
use App\Support\Esp32StateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RelayCommandAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_relay_power_on_is_blocked_when_latest_telemetry_is_stale(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $store = Mockery::mock(Esp32StateStore::class);
        $store->shouldReceive('latest')
            ->once()
            ->andReturn([
                'voltage' => 229.8,
                'current' => 0.0,
                'current_1' => 0.0,
                'current_2' => 0.0,
                'current_3' => 0.0,
                'power' => 0.0,
                'energy' => 1.24,
                'relay_1' => false,
                'relay_2' => false,
                'relay_3' => false,
                'updated_at' => now()->subMinutes(3)->toIso8601String(),
            ]);

        $publisher = Mockery::mock(Esp32RelayPublisher::class);
        $publisher->shouldNotReceive('publish');

        $this->app->instance(Esp32StateStore::class, $store);
        $this->app->instance(Esp32RelayPublisher::class, $publisher);

        $response = $this->get(route('api.relay', ['relayId' => 1, 'state' => 'on']));

        $response->assertStatus(409);
        $response->assertJsonPath('status', 'unavailable');
        $response->assertJsonPath('guard.can_turn_on', false);
        $response->assertJsonPath('guard.reason', 'stale');
    }

    public function test_dashboard_and_power_strip_render_the_relay_command_guard_with_the_toast_bootstrap(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['dashboard', 'power-strip.index'] as $routeName) {
            $response = $this->get(route($routeName));

            $response->assertOk();
            $response->assertSee('window.pulsenodeEnsureRelayCommandAllowed', false);
            $response->assertSee('window.pulsenodeShowRelayCommandNotification', false);
            $response->assertSee('id="relay-command-toast-root"', false);
            $response->assertDontSee('id="relay-command-alert-root"', false);
        }
    }

    public function test_relay_command_updates_state_without_refreshing_last_telemetry_timestamp(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $staleTelemetryAt = now()->subMinutes(4)->toIso8601String();

        $store = Mockery::mock(Esp32StateStore::class);
        $store->shouldReceive('latest')
            ->once()
            ->andReturn([
                'voltage' => 229.8,
                'current' => 0.18,
                'current_1' => 0.18,
                'current_2' => 0.0,
                'current_3' => 0.0,
                'power' => 41.4,
                'energy' => 1.24,
                'relay_1' => true,
                'relay_2' => false,
                'relay_3' => false,
                'updated_at' => $staleTelemetryAt,
            ]);
        $store->shouldReceive('updateRelayState')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['relay_1'] === false
                    && $payload['relay_2'] === false
                    && $payload['relay_3'] === false;
            }))
            ->andReturn([
                'voltage' => 229.8,
                'current' => 0.18,
                'current_1' => 0.18,
                'current_2' => 0.0,
                'current_3' => 0.0,
                'power' => 41.4,
                'energy' => 1.24,
                'relay_1' => false,
                'relay_2' => false,
                'relay_3' => false,
                'updated_at' => $staleTelemetryAt,
            ]);

        $publisher = Mockery::mock(Esp32RelayPublisher::class);
        $publisher->shouldReceive('publish')
            ->once()
            ->with(1, 'off')
            ->andReturn([
                'sent' => '{"relay":1,"state":"off"}',
                'published' => true,
                'message' => 'MQTT command published.',
            ]);

        $this->app->instance(Esp32StateStore::class, $store);
        $this->app->instance(Esp32RelayPublisher::class, $publisher);

        $response = $this->get(route('api.relay', ['relayId' => 1, 'state' => 'off']));

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('latest.relay_1', false);
        $response->assertJsonPath('latest.updated_at', $staleTelemetryAt);
    }
}
