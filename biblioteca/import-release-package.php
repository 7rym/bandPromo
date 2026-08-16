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

$importCompleted = false;
register_shutdown_function(static function () use (&$importCompleted): void {
    if ($importCompleted) {
        return;
    }
    $err = error_get_last();
    if (!is_array($err)) {
        return;
    }
    $type = (int) ($err['type'] ?? 0);
    if (!in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $message = trim((string) ($err['message'] ?? 'Import aborted.'));
    if ($message === '') {
        $message = 'Import aborted.';
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Import aborted: ' . $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
    $postMax = (string) ini_get('post_max_size');
    $uploadMax = (string) ini_get('upload_max_filesize');
    http_response_code(413);
    echo json_encode([
        'ok' => false,
        'error' => 'Package is larger than this host allows (post_max_size='
            . $postMax . ', upload_max_filesize=' . $uploadMax
            . '). Raise those limits or import from disk on the server.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
if (!is_array($file)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Upload a portable release package (.prp or .zip).']);
    exit;
}

$uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
    $postMax = (string) ini_get('post_max_size');
    $uploadMax = (string) ini_get('upload_max_filesize');
    $error = match ($uploadError) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Package exceeds PHP upload limits (upload_max_filesize='
            . $uploadMax . ', post_max_size=' . $postMax . ').',
        UPLOAD_ERR_PARTIAL => 'Upload was interrupted before the package finished transferring.',
        UPLOAD_ERR_NO_FILE => 'Upload a portable release package (.prp or .zip).',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded package.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
        default => 'Upload failed (error code ' . $uploadError . ').',
    };
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

@set_time_limit(0);

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
    $importCompleted = true;
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $importCompleted = true;
}
