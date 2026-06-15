<?php

require_once __DIR__ . '/media-delivery-helpers.php';

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

function bandpromo_gallery_video_poster_absolute_path(string $root_dir, string $filename): string {
    return $root_dir . bandpromo_gallery_video_poster_relative_path($filename);
}

function bandpromo_gallery_normalize_src_path(string $src): string
{
    $src = str_replace('\\', '/', trim($src));
    if ($src === '') {
        return '';
    }

    $path = parse_url($src, PHP_URL_PATH);
    if (is_string($path) && $path !== '') {
        $src = $path;
    }

    while (str_starts_with($src, '../')) {
        $src = substr($src, 3);
    }

    if ($src !== '' && $src[0] !== '/') {
        $src = '/' . $src;
    }

    return $src;
}

function bandpromo_gallery_resolve_image_src(string $root_dir, string $src): string
{
    $src = bandpromo_gallery_normalize_src_path($src);
    if ($src === '') {
        return '';
    }

    $filename = basename($src);
    if ($filename === '') {
        return $src;
    }

    if (str_starts_with($src, '/media/photo/optimal/') && is_file($root_dir . $src)) {
        return $src;
    }

    $deliveryRelative = '/media/photo/optimal/' . bandpromo_photo_delivery_basename($filename);
    if (is_file($root_dir . $deliveryRelative)) {
        return $deliveryRelative;
    }

    if (str_contains($src, '/media/img/')) {
        $optimal = preg_replace('#/original/#', '/optimal/', $src) ?? $src;
        $optimalJpg = preg_replace('/\.(png|jpe?g|webp)$/i', '.jpg', $optimal) ?? $optimal;
        if (is_file($root_dir . $optimalJpg)) {
            return $optimalJpg;
        }
        if (is_file($root_dir . $optimal)) {
            return $optimal;
        }
    }

    if (is_file($root_dir . $src)) {
        return $src;
    }

    $original = preg_replace('#/optimal/#', '/original/', $src) ?? $src;
    if ($original !== $src && is_file($root_dir . $original)) {
        return $original;
    }

    return $deliveryRelative;
}

function bandpromo_gallery_normalize_items(string $root_dir, array $items): array {
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = strtolower(trim((string) ($item['type'] ?? '')));
        if ($type === 'video') {
            $filename = bandpromo_gallery_video_filename_from_src((string) ($item['src'] ?? ''));
            if ($filename === '') {
                continue;
            }

            $delivery_relative = bandpromo_gallery_video_delivery_relative_path($filename);
            if (is_file($root_dir . $delivery_relative)) {
                $items[$index]['src'] = $delivery_relative;
            } else {
                $normalizedSrc = bandpromo_gallery_normalize_src_path((string) ($item['src'] ?? ''));
                if ($normalizedSrc !== '') {
                    $items[$index]['src'] = $normalizedSrc;
                }
            }

            $poster_relative = bandpromo_gallery_video_poster_relative_path($filename);
            if (is_file($root_dir . $poster_relative)) {
                $items[$index]['poster'] = $poster_relative;
            } elseif (isset($items[$index]['poster'])) {
                unset($items[$index]['poster']);
            }
            continue;
        }

        $resolved = bandpromo_gallery_resolve_image_src($root_dir, (string) ($item['src'] ?? ''));
        if ($resolved !== '') {
            $items[$index]['src'] = $resolved;
        }
        if ($type === '') {
            $items[$index]['type'] = 'image';
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

function bandpromo_load_gallery_items(string $root_dir, string $galleryId = 'main'): array {
    require_once __DIR__ . '/gallery-storage.php';
    bandpromo_gallery_ensure_seeded($root_dir);

    try {
        return bandpromo_gallery_materialize_items($root_dir, $galleryId);
    } catch (Throwable $throwable) {
        $decoded = bandpromo_decode_gallery_items($root_dir);
        if (!is_array($decoded)) {
            return [];
        }

        return bandpromo_gallery_normalize_items($root_dir, $decoded);
    }
}
