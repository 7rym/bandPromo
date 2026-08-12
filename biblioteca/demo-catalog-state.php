<?php
declare(strict_types=1);

/**
 * Install-level demo release policy.
 *
 * Prefs live in data/install-preferences.json (not web-config.json):
 * - demo_release_id: protected fallback release from first PRP import
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
        'demo_release_id' => '',
        'demo_release_hidden' => false,
        'demo_catalog_visible' => true,
    ];
}

/**
 * @return array{demo_release_id:string,demo_release_hidden:bool,demo_catalog_visible:bool}
 */
function bandpromo_demo_catalog_normalize_preferences(array $decoded): array
{
    $prefs = bandpromo_demo_catalog_default_preferences();

    $releaseId = '';
    if (array_key_exists('demo_release_id', $decoded)) {
        $releaseId = strtolower(trim((string) $decoded['demo_release_id']));
    }
    if ($releaseId !== '' && preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId)) {
        $prefs['demo_release_id'] = $releaseId;
    }

    if (array_key_exists('demo_release_hidden', $decoded)) {
        $prefs['demo_release_hidden'] = (bool) $decoded['demo_release_hidden'];
    } elseif (array_key_exists('demo_catalog_visible', $decoded)) {
        // Migrate legacy visible flag → hidden.
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

    if (array_key_exists('demo_release_id', $preferences)) {
        $releaseId = strtolower(trim((string) $preferences['demo_release_id']));
        $merged['demo_release_id'] = ($releaseId !== '' && preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId))
            ? $releaseId
            : '';
    }

    $payload = bandpromo_demo_catalog_normalize_preferences($merged);
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
function bandpromo_demo_release_derive_id(string $root): string
{
    require_once __DIR__ . '/release-storage.php';

    foreach (bandpromo_release_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = bandpromo_release_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '') {
            continue;
        }
        if (!empty($entry['platform_demo']) || bandpromo_release_is_platform_demo($releaseId)) {
            $docPath = bandpromo_release_document_path($root, $releaseId);
            if (is_file($docPath)) {
                return $releaseId;
            }
        }
    }

    $fallback = BANDPROMO_RELEASE_DEMO_ID;
    $docPath = bandpromo_release_document_path($root, $fallback);
    if (is_file($docPath)) {
        return $fallback;
    }

    return '';
}

/**
 * Ensure prefs have a durable demo_release_id (derive + persist when missing).
 *
 * @return array{demo_release_id:string,demo_release_hidden:bool,demo_catalog_visible:bool}
 */
function bandpromo_demo_release_ensure_preferences(string $root, string $preferredReleaseId = ''): array
{
    $prefs = bandpromo_demo_catalog_load_preferences($root);
    $preferredReleaseId = strtolower(trim($preferredReleaseId));
    if ($preferredReleaseId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $preferredReleaseId)) {
        $preferredReleaseId = '';
    }

    $releaseId = (string) ($prefs['demo_release_id'] ?? '');
    if ($releaseId === '') {
        $releaseId = $preferredReleaseId !== '' ? $preferredReleaseId : bandpromo_demo_release_derive_id($root);
        if ($releaseId !== '') {
            bandpromo_demo_catalog_save_preferences($root, [
                'demo_release_id' => $releaseId,
                'demo_release_hidden' => !empty($prefs['demo_release_hidden']),
            ]);
            $prefs = bandpromo_demo_catalog_load_preferences($root);
        }
    } elseif ($preferredReleaseId !== '' && $releaseId !== $preferredReleaseId) {
        // First successful PRP import wins; do not overwrite an already-persisted id.
    }

    return $prefs;
}

function bandpromo_demo_release_id(string $root): string
{
    $prefs = bandpromo_demo_release_ensure_preferences($root);

    return (string) ($prefs['demo_release_id'] ?? '');
}

function bandpromo_demo_release_is_hidden(string $root): bool
{
    $prefs = bandpromo_demo_release_ensure_preferences($root);

    return !empty($prefs['demo_release_hidden']);
}

function bandpromo_demo_catalog_is_visible(string $root): bool
{
    return !bandpromo_demo_release_is_hidden($root);
}

function bandpromo_demo_catalog_set_visible(string $root, bool $visible): bool
{
    bandpromo_demo_release_ensure_preferences($root);

    return bandpromo_demo_catalog_save_preferences($root, [
        'demo_catalog_visible' => $visible,
        'demo_release_hidden' => !$visible,
    ]);
}

