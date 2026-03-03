<?php

return [
    'mqtt' => [
        'enabled' => env('ESP32_MQTT_ENABLED', false),
        'host' => env('ESP32_MQTT_HOST', '127.0.0.1'),
        'port' => (int) env('ESP32_MQTT_PORT', 1883),
        'command_topic' => env('ESP32_MQTT_COMMAND_TOPIC', 'esp32/cmd'),
        'data_topic' => env('ESP32_MQTT_DATA_TOPIC', 'esp32/data'),
        'username' => env('ESP32_MQTT_USERNAME'),
        'password' => env('ESP32_MQTT_PASSWORD'),
        'publisher_binary' => env('ESP32_MQTT_PUBLISHER_BINARY', 'mosquitto_pub'),
    ],

    'ingest' => [
        'token' => env('ESP32_INGEST_TOKEN'),
    ],
];
