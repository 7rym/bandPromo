<?php
/**
 * Save operator display preferences in web-config.json (admin-only).
 */
require_once __DIR__ . '/admin-api-guard.php';
session_write_close();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/time-helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
$decoded = json_decode($body ?: '', true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$display = strtolower(trim((string) ($decoded['time_display'] ?? 'utc')));
if (!in_array($display, ['utc', 'local'], true)) {
    $display = 'utc';
}

$timezone = trim((string) ($decoded['timezone'] ?? 'UTC'));
if ($timezone === '') {
    $timezone = 'UTC';
}

try {
    new DateTimeZone($timezone);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid timezone identifier']);
    exit;
}

$configPath = dirname(__DIR__) . '/web-config.json';
$config = bandpromo_load_runtime_config_raw($configPath);
$config['operator'] = [
    'time_display' => $display,
    'timezone' => $timezone,
];

$pretty = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($pretty === false || file_put_contents($configPath, $pretty) === false) {
    bandpromo_admin_audit_log('operator_prefs_saved', [
        'target_type' => 'config',
        'target_id' => 'web-config.json:operator',
        'status' => 'error',
        'data' => ['error' => 'write failed'],
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Could not write web-config.json']);
    exit;
}

bandpromo_admin_audit_log('operator_prefs_saved', [
    'target_type' => 'config',
    'target_id' => 'web-config.json:operator',
    'status' => 'ok',
    'data' => [
        'time_display' => $display,
        'timezone' => $timezone,
    ],
]);

echo json_encode([
    'ok' => true,
    'operator' => $config['operator'],
]);
