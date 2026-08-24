<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'event_id',
        'user_id',
        'scan_time',
        'checkout_time',
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out',
        'status',
        'slot_statuses',
        'fine_amount',
        'fine_paid',
        'latitude',
        'longitude',
        'distance_meters',
        'device_credential',
        'is_offline_sync',
        'override_by',
        'override_reason',
        'verification_data',
    ];

    protected $casts = [
        'scan_time' => 'datetime',
        'checkout_time' => 'datetime',
        'am_time_in' => 'datetime',
        'am_time_out' => 'datetime',
        'pm_time_in' => 'datetime',
        'pm_time_out' => 'datetime',
        'slot_statuses' => 'array',
        'fine_amount' => 'float',
        'fine_paid' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'distance_meters' => 'float',
        'is_offline_sync' => 'boolean',
        'verification_data' => 'array',
    ];

    protected $appends = [
        'fine_breakdown',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function overrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    /**
     * Compute human-readable itemized fine breakdown for student, admin, and staff views.
     */
    public function getFineBreakdownAttribute(): array
    {
        $event = $this->event;
        $isWholeDay = $event && $event->session_type === 'whole_day';
        $finePerSlot = (float) ($event?->fine_per_slot ?: $event?->fine_amount ?: 0.00);
        if ($finePerSlot <= 0 && $this->fine_amount > 0) {
            $finePerSlot = $this->fine_amount;
        }

        $slots = [];
        $missedCount = 0;
        $lateCount = 0;
        $presentCount = 0;

        if ($this->status === 'absent') {
            $totalSlots = $isWholeDay ? 4 : 2;
            $summaryText = $isWholeDay
                ? "Absent: Missed all 4 scanning sessions of the day (AM In, AM Out, PM In, PM Out)."
                : "Absent: Missed both Time-In and Time-Out scanning sessions.";

            $absentSlots = $isWholeDay ? [
                ['key' => 'am_in', 'name' => 'AM Time-In', 'status' => 'missed', 'label' => 'Missed Scan', 'fine' => $finePerSlot, 'time' => null],
                ['key' => 'am_out', 'name' => 'AM Time-Out', 'status' => 'missed', 'label' => 'Missed Scan', 'fine' => $finePerSlot, 'time' => null],
                ['key' => 'pm_in', 'name' => 'PM Time-In', 'status' => 'missed', 'label' => 'Missed Scan', 'fine' => $finePerSlot, 'time' => null],
                ['key' => 'pm_out', 'name' => 'PM Time-Out', 'status' => 'missed', 'label' => 'Missed Scan', 'fine' => $finePerSlot, 'time' => null],
            ] : [
                ['key' => 'checkin', 'name' => 'Time-In', 'status' => 'missed', 'label' => 'Missed Scan', 'fine' => $finePerSlot, 'time' => null],
                ['key' => 'checkout', 'name' => 'Time-Out', 'status' => 'missed', 'label' => 'Missed Scan', 'fine' => $finePerSlot, 'time' => null],
            ];

            return [
                'type' => $isWholeDay ? 'whole_day_4_scans' : 'standard_2_scans',
                'summary' => $summaryText,
                'fine_per_slot' => $finePerSlot,
                'missed_count' => $totalSlots,
                'late_count' => 0,
                'present_count' => 0,
                'slots' => $absentSlots,
            ];
        }

        $slotStatusMap = $this->slot_statuses ?: [];

        if ($isWholeDay) {
            $slotConfig = [
                'am_in' => ['name' => 'AM Time-In', 'time' => $this->am_time_in ?: $this->scan_time],
                'am_out' => ['name' => 'AM Time-Out', 'time' => $this->am_time_out],
                'pm_in' => ['name' => 'PM Time-In', 'time' => $this->pm_time_in],
                'pm_out' => ['name' => 'PM Time-Out', 'time' => $this->pm_time_out ?: $this->checkout_time],
            ];

            foreach ($slotConfig as $key => $conf) {
                $t = $conf['time'];
                $st = $slotStatusMap[$key] ?? null;

                if (empty($t) || $st === 'missed') {
                    $status = 'missed';
                    $label = 'Missed Scan';
                    $fine = $finePerSlot;
                    $missedCount++;
                } elseif ($st === 'late') {
                    $status = 'late';
                    $label = 'Late Scan';
                    $fine = $finePerSlot;
                    $lateCount++;
                } else {
                    $status = 'present';
                    $label = 'Scanned On-Time';
                    $fine = 0.00;
                    $presentCount++;
                }

                $slots[] = [
                    'key' => $key,
                    'name' => $conf['name'],
                    'status' => $status,
                    'label' => $label,
                    'fine' => $fine,
                    'time' => $t ? $t->format('h:i A') : null,
                ];
            }

            $penalties = [];
            if ($missedCount > 0) $penalties[] = "{$missedCount} missed scan" . ($missedCount > 1 ? 's' : '');
            if ($lateCount > 0) $penalties[] = "{$lateCount} late scan" . ($lateCount > 1 ? 's' : '');

            $scannedList = [];
            foreach ($slots as $s) {
                if ($s['status'] === 'present' || $s['status'] === 'late') {
                    $scannedList[] = $s['name'] . ($s['time'] ? " at {$s['time']}" : "");
                }
            }

            if (count($penalties) > 0) {
                $summaryText = "Incurred fine for " . implode(' and ', $penalties) . ".";
                if (count($scannedList) > 0) {
                    $summaryText .= " Only scanned: " . implode(', ', $scannedList) . ".";
                } else {
                    $summaryText .= " No sessions recorded.";
                }
            } else {
                $summaryText = "All 4 scans completed on-time.";
            }
        } else {
            $slotConfig = [
                'checkin' => ['name' => 'Time-In', 'time' => $this->scan_time ?: $this->am_time_in],
                'checkout' => ['name' => 'Time-Out', 'time' => $this->checkout_time ?: $this->pm_time_out],
            ];

            foreach ($slotConfig as $key => $conf) {
                $t = $conf['time'];
                $st = $slotStatusMap[$key] ?? null;

                if (empty($t) || $st === 'missed') {
                    $status = 'missed';
                    $label = 'Missed Scan';
                    $fine = $finePerSlot;
                    $missedCount++;
                } elseif ($st === 'late') {
                    $status = 'late';
                    $label = 'Late Scan';
                    $fine = $finePerSlot;
                    $lateCount++;
                } else {
                    $status = 'present';
                    $label = 'Scanned On-Time';
                    $fine = 0.00;
                    $presentCount++;
                }

                $slots[] = [
                    'key' => $key,
                    'name' => $conf['name'],
                    'status' => $status,
                    'label' => $label,
                    'fine' => $fine,
                    'time' => $t ? $t->format('h:i A') : null,
                ];
            }

            $penalties = [];
            if ($missedCount > 0) $penalties[] = "{$missedCount} missed scan" . ($missedCount > 1 ? 's' : '');
            if ($lateCount > 0) $penalties[] = "{$lateCount} late scan" . ($lateCount > 1 ? 's' : '');

            $scannedList = [];
            foreach ($slots as $s) {
                if ($s['status'] === 'present' || $s['status'] === 'late') {
                    $scannedList[] = $s['name'] . ($s['time'] ? " at {$s['time']}" : "");
                }
            }

            if (count($penalties) > 0) {
                $summaryText = "Incurred fine for " . implode(' and ', $penalties) . ".";
                if (count($scannedList) > 0) {
                    $summaryText .= " Only scanned: " . implode(', ', $scannedList) . ".";
                }
            } else {
                $summaryText = "Both scans completed on-time.";
            }
        }

        return [
            'type' => $isWholeDay ? 'whole_day_4_scans' : 'standard_2_scans',
            'summary' => $summaryText,
            'fine_per_slot' => $finePerSlot,
            'missed_count' => $missedCount,
            'late_count' => $lateCount,
            'present_count' => $presentCount,
            'slots' => $slots,
        ];
    }
}
