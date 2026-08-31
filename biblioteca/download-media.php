<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/download-token-helpers.php';

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

function bandpromo_download_current_audio_name(?array $asset, string $masterFilename): string
{
    $extension = strtolower((string) pathinfo($masterFilename, PATHINFO_EXTENSION));
    $display = bandpromo_asset_read_audio_display($asset);
    $artist = trim((string) ($display['artist'] ?? ''));
    $title = trim((string) ($display['title'] ?? ''));
    $version = trim((string) ($display['version'] ?? ''));

    if ($title === '') {
        return basename($masterFilename);
    }

    $label = $artist !== '' ? $artist . ' - ' . $title : $title;
    if ($version !== '') {
        $label .= ' [' . $version . ']';
    }

    // Keep Unicode and readable punctuation, but remove characters forbidden
    // by Windows/macOS filesystems and browser attachment handling.
    $label = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]+/u', '_', $label) ?? '';
    $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '', " .\t\n\r\0\x0B");
    if ($label === '') {
        return basename($masterFilename);
    }
    if (function_exists('mb_substr')) {
        $label = mb_substr($label, 0, 180, 'UTF-8');
    } else {
        $label = substr($label, 0, 180);
    }

    return $label . ($extension !== '' ? '.' . $extension : '');
}

function bandpromo_resolve_download_item(string $root, string $sourceDir, string $target, string $variant, string $filename): array
{
    require_once __DIR__ . '/asset-registry.php';

    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return ['ok' => false, 'filename' => $filename, 'error' => 'Invalid filename'];
    }

    if ($variant === 'master') {
        if ($target !== 'audio') {
            return ['ok' => false, 'filename' => $safe, 'error' => 'Prepared downloads are only available for audio'];
        }
        $master = bandpromo_find_audio_master($root, $safe);
        if (empty($master['exists']) || empty($master['filename'])) {
            return ['ok' => false, 'filename' => $safe, 'error' => 'Prepared copy not found'];
        }
        $path = $root . '/media/audio/master/' . $master['filename'];
        if (!is_file($path)) {
            return ['ok' => false, 'filename' => $safe, 'error' => 'Prepared copy not found'];
        }

        $asset = bandpromo_asset_lookup_from_media_ref($root, $safe)
            ?? bandpromo_asset_lookup_by_master_filename($root, $safe);
        $downloadName = bandpromo_download_current_audio_name(
            is_array($asset) ? $asset : null,
            basename((string) $master['filename'])
        );

        return [
            'ok' => true,
            'filename' => $safe,
            'download_name' => $downloadName,
            'path' => $path,
        ];
    }

    // variant=original: stream original bytes only — never substitute the master.
    $path = $sourceDir . '/' . $safe;
    $downloadName = $safe;

    $asset = bandpromo_asset_lookup_from_media_ref($root, $safe)
        ?? bandpromo_asset_lookup_by_master_filename($root, $safe)
        ?? bandpromo_asset_lookup_by_original_filename($root, $safe);
    if (is_array($asset)) {
        $originalName = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($originalName !== '') {
            $downloadName = $originalName;
            if ($target === 'audio') {
                $path = $root . '/media/audio/original/' . $originalName;
            } elseif ($target === 'sfx') {
                $path = $root . '/media/sfx/original/' . $originalName;
            } elseif (in_array($target, ['illustrations', 'photos', 'video'], true)) {
                require_once __DIR__ . '/visual-master-helpers.php';
                $unified = bandpromo_visual_unified_original_path($root, $originalName);
                $legacy = bandpromo_asset_visual_legacy_original_path($root, $asset);
                if ($unified !== '' && is_file($unified)) {
                    $path = $unified;
                } elseif ($legacy !== '' && is_file($legacy)) {
                    $path = $legacy;
                } else {
                    $path = $sourceDir . '/' . $originalName;
                }
            } else {
                $path = $sourceDir . '/' . $originalName;
            }
        }
    }

    if (!is_file($path)) {
        return ['ok' => false, 'filename' => $safe, 'error' => 'Original not found'];
    }

    return [
        'ok' => true,
        'filename' => $safe,
        'download_name' => $downloadName,
        'path' => $path,
    ];
}

