<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MqttService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class Esp32Controller extends Controller
{
    private $mqttService;
    
    public function __construct(MqttService $mqttService)
    {
        $this->mqttService = $mqttService;
    }
    
    /**
     * Get latest ESP32 data
     */
    public function getLatest(): JsonResponse
    {
        // Simulez datele ca în Flask - în realitate ar trebui să stochezi 
        // ultimele date primite de la ESP32 în cache/database
        $data = [
            'voltage' => 0,
            'current' => 0,
            'power' => 0,
            'energy' => 0,
            'relay' => false
        ];
        
        return response()->json($data);
    }
    
    /**
     * Control ESP32 relay
     */
    public function controlRelay(Request $request, string $state): JsonResponse
    {
        $relayState = strtolower($state) === 'on';
        
        $success = $this->mqttService->publishRelayCommand($relayState);
        
        return response()->json([
            'status' => $success ? 'ok' : 'error',
            'sent' => $relayState ? 'ON' : 'OFF'
        ]);
    }
}