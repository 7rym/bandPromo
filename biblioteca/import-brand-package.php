<?php
declare(strict_types=1);

/**
 * Portable Brand File (.pbf) import.
 *
 * Single-file mode: POST package=…
 * Chunked mode (preferred for large brands): POST with
 *   chunk, filename, chunk_index, total_chunks, upload_id, collision, csrf_token
 */

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/brand-package.php';
require_once __DIR__ . '/brand-storage.php';

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
function bandpromo_brand_import_json_exit(array $payload, int $status = 200): void
{
    $GLOBALS['importCompleted'] = true;
    if ($status !== 200) {
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bandpromo_brand_import_normalize_collision(string $collision): string
{
    $collision = strtolower(trim($collision));
    if ($collision === 'skip-existing') {
        $collision = 'skip';
    }
    if ($collision === 'asnew' || $collision === 'as_new') {
        $collision = 'allocate';
    }
    if (!in_array($collision, ['refuse', 'overwrite', 'skip', 'allocate'], true)) {
        return 'refuse';
    }

    return $collision;
}

/**
 * @return array{
 *   ok: true,
 *   brand_id: string,
 *   message: string,
 *   imported_files: int,
 *   collision: string,
 *   brands: list,
 *   build_required?: bool,
 *   queue_deliverables?: bool,
 *   build_required_state?: mixed
 * }
 */
function bandpromo_brand_import_run(string $root, string $zipPath, string $originalName, string $collision): array
{
    $result = bandpromo_brand_import_from_zip($root, $zipPath, [
        'collision' => $collision,
    ]);

    bandpromo_admin_audit_log('brand_package_imported', [
        'target_type' => 'brand',
        'target_id' => (string) ($result['brand_id'] ?? ''),
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
        'brand_id' => (string) ($result['brand_id'] ?? ''),
        'message' => (string) ($result['message'] ?? 'Portable Brand File imported.'),
        'imported_files' => (int) ($result['imported_files'] ?? 0),
        'collision' => (string) ($result['collision'] ?? $collision),
        'brands' => bandpromo_brand_registry_entries($root),
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

function bandpromo_brand_import_cleanup_parts(string $tmpDir, string $uploadId, int $totalChunks): void
{
    for ($i = 0; $i < $totalChunks; $i++) {
        $partPath = $tmpDir . DIRECTORY_SEPARATOR . $uploadId . '.part' . $i;
        if (is_file($partPath)) {
            @unlink($partPath);
        }
    }
}

function bandpromo_brand_import_chunk_staging_dir(string $root): string
{
    $preferred = rtrim($root, "\\/") . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'upload_tmp';
    if (!is_dir($preferred) && !mkdir($preferred, 0750, true) && !is_dir($preferred)) {
        $preferred = '';
    }
    if ($preferred !== '' && is_writable($preferred)) {
        return $preferred;
    }

    $base = rtrim((string) sys_get_temp_dir(), "\\/");
    $dir = $base . DIRECTORY_SEPARATOR . 'bandpromo-pbf-upload';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create brand-file upload staging directory.');
    }

    return $dir;
}

function bandpromo_brand_import_zip_open_error(string $zipPath): string
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

        return 'The uploaded file is not a valid .pbf (header ' . $hex . ', size ' . $size
            . ' bytes). A chunk was likely truncated or replaced with an error page — retry the import.';
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
        return 'The uploaded .pbf is truncated (incomplete download, status 35, size '
            . $size . ' bytes). Re-download from Jobs and confirm the file size matches the Ready job before importing.';
    }
    $hint = $size < 100
        ? 'File is nearly empty — the upload likely did not finish.'
        : 'Chunk assembly did not produce a readable brand file (status ' . $statusCode . '). Retry the import.';

    return 'Could not open the brand file (status '
        . $statusCode . ', size ' . $size . ' bytes). ' . $hint;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bandpromo_brand_import_json_exit(['ok' => false, 'error' => 'POST required.'], 405);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    $postMax = (string) ini_get('post_max_size');
    $uploadMax = (string) ini_get('upload_max_filesize');
    bandpromo_brand_import_json_exit([
        'ok' => false,
        'error' => 'Package is larger than this host allows (post_max_size='
            . $postMax . ', upload_max_filesize=' . $uploadMax
            . '). Use chunked import (admin) or raise those limits.',
    ], 413);
}

$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
if (!validate_csrf_token($csrfToken)) {
    bandpromo_brand_import_json_exit([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ], 403);
}

$root = dirname(__DIR__);
$collision = bandpromo_brand_import_normalize_collision((string) ($_POST['collision'] ?? 'refuse'));

require_once __DIR__ . '/chunked-upload.php';

if (isset($_POST['chunk_index'], $_POST['filename'])) {
    try {
        $meta = bandpromo_chunked_upload_parse_request($_POST);
        if ($meta['filename'] === '' || !bandpromo_pbf_is_brand_file_extension($meta['extension'])) {
            throw new InvalidArgumentException(bandpromo_pbf_operator_extension_error());
        }
        $tmpDir = bandpromo_chunked_upload_staging_dir($root, 'bandpromo-pbf-upload');
        $chunkFile = is_array($_FILES['chunk'] ?? null) ? $_FILES['chunk'] : [];
        $received = bandpromo_chunked_upload_receive($tmpDir, $meta, $chunkFile, 'pbf');
        if (($received['status'] ?? '') === 'partial') {
            bandpromo_brand_import_json_exit($received);
        }
        $assembledPath = (string) ($received['assembled_path'] ?? '');
        $openError = bandpromo_chunked_upload_zip_open_error($assembledPath, '.pbf');
        if ($openError !== '') {
            @unlink($assembledPath);
            throw new RuntimeException($openError);
        }
        $payload = bandpromo_brand_import_run($root, $assembledPath, $meta['filename'], $collision);
        @unlink($assembledPath);
        bandpromo_brand_import_json_exit($payload);
    } catch (InvalidArgumentException $throwable) {
        bandpromo_brand_import_json_exit(['ok' => false, 'error' => $throwable->getMessage()], 400);
    } catch (Throwable $throwable) {
        bandpromo_brand_import_json_exit(['ok' => false, 'error' => $throwable->getMessage()], 400);
    }
}

$file = $_FILES['package'] ?? null;
if (!is_array($file)) {
    bandpromo_brand_import_json_exit([
        'ok' => false,
        'error' => 'Upload a Portable Brand File (.pbf), or use chunked upload fields.',
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
        UPLOAD_ERR_NO_FILE => 'Upload a Portable Brand File (.pbf).',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded package.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
        default => 'Upload failed (error code ' . $uploadError . ').',
    };
    bandpromo_brand_import_json_exit(['ok' => false, 'error' => $error], 400);
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$originalName = (string) ($file['name'] ?? 'package.pbf');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    bandpromo_brand_import_json_exit(['ok' => false, 'error' => 'Invalid upload.'], 400);
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!bandpromo_pbf_is_brand_file_extension($extension)) {
    bandpromo_brand_import_json_exit([
        'ok' => false,
        'error' => bandpromo_pbf_operator_extension_error(),
    ], 400);
}

@set_time_limit(0);

try {
    $payload = bandpromo_brand_import_run($root, $tmpName, $originalName, $collision);
    bandpromo_brand_import_json_exit($payload);
} catch (Throwable $throwable) {
    bandpromo_brand_import_json_exit([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], 400);
}
