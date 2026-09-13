(function () {
    'use strict';

    /**
     * Shared Content editor breadcrumb (section > Pool|Editor).
     *
     * @param {Object} options
     * @param {string} options.currentId - Element id for the current crumb text
     * @param {string} options.poolLinkId - Button id for the section root link
     * @param {Function} options.onPoolClick - Navigate to pool (use requestClose when editing)
     * @returns {{ setView: function(string): void }}
     */
    function attach(options) {
        const opts = options && typeof options === 'object' ? options : {};
        const currentEl = document.getElementById(String(opts.currentId || ''));
        const poolBtn = document.getElementById(String(opts.poolLinkId || ''));

        function setView(view) {
            if (!currentEl) {
                return;
            }
            currentEl.textContent = view === 'edit' ? 'Editor' : 'Pool';
        }

        poolBtn?.addEventListener('click', () => {
            if (typeof opts.onPoolClick === 'function') {
                opts.onPoolClick();
            }
        });

        return { setView: setView };
    }

    window.bandpromoContentEditorBreadcrumb = {
        attach: attach,
    };
})();
