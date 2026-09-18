<?php
declare(strict_types=1);

/**
 * Status → Proposed treatment: stamp one multi-campaign orphan to an operator-chosen home.
 */

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/campaign-storage.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = [];
if (is_string($raw) && trim($raw) !== '') {
    $parsed = json_decode($raw, true);
    if (is_array($parsed)) {
        $payload = $parsed;
    }
}

$csrfToken = trim((string) ($payload['csrf_token'] ?? ''));
if ($csrfToken === '' || !validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired or invalid request token. Refresh admin and try again.']);
    exit;
}

$assetId = trim((string) ($payload['asset_id'] ?? ''));
$campaignId = trim((string) ($payload['campaign_id'] ?? $payload['release_id'] ?? ''));
$root = dirname(__DIR__);

$result = bandpromo_site_health_assign_orphan_home($root, $assetId, $campaignId);

bandpromo_admin_audit_log(
    !empty($result['ok']) ? 'site_health_orphan_home_assign' : 'site_health_orphan_home_assign_failed',
    [
        'target_type' => 'asset',
        'target_id' => $assetId !== '' ? $assetId : 'unknown',
        'data' => [
            'campaign_id' => (string) ($result['campaign_id'] ?? ''),
            'kind' => (string) ($result['kind'] ?? ''),
            'error' => (string) ($result['error'] ?? ''),
        ],
    ]
);

if (empty($result['ok'])) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => (string) ($result['error'] ?? 'Could not set catalogue home.'),
        'asset_id' => (string) ($result['asset_id'] ?? ''),
        'campaign_id' => (string) ($result['campaign_id'] ?? ''),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'asset_id' => (string) ($result['asset_id'] ?? ''),
    'campaign_id' => (string) ($result['campaign_id'] ?? ''),
    'kind' => (string) ($result['kind'] ?? ''),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
