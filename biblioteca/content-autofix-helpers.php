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
        $step['changed'] = $staleCount;
        $step['items'][] = ['stale_catalog_links' => $staleCount];
        return $step;
    }

    bandpromo_release_sync_demo_audio_assets($root);
    $repaired = bandpromo_release_repair_catalog_release_ids($root);
    $step['changed'] = $repaired > 0 ? $repaired : 0;
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

function bandpromo_content_autofix_run(string $root, bool $dryRun = false): array
{
    $steps = [];
    $errors = [];
    $changedTotal = 0;

    try {
        bandpromo_asset_registry_ensure_migrated($root);
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
        'bandpromo_content_autofix_normalize_playlist_kind',
        'bandpromo_content_autofix_sync_playlist_entries',
        'bandpromo_content_autofix_sync_releases',
        'bandpromo_content_autofix_sync_audio_display',
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
