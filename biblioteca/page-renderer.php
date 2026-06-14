<?php
declare(strict_types=1);

require_once __DIR__ . '/page-blocks.php';
require_once __DIR__ . '/page-text-sanitize.php';

function bandpromo_page_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bandpromo_page_render_text_content(string $text): string {
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    if (bandpromo_page_text_contains_markup($text)) {
        return bandpromo_page_sanitize_document_html($text);
    }

    return nl2br(bandpromo_page_escape($text), false);
}

function bandpromo_page_render_block(array $block): string {
    $type = (string) ($block['type'] ?? '');

    if ($type === 'richtext') {
        $html = bandpromo_page_render_text_content((string) ($block['html'] ?? ''));
        if ($html === '') {
            return '';
        }

        return '<div class="page-richtext">' . $html . '</div>';
    }

    if ($type === 'picture') {
        $src = bandpromo_page_escape((string) ($block['src'] ?? ''));
        if ($src === '') {
            return '';
        }

        $style = bandpromo_page_resolve_picture_style($block);
        $alt = bandpromo_page_escape((string) ($block['alt'] ?? 'Picture'));
        $body = bandpromo_page_render_text_content((string) ($block['body'] ?? ''));
        $caption = trim((string) ($block['caption'] ?? ''));
        $classes = bandpromo_page_picture_style_classes($style);
        $inlineStyle = bandpromo_page_picture_style_inline($style);

        $html = '<section class="page-picture ' . $classes . '" style="' . bandpromo_page_escape($inlineStyle) . '">';
        $html .= '<figure class="page-picture-media"><img src="' . $src . '" alt="' . $alt . '" loading="lazy" decoding="async">';
        if ($caption !== '') {
            $html .= '<figcaption class="page-caption">' . bandpromo_page_escape($caption) . '</figcaption>';
        }
        $html .= '</figure>';
        if ($body !== '') {
            $html .= '<div class="page-picture-body">' . $body . '</div>';
        }
        $html .= '</section>';

        return $html;
    }

    if ($type === 'heading') {
        $level = (int) ($block['level'] ?? 2);
        if (!in_array($level, [2, 3, 4], true)) {
            $level = 2;
        }
        $text = bandpromo_page_render_text_content((string) ($block['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        return '<h' . $level . ' class="page-heading page-heading--' . $level . '">' . $text . '</h' . $level . '>';
    }

    if ($type === 'paragraph') {
        $text = bandpromo_page_render_text_content((string) ($block['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        return '<p class="page-paragraph">' . $text . '</p>';
    }

    if ($type === 'list') {
        $style = (string) ($block['style'] ?? 'unordered');
        $tag = $style === 'ordered' ? 'ol' : 'ul';
        $items = isset($block['items']) && is_array($block['items']) ? $block['items'] : [];
        $html = '<' . $tag . ' class="page-list page-list--' . bandpromo_page_escape($style) . '">';
        foreach ($items as $item) {
            $itemHtml = bandpromo_page_render_text_content((string) $item);
            if ($itemHtml !== '') {
                $html .= '<li>' . $itemHtml . '</li>';
            }
        }
        $html .= '</' . $tag . '>';

        return $html;
    }

    if ($type === 'quote') {
        $text = bandpromo_page_render_text_content((string) ($block['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $html = '<blockquote class="page-quote"><p>' . $text . '</p>';
        $attribution = trim((string) ($block['attribution'] ?? ''));
        if ($attribution !== '') {
            $html .= '<footer class="page-quote-attribution">— ' . bandpromo_page_escape($attribution) . '</footer>';
        }
        $html .= '</blockquote>';

        return $html;
    }

    if ($type === 'image') {
        $src = bandpromo_page_escape((string) ($block['src'] ?? ''));
        if ($src === '') {
            return '';
        }
        $alt = bandpromo_page_escape((string) ($block['alt'] ?? 'Content image'));
        $preset = bandpromo_page_escape((string) ($block['preset'] ?? 'feature'));
        $caption = trim((string) ($block['caption'] ?? ''));

        $html = '<figure class="page-figure page-image page-image--' . $preset . '">';
        $html .= '<img src="' . $src . '" alt="' . $alt . '" loading="lazy" decoding="async">';
        if ($caption !== '') {
            $html .= '<figcaption class="page-caption">' . bandpromo_page_escape($caption) . '</figcaption>';
        }
        $html .= '</figure>';

        return $html;
    }

    if ($type === 'divider') {
        return '<hr class="page-divider">';
    }

    if ($type === 'callout') {
        $text = bandpromo_page_render_text_content((string) ($block['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $tone = bandpromo_page_escape((string) ($block['tone'] ?? 'note'));

        return '<aside class="page-callout page-callout--' . $tone . '"><p>' . $text . '</p></aside>';
    }

    return '';
}

function bandpromo_page_render_document(array $document): string {
    $blocks = isset($document['blocks']) && is_array($document['blocks']) ? $document['blocks'] : [];
    $parts = ['<div class="page-content">'];

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $html = bandpromo_page_render_block($block);
        if ($html !== '') {
            $parts[] = $html;
        }
    }

    $parts[] = '</div>';

    return implode("\n", $parts);
}
