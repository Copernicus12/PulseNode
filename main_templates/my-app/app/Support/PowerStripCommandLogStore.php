<?php

namespace App\Support;

use Carbon\Carbon;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use Throwable;

class PowerStripCommandLogStore
{
    private const DUPLICATE_SUPPRESSION_SECONDS = 5;

    public function store(array $payload): ?array
    {
        $collection = $this->collection();

        if ($collection === null) {
            return null;
        }

        $createdAt = now();
        $level = $this->normalizeLevel((string) ($payload['level'] ?? 'info'));
        $message = trim((string) ($payload['message'] ?? ''));
        $source = $this->normalizeSource((string) ($payload['source'] ?? 'power-strip'));

        $recentDuplicate = $this->findRecentDuplicate($collection, $level, $source, $message, $createdAt);
        if ($recentDuplicate !== null) {
            $normalized = $this->normalizeDocument($recentDuplicate);

            return $this->toFeedItem($normalized);
        }

        $document = [
            'level' => $level,
            'message' => $message,
            'source' => $source,
            'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : null,
            'created_at' => new UTCDateTime($createdAt),
        ];

        if ($document['message'] === '') {
            $document['message'] = 'Power Strip event';
        }

        try {
            $result = $collection->insertOne($document);
        } catch (Throwable) {
            return null;
        }

        $document['_id'] = $result->getInsertedId();

        return $this->toFeedItem($this->normalizeDocument($document));
    }

    public function latest(int $limit = 40): array
    {
        $collection = $this->collection();

        if ($collection === null) {
            return [];
        }

        try {
            $cursor = $collection->find([], [
                'sort' => [
                    'created_at' => -1,
                    '_id' => -1,
                ],
                'limit' => max(1, $limit),
            ]);
        } catch (Throwable) {
            return [];
        }

        $items = [];
        foreach ($cursor as $document) {
            $normalized = $this->normalizeDocument($document);
            if ($normalized !== null) {
                $items[] = $this->toFeedItem($normalized);
            }
        }

        return $items;
    }

    public function clear(): bool
    {
        $collection = $this->collection();

        if ($collection === null) {
            return false;
        }

        try {
            $collection->deleteMany([]);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function collection(): ?\MongoDB\Collection
    {
        return MongoConnection::selectCollection((string) config('esp32.mongodb.power_strip_command_logs_collection', 'power_strip_command_logs'));
    }

    private function normalizeLevel(string $level): string
    {
        return in_array($level, ['success', 'warning', 'error', 'info'], true) ? $level : 'info';
    }

    private function findRecentDuplicate(\MongoDB\Collection $collection, string $level, string $source, string $message, \DateTimeInterface $createdAt): mixed
    {
        if (trim($message) === '') {
            return null;
        }

        $createdAtCarbon = Carbon::instance($createdAt);

        try {
            return $collection->findOne([
                'level' => $level,
                'source' => $source,
                'message' => $message,
                'created_at' => [
                    '$gte' => new UTCDateTime($createdAtCarbon->copy()->subSeconds(self::DUPLICATE_SUPPRESSION_SECONDS)),
                ],
            ], [
                'sort' => [
                    'created_at' => -1,
                    '_id' => -1,
                ],
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeSource(string $source): string
    {
        $source = trim($source);

        return $source !== '' ? $source : 'power-strip';
    }

    private function normalizeDocument(mixed $document): ?array
    {
        if ($document === null) {
            return null;
        }

        if ($document instanceof BSONDocument || $document instanceof BSONArray) {
            $document = $document->getArrayCopy();
        }

        if (! is_array($document)) {
            return null;
        }

        $id = $document['_id'] ?? null;
        $createdAt = $document['created_at'] ?? null;

        return [
            'id' => $id instanceof ObjectId ? (string) $id : (is_scalar($id) ? (string) $id : ''),
            'level' => $this->normalizeLevel((string) ($document['level'] ?? 'info')),
            'message' => (string) ($document['message'] ?? 'Power Strip event'),
            'source' => $this->normalizeSource((string) ($document['source'] ?? 'power-strip')),
            'meta' => is_array($document['meta'] ?? null) ? $document['meta'] : null,
            'created_at' => $createdAt instanceof UTCDateTime ? Carbon::instance($createdAt->toDateTime()) : null,
        ];
    }

    private function toFeedItem(?array $document): ?array
    {
        if ($document === null) {
            return null;
        }

        return [
            'id' => $document['id'],
            'level' => $document['level'],
            'message' => $document['message'],
            'source' => $document['source'],
            'meta' => $document['meta'],
            'time' => $document['created_at']?->toIso8601String(),
        ];
    }
}
