(function () {
    'use strict';

    /**
     * Create a range-selection controller for a two-list editor (available + active).
     *
     * @param {Object} options
     * @param {string}   options.dataKey       - Dataset key to identify rows ('src', 'file', 'id')
     * @param {Function} options.getAvailableRows - Function() returning array of row elements in the available list
     * @param {Function} options.getActiveRows    - Function() returning array of row elements in the active list
     * @param {Function} options.onSelectionChange - Function(listName) called after selection changes
     * @returns {Object} Controller with selection state and handlers
     */
    function createRangeSelection(options) {
        var dataKey = options.dataKey;
        var getAvailableRows = options.getAvailableRows;
        var getActiveRows = options.getActiveRows;
        var onSelectionChange = options.onSelectionChange;

        var selectedAvailable = new Set();
        var selectedActive = new Set();
        var anchorAvailable = '';
        var anchorActive = '';

        function getKey(row) {
            return String(row.dataset[dataKey] || '').trim();
        }

        function getRows(listName) {
            return listName === 'available' ? getAvailableRows() : getActiveRows();
        }

        function selectRange(listName, targetKey, preserveExisting) {
            var rows = getRows(listName);
            if (!rows.length) return;

            var anchor = listName === 'available' ? anchorAvailable : anchorActive;
            var selected = listName === 'available' ? selectedAvailable : selectedActive;

            if (!anchor || !rows.some(function (r) { return getKey(r) === anchor; })) {
                anchor = targetKey;
            }
            var anchorIndex = rows.findIndex(function (r) { return getKey(r) === anchor; });
            var targetIndex = rows.findIndex(function (r) { return getKey(r) === targetKey; });
            if (anchorIndex === -1 || targetIndex === -1) return;

            var next = preserveExisting ? new Set(selected) : new Set();
            var start = Math.min(anchorIndex, targetIndex);
            var end = Math.max(anchorIndex, targetIndex);
            rows.slice(start, end + 1).forEach(function (r) {
                var k = getKey(r);
                if (k) next.add(k);
            });

            if (listName === 'available') {
                selectedAvailable = next;
            } else {
                selectedActive = next;
            }
        }

        function handleSelection(listName, row, event) {
            var key = getKey(row);
            if (!key) return;

            var otherList = listName === 'available' ? 'active' : 'available';
            if (otherList === 'available') {
                selectedAvailable = new Set();
                anchorAvailable = '';
            } else {
                selectedActive = new Set();
                anchorActive = '';
            }
            onSelectionChange(otherList);

            var selected = listName === 'available' ? selectedAvailable : selectedActive;

            if (event.shiftKey) {
                selectRange(listName, key, event.ctrlKey || event.metaKey);
            } else if (event.ctrlKey || event.metaKey) {
                if (selected.has(key)) {
                    selected.delete(key);
                } else {
                    selected.add(key);
                }
            } else {
                if (listName === 'available') {
                    selectedAvailable = new Set([key]);
                } else {
                    selectedActive = new Set([key]);
                }
            }

            if (listName === 'available') {
                anchorAvailable = selectedAvailable.size ? key : '';
            } else {
                anchorActive = selectedActive.size ? key : '';
            }
            onSelectionChange(listName);
        }

        return {
            handleSelection: handleSelection,
            getSelected: function (listName) {
                return listName === 'available' ? selectedAvailable : selectedActive;
            },
            setSelected: function (listName, set) {
                if (listName === 'available') { selectedAvailable = set; }
                else { selectedActive = set; }
            },
            getAnchor: function (listName) {
                return listName === 'available' ? anchorAvailable : anchorActive;
            },
            setAnchor: function (listName, key) {
                if (listName === 'available') { anchorAvailable = key; }
                else { anchorActive = key; }
            },
            clearAll: function () {
                selectedAvailable = new Set();
                selectedActive = new Set();
                anchorAvailable = '';
                anchorActive = '';
            },
        };
    }

    window.bandpromoRangeSelection = { create: createRangeSelection };
})();
