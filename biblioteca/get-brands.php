<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/brand-storage.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);

try {
    bandpromo_brand_ensure_seeded($root);
    $brands = bandpromo_brand_registry_entries($root);
    $activeId = bandpromo_brand_active_id($root);

    echo json_encode([
        'ok' => true,
        'active_brand_id' => $activeId,
        'brands' => $brands,
        // Backwards compatibility for older clients not yet migrated.
        'active_theme_id' => $activeId,
        'themes' => $brands,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
