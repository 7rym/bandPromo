<?php
declare(strict_types=1);

require_once __DIR__ . '/release-package.php';
require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/https.php';

const BANDPROMO_PACKAGE_UPDATE_WORKDIR = '.bandpromo-update';
const BANDPROMO_PACKAGE_UPDATE_LOG = 'log/package-updates.jsonl';

function bandpromo_package_read_installed_version(string $root): string {
    $versionFile = $root . DIRECTORY_SEPARATOR . 'VERSION';
    if (!is_file($versionFile)) {
        return 'unknown';
    }

    $version = trim((string) file_get_contents($versionFile));
    return $version !== '' ? $version : 'unknown';
}

function bandpromo_package_parse_version(string $version): ?array {
    return bandpromo_release_parse_version($version);
}

function bandpromo_package_compare_versions(string $installed, string $remote): int {
    return bandpromo_release_compare_versions($installed, $remote);
}

function bandpromo_package_load_app_release_manifest(string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL): array {
    $manifest = bandpromo_release_load_manifest($manifestUrl);

    $packageUrl = $manifest['package_url'] ?? null;
    $version = $manifest['version'] ?? null;
    $sha256 = $manifest['sha256'] ?? null;

    if (!is_string($packageUrl) || trim($packageUrl) === '') {
        throw new RuntimeException('Published release manifest is missing package_url.');
    }
    if (!is_string($version) || trim($version) === '') {
        throw new RuntimeException('Published release manifest is missing version.');
    }
    if (!is_string($sha256) || trim($sha256) === '') {
        throw new RuntimeException('Published release manifest is missing sha256.');
    }

    $manifest['package_url'] = trim($packageUrl);
    $manifest['version'] = trim($version);
    $manifest['sha256'] = strtolower(trim($sha256));

    return $manifest;
}

function bandpromo_package_update_preserve_paths(): array {
    return [
        '.env',
        'web-config.json',
        'data',
        'log',
        'media',
        '.bandpromo-bootstrap',
        BANDPROMO_PACKAGE_UPDATE_WORKDIR,
        BANDPROMO_DEFAULT_THEME_WORKDIR,
    ];
}

function bandpromo_package_should_preserve(string $relativePath): bool {
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/\\'));

    foreach (bandpromo_package_update_preserve_paths() as $preservePath) {
        $preservePath = str_replace('\\', '/', $preservePath);
        if ($relativePath === $preservePath) {
            return true;
        }
        if (str_starts_with($relativePath, $preservePath . '/')) {
            return true;
        }
    }

    return false;
}

function bandpromo_package_find_package_root(string $extractDir): string {
    $directSetup = $extractDir . DIRECTORY_SEPARATOR . 'setup.php';
    $directVersion = $extractDir . DIRECTORY_SEPARATOR . 'VERSION';
    if (is_file($directSetup) && is_file($directVersion)) {
        return $extractDir;
    }

    $items = scandir($extractDir);
    if ($items === false) {
        throw new RuntimeException('Could not inspect extracted package contents.');
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $candidate = $extractDir . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($candidate)) {
            continue;
        }

        if (is_file($candidate . DIRECTORY_SEPARATOR . 'setup.php') && is_file($candidate . DIRECTORY_SEPARATOR . 'VERSION')) {
            return $candidate;
        }
    }

    throw new RuntimeException('Extracted package did not contain a recognizable bandPromo application root.');
}

function bandpromo_package_copy_tree(string $sourceRoot, string $targetRoot, string $relativePath = ''): void {
    $sourcePath = $relativePath === '' ? $sourceRoot : $sourceRoot . DIRECTORY_SEPARATOR . $relativePath;
    $targetPath = $relativePath === '' ? $targetRoot : $targetRoot . DIRECTORY_SEPARATOR . $relativePath;

    if (is_dir($sourcePath) && !is_link($sourcePath)) {
        bandpromo_release_ensure_dir($targetPath);
        $items = scandir($sourcePath);
        if ($items === false) {
            throw new RuntimeException('Could not inspect package directory: ' . $sourcePath);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $childRelative = $relativePath === '' ? $item : $relativePath . DIRECTORY_SEPARATOR . $item;
            if (bandpromo_package_should_preserve($childRelative)) {
                continue;
            }

            bandpromo_package_copy_tree($sourceRoot, $targetRoot, $childRelative);
        }

        return;
    }

    bandpromo_release_ensure_dir(dirname($targetPath));
    if (!copy($sourcePath, $targetPath)) {
        throw new RuntimeException('Could not copy package file into place: ' . $relativePath);
    }
}

