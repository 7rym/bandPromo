<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/theme-storage.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$themeId = bandpromo_theme_normalize_id((string) ($_GET['theme'] ?? bandpromo_theme_active_id($root)));

try {
    bandpromo_theme_ensure_seeded($root);
    $document = bandpromo_theme_load_document($root, $themeId);

    echo json_encode([
        'ok' => true,
        'theme_id' => $themeId,
        'active_theme_id' => bandpromo_theme_active_id($root),
        'document' => $document,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
