@echo off
title BSIS Cloudflare Tunnel Launcher
echo ========================================================
echo   BSIS Dynamic QR Code Attendance Monitoring System
echo   Launching Cloudflare Public Tunnel & Laravel Server
echo ========================================================
echo.

echo Starting Laravel Application Server on http://127.0.0.1:8000 ...
start /B php artisan serve --host=127.0.0.1 --port=8000 > nul 2>&1

timeout /t 2 > nul

echo.
echo Starting Cloudflare Permanent Tunnel for https://tpc-bsis.online ...
echo ========================================================
if exist "%~dp0cloudflared.exe" (
    "%~dp0cloudflared.exe" tunnel --protocol http2 --config "%~dp0cloudflared-config.yml" run
) else (
    cloudflared.exe tunnel --protocol http2 --config "%~dp0cloudflared-config.yml" run
)
pause
