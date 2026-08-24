<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FineController extends Controller
{
    use ApiResponse;

    /**
     * List all fine records with search, target search field, year level, section/block, and payment status filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Attendance::with(['event', 'user'])
            ->where('fine_amount', '>', 0);

        // Role restriction
        if ($user->role === 'student') {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'event_staff') {
            $query->whereHas('event', function ($eq) use ($user) {
                $eq->where(function ($sub) use ($user) {
                    $sub->whereHas('staff', function ($st) use ($user) {
                        $st->where('user_id', $user->id);
                    })
                    ->orDoesntHave('staff')
                    ->orWhere('created_by', $user->id);
                });
            });
        }

        // Filter by Fine Payment Status
        if ($request->has('fine_paid') && $request->query('fine_paid') !== '' && $request->query('fine_paid') !== 'all') {
            $query->where('fine_paid', filter_var($request->query('fine_paid'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($studentId = $request->query('user_id')) {
            $query->where('user_id', $studentId);
        }

        // Filter by Year Level on Student profile
        if ($yearLevel = $request->query('year_level')) {
            $query->whereHas('user', function ($q) use ($yearLevel) {
                $q->where('year_level', $yearLevel);
            });
        }

        // Filter by Section / Block on Student profile
        if ($sectionBlock = $request->query('section_block')) {
            $query->whereHas('user', function ($q) use ($sectionBlock) {
                $q->where('section_block', 'like', "%{$sectionBlock}%");
            });
        }

        // Search with target field selector (student_number, first_name, last_name, middle_name, or all)
        if ($search = $request->query('search')) {
            $search = trim($search);
            $searchField = $request->query('search_field', 'all');

            $query->whereHas('user', function ($q) use ($search, $searchField) {
                if (in_array($searchField, ['student_number', 'first_name', 'middle_name', 'last_name'])) {
                    $q->where($searchField, 'like', "%{$search}%");
                } else {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('student_number', 'like', "%{$search}%");
                    });
                }
            });
        }

        // Sorting options
        $sortBy = $request->query('sort_by', 'default');
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'fine_amount') {
            $query->orderBy('fine_amount', $sortOrder);
        } elseif ($sortBy === 'scan_time') {
            $query->orderByRaw("COALESCE(pm_time_out, pm_time_in, am_time_out, am_time_in, checkout_time, scan_time) {$sortOrder}");
        } else {
            // Default sorting: UNPAID (0) on top, PAID (1) below, then most recent
            $query->orderBy('fine_paid', 'asc')
                  ->orderByRaw("COALESCE(pm_time_out, pm_time_in, am_time_out, am_time_in, checkout_time, scan_time) DESC");
        }

        $perPage = (int) $request->query('per_page', 20);
        $fines = $query->paginate($perPage);

        $totalFinesSum = (float) (clone $query)->sum('fine_amount');
        $unpaidFinesSum = (float) (clone $query)->where('fine_paid', false)->sum('fine_amount');

        return $this->successResponse([
            'fines' => $fines,
            'summary' => [
                'total_fines_amount' => $totalFinesSum,
                'unpaid_fines_amount' => $unpaidFinesSum,
            ],
        ], 'Fines retrieved successfully.');
    }

    /**
     * View fine summary and history for a specific student.
     */
    public function getStudentFines(Request $request, int $studentId): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'student' && $user->id !== $studentId) {
            return $this->errorResponse('Forbidden. You can only view your own fines.', [], 403);
        }

        $student = User::findOrFail($studentId);

        // Fetch fines: Unpaid first (fine_paid = false), Paid below (fine_paid = true)
        $fines = Attendance::with('event')
            ->where('user_id', $student->id)
            ->where(function ($q) {
                $q->where('fine_amount', '>', 0)
                  ->orWhere('fine_paid', true);
            })
            ->orderBy('fine_paid', 'asc')
            ->orderByRaw("COALESCE(pm_time_out, pm_time_in, am_time_out, am_time_in, checkout_time, scan_time) DESC")
            ->get();

        $totalFines = (float) $fines->sum('fine_amount');
        $unpaidFines = (float) $fines->where('fine_paid', false)->sum('fine_amount');
        $paidFines = (float) $fines->where('fine_paid', true)->sum('fine_amount');

        return $this->successResponse([
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'year_level' => $student->year_level,
                'section_block' => $student->section_block,
            ],
            'summary' => [
                'total_fines' => $totalFines,
                'unpaid_fines' => $unpaidFines,
                'paid_fines' => $paidFines,
                'unpaid_count' => $fines->where('fine_paid', false)->count(),
            ],
            'fines_history' => $fines,
        ], 'Student fines summary retrieved successfully.');
    }

    /**
     * Admin or Event Staff marks fine as paid.
     */
    public function payFine(Request $request, int $attendanceId): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'event_staff'])) {
            return $this->errorResponse('Only administrators and event staff can record fine payments.', [], 403);
        }

        $attendance = Attendance::with(['event', 'user'])->findOrFail($attendanceId);

        if ($attendance->fine_amount <= 0) {
            return $this->errorResponse('This attendance record has no fine incurred.', [], 400);
        }

        if ($attendance->fine_paid) {
            return $this->errorResponse('This fine has already been marked as paid.', [], 409);
        }

        $attendance->fine_paid = true;
        
        $meta = $attendance->verification_data ?? [];
        $meta['payment_details'] = [
            'paid_at' => now()->toIso8601String(),
            'received_by_id' => $user->id,
            'received_by_name' => $user->full_name,
        ];
        $attendance->verification_data = $meta;
        $attendance->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'fine_paid',
            'description' => "Fine of ₱{$attendance->fine_amount} for student {$attendance->user->full_name} ({$attendance->user->student_number}) at event '{$attendance->event->title}' marked as PAID by {$user->full_name}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'attendance_id' => $attendance->id,
                'amount' => $attendance->fine_amount,
                'student_id' => $attendance->user_id,
            ],
        ]);

        return $this->successResponse($attendance, 'Fine marked as paid successfully.');
    }

    /**
     * Batch mark multiple fines as paid.
     */
    public function payBatch(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'event_staff'])) {
            return $this->errorResponse('Only administrators and event staff can record fine payments.', [], 403);
        }

        $request->validate([
            'attendance_ids' => 'required|array|min:1',
            'attendance_ids.*' => 'integer|exists:attendance,id',
        ]);

        $ids = $request->input('attendance_ids');

        $records = Attendance::with(['event', 'user'])
            ->whereIn('id', $ids)
            ->where('fine_amount', '>', 0)
            ->where('fine_paid', false)
            ->get();

        if ($records->isEmpty()) {
            return $this->errorResponse('No unpaid fine records found for the provided IDs.', [], 404);
        }

        $paidCount = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($records, $user, $request, &$paidCount) {
            foreach ($records as $attendance) {
                $attendance->fine_paid = true;

                $meta = $attendance->verification_data ?? [];
                $meta['payment_details'] = [
                    'paid_at' => now()->toIso8601String(),
                    'received_by_id' => $user->id,
                    'received_by_name' => $user->full_name,
                    'batch_payment' => true,
                ];
                $attendance->verification_data = $meta;
                $attendance->save();

                $paidCount++;
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'fine_batch_paid',
                'description' => "Batch payment: {$paidCount} fine(s) marked as PAID by {$user->full_name}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'attendance_ids' => $records->pluck('id')->toArray(),
                    'total_amount' => $records->sum('fine_amount'),
                    'count' => $paidCount,
                ],
            ]);
        });

        return $this->successResponse([
            'paid_count' => $paidCount,
        ], "{$paidCount} fine(s) marked as paid successfully.");
    }

    /**
     * Waive a specific fine (forgives the fine amount).
     */
    public function waiveFine(Request $request, int $attendanceId): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'event_staff'])) {
            return $this->errorResponse('Only administrators and event staff can waive fines.', [], 403);
        }

        $attendance = Attendance::with(['event', 'user'])->findOrFail($attendanceId);

        if ($attendance->fine_paid && $attendance->fine_amount <= 0) {
            return $this->errorResponse('This fine has already been settled/waived.', [], 409);
        }

        $waivedAmount = (float) $attendance->fine_amount;
        $reason = $request->input('reason', 'Administrative decision / Approved justification');

        $meta = $attendance->verification_data ?? [];
        $meta['waive_details'] = [
            'waived_at' => now()->toIso8601String(),
            'waived_by_id' => $user->id,
            'waived_by_name' => $user->full_name,
            'original_amount' => $waivedAmount,
            'reason' => $reason,
        ];

        $attendance->fine_amount = 0.00;
        $attendance->fine_paid = true;
        $attendance->verification_data = $meta;
        $attendance->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'fine_waived',
            'description' => "Fine of ₱" . number_format($waivedAmount, 2) . " for student {$attendance->user->full_name} ({$attendance->user->student_number}) at event '{$attendance->event->title}' was WAIVED by {$user->full_name}. Reason: {$reason}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'attendance_id' => $attendance->id,
                'waived_amount' => $waivedAmount,
                'student_id' => $attendance->user_id,
            ],
        ]);

        return $this->successResponse($attendance, 'Fine waived successfully.');
    }

    /**
     * Batch waive multiple fines.
     */
    public function waiveBatch(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'event_staff'])) {
            return $this->errorResponse('Only administrators and event staff can waive fines.', [], 403);
        }

        $request->validate([
            'attendance_ids' => 'required|array|min:1',
            'attendance_ids.*' => 'integer|exists:attendance,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $ids = $request->input('attendance_ids');
        $reason = $request->input('reason', 'Administrative decision / Approved justification');

        $records = Attendance::with(['event', 'user'])
            ->whereIn('id', $ids)
            ->where('fine_paid', false)
            ->get();

        if ($records->isEmpty()) {
            return $this->errorResponse('No unpaid fine records found for the provided IDs.', [], 404);
        }

        $waivedCount = 0;
        $totalWaivedAmount = 0.00;

        \Illuminate\Support\Facades\DB::transaction(function () use ($records, $user, $request, $reason, &$waivedCount, &$totalWaivedAmount) {
            foreach ($records as $attendance) {
                $origAmount = (float) $attendance->fine_amount;
                $totalWaivedAmount += $origAmount;

                $meta = $attendance->verification_data ?? [];
                $meta['waive_details'] = [
                    'waived_at' => now()->toIso8601String(),
                    'waived_by_id' => $user->id,
                    'waived_by_name' => $user->full_name,
                    'original_amount' => $origAmount,
                    'batch_waive' => true,
                    'reason' => $reason,
                ];

                $attendance->fine_amount = 0.00;
                $attendance->fine_paid = true;
                $attendance->verification_data = $meta;
                $attendance->save();

                $waivedCount++;
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'fine_batch_waived',
                'description' => "Batch waive: {$waivedCount} fine(s) totaling ₱" . number_format($totalWaivedAmount, 2) . " WAIVED by {$user->full_name}. Reason: {$reason}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'attendance_ids' => $records->pluck('id')->toArray(),
                    'total_waived_amount' => $totalWaivedAmount,
                    'count' => $waivedCount,
                ],
            ]);
        });

        return $this->successResponse([
            'waived_count' => $waivedCount,
            'total_waived_amount' => $totalWaivedAmount,
        ], "{$waivedCount} fine(s) waived successfully.");
    }
}
