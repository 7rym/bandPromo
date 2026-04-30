<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
session_write_close(); // release lock before file I/O

require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/build-required.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$patch = json_decode($raw, true);

if (!is_array($patch)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$cfgPath = __DIR__ . '/../web-config.json';
$cfg = json_decode(file_get_contents($cfgPath) ?: '{}', true) ?? [];

// Merge site fields
$siteFields = ['name', 'description', 'url'];
foreach ($siteFields as $field) {
    if (isset($patch['site'][$field])) {
        $cfg['site'][$field] = (string) $patch['site'][$field];
    }
}

// Merge social fields
$socialFields = ['twitter', 'facebook', 'instagram', 'share_image', 'keywords'];
foreach ($socialFields as $field) {
    if (isset($patch['social'][$field])) {
        $cfg['social'][$field] = (string) $patch['social'][$field];
    }
}

if (isset($patch['social']['categories'])) {
    if (is_array($patch['social']['categories'])) {
        $cfg['social']['categories'] = array_values(array_filter(array_map('strval', $patch['social']['categories']), static function ($value) {
            return trim($value) !== '';
        }));
    } else {
        $parts = array_map('trim', explode(',', (string) $patch['social']['categories']));
        $cfg['social']['categories'] = array_values(array_filter($parts, static function ($value) {
            return $value !== '';
        }));
    }
}

unset($cfg['content'], $cfg['build']);

$json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to encode config']);
    exit;
}

if (file_put_contents($cfgPath, $json) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to write config file']);
    exit;
}

$state = bandpromo_mark_build_required('web_config_changed');
echo json_encode([
    'ok' => true,
    'build_required' => true,
    'build_required_state' => $state,
]);
