<?php
declare(strict_types=1);

/**
 * Shared chunked upload assemble (Files, PCF, PBF, site-backup ZIP).
 *
 * Contract (admin JS bandpromoUploadChunked):
 *   chunk, filename, chunk_index, total_chunks, upload_id, file_size[, expected_sha256]
 * Assemble only on the last chunk index; reject size / optional SHA-256 mismatches.
 */

require_once __DIR__ . '/http-stream.php';

/**
 * Durable staging under data/upload_tmp (inside open_basedir).
 */
function bandpromo_chunked_upload_staging_dir(string $root, string $fallbackLeaf = 'bandpromo-chunk-upload'): string
{
    $preferred = rtrim($root, "\\/") . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'upload_tmp';
    if (!is_dir($preferred) && !mkdir($preferred, 0750, true) && !is_dir($preferred)) {
        $preferred = '';
    }
    if ($preferred !== '' && is_writable($preferred)) {
        return $preferred;
    }

    $base = rtrim((string) sys_get_temp_dir(), "\\/");
    $dir = $base . DIRECTORY_SEPARATOR . $fallbackLeaf;
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create chunked-upload staging directory.');
    }

    return $dir;
}

function bandpromo_chunked_upload_sanitize_upload_id(string $uploadId): string
{
    $uploadId = preg_replace('/[^a-zA-Z0-9._-]/', '', $uploadId) ?? '';
    if ($uploadId === '' || strlen($uploadId) < 8 || strlen($uploadId) > 80) {
        throw new InvalidArgumentException('Missing or invalid upload_id for chunked import.');
    }

    return $uploadId;
}

/**
 * @return array{chunk_index: int, total_chunks: int, filename: string, extension: string, upload_id: string, expected_size: int, expected_sha256: string}
 */
function bandpromo_chunked_upload_parse_request(array $post): array
{
    $chunkIndex = (int) ($post['chunk_index'] ?? -1);
    $totalChunks = (int) ($post['total_chunks'] ?? 0);
    $filename = basename((string) ($post['filename'] ?? ''));
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $uploadId = bandpromo_chunked_upload_sanitize_upload_id((string) ($post['upload_id'] ?? ''));
    $expectedSize = (int) ($post['file_size'] ?? 0);
    $expectedSha256 = strtolower(trim((string) ($post['expected_sha256'] ?? '')));

    if ($filename === '') {
        throw new InvalidArgumentException('filename is required for chunked upload.');
    }
    if ($totalChunks < 1 || $totalChunks > 100000 || $chunkIndex < 0 || $chunkIndex >= $totalChunks) {
        throw new InvalidArgumentException('Invalid chunk index.');
    }
    if ($expectedSha256 !== '' && preg_match('/^[a-f0-9]{64}$/', $expectedSha256) !== 1) {
        throw new InvalidArgumentException('expected_sha256 must be a 64-character hex digest.');
    }

    return [
        'chunk_index' => $chunkIndex,
        'total_chunks' => $totalChunks,
        'filename' => $filename,
        'extension' => $extension,
        'upload_id' => $uploadId,
        'expected_size' => $expectedSize,
        'expected_sha256' => $expectedSha256,
    ];
}

function bandpromo_chunked_upload_cleanup_parts(string $tmpDir, string $uploadId, int $totalChunks): void
{
    for ($i = 0; $i < $totalChunks; $i++) {
        $partPath = $tmpDir . DIRECTORY_SEPARATOR . $uploadId . '.part' . $i;
        if (is_file($partPath)) {
            @unlink($partPath);
        }
    }
}

/**
 * Probe whether a path looks like a zip-backed archive (PCF/PBF/ZIP).
 */
function bandpromo_chunked_upload_zip_open_error(string $zipPath, string $label = 'archive'): string
{
    $size = is_file($zipPath) ? (int) filesize($zipPath) : 0;
    $header = '';
    if (is_file($zipPath) && $size >= 4) {
        $handle = fopen($zipPath, 'rb');
        if ($handle !== false) {
            $header = (string) fread($handle, 4);
            fclose($handle);
        }
    }
    if ($header !== '' && $header !== "PK\x03\x04" && $header !== "PK\x05\x06" && $header !== "PK\x07\x08") {
        $hex = strtoupper(bin2hex($header));

        return 'The uploaded file is not a valid ' . $label . ' (header ' . $hex . ', size ' . $size
            . ' bytes). A chunk was likely truncated or replaced with an error page — retry the upload.';
    }

    if (!class_exists('ZipArchive')) {
        return '';
    }

    $zip = new ZipArchive();
    $status = $zip->open($zipPath);
    if ($status === true) {
        $zip->close();

        return '';
    }
    $statusCode = is_int($status) ? (string) $status : 'unknown';
    if (
        (defined('ZipArchive::ER_TRUNCATED_ZIP') && $status === ZipArchive::ER_TRUNCATED_ZIP)
        || $status === 35
    ) {
        return 'The uploaded ' . $label . ' is truncated (incomplete transfer, status 35, size '
            . $size . ' bytes). Re-download or re-upload and confirm the file size matches before retrying.';
    }
    $hint = $size < 100
        ? 'File is nearly empty — the upload likely did not finish.'
        : 'Chunk assembly did not produce a readable ' . $label . ' (status ' . $statusCode . '). Retry the upload.';

    return 'Could not open the ' . $label . ' (status ' . $statusCode . ', size ' . $size . ' bytes). ' . $hint;
}

