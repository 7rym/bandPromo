(function () {
    function initBandpromoThemeEditor() {
        const root = document.getElementById('themeEditorRoot');
        const poolView = document.getElementById('themePoolView');
        const editorView = document.getElementById('themeEditorView');
        const poolList = document.getElementById('themePoolList');
        const formEl = document.getElementById('themeEditorForm');
        const previewEl = document.getElementById('themeEditorPreview');
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

        const isLocalDevHost = window.BANDPROMO_LOCAL_DEV === true;

        function themeIsPlatformDefault(entryOrDoc) {
            if (entryOrDoc && typeof entryOrDoc.platform_default === 'boolean') {
                return entryOrDoc.platform_default;
            }
            const id = String(entryOrDoc?.id || '');
            return id === 'bandpromo-default' || id === 'setup-default';
        }

        function themeMayEdit(entryOrDoc) {
            if (entryOrDoc && typeof entryOrDoc.can_edit === 'boolean') {
                return entryOrDoc.can_edit;
            }
            if (!entryOrDoc?.locked) {
                return true;
            }
            return themeIsPlatformDefault(entryOrDoc) && isLocalDevHost;
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

        let themes = [];
        let activeThemeId = '';
        let selectedThemeId = String(root.dataset.initialTheme || 'setup-default');
        let previewDocument = null;
        let editorDocument = null;
        let isEditing = false;
        let themeSettingsBaseline = { title: '' };
        let themeSettingsSaving = false;
        let pendingThemeDeleteId = '';
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

        function renderEditorSection(title, innerHtml, extraClass) {
            const extra = extraClass ? ` ${extraClass}` : '';
            return `<div class="content-editor-section theme-editor-section${extra}">
                <div class="content-editor-section-head">
                    <h4 class="player-layout-col-title">${escapeHtml(title)}</h4>
                </div>
                <div class="content-editor-section-body">${innerHtml}</div>
            </div>`;
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

        function renderEffectsFields(locked) {
            const dim = String(tokenValue(editorDocument, 'effects.backdrop_dim') || '72');
            const blur = String(tokenValue(editorDocument, 'effects.panel_blur') || '5');
            return `
                <div class="theme-effects-grid">
                    <label class="theme-effect-field">
                        <span class="theme-effect-label">Backdrop dim <strong data-effect-value="backdrop_dim">${escapeHtml(dim)}</strong>%</span>
                        <input type="range" min="0" max="100" step="1" value="${escapeHtml(dim)}" data-token-path="effects.backdrop_dim" data-effect-range="backdrop_dim" ${locked ? 'disabled' : ''}>
                        <span class="theme-field-hint">Darkens the still/living shell background and fills lyrics, playlists, pages, gallery, and login panels.</span>
                    </label>
                    <label class="theme-effect-field">
                        <span class="theme-effect-label">Panel blur <strong data-effect-value="panel_blur">${escapeHtml(blur)}</strong>px</span>
                        <input type="range" min="0" max="24" step="1" value="${escapeHtml(blur)}" data-token-path="effects.panel_blur" data-effect-range="panel_blur" ${locked ? 'disabled' : ''}>
                        <span class="theme-field-hint">Glass blur on those same content panels (player chrome stays sharp).</span>
                    </label>
                </div>
            `;
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
                badges.push('<span class="theme-editor-badge theme-editor-badge--active">Base</span>');
            }
            if (locked) {
                badges.push('<span class="theme-editor-badge theme-editor-badge--locked">Locked</span>');
            }
            headBadges.innerHTML = badges.join('');
        }

        function syncThemeSettingsPanel(document) {
            const title = String(document?.title || document?.id || '');
            themeSettingsBaseline = { title };
            if (titleInput instanceof HTMLInputElement) {
                titleInput.value = title;
                titleInput.disabled = !themeMayEdit(document);
            }
            renderThemeHeadBadges(document);
            if (settingsStatus) {
                settingsStatus.textContent = '';
            }
        }

        async function saveThemeSettings({ silent = false } = {}) {
            if (themeSettingsSaving || !editorDocument || !themeMayEdit(editorDocument)) {
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
            if (!editorDocument || !themeMayEdit(editorDocument)) return;
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
                return `<div class="theme-shell-slot-empty" aria-hidden="true">${icon}</div>
                    <span class="theme-shell-slot-status">${escapeHtml(field.emptyLabel)}</span>`;
            }
            const kind = mediaKindFromPath(path);
            if (kind === 'image') {
                return `<img class="theme-shell-slot-thumb" src="${escapeHtml(path)}" alt="" loading="lazy">`;
            }
            if (kind === 'video') {
                return `<video class="theme-shell-slot-thumb" src="${escapeHtml(path)}" muted loop playsinline autoplay preload="auto"></video>`;
            }
            return `<div class="theme-shell-slot-empty theme-shell-slot-empty--audio" aria-hidden="true">♪</div>
                <span class="theme-shell-slot-status">Sound effect assigned</span>
                <button type="button" class="icon-btn theme-shell-slot-listen" data-shell-listen="${escapeHtml(path)}" title="Listen" aria-label="Listen to assigned sound">▶</button>`;
        }

        function renderShellMediaFields(locked) {
            const assets = editorDocument?.assets && typeof editorDocument.assets === 'object'
                ? editorDocument.assets
                : {};
            const slots = SHELL_MEDIA_FIELDS.map((field) => {
                const value = String(assets[field.key] || '').trim();
                const filledClass = value ? ' is-filled' : '';
                const chooseBtn = !locked
                    ? `<button type="button" class="icon-btn media-picker-open audio-master-cover-action theme-shell-slot-choose"
                            data-field="theme_asset_${escapeHtml(field.key)}"
                            data-title="${escapeHtml(field.pickerTitle || `Choose ${field.label}`)}"
                            data-targets="${escapeHtml(field.pickerTargets || 'special')}"
                            data-accept="${escapeHtml(field.accept.join(','))}"
                            data-brand="${escapeHtml(String(editorDocument?.id || ''))}"
                            title="${escapeHtml(field.pickerTitle || `Choose ${field.label}`)}"
                            aria-label="${escapeHtml(field.pickerTitle || `Choose ${field.label}`)}">✎</button>`
                    : '';
                const clearBtn = field.clearable && !locked
                    ? `<button type="button" class="icon-btn audio-master-cover-action theme-shell-slot-clear"
                            data-shell-clear="${escapeHtml(field.key)}"
                            title="Clear"
                            aria-label="Clear ${escapeHtml(field.label)}">↺</button>`
                    : '';
                const overlay = (chooseBtn || clearBtn)
                    ? `<div class="theme-shell-slot-overlay-actions audio-master-cover-overlay-actions">${chooseBtn}${clearBtn}</div>`
                    : '';
                return `
                    <div class="theme-shell-slot${filledClass}${locked ? ' is-locked' : ''}"
                         data-shell-slot="${escapeHtml(field.key)}"
                         data-accept="${escapeHtml(field.accept.join(','))}">
                        <div class="theme-shell-slot-head">
                            <strong>${escapeHtml(field.label)}</strong>
                            <span class="theme-shell-slot-kind">${escapeHtml(field.accept.map(kindLabel).join(' · '))}</span>
                        </div>
                        <div class="theme-shell-slot-preview">
                            ${overlay}
                            <div class="theme-shell-slot-media">
                                ${renderShellSlotPreviewHtml(field, value)}
                            </div>
                        </div>
                        <input type="hidden" id="theme_asset_${escapeHtml(field.key)}" value="${escapeHtml(value)}"
                               data-asset-key="${escapeHtml(field.key)}"
                               data-asset-id="${escapeHtml(String(editorDocument?.asset_ids?.[field.key] || ''))}"
                               data-empty-label="${escapeHtml(field.emptyLabel)}">
                        <p class="theme-shell-slot-note">${escapeHtml(field.note)}</p>
                    </div>`;
            }).join('');

            const slotHint = locked
                ? 'bandPromo Default is locked — shell media cannot be changed here.'
                : 'Click ✎ on a slot to choose compatible media already curated under Files → Brand assets.';

            return renderEditorSection('Shell media', `
                    <p class="theme-field-hint">${slotHint}</p>
                    <div class="theme-shell-media-grid" id="themeShellSlots">
                        ${slots}
                    </div>
            `, 'theme-editor-section--shell-media');
        }

        function normalizePlaylistSelectorMode(value) {
            const mode = String(value || '').trim().toLowerCase();
            if (mode === 'dropdown' || mode === 'buttons' || mode === 'coverflow') {
                return mode;
            }
            return 'coverflow';
        }

        function renderPlaylistSelectorFields(locked) {
            const selected = normalizePlaylistSelectorMode(editorDocument?.player?.playlist_selector);
            const beggarsOn = editorDocument?.player?.beggars_banquet !== false;
            const reflectionOn = editorDocument?.player?.cover_reflection !== false;
            const options = [
                ['dropdown', 'Dropdown'],
                ['buttons', 'Buttons'],
                ['coverflow', 'Cover flow'],
            ];
            const radios = options.map(([value, label]) => `
                <label class="theme-player-setting-option">
                    <input type="radio" name="themePlaylistSelector" value="${escapeHtml(value)}"
                           data-player-path="playlist_selector"
                           ${selected === value ? 'checked' : ''}
                           ${locked ? 'disabled' : ''}>
                    <span>${escapeHtml(label)}</span>
                </label>`).join('');

            return renderEditorSection('Player chrome', `
                    <p class="theme-field-hint">Playlist selector, cover mirror, and Beggars banquet. The Base brand’s choices apply site-wide on /play.</p>
                    <h6 class="theme-editor-subheading">Playlist selector</h6>
                    <p class="theme-field-hint">Shown in the Playlists tab when more than one playlist is available. Cover flow uses each playlist’s poster.</p>
                    <div class="theme-player-setting-toggle" role="group" aria-label="Playlist selector style">
                        ${radios}
                    </div>
                    <label class="theme-player-checkbox">
                        <input type="checkbox" name="themeCoverReflection" data-player-path="cover_reflection"
                               ${reflectionOn ? 'checked' : ''} ${locked ? 'disabled' : ''}>
                        <span>Cover reflection</span>
                    </label>
                    <p class="theme-field-hint">Mirrored cover under the main artwork on large split layouts (already hidden on small screens).</p>
                    <label class="theme-player-checkbox">
                        <input type="checkbox" name="themeBeggarsBanquet" data-player-path="beggars_banquet"
                               ${beggarsOn ? 'checked' : ''} ${locked ? 'disabled' : ''}>
                        <span>Beggars banquet</span>
                    </label>
                    <p class="theme-field-hint">In-flow support link under the player transport. Destination, label, and colors still come from Settings → Support.</p>
            `, 'theme-editor-section--player-chrome');
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
            const media = slot.querySelector('.theme-shell-slot-media');
            if (media) {
                media.innerHTML = renderShellSlotPreviewHtml(field, value);
                if (window.bandpromoThemePreview?.startVideos) {
                    window.bandpromoThemePreview.startVideos(media);
                }
            }
            slot.classList.toggle('is-filled', !!value);
        }

        function setShellAssetValue(key, path, { silent = false, assetId = '', kind = '' } = {}) {
            if (!editorDocument || !themeMayEdit(editorDocument)) return false;
            const field = shellFieldByKey(key);
            if (!field) return false;
            const next = String(path || '').trim();
            const nextAssetId = String(assetId || '').trim();
            if (next) {
                const resolvedKind = String(kind || '').trim() || mediaKindFromPath(next);
                if (!shellSlotAcceptsKind(field, resolvedKind)) {
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

        function bindShellMediaUi() {
            if (window.bandpromoThemePreview?.startVideos) {
                window.bandpromoThemePreview.startVideos(formEl);
            }
            // Listen works even when the brand is locked (preview only).
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
                        notifyThemeError('Could not play that sound effect.');
                    });
                });
            });

            if (!themeMayEdit(editorDocument)) {
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
                previewEl.innerHTML = '<p class="theme-editor-empty">No theme selected.</p>';
                updateActionButtons(null);
                return;
            }
            if (window.bandpromoThemePreview?.render) {
                window.bandpromoThemePreview.render(previewEl, document, {
                    styleId: 'bandpromo-theme-editor-preview-style',
                    selector: '#themeEditorPreview .theme-preview-shell-chrome',
                });
            } else {
                previewEl.innerHTML = '<p class="theme-editor-empty">Brand preview is unavailable.</p>';
            }
            updateActionButtons(document);
        }

        function updateActionButtons(document) {
            const mayEdit = themeMayEdit(document);
            const isActive = document && document.id === activeThemeId;
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
            isEditing = false;
            if (poolView) poolView.hidden = false;
            if (editorView) editorView.hidden = true;
            if (saveBtn) {
                saveBtn.hidden = true;
            }
            saveUi?.reset();
            renderPoolList();
            updateActionButtons(previewDocument);
        }

        function showEditView(themeId) {
            isEditing = true;
            selectedThemeId = themeId;
            if (poolView) poolView.hidden = true;
            if (editorView) editorView.hidden = false;
            syncThemeUrl(themeId, true);
            renderPoolList();
            updateActionButtons(editorDocument);
        }

        function themeEntry(themeId) {
            return themes.find((entry) => entry && entry.id === themeId) || null;
        }

        function themeCanDelete(entry) {
            if (!entry || entry.locked || themeIsPlatformDefault(entry)) {
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
                parts.push('<span class="theme-pool-meta-active">base</span>');
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
                const activeDot = id === activeThemeId ? '<span class="theme-pool-active-dot" title="Base brand">●</span>' : '';
                const title = escapeHtml(entry.title || id);
                const deleteBtn = themeCanDelete(entry)
                    ? `<button type="button" class="icon-btn icon-btn--pool icon-btn--danger page-pool-delete-btn" data-theme-id="${escapeHtml(id)}" title="Delete brand" aria-label="Delete ${title}">🗑️</button>`
                    : '';
                const editBtn = themeMayEdit(entry)
                    ? `<button type="button" class="icon-btn icon-btn--pool page-pool-edit-btn" data-theme-id="${escapeHtml(id)}" title="Edit brand" aria-label="Edit ${title}">✏️</button>`
                    : '';
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

            const fieldsLocked = !themeMayEdit(editorDocument);
            const fontBase = tokenValue(editorDocument, 'typography.font_family_base');
            const fontHeading = tokenValue(editorDocument, 'typography.font_family_heading');

            const description = String(editorDocument.mood || '').trim();

            formEl.innerHTML = `
                ${fieldsLocked ? '<p class="theme-editor-locked-note">bandPromo Default is protected. Duplicate it to customize this brand.</p>' : ''}
                ${!fieldsLocked && editorDocument.locked && themeIsPlatformDefault(editorDocument)
                    ? '<p class="theme-editor-locked-note">Localhost PCF edit: platform default is editable here. Remote installs stay locked.</p>'
                    : ''}
                ${renderEditorSection('Base info', `
                    <div class="theme-token-grid theme-token-grid--stacked">
                        <div class="theme-token-field">
                            <label for="themeBrandDescription">Description</label>
                            <textarea id="themeBrandDescription" data-brand-field="mood" maxlength="500" rows="3" ${fieldsLocked ? 'disabled' : ''}>${escapeHtml(description)}</textarea>
                        </div>
                    </div>
                `)}
                ${renderEditorSection('Typography', `
                    <div class="theme-token-grid theme-token-grid--stacked">
                        ${renderFontPresetSelect('base', fontBase, fieldsLocked)}
                        ${renderFontPresetSelect('heading', fontHeading, fieldsLocked)}
                    </div>
                `)}
                ${renderEditorSection('Colours', `
                    <p class="theme-field-hint">Type a hex colour (e.g. #FF6F61) or use the colour square. Accent transparency (alpha) is derived automatically from Primary/Secondary — not a separate control.</p>
                    ${renderCompactColors(fieldsLocked)}
                `, 'theme-editor-section--colors')}
                ${renderEditorSection('Readability', `
                    <p class="theme-field-hint">Dim busy still/living backdrops and soften glass panels so text stays readable.</p>
                    ${renderEffectsFields(fieldsLocked)}
                `, 'theme-editor-section--effects')}
                ${renderShellMediaFields(fieldsLocked)}
                ${renderPlaylistSelectorFields(fieldsLocked)}
            `;

            syncThemeSettingsPanel(editorDocument);
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
                    editorDocument.asset_ids[key] = '';
                    input.dataset.assetId = '';
                    return;
                }
                if (inputAssetId !== '') {
                    editorDocument.asset_ids[key] = inputAssetId;
                    return;
                }
                // Manual path edits clear the parallel asset_id unless unchanged.
                if (next !== previous) {
                    editorDocument.asset_ids[key] = '';
                }
            });
        }

        function collectFormIntoDocument() {
            if (!editorDocument || !themeMayEdit(editorDocument)) {
                return;
            }
            collectAssetsFromForm();
            if (!editorDocument.player || typeof editorDocument.player !== 'object') {
                editorDocument.player = {};
            }
            const playlistSelector = formEl.querySelector('input[name="themePlaylistSelector"]:checked');
            if (playlistSelector instanceof HTMLInputElement) {
                editorDocument.player.playlist_selector = normalizePlaylistSelectorMode(playlistSelector.value);
            }
            const beggarsToggle = formEl.querySelector('input[name="themeBeggarsBanquet"]');
            if (beggarsToggle instanceof HTMLInputElement) {
                editorDocument.player.beggars_banquet = !!beggarsToggle.checked;
            }
            const reflectionToggle = formEl.querySelector('input[name="themeCoverReflection"]');
            if (reflectionToggle instanceof HTMLInputElement) {
                editorDocument.player.cover_reflection = !!reflectionToggle.checked;
            }
            const descriptionInput = formEl.querySelector('[data-brand-field="mood"]');
            if (descriptionInput instanceof HTMLTextAreaElement || descriptionInput instanceof HTMLInputElement) {
                editorDocument.mood = String(descriptionInput.value || '').trim();
            }
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
                || input.hasAttribute('data-asset-key')
                || input.hasAttribute('data-brand-field')
            ) {
                if (input.hasAttribute('data-effect-range')) {
                    const key = input.getAttribute('data-effect-range') || '';
                    const readout = formEl.querySelector(`[data-effect-value="${key}"]`);
                    if (readout) {
                        readout.textContent = String(input.value || '');
                    }
                }
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
            if (target instanceof HTMLInputElement && (
                target.name === 'themePlaylistSelector'
                || target.name === 'themeBeggarsBanquet'
                || target.name === 'themeCoverReflection'
            )) {
                collectFormIntoDocument();
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
            if (!editorDocument) return;
            if (!themeMayEdit(editorDocument)) {
                notifyThemeError('This brand is locked. Duplicate it to customise, or unlock on localhost for PCF source edits.');
                return;
            }
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
                notifyThemeError(error.message || 'Could not set base brand');
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
