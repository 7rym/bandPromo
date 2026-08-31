<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/security-sanity-helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isDeveloperUser($_SESSION['username'] ?? '')) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'secure' => false,
        'error' => 'Developer access required.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$root = dirname(__DIR__);

try {
    $report = bandpromo_security_sanity_check($root);
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'secure' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
