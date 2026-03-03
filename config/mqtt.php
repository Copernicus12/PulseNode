<?php

return [
    'host' => env('MQTT_HOST', 'broker.hivemq.com'),
    'port' => env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME', ''),
    'password' => env('MQTT_PASSWORD', ''),
    'client_id' => env('MQTT_CLIENT_ID', 'Laravel_ESP32_Dashboard'),
    
    'topics' => [
        'data' => 'razvy_esp32_2026/data',
        'cmd' => 'razvy_esp32_2026/cmd'
    ]
];