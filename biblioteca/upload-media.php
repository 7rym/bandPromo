<?php
/**
 * Media Upload Handler — supports chunked uploads for large files.
 *
 * Single-file mode:   POST with files[] field (small files)
 * Chunked mode:       POST with fields:
 *   chunk        — binary chunk data (single file field named "chunk")
 *   filename     — original filename
 *   chunk_index  — 0-based chunk number
 *   total_chunks — total number of chunks
 */

require_once __DIR__ . '/admin-api-guard.php';

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/cover-art-helpers.php';
require_once __DIR__ . '/gallery-helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$root_dir      = dirname(dirname(__FILE__));
$audio_orig_dir = $root_dir . '/media/audio/original';
$audio_master_dir = $root_dir . '/media/audio/master';
$img_orig_dir   = $root_dir . '/media/img/original';
$photo_dir    = $root_dir . '/media/photo/original';
$video_dir    = $root_dir . '/media/video/original';
$video_poster_dir = $root_dir . '/media/video/poster';
$special_dir  = $root_dir . '/media/special';
$tmp_dir      = $root_dir . '/data/upload_tmp';

// Optional target hint from Media sub-panel (audio | illustrations | photos | video | special)
$target_hint  = $_POST['target'] ?? '';

foreach ([$audio_orig_dir, $img_orig_dir, $photo_dir, $video_dir, $video_poster_dir, $special_dir] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}
if (!is_dir($audio_master_dir)) mkdir($audio_master_dir, 0755, true);
// tmp dir stays private — not served over the web
if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0750, true);

$audio_exts = ['flac', 'mp3', 'wav'];
$image_exts = ['png', 'jpg', 'jpeg', 'webp'];
$video_exts = ['mp4', 'webm', 'mov'];

function bandpromo_finalize_uploaded_file(string $root_dir, string $target_hint, string $ext, string $safe_name, string $dest): array {
    if ($target_hint !== 'special' || $ext !== 'wav') {
        return [
            'ok' => true,
            'saved_as' => $safe_name,
            'saved_path' => $dest,
            'saved_ext' => $ext,
            'warning' => '',
        ];
    }

    $flac_name = pathinfo($safe_name, PATHINFO_FILENAME) . '.flac';
    $flac_path = dirname($dest) . '/' . $flac_name;
    $conversion = bandpromo_convert_wav_to_flac($root_dir, $dest, $flac_path, 'Could not convert special WAV upload to FLAC');
    if (!$conversion['ok']) {
        @unlink($dest);
        return [
            'ok' => false,
            'saved_as' => $safe_name,
            'saved_path' => $dest,
            'saved_ext' => $ext,
            'warning' => $conversion['warning'],
        ];
    }

    @unlink($dest);

    return [
        'ok' => true,
        'saved_as' => $flac_name,
        'saved_path' => $flac_path,
        'saved_ext' => 'flac',
        'warning' => '',
    ];
}

function resolve_upload_destination(string $root_dir, string $target_hint, string $ext, string $safe_name): ?string {
    if ($target_hint === 'special') {
        return $root_dir . '/media/special/' . $safe_name;
    }

    if (in_array($ext, ['flac', 'mp3', 'wav'], true)) {
        return $root_dir . '/media/audio/original/' . $safe_name;
    }

    if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
        return $root_dir . '/media/video/original/' . $safe_name;
    }

    if ($target_hint === 'photos') {
        return $root_dir . '/media/photo/original/' . $safe_name;
    }

    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        return $root_dir . '/media/img/original/' . $safe_name;
    }

    return null;
}

function bandpromo_is_video_extension(string $ext): bool {
    return in_array($ext, ['mp4', 'webm', 'mov'], true);
}

function bandpromo_ffmpeg_command(): string {
    $configured = trim((string) getenv('FFMPEG_PATH'));
    return $configured !== '' ? $configured : 'ffmpeg';
}

