<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class Esp32StateStore
{
    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/private/esp32_latest.json');
    }

    public function latest(): array
    {
        $defaults = $this->defaults();

        if (! File::exists($this->path)) {
            return $defaults;
        }

        $decoded = json_decode((string) File::get($this->path), true);

        if (! is_array($decoded)) {
            return $defaults;
        }

        return $this->normalize(array_merge($defaults, $decoded));
    }

    public function update(array $payload): array
    {
        $state = $this->normalize(array_merge($this->latest(), $payload));
        $state['updated_at'] = now()->toIso8601String();

        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode($state, JSON_PRETTY_PRINT));

        return $state;
    }

    private function defaults(): array
    {
        return [
            'voltage' => 0.0,
            'current' => 0.0,
            'current_1' => 0.0,
            'current_2' => 0.0,
            'current_3' => 0.0,
            'power' => 0.0,
            'energy' => 0.0,
            'relay_1' => false,
            'relay_2' => false,
            'relay_3' => false,
            'updated_at' => null,
        ];
    }

    private function normalize(array $payload): array
    {
        return [
            'voltage' => (float) ($payload['voltage'] ?? 0),
            'current' => (float) ($payload['current'] ?? 0),
            'current_1' => (float) ($payload['current_1'] ?? 0),
            'current_2' => (float) ($payload['current_2'] ?? 0),
            'current_3' => (float) ($payload['current_3'] ?? 0),
            'power' => (float) ($payload['power'] ?? 0),
            'energy' => (float) ($payload['energy'] ?? 0),
            'relay_1' => (bool) ($payload['relay_1'] ?? false),
            'relay_2' => (bool) ($payload['relay_2'] ?? false),
            'relay_3' => (bool) ($payload['relay_3'] ?? false),
            'updated_at' => $payload['updated_at'] ?? null,
        ];
    }
}
