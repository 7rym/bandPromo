<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/package-updater.php';
require_once __DIR__ . '/install-migrations.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'POST required.',
        'stage' => 'request',
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
        'stage' => 'csrf',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

@set_time_limit(900);

$root = dirname(__DIR__);
$stage = 'precheck';

try {
    // Video delivery auto-retry can spin forever on broken hosts; clear it first so
    // package extraction is not fighting ffmpeg/python lock files.
    require_once __DIR__ . '/auto-build-tasks.php';
    if (bandpromo_has_running_background_video_tasks()) {
        bandpromo_force_stop_video_delivery([
            'pause_seconds' => 3600,
            'reason' => 'Force-stopped automatically so Site update can install the new package.',
        ]);
    }

    $status = bandpromo_package_check_update($root);
    // Refresh the notifications cache so Dashboard does not keep advertising the old build.
    bandpromo_package_write_update_cache($root, $status);
    if (empty($status['ready'])) {
        throw new RuntimeException('This hosting setup is not ready for package updates yet. Fix the requirements shown in the update panel first.');
    }
    if (!empty($status['manifest_error'])) {
        throw new RuntimeException($status['manifest_error']);
    }
    if (empty($status['update_available'])) {
        throw new RuntimeException('No newer published package is available for this site.');
    }

    $manifest = bandpromo_package_load_app_release_manifest();
    bandpromo_package_assert_manifest_requirements_met($manifest);
    $stage = 'download';

    $applyResult = bandpromo_package_apply_release($root, $manifest);
    $stage = 'post_update';

    $postUpdate = bandpromo_package_run_post_update_tasks($root, $applyResult);

    $logRecord = [
        'ok' => true,
        'actor' => bandpromo_admin_audit_actor(),
        'previous_version' => $applyResult['previous_version'],
        'installed_version' => $applyResult['installed_version'],
        'package_file' => $applyResult['package_file'],
        'package_url' => $applyResult['package_url'],
        'sha256' => $applyResult['sha256'],
        'post_update' => $postUpdate,
    ];
    bandpromo_package_append_update_log($root, $logRecord);

    $demoRefresh = is_array($postUpdate['demo_release_package'] ?? null)
        ? $postUpdate['demo_release_package']
        : null;
    bandpromo_admin_audit_log('package_update_applied', [
        'previous_version' => $applyResult['previous_version'],
        'installed_version' => $applyResult['installed_version'],
        'package_file' => $applyResult['package_file'],
        'demo_prp_refreshed' => !empty($demoRefresh['refreshed']),
        'demo_prp_ok' => !isset($demoRefresh['ok']) || !empty($demoRefresh['ok']),
    ]);

    $message = 'bandPromo was updated to ' . $applyResult['installed_version'] . '.';
    if (is_array($demoRefresh) && !empty($demoRefresh['refreshed'])) {
        $message .= ' Demo catalogue was refreshed to the latest published package.';
    } elseif (is_array($demoRefresh) && ($demoRefresh['skip_reason'] ?? '') === 'unlocked_localhost') {
        $message .= ' Demo catalogue left unchanged (unlocked on localhost).';
    } elseif (is_array($demoRefresh) && empty($demoRefresh['ok'])) {
        $message .= ' Demo catalogue could not be refreshed automatically; rebuild may still use the previous demo files.';
    }
    $message .= ' Opening Deliverables to rebuild listener-ready files for your public site.';

    $orphanMigration = is_array($postUpdate['install_migrations'][BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_ID] ?? null)
        ? $postUpdate['install_migrations'][BANDPROMO_INSTALL_MIGRATION_ORPHAN_PRIMARY_ID]
        : null;
    if (is_array($orphanMigration) && (int) ($orphanMigration['tracks_orphaned'] ?? 0) > 0) {
        $message .= ' Uploaded tracks that were stuck on Default release are now ready to assign to your campaigns.';
    }

    // Re-check and rewrite cache so Notifications immediately show up to date.
    bandpromo_package_write_update_cache($root, bandpromo_package_check_update($root));

    echo json_encode([
        'ok' => true,
        'message' => $message,
        'previous_version' => $applyResult['previous_version'],
        'installed_version' => $applyResult['installed_version'],
        'post_update' => $postUpdate,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    bandpromo_package_append_update_log($root, [
        'ok' => false,
        'actor' => bandpromo_admin_audit_actor(),
        'stage' => $stage,
        'error' => $throwable->getMessage(),
    ]);

    bandpromo_admin_audit_log('package_update_failed', [
        'stage' => $stage,
        'error' => $throwable->getMessage(),
    ]);

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
        'stage' => $stage,
        'retry_safe' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
