<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSIS Password Reset — Talibon Polytechnic College</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #F5F9FC; color: #0F172A; margin: 0; padding: 24px; }
        .container { max-width: 580px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; padding: 32px 28px; box-shadow: 0 4px 16px rgba(6, 59, 92, 0.06); }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #063B5C; }
        .title { color: #063B5C; font-size: 20px; font-weight: 800; margin-top: 10px; letter-spacing: -0.3px; }
        .subtitle { color: #0284C7; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; }
        .content { margin: 24px 0; font-size: 14.5px; line-height: 1.6; color: #334155; }
        .token-card { background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 12px; padding: 18px; text-align: center; margin: 20px 0; }
        .token-code { font-family: Consolas, 'Courier New', monospace; font-size: 20px; font-weight: 800; letter-spacing: 2px; color: #0369A1; word-break: break-all; margin: 8px 0; }
        .btn-action { display: inline-block; background-color: #063B5C; color: #FFFFFF !important; text-decoration: none; font-weight: 700; font-size: 14px; padding: 12px 26px; border-radius: 10px; margin-top: 12px; }
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
            <p style="margin-top: 0;">Hello <strong>{{ $user ? $user->full_name : 'User' }}</strong>,</p>
            <p>We received a request to reset the password for your BSIS Attendance System account (<code>{{ $user ? $user->email : '' }}</code>).</p>
            
            <div class="token-card">
                <div style="font-size: 12px; font-weight: 700; color: #0284C7; text-transform: uppercase; letter-spacing: 0.5px;">Your Password Reset Token</div>
                <div class="token-code">{{ $token }}</div>
                <div style="font-size: 12px; color: #64748B;">Copy and paste this code in the TPC Mobile App or Web Portal.</div>
                @if(!empty($resetUrl))
                <div style="margin-top: 14px;">
                    <a href="{{ $resetUrl }}" class="btn-action" target="_blank">Reset Password on Web Portal &rarr;</a>
                </div>
                @endif
            </div>

            <div class="notice-box">
                <strong style="color: #0F172A;">🔒 Security Details:</strong>
                <ul style="margin: 6px 0 0 0; padding-left: 18px;">
                    <li>This token is valid for <strong>60 minutes</strong> and can only be used once.</li>
                    @if(isset($user) && $user->role === 'student')
                    <li><strong>Device Security:</strong> Your registered mobile phone hardware binding is safely preserved. You can sign in immediately on your bound phone once your password is reset.</li>
                    @endif
                    <li>If you did not request this reset, please ignore this email or notify your BSIS Administrator.</li>
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
