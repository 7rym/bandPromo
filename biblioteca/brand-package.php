<?php
declare(strict_types=1);

/**
 * Portable Brand File (.pbf) import/export.
 * Unit: one brand document + curated library/slot masters + registry subset (no campaign tracks).
 */

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/release-package.php';
require_once __DIR__ . '/brand-storage.php';
require_once __DIR__ . '/campaign-package.php';

const BANDPROMO_BRAND_EXPORT_VERSION = 1;
const BANDPROMO_BRAND_PACKAGE_WORKDIR = '.bandpromo-brand-package';

function bandpromo_pbf_is_brand_file_extension(string $ext): bool
{
    $ext = strtolower(trim($ext, ". \t\n\r\0\x0B"));

    return $ext === 'pbf';
}

function bandpromo_pbf_operator_extension_error(): string
{
    return 'Brand files must be .pbf.';
}

/**
 * @return array{
 *   brand_export_version: int,
 *   format: string,
 *   brand_id: string,
 *   title: string,
 *   paths: list<string>
 * }
 */
function bandpromo_brand_read_package_manifest(string $packageDir): array
{
    $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'brand-package-manifest.json';
    if (!is_file($manifestPath)) {
        throw new RuntimeException('Brand file is missing brand-package-manifest.json.');
    }
    $decoded = bandpromo_json_read_array_file($manifestPath);
    if ($decoded === null) {
        throw new RuntimeException('Brand file manifest is not valid JSON.');
    }
    $version = (int) ($decoded['brand_export_version'] ?? 0);
    if ($version !== BANDPROMO_BRAND_EXPORT_VERSION) {
        throw new RuntimeException(
            'Incompatible brand file version ' . $version
            . ' (supported: ' . BANDPROMO_BRAND_EXPORT_VERSION . '). Upgrade bandPromo, then retry.'
        );
    }
    $format = strtolower(trim((string) ($decoded['format'] ?? 'pbf')));
    if ($format !== '' && $format !== 'pbf') {
        throw new RuntimeException('This archive is not a Portable Brand File (.pbf).');
    }
    $brandId = bandpromo_brand_canonical_id((string) ($decoded['brand_id'] ?? ''));
    if ($brandId === '') {
        throw new RuntimeException('Brand file manifest is missing brand_id.');
    }
    $paths = [];
    if (isset($decoded['paths']) && is_array($decoded['paths'])) {
        foreach ($decoded['paths'] as $path) {
            $relative = bandpromo_brand_package_normalize_relative_path((string) $path);
            if ($relative !== '') {
                $paths[] = $relative;
            }
        }
    }

    return [
        'brand_export_version' => $version,
        'format' => 'pbf',
        'brand_id' => $brandId,
        'title' => trim((string) ($decoded['title'] ?? $brandId)),
        'paths' => $paths,
    ];
}

function bandpromo_brand_package_normalize_relative_path(string $path): string
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
        'data/brands/',
        'data/themes/',
        'data/assets/',
        'media/',
        'brand-package-manifest.json',
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

function bandpromo_brand_package_is_allowed_entry(string $relativePath): bool
{
    return bandpromo_brand_package_normalize_relative_path($relativePath) !== '';
}

/**
 * @return list<string>
 */
function bandpromo_brand_collect_package_asset_ids(string $root, string $brandId): array
{
    require_once __DIR__ . '/asset-registry.php';

    $brandId = bandpromo_brand_canonical_id($brandId);
    $ids = [];
    $add = static function (string $id) use (&$ids): void {
        $id = trim($id);
        if ($id !== '' && bandpromo_asset_is_asset_id($id)) {
            $ids[$id] = true;
        }
    };

    try {
        $brand = bandpromo_brand_load_document($root, $brandId);
    } catch (Throwable $throwable) {
        return [];
    }

    foreach (is_array($brand['asset_ids'] ?? null) ? $brand['asset_ids'] : [] as $slotId) {
        $add((string) $slotId);
    }
    foreach (is_array($brand['library_asset_ids'] ?? null) ? $brand['library_asset_ids'] : [] as $libraryId) {
        $add((string) $libraryId);
    }

    $ordered = array_keys($ids);
    sort($ordered);

    return $ordered;
}

