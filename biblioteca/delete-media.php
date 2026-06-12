<?php
/**
 * Delete media files.
 * POST body: { target: "audio|cover|photos|video", filename: "..." }
 * or         { target: "audio|cover|photos|video", filenames: ["...", "..."] }
 * Admin-only.
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/media-reference-helpers.php';
require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/gallery-helpers.php';
require_once __DIR__ . '/admin-api-guard.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$root = dirname(__DIR__);
$dirs = [
    'audio'         => $root . '/media/audio/original',
    'illustrations' => $root . '/media/img/original',
    'photos'        => $root . '/media/photo/original',
    'video'         => $root . '/media/video/original',
    'special'       => $root . '/media/special',
];

$target = $body['target'] ?? '';
$mode = isset($body['mode']) ? strtolower(trim((string) $body['mode'])) : 'delete';
$detach_references = !array_key_exists('detach_references', $body) || !empty($body['detach_references']);

if (!isset($dirs[$target])) {
    echo json_encode(['error' => 'Unknown target']);
    exit;
}

$requestedFiles = [];
if (isset($body['filenames']) && is_array($body['filenames'])) {
    $requestedFiles = $body['filenames'];
} elseif (isset($body['filename'])) {
    $requestedFiles = [$body['filename']];
}

if ($requestedFiles === []) {
    echo json_encode(['error' => 'No filename provided']);
    exit;
}

function bandpromo_audio_master_paths(string $root, string $filename): array {
    $master_dir = $root . '/media/audio/master';
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    foreach (['flac', 'mp3', 'wav'] as $ext) {
        $path = $master_dir . '/' . $stem . '.' . $ext;
        if (is_file($path)) {
            $paths[] = $path;
        }
    }

    return $paths ?? [];
}

function bandpromo_audio_delivery_paths(string $root, string $filename): array {
    $optimal_dir = $root . '/media/audio/optimal';
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $paths = [];
    $candidate = $optimal_dir . '/' . $stem . '.mp3';
    if (is_file($candidate)) {
        $paths[] = $candidate;
    }
    return $paths;
}

function bandpromo_video_delivery_path(string $root, string $filename): string {
    return $root . '/media/video/optimal/' . pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
}

function bandpromo_image_delivery_path(string $root, string $target, string $filename): string {
    $subdir = $target === 'photos' ? 'photo' : 'img';
    return $root . '/media/' . $subdir . '/optimal/' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
}

function bandpromo_collect_media_references(string $root, string $target, string $filename): array {
    if (in_array($target, ['illustrations', 'photos', 'video'], true)) {
        return bandpromo_media_reference_collect_references($root, $target, $filename);
    }

    $references = [];

    if ($target === 'audio') {
        $playlist = bandpromo_json_read_array_file($root . '/play/playlist.json');
        if (is_array($playlist)) {
            foreach ($playlist as $track) {
                if (!is_array($track)) {
                    continue;
                }
                $label = trim((string) ($track['title'] ?? $track['file'] ?? ''));
                if (trim((string) ($track['file'] ?? '')) === $filename) {
                    $references[] = [
                        'scope' => 'playlist',
                        'kind' => 'playlist-track',
                        'label' => $label !== '' ? $label : $filename,
                    ];
                }
            }
        }
    }

    return $references;
}

function bandpromo_summarize_reference_counts(array $references): array {
    $summary = [
        'playlist_tracks' => 0,
        'playlist_covers' => 0,
        'gallery_items' => 0,
        'theme_assets' => 0,
        'release_fallbacks' => 0,
        'total' => 0,
    ];

    foreach ($references as $reference) {
        $kind = (string) ($reference['kind'] ?? '');
        if ($kind === 'playlist-track') {
            $summary['playlist_tracks']++;
        } elseif ($kind === 'playlist-cover') {
            $summary['playlist_covers']++;
        } elseif ($kind === 'gallery-item') {
            $summary['gallery_items']++;
        } elseif (in_array($kind, ['theme-cover', 'theme-background', 'theme-background-video', 'share-image'], true)) {
            $summary['theme_assets']++;
        } elseif ($kind === 'release-fallback') {
            $summary['release_fallbacks']++;
        }
        $summary['total']++;
    }

    return $summary;
}

function bandpromo_cleanup_media_references(string $root, string $target, string $filename): array {
    $cleanup = [
        'playlist_tracks_removed' => 0,
        'playlist_covers_cleared' => 0,
        'gallery_items_removed' => 0,
        'warnings' => [],
    ];

    if ($target === 'audio' || $target === 'illustrations') {
        $playlist_file = $root . '/play/playlist.json';
        $playlist = bandpromo_json_read_array_file($playlist_file);
        if (is_array($playlist)) {
            $changed = false;
            $updated = [];
            foreach ($playlist as $track) {
                if (!is_array($track)) {
                    $updated[] = $track;
                    continue;
                }
                if ($target === 'audio' && trim((string) ($track['file'] ?? '')) === $filename) {
                    $cleanup['playlist_tracks_removed']++;
                    $changed = true;
                    continue;
                }
                if ($target === 'illustrations' && basename(trim((string) ($track['cover'] ?? ''))) === $filename) {
                    $track['cover'] = '';
                    $cleanup['playlist_covers_cleared']++;
                    $changed = true;
                }
                $updated[] = $track;
            }
            if ($changed && !bandpromo_json_write_file($playlist_file, $updated)) {
                $cleanup['warnings'][] = 'Could not update play/playlist.json';
            }
        }
    }

    if ($target === 'audio') {
        $order_file = $root . '/data/playlist-order.json';
        $order = bandpromo_json_read_array_file($order_file);
        if (is_array($order)) {
            $updated_order = array_values(array_filter($order, static function ($entry) use ($filename) {
                return is_string($entry) && $entry !== $filename;
            }));
            if (count($updated_order) !== count($order) && !bandpromo_json_write_file($order_file, $updated_order)) {
                $cleanup['warnings'][] = 'Could not update data/playlist-order.json';
            }
        }
    }

    if (in_array($target, ['illustrations', 'photos', 'video'], true)) {
        $gallery_file = $root . '/data/gallery.json';
        $gallery = bandpromo_json_read_array_file($gallery_file);
        if (is_array($gallery)) {
            $updated_gallery = [];
            foreach ($gallery as $item) {
                if (is_array($item) && bandpromo_media_reference_gallery_matches_target($target, $filename, $item)) {
                    $cleanup['gallery_items_removed']++;
                    continue;
                }
                $updated_gallery[] = $item;
            }
            if ($cleanup['gallery_items_removed'] > 0 && !bandpromo_json_write_file($gallery_file, $updated_gallery)) {
                $cleanup['warnings'][] = 'Could not update data/gallery.json';
            }
        }
    }

    return $cleanup;
}

function bandpromo_delete_media_item(string $root, array $dirs, string $target, string $filename, bool $detach_references, string $mode): array
{
    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return ['ok' => false, 'filename' => $filename, 'error' => 'Invalid filename'];
    }

    $path = $dirs[$target] . '/' . $safe;
    if (!file_exists($path)) {
        return ['ok' => false, 'filename' => $safe, 'error' => 'File not found'];
    }

    $references = bandpromo_collect_media_references($root, $target, $safe);
    $reference_summary = bandpromo_summarize_reference_counts($references);

    if ($mode === 'preview') {
        return [
            'ok' => true,
            'filename' => $safe,
            'action' => 'preview',
            'references' => $references,
            'reference_summary' => $reference_summary,
        ];
    }

    if (bandpromo_media_is_bundled_placeholder($safe)) {
        if (!bandpromo_media_set_hidden_for_install($target, $safe, true)) {
            bandpromo_admin_audit_log('media_hide_failed', [
                'target_type' => 'media',
                'target_id' => $target . '/' . $safe,
                'status' => 'error',
                'data' => ['error' => 'state write failed'],
            ]);
            return ['ok' => false, 'filename' => $safe, 'error' => 'Could not hide bundled demo file for this install'];
        }

        bandpromo_admin_audit_log('media_hidden', [
            'target_type' => 'media',
            'target_id' => $target . '/' . $safe,
            'status' => 'ok',
            'data' => ['origin' => 'bundled-placeholder'],
        ]);

        return [
            'ok' => true,
            'filename' => $safe,
            'action' => 'hidden',
            'message' => 'Bundled demo file hidden for this install.',
        ];
    }

    $reference_cleanup = [
        'playlist_tracks_removed' => 0,
        'playlist_covers_cleared' => 0,
        'gallery_items_removed' => 0,
        'warnings' => [],
    ];
    if ($detach_references && $reference_summary['total'] > 0) {
        $reference_cleanup = bandpromo_cleanup_media_references($root, $target, $safe);
        if (!empty($reference_cleanup['warnings'])) {
            return [
                'ok' => false,
                'filename' => $safe,
                'error' => implode(' ', $reference_cleanup['warnings']),
            ];
        }
    }

    if (!unlink($path)) {
        bandpromo_admin_audit_log('media_deleted', [
            'target_type' => 'media',
            'target_id' => $target . '/' . $safe,
            'status' => 'error',
            'data' => ['error' => 'unlink failed'],
        ]);
        return ['ok' => false, 'filename' => $safe, 'error' => 'Could not delete file — check permissions'];
    }

    $master_deleted = false;
    $master_warning = '';
    $audio_delivery_deleted = false;
    $video_poster_deleted = false;
    $video_delivery_deleted = false;
    $image_delivery_deleted = false;
    if ($target === 'audio') {
        foreach (bandpromo_audio_master_paths($root, $safe) as $master_path) {
            if (@unlink($master_path)) {
                $master_deleted = true;
            } else {
                $master_warning = 'Audio original was deleted, but one or more matching master files could not be removed';
            }
        }
        foreach (bandpromo_audio_delivery_paths($root, $safe) as $delivery_path) {
            if (@unlink($delivery_path)) {
                $audio_delivery_deleted = true;
            }
        }
    } elseif ($target === 'video') {
        $poster_path = bandpromo_gallery_video_poster_absolute_path($root, $safe);
        if (is_file($poster_path) && @unlink($poster_path)) {
            $video_poster_deleted = true;
        }
        $delivery_path = bandpromo_video_delivery_path($root, $safe);
        if (is_file($delivery_path) && @unlink($delivery_path)) {
            $video_delivery_deleted = true;
        }
    } elseif (in_array($target, ['illustrations', 'photos'], true)) {
        $delivery_path = bandpromo_image_delivery_path($root, $target, $safe);
        if (is_file($delivery_path) && @unlink($delivery_path)) {
            $image_delivery_deleted = true;
        }
    }

    bandpromo_media_set_hidden_for_install($target, $safe, false);

    bandpromo_admin_audit_log('media_deleted', [
        'target_type' => 'media',
        'target_id' => $target . '/' . $safe,
        'status' => 'ok',
        'data' => [
            'master_deleted' => $master_deleted,
            'master_warning' => $master_warning,
            'audio_delivery_deleted' => $audio_delivery_deleted,
            'video_poster_deleted' => $video_poster_deleted,
            'video_delivery_deleted' => $video_delivery_deleted,
            'image_delivery_deleted' => $image_delivery_deleted,
            'reference_summary' => $reference_summary,
            'reference_cleanup' => $reference_cleanup,
        ],
    ]);

    return [
        'ok' => true,
        'filename' => $safe,
        'action' => 'deleted',
        'master_deleted' => $master_deleted,
        'master_warning' => $master_warning,
        'audio_delivery_deleted' => $audio_delivery_deleted,
        'video_poster_deleted' => $video_poster_deleted,
        'video_delivery_deleted' => $video_delivery_deleted,
        'image_delivery_deleted' => $image_delivery_deleted,
        'references' => $references,
        'reference_summary' => $reference_summary,
        'reference_cleanup' => $reference_cleanup,
    ];
}

$requestedFiles = array_values(array_unique(array_map(static fn($value) => (string) $value, $requestedFiles)));
$results = [];
foreach ($requestedFiles as $filename) {
    $results[] = bandpromo_delete_media_item($root, $dirs, $target, $filename, $detach_references, $mode);
}

if ($mode === 'preview') {
    $references = [];
    foreach ($results as $result) {
        if (!empty($result['ok']) && !empty($result['references']) && is_array($result['references'])) {
            foreach ($result['references'] as $reference) {
                $references[] = array_merge(['filename' => (string) ($result['filename'] ?? '')], $reference);
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'files' => array_map(static function ($result) use ($root, $target) {
            $payload = [
                'filename' => (string) ($result['filename'] ?? ''),
                'reference_summary' => is_array($result['reference_summary'] ?? null) ? $result['reference_summary'] : bandpromo_summarize_reference_counts([]),
                'references' => is_array($result['references'] ?? null) ? $result['references'] : [],
            ];
            if (in_array($target, ['illustrations', 'photos', 'video'], true) && $payload['filename'] !== '') {
                $payload['reference_info'] = bandpromo_media_reference_describe_file($root, $target, $payload['filename']);
                if ($target === 'illustrations') {
                    $payload['cover_info'] = $payload['reference_info'];
                }
            }
            return $payload;
        }, $results),
        'reference_summary' => bandpromo_summarize_reference_counts($references),
        'references' => $references,
    ]);
    exit;
}

if (count($results) === 1) {
    $result = $results[0];
    if (!$result['ok']) {
        echo json_encode(['error' => $result['error'] ?? 'Delete failed']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'action' => $result['action'] ?? 'deleted',
        'message' => $result['message'] ?? '',
        'master_deleted' => $result['master_deleted'] ?? false,
        'master_warning' => $result['master_warning'] ?? '',
        'reference_summary' => $result['reference_summary'] ?? bandpromo_summarize_reference_counts([]),
        'reference_cleanup' => $result['reference_cleanup'] ?? null,
    ]);
    exit;
}

$successful = array_values(array_filter($results, static fn($result) => !empty($result['ok'])));
$failed = array_values(array_filter($results, static fn($result) => empty($result['ok'])));
$hiddenCount = count(array_filter($successful, static fn($result) => ($result['action'] ?? '') === 'hidden'));
$deletedCount = count($successful) - $hiddenCount;
$warnings = array_values(array_filter(array_map(static fn($result) => (string) ($result['master_warning'] ?? ''), $successful)));
$totalReferenceCleanup = [
    'playlist_tracks_removed' => 0,
    'playlist_covers_cleared' => 0,
    'gallery_items_removed' => 0,
];
foreach ($successful as $result) {
    $cleanup = is_array($result['reference_cleanup'] ?? null) ? $result['reference_cleanup'] : [];
    $totalReferenceCleanup['playlist_tracks_removed'] += (int) ($cleanup['playlist_tracks_removed'] ?? 0);
    $totalReferenceCleanup['playlist_covers_cleared'] += (int) ($cleanup['playlist_covers_cleared'] ?? 0);
    $totalReferenceCleanup['gallery_items_removed'] += (int) ($cleanup['gallery_items_removed'] ?? 0);
}

if ($successful === []) {
    echo json_encode([
        'error' => 'Could not remove any of the selected files',
        'failed' => array_map(static fn($result) => [
            'filename' => $result['filename'] ?? '',
            'error' => $result['error'] ?? 'Delete failed',
        ], $failed),
    ]);
    exit;
}

$messageParts = [];
if ($deletedCount > 0) {
    $messageParts[] = sprintf('Removed %d file%s', $deletedCount, $deletedCount === 1 ? '' : 's');
}
if ($hiddenCount > 0) {
    $messageParts[] = sprintf('hid %d bundled demo file%s', $hiddenCount, $hiddenCount === 1 ? '' : 's');
}
if ($failed !== []) {
    $messageParts[] = sprintf('%d failed', count($failed));
}
if ($totalReferenceCleanup['playlist_tracks_removed'] > 0) {
    $messageParts[] = sprintf('removed %d playlist entr%s', $totalReferenceCleanup['playlist_tracks_removed'], $totalReferenceCleanup['playlist_tracks_removed'] === 1 ? 'y' : 'ies');
}
if ($totalReferenceCleanup['playlist_covers_cleared'] > 0) {
    $messageParts[] = sprintf('cleared %d playlist cover reference%s', $totalReferenceCleanup['playlist_covers_cleared'], $totalReferenceCleanup['playlist_covers_cleared'] === 1 ? '' : 's');
}
if ($totalReferenceCleanup['gallery_items_removed'] > 0) {
    $messageParts[] = sprintf('removed %d gallery item%s', $totalReferenceCleanup['gallery_items_removed'], $totalReferenceCleanup['gallery_items_removed'] === 1 ? '' : 's');
}

echo json_encode([
    'ok' => true,
    'count' => count($successful),
    'deleted_count' => $deletedCount,
    'hidden_count' => $hiddenCount,
    'failed_count' => count($failed),
    'failed' => array_map(static fn($result) => [
        'filename' => $result['filename'] ?? '',
        'error' => $result['error'] ?? 'Delete failed',
    ], $failed),
    'warnings' => $warnings,
    'reference_cleanup' => $totalReferenceCleanup,
    'message' => ucfirst(implode('; ', $messageParts)) . '.',
]);