/**
 * Receive one chunk. On non-final chunks returns status=partial.
 * On final chunk assembles to $assembledPath and returns status=assembled.
 *
 * @param array{chunk_index: int, total_chunks: int, filename: string, extension: string, upload_id: string, expected_size: int, expected_sha256: string} $meta
 * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $chunkFile $_FILES['chunk']
 * @return array{
 *   ok: true,
 *   status: 'partial'|'assembled',
 *   received?: int,
 *   total?: int,
 *   upload_id: string,
 *   assembled_path?: string,
 *   assembled_size?: int,
 *   sha256?: string,
 *   filename: string,
 *   extension: string
 * }
 */
function bandpromo_chunked_upload_receive(
    string $tmpDir,
    array $meta,
    array $chunkFile,
    string $assembledExtension = ''
): array {
    $chunkIndex = (int) $meta['chunk_index'];
    $totalChunks = (int) $meta['total_chunks'];
    $uploadId = (string) $meta['upload_id'];
    $expectedSize = (int) $meta['expected_size'];
    $expectedSha256 = (string) $meta['expected_sha256'];
    $filename = (string) $meta['filename'];
    $extension = (string) $meta['extension'];
    if ($assembledExtension === '') {
        $assembledExtension = $extension !== '' ? $extension : 'bin';
    }

    $error = (int) ($chunkFile['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Chunk upload error: ' . $error);
    }
    $tmpName = (string) ($chunkFile['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid chunk upload.');
    }

    $assembledPath = $tmpDir . DIRECTORY_SEPARATOR . $uploadId . '.assembled.' . $assembledExtension;
    // Append each chunk as it arrives — avoids a multi-GB concatenate on the final request.
    if ($chunkIndex === 0 && is_file($assembledPath)) {
        @unlink($assembledPath);
    }
    $out = fopen($assembledPath, $chunkIndex === 0 ? 'wb' : 'ab');
    if ($out === false) {
        throw new RuntimeException('Could not open assembled upload for writing.');
    }
    $in = fopen($tmpName, 'rb');
    if ($in === false) {
        fclose($out);
        throw new RuntimeException('Could not read uploaded chunk.');
    }
    $copied = stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);
    if ($copied === false) {
        throw new RuntimeException('Could not append chunk ' . $chunkIndex . '.');
    }

    if ($chunkIndex !== $totalChunks - 1) {
        return [
            'ok' => true,
            'status' => 'partial',
            'received' => $chunkIndex + 1,
            'total' => $totalChunks,
            'upload_id' => $uploadId,
            'filename' => $filename,
            'extension' => $extension,
        ];
    }

    @set_time_limit(0);
    $assembledSize = is_file($assembledPath) ? (int) filesize($assembledPath) : 0;
    if ($expectedSize > 0 && $assembledSize !== $expectedSize) {
        @unlink($assembledPath);
        throw new RuntimeException(
            'Assembled package size mismatch (got '
            . $assembledSize . ' bytes, expected ' . $expectedSize . '). Retry the upload.'
        );
    }

    require_once __DIR__ . '/http-stream.php';
    $sha256 = '';
    // Never SHA multi-GB assemblies inline — that timed out the final chunk (Failed to fetch).
    $maxInlineShaBytes = 64 * 1024 * 1024;
    if ($expectedSha256 !== '') {
        if ($assembledSize > $maxInlineShaBytes) {
            @unlink($assembledPath);
            throw new RuntimeException(
                'This host cannot verify a '
                . bandpromo_transfer_format_bytes($assembledSize)
                . ' upload checksum in one request. Import without an expected SHA, or raise host timeouts.'
            );
        }
        $sha256 = bandpromo_transfer_sha256_file($assembledPath);
        if ($sha256 !== $expectedSha256) {
            @unlink($assembledPath);
            throw new RuntimeException(
                'Assembled package checksum mismatch (integrity check failed). Re-download the source file and retry.'
            );
        }
    } elseif ($assembledSize > 0 && $assembledSize <= $maxInlineShaBytes) {
        $sha256 = bandpromo_transfer_sha256_file($assembledPath);
    }

    return [
        'ok' => true,
        'status' => 'assembled',
        'upload_id' => $uploadId,
        'assembled_path' => $assembledPath,
        'assembled_size' => $assembledSize,
        'sha256' => $sha256,
        'filename' => $filename,
        'extension' => $extension,
    ];
}