function bandpromo_demo_release_set_hidden(string $root, bool $hidden): bool
{
    bandpromo_demo_release_ensure_preferences($root);

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
    require_once __DIR__ . '/release-storage.php';

    return $entityId === BANDPROMO_RELEASE_DEMO_ID;
}

function bandpromo_demo_release_matches_entity(string $root, string $entityId): bool
{
    $entityId = strtolower(trim($entityId));
    if ($entityId === '') {
        return false;
    }

    $demoId = bandpromo_demo_release_id($root);
    if ($demoId !== '' && $entityId === $demoId) {
        return true;
    }

    return bandpromo_demo_catalog_is_demo_entity_id($entityId);
}

function bandpromo_demo_catalog_entity_is_visible(string $root, string $entityId): bool
{
    if (!bandpromo_demo_release_matches_entity($root, $entityId)) {
        return true;
    }

    return bandpromo_demo_catalog_is_visible($root);
}

/**
 * Whether a container owned by release_id should appear while demo is hidden.
 */
function bandpromo_demo_release_container_is_visible(string $root, string $containerReleaseId, string $containerId = ''): bool
{
    if (!bandpromo_demo_release_is_hidden($root)) {
        return true;
    }

    $demoId = bandpromo_demo_release_id($root);
    if ($demoId === '') {
        return true;
    }

    $containerId = strtolower(trim($containerId));
    if ($containerId !== '' && $containerId === $demoId) {
        return false;
    }

    require_once __DIR__ . '/release-storage.php';
    $owner = bandpromo_release_normalize_id(trim($containerReleaseId));

    return $owner !== $demoId;
}

/**
 * Brand-shell asset kinds/buckets stay visible even when demo campaign is hidden.
 */
function bandpromo_demo_release_asset_is_brand_shell(array $asset): bool
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
function bandpromo_demo_release_asset_files_target(array $asset): string
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
function &bandpromo_demo_release_asset_set_cache(): array
{
    static $cache = [];

    return $cache;
}

/**
 * Request-scoped campaign asset set for the install demo release (excludes brand shell).
 *
 * @return array{asset_ids:array<string,true>,file_keys:array<string,true>,files:list<array{asset_id:string,target:string,filename:string}>}
 */
function bandpromo_demo_release_asset_set(string $root): array
{
    $cache = &bandpromo_demo_release_asset_set_cache();
    if (isset($cache[$root]) && is_array($cache[$root])) {
        return $cache[$root];
    }

    require_once __DIR__ . '/release-storage.php';
    require_once __DIR__ . '/release-campaign-package.php';
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/theme-storage.php';

    $empty = [
        'asset_ids' => [],
        'file_keys' => [],
        'files' => [],
    ];

    $demoId = bandpromo_demo_release_id($root);
    if ($demoId === '') {
        $cache[$root] = $empty;

        return $empty;
    }

    $brandShellIds = [];
    try {
        $release = bandpromo_release_load_document($root, $demoId);
        $brandId = trim((string) ($release['brand_id'] ?? ''));
        if ($brandId !== '') {
            $brand = bandpromo_theme_load_document($root, $brandId);
            foreach (is_array($brand['asset_ids'] ?? null) ? $brand['asset_ids'] : [] as $slotId) {
                $slotId = trim((string) $slotId);
                if ($slotId !== '') {
                    $brandShellIds[$slotId] = true;
                }
            }
        }
    } catch (Throwable $throwable) {
        // Brand optional.
    }

    $assetIds = [];
    $fileKeys = [];
    $files = [];

    foreach (bandpromo_release_campaign_collect_asset_ids($root, $demoId) as $assetId) {
        $assetId = trim((string) $assetId);
        if ($assetId === '' || isset($brandShellIds[$assetId])) {
            continue;
        }

        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
        if (!is_array($asset) || bandpromo_demo_release_asset_is_brand_shell($asset)) {
            continue;
        }

        $assetIds[$assetId] = true;
        $filename = basename(trim((string) ($asset['original_filename'] ?? $asset['master_filename'] ?? '')));
        if ($filename === '') {
            continue;
        }
        $target = bandpromo_demo_release_asset_files_target($asset);
        if ($target === 'special' || $target === 'sfx') {
            continue;
        }
        $key = $target . '|' . $filename;
        if (!isset($fileKeys[$key])) {
            $fileKeys[$key] = true;
            $files[] = [
                'asset_id' => $assetId,
                'target' => $target,
                'filename' => $filename,
            ];
        }
    }

    $cache[$root] = [
        'asset_ids' => $assetIds,
        'file_keys' => $fileKeys,
        'files' => $files,
    ];

    return $cache[$root];
}

