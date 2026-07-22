<?php
declare(strict_types=1);

require_once __DIR__ . '/json-file-helpers.php';
require_once __DIR__ . '/config-loader.php';

const BANDPROMO_THEME_REGISTRY_VERSION = 1;
/** Canonical default brand id (legacy alias: setup-default). */
const BANDPROMO_BRAND_DEFAULT_ID = 'bandpromo-default';
const BANDPROMO_THEME_DEFAULT_ID = 'setup-default';

function bandpromo_brand_canonical_id(string $id): string
{
    $id = bandpromo_theme_normalize_id($id);
    if ($id === BANDPROMO_THEME_DEFAULT_ID) {
        return BANDPROMO_BRAND_DEFAULT_ID;
    }

    return $id;
}

function bandpromo_brand_normalize_pool_filter(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value === 'all') {
        return 'all';
    }
    if ($value === 'orphans') {
        return 'orphans';
    }

    return bandpromo_brand_canonical_id($value);
}

function bandpromo_brand_legacy_theme_id(string $id): string
{
    $id = bandpromo_brand_canonical_id($id);

    return $id === BANDPROMO_BRAND_DEFAULT_ID ? BANDPROMO_THEME_DEFAULT_ID : $id;
}

function bandpromo_theme_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'brands';
}

function bandpromo_theme_legacy_storage_root(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'themes';
}

function bandpromo_theme_registry_path(string $root): string
{
    return bandpromo_theme_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_theme_document_path(string $root, string $themeId): string
{
    $themeId = bandpromo_brand_canonical_id($themeId);

    return bandpromo_theme_storage_root($root) . DIRECTORY_SEPARATOR . $themeId . '.json';
}

function bandpromo_theme_legacy_document_path(string $root, string $themeId): string
{
    return bandpromo_theme_legacy_storage_root($root) . DIRECTORY_SEPARATOR . bandpromo_theme_normalize_id($themeId) . '.json';
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
        throw new RuntimeException('Could not create data/brands directory.');
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

function bandpromo_theme_derived_alpha_css_variables(): array
{
    $variables = [];
    foreach ([5, 8, 10, 12, 15, 20, 24, 25, 28, 30, 35, 40, 45, 50] as $percent) {
        $suffix = str_pad((string) $percent, 2, '0', STR_PAD_LEFT);
        $variables['--primary-a' . $suffix] = sprintf(
            'color-mix(in srgb, var(--primary-color) %d%%, transparent)',
            $percent
        );
    }
    $variables['--secondary-a20'] = 'color-mix(in srgb, var(--secondary-color) 20%, transparent)';
    $variables['--secondary-a30'] = 'color-mix(in srgb, var(--secondary-color) 30%, transparent)';

    return $variables;
}

function bandpromo_brand_normalize_narrative_field(mixed $value, int $maxLength): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }

    return $text;
}

function bandpromo_theme_default_document(): array
{
    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'id' => BANDPROMO_BRAND_DEFAULT_ID,
        'title' => 'bandPromo Default',
        'system' => true,
        'locked' => true,
        'release_id' => 'bandpromo-demo',
        'mood' => 'Clean demo identity for first-run installs',
        'keywords' => ['demo', 'electronic', 'modern'],
        'tone_notes' => 'Neutral platform defaults; duplicate and customize as release identity.',
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
            'welcome_audio' => '/media/sfx/original/bandPromo_welcome.flac',
            'loggedin_audio' => '/media/sfx/original/bandPromo_loggedin.flac',
        ],
    ];
}

