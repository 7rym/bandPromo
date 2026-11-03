<?php
declare(strict_types=1);

/**
 * Branding Live preview — Player frame.
 * Same #mediaplayer renderer + style.css + shell backdrop as /play.
 * Forced stacked; demo track/cover; Large|Normal|Small only change scale vars.
 */

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/player-chrome.php';
require_once __DIR__ . '/config-loader.php';

session_write_close();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'POST required';
    exit;
}

$raw = file_get_contents('php://input');
$payload = [];
if (is_string($raw) && trim($raw) !== '') {
    $parsed = json_decode($raw, true);
    if (is_array($parsed)) {
        $payload = $parsed;
    }
}

$csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
if ($csrfToken === '' || !validate_csrf_token($csrfToken)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired or invalid request token. Refresh admin and try again.';
    exit;
}

$document = $payload['brand'] ?? null;
if (!is_array($document)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Brand document required.';
    exit;
}

$root = dirname(__DIR__);
try {
    $normalized = bandpromo_brand_normalize_document($document);
} catch (Throwable $throwable) {
    $normalized = $document;
}
$player = bandpromo_brand_normalize_player($normalized);
$assets = is_array($normalized['assets'] ?? null) ? $normalized['assets'] : [];
$poster = trim((string) ($assets['poster'] ?? ''));

// Shell atmosphere from brand assets (editor already resolves delivery URLs).
$shellStill = trim((string) ($assets['background_image'] ?? ''));
$shellLiving = trim((string) ($assets['background_video'] ?? ''));
try {
    $resolvedShell = bandpromo_brand_player_shell_assets($root, $normalized);
    if ($shellStill === '' && ($resolvedShell['background_image'] ?? '') !== '') {
        $shellStill = trim((string) $resolvedShell['background_image']);
    }
    if ($shellLiving === '' && ($resolvedShell['background_video'] ?? '') !== '') {
        $shellLiving = trim((string) $resolvedShell['background_video']);
    }
} catch (Throwable $throwable) {
    // Keep asset URLs from the working copy.
}

$loginStatus = !empty($player['login_status']);
$beggars = array_key_exists('beggars_banquet', $player) ? !empty($player['beggars_banquet']) : true;
$coverReflection = array_key_exists('cover_reflection', $player) ? !empty($player['cover_reflection']) : true;
$analyzer = array_key_exists('analyzer', $player) ? !empty($player['analyzer']) : true;

$supportEnabled = (bool) get_config('support.enabled', false);
$supportLabel = trim((string) get_config('support.label', 'Support'));
if ($supportLabel === '') {
    $supportLabel = 'Support';
}
$supportUrl = trim((string) get_config('support.url', ''));
$supportBg = trim((string) get_config('support.button_background_color', '#323842'));
$supportFg = trim((string) get_config('support.button_text_color', '#ffffff'));
if ($supportBg === '') {
    $supportBg = '#323842';
}
if ($supportFg === '') {
    $supportFg = '#ffffff';
}

$chrome = bandpromo_player_chrome_render([
    'preview' => true,
    'login_status' => $loginStatus,
    'show_admin' => false,
    'show_debug' => false,
    'username' => 'listener',
    'beggars_banquet' => $beggars,
    'support_enabled' => $supportEnabled,
    'support_url' => $supportUrl,
    'support_label' => $supportLabel,
    'support_button_background' => $supportBg,
    'support_button_text' => $supportFg,
    'demo' => [
        'artist' => 'Artist name',
        'title' => 'Track title',
        'version' => 'Version',
        'cover_url' => $poster,
        'prev_cover_url' => $poster,
        'next_cover_url' => $poster,
        'time_current' => '0:42',
        'time_duration' => '3:24',
    ],
]);

$living = $shellLiving !== '';
$bodyClasses = ['play-preview-stacked'];
if ($living) {
    $bodyClasses[] = 'shell-bg-video';
} elseif ($shellStill !== '') {
    $bodyClasses[] = 'shell-bg-image';
}
if (!$coverReflection) {
    $bodyClasses[] = 'cover-reflection-off';
}
if (!$analyzer) {
    $bodyClasses[] = 'analyzer-off';
}
$bodyClassAttr = htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES, 'UTF-8');
$bodyStyleAttr = (!$living && $shellStill !== '')
    ? ' style="background-image:url(\'' . htmlspecialchars($shellStill, ENT_QUOTES, 'UTF-8') . '\');"'
    : '';

