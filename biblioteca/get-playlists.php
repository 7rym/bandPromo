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
        'configured_default_playlist_id' => bandpromo_playlist_configured_default_id($root),
        'demo_playlist_id' => BANDPROMO_PLAYLIST_DEMO_ID,
        'demo_catalog_visible' => bandpromo_demo_catalog_is_visible($root),
        'active_playlist_id' => $activeId,
        'package_types' => array_map(static function (string $type): array {
            return [
                'id' => $type,
                'label' => bandpromo_playlist_package_type_label($type),
                'default_play_order' => bandpromo_playlist_default_play_order_for_package_type($type),
            ];
        }, bandpromo_playlist_package_types()),
        'playlists' => $playlists,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
