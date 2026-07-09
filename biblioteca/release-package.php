<?php
declare(strict_types=1);

const BANDPROMO_RELEASE_MANIFEST_URL = 'https://github.com/7rym/bandPromo/releases/latest/download/release-manifest.json';
const BANDPROMO_GITHUB_REPOSITORY = '7rym/bandPromo';
const BANDPROMO_GITHUB_RELEASES_API_URL = 'https://api.github.com/repos/7rym/bandPromo/releases?per_page=100';
const BANDPROMO_DEFAULT_THEME_MARKER = 'data/default-theme-package.json';
const BANDPROMO_DEFAULT_THEME_WORKDIR = '.bandpromo-theme-package';
const BANDPROMO_DEFAULT_THEME_DISPLAY_VERSION = '1.0';

function bandpromo_release_default_theme_display_version(array $package): string {
    $displayVersion = trim((string) ($package['display_version'] ?? ''));
    if ($displayVersion !== '') {
        return $displayVersion;
    }

    $version = trim((string) ($package['version'] ?? ''));
    if ($version === '') {
        return BANDPROMO_DEFAULT_THEME_DISPLAY_VERSION;
    }

    if (preg_match('/^v\d+\.\d+\s+build\s+\d+$/i', $version)) {
        return BANDPROMO_DEFAULT_THEME_DISPLAY_VERSION;
    }

    return $version;
}

function bandpromo_release_log(?callable $logger, string $message): void {
    if ($logger !== null) {
        $logger($message);
    }
}

function bandpromo_release_ensure_dir(string $path): void {
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create directory: ' . $path);
    }
}

function bandpromo_release_rrmdir(string $path): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        if (!@unlink($path)) {
            throw new RuntimeException('Could not remove file: ' . $path);
        }
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        throw new RuntimeException('Could not inspect directory: ' . $path);
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        bandpromo_release_rrmdir($path . DIRECTORY_SEPARATOR . $item);
    }

    if (!@rmdir($path)) {
        throw new RuntimeException('Could not remove directory: ' . $path);
    }
}

function bandpromo_release_fetch_text(string $url): string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL for release manifest fetch.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'bandPromo release helper',
            CURLOPT_FAILONERROR => false,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Release manifest fetch failed: ' . ($error !== '' ? $error : 'Unknown cURL error'));
        }

        if ($status >= 400) {
            throw new RuntimeException('Release manifest fetch failed with HTTP status ' . $status . '.');
        }

        return (string) $body;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'follow_location' => 1,
            'user_agent' => 'bandPromo release helper',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException('Release manifest fetch failed. Check outbound HTTPS support and the manifest URL.');
    }

    return (string) $body;
}

function bandpromo_release_download_file(string $url, string $destination): void {
    bandpromo_release_ensure_dir(dirname($destination));

    if (function_exists('curl_init')) {
        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not create temporary download file.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            fclose($handle);
            throw new RuntimeException('Could not initialize cURL for package download.');
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'bandPromo release helper',
            CURLOPT_FAILONERROR => false,
        ]);

        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($ok === false) {
            @unlink($destination);
            throw new RuntimeException('Package download failed: ' . ($error !== '' ? $error : 'Unknown cURL error'));
        }

        if ($status >= 400) {
            @unlink($destination);
            throw new RuntimeException('Package download failed with HTTP status ' . $status . '.');
        }

        return;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 120,
            'follow_location' => 1,
            'user_agent' => 'bandPromo release helper',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $source = @fopen($url, 'rb', false, $context);
    if ($source === false) {
        throw new RuntimeException('Package download failed. Check outbound HTTPS support and the package URL.');
    }

    $target = fopen($destination, 'wb');
    if ($target === false) {
        fclose($source);
        throw new RuntimeException('Could not create temporary download file.');
    }

    stream_copy_to_stream($source, $target);
    fclose($source);
    fclose($target);
}

function bandpromo_release_sha256_file(string $path): string {
    $hash = hash_file('sha256', $path);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Could not calculate SHA256 for downloaded package.');
    }

    return strtolower($hash);
}

