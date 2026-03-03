<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    private $client;
    private $connectionSettings;
    
    public function __construct()
    {
        $this->client = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port')
        );
        
        $this->connectionSettings = (new ConnectionSettings())
            ->setUsername(config('mqtt.username'))
            ->setPassword(config('mqtt.password'));
    }
    
    public function connect()
    {
        try {
            $this->client->connect($this->connectionSettings, true);
            return true;
        } catch (\Exception $e) {
            Log::error('MQTT Connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function publish($topic, $message)
    {
        try {
            if (!$this->client->isConnected()) {
                $this->connect();
            }
            
            $this->client->publish($topic, json_encode($message));
            return true;
        } catch (\Exception $e) {
            Log::error('MQTT Publish failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function publishRelayCommand($state)
    {
        $command = $state ? 'ON' : 'OFF';
        return $this->publish(config('mqtt.topics.cmd'), $command);
    }
    
    public function disconnect()
    {
        if ($this->client->isConnected()) {
            $this->client->disconnect();
        }
    }
}