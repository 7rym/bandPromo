<?php
declare(strict_types=1);

/**
 * Portable Campaign File (.pcf) import. Legacy .prp is accepted without advertising it.
 *
 * Single-file mode: POST package=… (small archives)
 * Chunked mode (preferred for large campaigns): POST with
 *   chunk, filename, chunk_index, total_chunks, upload_id, collision, csrf_token
 */

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/campaign-package.php';
require_once __DIR__ . '/campaign-storage.php';

header('Content-Type: application/json; charset=utf-8');

$importCompleted = false;
register_shutdown_function(static function () use (&$importCompleted): void {
    if ($importCompleted) {
        return;
    }
    $err = error_get_last();
    if (!is_array($err)) {
        return;
    }
    $type = (int) ($err['type'] ?? 0);
    if (!in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $message = trim((string) ($err['message'] ?? 'Import aborted.'));
    if ($message === '') {
        $message = 'Import aborted.';
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Import aborted: ' . $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

/**
 * @return never
 */
function bandpromo_campaign_import_json_exit(array $payload, int $status = 200): void
{
    $GLOBALS['importCompleted'] = true;
    if ($status !== 200) {
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bandpromo_campaign_import_normalize_collision(string $collision): string
{
    $collision = strtolower(trim($collision));
    if ($collision === 'skip-existing') {
        $collision = 'skip';
    }
    if (!in_array($collision, ['refuse', 'overwrite', 'skip', 'allocate'], true)) {
        return 'refuse';
    }

    return $collision;
}

/**
 * @return array{
 *   ok: true,
 *   release_id: string,
 *   message: string,
 *   imported_files: int,
 *   collision: string,
 *   releases: list,
 *   build_required?: bool,
 *   queue_deliverables?: bool,
 *   build_required_state?: mixed
 * }
 */
function bandpromo_campaign_import_run(string $root, string $zipPath, string $originalName, string $collision): array
{
    $result = bandpromo_campaign_import_from_zip($root, $zipPath, [
        'mode' => 'operator',
        'allow_demo_overwrite' => false,
        'set_active_brand' => false,
        'collision' => $collision,
    ]);

    bandpromo_admin_audit_log('release_package_imported', [
        'target_type' => 'release',
        'target_id' => (string) ($result['release_id'] ?? ''),
        'status' => 'ok',
        'data' => [
            'imported_files' => (int) ($result['imported_files'] ?? 0),
            'filename' => $originalName,
            'collision' => (string) ($result['collision'] ?? $collision),
            'mode' => isset($_POST['chunk_index']) ? 'chunked' : 'single',
            'queue_deliverables' => !empty($result['queue_deliverables']),
        ],
    ]);

    $payload = [
        'ok' => true,
        'release_id' => (string) ($result['release_id'] ?? ''),
        'message' => (string) ($result['message'] ?? 'Release package imported.'),
        'imported_files' => (int) ($result['imported_files'] ?? 0),
        'collision' => (string) ($result['collision'] ?? $collision),
        'releases' => bandpromo_campaign_admin_registry_entries($root),
    ];
    if (!empty($result['build_required'])) {
        $payload['build_required'] = true;
    }
    if (!empty($result['queue_deliverables'])) {
        $payload['queue_deliverables'] = true;
    }
    if (!empty($result['image_delivery_ok'])) {
        $payload['image_delivery_ok'] = true;
    }
    if (!empty($result['deliverables_started'])) {
        $payload['deliverables_started'] = true;
    }
    if (!empty($result['deliverables_warning'])) {
        $payload['deliverables_warning'] = (string) $result['deliverables_warning'];
    }
    if (array_key_exists('build_required_state', $result)) {
        $payload['build_required_state'] = $result['build_required_state'];
    }

    return $payload;
}

function bandpromo_campaign_import_cleanup_parts(string $tmpDir, string $uploadId, int $totalChunks): void
{
    for ($i = 0; $i < $totalChunks; $i++) {
        $partPath = $tmpDir . DIRECTORY_SEPARATOR . $uploadId . '.part' . $i;
        if (is_file($partPath)) {
            @unlink($partPath);
        }
    }
}

/**
 * Stage PRP chunks under the install's data/upload_tmp (durable, inside open_basedir).
 * Fall back to sys temp only when the install path is not writable.
 */
function bandpromo_campaign_import_chunk_staging_dir(string $root): string
{
    $preferred = rtrim($root, "\\/") . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'upload_tmp';
    if (!is_dir($preferred) && !mkdir($preferred, 0750, true) && !is_dir($preferred)) {
        $preferred = '';
    }
    if ($preferred !== '' && is_writable($preferred)) {
        return $preferred;
    }

    $base = rtrim((string) sys_get_temp_dir(), "\\/");
    $dir = $base . DIRECTORY_SEPARATOR . 'bandpromo-prp-upload';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create campaign-file upload staging directory.');
    }

    return $dir;
}

function bandpromo_campaign_import_zip_open_error(string $zipPath): string
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

        return 'The uploaded file is not a valid .pcf (header ' . $hex . ', size ' . $size
            . ' bytes). A chunk was likely truncated or replaced with an error page — retry the import.';
    }

    $zip = new ZipArchive();
    $status = $zip->open($zipPath);
    if ($status === true) {
        $zip->close();

        return '';
    }
    $statusCode = is_int($status) ? (string) $status : 'unknown';
    $hint = $size < 100
        ? 'File is nearly empty — the upload likely did not finish.'
        : 'Chunk assembly did not produce a readable campaign file (status ' . $statusCode . '). Retry the import.';

    return 'Could not open the campaign file (status '
        . $statusCode . ', size ' . $size . ' bytes). ' . $hint;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bandpromo_campaign_import_json_exit(['ok' => false, 'error' => 'POST required.'], 405);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    $postMax = (string) ini_get('post_max_size');
    $uploadMax = (string) ini_get('upload_max_filesize');
    bandpromo_campaign_import_json_exit([
        'ok' => false,
        'error' => 'Package is larger than this host allows (post_max_size='
            . $postMax . ', upload_max_filesize=' . $uploadMax
            . '). Use chunked import (admin) or raise those limits.',
    ], 413);
}

$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
if (!validate_csrf_token($csrfToken)) {
    bandpromo_campaign_import_json_exit([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ], 403);
}

$root = dirname(__DIR__);
$collision = bandpromo_campaign_import_normalize_collision((string) ($_POST['collision'] ?? 'refuse'));

// ─── Chunked upload mode (2 MB parts from admin, same as Files) ───────────────
if (isset($_POST['chunk_index'], $_POST['filename'])) {
    $chunkIndex = (int) $_POST['chunk_index'];
    $totalChunks = (int) $_POST['total_chunks'];
    $filename = basename((string) $_POST['filename']);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $uploadId = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($_POST['upload_id'] ?? ''));
    $expectedSize = (int) ($_POST['file_size'] ?? 0);

    if ($filename === '' || !bandpromo_pcf_is_campaign_file_extension($extension)) {
        bandpromo_campaign_import_json_exit([
            'ok' => false,
            'error' => bandpromo_pcf_operator_extension_error(),
        ], 400);
    }
    if ($uploadId === '' || strlen($uploadId) < 8 || strlen($uploadId) > 80) {
        bandpromo_campaign_import_json_exit([
            'ok' => false,
            'error' => 'Missing or invalid upload_id for chunked import.',
        ], 400);
    }
    if ($totalChunks < 1 || $totalChunks > 100000 || $chunkIndex < 0 || $chunkIndex >= $totalChunks) {
        bandpromo_campaign_import_json_exit(['ok' => false, 'error' => 'Invalid chunk index.'], 400);
    }
    if (empty($_FILES['chunk']) || (int) ($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = (int) ($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE);
        bandpromo_campaign_import_json_exit([
            'ok' => false,
            'error' => 'Chunk upload error: ' . $code,
        ], 400);
    }

    try {
        $tmpDir = bandpromo_campaign_import_chunk_staging_dir($root);
    } catch (Throwable $throwable) {
        bandpromo_campaign_import_json_exit(['ok' => false, 'error' => $throwable->getMessage()], 500);
    }

    $chunkPath = $tmpDir . DIRECTORY_SEPARATOR . $uploadId . '.part' . $chunkIndex;
    if (!move_uploaded_file((string) $_FILES['chunk']['tmp_name'], $chunkPath)) {
        bandpromo_campaign_import_json_exit(['ok' => false, 'error' => 'Could not save chunk.'], 500);
    }

    // Only the final chunk assembles — avoids races if an earlier part is retried.
    if ($chunkIndex !== $totalChunks - 1) {
        bandpromo_campaign_import_json_exit([
            'ok' => true,
            'status' => 'partial',
            'received' => $chunkIndex + 1,
            'total' => $totalChunks,
            'upload_id' => $uploadId,
        ]);
    }

    $partsPresent = 0;
    $partsBytes = 0;
    for ($i = 0; $i < $totalChunks; $i++) {
        $partPath = $tmpDir . DIRECTORY_SEPARATOR . $uploadId . '.part' . $i;
        if (!is_file($partPath)) {
            continue;
        }
        $partsPresent++;
        $partsBytes += (int) filesize($partPath);
    }

    if ($partsPresent < $totalChunks) {
        bandpromo_campaign_import_cleanup_parts($tmpDir, $uploadId, $totalChunks);
        bandpromo_campaign_import_json_exit([
            'ok' => false,
            'error' => 'Upload finished but chunks are incomplete ('
                . $partsPresent . '/' . $totalChunks . '). Retry the import.',
        ], 400);
    }

    if ($expectedSize > 0 && $partsBytes !== $expectedSize) {
        bandpromo_campaign_import_cleanup_parts($tmpDir, $uploadId, $totalChunks);
        bandpromo_campaign_import_json_exit([
            'ok' => false,
            'error' => 'Assembled size mismatch (got '
                . $partsBytes . ' bytes, expected ' . $expectedSize
                . '). The .pcf may be truncated — re-download and retry.',
        ], 400);
    }

    @set_time_limit(0);
    $assembledPath = $tmpDir . DIRECTORY_SEPARATOR . $uploadId . '.assembled.' . $extension;
    $out = fopen($assembledPath, 'wb');
    if ($out === false) {
        bandpromo_campaign_import_cleanup_parts($tmpDir, $uploadId, $totalChunks);
        bandpromo_campaign_import_json_exit(['ok' => false, 'error' => 'Could not assemble package.'], 500);
    }
    try {
        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = $tmpDir . DIRECTORY_SEPARATOR . $uploadId . '.part' . $i;
            $in = fopen($partPath, 'rb');
            if ($in === false) {
                throw new RuntimeException('Missing chunk part ' . $i . '.');
            }
            $copied = stream_copy_to_stream($in, $out);
            fclose($in);
            if ($copied === false) {
                throw new RuntimeException('Could not copy chunk part ' . $i . '.');
            }
            @unlink($partPath);
        }
    } catch (Throwable $assembleError) {
        fclose($out);
        @unlink($assembledPath);
        bandpromo_campaign_import_cleanup_parts($tmpDir, $uploadId, $totalChunks);
        bandpromo_campaign_import_json_exit([
            'ok' => false,
            'error' => $assembleError->getMessage(),
        ], 500);
    }
    fclose($out);

    $assembledSize = (int) filesize($assembledPath);
    if ($expectedSize > 0 && $assembledSize !== $expectedSize) {
        @unlink($assembledPath);
        bandpromo_campaign_import_json_exit([
            'ok' => false,
            'error' => 'Assembled package size mismatch (got '
                . $assembledSize . ' bytes, expected ' . $expectedSize . '). Retry the import.',
        ], 400);
    }

    $openError = bandpromo_campaign_import_zip_open_error($assembledPath);
    if ($openError !== '') {
        @unlink($assembledPath);
        bandpromo_campaign_import_json_exit(['ok' => false, 'error' => $openError], 400);
    }

    try {
        $payload = bandpromo_campaign_import_run($root, $assembledPath, $filename, $collision);
        @unlink($assembledPath);
        bandpromo_campaign_import_json_exit($payload);
    } catch (Throwable $throwable) {
        @unlink($assembledPath);
        bandpromo_campaign_import_json_exit([
            'ok' => false,
            'error' => $throwable->getMessage(),
        ], 400);
    }
}

// ─── Single-file mode (small packages / scripts) ──────────────────────────────
$file = $_FILES['package'] ?? null;
if (!is_array($file)) {
    bandpromo_campaign_import_json_exit([
        'ok' => false,
        'error' => 'Upload a Portable Campaign File (.pcf), or use chunked upload fields.',
    ], 400);
}

$uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
    $postMax = (string) ini_get('post_max_size');
    $uploadMax = (string) ini_get('upload_max_filesize');
    $error = match ($uploadError) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Package exceeds PHP upload limits (upload_max_filesize='
            . $uploadMax . ', post_max_size=' . $postMax . '). Use the admin chunked importer.',
        UPLOAD_ERR_PARTIAL => 'Upload was interrupted before the package finished transferring.',
        UPLOAD_ERR_NO_FILE => 'Upload a Portable Campaign File (.pcf).',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded package.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
        default => 'Upload failed (error code ' . $uploadError . ').',
    };
    bandpromo_campaign_import_json_exit(['ok' => false, 'error' => $error], 400);
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$originalName = (string) ($file['name'] ?? 'package.pcf');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    bandpromo_campaign_import_json_exit(['ok' => false, 'error' => 'Invalid upload.'], 400);
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!bandpromo_pcf_is_campaign_file_extension($extension)) {
    bandpromo_campaign_import_json_exit([
        'ok' => false,
        'error' => bandpromo_pcf_operator_extension_error(),
    ], 400);
}

@set_time_limit(0);

try {
    $payload = bandpromo_campaign_import_run($root, $tmpName, $originalName, $collision);
    bandpromo_campaign_import_json_exit($payload);
} catch (Throwable $throwable) {
    bandpromo_campaign_import_json_exit([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], 400);
}
