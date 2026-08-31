<?php
declare(strict_types=1);

/**
 * Install-level demo release policy.
 *
 * Prefs live in data/install-preferences.json (not web-config.json):
 * - demo_campaign_id: protected demo campaign from first PCF import (legacy key: demo_release_id)
 * - demo_release_hidden: operator hide toggle
 *
 * Compat: demo_catalog_visible is kept as the inverse of demo_release_hidden
 * for existing admin UI / API clients.
 */

function bandpromo_demo_catalog_preferences_path(string $root): string
{
    return rtrim($root, '/\\') . '/data/install-preferences.json';
}

/**
 * @return array{demo_release_id:string,demo_release_hidden:bool,demo_catalog_visible:bool}
 */
function bandpromo_demo_catalog_default_preferences(): array
{
    return [
        'demo_campaign_id' => '',
        'demo_release_hidden' => false,
        'demo_catalog_visible' => true,
    ];
}

/**
 * @return array{demo_campaign_id:string,demo_release_hidden:bool,demo_catalog_visible:bool}
 */
function bandpromo_demo_catalog_normalize_preferences(array $decoded): array
{
    $prefs = bandpromo_demo_catalog_default_preferences();

    $campaignId = '';
    if (array_key_exists('demo_campaign_id', $decoded)) {
        $campaignId = strtolower(trim((string) $decoded['demo_campaign_id']));
    } elseif (array_key_exists('demo_release_id', $decoded)) {
        $campaignId = strtolower(trim((string) $decoded['demo_release_id']));
    }
    if ($campaignId !== '' && preg_match('/^[a-z][a-z0-9-]{0,47}$/', $campaignId)) {
        $prefs['demo_campaign_id'] = $campaignId;
    }

    if (array_key_exists('demo_release_hidden', $decoded)) {
        $prefs['demo_release_hidden'] = (bool) $decoded['demo_release_hidden'];
    } elseif (array_key_exists('demo_catalog_visible', $decoded)) {
        $prefs['demo_release_hidden'] = !(bool) $decoded['demo_catalog_visible'];
    }

    $prefs['demo_catalog_visible'] = !$prefs['demo_release_hidden'];

    return $prefs;
}

/**
 * @return array{demo_release_id:string,demo_release_hidden:bool,demo_catalog_visible:bool}
 */
function bandpromo_demo_catalog_load_preferences(string $root): array
{
    $path = bandpromo_demo_catalog_preferences_path($root);
    if (!is_file($path)) {
        return bandpromo_demo_catalog_default_preferences();
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return bandpromo_demo_catalog_default_preferences();
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return bandpromo_demo_catalog_default_preferences();
    }

    return bandpromo_demo_catalog_normalize_preferences($decoded);
}

function bandpromo_demo_catalog_save_preferences(string $root, array $preferences): bool
{
    $path = bandpromo_demo_catalog_preferences_path($root);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    $existing = bandpromo_demo_catalog_load_preferences($root);
    $merged = array_merge($existing, $preferences);

    // Keep visible/hidden in sync whichever key the caller set.
    if (array_key_exists('demo_release_hidden', $preferences)) {
        $merged['demo_release_hidden'] = (bool) $preferences['demo_release_hidden'];
        $merged['demo_catalog_visible'] = !$merged['demo_release_hidden'];
    } elseif (array_key_exists('demo_catalog_visible', $preferences)) {
        $merged['demo_catalog_visible'] = (bool) $preferences['demo_catalog_visible'];
        $merged['demo_release_hidden'] = !$merged['demo_catalog_visible'];
    } else {
        $merged['demo_catalog_visible'] = empty($merged['demo_release_hidden']);
        $merged['demo_release_hidden'] = !$merged['demo_catalog_visible'];
    }

    if (array_key_exists('demo_campaign_id', $preferences) || array_key_exists('demo_release_id', $preferences)) {
        $rawId = array_key_exists('demo_campaign_id', $preferences)
            ? $preferences['demo_campaign_id']
            : $preferences['demo_release_id'];
        $campaignId = strtolower(trim((string) $rawId));
        $merged['demo_campaign_id'] = ($campaignId !== '' && preg_match('/^[a-z][a-z0-9-]{0,47}$/', $campaignId))
            ? $campaignId
            : '';
    }

    $payload = bandpromo_demo_catalog_normalize_preferences($merged);
    unset($payload['demo_release_id']);
    $payload['updated_at_utc'] = gmdate('c');

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

/**
 * Derive the install's protected demo release id from on-disk state.
 */
function bandpromo_demo_campaign_derive_id(string $root): string
{
    require_once __DIR__ . '/campaign-storage.php';

    foreach (bandpromo_campaign_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '') {
            continue;
        }
        if (!empty($entry['platform_demo']) || bandpromo_campaign_is_platform_demo($releaseId)) {
            $docPath = bandpromo_campaign_document_path($root, $releaseId);
            if (is_file($docPath)) {
                return $releaseId;
            }
        }
    }

    $fallback = BANDPROMO_RELEASE_DEMO_ID;
    $docPath = bandpromo_campaign_document_path($root, $fallback);
    if (is_file($docPath)) {
        return $fallback;
    }

    return '';
}

