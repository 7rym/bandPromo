<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/site-backup-portability.php';
require_once __DIR__ . '/release-storage.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$raw = file_get_contents('php://input');
$decoded = json_decode($raw ?: '{}', true);
if (!is_array($decoded)) {
    $decoded = $_POST;
}

$csrfToken = trim((string) ($decoded['csrf_token'] ?? ''));
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ]);
    exit;
}

$root = dirname(__DIR__);
$releaseId = bandpromo_release_normalize_id((string) ($decoded['release_id'] ?? ''));
$actor = trim((string) ($_SESSION['username'] ?? ''));

if ($releaseId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'release_id is required.']);
    exit;
}

try {
    $job = bandpromo_site_backup_enqueue_prp($root, $releaseId, $actor);

    bandpromo_admin_audit_log('release_package_queued', [
        'target_type' => 'release',
        'target_id' => $releaseId,
        'status' => 'ok',
        'data' => [
            'job_id' => (string) ($job['id'] ?? ''),
            'filename' => (string) ($job['filename'] ?? ''),
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'queued' => true,
        'release_id' => $releaseId,
        'job' => $job,
        'job_id' => (string) ($job['id'] ?? ''),
        'filename' => (string) ($job['filename'] ?? ''),
        'message' => 'PRP export queued. It will appear under System → Backup, export & import when ready.',
        'jobs_url' => '?tab=system&stab=backup',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        bandpromo_site_backup_dispatch_job($root, (string) $job['id']);
    }
} catch (InvalidArgumentException $throwable) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
