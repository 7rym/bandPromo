/**
 * Branding Live preview — Content surface.
 * Same PHP #content-container + style.css as /play (via frame), stacked geometry.
 *
 * Paths (shell video must not restart on chrome / token tweaks):
 * 1. Same shell + same chrome identity → patch CSS vars in-place
 * 2. Same shell + chrome change (playlist mode / logo / poster)
 *    → re-fetch content chrome fragment; replace #content-container (+ brand CSS)
 * 3. Shell assets change / first paint → full iframe srcdoc reload
 */
(function () {
    let requestSerial = 0;
    /** @type {WeakMap<HTMLElement, { shellKey: string, chromeKey: string }>} */
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
        let host = container.querySelector('.play-preview-content-host');
        if (host instanceof HTMLElement) {
            return host;
        }
        host = document.createElement('div');
        host.className = 'play-preview-content-host';
        container.innerHTML = '';
        container.appendChild(host);
        return host;
    }

    function ensureIframe(host) {
        let iframe = host.querySelector('iframe.play-preview-content-frame');
        if (iframe instanceof HTMLIFrameElement) {
            return iframe;
        }
        host.innerHTML = '';
        iframe = document.createElement('iframe');
        iframe.className = 'play-preview-content-frame';
        iframe.title = 'Content live preview';
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

    function roleCss(document, roleKey, fallback) {
        const ROLE_CSS = {
            primary: 'var(--primary-color)',
            secondary: 'var(--secondary-color)',
            text: 'var(--text-color)',
            muted: 'var(--color-text-muted)',
            surface: 'var(--color-surface-mid)',
            deep: 'var(--color-surface-deep)',
            transparent: 'transparent',
        };
        const roles = document?.roles && typeof document.roles === 'object' ? document.roles : {};
        const raw = String(roles[roleKey] || '').trim().toLowerCase();
        if (ROLE_CSS[raw]) {
            return ROLE_CSS[raw];
        }
        return fallback;
    }

    function densityMetrics(raw) {
        let density = String(raw || 'normal').trim().toLowerCase();
        if (density === 'minimal') {
            density = 'dense';
        }
        if (density === 'dense') {
            return { padY: 6, padX: 8, gap: 4, minHeight: 28 };
        }
        if (density === 'compact') {
            return { padY: 8, padX: 10, gap: 6, minHeight: 32 };
        }
        if (density === 'comfortable') {
            return { padY: 16, padX: 20, gap: 12, minHeight: 40 };
        }
        if (density === 'spacious') {
            return { padY: 22, padX: 28, gap: 16, minHeight: 48 };
        }
        return { padY: 12, padX: 14, gap: 8, minHeight: 36 };
    }

    function panelDensityMetrics(raw) {
        let density = String(raw || 'normal').trim().toLowerCase();
        if (density === 'minimal') {
            density = 'dense';
        }
        if (density === 'dense') {
            return { padY: 8, padX: 10, gap: 6 };
        }
        if (density === 'compact') {
            return { padY: 10, padX: 12, gap: 8 };
        }
        if (density === 'comfortable') {
            return { padY: 18, padX: 22, gap: 14 };
        }
        if (density === 'spacious') {
            return { padY: 24, padX: 28, gap: 18 };
        }
        return { padY: 14, padX: 16, gap: 10 };
    }

    function cornersRadius(raw) {
        const value = String(raw || 'shaved').trim().toLowerCase();
        if (value === 'square') {
            return 0;
        }
        if (value === 'pill') {
            return 999;
        }
        return 10;
    }

    function borderWidth(raw) {
        const value = String(raw || 'none').trim().toLowerCase();
        if (value === 'thin') {
            return 1;
        }
        if (value === 'normal') {
            return 2;
        }
        if (value === 'fat') {
            return 3;
        }
        return 0;
    }

    function contentStyle(document) {
        const content = document?.content && typeof document.content === 'object' ? document.content : {};
        return String(content.style || 'soft').trim().toLowerCase();
    }

    function playlistMode(document) {
        const mode = String(document?.player?.playlist_selector || '').trim().toLowerCase();
        if (mode === 'dropdown' || mode === 'buttons' || mode === 'coverflow') {
            return mode;
        }
        return 'coverflow';
    }

    function navigatorIdle(document) {
        let mode = String(document?.player?.navigator_idle || '').trim().toLowerCase();
        if (mode === 'gray') {
            mode = 'grey';
        }
        return ['full', 'soft', 'grey'].includes(mode) ? mode : 'full';
    }

    function navigatorIdleVars(mode) {
        const opacity = { full: '1', soft: '0.72', grey: '0.88' };
        const grayscale = { full: '0', soft: '0.45', grey: '1' };
        return {
            '--navigator-idle-opacity': opacity[mode] || '1',
            '--navigator-idle-grayscale': grayscale[mode] || '0',
        };
    }

    function contentCssVars(document) {
        /** @type {Record<string, string>} */
        const vars = {};
        const primary = tokenValue(document, 'color.primary');
        const secondary = tokenValue(document, 'color.secondary');
        const text = tokenValue(document, 'color.text');
        const textMuted = tokenValue(document, 'color.text_muted');
        const surfaceMid = tokenValue(document, 'color.surface_mid');
        const surfaceDeep = tokenValue(document, 'color.surface_deep');
        const link = tokenValue(document, 'color.link');
        const linkHover = tokenValue(document, 'color.link_hover');
        const linkVisited = tokenValue(document, 'color.link_visited');
        const backdropDim = clampInt(tokenValue(document, 'effects.backdrop_dim'), 0, 100, 45);

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
        if (link) {
            vars['--color-link'] = link;
        }
        if (linkHover) {
            vars['--color-link-hover'] = linkHover;
        }
        if (linkVisited) {
            vars['--color-link-visited'] = linkVisited;
        }
        vars['--shell-scrim-strength'] = String(backdropDim / 100);

        const content = document?.content && typeof document.content === 'object' ? document.content : {};
        const controlMetrics = densityMetrics(content.density);
        const controlRadius = cornersRadius(content.corners);
        const controlBorder = borderWidth(content.border);
        const style = contentStyle(document);
        const buttonBg = roleCss(document, 'button_bg', 'transparent');
        const buttonFg = roleCss(document, 'button_fg', 'var(--text-color)');
        const buttonBorder = roleCss(document, 'button_border', 'var(--primary-color)');
        const buttonActive = roleCss(document, 'button_active', 'var(--primary-color)');
        let controlBg = buttonBg;
        let controlBorderColor = buttonBorder;
        if (style === 'outline') {
            controlBg = 'transparent';
        } else if (style === 'solid') {
            controlBg = buttonActive;
        } else if (style === 'soft' || style === 'soft fill') {
            controlBg = 'color-mix(in srgb, ' + buttonActive + ' 22%, transparent)';
        }

        vars['--content-control-radius'] = controlRadius + 'px';
        vars['--content-control-border-width'] = controlBorder + 'px';
        vars['--content-control-pad-y'] = controlMetrics.padY + 'px';
        vars['--content-control-pad-x'] = controlMetrics.padX + 'px';
        vars['--content-control-min-height'] = controlMetrics.minHeight + 'px';
        vars['--content-control-gap'] = controlMetrics.gap + 'px';
        vars['--content-control-bg'] = controlBg;
        vars['--content-control-border'] = controlBorder > 0 ? controlBorderColor : 'transparent';
        vars['--content-control-fg'] = buttonFg;
        vars['--content-control-bg-hover'] = 'color-mix(in srgb, ' + buttonActive + ' 35%, transparent)';
        vars['--content-control-fg-hover'] = buttonFg;
        vars['--content-control-bg-active'] = buttonActive;
        vars['--content-control-border-active'] = buttonActive;
        vars['--content-control-fg-active'] = '#000000';
        vars['--content-control-glow-active'] = 'color-mix(in srgb, ' + buttonActive + ' 55%, transparent)';

        const effects = document?.effects && typeof document.effects === 'object' ? document.effects : {};
        const panelDim = clampInt(effects.content_panel_dim ?? effects.panel_dim, 0, 100, 55);
        const panelBlur = clampInt(effects.content_panel_blur ?? effects.panel_blur, 0, 24, 12);
        const panelMetrics = panelDensityMetrics(effects.content_panel_density || 'normal');
        const panelRadius = cornersRadius(effects.content_panel_corners || 'shaved');
        const panelBorderW = borderWidth(effects.content_panel_border || 'none');
        const panelBorderRole = roleCss(document, 'panel_border', 'var(--primary-color)');
        const fillAlpha = Math.max(0, Math.min(1, panelDim / 100));
        vars['--content-panel-scrim-strength'] = fillAlpha.toFixed(2);
        vars['--content-panel-blur'] = panelBlur + 'px';
        vars['--content-panel-fill'] = 'color-mix(in srgb, var(--color-surface-mid, #1a2332) '
            + Math.round(fillAlpha * 100) + '%, transparent)';
        vars['--content-panel-pad-y'] = panelMetrics.padY + 'px';
        vars['--content-panel-pad-x'] = panelMetrics.padX + 'px';
        vars['--content-panel-gap'] = panelMetrics.gap + 'px';
        vars['--content-panel-radius'] = panelRadius + 'px';
        vars['--content-panel-border-width'] = panelBorderW + 'px';
        vars['--content-panel-border'] = panelBorderW > 0 ? panelBorderRole : 'transparent';
        vars['--role-panel-border'] = panelBorderRole;

        let typeDensity = String(tokenValue(document, 'typography.density') || 'normal').toLowerCase();
        if (typeDensity === 'minimal') {
            typeDensity = 'dense';
        }
        let typeLineHeight = '1.2';
        let typeBlockGap = 6;
        if (typeDensity === 'dense') {
            typeLineHeight = '1';
            typeBlockGap = 2;
        } else if (typeDensity === 'compact') {
            typeLineHeight = '1.1';
            typeBlockGap = 4;
        } else if (typeDensity === 'comfortable') {
            typeLineHeight = '1.3';
            typeBlockGap = 10;
        } else if (typeDensity === 'spacious') {
            typeLineHeight = '1.4';
            typeBlockGap = 14;
        }
        vars['--content-type-line-height'] = typeLineHeight;
        vars['--content-type-block-gap'] = typeBlockGap + 'px';

        const baseFont = tokenValue(document, 'typography.font_family_base');
        const headingFont = tokenValue(document, 'typography.font_family_heading');
        if (baseFont) {
            vars['--brand-font-body'] = baseFont;
            vars['font-family'] = baseFont;
        }
        if (headingFont) {
            vars['--brand-font-heading'] = headingFont;
        } else if (baseFont) {
            vars['--brand-font-heading'] = baseFont;
        }

        const heading = roleCss(document, 'heading', 'var(--text-color)');
        const body = roleCss(document, 'body', 'var(--text-color)');
        const muted = roleCss(document, 'muted', 'var(--color-text-muted)');
        vars['--role-heading'] = heading;
        vars['--role-body'] = body;
        vars['--role-muted'] = muted;

        const idleVars = navigatorIdleVars(navigatorIdle(document));
        Object.keys(idleVars).forEach(function (key) {
            vars[key] = idleVars[key];
        });

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

    function chromeKey(document) {
        const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
        // navigator_idle is CSS-only — patch in place (not a chrome remirror).
        return [
            playlistMode(document),
            String(assets.logo || ''),
            String(assets.poster || ''),
        ].join('|');
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
        return !!(doc && doc.getElementById('content-container') && doc.documentElement);
    }

    function patchInto(iframe, document) {
        const doc = frameDoc(iframe);
        if (!doc || !doc.documentElement) {
            return false;
        }
        const root = doc.documentElement;
        const vars = contentCssVars(document);
        Object.keys(vars).forEach(function (key) {
            if (key === 'font-family') {
                root.style.fontFamily = vars[key];
                return;
            }
            root.style.setProperty(key, vars[key]);
        });
        return true;
    }

    function applyBrandCss(doc, brandCss) {
        if (!doc || typeof brandCss !== 'string' || brandCss === '') {
            return;
        }
        const existing = doc.getElementById('bandpromo-brand-vars');
        const holder = doc.createElement('div');
        holder.innerHTML = brandCss;
        const next = holder.querySelector('#bandpromo-brand-vars');
        if (!(next instanceof HTMLElement)) {
            return;
        }
        if (existing) {
            existing.replaceWith(next);
        } else if (doc.head) {
            doc.head.appendChild(next);
        }
    }

    function remirrorChrome(iframe, brandDocument) {
        const doc = frameDoc(iframe);
        const current = doc && doc.getElementById('content-container');
        if (!doc || !current) {
            return Promise.resolve(false);
        }

        return csrfToken()
            .then(function (token) {
                return fetch('biblioteca/play-preview-content-frame.php', {
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
                const liveContent = liveDoc && liveDoc.getElementById('content-container');
                if (!liveDoc || !liveContent) {
                    return false;
                }
                const holder = liveDoc.createElement('div');
                holder.innerHTML = result.data.chrome;
                const next = holder.querySelector('#content-container');
                if (!(next instanceof HTMLElement)) {
                    return false;
                }
                liveContent.replaceWith(next);
                applyBrandCss(liveDoc, result.data.brand_css);
                patchInto(iframe, brandDocument);
                return true;
            })
            .catch(function () {
                return false;
            });
    }

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
        const nextChrome = chromeKey(brandDocument);
        const prev = frameState.get(container);

        if (
            prev
            && prev.shellKey === nextShell
            && prev.chromeKey === nextChrome
            && canPatch(iframe)
            && patchInto(iframe, brandDocument)
        ) {
            host.classList.remove('is-loading');
            return Promise.resolve();
        }

        if (prev && prev.shellKey === nextShell && canPatch(iframe)) {
            const serial = ++requestSerial;
            return remirrorChrome(iframe, brandDocument).then(function (ok) {
                if (serial !== requestSerial) {
                    return;
                }
                if (ok) {
                    frameState.set(container, { shellKey: nextShell, chromeKey: nextChrome });
                    host.classList.remove('is-loading');
                    return;
                }
                return reloadFrame(container, host, brandDocument, nextShell, nextChrome, serial);
            });
        }

        const serial = ++requestSerial;
        return reloadFrame(container, host, brandDocument, nextShell, nextChrome, serial);
    }

    function reloadFrame(container, host, brandDocument, nextShell, nextChrome, serial) {
        host.classList.add('is-loading');

        return csrfToken()
            .then(function (token) {
                return fetch('biblioteca/play-preview-content-frame.php', {
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
                    host.innerHTML = '<p class="brand-editor-empty">Content preview failed to load.</p>';
                    frameState.delete(container);
                    return;
                }
                const frame = ensureIframe(host);
                frame.onload = function () {
                    patchInto(frame, brandDocument);
                    frameState.set(container, { shellKey: nextShell, chromeKey: nextChrome });
                };
                frame.srcdoc = result.text;
                frameState.set(container, { shellKey: nextShell, chromeKey: nextChrome });
            })
            .catch(function () {
                if (serial !== requestSerial) {
                    return;
                }
                host.classList.remove('is-loading');
                host.innerHTML = '<p class="brand-editor-empty">Content preview failed to load.</p>';
                frameState.delete(container);
            });
    }

    function renderMarkup() {
        return '<div class="play-preview-content-host"><p class="brand-editor-empty">Loading content preview…</p></div>';
    }

    window.bandpromoPlayPreviewContent = {
        renderInto: renderInto,
        renderMarkup: renderMarkup,
    };
}());
