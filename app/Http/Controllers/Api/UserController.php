<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * Administrator user management endpoint: paginated list with search, target field filter, sort, block, and year level filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['activeDevice']);

        // Search with target field selector (student_number, first_name, middle_name, last_name, or all)
        if ($search = $request->query('search')) {
            $search = trim($search);
            $searchField = $request->query('search_field', 'all');

            if (in_array($searchField, ['student_number', 'first_name', 'middle_name', 'last_name'])) {
                $query->where($searchField, 'like', "%{$search}%");
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('middle_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_number', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
        }

        // Filter by Role (admin, event_staff, student)
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        // Filter by Year Level (1st Year, 2nd Year, 3rd Year, 4th Year)
        if ($yearLevel = $request->query('year_level')) {
            $query->where('year_level', $yearLevel);
        }

        // Filter by Section / Block (e.g. BSIS 1-A, Block A)
        if ($sectionBlock = $request->query('section_block')) {
            $query->where('section_block', 'like', "%{$sectionBlock}%");
        }

        // Filter by Account Status (active, inactive, pending_onboarding)
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Sorting options: default to alphabetical by last name if year_level or section_block filter is active
        $hasClassFilter = !empty($yearLevel) || !empty($sectionBlock);
        $defaultSort = $hasClassFilter ? 'last_name' : 'created_at';
        $defaultOrder = $hasClassFilter ? 'asc' : 'desc';

        $sortBy = $request->query('sort_by', $defaultSort);
        $sortOrder = strtolower($request->query('sort_order', $defaultOrder)) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['first_name', 'last_name', 'student_number', 'email', 'role', 'year_level', 'section_block', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'last_name') {
                $query->orderBy('last_name', $sortOrder)->orderBy('first_name', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $counts = [
            'all' => User::count(),
            'students' => User::where('role', 'student')->count(),
            'event_staff' => User::where('role', 'event_staff')->count(),
            'admins' => User::where('role', 'admin')->count(),
        ];

        $perPageInput = $request->query('per_page', 25);
        $perPage = ($perPageInput === 'all' || (int) $perPageInput > 500) ? 500 : max(1, (int) $perPageInput);
        $page = max(1, (int) $request->query('page', 1));
        $users = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->successResponse([
            'users' => $users,
            'counts' => $counts,
        ], 'Users retrieved successfully.');
    }

    /**
     * View detailed user profile, device state, attendance summary, and audit logs.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'devices',
            'attendance.event',
            'auditLogs' => function ($q) {
                $q->orderBy('created_at', 'desc')->take(20);
            },
        ])->findOrFail($id);

        $activeDevice = $user->devices->firstWhere('status', 'active');
        $totalFines = $user->attendance->sum('fine_amount');
        $unpaidFines = $user->attendance->where('fine_paid', false)->sum('fine_amount');

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'student_number' => $user->student_number,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'year_level' => $user->year_level,
                'section_block' => $user->section_block,
                'status' => $user->status,
                'created_at' => $user->created_at,
            ],
            'active_device' => $activeDevice,
            'devices_history' => $user->devices,
            'attendance_summary' => [
                'total_events_attended' => $user->attendance->count(),
                'present_count' => $user->attendance->where('status', 'present')->count(),
                'late_count' => $user->attendance->where('status', 'late')->count(),
                'manual_override_count' => $user->attendance->where('status', 'manual_override')->count(),
                'total_fines' => $totalFines,
                'unpaid_fines' => $unpaidFines,
            ],
            'recent_audit_logs' => $user->auditLogs,
        ], 'User profile details retrieved successfully.');
    }

    /**
     * Update user details and account status.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $user = User::findOrFail($id);

        $user->fill($request->only([
            'student_number',
            'first_name',
            'middle_name',
            'last_name',
            'email',
            'role',
            'year_level',
            'section_block',
            'status',
        ]));

        if ($user->isDirty()) {
            $changes = $user->getDirty();
            $user->save();

            if ($user->status === 'active') {
                \Illuminate\Support\Facades\RateLimiter::clear("device_mismatch:{$user->id}");
            }

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'user_updated',
                'description' => "Administrator {$admin->full_name} updated profile for user {$user->full_name} (ID: {$user->id}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'target_user_id' => $user->id,
                    'changes' => $changes,
                ],
            ]);
        }

        return $this->successResponse($user, 'User account updated successfully.');
    }

    /**
     * Direct Administrator Device Reset Action.
     */
    public function resetDevice(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $user = User::findOrFail($id);

        return DB::transaction(function () use ($request, $admin, $user) {
            // Set all devices for student to inactive
            Device::where('user_id', $user->id)->update(['status' => 'inactive']);

            // Restore active status if account was suspended
            if ($user->status === 'suspended') {
                $user->status = 'active';
                $user->save();
            }
            \Illuminate\Support\Facades\RateLimiter::clear("device_mismatch:{$user->id}");

            // Send notification email to student informing them of device reset
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(
                    new \App\Mail\DeviceResetStatusMail($user, 'approved')
                );
            } catch (\Exception $e) {
                // Ignore mail failure in local/offline environments
            }

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'direct_device_reset',
                'description' => "Administrator {$admin->full_name} directly reset bound device for student {$user->full_name} ({$user->student_number}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'student_id' => $user->id,
                    'student_name' => $user->full_name,
                    'student_number' => $user->student_number,
                    'year_level' => $user->year_level,
                    'section_block' => $user->section_block,
                    'admin_name' => $admin->full_name,
                ],
            ]);

            return $this->successResponse([], "Device credentials for student {$user->full_name} have been reset by administrator.");
        });
    }

    /**
     * Administrator Direct / Unlimited Password Reset Action.
     * Bypasses the 7-day cooldown policy and allows instant manual override or custom/generated password assignment.
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $user = User::findOrFail($id);

        $request->validate([
            'new_password' => ['nullable', 'string', 'min:8'],
            'send_email_notification' => ['nullable', 'boolean'],
        ]);

        $newPassword = $request->input('new_password');
        $sendEmail = $request->boolean('send_email_notification', true);

        // If no custom password was supplied, auto-generate a strong password
        $isGenerated = false;
        if (empty($newPassword)) {
            $newPassword = 'Tpc#' . Str::random(8) . '!';
            $isGenerated = true;
        }

        $user->password = Hash::make($newPassword);
        if ($user->status === 'pending_onboarding') {
            $user->status = 'active';
        }
        $user->save();

        // Invalidate active Sanctum bearer tokens
        $user->tokens()->delete();

        // Dispatch email notification to user
        if ($sendEmail && $user->email) {
            try {
                Mail::to($user->email)->send(new \App\Mail\AdminPasswordResetNotificationMail($user, $newPassword, $admin));
            } catch (\Exception $e) {
                Log::error("Failed to send admin password reset notification email to {$user->email}: " . $e->getMessage());
            }
        }

        // Security Guarantee: Device binding remains preserved (intact in devices table)

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'admin_direct_password_reset',
            'description' => "Administrator {$admin->full_name} performed an unlimited direct password override for user {$user->full_name} ({$user->email}, Role: {$user->role}).",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'admin_id' => $admin->id,
                'target_user_id' => $user->id,
                'target_email' => $user->email,
                'role' => $user->role,
                'is_generated' => $isGenerated,
                'device_binding_preserved' => true,
            ],
        ]);

        return $this->successResponse([
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'new_password' => $newPassword,
            'is_generated' => $isGenerated,
        ], "Password for {$user->full_name} has been reset successfully by administrator.");
    }

    /**
     * Secure Delete / Drop single user with Administrator Password Verification.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return $this->errorResponse('Unauthorized. Only administrators can delete user accounts.', [], 403);
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($request->input('password'), $admin->password)) {
            return $this->errorResponse('Invalid administrator password. Deletion cancelled.', [], 401);
        }

        if ($admin->id === $id) {
            return $this->errorResponse('You cannot delete your own active administrator account.', [], 400);
        }

        $user = User::findOrFail($id);
        $userName = $user->full_name;
        $studentNumber = $user->student_number;
        $userRole = $user->role;

        DB::transaction(function () use ($user, $admin, $userName, $studentNumber, $userRole, $request) {
            // Remove associated relations
            Device::where('user_id', $user->id)->delete();
            \App\Models\OnboardingToken::where('user_id', $user->id)->delete();
            \App\Models\DeviceResetRequest::where('user_id', $user->id)->delete();
            \App\Models\Attendance::where('user_id', $user->id)->delete();
            \App\Models\AuditLog::where('user_id', $user->id)->delete();

            $user->delete();

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'user_deleted',
                'description' => "Administrator {$admin->full_name} deleted {$userRole} account for {$userName} ({$studentNumber}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'deleted_user_name' => $userName,
                    'deleted_student_number' => $studentNumber,
                    'deleted_role' => $userRole,
                ],
            ]);
        });

        return $this->successResponse([], "User account for {$userName} has been successfully deleted.");
    }

    /**
     * Secure Batch Delete users with Administrator Password Verification.
     */
    public function destroyBatch(Request $request): JsonResponse
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return $this->errorResponse('Unauthorized. Only administrators can delete user accounts.', [], 403);
        }

        $request->validate([
            'password' => 'required|string',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($request->input('password'), $admin->password)) {
            return $this->errorResponse('Invalid administrator password. Batch deletion cancelled.', [], 401);
        }

        $userIds = array_values(array_filter($request->input('user_ids'), fn($id) => (int)$id !== $admin->id));

        if (empty($userIds)) {
            return $this->errorResponse('No valid target users selected for deletion.', [], 400);
        }

        $users = User::whereIn('id', $userIds)->get();
        $deletedCount = 0;

        DB::transaction(function () use ($users, $admin, $request, &$deletedCount) {
            foreach ($users as $user) {
                Device::where('user_id', $user->id)->delete();
                \App\Models\OnboardingToken::where('user_id', $user->id)->delete();
                \App\Models\DeviceResetRequest::where('user_id', $user->id)->delete();
                \App\Models\Attendance::where('user_id', $user->id)->delete();
                \App\Models\AuditLog::where('user_id', $user->id)->delete();

                $user->delete();
                $deletedCount++;
            }

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'user_batch_deleted',
                'description' => "Administrator {$admin->full_name} batch deleted {$deletedCount} user account(s).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'deleted_count' => $deletedCount,
                    'deleted_user_ids' => $users->pluck('id')->toArray(),
                ],
            ]);
        });

        return $this->successResponse(['deleted_count' => $deletedCount], "{$deletedCount} user account(s) have been successfully deleted.");
    }
}
