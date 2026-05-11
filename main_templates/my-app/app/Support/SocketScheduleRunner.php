<?php

namespace App\Support;

use App\Models\SocketSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

class SocketScheduleRunner
{
    public function __construct(
        private Esp32StateStore $store,
        private Esp32RelayPublisher $publisher,
        private Esp32ConnectionHealth $connectionHealth,
        private NotificationCenter $notifications,
        private PowerStripCommandLogStore $commandLogStore,
    ) {
    }

    public function run(?CarbonInterface $now = null): array
    {
        $latest = $this->store->latest();

        try {
            $schedules = SocketSchedule::query()
                ->where('is_active', true)
                ->get();
        } catch (Throwable) {
            $schedules = collect();
        }

        return $this->processSchedules($schedules, $latest, $now);
    }

    public function processSchedules(iterable $schedules, array $latest, ?CarbonInterface $now = null): array
    {
        $now = $this->normalizeNow($now);
        $results = [];
        $checked = 0;

        foreach ($schedules as $schedule) {
            $checked++;
            $dueAction = $this->resolveDueAction($schedule, $now);
            if ($dueAction === null) {
                continue;
            }

            $result = $this->executeSchedule($schedule, $dueAction['state'], $dueAction['event'], $now, $latest);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return [
            'checked' => $checked,
            'triggered' => count($results),
            'results' => $results,
        ];
    }

    public function resolveDueAction(object|array $schedule, CarbonInterface $now): ?array
    {
        $daysOfWeek = array_map(
            static fn ($day): string => strtolower(trim((string) $day)),
            Arr::wrap(data_get($schedule, 'days_of_week', [])),
        );

        if ($daysOfWeek === []) {
            return null;
        }

        $state = strtolower((string) data_get($schedule, 'action', ''));
        if (! in_array($state, ['on', 'off'], true)) {
            return null;
        }

        $lastTriggeredAt = data_get($schedule, 'last_triggered_at');
        $lastTriggeredEvent = (string) data_get($schedule, 'last_triggered_event', '');

        if ($this->shouldTriggerStart($schedule, $daysOfWeek, $now, $lastTriggeredAt, $lastTriggeredEvent)) {
            return [
                'event' => 'start_time',
                'state' => $state,
            ];
        }

        if ($this->shouldTriggerEnd($schedule, $daysOfWeek, $now, $lastTriggeredAt, $lastTriggeredEvent)) {
            return [
                'event' => 'end_time',
                'state' => $this->reverseAction($state),
            ];
        }

        return null;
    }

    private function executeSchedule(object|array $schedule, string $state, string $event, CarbonInterface $now, array $latest): ?array
    {
        $relayId = (int) data_get($schedule, 'socket_index', 0);
        $scheduleName = (string) data_get($schedule, 'name', 'Scheduled rule');

        if (! in_array($relayId, [1, 2, 3], true)) {
            $this->storeCommandLog(
                'warning',
                'Skipped invalid schedule "'.$scheduleName.'" because it targets an unknown socket.',
                'schedule',
                [
                    'schedule_name' => $scheduleName,
                    'socket_index' => $relayId,
                ],
            );

            return null;
        }

        if ($state === 'on') {
            $relayGuard = $this->connectionHealth->relayCommandAvailability($latest);
            if (! $relayGuard['can_turn_on']) {
                $this->notifications->relayCommandBlocked($relayId, $relayGuard);
                $this->storeCommandLog(
                    'warning',
                    'Schedule "'.$scheduleName.'" could not turn socket '.$relayId.' ON because the latest telemetry is stale.',
                    'schedule',
                    [
                        'schedule_name' => $scheduleName,
                        'socket_index' => $relayId,
                        'state' => $state,
                        'reason' => $relayGuard['reason'] ?? null,
                    ],
                );

                return [
                    'schedule' => $scheduleName,
                    'relay_id' => $relayId,
                    'state' => $state,
                    'status' => 'blocked',
                    'event' => $event,
                ];
            }
        }

        try {
            $this->publisher->publish($relayId, $state);
        } catch (RuntimeException $exception) {
            $this->notifications->relayCommandFailed($relayId, $state, $exception->getMessage());
            $this->storeCommandLog(
                'error',
                'Schedule "'.$scheduleName.'" failed to publish socket '.$relayId.' '.strtoupper($state).' command: '.$exception->getMessage(),
                'schedule',
                [
                    'schedule_name' => $scheduleName,
                    'socket_index' => $relayId,
                    'state' => $state,
                    'error' => $exception->getMessage(),
                ],
            );

            return null;
        }

        $updatedRelayState = [
            'relay_1' => $relayId === 1 ? $state === 'on' : (bool) ($latest['relay_1'] ?? false),
            'relay_2' => $relayId === 2 ? $state === 'on' : (bool) ($latest['relay_2'] ?? false),
            'relay_3' => $relayId === 3 ? $state === 'on' : (bool) ($latest['relay_3'] ?? false),
        ];
        $this->store->updateRelayState($updatedRelayState);
        $this->notifications->relayCommandSent($relayId, $state);
        $this->storeCommandLog(
            'success',
            'Schedule "'.$scheduleName.'" switched socket '.$relayId.' '.strtoupper($state).'.',
            'schedule',
            [
                'schedule_name' => $scheduleName,
                'socket_index' => $relayId,
                'state' => $state,
                'event' => $event,
            ],
        );

        $this->markTriggered($schedule, $now, $event, $state);

        return [
            'schedule' => $scheduleName,
            'relay_id' => $relayId,
            'state' => $state,
            'status' => 'triggered',
            'event' => $event,
        ];
    }

    private function markTriggered(object|array $schedule, CarbonInterface $now, string $event, string $state): void
    {
        $scheduleId = $this->scheduleId($schedule);
        if ($scheduleId === null) {
            return;
        }

        try {
            SocketSchedule::query()
                ->whereKey($scheduleId)
                ->update([
                    'last_triggered_at' => $now->toIso8601String(),
                    'last_triggered_event' => $event,
                    'last_triggered_state' => $state,
                ]);
        } catch (Throwable) {
            // The schedule already fired; keep the command result even if persistence is unavailable.
        }
    }

    private function storeCommandLog(string $level, string $message, string $source, array $meta = []): void
    {
        $this->commandLogStore->store([
            'level' => $level,
            'message' => $message,
            'source' => $source,
            'meta' => $meta,
        ]);
    }

    private function normalizeNow(?CarbonInterface $now): CarbonInterface
    {
        return $now instanceof CarbonInterface ? Carbon::instance($now)->setTimezone(config('app.timezone')) : now();
    }

    private function timeMatches(string $expectedTime, CarbonInterface $now): bool
    {
        return $this->normalizeTime($expectedTime) === $now->format('H:i');
    }

    private function shouldTriggerStart(
        object|array $schedule,
        array $daysOfWeek,
        CarbonInterface $now,
        mixed $lastTriggeredAt,
        string $lastTriggeredEvent,
    ): bool {
        $startTime = $this->normalizeTime((string) data_get($schedule, 'start_time', ''));

        if ($startTime === '' || ! in_array($this->dayKey($now), $daysOfWeek, true)) {
            return false;
        }

        if (! $this->timeMatches($startTime, $now)) {
            return false;
        }

        if ($this->alreadyTriggeredThisMinute($lastTriggeredAt, $now, $lastTriggeredEvent, 'start_time')) {
            return false;
        }

        return true;
    }

    private function shouldTriggerEnd(
        object|array $schedule,
        array $daysOfWeek,
        CarbonInterface $now,
        mixed $lastTriggeredAt,
        string $lastTriggeredEvent,
    ): bool {
        $endTime = $this->normalizeTime((string) data_get($schedule, 'end_time', ''));
        $startTime = $this->normalizeTime((string) data_get($schedule, 'start_time', ''));

        if ($endTime === '' || $startTime === '' || $endTime === $startTime) {
            return false;
        }

        $isSameDayWindow = $endTime > $startTime;
        $todayKey = $this->dayKey($now);
        $yesterdayKey = $this->dayKey($now->copy()->subDay());

        $dayMatches = $isSameDayWindow
            ? in_array($todayKey, $daysOfWeek, true)
            : in_array($yesterdayKey, $daysOfWeek, true);

        if (! $dayMatches || ! $this->timeMatches($endTime, $now)) {
            return false;
        }

        if ($this->alreadyTriggeredThisMinute($lastTriggeredAt, $now, $lastTriggeredEvent, 'end_time')) {
            return false;
        }

        return true;
    }

    private function alreadyTriggeredThisMinute(
        mixed $lastTriggeredAt,
        CarbonInterface $now,
        string $lastTriggeredEvent,
        string $event,
    ): bool {
        if ($lastTriggeredEvent !== $event || $lastTriggeredAt === null) {
            return false;
        }

        try {
            return Carbon::parse((string) $lastTriggeredAt)->format('Y-m-d H:i') === $now->format('Y-m-d H:i');
        } catch (Throwable) {
            return false;
        }
    }

    private function reverseAction(string $state): string
    {
        return $state === 'on' ? 'off' : 'on';
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
            return '';
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return '';
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function dayKey(CarbonInterface $now): string
    {
        return match (strtolower($now->format('D'))) {
            'mon' => 'mon',
            'tue' => 'tue',
            'wed' => 'wed',
            'thu' => 'thu',
            'fri' => 'fri',
            'sat' => 'sat',
            'sun' => 'sun',
            default => strtolower($now->format('D')),
        };
    }

    private function scheduleId(object|array $schedule): ?string
    {
        $id = data_get($schedule, 'id') ?? data_get($schedule, '_id');

        if ($id === null) {
            if (is_object($schedule) && method_exists($schedule, 'getKey')) {
                $id = $schedule->getKey();
            }
        }

        if ($id === null || $id === '') {
            return null;
        }

        return (string) $id;
    }
}
