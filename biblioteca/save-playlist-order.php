<?php
/**
 * Save playlist order.
 *
 * Accepts a JSON array of master filenames (desired track order) via POST body.
 * Persists data/playlists/{id}.json and syncs legacy play/playlist.json for builds.
 * Admin-only.
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/playlist-storage.php';

function bandpromo_playlist_optimal_delivery_basename(string $sourceFilename): string
{
    return pathinfo($sourceFilename, PATHINFO_FILENAME) . '.mp3';
}

function bandpromo_playlist_optimal_delivery_path(string $root, string $sourceFilename): string
{
    return $root . '/media/audio/optimal/' . bandpromo_playlist_optimal_delivery_basename($sourceFilename);
}

function bandpromo_playlist_missing_optimal_delivery(array $tracks, string $root): array
{
    $missing = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }
        $file = trim((string) ($track['file'] ?? ''));
        if ($file === '') {
            continue;
        }
        if (!is_file(bandpromo_playlist_optimal_delivery_path($root, $file))) {
            $missing[] = $file;
        }
    }

    return $missing;
}

function bandpromo_prepare_playlist_audio_delivery(array $filenames, string $root): array
{
    $requested = array_values(array_filter($filenames, static function ($entry) {
        return is_string($entry) && $entry !== '' && strpbrk($entry, '/\\') === false;
    }));
    if (!$requested) {
        return [
            'ok' => true,
            'prepared' => [],
            'failed' => [],
            'still_missing' => [],
            'error' => '',
        ];
    }

    $result = bandpromo_run_light_json_task('scripts/playlistAudioDelivery.py', [
        'filenames' => $requested,
    ]);
    $data = is_array($result['data'] ?? null) ? $result['data'] : null;
    if (!$result['ok'] || !is_array($data)) {
        $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
        $output = trim((string) ($result['output'] ?? ''));

        return [
            'ok' => false,
            'prepared' => [],
            'failed' => array_map(static fn($file) => ['file' => $file, 'error' => 'delivery_task_failed'], $requested),
            'still_missing' => $requested,
            'error' => $error !== '' ? $error : ($output !== '' ? $output : 'Could not prepare playlist audio delivery files'),
        ];
    }

    return [
        'ok' => !empty($data['ok']),
        'prepared' => is_array($data['prepared'] ?? null) ? array_values($data['prepared']) : [],
        'failed' => is_array($data['failed'] ?? null) ? $data['failed'] : [],
        'still_missing' => is_array($data['still_missing'] ?? null) ? array_values($data['still_missing']) : [],
        'error' => empty($data['ok']) ? 'Some playlist delivery files could not be prepared' : '',
    ];
}

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
$playlistId = bandpromo_playlist_normalize_id((string) ($_GET['playlist'] ?? BANDPROMO_PLAYLIST_DEFAULT_ID));
if ($playlistId === '') {
    $playlistId = BANDPROMO_PLAYLIST_DEFAULT_ID;
}

try {
    $saved = bandpromo_playlist_save_order($root, $playlistId, $order);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
    exit;
}

$reordered = $saved['tracks'];
$skipped = $saved['skipped'];

$missing_delivery = bandpromo_playlist_missing_optimal_delivery($reordered, $root);
$delivery_result = [
    'ok' => true,
    'prepared' => [],
    'failed' => [],
    'still_missing' => [],
    'error' => '',
];
if ($missing_delivery) {
    $delivery_result = bandpromo_prepare_playlist_audio_delivery($missing_delivery, $root);
}

$final_order = array_values(array_filter($order, static fn($entry) => is_string($entry) && $entry !== ''));

$response = [
    'ok' => true,
    'playlist_id' => $playlistId,
    'count' => count($reordered),
    'requested' => count($final_order),
    'skipped' => $skipped,
    'delivery_prepared' => $delivery_result['prepared'],
    'delivery_failed' => $delivery_result['failed'],
    'delivery_missing' => $delivery_result['still_missing'],
];

$warnings = [];
if ($skipped) {
    $warnings[] = 'Some tracks could not be added to the playlist because their source audio was not found';
}
if ($delivery_result['still_missing']) {
    $warnings[] = 'Some tracks are saved in the playlist but still need audio delivery before playback will work';
    $response['build_required'] = true;
    $response['build_required_state'] = bandpromo_mark_build_required('playlist_order_changed');
} elseif ($delivery_result['prepared']) {
    $response['auto_tasks'] = ['audio-delivery'];
    $response['build_required_state'] = bandpromo_clear_build_required_tasks(['audio-delivery']);
    $response['build_required'] = !empty($response['build_required_state']['required']);
}
if ($delivery_result['error'] !== '') {
    $warnings[] = $delivery_result['error'];
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
