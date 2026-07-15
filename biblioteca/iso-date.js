(function () {
    function normalizeIsoDateInput(value) {
        const trimmed = String(value || '').trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return trimmed;
        }
        // Keep year-only tags as YYYY (track editors / ID3 often use year alone).
        if (/^\d{4}$/.test(trimmed)) {
            return trimmed;
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

    function nativePickerSeed(value) {
        const normalized = normalizeIsoDateInput(value);
        if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
            return normalized;
        }
        if (/^\d{4}$/.test(normalized)) {
            return `${normalized}-01-01`;
        }
        return '';
    }

    function syncIsoDateFieldControls(wrapper) {
        if (!(wrapper instanceof HTMLElement)) {
            return;
        }
        const textInput = wrapper.querySelector('.iso-date-input');
        const nativeInput = wrapper.querySelector('.iso-date-picker-native');
        const pickerBtn = wrapper.querySelector('.iso-date-picker-btn');
        if (!(textInput instanceof HTMLInputElement) || !(nativeInput instanceof HTMLInputElement)) {
            return;
        }
        syncTextToNative(textInput, nativeInput);
        if (pickerBtn instanceof HTMLButtonElement) {
            pickerBtn.disabled = textInput.disabled || textInput.readOnly;
        }
    }

    function bindIsoDateField(wrapper) {
        if (!(wrapper instanceof HTMLElement)) {
            return;
        }
        if (wrapper.dataset.isoDateBound === '1') {
            return;
        }
        const textInput = wrapper.querySelector('.iso-date-input');
        const nativeInput = wrapper.querySelector('.iso-date-picker-native');
        const pickerBtn = wrapper.querySelector('.iso-date-picker-btn');
        if (!(textInput instanceof HTMLInputElement) || !(nativeInput instanceof HTMLInputElement)) {
            return;
        }

        bindIsoDateInput(textInput);
        syncIsoDateFieldControls(wrapper);

        textInput.addEventListener('change', () => {
            syncIsoDateFieldControls(wrapper);
        });
        textInput.addEventListener('input', () => {
            syncTextToNative(textInput, nativeInput);
        });

        nativeInput.addEventListener('change', () => {
            syncNativeToText(textInput, nativeInput);
            textInput.dispatchEvent(new Event('change', { bubbles: true }));
        });

        if (pickerBtn instanceof HTMLButtonElement) {
            pickerBtn.addEventListener('click', (event) => {
                event.preventDefault();
                if (textInput.disabled || textInput.readOnly || pickerBtn.disabled) {
                    return;
                }
                syncTextToNative(textInput, nativeInput);
                openNativePicker(nativeInput);
            });
        }

        wrapper.dataset.isoDateBound = '1';
    }

    function bindIsoDateInput(input) {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }
        if (input.dataset.isoDateBound === '1') {
            return;
        }
        input.addEventListener('input', () => {
            if (input.value.length > 10) {
                input.value = input.value.slice(0, 10);
            }
        });
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
            const wrapper = input.closest('.iso-date-field');
            if (wrapper instanceof HTMLElement) {
                syncIsoDateFieldControls(wrapper);
            }
        });
        input.dataset.isoDateBound = '1';
    }

    function syncTextToNative(textInput, nativeInput) {
        const seed = nativePickerSeed(textInput.value);
        nativeInput.value = seed;
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

    function bindIsoDateFields(root) {
        const scope = root instanceof Document || root instanceof HTMLElement ? root : document;
        scope.querySelectorAll('.iso-date-field').forEach(bindIsoDateField);
        scope.querySelectorAll('.iso-date-input').forEach((input) => {
            if (!(input.closest('.iso-date-field'))) {
                bindIsoDateInput(input);
            }
        });
    }

    function syncIsoDateField(inputOrWrapper) {
        let wrapper = null;
        if (inputOrWrapper instanceof HTMLInputElement) {
            wrapper = inputOrWrapper.closest('.iso-date-field');
        } else if (inputOrWrapper instanceof HTMLElement) {
            wrapper = inputOrWrapper.classList.contains('iso-date-field')
                ? inputOrWrapper
                : inputOrWrapper.querySelector('.iso-date-field');
        }
        if (wrapper instanceof HTMLElement) {
            if (wrapper.dataset.isoDateBound !== '1') {
                bindIsoDateField(wrapper);
            } else {
                syncIsoDateFieldControls(wrapper);
            }
        }
    }

    window.bandpromoNormalizeIsoDateInput = normalizeIsoDateInput;
    window.bandpromoBindIsoDateInput = bindIsoDateInput;
    window.bandpromoBindIsoDateFields = bindIsoDateFields;
    window.bandpromoSyncIsoDateField = syncIsoDateField;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => bindIsoDateFields(document));
    } else {
        bindIsoDateFields(document);
    }
})();
