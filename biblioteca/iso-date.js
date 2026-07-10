(function () {
    function normalizeIsoDateInput(value) {
        const trimmed = String(value || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return trimmed;
        }
        if (/^\d{4}$/.test(trimmed)) {
            return `${trimmed}-01-01`;
        }
        const dotted = trimmed.match(/^(\d{4})\.(\d{2})\.(\d{2})$/);
        if (dotted) {
            return `${dotted[1]}-${dotted[2]}-${dotted[3]}`;
        }
        const us = trimmed.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (us) {
            const month = String(us[1]).padStart(2, '0');
            const day = String(us[2]).padStart(2, '0');
            return `${us[3]}-${month}-${day}`;
        }
        return '';
    }

    function bindIsoDateInput(input) {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        input.addEventListener('blur', () => {
            const normalized = normalizeIsoDateInput(input.value);
            if (normalized) {
                input.value = normalized;
            }
        });
    }

    window.bandpromoNormalizeIsoDateInput = normalizeIsoDateInput;
    window.bandpromoBindIsoDateInput = bindIsoDateInput;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.iso-date-input').forEach(bindIsoDateInput);
        });
    } else {
        document.querySelectorAll('.iso-date-input').forEach(bindIsoDateInput);
    }
})();
