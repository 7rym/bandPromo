<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/release-storage.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';

$releaseFilter = bandpromo_playlist_normalize_release_filter((string) ($_GET['release'] ?? 'all'));
session_write_close();

$root = dirname(__DIR__);
$playlistId = bandpromo_playlist_normalize_id((string) ($_GET['playlist'] ?? BANDPROMO_PLAYLIST_DEFAULT_ID));
if ($playlistId === '') {
    $playlistId = BANDPROMO_PLAYLIST_DEFAULT_ID;
}

bandpromo_ensure_bundled_demo_audio_delivery($root);

try {
    bandpromo_playlist_ensure_seeded($root);
    bandpromo_release_ensure_seeded($root);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
    exit;
}

$result = bandpromo_run_light_json_task('scripts/playlistPreview.py', [
    'release' => $releaseFilter,
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
    $meta['previewSource'] = 'playlist-container';
}

$response = bandpromo_playlist_admin_editor_state($root, $playlistId, $releaseFilter, $poolByFile, $meta);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