function bandpromo_demo_release_invalidate_asset_set_cache(?string $root = null): void
{
    $cache = &bandpromo_demo_release_asset_set_cache();
    if ($root === null) {
        $cache = [];

        return;
    }
    unset($cache[$root]);
}

/**
 * @param array{asset_ids?:array<string,true>,file_keys?:array<string,true>}|null $precomputedSet
 */
function bandpromo_demo_release_owns_media_file(
    string $root,
    string $target,
    string $filename,
    ?array $precomputedSet = null
): bool {
    $target = trim($target);
    $filename = basename(trim($filename));
    if ($target === '' || $filename === '' || $target === 'special' || $target === 'sfx') {
        return false;
    }

    $set = is_array($precomputedSet) ? $precomputedSet : bandpromo_demo_release_asset_set($root);
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

    require_once __DIR__ . '/release-storage.php';
    $demoId = bandpromo_demo_release_id($root);
    if ($demoId === '') {
        return false;
    }
    $releaseId = bandpromo_release_id_for_media_file($root, $target, $filename);

    return $releaseId !== '' && $releaseId === $demoId;
}

/**
 * O(1) membership for delete/edit enforcement against the locked demo campaign set.
 *
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

    require_once __DIR__ . '/release-storage.php';
    $demoId = bandpromo_demo_release_id($root);
    if ($demoId === '') {
        return false;
    }

    try {
        $document = bandpromo_release_load_document($root, $demoId);
    } catch (Throwable $throwable) {
        return false;
    }
    if (empty($document['locked'])) {
        return false;
    }

    $set = is_array($precomputedSet) ? $precomputedSet : bandpromo_demo_release_asset_set($root);
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

function bandpromo_demo_release_reference_owner_id(array $reference): string
{
    foreach (['playlist_id', 'gallery_id', 'page_id', 'release_id', 'brand_id'] as $field) {
        $value = trim((string) ($reference[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function bandpromo_demo_release_reference_is_demo_owned(string $root, array $reference, string $demoId): bool
{
    require_once __DIR__ . '/release-storage.php';
    require_once __DIR__ . '/playlist-storage.php';
    require_once __DIR__ . '/gallery-storage.php';
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/theme-storage.php';

    $kind = (string) ($reference['kind'] ?? '');
    $scope = (string) ($reference['scope'] ?? '');

    // Brand/config shell refs are out of hide scope (campaign set excludes them).
    if ($scope === 'config' || strpos($kind, 'theme-') === 0 || strpos($kind, 'brand-') === 0
        || in_array($kind, ['welcome-audio', 'loggedin-audio', 'share-image'], true)
    ) {
        return true;
    }

    $playlistId = bandpromo_playlist_normalize_id((string) ($reference['playlist_id'] ?? ''));
    if ($playlistId !== '') {
        if ($playlistId === $demoId) {
            return true;
        }
        try {
            $doc = bandpromo_playlist_load_document($root, $playlistId);

            return bandpromo_release_normalize_id(trim((string) ($doc['release_id'] ?? ''))) === $demoId;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    $galleryId = bandpromo_gallery_normalize_id((string) ($reference['gallery_id'] ?? ''));
    if ($galleryId !== '') {
        if ($galleryId === $demoId) {
            return true;
        }
        try {
            $doc = bandpromo_gallery_load_document($root, $galleryId);

            return bandpromo_release_normalize_id(trim((string) ($doc['release_id'] ?? ''))) === $demoId;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    $pageId = trim((string) ($reference['page_id'] ?? ''));
    if ($pageId !== '') {
        try {
            $doc = bandpromo_page_load_document($root, $pageId);

            return bandpromo_release_normalize_id(trim((string) ($doc['release_id'] ?? ''))) === $demoId;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    $releaseId = bandpromo_release_normalize_id(trim((string) ($reference['release_id'] ?? '')));
    if ($releaseId !== '') {
        return $releaseId === $demoId;
    }

    $brandId = trim((string) ($reference['brand_id'] ?? ''));
    if ($brandId !== '') {
        try {
            $brand = bandpromo_theme_load_document($root, $brandId);

            return bandpromo_release_normalize_id(trim((string) ($brand['release_id'] ?? ''))) === $demoId;
        } catch (Throwable $throwable) {
            return false;
        }
    }

    // Unknown reference shape — treat as external so hide stays conservative.
    return false;
}

/**
 * External (non-demo) references to demo campaign assets. Empty ⇒ hide is safe.
 *
 * @return list<array{asset_id:string,target:string,filename:string,kind:string,label:string,container_id:string,scope:string}>
 */
