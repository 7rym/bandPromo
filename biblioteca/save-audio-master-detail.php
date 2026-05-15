<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/light-build-tasks.php';
bandpromo_enforce_https();

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$csrf_token = (string) ($payload['csrf_token'] ?? '');
if (!validate_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$filename = trim((string) ($payload['filename'] ?? ''));
if ($filename === '' || strpbrk($filename, '/\\') !== false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

$fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
$allowed_keys = ['title', 'artist', 'album', 'date', 'tracknumber', 'genre', 'comment', 'lyrics'];
$normalized_fields = [];
foreach ($allowed_keys as $key) {
    $value = $fields[$key] ?? '';
    $normalized_fields[$key] = trim((string) $value);
}

session_write_close();

$result = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
    'action' => 'update',
    'filename' => $filename,
    'fields' => $normalized_fields,
]);

$data = is_array($result['data'] ?? null) ? $result['data'] : null;
if (!$result['ok'] || !is_array($data) || empty($data['ok'])) {
    $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
    $output = trim((string) ($result['output'] ?? ''));
    $message = $error !== '' ? $error : ($output !== '' ? $output : 'Could not save audio master details');
    http_response_code(500);
    bandpromo_admin_audit_log('audio_master_metadata_saved', [
        'target_type' => 'audio_master',
        'target_id' => $filename,
        'status' => 'error',
        'data' => ['error' => $message],
    ]);
    echo json_encode(['error' => $message]);
    exit;
}

$build_state = bandpromo_mark_build_required('media_audio_master_changed');

bandpromo_admin_audit_log('audio_master_metadata_saved', [
    'target_type' => 'audio_master',
    'target_id' => $filename,
    'status' => 'ok',
    'data' => [
        'title' => $normalized_fields['title'],
        'artist' => $normalized_fields['artist'],
        'album' => $normalized_fields['album'],
    ],
]);

echo json_encode([
    'ok' => true,
    'detail' => $data,
    'build_required' => true,
    'build_required_state' => $build_state,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);