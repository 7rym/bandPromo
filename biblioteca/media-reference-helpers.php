<?php

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/cover-art-helpers.php';

function bandpromo_media_reference_targets(): array
{
    return ['illustrations', 'photos', 'video'];
}

function bandpromo_media_reference_normalize_basename(?string $path, string $expectedPrefix): string
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
    $pattern = '#^' . preg_quote(trim($expectedPrefix, '/'), '#') . '/(.+)$#i';
    if (preg_match($pattern, $value, $matches) === 1) {
        return basename($matches[1]);
    }

    return basename($value);
}

function bandpromo_media_reference_original_prefix(string $target): ?string
{
    $map = [
        'illustrations' => 'media/img/original',
        'photos' => 'media/photo/original',
        'video' => 'media/video/original',
    ];

    return $map[$target] ?? null;
}

function bandpromo_media_reference_file_exists(string $root, string $target, string $basename): bool
{
    $prefix = bandpromo_media_reference_original_prefix($target);
    if ($prefix === null || $basename === '') {
        return false;
    }

    return is_file($root . '/' . $prefix . '/' . $basename);
}

function bandpromo_media_reference_gallery_matches_target(string $target, string $filename, array $item): bool
{
    $src = str_replace('\\', '/', trim((string) ($item['src'] ?? '')));
    if ($src === '' || basename($src) !== $filename) {
        return false;
    }

    $type = trim((string) ($item['type'] ?? ''));
    if ($target === 'video') {
        return $type === 'video' && stripos($src, '/media/video/') !== false;
    }
    if ($target === 'photos') {
        return $type !== 'video' && stripos($src, '/media/photo/') !== false;
    }
    if ($target === 'illustrations') {
        return $type !== 'video' && stripos($src, '/media/img/') !== false;
    }

    return false;
}

function bandpromo_media_reference_collect_gallery_references(string $root, string $target, string $filename): array
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
        if (!is_array($item) || !bandpromo_media_reference_gallery_matches_target($target, $filename, $item)) {
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

function bandpromo_media_reference_config_entries(string $target): array
{
    if ($target === 'photos') {
        return [
            ['path' => 'release.theme.background_image', 'legacy' => ['media.background_image'], 'prefix' => 'media/photo/original', 'kind' => 'theme-background', 'label' => 'Background image (theme)'],
            ['path' => 'release.theme.cover', 'legacy' => ['media.cover'], 'prefix' => 'media/photo/original', 'kind' => 'theme-cover', 'label' => 'Primary cover (theme)'],
            ['path' => 'release.social.share_image', 'legacy' => ['social.share_image'], 'prefix' => 'media/photo/original', 'kind' => 'share-image', 'label' => 'Share image (social)'],
        ];
    }

    if ($target === 'video') {
        return [
            ['path' => 'release.theme.background_video', 'legacy' => ['media.background_video'], 'prefix' => 'media/video/original', 'kind' => 'theme-background-video', 'label' => 'Background video (theme)'],
        ];
    }

    return [];
}

function bandpromo_media_reference_collect_config_references(string $root, string $target, string $filename): array
{
    $references = [];
    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    if ($config === []) {
        return $references;
    }

    foreach (bandpromo_media_reference_config_entries($target) as $entry) {
        $raw = bandpromo_config_get_nonempty_value($config, $entry['path']);
        if (!is_string($raw) || trim($raw) === '') {
            foreach ($entry['legacy'] as $legacyPath) {
                $raw = bandpromo_config_get_nonempty_value($config, $legacyPath);
                if (is_string($raw) && trim($raw) !== '') {
                    break;
                }
            }
        }

        $basename = bandpromo_media_reference_normalize_basename(is_string($raw) ? $raw : '', $entry['prefix']);
        if ($basename === '' || $basename !== $filename) {
            continue;
        }

        if (!bandpromo_media_reference_file_exists($root, $target, $basename)) {
            continue;
        }

        $references[] = [
            'scope' => 'config',
            'kind' => $entry['kind'],
            'label' => $entry['label'],
        ];
    }

    return $references;
}

function bandpromo_media_reference_collect_references(string $root, string $target, string $filename): array
{
    if ($target === 'illustrations') {
        return bandpromo_cover_art_collect_references($root, $filename);
    }

    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return [];
    }

    $references = bandpromo_media_reference_collect_gallery_references($root, $target, $safe);
    foreach (bandpromo_media_reference_collect_config_references($root, $target, $safe) as $reference) {
        $references[] = $reference;
    }

    return $references;
}

function bandpromo_media_reference_describe_file(string $root, string $target, string $filename): array
{
    if ($target === 'illustrations') {
        $coverInfo = bandpromo_cover_art_describe_file($root, $filename);
        return [
            'filename' => (string) ($coverInfo['filename'] ?? basename($filename)),
            'role' => (string) ($coverInfo['role'] ?? ''),
            'origin' => (string) ($coverInfo['origin'] ?? ''),
            'references' => is_array($coverInfo['references'] ?? null) ? $coverInfo['references'] : [],
            'reference_count' => (int) ($coverInfo['reference_count'] ?? 0),
            'orphan' => !empty($coverInfo['orphan']),
            'regenerable' => !empty($coverInfo['regenerable']),
            'safe_to_delete' => !empty($coverInfo['safe_to_delete']),
            'linked_audio' => (string) ($coverInfo['linked_audio'] ?? ''),
        ];
    }

    $safe = basename($filename);
    $references = bandpromo_media_reference_collect_references($root, $target, $safe);
    $orphan = $references === [] && !bandpromo_media_is_bundled_placeholder($safe);

    return [
        'filename' => $safe,
        'role' => '',
        'origin' => bandpromo_media_origin($safe),
        'references' => $references,
        'reference_count' => count($references),
        'orphan' => $orphan,
        'regenerable' => false,
        'safe_to_delete' => $orphan,
        'linked_audio' => '',
    ];
}
