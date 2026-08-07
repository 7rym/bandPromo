<?php
declare(strict_types=1);

require_once __DIR__ . '/template-bootstrap.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/release-storage.php';

function bandpromo_content_autofix_step_result(string $id, string $label, array $details = []): array
{
    return array_merge([
        'id' => $id,
        'label' => $label,
        'changed' => 0,
        'skipped' => 0,
        'errors' => [],
        'items' => [],
    ], $details);
}

function bandpromo_content_autofix_materialize_audio_masters(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('materialize_masters', 'Prepare missing audio masters');
    $originalDir = $root . '/media/audio/original';
    if (!is_dir($originalDir)) {
        return $step;
    }

    foreach (scandir($originalDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $ext = strtolower((string) pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ['flac', 'mp3', 'wav'], true)) {
            continue;
        }

        $master = bandpromo_find_audio_master($root, $entry);
        if (!empty($master['exists'])) {
            $step['skipped']++;
            continue;
        }

        if ($dryRun) {
            $step['changed']++;
            $step['items'][] = $entry;
            continue;
        }

        $prepared = bandpromo_materialize_audio_master_from_original($root, $entry);
        if (!empty($prepared['prepared'])) {
            $step['changed']++;
            $step['items'][] = $entry;
        } elseif (!empty($prepared['warning'])) {
            $step['errors'][] = $entry . ': ' . (string) $prepared['warning'];
        } else {
            $step['skipped']++;
        }
    }

    return $step;
}

function bandpromo_content_autofix_canonicalize_master_filenames(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('canonical_masters', 'Rename audio masters to ast_{ULID} filenames');
    $registry = bandpromo_asset_load_registry($root);
    $masterDir = $root . '/media/audio/master';
    if (!is_dir($masterDir)) {
        return $step;
    }

    $registryChanged = false;
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }
        if (!bandpromo_asset_is_asset_id((string) $assetId)) {
            continue;
        }

        $format = strtolower((string) ($asset['master_format'] ?? pathinfo((string) ($asset['master_filename'] ?? ''), PATHINFO_EXTENSION)));
        if ($format === '') {
            $step['errors'][] = (string) $assetId . ': missing master format';
            continue;
        }

        $canonical = bandpromo_asset_master_filename_for_ulid((string) $assetId, $format);
        $current = basename((string) ($asset['master_filename'] ?? ''));
        if ($current === '' || $current === $canonical) {
            $step['skipped']++;
            continue;
        }

        $fromPath = $masterDir . '/' . $current;
        $toPath = $masterDir . '/' . $canonical;
        if (!is_file($fromPath)) {
            $step['errors'][] = $current . ': master file missing on disk';
            continue;
        }
        if (is_file($toPath) && realpath($fromPath) !== realpath($toPath)) {
            $step['errors'][] = $canonical . ': target master filename already exists';
            continue;
        }

        if ($dryRun) {
            $step['changed']++;
            $step['items'][] = ['asset_id' => $assetId, 'from' => $current, 'to' => $canonical];
            continue;
        }

        if (!@rename($fromPath, $toPath)) {
            $step['errors'][] = $current . ': could not rename to ' . $canonical;
            continue;
        }

        unset($registry['by_master_filename'][$current]);
        $registry['assets'][$assetId]['master_filename'] = $canonical;
        $registry['by_master_filename'][$canonical] = (string) $assetId;
        $registryChanged = true;
        $step['changed']++;
        $step['items'][] = ['asset_id' => $assetId, 'from' => $current, 'to' => $canonical];
    }

    if ($registryChanged && !$dryRun) {
        bandpromo_asset_write_registry($root, $registry);
    }

    return $step;
}

function bandpromo_content_autofix_resolve_pool_asset(string $root, string $poolFile): ?array
{
    $poolFile = basename(trim($poolFile));
    if ($poolFile === '') {
        return null;
    }

    $asset = bandpromo_asset_lookup_by_original_filename($root, $poolFile);
    if ($asset !== null) {
        return $asset;
    }

    $ext = strtolower((string) pathinfo($poolFile, PATHINFO_EXTENSION));
    if ($ext === 'wav') {
        $flacCandidate = pathinfo($poolFile, PATHINFO_FILENAME) . '.flac';
        $asset = bandpromo_asset_lookup_by_original_filename($root, $flacCandidate);
        if ($asset !== null) {
            return $asset;
        }
    }

    return null;
}

