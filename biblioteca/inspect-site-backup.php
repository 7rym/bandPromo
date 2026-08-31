<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/site-backup-portability.php';
require_once __DIR__ . '/chunked-upload.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * @return never
 */
function bandpromo_inspect_backup_json_exit(array $payload, int $status = 200): void
{
    if ($status !== 200) {
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    bandpromo_inspect_backup_json_exit(['ok' => false, 'error' => 'POST required.'], 405);
}

$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
if (!validate_csrf_token($csrfToken)) {
    bandpromo_inspect_backup_json_exit([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ], 403);
}

$root = dirname(__DIR__);

/**
 * @param array<string, mixed> $meta
 * @return array<string, mixed>
 */
function bandpromo_inspect_backup_payload(array $meta): array
{
    $manifest = is_array($meta['manifest'] ?? null) ? $meta['manifest'] : [];

    return [
        'ok' => true,
        'staging_id' => (string) ($meta['staging_id'] ?? ''),
        'original_filename' => (string) ($meta['original_filename'] ?? ''),
        'available_components' => $meta['available_components'] ?? [],
        'suggested_mode' => (string) ($meta['suggested_mode'] ?? BANDPROMO_SITE_IMPORT_MODE_RESTORE),
        'same_install' => !empty($meta['same_install']),
        'source_install_id' => (string) ($meta['source_install_id'] ?? ''),
        'local_install_id' => (string) ($meta['local_install_id'] ?? ''),
        'source_site_url' => (string) ($meta['source_site_url'] ?? ''),
        'current_site_url' => (string) ($meta['current_site_url'] ?? ''),
        'url_mismatch' => !empty($meta['url_mismatch']),
        'bandpromo_version' => (string) ($manifest['bandpromo_version'] ?? ''),
        'exported_at_utc' => (string) ($manifest['exported_at_utc'] ?? ''),
        'size_bytes' => (int) ($meta['size_bytes'] ?? 0),
        'size_label' => bandpromo_site_backup_format_bytes((int) ($meta['size_bytes'] ?? 0)),
        'sha256' => (string) ($meta['sha256'] ?? ''),
        'components_label' => bandpromo_site_backup_components_label($meta['available_components'] ?? []),
    ];
}

if (isset($_POST['chunk_index'], $_POST['filename'])) {
    try {
        $meta = bandpromo_chunked_upload_parse_request($_POST);
        if ($meta['extension'] !== 'zip') {
            throw new InvalidArgumentException('Backup archives must be .zip.');
        }
        $tmpDir = bandpromo_chunked_upload_staging_dir($root, 'bandpromo-backup-upload');
        $chunkFile = is_array($_FILES['chunk'] ?? null) ? $_FILES['chunk'] : [];
        $received = bandpromo_chunked_upload_receive($tmpDir, $meta, $chunkFile, 'zip');
        if (($received['status'] ?? '') === 'partial') {
            bandpromo_inspect_backup_json_exit($received);
        }
        $assembledPath = (string) ($received['assembled_path'] ?? '');
        $openError = bandpromo_chunked_upload_zip_open_error($assembledPath, 'backup ZIP');
        if ($openError !== '') {
            @unlink($assembledPath);
            throw new RuntimeException($openError);
        }
        $staged = bandpromo_site_backup_stage_uploaded_archive($root, $assembledPath, $meta['filename']);
        $staged['sha256'] = (string) ($received['sha256'] ?? bandpromo_transfer_sha256_file(
            bandpromo_site_backup_staging_zip_path($root, (string) ($staged['staging_id'] ?? ''))
        ));
        @unlink($assembledPath);
        bandpromo_inspect_backup_json_exit(bandpromo_inspect_backup_payload($staged));
    } catch (InvalidArgumentException $e) {
        bandpromo_inspect_backup_json_exit(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        bandpromo_inspect_backup_json_exit(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

if (!isset($_FILES['archive']) || !is_array($_FILES['archive'])) {
    bandpromo_inspect_backup_json_exit([
        'ok' => false,
        'error' => 'Choose a backup ZIP to inspect, or use chunked upload fields.',
    ], 400);
}

$upload = $_FILES['archive'];
$errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errorCode !== UPLOAD_ERR_OK) {
    bandpromo_inspect_backup_json_exit([
        'ok' => false,
        'error' => 'Upload failed. Try chunked upload or check server upload limits.',
    ], 400);
}

$tmpPath = (string) ($upload['tmp_name'] ?? '');
$originalName = trim((string) ($upload['name'] ?? 'backup.zip'));
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    bandpromo_inspect_backup_json_exit([
        'ok' => false,
        'error' => 'Uploaded archive was not received.',
    ], 400);
}

try {
    $meta = bandpromo_site_backup_stage_uploaded_archive($root, $tmpPath, $originalName);
    $meta['sha256'] = bandpromo_transfer_sha256_file(
        bandpromo_site_backup_staging_zip_path($root, (string) ($meta['staging_id'] ?? ''))
    );
    bandpromo_inspect_backup_json_exit(bandpromo_inspect_backup_payload($meta));
} catch (InvalidArgumentException $e) {
    bandpromo_inspect_backup_json_exit(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    bandpromo_inspect_backup_json_exit(['ok' => false, 'error' => $e->getMessage()], 500);
}
