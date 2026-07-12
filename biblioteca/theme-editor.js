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
            const toastHost = document.getElementById('adminToastHost');
            const text = String(message || '').trim();
            if (!toastHost || !text) {
                return;
            }

            const toast = document.createElement('div');
            toast.className = `admin-toast ${type}`;
            toast.textContent = text;
            toastHost.appendChild(toast);

            window.setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-4px)';
                toast.style.transition = 'opacity 150ms ease, transform 150ms ease';
                window.setTimeout(() => {
                    toast.remove();
                }, 180);
            }, 3200);
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

        function renderCompactColors(locked) {
            return `<div class="theme-color-compact-grid">${COLOR_FIELDS.map(([key, label]) => {
                const value = tokenValue(editorDocument, `color.${key}`) || '#000000';
                return `<label class="theme-color-chip" title="${escapeHtml(label)}">
                    <input type="color" data-token-path="color.${key}" value="${escapeHtml(value)}" ${locked ? 'disabled' : ''}>
                    <span>${escapeHtml(label)}</span>
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

        function renderAssetPreview(document) {
            const assets = document?.assets && typeof document.assets === 'object' ? document.assets : {};
            const imageKeys = [
                ['logo', 'Logo'],
                ['poster', 'Cover art'],
                ['background_image', 'Background image'],
            ];
            const items = imageKeys
                .map(([key, label]) => {
                    const src = String(assets[key] || '').trim();
                    if (!src) return '';
                    return `<figure class="theme-preview-asset">
                        <img src="${escapeHtml(src)}" alt="" loading="lazy" onerror="this.style.opacity=0.25">
                        <figcaption>${escapeHtml(label)}</figcaption>
                    </figure>`;
                })
                .filter(Boolean)
                .join('');
            if (!items) {
                return '<p class="theme-preview-muted">Brand assets use paths from Config → Theme or this theme document when saved as active.</p>';
            }
            return `<div class="theme-preview-assets">${items}</div>`;
        }

        function renderPreviewMarkup(document) {
            if (!document) {
                return '<p class="theme-editor-empty">No theme selected.</p>';
            }

            return `
                <div class="theme-preview-canvas">
                    <div class="theme-preview-shell">
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

                        <section class="theme-preview-section">
                            <h3 class="theme-preview-section-title">Brand assets</h3>
                            ${renderAssetPreview(document)}
                        </section>
                    </div>
                </div>
            `;
        }

        function renderPreview(document) {
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
                editorHint.textContent = 'Select a brand from the pool, then click edit to change colors, narrative, and typography.';
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
                editorHint.textContent = 'Changes update the live preview immediately. Save to keep token edits.';
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
            const parts = [escapeHtml(String(entry.id || ''))];
            if (entry.locked) parts.push('locked');
            let line = parts.join(' · ');
            if (entry.id === activeThemeId) {
                line += ' · <span class="theme-pool-meta-active">active</span>';
            }
            return line;
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
                    formEl.innerHTML = '<p class="theme-editor-locked-note">Select a theme from the pool.</p>';
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
                    ? `<button type="button" class="page-pool-delete-btn" data-theme-id="${escapeHtml(id)}" title="Delete brand" aria-label="Delete ${title}">🗑️</button>`
                    : '';
                const editBtn = entry.locked
                    ? ''
                    : `<button type="button" class="page-pool-edit-btn" data-theme-id="${escapeHtml(id)}" title="Edit brand" aria-label="Edit ${title}">✏️</button>`;
                return `<li class="playlist-editor-row theme-pool-row page-pool-row${selectedClass}${activeClass}" data-theme-id="${escapeHtml(id)}" aria-selected="${id === selectedThemeId ? 'true' : 'false'}">
                    <span class="playlist-track-info">
                        <strong>🎨 ${title}${activeDot}</strong>
                        <span class="playlist-track-meta">${themeMetaHtml(entry)}</span>
                    </span>
                    <span class="page-pool-row-actions">
                        ${editBtn}
                        <button type="button" class="page-pool-duplicate-btn" data-theme-id="${escapeHtml(id)}" title="Duplicate brand" aria-label="Duplicate ${title}">⧉</button>
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
                    <p class="theme-field-hint">Tap a swatch to adjust site-wide colors. Changes appear in the live preview immediately.</p>
                    ${renderCompactColors(locked)}
                </div>
                <p class="hint">Logo, cover art, and background media paths stay under <a href="?tab=settings&ctab=theme">Settings → Theme</a> during the brand migration.</p>
            `;

            syncThemeSettingsPanel(editorDocument);
        }

        function collectFormIntoDocument() {
            if (!editorDocument || editorDocument.locked) {
                return;
            }
            collectNarrativeFields();
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
            formEl.innerHTML = '<p class="theme-editor-locked-note">Select a theme from the pool.</p>';
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
            if (input.hasAttribute('data-token-path') || input.hasAttribute('data-narrative-field')) {
                collectFormIntoDocument();
            }
        });

        formEl.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
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
