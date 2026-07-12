<?php
/**
 * Setup Init — creates the first admin user and starts the session.
 * Only works when no users exist yet (data/terces absent or empty).
 * Called via POST with JSON body: { "username": "...", "password": "..." }
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/array-helpers.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/setup-state.php';
require_once __DIR__ . '/template-bootstrap.php';
require_once __DIR__ . '/environment-checks.php';

if (bandpromo_is_setup_complete()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Setup is already complete for this installation.']);
    exit;
}

// If user already exists, try to authenticate with provided credentials
// (handles page refresh during setup without losing progress)
if (file_exists(TERCES_FILE) && filesize(TERCES_FILE) > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $responsibilityAcknowledged = !empty($body['responsibility_acknowledged']);
        if ($username && $password && authenticate($username, $password) && isAdminUser($username)) {
                    if (!$responsibilityAcknowledged) {
                        http_response_code(400);
                        echo json_encode(['ok' => false, 'error' => 'Please confirm the license and operator responsibility note before continuing.']);
                        exit;
                    }
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['setup_in_progress'] = true;
                    $_SESSION['setup_acknowledgment'] = [
                        'accepted' => true,
                        'accepted_at' => date('c'),
                        'acknowledgment_version' => 'setup-operator-ack-v1',
                    ];
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
$responsibilityAcknowledged = !empty($body['responsibility_acknowledged']);

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
if (!$responsibilityAcknowledged) {
    echo json_encode(['ok' => false, 'error' => 'Please confirm the license and operator responsibility note before continuing.']);
    exit;
}

if (!bandpromo_environment_pdo_sqlite_available()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => bandpromo_environment_pdo_sqlite_setup_error()]);
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

$cfg = bandpromo_deep_merge($base, $existing);
bandpromo_sync_scoped_config_fields($cfg, ['site', 'social', 'media']);

file_put_contents($configPath, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// Auto-login
$_SESSION['authenticated'] = true;
$_SESSION['username'] = $username;
$_SESSION['setup_in_progress'] = true;
$_SESSION['setup_acknowledgment'] = [
    'accepted' => true,
    'accepted_at' => date('c'),
    'acknowledgment_version' => 'setup-operator-ack-v1',
];

echo json_encode(['ok' => true]);
