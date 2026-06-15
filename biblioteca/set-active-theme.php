<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/theme-storage.php';

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

$themeId = bandpromo_theme_normalize_id((string) ($decoded['theme_id'] ?? ''));

try {
    bandpromo_theme_ensure_seeded($root);
    if ($themeId === '') {
        throw new InvalidArgumentException('theme_id is required.');
    }

    bandpromo_theme_set_active_id($root, $themeId);

    bandpromo_admin_audit_log('theme_activated', [
        'target_type' => 'theme',
        'target_id' => $themeId,
        'status' => 'ok',
    ]);

    echo json_encode([
        'ok' => true,
        'active_theme_id' => $themeId,
    ]);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
