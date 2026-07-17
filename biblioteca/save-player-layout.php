<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/page-registry.php';
require_once __DIR__ . '/player-modules.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$root = dirname(__DIR__);
$configPath = $root . '/web-config.json';
$body = file_get_contents('php://input');
$payload = json_decode(is_string($body) ? $body : '', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

try {
    if (!is_file($configPath)) {
        throw new RuntimeException('Missing web-config.json.');
    }

    $config = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($config)) {
        throw new RuntimeException('web-config.json is invalid.');
    }

    if (!isset($config['player']) || !is_array($config['player'])) {
        $config['player'] = [];
    }
    if (!isset($config['player']['modules']) || !is_array($config['player']['modules'])) {
        $config['player']['modules'] = [];
    }

    $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
    foreach (['pages'] as $optionalModule) {
        if (array_key_exists($optionalModule, $modules)) {
            if (!isset($config['player']['modules'][$optionalModule]) || !is_array($config['player']['modules'][$optionalModule])) {
                $config['player']['modules'][$optionalModule] = [];
            }
            $config['player']['modules'][$optionalModule]['enabled'] = !empty($modules[$optionalModule]['enabled']);
        }
    }

    $tabOrder = is_array($payload['tab_order'] ?? null) ? $payload['tab_order'] : [];
    $normalizedTabOrder = [];
    foreach ($tabOrder as $item) {
        if (!is_string($item)) {
            continue;
        }
        $item = trim($item);
        if ($item === '' || $item === 'module:gallery') {
            continue;
        }
        $normalizedTabOrder[] = $item;
    }
    $config['player']['tab_order'] = $normalizedTabOrder;

    if (array_key_exists('shell_background', $payload)) {
        $config['player']['shell_background'] = bandpromo_player_normalize_shell_background_mode($payload['shell_background']);
    }

    if (array_key_exists('playlist_selector', $payload)) {
        $config['player']['playlist_selector'] = bandpromo_player_normalize_playlist_selector_mode($payload['playlist_selector']);
    }

    $config['player']['modules']['playlist'] = ['enabled' => true];
    $config['player']['modules']['lyrics'] = ['enabled' => true];
    if (!isset($config['player']['modules']['gallery']) || !is_array($config['player']['modules']['gallery'])) {
        $config['player']['modules']['gallery'] = [];
    }
    $config['player']['modules']['gallery']['enabled'] = false;

    $encodedConfig = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encodedConfig) || file_put_contents($configPath, $encodedConfig . "\n") === false) {
        throw new RuntimeException('Could not save web-config.json.');
    }

    global $config;
    $reloadedConfig = json_decode((string) file_get_contents($configPath), true);
    if (is_array($reloadedConfig)) {
        if (!isset($config['player']) || !is_array($config['player'])) {
            $config['player'] = [];
        }
        if (isset($reloadedConfig['player']) && is_array($reloadedConfig['player'])) {
            $config['player'] = $reloadedConfig['player'];
        }
    }

    $pageUpdates = is_array($payload['pages'] ?? null) ? $payload['pages'] : [];
    foreach ($pageUpdates as $pageUpdate) {
        if (!is_array($pageUpdate)) {
            continue;
        }
        $pageId = bandpromo_page_normalize_id((string) ($pageUpdate['id'] ?? ''));
        if ($pageId === '') {
            continue;
        }

        $changes = [];
        if (isset($pageUpdate['title'])) {
            $changes['title'] = (string) $pageUpdate['title'];
        }
        if (isset($pageUpdate['label'])) {
            $changes['label'] = (string) $pageUpdate['label'];
        }
        if (array_key_exists('show_in_player', $pageUpdate)) {
            $changes['show_in_player'] = (bool) $pageUpdate['show_in_player'];
        }
        if (isset($pageUpdate['sort_order'])) {
            $changes['sort_order'] = (int) $pageUpdate['sort_order'];
        }

        if ($changes !== []) {
            bandpromo_page_update_registry_entry($root, $pageId, $changes);
        }
    }

    bandpromo_admin_audit_log('player_layout_saved', [
        'target_type' => 'player_layout',
        'target_id' => 'player',
        'status' => 'ok',
        'data' => [
            'modules' => bandpromo_player_modules_config(),
            'shell_background' => bandpromo_player_shell_background_mode($config),
            'playlist_selector' => bandpromo_player_playlist_selector_mode($config),
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'modules' => bandpromo_player_modules_config(),
        'shell_background' => bandpromo_player_shell_background_mode($config),
        'playlist_selector' => bandpromo_player_playlist_selector_mode($config),
        'pages' => bandpromo_page_registry_entries($root),
        'tabs' => bandpromo_player_content_tabs($root),
        'layout' => bandpromo_player_layout_admin_state($root),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    bandpromo_admin_audit_log('player_layout_saved', [
        'target_type' => 'player_layout',
        'target_id' => 'player',
        'status' => 'error',
        'data' => ['error' => $throwable->getMessage()],
    ]);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
