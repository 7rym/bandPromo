<?php
require_once __DIR__ . '/../biblioteca/https.php';
bandpromo_enforce_https();

// Check if user is authenticated
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /');
    exit;
}

// Load playlist
require_once __DIR__ . '/../biblioteca/playlist-storage.php';
require_once __DIR__ . '/../biblioteca/auth.php';

$playerRoot = dirname(__DIR__);
bandpromo_playlist_ensure_seeded($playerRoot);

$currentUserRole = current_user_role();
$operatorBypass = in_array($currentUserRole, ['admin', 'developer'], true);

$requestedSegment = trim((string) ($_GET['playlist'] ?? ''));
$resolvedPlaylistId = $requestedSegment !== ''
    ? bandpromo_playlist_resolve_route_id($playerRoot, $requestedSegment)
    : '';
$activePlaylistId = $resolvedPlaylistId !== ''
    ? $resolvedPlaylistId
    : bandpromo_playlist_default_active_id($playerRoot);
if (!bandpromo_playlist_is_player_visible($playerRoot, $activePlaylistId, $operatorBypass)) {
    $activePlaylistId = bandpromo_playlist_default_active_id($playerRoot);
}
if (!bandpromo_playlist_is_player_visible($playerRoot, $activePlaylistId, $operatorBypass)) {
    header('Location: /');
    exit;
}
try {
    bandpromo_playlist_load_document($playerRoot, $activePlaylistId);
} catch (Throwable $throwable) {
    $activePlaylistId = bandpromo_playlist_default_active_id($playerRoot);
}

// Redirect to root if setup hasn't been completed (no playlist registry yet)
if (bandpromo_playlist_registry_entries($playerRoot) === []) {
    header('Location: /');
    exit;
}

// Load site config
$siteCfgFile = dirname(__DIR__) . '/web-config.json';
if (!file_exists($siteCfgFile)) {
    http_response_code(500);
    echo 'Missing runtime file: web-config.json. Run setup.';
    exit;
}
$siteCfgRaw = file_get_contents($siteCfgFile);
$siteCfg = json_decode($siteCfgRaw ?: '{}', true);
if (!is_array($siteCfg)) {
    http_response_code(500);
    echo 'Invalid runtime file: web-config.json';
    exit;
}

require_once '../biblioteca/config-loader.php';

$deepLinkReleaseSlug = strtolower(trim((string) ($_GET['release'] ?? '')));
$deepLinkTrackSlug = strtolower(trim((string) ($_GET['track'] ?? '')));
$playlistCatalog = bandpromo_playlist_player_catalog_entries($playerRoot, $operatorBypass);
$activePlaylistSlug = bandpromo_playlist_public_slug($playerRoot, $activePlaylistId);

function bandpromo_support_parse_kofi_page_id(string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $trimmed)) {
        $path = (string) parse_url($trimmed, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static function ($segment) {
            return $segment !== '';
        }));
        return isset($segments[0]) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $segments[0]) : '';
    }

    return preg_replace('/[^a-zA-Z0-9_-]/', '', $trimmed);
}

function bandpromo_player_support_hex(string $value, string $fallback): string {
    $value = strtolower(trim($value));
    if (preg_match('/^#[0-9a-f]{6}$/', $value) === 1) {
        return $value;
    }
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $matches) === 1) {
        return '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
    }

    return $fallback;
}

