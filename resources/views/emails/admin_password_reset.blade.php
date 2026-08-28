<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Password Reset by Administrator — Talibon Polytechnic College</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #F5F9FC; color: #0F172A; margin: 0; padding: 24px; }
        .container { max-width: 580px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 32px 28px; box-shadow: 0 4px 16px rgba(6, 59, 92, 0.06); }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #063B5C; }
        .title { color: #063B5C; font-size: 20px; font-weight: 800; margin-top: 10px; }
        .subtitle { color: #0284C7; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; }
        .content { margin: 24px 0; font-size: 14.5px; line-height: 1.6; color: #334155; }
        .password-card { background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 12px; padding: 18px; text-align: center; margin: 20px 0; }
        .password-code { font-family: Consolas, 'Courier New', monospace; font-size: 22px; font-weight: 800; letter-spacing: 1.5px; color: #0369A1; word-break: break-all; margin: 8px 0; }
        .notice-box { background-color: #F8FAFC; border-left: 4px solid #063B5C; padding: 12px 16px; font-size: 13px; color: #475569; border-radius: 0 8px 8px 0; margin-top: 20px; }
        .footer { text-align: center; font-size: 11.5px; color: #94A3B8; border-top: 1px solid #EEF2F6; padding-top: 20px; margin-top: 28px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="subtitle">Bachelor of Science in Information Systems</div>
            <div class="title">Talibon Polytechnic College</div>
        </div>
        <div class="content">
            <p style="margin-top: 0;">Hello <strong>{{ $user->full_name }}</strong>,</p>
            <p>Your BSIS Attendance System account password was directly reset by an institutional system administrator{{ $admin ? ' (' . $admin->full_name . ')' : '' }}.</p>
            
            <div class="password-card">
                <div style="font-size: 12px; font-weight: 700; color: #0284C7; text-transform: uppercase; letter-spacing: 0.5px;">Your New Temporary Password</div>
                <div class="password-code">{{ $newPassword }}</div>
                <div style="font-size: 12px; color: #64748B;">Please use this password to sign in to your registered device.</div>
            </div>

            <div class="notice-box">
                <strong style="color: #0F172A;">🔒 Important Device & Access Notes:</strong>
                <ul style="margin: 6px 0 0 0; padding-left: 18px;">
                    <li><strong>Device Binding:</strong> Your registered mobile smartphone hardware binding remains safely preserved. You can immediately log in on your bound phone.</li>
                    <li>If you did not request or expect this change, please immediately coordinate with the BSIS Department Office.</li>
                </ul>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} BSIS Department &bull; Talibon Polytechnic College<br>
            Secure Dynamic QR & GPS Attendance Monitoring System
        </div>
    </div>
</body>
</html>
