<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use Throwable;

class PowerStripGuardStore
{
    private const CURRENT_CACHE_KEY = 'esp32.power_strip_guard_policy.current';
    private const LIST_CACHE_KEY = 'esp32.power_strip_guard_policy.list';

    public function current(): array
    {
        $cached = Cache::get(self::CURRENT_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $policies = $this->all();
        $current = collect($policies)->firstWhere('status', 'active')
            ?? ($policies[0] ?? $this->defaults());

        Cache::forever(self::CURRENT_CACHE_KEY, $current);

        return $current;
    }

    public function all(): array
    {
        $cached = Cache::get(self::LIST_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

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
            ]);
        } catch (Throwable) {
            return [];
        }

        $policies = [];
        foreach ($cursor as $document) {
            $policies[] = $this->decorate($this->fromDocument($document));
        }

        Cache::forever(self::LIST_CACHE_KEY, $policies);
        Cache::forget(self::CURRENT_CACHE_KEY);

        return $policies;
    }

    public function runnable(): array
    {
        return array_values(array_filter($this->all(), static fn (array $policy): bool => ($policy['status'] ?? null) === 'active'));
    }

    public function save(array $payload): array
    {
        $policy = $this->normalizeForStorage($payload);
        $policy['id'] = (string) Str::uuid();
        $policy['created_at'] = now()->toIso8601String();
        $policy['updated_at'] = $policy['created_at'];

        $collection = $this->collection();
        if ($collection !== null) {
            try {
                $collection->insertOne($this->toDocument($policy));
            } catch (Throwable) {
                // Ignore write failures here so the caller can decide how to surface them.
            }
        }

        $decorated = $this->decorate($policy);
        $this->invalidateCache();

        return $decorated;
    }

    public function pause(string $policyId): ?array
    {
        return $this->toggleEnabled($policyId, false);
    }

    public function resume(string $policyId): ?array
    {
        return $this->toggleEnabled($policyId, true);
    }

    public function delete(string $policyId): bool
    {
        $collection = $this->collection();
        if ($collection === null) {
            return false;
        }

        try {
            $result = $collection->deleteOne(['_id' => $policyId]);
        } catch (Throwable) {
            return false;
        }

        $deleted = $result->getDeletedCount() > 0;
        if ($deleted) {
            $this->invalidateCache();
        }

        return $deleted;
    }

    public function markTriggered(string $policyId, array $metadata): ?array
    {
        $collection = $this->collection();
        if ($collection === null) {
            return null;
        }

        try {
            $collection->updateOne(
                ['_id' => $policyId],
                ['$set' => [
                    'last_triggered_at' => now()->toIso8601String(),
                    'last_triggered_scope' => $metadata['scope'] ?? null,
                    'last_triggered_socket' => $metadata['socket_index'] ?? null,
                    'last_triggered_value' => $metadata['value'] ?? null,
                    'last_triggered_action' => $metadata['action'] ?? null,
                    'last_triggered_reason' => $metadata['reason'] ?? null,
                    'updated_at' => now()->toIso8601String(),
                ]],
            );
        } catch (Throwable) {
            return null;
        }

        $this->invalidateCache();

        return $this->find($policyId);
    }

    public function find(string $policyId): ?array
    {
        $collection = $this->collection();
        if ($collection === null) {
            return null;
        }

        try {
            $document = $collection->findOne(['_id' => $policyId]);
        } catch (Throwable) {
            return null;
        }

        if ($document === null) {
            return null;
        }

        return $this->decorate($this->fromDocument($document));
    }

    private function toggleEnabled(string $policyId, bool $enabled): ?array
    {
        $collection = $this->collection();
        if ($collection === null) {
            return null;
        }

        try {
            $collection->updateOne(
                ['_id' => $policyId],
                ['$set' => [
                    'enabled' => $enabled,
                    'updated_at' => now()->toIso8601String(),
                ]],
            );
        } catch (Throwable) {
            return null;
        }

        $this->invalidateCache();

        return $this->find($policyId);
    }

    private function collection(): ?\MongoDB\Collection
    {
        return MongoConnection::selectCollection((string) config('esp32.mongodb.guard_policies_collection', 'power_strip_guard_policies'));
    }

    private function invalidateCache(): void
    {
        Cache::forget(self::CURRENT_CACHE_KEY);
        Cache::forget(self::LIST_CACHE_KEY);
    }

    private function defaults(): array
    {
        $today = now()->toDateString();

        return [
            'id' => null,
            'exists' => false,
            'enabled' => false,
            'status' => 'empty',
            'status_label' => 'No policy saved',
            'scope_mode' => 'common',
            'common_threshold_amps' => 10.0,
            'socket_threshold_amps_1' => 10.0,
            'socket_threshold_amps_2' => 10.0,
            'socket_threshold_amps_3' => 10.0,
            'action' => 'off-all',
            'start_date' => $today,
            'has_end_date' => false,
            'end_date' => null,
            'notes' => null,
            'last_triggered_at' => null,
            'last_triggered_scope' => null,
            'last_triggered_socket' => null,
            'last_triggered_value' => null,
            'last_triggered_action' => null,
            'last_triggered_reason' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    private function normalizeForStorage(array $payload): array
    {
        $scopeMode = in_array((string) ($payload['scope_mode'] ?? 'common'), ['common', 'per_socket'], true)
            ? (string) $payload['scope_mode']
            : 'common';

        $startDate = $this->normalizeDate($payload['start_date'] ?? null);
        $endDate = $this->normalizeDate($payload['end_date'] ?? null);
        $hasEndDate = (bool) ($payload['has_end_date'] ?? false);

        if (! $hasEndDate) {
            $endDate = null;
        }

        return [
            'id' => isset($payload['id']) && is_string($payload['id']) && trim($payload['id']) !== ''
                ? trim($payload['id'])
                : null,
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'scope_mode' => $scopeMode,
            'common_threshold_amps' => max(0.1, (float) ($payload['common_threshold_amps'] ?? 0)),
            'socket_threshold_amps_1' => max(0.1, (float) ($payload['socket_threshold_amps_1'] ?? 0)),
            'socket_threshold_amps_2' => max(0.1, (float) ($payload['socket_threshold_amps_2'] ?? 0)),
            'socket_threshold_amps_3' => max(0.1, (float) ($payload['socket_threshold_amps_3'] ?? 0)),
            'action' => in_array((string) ($payload['action'] ?? 'off-all'), ['off-1', 'off-2', 'off-3', 'off-all'], true)
                ? (string) $payload['action']
                : 'off-all',
            'start_date' => $startDate ?? now()->toDateString(),
            'has_end_date' => $hasEndDate,
            'end_date' => $endDate,
            'notes' => $this->normalizeNotes($payload['notes'] ?? null),
            'last_triggered_at' => $this->normalizeTimestamp($payload['last_triggered_at'] ?? null),
            'last_triggered_scope' => in_array((string) ($payload['last_triggered_scope'] ?? ''), ['common', 'socket_1', 'socket_2', 'socket_3'], true)
                ? (string) $payload['last_triggered_scope']
                : null,
            'last_triggered_socket' => in_array((int) ($payload['last_triggered_socket'] ?? 0), [1, 2, 3], true)
                ? (int) $payload['last_triggered_socket']
                : null,
            'last_triggered_value' => is_numeric($payload['last_triggered_value'] ?? null)
                ? round((float) $payload['last_triggered_value'], 3)
                : null,
            'last_triggered_action' => in_array((string) ($payload['last_triggered_action'] ?? ''), ['off-1', 'off-2', 'off-3', 'off-all'], true)
                ? (string) $payload['last_triggered_action']
                : null,
            'last_triggered_reason' => $this->normalizeNotes($payload['last_triggered_reason'] ?? null),
        ];
    }

    private function decorate(array $policy): array
    {
        $today = now()->toDateString();
        $enabled = (bool) ($policy['enabled'] ?? false);
        $startDate = $this->normalizeDate($policy['start_date'] ?? null) ?? $today;
        $endDate = $this->normalizeDate($policy['end_date'] ?? null);
        $hasEndDate = (bool) ($policy['has_end_date'] ?? false);

        $status = 'active';
        if (! $enabled) {
            $status = 'paused';
        } elseif ($today < $startDate) {
            $status = 'scheduled';
        } elseif ($hasEndDate && is_string($endDate) && $endDate !== '' && $today > $endDate) {
            $status = 'expired';
        }

        return array_merge($this->defaults(), $policy, [
            'id' => $policy['id'] ?? null,
            'exists' => true,
            'enabled' => $enabled,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'start_date' => $startDate,
            'end_date' => $hasEndDate ? $endDate : null,
            'has_end_date' => $hasEndDate,
            'common_threshold_amps' => (float) ($policy['common_threshold_amps'] ?? 10),
            'socket_threshold_amps_1' => (float) ($policy['socket_threshold_amps_1'] ?? 10),
            'socket_threshold_amps_2' => (float) ($policy['socket_threshold_amps_2'] ?? 10),
            'socket_threshold_amps_3' => (float) ($policy['socket_threshold_amps_3'] ?? 10),
            'last_triggered_value' => is_numeric($policy['last_triggered_value'] ?? null)
                ? round((float) $policy['last_triggered_value'], 3)
                : null,
            'created_at' => $this->normalizeTimestamp($policy['created_at'] ?? null),
            'updated_at' => $this->normalizeTimestamp($policy['updated_at'] ?? null),
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'paused' => 'Paused',
            'scheduled' => 'Scheduled',
            'expired' => 'Expired',
            default => 'No policy saved',
        };
    }

    private function toDocument(array $policy): array
    {
        return [
            '_id' => (string) ($policy['id'] ?? Str::uuid()),
            'enabled' => (bool) $policy['enabled'],
            'scope_mode' => (string) $policy['scope_mode'],
            'common_threshold_amps' => (float) $policy['common_threshold_amps'],
            'socket_threshold_amps_1' => (float) $policy['socket_threshold_amps_1'],
            'socket_threshold_amps_2' => (float) $policy['socket_threshold_amps_2'],
            'socket_threshold_amps_3' => (float) $policy['socket_threshold_amps_3'],
            'action' => (string) $policy['action'],
            'start_date' => $policy['start_date'],
            'has_end_date' => (bool) $policy['has_end_date'],
            'end_date' => $policy['end_date'],
            'notes' => $policy['notes'],
            'last_triggered_at' => $policy['last_triggered_at'],
            'last_triggered_scope' => $policy['last_triggered_scope'],
            'last_triggered_socket' => $policy['last_triggered_socket'],
            'last_triggered_value' => $policy['last_triggered_value'],
            'last_triggered_action' => $policy['last_triggered_action'],
            'last_triggered_reason' => $policy['last_triggered_reason'],
            'created_at' => $policy['created_at'],
            'updated_at' => $policy['updated_at'],
        ];
    }

    private function fromDocument(mixed $value): array
    {
        $document = $this->toArray($value);

        return [
            'id' => isset($document['_id']) ? (string) $document['_id'] : null,
            'enabled' => (bool) ($document['enabled'] ?? false),
            'scope_mode' => (string) ($document['scope_mode'] ?? 'common'),
            'common_threshold_amps' => (float) ($document['common_threshold_amps'] ?? 10),
            'socket_threshold_amps_1' => (float) ($document['socket_threshold_amps_1'] ?? 10),
            'socket_threshold_amps_2' => (float) ($document['socket_threshold_amps_2'] ?? 10),
            'socket_threshold_amps_3' => (float) ($document['socket_threshold_amps_3'] ?? 10),
            'action' => (string) ($document['action'] ?? 'off-all'),
            'start_date' => $this->normalizeDate($document['start_date'] ?? null) ?? now()->toDateString(),
            'has_end_date' => (bool) ($document['has_end_date'] ?? false),
            'end_date' => $this->normalizeDate($document['end_date'] ?? null),
            'notes' => $this->normalizeNotes($document['notes'] ?? null),
            'last_triggered_at' => $this->normalizeTimestamp($document['last_triggered_at'] ?? null),
            'last_triggered_scope' => (string) ($document['last_triggered_scope'] ?? '') ?: null,
            'last_triggered_socket' => in_array((int) ($document['last_triggered_socket'] ?? 0), [1, 2, 3], true)
                ? (int) $document['last_triggered_socket']
                : null,
            'last_triggered_value' => is_numeric($document['last_triggered_value'] ?? null)
                ? round((float) $document['last_triggered_value'], 3)
                : null,
            'last_triggered_action' => (string) ($document['last_triggered_action'] ?? '') ?: null,
            'last_triggered_reason' => $this->normalizeNotes($document['last_triggered_reason'] ?? null),
            'created_at' => $this->normalizeTimestamp($document['created_at'] ?? null),
            'updated_at' => $this->normalizeTimestamp($document['updated_at'] ?? null),
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->toIso8601String();
            } catch (Throwable) {
                return null;
            }
        }

        if ($value instanceof UTCDateTime) {
            return Carbon::instance($value->toDateTime())
                ->setTimezone(config('app.timezone'))
                ->toIso8601String();
        }

        return null;
    }

    private function normalizeNotes(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $notes = trim($value);

        return $notes === '' ? null : mb_substr($notes, 0, 500);
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof BSONDocument || $value instanceof BSONArray) {
            return $value->getArrayCopy();
        }

        return [];
    }
}
