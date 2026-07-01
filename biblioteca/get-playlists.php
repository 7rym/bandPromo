<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/playlist-storage.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);

try {
    bandpromo_playlist_ensure_seeded($root);
    $playlists = bandpromo_playlist_admin_registry_entries($root);
    $defaultId = bandpromo_playlist_default_active_id($root);
    $activeId = $defaultId;

    echo json_encode([
        'ok' => true,
        'default_playlist_id' => $defaultId,
        'demo_playlist_id' => BANDPROMO_PLAYLIST_DEMO_ID,
        'active_playlist_id' => $activeId,
        'playlists' => $playlists,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
