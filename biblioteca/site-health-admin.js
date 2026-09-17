/**
 * System → Status site health UI (Check / Review treatment / Apply / Force).
 *
 * Brief UI; verbose evidence stays in Activity. Treatment is never silent:
 * Review (read-only preview) before Apply.
 *
 * Colour ladder (ADMIN-UI): one green `.btn-good` recommended next step;
 * amber findings for attention; red for critical; grey `.btn` for optional paths.
 */
(function () {
    'use strict';

    const overallEl = document.getElementById('siteHealthOverall');
    const summaryEl = document.getElementById('siteHealthSummary');
    const findingsEl = document.getElementById('siteHealthFindings');
    const previewEl = document.getElementById('siteHealthTreatPreview');
    const previewBodyEl = document.getElementById('siteHealthTreatPreviewBody');
    const metaEl = document.getElementById('siteHealthMeta');
    const logEl = document.getElementById('siteHealthLog');
    const spinnerEl = document.getElementById('siteHealthSpinner');
    const checkBtn = document.getElementById('siteHealthCheckBtn');
    const checkFullBtn = document.getElementById('siteHealthCheckFullBtn');
    const treatBtn = document.getElementById('siteHealthTreatBtn');
    const treatApplyBtn = document.getElementById('siteHealthTreatApplyBtn');
    const treatCancelBtn = document.getElementById('siteHealthTreatCancelBtn');
    const forceBtn = document.getElementById('siteHealthForceBtn');
    const stopBtn = document.getElementById('siteHealthStopBtn');
    const copyBtn = document.getElementById('siteHealthLogCopyBtn');

    if (!overallEl || !checkBtn) {
        return;
    }

    const TREATMENT_COPY = {
        audio_register_in_place: 'Register audio masters already on disk into Files (no copy).',
        visual_register_in_place: 'Add these files to Files so you can use them (no copy, no re-upload).',
        sfx_register_in_place: 'Register sound-effect masters already on disk into Files (no copy).',
        listener_delivery: 'Rebuild missing or stale stream deliverables (audio, stills, video, SFX).',
        audio_fill_display_from_tags: 'Fill empty track title/artist from master tags.',
        audio_extract_covers: 'Extract embedded artwork and link track covers.',
        media_janitor_prune: 'Clear the selected leftovers. Your originals and masters stay safe.',
        dedupe_retarget_and_remove: 'Retarget duplicates and remove unreferenced clone masters.',
        files_index_rebuild: 'Rebuild the Files → Audio index from the registry.',
        playlists: 'Republish player playlist payloads.',
        site_chrome: 'Update share images and PWA manifest.',
        container_links: 'Refresh container links and related site chrome.',
        sfx_delivery: 'Rebuild missing or stale sound-effect deliverables.',
    };

    const ACTION_BUTTONS = [
        checkBtn,
        checkFullBtn,
        treatBtn,
        treatApplyBtn,
        treatCancelBtn,
        forceBtn,
        stopBtn,
    ].filter(Boolean);

    let pollTimer = null;
    let running = false;
    let lastPlan = null;
    let previewOpen = false;

    const STALE_CHECK_MS = 60 * 60 * 1000;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function planCheckedAtMs(plan) {
        const raw = plan && plan.checked_at ? String(plan.checked_at).trim() : '';
        if (!raw) {
            return 0;
        }
        // Plans store UTC as 2026-09-17T16:17:00Z (or without Z).
        let iso = raw;
        if (/^\d{4}-\d{2}-\d{2}T/.test(iso) && !/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) {
            iso += 'Z';
        }
        const ms = Date.parse(iso);
        return Number.isFinite(ms) ? ms : 0;
    }

    function isPlanStale(plan) {
        const ms = planCheckedAtMs(plan);
        if (!ms) {
            return false;
        }
        return (Date.now() - ms) > STALE_CHECK_MS;
    }

    function staleCheckMessage() {
        return 'This health check is over an hour old. Run a fresh Quick health check before reviewing or applying treatment.';
    }

    function setJobStatus(text, tone) {
        // Status line removed — Healthy/attention badge + summary + Activity carry the story.
        // Keep hard errors visible via alert so start/stop failures are not silent.
        const message = String(text || '').trim();
        if (!message) {
            return;
        }
        if (tone === 'error') {
            window.alert(message);
        }
    }

    function clearActionTones() {
        ACTION_BUTTONS.forEach((btn) => {
            btn.classList.remove('btn-primary', 'btn-good', 'btn-amber', 'btn-saved', 'btn-danger');
        });
    }

    /**
     * Exactly one green recommended next step when idle; optional paths stay grey.
     */
    function syncRecommendedAction() {
        clearActionTones();
        if (running) {
            return;
        }
        let recommended = null;
        const findings = Array.isArray(lastPlan && lastPlan.findings) ? lastPlan.findings : [];
        const stale = isPlanStale(lastPlan);
        if (!stale && previewOpen && previewEl && !previewEl.hidden && treatApplyBtn && !treatApplyBtn.disabled) {
            recommended = treatApplyBtn;
        } else if (!stale && findings.length > 0 && treatBtn && !treatBtn.hidden) {
            recommended = treatBtn;
        } else if (checkBtn && !checkBtn.hidden) {
            recommended = checkBtn;
        }
        if (recommended) {
            recommended.classList.add('btn-good');
        }
    }

    function setOverall(overall) {
        const key = String(overall || 'unknown').toLowerCase();
        let label = 'Not checked yet';
        let cls = 'badge audit-status-badge status-neutral';
        if (key === 'healthy') {
            label = 'Healthy';
            cls = 'badge audit-status-badge status-ok';
        } else if (key === 'attention') {
            label = 'Needs attention';
            cls = 'badge audit-status-badge status-warning';
        } else if (key === 'critical') {
            label = 'Critical';
            cls = 'badge audit-status-badge status-error';
        } else if (key === 'running') {
            label = 'Working…';
            cls = 'badge audit-status-badge status-neutral';
        }
        overallEl.className = cls;
        overallEl.textContent = label;
    }

    function findingCardHtml(finding) {
        const severity = String((finding && finding.severity) || 'attention').toLowerCase();
        const stepClass = severity === 'critical'
            ? 'publish-next-step--critical'
            : 'publish-next-step--attention';
        const title = escapeHtml((finding && (finding.title || finding.id)) || 'Finding');
        const body = escapeHtml((finding && finding.body) || '');
        const count = Number((finding && finding.count) || 0);
        return (
            '<article class="publish-next-step ' + stepClass + '">' +
            '<strong>' + title + (count ? ' (' + count + ')' : '') + '</strong>' +
            (body ? '<p>' + body + '</p>' : '') +
            '</article>'
        );
    }

    function goodRatioHtml(row) {
        const label = escapeHtml((row && row.label) || row.id || 'Item');
        const ok = Number((row && row.ok) || 0);
        const total = Number((row && row.total) || 0);
        const note = row && row.note ? ' <span class="site-health-summary-note">(' + escapeHtml(row.note) + ')</span>' : '';
        const complete = total > 0 && ok === total;
        const empty = total === 0;
        const tone = empty ? 'is-empty' : (complete ? 'is-complete' : 'is-partial');
        return (
            '<li class="site-health-summary-ratio ' + tone + '">' +
            '<span class="site-health-summary-ratio-label">' + label + '</span>' +
            '<span class="site-health-summary-ratio-value">' + ok + '/' + total + '</span>' +
            note +
            '</li>'
        );
    }

    function renderSummary(plan) {
        if (!summaryEl) {
            return;
        }
        const summary = plan && plan.summary && typeof plan.summary === 'object'
            ? plan.summary
            : null;
        if (!summary) {
            summaryEl.hidden = true;
            summaryEl.innerHTML = '';
            return;
        }
        const good = Array.isArray(summary.good) ? summary.good : [];
        const bad = Array.isArray(summary.bad) ? summary.bad : [];
        const ugly = Array.isArray(summary.ugly) ? summary.ugly : [];

        const goodBody = good.length
            ? '<ul class="site-health-summary-ratios">' + good.map(goodRatioHtml).join('') + '</ul>'
            : '<p class="site-health-summary-empty">No catalogue inventory yet.</p>';
        const badBody = bad.length
            ? '<div class="publish-status-checks">' + bad.map(findingCardHtml).join('') + '</div>'
            : '<p class="site-health-summary-empty">Nothing that needs treatment.</p>';
        const uglyBody = ugly.length
            ? '<div class="publish-status-checks">' + ugly.map(findingCardHtml).join('') + '</div>'
            : '<p class="site-health-summary-empty">No junk found.</p>';

        summaryEl.hidden = false;
        summaryEl.innerHTML = (
            '<section class="site-health-summary-panel site-health-summary-panel--good' +
            (good.length ? '' : ' is-clear') + '">' +
            '<h3 class="site-health-summary-title">The good</h3>' +
            goodBody +
            '</section>' +
            '<section class="site-health-summary-panel site-health-summary-panel--bad' +
            (bad.length ? '' : ' is-clear') + '">' +
            '<h3 class="site-health-summary-title">The bad</h3>' +
            badBody +
            '</section>' +
            '<section class="site-health-summary-panel site-health-summary-panel--ugly' +
            (ugly.length ? '' : ' is-clear') + '">' +
            '<h3 class="site-health-summary-title">The ugly</h3>' +
            uglyBody +
            '</section>'
        );
    }

    function treatmentLines(plan) {
        const treatments = Array.isArray(plan && plan.treatments) ? plan.treatments : [];
        const findings = Array.isArray(plan && plan.findings) ? plan.findings : [];
        if (!treatments.length) {
            return [];
        }
        return treatments.map((treatment) => {
            const id = String(treatment.id || '').trim();
            const label = TREATMENT_COPY[id]
                || String(treatment.label || id || 'Treatment').trim();
            const related = findings.filter((f) => String(f.treatment || '') === id);
            const count = related.reduce((sum, f) => sum + (Number(f.count) || 0), 0);
            return {
                id: id,
                label: label,
                count: count,
            };
        });
    }

    function findingsFixable(plan) {
        const findings = Array.isArray(plan && plan.findings) ? plan.findings : [];
        if (!findings.length) {
            return { all: true, fixable: 0, manual: 0 };
        }
        let fixable = 0;
        let manual = 0;
        findings.forEach((finding) => {
            if (String(finding.treatment || '').trim()) {
                fixable += 1;
            } else {
                manual += 1;
            }
        });
        return {
            all: manual === 0 && fixable > 0,
            fixable: fixable,
            manual: manual,
        };
    }

    function assuranceHtml(plan) {
        const fix = findingsFixable(plan);
        if (fix.all) {
            return (
                '<p class="site-health-treat-assurance">' +
                'We found a few things that need tidying — nothing to panic about. ' +
                'Tick what you want fixed (everything useful is selected already), then Apply.' +
                '</p>'
            );
        }
        if (fix.fixable > 0 && fix.manual > 0) {
            return (
                '<p class="site-health-treat-assurance">' +
                'We found some issues. Apply can fix <strong>' + fix.fixable +
                '</strong> of them automatically; <strong>' + fix.manual +
                '</strong> need a manual step (see the items without a treatment).' +
                '</p>'
            );
        }
        if (fix.manual > 0 && fix.fixable === 0) {
            return (
                '<p class="site-health-treat-assurance site-health-treat-assurance--caution">' +
                'These findings need your attention outside Apply — bandPromo cannot auto-fix them from here.' +
                '</p>'
            );
        }
        return (
            '<p class="site-health-treat-assurance">' +
            'Read-only preview. Nothing changes until you Apply.' +
            '</p>'
        );
    }

    function findingReviewRowHtml(finding) {
        const severity = String((finding && finding.severity) || 'attention').toLowerCase();
        const tone = severity === 'critical' ? 'is-critical' : 'is-attention';
        const findingId = String((finding && finding.id) || '');
        const title = escapeHtml((finding && (finding.title || finding.id)) || 'Finding');
        const body = escapeHtml((finding && finding.body) || '');
        const count = Number((finding && finding.count) || 0);
        const treatmentId = String((finding && finding.treatment) || '').trim();
        const canTreat = treatmentId !== '';
        const treatmentLabel = canTreat
            ? escapeHtml(TREATMENT_COPY[treatmentId] || treatmentId)
            : 'Needs a manual step — Site health will not change this automatically.';
        const sample = Array.isArray(finding && finding.items_sample)
            ? finding.items_sample
            : [];
        const sampleItems = sample.map((item) => (
            '<li><code>' + escapeHtml(item) + '</code></li>'
        )).join('');

        const foundBits = [];
        if (body) {
            foundBits.push('<p>' + body + '</p>');
        }
        if (sampleItems) {
            const more = count > sample.length
                ? '<p class="site-health-treat-detail-more">Showing ' + sample.length +
                  ' of ' + count + ' for operators who want the full names — the rest is in Activity.</p>'
                : '';
            foundBits.push(
                '<p class="site-health-treat-detail-label">Item names (optional detail)</p>' +
                '<ul class="site-health-treat-detail-list">' + sampleItems + '</ul>' +
                more
            );
        } else if (count > 0 && !body) {
            foundBits.push(
                '<p class="site-health-treat-detail-more">Item list is in Activity (' +
                count + ').</p>'
            );
        } else if (!body) {
            foundBits.push('<p>' + title + (count ? ' (' + count + ')' : '') + '</p>');
        }

        const selectHtml = canTreat
            ? (
                '<label class="site-health-treat-select" onclick="event.stopPropagation()">' +
                '<input type="checkbox" class="site-health-treat-check" checked ' +
                'data-finding-id="' + escapeHtml(findingId) + '" ' +
                'data-treatment-id="' + escapeHtml(treatmentId) + '">' +
                '<span class="site-health-treat-select-label">Include</span>' +
                '</label>'
            )
            : (
                '<span class="site-health-treat-select site-health-treat-select--manual" ' +
                'title="Not auto-fixed by Apply">Manual</span>'
            );

        return (
            '<details class="site-health-treat-finding ' + tone +
            (canTreat ? '' : ' is-manual') + '">' +
            '<summary>' +
            selectHtml +
            '<span class="site-health-treat-finding-title">' + title +
            (count ? ' (' + count + ')' : '') + '</span>' +
            '<span class="site-health-treat-finding-hint">Details</span>' +
            '</summary>' +
            '<div class="site-health-treat-finding-body">' +
            '<p><strong>Found this:</strong></p>' +
            foundBits.join('') +
            '<p class="site-health-treat-suggested"><strong>Suggested treatment:</strong> ' +
            treatmentLabel + '</p>' +
            '</div>' +
            '</details>'
        );
    }

    function selectedTreatmentsFromPreview() {
        if (!previewBodyEl) {
            return { treatmentIds: [], findingIds: [], labels: [] };
        }
        const boxes = previewBodyEl.querySelectorAll('.site-health-treat-check:checked');
        const treatmentIds = [];
        const findingIds = [];
        const labels = [];
        const seen = {};
        Array.prototype.forEach.call(boxes, (box) => {
            const tid = String(box.getAttribute('data-treatment-id') || '').trim();
            const fid = String(box.getAttribute('data-finding-id') || '').trim();
            if (fid) {
                findingIds.push(fid);
            }
            if (!tid || seen[tid]) {
                return;
            }
            seen[tid] = true;
            treatmentIds.push(tid);
            const row = box.closest('.site-health-treat-finding');
            const titleEl = row ? row.querySelector('.site-health-treat-finding-title') : null;
            labels.push(
                (titleEl && titleEl.textContent)
                    ? titleEl.textContent.trim()
                    : (TREATMENT_COPY[tid] || tid)
            );
        });
        return { treatmentIds: treatmentIds, findingIds: findingIds, labels: labels };
    }

    function syncApplyEnabledFromSelection() {
        if (!treatApplyBtn || !previewOpen) {
            return;
        }
        const selected = selectedTreatmentsFromPreview();
        treatApplyBtn.disabled = selected.treatmentIds.length === 0;
        updateApplySummaryLine();
        syncRecommendedAction();
    }

    function bindPreviewSelectionHandlers() {
        if (!previewBodyEl) {
            return;
        }
        previewBodyEl.querySelectorAll('.site-health-treat-check').forEach((box) => {
            box.addEventListener('change', syncApplyEnabledFromSelection);
            box.addEventListener('click', (event) => {
                event.stopPropagation();
            });
        });
        syncApplyEnabledFromSelection();
    }

    function scrollPreviewIntoView() {
        if (!previewEl || previewEl.hidden) {
            return;
        }
        window.requestAnimationFrame(() => {
            try {
                previewEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (err) {
                previewEl.scrollIntoView(true);
            }
        });
    }

    function setPreviewMode(open) {
        previewOpen = !!open;
        if (!previewEl) {
            syncRecommendedAction();
            return;
        }
        if (!previewOpen || !lastPlan) {
            previewEl.hidden = true;
            if (previewBodyEl) {
                previewBodyEl.innerHTML = '';
            }
            if (treatBtn) {
                treatBtn.hidden = !(lastPlan && Array.isArray(lastPlan.findings) && lastPlan.findings.length);
            }
            if (treatApplyBtn) {
                treatApplyBtn.disabled = false;
            }
            if (treatCancelBtn) {
                treatCancelBtn.disabled = false;
            }
            syncRecommendedAction();
            return;
        }

        const findings = Array.isArray(lastPlan.findings) ? lastPlan.findings : [];
        const findingRows = findings.length
            ? findings.map(findingReviewRowHtml).join('')
            : '<p class="site-health-summary-empty">No findings on the current plan.</p>';

        if (previewBodyEl) {
            previewBodyEl.innerHTML = (
                '<h3 class="site-health-treat-preview-title">Proposed treatment</h3>' +
                assuranceHtml(lastPlan) +
                '<p class="site-health-treat-preview-note">' +
                'Tick what you want fixed (everything useful is selected to start). ' +
                'Nothing changes until you Apply. A backup first is a good idea if you want a restore point.' +
                '</p>' +
                '<div class="site-health-treat-finding-list">' + findingRows + '</div>' +
                '<p class="site-health-treat-preview-steps" id="siteHealthTreatApplySummary"></p>'
            );
        }
        previewEl.hidden = false;
        if (treatBtn) {
            treatBtn.hidden = true;
        }
        if (treatApplyBtn) {
            treatApplyBtn.disabled = false;
            treatApplyBtn.hidden = false;
        }
        if (treatCancelBtn) {
            treatCancelBtn.disabled = false;
            treatCancelBtn.hidden = false;
        }
        bindPreviewSelectionHandlers();
        updateApplySummaryLine();
        syncRecommendedAction();
        scrollPreviewIntoView();
    }

    function updateApplySummaryLine() {
        const el = document.getElementById('siteHealthTreatApplySummary');
        if (!el) {
            return;
        }
        const selected = selectedTreatmentsFromPreview();
        if (!selected.treatmentIds.length) {
            el.innerHTML = '<strong>Apply will run:</strong> nothing selected.';
            return;
        }
        el.innerHTML = (
            '<strong>Apply will run:</strong> ' +
            escapeHtml(selected.labels.join(' · '))
        );
    }

    function renderPlan(plan) {
        lastPlan = plan && typeof plan === 'object' ? plan : null;
        const findings = Array.isArray(plan && plan.findings) ? plan.findings : [];
        const checkedAt = plan && plan.checked_at ? String(plan.checked_at) : '';
        const appVersion = plan && plan.app_version ? String(plan.app_version) : '';
        const modeKey = plan && plan.mode ? String(plan.mode).trim().toLowerCase() : '';
        const modeLabel = ({
            check: 'Quick check',
            check_full: 'Full check',
            treat: 'Treatment',
            followup: 'Follow-up',
            force: 'Force rebuild',
        })[modeKey] || '';

        if (metaEl) {
            const bits = [];
            if (checkedAt) {
                bits.push('Last check: ' + checkedAt + ' UTC');
            }
            if (modeLabel) {
                bits.push(modeLabel);
            }
            if (appVersion) {
                bits.push(appVersion);
            }
            if (isPlanStale(plan)) {
                bits.push('stale — run a fresh check');
            }
            metaEl.innerHTML = bits.length
                ? escapeHtml(bits.join(' · '))
                : 'Run <strong>Quick health check</strong> for a routine exam, or <strong>Full health check</strong> for a deeper read-only verify. Nothing is changed until you Apply treatment.';
        }

        if (!findingsEl) {
            return;
        }

        if (!plan || Object.keys(plan).length === 0) {
            if (summaryEl) {
                summaryEl.hidden = true;
                summaryEl.innerHTML = '';
            }
            findingsEl.innerHTML = '<p class="publish-status-empty">No check yet — start with Quick health check.</p>';
            findingsEl.hidden = false;
            if (running) {
                return;
            }
            setPreviewMode(false);
            return;
        }

        const stale = isPlanStale(plan);

        // Good / Bad / Ugly is the Status summary; findings detail lives under Bad & Ugly.
        renderSummary(plan);
        if (summaryEl && !summaryEl.hidden) {
            summaryEl.classList.toggle('is-stale', stale);
        }
        if (plan.summary) {
            findingsEl.innerHTML = '';
            findingsEl.hidden = true;
        } else if (findings.length === 0) {
            findingsEl.hidden = false;
            findingsEl.innerHTML = '<p class="publish-status-empty">Nothing needs treatment. Listener catalogue looks healthy.</p>';
        } else {
            findingsEl.hidden = false;
            findingsEl.innerHTML = (
                '<div class="publish-status-checks">' +
                findings.map(findingCardHtml).join('') +
                '</div>'
            );
        }

        // While a job is running, setRunningUi owns chrome (status + Stop only).
        // Do not re-enable or unhide Check / Review / Force here.
        if (running) {
            return;
        }

        if (stale) {
            // Do not treat or keep Review open on a stale plan — ask for a fresh check.
            setPreviewMode(false);
            if (treatBtn) {
                treatBtn.hidden = true;
            }
            if (forceBtn) {
                forceBtn.hidden = false;
                forceBtn.disabled = false;
            }
            setJobStatus(staleCheckMessage(), 'attention');
            syncRecommendedAction();
            return;
        }

        if (findings.length === 0) {
            setPreviewMode(false);
            syncRecommendedAction();
            return;
        }

        if (previewOpen) {
            setPreviewMode(true);
        } else if (treatBtn) {
            treatBtn.hidden = false;
            treatBtn.disabled = false;
            if (previewEl) {
                previewEl.hidden = true;
            }
            syncRecommendedAction();
        }
        if (forceBtn) {
            forceBtn.hidden = false;
            forceBtn.disabled = false;
        }
    }

    function setRunningUi(isRunning) {
        running = !!isRunning;
        if (spinnerEl) {
            spinnerEl.style.display = running ? '' : 'none';
        }
        // Busy: hide start / review / force; only Stop stays available.
        checkBtn.disabled = running;
        checkBtn.hidden = running;
        if (checkFullBtn) {
            checkFullBtn.disabled = running;
            checkFullBtn.hidden = running;
        }
        forceBtn.disabled = running;
        forceBtn.hidden = running;
        if (treatBtn) {
            treatBtn.disabled = running;
            if (running) {
                treatBtn.hidden = true;
            }
        }
        if (treatApplyBtn) {
            treatApplyBtn.disabled = running;
        }
        if (treatCancelBtn) {
            treatCancelBtn.disabled = running;
        }
        if (previewEl && running) {
            previewEl.hidden = true;
            previewOpen = false;
        }
        if (stopBtn) {
            stopBtn.hidden = !running;
        }
        if (running) {
            setOverall('running');
            clearActionTones();
        } else {
            syncRecommendedAction();
        }
    }

    async function refreshStatus() {
        try {
            const resp = await fetch('/biblioteca/site-health-status.php', { credentials: 'same-origin' });
            const data = await resp.json();
            if (!data || data.ok !== true) {
                return data;
            }
            if (logEl && typeof data.log === 'string') {
                logEl.textContent = data.log.trim() !== '' ? data.log : 'No health activity yet.';
                logEl.scrollTop = logEl.scrollHeight;
            }
            const plan = data.plan && typeof data.plan === 'object' ? data.plan : {};
            // Set running before renderPlan so action buttons stay hidden while busy.
            const isRunning = !!data.running;
            running = isRunning;
            renderPlan(plan);
            setRunningUi(isRunning);
            if (!data.running) {
                setOverall(data.overall || plan.overall || 'unknown');
                if (data.exit_code === 0) {
                    const overall = String(data.overall || plan.overall || '');
                    const metaMode = String((data.meta && data.meta.mode) || '').trim();
                    const planMode = String(plan.mode || '').trim();
                    // Prefer the job that was launched (meta.mode). Follow-up overwrites
                    // plan.mode to "followup" / check — never call Force a Quick check.
                    const jobMode = metaMode || planMode;
                    if (overall === 'healthy') {
                        setPreviewMode(false);
                        let doneMsg = 'Health job complete — site looks healthy.';
                        if (jobMode === 'check_full') {
                            doneMsg = 'Full check complete — site looks healthy.';
                        } else if (jobMode === 'check') {
                            doneMsg = 'Quick check complete — site looks healthy.';
                        } else if (jobMode === 'treat') {
                            doneMsg = 'Treatment complete — site looks healthy.';
                        } else if (jobMode === 'force') {
                            doneMsg = 'Force rebuild complete — site looks healthy.';
                        } else if (jobMode === 'followup') {
                            doneMsg = 'Follow-up complete — site looks healthy.';
                        }
                        setJobStatus(doneMsg, 'success');
                    } else if (overall === 'critical' || overall === 'attention') {
                        // Findings live in The bad / The ugly — no duplicate status nudge.
                        if (jobMode === 'force') {
                            setJobStatus(
                                'Force rebuild finished with remaining findings.',
                                overall === 'critical' ? 'error' : 'attention'
                            );
                        } else if (jobMode === 'treat') {
                            setJobStatus(
                                'Treatment finished with remaining findings.',
                                overall === 'critical' ? 'error' : 'attention'
                            );
                        } else {
                            setJobStatus('');
                        }
                    } else {
                        setJobStatus('');
                    }
                }
                syncRecommendedAction();
            } else {
                const message = data.meta && data.meta.message ? String(data.meta.message) : 'Working…';
                setJobStatus(message);
            }
            return data;
        } catch (err) {
            setJobStatus('Could not refresh site health status.', 'error');
            return null;
        }
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function beginPolling() {
        stopPolling();
        pollTimer = setInterval(async () => {
            const data = await refreshStatus();
            if (data && !data.running) {
                stopPolling();
            }
        }, 1000);
    }

    async function startMode(mode, options) {
        const opts = options && typeof options === 'object' ? options : {};
        let csrfToken = '';
        if (typeof refreshAdminCsrfToken === 'function') {
            csrfToken = await refreshAdminCsrfToken();
        }
        setRunningUi(true);
        let starting = 'Starting…';
        if (mode === 'check') {
            starting = 'Starting quick health check…';
        } else if (mode === 'check_full') {
            starting = 'Starting full health check…';
        } else if (mode === 'treat') {
            starting = 'Starting treatment…';
        } else if (mode === 'force') {
            starting = 'Starting force rebuild…';
        }
        setJobStatus(starting);
        const body = { mode: mode, csrf_token: csrfToken };
        if (mode === 'treat') {
            body.treatment_ids = Array.isArray(opts.treatmentIds) ? opts.treatmentIds : [];
            body.finding_ids = Array.isArray(opts.findingIds) ? opts.findingIds : [];
        }
        try {
            const resp = await fetch('/biblioteca/site-health-run.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await resp.json();
            if (!resp.ok || !data || data.ok !== true) {
                setRunningUi(false);
                setJobStatus((data && data.error) ? data.error : 'Could not start site health.', 'error');
                return;
            }
            if (mode === 'treat') {
                setPreviewMode(false);
            }
            beginPolling();
            await refreshStatus();
        } catch (err) {
            setRunningUi(false);
            setJobStatus('Could not start site health.', 'error');
        }
    }

    window.bandpromoStartSiteHealthQuickCheck = function bandpromoStartSiteHealthQuickCheck() {
        if (running) {
            return 'already-running';
        }
        if (!checkBtn || checkBtn.disabled || checkBtn.hidden) {
            return 'unavailable';
        }
        checkBtn.click();
        return 'started';
    };

    function shouldAutoStartQuickCheck() {
        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('run_recommended') === '1') {
                return true;
            }
            if (sessionStorage.getItem('bandpromo_run_site_health_check') === '1') {
                return true;
            }
            if (sessionStorage.getItem('bandpromo_post_package_update')) {
                return true;
            }
        } catch (error) {
            // Ignore storage failures.
        }
        return false;
    }

    async function maybeAutoStartAfterUpdate() {
        if (!shouldAutoStartQuickCheck()) {
            return;
        }
        // Wait for the first status paint so we do not fight a busy lock.
        await refreshStatus();
        if (running) {
            return;
        }
        let attempts = 0;
        while (attempts < 8) {
            attempts += 1;
            const result = window.bandpromoStartSiteHealthQuickCheck();
            if (result === 'started' || result === 'already-running') {
                try {
                    sessionStorage.removeItem('bandpromo_run_site_health_check');
                } catch (error) {
                    // Ignore.
                }
                if (typeof window.closeOperatorNotifications === 'function') {
                    window.closeOperatorNotifications();
                }
                return;
            }
            await new Promise((resolve) => setTimeout(resolve, 250));
        }
    }

    checkBtn.addEventListener('click', () => {
        setPreviewMode(false);
        startMode('check');
    });

    if (checkFullBtn) {
        checkFullBtn.addEventListener('click', () => {
            setPreviewMode(false);
            startMode('check_full');
        });
    }

    if (treatBtn) {
        treatBtn.addEventListener('click', () => {
            if (!lastPlan || !Array.isArray(lastPlan.findings) || !lastPlan.findings.length) {
                setJobStatus('Run Quick or Full health check first.', 'error');
                return;
            }
            if (isPlanStale(lastPlan)) {
                setPreviewMode(false);
                setJobStatus(staleCheckMessage(), 'attention');
                syncRecommendedAction();
                return;
            }
            setPreviewMode(true);
            setJobStatus('');
        });
    }

    if (treatCancelBtn) {
        treatCancelBtn.addEventListener('click', () => {
            setPreviewMode(false);
            setJobStatus('');
        });
    }

    if (treatApplyBtn) {
        treatApplyBtn.addEventListener('click', () => {
            if (isPlanStale(lastPlan)) {
                setPreviewMode(false);
                setJobStatus(staleCheckMessage(), 'attention');
                syncRecommendedAction();
                return;
            }
            const selected = selectedTreatmentsFromPreview();
            if (!selected.treatmentIds.length) {
                setJobStatus('Select at least one finding to apply.', 'error');
                return;
            }
            const summary = selected.labels.length
                ? selected.labels.map((line) => '- ' + line).join('\n')
                : '- (none)';
            if (!window.confirm(
                'Apply the selected treatment now?\n\n' +
                summary +
                '\n\nOnly the ticked items will be fixed. Unticked items stay for later.'
            )) {
                return;
            }
            startMode('treat', {
                treatmentIds: selected.treatmentIds,
                findingIds: selected.findingIds,
            });
        });
    }

    forceBtn.addEventListener('click', () => {
        if (!window.confirm('Force a full listener rebuild even if Check looks healthy?\n\nThis is blocked while critical catalogue findings remain. Prefer Check → Review → Apply for missing Files rows.')) {
            return;
        }
        setPreviewMode(false);
        startMode('force');
    });

    if (stopBtn) {
        stopBtn.addEventListener('click', async () => {
            let csrfToken = '';
            if (typeof refreshAdminCsrfToken === 'function') {
                csrfToken = await refreshAdminCsrfToken();
            }
            try {
                const resp = await fetch('/biblioteca/request-job-stop.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ job: 'site_health', csrf_token: csrfToken }),
                });
                const data = await resp.json();
                if (!resp.ok || !data || data.ok !== true) {
                    setJobStatus((data && data.error) ? data.error : 'Could not request stop.', 'error');
                    return;
                }
                setJobStatus('Stop requested — finishing the current step, then exiting.');
            } catch (err) {
                setJobStatus('Could not request stop.', 'error');
            }
        });
    }

    if (copyBtn && logEl) {
        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(logEl.textContent || '');
                setJobStatus('Log copied.', 'success');
            } catch (err) {
                setJobStatus('Could not copy log.', 'error');
            }
        });
    }

    // Status link inside Activity <summary> must not toggle the details open/closed.
    document.querySelectorAll('#site-health-log-card summary .content-editor-breadcrumb-link').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    });

    refreshStatus();
    maybeAutoStartAfterUpdate();
})();
