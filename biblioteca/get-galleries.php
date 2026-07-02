<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/gallery-storage.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);

try {
    bandpromo_gallery_ensure_seeded($root);
    $galleries = bandpromo_gallery_registry_entries($root);
    $defaultId = BANDPROMO_GALLERY_DEMO_ID;

    echo json_encode([
        'ok' => true,
        'default_gallery_id' => $defaultId,
        'galleries' => $galleries,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
