<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceDetection extends Model
{
    use HasFactory;

    protected $fillable = [
        'socket_index',
        'device_profile_id',
        'predicted_label',
        'predicted_category',
        'confidence',
        'signature_snapshot',
        'detected_at',
        'last_seen_at',
        'released_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'signature_snapshot' => 'array',
            'detected_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DeviceProfile::class, 'device_profile_id');
    }
}
