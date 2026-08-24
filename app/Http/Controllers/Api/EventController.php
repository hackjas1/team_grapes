<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Models\AuditLog;
use App\Models\Event;
use App\Services\AbsenceProcessorService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EventController extends Controller
{
    use ApiResponse;

    /**
     * List events based on role and request filters.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Event::with(['creator', 'staff']);

        // Role-based visibility scoping
        if ($user->role === 'event_staff') {
            $query->where(function ($q) use ($user) {
                $q->whereHas('staff', function ($sub) use ($user) {
                    $sub->where('user_id', $user->id);
                })
                ->orDoesntHave('staff')
                ->orWhere('created_by', $user->id);
            });
        } elseif ($user->role === 'student') {
            $query->whereIn('status', ['active', 'upcoming', 'completed']);
            if ($user->year_level) {
                $query->where(function ($q) use ($user) {
                    $q->whereNull('target_year_levels')
                      ->orWhereJsonContains('target_year_levels', 'All')
                      ->orWhereJsonContains('target_year_levels', $user->year_level);
                });
            }
        }

        // Search title, venue, or description
        if ($search = $request->query('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('venue_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter status (upcoming, active, completed)
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Order priority: Active (1) -> Upcoming (2) -> Completed (3) -> Cancelled/Other (4)
        $query->orderByRaw("CASE WHEN status = 'active' THEN 1 WHEN status = 'upcoming' THEN 2 WHEN status = 'completed' THEN 3 ELSE 4 END");

        // Sorting options within status groups
        $sortBy = $request->query('sort_by');
        $sortOrder = $request->query('sort_order', 'desc');
        if ($sortBy && str_contains($sortBy, ':')) {
            [$sortBy, $sortOrder] = explode(':', $sortBy, 2);
        }
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['start_time', 'end_time', 'created_at', 'title', 'fine_amount', 'status'];
        if ($sortBy && in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('start_time', 'desc');
        }

        $events = $query->paginate($request->query('per_page', 50));

        return $this->successResponse($events, 'Events retrieved successfully.');
    }

    /**
     * Admin creates a new event.
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $user = $request->user();
        $title = trim($request->input('title'));
        $startTime = $request->input('start_time');
        $eventDate = date('Y-m-d', strtotime($startTime));

        // Prevent Duplicate Events with identical title on the same date
        $existingDuplicate = Event::where('title', $title)
            ->whereDate('start_time', $eventDate)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingDuplicate) {
            return $this->errorResponse(
                "Duplicate Event Detected: An event titled '{$existingDuplicate->title}' is already scheduled on {$eventDate}. Duplicate event creation has been prevented.",
                [
                    'title' => ["An event titled '{$existingDuplicate->title}' already exists on {$eventDate}."],
                    'start_time' => ["An active/upcoming event already exists on {$eventDate}."]
                ],
                422
            );
        }

        return DB::transaction(function () use ($request, $user) {
            $sessionType = $request->input('session_type', 'half_day');
            $finePerSlot = $request->input('fine_per_slot');
            $fineAmount = $request->input('fine_amount', $finePerSlot ?: 0.00);

            $event = Event::create([
                'uuid' => (string) Str::uuid(),
                'title' => trim($request->input('title')),
                'description' => $request->input('description') ? trim($request->input('description')) : null,
                'session_type' => $sessionType,
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
                'checkin_start_time' => $request->input('checkin_start_time'),
                'checkin_end_time' => $request->input('checkin_end_time'),
                'checkout_start_time' => $request->input('checkout_start_time'),
                'checkout_end_time' => $request->input('checkout_end_time'),
                'am_checkin_start_time' => $request->input('am_checkin_start_time'),
                'am_checkin_end_time' => $request->input('am_checkin_end_time'),
                'am_checkout_start_time' => $request->input('am_checkout_start_time'),
                'am_checkout_end_time' => $request->input('am_checkout_end_time'),
                'pm_checkin_start_time' => $request->input('pm_checkin_start_time'),
                'pm_checkin_end_time' => $request->input('pm_checkin_end_time'),
                'pm_checkout_start_time' => $request->input('pm_checkout_start_time'),
                'pm_checkout_end_time' => $request->input('pm_checkout_end_time'),
                'allow_window_bypass' => $request->input('allow_window_bypass', false),
                'target_year_levels' => $request->input('target_year_levels', ['All']),
                'venue_name' => trim($request->input('venue_name')),
                'venue_latitude' => $request->input('venue_latitude'),
                'venue_longitude' => $request->input('venue_longitude'),
                'allowed_radius_meters' => $request->input('allowed_radius_meters', 50.00),
                'fine_amount' => $fineAmount,
                'fine_per_slot' => $finePerSlot,
                'status' => $request->input('status', 'upcoming'),
                'created_by' => $user->id,
            ]);

            // Attach initial assigned staff
            if ($staffIds = $request->input('staff_ids')) {
                $event->staff()->sync($staffIds);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'event_created',
                'description' => "Administrator {$user->full_name} created event '{$event->title}' (ID: {$event->id}, Type: {$event->session_type}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'event_id' => $event->id,
                    'session_type' => $event->session_type,
                    'target_year_levels' => $event->target_year_levels,
                    'venue' => $event->venue_name,
                    'coordinates' => "{$event->venue_latitude},{$event->venue_longitude}",
                ],
            ]);

            return $this->successResponse($event->load(['creator', 'staff']), 'Event created successfully.', 201);
        });
    }

    /**
     * View detailed event information.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $event = Event::with(['creator', 'staff', 'attendance.user'])->findOrFail($id);

        // Security check for event_staff (allow viewing if assigned, if created by user, or if open to all staff)
        if ($user->role === 'event_staff' && $event->staff()->exists() && !$event->staff->pluck('id')->contains($user->id) && $event->created_by !== $user->id) {
            return $this->errorResponse('You are not assigned to manage this event.', [], 403);
        }

        $presentCount = $event->attendance->where('status', 'present')->count();
        $lateCount = $event->attendance->where('status', 'late')->count();
        $overrideCount = $event->attendance->where('status', 'manual_override')->count();
        $totalScanned = $event->attendance->count();
        $totalFines = (float) $event->attendance->sum('fine_amount');

        return $this->successResponse([
            'event' => $event,
            'target_audience_label' => $event->getTargetAudienceLabel(),
            'window_status' => $event->getActiveWindowStatus(),
            'statistics' => [
                'total_attendance' => $totalScanned,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'manual_override_count' => $overrideCount,
                'total_fines' => $totalFines,
            ],
        ], 'Event details retrieved successfully.');
    }

    /**
     * Admin or assigned staff updates event details.
     */
    public function update(UpdateEventRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $event = Event::findOrFail($id);

        if ($request->has('title') || $request->has('start_time')) {
            $newTitle = $request->has('title') ? trim($request->input('title')) : $event->title;
            $newStartTime = $request->has('start_time') ? $request->input('start_time') : $event->start_time;
            $eventDate = date('Y-m-d', strtotime($newStartTime));

            $existingDuplicate = Event::where('title', $newTitle)
                ->whereDate('start_time', $eventDate)
                ->where('id', '!=', $id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($existingDuplicate) {
                return $this->errorResponse(
                    "Duplicate Event: Another event titled '{$existingDuplicate->title}' is already scheduled on {$eventDate}.",
                    [
                        'title' => ["Another event titled '{$existingDuplicate->title}' already exists on {$eventDate}."]
                    ],
                    422
                );
            }
        }

        $event->fill($request->only([
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
            'target_year_levels',
            'venue_name',
            'venue_latitude',
            'venue_longitude',
            'allowed_radius_meters',
            'fine_amount',
            'fine_per_slot',
            'status',
        ]));

        if ($request->has('fine_per_slot') && !$request->has('fine_amount')) {
            $event->fine_amount = $request->input('fine_per_slot');
        }

        if ($event->isDirty()) {
            $isBecomingCompleted = $event->isDirty('status') && $event->status === 'completed';
            $changes = $event->getDirty();
            $event->save();

            $absenceStats = null;
            if ($isBecomingCompleted) {
                $absenceService = app(AbsenceProcessorService::class);
                $absenceStats = $absenceService->processEventAbsences($event, $user);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'event_updated',
                'description' => "User {$user->full_name} updated event '{$event->title}' (ID: {$event->id})" . ($absenceStats ? ". Auto-processed {$absenceStats['absent_records_created']} absences." : "."),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'event_id' => $event->id,
                    'changes' => $changes,
                    'absence_stats' => $absenceStats,
                ],
            ]);
        }

        return $this->successResponse($event->load(['creator', 'staff']), 'Event updated successfully.');
    }

    /**
     * Admin cancels or deletes event.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return $this->errorResponse('Only administrators can delete events.', [], 403);
        }

        $event = Event::findOrFail($id);
        $title = $event->title;

        if ($request->filled('password') && !\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
            return $this->errorResponse('Invalid administrator password verification.', [], 403);
        }

        DB::transaction(function () use ($event, $user, $title, $request) {
            \App\Models\Attendance::where('event_id', $event->id)->delete();
            $event->staff()->detach();
            $event->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'event_deleted',
                'description' => "Administrator {$user->full_name} deleted event '{$title}' (ID: {$event->id}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return $this->successResponse([], "Event '{$title}' has been deleted successfully.");
    }

    /**
     * Admin batch deletes multiple events.
     */
    public function destroyBatch(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return $this->errorResponse('Only administrators can delete events.', [], 403);
        }

        if ($request->filled('password') && !\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
            return $this->errorResponse('Invalid administrator password verification.', [], 403);
        }

        $request->validate([
            'event_ids' => 'required|array|min:1',
            'event_ids.*' => 'integer|exists:events,id',
        ]);

        $eventIds = $request->input('event_ids');
        $deletedCount = 0;

        DB::transaction(function () use ($eventIds, $user, $request, &$deletedCount) {
            $events = Event::whereIn('id', $eventIds)->get();
            foreach ($events as $event) {
                \App\Models\Attendance::where('event_id', $event->id)->delete();
                $event->staff()->detach();
                $event->delete();
                $deletedCount++;
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'event_batch_deleted',
                'description' => "Administrator {$user->full_name} batch deleted {$deletedCount} event(s).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return $this->successResponse(['deleted_count' => $deletedCount], "{$deletedCount} event(s) have been deleted successfully.");
    }

    /**
     * Activate event session.
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $event = Event::findOrFail($id);

        if ($user->role === 'event_staff' && $event->staff()->exists() && !$event->staff()->where('user_id', $user->id)->exists() && $event->created_by !== $user->id) {
            return $this->errorResponse('You are not authorized to activate this event.', [], 403);
        }

        $event->status = 'active';
        $event->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'event_activated',
            'description' => "Event '{$event->title}' activated by {$user->full_name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse($event, 'Event session is now active.');
    }

    /**
     * Complete event session and auto-process absences (Requires password confirmation).
     */
    public function complete(Request $request, int $id, AbsenceProcessorService $absenceService): JsonResponse
    {
        $user = $request->user();
        $event = Event::findOrFail($id);

        if ($user->role === 'event_staff' && $event->staff()->exists() && !$event->staff()->where('user_id', $user->id)->exists() && $event->created_by !== $user->id) {
            return $this->errorResponse('You are not authorized to complete this event.', [], 403);
        }

        if ($event->status === 'completed') {
            return $this->errorResponse('This event has already been marked as completed.', [
                'error_code' => 'EVENT_ALREADY_COMPLETED'
            ], 422);
        }

        $password = $request->input('password');
        if (empty($password)) {
            return $this->errorResponse('Password is required to confirm event completion and finalize attendance penalties.', [
                'error_code' => 'PASSWORD_REQUIRED'
            ], 422);
        }

        if (!Hash::check($password, $user->password)) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'event_complete_failed_auth',
                'description' => "Failed event completion authorization attempt for event '{$event->title}' by {$user->full_name} (Invalid password).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'event_id' => $event->id,
                ],
            ]);

            return $this->errorResponse('Incorrect password. Event completion authorization failed.', [
                'error_code' => 'INVALID_PASSWORD'
            ], 422);
        }

        $event->status = 'completed';
        $event->allow_window_bypass = false;
        $event->bypass_expires_at = null;
        $event->save();

        // Auto-process absences and generate non-attendance fines for eligible students who did not scan
        $absenceStats = $absenceService->processEventAbsences($event, $user);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'event_completed',
            'description' => "Event '{$event->title}' marked completed by {$user->full_name} with verified password authorization. Auto-processed {$absenceStats['absent_records_created']} absence records.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'event_id' => $event->id,
                'absence_stats' => $absenceStats,
            ],
        ]);

        return $this->successResponse([
            'event' => $event,
            'absence_stats' => $absenceStats,
        ], "Event session marked as completed. Auto-processed {$absenceStats['absent_records_created']} absent student(s) with non-attendance fines.");
    }

    /**
     * Manually trigger absence fine calculation for an event.
     */
    public function processAbsences(Request $request, int $id, AbsenceProcessorService $absenceService): JsonResponse
    {
        $user = $request->user();
        $event = Event::findOrFail($id);

        if ($user->role === 'event_staff' && $event->staff()->exists() && !$event->staff()->where('user_id', $user->id)->exists() && $event->created_by !== $user->id) {
            return $this->errorResponse('You are not authorized to process absences for this event.', [], 403);
        }

        $absenceStats = $absenceService->processEventAbsences($event, $user);

        return $this->successResponse([
            'event' => $event,
            'absence_stats' => $absenceStats,
        ], "Absence processing complete: {$absenceStats['absent_records_created']} absence record(s) created (Total Fines: ₱" . number_format($absenceStats['total_fines_generated'], 2) . ").");
    }

    /**
     * Toggle emergency window bypass mode for this event (Requires password confirmation when enabling).
     */
    public function toggleBypass(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $event = Event::findOrFail($id);

        if ($user->role === 'event_staff' && $event->staff()->exists() && !$event->staff()->where('user_id', $user->id)->exists() && $event->created_by !== $user->id) {
            return $this->errorResponse('You are not authorized to control this event.', [], 403);
        }

        // If turning ON bypass, validate quota, reason, duration, and password
        if (!$event->allow_window_bypass) {
            // Check quota for event staff (max 2 activations per event)
            if ($user->role !== 'admin' && ($event->bypass_count ?? 0) >= 2) {
                return $this->errorResponse("Maximum Emergency Bypass limit reached for this event (2 of 2 used). Please contact the Lead Administrator.", [
                    'error_code' => 'BYPASS_QUOTA_EXCEEDED',
                    'bypass_count' => (int) $event->bypass_count,
                ], 422);
            }

            // Duration selection: 15, 20, 30, or 60 minutes (1 hour)
            $durationMinutes = (int) $request->input('duration_minutes', 20);
            if (!in_array($durationMinutes, [15, 20, 30, 60])) {
                $durationMinutes = 20;
            }

            $reason = trim($request->input('reason', ''));
            if (empty($reason)) {
                return $this->errorResponse('Please specify a brief reason for enabling Emergency Bypass.', [
                    'error_code' => 'REASON_REQUIRED'
                ], 422);
            }

            $password = $request->input('password');
            if (empty($password)) {
                return $this->errorResponse('Password is required to authorize Emergency Window Bypass.', [
                    'error_code' => 'PASSWORD_REQUIRED'
                ], 422);
            }

            if (!Hash::check($password, $user->password)) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'event_bypass_failed_auth',
                    'description' => "Failed Emergency Bypass authorization attempt for event '{$event->title}' by {$user->full_name} (Invalid password).",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'event_id' => $event->id,
                        'reason' => $reason,
                    ],
                ]);

                return $this->errorResponse('Incorrect password. Emergency Bypass authorization failed.', [
                    'error_code' => 'INVALID_PASSWORD'
                ], 422);
            }

            $event->allow_window_bypass = true;
            $event->bypass_expires_at = now()->addMinutes($durationMinutes);
            $event->bypass_count = (int) ($event->bypass_count ?? 0) + 1;
            $event->bypass_reason = $reason;
            $event->save();

            $statusStr = 'ENABLED';
            $durationStr = "{$durationMinutes} minutes (expires at " . $event->bypass_expires_at->format('h:i A') . ")";

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'event_bypass_toggled',
                'description' => "Emergency Window Bypass {$statusStr} for event '{$event->title}' by {$user->full_name}. Duration: {$durationStr}. Reason: '{$reason}'. Activation {$event->bypass_count} of 2.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'event_id' => $event->id,
                    'allow_window_bypass' => true,
                    'duration_minutes' => $durationMinutes,
                    'expires_at' => $event->bypass_expires_at->toIso8601String(),
                    'reason' => $reason,
                    'bypass_count' => $event->bypass_count,
                ],
            ]);
        } else {
            $event->allow_window_bypass = false;
            $event->bypass_expires_at = null;
            $event->save();

            $statusStr = 'DISABLED';

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'event_bypass_toggled',
                'description' => "Emergency Window Bypass {$statusStr} manually for event '{$event->title}' by {$user->full_name}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'event_id' => $event->id,
                    'allow_window_bypass' => false,
                ],
            ]);
        }

        return $this->successResponse([
            'allow_window_bypass' => $event->allow_window_bypass,
            'window_status' => $event->getActiveWindowStatus(),
            'event' => $event
        ], "Emergency Window Bypass is now {$statusStr}.");
    }
}
