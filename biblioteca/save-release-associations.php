<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/release-ownership-helpers.php';
require_once __DIR__ . '/build-required.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
$payload = json_decode(is_string($body) ? $body : '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

$root = dirname(__DIR__);
$releaseId = bandpromo_release_normalize_id((string) ($_GET['release'] ?? ($payload['release_id'] ?? '')));
$kind = strtolower(trim((string) ($payload['kind'] ?? $_GET['kind'] ?? '')));
$activeIds = $payload['ids'] ?? $payload['active'] ?? [];
if (!is_array($activeIds)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Expected an ids array.']);
    exit;
}

try {
    bandpromo_release_ensure_seeded($root);
    $saved = bandpromo_release_save_associations($root, $releaseId, $kind, $activeIds);
    $kind = bandpromo_release_association_normalize_kind($kind);

    bandpromo_admin_audit_log('release_associations_saved', [
        'target_type' => 'release',
        'target_id' => $releaseId,
        'status' => 'ok',
        'data' => [
            'kind' => $kind,
            'count' => count($saved['active']),
            'changed' => (int) ($saved['changed'] ?? 0),
        ],
    ]);

    $buildState = bandpromo_mark_build_required('release_associations_changed');

    echo json_encode([
        'ok' => true,
        'release_id' => $releaseId,
        'kind' => $kind,
        'active' => $saved['active'],
        'available' => $saved['available'],
        'changed' => (int) ($saved['changed'] ?? 0),
        'ownership_children' => bandpromo_release_ownership_children($root, $releaseId),
        'build_required' => true,
        'build_required_state' => $buildState,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
