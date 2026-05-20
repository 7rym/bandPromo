<?php
declare(strict_types=1);

const BANDPROMO_RELEASE_MANIFEST_URL = 'https://github.com/7rym/bandPromo/releases/latest/download/release-manifest.json';
const BANDPROMO_DEFAULT_THEME_MARKER = 'data/default-theme-package.json';
const BANDPROMO_DEFAULT_THEME_WORKDIR = '.bandpromo-theme-package';

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

function bandpromo_release_load_manifest(string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL): array {
    $body = bandpromo_release_fetch_text($manifestUrl);
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Release manifest is not valid JSON.');
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
        'sha256' => $package['sha256'],
        'package_file' => $package['package_file'],
        'package_url' => $package['package_url'],
        'release_tag' => $package['release_tag'],
        'paths' => $package['paths'],
        'installed_at_utc' => gmdate('c'),
    ];
    file_put_contents($markerPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function bandpromo_ensure_default_theme_package(string $root, string $manifestUrl = BANDPROMO_RELEASE_MANIFEST_URL, ?callable $logger = null): array {
    bandpromo_release_log($logger, '[starter pack] Checking for the required demo content package...');
    $manifest = bandpromo_release_load_manifest($manifestUrl);
    $package = bandpromo_release_default_theme_package($manifest);
    if (bandpromo_release_default_theme_is_current($root, $package)) {
        bandpromo_release_log($logger, '[starter pack] Demo content is already present.');
        return [
            'installed' => false,
            'version' => $package['version'],
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
        'release_tag' => $package['release_tag'],
        'package_file' => $package['package_file'],
        'path_count' => count($package['paths']),
    ];
}