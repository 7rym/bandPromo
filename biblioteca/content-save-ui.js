(function () {
    function applyButtonState(btn, state, labels) {
        const saveLabel = labels.saveLabel || 'Save';
        const savedLabel = labels.savedLabel || 'Saved';

        btn.hidden = false;
        btn.disabled = false;
        btn.classList.remove('btn-amber', 'btn-saved');

        if (state === 'hidden') {
            btn.hidden = true;
            btn.textContent = saveLabel;
            return;
        }

        if (state === 'dirty') {
            btn.classList.add('btn-amber');
            btn.textContent = saveLabel;
            return;
        }

        if (state === 'saved') {
            btn.classList.add('btn-saved');
            btn.textContent = savedLabel;
            btn.disabled = true;
            return;
        }

        if (state === 'saving') {
            btn.classList.add('btn-amber');
            btn.textContent = 'Saving…';
            btn.disabled = true;
        }
    }

    function createContentSaveController(btn, labels) {
        if (!btn) {
            return {
                setBaseline() {},
                reconcile() {},
                markSaving() {},
                markSaved() {},
                markDirty() {},
                markFailed() {},
                reset() {},
            };
        }

        let baseline = '';
        let sessionSaved = false;
        let readFingerprint = typeof labels.readFingerprint === 'function' ? labels.readFingerprint : () => '';

        function reconcile() {
            const dirty = readFingerprint() !== baseline;
            if (dirty) {
                applyButtonState(btn, 'dirty', labels);
                return;
            }
            if (sessionSaved) {
                applyButtonState(btn, 'saved', labels);
                return;
            }
            applyButtonState(btn, 'hidden', labels);
        }

        applyButtonState(btn, 'hidden', labels);

        return {
            setBaseline(nextBaseline) {
                baseline = typeof nextBaseline === 'string' ? nextBaseline : readFingerprint();
                reconcile();
            },
            reconcile,
            markSaving() {
                applyButtonState(btn, 'saving', labels);
            },
            markSaved() {
                baseline = readFingerprint();
                sessionSaved = true;
                reconcile();
            },
            markDirty() {
                applyButtonState(btn, 'dirty', labels);
            },
            markFailed() {
                reconcile();
            },
            reset() {
                sessionSaved = false;
                baseline = readFingerprint();
                applyButtonState(btn, 'hidden', labels);
            },
        };
    }

    window.bandpromoContentSaveUi = {
        create: createContentSaveController,
    };
})();
