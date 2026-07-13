<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/site-backup-portability.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'POST required.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_FILES['archive']) || !is_array($_FILES['archive'])) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Choose a backup ZIP to inspect.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$upload = $_FILES['archive'];
$errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errorCode !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Upload failed. Try a smaller archive or check server upload limits.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$tmpPath = (string) ($upload['tmp_name'] ?? '');
$originalName = trim((string) ($upload['name'] ?? 'backup.zip'));
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Uploaded archive was not received.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$root = dirname(__DIR__);

try {
    $meta = bandpromo_site_backup_stage_uploaded_archive($root, $tmpPath, $originalName);
    $manifest = is_array($meta['manifest'] ?? null) ? $meta['manifest'] : [];

    echo json_encode([
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
        'size_label' => bandpromo_site_backup_format_bytes((int) ($meta['size_bytes'] ?? 0)),
        'components_label' => bandpromo_site_backup_components_label($meta['available_components'] ?? []),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
