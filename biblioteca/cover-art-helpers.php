<?php

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/playlist-storage.php';

function bandpromo_cover_art_configured_basename(): string
{
    return 'configured_release_cover';
}

function bandpromo_cover_art_is_configured_release_cover(string $filename): bool
{
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    return $stem === bandpromo_cover_art_configured_basename();
}

function bandpromo_cover_art_normalize_img_basename(?string $path): string
{
    $value = trim((string) $path);
    if ($value === '') {
        return '';
    }

    $value = str_replace('\\', '/', $value);
    if (strpos($value, '://') !== false) {
        $parsed = parse_url($value, PHP_URL_PATH);
        $value = is_string($parsed) ? $parsed : '';
    }

    $value = ltrim($value, '/');
    if (preg_match('#^media/img/original/(.+)$#i', $value, $matches) === 1) {
        return basename($matches[1]);
    }

    return basename($value);
}

function bandpromo_cover_art_img_path_basename_exists(string $root, string $basename): bool
{
    if ($basename === '') {
        return false;
    }

    return is_file($root . '/media/img/original/' . $basename);
}

function bandpromo_cover_art_collect_audio_stems(string $root): array
{
    require_once __DIR__ . '/media-library-state.php';

    $stems = [];
    bandpromo_media_files_index_ensure_target($root, 'audio');
    foreach (bandpromo_media_files_index_list($root, 'audio') as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $filename = (string) ($entry['name'] ?? '');
        if ($filename === '' || strcasecmp($filename, 'desktop.ini') === 0) {
            continue;
        }
        $stems[pathinfo($filename, PATHINFO_FILENAME)] = $filename;
    }

    return $stems;
}

function bandpromo_cover_art_load_playlist_context(string $root, ?array $trackVisualIndex = null): array
{
    $context = [
        'cover_refs' => [],
        'cover_sources' => [],
        'audio_stems' => bandpromo_cover_art_collect_audio_stems($root),
        'configured_in_use' => false,
    ];

    require_once __DIR__ . '/media-reference-helpers.php';
    $trackVisualIndex = is_array($trackVisualIndex)
        ? $trackVisualIndex
        : bandpromo_media_reference_build_track_visual_index($root);

    // Live registry assignments (updated immediately on track-editor save).
    foreach ($trackVisualIndex['covers'] ?? [] as $coverBasename => $refs) {
        if (!is_array($refs) || $coverBasename === '') {
            continue;
        }
        if (!isset($context['cover_refs'][$coverBasename])) {
            $context['cover_refs'][$coverBasename] = [];
        }
        foreach ($refs as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            $context['cover_refs'][$coverBasename][] = $reference;
        }
    }

    $validation_map = bandpromo_playlist_cover_source_validation_map($root);

    foreach (bandpromo_playlist_merged_built_track_map($root) as $audioFile => $track) {
        if (!is_array($track)) {
            continue;
        }

        $label = trim((string) ($track['title'] ?? $audioFile));
        $coverBasename = bandpromo_cover_art_normalize_img_basename((string) ($track['cover'] ?? ''));
        if ($coverBasename === '') {
            continue;
        }

        if (!isset($context['cover_refs'][$coverBasename])) {
            $context['cover_refs'][$coverBasename] = [];
        }

        // Avoid duplicate playlist refs when registry already lists the same master/cover.
        $alreadyListed = false;
        foreach ($context['cover_refs'][$coverBasename] as $existing) {
            if (
                ($existing['kind'] ?? '') === 'track-cover'
                && ($existing['audio_file'] ?? '') === $audioFile
            ) {
                $alreadyListed = true;
                break;
            }
        }
        if (!$alreadyListed) {
            $context['cover_refs'][$coverBasename][] = [
                'scope' => 'playlist',
                'kind' => 'playlist-cover',
                'label' => $label !== '' ? $label : $coverBasename,
                'audio_file' => $audioFile,
            ];
        }

        $coverSource = $validation_map[$audioFile] ?? '';
        if ($coverSource !== '') {
            $context['cover_sources'][$coverBasename][$coverSource] = true;
        }
        if ($coverSource === 'configured') {
            $context['configured_in_use'] = true;
        }
    }

    return $context;
}

