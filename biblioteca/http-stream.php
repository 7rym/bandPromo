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
 * Compact byte size for transfer / export progress lines.
 */
function bandpromo_transfer_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

/**
 * Prefer store (no deflate) for already-compressed media — faster ZipArchive flushes.
 */
function bandpromo_transfer_zip_prefer_store(string $entryName): bool
{
    $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
    $store = [
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'avif',
        'mp3', 'flac', 'wav', 'm4a', 'ogg', 'opus',
        'mp4', 'webm', 'mov', 'mkv',
        'zip', 'gz', 'br', '7z', 'pcf', 'pbf', 'prp',
    ];

    return in_array($ext, $store, true);
}

/**
 * Order zip entries smallest-first so tiny JSON/JPG files are not appended after multi-GB media.
 *
 * @param array<string, string> $entries relative => absolute
 * @return list<string>
 */
function bandpromo_transfer_zip_entry_order_by_size_asc(array $entries): array
{
    $ranked = [];
    foreach ($entries as $relative => $absolute) {
        $relative = str_replace('\\', '/', (string) $relative);
        if ($relative === '') {
            continue;
        }
        $absolute = (string) $absolute;
        $size = ($absolute !== '' && is_file($absolute)) ? (int) filesize($absolute) : 0;
        $ranked[] = [
            'relative' => $relative,
            'size' => max(0, $size),
        ];
    }

    usort($ranked, static function (array $a, array $b): int {
        if ($a['size'] === $b['size']) {
            return strcmp($a['relative'], $b['relative']);
        }

        return $a['size'] <=> $b['size'];
    });

    $order = [];
    foreach ($ranked as $row) {
        $order[] = $row['relative'];
    }

    return $order;
}

/**
 * Pack files into a zip, flushing to disk in chunks.
 *
 * ZipArchive::addFile() defers all I/O until close(); one final close on a large
 * campaign looks hung at "Closing…" with size 0 B. Flushing after each large
 * entry (or a small batch) keeps Jobs progress/heartbeat alive and grows size.
 *
 * @param array<string, string> $entries relative zip path => absolute filesystem path
 * @param callable|null $onProgress function(string $message, ?int $archiveBytes = null): void
 */
function bandpromo_transfer_zip_pack_entries(
    string $zipPath,
    array $entries,
    $onProgress = null,
    string $verb = 'Archiving'
): void {
    $order = bandpromo_transfer_zip_entry_order_by_size_asc($entries);
    $index = 0;
    $deadline = microtime(true) + 86400;
    $done = bandpromo_transfer_zip_pack_entries_slice(
        $zipPath,
        $order,
        $entries,
        $index,
        $deadline,
        $onProgress,
        $verb
    );
    if (!$done) {
        throw new RuntimeException('Archive packing did not finish.');
    }
}

/**
 * Pack a time-budgeted slice of zip entries (flush after each file for crash resume).
 *
 * @param list<string> $entryOrder
 * @param array<string, string> $entries relative => absolute
 * @return bool true when packing is complete
 */
