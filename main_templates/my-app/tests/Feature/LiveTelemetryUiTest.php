<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveTelemetryUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_layout_includes_global_live_telemetry_polling_script(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('id="live-telemetry-pill"', false);
        $response->assertSee("window.dispatchEvent(new CustomEvent('pulsenode:latest'", false);
        $response->assertSee('setInterval(pollLatest, 2000);', false);
    }

    public function test_operational_pages_listen_to_the_global_live_telemetry_event(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['power-strip.index', 'devices.index', 'battery.index', 'history.index'] as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertOk();
            $response->assertSee("window.addEventListener('pulsenode:latest'", false);
        }
    }

    public function test_devices_page_fetches_live_detections_for_confidence_updates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));

        $response->assertOk();
        $response->assertSee('/api/devices/live-detections', false);
        $response->assertSee('devices-confidence-bar-1', false);
    }

    public function test_live_detections_endpoint_returns_three_sockets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('api.devices.live-detections'));

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonCount(3, 'detections');
    }
}