/**
 * Ensure prefs have a durable demo_campaign_id (derive + persist when missing).
 *
 * @return array{demo_campaign_id:string,demo_release_hidden:bool,demo_catalog_visible:bool}
 */
function bandpromo_demo_campaign_ensure_preferences(string $root, string $preferredReleaseId = ''): array
{
    $prefs = bandpromo_demo_catalog_load_preferences($root);
    $preferredReleaseId = strtolower(trim($preferredReleaseId));
    if ($preferredReleaseId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $preferredReleaseId)) {
        $preferredReleaseId = '';
    }

    $campaignId = (string) ($prefs['demo_campaign_id'] ?? '');
    if ($campaignId === '') {
        $campaignId = $preferredReleaseId !== '' ? $preferredReleaseId : bandpromo_demo_campaign_derive_id($root);
        if ($campaignId !== '') {
            bandpromo_demo_catalog_save_preferences($root, [
                'demo_campaign_id' => $campaignId,
                'demo_release_hidden' => !empty($prefs['demo_release_hidden']),
            ]);
            $prefs = bandpromo_demo_catalog_load_preferences($root);
        }
    } elseif ($preferredReleaseId !== '' && $campaignId !== $preferredReleaseId) {
        // First successful PCF import wins; do not overwrite an already-persisted id.
    }

    return $prefs;
}

function bandpromo_demo_campaign_id(string $root): string
{
    $prefs = bandpromo_demo_campaign_ensure_preferences($root);

    return (string) ($prefs['demo_campaign_id'] ?? $prefs['demo_release_id'] ?? '');
}

function bandpromo_demo_campaign_is_hidden(string $root): bool
{
    $prefs = bandpromo_demo_campaign_ensure_preferences($root);

    return !empty($prefs['demo_release_hidden']);
}

function bandpromo_demo_catalog_is_visible(string $root): bool
{
    return !bandpromo_demo_campaign_is_hidden($root);
}

function bandpromo_demo_catalog_set_visible(string $root, bool $visible): bool
{
    bandpromo_demo_campaign_ensure_preferences($root);

    return bandpromo_demo_catalog_save_preferences($root, [
        'demo_catalog_visible' => $visible,
        'demo_release_hidden' => !$visible,
    ]);
}

function bandpromo_demo_campaign_set_hidden(string $root, bool $hidden): bool
{
    bandpromo_demo_campaign_ensure_preferences($root);

    return bandpromo_demo_catalog_save_preferences($root, [
        'demo_release_hidden' => $hidden,
        'demo_catalog_visible' => !$hidden,
    ]);
}

function bandpromo_demo_catalog_is_demo_entity_id(string $entityId): bool
{
    $entityId = strtolower(trim($entityId));
    if ($entityId === '') {
        return false;
    }

    // Legacy constant still used by templates / PRP; prefs may match the same id.
    require_once __DIR__ . '/campaign-storage.php';

    return $entityId === BANDPROMO_RELEASE_DEMO_ID;
}

function bandpromo_demo_campaign_matches_entity(string $root, string $entityId): bool
{
    $entityId = strtolower(trim($entityId));
    if ($entityId === '') {
        return false;
    }

    $demoId = bandpromo_demo_campaign_id($root);
    if ($demoId !== '' && $entityId === $demoId) {
        return true;
    }

    return bandpromo_demo_catalog_is_demo_entity_id($entityId);
}

function bandpromo_demo_catalog_entity_is_visible(string $root, string $entityId): bool
{
    if (!bandpromo_demo_campaign_matches_entity($root, $entityId)) {
        return true;
    }

    return bandpromo_demo_catalog_is_visible($root);
}

/**
 * Whether a container owned by release_id should appear while demo is hidden.
 */
function bandpromo_demo_campaign_container_is_visible(string $root, string $containerReleaseId, string $containerId = ''): bool
{
    if (!bandpromo_demo_campaign_is_hidden($root)) {
        return true;
    }

    $demoId = bandpromo_demo_campaign_id($root);
    if ($demoId === '') {
        return true;
    }

    $containerId = strtolower(trim($containerId));
    if ($containerId !== '' && $containerId === $demoId) {
        return false;
    }

    require_once __DIR__ . '/campaign-storage.php';
    $owner = bandpromo_campaign_normalize_id(trim($containerReleaseId));

    return $owner !== $demoId;
}

/**
 * Brand-shell asset kinds/buckets stay visible even when demo campaign is hidden.
 */
function bandpromo_demo_campaign_asset_is_brand_shell(array $asset): bool
{
    $kind = strtolower(trim((string) ($asset['kind'] ?? '')));
    if ($kind === 'sfx') {
        return true;
    }

    $intake = strtolower(trim((string) ($asset['intake_bucket'] ?? '')));
    if ($intake === 'special' || $intake === 'sfx') {
        return true;
    }

    $role = strtolower(trim((string) ($asset['role'] ?? '')));
    if (in_array($role, ['logo', 'poster', 'share', 'background', 'background_image', 'background_video', 'sfx', 'welcome', 'loggedin'], true)) {
        return true;
    }

    return false;
}

