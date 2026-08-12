<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

const BANDPROMO_BOOTSTRAP_WORKDIR = '.bandpromo-bootstrap';
const BANDPROMO_BOOTSTRAP_DEFAULT_MANIFEST_URL = 'https://github.com/7rym/bandPromo/releases/latest/download/release-manifest.json';
const BANDPROMO_BOOTSTRAP_GITHUB_REPOSITORY = '7rym/bandPromo';
const BANDPROMO_BOOTSTRAP_GITHUB_RELEASES_API_URL = 'https://api.github.com/repos/7rym/bandPromo/releases?per_page=100';
const BANDPROMO_BOOTSTRAP_GITHUB_RELEASES_ATOM_URL = 'https://github.com/7rym/bandPromo/releases.atom';

/**
 * cURL option profiles for GitHub release downloads.
 * First profile matches the proven setup/PRP downloader (defaults only).
 * Later profiles only run if earlier attempts return empty replies.
 *
 * @return list<array<int, mixed>>
 */
function bandpromo_bootstrap_curl_profiles(): array
{
    $classic = [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => 'bandPromo bootstrap installer',
        CURLOPT_FAILONERROR => false,
    ];

    $http11 = $classic;
    $http11[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;

    $http11v4 = $http11;
    if (defined('CURL_IPRESOLVE_V4')) {
        $http11v4[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }

    $tls12 = $classic;
    if (defined('CURL_SSLVERSION_TLSv1_2')) {
        $tls12[CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2;
    }
    $tls12[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;

    return [$classic, $http11, $http11v4, $tls12];
}

/**
 * Fetch a URL body with cURL, trying several transport profiles.
 *
 * @return array{ok:bool, body:string, error:string, status:int, profile:int}
 */
function bandpromo_bootstrap_curl_fetch(string $url, int $timeoutSeconds = 120, int $redirectDepth = 0): array
{
    $last = [
        'ok' => false,
        'body' => '',
        'error' => 'cURL unavailable',
        'status' => 0,
        'profile' => -1,
    ];

    if (!extension_loaded('curl')) {
        return $last;
    }

    foreach (bandpromo_bootstrap_curl_profiles() as $profileIndex => $profile) {
        $handle = curl_init($url);
        if ($handle === false) {
            $last['error'] = 'Could not initialize cURL';
            continue;
        }

        $options = $profile;
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_TIMEOUT] = $timeoutSeconds;
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $last = [
            'ok' => false,
            'body' => is_string($body) ? $body : '',
            'error' => $error,
            'status' => $status,
            'profile' => $profileIndex,
        ];

        if ($body !== false && is_string($body) && $body !== '' && $status > 0 && $status < 400) {
            $last['ok'] = true;

            return $last;
        }
    }

    if ($redirectDepth >= 5) {
        return $last;
    }

    // Manual redirect hop (some hosts fail automatic cross-host FOLLOWLOCATION).
    $handle = curl_init($url);
    if ($handle === false) {
        return $last;
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_USERAGENT => 'bandPromo bootstrap installer',
        CURLOPT_FAILONERROR => false,
    ]);
    $raw = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    if ($raw !== false && is_string($raw) && in_array($status, [301, 302, 303, 307, 308], true)) {
        $headers = substr($raw, 0, $headerSize);
        $location = '';
        foreach (preg_split('/\r\n|\n|\r/', $headers) ?: [] as $line) {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
                break;
            }
        }
        if ($location !== '') {
            if (str_starts_with($location, '/')) {
                $parts = parse_url($url);
                $scheme = $parts['scheme'] ?? 'https';
                $host = $parts['host'] ?? 'github.com';
                $location = $scheme . '://' . $host . $location;
            }

            return bandpromo_bootstrap_curl_fetch($location, $timeoutSeconds, $redirectDepth + 1);
        }
    }

    if ($raw !== false && is_string($raw) && $status > 0 && $status < 400) {
        $body = substr($raw, $headerSize);
        if ($body !== '') {
            return [
                'ok' => true,
                'body' => $body,
                'error' => '',
                'status' => $status,
                'profile' => 99,
            ];
        }
    }

    return [
        'ok' => false,
        'body' => '',
        'error' => $error !== '' ? $error : ('HTTP ' . $status),
        'status' => $status,
        'profile' => 99,
    ];
}
function bandpromo_bootstrap_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function bandpromo_bootstrap_is_setup_complete(string $root): bool {
    return is_file($root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . '.setup_complete');
}

if (bandpromo_bootstrap_is_setup_complete(__DIR__)) {
  header('Location: /admin.php');
  exit;
}

function bandpromo_bootstrap_runtime_preserve_paths(): array {
    return [
        '.env',
        'web-config.json',
        'data',
        'log',
        'media',
        'backups',
        BANDPROMO_BOOTSTRAP_WORKDIR,
        basename(__FILE__),
    ];
}

function bandpromo_bootstrap_seed_media_paths(): array {
    return [
        'media/icons/bP-icons.zip',
    ];
}

function bandpromo_bootstrap_is_seed_media_path(string $relativePath): bool {
    $normalized = str_replace('\\', '/', ltrim($relativePath, '/\\'));

    foreach (bandpromo_bootstrap_seed_media_paths() as $seedPath) {
        if ($normalized === $seedPath) {
            return true;
        }
    }

    $fileName = basename($normalized);
    if ($fileName !== '' && preg_match('/^bandPromo_/i', $fileName) === 1) {
        return true;
    }

    return false;
}

function bandpromo_bootstrap_should_descend_preserved_dir(string $relativePath): bool {
    $normalized = str_replace('\\', '/', trim($relativePath, '/\\'));
    if ($normalized === '') {
        return false;
    }

    foreach (bandpromo_bootstrap_seed_media_paths() as $seedPath) {
        if (str_starts_with($seedPath, $normalized . '/')) {
            return true;
        }
    }

    if ($normalized === 'media' || $normalized === 'media/audio' || $normalized === 'media/audio/original') {
        return true;
    }

    return false;
}

function bandpromo_bootstrap_runtime_dirs(): array {
    return [
        'data',
        'log',
        'media',
    ];
}

function bandpromo_bootstrap_sqlite_min_version(): string
{
    return '3.8.0';
}

function bandpromo_bootstrap_sqlite_library_version(): ?string
{
    if (!extension_loaded('pdo_sqlite')) {
        return null;
    }

    try {
        $pdo = new PDO('sqlite::memory:');
        $version = $pdo->query('SELECT sqlite_version()')->fetchColumn();
        if (!is_string($version)) {
            return null;
        }

        $version = trim($version);

        return $version !== '' ? $version : null;
    } catch (Throwable $e) {
        return null;
    }
}

function bandpromo_bootstrap_https_download_available(): bool
{
    if (extension_loaded('curl')) {
        return true;
    }

    return filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)
        && extension_loaded('openssl');
}

