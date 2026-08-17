<?php
declare(strict_types=1);

require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/page-registry.php';
require_once __DIR__ . '/theme-storage.php';

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

/**
 * Optional page tabs owned by a release (campaign Pages associations), ordered.
 *
 * @return list<string> Keys like page:bio
 */
function bandpromo_player_release_optional_tab_keys(string $root, string $releaseId): array
{
    require_once __DIR__ . '/page-storage.php';
    require_once __DIR__ . '/release-storage.php';

    $releaseId = bandpromo_release_normalize_id($releaseId);
    if ($releaseId === '' || $releaseId === BANDPROMO_RELEASE_DEFAULT_ID) {
        return [];
    }

    $owned = [];
    foreach (bandpromo_page_registry_entries($root) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pageId = bandpromo_page_normalize_id((string) ($entry['id'] ?? ''));
        if ($pageId === '' || $pageId === BANDPROMO_PAGE_REQUIRED_ID) {
            continue;
        }
        if (($entry['surface'] ?? 'player') === 'login' || !empty($entry['required'])) {
            continue;
        }
        try {
            $doc = bandpromo_page_load_document($root, $pageId);
        } catch (Throwable $throwable) {
            continue;
        }
        $owner = bandpromo_release_normalize_id(trim((string) ($doc['release_id'] ?? '')));
        if ($owner !== $releaseId) {
            continue;
        }
        $owned[] = [
            'key' => 'page:' . $pageId,
            'sort_order' => (int) ($entry['sort_order'] ?? 0),
            'title' => (string) ($entry['title'] ?? $pageId),
        ];
    }

    if ($owned === []) {
        return [];
    }

    usort($owned, static function (array $left, array $right): int {
        $order = ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0);
        if ($order !== 0) {
            return $order;
        }

        return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
    });

    return array_values(array_map(static function (array $row): string {
        return (string) ($row['key'] ?? '');
    }, $owned));
}

function bandpromo_player_strip_gallery_tab_keys(array $keys): array {
    return array_values(array_filter($keys, static fn(string $key): bool => $key !== 'module:gallery'));
}

function bandpromo_player_valid_optional_tab_key_set(string $root, ?array $allowKeys = null): array {
    $valid = [];

    if (is_array($allowKeys)) {
        foreach ($allowKeys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $valid[$key] = true;
        }

        return $valid;
    }

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

function bandpromo_player_tab_order_keys(string $root, string $releaseId = ''): array {
    $releaseKeys = bandpromo_player_release_optional_tab_keys($root, $releaseId);
    if ($releaseKeys !== []) {
        return bandpromo_player_strip_gallery_tab_keys($releaseKeys);
    }

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

function bandpromo_player_tab_from_key(string $root, string $key, bool $requireShowInPlayer = true): ?array {
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
    if ($entry === null) {
        return null;
    }
    if ($requireShowInPlayer && empty($entry['show_in_player'])) {
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

function bandpromo_player_playlist_selector_mode(?array $config = null): string
{
    // Prefer Base brand document; fall back to legacy web-config once for migration, then hard default.
    try {
        $root = defined('BANDPROMO_ROOT') ? (string) BANDPROMO_ROOT : dirname(__DIR__);
        if (function_exists('bandpromo_theme_load_active_document')) {
            $document = bandpromo_theme_load_active_document($root);
            if (is_array($document)) {
                $fromBrand = $document['player']['playlist_selector'] ?? null;
                if ($fromBrand !== null && trim((string) $fromBrand) !== '') {
                    return bandpromo_player_normalize_playlist_selector_mode($fromBrand);
                }
            }
        }
    } catch (Throwable $throwable) {
        // Brand storage may be unavailable during early bootstrap.
    }

    if ($config === null) {
        $raw = function_exists('get_config') ? get_config('player.playlist_selector', 'coverflow') : 'coverflow';
    } else {
        $raw = $config['player']['playlist_selector'] ?? 'coverflow';
    }

    return bandpromo_player_normalize_playlist_selector_mode($raw);
}

/**
 * Whether the in-flow support CTA (#beggars-banquet) may render.
 * Base brand owns the toggle; Settings → Support still supplies URL/label/colors.
 */
function bandpromo_player_beggars_banquet_enabled(): bool
{
    try {
        $root = defined('BANDPROMO_ROOT') ? (string) BANDPROMO_ROOT : dirname(__DIR__);
        if (function_exists('bandpromo_theme_load_active_document')) {
            require_once __DIR__ . '/theme-storage.php';
            $document = bandpromo_theme_load_active_document($root);
            if (is_array($document)) {
                $player = is_array($document['player'] ?? null) ? $document['player'] : [];
                if (array_key_exists('beggars_banquet', $player)) {
                    return (bool) filter_var($player['beggars_banquet'], FILTER_VALIDATE_BOOLEAN);
                }
            }
        }
    } catch (Throwable $throwable) {
        // Brand storage may be unavailable during early bootstrap.
    }

    return true;
}

/**
 * Whether the mirrored cover reflection under the main flip-card is shown.
 * Base brand owns the toggle (desktop split layout only; small screens already hide it).
 */
function bandpromo_player_cover_reflection_enabled(): bool
{
    try {
        $root = defined('BANDPROMO_ROOT') ? (string) BANDPROMO_ROOT : dirname(__DIR__);
        if (function_exists('bandpromo_theme_load_active_document')) {
            require_once __DIR__ . '/theme-storage.php';
            $document = bandpromo_theme_load_active_document($root);
            if (is_array($document)) {
                $player = is_array($document['player'] ?? null) ? $document['player'] : [];
                if (array_key_exists('cover_reflection', $player)) {
                    return (bool) filter_var($player['cover_reflection'], FILTER_VALIDATE_BOOLEAN);
                }
            }
        }
    } catch (Throwable $throwable) {
        // Brand storage may be unavailable during early bootstrap.
    }

    return true;
}

function bandpromo_player_normalize_playlist_selector_mode(mixed $value): string
{
    if (function_exists('bandpromo_theme_normalize_playlist_selector_mode')) {
        return bandpromo_theme_normalize_playlist_selector_mode($value);
    }

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

function bandpromo_player_content_tabs(string $root, bool $operatorBypass = false, string $releaseId = ''): array {
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

    $releaseKeys = bandpromo_player_release_optional_tab_keys($root, $releaseId);
    $requireShowInPlayer = $releaseKeys === [];
    foreach (bandpromo_player_tab_order_keys($root, $releaseId) as $key) {
        $tab = bandpromo_player_tab_from_key($root, $key, $requireShowInPlayer);
        if ($tab !== null) {
            $tabs[] = $tab;
        }
    }

    return $tabs;
}
