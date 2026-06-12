<?php
/**
 * Save runtime gallery content (data/gallery.json).
 * Accepts a JSON array via POST body. Admin-only.
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
session_write_close(); // release lock before file I/O

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false) {
    echo json_encode(['error' => 'Could not read request body']);
    exit;
}

// Validate JSON and ensure it's an array
$decoded = json_decode($body, true);
if ($decoded === null) {
    http_response_code(400);
    bandpromo_admin_audit_log('gallery_saved', [
        'target_type' => 'gallery',
        'target_id' => 'data/gallery.json',
        'status' => 'error',
        'data' => ['error' => 'Invalid JSON: ' . json_last_error_msg()],
    ]);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['error' => 'gallery.json must be a JSON array']);
    exit;
}

function bandpromo_gallery_video_poster_from_src(string $root, string $src): ?string {
    $path = parse_url($src, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $src;
    }
    $path = str_replace('\\', '/', $path);
    $filename = basename($path);
    if ($filename === '' || !preg_match('/\.(mp4|webm|mov)$/i', $filename)) {
        return null;
    }

    $poster = '/media/video/poster/' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
    return is_file($root . $poster) ? $poster : null;
}

$gallery_file = dirname(__DIR__) . '/data/gallery.json';
$root = dirname(__DIR__);

foreach ($decoded as $index => $item) {
    if (!is_array($item)) {
        continue;
    }

    $type = trim((string) ($item['type'] ?? ''));
    if ($type === 'video') {
        $poster = bandpromo_gallery_video_poster_from_src($root, (string) ($item['src'] ?? ''));
        if ($poster !== null) {
            $decoded[$index]['poster'] = $poster;
        } else {
            unset($decoded[$index]['poster']);
        }
        continue;
    }

    unset($decoded[$index]['poster']);
}

// Ensure data dir exists
if (!is_dir(dirname($gallery_file))) {
    mkdir(dirname($gallery_file), 0750, true);
}

$pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($gallery_file, $pretty) === false) {
    bandpromo_admin_audit_log('gallery_saved', [
        'target_type' => 'gallery',
        'target_id' => 'data/gallery.json',
        'status' => 'error',
        'data' => ['error' => 'Write failed'],
    ]);
    echo json_encode(['error' => 'Could not write data/gallery.json — check file permissions']);
    exit;
}

bandpromo_admin_audit_log('gallery_saved', [
    'target_type' => 'gallery',
    'target_id' => 'data/gallery.json',
    'status' => 'ok',
    'data' => ['count' => count($decoded)],
]);

echo json_encode(['ok' => true, 'count' => count($decoded)]);
