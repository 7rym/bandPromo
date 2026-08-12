<?php

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/cover-art-helpers.php';

function bandpromo_media_reference_targets(): array
{
    return ['illustrations', 'photos', 'video', 'special'];
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
        'special' => 'media/special',
        'sfx' => 'media/sfx/original',
    ];

    return $map[$target] ?? null;
}

/**
 * When a config value includes a /media/… path, require the expected intake prefix.
 * Bare basenames are allowed (matched against files in that target).
 */
function bandpromo_media_reference_path_matches_prefix(?string $raw, string $prefix): bool
{
    $value = str_replace('\\', '/', trim((string) $raw));
    if ($value === '') {
        return false;
    }
    if (strpos($value, '://') !== false) {
        $parsed = parse_url($value, PHP_URL_PATH);
        $value = is_string($parsed) ? $parsed : '';
    }
    $value = ltrim($value, '/');
    if ($value === '') {
        return false;
    }
    if (preg_match('#^media/#i', $value) !== 1) {
        return true;
    }

    $expected = trim($prefix, '/');

    return $expected !== '' && stripos($value, $expected . '/') === 0;
}

function bandpromo_media_reference_file_exists(string $root, string $target, string $basename): bool
{
    $prefix = bandpromo_media_reference_original_prefix($target);
    if ($prefix === null || $basename === '') {
        return false;
    }

    return is_file($root . '/' . $prefix . '/' . $basename);
}

/**
 * Resolve a gallery entry to a visual asset id (explicit field or delivery path).
 */
function bandpromo_media_reference_gallery_item_asset_id(string $root, array $item): string
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/theme-storage.php';

    $assetId = trim((string) ($item['asset_id'] ?? ''));
    if ($assetId !== '' && bandpromo_asset_is_asset_id($assetId)) {
        if (bandpromo_asset_lookup_by_id($root, $assetId) !== null) {
            return $assetId;
        }
    }

    $src = trim((string) ($item['src'] ?? ''));
    if ($src === '') {
        return '';
    }

    return bandpromo_theme_lookup_asset_id_for_path($root, $src);
}

/**
 * Files-pool target for a gallery item (photos / video / illustrations).
 */
function bandpromo_media_reference_gallery_item_files_target(string $root, array $item): string
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = bandpromo_media_reference_gallery_item_asset_id($root, $item);
    if ($assetId !== '') {
        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
        if (is_array($asset)) {
            $fromIntake = bandpromo_asset_files_index_target_for_intake_bucket(
                (string) ($asset['intake_bucket'] ?? '')
            );
            if (in_array($fromIntake, ['photos', 'video', 'illustrations'], true)) {
                return $fromIntake;
            }
        }
    }

    $src = str_replace('\\', '/', trim((string) ($item['src'] ?? '')));
    $fromPath = bandpromo_media_reference_target_for_media_path($src);
    if ($fromPath !== '') {
        return $fromPath;
    }

    $type = strtolower(trim((string) ($item['type'] ?? '')));

    return $type === 'video' ? 'video' : '';
}

/**
 * Candidate Files basenames for a gallery item (original/master, not delivery variants).
 *
 * @return list<string>
 */
function bandpromo_media_reference_gallery_item_candidate_filenames(string $root, array $item): array
{
    require_once __DIR__ . '/asset-registry.php';

    $names = [];
    $assetId = bandpromo_media_reference_gallery_item_asset_id($root, $item);
    if ($assetId !== '') {
        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
        if (is_array($asset)) {
            foreach (['original_filename', 'master_filename'] as $field) {
                $name = basename(trim((string) ($asset[$field] ?? '')));
                if ($name !== '' && $name !== '.' && $name !== '..') {
                    $names[$name] = true;
                }
            }
        }
    }

    $src = str_replace('\\', '/', trim((string) ($item['src'] ?? '')));
    $basename = $src !== '' ? basename($src) : '';
    // Delivery variant filenames are not Files pool keys.
    $deliveryVariants = [
        'thumb.jpg' => true,
        'card.jpg' => true,
        'poster.jpg' => true,
        'standard-stream.mp4' => true,
    ];
    if ($basename !== ''
        && $basename !== '.'
        && $basename !== '..'
        && !isset($deliveryVariants[strtolower($basename)])
        && preg_match('#^/media/visual/delivery/#i', $src) !== 1
    ) {
        $names[$basename] = true;
    }

    return array_keys($names);
}

