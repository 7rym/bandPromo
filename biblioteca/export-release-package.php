<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/release-campaign-package.php';
require_once __DIR__ . '/release-storage.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$raw = file_get_contents('php://input');
$decoded = json_decode($raw ?: '{}', true);
if (!is_array($decoded)) {
    $decoded = $_POST;
}

$csrfToken = trim((string) ($decoded['csrf_token'] ?? ''));
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ]);
    exit;
}

$root = dirname(__DIR__);
$releaseId = bandpromo_release_normalize_id((string) ($decoded['release_id'] ?? ''));
if ($releaseId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'release_id is required.']);
    exit;
}

try {
    bandpromo_release_load_document($root, $releaseId);
} catch (Throwable $throwable) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Unknown release.']);
    exit;
}

$backupsDir = $root . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupsDir) && !mkdir($backupsDir, 0750, true) && !is_dir($backupsDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create backups directory.']);
    exit;
}

$safeStamp = gmdate('Ymd-His');
$zipName = 'release-package-' . $releaseId . '-' . $safeStamp . '.zip';
$zipPath = $backupsDir . DIRECTORY_SEPARATOR . $zipName;

try {
    $result = bandpromo_release_campaign_export_to_zip($root, $releaseId, $zipPath);

    bandpromo_admin_audit_log('release_package_exported', [
        'target_type' => 'release',
        'target_id' => $releaseId,
        'status' => 'ok',
        'data' => [
            'files' => (int) ($result['files'] ?? 0),
            'asset_ids' => count($result['asset_ids'] ?? []),
            'filename' => $zipName,
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'release_id' => $releaseId,
        'filename' => $zipName,
        'path' => 'backups/' . $zipName,
        'files' => (int) ($result['files'] ?? 0),
        'asset_count' => count($result['asset_ids'] ?? []),
        'message' => 'Release package ready under backups/' . $zipName . '.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