function bandpromo_theme_default_registry(): array
{
    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'brands' => [
            [
                'id' => BANDPROMO_BRAND_DEFAULT_ID,
                'title' => 'bandPromo Default',
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
    $requiredKeys = ['logo' => true, 'poster' => true];
    $normalized = [];
    foreach ($defaults as $key => $defaultValue) {
        $hasKey = array_key_exists($key, $assets);
        $value = $hasKey ? trim((string) $assets[$key]) : trim((string) $defaultValue);
        if ($value !== '' && ($value[0] === '/' || preg_match('/^https?:\/\//i', $value) === 1)) {
            $normalized[$key] = $value;
            continue;
        }
        if (isset($requiredKeys[$key])) {
            $normalized[$key] = (string) $defaultValue;
            continue;
        }
        // Optional shell slots (backgrounds/audio) may stay cleared.
        $normalized[$key] = '';
    }

    return $normalized;
}

/**
 * Resolve a web media path to an absolute install file under /media/.
 */
function bandpromo_theme_resolve_media_absolute_path(string $root, string $webPath): ?string
{
    $webPath = trim(str_replace('\\', '/', $webPath));
    if ($webPath === '' || preg_match('#^https?://#i', $webPath) === 1) {
        return null;
    }
    if ($webPath[0] !== '/') {
        $webPath = '/' . $webPath;
    }
    if (strpos($webPath, '/media/') !== 0) {
        return null;
    }
    if (strpos($webPath, '..') !== false) {
        return null;
    }

    $absolute = rtrim($root, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $webPath);
    if (!is_file($absolute)) {
        return null;
    }

    return $absolute;
}

/**
 * Copy one shell media file into the owning brand's library.
 * Visual slots clone into Brand assets (`media/special/`).
 * Audio slots clone into Sound effects (`media/sfx/original/`).
 * Returns the new web path, or the original path when copy is not possible.
 */
function bandpromo_theme_clone_asset_file(string $root, string $brandId, string $assetKey, string $sourcePath): string
{
    $sourcePath = trim($sourcePath);
    if ($sourcePath === '') {
        return '';
    }

    $absolute = bandpromo_theme_resolve_media_absolute_path($root, $sourcePath);
    if ($absolute === null) {
        return $sourcePath;
    }

    $ext = strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION));
    if ($ext === '') {
        return $sourcePath;
    }

    $isAudioSlot = in_array($assetKey, ['welcome_audio', 'loggedin_audio'], true)
        || in_array($ext, ['flac', 'mp3', 'wav', 'ogg', 'm4a'], true);

    require_once __DIR__ . '/media-library-state.php';

    if ($isAudioSlot) {
        require_once __DIR__ . '/sfx-helpers.php';
        bandpromo_sfx_ensure_dir($root);
        $destDir = bandpromo_sfx_original_dir($root);
        $indexTarget = 'sfx';
        $webPrefix = '/media/sfx/original/';
    } else {
        $destDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'special';
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            throw new RuntimeException('Could not create media/special for brand asset clones.');
        }
        $indexTarget = 'special';
        $webPrefix = '/media/special/';
    }

    $safeBrand = preg_replace('/[^a-z0-9-]+/', '-', strtolower($brandId)) ?: 'brand';
    $safeKey = preg_replace('/[^a-z0-9_]+/', '_', strtolower($assetKey)) ?: 'asset';
    $base = $safeBrand . '_' . $safeKey;
    $destName = $base . '.' . $ext;
    $suffix = 2;
    while (is_file($destDir . DIRECTORY_SEPARATOR . $destName)) {
        $destName = $base . '-' . $suffix . '.' . $ext;
        $suffix++;
        if ($suffix > 100) {
            throw new RuntimeException('Could not allocate a unique brand asset filename.');
        }
    }

    $destAbsolute = $destDir . DIRECTORY_SEPARATOR . $destName;
    if (!copy($absolute, $destAbsolute)) {
        throw new RuntimeException('Could not clone brand asset: ' . $assetKey);
    }

    bandpromo_media_files_index_sync_file($root, $indexTarget, $destName);
    if ($indexTarget === 'sfx') {
        try {
            bandpromo_asset_register_sfx($root, $destName);
        } catch (Throwable $throwable) {
            // Registry optional for clone success.
        }
    }

    return $webPrefix . $destName;
}

/**
 * Physically clone shell media into Brand assets for a duplicated brand.
 * Operators can then delete/replace those copies without touching the source brand.
 */