/**
 * Build a Portable Brand File (masters + brand doc + registry subset).
 *
 * @return array{ok: bool, path: string, brand_id: string, files: int, asset_ids: list<string>}
 */
function bandpromo_brand_export_to_zip(string $root, string $brandId, string $zipPath): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/visual-master-helpers.php';

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('This host cannot export brand files.');
    }

    $brandId = bandpromo_brand_canonical_id($brandId);
    if ($brandId === '') {
        throw new InvalidArgumentException('Choose a brand to export.');
    }
    $brand = bandpromo_brand_load_document($root, $brandId);
    $title = trim((string) ($brand['title'] ?? $brandId));
    if ($title === '') {
        $title = $brandId;
    }

    $assetIds = bandpromo_brand_collect_package_asset_ids($root, $brandId);
    $paths = [];
    $addPath = static function (string $relative) use (&$paths, $root): void {
        $relative = bandpromo_brand_package_normalize_relative_path($relative);
        if ($relative === '') {
            return;
        }
        $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($absolute)) {
            $paths[$relative] = $absolute;
        }
    };

    $addPath('data/brands/' . $brandId . '.json');

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
            $format = strtolower(trim((string) ($asset['master_format'] ?? '')));
            $masterName = basename((string) ($asset['master_filename'] ?? ''));
            if ($format !== '') {
                $addPath('media/visual/master/' . bandpromo_asset_master_filename_for_ulid($assetId, $format));
            } elseif ($masterName !== '') {
                $addPath('media/visual/master/' . $masterName);
            }
        } elseif ($kind === 'sfx') {
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

    $workdir = $root . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR
        . '.bandpromo-brand-export-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', $brandId);
    if (!is_dir($workdir) && !mkdir($workdir, 0750, true) && !is_dir($workdir)) {
        throw new RuntimeException('Could not create brand export work directory.');
    }
    $assetsDir = $workdir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'assets';
    if (!is_dir($assetsDir) && !mkdir($assetsDir, 0750, true) && !is_dir($assetsDir)) {
        throw new RuntimeException('Could not create brand export assets directory.');
    }
    $subsetPath = $assetsDir . DIRECTORY_SEPARATOR . 'registry.json';
    if (!bandpromo_json_write_file($subsetPath, $subset)) {
        throw new RuntimeException('Could not write registry subset for brand export.');
    }
    $paths['data/assets/registry.json'] = $subsetPath;

    require_once __DIR__ . '/chunked-upload.php';
    $fileDigests = bandpromo_transfer_file_digests($paths);

    $manifest = [
        'brand_export_version' => BANDPROMO_BRAND_EXPORT_VERSION,
        'format' => 'pbf',
        'brand_id' => $brandId,
        'title' => $title,
        'exported_at' => gmdate('c'),
        'bandpromo_version' => bandpromo_campaign_bandpromo_version($root),
        'asset_ids' => array_keys($subsetAssets),
        'paths' => array_keys($paths),
        'file_digests' => $fileDigests,
    ];
    $manifestPath = $workdir . DIRECTORY_SEPARATOR . 'brand-package-manifest.json';
    if (!bandpromo_json_write_file($manifestPath, $manifest)) {
        throw new RuntimeException('Could not write brand-package-manifest.json.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create the brand file.');
    }
    $zip->addFile($manifestPath, 'brand-package-manifest.json');
    foreach ($paths as $relative => $absolute) {
        $zip->addFile($absolute, $relative);
    }
    $zip->close();

    return [
        'ok' => true,
        'path' => $zipPath,
        'brand_id' => $brandId,
        'files' => count($paths) + 1,
        'asset_ids' => array_keys($subsetAssets),
    ];
}

function bandpromo_brand_package_exists(string $root, string $brandId): bool
{
    $brandId = bandpromo_brand_canonical_id($brandId);

    return $brandId !== '' && is_file(bandpromo_brand_document_path($root, $brandId));
}

