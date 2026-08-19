(function () {
    'use strict';

    /**
     * Bind drag-and-drop reorder behaviour to a list element.
     *
     * @param {HTMLElement} listEl - The <ol>/<ul> that contains draggable rows
     * @param {Object} options
     * @param {string}   options.rowSelector       - CSS selector for draggable rows (e.g. '.editor-row[draggable="true"]')
     * @param {string}   options.dataKey            - Dataset key to identify rows (e.g. 'src', 'file')
     * @param {Function} options.listName           - Function(listEl) returning 'available' | 'active'
     * @param {Function} options.getSelectedSet     - Function(listName) returning the Set of selected keys for that list
     * @param {Function} options.setSelectedSet     - Function(listName, newSet) to replace the selected set
     * @param {Function} options.getSelectionAnchor - Function(listName) returning current anchor key
     * @param {Function} options.setSelectionAnchor - Function(listName, key) to set anchor
     * @param {Function} options.syncSelectionUi    - Function(listName) to refresh selection CSS
     * @param {Function} options.collectDraggedRows - Function(listEl, anchorRow) returning array of rows to drag
     * @param {Function} options.movePlaceholder    - Function(listEl, clientY) to position the placeholder
     * @param {Function} options.ensurePlaceholder  - Function() returning the placeholder element
     * @param {Function} options.updatePlaceholderHeight - Function() to set placeholder height
     * @param {Function} options.finalizeDrag       - Function() to handle cross-list drops
     * @param {Function} options.finalizeWithinList  - Function(listEl) to handle same-list reorder
     * @param {Function} options.onDragEnd          - Function() called after drag cleanup for DOM sync
     * @param {Function} [options.canDrag]          - Optional guard, return false to prevent drag
     */
    function bindDragReorder(listEl, options) {
        if (!listEl) return;

        const {
            rowSelector,
            dataKey,
            listName: getListName,
            getSelectedSet,
            setSelectedSet,
            getSelectionAnchor,
            setSelectionAnchor,
            syncSelectionUi,
            collectDraggedRows,
            movePlaceholder,
            ensurePlaceholder,
            updatePlaceholderHeight,
            finalizeDrag,
            finalizeWithinList,
            onDragEnd,
            canDrag,
        } = options;

        let dragSrc = null;
        let draggedRows = [];
        let dragSourceList = '';
        let suppressNextClick = false;

        listEl.addEventListener('dragstart', function (event) {
            if (typeof canDrag === 'function' && !canDrag()) {
                event.preventDefault();
                return;
            }
            const row = event.target instanceof HTMLElement
                ? event.target.closest(rowSelector)
                : null;
            if (!row || !listEl.contains(row)) return;

            dragSrc = row;
            dragSourceList = getListName(listEl);
            const key = String(row.dataset[dataKey] || '').trim();

            const selected = getSelectedSet(dragSourceList);
            if (key && !selected.has(key)) {
                setSelectedSet(dragSourceList, new Set([key]));
                setSelectionAnchor(dragSourceList, key);
                syncSelectionUi(dragSourceList);
            }
            draggedRows = collectDraggedRows(listEl, row);
            if (!draggedRows.length) draggedRows = [row];

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', key);
            window.requestAnimationFrame(function () {
                if (!dragSrc || !draggedRows.length) return;
                updatePlaceholderHeight();
                listEl.insertBefore(ensurePlaceholder(), draggedRows[0]);
                draggedRows.forEach(function (r) { r.classList.add('dragging'); });
            });
        });

        listEl.addEventListener('dragover', function (event) {
            if (!draggedRows.length) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            movePlaceholder(listEl, event.clientY);
        });

        listEl.addEventListener('drop', function (event) {
            if (!draggedRows.length) return;
            event.preventDefault();
            movePlaceholder(listEl, event.clientY);
            finalizeDrag();
        });

        listEl.addEventListener('dragend', function () {
            finalizeWithinList(listEl);
            draggedRows.forEach(function (r) { r.classList.remove('dragging'); });
            var ph = ensurePlaceholder();
            if (ph && ph.parentNode) ph.parentNode.removeChild(ph);
            dragSrc = null;
            draggedRows = [];
            dragSourceList = '';
            if (typeof onDragEnd === 'function') onDragEnd();
            syncSelectionUi('available');
            syncSelectionUi('active');
            suppressNextClick = true;
            window.requestAnimationFrame(function () {
                suppressNextClick = false;
            });
        });

        return {
            isSuppressingClick: function () { return suppressNextClick; },
        };
    }

    window.bandpromoDragReorder = { bind: bindDragReorder };
})();
