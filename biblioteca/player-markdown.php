<?php
declare(strict_types=1);

/**
 * Restricted Markdown renderer for player-shell operator text.
 *
 * Storage remains plain Markdown in masters/containers; render to safe HTML at output.
 */

function bandpromo_player_markdown_normalize_mode(string $mode): string
{
    $mode = strtolower(trim($mode));
    if ($mode === 'lyrics' || $mode === 'notes') {
        return $mode;
    }

    return 'default';
}

function bandpromo_player_markdown_strip_to_plain_text(string $markdown): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $markdown);
    $text = preg_replace('/```[\s\S]*?```/', ' ', $text) ?? $text;
    $text = preg_replace('/`([^`]+)`/', '$1', $text) ?? $text;
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1', $text) ?? $text;
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '$1', $text) ?? $text;
    $text = preg_replace('/^\s{0,3}#{1,6}\s+/m', '', $text) ?? $text;
    $text = preg_replace('/^\s*>\s?/m', '', $text) ?? $text;
    $text = preg_replace('/^\s*([-*]|\d+\.)\s+/m', '', $text) ?? $text;
    $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text) ?? $text;
    $text = preg_replace('/\*([^*]+)\*/', '$1', $text) ?? $text;
    $text = preg_replace('/_([^_]+)_/', '$1', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function bandpromo_player_markdown_is_safe_href(string $href): bool
{
    $href = trim($href);
    if ($href === '') {
        return false;
    }

    return preg_match('~^(https?:|mailto:)~i', $href) === 1;
}

function bandpromo_player_markdown_render_link(string $label, string $href): string
{
    $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    if (!bandpromo_player_markdown_is_safe_href($href)) {
        return $label;
    }

    $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
    $rel = stripos($href, 'mailto:') === 0 ? 'nofollow' : 'noopener noreferrer';

    return '<a href="' . $safeHref . '" rel="' . $rel . '">' . $label . '</a>';
}

function bandpromo_player_markdown_render_inline(string $text): string
{
    $placeholders = [];

    $text = preg_replace_callback('/`([^`]+)`/', static function (array $matches) use (&$placeholders): string {
        $token = 'PLAYERMDCODE' . count($placeholders);
        $placeholders[] = [
            'token' => $token,
            'html' => '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>',
        ];

        return $token;
    }, $text) ?? $text;

    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $matches) use (&$placeholders): string {
        $token = 'PLAYERMDLINK' . count($placeholders);
        $placeholders[] = [
            'token' => $token,
            'html' => bandpromo_player_markdown_render_link($matches[1], trim((string) $matches[2])),
        ];

        return $token;
    }, $text) ?? $text;

    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
    $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;
    $escaped = preg_replace('/_([^_]+)_/', '<em>$1</em>', $escaped) ?? $escaped;

    foreach ($placeholders as $placeholder) {
        $escaped = str_replace($placeholder['token'], $placeholder['html'], $escaped);
    }

    return $escaped;
}

/**
 * @return list<string>
 */
function bandpromo_player_markdown_split_paragraphs(string $markdown): array
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $markdown);
    $chunks = preg_split("/\n\s*\n/", $normalized) ?: [];
    $paragraphs = [];

    foreach ($chunks as $chunk) {
        $chunk = trim((string) $chunk);
        if ($chunk !== '') {
            $paragraphs[] = $chunk;
        }
    }

    if ($paragraphs === [] && trim($normalized) !== '') {
        $paragraphs[] = trim($normalized);
    }

    return $paragraphs;
}

/**
 * Restricted block Markdown. When $hardBreaks is true (lyrics mode), consecutive
 * plain lines become one paragraph joined with <br> instead of spaces.
 *
 * @return list<string>
 */