function bandpromo_bootstrap_https_download_setup_hint(): string
{
    if (bandpromo_bootstrap_https_download_available()) {
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

    return 'Enable outbound HTTPS downloads (PHP ' . implode(' and ', $missing) . ').';
}

function bandpromo_bootstrap_install_path_is_writable(string $root): bool
{
    $root = rtrim($root, '/\\');
    if ($root !== '' && @is_writable($root)) {
        return true;
    }

    $candidates = [
        $root . DIRECTORY_SEPARATOR . 'log',
        $root . DIRECTORY_SEPARATOR . 'data',
    ];

    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir)) {
            continue;
        }

        $probe = $dir . DIRECTORY_SEPARATOR . '.bandpromo-write-test';
        if (@file_put_contents($probe, 'ok') !== false) {
            @unlink($probe);

            return true;
        }
    }

    return false;
}

function bandpromo_bootstrap_collect_environment_checks(string $root): array {
    $downloadSupport = bandpromo_bootstrap_https_download_available();
    $downloadDetail = $downloadSupport
        ? (extension_loaded('curl') ? 'curl available' : 'allow_url_fopen + openssl available')
        : bandpromo_bootstrap_https_download_setup_hint();

    $pdoSqliteOk = extension_loaded('pdo_sqlite');
    $pdoSqliteCheck = [
        'label' => 'PDO SQLite available',
        'ok' => $pdoSqliteOk,
        'detail' => $pdoSqliteOk
            ? 'Available'
            : 'Missing pdo_sqlite extension (required for listener activity logs and analytics)',
    ];

    $sqliteMin = bandpromo_bootstrap_sqlite_min_version();
    if (!$pdoSqliteOk) {
        $sqliteVersionCheck = [
            'label' => 'SQLite library ' . $sqliteMin . '+',
            'ok' => false,
            'detail' => 'pdo_sqlite is not loaded',
        ];
    } else {
        $actual = bandpromo_bootstrap_sqlite_library_version();
        if ($actual === null) {
            $sqliteVersionCheck = [
                'label' => 'SQLite library ' . $sqliteMin . '+',
                'ok' => false,
                'detail' => 'Could not read the SQLite library version bundled with PHP',
            ];
        } else {
            $sqliteOk = version_compare($actual, $sqliteMin, '>=');
            $sqliteVersionCheck = [
                'label' => 'SQLite library ' . $sqliteMin . '+',
                'ok' => $sqliteOk,
                'detail' => $sqliteOk
                    ? ('Bundled SQLite ' . $actual)
                    : ('Bundled SQLite ' . $actual . '; SQLite ' . $sqliteMin . ' or newer is required'),
            ];
        }
    }

    return [
        [
            'label' => 'PHP 8+',
            'ok' => PHP_VERSION_ID >= 80000,
            'detail' => 'Running ' . PHP_VERSION,
        ],
        $pdoSqliteCheck,
        $sqliteVersionCheck,
        [
            'label' => 'ZipArchive available',
            'ok' => class_exists('ZipArchive'),
            'detail' => class_exists('ZipArchive') ? 'Available' : 'Missing ZipArchive extension',
        ],
        [
            'label' => 'HTTPS-capable download support',
            'ok' => $downloadSupport,
            'detail' => $downloadDetail,
        ],
        [
            'label' => 'Target folder writable',
            'ok' => bandpromo_bootstrap_install_path_is_writable($root),
            'detail' => $root,
        ],
    ];
}

  function bandpromo_bootstrap_install_target_context(string $root): array {
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
      $host = 'this site';
    }

    return [
      'site' => $host,
      'folder' => $root,
    ];
  }

  function bandpromo_bootstrap_operator_check_status(array $check): array {
    $label = (string) ($check['label'] ?? 'Requirement');
    $detail = (string) ($check['detail'] ?? '');
    $ok = !empty($check['ok']);

    if ($label === 'PHP 8+') {
      return [
        'title' => 'PHP version',
        'success' => 'Your hosting is already running a compatible PHP version.',
        'failure' => 'bandPromo needs PHP 8 or newer before installation can begin.',
        'detail' => $ok ? $detail : 'PHP 8.0 or newer is required.',
      ];
    }

    if ($label === 'ZipArchive available') {
      return [
        'title' => 'ZIP support',
        'success' => 'Your hosting can unpack the bandPromo install package.',
        'failure' => 'bandPromo needs ZIP support to unpack the install package safely.',
        'detail' => $ok ? 'ZIP support is available.' : 'The PHP ZipArchive extension is required.',
      ];
    }

    if ($label === 'PDO SQLite available') {
      return [
        'title' => 'Activity log storage',
        'success' => 'Your hosting can store listener activity and analytics locally.',
        'failure' => 'bandPromo needs PDO SQLite before installation can begin.',
        'detail' => $ok ? 'PDO SQLite is available.' : 'The PHP pdo_sqlite extension is required.',
      ];
    }

    if (str_starts_with($label, 'SQLite library ')) {
      return [
        'title' => 'SQLite library version',
        'success' => 'The SQLite library bundled with PHP is new enough for activity storage.',
        'failure' => 'The SQLite library bundled with PHP is too old for bandPromo.',
        'detail' => $ok ? $detail : ('SQLite ' . bandpromo_bootstrap_sqlite_min_version() . ' or newer is required.'),
      ];
    }

    if ($label === 'HTTPS-capable download support') {
      return [
        'title' => 'Secure download support',
        'success' => 'This site can download the published bandPromo package securely.',
        'failure' => 'This site cannot yet download the published bandPromo package securely.',
        'detail' => $ok ? 'Secure download support is available.' : 'Outbound HTTPS downloads need curl or allow_url_fopen.',
      ];
    }

    if ($label === 'Target folder writable') {
      return [
        'title' => 'Install folder access',
        'success' => 'bandPromo can write the files it needs into this site folder.',
        'failure' => 'bandPromo cannot write into this site folder yet.',
        'detail' => $ok ? 'This folder is writable.' : 'The install folder needs write permission.',
      ];
    }

    return [
      'title' => $label,
      'success' => $label . ' is ready.',
      'failure' => $label . ' still needs attention.',
      'detail' => $detail,
    ];
  }

  function bandpromo_bootstrap_hosting_provider_requests(array $checks): array {
    $requests = [];

    foreach ($checks as $check) {
      if (!empty($check['ok'])) {
        continue;
      }

      $label = (string) ($check['label'] ?? '');

      if ($label === 'PHP 8+') {
        $requests[] = 'Please switch this site to PHP 8.0 or newer so bandPromo can run.';
        continue;
      }

      if ($label === 'ZipArchive available') {
        $requests[] = 'Please enable the PHP ZipArchive extension for this site so bandPromo can unpack its install package.';
        continue;
      }

      if ($label === 'PDO SQLite available') {
        $requests[] = 'Please enable the PHP pdo_sqlite extension for this site so bandPromo can store listener activity logs and analytics.';
        continue;
      }

      if (str_starts_with($label, 'SQLite library ')) {
        $requests[] = 'Please ask your host for a newer PHP build with SQLite '
          . bandpromo_bootstrap_sqlite_min_version()
          . ' or newer so bandPromo can store listener activity logs and analytics.';
        continue;
      }

      if ($label === 'HTTPS-capable download support') {
        $requests[] = 'Please enable outbound HTTPS downloads for this site by turning on curl or allow_url_fopen in PHP.';
        continue;
      }

      if ($label === 'Target folder writable') {
        $requests[] = 'Please give the site write permission to this install folder so bandPromo can place its files during setup.';
        continue;
      }

      $requests[] = 'Please review and fix this hosting requirement for bandPromo: ' . $label . '.';
    }

    return $requests;
  }

