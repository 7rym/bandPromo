<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/audio-master-detail-helpers.php';
bandpromo_enforce_https();

function bandpromo_text_length(string $value): int {
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    if (function_exists('iconv_strlen')) {
        $length = iconv_strlen($value, 'UTF-8');
        if ($length !== false) {
            return $length;
        }
    }

    return strlen($value);
}

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
$allowed_keys = ['title', 'artist', 'album', 'date', 'tracknumber', 'bpm', 'initialkey', 'genre', 'comment', 'lyrics'];
$normalized_fields = [];
foreach ($allowed_keys as $key) {
    $value = $fields[$key] ?? '';
    $normalized_fields[$key] = trim((string) $value);
}

if ($normalized_fields['album'] === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Release name is required']);
    exit;
}

if (!bandpromo_audio_master_validate_release_date($normalized_fields['date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Release date must use YYYY or YYYY-MM-DD']);
    exit;
}

if ($normalized_fields['artist'] === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Artist is required']);
    exit;
}

if ($normalized_fields['title'] === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit;
}

if ($normalized_fields['bpm'] !== '' && !preg_match('/^\d{1,3}$/', $normalized_fields['bpm'])) {
    http_response_code(400);
    echo json_encode(['error' => 'BPM must be 1 to 3 digits']);
    exit;
}

if ($normalized_fields['tracknumber'] !== '' && !preg_match('/^\d{1,3}$/', $normalized_fields['tracknumber'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Track must be 1 to 3 digits']);
    exit;
}

if (bandpromo_text_length($normalized_fields['comment']) > 300) {
    http_response_code(400);
    echo json_encode(['error' => 'Track description must be 300 characters or fewer']);
    exit;
}

if ($normalized_fields['initialkey'] !== '' && bandpromo_text_length($normalized_fields['initialkey']) > 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Key must be 3 characters or fewer']);
    exit;
}

$cover_path = trim((string) ($payload['cover_path'] ?? ''));
$cover_mode = trim((string) ($payload['cover_mode'] ?? 'preserve'));
if (!in_array($cover_mode, ['preserve', 'set', 'clear'], true)) {
    $cover_mode = 'preserve';
}

session_write_close();

$root = dirname(__DIR__);
$inspect_result = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
    'action' => 'inspect',
    'filename' => $filename,
]);
$inspect_data = is_array($inspect_result['data'] ?? null) ? $inspect_result['data'] : null;
$existing_tracknumber = is_array($inspect_data) ? trim((string) ($inspect_data['tracknumber'] ?? '')) : '';
$playlist_map = bandpromo_audio_master_playlist_map($root);
$playlist_entry = is_array($playlist_map[$filename] ?? null) ? $playlist_map[$filename] : [];
$playlist_tracknumber = trim((string) ($playlist_entry['playlist_tracknumber'] ?? ''));
if ($normalized_fields['tracknumber'] === '') {
    $normalized_fields['tracknumber'] = $existing_tracknumber !== '' ? $existing_tracknumber : $playlist_tracknumber;
}

$current_fields = [];
foreach ($allowed_keys as $key) {
    $current_fields[$key] = is_array($inspect_data) ? trim((string) ($inspect_data[$key] ?? '')) : '';
}

$metadata_changed = $current_fields !== $normalized_fields;
$sidecar_cover = is_array($inspect_data) ? trim((string) ($inspect_data['sidecar_cover'] ?? '')) : '';
$cover_changed = ($cover_mode === 'clear' && $sidecar_cover !== '') || $cover_mode === 'set';

if (!$metadata_changed && !$cover_changed) {
    $data = is_array($inspect_data) ? $inspect_data : [];
    $data = bandpromo_audio_master_enrich_detail($root, $filename, $data);
    $build_state = bandpromo_get_build_required_state();

    bandpromo_admin_audit_log('audio_master_metadata_saved', [
        'target_type' => 'audio_master',
        'target_id' => $filename,
        'status' => 'ok',
        'data' => [
            'no_change' => true,
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'detail' => $data,
        'build_required' => !empty($build_state['required']),
        'build_required_state' => $build_state,
        'no_change' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

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

$cover_result = ['ok' => true];
if ($cover_mode === 'set') {
    $cover_result = bandpromo_audio_master_apply_cover_selection($root, $filename, $cover_path);
} elseif ($cover_mode === 'clear') {
    $cover_result = bandpromo_audio_master_apply_cover_selection($root, $filename, '');
}

if (!($cover_result['ok'] ?? false)) {
    http_response_code(400);
    echo json_encode(['error' => (string) ($cover_result['error'] ?? 'Could not save track cover')]);
    exit;
}

$updatedSidecarCover = array_key_exists('sidecar_cover', $cover_result)
    ? (string) ($cover_result['sidecar_cover'] ?? '')
    : (string) ($data['sidecar_cover'] ?? '');
$data['sidecar_cover'] = $updatedSidecarCover;

$playlist_scan = bandpromo_run_light_task('scripts/makePlaylists.py');

$data = bandpromo_audio_master_enrich_detail($root, $filename, $data);

$build_state = bandpromo_mark_build_required('media_audio_master_changed');

$response = [
    'ok' => true,
    'detail' => $data,
    'build_required' => true,
    'build_required_state' => $build_state,
];

if ($playlist_scan['ok']) {
    $response['auto_tasks'] = ['playlist-scan'];
} else {
    $response['warning'] = 'Track details were saved, but the automatic playlist refresh failed.';
    $response['task_output'] = trim((string) ($playlist_scan['output'] ?? ''));
}

bandpromo_admin_audit_log('audio_master_metadata_saved', [
    'target_type' => 'audio_master',
    'target_id' => $filename,
    'status' => $playlist_scan['ok'] ? 'ok' : 'warning',
    'data' => [
        'title' => $normalized_fields['title'],
        'artist' => $normalized_fields['artist'],
        'album' => $normalized_fields['album'],
        'cover_mode' => $cover_mode,
        'cover_path' => $cover_path,
        'auto_tasks' => $playlist_scan['ok'] ? ['playlist-scan'] : [],
        'playlist_refresh_failed' => !$playlist_scan['ok'],
    ],
]);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);