function bandpromo_run_command(array $command, string $cwd): array {
    if (!function_exists('proc_open')) {
        return [
            'ok' => false,
            'output' => '',
            'exit_code' => null,
            'error' => 'Process execution is unavailable on this host',
        ];
    }

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) {
        return [
            'ok' => false,
            'output' => '',
            'exit_code' => null,
            'error' => 'Could not start process',
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);
    $output = trim((string) $stdout . (string) $stderr);

    return [
        'ok' => $exit_code === 0,
        'output' => $output,
        'exit_code' => $exit_code,
        'error' => $exit_code === 0 ? '' : 'Command failed',
    ];
}

function bandpromo_generate_video_poster(string $root_dir, string $saved_ext, string $saved_name, string $saved_path, string $target_hint = ''): array {
    if ($target_hint === 'special' || !bandpromo_is_video_extension($saved_ext)) {
        return [
            'attempted' => false,
            'generated' => false,
            'poster' => '',
            'warning' => '',
        ];
    }

    $poster_relative = bandpromo_gallery_video_poster_relative_path($saved_name);
    $poster_path = bandpromo_gallery_video_poster_absolute_path($root_dir, $saved_name);
    $ffmpeg = bandpromo_ffmpeg_command();
    $result = bandpromo_run_command([
        $ffmpeg,
        '-y',
        '-i',
        $saved_path,
        '-frames:v',
        '1',
        '-q:v',
        '2',
        $poster_path,
    ], $root_dir);

    if ($result['ok'] && is_file($poster_path)) {
        return [
            'attempted' => true,
            'generated' => true,
            'poster' => $poster_relative,
            'warning' => '',
        ];
    }

    $output = strtolower((string) ($result['output'] ?? ''));
    $warning = 'Could not generate a poster image automatically for this video.';
    if (($result['exit_code'] ?? null) === null || strpos($output, 'not found') !== false || strpos($output, 'not recognized') !== false) {
        $warning = 'Automatic video poster generation requires ffmpeg on the host.';
    }

    if (is_file($poster_path)) {
        @unlink($poster_path);
    }

    return [
        'attempted' => true,
        'generated' => false,
        'poster' => '',
        'warning' => $warning,
    ];
}

function image_matches_audio_basename(string $filename): bool {
    $root_dir = dirname(dirname(__FILE__));
    $audio_orig_dir = $root_dir . '/media/audio/original';
    if (!is_dir($audio_orig_dir)) {
        return false;
    }

    $image_stem = strtolower(pathinfo($filename, PATHINFO_FILENAME));
    if ($image_stem === '') {
        return false;
    }

    $audio_files = scandir($audio_orig_dir);
    if (!is_array($audio_files)) {
        return false;
    }

    foreach ($audio_files as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $audio_ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($audio_ext, ['flac', 'mp3', 'wav'], true)) {
            continue;
        }
        $audio_stem = strtolower(pathinfo($entry, PATHINFO_FILENAME));
        if ($audio_stem !== '' && $audio_stem === $image_stem) {
            return true;
        }
    }

    return false;
}

function bandpromo_record_cover_upload_if_needed(string $root_dir, string $saved_path, string $saved_name): void
{
    $normalized = str_replace('\\', '/', $saved_path);
    if (strpos($normalized, '/media/img/original/') === false) {
        return;
    }

    bandpromo_cover_art_record_upload($root_dir, $saved_name, 'illustrations');
}

function build_reason_for_upload(string $target_hint, string $ext, string $filename): string {
    if ($target_hint === 'special') {
        return '';
    }

    if (in_array($ext, ['flac', 'mp3', 'wav'], true)) {
        return 'media_audio_upload';
    }

    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        if (($target_hint === 'illustrations' || $target_hint === '') && image_matches_audio_basename($filename)) {
            return 'media_cover_upload';
        }
        if ($target_hint === 'illustrations' || $target_hint === 'photos' || $target_hint === '') {
            return 'media_image_upload';
        }
    }

    if (bandpromo_is_video_extension($ext)) {
        return 'media_video_upload';
    }

    return '';
}

