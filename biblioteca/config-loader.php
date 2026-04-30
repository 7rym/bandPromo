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

$config['social'] = array_merge(array(
    'twitter' => '@YourHandle',
    'facebook' => 'YourFacebook',
    'instagram' => 'YourInstagram',
    'share_image' => '/media/special/bandPromo_share.png',
    'share_image_width' => 1200,
    'share_image_height' => 630
), $config['social']);

if (!isset($config['content'])) {
    $config['content'] = array();
}

$config['content'] = array_merge(array(
    'categories' => array('entertainment'),
    'keywords' => 'website'
), $config['content']);

if (!isset($config['build'])) {
    $config['build'] = array();
}

$config['build'] = array_merge(array(
    'speedtest_threshold_mbps' => 20
), $config['build']);

if (!isset($config['admins'])) {
    $config['admins'] = [];
}

/**
 * Check if the currently logged-in user is an admin.
 * Delegates to auth.php which reads roles from the terces file.
 */
function is_admin(): bool {
    $username = $_SESSION['username'] ?? '';
    if (empty($username)) return false;
    require_once __DIR__ . '/auth.php';
    return isAdminUser($username);
}