function bandpromo_content_autofix_normalize_playlist_kind(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('playlist_kind', 'Use system playlists in admin and player');
    bandpromo_playlist_ensure_seeded($root);
    $registry = bandpromo_playlist_load_registry($root);
    $registryChanged = false;

    foreach ($registry['playlists'] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $kind = strtolower(trim((string) ($entry['kind'] ?? 'system')));
        if ($kind === 'system') {
            $step['skipped']++;
            continue;
        }

        $playlistId = trim((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        $step['changed']++;
        $step['items'][] = ['playlist' => $playlistId, 'from' => $kind, 'to' => 'system'];

        if ($dryRun) {
            continue;
        }

        $registry['playlists'][$index]['kind'] = 'system';
        $registryChanged = true;

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
            if (strtolower(trim((string) ($document['kind'] ?? 'system'))) !== 'system') {
                $document['kind'] = 'system';
                bandpromo_playlist_write_document($root, $document);
            }
        } catch (Throwable $throwable) {
            $step['errors'][] = $playlistId . ': ' . $throwable->getMessage();
        }
    }

    if ($registryChanged && !$dryRun) {
        bandpromo_playlist_write_registry($root, $registry);
    }

    return $step;
}

function bandpromo_content_autofix_sync_playlist_entries(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('playlist_links', 'Link playlist entries to asset registry');
    bandpromo_playlist_ensure_seeded($root);
    $registry = bandpromo_playlist_load_registry($root);

    foreach ($registry['playlists'] as $playlistMeta) {
        if (!is_array($playlistMeta)) {
            continue;
        }
        $playlistId = trim((string) ($playlistMeta['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $playlistId . ': ' . $throwable->getMessage();
            continue;
        }

        $changed = false;
        $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $poolFile = basename(trim((string) ($entry['master_file'] ?? $entry['file'] ?? '')));
            if ($poolFile === '') {
                continue;
            }

            $asset = bandpromo_content_autofix_resolve_pool_asset($root, $poolFile);
            if ($asset === null) {
                $step['errors'][] = $playlistId . ': no asset for ' . $poolFile;
                continue;
            }

            $assetId = (string) ($asset['id'] ?? '');
            $releaseId = trim((string) ($asset['release_id'] ?? ''));

            $currentAssetId = trim((string) ($entry['asset_id'] ?? ''));
            $currentReleaseId = trim((string) ($entry['release_id'] ?? ''));
            if ($currentAssetId === $assetId && $currentReleaseId === $releaseId) {
                $step['skipped']++;
                continue;
            }

            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'playlist' => $playlistId,
                'file' => $poolFile,
                'asset_id' => $assetId,
                'release_id' => $releaseId,
            ];

            if (!$dryRun) {
                $entries[$index]['master_file'] = $poolFile;
                $entries[$index]['asset_id'] = $assetId;
                $entries[$index]['release_id'] = $releaseId;
            }
        }

        if ($changed && !$dryRun) {
            $document['entries'] = $entries;
            $document = bandpromo_playlist_clear_player_payload_fields($document);
            bandpromo_playlist_write_document($root, $document);
        }
    }

    return $step;
}

function bandpromo_release_sync_primary_audio_assets(string $root): void
{
    bandpromo_release_repair_catalog_release_ids($root);
}

function bandpromo_content_autofix_sync_releases(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('release_membership', 'Repair catalog release links on audio assets');
    if ($dryRun) {
        $registry = bandpromo_asset_load_registry($root);
        $membershipIndex = bandpromo_release_asset_membership_index($root);
        $staleCount = 0;
        $missingMembershipIds = 0;
        foreach ($registry['assets'] as $assetId => $asset) {
            if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
                continue;
            }
            $assignedReleaseId = bandpromo_release_normalize_id(trim((string) ($asset['release_id'] ?? '')));
            $memberships = $membershipIndex[(string) $assetId] ?? [];
            $documentReleaseId = '';
            if (count($memberships) === 1) {
                $documentReleaseId = bandpromo_release_normalize_id((string) ($memberships[0]['release_id'] ?? ''));
            }
            if ($documentReleaseId === '') {
                if ($assignedReleaseId !== '') {
                    $staleCount++;
                }
                continue;
            }
            if ($assignedReleaseId !== $documentReleaseId) {
                $staleCount++;
            }
        }
        foreach (bandpromo_release_registry_entries($root) as $entry) {
            $releaseId = bandpromo_release_normalize_id((string) ($entry['id'] ?? ''));
            if ($releaseId === '') {
                continue;
            }
            try {
                $document = bandpromo_release_load_document($root, $releaseId);
            } catch (Throwable $throwable) {
                continue;
            }
            foreach ($document['tracks'] ?? [] as $track) {
                if (!is_array($track)) {
                    continue;
                }
                $assetId = trim((string) ($track['asset_id'] ?? ''));
                if ($assetId === '' || bandpromo_asset_lookup_by_id($root, $assetId) !== null) {
                    continue;
                }
                $missingMembershipIds++;
            }
        }
        $step['changed'] = $staleCount + $missingMembershipIds;
        $step['items'][] = [
            'stale_catalog_links' => $staleCount,
            'missing_membership_asset_ids' => $missingMembershipIds,
        ];
        return $step;
    }

    bandpromo_release_sync_demo_audio_assets($root);
    $membershipRepair = bandpromo_release_repair_stale_membership_asset_ids($root);
    require_once __DIR__ . '/playlist-storage.php';
    $playlistRepair = bandpromo_playlist_repair_stale_track_asset_ids($root, $membershipRepair['remaps'] ?? []);
    $repaired = bandpromo_release_repair_catalog_release_ids($root);
    $step['changed'] = (int) ($membershipRepair['rebound'] ?? 0)
        + (int) ($playlistRepair['changed'] ?? 0)
        + ($repaired > 0 ? $repaired : 0);
    if (($membershipRepair['rebound'] ?? 0) > 0) {
        $step['items'][] = [
            'rebound_membership_asset_ids' => (int) $membershipRepair['rebound'],
            'releases' => $membershipRepair['releases'] ?? [],
        ];
    }
    if (($membershipRepair['unresolved'] ?? 0) > 0) {
        $step['items'][] = ['unresolved_membership_asset_ids' => (int) $membershipRepair['unresolved']];
    }
    if (($playlistRepair['changed'] ?? 0) > 0) {
        $step['items'][] = [
            'rebound_playlist_tracks' => (int) $playlistRepair['changed'],
            'playlists' => $playlistRepair['playlists'] ?? [],
        ];
    }
    if ($repaired > 0) {
        $step['items'][] = ['repaired_catalog_links' => $repaired];
    }

    return $step;
}

