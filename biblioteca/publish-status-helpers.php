<?php
declare(strict_types=1);

require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/build-required.php';

function bandpromo_asset_audio_delivery_path(string $root, string $masterFilename): string
{
    $stem = pathinfo(basename($masterFilename), PATHINFO_FILENAME);

    return $root . '/media/audio/optimal/' . $stem . '.mp3';
}

function bandpromo_asset_audio_delivery_ready(string $root, string $masterFilename): bool
{
    $masterFilename = basename(trim($masterFilename));
    if ($masterFilename === '') {
        return false;
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFilename);
    if (is_array($asset)) {
        $delivery = is_array($asset['delivery'] ?? null) ? $asset['delivery'] : [];
        if (array_key_exists('audio_optimal', $delivery)) {
            return !empty($delivery['audio_optimal']);
        }
    }

    return is_file(bandpromo_asset_audio_delivery_path($root, $masterFilename));
}

function bandpromo_delivery_inventory_snapshot_path(string $root): string
{
    return $root . '/data/delivery/inventory-snapshot.json';
}

/**
 * Write path only: probe delivery files once, store flags on assets, snapshot inventory counts.
 */
function bandpromo_delivery_refresh_inventory_snapshot(string $root): array
{
    require_once __DIR__ . '/json-file-helpers.php';

    $registry = bandpromo_asset_load_registry($root);
    $registeredAudio = 0;
    $audioReady = 0;
    $missingDelivery = [];

    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || strtolower((string) ($asset['kind'] ?? 'audio')) !== 'audio') {
            continue;
        }

        $registeredAudio++;
        $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($masterFilename === '') {
            continue;
        }

        $ready = is_file(bandpromo_asset_audio_delivery_path($root, $masterFilename));
        $existingDelivery = is_array($asset['delivery'] ?? null) ? $asset['delivery'] : [];
        $previous = array_key_exists('audio_optimal', $existingDelivery)
            ? !empty($existingDelivery['audio_optimal'])
            : null;
        if ($previous !== $ready) {
            try {
                bandpromo_asset_update_entry($root, (string) ($asset['id'] ?? $assetId), [
                    'delivery' => [
                        'audio_optimal' => $ready,
                        'checked_at' => gmdate('c'),
                    ],
                ]);
            } catch (Throwable $throwable) {
                // Keep scanning even if one asset update fails.
            }
        }

        $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($originalFilename !== '' && ($previous === null || $previous !== $ready)) {
            require_once __DIR__ . '/media-library-state.php';
            bandpromo_media_files_index_patch_pool_ready($root, 'audio', $originalFilename, $ready);
        }

        if ($ready) {
            $audioReady++;
            continue;
        }

        $display = bandpromo_asset_read_audio_display($asset);
        $missingDelivery[] = [
            'asset_id' => (string) ($asset['id'] ?? $assetId),
            'master_filename' => $masterFilename,
            'title' => trim((string) ($display['title'] ?? '')),
        ];
    }

    $counts = bandpromo_delivery_inventory_counts_compute($root);
    $snapshot = [
        'version' => 1,
        'generated_at' => gmdate('c'),
        'registered_audio' => $registeredAudio,
        'audio_ready' => $audioReady,
        'missing_delivery' => $missingDelivery,
        'counts' => $counts,
    ];

    $dir = dirname(bandpromo_delivery_inventory_snapshot_path($root));
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    bandpromo_json_write_file(bandpromo_delivery_inventory_snapshot_path($root), $snapshot);

    return $snapshot;
}

function bandpromo_delivery_load_inventory_snapshot(string $root): ?array
{
    require_once __DIR__ . '/json-file-helpers.php';

    $path = bandpromo_delivery_inventory_snapshot_path($root);
    if (!is_file($path)) {
        return null;
    }

    $decoded = bandpromo_json_read_array_file($path);
    return is_array($decoded) ? $decoded : null;
}

function bandpromo_delivery_mark_audio_ready(string $root, array $masterFilenames, bool $ready = true): void
{
    require_once __DIR__ . '/media-library-state.php';

    foreach ($masterFilenames as $filename) {
        $filename = basename(trim((string) $filename));
        if ($filename === '') {
            continue;
        }
        $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
            ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
        if ($asset === null) {
            continue;
        }
        $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
        try {
            bandpromo_asset_update_entry($root, (string) $asset['id'], [
                'delivery' => [
                    'audio_optimal' => $ready,
                    'checked_at' => gmdate('c'),
                ],
            ]);
        } catch (Throwable $throwable) {
            continue;
        }

        if ($originalFilename !== '') {
            bandpromo_media_files_index_sync_file($root, 'audio', $originalFilename);
        }
    }
}

