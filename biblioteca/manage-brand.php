<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/brand-storage.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'PATCH') {
        $body = file_get_contents('php://input');
        $payload = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        $brandId = bandpromo_brand_normalize_id((string) (
            $_GET['brand']
            ?? $_GET['theme']
            ?? ($payload['id'] ?? '')
        ));
        if ($brandId === '') {
            throw new InvalidArgumentException('Brand id is required.');
        }

        $title = (string) ($payload['title'] ?? '');
        $newStorageId = trim((string) ($payload['storage_id'] ?? $payload['new_id'] ?? ''));
        $migrated = null;
        if ($newStorageId !== '' && bandpromo_brand_canonical_id($newStorageId) !== $brandId) {
            $migrated = bandpromo_brand_migrate_id($root, $brandId, $newStorageId);
            $brandId = bandpromo_brand_canonical_id((string) ($migrated['brand_id'] ?? $newStorageId));
        }
        $entry = bandpromo_brand_update_title($root, $brandId, $title);
        $registry = bandpromo_brand_registry_entries($root);

        bandpromo_admin_audit_log('theme_updated', [
            'target_type' => 'theme',
            'target_id' => $brandId,
            'status' => 'ok',
            'data' => [
                'title' => (string) ($entry['title'] ?? ''),
                'migrated_from' => is_array($migrated) ? (string) ($migrated['from'] ?? '') : '',
                'rewrites' => is_array($migrated) ? ($migrated['rewrites'] ?? []) : [],
            ],
        ]);

        echo json_encode([
            'ok' => true,
            'brand' => $entry,
            'brands' => $registry,
            'theme' => $entry,
            'themes' => $registry,
            'migrated' => $migrated,
            'active_brand_id' => bandpromo_brand_active_id($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'DELETE') {
        $brandId = bandpromo_brand_normalize_id((string) ($_GET['brand'] ?? $_GET['theme'] ?? ''));
        if ($brandId === '') {
            throw new InvalidArgumentException('Brand id is required.');
        }

        bandpromo_brand_delete($root, $brandId);
        $registry = bandpromo_brand_registry_entries($root);
        $activeId = bandpromo_brand_active_id($root);

        bandpromo_admin_audit_log('theme_deleted', [
            'target_type' => 'theme',
            'target_id' => $brandId,
            'status' => 'ok',
        ]);

        echo json_encode([
            'ok' => true,
            'deleted' => $brandId,
            'brands' => $registry,
            'active_brand_id' => $activeId,
            'themes' => $registry,
            'active_theme_id' => $activeId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'PATCH or DELETE required']);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
