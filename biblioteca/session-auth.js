(function () {
    'use strict';

    const config = window.BANDPROMO_SESSION_AUTH || {};
    if (config.enabled !== true) {
        return;
    }

    const loginUrl = typeof config.loginUrl === 'string' && config.loginUrl !== ''
        ? config.loginUrl
        : '/';
    const pingUrl = typeof config.pingUrl === 'string' && config.pingUrl !== ''
        ? config.pingUrl
        : '/biblioteca/session-check.php';
    const pingIntervalMs = Number.isFinite(config.pingIntervalMs) ? config.pingIntervalMs : 300000;

    let redirecting = false;

    function shouldWatchRequest(input) {
        const requestUrl = typeof input === 'string'
            ? input
            : (input instanceof Request ? input.url : '');
        if (!requestUrl) {
            return false;
        }

        try {
            const parsed = new URL(requestUrl, window.location.origin);
            if (parsed.origin !== window.location.origin) {
                return false;
            }

            if (parsed.pathname === '/biblioteca/log.php') {
                return false;
            }

            return parsed.pathname.startsWith('/biblioteca/')
                || parsed.pathname.startsWith('/play/');
        } catch (_error) {
            return false;
        }
    }

    function handleSessionExpired() {
        if (redirecting) {
            return;
        }

        redirecting = true;

        try {
            const url = new URL(loginUrl, window.location.origin);
            url.searchParams.set('session_expired', '1');
            window.location.replace(url.toString());
        } catch (_error) {
            window.location.replace(loginUrl);
        }
    }

    window.bandpromoHandleSessionExpired = handleSessionExpired;

    const nativeFetch = window.fetch.bind(window);
    window.fetch = async function bandpromoFetch(input, init) {
        const response = await nativeFetch(input, init);
        if (response.status === 401 && shouldWatchRequest(input)) {
            handleSessionExpired();
        }
        return response;
    };

    async function pingSession() {
        if (redirecting) {
            return;
        }

        try {
            const response = await nativeFetch(pingUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (response.status === 401) {
                handleSessionExpired();
            }
        } catch (_error) {
            // Ignore transient network errors during background checks.
        }
    }

    if (pingIntervalMs > 0) {
        window.setInterval(pingSession, pingIntervalMs);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            pingSession();
        }
    });

    window.setTimeout(pingSession, 1500);
})();
