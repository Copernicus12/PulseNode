<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

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
            $reading = self::where('date', $date->toDateString())->first();

            $days[] = [
                'date'      => $date->toDateString(),
                'day_short' => strtoupper(substr($date->format('D'), 0, 3)),
                'is_today'  => $date->isToday(),
                'socket_1'  => $reading ? round($reading->energy_socket_1, 4) : 0,
                'socket_2'  => $reading ? round($reading->energy_socket_2, 4) : 0,
                'socket_3'  => $reading ? round($reading->energy_socket_3, 4) : 0,
                'total'     => $reading ? round($reading->energy_total, 4) : 0,
            ];
        }

        return $days;
    }

    public static function historyPayload(): array
    {
        $week = self::weeklyData();
        $today = collect($week)->firstWhere('is_today', true);

        return [
            'week' => $week,
            'today_progress_kwh' => round((float) ($today['total'] ?? 0), 4),
        ];
    }

    public static function dayDetails(string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $dayKey = $day->toDateString();

        /** @var self|null $reading */
        $reading = self::query()->where('date', $dayKey)->first();
        $samples = EnergySample::query()
            ->whereDate('sampled_at', $dayKey)
            ->orderBy('sampled_at')
            ->get();

        $total = (float) ($reading->energy_total ?? 0);
        $socket1 = (float) ($reading->energy_socket_1 ?? 0);
        $socket2 = (float) ($reading->energy_socket_2 ?? 0);
        $socket3 = (float) ($reading->energy_socket_3 ?? 0);

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

        $hourly = collect(range(0, 23))->map(function (int $hour) use ($samples): array {
            /** @var Collection<int, EnergySample> $items */
            $items = $samples->where('hour', $hour)->values();

            return [
                'hour' => sprintf('%02d:00', $hour),
                'energy_kwh' => round((float) $items->sum('delta_energy'), 4),
                'avg_power_w' => round((float) ($items->avg('power') ?? 0), 1),
                'peak_power_w' => round((float) ($items->max('power') ?? 0), 1),
                'warnings' => [
                    'high' => $items->where('warning_level', 'high')->count(),
                    'overload' => $items->where('warning_level', 'overload')->count(),
                ],
            ];
        })->toArray();

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
    }

    /**
     * @param Collection<int, EnergySample> $samples
     */
    private static function buildIntervals(Collection $samples, float $sampleMinutes): array
    {
        $intervals = [];
        $active = null;

        foreach ($samples as $sample) {
            $isActive = (float) $sample->power >= 50;

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
                $active['sum_power'] += (float) $sample->power;
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
                    'start' => Carbon::parse($item['start'])->format('H:i'),
                    'end' => Carbon::parse($item['end'])->format('H:i'),
                    'duration_minutes' => $duration,
                    'energy_kwh' => round((float) $item['energy_kwh'], 4),
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
}