function bandpromo_delivery_count_visible_media(string $root, string $target): int
{
    require_once __DIR__ . '/media-library-state.php';

    bandpromo_media_files_index_ensure_target($root, $target);

    $count = 0;
    foreach (bandpromo_media_files_index_list($root, $target) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $filename = (string) ($entry['name'] ?? '');
        if ($filename === '' || strcasecmp($filename, 'desktop.ini') === 0) {
            continue;
        }

        if (bandpromo_media_is_hidden_for_install($target, $filename)) {
            continue;
        }

        $extension = strtolower((string) ($entry['original_format'] ?? pathinfo($filename, PATHINFO_EXTENSION)));
        if ($extension === '' || $extension === 'htaccess') {
            continue;
        }

        $count++;
    }

    return $count;
}

function bandpromo_delivery_inventory_counts_compute(string $root): array
{
    require_once __DIR__ . '/release-storage.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/page-registry.php';
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/demo-catalog-state.php';
    require_once __DIR__ . '/media-library-state.php';

    $releases = 0;
    $releasesWithTracks = 0;
    $catalogTrackIds = [];
    $catalogTrackReadyIds = [];
    foreach (bandpromo_release_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $releaseId = bandpromo_release_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '' || !bandpromo_release_visible_in_admin_catalog($root, $entry)) {
            continue;
        }

        $releases++;
        $trackCount = 0;
        try {
            $document = bandpromo_release_load_document($root, $releaseId);
            $tracks = is_array($document['tracks'] ?? null) ? $document['tracks'] : [];
            $trackCount = count($tracks);
            foreach ($tracks as $track) {
                if (!is_array($track)) {
                    continue;
                }
                $assetId = trim((string) ($track['asset_id'] ?? ''));
                $master = basename(trim((string) ($track['master_filename'] ?? $track['file'] ?? '')));
                if ($assetId !== '') {
                    $catalogTrackIds[$assetId] = true;
                } elseif ($master !== '') {
                    $catalogTrackIds['file:' . $master] = true;
                } else {
                    continue;
                }

                if ($master === '' && $assetId !== '') {
                    $asset = bandpromo_asset_lookup_by_id($root, $assetId);
                    $master = is_array($asset)
                        ? basename(trim((string) ($asset['master_filename'] ?? '')))
                        : '';
                }
                if ($master !== '' && bandpromo_asset_audio_delivery_ready($root, $master)) {
                    $readyKey = $assetId !== '' ? $assetId : ('file:' . $master);
                    $catalogTrackReadyIds[$readyKey] = true;
                }
            }
        } catch (Throwable $throwable) {
            $trackCount = (int) ($entry['track_count'] ?? 0);
        }

        if ($trackCount > 0) {
            $releasesWithTracks++;
        }
    }
    $catalogTracks = count($catalogTrackIds);
    $catalogTracksReady = count($catalogTrackReadyIds);

    $playlists = 0;
    $playlistsWithTracks = 0;
    $playlistTracks = 0;
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '' || !bandpromo_demo_catalog_entity_is_visible($root, $playlistId)) {
            continue;
        }

        $playlists++;
        $trackCount = (int) ($entry['track_count'] ?? 0);
        if ($trackCount > 0) {
            $playlistsWithTracks++;
            $playlistTracks += $trackCount;
        }
    }

    $galleries = 0;
    $galleryItems = 0;
    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '' || !bandpromo_demo_catalog_entity_is_visible($root, $galleryId)) {
            continue;
        }

        $galleries++;
        if (!bandpromo_gallery_document_is_empty($root, $galleryId)) {
            try {
                $document = bandpromo_gallery_load_document($root, $galleryId);
                $galleryItems += count(is_array($document['entries'] ?? null) ? $document['entries'] : []);
            } catch (Throwable $throwable) {
                // Skip unreadable gallery documents in the inventory summary.
            }
        }
    }

    $pages = 0;
    foreach (bandpromo_page_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $pageId = bandpromo_page_normalize_id((string) ($entry['id'] ?? ''));
        if ($pageId === '' || $pageId === BANDPROMO_PAGE_REQUIRED_ID) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login' || !empty($entry['required'])) {
            continue;
        }
        if (!bandpromo_page_runtime_present($root, $pageId)) {
            continue;
        }

        $pages++;
    }

    $brands = 0;
    $operatorBrands = 0;
    foreach (bandpromo_brand_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $brands++;
        if (empty($entry['system'])) {
            $operatorBrands++;
        }
    }

    $audioFiles = bandpromo_delivery_count_visible_media($root, 'audio');
    $illustrations = bandpromo_delivery_count_visible_media($root, 'illustrations');
    $photos = bandpromo_delivery_count_visible_media($root, 'photos');
    $images = $illustrations + $photos;
    $videos = bandpromo_delivery_count_visible_media($root, 'video');
    $themeAssets = bandpromo_delivery_count_visible_media($root, 'special');
    $operatorMedia = bandpromo_media_install_has_operator_uploads($root);

    return [
        'releases' => $releases,
        'releases_with_tracks' => $releasesWithTracks,
        'catalog_tracks' => $catalogTracks,
        'catalog_tracks_ready' => $catalogTracksReady,
        'playlists' => $playlists,
        'playlists_with_tracks' => $playlistsWithTracks,
        'playlist_tracks' => $playlistTracks,
        'audio_files' => $audioFiles,
        'illustrations' => $illustrations,
        'photos' => $photos,
        'images' => $images,
        'videos' => $videos,
        'theme_assets' => $themeAssets,
        'galleries' => $galleries,
        'gallery_items' => $galleryItems,
        'pages' => $pages,
        'brands' => $brands,
        'operator_brands' => $operatorBrands,
        'operator_media_present' => $operatorMedia,
    ];
}

