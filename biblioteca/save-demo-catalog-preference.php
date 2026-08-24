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
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Expected JSON body with a hidden or visible boolean.']);
    exit;
}

$root = dirname(__DIR__);
bandpromo_demo_campaign_ensure_preferences($root);

$hidden = null;
if (array_key_exists('hidden', $body)) {
    $hidden = (bool) $body['hidden'];
} elseif (array_key_exists('visible', $body)) {
    $hidden = !(bool) $body['visible'];
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Expected JSON body with a hidden or visible boolean.']);
    exit;
}

$visible = !$hidden;

if ($hidden) {
    if (!bandpromo_demo_catalog_install_has_operator_content($root)) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'error' => 'Hide the demo campaign after you have a campaign with a track on a playlist.',
            'demo_release_id' => bandpromo_demo_campaign_id($root),
            'demo_campaign_id' => bandpromo_demo_campaign_id($root),
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

$keptVisible = $hidden ? bandpromo_demo_campaign_assets_kept_visible($root) : [];
$keptOperator = 0;
$keptBrand = 0;
foreach ($keptVisible as $row) {
    if (!is_array($row)) {
        continue;
    }
    if (($row['reason'] ?? '') === 'brand') {
        $keptBrand++;
    } else {
        $keptOperator++;
    }
}

bandpromo_admin_audit_log($visible ? 'demo_catalog_shown' : 'demo_catalog_hidden', [
    'target_type' => 'install_preference',
    'target_id' => 'demo_release_hidden',
    'data' => [
        'visible' => $visible,
        'hidden' => $hidden,
        'demo_release_hidden' => $hidden,
        'demo_release_id' => bandpromo_demo_campaign_id($root),
        'kept_visible' => count($keptVisible),
    ],
]);

$warning = '';
if ($hidden && ($keptOperator > 0 || $keptBrand > 0)) {
    $parts = [];
    if ($keptOperator > 0) {
        $parts[] = $keptOperator === 1
            ? '1 demo asset stays visible because your catalogue still uses it'
            : $keptOperator . ' demo assets stay visible because your catalogue still uses them';
    }
    if ($keptBrand > 0) {
        $parts[] = $keptBrand === 1
            ? '1 demo Brand shell asset stays visible while a Brand still uses it'
            : $keptBrand . ' demo Brand shell assets stay visible while Brands still use them';
    }
    $warning = implode('. ', $parts) . '.';
}

echo json_encode([
    'ok' => true,
    'demo_catalog_visible' => $visible,
    'demo_release_hidden' => $hidden,
    'demo_release_id' => bandpromo_demo_campaign_id($root),
    'demo_campaign_id' => bandpromo_demo_campaign_id($root),
    'kept_visible' => $keptVisible,
    'kept_visible_count' => count($keptVisible),
    'warning' => $warning,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