function bandpromo_theme_clone_assets_for_brand(string $root, array $assets, string $brandId): array
{
    $brandId = bandpromo_brand_canonical_id($brandId);
    if ($brandId === '') {
        throw new InvalidArgumentException('Brand id is required to clone assets.');
    }

    $defaults = bandpromo_theme_default_document()['assets'];
    $cloned = [];
    foreach ($defaults as $key => $_defaultValue) {
        $cloned[$key] = bandpromo_theme_clone_asset_file(
            $root,
            $brandId,
            $key,
            trim((string) ($assets[$key] ?? ''))
        );
    }

    return bandpromo_theme_normalize_assets($cloned);
}

function bandpromo_theme_normalize_document(array $input, ?string $expectedId = null): array
{
    $id = bandpromo_brand_canonical_id((string) ($input['id'] ?? $expectedId ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $id)) {
        throw new InvalidArgumentException('Invalid brand id.');
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = ucfirst(str_replace('-', ' ', $id));
    }

    $locked = !empty($input['locked']);
    $system = !empty($input['system']);
    if ($id === BANDPROMO_BRAND_DEFAULT_ID) {
        $locked = true;
        $system = true;
    }

    $releaseId = trim((string) ($input['release_id'] ?? ''));
    if ($releaseId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId)) {
        $releaseId = '';
    }
    if ($releaseId === '' && $id === BANDPROMO_BRAND_DEFAULT_ID) {
        $releaseId = 'bandpromo-demo';
    }

    return [
        'version' => BANDPROMO_THEME_REGISTRY_VERSION,
        'id' => $id,
        'title' => $title,
        'system' => $system,
        'locked' => $locked,
        'release_id' => $releaseId,
        'mood' => bandpromo_brand_normalize_narrative_field($input['mood'] ?? '', 500),
        'keywords' => array_values(array_filter(array_map(
            static fn(mixed $item): string => bandpromo_brand_normalize_narrative_field($item, 80),
            is_array($input['keywords'] ?? null)
                ? $input['keywords']
                : (preg_split('/\s*,\s*/', (string) ($input['keywords'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
        ))),
        'tone_notes' => bandpromo_brand_normalize_narrative_field($input['tone_notes'] ?? '', 2000),
        'tokens' => bandpromo_theme_normalize_tokens(is_array($input['tokens'] ?? null) ? $input['tokens'] : []),
        'assets' => bandpromo_theme_normalize_assets(is_array($input['assets'] ?? null) ? $input['assets'] : []),
    ];
}

function bandpromo_theme_write_registry(string $root, array $registry): void
{
    bandpromo_theme_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_theme_registry_path($root), $registry)) {
        throw new RuntimeException('Could not write data/brands/registry.json');
    }
}

function bandpromo_theme_registry_list_key(array $registry): string
{
    if (isset($registry['brands']) && is_array($registry['brands'])) {
        return 'brands';
    }

    return 'themes';
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

    $listKey = bandpromo_theme_registry_list_key($decoded);
    if (!isset($decoded[$listKey]) || !is_array($decoded[$listKey])) {
        $decoded[$listKey] = [];
    }
    if ($listKey === 'themes' && !isset($decoded['brands'])) {
        $decoded['brands'] = $decoded['themes'];
        unset($decoded['themes']);
    }
    foreach ($decoded['brands'] as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $decoded['brands'][$index]['id'] = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
    }
    if ($listKey === 'themes') {
        bandpromo_theme_write_registry($root, $decoded);
    }

    return $decoded;
}

/**
 * @param array{allow_locked?: bool} $options
 */
function bandpromo_theme_write_document(string $root, array $document, array $options = []): void
{
    $document = bandpromo_theme_normalize_document($document, (string) ($document['id'] ?? ''));
    if (!empty($document['locked']) && empty($options['allow_locked'])) {
        throw new RuntimeException('Brand is locked and cannot be edited.');
    }

    bandpromo_theme_registry_ensure_dir($root);
    if (!bandpromo_json_write_file(bandpromo_theme_document_path($root, $document['id']), $document)) {
        throw new RuntimeException('Could not write theme document.');
    }
}

function bandpromo_theme_load_document(string $root, string $themeId): array
{
    $themeId = bandpromo_brand_canonical_id($themeId);
    if ($themeId === '') {
        throw new InvalidArgumentException('Invalid brand id.');
    }

    $path = bandpromo_theme_document_path($root, $themeId);
    if (!is_file($path)) {
        throw new RuntimeException('Missing brand document: data/brands/' . $themeId . '.json');
    }

    $decoded = bandpromo_json_read_array_file($path);
    if ($decoded === null) {
        throw new RuntimeException('Invalid brand document: data/brands/' . $themeId . '.json');
    }

    return bandpromo_theme_normalize_document($decoded, $themeId);
}

function bandpromo_theme_registry_entries(string $root): array
{
    $registry = bandpromo_theme_load_registry($root);
    $entries = $registry['brands'] ?? [];
    foreach ($entries as $index => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $canonical = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
        $entries[$index]['id'] = bandpromo_brand_legacy_theme_id($canonical);
        if ($canonical === BANDPROMO_BRAND_DEFAULT_ID) {
            $entries[$index]['title'] = (string) ($entry['title'] ?? 'bandPromo Default');
        }
    }

    return $entries;
}

function bandpromo_theme_registry_entry(string $root, string $themeId): ?array
{
    $canonical = bandpromo_brand_canonical_id($themeId);
    foreach (bandpromo_theme_load_registry($root)['brands'] ?? [] as $entry) {
        if (bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) === $canonical) {
            $entry['id'] = bandpromo_brand_legacy_theme_id($canonical);

            return $entry;
        }
    }

    return null;
}

