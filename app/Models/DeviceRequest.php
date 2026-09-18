<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class DeviceRequest extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'device_requests';

    protected $fillable = [
        'operator_id',
        'operator_username',
        'operator_name',
        'operator_division',
        'room_name',
        'location',
        'request_type', // 'new_device' (Pengadaan Node Baru) | 'access_existing' (Izin Akses Ruangan)
        'notes',
        'status', // 'pending' | 'approved' | 'rejected'
        'admin_notes',
        'assigned_device_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Scope for pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if request is still pending.
     */
    public function isPending(): bool
    {
        return ($this->status ?? 'pending') === 'pending';
    }

    /**
     * Check if request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
