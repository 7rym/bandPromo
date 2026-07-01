<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/light-build-tasks.php';
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

$result = bandpromo_run_light_json_task('scripts/playlistPreview.py', [
    'release' => 'all',
]);

$data = is_array($result['data'] ?? null) ? $result['data'] : null;
$poolByFile = [];
$meta = [
    'previewSource' => 'audio-pool',
    'unsupportedSourceFiles' => [],
    'hiddenBundledSourceFiles' => [],
];

if ($result['ok'] && is_array($data) && !empty($data['ok'])) {
    $poolTracks = array_merge(
        is_array($data['activeTracks'] ?? null) ? $data['activeTracks'] : [],
        is_array($data['availableTracks'] ?? null) ? $data['availableTracks'] : [],
        is_array($data['tracks'] ?? null) ? $data['tracks'] : []
    );
    $poolByFile = bandpromo_playlist_pool_map_from_preview_tracks($poolTracks);
    $meta['unsupportedSourceFiles'] = is_array($data['unsupportedSourceFiles'] ?? null)
        ? $data['unsupportedSourceFiles']
        : [];
    $meta['hiddenBundledSourceFiles'] = is_array($data['hiddenBundledSourceFiles'] ?? null)
        ? $data['hiddenBundledSourceFiles']
        : [];
} else {
    $meta['previewSource'] = 'release-container';
}

$poolByFile = bandpromo_release_pool_map_canonical($root, $poolByFile);

$response = bandpromo_release_admin_editor_state($root, $releaseId, $poolByFile, $meta);
if (!empty($response['activeTracks']) && is_array($response['activeTracks'])) {
    $response['activeTracks'] = bandpromo_playlist_enrich_tracks_for_player($root, $response['activeTracks'], true);
}
if (!empty($response['availableTracks']) && is_array($response['availableTracks'])) {
    $response['availableTracks'] = bandpromo_playlist_enrich_tracks_for_player($root, $response['availableTracks'], true);
}
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
