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
$final_order = array_column($reordered, 'file');
$order_pretty = json_encode($final_order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($order_file, $order_pretty) === false) {
    // Non-fatal: playlist.json was already saved; just warn
    bandpromo_admin_audit_log('playlist_reordered', [
        'target_type' => 'playlist',
        'target_id' => 'play/playlist.json',
        'status' => 'warning',
        'data' => ['count' => count($reordered), 'warning' => 'playlist-order write failed'],
    ]);
    echo json_encode(['ok' => true, 'count' => count($reordered), 'warning' => 'Could not write data/playlist-order.json']);
    exit;
}

bandpromo_admin_audit_log('playlist_reordered', [
    'target_type' => 'playlist',
    'target_id' => 'play/playlist.json',
    'status' => 'ok',
    'data' => ['count' => count($reordered)],
]);

echo json_encode(['ok' => true, 'count' => count($reordered)]);
