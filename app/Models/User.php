<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'role',
        'year_level',
        'section_block',
        'status',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'full_name',
        'formal_name',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get formal name accessor with uppercase LAST NAME, FIRST NAME M.I.
     * e.g. "LA ROSA, CHRISTIAN PAUL G." or "ABAYABAY, OMAR B."
     */
    public function getFormalNameAttribute(): string
    {
        $last = strtoupper(trim($this->last_name ?? ''));
        $first = strtoupper(trim($this->first_name ?? ''));
        $mi = '';
        if (!empty($this->middle_name) && trim($this->middle_name) !== '' && strtolower(trim($this->middle_name)) !== 'n/a') {
            $firstChar = strtoupper(substr(trim($this->middle_name), 0, 1));
            $mi = ' ' . $firstChar . '.';
        }

        if (empty($last)) {
            return $first . $mi;
        }

        return "{$last}, {$first}{$mi}";
    }

    /**
     * Get full name accessor with middle initial (e.g. "Earl Lawrence T. Baratas").
     */
    public function getFullNameAttribute(): string
    {
        $mi = '';
        if (!empty($this->middle_name) && trim($this->middle_name) !== '' && strtolower(trim($this->middle_name)) !== 'n/a') {
            $firstChar = strtoupper(substr(trim($this->middle_name), 0, 1));
            $mi = $firstChar . '.';
        }

        $parts = array_filter([$this->first_name, $mi, $this->last_name], function ($p) {
            return $p !== null && $p !== '';
        });

        return implode(' ', $parts);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function activeDevice(): HasMany
    {
        return $this->hasMany(Device::class)->where('status', 'active');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function deviceResetRequests(): HasMany
    {
        return $this->hasMany(DeviceResetRequest::class);
    }

    public function onboardingTokens(): HasMany
    {
        return $this->hasMany(OnboardingToken::class);
    }

    public function assignedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_staff', 'user_id', 'event_id')->withTimestamps();
    }
}
