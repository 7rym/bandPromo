(function () {
    function initBandpromoPageEditor() {
        const layout = document.getElementById('pageEditorLayout');
        const root = document.getElementById('pageEditorRoot');
        if (!layout || !root) {
            return;
        }

        const poolView = document.getElementById('pagePoolView');
        const editorView = document.getElementById('pageEditorView');
        const poolList = document.getElementById('pagePoolList');
        const backBtn = document.getElementById('pageEditorBackBtn');
        const pageLabelFieldWrap = document.getElementById('pageLabelFieldWrap');

        let pages = [];
        try {
            pages = JSON.parse(root.dataset.pages || '[]');
        } catch (error) {
            pages = [];
        }
        if (!Array.isArray(pages)) {
            pages = [];
        }

        let currentPageKey = String(root.dataset.initialPage || pages[0]?.id || 'faq');
        let selectedPageId = currentPageKey;
        let isEditing = false;
        const BACK_TO_POOL = '__back__';

        const pageTitleInput = document.getElementById('pageTitleInput');
        const pageLabelInput = document.getElementById('pageLabelInput');
        const pageSettingsShortDescription = document.getElementById('pageSettingsShortDescription');
        const pageSettingsShortDescriptionCount = document.getElementById('pageSettingsShortDescriptionCount');
        const pageSettingsDescription = document.getElementById('pageSettingsDescription');
        const pageSettingsPosterAssetId = document.getElementById('pageSettingsPosterAssetId');
        const pageSettingsPosterAssetIdLabel = document.getElementById('pageSettingsPosterAssetId_label');
        function pageEntry(pageId) {
            return pages.find((entry) => entry && entry.id === pageId) || null;
        }

        function pageMetaLine(entry) {
            if (!entry) return '';
            if (entry.surface === 'login') {
                return 'Login / info lightbox';
            }
            if (entry.show_in_player) {
                const label = String(entry.label || '').trim();
                return label ? `Player tab: ${label}` : 'Shown in player';
            }
            return 'Not in player layout';
        }

        function syncPageUrl(pageId) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'content');
            url.searchParams.set('cntab', 'pages');
            url.searchParams.set('page', pageId);
            window.history.replaceState({}, '', url.toString());
        }

        function updateLabelFieldVisibility(pageId) {
            const entry = pageEntry(pageId);
            const isLoginOnly = entry && entry.surface === 'login';
            if (pageLabelFieldWrap) {
                pageLabelFieldWrap.hidden = !!isLoginOnly;
            }
        }

        function renderPoolList() {
            if (!poolList) return;
            if (!pages.length) {
                poolList.innerHTML = '<li class="player-layout-empty">No pages available yet.</li>';
                return;
            }

            poolList.innerHTML = pages.map((entry) => {
                const selectedClass = entry.id === selectedPageId ? ' playlist-editor-row-selected' : '';
                const title = `${entry.emoji || '📝'} ${entry.title || entry.label || entry.id}`.trim();
                const deleteBtn = !entry.required
                    ? `<button type="button" class="icon-btn icon-btn--pool icon-btn--danger page-pool-delete-btn" data-page-id="${escapeHtml(entry.id)}" data-page-title="${escapeHtml(entry.title || entry.label || entry.id)}" title="Delete page" aria-label="Delete ${escapeHtml(entry.title || entry.label || entry.id)}">🗑️</button>`
                    : '';
                return `<li class="playlist-editor-row page-pool-row${selectedClass}" data-page-id="${escapeHtml(entry.id)}" aria-selected="${entry.id === selectedPageId ? 'true' : 'false'}">
                    <span class="playlist-track-info">
                        <strong>${escapeHtml(title)}</strong>
                        <span class="playlist-track-meta">${escapeHtml(pageMetaLine(entry))}</span>
                    </span>
                    <span class="page-pool-row-actions">
                        <button type="button" class="icon-btn icon-btn--pool page-pool-edit-btn" data-page-id="${escapeHtml(entry.id)}" title="Edit page" aria-label="Edit ${escapeHtml(entry.title || entry.label || entry.id)}">✏️</button>
                        ${deleteBtn}
                    </span>
                </li>`;
            }).join('');
        }

        function showPoolView() {
            isEditing = false;
            if (poolView) poolView.hidden = false;
            if (editorView) editorView.hidden = true;
            if (saveBtn) {
                saveBtn.hidden = true;
            }
            renderPoolList();
        }

        function showEditorView(pageId) {
            isEditing = true;
            currentPageKey = pageId;
            selectedPageId = pageId;
            if (poolView) poolView.hidden = true;
            if (editorView) editorView.hidden = false;
            syncPageUrl(pageId);
            updateLabelFieldVisibility(pageId);
            renderPoolList();
        }

        function updatePoolEntry(pageId, meta) {
            const entry = pageEntry(pageId);
            if (!entry || !meta) return;
            if (meta.title) entry.title = meta.title;
            if (meta.label) entry.label = meta.label;
            renderPoolList();
        }

        async function loadPreviewOnly(pageId) {
            if (!previewEl) return;
            previewEl.innerHTML = '<p class="page-editor-empty">Loading preview…</p>';
            const resp = await fetch(`/biblioteca/get-page-document.php?page=${encodeURIComponent(pageId)}`, {
                credentials: 'same-origin',
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Could not load page preview');
            }
            if (typeof data.html === 'string') {
                setPreviewHtml(data.html);
            } else {
                previewEl.innerHTML = '<p class="page-editor-empty">Preview is not available for this page.</p>';
            }
        }

        async function selectPageForPreview(pageId) {
            if (!pageId || pageId === selectedPageId && !isEditing) {
                return;
            }
            selectedPageId = pageId;
            syncPageUrl(pageId);
            renderPoolList();
            try {
                await loadPreviewOnly(pageId);
                setStatus('', 'neutral');
            } catch (error) {
                if (previewEl) {
                    previewEl.innerHTML = `<p class="page-editor-empty">${escapeHtml(error.message)}</p>`;
                }
            }
        }

        async function openPageEditor(pageId) {
            if (!pageId) return;
            if (pageId !== currentPageKey && isEditing && hasUnsavedChanges()) {
                pendingNavHref = pageId;
                openUnsavedModal(pageId);
                return;
            }
            showEditorView(pageId);
            try {
                await loadDocument();
            } catch (error) {
                setStatus('❌ ' + error.message, 'error');
                if (blocksEl) {
                    blocksEl.innerHTML = `<p class="page-editor-empty">${escapeHtml(error.message)}</p>`;
                }
            }
        }

        function requestCloseEditor() {
            if (hasUnsavedChanges()) {
                openUnsavedModal(BACK_TO_POOL);
                return;
            }
            showPoolView();
        }

        poolList?.addEventListener('click', (event) => {
            const deleteBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-delete-btn')
                : null;
            if (deleteBtn) {
                event.preventDefault();
                event.stopPropagation();
                const pageId = deleteBtn.getAttribute('data-page-id') || '';
                const pageTitle = deleteBtn.getAttribute('data-page-title') || 'this page';
                if (pageId && typeof window.bandpromoOpenPageDeleteModal === 'function') {
                    window.bandpromoOpenPageDeleteModal(pageId, pageTitle);
                }
                return;
            }

            const editBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-edit-btn')
                : null;
            if (editBtn) {
                event.preventDefault();
                event.stopPropagation();
                const pageId = editBtn.getAttribute('data-page-id') || '';
                openPageEditor(pageId);
                return;
            }

            const row = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-row')
                : null;
            if (!row || !poolList.contains(row)) {
                return;
            }
            const pageId = row.getAttribute('data-page-id') || '';
            if (!pageId) return;
            if (isEditing && pageId !== currentPageKey && hasUnsavedChanges()) {
                pendingNavHref = pageId;
                openUnsavedModal(pageId);
                return;
            }
            if (isEditing && pageId !== currentPageKey) {
                openPageEditor(pageId);
                return;
            }
            selectPageForPreview(pageId);
        });

        backBtn?.addEventListener('click', () => {
            requestCloseEditor();
        });

        const unsavedModal = document.getElementById('pageUnsavedModal');
        const unsavedSaveBtn = document.getElementById('pageUnsavedSaveBtn');
        const unsavedDiscardBtn = document.getElementById('pageUnsavedDiscardBtn');
        const unsavedCancelBtn = document.getElementById('pageUnsavedCancelBtn');
        const blockDeleteModal = document.getElementById('pageBlockDeleteModal');
        const blockDeleteModalName = document.getElementById('pageBlockDeleteModalName');
        const blockDeleteConfirmBtn = document.getElementById('pageBlockDeleteConfirmBtn');
        const blockDeleteCancelBtn = document.getElementById('pageBlockDeleteCancelBtn');
        const blocksEl = document.getElementById('pageEditorBlocks');
        const previewEl = document.getElementById('pageEditorPreview');

        function setPreviewHtml(html) {
            if (!previewEl) {
                return;
            }
            previewEl.innerHTML = html;
            if (typeof window.bandpromoBindPageGalleryCarousels === 'function') {
                window.bandpromoBindPageGalleryCarousels(previewEl);
            }
        }
        const saveBtn = document.getElementById('pageSaveBtn');
        const pagePicturePickerField = document.getElementById('pagePicturePickerField');

        const PICTURE_STYLE_DEFAULTS = {
            width_num: 1,
            width_den: 2,
            flow: 'row',
        };

        const PICTURE_FLOWS = ['row', 'row-end', 'row-center', 'wrap-left', 'wrap-right', 'beside-left', 'beside-right'];

        const PICTURE_LAYOUT_TO_STYLE = {
            full: { width_num: 1, width_den: 1, flow: 'row' },
            centered: { width_num: 3, width_den: 4, flow: 'row' },
            'left-wrap': { width_num: 1, width_den: 2, flow: 'wrap-left' },
            'right-wrap': { width_num: 1, width_den: 2, flow: 'wrap-right' },
            'left-under': { width_num: 1, width_den: 2, flow: 'row' },
            'right-under': { width_num: 1, width_den: 2, flow: 'row-end' },
        };

        const PICTURE_LEGACY_SIZE_TO_FRACTION = {
            25: { width_num: 1, width_den: 4 },
            33: { width_num: 1, width_den: 3 },
            50: { width_num: 1, width_den: 2 },
            75: { width_num: 3, width_den: 4 },
            100: { width_num: 1, width_den: 1 },
        };

        let documentState = null;
        let pictureStyleMeta = {
            width_min: 1,
            width_max: 6,
            width_options: [1, 2, 3, 4, 5, 6],
            flows: [
                { value: 'row', label: 'In row' },
                { value: 'row-end', label: 'End of row' },
                { value: 'row-center', label: 'Full row' },
                { value: 'wrap-left', label: 'Wrap left' },
                { value: 'wrap-right', label: 'Wrap right' },
                { value: 'beside-left', label: 'Beside left' },
                { value: 'beside-right', label: 'Beside right' },
            ],
        };
        let galleryCatalog = [{ id: 'bandpromo-demo', title: 'bandPromo demo' }];
        let galleryPresets = ['grid', 'list', 'carousel', 'parallax'];
        const galleryPresetLabels = {
            grid: 'Grid',
            list: 'List',
            carousel: 'Carousel',
            parallax: 'Parallax',
        };
        const galleryHints = {
            grid: 'Mosaic of photos at their original ratios. Max across is a ceiling — narrower panes wrap to fewer columns.',
            list: 'Editorial rows: square thumb on the left, name on the right.',
            carousel: 'Snap-scroll hero with a peek of the next photo. Autorotate advances while this block is on screen (Slow 3s, Normal 2s, Fast 1s) and pauses when it is not.',
            parallax: 'Full-width scenes (layout still in progress).',
        };

        function galleryHint(preset) {
            return galleryHints[String(preset || 'grid')] || galleryHints.grid;
        }

        function normalizeGalleryId(galleryId) {
            const id = String(galleryId || '').trim();
            if (id === '' || id === 'main') {
                return 'bandpromo-demo';
            }
            return id;
        }

        function galleryTitle(galleryId) {
            const normalized = normalizeGalleryId(galleryId);
            const entry = galleryCatalog.find((item) => item.id === normalized);
            return entry?.title || normalized;
        }

        function renderGalleryEditor(block, index) {
            const activeGalleryId = normalizeGalleryId(block.gallery_id);
            const galleryOptions = galleryCatalog.map((entry) => {
                const id = String(entry.id || 'bandpromo-demo');
                const selected = activeGalleryId === id ? ' selected' : '';
                return `<option value="${escapeHtml(id)}"${selected}>${escapeHtml(entry.title || id)}</option>`;
            }).join('');
            const preset = String(block.preset || 'grid');
            const columns = Number(block.columns) >= 2 ? Number(block.columns) : 0;
            const presetChips = renderChipPool(
                index,
                'preset',
                'Layout',
                galleryPresets.map((value) => ({
                    value,
                    label: galleryPresetLabels[value] || value,
                })),
                preset
            );
            const columnChoices = [{ value: '0', label: 'Auto' }].concat(
                [2, 3, 4, 5, 6].map((count) => ({ value: String(count), label: String(count) }))
            );
            const columnChips = renderChipPool(index, 'columns', 'Max across', columnChoices, String(columns));
            const autorotateOn = block.autorotate === true;
            const autorotateSpeed = ['slow', 'normal', 'fast'].includes(String(block.autorotate_speed || ''))
                ? String(block.autorotate_speed)
                : 'normal';
            const autorotateChips = renderOnOffChipPool(index, 'autorotate', 'Autorotate', autorotateOn);
            const speedChips = renderChipPool(
                index,
                'autorotate_speed',
                'Speed',
                [
                    { value: 'slow', label: 'Slow' },
                    { value: 'normal', label: 'Normal' },
                    { value: 'fast', label: 'Fast' },
                ],
                autorotateSpeed
            );
            return `
                <div class="page-picture-style-bar" data-block-index="${index}">
                    <label class="page-picture-style-inline page-gallery-source-inline">
                        <span class="page-picture-style-label">Source</span>
                        <select class="page-gallery-source-select" data-field="gallery_id" data-block-index="${index}" aria-label="Gallery source">${galleryOptions}</select>
                    </label>
                    ${presetChips}
                    <div data-gallery-columns-for="${index}"${preset === 'grid' ? '' : ' hidden'}>${columnChips}</div>
                    <div data-gallery-carousel-opts-for="${index}"${preset === 'carousel' ? '' : ' hidden'}>
                        ${autorotateChips}
                        ${speedChips}
                    </div>
                </div>
                <p class="hint" data-gallery-hint-for="${index}">${escapeHtml(galleryHint(preset))}</p>
            `;
        }

        let imagePickerTargetIndex = null;
        let previewTimer = null;
        let previewRequestId = 0;
        let isDirtyState = false;
        let pendingNavHref = '';
        let allowUnloadWithoutSave = false;
        let suppressDirtyTracking = false;
        let baselineFingerprint = '';

        const saveUi = window.bandpromoContentSaveUi?.create(saveBtn, {
            saveLabel: '💾 Save changes',
            readFingerprint() {
                if (allowUnloadWithoutSave || suppressDirtyTracking || !documentState) {
                    return baselineFingerprint;
                }
                syncRichEditors();
                return buildDirtyFingerprint();
            },
        }) || null;

        function syncPageDocumentMetaFromForm() {
            if (!documentState || typeof documentState !== 'object') {
                return;
            }
            if (pageSettingsShortDescription instanceof HTMLTextAreaElement) {
                documentState.short_description = String(pageSettingsShortDescription.value || '').trim();
            }
            if (pageSettingsDescription instanceof HTMLTextAreaElement) {
                documentState.description = String(pageSettingsDescription.value || '').trim();
            }
            if (pageSettingsPosterAssetId instanceof HTMLInputElement) {
                documentState.poster_asset_id = String(pageSettingsPosterAssetId.value || '').trim();
            }
        }

        function syncPageDocumentMetaToForm() {
            const doc = documentState && typeof documentState === 'object' ? documentState : {};
            if (pageSettingsShortDescription instanceof HTMLTextAreaElement) {
                pageSettingsShortDescription.value = String(doc.short_description || '').trim();
            }
            if (pageSettingsDescription instanceof HTMLTextAreaElement) {
                pageSettingsDescription.value = String(doc.description || '').trim();
            }
            if (pageSettingsPosterAssetId instanceof HTMLInputElement) {
                pageSettingsPosterAssetId.value = String(doc.poster_asset_id || '').trim();
            }
            if (pageSettingsPosterAssetIdLabel) {
                const posterId = pageSettingsPosterAssetId instanceof HTMLInputElement
                    ? String(pageSettingsPosterAssetId.value || '').trim()
                    : '';
                pageSettingsPosterAssetIdLabel.textContent = posterId || 'No share image selected';
                pageSettingsPosterAssetIdLabel.classList.toggle('empty', posterId === '');
            }
            if (pageSettingsShortDescriptionCount && pageSettingsShortDescription instanceof HTMLTextAreaElement) {
                pageSettingsShortDescriptionCount.textContent = String(pageSettingsShortDescription.value.length);
            }
        }

        function pageMetaSnapshot() {
            return {
                title: pageTitleInput ? String(pageTitleInput.value || '').trim() : '',
                label: pageLabelInput ? String(pageLabelInput.value || '').trim() : '',
            };
        }

        function isDirty() {
            return isDirtyState;
        }

        function buildDirtyFingerprint() {
            syncPageDocumentMetaFromForm();
            const meta = pageMetaSnapshot();
            const blocks = Array.isArray(documentState?.blocks) ? documentState.blocks : [];
            const containerMeta = {
                short_description: String(documentState?.short_description || '').trim(),
                description: String(documentState?.description || '').trim(),
                poster_asset_id: String(documentState?.poster_asset_id || '').trim(),
            };
            return JSON.stringify({ meta, containerMeta, blocks });
        }

        function hasUnsavedChanges() {
            if (allowUnloadWithoutSave || suppressDirtyTracking || !documentState) {
                return false;
            }
            syncRichEditors();
            return buildDirtyFingerprint() !== baselineFingerprint;
        }

        function updateSaveButton() {
            if (!saveBtn || !isEditing) {
                if (saveBtn) saveBtn.hidden = true;
                return;
            }
            const dirty = hasUnsavedChanges();
            if (dirty !== isDirtyState) {
                isDirtyState = dirty;
            }
            saveUi?.reconcile();
        }

        function reconcileDirtyState() {
            if (suppressDirtyTracking || !documentState) return;
            const dirty = hasUnsavedChanges();
            if (dirty !== isDirtyState) {
                isDirtyState = dirty;
                updateSaveButton();
            }
        }

        function resetBaseline() {
            syncRichEditors();
            baselineFingerprint = buildDirtyFingerprint();
            isDirtyState = false;
            allowUnloadWithoutSave = false;
            saveUi?.setBaseline(baselineFingerprint);
        }

        function markDirty() {
            if (suppressDirtyTracking || isDirtyState) return;
            allowUnloadWithoutSave = false;
            isDirtyState = true;
            saveUi?.reconcile();
        }

        function syncMetaFromRegistry(registry) {
            if (!registry || typeof registry !== 'object') return;
            if (pageTitleInput && registry.title) {
                pageTitleInput.value = registry.title;
            }
            if (pageLabelInput && registry.label) {
                pageLabelInput.value = registry.label;
            }
        }

        function updateActiveTabLabel(meta) {
            updatePoolEntry(currentPageKey, meta);
        }

        function abandonUnsavedChanges() {
            syncRichEditors();
            baselineFingerprint = buildDirtyFingerprint();
            allowUnloadWithoutSave = true;
            isDirtyState = false;
            updateSaveButton();
        }

        function openUnsavedModal(href) {
            pendingNavHref = href;
            if (!unsavedModal) {
                if (href === BACK_TO_POOL) {
                    abandonUnsavedChanges();
                    showPoolView();
                    loadPreviewOnly(selectedPageId).catch(() => {});
                } else if (pages.some((entry) => entry && entry.id === href)) {
                    openPageEditor(href);
                } else if (href) {
                    window.location.href = href;
                }
                return;
            }
            unsavedModal.style.display = 'flex';
            unsavedModal.setAttribute('aria-hidden', 'false');
        }

        function closeUnsavedModal() {
            pendingNavHref = '';
            if (!unsavedModal) return;
            unsavedModal.style.display = 'none';
            unsavedModal.setAttribute('aria-hidden', 'true');
        }

        function isInternalAdminNavigationLink(link) {
            if (!(link instanceof HTMLAnchorElement)) return false;
            if (link.closest('#pageUnsavedModal, #mediaPickerModal')) return false;
            if (link.target && link.target !== '_self') return false;
            if (link.hasAttribute('download')) return false;

            let targetUrl;
            try {
                targetUrl = new URL(link.href, window.location.href);
            } catch (error) {
                return false;
            }

            const currentUrl = new URL(window.location.href);
            if (targetUrl.origin !== currentUrl.origin) return false;
            if (!currentUrl.pathname.endsWith('admin.php') || !targetUrl.pathname.endsWith('admin.php')) {
                return false;
            }
            if (targetUrl.href === currentUrl.href) {
                return false;
            }

            return true;
        }

        function guardAdminNavigation(event) {
            if (!hasUnsavedChanges()) {
                reconcileDirtyState();
                return;
            }
            const link = event.target instanceof HTMLElement ? event.target.closest('a[href]') : null;
            if (!isInternalAdminNavigationLink(link)) return;

            event.preventDefault();
            event.stopPropagation();
            openUnsavedModal(link.href);
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function richHtmlForEditor(value) {
            const text = String(value ?? '').trim();
            if (!text) {
                return '<p><br></p>';
            }
            return text;
        }

        function renderRichContent(value) {
            const text = String(value ?? '').trim();
            return text || '';
        }

        function blockLabel(block) {
            if (block?.type === 'picture') return 'Picture';
            if (block?.type === 'picture_richtext') return 'Picture + text';
            if (block?.type === 'video') return 'Video';
            if (block?.type === 'gallery') return 'Gallery';
            if (block?.type === 'list') return 'List';
            return 'Text';
        }

        function isPictureFamilyBlock(block) {
            return block?.type === 'picture' || block?.type === 'picture_richtext';
        }

        function isVisualMediaBlock(block) {
            return isPictureFamilyBlock(block) || block?.type === 'video';
        }

        function renderToolbarButton(index, field, format, iconHtml, title, extraAttrs = '', wide = false) {
            const wideClass = wide ? ' page-rich-btn--wide' : '';
            return `<button type="button" class="page-rich-btn${wideClass}" data-format="${format}" data-block-index="${index}" data-rich-field="${field}" title="${title}" aria-label="${title}"${extraAttrs}>${iconHtml}</button>`;
        }

        function renderWordToolbar(index, field) {
            const sep = '<span class="page-toolbar-sep" aria-hidden="true"></span>';
            const alignIcon = (modifier) => `
                <span class="page-toolbar-glyph-align page-toolbar-glyph-align--${modifier}" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
            `;

            return `
                <div class="page-word-toolbar" data-block-index="${index}" data-rich-field="${field}">
                    <div class="page-toolbar-style-group">
                        ${renderToolbarButton(index, field, 'block', '<span class="page-toolbar-glyph page-toolbar-glyph-p" aria-hidden="true">¶</span>', 'Normal text', ' data-block-tag="p"')}
                        ${renderToolbarButton(index, field, 'block', '<span class="page-toolbar-glyph page-toolbar-glyph-h1" aria-hidden="true">H</span>', 'Heading 1', ' data-block-tag="h1"')}
                        ${renderToolbarButton(index, field, 'block', '<span class="page-toolbar-glyph page-toolbar-glyph-h2" aria-hidden="true">H</span>', 'Heading 2', ' data-block-tag="h2"')}
                        ${renderToolbarButton(index, field, 'block', '<span class="page-toolbar-glyph page-toolbar-glyph-h3" aria-hidden="true">H</span>', 'Heading 3', ' data-block-tag="h3"')}
                        ${renderToolbarButton(index, field, 'block', '<span class="page-toolbar-glyph page-toolbar-glyph-small" aria-hidden="true">S</span>', 'Small text', ' data-block-tag="page-text-small"')}
                        ${renderToolbarButton(index, field, 'block', '<span class="page-toolbar-glyph page-toolbar-glyph-code" aria-hidden="true">&lt;/&gt;</span>', 'Code', ' data-block-tag="page-text-code"', true)}
                        ${renderToolbarButton(index, field, 'clear-format', '<span class="page-toolbar-glyph page-toolbar-glyph-clear" aria-hidden="true">⌫</span>', 'Clear formatting')}
                    </div>
                    ${sep}
                    ${renderToolbarButton(index, field, 'bold', '<strong class="page-toolbar-glyph" aria-hidden="true">B</strong>', 'Bold')}
                    ${renderToolbarButton(index, field, 'italic', '<em class="page-toolbar-glyph" aria-hidden="true">I</em>', 'Italic')}
                    ${renderToolbarButton(index, field, 'underline', '<span class="page-toolbar-glyph page-toolbar-glyph-u" aria-hidden="true">U</span>', 'Underline')}
                    ${renderToolbarButton(index, field, 'link', '<span class="page-toolbar-glyph page-toolbar-glyph-link" aria-hidden="true">🔗</span>', 'Link')}
                    ${sep}
                    ${renderToolbarButton(index, field, 'align-left', alignIcon('left'), 'Align left')}
                    ${renderToolbarButton(index, field, 'align-center', alignIcon('center'), 'Align center')}
                    ${renderToolbarButton(index, field, 'align-right', alignIcon('right'), 'Align right')}
                </div>
            `;
        }

        function renderRichEditor(index, field, value, compact) {
            const minClass = compact ? ' page-rich-editor--compact' : '';
            return `
                <div class="page-rich-editor-shell">
                    <div class="page-rich-toolbar-sticky">${renderWordToolbar(index, field)}</div>
                    <div class="page-rich-editor${minClass}" contenteditable="true" data-rich-editor="1" data-block-index="${index}" data-rich-field="${field}" spellcheck="true">${richHtmlForEditor(value)}</div>
                </div>
            `;
        }

        function clampPictureWidthPart(value) {
            const min = Number(pictureStyleMeta.width_min) || 1;
            const max = Number(pictureStyleMeta.width_max) || 6;
            const part = Number(value);
            if (!Number.isFinite(part)) return min;
            return Math.min(max, Math.max(min, Math.round(part)));
        }

        function normalizePictureFraction(num, den) {
            let widthNum = clampPictureWidthPart(num);
            let widthDen = clampPictureWidthPart(den);
            if (widthNum > widthDen) {
                widthDen = widthNum;
            }
            return { width_num: widthNum, width_den: widthDen };
        }

        function legacyStyleToFlow(align, text) {
            const normalizedAlign = ['left', 'center', 'right'].includes(align) ? align : 'center';
            const normalizedText = ['under', 'beside', 'wrap'].includes(text) ? text : 'under';
            if (normalizedText === 'wrap') {
                return normalizedAlign === 'right' ? 'wrap-right' : 'wrap-left';
            }
            if (normalizedText === 'beside') {
                return normalizedAlign === 'right' ? 'beside-right' : 'beside-left';
            }
            if (normalizedAlign === 'right') {
                return 'row-end';
            }
            return 'row';
        }

        function legacySizeToFraction(size) {
            const normalized = Number(size);
            return PICTURE_LEGACY_SIZE_TO_FRACTION[normalized] || { width_num: 1, width_den: 2 };
        }

        function resolvePictureStyle(block) {
            if (!block || typeof block !== 'object') {
                return { ...PICTURE_STYLE_DEFAULTS };
            }

            if (block.width_num !== undefined || block.width_den !== undefined || block.flow !== undefined) {
                const fraction = normalizePictureFraction(block.width_num ?? 1, block.width_den ?? 1);
                const flow = PICTURE_FLOWS.includes(block.flow) ? block.flow : PICTURE_STYLE_DEFAULTS.flow;
                return { ...fraction, flow };
            }

            if (block.size !== undefined || block.align !== undefined || block.text !== undefined) {
                const fraction = legacySizeToFraction(block.size ?? 50);
                return {
                    ...fraction,
                    flow: legacyStyleToFlow(block.align, block.text),
                };
            }

            const legacy = PICTURE_LAYOUT_TO_STYLE[block.layout] || PICTURE_LAYOUT_TO_STYLE.centered;
            return { ...legacy };
        }

        function pictureStyleAttrs(style) {
            const flow = PICTURE_FLOWS.includes(style.flow) ? style.flow : PICTURE_STYLE_DEFAULTS.flow;
            return {
                className: `page-picture--flow-${flow}`,
                styleAttr: `--pw-num:${style.width_num};--pw-den:${style.width_den}`,
            };
        }

        function renderPictureWidthOptions(selected) {
            const options = Array.isArray(pictureStyleMeta.width_options)
                ? pictureStyleMeta.width_options
                : [1, 2, 3, 4, 5, 6];
            return options.map((entry) => {
                const value = Number(entry?.value ?? entry);
                const label = String(entry?.label ?? value);
                return `<option value="${value}"${value === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`;
            }).join('');
        }

        function renderPictureFlowOptions(selected) {
            const flows = Array.isArray(pictureStyleMeta.flows) ? pictureStyleMeta.flows : [];
            return flows.map((entry) => {
                const value = String(entry.value ?? entry);
                const label = String(entry.label ?? value);
                return `<option value="${escapeHtml(value)}"${value === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`;
            }).join('');
        }

        function renderChipPool(index, field, label, options, selected) {
            const name = `page-block-${field}-${index}`;
            const chips = options.map((entry) => {
                const value = String(entry.value ?? '');
                const text = String(entry.label ?? value);
                const checked = String(selected) === value ? ' checked' : '';
                return `
                    <label class="visual-filter-chip prp-collision-chip">
                        <input type="radio" name="${escapeHtml(name)}" data-field="${escapeHtml(field)}" data-block-index="${index}" value="${escapeHtml(value)}"${checked}>
                        <span>${escapeHtml(text)}</span>
                    </label>`;
            }).join('');
            return `
                <div class="page-picture-style-inline">
                    <span class="page-picture-style-label">${escapeHtml(label)}</span>
                    <div class="visual-filter-chip-group" role="radiogroup" aria-label="${escapeHtml(label)}">${chips}</div>
                </div>
            `;
        }

        function renderOnOffChipPool(index, field, label, isOn) {
            return renderChipPool(
                index,
                field,
                label,
                [
                    { value: 'on', label: 'On' },
                    { value: 'off', label: 'Off' },
                ],
                isOn ? 'on' : 'off'
            );
        }

        function renderPictureStyleBar(block, index, extraHtml = '') {
            const style = resolvePictureStyle(block);

            return `
                <div class="page-picture-style-bar" data-block-index="${index}" title="Mix widths like 1/6 or 2/5 for gallery rows. Flow controls wrap and row placement.">
                    <label class="page-picture-style-inline">
                        <span class="page-picture-style-label">Width</span>
                        <span class="page-picture-width-row">
                            <select class="page-picture-width-select"
                                    data-action="set-picture-width-num"
                                    data-block-index="${index}"
                                    aria-label="Picture width numerator">${renderPictureWidthOptions(style.width_num)}</select>
                            <span class="page-picture-width-sep" aria-hidden="true">/</span>
                            <select class="page-picture-width-select"
                                    data-action="set-picture-width-den"
                                    data-block-index="${index}"
                                    aria-label="Picture width denominator">${renderPictureWidthOptions(style.width_den)}</select>
                        </span>
                    </label>
                    <label class="page-picture-style-inline">
                        <span class="page-picture-style-label">Flow</span>
                        <select class="page-picture-flow-select"
                                data-action="set-picture-flow"
                                data-block-index="${index}"
                                aria-label="Picture flow">${renderPictureFlowOptions(style.flow)}</select>
                    </label>
                    ${extraHtml}
                </div>
            `;
        }

        function renderPictureEditor(block, index) {
            const thumb = block.src
                ? `<img src="${escapeHtml(block.src)}" alt="" class="page-picture-thumb">`
                : '<div class="page-picture-empty">No picture</div>';

            const captionField = `
                <div class="page-block-field">
                    <input type="text" id="page-picture-caption-${index}" data-field="caption" data-block-index="${index}" value="${escapeHtml(block.caption || '')}" maxlength="500" placeholder="Optional short caption" aria-label="Caption">
                </div>
            `;

            const richField = `
                <div class="page-block-field">
                    ${renderRichEditor(index, 'body', block.body || '', true)}
                </div>
            `;

            return `
                <div class="page-picture-editor">
                    <div class="page-picture-top">
                        <div class="page-picture-visual">${thumb}</div>
                        <div class="page-picture-controls">
                            <button type="button" class="btn btn-primary page-picture-change-btn" data-action="pick-image" data-block-index="${index}">${block.src ? 'Change picture' : 'Choose picture'}</button>
                            ${renderPictureStyleBar(block, index)}
                        </div>
                    </div>
                    ${block.type === 'picture_richtext' ? richField : captionField}
                </div>
            `;
        }

        function renderVideoEditor(block, index) {
            const thumb = block.src
                ? `<video src="${escapeHtml(block.src)}" class="page-picture-thumb" preload="metadata" muted playsinline></video>`
                : '<div class="page-picture-empty">No video</div>';
            const audioOn = block.audio_on !== false;
            const loopOn = block.loop_on === true;

            const playbackBar = `${renderOnOffChipPool(index, 'audio_on', 'Audio', audioOn)}${renderOnOffChipPool(index, 'loop_on', 'Loop', loopOn)}`;

            return `
                <div class="page-picture-editor">
                    <div class="page-picture-top">
                        <div class="page-picture-visual">${thumb}</div>
                        <div class="page-picture-controls">
                            <button type="button" class="btn btn-primary page-picture-change-btn" data-action="pick-video" data-block-index="${index}">${block.src ? 'Change video' : 'Choose video'}</button>
                            ${renderPictureStyleBar(block, index, playbackBar)}
                        </div>
                    </div>
                    <div class="page-block-field">
                        <input type="text" id="page-video-caption-${index}" data-field="caption" data-block-index="${index}" value="${escapeHtml(block.caption || '')}" maxlength="500" placeholder="Optional short caption" aria-label="Caption">
                    </div>
                </div>
            `;
        }

        function renderListEditor(block, index) {
            const itemsText = Array.isArray(block.items) ? block.items.join('\n') : '';
            return `
                <div class="page-block-field">
                    <label>List style</label>
                    <select data-field="style" data-block-index="${index}">
                        <option value="unordered"${block.style !== 'ordered' ? ' selected' : ''}>Bullet list</option>
                        <option value="ordered"${block.style === 'ordered' ? ' selected' : ''}>Numbered list</option>
                    </select>
                </div>
                <div class="page-block-field">
                    <label>Items (one per line)</label>
                    <textarea data-field="list-text" data-block-index="${index}" rows="4">${escapeHtml(itemsText)}</textarea>
                </div>
            `;
        }

        function renderBlockFields(block, index) {
            if (block.type === 'richtext') {
                return `<div class="page-block-field">${renderRichEditor(index, 'html', block.html || '', false)}</div>`;
            }
            if (isPictureFamilyBlock(block)) {
                return renderPictureEditor(block, index);
            }
            if (block.type === 'video') {
                return renderVideoEditor(block, index);
            }
            if (block.type === 'list') {
                return renderListEditor(block, index);
            }
            if (block.type === 'gallery') {
                return renderGalleryEditor(block, index);
            }
            return '<p class="hint">Unsupported block type. Remove it and add Text, Picture, Video, Gallery, or List.</p>';
        }

        function renderBlockPreview(block) {
            if (block.type === 'richtext') {
                const html = renderRichContent(block.html);
                return html ? `<div class="page-richtext">${html}</div>` : '';
            }
            if (isPictureFamilyBlock(block)) {
                if (!block.src) return '';
                const style = resolvePictureStyle(block);
                const attrs = pictureStyleAttrs(style);
                const caption = block.type === 'picture' && block.caption
                    ? `<figcaption class="page-caption">${escapeHtml(block.caption)}</figcaption>`
                    : '';
                const body = block.type === 'picture_richtext' ? renderRichContent(block.body) : '';
                return `
                    <section class="page-picture ${attrs.className}" style="${attrs.styleAttr}">
                        <figure class="page-picture-media">
                            <img src="${escapeHtml(block.src)}" alt="${escapeHtml(block.alt || 'Picture')}" loading="lazy" decoding="async">
                            ${caption}
                        </figure>
                        ${body ? `<div class="page-picture-body">${body}</div>` : ''}
                    </section>
                `;
            }
            if (block.type === 'video') {
                if (!block.src) return '';
                const style = resolvePictureStyle(block);
                const attrs = pictureStyleAttrs(style);
                const caption = block.caption
                    ? `<figcaption class="page-caption">${escapeHtml(block.caption)}</figcaption>`
                    : '';
                const poster = block.poster ? ` poster="${escapeHtml(block.poster)}"` : '';
                const muted = block.audio_on === false ? ' muted' : '';
                const loop = block.loop_on === true ? ' loop' : '';
                return `
                    <section class="page-picture page-video ${attrs.className}" style="${attrs.styleAttr}">
                        <figure class="page-picture-media">
                            <video controls preload="metadata" playsinline${poster}${muted}${loop}>
                                <source src="${escapeHtml(block.src)}" type="video/mp4">
                            </video>
                            ${caption}
                        </figure>
                    </section>
                `;
            }
            if (block.type === 'list') {
                const tag = block.style === 'ordered' ? 'ol' : 'ul';
                const items = Array.isArray(block.items) ? block.items : [];
                const rendered = items.map((item) => `<li>${escapeHtml(item)}</li>`).join('');
                return rendered ? `<${tag} class="page-list page-list--${escapeHtml(block.style || 'unordered')}">${rendered}</${tag}>` : '';
            }
            if (block.type === 'gallery') {
                const galleryId = escapeHtml(block.gallery_id || 'bandpromo-demo');
                const preset = escapeHtml(block.preset || 'grid');
                const title = escapeHtml(galleryTitle(block.gallery_id || 'bandpromo-demo'));
                return `<section class="page-gallery page-gallery--${preset}" data-gallery-id="${galleryId}"><p class="page-gallery-preview-hint">Gallery: ${title} (${preset} layout)</p></section>`;
            }
            return '';
        }

        function renderPreviewHtml(documentModel) {
            const blocks = Array.isArray(documentModel?.blocks) ? documentModel.blocks : [];
            const rendered = blocks.map(renderBlockPreview).filter(Boolean).join('');
            return `<div class="page-content page-preview">${rendered || '<p class="page-editor-empty">Preview will appear as you add content.</p>'}</div>`;
        }

        function setStatus() {}

        function getRichEditor(index, field) {
            return blocksEl?.querySelector(`[data-rich-editor="1"][data-block-index="${index}"][data-rich-field="${field}"]`) ?? null;
        }

        let toolbarStateFrame = null;

        function getActiveRichEditor() {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0 || !selection.anchorNode) {
                return document.activeElement?.dataset?.richEditor === '1'
                    ? document.activeElement
                    : null;
            }

            let node = selection.anchorNode;
            if (node.nodeType === Node.TEXT_NODE) {
                node = node.parentElement;
            }

            if (!(node instanceof HTMLElement)) {
                return null;
            }

            const editor = node.closest('[data-rich-editor="1"]');
            return editor instanceof HTMLElement ? editor : null;
        }

        const PAGE_EDITOR_BLOCK_TAGS = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'PRE', 'DIV']);
        const PAGE_EDITOR_STYLE_CLASSES = ['page-text-small', 'page-text-code'];
        const PAGE_EDITOR_ALIGN_CLASSES = ['page-align-left', 'page-align-center', 'page-align-right'];
        const editorSelectionStore = new WeakMap();

        function captureEditorSelection(editor) {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0 || !selection.anchorNode) {
                return;
            }
            if (!editor.contains(selection.anchorNode)) {
                return;
            }
            editorSelectionStore.set(editor, selection.getRangeAt(0).cloneRange());
        }

        function restoreEditorSelection(editor, range) {
            if (!range) {
                return;
            }
            editor.focus({ preventScroll: true });
            const selection = window.getSelection();
            if (!selection) {
                return;
            }
            selection.removeAllRanges();
            selection.addRange(range);
        }

        function peekEditorSelection(editor) {
            const stored = editorSelectionStore.get(editor);
            if (stored) {
                return stored.cloneRange();
            }

            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0 || !selection.anchorNode) {
                return null;
            }
            if (!editor.contains(selection.anchorNode)) {
                return null;
            }

            return selection.getRangeAt(0).cloneRange();
        }

        function consumeEditorSelection(editor) {
            const stored = editorSelectionStore.get(editor);
            if (stored) {
                editorSelectionStore.delete(editor);
                return stored.cloneRange();
            }

            return peekEditorSelection(editor);
        }

        function blockIntersectsRange(blockEl, range) {
            if (!(blockEl instanceof HTMLElement) || !range) {
                return false;
            }

            try {
                const blockRange = document.createRange();
                blockRange.selectNodeContents(blockEl);
                return range.compareBoundaryPoints(Range.START_TO_END, blockRange) > 0
                    && range.compareBoundaryPoints(Range.END_TO_START, blockRange) < 0;
            } catch (error) {
                return false;
            }
        }

        function getBlockElementAtCursor(editor, range = null) {
            const selection = window.getSelection();
            let anchorNode = null;

            if (range) {
                anchorNode = range.commonAncestorContainer;
            } else if (selection && selection.rangeCount > 0 && selection.anchorNode) {
                anchorNode = selection.anchorNode;
            }

            if (!anchorNode) {
                return editor.querySelector('p, h1, h2, h3, h4, pre, div');
            }

            let node = anchorNode;
            if (node.nodeType === Node.TEXT_NODE) {
                node = node.parentElement;
            }

            let blockEl = node instanceof HTMLElement ? node : null;

            while (blockEl && blockEl !== editor) {
                if (PAGE_EDITOR_BLOCK_TAGS.has(blockEl.tagName)) {
                    return blockEl;
                }
                blockEl = blockEl.parentElement;
            }

            return editor.querySelector('p, h1, h2, h3, h4, pre, div');
        }

        function getBlockElementsInSelection(editor, range = null) {
            const workingRange = range || peekEditorSelection(editor);
            if (!workingRange) {
                const fallback = getBlockElementAtCursor(editor);
                return fallback ? [fallback] : [];
            }

            if (workingRange.collapsed) {
                const fallback = getBlockElementAtCursor(editor, workingRange);
                return fallback ? [fallback] : [];
            }

            const blocks = Array.from(editor.querySelectorAll('p, h1, h2, h3, h4, pre'))
                .filter((blockEl) => blockIntersectsRange(blockEl, workingRange));

            if (blocks.length > 0) {
                return blocks;
            }

            const wrapperBlocks = Array.from(editor.querySelectorAll(':scope > div'))
                .filter((blockEl) => blockIntersectsRange(blockEl, workingRange));

            if (wrapperBlocks.length > 0) {
                return wrapperBlocks;
            }

            const fallback = getBlockElementAtCursor(editor, workingRange);
            return fallback ? [fallback] : [];
        }

        function stripInlineFormattingFromHtml(html) {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;

            wrapper.querySelectorAll('span, font').forEach((element) => {
                element.removeAttribute('style');
                element.removeAttribute('class');
            });

            ['b', 'strong', 'i', 'em', 'u'].forEach((tagName) => {
                wrapper.querySelectorAll(tagName).forEach((element) => {
                    const parent = element.parentNode;
                    if (!parent) {
                        return;
                    }
                    while (element.firstChild) {
                        parent.insertBefore(element.firstChild, element);
                    }
                    parent.removeChild(element);
                });
            });

            return wrapper.innerHTML;
        }

        function createBlockElement(normalizedTag, innerHtml, sourceBlock, options = {}) {
            const clearInlineFormatting = options.clearInlineFormatting === true;
            const resetAlignment = options.resetAlignment === true;
            const content = clearInlineFormatting ? stripInlineFormattingFromHtml(innerHtml) : innerHtml;
            let next;

            if (normalizedTag === 'page-text-small') {
                next = document.createElement('p');
                next.className = 'page-text-small';
            } else if (normalizedTag === 'page-text-code') {
                next = document.createElement('pre');
                next.className = 'page-text-code';
            } else if (normalizedTag === 'h1' || normalizedTag === 'h2' || normalizedTag === 'h3' || normalizedTag === 'h4') {
                next = document.createElement(normalizedTag);
            } else {
                next = document.createElement('p');
            }

            next.innerHTML = content;

            if (resetAlignment) {
                applyAlignmentClasses(next, 'left');
            } else if (sourceBlock instanceof HTMLElement) {
                copyBlockAlignment(sourceBlock, next);
            } else {
                applyAlignmentClasses(next, 'left');
            }

            return next;
        }

        function transformBlockElement(blockEl, normalizedTag, options = {}) {
            if (!(blockEl instanceof HTMLElement)) {
                return null;
            }

            const clearInlineFormatting = options.clearInlineFormatting === true;
            const resetAlignment = options.resetAlignment === true;
            const effectiveTag = clearInlineFormatting ? 'p' : normalizedTag;
            const next = createBlockElement(
                effectiveTag,
                blockEl.innerHTML,
                blockEl,
                { clearInlineFormatting, resetAlignment }
            );
            blockEl.replaceWith(next);
            return next;
        }

        function restoreSelectionOnBlocks(editor, blocks, collapseToEnd = false) {
            const targets = (Array.isArray(blocks) ? blocks : []).filter((blockEl) => blockEl instanceof HTMLElement);
            if (!targets.length) {
                return;
            }

            const range = document.createRange();
            if (collapseToEnd || targets.length === 1) {
                range.selectNodeContents(targets[targets.length - 1]);
                range.collapse(false);
            } else {
                range.setStart(targets[0], 0);
                range.setEnd(targets[targets.length - 1], targets[targets.length - 1].childNodes.length);
            }

            restoreEditorSelection(editor, range);
            captureEditorSelection(editor);
        }

        function applyBlockStyle(editor, blockTag, range = null) {
            const normalizedTag = normalizeBlockTag(blockTag);
            const targets = getBlockElementsInSelection(editor, range);
            if (!targets.length) {
                return;
            }

            const togglesOff = ['page-text-small', 'page-text-code', 'h1', 'h2', 'h3', 'h4'].includes(normalizedTag)
                && targets.every((blockEl) => inferBlockTag(blockEl) === normalizedTag);

            const transformed = [];
            if (normalizedTag === 'p' || togglesOff) {
                targets.forEach((blockEl) => {
                    transformed.push(transformBlockElement(blockEl, 'p', {
                        clearInlineFormatting: true,
                        resetAlignment: false,
                    }));
                });
            } else {
                targets.forEach((blockEl) => {
                    transformed.push(transformBlockElement(blockEl, normalizedTag, {
                        clearInlineFormatting: false,
                        resetAlignment: false,
                    }));
                });
            }

            restoreSelectionOnBlocks(editor, transformed, togglesOff || normalizedTag === 'p');
        }

        function applyClearFormatting(editor, range = null) {
            const targets = getBlockElementsInSelection(editor, range);
            const transformed = targets.map((blockEl) => transformBlockElement(blockEl, 'p', {
                clearInlineFormatting: true,
                resetAlignment: true,
            }));
            restoreSelectionOnBlocks(editor, transformed, true);
        }

        function normalizeBlockTag(value) {
            const raw = String(value || '').toLowerCase().replace(/[<>]/g, '').trim();
            if (raw === 'h1' || raw === 'h2' || raw === 'h3' || raw === 'h4' || raw === 'p') {
                return raw;
            }
            if (raw === 'page-text-small' || raw === 'page-text-code') {
                return raw;
            }
            return 'p';
        }

        function inferBlockTag(blockEl) {
            if (!(blockEl instanceof HTMLElement)) {
                return 'p';
            }
            if (blockEl.classList.contains('page-text-small')) {
                return 'page-text-small';
            }
            if (blockEl.classList.contains('page-text-code')) {
                return 'page-text-code';
            }
            const tag = blockEl.tagName.toLowerCase();
            if (tag === 'h1' || tag === 'h2' || tag === 'h3' || tag === 'h4' || tag === 'p') {
                return tag;
            }
            if (tag === 'pre') {
                return 'page-text-code';
            }
            return 'p';
        }

        function applyAlignmentClasses(element, align) {
            if (!(element instanceof HTMLElement)) {
                return;
            }
            PAGE_EDITOR_ALIGN_CLASSES.forEach((className) => element.classList.remove(className));
            element.removeAttribute('align');
            if (element.style.textAlign) {
                element.style.removeProperty('text-align');
            }
            if (align === 'center') {
                element.classList.add('page-align-center');
            } else if (align === 'right') {
                element.classList.add('page-align-right');
            } else {
                element.classList.add('page-align-left');
            }
        }

        function clearWrapperDivAlignment(blockEl, editor) {
            let parent = blockEl?.parentElement ?? null;
            while (parent && parent !== editor) {
                if (parent.tagName === 'DIV') {
                    PAGE_EDITOR_ALIGN_CLASSES.forEach((className) => parent.classList.remove(className));
                    parent.removeAttribute('align');
                    if (parent.style.textAlign) {
                        parent.style.removeProperty('text-align');
                    }
                }
                parent = parent.parentElement;
            }
        }

        function copyBlockAlignment(source, target) {
            if (source.classList.contains('page-align-center')) {
                applyAlignmentClasses(target, 'center');
            } else if (source.classList.contains('page-align-right')) {
                applyAlignmentClasses(target, 'right');
            } else {
                applyAlignmentClasses(target, 'left');
            }
        }

        function getAlignmentFromBlock(blockEl) {
            if (!(blockEl instanceof HTMLElement)) {
                return 'left';
            }
            if (blockEl.classList.contains('page-align-center')) {
                return 'center';
            }
            if (blockEl.classList.contains('page-align-right')) {
                return 'right';
            }
            if (blockEl.classList.contains('page-align-left')) {
                return 'left';
            }
            return 'left';
        }

        function selectionInsideTag(editor, tagName) {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0 || !selection.anchorNode) {
                return false;
            }

            let node = selection.anchorNode;
            if (node.nodeType === Node.TEXT_NODE) {
                node = node.parentElement;
            }

            while (node && node !== editor) {
                if (node instanceof HTMLElement && node.tagName === tagName) {
                    return true;
                }
                node = node.parentElement;
            }

            return false;
        }

        function clearToolbarState(toolbar) {
            toolbar.querySelectorAll('.page-rich-btn').forEach((btn) => {
                btn.classList.remove('is-active');
                btn.setAttribute('aria-pressed', 'false');
            });
        }

        function setToolbarButtonActive(toolbar, format, blockTag = '') {
            const selector = blockTag
                ? `.page-rich-btn[data-format="${format}"][data-block-tag="${blockTag}"]`
                : `.page-rich-btn[data-format="${format}"]`;
            const btn = toolbar.querySelector(selector);
            if (!btn) return;
            btn.classList.add('is-active');
            btn.setAttribute('aria-pressed', 'true');
        }

        function selectionUsesInlineTag(editor, tagNames) {
            const tags = Array.isArray(tagNames) ? tagNames : [tagNames];
            return tags.some((tagName) => selectionInsideTag(editor, String(tagName).toUpperCase()));
        }

        function uniformBlockValue(blocks, readValue) {
            if (!Array.isArray(blocks) || blocks.length === 0) {
                return null;
            }
            const firstValue = readValue(blocks[0]);
            return blocks.every((blockEl) => readValue(blockEl) === firstValue) ? firstValue : null;
        }

        function updateToolbarForEditor(editor) {
            if (!editor || !blocksEl) return;

            const blockIndex = editor.dataset.blockIndex;
            const field = editor.dataset.richField || 'html';
            const toolbar = blocksEl.querySelector(
                `.page-word-toolbar[data-block-index="${blockIndex}"][data-rich-field="${field}"]`
            );
            if (!toolbar) return;

            blocksEl.querySelectorAll('.page-word-toolbar').forEach((otherToolbar) => {
                if (otherToolbar !== toolbar) {
                    clearToolbarState(otherToolbar);
                }
            });

            const selection = window.getSelection();
            const selectionInEditor = Boolean(selection?.anchorNode && editor.contains(selection.anchorNode));
            const storedRange = editorSelectionStore.get(editor);
            const storedSelectionInEditor = Boolean(
                storedRange && editor.contains(storedRange.commonAncestorContainer)
            );
            const editorIsActive = document.activeElement === editor || selectionInEditor || storedSelectionInEditor;
            if (!editorIsActive) {
                clearToolbarState(toolbar);
                return;
            }

            const workingRange = peekEditorSelection(editor);
            const selectedBlocks = getBlockElementsInSelection(editor, workingRange);
            const blockEl = selectedBlocks[0] || getBlockElementAtCursor(editor, workingRange);
            const states = {
                block: uniformBlockValue(selectedBlocks, inferBlockTag),
                align: uniformBlockValue(selectedBlocks, getAlignmentFromBlock),
                bold: false,
                italic: false,
                underline: false,
                link: selectionUsesInlineTag(editor, ['A']),
            };

            if (document.activeElement === editor) {
                try {
                    states.bold = document.queryCommandState('bold') || selectionUsesInlineTag(editor, ['B', 'STRONG']);
                    states.italic = document.queryCommandState('italic') || selectionUsesInlineTag(editor, ['I', 'EM']);
                    states.underline = document.queryCommandState('underline') || selectionUsesInlineTag(editor, ['U']);
                } catch (error) {
                    states.bold = selectionUsesInlineTag(editor, ['B', 'STRONG']);
                    states.italic = selectionUsesInlineTag(editor, ['I', 'EM']);
                    states.underline = selectionUsesInlineTag(editor, ['U']);
                }
            } else {
                states.bold = selectionUsesInlineTag(editor, ['B', 'STRONG']);
                states.italic = selectionUsesInlineTag(editor, ['I', 'EM']);
                states.underline = selectionUsesInlineTag(editor, ['U']);
            }

            if (!states.block && blockEl) {
                states.block = inferBlockTag(blockEl);
            }

            clearToolbarState(toolbar);
            if (states.block) {
                setToolbarButtonActive(toolbar, 'block', states.block);
            }
            if (states.bold) setToolbarButtonActive(toolbar, 'bold');
            if (states.italic) setToolbarButtonActive(toolbar, 'italic');
            if (states.underline) setToolbarButtonActive(toolbar, 'underline');
            if (states.link) setToolbarButtonActive(toolbar, 'link');
            if (states.align) {
                setToolbarButtonActive(toolbar, `align-${states.align}`);
            }
        }

        function updateActiveToolbarFromSelection() {
            const editor = getActiveRichEditor();
            if (!editor) {
                blocksEl?.querySelectorAll('.page-word-toolbar').forEach(clearToolbarState);
                return;
            }
            updateToolbarForEditor(editor);
        }

        function scheduleToolbarStateUpdate() {
            if (toolbarStateFrame !== null) {
                return;
            }
            toolbarStateFrame = window.requestAnimationFrame(() => {
                toolbarStateFrame = null;
                updateActiveToolbarFromSelection();
            });
        }

        function syncRichEditors() {
            if (!blocksEl || !documentState?.blocks) return;
            blocksEl.querySelectorAll('[data-rich-editor="1"]').forEach((editor) => {
                const blockIndex = Number(editor.dataset.blockIndex);
                const field = editor.dataset.richField || 'html';
                const block = documentState.blocks[blockIndex];
                if (!block) return;
                if (field === 'body' && block.type === 'picture_richtext') {
                    block.body = editor.innerHTML;
                } else if (field === 'html' && block.type === 'richtext') {
                    block.html = editor.innerHTML;
                }
            });
        }

        function syncRichField(index, field, html) {
            const block = documentState?.blocks?.[index];
            if (!block) return;
            if (field === 'body' && block.type === 'picture_richtext') {
                block.body = html;
            } else if (field === 'html' && block.type === 'richtext') {
                block.html = html;
            }
            queuePreview();
        }

        function applyTextAlignment(blockIndex, field, align, range = null) {
            const editor = getRichEditor(blockIndex, field);
            if (!editor) return;

            const workingRange = range || consumeEditorSelection(editor);
            restoreEditorSelection(editor, workingRange);

            const targets = getBlockElementsInSelection(editor, workingRange);
            if (!targets.length) {
                return;
            }

            targets.forEach((blockEl) => {
                applyAlignmentClasses(blockEl, align);
                clearWrapperDivAlignment(blockEl, editor);
            });

            restoreSelectionOnBlocks(editor, targets, false);

            syncRichField(blockIndex, field, editor.innerHTML);
            markDirty();
            scheduleToolbarStateUpdate();
        }

        function applyRichFormat(format, blockIndex, field, blockTag) {
            const editor = getRichEditor(blockIndex, field);
            if (!editor) return;

            const workingRange = consumeEditorSelection(editor);
            restoreEditorSelection(editor, workingRange);

            if (format === 'block' && blockTag) {
                applyBlockStyle(editor, blockTag, workingRange);
            } else if (format === 'clear-format') {
                applyClearFormatting(editor, workingRange);
            } else if (format === 'link') {
                const url = window.prompt('Link address (https://… or /page):');
                if (!url) return;
                const trimmed = url.trim();
                if (!trimmed) return;
                document.execCommand('createLink', false, trimmed);
            } else if (format === 'bold') {
                document.execCommand('bold');
            } else if (format === 'italic') {
                document.execCommand('italic');
            } else if (format === 'underline') {
                document.execCommand('underline');
            } else if (format === 'align-left') {
                applyTextAlignment(blockIndex, field, 'left', workingRange);
                return;
            } else if (format === 'align-center') {
                applyTextAlignment(blockIndex, field, 'center', workingRange);
                return;
            } else if (format === 'align-right') {
                applyTextAlignment(blockIndex, field, 'right', workingRange);
                return;
            }

            syncRichField(blockIndex, field, editor.innerHTML);
            markDirty();
            scheduleToolbarStateUpdate();
        }

        function renderBlocks(options = {}) {
            if (!blocksEl || !documentState) return;

            const blocks = Array.isArray(documentState.blocks) ? documentState.blocks : [];
            const wasSuppressingDirty = suppressDirtyTracking;
            if (options.silent) {
                suppressDirtyTracking = true;
            }

            if (blocks.length === 0) {
                blocksEl.innerHTML = '<p class="page-editor-empty">Start with + Text, + Picture, + Video, + Picture + text, or + List.</p>';
                queuePreview();
                if (options.silent) {
                    window.requestAnimationFrame(() => {
                        suppressDirtyTracking = wasSuppressingDirty;
                    });
                }
                return;
            }

            blocksEl.innerHTML = blocks.map((block, index) => `
                <article class="page-block-card page-block-card--compact" data-block-index="${index}">
                    <div class="page-block-card-header">
                        <span class="page-block-type-label">${escapeHtml(blockLabel(block))}</span>
                        <div class="page-block-actions">
                            <button type="button" class="btn" data-action="move-up" data-block-index="${index}"${index === 0 ? ' disabled' : ''}>↑</button>
                            <button type="button" class="btn" data-action="move-down" data-block-index="${index}"${index === blocks.length - 1 ? ' disabled' : ''}>↓</button>
                            <button type="button" class="icon-btn icon-btn--danger page-block-delete-btn" data-action="delete-block" data-block-index="${index}" title="Delete block" aria-label="Delete block">🗑️</button>
                        </div>
                    </div>
                    ${renderBlockFields(block, index)}
                </article>
            `).join('');
            queuePreview();
            if (!options.silent) {
                markDirty();
            } else {
                window.requestAnimationFrame(() => {
                    suppressDirtyTracking = wasSuppressingDirty;
                });
            }
            scheduleToolbarStateUpdate();
        }

        function queuePreview() {
            if (!previewEl || !documentState) return;
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(async () => {
                syncRichEditors();
                const requestId = ++previewRequestId;
                try {
                    const resp = await fetch(`/biblioteca/preview-page-document.php?page=${encodeURIComponent(currentPageKey)}`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json; charset=utf-8' },
                        body: JSON.stringify({ document: documentState }),
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (requestId !== previewRequestId) {
                        return;
                    }
                    if (!resp.ok || !data.ok || typeof data.html !== 'string') {
                        setPreviewHtml(renderPreviewHtml(documentState));
                        return;
                    }
                    setPreviewHtml(data.html);
                } catch (error) {
                    if (requestId !== previewRequestId) {
                        return;
                    }
                    setPreviewHtml(renderPreviewHtml(documentState));
                }
            }, 200);
        }

        function defaultBlock(type) {
            if (type === 'picture' || type === 'image') {
                return { type: 'picture', src: '', alt: 'Picture', width_num: 1, width_den: 2, flow: 'row', caption: '' };
            }
            if (type === 'video') {
                return { type: 'video', src: '', alt: 'Video', width_num: 1, width_den: 2, flow: 'row', caption: '', audio_on: true, loop_on: false };
            }
            if (type === 'picture_richtext') {
                return { type: 'picture_richtext', src: '', alt: 'Picture', width_num: 1, width_den: 2, flow: 'row', body: '<p>Write text with this picture.</p>' };
            }
            if (type === 'list') {
                return { type: 'list', style: 'unordered', items: ['First item'] };
            }
            if (type === 'gallery') {
                return { type: 'gallery', gallery_id: 'bandpromo-demo', preset: 'grid', columns: 4, autorotate: false, autorotate_speed: 'normal' };
            }
            return { type: 'richtext', html: '<p>Write your text here.</p>' };
        }

        function addBlock(type) {
            if (!documentState) return;
            syncRichEditors();
            documentState.blocks = Array.isArray(documentState.blocks) ? documentState.blocks : [];
            documentState.blocks.push(defaultBlock(type));
            renderBlocks();
        }

        function moveBlock(index, direction) {
            if (!documentState || !Array.isArray(documentState.blocks)) return;
            syncRichEditors();
            const target = index + direction;
            if (target < 0 || target >= documentState.blocks.length) return;
            const blocks = documentState.blocks;
            [blocks[index], blocks[target]] = [blocks[target], blocks[index]];
            renderBlocks();
        }

        let pendingDeleteBlockIndex = null;

        function openBlockDeleteModal(index) {
            const block = documentState?.blocks?.[index];
            if (!block) return;

            pendingDeleteBlockIndex = index;
            if (blockDeleteModalName) {
                blockDeleteModalName.textContent = blockLabel(block);
            }
            if (blockDeleteModal) {
                blockDeleteModal.style.display = 'flex';
                blockDeleteModal.setAttribute('aria-hidden', 'false');
            }
            blockDeleteConfirmBtn?.focus();
        }

        function closeBlockDeleteModal() {
            pendingDeleteBlockIndex = null;
            if (blockDeleteModal) {
                blockDeleteModal.style.display = 'none';
                blockDeleteModal.setAttribute('aria-hidden', 'true');
            }
        }

        function performDeleteBlock(index) {
            if (!documentState || !Array.isArray(documentState.blocks)) return;
            if (!documentState.blocks[index]) return;
            syncRichEditors();
            documentState.blocks.splice(index, 1);
            renderBlocks();
        }

        function requestDeleteBlock(index) {
            if (!documentState?.blocks?.[index]) return;
            openBlockDeleteModal(index);
        }

        function updateBlockField(index, field, value) {
            if (!documentState?.blocks?.[index]) return;
            const block = documentState.blocks[index];

            if (field === 'list-text') {
                block.items = String(value).split('\n').map((line) => line.trim()).filter(Boolean);
                if (block.items.length === 0) block.items = [''];
                queuePreview();
                return;
            }

            if ((field === 'audio_on' || field === 'loop_on') && block.type === 'video') {
                block[field] = String(value || '').toLowerCase() === 'on';
                queuePreview();
                return;
            }

            if (field === 'columns' && block.type === 'gallery') {
                const parsed = parseInt(String(value), 10);
                block.columns = Number.isFinite(parsed) && parsed >= 2 ? parsed : 0;
                queuePreview();
                return;
            }

            if (field === 'autorotate' && block.type === 'gallery') {
                block.autorotate = String(value || '').toLowerCase() === 'on';
                queuePreview();
                return;
            }

            if (field === 'autorotate_speed' && block.type === 'gallery') {
                const speed = String(value || '').toLowerCase();
                block.autorotate_speed = ['slow', 'normal', 'fast'].includes(speed) ? speed : 'normal';
                queuePreview();
                return;
            }

            if (field === 'preset' && block.type === 'gallery') {
                block.preset = value;
                const columnsRow = layout?.querySelector(`[data-gallery-columns-for="${index}"]`);
                if (columnsRow) {
                    columnsRow.hidden = String(value) !== 'grid';
                }
                const carouselOpts = layout?.querySelector(`[data-gallery-carousel-opts-for="${index}"]`);
                if (carouselOpts) {
                    carouselOpts.hidden = String(value) !== 'carousel';
                }
                const hint = layout?.querySelector(`[data-gallery-hint-for="${index}"]`);
                if (hint) {
                    hint.textContent = galleryHint(value);
                }
                queuePreview();
                return;
            }

            block[field] = value;
            queuePreview();
        }

        function applyPictureStyleToBlock(block, style) {
            block.width_num = style.width_num;
            block.width_den = style.width_den;
            block.flow = style.flow;
            delete block.size;
            delete block.align;
            delete block.text;
            delete block.layout;
        }

        function updatePictureStyleBar(index) {
            const block = documentState?.blocks?.[index];
            if (!block || !isVisualMediaBlock(block)) return;

            const card = blocksEl?.querySelector(`.page-block-card[data-block-index="${index}"]`);
            const styleBar = card?.querySelector('.page-picture-style-bar');
            if (!styleBar) return;

            const style = resolvePictureStyle(block);
            const numSelect = styleBar.querySelector('[data-action="set-picture-width-num"]');
            const denSelect = styleBar.querySelector('[data-action="set-picture-width-den"]');
            const flowSelect = styleBar.querySelector('[data-action="set-picture-flow"]');

            if (numSelect instanceof HTMLSelectElement) numSelect.value = String(style.width_num);
            if (denSelect instanceof HTMLSelectElement) denSelect.value = String(style.width_den);
            if (flowSelect instanceof HTMLSelectElement) flowSelect.value = style.flow;
        }

        function setPictureWidth(index, part, rawValue) {
            const block = documentState?.blocks?.[index];
            if (!block || !isVisualMediaBlock(block)) return;

            const style = resolvePictureStyle(block);
            const next = normalizePictureFraction(
                part === 'num' ? rawValue : style.width_num,
                part === 'den' ? rawValue : style.width_den
            );
            applyPictureStyleToBlock(block, { ...style, ...next });
            updatePictureStyleBar(index);
            queuePreview();
            markDirty();
        }

        function setPictureFlow(index, rawValue) {
            const block = documentState?.blocks?.[index];
            if (!block || !isVisualMediaBlock(block)) return;

            const style = resolvePictureStyle(block);
            const flow = PICTURE_FLOWS.includes(rawValue) ? rawValue : PICTURE_STYLE_DEFAULTS.flow;
            applyPictureStyleToBlock(block, { ...style, flow });
            updatePictureStyleBar(index);
            queuePreview();
            markDirty();
        }

        function applyVisualSelection(index, selection, mode) {
            const block = documentState?.blocks?.[index];
            if (!block || !isVisualMediaBlock(block)) return;
            const wantsVideo = mode === 'video';
            if (wantsVideo && block.type !== 'video') return;
            if (!wantsVideo && !isPictureFamilyBlock(block)) return;

            const assetId = String(selection?.assetId || '').trim();
            const path = String(selection?.path || '').trim();
            const filename = String(selection?.filename || '').trim();
            const src = path || (assetId
                ? (wantsVideo
                    ? `/media/visual/delivery/${encodeURIComponent(assetId)}/standard-stream.mp4`
                    : `/media/visual/delivery/${encodeURIComponent(assetId)}/card.jpg`)
                : '');
            if (!src && !assetId) {
                return;
            }

            block.src = src;
            block.alt = filename || block.alt || (wantsVideo ? 'Video' : 'Picture');
            if (assetId) {
                block.asset_id = assetId;
                if (wantsVideo) {
                    block.poster = `/media/visual/delivery/${encodeURIComponent(assetId)}/poster.jpg`;
                }
            } else {
                delete block.asset_id;
                if (wantsVideo) {
                    delete block.poster;
                }
            }

            const card = blocksEl?.querySelector(`.page-block-card[data-block-index="${index}"]`);
            if (card) {
                const visual = card.querySelector('.page-picture-visual');
                if (visual) {
                    if (wantsVideo) {
                        visual.innerHTML = block.src
                            ? `<video src="${escapeHtml(block.src)}" class="page-picture-thumb" preload="metadata" muted playsinline></video>`
                            : '<div class="page-picture-empty">No video</div>';
                    } else {
                        visual.innerHTML = block.src
                            ? `<img src="${escapeHtml(block.src)}" alt="" class="page-picture-thumb">`
                            : '<div class="page-picture-empty">No picture</div>';
                    }
                }
                const pickBtn = card.querySelector('[data-action="pick-image"]');
                if (pickBtn) pickBtn.textContent = block.src ? 'Change picture' : 'Choose picture';
                const pickVideoBtn = card.querySelector('[data-action="pick-video"]');
                if (pickVideoBtn) pickVideoBtn.textContent = block.src ? 'Change video' : 'Choose video';
            } else {
                renderBlocks();
            }
            queuePreview();
            markDirty();
        }

        function openImagePicker(index) {
            if (typeof window.openMediaPicker !== 'function' || !(pagePicturePickerField instanceof HTMLInputElement)) {
                window.alert('Media picker is not available. Reload the admin panel and try again.');
                return;
            }
            imagePickerTargetIndex = index;
            const current = documentState?.blocks?.[index];
            pagePicturePickerField.value = String(current?.src || current?.asset_id || '').trim();
            window.openMediaPicker(
                'pagePicturePickerField',
                current?.src ? 'Change picture' : 'Choose picture',
                'illustrations,photos,special',
                {
                    acceptKinds: ['image'],
                    onSelect(selection) {
                        const targetIndex = imagePickerTargetIndex;
                        imagePickerTargetIndex = null;
                        if (targetIndex === null || targetIndex === undefined) {
                            return;
                        }
                        applyVisualSelection(targetIndex, selection || {}, 'image');
                    },
                }
            );
        }

        function openVideoPicker(index) {
            if (typeof window.openMediaPicker !== 'function' || !(pagePicturePickerField instanceof HTMLInputElement)) {
                window.alert('Media picker is not available. Reload the admin panel and try again.');
                return;
            }
            imagePickerTargetIndex = index;
            const current = documentState?.blocks?.[index];
            pagePicturePickerField.value = String(current?.src || current?.asset_id || '').trim();
            window.openMediaPicker(
                'pagePicturePickerField',
                current?.src ? 'Change video' : 'Choose video',
                'video,special',
                {
                    acceptKinds: ['video'],
                    onSelect(selection) {
                        const targetIndex = imagePickerTargetIndex;
                        imagePickerTargetIndex = null;
                        if (targetIndex === null || targetIndex === undefined) {
                            return;
                        }
                        applyVisualSelection(targetIndex, selection || {}, 'video');
                    },
                }
            );
        }

        function autofitPageMetaTextareas() {
            [pageSettingsShortDescription, pageSettingsDescription].forEach((field) => {
                if (!(field instanceof HTMLTextAreaElement)) {
                    return;
                }
                field.style.height = 'auto';
                field.style.height = `${Math.max(field.scrollHeight, field === pageSettingsDescription ? 104 : 72)}px`;
            });
        }

        async function loadDocument() {
            const resp = await fetch(`/biblioteca/get-page-document.php?page=${encodeURIComponent(currentPageKey)}`, {
                credentials: 'same-origin',
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Could not load page');
            }
            suppressDirtyTracking = true;
            allowUnloadWithoutSave = false;
            documentState = data.document;
            syncPageDocumentMetaToForm();
            autofitPageMetaTextareas();
            if (data.picture_styles && typeof data.picture_styles === 'object') {
                pictureStyleMeta = {
                    width_min: Number(data.picture_styles.width_min) || 1,
                    width_max: Number(data.picture_styles.width_max) || 6,
                    width_options: Array.isArray(data.picture_styles.width_options)
                        ? data.picture_styles.width_options.map((entry) => ({
                            value: Number(entry.value ?? entry),
                            label: String(entry.label ?? entry.value ?? entry),
                        }))
                        : pictureStyleMeta.width_options,
                    flows: Array.isArray(data.picture_styles.flows)
                        ? data.picture_styles.flows.map((entry) => ({
                            value: String(entry.value ?? entry),
                            label: String(entry.label ?? entry.value ?? entry),
                        }))
                        : pictureStyleMeta.flows,
                };
            }
            if (Array.isArray(data.galleries) && data.galleries.length > 0) {
                galleryCatalog = data.galleries;
            }
            if (Array.isArray(data.gallery_presets) && data.gallery_presets.length > 0) {
                galleryPresets = data.gallery_presets.map((preset) => String(preset));
            }
            syncMetaFromRegistry(data.registry);
            renderBlocks({ silent: true });
            if (typeof data.html === 'string' && previewEl) {
                setPreviewHtml(data.html);
            }
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    suppressDirtyTracking = false;
                    resetBaseline();
                    scheduleToolbarStateUpdate();
                });
            });
        }

        async function saveDocument() {
            if (!documentState || !saveBtn) return;
            syncRichEditors();
            syncPageDocumentMetaFromForm();
            saveUi?.markSaving();

            const meta = pageMetaSnapshot();
            if (!meta.title) {
                saveUi?.markFailed();
                throw new Error('Page name is required.');
            }

            try {
                const resp = await fetch(`/biblioteca/save-page.php?page=${encodeURIComponent(currentPageKey)}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({
                        document: documentState,
                        meta,
                    }),
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.ok) {
                    throw new Error(data.error || 'Save failed');
                }

                documentState = data.document || documentState;
                syncMetaFromRegistry(data.registry);
                if (typeof data.html === 'string' && previewEl) {
                    setPreviewHtml(data.html);
                }
                updateActiveTabLabel(data.registry || meta);
                resetBaseline();
                saveUi?.markSaved();
            } catch (error) {
                saveUi?.markFailed();
                throw error;
            }
        }

        layout.addEventListener('click', (event) => {
            const rawTarget = event.target;
            if (!(rawTarget instanceof HTMLElement)) return;

            const formatBtn = rawTarget.closest('button[data-format]');
            if (formatBtn instanceof HTMLButtonElement && layout.contains(formatBtn)) {
                event.preventDefault();
                applyRichFormat(
                    formatBtn.dataset.format || '',
                    Number(formatBtn.dataset.blockIndex),
                    formatBtn.dataset.richField || 'html',
                    formatBtn.dataset.blockTag || ''
                );
                return;
            }

            const target = rawTarget.closest('[data-action]');
            if (!(target instanceof HTMLElement) || !layout.contains(target)) return;

            const action = target.dataset.action;
            const blockIndex = Number(target.dataset.blockIndex);
            if (action === 'add-block') {
                addBlock(target.dataset.blockType || 'text');
                return;
            }
            if (action === 'move-up') {
                moveBlock(blockIndex, -1);
                return;
            }
            if (action === 'move-down') {
                moveBlock(blockIndex, 1);
                return;
            }
            if (action === 'delete-block') {
                requestDeleteBlock(blockIndex);
                return;
            }
            if (action === 'pick-image') {
                openImagePicker(blockIndex);
                return;
            }
            if (action === 'pick-video') {
                openVideoPicker(blockIndex);
                return;
            }
        });

        layout.addEventListener('input', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;

            if (target.dataset.richEditor === '1') {
                syncRichField(
                    Number(target.dataset.blockIndex),
                    target.dataset.richField || 'html',
                    target.innerHTML
                );
                if (event.isTrusted) {
                    markDirty();
                }
                scheduleToolbarStateUpdate();
                return;
            }

            const field = target.dataset.field;
            const blockIndex = Number(target.dataset.blockIndex);
            if (!field || Number.isNaN(blockIndex)) return;
            updateBlockField(blockIndex, field, target.value);
            if (event.isTrusted) {
                markDirty();
            }
        });

        layout.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;

            if (target.dataset.format === 'block') {
                applyRichFormat(
                    'block',
                    Number(target.dataset.blockIndex),
                    target.dataset.richField || 'html',
                    target.value
                );
                return;
            }

            const pictureAction = target.dataset.action || '';
            if (pictureAction === 'set-picture-width-num') {
                setPictureWidth(Number(target.dataset.blockIndex), 'num', target.value);
                return;
            }
            if (pictureAction === 'set-picture-width-den') {
                setPictureWidth(Number(target.dataset.blockIndex), 'den', target.value);
                return;
            }
            if (pictureAction === 'set-picture-flow') {
                setPictureFlow(Number(target.dataset.blockIndex), target.value);
                return;
            }

            if (target instanceof HTMLSelectElement
                || (target instanceof HTMLInputElement && target.type === 'radio')) {
                const field = target.dataset.field;
                const blockIndex = Number(target.dataset.blockIndex);
                if (!field || Number.isNaN(blockIndex)) return;
                updateBlockField(blockIndex, field, target.value);
                if (event.isTrusted) {
                    markDirty();
                }
            }
        });

        saveBtn?.addEventListener('click', () => {
            saveDocument().catch((error) => setStatus('❌ ' + error.message, 'error'));
        });

        closeUnsavedModal();

        pageTitleInput?.addEventListener('input', (event) => {
            if (event.isTrusted) {
                markDirty();
            }
        });
        pageLabelInput?.addEventListener('input', (event) => {
            if (event.isTrusted) {
                markDirty();
            }
        });
        pageSettingsShortDescription?.addEventListener('input', (event) => {
            if (pageSettingsShortDescriptionCount && pageSettingsShortDescription instanceof HTMLTextAreaElement) {
                pageSettingsShortDescriptionCount.textContent = String(pageSettingsShortDescription.value.length);
            }
            autofitPageMetaTextareas();
            if (event.isTrusted) {
                markDirty();
            }
        });
        pageSettingsDescription?.addEventListener('input', (event) => {
            autofitPageMetaTextareas();
            if (event.isTrusted) {
                markDirty();
            }
        });
        pageSettingsPosterAssetId?.addEventListener('input', () => {
            syncPageDocumentMetaToForm();
            markDirty();
        });
        document.addEventListener('click', guardAdminNavigation, true);

        layout.addEventListener('paste', (event) => {
            const rawTarget = event.target;
            if (!(rawTarget instanceof HTMLElement)) {
                return;
            }
            const editor = rawTarget.closest('[data-rich-editor="1"]');
            if (!(editor instanceof HTMLElement) || !layout.contains(editor)) {
                return;
            }

            const clipboard = event.clipboardData;
            if (!clipboard) {
                return;
            }

            event.preventDefault();
            const plainText = clipboard.getData('text/plain').replace(/\r\n/g, '\n');
            if (plainText === '') {
                return;
            }

            const paragraphs = plainText.split(/\n{2,}/).map((paragraph) => {
                const lines = paragraph.split('\n').map((line) => escapeHtml(line));
                return `<p>${lines.join('<br />')}</p>`;
            });

            document.execCommand('insertHTML', false, paragraphs.join('') || '<p><br></p>');
            syncRichField(
                Number(editor.dataset.blockIndex),
                editor.dataset.richField || 'html',
                editor.innerHTML
            );
            markDirty();
            scheduleToolbarStateUpdate();
        });

        layout.addEventListener('mousedown', (event) => {
            const rawTarget = event.target;
            if (!(rawTarget instanceof HTMLElement)) {
                return;
            }
            const formatBtn = rawTarget.closest('.page-word-toolbar button[data-format]');
            if (!(formatBtn instanceof HTMLButtonElement) || !layout.contains(formatBtn)) {
                return;
            }
            event.preventDefault();
            const toolbar = formatBtn.closest('.page-word-toolbar');
            const editor = toolbar
                ? getRichEditor(Number(toolbar.dataset.blockIndex), toolbar.dataset.richField || 'html')
                : getActiveRichEditor();
            if (editor) {
                captureEditorSelection(editor);
            }
        });

        document.addEventListener('selectionchange', () => {
            const editor = getActiveRichEditor();
            if (!editor || !layout.contains(editor)) {
                return;
            }
            captureEditorSelection(editor);
            scheduleToolbarStateUpdate();
        });
        layout.addEventListener('mouseup', (event) => {
            if (event.target instanceof HTMLElement && event.target.closest('[data-rich-editor="1"]')) {
                scheduleToolbarStateUpdate();
            }
        });
        layout.addEventListener('keyup', (event) => {
            if (event.target instanceof HTMLElement && event.target.dataset.richEditor === '1') {
                scheduleToolbarStateUpdate();
            }
        });
        layout.addEventListener('focusin', (event) => {
            if (event.target instanceof HTMLElement && event.target.dataset.richEditor === '1') {
                scheduleToolbarStateUpdate();
            }
        });
        layout.addEventListener('focusout', (event) => {
            if (event.target instanceof HTMLElement && event.target.dataset.richEditor === '1') {
                window.setTimeout(() => {
                    scheduleToolbarStateUpdate();
                    reconcileDirtyState();
                }, 0);
            }
        });

        blockDeleteCancelBtn?.addEventListener('click', closeBlockDeleteModal);
        blockDeleteModal?.addEventListener('click', (event) => {
            if (event.target === blockDeleteModal) {
                closeBlockDeleteModal();
            }
        });
        blockDeleteConfirmBtn?.addEventListener('click', () => {
            const index = pendingDeleteBlockIndex;
            if (index === null || Number.isNaN(index)) return;
            performDeleteBlock(index);
            closeBlockDeleteModal();
        });

        async function resolvePendingNavigation() {
            const href = pendingNavHref;
            pendingNavHref = '';
            if (!href) {
                return;
            }
            if (href === BACK_TO_POOL) {
                showPoolView();
                try {
                    await loadPreviewOnly(selectedPageId);
                } catch (error) {
                    if (previewEl) {
                        previewEl.innerHTML = `<p class="page-editor-empty">${escapeHtml(error.message)}</p>`;
                    }
                }
                return;
            }
            if (pages.some((entry) => entry && entry.id === href)) {
                await openPageEditor(href);
                return;
            }
            window.location.href = href;
        }

        unsavedSaveBtn?.addEventListener('click', async () => {
            const href = pendingNavHref;
            if (!href) {
                closeUnsavedModal();
                return;
            }
            try {
                await saveDocument();
                closeUnsavedModal();
                pendingNavHref = href;
                await resolvePendingNavigation();
            } catch (error) {
                setStatus('❌ ' + error.message, 'error');
            }
        });

        unsavedDiscardBtn?.addEventListener('click', async () => {
            const href = pendingNavHref;
            abandonUnsavedChanges();
            closeUnsavedModal();
            pendingNavHref = href;
            await resolvePendingNavigation();
        });

        unsavedCancelBtn?.addEventListener('click', closeUnsavedModal);
        unsavedModal?.addEventListener('click', (event) => {
            if (event.target === unsavedModal) {
                closeUnsavedModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            if (blockDeleteModal && blockDeleteModal.style.display === 'flex') {
                closeBlockDeleteModal();
                return;
            }
            if (unsavedModal && unsavedModal.style.display === 'flex') {
                closeUnsavedModal();
            }
        });

        window.addEventListener('beforeunload', (event) => {
            if (!hasUnsavedChanges()) return;
            event.preventDefault();
            event.returnValue = '';
        });

        const shouldOpenEditor = new URLSearchParams(window.location.search).get('edit') === '1';
        const bootstrap = shouldOpenEditor
            ? openPageEditor(selectedPageId)
            : loadPreviewOnly(selectedPageId);
        if (!shouldOpenEditor) {
            showPoolView();
        }
        bootstrap.catch((error) => {
            setStatus('❌ ' + error.message, 'error');
            if (previewEl) {
                previewEl.innerHTML = `<p class="page-editor-empty">${escapeHtml(error.message)}</p>`;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBandpromoPageEditor);
    } else {
        initBandpromoPageEditor();
    }
})();
