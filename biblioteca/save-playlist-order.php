<?php
/**
 * Save playlist order.
 *
 * Accepts a JSON array of master filenames (desired track order) via POST body.
 * Persists membership, prepares missing delivery MP3s, and republishes this
 * playlist’s player payload so /play works without a full Deliverables rebuild.
 * Admin-only.
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/publish-status-helpers.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/asset-registry.php';

require_once __DIR__ . '/admin-api-guard.php';
session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

$order = json_decode($body, true);
if ($order === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}
if (!is_array($order)) {
    http_response_code(400);
    echo json_encode(['error' => 'Expected a JSON array of filenames']);
    exit;
}

foreach ($order as $entry) {
    if (!is_string($entry) || $entry === '' || strpbrk($entry, '/\\') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename in order array: ' . json_encode($entry)]);
        exit;
    }
}

$root = dirname(__DIR__);
$playlistId = bandpromo_playlist_resolve_id($root, (string) ($_GET['playlist'] ?? ''));

try {
    $saved = bandpromo_playlist_save_order($root, $playlistId, $order);
} catch (InvalidArgumentException $throwable) {
    http_response_code(400);
    echo json_encode(['error' => $throwable->getMessage()]);
    exit;
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
    exit;
}

$reordered = $saved['tracks'];
$skipped = $saved['skipped'];
$final_order = array_values(array_filter($order, static fn($entry) => is_string($entry) && $entry !== ''));

$missing_delivery = [];
foreach ($reordered as $track) {
    if (!is_array($track)) {
        continue;
    }
    $file = trim((string) ($track['file'] ?? ''));
    if ($file === '') {
        continue;
    }
    if (!bandpromo_asset_audio_delivery_ready($root, $file)) {
        $missing_delivery[] = $file;
    }
}

$deliveryPrepared = [];
$deliveryFailed = [];
$deliveryWarning = '';
if ($missing_delivery !== []) {
    $delivery = bandpromo_run_audio_source_delivery_and_refresh($missing_delivery);
    $deliveryPrepared = is_array($delivery['prepared'] ?? null) ? array_values($delivery['prepared']) : [];
    $deliveryFailed = is_array($delivery['failed'] ?? null) ? $delivery['failed'] : [];
    $stillMissing = is_array($delivery['still_missing'] ?? null) ? array_values($delivery['still_missing']) : [];
    $missing_delivery = $stillMissing;
    if (empty($delivery['ok'])) {
        $deliveryWarning = trim((string) ($delivery['error'] ?? ''));
        if ($deliveryWarning === '') {
            $deliveryWarning = 'Could not prepare audio delivery for some playlist tracks.';
        }
    }
}

$playerBuiltAt = '';
$publishWarning = '';
try {
    $published = bandpromo_playlist_publish_player_payload($root, $playlistId);
    $playerBuiltAt = trim((string) ($published['player_built_at'] ?? ''));
} catch (Throwable $throwable) {
    $publishWarning = 'Playlist saved, but player payload publish failed: ' . $throwable->getMessage();
}

$warnings = [];
if ($skipped) {
    $warnings[] = 'Some tracks could not be added to the playlist because they are not in the asset registry';
}
if ($deliveryWarning !== '') {
    $warnings[] = $deliveryWarning;
}
if ($missing_delivery !== []) {
    $warnings[] = 'Some tracks are saved in the playlist but still need audio delivery before playback will work. Check Python/ffmpeg, then save again or use System → Deliverables.';
}
if ($publishWarning !== '') {
    $warnings[] = $publishWarning;
}

$lightPathOk = $missing_delivery === [] && $publishWarning === '';
if ($lightPathOk) {
    $buildState = bandpromo_clear_build_required_tasks(['audio-delivery']);
    if (empty($buildState['required'])) {
        $buildState = bandpromo_set_build_required_last_error('');
    }
} else {
    $buildState = bandpromo_mark_build_required('playlist_order_changed');
    if ($deliveryWarning !== '' || $publishWarning !== '' || $missing_delivery !== []) {
        $buildState = bandpromo_set_build_required_last_error(implode(' ', array_filter([
            $deliveryWarning,
            $publishWarning,
            $missing_delivery !== [] ? 'Missing delivery for: ' . implode(', ', array_slice($missing_delivery, 0, 5)) : '',
        ])));
    }
}

$response = [
    'ok' => true,
    'playlist_id' => $playlistId,
    'count' => count($reordered),
    'requested' => count($final_order),
    'skipped' => $skipped,
    'delivery_prepared' => $deliveryPrepared,
    'delivery_failed' => $deliveryFailed,
    'delivery_missing' => $missing_delivery,
    'player_built_at' => $playerBuiltAt,
    'build_required' => !empty($buildState['required']),
    'build_required_state' => $buildState,
];

if ($warnings) {
    $response['warning'] = implode('. ', $warnings);
}

bandpromo_admin_audit_log('playlist_reordered', [
    'target_type' => 'playlist',
    'target_id' => 'data/playlists/' . $playlistId . '.json',
    'status' => $warnings ? 'warning' : 'ok',
    'data' => [
        'playlist_id' => $playlistId,
        'count' => count($reordered),
        'requested' => count($final_order),
        'skipped' => count($skipped),
        'player_built_at' => $playerBuiltAt,
        'delivery_prepared' => count($deliveryPrepared),
        'delivery_missing' => count($missing_delivery),
    ],
]);

echo json_encode($response);
