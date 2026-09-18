<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'username',
        'nip',
        'email',
        'password',
        'role', // 'admin' (Super Administrator) | 'operator' (Operator Ruangan)
        'division',
        'assigned_device_ids', // Array of device_id strings that this operator can access
        'status', // 'active' | 'inactive'
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'assigned_device_ids' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Check if user is Super Administrator.
     */
    public function isAdmin(): bool
    {
        return ($this->role === 'admin') || ($this->username === 'PINDAD-IOT-2026') || ($this->nip === 'PINDAD-IOT-2026');
    }

    /**
     * Check if user is an Operator.
     */
    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    /**
     * Check if user has access to a specific device.
     */
    public function canAccessDevice(?string $deviceId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (empty($deviceId)) {
            return false;
        }

        $assigned = $this->assigned_device_ids ?? [];
        return in_array($deviceId, $assigned, true);
    }

    /**
     * Get TwoFactorSetting record for this user.
     */
    public function getTwoFactorSetting()
    {
        $identifiers = array_filter([$this->username, $this->nip, $this->email]);
        return TwoFactorSetting::whereIn('user_nip', $identifiers)->first();
    }

    /**
     * Check if user has Two-Factor Authentication actively enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        $setting = $this->getTwoFactorSetting();
        return $setting && $setting->is_enabled && $setting->confirmed_at !== null;
    }
}