/**
 * Ensure imported brand appears in data/brands/registry.json.
 */
function bandpromo_brand_package_ensure_registry_entry(string $root, string $brandId): void
{
    bandpromo_brand_ensure_seeded($root);
    $brandId = bandpromo_brand_canonical_id($brandId);
    if ($brandId === '' || !is_file(bandpromo_brand_document_path($root, $brandId))) {
        return;
    }

    try {
        $brandDoc = bandpromo_brand_load_document($root, $brandId);
    } catch (Throwable $throwable) {
        $brandDoc = ['title' => $brandId, 'system' => false, 'locked' => false];
    }

    $brandRegistry = bandpromo_brand_load_registry($root);
    $listKey = bandpromo_brand_registry_list_key($brandRegistry);
    $knownBrands = [];
    $maxBrandOrder = 0;
    foreach ($brandRegistry[$listKey] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $bid = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
        if ($bid !== '') {
            $knownBrands[$bid] = true;
        }
        if ($bid === $brandId) {
            $brandRegistry[$listKey][$index]['title'] = (string) ($brandDoc['title'] ?? $brandId);
            $brandRegistry[$listKey][$index]['system'] = !empty($brandDoc['system']);
            $brandRegistry[$listKey][$index]['locked'] = !empty($brandDoc['locked']);
            bandpromo_brand_write_registry($root, $brandRegistry);

            return;
        }
        $maxBrandOrder = max($maxBrandOrder, (int) ($entry['sort_order'] ?? 0));
    }

    if (!isset($knownBrands[$brandId])) {
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

/**
 * @param array{collision?: string} $options collision: refuse|overwrite|skip|allocate
 * @return array{
 *   ok: bool,
 *   brand_id: string,
 *   message: string,
 *   imported_files: int,
 *   collision?: string,
 *   build_required?: bool,
 *   queue_deliverables?: bool,
 *   build_required_state?: mixed,
 *   image_delivery_ok?: bool,
 *   deliverables_started?: bool,
 *   deliverables_warning?: string
 * }
 */
function bandpromo_brand_import_from_directory(string $root, string $packageDir, array $options = []): array
{
    $packageDir = rtrim($packageDir, "\\/");
    $manifest = bandpromo_brand_read_package_manifest($packageDir);
    $rawManifest = bandpromo_json_read_array_file(
        $packageDir . DIRECTORY_SEPARATOR . 'brand-package-manifest.json'
    );
    if (is_array($rawManifest) && isset($rawManifest['file_digests']) && is_array($rawManifest['file_digests'])) {
        require_once __DIR__ . '/chunked-upload.php';
        bandpromo_transfer_verify_extracted_digests($packageDir, $rawManifest['file_digests']);
    }
    $sourceBrandId = $manifest['brand_id'];
    $collision = strtolower(trim((string) ($options['collision'] ?? 'refuse')));
    if ($collision === 'skip-existing') {
        $collision = 'skip';
    }
    if ($collision === 'asnew' || $collision === 'as_new') {
        $collision = 'allocate';
    }
    if (!in_array($collision, ['refuse', 'overwrite', 'skip', 'allocate'], true)) {
        $collision = 'refuse';
    }

    $exists = bandpromo_brand_package_exists($root, $sourceBrandId);
    $targetBrandId = $sourceBrandId;
    if ($exists) {
        if ($sourceBrandId === BANDPROMO_BRAND_DEFAULT_ID && $collision === 'overwrite') {
            throw new RuntimeException(
                'The platform default brand is locked. Choose Skip or AsNew, or edit it on localhost.'
            );
        }
        if ($collision === 'overwrite') {
            try {
                $existing = bandpromo_brand_load_document($root, $sourceBrandId);
                if (!empty($existing['locked']) && !bandpromo_brand_may_edit_document($existing)) {
                    throw new RuntimeException(
                        'Brand "' . $sourceBrandId . '" is locked. Unlock it, or choose Skip / AsNew.'
                    );
                }
            } catch (RuntimeException $lockedError) {
                throw $lockedError;
            } catch (Throwable $throwable) {
                // Missing/corrupt local doc — overwrite may replace it.
            }
        }
        if ($collision === 'refuse') {
            throw new RuntimeException(
                'Brand "' . $sourceBrandId . '" already exists. Choose collision Overwrite, Skip, or AsNew.'
            );
        }
        if ($collision === 'skip') {
            return [
                'ok' => true,
                'brand_id' => $sourceBrandId,
                'message' => 'Skipped import; brand "' . $sourceBrandId . '" already exists.',
                'imported_files' => 0,
                'collision' => 'skip',
            ];
        }
        if ($collision === 'allocate') {
            $targetBrandId = bandpromo_brand_allocate_duplicate_id(
                $root,
                $manifest['title'] !== '' ? $manifest['title'] : $sourceBrandId
            );
        }
    }

    $remapBrand = $sourceBrandId !== $targetBrandId;
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
        $relative = bandpromo_brand_package_normalize_relative_path($relative);
        if ($relative === '' || $relative === 'brand-package-manifest.json') {
            continue;
        }

        $destinationRelative = $relative;
        if ($remapBrand
            && (str_starts_with($relative, 'data/brands/' . $sourceBrandId . '.json')
                || str_starts_with($relative, 'data/themes/' . $sourceBrandId . '.json'))
        ) {
            $destinationRelative = 'data/brands/' . $targetBrandId . '.json';
        }

        // Never overwrite platform default via brand-only import.
        if (basename($destinationRelative) === 'bandpromo-default.json'
            && (str_starts_with($destinationRelative, 'data/brands/')
                || str_starts_with($destinationRelative, 'data/themes/'))
        ) {
            continue;
        }

        $destination = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $destinationRelative);
        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            throw new RuntimeException('Could not create directory for imported file: ' . $destinationRelative);
        }

        if (str_ends_with(strtolower($destinationRelative), '.json') && str_starts_with($destinationRelative, 'data/')) {
            $decoded = bandpromo_json_read_array_file($absolute);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid JSON in brand file: ' . $relative);
            }
            if ($destinationRelative === 'data/assets/registry.json') {
                bandpromo_campaign_merge_asset_registry($root, $decoded);
                $imported++;
                continue;
            }
            if (str_starts_with($destinationRelative, 'data/brands/')
                || str_starts_with($destinationRelative, 'data/themes/')
            ) {
                $decoded['id'] = $targetBrandId;
                // Brand-only handoff: detach from source campaign ownership.
                $decoded['campaign_id'] = '';
                $decoded['release_id'] = '';
                if ($remapBrand) {
                    $decoded['system'] = false;
                    $decoded['locked'] = false;
                    $title = trim((string) ($decoded['title'] ?? $manifest['title']));
                    if ($title === '' || $title === $sourceBrandId) {
                        $title = $manifest['title'] !== '' ? $manifest['title'] : $targetBrandId;
                    }
                    if (!str_ends_with(strtolower($title), ' copy') && $title === $manifest['title']) {
                        $decoded['title'] = bandpromo_brand_propose_duplicate_title($title);
                    } else {
                        $decoded['title'] = $title;
                    }
                }
                $decoded = bandpromo_brand_normalize_document($decoded, $targetBrandId);
                if (!bandpromo_json_write_file($destination, $decoded)) {
                    throw new RuntimeException('Could not write imported brand: ' . $destinationRelative);
                }
                $imported++;
                continue;
            }
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

    bandpromo_brand_package_ensure_registry_entry($root, $targetBrandId);

    require_once __DIR__ . '/media-library-state.php';
    try {
        bandpromo_media_files_index_rebuild_all($root);
    } catch (Throwable $throwable) {
        // Listing heals on the next Files GET.
    }

    require_once __DIR__ . '/sfx-helpers.php';
    try {
        bandpromo_sfx_backfill_tiers($root);
    } catch (Throwable $throwable) {
        // Non-fatal.
    }

    require_once __DIR__ . '/build-required.php';
    $buildState = null;
    try {
        $buildState = bandpromo_mark_build_required('brand_package_imported');
        $buildState = bandpromo_mark_build_required('media_image_upload');
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

        require_once __DIR__ . '/light-build-tasks.php';
        $imageTask = bandpromo_run_light_task('scripts/optimizeMedia.py', [
            'BANDPROMO_OPTIMIZE_MODE' => 'image-only',
        ]);
        $imageDeliveryOk = !empty($imageTask['ok']);
        if ($imageDeliveryOk) {
            try {
                bandpromo_media_files_index_rebuild_target($root, 'illustrations');
                bandpromo_media_files_index_rebuild_target($root, 'photos');
            } catch (Throwable $throwable) {
                // Non-fatal.
            }
            try {
                $buildState = bandpromo_clear_build_required_tasks(['image-delivery']);
            } catch (Throwable $throwable) {
                // Non-fatal.
            }
        } else {
            $deliverablesWarning = 'Image delivery refresh did not finish after brand import.';
        }

        require_once __DIR__ . '/build-queue-helpers.php';
        $queued = bandpromo_build_try_start($root, [
            'mode' => 'full',
            'profile' => 'deliverables-only',
            'actor' => 'brand_package_import',
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

    $message = $remapBrand
        ? 'Imported Portable Brand File as ' . $targetBrandId . '.'
        : 'Imported Portable Brand File ' . $targetBrandId . '.';
    if ($imageDeliveryOk && $deliverablesStarted) {
        $message .= ' Image delivery refreshed; remaining deliverables queued.';
    } elseif ($deliverablesWarning !== '') {
        $message .= ' ' . $deliverablesWarning;
    }

    $result = [
        'ok' => true,
        'brand_id' => $targetBrandId,
        'message' => $message,
        'imported_files' => $imported,
        'collision' => $remapBrand ? 'allocate' : ($exists ? $collision : 'new'),
        'build_required' => true,
        'queue_deliverables' => true,
        'image_delivery_ok' => $imageDeliveryOk,
        'deliverables_started' => $deliverablesStarted,
    ];
    if ($deliverablesWarning !== '') {
        $result['deliverables_warning'] = $deliverablesWarning;
    }
    if ($buildState !== null) {
        $result['build_required_state'] = $buildState;
    }

    return $result;
}

/**
 * @param array{collision?: string} $options
 * @return array{ok: bool, brand_id: string, message: string, imported_files: int, collision?: string}
 */
function bandpromo_brand_import_from_zip(string $root, string $zipPath, array $options = []): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('This host cannot import brand files.');
    }
    if (!is_file($zipPath)) {
        throw new RuntimeException('Brand file is missing.');
    }

    $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_BRAND_PACKAGE_WORKDIR
        . DIRECTORY_SEPARATOR . 'import-' . bin2hex(random_bytes(4));
    bandpromo_release_rrmdir($workDir);
    bandpromo_release_ensure_dir($workDir);

    $zip = new ZipArchive();
    $openStatus = $zip->open($zipPath);
    if ($openStatus !== true) {
        $size = is_file($zipPath) ? (int) filesize($zipPath) : 0;
        $code = is_int($openStatus) ? (string) $openStatus : 'unknown';
        throw new RuntimeException(
            'Could not open the brand file (status ' . $code
            . ', size ' . $size . ' bytes).'
        );
    }

    try {
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
            if (str_contains($name, '..') || !bandpromo_brand_package_is_allowed_entry($name)) {
                continue;
            }
            $allowedNames[] = $rawName;
        }

        if ($allowedNames === []) {
            throw new RuntimeException('The brand file has no importable entries.');
        }
        if ($zip->extractTo($workDir, $allowedNames) !== true) {
            throw new RuntimeException('Could not extract brand file contents.');
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
        return bandpromo_brand_import_from_directory($root, $workDir, $options);
    } finally {
        bandpromo_release_rrmdir($workDir);
    }
}
