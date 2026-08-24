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
            $query->where('event_id', $eventId);
        }

        if ($studentId = $request->query('student_id')) {
            $query->where('user_id', $studentId);
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
            $query->where('status', $status);
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
            $query->whereDate('scan_time', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->whereDate('scan_time', '<=', $endDate);
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

        $records = $query->orderBy('scan_time', 'desc')->get();

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
            $filename = "BSIS_Attendance_Report_{$eventSlug}_{$timestamp}.doc";
            
            $presentCount = $records->where('status', 'present')->count();
            $lateCount = $records->where('status', 'late')->count();
            $overrideCount = $records->where('status', 'manual_override')->count();
            $totalFines = (float) $records->sum('fine_amount');
            $printDate = date('F d, Y - h:i A');

            $eventTitle = $selectedEvent ? htmlspecialchars($selectedEvent->title) : 'All Event Sessions';
            $venueName = $selectedEvent ? htmlspecialchars($selectedEvent->venue_name) : 'All Venues';
            $eventDate = $selectedEvent && $selectedEvent->start_time ? $selectedEvent->start_time->format('F d, Y (h:i A)') : 'Various Dates';
            $audience = $selectedEvent ? $selectedEvent->getTargetAudienceLabel() : 'All Students';

            $isWholeDayEvent = $selectedEvent && $selectedEvent->session_type === 'whole_day';
            $rowsHtml = '';
            foreach ($records as $idx => $r) {
                $num = $idx + 1;
                $sNum = htmlspecialchars($r->user->student_number ?? 'N/A');
                $name = htmlspecialchars($r->user->full_name ?? 'N/A');
                $yr = htmlspecialchars($r->user->year_level ?? 'N/A');
                $blk = htmlspecialchars($r->user->section_block ?? 'N/A');
                $amIn = $r->am_time_in ? $r->am_time_in->format('h:i:s A') : ($r->scan_time ? $r->scan_time->format('h:i:s A') : '—');
                $amOut = $r->am_time_out ? $r->am_time_out->format('h:i:s A') : '—';
                $pmIn = $r->pm_time_in ? $r->pm_time_in->format('h:i:s A') : '—';
                $pmOut = $r->pm_time_out ? $r->pm_time_out->format('h:i:s A') : ($r->checkout_time ? $r->checkout_time->format('h:i:s A') : '—');

                $st = strtoupper($r->status);
                $stColor = $r->status === 'present' ? '#0d6efd' : ($r->status === 'late' ? '#fd7e14' : '#198754');
                $dist = $r->distance_meters !== null ? $r->distance_meters . 'm' : 'N/A';
                
                $isPaid = (bool) $r->fine_paid;
                $isWaived = $isPaid && ((float) $r->fine_amount <= 0 || isset($r->verification_data['waive_details']));
                if ($isWaived) {
                    $fine = "<span style='color:#0284C7; font-weight:bold;'>WAIVED</span>";
                } elseif ($isPaid) {
                    $fine = "<span style='color:#16A34A; font-weight:bold;'>PAID</span>";
                } elseif ((float) $r->fine_amount > 0) {
                    $fine = "<span style='color:#DC2626; font-weight:bold;'>PHP " . number_format($r->fine_amount, 2) . "</span>";
                } else {
                    $fine = "<span style='color:#888;'>—</span>";
                }

                if ($isWholeDayEvent) {
                    $rowsHtml .= "
                        <tr>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db;'>{$num}</td>
                            <td style='padding:5px; border:1px solid #c4d1db; font-weight:bold;'>{$sNum}</td>
                            <td style='padding:5px; border:1px solid #c4d1db;'>{$name}</td>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db;'>{$yr}</td>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db;'>{$blk}</td>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db; font-family:Consolas, monospace; font-size:8pt;'>{$amIn}</td>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db; font-family:Consolas, monospace; font-size:8pt;'>{$amOut}</td>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db; font-family:Consolas, monospace; font-size:8pt;'>{$pmIn}</td>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db; font-family:Consolas, monospace; font-size:8pt;'>{$pmOut}</td>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db; font-weight:bold; color:{$stColor};'>{$st}</td>
                            <td style='text-align:center; padding:5px; border:1px solid #c4d1db;'>{$dist}</td>
                            <td style='text-align:right; padding:5px; border:1px solid #c4d1db; font-weight:bold;'>{$fine}</td>
                        </tr>
                    ";
                } else {
                    $timeIn = $r->scan_time ? $r->scan_time->format('h:i:s A') : ($r->am_time_in ? $r->am_time_in->format('h:i:s A') : '—');
                    $timeOut = $r->checkout_time ? $r->checkout_time->format('h:i:s A') : ($r->pm_time_out ? $r->pm_time_out->format('h:i:s A') : '—');
                    $rowsHtml .= "
                        <tr>
                            <td style='text-align:center; padding:6px; border:1px solid #c4d1db;'>{$num}</td>
                            <td style='padding:6px; border:1px solid #c4d1db; font-weight:bold;'>{$sNum}</td>
                            <td style='padding:6px; border:1px solid #c4d1db;'>{$name}</td>
                            <td style='text-align:center; padding:6px; border:1px solid #c4d1db;'>{$yr}</td>
                            <td style='text-align:center; padding:6px; border:1px solid #c4d1db;'>{$blk}</td>
                            <td style='text-align:center; padding:6px; border:1px solid #c4d1db; font-family:Consolas, monospace;'>{$timeIn}</td>
                            <td style='text-align:center; padding:6px; border:1px solid #c4d1db; font-family:Consolas, monospace;'>{$timeOut}</td>
                            <td style='text-align:center; padding:6px; border:1px solid #c4d1db; font-weight:bold; color:{$stColor};'>{$st}</td>
                            <td style='text-align:center; padding:6px; border:1px solid #c4d1db;'>{$dist}</td>
                            <td style='text-align:right; padding:6px; border:1px solid #c4d1db; font-weight:bold;'>{$fine}</td>
                        </tr>
                    ";
                }
            }

            $colspan = $isWholeDayEvent ? 12 : 10;
            if (empty($rowsHtml)) {
                $rowsHtml = "<tr><td colspan='{$colspan}' style='text-align:center; padding:15px; color:#888;'>No attendance records found for this event.</td></tr>";
            }

            $tableHeadersHtml = $isWholeDayEvent
                ? "
                    <tr>
                        <th style='width:22px;'>#</th>
                        <th style='width:80px;'>Student ID</th>
                        <th>Student Full Name</th>
                        <th style='width:50px;'>Year</th>
                        <th style='width:50px;'>Block</th>
                        <th style='width:68px;'>AM In</th>
                        <th style='width:68px;'>AM Out</th>
                        <th style='width:68px;'>PM In</th>
                        <th style='width:68px;'>PM Out</th>
                        <th style='width:60px;'>Status</th>
                        <th style='width:50px;'>Distance</th>
                        <th style='width:65px;'>Fine</th>
                    </tr>
                "
                : "
                    <tr>
                        <th style='width:25px;'>#</th>
                        <th style='width:90px;'>Student ID</th>
                        <th>Student Full Name</th>
                        <th style='width:55px;'>Year</th>
                        <th style='width:55px;'>Block</th>
                        <th style='width:75px;'>Time-In</th>
                        <th style='width:75px;'>Time-Out</th>
                        <th style='width:65px;'>Status</th>
                        <th style='width:55px;'>Distance</th>
                        <th style='width:70px;'>Fine</th>
                    </tr>
                ";

            $docContent = "
            <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
            <head>
                <!--[if gte mso 9]>
                <xml>
                <w:WordDocument>
                <w:View>Print</w:View>
                <w:Zoom>100</w:Zoom>
                <w:DoNotOptimizeForBrowser/>
                </w:WordDocument>
                </xml>
                <![endif]-->
                <meta charset='utf-8'>
                <title>Official Event Attendance Report</title>
                <style>
                    @page {
                        size: 8.5in 11in;
                        margin: 0.6in 0.6in 0.6in 0.6in;
                        mso-page-orientation: portrait;
                    }
                    body {
                        font-family: 'Calibri', 'Arial', sans-serif;
                        font-size: 10.5pt;
                        color: #17212B;
                        line-height: 1.25;
                    }
                    table.report-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                        font-size: 8.5pt;
                    }
                    table.report-table th {
                        background-color: #063B5C;
                        color: #FFFFFF;
                        padding: 6px 4px;
                        border: 1px solid #063B5C;
                        font-weight: bold;
                        text-align: center;
                    }
                    .header-box {
                        border-bottom: 2.5pt solid #063B5C;
                        padding-bottom: 6px;
                        margin-bottom: 10px;
                    }
                    .info-card {
                        background-color: #F4F8FA;
                        border: 1pt solid #DCE7ED;
                        padding: 6px 10px;
                        margin-bottom: 10px;
                    }
                </style>
            </head>
            <body>
                <div class='header-box'>
                    <table style='width:100%; border:none;'>
                        <tr>
                            <td style='vertical-align:middle;'>
                                <div style='font-size: 13pt; font-weight: bold; color: #063B5C;'>TALIBON POLYTECHNIC COLLEGE</div>
                                <div style='font-size: 10.5pt; font-weight: bold; color: #35C4E8;'>Department of Bachelor of Science in Information Systems</div>
                                <div style='font-size: 8.5pt; color: #6B7A86;'>Official Student Event Attendance & Attendance Verification Sheet</div>
                            </td>
                            <td style='text-align:right; vertical-align:middle; font-size: 8.5pt; color: #6B7A86;'>
                                <strong>Generated:</strong> {$printDate}<br>
                                <strong>Issued By:</strong> {$user->full_name}
                            </td>
                        </tr>
                    </table>
                </div>

                <div class='info-card'>
                    <table style='width:100%; border:none; font-size:9pt;'>
                        <tr>
                            <td style='width:50%;'><strong>Event Title:</strong> {$eventTitle}</td>
                            <td style='width:50%;'><strong>Venue:</strong> {$venueName}</td>
                        </tr>
                        <tr>
                            <td><strong>Event Date & Time:</strong> {$eventDate}</td>
                            <td><strong>Target Participants:</strong> {$audience}</td>
                        </tr>
                    </table>
                </div>

                <table style='width:100%; border:none; margin-bottom:10px; font-size:8.5pt;'>
                    <tr>
                        <td style='background:#EBF5FB; padding:5px 8px; border-left:3pt solid #0d6efd;'><strong>Total Scanned:</strong> {$records->count()}</td>
                        <td style='background:#EBF5FB; padding:5px 8px; border-left:3pt solid #198754;'><strong>Present (On-Time):</strong> {$presentCount}</td>
                        <td style='background:#EBF5FB; padding:5px 8px; border-left:3pt solid #fd7e14;'><strong>Late Scans:</strong> {$lateCount}</td>
                        <td style='background:#EBF5FB; padding:5px 8px; border-left:3pt solid #20c997;'><strong>Manual Overrides:</strong> {$overrideCount}</td>
                        <td style='background:#EBF5FB; padding:5px 8px; border-left:3pt solid #dc3545;'><strong>Total Fines:</strong> PHP " . number_format($totalFines, 2) . "</td>
                    </tr>
                </table>

                <table class='report-table'>
                    <thead>
                        {$tableHeadersHtml}
                    </thead>
                    <tbody>
                        {$rowsHtml}
                    </tbody>
                </table>

                <br><br>
                <table style='width:100%; border:none; margin-top:15px; font-size:9pt;'>
                    <tr>
                        <td style='width:45%; text-align:center;'>
                            _______________________________________<br>
                            <strong>{$user->full_name}</strong><br>
                            <span style='font-size:8pt; color:#666;'>BSIS Attendance Officer / Staff In-Charge</span>
                        </td>
                        <td style='width:10%;'></td>
                        <td style='width:45%; text-align:center;'>
                            _______________________________________<br>
                            <strong>Department Head / Dean</strong><br>
                            <span style='font-size:8pt; color:#666;'>College of Information Systems</span>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            return response($docContent, 200, [
                'Content-Type' => 'application/msword; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ]);
        }

        // CSV EXPORT
        $prefix = $type === 'fines' ? 'BSIS_Fines_Report_' : 'BSIS_Attendance_Report_';
        $filename = "{$prefix}{$eventSlug}_{$timestamp}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($records) {
            $file = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Column Headers
            fputcsv($file, [
                'Student ID',
                'Student Name',
                'Year Level',
                'Block',
                'Institutional Email',
                'Event Title',
                'Session Type',
                'AM Time-In',
                'AM Time-Out',
                'PM Time-In',
                'PM Time-Out',
                'Status',
                'Fine Amount (PHP)',
                'Payment Status',
                'Distance (Meters)',
                'Is Offline Sync',
                'Override Staff',
                'Override Reason',
            ]);

            foreach ($records as $row) {
                $amIn = $row->am_time_in ? $row->am_time_in->format('h:i:s A') : ($row->scan_time ? $row->scan_time->format('h:i:s A') : '');
                $amOut = $row->am_time_out ? $row->am_time_out->format('h:i:s A') : '';
                $pmIn = $row->pm_time_in ? $row->pm_time_in->format('h:i:s A') : '';
                $pmOut = $row->pm_time_out ? $row->pm_time_out->format('h:i:s A') : ($row->checkout_time ? $row->checkout_time->format('h:i:s A') : '');

                fputcsv($file, [
                    $row->user->student_number ?? 'N/A',
                    $row->user->full_name ?? 'N/A',
                    $row->user->year_level ?? 'N/A',
                    $row->user->section_block ?? 'N/A',
                    $row->user->email ?? 'N/A',
                    $row->event->title ?? 'N/A',
                    $row->event->session_type === 'whole_day' ? 'EVENT (4 Scans)' : 'EVENT (2 Scans)',
                    $amIn,
                    $amOut,
                    $pmIn,
                    $pmOut,
                    strtoupper($row->status),
                    number_format($row->fine_amount, 2),
                    $row->fine_paid ? 'PAID' : 'UNPAID',
                    $row->distance_meters !== null ? $row->distance_meters . 'm' : 'N/A',
                    $row->is_offline_sync ? 'YES' : 'NO',
                    $row->overrider->full_name ?? 'N/A',
                    $row->override_reason ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
