<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/brand-storage.php';
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
    bandpromo_brand_ensure_seeded($root);
    $themeId = bandpromo_brand_normalize_id((string) ($decoded['id'] ?? ''));
    if ($themeId === '') {
        throw new InvalidArgumentException('Theme id is required.');
    }

    $existing = bandpromo_brand_load_document($root, $themeId);
    if (!bandpromo_brand_may_edit_document($existing)) {
        throw new RuntimeException('This theme is locked. Duplicate it to customise colours, or unlock on localhost for PCF source edits.');
    }

    $document = bandpromo_brand_normalize_document(array_merge($existing, $decoded), $themeId);
    // Preserve lock unless localhost explicitly unlocks the platform default.
    if (array_key_exists('locked', $decoded) && bandpromo_brand_may_change_lock($themeId)) {
        $document['locked'] = !empty($decoded['locked']);
    } else {
        $document['locked'] = !empty($existing['locked']);
    }
    bandpromo_brand_write_document($root, $document, ['allow_locked' => true]);
    $document = bandpromo_brand_load_document($root, $themeId);

    if (bandpromo_brand_active_id($root) === bandpromo_brand_canonical_id($themeId)) {
        bandpromo_brand_sync_assets_to_config($root, $document);
    }

    $registry = bandpromo_brand_load_registry($root);
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
    bandpromo_brand_write_registry($root, $registry);

    $playlistBrandRefresh = bandpromo_playlist_refresh_brand_styles_for_brand($root, $canonicalId);

    bandpromo_admin_audit_log('brand_saved', [
        'target_type' => 'brand',
        'target_id' => 'data/brands/' . $canonicalId . '.json',
        'status' => 'ok',
        'playlist_brand_styles_updated' => $playlistBrandRefresh['updated'],
    ]);

    echo json_encode([
        'ok' => true,
        'document' => bandpromo_brand_api_document($document),
        'playlist_brand_styles_updated' => $playlistBrandRefresh['updated'],
    ]);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
