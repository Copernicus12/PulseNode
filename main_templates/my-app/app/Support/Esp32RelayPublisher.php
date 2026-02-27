<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class Esp32RelayPublisher
{
    public function publish(int $relayId, string $state): array
    {
        $normalizedState = strtolower($state) === 'on' ? 'on' : 'off';
        $payload = json_encode([
            'relay' => $relayId,
            'state' => $normalizedState,
        ]);

        if (! config('esp32.mqtt.enabled')) {
            return [
                'sent' => $payload ?: '',
                'published' => false,
                'message' => 'MQTT disabled in config/esp32.php',
            ];
        }

        $binary = (string) config('esp32.mqtt.publisher_binary', 'mosquitto_pub');
        $host = (string) config('esp32.mqtt.host', '127.0.0.1');
        $port = (int) config('esp32.mqtt.port', 1883);
        $topic = (string) config('esp32.mqtt.command_topic', 'esp32/cmd');
        $username = config('esp32.mqtt.username');
        $password = config('esp32.mqtt.password');

        $command = [
            $binary,
            '-h',
            $host,
            '-p',
            (string) $port,
            '-t',
            $topic,
            '-m',
            $payload ?: '',
        ];

        if (is_string($username) && $username !== '') {
            $command[] = '-u';
            $command[] = $username;
        }

        if (is_string($password) && $password !== '') {
            $command[] = '-P';
            $command[] = $password;
        }

        $process = new Process($command);
        $process->setTimeout(4);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Failed to publish relay command via MQTT.');
        }

        return [
            'sent' => $payload ?: '',
            'published' => true,
            'message' => 'MQTT command published.',
        ];
    }
}
