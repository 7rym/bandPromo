<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/media-delivery-helpers.php';
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
    if ($playlistId === BANDPROMO_PLAYLIST_DEMO_ID) {
        bandpromo_ensure_bundled_demo_audio_delivery($root);
    }
    $tracks = bandpromo_playlist_materialize_for_player($root, $playlistId, $operatorBypass, $preferredVariant);
    $deliverySummary = bandpromo_playlist_delivery_summary($tracks);
    $registryEntry = null;
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (($entry['id'] ?? '') === $playlistId) {
            $registryEntry = $entry;
            break;
        }
    }

    echo json_encode([
        'playlist_id' => $playlistId,
        'playlist_slug' => bandpromo_playlist_public_slug($root, $playlistId),
        'playlist_title' => (string) ($registryEntry['title'] ?? $playlistId),
        'preferred_audio_variant' => $preferredVariant,
        'delivery_summary' => $deliverySummary,
        'tracks' => $tracks,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['error' => $throwable->getMessage()]);
}
