<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/config-loader.php';

const BANDPROMO_THEME_REGISTRY_VERSION = 1;
const BANDPROMO_THEME_DEFAULT_ID = 'setup-default';

function bandpromo_theme_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'themes';
}

function bandpromo_theme_registry_path(string $root): string
{
    return bandpromo_theme_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_theme_document_path(string $root, string $themeId): string
{
    return bandpromo_theme_storage_root($root) . DIRECTORY_SEPARATOR . bandpromo_theme_normalize_id($themeId) . '.json';
}

function bandpromo_theme_template_path(string $root, string $themeId): string
{
    return $root . DIRECTORY_SEPARATOR . 'biblioteca' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR
        . bandpromo_theme_normalize_id($themeId) . '.theme.template.json';
}

function bandpromo_theme_registry_ensure_dir(string $root): void
{
    $dir = bandpromo_theme_storage_root($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create data/themes directory.');
    }
}

function bandpromo_theme_normalize_id(string $themeId): string
{
    $themeId = strtolower(trim($themeId));
    $themeId = preg_replace('/[^a-z0-9-]+/', '-', $themeId) ?? '';
    $themeId = trim($themeId, '-');

    return substr($themeId, 0, 48);
}

function bandpromo_theme_default_color_tokens(): array
{
    return [
        'primary' => '#00d2ff',
        'secondary' => '#3a7bd5',
        'background' => '#121212',
        'text' => '#ffffff',
        'text_muted' => '#dddddd',
        'surface_mid' => '#1e1e24',
        'surface_deep' => '#000000',
        'link' => '#00d2ff',
        'link_hover' => '#5ce4ff',
        'link_visited' => '#3a7bd5',
    ];
}

function bandpromo_theme_css_variable_map(): array
{
    return [
        'color.primary' => '--primary-color',
        'color.secondary' => '--secondary-color',
        'color.background' => '--bg-color',
        'color.text' => '--text-color',
        'color.text_muted' => '--color-text-muted',
        'color.surface_mid' => '--color-surface-mid',
        'color.surface_deep' => '--color-surface-deep',
        'color.link' => '--color-link',
        'color.link_hover' => '--color-link-hover',
        'color.link_visited' => '--color-link-visited',
    ];
}

function bandpromo_theme_default_document(): array
{
    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'id' => BANDPROMO_THEME_DEFAULT_ID,
        'title' => 'Setup Default',
        'system' => true,
        'locked' => true,
        'tokens' => [
            'color' => bandpromo_theme_default_color_tokens(),
            'layout' => [
                'card_size_base' => '400px',
            ],
            'typography' => [
                'font_family_base' => "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                'font_family_heading' => '',
            ],
        ],
        'assets' => [
            'logo' => '/media/special/bandPromo_logo.png',
            'poster' => '/media/special/bandPromo_cover.png',
            'background_image' => '/media/special/bandPromo_background.png',
            'background_video' => '/media/special/bandPromo_background.mp4',
            'welcome_audio' => '/media/special/bandPromo_welcome.flac',
            'loggedin_audio' => '/media/special/bandPromo_loggedin.flac',
        ],
    ];
}

function bandpromo_theme_default_registry(): array
{
    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'themes' => [
            [
                'id' => BANDPROMO_THEME_DEFAULT_ID,
                'title' => 'Setup Default',
                'system' => true,
                'locked' => true,
                'sort_order' => 10,
            ],
        ],
    ];
}

function bandpromo_theme_normalize_hex_color(string $value, string $fallback): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1) {
        return strtolower($value);
    }

    return $fallback;
}