function bandpromo_bootstrap_has_blocking_failures(array $checks): bool {
    foreach ($checks as $check) {
        if (!$check['ok']) {
            return true;
        }
    }

    return false;
}

function bandpromo_bootstrap_rrmdir(string $path): void {
    if (!is_dir($path)) {
        if (file_exists($path)) {
            @unlink($path);
        }
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child) && !is_link($child)) {
            bandpromo_bootstrap_rrmdir($child);
            continue;
        }

        @unlink($child);
    }

    @rmdir($path);
}

function bandpromo_bootstrap_ensure_dir(string $path): void {
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create directory: ' . $path);
    }
}

function bandpromo_bootstrap_download_file(string $url, string $target): void {
    if (extension_loaded('curl')) {
        $lastError = '';
        $lastStatus = 0;
        foreach (bandpromo_bootstrap_curl_profiles() as $profile) {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('Could not initialize download client.');
            }

            $stream = fopen($target, 'wb');
            if ($stream === false) {
                curl_close($handle);
                throw new RuntimeException('Could not open temporary file for download.');
            }

            $options = $profile;
            $options[CURLOPT_FILE] = $stream;
            $options[CURLOPT_TIMEOUT] = 600;
            curl_setopt_array($handle, $options);

            $ok = curl_exec($handle);
            $lastError = curl_error($handle);
            $lastStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            fclose($stream);

            if ($ok !== false && $lastStatus > 0 && $lastStatus < 400 && is_file($target) && filesize($target) > 0) {
                return;
            }
            @unlink($target);
        }

        // Fall back to in-memory fetch + write (uses manual-redirect path too).
        $fetched = bandpromo_bootstrap_curl_fetch($url, 600);
        if ($fetched['ok'] && $fetched['body'] !== '') {
            if (file_put_contents($target, $fetched['body']) === false) {
                throw new RuntimeException('Could not write downloaded package to temporary storage.');
            }

            return;
        }

        throw new RuntimeException(
            'Download failed: '
            . ($fetched['error'] !== '' ? $fetched['error'] : ($lastError !== '' ? $lastError : ('HTTP ' . $lastStatus)))
        );
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 600,
            'follow_location' => 1,
            'user_agent' => 'bandPromo bootstrap installer',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        throw new RuntimeException('Download failed. Check the package URL and outbound HTTPS support.');
    }

    if (file_put_contents($target, $data) === false) {
        throw new RuntimeException('Could not write downloaded package to temporary storage.');
    }
}

