<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/brand-storage.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$root = dirname(__DIR__);
$body = file_get_contents('php://input');
$decoded = json_decode($body ?: '{}', true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$sourceId = bandpromo_brand_normalize_id((string) ($decoded['source_id'] ?? BANDPROMO_BRAND_DEFAULT_ID));
$newId = bandpromo_brand_normalize_id((string) ($decoded['new_id'] ?? ''));
$title = trim((string) ($decoded['title'] ?? ''));

try {
    bandpromo_brand_ensure_seeded($root);
    $document = bandpromo_brand_duplicate($root, $sourceId, $newId, $title);

    bandpromo_admin_audit_log('theme_duplicated', [
        'target_type' => 'theme',
        'target_id' => 'data/themes/' . $newId . '.json',
        'status' => 'ok',
        'data' => ['source_id' => $sourceId],
    ]);

    echo json_encode(['ok' => true, 'document' => $document]);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
