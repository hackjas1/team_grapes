<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Detailed attendance report API with filtering options.
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::with(['event', 'user', 'overrider']);

        if ($user->role === 'event_staff') {
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

        if ($eventId = $request->query('event_id')) {
            $query->where('attendances.event_id', $eventId);
        }

        if ($studentId = $request->query('student_id')) {
            $query->where('attendances.user_id', $studentId);
        }

        if ($search = $request->query('search')) {
            $search = trim($search);
            $query->whereHas('user', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        if ($status = $request->query('status')) {
            $query->where('attendances.status', $status);
        }

        if ($yearLevel = $request->query('year_level')) {
            $query->whereHas('user', function ($q) use ($yearLevel) {
                $q->where('year_level', $yearLevel);
            });
        }

        if ($sectionBlock = $request->query('section_block')) {
            $query->whereHas('user', function ($q) use ($sectionBlock) {
                $q->where('section_block', 'like', "%{$sectionBlock}%");
            });
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('attendances.scan_time', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->whereDate('attendances.scan_time', '<=', $endDate);
        }

        $perPage = (int) $request->query('per_page', 25);
        $records = $query->orderBy('scan_time', 'desc')->paginate($perPage);

        return $this->successResponse($records, 'Attendance report retrieved successfully.');
    }

    /**
     * Event attendance summary analytics report.
     */
    public function summaryReport(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeStudentsCount = User::where('role', 'student')->where('status', 'active')->count();

        $eventsQuery = Event::with('staff');

        if ($user->role === 'event_staff') {
            $eventsQuery->where(function ($q) use ($user) {
                $q->whereHas('staff', function ($sub) use ($user) {
                    $sub->where('user_id', $user->id);
                })
                ->orDoesntHave('staff')
                ->orWhere('created_by', $user->id);
            });
        }

        if ($eventId = $request->query('event_id')) {
            $eventsQuery->where('id', $eventId);
        }

        $events = $eventsQuery->orderBy('start_time', 'desc')->get();

        $summaryData = $events->map(function ($event) use ($activeStudentsCount) {
            $attendance = Attendance::where('event_id', $event->id)->get();
            $presentCount = $attendance->where('status', 'present')->count();
            $lateCount = $attendance->where('status', 'late')->count();
            $overrideCount = $attendance->where('status', 'manual_override')->count();
            $totalAttended = $attendance->count();
            $absentCount = max(0, $activeStudentsCount - $totalAttended);
            $attendanceRate = $activeStudentsCount > 0 ? round(($totalAttended / $activeStudentsCount) * 100, 1) : 0;
            $totalFines = (float) $attendance->sum('fine_amount');

            return [
                'event_id' => $event->id,
                'uuid' => $event->uuid,
                'title' => $event->title,
                'venue_name' => $event->venue_name,
                'start_time' => $event->start_time->format('Y-m-d H:i:s'),
                'status' => $event->status,
                'fine_amount_per_late' => $event->fine_amount,
                'total_registered_students' => $activeStudentsCount,
                'total_attended' => $totalAttended,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'manual_override_count' => $overrideCount,
                'absent_count' => $absentCount,
                'attendance_rate_percentage' => $attendanceRate,
                'total_fines_incurred' => $totalFines,
            ];
        });

        return $this->successResponse([
            'total_active_students' => $activeStudentsCount,
            'events_summary' => $summaryData,
        ], 'Event attendance summary report generated successfully.');
    }

    /**
     * Fine summary report per event or student.
     */
    public function fineReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Attendance::with(['event', 'user'])->where('fine_amount', '>', 0);

        if ($user->role === 'event_staff') {
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

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($yearLevel = $request->query('year_level')) {
            $query->whereHas('user', function ($q) use ($yearLevel) {
                $q->where('year_level', $yearLevel);
            });
        }

        if ($sectionBlock = $request->query('section_block')) {
            $query->whereHas('user', function ($q) use ($sectionBlock) {
                $q->where('section_block', 'like', "%{$sectionBlock}%");
            });
        }

        if ($request->has('fine_paid') && $request->query('fine_paid') !== '' && $request->query('fine_paid') !== 'all') {
            $query->where('fine_paid', filter_var($request->query('fine_paid'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->query('per_page', 25);
        $records = $query->orderBy('scan_time', 'desc')->paginate($perPage);

        $totalFines = (float) (clone $query)->sum('fine_amount');
        $unpaidFines = (float) (clone $query)->where('fine_paid', false)->sum('fine_amount');
        $paidFines = (float) (clone $query)->where('fine_paid', true)->sum('fine_amount');

        return $this->successResponse([
            'summary' => [
                'total_fines_amount' => $totalFines,
                'unpaid_fines_amount' => $unpaidFines,
                'paid_fines_amount' => $paidFines,
            ],
            'fines' => $records,
        ], 'Fine report retrieved successfully.');
    }

    /**
     * Export attendance or fine data as CSV file stream matching active filters.
     */
    /**
     * Export attendance or fine data as CSV or Word DOCX matching active filters.
     */
    public function export(Request $request)
    {
        $user = $request->user();
        if (!$user && $request->has('token')) {
            $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($request->query('token'));
            if ($pat) {
                $user = $pat->tokenable;
            }
        }

        if (!$user || !in_array($user->role, ['admin', 'event_staff'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized export request.'], 403);
        }

        $type = $request->query('type', 'attendance'); // 'attendance' or 'fines'
        $format = strtolower($request->query('format', 'csv')); // 'csv' or 'docx' / 'doc'
        $eventId = $request->query('event_id');
        $status = $request->query('status');
        $yearLevel = $request->query('year_level');
        $sectionBlock = $request->query('section_block');
        $finePaid = $request->query('fine_paid');
        $search = $request->query('search');
        $searchField = $request->query('search_field', 'all');

        $query = Attendance::with(['event', 'user', 'overrider']);

        $selectedEvent = null;
        if ($eventId) {
            $query->where('event_id', $eventId);
            $selectedEvent = Event::find($eventId);
        }

        if ($type === 'fines') {
            $query->where('fine_amount', '>', 0);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($finePaid !== null && $finePaid !== '' && $finePaid !== 'all') {
            $query->where('fine_paid', filter_var($finePaid, FILTER_VALIDATE_BOOLEAN));
        }

        if ($yearLevel) {
            $query->whereHas('user', function ($q) use ($yearLevel) {
                $q->where('year_level', $yearLevel);
            });
        }

        if ($sectionBlock) {
            $query->whereHas('user', function ($q) use ($sectionBlock) {
                $q->where('section_block', 'like', "%{$sectionBlock}%");
            });
        }

        if ($search) {
            $search = trim($search);
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

        $records = $query->get()->sortBy(function ($r) {
            $last = strtoupper(trim($r->user->last_name ?? ''));
            $first = strtoupper(trim($r->user->first_name ?? ''));
            return $last . ' ' . $first;
        })->values();

        $eventSlug = $selectedEvent ? \Illuminate\Support\Str::slug($selectedEvent->title) : 'all_events';
        $timestamp = date('Y_m_d_His');

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'attendance_exported',
            'description' => "User {$user->full_name} exported {$records->count()} {$type} records as {$format}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'export_type' => $type,
                'format' => $format,
                'event_id' => $eventId,
                'year_level' => $yearLevel,
                'records_count' => $records->count(),
            ],
        ]);

        // WORD DOCX / DOC EXPORT
        if (in_array($format, ['docx', 'doc', 'word'])) {
            $filename = $type === 'fines' 
                ? "BSIS_Student_Fines_Clearance_Summary_{$timestamp}.doc" 
                : "BSIS_Student_Attendance_Clearance_Summary_{$eventSlug}_{$timestamp}.doc";
            $printDate = date('F d, Y - h:i A');

            $grouped = $records->groupBy('user_id')->sortBy(function ($items) {
                $u = $items->first()->user;
                $last = strtoupper(trim($u->last_name ?? ''));
                $first = strtoupper(trim($u->first_name ?? ''));
                return $last . ' ' . $first;
            });
            $totalIncurredAll = 0;
            $totalPaidAll = 0;
            $totalUnpaidAll = 0;

            $rowsHtml = '';
            $num = 0;
            foreach ($grouped as $userId => $items) {
                $u = $items->first()->user;
                if (!$u) continue;
                $num++;

                $incurred = (float) $items->sum('fine_amount');
                $paid = (float) $items->where('fine_paid', true)->sum('fine_amount');
                $balance = max(0, $incurred - $paid);

                $totalIncurredAll += $incurred;
                $totalPaidAll += $paid;
                $totalUnpaidAll += $balance;

                $sNum = htmlspecialchars($u->student_number ?? 'N/A');
                $name = htmlspecialchars($u->formal_name ?? $u->full_name ?? 'N/A');
                $yr = htmlspecialchars($u->year_level ?? 'N/A');
                $blk = htmlspecialchars($u->section_block ?? 'N/A');

                $isCleared = $balance <= 0;
                $statusText = $isCleared ? 'CLEARED' : 'UNPAID';
                $statusColor = $isCleared ? '#16A34A' : '#DC2626';

                $rowsHtml .= "
                    <tr>
                        <td style='text-align:center; padding:6px; border:1px solid #c4d1db;'>{$num}</td>
                        <td style='padding:6px; border:1px solid #c4d1db; font-weight:bold; white-space:nowrap;'>{$sNum}</td>
                        <td style='padding:6px; border:1px solid #c4d1db; white-space:nowrap;'>{$name}</td>
                        <td style='text-align:center; padding:6px; border:1px solid #c4d1db; white-space:nowrap;'>{$yr}</td>
                        <td style='text-align:center; padding:6px; border:1px solid #c4d1db; white-space:nowrap;'>{$blk}</td>
                        <td style='text-align:right; padding:6px; border:1px solid #c4d1db; font-weight:bold; white-space:nowrap;'>PHP " . number_format($incurred, 2) . "</td>
                        <td style='text-align:right; padding:6px; border:1px solid #c4d1db; color:#16A34A; font-weight:bold; white-space:nowrap;'>PHP " . number_format($paid, 2) . "</td>
                        <td style='text-align:right; padding:6px; border:1px solid #c4d1db; color:#DC2626; font-weight:bold; white-space:nowrap;'>PHP " . number_format($balance, 2) . "</td>
                        <td style='text-align:center; padding:6px; border:1px solid #c4d1db; font-weight:bold; color:{$statusColor}; white-space:nowrap;'>{$statusText}</td>
                        <td style='padding:6px; border:1px solid #c4d1db; text-align:center; color:#999; font-size:8pt; white-space:nowrap;'>_________________</td>
                    </tr>
                ";
            }

            if (empty($rowsHtml)) {
                $rowsHtml = "<tr><td colspan='10' style='text-align:center; padding:15px; color:#888;'>No student records found matching the active filters.</td></tr>";
            }

            $docContent = "
            <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <meta charset='utf-8'>
                <title>Official Student Clearance & Fine Summary</title>
                <style>
                    @page { size: 8.5in 11in; margin: 0.5in; mso-page-orientation: portrait; }
                    body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 10pt; color: #17212B; line-height: 1.25; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th { background-color: #063B5C; color: #FFFFFF; font-weight: bold; padding: 6px; border: 1px solid #063B5C; text-align: center; font-size: 9pt; }
                    td { font-size: 9pt; }
                </style>
            </head>
            <body>
                <table style='border:none; margin-bottom:10px;'>
                    <tr>
                        <td style='border:none; width:65px; vertical-align:middle;'><img src='https://tpc-bsis.online/images/bsis-logo.png' width='55' height='55'></td>
                        <td style='border:none; vertical-align:middle;'>
                            <div style='font-size:14pt; font-weight:bold; color:#063B5C;'>TALIBON POLYTECHNIC COLLEGE</div>
                            <div style='font-size:10pt; font-weight:bold; color:#0284C7;'>Bachelor of Science in Information Systems (BSIS)</div>
                            <div style='font-size:9pt; color:#555;'>Official Student Clearance & Fine Summary Masterlist</div>
                        </td>
                        <td style='border:none; text-align:right; font-size:8.5pt; color:#555; vertical-align:middle;'>
                            <strong>Date Generated:</strong> {$printDate}
                        </td>
                    </tr>
                </table>

                <div style='background-color:#F4F8FA; border:1px solid #D1E3ED; padding:8px 12px; margin-bottom:10px; font-size:9pt;'>
                    <strong style='color:#063B5C;'>Total Students Listed:</strong> {$num} &nbsp;|&nbsp;
                    <strong style='color:#063B5C;'>Total Fines Incurred:</strong> PHP " . number_format($totalIncurredAll, 2) . " &nbsp;|&nbsp;
                    <strong style='color:#16A34A;'>Total Paid:</strong> PHP " . number_format($totalPaidAll, 2) . " &nbsp;|&nbsp;
                    <strong style='color:#DC2626;'>Total Balance Due:</strong> PHP " . number_format($totalUnpaidAll, 2) . "
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style='width:25px;'>#</th>
                            <th style='width:85px;'>Student ID</th>
                            <th>Student Full Name</th>
                            <th style='width:60px;'>Year</th>
                            <th style='width:60px;'>Block</th>
                            <th style='width:75px;'>Total Fine</th>
                            <th style='width:75px;'>Paid</th>
                            <th style='width:75px;'>Balance Due</th>
                            <th style='width:65px;'>Status</th>
                            <th style='width:105px;'>Signature</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rowsHtml}
                    </tbody>
                </table>

                <br><br>
                <table style='border:none; margin-top:25px; font-size:9pt;'>
                    <tr>
                        <td style='border:none; width:45%; text-align:center;'>
                            _______________________________________<br><br>
                            <strong>BSIS Attendance Officer / Treasurer</strong><br>
                            <span style='font-size:8pt; color:#666;'>Signature over Printed Name</span>
                        </td>
                        <td style='border:none; width:10%;'></td>
                        <td style='border:none; width:45%; text-align:center;'>
                            _______________________________________<br><br>
                            <strong>BSIS Department Head</strong><br>
                            <span style='font-size:8pt; color:#666;'>Signature over Printed Name</span>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            return response($docContent, 200, [
                'Content-Type' => 'application/msword; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // CSV EXPORT (for both Attendance and Fines)
        $filename = $type === 'fines' 
            ? "BSIS_Student_Fines_Clearance_Summary_{$timestamp}.csv" 
            : "BSIS_Student_Attendance_Clearance_Summary_{$eventSlug}_{$timestamp}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $grouped = $records->groupBy('user_id')->sortBy(function ($items) {
            $u = $items->first()->user;
            $last = strtoupper(trim($u->last_name ?? ''));
            $first = strtoupper(trim($u->first_name ?? ''));
            return $last . ' ' . $first;
        });

        $callback = function () use ($grouped) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($file, [
                'Student ID',
                'Student Full Name',
                'Year Level',
                'Block',
                'Institutional Email',
                'Total Fines Incurred (PHP)',
                'Total Fines Paid (PHP)',
                'Outstanding Balance Due (PHP)',
                'Clearance Status',
            ]);

            foreach ($grouped as $userId => $items) {
                $u = $items->first()->user;
                if (!$u) continue;

                $incurred = (float) $items->sum('fine_amount');
                $paid = (float) $items->where('fine_paid', true)->sum('fine_amount');
                $balance = max(0, $incurred - $paid);
                $status = $balance <= 0 ? 'CLEARED' : 'UNPAID';

                fputcsv($file, [
                    $u->student_number ?? 'N/A',
                    $u->formal_name ?? $u->full_name ?? 'N/A',
                    $u->year_level ?? 'N/A',
                    $u->section_block ?? 'N/A',
                    $u->email ?? 'N/A',
                    number_format($incurred, 2, '.', ''),
                    number_format($paid, 2, '.', ''),
                    number_format($balance, 2, '.', ''),
                    $status,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
