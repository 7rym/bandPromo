<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/demo-catalog-state.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body) || !array_key_exists('visible', $body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Expected JSON body with a visible boolean.']);
    exit;
}

$root = dirname(__DIR__);
bandpromo_demo_release_ensure_preferences($root);
$visible = (bool) $body['visible'];
$hiding = !$visible;

if ($hiding) {
    $blockers = bandpromo_demo_release_hide_blockers($root);
    if ($blockers !== []) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'error' => 'Cannot hide the demo catalog while demo campaign assets are still used outside the demo release. Remove or replace those references first.',
            'hide_blockers' => $blockers,
            'demo_release_id' => bandpromo_demo_release_id($root),
            'demo_catalog_visible' => true,
            'demo_release_hidden' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!bandpromo_demo_catalog_set_visible($root, $visible)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save install preference. Check data/ permissions.']);
    exit;
}

bandpromo_admin_audit_log($visible ? 'demo_catalog_shown' : 'demo_catalog_hidden', [
    'target_type' => 'install_preference',
    'target_id' => 'demo_release_hidden',
    'data' => [
        'visible' => $visible,
        'demo_release_hidden' => !$visible,
        'demo_release_id' => bandpromo_demo_release_id($root),
    ],
]);

echo json_encode([
    'ok' => true,
    'demo_catalog_visible' => $visible,
    'demo_release_hidden' => !$visible,
    'demo_release_id' => bandpromo_demo_release_id($root),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
