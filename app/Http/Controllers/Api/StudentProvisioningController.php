<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportStudentsRequest;
use App\Http\Requests\Admin\ProvisionStudentRequest;
use App\Mail\StudentOnboardingMail;
use App\Models\AuditLog;
use App\Models\OnboardingToken;
use App\Models\SystemSetting;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StudentProvisioningController extends Controller
{
    use ApiResponse;

    /**
     * Single student manual provisioning by Administrator.
     */
    public function store(ProvisionStudentRequest $request): JsonResponse
    {
        $admin = $request->user();

        return DB::transaction(function () use ($request, $admin) {
            $role = in_array($request->input('role'), ['student', 'event_staff', 'admin']) 
                ? $request->input('role') 
                : 'student';

            $user = User::create([
                'uuid' => (string) Str::uuid(),
                'student_number' => trim($request->input('student_number')),
                'first_name' => trim($request->input('first_name')),
                'middle_name' => $request->input('middle_name') ? trim($request->input('middle_name')) : null,
                'last_name' => trim($request->input('last_name')),
                'email' => strtolower(trim($request->input('email'))),
                'year_level' => $request->input('year_level') ? trim($request->input('year_level')) : '1st Year',
                'section_block' => $request->input('section_block') ? trim($request->input('section_block')) : 'Block 1',
                'password' => null, // NO default password as required
                'role' => $role,
                'status' => 'pending_onboarding',
            ]);

            // Create 48-hour secure onboarding token
            $tokenString = Str::random(64);
            $onboardingToken = OnboardingToken::create([
                'user_id' => $user->id,
                'token' => $tokenString,
                'expires_at' => now()->addHours(48),
            ]);

            $baseUrl = $request->getSchemeAndHttpHost();
            $onboardingUrl = "{$baseUrl}/onboarding?token={$tokenString}";
            $downloadUrl = "{$baseUrl}/download/app/{$tokenString}";

            // Send onboarding email
            try {
                Mail::to($user->email)->send(new StudentOnboardingMail($user, $onboardingUrl, $downloadUrl));
            } catch (\Exception $e) {
                Log::error("Failed to send onboarding email to {$user->email}: " . $e->getMessage());
            }

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'account_provisioned',
                'description' => "Administrator {$admin->full_name} manually provisioned student {$user->full_name} ({$user->student_number}). Year: {$user->year_level}, Block: {$user->section_block}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'provisioned_student_id' => $user->id,
                    'email' => $user->email,
                ],
            ]);

            return $this->successResponse([
                'student' => [
                    'id' => $user->id,
                    'student_number' => $user->student_number,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'year_level' => $user->year_level,
                    'section_block' => $user->section_block,
                    'status' => $user->status,
                ],
                'onboarding_url' => $onboardingUrl,
                'expires_at' => $onboardingToken->expires_at,
            ], 'Student account provisioned successfully. Onboarding email sent.', 201);
        });
    }

    /**
     * CSV Batch Student Provisioning by Administrator.
     */
    public function import(ImportStudentsRequest $request): JsonResponse
    {
        $admin = $request->user();
        $file = $request->file('file') ?? $request->file('csv_file');

        if (!$file) {
            return $this->errorResponse('Please upload a valid CSV file.', [], 422);
        }

        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return $this->errorResponse('Failed to open uploaded CSV file.', [], 400);
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $this->errorResponse('CSV file is empty or unreadable.', [], 400);
        }

        $domain = strtolower(SystemSetting::get('institutional_email_domain', 'tpc.edu.ph'));

        $successfulCount = 0;
        $failedCount = 0;
        $errors = [];
        $totalRows = 0;
        $processedEmails = [];
        $processedStudentIds = [];

        $baseUrl = $request->getSchemeAndHttpHost();
        $rowNumber = 1; // 1 is header

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip blank rows
            if (empty(array_filter($data))) {
                continue;
            }

            $totalRows++;

            // Support column mapping: Student ID, First Name, Middle Name, Last Name, Email, Year Level, Section Block
            $studentNumber = isset($data[0]) ? trim($data[0]) : '';
            $firstName = isset($data[1]) ? trim($data[1]) : '';
            $middleName = isset($data[2]) ? trim($data[2]) : '';
            $lastName = isset($data[3]) ? trim($data[3]) : '';
            $email = isset($data[4]) ? strtolower(trim($data[4])) : '';
            $yearLevel = isset($data[5]) && !empty(trim($data[5])) ? trim($data[5]) : '1st Year';
            $sectionBlock = isset($data[6]) && !empty(trim($data[6])) ? trim($data[6]) : 'BSIS 1-A';

            $rowErrors = [];

            if (empty($studentNumber)) {
                $rowErrors[] = 'Student ID is required.';
            }

            if (empty($firstName)) {
                $rowErrors[] = 'First Name is required.';
            }

            if (empty($lastName)) {
                $rowErrors[] = 'Last Name is required.';
            }

            if (empty($email)) {
                $rowErrors[] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = "Invalid email format '{$email}'.";
            }

            // Check intra-CSV duplicates
            if (in_array($studentNumber, $processedStudentIds)) {
                $rowErrors[] = "Duplicate Student ID '{$studentNumber}' within CSV file.";
            }

            if (in_array($email, $processedEmails)) {
                $rowErrors[] = "Duplicate Email '{$email}' within CSV file.";
            }

            // Check Database duplicates
            if (empty($rowErrors)) {
                if (User::where('student_number', $studentNumber)->exists()) {
                    $rowErrors[] = "Student ID '{$studentNumber}' already exists in database.";
                }

                if (User::where('email', $email)->exists()) {
                    $rowErrors[] = "Email '{$email}' already exists in database.";
                }
            }

            if (!empty($rowErrors)) {
                $failedCount++;
                $errors[] = [
                    'row' => $rowNumber,
                    'student_number' => $studentNumber,
                    'email' => $email,
                    'reasons' => $rowErrors,
                ];
                continue;
            }

            // Record as processed
            $processedStudentIds[] = $studentNumber;
            $processedEmails[] = $email;

            // Provision Student
            try {
                DB::transaction(function () use ($studentNumber, $firstName, $middleName, $lastName, $email, $yearLevel, $sectionBlock, $baseUrl) {
                    $user = User::create([
                        'uuid' => (string) Str::uuid(),
                        'student_number' => $studentNumber,
                        'first_name' => $firstName,
                        'middle_name' => $middleName ?: null,
                        'last_name' => $lastName,
                        'email' => $email,
                        'year_level' => $yearLevel,
                        'section_block' => $sectionBlock,
                        'password' => null,
                        'role' => 'student',
                        'status' => 'pending_onboarding',
                    ]);

                    $tokenString = Str::random(64);
                    OnboardingToken::create([
                        'user_id' => $user->id,
                        'token' => $tokenString,
                        'expires_at' => now()->addHours(48),
                    ]);

                    $onboardingUrl = "{$baseUrl}/onboarding?token={$tokenString}";
                    $downloadUrl = "{$baseUrl}/download/app/{$tokenString}";

                    try {
                        Mail::to($user->email)->send(new StudentOnboardingMail($user, $onboardingUrl, $downloadUrl));
                    } catch (\Exception $e) {
                        // ignore mail failure during bulk batch
                    }
                });

                $successfulCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = [
                    'row' => $rowNumber,
                    'student_number' => $studentNumber,
                    'email' => $email,
                    'reasons' => ['Database transaction error: ' . $e->getMessage()],
                ];
            }
        }

        fclose($handle);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'csv_import',
            'description' => "Administrator {$admin->full_name} completed CSV batch import. Total: {$totalRows}, Successful: {$successfulCount}, Failed: {$failedCount}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'total_rows' => $totalRows,
                'successful' => $successfulCount,
                'failed' => $failedCount,
            ],
        ]);

        return $this->successResponse([
            'total_rows' => $totalRows,
            'successful' => $successfulCount,
            'failed' => $failedCount,
            'errors' => $errors,
        ], "CSV import completed. {$successfulCount} students provisioned successfully, {$failedCount} failed.");
    }
}
