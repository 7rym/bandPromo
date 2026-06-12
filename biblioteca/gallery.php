<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/gallery-helpers.php';
bandpromo_enforce_https();

session_start();

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

// Load gallery data
$root_dir = dirname(__DIR__);
$galleryFile = bandpromo_gallery_file_path($root_dir);

if (!file_exists($galleryFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing runtime file data/gallery.json. Run setup.']);
    exit;
}

$galleryRaw = file_get_contents($galleryFile);
$galleryData = $galleryRaw !== false ? json_decode($galleryRaw, true) : null;

if (!is_array($galleryData)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid gallery data']);
    exit;
}

$galleryData = bandpromo_gallery_normalize_items($root_dir, $galleryData);

// Return gallery data as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'images' => $galleryData,
    'totalImages' => count($galleryData)
]);
?>
