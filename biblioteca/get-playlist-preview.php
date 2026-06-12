<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/light-build-tasks.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';

$includeBundled = isset($_GET['include_bundled']) && $_GET['include_bundled'] === '1';
session_write_close();

function bandpromo_playlist_preview_load_saved_order(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter($decoded, static function ($entry) {
        return is_string($entry) && $entry !== '';
    }));
}

function bandpromo_playlist_preview_from_built_playlist(bool $includeBundled): ?array
{
    $root = dirname(__DIR__);
    $playlistFile = $root . '/play/playlist.json';
    if (!file_exists($playlistFile)) {
        return null;
    }

    $raw = file_get_contents($playlistFile);
    if ($raw === false) {
        return null;
    }

    $playlist = json_decode($raw, true);
    if (!is_array($playlist)) {
        return null;
    }

    $savedOrder = bandpromo_playlist_preview_load_saved_order($root . '/data/playlist-order.json');
    if ($savedOrder) {
        $orderIndex = [];
        foreach ($savedOrder as $index => $name) {
            $orderIndex[$name] = $index;
        }

        usort($playlist, static function ($left, $right) use ($orderIndex) {
            $leftFile = (string) ($left['file'] ?? '');
            $rightFile = (string) ($right['file'] ?? '');
            $leftIndex = $orderIndex[$leftFile] ?? PHP_INT_MAX;
            $rightIndex = $orderIndex[$rightFile] ?? PHP_INT_MAX;
            if ($leftIndex === $rightIndex) {
                return strcasecmp($leftFile, $rightFile);
            }
            return $leftIndex <=> $rightIndex;
        });
    }

    $tracks = [];
    $hiddenBundled = [];
    foreach ($playlist as $track) {
        if (!is_array($track)) {
            continue;
        }

        $file = trim((string) ($track['file'] ?? ''));
        if ($file === '') {
            continue;
        }

        $isBundled = strncmp($file, 'bandPromo_', 10) === 0;
        if ($isBundled && !$includeBundled) {
            $hiddenBundled[] = $file;
            continue;
        }

        $tracks[] = [
            'file' => $file,
            'title' => (string) ($track['title'] ?? $file),
            'artist' => (string) ($track['artist'] ?? ''),
            'album' => (string) ($track['album'] ?? ''),
            'duration' => (int) ($track['duration'] ?? 0),
            'origin' => $isBundled ? 'bundled-placeholder' : 'user-upload',
            'sourceTier' => 'built-playlist',
        ];
    }

    return [
        'ok' => true,
        'tracks' => $tracks,
        'hiddenBundledSourceFiles' => $hiddenBundled,
        'unsupportedSourceFiles' => [],
        'includeBundled' => $includeBundled,
        'previewSource' => 'built-playlist',
    ];
}

$result = bandpromo_run_light_json_task('scripts/playlistPreview.py', [
    'includeBundled' => $includeBundled,
]);

$data = is_array($result['data'] ?? null) ? $result['data'] : null;
if (!$result['ok'] || !is_array($data) || empty($data['ok'])) {
    $fallback = bandpromo_playlist_preview_from_built_playlist($includeBundled);
    if (is_array($fallback)) {
        echo json_encode($fallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
    $output = trim((string) ($result['output'] ?? ''));
    $message = $error !== '' ? $error : ($output !== '' ? $output : 'Could not load playlist preview');
    http_response_code(500);
    echo json_encode(['error' => $message]);
    exit;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);