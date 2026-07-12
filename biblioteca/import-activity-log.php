<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/activity-log-portability.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'POST required.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    bandpromo_activity_log_require_developer_role();
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$csrfToken = '';
$mode = 'merge';
$package = null;

$contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (str_starts_with($contentType, 'multipart/form-data')) {
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $mode = isset($_POST['mode']) ? (string) $_POST['mode'] : 'merge';
    if (!isset($_FILES['package']) || !is_array($_FILES['package'])) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Choose an activity log package file to import.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $upload = $_FILES['package'];
    $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'Upload failed (code ' . $errorCode . ').',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $tmpPath = (string) ($upload['tmp_name'] ?? '');
    $raw = is_string($tmpPath) && $tmpPath !== '' ? file_get_contents($tmpPath) : false;
} else {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode(is_string($rawBody) ? $rawBody : '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    $csrfToken = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';
    $mode = isset($payload['mode']) ? (string) $payload['mode'] : 'merge';
    if (isset($payload['package']) && is_array($payload['package'])) {
        $package = $payload['package'];
        $raw = false;
    } else {
        $raw = is_string($rawBody) ? $rawBody : '';
    }
}

if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$root = dirname(__DIR__);

try {
    if ($package === null) {
        if ($raw === false || !is_string($raw) || trim($raw) === '') {
            throw new RuntimeException('Activity log package file was empty.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Activity log package is not valid JSON.');
        }
        $package = $decoded;
    }

    $result = bandpromo_activity_log_import_package($root, $package, $mode);

    bandpromo_admin_audit_log('activity_log_imported', [
        'mode' => $result['mode'],
        'listener_imported' => $result['listener_imported'],
        'listener_skipped' => $result['listener_skipped'],
        'audit_imported' => $result['audit_imported'],
        'audit_skipped' => $result['audit_skipped'],
        'source_version' => $result['source_version'],
        'exported_at_utc' => $result['exported_at_utc'],
    ]);

    echo json_encode([
        'ok' => true,
        'message' => sprintf(
            'Imported %d listener and %d audit events (%s mode). Skipped %d listener and %d audit duplicates.',
            $result['listener_imported'],
            $result['audit_imported'],
            $result['mode'],
            $result['listener_skipped'],
            $result['audit_skipped']
        ),
        'result' => $result,
        'counts' => bandpromo_activity_log_store_counts($root),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    bandpromo_admin_audit_log('activity_log_import_failed', [
        'mode' => $mode,
        'error' => $e->getMessage(),
        'status' => 'error',
    ]);

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
