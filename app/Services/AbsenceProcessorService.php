<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AbsenceProcessorService
{
    /**
     * Process absences and generate non-attendance fines for an event.
     *
     * @param Event $event
     * @param User|null $actor
     * @return array
     */
    public function processEventAbsences(Event $event, ?User $actor = null): array
    {
        // 1. Fetch eligible active students
        $query = User::where('role', 'student')->where('status', 'active');

        $targetYears = $event->target_year_levels;
        if (!empty($targetYears) && is_array($targetYears) && !in_array('All', $targetYears, true)) {
            $validYears = array_intersect($targetYears, ['1st Year', '2nd Year', '3rd Year', '4th Year']);
            if (count($validYears) > 0 && count($validYears) < 4) {
                $query->whereIn('year_level', $validYears);
            }
        }

        $eligibleStudents = $query->get();

        // 2. Fetch existing student IDs with attendance records for this event
        $existingAttendees = Attendance::where('event_id', $event->id)->get();
        $existingAttendeeIds = $existingAttendees->pluck('user_id')->toArray();

        $absentStudents = $eligibleStudents->whereNotIn('id', $existingAttendeeIds);

        $createdCount = 0;
        $totalFineGenerated = 0;
        $eventEndTime = $event->end_time ?: now();
        
        $finePerSlot = (float) ($event->fine_per_slot ?: $event->fine_amount ?: 0.00);
        $isWholeDay = $event->session_type === 'whole_day';
        
        // Full absent fine: 4 slots for whole-day, 2 slots for half-day
        $fullAbsentFine = $isWholeDay ? ($finePerSlot * 4) : ($finePerSlot * 2);

        DB::transaction(function () use ($absentStudents, $existingAttendees, $event, $eventEndTime, $finePerSlot, $fullAbsentFine, $isWholeDay, &$createdCount, &$totalFineGenerated) {
            // A. Create records for completely absent students
            foreach ($absentStudents as $student) {
                Attendance::create([
                    'event_id' => $event->id,
                    'user_id' => $student->id,
                    'scan_time' => $eventEndTime,
                    'status' => 'absent',
                    'fine_amount' => $fullAbsentFine,
                    'fine_paid' => false,
                    'verification_data' => [
                        'reason' => 'Auto-processed non-attendance fine on event conclusion',
                        'processed_at' => now()->toIso8601String(),
                        'missed_slots' => $isWholeDay ? 4 : 2,
                    ],
                ]);

                $createdCount++;
                $totalFineGenerated += $fullAbsentFine;
            }

            // B. Reconcile existing attendees who missed 1 or more scans
            foreach ($existingAttendees as $att) {
                // If fine is already paid/waived, skip altering
                if ($att->fine_paid) {
                    continue;
                }

                if ($att->status === 'absent') {
                    $att->fine_amount = $fullAbsentFine;
                    $att->save();
                    $totalFineGenerated += $fullAbsentFine;
                    continue;
                }

                $slotStatuses = $att->slot_statuses ?: [];
                $missedCount = 0;
                $lateCount = 0;

                if ($isWholeDay) {
                    $slots = [
                        'am_in' => $att->am_time_in,
                        'am_out' => $att->am_time_out,
                        'pm_in' => $att->pm_time_in,
                        'pm_out' => $att->pm_time_out,
                    ];

                    foreach ($slots as $sKey => $sTime) {
                        if (empty($sTime)) {
                            $slotStatuses[$sKey] = 'missed';
                            $missedCount++;
                        } elseif (($slotStatuses[$sKey] ?? null) === 'late') {
                            $lateCount++;
                        }
                    }
                } else {
                    $slots = [
                        'checkin' => $att->scan_time ?: $att->am_time_in,
                        'checkout' => $att->checkout_time ?: $att->pm_time_out,
                    ];

                    foreach ($slots as $sKey => $sTime) {
                        if (empty($sTime)) {
                            $slotStatuses[$sKey] = 'missed';
                            $missedCount++;
                        } elseif (($slotStatuses[$sKey] ?? null) === 'late') {
                            $lateCount++;
                        }
                    }
                }

                $totalSlotsPenalty = $missedCount + $lateCount;
                if ($totalSlotsPenalty > 0) {
                    $newFine = $totalSlotsPenalty * $finePerSlot;
                    $att->slot_statuses = $slotStatuses;
                    $att->fine_amount = $newFine;
                    if ($att->status === 'present') {
                        $att->status = 'late';
                    }
                    $att->save();
                    $totalFineGenerated += $newFine;
                }
            }
        });

        if ($createdCount > 0) {
            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => 'event_absences_processed',
                'description' => "Auto-processed {$createdCount} absence records and reconciled missed scans (Total fines: ₱" . number_format($totalFineGenerated, 2) . ") for concluded event '{$event->title}' (ID: {$event->id}).",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System / Console',
                'metadata' => [
                    'event_id' => $event->id,
                    'absent_count' => $createdCount,
                    'total_fine_amount' => $totalFineGenerated,
                ],
            ]);
        }

        return [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'eligible_students_count' => $eligibleStudents->count(),
            'attendees_count' => count($existingAttendeeIds),
            'absent_records_created' => $createdCount,
            'total_fines_generated' => $totalFineGenerated,
        ];
    }

    /**
     * Process all expired active events whose scheduled end_time has passed.
     *
     * @return array
     */
    public function processExpiredEvents(): array
    {
        $expiredEvents = Event::where('status', 'active')
            ->whereNotNull('end_time')
            ->where('end_time', '<=', now())
            ->get();

        $processedEvents = [];

        foreach ($expiredEvents as $event) {
            $event->status = 'completed';
            $event->save();

            $stats = $this->processEventAbsences($event);
            $processedEvents[] = $stats;
        }

        return $processedEvents;
    }
}