function bandpromo_cover_art_collect_config_references(string $root): array
{
    $references = [];
    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    if ($config === []) {
        return $references;
    }

    $paths = [
        ['path' => 'release.theme.cover', 'legacy' => ['media.cover'], 'kind' => 'theme-cover', 'label' => 'Primary cover (theme)'],
        ['path' => 'release.theme.background_image', 'legacy' => ['media.background_image'], 'kind' => 'theme-background', 'label' => 'Background image (theme)'],
        ['path' => 'release.social.share_image', 'legacy' => ['social.share_image'], 'kind' => 'share-image', 'label' => 'Share image (social)'],
    ];

    foreach ($paths as $entry) {
        $raw = bandpromo_config_get_nonempty_value($config, $entry['path']);
        if (!is_string($raw) || trim($raw) === '') {
            foreach ($entry['legacy'] as $legacyPath) {
                $raw = bandpromo_config_get_nonempty_value($config, $legacyPath);
                if (is_string($raw) && trim($raw) !== '') {
                    break;
                }
            }
        }

        $basename = bandpromo_cover_art_normalize_img_basename(is_string($raw) ? $raw : '');
        if ($basename === '' || !bandpromo_cover_art_img_path_basename_exists($root, $basename)) {
            continue;
        }

        if (!isset($references[$basename])) {
            $references[$basename] = [];
        }

        $references[$basename][] = [
            'scope' => 'config',
            'kind' => $entry['kind'],
            'label' => $entry['label'],
        ];
    }

    return $references;
}

function bandpromo_cover_art_collect_gallery_references(string $root, string $filename): array
{
    require_once __DIR__ . '/gallery-storage.php';
    $references = [];

    try {
        bandpromo_gallery_ensure_seeded($root);
    } catch (Throwable $throwable) {
        return $references;
    }

    foreach (bandpromo_gallery_registry_entries($root) as $registryEntry) {
        $galleryId = (string) ($registryEntry['id'] ?? '');
        if ($galleryId === '') {
            continue;
        }

        try {
            $items = bandpromo_gallery_materialize_items($root, $galleryId);
        } catch (Throwable $throwable) {
            continue;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $src = str_replace('\\', '/', trim((string) ($item['src'] ?? '')));
            if ($src === '' || basename($src) !== $filename || stripos($src, '/media/img/') === false) {
                continue;
            }

            $references[] = [
                'scope' => 'gallery',
                'kind' => 'gallery-item',
                'label' => trim((string) ($item['name'] ?? $item['alt'] ?? $filename)) ?: $filename,
                'gallery_id' => $galleryId,
            ];
        }
    }

    return $references;
}

function bandpromo_cover_art_manifest_record(string $filename): array
{
    $state = bandpromo_media_library_load_state();
    $assets = is_array($state['assets'] ?? null) ? $state['assets'] : [];
    $key = bandpromo_media_library_key('illustrations', $filename);
    $record = is_array($assets[$key] ?? null) ? $assets[$key] : [];

    return [
        'role' => trim((string) ($record['role'] ?? '')),
        'origin' => trim((string) ($record['origin'] ?? '')),
        'linked_audio' => trim((string) ($record['linked_audio'] ?? '')),
        'linked_config' => trim((string) ($record['linked_config'] ?? '')),
        'recorded_at' => trim((string) ($record['recorded_at'] ?? '')),
    ];
}

function bandpromo_cover_art_infer_role(string $filename, array $playlistContext): string
{
    if (bandpromo_cover_art_is_configured_release_cover($filename)) {
        return 'release-fallback';
    }

    if (!empty($playlistContext['cover_refs'][$filename])) {
        return 'track-cover';
    }

    $stem = pathinfo($filename, PATHINFO_FILENAME);
    if ($stem !== '' && isset($playlistContext['audio_stems'][$stem])) {
        return 'track-cover';
    }

    return 'illustration';
}

function bandpromo_cover_art_infer_origin(string $filename, array $manifest, string $role): string
{
    if ($manifest['origin'] !== '') {
        return $manifest['origin'];
    }

    if (bandpromo_media_is_bundled_placeholder($filename)) {
        return 'bundled-placeholder';
    }

    if (bandpromo_cover_art_is_configured_release_cover($filename)) {
        return 'build-configured';
    }

    if ($role === 'track-cover') {
        return 'user-upload';
    }

    return 'user-upload';
}

