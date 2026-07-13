<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
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

$rawBody = file_get_contents('php://input');
$payload = json_decode(is_string($rawBody) ? $rawBody : '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$csrfToken = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$jobId = trim((string) ($payload['id'] ?? ''));
if ($jobId === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Backup id is required.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$root = dirname(__DIR__);

try {
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        throw new RuntimeException('Backup archive was not found.');
    }

    if (($job['status'] ?? '') === BANDPROMO_SITE_BACKUP_JOB_BUILDING) {
        throw new RuntimeException('This job is still running. Try again after it finishes.');
    }

    bandpromo_site_backup_delete_job($root, $jobId);

    bandpromo_admin_audit_log('site_backup_deleted', [
        'job_id' => $jobId,
        'backup_type' => (string) ($job['type'] ?? 'full'),
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Backup archive deleted.',
        'jobs' => bandpromo_site_backup_list_jobs($root),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
