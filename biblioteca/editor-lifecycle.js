(function () {
    'use strict';

    /**
     * Create an editor lifecycle controller.
     *
     * @param {Object} options
     * @param {HTMLElement} options.root          - The editor card root element
     * @param {HTMLElement} options.poolView      - The pool/registry view container
     * @param {HTMLElement} options.editorView    - The editor/edit-mode view container
     * @param {HTMLElement} [options.saveBtn]     - The save button (hidden in pool view)
     * @param {string}      options.cntab        - The cntab value ('campaign', 'playlist', 'gallery', 'pages', 'branding')
     * @param {string}      options.entityParam  - URL param name for the entity ID ('campaign', 'playlist', 'gallery', 'page', 'brand')
     * @param {boolean}     [options.trackEditParam=true] - Whether to include ?edit=1 in URL
     * @param {Function}    [options.onShowPool]  - Hook called after transitioning to pool view
     * @param {Function}    [options.onShowEdit]  - Hook called after transitioning to edit view, receives (entityId)
     * @param {Function}    [options.onBeforeClose] - Async hook called before closing editor. Return false to abort close.
     * @param {Function}    [options.onAfterClose]  - Async hook called after close completes
     * @returns {Object} Controller with methods: showPoolView, showEditView, syncUrl, requestClose, isEditing
     */
    function createEditorLifecycle(options) {
        const {
            root,
            poolView,
            editorView,
            saveBtn,
            cntab,
            entityParam,
            trackEditParam = true,
            onShowPool,
            onShowEdit,
            onBeforeClose,
            onAfterClose,
        } = options;

        let editing = false;
        let currentEntityId = '';

        function syncUrl(entityId, isEdit) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'content');
            url.searchParams.set('cntab', cntab);
            if (entityId) {
                url.searchParams.set(entityParam, entityId);
            }
            if (trackEditParam) {
                if (isEdit) {
                    url.searchParams.set('edit', '1');
                } else {
                    url.searchParams.delete('edit');
                }
            }
            window.history.replaceState({}, '', url.toString());
        }

        function showPoolView() {
            editing = false;
            if (root) root.classList.remove('is-editing');
            if (poolView) poolView.hidden = false;
            if (editorView) editorView.hidden = true;
            if (saveBtn) saveBtn.hidden = true;
            if (typeof onShowPool === 'function') onShowPool();
        }

        function showEditView(entityId) {
            editing = true;
            currentEntityId = entityId;
            if (root) root.classList.add('is-editing');
            if (poolView) poolView.hidden = true;
            if (editorView) editorView.hidden = false;
            if (saveBtn) saveBtn.hidden = false;
            syncUrl(entityId, true);
            if (typeof onShowEdit === 'function') onShowEdit(entityId);
        }

        async function requestClose() {
            if (typeof onBeforeClose === 'function') {
                const allowed = await onBeforeClose();
                if (allowed === false) return false;
            }
            showPoolView();
            syncUrl(currentEntityId, false);
            if (typeof onAfterClose === 'function') {
                await onAfterClose();
            }
            return true;
        }

        return {
            showPoolView: showPoolView,
            showEditView: showEditView,
            syncUrl: syncUrl,
            requestClose: requestClose,
            isEditing: function () { return editing; },
            currentEntityId: function () { return currentEntityId; },
            setCurrentEntityId: function (id) { currentEntityId = id; },
        };
    }

    window.bandpromoEditorLifecycle = { create: createEditorLifecycle };
})();
