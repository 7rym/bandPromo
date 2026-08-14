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
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/audio-master-detail-helpers.php';

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
bandpromo_asset_registry_ensure_migrated($root);
$dirs = [
    'audio'         => $root . '/media/audio/original',
    'illustrations' => $root . '/media/img/original',
    'photos'        => $root . '/media/photo/original',
    'video'         => $root . '/media/video/original',
    'special'       => bandpromo_media_target_dir('special') ?? ($root . '/media/visual/original'),
    'sfx'           => bandpromo_media_target_dir('sfx') ?? ($root . '/media/sfx/original'),
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

function bandpromo_collect_media_references(string $root, string $target, string $filename): array {
    if (in_array($target, ['illustrations', 'photos', 'video', 'special', 'sfx'], true)) {
        return bandpromo_media_reference_collect_references($root, $target, $filename);
    }

    $references = [];

    if ($target === 'audio') {
        $references = array_merge($references, bandpromo_playlist_collect_audio_references($root, $filename));
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
        'page_images' => 0,
        'release_posters' => 0,
        'release_press_photos' => 0,
        'playlist_posters' => 0,
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
        } elseif (in_array($kind, [
            'theme-cover',
            'theme-background',
            'theme-background-video',
            'share-image',
            'brand-logo',
            'welcome-audio',
            'loggedin-audio',
            'brand-library',
        ], true)) {
            $summary['theme_assets']++;
        } elseif ($kind === 'release-fallback') {
            $summary['release_fallbacks']++;
        } elseif ($kind === 'page-image') {
            $summary['page_images']++;
        } elseif ($kind === 'release-poster') {
            $summary['release_posters']++;
        } elseif ($kind === 'release-press-photo') {
            $summary['release_press_photos']++;
        } elseif ($kind === 'playlist-poster') {
            $summary['playlist_posters']++;
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

    if ($target === 'audio') {
        $containerCleanup = bandpromo_playlist_remove_audio_reference($root, $filename);
        $cleanup['playlist_tracks_removed'] += (int) ($containerCleanup['entries_removed'] ?? 0);
    }

    if ($target === 'illustrations') {
        $coverCleanup = bandpromo_audio_master_clear_cover_reference($root, $filename);
        $cleanup['playlist_covers_cleared'] += (int) ($coverCleanup['covers_cleared'] ?? 0);
    }

    if (in_array($target, ['illustrations', 'photos', 'video'], true)) {
        require_once __DIR__ . '/gallery-storage.php';
        try {
            bandpromo_gallery_ensure_seeded($root);
            $cleanup['gallery_items_removed'] += bandpromo_gallery_detach_media($root, $target, $filename);
        } catch (Throwable $throwable) {
            $cleanup['warnings'][] = 'Could not update gallery containers: ' . $throwable->getMessage();
        }
    }

    return $cleanup;
}

function bandpromo_delete_media_item(string $root, array $dirs, string $target, string $filename, bool $detach_references, string $mode): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/visual-master-helpers.php';
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/sfx-helpers.php';

    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return ['ok' => false, 'filename' => $filename, 'error' => 'Invalid filename'];
    }

    $asset = bandpromo_asset_lookup_from_media_ref($root, $safe)
        ?? bandpromo_asset_lookup_by_master_filename($root, $safe)
        ?? bandpromo_asset_lookup_by_original_filename($root, $safe);

    $listingName = $safe;
    $originalName = $safe;
    $masterName = '';
    $assetId = '';
    if (is_array($asset)) {
        $assetId = trim((string) ($asset['id'] ?? ''));
        $originalName = basename(trim((string) ($asset['original_filename'] ?? $safe)));
        $masterName = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($masterName !== '') {
            $listingName = $masterName;
        } elseif ($originalName !== '') {
            $listingName = $originalName;
        }
    } else {
        $source = bandpromo_media_files_index_resolve_source($root, $target, $safe);
        if (is_array($source)) {
            $listingName = basename((string) ($source['name'] ?? $safe));
            $originalName = basename((string) ($source['original_filename'] ?? $listingName));
        }
    }

    $pathExists = false;
    if ($target === 'audio' && $masterName !== '') {
        $pathExists = is_file($root . '/media/audio/master/' . $masterName)
            || ($originalName !== '' && is_file($dirs[$target] . '/' . $originalName));
    } elseif ($target === 'sfx' && is_array($asset)) {
        $pathExists = ($masterName !== '' && is_file(bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $masterName))
            || ($originalName !== '' && is_file($dirs[$target] . '/' . $originalName));
    } elseif (in_array($target, ['illustrations', 'photos', 'video'], true) && is_array($asset)) {
        $working = bandpromo_visual_working_path($root, $asset);
        $pathExists = ($working !== '' && is_file($working))
            || ($originalName !== '' && (
                is_file(bandpromo_visual_unified_original_path($root, $originalName))
                || is_file($dirs[$target] . '/' . $originalName)
            ));
    } else {
        $pathExists = is_file($dirs[$target] . '/' . $safe)
            || (is_array($asset) && $masterName !== '');
    }

    if (!$pathExists && $target === 'audio') {
        $master = bandpromo_find_audio_master($root, $safe);
        if (!empty($master['exists']) && !empty($master['filename'])) {
            $pathExists = true;
            $listingName = basename((string) $master['filename']);
            $masterName = $listingName;
            $originalName = basename((string) ($master['original_filename'] ?? $originalName));
        }
    }

    if (!$pathExists) {
        return ['ok' => false, 'filename' => $safe, 'error' => 'File not found'];
    }

    require_once __DIR__ . '/demo-catalog-state.php';
    $demoAssetSet = bandpromo_demo_release_asset_set($root);
    $lockCandidates = array_values(array_unique(array_filter([
        $target . '|' . $safe,
        $target . '|' . $listingName,
        $originalName !== '' ? $target . '|' . $originalName : '',
        $assetId !== '' ? $target . '|' . $assetId : '',
    ])));
    foreach ($lockCandidates as $lockKey) {
        if (bandpromo_asset_is_in_locked_release($root, $lockKey, $demoAssetSet)) {
            return [
                'ok' => false,
                'filename' => $listingName,
                'error' => 'This file belongs to the locked demo release. Unlock the demo release on localhost before deleting campaign demo media.',
            ];
        }
    }

    $referenceNames = array_values(array_unique(array_filter([$safe, $listingName, $originalName, $masterName])));
    $references = [];
    foreach ($referenceNames as $refName) {
        $references = array_merge($references, bandpromo_collect_media_references($root, $target, $refName));
    }
    if ($assetId !== '') {
        $references = array_merge(
            $references,
            bandpromo_media_reference_collect_brand_library_references($root, $assetId)
        );
    }
    $reference_summary = bandpromo_summarize_reference_counts($references);

    if ($mode === 'preview') {
        return [
            'ok' => true,
            'filename' => $listingName,
            'action' => 'preview',
            'references' => $references,
            'reference_summary' => $reference_summary,
        ];
    }

    $brandLibraryReferences = array_values(array_filter(
        $references,
        static fn(array $reference): bool => (string) ($reference['kind'] ?? '') === 'brand-library'
    ));
    if ($brandLibraryReferences !== []) {
        return [
            'ok' => false,
            'filename' => $listingName,
            'error' => 'Remove this asset from every Brand library before deleting the global media.',
            'references' => $references,
            'reference_summary' => $reference_summary,
        ];
    }

    if (!$detach_references && $reference_summary['total'] > 0) {
        $multi = $reference_summary['total'] > 1;
        return [
            'ok' => false,
            'filename' => $listingName,
            'error' => $multi
                ? 'This media is referenced by multiple containers. Remove or detach those references before deleting the shared file.'
                : 'This media is still referenced. Detach references first, or remove the reference from the owning container.',
            'references' => $references,
            'reference_summary' => $reference_summary,
        ];
    }

    $reference_cleanup = [
        'playlist_tracks_removed' => 0,
        'playlist_covers_cleared' => 0,
        'gallery_items_removed' => 0,
        'warnings' => [],
    ];
    if ($detach_references && $reference_summary['total'] > 0) {
        foreach ($referenceNames as $refName) {
            $partial = bandpromo_cleanup_media_references($root, $target, $refName);
            $reference_cleanup['playlist_tracks_removed'] += (int) ($partial['playlist_tracks_removed'] ?? 0);
            $reference_cleanup['playlist_covers_cleared'] += (int) ($partial['playlist_covers_cleared'] ?? 0);
            $reference_cleanup['gallery_items_removed'] += (int) ($partial['gallery_items_removed'] ?? 0);
            if (!empty($partial['warnings'])) {
                $reference_cleanup['warnings'] = array_merge(
                    $reference_cleanup['warnings'],
                    $partial['warnings']
                );
            }
        }
        if (!empty($reference_cleanup['warnings'])) {
            return [
                'ok' => false,
                'filename' => $listingName,
                'error' => implode(' ', $reference_cleanup['warnings']),
            ];
        }
    }

    $master_deleted = false;
    $master_warning = '';
    $audio_delivery_deleted = false;
    $video_poster_deleted = false;
    $video_delivery_deleted = false;
    $image_delivery_deleted = false;
    $unlinkedAny = false;

    if ($target === 'audio') {
        if ($originalName !== '' && is_file($dirs[$target] . '/' . $originalName) && @unlink($dirs[$target] . '/' . $originalName)) {
            $unlinkedAny = true;
        }
        foreach (bandpromo_audio_master_paths_for_original($root, $listingName) as $master_path) {
            if (@unlink($master_path)) {
                $master_deleted = true;
                $unlinkedAny = true;
            } else {
                $master_warning = 'Audio tiers partially deleted; one or more master files could not be removed';
            }
        }
        if ($masterName !== '' && is_file($root . '/media/audio/master/' . $masterName) && @unlink($root . '/media/audio/master/' . $masterName)) {
            $master_deleted = true;
            $unlinkedAny = true;
        }
        foreach (bandpromo_audio_delivery_paths_for_original($root, $listingName) as $delivery_path) {
            if (@unlink($delivery_path)) {
                $audio_delivery_deleted = true;
                $unlinkedAny = true;
            }
        }
        if ($assetId !== '') {
            $deliveryById = $root . '/media/audio/optimal/' . $assetId . '.mp3';
            if (is_file($deliveryById) && @unlink($deliveryById)) {
                $audio_delivery_deleted = true;
                $unlinkedAny = true;
            }
            bandpromo_asset_unregister($root, $assetId);
        } elseif (is_array($asset)) {
            bandpromo_asset_unregister($root, (string) ($asset['id'] ?? ''));
        } else {
            bandpromo_asset_unregister_by_original_filename($root, $originalName !== '' ? $originalName : $safe);
        }
    } elseif ($target === 'sfx') {
        if ($originalName !== '' && is_file($dirs[$target] . '/' . $originalName) && @unlink($dirs[$target] . '/' . $originalName)) {
            $unlinkedAny = true;
        }
        if (is_array($asset) && ($asset['kind'] ?? '') === 'sfx') {
            bandpromo_sfx_delete_tier_files($root, $asset);
            $unlinkedAny = true;
            bandpromo_asset_unregister($root, (string) ($asset['id'] ?? ''));
        } else {
            bandpromo_asset_unregister_by_original_filename($root, $originalName !== '' ? $originalName : $safe);
        }
    } elseif ($target === 'video') {
        if ($originalName !== '' && is_file($dirs[$target] . '/' . $originalName) && @unlink($dirs[$target] . '/' . $originalName)) {
            $unlinkedAny = true;
        }
        $poster_path = $root . '/media/video/poster/' . pathinfo($originalName !== '' ? $originalName : $safe, PATHINFO_FILENAME) . '.jpg';
        if (is_file($poster_path) && @unlink($poster_path)) {
            $video_poster_deleted = true;
            $unlinkedAny = true;
        }
        // Leftover stem dual-write cleanup only (Visual delivery deleted below when registered).
        $delivery_path = $root . '/media/video/optimal/' . pathinfo($originalName !== '' ? $originalName : $safe, PATHINFO_FILENAME) . '.mp4';
        if (is_file($delivery_path) && @unlink($delivery_path)) {
            $video_delivery_deleted = true;
            $unlinkedAny = true;
        }
        if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
            bandpromo_visual_delivery_delete_for_asset($root, (string) ($asset['id'] ?? ''));
            bandpromo_visual_delete_tier_files($root, $asset);
            bandpromo_asset_unregister($root, (string) ($asset['id'] ?? ''));
            $unlinkedAny = true;
        }
    } elseif (in_array($target, ['illustrations', 'photos'], true)) {
        if ($originalName !== '' && is_file($dirs[$target] . '/' . $originalName) && @unlink($dirs[$target] . '/' . $originalName)) {
            $unlinkedAny = true;
        }
        // Leftover stem dual-write cleanup only.
        $delivery_path = $target === 'photos'
            ? ($root . '/media/photo/optimal/' . pathinfo($originalName !== '' ? $originalName : $safe, PATHINFO_FILENAME) . '.jpg')
            : ($root . '/media/img/optimal/' . pathinfo($originalName !== '' ? $originalName : $safe, PATHINFO_FILENAME) . '.jpg');
        if (is_file($delivery_path) && @unlink($delivery_path)) {
            $image_delivery_deleted = true;
            $unlinkedAny = true;
        }
        if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
            bandpromo_visual_delivery_delete_for_asset($root, (string) ($asset['id'] ?? ''));
            bandpromo_visual_delete_tier_files($root, $asset);
            bandpromo_asset_unregister($root, (string) ($asset['id'] ?? ''));
            $unlinkedAny = true;
        }
    } elseif ($target === 'special') {
        if (is_file($dirs[$target] . '/' . $safe) && @unlink($dirs[$target] . '/' . $safe)) {
            $unlinkedAny = true;
        }
        $mediaType = bandpromo_asset_infer_media_type_from_filename($safe);
        if (in_array($mediaType, ['image', 'video'], true) && is_array($asset)) {
            bandpromo_visual_delivery_delete_for_asset($root, (string) ($asset['id'] ?? ''));
            bandpromo_visual_delete_tier_files($root, $asset);
            bandpromo_asset_unregister($root, (string) ($asset['id'] ?? ''));
        }
    } else {
        if (!is_file($dirs[$target] . '/' . $safe) || !@unlink($dirs[$target] . '/' . $safe)) {
            return ['ok' => false, 'filename' => $safe, 'error' => 'Could not delete file — check permissions'];
        }
        $unlinkedAny = true;
    }

    if (!$unlinkedAny) {
        bandpromo_admin_audit_log('media_deleted', [
            'target_type' => 'media',
            'target_id' => $target . '/' . $listingName,
            'status' => 'error',
            'data' => ['error' => 'unlink failed'],
        ]);
        return ['ok' => false, 'filename' => $listingName, 'error' => 'Could not delete file — check permissions'];
    }

    foreach ($referenceNames as $refName) {
        bandpromo_media_set_hidden_for_install($target, $refName, false);
        bandpromo_media_files_index_remove($target, $refName);
    }

    bandpromo_admin_audit_log('media_deleted', [
        'target_type' => 'media',
        'target_id' => $target . '/' . $listingName,
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
            'asset_id' => $assetId,
        ],
    ]);

    return [
        'ok' => true,
        'filename' => $listingName,
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
            if (in_array($target, ['illustrations', 'photos', 'video', 'special'], true) && $payload['filename'] !== '') {
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
$deletedCount = count($successful);
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
