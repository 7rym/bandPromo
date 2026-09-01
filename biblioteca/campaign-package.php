<?php
declare(strict_types=1);

/**
 * Shared Release campaign package import/export (setup + admin).
 * Export includes masters + campaign docs + asset registry subset (no original/delivery bytes); import merges registries.
 */

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/release-package.php';
require_once __DIR__ . '/campaign-storage.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/gallery-storage.php';
require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/campaign-ownership-helpers.php';

const BANDPROMO_CAMPAIGN_EXPORT_VERSION = 1;
const BANDPROMO_DEMO_CAMPAIGN_MARKER = 'data/demo-release-package.json';
const BANDPROMO_DEMO_CAMPAIGN_WORKDIR = '.bandpromo-demo-release-package';

/**
 * @return array{release_export_version: int, release_id: string, title: string, paths: list<string>}
 */
function bandpromo_campaign_read_manifest(string $packageDir): array
{
    $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'release-package-manifest.json';
    if (!is_file($manifestPath)) {
        throw new RuntimeException('Release package is missing release-package-manifest.json.');
    }
    $decoded = bandpromo_json_read_array_file($manifestPath);
    if ($decoded === null) {
        throw new RuntimeException('Release package manifest is not valid JSON.');
    }
    $version = (int) ($decoded['release_export_version'] ?? 0);
    if ($version !== BANDPROMO_CAMPAIGN_EXPORT_VERSION) {
        throw new RuntimeException(
            'Incompatible release package version ' . $version
            . ' (supported: ' . BANDPROMO_CAMPAIGN_EXPORT_VERSION . '). Upgrade bandPromo, then retry.'
        );
    }
    $releaseId = bandpromo_campaign_normalize_id((string) ($decoded['release_id'] ?? ''));
    if ($releaseId === '') {
        throw new RuntimeException('Release package manifest is missing release_id.');
    }
    $paths = [];
    if (isset($decoded['paths']) && is_array($decoded['paths'])) {
        foreach ($decoded['paths'] as $path) {
            $relative = bandpromo_campaign_normalize_relative_path((string) $path);
            if ($relative !== '') {
                $paths[] = $relative;
            }
        }
    }

    return [
        'release_export_version' => $version,
        'release_id' => $releaseId,
        'title' => trim((string) ($decoded['title'] ?? $releaseId)),
        'paths' => $paths,
    ];
}

function bandpromo_campaign_normalize_relative_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');
    if ($path === '' || str_contains($path, '..')) {
        return '';
    }
    $base = strtolower(basename($path));
    if ($base === 'desktop.ini' || $base === 'thumbs.db' || str_starts_with($base, '.')) {
        return '';
    }
    $allowedPrefixes = [
        'data/campaigns/',
        'data/releases/',
        'data/brands/',
        'data/themes/',
        'data/playlists/',
        'data/galleries/',
        'data/pages/',
        'data/assets/',
        'media/',
        'release-package-manifest.json',
    ];
    foreach ($allowedPrefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix)) {
            if (str_starts_with($path, 'data/') && !str_ends_with(strtolower($path), '.json')) {
                return '';
            }

            return $path;
        }
    }

    return '';
}

function bandpromo_campaign_is_allowed_entry(string $relativePath): bool
{
    return bandpromo_campaign_normalize_relative_path($relativePath) !== '';
}

/**
 * Demo/campaign import may claim the install base brand only on first run.
 * Routine full builds must not reset an operator-chosen base brand (e.g. HITZ).
 */
function bandpromo_campaign_should_claim_active_brand(string $root): bool
{
    require_once __DIR__ . '/config-loader.php';
    require_once __DIR__ . '/brand-storage.php';

    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    $active = bandpromo_brand_canonical_id((string) bandpromo_config_get_path(
        $config,
        'install.pointers.active_brand_id',
        ''
    ));

    return $active === '';
}

/**
 * @param array{
 *   mode?: string,
 *   allow_demo_overwrite?: bool,
 *   set_active_brand?: bool,
 *   collision?: string
 * } $options collision: refuse|overwrite|skip|allocate (skip-existing → skip)
 * @return array{ok: bool, release_id: string, message: string, imported_files: int, ownership: array, collision?: string}
 */
