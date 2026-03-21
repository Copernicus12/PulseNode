<?php

namespace App\Support;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use Throwable;

class Esp32StateStore
{
    public function latest(): array
    {
        $defaults = $this->defaults();
        $collection = $this->collection();

        if ($collection === null) {
            return $defaults;
        }

        $doc = $collection->findOne([], ['sort' => ['received_at' => -1]]);

        if ($doc === null) {
            return $defaults;
        }

        $payload = $this->toArray($doc['payload'] ?? null);

        if ($payload === null) {
            return $defaults;
        }

        $updatedAt = null;
        if (isset($doc['received_at']) && $doc['received_at'] instanceof UTCDateTime) {
            $updatedAt = $doc['received_at']->toDateTime()->format(DATE_ATOM);
        }

        if ($updatedAt !== null) {
            $payload['updated_at'] = $updatedAt;
        }

        return $this->normalize(array_merge($defaults, $payload));
    }

    public function update(array $payload): array
    {
        $state = $this->normalize(array_merge($this->latest(), $payload));
        $state['updated_at'] = now()->toIso8601String();
        $collection = $this->collection();

        if ($collection !== null) {
            $collection->insertOne([
                'topic' => config('esp32.mqtt.data_topic', 'razvy_esp32_2026/data'),
                'received_at' => new UTCDateTime(),
                'payload' => $state,
            ]);
        }

        return $state;
    }

    private function collection(): ?\MongoDB\Collection
    {
        $uri = (string) config('esp32.mongodb.uri', '');
        if ($uri === '') {
            return null;
        }

        try {
            $client = new Client($uri);

            return $client
                ->selectDatabase((string) config('esp32.mongodb.database', 'espData'))
                ->selectCollection((string) config('esp32.mongodb.collection', 'readings'));
        } catch (Throwable) {
            return null;
        }
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

    private function normalize(array $payload): array
    {
        return [
            'voltage' => (float) ($payload['voltage'] ?? 0),
            'current' => (float) ($payload['current'] ?? 0),
            'current_1' => (float) ($payload['current_1'] ?? 0),
            'current_2' => (float) ($payload['current_2'] ?? 0),
            'current_3' => (float) ($payload['current_3'] ?? 0),
            'power' => (float) ($payload['power'] ?? 0),
            'power_1' => (float) ($payload['power_1'] ?? 0),
            'power_2' => (float) ($payload['power_2'] ?? 0),
            'power_3' => (float) ($payload['power_3'] ?? 0),
            'energy' => (float) ($payload['energy'] ?? 0),
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