$esc = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$shellVideoHtml = '';
if ($living) {
    $posterAttr = $shellStill !== '' ? ' poster="' . $esc($shellStill) . '"' : '';
    $shellVideoHtml = '<video id="shell-bg-video" class="shell-bg-video-el" src="'
        . $esc($shellLiving) . '"' . $posterAttr
        . ' muted loop playsinline autoplay preload="auto" aria-hidden="true" style="display:block;"></video>';
} else {
    $shellVideoHtml = '<video id="shell-bg-video" muted loop playsinline preload="none" aria-hidden="true" style="display:none;"></video>';
}

try {
    $brandCss = bandpromo_brand_render_css_for_document($normalized);
} catch (Throwable $throwable) {
    $brandCss = '';
}
$styleCssPath = $root . '/biblioteca/style.css';
$shellCssPath = $root . '/biblioteca/shell.css';
$styleVer = is_file($styleCssPath) ? (string) filemtime($styleCssPath) : '1';
$shellVer = is_file($shellCssPath) ? (string) filemtime($shellCssPath) : '1';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player preview</title>
    <link rel="stylesheet" href="/biblioteca/shell.css?v=<?php echo rawurlencode($shellVer); ?>">
    <link rel="stylesheet" href="/biblioteca/style.css?v=<?php echo rawurlencode($styleVer); ?>">
    <?php echo $brandCss; ?>
    <style>
        /*
         * Tall Branding column — stacked /play geometry only.
         * Same --card-size / --player-rail / --player-chrome-min contract as live stacked;
         * Player size only changes --player-size-scale-stacked (via brand CSS).
         */
        html, body {
            margin: 0;
            min-height: 100%;
            height: 100%;
        }
        body.play-preview-stacked {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-start !important;
            width: 100% !important;
            height: auto !important;
            min-height: 100% !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
            padding: 8px 0 12px !important;
            box-sizing: border-box;
            /* Match live stacked defaults (style.css :root), not a preview-only card cap. */
            --cover-size-scale: var(--player-size-scale-stacked, 0.62) !important;
            --card-size: min(100vw, 28rem) !important;
            --player-rail: min(
                calc(
                    var(--card-size) * var(--cover-size-scale, 0.62)
                    + 2 * var(--player-side-gutter, 12px) * var(--player-side-gutter-scale, 0)
                ),
                100%
            ) !important;
            background-attachment: scroll !important;
        }
        body.play-preview-stacked #mediaplayer {
            width: 100%;
            max-width: 100%;
            height: auto;
            min-height: 0;
        }
        body.play-preview-stacked #content-container {
            display: none !important;
        }
        /* Shell video is position:fixed in shell.css — fine inside the iframe. */
        body.play-preview-stacked #shell-bg-video.shell-bg-video-el,
        body.play-preview-stacked.shell-bg-video #shell-bg-video {
            display: block;
        }
        /* Preview reflection: show under cover when Branding Reflection is On. */
        body.play-preview-stacked:not(.cover-reflection-off) .reflection {
            display: block;
        }
    </style>
</head>
<body class="<?php echo $bodyClassAttr; ?>"<?php echo $bodyStyleAttr; ?>>
<?php echo $shellVideoHtml; ?>
<?php echo $chrome; ?>
<script>
(function () {
    var video = document.getElementById('shell-bg-video');
    if (!video || !video.getAttribute('src')) {
        return;
    }
    video.muted = true;
    video.loop = true;
    video.playsInline = true;
    var play = function () {
        var attempt = video.play();
        if (attempt && typeof attempt.catch === 'function') {
            attempt.catch(function () {});
        }
    };
    if (video.readyState >= 2) {
        play();
        return;
    }
    video.addEventListener('canplay', play, { once: true });
    try { video.load(); } catch (e) {}
    play();
})();
</script>
</body>
</html>