function bandpromo_release_copy_tree(string $sourceRoot, string $targetRoot, string $relativePath = ''): void {
    $sourcePath = $relativePath === '' ? $sourceRoot : $sourceRoot . DIRECTORY_SEPARATOR . $relativePath;
    $targetPath = $relativePath === '' ? $targetRoot : $targetRoot . DIRECTORY_SEPARATOR . $relativePath;

    if (is_dir($sourcePath) && !is_link($sourcePath)) {
        bandpromo_release_ensure_dir($targetPath);
        $items = scandir($sourcePath);
        if ($items === false) {
            throw new RuntimeException('Could not inspect extracted package directory: ' . $sourcePath);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $childRelative = $relativePath === '' ? $item : $relativePath . DIRECTORY_SEPARATOR . $item;
            bandpromo_release_copy_tree($sourceRoot, $targetRoot, $childRelative);
        }

        return;
    }

    bandpromo_release_ensure_dir(dirname($targetPath));
    if (!copy($sourcePath, $targetPath)) {
        throw new RuntimeException('Could not copy extracted package file into place: ' . $relativePath);
    }
}

function bandpromo_release_parse_version(string $version): ?array {
    $version = trim($version);

    if (preg_match('/^v(\d+)\.(\d+)\.(\d+)\s+build\s+(\d+)$/i', $version, $matches) === 1) {
        return [
            'major' => (int) $matches[1],
            'minor' => (int) $matches[2],
            'session' => (int) $matches[3],
            'build' => (int) $matches[4],
            'raw' => $version,
        ];
    }

    if (preg_match('/^v(\d+)\.(\d+)\s+build\s+(\d+)$/i', $version, $matches) === 1) {
        return [
            'major' => (int) $matches[1],
            'minor' => (int) $matches[2],
            'session' => 0,
            'build' => (int) $matches[3],
            'raw' => $version,
        ];
    }

    return null;
}

function bandpromo_release_compare_versions(string $installed, string $remote): int {
    $left = bandpromo_release_parse_version($installed);
    $right = bandpromo_release_parse_version($remote);

    if ($left === null || $right === null) {
        return strcasecmp($installed, $remote);
    }

    // Published packages use a monotonic build number across legacy and sessioned VERSION formats.
    if ($left['build'] !== $right['build']) {
        return $left['build'] <=> $right['build'];
    }

    foreach (['major', 'minor', 'session'] as $key) {
        if ($left[$key] !== $right[$key]) {
            return $left[$key] <=> $right[$key];
        }
    }

    return 0;
}

function bandpromo_release_version_text_from_tag(string $tag): ?string {
    $tag = trim($tag);

    if (preg_match('/^v(\d+)\.(\d+)\.(\d+)-build-(\d+)$/i', $tag, $matches) === 1) {
        return sprintf('v%s.%s.%s build %s', $matches[1], $matches[2], $matches[3], $matches[4]);
    }

    if (preg_match('/^v(\d+)\.(\d+)-build-(\d+)$/i', $tag, $matches) === 1) {
        return sprintf('v%s.%s build %s', $matches[1], $matches[2], $matches[3]);
    }

    return null;
}

function bandpromo_release_manifest_url_for_tag(string $repository, string $tag): string {
    return 'https://github.com/' . trim($repository, '/') . '/releases/download/' . rawurlencode($tag) . '/release-manifest.json';
}

function bandpromo_release_is_latest_manifest_url(string $manifestUrl): bool {
    return preg_match('#/releases/latest/download/release-manifest\.json$#i', $manifestUrl) === 1;
}

function bandpromo_release_fetch_github_releases(string $apiUrl = BANDPROMO_GITHUB_RELEASES_API_URL): array {
    $releases = [];
    $nextUrl = $apiUrl;
    $pages = 0;

    while ($nextUrl !== '' && $pages < 5) {
        $pages++;
        $page = bandpromo_release_fetch_github_releases_page($nextUrl);
        $releases = array_merge($releases, $page['releases']);
        $nextUrl = $page['next_url'];
    }

    return $releases;
}

