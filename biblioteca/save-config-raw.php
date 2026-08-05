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

function bandpromo_support_normalize_hex(string $value): string
{
    $value = strtolower(trim($value));
    if (preg_match('/^#[0-9a-f]{6}$/', $value) === 1) {
        return $value;
    }
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $matches) === 1) {
        return '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
    }

    return '';
}

function bandpromo_support_relative_luminance(string $hex): float
{
    $channels = [
        hexdec(substr($hex, 1, 2)) / 255,
        hexdec(substr($hex, 3, 2)) / 255,
        hexdec(substr($hex, 5, 2)) / 255,
    ];
    foreach ($channels as &$channel) {
        $channel = $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
    unset($channel);

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function bandpromo_support_contrast_ratio(string $first, string $second): float
{
    $a = bandpromo_support_relative_luminance($first);
    $b = bandpromo_support_relative_luminance($second);
    $lighter = max($a, $b);
    $darker = min($a, $b);

    return ($lighter + 0.05) / ($darker + 0.05);
}

function bandpromo_support_prepare_config(array &$config): ?string
{
    $support = is_array($config['support'] ?? null) ? $config['support'] : [];
    $support['enabled'] = !empty($support['enabled']);
    $support['mode'] = 'link';

    $label = trim((string) ($support['label'] ?? 'Support'));
    if ($label === '') {
        $label = 'Support';
    }
    $labelLength = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
    if ($labelLength > 80) {
        return 'Support button text must be 80 characters or fewer.';
    }
    $support['label'] = $label;

    $url = trim((string) ($support['url'] ?? ''));
    if ($url !== '') {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            return 'Support URL must be a valid http:// or https:// address.';
        }
    }
    $support['url'] = $url;

    $handle = trim((string) ($support['kofi_page_id'] ?? ''));
    if ($handle !== '' && preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $handle) !== 1) {
        return 'Ko-fi handle may contain only letters, numbers, underscores, and hyphens.';
    }
    $support['kofi_page_id'] = $handle;
    if ($support['enabled'] && $url === '' && $handle === '') {
        return 'Enter a direct support URL or Ko-fi handle before enabling the support button.';
    }

    $background = bandpromo_support_normalize_hex((string) ($support['button_background_color'] ?? '#323842'));
    $text = bandpromo_support_normalize_hex((string) ($support['button_text_color'] ?? '#ffffff'));
    if ($background === '' || $text === '') {
        return 'Support button colors must use 3- or 6-digit hex values.';
    }
    if (bandpromo_support_contrast_ratio($background, $text) < 4.5) {
        return 'Support button text and background need at least 4.5:1 contrast.';
    }
    $support['button_background_color'] = $background;
    $support['button_text_color'] = $text;
    $config['support'] = $support;

    return null;
}

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
if ($branch === 'support') {
    $supportError = bandpromo_support_prepare_config($decoded_array);
    if ($supportError !== null) {
        http_response_code(400);
        echo json_encode(['error' => $supportError]);
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
