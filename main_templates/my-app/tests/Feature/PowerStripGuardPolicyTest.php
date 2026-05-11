<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Esp32ConnectionHealth;
use App\Support\Esp32StateStore;
use App\Support\PowerStripCommandLogStore;
use App\Support\PowerStripGuardService;
use App\Support\PowerStripGuardStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PowerStripGuardPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_power_strip_page_embeds_the_saved_guard_policy(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $store = Mockery::mock(Esp32StateStore::class);
        $store->shouldReceive('latest')
            ->once()
            ->andReturn([
                'voltage' => 229.8,
                'current' => 2.1,
                'current_1' => 0.7,
                'current_2' => 0.8,
                'current_3' => 0.6,
                'power' => 483.0,
                'power_1' => 160.0,
                'power_2' => 185.0,
                'power_3' => 138.0,
                'energy' => 1.24,
                'relay_1' => true,
                'relay_2' => true,
                'relay_3' => false,
                'updated_at' => now()->toIso8601String(),
            ]);

        $guardStore = Mockery::mock(PowerStripGuardStore::class);
        $guardStore->shouldReceive('current')
            ->once()
            ->andReturn([
                'id' => 'guard-current',
                'enabled' => true,
                'status' => 'active',
                'status_label' => 'Active',
                'scope_mode' => 'common',
                'common_threshold_amps' => 8.5,
                'socket_threshold_amps_1' => 2.0,
                'socket_threshold_amps_2' => 2.0,
                'socket_threshold_amps_3' => 2.0,
                'action' => 'off-all',
                'start_date' => now()->toDateString(),
                'has_end_date' => true,
                'end_date' => now()->addDays(7)->toDateString(),
                'notes' => 'Kitchen circuit safety guard',
                'updated_at' => now()->toIso8601String(),
                'exists' => true,
            ]);
        $guardStore->shouldReceive('all')
            ->once()
            ->andReturn([
                [
                    'id' => 'guard-current',
                    'enabled' => true,
                    'status' => 'active',
                    'status_label' => 'Active',
                    'scope_mode' => 'common',
                    'common_threshold_amps' => 8.5,
                    'socket_threshold_amps_1' => 2.0,
                    'socket_threshold_amps_2' => 2.0,
                    'socket_threshold_amps_3' => 2.0,
                    'action' => 'off-all',
                    'start_date' => now()->toDateString(),
                    'has_end_date' => true,
                    'end_date' => now()->addDays(7)->toDateString(),
                    'notes' => 'Kitchen circuit safety guard',
                    'updated_at' => now()->toIso8601String(),
                    'exists' => true,
                ],
            ]);

        $guardService = Mockery::mock(PowerStripGuardService::class);
        $guardService->shouldReceive('preview')
            ->once()
            ->andReturn([
                'triggered' => false,
                'reason' => 'below_threshold',
                'scope' => 'common',
                'action' => 'off-all',
                'triggered_socket' => null,
                'threshold' => 8.5,
                'measured_value' => 2.1,
                'published_relays' => [],
                'message' => 'Current draw is below the configured threshold.',
            ]);

        $this->app->instance(Esp32StateStore::class, $store);
        $this->app->instance(PowerStripGuardStore::class, $guardStore);
        $this->app->instance(PowerStripGuardService::class, $guardService);
        $commandLogStore = Mockery::mock(PowerStripCommandLogStore::class);
        $commandLogStore->shouldReceive('latest')
            ->once()
            ->andReturn([]);
        $this->app->instance(PowerStripCommandLogStore::class, $commandLogStore);

        $response = $this->get(route('power-strip.index'));

        $response
            ->assertOk()
            ->assertSee('data-save-url=', false)
            ->assertSee('common_threshold_amps', false)
            ->assertSee('Kitchen circuit safety guard', false);
    }

    public function test_guard_policy_can_be_saved_to_mongo_store(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $guardStore = Mockery::mock(PowerStripGuardStore::class);
        $guardStore->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => $payload['scope_mode'] === 'per_socket'
                && $payload['action'] === 'off-2'
                && $payload['has_end_date'] === true))
            ->andReturn([
                'enabled' => true,
                'scope_mode' => 'per_socket',
                'common_threshold_amps' => 10.0,
                'socket_threshold_amps_1' => 1.2,
                'socket_threshold_amps_2' => 1.5,
                'socket_threshold_amps_3' => 1.8,
                'action' => 'off-2',
                'start_date' => now()->toDateString(),
                'has_end_date' => true,
                'end_date' => now()->addDays(3)->toDateString(),
                'notes' => 'garage',
                'updated_at' => now()->toIso8601String(),
                'exists' => true,
            ]);

        $this->app->instance(PowerStripGuardStore::class, $guardStore);

        $response = $this->postJson(route('power-strip.guard-policy.store'), [
            'enabled' => true,
            'scope_mode' => 'per_socket',
            'common_threshold_amps' => 10,
            'socket_threshold_amps_1' => 1.2,
            'socket_threshold_amps_2' => 1.5,
            'socket_threshold_amps_3' => 1.8,
            'action' => 'off-2',
            'start_date' => now()->toDateString(),
            'has_end_date' => true,
            'end_date' => now()->addDays(3)->toDateString(),
            'notes' => 'garage',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('policy.scope_mode', 'per_socket')
            ->assertJsonPath('policy.action', 'off-2');
    }

    public function test_guard_preview_uses_latest_telemetry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $store = Mockery::mock(Esp32StateStore::class);
        $store->shouldReceive('latest')
            ->once()
            ->andReturn([
                'current' => 4.2,
                'current_1' => 1.1,
                'current_2' => 1.4,
                'current_3' => 1.7,
                'relay_1' => true,
                'relay_2' => false,
                'relay_3' => true,
                'updated_at' => now()->toIso8601String(),
            ]);

        $guardService = Mockery::mock(PowerStripGuardService::class);
        $guardService->shouldReceive('currentPolicy')
            ->once()
            ->andReturn([
                'enabled' => true,
                'scope_mode' => 'common',
                'common_threshold_amps' => 4.0,
                'socket_threshold_amps_1' => 2.0,
                'socket_threshold_amps_2' => 2.0,
                'socket_threshold_amps_3' => 2.0,
                'action' => 'off-all',
                'start_date' => now()->subDay()->toDateString(),
                'has_end_date' => false,
                'end_date' => null,
                'last_triggered_at' => null,
            ]);
        $guardService->shouldReceive('evaluate')
            ->once()
            ->andReturn([
                'triggered' => true,
                'reason' => 'triggered',
                'scope' => 'common',
                'action' => 'off-all',
                'triggered_socket' => null,
                'threshold' => 4.0,
                'measured_value' => 4.2,
                'published_relays' => [1, 3],
                'message' => 'Guard triggered and issued shutdown commands.',
            ]);

        $this->app->instance(Esp32StateStore::class, $store);
        $this->app->instance(PowerStripGuardService::class, $guardService);

        $response = $this->postJson(route('power-strip.guard-policy.preview'));

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('preview.triggered', true)
            ->assertJsonPath('preview.published_relays.0', 1);
    }

    public function test_guard_policy_can_be_paused_and_deleted_from_the_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pausedStore = Mockery::mock(PowerStripGuardStore::class);
        $pausedStore->shouldReceive('pause')
            ->once()
            ->with('guard-1')
            ->andReturn([
                'id' => 'guard-1',
                'enabled' => false,
                'status' => 'paused',
                'status_label' => 'Paused',
                'scope_mode' => 'common',
                'common_threshold_amps' => 7.5,
                'socket_threshold_amps_1' => 2.0,
                'socket_threshold_amps_2' => 2.0,
                'socket_threshold_amps_3' => 2.0,
                'action' => 'off-all',
                'start_date' => now()->subDay()->toDateString(),
                'has_end_date' => false,
                'end_date' => null,
                'notes' => 'Garage',
                'updated_at' => now()->toIso8601String(),
                'exists' => true,
            ]);
        $pausedStore->shouldReceive('all')
            ->once()
            ->andReturn([
                [
                    'id' => 'guard-1',
                    'enabled' => false,
                    'status' => 'paused',
                    'status_label' => 'Paused',
                    'scope_mode' => 'common',
                    'common_threshold_amps' => 7.5,
                    'socket_threshold_amps_1' => 2.0,
                    'socket_threshold_amps_2' => 2.0,
                    'socket_threshold_amps_3' => 2.0,
                    'action' => 'off-all',
                    'start_date' => now()->subDay()->toDateString(),
                    'has_end_date' => false,
                    'end_date' => null,
                    'notes' => 'Garage',
                    'updated_at' => now()->toIso8601String(),
                    'exists' => true,
                ],
            ]);

        $this->app->instance(PowerStripGuardStore::class, $pausedStore);

        $pauseResponse = $this->postJson(route('power-strip.guard-policies.pause', ['policyId' => 'guard-1']));

        $pauseResponse
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('policy.status', 'paused');

        $deleteStore = Mockery::mock(PowerStripGuardStore::class);
        $deleteStore->shouldReceive('delete')
            ->once()
            ->with('guard-1')
            ->andReturn(true);
        $deleteStore->shouldReceive('all')
            ->once()
            ->andReturn([]);

        $this->app->instance(PowerStripGuardStore::class, $deleteStore);

        $deleteResponse = $this->deleteJson(route('power-strip.guard-policies.destroy', ['policyId' => 'guard-1']));

        $deleteResponse
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
