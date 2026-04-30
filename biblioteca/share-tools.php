<?php
/**
 * Share Tools - Open Graph & Twitter Card Meta Tags
 * Generates social sharing meta tags from configuration
 * 
 * Usage in HTML head:
 *   <?php include 'biblioteca/share-tools.php'; ?>
 *   <?php echo generate_og_tags($title, $description); ?>
 *   <?php echo generate_twitter_tags($title, $description); ?>
 */

require_once 'config-loader.php';

/**
 * Generate Open Graph meta tags
 */
function generate_og_tags($title = '', $description = '', $image = '', $url = '', $type = 'website') {
    global $config;
    
    $site_name = $config['site']['name'] ?? 'My Site';
    $og_image = $image ?: ($config['social']['share_image'] ?? '/media/special/bandPromo_share.png');
    $og_url = $url ?: ($config['site']['url'] ?? '');
    $og_description = $description ?: ($config['site']['description'] ?? '');
    $og_title = $title ?: $site_name;
    
    // Convert relative image URL to absolute
    if (strpos($og_image, 'http') !== 0) {
        $og_image = rtrim($og_url ?: $config['site']['url'], '/') . '/' . ltrim($og_image, '/');
    }
    
    $tags = array();
    $tags[] = sprintf('    <meta property="og:title" content="%s">', htmlspecialchars($og_title));
    $tags[] = sprintf('    <meta property="og:description" content="%s">', htmlspecialchars($og_description));
    $tags[] = sprintf('    <meta property="og:image" content="%s">', htmlspecialchars($og_image));
    $tags[] = sprintf('    <meta property="og:image:width" content="%d">', $config['social']['share_image_width'] ?? 1200);
    $tags[] = sprintf('    <meta property="og:image:height" content="%d">', $config['social']['share_image_height'] ?? 630);
    
    if ($og_url) {
        $tags[] = sprintf('    <meta property="og:url" content="%s">', htmlspecialchars($og_url));
    }
    
    $tags[] = sprintf('    <meta property="og:type" content="%s">', htmlspecialchars($type));
    $tags[] = sprintf('    <meta property="og:site_name" content="%s">', htmlspecialchars($site_name));
    
    return implode("\n", $tags);
}

/**
 * Generate Twitter Card meta tags
 */
function generate_twitter_tags($title = '', $description = '', $image = '', $type = 'summary_large_image') {
    global $config;
    
    $site_name = $config['site']['name'] ?? 'My Site';
    $twitter_handle = $config['social']['twitter'] ?? '';
    $twitter_image = $image ?: ($config['social']['share_image'] ?? '/media/special/bandPromo_share.png');
    $twitter_description = $description ?: ($config['site']['description'] ?? '');
    $twitter_title = $title ?: $site_name;
    
    // Convert relative image URL to absolute
    if (strpos($twitter_image, 'http') !== 0) {
        $twitter_image = rtrim($config['site']['url'], '/') . '/' . ltrim($twitter_image, '/');
    }
    
    $tags = array();
    $tags[] = sprintf('    <meta name="twitter:card" content="%s">', htmlspecialchars($type));
    $tags[] = sprintf('    <meta name="twitter:title" content="%s">', htmlspecialchars($twitter_title));
    $tags[] = sprintf('    <meta name="twitter:description" content="%s">', htmlspecialchars($twitter_description));
    $tags[] = sprintf('    <meta name="twitter:image" content="%s">', htmlspecialchars($twitter_image));
    
    if ($twitter_handle) {
        $tags[] = sprintf('    <meta name="twitter:creator" content="%s">', htmlspecialchars($twitter_handle));
    }
    
    return implode("\n", $tags);
}

/**
 * Generate standard meta tags (charset, viewport, etc)
 */
function generate_standard_meta_tags() {
    global $config;
    
    // Handle keywords - can be string or array
    $keywords = $config['content']['keywords'] ?? 'website';
    if (is_array($keywords)) {
        $keywords = implode(', ', $keywords);
    }
    
    $tags = array();
    $tags[] = '    <meta charset="UTF-8">';
    $tags[] = '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $tags[] = sprintf('    <meta name="description" content="%s">', htmlspecialchars($config['site']['description'] ?? 'A web application'));
    $tags[] = sprintf('    <meta name="keywords" content="%s">', htmlspecialchars($keywords));
    $tags[] = sprintf('    <meta name="author" content="%s">', htmlspecialchars($config['site']['author'] ?? 'Author'));
    $tags[] = sprintf('    <meta name="theme-color" content="%s">', htmlspecialchars($config['branding']['theme_color'] ?? '#121212'));
    $tags[] = '    <meta name="format-detection" content="telephone=no">';
    
    return implode("\n", $tags);
}

/**
 * Get config value with dot notation support
 * Example: get_config('social.twitter')
 */
function get_config($key, $default = null) {
    global $config;
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}