/**
 * Map registry asset → Files pool target for membership keys.
 */
function bandpromo_demo_campaign_asset_files_target(array $asset): string
{
    $kind = strtolower(trim((string) ($asset['kind'] ?? '')));
    if ($kind === 'audio') {
        return 'audio';
    }
    if ($kind === 'sfx') {
        return 'sfx';
    }

    $intake = strtolower(trim((string) ($asset['intake_bucket'] ?? '')));
    if ($intake === 'special') {
        return 'special';
    }
    if ($intake === 'sfx') {
        return 'sfx';
    }
    if ($intake === 'photo' || $intake === 'photos') {
        return 'photos';
    }
    if ($intake === 'video') {
        return 'video';
    }
    if ($intake === 'img' || $intake === 'illustrations' || $intake === 'illustration') {
        return 'illustrations';
    }

    $mediaType = strtolower(trim((string) ($asset['media_type'] ?? '')));
    if ($mediaType === 'video') {
        return 'video';
    }

    return 'illustrations';
}

/**
 * @return array<string, array{asset_ids:array<string,true>,file_keys:array<string,true>,files:list<array{asset_id:string,target:string,filename:string}>}>
 */
function &bandpromo_demo_campaign_asset_set_cache(): array
{
    static $cache = [];

    return $cache;
}

/**
 * Request-scoped demo workspace asset set (campaign media + demo brand library/shell).
 *
 * @return array{
 *   asset_ids:array<string,true>,
 *   file_keys:array<string,true>,
 *   files:list<array{asset_id:string,target:string,filename:string,shell:bool}>,
 *   shell_asset_ids:array<string,true>
 * }
 */
function bandpromo_demo_campaign_asset_set(string $root): array
{
    $cache = &bandpromo_demo_campaign_asset_set_cache();
    if (isset($cache[$root]) && is_array($cache[$root])) {
        return $cache[$root];
    }

    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/campaign-package.php';
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/brand-storage.php';

    $empty = [
        'asset_ids' => [],
        'file_keys' => [],
        'files' => [],
        'shell_asset_ids' => [],
    ];

    $demoId = bandpromo_demo_campaign_id($root);
    if ($demoId === '') {
        $cache[$root] = $empty;

        return $empty;
    }

    $demoBrandId = '';
    $shellSlotIds = [];
    try {
        $release = bandpromo_campaign_load_document($root, $demoId);
        $demoBrandId = trim((string) ($release['brand_id'] ?? ''));
        if ($demoBrandId !== '') {
            $brand = bandpromo_brand_load_document($root, $demoBrandId);
            foreach (bandpromo_campaign_visual_shell_slot_asset_ids($root, $brand) as $slotAssetId) {
                $slotAssetId = trim((string) $slotAssetId);
                if ($slotAssetId !== '') {
                    $shellSlotIds[$slotAssetId] = true;
                }
            }
        }
    } catch (Throwable $throwable) {
        // Brand optional.
    }

    $assetIds = [];
    $shellAssetIds = [];
    $fileKeys = [];
    $files = [];

    $addAsset = static function (string $assetId, bool $forceShell = false) use (
        $root,
        &$assetIds,
        &$shellAssetIds,
        &$fileKeys,
        &$files,
        $shellSlotIds
    ): void {
        $assetId = trim($assetId);
        if ($assetId === '') {
            return;
        }

        if (isset($assetIds[$assetId])) {
            if ($forceShell || isset($shellSlotIds[$assetId])) {
                $shellAssetIds[$assetId] = true;
                foreach ($files as $index => $file) {
                    if (($file['asset_id'] ?? '') === $assetId) {
                        $files[$index]['shell'] = true;
                    }
                }
            }

            return;
        }

        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
        if (!is_array($asset)) {
            return;
        }

        $target = bandpromo_demo_campaign_asset_files_target($asset);
        $isShell = $forceShell
            || isset($shellSlotIds[$assetId])
            || bandpromo_demo_campaign_asset_is_brand_shell($asset)
            || $target === 'special'
            || $target === 'sfx';

        $assetIds[$assetId] = true;
        if ($isShell) {
            $shellAssetIds[$assetId] = true;
        }

        foreach (['original_filename', 'master_filename'] as $field) {
            $filename = basename(trim((string) ($asset[$field] ?? '')));
            if ($filename === '') {
                continue;
            }
            $key = $target . '|' . $filename;
            if (isset($fileKeys[$key])) {
                continue;
            }
            $fileKeys[$key] = true;
            $files[] = [
                'asset_id' => $assetId,
                'target' => $target,
                'filename' => $filename,
                'shell' => $isShell,
            ];
        }
    };

    foreach (bandpromo_campaign_collect_asset_ids($root, $demoId) as $assetId) {
        $addAsset((string) $assetId, false);
    }

    // Demo brand library / slots even when collect missed an orphan row.
    if ($demoBrandId !== '') {
        try {
            $brand = bandpromo_brand_load_document($root, $demoBrandId);
            foreach (is_array($brand['library_asset_ids'] ?? null) ? $brand['library_asset_ids'] : [] as $libraryId) {
                $addAsset((string) $libraryId, false);
            }
            foreach (is_array($brand['asset_ids'] ?? null) ? $brand['asset_ids'] : [] as $slotId) {
                $addAsset((string) $slotId, true);
            }
        } catch (Throwable $throwable) {
            // Brand optional.
        }
    }

    // Registry rows still tagged to the demo campaign.
    $registry = bandpromo_asset_load_registry($root);
    foreach (is_array($registry['assets'] ?? null) ? $registry['assets'] : [] as $assetId => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $owner = '';
        if (function_exists('bandpromo_document_campaign_id')) {
            $owner = bandpromo_document_campaign_id($asset);
        } else {
            $owner = trim((string) ($asset['campaign_id'] ?? $asset['release_id'] ?? ''));
        }
        if ($owner !== '' && $owner === $demoId) {
            $addAsset((string) $assetId, false);
        }
    }

    $cache[$root] = [
        'asset_ids' => $assetIds,
        'file_keys' => $fileKeys,
        'files' => $files,
        'shell_asset_ids' => $shellAssetIds,
    ];

    return $cache[$root];
}

