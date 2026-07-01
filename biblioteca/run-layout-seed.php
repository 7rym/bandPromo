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

$root = dirname(__DIR__);
$marker = $root . '/data/initial-site-compose.json';
$body = json_decode(file_get_contents('php://input') ?: '', true);
$force = is_array($body) && !empty($body['force']);

if (is_file($marker) && !$force) {
    echo json_encode([
        'ok' => true,
        'skipped' => true,
        'message' => 'Initial layout is already recorded. Use disaster recovery only when container documents were lost.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$result = bandpromo_run_light_task('scripts/setupCompose.py');
$ok = !empty($result['ok']);

bandpromo_admin_audit_log('layout_seed_' . ($force ? 'recovery' : 'setup'), [
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
    'error' => $ok ? null : (trim((string) ($result['output'] ?? '')) ?: 'Initial layout seed failed'),
    'message' => $ok
        ? 'Initial site layout seed finished.'
        : 'Initial layout seed failed. See output for details.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
