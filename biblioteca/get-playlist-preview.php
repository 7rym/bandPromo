<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/campaign-storage.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';

$releaseFilter = bandpromo_playlist_normalize_release_filter((string) ($_GET['release'] ?? 'all'));
session_write_close();

$root = dirname(__DIR__);
$playlistId = bandpromo_playlist_resolve_id($root, (string) ($_GET['playlist'] ?? ''));

try {
    bandpromo_playlist_ensure_seeded($root);
    bandpromo_campaign_ensure_seeded($root);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
    exit;
}

$poolByFile = bandpromo_campaign_pool_map_canonical($root, []);
$poolByFile = bandpromo_playlist_enrich_pool_campaign_ids($root, $poolByFile);
$poolByFile = bandpromo_playlist_enrich_pool_delivery_ready($root, $poolByFile);

$meta = [
    'previewSource' => 'asset-registry',
    'unsupportedSourceFiles' => [],
    'hiddenBundledSourceFiles' => [],
];

$response = bandpromo_playlist_admin_editor_state($root, $playlistId, $releaseFilter, $poolByFile, $meta);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
