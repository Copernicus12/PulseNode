<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use Throwable;

class AppNotificationStore
{
    public function store(array $payload, int $suppressForSeconds = 0): ?array
    {
        $collection = $this->collection();

        if ($collection === null) {
            return null;
        }

        $message = $payload['message'] ?? null;
        $createdAt = now();

        if ($suppressForSeconds > 0) {
            $recentDuplicate = $collection->findOne([
                'type' => (string) ($payload['type'] ?? 'system'),
                'title' => (string) ($payload['title'] ?? 'Notification'),
                'message' => $message,
                'created_at' => [
                    '$gte' => new UTCDateTime($createdAt->copy()->subSeconds($suppressForSeconds)),
                ],
            ]);

            if ($recentDuplicate !== null) {
                return $this->normalizeDocument($recentDuplicate);
            }
        }

        $document = [
            'type' => (string) ($payload['type'] ?? 'system'),
            'level' => (string) ($payload['level'] ?? 'info'),
            'title' => (string) ($payload['title'] ?? 'Notification'),
            'message' => is_string($message) && trim($message) !== '' ? $message : null,
            'action_url' => isset($payload['action_url']) ? (string) $payload['action_url'] : null,
            'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : null,
            'created_at' => new UTCDateTime($createdAt),
        ];

        $result = $collection->insertOne($document);
        $document['_id'] = $result->getInsertedId();

        return $this->normalizeDocument($document);
    }

    public function latest(int $limit = 10): array
    {
        $collection = $this->collection();

        if ($collection === null) {
            return [];
        }

        $cursor = $collection->find([], [
            'sort' => ['created_at' => -1, '_id' => -1],
            'limit' => max(1, $limit),
        ]);

        $items = [];
        foreach ($cursor as $document) {
            $normalized = $this->normalizeDocument($document);
            if ($normalized !== null) {
                $items[] = $this->toFeedItem($normalized);
            }
        }

        return $items;
    }

    public function paginate(int $perPage, int $page, array $filters = [], string $sortBy = 'newest'): LengthAwarePaginator
    {
        $collection = $this->collection();
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $query = $this->buildQuery($filters);
        $sort = $this->sortDefinition($sortBy);

        if ($collection === null) {
            return new LengthAwarePaginator([], 0, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        $total = $collection->countDocuments($query);
        $cursor = $collection->find($query, [
            'sort' => $sort,
            'skip' => ($page - 1) * $perPage,
            'limit' => $perPage,
        ]);

        $items = [];
        foreach ($cursor as $document) {
            $normalized = $this->normalizeDocument($document);
            if ($normalized !== null) {
                $items[] = (object) $normalized;
            }
        }

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    public function summary(array $filters = []): array
    {
        $collection = $this->collection();
        $query = $this->buildQuery($filters);

        if ($collection === null) {
            return [
                'total' => 0,
                'errors' => 0,
                'warnings' => 0,
                'latest' => null,
            ];
        }

        $latest = $collection->findOne($query, ['sort' => ['created_at' => -1, '_id' => -1]]);

        return [
            'total' => $collection->countDocuments($query),
            'errors' => $collection->countDocuments(array_merge($query, ['level' => 'error'])),
            'warnings' => $collection->countDocuments(array_merge($query, ['level' => 'warning'])),
            'latest' => ($normalized = $this->normalizeDocument($latest)) ? (object) $normalized : null,
        ];
    }

    public function availableTypes(): array
    {
        $collection = $this->collection();

        if ($collection === null) {
            return [];
        }

        try {
            $types = $collection->distinct('type');
        } catch (Throwable) {
            return [];
        }

        return collect($types)
            ->filter(fn (mixed $type): bool => is_string($type) && trim($type) !== '')
            ->map(fn (string $type): string => trim($type))
            ->unique()
            ->sort()
            ->values()
            ->all();
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
                ->selectCollection((string) config('esp32.mongodb.notifications_collection', 'notifications'));
        } catch (Throwable) {
            return null;
        }
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
            'type' => (string) ($document['type'] ?? 'system'),
            'level' => (string) ($document['level'] ?? 'info'),
            'title' => (string) ($document['title'] ?? 'Notification'),
            'message' => isset($document['message']) ? (string) $document['message'] : null,
            'action_url' => isset($document['action_url']) ? $this->normalizeActionUrl((string) $document['action_url']) : null,
            'meta' => is_array($document['meta'] ?? null) ? $document['meta'] : null,
            'created_at' => $createdAt instanceof UTCDateTime ? Carbon::instance($createdAt->toDateTime()) : null,
        ];
    }

    private function toFeedItem(array $notification): array
    {
        return [
            'id' => $notification['id'],
            'type' => $notification['type'],
            'level' => $notification['level'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'action_url' => $notification['action_url'],
            'created_at' => $notification['created_at']?->toIso8601String(),
        ];
    }

    private function buildQuery(array $filters): array
    {
        $query = [];

        $level = $filters['level'] ?? null;
        if (is_string($level) && in_array($level, ['info', 'success', 'warning', 'error'], true)) {
            $query['level'] = $level;
        }

        $type = $filters['type'] ?? null;
        if (is_string($type) && trim($type) !== '') {
            $query['type'] = trim($type);
        }

        return $query;
    }

    private function sortDefinition(string $sortBy): array
    {
        return match ($sortBy) {
            'oldest' => ['created_at' => 1, '_id' => 1],
            'level' => ['level' => 1, 'created_at' => -1, '_id' => -1],
            default => ['created_at' => -1, '_id' => -1],
        };
    }

    private function normalizeActionUrl(string $value): ?string
    {
        $url = trim($value);
        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['/','#'])) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }
}
