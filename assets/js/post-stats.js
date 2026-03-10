/* global folioPostStats */
(function () {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const config = window.folioPostStats || {};
    if (!config.ajaxUrl || !config.nonce || !config.postId) {
        return;
    }

    if (window.__folioPostStatsTracked && window.__folioPostStatsTracked === config.postId) {
        return;
    }
    window.__folioPostStatsTracked = config.postId;

    const sendRequest = function () {
        const payload = new URLSearchParams();
        payload.append('action', 'folio_track_post_view');
        payload.append('nonce', config.nonce);
        payload.append('postId', config.postId);

        if (navigator.sendBeacon) {
            const blob = new Blob([payload.toString()], { type: 'application/x-www-form-urlencoded' });
            navigator.sendBeacon(config.ajaxUrl, blob);
            return;
        }

        window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: payload.toString()
        }).catch(function () {
            // 静默失败，避免控制台噪音
        });
    };

    const schedule = function () {
        const delay = typeof config.delay === 'number' ? Math.max(config.delay, 0) : 0;
        if (delay > 0) {
            setTimeout(sendRequest, delay);
        } else if (window.requestIdleCallback) {
            window.requestIdleCallback(sendRequest, { timeout: 2000 });
        } else {
            sendRequest();
        }
    };

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        schedule();
    } else {
        document.addEventListener('DOMContentLoaded', schedule, { once: true });
    }
})();
