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

    if (is_file(bandpromo_playlist_document_path($root, BANDPROMO_PLAYLIST_DEFAULT_ID))) {
        try {
            $document = bandpromo_playlist_load_document($root, BANDPROMO_PLAYLIST_DEFAULT_ID);
            $tracks = bandpromo_playlist_build_track_list($root, $document);
            $map = [];
            foreach ($tracks as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $file = trim((string) ($entry['file'] ?? ''));
                if ($file === '') {
                    continue;
                }
                $map[$file] = $entry;
            }

            return $cache[$root] = $map;
        } catch (Throwable $throwable) {
            // Fall through to legacy built playlist.
        }
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