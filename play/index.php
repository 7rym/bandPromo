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
$configFile = __DIR__ . '/playlist.json';

// Redirect to root if setup hasn't been completed (no playlist yet)
if (!file_exists($configFile)) {
    header('Location: /');
    exit;
}
$playlistConfig = [];

// Load site config for OG defaults
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
require_once '../biblioteca/gallery-helpers.php';

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

function bandpromo_preferred_audio_variant(?string $quality): string {
    return strtolower(trim((string) $quality)) === 'high' ? 'original' : 'optimal';
}

$origin = bandpromo_current_origin();
$baseUrl = $origin . '/play/';

// Default meta tags
$ogTitle       = get_config('release.identity.title', 'Twisted Chronicles');
$ogDescription = get_config('release.identity.description', 'A private music experience');
$ogImage       = $origin . get_config('release.brand.poster', '/media/special/bandPromo_share.png');
$ogImageWidth  = get_config('release.brand.poster_width', 1200);
$ogImageHeight = get_config('release.brand.poster_height', 630);
$ogUrl         = $baseUrl;

$json = file_get_contents($configFile);
$playlistConfig = json_decode($json, true) ?: [];

$galleryItems = [];
$galleryItems = bandpromo_load_gallery_items(dirname(__DIR__));

$appVersion = 'dev';
$versionFile = dirname(__DIR__) . '/VERSION';
if (file_exists($versionFile)) {
    $rawVersion = trim((string) file_get_contents($versionFile));
    if ($rawVersion !== '') {
        $appVersion = $rawVersion;
    }
}

if (isset($_GET['t'])) {
    $track = intval($_GET['t']);
    $index = max(0, $track - 1);
    if ($index >= count($playlistConfig)) $index = 0;

    $song = $playlistConfig[$index];

    $ogTitle = htmlspecialchars(preg_replace('/\s+/', ' ', $song['title']), ENT_QUOTES, 'UTF-8');
    $ogDescription = htmlspecialchars($song['artist'], ENT_QUOTES, 'UTF-8');

    if (!empty($song['cover'])) {
        $coverFilename = basename(str_replace('\\', '/', $song['cover']));
        $ogImage       = $origin . '/media/img/original/' . rawurlencode($coverFilename);
        $ogImageWidth  = 600;
        $ogImageHeight = 600;
    }

    $ogUrl = $baseUrl . '?t=' . intval($track);
}

$supportEnabled = (bool) get_config('support.enabled', false);
$supportMode = (string) get_config('support.mode', 'link');
$supportLabel = trim((string) get_config('support.label', 'Support'));
if ($supportLabel === '') {
    $supportLabel = 'Support';
}
$supportUrl = trim((string) get_config('support.url', ''));
$supportKofiPageId = bandpromo_support_parse_kofi_page_id((string) get_config('support.kofi_page_id', ''));
$supportButtonBackgroundColor = trim((string) get_config('support.button_background_color', '#323842'));
if ($supportButtonBackgroundColor === '') {
    $supportButtonBackgroundColor = '#323842';
}
$supportButtonTextColor = trim((string) get_config('support.button_text_color', '#ffffff'));
if ($supportButtonTextColor === '') {
    $supportButtonTextColor = '#ffffff';
}

$currentUsername = trim((string) ($_SESSION['username'] ?? ''));
$currentUserRole = current_user_role();
$showAdminButton = can_access_admin_panel();
$showDebugTools = is_developer();
$preferredAudioVariant = bandpromo_preferred_audio_variant($_SESSION['quality'] ?? null);
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

