<?php
declare(strict_types=1);

require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/page-registry.php';

function bandpromo_player_module_defaults(): array {
    return [
        'playlist' => ['enabled' => true, 'required' => true, 'label' => 'Playlist'],
        'lyrics' => ['enabled' => true, 'required' => true, 'label' => 'Lyrics'],
        'gallery' => ['enabled' => false, 'required' => false, 'label' => 'Gallery'],
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

function bandpromo_player_default_optional_tab_keys(string $root): array {
    $keys = [];
    $entries = bandpromo_page_registry_entries($root);
    usort($entries, static function (array $a, array $b): int {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });
    foreach ($entries as $entry) {
        if (empty($entry['show_in_player'])) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login') {
            continue;
        }
        $keys[] = 'page:' . $entry['id'];
    }

    return $keys;
}

function bandpromo_player_strip_gallery_tab_keys(array $keys): array {
    return array_values(array_filter($keys, static fn(string $key): bool => $key !== 'module:gallery'));
}

function bandpromo_player_valid_optional_tab_key_set(string $root): array {
    $valid = [];

    foreach (bandpromo_page_registry_entries($root) as $entry) {
        if (empty($entry['show_in_player'])) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login') {
            continue;
        }
        $valid['page:' . $entry['id']] = true;
    }

    return $valid;
}

function bandpromo_player_tab_order_keys(string $root): array {
    $configured = get_config('player.tab_order', null);
    $candidateKeys = [];

    if (is_array($configured) && $configured !== []) {
        foreach ($configured as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $candidateKeys[] = $item;
        }
    } else {
        $candidateKeys = bandpromo_player_default_optional_tab_keys($root);
    }

    $valid = bandpromo_player_valid_optional_tab_key_set($root);
    $resolved = [];
    $seen = [];

    foreach ($candidateKeys as $key) {
        if (!isset($valid[$key]) || isset($seen[$key])) {
            continue;
        }
        $resolved[] = $key;
        $seen[$key] = true;
    }

    foreach (bandpromo_player_default_optional_tab_keys($root) as $key) {
        if (isset($seen[$key])) {
            continue;
        }
        $resolved[] = $key;
        $seen[$key] = true;
    }

    return bandpromo_player_strip_gallery_tab_keys($resolved);
}

function bandpromo_player_tab_from_key(string $root, string $key): ?array {
    $modules = bandpromo_player_modules_config();

    if ($key === 'module:gallery') {
        return null;
    }

    if (!str_starts_with($key, 'page:')) {
        return null;
    }

    $pageId = bandpromo_page_normalize_id(substr($key, 5));
    if ($pageId === '') {
        return null;
    }

    $entry = bandpromo_page_registry_entry($root, $pageId);
    if ($entry === null || empty($entry['show_in_player'])) {
        return null;
    }
    if (($entry['surface'] ?? 'player') === 'login') {
        return null;
    }

    $releaseId = '';
    try {
        require_once __DIR__ . '/page-storage.php';
        $doc = bandpromo_page_load_document($root, $pageId);
        $releaseId = trim((string) ($doc['release_id'] ?? ''));
    } catch (Throwable $throwable) {
        $releaseId = '';
    }

    return [
        'view' => 'page-' . $pageId,
        'label' => (string) ($entry['label'] ?? $entry['title']),
        'kind' => 'page',
        'page_id' => $pageId,
        'release_id' => $releaseId,
    ];
}

function bandpromo_player_shell_background_mode(?array $config = null): string
{
    if ($config === null) {
        $raw = get_config('player.shell_background', 'living');
    } else {
        $raw = $config['player']['shell_background'] ?? 'living';
    }
    $mode = strtolower(trim((string) $raw));
    if ($mode === 'still' || $mode === 'living') {
        return $mode;
    }

    return 'living';
}

function bandpromo_player_normalize_shell_background_mode(mixed $value): string
{
    $mode = strtolower(trim((string) $value));
    if ($mode === 'still' || $mode === 'living') {
        return $mode;
    }

    return 'living';
}

function bandpromo_player_playlist_selector_mode(?array $config = null): string
{
    if ($config === null) {
        $raw = get_config('player.playlist_selector', 'coverflow');
    } else {
        $raw = $config['player']['playlist_selector'] ?? 'coverflow';
    }

    return bandpromo_player_normalize_playlist_selector_mode($raw);
}

function bandpromo_player_normalize_playlist_selector_mode(mixed $value): string
{
    $mode = strtolower(trim((string) $value));
    if ($mode === 'dropdown' || $mode === 'buttons' || $mode === 'coverflow') {
        return $mode;
    }

    return 'coverflow';
}

