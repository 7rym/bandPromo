<?php
/**
 * Complete Setup — writes data/.setup_complete marker.
 * Called from setup wizard "Finish" button.
 * Requires active authenticated session.
 */

session_start();
header('Content-Type: application/json');

if (empty($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$marker = __DIR__ . '/../data/.setup_complete';

if (file_put_contents($marker, date('c')) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write setup marker. Check folder permissions.']);
    exit;
}

echo json_encode(['ok' => true]);
