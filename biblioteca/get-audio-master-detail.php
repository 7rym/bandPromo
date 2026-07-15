<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/audio-master-detail-helpers.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';

$filename = trim((string) ($_GET['filename'] ?? ''));
if ($filename === '' || strpbrk($filename, '/\\') !== false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

session_write_close();

try {
    $data = bandpromo_audio_master_detail_from_registry(dirname(__DIR__), $filename);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $throwable) {
    http_response_code(400);
    echo json_encode(['error' => $throwable->getMessage()]);
} catch (Throwable $throwable) {
    http_response_code(404);
    echo json_encode(['error' => $throwable->getMessage()]);
}
