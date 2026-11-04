<?php
/**
 * Save install Stay signed in policy in web-config.json (admin-only).
 */
require_once __DIR__ . '/admin-api-guard.php';
session_write_close();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/https.php';

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

$staySignedIn = array_key_exists('stay_signed_in', $decoded)
    ? ($decoded['stay_signed_in'] !== false && $decoded['stay_signed_in'] !== 0 && $decoded['stay_signed_in'] !== '0')
    : true;

$configPath = dirname(__DIR__) . '/web-config.json';
$config = bandpromo_load_runtime_config_raw($configPath);
if (!isset($config['install']) || !is_array($config['install'])) {
    $config['install'] = [];
}
if (!isset($config['install']['security']) || !is_array($config['install']['security'])) {
    $config['install']['security'] = [];
}
$config['install']['security']['stay_signed_in'] = $staySignedIn;

$pretty = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($pretty === false || file_put_contents($configPath, $pretty) === false) {
    bandpromo_admin_audit_log('session_prefs_saved', [
        'target_type' => 'config',
        'target_id' => 'web-config.json:install.security',
        'status' => 'error',
        'data' => ['error' => 'write failed'],
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Could not write web-config.json']);
    exit;
}

bandpromo_admin_audit_log('session_prefs_saved', [
    'target_type' => 'config',
    'target_id' => 'web-config.json:install.security',
    'status' => 'ok',
    'data' => [
        'stay_signed_in' => $staySignedIn,
    ],
]);

echo json_encode([
    'ok' => true,
    'stay_signed_in' => $staySignedIn,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
