<?php

namespace Tests\Unit;

use App\Support\Esp32ConnectionHealth;
use App\Support\Esp32RelayPublisher;
use App\Support\Esp32StateStore;
use App\Support\NotificationCenter;
use App\Support\PowerStripCommandLogStore;
use App\Support\SocketScheduleRunner;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class SocketScheduleRunnerTest extends TestCase
{
    public function test_resolve_due_action_matches_start_time_on_the_correct_day(): void
    {
        $runner = new SocketScheduleRunner(
            Mockery::mock(Esp32StateStore::class),
            Mockery::mock(Esp32RelayPublisher::class),
            Mockery::mock(Esp32ConnectionHealth::class),
            Mockery::mock(NotificationCenter::class),
            Mockery::mock(PowerStripCommandLogStore::class),
        );

        $schedule = [
            'days_of_week' => ['mon', 'tue'],
            'start_time' => '22:15',
            'action' => 'off',
            'last_triggered_at' => null,
        ];

        $due = $runner->resolveDueAction($schedule, Carbon::create(2026, 5, 11, 22, 15, 0, config('app.timezone')));

        $this->assertSame([
            'event' => 'start_time',
            'state' => 'off',
        ], $due);
    }

    public function test_process_schedules_publishes_off_commands_and_marks_the_schedule(): void
    {
        $store = Mockery::mock(Esp32StateStore::class);
        $store->shouldReceive('updateRelayState')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => $payload['relay_2'] === false))
            ->andReturn([]);

        $publisher = Mockery::mock(Esp32RelayPublisher::class);
        $publisher->shouldReceive('publish')
            ->once()
            ->with(2, 'off')
            ->andReturn([
                'published' => true,
                'sent' => '{"relay":2,"state":"off"}',
                'message' => 'MQTT command published.',
            ]);

        $connectionHealth = Mockery::mock(Esp32ConnectionHealth::class);
        $connectionHealth->shouldNotReceive('relayCommandAvailability');

        $notifications = Mockery::mock(NotificationCenter::class);
        $notifications->shouldReceive('relayCommandSent')
            ->once()
            ->with(2, 'off');

        $commandLogStore = Mockery::mock(PowerStripCommandLogStore::class);
        $commandLogStore->shouldReceive('store')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => $payload['level'] === 'success'
                && $payload['source'] === 'schedule'
                && $payload['meta']['state'] === 'off'))
            ->andReturn([
                'level' => 'success',
                'message' => 'Schedule "Evening Off" switched socket 2 OFF.',
                'source' => 'schedule',
                'meta' => [
                    'schedule_name' => 'Evening Off',
                    'socket_index' => 2,
                    'state' => 'off',
                    'event' => 'start_time',
                ],
                'time' => now()->toIso8601String(),
            ]);

        $latest = [
            'voltage' => 229.7,
            'current' => 1.2,
            'current_1' => 0.6,
            'current_2' => 0.4,
            'current_3' => 0.2,
            'power' => 276.0,
            'power_1' => 138.0,
            'power_2' => 92.0,
            'power_3' => 46.0,
            'energy' => 1.8,
            'relay_1' => true,
            'relay_2' => true,
            'relay_3' => false,
            'updated_at' => now()->toIso8601String(),
        ];

        $runner = new SocketScheduleRunner(
            $store,
            $publisher,
            $connectionHealth,
            $notifications,
            $commandLogStore,
        );

        $schedule = new class
        {
            public string $name = 'Evening Off';
            public int $socket_index = 2;
            public string $action = 'off';
            public array $days_of_week = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            public string $start_time = '22:15';
            public ?string $last_triggered_at = null;
        };

        $result = $runner->processSchedules(
            [$schedule],
            $latest,
            Carbon::create(2026, 5, 11, 22, 15, 0, config('app.timezone')),
        );

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['triggered']);
        $this->assertSame('triggered', $result['results'][0]['status']);
    }

    public function test_process_schedules_reverses_the_action_at_end_time(): void
    {
        $store = Mockery::mock(Esp32StateStore::class);
        $store->shouldReceive('updateRelayState')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => $payload['relay_2'] === true))
            ->andReturn([]);

        $publisher = Mockery::mock(Esp32RelayPublisher::class);
        $publisher->shouldReceive('publish')
            ->once()
            ->with(2, 'on')
            ->andReturn([
                'published' => true,
                'sent' => '{"relay":2,"state":"on"}',
                'message' => 'MQTT command published.',
            ]);

        $connectionHealth = Mockery::mock(Esp32ConnectionHealth::class);
        $connectionHealth->shouldReceive('relayCommandAvailability')
            ->once()
            ->andReturn([
                'can_turn_on' => true,
                'reason' => null,
                'last_seen_at' => now()->toIso8601String(),
            ]);

        $notifications = Mockery::mock(NotificationCenter::class);
        $notifications->shouldReceive('relayCommandSent')
            ->once()
            ->with(2, 'on');

        $commandLogStore = Mockery::mock(PowerStripCommandLogStore::class);
        $commandLogStore->shouldReceive('store')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => $payload['level'] === 'success'
                && $payload['source'] === 'schedule'
                && $payload['meta']['state'] === 'on'
                && $payload['meta']['event'] === 'end_time'))
            ->andReturn([
                'level' => 'success',
                'message' => 'Schedule "Evening Window" switched socket 2 ON.',
                'source' => 'schedule',
                'meta' => [
                    'schedule_name' => 'Evening Window',
                    'socket_index' => 2,
                    'state' => 'on',
                    'event' => 'end_time',
                ],
                'time' => now()->toIso8601String(),
            ]);

        $latest = [
            'voltage' => 229.7,
            'current' => 1.2,
            'current_1' => 0.6,
            'current_2' => 0.4,
            'current_3' => 0.2,
            'power' => 276.0,
            'power_1' => 138.0,
            'power_2' => 92.0,
            'power_3' => 46.0,
            'energy' => 1.8,
            'relay_1' => false,
            'relay_2' => false,
            'relay_3' => false,
            'updated_at' => now()->toIso8601String(),
        ];

        $runner = new SocketScheduleRunner(
            $store,
            $publisher,
            $connectionHealth,
            $notifications,
            $commandLogStore,
        );

        $schedule = new class
        {
            public string $name = 'Evening Window';
            public int $socket_index = 2;
            public string $action = 'off';
            public array $days_of_week = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            public string $start_time = '20:00';
            public string $end_time = '22:00';
            public ?string $last_triggered_at = null;
        };

        $result = $runner->processSchedules(
            [$schedule],
            $latest,
            Carbon::create(2026, 5, 11, 22, 0, 0, config('app.timezone')),
        );

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['triggered']);
        $this->assertSame('triggered', $result['results'][0]['status']);
        $this->assertSame('end_time', $result['results'][0]['event']);
        $this->assertSame('on', $result['results'][0]['state']);
    }

    public function test_resolve_due_action_triggers_end_time_on_cross_midnight_windows(): void
    {
        $runner = new SocketScheduleRunner(
            Mockery::mock(Esp32StateStore::class),
            Mockery::mock(Esp32RelayPublisher::class),
            Mockery::mock(Esp32ConnectionHealth::class),
            Mockery::mock(NotificationCenter::class),
            Mockery::mock(PowerStripCommandLogStore::class),
        );

        $schedule = [
            'days_of_week' => ['mon', 'tue'],
            'start_time' => '22:00',
            'end_time' => '02:00',
            'action' => 'on',
            'last_triggered_at' => null,
        ];

        $due = $runner->resolveDueAction($schedule, Carbon::create(2026, 5, 12, 2, 0, 0, config('app.timezone')));

        $this->assertSame([
            'event' => 'end_time',
            'state' => 'off',
        ], $due);
    }
}