function bandpromo_bootstrap_fetch_text(string $url): string {
  if (extension_loaded('curl')) {
    $fetched = bandpromo_bootstrap_curl_fetch($url, 120);
    if ($fetched['ok']) {
      return $fetched['body'];
    }

    throw new RuntimeException(
      'Manifest fetch failed: '
      . ($fetched['error'] !== '' ? $fetched['error'] : ('HTTP ' . $fetched['status']))
    );
  }

  $context = stream_context_create([
    'http' => [
      'timeout' => 120,
      'follow_location' => 1,
      'user_agent' => 'bandPromo bootstrap installer',
    ],
    'ssl' => [
      'verify_peer' => true,
      'verify_peer_name' => true,
    ],
  ]);

  $body = @file_get_contents($url, false, $context);
  if ($body === false || $body === '') {
    throw new RuntimeException('Manifest fetch failed. Check outbound HTTPS support and the manifest URL.');
  }

  return $body;
}

function bandpromo_bootstrap_is_latest_manifest_url(string $manifestUrl): bool {
  return preg_match('#/releases/latest/download/release-manifest\.json$#i', $manifestUrl) === 1;
}

function bandpromo_bootstrap_manifest_url_for_tag(string $repository, string $tag): string {
  return 'https://github.com/' . trim($repository, '/') . '/releases/download/' . rawurlencode($tag) . '/release-manifest.json';
}

function bandpromo_bootstrap_parse_version(string $version): ?array {
  $version = trim($version);

  if (preg_match('/^v(\d+)\.(\d+)\.(\d+)\s+build\s+(\d+)$/i', $version, $matches) === 1) {
    return [
      'major' => (int) $matches[1],
      'minor' => (int) $matches[2],
      'session' => (int) $matches[3],
      'build' => (int) $matches[4],
    ];
  }

  if (preg_match('/^v(\d+)\.(\d+)\s+build\s+(\d+)$/i', $version, $matches) === 1) {
    return [
      'major' => (int) $matches[1],
      'minor' => (int) $matches[2],
      'session' => 0,
      'build' => (int) $matches[3],
    ];
  }

  return null;
}

