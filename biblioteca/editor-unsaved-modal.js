(function () {
    'use strict';

    let modalEl = null;
    let messageEl = null;
    let saveBtn = null;
    let discardBtn = null;
    let cancelBtn = null;
    let pendingResolve = null;
    let bound = false;

    function bindModal() {
        if (bound) {
            return;
        }
        modalEl = document.getElementById('contentUnsavedModal');
        messageEl = document.getElementById('contentUnsavedModalMessage');
        saveBtn = document.getElementById('contentUnsavedSaveBtn');
        discardBtn = document.getElementById('contentUnsavedDiscardBtn');
        cancelBtn = document.getElementById('contentUnsavedCancelBtn');
        if (!modalEl) {
            return;
        }
        bound = true;

        saveBtn?.addEventListener('click', () => {
            closeModal('save');
        });
        discardBtn?.addEventListener('click', () => {
            closeModal('discard');
        });
        cancelBtn?.addEventListener('click', () => {
            closeModal('cancel');
        });
        modalEl.addEventListener('click', (event) => {
            if (event.target === modalEl) {
                closeModal('cancel');
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            if (modalEl && modalEl.style.display === 'flex') {
                closeModal('cancel');
            }
        });
    }

    function openModal(message) {
        bindModal();
        return new Promise((resolve) => {
            if (!modalEl) {
                resolve('discard');
                return;
            }
            pendingResolve = resolve;
            if (messageEl) {
                messageEl.textContent = message
                    || 'You have unsaved changes. What would you like to do?';
            }
            modalEl.style.display = 'flex';
            modalEl.setAttribute('aria-hidden', 'false');
            saveBtn?.focus();
        });
    }

    function closeModal(result) {
        if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
        }
        const resolve = pendingResolve;
        pendingResolve = null;
        if (resolve) {
            resolve(result);
        }
    }

    /**
     * Prompt when leaving with unsaved editor changes.
     *
     * @param {Object} options
     * @param {Function} options.isDirty - Returns true when there are unsaved changes.
     * @param {Function} options.save - Async save handler; return true on success.
     * @param {Function} [options.discard] - Revert local dirty state without saving.
     * @param {string} [options.message] - Modal body copy.
     * @param {string} [options.fallbackMessage] - window.confirm text when modal markup is missing.
     * @returns {Promise<'proceed'|'abort'>}
     */
    async function confirmLeave(options) {
        const isDirty = typeof options.isDirty === 'function' ? options.isDirty : () => false;
        if (!isDirty()) {
            return 'proceed';
        }

        bindModal();
        if (!modalEl) {
            const proceed = window.confirm(
                options.fallbackMessage
                || options.message
                || 'Leave without saving?'
            );
            return proceed ? 'proceed' : 'abort';
        }

        const choice = await openModal(options.message);
        if (choice === 'cancel') {
            return 'abort';
        }
        if (choice === 'save') {
            const saved = typeof options.save === 'function' ? await options.save() : true;
            return saved ? 'proceed' : 'abort';
        }
        if (typeof options.discard === 'function') {
            options.discard();
        }
        return 'proceed';
    }

    window.bandpromoEditorUnsavedModal = {
        confirmLeave: confirmLeave,
    };
})();
