<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/playlist-storage.php';

bandpromo_enforce_https();
require_once __DIR__ . '/admin-api-guard.php';

session_write_close();

$root = dirname(__DIR__);
$releaseId = bandpromo_campaign_normalize_id((string) ($_GET['release'] ?? BANDPROMO_CAMPAIGN_DEFAULT_ID));
if ($releaseId === '') {
    $releaseId = BANDPROMO_CAMPAIGN_DEFAULT_ID;
}

try {
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

$response = bandpromo_campaign_admin_editor_state($root, $releaseId, $poolByFile, $meta);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
