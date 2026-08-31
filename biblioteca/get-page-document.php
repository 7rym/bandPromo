<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/gallery-storage.php';
require_once __DIR__ . '/demo-catalog-state.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$pageKey = isset($_GET['page']) ? bandpromo_page_normalize_id((string) $_GET['page']) : 'bio';

try {
    if (!bandpromo_page_is_allowed_id($pageKey, $root)) {
        throw new InvalidArgumentException('Unknown page.');
    }
    if (!bandpromo_demo_page_visible_in_admin($root, $pageKey)) {
        throw new InvalidArgumentException('That demo page is hidden with the bandPromo demo campaign.');
    }

    $document = bandpromo_page_load_document($root, $pageKey);
    $document = bandpromo_page_editor_document($document, $root);
    $html = bandpromo_page_render_document($document, $root);
    $registryEntry = bandpromo_page_registry_entry($root, $pageKey);
    bandpromo_gallery_ensure_seeded($root);

    echo json_encode([
        'ok' => true,
        'page' => $pageKey,
        'document' => $document,
        'html' => $html,
        'registry' => is_array($registryEntry) ? [
            'title' => (string) ($registryEntry['title'] ?? ''),
            'label' => (string) ($registryEntry['label'] ?? ''),
            'surface' => (string) ($registryEntry['surface'] ?? 'player'),
            'required' => !empty($registryEntry['required']),
            'show_in_player' => !empty($registryEntry['show_in_player']),
        ] : null,
        'image_presets' => BANDPROMO_PAGE_IMAGE_PRESETS,
        'image_layouts' => bandpromo_page_operator_image_layouts(),
        'picture_styles' => bandpromo_page_operator_picture_styles(),
        'gallery_presets' => BANDPROMO_PAGE_GALLERY_PRESETS,
        'galleries' => bandpromo_gallery_admin_registry_entries($root),
        'block_types' => BANDPROMO_PAGE_BLOCK_TYPES,
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