// ─── Chunked upload mode ──────────────────────────────────────────────────────
if (isset($_POST['chunk_index']) && isset($_POST['filename'])) {
    $chunkIndex  = (int)$_POST['chunk_index'];
    $totalChunks = (int)$_POST['total_chunks'];
    $filename    = basename($_POST['filename']);
    $safeName    = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $filename);
    $ext         = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    $target_hint = $_POST['target'] ?? '';

    if (!in_array($ext, array_merge($audio_exts, $image_exts, $video_exts))) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Unsupported file type: .$ext"]);
        exit;
    }

    if (empty($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Chunk upload error: ' . ($_FILES['chunk']['error'] ?? 'missing')]);
        exit;
    }

    // Save chunk
    $chunkPath = $tmp_dir . '/' . $safeName . '.part' . $chunkIndex;
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save chunk']);
        exit;
    }

    // Check if all chunks are present
    $partsPresent = 0;
    for ($i = 0; $i < $totalChunks; $i++) {
        if (file_exists($tmp_dir . '/' . $safeName . '.part' . $i)) $partsPresent++;
    }

    if ($partsPresent < $totalChunks) {
        // Still waiting for more chunks
        echo json_encode(['ok' => true, 'status' => 'partial', 'received' => $partsPresent, 'total' => $totalChunks]);
        exit;
    }

    // All chunks received — assemble
    @set_time_limit(0);
    $dest = resolve_upload_destination($root_dir, (string) $target_hint, $ext, $safeName);
    if ($dest === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Unsupported file type: .$ext"]);
        exit;
    }

    $out = fopen($dest, 'wb');
    if (!$out) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not create destination file']);
        exit;
    }
    for ($i = 0; $i < $totalChunks; $i++) {
        $partPath = $tmp_dir . '/' . $safeName . '.part' . $i;
        $in = fopen($partPath, 'rb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        unlink($partPath);
    }
    fclose($out);

    $finalized = bandpromo_finalize_uploaded_file($root_dir, (string) $target_hint, $ext, $safeName, $dest);
    if (empty($finalized['ok'])) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $finalized['warning'] ?: 'Could not finalize upload']);
        exit;
    }

    $savedName = (string) ($finalized['saved_as'] ?? $safeName);
    $savedPath = (string) ($finalized['saved_path'] ?? $dest);
    $savedExt = (string) ($finalized['saved_ext'] ?? $ext);
    bandpromo_record_cover_upload_if_needed($root_dir, $savedPath, $savedName);
    $reason = build_reason_for_upload((string) $target_hint, $savedExt, $savedName);
    $master = $target_hint === 'special'
        ? ['attempted' => false, 'prepared' => false, 'warning' => '']
        : bandpromo_prepare_audio_master($root_dir, $savedExt, $savedName, $savedPath);
    $videoPoster = bandpromo_is_video_extension($savedExt)
        ? ['attempted' => false, 'generated' => false, 'poster' => '', 'warning' => '']
        : bandpromo_generate_video_poster($root_dir, $savedExt, $savedName, $savedPath, (string) $target_hint);
    $response = [
        'ok' => true,
        'status' => 'complete',
        'saved_as' => $savedName,
    ];

    if (!empty($master['attempted'])) {
        $response['master_prepared'] = !empty($master['prepared']);
        if (!empty($master['master_filename'])) {
            $response['master_filename'] = $master['master_filename'];
        }
        if (!empty($master['master_format'])) {
            $response['master_format'] = $master['master_format'];
        }
        if (!empty($master['asset_id'])) {
            $response['asset_id'] = $master['asset_id'];
        }
        if (!empty($master['warning'])) {
            $response['master_warning'] = $master['warning'];
        }
    }

    if (!empty($videoPoster['attempted'])) {
        $response['video_poster_generated'] = !empty($videoPoster['generated']);
        if (!empty($videoPoster['poster'])) {
            $response['video_poster'] = $videoPoster['poster'];
        }
        if (!empty($videoPoster['warning'])) {
            $response['video_poster_warning'] = $videoPoster['warning'];
        }
    }

    if ($reason !== '') {
        $state = bandpromo_mark_build_required($reason);
        $auto = bandpromo_run_auto_upload_tasks([$reason], [$savedName], $state);
        $state = $auto['state'];
        $response['build_required'] = !empty($state['required']);
        $response['build_required_state'] = $state;
        if (!empty($auto['auto_tasks'])) {
            $response['auto_tasks'] = $auto['auto_tasks'];
        }
        if (!empty($auto['delivery_prepared'])) {
            $response['delivery_prepared'] = $auto['delivery_prepared'];
        }
        if (!empty($auto['delivery_missing'])) {
            $response['delivery_missing'] = $auto['delivery_missing'];
        }
        if (!empty($auto['background_tasks'])) {
            $response['background_tasks'] = $auto['background_tasks'];
        }
        if ($auto['warning'] !== '') {
            $response['warning'] = $auto['warning'];
            $response['task_output'] = $auto['task_output'];
        }
    } else {
        $response['build_required'] = false;
    }

    echo json_encode($response);

    bandpromo_admin_audit_log('media_uploaded', [
        'target_type' => 'media',
        'target_id' => ($target_hint !== '' ? $target_hint : $savedExt) . '/' . $savedName,
        'status' => 'ok',
        'data' => [
            'mode' => 'chunked',
            'build_required' => $response['build_required'] ?? false,
            'reasons' => $reason !== '' ? [$reason] : [],
            'master_prepared' => $response['master_prepared'] ?? false,
            'master_warning' => $response['master_warning'] ?? '',
            'video_poster_generated' => $response['video_poster_generated'] ?? false,
            'video_poster_warning' => $response['video_poster_warning'] ?? '',
        ],
    ]);
    exit;
}