function bandpromo_content_autofix_sync_config_scope(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('config_scope', 'Dual-write scoped config fields');
    $configPath = $root . '/web-config.json';
    if (!is_file($configPath)) {
        $step['skipped'] = 1;
        return $step;
    }

    $decoded = bandpromo_json_read_array_file($configPath);
    if (!is_array($decoded)) {
        $step['errors'][] = 'web-config.json is invalid';
        return $step;
    }

    if ($dryRun) {
        $step['changed'] = 1;
        return $step;
    }

    bandpromo_sync_scoped_config_fields($decoded, ['site', 'social', 'media']);
    if (!bandpromo_json_write_file($configPath, $decoded)) {
        $step['errors'][] = 'Could not write web-config.json';
        return $step;
    }

    $step['changed'] = 1;
    return $step;
}

function bandpromo_content_autofix_sync_audio_display(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result(
        'audio_display_cache',
        'Refresh asset registry display cache from master tags'
    );

    if ($dryRun) {
        $registry = bandpromo_asset_load_registry($root);
        $pending = 0;
        foreach ($registry['assets'] as $asset) {
            if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
                continue;
            }
            $display = bandpromo_asset_read_audio_display($asset);
            if (!bandpromo_asset_audio_display_is_complete($display)) {
                $pending++;
            }
        }
        $step['changed'] = $pending;
        if ($pending > 0) {
            $step['items'][] = ['pending' => $pending];
        }

        return $step;
    }

    $result = bandpromo_asset_refresh_all_audio_displays($root);
    $step['changed'] = (int) ($result['changed'] ?? 0);
    $step['items'] = is_array($result['items'] ?? null) ? $result['items'] : [];

    $metaRestore = bandpromo_asset_restore_audio_meta_from_unregistered_masters($root);
    $restored = (int) ($metaRestore['restored'] ?? 0);
    if ($restored > 0) {
        $step['changed'] += $restored;
        $step['items'][] = [
            'restored_from_leftover_masters' => $restored,
            'covers' => (int) ($metaRestore['covers'] ?? 0),
            'details' => $metaRestore['items'] ?? [],
        ];
    }

    return $step;
}

