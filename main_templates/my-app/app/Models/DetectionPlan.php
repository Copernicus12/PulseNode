<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class DetectionPlan extends Model
{
    /** @use HasFactory<\Database\Factories\DetectionPlanFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'detection_plans';

    protected $fillable = [
        'name',
        'strategy',
        'socket_scope',
        'window_samples',
        'min_samples',
        'match_threshold',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'socket_scope' => 'integer',
            'window_samples' => 'integer',
            'min_samples' => 'integer',
            'match_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function detections(): HasMany
    {
        return $this->hasMany(DeviceDetection::class, 'detection_plan_id');
    }
}