function bandpromo_demo_campaign_invalidate_asset_set_cache(?string $root = null): void
{
    $cache = &bandpromo_demo_campaign_asset_set_cache();
    if ($root === null) {
        $cache = [];

        return;
    }
    unset($cache[$root]);
}

/**
 * @param array{asset_ids?:array<string,true>,file_keys?:array<string,true>,shell_asset_ids?:array<string,true>}|null $precomputedSet
 */
function bandpromo_demo_campaign_owns_media_file(
    string $root,
    string $target,
    string $filename,
    ?array $precomputedSet = null
): bool {
    $target = trim($target);
    $filename = basename(trim($filename));
    if ($target === '' || $filename === '') {
        return false;
    }

    $set = is_array($precomputedSet) ? $precomputedSet : bandpromo_demo_campaign_asset_set($root);
    $key = $target . '|' . $filename;
    if (!empty($set['file_keys'][$key])) {
        return true;
    }

    require_once __DIR__ . '/asset-registry.php';
    $asset = bandpromo_asset_lookup_by_original_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_master_filename($root, $filename);
    if (is_array($asset)) {
        $assetId = trim((string) ($asset['id'] ?? ''));
        if ($assetId !== '' && !empty($set['asset_ids'][$assetId])) {
            return true;
        }
    }

    require_once __DIR__ . '/campaign-storage.php';
    $demoId = bandpromo_demo_campaign_id($root);
    if ($demoId === '') {
        return false;
    }
    $releaseId = bandpromo_campaign_id_for_media_file($root, $target, $filename);

    return $releaseId !== '' && $releaseId === $demoId;
}

/**
 * True when any brand that still counts while demo is hidden references this asset.
 * Demo-owned brands (and the locked platform default when it is not Base) do not keep shell media visible.
 */
