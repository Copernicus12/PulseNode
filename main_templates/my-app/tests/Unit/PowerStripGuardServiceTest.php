<?php

namespace Tests\Unit;

use App\Support\Esp32RelayPublisher;
use App\Support\NotificationCenter;
use App\Support\PowerStripGuardService;
use App\Support\PowerStripGuardStore;
use Mockery;
use Tests\TestCase;

class PowerStripGuardServiceTest extends TestCase
{
    public function test_common_scope_triggers_all_relays_when_total_current_exceeds_threshold(): void
    {
        $store = Mockery::mock(PowerStripGuardStore::class);
        $publisher = Mockery::mock(Esp32RelayPublisher::class);
        $notifications = Mockery::mock(NotificationCenter::class);

        $store->shouldReceive('runnable')
            ->once()
            ->andReturn([[
                'id' => 'policy-common',
                'enabled' => true,
                'scope_mode' => 'common',
                'common_threshold_amps' => 5.0,
                'socket_threshold_amps_1' => 2.0,
                'socket_threshold_amps_2' => 2.0,
                'socket_threshold_amps_3' => 2.0,
                'action' => 'off-all',
                'start_date' => now()->subDay()->toDateString(),
                'has_end_date' => false,
                'end_date' => null,
                'last_triggered_at' => null,
            ]]);

        $publisher->shouldReceive('publish')->once()->with(1, 'off');
        $publisher->shouldReceive('publish')->once()->with(2, 'off');
        $publisher->shouldReceive('publish')->once()->with(3, 'off');
        $notifications->shouldReceive('guardTriggered')
            ->once()
            ->with('common', 5.0, 6.1, 'off-all', [1, 2, 3], null);
        $store->shouldReceive('markTriggered')
            ->once()
            ->with(
                'policy-common',
                Mockery::on(fn (array $metadata): bool => $metadata['action'] === 'off-all' && $metadata['scope'] === 'common'),
            );

        $service = new PowerStripGuardService($store, $publisher, $notifications);
        $result = $service->enforce([
            'relay_1' => true,
            'relay_2' => true,
            'relay_3' => true,
            'current' => 6.1,
            'current_1' => 2.0,
            'current_2' => 2.1,
            'current_3' => 2.0,
        ]);

        $this->assertTrue($result['triggered']);
        $this->assertSame([1, 2, 3], $result['published_relays']);
    }

    public function test_per_socket_scope_triggers_only_the_matching_socket(): void
    {
        $store = Mockery::mock(PowerStripGuardStore::class);
        $publisher = Mockery::mock(Esp32RelayPublisher::class);
        $notifications = Mockery::mock(NotificationCenter::class);

        $store->shouldReceive('runnable')
            ->once()
            ->andReturn([[
                'id' => 'policy-socket',
                'enabled' => true,
                'scope_mode' => 'per_socket',
                'common_threshold_amps' => 10.0,
                'socket_threshold_amps_1' => 1.0,
                'socket_threshold_amps_2' => 2.0,
                'socket_threshold_amps_3' => 3.0,
                'action' => 'off-2',
                'start_date' => now()->subDay()->toDateString(),
                'has_end_date' => false,
                'end_date' => null,
                'last_triggered_at' => null,
            ]]);

        $publisher->shouldReceive('publish')->once()->with(2, 'off');
        $notifications->shouldReceive('guardTriggered')
            ->once()
            ->with('per_socket', 2.0, 2.2, 'off-2', [2], 2);
        $store->shouldReceive('markTriggered')
            ->once()
            ->with(
                'policy-socket',
                Mockery::on(fn (array $metadata): bool => $metadata['action'] === 'off-2' && $metadata['scope'] === 'per_socket'),
            );

        $service = new PowerStripGuardService($store, $publisher, $notifications);
        $result = $service->enforce([
            'relay_1' => true,
            'relay_2' => true,
            'relay_3' => true,
            'current' => 5.5,
            'current_1' => 0.8,
            'current_2' => 2.2,
            'current_3' => 1.1,
        ]);

        $this->assertTrue($result['triggered']);
        $this->assertSame([2], $result['published_relays']);
    }
}
