<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'session_type',
        'start_time',
        'end_time',
        'checkin_start_time',
        'checkin_end_time',
        'checkout_start_time',
        'checkout_end_time',
        'am_checkin_start_time',
        'am_checkin_end_time',
        'am_checkout_start_time',
        'am_checkout_end_time',
        'pm_checkin_start_time',
        'pm_checkin_end_time',
        'pm_checkout_start_time',
        'pm_checkout_end_time',
        'allow_window_bypass',
        'bypass_expires_at',
        'bypass_count',
        'bypass_reason',
        'target_year_levels',
        'venue_name',
        'venue_latitude',
        'venue_longitude',
        'allowed_radius_meters',
        'fine_amount',
        'fine_per_slot',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_time' => 'datetime:Y-m-d H:i:s',
        'end_time' => 'datetime:Y-m-d H:i:s',
        'checkin_start_time' => 'datetime:Y-m-d H:i:s',
        'checkin_end_time' => 'datetime:Y-m-d H:i:s',
        'checkout_start_time' => 'datetime:Y-m-d H:i:s',
        'checkout_end_time' => 'datetime:Y-m-d H:i:s',
        'am_checkin_start_time' => 'datetime:Y-m-d H:i:s',
        'am_checkin_end_time' => 'datetime:Y-m-d H:i:s',
        'am_checkout_start_time' => 'datetime:Y-m-d H:i:s',
        'am_checkout_end_time' => 'datetime:Y-m-d H:i:s',
        'pm_checkin_start_time' => 'datetime:Y-m-d H:i:s',
        'pm_checkin_end_time' => 'datetime:Y-m-d H:i:s',
        'pm_checkout_start_time' => 'datetime:Y-m-d H:i:s',
        'pm_checkout_end_time' => 'datetime:Y-m-d H:i:s',
        'allow_window_bypass' => 'boolean',
        'bypass_expires_at' => 'datetime:Y-m-d H:i:s',
        'bypass_count' => 'integer',
        'target_year_levels' => 'array',
        'venue_latitude' => 'float',
        'venue_longitude' => 'float',
        'allowed_radius_meters' => 'float',
        'fine_amount' => 'float',
        'fine_per_slot' => 'float',
    ];

    protected $appends = [
        'target_audience_label',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_staff', 'event_id', 'user_id')->withTimestamps();
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Determine active time window status and phase (supports 2-slot and 4-slot sessions).
     */
    public function getActiveWindowStatus(): array
    {
        if ($this->allow_window_bypass) {
            $now = now();
            if ($this->bypass_expires_at && $now->greaterThan($this->bypass_expires_at)) {
                $this->allow_window_bypass = false;
                $this->bypass_expires_at = null;
                $this->save();
            } else {
                $remainingSecs = $this->bypass_expires_at ? max(0, $now->diffInSeconds($this->bypass_expires_at, false)) : null;
                return [
                    'is_open' => true,
                    'phase' => 'bypass',
                    'label' => '⚡ Emergency Bypass Active',
                    'message' => 'Emergency Window Bypass is enabled. All scans are currently accepted.',
                    'next_time' => $this->bypass_expires_at ? $this->bypass_expires_at->toIso8601String() : null,
                    'allow_window_bypass' => true,
                    'bypass_expires_at' => $this->bypass_expires_at ? $this->bypass_expires_at->toIso8601String() : null,
                    'bypass_remaining_seconds' => $remainingSecs,
                    'bypass_count' => (int) ($this->bypass_count ?? 0),
                    'bypass_reason' => $this->bypass_reason,
                ];
            }
        }

        $now = now();

        // --- WHOLE DAY (4-SLOT) MULTI-SESSION EVALUATION ---
        if ($this->session_type === 'whole_day') {
            $amInStart = $this->am_checkin_start_time ? \Carbon\Carbon::parse($this->am_checkin_start_time) : null;
            $amInEnd = $this->am_checkin_end_time ? \Carbon\Carbon::parse($this->am_checkin_end_time) : null;
            $amOutStart = $this->am_checkout_start_time ? \Carbon\Carbon::parse($this->am_checkout_start_time) : null;
            $amOutEnd = $this->am_checkout_end_time ? \Carbon\Carbon::parse($this->am_checkout_end_time) : null;

            $pmInStart = $this->pm_checkin_start_time ? \Carbon\Carbon::parse($this->pm_checkin_start_time) : null;
            $pmInEnd = $this->pm_checkin_end_time ? \Carbon\Carbon::parse($this->pm_checkin_end_time) : null;
            $pmOutStart = $this->pm_checkout_start_time ? \Carbon\Carbon::parse($this->pm_checkout_start_time) : null;
            $pmOutEnd = $this->pm_checkout_end_time ? \Carbon\Carbon::parse($this->pm_checkout_end_time) : null;

            // 1. AM Time-In
            if ($amInStart && $amInEnd && $now->gte($amInStart) && $now->lte($amInEnd)) {
                return [
                    'is_open' => true,
                    'phase' => 'am_checkin',
                    'slot' => 'am_in',
                    'label' => '🟢 AM TIME-IN OPEN',
                    'message' => 'Morning Time-In is active until ' . $amInEnd->format('h:i A') . '.',
                    'window_start' => $amInStart->format('Y-m-d H:i:s'),
                    'window_end' => $amInEnd->format('Y-m-d H:i:s'),
                    'next_time' => $amInEnd->format('Y-m-d H:i:s'),
                    'allow_window_bypass' => false
                ];
            }

            // 2. AM Time-Out
            if ($amOutStart && $amOutEnd && $now->gte($amOutStart) && $now->lte($amOutEnd)) {
                return [
                    'is_open' => true,
                    'phase' => 'am_checkout',
                    'slot' => 'am_out',
                    'label' => '🔵 AM TIME-OUT OPEN',
                    'message' => 'Morning Time-Out is active until ' . $amOutEnd->format('h:i A') . '.',
                    'window_start' => $amOutStart->format('Y-m-d H:i:s'),
                    'window_end' => $amOutEnd->format('Y-m-d H:i:s'),
                    'next_time' => $amOutEnd->format('Y-m-d H:i:s'),
                    'allow_window_bypass' => false
                ];
            }

            // 3. PM Time-In
            if ($pmInStart && $pmInEnd && $now->gte($pmInStart) && $now->lte($pmInEnd)) {
                return [
                    'is_open' => true,
                    'phase' => 'pm_checkin',
                    'slot' => 'pm_in',
                    'label' => '🟢 PM TIME-IN OPEN',
                    'message' => 'Afternoon Time-In is active until ' . $pmInEnd->format('h:i A') . '.',
                    'window_start' => $pmInStart->format('Y-m-d H:i:s'),
                    'window_end' => $pmInEnd->format('Y-m-d H:i:s'),
                    'next_time' => $pmInEnd->format('Y-m-d H:i:s'),
                    'allow_window_bypass' => false
                ];
            }

            // 4. PM Time-Out
            if ($pmOutStart && $pmOutEnd && $now->gte($pmOutStart) && $now->lte($pmOutEnd)) {
                return [
                    'is_open' => true,
                    'phase' => 'pm_checkout',
                    'slot' => 'pm_out',
                    'label' => '🔵 PM TIME-OUT OPEN',
                    'message' => 'Afternoon Time-Out is active until ' . $pmOutEnd->format('h:i A') . '.',
                    'window_start' => $pmOutStart->format('Y-m-d H:i:s'),
                    'window_end' => $pmOutEnd->format('Y-m-d H:i:s'),
                    'next_time' => $pmOutEnd->format('Y-m-d H:i:s'),
                    'allow_window_bypass' => false
                ];
            }

            // Outside active windows for Whole-Day
            $nextTime = null;
            $nextLabel = '';
            if ($amInStart && $now->lt($amInStart)) {
                $nextTime = $amInStart->format('Y-m-d H:i:s');
                $nextLabel = 'AM Time-In opens at ' . $amInStart->format('h:i A');
            } elseif ($amOutStart && $now->lt($amOutStart)) {
                $nextTime = $amOutStart->format('Y-m-d H:i:s');
                $nextLabel = 'AM Time-Out opens at ' . $amOutStart->format('h:i A');
            } elseif ($pmInStart && $now->lt($pmInStart)) {
                $nextTime = $pmInStart->format('Y-m-d H:i:s');
                $nextLabel = 'PM Time-In opens at ' . $pmInStart->format('h:i A');
            } elseif ($pmOutStart && $now->lt($pmOutStart)) {
                $nextTime = $pmOutStart->format('Y-m-d H:i:s');
                $nextLabel = 'PM Time-Out opens at ' . $pmOutStart->format('h:i A');
            }

            return [
                'is_open' => false,
                'phase' => 'closed',
                'label' => '🔴 ATTENDANCE WINDOW CLOSED',
                'message' => $nextLabel ?: 'Scheduled session windows are currently closed.',
                'next_time' => $nextTime,
                'allow_window_bypass' => false
            ];
        }

        // --- HALF-DAY (2-SLOT) STANDARD EVALUATION ---
        $cinStart = $this->checkin_start_time ? \Carbon\Carbon::parse($this->checkin_start_time) : null;
        $cinEnd = $this->checkin_end_time ? \Carbon\Carbon::parse($this->checkin_end_time) : null;
        $coutStart = $this->checkout_start_time ? \Carbon\Carbon::parse($this->checkout_start_time) : null;
        $coutEnd = $this->checkout_end_time ? \Carbon\Carbon::parse($this->checkout_end_time) : null;

        $hasCheckin = !empty($cinStart) && !empty($cinEnd);
        $hasCheckout = !empty($coutStart) && !empty($coutEnd);

        // If no custom windows configured, default to open during event start_time to end_time
        if (!$hasCheckin && !$hasCheckout) {
            $eStart = \Carbon\Carbon::parse($this->start_time);
            $eEnd = \Carbon\Carbon::parse($this->end_time);
            $isOpen = $now->gte($eStart) && $now->lte($eEnd);
            return [
                'is_open' => $isOpen,
                'phase' => $isOpen ? 'active' : 'outside_event',
                'label' => $isOpen ? 'Event In Progress' : 'Event Session Closed',
                'message' => $isOpen ? 'Attendance session is active.' : 'Outside scheduled event hours.',
                'next_time' => $isOpen ? $eEnd->format('Y-m-d H:i:s') : $eStart->format('Y-m-d H:i:s'),
                'allow_window_bypass' => false
            ];
        }

        // 1. Check Time-In Window
        if ($hasCheckin && $now->gte($cinStart) && $now->lte($cinEnd)) {
            return [
                'is_open' => true,
                'phase' => 'checkin',
                'slot' => 'checkin',
                'label' => '🟢 TIME-IN WINDOW OPEN',
                'message' => 'Time-In attendance is active until ' . $cinEnd->format('h:i A') . '.',
                'window_start' => $cinStart->format('Y-m-d H:i:s'),
                'window_end' => $cinEnd->format('Y-m-d H:i:s'),
                'next_time' => $cinEnd->format('Y-m-d H:i:s'),
                'allow_window_bypass' => false
            ];
        }

        // 2. Check Time-Out Window
        if ($hasCheckout && $now->gte($coutStart) && $now->lte($coutEnd)) {
            return [
                'is_open' => true,
                'phase' => 'checkout',
                'slot' => 'checkout',
                'label' => '🔵 TIME-OUT WINDOW OPEN',
                'message' => 'Time-Out attendance is active until ' . $coutEnd->format('h:i A') . '.',
                'window_start' => $coutStart->format('Y-m-d H:i:s'),
                'window_end' => $coutEnd->format('Y-m-d H:i:s'),
                'next_time' => $coutEnd->format('Y-m-d H:i:s'),
                'allow_window_bypass' => false
            ];
        }

        // 3. Outside active windows -> Determine next opening time
        $nextTime = null;
        $nextLabel = '';
        if ($hasCheckin && $now->lt($cinStart)) {
            $nextTime = $cinStart->format('Y-m-d H:i:s');
            $nextLabel = 'Time-In opens at ' . $cinStart->format('h:i A');
        } elseif ($hasCheckout && $now->lt($coutStart)) {
            $nextTime = $coutStart->format('Y-m-d H:i:s');
            $nextLabel = 'Time-Out opens at ' . $coutStart->format('h:i A');
        }

        return [
            'is_open' => false,
            'phase' => 'closed',
            'label' => '🔴 ATTENDANCE WINDOW CLOSED',
            'message' => $nextLabel ?: 'Scheduled attendance windows are currently closed.',
            'next_time' => $nextTime,
            'allow_window_bypass' => false
        ];
    }

    /**
     * Check if a specific student is eligible to attend this event based on year level.
     */
    public function isEligibleStudent(?User $user): bool
    {
        if (!$user || $user->role !== 'student') {
            return true; // Staff/Admin always eligible to view/manage
        }

        $targets = $this->target_year_levels;

        // If no target set, or empty, or contains 'All', open to all BSIS students
        if (empty($targets) || in_array('All', $targets, true)) {
            return true;
        }

        // If student has no year level assigned
        if (empty($user->year_level)) {
            return true;
        }

        return in_array($user->year_level, $targets, true);
    }

    /**
     * Return user-friendly badge label for target participants.
     */
    public function getTargetAudienceLabel(): string
    {
        $targets = $this->target_year_levels;
        if (empty($targets) || in_array('All', $targets, true) || count($targets) >= 4) {
            return 'All BSIS Students';
        }

        return implode(', ', $targets);
    }

    /**
     * Accessor for target_audience_label.
     */
    public function getTargetAudienceLabelAttribute(): string
    {
        return $this->getTargetAudienceLabel();
    }
}
