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
        return typeof value === 'string' ? value.trim() : '';
    }

    function renderShellPreviewChrome(document) {
        const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
        const logo = String(assets.logo || '').trim();
        const background = String(assets.background_image || '').trim();
        const backgroundAttribute = background
            ? ` style="background-image:url('${escapeHtml(background)}');"`
            : '';
        const logoMarkup = logo
            ? `<img class="theme-preview-shell-logo" src="${escapeHtml(logo)}" alt="" loading="lazy" onerror="this.style.opacity=0.25">`
            : '<span class="theme-preview-muted">No logo assigned</span>';

        return `
            <section class="theme-preview-section theme-preview-section--shell">
                <h3 class="theme-preview-section-title">Shell</h3>
                <p class="theme-preview-muted theme-preview-section-lead">Logo and still backdrop as assigned in Shell media.</p>
                <div class="theme-preview-shell-chrome"${backgroundAttribute}>
                    ${logoMarkup}
                </div>
            </section>`;
    }

    function renderMarkup(document) {
        if (!document) {
            return '<p class="theme-editor-empty">No brand selected.</p>';
        }

        return `
            <div class="theme-preview-canvas">
                <div class="theme-preview-shell">
                    ${renderShellPreviewChrome(document)}

                    <section class="theme-preview-section">
                        <h3 class="theme-preview-section-title">Page text styles</h3>
                        <p class="theme-preview-muted theme-preview-section-lead">Styles available in the page editor (+ Text block).</p>
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
                        <h3 class="theme-preview-section-title">Media player</h3>
                        <p class="theme-preview-muted theme-preview-section-lead">Player layout and cover art size follow screen breakpoints; this sample shows brand colors on the listening area.</p>
                        <div class="theme-preview-player">
                            <div class="theme-preview-cover" aria-hidden="true">
                                <span class="theme-preview-cover-label">Cover art</span>
                            </div>
                            <div class="theme-preview-track-card">
                                <span class="theme-preview-track-title">Track title</span>
                                <span class="theme-preview-muted">Artist name</span>
                            </div>
                        </div>
                    </section>

                    <section class="theme-preview-section">
                        <h3 class="theme-preview-section-title">Buttons &amp; tabs</h3>
                        <div class="theme-preview-controls">
                            <button type="button" class="theme-preview-btn theme-preview-btn--primary">Primary action</button>
                            <button type="button" class="theme-preview-btn theme-preview-btn--secondary">Secondary</button>
                            <span class="theme-preview-tab theme-preview-tab--active">Active tab</span>
                            <span class="theme-preview-tab">Tab</span>
                        </div>
                    </section>

                    <section class="theme-preview-section">
                        <h3 class="theme-preview-section-title">Surfaces</h3>
                        <div class="theme-preview-surfaces">
                            <div class="theme-preview-surface theme-preview-surface--mid">
                                <strong>Panels</strong>
                                <span>Cards, blocks, and elevated UI areas.</span>
                            </div>
                            <div class="theme-preview-surface theme-preview-surface--deep">
                                <strong>Deep background</strong>
                                <span>Backdrop behind the main page content.</span>
                            </div>
                        </div>
                    </section>

                    <section class="theme-preview-section">
                        <h3 class="theme-preview-section-title">Links</h3>
                        <p class="theme-preview-links">
                            <a href="#" class="theme-preview-link" onclick="return false;">Default link</a>
                            <a href="#" class="theme-preview-link theme-preview-link--hover" onclick="return false;">Hover state</a>
                            <a href="#" class="theme-preview-link theme-preview-link--visited" onclick="return false;">Visited state</a>
                        </p>
                    </section>
                </div>
            </div>`;
    }

    function render(container, document, options = {}) {
        if (!(container instanceof HTMLElement)) {
            return;
        }
        const styleId = String(options.styleId || 'bandpromo-shared-theme-preview-style');
        const selector = String(options.selector || `#${container.id} .theme-preview-canvas`);
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

        style.textContent = rules.length ? `${selector}{${rules.join(';')};}` : '';
        container.innerHTML = renderMarkup(document);
    }

    window.bandpromoThemePreview = {
        render,
        renderMarkup,
    };
}());
