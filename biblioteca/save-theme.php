<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/theme-storage.php';
require_once __DIR__ . '/playlist-storage.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$root = dirname(__DIR__);
$body = file_get_contents('php://input');
$decoded = json_decode($body ?: '{}', true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

try {
    bandpromo_theme_ensure_seeded($root);
    $themeId = bandpromo_theme_normalize_id((string) ($decoded['id'] ?? ''));
    if ($themeId === '') {
        throw new InvalidArgumentException('Theme id is required.');
    }

    $existing = bandpromo_theme_load_document($root, $themeId);
    if (!empty($existing['locked'])) {
        throw new RuntimeException('This theme is locked. Duplicate it to customize colors.');
    }

    $document = bandpromo_theme_normalize_document(array_merge($existing, $decoded), $themeId);
    bandpromo_theme_write_document($root, $document);
    $document = bandpromo_theme_load_document($root, $themeId);

    if (bandpromo_brand_active_id($root) === bandpromo_brand_canonical_id($themeId)) {
        bandpromo_theme_sync_assets_to_config($root, $document);
    }

    $registry = bandpromo_theme_load_registry($root);
    $canonicalId = bandpromo_brand_canonical_id($themeId);
    foreach ($registry['brands'] ?? [] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) === $canonicalId) {
            $registry['brands'][$index]['title'] = $document['title'];
            break;
        }
    }
    bandpromo_theme_write_registry($root, $registry);

    $playlistBrandRefresh = bandpromo_playlist_refresh_brand_styles_for_brand($root, $canonicalId);

    bandpromo_admin_audit_log('brand_saved', [
        'target_type' => 'brand',
        'target_id' => 'data/brands/' . $canonicalId . '.json',
        'status' => 'ok',
        'playlist_brand_styles_updated' => $playlistBrandRefresh['updated'],
    ]);

    echo json_encode([
        'ok' => true,
        'document' => $document,
        'playlist_brand_styles_updated' => $playlistBrandRefresh['updated'],
    ]);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
