<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class SocketSchedule extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'socket_schedules';

    protected $fillable = [
        'name',
        'socket_index',
        'action',
        'days_of_week',
        'start_time',
        'end_time',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'socket_index' => 'integer',
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
