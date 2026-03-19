<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

class Esp32ConnectionHealth
{
    public function relayCommandAvailability(array $latest): array
    {
        $maxAgeSeconds = $this->relayCommandMaxAgeSeconds();
        $lastSeenAt = $this->lastSeenAt($latest);
        $ageSeconds = $this->ageInSeconds($latest);

        if ($lastSeenAt === null || $ageSeconds === null) {
            return [
                'can_turn_on' => false,
                'reason' => 'never_seen',
                'message' => 'Socket power-on is unavailable because the power strip has not sent telemetry yet. It may be unplugged or not connected.',
                'age_seconds' => null,
                'max_age_seconds' => $maxAgeSeconds,
                'last_seen_at' => null,
            ];
        }

        if ($ageSeconds > $maxAgeSeconds) {
            return [
                'can_turn_on' => false,
                'reason' => 'stale',
                'message' => 'Socket power-on is blocked because the latest ESP32 reading is too old. The strip may be unplugged or it may have lost the connection.',
                'age_seconds' => $ageSeconds,
                'max_age_seconds' => $maxAgeSeconds,
                'last_seen_at' => $lastSeenAt->toIso8601String(),
            ];
        }

        return [
            'can_turn_on' => true,
            'reason' => null,
            'message' => null,
            'age_seconds' => $ageSeconds,
            'max_age_seconds' => $maxAgeSeconds,
            'last_seen_at' => $lastSeenAt->toIso8601String(),
        ];
    }

    public function isOnline(array $latest): bool
    {
        $ageSeconds = $this->ageInSeconds($latest);

        return $ageSeconds !== null && $ageSeconds <= $this->offlineAfterSeconds();
    }

    public function ageInSeconds(array $latest): ?int
    {
        $lastSeenAt = $this->lastSeenAt($latest);

        if ($lastSeenAt === null) {
            return null;
        }

        return max(0, now()->getTimestamp() - $lastSeenAt->getTimestamp());
    }

    public function offlineAfterSeconds(): int
    {
        return max(30, (int) config('esp32.connection.offline_after_seconds', 300));
    }

    public function relayCommandMaxAgeSeconds(): int
    {
        return max(15, (int) config('esp32.connection.relay_command_max_age_seconds', 90));
    }

    private function lastSeenAt(array $latest): ?CarbonInterface
    {
        $updatedAt = $latest['updated_at'] ?? null;

        if (! is_string($updatedAt) || trim($updatedAt) === '') {
            return null;
        }

        try {
            return Carbon::parse($updatedAt);
        } catch (Throwable) {
            return null;
        }
    }
}
