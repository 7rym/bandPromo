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

    /**
     * Restricted block Markdown. When hardBreaks is true (lyrics mode), consecutive
     * plain lines become a single paragraph joined with <br> instead of spaces.
     */
    function renderBlocks(markdown, hardBreaks) {
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
            if (hardBreaks) {
                html.push(`<p>${paragraphLines.map((line) => renderInline(line)).join('<br>')}</p>`);
            } else {
                html.push(`<p>${renderInline(paragraphLines.join(' ').trim())}</p>`);
            }
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
            paragraphLines.push(hardBreaks ? line.replace(/\s+$/, '') : line.trim());
        });

        if (inCodeBlock) {
            flushCode();
        }
        flushParagraph();
        flushList();
        flushQuote();

        return html;
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
        const rawMode = options && options.mode ? String(options.mode) : 'default';
        const mode = rawMode === 'lyrics' || rawMode === 'notes' ? rawMode : 'default';
        const hardBreaks = mode === 'lyrics';
        const html = renderBlocks(source, hardBreaks);
        if (!html.length) {
            return '';
        }
        let className = 'player-markdown';
        if (mode === 'lyrics') {
            className += ' player-markdown--lyrics';
        } else if (mode === 'notes') {
            className += ' player-markdown--notes';
        }
        return `<div class="${className}">${html.join('')}</div>`;
    }

    global.bandpromoPlayerMarkdown = {
        escapeHtml,
        render,
        stripToPlainText,
    };
})(window);
