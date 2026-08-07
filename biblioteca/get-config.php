<?php
/**
 * Secure Configuration API
 *
 * Returns a non-sensitive subset of web-config.json to authenticated sessions.
 * Used by admin (and historically by /web-config.json rewrite).
 */
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/auth.php';

bandpromo_enforce_https();
bandpromo_require_authenticated_session(true, true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

$root_dir = dirname(__DIR__);
$config_file = $root_dir . '/web-config.json';

if (!is_file($config_file)) {
    http_response_code(404);
    echo json_encode(['error' => 'Configuration not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents($config_file);
$decoded = is_string($raw) ? json_decode($raw) : null;
if (!is_object($decoded)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid configuration format'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

unset($decoded->admins);
echo json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