function bandpromo_theme_assets_from_config(array $config): array
{
    $poster = (string) bandpromo_config_get_nonempty_value($config, 'release.brand.poster', '');
    if ($poster === '') {
        $poster = (string) bandpromo_config_get_nonempty_value($config, 'release.theme.cover', '');
    }
    if ($poster === '') {
        $poster = (string) bandpromo_config_get_nonempty_value($config, 'media.cover', '/media/special/bandPromo_cover.png');
    }

    return [
        'logo' => (string) bandpromo_config_get_nonempty_value($config, 'install.brand.logo', '/media/special/bandPromo_logo.png'),
        'poster' => $poster,
        'background_image' => (string) bandpromo_config_get_nonempty_value($config, 'release.theme.background_image', '/media/special/bandPromo_background.png'),
        'background_video' => (string) bandpromo_config_get_nonempty_value($config, 'release.theme.background_video', '/media/special/bandPromo_background.mp4'),
        'welcome_audio' => (string) bandpromo_config_get_nonempty_value($config, 'install.theme.welcome_audio', '/media/sfx/original/bandPromo_welcome.flac'),
        'loggedin_audio' => (string) bandpromo_config_get_nonempty_value($config, 'install.theme.loggedin_audio', '/media/sfx/original/bandPromo_loggedin.flac'),
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
        'poster' => [
            'release.brand.poster',
            'release.social.share_image',
            'social.share_image',
            'release.theme.cover',
            'media.cover',
        ],
        'background_image' => ['release.theme.background_image', 'media.background_image'],
        'background_video' => ['release.theme.background_video', 'media.background_video'],
        'welcome_audio' => ['install.theme.welcome_audio', 'media.welcome_audio'],
        'loggedin_audio' => ['install.theme.loggedin_audio', 'media.loggedin_audio'],
    ];

    foreach ($map as $assetKey => $paths) {
        $value = trim((string) ($assets[$assetKey] ?? ''));
        foreach ($paths as $path) {
            bandpromo_config_set_path($config, $path, $value);
        }
    }

    bandpromo_json_write_file($configPath, $config);
}

