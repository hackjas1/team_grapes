<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    use ApiResponse;

    /**
     * Get system settings.
     */
    public function index(): JsonResponse
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
        return $this->successResponse([
            'settings' => $settings,
            'qr_expiration_seconds' => (int) SystemSetting::get('qr_expiration_seconds', '20'),
            'institutional_email_domain' => SystemSetting::get('institutional_email_domain', 'tpc.edu.ph'),
            'default_allowed_radius_meters' => (int) SystemSetting::get('default_allowed_radius_meters', '50'),
        ], 'System settings retrieved successfully.');
    }

    /**
     * Update system settings (Admin only).
     */
    public function update(Request $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return $this->errorResponse('Only administrators can update system settings.', [], 403);
        }

        $validated = $request->validate([
            'qr_expiration_seconds' => ['nullable', 'integer', 'between:5,300'],
            'default_allowed_radius_meters' => ['nullable', 'integer', 'between:10,1000'],
            'institutional_email_domain' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['qr_expiration_seconds'])) {
            SystemSetting::set('qr_expiration_seconds', (string) $validated['qr_expiration_seconds'], 'Dynamic QR code token expiration time in seconds');
        }

        if (isset($validated['default_allowed_radius_meters'])) {
            SystemSetting::set('default_allowed_radius_meters', (string) $validated['default_allowed_radius_meters'], 'Default GPS verification radius in meters for events');
        }

        if (isset($validated['institutional_email_domain'])) {
            SystemSetting::set('institutional_email_domain', trim($validated['institutional_email_domain']), 'Required institutional email domain for student accounts');
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'settings_updated',
            'description' => "Administrator {$admin->full_name} updated system settings (QR Interval: {$validated['qr_expiration_seconds']}s).",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $validated,
        ]);

        return $this->successResponse([
            'qr_expiration_seconds' => (int) SystemSetting::get('qr_expiration_seconds', '20'),
            'default_allowed_radius_meters' => (int) SystemSetting::get('default_allowed_radius_meters', '50'),
            'institutional_email_domain' => SystemSetting::get('institutional_email_domain', 'tpc.edu.ph'),
        ], 'System settings updated successfully.');
    }
}
