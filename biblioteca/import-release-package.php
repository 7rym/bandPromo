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

$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ]);
    exit;
}

$root = dirname(__DIR__);
$file = $_FILES['package'] ?? null;
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Upload a portable release package (.prp or .zip).']);
    exit;
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$originalName = (string) ($file['name'] ?? 'package.prp');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid upload.']);
    exit;
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, ['prp', 'zip'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Release packages must be .prp or .zip files.']);
    exit;
}

$collision = strtolower(trim((string) ($_POST['collision'] ?? 'refuse')));
if ($collision === 'skip-existing') {
    $collision = 'skip';
}
if (!in_array($collision, ['refuse', 'overwrite', 'skip', 'allocate'], true)) {
    $collision = 'refuse';
}

try {
    $result = bandpromo_release_campaign_import_from_zip($root, $tmpName, [
        'mode' => 'operator',
        'allow_demo_overwrite' => false,
        'set_active_brand' => false,
        'collision' => $collision,
    ]);

    bandpromo_admin_audit_log('release_package_imported', [
        'target_type' => 'release',
        'target_id' => (string) ($result['release_id'] ?? ''),
        'status' => 'ok',
        'data' => [
            'imported_files' => (int) ($result['imported_files'] ?? 0),
            'filename' => $originalName,
            'collision' => (string) ($result['collision'] ?? $collision),
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'release_id' => $result['release_id'],
        'message' => $result['message'],
        'imported_files' => $result['imported_files'],
        'collision' => $result['collision'] ?? $collision,
        'releases' => bandpromo_release_admin_registry_entries($root),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
