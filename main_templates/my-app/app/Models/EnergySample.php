<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class EnergySample extends Model
{
    protected $fillable = [
        'date',
        'hour',
        'sampled_at',
        'energy_abs',
        'delta_energy',
        'energy_socket_1',
        'energy_socket_2',
        'energy_socket_3',
        'voltage',
        'power',
        'power_socket_1',
        'power_socket_2',
        'power_socket_3',
        'current',
        'current_1',
        'current_2',
        'current_3',
        'warning_level',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'energy_abs' => 'float',
            'delta_energy' => 'float',
            'energy_socket_1' => 'float',
            'energy_socket_2' => 'float',
            'energy_socket_3' => 'float',
            'voltage' => 'float',
            'power' => 'float',
            'power_socket_1' => 'float',
            'power_socket_2' => 'float',
            'power_socket_3' => 'float',
            'current' => 'float',
            'current_1' => 'float',
            'current_2' => 'float',
            'current_3' => 'float',
        ];
    }

    public static function recordFromLatest(array $latest): void
    {
        $now = now();

        $energyAbs = max(0.0, (float) ($latest['energy'] ?? 0));
        $voltage = max(0.0, (float) ($latest['voltage'] ?? 0));
        $power = max(0.0, (float) ($latest['power'] ?? 0));
        $current = max(0.0, (float) ($latest['current'] ?? 0));
        $c1 = max(0.0, (float) ($latest['current_1'] ?? 0));
        $c2 = max(0.0, (float) ($latest['current_2'] ?? 0));
        $c3 = max(0.0, (float) ($latest['current_3'] ?? 0));

        $previous = self::query()->latest('sampled_at')->first();
        $deltaEnergy = 0.0;

        if ($previous !== null
            && $previous->date === $now->toDateString()
            && $energyAbs >= (float) $previous->energy_abs
            && ($energyAbs - (float) $previous->energy_abs) < 2.0
        ) {
            $deltaEnergy = $energyAbs - (float) $previous->energy_abs;
        }

        $sumCurrents = $c1 + $c2 + $c3;
        if ($sumCurrents > 0.0001) {
            $r1 = $c1 / $sumCurrents;
            $r2 = $c2 / $sumCurrents;
            $r3 = $c3 / $sumCurrents;
        } else {
            $r1 = $r2 = $r3 = 1 / 3;
        }

        $s1Delta = $deltaEnergy * $r1;
        $s2Delta = $deltaEnergy * $r2;
        $s3Delta = $deltaEnergy * $r3;

        $p1 = $voltage * $c1;
        $p2 = $voltage * $c2;
        $p3 = $voltage * $c3;

        $warningLevel = $power > 2500 ? 'overload' : ($power > 1800 ? 'high' : 'normal');

        self::query()->create([
            'date' => $now->toDateString(),
            'hour' => (int) $now->format('H'),
            'sampled_at' => $now,
            'energy_abs' => round($energyAbs, 6),
            'delta_energy' => round($deltaEnergy, 6),
            'energy_socket_1' => round($s1Delta, 6),
            'energy_socket_2' => round($s2Delta, 6),
            'energy_socket_3' => round($s3Delta, 6),
            'voltage' => round($voltage, 3),
            'power' => round($power, 3),
            'power_socket_1' => round($p1, 3),
            'power_socket_2' => round($p2, 3),
            'power_socket_3' => round($p3, 3),
            'current' => round($current, 6),
            'current_1' => round($c1, 6),
            'current_2' => round($c2, 6),
            'current_3' => round($c3, 6),
            'warning_level' => $warningLevel,
        ]);

        $reading = EnergyReading::query()
            ->whereDate('date', $now->toDateString())
            ->first();

        if ($reading === null) {
            try {
                $reading = EnergyReading::query()->create([
                    'date' => $now->toDateString(),
                    'energy_socket_1' => 0,
                    'energy_socket_2' => 0,
                    'energy_socket_3' => 0,
                    'energy_total' => 0,
                ]);
            } catch (UniqueConstraintViolationException) {
                $reading = EnergyReading::query()
                    ->whereDate('date', $now->toDateString())
                    ->firstOrFail();
            }
        }

        $reading->update([
            'energy_socket_1' => round((float) $reading->energy_socket_1 + $s1Delta, 6),
            'energy_socket_2' => round((float) $reading->energy_socket_2 + $s2Delta, 6),
            'energy_socket_3' => round((float) $reading->energy_socket_3 + $s3Delta, 6),
            'energy_total' => round((float) $reading->energy_total + $deltaEnergy, 6),
        ]);
    }
}
