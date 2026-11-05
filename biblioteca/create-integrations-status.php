<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/create-integrations.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$username = trim((string) ($_SESSION['username'] ?? ''));
if ($username === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!isDeveloperUser($username)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Create is developer-only for now.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$root = dirname(__DIR__);
$status = bandpromo_create_integrations_status_payload($root, $username);

echo json_encode([
    'ok' => true,
    'integrations' => $status,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
