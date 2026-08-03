<?php
declare(strict_types=1);

/**
 * Shared Release campaign package import (setup + admin).
 * Export is deferred until the Release hub model is stable.
 */

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/release-package.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/theme-storage.php';
require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/gallery-storage.php';
require_once __DIR__ . '/page-storage.php';
require_once __DIR__ . '/release-ownership-helpers.php';

const BANDPROMO_RELEASE_CAMPAIGN_EXPORT_VERSION = 1;
const BANDPROMO_DEMO_RELEASE_MARKER = 'data/demo-release-package.json';
const BANDPROMO_DEMO_RELEASE_WORKDIR = '.bandpromo-demo-release-package';

/**
 * @return array{release_export_version: int, release_id: string, title: string, paths: list<string>}
 */
function bandpromo_release_campaign_read_manifest(string $packageDir): array
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
    if ($version !== BANDPROMO_RELEASE_CAMPAIGN_EXPORT_VERSION) {
        throw new RuntimeException(
            'Incompatible release package version ' . $version
            . ' (supported: ' . BANDPROMO_RELEASE_CAMPAIGN_EXPORT_VERSION . '). Upgrade bandPromo, then retry.'
        );
    }
    $releaseId = bandpromo_release_normalize_id((string) ($decoded['release_id'] ?? ''));
    if ($releaseId === '') {
        throw new RuntimeException('Release package manifest is missing release_id.');
    }
    $paths = [];
    if (isset($decoded['paths']) && is_array($decoded['paths'])) {
        foreach ($decoded['paths'] as $path) {
            $relative = bandpromo_release_campaign_normalize_relative_path((string) $path);
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

function bandpromo_release_campaign_normalize_relative_path(string $path): string
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
        'data/releases/',
        'data/brands/',
        'data/themes/',
        'data/playlists/',
        'data/galleries/',
        'data/pages/',
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

function bandpromo_release_campaign_is_allowed_entry(string $relativePath): bool
{
    return bandpromo_release_campaign_normalize_relative_path($relativePath) !== '';
}

/**
 * Demo/campaign import may claim the install active brand only on first run.
 * Routine full builds must not reset an operator-chosen active brand (e.g. HITZ).
 */
function bandpromo_release_campaign_should_claim_active_brand(string $root): bool
{
    require_once __DIR__ . '/config-loader.php';
    require_once __DIR__ . '/theme-storage.php';

    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    $active = bandpromo_brand_canonical_id((string) bandpromo_config_get_path(
        $config,
        'install.pointers.active_brand_id',
        ''
    ));

    return $active === '';
}

/**
 * @param array{mode?: string, allow_demo_overwrite?: bool, set_active_brand?: bool} $options
 * @return array{ok: bool, release_id: string, message: string, imported_files: int, ownership: array}
 */
function bandpromo_release_campaign_import_from_directory(string $root, string $packageDir, array $options = []): array
{
    $packageDir = rtrim($packageDir, "\\/");
    $manifest = bandpromo_release_campaign_read_manifest($packageDir);
    $mode = strtolower(trim((string) ($options['mode'] ?? 'operator')));
    $allowDemoOverwrite = !empty($options['allow_demo_overwrite']) || $mode === 'demo' || $mode === 'setup';
    $setActiveBrand = array_key_exists('set_active_brand', $options)
        ? !empty($options['set_active_brand'])
        : ($mode === 'demo' || $mode === 'setup');
    if ($setActiveBrand && !bandpromo_release_campaign_should_claim_active_brand($root)) {
        $setActiveBrand = false;
    }

    $sourceReleaseId = $manifest['release_id'];
    $targetReleaseId = $sourceReleaseId;
    if ($sourceReleaseId === BANDPROMO_RELEASE_DEMO_ID && !$allowDemoOverwrite) {
        $targetReleaseId = bandpromo_release_campaign_allocate_id($root, $manifest['title'] !== '' ? $manifest['title'] : 'imported-release');
    } elseif ($sourceReleaseId !== BANDPROMO_RELEASE_DEMO_ID) {
        try {
            bandpromo_release_load_document($root, $sourceReleaseId);
            $targetReleaseId = bandpromo_release_campaign_allocate_id($root, $manifest['title'] !== '' ? $manifest['title'] : $sourceReleaseId);
        } catch (Throwable $throwable) {
            $targetReleaseId = $sourceReleaseId;
        }
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
        $relative = bandpromo_release_campaign_normalize_relative_path($relative);
        if ($relative === '' || $relative === 'release-package-manifest.json') {
            continue;
        }

        $destinationRelative = $relative;
        if ($remapRelease && str_starts_with($relative, 'data/releases/' . $sourceReleaseId . '.json')) {
            $destinationRelative = 'data/releases/' . $targetReleaseId . '.json';
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
            $decoded = bandpromo_release_campaign_remap_document($decoded, $destinationRelative, $sourceReleaseId, $targetReleaseId);
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

    bandpromo_release_campaign_ensure_registry_entries($root, $targetReleaseId);
    $ownership = bandpromo_release_ownership_migrate($root);

    if ($setActiveBrand) {
        try {
            $releaseDoc = bandpromo_release_load_document($root, $targetReleaseId);
            $brandId = trim((string) ($releaseDoc['brand_id'] ?? ''));
            if ($brandId !== '') {
                bandpromo_theme_set_active_id($root, $brandId);
            }
        } catch (Throwable $throwable) {
            // Best-effort active brand pointer.
        }
    }

    return [
        'ok' => true,
        'release_id' => $targetReleaseId,
        'message' => $remapRelease
            ? 'Imported release package as ' . $targetReleaseId . '.'
            : 'Imported release package ' . $targetReleaseId . '.',
        'imported_files' => $imported,
        'ownership' => $ownership,
    ];
}

/**
 * @return array<string, mixed>
 */
function bandpromo_release_campaign_remap_document(
    array $document,
    string $destinationRelative,
    string $sourceReleaseId,
    string $targetReleaseId
): array {
    if (str_starts_with($destinationRelative, 'data/releases/')) {
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

function bandpromo_release_campaign_allocate_id(string $root, string $title): string
{
    $base = bandpromo_release_normalize_id(preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($title))) ?: 'imported-release');
    if ($base === '') {
        $base = 'imported-release';
    }
    $candidate = $base;
    $suffix = 2;
    while (is_file(bandpromo_release_document_path($root, $candidate))) {
        $candidate = $base . '-' . $suffix;
        $suffix++;
        if ($suffix > 99) {
            throw new RuntimeException('Could not allocate a free release id for import.');
        }
    }

    return $candidate;
}

function bandpromo_release_campaign_ensure_registry_entries(string $root, string $releaseId): void
{
    bandpromo_release_ensure_seeded($root);
    bandpromo_theme_ensure_seeded($root);
    bandpromo_playlist_ensure_seeded($root);
    bandpromo_gallery_ensure_seeded($root);

    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '' || !is_file(bandpromo_release_document_path($root, $releaseId))) {
        return;
    }

    $document = bandpromo_release_load_document($root, $releaseId);
    $registry = bandpromo_release_load_registry($root);
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
    bandpromo_release_write_registry($root, $registry);
}

/**
 * @return array{ok: bool, release_id: string, message: string, imported_files: int, ownership: array}
 */
function bandpromo_release_campaign_import_from_zip(string $root, string $zipPath, array $options = []): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available on this host.');
    }
    if (!is_file($zipPath)) {
        throw new RuntimeException('Release package ZIP is missing.');
    }

    $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_DEMO_RELEASE_WORKDIR . DIRECTORY_SEPARATOR . 'import-' . bin2hex(random_bytes(4));
    bandpromo_release_rrmdir($workDir);
    bandpromo_release_ensure_dir($workDir);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open release package ZIP.');
    }

    try {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !isset($stat['name'])) {
                continue;
            }
            $name = str_replace('\\', '/', (string) $stat['name']);
            $name = ltrim($name, '/');
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }
            if (str_contains($name, '..') || !bandpromo_release_campaign_is_allowed_entry($name)) {
                continue;
            }
            $target = $workDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            $targetDir = dirname($target);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new RuntimeException('Could not prepare extract path for: ' . $name);
            }
            $contents = $zip->getFromIndex($index);
            if ($contents === false || file_put_contents($target, $contents) === false) {
                throw new RuntimeException('Could not extract: ' . $name);
            }
        }
    } finally {
        $zip->close();
    }

    try {
        return bandpromo_release_campaign_import_from_directory($root, $workDir, $options);
    } finally {
        bandpromo_release_rrmdir($workDir);
    }
}

