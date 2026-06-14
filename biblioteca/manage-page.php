<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/page-registry.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'POST') {
        $body = file_get_contents('php://input');
        $payload = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        $title = (string) ($payload['title'] ?? '');
        $label = (string) ($payload['label'] ?? '');
        $preferredId = (string) ($payload['id'] ?? '');
        $entry = bandpromo_page_create_page($root, $title, $label, $preferredId);

        bandpromo_admin_audit_log('page_created', [
            'target_type' => 'page',
            'target_id' => (string) ($entry['id'] ?? ''),
            'status' => 'ok',
            'data' => ['title' => (string) ($entry['title'] ?? '')],
        ]);

        echo json_encode([
            'ok' => true,
            'page' => $entry,
            'pages' => bandpromo_page_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'DELETE') {
        $pageId = bandpromo_page_normalize_id((string) ($_GET['page'] ?? ''));
        if ($pageId === '') {
            throw new InvalidArgumentException('Page id is required.');
        }

        bandpromo_page_delete_page($root, $pageId);

        bandpromo_admin_audit_log('page_deleted', [
            'target_type' => 'page',
            'target_id' => $pageId,
            'status' => 'ok',
        ]);

        echo json_encode([
            'ok' => true,
            'deleted' => $pageId,
            'pages' => bandpromo_page_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST or DELETE required']);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
