<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/site-backup-portability.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/demo-catalog-state.php';

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
$brandId = bandpromo_brand_canonical_id((string) ($decoded['brand_id'] ?? ''));
$actor = trim((string) ($_SESSION['username'] ?? ''));

if ($brandId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'brand_id is required.']);
    exit;
}

if (!bandpromo_demo_brand_visible_in_admin($root, $brandId)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'That demo brand is hidden with the bandPromo demo campaign.',
    ]);
    exit;
}

try {
    $job = bandpromo_site_backup_enqueue_pbf($root, $brandId, $actor);

    bandpromo_admin_audit_log('brand_package_queued', [
        'target_type' => 'brand',
        'target_id' => $brandId,
        'status' => 'ok',
        'data' => [
            'job_id' => (string) ($job['id'] ?? ''),
            'filename' => (string) ($job['filename'] ?? ''),
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'queued' => true,
        'brand_id' => $brandId,
        'job' => $job,
        'job_id' => (string) ($job['id'] ?? ''),
        'filename' => (string) ($job['filename'] ?? ''),
        'message' => 'PBF export queued. It will appear under System → Backup, export & import when ready.',
        'jobs_url' => '?tab=system&stab=backup',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    bandpromo_site_backup_finish_response_and_dispatch($root, (string) $job['id']);
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
