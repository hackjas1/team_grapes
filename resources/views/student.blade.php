<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSIS Event Attendance System — Student Portal & App Download</title>
    
    <!-- PWA Manifest & Icons -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#063B5C">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/png" href="/images/bsis-logo.png">

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- BSIS Institutional Color Theme CSS -->
    <link rel="stylesheet" href="/css/bsis-theme.css">

    <!-- Security: Suppress DevTools console output to protect credentials, tokens, and keys -->
    <script>
        (function() {
            try {
                var noop = function() {};
                window.console.log = noop;
                window.console.info = noop;
                window.console.debug = noop;
                window.console.warn = noop;
            } catch(e) {}
        })();
    </script>

    <style>
        :root {
            --bsis-navy: #063B5C;
            --bsis-navy-dark: #04253A;
            --bsis-cyan: #35C4E8;
            --bsis-bg: #F5F9FC;
        }
        body { 
            background-color: var(--bsis-bg); 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #0F172A;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .bsis-header { 
            background-color: var(--bsis-navy); 
            color: #FFFFFF; 
            padding: 12px 16px; 
            box-shadow: 0 4px 12px rgba(6, 59, 92, 0.12); 
        }
        @media (max-width: 576px) {
            .bsis-header {
                padding: 10px 10px;
            }
            .bsis-header .container {
                padding-left: 2px !important;
                padding-right: 2px !important;
            }
        }
        .hero-banner {
            background: linear-gradient(145deg, var(--bsis-navy) 0%, var(--bsis-navy-dark) 100%);
            border-radius: 20px;
            color: #FFFFFF;
            padding: 36px 24px;
            box-shadow: 0 10px 25px rgba(6, 59, 92, 0.18);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 320px;
            height: 320px;
            background-image: url('/images/bsis-logo.png');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            opacity: 0.11;
            pointer-events: none;
            z-index: 0;
            filter: drop-shadow(0 0 12px rgba(53, 196, 232, 0.3));
        }
        .hero-banner > * {
            position: relative;
            z-index: 2;
        }
        .hero-banner h1,
        .hero-banner h2,
        .hero-banner h3,
        .hero-banner h4,
        .hero-banner h5,
        .hero-banner h6 {
            color: #FFFFFF !important;
        }
        .hero-banner::after {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(53, 196, 232, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 1;
        }
        .bsis-card {
            background: #FFFFFF;
            border-radius: 18px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .feature-icon-box {
            width: 44px;
            min-width: 44px;
            max-width: 44px;
            height: 44px;
            min-height: 44px;
            max-height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            margin: 0;
        }
        .btn-download-apk {
            background-color: var(--bsis-cyan);
            color: #04253A !important;
            font-weight: 800;
            font-size: 1.02rem;
            padding: 14px 24px;
            border-radius: 12px;
            border: none;
            box-shadow: 0 6px 18px rgba(53, 196, 232, 0.35);
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-download-apk:hover {
            background-color: #22B0D4;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(53, 196, 232, 0.45);
        }
        .btn-download-ios {
            background-color: rgba(255, 255, 255, 0.15);
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 1.02rem;
            padding: 14px 24px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(10px);
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .btn-download-ios:hover {
            background-color: rgba(255, 255, 255, 0.25);
            border-color: #FFFFFF;
            transform: translateY(-2px);
            color: #FFFFFF;
        }
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--bsis-navy);
            color: #FFFFFF;
            font-weight: bold;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .password-input-wrapper {
            position: relative;
        }
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748B;
            cursor: pointer;
        }
        .footer-sec {
            margin-top: auto;
            background: #04253A;
            color: #FFFFFF;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 36px 0 24px;
        }
        .footer-sec a {
            color: #E2E8F0;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .footer-sec a:hover {
            color: var(--bsis-cyan);
        }
        .footer-contact-title {
            color: var(--bsis-cyan);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .footer-contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px 20px;
            font-size: 0.88rem;
        }
        .footer-contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #E2E8F0;
        }
        .footer-contact-item i {
            font-size: 1.1rem;
            color: var(--bsis-cyan);
            flex-shrink: 0;
        }
        .modal {
            z-index: 1060 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal-header.text-white .modal-title,
        .modal-header[style*="background"] .modal-title {
            color: #FFFFFF !important;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header class="bsis-header">
        <div class="container d-flex align-items-center justify-content-start">
            <div class="d-flex align-items-center gap-2">
                <img src="/images/tpc-logo.png" alt="TPC Logo" style="height: 38px; min-width: 38px; border-radius: 50%;">
                <div class="fw-bold text-white text-truncate" style="font-size: 0.95rem; line-height: 1.2;">Talibon Polytechnic College</div>
            </div>
        </div>
    </header>

    <main class="container py-4">

        <!-- ==========================================
             VIEW 1: DEFAULT STUDENT WELCOME & APP HUB
             ========================================== -->
        <section id="view-welcome" class="app-view">
            <!-- Hero Banner -->
            <div class="hero-banner text-center mb-4">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(53, 196, 232, 0.18); border: 1px solid rgba(53, 196, 232, 0.3);">
                    <i class="bi bi-patch-check-fill text-info"></i>
                    <span style="font-size: 0.8rem; font-weight: 600; color: #FFFFFF;">Official College Attendance System</span>
                </div>
                <h2 class="fw-bold mb-2 display-6 text-white" style="color: #FFFFFF !important;">BSIS Student Attendance Portal</h2>
                <p class="text-light opacity-75 mb-4 mx-auto" style="max-width: 620px; font-size: 1rem;">
                    Live event attendance scanning, clearance compliance, and attendance records are powered exclusively by the official <strong>TPC Mobile App</strong>.
                </p>

                <!-- Primary Download & Platform Options -->
                <div class="d-flex flex-wrap justify-content-center gap-3 mb-2">
                    <a href="/download/app" class="btn-download-apk">
                        <i class="bi bi-android2 fs-4"></i>
                        <span>Download for Android (APK)</span>
                    </a>
                    <button type="button" class="btn-download-ios" onclick="StudentPWA.openIosGuideModal(event)" data-bs-toggle="modal" data-bs-target="#modal-ios-guide">
                        <i class="bi bi-apple fs-4"></i>
                        <span>Install on iOS (iPhone / iPad)</span>
                    </button>
                </div>
                <span class="text-light opacity-60 small">
                    <i class="bi bi-shield-check text-info me-1"></i> Version 1.0.0 Stable • Cross-Platform Android & iOS Supported
                </span>
            </div>

            <!-- Administrator Account Provisioning Info Banner -->
            <div class="bsis-card p-3 mb-4 shadow-sm" style="background: #F0FDF4; border: 1px solid #BBF7D0; border-left: 5px solid #16A34A; border-radius: 14px;">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(22, 163, 74, 0.12); color: #16A34A; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <div class="flex-grow-1" style="font-size: 0.88rem; color: #14532D; line-height: 1.45;">
                        <strong class="d-block text-dark mb-1" style="font-size: 0.94rem;">
                            <i class="bi bi-shield-check text-success me-1"></i> Student Account Provisioning Notice
                        </strong>
                        <span>Student accounts are officially created and provisioned by the <strong>BSIS Department Administrator</strong>. Once registered, an automated activation link is sent to your email. If your account has not yet been registered, please coordinate with the <strong>BSIS Department Office</strong>.</span>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- 3-Step Student Getting Started Guide -->
                <div class="col-12 col-lg-7">
                    <div class="bsis-card p-4 h-100">
                        <h5 class="fw-bold text-primary mb-3">
                            <i class="bi bi-rocket-takeoff-fill text-primary me-2"></i>How to Get Started as a Student
                        </h5>
                        
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #EEF2F6;">
                                <div class="step-circle">1</div>
                                <div>
                                    <strong class="d-block text-dark mb-1">Check Your Institutional Email</strong>
                                    <p class="text-muted small mb-0">Look for the official activation email from <code>admin@tpc-bsis.online</code> containing your personalized activation link.</p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #EEF2F6;">
                                <div class="step-circle">2</div>
                                <div>
                                    <strong class="d-block text-dark mb-1">Set Password & Activate Account</strong>
                                    <p class="text-muted small mb-0">Click your unique invitation link to set up your secure password. Your account will be activated instantly.</p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #EEF2F6;">
                                <div class="step-circle">3</div>
                                <div>
                                    <strong class="d-block text-dark mb-1">Install App & Auto-Bind Your Smartphone</strong>
                                    <div class="text-muted small mb-0" style="line-height: 1.5;">
                                        <div class="mb-1"><strong>🤖 Android:</strong> Install the APK and sign in with your Student ID and password.</div>
                                        <div><strong>🍎 iOS (iPhone/iPad):</strong> Install <a href="https://apps.apple.com/app/expo-go/id982105225" target="_blank" class="fw-bold text-decoration-none">Expo Go</a> from the App Store or open via <a href="javascript:void(0)" onclick="StudentPWA.openIosGuideModal(event)" data-bs-toggle="modal" data-bs-target="#modal-ios-guide" class="fw-bold text-decoration-none" role="button">iOS Guide</a>.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security & Feature Highlights -->
                <div class="col-12 col-lg-5">
                    <div class="bsis-card p-4 h-100">
                        <h5 class="fw-bold text-primary mb-3">
                            <i class="bi bi-shield-shaded text-primary me-2"></i>Attendance Security
                        </h5>

                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #EEF2F6;">
                                <div class="feature-icon-box bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-phone-vibrate-fill"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="d-block text-dark small mb-1">1-Student-1-Device Binding</strong>
                                    <span class="text-muted small" style="font-size: 0.8rem; line-height: 1.35;">Hardware keystore locked to your physical phone</span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #EEF2F6;">
                                <div class="feature-icon-box bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="d-block text-dark small mb-1">GPS Geofence Verification</strong>
                                    <span class="text-muted small" style="font-size: 0.8rem; line-height: 1.35;">Validates real-time presence at event venues</span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #EEF2F6;">
                                <div class="feature-icon-box bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-qr-code-scan"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="d-block text-dark small mb-1">Anti-Proxy Dynamic QR</strong>
                                    <span class="text-muted small" style="font-size: 0.8rem; line-height: 1.35;">HMAC-SHA256 rotating codes refresh dynamically per event settings</span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: #F8FAFC; border: 1px solid #EEF2F6;">
                                <div class="feature-icon-box bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-fingerprint"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="d-block text-dark small mb-1">Biometric Quick Login</strong>
                                    <span class="text-muted small" style="font-size: 0.8rem; line-height: 1.35;">Fingerprint & Face Unlock for instant access</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ==========================================
             VIEW 2: SECURE ONBOARDING & PASSWORD SETUP
             ========================================== -->
        <section id="view-onboarding" class="app-view d-none">
            <div class="row justify-content-center py-2">
                <div class="col-12 col-md-6 col-lg-5">
                    <div class="bsis-card p-4 shadow">
                        <div class="text-center mb-3">
                            <div class="d-flex justify-content-center align-items-center gap-3 mb-2">
                                <img src="/images/tpc-logo.png" alt="TPC Logo" style="height: 55px; border-radius: 50%;">
                                <img src="/images/bsis-logo.png" alt="BSIS Logo" style="height: 55px;">
                            </div>
                            <div class="fw-bold text-uppercase mb-1" style="color: #063B5C; font-size: 0.95rem; letter-spacing: 0.8px; line-height: 1.25;">TALIBON POLYTECHNIC COLLEGE</div>
                            <div class="text-muted small fw-bold text-uppercase mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">BSIS DEPARTMENT</div>
                            <h4 class="fw-bold text-primary mb-1">Student Account Activation</h4>
                        </div>

                        <div id="onboarding-alert" class="alert alert-danger d-none" style="font-size: 0.88rem;"></div>

                        <div class="alert alert-info py-2 px-3 mb-3 text-start" style="font-size: 0.85rem; border-left: 4px solid var(--bsis-cyan);">
                            <strong>Student:</strong> <span id="onboard-student-name">...</span><br>
                            <strong>Student ID:</strong> <span id="onboard-student-id">...</span><br>
                            <strong>Email:</strong> <span id="onboard-student-email">...</span>
                        </div>

                        <form id="onboarding-form" onsubmit="StudentPWA.handleCompleteOnboarding(event)">
                            <input type="hidden" id="onboarding-token-val">
                            
                            <!-- Create Password -->
                            <div class="mb-3">
                                <label class="bsis-form-label fw-bold">Create Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="onboard-password" class="form-control" placeholder="Enter secure password" oninput="StudentPWA.validateOnboardPasswordLive()" required>
                                    <button class="password-toggle-btn" type="button" onclick="StudentPWA.togglePasswordVisibility('onboard-password', this)" title="Show / Hide Password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3">
                                <label class="bsis-form-label fw-bold">Confirm Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="onboard-password-confirm" class="form-control" placeholder="Re-enter password" oninput="StudentPWA.validateOnboardPasswordLive()" required>
                                    <button class="password-toggle-btn" type="button" onclick="StudentPWA.togglePasswordVisibility('onboard-password-confirm', this)" title="Show / Hide Password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Live Password Requirements Checklist -->
                            <div class="p-3 mb-3 bg-light rounded border" style="font-size: 0.82rem;">
                                <div class="fw-bold mb-2 text-dark"><i class="bi bi-shield-check text-primary"></i> Password Requirements:</div>
                                <div class="d-flex flex-column gap-1">
                                    <div id="rule-len" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> Minimum 8 characters</div>
                                    <div id="rule-lower" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> At least one lowercase letter (a-z)</div>
                                    <div id="rule-upper" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> At least one uppercase letter (A-Z)</div>
                                    <div id="rule-num" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> At least one number (0-9)</div>
                                    <div id="rule-sym" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> At least one special symbol (!@#$%^&*...)</div>
                                    <div id="rule-match" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> Passwords must match</div>
                                </div>
                            </div>

                            <button type="submit" id="onboard-submit-btn" class="btn btn-primary w-100 py-2 fw-bold" style="background-color: var(--bsis-navy); border-color: var(--bsis-navy);">
                                Confirm & Activate Account
                            </button>
                        </form>

                        <!-- Onboarding Success Screen (Guide to Mobile App) -->
                        <div id="onboarding-success-card" class="d-none text-center pt-2">
                            <div class="mb-3">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 3.5rem;"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">Account Activated Successfully! 🎉</h4>
                            <p class="text-muted small mb-3">Your student password has been created.</p>

                            <div class="p-3 bg-light rounded-3 border text-start mb-4" style="border-left: 4px solid var(--bsis-cyan) !important;">
                                <h6 class="fw-bold text-primary mb-1"><i class="bi bi-phone-fill me-1"></i> Next Step: Open the TPC Mobile App</h6>
                                <p class="text-muted small mb-0" style="line-height: 1.45;">
                                    Sign in on your Android or Apple iOS smartphone using your <strong>Student ID</strong> and your newly created password. Your phone hardware will be registered automatically!
                                </p>
                            </div>

                            <div class="d-flex flex-column gap-2">
                                <a href="/download/app" class="btn-download-apk w-100 justify-content-center">
                                    <i class="bi bi-android2 fs-4"></i>
                                    <span>Download for Android (APK)</span>
                                </a>
                                <button type="button" class="btn btn-outline-dark w-100 py-2 fw-bold" onclick="StudentPWA.openIosGuideModal(event)" data-bs-toggle="modal" data-bs-target="#modal-ios-guide" style="border-radius: 12px;">
                                    <i class="bi bi-apple me-1"></i> iOS Installation Guide (iPhone / iPad)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==========================================
             VIEW 3: SECURE STUDENT PASSWORD RESET
             ========================================== -->
        <section id="view-reset-password" class="app-view d-none">
            <div class="row justify-content-center py-2">
                <div class="col-12 col-md-6 col-lg-5">
                    <div class="bsis-card p-4 shadow">
                        <div class="text-center mb-3">
                            <div class="d-flex justify-content-center align-items-center gap-3 mb-2">
                                <img src="/images/tpc-logo.png" alt="TPC Logo" style="height: 55px; border-radius: 50%;">
                                <img src="/images/bsis-logo.png" alt="BSIS Logo" style="height: 55px;">
                            </div>
                            <div class="fw-bold text-uppercase mb-1" style="color: #063B5C; font-size: 0.95rem; letter-spacing: 0.8px; line-height: 1.25;">TALIBON POLYTECHNIC COLLEGE</div>
                            <div class="text-muted small fw-bold text-uppercase mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">BSIS DEPARTMENT</div>
                            <h4 class="fw-bold text-primary mb-1">Student Password Reset</h4>
                        </div>

                        <div id="student-reset-alert" class="alert alert-danger d-none" style="font-size: 0.88rem; border-radius: 10px;"></div>
                        <div id="student-reset-success-alert" class="alert alert-success d-none" style="font-size: 0.88rem; border-radius: 10px;"></div>

                        <form id="student-reset-password-form" onsubmit="StudentPWA.handleCompletePasswordReset(event)">
                            <!-- Email or Student ID -->
                            <div class="mb-3">
                                <label class="bsis-form-label fw-bold small text-dark">Student ID or Institutional Email</label>
                                <input type="text" id="student-reset-identifier" class="form-control" placeholder="e.g. 2024-00001 or student@tpc.edu.ph" required>
                            </div>

                            <!-- Token -->
                            <div class="mb-3">
                                <label class="bsis-form-label fw-bold small text-dark">Password Reset Token</label>
                                <input type="text" id="student-reset-token-input" class="form-control font-monospace" placeholder="Paste 64-character token from email" style="font-size: 0.84rem;" required>
                            </div>

                            <!-- New Password -->
                            <div class="mb-3">
                                <label class="bsis-form-label fw-bold small text-dark">New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="student-reset-password" class="form-control" placeholder="Enter secure password" oninput="StudentPWA.validateStudentResetPasswordLive()" required>
                                    <button class="password-toggle-btn" type="button" onclick="StudentPWA.togglePasswordVisibility('student-reset-password', this)" title="Show / Hide Password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3">
                                <label class="bsis-form-label fw-bold small text-dark">Confirm New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="student-reset-password-confirm" class="form-control" placeholder="Re-enter new password" oninput="StudentPWA.validateStudentResetPasswordLive()" required>
                                    <button class="password-toggle-btn" type="button" onclick="StudentPWA.togglePasswordVisibility('student-reset-password-confirm', this)" title="Show / Hide Password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Live Password Requirements Checklist -->
                            <div class="p-3 mb-3 bg-light rounded border" style="font-size: 0.82rem;">
                                <div class="fw-bold mb-2 text-dark"><i class="bi bi-shield-check text-primary"></i> Password Requirements:</div>
                                <div class="d-flex flex-column gap-1">
                                    <div id="student-reset-rule-len" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> Minimum 8 characters</div>
                                    <div id="student-reset-rule-lower" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> At least one lowercase letter (a-z)</div>
                                    <div id="student-reset-rule-upper" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> At least one uppercase letter (A-Z)</div>
                                    <div id="student-reset-rule-num" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> At least one number (0-9)</div>
                                    <div id="student-reset-rule-sym" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> At least one special symbol (!@#$%^&*...)</div>
                                    <div id="student-reset-rule-match" class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> Passwords must match</div>
                                </div>
                            </div>

                            <button type="submit" id="student-reset-submit-btn" class="btn btn-primary w-100 py-2 fw-bold" style="background-color: var(--bsis-navy); border-color: var(--bsis-navy); border-radius: 10px;">
                                Confirm & Update Password
                            </button>
                        </form>

                        <!-- Reset Success Card -->
                        <div id="student-reset-success-card" class="d-none text-center pt-2">
                            <div class="mb-3">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 3.5rem;"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">Password Reset Successful! 🎉</h4>
                            <p class="text-muted small mb-3">Your password has been updated. Your phone device binding remains active.</p>

                            <div class="p-3 bg-light rounded-3 border text-start mb-4" style="border-left: 4px solid var(--bsis-cyan) !important;">
                                <h6 class="fw-bold text-primary mb-1"><i class="bi bi-phone-fill me-1"></i> Sign In to TPC Mobile App</h6>
                                <p class="text-muted small mb-0" style="line-height: 1.45;">
                                    You can now open the <strong>TPC Mobile App</strong> on your registered smartphone and sign in with your new password.
                                </p>
                            </div>

                            <a href="/download/app" class="btn-download-apk w-100 justify-content-center">
                                <i class="bi bi-android2 fs-4"></i>
                                <span>Open or Download Mobile App (APK)</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Confirmation Modal -->
        <div class="modal fade" id="modal-confirm-onboard" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                    <div class="modal-header text-white" style="background-color: var(--bsis-navy);">
                        <h5 class="modal-title fw-bold text-white mb-0" style="color: #FFFFFF !important; font-size: 1.05rem;">
                            <i class="bi bi-shield-lock-fill me-2" style="color: #35C4E8 !important;"></i>Account Activation Confirmation
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <i class="bi bi-shield-check text-primary" style="font-size: 3rem; color: #0284C7 !important;"></i>
                        <h5 class="fw-bold mt-3 mb-2" style="color: #0F172A;">Activate Your Student Account?</h5>
                        <p class="text-muted small mb-0" style="line-height: 1.45;">
                            This will create your secure password and activate your account. You will then sign in on the <strong>TPC BSIS Mobile App</strong> on your phone.
                        </p>
                    </div>
                    <div class="modal-footer justify-content-center gap-2 border-0 pt-0 pb-4">
                        <button type="button" class="btn btn-outline-secondary px-3 fw-bold" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                        <button type="button" id="btn-proceed-onboarding" onclick="StudentPWA.submitOnboarding()" class="btn btn-primary px-4 fw-bold" style="background-color: var(--bsis-navy); border-color: var(--bsis-navy); border-radius: 8px;">Confirm & Activate</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- iOS (iPhone / iPad) Installation Guide Modal -->
        <div class="modal fade" id="modal-ios-guide" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                    <div class="modal-header text-white" style="background: linear-gradient(135deg, #063B5C 0%, #032134 100%); border-top-left-radius: 20px; border-top-right-radius: 20px;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-apple fs-4 text-white"></i>
                            <h5 class="modal-title fw-bold mb-0 text-white" style="color: #FFFFFF !important;">iOS Installation Guide (iPhone & iPad)</h5>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small mb-3">Follow these 3 quick steps to run the official <strong>TPC BSIS Attendance App</strong> on your Apple iOS device:</p>
                        
                        <div class="d-flex flex-column gap-3 mb-3">
                            <div class="d-flex align-items-start gap-3 p-3 rounded-3 bg-light border">
                                <div class="step-circle" style="background-color: #063B5C;">1</div>
                                <div>
                                    <strong class="d-block text-dark mb-1">Install "Expo Go" from App Store</strong>
                                    <p class="text-muted small mb-2">Download the free Expo Go runtime from the official Apple App Store.</p>
                                    <a href="https://apps.apple.com/app/expo-go/id982105225" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-dark fw-bold py-1 px-3" style="border-radius: 8px;">
                                        <i class="bi bi-apple me-1"></i> Open Apple App Store
                                    </a>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-3 p-3 rounded-3 bg-light border">
                                <div class="step-circle" style="background-color: #063B5C;">2</div>
                                <div>
                                    <strong class="d-block text-dark mb-1">Activate Your Account & Set Password</strong>
                                    <p class="text-muted small mb-0">Use the personalized onboarding link sent to your institutional email to activate your account.</p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-3 p-3 rounded-3 bg-light border">
                                <div class="step-circle" style="background-color: #063B5C;">3</div>
                                <div>
                                    <strong class="d-block text-dark mb-1">Launch App & Sign In</strong>
                                    <p class="text-muted small mb-0">Sign in with your Student ID and password. Your iPhone hardware will be auto-bound and Face ID / Touch ID enabled for instant event scans!</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded-3 text-center" style="background: rgba(6, 59, 92, 0.05); border: 1px dashed rgba(6, 59, 92, 0.25);">
                            <small class="text-primary fw-bold d-block mb-1"><i class="bi bi-shield-check me-1"></i> 1-Student-1-Device Hardware Binding</small>
                            <span class="small text-muted">Your iPhone's Secure Enclave will be tied to your student profile for strict anti-proxy attendance security.</span>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 justify-content-center">
                        <button type="button" class="btn btn-primary px-4 fw-bold" style="background-color: #063B5C; border-color: #063B5C; border-radius: 10px;" data-bs-dismiss="modal">Got It, Thanks!</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: STUDENT VIEW EVENT DETAILS -->
        <div class="modal fade" id="modal-student-event-details" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                    <div class="modal-header text-white py-3 px-3 px-sm-4" style="background: linear-gradient(135deg, #063B5C 0%, #032134 100%);">
                        <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; background: rgba(53, 196, 232, 0.2); color: #35C4E8; font-size: 1.2rem;">
                                <i class="bi bi-calendar2-event-fill"></i>
                            </div>
                            <div class="min-w-0">
                                <h5 class="modal-title fw-bold mb-0 text-white text-truncate" id="stu-detail-event-title" style="font-size: 1.05rem;">Event Title</h5>
                                <small class="text-light d-block" style="opacity: 0.85; font-size: 0.78rem;">Session Scanning Windows & Venue Geofence</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white flex-shrink-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-3 p-sm-4">
                        <!-- Badges -->
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            <span id="stu-detail-status-badge" class="bsis-badge bsis-badge-success">ACTIVE</span>
                            <span id="stu-detail-audience-badge" class="badge bg-primary px-2 py-1" style="font-size: 0.8rem;">
                                <i class="bi bi-people-fill"></i> All BSIS Students
                            </span>
                            <span id="stu-detail-window-badge" class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.8rem;">
                                <i class="bi bi-clock-history"></i> Window: Open
                            </span>
                        </div>

                        <!-- Schedule & Fine Info -->
                        <div class="card p-3 mb-3 border-0 bg-light" style="border-radius: 12px;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <h6 class="fw-bold text-primary mb-0" style="font-size: 0.90rem;"><i class="bi bi-clock-fill me-1"></i> Attendance Session Scanning Windows</h6>
                                <span id="stu-detail-session-badge" class="badge bg-white text-dark border text-wrap text-start" style="font-size: 0.74rem;">2 SCANS</span>
                            </div>
                            <div class="row g-2 small mb-3">
                                <div class="col-12 col-sm-6">
                                    <span class="text-muted d-block" style="font-size: 0.78rem;">Overall Event Duration:</span>
                                    <strong id="stu-detail-schedule" class="text-dark" style="font-size: 0.84rem;">Loading...</strong>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <span class="text-muted d-block" style="font-size: 0.78rem;">Late / Missed Fine Policy:</span>
                                    <strong id="stu-detail-fine" class="text-danger" style="font-size: 0.84rem;">₱0.00</strong>
                                </div>
                            </div>
                            <div id="stu-detail-windows-container" class="row g-2">
                                <!-- Dynamic Windows Injected Here -->
                            </div>
                        </div>

                        <!-- Geofence & Venue -->
                        <div class="card p-3 mb-3 border-0 bg-light" style="border-radius: 12px;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <h6 class="fw-bold text-primary mb-0" style="font-size: 0.90rem;"><i class="bi bi-geo-alt-fill me-1"></i> Venue & Geofence Location</h6>
                                <span id="stu-detail-radius-badge" class="badge bg-info text-dark" style="font-size: 0.75rem;">50m Allowed Radius</span>
                            </div>
                            <p class="mb-2 small" id="stu-detail-venue-name" style="font-size: 0.84rem;"><strong>Talibon Polytechnic College</strong></p>
                            <div id="stu-detail-map" style="height: 200px; border-radius: 8px; border: 1px solid #cbd5e1; z-index: 1;" class="mb-1"></div>
                            <small class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-info-circle"></i> Cyan circle indicates student attendance scanning geofence perimeter.</small>
                        </div>

                        <!-- Description -->
                        <div class="card p-3 border-0 bg-light" style="border-radius: 12px;">
                            <h6 class="fw-bold text-primary mb-2" style="font-size: 0.90rem;"><i class="bi bi-card-text me-1"></i> Event Information & Notes</h6>
                            <p class="small text-muted mb-0" id="stu-detail-desc" style="font-size: 0.84rem; line-height: 1.45;">No description provided.</p>
                        </div>
                    </div>
                    <div class="modal-footer d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2 bg-light py-2 px-3 px-sm-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold order-last order-sm-first py-2 px-3 event-detail-btn-close" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i> Close
                        </button>
                        <div id="stu-detail-actions" class="d-flex flex-wrap gap-2 justify-content-stretch justify-content-sm-end">
                            <!-- Actions injected via JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Institutional Footer with Official Contact Details -->
    <footer class="footer-sec">
        <div class="container">
            <div class="row g-4 align-items-start mb-4 pb-4 border-bottom border-white border-opacity-10">
                <!-- Institution Brand & Info -->
                <div class="col-12 col-lg-5">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img src="/images/tpc-logo.png" alt="TPC Logo" style="height: 42px; width: 42px; border-radius: 50%; background: #fff; padding: 2px;">
                        <img src="/images/bsis-logo.png" alt="BSIS Logo" style="height: 42px; width: 42px;">
                        <div>
                            <div class="fw-bold text-white fs-6">Talibon Polytechnic College</div>
                            <a href="https://www.tpc.edu.ph/academics/bachelor-of-science-in-information-systems" target="_blank" rel="noopener noreferrer" style="color: var(--bsis-cyan); font-size: 0.78rem; font-weight: 600; text-decoration: none;" class="d-inline-flex align-items-center gap-1">
                                <span>Bachelor of Science in Information Systems</span>
                                <i class="bi bi-box-arrow-up-right" style="font-size: 0.65rem;"></i>
                            </a>
                        </div>
                    </div>
                    <p class="text-light opacity-75 small mb-0" style="line-height: 1.45;">
                        Official Smart Event Attendance & Compliance Monitoring System. Real-time GPS geofenced attendance verification & clearance processing.
                    </p>
                </div>

                <!-- Official Contact Details Grid -->
                <div class="col-12 col-lg-7">
                    <div class="footer-contact-title">CONTACT & OFFICIAL CHANNELS</div>
                    <div class="footer-contact-grid">
                        <div class="footer-contact-item">
                            <i class="bi bi-mortarboard-fill"></i>
                            <a href="https://www.tpc.edu.ph/academics/bachelor-of-science-in-information-systems" target="_blank" rel="noopener noreferrer">BSIS Academic Program</a>
                        </div>
                        <div class="footer-contact-item">
                            <i class="bi bi-globe2"></i>
                            <a href="https://tpc.edu.ph" target="_blank" rel="noopener noreferrer">tpc.edu.ph</a>
                        </div>
                        <div class="footer-contact-item">
                            <i class="bi bi-facebook"></i>
                            <a href="https://www.facebook.com/TalibonPolytechnicCollege" target="_blank" rel="noopener noreferrer">TalibonPolytechnicCollege</a>
                        </div>
                        <div class="footer-contact-item">
                            <i class="bi bi-envelope"></i>
                            <a href="mailto:tpcwebsite05@gmail.com">tpcwebsite05@gmail.com</a>
                        </div>
                        <div class="footer-contact-item">
                            <i class="bi bi-telephone"></i>
                            <a href="tel:0384115340">(038) 411 5340</a>
                        </div>
                        <div class="footer-contact-item">
                            <i class="bi bi-geo-alt"></i>
                            <span>San Isidro, Talibon, Bohol</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Copyright Line -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small text-light opacity-60">
                <div>Talibon Polytechnic College &bull; BSIS Attendance Monitoring System</div>
                <div>&copy; {{ date('Y') }} All Rights Reserved.</div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- App JavaScript Modules -->
    <script src="/js/storage.js?v={{ time() }}"></script>
    <script src="/js/student-app.js?v={{ time() }}"></script>
</body>
</html>