function bandpromo_media_reference_gallery_matches_target(
    string $root,
    string $target,
    string $filename,
    array $item
): bool {
    $safe = basename(trim($filename));
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return false;
    }
    if (!in_array($target, ['photos', 'video', 'illustrations'], true)) {
        return false;
    }
    if (bandpromo_media_reference_gallery_item_files_target($root, $item) !== $target) {
        return false;
    }

    foreach (bandpromo_media_reference_gallery_item_candidate_filenames($root, $item) as $candidate) {
        if (bandpromo_media_reference_names_match($candidate, $safe)) {
            return true;
        }
    }

    return false;
}

function bandpromo_media_reference_collect_gallery_references(string $root, string $target, string $filename): array
{
    $index = bandpromo_media_reference_build_gallery_index($root, $target);
    $safe = basename($filename);

    return $index[$safe] ?? [];
}

function bandpromo_media_reference_build_gallery_index(string $root, string $target): array
{
    require_once __DIR__ . '/gallery-storage.php';
    $index = [];

    if (!in_array($target, ['photos', 'video', 'illustrations'], true)) {
        return $index;
    }

    try {
        bandpromo_gallery_ensure_seeded($root);
    } catch (Throwable $throwable) {
        return $index;
    }

    foreach (bandpromo_gallery_registry_entries($root) as $registryEntry) {
        $galleryId = (string) ($registryEntry['id'] ?? '');
        if ($galleryId === '') {
            continue;
        }

        try {
            $items = bandpromo_gallery_materialize_items($root, $galleryId);
        } catch (Throwable $throwable) {
            continue;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (bandpromo_media_reference_gallery_item_files_target($root, $item) !== $target) {
                continue;
            }

            $candidates = bandpromo_media_reference_gallery_item_candidate_filenames($root, $item);
            if ($candidates === []) {
                continue;
            }

            $labelSource = trim((string) ($item['name'] ?? $item['alt'] ?? ''));
            foreach ($candidates as $basename) {
                if (!isset($index[$basename])) {
                    $index[$basename] = [];
                }
                $index[$basename][] = [
                    'scope' => 'gallery',
                    'kind' => 'gallery-item',
                    'label' => $labelSource !== '' ? $labelSource : $basename,
                    'gallery_id' => $galleryId,
                ];
            }
        }
    }

    return $index;
}

