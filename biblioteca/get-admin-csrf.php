<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/csrf.php';
bandpromo_enforce_https();

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

echo json_encode([
    'ok' => true,
    'csrf_token' => get_csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);