function bandpromo_package_collect_environment_checks(string $root): array {
    $downloadSupport = bandpromo_release_https_download_available();
    $downloadDetail = $downloadSupport
        ? (extension_loaded('curl') ? 'curl available' : 'allow_url_fopen + openssl available')
        : bandpromo_release_https_download_setup_hint();
    $zipAvailable = class_exists('ZipArchive');
    $isLocalDev = bandpromo_is_local_dev_host();
    $rootWritable = bandpromo_install_path_is_writable($root);

    return [
        [
            'id' => 'php',
            'label' => 'PHP 8+',
            'ok' => PHP_VERSION_ID >= 80000,
            'detail' => 'Running ' . PHP_VERSION,
            'blocking' => true,
        ],
        [
            'id' => 'zip',
            'label' => 'ZipArchive available',
            'ok' => $zipAvailable,
            'detail' => $zipAvailable
                ? 'Available'
                : ($isLocalDev
                    ? 'Not loaded in this PHP build (optional on local dev; enable ext-zip to test Site update)'
                    : 'Missing ZipArchive extension'),
            'blocking' => !$isLocalDev,
            'advisory' => $isLocalDev && !$zipAvailable,
        ],
        [
            'id' => 'download',
            'label' => 'HTTPS download support',
            'ok' => $downloadSupport,
            'detail' => $downloadDetail,
            'blocking' => true,
        ],
        [
            'id' => 'writable',
            'label' => 'Site folder writable',
            'ok' => $rootWritable,
            'detail' => $rootWritable
                ? ($isLocalDev ? 'Writable via log/ or data/' : 'Writable')
                : $root,
            'blocking' => true,
        ],
    ];
}

function bandpromo_package_environment_ready(array $checks): bool {
    foreach ($checks as $check) {
        if (array_key_exists('blocking', $check) && $check['blocking'] === false) {
            continue;
        }
        if (empty($check['ok'])) {
            return false;
        }
    }

    return true;
}

function bandpromo_package_update_log_path(string $root): string {
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, BANDPROMO_PACKAGE_UPDATE_LOG);
}