function bandpromo_bootstrap_compare_versions(string $leftVersion, string $rightVersion): int {
  $left = bandpromo_bootstrap_parse_version($leftVersion);
  $right = bandpromo_bootstrap_parse_version($rightVersion);

  if ($left === null || $right === null) {
    return strcasecmp($leftVersion, $rightVersion);
  }

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

function bandpromo_bootstrap_version_text_from_tag(string $tag): ?string {
  $tag = trim($tag);

  if (preg_match('/^v(\d+)\.(\d+)\.(\d+)-build-(\d+)$/i', $tag, $matches) === 1) {
    return sprintf('v%s.%s.%s build %s', $matches[1], $matches[2], $matches[3], $matches[4]);
  }

  if (preg_match('/^v(\d+)\.(\d+)-build-(\d+)$/i', $tag, $matches) === 1) {
    return sprintf('v%s.%s build %s', $matches[1], $matches[2], $matches[3]);
  }

  return null;
}

function bandpromo_bootstrap_parse_github_next_link(string $headers): string {
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

function bandpromo_bootstrap_fetch_github_releases_page(string $apiUrl): array {
  $nextUrl = '';

  if (extension_loaded('curl')) {
    $handle = curl_init($apiUrl);
    if ($handle === false) {
      throw new RuntimeException('Could not initialize cURL for GitHub releases fetch.');
    }

    curl_setopt_array($handle, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => 20,
      CURLOPT_TIMEOUT => 60,
      CURLOPT_USERAGENT => 'bandPromo bootstrap installer',
      CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
      CURLOPT_HEADER => true,
      CURLOPT_FAILONERROR => false,
    ]);

    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($response === false) {
      throw new RuntimeException('GitHub releases fetch failed: ' . ($error !== '' ? $error : 'Unknown cURL error'));
    }

    if ($status >= 400) {
      throw new RuntimeException('GitHub releases fetch failed with HTTP status ' . $status . '.');
    }

    $headers = substr((string) $response, 0, $headerSize);
    $body = substr((string) $response, $headerSize);
    $nextUrl = bandpromo_bootstrap_parse_github_next_link($headers);
  } else {
    $context = stream_context_create([
      'http' => [
        'timeout' => 30,
        'follow_location' => 1,
        'user_agent' => 'bandPromo bootstrap installer',
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

function bandpromo_bootstrap_fetch_github_releases(string $apiUrl = BANDPROMO_BOOTSTRAP_GITHUB_RELEASES_API_URL): array {
  $releases = [];
  $nextUrl = $apiUrl;
  $pages = 0;

  while ($nextUrl !== '' && $pages < 5) {
    $pages++;
    $page = bandpromo_bootstrap_fetch_github_releases_page($nextUrl);
    $releases = array_merge($releases, $page['releases']);
    $nextUrl = $page['next_url'];
  }

  return $releases;
}

function bandpromo_bootstrap_pick_newest_release_tag(array $releases): ?string {
  $bestTag = null;
  $bestVersion = null;

  foreach ($releases as $release) {
    if (!is_array($release)) {
      continue;
    }

    $tag = trim((string) ($release['tag_name'] ?? ''));
    $versionText = bandpromo_bootstrap_version_text_from_tag($tag);
    if ($versionText === null) {
      continue;
    }

    if ($bestVersion === null || bandpromo_bootstrap_compare_versions($bestVersion, $versionText) < 0) {
      $bestVersion = $versionText;
      $bestTag = $tag;
    }
  }

  return $bestTag;
}

function bandpromo_bootstrap_fetch_github_releases_atom(string $atomUrl = BANDPROMO_BOOTSTRAP_GITHUB_RELEASES_ATOM_URL): array
{
  $body = bandpromo_bootstrap_fetch_text($atomUrl);
  if ($body === '') {
    throw new RuntimeException('GitHub releases Atom feed was empty.');
  }

  $tags = [];
  if (preg_match_all('#/releases/tag/([^<"\s]+)#i', $body, $matches) !== false) {
    foreach ($matches[1] as $rawTag) {
      $tag = rawurldecode(trim((string) $rawTag));
      if ($tag === '' || bandpromo_bootstrap_version_text_from_tag($tag) === null) {
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

function bandpromo_bootstrap_resolve_newest_release_tag(): ?string
{
  try {
    $tag = bandpromo_bootstrap_pick_newest_release_tag(bandpromo_bootstrap_fetch_github_releases());
    if ($tag !== null) {
      return $tag;
    }
  } catch (Throwable $throwable) {
    // Shared hosts often cannot call api.github.com.
  }

  try {
    return bandpromo_bootstrap_pick_newest_release_tag(bandpromo_bootstrap_fetch_github_releases_atom());
  } catch (Throwable $throwable) {
    return null;
  }
}

function bandpromo_bootstrap_resolve_manifest_url(string $manifestUrl): string {
  if (!bandpromo_bootstrap_is_latest_manifest_url($manifestUrl)) {
    return $manifestUrl;
  }

  $tag = bandpromo_bootstrap_resolve_newest_release_tag();
  if ($tag !== null) {
    return bandpromo_bootstrap_manifest_url_for_tag(BANDPROMO_BOOTSTRAP_GITHUB_REPOSITORY, $tag);
  }

  return $manifestUrl;
}

function bandpromo_bootstrap_load_manifest(string $manifestUrl): array {
  $candidates = [];
  $resolvedUrl = bandpromo_bootstrap_resolve_manifest_url($manifestUrl);
  $candidates[] = $resolvedUrl;
  if ($resolvedUrl !== $manifestUrl) {
    $candidates[] = $manifestUrl;
  }
  // Always try the stable latest URL as a final candidate.
  if (!in_array(BANDPROMO_BOOTSTRAP_DEFAULT_MANIFEST_URL, $candidates, true)) {
    $candidates[] = BANDPROMO_BOOTSTRAP_DEFAULT_MANIFEST_URL;
  }

  $errors = [];
  foreach ($candidates as $candidateUrl) {
    try {
      $body = bandpromo_bootstrap_fetch_text($candidateUrl);
      // Windows tools sometimes rewrite UTF-8 with a BOM; PHP json_decode rejects it.
      if (str_starts_with($body, "\xEF\xBB\xBF")) {
        $body = substr($body, 3);
      }
      $decoded = json_decode($body, true);
      if (!is_array($decoded)) {
        $errors[] = $candidateUrl . ' → not valid JSON';
        continue;
      }

      if (empty($decoded['package_url']) || !is_string($decoded['package_url'])) {
        $errors[] = $candidateUrl . ' → missing package_url';
        continue;
      }

      if (empty($decoded['version']) || !is_string($decoded['version'])) {
        $errors[] = $candidateUrl . ' → missing version';
        continue;
      }

      $decoded['resolved_manifest_url'] = $candidateUrl;

      return $decoded;
    } catch (Throwable $throwable) {
      $errors[] = $candidateUrl . ' → ' . $throwable->getMessage();
    }
  }

  throw new RuntimeException(
    'Manifest fetch failed after trying GitHub release URLs. '
    . implode(' | ', $errors)
  );
}

function bandpromo_bootstrap_sha256_file(string $path): string {
  $hash = hash_file('sha256', $path);
  if (!is_string($hash) || $hash === '') {
    throw new RuntimeException('Could not calculate SHA256 for the downloaded package.');
  }

  return strtolower($hash);
}

function bandpromo_bootstrap_find_package_root(string $extractDir): string {
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

function bandpromo_bootstrap_should_preserve(string $relativePath): bool {
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/\\'));

    if (bandpromo_bootstrap_is_seed_media_path($relativePath)) {
        return false;
    }

    foreach (bandpromo_bootstrap_runtime_preserve_paths() as $preservePath) {
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

function bandpromo_bootstrap_copy_tree(string $sourceRoot, string $targetRoot, string $relativePath = ''): void {
    $sourcePath = $relativePath === '' ? $sourceRoot : $sourceRoot . DIRECTORY_SEPARATOR . $relativePath;
    $targetPath = $relativePath === '' ? $targetRoot : $targetRoot . DIRECTORY_SEPARATOR . $relativePath;

    if (is_dir($sourcePath) && !is_link($sourcePath)) {
        bandpromo_bootstrap_ensure_dir($targetPath);
        $items = scandir($sourcePath);
        if ($items === false) {
            throw new RuntimeException('Could not inspect package directory: ' . $sourcePath);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $childRelative = $relativePath === '' ? $item : $relativePath . DIRECTORY_SEPARATOR . $item;
          $childSourcePath = $sourceRoot . DIRECTORY_SEPARATOR . $childRelative;
          if (bandpromo_bootstrap_should_preserve($childRelative)) {
            if (is_dir($childSourcePath) && !is_link($childSourcePath) && bandpromo_bootstrap_should_descend_preserved_dir($childRelative)) {
              bandpromo_bootstrap_copy_tree($sourceRoot, $targetRoot, $childRelative);
            }
                continue;
            }

            bandpromo_bootstrap_copy_tree($sourceRoot, $targetRoot, $childRelative);
        }

        return;
    }

    $parent = dirname($targetPath);
    bandpromo_bootstrap_ensure_dir($parent);

    if (!copy($sourcePath, $targetPath)) {
        throw new RuntimeException('Could not copy package file into place: ' . $relativePath);
    }
}

function bandpromo_bootstrap_seed_runtime_dirs(string $root): void {
    foreach (bandpromo_bootstrap_runtime_dirs() as $directory) {
        bandpromo_bootstrap_ensure_dir($root . DIRECTORY_SEPARATOR . $directory);
    }
}

function bandpromo_bootstrap_read_version(string $root): string {
    $versionFile = $root . DIRECTORY_SEPARATOR . 'VERSION';
    if (!is_file($versionFile)) {
        return 'unknown';
    }

    $version = trim((string) file_get_contents($versionFile));
    return $version !== '' ? $version : 'unknown';
}

function bandpromo_bootstrap_install_package_from_zip(string $root, string $downloadPath, ?string $expectedSha256 = null): array {
    if (!is_file($downloadPath)) {
        throw new RuntimeException('Install package ZIP was not found.');
    }

    if ($expectedSha256 !== null && $expectedSha256 !== '') {
        $actualSha256 = bandpromo_bootstrap_sha256_file($downloadPath);
        if ($actualSha256 !== strtolower($expectedSha256)) {
            throw new RuntimeException('Uploaded package checksum did not match the expected SHA-256.');
        }
    }

    $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_BOOTSTRAP_WORKDIR;
    $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract';

    // Keep the uploaded/downloaded ZIP outside extract/; wipe previous extract only.
    if (is_dir($extractDir)) {
        bandpromo_bootstrap_rrmdir($extractDir);
    }
    bandpromo_bootstrap_ensure_dir($workDir);
    bandpromo_bootstrap_ensure_dir($extractDir);

    $zip = new ZipArchive();
    $result = $zip->open($downloadPath);
    if ($result !== true) {
        throw new RuntimeException('Could not open the install package ZIP.');
    }

    if (!$zip->extractTo($extractDir)) {
        $zip->close();
        throw new RuntimeException('Could not extract the package ZIP.');
    }
    $zip->close();

    $packageRoot = bandpromo_bootstrap_find_package_root($extractDir);
    bandpromo_bootstrap_copy_tree($packageRoot, $root);
    bandpromo_bootstrap_seed_runtime_dirs($root);

    $installedVersion = bandpromo_bootstrap_read_version($root);
    bandpromo_bootstrap_rrmdir($workDir);

    return [
        'version' => $installedVersion,
        'package_root' => $packageRoot,
    ];
}

function bandpromo_bootstrap_install_package(string $root, string $packageUrl, ?string $expectedSha256 = null): array {
    $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_BOOTSTRAP_WORKDIR;
    $downloadPath = $workDir . DIRECTORY_SEPARATOR . 'package.zip';

    bandpromo_bootstrap_rrmdir($workDir);
    bandpromo_bootstrap_ensure_dir($workDir);
    bandpromo_bootstrap_download_file($packageUrl, $downloadPath);

    return bandpromo_bootstrap_install_package_from_zip($root, $downloadPath, $expectedSha256);
}

/**
 * Lightweight outbound probe (one attempt) for support notes.
 *
 * @return list<string>
 */
function bandpromo_bootstrap_outbound_probe_notes(): array
{
    $notes = [];
    $targets = [
        'github.com /releases.atom' => BANDPROMO_BOOTSTRAP_GITHUB_RELEASES_ATOM_URL,
        'github.com latest release-manifest.json' => BANDPROMO_BOOTSTRAP_DEFAULT_MANIFEST_URL,
    ];
    foreach ($targets as $label => $url) {
        $fetched = bandpromo_bootstrap_curl_fetch($url, 20);
        if ($fetched['ok']) {
            $notes[] = $label . ': OK (' . strlen($fetched['body']) . ' bytes, profile ' . $fetched['profile'] . ')';
        } else {
            $notes[] = $label . ': FAIL — '
                . ($fetched['error'] !== '' ? $fetched['error'] : ('HTTP ' . $fetched['status']));
        }
    }

    return $notes;
}

$root = __DIR__;
$checks = bandpromo_bootstrap_collect_environment_checks($root);
$errors = [];
$successMessage = null;
$installedVersion = null;
$releaseManifest = null;
$releaseManifestError = null;
$outboundProbeNotes = [];

try {
  $releaseManifest = bandpromo_bootstrap_load_manifest(BANDPROMO_BOOTSTRAP_DEFAULT_MANIFEST_URL);
} catch (Throwable $throwable) {
  $releaseManifestError = $throwable->getMessage();
  $outboundProbeNotes = bandpromo_bootstrap_outbound_probe_notes();
}

$packageUrl = $releaseManifest !== null ? trim((string) $releaseManifest['package_url']) : '';
$expectedSha256 = $releaseManifest !== null && isset($releaseManifest['sha256']) && is_string($releaseManifest['sha256'])
  ? $releaseManifest['sha256']
  : null;
$installTarget = bandpromo_bootstrap_install_target_context($root);
$hasBlockingFailures = bandpromo_bootstrap_has_blocking_failures($checks);
$canInstall = !$hasBlockingFailures && $releaseManifest !== null && $packageUrl !== '';
$hostingProviderRequests = bandpromo_bootstrap_hosting_provider_requests($checks);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
  if ($hasBlockingFailures) {
    $errors[] = 'bandPromo stopped before making changes because this hosting setup is not ready yet.';
  }

  if ($releaseManifest === null) {
    $errors[] = 'The published bandPromo install package could not be reached right now. Please try again in a moment or ask the person managing releases to publish a package first.';
  }

  if ($packageUrl === '') {
    $errors[] = 'The published install package information is incomplete right now, so the installer cannot continue yet.';
  }

  if ($errors === []) {
    try {
      $result = bandpromo_bootstrap_install_package($root, $packageUrl, $expectedSha256);
      $installedVersion = $result['version'];
      $successMessage = 'bandPromo was installed successfully.';
    } catch (Throwable $throwable) {
      $errors[] = $throwable->getMessage();
    }
  }
}

$isSetupComplete = bandpromo_bootstrap_is_setup_complete($root);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>bandPromo Bootstrap Installer</title>
  <style>
    :root {
      --bg: #0f1115;
      --panel: #171a21;
      --panel-alt: #1d2230;
      --border: #2c3447;
      --text: #edf1f7;
      --muted: #9aa7bd;
      --accent: #ff7a59;
      --danger: #ff5b6e;
      --success: #52c17a;
      --success-strong: #6ee48f;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background: linear-gradient(180deg, #0b0d12 0%, #111522 100%);
      color: var(--text);
      padding: 32px 18px;
    }

    .shell {
      max-width: 860px;
      margin: 0 auto;
    }

    .hero,
    .panel {
      background: rgba(23, 26, 33, 0.92);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.28);
    }

    .hero {
      padding: 28px;
      margin-bottom: 20px;
    }

    .eyebrow {
      color: var(--accent);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 10px;
    }

    h1 {
      margin: 0 0 12px;
      font-size: clamp(30px, 5vw, 44px);
      line-height: 1.05;
    }

    .hero p,
    .panel p,
    li,
    label {
      color: var(--muted);
      line-height: 1.6;
    }

    .panel {
      padding: 24px;
      margin-bottom: 20px;
    }

    h2 {
      margin: 0 0 14px;
      font-size: 20px;
    }

    ul {
      margin: 0;
      padding-left: 20px;
    }

    li + li {
      margin-top: 8px;
    }

    .checks {
      display: grid;
      gap: 12px;
    }

    .check {
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 14px 16px;
      background: var(--panel-alt);
    }

    .check strong {
      display: inline-block;
      margin-right: 8px;
    }

    .ok {
      color: var(--success);
    }

    .bad {
      color: var(--danger);
    }

    .field {
      display: grid;
      gap: 8px;
      margin-top: 18px;
    }

    input[type="url"],
    input[type="text"],
    input[type="file"] {
      width: 100%;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: #101521;
      color: var(--text);
      padding: 14px 16px;
      font-size: 15px;
    }

    .upload-form {
      margin-top: 12px;
    }

    .provider-help a {
      color: var(--success-strong);
    }

    .help-note code {
      color: var(--text);
    }

    .actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 18px;
    }

    button,
    .button-link {
      border: 0;
      border-radius: 999px;
      padding: 13px 18px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    button {
      background: linear-gradient(180deg, var(--success-strong) 0%, var(--success) 100%);
      color: #102016;
      box-shadow: 0 10px 24px rgba(82, 193, 122, 0.24);
    }

    button[disabled] {
      opacity: 0.55;
      cursor: not-allowed;
      box-shadow: none;
    }

    .button-link {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
    }

    .button-link.disabled {
      opacity: 0.7;
      cursor: default;
      border-style: dashed;
    }

    .notice,
    .error-list {
      border-radius: 14px;
      padding: 16px 18px;
      margin-bottom: 18px;
    }

    .notice {
      background: rgba(82, 193, 122, 0.14);
      border: 1px solid rgba(82, 193, 122, 0.35);
    }

    .error-list {
      background: rgba(255, 91, 110, 0.12);
      border: 1px solid rgba(255, 91, 110, 0.35);
    }

        .status-banner {
          border-radius: 14px;
          padding: 16px 18px;
          margin-top: 20px;
          background: rgba(82, 193, 122, 0.12);
          border: 1px solid rgba(82, 193, 122, 0.32);
        }

        .status-banner.warning {
          background: rgba(255, 122, 89, 0.12);
          border-color: rgba(255, 122, 89, 0.28);
        }

        .status-banner strong {
          display: block;
          margin-bottom: 6px;
        }

        .hero-actions {
          display: flex;
          gap: 12px;
          flex-wrap: wrap;
          margin-top: 20px;
        }

        .mini-steps {
          display: grid;
          gap: 12px;
          margin-top: 18px;
          grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .mini-step {
          border: 1px solid var(--border);
          border-radius: 14px;
          padding: 14px 16px;
          background: rgba(29, 34, 48, 0.72);
        }

        .mini-step.success {
          background: rgba(82, 193, 122, 0.12);
          border-color: rgba(82, 193, 122, 0.35);
        }

        .mini-step.active {
          border-color: rgba(110, 228, 143, 0.45);
          box-shadow: inset 0 0 0 1px rgba(110, 228, 143, 0.08);
        }

        .mini-step strong,
        .check strong {
          display: block;
          margin-bottom: 4px;
        }

        .help-note {
          margin-top: 14px;
          font-size: 14px;
          color: var(--muted);
        }

        .provider-help {
          margin-top: 18px;
          border: 1px solid rgba(255, 122, 89, 0.28);
          background: rgba(255, 122, 89, 0.08);
          border-radius: 14px;
          padding: 16px 18px;
        }

        .provider-help h3 {
          margin: 0 0 8px;
          font-size: 16px;
        }

        details.support-note {
          margin-top: 12px;
        }

        details.support-note summary {
          cursor: pointer;
          color: var(--muted);
        }

        .status-list {
          display: grid;
          gap: 8px;
          margin: 0;
          padding-left: 20px;
        }

        .compact-stack > * + * {
          margin-top: 18px;
        }

        .step-action {
          display: flex;
          gap: 10px;
          flex-wrap: wrap;
          margin-top: 12px;
        }

        .button-link.primary {
          background: linear-gradient(180deg, var(--success-strong) 0%, var(--success) 100%);
          color: #102016;
          border: 0;
          box-shadow: 0 10px 24px rgba(82, 193, 122, 0.24);
        }

    .error-list ul {
      margin-top: 10px;
    }

    code {
      font-family: Consolas, monospace;
      color: #ffd6c2;
    }
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero">
      <div class="eyebrow">bandPromo Installer</div>
      <h1>You are only a few clicks away from running your own bandPromo site.</h1>
      <p>This page does the heavy lifting for you. If the checks below are ready, you can install bandPromo here and move straight into setup.</p>
      <?php if ($canInstall && $successMessage === null): ?>
        <div class="status-banner">
          <strong>Great news: this hosting looks ready.</strong>
          <div class="mini-steps">
            <div class="mini-step active">
              <strong>1. Install bandPromo</strong>
              The installer downloads the latest published version and places it into this site for you.
              <div class="step-action">
                <button type="submit" form="install-form">Install bandPromo now</button>
              </div>
            </div>
            <div class="mini-step">
              <strong>2. Open setup</strong>
              Setup unlocks as soon as bandPromo has been installed successfully.
            </div>
            <div class="mini-step">
              <strong>3. Make it yours</strong>
              bandPromo prepares the starter material so you can finish setup and begin customizing your own installation.
            </div>
          </div>
        </div>
      <?php elseif ($successMessage !== null): ?>
        <div class="status-banner">
          <strong>bandPromo is installed.</strong>
          <div class="mini-steps">
            <div class="mini-step success">
              <strong>1. Install bandPromo</strong>
              Installed version: <code><?= bandpromo_bootstrap_h($installedVersion ?? 'unknown') ?></code>
            </div>
            <div class="mini-step active">
              <strong>2. Open setup</strong>
              Continue straight into setup to create your first admin account and confirm the site details.
              <div class="step-action">
                <a class="button-link primary" href="setup.php">Open setup</a>
              </div>
            </div>
            <div class="mini-step">
              <strong>3. Make it yours</strong>
              Finish setup and begin customizing your installation.
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="status-banner warning">
          <strong>Almost there.</strong>
          <p>If one of the checks below needs attention, bandPromo will stop safely before changing anything. In most cases your hosting provider only needs to adjust one or two settings.</p>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel compact-stack">
      <h2>Before you install</h2>
      <?php if ($errors !== []): ?>
        <div class="error-list">
          <strong>The installer stopped safely before changing your site.</strong>
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?= bandpromo_bootstrap_h($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="checks">
        <?php foreach ($checks as $check): ?>
          <?php $operatorCheck = bandpromo_bootstrap_operator_check_status($check); ?>
          <div class="check">
            <strong class="<?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'Ready' : 'Needs attention' ?></strong>
            <?= bandpromo_bootstrap_h($operatorCheck['title']) ?><br>
            <span><?= bandpromo_bootstrap_h($check['ok'] ? $operatorCheck['success'] : $operatorCheck['failure']) ?></span><br>
            <span><?= bandpromo_bootstrap_h($operatorCheck['detail']) ?></span>
          </div>
        <?php endforeach; ?>

        <div class="check">
          <?php if ($releaseManifest !== null): ?>
            <strong class="ok">Ready</strong>
            Latest install package<br>
            <span>bandPromo found the newest published install package (including beta prereleases).</span><br>
            <span>Version <?= bandpromo_bootstrap_h((string) ($releaseManifest['version'] ?? 'unknown')) ?><?= !empty($releaseManifest['release_tag']) ? ' · ' . bandpromo_bootstrap_h((string) $releaseManifest['release_tag']) : '' ?></span>
          <?php else: ?>
            <strong class="bad">Needs attention</strong>
            Latest install package<br>
            <span>The published install package is not available right now.</span><br>
            <span>Installation is paused safely until that package can be reached.</span>
            <?php if ($releaseManifestError !== null): ?>
              <details class="support-note">
                <summary>Technical detail for support</summary>
                <p class="help-note"><?= bandpromo_bootstrap_h($releaseManifestError) ?></p>
                <?php if ($outboundProbeNotes !== []): ?>
                  <ul class="help-note">
                    <?php foreach ($outboundProbeNotes as $note): ?>
                      <li><?= bandpromo_bootstrap_h($note) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </details>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$canInstall): ?>
        <div>
          <h2>What happens next</h2>
          <div class="mini-steps">
            <div class="mini-step">
              <strong>1. Fix the blocked checks</strong>
              Use the message below if you need help from your hosting provider.
            </div>
            <div class="mini-step">
              <strong>2. Reload this page</strong>
              When the checks turn ready, the install button unlocks automatically.
            </div>
            <div class="mini-step">
              <strong>3. Install bandPromo</strong>
              Then you can move straight into the setup wizard.
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($hostingProviderRequests !== []): ?>
        <div class="provider-help">
          <h3>Message you can send to your hosting provider</h3>
          <p>Please help me prepare this site for a bandPromo installation.</p>
          <ul>
            <li>Site/domain: <code><?= bandpromo_bootstrap_h($installTarget['site']) ?></code></li>
            <li>Install folder: <code><?= bandpromo_bootstrap_h($installTarget['folder']) ?></code></li>
          </ul>
          <p>Could you make these changes for this site and folder?</p>
          <ul>
            <?php foreach ($hostingProviderRequests as $request): ?>
              <li><?= bandpromo_bootstrap_h($request) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" id="install-form">
        <input type="hidden" name="action" value="install">
      </form>
    </section>

    <?php if ($isSetupComplete): ?>
      <section class="panel">
        <div class="actions">
          <a class="button-link" href="admin.php">Open admin</a>
        </div>
      </section>
    <?php endif; ?>
  </div>
</body>
</html>