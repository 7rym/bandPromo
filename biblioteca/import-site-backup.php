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

$stagingId = trim((string) ($payload['staging_id'] ?? ''));
if ($stagingId === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Upload and inspect an archive before importing.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$root = dirname(__DIR__);
$actor = trim((string) ($_SESSION['username'] ?? ''));

try {
    $components = bandpromo_site_backup_normalize_components($payload['components'] ?? []);
    $importMode = bandpromo_site_backup_normalize_import_mode((string) ($payload['mode'] ?? BANDPROMO_SITE_IMPORT_MODE_RESTORE));
    $repairSiteUrlRaw = strtolower(trim((string) ($payload['repair_site_url'] ?? '0')));
    $repairSiteUrl = in_array($repairSiteUrlRaw, ['1', 'true', 'yes'], true);

    $job = bandpromo_site_backup_enqueue_import(
        $root,
        $stagingId,
        $components,
        $importMode,
        $repairSiteUrl,
        $actor
    );

    bandpromo_admin_audit_log('site_backup_import_queued', [
        'job_id' => $job['id'],
        'import_mode' => $importMode,
        'components' => $job['components'] ?? [],
        'source_install_id' => $job['source_install_id'] ?? '',
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Import queued. This list refreshes automatically.',
        'job' => $job,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    bandpromo_site_backup_finish_response_and_dispatch($root, (string) $job['id']);
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