function bandpromo_theme_normalize_tokens(array $tokens): array
{
    $defaults = bandpromo_theme_default_document()['tokens'];
    $color = is_array($tokens['color'] ?? null) ? $tokens['color'] : [];
    $layout = is_array($tokens['layout'] ?? null) ? $tokens['layout'] : [];
    $typography = is_array($tokens['typography'] ?? null) ? $tokens['typography'] : [];
    $defaultColor = bandpromo_theme_default_color_tokens();

    $normalizedColor = [];
    foreach ($defaultColor as $key => $fallback) {
        $normalizedColor[$key] = bandpromo_theme_normalize_hex_color((string) ($color[$key] ?? ''), $fallback);
    }

    $cardSize = trim((string) ($layout['card_size_base'] ?? $defaults['layout']['card_size_base']));
    if ($cardSize === '' || !preg_match('/^\d+(px|rem|em|%)$/', $cardSize)) {
        $cardSize = (string) $defaults['layout']['card_size_base'];
    }

    $fontBase = trim((string) ($typography['font_family_base'] ?? $defaults['typography']['font_family_base']));
    if ($fontBase === '') {
        $fontBase = (string) $defaults['typography']['font_family_base'];
    }
    $fontHeading = trim((string) ($typography['font_family_heading'] ?? ''));

    return [
        'color' => $normalizedColor,
        'layout' => [
            'card_size_base' => $cardSize,
        ],
        'typography' => [
            'font_family_base' => $fontBase,
            'font_family_heading' => $fontHeading,
        ],
    ];
}

function bandpromo_theme_normalize_assets(array $assets): array
{
    $defaults = bandpromo_theme_default_document()['assets'];
    $normalized = [];
    foreach ($defaults as $key => $defaultValue) {
        $value = trim((string) ($assets[$key] ?? $defaultValue));
        if ($value !== '' && ($value[0] === '/' || preg_match('/^https?:\/\//i', $value) === 1)) {
            $normalized[$key] = $value;
        } elseif ($defaultValue !== '') {
            $normalized[$key] = $defaultValue;
        }
    }

    return $normalized;
}

function bandpromo_theme_normalize_document(array $input, ?string $expectedId = null): array
{
    $id = bandpromo_theme_normalize_id((string) ($input['id'] ?? $expectedId ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $id)) {
        throw new InvalidArgumentException('Invalid theme id.');
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = ucfirst(str_replace('-', ' ', $id));
    }

    $locked = !empty($input['locked']);
    $system = !empty($input['system']);
    if ($id === BANDPROMO_THEME_DEFAULT_ID) {
        $locked = true;
        $system = true;
    }

    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'id' => $id,
        'title' => $title,
        'system' => $system,
        'locked' => $locked,
        'tokens' => bandpromo_theme_normalize_tokens(is_array($input['tokens'] ?? null) ? $input['tokens'] : []),
        'assets' => bandpromo_theme_normalize_assets(is_array($input['assets'] ?? null) ? $input['assets'] : []),
    ];
}

function bandpromo_theme_write_registry(string $root, array $registry): void
{
    bandpromo_theme_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_theme_registry_path($root), $registry)) {
        throw new RuntimeException('Could not write data/themes/registry.json');
    }
}

function bandpromo_theme_load_registry(string $root): array
{
    bandpromo_theme_registry_ensure_dir($root);
    $decoded = bandpromo_json_read_array_file(bandpromo_theme_registry_path($root));
    if ($decoded === null) {
        $default = bandpromo_theme_default_registry();
        bandpromo_theme_write_registry($root, $default);

        return $default;
    }

    if (!isset($decoded['themes']) || !is_array($decoded['themes'])) {
        $decoded['themes'] = [];
    }

    return $decoded;
}

function bandpromo_theme_write_document(string $root, array $document): void
{
    $document = bandpromo_theme_normalize_document($document, (string) ($document['id'] ?? ''));
    if (!empty($document['locked'])) {
        throw new RuntimeException('Theme is locked and cannot be edited.');
    }

    bandpromo_theme_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_theme_document_path($root, $document['id']), $document)) {
        throw new RuntimeException('Could not write theme document.');
    }
}

function bandpromo_theme_load_document(string $root, string $themeId): array
{
    $themeId = bandpromo_theme_normalize_id($themeId);
    if ($themeId === '') {
        throw new InvalidArgumentException('Invalid theme id.');
    }

    $path = bandpromo_theme_document_path($root, $themeId);
    if (!is_file($path)) {
        throw new RuntimeException('Missing theme document: data/themes/' . $themeId . '.json');
    }

    $decoded = bandpromo_json_read_array_file($path);
    if ($decoded === null) {
        throw new RuntimeException('Invalid theme document: data/themes/' . $themeId . '.json');
    }

    return bandpromo_theme_normalize_document($decoded, $themeId);
}

function bandpromo_theme_registry_entries(string $root): array
{
    return bandpromo_theme_load_registry($root)['themes'] ?? [];
}

