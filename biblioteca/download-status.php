<?php
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/download-token-helpers.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Download token is required']);
    exit;
}

$status = bandpromo_download_status_read($root, $token);
if ($status === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Download status is unavailable']);
    exit;
}

$username = trim((string) ($_SESSION['username'] ?? ''));
if ($username === '' || (string) ($status['username'] ?? '') !== $username) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin privileges required']);
    exit;
}

echo json_encode([
    'ok' => true,
    'state' => (string) ($status['state'] ?? 'pending'),
    'download_name' => (string) ($status['download_name'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
