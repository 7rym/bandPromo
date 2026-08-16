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
        'illustrations' => 'media/visual/original',
        'photos' => 'media/visual/original',
        'video' => 'media/visual/original',
        'special' => 'media/visual/original',
        'sfx' => 'media/sfx/original',
    ];

    return $map[$target] ?? null;
}

/**
 * @return list<string>
 */
function bandpromo_media_reference_legacy_original_prefixes(string $target): array
{
    if ($target === 'illustrations') {
        return ['media/img/original'];
    }
    if ($target === 'photos') {
        return ['media/photo/original'];
    }
    if ($target === 'video') {
        return ['media/video/original'];
    }
    if ($target === 'special') {
        return ['media/special'];
    }

    return [];
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
    if ($expected !== '' && stripos($value, $expected . '/') === 0) {
        return true;
    }

    // Accept legacy intake paths while leftovers remain on disk.
    foreach (['media/img/original', 'media/photo/original', 'media/video/original', 'media/special'] as $legacy) {
        if (stripos($value, $legacy . '/') === 0) {
            return true;
        }
    }

    return false;
}

function bandpromo_media_reference_file_exists(string $root, string $target, string $basename): bool
{
    $basename = basename(trim($basename));
    if ($basename === '' || $basename === '.' || $basename === '..') {
        return false;
    }

    $prefix = bandpromo_media_reference_original_prefix($target);
    if ($prefix !== null && is_file($root . '/' . $prefix . '/' . $basename)) {
        return true;
    }
    foreach (bandpromo_media_reference_legacy_original_prefixes($target) as $legacyPrefix) {
        if (is_file($root . '/' . $legacyPrefix . '/' . $basename)) {
            return true;
        }
    }

    require_once __DIR__ . '/asset-registry.php';
    $asset = bandpromo_asset_lookup_from_media_ref($root, $basename);
    if (!is_array($asset)) {
        return false;
    }

    $kind = strtolower(trim((string) ($asset['kind'] ?? '')));
    if ($kind === 'visual') {
        require_once __DIR__ . '/visual-master-helpers.php';
        $working = bandpromo_visual_working_path($root, $asset);

        return $working !== '' && is_file($working);
    }

    if ($kind === 'sfx') {
        $master = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($master !== '' && is_file($root . '/media/sfx/master/' . $master)) {
            return true;
        }
        $original = basename(trim((string) ($asset['original_filename'] ?? '')));

        return $original !== '' && is_file($root . '/media/sfx/original/' . $original);
    }

    return false;
}

/**
 * Lookup keys for a Visual ref: asset id, original filename, master filename.
 *
 * @return list<string>
 */
function bandpromo_media_reference_visual_alias_keys(string $root, string $ref): array
{
    require_once __DIR__ . '/asset-registry.php';

    $keys = [];
    $add = static function (string $name) use (&$keys): void {
        $name = basename(trim($name));
        if ($name === '' || $name === '.' || $name === '..') {
            return;
        }
        $keys[$name] = true;
    };

    $add($ref);
    $normalized = bandpromo_asset_normalize_media_ref($ref);
    if ($normalized !== '') {
        $add($normalized);
    }

    $asset = bandpromo_asset_lookup_from_media_ref($root, $ref);
    if (is_array($asset) && strtolower((string) ($asset['kind'] ?? '')) === 'visual') {
        $add((string) ($asset['id'] ?? ''));
        $add((string) ($asset['original_filename'] ?? ''));
        $add((string) ($asset['master_filename'] ?? ''));
    }

    return array_keys($keys);
}

/**
 * Registry Visual id for a Files listing name, stored ref, or delivery path.
 * Empty when the row is not a registered Visual (titles are never used).
 */
function bandpromo_media_reference_listing_asset_id(string $root, string $ref): string
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/theme-storage.php';

    $ref = trim($ref);
    if ($ref === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_from_media_ref($root, $ref);
    if (is_array($asset) && strtolower((string) ($asset['kind'] ?? '')) === 'visual') {
        return trim((string) ($asset['id'] ?? ''));
    }

    if (strpos(str_replace('\\', '/', $ref), '/media/') !== false) {
        $fromPath = bandpromo_theme_lookup_asset_id_for_path($root, $ref);
        if ($fromPath !== '') {
            return $fromPath;
        }
    }

    return '';
}

