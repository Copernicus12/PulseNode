<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'notes',
        'expected_power_min',
        'expected_power_max',
        'avg_power_w',
        'peak_power_w',
        'avg_current_a',
        'variability_pct',
        'startup_ratio',
        'signature_snapshot',
        'trained_from_socket',
        'last_trained_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_power_min' => 'float',
            'expected_power_max' => 'float',
            'avg_power_w' => 'float',
            'peak_power_w' => 'float',
            'avg_current_a' => 'float',
            'variability_pct' => 'float',
            'startup_ratio' => 'float',
            'signature_snapshot' => 'array',
            'last_trained_at' => 'datetime',
        ];
    }

    public function detections(): HasMany
    {
        return $this->hasMany(DeviceDetection::class);
    }
}
