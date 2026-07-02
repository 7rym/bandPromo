<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/gallery-helpers.php';
require_once __DIR__ . '/gallery-storage.php';
bandpromo_enforce_https();

session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

$root_dir = dirname(__DIR__);
$galleryId = bandpromo_gallery_resolve_id((string) ($_GET['gallery'] ?? BANDPROMO_GALLERY_DEMO_ID));

try {
    bandpromo_gallery_ensure_seeded($root_dir);
    $galleryData = bandpromo_gallery_materialize_items($root_dir, $galleryId);
} catch (Throwable $throwable) {
    $galleryData = bandpromo_load_gallery_items($root_dir, $galleryId);
    if ($galleryData === []) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Could not load gallery: ' . $throwable->getMessage()]);
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'gallery_id' => $galleryId,
    'images' => $galleryData,
    'totalImages' => count($galleryData),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
