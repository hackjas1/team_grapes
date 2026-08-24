<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewDeviceResetRequest;
use App\Http\Requests\Device\DeviceResetRequestSubmitRequest;
use App\Mail\DeviceResetStatusMail;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DeviceResetRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DeviceResetController extends Controller
{
    use ApiResponse;

    /**
     * Student submits device reset request.
     */
    public function requestReset(DeviceResetRequestSubmitRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check if student already has a pending reset request
        $existingPending = DeviceResetRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return $this->errorResponse('You already have a pending device reset request awaiting administrator approval.', [
                'pending_request' => $existingPending,
            ], 409);
        }

        $activeDevice = $user->devices()->where('status', 'active')->first();

        return DB::transaction(function () use ($request, $user, $activeDevice) {
            if ($activeDevice) {
                $activeDevice->status = 'pending_reset';
                $activeDevice->save();
            }

            $resetRequest = DeviceResetRequest::create([
                'user_id' => $user->id,
                'device_id' => $activeDevice?->id,
                'reason' => trim($request->input('reason')),
                'status' => 'pending',
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'device_reset_requested',
                'description' => "Student {$user->full_name} submitted a device reset request. Reason: {$resetRequest->reason}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([
                'reset_request' => $resetRequest,
            ], 'Device reset request submitted successfully. Awaiting administrator review.', 201);
        });
    }

    /**
     * Admin views device reset audit activity history and logs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user')
            ->whereIn('action', [
                'direct_device_reset',
                'device_reset_approved',
                'device_reset_rejected',
                'device_reset_requested'
            ]);

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($search = $request->query('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successResponse($logs, 'Device reset audit logs retrieved successfully.');
    }

    /**
     * Admin approves device reset request.
     */
    public function approve(ReviewDeviceResetRequest $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $resetRequest = DeviceResetRequest::with(['user', 'device'])->findOrFail($id);

        if ($resetRequest->status !== 'pending') {
            return $this->errorResponse("This request has already been processed with status '{$resetRequest->status}'.", [], 400);
        }

        return DB::transaction(function () use ($request, $admin, $resetRequest) {
            // 1. Invalidate active/pending device credentials for student
            Device::where('user_id', $resetRequest->user_id)
                ->update(['status' => 'inactive']);

            // If student account was blocked due to device mismatch attempts, restore to active
            if ($resetRequest->user && $resetRequest->user->status === 'suspended') {
                $resetRequest->user->status = 'active';
                $resetRequest->user->save();
            }
            \Illuminate\Support\Facades\RateLimiter::clear("device_mismatch:{$resetRequest->user_id}");

            // 2. Mark reset request as approved
            $resetRequest->status = 'approved';
            $resetRequest->reviewed_by = $admin->id;
            $resetRequest->reviewed_at = now();
            $resetRequest->save();

            // 3. Dispatch status notification email
            try {
                Mail::to($resetRequest->user->email)->send(
                    new DeviceResetStatusMail($resetRequest->user, 'approved')
                );
            } catch (\Exception $e) {
                // ignore mail exception in local env
            }

            // 4. Log audit action
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'device_reset_approved',
                'description' => "Administrator {$admin->full_name} approved device reset for student {$resetRequest->user->full_name} ({$resetRequest->user->student_number}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([
                'reset_request' => $resetRequest,
            ], "Device reset request approved. Student {$resetRequest->user->full_name} can now bind a new device.");
        });
    }

    /**
     * Admin rejects device reset request.
     */
    public function reject(ReviewDeviceResetRequest $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $resetRequest = DeviceResetRequest::with(['user', 'device'])->findOrFail($id);

        if ($resetRequest->status !== 'pending') {
            return $this->errorResponse("This request has already been processed with status '{$resetRequest->status}'.", [], 400);
        }

        $rejectionReason = $request->input('rejection_reason', 'Device reset request rejected by administrator.');

        return DB::transaction(function () use ($request, $admin, $resetRequest, $rejectionReason) {
            // Restore device status to active if pending_reset
            if ($resetRequest->device) {
                $resetRequest->device->status = 'active';
                $resetRequest->device->save();
            }

            $resetRequest->status = 'rejected';
            $resetRequest->reviewed_by = $admin->id;
            $resetRequest->reviewed_at = now();
            $resetRequest->rejection_reason = $rejectionReason;
            $resetRequest->save();

            try {
                Mail::to($resetRequest->user->email)->send(
                    new DeviceResetStatusMail($resetRequest->user, 'rejected', $rejectionReason)
                );
            } catch (\Exception $e) {
                // ignore mail exception in local env
            }

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'device_reset_rejected',
                'description' => "Administrator {$admin->full_name} rejected device reset for student {$resetRequest->user->full_name}. Reason: {$rejectionReason}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([
                'reset_request' => $resetRequest,
            ], 'Device reset request rejected.');
        });
    }
}
