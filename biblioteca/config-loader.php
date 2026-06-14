<?php
/**
 * Configuration Loader
 * Loads site configuration from web-config.json
 * 
 * Usage:
 *   require_once 'config-loader.php';
 *   echo $config['site']['name'];
 */

// Get root directory (biblioteca is one level deep in root)
$root_dir = dirname(dirname(__FILE__));
$config_file = $root_dir . '/web-config.json';

// Initialize empty config
$config = array();

// Load configuration
if (file_exists($config_file)) {
    try {
        $config_json = file_get_contents($config_file);
        $config = json_decode($config_json, true);
        
        if ($config === null) {
            error_log("ERROR: web-config.json is not valid JSON");
            $config = array();
        }
    } catch (Exception $e) {
        error_log("ERROR loading web-config.json: " . $e->getMessage());
        $config = array();
    }
} else {
    error_log("WARNING: web-config.json not found at " . $config_file);
    error_log("Run setup so templates are seeded into runtime files before use");
}

// Validate critical fields with defaults
if (!isset($config['site'])) {
    $config['site'] = array();
}

$config['site'] = array_merge(array(
    'name' => 'My Site',
    'short_name' => 'Site',
    'description' => 'A web application',
    'url' => 'https://example.com',
    'language' => 'en',
    'author' => 'Author Name'
), $config['site']);

if (!isset($config['social'])) {
    $config['social'] = array();
}

if (isset($config['content']) && is_array($config['content'])) {
    if (!isset($config['social']['categories']) && isset($config['content']['categories'])) {
        $config['social']['categories'] = $config['content']['categories'];
    }
    if (!isset($config['social']['keywords']) && isset($config['content']['keywords'])) {
        $config['social']['keywords'] = $config['content']['keywords'];
    }
}

$config['social'] = array_merge(array(
    'twitter' => '@YourHandle',
    'facebook' => 'YourFacebook',
    'instagram' => 'YourInstagram',
    'categories' => array('entertainment'),
    'keywords' => 'website',
    'share_image' => '/media/special/bandPromo_share.png',
    'share_image_width' => 1200,
    'share_image_height' => 630
), $config['social']);

if (!isset($config['support'])) {
    $config['support'] = array();
}

$config['support'] = array_merge(array(
    'enabled' => false,
    'mode' => 'link',
    'label' => 'Support',
    'url' => '',
    'kofi_page_id' => '',
    'button_background_color' => '#323842',
    'button_text_color' => '#ffffff'
), $config['support']);

if (!isset($config['player']) || !is_array($config['player'])) {
    $config['player'] = array();
}

$playerModuleDefaults = array(
    'playlist' => array('enabled' => true),
    'lyrics' => array('enabled' => true),
    'gallery' => array('enabled' => true),
    'pages' => array('enabled' => true),
);

if (!isset($config['player']['modules']) || !is_array($config['player']['modules'])) {
    $config['player']['modules'] = array();
}

foreach ($playerModuleDefaults as $moduleKey => $moduleDefaults) {
    $existing = is_array($config['player']['modules'][$moduleKey] ?? null)
        ? $config['player']['modules'][$moduleKey]
        : array();
    $config['player']['modules'][$moduleKey] = array_merge($moduleDefaults, $existing);
    if (in_array($moduleKey, array('playlist', 'lyrics'), true)) {
        $config['player']['modules'][$moduleKey]['enabled'] = true;
    }
}

if (!isset($config['player']['default_view']) || trim((string) $config['player']['default_view']) === '') {
    $config['player']['default_view'] = 'playlist';
}

if (!isset($config['admins'])) {
    $config['admins'] = [];
}

/**
 * Legacy fallback paths for the future scoped config model.
 */
function bandpromo_config_legacy_fallbacks(): array {
    return [
        'install.site.url' => ['site.url'],
        'install.site.language' => ['site.language'],
        'install.site.author' => ['site.author'],
        'release.identity.title' => ['site.name'],
        'release.identity.short_label' => ['site.short_name'],
        'release.identity.description' => ['site.description'],
        'install.brand.logo' => ['install.theme.logo', 'media.logo'],
        'install.brand.poster' => ['install.social.poster', 'release.social.share_image', 'social.share_image'],
        'install.brand.poster_width' => ['install.social.poster_width', 'release.social.share_image_width', 'social.share_image_width'],
        'install.brand.poster_height' => ['install.social.poster_height', 'release.social.share_image_height', 'social.share_image_height'],
        'release.brand.logo' => ['release.theme.logo_variant', 'release.theme.logo', 'install.theme.logo', 'media.logo'],
        'release.brand.poster' => ['release.social.poster', 'release.social.share_image', 'install.social.poster', 'social.share_image'],
        'release.brand.poster_width' => ['release.social.poster_width', 'release.social.share_image_width', 'install.social.poster_width', 'social.share_image_width'],
        'release.brand.poster_height' => ['release.social.poster_height', 'release.social.share_image_height', 'install.social.poster_height', 'social.share_image_height'],
        'install.social.twitter' => ['social.twitter'],
        'install.social.facebook' => ['social.facebook'],
        'install.social.instagram' => ['social.instagram'],
        'install.social.tiktok' => ['social.tiktok'],
        'install.social.youtube' => ['social.youtube'],
        'release.social.share_image' => ['social.share_image'],
        'release.social.share_image_width' => ['social.share_image_width'],
        'release.social.share_image_height' => ['social.share_image_height'],
        'release.social.keywords' => ['social.keywords'],
        'release.social.categories' => ['social.categories'],
        'install.theme.logo' => ['media.logo'],
        'install.theme.welcome_audio' => ['media.welcome_audio'],
        'install.theme.loggedin_audio' => ['media.loggedin_audio'],
        'release.theme.background_image' => ['media.background_image'],
        'release.theme.background_video' => ['media.background_video'],
        'release.theme.cover' => ['media.cover'],
    ];
}

