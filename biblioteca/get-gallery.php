<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/gallery-storage.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$galleryId = bandpromo_gallery_resolve_id((string) ($_GET['gallery'] ?? BANDPROMO_GALLERY_DEMO_ID));

try {
    bandpromo_gallery_ensure_seeded($root);
    $document = bandpromo_gallery_load_document($root, $galleryId);
    $items = bandpromo_gallery_materialize_items($root, $galleryId);

    echo json_encode([
        'ok' => true,
        'gallery_id' => $galleryId,
        'title' => (string) ($document['title'] ?? $galleryId),
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