function bandpromo_package_append_update_log(string $root, array $record): void {
    $logPath = bandpromo_package_update_log_path($root);
    bandpromo_release_ensure_dir(dirname($logPath));

    $payload = array_merge($record, [
        'logged_at_utc' => gmdate('c'),
    ]);

    $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line)) {
        return;
    }

    file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function bandpromo_package_last_update_record(string $root): ?array {
    $logPath = bandpromo_package_update_log_path($root);
    if (!is_file($logPath)) {
        return null;
    }

    $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || $lines === []) {
        return null;
    }

    for ($index = count($lines) - 1; $index >= 0; $index--) {
        $decoded = json_decode($lines[$index], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function bandpromo_package_check_update(string $root, string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL): array {
    $installedVersion = bandpromo_package_read_installed_version($root);
    $checks = bandpromo_package_collect_environment_checks($root);
    $ready = bandpromo_package_environment_ready($checks);

    $manifest = null;
    $remoteVersion = null;
    $updateAvailable = false;
    $aheadOfPublished = false;
    $upToDate = false;
    $manifestError = null;
    $versionCompare = null;

    try {
        $manifest = bandpromo_package_load_app_release_manifest($manifestUrl);
        $remoteVersion = (string) $manifest['version'];
        $versionCompare = bandpromo_package_compare_versions($installedVersion, $remoteVersion);
        $updateAvailable = $versionCompare < 0;
        $aheadOfPublished = $versionCompare > 0;
        $upToDate = $versionCompare === 0;
    } catch (Throwable $throwable) {
        $manifestError = $throwable->getMessage();
    }

    $lastUpdate = bandpromo_package_last_update_record($root);

    return [
        'installed_version' => $installedVersion,
        'remote_version' => $remoteVersion,
        'update_available' => $updateAvailable,
        'ahead_of_published' => $aheadOfPublished,
        'up_to_date' => $upToDate,
        'ready' => $ready,
        'checks' => $checks,
        'manifest_error' => $manifestError,
        'package_file' => is_array($manifest) ? ($manifest['package_file'] ?? null) : null,
        'release_notes' => is_array($manifest) && isset($manifest['notes']) && is_array($manifest['notes']) ? array_values($manifest['notes']) : [],
        'generated_at_utc' => is_array($manifest) ? ($manifest['generated_at_utc'] ?? null) : null,
        'last_update' => $lastUpdate,
    ];
}

function bandpromo_package_apply_release(string $root, array $manifest): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available on this host.');
    }

    $packageUrl = (string) ($manifest['package_url'] ?? '');
    $expectedSha256 = (string) ($manifest['sha256'] ?? '');
    $targetVersion = (string) ($manifest['version'] ?? '');

    if ($packageUrl === '' || $expectedSha256 === '' || $targetVersion === '') {
        throw new RuntimeException('Published release manifest is incomplete.');
    }

    $previousVersion = bandpromo_package_read_installed_version($root);
    if (bandpromo_package_compare_versions($previousVersion, $targetVersion) >= 0) {
        throw new RuntimeException('This site is already on ' . $previousVersion . '. No update is needed.');
    }

    $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_PACKAGE_UPDATE_WORKDIR;
    $downloadPath = $workDir . DIRECTORY_SEPARATOR . 'package.zip';
    $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract';

    bandpromo_release_rrmdir($workDir);
    bandpromo_release_ensure_dir($workDir);
    bandpromo_release_ensure_dir($extractDir);

    try {
        bandpromo_release_download_file($packageUrl, $downloadPath);

        $actualSha256 = bandpromo_release_sha256_file($downloadPath);
        if ($actualSha256 !== strtolower($expectedSha256)) {
            throw new RuntimeException('Downloaded package checksum did not match the published release manifest.');
        }

        $zip = new ZipArchive();
        $result = $zip->open($downloadPath);
        if ($result !== true) {
            throw new RuntimeException('Could not open downloaded ZIP package.');
        }

        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            throw new RuntimeException('Could not extract the package ZIP.');
        }
        $zip->close();

        $packageRoot = bandpromo_package_find_package_root($extractDir);
        bandpromo_package_copy_tree($packageRoot, $root);

        $installedVersion = bandpromo_package_read_installed_version($root);
        if ($installedVersion === 'unknown' || bandpromo_package_compare_versions($installedVersion, $targetVersion) < 0) {
            throw new RuntimeException('Package files were copied, but the installed VERSION file did not match the published release.');
        }

        return [
            'previous_version' => $previousVersion,
            'installed_version' => $installedVersion,
            'package_file' => (string) ($manifest['package_file'] ?? ''),
            'package_url' => $packageUrl,
            'sha256' => $expectedSha256,
        ];
    } finally {
        bandpromo_release_rrmdir($workDir);
    }
}

function bandpromo_package_run_post_update_tasks(string $root, array $applyResult): array {
    require_once __DIR__ . '/build-required.php';

    $defaultTheme = null;
    $defaultThemeError = null;

    try {
        $defaultTheme = bandpromo_ensure_default_theme_package($root);
    } catch (Throwable $throwable) {
        $defaultThemeError = $throwable->getMessage();
    }

    $buildRequired = bandpromo_mark_build_required('package_update');

    return [
        'default_theme' => $defaultTheme,
        'default_theme_error' => $defaultThemeError,
        'build_required' => $buildRequired,
        'follow_up' => !empty($buildRequired['required']) ? 'open_build_tab' : 'none',
    ];
}
