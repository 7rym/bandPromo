(function () {
    function initBandpromoThemeEditor() {
        const root = document.getElementById('themeEditorRoot');
        const poolView = document.getElementById('themePoolView');
        const editorView = document.getElementById('themeEditorView');
        const poolList = document.getElementById('themePoolList');
        const formEl = document.getElementById('themeEditorForm');
        const previewEl = document.getElementById('themeEditorPreview');
        const editorHint = document.getElementById('themeEditorHint');
        const saveBtn = document.getElementById('themeSaveBtn');
        const setActiveBtn = document.getElementById('themeSetActiveBtn');
        const backBtn = document.getElementById('themeEditorBackBtn');
        const titleInput = document.getElementById('themeSettingsTitle');
        const settingsStatus = document.getElementById('themeSettingsStatus');
        const headBadges = document.getElementById('themeEditorHeadBadges');
        const registryStatus = document.getElementById('themeRegistryStatus');
        const deleteModal = document.getElementById('themeDeleteModal');
        const deleteModalName = document.getElementById('themeDeleteModalName');
        const deleteConfirmBtn = document.getElementById('themeDeleteConfirmBtn');
        const deleteCancelBtn = document.getElementById('themeDeleteCancelBtn');
        if (!root || !poolList || !formEl || !previewEl) {
            return;
        }

        const COLOR_FIELDS = [
            ['primary', 'Primary accent'],
            ['secondary', 'Secondary accent'],
            ['background', 'Page background'],
            ['text', 'Main text'],
            ['text_muted', 'Muted text'],
            ['surface_mid', 'Panels'],
            ['surface_deep', 'Deep background'],
            ['link', 'Links'],
            ['link_hover', 'Link hover'],
            ['link_visited', 'Visited links'],
        ];

        const FONT_PRESETS = [
            { id: 'segoe', label: 'Segoe UI (recommended)', value: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" },
            { id: 'system', label: 'System default', value: "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" },
            { id: 'arial', label: 'Arial / Helvetica', value: 'Arial, Helvetica, sans-serif' },
            { id: 'georgia', label: 'Georgia (serif)', value: "Georgia, 'Times New Roman', serif" },
        ];

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

        let themes = [];
        let activeThemeId = '';
        let selectedThemeId = String(root.dataset.initialTheme || 'setup-default');
        let previewDocument = null;
        let editorDocument = null;
        let isEditing = false;
        let themeSettingsBaseline = { title: '' };
        let themeSettingsSaving = false;
        let pendingThemeDeleteId = '';
        let previewStyleEl = document.getElementById('bandpromo-theme-editor-preview-style');

        if (!previewStyleEl) {
            previewStyleEl = document.createElement('style');
            previewStyleEl.id = 'bandpromo-theme-editor-preview-style';
            document.head.appendChild(previewStyleEl);
        }

        const saveUi = window.bandpromoContentSaveUi?.create(saveBtn, {
            saveLabel: '💾 Save brand',
            readFingerprint() {
                return JSON.stringify({
                    tokens: editorDocument?.tokens || previewDocument?.tokens || {},
                    mood: editorDocument?.mood || '',
                    keywords: editorDocument?.keywords || [],
                    tone_notes: editorDocument?.tone_notes || '',
                    assets: editorDocument?.assets || previewDocument?.assets || {},
                });
            },
        }) || null;

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function showThemeToast(message, type = 'warning') {
            const text = String(message || '').trim();
            if (!text) {
                return;
            }
            if (typeof window.bandpromoShowAdminToast === 'function') {
                window.bandpromoShowAdminToast(text, type);
                return;
            }

            const toastHost = document.getElementById('adminToastHost');
            if (!toastHost) {
                return;
            }

            const kind = String(type || 'warning').trim().toLowerCase() || 'warning';
            const toast = document.createElement('div');
            toast.className = `admin-toast ${kind}`;
            toast.setAttribute('role', kind === 'error' || kind === 'warning' ? 'alert' : 'status');

            const messageEl = document.createElement('div');
            messageEl.className = 'admin-toast-message';
            messageEl.textContent = text;
            toast.appendChild(messageEl);

            const isSticky = kind === 'warning' || kind === 'error';
            let hideTimer = null;
            const dismissToast = () => {
                if (hideTimer) {
                    window.clearTimeout(hideTimer);
                    hideTimer = null;
                }
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-4px)';
                toast.style.transition = 'opacity 150ms ease, transform 150ms ease';
                window.setTimeout(() => toast.remove(), 180);
            };
            if (isSticky) {
                const dismissBtn = document.createElement('button');
                dismissBtn.type = 'button';
                dismissBtn.className = 'admin-toast-dismiss';
                dismissBtn.setAttribute('aria-label', 'Dismiss notification');
                dismissBtn.textContent = '×';
                dismissBtn.addEventListener('click', dismissToast);
                toast.appendChild(dismissBtn);
            }
            toastHost.appendChild(toast);
            hideTimer = window.setTimeout(
                dismissToast,
                isSticky ? Math.min(20000, Math.max(10000, 80 * text.length)) : 4500
            );
        }

        function notifyThemeError(message) {
            const text = String(message || '').replace(/^❌\s*/, '').trim();
            if (!text) {
                return;
            }
            showThemeToast(text, 'warning');
        }

        function syncThemeUrl(themeId, editing = isEditing) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'content');
            url.searchParams.set('cntab', 'themes');
            url.searchParams.set('theme', themeId);
            if (editing) {
                url.searchParams.set('edit', '1');
            } else {
                url.searchParams.delete('edit');
            }
            window.history.replaceState({}, '', url.toString());
        }

        async function fetchJson(url, options) {
            const resp = await fetch(url, Object.assign({ credentials: 'same-origin' }, options || {}));
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || data.ok === false) {
                throw new Error(data.error || 'Request failed');
            }
            return data;
        }

        function cloneDocument(document) {
            return document ? JSON.parse(JSON.stringify(document)) : null;
        }

        function narrativeKeywordsString(document) {
            const keywords = document?.keywords;
            if (!Array.isArray(keywords)) {
                return '';
            }
            return keywords.map((item) => String(item || '').trim()).filter(Boolean).join(', ');
        }

        function parseKeywordsInput(value) {
            return String(value || '')
                .split(/[,;\n]+/)
                .map((item) => item.trim())
                .filter(Boolean);
        }

        function collectNarrativeFields() {
            if (!editorDocument || editorDocument.locked) {
                return;
            }
            formEl.querySelectorAll('[data-narrative-field]').forEach((input) => {
                const field = input.getAttribute('data-narrative-field') || '';
                if (!field) {
                    return;
                }
                if (field === 'keywords') {
                    editorDocument.keywords = parseKeywordsInput(input.value);
                    return;
                }
                editorDocument[field] = String(input.value || '').trim();
            });
        }

        function renderNarrativeFields(locked) {
            const mood = escapeHtml(String(editorDocument?.mood || ''));
            const keywords = escapeHtml(narrativeKeywordsString(editorDocument));
            const toneNotes = escapeHtml(String(editorDocument?.tone_notes || ''));
            const disabled = locked ? 'disabled' : '';

            return `
                <div class="theme-editor-section">
                    <h5>Brand narrative</h5>
                    <p class="theme-field-hint">Canon for this visual era — mood, keywords, and tone notes for content AI wizards and operator reference.</p>
                    <div class="theme-token-grid theme-token-grid--stacked">
                        <div class="theme-token-field">
                            <label for="brand-mood">Mood</label>
                            <input type="text" id="brand-mood" data-narrative-field="mood" maxlength="500" value="${mood}" placeholder="e.g. Gritty winter club energy" ${disabled}>
                        </div>
                        <div class="theme-token-field">
                            <label for="brand-keywords">Keywords</label>
                            <input type="text" id="brand-keywords" data-narrative-field="keywords" value="${keywords}" placeholder="e.g. electronic, neon, dance, party" ${disabled}>
                            <p class="theme-field-hint">Comma-separated tags describing this brand era.</p>
                        </div>
                        <div class="theme-token-field">
                            <label for="brand-tone-notes">Tone notes</label>
                            <textarea id="brand-tone-notes" class="theme-narrative-textarea" data-narrative-field="tone_notes" maxlength="2000" rows="4" placeholder="Voice, attitude, and copy guidance for this era." ${disabled}>${toneNotes}</textarea>
                        </div>
                    </div>
                </div>
            `;
        }

        function tokenValue(document, path) {
            if (!document || !document.tokens) return '';
            const segments = path.split('.');
            let value = document.tokens;
            for (const segment of segments) {
                if (!value || typeof value !== 'object' || !(segment in value)) {
                    return '';
                }
                value = value[segment];
            }
            return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
        }

        function setTokenValue(document, path, value) {
            if (!document) return;
            const segments = path.split('.');
            let node = document.tokens;
            for (let i = 0; i < segments.length - 1; i += 1) {
                const key = segments[i];
                if (!node[key] || typeof node[key] !== 'object') {
                    node[key] = {};
                }
                node = node[key];
            }
            node[segments[segments.length - 1]] = value;
        }

        function normalizeFontValue(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function fontsEqual(a, b) {
            return normalizeFontValue(a) === normalizeFontValue(b);
        }

        function fontPresetIdForValue(value, allowSame = false) {
            if (allowSame && !normalizeFontValue(value)) {
                return '__same__';
            }
            const match = FONT_PRESETS.find((preset) => fontsEqual(preset.value, value));
            return match ? match.id : '__custom__';
        }

        function renderFontPresetSelect(kind, currentValue, locked) {
            const isHeading = kind === 'heading';
            const presetId = fontPresetIdForValue(currentValue, isHeading);
            const customVisible = presetId === '__custom__';
            const options = [];
            if (isHeading) {
                options.push(`<option value="__same__"${presetId === '__same__' ? ' selected' : ''}>Same as main font</option>`);
            }
            FONT_PRESETS.forEach((preset) => {
                const selected = presetId === preset.id ? ' selected' : '';
                options.push(`<option value="${escapeHtml(preset.id)}"${selected}>${escapeHtml(preset.label)}</option>`);
            });
            options.push(`<option value="__custom__"${presetId === '__custom__' ? ' selected' : ''}>Custom…</option>`);
            const tokenPath = isHeading ? 'typography.font_family_heading' : 'typography.font_family_base';
            const label = isHeading ? 'Heading font' : 'Main font';
            const hint = isHeading
                ? 'Used for page headings. Choose Same as main font unless you want headings to stand out.'
                : 'Used on pages, the player, login screens, and most site text.';
            return `
                <div class="theme-token-field theme-token-field--preset">
                    <label for="theme-font-preset-${kind}">${label}</label>
                    <select id="theme-font-preset-${kind}" data-font-preset-select="${kind}" ${locked ? 'disabled' : ''}>${options.join('')}</select>
                    <input type="text" class="theme-custom-token-input" id="theme-font-custom-${kind}" data-token-path="${tokenPath}" value="${escapeHtml(currentValue)}" placeholder="e.g. Georgia, serif" ${locked || !customVisible ? 'hidden' : ''} ${locked ? 'disabled' : ''}>
                    <p class="theme-field-hint">${hint}</p>
                </div>
            `;
        }

        function normalizeHexColor(value) {
            const raw = String(value || '').trim();
            if (/^#[0-9a-fA-F]{6}$/.test(raw)) {
                return raw.toLowerCase();
            }
            if (/^#[0-9a-fA-F]{3}$/.test(raw)) {
                return `#${raw[1]}${raw[1]}${raw[2]}${raw[2]}${raw[3]}${raw[3]}`.toLowerCase();
            }
            return '';
        }

        function syncColorChipPresentation(chip) {
            if (!(chip instanceof HTMLElement)) {
                return;
            }
            const hexInput = chip.querySelector('input.theme-color-hex-input');
            const picker = chip.querySelector('input.theme-color-picker');
            const controls = chip.querySelector('.theme-color-controls');
            if (!(hexInput instanceof HTMLInputElement)) {
                return;
            }
            const hex = normalizeHexColor(hexInput.value) || normalizeHexColor(picker?.value) || '#000000';
            hexInput.value = hex.toUpperCase();
            hexInput.classList.remove('is-invalid');
            if (picker instanceof HTMLInputElement) {
                picker.value = hex;
            }
            if (controls instanceof HTMLElement) {
                controls.style.setProperty('--theme-swatch-color', hex);
            }
            const label = chip.querySelector('.theme-color-label');
            const labelText = label ? String(label.textContent || '').trim() : 'Color';
            chip.title = `${labelText}: ${hex.toUpperCase()}`;
        }

        function renderCompactColors(locked) {
            return `<div class="theme-color-compact-grid">${COLOR_FIELDS.map(([key, label]) => {
                const value = normalizeHexColor(tokenValue(editorDocument, `color.${key}`) || '#000000') || '#000000';
                return `<label class="theme-color-chip" title="${escapeHtml(label)}: ${escapeHtml(value.toUpperCase())}">
                    <span class="theme-color-label">${escapeHtml(label)}</span>
                    <span class="theme-color-controls" style="--theme-swatch-color:${escapeHtml(value)}">
                        <input type="text" class="theme-color-hex-input" data-token-path="color.${key}" value="${escapeHtml(value.toUpperCase())}" maxlength="7" spellcheck="false" autocomplete="off" inputmode="text" aria-label="${escapeHtml(label)} hex" ${locked ? 'disabled' : ''}>
                        <input type="color" class="theme-color-picker" value="${escapeHtml(value)}" tabindex="-1" aria-label="${escapeHtml(label)} color picker" title="Open color picker" ${locked ? 'disabled' : ''}>
                    </span>
                </label>`;
            }).join('')}</div>`;
        }

        function themeTitleValue() {
            return titleInput instanceof HTMLInputElement
                ? String(titleInput.value || '').trim()
                : '';
        }

        function themeSettingsDirty() {
            return themeTitleValue() !== themeSettingsBaseline.title;
        }

        function renderThemeHeadBadges(document) {
            if (!headBadges || !document) {
                return;
            }
            const isActive = document.id === activeThemeId;
            const locked = !!document.locked;
            const badges = [];
            if (isActive) {
                badges.push('<span class="theme-editor-badge theme-editor-badge--active">Active</span>');
            }
            if (locked) {
                badges.push('<span class="theme-editor-badge theme-editor-badge--locked">Locked</span>');
            }
            headBadges.innerHTML = badges.join('');
        }

        function syncThemeSettingsPanel(document) {
            const title = String(document?.title || document?.id || '');
            const locked = !!document?.locked;
            themeSettingsBaseline = { title };
            if (titleInput instanceof HTMLInputElement) {
                titleInput.value = title;
                titleInput.disabled = locked;
            }
            renderThemeHeadBadges(document);
            if (settingsStatus) {
                settingsStatus.textContent = '';
            }
        }

        async function saveThemeSettings({ silent = false } = {}) {
            if (themeSettingsSaving || !editorDocument || editorDocument.locked) {
                return true;
            }
            if (!(titleInput instanceof HTMLInputElement)) {
                return true;
            }

            const title = themeTitleValue();
            if (!title) {
                if (!silent && settingsStatus) {
                    settingsStatus.textContent = 'Brand name is required.';
                }
                return false;
            }

            if (!themeSettingsDirty()) {
                if (!silent && settingsStatus) {
                    settingsStatus.textContent = '';
                }
                return true;
            }

            themeSettingsSaving = true;
            if (!silent && settingsStatus) {
                settingsStatus.textContent = 'Saving…';
            }

            try {
                const data = await fetchJson(`/biblioteca/manage-theme.php?theme=${encodeURIComponent(editorDocument.id)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ title }),
                });
                editorDocument.title = title;
                if (previewDocument) {
                    previewDocument.title = title;
                }
                themes = Array.isArray(data.themes) ? data.themes : themes;
                themeSettingsBaseline = { title };
                renderPoolList();
                renderPreview(previewDocument);
                if (!silent && settingsStatus) {
                    settingsStatus.textContent = 'Saved.';
                }
                return true;
            } catch (error) {
                if (!silent && settingsStatus) {
                    settingsStatus.textContent = error.message || 'Could not save brand name';
                }
                return false;
            } finally {
                themeSettingsSaving = false;
            }
        }

        function applyFontPresetSelection(kind, presetKey) {
            if (!editorDocument || editorDocument.locked) return;
            const customInput = formEl.querySelector(`#theme-font-custom-${kind}`);
            const path = kind === 'heading' ? 'typography.font_family_heading' : 'typography.font_family_base';

            if (presetKey === '__custom__') {
                if (customInput instanceof HTMLInputElement) {
                    customInput.hidden = false;
                    customInput.focus();
                }
                return;
            }

            if (customInput instanceof HTMLInputElement) {
                customInput.hidden = true;
            }

            if (presetKey === '__same__') {
                setTokenValue(editorDocument, path, '');
            } else {
                const preset = FONT_PRESETS.find((entry) => entry.id === presetKey);
                if (preset) {
                    setTokenValue(editorDocument, path, preset.value);
                }
            }
            collectFormIntoDocument();
        }

        function applyPreviewStyles(document) {
            const rules = [];
            Object.entries(CSS_VAR_MAP).forEach(([tokenPath, cssVar]) => {
                const value = tokenValue(document, tokenPath);
                if (value) {
                    rules.push(`${cssVar}:${value}`);
                }
            });
            const fontBase = tokenValue(document, 'typography.font_family_base');
            const fontHeading = tokenValue(document, 'typography.font_family_heading');
            if (fontBase) {
                rules.push(`--theme-body-font:${fontBase}`);
            }
            if (fontHeading) {
                rules.push(`--theme-heading-font:${fontHeading}`);
            } else if (fontBase) {
                rules.push(`--theme-heading-font:${fontBase}`);
            }
            previewStyleEl.textContent = rules.length
                ? `#themeEditorPreview .theme-preview-canvas{${rules.join(';')};}`
                : '';
        }

        function assetBasename(path) {
            const raw = String(path || '').trim().replace(/\\/g, '/');
            if (!raw) return '';
            const parts = raw.split('/');
            return parts[parts.length - 1] || raw;
        }

        function mediaKindFromPath(path) {
            const name = assetBasename(path).toLowerCase();
            if (/\.(png|jpe?g|gif|webp|svg|bmp|avif)$/i.test(name)) return 'image';
            if (/\.(mp4|webm|mov|m4v|ogv)$/i.test(name)) return 'video';
            if (/\.(mp3|flac|wav|ogg|m4a|aac|aiff?)$/i.test(name)) return 'audio';
            return 'other';
        }

        function mediaKindFromFile(file) {
            const declared = String(file?.media_type || '').trim();
            if (declared === 'image' || declared === 'video' || declared === 'audio') {
                return declared;
            }
            return mediaKindFromPath(file?.name || '');
        }

        function kindLabel(kind) {
            if (kind === 'image') return 'Still';
            if (kind === 'video') return 'Living';
            if (kind === 'audio') return 'Audio';
            return 'File';
        }

        function specialMediaUrl(pathOrName) {
            const name = assetBasename(pathOrName);
            if (!name) return '';
            return `/media/special/${encodeURIComponent(name)}`;
        }

        function specialMediaPath(pathOrName) {
            const name = assetBasename(pathOrName);
            if (!name) return '';
            return `/media/special/${name}`;
        }

        function visualIntakeBasePath(bucket) {
            if (bucket === 'video') return '/media/video/original';
            if (bucket === 'photos') return '/media/photo/original';
            return '/media/img/original';
        }

        function poolFilePath(file) {
            const source = String(file?.pool_source || 'special');
            const name = assetBasename(file?.name || '');
            if (!name) return '';
            // Prefer delivery URLs when Publish has produced them.
            const cardUrl = String(file?.card_url || '').trim();
            const streamUrl = String(file?.stream_url || '').trim();
            const kind = mediaKindFromFile(file);
            if (kind === 'video' && streamUrl) {
                return streamUrl;
            }
            if (kind === 'image' && cardUrl) {
                return cardUrl;
            }
            if (source === 'sfx') {
                return `/media/sfx/original/${name}`;
            }
            if (source === 'special') {
                return specialMediaPath(name);
            }
            const bucket = String(file?.intake_bucket || (kind === 'video' ? 'video' : 'illustrations'));
            return `${visualIntakeBasePath(bucket)}/${name}`;
        }

        function poolFileThumbUrl(file) {
            const kind = mediaKindFromFile(file);
            const source = String(file?.pool_source || 'special');
            if (kind === 'video') {
                const preview = String(file?.preview_url || '').trim();
                if (preview) return preview;
                const poster = String(file?.poster_url || '').trim();
                if (poster) return poster;
            }
            if (source === 'sfx') {
                return '';
            }
            if (source === 'special') {
                return specialMediaUrl(file?.name || '');
            }
            return poolFilePath(file);
        }

        function poolFileFitsSlot(file, field) {
            if (!field) return true;
            const kind = mediaKindFromFile(file);
            if (!shellSlotAcceptsKind(field, kind)) {
                return false;
            }
            // Logo stays Brand-assets stills only (transparency-friendly special intake).
            if (field.key === 'logo' && String(file?.pool_source || '') !== 'special') {
                return false;
            }
            // Shell audio comes from Sound effects (legacy special audio still accepted).
            if (kind === 'audio') {
                const source = String(file?.pool_source || '');
                return source === 'sfx' || source === 'special';
            }
            return true;
        }

        function renderShellPreviewChrome(document) {
            const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
            const logo = String(assets.logo || '').trim();
            const bg = String(assets.background_image || '').trim();
            const bgAttr = bg
                ? ` style="background-image:url('${escapeHtml(bg)}');"`
                : '';
            const logoHtml = logo
                ? `<img class="theme-preview-shell-logo" src="${escapeHtml(logo)}" alt="" loading="lazy" onerror="this.style.opacity=0.25">`
                : '<span class="theme-preview-muted">No logo assigned</span>';

            return `
                <section class="theme-preview-section theme-preview-section--shell">
                    <h3 class="theme-preview-section-title">Shell</h3>
                    <p class="theme-preview-muted theme-preview-section-lead">Logo and still backdrop as assigned in Shell media.</p>
                    <div class="theme-preview-shell-chrome"${bgAttr}>
                        ${logoHtml}
                    </div>
                </section>`;
        }

        const SHELL_MEDIA_FIELDS = [
            {
                key: 'logo',
                label: 'Logo',
                emptyLabel: 'Drop a still logo',
                accept: ['image'],
                clearable: false,
                note: 'Still image for login and player chrome.',
            },
            {
                key: 'poster',
                label: 'Poster / share cover',
                emptyLabel: 'Drop a still poster',
                accept: ['image'],
                clearable: false,
                note: 'Still cover for share cards and shell presentation.',
            },
            {
                key: 'background_image',
                label: 'Still background',
                emptyLabel: 'Drop a still background',
                accept: ['image'],
                clearable: true,
                note: 'Still shell backdrop on login and player.',
            },
            {
                key: 'background_video',
                label: 'Living background',
                emptyLabel: 'Drop a living background',
                accept: ['video'],
                clearable: true,
                note: 'Living (video) shell backdrop on login and player.',
            },
            {
                key: 'welcome_audio',
                label: 'Welcome audio',
                emptyLabel: 'Drop a sound effect',
                accept: ['audio'],
                clearable: true,
                note: 'Short intro sound on the login surface. Pick from Files → Sound effects.',
            },
            {
                key: 'loggedin_audio',
                label: 'Logged-in audio',
                emptyLabel: 'Drop a sound effect',
                accept: ['audio'],
                clearable: true,
                note: 'Sound once visitors are inside the site. Pick from Files → Sound effects.',
            },
        ];

        let brandAssetFiles = [];
        let brandAssetFilter = 'all';
        let selectedShellSlotKey = '';
        let brandAssetsLoading = false;

        function shellFieldByKey(key) {
            return SHELL_MEDIA_FIELDS.find((field) => field.key === key) || null;
        }

        function shellSlotAcceptsKind(field, kind) {
            return Array.isArray(field?.accept) && field.accept.includes(kind);
        }

        function renderShellSlotPreviewHtml(field, value) {
            const path = String(value || '').trim();
            if (!path) {
                const icon = field.accept.includes('audio') ? '♪' : field.accept.includes('video') ? '▶' : '◻';
                return `<div class="theme-shell-slot-empty" aria-hidden="true">${icon}</div>
                    <span class="theme-shell-slot-status">${escapeHtml(field.emptyLabel)}</span>`;
            }
            const kind = mediaKindFromPath(path);
            if (kind === 'image') {
                return `<img class="theme-shell-slot-thumb" src="${escapeHtml(path)}" alt="" loading="lazy">
                    <span class="theme-shell-slot-status">Assigned</span>`;
            }
            if (kind === 'video') {
                return `<video class="theme-shell-slot-thumb" src="${escapeHtml(path)}" muted loop playsinline preload="metadata"></video>
                    <span class="theme-shell-slot-status">Assigned</span>`;
            }
            return `<div class="theme-shell-slot-empty theme-shell-slot-empty--audio" aria-hidden="true">♪</div>
                <span class="theme-shell-slot-status">Assigned</span>`;
        }

        function renderShellMediaFields(locked) {
            const assets = editorDocument?.assets && typeof editorDocument.assets === 'object'
                ? editorDocument.assets
                : {};
            const slots = SHELL_MEDIA_FIELDS.map((field) => {
                const value = String(assets[field.key] || '').trim();
                const selectedClass = selectedShellSlotKey === field.key ? ' is-selected' : '';
                const filledClass = value ? ' is-filled' : '';
                const clearBtn = field.clearable && !locked
                    ? `<button type="button" class="icon-btn theme-shell-slot-clear" data-shell-clear="${escapeHtml(field.key)}" title="Clear">Clear</button>`
                    : '';
                return `
                    <div class="theme-shell-slot${selectedClass}${filledClass}${locked ? ' is-locked' : ''}"
                         data-shell-slot="${escapeHtml(field.key)}"
                         data-accept="${escapeHtml(field.accept.join(','))}"
                         role="button"
                         tabindex="${locked ? '-1' : '0'}"
                         aria-pressed="${selectedShellSlotKey === field.key ? 'true' : 'false'}"
                         aria-label="${escapeHtml(field.label)}">
                        <div class="theme-shell-slot-head">
                            <strong>${escapeHtml(field.label)}</strong>
                            <span class="theme-shell-slot-kind">${escapeHtml(field.accept.map(kindLabel).join(' · '))}</span>
                        </div>
                        <div class="theme-shell-slot-body">
                            ${renderShellSlotPreviewHtml(field, value)}
                        </div>
                        <input type="hidden" id="theme_asset_${escapeHtml(field.key)}" value="${escapeHtml(value)}"
                               data-asset-key="${escapeHtml(field.key)}">
                        <div class="theme-shell-slot-actions">${clearBtn}</div>
                        <p class="theme-shell-slot-note">${escapeHtml(field.note)}</p>
                    </div>`;
            }).join('');

            const slotHint = locked
                ? 'bandPromo Default is locked — shell media cannot be changed here.'
                : 'Select a slot, then pick from Brand assets below (or drag onto a slot). Filenames stay hidden.';

            return `
                <div class="theme-editor-section theme-editor-section--shell-media">
                    <h5>Shell media</h5>
                    <p class="theme-field-hint">${slotHint}</p>
                    <div class="theme-shell-media-grid" id="themeShellSlots">
                        ${slots}
                    </div>
                </div>`;
        }

        function renderBrandAssetsPoolSection(locked) {
            const poolHint = locked
                ? 'bandPromo Default is locked — assignable media cannot be used here.'
                : 'Upload under Files → Brand assets (or Visual for living stills/video). Assign into Shell media slots above.';

            return `
                <div class="theme-editor-section theme-editor-section--brand-assets">
                    <h5>Brand assets</h5>
                    <p class="theme-field-hint">${poolHint}</p>
                    <div class="theme-brand-assets" id="themeBrandAssets">
                        <div class="theme-brand-assets-head">
                            <div class="theme-brand-assets-filters" role="group" aria-label="Media type">
                                <button type="button" class="theme-brand-filter is-active" data-brand-filter="all" ${locked ? 'disabled' : ''}>All</button>
                                <button type="button" class="theme-brand-filter" data-brand-filter="image" ${locked ? 'disabled' : ''}>Still</button>
                                <button type="button" class="theme-brand-filter" data-brand-filter="video" ${locked ? 'disabled' : ''}>Living</button>
                                <button type="button" class="theme-brand-filter" data-brand-filter="audio" ${locked ? 'disabled' : ''}>Audio</button>
                            </div>
                            <a class="theme-brand-assets-link" href="?tab=files&amp;fpanel=special">Open Files → Brand assets</a>
                            <a class="theme-brand-assets-link" href="?tab=files&amp;fpanel=visual">Visual</a>
                        </div>
                        <p class="theme-brand-assets-status" id="themeBrandAssetsStatus">Loading media…</p>
                        <div class="theme-brand-assets-grid" id="themeBrandAssetsGrid" aria-label="Brand assets pool"></div>
                    </div>
                </div>`;
        }

        function updateShellSlotDom(key) {
            const field = shellFieldByKey(key);
            const slot = formEl.querySelector(`[data-shell-slot="${key}"]`);
            if (!field || !slot) return;
            const value = String(editorDocument?.assets?.[key] || '').trim();
            const input = slot.querySelector(`[data-asset-key="${key}"]`);
            if (input instanceof HTMLInputElement) {
                input.value = value;
            }
            const body = slot.querySelector('.theme-shell-slot-body');
            if (body) {
                body.innerHTML = renderShellSlotPreviewHtml(field, value);
            }
            slot.classList.toggle('is-filled', !!value);
            slot.classList.toggle('is-selected', selectedShellSlotKey === key);
            slot.setAttribute('aria-pressed', selectedShellSlotKey === key ? 'true' : 'false');
        }

        function setShellAssetValue(key, path, { silent = false, assetId = '' } = {}) {
            if (!editorDocument || editorDocument.locked) return false;
            const field = shellFieldByKey(key);
            if (!field) return false;
            const next = String(path || '').trim();
            const nextAssetId = String(assetId || '').trim();
            if (next) {
                const kind = mediaKindFromPath(next);
                if (!shellSlotAcceptsKind(field, kind)) {
                    if (!silent) {
                        notifyThemeError(`${field.label} accepts ${field.accept.map(kindLabel).join(' / ')} only.`);
                    }
                    return false;
                }
            } else if (!field.clearable) {
                if (!silent) {
                    notifyThemeError(`${field.label} cannot be cleared.`);
                }
                return false;
            }
            if (!editorDocument.assets || typeof editorDocument.assets !== 'object') {
                editorDocument.assets = {};
            }
            if (!editorDocument.asset_ids || typeof editorDocument.asset_ids !== 'object') {
                editorDocument.asset_ids = {};
            }
            editorDocument.assets[key] = next;
            editorDocument.asset_ids[key] = next ? nextAssetId : '';
            updateShellSlotDom(key);
            previewDocument = cloneDocument(editorDocument);
            renderPreview(previewDocument);
            saveUi?.reconcile();
            return true;
        }

        function selectShellSlot(key) {
            if (!editorDocument || editorDocument.locked) return;
            selectedShellSlotKey = selectedShellSlotKey === key ? '' : key;
            formEl.querySelectorAll('[data-shell-slot]').forEach((slot) => {
                const slotKey = slot.getAttribute('data-shell-slot') || '';
                const selected = slotKey === selectedShellSlotKey;
                slot.classList.toggle('is-selected', selected);
                slot.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            renderBrandAssetsGrid();
        }

        function filteredBrandAssets() {
            return brandAssetFiles.filter((file) => {
                const kind = mediaKindFromFile(file);
                if (brandAssetFilter !== 'all' && kind !== brandAssetFilter) {
                    return false;
                }
                if (selectedShellSlotKey) {
                    const field = shellFieldByKey(selectedShellSlotKey);
                    if (!poolFileFitsSlot(file, field)) {
                        return false;
                    }
                } else if (kind === 'audio') {
                    const source = String(file?.pool_source || '');
                    if (source !== 'sfx' && source !== 'special') {
                        return false;
                    }
                }
                return true;
            });
        }

        function renderBrandAssetsGrid() {
            const grid = document.getElementById('themeBrandAssetsGrid');
            const status = document.getElementById('themeBrandAssetsStatus');
            if (!grid || !status) return;

            formEl.querySelectorAll('.theme-brand-filter').forEach((button) => {
                const filter = button.getAttribute('data-brand-filter') || 'all';
                button.classList.toggle('is-active', filter === brandAssetFilter);
            });

            if (brandAssetsLoading) {
                status.textContent = 'Loading media…';
                grid.innerHTML = '';
                return;
            }

            const files = filteredBrandAssets();
            if (!brandAssetFiles.length) {
                status.innerHTML = 'No assignable media yet. Upload under <a href="?tab=files&amp;fpanel=special">Brand assets</a> or <a href="?tab=files&amp;fpanel=visual">Visual</a>.';
                grid.innerHTML = '';
                return;
            }
            if (!files.length) {
                if (brandAssetFilter === 'video' || selectedShellSlotKey === 'background_video') {
                    status.innerHTML = 'No living media visible here. Upload a video under <a href="?tab=files&amp;fpanel=special">Brand assets</a>, or add living video under <a href="?tab=files&amp;fpanel=visual">Visual</a>.';
                } else {
                    status.textContent = selectedShellSlotKey
                        ? 'No matching media for the selected slot.'
                        : 'No media match this filter.';
                }
                grid.innerHTML = '';
                return;
            }

            status.textContent = selectedShellSlotKey
                ? `Showing media that fit “${shellFieldByKey(selectedShellSlotKey)?.label || selectedShellSlotKey}”. Click or drag onto the Shell media slot.`
                : 'Drag onto a Shell media slot, or select a slot first then click. Living can come from Brand assets or Visual.';

            const locked = !!editorDocument?.locked;
            grid.innerHTML = files.map((file) => {
                const kind = mediaKindFromFile(file);
                const path = poolFilePath(file);
                const thumbUrl = poolFileThumbUrl(file);
                const source = String(file.pool_source || 'special') === 'visual'
                    ? 'Visual'
                    : (String(file.pool_source || '') === 'sfx' ? 'SFX' : 'Brand');
                let thumb = `<span class="theme-brand-tile-placeholder">${kind === 'audio' ? '♪' : kind === 'video' ? '▶' : '◻'}</span>`;
                if (kind === 'image') {
                    thumb = `<img src="${escapeHtml(thumbUrl)}" alt="" loading="lazy">`;
                } else if (kind === 'video') {
                    if (/\.(png|jpe?g|webp|gif)$/i.test(thumbUrl)) {
                        thumb = `<img src="${escapeHtml(thumbUrl)}" alt="" loading="lazy">`;
                    } else {
                        thumb = `<video src="${escapeHtml(thumbUrl)}" muted loop playsinline preload="metadata"></video>`;
                    }
                }
                const assetId = String(file.asset_id || '').trim();
                return `<button type="button" class="theme-brand-tile"
                        data-brand-path="${escapeHtml(path)}"
                        data-brand-kind="${escapeHtml(kind)}"
                        data-brand-asset-id="${escapeHtml(assetId)}"
                        draggable="${locked ? 'false' : 'true'}"
                        ${locked ? 'disabled' : ''}
                        title="${escapeHtml(kindLabel(kind) + ' · ' + source)}">
                    <span class="theme-brand-tile-thumb">${thumb}</span>
                    <span class="theme-brand-tile-label">${escapeHtml(kindLabel(kind))} · ${escapeHtml(source)}</span>
                </button>`;
            }).join('');
        }

        async function loadBrandAssetPool() {
            const status = document.getElementById('themeBrandAssetsStatus');
            brandAssetsLoading = true;
            renderBrandAssetsGrid();
            try {
                const [specialResp, visualResp, sfxResp] = await Promise.all([
                    fetch('/biblioteca/list-media.php?target=special', { credentials: 'same-origin' }),
                    fetch('/biblioteca/list-media.php?target=visual', { credentials: 'same-origin' }),
                    fetch('/biblioteca/list-media.php?target=sfx', { credentials: 'same-origin' }),
                ]);
                const specialData = await specialResp.json().catch(() => ({}));
                const visualData = await visualResp.json().catch(() => ({}));
                const sfxData = await sfxResp.json().catch(() => ({}));
                if (!specialResp.ok || specialData.error) {
                    throw new Error(specialData.error || 'Could not load Brand assets');
                }
                const specialFiles = (Array.isArray(specialData.files) ? specialData.files : [])
                    .filter((file) => mediaKindFromFile(file) !== 'audio')
                    .map((file) => Object.assign({}, file, {
                        pool_source: 'special',
                    }));
                const visualFiles = (!visualResp.ok || visualData.error)
                    ? []
                    : (Array.isArray(visualData.files) ? visualData.files : [])
                        .filter((file) => {
                            const kind = mediaKindFromFile(file);
                            return kind === 'image' || kind === 'video';
                        })
                        .map((file) => Object.assign({}, file, {
                            pool_source: 'visual',
                        }));
                const sfxFiles = (!sfxResp.ok || sfxData.error)
                    ? []
                    : (Array.isArray(sfxData.files) ? sfxData.files : [])
                        .filter((file) => mediaKindFromFile(file) === 'audio')
                        .map((file) => Object.assign({}, file, {
                            pool_source: 'sfx',
                        }));
                // Prefer Sound effects; keep legacy special audio only when not already in sfx.
                const sfxNames = new Set(sfxFiles.map((file) => String(file.name || '')));
                const legacySpecialAudio = (Array.isArray(specialData.files) ? specialData.files : [])
                    .filter((file) => mediaKindFromFile(file) === 'audio' && !sfxNames.has(String(file.name || '')))
                    .map((file) => Object.assign({}, file, {
                        pool_source: 'special',
                    }));
                brandAssetFiles = specialFiles.concat(visualFiles).concat(sfxFiles).concat(legacySpecialAudio);
            } catch (error) {
                brandAssetFiles = [];
                if (status) {
                    status.textContent = error.message || 'Could not load media';
                }
            } finally {
                brandAssetsLoading = false;
                renderBrandAssetsGrid();
            }
        }

        function bindShellMediaUi() {
            const locked = !!editorDocument?.locked;
            if (locked) {
                selectedShellSlotKey = '';
                loadBrandAssetPool();
                return;
            }

            formEl.querySelectorAll('[data-shell-slot]').forEach((slot) => {
                const key = slot.getAttribute('data-shell-slot') || '';
                slot.addEventListener('click', (event) => {
                    if (event.target.closest('[data-shell-clear]')) return;
                    selectShellSlot(key);
                });
                slot.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        selectShellSlot(key);
                    }
                });
                slot.addEventListener('dragover', (event) => {
                    const kinds = String(slot.getAttribute('data-accept') || '').split(',').filter(Boolean);
                    const dragKind = formEl.dataset.dragKind || '';
                    if (!kinds.length || (dragKind && !kinds.includes(dragKind))) {
                        slot.classList.remove('is-drop-target');
                        return;
                    }
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'copy';
                    slot.classList.add('is-drop-target');
                });
                slot.addEventListener('dragleave', () => {
                    slot.classList.remove('is-drop-target');
                });
                slot.addEventListener('drop', (event) => {
                    event.preventDefault();
                    slot.classList.remove('is-drop-target');
                    const path = event.dataTransfer.getData('application/x-bandpromo-path')
                        || event.dataTransfer.getData('text/plain');
                    const kind = event.dataTransfer.getData('application/x-bandpromo-kind')
                        || mediaKindFromPath(path);
                    const assetId = event.dataTransfer.getData('application/x-bandpromo-asset-id') || '';
                    const field = shellFieldByKey(key);
                    if (!field || !shellSlotAcceptsKind(field, kind)) {
                        notifyThemeError(`${field?.label || 'Slot'} accepts ${field?.accept.map(kindLabel).join(' / ') || 'matching media'} only.`);
                        return;
                    }
                    setShellAssetValue(key, path, { assetId });
                    selectedShellSlotKey = key;
                    updateShellSlotDom(key);
                    renderBrandAssetsGrid();
                });
            });

            formEl.querySelectorAll('[data-shell-clear]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const key = button.getAttribute('data-shell-clear') || '';
                    setShellAssetValue(key, '');
                });
            });

            formEl.querySelectorAll('.theme-brand-filter').forEach((button) => {
                button.addEventListener('click', () => {
                    brandAssetFilter = button.getAttribute('data-brand-filter') || 'all';
                    renderBrandAssetsGrid();
                });
            });

            const grid = document.getElementById('themeBrandAssetsGrid');
            grid?.addEventListener('dragstart', (event) => {
                const tile = event.target instanceof Element
                    ? event.target.closest('.theme-brand-tile')
                    : null;
                if (!tile || !(event.dataTransfer)) return;
                const path = tile.getAttribute('data-brand-path') || '';
                const kind = tile.getAttribute('data-brand-kind') || '';
                const assetId = tile.getAttribute('data-brand-asset-id') || '';
                event.dataTransfer.setData('application/x-bandpromo-path', path);
                event.dataTransfer.setData('application/x-bandpromo-kind', kind);
                event.dataTransfer.setData('application/x-bandpromo-asset-id', assetId);
                event.dataTransfer.setData('text/plain', path);
                event.dataTransfer.effectAllowed = 'copy';
                tile.classList.add('is-dragging');
                formEl.dataset.dragKind = kind;
            });
            grid?.addEventListener('dragend', (event) => {
                const tile = event.target instanceof Element
                    ? event.target.closest('.theme-brand-tile')
                    : null;
                tile?.classList.remove('is-dragging');
                delete formEl.dataset.dragKind;
                formEl.querySelectorAll('.theme-shell-slot.is-drop-target').forEach((slot) => {
                    slot.classList.remove('is-drop-target');
                });
            });
            grid?.addEventListener('click', (event) => {
                const tile = event.target instanceof Element
                    ? event.target.closest('.theme-brand-tile')
                    : null;
                if (!tile) return;
                const path = tile.getAttribute('data-brand-path') || '';
                const kind = tile.getAttribute('data-brand-kind') || '';
                const assetId = tile.getAttribute('data-brand-asset-id') || '';
                if (!selectedShellSlotKey) {
                    notifyThemeError('Select a shell slot first, or drag the asset onto a slot.');
                    return;
                }
                const field = shellFieldByKey(selectedShellSlotKey);
                if (!field || !shellSlotAcceptsKind(field, kind)) {
                    notifyThemeError(`${field?.label || 'Slot'} accepts ${field?.accept.map(kindLabel).join(' / ') || 'matching media'} only.`);
                    return;
                }
                setShellAssetValue(selectedShellSlotKey, path, { assetId });
            });

            loadBrandAssetPool();
        }

        function renderPreviewMarkup(document) {
            if (!document) {
                return '<p class="theme-editor-empty">No theme selected.</p>';
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
                            <p class="theme-preview-muted theme-preview-section-lead">Player layout and cover art size follow screen breakpoints; this sample shows theme colors on the listening area.</p>
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
                            <h3 class="theme-preview-section-title">Buttons & tabs</h3>
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
                </div>
            `;
        }

        function renderPreview(document) {
            if (window.bandpromoThemePreview?.render) {
                window.bandpromoThemePreview.render(previewEl, document, {
                    styleId: 'bandpromo-theme-editor-preview-style',
                    selector: '#themeEditorPreview .theme-preview-canvas',
                });
                updateActionButtons(document);
                return;
            }
            if (!document) {
                previewEl.innerHTML = '<p class="theme-editor-empty">No theme selected.</p>';
                previewStyleEl.textContent = '';
                updateActionButtons(null);
                return;
            }
            applyPreviewStyles(document);
            previewEl.innerHTML = renderPreviewMarkup(document);
            updateActionButtons(document);
        }

        function updateActionButtons(document) {
            const locked = !!document?.locked;
            const isActive = document && document.id === activeThemeId;
            if (saveBtn) {
                if (!isEditing || locked) {
                    saveBtn.hidden = true;
                } else {
                    saveBtn.hidden = false;
                    saveUi?.reconcile();
                }
            }
            if (setActiveBtn) {
                setActiveBtn.hidden = !document;
                setActiveBtn.disabled = !!isActive;
                setActiveBtn.textContent = isActive ? '✓ Active brand' : '★ Set active';
                setActiveBtn.classList.toggle('btn-saved', !!isActive);
            }
        }

        function showPoolView() {
            isEditing = false;
            if (poolView) poolView.hidden = false;
            if (editorView) editorView.hidden = true;
            if (saveBtn) {
                saveBtn.hidden = true;
            }
            saveUi?.reset();
            if (editorHint) {
                editorHint.textContent = 'Select a brand from the pool, then click edit to change colors, narrative, typography, shell media, and Brand assets assignment.';
            }
            renderPoolList();
            updateActionButtons(previewDocument);
        }

        function showEditView(themeId) {
            isEditing = true;
            selectedThemeId = themeId;
            if (poolView) poolView.hidden = true;
            if (editorView) editorView.hidden = false;
            syncThemeUrl(themeId, true);
            if (editorHint) {
                editorHint.textContent = 'Changes update the live preview immediately. Save to keep brand and shell media edits.';
            }
            renderPoolList();
            updateActionButtons(editorDocument);
        }

        function themeEntry(themeId) {
            return themes.find((entry) => entry && entry.id === themeId) || null;
        }

        function themeCanDelete(entry) {
            if (!entry || entry.locked) {
                return false;
            }
            return String(entry.id || '') !== activeThemeId;
        }

        function themeMetaHtml(entry) {
            if (!entry) return '';
            // Storage ids (brd_*, legacy *-copy) stay machine-only; operators see title + status.
            const parts = [];
            if (entry.locked) parts.push('locked');
            if (entry.id === activeThemeId) {
                parts.push('<span class="theme-pool-meta-active">active</span>');
            }
            return parts.join(' · ');
        }

        function closeThemeDeleteModal() {
            pendingThemeDeleteId = '';
            if (deleteModal) {
                deleteModal.style.display = 'none';
                deleteModal.setAttribute('aria-hidden', 'true');
            }
        }

        function openThemeDeleteModal(themeId) {
            const entry = themeEntry(themeId);
            if (!entry || !themeCanDelete(entry)) {
                return;
            }
            const title = String(entry.title || themeId);
            if (!deleteModal) {
                if (!window.confirm(`Delete brand "${title}"? Its settings will be lost. This cannot be undone.`)) {
                    return;
                }
                deleteTheme(themeId).catch((error) => notifyThemeError(error.message || 'Could not delete theme'));
                return;
            }
            pendingThemeDeleteId = themeId;
            if (deleteModalName) {
                deleteModalName.textContent = title;
            }
            deleteModal.style.display = 'flex';
            deleteModal.setAttribute('aria-hidden', 'false');
            deleteConfirmBtn?.focus();
        }

        async function deleteTheme(themeId) {
            const entry = themeEntry(themeId);
            if (!entry || !themeCanDelete(entry)) {
                return;
            }
            const data = await fetchJson(`/biblioteca/manage-theme.php?theme=${encodeURIComponent(themeId)}`, {
                method: 'DELETE',
                credentials: 'same-origin',
            });
            themes = Array.isArray(data.themes) ? data.themes : themes;
            activeThemeId = String(data.active_theme_id || activeThemeId);
            if (selectedThemeId === themeId) {
                selectedThemeId = themes[0]?.id || 'setup-default';
                if (isEditing) {
                    showPoolView();
                    syncThemeUrl(selectedThemeId, false);
                    editorDocument = null;
                    formEl.innerHTML = '<p class="theme-editor-locked-note">Select a brand from the pool.</p>';
                } else {
                    syncThemeUrl(selectedThemeId, false);
                }
                await loadThemeDocuments(selectedThemeId);
            } else if (previewDocument?.id === themeId) {
                selectedThemeId = themes[0]?.id || 'setup-default';
                syncThemeUrl(selectedThemeId, false);
                await loadThemeDocuments(selectedThemeId);
            } else {
                renderPreview(previewDocument);
            }
            renderPoolList();
        }

        function renderPoolList() {
            if (!themes.length) {
                poolList.innerHTML = '<li class="player-layout-empty">No brands available.</li>';
                return;
            }

            poolList.innerHTML = themes.map((entry) => {
                const id = entry.id || '';
                const selectedClass = id === selectedThemeId ? ' playlist-editor-row-selected' : '';
                const activeClass = id === activeThemeId ? ' theme-pool-row--active' : '';
                const activeDot = id === activeThemeId ? '<span class="theme-pool-active-dot" title="Active brand">●</span>' : '';
                const title = escapeHtml(entry.title || id);
                const deleteBtn = themeCanDelete(entry)
                    ? `<button type="button" class="icon-btn icon-btn--pool icon-btn--danger page-pool-delete-btn" data-theme-id="${escapeHtml(id)}" title="Delete brand" aria-label="Delete ${title}">🗑️</button>`
                    : '';
                const editBtn = entry.locked
                    ? ''
                    : `<button type="button" class="icon-btn icon-btn--pool page-pool-edit-btn" data-theme-id="${escapeHtml(id)}" title="Edit brand" aria-label="Edit ${title}">✏️</button>`;
                return `<li class="playlist-editor-row theme-pool-row page-pool-row${selectedClass}${activeClass}" data-theme-id="${escapeHtml(id)}" aria-selected="${id === selectedThemeId ? 'true' : 'false'}">
                    <span class="playlist-track-info">
                        <strong>🎨 ${title}${activeDot}</strong>
                        <span class="playlist-track-meta">${themeMetaHtml(entry)}</span>
                    </span>
                    <span class="page-pool-row-actions">
                        ${editBtn}
                        <button type="button" class="icon-btn icon-btn--pool page-pool-duplicate-btn" data-theme-id="${escapeHtml(id)}" title="Duplicate brand" aria-label="Duplicate ${title}">⧉</button>
                        ${deleteBtn}
                    </span>
                </li>`;
            }).join('');
        }

        function renderForm() {
            if (!editorDocument) {
                formEl.innerHTML = '<p class="theme-editor-locked-note">Select a brand from the pool.</p>';
                return;
            }

            const locked = !!editorDocument.locked;
            const fontBase = tokenValue(editorDocument, 'typography.font_family_base');
            const fontHeading = tokenValue(editorDocument, 'typography.font_family_heading');

            formEl.innerHTML = `
                ${locked ? '<p class="theme-editor-locked-note">bandPromo Default is protected. Duplicate it to customize this brand.</p>' : ''}
                ${renderNarrativeFields(locked)}
                <div class="theme-editor-section">
                    <h5>Typography</h5>
                    <div class="theme-token-grid theme-token-grid--stacked">
                        ${renderFontPresetSelect('base', fontBase, locked)}
                        ${renderFontPresetSelect('heading', fontHeading, locked)}
                    </div>
                </div>
                <div class="theme-editor-section theme-editor-section--colors">
                    <h5>Colors</h5>
                    <p class="theme-field-hint">Type a full hex code (e.g. #FF6F61), or use the color square on the right. Changes appear in the live preview immediately.</p>
                    ${renderCompactColors(locked)}
                </div>
                ${renderShellMediaFields(locked)}
                ${renderBrandAssetsPoolSection(locked)}
            `;

            syncThemeSettingsPanel(editorDocument);
            selectedShellSlotKey = '';
            brandAssetFilter = 'all';
            bindShellMediaUi();
        }

        function collectAssetsFromForm() {
            if (!editorDocument) {
                return;
            }
            if (!editorDocument.assets || typeof editorDocument.assets !== 'object') {
                editorDocument.assets = {};
            }
            if (!editorDocument.asset_ids || typeof editorDocument.asset_ids !== 'object') {
                editorDocument.asset_ids = {};
            }
            formEl.querySelectorAll('[data-asset-key]').forEach((input) => {
                if (!(input instanceof HTMLInputElement)) return;
                const key = input.getAttribute('data-asset-key') || '';
                if (!key) return;
                const next = String(input.value || '').trim();
                const previous = String(editorDocument.assets[key] || '').trim();
                editorDocument.assets[key] = next;
                // Manual path edits clear the parallel asset_id unless unchanged.
                if (next !== previous) {
                    editorDocument.asset_ids[key] = '';
                }
            });
        }

        function collectFormIntoDocument() {
            if (!editorDocument || editorDocument.locked) {
                return;
            }
            collectNarrativeFields();
            collectAssetsFromForm();
            formEl.querySelectorAll('[data-token-path]').forEach((input) => {
                if (!(input instanceof HTMLInputElement) || input.hidden) return;
                const path = input.getAttribute('data-token-path') || '';
                if (!path) return;
                setTokenValue(editorDocument, path, input.value.trim());
            });
            previewDocument = cloneDocument(editorDocument);
            renderPreview(previewDocument);
            saveUi?.reconcile();
        }

        function hasUnsavedChanges() {
            return !!(saveBtn && saveBtn.classList.contains('btn-amber'));
        }

        async function loadRegistry() {
            const data = await fetchJson('/biblioteca/get-themes.php');
            themes = Array.isArray(data.themes) ? data.themes : [];
            activeThemeId = String(data.active_theme_id || 'setup-default');
            renderPoolList();
        }

        async function loadThemeDocuments(themeId) {
            const data = await fetchJson(`/biblioteca/get-theme.php?theme=${encodeURIComponent(themeId)}`);
            previewDocument = data.document || null;
            if (previewDocument && (!previewDocument.assets || typeof previewDocument.assets !== 'object')) {
                previewDocument.assets = {};
            }
            editorDocument = cloneDocument(previewDocument);
            activeThemeId = String(data.active_theme_id || activeThemeId);
            renderPreview(previewDocument);
            if (isEditing) {
                renderForm();
                saveUi?.setBaseline();
            }
        }

        async function requestCloseEditor() {
            if (themeSettingsDirty()) {
                const saved = await saveThemeSettings();
                if (!saved) {
                    return false;
                }
            }
            if (hasUnsavedChanges()) {
                const proceed = window.confirm('You have unsaved theme changes. Leave edit mode without saving?');
                if (!proceed) return false;
            }
            showPoolView();
            syncThemeUrl(selectedThemeId, false);
            editorDocument = null;
            formEl.innerHTML = '<p class="theme-editor-locked-note">Select a brand from the pool.</p>';
            await loadThemeDocuments(selectedThemeId);
            return true;
        }

        async function openThemeEditor(themeId) {
            if (!themeId) return;
            if (isEditing && themeId !== selectedThemeId) {
                if (themeSettingsDirty()) {
                    const saved = await saveThemeSettings();
                    if (!saved) {
                        return;
                    }
                }
                if (hasUnsavedChanges()) {
                    const proceed = window.confirm('You have unsaved theme changes. Switch themes without saving?');
                    if (!proceed) return;
                }
            }
            selectedThemeId = themeId;
            showEditView(themeId);
            try {
                await loadThemeDocuments(themeId);
                renderForm();
            } catch (error) {
                notifyThemeError(error.message || 'Could not load theme');
            }
        }

        async function selectThemeForPreview(themeId) {
            if (!themeId || (themeId === selectedThemeId && previewDocument && !isEditing)) {
                return;
            }
            if (isEditing) {
                await openThemeEditor(themeId);
                return;
            }
            if (hasUnsavedChanges()) {
                const proceed = window.confirm('You have unsaved theme changes. Switch themes without saving?');
                if (!proceed) return;
            }
            selectedThemeId = themeId;
            syncThemeUrl(themeId, false);
            renderPoolList();
            try {
                await loadThemeDocuments(themeId);
                renderPoolList();
            } catch (error) {
                notifyThemeError(error.message || 'Could not load theme preview');
            }
        }

        poolList.addEventListener('click', (event) => {
            const deleteBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-delete-btn')
                : null;
            if (deleteBtn) {
                event.preventDefault();
                event.stopPropagation();
                const themeId = deleteBtn.getAttribute('data-theme-id') || '';
                openThemeDeleteModal(themeId);
                return;
            }

            const editBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-edit-btn')
                : null;
            if (editBtn) {
                event.preventDefault();
                event.stopPropagation();
                const themeId = editBtn.getAttribute('data-theme-id') || '';
                openThemeEditor(themeId);
                return;
            }

            const rowDuplicateBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-duplicate-btn')
                : null;
            if (rowDuplicateBtn) {
                event.preventDefault();
                event.stopPropagation();
                const themeId = rowDuplicateBtn.getAttribute('data-theme-id') || '';
                duplicateTheme(themeId);
                return;
            }

            const row = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-row')
                : null;
            if (!row || !poolList.contains(row)) return;
            const themeId = row.getAttribute('data-theme-id') || '';
            if (!themeId) return;
            selectThemeForPreview(themeId);
        });

        deleteCancelBtn?.addEventListener('click', closeThemeDeleteModal);
        deleteModal?.addEventListener('click', (event) => {
            if (event.target === deleteModal) {
                closeThemeDeleteModal();
            }
        });
        deleteConfirmBtn?.addEventListener('click', async () => {
            const themeId = pendingThemeDeleteId;
            if (!themeId) {
                return;
            }
            closeThemeDeleteModal();
            try {
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = true;
                }
                await deleteTheme(themeId);
            } catch (error) {
                notifyThemeError(error.message || 'Could not delete theme');
            } finally {
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = false;
                }
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !deleteModal || deleteModal.style.display !== 'flex') {
                return;
            }
            closeThemeDeleteModal();
        });

        backBtn?.addEventListener('click', () => {
            requestCloseEditor();
        });

        formEl.addEventListener('input', (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
                return;
            }
            if (input instanceof HTMLInputElement && input.classList.contains('theme-color-hex-input')) {
                const chip = input.closest('.theme-color-chip');
                const typed = String(input.value || '').trim();
                const hex = normalizeHexColor(typed.startsWith('#') ? typed : `#${typed}`);
                if (hex) {
                    input.classList.remove('is-invalid');
                    if (chip) {
                        syncColorChipPresentation(chip);
                    }
                    collectFormIntoDocument();
                } else {
                    input.classList.add('is-invalid');
                }
                return;
            }
            if (input instanceof HTMLInputElement && input.classList.contains('theme-color-picker')) {
                const chip = input.closest('.theme-color-chip');
                if (chip) {
                    const hexInput = chip.querySelector('input.theme-color-hex-input');
                    if (hexInput instanceof HTMLInputElement) {
                        hexInput.value = normalizeHexColor(input.value) || '#000000';
                    }
                    syncColorChipPresentation(chip);
                }
                collectFormIntoDocument();
                return;
            }
            if (
                input.hasAttribute('data-token-path')
                || input.hasAttribute('data-narrative-field')
                || input.hasAttribute('data-asset-key')
            ) {
                collectFormIntoDocument();
            }
        });

        formEl.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (target instanceof HTMLInputElement && target.classList.contains('theme-color-hex-input')) {
                const chip = target.closest('.theme-color-chip');
                const typed = String(target.value || '').trim();
                const hex = normalizeHexColor(typed.startsWith('#') ? typed : `#${typed}`);
                if (hex && chip) {
                    target.value = hex.toUpperCase();
                    syncColorChipPresentation(chip);
                    collectFormIntoDocument();
                } else if (chip) {
                    const picker = chip.querySelector('input.theme-color-picker');
                    target.value = normalizeHexColor(picker?.value) || '#000000';
                    target.value = String(target.value).toUpperCase();
                    target.classList.remove('is-invalid');
                    syncColorChipPresentation(chip);
                }
            }
            if (target instanceof HTMLSelectElement && target.hasAttribute('data-font-preset-select')) {
                applyFontPresetSelection(target.getAttribute('data-font-preset-select') || '', target.value);
            }
        });

        titleInput?.addEventListener('focusout', () => {
            saveThemeSettings();
        });

        titleInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                titleInput.blur();
            }
        });

        saveBtn?.addEventListener('click', async () => {
            if (!editorDocument || editorDocument.locked) return;
            collectFormIntoDocument();
            const title = themeTitleValue();
            if (!title) {
                if (settingsStatus) {
                    settingsStatus.textContent = 'Brand name is required.';
                }
                notifyThemeError('Brand name is required.');
                return;
            }
            editorDocument.title = title;
            try {
                saveBtn.disabled = true;
                saveUi?.markSaving();
                const data = await fetchJson('/biblioteca/save-theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify(editorDocument),
                });
                editorDocument = data.document || editorDocument;
                previewDocument = cloneDocument(editorDocument);
                renderPreview(previewDocument);
                renderForm();
                const entry = themes.find((item) => item.id === editorDocument.id);
                if (entry) {
                    entry.title = editorDocument.title;
                }
                renderPoolList();
                saveUi?.markSaved();
                themeSettingsBaseline = { title: editorDocument.title };
                if (settingsStatus) {
                    settingsStatus.textContent = '';
                }
            } catch (error) {
                saveUi?.markFailed();
                notifyThemeError(error.message || 'Could not save theme');
            } finally {
                saveBtn.disabled = false;
            }
        });

        setActiveBtn?.addEventListener('click', async () => {
            const document = isEditing ? editorDocument : previewDocument;
            if (!document) return;
            try {
                setActiveBtn.disabled = true;
                const data = await fetchJson('/biblioteca/set-active-theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ theme_id: document.id }),
                });
                activeThemeId = String(data.active_theme_id || document.id);
                renderPreview(previewDocument);
                if (isEditing) {
                    renderForm();
                }
                renderPoolList();
            } catch (error) {
                notifyThemeError(error.message || 'Could not set active theme');
            } finally {
                updateActionButtons(isEditing ? editorDocument : previewDocument);
            }
        });

        async function duplicateTheme(sourceId) {
            if (!sourceId) return;
            try {
                if (registryStatus) {
                    registryStatus.textContent = 'Duplicating theme…';
                    registryStatus.style.color = '';
                }
                const data = await fetchJson('/biblioteca/duplicate-theme.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ source_id: sourceId }),
                });
                await loadRegistry();
                const newId = data.document?.id;
                if (registryStatus) {
                    registryStatus.textContent = '';
                }
                if (newId) {
                    await openThemeEditor(newId);
                }
            } catch (error) {
                if (registryStatus) {
                    registryStatus.textContent = '❌ ' + error.message;
                    registryStatus.style.color = '#f87171';
                }
                notifyThemeError(error.message || 'Could not duplicate theme');
            }
        }

        const urlParams = new URLSearchParams(window.location.search);
        const startInEdit = urlParams.get('edit') === '1';

        loadRegistry()
            .catch((error) => {
                poolList.innerHTML = `<li class="player-layout-empty" style="color:#f87171">${escapeHtml(error.message)}</li>`;
            })
            .finally(async () => {
                if (startInEdit) {
                    await openThemeEditor(selectedThemeId);
                } else {
                    showPoolView();
                    syncThemeUrl(selectedThemeId, false);
                    try {
                        await loadThemeDocuments(selectedThemeId);
                    } catch (error) {
                        notifyThemeError(error.message || 'Could not load theme');
                    }
                }
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBandpromoThemeEditor);
    } else {
        initBandpromoThemeEditor();
    }
})();
