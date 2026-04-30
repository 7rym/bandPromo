<?php
require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

session_start();

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

// Load gallery data
$galleryFile = dirname(__DIR__) . '/data/gallery.json';

if (!file_exists($galleryFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing runtime file data/gallery.json. Run setup.']);
    exit;
}

$galleryData = json_decode(file_get_contents($galleryFile), true);

if (!is_array($galleryData)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid gallery data']);
    exit;
}

// Return gallery data as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'images' => $galleryData,
    'totalImages' => count($galleryData)
]);
?>