function bandpromo_stream_download_file(string $path, string $downloadName): bool
{
    require_once __DIR__ . '/http-stream.php';

    return bandpromo_http_stream_file($path, $downloadName, [
        'exit' => false,
    ]);
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

function bandpromo_download_execute(
    string $root,
    string $target,
    string $variant,
    array $requestedFiles,
    bool $jsonMode,
    string $statusToken = '',
    string $statusUsername = ''
): void
{
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

    $resolved = [];
    $failures = [];
    foreach ($requestedFiles as $filename) {
        $item = bandpromo_resolve_download_item($root, $sourceDir, $target, $variant, (string) $filename);
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
        $totalBytes = 0;
        foreach ($resolved as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path !== '' && is_file($path)) {
                $totalBytes += (int) filesize($path);
            }
        }

        echo json_encode([
            'ok' => true,
            'mode' => count($resolved) === 1 && $failures === [] ? 'single' : 'archive',
            'resolved_count' => count($resolved),
            'failed_count' => count($failures),
            'total_bytes' => $totalBytes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (count($resolved) === 1 && $failures === []) {
        $downloadName = (string) $resolved[0]['download_name'];
        $completed = bandpromo_stream_download_file($resolved[0]['path'], $downloadName);
        if ($statusToken !== '') {
            bandpromo_download_status_write(
                $root,
                $statusToken,
                $statusUsername,
                $completed ? 'completed' : 'failed',
                $downloadName
            );
        }
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

    $completed = bandpromo_stream_download_file($zipPath, $archiveName);
    if ($statusToken !== '') {
        bandpromo_download_status_write(
            $root,
            $statusToken,
            $statusUsername,
            $completed ? 'completed' : 'failed',
            $archiveName
        );
    }
    exit;
}

$root = dirname(__DIR__);
$jsonMode = bandpromo_download_wants_json();
$downloadToken = trim((string) ($_GET['token'] ?? ''));

bandpromo_ensure_session_started(true);
if (!bandpromo_is_authenticated_session()) {
    bandpromo_download_error(401, 'Unauthorized', $jsonMode || $downloadToken !== '');
}
$downloadUsername = trim((string) ($_SESSION['username'] ?? ''));
if ($downloadUsername === '' || !isAdminUser($downloadUsername)) {
    bandpromo_download_error(403, 'Admin privileges required', $jsonMode || $downloadToken !== '');
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if ($downloadToken !== '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        bandpromo_download_error(405, 'GET required', false);
    }

    $tokenPayload = bandpromo_download_token_consume($root, $downloadToken);
    if ($tokenPayload === null) {
        bandpromo_download_error(404, 'Download link expired or invalid', false);
    }
    if ((string) ($tokenPayload['username'] ?? '') !== $downloadUsername) {
        bandpromo_download_error(403, 'Admin privileges required', false);
    }

    $target = trim((string) ($tokenPayload['target'] ?? ''));
    $variant = trim((string) ($tokenPayload['variant'] ?? 'original'));
    $requestedFiles = is_array($tokenPayload['files'] ?? null) ? $tokenPayload['files'] : [];
    if ($target === '' || $requestedFiles === []) {
        bandpromo_download_error(400, 'Invalid download token', false);
    }

    bandpromo_download_status_write($root, $downloadToken, $downloadUsername, 'started');
    register_shutdown_function(static function () use ($root, $downloadToken, $downloadUsername): void {
        $status = bandpromo_download_status_read($root, $downloadToken);
        if (is_array($status) && ($status['state'] ?? '') === 'started') {
            bandpromo_download_status_write($root, $downloadToken, $downloadUsername, 'failed');
        }
    });

    bandpromo_download_execute(
        $root,
        $target,
        $variant,
        $requestedFiles,
        false,
        $downloadToken,
        $downloadUsername
    );
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

$requestedFiles = array_values(array_unique(array_filter(array_map(static function ($value) {
    return basename((string) $value);
}, $requestedFiles))));
if ($requestedFiles === []) {
    bandpromo_download_error(400, 'No files selected', $jsonMode);
}

if (bandpromo_media_target_dir($target) === null) {
    bandpromo_download_error(400, 'Unknown target', $jsonMode);
}

if ($jsonMode) {
    // Resolve once so preflight can return actionable errors before issuing a token.
    $sourceDir = bandpromo_media_target_dir($target);
    $resolved = [];
    $failures = [];
    foreach ($requestedFiles as $filename) {
        $item = bandpromo_resolve_download_item($root, $sourceDir, $target, $variant, (string) $filename);
        if (!empty($item['ok'])) {
            $resolved[] = $item;
        } else {
            $failures[] = $item;
        }
    }

    if ($resolved === []) {
        bandpromo_download_error(404, $failures[0]['error'] ?? 'Nothing available to download', true);
    }

    if (count($resolved) > 1 && !class_exists('ZipArchive')) {
        bandpromo_download_error(500, 'Multi-file download is unavailable on this server because ZipArchive is missing.', true);
    }

    $totalBytes = 0;
    foreach ($resolved as $item) {
        $path = (string) ($item['path'] ?? '');
        if ($path !== '' && is_file($path)) {
            $totalBytes += (int) filesize($path);
        }
    }

    try {
        $token = bandpromo_download_token_issue($root, $downloadUsername, $target, $variant, $requestedFiles);
    } catch (Throwable $throwable) {
        bandpromo_download_error(500, 'Could not prepare download', true);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'mode' => count($resolved) === 1 && $failures === [] ? 'single' : 'archive',
        'resolved_count' => count($resolved),
        'failed_count' => count($failures),
        'total_bytes' => $totalBytes,
        'download_url' => '/biblioteca/download-media.php?token=' . rawurlencode($token),
        'status_url' => '/biblioteca/download-status.php?token=' . rawurlencode($token),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

bandpromo_download_execute($root, $target, $variant, $requestedFiles, false);
