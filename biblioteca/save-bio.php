<?php
/**
 * Save bio.php content.
 * Accepts raw HTML/text via POST body. Admin-only.
 */

require_once dirname(__DIR__) . '/vendor/htmlpurifier/library/HTMLPurifier.auto.php';
require_once __DIR__ . '/admin-audit.php';

require_once __DIR__ . '/admin-api-guard.php';
session_write_close(); // release lock before file I/O

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false) {
    echo json_encode(['error' => 'Could not read request body']);
    exit;
}

$purifierCacheDir = dirname(__DIR__) . '/log/htmlpurifier';
if (!is_dir($purifierCacheDir)) {
    mkdir($purifierCacheDir, 0750, true);
}

function bandpromo_is_allowed_page_image_src(string $src): bool
{
    $allowedPrefixes = [
        '/media/img/optimal/',
        '/media/photo/optimal/',
    ];

    foreach ($allowedPrefixes as $prefix) {
        if (strpos($src, $prefix) === 0) {
            return true;
        }
    }

    return false;
}

function bandpromo_is_allowed_link_href(string $href): bool
{
    if ($href === '' || strpos($href, '//') === 0) {
        return false;
    }

    if ($href[0] === '#' || $href[0] === '/') {
        return true;
    }

    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href)) {
        return (bool) preg_match('/^(https?|mailto):/i', $href);
    }

    return true;
}

function bandpromo_postprocess_page_html(string $html): string
{
    if (!class_exists('DOMDocument')) {
        return $html;
    }

    $previousState = libxml_use_internal_errors(true);

    $doc = new DOMDocument('1.0', 'UTF-8');
    $wrapper = '<div id="bandpromo-page-root">' . $html . '</div>';
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
        $src = trim((string) $img->getAttribute('src'));
        if (!bandpromo_is_allowed_page_image_src($src)) {
            $img->parentNode?->removeChild($img);
            continue;
        }

        foreach (['srcset', 'sizes', 'style', 'width', 'height'] as $attribute) {
            if ($img->hasAttribute($attribute)) {
                $img->removeAttribute($attribute);
            }
        }
    }

    foreach (iterator_to_array($doc->getElementsByTagName('a')) as $link) {
        $href = trim((string) $link->getAttribute('href'));
        if (!bandpromo_is_allowed_link_href($href)) {
            $link->removeAttribute('href');
        }

        $target = trim((string) $link->getAttribute('target'));
        if ($target === '_blank') {
            $link->setAttribute('rel', 'noopener noreferrer');
        } elseif ($target !== '') {
            $link->removeAttribute('target');
        }
    }

    $root = $doc->getElementsByTagName('div')->item(0);
    $output = '';
    if ($root !== null) {
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previousState);

    return trim($output);
}

function bandpromo_sanitize_page_html(string $html): string
{
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Core.Encoding', 'UTF-8');
    $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
    $config->set('Cache.SerializerPath', dirname(__DIR__) . '/log/htmlpurifier');
    $config->set('HTML.Allowed', 'p,br,strong,em,b,i,h2,h3,h4,blockquote,ul,ol,li,a[href|target|rel],img[src|alt|title],hr');
    $config->set('Attr.AllowedFrameTargets', ['_blank']);
    $config->set('CSS.AllowedProperties', []);
    $config->set('HTML.SafeIframe', false);
    $config->set('HTML.SafeObject', false);
    $config->set('Output.TidyFormat', true);

    $purifier = new HTMLPurifier($config);
    return bandpromo_postprocess_page_html($purifier->purify($html));
}

$bio_file = dirname(__DIR__) . '/data/bio.html';
$sanitized = bandpromo_sanitize_page_html($body);

// Ensure data dir exists
if (!is_dir(dirname($bio_file))) {
    mkdir(dirname($bio_file), 0750, true);
}

if (file_put_contents($bio_file, $sanitized) === false) {
    bandpromo_admin_audit_log('page_saved', [
        'target_type' => 'page',
        'target_id' => 'bio',
        'status' => 'error',
        'data' => ['error' => 'Write failed'],
    ]);
    echo json_encode(['error' => 'Could not write data/bio.html — check file permissions']);
    exit;
}

bandpromo_admin_audit_log('page_saved', [
    'target_type' => 'page',
    'target_id' => 'bio',
    'status' => 'ok',
    'data' => [
        'sanitized' => trim($body) !== trim($sanitized),
        'input_bytes' => strlen($body),
        'saved_bytes' => strlen($sanitized),
        'endpoint' => 'save-bio.php',
    ],
]);

echo json_encode([
    'ok' => true,
    'sanitized' => trim($body) !== trim($sanitized),
    'html' => $sanitized,
]);