/**
 * Build/apply the tracked Demo Release campaign seed (docs + ownership).
 * Media still comes from the starter media package / local media tree.
 *
 * @return array{ok: bool, release_id: string, message: string, imported_files: int, ownership: array}
 */
function bandpromo_release_campaign_seed_demo_from_templates(string $root): array
{
    $packageDir = $root . DIRECTORY_SEPARATOR . 'biblioteca' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'demo-release-package';
    if (!is_dir($packageDir) || !is_file($packageDir . DIRECTORY_SEPARATOR . 'release-package-manifest.json')) {
        bandpromo_release_ownership_migrate($root);

        return [
            'ok' => true,
            'release_id' => BANDPROMO_RELEASE_DEMO_ID,
            'message' => 'Demo Release ownership linked from local templates.',
            'imported_files' => 0,
            'ownership' => [],
        ];
    }

    return bandpromo_release_campaign_import_from_directory($root, $packageDir, [
        'mode' => 'demo',
        'allow_demo_overwrite' => true,
        // Local template re-seed runs on every full build when the remote demo
        // package is already installed — never steal the operator active brand.
        'set_active_brand' => false,
    ]);
}

function bandpromo_release_campaign_demo_marker_path(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, BANDPROMO_DEMO_RELEASE_MARKER);
}

