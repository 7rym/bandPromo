(function (global) {
    function sanitizeInput(value) {
        return String(value || '')
            .replace(/[\x00-\x1F\x7F]/g, '')
            .trim();
    }

    function domainFromUrl(url) {
        const raw = String(url || '').trim();
        if (!raw) {
            return '';
        }

        try {
            const parsed = new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`);
            let host = String(parsed.hostname || '').toLowerCase();
            if (host.startsWith('www.')) {
                host = host.slice(4);
            }
            if (!host || host === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(host)) {
                return '';
            }
            return host;
        } catch (error) {
            return '';
        }
    }

    function localFromAuthor(author) {
        return String(author || '').toLowerCase().replace(/[^a-z0-9]+/g, '').slice(0, 64);
    }

    function formatContact(name, addr) {
        const mailbox = String(addr || '').trim();
        if (!mailbox) {
            return '';
        }

        const displayName = String(name || '').trim();
        if (!displayName) {
            return mailbox;
        }

        if (/[<>"]/.test(displayName)) {
            const escaped = displayName.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            return `"${escaped}" <${mailbox}>`;
        }

        return `${displayName} <${mailbox}>`;
    }

    function normalizeAddrSpec(addr) {
        const candidate = String(addr || '').trim().toLowerCase();
        if (!candidate || candidate.split('@').length !== 2) {
            return null;
        }

        const atIndex = candidate.indexOf('@');
        const local = candidate.slice(0, atIndex).trim();
        const domain = candidate.slice(atIndex + 1).trim();
        if (!local || !domain || local.includes('..') || domain.includes('..')) {
            return null;
        }
        if (local.startsWith('.') || local.endsWith('.') || domain.startsWith('.') || domain.endsWith('.')) {
            return null;
        }
        if (!domain.includes('.')) {
            return null;
        }
        if (!/^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/.test(`${local}@${domain}`)) {
            return null;
        }

        return `${local}@${domain}`;
    }

    function parseContact(value) {
        const raw = sanitizeInput(value);
        if (!raw) {
            return null;
        }

        let match = raw.match(/^"((?:\\.|[^"\\])*)"\s*<([^>]+)>$/);
        if (match) {
            const name = match[1].replace(/\\(["\\])/g, '$1');
            const addr = normalizeAddrSpec(match[2]);
            if (addr) {
                return { name, addr };
            }
            return null;
        }

        match = raw.match(/^([^<]+)<([^>]+)>$/);
        if (match) {
            const name = match[1].trim();
            const addr = normalizeAddrSpec(match[2]);
            if (name && addr) {
                return { name, addr };
            }
            return null;
        }

        const addr = normalizeAddrSpec(raw);
        if (addr) {
            return { name: '', addr };
        }

        return null;
    }

    function normalize(value) {
        const parsed = parseContact(value);
        if (!parsed) {
            return null;
        }

        const name = parsed.name.replace(/\s+/g, ' ').trim();
        return formatContact(name, parsed.addr);
    }

    function derive(author, url) {
        const displayName = String(author || '').trim();
        const domain = domainFromUrl(url);
        if (!domain) {
            return '';
        }

        let local = localFromAuthor(displayName);
        if (!local) {
            local = 'contact';
        }

        return normalize(formatContact(displayName, `${local}@${domain}`)) || '';
    }

    function isValid(value) {
        const raw = sanitizeInput(value);
        if (!raw) {
            return true;
        }
        return normalize(raw) !== null;
    }

    function invalidMessage() {
        return 'Contact must be a valid RFC 5322 address (for example 7rym <7rym@7rym.net>).';
    }

    function mailbox(value) {
        const parsed = parseContact(value);
        return parsed ? parsed.addr : '';
    }

    global.bandpromoSiteContactDerive = derive;
    global.bandpromoSiteContactNormalize = normalize;
    global.bandpromoSiteContactIsValid = isValid;
    global.bandpromoSiteContactInvalidMessage = invalidMessage;
    global.bandpromoSiteContactParse = parseContact;
    global.bandpromoSiteContactMailbox = mailbox;
}(typeof window !== 'undefined' ? window : globalThis));
