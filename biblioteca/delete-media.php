<?php
/**
 * Delete media files.
 * POST body: { target: "audio|cover|photos|video", filename: "..." }
 * or         { target: "audio|cover|photos|video", filenames: ["...", "..."] }
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

$target = $body['target'] ?? '';

if (!isset($dirs[$target])) {
    echo json_encode(['error' => 'Unknown target']);
    exit;
}

$requestedFiles = [];
if (isset($body['filenames']) && is_array($body['filenames'])) {
    $requestedFiles = $body['filenames'];
} elseif (isset($body['filename'])) {
    $requestedFiles = [$body['filename']];
}

if ($requestedFiles === []) {
    echo json_encode(['error' => 'No filename provided']);
    exit;
}

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

function bandpromo_video_poster_path(string $root, string $filename): string {
    return $root . '/media/video/poster/' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
}

function bandpromo_video_delivery_path(string $root, string $filename): string {
    return $root . '/media/video/optimal/' . pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
}

function bandpromo_delete_media_item(string $root, array $dirs, string $target, string $filename): array
{
    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return ['ok' => false, 'filename' => $filename, 'error' => 'Invalid filename'];
    }

    $path = $dirs[$target] . '/' . $safe;
    if (!file_exists($path)) {
        return ['ok' => false, 'filename' => $safe, 'error' => 'File not found'];
    }

    if (bandpromo_media_is_bundled_placeholder($safe)) {
        if (!bandpromo_media_set_hidden_for_install($target, $safe, true)) {
            bandpromo_admin_audit_log('media_hide_failed', [
                'target_type' => 'media',
                'target_id' => $target . '/' . $safe,
                'status' => 'error',
                'data' => ['error' => 'state write failed'],
            ]);
            return ['ok' => false, 'filename' => $safe, 'error' => 'Could not hide bundled demo file for this install'];
        }

        bandpromo_admin_audit_log('media_hidden', [
            'target_type' => 'media',
            'target_id' => $target . '/' . $safe,
            'status' => 'ok',
            'data' => ['origin' => 'bundled-placeholder'],
        ]);

        return [
            'ok' => true,
            'filename' => $safe,
            'action' => 'hidden',
            'message' => 'Bundled demo file hidden for this install.',
        ];
    }

    if (!unlink($path)) {
        bandpromo_admin_audit_log('media_deleted', [
            'target_type' => 'media',
            'target_id' => $target . '/' . $safe,
            'status' => 'error',
            'data' => ['error' => 'unlink failed'],
        ]);
        return ['ok' => false, 'filename' => $safe, 'error' => 'Could not delete file — check permissions'];
    }

    $master_deleted = false;
    $master_warning = '';
    $video_poster_deleted = false;
    $video_delivery_deleted = false;
    if ($target === 'audio') {
        foreach (bandpromo_audio_master_paths($root, $safe) as $master_path) {
            if (@unlink($master_path)) {
                $master_deleted = true;
            } else {
                $master_warning = 'Audio original was deleted, but one or more matching master files could not be removed';
            }
        }
    } elseif ($target === 'video') {
        $poster_path = bandpromo_video_poster_path($root, $safe);
        if (is_file($poster_path) && @unlink($poster_path)) {
            $video_poster_deleted = true;
        }
        $delivery_path = bandpromo_video_delivery_path($root, $safe);
        if (is_file($delivery_path) && @unlink($delivery_path)) {
            $video_delivery_deleted = true;
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
            'video_poster_deleted' => $video_poster_deleted,
            'video_delivery_deleted' => $video_delivery_deleted,
        ],
    ]);

    return [
        'ok' => true,
        'filename' => $safe,
        'action' => 'deleted',
        'master_deleted' => $master_deleted,
        'master_warning' => $master_warning,
        'video_poster_deleted' => $video_poster_deleted,
        'video_delivery_deleted' => $video_delivery_deleted,
    ];
}

$requestedFiles = array_values(array_unique(array_map(static fn($value) => (string) $value, $requestedFiles)));
$results = [];
foreach ($requestedFiles as $filename) {
    $results[] = bandpromo_delete_media_item($root, $dirs, $target, $filename);
}

if (count($results) === 1) {
    $result = $results[0];
    if (!$result['ok']) {
        echo json_encode(['error' => $result['error'] ?? 'Delete failed']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'action' => $result['action'] ?? 'deleted',
        'message' => $result['message'] ?? '',
        'master_deleted' => $result['master_deleted'] ?? false,
        'master_warning' => $result['master_warning'] ?? '',
    ]);
    exit;
}

$successful = array_values(array_filter($results, static fn($result) => !empty($result['ok'])));
$failed = array_values(array_filter($results, static fn($result) => empty($result['ok'])));
$hiddenCount = count(array_filter($successful, static fn($result) => ($result['action'] ?? '') === 'hidden'));
$deletedCount = count($successful) - $hiddenCount;
$warnings = array_values(array_filter(array_map(static fn($result) => (string) ($result['master_warning'] ?? ''), $successful)));

if ($successful === []) {
    echo json_encode([
        'error' => 'Could not remove any of the selected files',
        'failed' => array_map(static fn($result) => [
            'filename' => $result['filename'] ?? '',
            'error' => $result['error'] ?? 'Delete failed',
        ], $failed),
    ]);
    exit;
}

$messageParts = [];
if ($deletedCount > 0) {
    $messageParts[] = sprintf('Removed %d file%s', $deletedCount, $deletedCount === 1 ? '' : 's');
}
if ($hiddenCount > 0) {
    $messageParts[] = sprintf('hid %d bundled demo file%s', $hiddenCount, $hiddenCount === 1 ? '' : 's');
}
if ($failed !== []) {
    $messageParts[] = sprintf('%d failed', count($failed));
}

echo json_encode([
    'ok' => true,
    'count' => count($successful),
    'deleted_count' => $deletedCount,
    'hidden_count' => $hiddenCount,
    'failed_count' => count($failed),
    'failed' => array_map(static fn($result) => [
        'filename' => $result['filename'] ?? '',
        'error' => $result['error'] ?? 'Delete failed',
    ], $failed),
    'warnings' => $warnings,
    'message' => ucfirst(implode('; ', $messageParts)) . '.',
]);
