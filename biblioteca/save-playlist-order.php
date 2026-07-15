<?php
/**
 * Save playlist order.
 *
 * Accepts a JSON array of master filenames (desired track order) via POST body.
 * Persists data/playlists/{id}.json entry refs only (no tag parse / delivery).
 * Admin-only.
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/publish-status-helpers.php';

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

$response = [
    'ok' => true,
    'playlist_id' => $playlistId,
    'count' => count($reordered),
    'requested' => count($final_order),
    'skipped' => $skipped,
    'delivery_prepared' => [],
    'delivery_failed' => [],
    'delivery_missing' => $missing_delivery,
];

$warnings = [];
if ($skipped) {
    $warnings[] = 'Some tracks could not be added to the playlist because they are not in the asset registry';
}
$response['build_required_state'] = bandpromo_mark_build_required('playlist_order_changed');
$response['build_required'] = true;
if ($missing_delivery) {
    $warnings[] = 'Some tracks are saved in the playlist but still need audio delivery before playback will work. Run System → Publish.';
}

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
    ],
]);

echo json_encode($response);
