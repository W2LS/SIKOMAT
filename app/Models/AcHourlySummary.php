<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class AcHourlySummary extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'ac_hourly_summaries';

    protected $fillable = [
        'device_id',
        'ac_number',
        'hour_bucket',       // DateTime at start of the hour (e.g. 2026-09-11 10:00:00)
        'avg_current',       // Average Ampere in this hour
        'min_current',       // Minimum Ampere recorded
        'max_current',       // Peak Ampere recorded
        'avg_watt',          // Average Watt (avg_current * 220)
        'min_watt',          // Minimum Watt
        'max_watt',          // Peak Watt
        'est_kwh',           // Estimated energy consumption for this hour (avg_watt * 1h / 1000)
        'sample_count',      // Total telemetry points in this hour
        'on_sample_count',   // Number of samples when AC was ON
        'off_sample_count',  // Number of samples when AC was OFF
        'uptime_percentage', // Compressor duty cycle % (on_samples / sample_count * 100)
    ];

    protected $casts = [
        'ac_number'         => 'integer',
        'hour_bucket'       => 'datetime',
        'avg_current'       => 'float',
        'min_current'       => 'float',
        'max_current'       => 'float',
        'avg_watt'          => 'float',
        'min_watt'          => 'float',
        'max_watt'          => 'float',
        'est_kwh'           => 'float',
        'sample_count'      => 'integer',
        'on_sample_count'   => 'integer',
        'off_sample_count'  => 'integer',
        'uptime_percentage' => 'float',
    ];
}
