<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/campaign-ownership-helpers.php';

bandpromo_enforce_https();
session_write_close();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$root = dirname(__DIR__);
$releaseId = bandpromo_campaign_normalize_id((string) ($_GET['campaign'] ?? $_GET['release'] ?? ''));
$section = strtolower(trim((string) ($_GET['section'] ?? '')));
$allowedSections = ['tracks', 'playlists', 'galleries', 'pages', 'branding', 'presskit'];

if ($releaseId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Release id is required.']);
    exit;
}
if (!in_array($section, $allowedSections, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown release preview section.']);
    exit;
}

try {
    $document = bandpromo_campaign_load_document($root, $releaseId);

    if ($section === 'tracks') {
        $data = bandpromo_campaign_admin_preview_tracks($root, $document);
    } elseif ($section === 'presskit') {
        $data = [
            'short_description' => (string) ($document['short_description'] ?? ''),
            'description' => (string) ($document['description'] ?? ''),
            'epk' => is_array($document['epk'] ?? null)
                ? bandpromo_campaign_normalize_epk($document['epk'])
                : bandpromo_campaign_default_epk(),
        ];
    } else {
        $children = bandpromo_campaign_ownership_children($root, $releaseId);
        if ($section === 'branding') {
            $data = [
                'brand_id' => (string) ($children['brand_id'] ?? ''),
                'brand' => is_array($children['brand'] ?? null) ? $children['brand'] : null,
            ];
        } else {
            $data = is_array($children[$section] ?? null) ? $children[$section] : [];
        }
    }

    echo json_encode([
        'ok' => true,
        'release_id' => $releaseId,
        'section' => $section,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
