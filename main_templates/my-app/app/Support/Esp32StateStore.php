<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use Throwable;

class Esp32StateStore
{
    private const LATEST_CACHE_KEY = 'esp32.state.latest';

    public function latest(): array
    {
        $defaults = $this->defaults();

        $cached = Cache::get(self::LATEST_CACHE_KEY);
        if (is_array($cached)) {
            return $this->normalize(array_merge($defaults, $cached));
        }

        $collection = $this->collection();

        if ($collection === null) {
            return $defaults;
        }

        try {
            $doc = $collection->findOne([], ['sort' => ['received_at' => -1]]);
        } catch (Throwable) {
            return $defaults;
        }

        if ($doc === null) {
            return $defaults;
        }

        $payload = $this->toArray($doc['payload'] ?? null);

        if ($payload === null) {
            return $defaults;
        }

        $updatedAt = $payload['updated_at'] ?? null;
        if (
            (! is_string($updatedAt) || trim($updatedAt) === '')
            && isset($doc['received_at'])
            && $doc['received_at'] instanceof UTCDateTime
        ) {
            $updatedAt = $doc['received_at']->toDateTime()->format(DATE_ATOM);
        }

        if (is_string($updatedAt) && trim($updatedAt) !== '') {
            $payload['updated_at'] = $updatedAt;
        }

        $state = $this->normalize(array_merge($defaults, $payload));
        Cache::forever(self::LATEST_CACHE_KEY, $state);

        return $state;
    }

    public function updateTelemetry(array $payload): array
    {
        $state = $this->normalize(array_merge($this->latest(), $payload));
        $state['updated_at'] = now()->toIso8601String();

        return $this->persist($state, 'telemetry');
    }

    public function updateRelayState(array $payload): array
    {
        $latest = $this->latest();
        $state = $this->normalize(array_merge($latest, $payload));
        $state['updated_at'] = now()->toIso8601String();

        return $this->persist($state, 'command');
    }

    public function update(array $payload): array
    {
        return $this->updateTelemetry($payload);
    }

    private function collection(): ?\MongoDB\Collection
    {
        return MongoConnection::selectCollection((string) config('esp32.mongodb.collection', 'readings'));
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
            'power_1' => 0.0,
            'power_2' => 0.0,
            'power_3' => 0.0,
            'energy' => 0.0,
            'relay_1' => false,
            'relay_2' => false,
            'relay_3' => false,
            'updated_at' => null,
        ];
    }

    private function persist(array $state, string $source): array
    {
        $collection = $this->collection();

        if ($collection !== null) {
            try {
                $collection->insertOne([
                    'topic' => config('esp32.mqtt.data_topic', 'razvy_esp32_2026/data'),
                    'source' => $source,
                    'received_at' => new UTCDateTime(),
                    'payload' => $state,
                ]);
            } catch (Throwable) {
                // Keep serving the latest cached value even when Mongo is temporarily unreachable.
            }
        }

        Cache::forever(self::LATEST_CACHE_KEY, $state);

        return $state;
    }

    private function normalize(array $payload): array
    {
        $power = max(0.0, (float) ($payload['power'] ?? 0));
        $power1 = max(0.0, (float) ($payload['power_1'] ?? 0));
        $power2 = max(0.0, (float) ($payload['power_2'] ?? 0));
        $power3 = max(0.0, (float) ($payload['power_3'] ?? 0));

        return [
            'voltage' => (float) ($payload['voltage'] ?? 0),
            'current' => (float) ($payload['current'] ?? 0),
            'current_1' => (float) ($payload['current_1'] ?? 0),
            'current_2' => (float) ($payload['current_2'] ?? 0),
            'current_3' => (float) ($payload['current_3'] ?? 0),
            'power' => $power,
            'power_1' => $power1,
            'power_2' => $power2,
            'power_3' => $power3,
            'energy' => max(0.0, (float) ($payload['energy'] ?? 0)),
            'relay_1' => (bool) ($payload['relay_1'] ?? false),
            'relay_2' => (bool) ($payload['relay_2'] ?? false),
            'relay_3' => (bool) ($payload['relay_3'] ?? false),
            'updated_at' => $payload['updated_at'] ?? null,
        ];
    }

    private function toArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof BSONDocument || $value instanceof BSONArray) {
            return $value->getArrayCopy();
        }

        return null;
    }
}
