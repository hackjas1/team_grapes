<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ScanAttendanceRequest;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Event;
use App\Services\GpsValidationService;
use App\Services\QrTokenService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use ApiResponse;

    protected QrTokenService $qrTokenService;
    protected GpsValidationService $gpsValidationService;

    public function __construct(QrTokenService $qrTokenService, GpsValidationService $gpsValidationService)
    {
        $this->qrTokenService = $qrTokenService;
        $this->gpsValidationService = $gpsValidationService;
    }

    /**
     * Central Attendance Validation & Scanning Engine.
     * POST /api/attendance/scan
     */
    public function scan(ScanAttendanceRequest $request): JsonResponse
    {
        $student = $request->user();
        $qrToken = $request->input('qr_token');
        $suppliedDeviceCred = trim($request->input('device_credential'));
        $studentLat = (float) $request->input('latitude');
        $studentLon = (float) $request->input('longitude');

        // Step 1: Validate Student Account Status
        if ($student->status !== 'active') {
            $this->logFailedScan($student->id, null, "Student account status is '{$student->status}'", $request);
            return $this->errorResponse("Account is not active (current status: {$student->status}).", [], 403);
        }

        $isOfflineSync = (bool) $request->input('is_offline_sync', false);
        $scanTimestamp = null;
        if ($isOfflineSync && $request->input('scan_time')) {
            $scanTimestamp = strtotime($request->input('scan_time'));
        }

        // Step 2: Validate Dynamic QR Token (Signature & Expiration)
        $qrValidation = $this->qrTokenService->validateToken($qrToken, $scanTimestamp);
        if (!$qrValidation['is_valid']) {
            $eventId = $qrValidation['event_id'] ?? null;
            $this->logFailedScan($student->id, $eventId, $qrValidation['error'], $request);
            return $this->errorResponse($qrValidation['error'], [], 422);
        }

        $eventId = $qrValidation['event_id'];

        // Step 3: Validate Device Binding
        $activeDevice = Device::where('user_id', $student->id)
            ->where('status', 'active')
            ->first();

        if (!$activeDevice) {
            // Auto-bind device on scan if no active device is registered (e.g. after Admin Device Reset or fresh login)
            $bindCred = !empty($suppliedDeviceCred) ? $suppliedDeviceCred : (string) \Illuminate\Support\Str::uuid();
            $activeDevice = Device::create([
                'user_id' => $student->id,
                'device_credential' => $bindCred,
                'device_name' => $request->header('User-Agent', 'Student Mobile Phone'),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'status' => 'active',
                'bound_at' => now(),
            ]);
            $suppliedDeviceCred = $bindCred;
        } elseif (!hash_equals($activeDevice->device_credential, $suppliedDeviceCred)) {
            $this->logFailedScan($student->id, $eventId, 'Device credential mismatch', $request);
            return $this->errorResponse('Unauthorized device credential. This device does not match your registered attendance device. If you switched devices or cleared browser data, log out and log in on this phone to re-sync.', [
                'device_bound' => true,
                'reason' => 'device_credential_mismatch'
            ], 403);
        }

        // Step 4: Validate Event Status
        $event = Event::find($eventId);
        if (!$event) {
            $this->logFailedScan($student->id, $eventId, 'Event not found', $request);
            return $this->errorResponse('Event not found.', [], 404);
        }

        if ($event->status !== 'active') {
            $this->logFailedScan($student->id, $event->id, "Event is not active (current status: {$event->status})", $request);
            return $this->errorResponse("Attendance session for this event is not active (status: {$event->status}).", [], 400);
        }

        // Step 4.2: Validate Target Year Level Eligibility
        if (!$event->isEligibleStudent($student)) {
            $audienceLabel = $event->getTargetAudienceLabel();
            $reason = "You are not an eligible participant for this event. Restricted to: {$audienceLabel} (Your Year Level: " . ($student->year_level ?: 'Unspecified') . ").";
            $this->logFailedScan($student->id, $event->id, $reason, $request, [
                'target_audience' => $audienceLabel,
                'student_year_level' => $student->year_level,
            ]);
            return $this->errorResponse($reason, [
                'target_audience' => $audienceLabel,
                'student_year_level' => $student->year_level,
            ], 403);
        }

        // Step 4.5: Validate Attendance Time Window & Emergency Bypass
        $windowStatus = $event->getActiveWindowStatus();
        if (!$windowStatus['is_open']) {
            $reason = "Scanning is currently closed. " . ($windowStatus['message'] ?? 'Outside scheduled attendance timeframe.');
            $this->logFailedScan($student->id, $event->id, $reason, $request);
            return $this->errorResponse($reason, [
                'window_status' => $windowStatus,
            ], 422);
        }

        // Step 5: Anti-Spoofing & Instantaneous Teleportation Check (Fake GPS Detection)
        $prevLat = null;
        $prevLon = null;
        $prevTimestamp = null;

        // Check recent failed scan log
        $recentScanLog = AuditLog::where('user_id', $student->id)
            ->where('action', 'failed_scan')
            ->where('created_at', '>=', now()->subMinutes(45))
            ->latest()
            ->first();

        if ($recentScanLog && !empty($recentScanLog->metadata['student_coords'])) {
            $coords = explode(',', $recentScanLog->metadata['student_coords']);
            if (count($coords) === 2 && is_numeric($coords[0]) && is_numeric($coords[1])) {
                $prevLat = (float) $coords[0];
                $prevLon = (float) $coords[1];
                $prevTimestamp = $recentScanLog->created_at->timestamp;
            }
        }

        // Check recent attendance record if no failed scan log
        if ($prevLat === null) {
            $recentAttendance = Attendance::where('user_id', $student->id)
                ->where('updated_at', '>=', now()->subMinutes(45))
                ->latest('updated_at')
                ->first();

            if ($recentAttendance && !empty($recentAttendance->verification_data['student_latitude']) && !empty($recentAttendance->verification_data['student_longitude'])) {
                $prevLat = (float) $recentAttendance->verification_data['student_latitude'];
                $prevLon = (float) $recentAttendance->verification_data['student_longitude'];
                $prevTimestamp = $recentAttendance->updated_at->timestamp;
            }
        }

        if ($prevLat !== null && $prevLon !== null && $prevTimestamp !== null) {
            $teleportCheck = $this->gpsValidationService->validateTeleportation(
                $studentLat,
                $studentLon,
                $prevLat,
                $prevLon,
                $prevTimestamp
            );

            if (!$teleportCheck['is_valid']) {
                $this->logFailedScan($student->id, $event->id, $teleportCheck['error'], $request, [
                    'distance_meters' => $teleportCheck['distance_meters'] ?? 0,
                    'speed_kmh' => $teleportCheck['speed_kmh'] ?? 0,
                    'student_coords' => "{$studentLat},{$studentLon}",
                    'prev_coords' => "{$prevLat},{$prevLon}",
                ]);
                return $this->errorResponse($teleportCheck['error'], $teleportCheck, 422);
            }
        }

        // Step 5.1: GPS Verification (Haversine Formula Radius Check)
        $gpsCheck = $this->gpsValidationService->validateRadius(
            $studentLat,
            $studentLon,
            (float) $event->venue_latitude,
            (float) $event->venue_longitude,
            (float) $event->allowed_radius_meters
        );

        if (!$gpsCheck['is_valid']) {
            $reason = "You are outside the allowed event venue area. Distance: {$gpsCheck['distance_meters']} meters away (Allowed radius: {$gpsCheck['allowed_radius_meters']} meters).";
            $this->logFailedScan($student->id, $event->id, $reason, $request, [
                'distance_meters' => $gpsCheck['distance_meters'],
                'student_coords' => "{$studentLat},{$studentLon}",
                'venue_coords' => "{$event->venue_latitude},{$event->venue_longitude}",
            ]);
            return $this->errorResponse($reason, [
                'distance_meters' => $gpsCheck['distance_meters'],
                'allowed_radius_meters' => $gpsCheck['allowed_radius_meters'],
            ], 422);
        }

        $scanTime = ($isOfflineSync && $request->input('scan_time'))
            ? \Carbon\Carbon::parse($request->input('scan_time'))
            : now();
        $phase = $windowStatus['phase'] ?? 'checkin';
        $isWholeDay = $event->session_type === 'whole_day';
        $finePerSlot = (float) ($event->fine_per_slot ?: $event->fine_amount ?: 0.00);

        // Fetch existing attendance record if any
        $attendance = Attendance::where('event_id', $event->id)
            ->where('user_id', $student->id)
            ->first();

        // 1. Determine target slot
        if ($isWholeDay) {
            $slot = $windowStatus['slot'] ?? null;
            if (!$slot || $phase === 'bypass' || $phase === 'active') {
                // Determine next unrecorded slot
                if (!$attendance || !$attendance->am_time_in) {
                    $slot = 'am_in';
                } elseif (!$attendance->am_time_out) {
                    $slot = 'am_out';
                } elseif (!$attendance->pm_time_in) {
                    $slot = 'pm_in';
                } elseif (!$attendance->pm_time_out) {
                    $slot = 'pm_out';
                } else {
                    $alreadyMsg = "All 4 attendance sessions (AM In, AM Out, PM In, PM Out) have already been recorded for this event.";
                    $this->logFailedScan($student->id, $event->id, $alreadyMsg, $request);
                    return $this->errorResponse($alreadyMsg, ['existing_attendance' => $attendance], 409);
                }
            }

            // Slot configurations
            $slotConfigs = [
                'am_in' => [
                    'field' => 'am_time_in',
                    'label' => 'AM Time-In',
                    'badge' => '🟢 AM TIME-IN RECORDED',
                    'end_time' => $event->am_checkin_end_time,
                ],
                'am_out' => [
                    'field' => 'am_time_out',
                    'label' => 'AM Time-Out',
                    'badge' => '🔵 AM TIME-OUT RECORDED',
                    'end_time' => $event->am_checkout_end_time,
                ],
                'pm_in' => [
                    'field' => 'pm_time_in',
                    'label' => 'PM Time-In',
                    'badge' => '🟢 PM TIME-IN RECORDED',
                    'end_time' => $event->pm_checkin_end_time,
                ],
                'pm_out' => [
                    'field' => 'pm_time_out',
                    'label' => 'PM Time-Out',
                    'badge' => '🔵 PM TIME-OUT RECORDED',
                    'end_time' => $event->pm_checkout_end_time,
                ],
            ];

            $cfg = $slotConfigs[$slot];
            $field = $cfg['field'];
            $slotLabel = $cfg['label'];
            $badgeLabel = $cfg['badge'];

            // Duplicate check on that specific slot
            if ($attendance && !empty($attendance->{$field})) {
                $alreadyMsg = "{$slotLabel} has already been recorded on " . $attendance->{$field}->format('h:i:s A');
                $this->logFailedScan($student->id, $event->id, $alreadyMsg, $request);
                return $this->errorResponse($alreadyMsg, [
                    'existing_attendance' => $attendance,
                    'scan_type' => $slotLabel
                ], 409);
            }

            // On-time vs Late evaluation for this slot (Emergency Bypass waives all late penalties)
            $isBypass = $phase === 'bypass' || (bool) $event->allow_window_bypass;
            $slotDeadline = $cfg['end_time'] ? \Carbon\Carbon::parse($cfg['end_time']) : null;
            $slotIsLate = !$isBypass && $slotDeadline && $scanTime->gt($slotDeadline);
            $slotStatus = $slotIsLate ? 'late' : 'present';

        } else {
            // Half-Day / 2-Slot Standard Event
            $isCheckout = $phase === 'checkout';
            if ($phase === 'bypass' || $phase === 'active') {
                $isCheckout = $attendance && !empty($attendance->scan_time) && empty($attendance->checkout_time);
            }

            $slot = $isCheckout ? 'checkout' : 'checkin';
            $slotLabel = $isCheckout ? 'Time-Out' : 'Time-In';
            $badgeLabel = $isCheckout ? '🔵 TIME-OUT RECORDED' : '🟢 TIME-IN RECORDED';

            if ($attendance) {
                if ($isCheckout && !empty($attendance->checkout_time)) {
                    $alreadyMsg = "Time-Out has already been recorded for this event on " . $attendance->checkout_time->format('h:i:s A');
                    $this->logFailedScan($student->id, $event->id, $alreadyMsg, $request);
                    return $this->errorResponse($alreadyMsg, ['existing_attendance' => $attendance, 'scan_type' => 'TIME-OUT'], 409);
                } elseif (!$isCheckout && !empty($attendance->scan_time)) {
                    $alreadyMsg = "Time-In has already been recorded for this event on " . $attendance->scan_time->format('h:i:s A');
                    $this->logFailedScan($student->id, $event->id, $alreadyMsg, $request);
                    return $this->errorResponse($alreadyMsg, ['existing_attendance' => $attendance, 'scan_type' => 'TIME-IN'], 409);
                }
            }

            $isBypass = $phase === 'bypass' || (bool) $event->allow_window_bypass;
            $deadline = $isCheckout
                ? ($event->checkout_end_time ? \Carbon\Carbon::parse($event->checkout_end_time) : ($event->end_time ? \Carbon\Carbon::parse($event->end_time) : null))
                : ($event->checkin_end_time ? \Carbon\Carbon::parse($event->checkin_end_time) : ($event->start_time ? $event->start_time->copy()->addMinutes(15) : null));

            $slotIsLate = !$isBypass && $deadline && $scanTime->gt($deadline);
            $slotStatus = $slotIsLate ? 'late' : 'present';
        }

        // Step 8: Record Attendance Transaction
        return DB::transaction(function () use ($student, $event, $scanTime, $isOfflineSync, $studentLat, $studentLon, $gpsCheck, $suppliedDeviceCred, $request, $isWholeDay, $slot, $slotLabel, $badgeLabel, $slotStatus, $finePerSlot, $phase, $attendance) {
            if (!$attendance) {
                $attendance = new Attendance([
                    'event_id' => $event->id,
                    'user_id' => $student->id,
                    'fine_paid' => false,
                    'latitude' => $studentLat,
                    'longitude' => $studentLon,
                    'distance_meters' => $gpsCheck['distance_meters'],
                    'device_credential' => $suppliedDeviceCred,
                    'is_offline_sync' => $isOfflineSync,
                ]);
            }

            $slotStatuses = $attendance->slot_statuses ?: [];
            $slotStatuses[$slot] = $slotStatus;

            if ($isWholeDay) {
                if ($slot === 'am_in') {
                    $attendance->am_time_in = $scanTime;
                    $attendance->scan_time = $scanTime; // legacy compatibility
                } elseif ($slot === 'am_out') {
                    $attendance->am_time_out = $scanTime;
                } elseif ($slot === 'pm_in') {
                    $attendance->pm_time_in = $scanTime;
                } elseif ($slot === 'pm_out') {
                    $attendance->pm_time_out = $scanTime;
                    $attendance->checkout_time = $scanTime; // legacy compatibility
                }

                // Detect missed earlier slots in whole-day sequence
                $slotSequence = ['am_in', 'am_out', 'pm_in', 'pm_out'];
                $currentIdx = array_search($slot, $slotSequence, true);
                if ($currentIdx !== false) {
                    for ($i = 0; $i < $currentIdx; $i++) {
                        $prevSlot = $slotSequence[$i];
                        $hasPrevScan = match($prevSlot) {
                            'am_in' => !empty($attendance->am_time_in),
                            'am_out' => !empty($attendance->am_time_out),
                            'pm_in' => !empty($attendance->pm_time_in),
                            'pm_out' => !empty($attendance->pm_time_out),
                            default => false,
                        };
                        if (!$hasPrevScan && empty($slotStatuses[$prevSlot])) {
                            $slotStatuses[$prevSlot] = 'missed';
                        }
                    }
                }
            } else {
                if ($slot === 'checkin') {
                    $attendance->scan_time = $scanTime;
                    $attendance->am_time_in = $scanTime;
                } else {
                    $attendance->checkout_time = $scanTime;
                    $attendance->pm_time_out = $scanTime;
                    // If checked out directly without check-in
                    if (empty($attendance->scan_time) && empty($attendance->am_time_in) && empty($slotStatuses['checkin'])) {
                        $slotStatuses['checkin'] = 'missed';
                    }
                }
            }

            $attendance->slot_statuses = $slotStatuses;

            // Calculate cumulative fine across recorded and missed slots
            $totalFine = 0.00;
            $hasAnyPenalty = false;
            foreach ($slotStatuses as $sKey => $sVal) {
                if ($sVal === 'late' || $sVal === 'missed') {
                    $totalFine += $finePerSlot;
                    $hasAnyPenalty = true;
                }
            }

            $attendance->fine_amount = $totalFine;
            $attendance->status = $hasAnyPenalty ? 'late' : 'present';
            $attendance->verification_data = [
                'verified_via' => $isOfflineSync ? 'Dynamic QR + GPS Haversine + Device Binding (Offline Sync)' : 'Dynamic QR + GPS Haversine + Device Binding',
                'allowed_radius' => $gpsCheck['allowed_radius_meters'],
                'actual_distance' => $gpsCheck['distance_meters'],
                'student_latitude' => $studentLat,
                'student_longitude' => $studentLon,
                'scan_type' => $slotLabel,
                'window_phase' => $phase,
                'session_type' => $event->session_type,
            ];
            $attendance->save();

            $successMsg = $slotStatus === 'present'
                ? "{$badgeLabel}: On-time scan verified!"
                : "{$badgeLabel}: Scanned past deadline. Added ₱" . number_format($finePerSlot, 2) . " fine.";

            return $this->successResponse([
                'scan_type' => $slotLabel,
                'slot' => $slot,
                'slot_status' => $slotStatus,
                'session_type' => $event->session_type,
                'phase' => $phase,
                'recorded_at' => $scanTime->format('h:i A'),
                'formatted_scan_time' => $scanTime->format('h:i A'),
                'attendance' => [
                    'id' => $attendance->id,
                    'status' => $attendance->status,
                    'slot_statuses' => $attendance->slot_statuses,
                    'scan_time' => $attendance->scan_time ? $attendance->scan_time->format('Y-m-d H:i:s') : null,
                    'formatted_time' => $attendance->scan_time ? $attendance->scan_time->format('h:i:s A') : null,
                    'checkout_time' => $attendance->checkout_time ? $attendance->checkout_time->format('Y-m-d H:i:s') : null,
                    'formatted_checkout_time' => $attendance->checkout_time ? $attendance->checkout_time->format('h:i:s A') : null,
                    'am_time_in' => $attendance->am_time_in ? $attendance->am_time_in->format('Y-m-d H:i:s') : null,
                    'formatted_am_in' => $attendance->am_time_in ? $attendance->am_time_in->format('h:i:s A') : null,
                    'am_time_out' => $attendance->am_time_out ? $attendance->am_time_out->format('Y-m-d H:i:s') : null,
                    'formatted_am_out' => $attendance->am_time_out ? $attendance->am_time_out->format('h:i:s A') : null,
                    'pm_time_in' => $attendance->pm_time_in ? $attendance->pm_time_in->format('Y-m-d H:i:s') : null,
                    'formatted_pm_in' => $attendance->pm_time_in ? $attendance->pm_time_in->format('h:i:s A') : null,
                    'pm_time_out' => $attendance->pm_time_out ? $attendance->pm_time_out->format('Y-m-d H:i:s') : null,
                    'formatted_pm_out' => $attendance->pm_time_out ? $attendance->pm_time_out->format('h:i:s A') : null,
                    'fine_amount' => $attendance->fine_amount,
                    'fine_per_slot' => $finePerSlot,
                    'distance_meters' => $attendance->distance_meters,
                ],
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'session_type' => $event->session_type,
                    'venue_name' => $event->venue_name,
                ],
                'student_profile' => [
                    'student_number' => $student->student_number,
                    'first_name' => $student->first_name,
                    'middle_name' => $student->middle_name,
                    'last_name' => $student->last_name,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'avatar_text' => strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)),
                ],
            ], $successMsg, 201);
        });
    }

    /**
     * List attendance records with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::with(['event', 'user', 'overrider']);

        if ($user->role === 'student') {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'event_staff') {
            $query->whereHas('event.staff', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($studentId = $request->query('student_id')) {
            $query->where('user_id', $studentId);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('scan_time', $date);
        }

        $records = $query->leftJoin('events', 'attendance.event_id', '=', 'events.id')
            ->select('attendance.*')
            ->orderByRaw("CASE WHEN events.status = 'active' THEN 0 ELSE 1 END ASC")
            ->orderByRaw("COALESCE(attendance.pm_time_out, attendance.pm_time_in, attendance.am_time_out, attendance.am_time_in, attendance.checkout_time, attendance.scan_time, attendance.created_at) DESC")
            ->paginate($request->query('per_page', 50));

        return $this->successResponse($records, 'Attendance records retrieved successfully.');
    }

    /**
     * Show single attendance record details.
     */
    public function show(int $id): JsonResponse
    {
        $attendance = Attendance::with(['event', 'user', 'overrider'])->findOrFail($id);
        return $this->successResponse($attendance, 'Attendance record details retrieved successfully.');
    }

    /**
     * Helper to log failed scan attempt in audit logs.
     */
    protected function logFailedScan(?int $userId, ?int $eventId, string $reason, Request $request, array $metadata = []): void
    {
        $coords = null;
        if ($request->has('latitude') && $request->has('longitude')) {
            $coords = $request->input('latitude') . ',' . $request->input('longitude');
        }

        $baseMetadata = [
            'event_id' => $eventId,
            'reason' => $reason,
        ];
        if ($coords) {
            $baseMetadata['student_coords'] = $coords;
        }

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'failed_scan',
            'description' => "Attendance scan rejected: {$reason}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => array_merge($baseMetadata, $metadata),
        ]);
    }
}
