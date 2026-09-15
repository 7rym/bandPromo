<?php
declare(strict_types=1);

/**
 * Start background catalogue Repair (Python supervisor → PHP CLI Apply).
 * Does not depend on the browser staying open.
 */

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/catalog-repair-auto.php';
require_once __DIR__ . '/content-autofix-helpers.php';
require_once __DIR__ . '/job-stop.php';
require_once __DIR__ . '/build-launcher.php';
require_once __DIR__ . '/build-launch-diagnostics.php';
require_once __DIR__ . '/light-build-tasks.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isDeveloperUser($_SESSION['username'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Developer access required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode(is_string($raw) ? $raw : '', true);
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

$root = dirname(__DIR__);
$logDir = $root . '/log';
$logFile = bandpromo_content_autofix_log_path($root);
$lockFile = bandpromo_catalog_repair_lock_path($root);
$script = $root . '/scripts/catalogRepair.py';
$isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';

if (bandpromo_catalog_repair_is_locked($root)) {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'busy' => true,
        'error' => 'Repair already running. Wait for it to finish, or press Stop.',
        'running' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_dir($logDir) && !@mkdir($logDir, 0777, true) && !is_dir($logDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create log directory.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

bandpromo_job_stop_clear($root, 'catalog_repair');
@file_put_contents($logFile, '');
@file_put_contents(
    $logFile,
    '[' . gmdate('Y-m-d H:i:s') . ' UTC] Starting background catalogue Repair…'
    . "\n(Does not depend on this browser tab staying open.)\n"
);

$python = bandpromo_resolve_python_interpreter();
if ($python === '' || !is_file($script)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not resolve Python runtime or catalogRepair.py.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

@file_put_contents($lockFile, 'running');

$runId = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : uniqid('repair_', true);
$startedAt = time();
@file_put_contents($root . '/log/catalog-repair.meta.json', json_encode([
    'run_id' => $runId,
    'started_at' => $startedAt,
    'updated_at' => $startedAt,
    'heartbeat_at' => $startedAt,
    'stage' => 'starting',
    'message' => 'Starting catalogue Repair…',
    'pid' => null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$diagnostics = bandpromo_build_run_launch_diagnostics($root, $logFile, $python, $script, $isWindows, false);
$launch = bandpromo_build_launch_background($python, $script, $logFile, $lockFile, $runId, $isWindows, $diagnostics);

if (empty($launch['started'])) {
    @unlink($lockFile);
    @unlink($root . '/log/catalog-repair.meta.json');
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not start background Repair.',
        'debug' => [
            'launch_command' => $launch['launch_command'] ?? null,
            'launch_output_tail' => $launch['launch_output_tail'] ?? null,
            'python' => $python,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!empty($launch['pid'])) {
    @file_put_contents($lockFile, (string) $launch['pid']);
    @file_put_contents($root . '/log/catalog-repair.meta.json', json_encode([
        'run_id' => $runId,
        'started_at' => $startedAt,
        'updated_at' => time(),
        'heartbeat_at' => time(),
        'stage' => 'starting',
        'message' => 'Starting catalogue Repair…',
        'pid' => (int) $launch['pid'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

bandpromo_admin_audit_log('content_autofix_apply_started', [
    'target_type' => 'content',
    'target_id' => 'platform-model',
    'status' => 'ok',
    'data' => ['run_id' => $runId, 'background' => true],
]);

echo json_encode([
    'ok' => true,
    'started' => true,
    'running' => true,
    'message' => 'Repair started in the background. You can leave this page — watch the Repair log, or press Stop to finish after the current step.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