function bandpromo_delivery_inventory_counts(string $root): array
{
    $snapshot = bandpromo_delivery_load_inventory_snapshot($root);
    if (is_array($snapshot) && is_array($snapshot['counts'] ?? null)) {
        return $snapshot['counts'];
    }

    return bandpromo_delivery_inventory_counts_compute($root);
}

function bandpromo_delivery_inventory_tile_detail(string $id, array $counts): string
{
    switch ($id) {
        case 'releases':
            $withTracks = (int) ($counts['releases_with_tracks'] ?? 0);
            return $withTracks > 0
                ? $withTracks . ' with tracks'
                : 'Ready for your catalog';
        case 'playlists':
            $withTracks = (int) ($counts['playlists_with_tracks'] ?? 0);
            return $withTracks > 0
                ? $withTracks . ' ready to play'
                : 'Shape your listening flow';
        case 'tracks':
            return (int) ($counts['catalog_tracks'] ?? 0) > 0
                ? 'Across your releases'
                : 'Add audio to get started';
        case 'audio':
            return !empty($counts['operator_media_present'])
                ? 'Including your uploads'
                : 'Starter and catalog audio';
        case 'images':
            $parts = [];
            if ((int) ($counts['illustrations'] ?? 0) > 0) {
                $parts[] = (int) $counts['illustrations'] . ' illustrations';
            }
            if ((int) ($counts['photos'] ?? 0) > 0) {
                $parts[] = (int) $counts['photos'] . ' photos';
            }
            return $parts !== [] ? implode(' · ', $parts) : 'Artwork and photos';
        case 'videos':
            return (int) ($counts['videos'] ?? 0) > 0
                ? 'Originals kept on disk'
                : 'Upload when you are ready';
        case 'galleries':
            $items = (int) ($counts['gallery_items'] ?? 0);
            return $items > 0
                ? $items . ' gallery item' . ($items === 1 ? '' : 's')
                : 'Showcase your visuals';
        case 'pages':
            return (int) ($counts['pages'] ?? 0) > 0
                ? 'Campaign pages in the player'
                : 'Add a Bio or story page';
        case 'brands':
            $operator = (int) ($counts['operator_brands'] ?? 0);
            return $operator > 0
                ? 'Your visual identity'
                : 'Create a custom brand';
        default:
            return '';
    }
}

