<?php

function bandpromo_media_library_state_path(): string
{
    return dirname(__DIR__) . '/data/media-library-state.json';
}

function bandpromo_media_library_default_state(): array
{
    return [
        'hidden' => [],
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

    return $state;
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

function bandpromo_media_has_visible_user_uploads(string $target): bool
{
    $dir = bandpromo_media_target_dir($target);
    if ($dir === null || !is_dir($dir)) {
        return false;
    }

    foreach (new DirectoryIterator($dir) as $entry) {
        if ($entry->isDot() || $entry->isDir()) {
            continue;
        }

        $filename = $entry->getFilename();
        if (strcasecmp($filename, 'desktop.ini') === 0) {
            continue;
        }

        if (bandpromo_media_is_bundled_placeholder($filename)) {
            continue;
        }

        if (bandpromo_media_is_hidden_for_install($target, $filename)) {
            continue;
        }

        return true;
    }

    return false;
}

function bandpromo_media_is_effectively_hidden_for_install(string $target, string $filename): bool
{
    if (bandpromo_media_is_hidden_for_install($target, $filename)) {
        return true;
    }

    if (!bandpromo_media_is_bundled_placeholder($filename)) {
        return false;
    }

    return bandpromo_media_has_visible_user_uploads($target);
}