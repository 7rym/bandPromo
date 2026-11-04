/**
 * Branding Live preview — Player surface.
 * Same PHP #mediaplayer + style.css as /play (via frame), stacked geometry.
 *
 * Paths (shell video must not restart on chrome tweaks):
 * 1. Same shell + same demo chrome → patch CSS vars / toggles in-place
 * 2. Same shell + chrome change (Preferred type, Login, Animation, demo art)
 *    → re-fetch player-chrome.php fragment and replace #mediaplayer only
 * 3. Shell assets change / first paint → full iframe srcdoc reload
 */
(function () {
    let requestSerial = 0;
    /** @type {WeakMap<HTMLElement, { shellKey: string, artworkKey: string }>} */
    const frameState = new WeakMap();

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

    function tokenValue(document, path) {
        const parts = String(path || '').split('.');
        let value = document && document.tokens;
        for (let i = 0; i < parts.length; i++) {
            if (!value || typeof value !== 'object') {
                return '';
            }
            value = value[parts[i]];
        }
        return typeof value === 'string' || typeof value === 'number' ? String(value).trim() : '';
    }

    function clampInt(raw, min, max, fallback) {
        const parsed = parseInt(String(raw), 10);
        if (!Number.isFinite(parsed)) {
            return fallback;
        }
        return Math.max(min, Math.min(max, parsed));
    }

    function playerSize(document) {
        let size = String(document?.player?.size || document?.player?.cover_size || 'large').trim().toLowerCase();
        const legacy = { full: 'large', medium: 'normal', half: 'small' };
        if (legacy[size]) {
            size = legacy[size];
        }
        return ['large', 'normal', 'small'].includes(size) ? size : 'large';
    }

    function artworkPreferredForcesFlatCover(document) {
        const mode = artworkPreferred(document);
        return mode === 'playlist' || mode === 'campaign';
    }

    function peeksMode(document) {
        const size = playerSize(document);
        if (size === 'large' || artworkPreferredForcesFlatCover(document)) {
            return 'none';
        }
        let mode = String(document?.player?.peeks || '').trim().toLowerCase();
        if (mode === 'skip') {
            mode = 'jump';
        }
        if (['none', 'visible', 'jump'].includes(mode)) {
            return mode;
        }
        const sideOn = document?.player?.side_covers !== false;
        if (!sideOn) {
            return 'none';
        }
        return document?.player?.side_covers_navigate === false ? 'visible' : 'jump';
    }

    function animationMode(document) {
        let mode = String(document?.player?.animation || '').trim().toLowerCase();
        if (mode === 'none' || mode === 'bars') {
            return mode;
        }
        return document?.player?.analyzer === false ? 'none' : 'bars';
    }

    function artworkPreferred(document) {
        const mode = String(document?.player?.artwork_preferred || '').trim().toLowerCase();
        return ['still', 'living', 'playlist', 'campaign'].includes(mode) ? mode : 'still';
    }

    function densityMetrics(raw) {
        let density = String(raw || 'normal').trim().toLowerCase();
        if (density === 'minimal') {
            density = 'dense';
        }
        if (density === 'dense') {
            return { padY: 6, padX: 8, gap: 4 };
        }
        if (density === 'compact') {
            return { padY: 8, padX: 10, gap: 6 };
        }
        if (density === 'comfortable') {
            return { padY: 16, padX: 20, gap: 12 };
        }
        if (density === 'spacious') {
            return { padY: 22, padX: 28, gap: 16 };
        }
        return { padY: 12, padX: 14, gap: 8 };
    }

    function sizeScales(size) {
        const map = {
            large: { stacked: '0.62', split: '1', short: '1' },
            normal: { stacked: '0.52', split: '0.9', short: '1' },
            small: { stacked: '0.42', split: '0.75', short: '1' },
        };
        return map[size] || map.large;
    }

    function roleCss(document, roleKey, fallback) {
        const ROLE_CSS = {
            primary: 'var(--primary-color)',
            secondary: 'var(--secondary-color)',
            text: 'var(--text-color)',
            text_muted: 'var(--color-text-muted)',
            surface_mid: 'var(--color-surface-mid)',
        };
        let key = String(tokenValue(document, `roles.${roleKey}`) || fallback || 'primary').trim().toLowerCase();
        if (!ROLE_CSS[key]) {
            key = fallback || 'primary';
        }
        return ROLE_CSS[key] || ROLE_CSS.primary;
    }

    /**
     * Player chrome CSS vars (mirrors brand-storage effects + peeks).
     * @returns {Record<string, string>}
     */
    function playerCssVars(document) {
        const legacyDim = clampInt(tokenValue(document, 'effects.panel_dim') || tokenValue(document, 'effects.backdrop_dim'), 0, 100, 72);
        const panelDim = clampInt(tokenValue(document, 'effects.player_panel_dim') || legacyDim, 0, 100, legacyDim);
        const panelBlur = clampInt(tokenValue(document, 'effects.player_panel_blur') || tokenValue(document, 'effects.panel_blur'), 0, 24, 5);
        const density = densityMetrics(tokenValue(document, 'effects.player_panel_density'));
        let corners = String(tokenValue(document, 'effects.player_panel_corners') || 'shaved').trim().toLowerCase();
        if (corners === 'pill') {
            corners = 'shaved';
        }
        if (!['square', 'shaved'].includes(corners)) {
            corners = 'shaved';
        }
        const radius = corners === 'square' ? 0 : 10;
        let border = String(tokenValue(document, 'effects.player_panel_border') || 'none').trim().toLowerCase();
        if (!['none', 'thin', 'normal', 'fat'].includes(border)) {
            border = 'none';
        }
        const borderWidth = border === 'none' ? 0 : (border === 'thin' ? 1 : (border === 'fat' ? 3 : 2));

        const peeks = peeksMode(document);
        const peeksOn = peeks !== 'none';
        const opacityPercent = clampInt(document?.player?.side_covers_opacity, 5, 60, 20);
        const opacity = Math.max(0.05, Math.min(0.6, opacityPercent / 100));
        let hover = Math.min(0.85, Math.round(opacity * 3 * 1000) / 1000);
        if (hover < opacity + 0.15) {
            hover = Math.min(0.85, opacity + 0.25);
        }
        let spread = String(document?.player?.side_covers_spread || 'normal').trim().toLowerCase();
        if (!['close', 'normal', 'wide'].includes(spread)) {
            spread = 'normal';
        }
        const offsetMap = { close: 72, normal: 120, wide: 168 };
        const offset = offsetMap[spread] || 120;
        let colour = String(document?.player?.side_covers_colour || 'soft').trim().toLowerCase();
        if (colour === 'gray') {
            colour = 'grey';
        }
        if (!['full', 'soft', 'grey'].includes(colour)) {
            colour = 'soft';
        }
        const grayscaleMap = { full: '0', soft: '0.5', grey: '1' };
        const navigate = peeks === 'jump';
        const scales = sizeScales(playerSize(document));

        const primary = tokenValue(document, 'color.primary');
        const secondary = tokenValue(document, 'color.secondary');
        const text = tokenValue(document, 'color.text');
        const textMuted = tokenValue(document, 'color.text_muted');
        const surfaceMid = tokenValue(document, 'color.surface_mid');
        const surfaceDeep = tokenValue(document, 'color.surface_deep');
        const backdropDim = clampInt(tokenValue(document, 'effects.backdrop_dim'), 0, 100, 72);

        /** @type {Record<string, string>} */
        const vars = {
            '--shell-scrim-strength': (backdropDim / 100).toFixed(2),
            '--player-panel-scrim-strength': (panelDim / 100).toFixed(2),
            '--player-panel-blur': `${panelBlur}px`,
            '--player-panel-fill': `color-mix(in srgb, var(--color-surface-mid) ${panelDim}%, transparent)`,
            '--player-panel-pad-y': `${density.padY}px`,
            '--player-panel-pad-x': `${density.padX}px`,
            '--player-panel-gap': `${density.gap}px`,
            '--player-panel-radius': `${radius}px`,
            '--player-panel-border-width': `${borderWidth}px`,
            '--player-panel-border': borderWidth > 0 ? 'var(--role-player-panel-border)' : 'transparent',
            '--role-player-panel-border': roleCss(document, 'player_panel_border', 'primary'),
            '--player-size-scale-stacked': scales.stacked,
            '--player-size-scale-split': scales.split,
            '--player-size-scale-short': scales.short,
            '--player-side-gutter': `${offset}px`,
            '--player-side-gutter-scale': peeksOn ? '1' : '0',
            '--side-cover-display': peeksOn ? 'block' : 'none',
            '--side-cover-opacity': String(opacity),
            '--side-cover-opacity-hover': String(hover),
            '--side-cover-offset': `${offset}px`,
            '--side-cover-grayscale': grayscaleMap[colour] || '0.5',
            '--side-cover-pointer-events': navigate ? 'auto' : 'none',
            '--side-cover-cursor': navigate ? 'pointer' : 'default',
        };

        if (primary) {
            vars['--primary-color'] = primary;
        }
        if (secondary) {
            vars['--secondary-color'] = secondary;
        }
        if (text) {
            vars['--text-color'] = text;
        }
        if (textMuted) {
            vars['--color-text-muted'] = textMuted;
        }
        if (surfaceMid) {
            vars['--color-surface-mid'] = surfaceMid;
        }
        if (surfaceDeep) {
            vars['--color-surface-deep'] = surfaceDeep;
        }

        return vars;
    }

    function shellKey(document) {
        const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
        return [
            String(assets.background_image || ''),
            String(assets.background_video || ''),
            String(tokenValue(document, 'effects.backdrop_dim') || ''),
        ].join('|');
    }

    function artworkKey(document) {
        const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
        // Demo chrome identity — remirror #mediaplayer (not the whole iframe/shell).
        return [
            artworkPreferred(document),
            String(assets.poster || ''),
            String(assets.logo || ''),
            artworkPreferred(document) === 'living' ? String(assets.background_video || '') : '',
            animationMode(document),
            loginFormMode(document),
        ].join('|');
    }

    function loginFormMode(document) {
        let mode = String(document?.player?.login_form || '').trim().toLowerCase();
        if (mode === 'minimalistic') {
            mode = 'minimal';
        }
        if (mode === 'none' || mode === 'minimal' || mode === 'full') {
            return mode;
        }
        if (document?.player?.login_status === false) {
            return 'none';
        }
        return 'full';
    }

    function frameDoc(iframe) {
        try {
            return iframe.contentDocument || null;
        } catch (error) {
            return null;
        }
    }

    function canPatch(iframe) {
        const doc = frameDoc(iframe);
        return !!(doc && doc.getElementById('mediaplayer') && doc.documentElement);
    }

    function bindMinimalLogin(doc) {
        doc.querySelectorAll('.player-user-login-wrap[data-login-form-mode="minimal"]').forEach(function (wrap) {
            const openBtn = wrap.querySelector('.player-user-login-open');
            const form = wrap.querySelector('.player-user-login-form');
            if (!(openBtn instanceof HTMLElement) || !(form instanceof HTMLElement)) {
                return;
            }
            if (openBtn.dataset.bound === '1') {
                return;
            }
            openBtn.dataset.bound = '1';
            openBtn.addEventListener('click', function () {
                wrap.classList.add('is-login-expanded');
                openBtn.hidden = true;
                form.hidden = false;
            });
        });
    }

    function bootCoverVideo(doc) {
        const coverVideo = doc.getElementById('coverVideo');
        if (!(coverVideo instanceof HTMLVideoElement) || coverVideo.hidden || !coverVideo.getAttribute('src')) {
            return;
        }
        coverVideo.muted = true;
        coverVideo.loop = true;
        const playPromise = coverVideo.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(function () {});
        }
    }

    function bootAnalyzer(doc) {
        const body = doc.body;
        if (!body || body.classList.contains('analyzer-off')) {
            return;
        }
        const canvas = doc.getElementById('analyzer');
        if (!(canvas instanceof HTMLCanvasElement)) {
            return;
        }
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }
        const bars = 24;
        const draw = function () {
            if (!canvas.isConnected || !doc.body || doc.body.classList.contains('analyzer-off')) {
                return;
            }
            const w = canvas.clientWidth || 200;
            const h = canvas.clientHeight || 40;
            if (canvas.width !== w) {
                canvas.width = w;
            }
            if (canvas.height !== h) {
                canvas.height = h;
            }
            ctx.clearRect(0, 0, w, h);
            const gap = 2;
            const barW = Math.max(2, (w - gap * (bars - 1)) / bars);
            const t = Date.now() / 240;
            for (let i = 0; i < bars; i += 1) {
                const amp = 0.25 + 0.75 * Math.abs(Math.sin(t + i * 0.45));
                const barH = Math.max(3, h * amp * 0.9);
                const x = i * (barW + gap);
                ctx.fillStyle = 'rgba(255,255,255,0.55)';
                ctx.fillRect(x, h - barH, barW, barH);
            }
            doc.defaultView.requestAnimationFrame(draw);
        };
        if (canvas.dataset.previewBars !== '1') {
            canvas.dataset.previewBars = '1';
            doc.defaultView.requestAnimationFrame(draw);
        }
    }

    /**
     * Patch CSS vars + body classes + Support toggle — no markup rebuild.
     */
    function patchInto(iframe, document) {
        const doc = frameDoc(iframe);
        if (!doc || !doc.documentElement) {
            return false;
        }
        const root = doc.documentElement;
        const body = doc.body;
        const vars = playerCssVars(document);
        Object.keys(vars).forEach(function (key) {
            root.style.setProperty(key, vars[key]);
        });

        if (body) {
            const preferred = artworkPreferred(document);
            const flat = artworkPreferredForcesFlatCover(document);
            const reflectionOff = flat || document?.player?.cover_reflection === false;
            body.classList.toggle('cover-reflection-off', reflectionOff);
            body.classList.toggle('analyzer-off', animationMode(document) === 'none');
            body.classList.toggle('artwork-preferred-playlist', preferred === 'playlist');
            body.classList.toggle('artwork-preferred-campaign', preferred === 'campaign');
            body.classList.toggle('artwork-preferred-living', preferred === 'living');
        }

        const beggars = doc.getElementById('beggars-banquet');
        if (beggars) {
            const on = document?.player?.beggars_banquet !== false;
            beggars.hidden = !on;
            const link = beggars.querySelector('.support-link-button');
            if (link instanceof HTMLElement) {
                link.hidden = !on;
            }
        }

        return true;
    }

    /**
     * Re-run the shared player chrome renderer; keep shell video/backdrop in the iframe.
     * @returns {Promise<boolean>}
     */
    function remirrorChrome(iframe, brandDocument) {
        const doc = frameDoc(iframe);
        const current = doc && doc.getElementById('mediaplayer');
        if (!doc || !current) {
            return Promise.resolve(false);
        }

        return csrfToken()
            .then(function (token) {
                return fetch('biblioteca/play-preview-player-frame.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        csrf_token: token || '',
                        brand: brandDocument,
                        fragment: 'chrome',
                    }),
                });
            })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data || result.data.ok !== true || typeof result.data.chrome !== 'string') {
                    return false;
                }
                const liveDoc = frameDoc(iframe);
                const livePlayer = liveDoc && liveDoc.getElementById('mediaplayer');
                if (!liveDoc || !livePlayer) {
                    return false;
                }
                const holder = liveDoc.createElement('div');
                holder.innerHTML = result.data.chrome;
                const next = holder.querySelector('#mediaplayer');
                if (!(next instanceof HTMLElement)) {
                    return false;
                }
                livePlayer.replaceWith(next);

                const body = liveDoc.body;
                const classes = result.data.body_classes && typeof result.data.body_classes === 'object'
                    ? result.data.body_classes
                    : {};
                if (body) {
                    Object.keys(classes).forEach(function (name) {
                        body.classList.toggle(name, !!classes[name]);
                    });
                }

                bindMinimalLogin(liveDoc);
                bootCoverVideo(liveDoc);
                if (result.data.bars_on) {
                    bootAnalyzer(liveDoc);
                }
                patchInto(iframe, brandDocument);
                return true;
            })
            .catch(function () {
                return false;
            });
    }

    /**
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
            frameState.delete(container);
            return Promise.resolve();
        }

        const host = ensureHost(container);
        const iframe = ensureIframe(host);
        const nextShell = shellKey(brandDocument);
        const nextArtwork = artworkKey(brandDocument);
        const prev = frameState.get(container);

        // Fast path: same shell + same demo chrome → CSS/DOM patch only.
        if (
            prev
            && prev.shellKey === nextShell
            && prev.artworkKey === nextArtwork
            && canPatch(iframe)
            && patchInto(iframe, brandDocument)
        ) {
            host.classList.remove('is-loading');
            return Promise.resolve();
        }

        // Same shell, chrome changed (Preferred type / Login / Animation / demo art):
        // remirror #mediaplayer only — do not restart shell living video.
        if (prev && prev.shellKey === nextShell && canPatch(iframe)) {
            const serial = ++requestSerial;
            return remirrorChrome(iframe, brandDocument).then(function (ok) {
                if (serial !== requestSerial) {
                    return;
                }
                if (ok) {
                    frameState.set(container, { shellKey: nextShell, artworkKey: nextArtwork });
                    host.classList.remove('is-loading');
                    return;
                }
                // Fall through to full frame if chrome remirror failed.
                return reloadFrame(container, host, brandDocument, nextShell, nextArtwork, serial);
            });
        }

        const serial = ++requestSerial;
        return reloadFrame(container, host, brandDocument, nextShell, nextArtwork, serial);
    }

    function reloadFrame(container, host, brandDocument, nextShell, nextArtwork, serial) {
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
                    frameState.delete(container);
                    return;
                }
                const frame = ensureIframe(host);
                frame.onload = function () {
                    patchInto(frame, brandDocument);
                    frameState.set(container, { shellKey: nextShell, artworkKey: nextArtwork });
                };
                frame.srcdoc = result.text;
                frameState.set(container, { shellKey: nextShell, artworkKey: nextArtwork });
            })
            .catch(function () {
                if (serial !== requestSerial) {
                    return;
                }
                host.classList.remove('is-loading');
                host.innerHTML = '<p class="brand-editor-empty">Player preview failed to load.</p>';
                frameState.delete(container);
            });
    }

    function renderMarkup() {
        return '<div class="play-preview-player-host"><p class="brand-editor-empty">Loading player preview…</p></div>';
    }

    window.bandpromoPlayPreviewPlayer = {
        renderInto: renderInto,
        renderMarkup: renderMarkup,
    };
}());
