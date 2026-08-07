<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/site-backup-portability.php';

$root = dirname(__DIR__);
$jobId = trim((string) ($_GET['id'] ?? ''));

if ($jobId === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'Backup id is required.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Backup archive was not found.');
    }

    $normalized = bandpromo_site_backup_normalize_job($root, $job);
    if (empty($normalized['download_ready'])) {
        throw new RuntimeException('This backup is not ready to download yet.');
    }

    $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);
    $filename = (string) ($normalized['filename'] ?? bandpromo_site_backup_export_filename((string) ($normalized['type'] ?? 'full'), $jobId));

    bandpromo_admin_audit_log('site_backup_downloaded', [
        'job_id' => $jobId,
        'backup_type' => (string) ($normalized['type'] ?? 'full'),
        'size_bytes' => (int) ($normalized['size_bytes'] ?? 0),
    ]);

    // Streams and exits on success. Do not wrap post-header failures in JSON.
    bandpromo_site_backup_stream_file($zipPath, $filename);
} catch (Throwable $e) {
    if (headers_sent()) {
        exit;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