function bandpromo_content_autofix_refresh_validation(string $root, bool $dryRun): array
{
    $step = bandpromo_content_autofix_step_result('validation_refresh', 'Refresh playlist validation report');
    if ($dryRun) {
        $step['skipped'] = 1;
        return $step;
    }

    $result = bandpromo_run_light_task('scripts/makePlaylists.py');
    if (empty($result['ok'])) {
        $output = trim((string) ($result['output'] ?? ''));
        $step['errors'][] = $output !== '' ? $output : 'Playlist validation refresh failed';
        return $step;
    }

    $step['changed'] = 1;
    return $step;
}

/**
 * Backfill brand shell asset_ids from current path slots (dual-write map).
 */
function bandpromo_content_autofix_sync_brand_asset_ids(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/theme-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $step = bandpromo_content_autofix_step_result('brand_asset_ids', 'Backfill brand shell asset_ids');
    bandpromo_theme_ensure_seeded($root);

    foreach (bandpromo_theme_registry_entries($root) as $entry) {
        $brandId = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
        if ($brandId === '') {
            continue;
        }
        try {
            $document = bandpromo_theme_load_document($root, $brandId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $brandId . ': ' . $throwable->getMessage();
            continue;
        }

        $assetIds = bandpromo_theme_normalize_asset_ids(
            is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : []
        );
        $assets = bandpromo_theme_normalize_assets(
            is_array($document['assets'] ?? null) ? $document['assets'] : []
        );
        $changed = false;

        foreach ($assets as $slot => $path) {
            $current = trim((string) ($assetIds[$slot] ?? ''));
            if ($current !== '') {
                $step['skipped']++;
                continue;
            }
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            $found = bandpromo_theme_lookup_asset_id_for_path($root, $path);
            if ($found === '') {
                continue;
            }
            $assetIds[$slot] = $found;
            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'brand' => $brandId,
                'slot' => $slot,
                'asset_id' => $found,
            ];
        }

        if (!$changed) {
            continue;
        }

        $document['asset_ids'] = $assetIds;
        $materialized = bandpromo_theme_materialize_asset_urls($root, $document);
        $document = $materialized['document'];
        if (!$dryRun) {
            bandpromo_theme_write_document($root, $document, ['allow_locked' => true]);
            if (bandpromo_brand_active_id($root) === $brandId) {
                bandpromo_theme_sync_assets_to_config($root, $document);
            }
        }
    }

    return $step;
}

/**
 * Backfill gallery entry asset_ids from src paths.
 */
function bandpromo_content_autofix_sync_gallery_asset_ids(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/theme-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $step = bandpromo_content_autofix_step_result('gallery_asset_ids', 'Backfill gallery entry asset_ids');
    bandpromo_gallery_ensure_seeded($root);

    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '') {
            continue;
        }
        try {
            $document = bandpromo_gallery_load_document($root, $galleryId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $galleryId . ': ' . $throwable->getMessage();
            continue;
        }

        $entries = is_array($document['entries'] ?? null) ? $document['entries'] : [];
        $changed = false;
        foreach ($entries as $index => $galleryEntry) {
            if (!is_array($galleryEntry)) {
                continue;
            }
            $current = trim((string) ($galleryEntry['asset_id'] ?? ''));
            if ($current !== '' && bandpromo_asset_is_asset_id($current)) {
                $step['skipped']++;
                continue;
            }
            $src = trim((string) ($galleryEntry['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $found = bandpromo_theme_lookup_asset_id_for_path($root, $src);
            if ($found === '' && bandpromo_asset_is_asset_id($src)) {
                $found = $src;
            }
            if ($found === '') {
                $basename = basename($src);
                $asset = bandpromo_asset_lookup_by_original_filename($root, $basename);
                if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
                    $found = (string) ($asset['id'] ?? '');
                }
            }
            if ($found === '') {
                continue;
            }
            $entries[$index]['asset_id'] = $found;
            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'gallery' => $galleryId,
                'src' => $src,
                'asset_id' => $found,
            ];
        }

        if ($changed && !$dryRun) {
            $document['entries'] = $entries;
            bandpromo_gallery_write_document($root, $document);
        }
    }

    return $step;
}

