<?php
/**
 * Secure Configuration API
 * 
 * Returns web-config.json only to legitimate requests (JS/API)
 * Blocks direct browser access with 403 Forbidden
 * 
 * Security checks:
 * - Rejects requests with Accept: text/html (browser direct access)
 * - Allows requests from JavaScript (application/json, * / *, XMLHttpRequest)
 * - Returns appropriate HTTP headers and status codes
 */

// Disable output buffering to ensure clean headers
@ob_end_clean();

// Require authentication
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get HTTP headers
$accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$x_requested_with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';

// Check if this is a direct browser request (human trying to view JSON in browser)
$is_browser_request = (
    // Check if Accept header indicates HTML (browser)
    (strpos($accept, 'text/html') !== false && strpos($accept, 'application/json') === false) ||
    // Check for browser user agents without XMLHttpRequest header
    (empty($x_requested_with) && !preg_match('/curl|wget|python|java(?!script)|go-http|postman/i', $user_agent))
);

// Reject direct browser access
if ($is_browser_request) {
    http_response_code(403);
    die('Access Forbidden');
}

// Load configuration
$root_dir = dirname(dirname(__FILE__));
$config_file = $root_dir . '/web-config.json';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');

// Return config or error
if (file_exists($config_file)) {
    try {
        $config_json = file_get_contents($config_file);
        $decoded = json_decode($config_json);
        
        if ($decoded === null) {
            http_response_code(500);
            echo json_encode(array('error' => 'Invalid configuration format'));
            exit;
        }
        
        // Strip sensitive keys before returning
        unset($decoded->admins);
        http_response_code(200);
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array('error' => 'Failed to read configuration'));
        exit;
    }
} else {
    http_response_code(404);
    echo json_encode(array('error' => 'Configuration not found'));
    exit;
}
?>
