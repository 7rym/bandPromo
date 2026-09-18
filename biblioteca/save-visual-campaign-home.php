<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/csrf.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty request body']);
    exit;
}

$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (!validate_csrf_token(trim((string) ($payload['csrf_token'] ?? '')))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$assetIds = $payload['asset_ids'] ?? [];
if (!is_array($assetIds)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'asset_ids must be an array']);
    exit;
}

$campaignId = trim((string) ($payload['campaign_id'] ?? $payload['release_id'] ?? ''));
$root = dirname(__DIR__);

$result = bandpromo_campaign_set_visual_homes($root, $assetIds, $campaignId);
$ok = !empty($result['ok']) || (int) ($result['updated'] ?? 0) > 0;

bandpromo_admin_audit_log(
    $campaignId === '' || $campaignId === 'orphans' ? 'visual_campaign_home_clear' : 'visual_campaign_home_set',
    [
        'target_type' => 'visual_asset',
        'target_id' => $campaignId !== '' ? $campaignId : 'orphan',
        'data' => [
            'updated' => (int) ($result['updated'] ?? 0),
            'asset_ids' => array_values(array_map('strval', $assetIds)),
            'errors' => $result['errors'] ?? [],
        ],
    ]
);

if (!$ok && (int) ($result['updated'] ?? 0) === 0) {
    http_response_code(400);
}

echo json_encode([
    'ok' => $ok,
    'updated' => (int) ($result['updated'] ?? 0),
    'campaign_id' => bandpromo_campaign_normalize_id($campaignId),
    'errors' => $result['errors'] ?? [],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
