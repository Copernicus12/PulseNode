<?php

namespace App\Http\Controllers;

use App\Support\Esp32RelayPublisher;
use App\Support\Esp32StateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class Esp32ApiController extends Controller
{
    public function latest(Esp32StateStore $store): JsonResponse
    {
        return response()->json($store->latest());
    }

    public function relay(string $state, Esp32StateStore $store, Esp32RelayPublisher $publisher): JsonResponse
    {
        if (! in_array($state, ['on', 'off'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'State must be on or off.',
            ], 422);
        }

        try {
            $publishResult = $publisher->publish($state);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'sent' => strtoupper($state),
                'published' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        $latest = $store->update(['relay' => $state === 'on']);

        return response()->json([
            'status' => 'ok',
            ...$publishResult,
            'relay' => $latest['relay'],
            'latest' => $latest,
        ]);
    }

    public function ingest(Request $request, Esp32StateStore $store): JsonResponse
    {
        $configuredToken = (string) config('esp32.ingest.token', '');
        if ($configuredToken !== '' && $request->header('X-ESP32-TOKEN') !== $configuredToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid ingest token.',
            ], 403);
        }

        $payload = $request->validate([
            'voltage' => ['sometimes', 'numeric'],
            'current' => ['sometimes', 'numeric'],
            'power' => ['sometimes', 'numeric'],
            'energy' => ['sometimes', 'numeric'],
            'relay' => ['sometimes', 'boolean'],
        ]);

        $latest = $store->update($payload);

        return response()->json([
            'status' => 'ok',
            'latest' => $latest,
        ]);
    }
}