function bandpromo_release_fetch_github_releases_page(string $apiUrl): array {
    $nextUrl = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL for GitHub releases fetch.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'bandPromo release helper',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
            CURLOPT_HEADER => true,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('GitHub releases fetch failed: ' . ($error !== '' ? $error : 'Unknown cURL error'));
        }

        if ($status >= 400) {
            throw new RuntimeException('GitHub releases fetch failed with HTTP status ' . $status . '.');
        }

        $headers = substr((string) $response, 0, $headerSize);
        $body = substr((string) $response, $headerSize);
        $nextUrl = bandpromo_release_parse_github_next_link($headers);
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'follow_location' => 1,
                'user_agent' => 'bandPromo release helper',
                'header' => "Accept: application/vnd.github+json\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($apiUrl, false, $context);
        if ($body === false) {
            throw new RuntimeException('GitHub releases fetch failed. Check outbound HTTPS support.');
        }
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('GitHub releases response is not valid JSON.');
    }

    return [
        'releases' => $decoded,
        'next_url' => $nextUrl,
    ];
}

function bandpromo_release_parse_github_next_link(string $headers): string
{
    foreach (preg_split('/\r\n|\n|\r/', $headers) ?: [] as $line) {
        if (stripos($line, 'Link:') !== 0) {
            continue;
        }
        if (preg_match('/<([^>]+)>;\s*rel="next"/i', $line, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }
    }

    return '';
}

function bandpromo_release_pick_newest_release_tag(array $releases): ?string {
    $bestTag = null;
    $bestVersion = null;

    foreach ($releases as $release) {
        if (!is_array($release)) {
            continue;
        }

        $tag = trim((string) ($release['tag_name'] ?? ''));
        $versionText = bandpromo_release_version_text_from_tag($tag);
        if ($versionText === null) {
            continue;
        }

        if ($bestVersion === null || bandpromo_release_compare_versions($bestVersion, $versionText) < 0) {
            $bestVersion = $versionText;
            $bestTag = $tag;
        }
    }

    return $bestTag;
}

function bandpromo_release_resolve_manifest_url(string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL): string {
    if (!bandpromo_release_is_latest_manifest_url($manifestUrl)) {
        return $manifestUrl;
    }

    try {
        $releases = bandpromo_release_fetch_github_releases();
        $tag = bandpromo_release_pick_newest_release_tag($releases);
        if ($tag !== null) {
            return bandpromo_release_manifest_url_for_tag(BANDPROMO_GITHUB_REPOSITORY, $tag);
        }
    } catch (Throwable $throwable) {
        // Fall back to GitHub's latest stable release URL when the API is unavailable.
    }

    return $manifestUrl;
}

function bandpromo_release_load_manifest(string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL): array {
    $resolvedUrl = bandpromo_release_resolve_manifest_url($manifestUrl);
    $body = bandpromo_release_fetch_text($resolvedUrl);
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Release manifest is not valid JSON.');
    }

    return $decoded;
}

function bandpromo_release_load_manifest_file(string $path): array {
    if (!is_file($path)) {
        throw new RuntimeException('Local release manifest file is missing: ' . $path);
    }

    $body = file_get_contents($path);
    if (!is_string($body) || trim($body) === '') {
        throw new RuntimeException('Local release manifest file could not be read: ' . $path);
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Local release manifest is not valid JSON: ' . $path);
    }

    return $decoded;
}

function bandpromo_release_normalize_paths(array $paths): array {
    $normalized = [];
    foreach ($paths as $path) {
        if (!is_string($path)) {
            continue;
        }

        $candidate = str_replace('\\', '/', ltrim(trim($path), '/\\'));
        if ($candidate === '') {
            continue;
        }

        $normalized[$candidate] = true;
    }

    return array_keys($normalized);
}

