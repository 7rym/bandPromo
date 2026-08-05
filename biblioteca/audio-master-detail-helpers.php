<?php

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/cover-art-helpers.php';
require_once __DIR__ . '/living-cover-helpers.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/audio-master-helpers.php';

function bandpromo_audio_master_canonical_filename(string $root, string $filename): string
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    if ($asset !== null) {
        $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($masterFilename !== '') {
            return $masterFilename;
        }
    }

    $master = bandpromo_find_audio_master($root, $filename);
    if (!empty($master['exists'])) {
        $masterFilename = basename(trim((string) ($master['filename'] ?? '')));
        if ($masterFilename !== '') {
            return $masterFilename;
        }
    }

    return $filename;
}

function bandpromo_audio_master_sidecar_stems(string $root, string $filename): array
{
    $canonical = bandpromo_audio_master_canonical_filename($root, $filename);
    $stems = [];
    foreach ([$canonical, basename(trim($filename))] as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $stem = pathinfo($candidate, PATHINFO_FILENAME);
        if ($stem !== '' && !in_array($stem, $stems, true)) {
            $stems[] = $stem;
        }
    }

    return $stems;
}

function bandpromo_audio_master_playlist_map(string $root): array
{
    return bandpromo_playlist_merged_built_track_map($root);
}

function bandpromo_audio_master_resolve_current_cover_url(?string $cover): string
{
    $filename = basename(trim((string) $cover));
    if ($filename === '') {
        return '';
    }

    return '/media/img/original/' . rawurlencode($filename);
}

/**
 * Absolute path for a track-cover pool file (img / photo / special).
 */