function bandpromo_campaign_import_from_directory(string $root, string $packageDir, array $options = []): array
{
    $packageDir = rtrim($packageDir, "\\/");
    $manifest = bandpromo_campaign_read_manifest($packageDir);
    $rawManifest = bandpromo_json_read_array_file(
        $packageDir . DIRECTORY_SEPARATOR . 'release-package-manifest.json'
    );
    if (is_array($rawManifest) && isset($rawManifest['file_digests']) && is_array($rawManifest['file_digests'])) {
        require_once __DIR__ . '/chunked-upload.php';
        bandpromo_transfer_verify_extracted_digests($packageDir, $rawManifest['file_digests']);
    }
    $mode = strtolower(trim((string) ($options['mode'] ?? 'operator')));
    $allowDemoOverwrite = !empty($options['allow_demo_overwrite']) || $mode === 'demo' || $mode === 'setup';
    $setActiveBrand = array_key_exists('set_active_brand', $options)
        ? !empty($options['set_active_brand'])
        : ($mode === 'demo' || $mode === 'setup');
    if ($setActiveBrand && !bandpromo_campaign_should_claim_active_brand($root)) {
        $setActiveBrand = false;
    }

    $sourceReleaseId = $manifest['release_id'];
    $collision = bandpromo_campaign_normalize_collision((string) ($options['collision'] ?? ''));
    // System/demo/setup always overwrites so delivery can rebuild with stable IDs.
    if ($allowDemoOverwrite && ($collision === '' || $mode === 'demo' || $mode === 'setup')) {
        $collision = 'overwrite';
    }
    if ($collision === '') {
        $collision = 'refuse';
    }

    $exists = bandpromo_campaign_release_exists($root, $sourceReleaseId);
    $targetReleaseId = $sourceReleaseId;
    if ($exists) {
        if ($sourceReleaseId === BANDPROMO_RELEASE_DEMO_ID && !$allowDemoOverwrite && $collision === 'overwrite') {
            throw new RuntimeException(
                'The platform demo release is locked. Duplicate it as a template, or choose skip / allocate.'
            );
        }
        if ($collision === 'refuse') {
            throw new RuntimeException(
                'Release "' . $sourceReleaseId . '" already exists. Choose collision overwrite, skip, or allocate.'
            );
        }
        if ($collision === 'skip') {
            return [
                'ok' => true,
                'release_id' => $sourceReleaseId,
                'message' => 'Skipped import; release "' . $sourceReleaseId . '" already exists.',
                'imported_files' => 0,
                'ownership' => [],
                'collision' => 'skip',
            ];
        }
        if ($collision === 'allocate') {
            $targetReleaseId = bandpromo_campaign_allocate_id(
                $root,
                $manifest['title'] !== '' ? $manifest['title'] : $sourceReleaseId
            );
        }
        // overwrite keeps $sourceReleaseId.
    }

    $remapRelease = $sourceReleaseId !== $targetReleaseId;
    $imported = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packageDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }
        $absolute = $fileInfo->getPathname();
        $relative = str_replace('\\', '/', substr($absolute, strlen($packageDir) + 1));
        $relative = bandpromo_campaign_normalize_relative_path($relative);
        if ($relative === '' || $relative === 'release-package-manifest.json') {
            continue;
        }

        $destinationRelative = $relative;
        if ($remapRelease) {
            if (str_starts_with($relative, 'data/campaigns/' . $sourceReleaseId . '.json')
                || str_starts_with($relative, 'data/releases/' . $sourceReleaseId . '.json')
            ) {
                $destinationRelative = 'data/campaigns/' . $targetReleaseId . '.json';
            }
        }

        // Operator imports must not silently overwrite locked demo identity/containers.
        if (!$allowDemoOverwrite) {
            $baseName = basename($destinationRelative);
            $protected = [
                'bandpromo-default.json',
                'bandpromo-demo.json',
                'setup-default.json',
            ];
            if (in_array($baseName, $protected, true)
                && (str_starts_with($destinationRelative, 'data/brands/')
                    || str_starts_with($destinationRelative, 'data/themes/')
                    || str_starts_with($destinationRelative, 'data/playlists/')
                    || str_starts_with($destinationRelative, 'data/galleries/'))
            ) {
                continue;
            }
        }

        $destination = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $destinationRelative);
        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            throw new RuntimeException('Could not create directory for imported file: ' . $destinationRelative);
        }

        if (str_ends_with(strtolower($destinationRelative), '.json') && str_starts_with($destinationRelative, 'data/')) {
            $decoded = bandpromo_json_read_array_file($absolute);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid JSON in release package: ' . $relative);
            }
            if ($destinationRelative === 'data/assets/registry.json') {
                bandpromo_campaign_merge_asset_registry($root, $decoded);
                $imported++;
                continue;
            }
            $decoded = bandpromo_campaign_remap_document($decoded, $destinationRelative, $sourceReleaseId, $targetReleaseId);
            if (!bandpromo_json_write_file($destination, $decoded)) {
                throw new RuntimeException('Could not write imported JSON: ' . $destinationRelative);
            }
        } else {
            if (!@copy($absolute, $destination)) {
                throw new RuntimeException('Could not copy imported file: ' . $destinationRelative);
            }
        }
        $imported++;
    }

    bandpromo_campaign_ensure_registry_entries($root, $targetReleaseId);
    $ownership = bandpromo_campaign_ownership_migrate($root);

    // Heal gallery asset_ids from delivery src paths (older PRPs shipped empty asset_id).
    $galleryDir = bandpromo_gallery_storage_root($root);
    if (is_dir($galleryDir)) {
        foreach (glob($galleryDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $galleryPath) {
            if (basename($galleryPath) === 'registry.json') {
                continue;
            }
            $decoded = bandpromo_json_read_array_file($galleryPath);
            if (!is_array($decoded)) {
                continue;
            }
            if (trim((string) ($decoded['release_id'] ?? '')) !== $targetReleaseId) {
                continue;
            }
            $healed = bandpromo_campaign_heal_gallery_entries($decoded);
            if ($healed !== $decoded) {
                try {
                    bandpromo_gallery_write_document($root, $healed);
                } catch (Throwable $throwable) {
                    // Non-fatal; delivery rebuild still uses registry masters when present.
                }
            }
        }
    }

    if ($setActiveBrand) {
        try {
            $releaseDoc = bandpromo_campaign_load_document($root, $targetReleaseId);
            $brandId = trim((string) ($releaseDoc['brand_id'] ?? ''));
            if ($brandId !== '') {
                bandpromo_brand_set_active_id($root, $brandId);
            }
        } catch (Throwable $throwable) {
            // Best-effort base brand pointer.
        }
    }

    require_once __DIR__ . '/media-library-state.php';
    try {
        bandpromo_media_files_index_rebuild_all($root);
    } catch (Throwable $throwable) {
        // Listing heals on the next Files GET via ensure_target.
    }

    require_once __DIR__ . '/sfx-helpers.php';
    try {
        bandpromo_sfx_backfill_tiers($root);
    } catch (Throwable $throwable) {
        // SFX delivery can heal on Files list / upload; masters are already imported.
    }

    // Masters-only PRP: thumbs / optimal / streams need a deliverables rebuild on the target.
    require_once __DIR__ . '/build-required.php';
    $buildState = null;
    try {
        $buildState = bandpromo_mark_build_required('release_package_imported');
        $buildState = bandpromo_mark_build_required('media_image_upload');
        $buildState = bandpromo_mark_build_required('media_audio_upload');
        $buildState = bandpromo_mark_build_required('media_video_upload');
    } catch (Throwable $throwable) {
        $buildState = null;
    }

    $imageDeliveryOk = false;
    $deliverablesStarted = false;
    $deliverablesWarning = '';
    try {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // Gallery / cover cards first so Bio and admin thumbs work before the full pipeline.
        require_once __DIR__ . '/light-build-tasks.php';
        $imageTask = bandpromo_run_light_task('scripts/optimizeMedia.py', [
            'BANDPROMO_OPTIMIZE_MODE' => 'image-only',
        ]);
        $imageDeliveryOk = !empty($imageTask['ok']);
        if ($imageDeliveryOk) {
            try {
                require_once __DIR__ . '/media-library-state.php';
                bandpromo_media_files_index_rebuild_target($root, 'illustrations');
                bandpromo_media_files_index_rebuild_target($root, 'photos');
            } catch (Throwable $throwable) {
                // Listing heals on the next Files GET.
            }
            try {
                $buildState = bandpromo_clear_build_required_tasks(['image-delivery']);
            } catch (Throwable $throwable) {
                // Non-fatal.
            }
        } else {
            $deliverablesWarning = 'Image delivery refresh did not finish after import.';
        }

        require_once __DIR__ . '/build-queue-helpers.php';
        $queued = bandpromo_build_try_start($root, [
            'mode' => 'full',
            'profile' => 'deliverables-only',
            'actor' => 'release_package_import',
            'skip_preflight' => true,
        ]);
        $deliverablesStarted = !empty($queued['started']);
        if (!$deliverablesStarted && $deliverablesWarning === '') {
            $deliverablesWarning = trim((string) ($queued['error'] ?? 'Deliverables rebuild did not start automatically.'));
        }
    } catch (Throwable $throwable) {
        if ($deliverablesWarning === '') {
            $deliverablesWarning = 'Post-import deliverables could not be started automatically.';
        }
    }

    $message = $remapRelease
        ? 'Imported release package as ' . $targetReleaseId . '.'
        : 'Imported release package ' . $targetReleaseId . '.';
    if ($imageDeliveryOk && $deliverablesStarted) {
        $message .= ' Gallery images refreshed; deliverables rebuild started — watch System → Deliverables.';
    } elseif ($imageDeliveryOk) {
        $message .= ' Gallery images refreshed. Open System → Deliverables to rebuild audio/streams when ready.';
    } elseif ($deliverablesStarted) {
        $message .= ' Deliverables rebuild started — watch System → Deliverables.';
    } else {
        $message .= ' Rebuild deliverables to refresh thumbs, streams, and playlist files.';
        if ($deliverablesWarning !== '') {
            $message .= ' (' . $deliverablesWarning . ')';
        }
    }

    return [
        'ok' => true,
        'release_id' => $targetReleaseId,
        'message' => $message,
        'imported_files' => $imported,
        'ownership' => $ownership,
        'collision' => $collision,
        'build_required' => !$deliverablesStarted || !$imageDeliveryOk,
        'build_required_state' => $buildState,
        'queue_deliverables' => !$deliverablesStarted,
        'image_delivery_ok' => $imageDeliveryOk,
        'deliverables_started' => $deliverablesStarted,
        'deliverables_warning' => $deliverablesWarning,
    ];
}

function bandpromo_campaign_normalize_collision(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === 'skip-existing') {
        return 'skip';
    }
    if (in_array($value, ['refuse', 'overwrite', 'skip', 'allocate'], true)) {
        return $value;
    }

    return '';
}

function bandpromo_campaign_release_exists(string $root, string $releaseId): bool
{
    $releaseId = bandpromo_campaign_normalize_id($releaseId);

    return $releaseId !== '' && is_file(bandpromo_campaign_document_path($root, $releaseId));
}