function bandpromo_theme_registry_entry(string $root, string $themeId): ?array
{
    $themeId = bandpromo_theme_normalize_id($themeId);
    foreach (bandpromo_theme_registry_entries($root) as $entry) {
        if (($entry['id'] ?? '') === $themeId) {
            return $entry;
        }
    }

    return null;
}

function bandpromo_theme_assets_from_config(array $config): array
{
    return [
        'logo' => (string) bandpromo_config_get_nonempty_value($config, 'install.brand.logo', '/media/special/bandPromo_logo.png'),
        'poster' => (string) bandpromo_config_get_nonempty_value($config, 'release.brand.poster', '/media/special/bandPromo_cover.png'),
        'background_image' => (string) bandpromo_config_get_nonempty_value($config, 'release.theme.background_image', '/media/special/bandPromo_background.png'),
        'background_video' => (string) bandpromo_config_get_nonempty_value($config, 'release.theme.background_video', '/media/special/bandPromo_background.mp4'),
        'welcome_audio' => (string) bandpromo_config_get_nonempty_value($config, 'install.theme.welcome_audio', '/media/special/bandPromo_welcome.flac'),
        'loggedin_audio' => (string) bandpromo_config_get_nonempty_value($config, 'install.theme.loggedin_audio', '/media/special/bandPromo_loggedin.flac'),
    ];
}

function bandpromo_theme_sync_assets_to_config(string $root, array $document): void
{
    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        return;
    }

    $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
    $map = [
        'logo' => ['install.brand.logo', 'install.theme.logo', 'media.logo'],
        'poster' => ['release.brand.poster', 'release.social.share_image', 'social.share_image'],
        'background_image' => ['release.theme.background_image', 'media.background_image'],
        'background_video' => ['release.theme.background_video', 'media.background_video'],
        'welcome_audio' => ['install.theme.welcome_audio', 'media.welcome_audio'],
        'loggedin_audio' => ['install.theme.loggedin_audio', 'media.loggedin_audio'],
    ];

    foreach ($map as $assetKey => $paths) {
        $value = trim((string) ($assets[$assetKey] ?? ''));
        if ($value === '') {
            continue;
        }
        foreach ($paths as $path) {
            bandpromo_config_set_path($config, $path, $value);
        }
    }

    bandpromo_json_write_file($configPath, $config);
}

function bandpromo_theme_active_id(string $root): string
{
    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    $themeId = bandpromo_theme_normalize_id((string) bandpromo_config_get_path($config, 'install.pointers.active_theme_id', ''));
    if ($themeId !== '' && is_file(bandpromo_theme_document_path($root, $themeId))) {
        return $themeId;
    }

    return BANDPROMO_THEME_DEFAULT_ID;
}

function bandpromo_theme_set_active_id(string $root, string $themeId): void
{
    $themeId = bandpromo_theme_normalize_id($themeId);
    if ($themeId === '' || !is_file(bandpromo_theme_document_path($root, $themeId))) {
        throw new InvalidArgumentException('Unknown theme.');
    }

    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        throw new RuntimeException('Missing web-config.json');
    }

    bandpromo_config_set_path($config, 'install.pointers.active_theme_id', $themeId);
    if (!bandpromo_json_write_file($configPath, $config)) {
        throw new RuntimeException('Could not update active theme pointer.');
    }

    $document = bandpromo_theme_load_document($root, $themeId);
    bandpromo_theme_sync_assets_to_config($root, $document);
}

function bandpromo_theme_load_active_document(string $root): array
{
    bandpromo_theme_ensure_seeded($root);

    return bandpromo_theme_load_document($root, bandpromo_theme_active_id($root));
}

function bandpromo_theme_token_value(array $document, string $path): string
{
    $segments = explode('.', $path);
    $value = $document['tokens'] ?? [];
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return '';
        }
        $value = $value[$segment];
    }

    return is_scalar($value) ? trim((string) $value) : '';
}

function bandpromo_theme_render_css(string $root): string
{
    try {
        $document = bandpromo_theme_load_active_document($root);
    } catch (Throwable $throwable) {
        return '';
    }

    $rules = [];
    foreach (bandpromo_theme_css_variable_map() as $tokenPath => $cssVar) {
        $value = bandpromo_theme_token_value($document, $tokenPath);
        if ($value !== '') {
            $rules[] = $cssVar . ':' . $value;
        }
    }

    $fontBase = bandpromo_theme_token_value($document, 'typography.font_family_base');
    if ($fontBase !== '') {
        $rules[] = 'font-family:' . $fontBase;
    }

    if ($rules === []) {
        return '';
    }

    return '<style id="bandpromo-theme-vars">:root{' . implode(';', $rules) . ';}</style>' . "\n";
}

