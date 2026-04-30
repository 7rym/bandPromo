<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // Also allow setup session (setup wizard uses $_SESSION['user'])
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/build-required.php';

$state = bandpromo_get_build_required_state();
echo json_encode([
    'ok' => true,
    'build_required' => !empty($state['required']),
    'build_required_state' => $state,
], JSON_UNESCAPED_UNICODE);
exit;
