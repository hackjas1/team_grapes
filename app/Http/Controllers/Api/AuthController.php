<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use App\Mail\PasswordResetMail;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Authenticate user via email or student_number + password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $loginInput = trim($request->input('login'));
        $password = $request->input('password');

        $throttleKey = Str::transliterate(Str::lower($loginInput) . '|' . $request->ip());

        // Check if user has exceeded 3 wrong attempts (1 minute cooldown)
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $secondsRemaining = RateLimiter::availableIn($throttleKey);

            AuditLog::create([
                'user_id' => null,
                'action' => 'login_rate_limited',
                'description' => "Login throttled for identifier '{$loginInput}'. Locked for {$secondsRemaining}s due to 3 failed password attempts.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->errorResponse(
                "Too many wrong password attempts. To protect this account, login is temporarily locked for {$secondsRemaining} second(s). Please wait 1 minute before trying again.",
                [
                    'retry_after_seconds' => $secondsRemaining,
                    'is_locked' => true,
                ],
                429
            );
        }

        $user = User::where('email', $loginInput)
            ->orWhere('student_number', $loginInput)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            // Record failed attempt with a 60-second decay (1 minute)
            RateLimiter::hit($throttleKey, 60);
            $remaining = RateLimiter::remaining($throttleKey, 3);

            AuditLog::create([
                'user_id' => $user?->id,
                'action' => 'login_failed',
                'description' => "Failed login attempt for identifier: {$loginInput} (Remaining attempts before lock: {$remaining})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $msg = $remaining > 0
                ? "Invalid credentials. You have {$remaining} attempt(s) remaining before a 1-minute temporary lockout."
                : "Too many wrong password attempts. Login is temporarily locked for 1 minute to avoid flooding.";

            return $this->errorResponse($msg, [
                'remaining_attempts' => $remaining,
                'max_attempts' => 3,
                'is_locked' => $remaining === 0,
            ], $remaining === 0 ? 429 : 401);
        }

        // Credentials are valid, clear failed attempts
        RateLimiter::clear($throttleKey);

        if ($user->status !== 'active') {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'login_rejected',
                'description' => "Login rejected for account with status: {$user->status}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $statusMsg = match ($user->status) {
                'suspended', 'blocked' => 'Account Blocked: Your student account has been BLOCKED due to multiple unauthorized device/browser login attempts. Please contact your BSIS Administrator to unblock.',
                'pending' => 'Account is pending onboarding completion. Please verify your email first.',
                default => "Account is not active. Status: {$user->status}."
            };

            return $this->errorResponse($statusMsg, [
                'status' => $user->status,
                'account_blocked' => in_array($user->status, ['suspended', 'blocked']),
            ], 403);
        }

        // Option B: Strict Single-Device Lockdown for Students (Anti-Cloning & Anti-Duplication Protected)
        $newlyBoundCred = null;
        if ($user->role === 'student') {
            $deviceMismatchKey = "device_mismatch:{$user->id}";
            $suppliedDeviceCred = $request->input('device_credential');

            // 1. Anti-App Duplication & Cloned Device Collision Check:
            // Prevent two different students from using cloned app instances on the same physical phone
            if (!empty($suppliedDeviceCred)) {
                $conflictingDevice = Device::where('device_credential', $suppliedDeviceCred)
                    ->where('user_id', '!=', $user->id)
                    ->where('status', 'active')
                    ->first();

                if ($conflictingDevice) {
                    AuditLog::create([
                        'user_id' => $user->id,
                        'action' => 'app_duplication_collision_blocked',
                        'description' => "SECURITY ALERT: Student {$user->full_name} ({$user->student_number}) attempted login from a duplicated/cloned app on a physical device already bound to another student (#{$conflictingDevice->user_id}).",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    return $this->errorResponse(
                        'Security Violation: Hardware Collision Detected. This physical smartphone is already bound to another student account. App duplication (cloning, dual spaces) or sharing devices to record proxy attendance is strictly prohibited.',
                        [
                            'device_bound' => true,
                            'reason' => 'device_collision_detected',
                            'account_blocked' => false,
                        ],
                        403
                    );
                }
            }

            $activeDevice = $user->devices()->where('status', 'active')->first();
            if ($activeDevice) {
                // Active device is registered: Must match this client's hardware
                if (!$suppliedDeviceCred || !hash_equals($activeDevice->device_credential, $suppliedDeviceCred)) {
                    // Record failed unauthorized device attempt (tracked over 7 days)
                    RateLimiter::hit($deviceMismatchKey, 86400 * 7);
                    $attempts = RateLimiter::attempts($deviceMismatchKey);
                    $remaining = max(0, 3 - $attempts);

                    if ($attempts >= 3) {
                        // 3rd attempt: BLOCK / SUSPEND THE STUDENT ACCOUNT
                        $user->status = 'suspended';
                        $user->save();
                        RateLimiter::clear($deviceMismatchKey);

                        AuditLog::create([
                            'user_id' => $user->id,
                            'action' => 'account_blocked_device_mismatch',
                            'description' => "SECURITY ACTION: Student {$user->full_name} ({$user->student_number}) account has been BLOCKED after 3 unauthorized device/browser login attempts.",
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                        ]);

                        return $this->errorResponse(
                            'Security Alert: Your student account has been BLOCKED due to 3 consecutive login attempts from an unauthorized browser or device. Please contact the BSIS Administrator to unblock your account.',
                            [
                                'device_bound' => true,
                                'reason' => 'account_blocked_device_mismatch',
                                'account_blocked' => true,
                                'remaining_attempts' => 0,
                            ],
                            403
                        );
                    }

                    AuditLog::create([
                        'user_id' => $user->id,
                        'action' => 'login_rejected_device_mismatch',
                        'description' => "Login rejected for student {$user->full_name} ({$user->student_number}): Attempted login from an unauthorized secondary phone. (Attempt {$attempts}/3 - Remaining: {$remaining})",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    return $this->errorResponse(
                        "Access Restricted: This student account is permanently locked to your registered mobile phone (TPC Mobile App). Logging in on unauthorized secondary devices is strictly prohibited. Warning: {$remaining} attempt(s) remaining before your account is blocked. If you changed your phone, please submit a device reset request through the BSIS Administrator.",
                        [
                            'device_bound' => true,
                            'reason' => 'device_credential_mismatch',
                            'attempts_used' => $attempts,
                            'remaining_attempts' => $remaining,
                            'account_blocked' => false,
                        ],
                        403
                    );
                }

                // Successful login from authorized device: Reset mismatch attempts counter
                RateLimiter::clear($deviceMismatchKey);
            } else {
                // No active device bound (e.g. after Admin Device Reset)
                // Automatically bind this new phone as the authorized mobile device!
                $newDeviceCred = (string) Str::uuid();
                $activeDevice = Device::create([
                    'user_id' => $user->id,
                    'device_credential' => $newDeviceCred,
                    'device_name' => $request->input('device_name', 'Student Mobile Device'),
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'status' => 'active',
                    'bound_at' => now(),
                ]);

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'device_rebound_on_login',
                    'description' => "Student {$user->full_name} ({$user->student_number}) bound new mobile device on login.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                $newlyBoundCred = $newDeviceCred;
                RateLimiter::clear($deviceMismatchKey);
            }
        }

        // Revoke existing tokens if desired or create a new token
        $tokenName = $request->input('device_name', 'Web Browser Token');
        $token = $user->createToken($tokenName)->plainTextToken;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => "User {$user->full_name} logged in successfully.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse([
            'token' => $token,
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
                'device_credential' => $newlyBoundCred,
            ],
        ], 'Login successful.');
    }

    /**
     * Logout and revoke all tokens for the user account across all tabs and devices.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            // Revoke all personal access tokens for this user account across all tabs and sessions
            $user->tokens()->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'logout',
                'description' => "User {$user->full_name} ({$user->role}) logged out. All active session tokens revoked.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $this->successResponse([], 'Logged out successfully.');
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeDevice = $user->devices()->where('status', 'active')->first();

        return $this->successResponse([
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
            'active_device' => $activeDevice ? [
                'device_credential' => $activeDevice->device_credential,
                'device_name' => $activeDevice->device_name,
                'bound_at' => $activeDevice->bound_at,
            ] : null,
        ], 'User profile retrieved successfully.');
    }

    /**
     * Forgot password request (Protected with anti-flooding rate limiter).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $input = trim($request->input('login') ?: $request->input('email'));
        $throttleKey = Str::transliterate('forgot_pw|' . Str::lower($input) . '|' . $request->ip());

        // Max 3 requests per 15 minutes (900s) to prevent spamming and enumeration
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $secondsRemaining = RateLimiter::availableIn($throttleKey);

            AuditLog::create([
                'user_id' => null,
                'action' => 'forgot_password_throttled',
                'description' => "Forgot password throttled for '{$input}' from IP {$request->ip()}. Locked for {$secondsRemaining}s.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->errorResponse(
                "Too many password reset requests. Please wait {$secondsRemaining} second(s) before trying again.",
                ['retry_after_seconds' => $secondsRemaining],
                429
            );
        }

        RateLimiter::hit($throttleKey, 900);

        $user = User::where('email', strtolower($input))
            ->orWhere('student_number', $input)
            ->first();

        if ($user && $user->email) {
            // Check 7-Day Cooldown Policy on automated self-service resets
            $lastReset = AuditLog::where('user_id', $user->id)
                ->whereIn('action', ['password_reset_completed', 'admin_direct_password_reset'])
                ->latest('created_at')
                ->first();

            if ($lastReset) {
                $lastResetTime = Carbon::parse($lastReset->created_at);
                $cooldownEnd = $lastResetTime->copy()->addDays(7);
                if ($cooldownEnd->isFuture()) {
                    $daysLeft = (int) ceil(now()->diffInHours($cooldownEnd) / 24);
                    $formattedDate = $cooldownEnd->format('M d, Y h:i A');

                    AuditLog::create([
                        'user_id' => $user->id,
                        'action' => 'forgot_password_cooldown_blocked',
                        'description' => "Automated password reset blocked by 7-day cooldown policy for {$user->email}. Last reset on {$lastResetTime->format('M d, Y')}.",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    return $this->errorResponse(
                        "Security Policy: Self-service password resets are limited to once every 7 days. Your password was recently reset on {$lastResetTime->format('M d, Y')}. You can request another automated reset in {$daysLeft} day(s) (after {$formattedDate}). For urgent assistance, please coordinate with your BSIS Administrator for an instant password override.",
                        [
                            'cooldown_active' => true,
                            'last_reset_at' => $lastResetTime->toIso8601String(),
                            'next_allowed_reset_at' => $cooldownEnd->toIso8601String(),
                            'days_remaining' => $daysLeft,
                        ],
                        429
                    );
                }
            }

            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            try {
                Mail::to($user->email)->send(new PasswordResetMail($token, $user));
            } catch (\Exception $e) {
                Log::error("Failed to send password reset email to {$user->email}: " . $e->getMessage());
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'forgot_password_requested',
                'description' => "Password reset token generated and sent to {$user->email} (User: {$user->full_name}, Role: {$user->role}).",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Generic response to prevent user account enumeration
        return $this->successResponse([], 'If an account matches the provided identifier, a password reset link and code have been sent to the registered institutional email address.');
    }

    /**
     * Reset password using token (Enforces 60-min expiry, rate limits, revokes sessions, preserves device binding).
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $input = trim($request->input('email') ?: $request->input('login'));
        $token = trim($request->input('token'));
        $throttleKey = Str::transliterate('reset_pw|' . $request->ip());

        // Max 5 attempts per 15 minutes to prevent brute-forcing reset tokens
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $secondsRemaining = RateLimiter::availableIn($throttleKey);

            AuditLog::create([
                'user_id' => null,
                'action' => 'password_reset_throttled',
                'description' => "Password reset brute-force throttled from IP {$request->ip()}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->errorResponse(
                "Too many failed password reset attempts. Please wait {$secondsRemaining} second(s) before attempting again.",
                ['retry_after_seconds' => $secondsRemaining],
                429
            );
        }

        $user = User::where('email', strtolower($input))
            ->orWhere('student_number', $input)
            ->first();

        if (!$user) {
            RateLimiter::hit($throttleKey, 900);
            return $this->errorResponse('Invalid or expired password reset token.', [], 400);
        }

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        // 1. Verify token existence and hash match
        if (!$record || !Hash::check($token, $record->token)) {
            RateLimiter::hit($throttleKey, 900);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'password_reset_failed',
                'description' => "Failed password reset attempt for {$user->email}: Invalid token.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->errorResponse('Invalid or expired password reset token.', [], 400);
        }

        // 2. Enforce 60-minute token expiration
        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            RateLimiter::hit($throttleKey, 900);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'password_reset_expired',
                'description' => "Expired password reset token rejected for {$user->email}.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->errorResponse('This password reset token has expired (tokens are valid for 60 minutes). Please request a new reset code.', [], 400);
        }

        // 3. Clear rate limiters
        RateLimiter::clear($throttleKey);
        RateLimiter::clear(Str::transliterate('forgot_pw|' . Str::lower($user->email) . '|' . $request->ip()));

        // 4. Update user password
        $user->password = Hash::make($request->input('password'));
        if ($user->status === 'pending_onboarding') {
            $user->status = 'active';
        }
        $user->save();

        // 5. Invalidate existing active API Sanctum bearer tokens (forces re-login across web/browsers)
        $user->tokens()->delete();

        // 6. Consume and delete used reset token
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // 7. Security Guarantee: Device binding in `devices` table is intentionally KEPT INTACT.
        // For students, their physical mobile phone keystore credential remains bound and active.

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'password_reset_completed',
            'description' => "Password reset completed successfully for {$user->full_name} ({$user->email}, Role: {$user->role}). Sanctum tokens revoked; device hardware binding preserved.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'user_id' => $user->id,
                'role' => $user->role,
                'device_binding_preserved' => $user->role === 'student',
            ],
        ]);

        return $this->successResponse([], 'Password has been reset successfully. You may now log in with your new password.');
    }
}
