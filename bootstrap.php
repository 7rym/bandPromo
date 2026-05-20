<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

const BANDPROMO_BOOTSTRAP_WORKDIR = '.bandpromo-bootstrap';
const BANDPROMO_BOOTSTRAP_DEFAULT_MANIFEST_URL = 'https://github.com/7rym/bandPromo/releases/latest/download/release-manifest.json';

function bandpromo_bootstrap_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function bandpromo_bootstrap_is_setup_complete(string $root): bool {
    return is_file($root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . '.setup_complete');
}

function bandpromo_bootstrap_runtime_preserve_paths(): array {
    return [
        '.env',
        'web-config.json',
        'data',
        'log',
        'media',
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

function bandpromo_bootstrap_collect_environment_checks(string $root): array {
    $downloadSupport = extension_loaded('curl') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);

    return [
        [
            'label' => 'PHP 8+',
            'ok' => PHP_VERSION_ID >= 80000,
            'detail' => 'Running ' . PHP_VERSION,
        ],
        [
            'label' => 'ZipArchive available',
            'ok' => class_exists('ZipArchive'),
            'detail' => class_exists('ZipArchive') ? 'Available' : 'Missing ZipArchive extension',
        ],
        [
            'label' => 'HTTPS-capable download support',
            'ok' => $downloadSupport,
            'detail' => $downloadSupport ? 'curl or allow_url_fopen available' : 'Enable curl or allow_url_fopen',
        ],
        [
            'label' => 'Target folder writable',
            'ok' => is_writable($root),
            'detail' => $root,
        ],
    ];
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
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Could not initialize download client.');
        }

        $stream = fopen($target, 'wb');
        if ($stream === false) {
            curl_close($handle);
            throw new RuntimeException('Could not open temporary file for download.');
        }

        curl_setopt_array($handle, [
            CURLOPT_FILE => $stream,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_USERAGENT => 'bandPromo bootstrap installer',
        ]);

        $ok = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        fclose($stream);

        if ($ok === false) {
            @unlink($target);
            throw new RuntimeException('Download failed: ' . ($error !== '' ? $error : 'Unknown cURL error'));
        }

        if ($status >= 400) {
            @unlink($target);
            throw new RuntimeException('Download failed with HTTP status ' . $status . '.');
        }

        return;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 180,
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
    $handle = curl_init($url);
    if ($handle === false) {
      throw new RuntimeException('Could not initialize manifest download client.');
    }

    curl_setopt_array($handle, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_FAILONERROR => true,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_USERAGENT => 'bandPromo bootstrap installer',
    ]);

    $body = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    if ($body === false) {
      throw new RuntimeException('Manifest fetch failed: ' . ($error !== '' ? $error : 'Unknown cURL error'));
    }

    if ($status >= 400) {
      throw new RuntimeException('Manifest fetch failed with HTTP status ' . $status . '.');
    }

    return (string) $body;
  }

  $context = stream_context_create([
    'http' => [
      'timeout' => 30,
      'follow_location' => 1,
      'user_agent' => 'bandPromo bootstrap installer',
    ],
    'ssl' => [
      'verify_peer' => true,
      'verify_peer_name' => true,
    ],
  ]);

  $body = @file_get_contents($url, false, $context);
  if ($body === false) {
    throw new RuntimeException('Manifest fetch failed. Check outbound HTTPS support and the manifest URL.');
  }

  return $body;
}

