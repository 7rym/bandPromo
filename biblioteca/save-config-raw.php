<?php
/**
 * Save web-config.json (full raw replace).
 * Accepts the complete JSON blob via POST body. Admin-only.
 * Used by the Basics editor in admin.php.
 */
require_once __DIR__ . '/admin-api-guard.php';
session_write_close(); // release lock before file I/O

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/site-contact.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false || trim($body) === '') {
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

// Validate JSON
$decoded = json_decode($body);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

$decoded_array = json_decode($body, true);
if (!is_array($decoded_array)) {
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$config_file = dirname(__DIR__) . '/web-config.json';
$branch = isset($_GET['branch']) ? strtolower(trim((string) $_GET['branch'])) : '';
$existing_config = [];
if (file_exists($config_file)) {
    $loaded = json_decode(file_get_contents($config_file) ?: '{}', true);
    if (is_array($loaded)) {
        $existing_config = $loaded;
    }
}

// Write pretty-printed JSON
$syncRoots = [];
if ($branch === 'site') {
    $syncRoots = ['site'];
} elseif ($branch === 'media') {
    $syncRoots = ['media'];
}
if (!empty($syncRoots)) {
    bandpromo_sync_scoped_config_fields($decoded_array, $syncRoots);
}
if ($branch === 'site' && isset($decoded_array['site']) && is_array($decoded_array['site'])) {
    $contactError = bandpromo_site_prepare_contact_fields($decoded_array['site']);
    if ($contactError !== null) {
        http_response_code(400);
        echo json_encode(['error' => $contactError]);
        exit;
    }
}

$pretty = json_encode($decoded_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($config_file, $pretty) === false) {
    bandpromo_admin_audit_log('config_saved', [
        'target_type' => 'config',
        'target_id' => 'web-config.json:' . ($branch !== '' ? $branch : 'full'),
        'status' => 'error',
        'data' => ['error' => 'write failed'],
    ]);
    echo json_encode(['error' => 'Could not write web-config.json — check file permissions']);
    exit;
}

if ($branch === 'site') {
    $task = bandpromo_run_light_task('scripts/makePWA.py');
    if ($task['ok']) {
        bandpromo_admin_audit_log('config_saved', [
            'target_type' => 'config',
            'target_id' => 'web-config.json:site',
            'status' => 'ok',
            'data' => ['build_required' => false, 'auto_tasks' => ['manifest']],
        ]);
        echo json_encode([
            'ok' => true,
            'build_required' => false,
            'build_required_state' => bandpromo_get_build_required_state(),
            'auto_tasks' => ['manifest'],
        ]);
        exit;
    }

    $state = bandpromo_mark_build_required('site_config_changed');
    bandpromo_admin_audit_log('config_saved', [
        'target_type' => 'config',
        'target_id' => 'web-config.json:site',
        'status' => 'warning',
        'data' => ['build_required' => true, 'reasons' => $state['reasons'] ?? [], 'warning' => 'automatic manifest refresh failed'],
    ]);
    echo json_encode([
        'ok' => true,
        'build_required' => true,
        'build_required_state' => $state,
        'warning' => 'Saved, but automatic manifest refresh failed.',
        'task_output' => $task['output'],
    ]);
    exit;
}

if ($branch === 'media') {
    $existing_media = isset($existing_config['media']) && is_array($existing_config['media'])
        ? $existing_config['media']
        : [];
    $new_config = json_decode($pretty, true);
    $new_media = isset($new_config['media']) && is_array($new_config['media'])
        ? $new_config['media']
        : [];

    $existing_cover = trim((string) ($existing_media['cover'] ?? ''));
    $new_cover = trim((string) ($new_media['cover'] ?? ''));

    if ($existing_cover !== $new_cover) {
        $task = bandpromo_run_light_task('scripts/makePlaylists.py');
        if ($task['ok']) {
            $state = bandpromo_mark_build_required('theme_cover_changed');
            $imageTask = bandpromo_run_light_task('scripts/optimizeMedia.py', [
                'BANDPROMO_OPTIMIZE_MODE' => 'image-only',
            ]);
            if ($imageTask['ok']) {
                $state = bandpromo_clear_build_required_tasks(['image-delivery']);
                bandpromo_admin_audit_log('config_saved', [
                    'target_type' => 'config',
                    'target_id' => 'web-config.json:media',
                    'status' => !empty($state['required']) ? 'warning' : 'ok',
                    'data' => [
                        'build_required' => !empty($state['required']),
                        'reasons' => $state['reasons'] ?? [],
                        'auto_tasks' => ['playlist-scan', 'image-delivery'],
                    ],
                ]);
                echo json_encode([
                    'ok' => true,
                    'build_required' => !empty($state['required']),
                    'build_required_state' => $state,
                    'auto_tasks' => ['playlist-scan', 'image-delivery'],
                ]);
                exit;
            }

            bandpromo_admin_audit_log('config_saved', [
                'target_type' => 'config',
                'target_id' => 'web-config.json:media',
                'status' => 'warning',
                'data' => [
                    'build_required' => true,
                    'reasons' => $state['reasons'] ?? [],
                    'auto_tasks' => ['playlist-scan'],
                    'warning' => 'automatic image refresh failed',
                ],
            ]);
            echo json_encode([
                'ok' => true,
                'build_required' => true,
                'build_required_state' => $state,
                'auto_tasks' => ['playlist-scan'],
                'warning' => 'Saved and refreshed playlist metadata, but the automatic image refresh failed.',
                'task_output' => trim(($task['output'] ?? '') . "\n" . ($imageTask['output'] ?? '')),
            ]);
            exit;
        }

        $state = bandpromo_mark_build_required('theme_config_changed');
        bandpromo_admin_audit_log('config_saved', [
            'target_type' => 'config',
            'target_id' => 'web-config.json:media',
            'status' => 'warning',
            'data' => ['build_required' => true, 'reasons' => $state['reasons'] ?? [], 'warning' => 'automatic playlist refresh failed after cover change'],
        ]);
        echo json_encode([
            'ok' => true,
            'build_required' => true,
            'build_required_state' => $state,
            'warning' => 'Saved, but automatic playlist refresh failed after the cover change.',
            'task_output' => $task['output'],
        ]);
        exit;
    }

    bandpromo_admin_audit_log('config_saved', [
        'target_type' => 'config',
        'target_id' => 'web-config.json:media',
        'status' => 'ok',
        'data' => ['build_required' => false],
    ]);
    echo json_encode([
        'ok' => true,
        'build_required' => false,
        'build_required_state' => bandpromo_get_build_required_state(),
        'auto_tasks' => [],
    ]);
    exit;
}

if ($branch === 'support') {
    bandpromo_admin_audit_log('config_saved', [
        'target_type' => 'config',
        'target_id' => 'web-config.json:support',
        'status' => 'ok',
        'data' => ['build_required' => false],
    ]);
    echo json_encode([
        'ok' => true,
        'build_required' => false,
        'build_required_state' => bandpromo_get_build_required_state(),
        'auto_tasks' => [],
    ]);
    exit;
}

$reason = $branch === 'media' ? 'theme_config_changed' : 'web_config_changed';
$state = bandpromo_mark_build_required($reason);
bandpromo_admin_audit_log('config_saved', [
    'target_type' => 'config',
    'target_id' => 'web-config.json:' . ($branch !== '' ? $branch : 'full'),
    'status' => 'warning',
    'data' => ['build_required' => true, 'reasons' => $state['reasons'] ?? []],
]);
echo json_encode([
    'ok' => true,
    'build_required' => true,
    'build_required_state' => $state,
]);