function bandpromo_theme_active_id(string $root): string
{
    $config = bandpromo_load_runtime_config_raw($root . '/web-config.json');
    $brandId = bandpromo_brand_canonical_id((string) bandpromo_config_get_path($config, 'install.pointers.active_brand_id', ''));
    if ($brandId === '') {
        $brandId = bandpromo_brand_canonical_id((string) bandpromo_config_get_path($config, 'install.pointers.active_theme_id', ''));
    }
    if ($brandId !== '' && is_file(bandpromo_theme_document_path($root, $brandId))) {
        return bandpromo_brand_legacy_theme_id($brandId);
    }

    return BANDPROMO_THEME_DEFAULT_ID;
}

function bandpromo_brand_active_id(string $root): string
{
    return bandpromo_brand_canonical_id(bandpromo_theme_active_id($root));
}

function bandpromo_theme_set_active_id(string $root, string $themeId): void
{
    $themeId = bandpromo_brand_canonical_id($themeId);
    if ($themeId === '' || !is_file(bandpromo_theme_document_path($root, $themeId))) {
        throw new InvalidArgumentException('Unknown brand.');
    }

    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config === []) {
        throw new RuntimeException('Missing web-config.json');
    }

    $legacyId = bandpromo_brand_legacy_theme_id($themeId);
    bandpromo_config_set_path($config, 'install.pointers.active_brand_id', $themeId);
    bandpromo_config_set_path($config, 'install.pointers.active_theme_id', $legacyId);
    if (!bandpromo_json_write_file($configPath, $config)) {
        throw new RuntimeException('Could not update active brand pointer.');
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

    foreach (bandpromo_theme_derived_alpha_css_variables() as $cssVar => $value) {
        $rules[] = $cssVar . ':' . $value;
    }

    if ($rules === []) {
        return '';
    }

    return '<style id="bandpromo-theme-vars">:root{' . implode(';', $rules) . ';}</style>' . "\n";
}

function bandpromo_theme_migrate_from_themes(string $root): void
{
    $brandsRoot = bandpromo_theme_storage_root($root);
    $legacyRoot = bandpromo_theme_legacy_storage_root($root);
    if (is_file($brandsRoot . DIRECTORY_SEPARATOR . 'registry.json')) {
        return;
    }
    if (!is_dir($legacyRoot)) {
        return;
    }

    bandpromo_theme_registry_ensure_dir($root);
    $legacyRegistryPath = $legacyRoot . DIRECTORY_SEPARATOR . 'registry.json';
    if (is_file($legacyRegistryPath)) {
        $legacyRegistry = bandpromo_json_read_array_file($legacyRegistryPath) ?? [];
        $entries = is_array($legacyRegistry['themes'] ?? null) ? $legacyRegistry['themes'] : [];
        $brands = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entry['id'] = bandpromo_brand_canonical_id((string) ($entry['id'] ?? ''));
            if ($entry['id'] === BANDPROMO_BRAND_DEFAULT_ID) {
                $entry['title'] = 'bandPromo Default';
            }
            $brands[] = $entry;
        }
        if ($brands === []) {
            $brands = bandpromo_theme_default_registry()['brands'];
        }
        bandpromo_theme_write_registry($root, [
            'version' => BANDPROMO_THEME_REGISTRY_VERSION,
            'brands' => $brands,
        ]);
    }

    foreach (glob($legacyRoot . DIRECTORY_SEPARATOR . '*.json') ?: [] as $legacyDocPath) {
        if (basename($legacyDocPath) === 'registry.json') {
            continue;
        }
        $stem = pathinfo($legacyDocPath, PATHINFO_FILENAME);
        $canonical = bandpromo_brand_canonical_id($stem);
        $target = bandpromo_theme_document_path($root, $canonical);
        if (is_file($target)) {
            continue;
        }
        $decoded = bandpromo_json_read_array_file($legacyDocPath);
        if (!is_array($decoded)) {
            continue;
        }
        $decoded['id'] = $canonical;
        bandpromo_json_write_file($target, bandpromo_theme_normalize_document($decoded, $canonical));
    }
}

