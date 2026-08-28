<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceSyncController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceResetController;
use App\Http\Controllers\Api\DynamicQrController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventStaffController;
use App\Http\Controllers\Api\FineController;
use App\Http\Controllers\Api\ManualOverrideController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StudentProvisioningController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Secure BSIS Attendance System
|--------------------------------------------------------------------------
*/

// Public Authentication & Password Management
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public Secure Onboarding
Route::prefix('onboarding')->group(function () {
    Route::get('/{token}', [OnboardingController::class, 'show']);
    Route::post('/{token}/complete', [OnboardingController::class, 'complete']);
});

// Authenticated Routes (Requires Valid Sanctum Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth Sessions & Profiles
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // System Settings Retrieval
    Route::get('/settings', [SystemSettingController::class, 'index']);

    // Desktop & Real-Time Dashboard Summary & Live Feed
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/live-attendance/{event}', [DashboardController::class, 'liveAttendance']);
    });

    // Student Device Management
    Route::prefix('devices')->group(function () {
        Route::get('/', [DeviceController::class, 'index']);
        Route::post('/bind', [DeviceController::class, 'bind']);
        Route::post('/reset-request', [DeviceResetController::class, 'requestReset']);
    });

    // Event Management & QR Generation
    Route::prefix('events')->group(function () {
        Route::get('/', [EventController::class, 'index']);
        Route::get('/{id}', [EventController::class, 'show']);

        // Staff & Admin Event Operations
        Route::middleware('role:admin,event_staff')->group(function () {
            Route::put('/{id}', [EventController::class, 'update']);
            Route::post('/{id}/activate', [EventController::class, 'activate']);
            Route::post('/{id}/complete', [EventController::class, 'complete']);
            Route::post('/{id}/process-absences', [EventController::class, 'processAbsences']);
            Route::post('/{id}/toggle-bypass', [EventController::class, 'toggleBypass']);
            Route::get('/{event}/staff', [EventStaffController::class, 'index']);
            Route::post('/{event}/qr-token', [DynamicQrController::class, 'generate']);
        });
    });

    // Attendance Engine & Manual Override
    Route::prefix('attendance')->group(function () {
        Route::post('/scan', [AttendanceController::class, 'scan']);
        Route::get('/', [AttendanceController::class, 'index']);
        Route::get('/{id}', [AttendanceController::class, 'show']);
        Route::post('/override', [ManualOverrideController::class, 'store'])->middleware('role:admin,event_staff');
    });

    // Offline Batch Attendance Synchronization
    Route::prefix('sync')->middleware('role:admin,event_staff')->group(function () {
        Route::post('/attendance', [AttendanceSyncController::class, 'sync']);
        Route::get('/status', [AttendanceSyncController::class, 'status']);
    });

    // Fine Tracking & Payments
    Route::prefix('fines')->group(function () {
        Route::get('/', [FineController::class, 'index']);
        Route::middleware('role:admin,event_staff')->group(function () {
            Route::post('/{attendance}/pay', [FineController::class, 'payFine']);
            Route::post('/{attendance}/waive', [FineController::class, 'waiveFine']);
            Route::post('/batch-pay', [FineController::class, 'payBatch']);
            Route::post('/batch-waive', [FineController::class, 'waiveBatch']);
        });
    });
    Route::get('/students/{student}/fines', [FineController::class, 'getStudentFines']);

    // Analytics Reports & CSV Data Exports
    Route::prefix('reports')->middleware('role:admin,event_staff')->group(function () {
        Route::get('/attendance', [ReportController::class, 'attendanceReport']);
        Route::get('/summary', [ReportController::class, 'summaryReport']);
        Route::get('/fines', [ReportController::class, 'fineReport']);
        Route::get('/export', [ReportController::class, 'export']);
    });

    // Administrator Only Endpoint Group
    Route::middleware('role:admin')->group(function () {
        
        // System Settings & SMTP Diagnostic
        Route::post('/settings', [SystemSettingController::class, 'update']);
        Route::post('/test-email', [SystemSettingController::class, 'testEmail']);

        // Event Management (Admin Mutations)
        Route::post('/events', [EventController::class, 'store']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
        Route::post('/events/batch-delete', [EventController::class, 'destroyBatch']);
        Route::post('/events/{event}/staff', [EventStaffController::class, 'store']);
        Route::delete('/events/{event}/staff/{user}', [EventStaffController::class, 'destroy']);

        // Student Provisioning (Single & CSV Batch)
        Route::prefix('students')->group(function () {
            Route::post('/', [StudentProvisioningController::class, 'store']);
            Route::post('/import', [StudentProvisioningController::class, 'import']);
        });

        // User & Account Management
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::post('/{id}/reset-device', [UserController::class, 'resetDevice']);
            Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            Route::post('/batch-delete', [UserController::class, 'destroyBatch']);
        });

        // Device Reset Request Review & Approvals
        Route::prefix('device-resets')->group(function () {
            Route::get('/', [DeviceResetController::class, 'index']);
            Route::post('/{id}/approve', [DeviceResetController::class, 'approve']);
            Route::post('/{id}/reject', [DeviceResetController::class, 'reject']);
        });

        // System Audit Logs
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
        });

        // Database Backup & Recovery Management
        Route::prefix('backups')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/create', [BackupController::class, 'create']);
            Route::get('/{filename}/download', [BackupController::class, 'download']);
            Route::post('/{filename}/restore', [BackupController::class, 'restore']);
        });
    });
});
