<?php

namespace App\Models;

use App\Support\MongoConnection;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

class EnergyReading extends Model
{
    protected $fillable = [
        'date',
        'energy_socket_1',
        'energy_socket_2',
        'energy_socket_3',
        'energy_total',
    ];

    protected function casts(): array
    {
        return [
            'date'            => 'date',
            'energy_socket_1' => 'float',
            'energy_socket_2' => 'float',
            'energy_socket_3' => 'float',
            'energy_total'    => 'float',
        ];
    }

    public static function weeklyData(): array
    {
        $startOfWeek = now()->startOfWeek(); // Monday
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $stats = self::dailyEnergyStats($date->toDateString());

            $days[] = [
                'date'      => $date->toDateString(),
                'day_short' => strtoupper(substr($date->format('D'), 0, 3)),
                'is_today'  => $date->isToday(),
                'socket_1'  => round((float) ($stats['socket_1'] ?? 0), 4),
                'socket_2'  => round((float) ($stats['socket_2'] ?? 0), 4),
                'socket_3'  => round((float) ($stats['socket_3'] ?? 0), 4),
                'total'     => round((float) ($stats['total'] ?? 0), 4),
            ];
        }

        return $days;
    }

    public static function historyPayload(): array
    {
        return Cache::remember('energy.history_payload', now()->addSeconds(20), function (): array {
            $week = self::weeklyData();
            $today = collect($week)->firstWhere('is_today', true);

            return [
                'week' => $week,
                'today_progress_kwh' => round((float) ($today['total'] ?? 0), 4),
            ];
        });
    }

    public static function dayDetails(string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $dayKey = $day->toDateString();
        $ttl = $day->isToday() ? now()->addSeconds(20) : now()->addHours(12);

        return Cache::remember("energy.day_details.{$dayKey}", $ttl, function () use ($day, $dayKey): array {
            $samples = self::mongoSamplesForDate($dayKey);

            $socket1 = (float) $samples->sum('energy_socket_1');
            $socket2 = (float) $samples->sum('energy_socket_2');
            $socket3 = (float) $samples->sum('energy_socket_3');
            $total = $socket1 + $socket2 + $socket3;

            $sampleMinutes = (float) config('esp32.analytics.sample_minutes', 0.1667);
            $avgVoltage = max(1.0, (float) ($samples->avg('voltage') ?? 230));

            $socketStats = [
                [
                    'name' => 'Socket 1',
                    'energy_kwh' => round($socket1, 4),
                    'percentage' => $total > 0 ? round(($socket1 / $total) * 100, 1) : 0,
                    'avg_power_w' => round((float) ($samples->avg('power_socket_1') ?? 0), 1),
                    'peak_power_w' => round((float) ($samples->max('power_socket_1') ?? 0), 1),
                    'active_minutes' => round((float) $samples->where('current_1', '>', 0.05)->count() * $sampleMinutes, 1),
                ],
                [
                    'name' => 'Socket 2',
                    'energy_kwh' => round($socket2, 4),
                    'percentage' => $total > 0 ? round(($socket2 / $total) * 100, 1) : 0,
                    'avg_power_w' => round((float) ($samples->avg('power_socket_2') ?? 0), 1),
                    'peak_power_w' => round((float) ($samples->max('power_socket_2') ?? 0), 1),
                    'active_minutes' => round((float) $samples->where('current_2', '>', 0.05)->count() * $sampleMinutes, 1),
                ],
                [
                    'name' => 'Socket 3',
                    'energy_kwh' => round($socket3, 4),
                    'percentage' => $total > 0 ? round(($socket3 / $total) * 100, 1) : 0,
                    'avg_power_w' => round((float) ($samples->avg('power_socket_3') ?? 0), 1),
                    'peak_power_w' => round((float) ($samples->max('power_socket_3') ?? 0), 1),
                    'active_minutes' => round((float) $samples->where('current_3', '>', 0.05)->count() * $sampleMinutes, 1),
                ],
            ];

            $hourly = self::buildHourlyBreakdown($samples);

            return [
                'date' => $dayKey,
                'day_short' => strtoupper(substr($day->format('D'), 0, 3)),
                'is_today' => $day->isToday(),
                'total_kwh' => round($total, 4),
                'from_time' => '00:00',
                'to_time' => $day->isToday() ? now()->format('H:i:s') : '23:59:59',
                'avg_voltage' => round($avgVoltage, 1),
                'socket_stats' => $socketStats,
                'warnings' => [
                    'high' => $samples->where('warning_level', 'high')->count(),
                    'overload' => $samples->where('warning_level', 'overload')->count(),
                ],
                'intervals' => self::buildIntervals($samples, $sampleMinutes),
                'hourly' => $hourly,
            ];
        });
    }

    public static function recentSamples(int $limit = 180): Collection
    {
        $collection = self::mongoCollection();
        if ($collection === null) {
            return collect();
        }

        $cursor = $collection->find([], [
            'sort' => ['received_at' => -1],
            'limit' => max(1, $limit),
        ]);

        $docs = [];
        foreach ($cursor as $doc) {
            $docs[] = $doc;
        }

        return self::hydrateMongoSamples(collect($docs)->reverse()->values());
    }

    public static function oldestDate(): ?string
    {
        $collection = self::mongoCollection();
        if ($collection === null) {
            return null;
        }

        $doc = $collection->findOne([], ['sort' => ['received_at' => 1]]);
        if ($doc === null || ! isset($doc['received_at']) || ! $doc['received_at'] instanceof UTCDateTime) {
            return null;
        }

        return self::toAppTimezone($doc['received_at'])->toDateString();
    }

    private static function mongoCollection(): ?\MongoDB\Collection
    {
        return MongoConnection::selectCollection((string) config('esp32.mongodb.collection', 'readings'));
    }

    private static function mongoSamplesForDate(string $date): Collection
    {
        $collection = self::mongoCollection();
        if ($collection === null) {
            return collect();
        }

        $start = Carbon::parse($date)->startOfDay();
        $end = $start->copy()->endOfDay();
        $startMs = (int) $start->valueOf();
        $endMs = (int) $end->valueOf();

        $cursor = $collection->find([
            'received_at' => [
                '$gte' => new UTCDateTime($startMs),
                '$lte' => new UTCDateTime($endMs),
            ],
        ], [
            'sort' => ['received_at' => 1],
        ]);

        $docs = [];
        foreach ($cursor as $doc) {
            $docs[] = $doc;
        }

        return self::hydrateMongoSamples(collect($docs));
    }

    private static function hydrateMongoSamples(Collection $docs): Collection
    {
        $samples = collect();
        $previousEnergy = null;

        foreach ($docs as $doc) {
            $payload = self::payloadToArray($doc['payload'] ?? null);
            if ($payload === null) {
                continue;
            }

            $sampledAt = now();
            if (isset($doc['received_at']) && $doc['received_at'] instanceof UTCDateTime) {
                $sampledAt = self::toAppTimezone($doc['received_at']);
            }

            $energy = max(0.0, (float) ($payload['energy'] ?? 0));
            $delta = 0.0;
            if ($previousEnergy !== null && $energy >= $previousEnergy && ($energy - $previousEnergy) < 2.0) {
                $delta = $energy - $previousEnergy;
            }
            $previousEnergy = $energy;

            $c1 = max(0.0, (float) ($payload['current_1'] ?? 0));
            $c2 = max(0.0, (float) ($payload['current_2'] ?? 0));
            $c3 = max(0.0, (float) ($payload['current_3'] ?? 0));
            $sum = $c1 + $c2 + $c3;

            if ($sum > 0.0001) {
                $r1 = $c1 / $sum;
                $r2 = $c2 / $sum;
                $r3 = $c3 / $sum;
            } else {
                $r1 = $r2 = $r3 = 1 / 3;
            }

            $voltage = max(0.0, (float) ($payload['voltage'] ?? 0));
            $power = max(0.0, (float) ($payload['power'] ?? 0));
            $warning = $power > 2500 ? 'overload' : ($power > 1800 ? 'high' : 'normal');

            $samples->push((object) [
                'hour' => (int) $sampledAt->format('H'),
                'sampled_at' => $sampledAt,
                'delta_energy' => $delta,
                'energy_socket_1' => $delta * $r1,
                'energy_socket_2' => $delta * $r2,
                'energy_socket_3' => $delta * $r3,
                'voltage' => $voltage,
                'power' => $power,
                'power_socket_1' => max(0.0, (float) ($payload['power_1'] ?? ($voltage * $c1))),
                'power_socket_2' => max(0.0, (float) ($payload['power_2'] ?? ($voltage * $c2))),
                'power_socket_3' => max(0.0, (float) ($payload['power_3'] ?? ($voltage * $c3))),
                'current' => max(0.0, (float) ($payload['current'] ?? 0)),
                'current_1' => $c1,
                'current_2' => $c2,
                'current_3' => $c3,
                'warning_level' => $warning,
            ]);
        }

        return $samples;
    }

    private static function payloadToArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof BSONDocument || $value instanceof BSONArray) {
            return $value->getArrayCopy();
        }

        return null;
    }

    private static function dailyEnergyStats(string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $dayKey = $day->toDateString();
        $ttl = $day->isToday() ? now()->addSeconds(20) : now()->addHours(12);

        return Cache::remember("energy.daily_stats.{$dayKey}", $ttl, function () use ($dayKey): array {
            $samples = self::mongoSamplesForDate($dayKey);
            if ($samples->isEmpty()) {
                return ['socket_1' => 0.0, 'socket_2' => 0.0, 'socket_3' => 0.0, 'total' => 0.0];
            }

            $s1 = (float) $samples->sum('energy_socket_1');
            $s2 = (float) $samples->sum('energy_socket_2');
            $s3 = (float) $samples->sum('energy_socket_3');

            return [
                'socket_1' => $s1,
                'socket_2' => $s2,
                'socket_3' => $s3,
                'total' => $s1 + $s2 + $s3,
            ];
        });
    }

    /**
     * @param Collection<int, object> $samples
     */
    private static function buildHourlyBreakdown(Collection $samples): array
    {
        $hourBuckets = [];
        $minuteBuckets = [];
        $secondBuckets = [];

        foreach ($samples as $sample) {
            $sampledAt = self::toAppTimezone($sample->sampled_at ?? now());

            $hour = (int) $sampledAt->format('H');
            $minute = (int) $sampledAt->format('i');
            $second = (int) $sampledAt->format('s');

            $hourBuckets[$hour][] = $sample;
            $minuteBuckets[$hour][$minute][] = $sample;
            $secondBuckets[$hour][$minute][$second][] = $sample;
        }

        return collect(range(0, 23))
            ->map(function (int $hour) use ($hourBuckets, $minuteBuckets, $secondBuckets): array {
                $hourItems = collect($hourBuckets[$hour] ?? []);
                $hourSummary = self::summarizeSamples($hourItems);

                return [
                    'hour' => sprintf('%02d:00', $hour),
                    ...$hourSummary,
                    'minutes' => collect(range(0, 59))
                        ->map(function (int $minute) use ($hour, $minuteBuckets, $secondBuckets): array {
                            $minuteItems = collect($minuteBuckets[$hour][$minute] ?? []);
                            $minuteSummary = self::summarizeSamples($minuteItems);

                            return [
                                'minute' => sprintf('%02d:%02d', $hour, $minute),
                                ...$minuteSummary,
                                'seconds' => collect($secondBuckets[$hour][$minute] ?? [])
                                    ->sortKeys()
                                    ->map(function (array $secondItems, int $second) use ($hour, $minute): array {
                                        return [
                                            'second' => sprintf('%02d:%02d:%02d', $hour, $minute, $second),
                                            ...self::summarizeSamples(collect($secondItems)),
                                        ];
                                    })
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @param Collection<int, object> $samples
     */
    private static function summarizeSamples(Collection $samples): array
    {
        $powerSamples = $samples->map(function ($sample): float {
            return max(0.0, (float) ($sample->power ?? 0));
        });

        return [
            'energy_kwh' => round((float) $samples->sum('delta_energy'), 6),
            'avg_power_w' => round((float) ($powerSamples->avg() ?? 0), 1),
            'peak_power_w' => round((float) ($powerSamples->max() ?? 0), 1),
            'warnings' => self::warningCounters($samples),
        ];
    }

    /**
     * @param Collection<int, object> $samples
     */
    private static function warningCounters(Collection $samples): array
    {
        return [
            'high' => $samples->where('warning_level', 'high')->count(),
            'overload' => $samples->where('warning_level', 'overload')->count(),
        ];
    }

    /**
     * @param Collection<int, EnergySample> $samples
     */
    private static function buildIntervals(Collection $samples, float $sampleMinutes): array
    {
        $intervals = [];
        $active = null;

        foreach ($samples as $sample) {
            $power = max(0.0, (float) $sample->power);
            $isActive = $power >= 50;

            if ($isActive && $active === null) {
                $active = [
                    'start' => $sample->sampled_at,
                    'end' => $sample->sampled_at,
                    'energy_kwh' => 0.0,
                    'samples' => 0,
                    'sum_power' => 0.0,
                ];
            }

            if ($isActive && $active !== null) {
                $active['end'] = $sample->sampled_at;
                $active['energy_kwh'] += (float) $sample->delta_energy;
                $active['samples']++;
                $active['sum_power'] += $power;
            }

            if (! $isActive && $active !== null) {
                $intervals[] = $active;
                $active = null;
            }
        }

        if ($active !== null) {
            $intervals[] = $active;
        }

        return collect($intervals)
            ->map(function (array $item) use ($sampleMinutes): array {
                $duration = max(1, (int) round($item['samples'] * $sampleMinutes));

                return [
                    'start' => self::toAppTimezone($item['start'])->format('H:i'),
                    'end' => self::toAppTimezone($item['end'])->format('H:i'),
                    'duration_minutes' => $duration,
                    'energy_kwh' => round((float) $item['energy_kwh'], 6),
                    'avg_power_w' => $item['samples'] > 0
                        ? round((float) $item['sum_power'] / $item['samples'], 1)
                        : 0,
                ];
            })
            ->sortByDesc('energy_kwh')
            ->take(6)
            ->values()
            ->all();
    }

    private static function toAppTimezone(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone(config('app.timezone'));
        }

        if ($value instanceof UTCDateTime) {
            return Carbon::instance($value->toDateTime())
                ->setTimezone(config('app.timezone'));
        }

        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