function bandpromo_release_default_theme_package(array $manifest): array {
    $package = $manifest['default_theme_package'] ?? null;
    if (!is_array($package)) {
        throw new RuntimeException('Published release manifest is missing the required default theme package metadata.');
    }

    $packageUrl = $package['package_url'] ?? null;
    $sha256 = $package['sha256'] ?? null;
    $version = $package['version'] ?? null;
    $paths = bandpromo_release_normalize_paths(is_array($package['paths'] ?? null) ? $package['paths'] : []);

    if (!is_string($packageUrl) || trim($packageUrl) === '') {
        throw new RuntimeException('Published release manifest is missing default theme package_url.');
    }
    if (!is_string($sha256) || trim($sha256) === '') {
        throw new RuntimeException('Published release manifest is missing default theme sha256.');
    }
    if (!is_string($version) || trim($version) === '') {
        throw new RuntimeException('Published release manifest is missing default theme version.');
    }
    if ($paths === []) {
        throw new RuntimeException('Published release manifest is missing default theme asset paths.');
    }

    return [
        'version' => trim($version),
        'display_version' => bandpromo_release_default_theme_display_version($package),
        'package_file' => is_string($package['package_file'] ?? null) ? trim((string) $package['package_file']) : '',
        'package_url' => trim($packageUrl),
        'sha256' => strtolower(trim($sha256)),
        'paths' => $paths,
        'release_tag' => is_string($package['release_tag'] ?? null) ? trim((string) $package['release_tag']) : '',
    ];
}

function bandpromo_release_default_theme_marker_path(string $root): string {
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, BANDPROMO_DEFAULT_THEME_MARKER);
}

function bandpromo_release_default_theme_paths_present(string $root, array $paths): bool {
    foreach ($paths as $relativePath) {
        $fullPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($fullPath)) {
            return false;
        }
    }

    return true;
}

function bandpromo_release_default_theme_is_current(string $root, array $package): bool {
    $markerPath = bandpromo_release_default_theme_marker_path($root);
    if (!is_file($markerPath)) {
        return false;
    }

    $decoded = json_decode((string) file_get_contents($markerPath), true);
    if (!is_array($decoded)) {
        return false;
    }

    if (($decoded['version'] ?? null) !== $package['version']) {
        return false;
    }
    if (($decoded['sha256'] ?? null) !== $package['sha256']) {
        return false;
    }

    return bandpromo_release_default_theme_paths_present($root, $package['paths']);
}

