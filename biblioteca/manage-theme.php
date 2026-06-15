<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/theme-storage.php';

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

        $themeId = bandpromo_theme_normalize_id((string) ($_GET['theme'] ?? ($payload['id'] ?? '')));
        if ($themeId === '') {
            throw new InvalidArgumentException('Theme id is required.');
        }

        $title = (string) ($payload['title'] ?? '');
        $entry = bandpromo_theme_update_title($root, $themeId, $title);

        bandpromo_admin_audit_log('theme_updated', [
            'target_type' => 'theme',
            'target_id' => $themeId,
            'status' => 'ok',
            'data' => ['title' => (string) ($entry['title'] ?? '')],
        ]);

        echo json_encode([
            'ok' => true,
            'theme' => $entry,
            'themes' => bandpromo_theme_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'DELETE') {
        $themeId = bandpromo_theme_normalize_id((string) ($_GET['theme'] ?? ''));
        if ($themeId === '') {
            throw new InvalidArgumentException('Theme id is required.');
        }

        bandpromo_theme_delete($root, $themeId);

        bandpromo_admin_audit_log('theme_deleted', [
            'target_type' => 'theme',
            'target_id' => $themeId,
            'status' => 'ok',
        ]);

        echo json_encode([
            'ok' => true,
            'deleted' => $themeId,
            'themes' => bandpromo_theme_registry_entries($root),
            'active_theme_id' => bandpromo_theme_active_id($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'PATCH or DELETE required']);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