/**
 * Backfill page picture block + poster asset_ids.
 */
function bandpromo_content_autofix_sync_page_asset_ids(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/theme-storage.php';
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/page-blocks.php';

    $step = bandpromo_content_autofix_step_result('page_asset_ids', 'Backfill page picture asset_ids');
    bandpromo_page_seed_all_if_missing($root);

    foreach (bandpromo_page_registry_ids($root) as $pageId) {
        try {
            $document = bandpromo_page_load_document($root, $pageId);
        } catch (Throwable $throwable) {
            $step['errors'][] = $pageId . ': ' . $throwable->getMessage();
            continue;
        }

        $changed = false;
        $posterId = trim((string) ($document['poster_asset_id'] ?? ''));
        if ($posterId === '') {
            // No path field for poster; skip unless already set.
            $step['skipped']++;
        } elseif (!bandpromo_asset_is_asset_id($posterId)) {
            $document['poster_asset_id'] = '';
            $changed = true;
            $step['changed']++;
        }

        $blocks = is_array($document['blocks'] ?? null) ? $document['blocks'] : [];
        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            if ($type !== 'picture' && $type !== 'picture_richtext') {
                continue;
            }
            $current = trim((string) ($block['asset_id'] ?? ''));
            if ($current !== '' && bandpromo_asset_is_asset_id($current)) {
                $step['skipped']++;
                continue;
            }
            $src = trim((string) ($block['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $found = bandpromo_theme_lookup_asset_id_for_path($root, $src);
            if ($found === '') {
                $basename = basename(parse_url($src, PHP_URL_PATH) ?: $src);
                $asset = bandpromo_asset_lookup_by_original_filename($root, $basename);
                if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
                    $found = (string) ($asset['id'] ?? '');
                }
            }
            if ($found === '') {
                continue;
            }
            $blocks[$index]['asset_id'] = $found;
            $changed = true;
            $step['changed']++;
            $step['items'][] = [
                'page' => $pageId,
                'block' => $index,
                'asset_id' => $found,
            ];
        }

        if ($changed && !$dryRun) {
            $document['blocks'] = $blocks;
            bandpromo_page_save_document($root, $document);
        }
    }

    return $step;
}

/**
 * Rewrite audio display.cover / living_cover filename refs to asset_ids when known.
 */
function bandpromo_content_autofix_sync_audio_visual_refs(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/asset-registry.php';

    $step = bandpromo_content_autofix_step_result(
        'audio_visual_refs',
        'Rewrite audio cover/living-cover refs to asset_ids'
    );
    $registry = bandpromo_asset_load_registry($root);

    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }
        $display = is_array($asset['display'] ?? null) ? $asset['display'] : [];
        $changes = [];

        $cover = trim((string) ($display['cover'] ?? ''));
        if ($cover !== '' && !bandpromo_asset_is_asset_id($cover)) {
            $visual = bandpromo_asset_lookup_by_original_filename($root, basename($cover));
            if (is_array($visual) && ($visual['kind'] ?? '') === 'visual') {
                $found = (string) ($visual['id'] ?? '');
                if ($found !== '') {
                    $changes['cover'] = $found;
                }
            }
        } else {
            $step['skipped']++;
        }

        $living = trim((string) ($display['living_cover'] ?? ''));
        if ($living !== '' && !bandpromo_asset_is_asset_id($living)) {
            $visual = bandpromo_asset_lookup_by_original_filename($root, basename($living));
            if (is_array($visual) && ($visual['kind'] ?? '') === 'visual') {
                $found = (string) ($visual['id'] ?? '');
                if ($found !== '') {
                    $changes['living_cover'] = $found;
                }
            }
        } elseif ($living !== '') {
            $step['skipped']++;
        }

        if ($changes === []) {
            continue;
        }

        $step['changed']++;
        $step['items'][] = [
            'asset_id' => (string) $assetId,
            'changes' => $changes,
        ];
        if (!$dryRun) {
            bandpromo_asset_update_entry($root, (string) $assetId, [
                'display' => $changes,
            ]);
        }
    }

    return $step;
}

