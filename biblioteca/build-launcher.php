<?php
declare(strict_types=1);

/**
 * Cross-platform background launcher for publish/optimize scripts.
 *
 * Spawns biblioteca/build-runner.php via proc_open (php.exe → python.exe).
 * No cmd.exe, PowerShell, or .bat files.
 */

function bandpromo_resolve_php_cli(): string
{
    if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
        return PHP_BINARY;
    }

    return 'php';
}

function bandpromo_build_null_device(): string
{
    return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
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
            'launch_output_tail' => 'proc_open failed',
        ];
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    $status = proc_get_status($process);
    $pid = is_array($status) ? (int) ($status['pid'] ?? 0) : 0;

    // Do not proc_close here — the runner continues after the HTTP response returns.
    return [
        'started' => $pid > 0,
        'launch_command' => 'php build-runner.php (detached)',
        'launch_exit_code' => $pid > 0 ? 0 : 1,
        'launch_output_tail' => $pid > 0 ? ('runner_pid:' . $pid) : 'runner did not start',
        'pid' => $pid > 0 ? $pid : null,
    ];
}