if ($supportEnabled && $supportUrl !== '') {
    $supportMode = 'link';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    require_once '../biblioteca/share-tools.php';
    echo generate_standard_meta_tags();
    ?>
    <title><?php echo $ogTitle; ?></title>

    <!-- Open Graph -->
    <?php echo generate_og_tags($ogTitle, $ogDescription, $ogImage, $ogUrl, 'music.song'); ?>

    <!-- Twitter Card -->
    <?php echo generate_twitter_tags($ogTitle, $ogDescription, $ogImage); ?>

    <!-- Favicons -->
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/favicon.svg">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/media/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo $ogTitle; ?>">

    <!-- Manifest & Theme -->
    <link rel="manifest" href="<?php echo htmlspecialchars($origin, ENT_QUOTES, 'UTF-8'); ?>/site.webmanifest?v=<?php echo rawurlencode($appVersion); ?>">
    <meta name="theme-color" content="#121212">
    <link rel="stylesheet" href="../biblioteca/style.css?v=<?php echo rawurlencode($appVersion); ?>">
</head>
<body>
    <?php
    require_once '../biblioteca/config-loader.php';
    if ($showDebugTools):
    ?>
    <button type="button" id="debug-panel-btn" title="Developer debug panel" aria-label="Open developer debug panel">🐛</button>
    <?php endif; ?>
    <?php if ($showAdminButton): ?>
    <a id="admin-btn" href="/admin.php" title="Admin panel">⚙️</a>
    <?php endif; ?>

    <div id="loading-msg">
        <h2>Cannot load config</h2>
        <p>If you are running this file directly from your hard drive, browsers block external files for security (CORS).</p>
        <p>Please use a local web server (like Live Server in VS Code) to run this.</p>
    </div>

    <div id="mediaplayer">
        <div class="scene">
            <div class="side-card prev" onclick="prevSong()">
                <img src="" id="prevCover" alt="Previous">
            </div>
            <div class="card-wrapper" id="cardWrapper">
                <img src="" alt="Album Cover" class="cover-art" id="coverImage">
                <div class="reflection">
                    <img src="" alt="" id="reflectionImage">
                </div>
            </div>
            <div class="side-card next" onclick="nextSong()">
                <img src="" id="nextCover" alt="Next">
            </div>
        </div>

        <div class="info-container">
            <canvas id="analyzer"></canvas>
            <h3 id="artistName">Artist</h3>
            <h2 id="songTitle">Title</h2>
        </div>

        <div class="controls">
            <button onclick="prevSong()">&#9664; Previous</button>
            <button onclick="togglePlay()" id="playBtn">Play</button>
            <button onclick="nextSong()">Next &#9654;</button>
        </div>

        <audio id="audioPlayer" controls controlsList="nodownload noplaybackrate" preload="metadata"></audio>

        <div id="beggars-banquet">
            &nbsp;
        </div>
    </div>

    <div id="content-container">
        <div class="content-logo">
            <img src="<?php echo htmlspecialchars(get_config('install.brand.logo', '/media/special/bandPromo_logo.png')); ?>" alt="Band Logo" class="content-logo-img">
        </div>
        <div class="content-toggle">
            <button class="active" type="button" data-view="playlist" onclick="toggleView('playlist')">Playlist</button>
            <button type="button" data-view="lyrics" onclick="toggleView('lyrics')">Lyrics</button>
            <button type="button" data-view="bio" onclick="toggleView('bio')">Bio</button>
            <button type="button" data-view="gallery" onclick="toggleView('gallery')">Gallery</button>
        </div>
        <div class="lyrics-box" id="lyricsBox">Loading lyrics...</div>
        <div class="playlist-box active" id="playlistBox">Loading playlist...</div>
        <div class="bio-box" id="bioBox">
            <?php
            $bio_file = dirname(__DIR__) . '/data/bio.html';
            if (!file_exists($bio_file)) {
                http_response_code(500);
                echo '<p>Missing runtime file: data/bio.html. Run setup.</p>';
            } else {
                echo file_get_contents($bio_file);
            }
            ?>
        </div>
        <div class="gallery-box" id="galleryBox">
            <div class="visuals-gallery" id="visualsGallery"></div>
        </div>
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
        window.CONFIG_URL       = '/play/playlist.json';
        window.MEDIA_AUDIO_BASE = '/media/audio';
        window.MEDIA_IMG_BASE   = '/media/img';
        window.BANDPROMO_PREFERRED_AUDIO_VARIANT = <?php echo json_encode($preferredAudioVariant); ?>;
        window.BANDPROMO_LOCAL_DEV = <?php echo json_encode(bandpromo_is_local_dev_host()); ?>;
        window.INITIAL_GALLERY_ITEMS = <?php echo json_encode($galleryItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
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
    <script src="../biblioteca/session-auth.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <script src="../biblioteca/lightbox.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <script src="../biblioteca/player.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <script src="../biblioteca/gallery.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
    <?php if ($supportEnabled && $supportMode === 'floating_widget' && $supportKofiPageId !== ''): ?>
    <script src="https://storage.ko-fi.com/cdn/scripts/overlay-widget.js"></script>
    <script>
        kofiWidgetOverlay.draw(<?php echo json_encode($supportKofiPageId); ?>, {
            'type': 'floating-chat',
            'floating-chat.donateButton.text': <?php echo json_encode($supportLabel); ?>,
            'floating-chat.donateButton.background-color': <?php echo json_encode($supportButtonBackgroundColor); ?>,
            'floating-chat.donateButton.text-color': <?php echo json_encode($supportButtonTextColor); ?>
        });
    </script>
    <?php elseif ($supportEnabled && $supportMode === 'link' && $supportUrl !== ''): ?>
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
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js?v=<?php echo rawurlencode($appVersion); ?>', {
                updateViaCache: 'none'
            }).catch(() => {});
        }
    </script>
</body>
</html>