// ─── Standard (small file) upload mode ────────────────────────────────────────
if (empty($_FILES['files'])) {
    $maxPost   = ini_get('post_max_size');
    $maxUpload = ini_get('upload_max_filesize');
    $postSize  = $_SERVER['CONTENT_LENGTH'] ?? 0;
    http_response_code(400);
    echo json_encode([
        'error' => 'No files received. The file may exceed the server upload limit.',
        'server_upload_max_filesize' => $maxUpload,
        'server_post_max_size'       => $maxPost,
        'request_content_length'     => $postSize,
    ]);
    exit;
}

$files = [];
if (is_array($_FILES['files']['name'])) {
    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        $files[] = [
            'name'     => $_FILES['files']['name'][$i],
            'type'     => $_FILES['files']['type'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'error'    => $_FILES['files']['error'][$i],
            'size'     => $_FILES['files']['size'][$i],
        ];
    }
} else {
    $files[] = [
        'name'     => $_FILES['files']['name'],
        'type'     => $_FILES['files']['type'],
        'tmp_name' => $_FILES['files']['tmp_name'],
        'error'    => $_FILES['files']['error'],
        'size'     => $_FILES['files']['size'],
    ];
}

$results  = [];
$uploaded = 0;
$errors   = 0;
$masterPreparedCount = 0;
$masterWarnings = [];
$videoPosterWarnings = [];
$upload_reasons = [];