function bandpromo_demo_asset_referenced_by_any_brand(string $root, string $assetId): bool
{
    $assetId = trim($assetId);
    if ($assetId === '') {
        return false;
    }

    require_once __DIR__ . '/brand-storage.php';
    foreach (bandpromo_brand_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $brandId = trim((string) ($entry['id'] ?? ''));
        if ($brandId === '' || !bandpromo_demo_brand_keeps_shell_visible($root, $brandId)) {
            continue;
        }
        try {
            $document = bandpromo_brand_load_document($root, $brandId);
        } catch (Throwable $throwable) {
            continue;
        }
        foreach (is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : [] as $slotId) {
            if (trim((string) $slotId) === $assetId) {
                return true;
            }
        }
        foreach (is_array($document['library_asset_ids'] ?? null) ? $document['library_asset_ids'] : [] as $libraryId) {
            if (trim((string) $libraryId) === $assetId) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Whether a brand should still surface (and keep shell media visible) while the demo campaign is hidden.
 * Base brand always stays. Platform default and demo-owned brands hide when they are not Base.
 */
function bandpromo_demo_brand_keeps_shell_visible(string $root, string $brandId): bool
{
    require_once __DIR__ . '/brand-storage.php';
    $brandId = bandpromo_brand_canonical_id($brandId);
    if ($brandId === '') {
        return false;
    }

    if (!bandpromo_demo_campaign_is_hidden($root)) {
        return true;
    }

    $baseId = bandpromo_brand_active_canonical_id($root);
    if ($brandId === $baseId) {
        return true;
    }

    if ($brandId === BANDPROMO_BRAND_DEFAULT_ID) {
        return false;
    }

    $demoId = bandpromo_demo_campaign_id($root);
    if ($demoId === '') {
        return true;
    }

    try {
        $document = bandpromo_brand_load_document($root, $brandId);
    } catch (Throwable $throwable) {
        return true;
    }

    require_once __DIR__ . '/campaign-storage.php';
    $owner = bandpromo_document_campaign_id($document);

    return $owner === '' || $owner !== $demoId;
}

/**
 * Admin Branding / PBF export: omit hidden demo brands (Base always kept).
 */
function bandpromo_demo_brand_visible_in_admin(string $root, string $brandId): bool
{
    return bandpromo_demo_brand_keeps_shell_visible($root, $brandId);
}

/**
 * Admin Pages pool: FAQ always stays; demo-owned pages hide with the demo campaign.
 */
function bandpromo_demo_page_visible_in_admin(string $root, string $pageId): bool
{
    require_once __DIR__ . '/page-registry.php';
    require_once __DIR__ . '/page-storage.php';

    $pageId = bandpromo_page_normalize_id($pageId);
    if ($pageId === '' || $pageId === BANDPROMO_PAGE_REQUIRED_ID) {
        return true;
    }

    if (!bandpromo_demo_campaign_is_hidden($root)) {
        return true;
    }

    $owner = '';
    try {
        $document = bandpromo_page_load_document($root, $pageId);
        require_once __DIR__ . '/campaign-storage.php';
        $owner = bandpromo_document_campaign_id($document);
    } catch (Throwable $throwable) {
        $owner = '';
    }

    // Classic Demo PCF pages without a healed campaign_id still hide with the demo.
    if ($owner === '' && in_array($pageId, ['bio', 'gallery'], true)) {
        $owner = bandpromo_demo_campaign_id($root);
    }

    return bandpromo_demo_campaign_container_is_visible($root, $owner, $pageId);
}

/**
 * Non-demo playlist/gallery/page/campaign references keep campaign media visible.
 *
 * @return list<array<string,mixed>>
 */
function bandpromo_demo_media_non_demo_references(
    string $root,
    string $target,
    string $filename
): array {
    require_once __DIR__ . '/media-reference-helpers.php';
    require_once __DIR__ . '/playlist-storage.php';

    $demoId = bandpromo_demo_campaign_id($root);
    $target = trim($target);
    $filename = basename(trim($filename));
    if ($demoId === '' || $target === '' || $filename === '') {
        return [];
    }

    if ($target === 'audio') {
        $references = bandpromo_playlist_collect_audio_references($root, $filename);
    } elseif ($target === 'special' || $target === 'sfx') {
        return [];
    } else {
        $references = bandpromo_media_reference_collect_references($root, $target, $filename);
    }

    $external = [];
    foreach ($references as $reference) {
        if (!is_array($reference)) {
            continue;
        }
        if (bandpromo_demo_campaign_reference_is_demo_owned($root, $reference, $demoId)) {
            continue;
        }
        $external[] = $reference;
    }

    return $external;
}

/**
 * Whether a Files-pool row should be omitted while the demo campaign is hidden.
 * Unused demo workspace media (and unused demo shell) hide; in-use / any-brand stay visible.
 */
function bandpromo_demo_workspace_media_should_hide(
    string $root,
    string $target,
    string $filename,
    ?array $precomputedSet = null
): bool {
    if (!bandpromo_demo_campaign_is_hidden($root)) {
        return false;
    }

    $target = trim($target);
    $filename = basename(trim($filename));
    if ($target === '' || $filename === '') {
        return false;
    }

    $set = is_array($precomputedSet) ? $precomputedSet : bandpromo_demo_campaign_asset_set($root);
    if (!bandpromo_demo_campaign_owns_media_file($root, $target, $filename, $set)) {
        return false;
    }

    require_once __DIR__ . '/asset-registry.php';
    $asset = bandpromo_asset_lookup_by_original_filename($root, $filename)
        ?? bandpromo_asset_lookup_by_master_filename($root, $filename);
    $assetId = is_array($asset) ? trim((string) ($asset['id'] ?? '')) : '';

    $isShell = ($target === 'special' || $target === 'sfx')
        || ($assetId !== '' && !empty($set['shell_asset_ids'][$assetId]))
        || (is_array($asset) && bandpromo_demo_campaign_asset_is_brand_shell($asset));

    if ($isShell) {
        if ($assetId === '') {
            return true;
        }

        return !bandpromo_demo_asset_referenced_by_any_brand($root, $assetId);
    }

    return bandpromo_demo_media_non_demo_references($root, $target, $filename) === [];
}

/**
 * Demo workspace assets that stay visible while hide is on (operator/brand still use them).
 *
 * @return list<array{asset_id:string,target:string,filename:string,kind:string,label:string,container_id:string,scope:string,detail:string,href:string,reason:string}>
 */
function bandpromo_demo_campaign_assets_kept_visible(string $root): array
{
    require_once __DIR__ . '/media-reference-helpers.php';
    require_once __DIR__ . '/playlist-storage.php';

    $demoId = bandpromo_demo_campaign_id($root);
    if ($demoId === '') {
        return [];
    }

    $set = bandpromo_demo_campaign_asset_set($root);
    $kept = [];
    $seen = [];

    foreach ($set['files'] as $file) {
        if (!is_array($file)) {
            continue;
        }
        $target = (string) ($file['target'] ?? '');
        $filename = (string) ($file['filename'] ?? '');
        $assetId = (string) ($file['asset_id'] ?? '');
        $isShell = !empty($file['shell']) || $target === 'special' || $target === 'sfx'
            || ($assetId !== '' && !empty($set['shell_asset_ids'][$assetId]));
        if ($target === '' || $filename === '') {
            continue;
        }

        if ($isShell) {
            if ($assetId === '' || !bandpromo_demo_asset_referenced_by_any_brand($root, $assetId)) {
                continue;
            }
            $dedupe = $assetId . '|shell';
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $kept[] = [
                'asset_id' => $assetId,
                'target' => $target,
                'filename' => $filename,
                'kind' => 'brand-shell',
                'label' => $filename,
                'container_id' => '',
                'scope' => 'brand',
                'detail' => 'Still used by a Brand shell or Brand library — kept visible.',
                'href' => '?tab=content&cntab=branding',
                'reason' => 'brand',
            ];
            continue;
        }

        foreach (bandpromo_demo_media_non_demo_references($root, $target, $filename) as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            $containerId = bandpromo_demo_campaign_reference_owner_id($reference);
            $kind = (string) ($reference['kind'] ?? 'reference');
            $dedupe = $assetId . '|' . $target . '|' . $filename . '|' . $kind . '|' . $containerId;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            $row = [
                'asset_id' => $assetId,
                'target' => $target,
                'filename' => $filename,
                'kind' => $kind,
                'label' => trim((string) ($reference['label'] ?? $containerId)) ?: $filename,
                'container_id' => $containerId,
                'scope' => (string) ($reference['scope'] ?? ''),
                'reason' => 'operator',
            ];
            $row['detail'] = bandpromo_demo_campaign_hide_blocker_detail($row);
            $row['href'] = bandpromo_demo_campaign_hide_blocker_href($row);
            $kept[] = $row;
        }
    }

    return $kept;
}

/**
 * @param array{asset_ids?:array<string,true>,file_keys?:array<string,true>}|null $precomputedSet
 */
function bandpromo_asset_is_in_locked_release(
    string $root,
    string $assetKey,
    ?array $precomputedSet = null
): bool {
    $assetKey = trim($assetKey);
    if ($assetKey === '') {
        return false;
    }

    require_once __DIR__ . '/campaign-storage.php';
    $demoId = bandpromo_demo_campaign_id($root);
    if ($demoId === '') {
        return false;
    }

    try {
        $document = bandpromo_campaign_load_document($root, $demoId);
    } catch (Throwable $throwable) {
        return false;
    }
    if (empty($document['locked'])) {
        return false;
    }

    $set = is_array($precomputedSet) ? $precomputedSet : bandpromo_demo_campaign_asset_set($root);
    if (!empty($set['asset_ids'][$assetKey])) {
        return true;
    }
    if (strpos($assetKey, '|') !== false && !empty($set['file_keys'][$assetKey])) {
        return true;
    }
    if (!empty($set['file_keys']['audio|' . basename($assetKey)])
        || !empty($set['file_keys']['illustrations|' . basename($assetKey)])
        || !empty($set['file_keys']['photos|' . basename($assetKey)])
        || !empty($set['file_keys']['video|' . basename($assetKey)])
    ) {
        return true;
    }

    return false;
}

function bandpromo_demo_campaign_reference_owner_id(array $reference): string
{
    foreach (['playlist_id', 'gallery_id', 'page_id', 'release_id', 'brand_id', 'container_id'] as $field) {
        $value = trim((string) ($reference[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function bandpromo_demo_campaign_reference_is_demo_owned(string $root, array $reference, string $demoId): bool
{
    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $kind = (string) ($reference['kind'] ?? '');
    $scope = (string) ($reference['scope'] ?? '');
    $containerId = trim((string) ($reference['container_id'] ?? ''));

    // Brand/config shell refs are out of hide scope (campaign set excludes them).
    if ($scope === 'config' || strpos($kind, 'theme-') === 0 || strpos($kind, 'brand-') === 0
        || in_array($kind, ['welcome-audio', 'loggedin-audio', 'share-image'], true)
    ) {
        return true;
    }

    $playlistId = bandpromo_playlist_normalize_id((string) ($reference['playlist_id'] ?? ''));
    if ($playlistId === '' && $containerId !== '' && ($kind === 'playlist-poster' || $scope === 'playlist')) {
        $playlistId = bandpromo_playlist_normalize_id($containerId);
    }
    if ($playlistId !== '') {
        if ($playlistId === $demoId) {
            return true;
        }
        try {
            $doc = bandpromo_playlist_load_document($root, $playlistId);

            return bandpromo_campaign_normalize_id(trim((string) ($doc['release_id'] ?? ''))) === $demoId;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    $galleryId = bandpromo_gallery_normalize_id((string) ($reference['gallery_id'] ?? ''));
    if ($galleryId === '' && $containerId !== '' && ($kind === 'gallery-item' || $scope === 'gallery')) {
        $galleryId = bandpromo_gallery_normalize_id($containerId);
    }
    if ($galleryId !== '') {
        if ($galleryId === $demoId) {
            return true;
        }
        try {
            $doc = bandpromo_gallery_load_document($root, $galleryId);

            return bandpromo_campaign_normalize_id(trim((string) ($doc['release_id'] ?? ''))) === $demoId;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    $pageId = trim((string) ($reference['page_id'] ?? ''));
    if ($pageId === '' && $containerId !== '' && (strpos($kind, 'page-') === 0 || $scope === 'page')) {
        $pageId = $containerId;
    }
    if ($pageId !== '') {
        try {
            $doc = bandpromo_page_load_document($root, $pageId);

            return bandpromo_campaign_normalize_id(trim((string) ($doc['release_id'] ?? ''))) === $demoId;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    $releaseId = bandpromo_campaign_normalize_id(trim((string) ($reference['release_id'] ?? '')));
    if ($releaseId === '' && $containerId !== '' && (strpos($kind, 'release-') === 0 || $scope === 'release')) {
        $releaseId = bandpromo_campaign_normalize_id($containerId);
    }
    if ($releaseId === '' && (in_array($kind, ['track-cover', 'track-living-cover'], true) || $scope === 'track')) {
        $audioId = trim((string) ($reference['asset_id'] ?? ''));
        if ($audioId !== '') {
            $audio = bandpromo_asset_lookup_by_id($root, $audioId);
            if (is_array($audio)) {
                $releaseId = bandpromo_campaign_normalize_id(trim((string) ($audio['release_id'] ?? '')));
            }
        }
    }
    if ($releaseId !== '') {
        return $releaseId === $demoId;
    }

    $brandId = trim((string) ($reference['brand_id'] ?? ''));
    if ($brandId !== '') {
        try {
            $brand = bandpromo_brand_load_document($root, $brandId);

            return bandpromo_campaign_normalize_id(trim((string) ($brand['release_id'] ?? ''))) === $demoId;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    // Unknown reference shape — treat as external so hide stays conservative.
    return false;
}

/**
 * Assets that remain visible after hide because an operator container still uses them.
 * Soft inventory only — hide is no longer refused when non-empty.
 *
 * @return list<array{asset_id:string,target:string,filename:string,kind:string,label:string,container_id:string,scope:string,detail:string,href:string}>
 */
function bandpromo_demo_campaign_hide_blockers(string $root): array
{
    $kept = [];
    foreach (bandpromo_demo_campaign_assets_kept_visible($root) as $row) {
        if (!is_array($row) || (($row['reason'] ?? '') === 'brand')) {
            continue;
        }
        unset($row['reason']);
        $kept[] = $row;
    }

    return $kept;
}

function bandpromo_demo_campaign_hide_blocker_detail(array $blocker): string
{
    $kind = (string) ($blocker['kind'] ?? '');
    $label = trim((string) ($blocker['label'] ?? ''));
    $name = $label !== '' ? $label : 'that item';

    switch ($kind) {
        case 'track-cover':
            return 'Track cover on “' . $name . '”. Open Files → Audio, edit that track, and choose a different cover.';
        case 'track-living-cover':
            return 'Living cover on “' . $name . '”. Open Files → Audio, edit that track, and choose a different living cover.';
        case 'playlist-track':
            return 'Demo audio is on playlist “' . $name . '”. Remove it from that playlist or replace it with your own track.';
        case 'playlist-poster':
            return 'Playlist cover on “' . $name . '”. Open Content → Playlists and choose a different cover.';
        case 'gallery-item':
            return 'Used in gallery “' . $name . '”. Open Content → Galleries and remove or replace that item.';
        case 'page-image':
        case 'page-poster':
            return 'Used on page “' . $name . '”. Open Content → Pages and replace that picture.';
        case 'release-poster':
            return 'Campaign cover on “' . $name . '”. Open Content → Catalogue and choose a different cover.';
        case 'release-press-photo':
            return 'Press photo on campaign “' . $name . '”. Replace it in the Campaign editor.';
        default:
            $kindLabel = $kind !== '' ? $kind : 'reference';
            return 'Still assigned on “' . $name . '” (' . $kindLabel . '). Replace that assignment with your own media.';
    }
}

function bandpromo_demo_campaign_hide_blocker_href(array $blocker): string
{
    $kind = (string) ($blocker['kind'] ?? '');
    $id = rawurlencode(trim((string) ($blocker['container_id'] ?? '')));

    if (in_array($kind, ['track-cover', 'track-living-cover'], true)) {
        return '?tab=files&fpanel=audio';
    }
    if ($kind === 'playlist-track' || $kind === 'playlist-poster') {
        return $id !== '' ? ('?tab=content&cntab=playlist&playlist=' . $id) : '?tab=content&cntab=playlist';
    }
    if ($kind === 'gallery-item') {
        return $id !== '' ? ('?tab=content&cntab=gallery&gallery=' . $id) : '?tab=content&cntab=gallery';
    }
    if (strpos($kind, 'page-') === 0) {
        return $id !== '' ? ('?tab=content&cntab=pages&page=' . $id) : '?tab=content&cntab=pages';
    }
    if (strpos($kind, 'release-') === 0) {
        return $id !== '' ? ('?tab=content&cntab=release&release=' . $id) : '?tab=content&cntab=release';
    }

    return '';
}

function bandpromo_demo_catalog_is_operator_campaign_id(string $root, string $releaseId): bool
{
    require_once __DIR__ . '/campaign-storage.php';

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '' || $releaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
        return false;
    }

    $demoId = bandpromo_demo_campaign_id($root);
    if ($demoId !== '' && $releaseId === $demoId) {
        return false;
    }

    return !bandpromo_campaign_is_platform_demo($releaseId);
}

/**
 * Operator campaign ids that already have at least one track.
 *
 * @return list<string>
 */
function bandpromo_demo_catalog_operator_campaign_ids_with_tracks(string $root): array
{
    require_once __DIR__ . '/campaign-storage.php';

    $ids = [];
    foreach (bandpromo_campaign_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = bandpromo_campaign_normalize_id((string) ($entry['id'] ?? ''));
        if (!bandpromo_demo_catalog_is_operator_campaign_id($root, $releaseId)) {
            continue;
        }

        try {
            $document = bandpromo_campaign_load_document($root, $releaseId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (count($document['tracks'] ?? []) <= 0) {
            continue;
        }
        $ids[] = $releaseId;
    }

    return $ids;
}

function bandpromo_demo_catalog_playlist_is_demo_owned(string $root, array $entry, string $demoId = ''): bool
{
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/campaign-storage.php';

    if ($demoId === '') {
        $demoId = bandpromo_demo_campaign_id($root);
    }

    $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
    if ($playlistId !== '') {
        if (($demoId !== '' && $playlistId === $demoId) || bandpromo_demo_catalog_is_demo_entity_id($playlistId)) {
            return true;
        }
    }

    $releaseId = bandpromo_campaign_normalize_id(trim((string) ($entry['release_id'] ?? '')));
    if ($releaseId === '') {
        return false;
    }

    if ($demoId !== '' && $releaseId === $demoId) {
        return true;
    }

    return bandpromo_campaign_is_platform_demo($releaseId);
}

function bandpromo_demo_catalog_entry_campaign_id(string $root, array $entry): string
{
    require_once __DIR__ . '/campaign-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $releaseId = bandpromo_campaign_normalize_id(trim((string) ($entry['release_id'] ?? '')));
    if ($releaseId !== '') {
        return $releaseId;
    }

    $assetId = trim((string) ($entry['asset_id'] ?? ''));
    if ($assetId !== '') {
        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
        if (is_array($asset)) {
            $fromAsset = bandpromo_campaign_normalize_id(trim((string) ($asset['release_id'] ?? '')));
            if ($fromAsset !== '') {
                return $fromAsset;
            }
        }
    }

    $masterFile = basename(trim((string) ($entry['master_file'] ?? '')));
    if ($masterFile === '') {
        return '';
    }

    $asset = bandpromo_asset_lookup_by_master_filename($root, $masterFile);
    if (!is_array($asset)) {
        return '';
    }

    return bandpromo_campaign_normalize_id(trim((string) ($asset['release_id'] ?? '')));
}

/**
 * True only when an operator-created release has at least one track and a
 * non-demo playlist exposes a track from that release.
 */
function bandpromo_demo_catalog_install_has_operator_content(string $root): bool
{
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/campaign-storage.php';

    $operatorReleases = bandpromo_demo_catalog_operator_campaign_ids_with_tracks($root);
    if ($operatorReleases === []) {
        return false;
    }

    $lookup = array_fill_keys($operatorReleases, true);
    $demoId = bandpromo_demo_campaign_id($root);

    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }

        try {
            $document = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }

        $owned = array_merge($entry, [
            'id' => $playlistId,
            'release_id' => (string) ($document['release_id'] ?? ($entry['release_id'] ?? '')),
        ]);
        if (bandpromo_demo_catalog_playlist_is_demo_owned($root, $owned, $demoId)) {
            continue;
        }

        $entries = $document['entries'] ?? [];
        if (!is_array($entries) || $entries === []) {
            continue;
        }

        $owner = bandpromo_campaign_normalize_id(trim((string) ($document['release_id'] ?? '')));
        if ($owner !== '' && isset($lookup[$owner])) {
            return true;
        }

        foreach ($entries as $trackEntry) {
            if (!is_array($trackEntry)) {
                continue;
            }
            $releaseId = bandpromo_demo_catalog_entry_campaign_id($root, $trackEntry);
            if ($releaseId !== '' && isset($lookup[$releaseId])) {
                return true;
            }
        }
    }

    return false;
}

/**
 * If the operator later deletes their campaign, the demo catalogue must return.
 */
function bandpromo_demo_catalog_restore_if_operator_campaign_gone(string $root): bool
{
    if (bandpromo_demo_catalog_is_visible($root)) {
        return false;
    }
    if (bandpromo_demo_catalog_install_has_operator_content($root)) {
        return false;
    }

    return bandpromo_demo_catalog_set_visible($root, true);
}

function bandpromo_demo_catalog_should_suggest_hide(string $root): bool
{
    bandpromo_demo_catalog_restore_if_operator_campaign_gone($root);

    return bandpromo_demo_catalog_is_visible($root)
        && bandpromo_demo_catalog_install_has_operator_content($root);
}
