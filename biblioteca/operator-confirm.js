(function () {
    'use strict';

    let modalEl = null;
    let titleEl = null;
    let bodyEl = null;
    let confirmBtn = null;
    let cancelBtn = null;
    let pendingResolve = null;
    let bound = false;

    function bindModal() {
        if (bound) {
            return;
        }
        modalEl = document.getElementById('adminConfirmModal');
        titleEl = document.getElementById('adminConfirmModalTitle');
        bodyEl = document.getElementById('adminConfirmModalBody');
        confirmBtn = document.getElementById('adminConfirmModalConfirmBtn');
        cancelBtn = document.getElementById('adminConfirmModalCancelBtn');
        if (!modalEl) {
            return;
        }
        bound = true;

        confirmBtn?.addEventListener('click', () => {
            closeModal(true);
        });
        cancelBtn?.addEventListener('click', () => {
            closeModal(false);
        });
        modalEl.addEventListener('click', (event) => {
            if (event.target === modalEl) {
                closeModal(false);
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            if (modalEl && modalEl.style.display === 'flex') {
                closeModal(false);
            }
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
            resolve(Boolean(result));
        }
    }

    /**
     * In-app confirm (replaces window.confirm for operator admin).
     *
     * @param {Object} [options]
     * @param {string} [options.title]
     * @param {string} [options.body]
     * @param {string} [options.confirmLabel]
     * @param {string} [options.cancelLabel]
     * @param {'default'|'danger'} [options.tone]
     * @returns {Promise<boolean>}
     */
    function bandpromoConfirm(options) {
        const opts = options && typeof options === 'object' ? options : {};
        const title = String(opts.title || 'Please confirm');
        const body = String(opts.body || '');
        const confirmLabel = String(opts.confirmLabel || 'Confirm');
        const cancelLabel = String(opts.cancelLabel || 'Cancel');
        const tone = opts.tone === 'danger' ? 'danger' : 'default';

        bindModal();
        return new Promise((resolve) => {
            if (!modalEl || !confirmBtn) {
                const fallback = body !== '' ? (title + '\n\n' + body) : title;
                resolve(window.confirm(fallback));
                return;
            }
            if (pendingResolve) {
                pendingResolve(false);
                pendingResolve = null;
            }
            pendingResolve = resolve;
            if (titleEl) {
                titleEl.textContent = title;
            }
            if (bodyEl) {
                bodyEl.textContent = body;
            }
            confirmBtn.textContent = confirmLabel;
            confirmBtn.classList.remove('btn-primary', 'btn-danger');
            confirmBtn.classList.add(tone === 'danger' ? 'btn-danger' : 'btn-primary');
            if (cancelBtn) {
                cancelBtn.textContent = cancelLabel;
            }
            modalEl.style.display = 'flex';
            modalEl.setAttribute('aria-hidden', 'false');
            confirmBtn.focus();
        });
    }

    window.bandpromoConfirm = bandpromoConfirm;
})();
