<?php
/**
 * Build Pipeline Runner — thin start.
 * Acquires lock, writes meta, launches Python in the background, returns immediately.
 * Heavy prep (reconcile / heal / Demo ensure) runs inside Python via publish-prep-cli.php.
 */

require_once __DIR__ . '/admin-api-guard.php';

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/build-lock.php';
require_once __DIR__ . '/build-launcher.php';
require_once __DIR__ . '/build-stages.php';
require_once __DIR__ . '/build-log-helpers.php';
require_once __DIR__ . '/job-stop.php';
require_once __DIR__ . '/light-build-tasks.php';

$root_dir  = dirname(dirname(__FILE__));
$log_dir   = $root_dir . '/log';
$request_data = [];
$raw_body = file_get_contents('php://input');
if ($raw_body !== false && trim($raw_body) !== '') {
    $parsed = json_decode($raw_body, true);
    if (is_array($parsed)) {
        $request_data = $parsed;
    }
}

$mode = $_POST['mode'] ?? ($request_data['mode'] ?? 'full');
if (!is_string($mode)) {
    $mode = 'full';
}
$mode = strtolower(trim($mode));
if (!in_array($mode, ['full', 'optimize'], true)) {
    $mode = 'full';
}

$build_profile = 'full';
$build_stage_ids = [];
if ($mode === 'full') {
    $raw_profile = $request_data['profile'] ?? 'full';
    if (is_string($raw_profile) && bandpromo_build_profile_is_valid($raw_profile)) {
        $build_profile = $raw_profile;
    }
    $requested_stages = $request_data['stages'] ?? null;
    if (is_array($requested_stages)) {
        $build_stage_ids = bandpromo_build_filter_stage_ids($requested_stages);
    }
    if ($build_stage_ids === []) {
        $build_stage_ids = bandpromo_build_resolve_stage_ids($build_profile, null);
    }
}

$log_file  = $log_dir  . ($mode === 'optimize' ? '/optimize.log' : '/build.log');
$lock_file = $log_dir  . ($mode === 'optimize' ? '/optimize.lock' : '/build.lock');
$meta_file = $log_dir  . ($mode === 'optimize' ? '/optimize.meta.json' : '/build.meta.json');
$script    = $root_dir . ($mode === 'optimize' ? '/scripts/optimizeMedia.py' : '/scripts/build.py');
$is_windows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
$build_run_id = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : uniqid('build_', true);
$build_actor = trim((string) ($_SESSION['username'] ?? 'unknown'));
$build_ip = bandpromo_admin_audit_client_ip();
$build_user_agent = bandpromo_admin_audit_user_agent();
$ensureDemo = !empty($request_data['ensure_demo']) || !empty($_POST['ensure_demo']);
$debug = [
    'mode' => $mode,
    'os' => PHP_OS_FAMILY,
    'launcher' => 'php-proc-runner',
    'python' => null,
    'script' => $script,
    'launch_command' => null,
    'launch_exit_code' => null,
    'launch_output_tail' => null,
    'thin_start' => true,
];

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Prevent concurrent builds
if (bandpromo_build_lock_active($root_dir, $mode)) {
    echo json_encode([
        'error' => 'Build already in progress',
        'running' => true,
        'mode' => $mode,
        'content' => bandpromo_build_read_log_tail($log_file),
    ]);
    exit;
}

if (!is_dir($log_dir)) {
    mkdir($log_dir, 0750, true);
}

bandpromo_job_stop_clear($root_dir, $mode === 'optimize' ? 'optimize' : 'build');

file_put_contents($log_file, '');
file_put_contents($log_file, bandpromo_build_log_started_lines($mode), FILE_APPEND);
file_put_contents(
    $log_file,
    "[setup] Starting in the background — preparation and publish stages follow below.\n"
    . "(Safe to leave this page; progress keeps updating.)\n",
    FILE_APPEND
);
file_put_contents($log_file, "RUN_ID:{$build_run_id}\n", FILE_APPEND);

$startedAt = time();
file_put_contents($meta_file, json_encode([
    'run_id' => $build_run_id,
    'mode' => $mode,
    'profile' => $build_profile,
    'stages' => $build_stage_ids,
    'actor' => $build_actor,
    'ip' => $build_ip,
    'user_agent' => $build_user_agent,
    'ensure_demo' => $ensureDemo,
    'started_at' => $startedAt,
    'updated_at' => $startedAt,
    'heartbeat_at' => $startedAt,
    'stage' => 'starting',
    'message' => 'Starting the bandPromo machine…',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$python = bandpromo_resolve_python_interpreter();
$debug['python'] = $python;

if ($python === '' || !file_exists($script)) {
    file_put_contents($log_file, "FAILED Could not resolve build runtime or script path.\n", FILE_APPEND);
    @unlink($meta_file);
    echo json_encode(['error' => 'Could not resolve build runtime', 'debug' => $debug]);
    exit;
}

// Lock before launch so polls see an active job immediately.
file_put_contents($lock_file, 'running');

file_put_contents($log_file, "[setup] Starting Python pipeline…\n", FILE_APPEND);
// Skip heavy launch diagnostics on the web request — use launcher fallbacks / cache.
$launch = bandpromo_build_launch_background($python, $script, $log_file, $lock_file, $build_run_id, $is_windows, null);
$debug['launch_command'] = $launch['launch_command'] ?? null;
$debug['launch_exit_code'] = $launch['launch_exit_code'] ?? null;
$debug['launch_output_tail'] = $launch['launch_output_tail'] ?? null;

if (empty($launch['started'])) {
    @unlink($lock_file);
    @unlink($meta_file);
    file_put_contents($log_file, "FAILED Could not start build process (launcher failed).\n", FILE_APPEND);
    if (!empty($debug['launch_output_tail'])) {
        file_put_contents($log_file, "DEBUG Launcher output:\n" . $debug['launch_output_tail'] . "\n", FILE_APPEND);
    }
    bandpromo_admin_audit_log('build_started', [
        'target_type' => 'build',
        'target_id' => $mode,
        'status' => 'error',
        'data' => ['error' => 'Could not start build process'],
    ]);
    echo json_encode(['error' => 'Could not start build process', 'debug' => $debug]);
    exit;
}

if (!empty($launch['pid'])) {
    file_put_contents($lock_file, (string) $launch['pid']);
    $metaNow = time();
    $metaPayload = [
        'run_id' => $build_run_id,
        'mode' => $mode,
        'profile' => $build_profile,
        'stages' => $build_stage_ids,
        'actor' => $build_actor,
        'ip' => $build_ip,
        'user_agent' => $build_user_agent,
        'ensure_demo' => $ensureDemo,
        'pid' => (int) $launch['pid'],
        'started_at' => $startedAt,
        'updated_at' => $metaNow,
        'heartbeat_at' => $metaNow,
        'stage' => 'starting',
        'message' => 'Starting the bandPromo machine…',
    ];
    file_put_contents($meta_file, json_encode($metaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

file_put_contents($log_file, "[setup] Python pipeline is running — further stages appear below.\n", FILE_APPEND);

bandpromo_admin_audit_log('build_started', [
    'actor' => $build_actor,
    'ip' => $build_ip,
    'user_agent' => $build_user_agent,
    'target_type' => 'build',
    'target_id' => $mode,
    'status' => 'ok',
    'data' => ['mode' => $mode, 'run_id' => $build_run_id, 'thin_start' => true],
]);

echo json_encode([
    'ok' => true,
    'message' => ($mode === 'optimize' ? 'Optimizer started' : 'Build started'),
    'mode' => $mode,
    'debug' => $debug,
]);
exit;
