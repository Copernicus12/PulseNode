<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\Esp32StateStore;
use App\Support\Esp32ConnectionHealth;
use App\Support\NotificationCenter;
use App\Support\PowerStripGuardService;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListener extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Listen to ESP32 MQTT messages and store data';

    private $store;
    private $connectionHealth;
    private $notifications;
    private $guardService;

    public function __construct(Esp32StateStore $store, Esp32ConnectionHealth $connectionHealth, NotificationCenter $notifications, PowerStripGuardService $guardService)
    {
        parent::__construct();
        $this->store = $store;
        $this->connectionHealth = $connectionHealth;
        $this->notifications = $notifications;
        $this->guardService = $guardService;
    }

    public function handle()
    {
        $this->info('Starting MQTT listener...');

        $host = (string) config('esp32.mqtt.host', 'broker.hivemq.com');
        $port = (int) config('esp32.mqtt.port', 1883);
        $username = config('esp32.mqtt.username');
        $password = config('esp32.mqtt.password');

        $client = new MqttClient(
            $host,
            $port,
            'laravel-mqtt-listener-'.bin2hex(random_bytes(4))
        );

        $connectionSettings = new ConnectionSettings();
        if (is_string($username) && $username !== '') {
            $connectionSettings = $connectionSettings->setUsername($username);
        }
        if (is_string($password) && $password !== '') {
            $connectionSettings = $connectionSettings->setPassword($password);
        }
        
        try {
            $client->connect($connectionSettings, true);
            $this->info('Connected to MQTT broker');
            
            $client->subscribe(config('esp32.mqtt.data_topic'), function ($topic, $message) {
                $this->info("Received: {$message}");
                
                try {
                    $data = json_decode($message, true);
                    if ($data) {
                        $previous = $this->store->latest();
                        $latest = $this->store->updateTelemetry($data);
                        $this->notifications->recordTelemetryUpdate($previous, $latest, $this->connectionHealth);
                        $this->guardService->enforce($latest);
                        $this->info('Data stored successfully');
                    }
                } catch (\Exception $e) {
                    $this->error('Failed to process message: ' . $e->getMessage());
                }
            });
            
            $client->loop(true);
            
        } catch (\Exception $e) {
            $this->error('MQTT connection failed: ' . $e->getMessage());
        }
    }
}
