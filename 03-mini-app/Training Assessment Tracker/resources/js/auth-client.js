export function createAuthClient({ state, storage, fetcher = fetch, onExpired = () => {} }) {
    const key = 'tracker.session';
    let token = null;
    try { token = storage.getItem(key); } catch { /* Memory-only if storage is unavailable. */ }
    let pending;
    function clear() {
        token = null;
        state.user = null;
        try { storage.removeItem(key); } catch { /* Storage may be blocked. */ }
    }
    async function request(path, options = {}) {
        const currentToken = token;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 12000);
        try {
            const response = await fetcher('/api' + path, {
                ...options, signal: controller.signal, credentials: 'omit',
                headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    ...(currentToken ? { Authorization: 'Bearer ' + currentToken } : {}),
                },
            });
            const body = await response.json().catch(() => null);
            if (!response.ok) {
                if (response.status === 401 && path !== '/login' && currentToken === token) {
                    clear();
                    state.notice = 'Your session has ended. Please sign in again.';
                    onExpired();
                }
                const error = new Error(body?.message || 'Something went wrong. Please try again.');
                error.status = response.status;
                error.details = body?.details || {};
                throw error;
            }
            if (!body) throw new Error('The server returned an unreadable response. Please try again.');
            return body;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('The request timed out. Please try again.');
            if (error instanceof TypeError) throw new Error('Unable to reach the server. Check your connection and try again.');
            throw error;
        } finally { clearTimeout(timer); }
    }
    return {
        request,
        async register(fields) {
            const result = await request('/register', { method: 'POST', body: JSON.stringify(fields) });
            if (!result.token || !result.data) throw new Error('The server returned an incomplete registration response.');
            token = result.token; state.user = result.data; state.notice = '';
            try { storage.setItem(key, token); } catch { state.notice = 'Browser storage is unavailable. Refreshing will sign you out.'; }
        },
        async login(email, password) {
            const result = await request('/login', { method: 'POST', body: JSON.stringify({ email, password }) });
            if (!result.token || !result.data) throw new Error('The server returned an incomplete sign-in response.');
            token = result.token;
            state.user = result.data;
            state.notice = '';
            try { storage.setItem(key, token); } catch { state.notice = 'Browser storage is unavailable. Refreshing will sign you out.'; }
        },
        async restore() {
            if (!token) return false;
            if (state.user) return true;
            if (!pending) {
                const checkingToken = token;
                pending = request('/me').then(result => {
                    if (checkingToken !== token) return false;
                    state.user = result.data;
                    return true;
                }).finally(() => { pending = null; });
            }
            return pending;
        },
        async logout() {
            try {
                if (token) await request('/logout', { method: 'POST' });
                state.notice = 'You have been signed out.';
            } catch (error) {
                state.notice = error.status === 401 ? 'You have been signed out.' :
                    'Signed out on this browser. The server could not confirm token revocation.';
            } finally { clear(); }
        },
    };
}
