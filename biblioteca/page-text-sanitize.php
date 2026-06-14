<?php
declare(strict_types=1);

function bandpromo_page_text_sanitizer(): HTMLPurifier {
    static $purifier = null;
    if ($purifier instanceof HTMLPurifier) {
        return $purifier;
    }

    require_once dirname(__DIR__) . '/vendor/htmlpurifier/library/HTMLPurifier.auto.php';

    $config = HTMLPurifier_Config::createDefault();
    $config->set(
        'HTML.Allowed',
        'h1[class],h2[class],h3[class],h4[class],p[class],pre[class],code,strong,b,em,i,u,a[href|title|target|rel],br,ul,ol,li'
    );
    $config->set('Attr.AllowedClasses', [
        'page-align-left' => true,
        'page-align-center' => true,
        'page-align-right' => true,
        'page-text-small' => true,
        'page-text-code' => true,
    ]);
    $config->set('HTML.TargetBlank', true);
    $config->set('Attr.AllowedFrameTargets', ['_blank']);
    $config->set('URI.AllowedSchemes', [
        'http' => true,
        'https' => true,
        'mailto' => true,
    ]);
    $config->set('Cache.SerializerPath', sys_get_temp_dir());

    $purifier = new HTMLPurifier($config);

    return $purifier;
}

function bandpromo_page_sanitize_rich_text(string $value): string {
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    if ($value === '') {
        return '';
    }

    if (strpos($value, '<') === false) {
        return $value;
    }

    return trim((string) bandpromo_page_text_sanitizer()->purify($value));
}

function bandpromo_page_sanitize_document_html(string $value): string {
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    if ($value === '') {
        return '';
    }

    if (strpos($value, '<') === false) {
        return '<p>' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }

    return trim((string) bandpromo_page_text_sanitizer()->purify($value));
}

function bandpromo_page_text_contains_markup(string $value): bool {
    return strpos($value, '<') !== false;
}
