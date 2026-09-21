(function () {
    'use strict';

    /**
     * Shared Content editor breadcrumb behaviour (Content > Section > Pool|Editor).
     * Markup comes from bandpromo_admin_render_content_breadcrumb() path/presets.
     *
     * @param {Object} options
     * @param {string} options.currentId - Element id for the current crumb text
     * @param {string} options.poolLinkId - Button id for the section pool link
     * @param {string} [options.contentLinkId] - Optional Content root button id
     * @param {Function} options.onPoolClick - Navigate to pool (use requestClose when editing)
     * @returns {{ setView: function(string): void }}
     */
    function attach(options) {
        const opts = options && typeof options === 'object' ? options : {};
        const currentEl = document.getElementById(String(opts.currentId || ''));
        const poolLinkId = String(opts.poolLinkId || '');
        const poolBtn = document.getElementById(poolLinkId);
        let contentLinkId = String(opts.contentLinkId || '');
        if (!contentLinkId && poolLinkId.indexOf('BreadcrumbPool') !== -1) {
            contentLinkId = poolLinkId.replace(/BreadcrumbPool$/, 'BreadcrumbContent');
        }
        const contentBtn = contentLinkId ? document.getElementById(contentLinkId) : null;

        function setView(view) {
            if (!currentEl) {
                return;
            }
            currentEl.textContent = view === 'edit' ? 'Editor' : 'Pool';
        }

        function onPoolClick() {
            if (typeof opts.onPoolClick === 'function') {
                opts.onPoolClick();
            }
        }

        poolBtn?.addEventListener('click', onPoolClick);
        if (contentBtn && contentBtn !== poolBtn) {
            contentBtn.addEventListener('click', onPoolClick);
        }

        return { setView: setView };
    }

    window.bandpromoContentEditorBreadcrumb = {
        attach: attach,
    };
})();
