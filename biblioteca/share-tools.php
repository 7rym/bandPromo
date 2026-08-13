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
require_once __DIR__ . '/player-markdown.php';

if (!function_exists('get_config')) {
    function get_config($key, $default = null) {
        global $config;
        return bandpromo_config_get_value($config, (string) $key, $default);
    }
}

/**
 * Generate Open Graph meta tags
 */
function generate_og_tags($title = '', $description = '', $image = '', $url = '', $type = 'website') {
    $site_name = get_config('release.identity.title', 'My Site');
    $og_image = $image ?: get_config('release.brand.poster', '');
    if ($og_image === '') {
        require_once __DIR__ . '/brand-storage.php';
        $og_image = bandpromo_brand_resolve_active_shell_slot(dirname(__DIR__), 'poster');
    }
    $og_url = $url ?: get_config('install.site.url', '');
    $og_description = $description !== ''
        ? bandpromo_player_markdown_strip_to_plain_text((string) $description)
        : bandpromo_player_markdown_strip_to_plain_text((string) get_config('release.identity.description', ''));
    $og_title = $title ?: $site_name;
    
    // Convert relative image URL to absolute
    if ($og_image !== '' && strpos($og_image, 'http') !== 0) {
        $og_image = rtrim($og_url ?: get_config('install.site.url', ''), '/') . '/' . ltrim($og_image, '/');
    }
    
    $tags = array();
    $tags[] = sprintf('    <meta property="og:title" content="%s">', htmlspecialchars($og_title));
    $tags[] = sprintf('    <meta property="og:description" content="%s">', htmlspecialchars($og_description));
    $tags[] = sprintf('    <meta property="og:image" content="%s">', htmlspecialchars($og_image));
    $tags[] = sprintf('    <meta property="og:image:width" content="%d">', get_config('release.brand.poster_width', 1200));
    $tags[] = sprintf('    <meta property="og:image:height" content="%d">', get_config('release.brand.poster_height', 630));
    
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
    $site_name = get_config('release.identity.title', 'My Site');
    $twitter_handle = get_config('install.social.twitter', '');
    $twitter_image = $image ?: get_config('release.brand.poster', '');
    if ($twitter_image === '') {
        require_once __DIR__ . '/brand-storage.php';
        $twitter_image = bandpromo_brand_resolve_active_shell_slot(dirname(__DIR__), 'poster');
    }
    $twitter_description = $description !== ''
        ? bandpromo_player_markdown_strip_to_plain_text((string) $description)
        : bandpromo_player_markdown_strip_to_plain_text((string) get_config('release.identity.description', ''));
    $twitter_title = $title ?: $site_name;
    
    // Convert relative image URL to absolute
    if ($twitter_image !== '' && strpos($twitter_image, 'http') !== 0) {
        $twitter_image = rtrim(get_config('install.site.url', ''), '/') . '/' . ltrim($twitter_image, '/');
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
    // Handle keywords - can be string or array
    $keywords = get_config('release.social.keywords', 'website');
    if (is_array($keywords)) {
        $keywords = implode(', ', $keywords);
    }
    
    $tags = array();
    $tags[] = '    <meta charset="UTF-8">';
    $tags[] = '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $tags[] = sprintf('    <meta name="description" content="%s">', htmlspecialchars(bandpromo_player_markdown_strip_to_plain_text((string) get_config('release.identity.description', 'A web application'))));
    $tags[] = sprintf('    <meta name="keywords" content="%s">', htmlspecialchars($keywords));
    $tags[] = sprintf('    <meta name="author" content="%s">', htmlspecialchars(get_config('install.site.author', 'Author')));
    $tags[] = sprintf('    <meta name="theme-color" content="%s">', htmlspecialchars($config['branding']['theme_color'] ?? '#121212'));
    $tags[] = '    <meta name="format-detection" content="telephone=no">';
    
    return implode("\n", $tags);
}