function bandpromo_player_markdown_render_blocks(string $markdown, bool $hardBreaks): array
{
    $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
    $html = [];
    $paragraphLines = [];
    $listItems = [];
    $listType = '';
    $quoteLines = [];
    $codeLines = [];
    $inCodeBlock = false;

    $flushParagraph = static function () use (&$html, &$paragraphLines, $hardBreaks): void {
        if ($paragraphLines === []) {
            return;
        }
        if ($hardBreaks) {
            $parts = [];
            foreach ($paragraphLines as $line) {
                $parts[] = bandpromo_player_markdown_render_inline((string) $line);
            }
            $html[] = '<p>' . implode('<br>', $parts) . '</p>';
        } else {
            $html[] = '<p>' . bandpromo_player_markdown_render_inline(trim(implode(' ', $paragraphLines))) . '</p>';
        }
        $paragraphLines = [];
    };

    $flushList = static function () use (&$html, &$listItems, &$listType): void {
        if ($listItems === [] || $listType === '') {
            $listItems = [];
            $listType = '';
            return;
        }
        $html[] = '<' . $listType . '>' . implode('', $listItems) . '</' . $listType . '>';
        $listItems = [];
        $listType = '';
    };

    $flushQuote = static function () use (&$html, &$quoteLines): void {
        if ($quoteLines === []) {
            return;
        }
        $html[] = '<blockquote>' . bandpromo_player_markdown_render_inline(trim(implode(' ', $quoteLines))) . '</blockquote>';
        $quoteLines = [];
    };

    $flushCode = static function () use (&$html, &$codeLines): void {
        if ($codeLines === []) {
            return;
        }
        $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES, 'UTF-8') . '</code></pre>';
        $codeLines = [];
    };

    foreach ($lines as $line) {
        if ($inCodeBlock) {
            if (preg_match('/^```/', $line) === 1) {
                $flushCode();
                $inCodeBlock = false;
            } else {
                $codeLines[] = $line;
            }
            continue;
        }

        if (preg_match('/^```/', $line) === 1) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $inCodeBlock = true;
            continue;
        }

        if (trim($line) === '') {
            $flushParagraph();
            $flushList();
            $flushQuote();
            continue;
        }

        if (preg_match('/^\s{0,3}(#{1,6})\s+(.+)$/', $line, $matches) === 1) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $level = strlen($matches[1]);
            $html[] = '<h' . $level . '>' . bandpromo_player_markdown_render_inline(trim((string) $matches[2])) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^\s{0,3}([-*]){3,}\s*$/', $line) === 1) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $html[] = '<hr>';
            continue;
        }

        if (preg_match('/^\s*>\s?(.*)$/', $line, $matches) === 1) {
            $flushParagraph();
            $flushList();
            $quoteLines[] = trim((string) $matches[1]);
            continue;
        }

        if (preg_match('/^\s*([-*]|\d+\.)\s+(.+)$/', $line, $matches) === 1) {
            $flushParagraph();
            $flushQuote();
            $nextListType = preg_match('/^\d+\.$/', $matches[1]) === 1 ? 'ol' : 'ul';
            if ($listType !== '' && $listType !== $nextListType) {
                $flushList();
            }
            $listType = $nextListType;
            $listItems[] = '<li>' . bandpromo_player_markdown_render_inline(trim((string) $matches[2])) . '</li>';
            continue;
        }

        $flushList();
        $flushQuote();
        $paragraphLines[] = $hardBreaks ? rtrim($line) : trim($line);
    }

    if ($inCodeBlock) {
        $flushCode();
    }

    $flushParagraph();
    $flushList();
    $flushQuote();

    return $html;
}

function bandpromo_player_markdown_render_lyrics(string $markdown): string
{
    $html = bandpromo_player_markdown_render_blocks($markdown, true);
    if ($html === []) {
        return '';
    }

    return '<div class="player-markdown player-markdown--lyrics">' . implode('', $html) . '</div>';
}

function bandpromo_player_markdown_render_default(string $markdown): string
{
    $html = bandpromo_player_markdown_render_blocks($markdown, false);
    if ($html === []) {
        return '';
    }

    return '<div class="player-markdown">' . implode('', $html) . '</div>';
}

function bandpromo_player_markdown_render_notes(string $markdown): string
{
    $html = bandpromo_player_markdown_render_blocks($markdown, false);
    if ($html === []) {
        return '';
    }

    return '<div class="player-markdown player-markdown--notes">' . implode('', $html) . '</div>';
}

function bandpromo_player_markdown_render(string $markdown, string $mode = 'default'): string
{
    $markdown = trim($markdown);
    if ($markdown === '') {
        return '';
    }

    $normalized = bandpromo_player_markdown_normalize_mode($mode);
    if ($normalized === 'lyrics') {
        return bandpromo_player_markdown_render_lyrics($markdown);
    }
    if ($normalized === 'notes') {
        return bandpromo_player_markdown_render_notes($markdown);
    }

    return bandpromo_player_markdown_render_default($markdown);
}