function bandpromo_cover_art_collect_references(string $root, string $filename, ?array $trackVisualIndex = null): array
{
    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return [];
    }

    $playlistContext = bandpromo_cover_art_load_playlist_context($root, $trackVisualIndex);
    $references = [];

    if (!empty($playlistContext['cover_refs'][$safe])) {
        foreach ($playlistContext['cover_refs'][$safe] as $reference) {
            $references[] = $reference;
        }
    }

    foreach (bandpromo_cover_art_collect_gallery_references($root, $safe) as $reference) {
        $references[] = $reference;
    }

    $configRefs = bandpromo_cover_art_collect_config_references($root);
    if (!empty($configRefs[$safe])) {
        foreach ($configRefs[$safe] as $reference) {
            $references[] = $reference;
        }
    }

    if (
        bandpromo_cover_art_is_configured_release_cover($safe)
        && (
            !empty($playlistContext['configured_in_use'])
            || !empty($configRefs[$safe])
        )
    ) {
        $hasReleaseFallback = false;
        foreach ($references as $reference) {
            if (($reference['kind'] ?? '') === 'release-fallback') {
                $hasReleaseFallback = true;
                break;
            }
        }
        if (!$hasReleaseFallback) {
            $references[] = [
                'scope' => 'build',
                'kind' => 'release-fallback',
                'label' => 'Release cover fallback',
            ];
        }
    }

    return $references;
}

function bandpromo_cover_art_describe_file(
    string $root,
    string $filename,
    ?array $playlistContext = null,
    ?array $trackVisualIndex = null
): array {
    $safe = basename($filename);
    $playlistContext = is_array($playlistContext)
        ? $playlistContext
        : bandpromo_cover_art_load_playlist_context($root, $trackVisualIndex);
    $manifest = bandpromo_cover_art_manifest_record($safe);
    $role = $manifest['role'] !== '' ? $manifest['role'] : bandpromo_cover_art_infer_role($safe, $playlistContext);
    $origin = bandpromo_cover_art_infer_origin($safe, $manifest, $role);
    $references = bandpromo_cover_art_collect_references($root, $safe, $trackVisualIndex);
    $linkedAudio = $manifest['linked_audio'];

    if ($linkedAudio === '' && ($role === 'track-cover' || $references !== [])) {
        $stem = pathinfo($safe, PATHINFO_FILENAME);
        if ($stem !== '' && isset($playlistContext['audio_stems'][$stem])) {
            $linkedAudio = $playlistContext['audio_stems'][$stem];
        } elseif (!empty($references)) {
            foreach ($references as $reference) {
                $audioFile = trim((string) ($reference['audio_file'] ?? ''));
                if ($audioFile !== '') {
                    $linkedAudio = $audioFile;
                    break;
                }
            }
        }
    }

    if ($role === '' && $references !== []) {
        foreach ($references as $reference) {
            if (in_array(($reference['kind'] ?? ''), ['track-cover', 'playlist-cover'], true)) {
                $role = 'track-cover';
                break;
            }
        }
    }

    $orphan = $references === [] && !bandpromo_media_is_bundled_placeholder($safe);
    $regenerable = in_array($origin, ['build-extracted', 'build-configured', 'build-sidecar-copy'], true);
    $safeToDelete = $orphan || ($regenerable && $role !== 'release-fallback');

    return [
        'filename' => $safe,
        'role' => $role,
        'origin' => $origin,
        'linked_audio' => $linkedAudio,
        'linked_config' => $manifest['linked_config'],
        'references' => $references,
        'reference_count' => count($references),
        'orphan' => $orphan,
        'regenerable' => $regenerable,
        'safe_to_delete' => $safeToDelete,
        'recorded_at' => $manifest['recorded_at'],
    ];
}

function bandpromo_cover_art_record_upload(string $root, string $filename, string $uploadTarget = 'illustrations'): void
{
    $safe = basename($filename);
    if ($safe === '' || $uploadTarget !== 'illustrations') {
        return;
    }

    $playlistContext = bandpromo_cover_art_load_playlist_context($root);
    $role = bandpromo_cover_art_infer_role($safe, $playlistContext);
    $meta = [
        'role' => $role,
        'origin' => 'user-upload',
    ];

    if ($role === 'track-cover') {
        $stem = pathinfo($safe, PATHINFO_FILENAME);
        if ($stem !== '' && isset($playlistContext['audio_stems'][$stem])) {
            $meta['linked_audio'] = $playlistContext['audio_stems'][$stem];
        }
    }

    bandpromo_media_record_asset('illustrations', $safe, $meta);
}

function bandpromo_cover_art_record_build_asset(string $filename, string $role, string $origin, array $extra = []): void
{
    $safe = basename($filename);
    if ($safe === '') {
        return;
    }

    bandpromo_media_record_asset('illustrations', $safe, array_merge([
        'role' => $role,
        'origin' => $origin,
    ], $extra));
}

