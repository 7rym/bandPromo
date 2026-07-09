<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/playlist-storage.php';

bandpromo_enforce_https();
require_once __DIR__ . '/admin-api-guard.php';

session_write_close();

$root = dirname(__DIR__);
$releaseId = bandpromo_release_normalize_id((string) ($_GET['release'] ?? BANDPROMO_RELEASE_DEFAULT_ID));
if ($releaseId === '') {
    $releaseId = BANDPROMO_RELEASE_DEFAULT_ID;
}

bandpromo_ensure_bundled_demo_audio_delivery($root);

try {
    bandpromo_release_ensure_seeded($root);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
    exit;
}

$poolByFile = bandpromo_release_pool_map_canonical($root, []);
$meta = [
    'previewSource' => 'release-container',
    'unsupportedSourceFiles' => [],
    'hiddenBundledSourceFiles' => [],
];

$response = bandpromo_release_admin_editor_state($root, $releaseId, $poolByFile, $meta);
if (!empty($response['activeTracks']) && is_array($response['activeTracks'])) {
    $response['activeTracks'] = bandpromo_playlist_enrich_tracks_for_player($root, $response['activeTracks'], true);
}
if (!empty($response['availableTracks']) && is_array($response['availableTracks'])) {
    $response['availableTracks'] = bandpromo_playlist_enrich_tracks_for_player($root, $response['availableTracks'], true);
}
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
