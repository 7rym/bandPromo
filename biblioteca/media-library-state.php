<?php

function bandpromo_media_library_state_path(): string
{
    return dirname(__DIR__) . '/data/media-library-state.json';
}

function bandpromo_media_library_default_state(): array
{
    return [
        'hidden' => [],
        'assets' => [],
    ];
}

function bandpromo_media_library_load_state(): array
{
    $path = bandpromo_media_library_state_path();
    if (!file_exists($path)) {
        return bandpromo_media_library_default_state();
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return bandpromo_media_library_default_state();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return bandpromo_media_library_default_state();
    }

    $state = bandpromo_media_library_default_state();
    if (isset($decoded['hidden']) && is_array($decoded['hidden'])) {
        $state['hidden'] = $decoded['hidden'];
    }
    if (isset($decoded['assets']) && is_array($decoded['assets'])) {
        $state['assets'] = $decoded['assets'];
    }

    return $state;
}

function bandpromo_media_get_asset_record(string $target, string $filename): array
{
    $state = bandpromo_media_library_load_state();
    $assets = is_array($state['assets'] ?? null) ? $state['assets'] : [];
    $key = bandpromo_media_library_key($target, $filename);
    $record = is_array($assets[$key] ?? null) ? $assets[$key] : [];

    return $record;
}

function bandpromo_media_record_asset(string $target, string $filename, array $meta): bool
{
    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return false;
    }

    $state = bandpromo_media_library_load_state();
    if (!isset($state['assets']) || !is_array($state['assets'])) {
        $state['assets'] = [];
    }

    $key = bandpromo_media_library_key($target, $safe);
    $existing = is_array($state['assets'][$key] ?? null) ? $state['assets'][$key] : [];
    $state['assets'][$key] = array_merge($existing, $meta, [
        'recorded_at' => gmdate('Y-m-d H:i:s'),
    ]);

    return bandpromo_media_library_save_state($state);
}

function bandpromo_media_library_save_state(array $state): bool
{
    $path = bandpromo_media_library_state_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    $payload = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    return file_put_contents($path, $payload . PHP_EOL, LOCK_EX) !== false;
}

function bandpromo_media_library_key(string $target, string $filename): string
{
    return trim($target) . '/' . basename($filename);
}

function bandpromo_media_is_bundled_placeholder(string $filename): bool
{
    return strncmp($filename, 'bandPromo_', 10) === 0;
}

function bandpromo_media_origin(string $filename): string
{
    return bandpromo_media_is_bundled_placeholder($filename) ? 'bundled-placeholder' : 'user-upload';
}

function bandpromo_media_is_hidden_for_install(string $target, string $filename): bool
{
    $state = bandpromo_media_library_load_state();
    $key = bandpromo_media_library_key($target, $filename);
    return !empty($state['hidden'][$key]);
}

function bandpromo_media_set_hidden_for_install(string $target, string $filename, bool $hidden): bool
{
    $state = bandpromo_media_library_load_state();
    $key = bandpromo_media_library_key($target, $filename);

    if ($hidden) {
        $state['hidden'][$key] = [
            'origin' => bandpromo_media_origin($filename),
            'hidden_at' => gmdate('Y-m-d H:i:s'),
        ];
    } else {
        unset($state['hidden'][$key]);
    }

    return bandpromo_media_library_save_state($state);
}

function bandpromo_media_target_dir(string $target): ?string
{
    $root = dirname(__DIR__);
    $dirs = [
        'audio' => $root . '/media/audio/original',
        'illustrations' => $root . '/media/img/original',
        'photos' => $root . '/media/photo/original',
        'video' => $root . '/media/video/original',
        'special' => $root . '/media/special',
    ];

    return $dirs[$target] ?? null;
}

