<?php
/**
 * Build Log Poller
 * Returns current content of log/build.log plus a flag indicating
 * whether the build process is still running.
 * Admin-only endpoint — polled every second by the Config tab.
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
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/admin-audit.php';

$root_dir  = dirname(dirname(__FILE__));
$mode = isset($_GET['mode']) ? strtolower(trim((string) $_GET['mode'])) : 'full';
if (!in_array($mode, ['full', 'optimize'], true)) {
    $mode = 'full';
}

$log_file  = $root_dir . '/log/' . ($mode === 'optimize' ? 'optimize.log' : 'build.log');
$lock_file = $root_dir . '/log/' . ($mode === 'optimize' ? 'optimize.lock' : 'build.lock');
$meta_file = $root_dir . '/log/' . ($mode === 'optimize' ? 'optimize.meta.json' : 'build.meta.json');
$audit_state_file = $root_dir . '/log/admin-audit/' . ($mode === 'optimize' ? 'state-optimize-build.json' : 'state-full-build.json');
$validation_file = $root_dir . '/play/playlist-validation.json';

$content    = '';
$metadata_validation = null;

function bandpromo_build_poller_load_json(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function bandpromo_build_poller_extract_run_id(string $content): string
{
    if (preg_match('/^RUN_ID:([a-f0-9_\.\-]+)/mi', $content, $matches)) {
        return trim((string) $matches[1]);
    }
    return '';
}

if (file_exists($log_file)) {
    $content = file_get_contents($log_file);
    if ($content === false) {
        $content = '';
    }
}

if (file_exists($validation_file)) {
    $validation_json = file_get_contents($validation_file);
    if ($validation_json !== false) {
        $parsed = json_decode($validation_json, true);
        if (is_array($parsed)) {
            $metadata_validation = $parsed;
        }
    }
}

$build_meta = bandpromo_build_poller_load_json($meta_file);
$build_run_id = trim((string) ($build_meta['run_id'] ?? ''));

// Detect exit code from log content and strip it from display
$exit_code = null;
if (preg_match('/\nEXITCODE:(\-?\d+)\s*$/', $content, $m)) {
    $exit_code = (int)$m[1];
    $content   = preg_replace('/\nEXITCODE:\-?\d+\s*$/', '', $content);
}

if ($build_run_id === '') {
    $build_run_id = bandpromo_build_poller_extract_run_id($content);
}

$content = preg_replace('/^RUN_ID:[^\r\n]+\r?\n?/mi', '', $content, 1);

// Determine running state:
// - Primary: lock file exists AND no EXITCODE yet → still running
// - Secondary: try /proc/$pid as extra confirmation
if (file_exists($lock_file)) {
    if ($exit_code === null) {
        // No EXITCODE in log yet → must still be running
        $is_running = true;
    } else {
        // EXITCODE written → done; clean up lock file
        @unlink($lock_file);
        $is_running = false;
    }
} else {
    // No lock file
    $is_running = false;
}

$success = !$is_running && $exit_code !== null && $exit_code === 0;

if (!$is_running && $exit_code !== null && $build_run_id !== '') {
    bandpromo_admin_audit_ensure_dir();
    $audit_state = bandpromo_build_poller_load_json($audit_state_file);
    $already_audited = (($audit_state['run_id'] ?? '') === $build_run_id);

    if (!$already_audited) {
        $started_at = (int) ($build_meta['started_at'] ?? 0);
        $duration_seconds = $started_at > 0 ? max(0, time() - $started_at) : null;
        $audit_action = $success ? 'build_completed' : 'build_failed';
        $audit_data = [
            'mode' => $mode,
            'run_id' => $build_run_id,
            'exit_code' => $exit_code,
        ];
        if ($duration_seconds !== null) {
            $audit_data['duration_seconds'] = $duration_seconds;
        }

        bandpromo_admin_audit_log($audit_action, [
            'actor' => trim((string) ($build_meta['actor'] ?? '')),
            'ip' => trim((string) ($build_meta['ip'] ?? '')),
            'user_agent' => trim((string) ($build_meta['user_agent'] ?? '')),
            'target_type' => 'build',
            'target_id' => $mode,
            'status' => $success ? 'ok' : 'error',
            'data' => $audit_data,
        ]);

        file_put_contents($audit_state_file, json_encode([
            'run_id' => $build_run_id,
            'audited_at' => gmdate('Y-m-d H:i:s'),
            'mode' => $mode,
            'exit_code' => $exit_code,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if ($success) {
    $build_required_state = bandpromo_clear_build_required_for_action($mode);
} else {
    $build_required_state = bandpromo_get_build_required_state();
}

echo json_encode([
    'content'    => $content,
    'is_running' => $is_running,
    'mode' => $mode,
    'exit_code'  => $exit_code,
    'success'    => $success,
    'metadata_validation' => $metadata_validation,
    'build_required' => !empty($build_required_state['required']),
    'build_required_state' => $build_required_state,
], JSON_UNESCAPED_UNICODE);
exit;