function bandpromo_media_reference_config_entries(string $target): array
{
    if ($target === 'photos') {
        return [
            ['path' => 'release.theme.background_image', 'legacy' => ['media.background_image'], 'prefix' => 'media/photo/original', 'kind' => 'theme-background', 'label' => 'Still background (theme)'],
            ['path' => 'release.theme.cover', 'legacy' => ['media.cover'], 'prefix' => 'media/photo/original', 'kind' => 'theme-cover', 'label' => 'Primary cover (theme)'],
            ['path' => 'release.social.share_image', 'legacy' => ['social.share_image'], 'prefix' => 'media/photo/original', 'kind' => 'share-image', 'label' => 'Share image (social)'],
        ];
    }

    if ($target === 'video') {
        return [
            ['path' => 'release.theme.background_video', 'legacy' => ['media.background_video'], 'prefix' => 'media/video/original', 'kind' => 'theme-background-video', 'label' => 'Living background (theme)'],
        ];
    }

    if ($target === 'special') {
        return [
            ['path' => 'install.brand.logo', 'legacy' => ['install.theme.logo', 'media.logo', 'release.brand.logo', 'release.theme.logo'], 'prefix' => 'media/special', 'kind' => 'brand-logo', 'label' => 'Logo'],
            ['path' => 'release.brand.poster', 'legacy' => ['release.social.share_image', 'social.share_image', 'install.brand.poster'], 'prefix' => 'media/special', 'kind' => 'share-image', 'label' => 'Share image'],
            ['path' => 'release.theme.cover', 'legacy' => ['media.cover'], 'prefix' => 'media/special', 'kind' => 'theme-cover', 'label' => 'Primary cover'],
            ['path' => 'release.theme.background_image', 'legacy' => ['media.background_image'], 'prefix' => 'media/special', 'kind' => 'theme-background', 'label' => 'Still background'],
            ['path' => 'release.theme.background_video', 'legacy' => ['media.background_video'], 'prefix' => 'media/special', 'kind' => 'theme-background-video', 'label' => 'Living background'],
            // Legacy shell audio paths (pre–Sound effects pool).
            ['path' => 'install.theme.welcome_audio', 'legacy' => ['media.welcome_audio'], 'prefix' => 'media/special', 'kind' => 'welcome-audio', 'label' => 'Welcome audio'],
            ['path' => 'install.theme.loggedin_audio', 'legacy' => ['media.loggedin_audio'], 'prefix' => 'media/special', 'kind' => 'loggedin-audio', 'label' => 'Logged-in audio'],
        ];
    }

    if ($target === 'sfx') {
        return [
            ['path' => 'install.theme.welcome_audio', 'legacy' => ['media.welcome_audio'], 'prefix' => 'media/sfx/optimal', 'kind' => 'welcome-audio', 'label' => 'Welcome audio'],
            ['path' => 'install.theme.loggedin_audio', 'legacy' => ['media.loggedin_audio'], 'prefix' => 'media/sfx/optimal', 'kind' => 'loggedin-audio', 'label' => 'Logged-in audio'],
            ['path' => 'install.theme.welcome_audio', 'legacy' => ['media.welcome_audio'], 'prefix' => 'media/sfx/master', 'kind' => 'welcome-audio', 'label' => 'Welcome audio'],
            ['path' => 'install.theme.loggedin_audio', 'legacy' => ['media.loggedin_audio'], 'prefix' => 'media/sfx/master', 'kind' => 'loggedin-audio', 'label' => 'Logged-in audio'],
            ['path' => 'install.theme.welcome_audio', 'legacy' => ['media.welcome_audio'], 'prefix' => 'media/sfx/original', 'kind' => 'welcome-audio', 'label' => 'Welcome audio'],
            ['path' => 'install.theme.loggedin_audio', 'legacy' => ['media.loggedin_audio'], 'prefix' => 'media/sfx/original', 'kind' => 'loggedin-audio', 'label' => 'Logged-in audio'],
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

        if (!is_string($raw) || trim($raw) === '') {
            continue;
        }
        if (!bandpromo_media_reference_path_matches_prefix($raw, $entry['prefix'])) {
            continue;
        }

        $basename = bandpromo_media_reference_normalize_basename($raw, $entry['prefix']);
        if ($basename === '' || !bandpromo_media_reference_names_match($basename, $filename)) {
            continue;
        }

        if (!bandpromo_media_reference_file_exists($root, $target, $filename)) {
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

/**
 * Scan brand/theme documents for shell media asset paths.
 *
 * @param string $pathPrefix e.g. media/special or media/sfx/original
 * @param string $filesTarget files-index target used for existence checks
 * @return list<array{scope:string,kind:string,label:string,brand_id?:string}>
 */
function bandpromo_media_reference_collect_brand_document_references(
    string $root,
    string $filename,
    string $pathPrefix = 'media/special',
    string $filesTarget = 'special'
): array {
    $safe = basename(trim($filename));
    if ($safe === '') {
        return [];
    }

    require_once __DIR__ . '/theme-storage.php';

    $kindMap = [
        'logo' => ['kind' => 'brand-logo', 'label' => 'Logo'],
        'poster' => ['kind' => 'share-image', 'label' => 'Share image'],
        'background_image' => ['kind' => 'theme-background', 'label' => 'Still background'],
        'background_video' => ['kind' => 'theme-background-video', 'label' => 'Living background'],
        'welcome_audio' => ['kind' => 'welcome-audio', 'label' => 'Welcome audio'],
        'loggedin_audio' => ['kind' => 'loggedin-audio', 'label' => 'Logged-in audio'],
    ];

    $references = [];
    $seen = [];

    try {
        bandpromo_theme_ensure_seeded($root);
        foreach (bandpromo_theme_registry_entries($root) as $registryEntry) {
            if (!is_array($registryEntry)) {
                continue;
            }
            $brandId = trim((string) ($registryEntry['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            try {
                $document = bandpromo_theme_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                continue;
            }
            $brandTitle = trim((string) ($document['title'] ?? $brandId));
            $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
            foreach ($kindMap as $assetKey => $meta) {
                $raw = trim((string) ($assets[$assetKey] ?? ''));
                if ($raw === '' || !bandpromo_media_reference_path_matches_prefix($raw, $pathPrefix)) {
                    continue;
                }
                $basename = bandpromo_media_reference_normalize_basename($raw, $pathPrefix);
                if ($basename === '' || !bandpromo_media_reference_names_match($basename, $safe)) {
                    continue;
                }
                if (!bandpromo_media_reference_file_exists($root, $filesTarget, $safe)) {
                    continue;
                }
                $key = $brandId . '|' . $meta['kind'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $references[] = [
                    'scope' => 'brand',
                    'kind' => $meta['kind'],
                    'label' => ($brandTitle !== '' ? $brandTitle : $brandId) . ' — ' . $meta['label'],
                    'brand_id' => $brandId,
                ];
            }
        }
    } catch (Throwable $throwable) {
        // Theme registry may be missing during early setup.
    }

    return $references;
}

/**
 * Live track assignments from the asset registry (still cover + living cover).
 * Source of truth after track-editor save — not the published playlist payload.
 *
 * @return array{covers: array<string, list<array>>, living_covers: array<string, list<array>>}
 */
function bandpromo_media_reference_build_track_visual_index(string $root): array
{
    static $cache = [];
    if (isset($cache[$root])) {
        return $cache[$root];
    }

    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/living-cover-helpers.php';

    $covers = [];
    $livingCovers = [];

    foreach (bandpromo_asset_load_registry($root)['assets'] as $asset) {
        if (!is_array($asset) || strtolower((string) ($asset['kind'] ?? '')) !== 'audio') {
            continue;
        }

        $display = bandpromo_asset_read_audio_display($asset);
        $masterFile = basename(trim((string) ($asset['master_filename'] ?? '')));
        $label = trim((string) ($display['title'] ?? ''));
        if ($label === '') {
            $label = $masterFile !== '' ? $masterFile : (string) ($asset['id'] ?? 'track');
        }

        $cover = basename(trim((string) ($display['cover'] ?? '')));
        if ($cover !== '') {
            if (!isset($covers[$cover])) {
                $covers[$cover] = [];
            }
            $covers[$cover][] = [
                'scope' => 'track',
                'kind' => 'track-cover',
                'label' => $label,
                'audio_file' => $masterFile,
                'asset_id' => (string) ($asset['id'] ?? ''),
            ];
        }

        $living = bandpromo_living_cover_normalize_video_filename((string) ($display['living_cover'] ?? ''));
        if ($living !== '') {
            if (!isset($livingCovers[$living])) {
                $livingCovers[$living] = [];
            }
            $livingCovers[$living][] = [
                'scope' => 'track',
                'kind' => 'track-living-cover',
                'label' => $label,
                'audio_file' => $masterFile,
                'asset_id' => (string) ($asset['id'] ?? ''),
            ];
        }
    }

    return $cache[$root] = [
        'covers' => $covers,
        'living_covers' => $livingCovers,
    ];
}

function bandpromo_media_reference_collect_track_visual_references(
    string $root,
    string $target,
    string $filename,
    ?array $trackVisualIndex = null
): array {
    $safe = basename(trim($filename));
    if ($safe === '') {
        return [];
    }

    $index = is_array($trackVisualIndex)
        ? $trackVisualIndex
        : bandpromo_media_reference_build_track_visual_index($root);

    if ($target === 'illustrations' || $target === 'photos') {
        return is_array($index['covers'][$safe] ?? null) ? $index['covers'][$safe] : [];
    }

    if ($target === 'video') {
        return is_array($index['living_covers'][$safe] ?? null) ? $index['living_covers'][$safe] : [];
    }

    return [];
}

function bandpromo_media_reference_basename_stem(string $filename): string
{
    return strtolower(pathinfo(basename(trim($filename)), PATHINFO_FILENAME));
}

function bandpromo_media_reference_names_match(string $candidate, string $filename): bool
{
    $candidate = basename(trim($candidate));
    $filename = basename(trim($filename));
    if ($candidate === '' || $filename === '') {
        return false;
    }
    if (strcasecmp($candidate, $filename) === 0) {
        return true;
    }

    $candidateStem = bandpromo_media_reference_basename_stem($candidate);
    $filenameStem = bandpromo_media_reference_basename_stem($filename);

    return $candidateStem !== '' && $candidateStem === $filenameStem;
}

function bandpromo_media_reference_target_for_media_path(string $src): string
{
    $src = str_replace('\\', '/', trim($src));
    if ($src === '') {
        return '';
    }
    if (stripos($src, '/media/video/') !== false) {
        return 'video';
    }
    if (stripos($src, '/media/photo/') !== false) {
        return 'photos';
    }
    if (stripos($src, '/media/img/') !== false) {
        return 'illustrations';
    }

    return '';
}

/**
 * Page editor picture blocks reference /media/{img|photo}/optimal/… basenames.
 *
 * @return array<string, list<array>>
 */
function bandpromo_media_reference_build_page_index(string $root, string $target): array
{
    static $cache = [];
    $cacheKey = $root . '|' . $target;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/page-registry.php';

    $index = [];
    try {
        bandpromo_page_seed_all_if_missing($root);
        $pageIds = bandpromo_page_registry_ids($root);
    } catch (Throwable $throwable) {
        return $cache[$cacheKey] = $index;
    }

    foreach ($pageIds as $pageId) {
        $pageId = trim((string) $pageId);
        if ($pageId === '') {
            continue;
        }

        try {
            $document = bandpromo_page_load_document($root, $pageId);
        } catch (Throwable $throwable) {
            continue;
        }

        $pageTitle = trim((string) ($document['title'] ?? $pageId));
        $blocks = is_array($document['blocks'] ?? null) ? $document['blocks'] : [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            if (!in_array($type, ['picture', 'picture_richtext', 'image'], true)) {
                continue;
            }
            $src = trim((string) ($block['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            if (bandpromo_media_reference_target_for_media_path($src) !== $target) {
                continue;
            }
            $basename = basename($src);
            if ($basename === '') {
                continue;
            }
            if (!isset($index[$basename])) {
                $index[$basename] = [];
            }
            $index[$basename][] = [
                'scope' => 'page',
                'kind' => 'page-image',
                'label' => $pageTitle !== '' ? $pageTitle : $pageId,
                'page_id' => $pageId,
            ];
        }
    }

    return $cache[$cacheKey] = $index;
}

function bandpromo_media_reference_collect_page_references(string $root, string $target, string $filename): array
{
    $safe = basename(trim($filename));
    if ($safe === '' || !in_array($target, ['illustrations', 'photos'], true)) {
        return [];
    }

    $index = bandpromo_media_reference_build_page_index($root, $target);
    $references = [];
    $seen = [];
    $stem = bandpromo_media_reference_basename_stem($safe);

    foreach ($index as $indexedName => $entries) {
        if (!bandpromo_media_reference_names_match((string) $indexedName, $safe)
            && !($stem !== '' && bandpromo_media_reference_basename_stem((string) $indexedName) === $stem)
        ) {
            continue;
        }
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = ($entry['page_id'] ?? '') . '|' . ($entry['kind'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $references[] = $entry;
        }
    }

    return $references;
}

/**
 * Resolve release/playlist poster or press-photo asset ids to candidate basenames.
 *
 * @return list<string>
 */
function bandpromo_media_reference_poster_candidate_filenames(string $root, string $reference): array
{
    $reference = trim($reference);
    if ($reference === '') {
        return [];
    }

    require_once __DIR__ . '/release-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    if (preg_match('#^/media/#', $reference) === 1) {
        $basename = basename($reference);

        return $basename !== '' ? [$basename] : [];
    }

    $asset = null;
    if (bandpromo_asset_is_asset_id($reference)) {
        $asset = bandpromo_asset_lookup_by_id($root, $reference);
    }

    return bandpromo_release_poster_filename_candidates($reference, is_array($asset) ? $asset : null);
}

/**
 * @return list<array{scope:string,kind:string,label:string,container_id?:string}>
 */
function bandpromo_media_reference_collect_poster_references(string $root, string $target, string $filename): array
{
    $safe = basename(trim($filename));
    if ($safe === '' || !in_array($target, ['illustrations', 'photos'], true)) {
        return [];
    }

    require_once __DIR__ . '/release-storage.php';
    require_once __DIR__ . '/playlist-storage.php';

    $references = [];
    $seen = [];

    $appendMatch = static function (array $entry, array $candidates) use (&$references, &$seen, $safe, $root, $target): void {
        if (!bandpromo_media_reference_file_exists($root, $target, $safe)) {
            return;
        }
        $matched = false;
        foreach ($candidates as $candidate) {
            if (bandpromo_media_reference_names_match((string) $candidate, $safe)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return;
        }
        $key = ($entry['kind'] ?? '') . '|' . ($entry['container_id'] ?? '') . '|' . ($entry['label'] ?? '');
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $references[] = $entry;
    };

    try {
        bandpromo_release_ensure_seeded($root);
        foreach (bandpromo_release_registry_entries($root) as $registryEntry) {
            if (!is_array($registryEntry)) {
                continue;
            }
            $releaseId = bandpromo_release_normalize_id((string) ($registryEntry['id'] ?? ''));
            if ($releaseId === '') {
                continue;
            }
            try {
                $document = bandpromo_release_load_document($root, $releaseId);
            } catch (Throwable $throwable) {
                continue;
            }
            $title = trim((string) ($document['title'] ?? $releaseId));
            $posterId = trim((string) ($document['poster_asset_id'] ?? ''));
            if ($posterId !== '') {
                $appendMatch([
                    'scope' => 'release',
                    'kind' => 'release-poster',
                    'label' => $title !== '' ? $title : $releaseId,
                    'container_id' => $releaseId,
                ], bandpromo_media_reference_poster_candidate_filenames($root, $posterId));
            }
            $pressIds = is_array($document['epk']['press_photo_asset_ids'] ?? null)
                ? $document['epk']['press_photo_asset_ids']
                : [];
            foreach ($pressIds as $pressId) {
                $pressId = trim((string) $pressId);
                if ($pressId === '') {
                    continue;
                }
                $appendMatch([
                    'scope' => 'release',
                    'kind' => 'release-press-photo',
                    'label' => ($title !== '' ? $title : $releaseId) . ' (press)',
                    'container_id' => $releaseId,
                ], bandpromo_media_reference_poster_candidate_filenames($root, $pressId));
            }
        }
    } catch (Throwable $throwable) {
        // Release registry may be missing during early setup.
    }

    try {
        bandpromo_playlist_ensure_seeded($root);
        foreach (bandpromo_playlist_registry_entries($root) as $registryEntry) {
            if (!is_array($registryEntry)) {
                continue;
            }
            $playlistId = bandpromo_playlist_normalize_id((string) ($registryEntry['id'] ?? ''));
            if ($playlistId === '') {
                continue;
            }
            try {
                $document = bandpromo_playlist_load_document($root, $playlistId);
            } catch (Throwable $throwable) {
                continue;
            }
            $title = trim((string) ($document['title'] ?? $playlistId));
            $posterId = trim((string) ($document['poster_asset_id'] ?? ''));
            if ($posterId === '') {
                continue;
            }
            $appendMatch([
                'scope' => 'playlist',
                'kind' => 'playlist-poster',
                'label' => $title !== '' ? $title : $playlistId,
                'container_id' => $playlistId,
            ], bandpromo_media_reference_poster_candidate_filenames($root, $posterId));
        }
    } catch (Throwable $throwable) {
        // Playlist registry may be missing during early setup.
    }

    return $references;
}

function bandpromo_media_reference_collect_references(string $root, string $target, string $filename, ?array $galleryReferenceIndex = null, ?array $trackVisualIndex = null): array
{
    if ($target === 'special') {
        $safe = basename($filename);
        if ($safe === '' || $safe === '.' || $safe === '..') {
            return [];
        }
        $references = bandpromo_media_reference_collect_config_references($root, $target, $safe);
        foreach (bandpromo_media_reference_collect_brand_document_references($root, $safe) as $reference) {
            $references[] = $reference;
        }

        return $references;
    }

    if ($target === 'sfx') {
        $safe = basename($filename);
        if ($safe === '' || $safe === '.' || $safe === '..') {
            return [];
        }
        $references = bandpromo_media_reference_collect_config_references($root, $target, $safe);
        foreach (bandpromo_media_reference_collect_brand_document_references(
            $root,
            $safe,
            'media/sfx/original',
            'sfx'
        ) as $reference) {
            $references[] = $reference;
        }

        return $references;
    }

    if ($target === 'illustrations') {
        $safe = basename($filename);
        if (is_array($galleryReferenceIndex)) {
            $references = $galleryReferenceIndex[$safe] ?? [];
        } else {
            $references = bandpromo_media_reference_collect_gallery_references($root, $target, $safe);
        }
        foreach (bandpromo_cover_art_collect_references($root, $filename, $trackVisualIndex) as $reference) {
            $references[] = $reference;
        }
        foreach (bandpromo_media_reference_collect_page_references($root, $target, $filename) as $reference) {
            $references[] = $reference;
        }
        foreach (bandpromo_media_reference_collect_poster_references($root, $target, $filename) as $reference) {
            $references[] = $reference;
        }

        return $references;
    }

    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return [];
    }

    if (is_array($galleryReferenceIndex)) {
        $references = $galleryReferenceIndex[$safe] ?? [];
    } else {
        $references = bandpromo_media_reference_collect_gallery_references($root, $target, $safe);
    }
    foreach (bandpromo_media_reference_collect_config_references($root, $target, $safe) as $reference) {
        $references[] = $reference;
    }
    foreach (bandpromo_media_reference_collect_track_visual_references($root, $target, $safe, $trackVisualIndex) as $reference) {
        $references[] = $reference;
    }
    foreach (bandpromo_media_reference_collect_page_references($root, $target, $safe) as $reference) {
        $references[] = $reference;
    }
    foreach (bandpromo_media_reference_collect_poster_references($root, $target, $safe) as $reference) {
        $references[] = $reference;
    }

    return $references;
}

function bandpromo_media_reference_describe_file(
    string $root,
    string $target,
    string $filename,
    ?array $galleryReferenceIndex = null,
    ?array $playlistCoverContext = null,
    ?array $trackVisualIndex = null
): array {
    if ($target === 'illustrations') {
        $coverInfo = bandpromo_cover_art_describe_file($root, $filename, $playlistCoverContext, $trackVisualIndex);
        $references = bandpromo_media_reference_collect_references(
            $root,
            $target,
            $filename,
            $galleryReferenceIndex,
            $trackVisualIndex
        );
        $safe = basename($filename);
        $orphan = $references === [] && !bandpromo_media_is_bundled_placeholder($safe);
        $regenerable = !empty($coverInfo['regenerable']);
        $role = (string) ($coverInfo['role'] ?? '');
        $safeToDelete = $orphan || ($regenerable && $role !== 'release-fallback');

        return [
            'filename' => (string) ($coverInfo['filename'] ?? $safe),
            'role' => $role,
            'origin' => (string) ($coverInfo['origin'] ?? ''),
            'references' => $references,
            'reference_count' => count($references),
            'orphan' => $orphan,
            'regenerable' => $regenerable,
            'safe_to_delete' => $safeToDelete,
            'linked_audio' => (string) ($coverInfo['linked_audio'] ?? ''),
        ];
    }

    $safe = basename($filename);
    $references = bandpromo_media_reference_collect_references(
        $root,
        $target,
        $safe,
        $galleryReferenceIndex,
        $trackVisualIndex
    );
    $orphan = $references === [] && !bandpromo_media_is_bundled_placeholder($safe);

    $role = '';
    if ($target === 'video') {
        foreach ($references as $reference) {
            if (($reference['kind'] ?? '') === 'track-living-cover') {
                $role = 'living-cover';
                break;
            }
        }
    } elseif ($target === 'special' && $references !== []) {
        $role = (string) ($references[0]['kind'] ?? '');
    }

    return [
        'filename' => $safe,
        'role' => $role,
        'origin' => bandpromo_media_resolved_origin($target, $safe),
        'references' => $references,
        'reference_count' => count($references),
        'orphan' => $orphan,
        'regenerable' => false,
        'safe_to_delete' => $orphan,
        'linked_audio' => '',
    ];
}
