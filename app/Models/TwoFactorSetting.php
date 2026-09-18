<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TwoFactorSetting extends Model
{
    protected $table = 'two_factor_settings';

    protected $fillable = [
        'user_nip',
        'is_enabled',
        'secret_key',
        'recovery_codes',
        'confirmed_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'confirmed_at' => 'datetime',
        'recovery_codes' => 'array',
    ];

    /**
     * Enkripsi secret key otomatis sebelum simpan ke database.
     */
    public function setSecretKeyAttribute($value)
    {
        $this->attributes['secret_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Dekripsi secret key saat dibaca.
     */
    public function getSecretKeyAttribute($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}