function bandpromo_demo_release_hide_blockers(string $root): array
{
    require_once __DIR__ . '/media-reference-helpers.php';
    require_once __DIR__ . '/playlist-storage.php';

    $demoId = bandpromo_demo_release_id($root);
    if ($demoId === '') {
        return [];
    }

    $set = bandpromo_demo_release_asset_set($root);
    $blockers = [];
    $seen = [];

    foreach ($set['files'] as $file) {
        if (!is_array($file)) {
            continue;
        }
        $target = (string) ($file['target'] ?? '');
        $filename = (string) ($file['filename'] ?? '');
        $assetId = (string) ($file['asset_id'] ?? '');
        if ($target === '' || $filename === '') {
            continue;
        }

        if ($target === 'audio') {
            $references = bandpromo_playlist_collect_audio_references($root, $filename);
        } else {
            $references = bandpromo_media_reference_collect_references($root, $target, $filename);
        }

        foreach ($references as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            if (bandpromo_demo_release_reference_is_demo_owned($root, $reference, $demoId)) {
                continue;
            }

            $containerId = bandpromo_demo_release_reference_owner_id($reference);
            $kind = (string) ($reference['kind'] ?? 'reference');
            $dedupe = $assetId . '|' . $target . '|' . $filename . '|' . $kind . '|' . $containerId;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            $blockers[] = [
                'asset_id' => $assetId,
                'target' => $target,
                'filename' => $filename,
                'kind' => $kind,
                'label' => trim((string) ($reference['label'] ?? $containerId)) ?: $filename,
                'container_id' => $containerId,
                'scope' => (string) ($reference['scope'] ?? ''),
            ];
        }
    }

    return $blockers;
}

function bandpromo_demo_catalog_install_has_operator_content(string $root): bool
{
    require_once __DIR__ . '/media-library-state.php';

    if (bandpromo_media_has_visible_user_uploads('audio')
        || bandpromo_media_has_visible_user_uploads('illustrations')
        || bandpromo_media_has_visible_user_uploads('photos')
        || bandpromo_media_has_visible_user_uploads('special')) {
        return true;
    }

    require_once __DIR__ . '/release-storage.php';
    $demoId = bandpromo_demo_release_id($root);
    foreach (bandpromo_release_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $releaseId = bandpromo_release_normalize_id((string) ($entry['id'] ?? ''));
        if ($releaseId === '' || ($demoId !== '' && $releaseId === $demoId) || bandpromo_demo_catalog_is_demo_entity_id($releaseId)) {
            continue;
        }
        if ($releaseId === BANDPROMO_RELEASE_DEFAULT_ID) {
            continue;
        }
        if ((int) ($entry['track_count'] ?? 0) > 0) {
            return true;
        }
    }

    require_once __DIR__ . '/playlist-storage.php';
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '' || ($demoId !== '' && $playlistId === $demoId) || bandpromo_demo_catalog_is_demo_entity_id($playlistId)) {
            continue;
        }
        if ((int) ($entry['track_count'] ?? 0) > 0) {
            return true;
        }
    }

    require_once __DIR__ . '/gallery-storage.php';
    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '' || ($demoId !== '' && $galleryId === $demoId) || bandpromo_demo_catalog_is_demo_entity_id($galleryId)) {
            continue;
        }
        if (($entry['kind'] ?? 'system') !== 'user') {
            continue;
        }
        if (!bandpromo_gallery_document_is_empty($root, $galleryId)) {
            return true;
        }
    }

    return false;
}

function bandpromo_demo_catalog_should_suggest_hide(string $root): bool
{
    return bandpromo_demo_catalog_is_visible($root)
        && bandpromo_demo_catalog_install_has_operator_content($root);
}
