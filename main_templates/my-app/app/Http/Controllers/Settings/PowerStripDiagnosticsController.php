<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Esp32StateStore;
use Inertia\Inertia;
use Inertia\Response;

class PowerStripDiagnosticsController extends Controller
{
    public function edit(Esp32StateStore $store): Response
    {
        return Inertia::render('settings/PowerStripDiagnostics', [
            'diagnostics' => [
                'latest' => $store->latest(),
                'connection' => [
                    'mqtt_broker' => (string) config('esp32.mqtt.host', 'broker.hivemq.com'),
                    'mqtt_port' => (int) config('esp32.mqtt.port', 1883),
                    'mqtt_enabled' => (bool) config('esp32.mqtt.enabled', true),
                    'command_topic' => (string) config('esp32.mqtt.command_topic', 'razvy_esp32_2026/cmd'),
                    'telemetry_topic' => (string) config('esp32.mqtt.data_topic', 'razvy_esp32_2026/data'),
                    'publish_interval_seconds' => 10,
                    'dashboard_poll_seconds' => 5,
                ],
                'hardware' => [
                    'device_type' => 'ESP32-S3 N16R8 DevKit',
                    'firmware_version' => 'v2.4.1',
                    'relay_count' => '3 (230V / 10A each)',
                    'ingest_endpoint' => 'POST /api/ingest',
                    'storage' => 'Local JSON file',
                ],
                'pinout' => [
                    ['name' => 'Current 1 (ACS712)', 'pin' => 'GPIO 4', 'type' => 'Analog'],
                    ['name' => 'Current 2 (ACS712)', 'pin' => 'GPIO 5', 'type' => 'Analog'],
                    ['name' => 'Current 3 (ACS712)', 'pin' => 'GPIO 6', 'type' => 'Analog'],
                    ['name' => 'Voltage (ZMPT101B)', 'pin' => 'GPIO 7', 'type' => 'Analog'],
                    ['name' => 'Relay 1 (for Current 1)', 'pin' => 'GPIO 15', 'type' => 'Digital'],
                    ['name' => 'Relay 2 (for Current 2)', 'pin' => 'GPIO 16', 'type' => 'Digital'],
                    ['name' => 'Relay 3 (for Current 3)', 'pin' => 'GPIO 17', 'type' => 'Digital'],
                ],
            ],
        ]);
    }
}