/**
 * Setup/build path: prefer published Demo Release package; dual-read default-theme media ZIP.
 *
 * @return array{installed: bool, source: string, release_id: string, version?: string, message: string}
 */
function bandpromo_ensure_demo_release_package(string $root, string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL, ?callable $logger = null): array
{
    require_once __DIR__ . '/release-package.php';

    $mediaResult = null;
    try {
        $mediaResult = bandpromo_ensure_default_theme_package($root, $manifestUrl, $logger);
    } catch (Throwable $throwable) {
        bandpromo_release_log($logger, '[demo release] Media starter pack: ' . $throwable->getMessage());
        throw $throwable;
    }

    $campaignFromRemote = false;
    try {
        $manifest = bandpromo_release_load_manifest($manifestUrl);
        $demoPackage = $manifest['demo_release_package'] ?? null;
        if (is_array($demoPackage)
            && trim((string) ($demoPackage['package_url'] ?? '')) !== ''
            && trim((string) ($demoPackage['sha256'] ?? '')) !== ''
        ) {
            $markerPath = bandpromo_release_campaign_demo_marker_path($root);
            $marker = is_file($markerPath) ? bandpromo_json_read_array_file($markerPath) : null;
            $already = is_array($marker) && (string) ($marker['sha256'] ?? '') === (string) $demoPackage['sha256'];
            if (!$already) {
                $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_DEMO_RELEASE_WORKDIR;
                $downloadPath = $workDir . DIRECTORY_SEPARATOR . 'demo-release.zip';
                bandpromo_release_rrmdir($workDir);
                bandpromo_release_ensure_dir($workDir);
                bandpromo_release_log($logger, '[demo release] Downloading Demo Release package...');
                bandpromo_release_download_file((string) $demoPackage['package_url'], $downloadPath);
                $actual = bandpromo_release_sha256_file($downloadPath);
                if ($actual !== (string) $demoPackage['sha256']) {
                    throw new RuntimeException('Demo Release package checksum did not match the published manifest.');
                }
                $import = bandpromo_release_campaign_import_from_zip($root, $downloadPath, [
                    'mode' => 'demo',
                    'allow_demo_overwrite' => true,
                    // First install may claim active brand; later package refreshes must not.
                    'set_active_brand' => bandpromo_release_campaign_should_claim_active_brand($root),
                ]);
                bandpromo_json_write_file($markerPath, [
                    'version' => (string) ($demoPackage['version'] ?? ''),
                    'sha256' => (string) $demoPackage['sha256'],
                    'package_file' => (string) ($demoPackage['package_file'] ?? ''),
                    'installed_at' => gmdate('c'),
                ]);
                bandpromo_release_rrmdir($workDir);
                $campaignFromRemote = true;
                bandpromo_release_log($logger, '[demo release] ' . $import['message']);

                return [
                    'installed' => true,
                    'source' => 'remote-demo-release',
                    'release_id' => $import['release_id'],
                    'version' => (string) ($demoPackage['version'] ?? ''),
                    'message' => $import['message'],
                    'media' => $mediaResult,
                ];
            }
        }
    } catch (Throwable $throwable) {
        bandpromo_release_log($logger, '[demo release] Remote Demo Release package unavailable: ' . $throwable->getMessage());
    }

    $seed = bandpromo_release_campaign_seed_demo_from_templates($root);
    bandpromo_release_log($logger, '[demo release] ' . $seed['message']);

    return [
        'installed' => !$campaignFromRemote,
        'source' => 'local-templates-plus-media',
        'release_id' => $seed['release_id'],
        'message' => $seed['message'],
        'media' => $mediaResult,
    ];
}
