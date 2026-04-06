<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PowerStripSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_hardware_and_payload_in_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('power-strip-diagnostics.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/PowerStripDiagnostics')
                ->where('diagnostics.hardware.device_type', 'ESP32-S3 N16R8 DevKit')
                ->where('diagnostics.hardware.ingest_endpoint', 'POST /api/ingest')
                ->where('diagnostics.connection.publish_interval_seconds', 10)
                ->where('diagnostics.pinout.0.pin', 'GPIO 4')
                ->where('diagnostics.pinout.3.name', 'Voltage (ZMPT101B)')
                ->where('diagnostics.pinout.6.name', 'Relay 3 (for Current 3)')
            );
    }

    public function test_legacy_power_strip_settings_route_redirects_to_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('power-strip.settings'))
            ->assertRedirect(route('power-strip-diagnostics.edit'));
    }
}