/**
 * Install FAQ is system-owned and never travels in a PRP.
 */
function bandpromo_campaign_page_is_portable(string $pageId): bool
{
    require_once __DIR__ . '/page-registry.php';

    $pageId = bandpromo_page_normalize_id($pageId);
    if ($pageId === '' || $pageId === BANDPROMO_PAGE_REQUIRED_ID) {
        return false;
    }

    return true;
}

/**
 * Resolve an asset id from a media path or bare ast_* / filename reference.
 */
function bandpromo_campaign_asset_id_from_media_ref(string $ref): string
{
    require_once __DIR__ . '/asset-registry.php';

    $ref = str_replace('\\', '/', trim($ref));
    if ($ref === '') {
        return '';
    }
    if (bandpromo_asset_is_asset_id($ref)) {
        return $ref;
    }
    if (preg_match('#/(ast_[0-9A-HJKMNP-TV-Z]{20})(?:/|\.|$)#', $ref, $matches) === 1) {
        $candidate = (string) ($matches[1] ?? '');
        return bandpromo_asset_is_asset_id($candidate) ? $candidate : '';
    }
    $base = pathinfo(basename($ref), PATHINFO_FILENAME);
    if (bandpromo_asset_is_asset_id($base)) {
        return $base;
    }

    return '';
}

/**
 * Gallery rows often store delivery URLs with empty asset_id — fill ids so PRP export packs masters.
 *
 * @param array<string, mixed> $document
 * @return array<string, mixed>
 */
function bandpromo_campaign_heal_gallery_entries(array $document): array
{
    if (!isset($document['entries']) || !is_array($document['entries'])) {
        return $document;
    }

    foreach ($document['entries'] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $assetId = trim((string) ($entry['asset_id'] ?? ''));
        if ($assetId !== '' && bandpromo_asset_is_asset_id($assetId)) {
            continue;
        }
        $fromSrc = bandpromo_campaign_asset_id_from_media_ref((string) ($entry['src'] ?? ''));
        if ($fromSrc !== '') {
            $document['entries'][$index]['asset_id'] = $fromSrc;
        }
    }

    return $document;
}

function bandpromo_campaign_bandpromo_version(string $root): string
{
    $path = $root . DIRECTORY_SEPARATOR . 'VERSION';
    if (!is_file($path)) {
        return '';
    }
    $raw = trim((string) @file_get_contents($path));

    return $raw !== '' ? $raw : '';
}

/**
 * Masters-only portable registry rows — delivery is rebuilt on the target host.
 *
 * @param array<string, mixed> $asset
 * @return array<string, mixed>
 */
function bandpromo_campaign_strip_delivery_from_asset(array $asset): array
{
    unset($asset['delivery']);
    $asset['delivery'] = [];

    return $asset;
}

/**
 * @return array<string, mixed>
 */
function bandpromo_campaign_remap_document(
    array $document,
    string $destinationRelative,
    string $sourceReleaseId,
    string $targetReleaseId
): array {
    if (str_starts_with($destinationRelative, 'data/campaigns/')
        || str_starts_with($destinationRelative, 'data/releases/')
    ) {
        $document['id'] = $targetReleaseId;
        $document['slug'] = $targetReleaseId;
        if (trim((string) ($document['title'] ?? '')) === '' || $document['title'] === 'bandPromo demo') {
            $document['title'] = 'bandPromo Demo Release';
        }
        if (trim((string) ($document['brand_id'] ?? '')) === '') {
            $document['brand_id'] = BANDPROMO_BRAND_DEFAULT_ID;
        }
    }
    if (array_key_exists('release_id', $document) && trim((string) $document['release_id']) === $sourceReleaseId) {
        $document['release_id'] = $targetReleaseId;
    }
    if (isset($document['entries']) && is_array($document['entries'])) {
        foreach ($document['entries'] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (trim((string) ($entry['release_id'] ?? '')) === $sourceReleaseId) {
                $document['entries'][$index]['release_id'] = $targetReleaseId;
            }
        }
    }

    return $document;
}

function bandpromo_campaign_allocate_id(string $root, string $title): string
{
    $base = bandpromo_campaign_normalize_id(preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($title))) ?: 'imported-release');
    if ($base === '') {
        $base = 'imported-release';
    }
    $candidate = $base;
    $suffix = 2;
    while (is_file(bandpromo_campaign_document_path($root, $candidate))) {
        $candidate = $base . '-' . $suffix;
        $suffix++;
        if ($suffix > 99) {
            throw new RuntimeException('Could not allocate a free release id for import.');
        }
    }

    return $candidate;
}

/**
 * Merge a packaged asset registry subset into the install registry (no silent wipe).
 *
 * @param array<string, mixed> $incoming
 */
function bandpromo_campaign_merge_asset_registry(string $root, array $incoming): int
{
    require_once __DIR__ . '/asset-registry.php';

    bandpromo_asset_registry_ensure_migrated($root);
    $registry = bandpromo_asset_load_registry($root);
    $incomingAssets = is_array($incoming['assets'] ?? null) ? $incoming['assets'] : [];
    $merged = 0;

    foreach ($incomingAssets as $assetId => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $assetId = trim((string) ($asset['id'] ?? $assetId));
        if (!bandpromo_asset_is_asset_id($assetId)) {
            continue;
        }
        $normalized = bandpromo_asset_normalize_entry(array_merge($asset, ['id' => $assetId]));
        if ($normalized === null) {
            continue;
        }
        // PRP rows are masters-only; keep host-local delivery when the asset already exists.
        $normalized = bandpromo_campaign_strip_delivery_from_asset($normalized);
        $existing = $registry['assets'][$assetId] ?? null;
        if (is_array($existing)) {
            $delivery = is_array($existing['delivery'] ?? null) ? $existing['delivery'] : [];
            $normalized['delivery'] = $delivery;
        }
        $registry['assets'][$assetId] = $normalized;
        if ($normalized['master_filename'] !== '') {
            $registry['by_master_filename'][$normalized['master_filename']] = $assetId;
        }
        if ($normalized['original_filename'] !== '') {
            $registry['by_original_filename'][$normalized['original_filename']] = $assetId;
        }
        $merged++;
    }

    bandpromo_asset_write_registry($root, $registry);

    return $merged;
}

/**
 * Collect asset ids referenced by a release campaign (tracks, covers, brand slots, galleries, pages).
 *
 * @return list<string>
 */
