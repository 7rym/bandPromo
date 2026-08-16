<?php
declare(strict_types=1);

/**
 * Portable release package (.prp / .zip) import.
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
require_once __DIR__ . '/release-campaign-package.php';
require_once __DIR__ . '/release-storage.php';

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
function bandpromo_release_import_json_exit(array $payload, int $status = 200): void
{
    $GLOBALS['importCompleted'] = true;
    if ($status !== 200) {
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bandpromo_release_import_normalize_collision(string $collision): string
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
 * @return array{ok: true, release_id: string, message: string, imported_files: int, collision: string, releases: list}
 */
function bandpromo_release_import_run(string $root, string $zipPath, string $originalName, string $collision): array
{
    $result = bandpromo_release_campaign_import_from_zip($root, $zipPath, [
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
        ],
    ]);

    return [
        'ok' => true,
        'release_id' => (string) ($result['release_id'] ?? ''),
        'message' => (string) ($result['message'] ?? 'Release package imported.'),
        'imported_files' => (int) ($result['imported_files'] ?? 0),
        'collision' => (string) ($result['collision'] ?? $collision),
        'releases' => bandpromo_release_admin_registry_entries($root),
    ];
}

function bandpromo_release_import_cleanup_parts(string $tmpDir, string $uploadId, int $totalChunks): void
{
    for ($i = 0; $i < $totalChunks; $i++) {
        $partPath = $tmpDir . '/' . $uploadId . '.part' . $i;
        if (is_file($partPath)) {
            @unlink($partPath);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bandpromo_release_import_json_exit(['ok' => false, 'error' => 'POST required.'], 405);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    $postMax = (string) ini_get('post_max_size');
    $uploadMax = (string) ini_get('upload_max_filesize');
    bandpromo_release_import_json_exit([
        'ok' => false,
        'error' => 'Package is larger than this host allows (post_max_size='
            . $postMax . ', upload_max_filesize=' . $uploadMax
            . '). Use chunked import (admin) or raise those limits.',
    ], 413);
}

$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
if (!validate_csrf_token($csrfToken)) {
    bandpromo_release_import_json_exit([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ], 403);
}

$root = dirname(__DIR__);
$tmpDir = $root . '/data/upload_tmp';
if (!is_dir($tmpDir) && !mkdir($tmpDir, 0750, true) && !is_dir($tmpDir)) {
    bandpromo_release_import_json_exit(['ok' => false, 'error' => 'Could not create upload staging directory.'], 500);
}

$collision = bandpromo_release_import_normalize_collision((string) ($_POST['collision'] ?? 'refuse'));

// ─── Chunked upload mode (2 MB parts from admin, same as Files) ───────────────
if (isset($_POST['chunk_index'], $_POST['filename'])) {
    $chunkIndex = (int) $_POST['chunk_index'];
    $totalChunks = (int) $_POST['total_chunks'];
    $filename = basename((string) $_POST['filename']);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $uploadId = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($_POST['upload_id'] ?? ''));

    if ($filename === '' || !in_array($extension, ['prp', 'zip'], true)) {
        bandpromo_release_import_json_exit([
            'ok' => false,
            'error' => 'Release packages must be .prp or .zip files.',
        ], 400);
    }
    if ($uploadId === '' || strlen($uploadId) < 8 || strlen($uploadId) > 80) {
        bandpromo_release_import_json_exit([
            'ok' => false,
            'error' => 'Missing or invalid upload_id for chunked import.',
        ], 400);
    }
    if ($totalChunks < 1 || $totalChunks > 100000 || $chunkIndex < 0 || $chunkIndex >= $totalChunks) {
        bandpromo_release_import_json_exit(['ok' => false, 'error' => 'Invalid chunk index.'], 400);
    }
    if (empty($_FILES['chunk']) || (int) ($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = (int) ($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE);
        bandpromo_release_import_json_exit([
            'ok' => false,
            'error' => 'Chunk upload error: ' . $code,
        ], 400);
    }

    $chunkPath = $tmpDir . '/' . $uploadId . '.part' . $chunkIndex;
    if (!move_uploaded_file((string) $_FILES['chunk']['tmp_name'], $chunkPath)) {
        bandpromo_release_import_json_exit(['ok' => false, 'error' => 'Could not save chunk.'], 500);
    }

    $partsPresent = 0;
    for ($i = 0; $i < $totalChunks; $i++) {
        if (is_file($tmpDir . '/' . $uploadId . '.part' . $i)) {
            $partsPresent++;
        }
    }

    if ($partsPresent < $totalChunks) {
        bandpromo_release_import_json_exit([
            'ok' => true,
            'status' => 'partial',
            'received' => $partsPresent,
            'total' => $totalChunks,
            'upload_id' => $uploadId,
        ]);
    }

    @set_time_limit(0);
    $assembledPath = $tmpDir . '/' . $uploadId . '.assembled.' . $extension;
    $out = fopen($assembledPath, 'wb');
    if ($out === false) {
        bandpromo_release_import_cleanup_parts($tmpDir, $uploadId, $totalChunks);
        bandpromo_release_import_json_exit(['ok' => false, 'error' => 'Could not assemble package.'], 500);
    }
    try {
        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = $tmpDir . '/' . $uploadId . '.part' . $i;
            $in = fopen($partPath, 'rb');
            if ($in === false) {
                throw new RuntimeException('Missing chunk part ' . $i . '.');
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
            @unlink($partPath);
        }
    } catch (Throwable $assembleError) {
        fclose($out);
        @unlink($assembledPath);
        bandpromo_release_import_cleanup_parts($tmpDir, $uploadId, $totalChunks);
        bandpromo_release_import_json_exit([
            'ok' => false,
            'error' => $assembleError->getMessage(),
        ], 500);
    }
    fclose($out);

    try {
        $payload = bandpromo_release_import_run($root, $assembledPath, $filename, $collision);
        @unlink($assembledPath);
        bandpromo_release_import_json_exit($payload);
    } catch (Throwable $throwable) {
        @unlink($assembledPath);
        bandpromo_release_import_json_exit([
            'ok' => false,
            'error' => $throwable->getMessage(),
        ], 400);
    }
}

// ─── Single-file mode (small packages / scripts) ──────────────────────────────
$file = $_FILES['package'] ?? null;
if (!is_array($file)) {
    bandpromo_release_import_json_exit([
        'ok' => false,
        'error' => 'Upload a portable release package (.prp or .zip), or use chunked upload fields.',
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
        UPLOAD_ERR_NO_FILE => 'Upload a portable release package (.prp or .zip).',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded package.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
        default => 'Upload failed (error code ' . $uploadError . ').',
    };
    bandpromo_release_import_json_exit(['ok' => false, 'error' => $error], 400);
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$originalName = (string) ($file['name'] ?? 'package.prp');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    bandpromo_release_import_json_exit(['ok' => false, 'error' => 'Invalid upload.'], 400);
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, ['prp', 'zip'], true)) {
    bandpromo_release_import_json_exit([
        'ok' => false,
        'error' => 'Release packages must be .prp or .zip files.',
    ], 400);
}

@set_time_limit(0);

try {
    $payload = bandpromo_release_import_run($root, $tmpName, $originalName, $collision);
    bandpromo_release_import_json_exit($payload);
} catch (Throwable $throwable) {
    bandpromo_release_import_json_exit([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], 400);
}
