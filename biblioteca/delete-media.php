<?php
/**
 * Delete a media file.
 * POST body: { target: "audio|cover|photos|video", filename: "..." }
 * Admin-only.
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/media-library-state.php';
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

function bandpromo_audio_master_paths(string $root, string $filename): array {
    $master_dir = $root . '/media/audio/master';
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    foreach (['flac', 'mp3', 'wav'] as $ext) {
        $path = $master_dir . '/' . $stem . '.' . $ext;
        if (is_file($path)) {
            $paths[] = $path;
        }
    }

    return $paths ?? [];
}

if (!file_exists($path)) {
    echo json_encode(['error' => 'File not found']);
    exit;
}

if (bandpromo_media_is_bundled_placeholder($safe)) {
    if (!bandpromo_media_set_hidden_for_install($target, $safe, true)) {
        bandpromo_admin_audit_log('media_hide_failed', [
            'target_type' => 'media',
            'target_id' => $target . '/' . $safe,
            'status' => 'error',
            'data' => ['error' => 'state write failed'],
        ]);
        echo json_encode(['error' => 'Could not hide bundled demo file for this install']);
        exit;
    }

    bandpromo_admin_audit_log('media_hidden', [
        'target_type' => 'media',
        'target_id' => $target . '/' . $safe,
        'status' => 'ok',
        'data' => ['origin' => 'bundled-placeholder'],
    ]);

    echo json_encode([
        'ok' => true,
        'action' => 'hidden',
        'message' => 'Bundled demo file hidden for this install.',
    ]);
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

$master_deleted = false;
$master_warning = '';
if ($target === 'audio') {
    foreach (bandpromo_audio_master_paths($root, $safe) as $master_path) {
        if (@unlink($master_path)) {
            $master_deleted = true;
        } else {
            $master_warning = 'Audio original was deleted, but one or more matching master files could not be removed';
        }
    }
}

bandpromo_media_set_hidden_for_install($target, $safe, false);

bandpromo_admin_audit_log('media_deleted', [
    'target_type' => 'media',
    'target_id' => $target . '/' . $safe,
    'status' => 'ok',
    'data' => [
        'master_deleted' => $master_deleted,
        'master_warning' => $master_warning,
    ],
]);

echo json_encode([
    'ok' => true,
    'master_deleted' => $master_deleted,
    'master_warning' => $master_warning,
]);
