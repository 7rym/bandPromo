<?php
declare(strict_types=1);

require_once __DIR__ . '/build-launcher.php';

function bandpromo_build_diag_log(string $logFile, string $message): void
{
    if ($logFile === '') {
        return;
    }

    file_put_contents($logFile, '[diag] ' . rtrim($message) . "\n", FILE_APPEND);
}

function bandpromo_build_diag_exec_output(string $command): array
{
    if (!bandpromo_build_function_usable('exec')) {
        return [
            'ok' => false,
            'output' => '',
            'exit_code' => null,
            'error' => 'exec disabled',
        ];
    }

    $output = [];
    $exitCode = 1;
    exec($command . ' 2>&1', $output, $exitCode);

    return [
        'ok' => $exitCode === 0,
        'output' => trim(implode("\n", $output)),
        'exit_code' => $exitCode,
        'error' => $exitCode === 0 ? '' : 'exit ' . $exitCode,
    ];
}

function bandpromo_build_diag_shell_lookup(string $command): array
{
    if (!bandpromo_build_function_usable('shell_exec')) {
        return [
            'ok' => false,
            'output' => '',
            'error' => 'shell_exec disabled',
        ];
    }

    $resolved = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));
    if ($resolved === '') {
        return [
            'ok' => false,
            'output' => '',
            'error' => 'not found',
        ];
    }

    return [
        'ok' => true,
        'output' => $resolved,
        'error' => '',
    ];
}

