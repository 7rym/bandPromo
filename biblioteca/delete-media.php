<?php
/**
 * Delete a media file.
 * POST body: { target: "audio|cover|photos|video", filename: "..." }
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

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$root = dirname(__DIR__);
$dirs = [
    'audio'         => $root . '/media/audio/original',
    'illustrations' => $root . '/media/img/original',
    'photos'        => $root . '/media/photo/original',
    'video'         => $root . '/media/video/original',
    'special'       => $root . '/media/special',
];

$target   = $body['target']   ?? '';
$filename = $body['filename'] ?? '';

if (!isset($dirs[$target])) {
    echo json_encode(['error' => 'Unknown target']);
    exit;
}

// Sanitise: basename only — no path traversal possible
$safe = basename($filename);
if ($safe === '' || $safe === '.' || $safe === '..') {
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

$path = $dirs[$target] . '/' . $safe;

if (!file_exists($path)) {
    echo json_encode(['error' => 'File not found']);
    exit;
}

if (!unlink($path)) {
    bandpromo_admin_audit_log('media_deleted', [
        'target_type' => 'media',
        'target_id' => $target . '/' . $safe,
        'status' => 'error',
        'data' => ['error' => 'unlink failed'],
    ]);
    echo json_encode(['error' => 'Could not delete file — check permissions']);
    exit;
}

bandpromo_admin_audit_log('media_deleted', [
    'target_type' => 'media',
    'target_id' => $target . '/' . $safe,
    'status' => 'ok',
]);

echo json_encode(['ok' => true]);