function bandpromo_release_write_default_theme_marker(string $root, array $package): void {
    $markerPath = bandpromo_release_default_theme_marker_path($root);
    bandpromo_release_ensure_dir(dirname($markerPath));
    $payload = [
        'version' => $package['version'],
        'display_version' => bandpromo_release_default_theme_display_version($package),
        'sha256' => $package['sha256'],
        'package_file' => $package['package_file'],
        'package_url' => $package['package_url'],
        'release_tag' => $package['release_tag'],
        'paths' => $package['paths'],
        'installed_at_utc' => gmdate('c'),
    ];
    file_put_contents($markerPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function bandpromo_release_default_theme_from_marker(string $root): ?array {
    $markerPath = bandpromo_release_default_theme_marker_path($root);
    if (!is_file($markerPath)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($markerPath), true);
    if (!is_array($decoded)) {
        return null;
    }

    $version = trim((string) ($decoded['version'] ?? ''));
    $sha256 = strtolower(trim((string) ($decoded['sha256'] ?? '')));
    $packageUrl = trim((string) ($decoded['package_url'] ?? ''));
    $source = trim((string) ($decoded['source'] ?? ''));
    $paths = bandpromo_release_normalize_paths(is_array($decoded['paths'] ?? null) ? $decoded['paths'] : []);
    if ($version === '' || $paths === []) {
        return null;
    }

    if (!bandpromo_release_default_theme_paths_present($root, $paths)) {
        return null;
    }

    $isLocalSourceTree = $version === 'local-source-tree' || $source === 'inferred-from-local-files';
    if (!$isLocalSourceTree && $sha256 === '') {
        return null;
    }

    return [
        'version' => $version,
        'display_version' => bandpromo_release_default_theme_display_version($decoded),
        'package_file' => trim((string) ($decoded['package_file'] ?? '')),
        'package_url' => $packageUrl,
        'sha256' => $sha256,
        'paths' => $paths,
        'release_tag' => trim((string) ($decoded['release_tag'] ?? '')),
    ];
}

function bandpromo_release_default_theme_from_local_manifest(string $root): ?array {
    $localManifestPath = $root . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'validate' . DIRECTORY_SEPARATOR . 'release-manifest.json';
    if (!is_file($localManifestPath)) {
        return null;
    }

    try {
        $manifest = bandpromo_release_load_manifest_file($localManifestPath);
        $package = bandpromo_release_default_theme_package($manifest);
    } catch (Throwable $throwable) {
        return null;
    }

    if (!bandpromo_release_default_theme_paths_present($root, $package['paths'])) {
        return null;
    }

    return $package;
}

function bandpromo_release_default_theme_local_fallback(string $root): ?array {
    $package = bandpromo_release_default_theme_from_marker($root);
    if ($package !== null) {
        return $package;
    }

    return bandpromo_release_default_theme_from_local_manifest($root);
}

function bandpromo_ensure_default_theme_package(string $root, string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL, ?callable $logger = null): array {
    bandpromo_release_log($logger, '[starter pack] Checking for the required demo content package...');
    try {
        $manifest = bandpromo_release_load_manifest($manifestUrl);
        $package = bandpromo_release_default_theme_package($manifest);
    } catch (Throwable $throwable) {
        $fallbackPackage = bandpromo_release_default_theme_local_fallback($root);
        if ($fallbackPackage !== null) {
            bandpromo_release_log($logger, '[starter pack] Remote package check failed, but required demo content is already present locally. Continuing with local starter assets.');
            return [
                'installed' => false,
                'version' => $fallbackPackage['version'],
                'display_version' => bandpromo_release_default_theme_display_version($fallbackPackage),
                'release_tag' => $fallbackPackage['release_tag'],
                'package_file' => $fallbackPackage['package_file'],
                'path_count' => count($fallbackPackage['paths']),
                'source' => 'local-fallback',
            ];
        }

        throw $throwable;
    }

    if (bandpromo_release_default_theme_is_current($root, $package)) {
        bandpromo_release_log($logger, '[starter pack] Demo content is already present.');
        return [
            'installed' => false,
            'version' => $package['version'],
            'display_version' => bandpromo_release_default_theme_display_version($package),
            'release_tag' => $package['release_tag'],
            'package_file' => $package['package_file'],
            'path_count' => count($package['paths']),
        ];
    }

    $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_DEFAULT_THEME_WORKDIR;
    $downloadPath = $workDir . DIRECTORY_SEPARATOR . 'default-theme.zip';
    $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract';

    bandpromo_release_rrmdir($workDir);
    bandpromo_release_ensure_dir($extractDir);

    bandpromo_release_log($logger, '[starter pack] Downloading demo content package...');
    bandpromo_release_download_file($package['package_url'], $downloadPath);

    bandpromo_release_log($logger, '[starter pack] Verifying downloaded demo content package...');
    $actualSha256 = bandpromo_release_sha256_file($downloadPath);
    if ($actualSha256 !== $package['sha256']) {
        throw new RuntimeException('Default theme package checksum did not match the published release manifest.');
    }

    $zip = new ZipArchive();
    $result = $zip->open($downloadPath);
    if ($result !== true) {
        throw new RuntimeException('Could not open downloaded default theme package ZIP.');
    }

    bandpromo_release_log($logger, '[starter pack] Extracting demo content package...');
    if (!$zip->extractTo($extractDir)) {
        $zip->close();
        throw new RuntimeException('Could not extract the default theme package ZIP.');
    }
    $zip->close();

    bandpromo_release_log($logger, '[starter pack] Installing demo content into this site...');
    bandpromo_release_copy_tree($extractDir, $root);
    if (!bandpromo_release_default_theme_paths_present($root, $package['paths'])) {
        throw new RuntimeException('Default theme package was extracted, but required asset files are still missing.');
    }

    bandpromo_release_write_default_theme_marker($root, $package);
    bandpromo_release_rrmdir($workDir);
    bandpromo_release_log($logger, '[starter pack] Demo content package installed successfully.');

    return [
        'installed' => true,
        'version' => $package['version'],
        'display_version' => bandpromo_release_default_theme_display_version($package),
        'release_tag' => $package['release_tag'],
        'package_file' => $package['package_file'],
        'path_count' => count($package['paths']),
    ];
}