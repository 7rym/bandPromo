(function () {
    'use strict';

    let modalEl = null;
    let titleEl = null;
    let bodyEl = null;
    let confirmBtn = null;
    let cancelBtn = null;
    let pendingResolve = null;
    let bound = false;
    let navDismissBound = false;

    const CONFIRM_TONE_CLASSES = ['btn-primary', 'btn-danger', 'btn-good'];

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
        if (!navDismissBound) {
            navDismissBound = true;
            // Do not carry an acknowledge dialog onto Files / other tabs.
            document.addEventListener('click', (event) => {
                if (!modalEl || modalEl.style.display !== 'flex') {
                    return;
                }
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }
                const nav = target.closest(
                    '.tabs a.tab-link, .tabs .tab-link, a[href*="tab="], #adminConfirmModal'
                );
                if (!nav || nav.closest('#adminConfirmModal')) {
                    return;
                }
                if (nav.matches('a[href*="tab="], .tabs a.tab-link, .tabs .tab-link')) {
                    closeModal(false);
                }
            }, true);
        }
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

    function applyConfirmTone(button, tone) {
        if (!button) {
            return;
        }
        CONFIRM_TONE_CLASSES.forEach((className) => button.classList.remove(className));
        if (tone === 'danger') {
            button.classList.add('btn-danger');
            return;
        }
        if (tone === 'good') {
            button.classList.add('btn-good');
            return;
        }
        if (tone === 'quiet') {
            // Neutral .btn only — optional / Status alternate paths (e.g. Force).
            return;
        }
        button.classList.add('btn-primary');
    }

    /**
     * In-app confirm (replaces window.confirm for operator admin).
     *
     * @param {Object} [options]
     * @param {string} [options.title]
     * @param {string} [options.body]
     * @param {string} [options.confirmLabel]
     * @param {string} [options.cancelLabel]
     * @param {boolean} [options.hideCancel]
     * @param {'default'|'danger'|'good'|'quiet'} [options.tone]
     * @returns {Promise<boolean>}
     */
    function bandpromoConfirm(options) {
        const opts = options && typeof options === 'object' ? options : {};
        const title = String(opts.title || 'Please confirm');
        const body = String(opts.body || '');
        const confirmLabel = String(opts.confirmLabel || 'Confirm');
        const cancelLabel = String(opts.cancelLabel || 'Cancel');
        const hideCancel = !!opts.hideCancel;
        const rawTone = String(opts.tone || 'default').trim().toLowerCase();
        const tone = (
            rawTone === 'danger' || rawTone === 'good' || rawTone === 'quiet'
                ? rawTone
                : 'default'
        );

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
            applyConfirmTone(confirmBtn, tone);
            if (cancelBtn) {
                cancelBtn.textContent = cancelLabel;
                cancelBtn.hidden = hideCancel;
                cancelBtn.style.display = hideCancel ? 'none' : '';
            }
            modalEl.style.display = 'flex';
            modalEl.setAttribute('aria-hidden', 'false');
            confirmBtn.focus();
        });
    }

    window.bandpromoConfirm = bandpromoConfirm;
})();
