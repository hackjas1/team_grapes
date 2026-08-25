/* BSIS Admin & Event Staff Desktop Dashboard Controller */

document.addEventListener('DOMContentLoaded', () => {
    AdminApp.init();
});

const AdminApp = {
    activeQrInterval: null,
    activeLivePollInterval: null,
    lastScanId: 0,
    currentQrDurationSeconds: 20,
    statusChartInstance: null,
    sessionChartInstance: null,
    eventMap: null,
    eventMarker: null,
    eventRadiusCircle: null,
    editEventMap: null,
    editEventMarker: null,
    editEventRadiusCircle: null,
    usersCurrentPage: 1,
    usersPerPage: 25,
    userSearchDebounceTimer: null,
    fineSearchDebounceTimer: null,
    reportSearchDebounceTimer: null,
    loginUserExplicit: false,
    calendarCurrentYear: new Date().getFullYear(),
    calendarCurrentMonth: new Date().getMonth(),
    selectedCalendarDate: null,
    cachedEvents: [],
    detailEventMap: null,
    detailEventMarker: null,
    detailEventRadiusCircle: null,

    init() {
        window.addEventListener('hashchange', () => this.handleRoute());
        window.addEventListener('afterprint', () => {
            const printArea = document.getElementById('printable-fines-area');
            if (printArea) printArea.innerHTML = '';
            window.location.hash = '#fines';
            this.showView('view-fines');
        });

        // Close autocomplete popups when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.bsis-autocomplete-container')) {
                document.querySelectorAll('.bsis-autocomplete-dropdown').forEach(el => el.style.display = 'none');
            }
        });

        // Prevent auto-login on browser password manager autofill
        const adminLoginForm = document.getElementById('admin-login-form') || document.querySelector('#view-login form');
        const adminLoginBtn = document.getElementById('admin-login-btn');
        if (adminLoginBtn) {
            adminLoginBtn.addEventListener('click', () => { this.loginUserExplicit = true; });
            adminLoginBtn.addEventListener('touchstart', () => { this.loginUserExplicit = true; }, { passive: true });
        }
        if (adminLoginForm) {
            adminLoginForm.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') this.loginUserExplicit = true;
            });
        }

        this.initSidebarState();
        this.initKeyboardShortcuts();
        this.updateLiveEventBanner();
        this.handleRoute();

        // Close profile popup when clicking outside
        document.addEventListener('click', (e) => {
            const popup = document.getElementById('admin-profile-popup');
            const trigger = document.getElementById('adminProfileTrigger');
            if (popup && !popup.classList.contains('d-none')) {
                if (!popup.contains(e.target) && !trigger?.contains(e.target)) {
                    this.closeProfilePopup();
                }
            }
        });

        // Listen for Create Event Modal show to invalidate & render Leaflet Map
        const modalEl = document.getElementById('modal-create-event');
        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', () => {
                setTimeout(() => {
                    this.initCreateEventMap();
                }, 100);
                const dateInput = document.getElementById('event-date');
                if (dateInput && !dateInput.value) {
                    const today = new Date();
                    const pad = n => String(n).padStart(2, '0');
                    dateInput.value = `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;
                }
            });
        }

        // Listen for Edit Event Modal show to invalidate & render Leaflet Map
        const editModalEl = document.getElementById('modal-edit-event');
        if (editModalEl) {
            editModalEl.addEventListener('shown.bs.modal', () => {
                setTimeout(() => {
                    this.initEditEventMap();
                }, 100);
            });
        }

        // Listen for Manual Override Modal show to populate active events
        const overrideModalEl = document.getElementById('modal-manual-override');
        if (overrideModalEl) {
            overrideModalEl.addEventListener('shown.bs.modal', () => {
                this.populateOverrideEventsDropdown();
            });
        }

        // Responsive Layout Resize Watcher
        window.addEventListener('resize', () => {
            const mainContent = document.querySelector('.admin-content');
            if (document.body.classList.contains('in-login-view')) return;
            if (window.innerWidth >= 992) {
                const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed') || document.body.classList.contains('sidebar-collapsed');
                if (mainContent) mainContent.style.marginLeft = isCollapsed ? '72px' : '260px';
                this.closeMobileSidebar();
            } else {
                if (mainContent) mainContent.style.marginLeft = '0';
            }
        });

        // Ensure table action dropdowns float freely without container clipping
        document.addEventListener('show.bs.dropdown', (e) => {
            const toggle = e.target;
            const menu = toggle?.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                menu.style.position = 'fixed';
                menu.style.zIndex = '1070';
            }
        });

        // Fullscreen Change & Escape key listeners for QR Display
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                this.applyCssFullscreen(false);
            } else {
                this.applyCssFullscreen(true);
            }
        });
        document.addEventListener('webkitfullscreenchange', () => {
            if (!document.webkitFullscreenElement) {
                this.applyCssFullscreen(false);
            } else {
                this.applyCssFullscreen(true);
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && (document.body.classList.contains('qr-fullscreen-active') || document.getElementById('view-qr-display')?.classList.contains('is-fullscreen'))) {
                this.applyCssFullscreen(false);
            }
        });
    },

    handleRoute() {
        const hash = window.location.hash || '#overview';
        const user = StorageManager.getUser();
        const token = StorageManager.getToken();

        // If unauthenticated or student role, show Admin Login View
        if (!token || !user || (user.role !== 'admin' && user.role !== 'event_staff')) {
            this.showView('view-login');
            return;
        }

        const isAdmin = user.role === 'admin';
        const isStaff = user.role === 'event_staff';

        // Display Admin Profile in Sidebar & Mobile Header
        const nameEl = document.getElementById('admin-name-display');
        const roleEl = document.getElementById('admin-role-badge');
        const mobileRoleEl = document.getElementById('admin-mobile-role-badge');
        const initialEl = document.getElementById('admin-avatar-initial');
        const menuNameEl = document.getElementById('admin-menu-name');
        const menuRoleEl = document.getElementById('admin-menu-role');
        const menuEmailEl = document.getElementById('admin-menu-email');

        const initial = (user.first_name ? user.first_name.charAt(0) : (user.full_name ? user.full_name.charAt(0) : 'A')).toUpperCase();
        if (initialEl) initialEl.innerText = initial;
        if (nameEl) nameEl.innerText = user.full_name || 'Admin Account';
        if (menuNameEl) menuNameEl.innerText = user.full_name || 'Admin Account';
        if (menuRoleEl) {
            menuRoleEl.innerText = isAdmin ? 'ADMINISTRATOR' : 'EVENT STAFF';
            menuRoleEl.className = `bsis-badge ${isAdmin ? 'bsis-badge-danger' : 'bsis-badge-info'}`;
        }
        if (menuEmailEl) menuEmailEl.innerText = user.email || 'admin@tpc-bsis.online';

        if (roleEl) {
            roleEl.innerText = isAdmin ? 'ADMINISTRATOR' : 'EVENT STAFF';
            roleEl.className = `bsis-badge ${isAdmin ? 'bsis-badge-danger' : 'bsis-badge-info'}`;
        }
        if (mobileRoleEl) {
            mobileRoleEl.innerText = isAdmin ? 'ADMIN' : 'STAFF';
            mobileRoleEl.className = `bsis-badge ${isAdmin ? 'bsis-badge-danger' : 'bsis-badge-info'} py-1 px-2`;
        }

        // Apply Role-Based Navigation & Button Visibility
        document.querySelectorAll('.admin-only-nav, [data-admin-only="true"], .admin-only-btn, .admin-only-section').forEach(el => {
            el.style.display = isAdmin ? '' : 'none';
        });
        document.querySelectorAll('.staff-only-btn').forEach(el => {
            if (isStaff) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        });

        // Role-Based Route Guard for Event Staff
        if (!isAdmin && ['#users', '#device-resets', '#audit-logs', '#backups'].includes(hash)) {
            window.location.hash = '#overview';
            this.showToast('ℹ️ Restricted area: Redirected to event operations dashboard.');
            return;
        }

        // Stop active timers when switching views
        this.stopQrTimer();
        this.stopLivePoll();
        this.updateLiveEventBanner();

        if (hash.startsWith('#qr-display')) {
            const eventId = new URLSearchParams(hash.split('?')[1]).get('event');
            this.showView('view-qr-display');
            this.startQrDisplay(eventId);
        } else if (hash === '#events') {
            this.showView('view-events');
            this.loadEvents();
        } else if (hash === '#live-monitor') {
            this.showView('view-live-monitor');
            this.loadLiveMonitorEventsDropdown();
        } else if (hash === '#users') {
            this.showView('view-users');
            this.loadUsers();
        } else if (hash === '#device-resets') {
            this.showView('view-device-resets');
            this.loadDeviceResets();
        } else if (hash === '#fines') {
            this.showView('view-fines');
            this.loadFines();
        } else if (hash === '#reports') {
            this.showView('view-reports');
            this.loadReports();
        } else if (hash === '#audit-logs') {
            this.showView('view-audit-logs');
            this.loadAuditLogs();
        } else if (hash === '#sync-queue') {
            this.showView('view-sync-queue');
            this.loadSyncQueue();
        } else if (hash === '#backups') {
            this.showView('view-backups');
            this.loadBackups();
        } else {
            this.showView('view-overview');
            this.loadOverview();
        }
    },

    showView(viewId) {
        if (viewId !== 'view-qr-display') {
            this.applyCssFullscreen(false);
            if (document.fullscreenElement) {
                document.exitFullscreen().catch(() => {});
            }
        }

        document.querySelectorAll('.admin-view').forEach(view => view.classList.add('d-none'));
        const targetView = document.getElementById(viewId);
        if (targetView) targetView.classList.remove('d-none');

        // Sidebar & Header visibility
        const sidebar = document.querySelector('.admin-sidebar');
        const mainContent = document.querySelector('.admin-content');
        const mobileHeader = document.querySelector('.admin-mobile-header');
        const liveBanner = document.getElementById('admin-live-event-banner');

        if (viewId === 'view-login') {
            document.documentElement.classList.add('in-login-view');
            document.body.classList.add('in-login-view');
            if (sidebar) sidebar.classList.add('d-none');
            if (mobileHeader) mobileHeader.classList.add('d-none');
            if (liveBanner) liveBanner.classList.add('d-none');
            if (mainContent) mainContent.style.marginLeft = '0';
        } else {
            document.documentElement.classList.remove('in-login-view');
            document.body.classList.remove('in-login-view');
            if (sidebar) sidebar.classList.remove('d-none');
            if (mobileHeader) mobileHeader.classList.remove('d-none');
            if (window.innerWidth >= 992) {
                const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed') || document.body.classList.contains('sidebar-collapsed');
                if (mainContent) mainContent.style.marginLeft = isCollapsed ? '72px' : '260px';
            } else {
                if (mainContent) mainContent.style.marginLeft = '0';
            }
        }

        // Close mobile drawer on navigation
        this.closeMobileSidebar();

        document.querySelectorAll('.sidebar-nav-link').forEach(link => link.classList.remove('active'));
        const activeLink = document.querySelector(`.sidebar-nav-link[href="${window.location.hash || '#overview'}"]`);
        if (activeLink) activeLink.classList.add('active');

        // Smooth scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    toggleSidebarCollapse() {
        const isCurrentlyCollapsed = document.documentElement.classList.contains('sidebar-collapsed') || document.body.classList.contains('sidebar-collapsed');
        const newState = !isCurrentlyCollapsed;

        if (newState) {
            document.documentElement.classList.add('sidebar-collapsed');
            document.body.classList.add('sidebar-collapsed');
        } else {
            document.documentElement.classList.remove('sidebar-collapsed');
            document.body.classList.remove('sidebar-collapsed');
        }

        localStorage.setItem('admin_sidebar_collapsed', newState ? 'true' : 'false');

        // Adjust main content margin on desktop if not in login view
        if (!document.body.classList.contains('in-login-view') && window.innerWidth >= 992) {
            const mainContent = document.querySelector('.admin-content');
            if (mainContent) {
                mainContent.style.marginLeft = newState ? '72px' : '260px';
            }
        }
    },

    initSidebarState() {
        if (localStorage.getItem('admin_sidebar_collapsed') === 'true' && window.innerWidth >= 992) {
            document.documentElement.classList.add('sidebar-collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
    },

    toggleMobileSidebar() {
        const sidebar = document.querySelector('.admin-sidebar');
        const backdrop = document.getElementById('admin-sidebar-backdrop');
        if (sidebar && backdrop) {
            const isShowing = sidebar.classList.contains('show');
            if (isShowing) {
                this.closeMobileSidebar();
            } else {
                sidebar.classList.add('show');
                backdrop.classList.add('show');
                document.body.classList.add('mobile-sidebar-open');
            }
        }
    },

    closeMobileSidebar() {
        const sidebar = document.querySelector('.admin-sidebar');
        const backdrop = document.getElementById('admin-sidebar-backdrop');
        if (sidebar) sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
        document.body.classList.remove('mobile-sidebar-open');
    },

    toggleProfilePopup(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const popup = document.getElementById('admin-profile-popup');
        const trigger = document.getElementById('adminProfileTrigger');
        if (popup) {
            const isHidden = popup.classList.contains('d-none');
            if (isHidden) {
                popup.classList.remove('d-none');
                if (trigger) trigger.classList.add('active');
            } else {
                popup.classList.add('d-none');
                if (trigger) trigger.classList.remove('active');
            }
        }
    },

    closeProfilePopup() {
        const popup = document.getElementById('admin-profile-popup');
        const trigger = document.getElementById('adminProfileTrigger');
        if (popup) popup.classList.add('d-none');
        if (trigger) trigger.classList.remove('active');
    },

    // 0. ADMIN / STAFF LOGIN
    async handleAdminLogin(event) {
        if (event && event.preventDefault) event.preventDefault();

        const loginInput = document.getElementById('admin-login-identifier')?.value.trim() || '';
        const passwordInput = document.getElementById('admin-login-password')?.value || '';
        const btn = document.getElementById('admin-login-btn');
        const alertBox = document.getElementById('admin-login-alert');

        if (!loginInput || !passwordInput) {
            if (alertBox) {
                alertBox.className = 'alert alert-warning text-start py-2 px-3 small';
                alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Please enter your email and password.';
                alertBox.classList.remove('d-none');
            }
            return;
        }

        alertBox.classList.add('d-none');
        btn.disabled = true;
        btn.innerText = 'Authenticating...';

        const res = await StorageManager.apiRequest('/api/auth/login', {
            method: 'POST',
            body: JSON.stringify({
                login: loginInput,
                password: passwordInput,
                device_name: 'Desktop Admin Console'
            })
        });

        btn.disabled = false;
        btn.innerText = 'SIGN IN TO DASHBOARD';

        if (res.ok && res.data && res.data.success) {
            const user = res.data.data.user;
            if (user.role !== 'admin' && user.role !== 'event_staff') {
                alertBox.innerText = 'Access denied. Student accounts must use the Student PWA app.';
                alertBox.classList.remove('d-none');
                return;
            }

            StorageManager.setToken(res.data.data.token);
            StorageManager.setUser(user);
            window.location.hash = '#overview';
            this.handleRoute();
        } else {
            const errorMsg = res.data?.message || 'Invalid login credentials or unauthorized role.';
            const isLocked = res.status === 429 || res.data?.data?.is_locked;
            alertBox.className = `alert ${isLocked ? 'alert-danger' : 'alert-warning'} text-start py-2 px-3 small`;
            alertBox.innerHTML = `<i class="bi ${isLocked ? 'bi-clock-history' : 'bi-exclamation-triangle-fill'} me-1"></i> ${errorMsg}`;
            alertBox.classList.remove('d-none');
        }
    },

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

    // 1. OVERVIEW DASHBOARD & REAL-TIME ANALYTICS GRAPHS
    async loadOverview(eventId = '') {
        const queryParams = eventId ? `?event_id=${eventId}` : '';
        const res = await StorageManager.apiRequest(`/api/dashboard/stats${queryParams}`);
        if (!res.ok || !res.data || !res.data.success) return;

        const d = res.data.data;

        // 1. Populate Event Target Selector Dropdown
        const select = document.getElementById('overview-event-select');
        if (select && d.events_list) {
            this.cachedEvents = d.events_list;
            const currentVal = eventId || (d.selected_event ? d.selected_event.id : '');
            select.innerHTML = d.events_list.map(e => {
                const isActive = e.status === 'active';
                const tag = isActive ? '🔵 [ACTIVE]' : (e.status === 'upcoming' ? '🟠 [UPCOMING]' : '🟢 [COMPLETED]');
                return `<option value="${e.id}" ${e.id == currentVal ? 'selected' : ''}>${tag} ${e.title}</option>`;
            }).join('');

            // Render interactive calendar with event indicators
            this.renderOverviewCalendar();
        }

        // 2. Populate Metrics Cards
        const totalTarget = d.total_target_students ?? 0;
        const present = d.present_count ?? 0;
        const late = d.late_count ?? 0;
        const absent = d.absent_count ?? 0;
        const override = d.manual_override_count ?? 0;
        const attended = d.total_attended ?? 0;
        const turnoutRate = d.attendance_rate_percentage ?? 0;
        const unpaidFines = d.total_unpaid_fines ?? 0;

        const elTarget = document.getElementById('stat-total-target');
        if (elTarget) elTarget.innerText = totalTarget;

        const elPresent = document.getElementById('stat-present-count');
        if (elPresent) elPresent.innerText = present;

        const elLate = document.getElementById('stat-late-count');
        if (elLate) elLate.innerText = late;

        const elAbsent = document.getElementById('stat-absent-count');
        if (elAbsent) elAbsent.innerText = absent;

        const elTurnout = document.getElementById('stat-turnout-rate');
        if (elTurnout) elTurnout.innerText = `${turnoutRate}%`;

        const elAttended = document.getElementById('stat-attended-count');
        if (elAttended) elAttended.innerText = `${attended} Attended`;

        const elFines = document.getElementById('stat-unpaid-fines');
        if (elFines) elFines.innerText = `₱${parseFloat(unpaidFines).toFixed(2)}`;

        if (totalTarget > 0) {
            const elPresentRate = document.getElementById('stat-present-rate');
            if (elPresentRate) elPresentRate.innerText = `${((present / totalTarget) * 100).toFixed(1)}% of roster`;

            const elLateRate = document.getElementById('stat-late-rate');
            if (elLateRate) elLateRate.innerText = `${((late / totalTarget) * 100).toFixed(1)}% of roster`;

            const elAbsentRate = document.getElementById('stat-absent-rate');
            if (elAbsentRate) elAbsentRate.innerText = `${((absent / totalTarget) * 100).toFixed(1)}% of roster`;
        }

        // Executive Turnout Health Gauge (Department Head View)
        const healthGauge = document.getElementById('overview-health-gauge');
        const healthText = document.getElementById('overview-health-gauge-text');
        if (healthGauge && healthText) {
            healthGauge.classList.remove('d-none', 'bsis-health-gauge-optimal', 'bsis-health-gauge-moderate', 'bsis-health-gauge-alert');
            if (turnoutRate >= 85) {
                healthGauge.classList.add('bsis-health-gauge-optimal');
                healthText.innerText = `OPTIMAL COMPLIANCE (${turnoutRate}%)`;
            } else if (turnoutRate >= 70) {
                healthGauge.classList.add('bsis-health-gauge-moderate');
                healthText.innerText = `MODERATE COMPLIANCE (${turnoutRate}%)`;
            } else {
                healthGauge.classList.add('bsis-health-gauge-alert');
                healthText.innerText = `LOW TURNOUT ALERT (${turnoutRate}%)`;
            }
        }

        // Event Tags & Titles
        const selectedEventTitle = d.selected_event ? d.selected_event.title : 'All Today';
        const sessionType = d.selected_event ? (d.selected_event.session_type === 'whole_day' ? 'EVENT (4 Scans)' : 'EVENT (2 Scans)') : 'OVERALL';
        
        const chartTagEl = document.getElementById('chart-event-tag');
        if (chartTagEl) chartTagEl.innerText = selectedEventTitle;

        const sessionBadgeEl = document.getElementById('chart-session-type-badge');
        if (sessionBadgeEl) sessionBadgeEl.innerText = sessionType;

        // 3. Render Status Doughnut Chart
        const legP = document.getElementById('legend-present');
        if (legP) legP.innerText = `${present} (${totalTarget > 0 ? ((present / totalTarget) * 100).toFixed(0) : 0}%)`;

        const legL = document.getElementById('legend-late');
        if (legL) legL.innerText = `${late} (${totalTarget > 0 ? ((late / totalTarget) * 100).toFixed(0) : 0}%)`;

        const legA = document.getElementById('legend-absent');
        if (legA) legA.innerText = `${absent} (${totalTarget > 0 ? ((absent / totalTarget) * 100).toFixed(0) : 0}%)`;

        const legO = document.getElementById('legend-override');
        if (legO) legO.innerText = `${override} (${totalTarget > 0 ? ((override / totalTarget) * 100).toFixed(0) : 0}%)`;

        this.renderOverviewStatusChart(present, late, absent, override);

        // 4. Render Session Bar Chart
        if (d.session_turnout) {
            this.renderOverviewSessionChart(d.session_turnout.labels, d.session_turnout.counts);
        }

        // 5. Populate Recent Scans Table
        const recentTable = document.getElementById('overview-recent-scans-table');
        if (recentTable) {
            if (d.recent_scans && d.recent_scans.length > 0) {
                recentTable.innerHTML = d.recent_scans.map(s => {
                    const statusBadge = s.status === 'present' 
                        ? 'bsis-badge-success' 
                        : (s.status === 'late' ? 'bsis-badge-warning' : (s.status === 'absent' ? 'bsis-badge-danger' : 'bsis-badge-info'));
                    const statusLabel = s.status ? s.status.toUpperCase() : 'UNKNOWN';
                    const yrBlk = [s.year_level, s.section_block].filter(Boolean).join(' - ') || 'N/A';
                    
                    return `
                        <tr>
                            <td><strong class="text-primary">${s.student_number || 'N/A'}</strong></td>
                            <td><strong>${s.student_name || 'Student'}</strong></td>
                            <td><span class="bsis-badge bsis-badge-info">${yrBlk}</span></td>
                            <td>${s.event_title || 'Event'}</td>
                            <td><span class="bsis-badge ${statusBadge}">${statusLabel}</span></td>
                            <td class="font-monospace">${s.scan_time || 'N/A'}</td>
                            <td class="text-end font-monospace">${s.distance_meters !== null && s.distance_meters !== undefined ? s.distance_meters + 'm' : '—'}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                recentTable.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><i class="bi bi-inbox me-1"></i> No verified scans recorded yet for this event target.</td></tr>';
            }
        }

        // 6. Populate Right-Side Quick Glance Widgets
        this.renderRightPanelWidgets(d.recent_scans);

        // Load System Settings for QR Interval
        const settingsRes = await StorageManager.apiRequest('/api/settings');
        if (settingsRes.ok && settingsRes.data.success) {
            const qrSec = settingsRes.data.data.qr_expiration_seconds;
            this.currentQrDurationSeconds = qrSec;
            const input = document.getElementById('setting-qr-interval-input');
            if (input) input.value = qrSec;
        }
    },

    changeCalendarMonth(delta) {
        this.calendarCurrentMonth += delta;
        if (this.calendarCurrentMonth < 0) {
            this.calendarCurrentMonth = 11;
            this.calendarCurrentYear--;
        } else if (this.calendarCurrentMonth > 11) {
            this.calendarCurrentMonth = 0;
            this.calendarCurrentYear++;
        }
        this.renderOverviewCalendar();
    },

    renderOverviewCalendar() {
        const monthTitle = document.getElementById('calendar-month-title');
        const daysGrid = document.getElementById('calendar-days-grid');
        if (!daysGrid) return;

        const year = this.calendarCurrentYear;
        const month = this.calendarCurrentMonth;
        const monthDate = new Date(year, month, 1);
        const monthName = monthDate.toLocaleString('en-US', { month: 'long' });

        if (monthTitle) monthTitle.innerText = `${monthName} ${year}`;

        const firstDayIndex = monthDate.getDay(); // 0 = Sun
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        const isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;
        const todayDate = today.getDate();

        if (!this.selectedCalendarDate) {
            this.selectedCalendarDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        }

        // Extract event days in this month from cached events
        const eventDays = new Set();
        if (this.cachedEvents && this.cachedEvents.length > 0) {
            this.cachedEvents.forEach(ev => {
                const evDateStr = ev.start_time || ev.event_date;
                if (evDateStr) {
                    const evD = new Date(evDateStr);
                    if (evD.getFullYear() === year && evD.getMonth() === month) {
                        eventDays.add(evD.getDate());
                    }
                }
            });
        }

        let html = '';
        // Blank days before start
        for (let i = 0; i < firstDayIndex; i++) {
            html += `<div class="bsis-calendar-day is-empty"></div>`;
        }

        // Days in month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = isCurrentMonth && day === todayDate;
            const isSelected = this.selectedCalendarDate === dateStr;
            const hasEvent = eventDays.has(day);
            const classes = ['bsis-calendar-day'];
            if (isToday) classes.push('is-today');
            if (hasEvent) classes.push('has-event');
            if (isSelected) classes.push('is-selected');

            html += `<div class="${classes.join(' ')}" title="${hasEvent ? 'Scheduled Event' : ''}" onclick="AdminApp.handleCalendarDayClick(${year}, ${month}, ${day})">${day}</div>`;
        }

        daysGrid.innerHTML = html;
    },

    handleCalendarDayClick(year, month, day) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        this.selectedCalendarDate = dateStr;
        this.renderOverviewCalendar();

        const matchedEvent = this.cachedEvents?.find(e => {
            const d = (e.start_time || e.event_date || '').substring(0, 10);
            return d === dateStr;
        });
        if (matchedEvent) {
            const select = document.getElementById('overview-event-select');
            if (select && select.value !== String(matchedEvent.id)) {
                select.value = matchedEvent.id;
                this.loadOverview(matchedEvent.id);
            }
        }
    },

    renderRightPanelWidgets(recentScans) {
        // 1. Recent Attendees
        const recentContainer = document.getElementById('right-panel-recent-students');
        if (recentContainer) {
            if (recentScans && recentScans.length > 0) {
                const top4 = recentScans.slice(0, 4);
                recentContainer.innerHTML = top4.map(s => {
                    const name = s.student_name || 'Student';
                    const initials = name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() || 'ST';
                    const timeStr = s.scan_time || 'Just now';
                    const yrBlk = [s.year_level, s.section_block].filter(Boolean).join(' ') || 'BSIS';

                    return `
                        <div class="bsis-feed-item">
                            <div class="d-flex align-items-center gap-2 text-truncate">
                                <div class="bsis-feed-avatar">${initials}</div>
                                <div class="text-truncate" style="line-height: 1.25;">
                                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.84rem;">${name}</div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">${s.student_number || 'N/A'} &bull; ${yrBlk}</div>
                                </div>
                            </div>
                            <span class="badge bg-light text-secondary border font-monospace ms-2" style="font-size: 0.68rem;">${timeStr}</span>
                        </div>
                    `;
                }).join('');
            } else {
                recentContainer.innerHTML = `
                    <div class="text-center py-3 text-muted small">
                        <i class="bi bi-person-x d-block fs-4 text-muted opacity-50 mb-1"></i>
                        No active attendees recorded yet.
                    </div>
                `;
            }
        }

        // 2. Upcoming Events
        const upcomingContainer = document.getElementById('right-panel-upcoming-events');
        if (upcomingContainer && this.cachedEvents) {
            const upcoming = this.cachedEvents.filter(e => e.status === 'upcoming' || e.status === 'active').slice(0, 3);
            if (upcoming.length > 0) {
                upcomingContainer.innerHTML = upcoming.map(e => {
                    const d = new Date(e.start_time || e.event_date);
                    const dateChip = !isNaN(d) ? d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : 'Upcoming';
                    const statusBadge = e.status === 'active' ? 'bg-success text-white' : 'bg-primary bg-opacity-10 text-primary';

                    return `
                        <div class="bsis-upcoming-card" style="cursor: pointer;" onclick="AdminApp.showEventDetails(${e.id})">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <h6 class="fw-bold text-dark mb-0 text-truncate" style="font-size: 0.88rem;">${e.title}</h6>
                                <span class="badge ${statusBadge} px-2 py-1" style="font-size: 0.68rem;">${e.status.toUpperCase()}</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mt-2 pt-1 border-top border-light">
                                <span class="bsis-date-chip"><i class="bi bi-calendar3"></i> ${dateChip}</span>
                                <small class="text-muted text-truncate" style="max-width: 140px;"><i class="bi bi-geo-alt me-1"></i>${e.venue_name || 'Campus'}</small>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                upcomingContainer.innerHTML = `
                    <div class="text-center py-3 text-muted small">
                        <i class="bi bi-calendar-check d-block fs-4 text-muted opacity-50 mb-1"></i>
                        No upcoming events scheduled.
                    </div>
                `;
            }
        }
    },

    handleGlobalQuickSearch(e) {
        const val = (e.target?.value || '').trim().toLowerCase();
        if (!val) return;
        if (e.key === 'Enter') {
            window.location.hash = '#users';
            const userSearchInput = document.getElementById('user-search-input');
            if (userSearchInput) {
                userSearchInput.value = val;
                this.handleUserSearchDebounced();
            }
        }
    },

    renderOverviewStatusChart(present, late, absent, override) {
        const canvas = document.getElementById('overview-status-chart');
        if (!canvas || typeof Chart === 'undefined') return;

        if (this.statusChartInstance) {
            this.statusChartInstance.destroy();
        }

        const total = present + late + absent + override;
        const chartData = total === 0 ? [0, 0, 1, 0] : [present, late, absent, override];
        const chartColors = total === 0 ? ['#E2E8F0', '#E2E8F0', '#E2E8F0', '#E2E8F0'] : ['#16A34A', '#D97706', '#DC2626', '#0284C7'];

        this.statusChartInstance = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: ['Present (On-Time)', 'Late', 'Absent', 'Manual Override'],
                datasets: [{
                    data: chartData,
                    backgroundColor: chartColors,
                    borderWidth: 2,
                    borderColor: '#FFFFFF',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const val = ctx.raw || 0;
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return ` ${ctx.label}: ${val} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    },

    renderOverviewSessionChart(labels, counts) {
        const canvas = document.getElementById('overview-session-chart');
        if (!canvas || typeof Chart === 'undefined') return;

        if (this.sessionChartInstance) {
            this.sessionChartInstance.destroy();
        }

        this.sessionChartInstance = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels || ['Time-In', 'Time-Out'],
                datasets: [{
                    label: 'Verified Student Scans',
                    data: counts || [0, 0],
                    backgroundColor: ['#063B5C', '#0284C7', '#0EA5E9', '#38BDF8'],
                    borderRadius: 6,
                    maxBarThickness: 55,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: '#64748B' },
                        grid: { color: '#F1F5F9' }
                    },
                    x: {
                        ticks: { color: '#0F172A', font: { weight: '600' } },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ` Scanned: ${ctx.raw} students`;
                            }
                        }
                    }
                }
            }
        });
    },

    async handleSaveQrSettings(event) {
        event.preventDefault();
        const seconds = parseInt(document.getElementById('setting-qr-interval-input').value);
        if (isNaN(seconds) || seconds < 5 || seconds > 300) {
            alert('Please enter a valid QR expiration interval between 5 and 300 seconds.');
            return;
        }

        const res = await StorageManager.apiRequest('/api/settings', {
            method: 'POST',
            body: JSON.stringify({ qr_expiration_seconds: seconds })
        });

        if (res.ok && res.data.success) {
            this.currentQrDurationSeconds = res.data.data.qr_expiration_seconds;
            bootstrap.Modal.getInstance(document.getElementById('modal-qr-settings'))?.hide();
            this.showToast(`Dynamic QR Code refresh interval updated to ${this.currentQrDurationSeconds} seconds!`);
        } else {
            alert(res.data?.message || 'Failed to update QR interval setting.');
        }
    },

    showEventDetails(eventId) {
        return this.viewEventDetails(eventId);
    },

    // 2. EVENT MANAGEMENT & INTERACTIVE LEAFLET MAP PICKER
    initCreateEventMap() {
        if (typeof L === 'undefined') return;

        const defaultLat = parseFloat(document.getElementById('event-lat').value) || 10.1492;
        const defaultLng = parseFloat(document.getElementById('event-lon').value) || 124.3312;
        const radius = parseInt(document.getElementById('event-radius').value) || 50;

        if (!this.eventMap) {
            this.eventMap = L.map('create-event-map').setView([defaultLat, defaultLng], 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.eventMap);

            this.eventMarker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(this.eventMap);

            this.eventRadiusCircle = L.circle([defaultLat, defaultLng], {
                color: '#35C4E8',
                fillColor: '#35C4E8',
                fillOpacity: 0.2,
                radius: radius
            }).addTo(this.eventMap);

            // Drag marker listener
            this.eventMarker.on('dragend', (e) => {
                const pos = e.target.getLatLng();
                this.updateMapCoordinates(pos.lat, pos.lng);
            });

            // Map click listener
            this.eventMap.on('click', (e) => {
                this.eventMarker.setLatLng(e.latlng);
                this.updateMapCoordinates(e.latlng.lat, e.latlng.lng);
            });
        } else {
            this.eventMap.invalidateSize();
            this.eventMap.setView([defaultLat, defaultLng], 16);
            this.eventMarker.setLatLng([defaultLat, defaultLng]);
            this.eventRadiusCircle.setLatLng([defaultLat, defaultLng]);
            this.eventRadiusCircle.setRadius(radius);
        }
    },

    updateMapCoordinates(lat, lng) {
        document.getElementById('event-lat').value = lat.toFixed(8);
        document.getElementById('event-lon').value = lng.toFixed(8);
        if (this.eventRadiusCircle) {
            this.eventRadiusCircle.setLatLng([lat, lng]);
        }
    },

    updateMapRadius(radius) {
        const rad = parseInt(radius) || 50;
        if (this.eventRadiusCircle) {
            this.eventRadiusCircle.setRadius(rad);
        }
    },

    detectCurrentLocationForEvent() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        this.showToast('Detecting your GPS location...');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                this.updateMapCoordinates(lat, lng);
                if (this.eventMap && this.eventMarker) {
                    this.eventMap.setView([lat, lng], 17);
                    this.eventMarker.setLatLng([lat, lng]);
                }
                this.showToast('GPS location set successfully!');
            },
            (err) => {
                alert('GPS location detection failed: ' + err.message);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    },
    formatEventDisplayDateTime(dtStr) {
        if (!dtStr) return 'N/A';
        const match = String(dtStr).match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
        if (match) {
            const [_, y, m, d, h, min] = match;
            const hour = parseInt(h);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const displayHour = hour % 12 || 12;
            return `${parseInt(m)}/${parseInt(d)}/${y}, ${displayHour}:${min}:00 ${ampm}`;
        }
        return new Date(dtStr).toLocaleString();
    },

    parseEventDateTime(dtStr) {
        if (!dtStr) return { date: '', time: '' };
        const match = String(dtStr).match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/);
        if (match) {
            return { date: match[1], time: match[2] };
        }
        const d = new Date(dtStr);
        const pad = n => String(n).padStart(2, '0');
        return {
            date: `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`,
            time: `${pad(d.getHours())}:${pad(d.getMinutes())}`
        };
    },

    toggleAllEventCheckboxes(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.event-row-checkbox');
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
        this.updateEventsBatchToolbar();
    },

    clearEventSelection() {
        const selectAll = document.getElementById('events-select-all');
        if (selectAll) selectAll.checked = false;
        document.querySelectorAll('.event-row-checkbox').forEach(cb => cb.checked = false);
        this.updateEventsBatchToolbar();
    },

    updateEventsBatchToolbar() {
        const selected = document.querySelectorAll('.event-row-checkbox:checked');
        const toolbar = document.getElementById('events-batch-toolbar');
        const text = document.getElementById('events-selected-count-text');
        const selectAll = document.getElementById('events-select-all');

        if (toolbar) {
            if (selected.length > 0) {
                toolbar.classList.remove('d-none');
                toolbar.classList.add('d-flex');
                if (text) text.innerText = `${selected.length} event(s) selected`;
            } else {
                toolbar.classList.add('d-none');
                toolbar.classList.remove('d-flex');
            }
        }

        const totalCheckboxes = document.querySelectorAll('.event-row-checkbox');
        if (selectAll && totalCheckboxes.length > 0) {
            selectAll.checked = selected.length === totalCheckboxes.length;
        }
    },

    promptDeleteEvent(eventId, eventTitle) {
        document.getElementById('delete-event-mode').value = 'single';
        document.getElementById('delete-event-target-id').value = eventId;
        document.getElementById('delete-event-prompt-title').innerText = `Delete: ${eventTitle}`;
        document.getElementById('delete-event-prompt-desc').innerText = `Are you sure you want to permanently delete event "${eventTitle}"?`;
        document.getElementById('delete-event-admin-password').value = '';

        const modal = new bootstrap.Modal(document.getElementById('modal-confirm-delete-event'));
        modal.show();
    },

    promptBatchDeleteEvents() {
        const selected = Array.from(document.querySelectorAll('.event-row-checkbox:checked')).map(cb => parseInt(cb.value));
        if (selected.length === 0) {
            alert('Please select at least one event to delete.');
            return;
        }

        document.getElementById('delete-event-mode').value = 'batch';
        document.getElementById('delete-event-target-id').value = selected.join(',');
        document.getElementById('delete-event-prompt-title').innerText = `Batch Delete: ${selected.length} Event(s)`;
        document.getElementById('delete-event-prompt-desc').innerText = `Are you sure you want to permanently delete all ${selected.length} selected events?`;
        document.getElementById('delete-event-admin-password').value = '';

        const modal = new bootstrap.Modal(document.getElementById('modal-confirm-delete-event'));
        modal.show();
    },

    async confirmExecuteDeleteEvent(event) {
        event.preventDefault();
        const mode = document.getElementById('delete-event-mode').value;
        const targetId = document.getElementById('delete-event-target-id').value;
        const password = document.getElementById('delete-event-admin-password').value;
        const btn = document.getElementById('btn-execute-delete-event');

        if (!password) {
            alert('Please enter your Administrator Password.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

        let res;
        if (mode === 'single') {
            res = await StorageManager.apiRequest(`/api/events/${targetId}`, {
                method: 'DELETE',
                body: JSON.stringify({ password })
            });
        } else {
            const eventIds = targetId.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
            res = await StorageManager.apiRequest('/api/events/batch-delete', {
                method: 'POST',
                body: JSON.stringify({
                    event_ids: eventIds,
                    password
                })
            });
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash-fill me-1"></i> Confirm Deletion';

        if (res.ok && res.data.success) {
            const modalEl = document.getElementById('modal-confirm-delete-event');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            this.showToast(res.data?.message || 'Event(s) deleted successfully!');
            this.clearEventSelection();
            this.loadEvents();
        } else {
            alert(res.data?.message || 'Failed to delete event(s). Please verify your administrator password.');
        }
    },

    async loadEvents() {
        const user = StorageManager.getUser();
        const isAdmin = user && user.role === 'admin';

        const search = document.getElementById('event-search-input')?.value || '';
        const status = document.getElementById('event-status-filter')?.value || '';
        const sortByRaw = document.getElementById('event-sort-by')?.value || 'start_time:desc';
        let sortBy = 'start_time';
        let sortOrder = 'desc';
        if (sortByRaw.includes(':')) {
            [sortBy, sortOrder] = sortByRaw.split(':');
        } else {
            sortBy = sortByRaw;
        }

        const queryParams = new URLSearchParams({
            per_page: 50,
            search,
            status,
            sort_by: sortBy,
            sort_order: sortOrder
        });

        const table = document.getElementById('events-table-body');
        if (table) table.innerHTML = this.renderTableSkeleton(7, 4);

        const res = await StorageManager.apiRequest(`/api/events?${queryParams.toString()}`);
        if (!table) return;
        if (res.ok && res.data && res.data.data && res.data.data.data && res.data.data.data.length > 0) {
            table.innerHTML = res.data.data.data.map(e => {
                const titleEscaped = (e.title || '').replace(/'/g, "\\'");
                return `
                <tr>
                    <td class="sticky-col-1 text-center">
                        <input type="checkbox" class="event-row-checkbox form-check-input" value="${e.id}" onchange="AdminApp.updateEventsBatchToolbar()">
                    </td>
                    <td class="sticky-col-event">
                        <div>
                            <a href="javascript:void(0)" onclick="AdminApp.viewEventDetails(${e.id})" class="fw-bold text-primary text-decoration-none event-title-link d-inline-flex align-items-center gap-1" title="Click to view full event details">
                                <span class="event-title-text">${e.title}</span> <i class="bi bi-info-circle-fill text-info" style="font-size: 0.78rem;"></i>
                            </a>
                        </div>
                        <div class="mt-1">
                            <span class="badge ${(!e.target_audience_label || e.target_audience_label === 'All BSIS Students') ? 'bg-secondary' : 'bg-primary'}" style="font-size: 0.72rem; font-weight: 500;">
                                <i class="bi bi-people-fill"></i> ${e.target_audience_label || 'All BSIS Students'}
                            </span>
                        </div>
                    </td>
                    <td class="text-nowrap">${e.venue_name} (${e.allowed_radius_meters}m)</td>
                    <td class="text-nowrap">${this.formatEventDisplayDateTime(e.start_time)}</td>
                    <td class="text-nowrap">₱${parseFloat(e.fine_amount).toFixed(2)}</td>
                    <td class="text-center text-nowrap">
                        <span class="bsis-badge ${e.status === 'active' ? 'bsis-badge-event-active' : (e.status === 'upcoming' ? 'bsis-badge-event-upcoming' : 'bsis-badge-event-completed')}">
                            ${e.status === 'active' ? '<i class="bi bi-broadcast me-1"></i> ACTIVE' : (e.status === 'upcoming' ? '<i class="bi bi-hourglass-split me-1"></i> UPCOMING' : '<i class="bi bi-check-circle me-1"></i> COMPLETED')}
                        </span>
                    </td>
                    <td class="text-center text-nowrap">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-bsis-primary dropdown-toggle py-1 px-3 fw-semibold shadow-sm d-inline-flex align-items-center justify-content-center gap-1" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="border-radius: 8px; font-size: 0.82rem; min-width: 98px;">
                                <i class="bi bi-gear-fill"></i> <span>Actions</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg bsis-action-dropdown">
                                <li>
                                    <a class="dropdown-item py-2" href="javascript:void(0)" onclick="AdminApp.viewEventDetails(${e.id})">
                                        <i class="bi bi-info-circle-fill text-primary me-2"></i> View Event Details
                                    </a>
                                </li>
                                ${(e.status === 'upcoming' || e.status === 'active' || e.status === 'draft') ? `
                                <li>
                                    <a class="dropdown-item py-2" href="javascript:void(0)" onclick="AdminApp.editEvent(${e.id})">
                                        <i class="bi bi-pencil-square text-primary me-2"></i> Edit Event Details
                                    </a>
                                </li>` : ''}
                                
                                ${e.status === 'active' ? `
                                <li>
                                    <a class="dropdown-item py-2 text-info fw-bold" href="#qr-display?event=${e.id}">
                                        <i class="bi bi-qr-code text-info me-2"></i> Dynamic QR Display
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 text-warning fw-semibold" href="javascript:void(0)" onclick="AdminApp.openEmergencyBypassModal(${e.id}, '${titleEscaped}')">
                                        <i class="bi bi-lightning-charge-fill text-warning me-2"></i> Emergency Bypass
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 text-secondary fw-semibold" href="javascript:void(0)" onclick="AdminApp.completeEvent(${e.id}, '${titleEscaped}')">
                                        <i class="bi bi-check2-circle text-secondary me-2"></i> Conclude & Process
                                    </a>
                                </li>
                                ` : (e.status === 'upcoming' || e.status === 'draft') ? `
                                <li>
                                    <a class="dropdown-item py-2 text-success fw-bold" href="javascript:void(0)" onclick="AdminApp.activateEvent(${e.id})">
                                        <i class="bi bi-play-circle text-success me-2"></i> Activate Event Now
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 text-info" href="#qr-display?event=${e.id}">
                                        <i class="bi bi-qr-code text-info me-2"></i> Preview QR Display
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 text-secondary" href="javascript:void(0)" onclick="AdminApp.jumpToEventReports(${e.id})">
                                        <i class="bi bi-file-earmark-bar-graph me-2"></i> Event Audience & Roster
                                    </a>
                                </li>
                                ` : `
                                <li>
                                    <a class="dropdown-item py-2 text-primary fw-semibold" href="javascript:void(0)" onclick="AdminApp.jumpToEventReports(${e.id})">
                                        <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i> Official Event Report
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 text-warning fw-semibold" href="javascript:void(0)" onclick="AdminApp.processEventAbsences(${e.id})">
                                        <i class="bi bi-calculator text-warning me-2"></i> Re-Process Absences
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 text-info" href="javascript:void(0)" onclick="AdminApp.jumpToEventReports(${e.id})">
                                        <i class="bi bi-download text-info me-2"></i> Export Attendance File
                                    </a>
                                </li>
                                `}
                                
                                ${isAdmin ? `
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="AdminApp.promptDeleteEvent(${e.id}, '${titleEscaped}')">
                                        <i class="bi bi-trash text-danger me-2"></i> Drop Event
                                    </a>
                                </li>` : ''}
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
            }).join('');
            this.updateEventsBatchToolbar();
        } else {
            table.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="bsis-empty-state">
                            <div class="bsis-empty-icon">
                                <i class="bi bi-calendar2-x"></i>
                            </div>
                            <div class="bsis-empty-title">No Events Found</div>
                            <p class="bsis-empty-subtitle">No college events match your selected search query or status filter.</p>
                        </div>
                    </td>
                </tr>
            `;
            this.updateEventsBatchToolbar();
        }
    },

    selectTargetYear(year, mode) {
        const isCreate = mode === 'create';
        const allBtn = document.getElementById(isCreate ? 'pill-all-create' : 'pill-all-edit');
        const summaryBadge = document.getElementById(isCreate ? 'event-target-summary-create' : 'edit-event-target-summary');
        const yrPills = [
            { el: document.getElementById(isCreate ? 'pill-yr1-create' : 'pill-yr1-edit'), name: '1st Year' },
            { el: document.getElementById(isCreate ? 'pill-yr2-create' : 'pill-yr2-edit'), name: '2nd Year' },
            { el: document.getElementById(isCreate ? 'pill-yr3-create' : 'pill-yr3-edit'), name: '3rd Year' },
            { el: document.getElementById(isCreate ? 'pill-yr4-create' : 'pill-yr4-edit'), name: '4th Year' }
        ];

        if (year === 'All') {
            // Select all years
            if (allBtn) allBtn.classList.add('active');
            yrPills.forEach(p => {
                if (p.el) {
                    p.el.classList.add('active');
                    p.el.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${p.name}`;
                }
            });
        } else {
            // Toggle specific year level
            const target = yrPills.find(p => p.name === year);
            if (target && target.el) {
                const wasActive = target.el.classList.contains('active');
                
                // Count current active
                const activeCount = yrPills.filter(p => p.el?.classList.contains('active')).length;
                if (wasActive && activeCount <= 1) {
                    this.showToast('At least one year level must be selected for the event.', 'warning');
                    return;
                }

                if (wasActive) {
                    target.el.classList.remove('active');
                    target.el.innerHTML = `<i class="bi bi-circle me-1 text-muted"></i> ${target.name}`;
                } else {
                    target.el.classList.add('active');
                    target.el.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${target.name}`;
                }

                const newActive = yrPills.filter(p => p.el?.classList.contains('active'));
                if (newActive.length === 4) {
                    if (allBtn) allBtn.classList.add('active');
                } else {
                    if (allBtn) allBtn.classList.remove('active');
                }
            }
        }

        // Update live summary badge
        const activeYears = yrPills.filter(p => p.el?.classList.contains('active')).map(p => p.name);
        if (summaryBadge) {
            if (activeYears.length === 4) {
                summaryBadge.innerHTML = '🎓 All BSIS Students (1st – 4th Year)';
                summaryBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-bold';
            } else {
                summaryBadge.innerHTML = `🎯 ${activeYears.join(', ')} (${activeYears.length} Level${activeYears.length > 1 ? 's' : ''})`;
                summaryBadge.className = 'badge bg-warning-subtle text-dark border border-warning-subtle px-2.5 py-1 rounded-pill fw-bold';
            }
        }
    },

    getSelectedTargetYears(mode) {
        const isCreate = mode === 'create';
        const allBtn = document.getElementById(isCreate ? 'pill-all-create' : 'pill-all-edit');
        const yrPills = [
            { el: document.getElementById(isCreate ? 'pill-yr1-create' : 'pill-yr1-edit'), name: '1st Year' },
            { el: document.getElementById(isCreate ? 'pill-yr2-create' : 'pill-yr2-edit'), name: '2nd Year' },
            { el: document.getElementById(isCreate ? 'pill-yr3-create' : 'pill-yr3-edit'), name: '3rd Year' },
            { el: document.getElementById(isCreate ? 'pill-yr4-create' : 'pill-yr4-edit'), name: '4th Year' }
        ];
        const activeYears = yrPills.filter(p => p.el?.classList.contains('active')).map(p => p.name);
        if (activeYears.length === 4 || allBtn?.classList.contains('active')) {
            return ['All'];
        }
        return activeYears.length > 0 ? activeYears : ['All'];
    },

    setTargetYears(targets, mode) {
        const isCreate = mode === 'create';
        const isAll = !targets || targets.length === 0 || targets.includes('All') || targets.length >= 4;
        const allBtn = document.getElementById(isCreate ? 'pill-all-create' : 'pill-all-edit');
        const summaryBadge = document.getElementById(isCreate ? 'event-target-summary-create' : 'edit-event-target-summary');
        const yrPills = [
            { el: document.getElementById(isCreate ? 'pill-yr1-create' : 'pill-yr1-edit'), name: '1st Year' },
            { el: document.getElementById(isCreate ? 'pill-yr2-create' : 'pill-yr2-edit'), name: '2nd Year' },
            { el: document.getElementById(isCreate ? 'pill-yr3-create' : 'pill-yr3-edit'), name: '3rd Year' },
            { el: document.getElementById(isCreate ? 'pill-yr4-create' : 'pill-yr4-edit'), name: '4th Year' }
        ];

        if (isAll) {
            if (allBtn) allBtn.classList.add('active');
            yrPills.forEach(p => {
                if (p.el) {
                    p.el.classList.add('active');
                    p.el.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${p.name}`;
                }
            });
            if (summaryBadge) {
                summaryBadge.innerHTML = '🎓 All BSIS Students (1st – 4th Year)';
                summaryBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill fw-bold';
            }
        } else {
            if (allBtn) allBtn.classList.remove('active');
            const activeYears = [];
            yrPills.forEach(p => {
                if (p.el) {
                    const isSelected = targets.includes(p.name);
                    if (isSelected) {
                        p.el.classList.add('active');
                        p.el.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${p.name}`;
                        activeYears.push(p.name);
                    } else {
                        p.el.classList.remove('active');
                        p.el.innerHTML = `<i class="bi bi-circle me-1 text-muted"></i> ${p.name}`;
                    }
                }
            });
            if (summaryBadge) {
                summaryBadge.innerHTML = `🎯 ${activeYears.join(', ')} (${activeYears.length} Level${activeYears.length > 1 ? 's' : ''})`;
                summaryBadge.className = 'badge bg-warning-subtle text-dark border border-warning-subtle px-2.5 py-1 rounded-pill fw-bold';
            }
        }
    },

    // --------------------------------------------------------------------------
    // --------------------------------------------------------------------------
    // Clean 12-Hour Dropdown Time Picker Suite (No Native Browser Popups)
    // --------------------------------------------------------------------------
    formatTime12h(timeStr) {
        if (!timeStr) return '';
        const parts = timeStr.split(':');
        if (parts.length < 2) return timeStr;
        let hours = parseInt(parts[0], 10);
        const minutes = parts[1].padStart(2, '0');
        if (isNaN(hours)) return timeStr;
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${hours}:${minutes} ${ampm}`;
    },

    syncTimePicker(el) {
        const wrap = el.closest('.bsis-time-picker-control');
        if (!wrap) return;
        const targetId = wrap.getAttribute('data-target');
        const hiddenInput = document.getElementById(targetId);
        if (!hiddenInput) return;

        wrap.style.opacity = '1';

        const hourSelect = wrap.querySelector('.time-select-hour');
        const minSelect = wrap.querySelector('.time-select-min');
        const isPm = wrap.querySelector('.btn-ampm-pm')?.classList.contains('active');

        let h = parseInt(hourSelect?.value || '8', 10);
        const m = minSelect?.value || '00';

        if (isPm && h < 12) h += 12;
        if (!isPm && h === 12) h = 0;

        const hh = String(h).padStart(2, '0');
        hiddenInput.value = `${hh}:${m}`;
        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));

        // Update live duration badge for main event schedule
        if (targetId === 'event-start-time' || targetId === 'event-end-time') {
            this.updateTimeLiveBadge('event-start-time', 'event-end-time', 'event-time-live-badge');
        } else if (targetId === 'edit-event-start-time' || targetId === 'edit-event-end-time') {
            this.updateTimeLiveBadge('edit-event-start-time', 'edit-event-end-time', 'edit-event-time-live-badge');
        }
    },

    setAmPm(btn, ampm) {
        const wrap = btn.closest('.bsis-time-picker-control');
        if (!wrap) return;
        const amBtn = wrap.querySelector('.btn-ampm-am');
        const pmBtn = wrap.querySelector('.btn-ampm-pm');

        if (ampm === 'AM') {
            amBtn?.classList.add('active');
            pmBtn?.classList.remove('active');
        } else {
            pmBtn?.classList.add('active');
            amBtn?.classList.remove('active');
        }

        this.syncTimePicker(btn);
    },

    setTimePickerValue(targetId, timeStr) {
        const hiddenInput = document.getElementById(targetId);
        if (!hiddenInput) return;
        hiddenInput.value = timeStr || '';

        const wrap = document.querySelector(`.bsis-time-picker-control[data-target="${targetId}"]`);
        if (!wrap) return;

        if (!timeStr) {
            wrap.style.opacity = '0.5';
            return;
        }

        wrap.style.opacity = '1';
        const parts = timeStr.split(':');
        let hours = parseInt(parts[0], 10);
        let mins = parts[1] ? parts[1].padStart(2, '0') : '00';
        if (isNaN(hours)) return;

        const isPm = hours >= 12;
        const displayHour = String(hours % 12 || 12);

        const hourSelect = wrap.querySelector('.time-select-hour');
        const minSelect = wrap.querySelector('.time-select-min');
        if (hourSelect) hourSelect.value = displayHour;
        
        if (minSelect) {
            let opt = Array.from(minSelect.options).find(o => o.value === mins);
            if (!opt) {
                const newOpt = document.createElement('option');
                newOpt.value = mins;
                newOpt.textContent = mins;
                minSelect.appendChild(newOpt);
            }
            minSelect.value = mins;
        }

        const amBtn = wrap.querySelector('.btn-ampm-am');
        const pmBtn = wrap.querySelector('.btn-ampm-pm');
        if (isPm) {
            pmBtn?.classList.add('active');
            amBtn?.classList.remove('active');
        } else {
            amBtn?.classList.add('active');
            pmBtn?.classList.remove('active');
        }

        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
    },

    setTimeField(inputId, timeStr) {
        this.setTimePickerValue(inputId, timeStr);
    },

    clearTimeField(inputId) {
        const hiddenInput = document.getElementById(inputId);
        if (hiddenInput) hiddenInput.value = '';
        const wrap = document.querySelector(`.bsis-time-picker-control[data-target="${inputId}"]`);
        if (wrap) wrap.style.opacity = '0.45';
        hiddenInput?.dispatchEvent(new Event('change', { bubbles: true }));
    },

    updateTimeLiveBadge(startInputId, endInputId, badgeId) {
        const start = document.getElementById(startInputId)?.value;
        const end = document.getElementById(endInputId)?.value;
        const badge = document.getElementById(badgeId);
        if (!badge) return;

        if (!start || !end) {
            badge.innerHTML = '<i class="bi bi-clock"></i> Specify start & end times';
            return;
        }

        const s12 = this.formatTime12h(start);
        const e12 = this.formatTime12h(end);

        const parseMins = (t) => {
            const p = t.split(':');
            return (parseInt(p[0], 10) * 60) + (parseInt(p[1], 10) || 0);
        };
        let diff = parseMins(end) - parseMins(start);
        if (diff < 0) diff += 24 * 60;
        const hrs = Math.floor(diff / 60);
        const mins = diff % 60;
        const durStr = hrs > 0 ? (mins > 0 ? `${hrs}h ${mins}m` : `${hrs} hrs`) : `${mins} mins`;

        badge.innerHTML = `<i class="bi bi-clock-fill text-primary"></i> <span><strong>${s12}</strong> &mdash; <strong>${e12}</strong> <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Duration: ${durStr}</span></span>`;
    },

    autoFillSessionWindows(mode = 'create') {
        const prefix = mode === 'create' ? 'event-' : 'edit-event-';
        const startInput = document.getElementById(prefix + 'start-time');
        const endInput = document.getElementById(prefix + 'end-time');
        const startTime = startInput?.value || '08:00';
        const endTime = endInput?.value || '17:00';

        const parseMins = (t) => {
            const p = (t || '08:00').split(':');
            return (parseInt(p[0], 10) * 60) + (parseInt(p[1], 10) || 0);
        };
        const formatMins = (mins) => {
            let m = mins % (24 * 60);
            if (m < 0) m += 24 * 60;
            const hh = String(Math.floor(m / 60)).padStart(2, '0');
            const mm = String(m % 60).padStart(2, '0');
            return `${hh}:${mm}`;
        };

        const startMins = parseMins(startTime);
        const endMins = parseMins(endTime);

        const isWhole = mode === 'create'
            ? document.getElementById('session-type-whole-create')?.checked
            : document.getElementById('edit-session-type-whole')?.checked;

        if (isWhole) {
            // Whole Day 4-scan slots
            this.setTimePickerValue(prefix + 'am-checkin-start', formatMins(startMins - 30));
            this.setTimePickerValue(prefix + 'am-checkin-end', formatMins(startMins + 30));
            
            const middayMins = 12 * 60;
            this.setTimePickerValue(prefix + 'am-checkout-start', formatMins(middayMins - 30));
            this.setTimePickerValue(prefix + 'am-checkout-end', formatMins(middayMins + 30));
            
            const pmStartMins = 13 * 60;
            this.setTimePickerValue(prefix + 'pm-checkin-start', formatMins(pmStartMins));
            this.setTimePickerValue(prefix + 'pm-checkin-end', formatMins(pmStartMins + 45));
            
            this.setTimePickerValue(prefix + 'pm-checkout-start', formatMins(endMins - 30));
            this.setTimePickerValue(prefix + 'pm-checkout-end', formatMins(endMins + 30));
        } else {
            // Half Day 2-scan slots
            this.setTimePickerValue(prefix + 'checkin-start', formatMins(startMins - 30));
            this.setTimePickerValue(prefix + 'checkin-end', formatMins(startMins + 30));
            this.setTimePickerValue(prefix + 'checkout-start', formatMins(endMins - 30));
            this.setTimePickerValue(prefix + 'checkout-end', formatMins(endMins + 30));
        }

        this.showToast(`✨ Smart Attendance Scanning Windows populated for ${this.formatTime12h(startTime)} – ${this.formatTime12h(endTime)}!`, 'info');
    },

    toggleSessionTypeUI(mode = 'create') {
        const isWhole = mode === 'create'
            ? document.getElementById('session-type-whole-create')?.checked
            : document.getElementById('edit-session-type-whole')?.checked;

        const halfWrap = document.getElementById(mode === 'create' ? 'create-halfday-windows-wrap' : 'edit-halfday-windows-wrap');
        const wholeWrap = document.getElementById(mode === 'create' ? 'create-wholeday-windows-wrap' : 'edit-wholeday-windows-wrap');
        const fineLabel = document.getElementById(mode === 'create' ? 'event-fine-label' : 'edit-event-fine-label');
        const fineHint = document.getElementById(mode === 'create' ? 'event-fine-hint' : 'edit-event-fine-hint');

        if (halfWrap) halfWrap.style.display = isWhole ? 'none' : 'block';
        if (wholeWrap) wholeWrap.style.display = isWhole ? 'block' : 'none';
        if (fineLabel) {
            fineLabel.innerHTML = isWhole 
                ? '<i class="bi bi-cash"></i> Fine Amount Per Missed/Late Slot (PHP)'
                : '<i class="bi bi-cash"></i> Late / Non-Attendance Fine Amount (PHP)';
        }
        if (fineHint) {
            fineHint.innerText = isWhole
                ? 'Assessed separately for each missed or late scanning session slot (e.g. ₱20/slot)'
                : 'Assessed when student is late or absent for the event';
        }
    },

    async handleCreateEvent(event) {
        event.preventDefault();
        const submitBtn = event.target?.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';

        const eventTitle = document.getElementById('event-title')?.value?.trim();
        const eventVenue = document.getElementById('event-venue')?.value?.trim();
        const eventDate = document.getElementById('event-date')?.value;
        const startTime = document.getElementById('event-start-time')?.value;
        const endTime = document.getElementById('event-end-time')?.value;

        // Run inline form validations
        const v1 = this.validateField('event-title', !!eventTitle && eventTitle.length >= 3, 'Event title must be at least 3 characters.');
        const v2 = this.validateField('event-venue', !!eventVenue, 'Please enter a venue or building name.');
        const v3 = this.validateField('event-date', !!eventDate, 'Please select an event date.');
        const v4 = this.validateField('event-start-time', !!startTime, 'Please specify the start time.');
        const v5 = this.validateField('event-end-time', !!endTime, 'Please specify the end time.');

        if (!v1 || !v2 || !v3 || !v4 || !v5) {
            this.showToast('Please check the required event details highlighted in red.', 'warning');
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving Event...';
        }

        try {
            const sessionType = document.querySelector('input[name="event_session_type_create"]:checked')?.value || 'half_day';
            const fineVal = parseFloat(document.getElementById('event-fine').value) || 0;

            const targetYears = this.getSelectedTargetYears('create');

            const data = {
                title: eventTitle,
                description: document.getElementById('event-description').value,
                session_type: sessionType,
                venue_name: eventVenue,
                venue_latitude: parseFloat(document.getElementById('event-lat').value),
                venue_longitude: parseFloat(document.getElementById('event-lon').value),
                allowed_radius_meters: parseInt(document.getElementById('event-radius').value),
                start_time: `${eventDate} ${startTime}:00`,
                end_time: `${eventDate} ${endTime}:00`,
                target_year_levels: targetYears,
                fine_amount: fineVal,
                fine_per_slot: fineVal,
            };

            if (sessionType === 'whole_day') {
                const amCinStart = document.getElementById('event-am-checkin-start')?.value;
                const amCinEnd = document.getElementById('event-am-checkin-end')?.value;
                const amCoutStart = document.getElementById('event-am-checkout-start')?.value;
                const amCoutEnd = document.getElementById('event-am-checkout-end')?.value;
                const pmCinStart = document.getElementById('event-pm-checkin-start')?.value;
                const pmCinEnd = document.getElementById('event-pm-checkin-end')?.value;
                const pmCoutStart = document.getElementById('event-pm-checkout-start')?.value;
                const pmCoutEnd = document.getElementById('event-pm-checkout-end')?.value;

                data.am_checkin_start_time = (amCinStart && amCinEnd) ? `${eventDate} ${amCinStart}:00` : null;
                data.am_checkin_end_time = (amCinStart && amCinEnd) ? `${eventDate} ${amCinEnd}:00` : null;
                data.am_checkout_start_time = (amCoutStart && amCoutEnd) ? `${eventDate} ${amCoutStart}:00` : null;
                data.am_checkout_end_time = (amCoutStart && amCoutEnd) ? `${eventDate} ${amCoutEnd}:00` : null;
                data.pm_checkin_start_time = (pmCinStart && pmCinEnd) ? `${eventDate} ${pmCinStart}:00` : null;
                data.pm_checkin_end_time = (pmCinStart && pmCinEnd) ? `${eventDate} ${pmCinEnd}:00` : null;
                data.pm_checkout_start_time = (pmCoutStart && pmCoutEnd) ? `${eventDate} ${pmCoutStart}:00` : null;
                data.pm_checkout_end_time = (pmCoutStart && pmCoutEnd) ? `${eventDate} ${pmCoutEnd}:00` : null;
            } else {
                const checkinStart = document.getElementById('event-checkin-start')?.value || null;
                const checkinEnd = document.getElementById('event-checkin-end')?.value || null;
                const checkoutStart = document.getElementById('event-checkout-start')?.value || null;
                const checkoutEnd = document.getElementById('event-checkout-end')?.value || null;

                data.checkin_start_time = (checkinStart && checkinEnd) ? `${eventDate} ${checkinStart}:00` : null;
                data.checkin_end_time = (checkinStart && checkinEnd) ? `${eventDate} ${checkinEnd}:00` : null;
                data.checkout_start_time = (checkoutStart && checkoutEnd) ? `${eventDate} ${checkoutStart}:00` : null;
                data.checkout_end_time = (checkoutStart && checkoutEnd) ? `${eventDate} ${checkoutEnd}:00` : null;
            }

            const res = await StorageManager.apiRequest('/api/events', {
                method: 'POST',
                body: JSON.stringify(data)
            });

            if (res.ok && res.data.success) {
                bootstrap.Modal.getInstance(document.getElementById('modal-create-event')).hide();
                this.showToast('Event created successfully!', 'success');
                this.loadEvents();
            } else {
                let errMsg = res.data?.message || 'Failed to create event.';
                const errors = res.data?.errors || {};
                if (errors.title) {
                    const titleMsg = Array.isArray(errors.title) ? errors.title[0] : errors.title;
                    this.validateField('event-title', false, titleMsg);
                    errMsg = titleMsg;
                    document.getElementById('event-title')?.focus();
                }
                this.showToast(errMsg, 'danger', 7000);
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    },

    async editEvent(eventId) {
        const res = await StorageManager.apiRequest(`/api/events/${eventId}`);
        if (!res.ok) {
            alert('Failed to load event details.');
            return;
        }
        const e = res.data.data.event;

        // Populate edit modal fields
        document.getElementById('edit-event-id').value = e.id;
        document.getElementById('edit-event-title').value = e.title || '';
        document.getElementById('edit-event-description').value = e.description || '';
        document.getElementById('edit-event-venue').value = e.venue_name || '';
        document.getElementById('edit-event-radius').value = e.allowed_radius_meters || 50;
        document.getElementById('edit-event-lat').value = e.venue_latitude || '';
        document.getElementById('edit-event-lon').value = e.venue_longitude || '';
        document.getElementById('edit-event-fine').value = parseFloat(e.fine_per_slot || e.fine_amount || 0).toFixed(2);

        // Session Type
        const isWhole = e.session_type === 'whole_day';
        if (document.getElementById('edit-session-type-whole')) document.getElementById('edit-session-type-whole').checked = isWhole;
        if (document.getElementById('edit-session-type-half')) document.getElementById('edit-session-type-half').checked = !isWhole;
        this.toggleSessionTypeUI('edit');

        // Format separated date & time values without timezone shifting
        if (e.start_time) {
            const st = this.parseEventDateTime(e.start_time);
            document.getElementById('edit-event-date').value = st.date;
            this.setTimePickerValue('edit-event-start-time', st.time);
        }
        if (e.end_time) {
            const et = this.parseEventDateTime(e.end_time);
            this.setTimePickerValue('edit-event-end-time', et.time);
        }
        this.updateTimeLiveBadge('edit-event-start-time', 'edit-event-end-time', 'edit-event-time-live-badge');

        // Half-Day Windows
        const cinStart = this.parseEventDateTime(e.checkin_start_time);
        const cinEnd = this.parseEventDateTime(e.checkin_end_time);
        const coutStart = this.parseEventDateTime(e.checkout_start_time);
        const coutEnd = this.parseEventDateTime(e.checkout_end_time);

        this.setTimePickerValue('edit-event-checkin-start', cinStart.time);
        this.setTimePickerValue('edit-event-checkin-end', cinEnd.time);
        this.setTimePickerValue('edit-event-checkout-start', coutStart.time);
        this.setTimePickerValue('edit-event-checkout-end', coutEnd.time);

        // Whole-Day Windows
        const amCinStart = this.parseEventDateTime(e.am_checkin_start_time);
        const amCinEnd = this.parseEventDateTime(e.am_checkin_end_time);
        const amCoutStart = this.parseEventDateTime(e.am_checkout_start_time);
        const amCoutEnd = this.parseEventDateTime(e.am_checkout_end_time);
        const pmCinStart = this.parseEventDateTime(e.pm_checkin_start_time);
        const pmCinEnd = this.parseEventDateTime(e.pm_checkin_end_time);
        const pmCoutStart = this.parseEventDateTime(e.pm_checkout_start_time);
        const pmCoutEnd = this.parseEventDateTime(e.pm_checkout_end_time);

        this.setTimePickerValue('edit-event-am-checkin-start', amCinStart.time);
        this.setTimePickerValue('edit-event-am-checkin-end', amCinEnd.time);
        this.setTimePickerValue('edit-event-am-checkout-start', amCoutStart.time);
        this.setTimePickerValue('edit-event-am-checkout-end', amCoutEnd.time);
        this.setTimePickerValue('edit-event-pm-checkin-start', pmCinStart.time);
        this.setTimePickerValue('edit-event-pm-checkin-end', pmCinEnd.time);
        this.setTimePickerValue('edit-event-pm-checkout-start', pmCoutStart.time);
        this.setTimePickerValue('edit-event-pm-checkout-end', pmCoutEnd.time);

        // Target Participants / Year Levels
        this.setTargetYears(e.target_year_levels, 'edit');

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('modal-edit-event'));
        modal.show();
    },

    initEditEventMap() {
        if (typeof L === 'undefined') return;

        const lat = parseFloat(document.getElementById('edit-event-lat').value) || 10.1492;
        const lng = parseFloat(document.getElementById('edit-event-lon').value) || 124.3312;
        const radius = parseInt(document.getElementById('edit-event-radius').value) || 50;

        if (!this.editEventMap) {
            this.editEventMap = L.map('edit-event-map').setView([lat, lng], 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.editEventMap);

            this.editEventMarker = L.marker([lat, lng], { draggable: true }).addTo(this.editEventMap);

            this.editEventRadiusCircle = L.circle([lat, lng], {
                color: '#35C4E8',
                fillColor: '#35C4E8',
                fillOpacity: 0.2,
                radius: radius
            }).addTo(this.editEventMap);

            this.editEventMarker.on('dragend', (e) => {
                const pos = e.target.getLatLng();
                document.getElementById('edit-event-lat').value = pos.lat.toFixed(8);
                document.getElementById('edit-event-lon').value = pos.lng.toFixed(8);
                this.editEventRadiusCircle.setLatLng(pos);
            });

            this.editEventMap.on('click', (e) => {
                this.editEventMarker.setLatLng(e.latlng);
                document.getElementById('edit-event-lat').value = e.latlng.lat.toFixed(8);
                document.getElementById('edit-event-lon').value = e.latlng.lng.toFixed(8);
                this.editEventRadiusCircle.setLatLng(e.latlng);
            });
        } else {
            this.editEventMap.invalidateSize();
            this.editEventMap.setView([lat, lng], 16);
            this.editEventMarker.setLatLng([lat, lng]);
            this.editEventRadiusCircle.setLatLng([lat, lng]);
            this.editEventRadiusCircle.setRadius(radius);
        }
    },

    updateEditMapRadius(radius) {
        const rad = parseInt(radius) || 50;
        if (this.editEventRadiusCircle) {
            this.editEventRadiusCircle.setRadius(rad);
        }
    },

    async handleUpdateEvent(event) {
        event.preventDefault();
        const eventId = document.getElementById('edit-event-id').value;
        const eventDate = document.getElementById('edit-event-date').value;
        const startTime = document.getElementById('edit-event-start-time').value;
        const endTime = document.getElementById('edit-event-end-time').value;

        if (!eventDate || !startTime || !endTime) {
            alert('Please specify Event Date, Start Time, and End Time.');
            return;
        }

        const sessionType = document.querySelector('input[name="edit_event_session_type"]:checked')?.value || 'half_day';
        const fineVal = parseFloat(document.getElementById('edit-event-fine').value) || 0;

        const targetYears = this.getSelectedTargetYears('edit');

        const data = {
            title: document.getElementById('edit-event-title').value,
            description: document.getElementById('edit-event-description').value,
            session_type: sessionType,
            venue_name: document.getElementById('edit-event-venue').value,
            venue_latitude: parseFloat(document.getElementById('edit-event-lat').value),
            venue_longitude: parseFloat(document.getElementById('edit-event-lon').value),
            allowed_radius_meters: parseInt(document.getElementById('edit-event-radius').value),
            start_time: `${eventDate} ${startTime}:00`,
            end_time: `${eventDate} ${endTime}:00`,
            target_year_levels: targetYears,
            fine_amount: fineVal,
            fine_per_slot: fineVal,
        };

        if (sessionType === 'whole_day') {
            const amCinStart = document.getElementById('edit-event-am-checkin-start')?.value;
            const amCinEnd = document.getElementById('edit-event-am-checkin-end')?.value;
            const amCoutStart = document.getElementById('edit-event-am-checkout-start')?.value;
            const amCoutEnd = document.getElementById('edit-event-am-checkout-end')?.value;
            const pmCinStart = document.getElementById('edit-event-pm-checkin-start')?.value;
            const pmCinEnd = document.getElementById('edit-event-pm-checkin-end')?.value;
            const pmCoutStart = document.getElementById('edit-event-pm-checkout-start')?.value;
            const pmCoutEnd = document.getElementById('edit-event-pm-checkout-end')?.value;

            data.am_checkin_start_time = (amCinStart && amCinEnd) ? `${eventDate} ${amCinStart}:00` : null;
            data.am_checkin_end_time = (amCinStart && amCinEnd) ? `${eventDate} ${amCinEnd}:00` : null;
            data.am_checkout_start_time = (amCoutStart && amCoutEnd) ? `${eventDate} ${amCoutStart}:00` : null;
            data.am_checkout_end_time = (amCoutStart && amCoutEnd) ? `${eventDate} ${amCoutEnd}:00` : null;
            data.pm_checkin_start_time = (pmCinStart && pmCinEnd) ? `${eventDate} ${pmCinStart}:00` : null;
            data.pm_checkin_end_time = (pmCinStart && pmCinEnd) ? `${eventDate} ${pmCinEnd}:00` : null;
            data.pm_checkout_start_time = (pmCoutStart && pmCoutEnd) ? `${eventDate} ${pmCoutStart}:00` : null;
            data.pm_checkout_end_time = (pmCoutStart && pmCoutEnd) ? `${eventDate} ${pmCoutEnd}:00` : null;
        } else {
            const checkinStart = document.getElementById('edit-event-checkin-start')?.value || null;
            const checkinEnd = document.getElementById('edit-event-checkin-end')?.value || null;
            const checkoutStart = document.getElementById('edit-event-checkout-start')?.value || null;
            const checkoutEnd = document.getElementById('edit-event-checkout-end')?.value || null;

            data.checkin_start_time = (checkinStart && checkinEnd) ? `${eventDate} ${checkinStart}:00` : null;
            data.checkin_end_time = (checkinStart && checkinEnd) ? `${eventDate} ${checkinEnd}:00` : null;
            data.checkout_start_time = (checkoutStart && checkoutEnd) ? `${eventDate} ${checkoutStart}:00` : null;
            data.checkout_end_time = (checkoutStart && checkoutEnd) ? `${eventDate} ${checkoutEnd}:00` : null;
        }

        const res = await StorageManager.apiRequest(`/api/events/${eventId}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });

        if (res.ok && res.data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modal-edit-event')).hide();
            this.showToast('Event updated successfully!');
            this.loadEvents();
        } else {
            alert(res.data?.message || 'Failed to update event.');
        }
    },

    async activateEvent(eventId) {
        this.showConfirm({
            title: 'Activate Event',
            message: 'Activate this event session for real-time dynamic QR code scanning?',
            icon: 'bi-broadcast-pin',
            type: 'info',
            confirmText: 'Activate Now',
            confirmClass: 'btn-success',
            onConfirm: async () => {
                const res = await StorageManager.apiRequest(`/api/events/${eventId}/activate`, { method: 'POST' });
                if (res.ok) {
                    this.showToast('Event activated for live scanning!', 'success');
                    this.loadEvents();
                } else {
                    this.showToast(res.data?.message || 'Failed to activate event.', 'danger');
                }
            }
        });
    },

    completeEvent(eventId, eventTitle = '') {
        const modalEl = document.getElementById('modal-confirm-complete-event');
        if (!modalEl) return;

        const idInput = document.getElementById('complete-event-target-id');
        const passInput = document.getElementById('complete-event-password');
        const titleEl = document.getElementById('complete-event-prompt-title');
        const descEl = document.getElementById('complete-event-prompt-desc');
        const errEl = document.getElementById('complete-event-error');
        const submitBtn = document.getElementById('btn-execute-complete-event');

        if (idInput) idInput.value = eventId;
        if (passInput) passInput.value = '';
        if (titleEl) titleEl.innerText = eventTitle ? `Conclude "${eventTitle}"` : 'Conclude Event Session';
        if (descEl) descEl.innerText = 'This will immediately end attendance scanning and auto-generate non-attendance fines for unscanned eligible students.';
        if (errEl) {
            errEl.innerText = '';
            errEl.classList.add('d-none');
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Authorize & Conclude Event';
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        setTimeout(() => passInput?.focus(), 300);
    },

    async confirmExecuteCompleteEvent(e) {
        if (e && e.preventDefault) e.preventDefault();

        const eventId = document.getElementById('complete-event-target-id')?.value;
        const passInput = document.getElementById('complete-event-password');
        const errEl = document.getElementById('complete-event-error');
        const submitBtn = document.getElementById('btn-execute-complete-event');

        const password = passInput ? passInput.value : '';
        if (!eventId) return;

        if (!password) {
            if (errEl) {
                errEl.innerText = 'Please enter your password to authorize event conclusion.';
                errEl.classList.remove('d-none');
            }
            if (passInput) passInput.focus();
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing Absences...';
        }
        if (errEl) errEl.classList.add('d-none');

        try {
            const res = await StorageManager.apiRequest(`/api/events/${eventId}/complete`, {
                method: 'POST',
                body: JSON.stringify({ password: password })
            });

            if (res.ok && res.data.success) {
                const modalEl = document.getElementById('modal-confirm-complete-event');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                const stats = res.data.data?.absence_stats;
                const absentCount = stats ? stats.absent_records_created : 0;
                const fineSum = stats ? parseFloat(stats.total_fines_generated).toFixed(2) : '0.00';
                this.showToast(`✅ Event concluded! ${absentCount} absence(s) recorded (₱${fineSum} total fines generated).`, 'success', 6000);

                // Also hide event details modal if open
                const detailsModalEl = document.getElementById('modal-view-event-details');
                if (detailsModalEl) {
                    const detailsModal = bootstrap.Modal.getInstance(detailsModalEl);
                    if (detailsModal) detailsModal.hide();
                }

                this.loadEvents();
                this.updateLiveEventBanner();
            } else {
                const errMsg = res.data?.message || 'Authorization failed. Incorrect password.';
                if (errEl) {
                    errEl.innerText = errMsg;
                    errEl.classList.remove('d-none');
                }
                if (passInput) {
                    passInput.value = '';
                    passInput.focus();
                }
            }
        } catch (err) {
            if (errEl) {
                errEl.innerText = 'A network error occurred while verifying password.';
                errEl.classList.remove('d-none');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Authorize & Conclude Event';
            }
        }
    },

    async processEventAbsences(eventId) {
        this.showConfirm({
            title: 'Re-Process Event Absences',
            message: 'Run absence processor now? Any eligible BSIS student without verified scans will be recorded as ABSENT with fines.',
            icon: 'bi-calculator',
            type: 'warning',
            confirmText: 'Run Processor',
            confirmClass: 'btn-primary',
            onConfirm: async () => {
                const res = await StorageManager.apiRequest(`/api/events/${eventId}/process-absences`, { method: 'POST' });
                if (res.ok && res.data.success) {
                    const stats = res.data.data?.absence_stats;
                    this.showToast(`Absence processing complete: ${stats.absent_records_created} absence fine record(s) recorded.`, 'success');
                    this.loadEvents();
                } else {
                    this.showToast(res.data?.message || 'Failed to process absences.', 'danger');
                }
            }
        });
    },

    async openEventActionHub(eventId) {
        const res = await StorageManager.apiRequest(`/api/events/${eventId}`);
        if (!res.ok || !res.data.success) {
            alert(res.data?.message || 'Failed to load event details.');
            return;
        }

        const e = res.data.data.event;
        const user = StorageManager.getUser();
        const isAdmin = user && user.role === 'admin';
        const titleEscaped = (e.title || '').replace(/'/g, "\\'");

        document.getElementById('action-hub-event-title').innerText = e.title;
        const statusBadge = document.getElementById('action-hub-event-status-badge');
        if (statusBadge) {
            const isAct = e.status === 'active';
            const isUpc = e.status === 'upcoming' || e.status === 'draft';
            statusBadge.className = `badge ${isAct ? 'bg-info text-dark' : (isUpc ? 'bg-warning text-dark' : 'bg-success text-white')}`;
            statusBadge.innerText = (e.status || 'upcoming').toUpperCase();
        }

        const container = document.getElementById('action-hub-buttons-container');
        let html = '';

        if (e.status === 'active') {
            html += `
                <a href="#qr-display?event=${e.id}" onclick="bootstrap.Modal.getInstance(document.getElementById('modal-event-action-hub'))?.hide()" class="action-hub-card">
                    <div class="action-hub-icon qr"><i class="bi bi-qr-code"></i></div>
                    <div class="action-hub-info">
                        <div class="action-hub-title text-info">Launch Dynamic QR Display</div>
                        <div class="action-hub-desc">Open live auto-refreshing QR projector screen</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
                <button type="button" onclick="AdminApp.closeActionHubAndDo(() => AdminApp.openEmergencyBypassModal(${e.id}, '${titleEscaped}'))" class="action-hub-card">
                    <div class="action-hub-icon bypass"><i class="bi bi-lightning-charge-fill"></i></div>
                    <div class="action-hub-info">
                        <div class="action-hub-title text-warning">Emergency Window Bypass</div>
                        <div class="action-hub-desc">Temporarily open attendance scanning window</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </button>
            `;
        }

        if (e.status === 'upcoming' || e.status === 'draft') {
            html += `
                <button type="button" onclick="AdminApp.closeActionHubAndDo(() => AdminApp.activateEvent(${e.id}))" class="action-hub-card">
                    <div class="action-hub-icon activate"><i class="bi bi-play-circle-fill"></i></div>
                    <div class="action-hub-info">
                        <div class="action-hub-title text-success">Activate Event Now</div>
                        <div class="action-hub-desc">Start event and enable attendance scanning</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </button>
            `;
        }

        html += `
            <button type="button" onclick="AdminApp.closeActionHubAndDo(() => AdminApp.viewEventDetails(${e.id}))" class="action-hub-card">
                <div class="action-hub-icon info"><i class="bi bi-info-circle-fill"></i></div>
                <div class="action-hub-info">
                    <div class="action-hub-title">View Event Details & Map</div>
                    <div class="action-hub-desc">View scanning statistics, schedule, and venue radius</div>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
            </button>
        `;

        if (e.status === 'upcoming' || e.status === 'active' || e.status === 'draft') {
            html += `
                <button type="button" onclick="AdminApp.closeActionHubAndDo(() => AdminApp.editEvent(${e.id}))" class="action-hub-card">
                    <div class="action-hub-icon edit"><i class="bi bi-pencil-square"></i></div>
                    <div class="action-hub-info">
                        <div class="action-hub-title">Edit Event Settings</div>
                        <div class="action-hub-desc">Modify date, time windows, venue geofence, and fines</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </button>
            `;
        }

        if (e.status === 'active') {
            html += `
                <button type="button" onclick="AdminApp.closeActionHubAndDo(() => AdminApp.completeEvent(${e.id}, '${titleEscaped}'))" class="action-hub-card">
                    <div class="action-hub-icon conclude"><i class="bi bi-check2-circle"></i></div>
                    <div class="action-hub-info">
                        <div class="action-hub-title text-secondary">Conclude Event & Process Absences</div>
                        <div class="action-hub-desc">Lock scanning and automatically calculate missed scan fines</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </button>
            `;
        }

        if (e.status === 'completed') {
            html += `
                <button type="button" onclick="AdminApp.closeActionHubAndDo(() => AdminApp.processEventAbsences(${e.id}))" class="action-hub-card">
                    <div class="action-hub-icon calc"><i class="bi bi-calculator"></i></div>
                    <div class="action-hub-info">
                        <div class="action-hub-title">Re-Process Absences & Fines</div>
                        <div class="action-hub-desc">Recalculate attendance penalties and update balances</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </button>
            `;
        }

        if (isAdmin) {
            html += `
                <button type="button" onclick="AdminApp.closeActionHubAndDo(() => AdminApp.promptDeleteEvent(${e.id}, '${titleEscaped}'))" class="action-hub-card">
                    <div class="action-hub-icon delete"><i class="bi bi-trash-fill"></i></div>
                    <div class="action-hub-info">
                        <div class="action-hub-title text-danger">Delete / Drop Event</div>
                        <div class="action-hub-desc">Permanently remove this event record</div>
                    </div>
                    <i class="bi bi-chevron-right text-danger"></i>
                </button>
            `;
        }

        container.innerHTML = html;

        const modalEl = document.getElementById('modal-event-action-hub');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    },

    closeActionHubAndDo(callback) {
        const modalEl = document.getElementById('modal-event-action-hub');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        setTimeout(() => {
            if (typeof callback === 'function') callback();
        }, 250);
    },

    async viewEventDetails(eventId) {
        const res = await StorageManager.apiRequest(`/api/events/${eventId}`);
        if (!res.ok || !res.data.success) {
            alert(res.data?.message || 'Failed to load event information.');
            return;
        }

        const data = res.data.data;
        const e = data.event;
        const stats = data.statistics || {};
        const audience = data.target_audience_label || e.target_audience_label || 'All BSIS Students';
        const winStatus = data.window_status || {};

        // 1. Header & Title
        document.getElementById('detail-event-title').innerText = e.title;

        // 2. Status Badge
        const statusBadge = document.getElementById('detail-event-status-badge');
        if (statusBadge) {
            const isAct = e.status === 'active';
            const isUpc = e.status === 'upcoming' || e.status === 'draft';
            statusBadge.className = `bsis-badge ${isAct ? 'bsis-badge-event-active' : (isUpc ? 'bsis-badge-event-upcoming' : 'bsis-badge-event-completed')}`;
            statusBadge.innerHTML = isAct ? '<i class="bi bi-broadcast me-1"></i> ACTIVE' : (isUpc ? '<i class="bi bi-hourglass-split me-1"></i> UPCOMING' : '<i class="bi bi-check-circle me-1"></i> COMPLETED');
        }

        // 3. Audience Badge
        const audBadge = document.getElementById('detail-event-audience-badge');
        if (audBadge) {
            audBadge.className = `badge ${audience === 'All BSIS Students' ? 'bg-secondary' : 'bg-primary'} px-2 py-1`;
            audBadge.innerHTML = `<i class="bi bi-people-fill me-1"></i> ${audience}`;
        }

        // 4. Window Badge
        const winBadge = document.getElementById('detail-event-window-badge');
        if (winBadge) {
            if (winStatus.window_open) {
                winBadge.className = 'badge bg-success text-white px-2 py-1';
                winBadge.innerHTML = `<i class="bi bi-door-open-fill me-1"></i> Window: Open (${winStatus.type || 'Time-In'})`;
            } else {
                winBadge.className = 'badge bg-light text-secondary border px-2 py-1';
                winBadge.innerHTML = `<i class="bi bi-door-closed-fill me-1"></i> Window: Closed`;
            }
        }

        // 5. Statistics
        document.getElementById('detail-stat-total').innerText = stats.total_attendance || 0;
        document.getElementById('detail-stat-present').innerText = stats.present_count || 0;
        document.getElementById('detail-stat-late').innerText = stats.late_count || 0;
        document.getElementById('detail-stat-fines').innerText = `₱${parseFloat(stats.total_fines || 0).toFixed(2)}`;

        // 6. Schedule & Session Windows
        const scheduleText = `${this.formatEventDisplayDateTime(e.start_time)} to ${this.formatEventDisplayDateTime(e.end_time)}`;
        document.getElementById('detail-event-schedule').innerText = scheduleText;
        document.getElementById('detail-event-fine').innerText = `₱${parseFloat(e.fine_amount).toFixed(2)}`;

        const isWhole = e.session_type === 'whole_day';
        const sessionBadge = document.getElementById('detail-event-session-type-badge');
        if (sessionBadge) sessionBadge.innerText = isWhole ? '4 SCANS (AM & PM)' : '2 SCANS (IN & OUT)';

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

        const windowsContainer = document.getElementById('detail-event-windows-container');
        if (windowsContainer) {
            if (isWhole) {
                windowsContainer.innerHTML = `
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-sun-fill text-warning me-1"></i> AM Time-In</span>
                            <div class="small">${fmtWindowRange(e.am_checkin_start_time, e.am_checkin_end_time)}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-box-arrow-right text-info me-1"></i> AM Time-Out</span>
                            <div class="small">${fmtWindowRange(e.am_checkout_start_time, e.am_checkout_end_time)}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-cloud-sun-fill text-primary me-1"></i> PM Time-In</span>
                            <div class="small">${fmtWindowRange(e.pm_checkin_start_time, e.pm_checkin_end_time)}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-check2-all text-success me-1"></i> PM Time-Out</span>
                            <div class="small">${fmtWindowRange(e.pm_checkout_start_time, e.pm_checkout_end_time)}</div>
                        </div>
                    </div>
                `;
            } else {
                windowsContainer.innerHTML = `
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-box-arrow-in-right text-success me-1"></i> Time-In Window</span>
                            <div class="small">${fmtWindowRange(e.checkin_start_time, e.checkin_end_time)}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white h-100 shadow-sm">
                            <span class="text-dark d-block small fw-bold mb-1"><i class="bi bi-box-arrow-right text-info me-1"></i> Time-Out Window</span>
                            <div class="small">${fmtWindowRange(e.checkout_start_time, e.checkout_end_time)}</div>
                        </div>
                    </div>
                `;
            }
        }

        // 7. Venue & Radius
        document.getElementById('detail-event-venue-name').innerHTML = `<strong>${e.venue_name}</strong> &bull; <span class="text-muted">Lat: ${parseFloat(e.venue_latitude).toFixed(6)}, Lng: ${parseFloat(e.venue_longitude).toFixed(6)}</span>`;
        document.getElementById('detail-event-radius-badge').innerText = `${e.allowed_radius_meters}m Perimeter Radius`;

        // 8. Description, Creator, Staff
        document.getElementById('detail-event-desc').innerText = e.description || 'No specific description or agenda notes provided.';
        document.getElementById('detail-event-creator').innerText = e.creator ? e.creator.full_name : 'System Administrator';
        
        const staffList = (e.staff && e.staff.length > 0) ? e.staff.map(s => s.full_name).join(', ') : 'All Assigned Event Staff';
        document.getElementById('detail-event-staff').innerText = staffList;

        // 9. Actions Bar
        const actionsBar = document.getElementById('detail-event-actions-bar');
        if (actionsBar) {
            let actionsHtml = '';
            if (e.status === 'active') {
                actionsHtml += `<button type="button" onclick="AdminApp.openEventQrDisplay(${e.id})" class="btn btn-bsis-info btn-sm fw-bold text-white d-inline-flex align-items-center justify-content-center text-nowrap flex-grow-1 flex-sm-grow-0 py-2 px-3" style="min-height: 38px; font-size: 0.82rem;"><i class="bi bi-qr-code me-1"></i> Dynamic QR</button>`;
                actionsHtml += `<button type="button" onclick="AdminApp.completeEvent(${e.id}, '${e.title.replace(/'/g, "\\'")}')" class="btn btn-warning btn-sm fw-bold d-inline-flex align-items-center justify-content-center text-nowrap flex-grow-1 flex-sm-grow-0 py-2 px-3" style="min-height: 38px; font-size: 0.82rem;"><i class="bi bi-flag-fill me-1"></i> Conclude Event</button>`;
            } else if (e.status === 'upcoming' || e.status === 'draft') {
                actionsHtml += `<button type="button" onclick="AdminApp.activateEvent(${e.id})" class="btn btn-success btn-sm fw-bold d-inline-flex align-items-center justify-content-center text-nowrap flex-grow-1 flex-sm-grow-0 py-2 px-3" style="min-height: 38px; font-size: 0.82rem;"><i class="bi bi-play-circle me-1"></i> Activate Event</button>`;
            }
            actionsHtml += `<button type="button" onclick="AdminApp.jumpToEventReports(${e.id})" class="btn btn-bsis-primary btn-sm fw-bold d-inline-flex align-items-center justify-content-center text-nowrap flex-grow-1 flex-sm-grow-0 py-2 px-3" style="min-height: 38px; font-size: 0.82rem;"><i class="bi bi-file-earmark-bar-graph me-1"></i> Reports & Roster</button>`;
            actionsBar.innerHTML = actionsHtml;
        }

        // 10. Show Modal
        const modalEl = document.getElementById('modal-view-event-details');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        // 11. Render Leaflet Map
        modalEl.addEventListener('shown.bs.modal', () => {
            const lat = parseFloat(e.venue_latitude) || 10.1492;
            const lng = parseFloat(e.venue_longitude) || 124.3312;
            const radius = parseInt(e.allowed_radius_meters) || 50;

            if (!this.detailEventMap) {
                this.detailEventMap = L.map('detail-event-map').setView([lat, lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap'
                }).addTo(this.detailEventMap);

                this.detailMarker = L.marker([lat, lng]).addTo(this.detailEventMap);
                this.detailCircle = L.circle([lat, lng], {
                    color: '#35C4E8',
                    fillColor: '#35C4E8',
                    fillOpacity: 0.25,
                    radius: radius
                }).addTo(this.detailEventMap);
            } else {
                this.detailEventMap.invalidateSize();
                this.detailEventMap.setView([lat, lng], 16);
                this.detailMarker.setLatLng([lat, lng]);
                this.detailCircle.setLatLng([lat, lng]);
                this.detailCircle.setRadius(radius);
            }
            this.detailEventMap.invalidateSize();
        }, { once: true });
    },

    openEventQrDisplay(eventId) {
        const modalEl = document.getElementById('modal-view-event-details');
        if (modalEl) {
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();
        }
        window.location.hash = `#qr-display?event=${eventId}`;
        this.handleRoute();
    },

    jumpToEventReports(eventId) {
        const modalEl = document.getElementById('modal-view-event-details');
        if (modalEl) {
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();
        }
        window.location.hash = '#reports';
        this.handleRoute();
        const filter = document.getElementById('report-event-filter');
        if (filter) {
            filter.value = eventId;
            this.loadReports();
        }
    },

    // 3. DYNAMIC CONFIGURABLE QR DISPLAY SCREEN FOR STAFF
    currentQrEventId: null,
    currentWindowEnd: null,
    currentWindowIsOpen: false,
    currentBypassExpiresAt: null,

    parseTimestamp(val) {
        if (!val) return null;
        if (typeof val === 'number') return isNaN(val) ? null : val;
        // Direct parse (handles ISO-8601 strings like "2026-08-20T21:24:00+08:00")
        let d = new Date(val);
        if (!isNaN(d.getTime())) return d.getTime();
        // Fallback for "YYYY-MM-DD HH:MM:SS" (converts dashes to slashes for older engines)
        d = new Date(String(val).replace(/-/g, '/'));
        if (!isNaN(d.getTime())) return d.getTime();
        return null;
    },

    formatCountdown(totalSeconds) {
        if (isNaN(totalSeconds) || totalSeconds <= 0) return '00:00';
        const hrs = Math.floor(totalSeconds / 3600);
        const mins = Math.floor((totalSeconds % 3600) / 60);
        const secs = totalSeconds % 60;
        if (hrs > 0) {
            return `${hrs}h ${String(mins).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`;
        }
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    },

    async startQrDisplay(eventId) {
        if (!eventId) return;
        this.stopQrTimer();
        this.currentQrEventId = eventId;
        
        await this.fetchAndRenderQrToken(eventId);

        if (!this.hasQrVisibilityListener) {
            this.hasQrVisibilityListener = true;
            document.addEventListener('visibilitychange', async () => {
                if (!document.hidden && this.currentQrEventId && (this.currentWindowIsOpen || this.currentQrEventData?.allow_window_bypass)) {
                    await this.fetchAndRenderQrToken(this.currentQrEventId);
                }
            });
        }

        this.activeQrInterval = setInterval(async () => {
            const cdBadge = document.getElementById('qr-window-countdown-badge');
            const cdText = document.getElementById('qr-window-countdown-text');

            // 1. Check if Emergency Bypass is active with an auto-expiry timer
            if (this.currentQrEventData?.allow_window_bypass && this.currentBypassExpiresAt) {
                const now = new Date().getTime();
                const endMs = this.parseTimestamp(this.currentBypassExpiresAt);
                const bypassRemainingSec = endMs !== null ? Math.floor((endMs - now) / 1000) : null;

                if (bypassRemainingSec !== null && bypassRemainingSec <= 0) {
                    // Bypass has expired! Auto-revert to scheduled rules immediately
                    this.currentBypassExpiresAt = null;
                    if (cdBadge) cdBadge.classList.add('d-none');
                    await this.fetchAndRenderQrToken(eventId);
                    return;
                } else if (cdBadge && cdText && bypassRemainingSec !== null) {
                    const formatted = this.formatCountdown(bypassRemainingSec);
                    const quotaCount = this.currentQrEventData?.bypass_count || 1;
                    cdText.innerText = `🚨 Bypass Active: ${formatted} left (${quotaCount}/2 used)`;
                    cdBadge.className = 'bsis-badge bsis-badge-warning';
                    cdBadge.classList.remove('d-none');
                }
            } else if (this.currentWindowEnd && !this.currentQrEventData?.allow_window_bypass) {
                // 2. Check scheduled attendance window expiration
                const now = new Date().getTime();
                const endMs = this.parseTimestamp(this.currentWindowEnd);
                const windowRemainingSec = endMs !== null ? Math.floor((endMs - now) / 1000) : null;

                if (windowRemainingSec !== null && windowRemainingSec <= 0) {
                    // Window has expired! Immediately auto-close QR display without waiting or manual refresh!
                    this.currentWindowIsOpen = false;
                    this.currentWindowEnd = null;

                    // Immediately wipe and hide QR code
                    const qrWrapper = document.getElementById('qr-canvas-wrapper');
                    const qrImg = document.getElementById('qr-code-image');
                    const activeContainer = document.getElementById('qr-active-container');
                    const overlay = document.getElementById('qr-closed-overlay');
                    const badge = document.getElementById('qr-window-badge');
                    const msgBox = document.getElementById('qr-window-message');
                    const rawToken = document.getElementById('qr-raw-token-text');

                    if (qrWrapper) qrWrapper.innerHTML = '';
                    if (qrImg) qrImg.classList.add('d-none');
                    if (rawToken) rawToken.innerText = '';
                    if (activeContainer) activeContainer.classList.add('d-none');
                    if (overlay) {
                        overlay.classList.remove('d-none');
                        const closedDetails = document.getElementById('qr-closed-details');
                        if (closedDetails) closedDetails.innerText = 'The scheduled Time-In / Time-Out attendance window has ended.';
                    }
                    if (badge) {
                        badge.innerText = '🔴 ATTENDANCE WINDOW CLOSED';
                        badge.className = 'bsis-badge bsis-badge-danger';
                    }
                    if (msgBox) {
                        msgBox.innerText = 'Attendance window expired. Scanning is now closed for this session.';
                        msgBox.className = 'alert alert-warning py-2 px-3 mx-auto mb-3 text-center fw-semibold';
                        msgBox.classList.remove('d-none');
                    }
                    if (cdBadge) cdBadge.classList.add('d-none');

                    // Synchronize with server
                    await this.fetchAndRenderQrToken(eventId);
                    return;
                } else if (cdBadge && cdText && this.currentWindowIsOpen && windowRemainingSec !== null) {
                    const formatted = this.formatCountdown(windowRemainingSec);
                    cdText.innerText = `Closes in ${formatted}`;
                    cdBadge.classList.remove('d-none');
                    if (windowRemainingSec <= 60) {
                        cdBadge.className = 'bsis-badge bsis-badge-danger';
                    } else if (windowRemainingSec <= 300) {
                        cdBadge.className = 'bsis-badge bsis-badge-warning';
                    } else {
                        cdBadge.className = 'bsis-badge bsis-badge-info';
                    }
                }
            } else {
                if (cdBadge) cdBadge.classList.add('d-none');
            }

            // Only tick the rotating QR token timer if the window is open or bypass is active
            if (this.currentWindowIsOpen || this.currentQrEventData?.allow_window_bypass) {
                const now = Date.now();
                const tokenRemainingSec = this.currentQrTokenExpiresAt ? Math.max(0, Math.floor((this.currentQrTokenExpiresAt - now) / 1000)) : 0;

                const timerText = document.getElementById('qr-timer-text');
                const timerProgress = document.getElementById('qr-timer-progress');
                if (timerText) timerText.innerText = `${tokenRemainingSec}s`;
                const totalDuration = this.currentQrDurationSeconds > 0 ? this.currentQrDurationSeconds : 20;
                if (timerProgress) timerProgress.style.width = `${(tokenRemainingSec / totalDuration) * 100}%`;

                if (tokenRemainingSec <= 0) {
                    await this.fetchAndRenderQrToken(eventId);
                }
            }
        }, 1000);
    },

    stopQrTimer() {
        if (this.activeQrInterval) {
            clearInterval(this.activeQrInterval);
            this.activeQrInterval = null;
        }
    },

    async fetchAndRenderQrToken(eventId) {
        this.currentQrEventId = eventId;
        const res = await StorageManager.apiRequest(`/api/events/${eventId}/qr-token`, { method: 'POST' });
        if (res.ok && res.data.success) {
            const token = res.data.data.qr_token;
            const event = res.data.data.event;
            const duration = res.data.data.expires_in_seconds;
            const windowStatus = res.data.data.window_status;
            const expiresAt = res.data.data.expires_at;

            this.currentQrDurationSeconds = duration > 0 ? duration : 20;
            this.currentQrTokenExpiresAt = expiresAt ? new Date(expiresAt).getTime() : (Date.now() + (this.currentQrDurationSeconds * 1000));
            this.currentQrEventData = event;
            this.currentWindowEnd = windowStatus?.window_end || windowStatus?.next_time || null;
            this.currentBypassExpiresAt = windowStatus?.bypass_expires_at || event.bypass_expires_at || null;
            this.currentWindowIsOpen = (windowStatus?.is_open || false) || !!event.allow_window_bypass;

            const titleEl = document.getElementById('qr-display-title');
            const venueEl = document.getElementById('qr-display-venue');
            const rawTokenEl = document.getElementById('qr-raw-token-text');
            const intervalBadge = document.getElementById('qr-interval-badge-display');

            if (titleEl) titleEl.innerText = event.title;
            if (venueEl) venueEl.innerText = event.venue_name;
            if (rawTokenEl) rawTokenEl.innerText = token || '';
            if (intervalBadge) intervalBadge.innerText = `Refreshes every ${this.currentQrDurationSeconds}s`;

            // Window Status Badge and Message
            const badge = document.getElementById('qr-window-badge');
            const msgBox = document.getElementById('qr-window-message');
            const bypassBtnText = document.getElementById('qr-bypass-btn-text');
            const overlay = document.getElementById('qr-closed-overlay');
            const activeContainer = document.getElementById('qr-active-container');
            const bypassBtn = document.getElementById('btn-toggle-bypass');
            const qrWrapper = document.getElementById('qr-canvas-wrapper');
            const qrImg = document.getElementById('qr-code-image');
            const cdBadge = document.getElementById('qr-window-countdown-badge');
            const cdText = document.getElementById('qr-window-countdown-text');

            if (bypassBtnText) {
                bypassBtnText.innerText = `Emergency Bypass: ${event.allow_window_bypass ? 'ON ⚡' : 'OFF'}`;
                if (event.allow_window_bypass) {
                    bypassBtn?.classList.remove('btn-outline-warning');
                    bypassBtn?.classList.add('btn-warning');
                } else {
                    bypassBtn?.classList.remove('btn-warning');
                    bypassBtn?.classList.add('btn-outline-warning');
                }
            }

            if (badge && windowStatus) {
                badge.innerText = windowStatus.label;
                badge.className = 'bsis-badge ' + (
                    windowStatus.phase === 'bypass' ? 'bsis-badge-warning' :
                    windowStatus.phase === 'checkin' || windowStatus.phase === 'am_checkin' || windowStatus.phase === 'pm_checkin' ? 'bsis-badge-success' :
                    windowStatus.phase === 'checkout' || windowStatus.phase === 'am_checkout' || windowStatus.phase === 'pm_checkout' ? 'bsis-badge-info' :
                    windowStatus.is_open ? 'bsis-badge-success' : 'bsis-badge-danger'
                );
            }

            // Immediately update countdown text on render
            if (event.allow_window_bypass && this.currentBypassExpiresAt) {
                const now = new Date().getTime();
                const endMs = this.parseTimestamp(this.currentBypassExpiresAt);
                const sec = endMs !== null ? Math.max(0, Math.floor((endMs - now) / 1000)) : 0;
                if (cdBadge && cdText) {
                    const formatted = this.formatCountdown(sec);
                    const quotaCount = event.bypass_count || 1;
                    cdText.innerText = `🚨 Bypass Active: ${formatted} left (${quotaCount}/2 used)`;
                    cdBadge.className = 'bsis-badge bsis-badge-warning';
                    cdBadge.classList.remove('d-none');
                }
            } else if (this.currentWindowEnd && this.currentWindowIsOpen) {
                const now = new Date().getTime();
                const endMs = this.parseTimestamp(this.currentWindowEnd);
                const sec = endMs !== null ? Math.max(0, Math.floor((endMs - now) / 1000)) : 0;
                if (cdBadge && cdText) {
                    const formatted = this.formatCountdown(sec);
                    cdText.innerText = `Closes in ${formatted}`;
                    cdBadge.className = 'bsis-badge bsis-badge-info';
                    cdBadge.classList.remove('d-none');
                }
            } else {
                if (cdBadge) cdBadge.classList.add('d-none');
            }

            if (msgBox && windowStatus) {
                msgBox.innerText = windowStatus.message;
                msgBox.className = 'alert ' + (windowStatus.is_open ? 'alert-info' : 'alert-warning') + ' py-2 px-3 mx-auto mb-3 text-center fw-semibold';
                msgBox.classList.remove('d-none');
            }

            // If window is closed and bypass is disabled, show closed overlay and destroy QR canvas
            if (!this.currentWindowIsOpen || !token) {
                if (qrWrapper) qrWrapper.innerHTML = '';
                if (qrImg) qrImg.classList.add('d-none');
                if (rawTokenEl) rawTokenEl.innerText = '';
                if (overlay) {
                    overlay.classList.remove('d-none');
                    const closedDetails = document.getElementById('qr-closed-details');
                    if (closedDetails) closedDetails.innerText = windowStatus?.message || 'Scanning is currently outside the scheduled attendance window.';
                }
                if (activeContainer) activeContainer.classList.add('d-none');
            } else {
                if (overlay) overlay.classList.add('d-none');
                if (activeContainer) activeContainer.classList.remove('d-none');

                // Generate QR Code using instant client-side QRCode library or fallback to image
                if (window.QRCode && qrWrapper) {
                    qrWrapper.innerHTML = '';
                    new QRCode(qrWrapper, {
                        text: token,
                        width: 480,
                        height: 480,
                        colorDark: '#0B2046',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M
                    });
                    if (qrImg) qrImg.classList.add('d-none');
                    qrWrapper.classList.remove('d-none');
                } else if (qrImg) {
                    qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=480x480&data=${encodeURIComponent(token)}`;
                    qrImg.classList.remove('d-none');
                }
            }

            // Update attendance counts for live QR view
            const liveRes = await StorageManager.apiRequest(`/api/dashboard/live-attendance/${eventId}`);
            if (liveRes.ok && liveRes.data?.data) {
                const stats = liveRes.data.data.statistics;
                if (stats) {
                    document.getElementById('qr-live-present').innerText = stats.present_count ?? 0;
                    document.getElementById('qr-live-late').innerText = stats.late_count ?? 0;
                    document.getElementById('qr-live-total').innerText = stats.total_scanned ?? 0;
                }
            }
        } else {
            const errorMsg = res.data?.message || 'Unable to generate dynamic QR token for this event.';
            const titleEl = document.getElementById('qr-display-title');
            if (titleEl) titleEl.innerText = 'Event QR Unavailable';
            const msgBox = document.getElementById('qr-window-message');
            if (msgBox) {
                msgBox.innerText = errorMsg;
                msgBox.className = 'alert alert-danger py-2 px-3 mx-auto mb-3 text-center fw-semibold';
                msgBox.classList.remove('d-none');
            }
            this.showToast(errorMsg, 'danger');
        }
    },

    toggleQrFullscreen() {
        const qrSection = document.getElementById('view-qr-display');
        if (!qrSection) return;

        const isFs = document.fullscreenElement || qrSection.classList.contains('is-fullscreen') || document.body.classList.contains('qr-fullscreen-active');

        if (!isFs) {
            if (qrSection.requestFullscreen) {
                qrSection.requestFullscreen().catch(() => this.applyCssFullscreen(true));
            } else if (qrSection.webkitRequestFullscreen) {
                qrSection.webkitRequestFullscreen();
            } else {
                this.applyCssFullscreen(true);
            }
            this.applyCssFullscreen(true);
        } else {
            if (document.exitFullscreen && document.fullscreenElement) {
                document.exitFullscreen().catch(() => {});
            } else if (document.webkitExitFullscreen && document.webkitFullscreenElement) {
                document.webkitExitFullscreen();
            }
            this.applyCssFullscreen(false);
        }
    },

    applyCssFullscreen(enable) {
        const qrSection = document.getElementById('view-qr-display');
        const fsBtnText = document.getElementById('btn-fullscreen-qr-text');
        const fsBtnIcon = document.getElementById('btn-fullscreen-qr-icon');
        const fsBtn = document.getElementById('btn-fullscreen-qr');

        if (enable) {
            qrSection?.classList.add('is-fullscreen');
            document.body.classList.add('qr-fullscreen-active');
            if (fsBtnText) fsBtnText.innerText = 'Exit Fullscreen (Esc)';
            if (fsBtnIcon) fsBtnIcon.className = 'bi bi-fullscreen-exit';
            if (fsBtn) {
                fsBtn.classList.remove('btn-bsis-primary');
                fsBtn.classList.add('btn-outline-light');
            }
        } else {
            qrSection?.classList.remove('is-fullscreen');
            document.body.classList.remove('qr-fullscreen-active');
            if (fsBtnText) fsBtnText.innerText = 'Fullscreen Mode';
            if (fsBtnIcon) fsBtnIcon.className = 'bi bi-arrows-fullscreen';
            if (fsBtn) {
                fsBtn.classList.remove('btn-outline-light');
                fsBtn.classList.add('btn-bsis-primary');
            }
        }
    },

    toggleBypassPasswordVisibility(btn) {
        const input = document.getElementById('bypass-auth-password');
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<i class="bi bi-eye"></i>';
        }
    },

    async toggleQrBypass() {
        if (!this.currentQrEventId) return;

        const isCurrentlyActive = this.currentQrEventData?.allow_window_bypass;
        const bypassCount = this.currentQrEventData?.bypass_count || 0;
        const currentUser = StorageManager.getUser();

        if (!isCurrentlyActive) {
            // Check quota for event staff (max 2 activations per event)
            if (currentUser?.role !== 'admin' && bypassCount >= 2) {
                this.showConfirm({
                    title: 'Emergency Bypass Limit Reached',
                    message: `This event has already reached its maximum of 2 Emergency Bypass activations (${bypassCount} of 2 used). To prevent attendance abuse, no further staff bypasses can be authorized for this event. Please contact the Lead Administrator if an emergency override is required.`,
                    icon: 'bi-exclamation-octagon-fill',
                    type: 'danger',
                    confirmText: 'Understood',
                    confirmClass: 'btn-secondary',
                    onConfirm: () => {}
                });
                return;
            }

            // Turning ON: Require Password Verification & Duration/Reason Modal
            const modalEl = document.getElementById('modal-bypass-confirm');
            const passInput = document.getElementById('bypass-auth-password');
            const reasonInput = document.getElementById('bypass-auth-reason');
            const durationSelect = document.getElementById('bypass-duration-minutes');
            const quotaBadge = document.getElementById('bypass-quota-badge');
            const errEl = document.getElementById('bypass-auth-error');

            if (passInput) passInput.value = '';
            if (reasonInput) reasonInput.value = '';
            if (durationSelect) durationSelect.value = '20';
            if (quotaBadge) quotaBadge.innerText = `Activation ${bypassCount + 1} of 2`;
            if (errEl) { errEl.innerText = ''; errEl.classList.add('d-none'); }

            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
                setTimeout(() => reasonInput?.focus(), 300);
            }
        } else {
            // Turning OFF: Simple confirmation
            this.showConfirm({
                title: 'Disable Emergency Bypass',
                message: 'Are you sure you want to turn OFF Emergency Bypass early and restore strict scheduled attendance window rules?',
                icon: 'bi-lightning-charge',
                type: 'warning',
                confirmText: 'Disable Bypass',
                confirmClass: 'btn-warning',
                onConfirm: async () => {
                    const res = await StorageManager.apiRequest(`/api/events/${this.currentQrEventId}/toggle-bypass`, {
                        method: 'POST',
                        body: {}
                    });
                    if (res.ok && res.data.success) {
                        this.showToast(res.data?.message || 'Emergency Bypass disabled.', 'info');
                        await this.fetchAndRenderQrToken(this.currentQrEventId);
                    } else {
                        this.showToast(res.data?.message || 'Failed to disable emergency bypass.', 'danger');
                    }
                }
            });
        }
    },

    async handleBypassAuthSubmit(e) {
        e.preventDefault();
        if (!this.currentQrEventId) return;

        const passInput = document.getElementById('bypass-auth-password');
        const reasonInput = document.getElementById('bypass-auth-reason');
        const durationSelect = document.getElementById('bypass-duration-minutes');
        const errEl = document.getElementById('bypass-auth-error');
        const submitBtn = document.getElementById('btn-submit-bypass-auth');

        const password = passInput ? passInput.value : '';
        const reason = reasonInput ? reasonInput.value.trim() : '';
        const duration_minutes = durationSelect ? parseInt(durationSelect.value, 10) : 20;

        if (!reason) {
            if (errEl) {
                errEl.innerText = 'Please specify a reason for enabling Emergency Bypass.';
                errEl.classList.remove('d-none');
            }
            if (reasonInput) reasonInput.focus();
            return;
        }

        if (!password) {
            if (errEl) {
                errEl.innerText = 'Please enter your account password.';
                errEl.classList.remove('d-none');
            }
            if (passInput) passInput.focus();
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Authorizing...';
        }
        if (errEl) errEl.classList.add('d-none');

        try {
            const res = await StorageManager.apiRequest(`/api/events/${this.currentQrEventId}/toggle-bypass`, {
                method: 'POST',
                body: JSON.stringify({ password, reason, duration_minutes })
            });

            if (res.ok && res.data.success) {
                const modalEl = document.getElementById('modal-bypass-confirm');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                if (passInput) passInput.value = '';
                if (reasonInput) reasonInput.value = '';
                this.showToast(res.data?.message || `⚡ Emergency Bypass ENABLED for ${duration_minutes} minutes!`, 'success');
                await this.fetchAndRenderQrToken(this.currentQrEventId);
            } else {
                const errMsg = res.data?.message || 'Authorization failed. Please check your password.';
                if (errEl) {
                    errEl.innerText = errMsg;
                    errEl.classList.remove('d-none');
                }
                if (passInput) passInput.focus();
            }
        } catch (err) {
            if (errEl) {
                errEl.innerText = 'A network error occurred while verifying password.';
                errEl.classList.remove('d-none');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-lightning-charge-fill me-1"></i> Authorize & Activate Timer';
            }
        }
    },

    // 4. LIVE ATTENDANCE MONITOR
    async loadLiveMonitorEventsDropdown() {
        const res = await StorageManager.apiRequest('/api/events?status=active');
        const select = document.getElementById('live-event-select');
        if (res.ok && res.data.data.data.length > 0) {
            select.innerHTML = res.data.data.data.map(e => `<option value="${e.id}">${e.title} (${e.venue_name})</option>`).join('');
            this.startLiveMonitoring(select.value);
        } else {
            select.innerHTML = '<option value="">No Active Event Session</option>';
            document.getElementById('live-monitor-table-body').innerHTML = '<tr><td colspan="7" class="text-center text-muted">No active events found.</td></tr>';
        }
    },

    startLiveMonitoring(eventId) {
        this.stopLivePoll();
        if (!eventId) return;
        this.lastScanId = 0;
        document.getElementById('live-monitor-table-body').innerHTML = '';

        this.pollLiveAttendance(eventId);
        this.activeLivePollInterval = setInterval(() => this.pollLiveAttendance(eventId), 3000);
    },

    stopLivePoll() {
        if (this.activeLivePollInterval) {
            clearInterval(this.activeLivePollInterval);
            this.activeLivePollInterval = null;
        }
    },

    async pollLiveAttendance(eventId) {
        const res = await StorageManager.apiRequest(`/api/dashboard/live-attendance/${eventId}?last_scan_id=${this.lastScanId}`);
        if (!res.ok || !res.data.success) return;

        const data = res.data.data;
        this.lastScanId = data.latest_scan_id;

        document.getElementById('live-stat-total').innerText = data.statistics.total_scanned;
        document.getElementById('live-stat-present').innerText = data.statistics.present_count;
        document.getElementById('live-stat-late').innerText = data.statistics.late_count;

        const table = document.getElementById('live-monitor-table-body');
        if (data.scans.length > 0) {
            this.playAudioCue('success');
            data.scans.forEach(s => {
                const tr = document.createElement('tr');
                tr.className = 'table-success-highlight';

                let scanTypeBadge = '';
                const st = (s.scan_type || '').toUpperCase();

                if (st.includes('PM') && st.includes('OUT')) {
                    scanTypeBadge = '<span class="bsis-badge bsis-badge-primary"><i class="bi bi-box-arrow-right me-1"></i> PM TIME-OUT</span>';
                } else if (st.includes('PM') && st.includes('IN')) {
                    scanTypeBadge = '<span class="bsis-badge bsis-badge-info"><i class="bi bi-box-arrow-in-right me-1"></i> PM TIME-IN</span>';
                } else if (st.includes('AM') && st.includes('OUT')) {
                    scanTypeBadge = '<span class="bsis-badge bsis-badge-warning"><i class="bi bi-box-arrow-right me-1"></i> AM TIME-OUT</span>';
                } else if (st.includes('AM') && st.includes('IN')) {
                    scanTypeBadge = '<span class="bsis-badge bsis-badge-success"><i class="bi bi-box-arrow-in-right me-1"></i> AM TIME-IN</span>';
                } else if (st.includes('COMPLETED')) {
                    scanTypeBadge = '<span class="bsis-badge bsis-badge-primary"><i class="bi bi-check2-all me-1"></i> COMPLETED</span>';
                } else if (st.includes('OUT') || st === 'TIME-OUT' || st === 'CHECKOUT') {
                    scanTypeBadge = '<span class="bsis-badge bsis-badge-info"><i class="bi bi-box-arrow-right me-1"></i> TIME-OUT</span>';
                } else {
                    scanTypeBadge = '<span class="bsis-badge bsis-badge-success"><i class="bi bi-box-arrow-in-right me-1"></i> TIME-IN</span>';
                }

                const displayTime = s.formatted_time || s.formatted_pm_out || s.formatted_checkout_time || s.formatted_am_in || '—';

                tr.innerHTML = `
                    <td><strong>${s.student_number}</strong></td>
                    <td>${s.student_name}</td>
                    <td>${scanTypeBadge}</td>
                    <td><span class="bsis-badge ${s.status === 'present' ? 'bsis-badge-success' : 'bsis-badge-warning'}">${s.status.toUpperCase()}</span></td>
                    <td class="font-monospace">${displayTime}</td>
                    <td>${s.distance_meters}m</td>
                    <td>${s.is_offline_sync ? '<span class="bsis-badge bsis-badge-warning">Offline Sync</span>' : '<span class="bsis-badge bsis-badge-success">Live QR</span>'}</td>
                    <td>₱${parseFloat(s.fine_amount).toFixed(2)}</td>
                `;
                table.prepend(tr);
            });
        }
    },

    // MANUAL OVERRIDE MODAL
    async populateOverrideEventsDropdown() {
        const select = document.getElementById('override-event-select');
        if (!select) return;
        const currentLiveEvent = document.getElementById('live-event-select')?.value;
        const res = await StorageManager.apiRequest('/api/events?status=active');
        if (res.ok && res.data.data.data.length > 0) {
            select.innerHTML = res.data.data.data.map(e => `
                <option value="${e.id}" ${currentLiveEvent == e.id ? 'selected' : ''}>${e.title} (${e.venue_name})</option>
            `).join('');
        } else {
            select.innerHTML = '<option value="">No Active Event Sessions Found</option>';
        }
    },

    async handleManualOverride(event) {
        event.preventDefault();
        const eventId = parseInt(document.getElementById('override-event-select').value);
        const studentInput = document.getElementById('override-student-input').value.trim();
        const reason = document.getElementById('override-reason').value.trim();
        const status = document.getElementById('override-status-select')?.value || 'manual_override';
        const btn = document.getElementById('btn-submit-override');

        if (!eventId) {
            alert('Please select an active event session.');
            return;
        }

        if (!studentInput) {
            alert('Please enter the Student ID number or institutional email.');
            return;
        }

        if (!reason || reason.length < 3) {
            alert('Please enter an override reason (min 3 characters).');
            return;
        }

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Recording...';
        }

        const data = {
            event_id: eventId,
            student_identifier: studentInput,
            reason: reason,
            status: status
        };

        const res = await StorageManager.apiRequest('/api/attendance/override', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Record Override Attendance';
        }

        if (res.ok && res.data.success) {
            const modalEl = document.getElementById('modal-manual-override');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            const studentName = res.data.data?.student?.full_name || studentInput;
            this.showToast(`✓ Attendance manually recorded for ${studentName}!`);
            
            // Reset form
            document.getElementById('override-student-input').value = '';
            document.getElementById('override-reason').value = '';

            // Refresh live monitor if on screen
            if (window.location.hash === '#live-monitor') {
                this.pollLiveAttendance(eventId);
            }
        } else {
            alert(res.data?.message || 'Manual override failed. Please verify student ID/email and event.');
        }
    },

    // 5. USER MANAGEMENT (ENHANCED ROLE TABS, TARGET SEARCH, YEAR LEVEL, BLOCK & SORTING)
    setUserRoleTab(role, btn) {
        document.querySelectorAll('.user-role-tab').forEach(b => {
            b.classList.remove('active');
        });
        if (btn) {
            btn.classList.add('active');
        }

        const roleSelect = document.getElementById('user-role-filter');
        if (roleSelect) roleSelect.value = role;

        // Toggle visibility of student-specific filters (Year Level & Block)
        const yearWrapper = document.getElementById('user-year-filter-wrapper');
        const blockWrapper = document.getElementById('user-block-filter-wrapper');
        const isStudentOrAll = role === '' || role === 'student';

        if (yearWrapper) yearWrapper.style.opacity = isStudentOrAll ? '1' : '0.4';
        if (blockWrapper) blockWrapper.style.opacity = isStudentOrAll ? '1' : '0.4';

        this.loadUsers();
    },

    syncRoleDropdownToTabs(role) {
        document.querySelectorAll('.user-role-tab').forEach(b => {
            const matches = b.getAttribute('data-role') === role;
            b.classList.toggle('active', matches);
        });

        const yearWrapper = document.getElementById('user-year-filter-wrapper');
        const blockWrapper = document.getElementById('user-block-filter-wrapper');
        const isStudentOrAll = role === '' || role === 'student';

        if (yearWrapper) yearWrapper.style.opacity = isStudentOrAll ? '1' : '0.4';
        if (blockWrapper) blockWrapper.style.opacity = isStudentOrAll ? '1' : '0.4';
    },

    handleYearOrBlockFilterChange() {
        const yearLevel = document.getElementById('user-year-filter')?.value || '';
        const block = document.getElementById('user-block-filter')?.value || '';

        // If either a specific year level or block is selected, automatically arrange alphabetical by last name (A-Z)
        if (yearLevel !== '' || block !== '') {
            const sortBySelect = document.getElementById('user-sort-by');
            const sortOrderSelect = document.getElementById('user-sort-order');
            if (sortBySelect) sortBySelect.value = 'last_name';
            if (sortOrderSelect) sortOrderSelect.value = 'asc';
        }

        this.usersCurrentPage = 1;
        this.loadUsers();
    },

    // PREDICTIVE SEARCH AUTOCOMPLETE ENGINE
    async fetchAutocompleteSuggestions(query, role = '') {
        if (!query || query.trim().length < 1) return [];
        const params = new URLSearchParams({
            search: query.trim(),
            per_page: 8
        });
        if (role) params.append('role', role);
        try {
            const res = await StorageManager.apiRequest(`/api/users?${params.toString()}`);
            if (res.ok && res.data) {
                const dataObj = res.data.data;
                return dataObj?.users?.data || dataObj?.data || [];
            }
        } catch (err) {
            console.error('Autocomplete fetch error:', err);
        }
        return [];
    },

    highlightMatch(text, query) {
        if (!text || !query) return text || '';
        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');
        return String(text).replace(regex, '<span class="match-highlight">$1</span>');
    },

    renderAutocompletePopup(containerId, users, query, type) {
        const dropdown = document.getElementById(containerId);
        if (!dropdown) return;

        if (!users || users.length === 0) {
            dropdown.innerHTML = `
                <div class="bsis-autocomplete-header">
                    <span><i class="bi bi-search me-1"></i> Suggestions</span>
                </div>
                <div class="p-2 text-center text-muted small"><i class="bi bi-info-circle me-1"></i> No matching records found for "${query}"</div>
            `;
            dropdown.style.display = 'block';
            return;
        }

        let html = `
            <div class="bsis-autocomplete-header">
                <span><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Suggestions (${users.length})</span>
                <span class="text-muted" style="font-size: 0.65rem;">Click to select</span>
            </div>
        `;

        html += users.map(u => {
            const safeStudentNumber = (u.student_number || '').replace(/'/g, "\\'");
            const safeFullName = (u.full_name || '').replace(/'/g, "\\'");
            const isStudent = u.role === 'student';
            const icon = u.role === 'admin' 
                ? '<i class="bi bi-shield-lock-fill text-danger me-1"></i>' 
                : (u.role === 'event_staff' ? '<i class="bi bi-person-badge-fill text-info me-1"></i>' : '<i class="bi bi-mortarboard-fill text-success me-1"></i>');
            const roleBadge = u.role === 'admin'
                ? '<span class="badge bg-danger" style="font-size: 0.65rem;">ADMIN</span>'
                : (u.role === 'event_staff' ? '<span class="badge bg-info text-dark" style="font-size: 0.65rem;">STAFF</span>' : '<span class="badge bg-success" style="font-size: 0.65rem;">STUDENT</span>');
            const classInfo = isStudent && (u.year_level || u.section_block)
                ? `<span class="badge bg-light text-dark border me-1" style="font-size: 0.68rem;">${[u.year_level, u.section_block].filter(Boolean).join(' - ')}</span>`
                : '';

            const trimmedQuery = query.trim();
            const isMiddleMatched = u.middle_name && trimmedQuery && u.middle_name.toLowerCase().includes(trimmedQuery.toLowerCase());
            const highlightedMiddle = isMiddleMatched ? this.highlightMatch(u.middle_name, query) : '';
            const middleBadge = isMiddleMatched 
                ? `<span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 0.68rem; font-weight: 500;"><i class="bi bi-tag-fill me-1"></i>Middle: ${highlightedMiddle}</span>` 
                : '';

            const highlightedId = this.highlightMatch(u.student_number || 'Staff ID', query);
            const highlightedName = this.highlightMatch(u.full_name || '', query);
            const highlightedEmail = u.email ? this.highlightMatch(u.email, query) : '';

            return `
                <div class="bsis-autocomplete-item" onclick="AdminApp.selectAutocompleteItem('${type}', '${safeStudentNumber}', '${safeFullName}')">
                    <div class="d-flex align-items-center gap-2">
                        ${icon}
                        <div>
                            <div class="fw-bold text-dark small mb-0 d-flex align-items-center flex-wrap gap-1">
                                <span>${highlightedName}</span>
                                ${middleBadge}
                            </div>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                <span class="text-primary font-monospace fw-semibold">${highlightedId}</span>
                                ${u.email ? `<span class="ms-1 d-none d-sm-inline opacity-75">(${highlightedEmail})</span>` : ''}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        ${classInfo}
                        ${roleBadge}
                    </div>
                </div>
            `;
        }).join('');

        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
    },

    selectAutocompleteItem(type, studentNumber, fullName) {
        const val = studentNumber && studentNumber !== 'N/A' && studentNumber !== 'Staff ID' ? studentNumber : fullName;
        if (type === 'user') {
            const input = document.getElementById('user-search-input');
            if (input) input.value = val;
            const clearBtn = document.getElementById('user-search-clear');
            if (clearBtn) clearBtn.style.display = 'block';
            const dropdown = document.getElementById('user-search-autocomplete');
            if (dropdown) dropdown.style.display = 'none';
            this.usersCurrentPage = 1;
            this.loadUsers();
        } else if (type === 'fine') {
            const input = document.getElementById('fine-search-input');
            if (input) input.value = val;
            const clearBtn = document.getElementById('fine-search-clear');
            if (clearBtn) clearBtn.style.display = 'block';
            const dropdown = document.getElementById('fine-search-autocomplete');
            if (dropdown) dropdown.style.display = 'none';
            this.loadFines();
        } else if (type === 'report') {
            const input = document.getElementById('report-search-input');
            if (input) input.value = val;
            const clearBtn = document.getElementById('report-search-clear');
            if (clearBtn) clearBtn.style.display = 'block';
            const dropdown = document.getElementById('report-search-autocomplete');
            if (dropdown) dropdown.style.display = 'none';
            this.loadReports();
        }
    },

    clearSearchInput(type) {
        if (type === 'user') {
            const input = document.getElementById('user-search-input');
            if (input) input.value = '';
            const clearBtn = document.getElementById('user-search-clear');
            if (clearBtn) clearBtn.style.display = 'none';
            const dropdown = document.getElementById('user-search-autocomplete');
            if (dropdown) dropdown.style.display = 'none';
            this.usersCurrentPage = 1;
            this.loadUsers();
        } else if (type === 'fine') {
            const input = document.getElementById('fine-search-input');
            if (input) input.value = '';
            const clearBtn = document.getElementById('fine-search-clear');
            if (clearBtn) clearBtn.style.display = 'none';
            const dropdown = document.getElementById('fine-search-autocomplete');
            if (dropdown) dropdown.style.display = 'none';
            this.loadFines();
        } else if (type === 'report') {
            const input = document.getElementById('report-search-input');
            if (input) input.value = '';
            const clearBtn = document.getElementById('report-search-clear');
            if (clearBtn) clearBtn.style.display = 'none';
            const dropdown = document.getElementById('report-search-autocomplete');
            if (dropdown) dropdown.style.display = 'none';
            this.loadReports();
        } else if (type === 'device-reset') {
            const input = document.getElementById('device-reset-search-input');
            if (input) input.value = '';
            const clearBtn = document.getElementById('device-reset-search-clear');
            if (clearBtn) clearBtn.style.display = 'none';
            this.deviceResetsCurrentPage = 1;
            this.loadDeviceResets(1);
        } else if (type === 'audit-log') {
            const input = document.getElementById('audit-log-search-input');
            if (input) input.value = '';
            const clearBtn = document.getElementById('audit-log-search-clear');
            if (clearBtn) clearBtn.style.display = 'none';
            this.auditLogsCurrentPage = 1;
            this.loadAuditLogs(1);
        }
    },

    handleUserSearchDebounced() {
        const input = document.getElementById('user-search-input');
        const query = input ? input.value : '';
        const clearBtn = document.getElementById('user-search-clear');
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

        clearTimeout(this.userSearchDebounceTimer);
        this.userSearchDebounceTimer = setTimeout(async () => {
            const dropdown = document.getElementById('user-search-autocomplete');
            if (query.trim().length >= 1) {
                const role = document.getElementById('user-role-filter')?.value || '';
                const suggestions = await this.fetchAutocompleteSuggestions(query, role);
                this.renderAutocompletePopup('user-search-autocomplete', suggestions, query.trim(), 'user');
            } else if (dropdown) {
                dropdown.style.display = 'none';
            }

            this.usersCurrentPage = 1;
            this.loadUsers();
        }, 220);
    },

    handleFineSearchDebounced() {
        const input = document.getElementById('fine-search-input');
        const query = input ? input.value : '';
        const clearBtn = document.getElementById('fine-search-clear');
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

        clearTimeout(this.fineSearchDebounceTimer);
        this.fineSearchDebounceTimer = setTimeout(async () => {
            const dropdown = document.getElementById('fine-search-autocomplete');
            if (query.trim().length >= 1) {
                const suggestions = await this.fetchAutocompleteSuggestions(query, 'student');
                this.renderAutocompletePopup('fine-search-autocomplete', suggestions, query.trim(), 'fine');
            } else if (dropdown) {
                dropdown.style.display = 'none';
            }

            this.loadFines();
        }, 220);
    },

    handleReportSearchDebounced() {
        const input = document.getElementById('report-search-input');
        const query = input ? input.value : '';
        const clearBtn = document.getElementById('report-search-clear');
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

        clearTimeout(this.reportSearchDebounceTimer);
        this.reportSearchDebounceTimer = setTimeout(async () => {
            const dropdown = document.getElementById('report-search-autocomplete');
            if (query.trim().length >= 1) {
                const suggestions = await this.fetchAutocompleteSuggestions(query, 'student');
                this.renderAutocompletePopup('report-search-autocomplete', suggestions, query.trim(), 'report');
            } else if (dropdown) {
                dropdown.style.display = 'none';
            }

            this.loadReports();
        }, 220);
    },

    goToUsersPage(page) {
        if (page < 1) return;
        this.usersCurrentPage = page;
        this.loadUsers();
    },

    changeUsersPerPage(val) {
        this.usersPerPage = val;
        this.usersCurrentPage = 1;
        this.loadUsers();
    },

    renderUsersPagination(paginator) {
        const infoEl = document.getElementById('users-page-info');
        const navEl = document.getElementById('users-pagination-nav');
        if (!paginator) return;

        const total = paginator.total || 0;
        const from = paginator.from || 0;
        const to = paginator.to || 0;
        const current = paginator.current_page || 1;
        const last = paginator.last_page || 1;

        if (infoEl) {
            infoEl.innerText = total > 0 ? `Showing ${from} to ${to} of ${total} accounts` : 'Showing 0 of 0 accounts';
        }

        if (!navEl) return;

        let html = '';

        // Previous button
        html += `
            <li class="page-item ${current <= 1 ? 'disabled' : ''}">
                <button class="page-link py-1 px-2" onclick="AdminApp.goToUsersPage(${current - 1})" ${current <= 1 ? 'disabled' : ''} title="Previous Page">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>
        `;

        if (last <= 1) {
            html += `
                <li class="page-item active">
                    <button class="page-link py-1 px-2 fw-semibold">1</button>
                </li>
                <li class="page-item disabled">
                    <button class="page-link py-1 px-2" disabled title="Next Page">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </li>
            `;
            navEl.innerHTML = html;
            return;
        }

        // Page numbers
        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(last, current + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><button class="page-link py-1 px-2" onclick="AdminApp.goToUsersPage(1)">1</button></li>`;
            if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link py-1 px-2">...</span></li>`;
        }

        for (let p = startPage; p <= endPage; p++) {
            html += `
                <li class="page-item ${p === current ? 'active' : ''}">
                    <button class="page-link py-1 px-2 fw-semibold" onclick="AdminApp.goToUsersPage(${p})">${p}</button>
                </li>
            `;
        }

        if (endPage < last) {
            if (endPage < last - 1) html += `<li class="page-item disabled"><span class="page-link py-1 px-2">...</span></li>`;
            html += `<li class="page-item"><button class="page-link py-1 px-2" onclick="AdminApp.goToUsersPage(${last})">${last}</button></li>`;
        }

        // Next button
        html += `
            <li class="page-item ${current >= last ? 'disabled' : ''}">
                <button class="page-link py-1 px-2" onclick="AdminApp.goToUsersPage(${current + 1})" ${current >= last ? 'disabled' : ''} title="Next Page">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        `;

        navEl.innerHTML = html;
    },

    async loadUsers() {
        const search = document.getElementById('user-search-input')?.value || '';
        const searchField = document.getElementById('user-search-field')?.value || 'all';
        const role = document.getElementById('user-role-filter')?.value || '';
        const yearLevel = document.getElementById('user-year-filter')?.value || '';
        const sectionBlock = document.getElementById('user-block-filter')?.value || '';
        const sortBy = document.getElementById('user-sort-by')?.value || 'created_at';
        const sortOrder = document.getElementById('user-sort-order')?.value || 'desc';

        const queryParams = new URLSearchParams({
            search,
            search_field: searchField,
            role,
            year_level: yearLevel,
            section_block: sectionBlock,
            sort_by: sortBy,
            sort_order: sortOrder,
            page: this.usersCurrentPage,
            per_page: this.usersPerPage
        });

        const table = document.getElementById('users-table-body');
        if (table) table.innerHTML = this.renderTableSkeleton(9, 5);

        const res = await StorageManager.apiRequest(`/api/users?${queryParams.toString()}`);
        const currentUser = StorageManager.getUser();

        // Reset master checkbox and batch toolbar
        const masterCb = document.getElementById('user-select-all');
        if (masterCb) masterCb.checked = false;
        this.updateUsersBatchToolbar();

        if (res.ok && res.data) {
            const dataObj = res.data.data;
            const users = dataObj?.users?.data || dataObj?.data || [];
            const paginator = dataObj?.users || dataObj;
            const counts = dataObj?.counts || null;

            // Render Pagination Bar
            this.renderUsersPagination(paginator);

            // Update Role Tab Count Badges in Real-Time
            if (counts) {
                const elAll = document.getElementById('user-count-all');
                const elStudents = document.getElementById('user-count-students');
                const elStaff = document.getElementById('user-count-staff');
                const elAdmins = document.getElementById('user-count-admins');

                if (elAll) elAll.innerText = counts.all || 0;
                if (elStudents) elStudents.innerText = counts.students || 0;
                if (elStaff) elStaff.innerText = counts.event_staff || 0;
                if (elAdmins) elAdmins.innerText = counts.admins || 0;
            }

            if (users.length > 0) {
                table.innerHTML = users.map(u => {
                    const isCurrent = currentUser && (currentUser.id === u.id || currentUser.email === u.email);
                    const safeName = (u.full_name || '').replace(/'/g, "\\'");
                    const isStudent = u.role === 'student';
                    const roleBadgeClass = u.role === 'admin' 
                        ? 'bsis-badge-danger' 
                        : (u.role === 'event_staff' ? 'bsis-badge-info' : 'bsis-badge-success');
                    const roleLabel = u.role === 'admin' 
                        ? '<i class="bi bi-shield-lock-fill me-1"></i> ADMIN' 
                        : (u.role === 'event_staff' ? '<i class="bi bi-person-badge-fill me-1"></i> EVENT STAFF' : '<i class="bi bi-mortarboard-fill me-1"></i> STUDENT');

                    return `
                    <tr>
                        <td class="text-center sticky-col-1">
                            <input type="checkbox" class="user-row-checkbox form-check-input" value="${u.id}" ${isCurrent ? 'disabled title="Cannot select current account"' : 'onchange="AdminApp.updateUsersBatchToolbar()"'} />
                        </td>
                        <td class="text-nowrap fw-bold sticky-col-2 ${isStudent ? 'text-primary' : 'text-muted'}">${u.student_number || (isStudent ? 'N/A' : 'Staff ID')}</td>
                        <td class="text-nowrap sticky-col-3">
                            <span class="fw-bold">${u.full_name || 'N/A'}</span>
                            ${isCurrent ? ' <span class="badge bg-secondary ms-1" style="font-size: 0.68rem;">YOU</span>' : ''}
                        </td>
                        <td class="text-nowrap">${u.email}</td>
                        <td class="text-center text-nowrap">${isStudent ? `<span class="bsis-badge bsis-badge-info">${u.year_level || 'N/A'}</span>` : '<span class="text-muted small">—</span>'}</td>
                        <td class="text-center text-nowrap">${isStudent ? `<span class="bsis-badge bsis-badge-warning">${u.section_block || 'N/A'}</span>` : '<span class="text-muted small">—</span>'}</td>
                        <td class="text-center text-nowrap"><span class="bsis-badge ${roleBadgeClass}">${roleLabel}</span></td>
                        <td class="text-center text-nowrap"><span class="bsis-badge ${u.status === 'active' ? 'bsis-badge-success' : 'bsis-badge-warning'}">${u.status ? u.status.toUpperCase() : 'ACTIVE'}</span></td>
                        <td class="text-center text-nowrap">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle py-1 px-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" style="border-radius: 8px; font-size: 0.82rem;">
                                    <i class="bi bi-gear-fill"></i> <span>Actions</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg bsis-action-dropdown">
                                    <li>
                                        <a class="dropdown-item py-2" href="javascript:void(0)" onclick="AdminApp.editUser(${u.id})">
                                            <i class="bi bi-pencil-square text-primary me-2"></i> Edit Account
                                        </a>
                                    </li>
                                    ${isStudent ? `
                                    <li>
                                        <a class="dropdown-item py-2 text-warning fw-semibold" href="javascript:void(0)" onclick="AdminApp.resetUserDevice(${u.id})">
                                            <i class="bi bi-arrow-repeat text-warning me-2"></i> Reset Bound Phone
                                        </a>
                                    </li>` : ''}
                                    ${isCurrent ? '' : `
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="AdminApp.promptDeleteUser(${u.id}, '${safeName}')">
                                            <i class="bi bi-trash text-danger me-2"></i> Drop User Account
                                        </a>
                                    </li>
                                    `}
                                </ul>
                            </div>
                        </td>
                    </tr>
                `;}).join('');
            } else {
                table.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="bsis-empty-state">
                                <div class="bsis-empty-icon">
                                    <i class="bi bi-person-x"></i>
                                </div>
                                <div class="bsis-empty-title">No User Accounts Found</div>
                                <p class="bsis-empty-subtitle">No accounts match your current search query, role, or year/block filter.</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        } else {
            table.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Failed to load user accounts list.</td></tr>';
        }
    },

    toggleAllUserCheckboxes(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.user-row-checkbox:not(:disabled)');
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
        this.updateUsersBatchToolbar();
    },

    updateUsersBatchToolbar() {
        const selected = document.querySelectorAll('.user-row-checkbox:checked');
        const toolbar = document.getElementById('users-batch-toolbar');
        const countSpan = document.getElementById('users-selected-count');
        if (!toolbar || !countSpan) return;

        if (selected.length > 0) {
            countSpan.innerText = selected.length;
            toolbar.style.setProperty('display', 'flex', 'important');
        } else {
            toolbar.style.setProperty('display', 'none', 'important');
        }
    },

    async editUser(userId) {
        const res = await StorageManager.apiRequest(`/api/users/${userId}`);
        if (!res.ok) {
            alert('Failed to load user details.');
            return;
        }
        const u = res.data.data.user;

        document.getElementById('edit-user-id').value = u.id;
        document.getElementById('edit-user-snumber').value = u.student_number || '';
        document.getElementById('edit-user-fname').value = u.first_name || '';
        document.getElementById('edit-user-mname').value = u.middle_name || '';
        document.getElementById('edit-user-lname').value = u.last_name || '';
        document.getElementById('edit-user-email').value = u.email || '';
        document.getElementById('edit-user-year').value = u.year_level || '';
        document.getElementById('edit-user-block').value = u.section_block || '';
        document.getElementById('edit-user-role').value = u.role || 'student';
        document.getElementById('edit-user-status').value = u.status || 'active';

        const modal = new bootstrap.Modal(document.getElementById('modal-edit-user'));
        modal.show();
    },

    async handleUpdateUser(event) {
        event.preventDefault();
        const userId = document.getElementById('edit-user-id').value;
        const data = {
            student_number: document.getElementById('edit-user-snumber').value || null,
            first_name: document.getElementById('edit-user-fname').value,
            middle_name: document.getElementById('edit-user-mname').value || null,
            last_name: document.getElementById('edit-user-lname').value,
            email: document.getElementById('edit-user-email').value,
            year_level: document.getElementById('edit-user-year').value || null,
            section_block: document.getElementById('edit-user-block').value || null,
            role: document.getElementById('edit-user-role').value,
            status: document.getElementById('edit-user-status').value
        };

        const res = await StorageManager.apiRequest(`/api/users/${userId}`, {
            method: 'PUT',
            body: JSON.stringify(data)
        });

        if (res.ok && res.data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modal-edit-user')).hide();
            this.showToast('User profile updated successfully!');
            this.loadUsers();
        } else {
            alert(res.data?.message || 'Failed to update user profile.');
        }
    },

    promptDeleteUser(userId, userName) {
        document.getElementById('delete-target-mode').value = 'single';
        document.getElementById('delete-target-id').value = userId;
        document.getElementById('delete-user-prompt-title').innerText = `Drop User: ${userName}`;
        document.getElementById('delete-user-prompt-desc').innerText = `Are you sure you want to permanently delete user account "${userName}"? This cannot be undone.`;
        document.getElementById('delete-admin-password').value = '';

        const modal = new bootstrap.Modal(document.getElementById('modal-confirm-delete-user'));
        modal.show();
    },

    promptBatchDeleteUsers() {
        const selected = Array.from(document.querySelectorAll('.user-row-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            alert('Please select at least one user to delete.');
            return;
        }

        document.getElementById('delete-target-mode').value = 'batch';
        document.getElementById('delete-target-id').value = selected.join(',');
        document.getElementById('delete-user-prompt-title').innerText = `Batch Drop: ${selected.length} User(s)`;
        document.getElementById('delete-user-prompt-desc').innerText = `Are you sure you want to permanently delete all ${selected.length} selected user accounts? This action cannot be undone.`;
        document.getElementById('delete-admin-password').value = '';

        const modal = new bootstrap.Modal(document.getElementById('modal-confirm-delete-user'));
        modal.show();
    },

    async confirmExecuteDelete(event) {
        event.preventDefault();
        const mode = document.getElementById('delete-target-mode').value;
        const targetId = document.getElementById('delete-target-id').value;
        const password = document.getElementById('delete-admin-password').value;
        const btn = document.getElementById('btn-execute-delete');

        if (!password) {
            alert('Please enter your Administrator Password.');
            return;
        }

        const confirmMsg = mode === 'single'
            ? '⚠️ FINAL VERIFICATION:\n\nAre you ABSOLUTELY sure you want to permanently delete this user account?\n\nAll associated attendance records, bound devices, and onboarding credentials will be permanently erased.\n\nClick "OK" to proceed with deletion or "Cancel" to abort.'
            : `⚠️ FINAL VERIFICATION:\n\nAre you ABSOLUTELY sure you want to permanently delete all ${targetId.split(',').length} selected user account(s)?\n\nAll associated attendance records, bound devices, and onboarding credentials will be permanently erased.\n\nClick "OK" to proceed with deletion or "Cancel" to abort.`;

        if (!confirm(confirmMsg)) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

        let res;
        if (mode === 'single') {
            res = await StorageManager.apiRequest(`/api/users/${targetId}`, {
                method: 'DELETE',
                body: JSON.stringify({ password })
            });
        } else {
            const userIds = targetId.split(',').map(id => parseInt(id.trim()));
            res = await StorageManager.apiRequest('/api/users/batch-delete', {
                method: 'POST',
                body: JSON.stringify({ password, user_ids: userIds })
            });
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash-fill"></i> Confirm Deletion';

        if (res.ok && res.data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modal-confirm-delete-user')).hide();
            this.showToast(res.data?.message || 'User account(s) deleted successfully!');
            this.loadUsers();
        } else {
            alert(res.data?.message || 'Deletion failed. Please verify your password.');
        }
    },

    async handleCreateStudent(event) {
        event.preventDefault();
        const role = document.getElementById('student-role')?.value || 'student';
        const studentNumber = document.getElementById('student-number')?.value?.trim();
        const firstName = document.getElementById('student-fname')?.value?.trim();
        const middleName = document.getElementById('student-mname')?.value?.trim();
        const lastName = document.getElementById('student-lname')?.value?.trim();
        const email = document.getElementById('student-email')?.value?.trim();
        const yearLevel = document.getElementById('student-year-level')?.value;
        const sectionBlock = document.getElementById('student-section-block')?.value?.trim();

        // Run inline form validations
        const v1 = this.validateField('student-number', !!studentNumber, 'Student ID number is required.');
        const v2 = this.validateField('student-fname', !!firstName, 'First name is required.');
        const v3 = this.validateField('student-lname', !!lastName, 'Last name is required.');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const v4 = this.validateField('student-email', !!email && emailRegex.test(email), 'Please enter a valid email address.');

        if (!v1 || !v2 || !v3 || !v4) {
            this.showToast('Please check the highlighted required account fields.', 'warning');
            return;
        }

        const roleLabel = role === 'event_staff' ? 'Event Staff (Student Officer)' : (role === 'admin' ? 'Administrator' : 'Student');
        const fullName = `${firstName} ${middleName ? middleName + ' ' : ''}${lastName}`.trim();

        this.showConfirm({
            title: 'Confirm Account Registration',
            message: `Register <strong>${fullName}</strong> (${studentNumber}) as <strong>${roleLabel}</strong>?<br><small class="text-muted">An automated onboarding email will be sent to <em>${email}</em>.</small>`,
            icon: 'bi-person-plus-fill',
            type: 'info',
            confirmText: 'Register Account',
            confirmClass: 'btn-primary',
            onConfirm: async () => {
                const data = {
                    role: role,
                    student_number: studentNumber,
                    first_name: firstName,
                    middle_name: middleName || null,
                    last_name: lastName,
                    email: email,
                    year_level: yearLevel,
                    section_block: sectionBlock
                };

                const res = await StorageManager.apiRequest('/api/students', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });

                if (res.ok && res.data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modal-create-student')).hide();
                    this.showToast(`${roleLabel} registered and onboarding email sent!`, 'success');
                    this.loadUsers();
                    
                    // Clear input fields
                    document.getElementById('student-number').value = '';
                    document.getElementById('student-fname').value = '';
                    document.getElementById('student-mname').value = '';
                    document.getElementById('student-lname').value = '';
                    document.getElementById('student-email').value = '';
                    document.getElementById('student-section-block').value = 'Block 1';
                    if (document.getElementById('student-role')) document.getElementById('student-role').value = 'student';
                    this.clearFieldValidation('student-number');
                    this.clearFieldValidation('student-fname');
                    this.clearFieldValidation('student-lname');
                    this.clearFieldValidation('student-email');
                } else {
                    let errMsg = res.data?.message || 'Failed to create account.';
                    const errors = res.data?.errors || {};
                    
                    if (errors.email) {
                        const emailMsg = Array.isArray(errors.email) ? errors.email[0] : errors.email;
                        this.validateField('student-email', false, emailMsg);
                        errMsg = emailMsg;
                        document.getElementById('student-email')?.focus();
                    }
                    if (errors.student_number) {
                        const idMsg = Array.isArray(errors.student_number) ? errors.student_number[0] : errors.student_number;
                        this.validateField('student-number', false, idMsg);
                        if (!errors.email) {
                            errMsg = idMsg;
                            document.getElementById('student-number')?.focus();
                        }
                    }

                    this.showToast(errMsg, 'danger', 6000);
                }
            }
        });
    },

    downloadCsvTemplate() {
        const headers = ['student_number', 'first_name', 'middle_name', 'last_name', 'email', 'year_level', 'block'];
        const sampleRows = [
            ['2024-00101', 'Juan', 'Dela', 'Cruz', 'juan.cruz@tpc.edu.ph', '1st Year', 'Block 1'],
            ['2024-00102', 'Maria', 'Santos', 'Clara', 'maria.clara@tpc.edu.ph', '1st Year', 'Block 1'],
            ['2023-00045', 'Jose', 'Protacio', 'Rizal', 'jose.rizal@tpc.edu.ph', '2nd Year', 'Block 2'],
            ['2022-00088', 'Andres', 'Castro', 'Bonifacio', 'andres.bonifacio@tpc.edu.ph', '3rd Year', 'Block 1'],
            ['2021-00012', 'Gabriela', 'Silang', 'Cariño', 'gabriela.silang@tpc.edu.ph', '4th Year', 'Block 3']
        ];

        const csvContent = [headers.join(','), ...sampleRows.map(r => r.join(','))].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'tpc_bsis_student_import_template.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    },

    async handleCsvImport(e) {
        e.preventDefault();
        const fileInput = document.getElementById('csv-file-input');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            alert('Please select a CSV file to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('csv_file', fileInput.files[0]);

        const token = StorageManager.getToken();
        const response = await fetch('/api/students/import', {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: formData
        });

        const res = await response.json();
        if (response.ok && res.success) {
            bootstrap.Modal.getInstance(document.getElementById('modal-csv-import')).hide();
            fileInput.value = '';
            alert(`✅ CSV Import Successful!\n\n• Total Records Processed: ${res.data.total_rows}\n• Successfully Created: ${res.data.created_count}\n• Failed / Duplicates: ${res.data.failed_count}`);
            this.loadUsers();
        } else {
            alert(res.message || 'CSV Import failed. Please verify your column headers and data formats.');
        }
    },

    async resetUserDevice(userId) {
        this.showConfirm({
            title: 'Reset Bound Device',
            message: 'Reset registered mobile device credential for this student? They will be able to bind a new device on their next login.',
            icon: 'bi-phone-flip',
            type: 'warning',
            confirmText: 'Reset Device',
            confirmClass: 'btn-warning',
            onConfirm: async () => {
                const res = await StorageManager.apiRequest(`/api/users/${userId}/reset-device`, { method: 'POST' });
                if (res.ok) {
                    this.showToast('Student mobile device credential reset successfully!', 'success');
                    this.loadUsers();
                } else {
                    this.showToast(res.data?.message || 'Failed to reset device.', 'danger');
                }
            }
        });
    },

    // 6. DEVICE RESET AUDIT LOGS & HISTORY
    handleDeviceResetSearchDebounced() {
        const input = document.getElementById('device-reset-search-input');
        const query = input ? input.value : '';
        const clearBtn = document.getElementById('device-reset-search-clear');
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

        clearTimeout(this.deviceResetSearchDebounceTimer);
        this.deviceResetSearchDebounceTimer = setTimeout(() => {
            this.deviceResetsCurrentPage = 1;
            this.loadDeviceResets(1);
        }, 250);
    },

    goToDeviceResetsPage(page) {
        this.deviceResetsCurrentPage = page;
        this.loadDeviceResets(page);
    },

    renderDeviceResetsPagination(paginator) {
        const infoEl = document.getElementById('device-resets-page-info');
        const navEl = document.getElementById('device-resets-pagination-nav');
        if (!paginator) return;

        const total = paginator.total || 0;
        const from = paginator.from || 0;
        const to = paginator.to || 0;
        const current = paginator.current_page || 1;
        const last = paginator.last_page || 1;

        if (infoEl) {
            infoEl.innerText = total > 0 ? `Showing ${from} to ${to} of ${total} reset logs` : 'Showing 0 of 0 logs';
        }

        if (!navEl) return;
        if (last <= 1) {
            navEl.innerHTML = '';
            return;
        }

        let html = '';
        html += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <button class="page-link py-1 px-2" onclick="AdminApp.goToDeviceResetsPage(${current - 1})" ${current === 1 ? 'disabled' : ''} title="Previous Page">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>
        `;

        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(last, current + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><button class="page-link py-1 px-2" onclick="AdminApp.goToDeviceResetsPage(1)">1</button></li>`;
            if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link py-1 px-2">...</span></li>`;
        }

        for (let p = startPage; p <= endPage; p++) {
            html += `
                <li class="page-item ${p === current ? 'active' : ''}">
                    <button class="page-link py-1 px-2 fw-semibold" onclick="AdminApp.goToDeviceResetsPage(${p})">${p}</button>
                </li>
            `;
        }

        if (endPage < last) {
            if (endPage < last - 1) html += `<li class="page-item disabled"><span class="page-link py-1 px-2">...</span></li>`;
            html += `<li class="page-item"><button class="page-link py-1 px-2" onclick="AdminApp.goToDeviceResetsPage(${last})">${last}</button></li>`;
        }

        html += `
            <li class="page-item ${current === last ? 'disabled' : ''}">
                <button class="page-link py-1 px-2" onclick="AdminApp.goToDeviceResetsPage(${current + 1})" ${current === last ? 'disabled' : ''} title="Next Page">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        `;
        navEl.innerHTML = html;
    },

    async loadDeviceResets(page = 1) {
        this.deviceResetsCurrentPage = page;
        const table = document.getElementById('device-resets-table-body');
        if (table) table.innerHTML = this.renderTableSkeleton(6, 4);

        const search = document.getElementById('device-reset-search-input')?.value || '';
        const action = document.getElementById('device-reset-action-filter')?.value || '';

        const params = new URLSearchParams();
        params.append('page', page);
        params.append('per_page', 10);
        if (search.trim()) params.append('search', search.trim());
        if (action.trim()) params.append('action', action.trim());

        const res = await StorageManager.apiRequest(`/api/device-resets?${params.toString()}`);
        if (!table) return;

        if (res.ok && res.data.data && res.data.data.data.length > 0) {
            const paginator = res.data.data;
            const records = paginator.data;
            const from = paginator.from || 1;

            table.innerHTML = records.map((r, idx) => {
                const num = from + idx;
                const dateObj = new Date(r.created_at);
                const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const formattedTime = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });

                // Student Details & Metadata
                let studentDetails = '';
                if (r.metadata && (r.metadata.student_name || r.metadata.student_number)) {
                    studentDetails = `
                        <div class="fw-bold text-primary" style="font-size: 0.90rem;">${r.metadata.student_name || 'Student'}</div>
                        <div class="text-muted small" style="font-size: 0.78rem;">
                            <span class="badge bg-light text-dark border me-1">${r.metadata.student_number || 'N/A'}</span>
                            ${r.metadata.year_level || ''} ${r.metadata.section_block ? '&bull; ' + r.metadata.section_block : ''}
                        </div>
                    `;
                } else if (r.description) {
                    studentDetails = `<span class="fw-semibold text-dark" style="font-size: 0.86rem;">${r.description}</span>`;
                } else {
                    studentDetails = `<span class="text-muted small">Student Record</span>`;
                }

                // Action Badge
                let actionBadge = '';
                if (r.action === 'direct_device_reset') {
                    actionBadge = `<span class="bsis-badge bsis-badge-warning" style="font-size: 0.75rem;"><i class="bi bi-arrow-repeat me-1"></i> DIRECT ADMIN RESET</span>`;
                } else if (r.action === 'device_reset_approved') {
                    actionBadge = `<span class="bsis-badge bsis-badge-success" style="font-size: 0.75rem;"><i class="bi bi-check-circle-fill me-1"></i> RESET APPROVED</span>`;
                } else if (r.action === 'device_reset_rejected') {
                    actionBadge = `<span class="bsis-badge bsis-badge-danger" style="font-size: 0.75rem;"><i class="bi bi-x-circle-fill me-1"></i> RESET REJECTED</span>`;
                } else {
                    actionBadge = `<span class="bsis-badge bsis-badge-info" style="font-size: 0.75rem;"><i class="bi bi-clock-history me-1"></i> RESET REQUESTED</span>`;
                }

                // Authorized Admin
                const adminName = r.user ? `${r.user.full_name} (${r.user.role.toUpperCase()})` : (r.metadata?.admin_name || 'System Administrator');

                // IP & User Agent
                const ip = r.ip_address || '127.0.0.1';
                const ua = r.user_agent ? (r.user_agent.length > 30 ? r.user_agent.substring(0, 30) + '...' : r.user_agent) : 'Web Portal';

                return `
                    <tr>
                        <td class="text-muted fw-bold small" style="width: 35px;">#${num}</td>
                        <td style="min-width: 200px;">${studentDetails}</td>
                        <td>${actionBadge}</td>
                        <td><span class="fw-semibold text-dark" style="font-size: 0.84rem;"><i class="bi bi-person-badge-fill me-1 text-primary"></i> ${adminName}</span></td>
                        <td>
                            <span class="badge bg-light text-secondary font-monospace border" style="font-size: 0.75rem;">${ip}</span>
                            <div class="text-muted" style="font-size: 0.72rem;">${ua}</div>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="fw-bold text-dark" style="font-size: 0.84rem;">${formattedDate}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">${formattedTime}</div>
                        </td>
                    </tr>
                `;
            }).join('');

            this.renderDeviceResetsPagination(paginator);
        } else {
            table.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="bsis-empty-state">
                            <div class="bsis-empty-icon success">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <div class="bsis-empty-title">No Device Reset Logs Found</div>
                            <p class="bsis-empty-subtitle">All student hardware unbindings and device authorizations performed by administrators will appear here.</p>
                        </div>
                    </td>
                </tr>
            `;
            const infoEl = document.getElementById('device-resets-page-info');
            if (infoEl) infoEl.innerText = 'Showing 0 of 0 logs';
            const navEl = document.getElementById('device-resets-pagination-nav');
            if (navEl) navEl.innerHTML = '';
        }
    },

    // 7. FINES MANAGEMENT (WITH YEAR, BLOCK, TARGET SEARCH, EXPORT & PRINTING)
    async loadFines() {
        const search = document.getElementById('fine-search-input')?.value || '';
        const searchField = document.getElementById('fine-search-field')?.value || 'all';
        const yearLevel = document.getElementById('fine-year-filter')?.value || '';
        const sectionBlock = document.getElementById('fine-block-filter')?.value || '';
        const finePaid = document.getElementById('fine-status-filter')?.value || '';

        const queryParams = new URLSearchParams({
            search,
            search_field: searchField,
            year_level: yearLevel,
            section_block: sectionBlock,
            fine_paid: finePaid
        });

        const table = document.getElementById('fines-table-body');
        if (table) table.innerHTML = this.renderTableSkeleton(9, 5);

        const res = await StorageManager.apiRequest(`/api/fines?${queryParams.toString()}`);
        const selectAllCheckbox = document.getElementById('fine-select-all');
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        this.updateFinesBatchToolbar();

        if (table && res.ok && res.data.data.fines.data.length > 0) {
            table.innerHTML = res.data.data.fines.data.map(f => {
                const yrBlk = [f.user?.year_level, f.user?.section_block].filter(Boolean).join(' - ') || 'N/A';
                const violationBadge = f.status === 'absent'
                    ? '<span class="bsis-badge bsis-badge-danger"><i class="bi bi-x-circle me-1"></i> ABSENT</span>'
                    : '<span class="bsis-badge bsis-badge-warning"><i class="bi bi-clock-history me-1"></i> LATE</span>';

                const paymentBadge = `<span class="bsis-badge ${f.fine_paid ? 'bsis-badge-success' : 'bsis-badge-danger'}">${f.fine_paid ? 'PAID' : 'UNPAID'}</span>`;

                return `
                    <tr>
                        <td class="text-center">${!f.fine_paid ? `<input type="checkbox" class="fine-checkbox form-check-input" value="${f.id}" onchange="AdminApp.updateFinesBatchToolbar()">` : ''}</td>
                        <td><strong class="text-primary">${f.user?.student_number || 'N/A'}</strong></td>
                        <td><strong>${f.user?.full_name || 'N/A'}</strong></td>
                        <td>${yrBlk}</td>
                        <td>${f.event?.title || 'N/A'}</td>
                        <td>${violationBadge}</td>
                        <td>₱${parseFloat(f.fine_amount).toFixed(2)}</td>
                        <td class="text-center">${paymentBadge}</td>
                        <td class="text-center">
                            ${!f.fine_paid ? `
                            <button onclick="AdminApp.payFine(${f.id})" class="btn btn-sm btn-success py-1 px-2 me-1" title="Mark Paid">
                                <i class="bi bi-check-lg"></i> Pay
                            </button>
                            <button onclick="AdminApp.waiveFine(${f.id})" class="btn btn-sm btn-outline-info py-1 px-2" title="Waive Fine">
                                <i class="bi bi-shield-check"></i> Waive
                            </button>
                            ` : '<span class="text-muted small">Settled</span>'}
                        </td>
                    </tr>
                `;
            }).join('');
            
            document.getElementById('fine-total-sum-display').innerText = `₱${res.data.data.summary.total_fines_amount.toFixed(2)}`;
            document.getElementById('fine-unpaid-sum-display').innerText = `₱${res.data.data.summary.unpaid_fines_amount.toFixed(2)}`;
        } else if (table) {
            table.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="bsis-empty-state">
                            <div class="bsis-empty-icon warning">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div class="bsis-empty-title">No Fine Records Found</div>
                            <p class="bsis-empty-subtitle">No student non-attendance fines match your selected status, year level, or search filter.</p>
                        </div>
                    </td>
                </tr>
            `;
            document.getElementById('fine-total-sum-display').innerText = '₱0.00';
            document.getElementById('fine-unpaid-sum-display').innerText = '₱0.00';
        }
    },

    toggleAllFineCheckboxes(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.fine-checkbox');
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
        this.updateFinesBatchToolbar();
    },

    deselectAllFines() {
        const checkboxes = document.querySelectorAll('.fine-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        const master = document.getElementById('fine-select-all');
        if (master) master.checked = false;
        this.updateFinesBatchToolbar();
    },

    updateFinesBatchToolbar() {
        const checked = document.querySelectorAll('.fine-checkbox:checked');
        const toolbar = document.getElementById('fines-batch-toolbar');
        const countEl = document.getElementById('fines-selected-count');

        if (checked.length > 0) {
            toolbar.style.cssText = 'display: flex !important;';
            countEl.innerText = checked.length;
        } else {
            toolbar.style.cssText = 'display: none !important;';
            countEl.innerText = '0';
        }
    },

    async batchMarkFinesPaid() {
        const checked = document.querySelectorAll('.fine-checkbox:checked');
        if (checked.length === 0) {
            this.showToast('No fine records selected.', 'warning');
            return;
        }

        const ids = Array.from(checked).map(cb => parseInt(cb.value));
        this.showConfirm({
            title: 'Record Batch Payment',
            message: `Mark ${ids.length} selected fine record(s) as officially PAID?`,
            icon: 'bi-cash-coin',
            type: 'info',
            confirmText: 'Mark as Paid',
            confirmClass: 'btn-success',
            onConfirm: async () => {
                const res = await StorageManager.apiRequest('/api/fines/batch-pay', {
                    method: 'POST',
                    body: JSON.stringify({ attendance_ids: ids })
                });

                if (res.ok && res.data.success) {
                    this.showToast(`${res.data.data.paid_count} fine(s) marked as paid!`, 'success');
                    this.loadFines();
                } else {
                    this.showToast(res.data?.message || 'Batch payment failed.', 'danger');
                }
            }
        });
    },

    // Backward compatibility alias
    async paySelectedFines() {
        return this.batchMarkFinesPaid();
    },

    async batchWaiveFines() {
        const checked = document.querySelectorAll('.fine-checkbox:checked');
        if (checked.length === 0) {
            alert('No fine records selected.');
            return;
        }

        const ids = Array.from(checked).map(cb => parseInt(cb.value));
        const reason = prompt(`Waive ${ids.length} selected fine(s)? Enter approval justification / reason:`, 'Administrative waiver / Approved student justification');
        if (reason === null) return; // User cancelled prompt

        const res = await StorageManager.apiRequest('/api/fines/batch-waive', {
            method: 'POST',
            body: JSON.stringify({ attendance_ids: ids, reason: reason.trim() || 'Administrative waiver' })
        });

        if (res.ok && res.data.success) {
            this.showToast(`${res.data.data.waived_count} fine(s) totaling ₱${parseFloat(res.data.data.total_waived_amount || 0).toFixed(2)} successfully WAIVED!`);
            this.loadFines();
        } else {
            alert(res.data?.message || 'Batch fine waiver failed.');
        }
    },

    async waiveFine(attendanceId) {
        const reason = prompt('Waive this student fine? Enter approval justification:', 'Valid excuse / Administrative waiver');
        if (reason === null) return;

        const res = await StorageManager.apiRequest(`/api/fines/${attendanceId}/waive`, {
            method: 'POST',
            body: JSON.stringify({ reason: reason.trim() || 'Administrative waiver' })
        });

        if (res.ok && res.data.success) {
            this.showToast('Fine successfully waived!');
            this.loadFines();
        } else {
            alert(res.data?.message || 'Failed to waive fine.');
        }
    },

    exportFilteredFinesCsv() {
        const token = StorageManager.getToken();
        const search = document.getElementById('fine-search-input')?.value || '';
        const searchField = document.getElementById('fine-search-field')?.value || 'all';
        const yearLevel = document.getElementById('fine-year-filter')?.value || '';
        const sectionBlock = document.getElementById('fine-block-filter')?.value || '';
        const finePaid = document.getElementById('fine-status-filter')?.value || '';

        const params = new URLSearchParams({
            type: 'fines',
            format: 'csv',
            token,
            search,
            search_field: searchField,
            year_level: yearLevel,
            section_block: sectionBlock,
            fine_paid: finePaid
        });

        window.location.href = `/api/reports/export?${params.toString()}`;
    },

    async printFilteredFines() {
        const user = StorageManager.getUser();
        const search = document.getElementById('fine-search-input')?.value || '';
        const searchField = document.getElementById('fine-search-field')?.value || 'all';
        const yearLevel = document.getElementById('fine-year-filter')?.value || '';
        const sectionBlock = document.getElementById('fine-block-filter')?.value || '';
        const finePaid = document.getElementById('fine-status-filter')?.value || '';

        const queryParams = new URLSearchParams({
            search,
            search_field: searchField,
            year_level: yearLevel,
            section_block: sectionBlock,
            fine_paid: finePaid,
            per_page: 1000
        });

        this.showToast('Preparing official student clearance summary for printing...');
        const res = await StorageManager.apiRequest(`/api/fines?${queryParams.toString()}`);
        if (!res.ok || !res.data.success) {
            alert('Failed to retrieve fine records for printing.');
            return;
        }

        const fines = res.data.data.fines.data || [];
        const printDate = new Date().toLocaleString([], { dateStyle: 'long', timeStyle: 'short', hour12: true });
        const filterSummaryText = `Year Level: ${yearLevel || 'All'} | Block: ${sectionBlock || 'All'} | Status: ${finePaid === 'false' ? 'Unpaid Only' : (finePaid === 'true' ? 'Paid Only' : 'All')}`;

        // Group fines by student to create a clean, 1-row-per-student clearance masterlist
        const studentMap = new Map();
        fines.forEach(f => {
            const uid = f.user_id || f.user?.id || f.user?.student_number;
            if (!uid) return;

            if (!studentMap.has(uid)) {
                studentMap.set(uid, {
                    student_number: f.user?.student_number || 'N/A',
                    full_name: f.user?.full_name || 'N/A',
                    year_level: f.user?.year_level || '',
                    section_block: f.user?.section_block || '',
                    total_incurred: 0,
                    total_paid: 0,
                    balance_due: 0,
                    violation_count: 0
                });
            }

            const item = studentMap.get(uid);
            const amt = parseFloat(f.fine_amount || 0);
            item.total_incurred += amt;
            item.violation_count++;
            if (f.fine_paid === true || f.fine_paid == 1) {
                item.total_paid += amt;
            }
        });

        // Compute balances and totals
        let totalListedStudents = 0;
        let grandTotalIncurred = 0;
        let grandTotalPaid = 0;
        let grandTotalBalance = 0;

        const studentsList = Array.from(studentMap.values()).map(s => {
            s.balance_due = Math.max(0, s.total_incurred - s.total_paid);
            grandTotalIncurred += s.total_incurred;
            grandTotalPaid += s.total_paid;
            grandTotalBalance += s.balance_due;
            totalListedStudents++;
            return s;
        });

        // Sort students alphabetically by full name
        studentsList.sort((a, b) => a.full_name.localeCompare(b.full_name));

        const rowsHtml = studentsList.map((s, idx) => {
            const yrBlk = [s.year_level, s.section_block].filter(Boolean).join(' - ') || 'N/A';
            const isCleared = s.balance_due <= 0;
            const statusLabel = isCleared ? 'CLEARED' : 'UNPAID';
            const statusColor = isCleared ? '#16A34A' : '#DC2626';

            return `
                <tr style="page-break-inside: avoid;">
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; text-align: center; font-size: 0.74rem;">${idx + 1}</td>
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; font-weight: 700; white-space: nowrap; font-size: 0.74rem;">${s.student_number}</td>
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; font-weight: 600; font-size: 0.74rem;">${s.full_name}</td>
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap; font-size: 0.74rem;">${yrBlk}</td>
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; text-align: right; font-weight: 700; white-space: nowrap; font-size: 0.74rem;">₱${s.total_incurred.toFixed(2)}</td>
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; text-align: right; font-weight: 700; color: #16A34A; white-space: nowrap; font-size: 0.74rem;">₱${s.total_paid.toFixed(2)}</td>
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; text-align: right; font-weight: 700; color: ${s.balance_due > 0 ? '#DC2626' : '#16A34A'}; white-space: nowrap; font-size: 0.74rem;">₱${s.balance_due.toFixed(2)}</td>
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; text-align: center; font-weight: 800; color: ${statusColor}; white-space: nowrap; font-size: 0.74rem;">${statusLabel}</td>
                    <td style="padding: 4px 6px; border: 1px solid #CBD5E1; text-align: center; color: #94A3B8; font-size: 0.72rem; white-space: nowrap;">_________________</td>
                </tr>
            `;
        }).join('');

        const printableContainer = document.getElementById('printable-fines-area');
        printableContainer.innerHTML = `
            <div style="font-family: 'Inter', Arial, sans-serif; color: #0F172A; padding: 4px; line-height: 1.25;">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #063B5C; padding-bottom: 5px; margin-bottom: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="/images/bsis-logo.png" style="height: 44px;">
                        <div>
                            <h4 style="margin: 0; color: #063B5C; font-weight: 800; font-size: 1.05rem; letter-spacing: -0.2px;">TALIBON POLYTECHNIC COLLEGE</h4>
                            <h6 style="margin: 1px 0 0 0; color: #0284C7; font-weight: 700; font-size: 0.78rem;">Bachelor of Science in Information Systems (BSIS)</h6>
                            <span style="font-size: 0.70rem; color: #64748B;">Official Student Clearance & Fine Summary Masterlist</span>
                        </div>
                    </div>
                    <div style="text-align: right; font-size: 0.70rem; color: #64748B; line-height: 1.3;">
                        <strong>Date Generated:</strong> ${printDate}<br>
                        <strong>Issued By:</strong> ${user ? user.full_name : 'System BSIS Administrator'}
                    </div>
                </div>

                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 6px 10px; margin-bottom: 8px; font-size: 0.72rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <strong style="color: #063B5C;">Filters:</strong> ${filterSummaryText} &nbsp;|&nbsp; <strong>Total Students:</strong> ${totalListedStudents}
                    </div>
                    <div style="display: flex; gap: 14px; font-weight: 700;">
                        <span style="color: #063B5C;">Total Incurred: ₱${grandTotalIncurred.toFixed(2)}</span>
                        <span style="color: #16A34A;">Total Paid: ₱${grandTotalPaid.toFixed(2)}</span>
                        <span style="color: #DC2626;">Total Outstanding Due: ₱${grandTotalBalance.toFixed(2)}</span>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; font-size: 0.72rem; margin-bottom: 10px;">
                    <thead>
                        <tr style="background-color: #063B5C; color: #FFFFFF;">
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; width: 25px; text-align: center;">#</th>
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; text-align: left; width: 85px;">Student ID</th>
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; text-align: left;">Student Full Name</th>
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; text-align: center; width: 90px;">Year / Block</th>
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; text-align: right; width: 75px;">Total Fine</th>
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; text-align: right; width: 75px;">Paid</th>
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; text-align: right; width: 85px;">Balance Due</th>
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; text-align: center; width: 75px;">Status</th>
                            <th style="padding: 4px 6px; border: 1px solid #063B5C; text-align: center; width: 110px;">Signature / Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml || '<tr><td colspan="9" style="text-align: center; padding: 14px; color: #64748B;">No student fine records found matching the active filters.</td></tr>'}
                    </tbody>
                </table>

                <div style="margin-top: 30px; display: flex; justify-content: space-between; text-align: center; font-size: 0.74rem; color: #1E293B; page-break-inside: avoid;">
                    <div style="width: 220px;">
                        <div style="height: 45px;"></div>
                        <div style="border-bottom: 1.5px solid #0F172A; padding-bottom: 2px; margin-bottom: 2px;">
                            <strong>${user ? user.full_name : 'System BSIS Administrator'}</strong>
                        </div>
                        <span style="color: #64748B; font-size: 0.68rem; font-weight: 500;">BSIS Attendance Officer / Treasurer</span>
                    </div>
                    <div style="width: 220px;">
                        <div style="height: 45px;"></div>
                        <div style="border-bottom: 1.5px solid #0F172A; padding-bottom: 2px; margin-bottom: 2px;">
                            <strong>Program Head / Dean</strong>
                        </div>
                        <span style="color: #64748B; font-size: 0.68rem; font-weight: 500;">BSIS Department / College</span>
                    </div>
                </div>
            </div>
        `;

        window.print();
        setTimeout(() => {
            printableContainer.innerHTML = '';
            window.location.hash = '#fines';
            this.showView('view-fines');
        }, 100);
    },

    exportFilteredFinesCsv() {
        const search = document.getElementById('fine-search-input')?.value || '';
        const finePaid = document.getElementById('fine-status-filter')?.value || '';
        const yearLevel = document.getElementById('fine-year-filter')?.value || '';
        const sectionBlock = document.getElementById('fine-block-filter')?.value || '';
        const token = StorageManager.getToken();

        const params = new URLSearchParams({
            type: 'fines',
            format: 'csv',
            token,
            search,
            fine_paid: finePaid,
            year_level: yearLevel,
            section_block: sectionBlock
        });

        this.showToast('Generating official student fines summary CSV export...');
        window.location.href = `/api/reports/export?${params.toString()}`;
    },

    async payFine(attendanceId) {
        this.showConfirm({
            title: 'Record Fine Payment',
            message: 'Record fine payment for this student? This will clear the unpaid fine status.',
            icon: 'bi-cash-coin',
            type: 'info',
            confirmText: 'Confirm Payment',
            confirmClass: 'btn-success',
            onConfirm: async () => {
                const res = await StorageManager.apiRequest(`/api/fines/${attendanceId}/pay`, { method: 'POST' });
                if (res.ok && res.data?.success) {
                    this.showToast('✓ Fine marked as paid successfully!', 'success');
                    this.loadFines();
                } else {
                    this.showToast(res.data?.message || 'Failed to record payment.', 'danger');
                }
            }
        });
    },

    async recordSingleFinePayment(attendanceId) {
        return this.payFine(attendanceId);
    },

    // 8. REPORTS, WORD (.DOCX), CSV EXPORT & PRINT
    async populateReportEventsDropdown() {
        const select = document.getElementById('report-event-filter');
        if (!select || select.dataset.loaded === 'true') return;

        const res = await StorageManager.apiRequest('/api/events?per_page=100');
        if (res.ok && res.data && res.data.data && res.data.data.data) {
            const currentVal = select.value;
            select.innerHTML = '<option value="">All Events (Aggregate)</option>' +
                res.data.data.data.map(e => `<option value="${e.id}">${e.title} (${new Date(e.start_time).toLocaleDateString()})</option>`).join('');
            select.dataset.loaded = 'true';
            if (currentVal) select.value = currentVal;
        }
    },

    async loadReports() {
        await this.populateReportEventsDropdown();

        const eventId = document.getElementById('report-event-filter')?.value || '';
        const search = document.getElementById('report-search-input')?.value || '';
        const status = document.getElementById('report-status-filter')?.value || '';
        const yearLevel = document.getElementById('report-year-filter')?.value || '';
        const sectionBlock = document.getElementById('report-block-filter')?.value || '';

        const queryParams = new URLSearchParams({
            per_page: 500,
            event_id: eventId,
            search,
            status,
            year_level: yearLevel,
            section_block: sectionBlock
        });

        const table = document.getElementById('report-attendance-table-body');
        if (table) table.innerHTML = this.renderTableSkeleton(10, 5);

        const res = await StorageManager.apiRequest(`/api/reports/attendance?${queryParams.toString()}`);
        const countBadge = document.getElementById('report-records-count');

        if (res.ok && res.data && res.data.data) {
            const records = res.data.data.data || [];
            
            // Calculate statistics
            const totalCount = records.length;
            const presentCount = records.filter(r => r.status === 'present').length;
            const lateCount = records.filter(r => r.status === 'late').length;
            const totalFines = records.reduce((sum, r) => sum + (r.fine_paid ? 0 : parseFloat(r.fine_amount || 0)), 0);

            const elTotal = document.getElementById('rep-stat-total');
            if (elTotal) elTotal.innerText = totalCount;

            const elPresent = document.getElementById('rep-stat-present');
            if (elPresent) elPresent.innerText = presentCount;

            const elLate = document.getElementById('rep-stat-late');
            if (elLate) elLate.innerText = lateCount;

            const elFines = document.getElementById('rep-stat-fines');
            if (elFines) elFines.innerText = `₱${totalFines.toFixed(2)}`;

            if (countBadge) countBadge.innerText = `${totalCount} Record(s)`;

            if (records.length > 0) {
                const isAnyWholeDay = records.some(r => r.event?.session_type === 'whole_day' || r.am_time_in || r.pm_time_in);
                
                // Update table header dynamically if needed
                const thead = table.closest('table')?.querySelector('thead');
                if (thead) {
                    thead.innerHTML = isAnyWholeDay 
                        ? `
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Student Full Name</th>
                                <th>Year & Block</th>
                                <th>Event Title</th>
                                <th class="text-center">AM In</th>
                                <th class="text-center">AM Out</th>
                                <th class="text-center">PM In</th>
                                <th class="text-center">PM Out</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Distance</th>
                                <th class="text-end">Fine</th>
                            </tr>
                        `
                        : `
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Student Full Name</th>
                                <th>Year & Block</th>
                                <th>Event Title</th>
                                <th class="text-center">Time-In</th>
                                <th class="text-center">Time-Out</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Distance</th>
                                <th class="text-end">Fine</th>
                            </tr>
                        `;
                }

                table.innerHTML = records.map((r, idx) => {
                    const statusBadge = r.status === 'present' 
                        ? 'bsis-badge-success' 
                        : (r.status === 'late' ? 'bsis-badge-warning' : (r.status === 'absent' ? 'bsis-badge-danger' : 'bsis-badge-info'));
                    const statusLabel = r.status ? r.status.toUpperCase() : 'N/A';
                    const yrBlk = [r.user?.year_level, r.user?.section_block].filter(Boolean).join(' - ') || 'N/A';
                    
                    const formatTime = (t) => t ? new Date(t).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true }) : '<span class="text-muted">—</span>';
                    
                    const amIn = formatTime(r.am_time_in || r.scan_time);
                    const amOut = formatTime(r.am_time_out);
                    const pmIn = formatTime(r.pm_time_in);
                    const pmOut = formatTime(r.pm_time_out || r.checkout_time);

                    const isPaid = r.fine_paid === true || r.fine_paid == 1;
                    const isWaived = isPaid && (parseFloat(r.fine_amount || 0) <= 0 || r.verification_data?.waive_details);
                    
                    let fineBadgeHtml = '';
                    if (isWaived) {
                        fineBadgeHtml = '<span class="bsis-badge bsis-badge-info" style="font-size:0.75rem;"><i class="bi bi-shield-check me-1"></i> WAIVED</span>';
                    } else if (isPaid) {
                        fineBadgeHtml = '<span class="bsis-badge bsis-badge-success" style="font-size:0.75rem;"><i class="bi bi-check2-circle me-1"></i> PAID</span>';
                    } else if (parseFloat(r.fine_amount || 0) > 0) {
                        fineBadgeHtml = `<strong class="text-danger">₱${parseFloat(r.fine_amount).toFixed(2)}</strong>`;
                    } else {
                        fineBadgeHtml = '<span class="text-muted">—</span>';
                    }

                    if (isAnyWholeDay) {
                        return `
                            <tr>
                                <td><span class="text-muted small">${idx + 1}</span></td>
                                <td><strong class="text-primary">${r.user?.student_number || 'N/A'}</strong></td>
                                <td><strong>${r.user?.full_name || 'N/A'}</strong></td>
                                <td><span class="bsis-badge bsis-badge-info">${yrBlk}</span></td>
                                <td>${r.event?.title || 'N/A'}</td>
                                <td class="font-monospace small text-center">${amIn}</td>
                                <td class="font-monospace small text-center">${amOut}</td>
                                <td class="font-monospace small text-center">${pmIn}</td>
                                <td class="font-monospace small text-center">${pmOut}</td>
                                <td class="text-center"><span class="bsis-badge ${statusBadge}">${statusLabel}</span></td>
                                <td class="text-center">${r.distance_meters !== null && r.distance_meters !== undefined ? r.distance_meters + 'm' : 'N/A'}</td>
                                <td class="text-end">${fineBadgeHtml}</td>
                            </tr>
                        `;
                    }

                    return `
                        <tr>
                            <td><span class="text-muted small">${idx + 1}</span></td>
                            <td><strong class="text-primary">${r.user?.student_number || 'N/A'}</strong></td>
                            <td><strong>${r.user?.full_name || 'N/A'}</strong></td>
                            <td><span class="bsis-badge bsis-badge-info">${yrBlk}</span></td>
                            <td>${r.event?.title || 'N/A'}</td>
                            <td class="font-monospace small text-center">${amIn}</td>
                            <td class="font-monospace small text-center">${pmOut}</td>
                            <td class="text-center"><span class="bsis-badge ${statusBadge}">${statusLabel}</span></td>
                            <td class="text-center">${r.distance_meters !== null && r.distance_meters !== undefined ? r.distance_meters + 'm' : 'N/A'}</td>
                            <td class="text-end">${fineBadgeHtml}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                table.innerHTML = `
                    <tr>
                        <td colspan="12" class="text-center py-5">
                            <div class="bsis-empty-state">
                                <div class="bsis-empty-icon">
                                    <i class="bi bi-file-earmark-bar-graph"></i>
                                </div>
                                <div class="bsis-empty-title">No Report Records Found</div>
                                <p class="bsis-empty-subtitle">No attendance records match your selected event, status, or search filters.</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        } else {
            table.innerHTML = '<tr><td colspan="12" class="text-center text-danger py-4">Failed to load attendance report data.</td></tr>';
        }
    },

    exportReportCsv() {
        const token = StorageManager.getToken();
        const eventId = document.getElementById('report-event-filter')?.value || '';
        const search = document.getElementById('report-search-input')?.value || '';
        const status = document.getElementById('report-status-filter')?.value || '';
        const yearLevel = document.getElementById('report-year-filter')?.value || '';
        const sectionBlock = document.getElementById('report-block-filter')?.value || '';

        const params = new URLSearchParams({
            type: 'attendance',
            format: 'csv',
            token,
            event_id: eventId,
            search,
            status,
            year_level: yearLevel,
            section_block: sectionBlock
        });

        window.location.href = `/api/reports/export?${params.toString()}`;
    },

    exportReportDocx() {
        const token = StorageManager.getToken();
        const eventId = document.getElementById('report-event-filter')?.value || '';
        const search = document.getElementById('report-search-input')?.value || '';
        const status = document.getElementById('report-status-filter')?.value || '';
        const yearLevel = document.getElementById('report-year-filter')?.value || '';
        const sectionBlock = document.getElementById('report-block-filter')?.value || '';

        const params = new URLSearchParams({
            type: 'attendance',
            format: 'docx',
            token,
            event_id: eventId,
            search,
            status,
            year_level: yearLevel,
            section_block: sectionBlock
        });

        window.location.href = `/api/reports/export?${params.toString()}`;
    },

    async printEventAttendanceReport() {
        const user = StorageManager.getUser();
        const eventId = document.getElementById('report-event-filter')?.value || '';
        const search = document.getElementById('report-search-input')?.value || '';
        const status = document.getElementById('report-status-filter')?.value || '';
        const yearLevel = document.getElementById('report-year-filter')?.value || '';
        const sectionBlock = document.getElementById('report-block-filter')?.value || '';

        const queryParams = new URLSearchParams({
            per_page: 1000,
            event_id: eventId,
            search,
            status,
            year_level: yearLevel,
            section_block: sectionBlock
        });

        this.showToast('Generating official printable attendance report...');
        const res = await StorageManager.apiRequest(`/api/reports/attendance?${queryParams.toString()}`);
        if (!res.ok || !res.data.success) {
            alert('Failed to retrieve attendance report data.');
            return;
        }

        const records = res.data.data.data || [];
        const printDate = new Date().toLocaleString([], { dateStyle: 'long', timeStyle: 'short', hour12: true });

        const eventSelect = document.getElementById('report-event-filter');
        const selectedEventTitle = eventSelect && eventSelect.selectedIndex >= 0 ? eventSelect.options[eventSelect.selectedIndex].text : 'All Events';

        const totalScanned = records.length;
        const presentCount = records.filter(r => r.status === 'present').length;
        const lateCount = records.filter(r => r.status === 'late').length;
        const overrideCount = records.filter(r => r.status === 'manual_override').length;
        const totalFines = records.reduce((sum, r) => sum + (r.fine_paid ? 0 : parseFloat(r.fine_amount || 0)), 0);

        const filterSummaryText = `Event: ${selectedEventTitle} | Year Level: ${yearLevel || 'All'} | Block: ${sectionBlock || 'All'} | Status: ${status ? status.toUpperCase() : 'All'}`;
        const isAnyWholeDay = records.some(r => r.event?.session_type === 'whole_day' || r.am_time_in || r.pm_time_in);

        const rowsHtml = records.map((r, idx) => {
            const isAbsent = r.status === 'absent';
            const yrBlk = [r.user?.year_level, r.user?.section_block].filter(Boolean).join(' - ') || 'N/A';
            const statusLabel = r.status ? r.status.toUpperCase() : 'N/A';
            const statusColor = r.status === 'present' ? '#16A34A' : (r.status === 'late' ? '#EA580C' : (r.status === 'manual_override' ? '#0284C7' : '#DC2626'));
            const fmt = (t) => isAbsent ? '<span style="color: #94A3B8; font-style: italic;">—</span>' : (t ? new Date(t).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true }) : '—');

            const isPaid = r.fine_paid === true || r.fine_paid == 1;
            const isWaived = isPaid && (parseFloat(r.fine_amount || 0) <= 0 || r.verification_data?.waive_details);
            
            let finePrintHtml = '';
            if (isWaived) {
                finePrintHtml = '<span style="color: #0284C7; font-weight: 700;">WAIVED</span>';
            } else if (isPaid) {
                finePrintHtml = '<span style="color: #16A34A; font-weight: 700;">PAID</span>';
            } else if (parseFloat(r.fine_amount || 0) > 0) {
                finePrintHtml = `<span style="color: #DC2626; font-weight: 700;">₱${parseFloat(r.fine_amount).toFixed(2)}</span>`;
            } else {
                finePrintHtml = '<span style="color: #64748B;">—</span>';
            }

            if (isAnyWholeDay) {
                return `
                    <tr style="page-break-inside: avoid;">
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; text-align: center;">${idx + 1}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; font-weight: 700; white-space: nowrap;">${r.user?.student_number || 'N/A'}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; font-weight: 600;">${r.user?.full_name || 'N/A'}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap;">${yrBlk}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1;">${r.event?.title || 'N/A'}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap; font-family: Consolas, monospace; font-size: 0.68rem;">${fmt(r.am_time_in || r.scan_time)}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap; font-family: Consolas, monospace; font-size: 0.68rem;">${fmt(r.am_time_out)}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap; font-family: Consolas, monospace; font-size: 0.68rem;">${fmt(r.pm_time_in)}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap; font-family: Consolas, monospace; font-size: 0.68rem;">${fmt(r.pm_time_out || r.checkout_time)}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; text-align: center; font-weight: 700; color: ${statusColor}; white-space: nowrap;">${statusLabel}</td>
                        <td style="padding: 2px 4px; border: 1px solid #CBD5E1; text-align: right; font-weight: 700; white-space: nowrap;">${finePrintHtml}</td>
                    </tr>
                `;
            }

            return `
                <tr style="page-break-inside: avoid;">
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1; text-align: center;">${idx + 1}</td>
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1; font-weight: 700; white-space: nowrap;">${r.user?.student_number || 'N/A'}</td>
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1; font-weight: 600;">${r.user?.full_name || 'N/A'}</td>
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap;">${yrBlk}</td>
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1;">${r.event?.title || 'N/A'}</td>
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap; font-family: Consolas, monospace;">${fmt(r.scan_time || r.am_time_in)}</td>
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1; text-align: center; white-space: nowrap; font-family: Consolas, monospace;">${fmt(r.checkout_time || r.pm_time_out)}</td>
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1; text-align: center; font-weight: 700; color: ${statusColor}; white-space: nowrap;">${statusLabel}</td>
                    <td style="padding: 3px 5px; border: 1px solid #CBD5E1; text-align: right; font-weight: 700; white-space: nowrap;">${finePrintHtml}</td>
                </tr>
            `;
        }).join('');

        const headersHtml = isAnyWholeDay
            ? `
                <tr style="background-color: #063B5C; color: #FFFFFF;">
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; width: 20px; text-align: center;">#</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: left; width: 80px;">Student ID</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: left;">Student Full Name</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: center; width: 60px;">Year/Block</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: left;">Event</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: center; width: 55px;">AM In</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: center; width: 55px;">AM Out</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: center; width: 55px;">PM In</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: center; width: 55px;">PM Out</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: center; width: 55px;">Status</th>
                    <th style="padding: 2px 4px; border: 1px solid #063B5C; text-align: right; width: 50px;">Fine</th>
                </tr>
            `
            : `
                <tr style="background-color: #063B5C; color: #FFFFFF;">
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; width: 25px; text-align: center;">#</th>
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; text-align: left; width: 85px;">Student ID</th>
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; text-align: left;">Student Full Name</th>
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; text-align: center; width: 70px;">Year/Block</th>
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; text-align: left;">Event Session</th>
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; text-align: center; width: 75px;">Time-In</th>
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; text-align: center; width: 75px;">Time-Out</th>
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; text-align: center; width: 65px;">Status</th>
                    <th style="padding: 3px 5px; border: 1px solid #063B5C; text-align: right; width: 55px;">Fine</th>
                </tr>
            `;

        const printableContainer = document.getElementById('printable-fines-area');
        printableContainer.innerHTML = `
            <div style="font-family: 'Inter', Arial, sans-serif; color: #0F172A; padding: 4px; line-height: 1.2;">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #063B5C; padding-bottom: 5px; margin-bottom: 6px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="/images/bsis-logo.png" style="height: 42px;">
                        <div>
                            <h4 style="margin: 0; color: #063B5C; font-weight: 800; font-size: 1.05rem; letter-spacing: -0.2px;">TALIBON POLYTECHNIC COLLEGE</h4>
                            <h6 style="margin: 1px 0 0 0; color: #0284C7; font-weight: 700; font-size: 0.78rem;">Bachelor of Science in Information Systems (BSIS)</h6>
                            <span style="font-size: 0.70rem; color: #64748B;">Official Student Event Attendance & Verification Sheet</span>
                        </div>
                    </div>
                    <div style="text-align: right; font-size: 0.70rem; color: #64748B; line-height: 1.3;">
                        <strong>Date Printed:</strong> ${printDate}<br>
                        <strong>Issued By:</strong> ${user ? user.full_name : 'System Administrator'}
                    </div>
                </div>

                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 4px; padding: 4px 8px; margin-bottom: 6px; font-size: 0.72rem;">
                    <div style="margin-bottom: 2px;"><strong>Report Target:</strong> ${filterSummaryText}</div>
                    <div style="display: flex; gap: 14px; color: #063B5C; font-weight: bold; flex-wrap: wrap;">
                        <span>Total Roster: ${totalScanned}</span>
                        <span style="color: #16A34A;">Present: ${presentCount}</span>
                        <span style="color: #EA580C;">Late: ${lateCount}</span>
                        <span style="color: #0284C7;">Override: ${overrideCount}</span>
                        <span style="color: #DC2626;">Total Fines: ₱${totalFines.toFixed(2)}</span>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; font-size: 0.70rem; margin-bottom: 8px;">
                    <thead>
                        ${headersHtml}
                    </thead>
                    <tbody>
                        ${rowsHtml || '<tr><td colspan="12" style="text-align: center; padding: 12px;">No attendance records found.</td></tr>'}
                    </tbody>
                </table>

                <div style="margin-top: 25px; display: flex; justify-content: space-between; text-align: center; font-size: 0.72rem; color: #1E293B; page-break-inside: avoid;">
                    <div style="width: 210px;">
                        <div style="height: 45px;"></div>
                        <div style="border-bottom: 1.5px solid #0F172A; padding-bottom: 2px; margin-bottom: 2px;">
                            <strong>${user ? user.full_name : 'System BSIS Administrator'}</strong>
                        </div>
                        <span style="color: #64748B; font-size: 0.68rem; font-weight: 500;">BSIS Attendance Officer / Staff In-Charge</span>
                    </div>
                    <div style="width: 210px;">
                        <div style="height: 45px;"></div>
                        <div style="border-bottom: 1.5px solid #0F172A; padding-bottom: 2px; margin-bottom: 2px;">
                            <strong>Program Head</strong>
                        </div>
                        <span style="color: #64748B; font-size: 0.68rem; font-weight: 500;">BSIS Department / College</span>
                    </div>
                </div>
            </div>
        `;

        window.print();
        setTimeout(() => {
            printableContainer.innerHTML = '';
            window.location.hash = '#reports';
            this.showView('view-reports');
        }, 100);
    },

    // 9. SYSTEM AUDIT LOGS
    handleAuditLogSearchDebounced() {
        const input = document.getElementById('audit-log-search-input');
        const query = input ? input.value : '';
        const clearBtn = document.getElementById('audit-log-search-clear');
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

        clearTimeout(this.auditLogSearchDebounceTimer);
        this.auditLogSearchDebounceTimer = setTimeout(() => {
            this.auditLogsCurrentPage = 1;
            this.loadAuditLogs(1);
        }, 250);
    },

    goToAuditLogsPage(page) {
        this.auditLogsCurrentPage = page;
        this.loadAuditLogs(page);
    },

    renderAuditLogsPagination(paginator) {
        const infoEl = document.getElementById('audit-logs-page-info');
        const navEl = document.getElementById('audit-logs-pagination-nav');
        if (!paginator) return;

        const total = paginator.total || 0;
        const from = paginator.from || 0;
        const to = paginator.to || 0;
        const current = paginator.current_page || 1;
        const last = paginator.last_page || 1;

        if (infoEl) {
            infoEl.innerText = total > 0 ? `Showing ${from} to ${to} of ${total} audit records` : 'Showing 0 of 0 audit records';
        }

        if (!navEl) return;
        if (last <= 1) {
            navEl.innerHTML = '';
            return;
        }

        let html = '';
        html += `
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <button class="page-link py-1 px-2" onclick="AdminApp.goToAuditLogsPage(${current - 1})" ${current === 1 ? 'disabled' : ''} title="Previous Page">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>
        `;

        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(last, current + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><button class="page-link py-1 px-2" onclick="AdminApp.goToAuditLogsPage(1)">1</button></li>`;
            if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link py-1 px-2">...</span></li>`;
        }

        for (let p = startPage; p <= endPage; p++) {
            html += `
                <li class="page-item ${p === current ? 'active' : ''}">
                    <button class="page-link py-1 px-2 fw-semibold" onclick="AdminApp.goToAuditLogsPage(${p})">${p}</button>
                </li>
            `;
        }

        if (endPage < last) {
            if (endPage < last - 1) html += `<li class="page-item disabled"><span class="page-link py-1 px-2">...</span></li>`;
            html += `<li class="page-item"><button class="page-link py-1 px-2" onclick="AdminApp.goToAuditLogsPage(${last})">${last}</button></li>`;
        }

        html += `
            <li class="page-item ${current === last ? 'disabled' : ''}">
                <button class="page-link py-1 px-2" onclick="AdminApp.goToAuditLogsPage(${current + 1})" ${current === last ? 'disabled' : ''} title="Next Page">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        `;
        navEl.innerHTML = html;
    },

    getAuditActionBadge(action) {
        switch (action) {
            case 'event_bypass_toggled':
                return '<span class="bsis-badge bsis-badge-warning" style="font-size: 0.73rem;"><i class="bi bi-lightning-charge-fill me-1"></i> BYPASS TOGGLED</span>';
            case 'event_bypass_failed_auth':
                return '<span class="bsis-badge bsis-badge-danger" style="font-size: 0.73rem;"><i class="bi bi-shield-x me-1"></i> FAILED BYPASS AUTH</span>';
            case 'failed_scan':
                return '<span class="bsis-badge bsis-badge-danger" style="font-size: 0.73rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i> FAILED SCAN</span>';
            case 'direct_device_reset':
                return '<span class="bsis-badge bsis-badge-warning" style="font-size: 0.73rem;"><i class="bi bi-arrow-repeat me-1"></i> DIRECT RESET</span>';
            case 'device_reset_approved':
                return '<span class="bsis-badge bsis-badge-success" style="font-size: 0.73rem;"><i class="bi bi-check-circle-fill me-1"></i> RESET APPROVED</span>';
            case 'device_reset_rejected':
                return '<span class="bsis-badge bsis-badge-danger" style="font-size: 0.73rem;"><i class="bi bi-x-circle-fill me-1"></i> RESET REJECTED</span>';
            case 'login':
                return '<span class="bsis-badge bsis-badge-info" style="font-size: 0.73rem;"><i class="bi bi-box-arrow-in-right me-1"></i> USER LOGIN</span>';
            case 'login_failed':
                return '<span class="bsis-badge bsis-badge-danger" style="font-size: 0.73rem;"><i class="bi bi-person-x-fill me-1"></i> LOGIN FAILED</span>';
            case 'backup_created':
                return '<span class="bsis-badge bsis-badge-success" style="font-size: 0.73rem;"><i class="bi bi-database-check me-1"></i> BACKUP CREATED</span>';
            case 'manual_override':
                return '<span class="bsis-badge bsis-badge-primary" style="font-size: 0.73rem;"><i class="bi bi-pencil-square me-1"></i> OVERRIDE</span>';
            default:
                const clean = String(action || 'ACTION').replace(/_/g, ' ').toUpperCase();
                return `<span class="bsis-badge bsis-badge-secondary" style="font-size: 0.73rem;">${clean}</span>`;
        }
    },

    async loadAuditLogs(page = 1) {
        this.auditLogsCurrentPage = page;
        const table = document.getElementById('audit-logs-table-body');
        if (table) table.innerHTML = this.renderTableSkeleton(6, 4);

        const search = document.getElementById('audit-log-search-input')?.value || '';
        const action = document.getElementById('audit-log-action-filter')?.value || '';

        const params = new URLSearchParams();
        params.append('page', page);
        params.append('per_page', 10);
        if (search.trim()) params.append('search', search.trim());
        if (action.trim()) params.append('action', action.trim());

        const res = await StorageManager.apiRequest(`/api/audit-logs?${params.toString()}`);
        if (!table) return;

        if (res.ok && res.data.data && res.data.data.data.length > 0) {
            const paginator = res.data.data;
            const records = paginator.data;
            const from = paginator.from || 1;

            table.innerHTML = records.map((a, idx) => {
                const num = from + idx;
                const dateObj = new Date(a.created_at);
                const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const formattedTime = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });

                const userName = a.user ? a.user.full_name : 'System Automated';
                const userRole = a.user ? (a.user.role || 'USER').toUpperCase() : 'SYSTEM';
                const actionBadge = this.getAuditActionBadge(a.action);

                return `
                    <tr>
                        <td class="text-muted fw-bold small text-center" style="width: 45px;">#${num}</td>
                        <td style="width: 175px;">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 170px;" title="${userName}">${userName}</div>
                            <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;">${userRole}</span>
                        </td>
                        <td style="width: 170px;">${actionBadge}</td>
                        <td style="min-width: 280px;">
                            <div class="text-dark" style="font-size: 0.83rem; line-height: 1.45; word-break: break-word;">${a.description}</div>
                        </td>
                        <td style="width: 120px;">
                            <span class="badge bg-light text-secondary font-monospace border" style="font-size: 0.74rem;">${a.ip_address || '127.0.0.1'}</span>
                        </td>
                        <td class="text-end text-nowrap" style="width: 145px;">
                            <div class="fw-bold text-dark" style="font-size: 0.83rem;">${formattedDate}</div>
                            <div class="text-muted" style="font-size: 0.74rem;">${formattedTime}</div>
                        </td>
                    </tr>
                `;
            }).join('');

            this.renderAuditLogsPagination(paginator);
        } else {
            table.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="bsis-empty-state">
                            <div class="bsis-empty-icon">
                                <i class="bi bi-journal-x"></i>
                            </div>
                            <div class="bsis-empty-title">No Audit Logs Recorded</div>
                            <p class="bsis-empty-subtitle">System actions and user security audit trails will appear here.</p>
                        </div>
                    </td>
                </tr>
            `;
            const infoEl = document.getElementById('audit-logs-page-info');
            if (infoEl) infoEl.innerText = 'Showing 0 of 0 audit records';
            const navEl = document.getElementById('audit-logs-pagination-nav');
            if (navEl) navEl.innerHTML = '';
        }
    },

    // 10. OFFLINE SYNC QUEUE MONITOR
    async loadSyncQueue() {
        const table = document.getElementById('sync-queue-table-body');
        if (table) table.innerHTML = this.renderTableSkeleton(6, 4);

        const res = await StorageManager.apiRequest('/api/sync/status');
        if (!table) return;
        if (res.ok && res.data.data.records.data.length > 0) {
            table.innerHTML = res.data.data.records.data.map(s => `
                <tr>
                    <td><strong>${s.local_record_id}</strong></td>
                    <td>${s.event ? s.event.title : 'N/A'}</td>
                    <td>${s.user ? s.user.full_name : 'N/A'}</td>
                    <td><span class="bsis-badge ${s.sync_status === 'synced' ? 'bsis-badge-success' : (s.sync_status === 'duplicate' ? 'bsis-badge-warning' : 'bsis-badge-danger')}">${s.sync_status}</span></td>
                    <td>${s.sync_error || 'Clean'}</td>
                    <td>${new Date(s.created_at).toLocaleString()}</td>
                </tr>
            `).join('');
        } else {
            table.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="bsis-empty-state">
                            <div class="bsis-empty-icon success">
                                <i class="bi bi-cloud-check"></i>
                            </div>
                            <div class="bsis-empty-title">Sync Queue All Clear</div>
                            <p class="bsis-empty-subtitle">There are no pending offline attendance synchronization tasks in the queue.</p>
                        </div>
                    </td>
                </tr>
            `;
        }
    },

    // 11. DATABASE BACKUPS
    goToBackupsPage(page) {
        this.backupsCurrentPage = page;
        this.renderBackupsTablePage(page);
    },

    renderBackupsPagination(total, page, perPage) {
        const infoEl = document.getElementById('backups-page-info');
        const navEl = document.getElementById('backups-pagination-nav');
        if (!infoEl || !navEl) return;

        const last = Math.ceil(total / perPage) || 1;
        const from = total === 0 ? 0 : (page - 1) * perPage + 1;
        const to = Math.min(page * perPage, total);

        infoEl.innerText = total > 0 ? `Showing ${from} to ${to} of ${total} backup snapshots` : 'Showing 0 of 0 backup snapshots';

        if (last <= 1) {
            navEl.innerHTML = '';
            return;
        }

        let html = '';
        html += `
            <li class="page-item ${page === 1 ? 'disabled' : ''}">
                <button class="page-link py-1 px-2" onclick="AdminApp.goToBackupsPage(${page - 1})" ${page === 1 ? 'disabled' : ''} title="Previous Page">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </li>
        `;

        for (let p = 1; p <= last; p++) {
            html += `
                <li class="page-item ${p === page ? 'active' : ''}">
                    <button class="page-link py-1 px-2 fw-semibold" onclick="AdminApp.goToBackupsPage(${p})">${p}</button>
                </li>
            `;
        }

        html += `
            <li class="page-item ${page === last ? 'disabled' : ''}">
                <button class="page-link py-1 px-2" onclick="AdminApp.goToBackupsPage(${page + 1})" ${page === last ? 'disabled' : ''} title="Next Page">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </li>
        `;
        navEl.innerHTML = html;
    },

    renderBackupsTablePage(page = 1) {
        const table = document.getElementById('backups-table-body');
        if (!table) return;

        const allBackups = this.cachedBackups || [];
        const perPage = 10;
        const total = allBackups.length;
        const from = (page - 1) * perPage;
        const pagedBackups = allBackups.slice(from, from + perPage);

        if (pagedBackups.length > 0) {
            table.innerHTML = pagedBackups.map((b, idx) => {
                const num = from + idx + 1;
                return `
                    <tr>
                        <td class="text-muted fw-bold small" style="width: 35px;">#${num}</td>
                        <td><strong class="font-monospace text-primary" style="font-size: 0.88rem;"><i class="bi bi-filetype-sql me-1 text-secondary"></i>${b.filename}</strong></td>
                        <td><span class="badge bg-light text-dark border">${b.size_formatted}</span></td>
                        <td><span class="text-muted small">${b.created_at}</span></td>
                        <td class="text-end">
                            <a href="/api/backups/${b.filename}/download" class="btn btn-sm btn-primary py-1 px-3 me-1 fw-bold"><i class="bi bi-download me-1"></i> Download</a>
                            <button onclick="AdminApp.restoreBackup('${b.filename}')" class="btn btn-sm btn-outline-warning py-1 px-3 fw-bold"><i class="bi bi-arrow-counterclockwise me-1"></i> Restore</button>
                        </td>
                    </tr>
                `;
            }).join('');
        } else {
            table.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="bsis-empty-state">
                            <div class="bsis-empty-icon">
                                <i class="bi bi-database-exclamation"></i>
                            </div>
                            <div class="bsis-empty-title">No Database Backups Found</div>
                            <p class="bsis-empty-subtitle">Click "Create Backup" to generate a complete SQL database snapshot.</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        this.renderBackupsPagination(total, page, perPage);
    },

    async loadBackups(page = 1) {
        this.backupsCurrentPage = page;
        const table = document.getElementById('backups-table-body');
        if (table) table.innerHTML = this.renderTableSkeleton(5, 3);

        const res = await StorageManager.apiRequest('/api/backups');
        if (!table) return;

        if (res.ok && res.data.data) {
            this.cachedBackups = res.data.data;
            this.renderBackupsTablePage(page);
        } else {
            table.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Failed to load database backups.</td></tr>';
        }
    },

    async createBackup() {
        this.showConfirm({
            title: 'Create Database Backup',
            message: 'Generate a new MySQL SQL dump backup of all events, attendance, and user data?',
            icon: 'bi-database-fill-gear',
            type: 'info',
            confirmText: 'Create Backup',
            confirmClass: 'btn-primary',
            onConfirm: async () => {
                const res = await StorageManager.apiRequest('/api/backups/create', { method: 'POST' });
                if (res.ok && res.data.success) {
                    this.showToast('Database backup created successfully!', 'success');
                    this.loadBackups();
                } else {
                    this.showToast(res.data?.message || 'Backup failed.', 'danger');
                }
            }
        });
    },

    async restoreBackup(filename) {
        this.showConfirm({
            title: 'Restore Database',
            message: `RESTORE DATABASE from '${filename}'? WARNING: This will overwrite the current database records with the backup dump!`,
            icon: 'bi-exclamation-triangle-fill',
            type: 'danger',
            confirmText: 'Restore Now',
            confirmClass: 'btn-danger',
            onConfirm: async () => {
                const res = await StorageManager.apiRequest(`/api/backups/${filename}/restore`, { method: 'POST' });
                if (res.ok) {
                    this.showToast('Database restored successfully! Reloading...', 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    this.showToast(res.data?.message || 'Database restore failed.', 'danger');
                }
            }
        });
    },

    logout() {
        this.showConfirm({
            title: 'Sign Out',
            message: 'Are you sure you want to sign out of the dashboard?',
            icon: 'bi-box-arrow-right',
            type: 'warning',
            confirmText: 'Sign Out',
            confirmClass: 'btn-warning',
            onConfirm: () => {
                StorageManager.clearSession();
                this.currentActiveLiveEvent = null;
                const banner = document.getElementById('admin-live-event-banner');
                if (banner) banner.classList.add('d-none');
                window.location.hash = '#login';
                this.handleRoute();
            }
        });
    },

    /* ======================================================================
       Stacking Toast Notification System
       ====================================================================== */
    showToast(msg, type = 'info') {
        let container = document.getElementById('bsis-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'bsis-toast-container';
            container.className = 'bsis-toast-container';
            document.body.appendChild(container);
        }

        const iconMap = {
            success: 'bi-check-circle-fill text-success',
            danger: 'bi-exclamation-triangle-fill text-danger',
            warning: 'bi-exclamation-circle-fill text-warning',
            info: 'bi-info-circle-fill text-primary'
        };

        const toast = document.createElement('div');
        toast.className = `bsis-toast-item toast-${type}`;
        toast.innerHTML = `<i class="bi ${iconMap[type] || iconMap.info}" style="font-size: 1.2rem; flex-shrink: 0;"></i><span>${msg}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, 4500);
    },

    /* ======================================================================
       Custom Confirmation Dialog (replaces browser confirm())
       ====================================================================== */
    showConfirm({ title, message, icon = 'bi-question-circle', type = 'warning', confirmText = 'Confirm', cancelText = 'Cancel', confirmClass = 'btn-danger', onConfirm, onCancel }) {
        // Remove existing confirm if any
        document.querySelectorAll('.bsis-confirm-overlay').forEach(el => el.remove());

        const overlay = document.createElement('div');
        overlay.className = 'bsis-confirm-overlay';
        overlay.innerHTML = `
            <div class="bsis-confirm-dialog">
                <div class="bsis-confirm-icon ${type}">
                    <i class="bi ${icon}"></i>
                </div>
                <div class="bsis-confirm-title">${title}</div>
                <div class="bsis-confirm-message">${message}</div>
                <div class="bsis-confirm-actions">
                    <button class="btn btn-outline-secondary bsis-confirm-cancel">${cancelText}</button>
                    <button class="btn ${confirmClass} bsis-confirm-ok">${confirmText}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add('show'));

        const close = () => {
            overlay.classList.remove('show');
            setTimeout(() => overlay.remove(), 250);
        };

        overlay.querySelector('.bsis-confirm-cancel').addEventListener('click', () => {
            close();
            if (onCancel) onCancel();
        });

        overlay.querySelector('.bsis-confirm-ok').addEventListener('click', () => {
            close();
            if (onConfirm) onConfirm();
        });

        // Close on overlay background click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                close();
                if (onCancel) onCancel();
            }
        });
    },

    /* ======================================================================
       Skeleton Loader Helpers
       ====================================================================== */
    renderTableSkeleton(colSpan, rows = 5, cols = null) {
        const colCount = cols || Math.min(colSpan, 7);
        let html = '';
        for (let r = 0; r < rows; r++) {
            html += `<tr><td colspan="${colSpan}" style="padding: 0; border: none;">
                <div class="bsis-skeleton-row">
                    <div class="bsis-skeleton skel-checkbox"></div>`;
            for (let c = 0; c < colCount - 2; c++) {
                const widths = ['skel-id', 'skel-name', 'skel-text', 'skel-text', 'skel-badge'];
                html += `<div class="bsis-skeleton skel-cell ${widths[c % widths.length]}"></div>`;
            }
            html += `<div class="bsis-skeleton skel-btn"></div>
                </div>
            </td></tr>`;
        }
        return html;
    },

    /* ======================================================================
       Inline Form Validation Helper
       ====================================================================== */
    validateField(fieldId, condition, errorMsg) {
        const field = document.getElementById(fieldId);
        if (!field) return condition;

        // Remove previous error
        const existing = field.parentElement.querySelector('.bsis-field-error');
        if (existing) existing.remove();

        if (!condition) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            const errEl = document.createElement('div');
            errEl.className = 'bsis-field-error';
            errEl.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i> ${errorMsg}`;
            field.parentElement.appendChild(errEl);
            return false;
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            return true;
        }
    },

    clearFieldValidation(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        field.classList.remove('is-invalid', 'is-valid');
        const err = field.parentElement.querySelector('.bsis-field-error');
        if (err) err.remove();
    },

    /* ======================================================================
       Institutional Live Audio Synthesizer (Web Audio API - Zero External Files)
       ====================================================================== */
    playAudioCue(type = 'success') {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const now = ctx.currentTime;

            if (type === 'success') {
                // Pleasant Harmonic Chime: D5 (587.33Hz) -> A5 (880Hz)
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, now);
                osc.frequency.exponentialRampToValueAtTime(880.00, now + 0.12);
                gain.gain.setValueAtTime(0.18, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.45);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.5);
            } else if (type === 'warning') {
                // Amber Double-Blip: 440Hz
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'triangle';
                osc1.frequency.setValueAtTime(440, now);
                gain1.gain.setValueAtTime(0.18, now);
                gain1.gain.exponentialRampToValueAtTime(0.01, now + 0.15);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.16);

                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'triangle';
                osc2.frequency.setValueAtTime(440, now + 0.18);
                gain2.gain.setValueAtTime(0.18, now + 0.18);
                gain2.gain.exponentialRampToValueAtTime(0.01, now + 0.33);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.18);
                osc2.stop(now + 0.34);
            } else {
                // Low Reject Tone: 220Hz -> 180Hz
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, now);
                osc.frequency.exponentialRampToValueAtTime(180, now + 0.25);
                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.4);
            }
        } catch (e) {
            // Audio context silently suspended until user gestures
        }
    },

    /* ======================================================================
       Global Keyboard Navigation Shortcuts
       ====================================================================== */
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Press '/' outside text fields to focus primary search
            if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                const activeView = document.querySelector('.admin-view:not(.d-none)');
                if (!activeView) return;
                const searchInput = activeView.querySelector('input[type="text"][id$="-search-input"]') || document.getElementById('user-search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // Press 'Escape' to dismiss autocomplete popups
            if (e.key === 'Escape') {
                document.querySelectorAll('.bsis-autocomplete-dropdown').forEach(el => el.style.display = 'none');
            }
        });
    },

    /* ======================================================================
       Live Event Sticky Banner & Fast Actions
       ====================================================================== */
    async updateLiveEventBanner() {
        const banner = document.getElementById('admin-live-event-banner');
        if (!banner) return;

        const token = StorageManager.getToken();
        const user = StorageManager.getUser();
        if (!token || !user || (user.role !== 'admin' && user.role !== 'event_staff') || document.body.classList.contains('in-login-view') || window.location.hash === '#login') {
            this.currentActiveLiveEvent = null;
            banner.classList.add('d-none');
            return;
        }

        const res = await StorageManager.apiRequest('/api/events?status=active&per_page=1');
        if (res.ok && res.data && res.data.data && res.data.data.data && res.data.data.data.length > 0) {
            const activeEvent = res.data.data.data[0];
            this.currentActiveLiveEvent = activeEvent;
            banner.classList.remove('d-none');
            const titleEl = document.getElementById('live-banner-title');
            const statsEl = document.getElementById('live-banner-stats');
            if (titleEl) titleEl.innerText = activeEvent.title;
            if (statsEl) {
                const timeStr = this.formatEventDisplayDateTime(activeEvent.start_time);
                statsEl.innerText = `${activeEvent.venue_name} • Started ${timeStr} • Real-time Attendance Active`;
            }
        } else {
            this.currentActiveLiveEvent = null;
            banner.classList.add('d-none');
        }
    },

    openActiveQrScreen() {
        if (this.currentActiveLiveEvent) {
            window.location.hash = `#qr-display?event=${this.currentActiveLiveEvent.id}`;
        } else {
            this.showToast('No live event currently active.', 'info');
        }
    },

    triggerQuickOverrideModal() {
        const modalEl = document.getElementById('modal-manual-override');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }
};
