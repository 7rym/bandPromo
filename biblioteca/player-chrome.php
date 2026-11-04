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
require_once __DIR__ . '/player-markdown.php';

/**
 * @param array<string,mixed> $options
 */
function bandpromo_player_chrome_render(array $options = []): string
{
    $preview = !empty($options['preview']);
    $signedIn = !empty($options['signed_in']);
    $showAdmin = !empty($options['show_admin']);
    $showDebug = !empty($options['show_debug']);
    $beggarsBanquet = array_key_exists('beggars_banquet', $options)
        ? !empty($options['beggars_banquet'])
        : true;
    $supportEnabled = !empty($options['support_enabled']);
    $supportUrl = trim((string) ($options['support_url'] ?? ''));
    $supportLabel = trim((string) ($options['support_label'] ?? 'Support'));
    if ($supportLabel === '') {
        $supportLabel = 'Support';
    }
    $supportLabelHtml = bandpromo_player_markdown_render_support_label($supportLabel);
    $supportBg = trim((string) ($options['support_button_background'] ?? '#323842'));
    $supportFg = trim((string) ($options['support_button_text'] ?? '#ffffff'));
    if ($supportBg === '') {
        $supportBg = '#323842';
    }
    if ($supportFg === '') {
        $supportFg = '#ffffff';
    }

    $username = trim((string) ($options['username'] ?? ''));
    $siteTitle = trim((string) ($options['site_title'] ?? 'bandPromo'));
    if ($siteTitle === '') {
        $siteTitle = 'bandPromo';
    }

    $loginForm = strtolower(trim((string) ($options['login_form'] ?? 'full')));
    if ($loginForm === 'minimalistic') {
        $loginForm = 'minimal';
    }
    if (!in_array($loginForm, ['none', 'minimal', 'full'], true)) {
        $loginForm = 'full';
    }

    $demo = is_array($options['demo'] ?? null) ? $options['demo'] : [];
    $artist = trim((string) ($demo['artist'] ?? ($preview ? 'Artist name' : 'Artist')));
    $title = trim((string) ($demo['title'] ?? ($preview ? 'Track title' : 'Title')));
    $version = trim((string) ($demo['version'] ?? ($preview ? 'Version' : '')));
    $coverUrl = trim((string) ($demo['cover_url'] ?? ''));
    $prevCoverUrl = trim((string) ($demo['prev_cover_url'] ?? $coverUrl));
    $nextCoverUrl = trim((string) ($demo['next_cover_url'] ?? $coverUrl));
    $livingCoverUrl = trim((string) ($demo['living_cover_url'] ?? ''));
    $timeCurrent = trim((string) ($demo['time_current'] ?? ($preview ? '0:42' : '0:00')));
    $timeDuration = trim((string) ($demo['time_duration'] ?? ($preview ? '3:24' : '0:00')));

    $userMode = $signedIn ? 'signed-in' : 'guest';

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

    // Preview: no live transport handlers. Keep .scene interactive so Peeks
    // hover (Fill) matches /play; transport/Support stay inert.
    $prevClick = $preview ? '' : ' onclick="prevSong()"';
    $nextClick = $preview ? '' : ' onclick="nextSong()"';
    $playClick = $preview ? '' : ' onclick="togglePlay()"';
    $chromeInert = $preview ? ' inert' : '';
    $versionHidden = $version === '' ? ' hidden' : '';

    $html = '';
    $html .= '<div id="mediaplayer">';
    // User bar: tools + signed-in name only. Guest invite lives above the login form.
    $hasUserTools = $showAdmin || $showDebug || $signedIn;
    if ($hasUserTools) {
        $html .= '<div id="player-user-area" data-login-form="' . $esc($loginForm) . '">';
        $html .= '<div class="player-user-status" data-player-user-mode="' . $esc($userMode) . '">';
        $html .= '<div class="player-user-tools">';
        if ($showAdmin) {
            $html .= '<a class="player-user-tool" id="admin-btn" href="/admin.php" title="Admin panel" aria-label="Open admin panel">⚙️</a>';
        }
        if ($showDebug) {
            $html .= '<button type="button" class="player-user-tool" id="debug-panel-btn" title="Developer debug panel" aria-label="Open developer debug panel">🐛</button>';
        }
        if ($signedIn) {
            if ($preview) {
                $html .= '<span class="player-user-logout" title="Log out" aria-disabled="true">'
                    . bandpromo_player_icon_svg('logout') . '</span>';
            } else {
                $html .= '<a class="player-user-logout" href="/?logout=1" title="Log out" aria-label="Log out">'
                    . bandpromo_player_icon_svg('logout') . '</a>';
            }
        }
        $html .= '</div>';
        if ($signedIn) {
            $displayName = $username !== '' ? $username : 'Signed in';
            $html .= '<p class="player-user-status-text">';
            $html .= '<span class="player-user-status-name">' . $esc($displayName) . '</span>';
            $html .= '</p>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    if (!$preview) {
        $html .= '<div id="operatorDeliveryNotice" class="operator-delivery-notice" hidden>';
        $html .= '<strong>Publish build required.</strong>';
        $html .= '<span id="operatorDeliveryNoticeText">Some tracks are waiting for streaming MP3 delivery. Open System → Status → Site health.</span>';
        $html .= '<a href="/admin.php?tab=system&amp;stab=deliverables#siteHealthCard">Open Site health</a>';
        $html .= '</div>';
    }

    // Do not mark .scene inert in preview — that blocks :hover on peeks.
    $html .= '<div class="scene">';
    $html .= '<div class="side-card prev"' . $prevClick . '>';
    $html .= '<img' . $prevImgAttrs . ' id="prevCover" alt="Previous">';
    $html .= '</div>';
    $livingPreview = $preview && $livingCoverUrl !== '';
    $html .= '<div class="card-wrapper' . ($livingPreview ? ' has-living-cover' : '') . '" id="cardWrapper">';
    $html .= '<img' . $coverImgAttrs . ' alt="Artwork" class="cover-art'
        . ($livingPreview ? ' cover-art--suppressed' : '') . '" id="coverImage">';
    if ($livingPreview) {
        $html .= '<video class="cover-art cover-art-video cover-art-video--active" id="coverVideo" src="'
            . $esc($livingCoverUrl) . '" loop muted playsinline autoplay preload="auto"></video>';
    } else {
        $html .= '<video class="cover-art cover-art-video" id="coverVideo" loop muted playsinline preload="metadata" hidden></video>';
    }
    $html .= '<div class="reflection">';
    $html .= '<img' . $coverImgAttrs . ' alt="" id="reflectionImage">';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="side-card next"' . $nextClick . '>';
    $html .= '<img' . $nextImgAttrs . ' id="nextCover" alt="Next">';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="player-transport"' . $chromeInert . '>';
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

    // Guest invite + optional login form above the Support banner (auth not wired yet).
    if (!$signedIn) {
        $minimal = $loginForm === 'minimal';
        $html .= '<div class="player-user-login-wrap" data-login-form-mode="' . $esc($loginForm) . '">';
        $html .= '<p class="player-user-login-invite">';
        $html .= 'Please register for free or login to get the full experience of ';
        $html .= '<span class="player-user-status-site">' . $esc($siteTitle) . '</span>';
        $html .= '</p>';
        if ($loginForm !== 'none') {
            if ($minimal) {
                $html .= '<button type="button" class="player-user-login-open">Login</button>';
            }
            $formHidden = $minimal ? ' hidden' : '';
            $html .= '<form class="player-user-login-form" action="#" method="post" autocomplete="off"' . $formHidden . '>';
            $html .= '<input type="text" name="username" autocomplete="username" placeholder="Username" aria-label="Username" disabled>';
            $html .= '<input type="password" name="password" autocomplete="current-password" placeholder="Password" aria-label="Password" disabled>';
            $html .= '<button type="button" class="player-user-login-submit" disabled>Login</button>';
            $html .= '</form>';
        }
        $html .= '</div>';
    }

    // Support banner (storage key / DOM id still beggars_banquet for PCF / CSS).
    $html .= '<div id="beggars-banquet" class="player-support-banner"' . $chromeInert . '>';
    if ($beggarsBanquet && $supportEnabled && $supportUrl !== '') {
        $html .= '<a href="' . $esc($supportUrl) . '" target="_blank" rel="noopener noreferrer" class="support-link-button" style="--support-button-background: '
            . $esc($supportBg) . '; --support-button-text: ' . $esc($supportFg) . ';">'
            . $supportLabelHtml . '</a>';
    } elseif ($preview && $beggarsBanquet) {
        // Demo chip so Support banner On is visible without live support config.
        $html .= '<a href="#" class="support-link-button" onclick="return false;" style="--support-button-background: '
            . $esc($supportBg) . '; --support-button-text: ' . $esc($supportFg) . ';">'
            . $supportLabelHtml . '</a>';
    }
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}