function bandpromo_theme_migrate_from_config(string $root): void
{
    $defaultPath = bandpromo_theme_document_path($root, BANDPROMO_BRAND_DEFAULT_ID);
    if (is_file($defaultPath)) {
        return;
    }

    $templatePath = bandpromo_theme_template_path($root, BANDPROMO_THEME_DEFAULT_ID);
    if (!is_file($templatePath)) {
        $templatePath = bandpromo_theme_template_path($root, BANDPROMO_BRAND_DEFAULT_ID);
    }
    if (is_file($templatePath)) {
        $decoded = bandpromo_json_read_array_file($templatePath);
        $document = is_array($decoded)
            ? bandpromo_theme_normalize_document($decoded, BANDPROMO_BRAND_DEFAULT_ID)
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
    foreach ($registry['brands'] ?? [] as $entry) {
        if (bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) === BANDPROMO_BRAND_DEFAULT_ID) {
            $hasDefault = true;
            break;
        }
    }
    if (!$hasDefault) {
        $registry['brands'][] = [
            'id' => BANDPROMO_BRAND_DEFAULT_ID,
            'title' => 'bandPromo Default',
            'system' => true,
            'locked' => true,
            'sort_order' => 10,
        ];
        bandpromo_theme_write_registry($root, $registry);
    }

    $configPath = $root . '/web-config.json';
    $config = bandpromo_load_runtime_config_raw($configPath);
    if ($config !== []) {
        $changed = false;
        if (trim((string) bandpromo_config_get_path($config, 'install.pointers.active_brand_id', '')) === '') {
            bandpromo_config_set_path($config, 'install.pointers.active_brand_id', BANDPROMO_BRAND_DEFAULT_ID);
            $changed = true;
        }
        if (trim((string) bandpromo_config_get_path($config, 'install.pointers.active_theme_id', '')) === '') {
            bandpromo_config_set_path($config, 'install.pointers.active_theme_id', BANDPROMO_THEME_DEFAULT_ID);
            $changed = true;
        }
        if ($changed) {
            bandpromo_json_write_file($configPath, $config);
        }
    }
}

function bandpromo_theme_ensure_seeded(string $root): void
{
    bandpromo_theme_migrate_from_themes($root);
    bandpromo_theme_registry_ensure_dir($root);
    if (!is_file(bandpromo_theme_registry_path($root))) {
        bandpromo_theme_write_registry($root, bandpromo_theme_default_registry());
    }

    if (!is_file(bandpromo_theme_document_path($root, BANDPROMO_BRAND_DEFAULT_ID))) {
        bandpromo_theme_migrate_from_config($root);
    }
}

function bandpromo_theme_slug_from_title(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'brand-copy';
    }

    return substr($slug, 0, 48);
}

