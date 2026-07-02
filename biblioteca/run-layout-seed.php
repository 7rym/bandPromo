<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/light-build-tasks.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

function bandpromo_initial_site_seed_marker_path(string $root): string
{
    return $root . '/data/initial-site-seed.json';
}

function bandpromo_initial_site_seed_marker_exists(string $root): bool
{
    if (is_file(bandpromo_initial_site_seed_marker_path($root))) {
        return true;
    }

    return is_file($root . '/data/initial-site-compose.json');
}

$root = dirname(__DIR__);
$body = json_decode(file_get_contents('php://input') ?: '', true);
$force = is_array($body) && !empty($body['force']);

if (bandpromo_initial_site_seed_marker_exists($root) && !$force) {
    echo json_encode([
        'ok' => true,
        'skipped' => true,
        'message' => 'Initial site seed is already recorded. Use disaster recovery only when container documents were lost.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$result = bandpromo_run_light_task('scripts/initialSiteSeed.py', $force ? ['BANDPROMO_LAYOUT_SEED_FORCE' => '1'] : []);
$ok = !empty($result['ok']);

bandpromo_admin_audit_log('initial_site_seed_' . ($force ? 'recovery' : 'setup'), [
    'target_type' => 'site',
    'target_id' => 'initial-layout',
    'status' => $ok ? 'ok' : 'error',
    'data' => [
        'force' => $force,
        'exit_code' => $result['exit_code'] ?? null,
    ],
]);

echo json_encode([
    'ok' => $ok,
    'skipped' => false,
    'force' => $force,
    'exit_code' => $result['exit_code'] ?? null,
    'output' => trim((string) ($result['output'] ?? '')),
    'error' => $ok ? null : (trim((string) ($result['output'] ?? '')) ?: 'Initial site seed failed'),
    'message' => $ok
        ? 'Initial site seed finished.'
        : 'Initial site seed failed. See output for details.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