function bandpromo_campaign_collect_asset_ids(string $root, string $releaseId): array
{
    require_once __DIR__ . '/asset-registry.php';

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    $ids = [];
    $add = static function (string $id) use (&$ids): void {
        $id = trim($id);
        if ($id !== '' && bandpromo_asset_is_asset_id($id)) {
            $ids[$id] = true;
        }
    };

    try {
        $release = bandpromo_campaign_load_document($root, $releaseId);
    } catch (Throwable $throwable) {
        return [];
    }

    $add((string) ($release['poster_asset_id'] ?? ''));
    foreach (is_array($release['tracks'] ?? null) ? $release['tracks'] : [] as $track) {
        if (!is_array($track)) {
            continue;
        }
        $add((string) ($track['asset_id'] ?? ''));
    }

    $brandId = trim((string) ($release['brand_id'] ?? ''));
    if ($brandId !== '') {
        try {
            $brand = bandpromo_brand_load_document($root, $brandId);
            foreach (is_array($brand['asset_ids'] ?? null) ? $brand['asset_ids'] : [] as $slotId) {
                $add((string) $slotId);
            }
            foreach (is_array($brand['library_asset_ids'] ?? null) ? $brand['library_asset_ids'] : [] as $libraryId) {
                $add((string) $libraryId);
            }
        } catch (Throwable $throwable) {
            // Brand optional.
        }
    }

    $registry = bandpromo_asset_load_registry($root);
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        if (($asset['kind'] ?? '') === 'audio' && trim((string) ($asset['release_id'] ?? '')) === $releaseId) {
            $add((string) $assetId);
            $display = is_array($asset['display'] ?? null) ? $asset['display'] : [];
            // display.cover / living_cover are filenames (or occasionally asset ids).
            // Resolve to visual asset ids so masters/videos travel in the PRP.
            foreach (['cover', 'living_cover'] as $displayKey) {
                $ref = trim((string) ($display[$displayKey] ?? ''));
                if ($ref === '') {
                    continue;
                }
                if (bandpromo_asset_is_asset_id($ref)) {
                    $add($ref);
                    continue;
                }
                $visual = bandpromo_asset_lookup_from_media_ref($root, $ref);
                if (is_array($visual) && ($visual['kind'] ?? '') === 'visual') {
                    $add((string) ($visual['id'] ?? ''));
                }
            }
        }
    }

    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            $doc = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) !== $releaseId) {
            continue;
        }
        $add((string) ($doc['poster_asset_id'] ?? ''));
        foreach (is_array($doc['entries'] ?? null) ? $doc['entries'] : [] as $row) {
            if (is_array($row)) {
                $add((string) ($row['asset_id'] ?? ''));
            }
        }
    }

    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '') {
            continue;
        }
        try {
            $doc = bandpromo_gallery_load_document($root, $galleryId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) !== $releaseId) {
            continue;
        }
        foreach (is_array($doc['entries'] ?? null) ? $doc['entries'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $assetId = trim((string) ($row['asset_id'] ?? ''));
            if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
                $assetId = bandpromo_campaign_asset_id_from_media_ref((string) ($row['src'] ?? ''));
            }
            $add($assetId);
        }
    }

    foreach (bandpromo_page_registry_ids($root) as $pageId) {
        if (!bandpromo_campaign_page_is_portable($pageId)) {
            continue;
        }
        try {
            $doc = bandpromo_page_load_document($root, $pageId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) !== $releaseId) {
            continue;
        }
        $add((string) ($doc['poster_asset_id'] ?? ''));
        foreach (is_array($doc['blocks'] ?? null) ? $doc['blocks'] : [] as $block) {
            if (!is_array($block)) {
                continue;
            }
            foreach (['asset_id', 'src', 'poster', 'poster_asset_id'] as $field) {
                $ref = trim((string) ($block[$field] ?? ''));
                if ($ref === '') {
                    continue;
                }
                if (bandpromo_asset_is_asset_id($ref)) {
                    $add($ref);
                    continue;
                }
                $visual = bandpromo_asset_lookup_from_media_ref($root, $ref);
                if (is_array($visual) && strtolower((string) ($visual['kind'] ?? '')) === 'visual') {
                    $add((string) ($visual['id'] ?? ''));
                }
            }
        }
    }

    return array_keys($ids);
}

/**
 * Build a release campaign ZIP (masters + campaign docs + registry subset; no originals/delivery).
 *
 * @return array{ok: bool, path: string, release_id: string, files: int, asset_ids: list<string>}
 */
function bandpromo_campaign_export_to_zip(string $root, string $releaseId, string $zipPath): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/visual-master-helpers.php';

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('This host cannot export campaign files.');
    }

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    $release = bandpromo_campaign_load_document($root, $releaseId);

    // Persist gallery asset_ids from delivery src paths before collecting masters.
    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '') {
            continue;
        }
        try {
            $doc = bandpromo_gallery_load_document($root, $galleryId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) !== $releaseId) {
            continue;
        }
        $healed = bandpromo_campaign_heal_gallery_entries($doc);
        if ($healed !== $doc) {
            try {
                bandpromo_gallery_write_document($root, $healed);
            } catch (Throwable $throwable) {
                // Collect still parses src paths below.
            }
        }
    }

    $assetIds = bandpromo_campaign_collect_asset_ids($root, $releaseId);
    $paths = [];
    $addPath = static function (string $relative) use (&$paths, $root): void {
        $relative = bandpromo_campaign_normalize_relative_path($relative);
        if ($relative === '') {
            return;
        }
        $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($absolute)) {
            $paths[$relative] = $absolute;
        }
    };

    $addPath('data/campaigns/' . $releaseId . '.json');
    $addPath('data/releases/' . $releaseId . '.json');
    $brandId = bandpromo_brand_canonical_id((string) ($release['brand_id'] ?? ''));
    if ($brandId !== '') {
        $addPath('data/brands/' . $brandId . '.json');
    }

    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            $doc = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) === $releaseId) {
            $addPath('data/playlists/' . $playlistId . '.json');
        }
    }
    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '') {
            continue;
        }
        try {
            $doc = bandpromo_gallery_load_document($root, $galleryId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) === $releaseId) {
            $addPath('data/galleries/' . $galleryId . '.json');
        }
    }
    foreach (bandpromo_page_registry_ids($root) as $pageId) {
        if (!bandpromo_campaign_page_is_portable($pageId)) {
            continue;
        }
        try {
            $doc = bandpromo_page_load_document($root, $pageId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) === $releaseId) {
            $addPath('data/pages/' . $pageId . '.json');
        }
    }

    $registry = bandpromo_asset_load_registry($root);
    $subsetAssets = [];
    foreach ($assetIds as $assetId) {
        $asset = $registry['assets'][$assetId] ?? null;
        if (!is_array($asset)) {
            continue;
        }
        $subsetAssets[$assetId] = bandpromo_campaign_strip_delivery_from_asset($asset);
        $kind = (string) ($asset['kind'] ?? '');
        if ($kind === 'audio') {
            $master = basename((string) ($asset['master_filename'] ?? ''));
            if ($master !== '') {
                $addPath('media/audio/master/' . $master);
            }
        } elseif ($kind === 'visual') {
            // Masters only — originals stay on the source host (recovery tier).
            $format = strtolower(trim((string) ($asset['master_format'] ?? '')));
            $masterName = basename((string) ($asset['master_filename'] ?? ''));
            if ($format !== '') {
                $addPath('media/visual/master/' . bandpromo_asset_master_filename_for_ulid($assetId, $format));
            } elseif ($masterName !== '') {
                $addPath('media/visual/master/' . $masterName);
            }
        } elseif ($kind === 'sfx') {
            // Masters only — never pack sfx/original. Refuse the row when master is missing.
            $master = basename((string) ($asset['master_filename'] ?? ''));
            $masterReady = $master !== ''
                && bandpromo_asset_is_asset_id((string) pathinfo($master, PATHINFO_FILENAME))
                && is_file($root . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'sfx'
                    . DIRECTORY_SEPARATOR . 'master' . DIRECTORY_SEPARATOR . $master);
            if ($masterReady) {
                $addPath('media/sfx/master/' . $master);
            } else {
                unset($subsetAssets[$assetId]);
            }
        }
    }

    $subset = [
        'version' => (int) ($registry['version'] ?? 1),
        'assets' => $subsetAssets,
        'by_master_filename' => [],
        'by_original_filename' => [],
    ];
    foreach ($subsetAssets as $assetId => $asset) {
        $master = (string) ($asset['master_filename'] ?? '');
        $original = (string) ($asset['original_filename'] ?? '');
        if ($master !== '') {
            $subset['by_master_filename'][$master] = $assetId;
        }
        if ($original !== '') {
            $subset['by_original_filename'][$original] = $assetId;
        }
    }

    $workdir = $root . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . '.bandpromo-release-export-' . $releaseId;
    if (!is_dir($workdir) && !mkdir($workdir, 0750, true) && !is_dir($workdir)) {
        throw new RuntimeException('Could not create export work directory.');
    }
    $assetsDir = $workdir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'assets';
    if (!is_dir($assetsDir) && !mkdir($assetsDir, 0750, true) && !is_dir($assetsDir)) {
        throw new RuntimeException('Could not create export assets directory.');
    }
    $subsetPath = $assetsDir . DIRECTORY_SEPARATOR . 'registry.json';
    if (!bandpromo_json_write_file($subsetPath, $subset)) {
        throw new RuntimeException('Could not write registry subset for export.');
    }
    $paths['data/assets/registry.json'] = $subsetPath;

    require_once __DIR__ . '/chunked-upload.php';
    $fileDigests = bandpromo_transfer_file_digests($paths);

    $manifest = [
        'release_export_version' => BANDPROMO_CAMPAIGN_EXPORT_VERSION,
        'format' => 'pcf',
        'release_id' => $releaseId,
        'title' => (string) ($release['title'] ?? $releaseId),
        'platform_demo' => $releaseId === BANDPROMO_RELEASE_DEMO_ID,
        'bandpromo_version' => bandpromo_campaign_bandpromo_version($root),
        'paths' => array_keys($paths),
        'file_digests' => $fileDigests,
        'asset_ids' => $assetIds,
        'exported_at' => gmdate('c'),
    ];
    $manifestPath = $workdir . DIRECTORY_SEPARATOR . 'release-package-manifest.json';
    if (!bandpromo_json_write_file($manifestPath, $manifest)) {
        throw new RuntimeException('Could not write release-package-manifest.json.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create the campaign file.');
    }
    $zip->addFile($manifestPath, 'release-package-manifest.json');
    foreach ($paths as $relative => $absolute) {
        $zip->addFile($absolute, $relative);
    }
    $zip->close();

    return [
        'ok' => true,
        'path' => $zipPath,
        'release_id' => $releaseId,
        'files' => count($paths) + 1,
        'asset_ids' => $assetIds,
    ];
}

