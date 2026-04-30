<?php
/**
 * Secure Gallery Items API
 * 
 * Returns gallery items from gallery.json for authenticated users
 * Blocks direct browser access to raw JSON file
 * 
 * Security checks:
 * - Session authentication required
 * - HTTP header validation
 */

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

session_start();

// Require authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check for direct browser access (reject with 403)
$accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
$is_browser_request = (
    strpos($accept, 'text/html') !== false && 
    strpos($accept, 'application/json') === false
);

if ($is_browser_request) {
    http_response_code(403);
    die('Access Forbidden');
}

// Path to gallery file
$galleryFile = dirname(__DIR__) . '/data/gallery.json';

// Return error if file doesn't exist
if (!file_exists($galleryFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Missing runtime file data/gallery.json. Run setup.']);
    exit;
}

// Read gallery data
$galleryData = json_decode(file_get_contents($galleryFile), true);

if (!is_array($galleryData)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid gallery data']);
    exit;
}

// Return gallery items as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'items' => $galleryData,
    'totalItems' => count($galleryData)
]);
?>
