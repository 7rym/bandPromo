/**
 * Branding Live preview — Player surface.
 * Uses the same PHP #mediaplayer renderer + style.css as /play (via frame),
 * with demo track/cover content and stacked geometry (not wide split).
 */
(function () {
    let requestSerial = 0;

    function csrfToken() {
        if (typeof window.refreshAdminCsrfToken === 'function') {
            return window.refreshAdminCsrfToken().catch(function () {
                return '';
            });
        }
        return Promise.resolve('');
    }

    function ensureHost(container) {
        let host = container.querySelector('.play-preview-player-host');
        if (host instanceof HTMLElement) {
            return host;
        }
        host = document.createElement('div');
        host.className = 'play-preview-player-host';
        container.innerHTML = '';
        container.appendChild(host);
        return host;
    }

    function ensureIframe(host) {
        let iframe = host.querySelector('iframe.play-preview-player-frame');
        if (iframe instanceof HTMLIFrameElement) {
            return iframe;
        }
        host.innerHTML = '';
        iframe = document.createElement('iframe');
        iframe.className = 'play-preview-player-frame';
        iframe.title = 'Player live preview';
        iframe.setAttribute('sandbox', 'allow-same-origin allow-scripts');
        iframe.setAttribute('referrerpolicy', 'no-referrer');
        host.appendChild(iframe);
        return iframe;
    }

    /**
     * Render Player preview into the Branding Live preview container.
     * @param {HTMLElement} container
     * @param {object} brandDocument
     * @returns {Promise<void>}
     */
    function renderInto(container, brandDocument) {
        if (!(container instanceof HTMLElement)) {
            return Promise.resolve();
        }
        if (!brandDocument) {
            container.innerHTML = '<p class="brand-editor-empty">No brand selected.</p>';
            return Promise.resolve();
        }

        const host = ensureHost(container);
        const iframe = ensureIframe(host);
        const serial = ++requestSerial;
        host.classList.add('is-loading');

        return csrfToken()
            .then(function (token) {
                return fetch('biblioteca/play-preview-player-frame.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'text/html',
                    },
                    body: JSON.stringify({
                        csrf_token: token || '',
                        brand: brandDocument,
                    }),
                });
            })
            .then(function (response) {
                return response.text().then(function (text) {
                    return { ok: response.ok, status: response.status, text: text };
                });
            })
            .then(function (result) {
                if (serial !== requestSerial) {
                    return;
                }
                host.classList.remove('is-loading');
                if (!result.ok) {
                    host.innerHTML = '<p class="brand-editor-empty">Player preview failed to load.</p>';
                    return;
                }
                // Re-ensure iframe if error path replaced host contents earlier.
                const frame = ensureIframe(host);
                frame.srcdoc = result.text;
            })
            .catch(function () {
                if (serial !== requestSerial) {
                    return;
                }
                host.classList.remove('is-loading');
                host.innerHTML = '<p class="brand-editor-empty">Player preview failed to load.</p>';
            });
    }

    /**
     * Sync stub kept for brand-preview.renderMarkup callers — prefer renderInto.
     */
    function renderMarkup() {
        return '<div class="play-preview-player-host"><p class="brand-editor-empty">Loading player preview…</p></div>';
    }

    window.bandpromoPlayPreviewPlayer = {
        renderInto: renderInto,
        renderMarkup: renderMarkup,
    };
}());
