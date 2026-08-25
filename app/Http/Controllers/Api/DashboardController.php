<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSyncRecord;
use App\Models\DeviceResetRequest;
use App\Models\Event;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Desktop-optimized dashboard summary statistics and chart metrics for Admin and Event Staff.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $selectedEventId = $request->query('event_id');

        // Events list for dropdown selector
        $eventsQuery = Event::query();
        if ($user->role === 'event_staff') {
            $eventsQuery->where(function ($q) use ($user) {
                $q->whereHas('staff', function ($sub) use ($user) {
                    $sub->where('user_id', $user->id);
                })
                ->orDoesntHave('staff')
                ->orWhere('created_by', $user->id);
            });
        }
        $allEvents = $eventsQuery->orderByRaw("
            CASE 
                WHEN status = 'active' THEN 0 
                WHEN status = 'upcoming' THEN 1 
                ELSE 2 
            END ASC
        ")
        ->orderByRaw("
            CASE 
                WHEN status IN ('active', 'upcoming') THEN start_time 
            END ASC
        ")
        ->orderBy('start_time', 'desc')
        ->get(['id', 'title', 'status', 'session_type', 'start_time', 'end_time']);

        // Determine which event to focus on
        $selectedEvent = null;
        if ($selectedEventId && is_numeric($selectedEventId)) {
            $selectedEvent = $allEvents->firstWhere('id', (int) $selectedEventId);
        }
        if (!$selectedEvent && $allEvents->isNotEmpty()) {
            $selectedEvent = $allEvents->firstWhere('status', 'active') ?? $allEvents->first();
        }

        // Target students count
        $activeStudentsCount = User::where('role', 'student')->where('status', 'active')->count();

        // Query attendance for selected event
        $attendanceQuery = Attendance::with(['user', 'event']);
        if ($selectedEvent) {
            $attendanceQuery->where('event_id', $selectedEvent->id);
        } else {
            $attendanceQuery->whereIn('event_id', $allEvents->pluck('id'));
        }

        $allAttendance = $attendanceQuery->get();

        $presentCount = $allAttendance->where('status', 'present')->count();
        $lateCount = $allAttendance->where('status', 'late')->count();
        $overrideCount = $allAttendance->where('status', 'manual_override')->count();
        $explicitAbsentCount = $allAttendance->where('status', 'absent')->count();

        $totalAttended = $presentCount + $lateCount + $overrideCount;
        $totalAbsent = max(0, $activeStudentsCount - $totalAttended);
        if ($explicitAbsentCount > $totalAbsent) {
            $totalAbsent = $explicitAbsentCount;
        }

        $turnoutRate = $activeStudentsCount > 0 ? round(($totalAttended / $activeStudentsCount) * 100, 1) : 0;

        // Session slots breakdown
        $isWholeDay = $selectedEvent && $selectedEvent->session_type === 'whole_day';
        $sessionTurnout = [];

        if ($isWholeDay) {
            $amInCount = $allAttendance->filter(fn($a) => ($a->am_time_in || $a->scan_time) && ($a->slot_statuses['am_in'] ?? null) !== 'missed')->count();
            $amOutCount = $allAttendance->filter(fn($a) => $a->am_time_out && ($a->slot_statuses['am_out'] ?? null) !== 'missed')->count();
            $pmInCount = $allAttendance->filter(fn($a) => $a->pm_time_in && ($a->slot_statuses['pm_in'] ?? null) !== 'missed')->count();
            $pmOutCount = $allAttendance->filter(fn($a) => ($a->pm_time_out || $a->checkout_time) && ($a->slot_statuses['pm_out'] ?? null) !== 'missed')->count();

            $sessionTurnout = [
                'labels' => ['AM Time-In', 'AM Time-Out', 'PM Time-In', 'PM Time-Out'],
                'counts' => [$amInCount, $amOutCount, $pmInCount, $pmOutCount],
            ];
        } else {
            $checkinCount = $allAttendance->filter(fn($a) => ($a->scan_time || $a->am_time_in) && ($a->slot_statuses['checkin'] ?? null) !== 'missed')->count();
            $checkoutCount = $allAttendance->filter(fn($a) => ($a->checkout_time || $a->pm_time_out) && ($a->slot_statuses['checkout'] ?? null) !== 'missed')->count();

            $sessionTurnout = [
                'labels' => ['Time-In', 'Time-Out'],
                'counts' => [$checkinCount, $checkoutCount],
            ];
        }

        // Breakdown per year level for selected event
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $yearLevelStats = [];
        foreach ($yearLevels as $yl) {
            $studentsInYl = User::where('role', 'student')->where('status', 'active')->where('year_level', $yl)->pluck('id');
            $totalInYl = $studentsInYl->count();
            $attendedInYl = $allAttendance->whereIn('user_id', $studentsInYl)->whereIn('status', ['present', 'late', 'manual_override'])->count();
            $rateInYl = $totalInYl > 0 ? round(($attendedInYl / $totalInYl) * 100, 1) : 0;
            $yearLevelStats[] = [
                'year_level' => $yl,
                'total_students' => $totalInYl,
                'attended_students' => $attendedInYl,
                'turnout_percentage' => $rateInYl,
            ];
        }

        // Active events count
        $activeEventsCount = $allEvents->where('status', 'active')->count();

        // Unpaid fines sum
        $unpaidFinesQuery = Attendance::where('fine_amount', '>', 0)->where('fine_paid', false);
        if ($selectedEvent) {
            $unpaidFinesQuery->where('event_id', $selectedEvent->id);
        } else {
            $unpaidFinesQuery->whereIn('event_id', $allEvents->pluck('id'));
        }
        $totalUnpaidFines = (float) $unpaidFinesQuery->sum('fine_amount');

        // Recent Scans
        $recentScansQuery = Attendance::with(['event', 'user']);
        if ($selectedEvent) {
            $recentScansQuery->where('event_id', $selectedEvent->id);
        } else {
            $recentScansQuery->whereIn('event_id', $allEvents->pluck('id'));
        }

        $recentScans = $recentScansQuery->orderByRaw('COALESCE(pm_time_out, pm_time_in, am_time_out, am_time_in, checkout_time, scan_time, created_at) DESC')
            ->take(10)
            ->get()
            ->map(function ($scan) {
                $latestTime = $scan->pm_time_out ?? $scan->pm_time_in ?? $scan->am_time_out ?? $scan->am_time_in ?? $scan->checkout_time ?? $scan->scan_time;
                return [
                    'id' => $scan->id,
                    'event_id' => $scan->event_id,
                    'event_title' => $scan->event ? $scan->event->title : 'Event',
                    'student_number' => $scan->user ? ($scan->user->student_number ?? 'N/A') : 'N/A',
                    'student_name' => $scan->user ? $scan->user->full_name : 'Unknown User',
                    'year_level' => $scan->user ? $scan->user->year_level : 'N/A',
                    'section_block' => $scan->user ? $scan->user->section_block : 'N/A',
                    'status' => $scan->status,
                    'scan_time' => $latestTime ? $latestTime->format('h:i:s A') : '—',
                    'distance_meters' => $scan->distance_meters,
                    'fine_amount' => $scan->fine_amount,
                ];
            });

        return $this->successResponse([
            'events_list' => $allEvents,
            'selected_event' => $selectedEvent,
            'active_events_count' => $activeEventsCount,
            'total_target_students' => $activeStudentsCount,
            'present_count' => $presentCount,
            'late_count' => $lateCount,
            'absent_count' => $totalAbsent,
            'manual_override_count' => $overrideCount,
            'total_attended' => $totalAttended,
            'attendance_rate_percentage' => $turnoutRate,
            'total_unpaid_fines' => $totalUnpaidFines,
            'session_turnout' => $sessionTurnout,
            'year_level_stats' => $yearLevelStats,
            'recent_scans' => $recentScans,
        ], 'Dashboard statistics retrieved successfully.');
    }

    /**
     * Live attendance monitoring feed for active event (supports incremental polling via last_scan_id).
     */
    public function liveAttendance(Request $request, int $eventId): JsonResponse
    {
        $user = $request->user();
        $event = Event::with('staff')->findOrFail($eventId);

        if ($user->role === 'event_staff' && $event->staff()->exists() && !$event->staff->pluck('id')->contains($user->id) && $event->created_by !== $user->id) {
            return $this->errorResponse('You are not authorized to monitor this event.', [], 403);
        }

        $query = Attendance::with('user')->where('event_id', $event->id);

        // Incremental polling support
        if ($lastScanId = $request->query('last_scan_id')) {
            $query->where('id', '>', (int) $lastScanId);
        }

        $scans = $query->orderByRaw('COALESCE(pm_time_out, pm_time_in, am_time_out, am_time_in, checkout_time, scan_time) DESC')->get()->map(function ($scan) {
            $timestamps = [
                'PM Time-Out' => $scan->pm_time_out,
                'PM Time-In' => $scan->pm_time_in,
                'AM Time-Out' => $scan->am_time_out,
                'AM Time-In' => $scan->am_time_in,
                'Time-Out' => $scan->checkout_time,
                'Time-In' => $scan->scan_time,
            ];

            $latestSlot = null;
            $latestTime = null;
            foreach ($timestamps as $slotName => $t) {
                if ($t && ($latestTime === null || $t->gt($latestTime))) {
                    $latestTime = $t;
                    $latestSlot = $slotName;
                }
            }

            $scanType = $latestSlot ?: ($scan->verification_data['scan_type'] ?? 'Time-In');
            $formattedLatestTime = $latestTime ? $latestTime->format('h:i:s A') : ($scan->scan_time ? $scan->scan_time->format('h:i:s A') : '—');

            return [
                'id' => $scan->id,
                'student_number' => $scan->user ? $scan->user->student_number : 'N/A',
                'student_name' => $scan->user ? $scan->user->full_name : 'Unknown User',
                'email' => $scan->user ? $scan->user->email : '',
                'scan_type' => $scanType,
                'scan_time' => $scan->scan_time ? $scan->scan_time->format('Y-m-d H:i:s') : null,
                'formatted_time' => $formattedLatestTime,
                'checkout_time' => $scan->checkout_time ? $scan->checkout_time->format('Y-m-d H:i:s') : null,
                'formatted_checkout_time' => $scan->checkout_time ? $scan->checkout_time->format('h:i:s A') : '',
                'am_time_in' => $scan->am_time_in ? $scan->am_time_in->format('Y-m-d H:i:s') : null,
                'formatted_am_in' => $scan->am_time_in ? $scan->am_time_in->format('h:i:s A') : '',
                'am_time_out' => $scan->am_time_out ? $scan->am_time_out->format('Y-m-d H:i:s') : null,
                'formatted_am_out' => $scan->am_time_out ? $scan->am_time_out->format('h:i:s A') : '',
                'pm_time_in' => $scan->pm_time_in ? $scan->pm_time_in->format('Y-m-d H:i:s') : null,
                'formatted_pm_in' => $scan->pm_time_in ? $scan->pm_time_in->format('h:i:s A') : '',
                'pm_time_out' => $scan->pm_time_out ? $scan->pm_time_out->format('Y-m-d H:i:s') : null,
                'formatted_pm_out' => $scan->pm_time_out ? $scan->pm_time_out->format('h:i:s A') : '',
                'slot_statuses' => $scan->slot_statuses,
                'status' => $scan->status,
                'fine_amount' => $scan->fine_amount,
                'distance_meters' => $scan->distance_meters,
                'device_credential' => $scan->device_credential,
                'is_offline_sync' => $scan->is_offline_sync,
            ];
        });

        $allAttendance = Attendance::where('event_id', $event->id)->get();
        $latestScan = $allAttendance->sortByDesc('id')->first();

        return $this->successResponse([
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'venue_name' => $event->venue_name,
            ],
            'statistics' => [
                'total_scanned' => $allAttendance->count(),
                'present_count' => $allAttendance->where('status', 'present')->count(),
                'late_count' => $allAttendance->where('status', 'late')->count(),
                'manual_override_count' => $allAttendance->where('status', 'manual_override')->count(),
            ],
            'latest_scan_id' => $latestScan?->id ?? 0,
            'scans' => $scans,
        ], 'Live attendance monitoring data retrieved.');
    }
}
