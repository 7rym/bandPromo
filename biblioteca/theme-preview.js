(function () {
    const CSS_VAR_MAP = {
        'color.primary': '--primary-color',
        'color.secondary': '--secondary-color',
        'color.background': '--bg-color',
        'color.text': '--text-color',
        'color.text_muted': '--color-text-muted',
        'color.surface_mid': '--color-surface-mid',
        'color.surface_deep': '--color-surface-deep',
        'color.link': '--color-link',
        'color.link_hover': '--color-link-hover',
        'color.link_visited': '--color-link-visited',
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function tokenValue(document, path) {
        const parts = String(path || '').split('.');
        let value = document?.tokens;
        for (const part of parts) {
            if (!value || typeof value !== 'object') {
                return '';
            }
            value = value[part];
        }
        return typeof value === 'string' || typeof value === 'number' ? String(value).trim() : '';
    }

    function renderShellPreviewChrome(document) {
        const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
        const logo = String(assets.logo || '').trim();
        const poster = String(assets.poster || '').trim();
        const background = String(assets.background_image || '').trim();
        const backgroundVideo = String(assets.background_video || '').trim();
        const posterAttr = background
            ? ` poster="${escapeHtml(background)}"`
            : '';
        const backgroundAttribute = background && !backgroundVideo
            ? ` style="background-image:url('${escapeHtml(background)}');"`
            : '';
        const logoMarkup = logo
            ? `<img class="theme-preview-shell-logo" src="${escapeHtml(logo)}" alt="" loading="lazy" onerror="this.style.opacity=0.25">`
            : '<span class="theme-preview-muted">No logo assigned</span>';
        const coverMarkup = poster
            ? `<img class="theme-preview-cover-art" src="${escapeHtml(poster)}" alt="" loading="lazy" onerror="this.style.opacity=0.35">`
            : '<span class="theme-preview-cover-label">Cover art</span>';
        const livingVideoMarkup = backgroundVideo
            ? `<video class="theme-preview-shell-video" src="${escapeHtml(backgroundVideo)}"${posterAttr} muted loop playsinline autoplay preload="auto" aria-hidden="true"></video>`
            : '';

        const beggarsBanquet = document?.player?.beggars_banquet !== false;
        const coverReflection = document?.player?.cover_reflection !== false;
        const beggarsMarkup = beggarsBanquet
            ? `<div class="theme-preview-beggars-banquet" aria-hidden="true">
                    <span class="theme-preview-support-link">Support</span>
               </div>`
            : '';
        const reflectionMarkup = coverReflection
            ? `<div class="theme-preview-cover-reflection" aria-hidden="true">${coverMarkup}</div>`
            : '';

        return `
            <div class="theme-preview-shell-chrome${backgroundVideo ? ' theme-preview-shell-chrome--living' : ''}"${backgroundAttribute}>
                ${livingVideoMarkup}
                <div class="theme-preview-player-chrome" aria-hidden="true">
                    <div class="theme-preview-scene">
                        <div class="theme-preview-cover theme-preview-cover--player">
                            ${coverMarkup}
                        </div>
                        ${reflectionMarkup}
                    </div>
                    <div class="theme-preview-player-transport">
                        <div class="theme-preview-track-info">
                            <span class="theme-preview-artist">Artist name</span>
                            <span class="theme-preview-track-title">Track title</span>
                        </div>
                        <div class="theme-preview-player-controls">
                            <button type="button" class="theme-preview-player-btn" tabindex="-1">&#9664; Previous</button>
                            <button type="button" class="theme-preview-player-btn theme-preview-player-btn--play" tabindex="-1">Play</button>
                            <button type="button" class="theme-preview-player-btn" tabindex="-1">Next &#9654;</button>
                        </div>
                        <div class="theme-preview-scrubber">
                            <span class="theme-preview-scrubber-time">0:42</span>
                            <span class="theme-preview-scrubber-track" aria-hidden="true">
                                <span class="theme-preview-scrubber-fill"></span>
                                <span class="theme-preview-scrubber-thumb"></span>
                            </span>
                            <span class="theme-preview-scrubber-time">3:24</span>
                        </div>
                    </div>
                    ${beggarsMarkup}
                </div>
                <div class="theme-preview-shell-header">
                    ${logoMarkup}
                </div>`;
    }

    function normalizePlaylistSelectorMode(document) {
        const mode = String(document?.player?.playlist_selector || '').trim().toLowerCase();
        if (mode === 'dropdown' || mode === 'buttons' || mode === 'coverflow') {
            return mode;
        }
        return 'coverflow';
    }

    function renderPlaylistSelectorPreview(document) {
        const mode = normalizePlaylistSelectorMode(document);
        const poster = String(document?.assets?.poster || '').trim();
        const samplePlaylists = [
            { title: 'Main playlist', active: true, initial: 'M' },
            { title: 'B-sides', active: false, initial: 'B' },
            { title: 'Live set', active: false, initial: 'L' },
        ];

        let body = '';
        if (mode === 'buttons') {
            body = `
                <div class="theme-preview-playlist-buttons">
                    ${samplePlaylists.map((entry) => `
                        <span class="theme-preview-playlist-btn${entry.active ? ' is-active' : ''}">${escapeHtml(entry.title)}</span>
                    `).join('')}
                </div>`;
        } else if (mode === 'coverflow') {
            body = `
                <div class="theme-preview-playlist-coverflow">
                    ${samplePlaylists.map((entry) => {
                        const thumb = poster
                            ? `<img class="theme-preview-playlist-thumb" src="${escapeHtml(poster)}" alt="" loading="lazy">`
                            : `<span class="theme-preview-playlist-thumb theme-preview-playlist-placeholder">${escapeHtml(entry.initial)}</span>`;
                        return `<span class="theme-preview-playlist-cover-item${entry.active ? ' is-active' : ''}">${thumb}</span>`;
                    }).join('')}
                </div>`;
        } else {
            body = `
                <span class="theme-preview-playlist-select" aria-hidden="true">
                    <span class="theme-preview-playlist-select-value">Main playlist</span>
                </span>`;
        }

        return `
            <section class="theme-preview-section theme-preview-section--playlist-selector" aria-hidden="true">
                <div class="theme-preview-playlist-selector theme-preview-playlist-selector--${escapeHtml(mode)}">
                    ${body}
                </div>
            </section>`;
    }

    function renderMarkup(document) {
        if (!document) {
            return '<p class="theme-editor-empty">No brand selected.</p>';
        }

        return `
            <div class="theme-preview-shell">
                ${renderShellPreviewChrome(document)}
                    ${renderPlaylistSelectorPreview(document)}
                    <section class="theme-preview-section">
                        <div class="page-richtext theme-preview-richtext">
                            <h1>Heading 1</h1>
                            <h2>Heading 2</h2>
                            <h3>Heading 3</h3>
                            <p>Paragraph — regular body text for pages, captions, and player content.</p>
                            <p class="page-text-small">Small — secondary notes and fine print.</p>
                            <pre class="page-text-code">Code — monospace sample text</pre>
                        </div>
                    </section>

                    <section class="theme-preview-section">
                        <div class="theme-preview-controls">
                            <button type="button" class="theme-preview-btn theme-preview-btn--primary">Primary action</button>
                            <button type="button" class="theme-preview-btn theme-preview-btn--secondary">Secondary</button>
                            <span class="theme-preview-tab theme-preview-tab--active">Active tab</span>
                            <span class="theme-preview-tab">Tab</span>
                        </div>
                    </section>

                    <section class="theme-preview-section">
                        <div class="theme-preview-links">
                            <a href="#" class="theme-preview-link" onclick="return false;">Default link</a>
                            <a href="#" class="theme-preview-link theme-preview-link--hover" onclick="return false;">Hover state</a>
                            <a href="#" class="theme-preview-link theme-preview-link--visited" onclick="return false;">Visited state</a>
                        </div>
                    </section>
            </div>
        </div>`;
    }

    function render(container, document, options = {}) {
        if (!(container instanceof HTMLElement)) {
            return;
        }
        const styleId = String(options.styleId || 'bandpromo-shared-theme-preview-style');
        const selector = String(options.selector || `#${container.id} .theme-preview-shell-chrome`);
        let style = document?.ownerDocument?.getElementById(styleId)
            || window.document.getElementById(styleId);
        if (!style) {
            style = window.document.createElement('style');
            style.id = styleId;
            window.document.head.appendChild(style);
        }

        if (!document) {
            style.textContent = '';
            container.innerHTML = renderMarkup(null);
            return;
        }

        const rules = [];
        Object.entries(CSS_VAR_MAP).forEach(([tokenPath, cssVariable]) => {
            const value = tokenValue(document, tokenPath);
            if (value) {
                rules.push(`${cssVariable}:${value}`);
            }
        });
        const baseFont = tokenValue(document, 'typography.font_family_base');
        const headingFont = tokenValue(document, 'typography.font_family_heading');
        if (baseFont) {
            rules.push(`--theme-body-font:${baseFont}`);
        }
        if (headingFont) {
            rules.push(`--theme-heading-font:${headingFont}`);
        } else if (baseFont) {
            rules.push(`--theme-heading-font:${baseFont}`);
        }
        const dimRaw = tokenValue(document, 'effects.backdrop_dim');
        const blurRaw = tokenValue(document, 'effects.panel_blur');
        const dim = Math.max(0, Math.min(100, parseInt(dimRaw || '72', 10) || 72));
        const blur = Math.max(0, Math.min(24, parseInt(blurRaw || '5', 10) || 0));
        rules.push(`--shell-scrim-strength:${(dim / 100).toFixed(2)}`);
        rules.push(`--panel-blur:${blur}px`);
        rules.push('--primary-a20:color-mix(in srgb, var(--primary-color) 20%, transparent)');
        rules.push('--primary-a50:color-mix(in srgb, var(--primary-color) 50%, transparent)');

        style.textContent = rules.length ? `${selector}{${rules.join(';')};}` : '';
        container.innerHTML = renderMarkup(document);
        startPreviewVideos(container);
    }

    function startPreviewVideos(root) {
        if (!(root instanceof HTMLElement)) {
            return;
        }
        root.querySelectorAll('video.theme-preview-shell-video, video.theme-shell-slot-thumb').forEach((video) => {
            video.muted = true;
            video.loop = true;
            video.playsInline = true;
            const play = () => {
                const attempt = video.play();
                if (attempt && typeof attempt.catch === 'function') {
                    attempt.catch(() => {
                        // Autoplay can still be blocked; poster/still remains visible.
                    });
                }
            };
            if (video.readyState >= 2) {
                play();
                return;
            }
            video.addEventListener('canplay', play, { once: true });
            try {
                video.load();
            } catch (error) {
                // Ignore reload failures.
            }
            play();
        });
    }

    window.bandpromoThemePreview = {
        render,
        renderMarkup,
        startVideos: startPreviewVideos,
    };
}());
