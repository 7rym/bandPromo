<?php
declare(strict_types=1);

/**
 * Poll site health plan + log + running state for System → Status.
 */

require_once __DIR__ . '/admin-api-guard.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$logFile = $root . '/log/site-health.log';
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

$log = '';
if (is_file($logFile)) {
    $rawLog = @file_get_contents($logFile);
    $log = is_string($rawLog) ? $rawLog : '';
}

$exitCode = null;
if (preg_match('/\nEXITCODE:(\-?\d+)\s*$/', $log, $m)) {
    $exitCode = (int) $m[1];
    $log = (string) preg_replace('/\nEXITCODE:\-?\d+\s*$/', '', $log);
}

$running = is_file($lockFile) && $exitCode === null;
if (is_file($lockFile) && $exitCode !== null) {
    @unlink($lockFile);
}

$plan = bandpromo_site_health_load_json($planFile);
$meta = bandpromo_site_health_load_json($metaFile);

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
