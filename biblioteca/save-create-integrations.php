<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/create-integrations.php';

bandpromo_enforce_https();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Expected JSON body.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$csrfToken = (string) ($payload['csrf_token'] ?? '');
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$username = trim((string) ($_SESSION['username'] ?? ''));
if ($username === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!isDeveloperUser($username)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Create is developer-only for now.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

session_write_close();

$root = dirname(__DIR__);
$current = bandpromo_create_integrations_load($root, $username);
$patch = [];
$changed = [];

if (array_key_exists('openai_api_key', $payload)) {
    $value = trim((string) $payload['openai_api_key']);
    // Blank field means “leave unchanged” unless clear_openai is set.
    if ($value !== '') {
        $patch['openai_api_key'] = $value;
        $changed[] = 'openai';
    } elseif (!empty($payload['clear_openai'])) {
        $patch['openai_api_key'] = '';
        $changed[] = 'openai_cleared';
    }
}

if (array_key_exists('fal_api_key', $payload)) {
    $value = trim((string) $payload['fal_api_key']);
    if ($value !== '') {
        $patch['fal_api_key'] = $value;
        $changed[] = 'fal';
    } elseif (!empty($payload['clear_fal'])) {
        $patch['fal_api_key'] = '';
        $changed[] = 'fal_cleared';
    }
}

if ($patch === []) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Nothing to save. Paste a new key, or use Clear for a service.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!bandpromo_create_integrations_save($root, $username, $patch)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not save API keys. Check data/ permissions.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

bandpromo_admin_audit_log('create_integrations_saved', [
    'target_type' => 'admin_integrations',
    'target_id' => bandpromo_create_integrations_sanitize_username($username),
    'data' => [
        'changed' => $changed,
        'had_openai' => trim((string) $current['openai_api_key']) !== '',
        'had_fal' => trim((string) $current['fal_api_key']) !== '',
    ],
]);

$status = bandpromo_create_integrations_status_payload($root, $username);

echo json_encode([
    'ok' => true,
    'integrations' => $status,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
