<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/page-storage.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode(is_string($rawBody) ? $rawBody : '', true);
if (!is_array($payload) || !isset($payload['document']) || !is_array($payload['document'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing page document payload.']);
    exit;
}

$pageKey = isset($_GET['page']) ? bandpromo_page_normalize_id((string) $_GET['page']) : bandpromo_page_normalize_id((string) ($payload['document']['id'] ?? 'bio'));
$root = dirname(__DIR__);

try {
    if (!bandpromo_page_is_allowed_id($pageKey, $root)) {
        throw new InvalidArgumentException('Unknown page.');
    }

    $normalized = bandpromo_page_normalize_document($payload['document'], $pageKey);
    $html = bandpromo_page_render_document($normalized);

    echo json_encode([
        'ok' => true,
        'page' => $pageKey,
        'document' => $normalized,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
