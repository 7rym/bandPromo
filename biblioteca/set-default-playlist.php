<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/playlist-storage.php';

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

$playlistId = bandpromo_playlist_normalize_id((string) ($decoded['playlist_id'] ?? ''));

try {
    bandpromo_playlist_ensure_seeded($root);
    if ($playlistId === '') {
        throw new InvalidArgumentException('playlist_id is required.');
    }

    bandpromo_playlist_set_default_id($root, $playlistId);

    bandpromo_admin_audit_log('playlist_set_default', [
        'target_type' => 'playlist',
        'target_id' => $playlistId,
        'status' => 'ok',
    ]);

    echo json_encode([
        'ok' => true,
        'default_playlist_id' => $playlistId,
        'playlists' => bandpromo_playlist_admin_registry_entries($root),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