function bandpromo_audio_master_resolve_pool_cover_path(string $root, string $coverFilename): string
{
    require_once __DIR__ . '/asset-registry.php';

    $ref = trim($coverFilename);
    if ($ref === '') {
        return '';
    }

    if (bandpromo_asset_is_asset_id($ref)) {
        $visual = bandpromo_asset_lookup_by_id($root, $ref);
        if (is_array($visual) && ($visual['kind'] ?? '') === 'visual') {
            $path = bandpromo_asset_visual_original_path($root, $visual);
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }
    }

    $coverFilename = basename($ref);
    if ($coverFilename === '') {
        return '';
    }

    $visual = bandpromo_asset_lookup_by_original_filename($root, $coverFilename);
    if (is_array($visual) && ($visual['kind'] ?? '') === 'visual') {
        $path = bandpromo_asset_visual_original_path($root, $visual);
        if ($path !== '' && is_file($path)) {
            return $path;
        }
    }

    foreach ([
        $root . '/media/img/original/' . $coverFilename,
        $root . '/media/photo/original/' . $coverFilename,
        $root . '/media/special/' . $coverFilename,
    ] as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Public URL for a track-cover pool file (prefers delivery card variant when ready).
 */
function bandpromo_audio_master_resolve_pool_cover_url(string $root, ?string $cover): string
{
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/asset-registry.php';

    $ref = trim((string) $cover);
    if ($ref === '') {
        return '';
    }

    $visual = null;
    if (bandpromo_asset_is_asset_id($ref)) {
        $visual = bandpromo_asset_lookup_by_id($root, $ref);
    }
    if ($visual === null) {
        $coverFilename = basename($ref);
        $visual = bandpromo_asset_lookup_by_original_filename($root, $coverFilename);
    }

    if (is_array($visual) && ($visual['kind'] ?? '') === 'visual' && !empty($visual['id'])) {
        $url = bandpromo_visual_resolve_url(
            $root,
            (string) $visual['id'],
            'card',
            (string) ($visual['intake_bucket'] ?? '')
        );
        if ($url !== '') {
            $path = bandpromo_audio_master_resolve_pool_cover_path(
                $root,
                basename((string) ($visual['original_filename'] ?? $ref))
            );
            $version = $path !== '' ? (string) filemtime($path) : (string) time();

            return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($version);
        }
    }

    $coverFilename = basename($ref);
    $path = bandpromo_audio_master_resolve_pool_cover_path($root, $coverFilename);
    if ($path === '') {
        return '';
    }

    $normalizedRoot = str_replace('\\', '/', rtrim($root, '/\\'));
    $normalizedPath = str_replace('\\', '/', $path);
    $rel = str_starts_with($normalizedPath, $normalizedRoot)
        ? substr($normalizedPath, strlen($normalizedRoot))
        : '';
    if ($rel === '') {
        return '';
    }
    if (!str_starts_with($rel, '/')) {
        $rel = '/' . $rel;
    }
    $version = (string) filemtime($path);

    return $rel . '?v=' . rawurlencode($version);
}

function bandpromo_audio_master_resolve_sidecar_cover_url(string $root, ?string $cover): string
{
    return bandpromo_audio_master_resolve_pool_cover_url($root, $cover);
}

function bandpromo_audio_files_listing_context(string $root): array
{
    static $cache = [];

    if (isset($cache[$root])) {
        return $cache[$root];
    }

    $playlistMap = bandpromo_audio_master_playlist_map($root);
    $poolMap = bandpromo_release_pool_map_canonical($root, $playlistMap);
    $releaseDates = [];
    foreach (bandpromo_release_admin_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = trim((string) ($entry['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $releaseDates[$id] = trim((string) ($entry['release_date'] ?? ''));
    }

    return $cache[$root] = [
        'pool' => $poolMap,
        'release_dates' => $releaseDates,
    ];
}

function bandpromo_audio_split_title_parts(string $value): array
{
    return bandpromo_release_split_audio_title_parts($value);
}

function bandpromo_audio_listing_title_parts(string $title, string $artist = '', string $album = ''): array
{
    $title = bandpromo_release_polish_track_title($title, $artist, $album);

    return bandpromo_release_split_audio_title_parts($title);
}

function bandpromo_audio_listing_title_looks_messy(string $title, string $filename): bool
{
    return bandpromo_release_track_title_looks_messy($title, $filename);
}

function bandpromo_audio_display_label_for_listing(
    string $root,
    string $filename,
    array $validation_map,
    array $context
): array {
    $filename = basename(trim($filename));
    $pool = is_array($context['pool'] ?? null) ? $context['pool'] : [];

    $row = is_array($pool[$filename] ?? null) ? $pool[$filename] : [];
    $artist = trim((string) ($row['artist'] ?? ''));
    $album = trim((string) ($row['album'] ?? ''));
    $title = trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($row['title'] ?? '')));

    if ($title === '' && isset($validation_map[$filename]['display_title'])) {
        $title = trim((string) $validation_map[$filename]['display_title']);
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    $display = bandpromo_asset_read_audio_display($asset);

    if ($display['artist'] !== '') {
        $artist = $display['artist'];
    } elseif ($title !== '') {
        $title = bandpromo_release_polish_track_title($title, $artist, $album);
    }

    if ($display['album'] !== '' && $album === '') {
        $album = $display['album'];
    }

    $duration = (int) ($row['duration'] ?? 0);
    if ($display['duration'] > 0) {
        $duration = $display['duration'];
    }

    $version = '';
    $cachedTitle = trim((string) ($display['title'] ?? ''));
    $useCachedDisplay = $cachedTitle !== ''
        && !bandpromo_release_title_needs_metadata_refresh($cachedTitle, $filename)
        && !bandpromo_release_title_looks_like_asset_id($cachedTitle, $filename);
    if ($useCachedDisplay) {
        $title = $cachedTitle;
        $version = $display['version'];
    } else {
        if ($title !== '') {
            $title = bandpromo_release_polish_track_title($title, $artist, $album);
        }
        if ($title === '' || bandpromo_release_title_looks_like_asset_id($title, $filename)) {
            if ($asset !== null) {
                $labels = bandpromo_release_track_display_from_asset($asset, $filename);
                if ($artist === '') {
                    $artist = trim((string) ($labels['artist'] ?? ''));
                }
                if ($title === '' || bandpromo_release_title_looks_like_asset_id($title, $filename)) {
                    $title = trim((string) ($labels['title'] ?? ''));
                }
                if ($duration <= 0) {
                    $duration = (int) ($labels['duration'] ?? 0);
                }
                if ($version === '' && trim((string) ($labels['version'] ?? '')) !== '') {
                    $version = trim((string) ($labels['version'] ?? ''));
                }
            }
        }

        $resolved = bandpromo_release_resolve_track_display_labels($title, $artist, $album);
        $title = $resolved['title'];
        if ($version === '') {
            $version = trim((string) ($resolved['version'] ?? ''));
        }
    }

    if ($title === '') {
        $title = 'Untitled';
    }

    $releaseMeta = bandpromo_release_audio_listing_meta($root, $filename);
    $releaseId = (string) ($releaseMeta['release_id'] ?? '');
    $releaseDate = trim((string) ($releaseMeta['release_date'] ?? ''));
    $trackNumber = (int) bandpromo_release_find_track_number_for_master($root, $filename);

    return [
        'display_title' => $title,
        'display_version' => $version,
        'display_artist' => $artist,
        'display_subtitle' => '',
        'display_duration' => max(0, $duration),
        'release_id' => $releaseId,
        'release_title' => trim((string) ($releaseMeta['release_title'] ?? '')),
        'release_date' => $releaseDate,
        'release_orphan' => !empty($releaseMeta['release_orphan']),
        'on_release' => !empty($releaseMeta['on_release']),
        'track_number' => max(0, $trackNumber),
    ];
}

function bandpromo_audio_files_listing_sort(array $left, array $right): int
{
    $leftDate = trim((string) ($left['release_date'] ?? ''));
    $rightDate = trim((string) ($right['release_date'] ?? ''));

    if ($leftDate !== '' && $rightDate !== '') {
        $dateCompare = strcmp($rightDate, $leftDate);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }
    } elseif ($leftDate !== '' || $rightDate !== '') {
        return $leftDate !== '' ? -1 : 1;
    }

    $leftTrack = (int) ($left['track_number'] ?? 0);
    $rightTrack = (int) ($right['track_number'] ?? 0);
    if ($leftTrack > 0 || $rightTrack > 0) {
        $leftTrackSort = $leftTrack > 0 ? $leftTrack : PHP_INT_MAX;
        $rightTrackSort = $rightTrack > 0 ? $rightTrack : PHP_INT_MAX;
        if ($leftTrackSort !== $rightTrackSort) {
            return $leftTrackSort <=> $rightTrackSort;
        }
    }

    $titleCompare = strcasecmp(
        (string) ($left['display_title'] ?? ''),
        (string) ($right['display_title'] ?? '')
    );
    if ($titleCompare !== 0) {
        return $titleCompare;
    }

    return strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
}

function bandpromo_audio_master_enrich_detail(string $root, string $filename, array $detail): array
{
    $masterFilename = bandpromo_audio_master_canonical_filename($root, $filename);
    $playlistMap = bandpromo_audio_master_playlist_map($root);
    $playlistEntry = is_array($playlistMap[$masterFilename] ?? null)
        ? $playlistMap[$masterFilename]
        : (is_array($playlistMap[$filename] ?? null) ? $playlistMap[$filename] : []);
    $sidecarCover = trim((string) ($detail['sidecar_cover'] ?? ''));
    if ($sidecarCover === '' && $masterFilename !== '') {
        foreach (bandpromo_audio_master_sidecar_stems($root, $filename) as $stem) {
            foreach (['jpg', 'jpeg', 'png'] as $extension) {
                $candidate = $stem . '.' . $extension;
                if (is_file($root . '/media/img/original/' . $candidate)) {
                    $sidecarCover = $candidate;
                    break 2;
                }
            }
        }
    }
    $currentCover = $sidecarCover !== ''
        ? $sidecarCover
        : trim((string) ($playlistEntry['cover'] ?? ''));
    $releaseTracknumber = bandpromo_release_find_track_number_for_master($root, $filename);
    $embeddedTracknumber = trim((string) ($detail['tracknumber'] ?? ''));

    $detail['release_tracknumber'] = $releaseTracknumber;
    $detail['suggested_tracknumber'] = $embeddedTracknumber !== ''
        ? $embeddedTracknumber
        : $releaseTracknumber;
    $detail['release_locked'] = bandpromo_release_is_master_locked($root, $filename);
    $detail['master_filename'] = $masterFilename;
    $detail['sidecar_cover'] = $sidecarCover;
    $detail['current_cover'] = $currentCover;
    $detail['current_cover_url'] = bandpromo_audio_master_resolve_pool_cover_url($root, $currentCover);
    $detail['sidecar_cover_url'] = bandpromo_audio_master_resolve_pool_cover_url($root, $sidecarCover);

    return bandpromo_living_cover_enrich_detail($root, $detail);
}

/**
 * Build track detail from the asset registry + stored playlist/cover refs.
 * Read path for admin GET — does not spawn Python or parse master tags.
 */
function bandpromo_audio_master_detail_from_registry(string $root, string $filename): array
{
    $filename = basename(trim($filename));
    if ($filename === '') {
        throw new InvalidArgumentException('Invalid filename');
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_original_filename($root, $filename);
    if ($asset === null || ($asset['kind'] ?? '') !== 'audio') {
        throw new RuntimeException('Audio asset not found in registry: ' . $filename);
    }

    $masterFilename = basename(trim((string) ($asset['master_filename'] ?? $filename)));
    $display = bandpromo_asset_read_audio_display($asset);
    $playlistMap = bandpromo_audio_master_playlist_map($root);
    $playlistEntry = is_array($playlistMap[$masterFilename] ?? null)
        ? $playlistMap[$masterFilename]
        : [];

    $title = trim((string) ($display['title'] ?? ''));
    $version = trim((string) ($display['version'] ?? ''));
    // Keep title and version separate for the track editor. (Older builds mashed
    // version into title with a newline, which filled Title and left Version empty.)
    // Never present the ULID master filename as the editable title — use original stem.
    if ($title === '' || bandpromo_release_title_looks_like_asset_id($title, $masterFilename)) {
        $labels = bandpromo_release_track_display_from_asset($asset, $masterFilename);
        $title = trim((string) ($labels['title'] ?? ''));
        if ($version === '' && trim((string) ($labels['version'] ?? '')) !== '') {
            $version = trim((string) ($labels['version'] ?? ''));
        }
        if (trim((string) ($display['artist'] ?? '')) === '' && trim((string) ($labels['artist'] ?? '')) !== '') {
            $display['artist'] = trim((string) ($labels['artist'] ?? ''));
        }
    }
    if ($title === '') {
        $title = trim((string) ($playlistEntry['title'] ?? ''));
    }
    if ($title === '' || bandpromo_release_title_looks_like_asset_id($title, $masterFilename)) {
        $originalFile = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($originalFile !== '') {
            $title = ucwords(str_replace(['_', '-'], ' ', pathinfo($originalFile, PATHINFO_FILENAME)));
        }
    }
    if ($title === '' || bandpromo_release_title_looks_like_asset_id($title, $masterFilename)) {
        $title = 'Untitled';
    }
    if ($version === '' && $title !== '') {
        $parts = bandpromo_release_split_audio_title_parts($title);
        $title = trim((string) ($parts['title'] ?? $title));
        $version = trim((string) ($parts['version'] ?? ''));
    }

    $masterPath = $root . '/media/audio/master/' . $masterFilename;
    $masterExists = is_file($masterPath);

    $detail = [
        'ok' => true,
        'filename' => $filename,
        'master_filename' => $masterFilename,
        'original_filename' => basename(trim((string) ($asset['original_filename'] ?? ''))),
        'format' => (string) ($asset['master_format'] ?? pathinfo($masterFilename, PATHINFO_EXTENSION)),
        'title' => $title,
        'version' => $version,
        'artist' => trim((string) ($display['artist'] !== '' ? $display['artist'] : ($playlistEntry['artist'] ?? ''))),
        'album' => trim((string) ($display['album'] !== '' ? $display['album'] : ($playlistEntry['album'] ?? ''))),
        'date' => trim((string) ($display['date'] ?? '')),
        'tracknumber' => trim((string) ($display['tracknumber'] ?? '')),
        'bpm' => trim((string) ($display['bpm'] ?? '')),
        'initialkey' => trim((string) ($display['initialkey'] ?? '')),
        'genre' => trim((string) ($display['genre'] ?? '')),
        'comment' => trim((string) ($display['comment'] !== '' ? $display['comment'] : ($playlistEntry['description'] ?? ''))),
        'lyrics' => (string) ($display['lyrics'] !== '' ? $display['lyrics'] : ($playlistEntry['lyrics'] ?? '')),
        'text_role' => (string) ($display['text_role'] ?? 'lyrics'),
        'notes_label' => (string) ($display['notes_label'] ?? ''),
        'duration_seconds' => max(0, (int) ($display['duration'] > 0 ? $display['duration'] : ($playlistEntry['duration'] ?? 0))),
        'bitrate_kbps' => 0,
        'sample_rate_hz' => 0,
        'bit_depth' => 0,
        'file_size_bytes' => $masterExists ? (int) filesize($masterPath) : 0,
        'sidecar_cover' => trim((string) ($display['cover'] ?? '')),
        'living_cover' => trim((string) (
            $display['living_cover'] !== ''
                ? $display['living_cover']
                : ($playlistEntry['living_cover'] ?? '')
        )),
        'source' => 'asset-registry',
    ];

    return bandpromo_audio_master_enrich_detail($root, $filename, $detail);
}

function bandpromo_audio_master_validate_release_date(string $value): bool
{
    if ($value === '') {
        return true;
    }

    if (preg_match('/^\d{4}$/', $value)) {
        return true;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function bandpromo_audio_master_clear_sidecar_cover(string $root, string $audioFilename): void
{
    // Only remove legacy stem-named copies next to the audio identity.
    // Never delete operator Visual pool uploads referenced by display.cover.
    $imgDir = $root . '/media/img/original';

    foreach (bandpromo_audio_master_sidecar_stems($root, $audioFilename) as $stem) {
        foreach (['jpg', 'jpeg', 'png'] as $extension) {
            $candidate = $imgDir . '/' . $stem . '.' . $extension;
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }
}

/**
 * Remove legacy stem-named cover copies when the audio asset already points at a pool file.
 *
 * @return list<string> Deleted basenames
 */
function bandpromo_audio_master_prune_redundant_cover_sidecars(string $root): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/cover-art-helpers.php';

    $deleted = [];
    $registry = bandpromo_asset_load_registry($root);
    $imgDir = $root . '/media/img/original';

    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'audio') {
            continue;
        }

        $display = is_array($asset['display'] ?? null) ? $asset['display'] : [];
        $assignedCover = basename(trim((string) ($display['cover'] ?? '')));
        if ($assignedCover === '') {
            continue;
        }

        $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
        $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
        $stems = [];
        if ($masterFilename !== '') {
            $stems[] = pathinfo($masterFilename, PATHINFO_FILENAME);
        }
        if ($originalFilename !== '') {
            $stems[] = pathinfo($originalFilename, PATHINFO_FILENAME);
        }
        $stems = array_values(array_unique(array_filter($stems)));

        foreach ($stems as $stem) {
            foreach (['jpg', 'jpeg', 'png'] as $extension) {
                $candidateName = $stem . '.' . $extension;
                if (strcasecmp($candidateName, $assignedCover) === 0) {
                    continue;
                }
                $candidatePath = $imgDir . '/' . $candidateName;
                if (!is_file($candidatePath)) {
                    continue;
                }
                if (@unlink($candidatePath)) {
                    $deleted[] = $candidateName;
                    bandpromo_media_files_index_remove('illustrations', $candidateName);
                }
            }
        }
    }

    return $deleted;
}

function bandpromo_audio_master_apply_cover_selection(string $root, string $audioFilename, string $coverPath): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/cover-art-helpers.php';
    require_once __DIR__ . '/media-library-state.php';

    $masterFilename = bandpromo_audio_master_canonical_filename($root, $audioFilename);
    if ($masterFilename === '') {
        return [
            'ok' => false,
            'error' => 'Could not resolve audio master file',
        ];
    }

    $relativePath = ltrim(trim($coverPath), '/\\');
    if ($relativePath === '') {
        // Drop legacy stem copies + clear embedded art; registry display.cover cleared by save sync.
        bandpromo_audio_master_clear_sidecar_cover($root, $masterFilename);
        bandpromo_audio_master_sync_embedded_cover($root, $masterFilename, '');

        return [
            'ok' => true,
            'sidecar_cover' => '',
            'master_filename' => $masterFilename,
        ];
    }

    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $relativePath)), 'strlen'));
    if (count($parts) < 3 || strtolower($parts[0]) !== 'media') {
        return [
            'ok' => false,
            'error' => 'Invalid cover path',
        ];
    }

    $targetKey = '';
    $filename = '';
    $firstDir = strtolower($parts[1] ?? '');
    $secondDir = strtolower($parts[2] ?? '');

    if ($firstDir === 'img' && $secondDir === 'original' && count($parts) >= 4) {
        $targetKey = 'illustrations';
        $filename = basename($parts[3]);
    } elseif ($firstDir === 'photo' && $secondDir === 'original' && count($parts) >= 4) {
        $targetKey = 'photos';
        $filename = basename($parts[3]);
    } elseif ($firstDir === 'special' && count($parts) >= 3) {
        $targetKey = 'special';
        $filename = basename($parts[2]);
    }

    if ($targetKey === '') {
        return [
            'ok' => false,
            'error' => 'Choose an illustration, photo, or theme image for the track cover',
        ];
    }

    if ($filename === '' || strpbrk($filename, '/\\') !== false) {
        return [
            'ok' => false,
            'error' => 'Invalid cover filename',
        ];
    }

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return [
            'ok' => false,
            'error' => 'Track covers must be JPG, PNG, or WebP',
        ];
    }

    $sourceDir = bandpromo_media_target_dir($targetKey);
    if ($sourceDir === null) {
        return [
            'ok' => false,
            'error' => 'Unsupported cover source',
        ];
    }

    $sourcePath = $sourceDir . '/' . $filename;
    if (!is_file($sourcePath)) {
        return [
            'ok' => false,
            'error' => 'Selected cover file was not found',
        ];
    }

    // Remove legacy stem-named duplicates from the old copy-on-assign path.
    bandpromo_audio_master_clear_sidecar_cover($root, $masterFilename);

    $embeddedSync = bandpromo_audio_master_sync_embedded_cover($root, $masterFilename, $sourcePath);
    if (!($embeddedSync['ok'] ?? false)) {
        return [
            'ok' => false,
            'error' => (string) ($embeddedSync['error'] ?? 'Could not update embedded track cover'),
        ];
    }

    // Pool file stays the source of truth — reference it; do not copy to {audio_stem}.ext.
    bandpromo_cover_art_record_upload($root, $filename, $targetKey);
    bandpromo_cover_art_record_build_asset($filename, 'track-cover', 'operator-assigned', [
        'linked_audio' => $masterFilename,
    ]);

    $intakeBucket = bandpromo_asset_intake_bucket_for_files_index_target($targetKey);
    $coverAssetId = '';
    if ($intakeBucket !== '') {
        try {
            $visual = bandpromo_asset_register_visual($root, $filename, $intakeBucket, 'image', [
                'role' => 'track-cover',
            ]);
            if (is_array($visual)) {
                $coverAssetId = (string) ($visual['id'] ?? '');
                if (($visual['role'] ?? '') === 'unassigned') {
                    bandpromo_asset_update_entry($root, $coverAssetId, [
                        'role' => 'track-cover',
                    ]);
                }
            }
        } catch (Throwable $throwable) {
            // Registry optional for assign success; embed + display.cover still work.
        }
    }

    return [
        'ok' => true,
        'sidecar_cover' => $filename,
        'master_filename' => $masterFilename,
        'cover_asset_id' => $coverAssetId,
    ];
}