/**
 * Heal empty visual display from embedded IPTC/XMP (stills) or Matroska tags (video).
 */
function bandpromo_content_autofix_heal_visual_display(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/light-build-tasks.php';

    $step = bandpromo_content_autofix_step_result(
        'visual_display_heal',
        'Heal empty visual display fields from master IPTC/XMP or Matroska tags'
    );

    if ($dryRun) {
        $registry = bandpromo_asset_load_registry($root);
        $pending = 0;
        foreach ($registry['assets'] as $asset) {
            if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
                continue;
            }
            $display = bandpromo_asset_read_visual_display($asset);
            if ($display['title'] === '' || $display['description'] === '' || $display['captured_at'] === '') {
                $pending++;
            }
        }
        $step['changed'] = $pending;
        if ($pending > 0) {
            $step['items'][] = ['pending' => $pending];
        }

        return $step;
    }

    $result = bandpromo_run_light_json_task('scripts/visualMasterMetadata.py', [
        'action' => 'heal_empty',
    ]);
    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
    if (empty($result['ok']) || empty($data['ok'])) {
        $step['errors'][] = (string) ($data['error'] ?? $result['error'] ?? 'Visual display heal failed');

        return $step;
    }

    $step['changed'] = (int) ($data['count'] ?? 0);
    $step['items'] = is_array($data['healed'] ?? null) ? $data['healed'] : [];

    return $step;
}

/**
 * Relocate visual originals + materialize media/visual/master/ast_* (M2).
 * Video masters remux to MKV.
 */
function bandpromo_content_autofix_materialize_visual_masters(string $root, bool $dryRun): array
{
    require_once __DIR__ . '/visual-master-helpers.php';

    $step = bandpromo_content_autofix_step_result(
        'materialize_visual_masters',
        'Prepare visual originals/masters under media/visual/'
    );
    $registry = bandpromo_asset_load_registry($root);

    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        $working = bandpromo_visual_working_path($root, $asset);
        $mediaType = strtolower(trim((string) ($asset['media_type'] ?? '')));
        $format = strtolower(trim((string) ($asset['master_format'] ?? pathinfo(
            (string) ($asset['original_filename'] ?? ''),
            PATHINFO_EXTENSION
        ))));
        $expectedFormat = $mediaType === 'video' ? 'mkv' : $format;
        $masterPath = $expectedFormat !== ''
            ? bandpromo_visual_master_path($root, (string) $assetId, $expectedFormat)
            : '';
        $needsMaster = $masterPath === '' || !is_file($masterPath);
        $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
        $unified = $originalFilename !== ''
            ? bandpromo_visual_unified_original_path($root, $originalFilename)
            : '';
        $needsOriginal = $unified !== '' && !is_file($unified);
        $currentMaster = basename(trim((string) ($asset['master_filename'] ?? '')));
        $needsCanonical = $currentMaster === ''
            || !bandpromo_asset_is_asset_id((string) pathinfo($currentMaster, PATHINFO_FILENAME))
            || ($mediaType === 'video' && strtolower(trim((string) ($asset['master_format'] ?? ''))) !== 'mkv');

        if (!$needsMaster && !$needsOriginal && !$needsCanonical) {
            $step['skipped']++;
            continue;
        }
        if ($working === '' && $needsMaster) {
            $step['errors'][] = (string) $assetId . ': no source bytes for visual master';
            continue;
        }

        $step['changed']++;
        $step['items'][] = [
            'asset_id' => (string) $assetId,
            'original' => $originalFilename,
            'needs_original' => $needsOriginal,
            'needs_master' => $needsMaster || $needsCanonical,
        ];
        if (!$dryRun) {
            bandpromo_visual_ensure_tiers_for_asset($root, (string) $assetId);
        }
    }

    return $step;
}

