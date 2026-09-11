<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/site-backup-portability.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);

try {
    // List first (fast). Never run export slices before the JSON body — shared hosts
    // kill long list requests and the Jobs table stays empty even though the job exists.
    $jobs = bandpromo_site_backup_list_jobs($root);

    echo json_encode([
        'ok' => true,
        'jobs' => $jobs,
        'status' => bandpromo_site_backup_status($root),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Continue at most one sliced PCF/PBF step after the browser has the job list.
    if (function_exists('session_write_close')) {
        @session_write_close();
    }
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }

    ignore_user_abort(true);
    @set_time_limit(0);
    bandpromo_site_backup_continue_building_jobs($root);
    bandpromo_site_backup_continue_archive_sha_jobs($root);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
