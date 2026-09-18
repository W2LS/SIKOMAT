<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Device extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'devices';

    protected $fillable = [
        'user_nip', // Owner account NIP / Username (User-scoping)
        'device_id',
        'template_id',
        'name',
        'type', // 'ac_monitoring', 'smart_lighting', 'datacenter', 'general_iot'
        'location',
        'ip_address',
        'hardware_type',
        'status',
        'icon',
        'auth_token',
        'num_ac',
        'description',
        'current_values', // Store latest datastream values like ['V0' => 1, 'V1' => 0, 'V2' => 0.0]
    ];

    protected $casts = [
        'num_ac' => 'integer',
        'current_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id', '_id');
    }
}