function bandpromo_transfer_zip_pack_entries_slice(
    string $zipPath,
    array $entryOrder,
    array $entries,
    int &$index,
    float $deadline,
    $onProgress = null,
    string $verb = 'Archiving'
): bool {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available on this host.');
    }
    if ($zipPath === '') {
        throw new InvalidArgumentException('Archive path is required.');
    }

    $total = count($entryOrder);
    if ($total === 0) {
        throw new RuntimeException('Archive has no files to pack.');
    }

    if ($index <= 0) {
        $index = 0;
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }
    }

    $zip = new ZipArchive();
    if ($index === 0) {
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the archive.');
        }
    } else {
        if (!is_file($zipPath) || $zip->open($zipPath) !== true) {
            // Partial archive lost after a host kill — restart packing.
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            $index = 0;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not recreate the archive after a host interrupt.');
            }
            if (is_callable($onProgress)) {
                $onProgress('Archive interrupted — restarting pack…', 0);
            }
        } else {
            // Resync if a kill dropped the last incomplete entry.
            $numFiles = (int) $zip->numFiles;
            if ($numFiles < $index) {
                $index = $numFiles;
            }
        }
    }

    try {
        $sinceFlush = 0;
        $bytesSinceFlush = 0;
        $largeEntryBytes = 512 * 1024;
        $flushThresholdBytes = 4 * 1024 * 1024;
        $maxBatchEntries = 24;

        while ($index < $total) {
            if (microtime(true) >= $deadline && $index > 0) {
                if ($sinceFlush > 0) {
                    if ($zip->close() !== true) {
                        throw new RuntimeException('Could not pause archive write.');
                    }
                    $sinceFlush = 0;
                    $bytesSinceFlush = 0;
                } else {
                    @$zip->close();
                }
                if (is_callable($onProgress) && is_file($zipPath)) {
                    $onProgress(
                        $verb . ' paused · ' . bandpromo_transfer_format_bytes((int) filesize($zipPath)),
                        (int) filesize($zipPath)
                    );
                }

                return false;
            }

            $relative = str_replace('\\', '/', (string) $entryOrder[$index]);
            $absolute = (string) ($entries[$relative] ?? '');
            if ($relative === '' || $absolute === '' || !is_file($absolute)) {
                $index++;
                continue;
            }

            $size = (int) filesize($absolute);
            $displayIndex = $index + 1;
            if (is_callable($onProgress)) {
                $onProgress(
                    $verb . ' ' . $displayIndex . '/' . $total
                    . ' · ' . bandpromo_transfer_format_bytes($size)
                    . ': ' . basename($relative),
                    is_file($zipPath) ? (int) filesize($zipPath) : null
                );
            }
            if (!$zip->addFile($absolute, $relative)) {
                throw new RuntimeException('Could not add file to archive: ' . $relative);
            }
            if (bandpromo_transfer_zip_prefer_store($relative) && method_exists($zip, 'setCompressionName')) {
                @$zip->setCompressionName($relative, ZipArchive::CM_STORE);
            }
            $index++;
            $sinceFlush++;
            $bytesSinceFlush += max(0, $size);

            $isLast = $index >= $total;
            $shouldFlush = $isLast
                || $size >= $largeEntryBytes
                || $bytesSinceFlush >= $flushThresholdBytes
                || $sinceFlush >= $maxBatchEntries
                || microtime(true) >= $deadline;

            if (!$shouldFlush) {
                continue;
            }

            if ($zip->close() !== true) {
                throw new RuntimeException('Could not write archive entries to disk.');
            }
            $sinceFlush = 0;
            $bytesSinceFlush = 0;
            if (is_callable($onProgress) && is_file($zipPath)) {
                $onProgress(
                    $verb . ' ' . $index . '/' . $total . ' on disk · '
                    . bandpromo_transfer_format_bytes((int) filesize($zipPath)),
                    (int) filesize($zipPath)
                );
            }
            if ($isLast) {
                break;
            }
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Could not reopen archive for the next files.');
            }
        }
    } catch (Throwable $e) {
        @$zip->close();
        throw $e;
    }

    if (!is_file($zipPath) || filesize($zipPath) === 0) {
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }
        throw new RuntimeException('Archive was empty after packing.');
    }
    if (is_callable($onProgress)) {
        $onProgress(
            'Archive ready · ' . bandpromo_transfer_format_bytes((int) filesize($zipPath)),
            (int) filesize($zipPath)
        );
    }

    return true;
}

/**
 * SHA-256 with optional byte progress (keeps long hashes alive for Jobs heartbeats).
 *
 * @param callable|null $onBytes function(int $bytesRead, int $totalBytes): void
 */
function bandpromo_transfer_sha256_file_with_progress(string $path, $onBytes = null): string
{
    if ($path === '' || !is_file($path)) {
        return '';
    }

    $totalBytes = (int) filesize($path);
    if ($totalBytes <= 0) {
        return bandpromo_transfer_sha256_file($path);
    }

    // Small files: one-shot hash is fine and cheaper than streaming.
    if ($totalBytes < 2 * 1024 * 1024 || !is_callable($onBytes)) {
        if (is_callable($onBytes)) {
            $onBytes(0, $totalBytes);
        }
        $digest = bandpromo_transfer_sha256_file($path);
        if ($digest !== '' && is_callable($onBytes)) {
            $onBytes($totalBytes, $totalBytes);
        }

        return $digest;
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return '';
    }

    $context = hash_init('sha256');
    $bytesRead = 0;
    $lastReportAt = 0.0;
    $lastReportBytes = 0;
    $chunkSize = 1024 * 1024;
    $reportEveryBytes = 4 * 1024 * 1024;

    try {
        if (is_callable($onBytes)) {
            $onBytes(0, $totalBytes);
        }
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                return '';
            }
            if ($chunk === '') {
                break;
            }
            hash_update($context, $chunk);
            $bytesRead += strlen($chunk);
            $now = microtime(true);
            $dueBySize = ($bytesRead - $lastReportBytes) >= $reportEveryBytes;
            $dueByTime = ($now - $lastReportAt) >= 2.0;
            if (is_callable($onBytes) && ($dueBySize || $dueByTime || $bytesRead >= $totalBytes)) {
                $onBytes($bytesRead, $totalBytes);
                $lastReportAt = $now;
                $lastReportBytes = $bytesRead;
            }
        }
    } finally {
        fclose($handle);
    }

    return strtolower(hash_final($context));
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
    // Do not hash multi-GB archives before streaming — that delayed downloads and
    // could kill the request on limited hosts. Prefer the job's stored digest.
    if ($sha256 === '' && !empty($options['compute_sha256_if_missing'])) {
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