function bandpromo_audio_master_sync_embedded_cover(string $root, string $masterFilename, string $imagePath): array
{
    require_once __DIR__ . '/light-build-tasks.php';

    $masterFilename = bandpromo_audio_master_canonical_filename($root, $masterFilename);
    if ($masterFilename === '') {
        return [
            'ok' => false,
            'error' => 'Could not resolve audio master file',
        ];
    }

    $payload = [
        'action' => 'sync_cover',
        'filename' => $masterFilename,
        'image_path' => trim($imagePath),
    ];
    $result = bandpromo_run_light_json_task('scripts/audioMasterMetadata.py', $payload);
    $data = is_array($result['data'] ?? null) ? $result['data'] : null;
    if (!$result['ok'] || !is_array($data) || empty($data['ok'])) {
        $error = is_array($data) ? (string) ($data['error'] ?? '') : '';
        $output = trim((string) ($result['output'] ?? ''));

        return [
            'ok' => false,
            'error' => $error !== '' ? $error : ($output !== '' ? $output : 'Could not update embedded track cover'),
        ];
    }

    return ['ok' => true];
}

function bandpromo_audio_master_clear_cover_reference(string $root, string $coverBasename): array
{
    $coverBasename = basename(trim($coverBasename));
    $summary = ['covers_cleared' => 0];

    if ($coverBasename === '') {
        return $summary;
    }

    foreach (bandpromo_playlist_merged_built_track_map($root) as $audioFile => $track) {
        if (!is_array($track)) {
            continue;
        }

        $trackCover = basename(trim((string) ($track['cover'] ?? '')));
        if ($trackCover === '' || $trackCover !== $coverBasename) {
            continue;
        }

        $result = bandpromo_audio_master_apply_cover_selection($root, $audioFile, '');
        if (!empty($result['ok'])) {
            $summary['covers_cleared']++;
        }
    }

    return $summary;
}