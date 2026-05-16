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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
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

// Build reordered playlist: requested order first, then any tracks not in the order list
$reordered = [];
foreach ($order as $filename) {
    if (isset($by_file[$filename])) {
        $reordered[] = $by_file[$filename];
        unset($by_file[$filename]);
    }
    // silently skip filenames not found in current playlist
}
// Append any remaining tracks not mentioned in the order (e.g. newly added mid-session)
foreach ($by_file as $track) {
    $reordered[] = $track;
}

$master_sync = bandpromo_sync_playlist_order_to_audio_masters($reordered);

// Write updated playlist.json
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
        'count' => count($final_order),
        'warning' => 'Could not write data/playlist-order.json',
        'master_tracknumbers_updated' => $master_sync['updated'],
        'master_tracknumber_warnings' => $master_sync['warnings'],
    ]);
    exit;
}

$response = [
    'ok' => true,
    'count' => count($reordered),
    'master_tracknumbers_updated' => $master_sync['updated'],
];

if ($master_sync['warnings']) {
    $response['warning'] = 'Playlist order saved, but some master track numbers could not be updated';
    $response['master_tracknumber_warnings'] = $master_sync['warnings'];
}

bandpromo_admin_audit_log('playlist_reordered', [
    'target_type' => 'playlist',
    'target_id' => 'play/playlist.json',
    'status' => $master_sync['warnings'] ? 'warning' : 'ok',
    'data' => [
        'count' => count($reordered),
        'master_tracknumbers_updated' => $master_sync['updated'],
        'master_tracknumber_warnings' => count($master_sync['warnings']),
    ],
]);

echo json_encode($response);
