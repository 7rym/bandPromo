<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/release-ownership-helpers.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$releaseId = bandpromo_release_normalize_id((string) ($_GET['release'] ?? ''));
$kind = strtolower(trim((string) ($_GET['kind'] ?? '')));

if ($releaseId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Release id is required.']);
    exit;
}

try {
    bandpromo_release_ensure_seeded($root);
    $pools = bandpromo_release_association_pools($root, $releaseId, $kind);
    echo json_encode([
        'ok' => true,
        'release_id' => $releaseId,
        'kind' => bandpromo_release_association_normalize_kind($kind),
        'active' => $pools['active'],
        'available' => $pools['available'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
