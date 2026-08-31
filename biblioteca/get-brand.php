<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/demo-catalog-state.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$brandId = bandpromo_brand_normalize_id((string) ($_GET['brand'] ?? $_GET['theme'] ?? bandpromo_brand_active_id($root)));

try {
    bandpromo_brand_ensure_seeded($root);
    if (!bandpromo_demo_brand_visible_in_admin($root, $brandId)) {
        throw new InvalidArgumentException('That demo brand is hidden with the bandPromo demo campaign.');
    }
    $document = bandpromo_brand_load_document($root, $brandId);
    $activeBrandId = bandpromo_brand_active_id($root);

    echo json_encode([
        'ok' => true,
        'brand_id' => $brandId,
        'active_brand_id' => $activeBrandId,
        // Backwards compatibility for older clients not yet migrated.
        'theme_id' => $brandId,
        'active_theme_id' => $activeBrandId,
        'document' => bandpromo_brand_api_document($document),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
