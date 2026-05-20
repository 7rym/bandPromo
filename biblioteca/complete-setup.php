<?php
/**
 * Complete Setup — writes data/.setup_complete marker.
 * Called from setup wizard "Finish" button.
 * Requires active authenticated session.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/setup-state.php';

if (empty($_SESSION['authenticated'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

if (bandpromo_is_setup_complete()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Setup is already complete for this installation.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

if (empty($_SESSION['setup_in_progress'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Setup can only be completed from an active setup session.']);
    exit;
}

$acknowledgment = $_SESSION['setup_acknowledgment'] ?? null;
if (!is_array($acknowledgment) || empty($acknowledgment['accepted'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Setup acknowledgment is required before setup can be completed.']);
    exit;
}

$versionFile = __DIR__ . '/../VERSION';
$version = file_exists($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$currentSiteUrl = $host !== '' ? $scheme . '://' . $host : null;
$configuredSiteUrl = bandpromo_config_get_nonempty_value($config, 'install.site.url', null);
$configuredSiteUrl = is_string($configuredSiteUrl) && trim($configuredSiteUrl) !== '' ? trim($configuredSiteUrl) : null;
$ackFile = __DIR__ . '/../data/operator-acknowledgment.json';
$ackRecord = [
    'accepted' => true,
    'accepted_at' => $acknowledgment['accepted_at'] ?? date('c'),
    'acknowledgment_version' => $acknowledgment['acknowledgment_version'] ?? 'setup-operator-ack-v1',
    'bandpromo_version' => $version,
    'username' => $_SESSION['username'] ?? null,
    'source' => 'setup-wizard',
    'host' => $host !== '' ? $host : null,
    'current_site_url' => $currentSiteUrl,
    'configured_site_url' => $configuredSiteUrl,
    'documents' => [
        'LICENSE',
        'docs/OPERATOR-RESPONSIBILITY.md',
    ],
];

$ackJson = json_encode($ackRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($ackJson === false || file_put_contents($ackFile, $ackJson) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write setup acknowledgment record. Check folder permissions.']);
    exit;
}

$marker = __DIR__ . '/../data/.setup_complete';

if (file_put_contents($marker, date('c')) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write setup marker. Check folder permissions.']);
    exit;
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

echo json_encode(['ok' => true]);
