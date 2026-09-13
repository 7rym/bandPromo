(function () {
    function initBandpromoBrandEditor() {
        const root = document.getElementById('brandEditorRoot');
        const poolView = document.getElementById('brandPoolView');
        const editorView = document.getElementById('brandEditorView');
        const poolList = document.getElementById('brandPoolList');
        const formEl = document.getElementById('brandEditorForm');
        const previewEl = document.getElementById('brandEditorPreview');
        const saveBtn = document.getElementById('brandSaveBtn');
        const setActiveBtn = document.getElementById('brandSetActiveBtn');
        const backBtn = document.getElementById('brandEditorBackBtn');
        const titleInput = document.getElementById('brandSettingsTitle');
        const settingsStatus = document.getElementById('brandSettingsStatus');
        const headBadges = document.getElementById('brandEditorHeadBadges');
        const registryStatus = document.getElementById('brandRegistryStatus');
        const deleteModal = document.getElementById('brandDeleteModal');
        const deleteModalName = document.getElementById('brandDeleteModalName');
        const deleteConfirmBtn = document.getElementById('brandDeleteConfirmBtn');
        const deleteCancelBtn = document.getElementById('brandDeleteCancelBtn');
        if (!root || !poolList || !formEl || !previewEl) {
            return;
        }
        if (root.dataset.brandEditorInitialized === 'true') {
            return;
        }
        root.dataset.brandEditorInitialized = 'true';

        const isLocalDevHost = window.BANDPROMO_LOCAL_DEV === true;

        function brandIsPlatformDefault(entryOrDoc) {
            if (entryOrDoc && typeof entryOrDoc.platform_default === 'boolean') {
                return entryOrDoc.platform_default;
            }
            const id = String(entryOrDoc?.id || '');
            return id === 'bandpromo-default' || id === 'setup-default';
        }

        function brandMayEdit(entryOrDoc) {
            if (entryOrDoc && typeof entryOrDoc.can_edit === 'boolean') {
                return entryOrDoc.can_edit;
            }
            if (!entryOrDoc?.locked) {
                return true;
            }
            return brandIsPlatformDefault(entryOrDoc) && isLocalDevHost;
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

        const ROLE_PALETTE = [
            ['primary', 'Primary'],
            ['secondary', 'Secondary'],
            ['text', 'Main text'],
            ['text_muted', 'Muted text'],
            ['surface_mid', 'Panels'],
        ];

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

        const FONT_PRESETS = [
            { id: 'segoe', label: 'Segoe UI (recommended)', value: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" },
            { id: 'system', label: 'System default', value: "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif" },
            { id: 'arial', label: 'Arial / Helvetica', value: 'Arial, Helvetica, sans-serif' },
            { id: 'georgia', label: 'Georgia (serif)', value: "Georgia, 'Times New Roman', serif" },
        ];

        const initialUrlParams = new URLSearchParams(window.location.search);
        let brands = [];
        let activeBrandId = '';
        let selectedBrandId = String(
            initialUrlParams.get('brand')
            || initialUrlParams.get('theme')
            || root.dataset.initialBrand
            || 'setup-default'
        );
        let previewDocument = null;
        let editorDocument = null;
        let isEditing = false;
        let brandSettingsBaseline = { title: '' };
        let brandSettingsSaving = false;
        let brandSettingsSaveQueued = false;
        let pendingBrandDeleteId = '';
        let brandBreadcrumb = null;
        const saveUi = window.bandpromoContentSaveUi?.create(saveBtn, {
            saveLabel: '💾 Save brand',
            readFingerprint() {
                return JSON.stringify({
                    tokens: editorDocument?.tokens || previewDocument?.tokens || {},
                    mood: editorDocument?.mood || '',
                    keywords: editorDocument?.keywords || [],
                    tone_notes: editorDocument?.tone_notes || '',
                    assets: editorDocument?.assets || previewDocument?.assets || {},
                    player: editorDocument?.player || previewDocument?.player || {},
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

        function renderEditorSection(title, innerHtml, extraClass, previewFocus) {
            const extra = extraClass ? ` ${extraClass}` : '';
            const focusAttr = previewFocus
                ? ` data-preview-focus-source="${escapeHtml(previewFocus)}"`
                : '';
            return `<div class="content-editor-section brand-editor-section${extra}"${focusAttr}>
                <div class="content-editor-section-head">
                    <h4 class="split-editor__title">${escapeHtml(title)}</h4>
                </div>
                <div class="content-editor-section-body">${innerHtml}</div>
            </div>`;
        }

        let previewFocusTimer = null;
        const PREVIEW_MODE_STORAGE_KEY = 'bandpromo_brand_preview_mode';

        function normalizePreviewMode(mode) {
            return String(mode || '').trim().toLowerCase() === 'content' ? 'content' : 'player';
        }

        function readStoredPreviewMode() {
            try {
                return normalizePreviewMode(window.localStorage.getItem(PREVIEW_MODE_STORAGE_KEY));
            } catch (error) {
                return 'player';
            }
        }

        let previewMode = readStoredPreviewMode();
        const EDITOR_TAB_STORAGE_KEY = 'bandpromo_brand_editor_tab';

        function normalizeEditorTab(tab) {
            const value = String(tab || '').trim().toLowerCase();
            if (value === 'player' || value === 'content') {
                return value;
            }
            return 'common';
        }

        function readStoredEditorTab() {
            try {
                return normalizeEditorTab(window.localStorage.getItem(EDITOR_TAB_STORAGE_KEY));
            } catch (error) {
                return 'common';
            }
        }

        let editorTab = readStoredEditorTab();

        function syncEditorTabUi() {
            if (!(formEl instanceof HTMLElement)) {
                return;
            }
            formEl.querySelectorAll('[data-brand-editor-tab]').forEach((button) => {
                if (!(button instanceof HTMLElement)) {
                    return;
                }
                const tab = normalizeEditorTab(button.getAttribute('data-brand-editor-tab'));
                const active = tab === editorTab;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            formEl.querySelectorAll('[data-brand-editor-panel]').forEach((panel) => {
                if (!(panel instanceof HTMLElement)) {
                    return;
                }
                const tab = normalizeEditorTab(panel.getAttribute('data-brand-editor-panel'));
                panel.hidden = tab !== editorTab;
            });
        }

        function setEditorTab(tab, options = {}) {
            const next = normalizeEditorTab(tab);
            const changed = next !== editorTab;
            editorTab = next;
            try {
                window.localStorage.setItem(EDITOR_TAB_STORAGE_KEY, editorTab);
            } catch (error) {}
            syncEditorTabUi();
            if (options.syncPreview) {
                if (editorTab === 'player' && previewMode !== 'player') {
                    setPreviewMode('player', { forceRender: true });
                } else if (editorTab === 'content' && previewMode !== 'content') {
                    setPreviewMode('content', { forceRender: true });
                }
            }
            if (changed && typeof options.onChanged === 'function') {
                options.onChanged(editorTab);
            }
        }

        function bindEditorTabUi() {
            if (!(formEl instanceof HTMLElement)) {
                return;
            }
            const nav = formEl.querySelector('#brandEditorSubnav');
            if (!(nav instanceof HTMLElement) || nav.dataset.bound === 'true') {
                syncEditorTabUi();
                return;
            }
            nav.dataset.bound = 'true';
            nav.addEventListener('click', (event) => {
                const button = event.target instanceof Element
                    ? event.target.closest('[data-brand-editor-tab]')
                    : null;
                if (!(button instanceof HTMLElement)) {
                    return;
                }
                setEditorTab(button.getAttribute('data-brand-editor-tab') || 'common', { syncPreview: true });
            });
            syncEditorTabUi();
        }

        function setPreviewMode(mode, options = {}) {
            const next = normalizePreviewMode(mode);
            const changed = next !== previewMode;
            previewMode = next;
            try {
                window.localStorage.setItem(PREVIEW_MODE_STORAGE_KEY, previewMode);
            } catch (error) {}
            if (changed || options.forceRender) {
                const doc = isEditing ? editorDocument : previewDocument;
                if (doc) {
                    renderPreview(doc);
                }
            }
        }

        function revealPreviewFocus(focusId) {
            const id = String(focusId || '').trim();
            if (id === '' || !(previewEl instanceof HTMLElement)) {
                return;
            }
            if ((id === 'content-chrome' || id === 'playlist-selector' || id === 'content-panels') && previewMode !== 'content') {
                setPreviewMode('content', { forceRender: true });
            }
            if ((id === 'content-chrome' || id === 'playlist-selector' || id === 'content-panels') && editorTab !== 'content') {
                setEditorTab('content');
            }
            const target = previewEl.querySelector(`[data-preview-focus="${id}"]`);
            if (!(target instanceof HTMLElement)) {
                return;
            }
            previewEl.querySelectorAll('.is-preview-focus').forEach((el) => {
                el.classList.remove('is-preview-focus');
            });
            target.classList.add('is-preview-focus');
            target.scrollIntoView({ block: 'nearest', behavior: 'smooth', inline: 'nearest' });
            if (previewFocusTimer) {
                window.clearTimeout(previewFocusTimer);
            }
            previewFocusTimer = window.setTimeout(() => {
                target.classList.remove('is-preview-focus');
                previewFocusTimer = null;
            }, 1600);
        }

        function previewFocusFromEventTarget(target) {
            if (!(target instanceof Element)) {
                return '';
            }
            const section = target.closest('[data-preview-focus-source]');
            return section ? String(section.getAttribute('data-preview-focus-source') || '').trim() : '';
        }

        function showBrandToast(message, type = 'warning') {
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

            const dismissToast = () => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-4px)';
                toast.style.transition = 'opacity 150ms ease, transform 150ms ease';
                window.setTimeout(() => toast.remove(), 180);
            };
            const dismissBtn = document.createElement('button');
            dismissBtn.type = 'button';
            dismissBtn.className = 'admin-toast-dismiss';
            dismissBtn.setAttribute('aria-label', 'Dismiss notification');
            dismissBtn.textContent = '×';
            dismissBtn.addEventListener('click', dismissToast);
            toast.appendChild(dismissBtn);
            toastHost.appendChild(toast);
        }

        function notifyBrandError(message) {
            const text = String(message || '').replace(/^❌\s*/, '').trim();
            if (!text) {
                return;
            }
            showBrandToast(text, 'warning');
        }

        const lifecycle = window.bandpromoEditorLifecycle.create({
            root: root,
            poolView: poolView,
            editorView: editorView,
            saveBtn: saveBtn,
            cntab: 'branding',
            entityParam: 'brand',
            onShowPool: function () {
                isEditing = false;
                brandBreadcrumb?.setView('pool');
                saveUi?.reset();
                renderPoolList();
                updateActionButtons(previewDocument);
            },
            onShowEdit: function (brandId) {
                isEditing = true;
                brandBreadcrumb?.setView('edit');
                selectedBrandId = brandId;
                renderPoolList();
                updateActionButtons(editorDocument);
            },
            onBeforeClose: async function () {
                if (brandSettingsDirty()) {
                    const saved = await saveBrandSettings();
                    if (!saved) {
                        return false;
                    }
                }
                const modal = window.bandpromoEditorUnsavedModal;
                if (modal && typeof modal.confirmLeave === 'function') {
                    const result = await modal.confirmLeave({
                        isDirty: () => hasUnsavedChanges(),
                        message: 'This brand has unsaved changes. What would you like to do?',
                        fallbackMessage: 'You have unsaved brand changes. Leave edit mode without saving?',
                        save: () => saveBrandDocument(),
                        discard: () => {
                            editorDocument = cloneDocument(previewDocument);
                            renderForm();
                            saveUi?.setBaseline();
                        },
                    });
                    return result === 'proceed';
                }
                if (hasUnsavedChanges()) {
                    if (typeof window.bandpromoConfirm === 'function') {
                        return window.bandpromoConfirm({
                            title: 'Unsaved brand changes',
                            body: 'You have unsaved brand changes. Leave edit mode without saving?',
                            confirmLabel: 'Leave without saving',
                            cancelLabel: 'Keep editing',
                            tone: 'danger',
                        });
                    }
                    return window.confirm('You have unsaved brand changes. Leave edit mode without saving?');
                }
                return true;
            },
            onAfterClose: async function () {
                editorDocument = null;
                formEl.innerHTML = '<p class="brand-editor-locked-note">Select a brand from the pool.</p>';
                await loadBrandDocuments(selectedBrandId);
            },
        });

        function syncBrandUrl(brandId, editing = isEditing) {
            lifecycle.syncUrl(brandId, editing);
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
            const label = isHeading ? 'Heading font:' : 'Main font:';
            return `
                <div class="brand-token-field brand-token-field--preset brand-token-field--inline">
                    <label for="brand-font-preset-${kind}">${label}</label>
                    <select id="brand-font-preset-${kind}" data-font-preset-select="${kind}" ${locked ? 'disabled' : ''}>${options.join('')}</select>
                    <input type="text" class="brand-custom-token-input" id="brand-font-custom-${kind}" data-token-path="${tokenPath}" value="${escapeHtml(currentValue)}" placeholder="e.g. Georgia, serif" ${locked || !customVisible ? 'hidden' : ''} ${locked ? 'disabled' : ''}>
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
            const hexInput = chip.querySelector('input.brand-color-hex-input');
            const picker = chip.querySelector('input.brand-color-picker');
            const controls = chip.querySelector('.brand-color-controls');
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
                controls.style.setProperty('--brand-swatch-color', hex);
            }
            const label = chip.querySelector('.brand-color-label');
            const labelText = label ? String(label.textContent || '').trim() : 'Color';
            chip.title = `${labelText}: ${hex.toUpperCase()}`;
        }

        function renderCompactColors(locked) {
            return `<div class="brand-color-compact-grid">${COLOR_FIELDS.map(([key, label]) => {
                const value = normalizeHexColor(tokenValue(editorDocument, `color.${key}`) || '#000000') || '#000000';
                return `<label class="brand-color-chip" title="${escapeHtml(label)}: ${escapeHtml(value.toUpperCase())}">
                    <span class="brand-color-label">${escapeHtml(label)}</span>
                    <span class="brand-color-controls" style="--brand-swatch-color:${escapeHtml(value)}">
                        <input type="text" class="brand-color-hex-input" data-token-path="color.${key}" value="${escapeHtml(value.toUpperCase())}" maxlength="7" spellcheck="false" autocomplete="off" inputmode="text" aria-label="${escapeHtml(label)} hex" ${locked ? 'disabled' : ''}>
                        <input type="color" class="brand-color-picker" value="${escapeHtml(value)}" tabindex="-1" aria-label="${escapeHtml(label)} color picker" title="Open color picker" ${locked ? 'disabled' : ''}>
                    </span>
                </label>`;
            }).join('')}</div>`;
        }

        function normalizeRoleKey(value, fallback) {
            const key = String(value || '').trim().toLowerCase();
            if (ROLE_PALETTE.some(([id]) => id === key)) {
                return key;
            }
            return fallback || 'primary';
        }

        function rolePaletteHex(roleKey) {
            const key = normalizeRoleKey(roleKey, 'primary');
            const hex = normalizeHexColor(tokenValue(editorDocument, `color.${key}`) || '');
            if (hex) {
                return hex;
            }
            if (key === 'text') {
                return normalizeHexColor(tokenValue(editorDocument, 'color.text') || '#ffffff') || '#ffffff';
            }
            if (key === 'text_muted') {
                return normalizeHexColor(tokenValue(editorDocument, 'color.text_muted') || '#dddddd') || '#dddddd';
            }
            if (key === 'surface_mid') {
                return normalizeHexColor(tokenValue(editorDocument, 'color.surface_mid') || '#1e1e24') || '#1e1e24';
            }
            if (key === 'secondary') {
                return normalizeHexColor(tokenValue(editorDocument, 'color.secondary') || '#3a7bd5') || '#3a7bd5';
            }
            return normalizeHexColor(tokenValue(editorDocument, 'color.primary') || '#00d2ff') || '#00d2ff';
        }

        function roleLabel(roleKey) {
            const match = ROLE_PALETTE.find(([id]) => id === roleKey);
            return match ? match[1] : roleKey;
        }

        function renderRoleSwatch(rolePath, label, fallback, locked, options) {
            const opts = options && typeof options === 'object' ? options : {};
            const swatchOnly = !!opts.swatchOnly;
            const selected = normalizeRoleKey(tokenValue(editorDocument, rolePath), fallback);
            const hex = rolePaletteHex(selected);
            const labelClean = String(label || '').replace(/:\s*$/, '');
            const name = roleLabel(selected);
            const menuOptions = ROLE_PALETTE.map(([id, optionName]) => {
                const optionHex = rolePaletteHex(id);
                return `
                    <button type="button" class="brand-role-option${id === selected ? ' is-selected' : ''}"
                            data-role-pick="${escapeHtml(id)}" ${locked ? 'disabled' : ''}>
                        <span class="brand-role-option-swatch" style="background:${escapeHtml(optionHex)}"></span>
                        <span class="brand-role-option-label">${escapeHtml(optionName)}</span>
                    </button>`;
            }).join('');
            return `
                <div class="brand-role-field" data-role-path="${escapeHtml(rolePath)}">
                    <span class="brand-effect-label">${escapeHtml(label)}</span>
                    <div class="brand-role-swatch-wrap">
                        <button type="button" class="brand-role-swatch${swatchOnly ? ' brand-role-swatch--chip' : ''}"
                                aria-expanded="false"
                                aria-label="${escapeHtml(labelClean)}: ${escapeHtml(name)}"
                                title="${escapeHtml(name)}"
                                style="--brand-role-swatch:${escapeHtml(hex)}"
                                ${locked ? 'disabled' : ''}>
                            ${swatchOnly ? '' : `<span class="brand-role-swatch-name">${escapeHtml(name)}</span>`}
                        </button>
                        <div class="brand-role-menu" hidden role="listbox" aria-label="${escapeHtml(labelClean)} palette">
                            ${menuOptions}
                        </div>
                    </div>
                    <input type="hidden" data-token-path="${escapeHtml(rolePath)}" value="${escapeHtml(selected)}" ${locked ? 'disabled' : ''}>
                </div>`;
        }

        function effectIntToken(path, fallback) {
            const raw = tokenValue(editorDocument, path);
            if (raw === '') {
                return String(fallback);
            }
            const parsed = parseInt(raw, 10);
            return Number.isFinite(parsed) ? String(parsed) : String(fallback);
        }

        function renderBackdropFields(locked) {
            const dim = effectIntToken('effects.backdrop_dim', 72);
            return `
                <div class="brand-content-chrome-grid">
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Dim: <strong data-effect-value="backdrop_dim">${escapeHtml(dim)}</strong>%</span>
                        <input type="range" min="0" max="100" step="1" value="${escapeHtml(dim)}" data-token-path="effects.backdrop_dim" data-effect-range="backdrop_dim" ${locked ? 'disabled' : ''}>
                    </div>
                </div>
            `;
        }

        function renderPlayerReadabilityFields(locked) {
            const legacyDim = effectIntToken('effects.panel_dim', effectIntToken('effects.backdrop_dim', 72));
            const panelDim = effectIntToken('effects.player_panel_dim', legacyDim);
            const blur = effectIntToken('effects.player_panel_blur', effectIntToken('effects.panel_blur', 5));
            const density = normalizeDensityToken(tokenValue(editorDocument, 'effects.player_panel_density'), 'normal');
            let corners = String(tokenValue(editorDocument, 'effects.player_panel_corners') || 'shaved').trim().toLowerCase();
            if (corners === 'pill') {
                corners = 'shaved';
            }
            if (!['square', 'shaved'].includes(corners)) {
                corners = 'shaved';
            }
            let border = String(tokenValue(editorDocument, 'effects.player_panel_border') || 'none').trim().toLowerCase();
            if (!['none', 'thin', 'normal', 'fat'].includes(border)) {
                border = 'none';
            }

            function panelToggle(path, name, selected, options) {
                return `
                    <div class="brand-player-setting-toggle" role="group" aria-label="${escapeHtml(name)}">
                        ${options.map(([value, label]) => `
                            <label class="brand-player-setting-option">
                                <input type="radio" name="${escapeHtml(name)}" value="${escapeHtml(value)}"
                                       data-token-path="${escapeHtml(path)}"
                                       ${selected === value ? 'checked' : ''}
                                       ${locked ? 'disabled' : ''}>
                                <span>${escapeHtml(label)}</span>
                            </label>
                        `).join('')}
                    </div>`;
            }

            return `
                <div class="brand-content-chrome-grid">
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Fill: <strong data-effect-value="player_panel_dim">${escapeHtml(panelDim)}</strong>%</span>
                        <input type="range" min="0" max="100" step="1" value="${escapeHtml(panelDim)}" data-token-path="effects.player_panel_dim" data-effect-range="player_panel_dim" ${locked ? 'disabled' : ''}>
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Blur: <strong data-effect-value="player_panel_blur">${escapeHtml(blur)}</strong>px</span>
                        <input type="range" min="0" max="24" step="1" value="${escapeHtml(blur)}" data-token-path="effects.player_panel_blur" data-effect-range="player_panel_blur" ${locked ? 'disabled' : ''}>
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Corners:</span>
                        ${panelToggle('effects.player_panel_corners', 'brandPlayerPanelCorners', corners, [
                            ['square', 'Square'],
                            ['shaved', 'Shaved'],
                        ])}
                    </div>
                    <div class="brand-chrome-inline-row">
                        <div class="brand-effect-field brand-effect-field--inline">
                            <span class="brand-effect-label">Border:</span>
                            ${panelToggle('effects.player_panel_border', 'brandPlayerPanelBorder', border, [
                                ['none', 'None'],
                                ['thin', 'Thin'],
                                ['normal', 'Normal'],
                                ['fat', 'Fat'],
                            ])}
                        </div>
                        ${renderRoleSwatch('roles.player_panel_border', 'Colour:', ROLE_DEFAULTS.player_panel_border, locked, { swatchOnly: true })}
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Density:</span>
                        ${renderDensityToggle('effects.player_panel_density', 'brandPlayerPanelDensity', density, locked)}
                    </div>
                </div>
            `;
        }

        function renderContentReadabilityFields(locked) {
            const legacyDim = effectIntToken('effects.panel_dim', effectIntToken('effects.backdrop_dim', 72));
            const panelDim = effectIntToken('effects.content_panel_dim', legacyDim);
            const blur = effectIntToken('effects.content_panel_blur', effectIntToken('effects.panel_blur', 5));
            const density = normalizeDensityToken(tokenValue(editorDocument, 'effects.content_panel_density'), 'normal');
            let corners = String(tokenValue(editorDocument, 'effects.content_panel_corners') || 'shaved').trim().toLowerCase();
            if (corners === 'pill') {
                corners = 'shaved';
            }
            if (!['square', 'shaved'].includes(corners)) {
                corners = 'shaved';
            }
            let border = String(tokenValue(editorDocument, 'effects.content_panel_border') || 'none').trim().toLowerCase();
            if (!['none', 'thin', 'normal', 'fat'].includes(border)) {
                border = 'none';
            }

            function panelToggle(path, name, selected, options) {
                return `
                    <div class="brand-player-setting-toggle" role="group" aria-label="${escapeHtml(name)}">
                        ${options.map(([value, label]) => `
                            <label class="brand-player-setting-option">
                                <input type="radio" name="${escapeHtml(name)}" value="${escapeHtml(value)}"
                                       data-token-path="${escapeHtml(path)}"
                                       ${selected === value ? 'checked' : ''}
                                       ${locked ? 'disabled' : ''}>
                                <span>${escapeHtml(label)}</span>
                            </label>
                        `).join('')}
                    </div>`;
            }

            return `
                <div class="brand-content-chrome-grid">
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Fill: <strong data-effect-value="content_panel_dim">${escapeHtml(panelDim)}</strong>%</span>
                        <input type="range" min="0" max="100" step="1" value="${escapeHtml(panelDim)}" data-token-path="effects.content_panel_dim" data-effect-range="content_panel_dim" ${locked ? 'disabled' : ''}>
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Blur: <strong data-effect-value="content_panel_blur">${escapeHtml(blur)}</strong>px</span>
                        <input type="range" min="0" max="24" step="1" value="${escapeHtml(blur)}" data-token-path="effects.content_panel_blur" data-effect-range="content_panel_blur" ${locked ? 'disabled' : ''}>
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Corners:</span>
                        ${panelToggle('effects.content_panel_corners', 'brandPanelCorners', corners, [
                            ['square', 'Square'],
                            ['shaved', 'Shaved'],
                        ])}
                    </div>
                    <div class="brand-chrome-inline-row">
                        <div class="brand-effect-field brand-effect-field--inline">
                            <span class="brand-effect-label">Border:</span>
                            ${panelToggle('effects.content_panel_border', 'brandPanelBorder', border, [
                                ['none', 'None'],
                                ['thin', 'Thin'],
                                ['normal', 'Normal'],
                                ['fat', 'Fat'],
                            ])}
                        </div>
                        ${renderRoleSwatch('roles.panel_border', 'Colour:', ROLE_DEFAULTS.panel_border, locked, { swatchOnly: true })}
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Density:</span>
                        ${renderDensityToggle('effects.content_panel_density', 'brandPanelDensity', density, locked)}
                    </div>
                </div>
            `;
        }

        function contentToken(path, fallback) {
            const raw = String(tokenValue(editorDocument, path) || '').trim().toLowerCase();
            return raw !== '' ? raw : fallback;
        }

        function normalizeDensityToken(value, fallback) {
            let density = String(value || '').trim().toLowerCase();
            if (density === 'minimal') {
                density = 'dense';
            }
            if (['dense', 'compact', 'normal', 'comfortable', 'spacious'].includes(density)) {
                return density;
            }
            return fallback || 'normal';
        }

        function renderDensityToggle(path, name, selected, locked) {
            const options = [
                ['dense', 'Dense'],
                ['compact', 'Compact'],
                ['normal', 'Normal'],
                ['comfortable', 'Comfortable'],
                ['spacious', 'Spacious'],
            ];
            return `
                <div class="brand-player-setting-toggle" role="group" aria-label="Density">
                    ${options.map(([value, label]) => `
                        <label class="brand-player-setting-option">
                            <input type="radio" name="${escapeHtml(name)}" value="${escapeHtml(value)}"
                                   data-token-path="${escapeHtml(path)}"
                                   ${selected === value ? 'checked' : ''}
                                   ${locked ? 'disabled' : ''}>
                            <span>${escapeHtml(label)}</span>
                        </label>
                    `).join('')}
                </div>`;
        }

        function renderPlaylistSelectorFields(locked) {
            const playlistSelected = normalizePlaylistSelectorMode(editorDocument?.player?.playlist_selector);
            const playlistRadios = [
                ['dropdown', 'Dropdown'],
                ['buttons', 'Buttons'],
                ['coverflow', 'Cover flow'],
            ].map(([value, label]) => `
                <label class="brand-player-setting-option">
                    <input type="radio" name="brandPlaylistSelector" value="${escapeHtml(value)}"
                           data-player-path="playlist_selector"
                           ${playlistSelected === value ? 'checked' : ''}
                           ${locked ? 'disabled' : ''}>
                    <span>${escapeHtml(label)}</span>
                </label>`).join('');

            return `
                <div class="brand-content-chrome-grid">
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Style:</span>
                        <div class="brand-player-setting-toggle" role="group" aria-label="Playlist selector style">
                            ${playlistRadios}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderButtonsFields(locked) {
            const style = contentToken('content.style', 'outline');
            let corners = contentToken('content.corners', '');
            if (!['square', 'shaved', 'pill'].includes(corners)) {
                const radius = effectIntToken('content.radius_percent', 50);
                corners = radius <= 8 ? 'square' : (radius <= 30 ? 'shaved' : 'pill');
            }
            let border = contentToken('content.border', '');
            if (!['thin', 'normal', 'fat'].includes(border)) {
                const width = effectIntToken('content.border_width', 2);
                border = width <= 1 ? 'thin' : (width >= 3 ? 'fat' : 'normal');
            }
            const density = normalizeDensityToken(contentToken('content.density', 'normal'), 'normal');

            function contentToggle(path, name, selected, options) {
                return `
                    <div class="brand-player-setting-toggle" role="group" aria-label="${escapeHtml(name)}">
                        ${options.map(([value, label]) => `
                            <label class="brand-player-setting-option">
                                <input type="radio" name="${escapeHtml(name)}" value="${escapeHtml(value)}"
                                       data-token-path="${escapeHtml(path)}"
                                       ${selected === value ? 'checked' : ''}
                                       ${locked ? 'disabled' : ''}>
                                <span>${escapeHtml(label)}</span>
                            </label>
                        `).join('')}
                    </div>`;
            }

            return `
                <div class="brand-content-chrome-grid">
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Style:</span>
                        ${contentToggle('content.style', 'brandContentStyle', style === 'filled' ? 'filled' : style, [
                            ['outline', 'Outline'],
                            ['soft', 'Soft fill'],
                            ['filled', 'Solid'],
                        ])}
                    </div>
                    <div class="brand-chrome-inline-row">
                        ${renderRoleSwatch('roles.button_outline', 'Outline:', ROLE_DEFAULTS.button_outline, locked, { swatchOnly: true })}
                        ${renderRoleSwatch('roles.button_fill', 'Fill:', ROLE_DEFAULTS.button_fill, locked, { swatchOnly: true })}
                        ${renderRoleSwatch('roles.button_active', 'Active:', ROLE_DEFAULTS.button_active, locked, { swatchOnly: true })}
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Corners:</span>
                        ${contentToggle('content.corners', 'brandContentCorners', corners, [
                            ['square', 'Square'],
                            ['shaved', 'Shaved'],
                            ['pill', 'Pill'],
                        ])}
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Borders:</span>
                        ${contentToggle('content.border', 'brandContentBorder', border, [
                            ['thin', 'Thin'],
                            ['normal', 'Normal'],
                            ['fat', 'Fat'],
                        ])}
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Density:</span>
                        ${renderDensityToggle('content.density', 'brandContentDensity', density, locked)}
                    </div>
                </div>
            `;
        }

        function brandTitleValue() {
            return titleInput instanceof HTMLInputElement
                ? String(titleInput.value || '').trim()
                : '';
        }

        function brandSettingsDirty() {
            return brandTitleValue() !== brandSettingsBaseline.title;
        }

        function renderBrandHeadBadges(document) {
            if (!headBadges || !document) {
                return;
            }
            const isActive = document.id === activeBrandId;
            const locked = !!document.locked;
            const badges = [];
            if (isActive) {
                badges.push('<span class="brand-editor-badge brand-editor-badge--active">Base</span>');
            }
            if (locked) {
                badges.push('<span class="brand-editor-badge brand-editor-badge--locked">Locked</span>');
            }
            headBadges.innerHTML = badges.join('');
        }

        function syncBrandSettingsPanel(document) {
            const title = String(document?.title || document?.id || '');
            brandSettingsBaseline = { title };
            if (titleInput instanceof HTMLInputElement) {
                titleInput.value = title;
                titleInput.disabled = !brandMayEdit(document);
            }
            renderBrandHeadBadges(document);
            if (settingsStatus) {
                settingsStatus.textContent = '';
            }
        }

        async function saveBrandSettings({ silent = false } = {}) {
            if (brandSettingsSaving) {
                brandSettingsSaveQueued = true;
                return true;
            }
            if (!editorDocument || !brandMayEdit(editorDocument)) {
                return true;
            }
            if (!(titleInput instanceof HTMLInputElement)) {
                return true;
            }

            const title = brandTitleValue();
            if (!title) {
                if (!silent && settingsStatus) {
                    settingsStatus.textContent = 'Brand name is required.';
                }
                return false;
            }

            if (!brandSettingsDirty()) {
                if (!silent && settingsStatus) {
                    settingsStatus.textContent = '';
                }
                return true;
            }

            const brandId = String(editorDocument.id || '').trim();
            brandSettingsSaving = true;
            if (!silent && settingsStatus) {
                settingsStatus.textContent = 'Saving…';
            }

            try {
                const data = await fetchJson(`/biblioteca/manage-brand.php?brand=${encodeURIComponent(brandId)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ title }),
                });
                if (data?.active_brand_id) {
                    activeBrandId = String(data.active_brand_id);
                }
                editorDocument.title = title;
                if (previewDocument) {
                    previewDocument.title = title;
                }
                brands = Array.isArray(data.brands) ? data.brands : brands;
                brandSettingsBaseline = { title };
                renderPoolList();
                renderPreview(previewDocument);
                if (!silent && settingsStatus) {
                    settingsStatus.textContent = 'Saved.';
                }
                return true;
            } catch (error) {
                if (!silent && settingsStatus) {
                    settingsStatus.textContent = error.message || 'Could not save brand';
                }
                return false;
            } finally {
                brandSettingsSaving = false;
                if (brandSettingsSaveQueued) {
                    brandSettingsSaveQueued = false;
                    saveBrandSettings({ silent: true }).catch(() => {});
                }
            }
        }

        async function saveBrandDocument() {
            if (!editorDocument) {
                return false;
            }
            if (!brandMayEdit(editorDocument)) {
                notifyBrandError('This brand is locked. Duplicate it to customise, or unlock on localhost for PCF source edits.');
                return false;
            }
            collectFormIntoDocument();
            const title = brandTitleValue();
            if (!title) {
                if (settingsStatus) {
                    settingsStatus.textContent = 'Brand name is required.';
                }
                notifyBrandError('Brand name is required.');
                return false;
            }
            editorDocument.title = title;
            try {
                saveUi?.markSaving();
                const data = await fetchJson('/biblioteca/save-brand.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify(editorDocument),
                });
                editorDocument = data.document || editorDocument;
                previewDocument = cloneDocument(editorDocument);
                renderPreview(previewDocument);
                renderForm();
                const entry = brands.find((item) => item.id === editorDocument.id);
                if (entry) {
                    entry.title = editorDocument.title;
                }
                renderPoolList();
                saveUi?.markSaved();
                brandSettingsBaseline = { title: editorDocument.title };
                if (settingsStatus) {
                    settingsStatus.textContent = '';
                }
                return true;
            } catch (error) {
                saveUi?.markFailed();
                notifyBrandError(error.message || 'Could not save brand');
                return false;
            }
        }

        async function confirmBrandContentLeave() {
            const modal = window.bandpromoEditorUnsavedModal;
            if (!hasUnsavedChanges()) {
                return true;
            }
            if (!modal || typeof modal.confirmLeave !== 'function') {
                if (typeof window.bandpromoConfirm === 'function') {
                    return window.bandpromoConfirm({
                        title: 'Unsaved brand changes',
                        body: 'You have unsaved brand changes. Switch brands without saving?',
                        confirmLabel: 'Switch without saving',
                        cancelLabel: 'Keep editing',
                        tone: 'danger',
                    });
                }
                return window.confirm('You have unsaved brand changes. Switch brands without saving?');
            }
            const result = await modal.confirmLeave({
                isDirty: () => hasUnsavedChanges(),
                message: 'This brand has unsaved changes. What would you like to do?',
                fallbackMessage: 'You have unsaved brand changes. Switch brands without saving?',
                save: () => saveBrandDocument(),
                discard: () => {
                    editorDocument = cloneDocument(previewDocument);
                    renderForm();
                    saveUi?.setBaseline();
                },
            });
            return result === 'proceed';
        }

        function applyFontPresetSelection(kind, presetKey) {
            if (!editorDocument || !brandMayEdit(editorDocument)) return;
            const customInput = formEl.querySelector(`#brand-font-custom-${kind}`);
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

        function assetBasename(path) {
            const raw = String(path || '').trim().replace(/\\/g, '/');
            if (!raw) return '';
            const parts = raw.split('/');
            return parts[parts.length - 1] || raw;
        }

        function mediaKindFromPath(path) {
            const raw = String(path || '').trim().replace(/\\/g, '/').toLowerCase();
            const name = assetBasename(path).toLowerCase();
            if (/\/media\/visual\/delivery\/.+\/standard-stream/.test(raw) || /\.(mp4|webm|mov|m4v|ogv|mkv)$/i.test(name)) {
                return 'video';
            }
            if (/\.(png|jpe?g|gif|webp|svg|bmp|avif)$/i.test(name)) return 'image';
            if (/\.(mp3|flac|wav|ogg|m4a|aac|aiff?)$/i.test(name)) return 'audio';
            if (raw.includes('/media/sfx/optimal/')) return 'audio';
            if (raw.includes('/media/visual/delivery/')) return 'image';
            return 'other';
        }

        function kindLabel(kind) {
            if (kind === 'image') return 'Still';
            if (kind === 'video') return 'Living';
            if (kind === 'audio') return 'Audio';
            return 'File';
        }

        const SHELL_MEDIA_FIELDS = [
            {
                key: 'logo',
                label: 'Logo',
                emptyLabel: 'No logo selected',
                accept: ['image'],
                clearable: false,
                pickerTargets: 'special',
                pickerTitle: 'Choose logo',
                note: 'Shown on login and in the player header.',
            },
            {
                key: 'poster',
                label: 'Poster / share cover',
                emptyLabel: 'No poster selected',
                accept: ['image'],
                clearable: false,
                pickerTargets: 'special',
                pickerTitle: 'Choose poster / share cover',
                note: 'Share cards and shell presentation cover.',
            },
            {
                key: 'background_image',
                label: 'Still background',
                emptyLabel: 'No still background',
                accept: ['image'],
                clearable: true,
                pickerTargets: 'special',
                pickerTitle: 'Choose still background',
                note: 'Still backdrop on login and player.',
            },
            {
                key: 'background_video',
                label: 'Living background',
                emptyLabel: 'No living background',
                accept: ['video'],
                clearable: true,
                pickerTargets: 'special',
                pickerTitle: 'Choose living background',
                note: 'Video backdrop (falls back to still when needed).',
            },
            {
                key: 'welcome_audio',
                label: 'Welcome audio',
                emptyLabel: 'No welcome audio',
                accept: ['audio'],
                clearable: true,
                pickerTargets: 'sfx',
                pickerTitle: 'Choose welcome audio',
                note: 'Short sound on the login screen.',
            },
            {
                key: 'loggedin_audio',
                label: 'Logged-in audio',
                emptyLabel: 'No logged-in audio',
                accept: ['audio'],
                clearable: true,
                pickerTargets: 'sfx',
                pickerTitle: 'Choose logged-in audio',
                note: 'Sound after visitors enter the site.',
            },
        ];

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
                return `<div class="brand-shell-slot-empty" aria-hidden="true">${icon}</div>
                    <span class="brand-shell-slot-status">${escapeHtml(field.emptyLabel)}</span>`;
            }
            const kind = mediaKindFromPath(path);
            if (kind === 'image') {
                return `<img class="brand-shell-slot-thumb" src="${escapeHtml(path)}" alt="" loading="lazy">`;
            }
            if (kind === 'video') {
                return `<video class="brand-shell-slot-thumb" src="${escapeHtml(path)}" muted loop playsinline autoplay preload="auto"></video>`;
            }
            return `<div class="brand-shell-slot-empty brand-shell-slot-empty--audio" aria-hidden="true">♪</div>
                <span class="brand-shell-slot-status">Sound effect assigned</span>
                <button type="button" class="icon-btn brand-shell-slot-listen" data-shell-listen="${escapeHtml(path)}" title="Listen" aria-label="Listen to assigned sound">▶</button>`;
        }

        function renderShellMediaFields(locked) {
            const assets = editorDocument?.assets && typeof editorDocument.assets === 'object'
                ? editorDocument.assets
                : {};
            const slots = SHELL_MEDIA_FIELDS.map((field) => {
                const value = String(assets[field.key] || '').trim();
                const filledClass = value ? ' is-filled' : '';
                const chooseBtn = !locked
                    ? `<button type="button" class="icon-btn media-picker-open audio-master-cover-action brand-shell-slot-choose"
                            data-field="brand_asset_${escapeHtml(field.key)}"
                            data-title="${escapeHtml(field.pickerTitle || `Choose ${field.label}`)}"
                            data-targets="${escapeHtml(field.pickerTargets || 'special')}"
                            data-accept="${escapeHtml(field.accept.join(','))}"
                            data-brand="${escapeHtml(String(editorDocument?.id || ''))}"
                            title="${escapeHtml(field.pickerTitle || `Choose ${field.label}`)}"
                            aria-label="${escapeHtml(field.pickerTitle || `Choose ${field.label}`)}">✎</button>`
                    : '';
                const clearBtn = field.clearable && !locked
                    ? `<button type="button" class="icon-btn audio-master-cover-action brand-shell-slot-clear"
                            data-shell-clear="${escapeHtml(field.key)}"
                            title="Clear"
                            aria-label="Clear ${escapeHtml(field.label)}">↺</button>`
                    : '';
                const overlay = (chooseBtn || clearBtn)
                    ? `<div class="brand-shell-slot-overlay-actions audio-master-cover-overlay-actions">${chooseBtn}${clearBtn}</div>`
                    : '';
                return `
                    <div class="brand-shell-slot${filledClass}${locked ? ' is-locked' : ''}"
                         data-shell-slot="${escapeHtml(field.key)}"
                         data-accept="${escapeHtml(field.accept.join(','))}">
                        <div class="brand-shell-slot-head">
                            <strong>${escapeHtml(field.label)}</strong>
                            <span class="brand-shell-slot-kind">${escapeHtml(field.accept.map(kindLabel).join(' · '))}</span>
                        </div>
                        <div class="brand-shell-slot-preview">
                            ${overlay}
                            <div class="brand-shell-slot-media">
                                ${renderShellSlotPreviewHtml(field, value)}
                            </div>
                        </div>
                        <input type="hidden" id="brand_asset_${escapeHtml(field.key)}" value="${escapeHtml(value)}"
                               data-asset-key="${escapeHtml(field.key)}"
                               data-asset-id="${escapeHtml(String(editorDocument?.asset_ids?.[field.key] || ''))}"
                               data-empty-label="${escapeHtml(field.emptyLabel)}">
                        <p class="brand-shell-slot-note">${escapeHtml(field.note)}</p>
                    </div>`;
            }).join('');

            const slotHint = locked
                ? 'bandPromo Default is locked — shell media cannot be changed here.'
                : 'Click ✎ on a slot to choose compatible media already curated under Files → Brand assets.';

            return renderEditorSection('Media', `
                    <p class="brand-field-hint">${slotHint}</p>
                    <div class="brand-shell-media-grid" id="brandShellSlots">
                        ${slots}
                    </div>
            `, 'brand-editor-section--shell-media');
        }

        function normalizePlaylistSelectorMode(value) {
            const mode = String(value || '').trim().toLowerCase();
            if (mode === 'dropdown' || mode === 'buttons' || mode === 'coverflow') {
                return mode;
            }
            return 'coverflow';
        }

        function renderOnOffToggle(name, path, isOn, locked) {
            const selected = isOn ? 'on' : 'off';
            return `
                <div class="brand-player-setting-toggle" role="group" aria-label="${escapeHtml(name)}">
                    <label class="brand-player-setting-option">
                        <input type="radio" name="${escapeHtml(name)}" value="on"
                               data-player-path="${escapeHtml(path)}"
                               ${selected === 'on' ? 'checked' : ''}
                               ${locked ? 'disabled' : ''}>
                        <span>On</span>
                    </label>
                    <label class="brand-player-setting-option">
                        <input type="radio" name="${escapeHtml(name)}" value="off"
                               data-player-path="${escapeHtml(path)}"
                               ${selected === 'off' ? 'checked' : ''}
                               ${locked ? 'disabled' : ''}>
                        <span>Off</span>
                    </label>
                </div>`;
        }

        function normalizeCoverSize(value) {
            const size = String(value || '').trim().toLowerCase();
            return ['full', 'medium', 'half'].includes(size) ? size : 'full';
        }

        function normalizeSideCoversSpread(value) {
            const spread = String(value || '').trim().toLowerCase();
            return ['close', 'normal', 'wide'].includes(spread) ? spread : 'normal';
        }

        function normalizeSideCoversColour(value) {
            let colour = String(value || '').trim().toLowerCase();
            if (colour === 'gray') {
                colour = 'grey';
            }
            return ['full', 'soft', 'grey'].includes(colour) ? colour : 'soft';
        }

        function renderPlayerCoverFields(locked) {
            const reflectionOn = editorDocument?.player?.cover_reflection !== false;
            const coverSize = normalizeCoverSize(editorDocument?.player?.cover_size);
            const sideOn = editorDocument?.player?.side_covers !== false;
            const opacity = Math.max(5, Math.min(60, parseInt(String(editorDocument?.player?.side_covers_opacity ?? 20), 10) || 20));
            const spread = normalizeSideCoversSpread(editorDocument?.player?.side_covers_spread);
            const colour = normalizeSideCoversColour(editorDocument?.player?.side_covers_colour);
            const navigateOn = editorDocument?.player?.side_covers_navigate !== false;
            const sizeRadios = [
                ['full', 'Full'],
                ['medium', 'Medium'],
                ['half', 'Half'],
            ].map(([value, label]) => `
                <label class="brand-player-setting-option">
                    <input type="radio" name="brandCoverSize" value="${escapeHtml(value)}"
                           data-player-path="cover_size"
                           ${coverSize === value ? 'checked' : ''}
                           ${locked ? 'disabled' : ''}>
                    <span>${escapeHtml(label)}</span>
                </label>`).join('');
            const spreadRadios = [
                ['close', 'Close'],
                ['normal', 'Normal'],
                ['wide', 'Wide'],
            ].map(([value, label]) => `
                <label class="brand-player-setting-option">
                    <input type="radio" name="brandSideCoversSpread" value="${escapeHtml(value)}"
                           data-player-path="side_covers_spread"
                           ${spread === value ? 'checked' : ''}
                           ${locked || !sideOn ? 'disabled' : ''}>
                    <span>${escapeHtml(label)}</span>
                </label>`).join('');
            const colourRadios = [
                ['full', 'Full'],
                ['soft', 'Soft'],
                ['grey', 'Grey'],
            ].map(([value, label]) => `
                <label class="brand-player-setting-option">
                    <input type="radio" name="brandSideCoversColour" value="${escapeHtml(value)}"
                           data-player-path="side_covers_colour"
                           ${colour === value ? 'checked' : ''}
                           ${locked || !sideOn ? 'disabled' : ''}>
                    <span>${escapeHtml(label)}</span>
                </label>`).join('');

            return `
                <div class="brand-content-chrome-grid">
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Size:</span>
                        <div class="brand-player-setting-toggle" role="group" aria-label="Cover size">
                            ${sizeRadios}
                        </div>
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Reflection:</span>
                        ${renderOnOffToggle('brandCoverReflection', 'cover_reflection', reflectionOn, locked)}
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Side covers:</span>
                        ${renderOnOffToggle('brandSideCovers', 'side_covers', sideOn, locked)}
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline" ${sideOn ? '' : 'hidden'}>
                        <span class="brand-effect-label">Fill: <strong data-effect-value="side_covers_opacity">${escapeHtml(String(opacity))}</strong>%</span>
                        <input type="range" min="5" max="60" step="1" value="${escapeHtml(String(opacity))}"
                               name="brandSideCoversOpacity"
                               data-player-path="side_covers_opacity"
                               data-effect-range="side_covers_opacity"
                               ${locked || !sideOn ? 'disabled' : ''}>
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline" ${sideOn ? '' : 'hidden'}>
                        <span class="brand-effect-label">Spread:</span>
                        <div class="brand-player-setting-toggle" role="group" aria-label="Side cover spread">
                            ${spreadRadios}
                        </div>
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline" ${sideOn ? '' : 'hidden'}>
                        <span class="brand-effect-label">Colour:</span>
                        <div class="brand-player-setting-toggle" role="group" aria-label="Side cover colour">
                            ${colourRadios}
                        </div>
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline" ${sideOn ? '' : 'hidden'}>
                        <span class="brand-effect-label">Navigate:</span>
                        ${renderOnOffToggle('brandSideCoversNavigate', 'side_covers_navigate', navigateOn, locked || !sideOn)}
                    </div>
                </div>
            `;
        }

        function renderPlayerUserAreaFields(locked) {
            const loginStatusOn = editorDocument?.player?.login_status === true;
            const beggarsOn = editorDocument?.player?.beggars_banquet !== false;
            return `
                <div class="brand-content-chrome-grid">
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Login / status:</span>
                        ${renderOnOffToggle('brandLoginStatus', 'login_status', loginStatusOn, locked)}
                    </div>
                    <div class="brand-effect-field brand-effect-field--inline">
                        <span class="brand-effect-label">Beggars banquet:</span>
                        ${renderOnOffToggle('brandBeggarsBanquet', 'beggars_banquet', beggarsOn, locked)}
                    </div>
                </div>
            `;
        }

        function updateShellSlotDom(key) {
            const field = shellFieldByKey(key);
            const slot = formEl.querySelector(`[data-shell-slot="${key}"]`);
            if (!field || !slot) return;
            const value = String(editorDocument?.assets?.[key] || '').trim();
            const assetId = String(editorDocument?.asset_ids?.[key] || '').trim();
            const input = slot.querySelector(`[data-asset-key="${key}"]`);
            if (input instanceof HTMLInputElement) {
                input.value = value;
                input.dataset.assetId = assetId;
            }
            const media = slot.querySelector('.brand-shell-slot-media');
            if (media) {
                media.innerHTML = renderShellSlotPreviewHtml(field, value);
                if (window.bandpromoBrandPreview?.startVideos) {
                    window.bandpromoBrandPreview.startVideos(media);
                }
            }
            slot.classList.toggle('is-filled', !!value);
        }

        function setShellAssetValue(key, path, { silent = false, assetId = '', kind = '' } = {}) {
            if (!editorDocument || !brandMayEdit(editorDocument)) return false;
            const field = shellFieldByKey(key);
            if (!field) return false;
            const next = String(path || '').trim();
            const nextAssetId = String(assetId || '').trim();
            if (next) {
                const resolvedKind = String(kind || '').trim() || mediaKindFromPath(next);
                if (!shellSlotAcceptsKind(field, resolvedKind)) {
                    if (!silent) {
                        notifyBrandError(`${field.label} accepts ${field.accept.map(kindLabel).join(' / ')} only.`);
                    }
                    return false;
                }
            } else if (!field.clearable) {
                if (!silent) {
                    notifyBrandError(`${field.label} cannot be cleared.`);
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

        function bindShellMediaUi() {
            if (window.bandpromoBrandPreview?.startVideos) {
                window.bandpromoBrandPreview.startVideos(formEl);
            }
            formEl.querySelectorAll('[data-shell-listen]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const path = String(button.getAttribute('data-shell-listen') || '').trim();
                    if (!path) {
                        return;
                    }
                    if (typeof window.toggleShellMediaListen === 'function') {
                        window.toggleShellMediaListen(path);
                        return;
                    }
                    const audio = new Audio(path);
                    audio.play().catch(() => {
                        notifyBrandError('Could not play that sound effect.');
                    });
                });
            });

            if (!brandMayEdit(editorDocument)) {
                return;
            }

            formEl.querySelectorAll('[data-shell-clear]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const key = button.getAttribute('data-shell-clear') || '';
                    setShellAssetValue(key, '');
                });
            });

            window.bandpromoShellMediaPicked = function bandpromoShellMediaPicked(key, path, assetId, kind) {
                setShellAssetValue(String(key || ''), path, {
                    assetId: String(assetId || ''),
                    kind: String(kind || ''),
                });
            };
        }

        function renderPreview(document) {
            if (!document) {
                previewEl.innerHTML = '<p class="brand-editor-empty">No brand selected.</p>';
                updateActionButtons(null);
                return;
            }
            if (window.bandpromoBrandPreview?.render) {
                window.bandpromoBrandPreview.render(previewEl, document, {
                    styleId: 'bandpromo-brand-editor-preview-style',
                    selector: '#brandEditorPreview .theme-preview-shell-chrome',
                    mode: previewMode,
                });
            } else {
                previewEl.innerHTML = '<p class="brand-editor-empty">Brand preview is unavailable.</p>';
            }
            updateActionButtons(document);
        }

        function updateActionButtons(document) {
            const mayEdit = brandMayEdit(document);
            const isActive = document && document.id === activeBrandId;
            if (saveBtn) {
                if (!isEditing || !mayEdit) {
                    saveBtn.hidden = true;
                } else {
                    saveBtn.hidden = false;
                    saveUi?.reconcile();
                }
            }
            if (setActiveBtn) {
                setActiveBtn.hidden = !document;
                setActiveBtn.disabled = !!isActive;
                setActiveBtn.textContent = isActive ? '✓ Base brand' : '★ Set as base';
                setActiveBtn.classList.toggle('btn-saved', !!isActive);
            }
        }

        function showPoolView() {
            lifecycle.showPoolView();
        }

        function showEditView(brandId) {
            lifecycle.showEditView(brandId);
        }

        function brandEntry(brandId) {
            return brands.find((entry) => entry && entry.id === brandId) || null;
        }

        function brandCanDelete(entry) {
            if (!entry || entry.locked || brandIsPlatformDefault(entry)) {
                return false;
            }
            return String(entry.id || '') !== activeBrandId;
        }

        function brandMetaHtml(entry) {
            if (!entry) return '';
            const parts = [];
            if (entry.locked) parts.push('locked');
            if (entry.id === activeBrandId) {
                parts.push('<span class="brand-pool-meta-active">base</span>');
            }
            return parts.join(' · ');
        }

        function closeBrandDeleteModal() {
            pendingBrandDeleteId = '';
            if (deleteModal) {
                deleteModal.style.display = 'none';
                deleteModal.setAttribute('aria-hidden', 'true');
            }
        }

        async function openBrandDeleteModal(brandId) {
            const entry = brandEntry(brandId);
            if (!entry || !brandCanDelete(entry)) {
                return;
            }
            const title = String(entry.title || brandId);
            if (!deleteModal) {
                const confirmed = typeof window.bandpromoConfirm === 'function'
                    ? await window.bandpromoConfirm({
                        title: 'Delete brand?',
                        body: `Delete brand "${title}"? Its settings will be lost. This cannot be undone.`,
                        confirmLabel: 'Delete brand',
                        tone: 'danger',
                    })
                    : window.confirm(`Delete brand "${title}"? Its settings will be lost. This cannot be undone.`);
                if (!confirmed) {
                    return;
                }
                deleteBrand(brandId).catch((error) => notifyBrandError(error.message || 'Could not delete brand'));
                return;
            }
            pendingBrandDeleteId = brandId;
            if (deleteModalName) {
                deleteModalName.textContent = title;
            }
            deleteModal.style.display = 'flex';
            deleteModal.setAttribute('aria-hidden', 'false');
            deleteConfirmBtn?.focus();
        }

        async function deleteBrand(brandId) {
            const entry = brandEntry(brandId);
            if (!entry || !brandCanDelete(entry)) {
                return;
            }
            const data = await fetchJson(`/biblioteca/manage-brand.php?brand=${encodeURIComponent(brandId)}`, {
                method: 'DELETE',
                credentials: 'same-origin',
            });
            brands = Array.isArray(data.brands) ? data.brands : brands;
            activeBrandId = String(data.active_brand_id || activeBrandId);
            if (selectedBrandId === brandId) {
                selectedBrandId = brands[0]?.id || 'setup-default';
                if (isEditing) {
                    showPoolView();
                    syncBrandUrl(selectedBrandId, false);
                    editorDocument = null;
                    formEl.innerHTML = '<p class="brand-editor-locked-note">Select a brand from the pool.</p>';
                } else {
                    syncBrandUrl(selectedBrandId, false);
                }
                await loadBrandDocuments(selectedBrandId);
            } else if (previewDocument?.id === brandId) {
                selectedBrandId = brands[0]?.id || 'setup-default';
                syncBrandUrl(selectedBrandId, false);
                await loadBrandDocuments(selectedBrandId);
            } else {
                renderPreview(previewDocument);
            }
            renderPoolList();
        }

        function renderPoolList() {
            window.bandpromoRegistryList.render(poolList, {
                entries: brands,
                selectedId: selectedBrandId,
                dataAttribute: 'brand-id',
                emptyMessage: 'No brands available.',
                renderRow: function (entry, isSelected) {
                    const id = String(entry?.id || '');
                    const label = String(entry?.title || id).trim() || id;
                    const activeClass = id === activeBrandId ? ' brand-pool-row--active' : '';
                    const actions = [];
                    if (brandMayEdit(entry)) {
                        actions.push(
                            window.bandpromoRegistryList.actionButton({
                                icon: '✏️',
                                title: `Edit ${label}`,
                                className: 'registry-btn--edit',
                                dataAttribute: `data-brand-id="${escapeHtml(id)}"`,
                            })
                        );
                    }
                    actions.push(
                        window.bandpromoRegistryList.actionButton({
                            icon: '⧉',
                            title: `Duplicate ${label}`,
                            className: 'registry-btn--duplicate',
                            dataAttribute: `data-brand-id="${escapeHtml(id)}"`,
                        })
                    );
                    if (brandCanDelete(entry)) {
                        actions.push(
                            `<button type="button" class="icon-btn icon-btn--pool icon-btn--danger registry-btn--delete" data-brand-id="${escapeHtml(id)}" title="Delete brand" aria-label="Delete ${escapeHtml(label)}">🗑️</button>`
                        );
                    }
                    return window.bandpromoRegistryList.row({
                        id: id,
                        dataAttribute: 'data-brand-id',
                        isSelected: isSelected,
                        icon: '🎨',
                        title: label,
                        meta: brandMetaHtml(entry),
                        extraClasses: `brand-pool-row${activeClass}`,
                        actions: actions,
                    });
                },
            });
        }

        function renderForm() {
            if (!editorDocument) {
                formEl.innerHTML = '<p class="brand-editor-locked-note">Select a brand from the pool.</p>';
                return;
            }

            const fieldsLocked = !brandMayEdit(editorDocument);
            const fontBase = tokenValue(editorDocument, 'typography.font_family_base');
            const fontHeading = tokenValue(editorDocument, 'typography.font_family_heading');

            const description = String(editorDocument.mood || '').trim();

            formEl.innerHTML = `
                ${fieldsLocked ? '<p class="brand-editor-locked-note">bandPromo Default is protected. Duplicate it to customise this brand.</p>' : ''}
                ${!fieldsLocked && editorDocument.locked && brandIsPlatformDefault(editorDocument)
                    ? '<p class="brand-editor-locked-note">Localhost PCF edit: platform default is editable here. Remote installs stay locked.</p>'
                    : ''}
                <div class="brand-editor-subnav" id="brandEditorSubnav" role="tablist" aria-label="Brand editor sections">
                    <button type="button" class="brand-editor-subnav-btn" data-brand-editor-tab="common" aria-pressed="false">Common</button>
                    <button type="button" class="brand-editor-subnav-btn" data-brand-editor-tab="player" aria-pressed="false">Player</button>
                    <button type="button" class="brand-editor-subnav-btn" data-brand-editor-tab="content" aria-pressed="false">Content</button>
                </div>
                <div class="brand-editor-tab-panel" data-brand-editor-panel="common" role="tabpanel">
                    ${renderEditorSection('Base info', `
                        <div class="brand-token-grid brand-token-grid--stacked">
                            <div class="brand-token-field">
                                <label for="brandBrandDescription">Description</label>
                                <textarea id="brandBrandDescription" data-brand-field="mood" maxlength="500" rows="3" ${fieldsLocked ? 'disabled' : ''}>${escapeHtml(description)}</textarea>
                            </div>
                        </div>
                    `)}
                    ${renderEditorSection('Backdrop', `
                        ${renderBackdropFields(fieldsLocked)}
                    `, 'brand-editor-section--backdrop')}
                    ${renderEditorSection('Colours', `
                        ${renderCompactColors(fieldsLocked)}
                    `, 'brand-editor-section--colors')}
                    ${renderShellMediaFields(fieldsLocked)}
                </div>
                <div class="brand-editor-tab-panel" data-brand-editor-panel="player" role="tabpanel" hidden>
                    ${renderEditorSection('Cover', `
                        ${renderPlayerCoverFields(fieldsLocked)}
                    `, 'brand-editor-section--player-cover')}
                    ${renderEditorSection('Controls', `
                        ${renderPlayerReadabilityFields(fieldsLocked)}
                    `, 'brand-editor-section--player-controls')}
                    ${renderEditorSection('User area', `
                        ${renderPlayerUserAreaFields(fieldsLocked)}
                    `, 'brand-editor-section--player-user-area')}
                </div>
                <div class="brand-editor-tab-panel" data-brand-editor-panel="content" role="tabpanel" hidden>
                    ${renderEditorSection('Buttons', `
                        ${renderButtonsFields(fieldsLocked)}
                    `, 'brand-editor-section--content-chrome', 'content-chrome')}
                    ${renderEditorSection('Playlist selector', `
                        ${renderPlaylistSelectorFields(fieldsLocked)}
                    `, 'brand-editor-section--playlist-selector', 'playlist-selector')}
                    ${renderEditorSection('Panels', `
                        ${renderContentReadabilityFields(fieldsLocked)}
                    `, 'brand-editor-section--content-panels', 'content-panels')}
                    ${renderEditorSection('Typography', `
                        <div class="brand-content-chrome-grid">
                            ${renderFontPresetSelect('base', fontBase, fieldsLocked)}
                            ${renderFontPresetSelect('heading', fontHeading, fieldsLocked)}
                            <div class="brand-role-row">
                                ${renderRoleSwatch('roles.heading', 'Headings:', ROLE_DEFAULTS.heading, fieldsLocked, { swatchOnly: true })}
                                ${renderRoleSwatch('roles.heading_sub', 'Subheadings:', ROLE_DEFAULTS.heading_sub, fieldsLocked, { swatchOnly: true })}
                                ${renderRoleSwatch('roles.body', 'Body:', ROLE_DEFAULTS.body, fieldsLocked, { swatchOnly: true })}
                                ${renderRoleSwatch('roles.muted', 'Muted / small:', ROLE_DEFAULTS.muted, fieldsLocked, { swatchOnly: true })}
                                ${renderRoleSwatch('roles.blockquote', 'Blockquote:', ROLE_DEFAULTS.blockquote, fieldsLocked, { swatchOnly: true })}
                            </div>
                            <div class="brand-effect-field brand-effect-field--inline">
                                <span class="brand-effect-label">Density:</span>
                                ${renderDensityToggle(
                                    'typography.density',
                                    'brandTypographyDensity',
                                    normalizeDensityToken(tokenValue(editorDocument, 'typography.density'), 'normal'),
                                    fieldsLocked
                                )}
                            </div>
                        </div>
                    `, 'brand-editor-section--typography', 'content-panels')}
                </div>
            `;

            syncBrandSettingsPanel(editorDocument);
            bindEditorTabUi();
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
                const inputAssetId = String(input.dataset.assetId || '').trim();
                editorDocument.assets[key] = next;
                if (next === '') {
                    // Keep slot asset id when the path is empty (delivery may be missing).
                    // Explicit clears go through setShellAssetValue('', …) which wipes dataset.assetId.
                    const keepId = String(input.dataset.assetId || editorDocument.asset_ids[key] || '').trim();
                    if (keepId !== '') {
                        editorDocument.asset_ids[key] = keepId;
                        return;
                    }
                    editorDocument.asset_ids[key] = '';
                    input.dataset.assetId = '';
                    return;
                }
                if (inputAssetId !== '') {
                    editorDocument.asset_ids[key] = inputAssetId;
                    return;
                }
                if (next !== previous) {
                    editorDocument.asset_ids[key] = '';
                }
            });
        }

        function collectFormIntoDocument() {
            if (!editorDocument || !brandMayEdit(editorDocument)) {
                return;
            }
            collectAssetsFromForm();
            if (!editorDocument.player || typeof editorDocument.player !== 'object') {
                editorDocument.player = {};
            }
            const playlistSelector = formEl.querySelector('input[name="brandPlaylistSelector"]:checked');
            if (playlistSelector instanceof HTMLInputElement) {
                editorDocument.player.playlist_selector = normalizePlaylistSelectorMode(playlistSelector.value);
            }
            const beggarsToggle = formEl.querySelector('input[name="brandBeggarsBanquet"]:checked');
            if (beggarsToggle instanceof HTMLInputElement) {
                editorDocument.player.beggars_banquet = beggarsToggle.value !== 'off';
            }
            const loginStatusToggle = formEl.querySelector('input[name="brandLoginStatus"]:checked');
            if (loginStatusToggle instanceof HTMLInputElement) {
                editorDocument.player.login_status = loginStatusToggle.value !== 'off';
            }
            const reflectionToggle = formEl.querySelector('input[name="brandCoverReflection"]:checked');
            if (reflectionToggle instanceof HTMLInputElement) {
                editorDocument.player.cover_reflection = reflectionToggle.value !== 'off';
            }
            const coverSizeToggle = formEl.querySelector('input[name="brandCoverSize"]:checked');
            if (coverSizeToggle instanceof HTMLInputElement) {
                editorDocument.player.cover_size = normalizeCoverSize(coverSizeToggle.value);
            }
            const sideCoversToggle = formEl.querySelector('input[name="brandSideCovers"]:checked');
            if (sideCoversToggle instanceof HTMLInputElement) {
                editorDocument.player.side_covers = sideCoversToggle.value !== 'off';
            }
            const sideOpacityInput = formEl.querySelector('input[name="brandSideCoversOpacity"]');
            if (sideOpacityInput instanceof HTMLInputElement) {
                const opacity = Math.max(5, Math.min(60, parseInt(sideOpacityInput.value, 10) || 20));
                editorDocument.player.side_covers_opacity = opacity;
            }
            const sideSpread = formEl.querySelector('input[name="brandSideCoversSpread"]:checked');
            if (sideSpread instanceof HTMLInputElement) {
                editorDocument.player.side_covers_spread = normalizeSideCoversSpread(sideSpread.value);
            }
            const sideColour = formEl.querySelector('input[name="brandSideCoversColour"]:checked');
            if (sideColour instanceof HTMLInputElement) {
                editorDocument.player.side_covers_colour = normalizeSideCoversColour(sideColour.value);
            }
            const sideNavigate = formEl.querySelector('input[name="brandSideCoversNavigate"]:checked');
            if (sideNavigate instanceof HTMLInputElement) {
                editorDocument.player.side_covers_navigate = sideNavigate.value !== 'off';
            }
            const descriptionInput = formEl.querySelector('[data-brand-field="mood"]');
            if (descriptionInput instanceof HTMLTextAreaElement || descriptionInput instanceof HTMLInputElement) {
                editorDocument.mood = String(descriptionInput.value || '').trim();
            }
            formEl.querySelectorAll('[data-token-path]').forEach((input) => {
                if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement) || input.hidden) return;
                if (input instanceof HTMLInputElement && input.type === 'radio' && !input.checked) {
                    return;
                }
                const path = input.getAttribute('data-token-path') || '';
                if (!path) return;
                setTokenValue(editorDocument, path, String(input.value || '').trim());
            });
            previewDocument = cloneDocument(editorDocument);
            renderPreview(previewDocument);
            saveUi?.reconcile();
        }

        function hasUnsavedChanges() {
            return !!(saveBtn && saveBtn.classList.contains('btn-amber'));
        }

        async function loadRegistry() {
            const data = await fetchJson('/biblioteca/get-brands.php');
            brands = Array.isArray(data.brands) ? data.brands : [];
            activeBrandId = String(data.active_brand_id || 'setup-default');
            renderPoolList();
        }

        async function loadBrandDocuments(brandId) {
            const data = await fetchJson(`/biblioteca/get-brand.php?brand=${encodeURIComponent(brandId)}`);
            previewDocument = data.document || null;
            if (previewDocument && (!previewDocument.assets || typeof previewDocument.assets !== 'object')) {
                previewDocument.assets = {};
            }
            editorDocument = cloneDocument(previewDocument);
            activeBrandId = String(data.active_brand_id || activeBrandId);
            renderPreview(previewDocument);
            if (isEditing) {
                renderForm();
                saveUi?.setBaseline();
            }
        }

        async function requestCloseEditor() {
            return lifecycle.requestClose();
        }

        async function openBrandEditor(brandId) {
            if (!brandId) return;
            if (isEditing && brandId !== selectedBrandId) {
                if (brandSettingsDirty()) {
                    const saved = await saveBrandSettings();
                    if (!saved) {
                        return;
                    }
                }
                if (hasUnsavedChanges()) {
                    const leaveOk = await confirmBrandContentLeave();
                    if (!leaveOk) return;
                }
            }
            selectedBrandId = brandId;
            showEditView(brandId);
            try {
                await loadBrandDocuments(brandId);
                renderForm();
            } catch (error) {
                notifyBrandError(error.message || 'Could not load brand');
            }
        }

        async function selectBrandForPreview(brandId) {
            if (!brandId || (brandId === selectedBrandId && previewDocument && !isEditing)) {
                return;
            }
            if (isEditing) {
                await openBrandEditor(brandId);
                return;
            }
            if (hasUnsavedChanges()) {
                const leaveOk = await confirmBrandContentLeave();
                if (!leaveOk) return;
            }
            selectedBrandId = brandId;
            syncBrandUrl(brandId, false);
            renderPoolList();
            try {
                await loadBrandDocuments(brandId);
                renderPoolList();
            } catch (error) {
                notifyBrandError(error.message || 'Could not load brand preview');
            }
        }

        poolList.addEventListener('click', (event) => {
            const deleteBtn = event.target instanceof HTMLElement
                ? event.target.closest('.registry-btn--delete')
                : null;
            if (deleteBtn) {
                event.preventDefault();
                event.stopPropagation();
                const brandId = deleteBtn.getAttribute('data-brand-id') || '';
                openBrandDeleteModal(brandId);
                return;
            }

            const editBtn = event.target instanceof HTMLElement
                ? event.target.closest('.registry-btn--edit')
                : null;
            if (editBtn) {
                event.preventDefault();
                event.stopPropagation();
                const brandId = editBtn.getAttribute('data-brand-id') || '';
                openBrandEditor(brandId);
                return;
            }

            const rowDuplicateBtn = event.target instanceof HTMLElement
                ? event.target.closest('.registry-btn--duplicate')
                : null;
            if (rowDuplicateBtn) {
                event.preventDefault();
                event.stopPropagation();
                const brandId = rowDuplicateBtn.getAttribute('data-brand-id') || '';
                duplicateBrand(brandId);
                return;
            }

            const row = event.target instanceof HTMLElement
                ? event.target.closest('.registry-row')
                : null;
            if (!row || !poolList.contains(row)) return;
            const brandId = row.getAttribute('data-brand-id') || '';
            if (!brandId) return;
            selectBrandForPreview(brandId);
        });

        deleteCancelBtn?.addEventListener('click', closeBrandDeleteModal);
        deleteModal?.addEventListener('click', (event) => {
            if (event.target === deleteModal) {
                closeBrandDeleteModal();
            }
        });
        deleteConfirmBtn?.addEventListener('click', async () => {
            const brandId = pendingBrandDeleteId;
            if (!brandId) {
                return;
            }
            closeBrandDeleteModal();
            try {
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = true;
                }
                await deleteBrand(brandId);
            } catch (error) {
                notifyBrandError(error.message || 'Could not delete brand');
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
            closeBrandDeleteModal();
        });

        backBtn?.addEventListener('click', () => {
            requestCloseEditor();
        });

        if (window.bandpromoContentEditorBreadcrumb?.attach) {
            brandBreadcrumb = window.bandpromoContentEditorBreadcrumb.attach({
                currentId: 'brandEditorBreadcrumbCurrent',
                poolLinkId: 'brandEditorBreadcrumbPool',
                onPoolClick: () => {
                    if (isEditing) {
                        requestCloseEditor();
                        return;
                    }
                    lifecycle.showPoolView();
                },
            });
        }

        formEl.addEventListener('input', (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
                return;
            }
            if (input instanceof HTMLInputElement && input.classList.contains('brand-color-hex-input')) {
                const chip = input.closest('.brand-color-chip');
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
            if (input instanceof HTMLInputElement && input.classList.contains('brand-color-picker')) {
                const chip = input.closest('.brand-color-chip');
                if (chip) {
                    const hexInput = chip.querySelector('input.brand-color-hex-input');
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
                || input.hasAttribute('data-asset-key')
                || input.hasAttribute('data-brand-field')
                || input.hasAttribute('data-player-path')
                || input.hasAttribute('data-effect-range')
            ) {
                if (input.hasAttribute('data-effect-range')) {
                    const key = input.getAttribute('data-effect-range') || '';
                    const readout = formEl.querySelector(`[data-effect-value="${key}"]`);
                    if (readout) {
                        readout.textContent = String(input.value || '');
                    }
                }
                const focusId = previewFocusFromEventTarget(input);
                if (focusId !== '') {
                    revealPreviewFocus(focusId);
                }
                collectFormIntoDocument();
            }
        });

        formEl.addEventListener('click', (event) => {
            const target = event.target instanceof HTMLElement ? event.target : null;
            if (!target) {
                return;
            }

            const pickBtn = target.closest('.brand-role-option');
            if (pickBtn instanceof HTMLButtonElement && formEl.contains(pickBtn)) {
                event.preventDefault();
                const field = pickBtn.closest('.brand-role-field');
                const pick = String(pickBtn.getAttribute('data-role-pick') || '').trim();
                if (!field || !pick) {
                    return;
                }
                const hidden = field.querySelector('input[data-token-path]');
                const swatch = field.querySelector('.brand-role-swatch');
                const menu = field.querySelector('.brand-role-menu');
                const nameEl = field.querySelector('.brand-role-swatch-name');
                if (hidden instanceof HTMLInputElement) {
                    hidden.value = pick;
                }
                const hex = rolePaletteHex(pick);
                if (swatch instanceof HTMLElement) {
                    swatch.style.setProperty('--brand-role-swatch', hex);
                    swatch.setAttribute('aria-expanded', 'false');
                    swatch.title = roleLabel(pick);
                    swatch.setAttribute('aria-label', `${field.querySelector('.brand-effect-label')?.textContent || 'Colour'}: ${roleLabel(pick)}`);
                }
                if (nameEl) {
                    nameEl.textContent = roleLabel(pick);
                }
                field.querySelectorAll('.brand-role-option').forEach((btn) => {
                    btn.classList.toggle('is-selected', btn.getAttribute('data-role-pick') === pick);
                });
                if (menu instanceof HTMLElement) {
                    menu.hidden = true;
                }
                const focusId = previewFocusFromEventTarget(field);
                if (focusId !== '') {
                    revealPreviewFocus(focusId);
                }
                collectFormIntoDocument();
                return;
            }

            const swatchBtn = target.closest('.brand-role-swatch');
            if (swatchBtn instanceof HTMLButtonElement && formEl.contains(swatchBtn) && !swatchBtn.disabled) {
                event.preventDefault();
                const field = swatchBtn.closest('.brand-role-field');
                const menu = field?.querySelector('.brand-role-menu');
                if (!(menu instanceof HTMLElement)) {
                    return;
                }
                const willOpen = menu.hidden;
                formEl.querySelectorAll('.brand-role-menu').forEach((el) => {
                    if (el instanceof HTMLElement) {
                        el.hidden = true;
                    }
                });
                formEl.querySelectorAll('.brand-role-swatch').forEach((el) => {
                    if (el instanceof HTMLElement) {
                        el.setAttribute('aria-expanded', 'false');
                    }
                });
                if (willOpen) {
                    menu.hidden = false;
                    swatchBtn.setAttribute('aria-expanded', 'true');
                }
                return;
            }

            if (!target.closest('.brand-role-field')) {
                formEl.querySelectorAll('.brand-role-menu').forEach((el) => {
                    if (el instanceof HTMLElement) {
                        el.hidden = true;
                    }
                });
                formEl.querySelectorAll('.brand-role-swatch').forEach((el) => {
                    if (el instanceof HTMLElement) {
                        el.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });

        formEl.addEventListener('focusin', (event) => {
            const focusId = previewFocusFromEventTarget(event.target);
            if (focusId !== '') {
                revealPreviewFocus(focusId);
                return;
            }
            if (event.target instanceof Element
                && event.target.closest(
                    '.brand-editor-section--player-cover, .brand-editor-section--player-controls, .brand-editor-section--player-user-area'
                )
            ) {
                if (editorTab !== 'player') {
                    setEditorTab('player', { syncPreview: true });
                } else if (previewMode !== 'player') {
                    setPreviewMode('player', { forceRender: true });
                }
            }
        });

        formEl.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (target instanceof HTMLInputElement && target.classList.contains('brand-color-hex-input')) {
                const chip = target.closest('.brand-color-chip');
                const typed = String(target.value || '').trim();
                const hex = normalizeHexColor(typed.startsWith('#') ? typed : `#${typed}`);
                if (hex && chip) {
                    target.value = hex.toUpperCase();
                    syncColorChipPresentation(chip);
                    collectFormIntoDocument();
                } else if (chip) {
                    const picker = chip.querySelector('input.brand-color-picker');
                    target.value = normalizeHexColor(picker?.value) || '#000000';
                    target.value = String(target.value).toUpperCase();
                    target.classList.remove('is-invalid');
                    syncColorChipPresentation(chip);
                }
            }
            if (target instanceof HTMLSelectElement && target.hasAttribute('data-font-preset-select')) {
                applyFontPresetSelection(target.getAttribute('data-font-preset-select') || '', target.value);
            }
            if (target instanceof HTMLSelectElement && target.hasAttribute('data-token-path')) {
                const focusId = previewFocusFromEventTarget(target);
                if (focusId !== '') {
                    revealPreviewFocus(focusId);
                }
                collectFormIntoDocument();
            }
            if (target instanceof HTMLInputElement
                && target.type === 'radio'
                && target.hasAttribute('data-token-path')
            ) {
                const focusId = previewFocusFromEventTarget(target);
                if (focusId !== '') {
                    revealPreviewFocus(focusId);
                }
                collectFormIntoDocument();
            }
            if (target instanceof HTMLInputElement && (
                target.name === 'brandBeggarsBanquet'
                || target.name === 'brandLoginStatus'
                || target.name === 'brandCoverReflection'
                || target.name === 'brandCoverSize'
                || target.name === 'brandSideCovers'
                || target.name === 'brandSideCoversSpread'
                || target.name === 'brandSideCoversColour'
                || target.name === 'brandSideCoversNavigate'
            )) {
                if (editorTab !== 'player') {
                    setEditorTab('player', { syncPreview: true });
                } else if (previewMode !== 'player') {
                    setPreviewMode('player', { forceRender: true });
                }
                if (target.name === 'brandSideCovers') {
                    // Re-render so Dim/Spread/Colour/Navigate show or hide with Side covers.
                    collectFormIntoDocument();
                    renderForm();
                    saveUi?.reconcile();
                    return;
                }
                collectFormIntoDocument();
            }
            if (target instanceof HTMLInputElement && target.name === 'brandPlaylistSelector') {
                revealPreviewFocus('playlist-selector');
                collectFormIntoDocument();
            }
        });

        titleInput?.addEventListener('focusout', () => {
            saveBrandSettings();
        });

        titleInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                titleInput.blur();
            }
        });

        saveBtn?.addEventListener('click', async () => {
            const saved = await saveBrandDocument();
            if (saved && typeof window.bandpromoShowAdminToast === 'function') {
                window.bandpromoShowAdminToast('Brand saved.', 'success');
            }
        });

        setActiveBtn?.addEventListener('click', async () => {
            const document = isEditing ? editorDocument : previewDocument;
            if (!document) return;
            try {
                setActiveBtn.disabled = true;
                const data = await fetchJson('/biblioteca/set-active-brand.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ brand_id: document.id }),
                });
                activeBrandId = String(data.active_brand_id || document.id);
                renderPreview(previewDocument);
                if (isEditing) {
                    renderForm();
                }
                renderPoolList();
            } catch (error) {
                notifyBrandError(error.message || 'Could not set base brand');
            } finally {
                updateActionButtons(isEditing ? editorDocument : previewDocument);
            }
        });

        async function duplicateBrand(sourceId) {
            if (!sourceId) return;
            try {
                if (registryStatus) {
                    registryStatus.textContent = 'Duplicating brand…';
                    registryStatus.style.color = '';
                }
                const data = await fetchJson('/biblioteca/duplicate-brand.php', {
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
                    await openBrandEditor(newId);
                }
            } catch (error) {
                if (registryStatus) {
                    registryStatus.textContent = '❌ ' + error.message;
                    registryStatus.style.color = '#f87171';
                }
                notifyBrandError(error.message || 'Could not duplicate brand');
            }
        }

        const startInEdit = initialUrlParams.get('edit') === '1';

        loadRegistry()
            .catch((error) => {
                poolList.innerHTML = `<li class="editor-empty text-error">${escapeHtml(error.message)}</li>`;
            })
            .finally(async () => {
                if (startInEdit) {
                    await openBrandEditor(selectedBrandId);
                } else {
                    showPoolView();
                    syncBrandUrl(selectedBrandId, false);
                    try {
                        await loadBrandDocuments(selectedBrandId);
                    } catch (error) {
                        notifyBrandError(error.message || 'Could not load brand');
                    }
                }
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBandpromoBrandEditor);
    } else {
        initBandpromoBrandEditor();
    }
})();
