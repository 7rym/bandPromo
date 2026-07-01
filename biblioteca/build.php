<?php
/**
 * Build Pipeline Runner
 * Executes scripts/build.py via proc_open and streams output to log/build.log
 * Admin-only endpoint — called by the Config tab Build button.
 */

require_once __DIR__ . '/admin-api-guard.php';

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/release-package.php';
require_once __DIR__ . '/publish-preflight-helpers.php';
require_once __DIR__ . '/build-lock.php';
require_once __DIR__ . '/build-launcher.php';
require_once __DIR__ . '/build-stages.php';
require_once __DIR__ . '/build-log-helpers.php';

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

$build_profile = 'full';
$build_stage_ids = [];
if ($mode === 'full') {
    $raw_profile = $request_data['profile'] ?? 'full';
    if (is_string($raw_profile) && bandpromo_build_profile_is_valid($raw_profile)) {
        $build_profile = $raw_profile;
    }
    $requested_stages = $request_data['stages'] ?? null;
    if (is_array($requested_stages)) {
        $build_stage_ids = bandpromo_build_filter_stage_ids($requested_stages);
    }
    if ($build_stage_ids === []) {
        $build_stage_ids = bandpromo_build_resolve_stage_ids($build_profile, null);
    }
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
    'launcher' => 'php-proc-runner',
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
    return bandpromo_build_log_has_exit_code(bandpromo_build_read_log_tail($log_file));
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Prevent concurrent builds
if (bandpromo_build_lock_active($root_dir, $mode)) {
    echo json_encode([
        'error' => 'Build already in progress',
        'running' => true,
        'mode' => $mode,
        'content' => bandpromo_build_read_log_tail($log_file),
    ]);
    exit;
}

// Lock immediately so package preparation also counts as in-progress work.
file_put_contents($lock_file, 'preparing');

// Ensure log directory exists and clear old log before preparing the starter pack.
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0750, true);
}
file_put_contents($log_file, '');
file_put_contents($log_file, bandpromo_build_log_started_lines($mode), FILE_APPEND);
file_put_contents($log_file, "[setup] Preparing your site for publish...\n", FILE_APPEND);

if ($mode === 'full') {
    bandpromo_run_publish_preflight($root_dir, static function (string $line) use ($log_file): void {
        file_put_contents($log_file, $line, FILE_APPEND);
    });
}

file_put_contents($log_file, "[setup] Starting publish build...\n", FILE_APPEND);

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
if ($mode === 'full') {
    file_put_contents($log_file, "DEBUG Profile: " . $build_profile . "\n", FILE_APPEND);
    file_put_contents($log_file, "DEBUG Stages: " . implode(', ', $build_stage_ids) . "\n", FILE_APPEND);
}
if (is_array($debug['default_theme_package'])) {
    $themePackage = $debug['default_theme_package'];
    $themeState = !empty($themePackage['installed']) ? 'downloaded' : 'already present';
    file_put_contents($log_file, "DEBUG Default theme package: {$themeState} ({$themePackage['version']})\n", FILE_APPEND);
}
file_put_contents($meta_file, json_encode([
    'run_id' => $build_run_id,
    'mode' => $mode,
    'profile' => $build_profile,
    'stages' => $build_stage_ids,
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

$launch = bandpromo_build_launch_background($python, $script, $log_file, $lock_file, $build_run_id, $is_windows);
$debug['launch_command'] = $launch['launch_command'] ?? null;
$debug['launch_exit_code'] = $launch['launch_exit_code'] ?? null;
$debug['launch_output_tail'] = $launch['launch_output_tail'] ?? null;
if ($launch['started'] ?? false) {
    $started = true;
    if (!$is_windows && !empty($launch['pid'])) {
        file_put_contents($lock_file, (string) $launch['pid']);
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
