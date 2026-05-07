<?php

namespace App\Support;

class NotificationCenter
{
    public function __construct(private AppNotificationStore $store)
    {
    }

    public function relayCommandBlocked(int $relayId, array $guard): void
    {
        $this->store([
            'type' => 'relay_blocked',
            'level' => 'warning',
            'title' => 'Socket '.$relayId.' command blocked',
            'message' => (string) ($guard['message'] ?? 'Power-on is unavailable because no recent ESP32 telemetry was received.'),
            'action_url' => $this->routePath('power-strip.index'),
            'meta' => [
                'relay_id' => $relayId,
                'reason' => $guard['reason'] ?? null,
            ],
        ], 30);
    }

    public function relayCommandFailed(int $relayId, string $state, string $message): void
    {
        $this->store([
            'type' => 'relay_failed',
            'level' => 'error',
            'title' => 'Socket '.$relayId.' failed to turn '.strtoupper($state),
            'message' => $message,
            'action_url' => $this->routePath('power-strip.index'),
            'meta' => [
                'relay_id' => $relayId,
                'state' => $state,
            ],
        ], 20);
    }

    public function relayCommandSent(int $relayId, string $state): void
    {
        $this->store([
            'type' => 'relay_sent',
            'level' => $state === 'on' ? 'success' : 'info',
            'title' => 'Socket '.$relayId.' turned '.strtoupper($state),
            'message' => 'The relay command was published to MQTT successfully.',
            'action_url' => $this->routePath('power-strip.index'),
            'meta' => [
                'relay_id' => $relayId,
                'state' => $state,
            ],
        ]);
    }

    public function deviceProfileCreated(string $profileName, int $socketIndex, string $actionRoute = 'devices.index'): void
    {
        $this->store([
            'type' => 'device_profile_created',
            'level' => 'success',
            'title' => 'Device profile created',
            'message' => 'Profile "'.$profileName.'" was trained from socket '.$socketIndex.'.',
            'action_url' => $this->routePath($actionRoute),
            'meta' => [
                'profile_name' => $profileName,
                'socket_index' => $socketIndex,
            ],
        ]);
    }

    public function deviceProfileDeleted(string $profileName, string $actionRoute = 'devices.index'): void
    {
        $this->store([
            'type' => 'device_profile_deleted',
            'level' => 'success',
            'title' => 'Device profile deleted',
            'message' => 'Profile "'.$profileName.'" was permanently deleted.',
            'action_url' => $this->routePath($actionRoute),
            'meta' => [
                'profile_name' => $profileName,
            ],
        ]);
    }

    public function mqttRestarted(): void
    {
        $this->store([
            'type' => 'mqtt_restart',
            'level' => 'info',
            'title' => 'MQTT listener restart requested',
            'message' => 'The background listener restart command was sent.',
            'action_url' => $this->routePath('power-strip-diagnostics.edit'),
        ], 30);
    }

    public function mqttRestartFailed(string $message): void
    {
        $this->store([
            'type' => 'mqtt_restart_failed',
            'level' => 'error',
            'title' => 'MQTT listener restart failed',
            'message' => $message,
            'action_url' => $this->routePath('power-strip-diagnostics.edit'),
        ], 30);
    }

    public function accountRequestSubmitted(string $name, string $email, string $userId): void
    {
        $this->store([
            'type' => 'account_request_created',
            'level' => 'warning',
            'title' => 'New account request',
            'message' => $name.' ('.$email.') requested access and is waiting for approval.',
            'action_url' => $this->routePath('accounts.index'),
            'meta' => [
                'user_id' => $userId,
                'email' => $email,
                'name' => $name,
                'status' => 'pending',
            ],
        ], 120);
    }

    public function accountRequestApproved(string $name, string $email, string $userId): void
    {
        $this->store([
            'type' => 'account_request_approved',
            'level' => 'success',
            'title' => 'Account request approved',
            'message' => $name.' ('.$email.') can now log in.',
            'action_url' => $this->routePath('accounts.index'),
            'meta' => [
                'user_id' => $userId,
                'email' => $email,
                'name' => $name,
                'status' => 'active',
            ],
        ], 120);
    }

    public function accountRequestRejected(string $name, string $email, string $userId): void
    {
        $this->store([
            'type' => 'account_request_rejected',
            'level' => 'error',
            'title' => 'Account request rejected',
            'message' => $name.' ('.$email.') was declined by an administrator.',
            'action_url' => $this->routePath('accounts.index'),
            'meta' => [
                'user_id' => $userId,
                'email' => $email,
                'name' => $name,
                'status' => 'rejected',
            ],
        ], 120);
    }

    public function recordTelemetryUpdate(array $previous, array $latest, Esp32ConnectionHealth $connectionHealth): void
    {
        $wasOnline = $connectionHealth->isOnline($previous);
        $isOnline = $connectionHealth->isOnline($latest);

        if (! $wasOnline && $isOnline) {
            $this->store([
                'type' => 'telemetry_restored',
                'level' => 'success',
                'title' => 'ESP32 telemetry is back online',
                'message' => 'Fresh readings are arriving again from the power strip.',
                'action_url' => $this->routePath('dashboard'),
            ], 120);
        }

        $previousBand = $this->powerBand((float) ($previous['power'] ?? 0));
        $currentPower = (float) ($latest['power'] ?? 0);
        $currentBand = $this->powerBand($currentPower);

        if ($previousBand === $currentBand) {
            return;
        }

        if ($currentBand === 'high') {
            $this->store([
                'type' => 'power_high',
                'level' => 'warning',
                'title' => 'High load detected',
                'message' => 'System consumption reached '.number_format($currentPower, 1).' W and is approaching the configured limit.',
                'action_url' => $this->routePath('power-strip.index'),
            ], 120);

            return;
        }

        if ($currentBand === 'overload') {
            $this->store([
                'type' => 'power_overload',
                'level' => 'error',
                'title' => 'Overload risk detected',
                'message' => 'System consumption reached '.number_format($currentPower, 1).' W. Check active sockets now.',
                'action_url' => $this->routePath('power-strip.index'),
            ], 120);

            return;
        }

        if ($previousBand !== 'normal') {
            $this->store([
                'type' => 'power_normalized',
                'level' => 'success',
                'title' => 'Power load returned to normal',
                'message' => 'Current draw dropped back to '.number_format($currentPower, 1).' W.',
                'action_url' => $this->routePath('dashboard'),
            ], 120);
        }
    }

    private function powerBand(float $power): string
    {
        if ($power > 2500) {
            return 'overload';
        }

        if ($power > 1800) {
            return 'high';
        }

        return 'normal';
    }

    private function store(array $payload, int $suppressForSeconds = 0): void
    {
        $this->store->store($payload, $suppressForSeconds);
    }

    private function routePath(string $name): string
    {
        return route($name, [], false);
    }
}