function bandpromo_media_starter_pack_basenames(string $root): array
{
    $basenames = [];
    $markerPath = rtrim($root, '/\\') . '/data/default-theme-package.json';
    if (is_file($markerPath)) {
        $decoded = json_decode((string) file_get_contents($markerPath), true);
        if (is_array($decoded) && is_array($decoded['paths'] ?? null)) {
            foreach ($decoded['paths'] as $path) {
                if (!is_string($path) || strpos($path, 'media/') !== 0) {
                    continue;
                }
                $basename = basename(str_replace('\\', '/', $path));
                if ($basename !== '') {
                    $basenames[$basename] = true;
                }
            }
        }
    }

    if ($basenames === []) {
        foreach ([
            'bandPromo_share.png',
            'bandPromo_vocalist.png',
            'bandPromo_the_very_first_song.flac',
            'bandPromo_the_second_song.flac',
        ] as $fallback) {
            $basenames[$fallback] = true;
        }
    }

    return array_keys($basenames);
}

function bandpromo_media_is_generated_delivery_artifact(string $filename): bool
{
    return preg_match('/^bandPromo_.+_(facebook|twitter)\.jpe?g$/i', $filename) === 1;
}

function bandpromo_media_is_operator_upload_filename(string $root, string $target, string $filename): bool
{
    if (strcasecmp($filename, 'desktop.ini') === 0) {
        return false;
    }

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension === '' || $extension === 'htaccess') {
        return false;
    }

    static $starterLookup = null;
    static $starterRoot = '';
    if ($starterRoot !== $root || !is_array($starterLookup)) {
        $starterRoot = $root;
        $starterLookup = array_fill_keys(bandpromo_media_starter_pack_basenames($root), true);
    }

    if (isset($starterLookup[$filename])) {
        return false;
    }

    if (bandpromo_media_is_bundled_placeholder($filename)) {
        return false;
    }

    if (bandpromo_media_is_generated_delivery_artifact($filename)) {
        return false;
    }

    if (bandpromo_media_is_hidden_for_install($target, $filename)) {
        return false;
    }

    if ($target === 'illustrations') {
        require_once __DIR__ . '/cover-art-helpers.php';
        $manifest = bandpromo_cover_art_manifest_record($filename);
        $playlistContext = bandpromo_cover_art_load_playlist_context($root);
        $role = $manifest['role'] !== '' ? $manifest['role'] : bandpromo_cover_art_infer_role($filename, $playlistContext);
        $origin = bandpromo_cover_art_infer_origin($filename, $manifest, $role);
        if (in_array($origin, ['build-extracted', 'build-configured', 'build-sidecar-copy', 'bundled-placeholder'], true)) {
            return false;
        }
    }

    return true;
}

function bandpromo_media_target_has_operator_uploads(string $root, string $target): bool
{
    $dir = bandpromo_media_target_dir($target);
    if ($dir === null || !is_dir($dir)) {
        return false;
    }

    foreach (new DirectoryIterator($dir) as $entry) {
        if ($entry->isDot() || $entry->isDir()) {
            continue;
        }

        if (bandpromo_media_is_operator_upload_filename($root, $target, $entry->getFilename())) {
            return true;
        }
    }

    return false;
}

function bandpromo_media_install_has_operator_uploads(string $root): bool
{
    foreach (['audio', 'illustrations', 'photos', 'special'] as $target) {
        if (bandpromo_media_target_has_operator_uploads($root, $target)) {
            return true;
        }
    }

    return false;
}

function bandpromo_media_has_visible_user_uploads(string $target): bool
{
    return bandpromo_media_target_has_operator_uploads(dirname(__DIR__), $target);
}

function bandpromo_media_is_effectively_hidden_for_install(string $target, string $filename): bool
{
    if (bandpromo_media_is_hidden_for_install($target, $filename)) {
        return true;
    }

    if (!bandpromo_media_is_bundled_placeholder($filename)) {
        return false;
    }

    $root = dirname(__DIR__);
    require_once __DIR__ . '/demo-catalog-state.php';
    if (!bandpromo_demo_catalog_is_visible($root)) {
        return true;
    }

    return bandpromo_media_has_visible_user_uploads($target);
}