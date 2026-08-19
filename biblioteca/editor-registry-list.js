(function () {
    'use strict';

    /**
     * Render a registry/pool list.
     *
     * @param {HTMLElement} listEl - The <ol>/<ul> to render into
     * @param {Object} options
     * @param {Array}    options.entries        - Array of entity objects
     * @param {string}   options.selectedId     - Currently selected entity ID
     * @param {string}   options.dataAttribute  - Data attribute name for the entity ID (e.g. 'gallery-id', 'playlist-id')
     * @param {string}   options.emptyMessage   - Message when no entries
     * @param {Function} options.renderRow      - Function(entry, isSelected) returning the <li> HTML string
     */
    function renderRegistryList(listEl, options) {
        if (!listEl) return;

        var entries = options.entries || [];
        var emptyMessage = options.emptyMessage || 'No items available.';
        var renderRow = options.renderRow;

        if (!entries.length) {
            listEl.innerHTML = '<li class="player-layout-empty">' + escapeHtml(emptyMessage) + '</li>';
            return;
        }

        listEl.innerHTML = entries.map(function (entry) {
            var isSelected = String(entry.id || '') === String(options.selectedId || '');
            return renderRow(entry, isSelected);
        }).join('');
    }

    /**
     * Build a standard registry row HTML string.
     *
     * @param {Object} options
     * @param {string}  options.id             - Entity ID
     * @param {string}  options.dataAttribute  - Data attribute name (e.g. 'data-gallery-id')
     * @param {boolean} options.isSelected     - Whether this row is selected
     * @param {string}  options.icon           - Emoji icon
     * @param {string}  options.title          - Display title (will be escaped)
     * @param {string}  [options.meta]         - Metadata HTML (already escaped)
     * @param {string}  [options.extraClasses] - Additional CSS classes for the <li>
     * @param {Array}   [options.actions]      - Array of action button HTML strings
     * @returns {string} The <li> HTML
     */
    function registryRow(options) {
        var id = options.id || '';
        var dataAttr = options.dataAttribute || 'data-id';
        var selectedClass = options.isSelected ? ' playlist-editor-row-selected editor-row--selected' : '';
        var extra = options.extraClasses ? ' ' + options.extraClasses : '';
        var title = escapeHtml(options.title || id);
        var icon = options.icon || '';
        var meta = options.meta || '';
        var actions = (options.actions || []).join('');

        return '<li class="playlist-editor-row editor-row page-pool-row registry-row' + extra + selectedClass + '" '
            + dataAttr + '="' + escapeAttr(id) + '" '
            + 'aria-selected="' + (options.isSelected ? 'true' : 'false') + '">'
            + '<span class="playlist-track-info">'
            + '<strong>' + icon + (icon ? ' ' : '') + title + '</strong>'
            + (meta ? '<span class="playlist-track-meta">' + meta + '</span>' : '')
            + '</span>'
            + '<span class="page-pool-row-actions registry-row-actions">' + actions + '</span>'
            + '</li>';
    }

    /**
     * Build an action button HTML string.
     *
     * @param {Object} options
     * @param {string} options.icon      - Button icon/emoji
     * @param {string} options.title     - Button title attribute
     * @param {string} options.className - CSS class (e.g. 'page-pool-edit-btn')
     * @param {string} [options.dataAttribute] - Optional data attribute (e.g. 'data-id="xyz"')
     * @returns {string}
     */
    function actionButton(options) {
        var className = String(options.className || '').trim();
        var expandedClassName = expandRegistryButtonClasses(className);
        return '<button type="button" class="icon-btn icon-btn--pool '
            + expandedClassName + '"'
            + (options.dataAttribute ? ' ' + options.dataAttribute : '')
            + ' title="' + escapeAttr(options.title || '') + '"'
            + '>' + (options.icon || '') + '</button>';
    }

    function expandRegistryButtonClasses(className) {
        var classes = className ? className.split(/\s+/).filter(Boolean) : [];
        if (classes.indexOf('page-pool-edit-btn') !== -1 && classes.indexOf('registry-btn--edit') === -1) {
            classes.push('registry-btn--edit');
        }
        if (classes.indexOf('page-pool-delete-btn') !== -1 && classes.indexOf('registry-btn--delete') === -1) {
            classes.push('registry-btn--delete');
        }
        if (classes.indexOf('page-pool-duplicate-btn') !== -1 && classes.indexOf('registry-btn--duplicate') === -1) {
            classes.push('registry-btn--duplicate');
        }
        if (classes.indexOf('page-pool-lock-btn') !== -1 && classes.indexOf('registry-btn--lock') === -1) {
            classes.push('registry-btn--lock');
        }
        if (classes.indexOf('page-pool-lock-btn--active') !== -1 && classes.indexOf('registry-btn--lock-active') === -1) {
            classes.push('registry-btn--lock-active');
        }
        return classes.join(' ');
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    window.bandpromoRegistryList = {
        render: renderRegistryList,
        row: registryRow,
        actionButton: actionButton,
        escapeHtml: escapeHtml,
    };
})();
