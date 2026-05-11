<?php

return [
    'mqtt' => [
        'enabled' => env('ESP32_MQTT_ENABLED', true),
        'host' => env('ESP32_MQTT_HOST', 'broker.hivemq.com'),
        'port' => (int) env('ESP32_MQTT_PORT', 1883),
        'command_topic' => env('ESP32_MQTT_COMMAND_TOPIC', 'razvy_esp32_2026/cmd'),
        'data_topic' => env('ESP32_MQTT_DATA_TOPIC', 'razvy_esp32_2026/data'),
        'username' => env('ESP32_MQTT_USERNAME'),
        'password' => env('ESP32_MQTT_PASSWORD'),
        'publisher_binary' => env('ESP32_MQTT_PUBLISHER_BINARY', 'mosquitto_pub'),
    ],

    'ingest' => [
        'token' => env('ESP32_INGEST_TOKEN'),
    ],

    'mongodb' => [
        'uri' => env('MONGODB_URI'),
        'database' => env('MONGODB_DATABASE', 'espData'),
        'collection' => env('MONGODB_COLLECTION', 'readings'),
        'notifications_collection' => env('MONGODB_NOTIFICATIONS_COLLECTION', 'notifications'),
        'guard_policies_collection' => env('MONGODB_GUARD_POLICIES_COLLECTION', 'power_strip_guard_policies'),
        'power_strip_command_logs_collection' => env('MONGODB_POWER_STRIP_COMMAND_LOGS_COLLECTION', 'power_strip_command_logs'),
        'billing_invoices_bucket' => env('MONGODB_BILLING_INVOICES_BUCKET', 'billing_invoices'),
        'server_selection_timeout_ms' => (int) env('MONGODB_SERVER_SELECTION_TIMEOUT_MS', 1500),
        'connect_timeout_ms' => (int) env('MONGODB_CONNECT_TIMEOUT_MS', 1500),
        'socket_timeout_ms' => (int) env('MONGODB_SOCKET_TIMEOUT_MS', 3000),
    ],

    'connection' => [
        'offline_after_seconds' => (int) env('ESP32_OFFLINE_AFTER_SECONDS', 300),
        'relay_command_max_age_seconds' => (int) env('ESP32_RELAY_COMMAND_MAX_AGE_SECONDS', 90),
    ],
];