function bandpromo_player_layout_admin_state(string $root): array {
    $modules = bandpromo_player_modules_config();
    $pagesMap = bandpromo_page_admin_pages_map($root);
    $entriesById = [];
    foreach (bandpromo_page_registry_entries($root) as $entry) {
        $entriesById[(string) ($entry['id'] ?? '')] = $entry;
    }

    $active = [];
    $activeKeys = [];
    foreach (bandpromo_player_tab_order_keys($root) as $key) {
        if ($key === 'module:gallery') {
            continue;
        }

        if (!str_starts_with($key, 'page:')) {
            continue;
        }

        $pageId = bandpromo_page_normalize_id(substr($key, 5));
        if ($pageId === '' || $pageId === BANDPROMO_PAGE_REQUIRED_ID) {
            continue;
        }

        $entry = $entriesById[$pageId] ?? null;
        if ($entry === null || empty($entry['show_in_player'])) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login') {
            continue;
        }

        $meta = $pagesMap[$pageId] ?? [];
        $active[] = [
            'key' => 'page:' . $pageId,
            'kind' => 'page',
            'id' => $pageId,
            'title' => (string) ($entry['title'] ?? $pageId),
            'label' => (string) ($entry['label'] ?? $entry['title'] ?? $pageId),
            'emoji' => (string) ($meta['emoji'] ?? '📝'),
        ];
        $activeKeys[$key] = true;
    }

    foreach ($entriesById as $pageId => $entry) {
        $key = 'page:' . $pageId;
        if (isset($activeKeys[$key]) || $pageId === BANDPROMO_PAGE_REQUIRED_ID) {
            continue;
        }
        if (empty($entry['show_in_player'])) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login') {
            continue;
        }

        $meta = $pagesMap[$pageId] ?? [];
        $active[] = [
            'key' => $key,
            'kind' => 'page',
            'id' => $pageId,
            'title' => (string) ($entry['title'] ?? $pageId),
            'label' => (string) ($entry['label'] ?? $entry['title'] ?? $pageId),
            'emoji' => (string) ($meta['emoji'] ?? '📝'),
        ];
        $activeKeys[$key] = true;
    }

    $available = [];
    foreach ($entriesById as $pageId => $entry) {
        if ($pageId === BANDPROMO_PAGE_REQUIRED_ID) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login') {
            continue;
        }
        if (!empty($entry['show_in_player'])) {
            continue;
        }

        $meta = $pagesMap[$pageId] ?? [];
        $available[] = [
            'key' => 'page:' . $pageId,
            'kind' => 'page',
            'id' => $pageId,
            'title' => (string) ($entry['title'] ?? $pageId),
            'label' => (string) ($entry['label'] ?? $entry['title'] ?? $pageId),
            'emoji' => (string) ($meta['emoji'] ?? '📝'),
            'sort_order' => (int) ($entry['sort_order'] ?? 0),
        ];
    }

    usort($available, static function (array $a, array $b): int {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });

    return [
        'shell_background' => bandpromo_player_shell_background_mode(),
        'playlist_selector' => bandpromo_player_playlist_selector_mode(),
        'locked' => [
            [
                'key' => 'module:playlist',
                'kind' => 'module',
                'id' => 'playlist',
                'title' => 'Playlist',
                'label' => (string) ($modules['playlist']['label'] ?? 'Playlist'),
                'emoji' => '🎵',
            ],
            [
                'key' => 'module:lyrics',
                'kind' => 'module',
                'id' => 'lyrics',
                'title' => 'Lyrics',
                'label' => (string) ($modules['lyrics']['label'] ?? 'Lyrics'),
                'emoji' => '🎤',
            ],
        ],
        'active' => $active,
        'available' => $available,
    ];
}

function bandpromo_page_player_visible_entries(string $root): array {
    $entriesById = [];
    foreach (bandpromo_page_registry_entries($root) as $entry) {
        $entriesById[(string) ($entry['id'] ?? '')] = $entry;
    }

    $ordered = [];
    foreach (bandpromo_player_tab_order_keys($root) as $key) {
        if (!str_starts_with($key, 'page:')) {
            continue;
        }

        $pageId = bandpromo_page_normalize_id(substr($key, 5));
        $entry = $entriesById[$pageId] ?? null;
        if ($entry === null || empty($entry['show_in_player'])) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login') {
            continue;
        }

        $ordered[] = $entry;
    }

    return $ordered;
}

function bandpromo_player_playlist_tab_label(string $root, bool $operatorBypass = false): string
{
    require_once __DIR__ . '/playlist-storage.php';
    $count = count(bandpromo_playlist_player_catalog_entries($root, $operatorBypass));

    return $count > 1 ? 'Playlists' : 'Playlist';
}

function bandpromo_player_content_tabs(string $root, bool $operatorBypass = false): array {
    $modules = bandpromo_player_modules_config();
    $tabs = [];

    if (!empty($modules['playlist']['enabled'])) {
        $tabs[] = [
            'view' => 'playlist',
            'label' => bandpromo_player_playlist_tab_label($root, $operatorBypass),
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

    foreach (bandpromo_player_tab_order_keys($root) as $key) {
        $tab = bandpromo_player_tab_from_key($root, $key);
        if ($tab !== null) {
            $tabs[] = $tab;
        }
    }

    return $tabs;
}
