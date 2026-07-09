<?php
declare(strict_types=1);

/**
 * Cross-platform background launcher for publish/optimize scripts.
 *
 * Preferred path: proc_open (php → build-runner.php → python).
 * Unix fallback: nohup shell background launch for hosts where proc_open is blocked
 * or PHP_BINARY points at php-fpm instead of the CLI binary.
 */

function bandpromo_build_function_usable(string $function): bool
{
    if (!function_exists($function)) {
        return false;
    }

    $disabled = ini_get('disable_functions');
    if (!is_string($disabled) || trim($disabled) === '') {
        return true;
    }

    $disabledList = array_map('trim', explode(',', strtolower($disabled)));

    return !in_array(strtolower($function), $disabledList, true);
}

function bandpromo_build_is_executable_file(string $path): bool
{
    return $path !== '' && is_file($path) && is_executable($path);
}

function bandpromo_build_derive_php_cli_from_fpm_path(string $binary): ?string
{
    $normalized = str_replace('\\', '/', $binary);
    if (preg_match('#/opt/plesk/php/([^/]+)/sbin/php-fpm(?:\d+)?$#i', $normalized, $matches) === 1) {
        return '/opt/plesk/php/' . $matches[1] . '/bin/php';
    }

    if (stripos($normalized, 'php-fpm') !== false) {
        $candidate = preg_replace('#/sbin/php-fpm(?:\d+)?$#i', '/bin/php', $normalized);

        return is_string($candidate) && $candidate !== '' ? $candidate : null;
    }

    return null;
}

function bandpromo_build_php_cli_smoke_test(string $candidate): bool
{
    if ($candidate === '' || $candidate === 'php' || $candidate === 'php.exe') {
        if (!bandpromo_build_function_usable('exec')) {
            return false;
        }

        $output = [];
        $exitCode = 1;
        exec(escapeshellarg($candidate) . ' -r ' . escapeshellarg('echo "php-cli-smoke";') . ' 2>&1', $output, $exitCode);

        return $exitCode === 0 && strpos(implode("\n", $output), 'php-cli-smoke') !== false;
    }

    if (bandpromo_build_is_executable_file($candidate)) {
        return true;
    }

    if (!bandpromo_build_function_usable('exec')) {
        return false;
    }

    $output = [];
    $exitCode = 1;
    exec(escapeshellarg($candidate) . ' -r ' . escapeshellarg('echo "php-cli-smoke";') . ' 2>&1', $output, $exitCode);

    return $exitCode === 0 && strpos(implode("\n", $output), 'php-cli-smoke') !== false;
}

function bandpromo_build_php_cli_usable(string $candidate): bool
{
    return bandpromo_build_php_cli_smoke_test($candidate);
}

function bandpromo_build_derive_php_cli_from_fpm(string $binary): ?string
{
    $candidate = bandpromo_build_derive_php_cli_from_fpm_path($binary);
    if ($candidate === null) {
        return null;
    }

    return bandpromo_build_php_cli_usable($candidate) ? $candidate : null;
}

function bandpromo_build_php_cli_candidates(): array
{
    $candidates = [];

    $envPhp = trim((string) getenv('BUILD_PHP'));
    if ($envPhp !== '') {
        $candidates[] = $envPhp;
    }

    if (defined('PHP_MAJOR_VERSION') && defined('PHP_MINOR_VERSION')) {
        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $candidates[] = '/opt/plesk/php/' . $version . '/bin/php';
        $candidates[] = '/opt/plesk/php/' . PHP_MAJOR_VERSION . '/bin/php';
        $candidates[] = '/usr/bin/php' . $version;
        $candidates[] = '/usr/bin/php' . PHP_MAJOR_VERSION;
    }

    if (defined('PHP_BINDIR') && is_string(PHP_BINDIR) && PHP_BINDIR !== '') {
        $candidates[] = rtrim(PHP_BINDIR, '/\\') . '/php';
    }

    if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
        $derivedPath = bandpromo_build_derive_php_cli_from_fpm_path(PHP_BINARY);
        if ($derivedPath !== null) {
            $candidates[] = $derivedPath;
        }
        if (stripos(PHP_BINARY, 'fpm') === false) {
            $candidates[] = PHP_BINARY;
        }
    }

    $candidates[] = '/usr/bin/php';
    $candidates[] = '/usr/local/bin/php';

    if (PHP_OS_FAMILY !== 'Windows' && bandpromo_build_function_usable('shell_exec')) {
        foreach (['php', 'php-cli', 'php82', 'php83', 'php81', 'php8.2', 'php8.3', 'php8.1'] as $name) {
            $resolved = trim((string) shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
            if ($resolved !== '') {
                $candidates[] = $resolved;
            }
        }
    }

    $candidates[] = 'php';

    $unique = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '' || in_array($candidate, $unique, true)) {
            continue;
        }
        $unique[] = $candidate;
    }

    return $unique;
}

