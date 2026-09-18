<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Schedule extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'schedules';

    protected $fillable = [
        'user_nip',
        'label',
        'start_time',
        'end_time',
        'target_ac',
        'is_active',
        'device_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
