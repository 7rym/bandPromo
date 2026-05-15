<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/light-build-tasks.php';
bandpromo_enforce_https();

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$filename = trim((string) ($_GET['filename'] ?? ''));
if ($filename === '' || strpbrk($filename, '/\\') !== false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

session_write_close();

$result = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', [
    'action' => 'inspect',
    'filename' => $filename,
]);

$data = is_array($result['data'] ?? null) ? $result['data'] : null;
if (!$result['ok'] || !is_array($data) || empty($data['ok'])) {
    $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
    $output = trim((string) ($result['output'] ?? ''));
    $message = $error !== '' ? $error : ($output !== '' ? $output : 'Could not load audio master details');
    http_response_code(stripos($message, 'not found') !== false ? 404 : 500);
    echo json_encode(['error' => $message]);
    exit;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);