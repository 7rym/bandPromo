<?php
declare(strict_types=1);

/**
 * Structured host environment snapshot for System → Environment (developer).
 * No secrets, no phpinfo dump.
 */

require_once __DIR__ . '/build-launcher.php';
require_once __DIR__ . '/environment-checks.php';
require_once __DIR__ . '/package-updater.php';

function bandpromo_environment_read_version(string $root): string
{
    $path = rtrim($root, '/\\') . '/VERSION';
    if (!is_file($path)) {
        return '';
    }
    $raw = trim((string) @file_get_contents($path));
    return $raw;
}

function bandpromo_environment_ffmpeg_path(string $root): string
{
    $bundled = rtrim($root, '/\\') . '/scripts/bin/ffmpeg';
    if (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
        $bundled .= '.exe';
    }
    if (is_file($bundled)) {
        return $bundled;
    }

    if (bandpromo_build_function_usable('shell_exec')) {
        $which = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($which !== '') {
            return $which;
        }
    }

    return '';
}

function bandpromo_environment_python_hint(): string
{
    if (!bandpromo_build_function_usable('shell_exec')) {
        return '';
    }
    $path = trim((string) shell_exec('command -v python3 2>/dev/null'));
    if ($path === '') {
        $path = trim((string) shell_exec('command -v python 2>/dev/null'));
    }
    return $path;
}

/**
 * @return array<string, mixed>
 */
function bandpromo_environment_collect_report(string $root): array
{
    $phpCli = bandpromo_resolve_php_cli();
    $packageChecks = bandpromo_package_collect_environment_checks($root);
    $diagCachePath = $root . '/log/build-launch-diag.json';
    $diagCache = null;
    if (is_file($diagCachePath)) {
        $decoded = json_decode((string) @file_get_contents($diagCachePath), true);
        if (is_array($decoded)) {
            $diagCache = [
                'resolved_php' => (string) ($decoded['resolved_php'] ?? ''),
                'recommended_method' => (string) ($decoded['recommended_method'] ?? ''),
                'recommended_reason' => (string) ($decoded['recommended_reason'] ?? ''),
                'cached_at' => (string) ($decoded['cached_at'] ?? ''),
            ];
        }
    }

    $disabled = trim((string) ini_get('disable_functions'));
    $openBasedir = trim((string) ini_get('open_basedir'));

    return [
        'app' => [
            'version' => bandpromo_environment_read_version($root),
            'document_root' => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
            'install_root' => $root,
            'host' => (string) ($_SERVER['HTTP_HOST'] ?? (function_exists('gethostname') ? (string) gethostname() : '')),
            'server_software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''),
        ],
        'php' => [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
            'binary' => defined('PHP_BINARY') ? (string) PHP_BINARY : '',
            'bindir' => defined('PHP_BINDIR') ? (string) PHP_BINDIR : '',
            'cli' => $phpCli,
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => (string) ini_get('max_execution_time'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'open_basedir' => $openBasedir !== '' ? $openBasedir : '(none)',
            'disable_functions' => $disabled !== '' ? $disabled : '(none)',
            'extensions' => [
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                'zip' => class_exists('ZipArchive'),
                'curl' => extension_loaded('curl'),
                'openssl' => extension_loaded('openssl'),
            ],
        ],
        'functions' => [
            'proc_open' => bandpromo_build_function_usable('proc_open'),
            'shell_exec' => bandpromo_build_function_usable('shell_exec'),
            'exec' => bandpromo_build_function_usable('exec'),
            'popen' => bandpromo_build_function_usable('popen'),
            'putenv' => bandpromo_build_function_usable('putenv'),
        ],
        'build_tools' => [
            'python' => bandpromo_environment_python_hint(),
            'ffmpeg' => bandpromo_environment_ffmpeg_path($root),
            'launch_diag_cache' => $diagCache,
        ],
        'package_checks' => $packageChecks,
        'generated_at' => gmdate('c'),
    ];
}
