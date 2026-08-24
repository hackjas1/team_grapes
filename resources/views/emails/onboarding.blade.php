<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>BSIS Student Account Activation & Mobile App — Talibon Polytechnic College</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, Arial, sans-serif; background-color: #F8FAFC; color: #0F172A; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 32px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #063B5C; }
        .title { color: #063B5C; font-size: 20px; font-weight: bold; margin-top: 10px; letter-spacing: 0.5px; }
        .subtitle { color: #0284C7; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .content { margin: 25px 0; font-size: 15px; line-height: 1.6; color: #334155; }
        .btn-container { text-align: center; margin: 15px 0; }
        .btn-primary { background-color: #063B5C; color: #FFFFFF !important; text-decoration: none; padding: 13px 28px; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 14.5px; }
        .btn-download { background-color: #0284C7; color: #FFFFFF !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 14px; }
        .btn-ios { background-color: #0F172A; color: #FFFFFF !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 14px; }
        .step-card { background-color: #F0F9FF; border-left: 4px solid #0284C7; padding: 14px 18px; margin: 16px 0; border-radius: 6px; }
        .security-badge { background-color: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 12px 16px; margin: 20px 0; font-size: 13px; color: #065F46; }
        .token-box { background-color: #F1F5F9; color: #063B5C; padding: 10px 14px; border-radius: 6px; font-family: monospace; word-break: break-all; margin: 10px 0; font-size: 12px; border: 1px dashed #CBD5E1; }
        .footer { text-align: center; font-size: 12px; color: #64748B; border-top: 1px solid #F1F5F9; padding-top: 20px; margin-top: 28px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="subtitle">Bachelor of Science in Information Systems</div>
            <div class="title">Talibon Polytechnic College</div>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,</p>
            <p>Your official student account for the <strong>BSIS Event Attendance & Compliance Monitoring System</strong> has been provisioned on our live cloud portal: <a href="https://tpc-bsis.online" style="color: #0284C7; text-decoration: none; font-weight: bold;">tpc-bsis.online</a>.</p>
            
            <div class="step-card">
                <strong>Student ID:</strong> {{ $user->student_number }}<br>
                <strong>Institutional Email:</strong> {{ $user->email }}<br>
                <strong>Academic Program:</strong> Bachelor of Science in Information Systems
            </div>

            <p style="margin-bottom: 8px;">Please complete the <strong>2 onboarding steps</strong> below:</p>

            <!-- STEP 1: SET PASSWORD -->
            <div style="background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 18px; margin-bottom: 16px;">
                <strong style="color: #063B5C; font-size: 15px;">Step 1: Set Account Password & Activate</strong>
                <p style="font-size: 13.5px; color: #64748B; margin: 6px 0 14px 0;">Create your secure password to activate your student profile and enable biometric quick logins.</p>
                <div class="btn-container" style="margin: 0; text-align: left;">
                    <a href="{{ $onboardingUrl }}" class="btn-primary">🔐 Set Password & Activate Profile</a>
                </div>
            </div>

            <!-- STEP 2: DOWNLOAD MOBILE ATTENDANCE APP -->
            <div style="background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; padding: 18px; margin-bottom: 16px;">
                <strong style="color: #0284C7; font-size: 15px;">Step 2: Install Official Mobile Attendance App</strong>
                <p style="font-size: 13.5px; color: #64748B; margin: 6px 0 14px 0;">Choose your device type to install the official attendance scanner app:</p>
                
                <!-- Android Option -->
                <div style="background-color: #F8FAFC; border: 1px solid #EEF2F6; border-radius: 8px; padding: 14px; margin-bottom: 12px;">
                    <strong style="color: #063B5C; font-size: 13.5px; display: block; margin-bottom: 4px;">🤖 Android Smartphone (APK):</strong>
                    <span style="font-size: 12.5px; color: #64748B; display: block; margin-bottom: 10px;">Direct high-speed download for all Android phones.</span>
                    <a href="{{ $downloadUrl }}" class="btn-download">📲 Download Android APK</a>
                </div>

                <!-- iOS Option -->
                <div style="background-color: #F8FAFC; border: 1px solid #EEF2F6; border-radius: 8px; padding: 14px;">
                    <strong style="color: #0F172A; font-size: 13.5px; display: block; margin-bottom: 4px;">🍎 Apple iOS (iPhone & iPad):</strong>
                    <span style="font-size: 12.5px; color: #64748B; display: block; margin-bottom: 10px;">Download the free <strong>Expo Go</strong> runtime from the Apple App Store, then sign in with your student credentials.</span>
                    <a href="https://apps.apple.com/app/expo-go/id982105225" target="_blank" class="btn-ios"> Open Apple App Store</a>
                </div>
            </div>

            <!-- OFFICIAL STUDENT HUB LINK -->
            <div style="background-color: #F0F9FF; border: 1px dashed #0284C7; border-radius: 8px; padding: 14px; margin-bottom: 16px; text-align: center;">
                <span style="font-size: 13px; color: #0369A1;">Need instructions or manual installation guides? Visit our official Student Hub:</span><br>
                <a href="{{ $studentHubUrl }}" style="color: #0284C7; font-weight: bold; font-size: 13.5px; text-decoration: underline; display: inline-block; margin-top: 6px;">🌐 https://tpc-bsis.online/student</a>
            </div>

            <!-- SECURITY NOTICE -->
            <div class="security-badge">
                <strong>🔒 1-Student-1-Device Binding:</strong>
                Your activation token binds your student profile to your personal device keystore. Face ID / Fingerprint unlock is supported for anti-proxy attendance security.
            </div>

            <p style="font-size: 13px; color: #64748B; margin-top: 20px;">Direct Password Activation Link:</p>
            <div class="token-box">{{ $onboardingUrl }}</div>

            <p style="font-size: 12px; color: #DC2626; margin-top: 18px;"><strong>Notice:</strong> This email contains single-use security credentials. Do not forward or share this email with others.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} BSIS Department — Talibon Polytechnic College. All rights reserved.<br>
            <a href="https://tpc-bsis.online" style="color: #64748B; text-decoration: none;">tpc-bsis.online</a> &bull; <a href="https://www.tpc.edu.ph/academics/bachelor-of-science-in-information-systems" style="color: #64748B; text-decoration: none;">BSIS Academic Program</a>
        </div>
    </div>
</body>
</html>
