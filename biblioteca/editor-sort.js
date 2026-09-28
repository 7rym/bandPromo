(function (global) {
    'use strict';

    function trackSortLabel(track) {
        if (!track || typeof track !== 'object') {
            return '';
        }
        const artist = String(track.artist || '').trim();
        let title = String(track.title || track.file || '').trim();
        if (title === '') {
            title = 'Untitled';
        }

        return artist !== '' ? `${artist} - ${title}` : title;
    }

    function sortTracksByLabel(tracks) {
        return (Array.isArray(tracks) ? tracks : []).slice().sort((left, right) =>
            trackSortLabel(left).localeCompare(trackSortLabel(right), undefined, {
                sensitivity: 'base',
                numeric: true,
            })
        );
    }

    function sortItemsByTitle(items, titleKey) {
        const key = titleKey || 'title';
        return (Array.isArray(items) ? items : []).slice().sort((left, right) =>
            String(left?.[key] || left?.id || '').localeCompare(
                String(right?.[key] || right?.id || ''),
                undefined,
                { sensitivity: 'base', numeric: true }
            )
        );
    }

    /**
     * Newest first by YYYY / YYYY-MM-DD. Empty dates sink. Title A–Z as tie-break.
     */
    function dateSortValue(value) {
        const trimmed = String(value || '').trim();
        if (/^\d{4}$/.test(trimmed)) {
            return parseInt(trimmed + '0101', 10) || 0;
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return parseInt(trimmed.replace(/-/g, ''), 10) || 0;
        }
        return 0;
    }

    function sortItemsByDateDesc(items, dateKey, titleKey) {
        const dKey = dateKey || 'publish_date';
        const tKey = titleKey || 'title';
        return (Array.isArray(items) ? items : []).slice().sort((left, right) => {
            const ld = dateSortValue(left?.[dKey]);
            const rd = dateSortValue(right?.[dKey]);
            if (ld !== rd) {
                return rd - ld;
            }
            return String(left?.[tKey] || left?.id || '').localeCompare(
                String(right?.[tKey] || right?.id || ''),
                undefined,
                { sensitivity: 'base', numeric: true }
            );
        });
    }

    global.bandpromoEditorSort = {
        trackSortLabel,
        sortTracksByLabel,
        sortItemsByTitle,
        dateSortValue,
        sortItemsByDateDesc,
    };
}(window));