function bandpromo_bootstrap_load_manifest(string $manifestUrl): array {
  $body = bandpromo_bootstrap_fetch_text($manifestUrl);
  $decoded = json_decode($body, true);
  if (!is_array($decoded)) {
    throw new RuntimeException('Release manifest is not valid JSON.');
  }

  if (empty($decoded['package_url']) || !is_string($decoded['package_url'])) {
    throw new RuntimeException('Release manifest is missing package_url.');
  }

  if (empty($decoded['version']) || !is_string($decoded['version'])) {
    throw new RuntimeException('Release manifest is missing version.');
  }

  return $decoded;
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

function bandpromo_bootstrap_install_package(string $root, string $packageUrl, ?string $expectedSha256 = null): array {
    $workDir = $root . DIRECTORY_SEPARATOR . BANDPROMO_BOOTSTRAP_WORKDIR;
    $downloadPath = $workDir . DIRECTORY_SEPARATOR . 'package.zip';
    $extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract';

    bandpromo_bootstrap_rrmdir($workDir);
    bandpromo_bootstrap_ensure_dir($workDir);
    bandpromo_bootstrap_ensure_dir($extractDir);

    bandpromo_bootstrap_download_file($packageUrl, $downloadPath);

    if ($expectedSha256 !== null && $expectedSha256 !== '') {
      $actualSha256 = bandpromo_bootstrap_sha256_file($downloadPath);
      if ($actualSha256 !== strtolower($expectedSha256)) {
        throw new RuntimeException('Downloaded package checksum did not match the published release manifest.');
      }
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

$root = __DIR__;
$checks = bandpromo_bootstrap_collect_environment_checks($root);
$errors = [];
$successMessage = null;
$installedVersion = null;
$releaseManifest = null;
$releaseManifestError = null;

try {
  $releaseManifest = bandpromo_bootstrap_load_manifest(BANDPROMO_BOOTSTRAP_DEFAULT_MANIFEST_URL);
} catch (Throwable $throwable) {
  $releaseManifestError = $throwable->getMessage();
}

$packageUrl = $releaseManifest !== null ? trim((string) $releaseManifest['package_url']) : '';
$expectedSha256 = $releaseManifest !== null && isset($releaseManifest['sha256']) && is_string($releaseManifest['sha256'])
  ? $releaseManifest['sha256']
  : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install') {
    if (bandpromo_bootstrap_has_blocking_failures($checks)) {
        $errors[] = 'Fix the blocking environment checks before starting the installer.';
    }

    if ($releaseManifest === null) {
        $errors[] = 'No published immutable release package was discovered. Publish a release package first, then reload this installer.';
    }

    if ($packageUrl === '') {
        $errors[] = 'The published release manifest did not provide a usable package URL.';
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

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 20px;
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

    input[type="url"] {
      width: 100%;
      border-radius: 12px;
      border: 1px solid var(--border);
      background: #101521;
      color: var(--text);
      padding: 14px 16px;
      font-size: 15px;
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
      background: var(--accent);
      color: #1b120d;
    }

    button[disabled] {
      opacity: 0.55;
      cursor: not-allowed;
    }

    .button-link {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
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
      <h1>Install bandPromo from one browser page.</h1>
      <p>This bootstrap installer is the package-entry point that should replace manual repository uploads for normal operators. It performs environment checks, discovers the latest published release package, unpacks the application into this folder, preserves runtime state on re-entry, and then hands off to the normal setup wizard.</p>
    </section>

    <div class="grid">
      <section class="panel">
        <h2>Environment checks</h2>
        <div class="checks">
          <?php foreach ($checks as $check): ?>
            <div class="check">
              <strong class="<?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'OK' : 'Fix' ?></strong>
              <?= bandpromo_bootstrap_h($check['label']) ?><br>
              <span><?= bandpromo_bootstrap_h($check['detail']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="panel">
        <h2>What happens next</h2>
        <ul>
          <li>Download a package ZIP into a temporary installer work folder.</li>
          <li>Extract it and verify that it contains a recognizable bandPromo application root.</li>
          <li>Copy tracked application files into this folder without overwriting runtime state such as <code>web-config.json</code>, <code>.env</code>, <code>data/</code>, <code>log/</code>, or <code>media/</code>.</li>
          <li>Continue into <code>setup.php</code> to create the first admin and finish site setup.</li>
        </ul>
      </section>
    </div>

    <section class="panel">
      <h2>Release discovery</h2>
      <?php if ($releaseManifest !== null): ?>
        <p>Latest published package discovered automatically from the release manifest.</p>
        <ul>
          <li>Version: <code><?= bandpromo_bootstrap_h((string) ($releaseManifest['version'] ?? 'unknown')) ?></code></li>
          <?php if (!empty($releaseManifest['release_tag'])): ?>
            <li>Release tag: <code><?= bandpromo_bootstrap_h((string) $releaseManifest['release_tag']) ?></code></li>
          <?php endif; ?>
          <li>Package source: <code><?= bandpromo_bootstrap_h((string) $releaseManifest['package_url']) ?></code></li>
          <?php if (!empty($releaseManifest['sha256'])): ?>
            <li>Checksum verification: <code>SHA256</code> available and will be enforced during install.</li>
          <?php endif; ?>
        </ul>
      <?php else: ?>
        <p>No published release manifest was discovered automatically. The operator install flow now requires a published immutable release package before installation can continue.</p>
        <?php if ($releaseManifestError !== null): ?>
          <p>Manifest lookup detail: <?= bandpromo_bootstrap_h($releaseManifestError) ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="panel">
      <h2>Install package</h2>
      <?php if ($successMessage !== null): ?>
        <div class="notice">
          <strong><?= bandpromo_bootstrap_h($successMessage) ?></strong><br>
          Installed version: <?= bandpromo_bootstrap_h($installedVersion ?? 'unknown') ?>
        </div>
      <?php endif; ?>

      <?php if ($errors !== []): ?>
        <div class="error-list">
          <strong>The installer stopped safely.</strong>
          <ul>
            <?php foreach ($errors as $error): ?>
              <li><?= bandpromo_bootstrap_h($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <p>The operator installer now uses the latest published immutable release discovered from the manifest. Manual ZIP URLs and mutable branch snapshots are no longer part of the normal install path.</p>

      <form method="post">
        <input type="hidden" name="action" value="install">
        <div class="actions">
          <button type="submit"<?= bandpromo_bootstrap_has_blocking_failures($checks) || $releaseManifest === null || $packageUrl === '' ? ' disabled' : '' ?>>Download and install latest release</button>
          <?php if ($successMessage !== null || is_file($root . DIRECTORY_SEPARATOR . 'setup.php')): ?>
            <a class="button-link" href="setup.php">Open setup</a>
          <?php endif; ?>
          <?php if ($isSetupComplete): ?>
            <a class="button-link" href="admin.php">Open admin</a>
          <?php endif; ?>
        </div>
      </form>
    </section>
  </div>
</body>
</html>