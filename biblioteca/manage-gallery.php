<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/gallery-storage.php';

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
        $preferredId = (string) ($payload['id'] ?? '');
        $entry = bandpromo_gallery_create($root, $title, $preferredId);

        bandpromo_admin_audit_log('gallery_created', [
            'target_type' => 'gallery',
            'target_id' => (string) ($entry['id'] ?? ''),
            'status' => 'ok',
            'data' => ['title' => (string) ($entry['title'] ?? '')],
        ]);

        echo json_encode([
            'ok' => true,
            'gallery' => $entry,
            'galleries' => bandpromo_gallery_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'PATCH') {
        $body = file_get_contents('php://input');
        $payload = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        $galleryId = bandpromo_gallery_normalize_id((string) ($_GET['gallery'] ?? ($payload['id'] ?? '')));
        if ($galleryId === '') {
            throw new InvalidArgumentException('Gallery id is required.');
        }

        $title = (string) ($payload['title'] ?? '');
        $entry = bandpromo_gallery_update_details($root, $galleryId, $title);

        bandpromo_admin_audit_log('gallery_updated', [
            'target_type' => 'gallery',
            'target_id' => $galleryId,
            'status' => 'ok',
            'data' => ['title' => (string) ($entry['title'] ?? '')],
        ]);

        echo json_encode([
            'ok' => true,
            'gallery' => $entry,
            'galleries' => bandpromo_gallery_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'DELETE') {
        $galleryId = bandpromo_gallery_normalize_id((string) ($_GET['gallery'] ?? ''));
        if ($galleryId === '') {
            throw new InvalidArgumentException('Gallery id is required.');
        }

        bandpromo_gallery_delete($root, $galleryId);

        bandpromo_admin_audit_log('gallery_deleted', [
            'target_type' => 'gallery',
            'target_id' => $galleryId,
            'status' => 'ok',
        ]);

        echo json_encode([
            'ok' => true,
            'deleted' => $galleryId,
            'galleries' => bandpromo_gallery_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST, PATCH, or DELETE required']);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
