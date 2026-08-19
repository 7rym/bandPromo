<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/campaign-storage.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || $body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

$order = json_decode($body, true);
if ($order === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}
if (!is_array($order)) {
    http_response_code(400);
    echo json_encode(['error' => 'Expected a JSON array of filenames']);
    exit;
}

foreach ($order as $entry) {
    if (!is_string($entry) || $entry === '' || strpbrk($entry, '/\\') !== false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename in order array: ' . json_encode($entry)]);
        exit;
    }
}

$root = dirname(__DIR__);
$releaseId = bandpromo_campaign_normalize_id((string) ($_GET['release'] ?? BANDPROMO_CAMPAIGN_DEFAULT_ID));
if ($releaseId === '') {
    $releaseId = BANDPROMO_CAMPAIGN_DEFAULT_ID;
}

try {
    $saved = bandpromo_campaign_save_tracks($root, $releaseId, $order);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
    exit;
}

$skipped = $saved['skipped'];
$tagsSynced = (int) ($saved['tags_synced'] ?? 0);
$response = [
    'ok' => true,
    'release_id' => $releaseId,
    'count' => (int) ($saved['count'] ?? 0),
    'requested' => count($order),
    'skipped' => $skipped,
    'tags_synced' => $tagsSynced,
];

$warnings = [];
if ($skipped) {
    $warnings[] = 'Some tracks could not be added because their source audio was not found';
}
if ($warnings) {
    $response['warning'] = implode('. ', $warnings);
}

bandpromo_admin_audit_log('release_tracks_saved', [
    'target_type' => 'release',
    'target_id' => 'data/releases/' . $releaseId . '.json',
    'status' => $warnings ? 'warning' : 'ok',
    'data' => [
        'release_id' => $releaseId,
        'count' => (int) ($saved['count'] ?? 0),
        'requested' => count($order),
        'skipped' => count($skipped),
    ],
]);

echo json_encode($response);