function bandpromo_build_diag_proc_open_smoke(string $binary, array $args): array
{
    if (!bandpromo_build_function_usable('proc_open')) {
        return [
            'ok' => false,
            'output' => '',
            'error' => 'proc_open disabled',
        ];
    }

    $command = array_merge([$binary], $args);
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        return [
            'ok' => false,
            'output' => '',
            'error' => 'proc_open failed',
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $output = trim((string) $stdout . "\n" . (string) $stderr);

    return [
        'ok' => $exitCode === 0,
        'output' => trim($output),
        'exit_code' => $exitCode,
        'error' => $exitCode === 0 ? '' : 'exit ' . $exitCode,
    ];
}

function bandpromo_build_diag_format_candidate_status(string $candidate): string
{
    if ($candidate === 'php') {
        return 'php (PATH lookup fallback; not a file path)';
    }

    if (!is_file($candidate)) {
        return $candidate . ' missing';
    }

    if (!is_executable($candidate)) {
        return $candidate . ' exists but not executable';
    }

    return $candidate . ' executable';
}

function bandpromo_build_run_launch_diagnostics(
    string $root,
    string $logFile,
    string $python,
    string $script,
    bool $isWindows
): array {
    $runnerScript = __DIR__ . '/build-runner.php';
    $logDir = $root . '/log';
    $resolvedPhp = bandpromo_resolve_php_cli();
    $phpCandidates = bandpromo_build_php_cli_candidates();
    $phpSmokeBinary = $resolvedPhp;

    bandpromo_build_diag_log($logFile, 'Launch diagnostics started.');
    bandpromo_build_diag_log(
        $logFile,
        'Context: publish builds since 2026-07-01 use php→build-runner→python; older installs used nohup→python directly.'
    );
    bandpromo_build_diag_log($logFile, 'PHP SAPI=' . PHP_SAPI . ' version=' . PHP_VERSION . ' os=' . PHP_OS_FAMILY);

    if (defined('PHP_BINARY')) {
        bandpromo_build_diag_log($logFile, 'PHP_BINARY=' . (string) PHP_BINARY);
    }
    if (defined('PHP_BINDIR')) {
        bandpromo_build_diag_log($logFile, 'PHP_BINDIR=' . (string) PHP_BINDIR);
    }

    $disabled = trim((string) ini_get('disable_functions'));
    bandpromo_build_diag_log($logFile, 'disable_functions=' . ($disabled !== '' ? $disabled : '(none)'));

    $openBasedir = trim((string) ini_get('open_basedir'));
    if ($openBasedir !== '') {
        bandpromo_build_diag_log($logFile, 'open_basedir=' . $openBasedir);
    }

    foreach (['proc_open', 'shell_exec', 'exec', 'popen', 'putenv'] as $function) {
        bandpromo_build_diag_log(
            $logFile,
            'function ' . $function . '=' . (bandpromo_build_function_usable($function) ? 'yes' : 'no')
        );
    }

    bandpromo_build_diag_log($logFile, 'Resolved PHP CLI=' . $resolvedPhp);
    foreach ($phpCandidates as $candidate) {
        bandpromo_build_diag_log($logFile, 'PHP candidate: ' . bandpromo_build_diag_format_candidate_status($candidate));
    }

    bandpromo_build_diag_log($logFile, 'Python path=' . $python);
    bandpromo_build_diag_log($logFile, 'Build script=' . $script . ' ' . (is_file($script) ? 'exists' : 'missing'));
    bandpromo_build_diag_log($logFile, 'Runner script=' . $runnerScript . ' ' . (is_file($runnerScript) ? 'exists' : 'missing'));
    bandpromo_build_diag_log($logFile, 'Log dir writable=' . (is_dir($logDir) && is_writable($logDir) ? 'yes' : 'no'));

    $checks = [];

    if (!$isWindows) {
        $phpLookup = bandpromo_build_diag_shell_lookup('php');
        $checks['shell_php'] = $phpLookup;
        bandpromo_build_diag_log(
            $logFile,
            'shell lookup php: ' . ($phpLookup['ok'] ? $phpLookup['output'] : $phpLookup['error'])
        );
        if ($phpSmokeBinary === 'php' && $phpLookup['ok']) {
            $phpSmokeBinary = $phpLookup['output'];
            bandpromo_build_diag_log($logFile, 'PHP smoke binary from shell lookup=' . $phpSmokeBinary);
        }

        $pythonLookup = bandpromo_build_diag_shell_lookup('python3');
        $checks['shell_python3'] = $pythonLookup;
        bandpromo_build_diag_log(
            $logFile,
            'shell lookup python3: ' . ($pythonLookup['ok'] ? $pythonLookup['output'] : $pythonLookup['error'])
        );
    }

    $phpSmokeIsExecutable = $phpSmokeBinary !== 'php' && bandpromo_build_is_executable_file($phpSmokeBinary);
    if ($phpSmokeIsExecutable) {
        $phpVersion = bandpromo_build_diag_exec_output(escapeshellarg($phpSmokeBinary) . ' -v');
        $checks['php_exec_version'] = $phpVersion;
        bandpromo_build_diag_log(
            $logFile,
            'exec php -v: ' . ($phpVersion['ok'] ? str_replace("\n", ' | ', $phpVersion['output']) : $phpVersion['error'])
        );

        $phpSmoke = bandpromo_build_diag_proc_open_smoke($phpSmokeBinary, ['-r', 'echo "runner-smoke-ok";']);
        $checks['php_proc_open_smoke'] = $phpSmoke;
        bandpromo_build_diag_log(
            $logFile,
            'proc_open php smoke: ' . ($phpSmoke['ok'] ? $phpSmoke['output'] : $phpSmoke['error'])
        );
    } elseif ($phpSmokeBinary === 'php') {
        bandpromo_build_diag_log($logFile, 'Skipped php exec/proc_open smoke tests because no executable PHP CLI path was resolved.');
    } else {
        bandpromo_build_diag_log($logFile, 'Skipped php exec/proc_open smoke tests because ' . $phpSmokeBinary . ' is not executable.');
    }

    if (!$isWindows && bandpromo_build_function_usable('shell_exec')) {
        $nohupPhp = bandpromo_build_diag_nohup_smoke($phpSmokeBinary, $logDir);
        $checks['nohup_php_smoke'] = $nohupPhp;
        bandpromo_build_diag_log(
            $logFile,
            'nohup php smoke: ' . ($nohupPhp['ok'] ? $nohupPhp['output'] : $nohupPhp['error'])
        );

        $nohupPython = bandpromo_build_diag_nohup_smoke($python, $logDir, true);
        $checks['nohup_python_smoke'] = $nohupPython;
        bandpromo_build_diag_log(
            $logFile,
            'nohup python smoke: ' . ($nohupPython['ok'] ? $nohupPython['output'] : $nohupPython['error'])
        );
    }

    if ($python !== '') {
        $pythonCommand = is_file($python) ? escapeshellarg($python) : escapeshellarg($python);
        $pythonVersion = bandpromo_build_diag_exec_output($pythonCommand . ' --version');
        $checks['python_exec_version'] = $pythonVersion;
        bandpromo_build_diag_log(
            $logFile,
            'exec python --version: ' . ($pythonVersion['ok'] ? $pythonVersion['output'] : $pythonVersion['error'])
        );

        $pythonImports = bandpromo_build_diag_exec_output(
            $pythonCommand . ' -c ' . escapeshellarg('import PIL, mutagen; print("imports-ok")')
        );
        $checks['python_imports'] = $pythonImports;
        bandpromo_build_diag_log(
            $logFile,
            'exec python imports: ' . ($pythonImports['ok'] ? $pythonImports['output'] : $pythonImports['error'])
        );
    }

    $ffmpegLookup = bandpromo_build_diag_shell_lookup('ffmpeg');
    $checks['ffmpeg'] = $ffmpegLookup;
    bandpromo_build_diag_log(
        $logFile,
        'ffmpeg: ' . ($ffmpegLookup['ok'] ? $ffmpegLookup['output'] : $ffmpegLookup['error'])
    );

    $recommended = 'failed';
    $reason = 'No supported background launch path was detected.';

    if (
        bandpromo_build_function_usable('proc_open')
        && ($checks['php_proc_open_smoke']['ok'] ?? false)
    ) {
        $recommended = 'proc_open_runner';
        $reason = 'PHP CLI proc_open smoke test passed.';
    } elseif (
        !$isWindows
        && ($checks['python_exec_version']['ok'] ?? false)
        && bandpromo_build_function_usable('shell_exec')
        && ($checks['nohup_python_smoke']['ok'] ?? false)
        && !($checks['nohup_php_smoke']['ok'] ?? false)
    ) {
        $recommended = 'nohup_python';
        $reason = 'nohup python smoke passed but nohup php smoke failed; use legacy nohup→python path.';
    } elseif (
        !$isWindows
        && ($checks['nohup_php_smoke']['ok'] ?? false)
        && bandpromo_build_function_usable('shell_exec')
    ) {
        $recommended = 'nohup_runner';
        $reason = 'nohup php smoke test passed; use php→build-runner.';
    } elseif (
        !$isWindows
        && ($checks['python_exec_version']['ok'] ?? false)
        && bandpromo_build_function_usable('shell_exec')
        && ($checks['nohup_python_smoke']['ok'] ?? false)
        && !($checks['php_exec_version']['ok'] ?? false)
        && !($checks['php_proc_open_smoke']['ok'] ?? false)
    ) {
        $recommended = 'nohup_python';
        $reason = 'Python works in the web context but PHP CLI smoke tests failed; nohup python smoke passed.';
    } elseif (
        !$isWindows
        && ($checks['php_exec_version']['ok'] ?? false)
        && bandpromo_build_function_usable('shell_exec')
    ) {
        $recommended = 'nohup_runner';
        $reason = 'PHP CLI exec works; use nohup php→build-runner.';
    }

    bandpromo_build_diag_log($logFile, 'Recommended launch method=' . $recommended);
    bandpromo_build_diag_log($logFile, 'Recommendation reason=' . $reason);

    if (!($checks['python_imports']['ok'] ?? true)) {
        bandpromo_build_diag_log(
            $logFile,
            'Warning: Python imports failed. Even with a working launcher, the build may fail until Pillow/mutagen are installed on the host.'
        );
    }

    if (!($checks['ffmpeg']['ok'] ?? true)) {
        bandpromo_build_diag_log(
            $logFile,
            'Warning: ffmpeg was not found on PATH. Video delivery stages may fail on this host.'
        );
    }

    bandpromo_build_diag_log($logFile, 'Launch diagnostics finished.');

    return [
        'resolved_php' => $resolvedPhp,
        'php_smoke_binary' => $phpSmokeBinary,
        'recommended_method' => $recommended,
        'recommended_reason' => $reason,
        'checks' => $checks,
    ];
}

function bandpromo_build_diag_nohup_smoke(string $binary, string $logDir, bool $isPython = false): array
{
    if (!bandpromo_build_function_usable('shell_exec')) {
        return [
            'ok' => false,
            'output' => '',
            'error' => 'shell_exec disabled',
        ];
    }

    if ($binary === '') {
        return [
            'ok' => false,
            'output' => '',
            'error' => 'binary path empty',
        ];
    }

    $marker = $logDir . '/launch-smoke-' . uniqid('', true) . '.txt';
    @unlink($marker);

    if ($isPython) {
        $inner = escapeshellarg($binary)
            . ' -c ' . escapeshellarg('open(' . var_export($marker, true) . ', "w").write("nohup-python-ok")');
    } else {
        $phpBinary = $binary === 'php' ? 'php' : escapeshellarg($binary);
        $inner = $phpBinary
            . ' -r ' . escapeshellarg('file_put_contents(' . var_export($marker, true) . ', "nohup-php-ok");');
    }

    $bgCmd = 'nohup sh -c ' . escapeshellarg($inner) . ' > /dev/null 2>&1 & echo $!';
    $pid = trim((string) shell_exec($bgCmd));
    if ($pid === '' || !ctype_digit($pid)) {
        return [
            'ok' => false,
            'output' => '',
            'error' => 'nohup did not return pid',
        ];
    }

    usleep(1500000);

    if (!is_file($marker)) {
        @unlink($marker);

        return [
            'ok' => false,
            'output' => 'pid:' . $pid,
            'error' => 'marker file not created (detached shell could not run binary)',
        ];
    }

    $contents = trim((string) file_get_contents($marker));
    @unlink($marker);

    $expected = $isPython ? 'nohup-python-ok' : 'nohup-php-ok';
    if ($contents !== $expected) {
        return [
            'ok' => false,
            'output' => 'pid:' . $pid,
            'error' => 'marker contents unexpected',
        ];
    }

    return [
        'ok' => true,
        'output' => 'pid:' . $pid . ' marker:' . $expected,
        'error' => '',
    ];
}
