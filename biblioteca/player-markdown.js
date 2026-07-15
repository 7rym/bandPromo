(function (global) {
    'use strict';

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isSafeHref(href) {
        const value = String(href || '').trim();
        return /^(https?:|mailto:)/i.test(value);
    }

    function renderLink(label, href) {
        const safeLabel = escapeHtml(label);
        if (!isSafeHref(href)) {
            return safeLabel;
        }
        const rel = /^mailto:/i.test(href) ? 'nofollow' : 'noopener noreferrer';
        return `<a href="${escapeHtml(href)}" rel="${rel}">${safeLabel}</a>`;
    }

    function renderInline(text) {
        const placeholders = [];
        let working = String(text);

        working = working.replace(/`([^`]+)`/g, (_, code) => {
            const token = `PLAYERMDCODE${placeholders.length}`;
            placeholders.push({ token, html: `<code>${escapeHtml(code)}</code>` });
            return token;
        });

        working = working.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (_, label, href) => {
            const token = `PLAYERMDLINK${placeholders.length}`;
            placeholders.push({ token, html: renderLink(label, String(href).trim()) });
            return token;
        });

        let escaped = escapeHtml(working);
        escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        escaped = escaped.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        escaped = escaped.replace(/_([^_]+)_/g, '<em>$1</em>');

        placeholders.forEach((entry) => {
            escaped = escaped.split(entry.token).join(entry.html);
        });

        return escaped;
    }

    function splitParagraphs(markdown) {
        const normalized = String(markdown).replace(/\r\n?/g, '\n').trim();
        if (normalized === '') {
            return [];
        }
        const chunks = normalized.split(/\n\s*\n/);
        return chunks.map((chunk) => chunk.trim()).filter(Boolean);
    }

    function renderLyrics(markdown) {
        const paragraphs = splitParagraphs(markdown);
        if (!paragraphs.length) {
            return '';
        }

        const html = paragraphs.map((paragraph) => {
            const lines = paragraph.split('\n');
            const renderedLines = lines.map((line) => {
                const trimmed = line.replace(/\s+$/, '');
                if (trimmed === '') {
                    return '<br>';
                }
                return renderInline(trimmed);
            });
            return `<p>${renderedLines.join('<br>')}</p>`;
        });

        return `<div class="player-markdown player-markdown--lyrics">${html.join('')}</div>`;
    }

    function renderDefault(markdown) {
        const lines = String(markdown).replace(/\r\n?/g, '\n').split('\n');
        const html = [];
        let paragraphLines = [];
        let listItems = [];
        let listType = '';
        let quoteLines = [];
        let codeLines = [];
        let inCodeBlock = false;

        const flushParagraph = () => {
            if (!paragraphLines.length) {
                return;
            }
            html.push(`<p>${renderInline(paragraphLines.join(' ').trim())}</p>`);
            paragraphLines = [];
        };

        const flushList = () => {
            if (!listItems.length || !listType) {
                listItems = [];
                listType = '';
                return;
            }
            html.push(`<${listType}>${listItems.join('')}</${listType}>`);
            listItems = [];
            listType = '';
        };

        const flushQuote = () => {
            if (!quoteLines.length) {
                return;
            }
            html.push(`<blockquote>${renderInline(quoteLines.join(' ').trim())}</blockquote>`);
            quoteLines = [];
        };

        const flushCode = () => {
            if (!codeLines.length) {
                return;
            }
            html.push(`<pre><code>${escapeHtml(codeLines.join('\n'))}</code></pre>`);
            codeLines = [];
        };

        lines.forEach((line) => {
            if (inCodeBlock) {
                if (/^```/.test(line)) {
                    flushCode();
                    inCodeBlock = false;
                } else {
                    codeLines.push(line);
                }
                return;
            }

            if (/^```/.test(line)) {
                flushParagraph();
                flushList();
                flushQuote();
                inCodeBlock = true;
                return;
            }

            if (line.trim() === '') {
                flushParagraph();
                flushList();
                flushQuote();
                return;
            }

            const headingMatch = line.match(/^\s{0,3}(#{1,6})\s+(.+)$/);
            if (headingMatch) {
                flushParagraph();
                flushList();
                flushQuote();
                const level = headingMatch[1].length;
                html.push(`<h${level}>${renderInline(headingMatch[2].trim())}</h${level}>`);
                return;
            }

            if (/^\s{0,3}([-*]){3,}\s*$/.test(line)) {
                flushParagraph();
                flushList();
                flushQuote();
                html.push('<hr>');
                return;
            }

            const quoteMatch = line.match(/^\s*>\s?(.*)$/);
            if (quoteMatch) {
                flushParagraph();
                flushList();
                quoteLines.push(quoteMatch[1].trim());
                return;
            }

            const listMatch = line.match(/^\s*([-*]|\d+\.)\s+(.+)$/);
            if (listMatch) {
                flushParagraph();
                flushQuote();
                const nextListType = /^\d+\.$/.test(listMatch[1]) ? 'ol' : 'ul';
                if (listType && listType !== nextListType) {
                    flushList();
                }
                listType = nextListType;
                listItems.push(`<li>${renderInline(listMatch[2].trim())}</li>`);
                return;
            }

            flushList();
            flushQuote();
            paragraphLines.push(line.trim());
        });

        if (inCodeBlock) {
            flushCode();
        }
        flushParagraph();
        flushList();
        flushQuote();

        if (!html.length) {
            return '';
        }

        return `<div class="player-markdown">${html.join('')}</div>`;
    }

    function stripToPlainText(markdown) {
        let text = String(markdown).replace(/\r\n?/g, '\n');
        text = text.replace(/```[\s\S]*?```/g, ' ');
        text = text.replace(/`([^`]+)`/g, '$1');
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1');
        text = text.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '$1');
        text = text.replace(/^\s{0,3}#{1,6}\s+/gm, '');
        text = text.replace(/^\s*>\s?/gm, '');
        text = text.replace(/^\s*([-*]|\d+\.)\s+/gm, '');
        text = text.replace(/\*\*([^*]+)\*\*/g, '$1');
        text = text.replace(/\*([^*]+)\*/g, '$1');
        text = text.replace(/_([^_]+)_/g, '$1');
        text = text.replace(/\s+/g, ' ');

        return text.trim();
    }

    function render(markdown, options) {
        const source = String(markdown || '').trim();
        if (source === '') {
            return '';
        }
        const mode = options && options.mode === 'lyrics' ? 'lyrics' : 'default';
        return mode === 'lyrics' ? renderLyrics(source) : renderDefault(source);
    }

    global.bandpromoPlayerMarkdown = {
        escapeHtml,
        render,
        stripToPlainText,
    };
})(window);
