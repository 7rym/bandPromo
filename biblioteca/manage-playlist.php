<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/release-storage.php';

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

        $fromReleaseId = bandpromo_release_normalize_id((string) ($payload['from_release_id'] ?? ''));
        if ($fromReleaseId !== '') {
            $entry = bandpromo_playlist_create_from_release($root, $fromReleaseId);

            bandpromo_admin_audit_log('playlist_created', [
                'target_type' => 'playlist',
                'target_id' => (string) ($entry['id'] ?? ''),
                'status' => 'ok',
                'data' => [
                    'title' => (string) ($entry['title'] ?? ''),
                    'from_release_id' => $fromReleaseId,
                ],
            ]);

            echo json_encode([
                'ok' => true,
                'playlist' => $entry,
                'playlists' => bandpromo_playlist_admin_registry_entries($root),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $title = (string) ($payload['title'] ?? '');
        $preferredId = (string) ($payload['id'] ?? '');
        $entry = bandpromo_playlist_create($root, $title, $preferredId);

        bandpromo_admin_audit_log('playlist_created', [
            'target_type' => 'playlist',
            'target_id' => (string) ($entry['id'] ?? ''),
            'status' => 'ok',
            'data' => ['title' => (string) ($entry['title'] ?? '')],
        ]);

        echo json_encode([
            'ok' => true,
            'playlist' => $entry,
            'playlists' => bandpromo_playlist_admin_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'PATCH') {
        $body = file_get_contents('php://input');
        $payload = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        $playlistId = bandpromo_playlist_normalize_id((string) ($_GET['playlist'] ?? ($payload['id'] ?? '')));
        if ($playlistId === '') {
            throw new InvalidArgumentException('Playlist id is required.');
        }

        $entry = bandpromo_playlist_update_details($root, $playlistId, $payload);

        bandpromo_admin_audit_log('playlist_updated', [
            'target_type' => 'playlist',
            'target_id' => $playlistId,
            'status' => 'ok',
            'data' => [
                'title' => (string) ($entry['title'] ?? ''),
                'publish_date' => (string) ($entry['publish_date'] ?? ''),
                'slug' => (string) ($entry['slug'] ?? ''),
            ],
        ]);

        echo json_encode([
            'ok' => true,
            'playlist' => $entry,
            'playlists' => bandpromo_playlist_admin_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'DELETE') {
        $playlistId = bandpromo_playlist_normalize_id((string) ($_GET['playlist'] ?? ''));
        if ($playlistId === '') {
            throw new InvalidArgumentException('Playlist id is required.');
        }

        bandpromo_playlist_delete($root, $playlistId);

        bandpromo_admin_audit_log('playlist_deleted', [
            'target_type' => 'playlist',
            'target_id' => $playlistId,
            'status' => 'ok',
        ]);

        echo json_encode([
            'ok' => true,
            'deleted' => $playlistId,
            'playlists' => bandpromo_playlist_admin_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST, PATCH, or DELETE required']);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
