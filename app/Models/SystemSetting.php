<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SystemSetting extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'system_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting by key with a default fallback.
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, $value)
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