/**
 * True when both refs resolve to the same Visual asset id.
 * Unregistered leftovers with no id do not match by title or stem.
 */
function bandpromo_media_reference_same_visual_asset(string $root, string $left, string $right): bool
{
    $leftId = bandpromo_media_reference_listing_asset_id($root, $left);
    $rightId = bandpromo_media_reference_listing_asset_id($root, $right);

    return $leftId !== '' && $leftId === $rightId;
}

/**
 * Keys used to look up Visual usage. Registered assets match by id only.
 * Unregistered leftovers fall back to the listing basename — never titles.
 *
 * @return list<string>
 */
function bandpromo_media_reference_usage_lookup_keys(string $root, string $ref): array
{
    $assetId = bandpromo_media_reference_listing_asset_id($root, $ref);
    if ($assetId !== '') {
        return [$assetId];
    }

    $safe = basename(trim($ref));
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return [];
    }

    return [$safe];
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

    $itemId = bandpromo_media_reference_gallery_item_asset_id($root, $item);
    $listingId = bandpromo_media_reference_listing_asset_id($root, $safe);
    if ($itemId !== '' && $listingId !== '') {
        return $itemId === $listingId;
    }

    return false;
}

function bandpromo_media_reference_collect_gallery_references(
    string $root,
    string $target,
    string $filename,
    ?array $precomputedIndex = null
): array {
    $index = is_array($precomputedIndex)
        ? $precomputedIndex
        : bandpromo_media_reference_build_gallery_index($root, $target);
    $references = [];
    $seen = [];
    $keys = bandpromo_media_reference_usage_lookup_keys($root, $filename);
    foreach ($keys as $key) {
        $hits = $index[$key] ?? [];
        if (!is_array($hits)) {
            continue;
        }
        foreach ($hits as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            $dedupe = (string) ($reference['kind'] ?? '')
                . '|' . (string) ($reference['gallery_id'] ?? '')
                . '|' . (string) ($reference['label'] ?? '');
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $references[] = $reference;
        }
    }

    return $references;
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

            $assetId = bandpromo_media_reference_gallery_item_asset_id($root, $item);
            $keys = $assetId !== ''
                ? [$assetId]
                : bandpromo_media_reference_gallery_item_candidate_filenames($root, $item);
            if ($keys === []) {
                continue;
            }

            $labelSource = trim((string) ($item['name'] ?? $item['alt'] ?? ''));
            $ref = [
                'scope' => 'gallery',
                'kind' => 'gallery-item',
                'label' => $labelSource !== '' ? $labelSource : ($assetId !== '' ? $assetId : $galleryId),
                'gallery_id' => $galleryId,
            ];
            foreach ($keys as $basename) {
                $basename = basename(trim((string) $basename));
                if ($basename === '' || $basename === '.' || $basename === '..') {
                    continue;
                }
                if (!isset($index[$basename])) {
                    $index[$basename] = [];
                }
                $index[$basename][] = $ref;
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

    $fileId = bandpromo_media_reference_listing_asset_id($root, $filename);
    if ($fileId === '') {
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
        if (!bandpromo_media_reference_same_visual_asset($root, $fileId, $raw)) {
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
 * Brand shell slot assignments keyed by asset id and listing filenames.
 *
 * Library membership is not included — curated is not the same as used.
 *
 * @return array{by_asset_id: array<string, list<array>>, by_filename: array<string, list<array>>}
 */
function bandpromo_media_reference_build_brand_slot_index(string $root): array
{
    static $cache = [];
    if (isset($cache[$root])) {
        return $cache[$root];
    }

    require_once __DIR__ . '/theme-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $kindMap = [
        'logo' => ['kind' => 'brand-logo', 'label' => 'Logo'],
        'poster' => ['kind' => 'share-image', 'label' => 'Share image'],
        'background_image' => ['kind' => 'theme-background', 'label' => 'Still background'],
        'background_video' => ['kind' => 'theme-background-video', 'label' => 'Living background'],
        'welcome_audio' => ['kind' => 'welcome-audio', 'label' => 'Welcome audio'],
        'loggedin_audio' => ['kind' => 'loggedin-audio', 'label' => 'Logged-in audio'],
    ];
    $deliveryBasenames = [
        'thumb.jpg' => true,
        'card.jpg' => true,
        'huge.jpg' => true,
        'thumb.png' => true,
        'card.png' => true,
        'huge.png' => true,
        'poster.jpg' => true,
        'standard-stream.mp4' => true,
    ];

    $byAssetId = [];
    $byFilename = [];

    $indexRef = static function (array $ref, string $assetId, array $names) use (&$byAssetId, &$byFilename): void {
        if ($assetId !== '') {
            $byAssetId[$assetId][] = $ref;
            return;
        }
        foreach ($names as $name) {
            $name = basename(trim((string) $name));
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }
            $byFilename[$name][] = $ref;
        }
    };

    try {
        bandpromo_theme_ensure_seeded($root);
        foreach (bandpromo_theme_registry_entries($root) as $registryEntry) {
            if (!is_array($registryEntry)) {
                continue;
            }
            $brandId = bandpromo_brand_canonical_id((string) ($registryEntry['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            try {
                $document = bandpromo_theme_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                continue;
            }
            $brandTitle = trim((string) ($document['title'] ?? $brandId));
            $assetIds = is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : [];
            $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
            foreach ($kindMap as $slotKey => $meta) {
                $slotAssetId = trim((string) ($assetIds[$slotKey] ?? ''));
                $path = trim((string) ($assets[$slotKey] ?? ''));
                if ($slotAssetId === '' && $path !== '') {
                    $slotAssetId = bandpromo_theme_lookup_asset_id_for_path($root, $path);
                }
                if ($slotAssetId === '' && $path === '') {
                    continue;
                }
                $ref = [
                    'scope' => 'brand',
                    'kind' => $meta['kind'],
                    'label' => ($brandTitle !== '' ? $brandTitle : $brandId) . ' — ' . $meta['label'],
                    'brand_id' => $brandId,
                ];
                $names = [];
                if ($slotAssetId !== '' && bandpromo_asset_is_asset_id($slotAssetId)) {
                    $asset = bandpromo_asset_lookup_by_id($root, $slotAssetId);
                    if (is_array($asset)) {
                        $names[] = (string) ($asset['id'] ?? '');
                        $names[] = (string) ($asset['original_filename'] ?? '');
                        $names[] = (string) ($asset['master_filename'] ?? '');
                    }
                    $indexRef($ref, $slotAssetId, $names);
                    continue;
                }
                $basename = $path !== '' ? basename($path) : '';
                if ($basename !== '' && !isset($deliveryBasenames[strtolower($basename)])) {
                    $indexRef($ref, '', [$basename]);
                }
            }
        }
    } catch (Throwable $throwable) {
        // Theme registry may be missing during early setup.
    }

    return $cache[$root] = [
        'by_asset_id' => $byAssetId,
        'by_filename' => $byFilename,
    ];
}

/**
 * Shell-slot usage for a Files row (track covers / galleries stay on other collectors).
 *
 * @return list<array{scope:string,kind:string,label:string,brand_id:string}>
 */
function bandpromo_media_reference_collect_brand_slot_references(
    string $root,
    string $filename,
    string $assetId = ''
): array {
    $safe = basename(trim($filename));
    $assetId = trim($assetId);
    if ($safe === '' && $assetId === '') {
        return [];
    }

    require_once __DIR__ . '/asset-registry.php';

    if ($assetId === '' && $safe !== '') {
        $asset = bandpromo_asset_lookup_from_media_ref($root, $safe);
        if (is_array($asset)) {
            $assetId = trim((string) ($asset['id'] ?? ''));
        }
    }

    $index = bandpromo_media_reference_build_brand_slot_index($root);
    $references = [];
    $seen = [];
    $append = static function (array $incoming) use (&$references, &$seen): void {
        foreach ($incoming as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            $key = (string) ($reference['brand_id'] ?? '') . '|' . (string) ($reference['kind'] ?? '');
            if ($key === '|' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $references[] = $reference;
        }
    };

    if ($assetId !== '') {
        $append($index['by_asset_id'][$assetId] ?? []);

        return $references;
    }
    if ($safe !== '') {
        $append($index['by_filename'][$safe] ?? []);
    }

    return $references;
}

/**
 * Scan brand/theme documents for shell media asset paths.
 *
 * @param string $pathPrefix unused; kept for callers
 * @param string $filesTarget unused; kept for callers
 * @return list<array{scope:string,kind:string,label:string,brand_id?:string}>
 */
function bandpromo_media_reference_collect_brand_document_references(
    string $root,
    string $filename,
    string $pathPrefix = 'media/special',
    string $filesTarget = 'special'
): array {
    unset($pathPrefix, $filesTarget);

    return bandpromo_media_reference_collect_brand_slot_references($root, $filename);
}

/**
 * Treat curated Brand-library membership as a first-class media reference.
 *
 * @return list<array{scope:string,kind:string,label:string,brand_id:string}>
 */
function bandpromo_media_reference_collect_brand_library_references(string $root, string $assetId): array
{
    $assetId = trim($assetId);
    if ($assetId === '') {
        return [];
    }

    require_once __DIR__ . '/theme-storage.php';
    $references = [];
    try {
        bandpromo_theme_ensure_seeded($root);
        foreach (bandpromo_theme_registry_entries($root) as $registryEntry) {
            if (!is_array($registryEntry)) {
                continue;
            }
            $brandId = bandpromo_brand_canonical_id((string) ($registryEntry['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            $document = bandpromo_theme_load_document($root, $brandId);
            $library = is_array($document['library_asset_ids'] ?? null)
                ? $document['library_asset_ids']
                : [];
            if (!in_array($assetId, $library, true)) {
                continue;
            }
            $references[] = [
                'scope' => 'brand',
                'kind' => 'brand-library',
                'label' => trim((string) ($document['title'] ?? $brandId)) . ' — Brand library',
                'brand_id' => $brandId,
            ];
        }
    } catch (Throwable $throwable) {
        // Brand storage may be unavailable during early setup.
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

        $cover = trim((string) ($display['cover'] ?? ''));
        if ($cover !== '') {
            $coverRef = [
                'scope' => 'track',
                'kind' => 'track-cover',
                'label' => $label,
                'audio_file' => $masterFile,
                'asset_id' => (string) ($asset['id'] ?? ''),
            ];
            foreach (bandpromo_media_reference_usage_lookup_keys($root, $cover) as $key) {
                $covers[$key][] = $coverRef;
            }
        }

        $living = trim((string) ($display['living_cover'] ?? ''));
        if ($living !== '') {
            $livingRef = [
                'scope' => 'track',
                'kind' => 'track-living-cover',
                'label' => $label,
                'audio_file' => $masterFile,
                'asset_id' => (string) ($asset['id'] ?? ''),
            ];
            foreach (bandpromo_media_reference_usage_lookup_keys($root, $living) as $key) {
                $livingCovers[$key][] = $livingRef;
            }
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

    $bucket = '';
    if ($target === 'illustrations' || $target === 'photos') {
        $bucket = 'covers';
    } elseif ($target === 'video') {
        $bucket = 'living_covers';
    }
    if ($bucket === '') {
        return [];
    }

    $references = [];
    $seen = [];
    foreach (bandpromo_media_reference_usage_lookup_keys($root, $safe) as $key) {
        $hits = $index[$bucket][$key] ?? [];
        if (!is_array($hits)) {
            continue;
        }
        foreach ($hits as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            $dedupe = (string) ($reference['kind'] ?? '')
                . '|' . (string) ($reference['audio_file'] ?? '')
                . '|' . (string) ($reference['asset_id'] ?? '')
                . '|' . (string) ($reference['label'] ?? '');
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $references[] = $reference;
        }
    }

    return $references;
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
    if (stripos($src, '/media/visual/') !== false) {
        if (preg_match('/\.(mp4|webm|mov|mkv)$/i', $src) === 1) {
            return 'video';
        }

        return 'illustrations';
    }

    return '';
}

/**
 * Page editor picture blocks and page posters. Index by Visual asset id /
 * original / master — never by delivery basenames such as card.jpg.
 *
 * @return array<string, list<array>>
 */
function bandpromo_media_reference_build_page_index(string $root, string $target = ''): array
{
    static $cache = [];
    if (isset($cache[$root]) && is_array($cache[$root])) {
        return $cache[$root];
    }

    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/page-registry.php';

    $index = [];
    try {
        bandpromo_page_seed_all_if_missing($root);
        $pageIds = bandpromo_page_registry_ids($root);
    } catch (Throwable $throwable) {
        return $cache[$root] = $index;
    }

    $add = static function (array $keys, array $ref) use (&$index): void {
        foreach ($keys as $key) {
            $key = basename(trim((string) $key));
            if ($key === '' || $key === '.' || $key === '..') {
                continue;
            }
            if (!isset($index[$key])) {
                $index[$key] = [];
            }
            $index[$key][] = $ref;
        }
    };

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
        $label = $pageTitle !== '' ? $pageTitle : $pageId;
        $posterId = trim((string) ($document['poster_asset_id'] ?? ''));
        if ($posterId !== '') {
            $add(
                [$posterId],
                [
                    'scope' => 'page',
                    'kind' => 'page-poster',
                    'label' => $label,
                    'page_id' => $pageId,
                ]
            );
        }

        $blocks = is_array($document['blocks'] ?? null) ? $document['blocks'] : [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string) ($block['type'] ?? '');
            if (!in_array($type, ['picture', 'picture_richtext', 'image'], true)) {
                continue;
            }
            $assetId = bandpromo_media_reference_gallery_item_asset_id($root, $block);
            $keys = $assetId !== ''
                ? [$assetId]
                : bandpromo_media_reference_gallery_item_candidate_filenames($root, $block);
            if ($keys === []) {
                continue;
            }
            $add($keys, [
                'scope' => 'page',
                'kind' => 'page-image',
                'label' => $label,
                'page_id' => $pageId,
            ]);
        }
    }

    return $cache[$root] = $index;
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
    foreach (bandpromo_media_reference_usage_lookup_keys($root, $safe) as $key) {
        $entries = $index[$key] ?? [];
        if (!is_array($entries)) {
            continue;
        }
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $dedupe = (string) ($entry['page_id'] ?? '') . '|' . (string) ($entry['kind'] ?? '');
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
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

    $fileId = bandpromo_media_reference_listing_asset_id($root, $safe);
    if ($fileId === '') {
        return [];
    }

    $appendMatch = static function (array $entry, string $ref) use (&$references, &$seen, $root, $fileId): void {
        if (!bandpromo_media_reference_same_visual_asset($root, $fileId, $ref)) {
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
                ], $posterId);
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
                ], $pressId);
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
            ], $posterId);
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
        $references = bandpromo_media_reference_collect_gallery_references(
            $root,
            $target,
            $safe,
            is_array($galleryReferenceIndex) ? $galleryReferenceIndex : null
        );
        foreach (bandpromo_cover_art_collect_references($root, $filename, $trackVisualIndex) as $reference) {
            $references[] = $reference;
        }
        foreach (bandpromo_media_reference_collect_page_references($root, $target, $filename) as $reference) {
            $references[] = $reference;
        }
        foreach (bandpromo_media_reference_collect_poster_references($root, $target, $filename) as $reference) {
            $references[] = $reference;
        }
        foreach (bandpromo_media_reference_collect_brand_slot_references($root, $safe) as $reference) {
            $references[] = $reference;
        }

        return $references;
    }

    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        return [];
    }

    $references = bandpromo_media_reference_collect_gallery_references(
        $root,
        $target,
        $safe,
        is_array($galleryReferenceIndex) ? $galleryReferenceIndex : null
    );
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
    foreach (bandpromo_media_reference_collect_brand_slot_references($root, $safe) as $reference) {
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
