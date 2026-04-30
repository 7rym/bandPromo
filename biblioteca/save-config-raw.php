<?php
/**
 * Save web-config.json (full raw replace).
 * Accepts the complete JSON blob via POST body. Admin-only.
 * Used by the Basics editor in admin.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
session_write_close(); // release lock before file I/O

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/build-required.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || trim($body) === '') {
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

// Validate JSON
$decoded = json_decode($body);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

$config_file = dirname(__DIR__) . '/web-config.json';

// Write pretty-printed JSON
$pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($config_file, $pretty) === false) {
    echo json_encode(['error' => 'Could not write web-config.json — check file permissions']);
    exit;
}

$state = bandpromo_mark_build_required('web_config_changed');
echo json_encode([
    'ok' => true,
    'build_required' => true,
    'build_required_state' => $state,
]);
