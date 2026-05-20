<?php
/**
 * Build Pipeline Runner
 * Executes scripts/build.py via proc_open and streams output to log/build.log
 * Admin-only endpoint — called by the Config tab Build button.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // Also allow setup session (setup wizard uses $_SESSION['user'])
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/release-package.php';

$root_dir  = dirname(dirname(__FILE__));
$log_dir   = $root_dir . '/log';
$request_data = [];
$raw_body = file_get_contents('php://input');
if ($raw_body !== false && trim($raw_body) !== '') {
    $parsed = json_decode($raw_body, true);
    if (is_array($parsed)) {
        $request_data = $parsed;
    }
}

$mode = $_POST['mode'] ?? ($request_data['mode'] ?? 'full');
if (!is_string($mode)) {
    $mode = 'full';
}
$mode = strtolower(trim($mode));
if (!in_array($mode, ['full', 'optimize'], true)) {
    $mode = 'full';
}

$log_file  = $log_dir  . ($mode === 'optimize' ? '/optimize.log' : '/build.log');
$lock_file = $log_dir  . ($mode === 'optimize' ? '/optimize.lock' : '/build.lock');
$meta_file = $log_dir  . ($mode === 'optimize' ? '/optimize.meta.json' : '/build.meta.json');
$script    = $root_dir . ($mode === 'optimize' ? '/scripts/optimizeMedia.py' : '/scripts/build.py');
$is_windows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
$build_run_id = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : uniqid('build_', true);
$build_actor = trim((string) ($_SESSION['username'] ?? 'unknown'));
$build_ip = bandpromo_admin_audit_client_ip();
$build_user_agent = bandpromo_admin_audit_user_agent();
$debug = [
    'mode' => $mode,
    'os' => PHP_OS_FAMILY,
    'launcher' => $is_windows ? 'windows-powershell-start-process' : 'unix-nohup-sh',
    'python' => null,
    'default_theme_package' => null,
    'script' => $script,
    'launch_command' => null,
    'launch_exit_code' => null,
    'launch_output_tail' => null,
];

function first_command_path(string $raw): string {
    $lines = preg_split('/\r\n|\r|\n/', trim($raw));
    return trim($lines[0] ?? '');
}

function is_working_python(string $candidate): bool {
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

function resolve_python_interpreter(): string {
    $env_python = trim((string) getenv('BUILD_PYTHON'));
    if ($env_python !== '' && is_working_python($env_python)) {
        return $env_python;
    }

    $workspace_venv = dirname(__DIR__) . '/.venv/Scripts/python.exe';
    if (file_exists($workspace_venv) && is_working_python($workspace_venv)) {
        return $workspace_venv;
    }

    $project_venv = dirname(dirname(__DIR__)) . '/.venv/Scripts/python.exe';
    if (file_exists($project_venv) && is_working_python($project_venv)) {
        return $project_venv;
    }

    $python_candidates = ['python3', 'python'];
    foreach ($python_candidates as $candidate) {
        $test = shell_exec("where $candidate 2>nul") ?? shell_exec("which $candidate 2>/dev/null");
        if (!$test) {
            continue;
        }
        $resolved = first_command_path($test);
        if ($resolved !== '' && is_working_python($resolved)) {
            return $resolved;
        }
    }

    return '';
}

function build_has_exit_code(string $log_file): bool {
    if (!file_exists($log_file)) {
        return false;
    }
    $content = file_get_contents($log_file);
    if ($content === false) {
        return false;
    }
    return preg_match('/\nEXITCODE:\-?\d+\s*$/', $content) === 1;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Prevent concurrent builds
if (file_exists($lock_file)) {
    if (!build_has_exit_code($log_file)) {
        echo json_encode(['error' => 'Build already in progress', 'running' => true]);
        exit;
    }
    unlink($lock_file);
}

// Lock immediately so package preparation also counts as in-progress work.
file_put_contents($lock_file, 'preparing');

// Ensure log directory exists and clear old log before preparing the starter pack.
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0750, true);
}
file_put_contents($log_file, '');
file_put_contents($log_file, "[setup] Preparing your first build...\n", FILE_APPEND);

try {
    $debug['default_theme_package'] = bandpromo_ensure_default_theme_package(
        $root_dir,
        BANDPROMO_RELEASE_MANIFEST_URL,
        static function (string $message) use ($log_file): void {
            file_put_contents($log_file, $message . "\n", FILE_APPEND);
        }
    );
} catch (Throwable $throwable) {
    file_put_contents($log_file, '[starter pack] Failed: ' . $throwable->getMessage() . "\n", FILE_APPEND);
    @unlink($lock_file);
    echo json_encode([
        'error' => 'bandPromo could not prepare the starter design pack this site needs before the build can continue. ' . $throwable->getMessage(),
        'debug' => $debug,
    ]);
    exit;
}

file_put_contents($log_file, "RUN_ID:{$build_run_id}\n", FILE_APPEND);
file_put_contents($log_file, "DEBUG Build launcher: " . ($is_windows ? 'windows' : 'unix') . "\n", FILE_APPEND);
file_put_contents($log_file, "DEBUG Mode: " . $mode . "\n", FILE_APPEND);
if (is_array($debug['default_theme_package'])) {
    $themePackage = $debug['default_theme_package'];
    $themeState = !empty($themePackage['installed']) ? 'downloaded' : 'already present';
    file_put_contents($log_file, "DEBUG Default theme package: {$themeState} ({$themePackage['version']})\n", FILE_APPEND);
}
file_put_contents($meta_file, json_encode([
    'run_id' => $build_run_id,
    'mode' => $mode,
    'actor' => $build_actor,
    'ip' => $build_ip,
    'user_agent' => $build_user_agent,
    'started_at' => time(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// Find python interpreter
$python = resolve_python_interpreter();
$debug['python'] = $python;

if ($python === '' || !file_exists($script)) {
    file_put_contents($log_file, "FAILED Could not resolve build runtime or script path.\n", FILE_APPEND);
    @unlink($meta_file);
    echo json_encode(['error' => 'Could not resolve build runtime', 'debug' => $debug]);
    exit;
}

// Write lock file before launch. The background runner clears it when done.
file_put_contents($lock_file, 'running');

$started = false;

if ($is_windows) {
    $runner_bat = $log_dir . '/run-build.bat';
    $bat = [];
    $bat[] = '@echo off';
    $bat[] = '"' . str_replace('"', '""', $python) . '" -u "' . str_replace('"', '""', $script) . '" >> "' . str_replace('"', '""', $log_file) . '" 2>&1';
    $bat[] = 'echo EXITCODE:%ERRORLEVEL%>>"' . str_replace('"', '""', $log_file) . '"';
    $bat[] = 'del /f /q "' . str_replace('"', '""', $lock_file) . '" >nul 2>&1';
    file_put_contents($runner_bat, implode("\r\n", $bat) . "\r\n");

    $ps_command = "Start-Process -FilePath '" . str_replace("'", "''", $runner_bat) . "' -WindowStyle Hidden";
    $launch_cmd = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command ' . escapeshellarg($ps_command);
    $debug['launch_command'] = $launch_cmd;
    $out = [];
    $rc = 1;
    exec($launch_cmd . ' 2>&1', $out, $rc);
    $debug['launch_exit_code'] = $rc;
    $debug['launch_output_tail'] = implode("\n", array_slice($out, -8));
    if ($rc === 0) {
        $started = true;
    }
} else {
    // Build the command — wrap in sh so we can capture exit code in background
    // Uses nohup to detach; exit code is appended as EXITCODE:N when done
    $inner_cmd = escapeshellarg($python) . ' -u ' . escapeshellarg($script)
               . ' >> ' . escapeshellarg($log_file) . ' 2>&1'
               . '; echo "EXITCODE:$?" >> ' . escapeshellarg($log_file)
               . '; rm -f ' . escapeshellarg($lock_file);

    $bg_cmd = 'nohup sh -c ' . escapeshellarg($inner_cmd) . ' > /dev/null 2>&1 & echo $!';
    $debug['launch_command'] = $bg_cmd;
    $pid = trim((string) shell_exec($bg_cmd));
    if ($pid !== '' && is_numeric($pid)) {
        $started = true;
        file_put_contents($lock_file, $pid);
        $debug['launch_exit_code'] = 0;
    } else {
        $debug['launch_exit_code'] = 1;
    }
}

if (!$started) {
    @unlink($lock_file);
    @unlink($meta_file);
    file_put_contents($log_file, "FAILED Could not start build process (launcher failed).\n", FILE_APPEND);
    if (!empty($debug['launch_output_tail'])) {
        file_put_contents($log_file, "DEBUG Launcher output:\n" . $debug['launch_output_tail'] . "\n", FILE_APPEND);
    }
    bandpromo_admin_audit_log('build_started', [
        'target_type' => 'build',
        'target_id' => $mode,
        'status' => 'error',
        'data' => ['error' => 'Could not start build process'],
    ]);
    echo json_encode(['error' => 'Could not start build process', 'debug' => $debug]);
    exit;
}

bandpromo_admin_audit_log('build_started', [
    'actor' => $build_actor,
    'ip' => $build_ip,
    'user_agent' => $build_user_agent,
    'target_type' => 'build',
    'target_id' => $mode,
    'status' => 'ok',
    'data' => ['mode' => $mode, 'run_id' => $build_run_id],
]);

echo json_encode(['ok' => true, 'message' => ($mode === 'optimize' ? 'Optimizer started' : 'Build started'), 'mode' => $mode, 'debug' => $debug]);
exit;
