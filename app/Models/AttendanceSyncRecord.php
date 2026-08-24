<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSyncRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'staff_id',
        'local_record_id',
        'scan_time',
        'latitude',
        'longitude',
        'device_credential',
        'override_reason',
        'sync_status',
        'sync_error',
        'synced_at',
    ];

    protected $casts = [
        'scan_time' => 'datetime',
        'synced_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
