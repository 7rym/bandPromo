<?php
declare(strict_types=1);

/**
 * Shared admin download streaming (Jobs, media, legacy campaign packages).
 * Do not use for player audio seeking — that stays in audio.php with byte Range.
 */

require_once __DIR__ . '/release-package.php';

/**
 * SHA-256 hex digest of a file (lowercase). Empty string if unreadable.
 */
function bandpromo_transfer_sha256_file(string $path): string
{
    if ($path === '' || !is_file($path)) {
        return '';
    }
    try {
        return bandpromo_release_sha256_file($path);
    } catch (Throwable $throwable) {
        $hash = @hash_file('sha256', $path);

        return is_string($hash) ? strtolower($hash) : '';
    }
}

/**
 * Guess Content-Type for an attachment download name.
 */
function bandpromo_http_stream_content_type(string $downloadName): string
{
    $ext = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
    return match ($ext) {
        'pcf', 'prp', 'pbf' => 'application/octet-stream',
        'zip' => 'application/zip',
        'flac' => 'audio/flac',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'mkv' => 'video/x-matroska',
        'json' => 'application/json; charset=utf-8',
        'csv' => 'text/csv; charset=utf-8',
        'jsonl', 'ndjson' => 'application/x-ndjson; charset=utf-8',
        default => 'application/octet-stream',
    };
}

/**
 * Stream a file as an attachment.
 *
 * @param array{
 *   content_type?: string,
 *   sha256?: string,
 *   accept_ranges?: bool,
 *   exit?: bool
 * } $options
 * @return bool true when the full Content-Length was sent (or exit was requested)
 */
function bandpromo_http_stream_file(string $path, string $downloadName, array $options = []): bool
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Download file is missing.');
    }

    $size = filesize($path);
    if ($size === false || $size < 0) {
        throw new RuntimeException('Could not read download file size.');
    }
    $size = (int) $size;

    $safeName = str_replace(["\r", "\n"], '', trim($downloadName));
    $safeName = str_replace('"', '', $safeName);
    if ($safeName === '') {
        $safeName = 'bandpromo-download.bin';
    }

    $contentType = trim((string) ($options['content_type'] ?? ''));
    if ($contentType === '') {
        $contentType = bandpromo_http_stream_content_type($safeName);
    }

    $sha256 = strtolower(trim((string) ($options['sha256'] ?? '')));
    if ($sha256 === '') {
        $sha256 = bandpromo_transfer_sha256_file($path);
    }

    $acceptRanges = array_key_exists('accept_ranges', $options)
        ? !empty($options['accept_ranges'])
        : false;
    $doExit = !array_key_exists('exit', $options) || !empty($options['exit']);

    @set_time_limit(0);
    ignore_user_abort(true);
    if (function_exists('ini_set')) {
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Partial / resumed downloads via Range routinely truncate archives on the
    // PHP built-in server and some embedded browsers (ZipArchive ER_TRUNCATED_ZIP).
    if (!$acceptRanges && isset($_SERVER['HTTP_RANGE']) && trim((string) $_SERVER['HTTP_RANGE']) !== '') {
        // Ignore Range; always send the full body with 200.
    }

    http_response_code(200);
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . (string) $size);
    $dispositionName = rawurlencode($safeName);
    header(
        'Content-Disposition: attachment; filename="' . str_replace('"', '', $safeName)
        . '"; filename*=UTF-8\'\'' . $dispositionName
    );
    header('Accept-Ranges: ' . ($acceptRanges ? 'bytes' : 'none'));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: public');
    if ($sha256 !== '' && preg_match('/^[a-f0-9]{64}$/', $sha256) === 1) {
        header('X-Checksum-SHA256: ' . $sha256);
    }

    $sent = @readfile($path);
    $sentBytes = is_int($sent) ? $sent : 0;
    if ($sent === false || $sentBytes !== $size) {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            if ($doExit) {
                exit;
            }

            return false;
        }
        try {
            $offset = $sentBytes > 0 ? $sentBytes : 0;
            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                if ($doExit) {
                    exit;
                }

                return false;
            }
            $remaining = $size - $offset;
            $chunkSize = 1024 * 1024;
            while ($remaining > 0 && !feof($handle)) {
                $read = fread($handle, (int) min($chunkSize, $remaining));
                if ($read === false || $read === '') {
                    break;
                }
                echo $read;
                $remaining -= strlen($read);
                $sentBytes += strlen($read);
                if (function_exists('flush')) {
                    flush();
                }
            }
        } finally {
            fclose($handle);
        }
    }

    $ok = $sentBytes >= $size;
    if ($doExit) {
        exit;
    }

    return $ok;
}
