<?php
declare(strict_types=1);

/**
 * Site health job control — Check / Treat / Force.
 * Launches scripts/siteHealth{Check,Treat,Force}.py via the existing build launcher.
 */

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/build-launcher.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/job-stop.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$root = dirname(__DIR__);
$logDir = $root . '/log';
if (!is_dir($logDir) && !@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create log directory']);
    exit;
}

$raw = file_get_contents('php://input');
$request = [];
if (is_string($raw) && trim($raw) !== '') {
    $parsed = json_decode($raw, true);
    if (is_array($parsed)) {
        $request = $parsed;
    }
}

$csrfToken = isset($request['csrf_token']) ? (string) $request['csrf_token'] : '';
if ($csrfToken !== '' && !validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired or invalid request token. Refresh admin and try again.']);
    exit;
}

$mode = strtolower(trim((string) ($request['mode'] ?? 'check')));
if (!in_array($mode, ['check', 'treat', 'force'], true)) {
    $mode = 'check';
}

$scriptMap = [
    'check' => $root . '/scripts/siteHealthCheck.py',
    'treat' => $root . '/scripts/siteHealthTreat.py',
    'force' => $root . '/scripts/siteHealthForce.py',
];
$script = $scriptMap[$mode];
$lockFile = $logDir . '/site-health.lock';
$metaFile = $logDir . '/site-health.meta.json';
$logFile = $logDir . '/site-health.log';
// build-runner EXITCODE / chatter — not the operator Activity pane
$runnerLogFile = $logDir . '/site-health.runner.log';
$isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';

if (!is_file($script)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Site health script missing']);
    exit;
}

if (is_file($lockFile)) {
    $lockAge = time() - (int) @filemtime($lockFile);
    if ($lockAge < 7200) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Site health is already running', 'running' => true]);
        exit;
    }
    @unlink($lockFile);
}

$python = bandpromo_resolve_python_interpreter();
if ($python === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not resolve Python for site health']);
    exit;
}

@file_put_contents($runnerLogFile, '');
$startedAt = time();
$runId = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : uniqid('health_', true);
@file_put_contents($metaFile, json_encode([
    'run_id' => $runId,
    'status' => 'starting',
    'mode' => $mode,
    'started_at' => $startedAt,
    'updated_at' => $startedAt,
    'heartbeat_at' => $startedAt,
    'message' => 'Starting ' . $mode . '...',
    'actor' => trim((string) ($_SESSION['username'] ?? 'unknown')),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

@file_put_contents($lockFile, 'running');
bandpromo_job_stop_clear($root, 'site_health');
$launch = bandpromo_build_launch_background($python, $script, $runnerLogFile, $lockFile, $runId, $isWindows, null);

if (empty($launch['started'])) {
    @unlink($lockFile);
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not start site health runner',
        'debug' => $launch,
    ]);
    exit;
}

if (!empty($launch['pid'])) {
    @file_put_contents($lockFile, (string) $launch['pid']);
}

bandpromo_admin_audit_log('site_health_' . $mode, [
    'target_type' => 'site_health',
    'target_id' => $mode,
    'status' => 'ok',
    'data' => ['run_id' => $runId],
]);

echo json_encode([
    'ok' => true,
    'mode' => $mode,
    'running' => true,
    'run_id' => $runId,
    'message' => $mode === 'check'
        ? 'Site health check started.'
        : ($mode === 'treat' ? 'Treatment started.' : 'Force rebuild started.'),
]);
