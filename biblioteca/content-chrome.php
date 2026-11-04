<?php
declare(strict_types=1);

/**
 * Shared Content surface markup for Branding Live preview (and future live dual-call).
 *
 * Preview is brand-jailed: only the brand document’s logo/poster plus generic demo
 * labels. Never install campaign/playlist names (must work on a blank install).
 *
 * @param array<string, mixed> $options
 */
function bandpromo_content_chrome_render(array $options = []): string
{
    $preview = !empty($options['preview']);
    $playlistMode = strtolower(trim((string) ($options['playlist_selector'] ?? 'coverflow')));
    if (!in_array($playlistMode, ['dropdown', 'buttons', 'coverflow'], true)) {
        $playlistMode = 'coverflow';
    }

    $logo = trim((string) ($options['logo'] ?? ''));
    $poster = trim((string) ($options['poster'] ?? ''));
    $brandTitle = trim((string) ($options['brand_title'] ?? 'Brand'));
    if ($brandTitle === '') {
        $brandTitle = 'Brand';
    }

    $esc = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    };

    // Generic multi-campaign / multi-playlist chrome shape (live navigator layout).
    // Labels are placeholders — not catalogue from this install.
    $campaignCount = 3;
    $campaigns = [
        ['title' => 'Campaign one', 'initial' => '1', 'current' => true],
        ['title' => 'Campaign two', 'initial' => '2', 'current' => false],
        ['title' => 'Campaign three', 'initial' => '3', 'current' => false],
    ];
    $playlists = [
        ['title' => 'First playlist', 'active' => true, 'initial' => 'A'],
        ['title' => 'Second playlist', 'active' => false, 'initial' => 'B'],
        ['title' => 'Third playlist', 'active' => false, 'initial' => 'C'],
        ['title' => 'Fourth playlist', 'active' => false, 'initial' => 'D'],
    ];
    $demoTracks = [
        ['title' => 'Track title', 'version' => 'Version', 'artist' => 'Artist name', 'current' => true],
        ['title' => 'Another track', 'version' => '', 'artist' => 'Artist name', 'current' => false],
        ['title' => 'Third track', 'version' => 'Remix', 'artist' => 'Artist name', 'current' => false],
    ];

    $brandInitial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($brandTitle, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($brandTitle, 0, 1));
    if ($brandInitial === '') {
        $brandInitial = '♪';
    }
    $logoImg = $logo !== ''
        ? '<img src="' . $esc($logo) . '" alt="' . $esc($brandTitle) . '" class="shell-logo-img" id="shell-logo" loading="lazy">'
        : '<span class="shell-logo-img" id="shell-logo" aria-hidden="true">' . $esc($brandInitial) . '</span>';

    $toggleButtons = '';
    if ($preview) {
        // Idle / Hover / Active samples for Branding → Buttons.
        $toggleButtons = ''
            . '<button type="button" tabindex="-1" data-preview-state="idle">Idle</button>'
            . '<button type="button" tabindex="-1" data-preview-state="hover">Hover</button>'
            . '<button type="button" class="active" tabindex="-1" data-preview-state="active">Active</button>';
    } else {
        $toggleButtons = ''
            . '<button type="button" class="active" data-view="playlist" tabindex="-1">Playlists</button>'
            . '<button type="button" data-view="lyrics" tabindex="-1">Lyrics</button>'
            . '<button type="button" data-view="page" tabindex="-1">Bio</button>';
    }

    $campaignItems = '';
    foreach ($campaigns as $entry) {
        $title = (string) ($entry['title'] ?? '');
        $initial = (string) ($entry['initial'] ?? '♪');
        $current = !empty($entry['current']);
        // Real brand logo on every chip when assigned (preview how it looks).
        $media = $logo !== ''
            ? '<img src="' . $esc($logo) . '" alt="" class="campaign-logo-strip-logo" loading="lazy" decoding="async">'
            : '<span class="campaign-logo-strip-placeholder" aria-hidden="true">' . $esc($initial) . '</span>';
        $campaignItems .= '<button type="button"'
            . ' class="campaign-logo-strip-item' . ($current ? ' is-current' : '') . '"'
            . ' role="option"'
            . ' tabindex="-1"'
            . ' aria-selected="' . ($current ? 'true' : 'false') . '"'
            . ' aria-label="' . $esc($title) . '"'
            . ' title="' . $esc($title) . '"'
            . '>' . $media . '</button>';
    }

    $playlistBody = '';
    if ($playlistMode === 'buttons') {
        $playlistBody = '<div class="playlist-selector-buttons">';
        foreach ($playlists as $entry) {
            $title = (string) ($entry['title'] ?? '');
            $active = !empty($entry['active']);
            $playlistBody .= '<button type="button" class="playlist-selector-btn'
                . ($active ? ' is-active' : '') . '" tabindex="-1">'
                . $esc($title) . '</button>';
        }
        $playlistBody .= '</div>';
    } elseif ($playlistMode === 'coverflow') {
        $playlistBody = '<div class="playlist-coverflow" role="listbox" aria-label="Playlists">';
        foreach ($playlists as $entry) {
            $title = (string) ($entry['title'] ?? '');
            $initial = (string) ($entry['initial'] ?? '♪');
            $active = !empty($entry['active']);
            // Same brand poster on every thumb when assigned.
            $thumb = $poster !== ''
                ? '<img class="playlist-coverflow-thumb" src="' . $esc($poster) . '" alt="" loading="lazy">'
                : '<span class="playlist-coverflow-thumb playlist-coverflow-placeholder" aria-hidden="true">'
                    . $esc($initial) . '</span>';
            $playlistBody .= '<button type="button" class="playlist-coverflow-item'
                . ($active ? ' is-active' : '') . '" role="option" tabindex="-1"'
                . ' aria-selected="' . ($active ? 'true' : 'false') . '"'
                . ' title="' . $esc($title) . '">' . $thumb . '</button>';
        }
        $playlistBody .= '</div>';
    } else {
        $playlistBody = '<select id="playlistSelector" tabindex="-1" aria-label="Playlist">';
        foreach ($playlists as $entry) {
            $title = (string) ($entry['title'] ?? '');
            $active = !empty($entry['active']);
            $playlistBody .= '<option' . ($active ? ' selected' : '') . '>' . $esc($title) . '</option>';
        }
        $playlistBody .= '</select>';
    }

    $trackRows = '';
    foreach ($demoTracks as $track) {
        $title = (string) ($track['title'] ?? 'Track');
        $version = trim((string) ($track['version'] ?? ''));
        $artist = trim((string) ($track['artist'] ?? ''));
        $current = !empty($track['current']);
        $versionHtml = $version !== ''
            ? ' <span class="playlist-track-tale">[' . $esc($version) . ']</span>'
            : '';
        $artistHtml = $artist !== ''
            ? ' <span class="playlist-track-artist">' . $esc($artist) . '</span>'
            : '';
        $cover = $poster !== ''
            ? '<img alt="" class="playlist-track-cover" src="' . $esc($poster) . '" loading="lazy" decoding="async" width="70" height="70">'
            : '<span class="playlist-track-cover playlist-coverflow-placeholder" aria-hidden="true">'
                . $esc($brandInitial) . '</span>';
        $trackRows .= '<div class="playlist-item' . ($current ? ' current' : '') . '">'
            . $cover
            . '<div class="playlist-track-content">'
            . '<h5 class="playlist-track-title">' . $esc($title) . $versionHtml . $artistHtml . '</h5>'
            . '<div class="playlist-track-description player-markdown-host">'
            . '<p class="page-text-small">Sample track row for Branding preview.</p>'
            . '</div></div></div>';
    }

    $playlistRowsHtml = ''
        . '<div class="playlist-box active" data-content-box="playlist-demo" data-preview-focus="playlist-selector">'
        . '<div class="playlist-tracks">'
        . $trackRows
        . '</div></div>';

    // Live page panel glass: .page-box > .page-content > .page-prose-row > .page-richtext
    $panelHtml = ''
        . '<div class="page-box active" data-content-box="page-demo" data-preview-focus="content-panels">'
        . '<div class="page-content">'
        . '<div class="page-prose-row">'
        . '<div class="page-richtext">'
        . '<h1>Heading 1</h1>'
        . '<h2>Heading 2</h2>'
        . '<h3>Heading 3</h3>'
        . '<h4>Heading 4</h4>'
        . '<p>Paragraph with <strong>bold</strong> and <em>italic</em> emphasis, plus inline <code>code</code>.</p>'
        . '<p class="page-text-small">Small — secondary notes and fine print.</p>'
        . '<pre class="page-text-code">Code — monospace sample text</pre>'
        . '<blockquote><p>Cited text — a blockquote for call-outs and quotes.</p></blockquote>'
        . '<ul><li>Unordered list item</li><li>Another bullet</li></ul>'
        . '<ol><li>Ordered list item</li><li>Second step</li></ol>'
        . '<hr>'
        . '<p class="play-preview-content-links">'
        . '<a href="#" class="play-preview-content-link" data-preview-link="default" onclick="return false;">Default link</a> '
        . '<a href="#" class="play-preview-content-link" data-preview-link="hover" onclick="return false;">Hover state</a> '
        . '<a href="#" class="play-preview-content-link" data-preview-link="visited" onclick="return false;">Visited state</a>'
        . '</p>'
        . '</div></div></div></div>';

    $html = '<div id="content-container" class="play-preview-content-container"'
        . ($preview ? ' data-preview="1"' : '') . '>'
        . '<div class="shell-logo" id="shell-logo-wrap" data-campaign-count="' . (int) $campaignCount . '">'
        . $logoImg
        . '</div>'
        . '<div class="content-toggle" data-preview-focus="content-chrome" aria-label="Buttons sample">'
        . $toggleButtons
        . '</div>'
        . '<div class="playlist-scope-toolbar" id="playlistScopeToolbar"'
        . ' data-has-campaigns="1" data-has-playlists="1" data-preview-focus="playlist-selector">'
        . '<div class="campaign-logo-strip" id="campaignLogoStrip" role="listbox" aria-label="Campaigns">'
        . $campaignItems
        . '</div>'
        . '<div class="playlist-selector playlist-selector--' . $esc($playlistMode) . '"'
        . ' id="playlistSelectorWrap" data-mode="' . $esc($playlistMode) . '">'
        . $playlistBody
        . '</div>'
        . '</div>'
        . $playlistRowsHtml
        . $panelHtml
        . '</div>';

    return $html;
}
