<?php

if (!function_exists('bandpromo_first_command_path')) {
    function bandpromo_first_command_path(string $raw): string {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        return trim((string) ($lines[0] ?? ''));
    }
}

function bandpromo_can_shell_exec(): bool {
    return function_exists('shell_exec');
}

function bandpromo_can_proc_open(): bool {
    return function_exists('proc_open');
}

function bandpromo_is_working_python(string $candidate): bool {
    if ($candidate === '') {
        return false;
    }

    $out = [];
    $rc = 1;
    exec('"' . str_replace('"', '""', $candidate) . '" --version 2>&1', $out, $rc);
    if ($rc !== 0) {
        return false;
    }

    $joined = strtolower(implode("\n", $out));
    if (strpos($joined, 'python was not found') !== false) {
        return false;
    }

    return strpos($joined, 'python') !== false;
}

function bandpromo_resolve_python_interpreter(): string {
    $env_python = trim((string) getenv('BUILD_PYTHON'));
    if ($env_python !== '' && bandpromo_is_working_python($env_python)) {
        return $env_python;
    }

    $workspace_venv = dirname(__DIR__, 2) . '/.venv/Scripts/python.exe';
    if (file_exists($workspace_venv) && bandpromo_is_working_python($workspace_venv)) {
        return $workspace_venv;
    }

    $project_venv = dirname(__DIR__, 3) . '/.venv/Scripts/python.exe';
    if (file_exists($project_venv) && bandpromo_is_working_python($project_venv)) {
        return $project_venv;
    }

    if (bandpromo_can_shell_exec()) {
        foreach (['python3', 'python'] as $candidate) {
            $test = shell_exec("where $candidate 2>nul") ?? shell_exec("which $candidate 2>/dev/null");
            if (!$test) {
                continue;
            }
            $resolved = bandpromo_first_command_path($test);
            if ($resolved !== '' && bandpromo_is_working_python($resolved)) {
                return $resolved;
            }
        }
    }

    return '';
}

function bandpromo_resolve_light_task_ffmpeg(string $root_dir): string {
    $local = $root_dir . '/scripts/bin/' . (DIRECTORY_SEPARATOR === '\\' ? 'ffmpeg.exe' : 'ffmpeg');
    if (is_file($local)) {
        return $local;
    }

    $configured = trim((string) getenv('FFMPEG_PATH'));
    if ($configured !== '' && is_file($configured)) {
        return $configured;
    }

    if (bandpromo_can_shell_exec()) {
        foreach (['ffmpeg'] as $candidate) {
            $test = shell_exec("where $candidate 2>nul") ?? shell_exec("which $candidate 2>/dev/null");
            if (!$test) {
                continue;
            }
            $resolved = bandpromo_first_command_path($test);
            if ($resolved !== '' && is_file($resolved)) {
                return $resolved;
            }
        }
    }

    return $configured !== '' ? $configured : 'ffmpeg';
}

function bandpromo_light_task_env(string $root_dir, array $env_extras = []): array {
    $env = $_ENV;
    $env['BUILD_ROOT'] = $root_dir;
    $env['PYTHONIOENCODING'] = 'utf-8:replace';
    $env['FFMPEG_PATH'] = bandpromo_resolve_light_task_ffmpeg($root_dir);
    foreach ($env_extras as $key => $value) {
        $env[(string) $key] = (string) $value;
    }

    return $env;
}

function bandpromo_run_light_task(string $script_relative_path, array $env_extras = []): array {
    $root_dir = dirname(__DIR__);

    if (!bandpromo_can_proc_open()) {
        return [
            'ok' => false,
            'error' => 'Process execution is unavailable on this host',
            'output' => '',
            'exit_code' => null,
        ];
    }

    $python = bandpromo_resolve_python_interpreter();
    $script = $root_dir . '/' . ltrim($script_relative_path, '/');

    if ($python === '') {
        return [
            'ok' => false,
            'error' => 'Could not resolve Python runtime',
            'output' => '',
            'exit_code' => null,
        ];
    }

    if (!file_exists($script)) {
        return [
            'ok' => false,
            'error' => 'Script not found: ' . $script_relative_path,
            'output' => '',
            'exit_code' => null,
        ];
    }

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $env = bandpromo_light_task_env($root_dir, $env_extras);

    $process = proc_open([$python, '-u', $script], $descriptors, $pipes, $root_dir, $env);
    if (!is_resource($process)) {
        return [
            'ok' => false,
            'error' => 'Could not start task process',
            'output' => '',
            'exit_code' => null,
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);
    $output = trim((string) $stdout . (string) $stderr);

    return [
        'ok' => $exit_code === 0,
        'error' => $exit_code === 0 ? null : ('Task failed: ' . $script_relative_path),
        'output' => $output,
        'exit_code' => $exit_code,
    ];
}

function bandpromo_decode_light_json_output(string $stdout): ?array {
    $trimmed = trim($stdout);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    // Library helpers may print progress on stdout before the JSON payload.
    $lines = preg_split('/\r\n|\r|\n/', $trimmed);
    if (!is_array($lines)) {
        return null;
    }

    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim((string) $lines[$i]);
        if ($line === '') {
            continue;
        }
        $first = $line[0];
        if ($first !== '{' && $first !== '[') {
            continue;
        }
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    if (preg_match('/(\{.*\}|\[.*\])\s*$/s', $trimmed, $matches)) {
        $decoded = json_decode($matches[1], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function bandpromo_run_light_json_task(string $script_relative_path, array $payload): array {
    $root_dir = dirname(__DIR__);

    if (!bandpromo_can_proc_open()) {
        return [
            'ok' => false,
            'error' => 'Process execution is unavailable on this host',
            'output' => '',
            'exit_code' => null,
            'data' => null,
        ];
    }

    $python = bandpromo_resolve_python_interpreter();
    $script = $root_dir . '/' . ltrim($script_relative_path, '/');

    if ($python === '') {
        return [
            'ok' => false,
            'error' => 'Could not resolve Python runtime',
            'output' => '',
            'exit_code' => null,
            'data' => null,
        ];
    }

    if (!file_exists($script)) {
        return [
            'ok' => false,
            'error' => 'Script not found: ' . $script_relative_path,
            'output' => '',
            'exit_code' => null,
            'data' => null,
        ];
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $env = bandpromo_light_task_env($root_dir);

    $process = proc_open([$python, '-u', $script], $descriptors, $pipes, $root_dir, $env);
    if (!is_resource($process)) {
        return [
            'ok' => false,
            'error' => 'Could not start task process',
            'output' => '',
            'exit_code' => null,
            'data' => null,
        ];
    }

    fwrite($pipes[0], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit_code = proc_close($process);
    $output = trim((string) $stdout . (string) $stderr);
    $decoded = ($stdout !== false) ? bandpromo_decode_light_json_output((string) $stdout) : null;

    return [
        'ok' => $exit_code === 0,
        'error' => $exit_code === 0 ? null : ('Task failed: ' . $script_relative_path),
        'output' => $output,
        'exit_code' => $exit_code,
        'data' => is_array($decoded) ? $decoded : null,
    ];
}