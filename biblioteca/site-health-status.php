<?php
declare(strict_types=1);

/**
 * Poll site health plan + Activity log + running state for System → Status.
 *
 * Activity text is read-only here — written only by scripts/site_health/log.py.
 */

require_once __DIR__ . '/admin-api-guard.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$logFile = $root . '/log/site-health.log';
$runnerLogFile = $root . '/log/site-health.runner.log';
$lockFile = $root . '/log/site-health.lock';
$metaFile = $root . '/log/site-health.meta.json';
$planFile = $root . '/data/site-health-plan.json';

function bandpromo_site_health_load_json(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

// Operator Activity pane (Python-owned).
$log = '';
if (is_file($logFile)) {
    $rawLog = @file_get_contents($logFile);
    $log = is_string($rawLog) ? $rawLog : '';
}
// Never show legacy EXITCODE crumbs if an old run wrote them into Activity.
$log = (string) preg_replace('/\nEXITCODE:\-?\d+\s*$/', '', $log);

$meta = bandpromo_site_health_load_json($metaFile);
$metaStatus = strtolower(trim((string) ($meta['status'] ?? '')));
$metaExit = array_key_exists('exit_code', $meta) ? $meta['exit_code'] : null;
if ($metaExit !== null && $metaExit !== '') {
    $metaExit = (int) $metaExit;
} else {
    $metaExit = null;
}

// Process sidecar — build-runner EXITCODE only; not shown in Activity.
$exitCode = $metaExit;
$runnerLog = '';
if (is_file($runnerLogFile)) {
    $rawRunner = @file_get_contents($runnerLogFile);
    $runnerLog = is_string($rawRunner) ? $rawRunner : '';
}
if ($exitCode === null && preg_match('/\nEXITCODE:(\-?\d+)\s*$/', $runnerLog, $m)) {
    $exitCode = (int) $m[1];
}

$lockPresent = is_file($lockFile);
$metaRunning = in_array($metaStatus, ['starting', 'running'], true);
$running = $lockPresent && $exitCode === null && ($metaRunning || $metaStatus === '');
if ($lockPresent && ($exitCode !== null || in_array($metaStatus, ['idle', 'failed'], true))) {
    @unlink($lockFile);
    $running = false;
}

$plan = bandpromo_site_health_load_json($planFile);

$overall = (string) ($plan['overall'] ?? '');
if ($overall === '' && $plan === []) {
    $overall = 'unknown';
}

echo json_encode([
    'ok' => true,
    'running' => $running,
    'exit_code' => $exitCode,
    'overall' => $overall,
    'plan' => $plan,
    'meta' => $meta,
    'log' => $log,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