function bandpromo_config_get_path(array $source, string $path, $missing = null) {
    $segments = explode('.', $path);
    $value = $source;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $missing;
        }
        $value = $value[$segment];
    }

    return $value;
}

function bandpromo_config_get_value(array $source, string $path, $default = null) {
    $missing = new stdClass();
    $value = bandpromo_config_get_path($source, $path, $missing);
    if ($value !== $missing) {
        return $value;
    }

    foreach (bandpromo_config_legacy_fallbacks()[$path] ?? [] as $legacyPath) {
        $legacyValue = bandpromo_config_get_path($source, $legacyPath, $missing);
        if ($legacyValue !== $missing) {
            return $legacyValue;
        }
    }

    return $default;
}

function bandpromo_config_get_nonempty_value(array $source, string $path, $default = null) {
    $value = bandpromo_config_get_value($source, $path, $default);
    if (is_string($value) && trim($value) === '') {
        return $default;
    }
    return $value;
}

function bandpromo_config_set_path(array &$target, string $path, $value): void {
    $segments = explode('.', $path);
    $cursor =& $target;
    $lastIndex = count($segments) - 1;

    foreach ($segments as $index => $segment) {
        if ($index === $lastIndex) {
            $cursor[$segment] = $value;
            return;
        }

        if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
            $cursor[$segment] = [];
        }

        $cursor =& $cursor[$segment];
    }
}

function bandpromo_load_runtime_config_raw(?string $configPath = null): array {
    $path = $configPath ?: dirname(__DIR__) . '/web-config.json';

    if (!file_exists($path)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($path) ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function bandpromo_sync_scoped_config_fields(array &$target, array $legacyRoots = ['site', 'social', 'media']): void {
    $legacyRoots = array_values(array_unique($legacyRoots));
    $syncMap = [
        'site' => [
            'site.url' => 'install.site.url',
            'site.language' => 'install.site.language',
            'site.author' => 'install.site.author',
            'site.name' => 'release.identity.title',
            'site.short_name' => 'release.identity.short_label',
            'site.description' => 'release.identity.description',
        ],
        'social' => [
            'social.twitter' => 'install.social.twitter',
            'social.facebook' => 'install.social.facebook',
            'social.instagram' => 'install.social.instagram',
            'social.tiktok' => 'install.social.tiktok',
            'social.youtube' => 'install.social.youtube',
            'social.share_image' => 'release.social.share_image',
            'social.share_image_width' => 'release.social.share_image_width',
            'social.share_image_height' => 'release.social.share_image_height',
            'social.keywords' => 'release.social.keywords',
            'social.categories' => 'release.social.categories',
        ],
        'media' => [
            'media.logo' => 'install.theme.logo',
            'media.welcome_audio' => 'install.theme.welcome_audio',
            'media.loggedin_audio' => 'install.theme.loggedin_audio',
            'media.background_image' => 'release.theme.background_image',
            'media.background_video' => 'release.theme.background_video',
            'media.cover' => 'release.theme.cover',
        ],
    ];

    foreach ($legacyRoots as $legacyRoot) {
        foreach ($syncMap[$legacyRoot] ?? [] as $legacyPath => $scopedPath) {
            $missing = new stdClass();
            $legacyValue = bandpromo_config_get_path($target, $legacyPath, $missing);
            if ($legacyValue !== $missing) {
                bandpromo_config_set_path($target, $scopedPath, $legacyValue);
            }
        }
    }
}

if (!function_exists('get_config')) {
    /**
     * Get config value with dot notation support and scoped-key fallbacks.
     */
    function get_config($key, $default = null) {
        global $config;
        return bandpromo_config_get_value($config, (string) $key, $default);
    }
}

if (!function_exists('get_config_nonempty')) {
    function get_config_nonempty($key, $default = null) {
        global $config;
        return bandpromo_config_get_nonempty_value($config, (string) $key, $default);
    }
}

/**
 * Get the currently logged-in user's role.
 */
function current_user_role(): string {
    $username = trim((string) ($_SESSION['username'] ?? ''));
    if ($username === '') return 'user';
    require_once __DIR__ . '/auth.php';
    return getUserRole($username);
}

/**
 * Check if the currently logged-in user may access the admin panel.
 */
function can_access_admin_panel(): bool {
    $username = trim((string) ($_SESSION['username'] ?? ''));
    if ($username === '') return false;
    require_once __DIR__ . '/auth.php';
    return isAdminUser($username);
}

/**
 * Check if the currently logged-in user is a developer.
 */
function is_developer(): bool {
    $username = trim((string) ($_SESSION['username'] ?? ''));
    if ($username === '') return false;
    require_once __DIR__ . '/auth.php';
    return isDeveloperUser($username);
}

/**
 * Check if the currently logged-in user is an admin.
 * Backwards-compatible alias for privileged admin-panel access.
 */
function is_admin(): bool {
    return can_access_admin_panel();
}
