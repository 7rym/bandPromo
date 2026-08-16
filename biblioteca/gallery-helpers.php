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
    require_once __DIR__ . '/theme-storage.php';

    $src = bandpromo_gallery_normalize_src_path($src);
    $ref = trim($src);
    $assetId = '';
    if (bandpromo_asset_is_asset_id($ref)) {
        $assetId = $ref;
    } elseif (bandpromo_asset_is_asset_id(basename($ref))) {
        $assetId = basename($ref);
    } elseif ($src !== '') {
        // Delivery URLs end in card.jpg — recover ast_* from the path segment.
        $assetId = bandpromo_theme_lookup_asset_id_for_path($root_dir, $src);
    }

    if ($assetId !== '') {
        // Prefer card delivery; allow master preview until post-import deliverables exist.
        $resolved = bandpromo_visual_resolve_url($root_dir, $assetId, 'card', '', true);
        if ($resolved !== '' && str_starts_with($resolved, '/media/visual/delivery/') && !is_file($root_dir . $resolved)) {
            // Stale registry delivery after a masters-only PRP import.
            $resolved = '';
            require_once __DIR__ . '/visual-master-helpers.php';
            $asset = bandpromo_asset_lookup_by_id($root_dir, $assetId);
            if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
                $format = strtolower(trim((string) ($asset['master_format'] ?? pathinfo(
                    (string) ($asset['original_filename'] ?? ''),
                    PATHINFO_EXTENSION
                ))));
                if ($format !== '') {
                    $masterPath = bandpromo_visual_master_path($root_dir, $assetId, $format);
                    if ($masterPath !== '' && is_file($masterPath)) {
                        $resolved = '/media/visual/master/' . basename($masterPath);
                    }
                }
                if ($resolved === '') {
                    $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
                    if ($masterFilename !== ''
                        && is_file(bandpromo_visual_master_dir($root_dir) . DIRECTORY_SEPARATOR . $masterFilename)
                    ) {
                        $resolved = '/media/visual/master/' . $masterFilename;
                    }
                }
            }
        }
        if ($resolved !== '') {
            return $resolved;
        }
    }

    if ($src === '') {
        return '';
    }

    // Prefer registry delivery when src is a legacy stem path or basename.
    $filename = basename($src);
    if ($filename !== '' && !in_array(strtolower($filename), ['card.jpg', 'thumb.jpg', 'huge.jpg', 'poster.jpg'], true)) {
        $resolved = bandpromo_visual_resolve_url($root_dir, $filename, 'card', '', true);
        if ($resolved !== '') {
            return $resolved;
        }
    }

    // Already a Visual delivery URL on disk.
    if (str_starts_with($src, '/media/visual/delivery/') && is_file($root_dir . $src)) {
        return $src;
    }

    return '';
}

function bandpromo_gallery_normalize_items(string $root_dir, array $items): array {
    require_once __DIR__ . '/media-delivery-helpers.php';
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/theme-storage.php';

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = strtolower(trim((string) ($item['type'] ?? '')));
        $assetId = trim((string) ($item['asset_id'] ?? ''));
        if ($assetId !== '' && !bandpromo_asset_is_asset_id($assetId)) {
            $assetId = '';
        }
        if ($assetId === '') {
            $assetId = bandpromo_theme_lookup_asset_id_for_path(
                $root_dir,
                (string) ($item['src'] ?? '')
            );
        }

        if ($type === 'video') {
            if ($assetId === '') {
                $filename = bandpromo_gallery_video_filename_from_src((string) ($item['src'] ?? ''));
                if ($filename !== '' && !in_array(strtolower($filename), ['standard-stream.mp4', 'poster.jpg'], true)) {
                    $asset = bandpromo_asset_lookup_visual($root_dir, $filename, 'video')
                        ?? bandpromo_asset_lookup_by_original_filename($root_dir, $filename)
                        ?? bandpromo_asset_lookup_by_master_filename($root_dir, $filename);
                    if (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
                        $assetId = (string) ($asset['id'] ?? '');
                    }
                }
            }

            if ($assetId !== '') {
                $stream = bandpromo_visual_resolve_url($root_dir, $assetId, 'standard-stream', '', false);
                $poster = bandpromo_visual_resolve_url($root_dir, $assetId, 'poster', '', false);
                if ($stream !== '') {
                    $items[$index]['src'] = $stream;
                }
                if ($poster !== '') {
                    $items[$index]['poster'] = $poster;
                } elseif (isset($items[$index]['poster'])) {
                    unset($items[$index]['poster']);
                }
                $items[$index]['asset_id'] = $assetId;
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
