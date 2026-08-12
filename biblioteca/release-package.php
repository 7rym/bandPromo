<?php
declare(strict_types=1);

const BANDPROMO_RELEASE_MANIFEST_URL = 'https://github.com/7rym/bandPromo/releases/latest/download/release-manifest.json';
const BANDPROMO_GITHUB_REPOSITORY = '7rym/bandPromo';
const BANDPROMO_GITHUB_RELEASES_API_URL = 'https://api.github.com/repos/7rym/bandPromo/releases?per_page=100';
const BANDPROMO_GITHUB_RELEASES_ATOM_URL = 'https://github.com/7rym/bandPromo/releases.atom';
/** Durable Demo PRP lives on a fixed release tag, not every app build. */
const BANDPROMO_DEMO_CONTENT_TAG = 'demo-content';
const BANDPROMO_DEMO_MANIFEST_URL = 'https://github.com/7rym/bandPromo/releases/download/demo-content/demo-manifest.json';
const BANDPROMO_DEMO_PRP_URL = 'https://github.com/7rym/bandPromo/releases/download/demo-content/bandPromo-demo.prp';
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

function bandpromo_release_rrmdir(string $path, int $retries = 3): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        $basename = strtolower(basename($path));
        // Google Drive recreates desktop.ini; never let it block workdir cleanup.
        if ($basename === 'desktop.ini') {
            @chmod($path, 0666);
        }
        if (!@unlink($path)) {
            throw new RuntimeException('Could not remove file: ' . $path);
        }
        return;
    }

    $items = @scandir($path);
    if ($items === false) {
        throw new RuntimeException('Could not inspect directory: ' . $path);
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        bandpromo_release_rrmdir($path . DIRECTORY_SEPARATOR . $item, $retries);
    }

    $attempts = max(1, $retries);
    for ($i = 0; $i < $attempts; $i++) {
        if (@rmdir($path)) {
            return;
        }
        // Windows + Google Drive often holds the folder briefly after extract/copy.
        usleep(150000 * ($i + 1));
        clearstatcache(true, $path);
        if (!file_exists($path)) {
            return;
        }
        // Retry deleting any desktop.ini Google Drive may have rewritten mid-cleanup.
        $desktopIni = $path . DIRECTORY_SEPARATOR . 'desktop.ini';
        if (is_file($desktopIni) || is_link($desktopIni)) {
            @chmod($desktopIni, 0666);
            @unlink($desktopIni);
        }
    }

    throw new RuntimeException('Could not remove directory: ' . $path);
}

/**
 * Best-effort recursive delete for temporary workdirs.
 * Returns false when the tree cannot be fully removed (e.g. Google Drive lock).
 */
function bandpromo_release_rrmdir_best_effort(string $path): bool
{
    try {
        bandpromo_release_rrmdir($path);
        return true;
    } catch (Throwable $throwable) {
        return false;
    }
}

function bandpromo_release_https_download_available(): bool
{
    if (extension_loaded('curl')) {
        return true;
    }

    return filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)
        && extension_loaded('openssl');
}

function bandpromo_release_https_download_setup_hint(): string
{
    if (bandpromo_release_https_download_available()) {
        return '';
    }

    $missing = [];
    if (!extension_loaded('curl')) {
        $missing[] = 'curl';
    }
    if (!extension_loaded('openssl')) {
        $missing[] = 'openssl';
    }

    if ($missing === []) {
        return 'Enable allow_url_fopen or the PHP curl extension in php.ini.';
    }

    return 'Enable the PHP ' . implode(' and ', $missing) . ' extension' . (count($missing) > 1 ? 's' : '') . ' in php.ini.';
}