/**
 * Build per-path digests for package manifests.
 *
 * @param array<string, string> $paths relative => absolute
 * @param callable|null $onProgress optional string progress message callback
 * @return array<string, array{sha256: string, size: int}>
 */
function bandpromo_transfer_file_digests(array $paths, $onProgress = null): array
{
    require_once __DIR__ . '/http-stream.php';

    $digests = [];
    $pathOrder = array_keys($paths);
    $index = 0;
    $deadline = microtime(true) + 86400;
    bandpromo_transfer_file_digests_slice($pathOrder, $paths, $digests, $index, $deadline, $onProgress);

    return $digests;
}

/**
 * Checksum a time-budgeted slice of paths (shared-host safe).
 *
 * @param list<string> $pathOrder
 * @param array<string, string> $paths
 * @param array<string, array{sha256: string, size: int}> $digests
 * @return bool true when all paths are done
 */
function bandpromo_transfer_file_digests_slice(
    array $pathOrder,
    array $paths,
    array &$digests,
    int &$index,
    float $deadline,
    $onProgress = null
): bool {
    require_once __DIR__ . '/http-stream.php';

    $total = count($pathOrder);
    while ($index < $total) {
        if (microtime(true) >= $deadline) {
            return false;
        }
        $relative = str_replace('\\', '/', (string) $pathOrder[$index]);
        $absolute = (string) ($paths[$relative] ?? '');
        $index++;
        if ($relative === '' || $absolute === '' || !is_file($absolute)) {
            continue;
        }
        if (isset($digests[$relative]['sha256']) && (string) $digests[$relative]['sha256'] !== '') {
            continue;
        }
        $baseName = basename($relative);
        $prefix = 'Checksum ' . $index . '/' . $total;
        if (is_callable($onProgress)) {
            $onProgress($prefix . ': ' . $baseName);
        }
        $sha = bandpromo_transfer_sha256_file_with_progress(
            $absolute,
            static function (int $bytesRead, int $totalBytes) use ($onProgress, $prefix, $baseName, $deadline): void {
                if (!is_callable($onProgress) || $totalBytes <= 0) {
                    return;
                }
                if (microtime(true) >= $deadline) {
                    // Keep hashing this file; progress only.
                }
                $onProgress(
                    $prefix
                    . ' · '
                    . bandpromo_transfer_format_bytes($bytesRead)
                    . ' / '
                    . bandpromo_transfer_format_bytes($totalBytes)
                    . ': '
                    . $baseName
                );
            }
        );
        if ($sha === '') {
            throw new RuntimeException('Could not checksum packaged file: ' . $relative);
        }
        $digests[$relative] = [
            'sha256' => $sha,
            'size' => (int) filesize($absolute),
        ];
    }

    return true;
}

/**
 * Verify extracted package files against manifest digests.
 *
 * @param array<string, mixed> $digests
 */
function bandpromo_transfer_verify_extracted_digests(string $packageDir, array $digests): void
{
    if ($digests === []) {
        return;
    }
    $packageDir = rtrim($packageDir, "\\/");
    foreach ($digests as $relative => $meta) {
        $relative = str_replace('\\', '/', trim((string) $relative));
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('Archive integrity check failed: invalid path in digests.');
        }
        if (!is_array($meta)) {
            continue;
        }
        $expectedSha = strtolower(trim((string) ($meta['sha256'] ?? '')));
        $expectedSize = (int) ($meta['size'] ?? 0);
        if ($expectedSha === '') {
            continue;
        }
        $absolute = $packageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($absolute)) {
            throw new RuntimeException('Archive integrity check failed: missing file ' . $relative . '.');
        }
        $actualSize = (int) filesize($absolute);
        if ($expectedSize > 0 && $actualSize !== $expectedSize) {
            throw new RuntimeException(
                'Archive integrity check failed: size mismatch for ' . $relative . '.'
            );
        }
        $actualSha = bandpromo_transfer_sha256_file($absolute);
        if ($actualSha !== $expectedSha) {
            throw new RuntimeException(
                'Archive integrity check failed: checksum mismatch for ' . $relative . '.'
            );
        }
    }
}
