<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppNotificationStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class NotificationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_page_renders_with_pagination_controls(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $store = Mockery::mock(AppNotificationStore::class);
        $store->shouldReceive('paginate')
            ->once()
            ->with(10, 1, ['level' => null, 'type' => null], 'newest')
            ->andReturn(new LengthAwarePaginator([
                (object) [
                    'id' => 'mongo-1',
                    'type' => 'power_high',
                    'level' => 'warning',
                    'title' => 'High load detected',
                    'message' => 'Consumption is above the warning threshold.',
                    'action_url' => null,
                    'created_at' => Carbon::parse('2026-03-26 12:00:00'),
                ],
            ], 1, 10, 1));
        $store->shouldReceive('summary')
            ->once()
            ->andReturn([
                'total' => 1,
                'errors' => 0,
                'warnings' => 1,
                'latest' => (object) [
                    'title' => 'High load detected',
                    'level' => 'warning',
                    'created_at' => Carbon::parse('2026-03-26 12:00:00'),
                ],
            ]);
        $store->shouldReceive('availableTypes')
            ->once()
            ->andReturn(['power_high', 'relay_sent']);

        $this->app->instance(AppNotificationStore::class, $store);

        $response = $this->get(route('notifications.index', ['per_page' => 10]));

        $response->assertOk();
        $response->assertSee('Notification history', false);
        $response->assertSee('id="notifications-filter-root"', false);
        $response->assertSee('notifications-page.ts', false);
        $response->assertSee('High load detected', false);
    }

    public function test_notifications_api_returns_the_latest_ten_items_in_descending_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $store = Mockery::mock(AppNotificationStore::class);
        $store->shouldReceive('latest')
            ->once()
            ->with(10)
            ->andReturn(collect(range(3, 12))
                ->reverse()
                ->map(fn (int $index): array => [
                    'id' => 'mongo-'.$index,
                    'type' => 'relay_sent',
                    'level' => 'info',
                    'title' => 'Notification '.$index,
                    'message' => 'Payload '.$index,
                    'action_url' => null,
                    'created_at' => Carbon::parse('2026-03-26 12:00:00')->addSeconds($index)->toIso8601String(),
                ])
                ->values()
                ->all());

        $this->app->instance(AppNotificationStore::class, $store);

        $response = $this->get(route('api.notifications.latest'));

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonCount(10, 'notifications');
        $response->assertJsonPath('notifications.0.title', 'Notification 12');
        $response->assertJsonPath('notifications.9.title', 'Notification 3');
    }

    public function test_authenticated_layout_renders_the_notifications_inbox_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('id="app-notifications-panel"', false);
        $response->assertSee(route('api.notifications.latest'), false);
        $response->assertSee(route('notifications.index'), false);
    }
}
