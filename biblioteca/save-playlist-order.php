<?php
/**
 * Save playlist order.
 *
 * Accepts a JSON array of filenames (the desired track order) via POST body.
 * Rewrites play/playlist.json to match the new order (immediate effect, no build needed).
 * Also writes data/playlist-order.json so future builds preserve the order.
 * Admin-only.
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/build-required.php';

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

function bandpromo_materialize_playlist_entries(array $filenames): array
{
    $requested = array_values(array_filter($filenames, static function ($entry) {
        return is_string($entry) && $entry !== '' && strpbrk($entry, '/\\') === false;
    }));
    if (!$requested) {
        return [
            'entries' => [],
            'missing' => [],
            'error' => '',
        ];
    }

    $result = bandpromo_run_light_json_task('scripts/playlistTrackEntries.py', [
        'filenames' => $requested,
    ]);
    $data = is_array($result['data'] ?? null) ? $result['data'] : null;
    if (!$result['ok'] || !is_array($data) || empty($data['ok'])) {
        $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
        $output = trim((string) ($result['output'] ?? ''));
        return [
            'entries' => [],
            'missing' => $requested,
            'error' => $error !== '' ? $error : ($output !== '' ? $output : 'Could not materialize playlist entries from source audio'),
        ];
    }

    $entries = [];
    foreach (($data['entries'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $file = trim((string) ($entry['file'] ?? ''));
        if ($file === '') {
            continue;
        }
        $entries[$file] = $entry;
    }

    $missing = [];
    foreach (($data['missing'] ?? []) as $entry) {
        $file = trim((string) $entry);
        if ($file !== '') {
            $missing[] = $file;
        }
    }

    return [
        'entries' => $entries,
        'missing' => $missing,
        'error' => '',
    ];
}

function bandpromo_sync_playlist_order_to_audio_masters(array $tracks): array
{
    $updated = 0;
    $warnings = [];
    $editableKeys = ['title', 'artist', 'album', 'date', 'bpm', 'initialkey', 'genre', 'comment', 'lyrics'];

    foreach (array_values($tracks) as $index => $track) {
        $filename = trim((string) ($track['file'] ?? ''));
        if ($filename === '') {
            continue;
        }

        $inspect = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
            'action' => 'inspect',
            'filename' => $filename,
        ]);
        $detail = is_array($inspect['data'] ?? null) ? $inspect['data'] : null;
        if (!$inspect['ok'] || !is_array($detail) || empty($detail['ok'])) {
            $warnings[] = [
                'file' => $filename,
                'error' => is_array($detail) ? (string) ($detail['error'] ?? 'Could not inspect audio master') : trim((string) ($inspect['output'] ?? 'Could not inspect audio master')),
            ];
            continue;
        }

        $fields = [];
        foreach ($editableKeys as $key) {
            $fields[$key] = trim((string) ($detail[$key] ?? ''));
        }
        $fields['tracknumber'] = (string) ($index + 1);

        $update = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
            'action' => 'update',
            'filename' => $filename,
            'fields' => $fields,
        ]);
        $updatedDetail = is_array($update['data'] ?? null) ? $update['data'] : null;
        if (!$update['ok'] || !is_array($updatedDetail) || empty($updatedDetail['ok'])) {
            $warnings[] = [
                'file' => $filename,
                'error' => is_array($updatedDetail) ? (string) ($updatedDetail['error'] ?? 'Could not update track number in audio master') : trim((string) ($update['output'] ?? 'Could not update track number in audio master')),
            ];
            continue;
        }

        $updated++;
    }

    return [
        'updated' => $updated,
        'warnings' => $warnings,
    ];
}

require_once __DIR__ . '/admin-api-guard.php';
session_write_close(); // release lock before file I/O

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

// Validate each entry is a non-empty string filename (no path separators)
foreach ($order as $entry) {
    if (!is_string($entry) || $entry === '' || strpbrk($entry, '/\\') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename in order array: ' . json_encode($entry)]);
        exit;
    }
}

$root          = dirname(__DIR__);
$playlist_file = $root . '/play/playlist.json';
$order_file    = $root . '/data/playlist-order.json';

// Read current playlist
if (!file_exists($playlist_file)) {
    http_response_code(500);
    echo json_encode(['error' => 'play/playlist.json not found — run a build first']);
    exit;
}
$current_raw = file_get_contents($playlist_file);
if ($current_raw === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not read play/playlist.json']);
    exit;
}
$current = json_decode($current_raw, true);
if (!is_array($current)) {
    http_response_code(500);
    echo json_encode(['error' => 'play/playlist.json is not a valid JSON array']);
    exit;
}

// Index existing tracks by filename for O(1) lookup
$by_file = [];
foreach ($current as $track) {
    if (isset($track['file'])) {
        $by_file[$track['file']] = $track;
    }
}

$missing_from_playlist = [];
foreach ($order as $filename) {
    if (!isset($by_file[$filename])) {
        $missing_from_playlist[] = $filename;
    }
}

$materialized = [
    'entries' => [],
    'missing' => [],
    'error' => '',
];
if ($missing_from_playlist) {
    $materialized = bandpromo_materialize_playlist_entries($missing_from_playlist);
    if ($materialized['error'] !== '') {
        http_response_code(500);
        echo json_encode(['error' => $materialized['error']]);
        exit;
    }
}

// Build playlist from requested order; materialize pool-only tracks from source audio.
$reordered = [];
$skipped = [];
foreach ($order as $filename) {
    if (isset($by_file[$filename])) {
        $reordered[] = $by_file[$filename];
        continue;
    }

    if (isset($materialized['entries'][$filename])) {
        $reordered[] = $materialized['entries'][$filename];
        continue;
    }

    $skipped[] = $filename;
}

$master_sync = bandpromo_sync_playlist_order_to_audio_masters($reordered);

// Write updated playlist.json before delivery prep so the partial audio task sees the saved track list.
$pretty = json_encode($reordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($playlist_file, $pretty) === false) {
    http_response_code(500);
    bandpromo_admin_audit_log('playlist_reordered', [
        'target_type' => 'playlist',
        'target_id' => 'play/playlist.json',
        'status' => 'error',
        'data' => ['error' => 'playlist write failed'],
    ]);
    echo json_encode(['error' => 'Could not write play/playlist.json — check file permissions']);
    exit;
}

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

// Write playlist-order.json (the canonical saved order for future builds)
$data_dir = $root . '/data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0750, true);
}
$final_order = array_values(array_filter($order, static fn($entry) => is_string($entry) && $entry !== ''));
$order_pretty = json_encode($final_order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($order_file, $order_pretty) === false) {
    // Non-fatal: playlist.json was already saved; just warn
    bandpromo_admin_audit_log('playlist_reordered', [
        'target_type' => 'playlist',
        'target_id' => 'play/playlist.json',
        'status' => 'warning',
        'data' => [
            'count' => count($reordered),
            'warning' => 'playlist-order write failed',
            'master_tracknumbers_updated' => $master_sync['updated'],
            'master_tracknumber_warnings' => count($master_sync['warnings']),
        ],
    ]);
    echo json_encode([
        'ok' => true,
        'count' => count($reordered),
        'requested' => count($final_order),
        'skipped' => $skipped,
        'warning' => 'Could not write data/playlist-order.json',
        'master_tracknumbers_updated' => $master_sync['updated'],
        'master_tracknumber_warnings' => $master_sync['warnings'],
    ]);
    exit;
}

$response = [
    'ok' => true,
    'count' => count($reordered),
    'requested' => count($final_order),
    'skipped' => $skipped,
    'delivery_prepared' => $delivery_result['prepared'],
    'delivery_failed' => $delivery_result['failed'],
    'delivery_missing' => $delivery_result['still_missing'],
    'master_tracknumbers_updated' => $master_sync['updated'],
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
if ($master_sync['warnings']) {
    $warnings[] = 'Some master track numbers could not be updated';
    $response['master_tracknumber_warnings'] = $master_sync['warnings'];
}
if ($warnings) {
    $response['warning'] = implode('. ', $warnings);
}

bandpromo_admin_audit_log('playlist_reordered', [
    'target_type' => 'playlist',
    'target_id' => 'play/playlist.json',
    'status' => $warnings ? 'warning' : 'ok',
    'data' => [
        'count' => count($reordered),
        'requested' => count($final_order),
        'skipped' => count($skipped),
        'master_tracknumbers_updated' => $master_sync['updated'],
        'master_tracknumber_warnings' => count($master_sync['warnings']),
    ],
]);

echo json_encode($response);
