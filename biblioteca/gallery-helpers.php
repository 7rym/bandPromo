<?php

require_once __DIR__ . '/media-delivery-helpers.php';

function bandpromo_gallery_file_path(string $root_dir): string {
    // One-time import source only. Runtime gallery reads use data/galleries/ containers.
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
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/asset-registry.php';

    $src = bandpromo_gallery_normalize_src_path($src);
    $ref = trim($src);
    $assetId = '';
    if (bandpromo_asset_is_asset_id($ref)) {
        $assetId = $ref;
    } elseif (bandpromo_asset_is_asset_id(basename($ref))) {
        $assetId = basename($ref);
    }

    if ($assetId !== '') {
        $resolved = bandpromo_visual_resolve_url($root_dir, $assetId, 'card');
        if ($resolved !== '') {
            return $resolved;
        }
    }

    if ($src === '') {
        return '';
    }

    // Prefer registry delivery when src is a legacy stem path.
    $filename = basename($src);
    if ($filename !== '') {
        $resolved = bandpromo_visual_resolve_url($root_dir, $filename, 'card');
        if ($resolved !== '' && str_starts_with($resolved, '/media/visual/delivery/')) {
            return $resolved;
        }
    }

    if (str_starts_with($src, '/media/photo/optimal/') && is_file($root_dir . $src)) {
        return $src;
    }

    $deliveryRelative = '/media/photo/optimal/' . bandpromo_photo_delivery_basename($filename);
    if ($filename !== '' && is_file($root_dir . $deliveryRelative)) {
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

    return $filename !== '' ? $deliveryRelative : $src;
}

function bandpromo_gallery_normalize_items(string $root_dir, array $items): array {
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/asset-registry.php';

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = strtolower(trim((string) ($item['type'] ?? '')));
        $assetId = trim((string) ($item['asset_id'] ?? ''));
        if ($assetId !== '' && !bandpromo_asset_is_asset_id($assetId)) {
            $assetId = '';
        }

        if ($type === 'video') {
            if ($assetId !== '') {
                $stream = bandpromo_visual_resolve_url($root_dir, $assetId, 'standard-stream');
                $poster = bandpromo_visual_resolve_url($root_dir, $assetId, 'poster');
                if ($stream !== '') {
                    $items[$index]['src'] = $stream;
                }
                if ($poster !== '') {
                    $items[$index]['poster'] = $poster;
                }
                $items[$index]['asset_id'] = $assetId;
                continue;
            }

            $filename = bandpromo_gallery_video_filename_from_src((string) ($item['src'] ?? ''));
            if ($filename === '') {
                continue;
            }

            $preview = bandpromo_video_admin_preview_relative_url($root_dir, $filename);
            if ($preview !== '') {
                $items[$index]['src'] = $preview;
            } else {
                $delivery_relative = bandpromo_gallery_video_delivery_relative_path($filename);
                if (is_file($root_dir . $delivery_relative)) {
                    $items[$index]['src'] = $delivery_relative;
                } else {
                    $normalizedSrc = bandpromo_gallery_normalize_src_path((string) ($item['src'] ?? ''));
                    if ($normalizedSrc !== '') {
                        $items[$index]['src'] = $normalizedSrc;
                    }
                }
            }

            $posterUrl = '';
            $asset = bandpromo_asset_lookup_visual($root_dir, $filename, 'video')
                ?? bandpromo_asset_lookup_by_original_filename($root_dir, $filename);
            if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
                $posterUrl = bandpromo_visual_resolve_url($root_dir, (string) ($asset['id'] ?? ''), 'poster');
                $items[$index]['asset_id'] = (string) ($asset['id'] ?? '');
            }
            if ($posterUrl === '') {
                $poster_relative = bandpromo_gallery_video_poster_relative_path($filename);
                if (is_file($root_dir . $poster_relative)) {
                    $posterUrl = $poster_relative;
                }
            }
            if ($posterUrl !== '') {
                $items[$index]['poster'] = $posterUrl;
            } elseif (isset($items[$index]['poster'])) {
                unset($items[$index]['poster']);
            }
            continue;
        }

        $srcRef = $assetId !== '' ? $assetId : (string) ($item['src'] ?? '');
        $resolved = bandpromo_gallery_resolve_image_src($root_dir, $srcRef);
        if ($resolved !== '') {
            $items[$index]['src'] = $resolved;
        }
        if ($assetId !== '') {
            $items[$index]['asset_id'] = $assetId;
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

function bandpromo_load_gallery_items(string $root_dir, string $galleryId = 'bandpromo-demo'): array {
    require_once __DIR__ . '/gallery-storage.php';
    bandpromo_gallery_ensure_seeded($root_dir);

    return bandpromo_gallery_materialize_items($root_dir, $galleryId);
}
