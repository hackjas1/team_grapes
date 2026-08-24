<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloading TPC BSIS Attendance App</title>
    
    <link rel="icon" type="image/png" href="/images/bsis-logo.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --bsis-primary: #063B5C;
            --bsis-accent: #0284C7;
            --bsis-bg: #F8FAFC;
        }
        body {
            background-color: var(--bsis-bg);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .download-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 12px 35px rgba(6, 59, 92, 0.10);
            max-width: 500px;
            width: 100%;
            padding: 28px 22px;
            text-align: center;
        }
        .info-pill {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 10px;
            padding: 12px 14px;
            color: #1E40AF;
            font-size: 13px;
            margin-bottom: 16px;
            text-align: left;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .btn-action-download {
            background: linear-gradient(135deg, #063B5C 0%, #0284C7 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 14.5px;
            padding: 12px 20px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
            border: none;
        }
        .btn-action-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(2, 132, 199, 0.35);
        }
        /* Attention Bouncing Banner */
        .attention-banner {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            border: 2px dashed #F59E0B;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
            cursor: pointer;
            animation: bouncePulse 2s infinite ease-in-out;
            text-decoration: none;
            display: block;
        }
        @keyframes bouncePulse {
            0%, 100% { transform: translateY(0); box-shadow: 0 4px 10px rgba(245, 158, 11, 0.15); }
            50% { transform: translateY(-4px); box-shadow: 0 8px 18px rgba(245, 158, 11, 0.25); }
        }
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
            padding: 10px 12px;
            background: #F8FAFC;
            border: 1px solid #EDF2F7;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .step-badge {
            background: var(--bsis-primary);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .troubleshoot-box {
            background: #FEF2F2;
            border: 1.5px solid #FECACA;
            border-radius: 10px;
            padding: 12px 14px;
            margin-top: 14px;
            text-align: left;
            font-size: 12.5px;
            color: #991B1B;
        }
    </style>
</head>
<body>

    <div class="download-card">
        <!-- Institution Branding -->
        <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
            <img src="/images/tpc-logo.png" alt="TPC Logo" style="height: 36px; width: 36px;">
            <img src="/images/bsis-logo.png" alt="BSIS Logo" style="height: 36px; width: 36px;">
        </div>

        <!-- Success Status Icon -->
        <div class="mb-2">
            <i class="bi bi-cloud-arrow-down-fill text-primary" style="font-size: 54px;"></i>
        </div>

        <h4 class="fw-bold text-dark mb-1">Download Started!</h4>
        <p class="text-muted small mb-3">
            Official <strong>TPC BSIS Attendance App (103 MB)</strong>
        </p>

        <!-- ATTENTION: Read Instructions Scroll Banner -->
        <a href="#install-guide" class="attention-banner">
            <div class="d-flex align-items-center justify-content-center gap-2 text-dark fw-bold" style="font-size: 13.5px;">
                <span>⚠️ IMPORTANT: Read Install Steps Below</span>
                <i class="bi bi-arrow-down-circle-fill text-warning fs-5"></i>
            </div>
            <small class="text-danger fw-bold d-block mt-1" style="font-size: 11.5px;">
                Android will prompt "Harmful / Unsafe App" — Tap here to see how to bypass!
            </small>
        </a>

        <!-- Notification Bar Tip -->
        <div class="info-pill">
            <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 text-primary"></i>
            <div>
                <strong>Check notification bar:</strong> Swipe down from the top of your phone screen to monitor download progress.
            </div>
        </div>

        <!-- Restart Download Button -->
        <div class="mb-4">
            <a href="{{ $directApkUrl }}" id="main-download-btn" class="btn-action-download">
                <i class="bi bi-arrow-clockwise fs-5"></i>
                <span>Restart Download</span>
            </a>
        </div>

        <!-- 3-Step Quick Guide -->
        <div class="border-top pt-3 text-start" id="install-guide">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-bold text-dark small mb-0 text-uppercase" style="letter-spacing: 0.5px;">
                    <i class="bi bi-phone-fill text-primary me-1"></i> How to Install on Android:
                </h6>
                <span class="badge bg-warning text-dark fw-bold" style="font-size: 10px;">REQUIRED STEPS</span>
            </div>
            
            <div class="step-item">
                <div class="step-badge">1</div>
                <div>Swipe down your notification bar and tap <strong>TPC-BSIS-Attendance.apk</strong> once finished.</div>
            </div>

            <div class="step-item">
                <div class="step-badge">2</div>
                <div>If prompted, choose <strong>"Allow from this source"</strong> (Settings).</div>
            </div>

            <div class="step-item">
                <div class="step-badge">3</div>
                <div>Tap <strong>Install</strong>, open the app, and log in with your Student ID!</div>
            </div>

            <!-- CRITICAL Troubleshooting Box for Play Protect / Blocked App -->
            <div class="troubleshoot-box">
                <div class="fw-bold mb-1 d-flex align-items-center gap-1 text-danger" style="font-size: 13px;">
                    <i class="bi bi-shield-x text-danger fs-5"></i>
                    <span>If it says "App not installed" or "Blocked by Play Protect":</span>
                </div>
                <ul class="mb-0 ps-3" style="line-height: 1.5; font-size: 12px;">
                    <li>Tap <strong>"More details"</strong> (down arrow 🔽) on the warning popup.</li>
                    <li>Tap <strong>"Install anyway"</strong> (or <em>Install anyway / Unsafe</em>).</li>
                    <li>If it closed, open your phone's <strong>Files / Downloads</strong> app, tap the APK, and choose <strong>"Install anyway"</strong>.</li>
                </ul>
            </div>
        </div>

        <!-- Return to Student Hub -->
        <div class="mt-3 pt-2 text-center">
            <a href="/student" class="small text-decoration-none fw-semibold text-secondary">
                <i class="bi bi-arrow-left"></i> Return to Student Portal
            </a>
        </div>
    </div>

    <!-- Automatic Trigger on Load -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var apkUrl = "{{ $directApkUrl }}";
            
            setTimeout(function() {
                var link = document.createElement('a');
                link.href = apkUrl;
                link.setAttribute('download', 'TPC-BSIS-Attendance.apk');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }, 300);
        });
    </script>
</body>
</html>
