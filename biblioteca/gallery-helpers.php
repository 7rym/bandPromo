<?php

function bandpromo_gallery_file_path(string $root_dir): string {
    return $root_dir . '/data/gallery.json';
}

function bandpromo_gallery_video_filename_from_src(string $src): string {
    $path = parse_url($src, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $src;
    }
    $path = str_replace('\\', '/', $path);
    return basename($path);
}

function bandpromo_gallery_video_delivery_relative_path(string $filename): string {
    return '/media/video/optimal/' . pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
}

function bandpromo_gallery_video_poster_relative_path(string $filename): string {
    return '/media/video/poster/' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
}

function bandpromo_gallery_normalize_items(string $root_dir, array $items): array {
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = trim((string) ($item['type'] ?? ''));
        if ($type !== 'video') {
            continue;
        }

        $filename = bandpromo_gallery_video_filename_from_src((string) ($item['src'] ?? ''));
        if ($filename === '') {
            continue;
        }

        $delivery_relative = bandpromo_gallery_video_delivery_relative_path($filename);
        if (is_file($root_dir . $delivery_relative)) {
            $items[$index]['src'] = $delivery_relative;
        }

        $poster_relative = bandpromo_gallery_video_poster_relative_path($filename);
        if (is_file($root_dir . $poster_relative)) {
            $items[$index]['poster'] = $poster_relative;
        } elseif (isset($items[$index]['poster'])) {
            unset($items[$index]['poster']);
        }
    }

    return $items;
}

function bandpromo_decode_gallery_items(string $root_dir): ?array {
    $gallery_file = bandpromo_gallery_file_path($root_dir);
    if (!is_file($gallery_file)) {
        return null;
    }

    $raw = file_get_contents($gallery_file);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function bandpromo_load_gallery_items(string $root_dir): array {
    $decoded = bandpromo_decode_gallery_items($root_dir);
    if (!is_array($decoded)) {
        return [];
    }

    return bandpromo_gallery_normalize_items($root_dir, $decoded);
}