function bandpromo_cover_art_cleanup_stale_configured_release_covers(string $root, string $keepFilename = ''): array
{
    $imgDir = $root . '/media/img/original';
    $removed = [];
    if (!is_dir($imgDir)) {
        return ['removed' => $removed];
    }

    $keep = basename($keepFilename);
    $basename = bandpromo_cover_art_configured_basename();

    foreach (new DirectoryIterator($imgDir) as $entry) {
        if ($entry->isDot() || $entry->isDir()) {
            continue;
        }

        $filename = $entry->getFilename();
        if (strcasecmp($filename, 'desktop.ini') === 0) {
            continue;
        }

        if (pathinfo($filename, PATHINFO_FILENAME) !== $basename) {
            continue;
        }

        if ($keep !== '' && $filename === $keep) {
            continue;
        }

        if (bandpromo_cover_art_collect_references($root, $filename) !== []) {
            continue;
        }

        if (@unlink($imgDir . '/' . $filename)) {
            $removed[] = $filename;
        }
    }

    return ['removed' => $removed];
}

/**
 * Resolve a pool cover basename for an audio asset (display.cover or stem sidecar).
 */
function bandpromo_cover_art_resolve_linked_cover_basename(string $root, array $audioAsset): string
{
    $display = is_array($audioAsset['display'] ?? null) ? $audioAsset['display'] : [];
    $cover = basename(trim((string) ($display['cover'] ?? '')));
    if ($cover !== '' && bandpromo_cover_art_img_path_basename_exists($root, $cover)) {
        return $cover;
    }

    $master = basename(trim((string) ($audioAsset['master_filename'] ?? '')));
    $original = basename(trim((string) ($audioAsset['original_filename'] ?? '')));
    foreach ([$master, $original] as $audioName) {
        if ($audioName === '') {
            continue;
        }
        $stem = (string) pathinfo($audioName, PATHINFO_FILENAME);
        if ($stem === '') {
            continue;
        }
        foreach (['.jpg', '.jpeg', '.png', '.webp'] as $ext) {
            $candidate = $stem . $ext;
            if (bandpromo_cover_art_img_path_basename_exists($root, $candidate)) {
                return $candidate;
            }
        }
    }

    return '';
}

/**
 * Register Visual assets for embedded/sidecar covers linked to audio masters.
 * Makes extracted track art selectable in Visual pickers without Repair catalog.
 *
 * @param list<string> $audioFilenames Master or original audio basenames
 * @return array{covers: list<string>, asset_ids: list<string>, count: int}
 */
function bandpromo_register_extracted_covers_for_audio_files(string $root, array $audioFilenames): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/media-library-state.php';

    $covers = [];
    $assetIds = [];

    foreach ($audioFilenames as $filename) {
        $filename = basename(trim((string) $filename));
        if ($filename === '') {
            continue;
        }

        $audio = bandpromo_asset_lookup_by_master_filename($root, $filename)
            ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
        if (!is_array($audio) || ($audio['kind'] ?? '') !== 'audio') {
            continue;
        }

        $cover = bandpromo_cover_art_resolve_linked_cover_basename($root, $audio);
        if ($cover === '') {
            continue;
        }

        // Keep display.cover pointing at the pool original when we discovered a sidecar.
        $display = is_array($audio['display'] ?? null) ? $audio['display'] : [];
        if (basename(trim((string) ($display['cover'] ?? ''))) !== $cover) {
            $display['cover'] = $cover;
            bandpromo_asset_update_entry($root, (string) ($audio['id'] ?? ''), [
                'display' => $display,
            ]);
        }

        try {
            $visual = bandpromo_asset_register_visual($root, $cover, 'img', 'image', [
                'role' => 'track-cover',
            ]);
        } catch (Throwable $throwable) {
            continue;
        }

        if (!is_array($visual)) {
            continue;
        }

        $visualId = trim((string) ($visual['id'] ?? ''));
        $releaseId = trim((string) ($audio['release_id'] ?? ''));
        $changes = [];
        if (($visual['role'] ?? '') === 'unassigned') {
            $changes['role'] = 'track-cover';
        }
        if ($releaseId !== '' && trim((string) ($visual['release_id'] ?? '')) === '') {
            $changes['release_id'] = $releaseId;
        }
        if ($changes !== [] && $visualId !== '') {
            $updated = bandpromo_asset_update_entry($root, $visualId, $changes);
            if (is_array($updated)) {
                $visual = $updated;
            }
        }

        bandpromo_media_files_index_sync_file($root, 'illustrations', $cover);
        $covers[$cover] = true;
        if ($visualId !== '') {
            $assetIds[$visualId] = true;
        }
    }

    $coverList = array_keys($covers);
    if ($coverList !== []) {
        bandpromo_media_files_index_rebuild_target($root, 'illustrations');
    }

    return [
        'covers' => $coverList,
        'asset_ids' => array_keys($assetIds),
        'count' => count($coverList),
    ];
}
