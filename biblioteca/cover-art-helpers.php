<?php

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/config-loader.php';

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
    $stems = [];
    $audioDir = $root . '/media/audio/original';
    if (!is_dir($audioDir)) {
        return $stems;
    }

    foreach (new DirectoryIterator($audioDir) as $entry) {
        if ($entry->isDot() || $entry->isDir()) {
            continue;
        }

        $filename = $entry->getFilename();
        if (strcasecmp($filename, 'desktop.ini') === 0) {
            continue;
        }

        $stems[pathinfo($filename, PATHINFO_FILENAME)] = $filename;
    }

    return $stems;
}

function bandpromo_cover_art_load_playlist_context(string $root): array
{
    $context = [
        'cover_refs' => [],
        'cover_sources' => [],
        'audio_stems' => bandpromo_cover_art_collect_audio_stems($root),
        'configured_in_use' => false,
    ];

    $playlist = $root . '/play/playlist.json';
    if (!is_file($playlist)) {
        return $context;
    }

    $decoded = json_decode(file_get_contents($playlist) ?: '[]', true);
    if (!is_array($decoded)) {
        return $context;
    }

    $validation_file = $root . '/play/playlist-validation.json';
    $validation_map = [];
    if (is_file($validation_file)) {
        $validation_decoded = json_decode(file_get_contents($validation_file) ?: '[]', true);
        if (is_array($validation_decoded) && is_array($validation_decoded['tracks'] ?? null)) {
            foreach ($validation_decoded['tracks'] as $track) {
                if (!is_array($track)) {
                    continue;
                }
                $file = trim((string) ($track['file'] ?? ''));
                if ($file === '') {
                    continue;
                }
                $validation_map[$file] = strtolower(trim((string) ($track['coverSource'] ?? '')));
            }
        }
    }

    foreach ($decoded as $track) {
        if (!is_array($track)) {
            continue;
        }

        $audioFile = trim((string) ($track['file'] ?? ''));
        $label = trim((string) ($track['title'] ?? $audioFile));
        $coverBasename = bandpromo_cover_art_normalize_img_basename((string) ($track['cover'] ?? ''));
        if ($coverBasename === '') {
            continue;
        }

        if (!isset($context['cover_refs'][$coverBasename])) {
            $context['cover_refs'][$coverBasename] = [];
        }

        $context['cover_refs'][$coverBasename][] = [
            'scope' => 'playlist',
            'kind' => 'playlist-cover',
            'label' => $label !== '' ? $label : $coverBasename,
            'audio_file' => $audioFile,
        ];

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
    $references = [];
    $gallery_file = $root . '/data/gallery.json';
    if (!is_file($gallery_file)) {
        return $references;
    }

    $gallery = json_decode(file_get_contents($gallery_file) ?: '[]', true);
    if (!is_array($gallery)) {
        return $references;
    }

    foreach ($gallery as $item) {
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
        ];
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

function bandpromo_cover_art_collect_references(string $root, string $filename): array
{
    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return [];
    }

    $playlistContext = bandpromo_cover_art_load_playlist_context($root);
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

function bandpromo_cover_art_describe_file(string $root, string $filename): array
{
    $safe = basename($filename);
    $playlistContext = bandpromo_cover_art_load_playlist_context($root);
    $manifest = bandpromo_cover_art_manifest_record($safe);
    $role = $manifest['role'] !== '' ? $manifest['role'] : bandpromo_cover_art_infer_role($safe, $playlistContext);
    $origin = bandpromo_cover_art_infer_origin($safe, $manifest, $role);
    $references = bandpromo_cover_art_collect_references($root, $safe);
    $linkedAudio = $manifest['linked_audio'];

    if ($linkedAudio === '' && $role === 'track-cover') {
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
