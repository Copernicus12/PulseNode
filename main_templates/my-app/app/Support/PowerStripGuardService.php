<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

class PowerStripGuardService
{
    public function __construct(
        private readonly PowerStripGuardStore $store,
        private readonly Esp32RelayPublisher $publisher,
        private readonly NotificationCenter $notifications,
    ) {
    }

    public function currentPolicy(): array
    {
        return $this->store->current();
    }

    public function preview(array $latest): array
    {
        return $this->evaluate($latest, $this->store->current(), false);
    }

    public function enforce(array $latest): array
    {
        $policies = $this->store->runnable();
        if ($policies === []) {
            return $this->result(false, 'disabled', 'common', 'off-all', null, null, null, 'No active guard policy is enabled.');
        }

        $lastResult = null;
        foreach ($policies as $policy) {
            $result = $this->evaluate($latest, $policy, true);
            $lastResult = $result;

            if (($result['triggered'] ?? false) !== true) {
                continue;
            }

            $policyId = (string) ($policy['id'] ?? $policy['_id'] ?? '');
            if ($policyId !== '') {
                $this->store->markTriggered($policyId, [
                    'scope' => $result['scope'] ?? null,
                    'socket_index' => $result['triggered_socket'] ?? null,
                    'value' => $result['measured_value'] ?? null,
                    'action' => $result['action'] ?? null,
                    'reason' => $result['reason'] ?? null,
                ]);
            }

            return $result;
        }

        return $lastResult ?? $this->result(false, 'below_threshold', 'common', 'off-all', null, null, null, 'Current draw is below the configured threshold.');
    }

    public function evaluate(array $latest, array $policy, bool $execute = false): array
    {
        $today = now()->toDateString();
        $scopeMode = (string) ($policy['scope_mode'] ?? 'common');
        $action = (string) ($policy['action'] ?? 'off-all');
        $startDate = (string) ($policy['start_date'] ?? $today);
        $endDate = ! empty($policy['end_date']) ? (string) $policy['end_date'] : null;
        $hasEndDate = (bool) ($policy['has_end_date'] ?? false);
        $enabled = (bool) ($policy['enabled'] ?? false);

        if (! $enabled) {
            return $this->result(false, 'disabled', $scopeMode, $action, null, null, null, 'Guard is disabled.');
        }

        if ($today < $startDate) {
            return $this->result(false, 'not_started', $scopeMode, $action, null, null, null, 'Guard is scheduled to start on '.$startDate.'.');
        }

        if ($hasEndDate && is_string($endDate) && $endDate !== '' && $today > $endDate) {
            return $this->result(false, 'expired', $scopeMode, $action, null, null, null, 'Guard ended on '.$endDate.'.');
        }

        $cooldownUntil = $this->parseTimestamp($policy['last_triggered_at'] ?? null);
        if ($cooldownUntil !== null && $cooldownUntil->greaterThan(now()->subSeconds(60))) {
            return $this->result(false, 'cooldown', $scopeMode, $action, null, null, null, 'Guard recently triggered and is waiting before checking again.');
        }

        $candidates = $this->resolveCandidates($latest, $policy, $scopeMode);
        if ($candidates === []) {
            return $this->result(false, 'below_threshold', $scopeMode, $action, null, null, null, 'Current draw is below the configured threshold.');
        }

        $candidate = $candidates[0];
        $targets = $this->actionTargets($action);
        if ($targets === []) {
            return $this->result(false, 'invalid_action', $scopeMode, $action, $candidate['socket_index'], $candidate['threshold'], $candidate['value'], 'No guard action was selected.');
        }

        $published = [];
        foreach ($targets as $relayId) {
            $relayKey = 'relay_'.$relayId;
            if (! array_key_exists($relayKey, $latest) || (bool) $latest[$relayKey] === false) {
                continue;
            }

            if (! $execute) {
                $published[] = $relayId;
                continue;
            }

            try {
                $this->publisher->publish($relayId, 'off');
                $published[] = $relayId;
            } catch (Throwable $exception) {
                // Keep checking the guard on the next telemetry update.
            }
        }

        if ($published === []) {
            return $this->result(false, 'already_off', $scopeMode, $action, $candidate['socket_index'], $candidate['threshold'], $candidate['value'], 'The target sockets are already off.');
        }

        if ($execute) {
            $this->notifications->guardTriggered(
                $scopeMode,
                $candidate['threshold'],
                $candidate['value'],
                $action,
                $published,
                $candidate['socket_index'],
            );
        }

        return $this->result(true, 'triggered', $scopeMode, $action, $candidate['socket_index'], $candidate['threshold'], $candidate['value'], 'Guard triggered and issued shutdown commands.', $published);
    }

    private function resolveCandidates(array $latest, array $policy, string $scopeMode): array
    {
        if ($scopeMode === 'per_socket') {
            $candidates = [];

            foreach ([1, 2, 3] as $socketIndex) {
                $threshold = (float) ($policy['socket_threshold_amps_'.$socketIndex] ?? 0);
                $value = max(0.0, (float) ($latest['current_'.$socketIndex] ?? 0));

                if ($value >= $threshold && $threshold > 0) {
                    $candidates[] = [
                        'socket_index' => $socketIndex,
                        'threshold' => $threshold,
                        'value' => $value,
                    ];
                }
            }

            return $candidates;
        }

        $threshold = (float) ($policy['common_threshold_amps'] ?? 0);
        $value = max(0.0, (float) ($latest['current'] ?? 0));

        if ($threshold > 0 && $value >= $threshold) {
            return [[
                'socket_index' => null,
                'threshold' => $threshold,
                'value' => $value,
            ]];
        }

        return [];
    }

    private function actionTargets(string $action): array
    {
        return match ($action) {
            'off-1' => [1],
            'off-2' => [2],
            'off-3' => [3],
            'off-all' => [1, 2, 3],
            default => [],
        };
    }

    private function result(
        bool $triggered,
        string $reason,
        string $scopeMode,
        string $action,
        ?int $triggeredSocket,
        ?float $threshold,
        ?float $value,
        string $message,
        array $published = [],
    ): array {
        return [
            'triggered' => $triggered,
            'reason' => $reason,
            'scope' => $scopeMode,
            'action' => $action,
            'triggered_socket' => $triggeredSocket,
            'threshold' => $threshold,
            'measured_value' => $value,
            'published_relays' => $published,
            'message' => $message,
        ];
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