function bandpromo_player_support_luminance(string $hex): float {
    $channels = [
        hexdec(substr($hex, 1, 2)) / 255,
        hexdec(substr($hex, 3, 2)) / 255,
        hexdec(substr($hex, 5, 2)) / 255,
    ];
    foreach ($channels as &$channel) {
        $channel = $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
    unset($channel);

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function bandpromo_player_support_contrast(string $first, string $second): float {
    $a = bandpromo_player_support_luminance($first);
    $b = bandpromo_player_support_luminance($second);

    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

$origin = bandpromo_current_origin();
$preferredAudioVariant = bandpromo_preferred_audio_variant($_SESSION['quality'] ?? null);

$playlistTracks = [];
try {
    $playlistDocument = bandpromo_playlist_load_document($playerRoot, $activePlaylistId);
    $playlistTracks = is_array($playlistDocument['tracks'] ?? null)
        ? bandpromo_playlist_normalize_stored_tracks($playlistDocument['tracks'])
        : [];
} catch (Throwable $throwable) {
    $playlistTracks = [];
}

// Resolve deep-linked track for page title and per-release branding.
$song = null;
if ($deepLinkTrackSlug !== '') {
    $trackIndex = bandpromo_playlist_resolve_player_track_index(
        $playlistTracks,
        $deepLinkReleaseSlug,
        $deepLinkTrackSlug
    );
    if ($trackIndex >= 0 && isset($playlistTracks[$trackIndex])) {
        $song = $playlistTracks[$trackIndex];
    }
} elseif (isset($_GET['t'])) {
    $track = max(1, (int) $_GET['t']);
    $index = $track - 1;
    if ($index >= count($playlistTracks)) {
        $index = 0;
    }
    if (isset($playlistTracks[$index])) {
        $song = $playlistTracks[$index];
    }
}

$siteTitle = trim((string) get_config('release.identity.title', 'bandPromo'));
if ($siteTitle === '') {
    $siteTitle = 'bandPromo';
}
$pageTitle = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
if (is_array($song)) {
    $trackTitle = trim(preg_replace('/\s+/', ' ', (string) ($song['title'] ?? '')));
    if ($trackTitle !== '') {
        $pageTitle = htmlspecialchars($trackTitle, ENT_QUOTES, 'UTF-8')
            . ' — '
            . htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');
    }
}

$appVersion = 'dev';
$versionFile = dirname(__DIR__) . '/VERSION';
if (file_exists($versionFile)) {
    $rawVersion = trim((string) file_get_contents($versionFile));
    if ($rawVersion !== '') {
        $appVersion = $rawVersion;
    }
}

$supportEnabled = (bool) get_config('support.enabled', false);
$supportLabel = trim((string) get_config('support.label', 'Support'));
if ($supportLabel === '') {
    $supportLabel = 'Support';
}
$supportLabel = function_exists('mb_substr')
    ? mb_substr($supportLabel, 0, 80, 'UTF-8')
    : substr($supportLabel, 0, 80);
$supportUrl = trim((string) get_config('support.url', ''));
$supportKofiPageId = bandpromo_support_parse_kofi_page_id((string) get_config('support.kofi_page_id', ''));
$supportButtonBackgroundColor = bandpromo_player_support_hex(
    (string) get_config('support.button_background_color', '#323842'),
    '#323842'
);
$supportButtonTextColor = bandpromo_player_support_hex(
    (string) get_config('support.button_text_color', '#ffffff'),
    '#ffffff'
);
if (bandpromo_player_support_contrast($supportButtonBackgroundColor, $supportButtonTextColor) < 4.5) {
    $blackContrast = bandpromo_player_support_contrast($supportButtonBackgroundColor, '#000000');
    $whiteContrast = bandpromo_player_support_contrast($supportButtonBackgroundColor, '#ffffff');
    $supportButtonTextColor = $blackContrast >= $whiteContrast ? '#000000' : '#ffffff';
}

$currentUsername = trim((string) ($_SESSION['username'] ?? ''));
$currentUserRole = current_user_role();
$showAdminButton = can_access_admin_panel();
$showDebugTools = is_developer();
$showOperatorNotice = in_array($currentUserRole, ['admin', 'developer'], true);
$debugInfo = [
    'username' => $currentUsername,
    'role' => $currentUserRole,
    'version' => $appVersion,
    'sessionQuality' => (string) ($_SESSION['quality'] ?? ''),
    'preferredAudioVariant' => $preferredAudioVariant,
    'origin' => $origin,
    'path' => $_SERVER['REQUEST_URI'] ?? '/play/',
];

if ($supportUrl === '' && $supportKofiPageId !== '') {
    $supportUrl = 'https://ko-fi.com/' . rawurlencode($supportKofiPageId);
}
if ($supportUrl !== '') {
    $supportScheme = strtolower((string) parse_url($supportUrl, PHP_URL_SCHEME));
    if (!filter_var($supportUrl, FILTER_VALIDATE_URL) || !in_array($supportScheme, ['http', 'https'], true)) {
        $supportUrl = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo $pageTitle; ?></title>

    <!-- Favicons -->
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon.svg">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo $pageTitle; ?>">

    <!-- Manifest & Theme -->
    <link rel="manifest" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/site.webmanifest?v=<?php echo rawurlencode($appVersion); ?>">
    <?php
    require_once __DIR__ . '/../biblioteca/brand-storage.php';
    $installActiveBrandId = bandpromo_brand_active_id($playerRoot);
    $playerBrandId = bandpromo_playlist_effective_brand_id($playerRoot, $activePlaylistId);
    $themeColorMeta = '#121212';
    try {
        $brandDocForMeta = bandpromo_brand_load_document($playerRoot, $playerBrandId);
        $bgToken = trim((string) bandpromo_theme_token_value($brandDocForMeta, 'color.background'));
        if ($bgToken !== '') {
            $themeColorMeta = $bgToken;
        }
    } catch (Throwable $throwable) {
        // Keep default theme-color.
    }
    ?>
    <meta name="theme-color" content="<?php echo htmlspecialchars($themeColorMeta, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="/biblioteca/style.css?v=<?php echo rawurlencode($appVersion); ?>">
    <link rel="stylesheet" href="/biblioteca/page-content.css?v=<?php echo rawurlencode($appVersion); ?>">
    <?php
    echo bandpromo_brand_render_css_for_id($playerRoot, $playerBrandId);
    ?>
    <script>
        window.BANDPROMO_ACTIVE_BRAND_ID = <?php echo json_encode($installActiveBrandId); ?>;
        window.BANDPROMO_PLAYLIST_BRAND_ID = <?php echo json_encode($playerBrandId); ?>;
    </script>
</head>
<body>
    <?php
    require_once '../biblioteca/config-loader.php';
    require_once '../biblioteca/player-modules.php';
    $installLogo = (string) get_config('install.brand.logo', '');
    $installBackgroundVideo = get_config_nonempty('release.theme.background_video', null);
    $installBackgroundImage = get_config_nonempty('release.theme.background_image', null);
    $installShellMedia = [
        'logo' => $installLogo,
        'background_image' => is_string($installBackgroundImage) ? $installBackgroundImage : '',
        'background_video' => is_string($installBackgroundVideo) ? $installBackgroundVideo : '',
    ];
    try {
        $installBrandDoc = bandpromo_brand_load_document($playerRoot, $installActiveBrandId);
        $installResolved = bandpromo_brand_player_shell_assets($playerRoot, $installBrandDoc);
        foreach (['logo', 'background_image', 'background_video'] as $shellSlot) {
            if ($installShellMedia[$shellSlot] === '' && ($installResolved[$shellSlot] ?? '') !== '') {
                $installShellMedia[$shellSlot] = $installResolved[$shellSlot];
            }
        }
    } catch (Throwable $throwable) {
        // Keep config baselines.
    }

    $playerShellMedia = $installShellMedia;
    $playerBrandTitle = 'Band Logo';
    try {
        $playerBrandDoc = bandpromo_brand_load_document($playerRoot, $playerBrandId);
        $playerBrandTitle = trim((string) ($playerBrandDoc['title'] ?? '')) !== ''
            ? (string) $playerBrandDoc['title']
            : $playerBrandTitle;
        $resolvedShell = bandpromo_brand_player_shell_assets($playerRoot, $playerBrandDoc);
        foreach (['logo', 'background_image', 'background_video'] as $shellSlot) {
            if (($resolvedShell[$shellSlot] ?? '') !== '') {
                $playerShellMedia[$shellSlot] = $resolvedShell[$shellSlot];
            }
        }
    } catch (Throwable $throwable) {
        // Keep install/Active shell baseline.
    }

    $backgroundVideo = $playerShellMedia['background_video'] !== '' ? $playerShellMedia['background_video'] : null;
    $backgroundImage = $playerShellMedia['background_image'] !== '' ? $playerShellMedia['background_image'] : null;
    $playerLogo = $playerShellMedia['logo'] !== '' ? $playerShellMedia['logo'] : $installShellMedia['logo'];
    $playlistSelectorMode = bandpromo_player_playlist_selector_mode();
    ?>
    <script>
        window.BANDPROMO_INSTALL_SHELL_MEDIA = <?php echo json_encode($installShellMedia, JSON_UNESCAPED_SLASHES); ?>;
        window.appConfig = window.appConfig || {};
        window.appConfig.media = {
            background_video: <?php echo json_encode($backgroundVideo); ?>,
            background_image: <?php echo json_encode($backgroundImage); ?>,
            logo: <?php echo json_encode($playerLogo); ?>
        };
        window.appConfig.player = Object.assign({}, window.appConfig.player || {}, {
            playlist_selector: <?php echo json_encode($playlistSelectorMode); ?>
        });
    </script>
    <video id="bg-video" preload="none" muted loop playsinline style="display:none"<?php
        if ($backgroundVideo) {
            echo ' data-src="' . htmlspecialchars($backgroundVideo, ENT_QUOTES, 'UTF-8') . '"';
        }
    ?>></video>
    <?php if ($showAdminButton): ?>
    <a id="admin-btn" href="/admin.php" title="Admin panel" aria-label="Open admin panel">⚙️</a>
    <?php endif; ?>
    <?php if ($showDebugTools): ?>
    <button type="button" id="debug-panel-btn" title="Developer debug panel" aria-label="Open developer debug panel">🐛</button>
    <?php endif; ?>

    <div id="loading-msg">
        <h2 id="loading-msg-title">Music isn't ready yet</h2>
        <p id="loading-msg-detail">This playlist can't be played right now. Please try again later.</p>
        <p id="loading-msg-operator" class="loading-msg-operator" hidden></p>
    </div>

    <div id="mediaplayer">
        <div id="operatorDeliveryNotice" class="operator-delivery-notice" hidden>
            <strong>Publish build required.</strong>
            <span id="operatorDeliveryNoticeText">Some tracks are waiting for streaming MP3 delivery. Open System → Deliverables.</span>
            <a href="/admin.php?tab=system&amp;stab=deliverables">Open Deliverables</a>
        </div>

        <div class="scene">
            <div class="side-card prev" onclick="prevSong()">
                <img src="" id="prevCover" alt="Previous">
            </div>
            <div class="card-wrapper" id="cardWrapper">
                <img src="" alt="Album Cover" class="cover-art" id="coverImage">
                <video class="cover-art cover-art-video" id="coverVideo" loop muted playsinline preload="metadata" hidden></video>
                <div class="reflection">
                    <img src="" alt="" id="reflectionImage">
                </div>
            </div>
            <div class="side-card next" onclick="nextSong()">
                <img src="" id="nextCover" alt="Next">
            </div>
        </div>

        <div class="track-info">
            <canvas id="analyzer"></canvas>
            <h3 id="artistName">Artist</h3>
            <h2 id="songTitle">Title</h2>
        </div>

        <div class="controls">
            <button onclick="prevSong()">&#9664; Previous</button>
            <button onclick="togglePlay()" id="playBtn">Play</button>
            <button onclick="nextSong()">Next &#9654;</button>
        </div>

        <audio id="audioPlayer" preload="none"></audio>
        <div class="audio-scrubber" id="audioScrubber">
            <span id="audioTimeCurrent" class="audio-scrubber-time">0:00</span>
            <input type="range" id="audioSeek" class="audio-scrubber-range" min="0" max="0" step="0.1" value="0" aria-label="Seek" disabled>
            <span id="audioTimeDuration" class="audio-scrubber-time">0:00</span>
        </div>

        <div id="beggars-banquet">
            <?php if ($supportEnabled && $supportUrl !== ''): ?>
            <a
                href="<?php echo htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8'); ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="support-link-button"
                style="--support-button-background: <?php echo htmlspecialchars($supportButtonBackgroundColor, ENT_QUOTES, 'UTF-8'); ?>; --support-button-text: <?php echo htmlspecialchars($supportButtonTextColor, ENT_QUOTES, 'UTF-8'); ?>;"
            >
                <?php echo htmlspecialchars($supportLabel, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div id="content-container">
        <div class="content-logo">
            <img src="<?php echo htmlspecialchars($playerLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($playerBrandTitle, ENT_QUOTES, 'UTF-8'); ?>" class="content-logo-img">
        </div>
        <div class="content-toggle">
            <?php
            require_once dirname(__DIR__) . '/biblioteca/player-modules.php';
            $playerRoot = dirname(__DIR__);
            $playerTabs = bandpromo_player_content_tabs($playerRoot, $operatorBypass);
            $defaultPlayerView = bandpromo_player_default_view();
            $hasDefaultView = false;
            foreach ($playerTabs as $playerTab) {
                if (($playerTab['view'] ?? '') === $defaultPlayerView) {
                    $hasDefaultView = true;
                    break;
                }
            }
            if (!$hasDefaultView && $playerTabs !== []) {
                $defaultPlayerView = (string) ($playerTabs[0]['view'] ?? 'playlist');
            }
            foreach ($playerTabs as $playerTab):
                $view = (string) ($playerTab['view'] ?? '');
                $label = (string) ($playerTab['label'] ?? $view);
                $isActive = $view === $defaultPlayerView ? ' active' : '';
            ?>
            <button type="button"<?php echo $isActive ? ' class="active"' : ''; ?> data-view="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>" onclick="toggleView('<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>')"><?php echo htmlspecialchars($label); ?></button>
            <?php endforeach; ?>
        </div>
        <?php
        foreach ($playerTabs as $playerTab):
            $view = (string) ($playerTab['view'] ?? '');
            $isActive = $view === $defaultPlayerView ? ' active' : '';
            if ($view === 'lyrics'):
        ?>
        <div class="lyrics-box<?php echo $isActive; ?>" id="lyricsBox" data-content-box="lyrics">Loading lyrics...</div>
        <?php elseif ($view === 'playlist'): ?>
        <?php if (count($playlistCatalog) > 1): ?>
        <?php
            $playlistSelectorMode = bandpromo_player_playlist_selector_mode();
            $playlistSelectorLabel = bandpromo_player_playlist_tab_label($playerRoot, $operatorBypass);
        ?>
        <div class="playlist-selector playlist-selector--<?php echo htmlspecialchars($playlistSelectorMode, ENT_QUOTES, 'UTF-8'); ?>" id="playlistSelectorWrap" data-mode="<?php echo htmlspecialchars($playlistSelectorMode, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isActive === '' ? ' hidden' : ''; ?>>
            <?php if ($playlistSelectorMode === 'buttons'): ?>
            <div class="playlist-selector-buttons" role="group" aria-label="<?php echo htmlspecialchars($playlistSelectorLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($playlistCatalog as $playlistEntry):
                    $entryId = (string) ($playlistEntry['id'] ?? '');
                    $entryTitle = (string) ($playlistEntry['title'] ?? $entryId);
                    $entryIsActive = $entryId === $activePlaylistId;
                ?>
                <button
                    type="button"
                    class="playlist-selector-btn<?php echo $entryIsActive ? ' is-active' : ''; ?>"
                    data-playlist-select
                    data-playlist-id="<?php echo htmlspecialchars($entryId, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-pressed="<?php echo $entryIsActive ? 'true' : 'false'; ?>"
                ><?php echo htmlspecialchars($entryTitle, ENT_QUOTES, 'UTF-8'); ?></button>
                <?php endforeach; ?>
            </div>
            <?php elseif ($playlistSelectorMode === 'coverflow'): ?>
            <div class="playlist-coverflow" role="listbox" aria-label="<?php echo htmlspecialchars($playlistSelectorLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($playlistCatalog as $playlistEntry):
                    $entryId = (string) ($playlistEntry['id'] ?? '');
                    $entryTitle = (string) ($playlistEntry['title'] ?? $entryId);
                    $entryCover = trim((string) ($playlistEntry['cover'] ?? ''));
                    $entryIsActive = $entryId === $activePlaylistId;
                    $initial = function_exists('mb_substr')
                        ? mb_strtoupper(mb_substr($entryTitle, 0, 1, 'UTF-8'), 'UTF-8')
                        : strtoupper(substr($entryTitle, 0, 1));
                ?>
                <button
                    type="button"
                    class="playlist-coverflow-item<?php echo $entryIsActive ? ' is-active' : ''; ?>"
                    role="option"
                    aria-selected="<?php echo $entryIsActive ? 'true' : 'false'; ?>"
                    data-playlist-select
                    data-playlist-id="<?php echo htmlspecialchars($entryId, ENT_QUOTES, 'UTF-8'); ?>"
                    title="<?php echo htmlspecialchars($entryTitle, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars($entryTitle, ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <?php if ($entryCover !== ''): ?>
                    <img
                        class="playlist-coverflow-thumb"
                        src="<?php echo htmlspecialchars($entryCover, ENT_QUOTES, 'UTF-8'); ?>"
                        alt=""
                        width="<?php echo $entryIsActive ? '100' : '70'; ?>"
                        height="<?php echo $entryIsActive ? '100' : '70'; ?>"
                        loading="lazy"
                        decoding="async"
                    >
                    <?php else: ?>
                    <span class="playlist-coverflow-thumb playlist-coverflow-placeholder" aria-hidden="true"><?php echo htmlspecialchars($initial !== '' ? $initial : '♪', ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <select id="playlistSelector" aria-label="<?php echo htmlspecialchars($playlistSelectorLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($playlistCatalog as $playlistEntry): ?>
                <option value="<?php echo htmlspecialchars((string) ($playlistEntry['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"<?php echo (($playlistEntry['id'] ?? '') === $activePlaylistId) ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars((string) ($playlistEntry['title'] ?? $playlistEntry['id'] ?? 'Playlist'), ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="playlist-box<?php echo $isActive; ?>" id="playlistBox" data-content-box="playlist">Loading playlist...</div>
        <?php elseif (str_starts_with($view, 'page-')):
            $pageId = (string) ($playerTab['page_id'] ?? substr($view, 5));
        ?>
        <div class="page-box<?php echo $isActive; ?>" id="pageBox-<?php echo htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8'); ?>" data-content-box="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>" data-page-id="<?php echo htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8'); ?>" data-page-hydrated="false">
            <p class="page-paragraph page-box-loading">Loading…</p>
        </div>
        <?php endif; endforeach; ?>
    </div>

    <div class="lightbox" id="lightbox">
        <div class="lightbox-content">
            <button class="lightbox-nav lightbox-prev" id="lightboxPrev" onclick="prevLightbox(event)">&#8249;</button>
            <img src="" alt="Album Cover Fullscreen" class="lightbox-image" id="lightboxImage">
            <video class="lightbox-video" id="lightboxVideo" controls preload="none" style="display:none;max-width:100%;max-height:80vh;"></video>
            <button class="lightbox-nav lightbox-next" id="lightboxNext" onclick="nextLightbox(event)">&#8250;</button>
            <div class="lightbox-close" onclick="closeLightbox()">&times;</div>
        </div>
    </div>

    <?php if ($showDebugTools): ?>
    <div class="debug-modal" id="debugModal" aria-hidden="true">
        <div class="debug-modal-panel" id="debugModalContent" role="dialog" aria-modal="true" aria-labelledby="debugModalTitle">
            <div class="debug-modal-header">
                <div>
                    <h2 id="debugModalTitle">Developer Debug</h2>
                    <p class="debug-modal-subtitle">Live client and session diagnostics for external mobile and PWA testing.</p>
                </div>
                <button type="button" class="debug-modal-close" id="debugModalClose" aria-label="Close debug panel">&times;</button>
            </div>
            <div class="debug-modal-actions">
                <button type="button" id="debugLogoutBtn">Logout</button>
                <button type="button" id="debugClearCacheBtn">Clear App Cache</button>
            </div>
            <div class="debug-modal-note">Data usage is approximate browser-side transfer for this session, not authoritative billing-grade usage.</div>
            <div class="debug-data-grid" id="debugDataBody">
                <div class="debug-data-empty">Loading debug data...</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tell player.js where media lives in the new structure -->
    <script>
        window.BANDPROMO_PLAYER_TABS = <?php echo json_encode($playerTabs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.BANDPROMO_DEFAULT_PLAYER_VIEW = <?php echo json_encode($defaultPlayerView, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.BANDPROMO_PLAYLIST_ID = <?php echo json_encode($activePlaylistId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.BANDPROMO_LYRICS_LABEL = <?php
            $lyricsModuleLabel = 'Lyrics';
            try {
                $playerModules = bandpromo_player_modules_config();
                $lyricsModuleLabel = trim((string) ($playerModules['lyrics']['label'] ?? 'Lyrics'));
                if ($lyricsModuleLabel === '') {
                    $lyricsModuleLabel = 'Lyrics';
                }
            } catch (Throwable $throwable) {
                $lyricsModuleLabel = 'Lyrics';
            }
            echo json_encode($lyricsModuleLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;
        window.BANDPROMO_PLAYLIST_SLUG = <?php echo json_encode($activePlaylistSlug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.BANDPROMO_PLAYLIST_CATALOG = <?php echo json_encode($playlistCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.BANDPROMO_DEEP_LINK = <?php echo json_encode([
            'release' => $deepLinkReleaseSlug,
            'track' => $deepLinkTrackSlug,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.CONFIG_URL       = '/biblioteca/get-player-playlist.php?playlist=' + encodeURIComponent(window.BANDPROMO_PLAYLIST_ID || 'bandpromo-demo');
        window.MEDIA_AUDIO_BASE = '/media/audio';
        window.BANDPROMO_PREFERRED_AUDIO_VARIANT = <?php echo json_encode($preferredAudioVariant); ?>;
        window.BANDPROMO_IS_OPERATOR = <?php echo json_encode($showOperatorNotice); ?>;
        window.BANDPROMO_LOCAL_DEV = <?php echo json_encode(bandpromo_is_local_dev_host()); ?>;
        window.BANDPROMO_DEBUG_INFO = <?php echo json_encode($debugInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        <?php
        require_once '../biblioteca/csrf.php';
        $csrf_token = generate_csrf_token();
        ?>
        window.csrfToken = <?php echo json_encode($csrf_token); ?>;
        sessionStorage.setItem('csrf_token', window.csrfToken);
    </script>
    <script>
        window.BANDPROMO_SESSION_AUTH = {
            enabled: true,
            loginUrl: '/',
            pingUrl: '/biblioteca/session-check.php',
            pingIntervalMs: 300000,
        };
    </script>
    <script src="/biblioteca/session-auth.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <script src="/biblioteca/shell-background.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <script src="/biblioteca/lightbox.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <script src="/biblioteca/player-markdown.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <script src="/biblioteca/player.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js?v=<?php echo rawurlencode($appVersion); ?>', {
                updateViaCache: 'none'
            }).catch(() => {});
        }
    </script>
</body>
</html>
