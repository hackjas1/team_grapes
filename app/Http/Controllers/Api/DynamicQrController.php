<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Services\QrTokenService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicQrController extends Controller
{
    use ApiResponse;

    protected QrTokenService $qrTokenService;

    public function __construct(QrTokenService $qrTokenService)
    {
        $this->qrTokenService = $qrTokenService;
    }

    /**
     * Generate dynamic 20-second QR token for active event.
     */
    public function generate(Request $request, int $eventId): JsonResponse
    {
        $user = $request->user();
        $event = Event::with('staff')->findOrFail($eventId);

        // Security authorization check: Admin, Event Creator, or Assigned Event Staff
        if (!in_array($user->role, ['admin', 'event_staff'])) {
            return $this->errorResponse('Only administrators and event staff can generate QR codes.', [], 403);
        }

        if ($user->role === 'event_staff' && $event->staff()->exists() && !$event->staff->pluck('id')->contains($user->id) && $event->created_by !== $user->id) {
            return $this->errorResponse('You are not authorized to generate QR codes for this event.', [], 403);
        }

        if ($event->status !== 'active') {
            return $this->errorResponse("Cannot generate QR code for an inactive event (current status: {$event->status}).", [], 400);
        }

        $windowStatus = $event->getActiveWindowStatus();
        $isWindowOpen = $windowStatus['is_open'] || (bool) $event->allow_window_bypass;

        $tokenData = $isWindowOpen ? $this->qrTokenService->generateToken($event) : [
            'qr_token' => null,
            'expires_in_seconds' => 0,
            'expires_at' => null,
        ];

        return $this->successResponse([
            'event' => [
                'id' => $event->id,
                'uuid' => $event->uuid,
                'title' => $event->title,
                'venue_name' => $event->venue_name,
                'allow_window_bypass' => (bool) $event->allow_window_bypass,
                'checkin_start_time' => $event->checkin_start_time,
                'checkin_end_time' => $event->checkin_end_time,
                'checkout_start_time' => $event->checkout_start_time,
                'checkout_end_time' => $event->checkout_end_time,
            ],
            'window_status' => $windowStatus,
            'qr_token' => $tokenData['qr_token'],
            'expires_in_seconds' => $tokenData['expires_in_seconds'],
            'expires_at' => $tokenData['expires_at'],
        ], $isWindowOpen ? 'Dynamic QR code token generated successfully.' : 'Attendance window is currently closed.');
    }
}
