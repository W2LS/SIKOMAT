<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Template extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'templates';

    protected $fillable = [
        'user_nip',
        'name',
        'hardware_type',
        'connection_type',
        'description',
        'icon',
        'datastreams', // Array of objects: ['pin' => 'V0', 'name' => 'Relay 1', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'unit' => '']
    ];

    protected $casts = [
        'datastreams' => 'array',
    ];
}
