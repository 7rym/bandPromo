<?php
/**
 * Save gallery container content (data/galleries/{id}.json).
 * Accepts a JSON array via POST body. Admin-only.
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/gallery-storage.php';
session_write_close();

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

$decoded = json_decode($body, true);
if ($decoded === null) {
    http_response_code(400);
    bandpromo_admin_audit_log('gallery_saved', [
        'target_type' => 'gallery',
        'target_id' => 'data/galleries',
        'status' => 'error',
        'data' => ['error' => 'Invalid JSON: ' . json_last_error_msg()],
    ]);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['error' => 'Gallery payload must be a JSON array']);
    exit;
}

$root = dirname(__DIR__);
$galleryId = bandpromo_gallery_normalize_id((string) ($_GET['gallery'] ?? BANDPROMO_GALLERY_DEFAULT_ID));
if ($galleryId === '') {
    $galleryId = BANDPROMO_GALLERY_DEFAULT_ID;
}

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

function bandpromo_gallery_video_poster_from_src(string $root, string $src): ?string
{
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

try {
    bandpromo_gallery_ensure_seeded($root);
    $result = bandpromo_gallery_save_items($root, $galleryId, $decoded);

    bandpromo_admin_audit_log('gallery_saved', [
        'target_type' => 'gallery',
        'target_id' => 'data/galleries/' . $galleryId . '.json',
        'status' => 'ok',
        'data' => ['count' => $result['count'], 'gallery_id' => $galleryId],
    ]);

    echo json_encode([
        'ok' => true,
        'count' => $result['count'],
        'gallery_id' => $galleryId,
    ]);
} catch (Throwable $throwable) {
    bandpromo_admin_audit_log('gallery_saved', [
        'target_type' => 'gallery',
        'target_id' => 'data/galleries/' . $galleryId . '.json',
        'status' => 'error',
        'data' => ['error' => $throwable->getMessage()],
    ]);
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
}
