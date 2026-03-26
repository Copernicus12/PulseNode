<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\Esp32StateStore;
use App\Support\Esp32ConnectionHealth;
use App\Support\NotificationCenter;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListener extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Listen to ESP32 MQTT messages and store data';

    private $store;
    private $connectionHealth;
    private $notifications;

    public function __construct(Esp32StateStore $store, Esp32ConnectionHealth $connectionHealth, NotificationCenter $notifications)
    {
        parent::__construct();
        $this->store = $store;
        $this->connectionHealth = $connectionHealth;
        $this->notifications = $notifications;
    }

    public function handle()
    {
        $this->info('Starting MQTT listener...');
        
        $client = new MqttClient(
            config('esp32.mqtt.host'),
            config('esp32.mqtt.port')
        );
        
        // For public broker, we don't need credentials
        $connectionSettings = new ConnectionSettings();
        
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