function bandpromo_resolve_php_cli(): string
{
    $fallback = 'php';

    foreach (bandpromo_build_php_cli_candidates() as $candidate) {
        if ($candidate === 'php' || $candidate === 'php.exe') {
            $fallback = $candidate;
            continue;
        }
        if (bandpromo_build_php_cli_usable($candidate)) {
            return $candidate;
        }
    }

    return bandpromo_build_php_cli_usable($fallback) ? $fallback : $fallback;
}

function bandpromo_build_python_subprocess_env(): array
{
    $env = $_ENV;
    foreach ($_SERVER as $key => $value) {
        if (!is_string($key) || !is_string($value)) {
            continue;
        }
        if (!array_key_exists($key, $env)) {
            $env[$key] = $value;
        }
    }

    $php = bandpromo_resolve_php_cli();
    if ($php !== '' && $php !== 'php' && $php !== 'php.exe') {
        $env['BANDPROMO_PHP_CLI'] = $php;
    }
    if (defined('PHP_BINDIR') && is_string(PHP_BINDIR) && PHP_BINDIR !== '') {
        $env['BANDPROMO_PHP_BINDIR'] = PHP_BINDIR;
    }
    if (defined('PHP_MAJOR_VERSION') && defined('PHP_MINOR_VERSION')) {
        $env['BANDPROMO_PHP_VERSION'] = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }

    return $env;
}

function bandpromo_build_shell_env_prefix(array $env): string
{
    $parts = [];
    foreach (['BANDPROMO_PHP_CLI', 'BANDPROMO_PHP_BINDIR', 'BANDPROMO_PHP_VERSION'] as $key) {
        if (!empty($env[$key])) {
            $parts[] = $key . '=' . escapeshellarg((string) $env[$key]);
        }
    }

    return $parts === [] ? '' : implode(' ', $parts) . ' ';
}

function bandpromo_build_null_device(): string
{
    return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
}

function bandpromo_build_launch_failure_tail(string $php, bool $procOpenAttempted): string
{
    $parts = [];
    if ($procOpenAttempted) {
        $parts[] = 'proc_open failed';
    }
    $parts[] = 'php=' . $php;
    $parts[] = 'proc_open=' . (bandpromo_build_function_usable('proc_open') ? 'yes' : 'no');
    $parts[] = 'shell_exec=' . (bandpromo_build_function_usable('shell_exec') ? 'yes' : 'no');

    return implode('; ', $parts);
}

function bandpromo_build_launch_background_unix_shell(
    string $php,
    string $runner,
    string $logFile,
    string $lockFile,
    string $python,
    string $script
): array {
    if (!bandpromo_build_function_usable('shell_exec')) {
        return [
            'started' => false,
            'launch_command' => 'nohup php build-runner.php',
            'launch_exit_code' => 1,
            'launch_output_tail' => bandpromo_build_launch_failure_tail($php, true) . '; nohup unavailable',
        ];
    }

    $root = dirname(__DIR__);
    $inner = 'cd ' . escapeshellarg($root)
        . ' && ' . escapeshellarg($php)
        . ' -f ' . escapeshellarg($runner)
        . ' ' . escapeshellarg($logFile)
        . ' ' . escapeshellarg($lockFile)
        . ' ' . escapeshellarg($python)
        . ' ' . escapeshellarg($script);

    $bgCmd = 'nohup sh -c ' . escapeshellarg($inner) . ' > /dev/null 2>&1 & echo $!';
    $pid = trim((string) shell_exec($bgCmd));
    $started = $pid !== '' && ctype_digit($pid);

    return [
        'started' => $started,
        'launch_command' => 'nohup ' . $php . ' build-runner.php (detached)',
        'launch_exit_code' => $started ? 0 : 1,
        'launch_output_tail' => $started ? ('runner_pid:' . $pid) : bandpromo_build_launch_failure_tail($php, true) . '; nohup did not return pid',
        'pid' => $started ? (int) $pid : null,
    ];
}

function bandpromo_build_launch_background_unix_python(
    string $python,
    string $script,
    string $logFile,
    string $lockFile
): array {
    if (!bandpromo_build_function_usable('shell_exec')) {
        return [
            'started' => false,
            'launch_command' => 'nohup python build.py',
            'launch_exit_code' => 1,
            'launch_output_tail' => 'shell_exec disabled; cannot use legacy nohup→python launcher',
        ];
    }

    $root = dirname(__DIR__);
    $envPrefix = bandpromo_build_shell_env_prefix(bandpromo_build_python_subprocess_env());
    $inner = 'cd ' . escapeshellarg($root)
        . ' && ' . $envPrefix
        . escapeshellarg($python)
        . ' -u ' . escapeshellarg($script)
        . ' >> ' . escapeshellarg($logFile)
        . ' 2>> ' . escapeshellarg($logFile)
        . '; code=$?; echo EXITCODE:$code >> ' . escapeshellarg($logFile)
        . '; rm -f ' . escapeshellarg($lockFile)
        . '; exit $code';

    $bgCmd = 'nohup sh -c ' . escapeshellarg($inner) . ' > /dev/null 2>&1 & echo $!';
    $pid = trim((string) shell_exec($bgCmd));
    $started = $pid !== '' && ctype_digit($pid);

    return [
        'started' => $started,
        'launch_command' => 'nohup ' . $python . ' build.py (detached)',
        'launch_exit_code' => $started ? 0 : 1,
        'launch_output_tail' => $started ? ('python_pid:' . $pid) : 'nohup python launcher did not return pid',
        'pid' => $started ? (int) $pid : null,
    ];
}