function bandpromo_delivery_inventory_tiles(array $counts): array
{
    $tiles = [
        ['id' => 'releases', 'icon' => '💿', 'value' => (int) ($counts['releases'] ?? 0), 'label' => 'Releases'],
        ['id' => 'playlists', 'icon' => '🎧', 'value' => (int) ($counts['playlists'] ?? 0), 'label' => 'Playlists'],
        ['id' => 'tracks', 'icon' => '🎵', 'value' => (int) ($counts['catalog_tracks'] ?? 0), 'label' => 'Catalog tracks'],
        ['id' => 'galleries', 'icon' => '📷', 'value' => (int) ($counts['galleries'] ?? 0), 'label' => 'Galleries'],
        ['id' => 'pages', 'icon' => '📄', 'value' => (int) ($counts['pages'] ?? 0), 'label' => 'Pages'],
        ['id' => 'brands', 'icon' => '✨', 'value' => (int) ($counts['operator_brands'] ?? 0), 'label' => 'Brands'],
    ];

    foreach ($tiles as $index => $tile) {
        $tiles[$index]['detail'] = bandpromo_delivery_inventory_tile_detail((string) $tile['id'], $counts);
    }

    return $tiles;
}

function bandpromo_delivery_inventory_copy(array $counts, int $audioReady, int $audioTotal, bool $deliveryOk): array
{
    $catalogTracks = (int) ($counts['catalog_tracks'] ?? 0);
    $releasesWithTracks = (int) ($counts['releases_with_tracks'] ?? 0);
    $playlistsWithTracks = (int) ($counts['playlists_with_tracks'] ?? 0);
    $images = (int) ($counts['images'] ?? 0);
    $pages = (int) ($counts['pages'] ?? 0);
    $operatorMedia = !empty($counts['operator_media_present']);

    if ($catalogTracks > 0 && $releasesWithTracks > 0) {
        $headline = sprintf(
            '%d track%s across %d release%s',
            $catalogTracks,
            $catalogTracks === 1 ? '' : 's',
            $releasesWithTracks,
            $releasesWithTracks === 1 ? '' : 's'
        );
    } elseif ($catalogTracks > 0) {
        $headline = sprintf('%d catalogued track%s', $catalogTracks, $catalogTracks === 1 ? '' : 's');
    } elseif ($operatorMedia) {
        $headline = 'Your uploads are on the site';
    } else {
        $headline = 'Your starter site is ready to grow';
    }

    if ($deliveryOk && $audioTotal > 0 && $audioReady === $audioTotal) {
        $subheadline = 'Every catalogued track has listener-ready streaming files.';
    } elseif ($catalogTracks > 0 && $playlistsWithTracks > 0) {
        $subheadline = sprintf(
            '%d playlist%s shaped for the player.',
            $playlistsWithTracks,
            $playlistsWithTracks === 1 ? '' : 's'
        );
    } elseif ($images > 0 && $pages > 0) {
        $subheadline = 'Artwork, pages, and presentation assets are in place.';
    } elseif ($operatorMedia) {
        $subheadline = 'You have already started making this site your own.';
    } else {
        $subheadline = 'Upload, edit, and save — bandPromo keeps building from here.';
    }

    return [
        'headline' => $headline,
        'subheadline' => $subheadline,
    ];
}

function bandpromo_delivery_build_inventory(string $root, int $registeredAudio, int $audioReady): array
{
    $counts = bandpromo_delivery_inventory_counts_compute($root);
    $catalogTracks = (int) ($counts['catalog_tracks'] ?? 0);
    $catalogReady = (int) ($counts['catalog_tracks_ready'] ?? 0);
    if ($catalogTracks > 0) {
        $audioTotal = $catalogTracks;
        $audioReady = min($catalogReady, $catalogTracks);
    } else {
        $audioTotal = max($registeredAudio, (int) ($counts['audio_files'] ?? 0));
    }
    $deliveryOk = $audioTotal === 0 || $audioReady >= $audioTotal;
    $copy = bandpromo_delivery_inventory_copy($counts, $audioReady, $audioTotal, $deliveryOk);
    $percent = $audioTotal > 0 ? (int) round(($audioReady / $audioTotal) * 100) : 100;

    return [
        'headline' => $copy['headline'],
        'subheadline' => $copy['subheadline'],
        'tiles' => bandpromo_delivery_inventory_tiles($counts),
        'delivery' => [
            'audio_ready' => $audioReady,
            'audio_total' => $audioTotal,
            'percent' => min(100, max(0, $percent)),
            'complete' => $deliveryOk,
        ],
        'counts' => $counts,
    ];
}

/**
 * @param array{include_inventory?:bool,include_uncatalogued_scan?:bool} $options
 */
