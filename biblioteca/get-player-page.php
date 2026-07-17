<?php
declare(strict_types=1);

/**
 * Public player delivery for page-tab HTML (Bio, Gallery, custom pages).
 * Auth required — same gate as /play and get-player-playlist.php.
 */
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/page-registry.php';

bandpromo_enforce_https();

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$root = dirname(__DIR__);
$pageId = bandpromo_page_normalize_id((string) ($_GET['page'] ?? ''));

if ($pageId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Page id is required.']);
    exit;
}

try {
    $entry = bandpromo_page_registry_entry($root, $pageId);
    if ($entry === null || empty($entry['show_in_player'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'This page is not available.']);
        exit;
    }
    if (($entry['surface'] ?? 'player') === 'login') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'This page is not available.']);
        exit;
    }

    $html = bandpromo_page_render_for_delivery($root, $pageId);

    echo json_encode([
        'ok' => true,
        'page' => $pageId,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
