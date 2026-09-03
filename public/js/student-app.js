/* BSIS Student Progressive Web Application (PWA) Controller */

document.addEventListener('DOMContentLoaded', () => {
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js')
            .then(reg => console.log('BSIS ServiceWorker registered.'))
            .catch(err => console.error('ServiceWorker error:', err));
    }

    // Initialize App Routing & Event Listeners
    StudentPWA.init();
});

const StudentPWA = {
    currentGps: { latitude: null, longitude: null, error: null },
    mediaStream: null,
    scanInterval: null,
    isScanning: false,
    scanCanvas: null,
    scanCanvasContext: null,
    lastScannedToken: null,
    lastScannedTime: 0,
    animationFrameId: null,
    gpsWatchId: null,
    gpsHistory: [],
    lastGpsPoint: null,
    mockLocationDetected: false,
    mockLocationReason: null,
    scannerStartTime: 0,
    loginUserExplicit: false,

    init() {
        window.addEventListener('hashchange', () => this.handleRoute());
        window.addEventListener('online', () => this.updateOnlineStatus());
        window.addEventListener('offline', () => this.updateOnlineStatus());

        // Prevent auto-login on browser password manager autofill
        const studentLoginForm = document.getElementById('student-login-form') || document.querySelector('#view-login form');
        const studentLoginBtn = document.getElementById('login-btn');
        if (studentLoginBtn) {
            studentLoginBtn.addEventListener('click', () => { this.loginUserExplicit = true; });
            studentLoginBtn.addEventListener('touchstart', () => { this.loginUserExplicit = true; }, { passive: true });
        }
        if (studentLoginForm) {
            studentLoginForm.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') this.loginUserExplicit = true;
            });
        }

        // Periodic background sync every 10 seconds if online
        setInterval(() => {
            if (navigator.onLine && StorageManager.getOfflineQueue().length > 0) {
                this.syncOfflineRecords();
            }
        }, 10000);

        this.updateOnlineStatus();
        this.handleRoute();
    },

    openIosGuideModal(e) {
        if (e && e.preventDefault) e.preventDefault();
        const modalEl = document.getElementById('modal-ios-guide');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    },

    updateOnlineStatus() {
        const statusBanner = document.getElementById('offline-banner');
        if (statusBanner) {
            if (!navigator.onLine) {
                statusBanner.classList.remove('d-none');
            } else {
                statusBanner.classList.add('d-none');
                this.syncOfflineRecords();
            }
        }
    },

    async syncOfflineRecords() {
        const queue = StorageManager.getOfflineQueue();
        if (queue.length === 0) return;

        let syncedAny = false;
        const remainingQueue = [];

        for (const item of queue) {
            const res = await StorageManager.apiRequest('/api/attendance/scan', {
                method: 'POST',
                body: JSON.stringify({
                    ...item,
                    is_offline_sync: true
                })
            });

            if (res.ok || res.status === 409) {
                syncedAny = true;
            } else if (res.status === 0) {
                // Network still down
                remainingQueue.push(item);
            }
        }

        if (remainingQueue.length === 0) {
            StorageManager.clearOfflineQueue();
        } else {
            localStorage.setItem(StorageManager.OFFLINE_QUEUE_KEY, JSON.stringify(remainingQueue));
        }

        if (syncedAny) {
            this.showToast('✓ Offline attendance synchronized with server!');
            if (window.location.hash === '#dashboard' || !window.location.hash) {
                this.loadDashboard();
            } else if (window.location.hash === '#history') {
                this.loadHistory();
            }
        }
    },

    handleRoute() {
        const hash = window.location.hash || '';

        // Reset password query string / hash parameter check
        if (hash.startsWith('#reset-password')) {
            this.showView('view-reset-password');
            const queryString = hash.includes('?') ? hash.split('?')[1] : window.location.search.replace(/^\?/, '');
            const urlParams = new URLSearchParams(queryString);
            const token = urlParams.get('token');
            const email = urlParams.get('email');
            const tokenInput = document.getElementById('student-reset-token-input');
            const idInput = document.getElementById('student-reset-identifier');
            if (tokenInput && token) tokenInput.value = decodeURIComponent(token);
            if (idInput && email) idInput.value = decodeURIComponent(email);
            return;
        }

        // Onboarding query string parameter check
        if (window.location.search.includes('token=') && !hash.includes('#reset-password')) {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            if (token) {
                this.showView('view-onboarding');
                this.loadOnboardingInfo(token);
                return;
            }
        }

        if (hash === '#onboarding') {
            this.showView('view-onboarding');
            return;
        }

        // Default to student welcome & APK download hub
        this.showView('view-welcome');
    },

    showView(viewId) {
        document.querySelectorAll('.app-view').forEach(view => view.classList.add('d-none'));
        const targetView = document.getElementById(viewId);
        if (targetView) {
            targetView.classList.remove('d-none');
        }

        // Show/hide bottom nav based on auth state
        const bottomNav = document.getElementById('student-bottom-nav');
        const isAuthView = viewId !== 'view-login' && viewId !== 'view-onboarding';

        if (bottomNav) {
            if (isAuthView) bottomNav.classList.remove('d-none');
            else bottomNav.classList.add('d-none');
        }

        // Update Bottom Nav active state
        document.querySelectorAll('.bsis-nav-btn').forEach(btn => btn.classList.remove('active'));
        const activeNavBtn = document.querySelector(`.bsis-nav-btn[data-target="${viewId}"]`);
        if (activeNavBtn) {
            activeNavBtn.classList.add('active');
        }

        // Smooth scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    // 1. LOGIN
    async handleLogin(event) {
        if (event && event.preventDefault) event.preventDefault();

        const loginInput = document.getElementById('login-identifier')?.value.trim() || '';
        const passwordInput = document.getElementById('login-password')?.value || '';
        const btn = document.getElementById('login-btn');
        const alertBox = document.getElementById('login-alert');

        if (!loginInput || !passwordInput) {
            if (alertBox) {
                alertBox.className = 'alert alert-warning text-start py-2 px-3 small';
                alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Please enter your student ID or email and password.';
                alertBox.classList.remove('d-none');
            }
            return;
        }

        alertBox.classList.add('d-none');
        btn.disabled = true;
        btn.innerText = 'Logging in...';

        const res = await StorageManager.apiRequest('/api/auth/login', {
            method: 'POST',
            body: JSON.stringify({
                login: loginInput,
                password: passwordInput,
                device_name: navigator.userAgent,
                device_credential: StorageManager.getDeviceCredential()
            })
        });

        btn.disabled = false;
        btn.innerText = 'LOGIN';

        if (res.ok && res.data.success) {
            StorageManager.setToken(res.data.data.token);
            StorageManager.setUser(res.data.data.user);
            if (res.data.data.user?.device_credential) {
                StorageManager.setDeviceCredential(res.data.data.user.device_credential);
            }
            window.location.hash = '#dashboard';
            this.handleRoute();
        } else {
            const errorMsg = res.data?.message || 'Invalid credentials or inactive account.';
            const isLocked = res.status === 429 || res.data?.data?.is_locked;
            alertBox.className = `alert ${isLocked ? 'alert-danger' : 'alert-warning'} text-start py-2 px-3 small`;
            alertBox.innerHTML = `<i class="bi ${isLocked ? 'bi-clock-history' : 'bi-shield-x'} me-1"></i> ${errorMsg}`;
            alertBox.classList.remove('d-none');
        }
    },

    // 2. ONBOARDING
    togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            if (icon) icon.className = 'bi bi-eye';
        }
    },

    validateOnboardPasswordLive() {
        const pass = document.getElementById('onboard-password')?.value || '';
        const passConf = document.getElementById('onboard-password-confirm')?.value || '';

        const hasLen = pass.length >= 8;
        const hasLower = /[a-z]/.test(pass);
        const hasUpper = /[A-Z]/.test(pass);
        const hasNum = /[0-9]/.test(pass);
        const hasSym = /[^A-Za-z0-9]/.test(pass);
        const hasMatch = pass.length > 0 && pass === passConf;

        const updateRule = (elId, valid, text) => {
            const el = document.getElementById(elId);
            if (!el) return;
            if (valid) {
                el.className = 'text-success fw-semibold';
                el.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${text}`;
            } else {
                el.className = 'text-danger';
                el.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i> ${text}`;
            }
        };

        updateRule('rule-len', hasLen, 'Minimum 8 characters');
        updateRule('rule-lower', hasLower, 'At least one lowercase letter (a-z)');
        updateRule('rule-upper', hasUpper, 'At least one uppercase letter (A-Z)');
        updateRule('rule-num', hasNum, 'At least one number (0-9)');
        updateRule('rule-sym', hasSym, 'At least one special symbol (!@#$%^&*...)');
        updateRule('rule-match', hasMatch, hasMatch ? 'Passwords match' : 'Passwords must match');

        return hasLen && hasLower && hasUpper && hasNum && hasSym && hasMatch;
    },

    async loadOnboardingInfo(token) {
        // Detect in-app browser (Gmail WebView, Messenger, FB, etc.)
        const ua = navigator.userAgent || '';
        const isAndroid = /Android/i.test(ua);
        const isInApp = /FBAN|FBAV|Instagram|GSA|Gmail|Line|MicroMessenger|WebView|wv/i.test(ua);

        if (isInApp) {
            const inAppNotice = document.getElementById('inapp-browser-notice');
            if (inAppNotice) inAppNotice.classList.remove('d-none');

            // On Android, attempt to auto-escape in-app WebView into phone's default browser
            if (isAndroid) {
                try {
                    const cleanPath = window.location.host + window.location.pathname + window.location.search;
                    // Standard Android Intent targeting system default browser
                    window.location.href = `intent://${cleanPath}#Intent;scheme=https;action=android.intent.action.VIEW;category=android.intent.category.BROWSABLE;end`;
                } catch (e) {}
            }
        }

        const res = await StorageManager.apiRequest(`/api/onboarding/${token}`);
        const alertBox = document.getElementById('onboarding-alert');
        if (!res.ok || !res.data.success) {
            alertBox.innerText = res.data?.message || 'Invalid or expired onboarding link.';
            alertBox.classList.remove('d-none');
            document.getElementById('onboarding-form').classList.add('d-none');
            return;
        }

        const student = res.data.data.student;
        document.getElementById('onboard-student-name').innerText = student.full_name;
        document.getElementById('onboard-student-id').innerText = student.student_number;
        document.getElementById('onboard-student-email').innerText = student.email;
        document.getElementById('onboarding-token-val').value = token;
        this.validateOnboardPasswordLive();
    },

    openInDefaultBrowser() {
        const cleanPath = window.location.host + window.location.pathname + window.location.search;
        const isAndroid = /Android/i.test(navigator.userAgent);
        if (isAndroid) {
            window.location.href = `intent://${cleanPath}#Intent;scheme=https;action=android.intent.action.VIEW;category=android.intent.category.BROWSABLE;end`;
        } else {
            this.copyOnboardingLink();
        }
    },

    copyOnboardingLink() {
        const url = window.location.href;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                this.showToast('✓ Link copied! Open Google Chrome, Safari, or your default browser.');
            }).catch(() => {
                prompt('Copy your account activation link:', url);
            });
        } else {
            prompt('Copy your account activation link:', url);
        }
    },

    handleCompleteOnboarding(event) {
        event.preventDefault();
        const isValid = this.validateOnboardPasswordLive();
        const alertBox = document.getElementById('onboarding-alert');

        if (!isValid) {
            alertBox.innerText = 'Please ensure all password requirements below are satisfied before proceeding.';
            alertBox.classList.remove('d-none');
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        alertBox.classList.add('d-none');
        const modalEl = document.getElementById('modal-confirm-onboard');
        if (typeof bootstrap !== 'undefined' && modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            const confirmed = confirm('🔐 CONFIRM ACCOUNT ACTIVATION:\n\nAre you sure you want to activate your student account and save your password?\n\nClick "OK" to proceed or "Cancel" to return.');
            if (confirmed) {
                this.submitOnboarding();
            }
        }
    },

    async submitOnboarding() {
        const modalEl = document.getElementById('modal-confirm-onboard');
        if (typeof bootstrap !== 'undefined' && modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }

        const token = document.getElementById('onboarding-token-val').value;
        const pass = document.getElementById('onboard-password').value;
        const passConf = document.getElementById('onboard-password-confirm').value;
        const alertBox = document.getElementById('onboarding-alert');

        const btn = document.getElementById('onboard-submit-btn');
        const modalBtn = document.getElementById('btn-proceed-onboarding');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Activating Account...';
        }
        if (modalBtn) {
            modalBtn.disabled = true;
            modalBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Activating...';
        }

        const res = await StorageManager.apiRequest(`/api/onboarding/${token}/complete`, {
            method: 'POST',
            body: JSON.stringify({
                password: pass,
                password_confirmation: passConf
            })
        });

        if (btn) {
            btn.disabled = false;
            btn.innerText = 'Create Password & Activate Account';
        }
        if (modalBtn) {
            modalBtn.disabled = false;
            modalBtn.innerText = 'Confirm & Activate';
        }

        if (res.ok && res.data.success) {
            const form = document.getElementById('onboarding-form');
            const successCard = document.getElementById('onboarding-success-card');
            const inappNotice = document.getElementById('inapp-browser-notice');
            
            if (form) form.classList.add('d-none');
            if (inappNotice) inappNotice.classList.add('d-none');
            if (successCard) successCard.classList.remove('d-none');
            
            this.showToast('Account activated! Please open the mobile app to sign in.', 'success');
        } else {
            alertBox.innerText = res.data?.message || 'Failed to complete onboarding.';
            alertBox.classList.remove('d-none');
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    },

    validateStudentResetPasswordLive() {
        const pass = document.getElementById('student-reset-password')?.value || '';
        const passConf = document.getElementById('student-reset-password-confirm')?.value || '';

        const hasLen = pass.length >= 8;
        const hasLower = /[a-z]/.test(pass);
        const hasUpper = /[A-Z]/.test(pass);
        const hasNum = /[0-9]/.test(pass);
        const hasSym = /[^A-Za-z0-9]/.test(pass);
        const hasMatch = pass.length > 0 && pass === passConf;

        const updateRule = (elId, valid, text) => {
            const el = document.getElementById(elId);
            if (!el) return;
            if (valid) {
                el.className = 'text-success fw-semibold';
                el.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${text}`;
            } else {
                el.className = 'text-danger';
                el.innerHTML = `<i class="bi bi-x-circle-fill me-1"></i> ${text}`;
            }
        };

        updateRule('student-reset-rule-len', hasLen, 'Minimum 8 characters');
        updateRule('student-reset-rule-lower', hasLower, 'At least one lowercase letter (a-z)');
        updateRule('student-reset-rule-upper', hasUpper, 'At least one uppercase letter (A-Z)');
        updateRule('student-reset-rule-num', hasNum, 'At least one number (0-9)');
        updateRule('student-reset-rule-sym', hasSym, 'At least one special symbol (!@#$%^&*...)');
        updateRule('student-reset-rule-match', hasMatch, hasMatch ? 'Passwords match' : 'Passwords must match');

        return hasLen && hasLower && hasUpper && hasNum && hasSym && hasMatch;
    },

    async handleCompletePasswordReset(event) {
        event.preventDefault();
        const identifier = document.getElementById('student-reset-identifier')?.value.trim() || '';
        const token = document.getElementById('student-reset-token-input')?.value.trim() || '';
        const pass = document.getElementById('student-reset-password')?.value || '';
        const passConf = document.getElementById('student-reset-password-confirm')?.value || '';
        const alertBox = document.getElementById('student-reset-alert');
        const btn = document.getElementById('student-reset-submit-btn');

        if (!identifier) {
            if (alertBox) {
                alertBox.innerText = 'Please enter your student ID or email.';
                alertBox.classList.remove('d-none');
            }
            return;
        }

        if (!token) {
            if (alertBox) {
                alertBox.innerText = 'Please paste your 64-character reset token from email.';
                alertBox.classList.remove('d-none');
            }
            return;
        }

        const isValid = this.validateStudentResetPasswordLive();
        if (!isValid) {
            if (alertBox) {
                alertBox.innerText = 'Please satisfy all password complexity rules and ensure passwords match.';
                alertBox.classList.remove('d-none');
            }
            return;
        }

        if (alertBox) alertBox.classList.add('d-none');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating Password...';
        }

        const res = await StorageManager.apiRequest('/api/auth/reset-password', {
            method: 'POST',
            body: JSON.stringify({
                login: identifier,
                token: token,
                password: pass,
                password_confirmation: passConf
            })
        });

        if (btn) {
            btn.disabled = false;
            btn.innerText = 'Confirm & Update Password';
        }

        if (res.ok && res.data && res.data.success) {
            const form = document.getElementById('student-reset-password-form');
            const successCard = document.getElementById('student-reset-success-card');
            if (form) form.classList.add('d-none');
            if (successCard) successCard.classList.remove('d-none');
            this.showToast('Password reset successfully! Open the mobile app to sign in.', 'success');
        } else {
            const errorMsg = res.data?.message || 'Invalid or expired reset token. Please request a new token.';
            if (alertBox) {
                alertBox.innerText = errorMsg;
                alertBox.classList.remove('d-none');
            }
        }
    },

    formatDisplayDateTime(dateStr) {
        if (!dateStr) return 'N/A';
        const d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return dateStr;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[d.getMonth()];
        const day = d.getDate();
        const year = d.getFullYear();
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${month} ${day}, ${year} • ${hours}:${minutes} ${ampm}`;
    },

    // 3. DASHBOARD
    async loadDashboard() {
        const user = StorageManager.getUser();
        if (user) {
            document.getElementById('user-full-name').innerText = user.full_name;
            document.getElementById('user-student-id').innerText = user.student_number || 'N/A';
            const yrBlkEl = document.getElementById('user-year-block');
            if (yrBlkEl) {
                yrBlkEl.innerText = [user.year_level, user.section_block].filter(Boolean).join(' • ') || 'BSIS Student';
            }
            const emailEl = document.getElementById('user-email');
            if (emailEl) emailEl.innerText = user.email;
        }

        // Background profile & device sync
        StorageManager.apiRequest('/api/auth/me').then(meRes => {
            if (meRes.ok && meRes.data?.data) {
                const u = meRes.data.data;
                StorageManager.setUser(u);
                if (u.active_device?.device_credential) {
                    StorageManager.setDeviceCredential(u.active_device.device_credential);
                }
            }
        });

        const deviceCred = StorageManager.getDeviceCredential();
        const deviceBadge = document.getElementById('dash-device-status');
        if (deviceBadge) {
            if (deviceCred) {
                deviceBadge.className = 'bsis-badge bsis-badge-success';
                deviceBadge.innerText = '● Bound & Active Device';
            } else {
                deviceBadge.className = 'bsis-badge bsis-badge-warning';
                deviceBadge.innerText = '● Auto-Binding on Scan';
            }
        }

        // Fetch active and upcoming events
        const eventsRes = await StorageManager.apiRequest('/api/events?per_page=50&sort_by=start_time&sort_order=asc');
        const eventList = document.getElementById('dash-events-list');
        if (eventsRes.ok && eventsRes.data && eventsRes.data.data && eventsRes.data.data.data.length > 0) {
            const events = eventsRes.data.data.data;
            eventList.innerHTML = events.map(event => {
                const isActive = event.status === 'active';
                const isUpcoming = event.status === 'upcoming' || event.status === 'draft';
                const statusBadge = isActive 
                    ? '<span class="bsis-badge bsis-badge-event-active"><i class="bi bi-broadcast me-1"></i> ACTIVE SESSION</span>' 
                    : (isUpcoming
                        ? '<span class="bsis-badge bsis-badge-event-upcoming"><i class="bi bi-hourglass-split me-1"></i> UPCOMING EVENT</span>'
                        : '<span class="bsis-badge bsis-badge-event-completed"><i class="bi bi-check-circle me-1"></i> COMPLETED EVENT</span>');
                const audienceBadge = `<span class="badge ${(!event.target_audience_label || event.target_audience_label === 'All BSIS Students') ? 'bg-secondary' : 'bg-primary'}" style="font-size: 0.72rem;">
                    <i class="bi bi-people-fill"></i> ${event.target_audience_label || 'All BSIS Students'}
                </span>`;
                const formattedTime = this.formatDisplayDateTime(event.start_time);
                const fineText = parseFloat(event.fine_amount) > 0 ? `₱${parseFloat(event.fine_amount).toFixed(2)} Late Fine` : 'No Fine';
                const borderClass = isActive ? 'border-start border-4 border-info' : (isUpcoming ? 'border-start border-4 border-warning' : 'border-start border-4 border-success');

                return `
                    <div class="bsis-card mb-3 p-3 ${borderClass}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="flex-grow-1">
                                <div class="d-flex gap-2 mb-2 flex-wrap align-items-center">
                                    ${statusBadge}
                                    ${audienceBadge}
                                </div>
                                <h5 class="mb-1">
                                    <a href="javascript:void(0)" onclick="StudentPWA.viewEventDetails(${event.id})" class="event-title-link text-primary-dark fw-bold" title="Click to view full event information">
                                        <span class="event-title-text">${event.title}</span>
                                        <span class="badge bg-light text-primary border shadow-sm" style="font-size: 0.68rem; font-weight: 600;">
                                            <i class="bi bi-info-circle-fill text-info me-1"></i>View Info
                                        </span>
                                    </a>
                                </h5>
                                <p class="mb-1 text-muted small"><i class="bi bi-geo-alt-fill text-danger me-1"></i> ${event.venue_name} (${event.allowed_radius_meters}m perimeter)</p>
                                <p class="mb-1 text-muted small"><i class="bi bi-clock-fill text-primary me-1"></i> ${formattedTime}</p>
                                <p class="mb-0 text-muted small"><i class="bi bi-cash-coin text-warning me-1"></i> ${fineText}</p>
                            </div>
                            <div class="text-end align-self-center ps-2">
                                ${isActive ? `
                                    <a href="#scanner" class="btn btn-bsis-accent shadow-sm event-card-action-btn">
                                        <i class="bi bi-qr-code-scan me-1"></i> Scan Now
                                    </a>
                                ` : `
                                    <span class="badge bg-light text-secondary border event-card-action-btn">
                                        <i class="bi bi-hourglass-split me-1"></i> Scheduled
                                    </span>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Render Today's Attendance Progress Timeline Pass
            this.renderStudentTimelineCard(events);
        } else {
            eventList.innerHTML = '<p class="text-muted small mb-0 py-3 text-center"><i class="bi bi-calendar-x me-1"></i> No active or upcoming events scheduled for your year level.</p>';
            const timelineCard = document.getElementById('student-session-timeline-card');
            if (timelineCard) timelineCard.classList.add('d-none');
        }
    },

    async renderStudentTimelineCard(events) {
        const container = document.getElementById('student-session-timeline-card');
        const stepsEl = document.getElementById('student-timeline-steps');
        if (!container || !stepsEl) return;

        // Pick active event first, or first scheduled event today
        const targetEvent = events.find(e => e.status === 'active') || events[0];
        if (!targetEvent) {
            container.classList.add('d-none');
            return;
        }

        const titleEl = document.getElementById('timeline-event-title');
        const venueEl = document.getElementById('timeline-event-venue');
        const badgeEl = document.getElementById('timeline-session-badge');

        if (titleEl) titleEl.innerHTML = `<i class="bi bi-calendar2-check-fill me-1 text-primary"></i> ${targetEvent.title}`;
        if (venueEl) venueEl.innerText = `${targetEvent.venue_name} • ${this.formatDisplayDateTime(targetEvent.start_time)}`;

        const isWhole = targetEvent.session_type === 'whole_day';
        if (badgeEl) badgeEl.innerText = isWhole ? '4 SCANS (AM & PM)' : '2 SCANS (AM IN / PM OUT)';

        // Fetch student's attendance history to inspect scan stamps
        let myRecord = null;
        try {
            const histRes = await StorageManager.apiRequest('/api/attendance');
            if (histRes.ok && histRes.data?.data) {
                const list = histRes.data.data.data || histRes.data.data || [];
                myRecord = Array.isArray(list) ? list.find(r => r.event_id == targetEvent.id) : null;
            }
        } catch (e) {}

        let html = '<div class="bsis-timeline-bar-bg"></div>';

        if (isWhole) {
            const amIn = myRecord?.am_time_in || myRecord?.scan_time;
            const amOut = myRecord?.am_time_out || myRecord?.am_checkout_time;
            const pmIn = myRecord?.pm_time_in;
            const pmOut = myRecord?.pm_time_out || myRecord?.checkout_time || myRecord?.pm_checkout_time;

            const steps = [
                { label: 'AM Time-In', time: amIn ? this.formatTimeOnly(amIn) : 'Pending', done: !!amIn, icon: 'bi-sun-fill' },
                { label: 'AM Time-Out', time: amOut ? this.formatTimeOnly(amOut) : 'Pending', done: !!amOut, icon: 'bi-box-arrow-right' },
                { label: 'PM Time-In', time: pmIn ? this.formatTimeOnly(pmIn) : 'Pending', done: !!pmIn, icon: 'bi-cloud-sun-fill' },
                { label: 'PM Time-Out', time: pmOut ? this.formatTimeOnly(pmOut) : 'Pending', done: !!pmOut, icon: 'bi-check2-all' },
            ];

            html += steps.map((s, idx) => `
                <div class="bsis-timeline-step ${s.done ? 'completed' : (idx === 0 || steps[idx-1]?.done ? 'active' : '')}">
                    <div class="bsis-timeline-icon">
                        <i class="bi ${s.done ? 'bi-check-lg' : s.icon}"></i>
                    </div>
                    <div class="bsis-timeline-label">${s.label}</div>
                    <div class="bsis-timeline-time">${s.time}</div>
                </div>
            `).join('');
        } else {
            const timeIn = myRecord?.am_time_in || myRecord?.scan_time || myRecord?.time_in;
            const timeOut = myRecord?.checkout_time || myRecord?.pm_time_out || myRecord?.pm_checkout_time;

            const steps = [
                { label: 'Check-In', time: timeIn ? this.formatTimeOnly(timeIn) : 'Pending', done: !!timeIn, icon: 'bi-box-arrow-in-right' },
                { label: 'Check-Out', time: timeOut ? this.formatTimeOnly(timeOut) : 'Pending', done: !!timeOut, icon: 'bi-box-arrow-right' },
            ];

            html += steps.map((s, idx) => `
                <div class="bsis-timeline-step ${s.done ? 'completed' : (idx === 0 || steps[idx-1]?.done ? 'active' : '')}">
                    <div class="bsis-timeline-icon">
                        <i class="bi ${s.done ? 'bi-check-lg' : s.icon}"></i>
                    </div>
                    <div class="bsis-timeline-label">${s.label}</div>
                    <div class="bsis-timeline-time">${s.time}</div>
                </div>
            `).join('');
        }

        stepsEl.innerHTML = html;
        container.classList.remove('d-none');
    },

    formatTimeOnly(dateStr) {
        if (!dateStr) return '—';
        try {
            const d = new Date(dateStr);
            return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
        } catch (e) {
            return dateStr;
        }
    },

    async loadProfile() {
        let user = StorageManager.getUser();

        const renderUserInfo = (u) => {
            if (!u) return;
            const nameEl = document.getElementById('prof-student-name');
            if (nameEl) nameEl.innerText = u.full_name;

            const sNumEl = document.getElementById('prof-student-number');
            if (sNumEl) sNumEl.innerText = u.student_number || 'N/A';

            const yrEl = document.getElementById('prof-year-level');
            if (yrEl) yrEl.innerText = u.year_level || 'N/A';

            const blkEl = document.getElementById('prof-section-block');
            if (blkEl) blkEl.innerText = u.section_block || 'N/A';

            const emailEl = document.getElementById('prof-email');
            if (emailEl) emailEl.innerText = u.email || 'N/A';

            const statusEl = document.getElementById('prof-account-status');
            if (statusEl) statusEl.innerText = u.status ? (u.status.toUpperCase() + ' (Enrolled)') : 'Active & Verified';

            const badgeEl = document.getElementById('prof-student-status-badge');
            if (badgeEl) badgeEl.innerText = `● ${u.status ? u.status.toUpperCase() : 'ACTIVE'} ENROLLED`;
        };

        // Render from cached user first
        renderUserInfo(user);

        // Fetch fresh profile from server
        try {
            const meRes = await StorageManager.apiRequest('/api/auth/me');
            if (meRes.ok && meRes.data?.data) {
                user = meRes.data.data;
                StorageManager.setUser(user);
                renderUserInfo(user);
            }
        } catch (e) {}

        const deviceCred = StorageManager.getDeviceCredential();
        const uuidEl = document.getElementById('prof-device-uuid');
        if (uuidEl) {
            uuidEl.innerText = deviceCred || 'No registered device bound yet.';
        }
    },

    async viewEventDetails(eventId) {
        const res = await StorageManager.apiRequest(`/api/events/${eventId}`);
        if (!res.ok || !res.data.success) {
            alert(res.data?.message || 'Failed to load event information.');
            return;
        }

        const data = res.data.data;
        const e = data.event;
        const audience = data.target_audience_label || e.target_audience_label || 'All BSIS Students';
        const winStatus = data.window_status || {};

        // Title
        document.getElementById('stu-detail-event-title').innerText = e.title;

        // Status badge
        const statusBadge = document.getElementById('stu-detail-status-badge');
        if (statusBadge) {
            const isAct = e.status === 'active';
            const isUpc = e.status === 'upcoming' || e.status === 'draft';
            statusBadge.className = `bsis-badge ${isAct ? 'bsis-badge-event-active' : (isUpc ? 'bsis-badge-event-upcoming' : 'bsis-badge-event-completed')}`;
            statusBadge.innerHTML = isAct ? '<i class="bi bi-broadcast me-1"></i> ACTIVE SESSION' : (isUpc ? '<i class="bi bi-hourglass-split me-1"></i> UPCOMING EVENT' : '<i class="bi bi-check-circle me-1"></i> COMPLETED EVENT');
        }

        // Audience badge
        const audBadge = document.getElementById('stu-detail-audience-badge');
        if (audBadge) {
            audBadge.className = `badge ${audience === 'All BSIS Students' ? 'bg-secondary' : 'bg-primary'} px-2 py-1`;
            audBadge.innerHTML = `<i class="bi bi-people-fill me-1"></i> ${audience}`;
        }

        // Window badge
        const winBadge = document.getElementById('stu-detail-window-badge');
        if (winBadge) {
            if (winStatus.window_open) {
                winBadge.className = 'badge bg-success text-white px-2 py-1';
                winBadge.innerHTML = `<i class="bi bi-door-open-fill me-1"></i> Window: Open (${winStatus.type || 'Time-In'})`;
            } else {
                winBadge.className = 'badge bg-light text-secondary border px-2 py-1';
                winBadge.innerHTML = `<i class="bi bi-door-closed-fill me-1"></i> Window: Closed`;
            }
        }

        // Schedule & Session Windows
        const scheduleText = `${this.formatDisplayDateTime(e.start_time)} to ${this.formatDisplayDateTime(e.end_time)}`;
        document.getElementById('stu-detail-schedule').innerText = scheduleText;
        document.getElementById('stu-detail-fine').innerText = parseFloat(e.fine_amount) > 0 ? `₱${parseFloat(e.fine_amount).toFixed(2)} (if scanned after time-in window)` : 'No fine';

        const isWhole = e.session_type === 'whole_day';
        const sessionBadge = document.getElementById('stu-detail-session-badge');
        if (sessionBadge) sessionBadge.innerText = isWhole ? '4 SCANS (AM & PM SLOTS)' : '2 SCANS (MORNING IN & AFTERNOON OUT)';

        const fmtSlotTime = (t) => {
            if (!t) return null;
            try {
                const d = new Date(t.includes(' ') || t.includes('T') ? t : `1970-01-01T${t}`);
                return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true });
            } catch (err) {
                return t;
            }
        };

        const fmtWindowRange = (start, end) => {
            if (!start && !end) return '<span class="text-muted fst-italic">Open / Unrestricted</span>';
            const s = fmtSlotTime(start) || 'Start';
            const en = fmtSlotTime(end) || 'End';
            return `<strong class="text-primary">${s}</strong> &mdash; <strong class="text-primary">${en}</strong>`;
        };

        const windowsContainer = document.getElementById('stu-detail-windows-container');
        if (windowsContainer) {
            if (isWhole) {
                windowsContainer.innerHTML = `
                    <div class="col-12 col-sm-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-sun-fill text-warning me-1"></i> AM Time-In</span>
                            <div class="small text-truncate">${fmtWindowRange(e.am_checkin_start_time, e.am_checkin_end_time)}</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-box-arrow-right text-info me-1"></i> AM Time-Out</span>
                            <div class="small text-truncate">${fmtWindowRange(e.am_checkout_start_time, e.am_checkout_end_time)}</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-cloud-sun-fill text-primary me-1"></i> PM Time-In</span>
                            <div class="small text-truncate">${fmtWindowRange(e.pm_checkin_start_time, e.pm_checkin_end_time)}</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-check2-all text-success me-1"></i> PM Time-Out</span>
                            <div class="small text-truncate">${fmtWindowRange(e.pm_checkout_start_time, e.pm_checkout_end_time)}</div>
                        </div>
                    </div>
                `;
            } else {
                windowsContainer.innerHTML = `
                    <div class="col-12 col-sm-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-box-arrow-in-right text-success me-1"></i> Time-In Window</span>
                            <div class="small text-truncate">${fmtWindowRange(e.checkin_start_time, e.checkin_end_time)}</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-box-arrow-right text-info me-1"></i> Time-Out Window</span>
                            <div class="small text-truncate">${fmtWindowRange(e.checkout_start_time, e.checkout_end_time)}</div>
                        </div>
                    </div>
                `;
            }
        }

        // Venue & Radius
        document.getElementById('stu-detail-venue-name').innerHTML = `<strong>${e.venue_name}</strong>`;
        document.getElementById('stu-detail-radius-badge').innerText = `${e.allowed_radius_meters}m Perimeter Radius`;

        // Description
        document.getElementById('stu-detail-desc').innerText = e.description || 'No specific agenda or description notes provided.';

        // Actions
        const actionsContainer = document.getElementById('stu-detail-actions');
        if (actionsContainer) {
            if (e.status === 'active') {
                actionsContainer.innerHTML = `<a href="#scanner" data-bs-dismiss="modal" class="btn btn-bsis-accent btn-sm fw-bold px-3 py-1"><i class="bi bi-qr-code-scan me-1"></i> Open Scanner</a>`;
            } else {
                actionsContainer.innerHTML = `<span class="badge bg-light text-secondary border py-1 px-2">Scheduled Event</span>`;
            }
        }

        // Show Modal
        const modalEl = document.getElementById('modal-student-event-details');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        // Render Leaflet Map
        modalEl.addEventListener('shown.bs.modal', () => {
            const lat = parseFloat(e.venue_latitude) || 10.1492;
            const lng = parseFloat(e.venue_longitude) || 124.3312;
            const radius = parseInt(e.allowed_radius_meters) || 50;

            if (!this.studentEventMap) {
                this.studentEventMap = L.map('stu-detail-map').setView([lat, lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(this.studentEventMap);

                this.stuMarker = L.marker([lat, lng]).addTo(this.studentEventMap);
                this.stuCircle = L.circle([lat, lng], {
                    color: '#35C4E8',
                    fillColor: '#35C4E8',
                    fillOpacity: 0.25,
                    radius: radius
                }).addTo(this.studentEventMap);
            } else {
                this.studentEventMap.invalidateSize();
                this.studentEventMap.setView([lat, lng], 16);
                this.stuMarker.setLatLng([lat, lng]);
                this.stuCircle.setLatLng([lat, lng]);
                this.stuCircle.setRadius(radius);
            }
            this.studentEventMap.invalidateSize();
        }, { once: true });
    },

    // 4. DYNAMIC QR CAMERA SCANNER (CROSS-BROWSER: SAFARI, GOOGLE APP, CHROME, iOS & ANDROID)
    async getCameraMediaStream() {
        // Multi-stage constraint attempts for Safari, iOS, Google App webviews, and Android
        const constraintAttempts = [
            { video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false },
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            { video: { facingMode: 'environment' }, audio: false },
            { video: true, audio: false }
        ];

        // Standard modern API
        if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
            for (const constraints of constraintAttempts) {
                try {
                    return await navigator.mediaDevices.getUserMedia(constraints);
                } catch (e) {
                    console.warn('getUserMedia attempt failed with constraints:', constraints, e);
                    if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                        throw e; // Stop trying if user explicitly denied permission
                    }
                }
            }
        }

        // Legacy browser getUserMedia fallback (older WebKit / iOS / Android)
        const legacyGetUserMedia = navigator.getUserMedia ||
                                   navigator.webkitGetUserMedia ||
                                   navigator.mozGetUserMedia ||
                                   navigator.msGetUserMedia;

        if (legacyGetUserMedia) {
            for (const constraints of constraintAttempts) {
                try {
                    return await new Promise((resolve, reject) => {
                        legacyGetUserMedia.call(navigator, constraints, resolve, reject);
                    });
                } catch (e) {
                    console.warn('legacy getUserMedia attempt failed:', e);
                }
            }
        }

        throw new Error('NO_CAMERA_API');
    },

    async startScanner() {
        this.fetchGpsLocation();

        const video = document.getElementById('camera-preview');
        const alertBox = document.getElementById('camera-compat-alert');
        const alertTitle = document.getElementById('camera-compat-title');
        const alertDesc = document.getElementById('camera-compat-desc');
        const gpsStatus = document.getElementById('gps-status-text');
        const fallbackOverlay = document.getElementById('camera-fallback-overlay');
        const frameOverlay = document.getElementById('scanner-frame-overlay');

        if (alertBox) alertBox.classList.add('d-none');

        // Configure video for iOS Safari & Android inline playback
        if (video) {
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            video.setAttribute('autoplay', 'true');
            video.muted = true;
        }

        try {
            this.mediaStream = await this.getCameraMediaStream();
            if (video) {
                video.srcObject = this.mediaStream;
                await video.play().catch(e => console.warn('Video play warning:', e));
            }

            if (gpsStatus) gpsStatus.innerText = 'Camera & GPS Active';
            if (fallbackOverlay) {
                fallbackOverlay.classList.add('d-none');
                fallbackOverlay.classList.remove('d-flex');
            }
            if (frameOverlay) frameOverlay.classList.remove('d-none');

            // Start QR Frame Decoding Loop
            this.isScanning = true;
            if (!this.scanCanvas) {
                this.scanCanvas = document.createElement('canvas');
                this.scanCanvasContext = this.scanCanvas.getContext('2d', { willReadFrequently: true });
            }
            this.scannerStartTime = Date.now();
            this.tickQrScanner();
        } catch (err) {
            console.error('Camera startup error:', err);
            if (gpsStatus) gpsStatus.innerText = 'Camera Restricted (Photo Mode)';

            if (fallbackOverlay) {
                fallbackOverlay.classList.remove('d-none');
                fallbackOverlay.classList.add('d-flex');
            }
            if (frameOverlay) frameOverlay.classList.add('d-none');

            if (alertBox) {
                alertBox.classList.remove('d-none');
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    if (alertTitle) alertTitle.innerText = 'Camera Permission Blocked on Mobile Chrome';
                    if (alertDesc) alertDesc.innerHTML = 'Camera permission was denied in your browser. Tap <strong>Snap Photo with Phone Camera</strong> below to scan without restrictions, or enable camera in Chrome site settings.';
                } else if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                    if (alertTitle) alertTitle.innerText = 'Mobile Browser Restricted Live Video on Non-HTTPS Origin';
                    if (alertDesc) alertDesc.innerHTML = 'Google Chrome and Safari require HTTPS for live video streaming on local IP addresses. Tap <strong>Snap Photo with Phone Camera</strong> below to scan seamlessly!';
                } else {
                    if (alertTitle) alertTitle.innerText = 'Live Video Stream Restricted';
                    if (alertDesc) alertDesc.innerHTML = 'Your current mobile browser or in-app view restricted live video. Use <strong>Snap Photo with Phone Camera</strong> below to record attendance!';
                }
            }
        }
    },

    triggerPhotoQrScan() {
        const fileInput = document.getElementById('qr-camera-file-input');
        if (fileInput) {
            fileInput.value = '';
            fileInput.click();
        }
    },

    async handleQrFileInput(input) {
        if (!input.files || input.files.length === 0) return;
        const file = input.files[0];

        this.showToast('Decoding QR code from photo...');
        this.fetchGpsLocation();

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0, img.width, img.height);

                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                if (typeof jsQR !== 'undefined') {
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "attemptBoth"
                    });

                    if (code && code.data) {
                        if (navigator.vibrate) navigator.vibrate(200);
                        this.processQrScan(code.data);
                    } else {
                        alert('Could not detect a valid QR code in this photo. Please make sure the QR code is clear, well-lit, and in focus, then try again.');
                    }
                } else {
                    alert('QR decoding library is still loading. Please try again in a moment.');
                }
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    },

    stopScanner() {
        this.isScanning = false;
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
        if (this.mediaStream) {
            this.mediaStream.getTracks().forEach(track => track.stop());
            this.mediaStream = null;
        }
        if (this.gpsWatchId) {
            navigator.geolocation.clearWatch(this.gpsWatchId);
            this.gpsWatchId = null;
        }
    },

    tickQrScanner() {
        if (!this.isScanning) return;

        const video = document.getElementById('camera-preview');
        if (video && video.readyState === video.HAVE_ENOUGH_DATA) {
            this.scanCanvas.height = video.videoHeight;
            this.scanCanvas.width = video.videoWidth;
            this.scanCanvasContext.drawImage(video, 0, 0, this.scanCanvas.width, this.scanCanvas.height);

            const imageData = this.scanCanvasContext.getImageData(0, 0, this.scanCanvas.width, this.scanCanvas.height);
            
            if (typeof jsQR !== 'undefined') {
                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: "dontInvert",
                });

                if (code && code.data) {
                    const now = Date.now();
                    const scanAge = now - this.scannerStartTime;

                    // Require 4 seconds of GPS warmup/calibration to defeat "turn off fake GPS" caching tricks and verify natural movement
                    if (scanAge < 4000) {
                        const remaining = Math.ceil((4000 - scanAge) / 1000);
                        const gpsStatus = document.getElementById('gps-status-text');
                        if (gpsStatus) gpsStatus.innerText = `Calibrating GPS... Please hold still (${remaining}s)`;
                        
                        // Show toast only once per second
                        if (!this._lastToastTime || (now - this._lastToastTime > 1000)) {
                            this.showToast(`Calibrating GPS security... Please wait ${remaining}s`);
                            this._lastToastTime = now;
                        }
                        this.animationFrameId = requestAnimationFrame(() => this.tickQrScanner());
                        return;
                    } else {
                        const gpsStatus = document.getElementById('gps-status-text');
                        if (gpsStatus && gpsStatus.innerText.includes('Calibrating')) {
                            gpsStatus.innerText = 'Camera & GPS Active';
                        }
                    }

                    if (code.data !== this.lastScannedToken || (now - this.lastScannedTime > 3000)) {
                        this.lastScannedToken = code.data;
                        this.lastScannedTime = now;
                        this.isScanning = false; // Pause scanner while modal is shown

                        if (navigator.vibrate) navigator.vibrate(200);

                        this.processQrScan(code.data);
                        return;
                    }
                }
            }
        }

        this.animationFrameId = requestAnimationFrame(() => this.tickQrScanner());
    },

    fetchGpsLocation() {
        const statusEl = document.getElementById('gps-coords');
        if (!navigator.geolocation) {
            if (statusEl) statusEl.innerText = 'GPS not supported';
            return;
        }

        if (this.gpsWatchId) {
            navigator.geolocation.clearWatch(this.gpsWatchId);
            this.gpsWatchId = null;
        }

        // Start continuous high-accuracy GNSS watch positioning (maximumAge: 0 forces fresh satellite lock)
        this.gpsWatchId = navigator.geolocation.watchPosition(
            (position) => {
                const newLat = position.coords.latitude;
                const newLng = position.coords.longitude;
                const now = Date.now();

                // Live stream jump check against previous fix in the same session
                if (this.lastGpsPoint) {
                    const timeDeltaSec = (now - this.lastGpsPoint.time) / 1000;
                    const distMeters = this.calculateDistanceMeters(newLat, newLng, this.lastGpsPoint.lat, this.lastGpsPoint.lng);
                    
                    // If position jumped > 80m in under 30s with speed > 45 km/h (impossible live jump while using the scanner)
                    if (timeDeltaSec > 0 && timeDeltaSec < 30 && distMeters > 80) {
                        const speedKmh = (distMeters / 1000) / (timeDeltaSec / 3600);
                        if (speedKmh > 45) {
                            console.warn('Live GPS jump detected:', distMeters, 'm in', timeDeltaSec, 's =', speedKmh, 'km/h');
                            this.mockLocationDetected = true;
                            this.mockLocationReason = `Sudden GPS position jump detected (${Math.round(distMeters)}m in ${Math.round(timeDeltaSec)}s = ${Math.round(speedKmh)} km/h). Mock Location / Fake GPS app detected.`;
                        }
                    }
                }
                this.lastGpsPoint = { lat: newLat, lng: newLng, time: now };

                this.currentGps.latitude = newLat;
                this.currentGps.longitude = newLng;
                this.currentGps.accuracy = position.coords.accuracy;
                this.currentGps.altitude = position.coords.altitude;
                this.currentGps.speed = position.coords.speed;
                this.currentGps.error = null;

                // Anti-Spoofing: Track jitter history (up to 10 points), throttled to ~1 point per second
                const lastHist = this.gpsHistory.length > 0 ? this.gpsHistory[this.gpsHistory.length - 1] : null;
                if (!lastHist || (now - lastHist.time >= 800)) {
                    if (this.gpsHistory.length >= 10) this.gpsHistory.shift();
                    this.gpsHistory.push({
                        lat: newLat,
                        lng: newLng,
                        accuracy: position.coords.accuracy,
                        altitude: position.coords.altitude,
                        time: now
                    });
                }

                if (statusEl) {
                    const acc = Math.round(position.coords.accuracy || 0);
                    statusEl.innerText = `${position.coords.latitude.toFixed(5)}, ${position.coords.longitude.toFixed(5)} (±${acc}m)`;
                }
            },
            (err) => {
                this.currentGps.error = err.message;
                if (statusEl) statusEl.innerText = 'GPS Access Denied / Unavailable';
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    },

    async submitManualQrScan() {
        const qrInput = document.getElementById('qr-manual-token-input').value.trim();
        if (!qrInput) {
            alert('Please paste or scan a dynamic QR code token.');
            return;
        }

        this.processQrScan(qrInput);
    },

    async processQrScan(qrToken) {
        const inputEl = document.getElementById('qr-manual-token-input');
        if (inputEl) inputEl.value = qrToken;

        if (!this.currentGps.latitude || !this.currentGps.longitude) {
            alert('GPS location permission is required for attendance validation. Locating...');
            this.fetchGpsLocation();
            this.isScanning = true;
            this.tickQrScanner();
            return;
        }

        // --- Anti-Spoofing Checks (Fake GPS Mitigation) ---
        const isSuspicious = this.runAntiSpoofingChecks();
        if (isSuspicious.flagged) {
            alert('⚠️ ' + isSuspicious.reason);
            // Reset calibration state and history to force a fresh 4-second warmup
            this.gpsHistory = [];
            this.scannerStartTime = Date.now();
            this.isScanning = true;
            this.tickQrScanner();
            return;
        }

        let deviceCred = StorageManager.getDeviceCredential();
        if (!deviceCred) {
            deviceCred = 'DEV-' + Date.now() + '-' + Math.random().toString(36).substring(2, 10);
            StorageManager.setDeviceCredential(deviceCred);
        }

        const nowLocal = new Date();
        const year = nowLocal.getFullYear();
        const month = String(nowLocal.getMonth() + 1).padStart(2, '0');
        const day = String(nowLocal.getDate()).padStart(2, '0');
        const hours = String(nowLocal.getHours()).padStart(2, '0');
        const minutes = String(nowLocal.getMinutes()).padStart(2, '0');
        const seconds = String(nowLocal.getSeconds()).padStart(2, '0');
        const localScanTime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;

        const scanPayload = {
            local_record_id: 'OFF-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5),
            qr_token: qrToken,
            device_credential: deviceCred,
            latitude: this.currentGps.latitude,
            longitude: this.currentGps.longitude,
            scan_time: localScanTime
        };

        if (!navigator.onLine) {
            StorageManager.addOfflineRecord(scanPayload);
            this.showOfflineSavedModal(scanPayload);
            return;
        }

        const res = await StorageManager.apiRequest('/api/attendance/scan', {
            method: 'POST',
            body: JSON.stringify(scanPayload)
        });

        if (res.status === 0) {
            // Network connection dropped or timed out
            StorageManager.addOfflineRecord(scanPayload);
            this.showOfflineSavedModal(scanPayload);
            return;
        }

        this.showScanResultModal(res);
    },

    runAntiSpoofingChecks() {
        // 1. Live stream jump detection flag
        if (this.mockLocationDetected) {
            const reason = this.mockLocationReason || 'Sudden GPS jump detected. Fake GPS / Mock Location apps are prohibited.';
            return { flagged: true, reason };
        }

        // 2. Strict Zero-Jitter / Static Mock Lock Check (Absolute Movement Requirement)
        // Genuine satellite locks ALWAYS have micro-fluctuations (jitter).
        // Fake GPS apps without a 'jitter' feature, or turned off Fake GPS apps, produce perfectly static coordinates (0 movement).
        if (this.gpsHistory.length >= 4) {
            const recent = this.gpsHistory.slice(-4);
            const isStatic = recent.every(p => 
                Math.abs(p.lat - recent[0].lat) < 0.000005 && 
                Math.abs(p.lng - recent[0].lng) < 0.000005
            );
            
            const timeSpan = recent[recent.length - 1].time - recent[0].time;
            
            // If there is ZERO movement over at least 2 seconds, it is mathematically impossible for a raw GPS lock.
            // We block this unconditionally to stop Fake GPS bypassing.
            if (isStatic && timeSpan >= 2000) {
                return { 
                    flagged: true, 
                    reason: 'Suspicious GPS signal (Zero Movement Detected). Your location is perfectly static, which indicates a Fake GPS or Mock Location app. Please turn it off, walk around to get a genuine moving satellite lock, and try again.' 
                };
            }

            // Detect split-second toggling: If the user toggles Fake GPS off and on, it injects a 5-20 meter jump.
            // A 10m jump in 1 second is 36 km/h. No one scans a QR code while sprinting at 36 km/h.
            let maxSpeedKmh = 0;
            for (let i = 1; i < recent.length; i++) {
                const distM = this.calculateDistanceMeters(recent[i].lat, recent[i].lng, recent[i-1].lat, recent[i-1].lng);
                const timeS = (recent[i].time - recent[i-1].time) / 1000;
                if (timeS > 0) {
                    const speed = (distM / 1000) / (timeS / 3600);
                    if (speed > maxSpeedKmh) maxSpeedKmh = speed;
                }
            }

            if (maxSpeedKmh > 12) {
                return {
                    flagged: true,
                    reason: `Unnatural erratic movement detected (${Math.round(maxSpeedKmh)} km/h) while scanning. This indicates Fake GPS tampering or toggling. Please hold still and try again.`
                };
            }
        }

        // 3. Active Fake GPS Signature Check
        // When Fake GPS is actively running, it often produces flat/perfect integer accuracies (like exactly 10m, 65m, 100m)
        // and almost always fails to spoof 3D altitude (returns null or exactly 0).
        // Real satellite locks almost always provide a float accuracy and a valid altitude value.
        if (this.currentGps.altitude === null || this.currentGps.altitude === 0) {
            // Check if accuracy is a suspiciously perfect integer often hardcoded by mock apps
            const suspiciousFlatAccuracies = [1, 5, 10, 12, 15, 20, 50, 65, 100, 500];
            if (suspiciousFlatAccuracies.includes(this.currentGps.accuracy)) {
                return {
                    flagged: true,
                    reason: 'GPS signal matches a Fake GPS signature (2D mock lock with simulated accuracy). Please turn off your Mock Location app. If you are legitimate, step outside to get a real 3D satellite lock before scanning.'
                };
            }
        }

        // 3. Teleportation / Fast Travel Speed Check (Across scans or app switch)
        const lastScanJson = localStorage.getItem('bsis_last_known_loc');
        if (lastScanJson) {
            try {
                const lastScan = JSON.parse(lastScanJson);
                const timeDiffHours = (Date.now() - lastScan.time) / (1000 * 60 * 60);
                const timeDiffSeconds = (Date.now() - lastScan.time) / 1000;

                // Evaluate within 45 minutes
                if (timeDiffHours > 0 && timeDiffHours < 0.75) {
                    const distMeters = this.calculateDistanceMeters(this.currentGps.latitude, this.currentGps.longitude, lastScan.lat, lastScan.lng);
                    const speedKmph = (distMeters / 1000) / timeDiffHours;
                    
                    // If jumped > 100m in under 15s or traveled > 50 km/h
                    if ((distMeters > 100 && timeDiffSeconds <= 15) || (distMeters > 150 && speedKmph > 50)) {
                        return { 
                            flagged: true, 
                            reason: `Impossible travel speed detected (${Math.round(distMeters)}m in ${Math.round(timeDiffSeconds)}s = ${Math.round(speedKmph)} km/h). Mock Location / Fake GPS detected.` 
                        };
                    }
                }
            } catch (e) {}
        }

        // Always save current location for next comparison
        localStorage.setItem('bsis_last_known_loc', JSON.stringify({
            lat: this.currentGps.latitude,
            lng: this.currentGps.longitude,
            time: Date.now()
        }));

        return { flagged: false };
    },

    calculateDistanceMeters(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Earth's radius in meters
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return Math.round(R * c);
    },

    showOfflineSavedModal(item) {
        const modal = document.getElementById('scan-result-modal');
        const content = document.getElementById('scan-result-content');

        content.innerHTML = `
            <div class="text-center">
                <div class="mb-3">
                    <span class="bsis-badge bsis-badge-warning" style="font-size: 1rem; padding: 8px 20px;">⚡ SAVED OFFLINE</span>
                </div>
                <h5 class="text-warning font-weight-bold mb-2">Network Disconnected / Slow</h5>
                <p class="text-muted small mb-3">Your attendance scan was securely captured and timestamped locally on this device at <strong>${item.scan_time}</strong>.</p>
                
                <div class="card bg-light p-3 border-0 rounded-3 mb-3 text-start small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Sync Status:</span>
                        <span class="text-warning font-weight-bold">Queued for Auto-Sync</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Recorded Time:</span>
                        <span class="font-weight-bold">${item.scan_time}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">GPS Coordinates:</span>
                        <span class="font-weight-bold">${item.latitude?.toFixed(4)}, ${item.longitude?.toFixed(4)}</span>
                    </div>
                </div>
                <p class="text-success small mb-0"><i class="bi bi-arrow-repeat"></i> Will automatically upload to server as soon as internet connection is restored.</p>
            </div>
        `;

        modal.classList.remove('d-none');
    },

    playSuccessChime() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.1); // A5
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.35);
        } catch (e) {}
    },

    showScanResultModal(res) {
        const modal = document.getElementById('scan-result-modal');
        const content = document.getElementById('scan-result-content');

        if (res.ok && res.data && res.data.success) {
            // Trigger Mobile Haptic Vibration & Audio Chime
            if ('vibrate' in navigator) {
                try { navigator.vibrate([80, 40, 80]); } catch (e) {}
            }
            this.playSuccessChime();

            const data = res.data.data;
            const scanType = data.scan_type || 'ATTENDANCE SCAN';
            const isWhole = data.session_type === 'whole_day';
            const isTimeout = scanType.includes('Time-Out') || scanType.includes('checkout');

            const badgeColor = isTimeout ? 'bsis-badge-info' : 'bsis-badge-success';
            const icon = isTimeout ? 'bi-box-arrow-right' : 'bi-box-arrow-in-right';

            const badgeHtml = `<span class="bsis-badge ${badgeColor}" style="font-size: 1.05rem; padding: 8px 22px;"><i class="bi ${icon} me-1"></i> ${scanType.toUpperCase()} RECORDED</span>`;

            let slotsHtml = '';
            if (isWhole) {
                const amIn = data.attendance.formatted_am_in || data.attendance.formatted_time || '—';
                const amOut = data.attendance.formatted_am_out || '—';
                const pmIn = data.attendance.formatted_pm_in || '—';
                const pmOut = data.attendance.formatted_pm_out || data.attendance.formatted_checkout_time || '—';

                slotsHtml = `
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="p-2 border rounded bg-white">
                                <small class="text-muted d-block">🌅 AM Time-In</small>
                                <strong class="font-monospace ${amIn !== '—' ? 'text-success' : 'text-muted'}">${amIn}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded bg-white">
                                <small class="text-muted d-block">🍱 AM Time-Out</small>
                                <strong class="font-monospace ${amOut !== '—' ? 'text-primary' : 'text-muted'}">${amOut}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded bg-white">
                                <small class="text-muted d-block">🌤️ PM Time-In</small>
                                <strong class="font-monospace ${pmIn !== '—' ? 'text-success' : 'text-muted'}">${pmIn}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded bg-white">
                                <small class="text-muted d-block">🌇 PM Time-Out</small>
                                <strong class="font-monospace ${pmOut !== '—' ? 'text-primary' : 'text-muted'}">${pmOut}</strong>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                const timeInDisplay = data.attendance.formatted_time || '— (No Time-In Recorded)';
                const timeOutDisplay = data.attendance.formatted_checkout_time || (isTimeout ? '—' : '— (Not yet scanned)');
                slotsHtml = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small"><i class="bi bi-box-arrow-in-right text-success me-1"></i> Time-In:</span>
                        <strong class="${data.attendance.formatted_time ? 'text-success font-monospace fw-bold' : 'text-muted font-monospace'}">${timeInDisplay}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small"><i class="bi bi-box-arrow-right text-info me-1"></i> Time-Out:</span>
                        <strong class="${data.attendance.formatted_checkout_time ? 'text-primary font-monospace fw-bold' : 'text-muted font-monospace'}">${timeOutDisplay}</strong>
                    </div>
                `;
            }

            content.innerHTML = `
                <div class="text-center">
                    <div class="mb-3">
                        ${badgeHtml}
                    </div>
                    <h4 class="text-primary-dark font-weight-bold mb-1">${data.event.title}</h4>
                    <p class="text-muted small mb-3">${data.event.venue_name}</p>
                    
                    <div class="card bg-light p-3 border-0 rounded-3 mb-3 text-start">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <span class="text-muted small fw-bold">Session Mode:</span>
                            <span class="badge bg-white text-dark border fw-bold">${isWhole ? 'EVENT (4 Scans)' : 'EVENT (2 Scans)'}</span>
                        </div>
                        ${slotsHtml}
                        ${data.attendance.fine_amount > 0 ? `
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="text-danger small fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Accumulated Fine:</span>
                            <strong class="text-danger font-monospace">₱${parseFloat(data.attendance.fine_amount).toFixed(2)}</strong>
                        </div>
                        ` : ''}
                    </div>
                    <p class="text-muted small mb-0"><i class="bi bi-geo-alt-fill text-success me-1"></i> Verified GPS Location (${data.attendance.distance_meters}m from venue center).</p>
                </div>
            `;
        } else {
            const reason = res.data?.message || 'Attendance submission rejected.';
            content.innerHTML = `
                <div class="text-center">
                    <div class="mb-3">
                        <span class="bsis-badge bsis-badge-danger" style="font-size: 1rem; padding: 8px 20px;"><i class="bi bi-x-circle-fill me-1"></i> ATTENDANCE REJECTED</span>
                    </div>
                    <h5 class="text-danger font-weight-bold mb-2">Scan Validation Notice</h5>
                    <p class="text-muted small">${reason}</p>
                </div>
            `;
        }

        modal.classList.remove('d-none');
    },

    closeScanModal() {
        document.getElementById('scan-result-modal').classList.add('d-none');
        if (window.location.hash === '#scanner') {
            this.isScanning = true;
            this.tickQrScanner();
        } else {
            window.location.hash = '#dashboard';
            this.loadDashboard();
        }
    },

    // 5. ATTENDANCE HISTORY
    async loadHistory() {
        const res = await StorageManager.apiRequest('/api/attendance');
        const container = document.getElementById('history-list');
        if (res.ok && res.data.data && res.data.data.data.length > 0) {
            // Sort: Latest active/ongoing attended events on TOP (0), followed by done/completed events (1) by most recent date
            const records = [...res.data.data.data].sort((a, b) => {
                const aIsActive = (a.event?.status === 'active') ? 0 : 1;
                const bIsActive = (b.event?.status === 'active') ? 0 : 1;
                if (aIsActive !== bIsActive) {
                    return aIsActive - bIsActive;
                }
                const aTime = new Date(a.pm_time_out || a.pm_time_in || a.am_time_out || a.am_time_in || a.checkout_time || a.scan_time || a.created_at || 0).getTime();
                const bTime = new Date(b.pm_time_out || b.pm_time_in || b.am_time_out || b.am_time_in || b.checkout_time || b.scan_time || b.created_at || 0).getTime();
                return bTime - aTime;
            });

            container.innerHTML = records.map(item => {
                const isActive = item.event?.status === 'active';
                const isWhole = item.event?.session_type === 'whole_day' || item.am_time_in || item.pm_time_in;
                const statusBadge = `<span class="bsis-badge ${item.status === 'present' ? 'bsis-badge-success' : (item.status === 'late' ? 'bsis-badge-warning' : (item.status === 'absent' ? 'bsis-badge-danger' : 'bsis-badge-info'))}">${item.status.toUpperCase()}</span>`;
                const eventStateBadge = isActive 
                    ? '<span class="badge bg-primary text-white py-1 px-2"><i class="bi bi-broadcast me-1"></i> ACTIVE EVENT</span>'
                    : '<span class="badge bg-light text-muted border py-1 px-2"><i class="bi bi-check-circle me-1"></i> COMPLETED</span>';

                const fmt = (t) => t ? new Date(t).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true }) : '<span class="text-muted">—</span>';

                let breakdownHtml = '';
                if (isWhole) {
                    breakdownHtml = `
                        <div class="row g-2 mb-2 small text-start">
                            <div class="col-6">
                                <div class="bg-white p-1 rounded border px-2">
                                    <span class="text-muted d-block" style="font-size:0.72rem;">AM In:</span>
                                    <strong class="font-monospace ${item.am_time_in || item.scan_time ? 'text-success' : 'text-muted'}">${fmt(item.am_time_in || item.scan_time)}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-white p-1 rounded border px-2">
                                    <span class="text-muted d-block" style="font-size:0.72rem;">AM Out:</span>
                                    <strong class="font-monospace ${item.am_time_out ? 'text-primary' : 'text-muted'}">${fmt(item.am_time_out)}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-white p-1 rounded border px-2">
                                    <span class="text-muted d-block" style="font-size:0.72rem;">PM In:</span>
                                    <strong class="font-monospace ${item.pm_time_in ? 'text-success' : 'text-muted'}">${fmt(item.pm_time_in)}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-white p-1 rounded border px-2">
                                    <span class="text-muted d-block" style="font-size:0.72rem;">PM Out:</span>
                                    <strong class="font-monospace ${item.pm_time_out || item.checkout_time ? 'text-primary' : 'text-muted'}">${fmt(item.pm_time_out || item.checkout_time)}</strong>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    const scanTime = item.scan_time ? new Date(item.scan_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true }) : '— (No Time-In)';
                    const checkoutTime = item.checkout_time ? new Date(item.checkout_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true }) : '— (No Time-Out)';
                    breakdownHtml = `
                        <div class="bg-light p-2 rounded-2 mb-2 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted"><i class="bi bi-box-arrow-in-right text-success me-1"></i> Time-In:</span>
                                <strong class="font-monospace ${item.scan_time ? 'text-success' : 'text-muted'}">${scanTime}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-box-arrow-right text-info me-1"></i> Time-Out:</span>
                                <strong class="font-monospace ${item.checkout_time ? 'text-primary' : 'text-muted'}">${checkoutTime}</strong>
                            </div>
                        </div>
                    `;
                }

                return `
                <div class="bsis-card mb-3 p-3 ${isActive ? 'border-primary border-2 shadow-sm' : ''}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex gap-1 align-items-center">
                            ${statusBadge}
                            ${eventStateBadge}
                        </div>
                        <span class="text-muted small">${new Date(item.scan_time || item.checkout_time || item.created_at).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                    </div>
                    <h6 class="mb-2 text-primary fw-bold">${item.event?.title || 'College Event'}</h6>
                    
                    ${breakdownHtml}

                    ${parseFloat(item.fine_amount) > 0 ? `
                        <div class="d-flex justify-content-between align-items-center mb-1 text-danger small">
                            <span><i class="bi bi-exclamation-triangle-fill me-1"></i> Total Fine:</span>
                            <strong class="font-monospace">₱${parseFloat(item.fine_amount).toFixed(2)}</strong>
                        </div>
                    ` : ''}
                    
                    <p class="mb-0 text-muted small"><i class="bi bi-geo-alt"></i> ${item.status === 'absent' ? 'Non-attendance (Did not scan)' : 'Verified GPS distance: ' + (item.distance_meters || 0) + 'm'}</p>
                </div>
            `;}).join('');
        } else {
            container.innerHTML = `
                <div class="bsis-empty-state py-5">
                    <div class="bsis-empty-icon">
                        <i class="bi bi-calendar2-x"></i>
                    </div>
                    <div class="bsis-empty-title">No Attendance Records Yet</div>
                    <p class="bsis-empty-subtitle">Your verified event attendance scans will be displayed here.</p>
                </div>
            `;
        }
    },

    // 6. FINES
    async loadFines() {
        const user = StorageManager.getUser();
        if (!user) return;

        const res = await StorageManager.apiRequest(`/api/students/${user.id}/fines`);
        const container = document.getElementById('fines-list');
        const totalEl = document.getElementById('fines-unpaid-total');

        if (res.ok && res.data.data) {
            const data = res.data.data;
            totalEl.innerText = `₱${data.summary.unpaid_fines.toFixed(2)}`;

            if (data.fines_history && data.fines_history.length > 0) {
                // Ensure UNPAID fines are strictly at the top, and PAID/WAIVED fines are below
                const sortedFines = [...data.fines_history].sort((a, b) => {
                    const aPaid = a.fine_paid ? 1 : 0;
                    const bPaid = b.fine_paid ? 1 : 0;
                    if (aPaid !== bPaid) {
                        return aPaid - bPaid; // 0 (Unpaid) first, 1 (Paid) second
                    }
                    const aTime = new Date(a.scan_time || a.created_at || 0).getTime();
                    const bTime = new Date(b.scan_time || b.created_at || 0).getTime();
                    return bTime - aTime;
                });

                container.innerHTML = sortedFines.map(item => {
                    const isPaid = item.fine_paid === true || item.fine_paid == 1;
                    const isWaived = isPaid && (parseFloat(item.fine_amount || 0) <= 0 || item.verification_data?.waive_details);
                    
                    let badgeHtml = '';
                    let amountHtml = '';
                    let cardBorder = '';

                    if (isWaived) {
                        badgeHtml = '<span class="bsis-badge bsis-badge-info"><i class="bi bi-shield-check me-1"></i> WAIVED</span>';
                        amountHtml = '<span class="text-info font-weight-bold">₱0.00 <small class="text-muted fw-normal">(Excused)</small></span>';
                        cardBorder = 'opacity-75';
                    } else if (isPaid) {
                        badgeHtml = '<span class="bsis-badge bsis-badge-success"><i class="bi bi-check2-circle me-1"></i> PAID</span>';
                        amountHtml = `<span class="text-success font-weight-bold">₱${parseFloat(item.fine_amount).toFixed(2)} <small class="text-muted fw-normal">(Settled)</small></span>`;
                        cardBorder = 'opacity-75';
                    } else {
                        badgeHtml = '<span class="bsis-badge bsis-badge-danger"><i class="bi bi-exclamation-circle me-1"></i> UNPAID</span>';
                        amountHtml = `<span class="text-danger font-weight-bold">₱${parseFloat(item.fine_amount).toFixed(2)}</span>`;
                        cardBorder = 'border-start border-3 border-danger';
                    }

                    const incurredDate = item.scan_time ? new Date(item.scan_time).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';

                    return `
                        <div class="bsis-card mb-3 p-3 shadow-sm ${cardBorder}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                ${badgeHtml}
                                ${amountHtml}
                            </div>
                            <h6 class="mb-1 text-primary-dark fw-bold">${item.event?.title || 'Attendance Fine'}</h6>
                            <div class="d-flex justify-content-between text-muted small">
                                <span><i class="bi bi-calendar-event me-1"></i> ${incurredDate}</span>
                                <span>Violation: <strong class="${item.status === 'absent' ? 'text-danger' : 'text-warning'}">${(item.status || 'Late / Missed').toUpperCase()}</strong></span>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = `
                    <div class="bsis-empty-state py-5">
                        <div class="bsis-empty-icon success">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="bsis-empty-title">All Clear & Caught Up!</div>
                        <p class="bsis-empty-subtitle">You have no pending or unpaid fines on record. Keep up the good attendance!</p>
                    </div>
                `;
            }
        }
    },

    // 7. DEVICE MANAGEMENT & RESET REQUEST
    async loadDeviceInfo() {
        const cred = StorageManager.getDeviceCredential();
        document.getElementById('device-cred-display').innerText = cred || 'No device bound';
    },

    async handleDeviceResetSubmit(event) {
        event.preventDefault();
        const reason = document.getElementById('reset-reason-input').value.trim();
        const alertBox = document.getElementById('device-reset-alert');

        if (reason.length < 5) {
            alertBox.innerText = 'Please enter a clear reason (at least 5 characters).';
            alertBox.classList.remove('d-none');
            return;
        }

        const res = await StorageManager.apiRequest('/api/devices/reset-request', {
            method: 'POST',
            body: JSON.stringify({ reason })
        });

        if (res.ok && res.data.success) {
            alertBox.className = 'alert alert-success mt-2';
            alertBox.innerText = 'Device reset request submitted! Awaiting administrator review.';
            alertBox.classList.remove('d-none');
            document.getElementById('device-reset-form').reset();
        } else {
            alertBox.className = 'alert alert-danger mt-2';
            alertBox.innerText = res.data?.message || 'Failed to submit reset request.';
            alertBox.classList.remove('d-none');
        }
    },

    async logout() {
        if (!confirm('Are you sure you want to sign out of your account?')) return;
        try {
            await StorageManager.apiRequest('/api/auth/logout', { method: 'POST' });
        } catch (e) {}
        StorageManager.clearSession();
        window.location.hash = '#login';
        this.handleRoute();
    },

    showToast(msg) {
        const toast = document.createElement('div');
        toast.className = 'bsis-toast';
        toast.innerText = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }
};
