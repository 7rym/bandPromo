<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/content-autofix-helpers.php';
require_once __DIR__ . '/catalog-repair-auto.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$dryRun = !empty($body['dry_run']);
$root = dirname(__DIR__);

if (!$dryRun) {
    // Shared hosts often ignore set_time_limit and keep php.ini 30s CPU. Still bump
    // aggressively so hosts that allow it give Repair room; steps also re-bump.
    @set_time_limit(600);
    @ini_set('max_execution_time', '600');
    ignore_user_abort(true);
}

if (!bandpromo_catalog_repair_try_acquire($root)) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'error' => 'Repair already running. Wait for the current Repair or background catalogue preparation to finish, then try again.',
        'busy' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Fatal timeout can skip finally on some hosts — always release the shared lock.
$repairLockReleased = false;
$releaseRepairLock = static function () use ($root, &$repairLockReleased): void {
    if ($repairLockReleased) {
        return;
    }
    $repairLockReleased = true;
    bandpromo_catalog_repair_release($root);
};
register_shutdown_function($releaseRepairLock);

try {
    $report = bandpromo_content_autofix_run($root, $dryRun);

    bandpromo_admin_audit_log('content_autofix_' . ($dryRun ? 'preview' : 'apply'), [
        'target_type' => 'content',
        'target_id' => 'platform-model',
        'status' => !empty($report['ok']) ? 'ok' : 'error',
        'data' => [
            'dry_run' => $dryRun,
            'changed_total' => (int) ($report['changed_total'] ?? 0),
            'errors' => $report['errors'] ?? [],
        ],
    ]);

    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    bandpromo_content_autofix_log_write($root, '!!!! exception: ' . $throwable->getMessage());
    $state = &bandpromo_content_autofix_log_state();
    $state['running'] = false;
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ]);
} finally {
    $releaseRepairLock();
}
