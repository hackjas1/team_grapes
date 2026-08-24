<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OnboardingToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppDownloadController extends Controller
{
    /**
     * Securely download the TPC Mobile App APK via a student-bound token.
     * GET /download/app/{token}
     */
    public function download(Request $request, string $token)
    {
        // 1. Verify that the token exists and belongs to an authorized student
        $onboardingToken = OnboardingToken::with('user')
            ->where('token', $token)
            ->first();

        // Also allow valid active users with active token
        if (!$onboardingToken) {
            return response()->view('errors.custom', [
                'title' => 'Unauthorized Download Link',
                'message' => 'This mobile app download link is invalid or has expired. Please use the official link sent to your institutional email.'
            ], 403);
        }

        $user = $onboardingToken->user;
        if (!$user) {
            return response()->view('errors.custom', [
                'title' => 'Account Not Found',
                'message' => 'The account associated with this download token does not exist.'
            ], 404);
        }

        // Check if an external Cloud CDN URL is configured (via .env or System Settings)
        $externalUrl = env('EXTERNAL_APK_URL') ?: \App\Models\SystemSetting::get('external_apk_url');
        if ($externalUrl && filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            $this->logDownload($user, $request);
            return response()->view('download', [
                'directApkUrl' => $externalUrl,
                'user' => $user,
            ]);
        }

        // 2. Check APK Storage Paths (public/downloads/ or storage/app/apk/)
        $targetFile = $this->resolveApkFile();
        $downloadFileName = 'TPC-BSIS-Attendance.apk';

        // If APK has not been placed in storage yet, show an informative guide page
        if (!$targetFile) {
            return response()->view('errors.custom', [
                'title' => 'Mobile App Package (APK)',
                'message' => "Hello {$user->first_name}! Your download token is verified. The latest APK build is currently being compiled by the administrator. Please check back shortly or access the app via Expo Go."
            ], 200);
        }

        // 3. Log the download activity in Audit Logs
        $this->logDownload($user, $request);

        // Render download landing page with direct stream link
        return response()->view('download', [
            'directApkUrl' => $externalUrl ?: route('app.download.direct', ['stream' => '1']),
            'user' => $user,
        ]);
    }

    /**
     * Direct public APK download from the student portal landing page.
     * GET /download/app
     */
    public function downloadDirect(Request $request)
    {
        $externalUrl = env('EXTERNAL_APK_URL') ?: \App\Models\SystemSetting::get('external_apk_url');
        
        // If stream parameter is explicitly passed, stream binary directly
        if ($request->query('stream') === '1') {
            $targetFile = $this->resolveApkFile();
            if ($targetFile) {
                return $this->streamAndCloseImmediately($targetFile, 'TPC-BSIS-Attendance.apk');
            }
        }

        return response()->view('download', [
            'directApkUrl' => $externalUrl ?: route('app.download.direct', ['stream' => '1']),
            'user' => null,
        ]);
    }

    /**
     * Helper to log download audit activity.
     */
    private function logDownload($user, Request $request): void
    {
        try {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'app_apk_downloaded',
                'description' => "Student {$user->full_name} ({$user->student_number}) downloaded the mobile app APK via secure token.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {}
    }

    /**
     * Stream binary with explicit Content-Length and instant TCP FIN closure.
     */
    private function streamAndCloseImmediately(string $filePath, string $downloadFileName)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            abort(404, 'Download file not found.');
        }

        // Clean all existing output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        ignore_user_abort(true);

        $fileSize = filesize($filePath);

        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Content-Transfer-Encoding: binary');
        header('Connection: close');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        if (request()->isMethod('HEAD')) {
            exit(0);
        }

        $fp = fopen($filePath, 'rb');
        if ($fp) {
            while (!feof($fp) && connection_status() === CONNECTION_NORMAL) {
                echo fread($fp, 1048576); // 1 MB per chunk
                flush();
            }
            fclose($fp);
        }

        exit(0);
    }

    /**
     * Resolve the latest valid APK file on the server.
     */
    private function resolveApkFile(): ?string
    {
        $namedFile = 'TPC-BSIS-Attendance.apk';
        $candidates = [
            public_path("downloads/{$namedFile}"),
            storage_path("app/apk/{$namedFile}"),
            public_path('downloads/app-release.apk'),
            storage_path('app/apk/app-release.apk'),
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand) && filesize($cand) > 0) {
                return $cand;
            }
        }

        // Search for any .apk file in public/downloads
        $publicDir = public_path('downloads');
        if (is_dir($publicDir)) {
            $files = glob("{$publicDir}/*.apk");
            if (!empty($files)) {
                usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                return $files[0];
            }
        }

        // Search for any .apk file in storage/app/apk
        $storageDir = storage_path('app/apk');
        if (is_dir($storageDir)) {
            $files = glob("{$storageDir}/*.apk");
            if (!empty($files)) {
                usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                return $files[0];
            }
        }

        return null;
    }
}
