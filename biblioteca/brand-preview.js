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

    function renderShellBackdrop(document) {
        const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
        const background = String(assets.background_image || '').trim();
        const backgroundVideo = String(assets.background_video || '').trim();
        const posterAttr = background
            ? ` poster="${escapeHtml(background)}"`
            : '';
        const backgroundAttribute = background && !backgroundVideo
            ? ` style="background-image:url('${escapeHtml(background)}');"`
            : '';
        const livingVideoMarkup = backgroundVideo
            ? `<video class="theme-preview-shell-video" src="${escapeHtml(backgroundVideo)}"${posterAttr} muted loop playsinline autoplay preload="auto" aria-hidden="true"></video>`
            : '';
        const livingClass = backgroundVideo ? ' theme-preview-shell-chrome--living' : '';

        return {
            openTag: `<div class="theme-preview-shell-chrome${livingClass}"${backgroundAttribute}>`,
            videoMarkup: livingVideoMarkup,
        };
    }

    function renderPlayerPreviewChrome(document) {
        const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
        const poster = String(assets.poster || '').trim();
        const coverMarkup = poster
            ? `<img class="theme-preview-cover-art" src="${escapeHtml(poster)}" alt="" loading="lazy" onerror="this.style.opacity=0.35">`
            : '<span class="theme-preview-cover-label">Cover art</span>';
        const beggarsBanquet = document?.player?.beggars_banquet !== false;
        const loginStatus = document?.player?.login_status === true;
        const coverReflection = document?.player?.cover_reflection !== false;
        let coverSize = String(document?.player?.cover_size || 'full').trim().toLowerCase();
        if (!['full', 'medium', 'half'].includes(coverSize)) {
            coverSize = 'full';
        }
        const coverScale = coverSize === 'half' ? 0.5 : (coverSize === 'medium' ? 0.75 : 1);
        const sideCovers = document?.player?.side_covers !== false;
        const sideOpacity = Math.max(5, Math.min(60, parseInt(String(document?.player?.side_covers_opacity ?? 20), 10) || 20));
        let sideColour = String(document?.player?.side_covers_colour || 'soft').trim().toLowerCase();
        if (sideColour === 'gray') {
            sideColour = 'grey';
        }
        if (!['full', 'soft', 'grey'].includes(sideColour)) {
            sideColour = 'soft';
        }
        let sideSpread = String(document?.player?.side_covers_spread || 'normal').trim().toLowerCase();
        if (!['close', 'normal', 'wide'].includes(sideSpread)) {
            sideSpread = 'normal';
        }
        const beggarsMarkup = beggarsBanquet
            ? `<div class="theme-preview-beggars-banquet" aria-hidden="true">
                    <span class="theme-preview-support-link">Support</span>
               </div>`
            : '';
        const loginStatusMarkup = loginStatus
            ? `<div class="theme-preview-user-status" aria-hidden="true">
                    <span>Signed in as <strong>listener</strong></span>
                    <span class="theme-preview-user-logout">Log out</span>
               </div>`
            : '';
        const reflectionMarkup = coverReflection
            ? `<div class="theme-preview-cover-reflection" aria-hidden="true">${coverMarkup}</div>`
            : '';
        const sideStyle = `--preview-side-opacity:${(sideOpacity / 100).toFixed(2)};`;
        const sideMarkup = sideCovers
            ? `<div class="theme-preview-side-card theme-preview-side-card--prev theme-preview-side-card--${escapeHtml(sideSpread)} theme-preview-side-card--${escapeHtml(sideColour)}" style="${sideStyle}" aria-hidden="true">${coverMarkup}</div>
               <div class="theme-preview-side-card theme-preview-side-card--next theme-preview-side-card--${escapeHtml(sideSpread)} theme-preview-side-card--${escapeHtml(sideColour)}" style="${sideStyle}" aria-hidden="true">${coverMarkup}</div>`
            : '';
        const backdrop = renderShellBackdrop(document);

        return `
            <div class="theme-preview-shell theme-preview-shell--player">
                ${backdrop.openTag}
                    ${backdrop.videoMarkup}
                    <div class="theme-preview-player-chrome" aria-hidden="true">
                        <div class="theme-preview-scene" style="--preview-cover-scale:${coverScale};">
                            ${sideMarkup}
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
                        ${loginStatusMarkup}
                        ${beggarsMarkup}
                    </div>
                </div>
            </div>`;
    }

    function renderContentPreviewChrome(document) {
        const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
        const logo = String(assets.logo || '').trim();
        const logoMarkup = logo
            ? `<img class="theme-preview-shell-logo" src="${escapeHtml(logo)}" alt="" loading="lazy" onerror="this.style.opacity=0.25">`
            : '<span class="theme-preview-muted">No logo assigned</span>';
        const backdrop = renderShellBackdrop(document);

        return `
            <div class="theme-preview-shell theme-preview-shell--content">
                ${backdrop.openTag}
                    ${backdrop.videoMarkup}
                    <div class="theme-preview-shell-header">
                        ${logoMarkup}
                    </div>
                    <section class="theme-preview-section theme-preview-section--content-chrome" data-preview-focus="content-chrome" aria-label="Buttons sample">
                        <p class="theme-preview-section-label">Buttons</p>
                        <div class="theme-preview-content-toggle" role="presentation">
                            <button type="button" class="theme-preview-nav-btn" tabindex="-1">Idle</button>
                            <button type="button" class="theme-preview-nav-btn theme-preview-nav-btn--hover" tabindex="-1">Hover</button>
                            <button type="button" class="theme-preview-nav-btn theme-preview-nav-btn--active" tabindex="-1">Active</button>
                        </div>
                    </section>
                    ${renderPlaylistSelectorPreview(document)}
                    <section class="theme-preview-section theme-preview-section--panel-sample" data-preview-focus="content-panels" aria-label="Panel sample">
                        <p class="theme-preview-section-label">Panels</p>
                        <div class="page-richtext theme-preview-panel page-box-panel">
                            <h1>Heading 1</h1>
                            <h2>Heading 2</h2>
                            <h3>Heading 3</h3>
                            <h4>Heading 4</h4>
                            <p>Paragraph with <strong>bold</strong> and <em>italic</em> emphasis, plus inline <code>code</code>.</p>
                            <p class="page-text-small">Small — secondary notes and fine print.</p>
                            <pre class="page-text-code">Code — monospace sample text</pre>
                            <blockquote>
                                <p>Cited text — a blockquote for call-outs and quotes.</p>
                            </blockquote>
                            <ul>
                                <li>Unordered list item</li>
                                <li>Another bullet</li>
                            </ul>
                            <ol>
                                <li>Ordered list item</li>
                                <li>Second step</li>
                            </ol>
                            <hr>
                            <p class="theme-preview-panel-links">
                                <a href="#" class="theme-preview-link" onclick="return false;">Default link</a>
                                <a href="#" class="theme-preview-link theme-preview-link--hover" onclick="return false;">Hover state</a>
                                <a href="#" class="theme-preview-link theme-preview-link--visited" onclick="return false;">Visited state</a>
                            </p>
                        </div>
                    </section>
                </div>
            </div>`;
    }

    function normalizePreviewMode(mode) {
        return String(mode || '').trim().toLowerCase() === 'content' ? 'content' : 'player';
    }

    function renderMarkup(document, mode) {
        if (!document) {
            return '<p class="brand-editor-empty">No brand selected.</p>';
        }
        return normalizePreviewMode(mode) === 'content'
            ? renderContentPreviewChrome(document)
            : renderPlayerPreviewChrome(document);
    }

    function render(container, document, options = {}) {
        if (!(container instanceof HTMLElement)) {
            return;
        }
        const styleId = String(options.styleId || 'bandpromo-shared-brand-preview-style');
        const selector = String(options.selector || `#${container.id} .theme-preview-shell-chrome`);
        const previewMode = normalizePreviewMode(options.mode);
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
        const legacyPanelDimRaw = tokenValue(document, 'effects.panel_dim');
        const legacyBlurRaw = tokenValue(document, 'effects.panel_blur');
        const playerDimRaw = tokenValue(document, 'effects.player_panel_dim') || legacyPanelDimRaw;
        const playerBlurRaw = tokenValue(document, 'effects.player_panel_blur') || legacyBlurRaw;
        const contentDimRaw = tokenValue(document, 'effects.content_panel_dim') || legacyPanelDimRaw;
        const contentBlurRaw = tokenValue(document, 'effects.content_panel_blur') || legacyBlurRaw;
        const dimParsed = parseInt(String(dimRaw), 10);
        const dim = Number.isFinite(dimParsed) ? Math.max(0, Math.min(100, dimParsed)) : 72;
        const playerPanelParsed = parseInt(String(playerDimRaw || dim), 10);
        const playerPanelDim = Number.isFinite(playerPanelParsed)
            ? Math.max(0, Math.min(100, playerPanelParsed))
            : dim;
        const contentPanelParsed = parseInt(String(contentDimRaw || dim), 10);
        const contentPanelDim = Number.isFinite(contentPanelParsed)
            ? Math.max(0, Math.min(100, contentPanelParsed))
            : dim;
        const playerBlurParsed = parseInt(String(playerBlurRaw), 10);
        const playerBlur = Number.isFinite(playerBlurParsed) ? Math.max(0, Math.min(24, playerBlurParsed)) : 5;
        const contentBlurParsed = parseInt(String(contentBlurRaw), 10);
        const contentBlur = Number.isFinite(contentBlurParsed) ? Math.max(0, Math.min(24, contentBlurParsed)) : 5;
        rules.push(`--shell-scrim-strength:${(dim / 100).toFixed(2)}`);
        rules.push(`--player-panel-scrim-strength:${(playerPanelDim / 100).toFixed(2)}`);
        rules.push(`--player-panel-blur:${playerBlur}px`);
        rules.push(`--player-panel-fill:color-mix(in srgb, var(--color-surface-mid) ${playerPanelDim}%, transparent)`);

        let playerDensityRaw = String(tokenValue(document, 'effects.player_panel_density') || 'normal').toLowerCase();
        if (playerDensityRaw === 'minimal') {
            playerDensityRaw = 'dense';
        }
        let playerPadY = 12;
        let playerPadX = 14;
        let playerGap = 8;
        if (playerDensityRaw === 'dense') {
            playerPadY = 6;
            playerPadX = 8;
            playerGap = 4;
        } else if (playerDensityRaw === 'compact') {
            playerPadY = 8;
            playerPadX = 10;
            playerGap = 6;
        } else if (playerDensityRaw === 'comfortable') {
            playerPadY = 16;
            playerPadX = 20;
            playerGap = 12;
        } else if (playerDensityRaw === 'spacious') {
            playerPadY = 22;
            playerPadX = 28;
            playerGap = 16;
        }
        let playerCorners = String(tokenValue(document, 'effects.player_panel_corners') || 'shaved').trim().toLowerCase();
        if (playerCorners === 'pill') {
            playerCorners = 'shaved';
        }
        if (!['square', 'shaved'].includes(playerCorners)) {
            playerCorners = 'shaved';
        }
        const playerRadius = playerCorners === 'square' ? 0 : 10;
        let playerBorderPreset = String(tokenValue(document, 'effects.player_panel_border') || 'none').trim().toLowerCase();
        if (!['none', 'thin', 'normal', 'fat'].includes(playerBorderPreset)) {
            playerBorderPreset = 'none';
        }
        const playerBorderWidth = playerBorderPreset === 'none'
            ? 0
            : (playerBorderPreset === 'thin' ? 1 : (playerBorderPreset === 'fat' ? 3 : 2));
        rules.push(`--player-panel-pad-y:${playerPadY}px`);
        rules.push(`--player-panel-pad-x:${playerPadX}px`);
        rules.push(`--player-panel-gap:${playerGap}px`);
        rules.push(`--player-panel-radius:${playerRadius}px`);
        rules.push(`--player-panel-border-width:${playerBorderWidth}px`);
        rules.push(`--player-panel-border:${playerBorderWidth > 0 ? 'var(--role-player-panel-border)' : 'transparent'}`);

        rules.push(`--content-panel-scrim-strength:${(contentPanelDim / 100).toFixed(2)}`);
        rules.push(`--content-panel-blur:${contentBlur}px`);
        rules.push(`--content-panel-fill:color-mix(in srgb, var(--color-surface-mid) ${contentPanelDim}%, transparent)`);
        rules.push(`--panel-scrim-strength:${(contentPanelDim / 100).toFixed(2)}`);
        rules.push(`--panel-blur:${contentBlur}px`);
        rules.push(`--panel-fill:color-mix(in srgb, var(--color-surface-mid) ${contentPanelDim}%, transparent)`);
        rules.push('--primary-a15:color-mix(in srgb, var(--primary-color) 15%, transparent)');
        rules.push('--primary-a20:color-mix(in srgb, var(--primary-color) 20%, transparent)');
        rules.push('--primary-a30:color-mix(in srgb, var(--primary-color) 30%, transparent)');
        rules.push('--primary-a50:color-mix(in srgb, var(--primary-color) 50%, transparent)');

        const ROLE_DEFAULTS = {
            heading: 'primary',
            heading_sub: 'secondary',
            body: 'text',
            muted: 'text_muted',
            button_outline: 'primary',
            button_fill: 'primary',
            button_active: 'primary',
            panel_border: 'primary',
            player_panel_border: 'primary',
            blockquote: 'primary',
        };
        const ROLE_CSS = {
            primary: 'var(--primary-color)',
            secondary: 'var(--secondary-color)',
            text: 'var(--text-color)',
            text_muted: 'var(--color-text-muted)',
            surface_mid: 'var(--color-surface-mid)',
        };
        function roleCssRef(roleKey, fallback) {
            let key = String(tokenValue(document, `roles.${roleKey}`) || fallback || 'primary').trim().toLowerCase();
            if (!ROLE_CSS[key]) {
                key = fallback || 'primary';
            }
            return ROLE_CSS[key] || ROLE_CSS.primary;
        }
        Object.keys(ROLE_DEFAULTS).forEach((role) => {
            const cssName = `--role-${role.replace(/_/g, '-')}`;
            rules.push(`${cssName}:${roleCssRef(role, ROLE_DEFAULTS[role])}`);
        });

        const contentStyle = String(tokenValue(document, 'content.style') || 'outline').toLowerCase();
        let corners = String(tokenValue(document, 'content.corners') || '').trim().toLowerCase();
        if (!['square', 'shaved', 'pill'].includes(corners)) {
            const radiusParsed = parseInt(String(tokenValue(document, 'content.radius_percent') || '50'), 10);
            const radiusLegacy = Number.isFinite(radiusParsed) ? radiusParsed : 50;
            corners = radiusLegacy <= 8 ? 'square' : (radiusLegacy <= 30 ? 'shaved' : 'pill');
        }
        const radius = corners === 'square' ? 0 : (corners === 'shaved' ? 6 : 999);
        let borderPreset = String(tokenValue(document, 'content.border') || '').trim().toLowerCase();
        if (!['thin', 'normal', 'fat'].includes(borderPreset)) {
            const borderParsed = parseInt(String(tokenValue(document, 'content.border_width') || '2'), 10);
            const widthLegacy = Number.isFinite(borderParsed) ? borderParsed : 2;
            borderPreset = widthLegacy <= 1 ? 'thin' : (widthLegacy >= 3 ? 'fat' : 'normal');
        }
        const borderWidth = borderPreset === 'thin' ? 1 : (borderPreset === 'fat' ? 3 : 2);
        let densityRaw = String(tokenValue(document, 'content.density') || 'normal').toLowerCase();
        if (densityRaw === 'minimal') {
            densityRaw = 'dense';
        }
        let densityScale = 1.2;
        if (densityRaw === 'dense') {
            densityScale = 1;
        } else if (densityRaw === 'compact') {
            densityScale = 1.1;
        } else if (densityRaw === 'comfortable') {
            densityScale = 1.3;
        } else if (densityRaw === 'spacious') {
            densityScale = 1.4;
        }
        // Uniform padding Dense→Spacious: 2px → 8px (same five steps as Typography).
        const pad = Math.max(2, Math.min(8, Math.round(2 + (densityScale - 1) * 15)));
        const padY = pad;
        const padX = pad;
        const minHeight = 20 + (2 * pad);
        const gap = pad;
        const outline = 'var(--role-button-outline)';
        const fill = 'var(--role-button-fill)';
        const active = 'var(--role-button-active)';
        let bg = 'transparent';
        let border = outline;
        let fg = outline;
        let bgHover = active;
        let fgHover = '#000000';
        if (contentStyle === 'filled' || contentStyle === 'solid') {
            bg = fill;
            border = fill;
            fg = '#000000';
            bgHover = `color-mix(in srgb, ${active} 88%, white)`;
            fgHover = '#000000';
        } else if (contentStyle === 'soft') {
            bg = `color-mix(in srgb, ${fill} 50%, transparent)`;
            border = outline;
            fg = 'var(--role-muted)';
            bgHover = `color-mix(in srgb, ${active} 72%, transparent)`;
            fgHover = 'var(--role-body)';
        }
        rules.push(`--content-control-radius:${radius}px`);
        rules.push(`--content-control-border-width:${borderWidth}px`);
        rules.push(`--content-control-pad-y:${padY}px`);
        rules.push(`--content-control-pad-x:${padX}px`);
        rules.push(`--content-control-min-height:${minHeight}px`);
        rules.push(`--content-control-gap:${gap}px`);
        rules.push(`--content-control-bg:${bg}`);
        rules.push(`--content-control-border:${border}`);
        rules.push(`--content-control-fg:${fg}`);
        rules.push(`--content-control-bg-hover:${bgHover}`);
        rules.push(`--content-control-fg-hover:${fgHover}`);
        rules.push(`--content-control-bg-active:${active}`);
        rules.push(`--content-control-border-active:${active}`);
        rules.push('--content-control-fg-active:#000000');
        rules.push(`--content-control-glow-active:color-mix(in srgb, ${active} 55%, transparent)`);

        let panelDensityRaw = String(tokenValue(document, 'effects.content_panel_density') || 'normal').toLowerCase();
        if (panelDensityRaw === 'minimal') {
            panelDensityRaw = 'dense';
        }
        let panelPadY = 12;
        let panelPadX = 14;
        let panelGap = 8;
        if (panelDensityRaw === 'dense') {
            panelPadY = 6;
            panelPadX = 8;
            panelGap = 4;
        } else if (panelDensityRaw === 'compact') {
            panelPadY = 8;
            panelPadX = 10;
            panelGap = 6;
        } else if (panelDensityRaw === 'comfortable') {
            panelPadY = 16;
            panelPadX = 20;
            panelGap = 12;
        } else if (panelDensityRaw === 'spacious') {
            panelPadY = 22;
            panelPadX = 28;
            panelGap = 16;
        }
        rules.push(`--content-panel-pad-y:${panelPadY}px`);
        rules.push(`--content-panel-pad-x:${panelPadX}px`);
        rules.push(`--content-panel-gap:${panelGap}px`);

        let panelCorners = String(tokenValue(document, 'effects.content_panel_corners') || 'shaved').trim().toLowerCase();
        if (panelCorners === 'pill') {
            panelCorners = 'shaved';
        }
        if (!['square', 'shaved'].includes(panelCorners)) {
            panelCorners = 'shaved';
        }
        const panelRadius = panelCorners === 'square' ? 0 : 10;
        let panelBorderPreset = String(tokenValue(document, 'effects.content_panel_border') || 'none').trim().toLowerCase();
        if (!['none', 'thin', 'normal', 'fat'].includes(panelBorderPreset)) {
            panelBorderPreset = 'none';
        }
        const panelBorderWidth = panelBorderPreset === 'none'
            ? 0
            : (panelBorderPreset === 'thin' ? 1 : (panelBorderPreset === 'fat' ? 3 : 2));
        rules.push(`--content-panel-radius:${panelRadius}px`);
        rules.push(`--content-panel-border-width:${panelBorderWidth}px`);
        rules.push(`--content-panel-border:${panelBorderWidth > 0 ? 'var(--role-panel-border)' : 'transparent'}`);

        let typeDensityRaw = String(tokenValue(document, 'typography.density') || 'normal').toLowerCase();
        if (typeDensityRaw === 'minimal') {
            typeDensityRaw = 'dense';
        }
        let typeLineHeight = '1.2';
        let typeBlockGap = 6;
        if (typeDensityRaw === 'dense') {
            typeLineHeight = '1';
            typeBlockGap = 2;
        } else if (typeDensityRaw === 'compact') {
            typeLineHeight = '1.1';
            typeBlockGap = 4;
        } else if (typeDensityRaw === 'comfortable') {
            typeLineHeight = '1.3';
            typeBlockGap = 10;
        } else if (typeDensityRaw === 'spacious') {
            typeLineHeight = '1.4';
            typeBlockGap = 14;
        }
        rules.push(`--content-type-line-height:${typeLineHeight}`);
        rules.push(`--content-type-block-gap:${typeBlockGap}px`);

        style.textContent = rules.length ? `${selector}{${rules.join(';')};}` : '';
        container.innerHTML = renderMarkup(document, previewMode);
        container.dataset.previewMode = previewMode;
        startPreviewVideos(container);
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
            <section class="theme-preview-section theme-preview-section--playlist-selector" data-preview-focus="playlist-selector" aria-label="Playlist selector sample">
                <p class="theme-preview-section-label">Playlist selector</p>
                <div class="theme-preview-playlist-selector theme-preview-playlist-selector--${escapeHtml(mode)}">
                    ${body}
                </div>
            </section>`;
    }

    function startPreviewVideos(root) {
        if (!(root instanceof HTMLElement)) {
            return;
        }
        root.querySelectorAll('video.theme-preview-shell-video, video.brand-shell-slot-thumb').forEach((video) => {
            video.muted = true;
            video.loop = true;
            video.playsInline = true;
            const play = () => {
                const attempt = video.play();
                if (attempt && typeof attempt.catch === 'function') {
                    attempt.catch(() => {});
                }
            };
            if (video.readyState >= 2) {
                play();
                return;
            }
            video.addEventListener('canplay', play, { once: true });
            try {
                video.load();
            } catch (error) {}
            play();
        });
    }

    window.bandpromoBrandPreview = {
        render,
        renderMarkup,
        startVideos: startPreviewVideos,
    };
    // Legacy alias used by campaign branding preview.
    window.bandpromoThemePreview = window.bandpromoBrandPreview;
}());
