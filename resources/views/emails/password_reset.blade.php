<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>BSIS Password Reset — Talibon Polytechnic College</title>
    <style>
        body { font-family: 'Inter', Arial, sans-serif; background-color: #F5F9FC; color: #17212B; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #FFFFFF; border-radius: 12px; border: 1px solid #DCE7ED; padding: 30px; }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #063B5C; }
        .title { color: #063B5C; font-size: 20px; font-weight: bold; margin-top: 15px; }
        .subtitle { color: #35C4E8; font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .content { margin: 25px 0; font-size: 15px; line-height: 1.6; }
        .token-box { background-color: #DDF7FC; color: #063B5C; padding: 12px; border-radius: 6px; font-family: monospace; word-break: break-all; margin: 15px 0; font-weight: bold; text-align: center; font-size: 18px; }
        .footer { text-align: center; font-size: 12px; color: #6B7A86; border-top: 1px solid #EEF4F8; padding-top: 20px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="subtitle">Bachelor of Science in Information Systems</div>
            <div class="title">Talibon Polytechnic College</div>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>You requested a password reset for your BSIS Attendance System account.</p>
            <p>Your password reset code is:</p>
            <div class="token-box">{{ $token }}</div>
            <p style="font-size: 13px; color: #6B7A86;">This reset token will expire in 60 minutes. If you did not request a password reset, please ignore this message.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} BSIS Department — Talibon Polytechnic College. All rights reserved.
        </div>
    </div>
</body>
</html>
