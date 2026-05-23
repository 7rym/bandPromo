<?php
require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/audio-master-helpers.php';

function bandpromo_download_wants_json(): bool
{
    $preflight = $_POST['preflight'] ?? $_GET['preflight'] ?? '';
    if (is_string($preflight)) {
        $normalized = strtolower(trim($preflight));
        return $normalized === '1' || $normalized === 'true' || $normalized === 'yes';
    }
    return $preflight === 1 || $preflight === true;
}

function bandpromo_download_error(int $status, string $message, bool $jsonMode): void
{
    http_response_code($status);
    if ($jsonMode) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$jsonMode = bandpromo_download_wants_json();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    bandpromo_download_error(401, 'Unauthorized', $jsonMode);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bandpromo_download_error(405, 'POST required', $jsonMode);
}

$target = trim((string) ($_POST['target'] ?? ''));
$variant = trim((string) ($_POST['variant'] ?? 'original'));
$requestedFiles = $_POST['filenames'] ?? ($_POST['filename'] ?? []);
if (!is_array($requestedFiles)) {
    $requestedFiles = [$requestedFiles];
}

$requestedFiles = array_values(array_unique(array_filter(array_map(static fn($value) => basename((string) $value), $requestedFiles))));
if ($requestedFiles === []) {
    bandpromo_download_error(400, 'No files selected', $jsonMode);
}

$root = dirname(__DIR__);
$sourceDir = bandpromo_media_target_dir($target);
if ($sourceDir === null) {
    bandpromo_download_error(400, 'Unknown target', $jsonMode);
}

if ($variant !== 'original' && $variant !== 'master') {
    bandpromo_download_error(400, 'Unknown download variant', $jsonMode);
}

if ($variant === 'master' && $target !== 'audio') {
    bandpromo_download_error(400, 'Prepared downloads are only available for audio', $jsonMode);
}

function bandpromo_download_content_type(string $filename): string
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'flac' => 'audio/flac',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'txt' => 'text/plain; charset=utf-8',
        'zip' => 'application/zip',
    ];

    return $map[$ext] ?? 'application/octet-stream';
}

function bandpromo_resolve_download_item(string $root, string $sourceDir, string $target, string $variant, string $filename): array
{
    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return ['ok' => false, 'filename' => $filename, 'error' => 'Invalid filename'];
    }

    if ($variant === 'master') {
        $master = bandpromo_find_audio_master($root, $safe);
        if (empty($master['exists']) || empty($master['filename'])) {
            return ['ok' => false, 'filename' => $safe, 'error' => 'Prepared copy not found'];
        }
        $path = $root . '/media/audio/master/' . $master['filename'];
        if (!is_file($path)) {
            return ['ok' => false, 'filename' => $safe, 'error' => 'Prepared copy not found'];
        }

        return [
            'ok' => true,
            'filename' => $safe,
            'download_name' => basename((string) $master['filename']),
            'path' => $path,
        ];
    }

    $path = $sourceDir . '/' . $safe;
    if (!is_file($path)) {
        return ['ok' => false, 'filename' => $safe, 'error' => 'File not found'];
    }

    return [
        'ok' => true,
        'filename' => $safe,
        'download_name' => $safe,
        'path' => $path,
    ];
}

function bandpromo_stream_download_file(string $path, string $downloadName): void
{
    header('Content-Type: ' . bandpromo_download_content_type($downloadName));
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Cache-Control: private, no-store, max-age=0');
    readfile($path);
}

function bandpromo_zip_entry_name(array &$usedNames, string $downloadName): string
{
    $base = $downloadName;
    $stem = pathinfo($downloadName, PATHINFO_FILENAME);
    $ext = pathinfo($downloadName, PATHINFO_EXTENSION);
    $index = 2;
    while (isset($usedNames[$base])) {
        $base = $stem . ' (' . $index . ')' . ($ext !== '' ? '.' . $ext : '');
        $index++;
    }
    $usedNames[$base] = true;
    return $base;
}

$resolved = [];
$failures = [];
foreach ($requestedFiles as $filename) {
    $item = bandpromo_resolve_download_item($root, $sourceDir, $target, $variant, $filename);
    if (!empty($item['ok'])) {
        $resolved[] = $item;
    } else {
        $failures[] = $item;
    }
}

if ($resolved === []) {
    bandpromo_download_error(404, $failures[0]['error'] ?? 'Nothing available to download', $jsonMode);
}

if ($jsonMode) {
    if (count($resolved) > 1 && !class_exists('ZipArchive')) {
        bandpromo_download_error(500, 'Multi-file download is unavailable on this server because ZipArchive is missing.', true);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'mode' => count($resolved) === 1 && $failures === [] ? 'single' : 'archive',
        'resolved_count' => count($resolved),
        'failed_count' => count($failures),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (count($resolved) === 1 && $failures === []) {
    bandpromo_stream_download_file($resolved[0]['path'], $resolved[0]['download_name']);
    exit;
}

if (!class_exists('ZipArchive')) {
    bandpromo_download_error(500, 'ZipArchive is required for multi-file downloads', false);
}

$zipPath = tempnam(sys_get_temp_dir(), 'bandpromo-download-');
if ($zipPath === false) {
    bandpromo_download_error(500, 'Could not create temporary ZIP file', false);
}

$zip = new ZipArchive();
$openResult = $zip->open($zipPath, ZipArchive::OVERWRITE);
if ($openResult !== true) {
    @unlink($zipPath);
    bandpromo_download_error(500, 'Could not open temporary ZIP file', false);
}

$usedNames = [];
foreach ($resolved as $item) {
    $entryName = bandpromo_zip_entry_name($usedNames, (string) $item['download_name']);
    $zip->addFile($item['path'], $entryName);
}

if ($failures !== []) {
    $notes = "Some requested files were not included:\n\n";
    foreach ($failures as $failure) {
        $notes .= '- ' . ($failure['filename'] ?? 'unknown') . ': ' . ($failure['error'] ?? 'Unavailable') . "\n";
    }
    $zip->addFromString('download-notes.txt', $notes);
}

$zip->close();

$archiveName = sprintf(
    'bandpromo-%s-%s-%s.zip',
    preg_replace('/[^a-z0-9]+/i', '-', $target),
    $variant === 'master' ? 'ready' : 'uploaded',
    gmdate('Ymd-His')
);

register_shutdown_function(static function () use ($zipPath): void {
    if (is_file($zipPath)) {
        @unlink($zipPath);
    }
});

bandpromo_stream_download_file($zipPath, $archiveName);
exit;