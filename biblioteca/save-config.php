<?php
/**
 * Save Config — merges submitted fields into web-config.json.
 * Requires an active authenticated session.
 * Called via POST with JSON body:
 *   {
 *     "site": { "name", "short_name", "description", "url", "author" }
 *   }
 * Fields not present in the body are left unchanged.
 */

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/template-bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$templateErrors = bandpromo_ensure_runtime_files_seeded();
if (!empty($templateErrors)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => implode(' | ', $templateErrors)]);
    exit;
}

define('CONFIG_FILE',    __DIR__ . '/../web-config.json');
define('CONFIG_EXAMPLE', __DIR__ . '/templates/web-config.template.json');

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON.']);
    exit;
}

// Load config — always use example as the structural base, then overlay
// existing values so no sections are ever missing after setup.
$base   = [];
$existing = [];

if (file_exists(CONFIG_EXAMPLE)) {
    $base = json_decode(file_get_contents(CONFIG_EXAMPLE), true) ?? [];
}
if (file_exists(CONFIG_FILE)) {
    $existing = json_decode(file_get_contents(CONFIG_FILE), true) ?? [];
}

// Deep-merge: example provides all keys, existing values win where present
function deep_merge(array $base, array $overlay): array {
    foreach ($overlay as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = deep_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

$config = deep_merge($base, $existing);

// Merge site fields
$siteFields = ['name', 'short_name', 'description', 'url', 'author', 'language'];
if (isset($body['site']) && is_array($body['site'])) {
    if (!isset($config['site'])) $config['site'] = [];
    foreach ($siteFields as $field) {
        if (array_key_exists($field, $body['site'])) {
            $value = trim((string)$body['site'][$field]);
            if ($value !== '') {
                $config['site'][$field] = $value;
            }
        }
    }
}

// Merge branding fields — validate hex colors
$brandingFields = ['theme_color', 'background_color', 'accent_color'];
if (isset($body['branding']) && is_array($body['branding'])) {
    if (!isset($config['branding'])) $config['branding'] = [];
    foreach ($brandingFields as $field) {
        if (array_key_exists($field, $body['branding'])) {
            $value = trim((string)$body['branding'][$field]);
            if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
                $config['branding'][$field] = $value;
            }
        }
    }
}

bandpromo_sync_scoped_config_fields($config, ['site', 'social', 'media']);

// Write back
$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents(CONFIG_FILE, $json) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to write config file.']);
    exit;
}

$state = bandpromo_mark_build_required('web_config_changed');
bandpromo_admin_audit_log('config_saved', [
    'target_type' => 'config',
    'target_id' => 'web-config.json:legacy',
    'status' => 'warning',
    'data' => ['build_required' => true, 'reasons' => $state['reasons'] ?? []],
]);
echo json_encode([
    'ok' => true,
    'build_required' => true,
    'build_required_state' => $state,
]);
