(function () {
    function setStatus(el, message, tone) {
        if (!el) return;
        el.textContent = message;
        el.style.color = tone === 'error' ? '#f55' : 'var(--success, #4ade80)';
    }

    async function postJson(url, payload) {
        const resp = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json; charset=utf-8' },
            body: JSON.stringify(payload),
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || !data.ok) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    }

    async function deletePage(pageId) {
        const resp = await fetch(`/biblioteca/manage-page.php?page=${encodeURIComponent(pageId)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || !data.ok) {
            throw new Error(data.error || 'Could not delete page');
        }
        return data;
    }

    const toggleAddPageBtn = document.getElementById('toggleAddPageBtn');
    const addPagePanel = document.getElementById('addPagePanel');
    const cancelAddPageBtn = document.getElementById('cancelAddPageBtn');
    const addPageForm = document.getElementById('addPageForm');
    const pageRegistryStatus = document.getElementById('pageRegistryStatus');

    function setAddPagePanelOpen(open) {
        if (!addPagePanel || !toggleAddPageBtn) return;
        addPagePanel.hidden = !open;
        toggleAddPageBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggleAddPageBtn.classList.toggle('active', open);
        if (open) {
            const titleInput = addPageForm?.querySelector('input[name="title"]');
            if (titleInput instanceof HTMLInputElement) {
                titleInput.focus();
            }
        } else if (pageRegistryStatus) {
            pageRegistryStatus.textContent = '';
        }
    }

    toggleAddPageBtn?.addEventListener('click', () => {
        setAddPagePanelOpen(addPagePanel?.hidden !== false);
    });

    cancelAddPageBtn?.addEventListener('click', () => {
        addPageForm?.reset();
        setAddPagePanelOpen(false);
    });

    if (addPageForm) {
        addPageForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(addPageForm);
            const title = String(formData.get('title') || '').trim();

            if (!title) {
                setStatus(pageRegistryStatus, 'Page name is required.', 'error');
                return;
            }

            try {
                setStatus(pageRegistryStatus, 'Creating page…', 'neutral');
                const data = await postJson('/biblioteca/manage-page.php', { title });
                const pageId = data.page?.id || data.pages?.[data.pages.length - 1]?.id;
                if (!pageId) {
                    throw new Error('Page was created but no id was returned.');
                }
                window.location.href = `?tab=content&cntab=pages&page=${encodeURIComponent(pageId)}&edit=1`;
            } catch (error) {
                setStatus(pageRegistryStatus, '❌ ' + error.message, 'error');
            }
        });
    }

    const pageDeleteModal = document.getElementById('pageDeleteModal');
    const pageDeleteModalName = document.getElementById('pageDeleteModalName');
    const pageDeleteConfirmBtn = document.getElementById('pageDeleteConfirmBtn');
    const pageDeleteCancelBtn = document.getElementById('pageDeleteCancelBtn');
    let pendingDeletePageId = '';

    function openPageDeleteModal(pageId, pageTitle) {
        pendingDeletePageId = pageId;
        if (pageDeleteModalName) {
            pageDeleteModalName.textContent = pageTitle;
        }
        if (pageDeleteModal) {
            pageDeleteModal.style.display = 'flex';
            pageDeleteModal.setAttribute('aria-hidden', 'false');
        }
        pageDeleteConfirmBtn?.focus();
    }

    window.bandpromoOpenPageDeleteModal = openPageDeleteModal;

    function closePageDeleteModal() {
        pendingDeletePageId = '';
        if (pageDeleteModal) {
            pageDeleteModal.style.display = 'none';
            pageDeleteModal.setAttribute('aria-hidden', 'true');
        }
    }

    pageDeleteCancelBtn?.addEventListener('click', closePageDeleteModal);
    pageDeleteModal?.addEventListener('click', (event) => {
        if (event.target === pageDeleteModal) {
            closePageDeleteModal();
        }
    });
    pageDeleteConfirmBtn?.addEventListener('click', async () => {
        const pageId = pendingDeletePageId;
        if (!pageId) return;

        try {
            if (pageDeleteConfirmBtn) {
                pageDeleteConfirmBtn.disabled = true;
            }
            await deletePage(pageId);
            window.location.href = '?tab=content&cntab=pages&page=faq';
        } catch (error) {
            if (pageDeleteConfirmBtn) {
                pageDeleteConfirmBtn.disabled = false;
            }
            alert(error.message);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !pageDeleteModal || pageDeleteModal.style.display !== 'flex') {
            return;
        }
        closePageDeleteModal();
    });


    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    const playerLayoutCard = document.getElementById('playerLayoutCard');
    if (playerLayoutCard) {
        const activeList = document.getElementById('playerLayoutActiveList');
        const availableList = document.getElementById('playerLayoutAvailableList');
        const activeCountEl = document.getElementById('playerLayoutActiveCount');
        const savePlayerLayoutBtn = document.getElementById('savePlayerLayoutBtn');

        const saveUi = window.bandpromoContentSaveUi?.create(savePlayerLayoutBtn, {
            saveLabel: '💾 Save player layout',
            readFingerprint() {
                const tabOrder = activeItems.map((item) => item.key);
                const hasPages = activeItems.some((item) => item.kind === 'page');
                const pages = [];
                let pageSort = 10;

                activeItems.forEach((item) => {
                    if (item.kind !== 'page') return;
                    pages.push({
                        id: item.id,
                        show_in_player: true,
                        sort_order: pageSort,
                    });
                    pageSort += 10;
                });

                availableItems.forEach((item) => {
                    if (item.kind !== 'page') return;
                    pages.push({
                        id: item.id,
                        show_in_player: false,
                    });
                });

                return JSON.stringify({
                    tab_order: tabOrder,
                    modules: {
                        pages: { enabled: hasPages },
                    },
                    pages,
                });
            },
        }) || null;

        let lockedItems = [];
        let activeItems = [];
        let availableItems = [];
        let dragSrc = null;
        let draggedRows = [];
        let dragSourceList = '';
        let dragPlaceholder = null;
        let selectedAvailable = new Set();
        let selectedActive = new Set();
        let selectionAnchorAvailable = '';
        let selectionAnchorActive = '';
        let suppressNextClick = false;

        function readInitialLayout() {
            try {
                return JSON.parse(playerLayoutCard.dataset.layout || '{}');
            } catch (error) {
                return {};
            }
        }

        function cloneItem(item) {
            return {
                key: item.key,
                kind: item.kind,
                id: item.id,
                title: item.title,
                label: item.label,
                emoji: item.emoji,
            };
        }

        function loadLayoutState(layout) {
            lockedItems = Array.isArray(layout.locked) ? layout.locked.map(cloneItem) : [];
            activeItems = Array.isArray(layout.active)
                ? layout.active.map(cloneItem).filter((item) => item.id !== 'gallery')
                : [];
            availableItems = Array.isArray(layout.available)
                ? layout.available.map(cloneItem).filter((item) => item.id !== 'gallery')
                : [];
        }

        function itemTitle(item) {
            return `${item.emoji || ''} ${item.title || item.id}`.trim();
        }

        function itemMeta(item) {
            if (item.id === 'playlist') {
                return 'Always first tab';
            }
            if (item.id === 'lyrics') {
                return 'Always second tab';
            }
            if (item.kind === 'page') {
                return 'Static page tab';
            }
            return 'Player tab';
        }

        function itemTabMeta(item) {
            if (item.kind === 'page') {
                const label = String(item.label || '').trim();
                return label ? `Tab: ${label}` : 'Static page tab';
            }
            return itemMeta(item);
        }

        function updateCounts() {
            if (activeCountEl) {
                const total = lockedItems.length + activeItems.length;
                activeCountEl.textContent = total ? `(${total})` : '';
            }
        }

        function renderLockedRow(item, position) {
            return `<li class="playlist-editor-row editor-row player-layout-row-locked" data-tab-key="${escapeHtml(item.key)}">
                <span class="player-layout-lock" title="Always on" aria-hidden="true">🔒</span>
                <span class="playlist-track-num">${position}</span>
                <span class="playlist-track-info">
                    <strong>${escapeHtml(itemTitle(item))}</strong>
                    <span class="playlist-track-meta">${escapeHtml(itemMeta(item))}</span>
                </span>
            </li>`;
        }

        function pruneAvailableSelection() {
            const allowed = new Set(availableItems.map((item) => item.key));
            selectedAvailable.forEach((key) => {
                if (!allowed.has(key)) {
                    selectedAvailable.delete(key);
                }
            });
            if (selectionAnchorAvailable && !allowed.has(selectionAnchorAvailable)) {
                selectionAnchorAvailable = '';
            }
        }

        function pruneActiveSelection() {
            const allowed = new Set(activeItems.map((item) => item.key));
            selectedActive.forEach((key) => {
                if (!allowed.has(key)) {
                    selectedActive.delete(key);
                }
            });
            if (selectionAnchorActive && !allowed.has(selectionAnchorActive)) {
                selectionAnchorActive = '';
            }
        }

        function getAvailableRows() {
            if (!availableList) return [];
            return Array.from(availableList.querySelectorAll('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]'));
        }

        function getActiveRows() {
            if (!activeList) return [];
            return Array.from(activeList.querySelectorAll('.player-layout-row-active[draggable="true"]'));
        }

        function syncAvailableSelectionUi() {
            getAvailableRows().forEach((row) => {
                const key = row.dataset.tabKey || '';
                const selected = selectedAvailable.has(key);
                row.classList.toggle('playlist-editor-row-selected', selected);
                row.classList.toggle('editor-row--selected', selected);
                row.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }

        function syncActiveSelectionUi() {
            getActiveRows().forEach((row) => {
                const key = row.dataset.tabKey || '';
                const selected = selectedActive.has(key);
                row.classList.toggle('playlist-editor-row-selected', selected);
                row.classList.toggle('editor-row--selected', selected);
                row.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }

        function selectAvailableRange(targetKey, preserveExisting) {
            const rows = getAvailableRows();
            if (!rows.length) return;
            const anchorKey = selectionAnchorAvailable && rows.some((row) => row.dataset.tabKey === selectionAnchorAvailable)
                ? selectionAnchorAvailable
                : targetKey;
            const anchorIndex = rows.findIndex((row) => row.dataset.tabKey === anchorKey);
            const targetIndex = rows.findIndex((row) => row.dataset.tabKey === targetKey);
            if (anchorIndex === -1 || targetIndex === -1) return;

            const nextSelected = preserveExisting ? new Set(selectedAvailable) : new Set();
            const start = Math.min(anchorIndex, targetIndex);
            const end = Math.max(anchorIndex, targetIndex);
            rows.slice(start, end + 1).forEach((row) => {
                const key = row.dataset.tabKey || '';
                if (key) nextSelected.add(key);
            });
            selectedAvailable = nextSelected;
        }

        function selectActiveRange(targetKey, preserveExisting) {
            const rows = getActiveRows();
            if (!rows.length) return;
            const anchorKey = selectionAnchorActive && rows.some((row) => row.dataset.tabKey === selectionAnchorActive)
                ? selectionAnchorActive
                : targetKey;
            const anchorIndex = rows.findIndex((row) => row.dataset.tabKey === anchorKey);
            const targetIndex = rows.findIndex((row) => row.dataset.tabKey === targetKey);
            if (anchorIndex === -1 || targetIndex === -1) return;

            const nextSelected = preserveExisting ? new Set(selectedActive) : new Set();
            const start = Math.min(anchorIndex, targetIndex);
            const end = Math.max(anchorIndex, targetIndex);
            rows.slice(start, end + 1).forEach((row) => {
                const key = row.dataset.tabKey || '';
                if (key) nextSelected.add(key);
            });
            selectedActive = nextSelected;
        }

        function handleAvailableSelection(row, event) {
            const key = row.dataset.tabKey || '';
            if (!key) return;
            selectedActive.clear();
            selectionAnchorActive = '';
            syncActiveSelectionUi();

            if (event.shiftKey) {
                selectAvailableRange(key, event.ctrlKey || event.metaKey);
            } else if (event.ctrlKey || event.metaKey) {
                if (selectedAvailable.has(key)) {
                    selectedAvailable.delete(key);
                } else {
                    selectedAvailable.add(key);
                }
            } else {
                selectedAvailable = new Set([key]);
            }

            selectionAnchorAvailable = selectedAvailable.size ? key : '';
            syncAvailableSelectionUi();
        }

        function handleActiveSelection(row, event) {
            const key = row.dataset.tabKey || '';
            if (!key) return;
            selectedAvailable.clear();
            selectionAnchorAvailable = '';
            syncAvailableSelectionUi();

            if (event.shiftKey) {
                selectActiveRange(key, event.ctrlKey || event.metaKey);
            } else if (event.ctrlKey || event.metaKey) {
                if (selectedActive.has(key)) {
                    selectedActive.delete(key);
                } else {
                    selectedActive.add(key);
                }
            } else {
                selectedActive = new Set([key]);
            }

            selectionAnchorActive = selectedActive.size ? key : '';
            syncActiveSelectionUi();
        }

        function renderActiveRow(item, position) {
            const selectedClass = selectedActive.has(item.key) ? ' playlist-editor-row-selected editor-row--selected' : '';
            return `<li class="playlist-editor-row editor-row player-layout-row-active${selectedClass}" draggable="true" data-tab-key="${escapeHtml(item.key)}" data-kind="${escapeHtml(item.kind)}" data-id="${escapeHtml(item.id)}" aria-selected="${selectedActive.has(item.key) ? 'true' : 'false'}">
                <span class="playlist-drag-handle editor-drag-handle" title="Drag to reorder">⠿</span>
                <span class="playlist-track-num">${position}</span>
                <span class="playlist-track-info">
                    <strong>${escapeHtml(itemTitle(item))}</strong>
                    <span class="playlist-track-meta">${escapeHtml(itemTabMeta(item))}</span>
                </span>
                <button type="button" class="player-layout-remove-btn" title="Move to Available content" aria-label="Remove from player">✕</button>
            </li>`;
        }

        function renderAvailableRow(item) {
            const selectedClass = selectedAvailable.has(item.key) ? ' playlist-editor-row-selected editor-row--selected' : '';
            return `<li class="playlist-editor-row editor-row${selectedClass}" draggable="true" data-tab-key="${escapeHtml(item.key)}" data-kind="${escapeHtml(item.kind)}" data-id="${escapeHtml(item.id)}" aria-selected="${selectedAvailable.has(item.key) ? 'true' : 'false'}">
                <span class="playlist-drag-handle editor-drag-handle" title="Drag into player tabs">⠿</span>
                <span class="playlist-track-info">
                    <strong>${escapeHtml(itemTitle(item))}</strong>
                    <span class="playlist-track-meta">${escapeHtml(itemMeta(item))}</span>
                </span>
            </li>`;
        }

        function renderLists() {
            pruneAvailableSelection();
            pruneActiveSelection();
            if (activeList) {
                const lockedHtml = lockedItems.map((item, index) => renderLockedRow(item, index + 1)).join('');
                const activeHtml = activeItems.map((item, index) => renderActiveRow(item, lockedItems.length + index + 1)).join('');
                activeList.innerHTML = lockedHtml + activeHtml;
            }
            if (availableList) {
                availableList.innerHTML = availableItems.length
                    ? availableItems.map(renderAvailableRow).join('')
                    : '<li class="player-layout-empty">All available content is already in the player layout. Use ✕ on the right to move items back here.</li>';
            }
            updateCounts();
            saveUi?.reconcile();
        }

        function itemLookup() {
            const lookup = new Map();
            [...lockedItems, ...activeItems, ...availableItems].forEach((item) => {
                lookup.set(item.key, item);
            });
            return lookup;
        }

        function syncActiveOrderFromDOM() {
            const keys = getActiveRows().map((row) => row.dataset.tabKey || '').filter(Boolean);
            const lookup = itemLookup();
            activeItems = keys.map((key) => lookup.get(key)).filter(Boolean);
        }

        function syncAvailableOrderFromDOM() {
            const keys = getAvailableRows().map((row) => row.dataset.tabKey || '').filter(Boolean);
            const lookup = itemLookup();
            availableItems = keys.map((key) => lookup.get(key)).filter(Boolean);
        }

        function moveItemsBetweenLists(fromList, toList, keys, targetIndex) {
            const keySet = new Set(keys.filter(Boolean));
            if (!keySet.size) {
                return false;
            }

            const source = fromList === 'active' ? activeItems : availableItems;
            const moving = source.filter((item) => keySet.has(item.key));
            if (!moving.length) {
                return false;
            }

            if (fromList === 'active') {
                activeItems = activeItems.filter((item) => !keySet.has(item.key));
            } else {
                availableItems = availableItems.filter((item) => !keySet.has(item.key));
            }

            const target = toList === 'active' ? activeItems : availableItems;
            const safeIndex = Math.max(0, Math.min(targetIndex, target.length));
            target.splice(safeIndex, 0, ...moving);

            if (fromList === 'active') {
                keys.forEach((key) => selectedActive.delete(key));
                if (selectionAnchorActive && keySet.has(selectionAnchorActive)) {
                    selectionAnchorActive = '';
                }
            } else {
                keys.forEach((key) => selectedAvailable.delete(key));
                if (selectionAnchorAvailable && keySet.has(selectionAnchorAvailable)) {
                    selectionAnchorAvailable = '';
                }
            }

            renderLists();
            return true;
        }

        function ensurePlaceholder() {
            if (!dragPlaceholder) {
                dragPlaceholder = document.createElement('li');
                dragPlaceholder.className = 'playlist-editor-placeholder editor-placeholder';
            }
            return dragPlaceholder;
        }

        function getDraggableRows(listEl) {
            if (!listEl) return [];
            return Array.from(listEl.querySelectorAll('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]'));
        }

        function listNameForElement(listEl) {
            if (listEl === activeList) return 'active';
            if (listEl === availableList) return 'available';
            return '';
        }

        function draggedKeySet() {
            return new Set(draggedRows.map((row) => row.dataset.tabKey || '').filter(Boolean));
        }

        function availableInsertIndexFromPlaceholder() {
            if (!dragPlaceholder?.parentNode) {
                return availableItems.length;
            }
            const children = Array.from(dragPlaceholder.parentNode.children);
            const placeholderIndex = children.indexOf(dragPlaceholder);
            const movingKeys = draggedKeySet();
            let index = 0;
            for (let i = 0; i < placeholderIndex; i += 1) {
                const child = children[i];
                if (!(child.classList.contains('playlist-editor-row') || child.classList.contains('editor-row'))) continue;
                const key = child.dataset.tabKey || '';
                if (movingKeys.has(key)) continue;
                index += 1;
            }
            return index;
        }

        function activeInsertIndexFromPlaceholder() {
            if (!dragPlaceholder?.parentNode) {
                return activeItems.length;
            }
            const children = Array.from(dragPlaceholder.parentNode.children);
            const placeholderIndex = children.indexOf(dragPlaceholder);
            const movingKeys = draggedKeySet();
            let optionalIndex = 0;
            for (let i = 0; i < placeholderIndex; i += 1) {
                const child = children[i];
                if (child.classList.contains('player-layout-row-locked')) {
                    continue;
                }
                if (!child.classList.contains('player-layout-row-active')) {
                    continue;
                }
                const key = child.dataset.tabKey || '';
                if (movingKeys.has(key)) {
                    continue;
                }
                optionalIndex += 1;
            }
            return optionalIndex;
        }

        function updatePlaceholderHeight() {
            if (!draggedRows.length) return;
            const placeholder = ensurePlaceholder();
            const totalHeight = draggedRows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0) + Math.max(0, draggedRows.length - 1) * 6;
            placeholder.style.height = `${Math.max(52, Math.round(totalHeight))}px`;
        }

        function movePlaceholder(listEl, clientY) {
            if (!draggedRows.length || !listEl) return;
            const placeholder = ensurePlaceholder();
            updatePlaceholderHeight();
            const rows = getDraggableRows(listEl).filter((row) => !draggedRows.includes(row));
            const referenceRow = rows.find((row) => {
                const rect = row.getBoundingClientRect();
                return clientY < rect.top + rect.height / 2;
            });
            if (referenceRow) {
                listEl.insertBefore(placeholder, referenceRow);
            } else if (listEl === activeList) {
                const firstDraggable = getDraggableRows(listEl)[0];
                if (firstDraggable) {
                    listEl.insertBefore(placeholder, firstDraggable);
                } else {
                    listEl.appendChild(placeholder);
                }
            } else {
                listEl.appendChild(placeholder);
            }
        }

        function finalizeWithinListDrag(listEl) {
            const placeholder = ensurePlaceholder();
            if (placeholder.parentNode === listEl && draggedRows.length) {
                draggedRows.forEach((row) => {
                    listEl.insertBefore(row, placeholder);
                });
                placeholder.remove();
            }
        }

        function finalizeDrag() {
            if (!draggedRows.length || !dragPlaceholder?.parentNode) {
                draggedRows.forEach((row) => row.classList.remove('dragging'));
                dragSrc = null;
                draggedRows = [];
                dragSourceList = '';
                dragPlaceholder?.remove();
                return;
            }

            const targetListName = listNameForElement(dragPlaceholder.parentNode);
            const sourceListName = dragSourceList;
            const keys = draggedRows.map((row) => row.dataset.tabKey || '').filter(Boolean);
            const insertIndex = targetListName === 'active'
                ? activeInsertIndexFromPlaceholder()
                : availableInsertIndexFromPlaceholder();

            if (!keys.length || !targetListName) {
                draggedRows.forEach((row) => row.classList.remove('dragging'));
                dragPlaceholder?.remove();
                dragSrc = null;
                draggedRows = [];
                dragSourceList = '';
                renderLists();
                return;
            }

            if (sourceListName === targetListName) {
                if (targetListName === 'active') {
                    finalizeWithinListDrag(activeList);
                    draggedRows.forEach((row) => row.classList.remove('dragging'));
                    syncActiveOrderFromDOM();
                    renderLists();
                } else {
                    finalizeWithinListDrag(availableList);
                    draggedRows.forEach((row) => row.classList.remove('dragging'));
                    syncAvailableOrderFromDOM();
                    renderLists();
                }
            } else {
                draggedRows.forEach((row) => row.classList.remove('dragging'));
                dragPlaceholder.remove();
                moveItemsBetweenLists(sourceListName, targetListName, keys, insertIndex);
            }

            dragSrc = null;
            draggedRows = [];
            dragSourceList = '';
        }

        function collectDraggedRows(listEl) {
            const listName = listNameForElement(listEl);
            if (listName === 'available') {
                return getAvailableRows().filter((row) => selectedAvailable.has(row.dataset.tabKey || ''));
            }
            if (listName === 'active') {
                return getActiveRows().filter((row) => selectedActive.has(row.dataset.tabKey || ''));
            }
            return [];
        }

        function bindDragList(listEl) {
            if (!listEl) return;

            listEl.addEventListener('dragstart', (event) => {
                const row = event.target.closest('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]');
                if (!row || !listEl.contains(row)) return;
                dragSrc = row;
                dragSourceList = listNameForElement(listEl);
                const sourceKey = row.dataset.tabKey || '';

                if (dragSourceList === 'available') {
                    if (sourceKey && !selectedAvailable.has(sourceKey)) {
                        selectedAvailable = new Set([sourceKey]);
                        selectionAnchorAvailable = sourceKey;
                        syncAvailableSelectionUi();
                    }
                    draggedRows = collectDraggedRows(listEl);
                } else if (dragSourceList === 'active') {
                    if (sourceKey && !selectedActive.has(sourceKey)) {
                        selectedActive = new Set([sourceKey]);
                        selectionAnchorActive = sourceKey;
                        syncActiveSelectionUi();
                    }
                    draggedRows = collectDraggedRows(listEl);
                }

                if (!draggedRows.length) {
                    draggedRows = [row];
                }

                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', sourceKey);
                window.requestAnimationFrame(() => {
                    if (!dragSrc || !draggedRows.length) return;
                    updatePlaceholderHeight();
                    listEl.insertBefore(ensurePlaceholder(), draggedRows[0]);
                    draggedRows.forEach((dragRow) => dragRow.classList.add('dragging'));
                });
            });

            listEl.addEventListener('dragover', (event) => {
                if (!draggedRows.length) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                movePlaceholder(listEl, event.clientY);
            });

            listEl.addEventListener('drop', (event) => {
                if (!draggedRows.length) return;
                event.preventDefault();
                movePlaceholder(listEl, event.clientY);
                finalizeDrag();
            });

            listEl.addEventListener('dragend', () => {
                finalizeWithinListDrag(listEl);
                draggedRows.forEach((row) => row.classList.remove('dragging'));
                dragPlaceholder?.remove();
                dragSrc = null;
                draggedRows = [];
                dragSourceList = '';
                syncActiveOrderFromDOM();
                syncAvailableOrderFromDOM();
                syncAvailableSelectionUi();
                syncActiveSelectionUi();
                suppressNextClick = true;
                window.requestAnimationFrame(() => {
                    suppressNextClick = false;
                });
            });
        }

        availableList?.addEventListener('click', (event) => {
            if (suppressNextClick) return;
            const row = event.target.closest('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]');
            if (!row || !availableList.contains(row)) return;
            handleAvailableSelection(row, event);
        });

        activeList?.addEventListener('click', (event) => {
            const button = event.target.closest('.player-layout-remove-btn');
            if (button && activeList.contains(button)) {
                const row = button.closest('.playlist-editor-row, .editor-row');
                if (!row) return;
                const key = row.dataset.tabKey || '';
                if (!key) return;
                moveItemsBetweenLists('active', 'available', [key], availableItems.length);
                return;
            }
            if (suppressNextClick) return;
            const row = event.target.closest('.player-layout-row-active[draggable="true"]');
            if (!row || !activeList.contains(row)) return;
            handleActiveSelection(row, event);
        });

        bindDragList(activeList);
        bindDragList(availableList);

        if (savePlayerLayoutBtn) {
            savePlayerLayoutBtn.addEventListener('click', async () => {
                const tabOrder = activeItems.map((item) => item.key);
                const hasPages = activeItems.some((item) => item.kind === 'page');
                const pages = [];
                let pageSort = 10;

                activeItems.forEach((item) => {
                    if (item.kind !== 'page') return;
                    pages.push({
                        id: item.id,
                        show_in_player: true,
                        sort_order: pageSort,
                    });
                    pageSort += 10;
                });

                availableItems.forEach((item) => {
                    if (item.kind !== 'page') return;
                    pages.push({
                        id: item.id,
                        show_in_player: false,
                    });
                });

                try {
                    saveUi?.markSaving();
                    const data = await postJson('/biblioteca/save-player-layout.php', {
                        tab_order: tabOrder,
                        modules: {
                            pages: { enabled: hasPages },
                        },
                        pages,
                    });
                    if (data.layout) {
                        loadLayoutState(data.layout);
                        renderLists();
                    }
                    saveUi?.markSaved();
                } catch (error) {
                    saveUi?.markFailed();
                }
            });
        }

        loadLayoutState(readInitialLayout());
        renderLists();
        saveUi?.setBaseline();
    }
})();