function bandpromo_release_curl_profiles(): array
{
    $classic = [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => 'bandPromo release helper',
        CURLOPT_FAILONERROR => false,
    ];

    $http11 = $classic;
    $http11[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;

    $http11v4 = $http11;
    if (defined('CURL_IPRESOLVE_V4')) {
        $http11v4[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }

    return [$classic, $http11, $http11v4];
}

function bandpromo_release_fetch_text(string $url): string {
    if (function_exists('curl_init')) {
        $lastError = '';
        $lastStatus = 0;
        foreach (bandpromo_release_curl_profiles() as $profile) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Could not initialize cURL for release manifest fetch.');
            }

            $options = $profile;
            $options[CURLOPT_RETURNTRANSFER] = true;
            curl_setopt_array($ch, $options);

            $body = curl_exec($ch);
            $lastStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $lastError = curl_error($ch);
            curl_close($ch);

            if ($body !== false && is_string($body) && $body !== '' && $lastStatus > 0 && $lastStatus < 400) {
                return $body;
            }
        }

        throw new RuntimeException(
            'Release manifest fetch failed: '
            . ($lastError !== '' ? $lastError : ('HTTP ' . $lastStatus))
        );
    }

    if (!bandpromo_release_https_download_available()) {
        $hint = bandpromo_release_https_download_setup_hint();
        throw new RuntimeException('Release manifest fetch failed. ' . ($hint !== '' ? $hint : 'HTTPS download support is not configured.'));
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

    $body = @file_get_contents($url, false, $context);
    if ($body === false || $body === '') {
        throw new RuntimeException('Release manifest fetch failed. Check outbound HTTPS support and the manifest URL.');
    }

    return (string) $body;
}

function bandpromo_release_download_file(string $url, string $destination): void {
    bandpromo_release_ensure_dir(dirname($destination));

    if (function_exists('curl_init')) {
        $lastError = '';
        $lastStatus = 0;
        foreach (bandpromo_release_curl_profiles() as $profile) {
            $handle = fopen($destination, 'wb');
            if ($handle === false) {
                throw new RuntimeException('Could not create temporary download file.');
            }

            $ch = curl_init($url);
            if ($ch === false) {
                fclose($handle);
                throw new RuntimeException('Could not initialize cURL for package download.');
            }

            $options = $profile;
            $options[CURLOPT_FILE] = $handle;
            $options[CURLOPT_TIMEOUT] = 600;
            curl_setopt_array($ch, $options);

            $ok = curl_exec($ch);
            $lastStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $lastError = curl_error($ch);
            curl_close($ch);
            fclose($handle);

            if ($ok !== false && $lastStatus > 0 && $lastStatus < 400 && is_file($destination) && filesize($destination) > 0) {
                return;
            }
            @unlink($destination);
        }

        throw new RuntimeException(
            'Package download failed: '
            . ($lastError !== '' ? $lastError : ('HTTP ' . $lastStatus))
        );
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 600,
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
    $basename = strtolower(basename($sourcePath));

    // Google Drive injects desktop.ini into extracted folders; never install those.
    if ($basename === 'desktop.ini') {
        return;
    }

    if (is_dir($sourcePath) && !is_link($sourcePath)) {
        bandpromo_release_ensure_dir($targetPath);
        $items = scandir($sourcePath);
        if ($items === false) {
            throw new RuntimeException('Could not inspect extracted package directory: ' . $sourcePath);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || strcasecmp($item, 'desktop.ini') === 0) {
                continue;
            }

            $childRelative = $relativePath === '' ? $item : $relativePath . DIRECTORY_SEPARATOR . $item;
            bandpromo_release_copy_tree($sourceRoot, $targetRoot, $childRelative);
        }

        return;
    }

    bandpromo_release_ensure_dir(dirname($targetPath));
    if (!@copy($sourcePath, $targetPath)) {
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

/**
 * Parse release tags from the public GitHub Releases Atom feed.
 * Includes prereleases and does not depend on api.github.com rate limits.
 *
 * @return list<array{tag_name:string}>
 */
function bandpromo_release_fetch_github_releases_atom(string $atomUrl = BANDPROMO_GITHUB_RELEASES_ATOM_URL): array
{
    $body = bandpromo_release_fetch_text($atomUrl);
    if ($body === '') {
        throw new RuntimeException('GitHub releases Atom feed was empty.');
    }

    $tags = [];
    if (preg_match_all('#/releases/tag/([^<"\s]+)#i', $body, $matches) !== false) {
        foreach ($matches[1] as $rawTag) {
            $tag = rawurldecode(trim((string) $rawTag));
            if ($tag === '' || bandpromo_release_version_text_from_tag($tag) === null) {
                continue;
            }
            $tags[$tag] = ['tag_name' => $tag];
        }
    }

    if ($tags === []) {
        throw new RuntimeException('GitHub releases Atom feed had no recognizable release tags.');
    }

    return array_values($tags);
}

function bandpromo_release_resolve_newest_release_tag(): ?string
{
    try {
        $tag = bandpromo_release_pick_newest_release_tag(bandpromo_release_fetch_github_releases());
        if ($tag !== null) {
            return $tag;
        }
    } catch (Throwable $throwable) {
        // Shared hosts often cannot call api.github.com (rate limit / block).
    }

    try {
        return bandpromo_release_pick_newest_release_tag(bandpromo_release_fetch_github_releases_atom());
    } catch (Throwable $throwable) {
        return null;
    }
}

function bandpromo_release_resolve_manifest_url(string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL): string {
    if (!bandpromo_release_is_latest_manifest_url($manifestUrl)) {
        return $manifestUrl;
    }

    $tag = bandpromo_release_resolve_newest_release_tag();
    if ($tag !== null) {
        return bandpromo_release_manifest_url_for_tag(BANDPROMO_GITHUB_REPOSITORY, $tag);
    }

    // Last resort: GitHub's non-prerelease "latest" URL.
    return $manifestUrl;
}

function bandpromo_release_load_manifest(string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL): array {
    $resolvedUrl = bandpromo_release_resolve_manifest_url($manifestUrl);
    $body = bandpromo_release_fetch_text($resolvedUrl);
    // Windows tools sometimes rewrite UTF-8 with a BOM; PHP json_decode rejects it.
    if (str_starts_with($body, "\xEF\xBB\xBF")) {
        $body = substr($body, 3);
    }
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
    if (str_starts_with($body, "\xEF\xBB\xBF")) {
        $body = substr($body, 3);
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

    if (!bandpromo_release_rrmdir_best_effort($workDir)) {
        bandpromo_release_log(
            $logger,
            '[starter pack] Could not fully clear previous package workdir (often Google Drive lock). Continuing with a fresh extract folder…'
        );
        // Prefer a unique extract dir when the old workdir is stuck.
        $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract-' . gmdate('YmdHis');
    }
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
    if (!bandpromo_release_rrmdir_best_effort($workDir)) {
        bandpromo_release_log(
            $logger,
            '[starter pack] Demo content installed; leftover package workdir could not be removed yet (Google Drive often locks extract/). Safe to ignore.'
        );
    }
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

/**
 * Ensure PWA/favicon icons exist under media/icons (from local zip or app package).
 *
 * @return array{installed: bool, source: string, message: string}
 */
function bandpromo_ensure_install_icons(string $root, string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL, ?callable $logger = null): array
{
    $iconsDir = $root . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'icons';
    $required = [
        'apple-touch-icon.png',
        'favicon-16x16.png',
        'favicon-32x32.png',
        'favicon-96x96.png',
        'favicon.ico',
        'web-app-manifest-192x192.png',
        'web-app-manifest-512x512.png',
    ];
    $missing = [];
    foreach ($required as $name) {
        if (!is_file($iconsDir . DIRECTORY_SEPARATOR . $name)) {
            $missing[] = $name;
        }
    }
    if ($missing === []) {
        return [
            'installed' => false,
            'source' => 'present',
            'message' => 'Install icons already present.',
        ];
    }

    if (!is_dir($iconsDir) && !mkdir($iconsDir, 0755, true) && !is_dir($iconsDir)) {
        throw new RuntimeException('Could not create media/icons for install icons.');
    }

    $localZip = $iconsDir . DIRECTORY_SEPARATOR . 'bP-icons.zip';
    if (is_file($localZip) && class_exists('ZipArchive')) {
        bandpromo_release_log($logger, '[icons] Extracting install icons from media/icons/bP-icons.zip...');
        $zip = new ZipArchive();
        if ($zip->open($localZip) === true) {
            $zip->extractTo($iconsDir);
            $zip->close();
            $still = [];
            foreach ($required as $name) {
                if (!is_file($iconsDir . DIRECTORY_SEPARATOR . $name)) {
                    $still[] = $name;
                }
            }
            if ($still === []) {
                return [
                    'installed' => true,
                    'source' => 'local-zip',
                    'message' => 'Install icons extracted from bP-icons.zip.',
                ];
            }
        }
    }

    bandpromo_release_log($logger, '[icons] Downloading install icons from the published application package...');
    $manifest = bandpromo_release_load_manifest($manifestUrl);
    $packageUrl = trim((string) ($manifest['package_url'] ?? ''));
    $expectedSha = trim((string) ($manifest['sha256'] ?? ''));
    if ($packageUrl === '' || $expectedSha === '') {
        throw new RuntimeException('Release manifest is missing package_url/sha256 while seeding icons.');
    }
    $workDir = $root . DIRECTORY_SEPARATOR . '.bandpromo-icons-package';
    $downloadPath = $workDir . DIRECTORY_SEPARATOR . 'bandPromo.zip';
    bandpromo_release_rrmdir($workDir);
    bandpromo_release_ensure_dir($workDir);
    bandpromo_release_download_file($packageUrl, $downloadPath);
    $actual = bandpromo_release_sha256_file($downloadPath);
    if ($actual !== $expectedSha) {
        throw new RuntimeException('Application package checksum did not match while seeding icons.');
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is required to extract install icons.');
    }
    $zip = new ZipArchive();
    if ($zip->open($downloadPath) !== true) {
        throw new RuntimeException('Could not open application package to extract icons.');
    }
    $extracted = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        if ($name === '' || str_ends_with($name, '/')) {
            continue;
        }
        if (!str_starts_with($name, 'media/icons/')) {
            continue;
        }
        $relative = substr($name, strlen('media/icons/'));
        if ($relative === '' || str_contains($relative, '..')) {
            continue;
        }
        $target = $iconsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            $zip->close();
            throw new RuntimeException('Could not create icon path: ' . $relative);
        }
        $bytes = $zip->getFromIndex($i);
        if ($bytes === false || file_put_contents($target, $bytes) === false) {
            $zip->close();
            throw new RuntimeException('Could not extract icon: ' . $relative);
        }
        $extracted++;
    }
    $zip->close();
    bandpromo_release_rrmdir_best_effort($workDir);

    $still = [];
    foreach ($required as $name) {
        if (!is_file($iconsDir . DIRECTORY_SEPARATOR . $name)) {
            $still[] = $name;
        }
    }
    if ($still !== []) {
        throw new RuntimeException('Install icons still missing after application package extract: ' . implode(', ', $still));
    }

    bandpromo_release_log($logger, '[icons] Seeded ' . $extracted . ' icon file(s) from application package.');

    return [
        'installed' => true,
        'source' => 'app-package',
        'message' => 'Install icons seeded from application package.',
    ];
}