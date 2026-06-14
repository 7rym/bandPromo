<?php
declare(strict_types=1);

require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/page-registry.php';

function bandpromo_player_module_defaults(): array {
    return [
        'playlist' => ['enabled' => true, 'required' => true, 'label' => 'Playlist'],
        'lyrics' => ['enabled' => true, 'required' => true, 'label' => 'Lyrics'],
        'gallery' => ['enabled' => true, 'required' => false, 'label' => 'Gallery'],
        'pages' => ['enabled' => true, 'required' => false, 'label' => 'Pages'],
    ];
}

function bandpromo_player_modules_config(): array {
    $defaults = bandpromo_player_module_defaults();
    $configured = get_config('player.modules', []);
    if (!is_array($configured)) {
        $configured = [];
    }

    $modules = [];
    foreach ($defaults as $key => $meta) {
        $entry = is_array($configured[$key] ?? null) ? $configured[$key] : [];
        $modules[$key] = [
            'enabled' => !empty($meta['required']) ? true : (bool) ($entry['enabled'] ?? $meta['enabled']),
            'required' => !empty($meta['required']),
            'label' => (string) ($entry['label'] ?? $meta['label']),
        ];
    }

    return $modules;
}

function bandpromo_player_module_enabled(string $module): bool {
    $modules = bandpromo_player_modules_config();

    return !empty($modules[$module]['enabled']);
}

function bandpromo_player_default_view(): string {
    $view = trim((string) get_config('player.default_view', 'playlist'));
    if ($view === '') {
        return 'playlist';
    }

    return $view;
}

function bandpromo_page_player_visible_entries(string $root): array {
    if (!bandpromo_player_module_enabled('pages')) {
        return [];
    }

    $entries = [];
    foreach (bandpromo_page_registry_entries($root) as $entry) {
        if (empty($entry['show_in_player'])) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login') {
            continue;
        }
        $entries[] = $entry;
    }

    return $entries;
}

function bandpromo_player_content_tabs(string $root): array {
    $modules = bandpromo_player_modules_config();

    if (!empty($modules['playlist']['enabled'])) {
        $tabs[] = [
            'view' => 'playlist',
            'label' => (string) $modules['playlist']['label'],
            'kind' => 'module',
        ];
    }

    if (!empty($modules['lyrics']['enabled'])) {
        $tabs[] = [
            'view' => 'lyrics',
            'label' => (string) $modules['lyrics']['label'],
            'kind' => 'module',
        ];
    }

    if (!empty($modules['pages']['enabled'])) {
        foreach (bandpromo_page_player_visible_entries($root) as $entry) {
            $tabs[] = [
                'view' => 'page-' . $entry['id'],
                'label' => (string) ($entry['label'] ?? $entry['title']),
                'kind' => 'page',
                'page_id' => (string) $entry['id'],
            ];
        }
    }

    if (!empty($modules['gallery']['enabled'])) {
        $tabs[] = [
            'view' => 'gallery',
            'label' => (string) $modules['gallery']['label'],
            'kind' => 'module',
        ];
    }

    return $tabs;
}