function bandpromo_theme_propose_duplicate_title(string $sourceTitle): string
{
    $sourceTitle = trim($sourceTitle);
    if ($sourceTitle === '') {
        return 'Brand copy';
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
    if ($baseId === BANDPROMO_BRAND_DEFAULT_ID || $baseId === BANDPROMO_THEME_DEFAULT_ID || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $baseId)) {
        $baseId = 'brand-copy';
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
            $fallback = bandpromo_theme_normalize_id('brand-copy-' . bin2hex(random_bytes(4)));
            if ($fallback !== '' && $fallback !== BANDPROMO_BRAND_DEFAULT_ID && $fallback !== BANDPROMO_THEME_DEFAULT_ID
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
    $sourceId = bandpromo_brand_canonical_id($sourceId);
    if ($sourceId === '') {
        throw new InvalidArgumentException('Invalid brand id.');
    }

    $source = bandpromo_theme_load_document($root, $sourceId);
    $duplicateTitle = trim($title) !== ''
        ? trim($title)
        : bandpromo_theme_propose_duplicate_title((string) ($source['title'] ?? $sourceId));

    if (trim($newId) === '') {
        $newId = bandpromo_theme_allocate_duplicate_id($root, $duplicateTitle);
    }

    $newId = bandpromo_brand_canonical_id($newId);
    if ($sourceId === '' || $newId === '') {
        throw new InvalidArgumentException('Invalid brand id.');
    }
    if ($newId === BANDPROMO_BRAND_DEFAULT_ID) {
        throw new InvalidArgumentException('Reserved brand id.');
    }
    if (is_file(bandpromo_theme_document_path($root, $newId))) {
        throw new InvalidArgumentException('Brand id already exists.');
    }

    $duplicate = bandpromo_theme_normalize_document([
        'id' => $newId,
        'title' => $duplicateTitle,
        'system' => false,
        'locked' => false,
        'mood' => $source['mood'] ?? '',
        'keywords' => $source['keywords'] ?? [],
        'tone_notes' => $source['tone_notes'] ?? '',
        'tokens' => is_array($source['tokens'] ?? null) ? $source['tokens'] : [],
        'assets' => [],
    ], $newId);

    // Always clone shell media files into Brand assets owned by the new brand
    // (setup "Your own brand" and manual duplicate). Operators may delete those copies.
    $duplicate['assets'] = bandpromo_theme_clone_assets_for_brand(
        $root,
        is_array($source['assets'] ?? null) ? $source['assets'] : [],
        $newId
    );

    bandpromo_json_write_file(bandpromo_theme_document_path($root, $newId), $duplicate);

    $registry = bandpromo_theme_load_registry($root);
    $registry['brands'][] = [
        'id' => $newId,
        'title' => $duplicate['title'],
        'system' => false,
        'locked' => false,
        'sort_order' => 50,
    ];
    bandpromo_theme_write_registry($root, $registry);

    $duplicate['id'] = bandpromo_brand_legacy_theme_id($newId);

    return $duplicate;
}

function bandpromo_theme_update_title(string $root, string $themeId, string $title): array
{
    $themeId = bandpromo_brand_canonical_id($themeId);
    if ($themeId === '') {
        throw new InvalidArgumentException('Brand id is required.');
    }

    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Brand name is required.');
    }

    $document = bandpromo_theme_load_document($root, $themeId);
    if (!empty($document['locked'])) {
        throw new InvalidArgumentException('This brand is locked.');
    }

    $document['title'] = $title;
    bandpromo_theme_write_document($root, $document);

    $registry = bandpromo_theme_load_registry($root);
    foreach ($registry['brands'] as $index => $entry) {
        if (bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) === $themeId) {
            $registry['brands'][$index]['title'] = $title;
            break;
        }
    }
    bandpromo_theme_write_registry($root, $registry);

    return bandpromo_theme_registry_entry($root, $themeId) ?? [];
}

function bandpromo_theme_delete(string $root, string $themeId): void
{
    $themeId = bandpromo_brand_canonical_id($themeId);
    if ($themeId === '' || $themeId === BANDPROMO_BRAND_DEFAULT_ID) {
        throw new InvalidArgumentException('This brand cannot be deleted.');
    }

    $document = bandpromo_theme_load_document($root, $themeId);
    if (!empty($document['locked'])) {
        throw new InvalidArgumentException('This brand is locked.');
    }

    if (bandpromo_brand_active_id($root) === $themeId) {
        throw new InvalidArgumentException('Set another brand active before deleting this one.');
    }

    $registry = bandpromo_theme_load_registry($root);
    $before = count($registry['brands'] ?? []);
    $registry['brands'] = array_values(array_filter(
        $registry['brands'] ?? [],
        static fn(array $entry): bool => bandpromo_brand_canonical_id((string) ($entry['id'] ?? '')) !== $themeId
    ));
    if (count($registry['brands']) === $before) {
        throw new InvalidArgumentException('Unknown brand.');
    }

    bandpromo_theme_write_registry($root, $registry);

    $path = bandpromo_theme_document_path($root, $themeId);
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Could not delete brand document.');
    }
}
