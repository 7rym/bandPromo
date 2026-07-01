<?php

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/cover-art-helpers.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/playlist-storage.php';

function bandpromo_audio_master_playlist_map(string $root): array
{
    static $cache = [];

    if (isset($cache[$root])) {
        return $cache[$root];
    }

    $map = [];
    try {
        foreach (bandpromo_playlist_registry_entries($root) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
            if ($playlistId === '') {
                continue;
            }
            try {
                $document = bandpromo_playlist_load_document($root, $playlistId);
            } catch (Throwable $throwable) {
                continue;
            }
            foreach (bandpromo_playlist_build_track_list($root, $document) as $track) {
                if (!is_array($track)) {
                    continue;
                }
                $file = trim((string) ($track['file'] ?? ''));
                if ($file !== '') {
                    $map[$file] = $track;
                }
            }
        }
    } catch (Throwable $throwable) {
        // Fall through to built playlist artifact.
    }

    if ($map !== []) {
        return $cache[$root] = $map;
    }

    $playlistFile = $root . '/play/playlist.json';
    if (!is_file($playlistFile)) {
        return $cache[$root] = [];
    }

    $raw = file_get_contents($playlistFile);
    if ($raw === false) {
        return $cache[$root] = [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $cache[$root] = [];
    }

    $map = [];
    foreach (array_values($decoded) as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $file = trim((string) ($entry['file'] ?? ''));
        if ($file === '') {
            continue;
        }
        $map[$file] = $entry;
    }

    $cache[$root] = $map;
    return $map;
}

function bandpromo_audio_master_resolve_current_cover_url(?string $cover): string
{
    $filename = trim((string) $cover);
    if ($filename === '') {
        return '';
    }

    return '/media/img/original/' . rawurlencode($filename);
}

function bandpromo_audio_master_resolve_sidecar_cover_url(string $root, ?string $cover): string
{
    $filename = trim((string) $cover);
    if ($filename === '') {
        return '';
    }

    $path = $root . '/media/img/original/' . $filename;
    $version = is_file($path) ? (string) filemtime($path) : (string) time();

    return '/media/img/original/' . rawurlencode($filename) . '?v=' . rawurlencode($version);
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
    if ($display['title'] !== '') {
        $title = $display['title'];
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
    $playlistMap = bandpromo_audio_master_playlist_map($root);
    $playlistEntry = is_array($playlistMap[$filename] ?? null) ? $playlistMap[$filename] : [];
    $currentCover = trim((string) ($playlistEntry['cover'] ?? ''));
    $releaseTracknumber = bandpromo_release_find_track_number_for_master($root, $filename);
    $embeddedTracknumber = trim((string) ($detail['tracknumber'] ?? ''));

    $detail['release_tracknumber'] = $releaseTracknumber;
    $detail['suggested_tracknumber'] = $embeddedTracknumber !== ''
        ? $embeddedTracknumber
        : $releaseTracknumber;
    $detail['release_locked'] = bandpromo_release_is_master_locked($root, $filename);
    $detail['current_cover'] = $currentCover;
    $detail['current_cover_url'] = bandpromo_audio_master_resolve_current_cover_url($currentCover);
    $detail['sidecar_cover_url'] = bandpromo_audio_master_resolve_sidecar_cover_url($root, $detail['sidecar_cover'] ?? '');

    return $detail;
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
    $stem = pathinfo($audioFilename, PATHINFO_FILENAME);
    $imgDir = $root . '/media/img/original';

    foreach (['jpg', 'jpeg', 'png'] as $extension) {
        $candidate = $imgDir . '/' . $stem . '.' . $extension;
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    }
}

function bandpromo_audio_master_apply_cover_selection(string $root, string $audioFilename, string $coverPath): array
{
    $relativePath = ltrim(trim($coverPath), '/\\');
    if ($relativePath === '') {
        bandpromo_audio_master_clear_sidecar_cover($root, $audioFilename);
        return [
            'ok' => true,
            'sidecar_cover' => '',
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
    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        return [
            'ok' => false,
            'error' => 'Track covers must be JPG or PNG',
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

    $targetDir = $root . '/media/img/original';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        return [
            'ok' => false,
            'error' => 'Could not prepare track cover directory',
        ];
    }

    bandpromo_audio_master_clear_sidecar_cover($root, $audioFilename);

    $stem = pathinfo($audioFilename, PATHINFO_FILENAME);
    $targetFilename = $stem . '.' . $extension;
    $targetPath = $targetDir . '/' . $targetFilename;
    if (!copy($sourcePath, $targetPath)) {
        return [
            'ok' => false,
            'error' => 'Could not save the selected cover image',
        ];
    }

    bandpromo_cover_art_record_build_asset($targetFilename, 'track-cover', 'build-sidecar-copy', [
        'linked_audio' => $audioFilename,
    ]);

    return [
        'ok' => true,
        'sidecar_cover' => $targetFilename,
    ];
}