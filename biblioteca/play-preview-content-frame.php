<?php
declare(strict_types=1);

/**
 * Branding Live preview — Content frame.
 * Same #content-container classes + style.css + shell backdrop as /play.
 * Demo multi-campaign / multi-playlist + panel sample (Buttons / selector / Panels).
 */

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/content-chrome.php';
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
$logo = trim((string) ($assets['logo'] ?? ''));
$poster = trim((string) ($assets['poster'] ?? ''));

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
    // Keep working-copy asset URLs.
}

$playlistMode = strtolower(trim((string) ($player['playlist_selector'] ?? 'coverflow')));
if (!in_array($playlistMode, ['dropdown', 'buttons', 'coverflow'], true)) {
    $playlistMode = 'coverflow';
}

$brandTitle = trim((string) ($normalized['title'] ?? $normalized['name'] ?? 'Brand'));
if ($brandTitle === '') {
    $brandTitle = 'Brand';
}

$chrome = bandpromo_content_chrome_render([
    'preview' => true,
    'playlist_selector' => $playlistMode,
    'logo' => $logo,
    'poster' => $poster,
    'brand_title' => $brandTitle,
]);

try {
    $brandCss = bandpromo_brand_render_css_for_document($normalized);
} catch (Throwable $throwable) {
    $brandCss = '';
}

$fragment = strtolower(trim((string) ($payload['fragment'] ?? '')));
if ($fragment === 'chrome') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'chrome' => $chrome,
        'brand_css' => $brandCss,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$living = $shellLiving !== '';
$bodyClasses = ['play-preview-stacked', 'play-preview-content'];
if ($living) {
    $bodyClasses[] = 'shell-bg-video';
} elseif ($shellStill !== '') {
    $bodyClasses[] = 'shell-bg-image';
}

$esc = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$bodyClassAttr = $esc(implode(' ', $bodyClasses));
$bodyStyleAttr = (!$living && $shellStill !== '')
    ? ' style="background-image:url(\'' . $esc($shellStill) . '\');"'
    : '';

$shellVideoHtml = '';
if ($living) {
    $posterAttr = $shellStill !== '' ? ' poster="' . $esc($shellStill) . '"' : '';
    $shellVideoHtml = '<video id="shell-bg-video" class="shell-bg-video-el" src="'
        . $esc($shellLiving) . '"' . $posterAttr
        . ' muted loop playsinline autoplay preload="auto" aria-hidden="true" style="display:block;"></video>';
} else {
    $shellVideoHtml = '<video id="shell-bg-video" muted loop playsinline preload="none" aria-hidden="true" style="display:none;"></video>';
}

$styleCssPath = $root . '/biblioteca/style.css';
$shellCssPath = $root . '/biblioteca/shell.css';
$pageCssPath = $root . '/biblioteca/page-content.css';
$styleVer = is_file($styleCssPath) ? (string) filemtime($styleCssPath) : '1';
$shellVer = is_file($shellCssPath) ? (string) filemtime($shellCssPath) : '1';
$pageVer = is_file($pageCssPath) ? (string) filemtime($pageCssPath) : '1';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content preview</title>
    <link rel="stylesheet" href="/biblioteca/shell.css?v=<?php echo rawurlencode($shellVer); ?>">
    <link rel="stylesheet" href="/biblioteca/style.css?v=<?php echo rawurlencode($styleVer); ?>">
    <link rel="stylesheet" href="/biblioteca/page-content.css?v=<?php echo rawurlencode($pageVer); ?>">
    <?php echo $brandCss; ?>
    <style>
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
            padding: 8px 10px 16px !important;
            box-sizing: border-box;
            background-attachment: scroll !important;
        }
        body.play-preview-content #mediaplayer {
            display: none !important;
        }
        body.play-preview-content #content-container {
            width: min(100%, 36rem);
            max-width: 100%;
            margin: 0 auto;
        }
        /*
         * Live /play boxes use overflow-y:scroll (always reserve a gutter).
         * Content preview stacks playlist + panel samples — those forced bars
         * nest inside the iframe scroll. Collapse to one scroll (body only).
         */
        body.play-preview-content .playlist-box,
        body.play-preview-content .page-box,
        body.play-preview-content .lyrics-box {
            overflow: visible !important;
            height: auto !important;
            max-height: none !important;
            padding-bottom: 16px;
        }
        /* Force Hover sample without pointer (Branding Buttons row). */
        body.play-preview-content #content-container .content-toggle button[data-preview-state="hover"] {
            background: var(--content-control-bg-hover);
            border-color: var(--content-control-border);
            color: var(--content-control-fg-hover);
            box-shadow: 0 0 12px var(--primary-a30);
        }
        /* Link state samples inside the panel. */
        body.play-preview-content .play-preview-content-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.1rem;
            margin: 0.75rem 0 0;
        }
        body.play-preview-content a.play-preview-content-link[data-preview-link="hover"] {
            color: var(--color-link-hover, var(--color-link));
        }
        body.play-preview-content a.play-preview-content-link[data-preview-link="visited"] {
            color: var(--color-link-visited, var(--color-link));
        }
        body.play-preview-content #shell-bg-video.shell-bg-video-el,
        body.play-preview-content.shell-bg-video #shell-bg-video {
            display: block;
        }
        /* Focus ring when Branding section scrolls the matching sample into view. */
        body.play-preview-content .is-preview-focus {
            outline: 2px solid color-mix(in srgb, var(--primary-color, #7dd3fc) 70%, white);
            outline-offset: 4px;
            border-radius: 8px;
        }
    </style>
</head>
<body class="<?php echo $bodyClassAttr; ?>"<?php echo $bodyStyleAttr; ?>>
<?php echo $shellVideoHtml; ?>
<?php echo $chrome; ?>
</body>
</html>
