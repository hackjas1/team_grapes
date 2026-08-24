<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSIS Event Attendance System — Administrator & Staff Dashboard</title>
    
    <link rel="icon" type="image/png" href="/images/bsis-logo.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Leaflet.js Interactive OpenStreetMap CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- BSIS Institutional Color Theme CSS -->
    <link rel="stylesheet" href="/css/bsis-theme.css?v={{ time() }}">

    <!-- Prevent Unauthenticated Sidebar & Dashboard Flash and Restore Collapsed State -->
    <script>
        (function() {
            var token = localStorage.getItem('bsis_auth_token') || sessionStorage.getItem('bsis_auth_token');
            var hash = window.location.hash;
            if (!token || hash === '#login') {
                document.documentElement.classList.add('in-login-view');
            }
            if (localStorage.getItem('admin_sidebar_collapsed') === 'true' && window.innerWidth >= 992) {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();
    </script>

    <style>
        html { scroll-behavior: smooth; overflow-y: auto; scrollbar-gutter: stable; }
        body { background-color: var(--color-background); min-height: 100vh; overflow-x: hidden; }
        


        #admin-sidebar-nav {
            overflow-y: auto;
            flex-grow: 1;
            flex-shrink: 1;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        #admin-sidebar-nav::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .sidebar-footer {
            flex-shrink: 0;
            margin-top: auto;
            padding: 8px 0;
            padding-bottom: max(14px, env(safe-area-inset-bottom, 14px)) !important;
            border-top: 1px solid rgba(255,255,255,0.08);
            background: transparent;
            width: 100%;
        }

        .sidebar-profile-popup {
            position: fixed;
            bottom: 70px;
            left: 12px;
            width: 236px;
            background: #142434;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.08);
            padding: 8px;
            z-index: 99999 !important;
            animation: popupSlideUp 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes popupSlideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-popup-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 12px;
            color: #E2E8F0;
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.86rem;
            font-weight: 500;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .profile-popup-item:hover,
        .profile-popup-item:focus {
            background: rgba(53, 196, 232, 0.14);
            color: #FFFFFF;
        }

        .profile-popup-item i {
            font-size: 1.05rem;
            color: #94A3B8;
            width: 18px;
            text-align: center;
        }

        .profile-popup-item:hover i {
            color: var(--color-accent);
        }

        .profile-popup-item.logout-item {
            color: #F87171 !important;
            font-weight: 600;
        }

        .profile-popup-item.logout-item i {
            color: #F87171 !important;
        }

        .profile-popup-item.logout-item:hover,
        .profile-popup-item.logout-item:focus {
            background: rgba(239, 68, 68, 0.18) !important;
            color: #EF4444 !important;
        }

        /* Modern Target Year Level Multi-Select Pills */
        .target-year-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 15px;
            font-size: 0.84rem;
            font-weight: 600;
            border-radius: 50px;
            border: 1.5px solid #CBD5E1;
            background-color: #FFFFFF;
            color: #475569;
            transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            user-select: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .target-year-pill:hover {
            border-color: #0284C7;
            color: #0284C7;
            background-color: #F0F9FF;
            transform: translateY(-1px);
        }

        .target-year-pill.active {
            background: linear-gradient(135deg, #063B5C 0%, #0A4D78 100%);
            border-color: #063B5C;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(6, 59, 92, 0.25);
        }

        .target-year-pill.active i {
            color: #35C4E8;
        }

        .target-year-pill.pill-all-btn.active {
            background: linear-gradient(135deg, #0284C7 0%, #0369A1 100%);
            border-color: #0284C7;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.28);
        }

        .target-year-pill.pill-all-btn.active i {
            color: #FFD700;
        }
        
        .admin-content { 
            margin-left: 260px; 
            padding: 24px; 
            transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
            min-height: 100vh;
        }

        .admin-sidebar { 
            width: 260px; 
            height: 100vh;
            height: 100dvh;
            max-height: 100dvh;
            background-color: var(--color-primary); 
            color: #FFFFFF; 
            position: fixed; 
            top: 0; 
            bottom: 0;
            left: 0; 
            z-index: 1050; 
            box-shadow: 2px 0 10px rgba(0,0,0,0.1); 
            overflow: hidden; 
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        #admin-sidebar-nav {
            flex: 1 1 auto;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            min-height: 0;
        }

        #admin-sidebar-nav::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .sidebar-brand { 
            height: 72px;
            min-height: 72px;
            max-height: 72px;
            padding: 0;
            box-sizing: border-box;
            overflow: hidden;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1); 
            flex-shrink: 0;
            width: 100%;
        }

        .sidebar-brand-wrapper {
            display: flex;
            align-items: center;
            width: 100%;
            overflow: hidden;
            flex-grow: 1;
            min-width: 0;
        }

        .sidebar-brand-logo-slot {
            width: 72px;
            min-width: 72px;
            max-width: 72px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-brand-logo {
            height: 38px;
            width: 38px;
            min-width: 38px;
            max-width: 38px;
            object-fit: contain;
            flex-shrink: 0;
            display: block;
        }

        .sidebar-brand-text {
            flex-grow: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            padding-right: 12px;
        }

        .sidebar-collapse-container {
            height: 46px;
            min-height: 46px;
            max-height: 46px;
            box-sizing: border-box;
            overflow: hidden;
            display: flex;
            align-items: center;
            padding: 0;
            flex-shrink: 0;
            width: 100%;
        }

        .sidebar-collapse-slot {
            width: 72px;
            min-width: 72px;
            max-width: 72px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-collapse-trigger-btn {
            color: rgba(255, 255, 255, 0.75);
            transition: color 0.2s ease, transform 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: transparent;
            border: none;
            cursor: pointer;
        }

        .sidebar-collapse-trigger-btn:hover {
            color: var(--color-accent) !important;
            transform: scale(1.1);
        }

        .sidebar-nav-link { 
            color: #DCE7ED; 
            padding: 0; 
            height: 44px;
            min-height: 44px;
            max-height: 44px;
            box-sizing: border-box;
            display: flex; 
            align-items: center; 
            font-weight: 500; 
            font-size: 0.90rem; 
            text-decoration: none; 
            box-shadow: inset 4px 0 0 transparent; 
            transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease; 
            white-space: nowrap;
            overflow: hidden;
            width: 100%;
        }

        .sidebar-nav-link i { 
            width: 72px; 
            min-width: 72px; 
            max-width: 72px; 
            height: 100%;
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.25rem; 
            color: var(--color-accent); 
            flex-shrink: 0; 
            margin: 0; 
            padding: 0; 
        }

        .sidebar-nav-link .sidebar-nav-text {
            flex-grow: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            padding-right: 14px;
        }

        .sidebar-nav-link:hover, .sidebar-nav-link.active { 
            background: rgba(53, 196, 232, 0.12); 
            color: #FFFFFF; 
            box-shadow: inset 4px 0 0 var(--color-accent); 
        }

        .sidebar-profile-card {
            padding: 0;
            border: none;
            background: transparent;
            display: flex;
            align-items: center;
            width: 100%;
            height: 48px;
            text-decoration: none;
        }

        .sidebar-profile-slot {
            width: 72px;
            min-width: 72px;
            max-width: 72px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar-avatar-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            min-width: 38px;
            max-width: 38px;
            border-radius: 50%;
            background: var(--color-accent);
            color: var(--color-primary);
            font-weight: 700;
            font-size: 1.05rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .sidebar-profile-card:hover .sidebar-avatar-circle {
            transform: scale(1.08);
            box-shadow: 0 0 16px rgba(53, 196, 232, 0.7);
        }

        /* Desktop Collapsed Sidebar (Icon-Rail Mode: 72px) */
        @media (min-width: 992px) {
            html.sidebar-collapsed body .admin-sidebar,
            body.sidebar-collapsed .admin-sidebar,
            html.sidebar-collapsed .admin-sidebar,
            body.sidebar-collapsed.admin-sidebar {
                width: 72px !important;
            }

            html.sidebar-collapsed body .admin-content,
            body.sidebar-collapsed .admin-content,
            html.sidebar-collapsed .admin-content,
            body.sidebar-collapsed.admin-content {
                margin-left: 72px !important;
            }

            html.sidebar-collapsed body .sidebar-brand-text,
            body.sidebar-collapsed .sidebar-brand-text,
            html.sidebar-collapsed body .sidebar-nav-text,
            body.sidebar-collapsed .sidebar-nav-text,
            html.sidebar-collapsed body .admin-only-section-title,
            body.sidebar-collapsed .admin-only-section-title,
            html.sidebar-collapsed body .sidebar-footer-text,
            body.sidebar-collapsed .sidebar-footer-text {
                display: none !important;
                width: 0 !important;
                opacity: 0 !important;
                visibility: hidden !important;
            }

            html.sidebar-collapsed body .sidebar-profile-popup,
            body.sidebar-collapsed .sidebar-profile-popup {
                position: fixed !important;
                left: 80px !important;
                bottom: 14px !important;
                width: 240px !important;
                z-index: 99999 !important;
            }
        }

        .table-success-highlight { animation: highlightFlash 1.5s ease-out; }
        @keyframes highlightFlash { 0% { background-color: #d1e7dd; } 100% { background-color: transparent; } }
        .qr-timer-bar { height: 8px; background: var(--color-accent); transition: width 1s linear; border-radius: 4px; }
        #create-event-map { z-index: 1; }

        /* Default QR Display Elements */
        #qr-canvas-wrapper canvas,
        #qr-canvas-wrapper img,
        #qr-code-image {
            width: 280px;
            height: 280px;
            max-width: 100%;
            border-radius: 12px;
            object-fit: contain;
        }

        /* Fullscreen QR Presentation Mode (Projector & High-Visibility Kiosk) */
        #view-qr-display:fullscreen,
        #view-qr-display:-webkit-full-screen,
        #view-qr-display.is-fullscreen,
        body.qr-fullscreen-active #view-qr-display {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            height: 100dvh !important;
            max-width: 100vw !important;
            z-index: 999999 !important;
            background: radial-gradient(circle at center, #0B253D 0%, #031320 100%) !important;
            color: #FFFFFF !important;
            padding: 18px 20px 28px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            -webkit-overflow-scrolling: touch !important;
            display: block !important;
            box-sizing: border-box !important;
        }

        body.qr-fullscreen-active .admin-sidebar,
        body.qr-fullscreen-active .admin-mobile-header {
            display: none !important;
        }

        body.qr-fullscreen-active .admin-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }

        #view-qr-display:fullscreen .qr-view-container,
        #view-qr-display:-webkit-full-screen .qr-view-container,
        #view-qr-display.is-fullscreen .qr-view-container,
        body.qr-fullscreen-active #view-qr-display .qr-view-container {
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
            align-items: center !important;
            margin: 0 auto !important;
            max-width: 680px !important;
            width: 100% !important;
            padding: 0 !important;
        }

        #view-qr-display:fullscreen .qr-top-actions,
        #view-qr-display:-webkit-full-screen .qr-top-actions,
        #view-qr-display.is-fullscreen .qr-top-actions,
        body.qr-fullscreen-active #view-qr-display .qr-top-actions {
            width: 100% !important;
            max-width: 580px !important;
            margin-bottom: 10px !important;
            position: relative !important;
            z-index: 10 !important;
        }

        #view-qr-display:fullscreen #qr-display-title,
        #view-qr-display:-webkit-full-screen #qr-display-title,
        #view-qr-display.is-fullscreen #qr-display-title,
        body.qr-fullscreen-active #view-qr-display #qr-display-title {
            color: #FFFFFF !important;
            font-size: 1.85rem !important;
            letter-spacing: -0.5px;
            text-shadow: 0 3px 14px rgba(0,0,0,0.7) !important;
            margin-top: 2px !important;
            margin-bottom: 2px !important;
        }

        #view-qr-display:fullscreen #qr-display-venue,
        #view-qr-display:-webkit-full-screen #qr-display-venue,
        #view-qr-display.is-fullscreen #qr-display-venue,
        body.qr-fullscreen-active #view-qr-display #qr-display-venue {
            color: #94A3B8 !important;
            font-size: 1.05rem !important;
            margin-bottom: 8px !important;
        }

        #view-qr-display:fullscreen #qr-window-message,
        #view-qr-display:-webkit-full-screen #qr-window-message,
        #view-qr-display.is-fullscreen #qr-window-message,
        body.qr-fullscreen-active #view-qr-display #qr-window-message {
            margin-bottom: 10px !important;
            font-size: 0.86rem !important;
            max-width: 520px !important;
        }

        #view-qr-display:fullscreen .qr-card-wrapper,
        #view-qr-display:-webkit-full-screen .qr-card-wrapper,
        #view-qr-display.is-fullscreen .qr-card-wrapper,
        body.qr-fullscreen-active #view-qr-display .qr-card-wrapper {
            background: #FFFFFF !important;
            border: 3px solid rgba(53, 196, 232, 0.6) !important;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.85) !important;
            border-radius: 20px !important;
            padding: 18px 24px !important;
            max-width: min(92vw, 520px) !important;
            width: 100% !important;
            margin-bottom: 12px !important;
        }

        #view-qr-display:fullscreen #qr-canvas-wrapper canvas,
        #view-qr-display:fullscreen #qr-canvas-wrapper img,
        #view-qr-display:fullscreen #qr-code-image,
        #view-qr-display:-webkit-full-screen #qr-canvas-wrapper canvas,
        #view-qr-display:-webkit-full-screen #qr-canvas-wrapper img,
        #view-qr-display:-webkit-full-screen #qr-code-image,
        #view-qr-display.is-fullscreen #qr-canvas-wrapper canvas,
        #view-qr-display.is-fullscreen #qr-canvas-wrapper img,
        #view-qr-display.is-fullscreen #qr-code-image,
        body.qr-fullscreen-active #view-qr-display #qr-canvas-wrapper canvas,
        body.qr-fullscreen-active #view-qr-display #qr-canvas-wrapper img,
        body.qr-fullscreen-active #view-qr-display #qr-code-image {
            width: 380px !important;
            height: 380px !important;
            max-width: 80vw !important;
            max-height: 44vh !important;
            aspect-ratio: 1 / 1 !important;
            border-radius: 10px !important;
        }

        #view-qr-display:fullscreen #qr-raw-token-text,
        #view-qr-display:-webkit-full-screen #qr-raw-token-text,
        #view-qr-display.is-fullscreen #qr-raw-token-text,
        body.qr-fullscreen-active #view-qr-display #qr-raw-token-text {
            display: none !important;
        }

        #view-qr-display:fullscreen .qr-timer-bar,
        #view-qr-display:-webkit-full-screen .qr-timer-bar,
        #view-qr-display.is-fullscreen .qr-timer-bar,
        body.qr-fullscreen-active #view-qr-display .qr-timer-bar {
            height: 8px !important;
            border-radius: 4px !important;
        }

        #view-qr-display:fullscreen .qr-live-stats-row,
        #view-qr-display:-webkit-full-screen .qr-live-stats-row,
        #view-qr-display.is-fullscreen .qr-live-stats-row,
        body.qr-fullscreen-active #view-qr-display .qr-live-stats-row {
            max-width: 520px !important;
            width: 100% !important;
            margin-bottom: 12px !important;
        }

        #view-qr-display:fullscreen .qr-live-stats-row .bsis-card,
        #view-qr-display:-webkit-full-screen .qr-live-stats-row .bsis-card,
        #view-qr-display.is-fullscreen .qr-live-stats-row .bsis-card,
        body.qr-fullscreen-active #view-qr-display .qr-live-stats-row .bsis-card {
            background: rgba(255, 255, 255, 0.12) !important;
            backdrop-filter: blur(12px) !important;
            border: 1px solid rgba(255, 255, 255, 0.22) !important;
            border-radius: 14px !important;
            padding: 8px 12px !important;
        }

        #view-qr-display:fullscreen .qr-live-stats-row .bsis-card span,
        #view-qr-display:-webkit-full-screen .qr-live-stats-row .bsis-card span,
        #view-qr-display.is-fullscreen .qr-live-stats-row .bsis-card span,
        body.qr-fullscreen-active #view-qr-display .qr-live-stats-row .bsis-card span {
            color: #94A3B8 !important;
            font-size: 0.75rem !important;
        }

        #view-qr-display:fullscreen .qr-live-stats-row .bsis-card h4,
        #view-qr-display:-webkit-full-screen .qr-live-stats-row .bsis-card h4,
        #view-qr-display.is-fullscreen .qr-live-stats-row .bsis-card h4,
        body.qr-fullscreen-active #view-qr-display .qr-live-stats-row .bsis-card h4 {
            color: #FFFFFF !important;
            font-size: 1.5rem !important;
        }

        /* Mobile Top Navigation Header (< 992px) */
        .admin-mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 58px;
            background-color: var(--color-primary);
            color: #FFFFFF;
            z-index: 1040;
            padding: 0 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            align-items: center;
            justify-content: space-between;
        }

        /* Mobile Sidebar Backdrop Overlay */
        .admin-sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(4, 44, 70, 0.65);
            backdrop-filter: blur(3px);
            z-index: 1045;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .admin-sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }

        /* Responsive Breakpoints */
        @media (max-width: 991.98px) {
            body.mobile-sidebar-open {
                overflow: hidden !important;
                touch-action: none !important;
                overscroll-behavior: none !important;
            }

            .admin-mobile-header {
                display: flex;
            }

            .admin-sidebar {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                left: 0 !important;
                height: 100% !important;
                height: 100dvh !important;
                max-height: 100dvh !important;
                width: 285px !important;
                max-width: 85vw !important;
                transform: translateX(-100%);
                z-index: 1050;
                overflow: hidden !important;
                overscroll-behavior: contain;
                box-shadow: 4px 0 25px rgba(0, 0, 0, 0.35);
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-content {
                margin-left: 0 !important;
                padding: 14px 10px;
                padding-top: 72px;
            }
        }

        /* Mobile Smooth Table Dragging & Horizontal Scrolling */
        .table-responsive,
        .bsis-table-responsive {
            display: block;
            width: 100%;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            position: relative;
        }

        .table-responsive::-webkit-scrollbar,
        .bsis-table-responsive::-webkit-scrollbar {
            height: 6px;
        }

        .table-responsive::-webkit-scrollbar-thumb,
        .bsis-table-responsive::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 3px;
        }

        .bsis-table {
            width: 100%;
            margin-bottom: 0;
            min-width: 720px; /* Ensures all columns retain readable structure and trigger smooth horizontal touch scrolling */
        }        /* Login Screen Isolation & Executive Styling */
        html.in-login-view,
        html.in-login-view body,
        body.in-login-view {
            background-color: #EEF4F8 !important;
        }

        html.in-login-view .admin-sidebar,
        body.in-login-view .admin-sidebar,
        html.in-login-view .admin-mobile-header,
        body.in-login-view .admin-mobile-header,
        html.in-login-view .admin-sidebar-backdrop,
        body.in-login-view .admin-sidebar-backdrop,
        html.in-login-view #admin-live-event-banner,
        body.in-login-view #admin-live-event-banner {
            display: none !important;
        }

        html.in-login-view .admin-content,
        body.in-login-view .admin-content {
            margin-left: 0 !important;
            padding: 0 !important;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #F0F4F8 0%, #E2E8F0 100%) !important;
        }

        .admin-login-wrapper {
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .admin-login-card-container {
            width: 100%;
            max-width: 1020px;
            background: #FFFFFF;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(6, 59, 92, 0.16), 0 2px 6px rgba(0,0,0,0.04);
            border: 1px solid #E2E8F0;
        }

        .admin-login-hero-side {
            background: linear-gradient(145deg, #063B5C 0%, #032134 100%);
            color: #FFFFFF;
            padding: 44px 38px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .admin-login-hero-side::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(53, 196, 232, 0.22) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .admin-login-feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.09);
            margin-bottom: 10px;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .admin-login-feature-item:hover {
            transform: translateX(4px);
            background: rgba(255, 255, 255, 0.12);
        }

        .admin-login-feature-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(53, 196, 232, 0.2);
            color: #35C4E8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .admin-login-form-side {
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF;
        }

        .admin-input-group {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .admin-input-group i.input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.2s ease;
            z-index: 2;
        }

        .admin-input-group input {
            width: 100%;
            padding-left: 44px !important;
            padding-right: 48px !important;
            height: 48px;
            border-radius: 10px;
            border: 1.5px solid #E2E8F0;
            font-size: 0.92rem;
            transition: all 0.2s ease;
            position: relative;
            z-index: 1;
        }

        .admin-input-group input:focus {
            border-color: #063B5C;
            box-shadow: 0 0 0 4px rgba(6, 59, 92, 0.1);
        }

        .admin-input-group input:focus ~ i.input-icon {
            color: #063B5C;
        }

        .admin-input-group .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            z-index: 3;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            transition: color 0.2s ease;
        }

        .admin-input-group .password-toggle-btn:hover {
            color: #063B5C;
        }

        .btn-admin-submit {
            background: linear-gradient(135deg, #063B5C 0%, #042C46 100%);
            color: #FFFFFF;
            font-weight: 700;
            font-size: 0.96rem;
            height: 48px;
            border-radius: 10px;
            border: none;
            box-shadow: 0 6px 18px rgba(6, 59, 92, 0.28);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-admin-submit:hover {
            background: linear-gradient(135deg, #084972 0%, #063B5C 100%);
            color: #FFFFFF;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(6, 59, 92, 0.38);
        }

        .admin-login-footer {
            margin-top: 26px;
            padding-top: 16px;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            font-size: 0.8rem;
            color: #64748B;
        }

        @media (max-width: 991.98px) {
            .admin-login-wrapper {
                padding: 24px 14px;
                align-items: center;
                min-height: 100vh;
            }
            .admin-login-card-container {
                max-width: 460px;
                border-radius: 20px;
                box-shadow: 0 12px 40px rgba(6, 59, 92, 0.12);
            }
            .admin-login-form-side {
                padding: 32px 24px 28px 24px;
            }
        }
    </style>
</head>
<body>

    <!-- Printable Official Report Container -->
    <div id="printable-fines-area" class="print-only"></div>

    <!-- Mobile Top Navigation Header (< 992px) -->
    <header class="admin-mobile-header d-flex d-lg-none align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-link text-white p-0 fs-4 me-1" id="btn-admin-mobile-toggle" onclick="AdminApp.toggleMobileSidebar()" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <a href="#overview" class="d-flex align-items-center gap-2 text-decoration-none text-white" title="Go to Overview" style="cursor: pointer;">
                <img src="/images/bsis-logo.png" alt="BSIS Logo" style="height: 34px;">
                <div>
                    <div class="fw-bold text-white mb-0" style="line-height: 1.15; font-size: 0.80rem;">Bachelor of Science in Information Systems</div>
                    <div style="font-size: 0.62rem; color: var(--color-accent); line-height: 1.1;">Talibon Polytechnic College</div>
                </div>
            </a>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="bsis-badge bsis-badge-info py-1 px-2" style="font-size: 0.72rem;" id="admin-mobile-role-badge">ADMIN</span>
        </div>
    </header>

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="admin-sidebar-backdrop" class="admin-sidebar-backdrop" onclick="AdminApp.closeMobileSidebar()"></div>

    <!-- Sidebar Navigation (Desktop Permanent / Mobile Offcanvas Drawer) -->
    <aside class="admin-sidebar d-flex flex-column">
        <div class="sidebar-brand d-flex align-items-center justify-content-between">
            <a href="#overview" class="sidebar-brand-wrapper d-flex align-items-center overflow-hidden flex-grow-1 text-decoration-none" title="Go to Overview" style="cursor: pointer;">
                <div class="sidebar-brand-logo-slot d-flex align-items-center justify-content-center flex-shrink-0">
                    <img src="/images/bsis-logo.png" alt="BSIS Logo" class="sidebar-brand-logo">
                </div>
                <div class="sidebar-brand-text flex-grow-1" style="min-width: 0;">
                    <div class="fw-bold" style="font-size: 0.78rem; line-height: 1.22; color: #FFFFFF; white-space: normal;">Bachelor of Science in<br>Information Systems</div>
                    <div style="font-size: 0.65rem; color: var(--color-accent); letter-spacing: 0.2px; margin-top: 2px;">Talibon Polytechnic College</div>
                </div>
            </a>
            <!-- Mobile Close Button -->
            <button type="button" class="btn btn-link text-white d-lg-none p-1 me-2" onclick="AdminApp.closeMobileSidebar()" aria-label="Close menu">
                <i class="bi bi-x-lg fs-5"></i>
            </button>
        </div>

        <!-- Static Steady Collapse Header (Fixed outside scrolling navigation) -->
        <div class="d-none d-lg-flex align-items-center sidebar-collapse-container border-bottom border-white border-opacity-10">
            <div class="sidebar-collapse-slot d-flex align-items-center justify-content-center flex-shrink-0">
                <button type="button" class="btn btn-link text-white p-0 sidebar-collapse-trigger-btn d-flex align-items-center justify-content-center" onclick="AdminApp.toggleSidebarCollapse()" title="Toggle Sidebar Width" aria-label="Toggle Sidebar width">
                    <i class="bi bi-layout-sidebar-inset fs-5"></i>
                </button>
            </div>
        </div>

        <nav class="flex-grow-1 py-2" id="admin-sidebar-nav">
            <a href="#overview" class="sidebar-nav-link active" title="Overview"><i class="bi bi-speedometer2"></i> <span class="sidebar-nav-text">Overview</span></a>
            <a href="#events" class="sidebar-nav-link" title="Event Management"><i class="bi bi-calendar-event"></i> <span class="sidebar-nav-text">Event Management</span></a>
            <a href="#live-monitor" class="sidebar-nav-link" title="Live Attendance Feed"><i class="bi bi-broadcast"></i> <span class="sidebar-nav-text">Live Attendance Feed</span></a>
            <a href="#fines" class="sidebar-nav-link" title="Fines Tracking"><i class="bi bi-receipt"></i> <span class="sidebar-nav-text">Fines Tracking</span></a>
            <a href="#reports" class="sidebar-nav-link" title="Reports & Export"><i class="bi bi-file-earmark-bar-graph"></i> <span class="sidebar-nav-text">Reports & Export</span></a>
            <a href="#sync-queue" class="sidebar-nav-link" title="Offline Sync Queue"><i class="bi bi-cloud-arrow-up"></i> <span class="sidebar-nav-text">Offline Sync Queue</span></a>
            
            <!-- Administrator Only Modules -->
            <div class="admin-only-section mt-2 pt-2 border-top border-secondary" data-admin-only="true">
                <div class="px-3 pb-1 small text-uppercase fw-bold admin-only-section-title" style="font-size: 0.65rem; color: var(--color-accent); letter-spacing: 0.5px;">Administration</div>
                <a href="#users" class="sidebar-nav-link admin-only-nav" data-admin-only="true" title="Users & Students"><i class="bi bi-people"></i> <span class="sidebar-nav-text">Users & Students</span></a>
                <a href="#device-resets" class="sidebar-nav-link admin-only-nav" data-admin-only="true" title="Device Reset Logs"><i class="bi bi-shield-check"></i> <span class="sidebar-nav-text">Device Reset Logs</span></a>
                <a href="#audit-logs" class="sidebar-nav-link admin-only-nav" data-admin-only="true" title="System Audit Logs"><i class="bi bi-journal-text"></i> <span class="sidebar-nav-text">System Audit Logs</span></a>
                <a href="#backups" class="sidebar-nav-link admin-only-nav" data-admin-only="true" title="Database Backups"><i class="bi bi-database-gear"></i> <span class="sidebar-nav-text">Database Backups</span></a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="position-relative">
                <button type="button" class="sidebar-profile-card w-100 btn text-start" id="adminProfileTrigger" onclick="AdminApp.toggleProfilePopup(event)" title="Click to view options and Sign Out">
                    <div class="sidebar-profile-slot d-flex align-items-center justify-content-center flex-shrink-0">
                        <div class="sidebar-avatar-circle" id="admin-avatar-initial">A</div>
                    </div>
                    <div class="sidebar-footer-text flex-grow-1 text-truncate pe-2" style="min-width: 0;">
                        <div class="fw-bold text-white text-truncate" style="font-size: 0.88rem; line-height: 1.25;" id="admin-name-display">Admin Account</div>
                    </div>
                    <i class="bi bi-chevron-up sidebar-footer-text opacity-75 ms-auto pe-3" style="font-size: 0.82rem; color: var(--color-accent);"></i>
                </button>

                <!-- Floating Pop-up Menu -->
                <div id="admin-profile-popup" class="sidebar-profile-popup shadow-lg d-none">
                    <div class="px-3 py-2 border-bottom border-white border-opacity-10 mb-2">
                        <div class="fw-bold text-white text-truncate" style="font-size: 0.90rem; line-height: 1.25;" id="admin-menu-name">Admin Account</div>
                        <div class="d-flex align-items-center gap-2 mt-1 mb-1">
                            <span class="bsis-badge bsis-badge-danger" style="font-size: 0.65rem; padding: 2px 8px; letter-spacing: 0.5px;" id="admin-menu-role">ADMINISTRATOR</span>
                        </div>
                        <div class="text-white-50 text-truncate" style="font-size: 0.74rem;" id="admin-menu-email">admin@tpc-bsis.online</div>
                    </div>

                    <a href="/student" target="_blank" rel="noopener noreferrer" class="profile-popup-item" onclick="AdminApp.closeProfilePopup()">
                        <i class="bi bi-globe"></i>
                        <span class="flex-grow-1">Homepage</span>
                    </a>
                    <a href="https://www.tpc.edu.ph/academics/bachelor-of-science-in-information-systems" target="_blank" rel="noopener noreferrer" class="profile-popup-item" onclick="AdminApp.closeProfilePopup()">
                        <i class="bi bi-book"></i>
                        <span class="flex-grow-1">BSIS Academic Site</span>
                        <i class="bi bi-arrow-up-right small opacity-50 ms-auto"></i>
                    </a>
                    <a href="#audit-logs" class="profile-popup-item admin-only-nav" data-admin-only="true" onclick="AdminApp.closeProfilePopup()">
                        <i class="bi bi-shield-lock"></i>
                        <span class="flex-grow-1">System Audit Logs</span>
                    </a>
                    <button type="button" class="profile-popup-item admin-only-nav w-100 text-start" data-admin-only="true" data-bs-toggle="modal" data-bs-target="#modal-qr-settings" onclick="AdminApp.closeProfilePopup()">
                        <i class="bi bi-clock-history"></i>
                        <span class="flex-grow-1">QR Interval Settings</span>
                    </button>

                    <div class="my-1 border-top border-white border-opacity-10"></div>

                    <button type="button" class="profile-popup-item logout-item w-100 text-start" onclick="AdminApp.logout()">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="flex-grow-1">Log out</span>
                    </button>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Dynamic Content Wrapper -->
    <main class="admin-content">
        <!-- Live Active Event Global Broadcast Banner (Sticky) -->
        <div id="admin-live-event-banner" class="bsis-live-event-banner d-none mb-3">
            <div class="d-flex align-items-center gap-3">
                <span class="bsis-live-badge"><span class="bsis-pulse-dot" style="background:#10B981;"></span> LIVE EVENT</span>
                <div>
                    <div class="fw-bold text-white fs-6" id="live-banner-title">Loading Active Event...</div>
                    <div class="small text-light opacity-75" id="live-banner-stats">0 Verified Scans recorded &bull; Active Now</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap w-100 w-md-auto">
                <button onclick="AdminApp.openActiveQrScreen()" class="btn btn-sm btn-bsis-accent fw-bold flex-grow-1 flex-md-grow-0"><i class="bi bi-qr-code-scan me-1"></i> Display Dynamic QR</button>
                <button onclick="window.location.hash='#live-monitor'" class="btn btn-sm btn-outline-light flex-grow-1 flex-md-grow-0"><i class="bi bi-broadcast me-1"></i> Live Stream</button>
                <button onclick="AdminApp.triggerQuickOverrideModal()" class="btn btn-sm btn-outline-light flex-grow-1 flex-md-grow-0"><i class="bi bi-person-check me-1"></i> Fast Override</button>
            </div>
        </div>

        <!-- VIEW 0: ADMIN & FACULTY CONTROL CENTER LOGIN -->
        <section id="view-login" class="admin-view d-none">
            <div class="admin-login-wrapper">
                <div class="admin-login-card-container">
                    <div class="row g-0">
                        <!-- Desktop Left Only: Institutional Showcase & Security Overview (Hidden on Mobile) -->
                        <div class="col-lg-6 d-none d-lg-flex admin-login-hero-side">
                            <div>
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <img src="/images/tpc-logo.png" alt="Talibon Polytechnic College" style="height: 54px; width: 54px; border-radius: 50%; border: 2px solid rgba(53, 196, 232, 0.4); background: #fff; padding: 2px;">
                                    <img src="/images/bsis-logo.png" alt="BSIS Logo" style="height: 54px; width: 54px; filter: drop-shadow(0 2px 8px rgba(0,0,0,0.2));">
                                    <div>
                                        <div class="fw-bold text-white fs-6" style="line-height: 1.2;">Talibon Polytechnic College</div>
                                        <div style="color: var(--color-accent); font-size: 0.76rem; font-weight: 600; letter-spacing: 0.5px;">BSIS Department &bull; Control Center</div>
                                    </div>
                                </div>

                                <h3 class="fw-bold text-white mb-2">BSIS Attendance Monitoring System</h3>
                                <p class="text-light opacity-75 small mb-4" style="line-height: 1.55;">
                                    Centralized administrative platform for event scheduling, dynamic QR code broadcasting, GPS geofence monitoring, automated fines calculation, and institutional clearance.
                                </p>

                                <div class="mb-4">
                                    <div class="admin-login-feature-item">
                                        <div class="admin-login-feature-icon">
                                            <i class="bi bi-qr-code-scan"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block text-white small">HMAC-SHA256 Dynamic QR</strong>
                                            <span class="text-light opacity-75" style="font-size: 0.76rem;">Anti-proxy codes auto-refreshing in real time</span>
                                        </div>
                                    </div>

                                    <div class="admin-login-feature-item">
                                        <div class="admin-login-feature-icon">
                                            <i class="bi bi-geo-alt-fill"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block text-white small">Geofence GPS Verification</strong>
                                            <span class="text-light opacity-75" style="font-size: 0.76rem;">Location validation with radius tolerance check</span>
                                        </div>
                                    </div>

                                    <div class="admin-login-feature-item">
                                        <div class="admin-login-feature-icon">
                                            <i class="bi bi-shield-check"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block text-white small">Hardware Device Lockdown</strong>
                                            <span class="text-light opacity-75" style="font-size: 0.76rem;">1-student-1-device binding with biometric authentication</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between pt-3 border-top border-white border-opacity-10" style="font-size: 0.76rem;">
                                <span class="text-light opacity-75"><i class="bi bi-circle-fill text-success me-1" style="font-size: 0.55rem;"></i> System Live & Secure</span>
                                <span class="text-light opacity-50">Cloudflare Protected</span>
                            </div>
                        </div>

                        <!-- Right on Desktop, Full Card on Mobile: Sleek Sign In Form -->
                        <div class="col-12 col-lg-6 admin-login-form-side">
                            <!-- Mobile-Only Clean Institutional Branding Header -->
                            <div class="d-flex d-lg-none align-items-center gap-3 mb-4 pb-3 border-bottom">
                                <img src="/images/tpc-logo.png" alt="Talibon Polytechnic College" style="height: 48px; width: 48px; border-radius: 50%; border: 2px solid rgba(6, 59, 92, 0.2); background: #fff; padding: 2px; flex-shrink: 0;">
                                <img src="/images/bsis-logo.png" alt="BSIS Logo" style="height: 48px; width: 48px; filter: drop-shadow(0 2px 6px rgba(0,0,0,0.12)); flex-shrink: 0;">
                                <div class="text-start">
                                    <div class="fw-bold text-dark fs-6" style="line-height: 1.25;">TALIBON POLYTECHNIC COLLEGE</div>
                                    <div style="color: var(--color-primary); font-size: 0.78rem; font-weight: 700; letter-spacing: 0.3px;">BSIS Department</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center gap-2 px-2 py-1 rounded bg-primary bg-opacity-10 text-primary small fw-bold mb-2">
                                    <i class="bi bi-shield-lock-fill"></i> Authorized Personnel Only
                                </div>
                                <h4 class="fw-bold text-dark mb-1">Faculty & Staff Login</h4>
                                <p class="text-muted small mb-0">Enter your institutional credentials to access the management dashboard.</p>
                            </div>

                            <div id="admin-login-alert" class="alert alert-danger d-none" style="font-size: 0.88rem; border-radius: 10px;"></div>

                            <form id="admin-login-form" onsubmit="return false;" autocomplete="off">
                                <div class="mb-3 text-start">
                                    <label class="bsis-form-label fw-semibold text-dark small mb-1">Institutional Email</label>
                                    <div class="admin-input-group">
                                        <input type="text" id="admin-login-identifier" class="form-control" placeholder="e.g. admin@tpc-bsis.online or faculty@tpc.edu.ph" required onkeydown="if(event.key==='Enter') AdminApp.handleAdminLogin(event)">
                                        <i class="bi bi-envelope-fill input-icon"></i>
                                    </div>
                                </div>

                                <div class="mb-4 text-start">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="bsis-form-label fw-semibold text-dark small mb-0">Password</label>
                                    </div>
                                    <div class="admin-input-group">
                                        <input type="password" id="admin-login-password" class="form-control" placeholder="Enter your password" required onkeydown="if(event.key==='Enter') AdminApp.handleAdminLogin(event)">
                                        <i class="bi bi-shield-lock-fill input-icon"></i>
                                        <button class="password-toggle-btn" type="button" onclick="AdminApp.togglePasswordVisibility('admin-login-password', this)" title="Show / Hide Password" style="right: 14px;">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="button" id="admin-login-btn" onclick="AdminApp.handleAdminLogin(event)" class="btn-admin-submit w-100">
                                    <span>Sign In to Control Center</span>
                                    <i class="bi bi-arrow-right-short fs-4"></i>
                                </button>
                            </form>

                            <div class="admin-login-footer">
                                <div class="text-muted mb-1">Talibon Polytechnic College &bull; BSIS Department</div>
                                <div class="text-muted opacity-75" style="font-size: 0.74rem;">BSIS Attendance Monitoring System &bull; Version 1.0.0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- VIEW 1: OVERVIEW DASHBOARD -->
        <section id="view-overview" class="admin-view d-none">
            <!-- Unified & Compact Dashboard Header Toolbar -->
            <div class="bsis-card p-3 mb-4 shadow-sm" style="border-radius: 16px;">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <!-- Left: Title, Subtitle & Live Compliance Gauge -->
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div>
                            <h4 class="fw-bold text-primary mb-0" style="letter-spacing: -0.02em;">Dashboard</h4>
                            <span class="text-muted small" style="font-size: 0.76rem;">Attendance Analytics & Turnout Breakdown</span>
                        </div>
                        <!-- Department Head Executive Turnout Health Gauge -->
                        <div id="overview-health-gauge" class="bsis-health-gauge bsis-health-gauge-optimal d-none" style="margin: 0; padding: 3px 10px; font-size: 0.72rem;">
                            <span class="bsis-pulse-dot"></span>
                            <span id="overview-health-gauge-text">OPTIMAL COMPLIANCE (85%+)</span>
                        </div>
                    </div>

                    <!-- Right: Event Target Selector & Action Buttons in a Single Compact Row -->
                    <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1 flex-md-grow-0 justify-content-md-end">
                        <div class="d-flex align-items-center gap-2" style="min-width: 240px; max-width: 360px;">
                            <span class="small fw-bold text-primary text-nowrap"><i class="bi bi-calendar2-range-fill me-1 text-primary"></i> Target:</span>
                            <select id="overview-event-select" class="form-select form-select-sm fw-semibold shadow-sm" style="border-radius: 8px; font-size: 0.85rem;" onchange="AdminApp.loadOverview(this.value)">
                                <option value="">Loading events...</option>
                            </select>
                        </div>

                        <button type="button" class="btn btn-sm btn-bsis-outline py-1 px-2" style="font-size: 0.82rem;" onclick="AdminApp.loadOverview(document.getElementById('overview-event-select')?.value)" title="Refresh Overview">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>

                        <button type="button" class="btn btn-sm btn-bsis-primary py-1 px-3 staff-only-btn d-none" title="Live Scanner" onclick="window.location.hash='#live-monitor'">
                            <i class="bi bi-broadcast me-1"></i> Live Scanner
                        </button>

                        <!-- Quick Override Action Button -->
                        <button class="btn btn-bsis-accent btn-sm staff-only-btn d-none py-1 px-2" data-bs-toggle="modal" data-bs-target="#modal-manual-override" title="Manual Attendance Override">
                            <i class="bi bi-person-check me-1"></i> Override
                        </button>
                    </div>
                </div>
            </div>

            <!-- Akademi 2-Column Responsive Canvas -->
            <div class="row g-4 mb-4">
                <!-- Main Canvas (Left 8 Columns) -->
                <div class="col-xl-8 col-lg-8 col-12">
                    <!-- Top 4 Circular KPI Metric Cards -->
                    <div class="row g-3 mb-4">
                        <!-- 1. Students / Target -->
                        <div class="col-sm-6 col-md-3">
                            <div class="bsis-metric-pill-card">
                                <div class="bsis-circle-icon bsis-circle-icon-primary">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div>
                                    <div class="bsis-metric-label">Target Roster</div>
                                    <div class="bsis-metric-value text-primary" id="stat-total-target">0</div>
                                    <div class="bsis-metric-subtext text-muted">Students</div>
                                </div>
                            </div>
                        </div>
                        <!-- 2. Present (On-Time) -->
                        <div class="col-sm-6 col-md-3">
                            <div class="bsis-metric-pill-card">
                                <div class="bsis-circle-icon bsis-circle-icon-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div>
                                    <div class="bsis-metric-label">Present</div>
                                    <div class="bsis-metric-value text-success" id="stat-present-count">0</div>
                                    <div class="bsis-metric-subtext text-success" id="stat-present-rate">0%</div>
                                </div>
                            </div>
                        </div>
                        <!-- 3. Late Scans -->
                        <div class="col-sm-6 col-md-3">
                            <div class="bsis-metric-pill-card">
                                <div class="bsis-circle-icon bsis-circle-icon-warning">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div>
                                    <div class="bsis-metric-label">Late Scans</div>
                                    <div class="bsis-metric-value text-warning" id="stat-late-count">0</div>
                                    <div class="bsis-metric-subtext text-warning" id="stat-late-rate">0%</div>
                                </div>
                            </div>
                        </div>
                        <!-- 4. Turnout Rate -->
                        <div class="col-sm-6 col-md-3">
                            <div class="bsis-metric-pill-card">
                                <div class="bsis-circle-icon bsis-circle-icon-accent">
                                    <i class="bi bi-pie-chart-fill"></i>
                                </div>
                                <div>
                                    <div class="bsis-metric-label">Turnout Rate</div>
                                    <div class="bsis-metric-value text-info" id="stat-turnout-rate">0%</div>
                                    <div class="bsis-metric-subtext text-muted" id="stat-attended-count">0 Attended</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Status Distribution & Trends Card -->
                    <div class="bsis-card mb-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-primary mb-0"><i class="bi bi-pie-chart-fill me-1"></i> Attendance Status Breakdown</h6>
                                <small class="text-muted">Breakdown of present, late, absent, and override students</small>
                            </div>
                            <span class="badge bg-light text-dark border px-2 py-1" id="chart-event-tag">All Today</span>
                        </div>
                        
                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <div style="position: relative; height: 210px;" class="d-flex justify-content-center align-items-center">
                                    <canvas id="overview-status-chart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex flex-column gap-2" id="overview-status-legend">
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-success bg-opacity-10 border border-success border-opacity-25">
                                        <span class="text-success fw-semibold small"><i class="bi bi-circle-fill me-1"></i> Present (On-Time)</span>
                                        <strong class="text-success" id="legend-present">0</strong>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                        <span class="text-warning fw-semibold small"><i class="bi bi-circle-fill me-1"></i> Late Scans</span>
                                        <strong class="text-warning" id="legend-late">0</strong>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-danger bg-opacity-10 border border-danger border-opacity-25">
                                        <span class="text-danger fw-semibold small"><i class="bi bi-circle-fill me-1"></i> Absent / Missed</span>
                                        <strong class="text-danger" id="legend-absent">0</strong>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-info bg-opacity-10 border border-info border-opacity-25">
                                        <span class="text-info fw-semibold small"><i class="bi bi-circle-fill me-1"></i> Manual Override</span>
                                        <strong class="text-info" id="legend-override">0</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-Grid: Calendar Widget + Session Turnout Bar Chart -->
                    <div class="row g-3 mb-4">
                        <!-- Interactive Event Schedule Calendar -->
                        <div class="col-md-5">
                            <div class="bsis-calendar-card shadow-sm">
                                <div class="bsis-calendar-header">
                                    <h6 class="fw-bold text-primary mb-0" id="calendar-month-title">August 2026</h6>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary py-0 px-2" onclick="AdminApp.changeCalendarMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                                        <button type="button" class="btn btn-outline-secondary py-0 px-2" onclick="AdminApp.changeCalendarMonth(1)"><i class="bi bi-chevron-right"></i></button>
                                    </div>
                                </div>
                                <div class="bsis-calendar-grid mb-2">
                                    <div class="bsis-calendar-weekday">Su</div>
                                    <div class="bsis-calendar-weekday">Mo</div>
                                    <div class="bsis-calendar-weekday">Tu</div>
                                    <div class="bsis-calendar-weekday">We</div>
                                    <div class="bsis-calendar-weekday">Th</div>
                                    <div class="bsis-calendar-weekday">Fr</div>
                                    <div class="bsis-calendar-weekday">Sa</div>
                                </div>
                                <div class="bsis-calendar-grid" id="calendar-days-grid">
                                    <!-- Rendered dynamically via AdminApp -->
                                </div>
                            </div>
                        </div>

                        <!-- Session Scanning Turnout Bar Chart -->
                        <div class="col-md-7">
                            <div class="bsis-card p-3 h-100 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="fw-bold text-primary mb-0"><i class="bi bi-bar-chart-line-fill me-1"></i> Session Slot Turnout</h6>
                                        <small class="text-muted">Verified scans per scheduled time window</small>
                                    </div>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border" id="chart-session-type-badge">EVENT SCANS</span>
                                </div>

                                <div style="position: relative; height: 210px;">
                                    <canvas id="overview-session-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Table: Recent Scans Activity Feed -->
                    <div class="bsis-card p-3 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-primary mb-0"><i class="bi bi-clock-history me-1"></i> Recent Attendance Activity Feed</h6>
                                <small class="text-muted">Latest verified attendance scans recorded in real time</small>
                            </div>
                            <button class="btn btn-sm btn-bsis-outline py-1 px-3" style="font-size: 0.75rem;" onclick="window.location.hash='#live-monitor'"><i class="bi bi-broadcast me-1"></i> Live Stream</button>
                        </div>
                        <div class="table-responsive bsis-table-responsive">
                            <table class="table bsis-table table-hover align-middle mb-0" style="min-width: 760px;">
                                <thead>
                                    <tr>
                                        <th style="width: 140px;">Student ID</th>
                                        <th>Student Name</th>
                                        <th style="width: 130px;">Year & Block</th>
                                        <th style="width: 150px;">Event Title</th>
                                        <th style="width: 110px;">Status</th>
                                        <th class="text-nowrap" style="width: 120px;">Scan Time</th>
                                        <th class="text-end text-nowrap" style="width: 100px;">Distance</th>
                                    </tr>
                                </thead>
                                <tbody id="overview-recent-scans-table">
                                    <tr>
                                        <td colspan="7" style="padding: 0; border: none;">
                                            <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-id"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-text"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-btn"></div></div>
                                            <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-id"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-text"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-btn"></div></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Side Quick Glance Panel (Right 4 Columns) -->
                <div class="col-xl-4 col-lg-4 col-12">
                    <!-- 1. Recent Active Students Feed Card -->
                    <div class="bsis-card p-3 mb-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-primary mb-0"><i class="bi bi-person-check-fill me-1 text-primary"></i> Recent Students</h6>
                                <small class="text-muted">Verified attendees today</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-bsis-outline py-0 px-2" style="font-size: 0.75rem;" onclick="window.location.hash='#users'">View All</button>
                        </div>
                        <div id="right-panel-recent-students">
                            <!-- Populated dynamically via AdminApp -->
                            <div class="text-center py-3 text-muted small">Loading attendees...</div>
                        </div>
                    </div>

                    <!-- 2. Unpaid Fines & Penalties Summary Card -->
                    <div class="bsis-card p-3 mb-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="fw-bold text-danger mb-0"><i class="bi bi-cash-stack me-1"></i> Fines & Missed Scans</h6>
                                <small class="text-muted">Current event balance</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-bsis-outline py-0 px-2" style="font-size: 0.75rem;" onclick="window.location.hash='#fines'">Fines</button>
                        </div>
                        <div class="p-3 bg-danger bg-opacity-10 rounded border border-danger border-opacity-25 mb-3 text-center">
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Unpaid Fines</span>
                            <h3 class="fw-bold text-danger mb-0" id="stat-unpaid-fines">₱0.00</h3>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <span>Absent / Missed Slots:</span>
                            <strong class="text-danger" id="stat-absent-count">0 students</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small text-muted mt-1">
                            <span>Missed Rate:</span>
                            <strong class="text-danger" id="stat-absent-rate">0% of roster</strong>
                        </div>
                    </div>

                    <!-- 3. Upcoming Scheduled Events Mini Cards -->
                    <div class="bsis-card p-3 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-primary mb-0"><i class="bi bi-calendar-event-fill me-1 text-primary"></i> Upcoming Events</h6>
                                <small class="text-muted">Scheduled department sessions</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-bsis-outline py-0 px-2" style="font-size: 0.75rem;" onclick="window.location.hash='#events'">Events</button>
                        </div>
                        <div id="right-panel-upcoming-events">
                            <!-- Populated dynamically via AdminApp -->
                            <div class="text-center py-3 text-muted small">Loading schedule...</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- VIEW 2: EVENT MANAGEMENT -->
        <section id="view-events" class="admin-view d-none">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Event Management</h3>
                    <p class="text-muted small mb-0">Search, sort, and manage upcoming, active, and completed college events</p>
                </div>
                <button class="btn btn-bsis-primary admin-only-btn text-nowrap" data-bs-toggle="modal" data-bs-target="#modal-create-event"><i class="bi bi-plus-lg me-1"></i> Create New Event</button>
            </div>

            <!-- Event Filter & Sort Toolbar Card -->
            <div class="bsis-card p-3 mb-3">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="bsis-form-label small text-muted">Search Events</label>
                        <input type="text" id="event-search-input" class="bsis-form-control" placeholder="Search event title, venue, or details..." onkeyup="AdminApp.loadEvents()">
                    </div>
                    <div class="col-md-3">
                        <label class="bsis-form-label small text-muted">Event Status</label>
                        <select id="event-status-filter" class="bsis-form-control" onchange="AdminApp.loadEvents()">
                            <option value="">All Statuses (Active, Upcoming, Completed)</option>
                            <option value="active">Active Sessions Only</option>
                            <option value="upcoming">Upcoming Events Only</option>
                            <option value="completed">Completed Events Only</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="bsis-form-label small text-muted">Sort By</label>
                        <select id="event-sort-by" class="bsis-form-control" onchange="AdminApp.loadEvents()">
                            <option value="start_time:desc">Start Date (Newest First)</option>
                            <option value="start_time:asc">Start Date (Oldest First)</option>
                            <option value="title:asc">Event Title (A to Z)</option>
                            <option value="title:desc">Event Title (Z to A)</option>
                            <option value="fine_amount:desc">Fine Amount (High to Low)</option>
                            <option value="fine_amount:asc">Fine Amount (Low to High)</option>
                            <option value="status:asc">Status</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Batch Actions Toolbar for Events -->
            <div id="events-batch-toolbar" class="alert alert-danger py-2 px-3 mb-3 d-none align-items-center justify-content-between shadow-sm" style="border-radius: var(--radius-sm); border-left: 4px solid var(--color-danger);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-danger fs-5"></i>
                    <div>
                        <strong id="events-selected-count-text" class="text-danger">0 event(s) selected</strong>
                        <small class="text-muted d-block" style="font-size: 0.75rem;">Selected for bulk deletion</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="AdminApp.clearEventSelection()">
                        <i class="bi bi-x-circle"></i> Deselect
                    </button>
                    <button type="button" class="btn btn-sm btn-danger fw-bold py-1 px-3 shadow-sm" onclick="AdminApp.promptBatchDeleteEvents()">
                        <i class="bi bi-trash-fill me-1"></i> Delete Selected Events
                    </button>
                </div>
            </div>

            <div class="bsis-card p-3 shadow-sm">
                <div class="table-responsive bsis-table-responsive">
                    <table class="table bsis-table bsis-table-sticky table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="sticky-col-1 text-center" style="width: 46px;">
                                    <input type="checkbox" id="events-select-all" class="form-check-input" onchange="AdminApp.toggleAllEventCheckboxes(this)" title="Select All Events">
                                </th>
                                <th class="sticky-col-event" style="min-width: 220px;">Event Title & Audience</th>
                                <th class="text-nowrap" style="min-width: 180px;">Venue & Allowed Radius</th>
                                <th class="text-nowrap" style="min-width: 160px;">Start Time</th>
                                <th class="text-nowrap" style="min-width: 100px;">Fine Amount</th>
                                <th class="text-nowrap text-center" style="min-width: 100px;">Status</th>
                                <th class="text-nowrap text-center" style="width: 110px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="events-table-body">
                            <tr>
                                <td colspan="7" style="padding: 0; border: none;">
                                    <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-checkbox"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-text"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-btn"></div></div>
                                    <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-checkbox"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-text"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-btn"></div></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- VIEW 3: DYNAMIC QR DISPLAY SCREEN -->
        <section id="view-qr-display" class="admin-view d-none">
            <div class="qr-view-container text-center py-2 w-100">
                <!-- Top Toolbar with Back and Fullscreen Toggle -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 px-2 qr-top-actions mx-auto" style="max-width: 580px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 qr-back-btn" onclick="window.location.hash='#events'; AdminApp.showView('view-events');">
                        <i class="bi bi-arrow-left"></i> Back to Events
                    </button>
                    <button type="button" id="btn-fullscreen-qr" onclick="AdminApp.toggleQrFullscreen()" class="btn btn-sm btn-bsis-primary fw-semibold px-3 d-inline-flex align-items-center gap-2 shadow-sm" title="Toggle Fullscreen Projector Mode">
                        <i class="bi bi-arrows-fullscreen" id="btn-fullscreen-qr-icon"></i> <span id="btn-fullscreen-qr-text">Fullscreen Mode</span>
                    </button>
                </div>

                <div class="d-flex justify-content-center align-items-center gap-2 mb-2 flex-wrap">
                    <span id="qr-window-badge" class="bsis-badge bsis-badge-success" style="font-size: 0.92rem; padding: 6px 16px;">🟢 LIVE ATTENDANCE</span>
                    <span id="qr-window-countdown-badge" class="bsis-badge bsis-badge-warning d-none" style="font-size: 0.92rem; padding: 6px 16px;"><i class="bi bi-clock-history me-1"></i> <span id="qr-window-countdown-text">Closes in --:--</span></span>
                    <span class="bsis-badge bsis-badge-info" id="qr-interval-badge-display" style="font-size: 0.92rem; padding: 6px 16px;">Refreshes every 20s</span>
                    <button type="button" id="btn-toggle-bypass" onclick="AdminApp.toggleQrBypass()" class="btn btn-sm btn-outline-warning fw-bold py-1 px-3" style="border-radius: 20px;">
                        <i class="bi bi-lightning-charge-fill"></i> <span id="qr-bypass-btn-text">Emergency Bypass: OFF</span>
                    </button>
                </div>

                <h2 id="qr-display-title" class="fw-bold text-primary mb-1">Loading Event...</h2>
                <p id="qr-display-venue" class="text-muted mb-2">Venue Location</p>
                <div id="qr-window-message" class="alert alert-info py-2 px-3 mx-auto mb-3 text-center fw-semibold d-none" style="max-width: 480px; font-size: 0.88rem; border-radius: var(--radius-sm);"></div>

                <div class="bsis-card qr-card-wrapper p-4 mx-auto mb-3" style="max-width: 440px;">
                    <div id="qr-closed-overlay" class="d-none py-5 text-center">
                        <i class="bi bi-door-closed text-danger" style="font-size: 3.5rem;"></i>
                        <h5 class="fw-bold text-danger mt-2 mb-1">Attendance Window Closed</h5>
                        <p id="qr-closed-details" class="text-muted small mb-3">Scanning is currently outside the scheduled timeframe.</p>
                        <button type="button" onclick="AdminApp.toggleQrBypass()" class="btn btn-sm btn-warning fw-bold"><i class="bi bi-lightning-charge-fill"></i> Enable Emergency Bypass</button>
                    </div>
                    <div id="qr-active-container">
                        <div id="qr-canvas-wrapper" class="d-flex justify-content-center mb-3"></div>
                        <img id="qr-code-image" src="" alt="Dynamic QR Code" class="img-fluid mb-3 d-none">
                        
                        <!-- Progress & Timer Bar -->
                        <div class="mb-2">
                            <div class="qr-timer-bar mx-auto mb-2" id="qr-timer-progress" style="width: 100%;"></div>
                            <span class="fw-bold text-primary" style="font-size: 1.2rem;">Refreshes in <span id="qr-timer-text">20s</span></span>
                        </div>
                        <p class="text-muted small mb-0 font-monospace text-break" id="qr-raw-token-text" style="font-size: 0.7rem;"></p>
                    </div>
                </div>

                <!-- Live Counter Stats Bar -->
                <div class="row g-2 align-items-stretch qr-live-stats-row" style="max-width: 480px; margin: 0 auto;">
                    <div class="col-4">
                        <div class="bsis-card p-2 text-center h-100 d-flex flex-column justify-content-between">
                            <span class="text-muted small fw-semibold" style="font-size: 0.70rem; min-height: 28px; display: flex; align-items: center; justify-content: center;">TOTAL SCANNED</span>
                            <h4 class="fw-bold text-primary mb-0 mt-1" id="qr-live-total">0</h4>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bsis-card p-2 text-center h-100 d-flex flex-column justify-content-between">
                            <span class="text-muted small fw-semibold" style="font-size: 0.70rem; min-height: 28px; display: flex; align-items: center; justify-content: center;">PRESENT</span>
                            <h4 class="fw-bold text-success mb-0 mt-1" id="qr-live-present">0</h4>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bsis-card p-2 text-center h-100 d-flex flex-column justify-content-between">
                            <span class="text-muted small fw-semibold" style="font-size: 0.70rem; min-height: 28px; display: flex; align-items: center; justify-content: center;">LATE</span>
                            <h4 class="fw-bold text-warning mb-0 mt-1" id="qr-live-late">0</h4>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- VIEW 4: LIVE ATTENDANCE MONITOR -->
        <section id="view-live-monitor" class="admin-view d-none">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
                <div class="w-100 w-md-auto">
                    <h3 class="fw-bold text-primary mb-1">Real-Time Attendance Monitoring</h3>
                    <p class="text-muted small mb-0">Live incremental scan feed (auto-updates every 3 seconds)</p>
                </div>
                <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 w-100 w-md-auto">
                    <select id="live-event-select" class="bsis-form-control w-100" style="max-width: 100%; font-size: 0.88rem;" onchange="AdminApp.startLiveMonitoring(this.value)">
                        <option value="">Select Event Session...</option>
                    </select>
                    <button class="btn btn-bsis-primary btn-sm text-nowrap py-2 px-3 fw-semibold flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modal-manual-override">
                        <i class="bi bi-person-check me-1"></i> Staff Override
                    </button>
                </div>
            </div>

            <!-- Live Stats Row (100% Equal Height Cards) -->
            <div class="row g-2 g-md-3 mb-4 align-items-stretch">
                <div class="col-4">
                    <div class="bsis-card p-2 p-md-3 text-center h-100 d-flex flex-column justify-content-between shadow-sm">
                        <span class="text-muted small fw-bold" style="font-size: 0.72rem; line-height: 1.2; min-height: 28px; display: flex; align-items: center; justify-content: center;">TOTAL SCANS</span>
                        <h2 class="fw-bold text-primary mb-0 mt-1" id="live-stat-total">0</h2>
                    </div>
                </div>
                <div class="col-4">
                    <div class="bsis-card p-2 p-md-3 text-center h-100 d-flex flex-column justify-content-between shadow-sm">
                        <span class="text-muted small fw-bold" style="font-size: 0.72rem; line-height: 1.2; min-height: 28px; display: flex; align-items: center; justify-content: center;">PRESENT</span>
                        <h2 class="fw-bold text-success mb-0 mt-1" id="live-stat-present">0</h2>
                    </div>
                </div>
                <div class="col-4">
                    <div class="bsis-card p-2 p-md-3 text-center h-100 d-flex flex-column justify-content-between shadow-sm">
                        <span class="text-muted small fw-bold" style="font-size: 0.72rem; line-height: 1.2; min-height: 28px; display: flex; align-items: center; justify-content: center;">LATE</span>
                        <h2 class="fw-bold text-warning mb-0 mt-1" id="live-stat-late">0</h2>
                    </div>
                </div>
            </div>

            <div class="bsis-card p-3 shadow-sm">
                <div class="table-responsive bsis-table-responsive">
                    <table class="table bsis-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Scan Type</th>
                                <th>Status</th>
                                <th>Scan Time</th>
                                <th>Distance</th>
                                <th>Method</th>
                                <th>Fine (PHP)</th>
                            </tr>
                        </thead>
                        <tbody id="live-monitor-table-body">
                            <tr><td colspan="8" class="text-center text-muted">Select an active event session to begin live feed.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- VIEW 5: USER MANAGEMENT (ENHANCED SEARCH & SORT CONTROLS) -->
        <section id="view-users" class="admin-view d-none">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                <div>
                    <h3 class="fw-bold text-primary mb-1">User & Student Management</h3>
                    <p class="text-muted small mb-0">Manage student profiles, roles, and device authorizations</p>
                </div>
                <div class="d-flex flex-wrap gap-2 w-100 w-sm-auto justify-content-start justify-content-sm-end">
                    <button class="btn btn-bsis-primary btn-sm py-2 px-3 fw-bold text-nowrap" data-bs-toggle="modal" data-bs-target="#modal-create-student">
                        <i class="bi bi-person-plus-fill me-1"></i> Register User
                    </button>
                    <button class="btn btn-bsis-accent btn-sm py-2 px-3 fw-bold text-nowrap" data-bs-toggle="modal" data-bs-target="#modal-csv-import">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Batch CSV Import
                    </button>
                </div>
            </div>

            <!-- Quick Role Category Filter Tabs -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="user-role-tab active" data-role="" onclick="AdminApp.setUserRoleTab('', this)">
                    <i class="bi bi-people-fill me-1"></i> All Accounts <span class="badge bg-secondary ms-1" id="user-count-all">0</span>
                </button>
                <button type="button" class="user-role-tab" data-role="student" onclick="AdminApp.setUserRoleTab('student', this)">
                    <i class="bi bi-mortarboard-fill me-1 text-success"></i> Students Only <span class="badge bg-success text-white ms-1" id="user-count-students">0</span>
                </button>
                <button type="button" class="user-role-tab" data-role="event_staff" onclick="AdminApp.setUserRoleTab('event_staff', this)">
                    <i class="bi bi-person-badge-fill me-1 text-info"></i> Event Staff Only <span class="badge bg-info text-white ms-1" id="user-count-staff">0</span>
                </button>
                <button type="button" class="user-role-tab" data-role="admin" onclick="AdminApp.setUserRoleTab('admin', this)">
                    <i class="bi bi-shield-lock-fill me-1 text-danger"></i> Administrators Only <span class="badge bg-danger text-white ms-1" id="user-count-admins">0</span>
                </button>
            </div>

            <!-- Search, Filter & Sort Toolbar Card -->
            <div class="bsis-card p-3 mb-3">
                <div class="row g-2 mb-2">
                    <div class="col-md-8">
                        <label class="bsis-form-label small text-muted">Search Query</label>
                        <div class="bsis-autocomplete-container">
                            <input type="text" id="user-search-input" class="bsis-form-control pe-4" placeholder="Type name, student ID, or email..." oninput="AdminApp.handleUserSearchDebounced()" autocomplete="off">
                            <button type="button" class="bsis-autocomplete-clear-btn" id="user-search-clear" onclick="AdminApp.clearSearchInput('user')" title="Clear Search"><i class="bi bi-x-circle-fill"></i></button>
                            <div class="bsis-autocomplete-dropdown shadow-lg" id="user-search-autocomplete"></div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="bsis-form-label small text-muted">Filter by Role</label>
                        <select id="user-role-filter" class="bsis-form-control" onchange="AdminApp.syncRoleDropdownToTabs(this.value); AdminApp.loadUsers()">
                            <option value="">All Account Roles</option>
                            <option value="student">Students Only</option>
                            <option value="event_staff">Event Staff Only</option>
                            <option value="admin">Administrators Only</option>
                        </select>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-3" id="user-year-filter-wrapper">
                        <label class="bsis-form-label small text-muted">Year Level Filter</label>
                        <select id="user-year-filter" class="bsis-form-control" onchange="AdminApp.handleYearOrBlockFilterChange()">
                            <option value="">All Year Levels</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>

                    <div class="col-md-3" id="user-block-filter-wrapper">
                        <label class="bsis-form-label small text-muted">Block Filter</label>
                        <select id="user-block-filter" class="bsis-form-control" onchange="AdminApp.handleYearOrBlockFilterChange()">
                            <option value="">All Blocks</option>
                            @for ($b = 1; $b <= 20; $b++)
                                <option value="Block {{ $b }}">Block {{ $b }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="bsis-form-label small text-muted">Sort By Field</label>
                        <select id="user-sort-by" class="bsis-form-control" onchange="AdminApp.loadUsers()">
                            <option value="last_name">Last Name (Alphabetical A-Z)</option>
                            <option value="created_at">Date Registered / Added</option>
                            <option value="role">Role (Admins / Staff / Students)</option>
                            <option value="first_name">First Name</option>
                            <option value="student_number">Student ID Number</option>
                            <option value="year_level">Year Level</option>
                            <option value="section_block">Block</option>
                            <option value="status">Account Status</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="bsis-form-label small text-muted">Sort Direction</label>
                        <select id="user-sort-order" class="bsis-form-control" onchange="AdminApp.loadUsers()">
                            <option value="desc">Descending (Z-A / Newest First)</option>
                            <option value="asc">Ascending (A-Z / Oldest First)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bsis-card p-3 shadow-sm">
                <!-- Users Batch Action Toolbar -->
                <div class="d-flex justify-content-between align-items-center mb-3" id="users-batch-toolbar" style="display: none !important;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small fw-bold"><i class="bi bi-check2-square"></i> <span id="users-selected-count">0</span> user(s) selected</span>
                    </div>
                    <button onclick="AdminApp.promptBatchDeleteUsers()" class="btn btn-danger btn-sm fw-bold px-3 py-2">
                        <i class="bi bi-trash-fill"></i> Delete Selected Users
                    </button>
                </div>

                <div class="table-responsive bsis-table-responsive">
                    <table class="table bsis-table bsis-table-sticky table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 36px;" class="text-center sticky-col-1"><input type="checkbox" id="user-select-all" onchange="AdminApp.toggleAllUserCheckboxes(this)" title="Select All"></th>
                                <th class="text-nowrap sticky-col-2">Student ID</th>
                                <th class="text-nowrap sticky-col-3">Full Name</th>
                                <th class="text-nowrap">Email</th>
                                <th class="text-nowrap text-center">Year Level</th>
                                <th class="text-nowrap text-center">Block</th>
                                <th class="text-nowrap text-center">Role</th>
                                <th class="text-nowrap text-center">Status</th>
                                <th class="text-nowrap text-center" style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <tr>
                                <td colspan="9" style="padding: 0; border: none;">
                                    <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-checkbox"></div><div class="bsis-skeleton skel-id"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-text"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-btn"></div></div>
                                    <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-checkbox"></div><div class="bsis-skeleton skel-id"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-text"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-btn"></div></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Users Pagination & Page Size Toolbar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top" id="users-pagination-container">
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted small" id="users-page-info">Showing 0 of 0 accounts</span>
                        <div class="d-flex align-items-center gap-1">
                            <label for="users-per-page" class="text-muted small mb-0">Show:</label>
                            <select id="users-per-page" class="form-select form-select-sm py-1 ps-2 pe-4" style="width: auto; min-width: 130px; font-size: 0.82rem; border-radius: 8px; cursor: pointer;" onchange="AdminApp.changeUsersPerPage(this.value)">
                                <option value="25" selected>25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                                <option value="all">All accounts</option>
                            </select>
                        </div>
                    </div>
                    <nav aria-label="Users pagination">
                        <ul class="pagination pagination-sm mb-0 gap-1" id="users-pagination-nav">
                            <!-- Populated via JavaScript -->
                        </ul>
                    </nav>
                </div>
            </div>
        </section>

        <!-- VIEW 6: DEVICE RESET AUDIT LOGS & HISTORY -->
        <section id="view-device-resets" class="admin-view d-none">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Device Reset History & Logs</h3>
                    <p class="text-muted small mb-0">Security audit history of authorized student hardware unbindings and device resets</p>
                </div>
                <div>
                    <a href="#users" onclick="AdminApp.navigateTo('users')" class="btn btn-bsis-primary btn-sm py-2 px-3 fw-bold text-nowrap">
                        <i class="bi bi-people-fill me-1"></i> Go to Users to Reset a Device
                    </a>
                </div>
            </div>

            <!-- Device Reset Search & Filter Toolbar -->
            <div class="bsis-card p-3 mb-3">
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="bsis-form-label small text-muted">Search Reset Logs</label>
                        <div class="bsis-autocomplete-container">
                            <input type="text" id="device-reset-search-input" class="bsis-form-control pe-4" placeholder="Search student name, ID number, description, or IP..." oninput="AdminApp.handleDeviceResetSearchDebounced()" autocomplete="off">
                            <button type="button" class="bsis-autocomplete-clear-btn" id="device-reset-search-clear" onclick="AdminApp.clearSearchInput('device-reset')" title="Clear Search"><i class="bi bi-x-circle-fill"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="bsis-form-label small text-muted">Filter by Action</label>
                        <select id="device-reset-action-filter" class="bsis-form-control" onchange="AdminApp.loadDeviceResets()">
                            <option value="">All Reset Actions</option>
                            <option value="direct_device_reset">Direct Admin Resets</option>
                            <option value="device_reset_approved">Approved Requests</option>
                            <option value="device_reset_rejected">Rejected Requests</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bsis-card p-3 shadow-sm">
                <div class="table-responsive bsis-table-responsive">
                    <table class="table bsis-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Details</th>
                                <th>Action Performed</th>
                                <th>Authorized By</th>
                                <th>Security & IP Trail</th>
                                <th class="text-nowrap text-end">Date & Timestamp</th>
                            </tr>
                        </thead>
                        <tbody id="device-resets-table-body">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Device Reset Pagination -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top" id="device-resets-pagination-container">
                    <span class="text-muted small" id="device-resets-page-info">Showing 0 of 0 logs</span>
                    <nav aria-label="Device resets pagination">
                        <ul class="pagination pagination-sm mb-0 gap-1" id="device-resets-pagination-nav"></ul>
                    </nav>
                </div>
            </div>
        </section>

        <!-- VIEW 7: FINES MANAGEMENT (WITH YEAR, BLOCK & TARGETED FILTERING + EXPORT & DIRECT PRINT) -->
        <section id="view-fines" class="admin-view d-none">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Fine Tracking & Payment Records</h3>
                    <p class="text-muted small mb-0">Filter fines by Student ID, Name, Year Level, Block, or Status</p>
                </div>
                <div class="d-flex flex-wrap gap-2 w-100 w-sm-auto justify-content-start justify-content-sm-end">
                    <button onclick="AdminApp.printFilteredFines()" class="btn btn-bsis-primary btn-sm py-2 px-3 fw-bold text-nowrap">
                        <i class="bi bi-printer me-1"></i> Print Fines Report
                    </button>
                    <button onclick="AdminApp.exportFilteredFinesCsv()" class="btn btn-bsis-accent btn-sm py-2 px-3 fw-bold text-nowrap">
                        <i class="bi bi-download me-1"></i> Export Fines CSV
                    </button>
                </div>
            </div>

            <!-- Fines Metric Summary Bar -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="bsis-card p-3 border-start border-4 border-primary d-flex justify-content-between align-items-center">
                        <span class="text-muted fw-bold">TOTAL FILTERED FINES</span>
                        <h3 class="fw-bold text-primary mb-0" id="fine-total-sum-display">₱0.00</h3>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="bsis-card p-3 border-start border-4 border-danger d-flex justify-content-between align-items-center">
                        <span class="text-muted fw-bold">TOTAL UNPAID FINES</span>
                        <h3 class="fw-bold text-danger mb-0" id="fine-unpaid-sum-display">₱0.00</h3>
                    </div>
                </div>
            </div>

            <!-- Fines Filter Toolbar Card -->
            <div class="bsis-card p-3 mb-3">
                <div class="row g-2 mb-2">
                    <div class="col-md-8">
                        <label class="bsis-form-label small text-muted">Search Student</label>
                        <div class="bsis-autocomplete-container">
                            <input type="text" id="fine-search-input" class="bsis-form-control pe-4" placeholder="Search student name or ID..." oninput="AdminApp.handleFineSearchDebounced()" autocomplete="off">
                            <button type="button" class="bsis-autocomplete-clear-btn" id="fine-search-clear" onclick="AdminApp.clearSearchInput('fine')" title="Clear Search"><i class="bi bi-x-circle-fill"></i></button>
                            <div class="bsis-autocomplete-dropdown shadow-lg" id="fine-search-autocomplete"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="bsis-form-label small text-muted">Payment Status</label>
                        <select id="fine-status-filter" class="bsis-form-control" onchange="AdminApp.loadFines()">
                            <option value="">All Payment Statuses</option>
                            <option value="false">Unpaid Fines Only</option>
                            <option value="true">Paid Fines Only</option>
                        </select>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="bsis-form-label small text-muted">Year Level Filter</label>
                        <select id="fine-year-filter" class="bsis-form-control" onchange="AdminApp.loadFines()">
                            <option value="">All Year Levels</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="bsis-form-label small text-muted">Block Filter</label>
                        <input type="text" id="fine-block-filter" class="bsis-form-control" placeholder="e.g. Block 1 ... Block 20" onkeyup="AdminApp.loadFines()">
                    </div>
                </div>
            </div>

            <div class="bsis-card p-3 shadow-sm">
                <!-- Batch Action Toolbar -->
                <div class="d-flex justify-content-between align-items-center mb-3" id="fines-batch-toolbar" style="display: none !important;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-dark small"><span id="fines-selected-count">0</span> records selected</span>
                        <button class="btn btn-sm btn-success fw-bold" onclick="AdminApp.batchMarkFinesPaid()"><i class="bi bi-check-circle"></i> Mark as Paid</button>
                        <button class="btn btn-sm btn-outline-danger fw-bold" onclick="AdminApp.batchWaiveFines()"><i class="bi bi-x-circle"></i> Waive Fines</button>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="AdminApp.deselectAllFines()"><i class="bi bi-dash-square"></i> Clear Selection</button>
                </div>

                <div class="table-responsive bsis-table-responsive">
                    <table class="table table-hover align-middle bsis-table mb-0" id="fines-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="fine-select-all" onchange="AdminApp.toggleAllFineCheckboxes(this)">
                                </th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Year & Block</th>
                                <th>Event Title</th>
                                <th>Violation</th>
                                <th>Fine Amount</th>
                                <th class="text-center">Payment</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fines-table-body">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Fines Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" id="fines-pagination-container">
                    <span class="text-muted small" id="fines-page-info">Showing 0 of 0 records</span>
                    <ul class="pagination pagination-sm mb-0" id="fines-pagination"></ul>
                </div>
            </div>
        </section>

        <!-- VIEW 8: REPORTS, WORD (.DOCX), CSV EXPORT & PRINT -->
        <section id="view-reports" class="admin-view d-none">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Attendance Reports & Official Export</h3>
                    <p class="text-muted small mb-0">Generate, print, and export official attendance sheets per specific event in Word (.docx) or CSV</p>
                </div>
                <div class="d-flex flex-wrap gap-2 w-100 w-sm-auto justify-content-start justify-content-sm-end">
                    <button onclick="AdminApp.printEventAttendanceReport()" class="btn btn-bsis-primary btn-sm py-2 px-3 fw-bold text-nowrap">
                        <i class="bi bi-printer-fill me-1"></i> Print Attendance Sheet
                    </button>
                    <button onclick="AdminApp.exportReportDocx()" class="btn btn-primary btn-sm py-2 px-3 fw-bold text-nowrap" style="background: #185abd; border-color: #185abd;">
                        <i class="bi bi-file-earmark-word-fill me-1"></i> Export Word (.docx)
                    </button>
                    <button onclick="AdminApp.exportReportCsv()" class="btn btn-bsis-accent btn-sm py-2 px-3 fw-bold text-nowrap">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Export CSV
                    </button>
                </div>
            </div>

            <!-- Report Filter Toolbar Card -->
            <div class="bsis-card p-3 mb-3">
                <div class="row g-2 mb-2">
                    <div class="col-md-5">
                        <label class="bsis-form-label fw-bold text-primary small"><i class="bi bi-calendar-event me-1"></i> Select Specific Event Session</label>
                        <select id="report-event-filter" class="bsis-form-control fw-semibold" onchange="AdminApp.loadReports()">
                            <option value="">All Events (Combined Summary)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="bsis-form-label small text-muted">Search Student</label>
                        <div class="bsis-autocomplete-container">
                            <input type="text" id="report-search-input" class="bsis-form-control pe-4" placeholder="Search student name or ID..." oninput="AdminApp.handleReportSearchDebounced()" autocomplete="off">
                            <button type="button" class="bsis-autocomplete-clear-btn" id="report-search-clear" onclick="AdminApp.clearSearchInput('report')" title="Clear Search"><i class="bi bi-x-circle-fill"></i></button>
                            <div class="bsis-autocomplete-dropdown shadow-lg" id="report-search-autocomplete"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="bsis-form-label small text-muted">Attendance Status</label>
                        <select id="report-status-filter" class="bsis-form-control" onchange="AdminApp.loadReports()">
                            <option value="">All Statuses (Present, Late, Absent, Override)</option>
                            <option value="present">Present (On-Time Only)</option>
                            <option value="late">Late Only</option>
                            <option value="absent">Absent Only (Auto-Fined)</option>
                            <option value="manual_override">Manual Override Only</option>
                        </select>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="bsis-form-label small text-muted">Year Level Filter</label>
                        <select id="report-year-filter" class="bsis-form-control" onchange="AdminApp.loadReports()">
                            <option value="">All Year Levels</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="bsis-form-label small text-muted">Section / Block Filter</label>
                        <input type="text" id="report-block-filter" class="bsis-form-control" placeholder="e.g. Block 1 ... Block 20" onkeyup="AdminApp.loadReports()">
                    </div>
                </div>
            </div>

            <!-- Event Attendance Analytics Metric Cards -->
            <div class="row g-3 mb-3" id="report-stats-cards">
                <div class="col-6 col-md-3">
                    <div class="bsis-card p-3 border-start border-4 border-primary">
                        <span class="text-muted small fw-bold">TOTAL SCANNED</span>
                        <h3 class="fw-bold text-primary mb-0" id="rep-stat-total">0</h3>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bsis-card p-3 border-start border-4 border-success">
                        <span class="text-muted small fw-bold">PRESENT (ON-TIME)</span>
                        <h3 class="fw-bold text-success mb-0" id="rep-stat-present">0</h3>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bsis-card p-3 border-start border-4 border-warning">
                        <span class="text-muted small fw-bold">LATE SCANS</span>
                        <h3 class="fw-bold text-warning mb-0" id="rep-stat-late">0</h3>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="bsis-card p-3 border-start border-4 border-danger">
                        <span class="text-muted small fw-bold">TOTAL FINES</span>
                        <h3 class="fw-bold text-danger mb-0" id="rep-stat-fines">₱0.00</h3>
                    </div>
                </div>
            </div>

            <!-- Attendance Records Table Preview -->
            <div class="bsis-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-primary mb-0"><i class="bi bi-table me-1"></i> Event Attendance Roster</h5>
                    <span id="report-records-count" class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.78rem;">0 Records</span>
                </div>
                <div class="table-responsive">
                    <table class="table bsis-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Student Full Name</th>
                                <th>Year & Block</th>
                                <th>Event Title</th>
                                <th>Time-In</th>
                                <th>Time-Out</th>
                                <th>Status</th>
                                <th>Distance</th>
                                <th>Fine</th>
                            </tr>
                        </thead>
                        <tbody id="report-attendance-table-body">
                            <tr>
                                <td colspan="10" style="padding: 0; border: none;">
                                    <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-id"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-text"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-btn"></div></div>
                                    <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-id"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-text"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-btn"></div></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- VIEW 9: SYSTEM AUDIT LOGS -->
        <section id="view-audit-logs" class="admin-view d-none">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Security Audit Activity Trail</h3>
                    <p class="text-muted small mb-0">System-wide immutable security logs, authorization attempts, and administrative actions</p>
                </div>
            </div>

            <!-- Audit Logs Search & Filter Toolbar -->
            <div class="bsis-card p-3 mb-3">
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="bsis-form-label small text-muted">Search Audit Trail</label>
                        <div class="bsis-autocomplete-container">
                            <input type="text" id="audit-log-search-input" class="bsis-form-control pe-4" placeholder="Search user, action, description, or IP..." oninput="AdminApp.handleAuditLogSearchDebounced()" autocomplete="off">
                            <button type="button" class="bsis-autocomplete-clear-btn" id="audit-log-search-clear" onclick="AdminApp.clearSearchInput('audit-log')" title="Clear Search"><i class="bi bi-x-circle-fill"></i></button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="bsis-form-label small text-muted">Filter by Action</label>
                        <select id="audit-log-action-filter" class="bsis-form-control" onchange="AdminApp.loadAuditLogs(1)">
                            <option value="">All Security Actions</option>
                            <option value="direct_device_reset">Direct Device Reset</option>
                            <option value="device_reset_approved">Device Reset Approved</option>
                            <option value="event_bypass_toggled">Emergency Bypass Toggled</option>
                            <option value="user_login">User Login</option>
                            <option value="failed_scan">Failed Attendance Scans</option>
                            <option value="manual_override">Manual Overrides</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bsis-card p-3 shadow-sm">
                <div class="table-responsive bsis-table-responsive">
                    <table class="table bsis-table table-hover align-middle mb-0" style="min-width: 920px;">
                        <thead>
                            <tr>
                                <th style="width: 45px;">#</th>
                                <th style="width: 175px;">User / Operator</th>
                                <th style="width: 170px;">Security Action</th>
                                <th style="min-width: 280px;">Activity Description & Reason</th>
                                <th style="width: 120px;">IP Address</th>
                                <th class="text-nowrap text-end" style="width: 145px;">Date & Timestamp</th>
                            </tr>
                        </thead>
                        <tbody id="audit-logs-table-body">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Audit Logs Pagination -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top" id="audit-logs-pagination-container">
                    <span class="text-muted small" id="audit-logs-page-info">Showing 0 of 0 audit records</span>
                    <nav aria-label="Audit logs pagination">
                        <ul class="pagination pagination-sm mb-0 gap-1" id="audit-logs-pagination-nav"></ul>
                    </nav>
                </div>
            </div>
        </section>

        <!-- VIEW 10: OFFLINE SYNC QUEUE -->
        <section id="view-sync-queue" class="admin-view d-none">
            <h3 class="fw-bold text-primary mb-4">Offline Attendance Synchronization Queue</h3>
            <div class="bsis-card p-3">
                <div class="table-responsive">
                    <table class="table bsis-table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Local ID</th>
                                <th>Event</th>
                                <th>Student Name</th>
                                <th>Status</th>
                                <th>Error Notes</th>
                                <th>Synced Date</th>
                            </tr>
                        </thead>
                        <tbody id="sync-queue-table-body">
                            <tr>
                                <td colspan="6" style="padding: 0; border: none;">
                                    <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-id"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-text"></div></div>
                                    <div class="bsis-skeleton-row"><div class="bsis-skeleton skel-id"></div><div class="bsis-skeleton skel-name"></div><div class="bsis-skeleton skel-badge"></div><div class="bsis-skeleton skel-text"></div></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- VIEW 11: DATABASE BACKUPS -->
        <section id="view-backups" class="admin-view d-none">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Database Backup & Recovery</h3>
                    <p class="text-muted small mb-0">Create, download, and restore system snapshots</p>
                </div>
                <div>
                    <button onclick="AdminApp.createBackup()" class="btn btn-bsis-primary text-nowrap">
                        <i class="bi bi-database-add me-1"></i> Create Backup
                    </button>
                </div>
            </div>

            <div class="bsis-card p-3 shadow-sm">
                <div class="table-responsive bsis-table-responsive">
                    <table class="table bsis-table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Backup Filename</th>
                                <th>File Size</th>
                                <th>Created Date & Time</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backups-table-body">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Backups Pagination -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top" id="backups-pagination-container">
                    <span class="text-muted small" id="backups-page-info">Showing 0 of 0 backup snapshots</span>
                    <nav aria-label="Backups pagination">
                        <ul class="pagination pagination-sm mb-0 gap-1" id="backups-pagination-nav"></ul>
                    </nav>
                </div>
            </div>
        </section>

    </main>

    <!-- MODAL 1: CREATE EVENT WITH INTERACTIVE LEAFLET MAP PICKER -->
    <div class="modal fade" id="modal-create-event" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0">
                <form onsubmit="AdminApp.handleCreateEvent(event)">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt"></i> Create Event Session & Map Location Picker</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="bsis-form-label">Event Title</label>
                            <input type="text" id="event-title" class="bsis-form-control" placeholder="e.g. BSIS Tech Summit 2026" required>
                        </div>

                        <!-- Interactive Leaflet Map Picker -->
                        <div class="mb-3">
                            <label class="bsis-form-label text-primary fw-bold"><i class="bi bi-map-fill"></i> Select Event Venue Location on Map</label>
                            <div id="create-event-map" style="height: 320px; width: 100%; border-radius: 12px; border: 2px solid var(--color-border); z-index: 1; background: #e5e3df;" class="mb-2"></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted"><i class="bi bi-info-circle"></i> Click anywhere on the map or drag the pin marker to position venue coordinates.</small>
                                <button type="button" onclick="AdminApp.detectCurrentLocationForEvent()" class="btn btn-sm btn-bsis-outline py-1"><i class="bi bi-geo-alt-fill text-danger"></i> Detect My Current GPS</button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="bsis-form-label">Description</label>
                            <textarea id="event-description" class="bsis-form-control" rows="2"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="bsis-form-label">Venue Name</label>
                                <input type="text" id="event-venue" class="bsis-form-control" value="Talibon Polytechnic College Gym" required>
                            </div>
                            <div class="col-md-6">
                                <label class="bsis-form-label">Allowed Perimeter Radius (Meters)</label>
                                <input type="number" id="event-radius" class="bsis-form-control" value="50" onchange="AdminApp.updateMapRadius(this.value)" onkeyup="AdminApp.updateMapRadius(this.value)" required>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="bsis-form-label">Selected Venue Latitude</label>
                                <input type="number" step="any" id="event-lat" class="bsis-form-control" value="10.14920000" required readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="bsis-form-label">Selected Venue Longitude</label>
                                <input type="number" step="any" id="event-lon" class="bsis-form-control" value="124.33120000" required readonly>
                            </div>
                        </div>
                        <!-- Event Date & Main Schedule -->
                        <div class="card p-3 mb-3 bg-light border-0" style="border-radius: var(--radius-sm);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="bsis-form-label fw-bold text-primary mb-0">
                                    <i class="bi bi-clock-history"></i> Event Date & Time Schedule
                                </label>
                                <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size: 0.72rem;">12-Hour AM/PM Dropdowns</span>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="bsis-form-label small mb-1"><i class="bi bi-calendar3 text-primary"></i> Event Date</label>
                                    <input type="date" id="event-date" class="bsis-form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="bsis-form-label small mb-1"><i class="bi bi-play-circle text-success"></i> Start Time</label>
                                    <div class="bsis-time-picker-control" data-target="event-start-time">
                                        <input type="hidden" id="event-start-time" value="08:00" required>
                                        <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                            <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8" selected>8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                        </select>
                                        <span class="time-colon">:</span>
                                        <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                            <option value="00" selected>00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                        </select>
                                        <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                        <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="bsis-form-label small mb-1"><i class="bi bi-stop-circle text-danger"></i> End Time</label>
                                    <div class="bsis-time-picker-control" data-target="event-end-time">
                                        <input type="hidden" id="event-end-time" value="17:00" required>
                                        <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                            <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5" selected>5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                        </select>
                                        <span class="time-colon">:</span>
                                        <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                            <option value="00" selected>00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                        </select>
                                        <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                        <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Human-Readable Duration Banner -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <div class="bsis-time-live-badge" id="event-time-live-badge">
                                    <i class="bi bi-clock-fill text-primary"></i> <span><strong>8:00 AM</strong> &mdash; <strong>5:00 PM</strong> <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Duration: 9 hrs</span></span>
                                </div>
                            </div>
                        </div>

                        <!-- Event Session Type Selector -->
                        <div class="card p-3 mb-3 border-primary bg-light" style="border-radius: var(--radius-sm);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="bsis-form-label fw-bold text-primary mb-0">
                                    <i class="bi bi-calendar2-range-fill"></i> Attendance Scanning Schedule Mode
                                </label>
                                <button type="button" class="btn btn-sm btn-bsis-accent py-1 px-2 fw-bold d-inline-flex align-items-center" onclick="AdminApp.autoFillSessionWindows('create')" style="font-size: 0.75rem;">
                                    <i class="bi bi-magic me-1"></i> Auto-Fill Windows
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="d-flex align-items-start gap-2 p-3 border rounded bg-white h-100 mb-0 shadow-sm" for="session-type-half-create" style="cursor: pointer;">
                                        <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="event_session_type_create" id="session-type-half-create" value="half_day" checked onchange="AdminApp.toggleSessionTypeUI('create')">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                                <span>☀️</span> <span>EVENT (2 Scans)</span>
                                            </div>
                                            <div class="text-muted small mt-1" style="font-size: 0.78rem; line-height: 1.35;">1 Time-In & 1 Time-Out (Single session or Whole-Day 2 scans)</div>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="d-flex align-items-start gap-2 p-3 border rounded bg-white h-100 mb-0 shadow-sm" for="session-type-whole-create" style="cursor: pointer;">
                                        <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="event_session_type_create" id="session-type-whole-create" value="whole_day" onchange="AdminApp.toggleSessionTypeUI('create')">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold text-primary d-flex align-items-center gap-1">
                                                <span>🌞</span> <span>EVENT (4 Scans)</span>
                                            </div>
                                            <div class="text-muted small mt-1" style="font-size: 0.78rem; line-height: 1.35;">Morning & Afternoon (AM In/Out & PM In/Out — 4 scanning windows)</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 2-SCAN SCANNING TIMEFRAMES (2 SLOTS) -->
                        <div id="create-halfday-windows-wrap" class="card p-3 mb-3 bg-light border-0" style="border-radius: var(--radius-sm);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="bsis-form-label fw-bold text-primary mb-0">
                                    <i class="bi bi-stopwatch-fill"></i> 2-Scan Timeframes (Time-In & Time-Out)
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.72rem;" onclick="AdminApp.autoFillSessionWindows('create')">
                                    <i class="bi bi-magic me-1"></i> Auto-Fill
                                </button>
                            </div>
                            
                            <!-- Time-In Window -->
                            <div class="bsis-window-slot-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-success py-1 px-2"><i class="bi bi-box-arrow-in-right me-1"></i> TIME-IN SCANNING WINDOW</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.70rem;" title="Clear Time-In Window" onclick="AdminApp.clearTimeField('event-checkin-start'); AdminApp.clearTimeField('event-checkin-end');">&times; Clear Window</button>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted me-1" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-checkin-start">
                                                <input type="hidden" id="event-checkin-start" value="07:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7" selected>7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted me-1" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-checkin-end">
                                                <input type="hidden" id="event-checkin-end" value="08:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8" selected>8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Time-Out Window -->
                            <div class="bsis-window-slot-card mb-0">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-info text-dark py-1 px-2"><i class="bi bi-box-arrow-right me-1"></i> TIME-OUT SCANNING WINDOW</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.70rem;" title="Clear Time-Out Window" onclick="AdminApp.clearTimeField('event-checkout-start'); AdminApp.clearTimeField('event-checkout-end');">&times; Clear Window</button>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted me-1" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-checkout-start">
                                                <input type="hidden" id="event-checkout-start" value="16:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4" selected>4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted me-1" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-checkout-end">
                                                <input type="hidden" id="event-checkout-end" value="17:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5" selected>5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4-SCAN SCANNING TIMEFRAMES (4 SLOTS: MORNING & AFTERNOON) -->
                        <div id="create-wholeday-windows-wrap" class="card p-3 mb-3 bg-light border-0" style="display: none; border-radius: var(--radius-sm);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="bsis-form-label fw-bold text-primary mb-0">
                                    <i class="bi bi-stopwatch-fill"></i> 4-Scan Windows (Morning AM & Afternoon PM)
                                </label>
                                <button type="button" class="btn btn-sm btn-bsis-accent py-1 px-2 fw-bold" style="font-size: 0.72rem;" onclick="AdminApp.autoFillSessionWindows('create')">
                                    <i class="bi bi-magic me-1"></i> Auto-Fill 4 Slots
                                </button>
                            </div>

                            <!-- AM Morning Session -->
                            <div class="bsis-window-slot-card mb-2">
                                <div class="fw-bold text-dark small mb-2 d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-sun text-warning me-1"></i> <strong>Morning Session (AM)</strong></span>
                                </div>
                                <div class="row g-2 mb-2 align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge bg-success py-2 w-100">AM TIME-IN</span>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-am-checkin-start">
                                                <input type="hidden" id="event-am-checkin-start" value="07:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7" selected>7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-am-checkin-end">
                                                <input type="hidden" id="event-am-checkin-end" value="08:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8" selected>8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Clear Slot" onclick="AdminApp.clearTimeField('event-am-checkin-start'); AdminApp.clearTimeField('event-am-checkin-end');">&times;</button>
                                    </div>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge bg-info text-dark py-2 w-100">AM TIME-OUT</span>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-am-checkout-start">
                                                <input type="hidden" id="event-am-checkout-start" value="11:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11" selected>11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-am-checkout-end">
                                                <input type="hidden" id="event-am-checkout-end" value="12:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12" selected>12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Clear Slot" onclick="AdminApp.clearTimeField('event-am-checkout-start'); AdminApp.clearTimeField('event-am-checkout-end');">&times;</button>
                                    </div>
                                </div>
                            </div>

                            <!-- PM Afternoon Session -->
                            <div class="bsis-window-slot-card mb-0">
                                <div class="fw-bold text-dark small mb-2 d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-sunset text-primary me-1"></i> <strong>Afternoon Session (PM)</strong></span>
                                </div>
                                <div class="row g-2 mb-2 align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge bg-success py-2 w-100">PM TIME-IN</span>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-pm-checkin-start">
                                                <input type="hidden" id="event-pm-checkin-start" value="13:00">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1" selected>1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00" selected>00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-pm-checkin-end">
                                                <input type="hidden" id="event-pm-checkin-end" value="13:45">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1" selected>1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45" selected>45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Clear Slot" onclick="AdminApp.clearTimeField('event-pm-checkin-start'); AdminApp.clearTimeField('event-pm-checkin-end');">&times;</button>
                                    </div>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge bg-info text-dark py-2 w-100">PM TIME-OUT</span>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-pm-checkout-start">
                                                <input type="hidden" id="event-pm-checkout-start" value="16:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4" selected>4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="event-pm-checkout-end">
                                                <input type="hidden" id="event-pm-checkout-end" value="17:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5" selected>5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Clear Slot" onclick="AdminApp.clearTimeField('event-pm-checkout-start'); AdminApp.clearTimeField('event-pm-checkout-end');">&times;</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Participants (Eligible Year Levels) -->
                        <div class="card p-3 mb-3 border shadow-none" style="border-radius: 12px; background: #F8FAFC; border-color: #E2E8F0 !important;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <label class="bsis-form-label fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-people-fill text-primary"></i> Target Participants / Eligible Year Levels
                                </label>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-bold" id="event-target-summary-create" style="font-size: 11px;">
                                    🎓 All BSIS Students (1st – 4th Year)
                                </span>
                            </div>
                            <div class="text-muted small mb-2" style="font-size: 12px;">
                                Click year levels to select who can view and attend this event:
                            </div>
                            <div class="d-flex flex-wrap gap-2" id="event-target-pills-create">
                                <button type="button" class="target-year-pill pill-all-btn active" id="pill-all-create" onclick="AdminApp.selectTargetYear('All', 'create')">
                                    <i class="bi bi-mortarboard-fill me-1.5"></i> All Years (General)
                                </button>
                                <button type="button" class="target-year-pill active" id="pill-yr1-create" data-year="1st Year" onclick="AdminApp.selectTargetYear('1st Year', 'create')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 1st Year
                                </button>
                                <button type="button" class="target-year-pill active" id="pill-yr2-create" data-year="2nd Year" onclick="AdminApp.selectTargetYear('2nd Year', 'create')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 2nd Year
                                </button>
                                <button type="button" class="target-year-pill active" id="pill-yr3-create" data-year="3rd Year" onclick="AdminApp.selectTargetYear('3rd Year', 'create')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 3rd Year
                                </button>
                                <button type="button" class="target-year-pill active" id="pill-yr4-create" data-year="4th Year" onclick="AdminApp.selectTargetYear('4th Year', 'create')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 4th Year
                                </button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="bsis-form-label" id="event-fine-label"><i class="bi bi-cash"></i> Fine Amount Per Missed/Late Slot (PHP)</label>
                                <input type="number" step="any" id="event-fine" class="bsis-form-control" value="20.00" required>
                                <small class="text-muted" id="event-fine-hint">Assessed for each missed or late scanning slot</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-bsis-primary fw-bold">Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- MODAL: EDIT EVENT SESSION -->
    <div class="modal fade" id="modal-edit-event" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0">
                <form onsubmit="AdminApp.handleUpdateEvent(event)">
                    <input type="hidden" id="edit-event-id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square"></i> Edit Event Session</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="bsis-form-label">Event Title</label>
                            <input type="text" id="edit-event-title" class="bsis-form-control" required>
                        </div>

                        <!-- Interactive Leaflet Map Picker for Edit -->
                        <div class="mb-3">
                            <label class="bsis-form-label text-primary fw-bold"><i class="bi bi-map-fill"></i> Update Event Venue Location on Map</label>
                            <div id="edit-event-map" style="height: 320px; border-radius: 12px; border: 2px solid var(--color-border); z-index: 1;" class="mb-2"></div>
                            <small class="text-muted"><i class="bi bi-info-circle"></i> Click anywhere on the map or drag the pin marker to update venue coordinates.</small>
                        </div>

                        <div class="mb-3">
                            <label class="bsis-form-label">Description</label>
                            <textarea id="edit-event-description" class="bsis-form-control" rows="2"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="bsis-form-label">Venue Name</label>
                                <input type="text" id="edit-event-venue" class="bsis-form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="bsis-form-label">Allowed Perimeter Radius (Meters)</label>
                                <input type="number" id="edit-event-radius" class="bsis-form-control" value="50" onchange="AdminApp.updateEditMapRadius(this.value)" onkeyup="AdminApp.updateEditMapRadius(this.value)" required>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="bsis-form-label">Venue Latitude</label>
                                <input type="number" step="any" id="edit-event-lat" class="bsis-form-control" required readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="bsis-form-label">Venue Longitude</label>
                                <input type="number" step="any" id="edit-event-lon" class="bsis-form-control" required readonly>
                            </div>
                        </div>
                        <!-- Edit Event Date & Main Schedule -->
                        <div class="card p-3 mb-3 bg-light border-0" style="border-radius: var(--radius-sm);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="bsis-form-label fw-bold text-primary mb-0">
                                    <i class="bi bi-clock-history"></i> Event Date & Time Schedule
                                </label>
                                <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size: 0.72rem;">12-Hour AM/PM Dropdowns</span>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-md-4">
                                    <label class="bsis-form-label small mb-1"><i class="bi bi-calendar3 text-primary"></i> Event Date</label>
                                    <input type="date" id="edit-event-date" class="bsis-form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="bsis-form-label small mb-1"><i class="bi bi-play-circle text-success"></i> Start Time</label>
                                    <div class="bsis-time-picker-control" data-target="edit-event-start-time">
                                        <input type="hidden" id="edit-event-start-time" value="08:00" required>
                                        <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                            <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8" selected>8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                        </select>
                                        <span class="time-colon">:</span>
                                        <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                            <option value="00" selected>00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                        </select>
                                        <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                        <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="bsis-form-label small mb-1"><i class="bi bi-stop-circle text-danger"></i> End Time</label>
                                    <div class="bsis-time-picker-control" data-target="edit-event-end-time">
                                        <input type="hidden" id="edit-event-end-time" value="17:00" required>
                                        <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                            <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5" selected>5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                        </select>
                                        <span class="time-colon">:</span>
                                        <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                            <option value="00" selected>00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                        </select>
                                        <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                        <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Live Human-Readable Duration Banner for Edit -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <div class="bsis-time-live-badge" id="edit-event-time-live-badge">
                                    <i class="bi bi-clock-fill text-primary"></i> <span><strong>8:00 AM</strong> &mdash; <strong>5:00 PM</strong> <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Duration: 9 hrs</span></span>
                                </div>
                            </div>
                        </div>

                        <!-- Event Session Type Selector for Edit -->
                        <div class="card p-3 mb-3 border-primary bg-light" style="border-radius: var(--radius-sm);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="bsis-form-label fw-bold text-primary mb-0">
                                    <i class="bi bi-calendar2-range-fill"></i> Attendance Scanning Schedule Mode
                                </label>
                                <button type="button" class="btn btn-sm btn-bsis-accent py-1 px-2 fw-bold d-inline-flex align-items-center" onclick="AdminApp.autoFillSessionWindows('edit')" style="font-size: 0.75rem;">
                                    <i class="bi bi-magic me-1"></i> Auto-Fill Windows
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="d-flex align-items-start gap-2 p-3 border rounded bg-white h-100 mb-0 shadow-sm" for="edit-session-type-half" style="cursor: pointer;">
                                        <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="edit_event_session_type" id="edit-session-type-half" value="half_day" checked onchange="AdminApp.toggleSessionTypeUI('edit')">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                                <span>☀️</span> <span>EVENT (2 Scans)</span>
                                            </div>
                                            <div class="text-muted small mt-1" style="font-size: 0.78rem; line-height: 1.35;">1 Time-In & 1 Time-Out (Single session or Whole-Day 2 scans)</div>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-6">
                                    <label class="d-flex align-items-start gap-2 p-3 border rounded bg-white h-100 mb-0 shadow-sm" for="edit-session-type-whole" style="cursor: pointer;">
                                        <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="edit_event_session_type" id="edit-session-type-whole" value="whole_day" onchange="AdminApp.toggleSessionTypeUI('edit')">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold text-primary d-flex align-items-center gap-1">
                                                <span>🌞</span> <span>EVENT (4 Scans)</span>
                                            </div>
                                            <div class="text-muted small mt-1" style="font-size: 0.78rem; line-height: 1.35;">Morning & Afternoon (AM In/Out & PM In/Out — 4 scanning windows)</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 2-SCAN SCANNING TIMEFRAMES (EDIT) -->
                        <div id="edit-halfday-windows-wrap" class="card p-3 mb-3 bg-light border-0" style="border-radius: var(--radius-sm);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="bsis-form-label fw-bold text-primary mb-0">
                                    <i class="bi bi-stopwatch-fill"></i> 2-Scan Timeframes (Time-In & Time-Out)
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.72rem;" onclick="AdminApp.autoFillSessionWindows('edit')">
                                    <i class="bi bi-magic me-1"></i> Auto-Fill
                                </button>
                            </div>
                            
                            <!-- Time-In Window -->
                            <div class="bsis-window-slot-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-success py-1 px-2"><i class="bi bi-box-arrow-in-right me-1"></i> TIME-IN SCANNING WINDOW</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.70rem;" title="Clear Time-In Window" onclick="AdminApp.clearTimeField('edit-event-checkin-start'); AdminApp.clearTimeField('edit-event-checkin-end');">&times; Clear Window</button>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted me-1" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-checkin-start">
                                                <input type="hidden" id="edit-event-checkin-start" value="07:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7" selected>7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted me-1" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-checkin-end">
                                                <input type="hidden" id="edit-event-checkin-end" value="08:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8" selected>8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Time-Out Window -->
                            <div class="bsis-window-slot-card mb-0">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-info text-dark py-1 px-2"><i class="bi bi-box-arrow-right me-1"></i> TIME-OUT SCANNING WINDOW</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.70rem;" title="Clear Time-Out Window" onclick="AdminApp.clearTimeField('edit-event-checkout-start'); AdminApp.clearTimeField('edit-event-checkout-end');">&times; Clear Window</button>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted me-1" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-checkout-start">
                                                <input type="hidden" id="edit-event-checkout-start" value="16:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4" selected>4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted me-1" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-checkout-end">
                                                <input type="hidden" id="edit-event-checkout-end" value="17:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5" selected>5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4-SCAN SCANNING TIMEFRAMES (EDIT - 4 SLOTS) -->
                        <div id="edit-wholeday-windows-wrap" class="card p-3 mb-3 bg-light border-0" style="display: none; border-radius: var(--radius-sm);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="bsis-form-label fw-bold text-primary mb-0">
                                    <i class="bi bi-stopwatch-fill"></i> 4-Scan Windows (Morning AM & Afternoon PM)
                                </label>
                                <button type="button" class="btn btn-sm btn-bsis-accent py-1 px-2 fw-bold" style="font-size: 0.72rem;" onclick="AdminApp.autoFillSessionWindows('edit')">
                                    <i class="bi bi-magic me-1"></i> Auto-Fill 4 Slots
                                </button>
                            </div>

                            <!-- AM Morning Session -->
                            <div class="bsis-window-slot-card mb-2">
                                <div class="fw-bold text-dark small mb-2 d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-sun text-warning me-1"></i> <strong>Morning Session (AM)</strong></span>
                                </div>
                                <div class="row g-2 mb-2 align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge bg-success py-2 w-100">AM TIME-IN</span>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-am-checkin-start">
                                                <input type="hidden" id="edit-event-am-checkin-start" value="07:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7" selected>7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-am-checkin-end">
                                                <input type="hidden" id="edit-event-am-checkin-end" value="08:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8" selected>8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Clear Slot" onclick="AdminApp.clearTimeField('edit-event-am-checkin-start'); AdminApp.clearTimeField('edit-event-am-checkin-end');">&times;</button>
                                    </div>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge bg-info text-dark py-2 w-100">AM TIME-OUT</span>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-am-checkout-start">
                                                <input type="hidden" id="edit-event-am-checkout-start" value="11:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11" selected>11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn active btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-am-checkout-end">
                                                <input type="hidden" id="edit-event-am-checkout-end" value="12:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12" selected>12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Clear Slot" onclick="AdminApp.clearTimeField('edit-event-am-checkout-start'); AdminApp.clearTimeField('edit-event-am-checkout-end');">&times;</button>
                                    </div>
                                </div>
                            </div>

                            <!-- PM Afternoon Session -->
                            <div class="bsis-window-slot-card mb-0">
                                <div class="fw-bold text-dark small mb-2 d-flex align-items-center justify-content-between">
                                    <span><i class="bi bi-sunset text-primary me-1"></i> <strong>Afternoon Session (PM)</strong></span>
                                </div>
                                <div class="row g-2 mb-2 align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge bg-success py-2 w-100">PM TIME-IN</span>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-pm-checkin-start">
                                                <input type="hidden" id="edit-event-pm-checkin-start" value="13:00">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1" selected>1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00" selected>00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-pm-checkin-end">
                                                <input type="hidden" id="edit-event-pm-checkin-end" value="13:45">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1" selected>1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30">30</option><option value="35">35</option><option value="40">40</option><option value="45" selected>45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Clear Slot" onclick="AdminApp.clearTimeField('edit-event-pm-checkin-start'); AdminApp.clearTimeField('edit-event-pm-checkin-end');">&times;</button>
                                    </div>
                                </div>
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-2">
                                        <span class="badge bg-info text-dark py-2 w-100">PM TIME-OUT</span>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">From:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-pm-checkout-start">
                                                <input type="hidden" id="edit-event-pm-checkout-start" value="16:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4" selected>4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="small fw-bold text-muted" style="min-width: 32px;">Until:</span>
                                            <div class="bsis-time-picker-control flex-grow-1" data-target="edit-event-pm-checkout-end">
                                                <input type="hidden" id="edit-event-pm-checkout-end" value="17:30">
                                                <select class="time-select-hour" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5" selected>5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                                                </select>
                                                <span class="time-colon">:</span>
                                                <select class="time-select-min" onchange="AdminApp.syncTimePicker(this)">
                                                    <option value="00">00</option><option value="05">05</option><option value="10">10</option><option value="15">15</option><option value="20">20</option><option value="25">25</option><option value="30" selected>30</option><option value="35">35</option><option value="40">40</option><option value="45">45</option><option value="50">50</option><option value="55">55</option>
                                                </select>
                                                <button type="button" class="time-ampm-btn btn-ampm-am" onclick="AdminApp.setAmPm(this, 'AM')">AM</button>
                                                <button type="button" class="time-ampm-btn active btn-ampm-pm" onclick="AdminApp.setAmPm(this, 'PM')">PM</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Clear Slot" onclick="AdminApp.clearTimeField('edit-event-pm-checkout-start'); AdminApp.clearTimeField('edit-event-pm-checkout-end');">&times;</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target Participants for Edit (Eligible Year Levels) -->
                        <div class="card p-3 mb-3 border shadow-none" style="border-radius: 12px; background: #F8FAFC; border-color: #E2E8F0 !important;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <label class="bsis-form-label fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-people-fill text-primary"></i> Target Participants / Eligible Year Levels
                                </label>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-bold" id="edit-event-target-summary" style="font-size: 11px;">
                                    🎓 All BSIS Students (1st – 4th Year)
                                </span>
                            </div>
                            <div class="text-muted small mb-2" style="font-size: 12px;">
                                Click year levels to select who can view and attend this event:
                            </div>
                            <div class="d-flex flex-wrap gap-2" id="event-target-pills-edit">
                                <button type="button" class="target-year-pill pill-all-btn active" id="pill-all-edit" onclick="AdminApp.selectTargetYear('All', 'edit')">
                                    <i class="bi bi-mortarboard-fill me-1.5"></i> All Years (General)
                                </button>
                                <button type="button" class="target-year-pill active" id="pill-yr1-edit" data-year="1st Year" onclick="AdminApp.selectTargetYear('1st Year', 'edit')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 1st Year
                                </button>
                                <button type="button" class="target-year-pill active" id="pill-yr2-edit" data-year="2nd Year" onclick="AdminApp.selectTargetYear('2nd Year', 'edit')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 2nd Year
                                </button>
                                <button type="button" class="target-year-pill active" id="pill-yr3-edit" data-year="3rd Year" onclick="AdminApp.selectTargetYear('3rd Year', 'edit')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 3rd Year
                                </button>
                                <button type="button" class="target-year-pill active" id="pill-yr4-edit" data-year="4th Year" onclick="AdminApp.selectTargetYear('4th Year', 'edit')">
                                    <i class="bi bi-check-circle-fill me-1"></i> 4th Year
                                </button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="bsis-form-label"><i class="bi bi-cash"></i> Late Fine Amount (PHP)</label>
                                <input type="number" step="any" id="edit-event-fine" class="bsis-form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-bsis-primary fw-bold">Update Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: USER / STUDENT PROVISIONING WITH ROLE, YEAR & BLOCK -->
    <div class="modal fade" id="modal-create-student" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form onsubmit="AdminApp.handleCreateStudent(event)">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Register User / Student Account</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="bsis-form-label fw-bold">Account Role</label>
                            <select id="student-role" class="bsis-form-control fw-semibold">
                                <option value="student">Student (Standard)</option>
                                <option value="event_staff">Event Staff (Student Officer / Scanner Operator)</option>
                                <option value="admin">Administrator</option>
                            </select>
                            <small class="text-muted">Student Officers assigned as Event Staff can operate dynamic QRs and attendance feeds.</small>
                        </div>

                        <div class="mb-3">
                            <label class="bsis-form-label fw-bold">Student / Staff ID Number</label>
                            <input type="text" id="student-number" class="bsis-form-control" placeholder="e.g. 2024-00002" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="bsis-form-label">First Name</label>
                                <input type="text" id="student-fname" class="bsis-form-control" required>
                            </div>
                            <div class="col-4">
                                <label class="bsis-form-label">Middle Name</label>
                                <input type="text" id="student-mname" class="bsis-form-control">
                            </div>
                            <div class="col-4">
                                <label class="bsis-form-label">Last Name</label>
                                <input type="text" id="student-lname" class="bsis-form-control" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="bsis-form-label">Year Level</label>
                                <select id="student-year-level" class="bsis-form-control">
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                    <option value="N/A">N/A (Staff Only)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="bsis-form-label">Block</label>
                                <select id="student-section-block" class="bsis-form-control">
                                    <option value="Block 1">Block 1</option>
                                    <option value="Block 2">Block 2</option>
                                    <option value="Block 3">Block 3</option>
                                    <option value="Block 4">Block 4</option>
                                    <option value="Block 5">Block 5</option>
                                    <option value="Block 6">Block 6</option>
                                    <option value="Block 7">Block 7</option>
                                    <option value="Block 8">Block 8</option>
                                    <option value="Block 9">Block 9</option>
                                    <option value="Block 10">Block 10</option>
                                    <option value="Block 11">Block 11</option>
                                    <option value="Block 12">Block 12</option>
                                    <option value="Block 13">Block 13</option>
                                    <option value="Block 14">Block 14</option>
                                    <option value="Block 15">Block 15</option>
                                    <option value="Block 16">Block 16</option>
                                    <option value="Block 17">Block 17</option>
                                    <option value="Block 18">Block 18</option>
                                    <option value="Block 19">Block 19</option>
                                    <option value="Block 20">Block 20</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="bsis-form-label">Email Address (Gmail, Yahoo, or Institutional)</label>
                            <input type="email" id="student-email" class="bsis-form-control" placeholder="e.g. officer@gmail.com" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-bsis-primary fw-bold"><i class="bi bi-check2-circle me-1"></i> Register Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 3: BATCH CSV IMPORT -->
    <div class="modal fade" id="modal-csv-import" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card); overflow: hidden;">
                <form onsubmit="AdminApp.handleCsvImport(event)">
                    <div class="modal-header bg-primary text-white py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center bg-white text-primary" style="width: 36px; height: 36px;">
                                <i class="bi bi-file-earmark-spreadsheet-fill" style="font-size: 1.15rem;"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">Batch CSV Student Provisioning</h5>
                                <small class="text-light" style="opacity: 0.85;">Register multiple student profiles simultaneously</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3 pb-3 border-bottom">
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-info-circle text-primary me-1"></i> Required CSV Format</h6>
                                <p class="text-muted small mb-0">Your CSV file must include these 7 column headers in order:</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success fw-bold text-nowrap d-inline-flex align-items-center gap-1 shadow-sm" onclick="AdminApp.downloadCsvTemplate()">
                                <i class="bi bi-download"></i> Download Sample CSV
                            </button>
                        </div>

                        <!-- Column Guide Table -->
                        <div class="table-responsive mb-3 border rounded">
                            <table class="table table-sm table-striped small mb-0" style="font-size: 0.78rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Header Name</th>
                                        <th>Required?</th>
                                        <th>Example Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>1</td><td><code>student_number</code></td><td><span class="badge bg-danger">Required</span></td><td>2024-00101</td></tr>
                                    <tr><td>2</td><td><code>first_name</code></td><td><span class="badge bg-danger">Required</span></td><td>Juan</td></tr>
                                    <tr><td>3</td><td><code>middle_name</code></td><td><span class="badge bg-secondary">Optional</span></td><td>Dela</td></tr>
                                    <tr><td>4</td><td><code>last_name</code></td><td><span class="badge bg-danger">Required</span></td><td>Cruz</td></tr>
                                    <tr><td>5</td><td><code>email</code></td><td><span class="badge bg-danger">Required</span></td><td>juan.cruz@tpc.edu.ph</td></tr>
                                    <tr><td>6</td><td><code>year_level</code></td><td><span class="badge bg-secondary">Optional</span></td><td>1st Year / 2nd Year / 3rd Year / 4th Year</td></tr>
                                    <tr><td>7</td><td><code>block</code></td><td><span class="badge bg-secondary">Optional</span></td><td>Block 1 ... Block 20</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-3">
                            <label class="bsis-form-label fw-bold"><i class="bi bi-upload me-1"></i> Select CSV File</label>
                            <input type="file" id="csv-file-input" accept=".csv,text/csv" class="bsis-form-control" required>
                            <small class="text-muted"><i class="bi bi-shield-check text-success"></i> Each student will be provisioned with a secure 48-hour onboarding email token.</small>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between bg-light py-2 px-4">
                        <button type="button" class="btn btn-bsis-outline btn-sm fw-semibold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-bsis-primary btn-sm fw-bold px-4"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Start Batch Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 4: MANUAL OVERRIDE -->
    <div class="modal fade" id="modal-manual-override" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form onsubmit="AdminApp.handleManualOverride(event)">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-person-check-fill me-2"></i> Manual Staff Attendance Override</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small mb-3">Manually validate attendance for a student who is unable to scan (e.g. dead battery, damaged camera, or offline device).</p>
                        
                        <div class="mb-3">
                            <label class="bsis-form-label fw-bold"><i class="bi bi-calendar-event me-1"></i> Target Event Session</label>
                            <select id="override-event-select" class="bsis-form-control" required>
                                <option value="">Select Active Event...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="bsis-form-label fw-bold"><i class="bi bi-person-badge me-1"></i> Student ID Number or Email</label>
                            <input type="text" id="override-student-input" class="bsis-form-control" placeholder="e.g. 2024-00001 or student@tpc.edu.ph" required>
                            <small class="text-muted">Enter the student's ID number or email.</small>
                        </div>

                        <div class="mb-3">
                            <label class="bsis-form-label fw-bold"><i class="bi bi-chat-left-text me-1"></i> Override Reason</label>
                            
                            <!-- Quick Reason Buttons -->
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="document.getElementById('override-reason').value='Mobile device battery depleted during event.'">📱 Low Battery</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="document.getElementById('override-reason').value='Device camera lens damaged / unable to decode QR.'">📷 Camera Damaged</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="document.getElementById('override-reason').value='Temporary device binding issue, student verified present.'">🔄 Device Issue</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="document.getElementById('override-reason').value='Student verified present at venue by event staff.'">✓ Verified Present</button>
                            </div>

                            <textarea id="override-reason" class="bsis-form-control" rows="2" placeholder="Explain why the student is being checked in manually..." required></textarea>
                        </div>

                        <div class="mb-2">
                            <label class="bsis-form-label fw-bold"><i class="bi bi-tag me-1"></i> Attendance Status</label>
                            <select id="override-status-select" class="bsis-form-control">
                                <option value="manual_override">Manual Override (Default)</option>
                                <option value="present">Present (On-Time / No Fine)</option>
                                <option value="late">Late (With Fine)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between bg-light">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="btn-submit-override" class="btn btn-bsis-primary fw-bold px-3">
                            <i class="bi bi-check2-circle me-1"></i> Record Override Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 5: QR REFRESH INTERVAL SETTINGS -->
    <div class="modal fade" id="modal-qr-settings" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0">
                <form onsubmit="AdminApp.handleSaveQrSettings(event)">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-clock-history"></i> Dynamic QR Expiration Settings</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small mb-3">Configure how many seconds a dynamic attendance QR code remains valid before refreshing to prevent screenshot sharing.</p>
                        <div class="mb-3">
                            <label class="bsis-form-label">QR Token Expiration Interval (Seconds)</label>
                            <div class="input-group">
                                <input type="number" min="5" max="300" id="setting-qr-interval-input" class="bsis-form-control" placeholder="20" value="20" required>
                                <span class="input-group-text bg-light text-muted fw-bold">seconds</span>
                            </div>
                            <small class="text-muted">Recommended: 10s to 60s (Default: 20 seconds).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-bsis-primary fw-bold">Save QR Interval</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: EMERGENCY BYPASS SECURITY PASSWORD VERIFICATION -->
    <!-- MODAL: EMERGENCY BYPASS SECURITY PASSWORD VERIFICATION (WITH DURATION DROPDOWN & REASON) -->
    <div class="modal fade" id="modal-bypass-confirm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-md); overflow: hidden;">
                <form onsubmit="AdminApp.handleBypassAuthSubmit(event)">
                    <div class="modal-header bg-warning text-dark py-3 px-4">
                        <h5 class="modal-title fw-bold fs-6 mb-0"><i class="bi bi-shield-lock-fill me-2"></i> Authorize Emergency Bypass</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3 p-3 bg-light rounded border">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.3rem;">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;">Temporary Emergency Window</h6>
                                    <p class="text-muted small mb-0" style="font-size: 0.78rem;">Accepts all scans with ₱0.00 fines during the active timer.</p>
                                </div>
                            </div>
                            <span id="bypass-quota-badge" class="badge bg-white text-dark border shadow-sm px-2 py-1" style="font-size: 0.72rem;">Use 1 of 2</span>
                        </div>

                        <!-- Duration Selector Dropdown -->
                        <div class="mb-3">
                            <label class="bsis-form-label fw-bold text-dark small"><i class="bi bi-clock-history me-1 text-primary"></i> Auto-Expiry Timer Duration</label>
                            <select id="bypass-duration-minutes" class="bsis-form-control fw-semibold">
                                <option value="15">⏱️ 15 Minutes (Rain delay / quick line clearance)</option>
                                <option value="20" selected>⏱️ 20 Minutes (Recommended - Standard Emergency)</option>
                                <option value="30">⏱️ 30 Minutes (Major hardware / hall delay)</option>
                                <option value="60">⏱️ 1 Hour (Extended event emergency / wide disruption)</option>
                            </select>
                            <small class="text-muted" style="font-size: 0.75rem;">Bypass automatically closes and returns to regular window rules when timer hits 0:00.</small>
                        </div>

                        <!-- Mandatory Reason Input -->
                        <div class="mb-3">
                            <label class="bsis-form-label fw-bold text-dark small"><i class="bi bi-chat-left-text-fill me-1 text-primary"></i> Reason for Emergency Bypass</label>
                            <input type="text" id="bypass-auth-reason" class="bsis-form-control" placeholder="e.g. Wi-Fi reconnection, projector reboot, rain delay" required autocomplete="off">
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.70rem;" onclick="document.getElementById('bypass-auth-reason').value='Wi-Fi network reconnection glitch.'">📶 Wi-Fi Glitch</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.70rem;" onclick="document.getElementById('bypass-auth-reason').value='Projector reboot / display delay at gate.'">📽️ Projector Delay</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.70rem;" onclick="document.getElementById('bypass-auth-reason').value='Weather / sudden rain delay for students.'">🌧️ Rain Delay</button>
                            </div>
                        </div>

                        <!-- Password Confirmation -->
                        <div class="mb-2">
                            <label class="bsis-form-label fw-bold text-dark small"><i class="bi bi-key-fill me-1 text-primary"></i> Confirm Your Account Password</label>
                            <div class="position-relative">
                                <input type="password" id="bypass-auth-password" class="bsis-form-control pe-5" placeholder="Enter your account password..." required autocomplete="current-password">
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted text-decoration-none pe-3" onclick="AdminApp.toggleBypassPasswordVisibility(this)" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div id="bypass-auth-error" class="text-danger small fw-semibold mt-2 d-none"></div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="btn-submit-bypass-auth" class="btn btn-warning btn-sm px-4 fw-bold text-dark">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Authorize & Activate Timer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: EDIT USER & STUDENT PROFILE -->
    <div class="modal fade" id="modal-edit-user" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0">
                <form onsubmit="AdminApp.handleUpdateUser(event)">
                    <input type="hidden" id="edit-user-id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square"></i> Edit User Information</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="bsis-form-label">Student ID Number</label>
                            <input type="text" id="edit-user-snumber" class="bsis-form-control" placeholder="e.g. 2024-00002">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="bsis-form-label">First Name</label>
                                <input type="text" id="edit-user-fname" class="bsis-form-control" required>
                            </div>
                            <div class="col-4">
                                <label class="bsis-form-label">Middle Name</label>
                                <input type="text" id="edit-user-mname" class="bsis-form-control">
                            </div>
                            <div class="col-4">
                                <label class="bsis-form-label">Last Name</label>
                                <input type="text" id="edit-user-lname" class="bsis-form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="bsis-form-label">Email Address</label>
                            <input type="email" id="edit-user-email" class="bsis-form-control" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="bsis-form-label">Year Level</label>
                                <select id="edit-user-year" class="bsis-form-control">
                                    <option value="">N/A</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="bsis-form-label">Block</label>
                                <select id="edit-user-block" class="bsis-form-control">
                                    <option value="">N/A</option>
                                    <option value="Block 1">Block 1</option>
                                    <option value="Block 2">Block 2</option>
                                    <option value="Block 3">Block 3</option>
                                    <option value="Block 4">Block 4</option>
                                    <option value="Block 5">Block 5</option>
                                    <option value="Block 6">Block 6</option>
                                    <option value="Block 7">Block 7</option>
                                    <option value="Block 8">Block 8</option>
                                    <option value="Block 9">Block 9</option>
                                    <option value="Block 10">Block 10</option>
                                    <option value="Block 11">Block 11</option>
                                    <option value="Block 12">Block 12</option>
                                    <option value="Block 13">Block 13</option>
                                    <option value="Block 14">Block 14</option>
                                    <option value="Block 15">Block 15</option>
                                    <option value="Block 16">Block 16</option>
                                    <option value="Block 17">Block 17</option>
                                    <option value="Block 18">Block 18</option>
                                    <option value="Block 19">Block 19</option>
                                    <option value="Block 20">Block 20</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="bsis-form-label">Account Role</label>
                                <select id="edit-user-role" class="bsis-form-control">
                                    <option value="student">Student</option>
                                    <option value="event_staff">Event Staff</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="bsis-form-label">Account Status</label>
                                <select id="edit-user-status" class="bsis-form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="pending_onboarding">Pending Onboarding</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-bsis-primary fw-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: SECURE DELETE EVENT WITH ADMIN PASSWORD -->
    <div class="modal fade" id="modal-confirm-delete-event" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form onsubmit="AdminApp.confirmExecuteDeleteEvent(event)">
                    <input type="hidden" id="delete-event-mode" value="single">
                    <input type="hidden" id="delete-event-target-id" value="">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Event Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="text-center mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center bg-danger-subtle text-danger rounded-circle mb-3" style="width: 64px; height: 64px;">
                                <i class="bi bi-trash-fill fs-2"></i>
                            </div>
                            <h5 class="fw-bold text-danger mb-1" id="delete-event-prompt-title">Delete Event Session</h5>
                            <p class="text-muted small mb-0" id="delete-event-prompt-desc">Are you sure you want to permanently delete this event?</p>
                        </div>

                        <div class="alert alert-warning py-2 px-3 mb-3" style="font-size: 0.82rem;">
                            <i class="bi bi-shield-lock-fill me-1"></i> <strong>Permanent Action:</strong> All attendance logs, scans, and fines associated with this event will be erased.
                        </div>

                        <div class="mb-3 text-start">
                            <label class="bsis-form-label fw-bold">Admin Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="delete-event-admin-password" class="bsis-form-control" placeholder="Enter your administrator password" required>
                                <button class="password-toggle-btn" type="button" onclick="AdminApp.togglePasswordVisibility('delete-event-admin-password', this)" title="Show / Hide Password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between bg-light">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="btn-execute-delete-event" class="btn btn-danger fw-bold px-3">
                            <i class="bi bi-trash-fill me-1"></i> Confirm Deletion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: SECURE COMPLETE / CONCLUDE EVENT WITH PASSWORD -->
    <div class="modal fade" id="modal-confirm-complete-event" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form onsubmit="AdminApp.confirmExecuteCompleteEvent(event)">
                    <input type="hidden" id="complete-event-target-id" value="">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title fw-bold"><i class="bi bi-shield-lock-fill me-2"></i> Conclude Event & Process Absences</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="text-center mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle mb-3" style="width: 64px; height: 64px;">
                                <i class="bi bi-flag-fill fs-2"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-1" id="complete-event-prompt-title">Mark Event as Completed</h5>
                            <p class="text-muted small mb-0" id="complete-event-prompt-desc">Conclude attendance scanning session and finalize attendance records.</p>
                        </div>

                        <div class="alert alert-warning py-2 px-3 mb-3" style="font-size: 0.82rem;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Automated Absence & Fine Processing:</strong> All eligible BSIS students who did not record required scans will be automatically marked <strong>ABSENT</strong> and non-attendance fines will be generated.
                        </div>

                        <div id="complete-event-error" class="alert alert-danger py-2 px-3 mb-3 d-none" style="font-size: 0.82rem;"></div>

                        <div class="mb-3 text-start">
                            <label class="bsis-form-label fw-bold">Enter Your Account Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="complete-event-password" class="bsis-form-control" placeholder="Enter your password to authorize" required>
                                <button class="password-toggle-btn" type="button" onclick="AdminApp.togglePasswordVisibility('complete-event-password', this)" title="Show / Hide Password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between bg-light">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="btn-execute-complete-event" class="btn btn-warning fw-bold px-3">
                            <i class="bi bi-check2-circle me-1"></i> Authorize & Conclude Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: SECURE DROP / DELETE USER WITH ADMIN PASSWORD -->
    <div class="modal fade" id="modal-confirm-delete-user" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form onsubmit="AdminApp.confirmExecuteDelete(event)">
                    <input type="hidden" id="delete-target-mode" value="single">
                    <input type="hidden" id="delete-target-id" value="">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> Confirm User Deletion</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="text-center mb-3">
                            <i class="bi bi-trash text-danger" style="font-size: 3rem;"></i>
                            <h5 class="fw-bold mt-2" id="delete-user-prompt-title">Drop User Account</h5>
                            <p class="text-muted small" id="delete-user-prompt-desc">Are you sure you want to permanently delete this user account?</p>
                        </div>

                        <div class="alert alert-warning py-2 px-3 mb-3" style="font-size: 0.82rem;">
                            <i class="bi bi-shield-lock-fill"></i> <strong>Security Verification:</strong> Enter your Administrator Password to authorize this deletion.
                        </div>

                        <div class="mb-3 text-start">
                            <label class="bsis-form-label fw-bold">Admin Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="delete-admin-password" class="bsis-form-control" placeholder="Enter your administrator password" required>
                                <button class="password-toggle-btn" type="button" onclick="AdminApp.togglePasswordVisibility('delete-admin-password', this)" title="Show / Hide Password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-bsis-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="btn-execute-delete" class="btn btn-danger fw-bold px-3"><i class="bi bi-trash-fill"></i> Confirm Deletion</button>
                    </div>
                </form>
            </div>
        </div>
    <!-- MODAL: EVENT ACTION HUB & QUICK ACTIONS -->
    <div class="modal fade" id="modal-event-action-hub" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
            <div class="modal-content border-0 shadow-2xl" style="border-radius: 20px; overflow: hidden; background: #ffffff;">
                <div class="modal-header py-3 px-4" style="background: linear-gradient(135deg, #063B5C 0%, #04253A 100%); color: #ffffff;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: rgba(53, 196, 232, 0.2); color: #35C4E8; font-size: 1.25rem;">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-white" id="action-hub-event-title" style="font-size: 1.05rem;">Event Title</h6>
                            <span class="badge" id="action-hub-event-status-badge" style="font-size: 0.72rem; margin-top: 2px;">ACTIVE</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted small mb-3 px-1">Select an action to execute for this event:</p>
                    <div class="d-flex flex-column gap-2" id="action-hub-buttons-container">
                        <!-- Action items injected dynamically by JS -->
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-light border-0 justify-content-between">
                    <span class="small text-muted"><i class="bi bi-shield-lock me-1"></i> TPC BSIS Attendance Engine</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3 fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: VIEW EVENT DETAILS & INFORMATION -->
    <div class="modal fade" id="modal-view-event-details" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-card);">
                <div class="modal-header bg-primary text-white py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-calendar2-event-fill" style="font-size: 1.2rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="detail-event-title">Event Title</h5>
                            <small class="text-light" style="opacity: 0.85;">Event Session Details & Attendance Overview</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Status & Audience Badges -->
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <span id="detail-event-status-badge" class="bsis-badge bsis-badge-success">ACTIVE</span>
                        <span id="detail-event-audience-badge" class="badge bg-primary px-2 py-1" style="font-size: 0.8rem;">
                            <i class="bi bi-people-fill"></i> All BSIS Students
                        </span>
                        <span id="detail-event-window-badge" class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.8rem;">
                            <i class="bi bi-clock-history"></i> Window: Open
                        </span>
                    </div>

                    <!-- Statistics Summary Grid -->
                    <div class="row g-2 mb-4 text-center">
                        <div class="col-6 col-md-3">
                            <div class="bsis-card p-2 bg-light border-0">
                                <span class="text-muted small fw-bold d-block">TOTAL SCANNED</span>
                                <h4 class="fw-bold text-primary mb-0" id="detail-stat-total">0</h4>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bsis-card p-2 bg-light border-0">
                                <span class="text-muted small fw-bold d-block">PRESENT</span>
                                <h4 class="fw-bold text-success mb-0" id="detail-stat-present">0</h4>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bsis-card p-2 bg-light border-0">
                                <span class="text-muted small fw-bold d-block">LATE SCANS</span>
                                <h4 class="fw-bold text-warning mb-0" id="detail-stat-late">0</h4>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="bsis-card p-2 bg-light border-0">
                                <span class="text-muted small fw-bold d-block">TOTAL FINES</span>
                                <h4 class="fw-bold text-danger mb-0" id="detail-stat-fines">₱0.00</h4>
                            </div>
                        </div>
                    </div>

                    <!-- Event Schedule & Session Scanning Windows Info -->
                    <div class="card p-3 mb-3 border-0 bg-light" style="border-radius: var(--radius-sm);">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h6 class="fw-bold text-primary mb-0"><i class="bi bi-clock-fill me-1"></i> Attendance Session Scanning Windows</h6>
                            <span id="detail-event-session-type-badge" class="badge bg-white text-dark border text-wrap text-start" style="font-size: 0.76rem; max-width: 100%;">WHOLE DAY (4 SCANS)</span>
                        </div>
                        <div class="row g-2 small mb-3">
                            <div class="col-md-6">
                                <span class="text-muted d-block">Overall Event Duration:</span>
                                <strong id="detail-event-schedule" class="text-dark">Loading...</strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted d-block">Late / Missed Fine:</span>
                                <strong id="detail-event-fine" class="text-danger">₱0.00</strong>
                            </div>
                        </div>
                        <div id="detail-event-windows-container" class="row g-2">
                            <!-- Dynamic Session Windows Injected Here -->
                        </div>
                    </div>

                    <!-- Venue & Geofence Map -->
                    <div class="card p-3 mb-3 border-0 bg-light" style="border-radius: var(--radius-sm);">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h6 class="fw-bold text-primary mb-0"><i class="bi bi-geo-alt-fill me-1"></i> Venue & Geofence Location</h6>
                            <span id="detail-event-radius-badge" class="badge bg-info text-dark">50m Allowed Radius</span>
                        </div>
                        <p class="mb-2 small" id="detail-event-venue-name"><strong>Talibon Polytechnic College</strong></p>
                        
                        <!-- Map container -->
                        <div id="detail-event-map" style="height: 220px; border-radius: 8px; border: 1px solid var(--color-border); z-index: 1;" class="mb-1"></div>
                        <small class="text-muted"><i class="bi bi-info-circle"></i> Cyan circle indicates student attendance scanning geofence perimeter.</small>
                    </div>

                    <!-- Description & Organization -->
                    <div class="card p-3 border-0 bg-light" style="border-radius: var(--radius-sm);">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-card-text me-1"></i> Description & Organization</h6>
                        <p class="small text-muted mb-3" id="detail-event-desc">No description provided.</p>
                        <div class="row g-2 small border-top pt-2">
                            <div class="col-md-6">
                                <span class="text-muted">Created By:</span>
                                <strong id="detail-event-creator" class="d-block text-dark">System Admin</strong>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted">Assigned Event Staff:</span>
                                <strong id="detail-event-staff" class="d-block text-dark">All Staff</strong>
                            </div>
                        </div>
                    </div>
                <div class="modal-footer d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2 bg-light py-2 px-3 px-sm-4">
                    <button type="button" class="btn btn-bsis-outline btn-sm fw-semibold order-last order-sm-first py-2 px-3" data-bs-dismiss="modal" style="min-height: 38px; font-size: 0.82rem;">
                        <i class="bi bi-x-lg me-1"></i> Close
                    </button>
                    <div class="d-flex flex-row align-items-center gap-2 flex-grow-1 flex-sm-grow-0 justify-content-end" id="detail-event-actions-bar">
                        <!-- Dynamic action buttons injected via JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js Analytics Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Leaflet.js OpenStreetMap JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- QRCode.js In-Browser Generator -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- App JS Modules -->
    <script src="/js/storage.js?v={{ time() }}"></script>
    <script src="/js/admin-app.js?v={{ time() }}"></script>
</body>
</html>