function bandpromo_theme_migrate_from_config(string $root): void
{
    $defaultPath = bandpromo_theme_document_path($root, BANDPROMO_THEME_DEFAULT_ID);
    if (is_file($defaultPath)) {
        return;
    }

    $templatePath = bandpromo_theme_template_path($root, BANDPROMO_THEME_DEFAULT_ID);
    if (is_file($templatePath)) {
        $decoded = bandpromo_json_read_array_file($templatePath);
        $document = is_array($decoded)
            ? bandpromo_theme_normalize_document($decoded, BANDPROMO_THEME_DEFAULT_ID)
            : bandpromo_theme_default_document();
    } else {
        $document = bandpromo_theme_default_document();
    }

    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    if ($config !== []) {
        $document['assets'] = bandpromo_theme_normalize_assets(
            array_merge($document['assets'], bandpromo_theme_assets_from_config($config))
        );
    }

    bandpromo_json_write_file($defaultPath, $document);

    $registry = bandpromo_theme_load_registry($root);
    $hasDefault = false;
    foreach ($registry['themes'] as $entry) {
        if (($entry['id'] ?? '') === BANDPROMO_THEME_DEFAULT_ID) {
            $hasDefault = true;
            break;
        }
    }
    if (!$hasDefault) {
        $registry['themes'][] = [
            'id' => BANDPROMO_THEME_DEFAULT_ID,
            'title' => 'Setup Default',
            'system' => true,
            'locked' => true,
            'sort_order' => 10,
        ];
        bandpromo_theme_write_registry($root, $registry);
    }

    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config !== [] && trim((string) bandpromo_config_get_path($config, 'install.pointers.active_theme_id', '')) === '') {
        bandpromo_config_set_path($config, 'install.pointers.active_theme_id', BANDPROMO_THEME_DEFAULT_ID);
        bandpromo_json_write_file($configPath, $config);
    }
}

function bandpromo_theme_ensure_seeded(string $root): void
{
    bandpromo_theme_registry_ensure_dir($root);
    if (!is_file(bandpromo_theme_registry_path($root))) {
        bandpromo_theme_write_registry($root, bandpromo_theme_default_registry());
    }

    if (!is_file(bandpromo_theme_document_path($root, BANDPROMO_THEME_DEFAULT_ID))) {
        bandpromo_theme_migrate_from_config($root);
    }
}

function bandpromo_theme_slug_from_title(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'theme-copy';
    }

    return substr($slug, 0, 48);
}

function bandpromo_theme_propose_duplicate_title(string $sourceTitle): string
{
    $sourceTitle = trim($sourceTitle);
    if ($sourceTitle === '') {
        return 'Theme copy';
    }

    if (preg_match('/^(.+?)\s+copy(?:\s+(\d+))?$/iu', $sourceTitle, $matches) === 1) {
        $base = trim((string) ($matches[1] ?? ''));
        $number = isset($matches[2]) && $matches[2] !== '' ? ((int) $matches[2]) + 1 : 2;

        return $base . ' copy ' . $number;
    }

    return $sourceTitle . ' copy';
}

function bandpromo_theme_allocate_duplicate_id(string $root, string $title = ''): string
{
    bandpromo_theme_registry_ensure_dir($root);

    $existing = [];
    foreach (bandpromo_theme_registry_entries($root) as $entry) {
        $existing[(string) ($entry['id'] ?? '')] = true;
    }

    $baseId = bandpromo_theme_slug_from_title($title);
    if ($baseId === BANDPROMO_THEME_DEFAULT_ID || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $baseId)) {
        $baseId = 'theme-copy';
    }

    $id = $baseId;
    $suffix = 2;
    while (isset($existing[$id]) || is_file(bandpromo_theme_document_path($root, $id))) {
        $id = substr($baseId, 0, 44) . '-' . $suffix;
        $suffix++;
        if ($suffix > 999) {
            break;
        }
    }

    if (isset($existing[$id]) || is_file(bandpromo_theme_document_path($root, $id))) {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $fallback = bandpromo_theme_normalize_id('theme-copy-' . bin2hex(random_bytes(4)));
            if ($fallback !== '' && $fallback !== BANDPROMO_THEME_DEFAULT_ID
                && !isset($existing[$fallback]) && !is_file(bandpromo_theme_document_path($root, $fallback))) {
                return $fallback;
            }
        }

        throw new RuntimeException('Could not allocate a unique theme id.');
    }

    return $id;
}

