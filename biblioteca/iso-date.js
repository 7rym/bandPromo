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
            const before = input.value;
            const normalized = normalizeIsoDateInput(before);
            if (!normalized) {
                return;
            }
            if (normalized !== before) {
                input.value = normalized;
            }
            if (normalized !== input.defaultValue) {
                input.defaultValue = normalized;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function syncTextToNative(textInput, nativeInput) {
        const normalized = normalizeIsoDateInput(textInput.value);
        if (normalized) {
            nativeInput.value = normalized;
        }
    }

    function syncNativeToText(textInput, nativeInput) {
        if (nativeInput.value) {
            textInput.value = nativeInput.value;
        }
    }

    function openNativePicker(nativeInput) {
        if (typeof nativeInput.showPicker === 'function') {
            try {
                nativeInput.showPicker();
                return;
            } catch (error) {
                // Fall through to click when showPicker is blocked.
            }
        }
        nativeInput.click();
    }

    function bindIsoDateField(wrapper) {
        if (!(wrapper instanceof HTMLElement)) {
            return;
        }
        const textInput = wrapper.querySelector('.iso-date-input');
        const nativeInput = wrapper.querySelector('.iso-date-picker-native');
        const pickerBtn = wrapper.querySelector('.iso-date-picker-btn');
        if (!(textInput instanceof HTMLInputElement) || !(nativeInput instanceof HTMLInputElement)) {
            return;
        }

        bindIsoDateInput(textInput);
        syncTextToNative(textInput, nativeInput);

        textInput.addEventListener('change', () => {
            syncTextToNative(textInput, nativeInput);
        });

        nativeInput.addEventListener('change', () => {
            syncNativeToText(textInput, nativeInput);
            textInput.dispatchEvent(new Event('change', { bubbles: true }));
        });

        if (pickerBtn instanceof HTMLButtonElement) {
            pickerBtn.addEventListener('click', (event) => {
                event.preventDefault();
                syncTextToNative(textInput, nativeInput);
                openNativePicker(nativeInput);
            });
        }
    }

    function bindIsoDateFields(root) {
        const scope = root instanceof Document || root instanceof HTMLElement ? root : document;
        scope.querySelectorAll('.iso-date-field').forEach(bindIsoDateField);
        scope.querySelectorAll('.iso-date-input').forEach((input) => {
            if (!(input.closest('.iso-date-field'))) {
                bindIsoDateInput(input);
            }
        });
    }

    window.bandpromoNormalizeIsoDateInput = normalizeIsoDateInput;
    window.bandpromoBindIsoDateInput = bindIsoDateInput;
    window.bandpromoBindIsoDateFields = bindIsoDateFields;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bindIsoDateFields(document));
    } else {
        bindIsoDateFields(document);
    }
})();
