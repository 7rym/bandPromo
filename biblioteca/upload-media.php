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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/build-required.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$root_dir      = dirname(dirname(__FILE__));
$audio_orig_dir = $root_dir . '/media/audio/original';
$img_orig_dir   = $root_dir . '/media/img/original';
$photo_dir    = $root_dir . '/media/photo/original';
$video_dir    = $root_dir . '/media/video/original';
$special_dir  = $root_dir . '/media/special';
$tmp_dir      = $root_dir . '/data/upload_tmp';

// Optional target hint from Media sub-panel (audio | illustrations | photos | video | special)
$target_hint  = $_POST['target'] ?? '';

foreach ([$audio_orig_dir, $img_orig_dir, $photo_dir, $video_dir, $special_dir] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}
// tmp dir stays private — not served over the web
if (!is_dir($tmp_dir)) mkdir($tmp_dir, 0750, true);

$audio_exts = ['flac', 'mp3'];
$image_exts = ['png', 'jpg', 'jpeg', 'webp'];
$video_exts = ['mp4', 'webm', 'mov'];

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
        if (!in_array($audio_ext, ['flac', 'mp3'], true)) {
            continue;
        }
        $audio_stem = strtolower(pathinfo($entry, PATHINFO_FILENAME));
        if ($audio_stem !== '' && $audio_stem === $image_stem) {
            return true;
        }
    }

    return false;
}

function build_reason_for_upload(string $target_hint, string $ext, string $filename): string {
    if (in_array($ext, ['flac', 'mp3'], true)) {
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
    if (in_array($ext, $audio_exts)) {
        $dest = $audio_orig_dir . '/' . $safeName;
    } elseif (in_array($ext, $video_exts)) {
        $dest = $video_dir . '/' . $safeName;
    } elseif ($target_hint === 'special') {
        $dest = $special_dir . '/' . $safeName;
    } elseif ($target_hint === 'photos') {
        $dest = $photo_dir . '/' . $safeName;
    } else {
        $dest = $img_orig_dir . '/' . $safeName;
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

    $reason = build_reason_for_upload((string) $target_hint, $ext, $safeName);
    $response = [
        'ok' => true,
        'status' => 'complete',
        'saved_as' => $safeName,
    ];

    if ($reason !== '') {
        $state = bandpromo_mark_build_required($reason);
        $response['build_required'] = true;
        $response['build_required_state'] = $state;
    } else {
        $response['build_required'] = false;
    }

    echo json_encode($response);
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

    if (in_array($ext, $audio_exts)) {
        $dest = $audio_orig_dir . '/' . $safe_name;
    } elseif (in_array($ext, $video_exts)) {
        $dest = $video_dir . '/' . $safe_name;
    } elseif ($target_hint === 'special') {
        $dest = $special_dir . '/' . $safe_name;
    } elseif (in_array($ext, $image_exts)) {
        // Route to photo dir if uploaded from Photos panel
        $dest = ($target_hint === 'photos')
            ? $photo_dir . '/' . $safe_name
            : $img_orig_dir . '/' . $safe_name;
    } else {
        $results[] = ['name' => $original, 'ok' => false, 'error' => "Unsupported file type: .$ext"];
        $errors++;
        continue;
    }

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $results[]  = ['name' => $original, 'ok' => true, 'saved_as' => $safe_name];
        $uploaded++;
        $reason = build_reason_for_upload((string) $target_hint, $ext, $safe_name);
        if ($reason !== '' && !in_array($reason, $upload_reasons, true)) {
            $upload_reasons[] = $reason;
        }
    } else {
        $results[] = ['name' => $original, 'ok' => false, 'error' => 'Could not save file'];
        $errors++;
    }
}

$response = ['ok' => $errors === 0, 'uploaded' => $uploaded, 'errors' => $errors, 'files' => $results];
if ($uploaded > 0 && !empty($upload_reasons)) {
    $state = null;
    foreach ($upload_reasons as $reason) {
        $state = bandpromo_mark_build_required($reason);
    }
    $response['build_required'] = true;
    $response['build_required_state'] = $state;
} else {
    $response['build_required'] = false;
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
