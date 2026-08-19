<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/campaign-package.php';
require_once __DIR__ . '/campaign-storage.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$raw = file_get_contents('php://input');
$decoded = json_decode($raw ?: '{}', true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$root = dirname(__DIR__);
$sourceReleaseId = bandpromo_campaign_normalize_id((string) ($decoded['release_id'] ?? ''));
$title = trim((string) ($decoded['title'] ?? ''));
if ($sourceReleaseId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'release_id is required.']);
    exit;
}

try {
    $result = bandpromo_campaign_duplicate($root, $sourceReleaseId, $title);

    bandpromo_admin_audit_log('release_campaign_duplicated', [
        'target_type' => 'release',
        'target_id' => (string) ($result['release_id'] ?? ''),
        'status' => 'ok',
        'data' => [
            'source_release_id' => $sourceReleaseId,
            'brand_id' => (string) ($result['brand_id'] ?? ''),
            'playlists' => count($result['playlists'] ?? []),
            'galleries' => count($result['galleries'] ?? []),
            'pages' => count($result['pages'] ?? []),
        ],
    ]);

    $registry = bandpromo_campaign_admin_registry_entries($root);
    echo json_encode([
        'ok' => true,
        'release_id' => $result['release_id'],
        'brand_id' => $result['brand_id'],
        'message' => $result['message'],
        'playlists' => $result['playlists'],
        'galleries' => $result['galleries'],
        'pages' => $result['pages'],
        'releases' => $registry,
        'campaigns' => $registry,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