function bandpromo_campaign_ensure_registry_entries(string $root, string $releaseId): void
{
    bandpromo_campaign_ensure_seeded($root);
    bandpromo_brand_ensure_seeded($root);
    bandpromo_playlist_ensure_seeded($root);
    bandpromo_gallery_ensure_seeded($root);
    require_once __DIR__ . '/page-registry.php';
    bandpromo_page_ensure_system_pages($root);

    $releaseId = bandpromo_campaign_normalize_id($releaseId);
    if ($releaseId === '' || !is_file(bandpromo_campaign_document_path($root, $releaseId))) {
        return;
    }

    $document = bandpromo_campaign_load_document($root, $releaseId);
    $registry = bandpromo_campaign_load_registry($root);
    $found = false;
    foreach ($registry['releases'] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((string) ($entry['id'] ?? '') === $releaseId) {
            $registry['releases'][$index]['title'] = (string) ($document['title'] ?? $releaseId);
            $registry['releases'][$index]['slug'] = (string) ($document['slug'] ?? $releaseId);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $registry['releases'][] = [
            'id' => $releaseId,
            'title' => (string) ($document['title'] ?? $releaseId),
            'slug' => (string) ($document['slug'] ?? $releaseId),
            'sort_order' => (count($registry['releases']) + 1) * 10,
            'system' => $releaseId === BANDPROMO_RELEASE_DEMO_ID,
        ];
    }
    bandpromo_campaign_write_registry($root, $registry);

    // Import copies container JSON files; they stay invisible until registry entries exist.
    $playlistRegistry = bandpromo_playlist_load_registry($root);
    $knownPlaylists = [];
    $maxPlaylistOrder = 0;
    foreach ($playlistRegistry['playlists'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pid = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($pid !== '') {
            $knownPlaylists[$pid] = true;
        }
        $maxPlaylistOrder = max($maxPlaylistOrder, (int) ($entry['sort_order'] ?? 0));
    }
    $playlistDir = bandpromo_playlist_storage_root($root);
    if (is_dir($playlistDir)) {
        foreach (glob($playlistDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $playlistPath) {
            if (basename($playlistPath) === 'registry.json') {
                continue;
            }
            $decoded = bandpromo_json_read_array_file($playlistPath);
            if (!is_array($decoded)) {
                continue;
            }
            if (trim((string) ($decoded['release_id'] ?? '')) !== $releaseId) {
                continue;
            }
            $playlistId = bandpromo_playlist_normalize_id((string) ($decoded['id'] ?? pathinfo($playlistPath, PATHINFO_FILENAME)));
            if ($playlistId === '' || isset($knownPlaylists[$playlistId])) {
                continue;
            }
            $kind = strtolower(trim((string) ($decoded['kind'] ?? 'user')));
            if (!in_array($kind, ['system', 'user'], true)) {
                $kind = 'user';
            }
            if ($playlistId !== BANDPROMO_PLAYLIST_DEMO_ID && $kind === 'system') {
                $kind = 'user';
            }
            $maxPlaylistOrder += 10;
            $playlistRegistry['playlists'][] = [
                'id' => $playlistId,
                'title' => (string) ($decoded['title'] ?? $playlistId),
                'kind' => $kind,
                'publish_date' => (string) ($decoded['publish_date'] ?? ''),
                'sort_order' => $maxPlaylistOrder,
            ];
            $knownPlaylists[$playlistId] = true;
        }
        bandpromo_playlist_write_registry($root, $playlistRegistry);
    }

    $rawRelease = bandpromo_json_read_array_file(bandpromo_campaign_document_path($root, $releaseId));
    $brandId = bandpromo_brand_canonical_id((string) ((is_array($rawRelease) ? $rawRelease['brand_id'] : null) ?? ''));
    if ($brandId !== '' && is_file(bandpromo_brand_document_path($root, $brandId))) {
        $brandRegistry = bandpromo_brand_load_registry($root);
        $listKey = bandpromo_brand_registry_list_key($brandRegistry);
        $knownBrands = [];
        $maxBrandOrder = 0;
        foreach ($brandRegistry[$listKey] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $bid = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
            if ($bid !== '') {
                $knownBrands[$bid] = true;
            }
            $maxBrandOrder = max($maxBrandOrder, (int) ($entry['sort_order'] ?? 0));
        }
        if (!isset($knownBrands[$brandId])) {
            try {
                $brandDoc = bandpromo_brand_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                $brandDoc = ['title' => $brandId, 'system' => false, 'locked' => false];
            }
            $brandRegistry[$listKey][] = [
                'id' => $brandId,
                'title' => (string) ($brandDoc['title'] ?? $brandId),
                'system' => !empty($brandDoc['system']),
                'locked' => !empty($brandDoc['locked']),
                'sort_order' => $maxBrandOrder + 10,
            ];
            bandpromo_brand_write_registry($root, $brandRegistry);
        }
    }

    $galleryRegistry = bandpromo_gallery_load_registry($root);
    $knownGalleries = [];
    $maxGalleryOrder = 0;
    foreach ($galleryRegistry['galleries'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $gid = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($gid !== '') {
            $knownGalleries[$gid] = true;
        }
        $maxGalleryOrder = max($maxGalleryOrder, (int) ($entry['sort_order'] ?? 0));
    }
    $galleryDir = bandpromo_gallery_storage_root($root);
    if (is_dir($galleryDir)) {
        foreach (glob($galleryDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $galleryPath) {
            if (basename($galleryPath) === 'registry.json') {
                continue;
            }
            $decoded = bandpromo_json_read_array_file($galleryPath);
            if (!is_array($decoded)) {
                continue;
            }
            if (trim((string) ($decoded['release_id'] ?? '')) !== $releaseId) {
                continue;
            }
            $galleryId = bandpromo_gallery_normalize_id((string) ($decoded['id'] ?? pathinfo($galleryPath, PATHINFO_FILENAME)));
            if ($galleryId === '' || isset($knownGalleries[$galleryId])) {
                continue;
            }
            $kind = strtolower(trim((string) ($decoded['kind'] ?? 'user')));
            if (!in_array($kind, ['system', 'user'], true)) {
                $kind = 'user';
            }
            $maxGalleryOrder += 10;
            $galleryRegistry['galleries'][] = [
                'id' => $galleryId,
                'title' => (string) ($decoded['title'] ?? $galleryId),
                'kind' => $kind,
                'sort_order' => $maxGalleryOrder,
            ];
            $knownGalleries[$galleryId] = true;
        }
        bandpromo_gallery_write_registry($root, $galleryRegistry);
    }

    // Campaign pages are copied as JSON but stay invisible until registry entries exist.
    require_once __DIR__ . '/page-registry.php';
    require_once __DIR__ . '/page-storage.php';
    $pageRegistry = bandpromo_page_load_registry($root);
    $knownPages = [];
    $maxPageOrder = 0;
    foreach ($pageRegistry['pages'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pid = bandpromo_page_normalize_id((string) ($entry['id'] ?? ''));
        if ($pid !== '') {
            $knownPages[$pid] = true;
        }
        $maxPageOrder = max($maxPageOrder, (int) ($entry['sort_order'] ?? 0));
    }
    $pageDir = bandpromo_page_registry_storage_root($root);
    if (is_dir($pageDir)) {
        foreach (glob($pageDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $pagePath) {
            if (basename($pagePath) === 'registry.json') {
                continue;
            }
            $decoded = bandpromo_json_read_array_file($pagePath);
            if (!is_array($decoded)) {
                continue;
            }
            if (trim((string) ($decoded['release_id'] ?? '')) !== $releaseId) {
                continue;
            }
            $pageId = bandpromo_page_normalize_id((string) ($decoded['id'] ?? pathinfo($pagePath, PATHINFO_FILENAME)));
            if ($pageId === ''
                || !bandpromo_campaign_page_is_portable($pageId)
                || isset($knownPages[$pageId])
            ) {
                continue;
            }
            $title = trim((string) ($decoded['title'] ?? $pageId));
            if ($title === '') {
                $title = $pageId;
            }
            $maxPageOrder += 10;
            $normalized = bandpromo_page_normalize_registry_entry([
                'id' => $pageId,
                'title' => $title,
                'label' => $title,
                'surface' => 'player',
                'show_in_player' => true,
                'required' => false,
                'system' => false,
                'sort_order' => $maxPageOrder,
            ], $maxPageOrder);
            if ($normalized === null) {
                continue;
            }
            $pageRegistry['pages'][] = $normalized;
            $knownPages[$pageId] = true;
        }
        bandpromo_page_write_registry($root, $pageRegistry);
    }
}

/**
 * @return array{ok: bool, release_id: string, message: string, imported_files: int, ownership: array}
 */
function bandpromo_campaign_import_from_zip(string $root, string $zipPath, array $options = []): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('This host cannot import campaign files.');
    }
    if (!is_file($zipPath)) {
        throw new RuntimeException('Campaign file is missing.');
    }

    $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_DEMO_CAMPAIGN_WORKDIR . DIRECTORY_SEPARATOR . 'import-' . bin2hex(random_bytes(4));
    bandpromo_release_rrmdir($workDir);
    bandpromo_release_ensure_dir($workDir);

    $zip = new ZipArchive();
    $openStatus = $zip->open($zipPath);
    if ($openStatus !== true) {
        $size = is_file($zipPath) ? (int) filesize($zipPath) : 0;
        $code = is_int($openStatus) ? (string) $openStatus : 'unknown';
        throw new RuntimeException(
            'Could not open the campaign file (status ' . $code
            . ', size ' . $size . ' bytes).'
        );
    }

    try {
        // Stream allowed entries to disk. getFromIndex() loads each master into PHP memory and
        // OOMs large campaign PRPs on shared hosts (bare "Import failed" with no JSON body).
        $allowedNames = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !isset($stat['name'])) {
                continue;
            }
            $rawName = (string) $stat['name'];
            $name = str_replace('\\', '/', ltrim($rawName, '/'));
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            if (str_contains($name, '..') || !bandpromo_campaign_is_allowed_entry($name)) {
                continue;
            }
            $allowedNames[] = $rawName;
        }

        if ($allowedNames === []) {
            throw new RuntimeException('The campaign file has no importable entries.');
        }
        if ($zip->extractTo($workDir, $allowedNames) !== true) {
            throw new RuntimeException('Could not extract release package contents.');
        }
        foreach ($allowedNames as $rawName) {
            $name = str_replace('\\', '/', ltrim((string) $rawName, '/'));
            $target = $workDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (!is_file($target)) {
                throw new RuntimeException('Could not extract: ' . $name);
            }
        }
    } finally {
        $zip->close();
    }

    try {
        return bandpromo_campaign_import_from_directory($root, $workDir, $options);
    } finally {
        bandpromo_release_rrmdir($workDir);
    }
}

/**
 * @deprecated Demo arrives via published PRP only. Kept so older callers do not fatally error.
 *
 * @return array{ok: bool, release_id: string, message: string, imported_files: int, ownership: array}
 */
function bandpromo_campaign_seed_demo_from_templates(string $root): array
{
    bandpromo_campaign_ownership_migrate($root);
    bandpromo_campaign_enforce_platform_demo_lock($root);

    return [
        'ok' => is_file($root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'releases'
            . DIRECTORY_SEPARATOR . BANDPROMO_RELEASE_DEMO_ID . '.json'),
        'release_id' => BANDPROMO_RELEASE_DEMO_ID,
        'message' => 'Template demo seed disabled — use bandPromo-demo.pcf via setup.',
        'imported_files' => 0,
        'ownership' => [],
    ];
}

function bandpromo_campaign_demo_marker_path(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, BANDPROMO_DEMO_CAMPAIGN_MARKER);
}

/**
 * Setup/build path: prefer published Demo Release package; dual-read default-theme media ZIP.
 *
 * @return array{installed: bool, source: string, release_id: string, version?: string, message: string}
 */
/**
 * Duplicate a release campaign: new container ids, shared media asset_ids.
 *
 * @return array{
 *   ok: bool,
 *   release_id: string,
 *   brand_id: string,
 *   playlists: list<string>,
 *   galleries: list<string>,
 *   pages: list<string>,
 *   message: string
 * }
 */
function bandpromo_campaign_duplicate(string $root, string $sourceReleaseId, string $title = ''): array
{
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/page-registry.php';
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/page-blocks.php';

    $sourceReleaseId = bandpromo_campaign_normalize_id($sourceReleaseId);
    if ($sourceReleaseId === '' || $sourceReleaseId === BANDPROMO_CAMPAIGN_DEFAULT_ID) {
        throw new InvalidArgumentException('Cannot duplicate the Primary orphan bucket.');
    }

    $source = bandpromo_campaign_load_document($root, $sourceReleaseId);
    $newTitle = trim($title);
    if ($newTitle === '') {
        $baseTitle = trim((string) ($source['title'] ?? ''));
        $newTitle = $baseTitle !== '' ? $baseTitle . ' copy' : 'Release copy';
    }

    $releaseEntry = bandpromo_campaign_create($root, $newTitle, '');
    $newReleaseId = bandpromo_campaign_normalize_id((string) ($releaseEntry['id'] ?? ''));
    if ($newReleaseId === '') {
        throw new RuntimeException('Could not allocate a release id for the duplicate.');
    }

    $sourceBrandId = bandpromo_brand_canonical_id((string) ($source['brand_id'] ?? ''));
    $newBrandId = '';
    if ($sourceBrandId !== '') {
        $sourceBrand = bandpromo_brand_load_document($root, $sourceBrandId);
        $newBrandId = bandpromo_brand_allocate_duplicate_id($root, $newTitle);
        $dupBrand = bandpromo_brand_normalize_document([
            'id' => $newBrandId,
            'title' => $newTitle,
            'system' => false,
            'locked' => false,
            'mood' => $sourceBrand['mood'] ?? '',
            'keywords' => $sourceBrand['keywords'] ?? [],
            'tone_notes' => $sourceBrand['tone_notes'] ?? '',
            'tokens' => is_array($sourceBrand['tokens'] ?? null) ? $sourceBrand['tokens'] : [],
            'asset_ids' => is_array($sourceBrand['asset_ids'] ?? null) ? $sourceBrand['asset_ids'] : [],
            'library_asset_ids' => is_array($sourceBrand['library_asset_ids'] ?? null)
                ? $sourceBrand['library_asset_ids']
                : [],
            'assets' => is_array($sourceBrand['assets'] ?? null) ? $sourceBrand['assets'] : [],
        ], $newBrandId);
        bandpromo_json_write_file(bandpromo_brand_document_path($root, $newBrandId), $dupBrand);
        $registry = bandpromo_brand_load_registry($root);
        $registry['brands'][] = [
            'id' => $newBrandId,
            'title' => (string) ($dupBrand['title'] ?? $newTitle),
            'system' => false,
            'locked' => false,
            'sort_order' => 50,
        ];
        bandpromo_brand_write_registry($root, $registry);
    }

    $dupRelease = $source;
    $dupRelease['id'] = $newReleaseId;
    $dupRelease['slug'] = $newReleaseId;
    $dupRelease['title'] = $newTitle;
    $dupRelease['brand_id'] = $newBrandId !== '' ? $newBrandId : $sourceBrandId;
    $dupRelease['system'] = false;
    $dupRelease['locked'] = false;
    bandpromo_campaign_write_document($root, $dupRelease);

    $galleryMap = [];
    $galleryIds = [];
    foreach (bandpromo_gallery_registry_entries($root) as $entry) {
        $galleryId = bandpromo_gallery_normalize_id((string) ($entry['id'] ?? ''));
        if ($galleryId === '') {
            continue;
        }
        try {
            $doc = bandpromo_gallery_load_document($root, $galleryId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) !== $sourceReleaseId) {
            continue;
        }
        $gTitle = trim((string) ($doc['title'] ?? $galleryId));
        if ($gTitle === '') {
            $gTitle = $newTitle . ' gallery';
        }
        $created = bandpromo_gallery_create($root, $gTitle . ' copy', '');
        $newGalleryId = bandpromo_gallery_normalize_id((string) ($created['id'] ?? ''));
        if ($newGalleryId === '') {
            continue;
        }
        $dupGallery = $doc;
        $dupGallery['id'] = $newGalleryId;
        $dupGallery['title'] = $gTitle . (stripos($gTitle, 'copy') === false ? ' copy' : '');
        $dupGallery['release_id'] = $newReleaseId;
        $dupGallery['kind'] = 'user';
        $dupGallery = bandpromo_gallery_normalize_document($dupGallery, $newGalleryId);
        bandpromo_gallery_write_document($root, $dupGallery);
        $galleryMap[$galleryId] = $newGalleryId;
        $galleryIds[] = $newGalleryId;
    }

    $playlistIds = [];
    foreach (bandpromo_playlist_registry_entries($root) as $entry) {
        $playlistId = bandpromo_playlist_normalize_id((string) ($entry['id'] ?? ''));
        if ($playlistId === '') {
            continue;
        }
        try {
            $doc = bandpromo_playlist_load_document($root, $playlistId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) !== $sourceReleaseId) {
            continue;
        }
        $pTitle = trim((string) ($doc['title'] ?? $playlistId));
        if ($pTitle === '') {
            $pTitle = $newTitle;
        }
        // Prefer source public slug (or source id) for the new storage id — not "Title copy".
        $sourceSlug = bandpromo_playlist_route_slug($doc, $playlistId);
        $preferredId = $sourceSlug !== '' ? $sourceSlug : $playlistId;
        $created = bandpromo_playlist_create($root, $pTitle . ' copy', $preferredId);
        $newPlaylistId = bandpromo_playlist_normalize_id((string) ($created['id'] ?? ''));
        if ($newPlaylistId === '') {
            continue;
        }
        $entries = [];
        foreach (is_array($doc['entries'] ?? null) ? $doc['entries'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['release_id'] = $newReleaseId;
            $entries[] = $row;
        }
        $dupPlaylist = $doc;
        $dupPlaylist['id'] = $newPlaylistId;
        $dupPlaylist['title'] = $pTitle . (stripos($pTitle, 'copy') === false ? ' copy' : '');
        $dupPlaylist['release_id'] = $newReleaseId;
        $dupPlaylist['entries'] = $entries;
        // Keep a usable public slug; collide with -copy / -2 when the source slug is taken.
        $slugBase = $sourceSlug !== '' ? $sourceSlug : $newPlaylistId;
        $slug = $slugBase;
        $slugSuffix = 2;
        while (true) {
            try {
                bandpromo_playlist_assert_slug_available($root, $slug, $newPlaylistId);
                break;
            } catch (Throwable $throwable) {
                $slug = substr($slugBase, 0, 44) . '-' . $slugSuffix;
                $slugSuffix++;
                if ($slugSuffix > 99) {
                    $slug = $newPlaylistId;
                    break;
                }
            }
        }
        $dupPlaylist['slug'] = $slug;
        $dupPlaylist = bandpromo_playlist_clear_player_payload_fields($dupPlaylist);
        bandpromo_playlist_write_document($root, $dupPlaylist);
        $playlistIds[] = $newPlaylistId;
    }

    $pageIds = [];
    foreach (bandpromo_page_registry_ids($root) as $pageId) {
        if (!bandpromo_campaign_page_is_portable($pageId)) {
            continue;
        }
        try {
            $doc = bandpromo_page_load_document($root, $pageId);
        } catch (Throwable $throwable) {
            continue;
        }
        if (trim((string) ($doc['release_id'] ?? '')) !== $sourceReleaseId) {
            continue;
        }
        $pTitle = trim((string) ($doc['title'] ?? $pageId));
        if ($pTitle === '') {
            $pTitle = $newTitle . ' page';
        }
        $label = trim((string) ($doc['label'] ?? $pTitle));
        $created = bandpromo_page_create_page($root, $pTitle . ' copy', $label !== '' ? $label : $pTitle, $pageId . '-copy');
        $newPageId = bandpromo_page_normalize_id((string) ($created['id'] ?? ''));
        if ($newPageId === '') {
            continue;
        }
        $blocks = is_array($doc['blocks'] ?? null) ? $doc['blocks'] : [];
        foreach ($blocks as $index => $block) {
            if (!is_array($block) || ($block['type'] ?? '') !== 'gallery') {
                continue;
            }
            $oldGallery = bandpromo_gallery_normalize_id((string) ($block['gallery_id'] ?? ''));
            if ($oldGallery !== '' && isset($galleryMap[$oldGallery])) {
                $blocks[$index]['gallery_id'] = $galleryMap[$oldGallery];
            }
        }
        $dupPage = $doc;
        $dupPage['id'] = $newPageId;
        $dupPage['title'] = $pTitle . (stripos($pTitle, 'copy') === false ? ' copy' : '');
        $dupPage['release_id'] = $newReleaseId;
        $dupPage['blocks'] = $blocks;
        $dupPage = bandpromo_page_normalize_document($dupPage, $newPageId);
        bandpromo_page_write_json($root, $dupPage);
        $pageIds[] = $newPageId;
    }

    bandpromo_campaign_ensure_registry_entries($root, $newReleaseId);
    bandpromo_campaign_ownership_migrate($root);

    return [
        'ok' => true,
        'release_id' => $newReleaseId,
        'brand_id' => $newBrandId,
        'playlists' => $playlistIds,
        'galleries' => $galleryIds,
        'pages' => $pageIds,
        'message' => 'Duplicated campaign as "' . $newTitle . '" (shared media, new containers).',
    ];
}

/**
 * Setup-only: install the published Demo PCF (or local template seed).
 * Campaign media travels in the PCF; do not pull a parallel default-theme content package.
 *
 * @return array{installed: bool, source: string, release_id: string, version?: string, message: string}
 */
function bandpromo_ensure_demo_campaign_package(string $root, string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL, ?callable $logger = null): array
{
    require_once __DIR__ . '/release-package.php';

    try {
        $demoPackage = null;
        try {
            $manifest = bandpromo_release_load_manifest($manifestUrl);
            $fromApp = $manifest['demo_release_package'] ?? null;
            if (is_array($fromApp)
                && trim((string) ($fromApp['package_url'] ?? '')) !== ''
                && trim((string) ($fromApp['sha256'] ?? '')) !== ''
            ) {
                $demoPackage = $fromApp;
            }
        } catch (Throwable $appManifestError) {
            bandpromo_release_log(
                $logger,
                '[demo release] App release manifest unavailable: ' . $appManifestError->getMessage()
            );
        }

        // Durable demo-content release is the source of truth when the app
        // manifest omits demo_release_package (or points at a missing asset).
        if (!is_array($demoPackage)) {
            bandpromo_release_log($logger, '[demo release] Loading durable demo-content manifest...');
            $demoManifest = bandpromo_release_load_manifest(BANDPROMO_DEMO_MANIFEST_URL);
            if (trim((string) ($demoManifest['package_url'] ?? '')) === '') {
                $demoManifest['package_url'] = BANDPROMO_DEMO_PCF_URL;
            }
            if (trim((string) ($demoManifest['package_file'] ?? '')) === '') {
                $demoManifest['package_file'] = BANDPROMO_DEMO_PCF_FILENAME;
            }
            $demoPackage = $demoManifest;
        }

        if (is_array($demoPackage)
            && trim((string) ($demoPackage['package_url'] ?? '')) !== ''
            && trim((string) ($demoPackage['sha256'] ?? '')) !== ''
        ) {
            $markerPath = bandpromo_campaign_demo_marker_path($root);
            $marker = is_file($markerPath) ? bandpromo_json_read_array_file($markerPath) : null;
            $already = is_array($marker) && (string) ($marker['sha256'] ?? '') === (string) $demoPackage['sha256'];
            if ($already) {
                bandpromo_release_log($logger, '[demo campaign] Demo PCF already installed for this manifest.');

                return [
                    'installed' => false,
                    'source' => 'remote-demo-pcf-cached',
                    'release_id' => BANDPROMO_RELEASE_DEMO_ID,
                    'version' => (string) ($demoPackage['version'] ?? ''),
                    'message' => 'Demo PCF already present.',
                ];
            }

            $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_DEMO_CAMPAIGN_WORKDIR;
            $packageFile = trim((string) ($demoPackage['package_file'] ?? ''));
            if ($packageFile === '') {
                $packageFile = BANDPROMO_DEMO_PCF_FILENAME;
            }
            $downloadPath = $workDir . DIRECTORY_SEPARATOR . basename($packageFile);
            bandpromo_release_rrmdir($workDir);
            bandpromo_release_ensure_dir($workDir);
            bandpromo_release_log($logger, '[demo campaign] Downloading Demo PCF (this can take a minute on first install)...');
            $downloadPath = bandpromo_release_download_demo_campaign_file(
                (string) $demoPackage['package_url'],
                $downloadPath,
                $logger
            );
            bandpromo_release_log($logger, '[demo campaign] Verifying Demo PCF checksum...');
            $actual = bandpromo_release_sha256_file($downloadPath);
            if ($actual !== (string) $demoPackage['sha256']) {
                throw new RuntimeException('Demo PCF checksum did not match the published manifest.');
            }
            bandpromo_release_log($logger, '[demo campaign] Importing Demo PCF into this site...');
            $import = bandpromo_campaign_import_from_zip($root, $downloadPath, [
                'mode' => 'demo',
                'allow_demo_overwrite' => true,
                'collision' => 'overwrite',
                // First install may claim base brand; later package refreshes must not.
                'set_active_brand' => bandpromo_campaign_should_claim_active_brand($root),
            ]);
            bandpromo_json_write_file($markerPath, [
                'version' => (string) ($demoPackage['version'] ?? ''),
                'sha256' => (string) $demoPackage['sha256'],
                'package_file' => (string) ($demoPackage['package_file'] ?? ''),
                'format' => 'pcf',
                'installed_at' => gmdate('c'),
            ]);
            bandpromo_campaign_lock_platform_demo_after_import($root);
            bandpromo_brand_lock_platform_default_after_import($root);
            require_once __DIR__ . '/demo-catalog-state.php';
            $importedReleaseId = bandpromo_campaign_normalize_id((string) ($import['release_id'] ?? BANDPROMO_RELEASE_DEMO_ID));
            bandpromo_demo_release_ensure_preferences($root, $importedReleaseId !== '' ? $importedReleaseId : BANDPROMO_RELEASE_DEMO_ID);
            bandpromo_demo_release_invalidate_asset_set_cache($root);
            if (!bandpromo_release_rrmdir_best_effort($workDir)) {
                bandpromo_release_log(
                    $logger,
                    '[demo release] Package imported; leftover workdir could not be removed yet (safe to ignore).'
                );
            }
            bandpromo_release_log($logger, '[demo release] ' . $import['message']);

            return [
                'installed' => true,
                    'source' => 'remote-demo-pcf',
                'release_id' => $import['release_id'],
                'version' => (string) ($demoPackage['version'] ?? ''),
                'message' => $import['message'],
            ];
        }
        bandpromo_release_log($logger, '[demo campaign] Manifest has no Demo PCF entry yet.');
    } catch (Throwable $throwable) {
        bandpromo_release_log($logger, '[demo campaign] Remote Demo PCF unavailable: ' . $throwable->getMessage());
    }

    // Demo content must come from the published PRP — no parallel template seed.
    $demoDoc = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'releases'
        . DIRECTORY_SEPARATOR . BANDPROMO_RELEASE_DEMO_ID . '.json';
    if (is_file($demoDoc)) {
        bandpromo_campaign_enforce_platform_demo_lock($root);
        require_once __DIR__ . '/demo-catalog-state.php';
        bandpromo_demo_release_ensure_preferences($root, BANDPROMO_RELEASE_DEMO_ID);

        return [
            'installed' => true,
            'source' => 'already-present',
            'release_id' => BANDPROMO_RELEASE_DEMO_ID,
            'message' => 'bandPromo demo release already present on this install.',
        ];
    }

    return [
        'installed' => false,
        'source' => 'missing',
        'release_id' => BANDPROMO_RELEASE_DEMO_ID,
        'message' => 'bandPromo-demo.pcf was not available. Setup cannot seed demo content from templates.',
    ];
}
