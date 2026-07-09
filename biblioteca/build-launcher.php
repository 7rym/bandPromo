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

function bandpromo_resolve_php_cli(): string
{
    $envPhp = trim((string) getenv('BUILD_PHP'));
    if ($envPhp !== '' && is_file($envPhp)) {
        return $envPhp;
    }

    if (PHP_OS_FAMILY !== 'Windows' && bandpromo_build_function_usable('shell_exec')) {
        foreach (['php', 'php-cli', 'php83', 'php82', 'php81', 'php8.3', 'php8.2', 'php8.1'] as $candidate) {
            $resolved = trim((string) shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null'));
            if ($resolved !== '' && is_file($resolved)) {
                return $resolved;
            }
        }
    }

    if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
        if (stripos(PHP_BINARY, 'fpm') === false) {
            return PHP_BINARY;
        }
    }

    return 'php';
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

    $inner = escapeshellarg($php)
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
        'launch_command' => 'nohup php build-runner.php (detached)',
        'launch_exit_code' => $started ? 0 : 1,
        'launch_output_tail' => $started ? ('runner_pid:' . $pid) : bandpromo_build_launch_failure_tail($php, true) . '; nohup did not return pid',
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
    bool $isWindows
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

    $php = bandpromo_resolve_php_cli();

    if (bandpromo_build_function_usable('proc_open')) {
        $launch = bandpromo_build_launch_background_proc_open($php, $runner, $logFile, $lockFile, $python, $script);
        if ($launch['started'] ?? false) {
            return $launch;
        }
    }

    if (!$isWindows) {
        return bandpromo_build_launch_background_unix_shell($php, $runner, $logFile, $lockFile, $python, $script);
    }

    return [
        'started' => false,
        'launch_command' => 'php build-runner.php (detached)',
        'launch_exit_code' => 1,
        'launch_output_tail' => bandpromo_build_launch_failure_tail($php, bandpromo_build_function_usable('proc_open')),
    ];
}
