<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\AssignEventStaffRequest;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventStaffController extends Controller
{
    use ApiResponse;

    /**
     * View staff assigned to a specific event.
     */
    public function index(int $eventId): JsonResponse
    {
        $event = Event::with('staff')->findOrFail($eventId);
        return $this->successResponse($event->staff, 'Assigned staff list retrieved successfully.');
    }

    /**
     * Admin assigns staff member to event.
     */
    public function store(AssignEventStaffRequest $request, int $eventId): JsonResponse
    {
        $admin = $request->user();
        $event = Event::findOrFail($eventId);
        $staffUser = User::findOrFail($request->input('user_id'));

        if (!in_array($staffUser->role, ['admin', 'event_staff'])) {
            return $this->errorResponse('Target user must be an administrator or event staff member.', [], 400);
        }

        if ($event->staff()->where('user_id', $staffUser->id)->exists()) {
            return $this->errorResponse('User is already assigned to this event.', [], 409);
        }

        $event->staff()->attach($staffUser->id);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'event_staff_assigned',
            'description' => "Administrator {$admin->full_name} assigned staff {$staffUser->full_name} to event '{$event->title}'.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse($event->load('staff'), 'Staff assigned to event successfully.');
    }

    /**
     * Admin removes staff assignment from event.
     */
    public function destroy(Request $request, int $eventId, int $userId): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return $this->errorResponse('Only administrators can remove assigned staff.', [], 403);
        }

        $event = Event::findOrFail($eventId);
        $staffUser = User::findOrFail($userId);

        $event->staff()->detach($staffUser->id);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'event_staff_removed',
            'description' => "Administrator {$admin->full_name} removed staff {$staffUser->full_name} from event '{$event->title}'.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse($event->load('staff'), 'Staff member removed from event.');
    }
}
