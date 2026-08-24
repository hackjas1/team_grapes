<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteOnboardingRequest;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\OnboardingToken;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    use ApiResponse;

    /**
     * Inspect and validate an onboarding token.
     */
    public function show(string $token): JsonResponse
    {
        $onboardingToken = OnboardingToken::with('user')
            ->where('token', $token)
            ->first();

        if (!$onboardingToken) {
            return $this->errorResponse('Onboarding token is invalid or does not exist.', [], 404);
        }

        if ($onboardingToken->used_at !== null) {
            return $this->errorResponse('This onboarding token has already been used.', [], 410);
        }

        if ($onboardingToken->expires_at->isPast()) {
            return $this->errorResponse('This onboarding token has expired. Please contact an administrator.', [], 410);
        }

        $user = $onboardingToken->user;

        return $this->successResponse([
            'token' => $onboardingToken->token,
            'expires_at' => $onboardingToken->expires_at,
            'student' => [
                'student_number' => $user->student_number,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ],
        ], 'Onboarding token is valid.');
    }

    /**
     * Complete onboarding, set student password, and perform initial device binding.
     */
    public function complete(CompleteOnboardingRequest $request, string $token): JsonResponse
    {
        $onboardingToken = OnboardingToken::with('user')
            ->where('token', $token)
            ->first();

        if (!$onboardingToken || !$onboardingToken->isValid()) {
            return $this->errorResponse('Invalid, expired, or already used onboarding token.', [], 400);
        }

        $user = $onboardingToken->user;

        return DB::transaction(function () use ($request, $user, $onboardingToken) {
            // 1. Set password and activate user
            $user->password = Hash::make($request->input('password'));
            $user->status = 'active';
            $user->save();

            // Clear any lingering rate limits or mismatch attempts
            \Illuminate\Support\Facades\RateLimiter::clear("device_mismatch:{$user->id}");

            // 2. Invalidate single-use onboarding token
            $onboardingToken->used_at = now();
            $onboardingToken->save();

            // 3. Record Audit Activity
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'account_onboarded',
                'description' => "Student {$user->full_name} ({$user->student_number}) activated account and created password.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // 4. Generate API token
            $apiToken = $user->createToken('Student Mobile Session')->plainTextToken;

            return $this->successResponse([
                'token' => $apiToken,
                'user' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'student_number' => $user->student_number,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'year_level' => $user->year_level,
                    'section_block' => $user->section_block,
                    'status' => $user->status,
                ],
            ], 'Account onboarding and device binding completed successfully.');
        });
    }
}