function bandpromo_theme_duplicate(string $root, string $sourceId, string $newId, string $title = ''): array
{
    $sourceId = bandpromo_theme_normalize_id($sourceId);
    if ($sourceId === '') {
        throw new InvalidArgumentException('Invalid theme id.');
    }

    $source = bandpromo_theme_load_document($root, $sourceId);
    $duplicateTitle = trim($title) !== ''
        ? trim($title)
        : bandpromo_theme_propose_duplicate_title((string) ($source['title'] ?? $sourceId));

    if (trim($newId) === '') {
        $newId = bandpromo_theme_allocate_duplicate_id($root, $duplicateTitle);
    }

    $newId = bandpromo_theme_normalize_id($newId);
    if ($sourceId === '' || $newId === '') {
        throw new InvalidArgumentException('Invalid theme id.');
    }
    if ($newId === BANDPROMO_THEME_DEFAULT_ID) {
        throw new InvalidArgumentException('Reserved theme id.');
    }
    if (is_file(bandpromo_theme_document_path($root, $newId))) {
        throw new InvalidArgumentException('Theme id already exists.');
    }

    $duplicate = $source;
    $duplicate['id'] = $newId;
    $duplicate['title'] = $duplicateTitle;
    $duplicate['system'] = false;
    $duplicate['locked'] = false;

    bandpromo_json_write_file(bandpromo_theme_document_path($root, $newId), $duplicate);

    $registry = bandpromo_theme_load_registry($root);
    $registry['themes'][] = [
        'id' => $newId,
        'title' => $duplicate['title'],
        'system' => false,
        'locked' => false,
        'sort_order' => 50,
    ];
    bandpromo_theme_write_registry($root, $registry);

    return $duplicate;
}

function bandpromo_theme_update_title(string $root, string $themeId, string $title): array
{
    $themeId = bandpromo_theme_normalize_id($themeId);
    if ($themeId === '') {
        throw new InvalidArgumentException('Theme id is required.');
    }

    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Theme name is required.');
    }

    $document = bandpromo_theme_load_document($root, $themeId);
    if (!empty($document['locked'])) {
        throw new InvalidArgumentException('This theme is locked.');
    }

    $document['title'] = $title;
    bandpromo_theme_write_document($root, $document);

    $registry = bandpromo_theme_load_registry($root);
    foreach ($registry['themes'] as $index => $entry) {
        if (($entry['id'] ?? '') === $themeId) {
            $registry['themes'][$index]['title'] = $title;
            break;
        }
    }
    bandpromo_theme_write_registry($root, $registry);

    return bandpromo_theme_registry_entry($root, $themeId) ?? [];
}

function bandpromo_theme_delete(string $root, string $themeId): void
{
    $themeId = bandpromo_theme_normalize_id($themeId);
    if ($themeId === '' || $themeId === BANDPROMO_THEME_DEFAULT_ID) {
        throw new InvalidArgumentException('This theme cannot be deleted.');
    }

    $document = bandpromo_theme_load_document($root, $themeId);
    if (!empty($document['locked'])) {
        throw new InvalidArgumentException('This theme is locked.');
    }

    if (bandpromo_theme_active_id($root) === $themeId) {
        throw new InvalidArgumentException('Set another theme active before deleting this one.');
    }

    $registry = bandpromo_theme_load_registry($root);
    $before = count($registry['themes']);
    $registry['themes'] = array_values(array_filter(
        $registry['themes'],
        static fn(array $entry): bool => ($entry['id'] ?? '') !== $themeId
    ));
    if (count($registry['themes']) === $before) {
        throw new InvalidArgumentException('Unknown theme.');
    }

    bandpromo_theme_write_registry($root, $registry);

    $path = bandpromo_theme_document_path($root, $themeId);
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Could not delete theme document.');
    }
}
