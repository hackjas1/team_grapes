/* BSIS Attendance System — Storage & API Manager */
(function() {
    try {
        if (typeof window !== 'undefined') {
            var noop = function() {};
            window.console.log = noop;
            window.console.info = noop;
            window.console.debug = noop;
            window.console.warn = noop;
        }
    } catch(e) {}
})();

const StorageManager = {
    TOKEN_KEY: 'bsis_auth_token',
    USER_KEY: 'bsis_user_profile',
    DEVICE_KEY: 'bsis_device_credential',
    OFFLINE_QUEUE_KEY: 'bsis_offline_attendance_queue',
    SYNC_EVENT_KEY: 'bsis_auth_sync_event',
    _broadcastChannel: null,
    _lastLogoutHandled: 0,

    getBroadcastChannel() {
        if (!this._broadcastChannel && typeof BroadcastChannel !== 'undefined') {
            try {
                this._broadcastChannel = new BroadcastChannel('bsis_auth_channel');
                this._broadcastChannel.onmessage = (event) => {
                    if (event.data && event.data.action === 'logout') {
                        this.handleCrossTabLogout();
                    }
                };
            } catch (e) {
                this._broadcastChannel = null;
            }
        }
        return this._broadcastChannel;
    },

    getToken() {
        // Check localStorage first as the shared cross-tab source of truth
        const localToken = localStorage.getItem(this.TOKEN_KEY);
        if (!localToken) {
            // If localStorage has no token, any session in this browser has terminated or logged out.
            // Clean up stale sessionStorage so a refresh cannot retain a stale logged-in session!
            try { sessionStorage.removeItem(this.TOKEN_KEY); } catch (e) {}
            try { sessionStorage.removeItem(this.USER_KEY); } catch (e) {}
            return null;
        }
        return localToken;
    },

    setToken(token) {
        this._lastLogoutHandled = 0;
        try { localStorage.setItem(this.TOKEN_KEY, token); } catch (e) {}
        try { sessionStorage.setItem(this.TOKEN_KEY, token); } catch (e) {}
    },

    clearToken() {
        try { localStorage.removeItem(this.TOKEN_KEY); } catch (e) {}
        try { sessionStorage.removeItem(this.TOKEN_KEY); } catch (e) {}
    },

    getUser() {
        const localToken = localStorage.getItem(this.TOKEN_KEY);
        if (!localToken) {
            try { sessionStorage.removeItem(this.USER_KEY); } catch (e) {}
            return null;
        }
        const data = localStorage.getItem(this.USER_KEY) || sessionStorage.getItem(this.USER_KEY);
        return data ? JSON.parse(data) : null;
    },

    setUser(user) {
        const serialized = JSON.stringify(user);
        try { localStorage.setItem(this.USER_KEY, serialized); } catch (e) {}
        try { sessionStorage.setItem(this.USER_KEY, serialized); } catch (e) {}
    },

    getDeviceCredential() {
        return localStorage.getItem(this.DEVICE_KEY);
    },

    setDeviceCredential(credential) {
        localStorage.setItem(this.DEVICE_KEY, credential);
    },

    clearSession(broadcast = true) {
        const hadToken = !!(localStorage.getItem(this.TOKEN_KEY) || sessionStorage.getItem(this.TOKEN_KEY));
        const hadUser = !!(localStorage.getItem(this.USER_KEY) || sessionStorage.getItem(this.USER_KEY));

        try { sessionStorage.removeItem(this.TOKEN_KEY); } catch (e) {}
        try { sessionStorage.removeItem(this.USER_KEY); } catch (e) {}
        try { localStorage.removeItem(this.TOKEN_KEY); } catch (e) {}
        try { localStorage.removeItem(this.USER_KEY); } catch (e) {}

        // Only broadcast if there was actually an active session before clearing and broadcast is requested
        if ((!hadToken && !hadUser) || !broadcast) {
            return;
        }

        // Broadcast to other open tabs using BroadcastChannel
        try {
            const channel = this.getBroadcastChannel();
            if (channel) {
                channel.postMessage({ action: 'logout', timestamp: Date.now() });
            }
        } catch (e) {}

        // Trigger storage event across all other tabs/windows
        try {
            localStorage.setItem(this.SYNC_EVENT_KEY, JSON.stringify({
                action: 'logout',
                timestamp: Date.now()
            }));
        } catch (e) {}
    },

    handleCrossTabLogout() {
        // Prevent rapid duplicate execution (e.g. BroadcastChannel + storage event firing simultaneously)
        const now = Date.now();
        if (this._lastLogoutHandled && (now - this._lastLogoutHandled < 2500)) {
            return;
        }

        const hadToken = !!(localStorage.getItem(this.TOKEN_KEY) || sessionStorage.getItem(this.TOKEN_KEY));
        const hadUser = !!(localStorage.getItem(this.USER_KEY) || sessionStorage.getItem(this.USER_KEY));

        // Always ensure local session is purged
        try { sessionStorage.removeItem(this.TOKEN_KEY); } catch (e) {}
        try { sessionStorage.removeItem(this.USER_KEY); } catch (e) {}
        try { localStorage.removeItem(this.TOKEN_KEY); } catch (e) {}
        try { localStorage.removeItem(this.USER_KEY); } catch (e) {}

        // If this tab had NO active session, silently exit (NEVER show toast or re-navigate!)
        if (!hadToken && !hadUser) {
            return;
        }

        // If this tab is already on the login screen, do not show any toast
        const currentHash = window.location.hash || '';
        const isLoginHash = currentHash === '#login' || currentHash === '';
        const isInLoginView = document.documentElement.classList.contains('in-login-view') || 
                              (document.body && document.body.classList.contains('in-login-view'));
        if (isLoginHash && isInLoginView) {
            return;
        }

        this._lastLogoutHandled = now;

        // Invalidate Admin Console view if loaded
        if (typeof AdminApp !== 'undefined' && AdminApp.handleRoute) {
            AdminApp.currentActiveLiveEvent = null;
            const banner = document.getElementById('admin-live-event-banner');
            if (banner) banner.classList.add('d-none');
            document.documentElement.classList.add('in-login-view');
            window.location.hash = '#login';
            AdminApp.handleRoute();
            if (typeof AdminApp.showToast === 'function') {
                AdminApp.showToast('Session ended. You were signed out from another tab or window.', 'warning');
            }
        }

        // Invalidate Student App view if loaded
        const studentController = typeof StudentPWA !== 'undefined' ? StudentPWA : (typeof StudentApp !== 'undefined' ? StudentApp : null);
        if (studentController && studentController.handleRoute) {
            window.location.hash = '#login';
            studentController.handleRoute();
            if (typeof studentController.showToast === 'function') {
                studentController.showToast('Session ended. You were signed out from another tab or window.');
            }
        }
    },

    // Offline Attendance Queue Helpers
    getOfflineQueue() {
        const queue = localStorage.getItem(this.OFFLINE_QUEUE_KEY);
        return queue ? JSON.parse(queue) : [];
    },

    addOfflineRecord(record) {
        const queue = this.getOfflineQueue();
        queue.push(record);
        localStorage.setItem(this.OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    },

    clearOfflineQueue() {
        localStorage.removeItem(this.OFFLINE_QUEUE_KEY);
    },

    // API Request Helper
    async apiRequest(endpoint, options = {}) {
        const token = this.getToken();
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        // Auto serialize JSON body if object is passed
        let requestBody = options.body;
        if (requestBody && typeof requestBody === 'object' && !(requestBody instanceof FormData)) {
            requestBody = JSON.stringify(requestBody);
        }

        try {
            const response = await fetch(endpoint, {
                ...options,
                headers,
                body: requestBody
            });

            const json = await response.json().catch(() => null);

            if (response.status === 401) {
                // Do NOT trigger session termination on authentication or password reset requests
                const isAuthAttempt = endpoint.includes('/api/auth/login') ||
                                      endpoint.includes('/api/auth/forgot-password') ||
                                      endpoint.includes('/api/auth/reset-password') ||
                                      endpoint.includes('/api/onboarding');

                if (!isAuthAttempt) {
                    const hadToken = !!(localStorage.getItem(this.TOKEN_KEY) || sessionStorage.getItem(this.TOKEN_KEY));
                    this.clearSession(false); // Clean up locally without cascading broadcast

                    if (hadToken) {
                        const currentHash = window.location.hash || '';
                        const isLoginHash = currentHash === '#login' || currentHash === '';
                        const isInLoginView = document.documentElement.classList.contains('in-login-view') || 
                                              (document.body && document.body.classList.contains('in-login-view'));

                        if (!isLoginHash || !isInLoginView) {
                            if (typeof AdminApp !== 'undefined' && AdminApp.handleRoute) {
                                AdminApp.currentActiveLiveEvent = null;
                                const banner = document.getElementById('admin-live-event-banner');
                                if (banner) banner.classList.add('d-none');
                                document.documentElement.classList.add('in-login-view');
                                window.location.hash = '#login';
                                AdminApp.handleRoute();
                                if (typeof AdminApp.showToast === 'function') {
                                    AdminApp.showToast('Your session has expired. Please sign in again.', 'warning');
                                }
                            } else {
                                const studentController = typeof StudentPWA !== 'undefined' ? StudentPWA : (typeof StudentApp !== 'undefined' ? StudentApp : null);
                                if (studentController && studentController.handleRoute) {
                                    window.location.hash = '#login';
                                    studentController.handleRoute();
                                    if (typeof studentController.showToast === 'function') {
                                        studentController.showToast('Your session has expired. Please sign in again.');
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return {
                status: response.status,
                ok: response.ok,
                data: json
            };
        } catch (error) {
            return {
                status: 0,
                ok: false,
                data: { success: false, message: 'Network connection unavailable. Operating in offline mode.' }
            };
        }
    }
};

// Initialize BroadcastChannel listener
try {
    StorageManager.getBroadcastChannel();
} catch (e) {}

// Listen for cross-tab storage changes (fired by other tabs on the same origin)
window.addEventListener('storage', (e) => {
    if (e.key === StorageManager.SYNC_EVENT_KEY && e.newValue) {
        try {
            const payload = JSON.parse(e.newValue);
            if (payload && payload.action === 'logout') {
                StorageManager.handleCrossTabLogout();
            }
        } catch (err) {}
    } else if (e.key === StorageManager.TOKEN_KEY && !e.newValue && e.oldValue) {
        StorageManager.handleCrossTabLogout();
    }
});
