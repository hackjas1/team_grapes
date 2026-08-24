<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\BatchSyncRequest;
use App\Models\Attendance;
use App\Models\AttendanceSyncRecord;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceSyncController extends Controller
{
    use ApiResponse;

    /**
     * Synchronize batch of offline attendance records captured locally during internet outage.
     */
    public function sync(BatchSyncRequest $request): JsonResponse
    {
        $staff = $request->user();
        $records = $request->input('records', []);

        $totalReceived = count($records);
        $syncedCount = 0;
        $failedCount = 0;
        $duplicateCount = 0;
        $results = [];

        foreach ($records as $item) {
            $localId = $item['local_record_id'];
            $eventId = (int) $item['event_id'];
            $userId = (int) $item['user_id'];
            $scanTime = $item['scan_time'];
            $lat = isset($item['latitude']) ? (float) $item['latitude'] : null;
            $lon = isset($item['longitude']) ? (float) $item['longitude'] : null;
            $deviceCred = $item['device_credential'] ?? null;
            $overrideReason = $item['override_reason'] ?? 'Offline batch sync';
            $itemStatus = $item['status'] ?? 'manual_override';

            // Validate event and user
            $event = Event::find($eventId);
            $student = User::find($userId);

            if (!$event || !$student) {
                $failedCount++;
                $syncError = !$event ? "Event ID {$eventId} not found." : "Student User ID {$userId} not found.";
                
                $syncRecord = AttendanceSyncRecord::create([
                    'event_id' => $event?->id,
                    'user_id' => $student?->id,
                    'staff_id' => $staff->id,
                    'local_record_id' => $localId,
                    'scan_time' => $scanTime,
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'device_credential' => $deviceCred,
                    'override_reason' => $overrideReason,
                    'sync_status' => 'failed',
                    'sync_error' => $syncError,
                ]);

                $results[] = [
                    'local_record_id' => $localId,
                    'status' => 'failed',
                    'error' => $syncError,
                ];
                continue;
            }

            // Duplicate Attendance Check
            $alreadyExists = Attendance::where('event_id', $event->id)
                ->where('user_id', $student->id)
                ->exists();

            if ($alreadyExists) {
                $duplicateCount++;
                $syncError = "Attendance already recorded for student {$student->full_name} at event '{$event->title}'.";

                $syncRecord = AttendanceSyncRecord::create([
                    'event_id' => $event->id,
                    'user_id' => $student->id,
                    'staff_id' => $staff->id,
                    'local_record_id' => $localId,
                    'scan_time' => $scanTime,
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'device_credential' => $deviceCred,
                    'override_reason' => $overrideReason,
                    'sync_status' => 'duplicate',
                    'sync_error' => $syncError,
                ]);

                $results[] = [
                    'local_record_id' => $localId,
                    'status' => 'duplicate',
                    'error' => $syncError,
                ];
                continue;
            }

            // Process Valid Sync Record
            try {
                DB::transaction(function () use ($event, $student, $staff, $localId, $scanTime, $lat, $lon, $deviceCred, $overrideReason, $itemStatus, &$results, &$syncedCount) {
                    $attendance = Attendance::create([
                        'event_id' => $event->id,
                        'user_id' => $student->id,
                        'scan_time' => $scanTime,
                        'status' => $itemStatus,
                        'fine_amount' => 0.00,
                        'fine_paid' => false,
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'device_credential' => $deviceCred,
                        'is_offline_sync' => true,
                        'override_by' => $staff->id,
                        'override_reason' => $overrideReason,
                        'verification_data' => [
                            'verified_via' => 'Offline Synchronization',
                            'local_record_id' => $localId,
                            'staff_id' => $staff->id,
                        ],
                    ]);

                    AttendanceSyncRecord::create([
                        'event_id' => $event->id,
                        'user_id' => $student->id,
                        'staff_id' => $staff->id,
                        'local_record_id' => $localId,
                        'scan_time' => $scanTime,
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'device_credential' => $deviceCred,
                        'override_reason' => $overrideReason,
                        'sync_status' => 'synced',
                        'synced_at' => now(),
                    ]);

                    $syncedCount++;

                    $results[] = [
                        'local_record_id' => $localId,
                        'status' => 'synced',
                        'attendance_id' => $attendance->id,
                    ];
                });
            } catch (\Exception $e) {
                $failedCount++;
                $results[] = [
                    'local_record_id' => $localId,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        AuditLog::create([
            'user_id' => $staff->id,
            'action' => 'attendance_synced',
            'description' => "Staff {$staff->full_name} synchronized offline attendance batch. Total: {$totalReceived}, Synced: {$syncedCount}, Duplicates: {$duplicateCount}, Failed: {$failedCount}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'total_received' => $totalReceived,
                'synced' => $syncedCount,
                'duplicates' => $duplicateCount,
                'failed' => $failedCount,
            ],
        ]);

        return $this->successResponse([
            'total_received' => $totalReceived,
            'synced' => $syncedCount,
            'duplicates' => $duplicateCount,
            'failed' => $failedCount,
            'results' => $results,
        ], "Synchronization batch completed. {$syncedCount} records synced successfully.");
    }

    /**
     * View offline synchronization queue monitoring stats.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AttendanceSyncRecord::with(['event', 'user', 'staff']);

        if ($user->role === 'event_staff') {
            $query->where('staff_id', $user->id);
        }

        $records = (clone $query)->orderBy('created_at', 'desc')->paginate(20);

        return $this->successResponse([
            'summary' => [
                'total_records' => (clone $query)->count(),
                'pending_count' => (clone $query)->where('sync_status', 'pending')->count(),
                'synced_count' => (clone $query)->where('sync_status', 'synced')->count(),
                'duplicate_count' => (clone $query)->where('sync_status', 'duplicate')->count(),
                'failed_count' => (clone $query)->where('sync_status', 'failed')->count(),
            ],
            'records' => $records,
        ], 'Synchronization status summary retrieved.');
    }
}
