<?php
declare(strict_types=1);

/**
 * Shared #mediaplayer chrome markup for live /play and Branding Live preview.
 *
 * Preview and live call the same renderer. Preview passes demo track/cover data
 * and preview=true (inert controls, no live wiring). Live passes empty media
 * slots that player.js fills.
 */

require_once __DIR__ . '/player-icons.php';

/**
 * @param array<string,mixed> $options
 */
function bandpromo_player_chrome_render(array $options = []): string
{
    $preview = !empty($options['preview']);
    $loginStatus = !empty($options['login_status']);
    $showAdmin = !empty($options['show_admin']);
    $showDebug = !empty($options['show_debug']);
    $username = trim((string) ($options['username'] ?? ''));
    if ($username === '') {
        $username = 'listener';
    }
    $beggarsBanquet = array_key_exists('beggars_banquet', $options)
        ? !empty($options['beggars_banquet'])
        : true;
    $supportEnabled = !empty($options['support_enabled']);
    $supportUrl = trim((string) ($options['support_url'] ?? ''));
    $supportLabel = trim((string) ($options['support_label'] ?? 'Support'));
    if ($supportLabel === '') {
        $supportLabel = 'Support';
    }
    $supportBg = trim((string) ($options['support_button_background'] ?? '#323842'));
    $supportFg = trim((string) ($options['support_button_text'] ?? '#ffffff'));
    if ($supportBg === '') {
        $supportBg = '#323842';
    }
    if ($supportFg === '') {
        $supportFg = '#ffffff';
    }

    $demo = is_array($options['demo'] ?? null) ? $options['demo'] : [];
    $artist = trim((string) ($demo['artist'] ?? ($preview ? 'Artist name' : 'Artist')));
    $title = trim((string) ($demo['title'] ?? ($preview ? 'Track title' : 'Title')));
    $version = trim((string) ($demo['version'] ?? ($preview ? 'Version' : '')));
    $coverUrl = trim((string) ($demo['cover_url'] ?? ''));
    $prevCoverUrl = trim((string) ($demo['prev_cover_url'] ?? $coverUrl));
    $nextCoverUrl = trim((string) ($demo['next_cover_url'] ?? $coverUrl));
    $timeCurrent = trim((string) ($demo['time_current'] ?? ($preview ? '0:42' : '0:00')));
    $timeDuration = trim((string) ($demo['time_duration'] ?? ($preview ? '3:24' : '0:00')));

    $showPlayerUserArea = $loginStatus || $showAdmin || $showDebug;
    $userMode = $loginStatus ? 'status' : 'tools';

    $esc = static function (string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    };

    $coverImgAttrs = $coverUrl !== ''
        ? ' src="' . $esc($coverUrl) . '"'
        : ' src=""';
    $prevImgAttrs = $prevCoverUrl !== ''
        ? ' src="' . $esc($prevCoverUrl) . '"'
        : ' src=""';
    $nextImgAttrs = $nextCoverUrl !== ''
        ? ' src="' . $esc($nextCoverUrl) . '"'
        : ' src=""';

    // Preview must not run live transport handlers.
    $prevClick = $preview ? '' : ' onclick="prevSong()"';
    $nextClick = $preview ? '' : ' onclick="nextSong()"';
    $playClick = $preview ? '' : ' onclick="togglePlay()"';
    $inertAttr = $preview ? ' inert' : '';
    $versionHidden = $version === '' ? ' hidden' : '';

    $html = '';
    $html .= '<div id="mediaplayer"' . $inertAttr . '>';
    $html .= '<div id="player-user-area"' . ($showPlayerUserArea ? '' : ' hidden') . '>';
    $html .= '<div class="player-user-status" data-player-user-mode="' . $esc($userMode) . '">';
    $html .= '<div class="player-user-tools">';
    if ($showAdmin) {
        $html .= '<a class="player-user-tool" id="admin-btn" href="/admin.php" title="Admin panel" aria-label="Open admin panel">⚙️</a>';
    }
    if ($showDebug) {
        $html .= '<button type="button" class="player-user-tool" id="debug-panel-btn" title="Developer debug panel" aria-label="Open developer debug panel">🐛</button>';
    }
    if ($loginStatus) {
        $html .= '<a class="player-user-tool player-user-profile" href="#" aria-disabled="true" tabindex="-1" title="Account preferences come in a later update" aria-label="Account (not available yet)">';
        $html .= bandpromo_player_icon_svg('user');
        $html .= '</a>';
        $html .= '<strong class="player-user-status-name">' . $esc($username) . '</strong>';
    }
    $html .= '</div>';
    if ($loginStatus) {
        $logoutHref = $preview ? '#' : '/?logout=1';
        $html .= '<a class="player-user-logout" href="' . $esc($logoutHref) . '" title="Log out" aria-label="Log out">'
            . bandpromo_player_icon_svg('logout') . '</a>';
    } else {
        $html .= '<span class="player-user-status-text player-user-status-text--spacer" aria-hidden="true"></span>';
    }
    $html .= '</div></div>';

    if (!$preview) {
        $html .= '<div id="operatorDeliveryNotice" class="operator-delivery-notice" hidden>';
        $html .= '<strong>Publish build required.</strong>';
        $html .= '<span id="operatorDeliveryNoticeText">Some tracks are waiting for streaming MP3 delivery. Open System → Status → Site health.</span>';
        $html .= '<a href="/admin.php?tab=system&amp;stab=deliverables#siteHealthCard">Open Site health</a>';
        $html .= '</div>';
    }

    $html .= '<div class="scene">';
    $html .= '<div class="side-card prev"' . $prevClick . '>';
    $html .= '<img' . $prevImgAttrs . ' id="prevCover" alt="Previous">';
    $html .= '</div>';
    $html .= '<div class="card-wrapper" id="cardWrapper">';
    $html .= '<img' . $coverImgAttrs . ' alt="Album Cover" class="cover-art" id="coverImage">';
    $html .= '<video class="cover-art cover-art-video" id="coverVideo" loop muted playsinline preload="metadata" hidden></video>';
    $html .= '<div class="reflection">';
    $html .= '<img' . $coverImgAttrs . ' alt="" id="reflectionImage">';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="side-card next"' . $nextClick . '>';
    $html .= '<img' . $nextImgAttrs . ' id="nextCover" alt="Next">';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="player-transport">';
    $html .= '<canvas id="analyzer" aria-hidden="true"></canvas>';
    $html .= '<div class="track-info">';
    $html .= '<h3 id="artistName">' . $esc($artist) . '</h3>';
    $html .= '<div class="track-headline">';
    $html .= '<h2 id="songTitle">' . $esc($title) . '</h2>';
    $html .= '<p id="songVersion" class="track-version"' . $versionHidden . '>' . $esc($version) . '</p>';
    $html .= '</div></div>';

    $html .= '<div class="player-controls" role="group" aria-label="Playback">';
    $html .= '<button type="button" class="player-control-btn" id="repeatBtn" aria-label="Repeat off" title="Repeat off" data-repeat="none">';
    $html .= bandpromo_player_icon_svg('repeat');
    $html .= bandpromo_player_icon_svg('repeat-one');
    $html .= '</button>';
    $html .= '<button type="button" class="player-control-btn" id="prevBtn"' . $prevClick . ' aria-label="Previous track" title="Previous">';
    $html .= bandpromo_player_icon_svg('prev');
    $html .= '</button>';
    $html .= '<button type="button" class="player-control-btn player-control-btn--play" id="playBtn"' . $playClick . ' aria-label="Play" title="Play" data-playing="false">';
    $html .= '<span class="player-control-icon player-control-icon--play">' . bandpromo_player_icon_svg('play') . '</span>';
    $html .= '<span class="player-control-icon player-control-icon--pause" hidden>' . bandpromo_player_icon_svg('pause') . '</span>';
    $html .= '</button>';
    $html .= '<button type="button" class="player-control-btn" id="nextBtn"' . $nextClick . ' aria-label="Next track" title="Next">';
    $html .= bandpromo_player_icon_svg('next');
    $html .= '</button>';
    $html .= '<button type="button" class="player-control-btn" id="castBtn" disabled aria-label="Cast (coming later)" title="Cast support comes in a later update">';
    $html .= bandpromo_player_icon_svg('cast');
    $html .= '</button>';
    $html .= '</div>';

    $html .= '<audio id="audioPlayer" preload="none"></audio>';
    $html .= '<div class="audio-scrubber" id="audioScrubber">';
    $html .= '<span id="audioTimeCurrent" class="audio-scrubber-time">' . $esc($timeCurrent) . '</span>';
    $seekDisabled = $preview ? ' disabled' : ' disabled';
    $seekMax = $preview ? '200' : '0';
    $seekValue = $preview ? '42' : '0';
    $html .= '<input type="range" id="audioSeek" class="audio-scrubber-range" min="0" max="' . $seekMax . '" step="0.1" value="' . $seekValue . '" aria-label="Seek"' . $seekDisabled . '>';
    $html .= '<span id="audioTimeDuration" class="audio-scrubber-time">' . $esc($timeDuration) . '</span>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div id="beggars-banquet">';
    if ($beggarsBanquet && $supportEnabled && $supportUrl !== '') {
        $html .= '<a href="' . $esc($supportUrl) . '" target="_blank" rel="noopener noreferrer" class="support-link-button" style="--support-button-background: '
            . $esc($supportBg) . '; --support-button-text: ' . $esc($supportFg) . ';">'
            . $esc($supportLabel) . '</a>';
    } elseif ($preview && $beggarsBanquet) {
        // Demo support chip so Beggars banquet On is visible without live support config.
        $html .= '<a href="#" class="support-link-button" onclick="return false;" style="--support-button-background: '
            . $esc($supportBg) . '; --support-button-text: ' . $esc($supportFg) . ';">'
            . $esc($supportLabel) . '</a>';
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}
