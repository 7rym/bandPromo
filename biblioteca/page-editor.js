(function () {
    function initBandpromoPageEditor() {
        const shell = document.getElementById('pageEditorShell');
        if (!shell) {
            return;
        }

        const pageKey = shell.dataset.pageKey || 'bio';
        const pageLabel = shell.dataset.pageLabel || 'Page';
        const pageTitleInput = document.getElementById('pageTitleInput');
        const pageLabelInput = document.getElementById('pageLabelInput');
        const pageEditorTabs = document.getElementById('pageEditorTabs');
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
        const saveBtn = document.getElementById('pageSaveBtn');
        const statusEl = document.getElementById('pageStatus');
        const imagePickerModal = document.getElementById('pageImagePickerModal');
        const imagePickerGrid = document.getElementById('pageImagePickerGrid');
        const imagePickerApplyBtn = document.getElementById('pageImagePickerApplyBtn');
        const imagePickerCancelBtn = document.getElementById('pageImagePickerCancelBtn');

        const PICTURE_STYLE_DEFAULTS = {
            width_num: 1,
            width_den: 2,
            flow: 'row',
        };

        const PICTURE_FLOWS = ['row', 'row-end', 'wrap-left', 'wrap-right', 'beside-left', 'beside-right'];

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
                { value: 'wrap-left', label: 'Wrap left' },
                { value: 'wrap-right', label: 'Wrap right' },
                { value: 'beside-left', label: 'Beside left' },
                { value: 'beside-right', label: 'Beside right' },
            ],
        };
        let flatImages = [];
        let imagePickerTargetIndex = null;
        let imagePickerSelected = null;
        let previewTimer = null;
        let isDirtyState = false;
        let pendingNavHref = '';
        let allowUnloadWithoutSave = false;
        let suppressDirtyTracking = false;
        let baselineFingerprint = '';

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
            const meta = pageMetaSnapshot();
            const blocks = Array.isArray(documentState?.blocks) ? documentState.blocks : [];
            return JSON.stringify({ meta, blocks });
        }

        function hasUnsavedChanges() {
            if (allowUnloadWithoutSave || suppressDirtyTracking || !documentState) {
                return false;
            }
            syncRichEditors();
            return buildDirtyFingerprint() !== baselineFingerprint;
        }

        function updateSaveButton() {
            if (!saveBtn) return;
            saveBtn.disabled = !isDirtyState;
            saveBtn.textContent = isDirtyState ? '💾 Save changes' : '💾 Saved';
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
            updateSaveButton();
        }

        function markDirty() {
            if (suppressDirtyTracking || isDirtyState) return;
            allowUnloadWithoutSave = false;
            isDirtyState = true;
            updateSaveButton();
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
            const tabLink = pageEditorTabs?.querySelector(`.page-tab-link[data-page-id="${pageKey}"]`);
            if (!tabLink || !meta) return;
            const emoji = tabLink.textContent.trim().split(/\s+/)[0] || '📝';
            const nextLabel = meta.label || meta.title || pageLabel;
            tabLink.textContent = `${emoji} ${nextLabel}`;
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
                window.location.href = href;
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
            if (link.closest('#pageUnsavedModal, #pageImagePickerModal')) return false;
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
            if (block?.type === 'picture') return 'Picture + text';
            if (block?.type === 'list') return 'List';
            return 'Text';
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
                ${renderWordToolbar(index, field)}
                <div class="page-rich-editor${minClass}" contenteditable="true" data-rich-editor="1" data-block-index="${index}" data-rich-field="${field}" spellcheck="true">${richHtmlForEditor(value)}</div>
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

        function renderPictureStyleBar(block, index) {
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
                </div>
            `;
        }

        function renderPictureEditor(block, index) {
            const thumb = block.src
                ? `<img src="${escapeHtml(block.src)}" alt="" class="page-picture-thumb">`
                : '<div class="page-picture-empty">No picture</div>';

            return `
                <div class="page-picture-editor">
                    <div class="page-picture-top">
                        <div class="page-picture-visual">${thumb}</div>
                        <div class="page-picture-controls">
                            <button type="button" class="btn btn-primary page-picture-change-btn" data-action="pick-image" data-block-index="${index}">${block.src ? 'Change picture' : 'Choose picture'}</button>
                            ${renderPictureStyleBar(block, index)}
                        </div>
                    </div>
                    <div class="page-block-field">
                        <label>Text with this picture</label>
                        ${renderRichEditor(index, 'body', block.body || '', true)}
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
            if (block.type === 'picture') {
                return renderPictureEditor(block, index);
            }
            if (block.type === 'list') {
                return renderListEditor(block, index);
            }
            return '<p class="hint">Unsupported block type. Remove it and add Text, Picture, or List.</p>';
        }

        function renderBlockPreview(block) {
            if (block.type === 'richtext') {
                const html = renderRichContent(block.html);
                return html ? `<div class="page-richtext">${html}</div>` : '';
            }
            if (block.type === 'picture') {
                if (!block.src) return '';
                const style = resolvePictureStyle(block);
                const attrs = pictureStyleAttrs(style);
                const body = renderRichContent(block.body);
                const caption = block.caption ? `<figcaption class="page-caption">${escapeHtml(block.caption)}</figcaption>` : '';
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
            if (block.type === 'list') {
                const tag = block.style === 'ordered' ? 'ol' : 'ul';
                const items = Array.isArray(block.items) ? block.items : [];
                const rendered = items.map((item) => `<li>${escapeHtml(item)}</li>`).join('');
                return rendered ? `<${tag} class="page-list page-list--${escapeHtml(block.style || 'unordered')}">${rendered}</${tag}>` : '';
            }
            return '';
        }

        function renderPreviewHtml(documentModel) {
            const blocks = Array.isArray(documentModel?.blocks) ? documentModel.blocks : [];
            const rendered = blocks.map(renderBlockPreview).filter(Boolean).join('');
            return `<div class="page-content page-preview">${rendered || '<p class="page-editor-empty">Preview will appear as you add content.</p>'}</div>`;
        }

        function setStatus(message, tone) {
            if (!statusEl) return;
            statusEl.textContent = message;
            if (tone === 'error') {
                statusEl.style.color = '#f55';
            } else if (tone === 'warn') {
                statusEl.style.color = '#fbbf24';
            } else {
                statusEl.style.color = 'var(--success, #4ade80)';
            }
        }

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

        function getBlockElementAtCursor(editor) {
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0 || !selection.anchorNode) {
                return editor.querySelector('p, h1, h2, h3, h4, pre');
            }

            let node = selection.anchorNode;
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

            return editor.querySelector('p, h1, h2, h3, h4, pre');
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

        function applyBlockStyle(editor, blockTag) {
            const blockEl = getBlockElementAtCursor(editor);
            if (!blockEl) return;

            const content = blockEl.innerHTML;
            const normalizedTag = normalizeBlockTag(blockTag);

            if (normalizedTag === 'page-text-small') {
                const next = document.createElement('p');
                next.className = 'page-text-small';
                next.innerHTML = content;
                copyBlockAlignment(blockEl, next);
                blockEl.replaceWith(next);
                return;
            }

            if (normalizedTag === 'page-text-code') {
                const next = document.createElement('pre');
                next.className = 'page-text-code';
                next.innerHTML = content;
                copyBlockAlignment(blockEl, next);
                blockEl.replaceWith(next);
                return;
            }

            if (normalizedTag === 'h1' || normalizedTag === 'h2' || normalizedTag === 'h3' || normalizedTag === 'h4' || normalizedTag === 'p') {
                document.execCommand('formatBlock', false, normalizedTag);
                const updated = getBlockElementAtCursor(editor);
                if (updated) {
                    PAGE_EDITOR_STYLE_CLASSES.forEach((className) => updated.classList.remove(className));
                }
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
            const editorIsActive = document.activeElement === editor || selectionInEditor;
            if (!editorIsActive) {
                clearToolbarState(toolbar);
                return;
            }

            const blockEl = getBlockElementAtCursor(editor);
            const states = {
                block: inferBlockTag(blockEl),
                align: getAlignmentFromBlock(blockEl),
                bold: false,
                italic: false,
                underline: false,
                link: selectionInsideTag(editor, 'A'),
            };

            try {
                states.bold = document.queryCommandState('bold');
                states.italic = document.queryCommandState('italic');
                states.underline = document.queryCommandState('underline');
                states.block = normalizeBlockTag(document.queryCommandValue('formatBlock')) || states.block;
            } catch (error) {
                states.block = inferBlockTag(blockEl);
            }

            if (blockEl) {
                states.block = inferBlockTag(blockEl);
            }

            clearToolbarState(toolbar);
            setToolbarButtonActive(toolbar, 'block', states.block);
            if (states.bold) setToolbarButtonActive(toolbar, 'bold');
            if (states.italic) setToolbarButtonActive(toolbar, 'italic');
            if (states.underline) setToolbarButtonActive(toolbar, 'underline');
            if (states.link) setToolbarButtonActive(toolbar, 'link');
            setToolbarButtonActive(toolbar, `align-${states.align}`);
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
                if (field === 'body' && block.type === 'picture') {
                    block.body = editor.innerHTML;
                } else if (field === 'html' && block.type === 'richtext') {
                    block.html = editor.innerHTML;
                }
            });
        }

        function syncRichField(index, field, html) {
            const block = documentState?.blocks?.[index];
            if (!block) return;
            if (field === 'body' && block.type === 'picture') {
                block.body = html;
            } else if (field === 'html' && block.type === 'richtext') {
                block.html = html;
            }
            queuePreview();
        }

        function applyTextAlignment(blockIndex, field, align) {
            const editor = getRichEditor(blockIndex, field);
            if (!editor) return;
            editor.focus();

            const selection = window.getSelection();
            let node = selection?.anchorNode ?? null;
            if (node?.nodeType === Node.TEXT_NODE) {
                node = node.parentElement;
            }

            let blockEl = node instanceof HTMLElement ? node : null;

            while (blockEl && blockEl !== editor) {
                if (PAGE_EDITOR_BLOCK_TAGS.has(blockEl.tagName)) {
                    applyAlignmentClasses(blockEl, align);
                    clearWrapperDivAlignment(blockEl, editor);
                    syncRichField(blockIndex, field, editor.innerHTML);
                    markDirty();
                    scheduleToolbarStateUpdate();
                    return;
                }
                blockEl = blockEl.parentElement;
            }

            const fallback = editor.querySelector('p, h1, h2, h3, h4, pre');
            if (fallback) {
                applyAlignmentClasses(fallback, align);
                clearWrapperDivAlignment(fallback, editor);
                syncRichField(blockIndex, field, editor.innerHTML);
                markDirty();
                scheduleToolbarStateUpdate();
            }
        }

        function applyRichFormat(format, blockIndex, field, blockTag) {
            const editor = getRichEditor(blockIndex, field);
            if (!editor) return;
            editor.focus();

            if (format === 'block' && blockTag) {
                applyBlockStyle(editor, blockTag);
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
                applyTextAlignment(blockIndex, field, 'left');
                return;
            } else if (format === 'align-center') {
                applyTextAlignment(blockIndex, field, 'center');
                return;
            } else if (format === 'align-right') {
                applyTextAlignment(blockIndex, field, 'right');
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
                blocksEl.innerHTML = '<p class="page-editor-empty">Start with + Text, + Picture, or + List.</p>';
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
                            <button type="button" class="icon-btn danger page-block-delete-btn" data-action="delete-block" data-block-index="${index}" title="Delete block" aria-label="Delete block">🗑️</button>
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
            previewTimer = window.setTimeout(() => {
                previewEl.innerHTML = renderPreviewHtml(documentState);
            }, 80);
        }

        function defaultBlock(type) {
            if (type === 'picture' || type === 'image') {
                return { type: 'picture', src: '', alt: 'Picture', width_num: 1, width_den: 2, flow: 'row', body: '<p><br></p>' };
            }
            if (type === 'list') {
                return { type: 'list', style: 'unordered', items: ['First item'] };
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
            if (!block || block.type !== 'picture') return;

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
            if (!block || block.type !== 'picture') return;

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
            if (!block || block.type !== 'picture') return;

            const style = resolvePictureStyle(block);
            const flow = PICTURE_FLOWS.includes(rawValue) ? rawValue : PICTURE_STYLE_DEFAULTS.flow;
            applyPictureStyleToBlock(block, { ...style, flow });
            updatePictureStyleBar(index);
            queuePreview();
            markDirty();
        }

        function openImagePicker(index) {
            imagePickerTargetIndex = index;
            imagePickerSelected = documentState?.blocks?.[index]?.src || (flatImages[0]?.value ?? null);
            renderImagePickerGrid();
            if (imagePickerModal) imagePickerModal.classList.add('active');
        }

        function closeImagePicker() {
            imagePickerTargetIndex = null;
            imagePickerSelected = null;
            if (imagePickerModal) imagePickerModal.classList.remove('active');
        }

        function renderImagePickerGrid() {
            if (!imagePickerGrid) return;
            if (!flatImages.length) {
                imagePickerGrid.innerHTML = '<p class="hint">Upload pictures in Files and run a build first.</p>';
                return;
            }

            imagePickerGrid.innerHTML = flatImages.map((item) => `
                <button type="button" class="page-image-picker-item${item.value === imagePickerSelected ? ' is-selected' : ''}" data-image-value="${escapeHtml(item.value)}">
                    <img src="${escapeHtml(item.thumb_url || item.value)}" alt="">
                    <span>${escapeHtml(item.title || item.value)}</span>
                </button>
            `).join('');
        }

        function applySelectedImage() {
            if (imagePickerTargetIndex === null || !imagePickerSelected) return;
            const block = documentState?.blocks?.[imagePickerTargetIndex];
            if (!block || block.type !== 'picture') return;
            const selected = flatImages.find((item) => item.value === imagePickerSelected);
            block.src = imagePickerSelected;
            block.alt = selected?.title || 'Picture';

            const card = blocksEl?.querySelector(`.page-block-card[data-block-index="${imagePickerTargetIndex}"]`);
            if (card) {
                const visual = card.querySelector('.page-picture-visual');
                if (visual) {
                    visual.innerHTML = `<img src="${escapeHtml(block.src)}" alt="" class="page-picture-thumb">`;
                }
                const pickBtn = card.querySelector('[data-action="pick-image"]');
                if (pickBtn) pickBtn.textContent = 'Change picture';
            } else {
                renderBlocks();
            }
            closeImagePicker();
            queuePreview();
        }

        async function loadImages() {
            const resp = await fetch('/biblioteca/list-page-images.php', { credentials: 'same-origin' });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok) {
                throw new Error(data.error || 'Could not load pictures');
            }
            flatImages = Array.isArray(data.flat_images) ? data.flat_images : [];
        }

        async function loadDocument() {
            const resp = await fetch(`/biblioteca/get-page-document.php?page=${encodeURIComponent(pageKey)}`, {
                credentials: 'same-origin',
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Could not load page');
            }
            suppressDirtyTracking = true;
            allowUnloadWithoutSave = false;
            documentState = data.document;
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
            syncMetaFromRegistry(data.registry);
            renderBlocks({ silent: true });
            if (typeof data.html === 'string' && previewEl) {
                previewEl.innerHTML = data.html;
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
            saveBtn.disabled = true;
            setStatus('Saving…', 'neutral');

            const meta = pageMetaSnapshot();
            if (!meta.title) {
                setStatus('Page name is required.', 'error');
                saveBtn.disabled = false;
                updateSaveButton();
                throw new Error('Page name is required.');
            }

            try {
                const resp = await fetch(`/biblioteca/save-page.php?page=${encodeURIComponent(pageKey)}`, {
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
                    previewEl.innerHTML = data.html;
                }
                updateActiveTabLabel(data.registry || meta);
                resetBaseline();
                setStatus('Changes saved', 'ok');
            } catch (error) {
                setStatus('❌ ' + error.message, 'error');
                throw error;
            } finally {
                updateSaveButton();
            }
        }

        shell.addEventListener('click', (event) => {
            const rawTarget = event.target;
            if (!(rawTarget instanceof HTMLElement)) return;

            const formatBtn = rawTarget.closest('button[data-format]');
            if (formatBtn instanceof HTMLButtonElement && shell.contains(formatBtn)) {
                applyRichFormat(
                    formatBtn.dataset.format || '',
                    Number(formatBtn.dataset.blockIndex),
                    formatBtn.dataset.richField || 'html',
                    formatBtn.dataset.blockTag || ''
                );
                return;
            }

            const target = rawTarget.closest('[data-action]');
            if (!(target instanceof HTMLElement) || !shell.contains(target)) return;

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
        });

        shell.addEventListener('input', (event) => {
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

        shell.addEventListener('change', (event) => {
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

            if (target instanceof HTMLSelectElement) {
                const field = target.dataset.field;
                const blockIndex = Number(target.dataset.blockIndex);
                if (!field || Number.isNaN(blockIndex)) return;
                updateBlockField(blockIndex, field, target.value);
                if (event.isTrusted) {
                    markDirty();
                }
            }
        });

        if (imagePickerGrid) {
            imagePickerGrid.addEventListener('click', (event) => {
                const button = event.target instanceof HTMLElement ? event.target.closest('[data-image-value]') : null;
                if (!button) return;
                imagePickerSelected = button.getAttribute('data-image-value');
                renderImagePickerGrid();
            });
        }

        imagePickerApplyBtn?.addEventListener('click', applySelectedImage);
        imagePickerCancelBtn?.addEventListener('click', closeImagePicker);
        imagePickerModal?.addEventListener('click', (event) => {
            if (event.target === imagePickerModal) closeImagePicker();
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
        document.addEventListener('click', guardAdminNavigation, true);

        document.addEventListener('selectionchange', () => {
            const editor = getActiveRichEditor();
            if (!editor || !shell.contains(editor)) {
                return;
            }
            scheduleToolbarStateUpdate();
        });
        shell.addEventListener('mouseup', (event) => {
            if (event.target instanceof HTMLElement && event.target.closest('[data-rich-editor="1"]')) {
                scheduleToolbarStateUpdate();
            }
        });
        shell.addEventListener('keyup', (event) => {
            if (event.target instanceof HTMLElement && event.target.dataset.richEditor === '1') {
                scheduleToolbarStateUpdate();
            }
        });
        shell.addEventListener('focusin', (event) => {
            if (event.target instanceof HTMLElement && event.target.dataset.richEditor === '1') {
                scheduleToolbarStateUpdate();
            }
        });
        shell.addEventListener('focusout', (event) => {
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

        unsavedSaveBtn?.addEventListener('click', async () => {
            const href = pendingNavHref;
            if (!href) {
                closeUnsavedModal();
                return;
            }
            try {
                await saveDocument();
                closeUnsavedModal();
                window.location.href = href;
            } catch (error) {
                setStatus('❌ ' + error.message, 'error');
            }
        });

        unsavedDiscardBtn?.addEventListener('click', () => {
            const href = pendingNavHref;
            abandonUnsavedChanges();
            closeUnsavedModal();
            if (href) {
                window.location.assign(href);
            }
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

        Promise.all([loadImages(), loadDocument()]).catch((error) => {
            setStatus('❌ ' + error.message, 'error');
            if (blocksEl) {
                blocksEl.innerHTML = `<p class="page-editor-empty">${escapeHtml(error.message)}</p>`;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBandpromoPageEditor);
    } else {
        initBandpromoPageEditor();
    }
})();
