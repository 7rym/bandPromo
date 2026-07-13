<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/site-backup-portability.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);

try {
    bandpromo_site_backup_process_pending($root);
    $jobs = bandpromo_site_backup_list_jobs($root);

    echo json_encode([
        'ok' => true,
        'jobs' => $jobs,
        'status' => bandpromo_site_backup_status($root),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