foreach ($files as $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $results[] = ['name' => $file['name'], 'ok' => false, 'error' => 'Upload error code ' . $file['error']];
        $errors++;
        continue;
    }
    $original  = $file['name'];
    $safe_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($original));
    $ext       = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));

    $dest = resolve_upload_destination($root_dir, (string) $target_hint, $ext, $safe_name);
    if ($dest === null) {
        $results[] = ['name' => $original, 'ok' => false, 'error' => "Unsupported file type: .$ext"];
        $errors++;
        continue;
    }

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $finalized = bandpromo_finalize_uploaded_file($root_dir, (string) $target_hint, $ext, $safe_name, $dest);
        if (empty($finalized['ok'])) {
            $results[] = ['name' => $original, 'ok' => false, 'error' => $finalized['warning'] ?: 'Could not finalize upload'];
            $errors++;
            continue;
        }

        $saved_name = (string) ($finalized['saved_as'] ?? $safe_name);
        $saved_path = (string) ($finalized['saved_path'] ?? $dest);
        $saved_ext = (string) ($finalized['saved_ext'] ?? $ext);
        bandpromo_record_cover_upload_if_needed($root_dir, $saved_path, $saved_name);
        $master = $target_hint === 'special'
            ? ['attempted' => false, 'prepared' => false, 'warning' => '']
            : bandpromo_prepare_audio_master($root_dir, $saved_ext, $saved_name, $saved_path);
        $videoPoster = bandpromo_is_video_extension($saved_ext)
            ? ['attempted' => false, 'generated' => false, 'poster' => '', 'warning' => '']
            : bandpromo_generate_video_poster($root_dir, $saved_ext, $saved_name, $saved_path, (string) $target_hint);
        $result = ['name' => $original, 'ok' => true, 'saved_as' => $saved_name];
        if (!empty($master['attempted'])) {
            $result['master_prepared'] = !empty($master['prepared']);
            if (!empty($master['master_filename'])) {
                $result['master_filename'] = $master['master_filename'];
            }
            if (!empty($master['master_format'])) {
                $result['master_format'] = $master['master_format'];
            }
            if (!empty($master['asset_id'])) {
                $result['asset_id'] = $master['asset_id'];
            }
            if (!empty($master['prepared'])) {
                $masterPreparedCount++;
            }
            if (!empty($master['warning'])) {
                $result['master_warning'] = $master['warning'];
                $masterWarnings[] = $safe_name . ': ' . $master['warning'];
            }
        }
        if (!empty($videoPoster['attempted'])) {
            $result['video_poster_generated'] = !empty($videoPoster['generated']);
            if (!empty($videoPoster['poster'])) {
                $result['video_poster'] = $videoPoster['poster'];
            }
            if (!empty($videoPoster['warning'])) {
                $result['video_poster_warning'] = $videoPoster['warning'];
                $videoPosterWarnings[] = $saved_name . ': ' . $videoPoster['warning'];
            }
        }
        $results[]  = $result;
        $uploaded++;
        $reason = build_reason_for_upload((string) $target_hint, $saved_ext, $saved_name);
        if ($reason !== '' && !in_array($reason, $upload_reasons, true)) {
            $upload_reasons[] = $reason;
        }
    } else {
        $results[] = ['name' => $original, 'ok' => false, 'error' => 'Could not save file'];
        $errors++;
    }
}

