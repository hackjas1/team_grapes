/* BSIS Attendance System — Storage & API Manager */
const StorageManager = {
    TOKEN_KEY: 'bsis_auth_token',
    USER_KEY: 'bsis_user_profile',
    DEVICE_KEY: 'bsis_device_credential',
    OFFLINE_QUEUE_KEY: 'bsis_offline_attendance_queue',

    getToken() {
        return sessionStorage.getItem(this.TOKEN_KEY);
    },

    setToken(token) {
        sessionStorage.setItem(this.TOKEN_KEY, token);
    },

    clearToken() {
        sessionStorage.removeItem(this.TOKEN_KEY);
    },

    getUser() {
        const data = sessionStorage.getItem(this.USER_KEY);
        return data ? JSON.parse(data) : null;
    },

    setUser(user) {
        sessionStorage.setItem(this.USER_KEY, JSON.stringify(user));
    },

    getDeviceCredential() {
        return localStorage.getItem(this.DEVICE_KEY);
    },

    setDeviceCredential(credential) {
        localStorage.setItem(this.DEVICE_KEY, credential);
    },

    clearSession() {
        sessionStorage.removeItem(this.TOKEN_KEY);
        sessionStorage.removeItem(this.USER_KEY);
        try {
            localStorage.removeItem(this.TOKEN_KEY);
            localStorage.removeItem(this.USER_KEY);
        } catch (e) {}
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

            if (response.status === 401 && (!json || json.message === 'Unauthenticated.')) {
                this.clearSession();
                window.location.hash = '#login';
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

// Security: Clean up any legacy persistent localStorage auth tokens on script load
try {
    localStorage.removeItem(StorageManager.TOKEN_KEY);
    localStorage.removeItem(StorageManager.USER_KEY);
} catch (e) {}