function bandpromo_build_launch_background_proc_open(
    string $php,
    string $runner,
    string $logFile,
    string $lockFile,
    string $python,
    string $script
): array {
    $null = bandpromo_build_null_device();
    $command = [$php, '-f', $runner, $logFile, $lockFile, $python, $script];

    $options = ['bypass_shell' => true];
    if (PHP_OS_FAMILY === 'Windows') {
        $options['create_new_console'] = false;
    }

    $pipes = [];
    $process = @proc_open(
        $command,
        [
            0 => ['file', $null, 'r'],
            1 => ['file', $null, 'w'],
            2 => ['file', $null, 'w'],
        ],
        $pipes,
        dirname(__DIR__),
        null,
        $options
    );

    if (!is_resource($process)) {
        return [
            'started' => false,
            'launch_command' => 'php build-runner.php (detached)',
            'launch_exit_code' => 1,
            'launch_output_tail' => bandpromo_build_launch_failure_tail($php, true),
        ];
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    $status = proc_get_status($process);
    $pid = is_array($status) ? (int) ($status['pid'] ?? 0) : 0;

    return [
        'started' => $pid > 0,
        'launch_command' => 'php build-runner.php (detached)',
        'launch_exit_code' => $pid > 0 ? 0 : 1,
        'launch_output_tail' => $pid > 0 ? ('runner_pid:' . $pid) : bandpromo_build_launch_failure_tail($php, true) . '; runner did not start',
        'pid' => $pid > 0 ? $pid : null,
    ];
}

function bandpromo_build_launch_background(
    string $python,
    string $script,
    string $logFile,
    string $lockFile,
    string $runId,
    bool $isWindows,
    ?array $diagnostics = null
): array {
    $runner = __DIR__ . '/build-runner.php';
    if (!is_file($runner)) {
        return [
            'started' => false,
            'launch_command' => null,
            'launch_exit_code' => 1,
            'launch_output_tail' => 'build-runner.php is missing',
        ];
    }

    $php = is_array($diagnostics) ? (string) ($diagnostics['resolved_php'] ?? bandpromo_resolve_php_cli()) : bandpromo_resolve_php_cli();
    $method = is_array($diagnostics) ? (string) ($diagnostics['recommended_method'] ?? '') : '';

    if ($method === 'nohup_python' && !$isWindows) {
        return bandpromo_build_launch_background_unix_python($python, $script, $logFile, $lockFile);
    }

    if ($method === 'proc_open_runner' && bandpromo_build_function_usable('proc_open')) {
        $launch = bandpromo_build_launch_background_proc_open($php, $runner, $logFile, $lockFile, $python, $script);
        if ($launch['started'] ?? false) {
            return $launch;
        }
    }

    if ($method === 'nohup_runner' && !$isWindows) {
        return bandpromo_build_launch_background_unix_shell($php, $runner, $logFile, $lockFile, $python, $script);
    }

    if ($method === 'failed') {
        return [
            'started' => false,
            'launch_command' => null,
            'launch_exit_code' => 1,
            'launch_output_tail' => is_array($diagnostics)
                ? (string) ($diagnostics['recommended_reason'] ?? 'Launch diagnostics found no supported path')
                : 'Launch diagnostics missing',
        ];
    }

    if (bandpromo_build_function_usable('proc_open')) {
        $launch = bandpromo_build_launch_background_proc_open($php, $runner, $logFile, $lockFile, $python, $script);
        if ($launch['started'] ?? false) {
            return $launch;
        }
    }

    if (!$isWindows) {
        if (!bandpromo_build_php_cli_usable($php) && bandpromo_build_function_usable('shell_exec')) {
            return bandpromo_build_launch_background_unix_python($python, $script, $logFile, $lockFile);
        }

        return bandpromo_build_launch_background_unix_shell($php, $runner, $logFile, $lockFile, $python, $script);
    }

    return [
        'started' => false,
        'launch_command' => 'php build-runner.php (detached)',
        'launch_exit_code' => 1,
        'launch_output_tail' => bandpromo_build_launch_failure_tail($php, bandpromo_build_function_usable('proc_open')),
    ];
}
