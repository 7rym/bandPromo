<?php
declare(strict_types=1);

/**
 * CLI worker: runs the publish/optimize Python script and finalizes the build log.
 *
 * Started detached from biblioteca/build.php via proc_open (no shell).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "build-runner.php is CLI-only.\n");
    exit(1);
}

require_once __DIR__ . '/build-launcher.php';

$logFile = isset($argv[1]) ? (string) $argv[1] : '';
$lockFile = isset($argv[2]) ? (string) $argv[2] : '';
$python = isset($argv[3]) ? (string) $argv[3] : '';
$script = isset($argv[4]) ? (string) $argv[4] : '';

function bandpromo_runner_runtime_exists(string $command): bool
{
    if ($command === '') {
        return false;
    }

    if (is_file($command)) {
        return true;
    }

    if (PHP_OS_FAMILY !== 'Windows' && bandpromo_build_function_usable('shell_exec')) {
        return trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null')) !== '';
    }

    return false;
}

if ($logFile !== '' && is_dir(dirname($logFile))) {
    @file_put_contents($logFile, "[runner] build-runner.php started\n", FILE_APPEND);
}

function bandpromo_build_runner_fail(string $logFile, string $lockFile, string $message): void
{
    if ($logFile !== '' && is_dir(dirname($logFile))) {
        file_put_contents($logFile, $message . "\n", FILE_APPEND);
        file_put_contents($logFile, "EXITCODE:1\n", FILE_APPEND);
    }
    if ($lockFile !== '') {
        @unlink($lockFile);
    }
}

if ($logFile === '' || $lockFile === '' || $python === '' || $script === '' || !bandpromo_runner_runtime_exists($python) || !is_file($script)) {
    bandpromo_build_runner_fail($logFile, $lockFile, 'FAILED Invalid build runner arguments.');
    exit(1);
}

$null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$options = ['bypass_shell' => true];
if (PHP_OS_FAMILY === 'Windows') {
    $options['create_new_console'] = false;
}

$pipes = [];
$process = @proc_open(
    [$python, '-u', $script],
    [
        0 => ['file', $null, 'r'],
        1 => ['file', $logFile, 'a'],
        2 => ['file', $logFile, 'a'],
    ],
    $pipes,
    dirname(__DIR__),
    bandpromo_build_python_subprocess_env(),
    $options
);

if (!is_resource($process)) {
    if (PHP_OS_FAMILY !== 'Windows' && bandpromo_build_function_usable('exec')) {
        $shellCmd = escapeshellarg($python)
            . ' -u '
            . escapeshellarg($script)
            . ' >> '
            . escapeshellarg($logFile)
            . ' 2>> '
            . escapeshellarg($logFile);
        $output = [];
        $exitCode = 1;
        exec($shellCmd, $output, $exitCode);
        file_put_contents($logFile, 'EXITCODE:' . $exitCode . "\n", FILE_APPEND);
        @unlink($lockFile);
        exit($exitCode === 0 ? 0 : 1);
    }

    bandpromo_build_runner_fail($logFile, $lockFile, 'FAILED Could not start build runtime.');
    exit(1);
}

foreach ($pipes as $pipe) {
    if (is_resource($pipe)) {
        fclose($pipe);
    }
}

$exitCode = proc_close($process);
file_put_contents($logFile, 'EXITCODE:' . $exitCode . "\n", FILE_APPEND);
@unlink($lockFile);

exit($exitCode === 0 ? 0 : 1);