function bandpromo_content_autofix_run(string $root, bool $dryRun = false): array
{
    $steps = [];
    $errors = [];
    $changedTotal = 0;

    try {
        bandpromo_asset_registry_ensure_migrated($root, true);
        bandpromo_release_ensure_seeded($root);
        bandpromo_playlist_ensure_seeded($root);
        bandpromo_gallery_ensure_seeded($root);
        bandpromo_theme_ensure_seeded($root);
        bandpromo_page_seed_all_if_missing($root);
        if ($dryRun) {
            $pending = bandpromo_list_uncatalogued_audio_originals($root);
            $changedTotal += count($pending);
            if ($pending !== []) {
                $steps[] = bandpromo_content_autofix_step_result('auto_register_audio', 'Register uncatalogued audio uploads', [
                    'changed' => count($pending),
                    'items' => array_map(static fn(array $item): string => (string) ($item['filename'] ?? ''), $pending),
                ]);
            }
        } else {
            $reconcile = bandpromo_reconcile_uncatalogued_audio_originals($root);
            if (!empty($reconcile['changed'])) {
                $changedTotal += (int) $reconcile['changed'];
                $steps[] = bandpromo_content_autofix_step_result('auto_register_audio', 'Register uncatalogued audio uploads', [
                    'changed' => (int) $reconcile['changed'],
                    'items' => $reconcile['fixed'],
                ]);
            }
            if (!empty($reconcile['failed'])) {
                foreach ($reconcile['failed'] as $failure) {
                    if (!is_array($failure)) {
                        continue;
                    }
                    $errors[] = (string) ($failure['filename'] ?? 'audio') . ': ' . (string) ($failure['error'] ?? 'Could not register automatically');
                }
            }
        }
        $steps[] = bandpromo_content_autofix_step_result('seed_containers', 'Seed platform containers', [
            'changed' => 1,
            'items' => ['assets', 'releases', 'playlists', 'galleries', 'themes', 'pages'],
        ]);
    } catch (Throwable $throwable) {
        $errors[] = $throwable->getMessage();
    }

    $pipeline = [
        'bandpromo_content_autofix_materialize_audio_masters',
        'bandpromo_content_autofix_canonicalize_master_filenames',
        'bandpromo_content_autofix_materialize_visual_masters',
        'bandpromo_content_autofix_heal_visual_display',
        'bandpromo_content_autofix_normalize_playlist_kind',
        'bandpromo_content_autofix_sync_playlist_entries',
        'bandpromo_content_autofix_sync_releases',
        'bandpromo_content_autofix_sync_audio_display',
        'bandpromo_content_autofix_sync_brand_asset_ids',
        'bandpromo_content_autofix_sync_gallery_asset_ids',
        'bandpromo_content_autofix_sync_page_asset_ids',
        'bandpromo_content_autofix_sync_audio_visual_refs',
        'bandpromo_content_autofix_sync_config_scope',
        'bandpromo_content_autofix_refresh_validation',
    ];

    foreach ($pipeline as $callable) {
        try {
            $step = $callable($root, $dryRun);
            $steps[] = $step;
            $changedTotal += (int) ($step['changed'] ?? 0);
            if (!empty($step['errors'])) {
                foreach ($step['errors'] as $error) {
                    $errors[] = (string) $error;
                }
            }
        } catch (Throwable $throwable) {
            $errors[] = $throwable->getMessage();
        }
    }

    $recommendBuild = !$dryRun && $changedTotal > 0;
    if ($recommendBuild) {
        bandpromo_mark_build_required('content_autofix');
    }

    return [
        'ok' => true,
        'dry_run' => $dryRun,
        'changed_total' => $changedTotal,
        'recommend_build' => $recommendBuild,
        'steps' => $steps,
        'errors' => $errors,
        'has_warnings' => $errors !== [],
        'message' => $dryRun
            ? 'Preview complete. Apply repairs only if you intend to change catalog or container links.'
            : ($changedTotal > 0
                ? 'Catalog repair finished. bandPromo will refresh delivery files automatically when needed.'
                : 'Catalog already matches the current registry and container links.'),
    ];
}
