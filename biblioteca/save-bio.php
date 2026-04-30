<?php
/**
 * Save bio.php content.
 * Accepts raw HTML/text via POST body. Admin-only.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false) {
    echo json_encode(['error' => 'Could not read request body']);
    exit;
}

$bio_file = dirname(__DIR__) . '/data/bio.html';

// Ensure data dir exists
if (!is_dir(dirname($bio_file))) {
    mkdir(dirname($bio_file), 0750, true);
}

if (file_put_contents($bio_file, $body) === false) {
    echo json_encode(['error' => 'Could not write data/bio.html — check file permissions']);
    exit;
}

echo json_encode(['ok' => true]);
