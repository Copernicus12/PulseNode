<?php

namespace App\Support;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use RuntimeException;

class Esp32RelayPublisher
{
    public function publish(int $relayId, string $state): array
    {
        $normalizedState = strtolower($state) === 'on' ? 'on' : 'off';
        $payload = json_encode([
            'relay' => $relayId,
            'state' => $normalizedState,
        ]);

        $host = (string) config('esp32.mqtt.host', 'broker.hivemq.com');
        $port = (int) config('esp32.mqtt.port', 1883);
        $topic = (string) config('esp32.mqtt.command_topic', 'razvy_esp32_2026/cmd');
        $username = config('esp32.mqtt.username');
        $password = config('esp32.mqtt.password');
        $clientId = 'laravel-relay-publisher-'.bin2hex(random_bytes(4));

        $settings = new ConnectionSettings();
        if (is_string($username) && $username !== '') {
            $settings = $settings->setUsername($username);
        }
        if (is_string($password) && $password !== '') {
            $settings = $settings->setPassword($password);
        }

        try {
            $client = new MqttClient($host, $port, $clientId);
            $client->connect($settings, true);
            $client->publish($topic, $payload ?: '', 0);
            $client->disconnect();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to publish relay command via MQTT: '.$exception->getMessage());
        }

        return [
            'sent' => $payload ?: '',
            'published' => true,
            'message' => 'MQTT command published.',
        ];
    }
}
