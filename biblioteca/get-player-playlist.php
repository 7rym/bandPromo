<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/auth.php';

bandpromo_enforce_https();

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$root = dirname(__DIR__);
$playlistId = bandpromo_playlist_normalize_id((string) ($_GET['playlist'] ?? ''));
if ($playlistId === '') {
    $playlistId = bandpromo_playlist_default_active_id($root);
}

$username = trim((string) ($_SESSION['username'] ?? ''));
$role = $username !== '' ? getUserRole($username) : 'user';
$operatorBypass = in_array($role, ['admin', 'developer'], true);

try {
    $tracks = bandpromo_playlist_materialize_for_player($root, $playlistId, $operatorBypass);
    $registryEntry = null;
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (($entry['id'] ?? '') === $playlistId) {
            $registryEntry = $entry;
            break;
        }
    }

    echo json_encode([
        'playlist_id' => $playlistId,
        'playlist_title' => (string) ($registryEntry['title'] ?? $playlistId),
        'tracks' => $tracks,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
}
