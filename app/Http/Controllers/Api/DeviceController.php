<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    use ApiResponse;

    /**
     * View devices for current user or target user (admin).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->has('user_id') && $user->role === 'admin') {
            $targetUser = User::findOrFail($request->query('user_id'));
            $devices = $targetUser->devices()->orderBy('created_at', 'desc')->get();
        } else {
            $devices = $user->devices()->orderBy('created_at', 'desc')->get();
        }

        return $this->successResponse($devices, 'Devices retrieved successfully.');
    }

    /**
     * Register or re-bind device credential for authenticated student.
     */
    public function bind(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'student') {
            return $this->errorResponse('Only students can bind attendance devices.', [], 403);
        }

        // Check if student already has an active device
        $activeDevice = $user->devices()->where('status', 'active')->first();

        if ($activeDevice) {
            return $this->errorResponse(
                'An active device is already bound to your account. You must request a device reset if you changed your device.',
                ['active_device' => $activeDevice],
                409
            );
        }

        $deviceCredential = (string) Str::uuid();
        $device = Device::create([
            'user_id' => $user->id,
            'device_credential' => $deviceCredential,
            'device_name' => $request->input('device_name', 'Authorized Mobile Device'),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'status' => 'active',
            'bound_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'device_bound',
            'description' => "Student {$user->full_name} bound new device: {$deviceCredential}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse([
            'device_credential' => $deviceCredential,
            'device' => $device,
        ], 'Device bound successfully.', 201);
    }
}
