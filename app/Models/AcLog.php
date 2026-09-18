<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class AcLog extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'ac_logs';

    protected $fillable = [
        'device_id',
        'active_ac',
        'ac_number',
        'state',
        'current_ampere',
        'recorded_at',
    ];

    protected $casts = [
        'ac_number'      => 'integer',
        'recorded_at'    => 'datetime',
        'current_ampere' => 'float',
    ];

    /**
     * Scope query to logs older than given days.
     */
    public function scopeOlderThan($query, int $days)
    {
        $cutoff = now()->subDays($days);
        return $query->where('recorded_at', '<', $cutoff);
    }

    /**
     * Scope query for a specific device.
     */
    public function scopeForDevice($query, ?string $deviceId)
    {
        if ($deviceId && $deviceId !== 'all') {
            return $query->where('device_id', $deviceId);
        }
        return $query;
    }
}
