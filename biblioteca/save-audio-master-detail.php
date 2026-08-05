<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/audio-master-detail-helpers.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/playlist-storage.php';
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

require_once __DIR__ . '/admin-api-guard.php';

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

// Registry-only presentation flags (not written into master audio tags).
$text_role = bandpromo_asset_normalize_text_role((string) ($fields['text_role'] ?? 'lyrics'));
$notes_label = bandpromo_asset_normalize_notes_label((string) ($fields['notes_label'] ?? ''));
if ($text_role !== 'notes') {
    $notes_label = '';
}

$cover_path = trim((string) ($payload['cover_path'] ?? ''));
$cover_mode = trim((string) ($payload['cover_mode'] ?? 'preserve'));
if (!in_array($cover_mode, ['preserve', 'set', 'clear'], true)) {
    $cover_mode = 'preserve';
}

$living_cover_path = trim((string) ($payload['living_cover_path'] ?? ''));
$living_cover_mode = trim((string) ($payload['living_cover_mode'] ?? 'preserve'));
if (!in_array($living_cover_mode, ['preserve', 'set', 'clear'], true)) {
    $living_cover_mode = 'preserve';
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

session_write_close();

$root = dirname(__DIR__);

try {
    bandpromo_release_assert_master_editable($root, $filename);
} catch (RuntimeException $exception) {
    http_response_code(409);
    echo json_encode(['error' => $exception->getMessage()]);
    exit;
}

try {
    $current_detail = bandpromo_audio_master_detail_from_registry($root, $filename);
} catch (Throwable $throwable) {
    http_response_code(404);
    echo json_encode(['error' => $throwable->getMessage()]);
    exit;
}

$existing_tracknumber = trim((string) ($current_detail['tracknumber'] ?? ''));
if ($existing_tracknumber === '') {
    $existing_tracknumber = trim((string) ($current_detail['suggested_tracknumber'] ?? ''));
}
if ($normalized_fields['tracknumber'] === '') {
    $normalized_fields['tracknumber'] = $existing_tracknumber !== ''
        ? $existing_tracknumber
        : bandpromo_release_find_track_number_for_master($root, $filename);
}

$existing_living_cover = bandpromo_living_cover_normalize_video_filename((string) ($current_detail['living_cover'] ?? ''));
$new_living_cover = $existing_living_cover;
if ($living_cover_mode === 'clear') {
    $new_living_cover = '';
} elseif ($living_cover_mode === 'set') {
    $living_cover_validation = bandpromo_living_cover_validate_video_path($root, $living_cover_path);
    if (!($living_cover_validation['ok'] ?? false)) {
        http_response_code(400);
        echo json_encode(['error' => (string) ($living_cover_validation['error'] ?? 'Invalid living cover video')]);
        exit;
    }
    $new_living_cover = (string) ($living_cover_validation['filename'] ?? '');
}
$normalized_fields['living_cover'] = $new_living_cover;

$current_fields = [];
foreach ($allowed_keys as $key) {
    $current_fields[$key] = trim((string) ($current_detail[$key] ?? ''));
}
// Client still saves a combined Title [Version] master tag; normalize current
// title the same way so separate display.version does not look like a change.
$current_fields['title'] = bandpromo_release_combine_audio_title_parts(
    (string) ($current_detail['title'] ?? ''),
    (string) ($current_detail['version'] ?? '')
);
$current_fields['living_cover'] = $existing_living_cover;

$metadata_changed = $current_fields !== $normalized_fields;
$sidecar_cover = trim((string) ($current_detail['sidecar_cover'] ?? ''));
$cover_changed = ($cover_mode === 'clear' && $sidecar_cover !== '') || $cover_mode === 'set';
$current_text_role = bandpromo_asset_normalize_text_role((string) ($current_detail['text_role'] ?? 'lyrics'));
$current_notes_label = bandpromo_asset_normalize_notes_label((string) ($current_detail['notes_label'] ?? ''));
if ($current_text_role !== 'notes') {
    $current_notes_label = '';
}
$text_panel_changed = ($current_text_role !== $text_role) || ($current_notes_label !== $notes_label);

$display_fields = $normalized_fields;
$display_fields['text_role'] = $text_role;
$display_fields['notes_label'] = $notes_label;

if (!$metadata_changed && !$cover_changed) {
    $data = $current_detail;
    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    $display = bandpromo_asset_read_audio_display($asset);
    if (!bandpromo_asset_audio_display_is_complete($display) || $text_panel_changed) {
        bandpromo_asset_sync_audio_display_from_fields($root, $filename, $display_fields, $data);
    }
    $data['text_role'] = $text_role;
    $data['notes_label'] = $notes_label;
    $build_state = bandpromo_get_build_required_state();

    bandpromo_admin_audit_log('audio_master_metadata_saved', [
        'target_type' => 'audio_master',
        'target_id' => $filename,
        'status' => 'ok',
        'data' => [
            'no_change' => !$text_panel_changed,
            'text_panel_changed' => $text_panel_changed,
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'detail' => $data,
        'build_required' => !empty($build_state['required']),
        'build_required_state' => $build_state,
        'no_change' => !$text_panel_changed,
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

bandpromo_asset_sync_audio_display_from_fields($root, $filename, $display_fields, is_array($data) ? $data : []);
if (is_array($data)) {
    $data['text_role'] = $text_role;
    $data['notes_label'] = $notes_label;
}

// Keep last-good player payloads readable; never wipe to empty tracks on tag/cover save.
$canonicalMaster = bandpromo_audio_master_canonical_filename($root, $filename);
if ($canonicalMaster === '') {
    $canonicalMaster = $filename;
}

require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/media-delivery-helpers.php';

$deliveryReady = false;
$deliverySynced = false;
$deliveryPrepared = false;
$autoTasks = [];

$optimalExists = false;
if (function_exists('bandpromo_asset_audio_delivery_ready')) {
    require_once __DIR__ . '/publish-status-helpers.php';
    $deliveryReady = bandpromo_asset_audio_delivery_ready($root, $canonicalMaster);
}
if (!$deliveryReady) {
    $stem = pathinfo($canonicalMaster, PATHINFO_FILENAME);
    $optimalPath = $root . '/media/audio/optimal/' . $stem . '.mp3';
    $optimalExists = is_file($optimalPath);
    $deliveryReady = $optimalExists;
} else {
    $optimalExists = true;
}

$tagsOnly = $metadata_changed && !$cover_changed;

if ($optimalExists) {
    $tagSync = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
        'action' => 'sync_delivery_tags',
        'filename' => $canonicalMaster,
    ]);
    $tagData = is_array($tagSync['data'] ?? null) ? $tagSync['data'] : null;
    $deliverySynced = is_array($tagData) && !empty($tagData['ok']);
    $autoTasks[] = 'delivery-tag-sync';
}

if (!$optimalExists || ($cover_changed && !$deliverySynced)) {
    $delivery = bandpromo_run_audio_source_delivery_and_refresh([$canonicalMaster]);
    $prepared = is_array($delivery['prepared'] ?? null) ? $delivery['prepared'] : [];
    $deliveryPrepared = $prepared !== [];
    $autoTasks[] = 'audio-delivery';
    $stem = pathinfo($canonicalMaster, PATHINFO_FILENAME);
    $optimalExists = is_file($root . '/media/audio/optimal/' . $stem . '.mp3');
}

$republish = bandpromo_playlist_republish_player_payloads_for_master($root, $canonicalMaster);
$autoTasks[] = 'playlist-republish';

$listenerReady = $optimalExists && ($deliverySynced || $deliveryPrepared || $deliveryReady);
if ($listenerReady && empty($republish['errors'])) {
    $build_state = bandpromo_clear_build_required_tasks(['audio-delivery']);
} elseif ($cover_changed || !$optimalExists) {
    $build_state = bandpromo_mark_build_required('media_audio_master_changed');
} else {
    $build_state = bandpromo_get_build_required_state();
}

$data = bandpromo_audio_master_enrich_detail($root, $filename, $data);
// Prefer freshly saved fields in the response (registry is authoritative after sync).
$data['date'] = $normalized_fields['date'];
$data['tracknumber'] = $normalized_fields['tracknumber'];
$data['bpm'] = $normalized_fields['bpm'];
$data['initialkey'] = $normalized_fields['initialkey'];
$data['genre'] = $normalized_fields['genre'];
$data['comment'] = $normalized_fields['comment'];
$data['lyrics'] = $normalized_fields['lyrics'];
$data['living_cover'] = $normalized_fields['living_cover'];
$data = bandpromo_living_cover_enrich_detail($root, $data);

$response = [
    'ok' => true,
    'detail' => $data,
    'build_required' => !empty($build_state['required']),
    'build_required_state' => $build_state,
    'playlists_republished' => count($republish['published'] ?? []),
    'tags_only' => $tagsOnly,
];

bandpromo_admin_audit_log('audio_master_metadata_saved', [
    'target_type' => 'audio_master',
    'target_id' => $filename,
    'status' => 'ok',
    'data' => [
        'title' => $normalized_fields['title'],
        'artist' => $normalized_fields['artist'],
        'album' => $normalized_fields['album'],
        'cover_mode' => $cover_mode,
        'cover_path' => $cover_path,
        'living_cover_mode' => $living_cover_mode,
        'living_cover_path' => $living_cover_path,
        'living_cover' => $new_living_cover,
        'auto_tasks' => $autoTasks,
        'delivery_synced' => $deliverySynced,
        'delivery_prepared' => $deliveryPrepared,
        'playlists_republished' => count($republish['published'] ?? []),
        'republish_errors' => count($republish['errors'] ?? []),
    ],
]);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