$response = ['ok' => $errors === 0, 'uploaded' => $uploaded, 'errors' => $errors, 'files' => $results];
if ($masterPreparedCount > 0) {
    $response['master_prepared_count'] = $masterPreparedCount;
}
if (!empty($masterWarnings)) {
    $response['master_warnings'] = $masterWarnings;
}
if (!empty($videoPosterWarnings)) {
    $response['video_poster_warnings'] = $videoPosterWarnings;
}
if ($uploaded > 0 && !empty($upload_reasons)) {
    $state = null;
    foreach ($upload_reasons as $reason) {
        $state = bandpromo_mark_build_required($reason);
    }
    $savedNames = [];
    foreach ($results as $result) {
        if (!empty($result['ok']) && !empty($result['saved_as'])) {
            $savedNames[] = (string) $result['saved_as'];
        }
    }
    $auto = bandpromo_run_auto_upload_tasks($upload_reasons, $savedNames, $state);
    $state = $auto['state'];
    $response['build_required'] = !empty($state['required']);
    $response['build_required_state'] = $state;
    if (!empty($auto['auto_tasks'])) {
        $response['auto_tasks'] = $auto['auto_tasks'];
    }
    if (!empty($auto['delivery_prepared'])) {
        $response['delivery_prepared'] = $auto['delivery_prepared'];
    }
    if (!empty($auto['delivery_missing'])) {
        $response['delivery_missing'] = $auto['delivery_missing'];
    }
    if (!empty($auto['background_tasks'])) {
        $response['background_tasks'] = $auto['background_tasks'];
    }
    if ($auto['warning'] !== '') {
        $response['warning'] = $auto['warning'];
        $response['task_output'] = $auto['task_output'];
    }
} else {
    $response['build_required'] = false;
}

if ($uploaded > 0 || $errors > 0) {
    $savedNames = [];
    foreach ($results as $result) {
        if (!empty($result['ok']) && !empty($result['saved_as'])) {
            $savedNames[] = (string) $result['saved_as'];
        }
    }

    bandpromo_admin_audit_log('media_uploaded', [
        'target_type' => 'media',
        'target_id' => $target_hint !== '' ? (string) $target_hint : 'mixed',
        'status' => $errors === 0 ? 'ok' : ($uploaded > 0 ? 'warning' : 'error'),
        'data' => [
            'uploaded' => $uploaded,
            'errors' => $errors,
            'saved_files' => array_slice($savedNames, 0, 20),
            'build_required' => $response['build_required'] ?? false,
            'reasons' => $upload_reasons,
            'master_prepared_count' => $masterPreparedCount,
            'master_warnings' => $masterWarnings,
            'video_poster_warnings' => $videoPosterWarnings,
        ],
    ]);
}

echo json_encode($response);
exit;

$results  = [];
$uploaded = 0;
$errors   = 0;

// Normalise the $_FILES array to a flat list
$files = [];
if (is_array($_FILES['files']['name'])) {
    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        $files[] = [
            'name'     => $_FILES['files']['name'][$i],
            'type'     => $_FILES['files']['type'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'error'    => $_FILES['files']['error'][$i],
            'size'     => $_FILES['files']['size'][$i],
        ];
    }
} else {
    $files[] = [
        'name'     => $_FILES['files']['name'],
        'type'     => $_FILES['files']['type'],
        'tmp_name' => $_FILES['files']['tmp_name'],
        'error'    => $_FILES['files']['error'],
        'size'     => $_FILES['files']['size'],
    ];
}

foreach ($files as $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $results[] = ['name' => $file['name'], 'ok' => false, 'error' => 'Upload error code ' . $file['error']];
        $errors++;
        continue;
    }

    // Sanitise filename — letters, digits, dots, hyphens, underscores only
    $original = $file['name'];
    $safe_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($original));
    $ext = strtolower(pathinfo($safe_name, PATHINFO_EXTENSION));

    // Determine destination by extension (don't trust browser MIME)
    if (in_array($ext, $audio_exts)) {
        $dest = $audio_orig_dir . '/' . $safe_name;
    } elseif (in_array($ext, $image_exts)) {
        $dest = $img_orig_dir . '/' . $safe_name;
    } else {
        $results[] = ['name' => $original, 'ok' => false, 'error' => "Unsupported file type: .$ext"];
        $errors++;
        continue;
    }

    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $results[]  = ['name' => $original, 'ok' => true, 'saved_as' => $safe_name];
        $uploaded++;
    } else {
        $results[] = ['name' => $original, 'ok' => false, 'error' => 'Could not save file'];
        $errors++;
    }
}

echo json_encode([
    'uploaded' => $uploaded,
    'errors'   => $errors,
    'files'    => $results,
]);
exit;