function bandpromo_publish_status_summary(string $root, array $options = []): array
{
    $includeInventory = array_key_exists('include_inventory', $options)
        ? !empty($options['include_inventory'])
        : true;
    $includeUncataloguedScan = array_key_exists('include_uncatalogued_scan', $options)
        ? !empty($options['include_uncatalogued_scan'])
        : true;

    // Uncatalogued scan walks originals + playlists — keep off hot inbox paths.
    $uncatalogued = $includeUncataloguedScan
        ? bandpromo_list_uncatalogued_audio_originals($root)
        : [];

    $snapshot = bandpromo_delivery_load_inventory_snapshot($root);
    if (!is_array($snapshot) && $includeInventory) {
        $snapshot = bandpromo_delivery_refresh_inventory_snapshot($root);
    }

    if (is_array($snapshot)) {
        $registeredAudio = (int) ($snapshot['registered_audio'] ?? 0);
        $audioReady = (int) ($snapshot['audio_ready'] ?? 0);
        $missingDelivery = is_array($snapshot['missing_delivery'] ?? null)
            ? $snapshot['missing_delivery']
            : [];
    } else {
        $registry = bandpromo_asset_load_registry($root);
        $registeredAudio = 0;
        $audioReady = 0;
        $missingDelivery = [];

        foreach ($registry['assets'] as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            if (strtolower((string) ($asset['kind'] ?? 'audio')) !== 'audio') {
                continue;
            }

            $registeredAudio++;
            $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
            if ($masterFilename === '') {
                continue;
            }
            if (bandpromo_asset_audio_delivery_ready($root, $masterFilename)) {
                $audioReady++;
                continue;
            }

            $display = bandpromo_asset_read_audio_display($asset);
            $missingDelivery[] = [
                'asset_id' => (string) ($asset['id'] ?? ''),
                'master_filename' => $masterFilename,
                'title' => trim((string) ($display['title'] ?? '')),
            ];
        }
    }

    $buildState = bandpromo_get_build_required_state();
    $pendingPublish = !empty($buildState['required']);
    $checks = [];

    if (count($uncatalogued) > 0) {
        $checks[] = [
            'id' => 'uncatalogued_audio',
            'severity' => 'attention',
            'label' => 'Uploads still being prepared',
            'count' => count($uncatalogued),
            'detail' => 'bandPromo is registering new audio files so delivery can be created.',
            'action' => 'This usually finishes automatically within a few moments.',
        ];
    }

    if ($missingDelivery !== []) {
        $checks[] = [
            'id' => 'missing_audio_delivery',
            'severity' => 'attention',
            'label' => 'Streaming files still missing',
            'count' => count($missingDelivery),
            'detail' => 'Some catalogued audio does not have listener-ready MP3 delivery files yet.',
            'action' => 'bandPromo usually creates these after uploads and saves. Use Refresh site files if you want to rebuild everything now.',
        ];
    }

    if ($pendingPublish) {
        $tasks = is_array($buildState['tasks'] ?? null) ? $buildState['tasks'] : [];
        $checks[] = [
            'id' => 'publish_pending',
            'severity' => 'attention',
            'label' => 'Delivery refresh recommended',
            'count' => max(1, count($tasks)),
            'detail' => $tasks !== []
                ? 'Pending: ' . implode(', ', array_map('strval', $tasks))
                : 'Recent site changes may need updated delivery files.',
            'action' => 'Use Rebuild all deliverables when you want extra reassurance that everything is current.',
        ];
    }

    $ok = $checks === [];
    $inventory = $includeInventory
        ? bandpromo_delivery_build_inventory($root, $registeredAudio, $audioReady)
        : [
            'headline' => '',
            'subheadline' => '',
            'tiles' => [],
            'delivery' => [
                'audio_ready' => $audioReady,
                'audio_total' => $registeredAudio,
                'percent' => $registeredAudio > 0 ? (int) round(($audioReady / $registeredAudio) * 100) : 100,
                'complete' => $registeredAudio === 0 || $audioReady >= $registeredAudio,
            ],
            'counts' => [],
        ];

    return [
        'ok' => $ok,
        'generated_at' => gmdate('c'),
        'summary' => [
            'registered_audio' => $registeredAudio,
            'audio_ready' => $audioReady,
            'uncatalogued_uploads' => count($uncatalogued),
            'missing_delivery' => count($missingDelivery),
            'publish_pending' => $pendingPublish,
        ],
        'inventory' => $inventory,
        'checks' => $checks,
        'samples' => [
            'uncatalogued' => array_slice($uncatalogued, 0, 5),
            'missing_delivery' => array_slice($missingDelivery, 0, 5),
        ],
    ];
}
