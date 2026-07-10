<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/demo-catalog-state.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);

try {
    bandpromo_release_ensure_seeded($root);
    $releases = bandpromo_release_admin_registry_entries($root);

    echo json_encode([
        'ok' => true,
        'default_release_id' => BANDPROMO_RELEASE_DEFAULT_ID,
        'demo_release_id' => BANDPROMO_RELEASE_DEMO_ID,
        'demo_catalog_visible' => bandpromo_demo_catalog_is_visible($root),
        'releases' => $releases,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
