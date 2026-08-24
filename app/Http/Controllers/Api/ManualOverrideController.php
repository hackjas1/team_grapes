<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ManualOverrideRequest;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ManualOverrideController extends Controller
{
    use ApiResponse;

    /**
     * Authorized Event Staff / Admin Manual Attendance Override.
     */
    public function store(ManualOverrideRequest $request): JsonResponse
    {
        $staff = $request->user();
        $eventId = (int) $request->input('event_id');
        $studentId = $request->input('student_id');
        $studentIdentifier = trim($request->input('student_identifier', ''));
        $reason = trim($request->input('reason'));
        $overrideStatus = $request->input('status', 'manual_override');

        $event = Event::with('staff')->findOrFail($eventId);

        $student = null;
        if ($studentId) {
            $student = User::where('role', 'student')->find($studentId);
        }
        
        if (!$student && $studentIdentifier) {
            $student = User::where('role', 'student')
                ->where(function ($q) use ($studentIdentifier) {
                    $q->where('student_number', $studentIdentifier)
                      ->orWhere('email', $studentIdentifier)
                      ->orWhere('id', $studentIdentifier);
                })->first();
        }

        if (!$student) {
            return $this->errorResponse('Student account not found. Please verify the Student ID number or email.', [], 404);
        }

        // Security check for event staff
        if ($staff->role === 'event_staff' && $event->staff()->exists() && !$event->staff->pluck('id')->contains($staff->id) && $event->created_by !== $staff->id) {
            return $this->errorResponse('You are not authorized to manage or override attendance for this event.', [], 403);
        }

        return DB::transaction(function () use ($staff, $event, $student, $reason, $overrideStatus, $request) {
            $existingAttendance = Attendance::where('event_id', $event->id)
                ->where('user_id', $student->id)
                ->first();

            if ($existingAttendance) {
                // Update existing record with manual override
                $existingAttendance->status = 'manual_override';
                $existingAttendance->override_by = $staff->id;
                $existingAttendance->override_reason = $reason;
                $existingAttendance->save();

                $attendance = $existingAttendance;
            } else {
                // Create new manual override record
                $attendance = Attendance::create([
                    'event_id' => $event->id,
                    'user_id' => $student->id,
                    'scan_time' => now(),
                    'status' => 'manual_override',
                    'fine_amount' => 0.00,
                    'fine_paid' => false,
                    'is_offline_sync' => false,
                    'override_by' => $staff->id,
                    'override_reason' => $reason,
                    'verification_data' => [
                        'verified_via' => 'Manual Staff Override',
                        'staff_name' => $staff->full_name,
                        'reason' => $reason,
                    ],
                ]);
            }

            AuditLog::create([
                'user_id' => $staff->id,
                'action' => 'manual_override',
                'description' => "Staff {$staff->full_name} manually validated attendance for student {$student->full_name} ({$student->student_number}) at event '{$event->title}'. Reason: {$reason}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                    'attendance_id' => $attendance->id,
                    'override_reason' => $reason,
                ],
            ]);

            return $this->successResponse([
                'attendance' => $attendance->load(['event', 'user', 'overrider']),
                'student' => [
                    'student_number' => $student->student_number,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                ],
            ], 'Manual attendance override recorded successfully.', 201);
        });
    }
}
