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
$requestedSegment = trim((string) ($_GET['playlist'] ?? ''));
$playlistId = $requestedSegment !== ''
    ? bandpromo_playlist_resolve_route_id($root, $requestedSegment)
    : '';
if ($playlistId === '') {
    $playlistId = bandpromo_playlist_default_active_id($root);
}

$username = trim((string) ($_SESSION['username'] ?? ''));
$role = $username !== '' ? getUserRole($username) : 'user';
$operatorBypass = in_array($role, ['admin', 'developer'], true);
$preferredVariant = bandpromo_preferred_audio_variant($_SESSION['quality'] ?? null);

try {
    if (!bandpromo_playlist_is_player_visible($root, $playlistId, $operatorBypass)) {
        http_response_code(404);
        echo json_encode(['error' => 'This playlist is not available yet.']);
        exit;
    }

    $payload = bandpromo_playlist_load_player_response($root, $playlistId, $preferredVariant);
    $builtAt = trim((string) ($payload['player_built_at'] ?? ''));
    if ($builtAt !== '') {
        header('ETag: "' . sha1($playlistId . ':' . $builtAt) . '"');
        header('Cache-Control: private, max-age=3600, stale-while-revalidate=86400');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $throwable) {
    http_response_code(503);
    echo json_encode(['error' => $throwable->getMessage()]);
}
