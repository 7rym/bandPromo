<?php
/**
 * Setup Init — creates the first admin user and starts the session.
 * Only works when no users exist yet (data/terces absent or empty).
 * Called via POST with JSON body: { "username": "...", "password": "..." }
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/template-bootstrap.php';

// If user already exists, try to authenticate with provided credentials
// (handles page refresh during setup without losing progress)
if (file_exists(TERCES_FILE) && filesize(TERCES_FILE) > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        if ($username && $password && authenticate($username, $password) && isAdminUser($username)) {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            echo json_encode(['ok' => true]);
            exit;
        }
    }
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'An admin account already exists. Wrong password?']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON.']);
    exit;
}

$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if ($username === '') {
    echo json_encode(['ok' => false, 'error' => 'Username is required.']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $username)) {
    echo json_encode(['ok' => false, 'error' => 'Username may only contain letters, numbers, _ and -.']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['ok' => false, 'error' => 'Password must be at least 6 characters.']);
    exit;
}

// Ensure data/ directory exists
$dataDir = dirname(TERCES_FILE);
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0750, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not create data directory.']);
        exit;
    }
}

if (!setUser($username, $password, 'admin')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to write user file.']);
    exit;
}

$templateErrors = bandpromo_ensure_runtime_files_seeded();
if (!empty($templateErrors)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => implode(' | ', $templateErrors)]);
    exit;
}

// Register the first user as admin in web-config.json.
// Always deep-merge: template provides the full structure as base,
// any existing config values overlay on top.
$configPath  = __DIR__ . '/../web-config.json';
$examplePath = __DIR__ . '/templates/web-config.template.json';

$base     = file_exists($examplePath) ? (json_decode(file_get_contents($examplePath), true) ?? []) : [];
$existing = file_exists($configPath)  ? (json_decode(file_get_contents($configPath),  true) ?? []) : [];

function setup_deep_merge(array $base, array $overlay): array {
    foreach ($overlay as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = setup_deep_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

$cfg = setup_deep_merge($base, $existing);

file_put_contents($configPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// Auto-login
$_SESSION['authenticated'] = true;
$_SESSION['username'] = $username;

echo json_encode(['ok' => true]);
