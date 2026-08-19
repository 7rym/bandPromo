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

    global.bandpromoEditorSort = {
        trackSortLabel,
        sortTracksByLabel,
        sortItemsByTitle,
    };
}(window));
