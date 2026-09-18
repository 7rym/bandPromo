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
    const resultEl = document.getElementById('siteHealthTreatResult');
    const resultBodyEl = document.getElementById('siteHealthTreatResultBody');
    const statusHomeEl = document.getElementById('siteHealthStatusHome');
    const hubEl = document.getElementById('siteHealthHub');
    const hubResumeEl = document.getElementById('siteHealthHubResume');
    const enterHubBtn = document.getElementById('siteHealthEnterHubBtn');
    const hubSlotQuick = document.getElementById('siteHealthHubSlotQuick');
    const hubSlotFull = document.getElementById('siteHealthHubSlotFull');
    const hubSlotForce = document.getElementById('siteHealthHubSlotForce');
    const checkActionsMount = document.getElementById('siteHealthCheckActionsMount');
    const actionsEl = document.getElementById('siteHealthActions');
    const metaEl = document.getElementById('siteHealthMeta');
    const logEl = document.getElementById('siteHealthLog');
    const spinnerEl = document.getElementById('siteHealthSpinner');
    const breadcrumbStepsEl = document.getElementById('siteHealthBreadcrumbSteps');
    const crumbStatusLink = document.getElementById('siteHealthCrumbStatus');
    const crumbSepEl = document.getElementById('siteHealthCrumbSep');
    const crumbHomeBtn = document.getElementById('siteHealthCrumbHome');
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

    const EXAM_LABEL_KEY = 'bandpromo_site_health_exam_label';
    /** @type {'status'|'hub'|'exam'|'review'|'result'} */
    let uiStage = 'status';
    let examLabel = 'Quick check';
    try {
        const storedExam = sessionStorage.getItem(EXAM_LABEL_KEY);
        if (storedExam) {
            examLabel = storedExam;
        }
    } catch (err) {
        // Ignore storage failures.
    }

    const TREATMENT_COPY = {
        audio_register_in_place: 'Register audio masters already on disk into Files (no copy).',
        visual_register_in_place: 'Add these files to Files so you can use them (no copy, no re-upload).',
        sfx_register_in_place: 'Register sound-effect masters already on disk into Files (no copy).',
        listener_delivery: 'Rebuild missing or stale stream deliverables (audio, stills, video, SFX).',
        audio_fill_display_from_tags: 'Fill empty track title/artist from master tags.',
        audio_extract_covers: 'Extract embedded artwork and link track covers.',
        media_janitor_prune: 'Clear the selected leftovers. Your originals and masters stay safe.',
        data_janitor_prune: 'Clear leftover files under data/ (upload scratch, OS junk, empty folders).',
        data_container_relink: 'Register invisible containers that already have a campaign home, and drop registry stubs.',
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
    /** True while a start request is in flight — status refresh must not clear local busy. */
    let startInFlight = false;
    let lastPlan = null;
    let staleAutoCheckStarted = false;
    let previewOpen = false;
    /** Last known overall key for the badge (healthy / attention / …). */
    let lastOverallKey = 'unknown';
    /** @type {{labels: string[], treats: Array<{id: string, status: string, count: number}>, notes: string[], overall: string}|null} */
    let lastTreatReport = null;

    function saveExamLabel(label) {
        const next = String(label || '').trim() || 'Quick check';
        examLabel = next;
        try {
            sessionStorage.setItem(EXAM_LABEL_KEY, next);
        } catch (err) {
            // Ignore storage failures.
        }
    }

    function examLabelFromMode(modeKey) {
        return ({
            check: 'Quick check',
            check_full: 'Full check',
            force: 'Force rebuild',
        })[String(modeKey || '').trim().toLowerCase()] || '';
    }

    function setUiStage(stage) {
        const next = String(stage || '').trim();
        if (next === 'status' || next === 'hub' || next === 'exam' || next === 'review' || next === 'result') {
            uiStage = next;
        }
        syncBreadcrumb();
        syncStagePanels();
    }

    function stripLogStamp(line) {
        return String(line || '').replace(/^\[[^\]]+\]\s*/, '').trim();
    }

    function treatmentLabel(treatmentId) {
        const id = String(treatmentId || '').trim();
        if (!id) {
            return '';
        }
        if (TREATMENT_COPY[id]) {
            return TREATMENT_COPY[id];
        }
        if (id === 'orphan_home_stamp') {
            return 'Stamp catalogue homes for orphan media used in campaigns.';
        }
        return id.replace(/_/g, ' ');
    }

    function parseTreatReportFromLog(logText) {
        const lines = String(logText || '').split(/\r?\n/);
        const treats = [];
        const notes = [];
        let sawTreat = false;
        for (let i = 0; i < lines.length; i += 1) {
            const raw = lines[i];
            const line = stripLogStamp(raw);
            if (/Site health Treat|treat:orphan_homes|^treat\b|Applying \d+ selected treatment/i.test(line)) {
                sawTreat = true;
            }
            const machine = line.match(/^HEALTH_TREAT:([^:]+):([^:]+):(-?\d+)\s*$/);
            if (machine) {
                sawTreat = true;
                treats.push({
                    id: machine[1],
                    status: machine[2],
                    count: Number(machine[3]) || 0,
                });
                continue;
            }
            if (!sawTreat) {
                continue;
            }
            if (/^followup\b|^Follow-up\b|Starting follow-up|HEALTH_RESULT:/i.test(line)) {
                // Keep collecting HEALTH_TREAT that may appear just before follow-up.
                continue;
            }
            if (
                /Wrote catalogue homes|Registered |Stamping catalogue|Cleared |Retarget|No unambiguous orphan|Treatment complete|Apply selected/i.test(line)
                || /^  - /.test(line)
            ) {
                notes.push(line);
            }
        }
        // Prefer the last run of each treatment id.
        const byId = {};
        treats.forEach((row) => {
            byId[row.id] = row;
        });
        return {
            treats: Object.keys(byId).map((id) => byId[id]),
            notes: notes.slice(-24),
        };
    }

    function remainingFindingsListHtml() {
        const findings = Array.isArray(lastPlan && lastPlan.findings) ? lastPlan.findings : [];
        if (!findings.length) {
            return '';
        }
        return (
            '<p class="site-health-treat-detail-label">Still needs attention</p>' +
            '<ul class="site-health-treat-result-list">' +
            findings.map((finding) => {
                const title = String((finding && (finding.title || finding.id)) || 'Finding').trim();
                const count = Number((finding && finding.count) || 0);
                const body = String((finding && finding.body) || '').trim();
                return (
                    '<li><strong>' + escapeHtml(title) +
                    (count ? ' (' + count + ')' : '') +
                    '</strong>' +
                    (body ? '<br><span class="site-health-treat-result-finding-body">' + escapeHtml(body) + '</span>' : '') +
                    '</li>'
                );
            }).join('') +
            '</ul>'
        );
    }

    function parseHealthOutcomeFromLog(logText) {
        const lines = String(logText || '').split(/\r?\n/);
        let outcome = '';
        for (let i = 0; i < lines.length; i += 1) {
            const line = stripLogStamp(lines[i]);
            const match = line.match(/^HEALTH_RESULT:(\S+)/);
            if (match) {
                outcome = match[1];
            }
        }
        return outcome;
    }

    function renderTreatResultPanel(overall) {
        if (!resultBodyEl) {
            return;
        }
        const logText = logEl ? String(logEl.textContent || '') : '';
        const parsed = parseTreatReportFromLog(logText);
        const logOutcome = parseHealthOutcomeFromLog(logText);
        const labels = (lastTreatReport && Array.isArray(lastTreatReport.labels))
            ? lastTreatReport.labels
            : [];
        const ov = String(overall || (lastTreatReport && lastTreatReport.overall) || '').toLowerCase();
        lastTreatReport = {
            labels: labels,
            treats: parsed.treats,
            notes: parsed.notes,
            overall: ov,
            logOutcome: logOutcome,
        };

        const requested = labels.length
            ? ('<p class="site-health-treat-detail-label">Requested</p>' +
                '<ul class="site-health-treat-result-list">' +
                labels.map((n) => '<li>' + escapeHtml(n) + '</li>').join('') +
                '</ul>')
            : '';

        const statusRows = parsed.treats.map((row) => {
            const label = treatmentLabel(row.id);
            const status = String(row.status || '').toLowerCase();
            let outcome = 'done';
            if (status === 'failed') {
                outcome = 'failed';
            } else if (status === 'partial') {
                outcome = 'partial';
            } else if (status === 'ok' && row.count === 0) {
                outcome = 'nothing to do';
            } else if (status === 'ok') {
                outcome = row.count === 1 ? '1 item' : (row.count + ' items');
            }
            return '<li><strong>' + escapeHtml(label) + '</strong> — ' + escapeHtml(outcome) + '</li>';
        });

        const applied = statusRows.length
            ? ('<p class="site-health-treat-detail-label">Applied</p>' +
                '<ul class="site-health-treat-result-list">' + statusRows.join('') + '</ul>')
            : '';

        const noteRows = parsed.notes.length
            ? ('<p class="site-health-treat-detail-label">From Activity</p>' +
                '<ul class="site-health-treat-result-list">' +
                parsed.notes.map((n) => '<li><code>' + escapeHtml(n) + '</code></li>').join('') +
                '</ul>')
            : '';

        let bodyLead = '';
        let summary = '';
        let continueLabel = 'Continue';
        let continueAction = 'exam';
        let panelTone = 'ok';

        if (running) {
            panelTone = 'running';
            bodyLead = (
                '<p class="site-health-treat-assurance">' +
                'Treatment is still running. Activity below updates as each step finishes.' +
                '</p>'
            );
            summary = '';
        } else {
            const findings = Array.isArray(lastPlan && lastPlan.findings) ? lastPlan.findings : [];
            const failedVerify = /verify_failed|failed/i.test(logOutcome)
                || ov === 'attention'
                || ov === 'critical'
                || findings.length > 0;

            if (failedVerify) {
                panelTone = 'caution';
                summary = (
                    '<p class="site-health-treat-assurance site-health-treat-assurance--caution">' +
                    '<strong>Summary:</strong> Treatment finished, but follow-up still needs attention.' +
                    '</p>' +
                    remainingFindingsListHtml() +
                    '<p class="site-health-treat-result-note">' +
                    'Use Continue to open the remaining findings and Apply again if needed.' +
                    '</p>'
                );
                continueLabel = findings.length ? 'Continue to remaining findings' : 'Continue';
                continueAction = findings.length ? 'review' : 'exam';
            } else if (ov === 'healthy' || logOutcome === 'healthy' || logOutcome === 'ok') {
                panelTone = 'ok';
                summary = (
                    '<p class="site-health-treat-assurance">' +
                    '<strong>Summary:</strong> Treatment finished — follow-up check looks healthy.' +
                    '</p>'
                );
                continueLabel = 'Continue';
                continueAction = 'exam';
            } else {
                panelTone = 'ok';
                summary = (
                    '<p class="site-health-treat-assurance">' +
                    '<strong>Summary:</strong> Treatment finished. Check Activity for the full story.' +
                    '</p>'
                );
                continueLabel = 'Continue';
                continueAction = 'exam';
            }
        }

        if (resultEl) {
            resultEl.classList.toggle('is-running', panelTone === 'running');
            resultEl.classList.toggle('is-caution', panelTone === 'caution');
        }

        const continueHtml = running
            ? ''
            : (
                '<div class="site-health-treat-result-actions">' +
                '<button type="button" class="btn btn-good" id="siteHealthTreatContinueBtn" ' +
                'data-continue-action="' + escapeHtml(continueAction) + '">' +
                escapeHtml(continueLabel) +
                '</button>' +
                '</div>'
            );

        resultBodyEl.innerHTML = (
            '<h3 class="site-health-treat-result-title">Treatment result</h3>' +
            bodyLead +
            requested +
            applied +
            noteRows +
            summary +
            continueHtml
        );

        const continueBtn = document.getElementById('siteHealthTreatContinueBtn');
        if (continueBtn) {
            continueBtn.addEventListener('click', () => {
                const action = String(continueBtn.getAttribute('data-continue-action') || 'exam');
                if (action === 'review') {
                    goBreadcrumb('review');
                    return;
                }
                goBreadcrumb('exam');
            });
        }
    }

    function syncHubResume() {
        if (!hubResumeEl) {
            return;
        }
        const plan = lastPlan;
        if (!plan || !plan.checked_at || isPlanStale(plan)) {
            hubResumeEl.hidden = true;
            hubResumeEl.innerHTML = '';
            return;
        }
        const overall = String(plan.overall || '').toLowerCase();
        const findings = Array.isArray(plan.findings) ? plan.findings.length : 0;
        let label = 'Open last results';
        let detail = 'Last check: ' + String(plan.checked_at) + ' UTC';
        if (overall === 'healthy' && findings === 0) {
            detail += ' — looked healthy.';
        } else if (findings > 0) {
            detail += ' — ' + findings + ' finding' + (findings === 1 ? '' : 's') + ' still open.';
            label = 'Open last results';
        }
        hubResumeEl.hidden = false;
        hubResumeEl.innerHTML = (
            '<span class="site-health-hub-resume-text">' + escapeHtml(detail) + '</span> ' +
            '<button type="button" class="btn btn-sm" id="siteHealthOpenLastResultsBtn">' +
            escapeHtml(label) +
            '</button>'
        );
        const openBtn = document.getElementById('siteHealthOpenLastResultsBtn');
        if (openBtn) {
            openBtn.addEventListener('click', () => {
                goBreadcrumb('exam');
            });
        }
    }

    function placeCheckActionButtons(where) {
        const target = String(where || '') === 'hub' ? 'hub' : 'toolbar';
        if (target === 'hub') {
            if (hubSlotQuick && checkBtn) {
                hubSlotQuick.appendChild(checkBtn);
            }
            if (hubSlotFull && checkFullBtn) {
                hubSlotFull.appendChild(checkFullBtn);
            }
            if (hubSlotForce && forceBtn) {
                hubSlotForce.appendChild(forceBtn);
            }
            return;
        }
        if (!checkActionsMount) {
            return;
        }
        if (checkBtn) {
            checkActionsMount.appendChild(checkBtn);
        }
        if (checkFullBtn) {
            checkActionsMount.appendChild(checkFullBtn);
        }
        if (forceBtn) {
            checkActionsMount.appendChild(forceBtn);
        }
    }

    function syncStagePanels() {
        const showStatus = uiStage === 'status';
        const showHub = uiStage === 'hub';
        const showExam = uiStage === 'exam';
        const showReview = uiStage === 'review';
        const showResult = uiStage === 'result';

        if (statusHomeEl) {
            statusHomeEl.hidden = !showStatus;
        }
        if (hubEl) {
            hubEl.hidden = !showHub;
            if (showHub) {
                syncHubResume();
            }
        }

        if (metaEl) {
            // Hub / Status speak for themselves; exam keeps last-check meta from renderPlan.
            metaEl.hidden = showStatus || showHub || showReview || showResult;
        }

        if (!showExam) {
            if (summaryEl) {
                summaryEl.hidden = true;
            }
            if (findingsEl) {
                findingsEl.hidden = true;
            }
        } else {
            if (summaryEl && String(summaryEl.innerHTML || '').trim() !== '') {
                summaryEl.hidden = false;
            }
            if (findingsEl) {
                const summaryVisible = summaryEl && !summaryEl.hidden
                    && !!summaryEl.querySelector('.site-health-summary-panel');
                if (summaryVisible) {
                    findingsEl.hidden = true;
                } else if (String(findingsEl.innerHTML || '').trim() !== '') {
                    findingsEl.hidden = false;
                }
            }
        }

        // Hub hosts the check buttons inside the guide panels; exam uses the toolbar.
        if (showHub && !running) {
            placeCheckActionButtons('hub');
        } else {
            placeCheckActionButtons('toolbar');
        }

        const showExamToolbar = (showExam || running) && !showReview && !showResult;
        const hideCheckButtons = (!showHub && !showExamToolbar) || running;
        if (checkBtn) {
            checkBtn.hidden = hideCheckButtons;
        }
        if (checkFullBtn) {
            checkFullBtn.hidden = hideCheckButtons;
        }
        if (forceBtn) {
            forceBtn.hidden = hideCheckButtons;
        }
        if (treatBtn) {
            // Review only on exam results, never on the Site health hub alone.
            if (!showExam || running) {
                treatBtn.hidden = true;
            }
        }
        if (actionsEl) {
            if (running) {
                actionsEl.hidden = false;
            } else if (showHub) {
                // Check actions live in the hub panels; no duplicate toolbar row.
                actionsEl.hidden = true;
            } else {
                actionsEl.hidden = !showExamToolbar;
            }
        }
        if (previewEl) {
            previewEl.hidden = !showReview;
        }
        if (resultEl) {
            resultEl.hidden = !showResult;
            if (showResult) {
                renderTreatResultPanel(lastTreatReport && lastTreatReport.overall);
            }
        }

        // After button visibility/placement settles — keeps one green recommended step.
        if (!running) {
            syncRecommendedAction();
        }
        paintOverallBadge();
    }

    function crumbSepHtml() {
        return '<span class="content-editor-breadcrumb-sep" aria-hidden="true"> &gt; </span>';
    }

    function crumbCurrentHtml(label) {
        return '<span class="content-editor-breadcrumb-current">' + escapeHtml(label) + '</span>';
    }

    function crumbButtonHtml(action, label, title) {
        return (
            '<button type="button" class="content-editor-breadcrumb-link" ' +
            'data-site-health-crumb="' + escapeHtml(action) + '" ' +
            'title="' + escapeHtml(title || label) + '">' +
            escapeHtml(label) +
            '</button>'
        );
    }

    function syncBreadcrumb() {
        if (!breadcrumbStepsEl) {
            return;
        }

        if (crumbSepEl) {
            crumbSepEl.hidden = uiStage === 'status';
        }
        if (crumbHomeBtn) {
            crumbHomeBtn.hidden = uiStage === 'status' || uiStage === 'hub';
        }

        if (uiStage === 'status') {
            breadcrumbStepsEl.innerHTML = '';
            return;
        }

        if (uiStage === 'hub') {
            breadcrumbStepsEl.innerHTML = crumbCurrentHtml('Site health');
            return;
        }

        // exam / review / result — Site health button is visible; steps continue after it.
        const parts = [crumbSepHtml()];
        if (uiStage === 'exam') {
            parts.push(crumbCurrentHtml(examLabel));
        } else {
            parts.push(crumbButtonHtml('exam', examLabel, 'Back to ' + examLabel + ' results'));
            parts.push(crumbSepHtml());
            if (uiStage === 'review') {
                parts.push(crumbCurrentHtml('Proposed treatment'));
            } else {
                parts.push(crumbButtonHtml('review', 'Proposed treatment', 'Back to proposed treatment'));
                parts.push(crumbSepHtml());
                parts.push(crumbCurrentHtml('Treatment result'));
            }
        }
        breadcrumbStepsEl.innerHTML = parts.join('');
    }

    function goBreadcrumb(action) {
        if (action === 'status') {
            setPreviewMode(false);
            setUiStage('status');
            setJobStatus('');
            return;
        }
        if (action === 'home' || action === 'hub') {
            setPreviewMode(false);
            setUiStage('hub');
            setJobStatus('');
            return;
        }
        if (action === 'exam') {
            setPreviewMode(false);
            setUiStage('exam');
            if (lastPlan) {
                renderPlan(lastPlan);
            }
            setJobStatus('');
            return;
        }
        if (action === 'review') {
            if (!lastPlan || !Array.isArray(lastPlan.findings) || !lastPlan.findings.length) {
                setUiStage('exam');
                if (lastPlan) {
                    renderPlan(lastPlan);
                }
                return;
            }
            if (isPlanStale(lastPlan)) {
                setPreviewMode(false);
                setUiStage('exam');
                if (lastPlan) {
                    renderPlan(lastPlan);
                }
                setJobStatus(staleCheckMessage(), 'attention');
                syncRecommendedAction();
                return;
            }
            setPreviewMode(true);
            setJobStatus('');
        }
    }

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
        // Hard errors use the shared in-app confirm (acknowledge), not window.alert.
        const message = String(text || '').trim();
        if (!message) {
            return;
        }
        if (tone === 'error') {
            if (typeof window.bandpromoConfirm === 'function') {
                window.bandpromoConfirm({
                    title: 'Site health',
                    body: message,
                    confirmLabel: 'OK',
                    cancelLabel: 'Close',
                });
            } else {
                window.alert(message);
            }
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
        lastOverallKey = String(overall || 'unknown').toLowerCase();
        paintOverallBadge();
    }

    function paintOverallBadge() {
        if (!overallEl) {
            return;
        }
        const key = String(lastOverallKey || 'unknown').toLowerCase();
        const onChooser = uiStage === 'status' || uiStage === 'hub';
        let label = 'Not checked yet';
        let cls = 'badge audit-status-badge status-neutral';
        if (key === 'running') {
            label = 'Working…';
            cls = 'badge audit-status-badge status-neutral';
        } else if (key === 'outdated' || key === 'stale') {
            label = 'Out of date';
            cls = 'badge audit-status-badge status-warning';
        } else if (key === 'attention') {
            label = 'Needs attention';
            cls = 'badge audit-status-badge status-warning';
        } else if (key === 'critical') {
            label = 'Critical';
            cls = 'badge audit-status-badge status-error';
        } else if (key === 'healthy') {
            if (onChooser) {
                // Historical result from the last finished plan — not a live clean bill
                // of health before the operator runs a check from this desk.
                label = 'Last check healthy';
                cls = 'badge audit-status-badge status-neutral';
            } else {
                label = 'Healthy';
                cls = 'badge audit-status-badge status-ok';
            }
        }
        overallEl.className = cls;
        overallEl.textContent = label;
        overallEl.title = onChooser && key === 'healthy'
            ? 'Based on the last finished health check — run Quick or Full to verify again.'
            : '';
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
        const findings = Array.isArray(plan && plan.findings) ? plan.findings : [];
        const hasOrphanClash = findings.some(
            (f) => String((f && f.id) || '') === 'orphan_assets_multi_campaign'
        );
        const hasDataOrphans = findings.some(
            (f) => String((f && f.id) || '') === 'data_container_orphans'
        );
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
                '</strong> need a choice below (Site health will not guess).' +
                '</p>'
            );
        }
        if (fix.manual > 0 && fix.fixable === 0) {
            if (hasOrphanClash || hasDataOrphans) {
                return (
                    '<p class="site-health-treat-assurance site-health-treat-assurance--caution">' +
                    'These need your choice on each row — select a campaign, then Adopt or Set. ' +
                    'Nothing is auto-fixed by Apply.' +
                    '</p>'
                );
            }
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

    function orphanClashPreviewHtml(kind, assetId, filename) {
        const id = String(assetId || '').trim();
        const name = String(filename || '').trim();
        const kindNorm = String(kind || '').trim().toLowerCase();
        if (kindNorm === 'visual' && id) {
            return (
                '<div class="site-health-orphan-clash-preview site-health-orphan-clash-preview--visual">' +
                '<img class="site-health-orphan-clash-thumb" ' +
                'src="/media/visual/delivery/' + encodeURIComponent(id) + '/card.jpg" ' +
                'alt="" loading="lazy" decoding="async" ' +
                'onerror="this.closest(\'.site-health-orphan-clash-preview\').hidden=true;">' +
                '</div>'
            );
        }
        if (kindNorm === 'audio' && name) {
            const params = new URLSearchParams();
            params.set('file', name);
            params.set('variant', 'master');
            return (
                '<div class="site-health-orphan-clash-preview site-health-orphan-clash-preview--audio">' +
                '<audio class="site-health-orphan-clash-audio" controls preload="none" ' +
                'src="/biblioteca/audio.php?' + params.toString() + '"></audio>' +
                '</div>'
            );
        }
        return '';
    }

    function orphanClashRowHtml(item) {
        if (!item || typeof item !== 'object') {
            const text = String(item || '').trim();
            return text
                ? '<li class="site-health-orphan-clash"><code>' + escapeHtml(text) + '</code></li>'
                : '';
        }
        const assetId = String(item.asset_id || '').trim();
        const kind = String(item.kind || 'media').trim().toLowerCase() || 'media';
        const filename = String(item.filename || assetId).trim() || assetId;
        const campaigns = Array.isArray(item.campaigns) ? item.campaigns : [];
        const containers = Array.isArray(item.containers) ? item.containers : [];
        const usedIn = containers.length
            ? containers.slice(0, 8).map((c) => escapeHtml(String(c))).join(', ')
                + (containers.length > 8 ? ', …' : '')
            : '—';
        const previewHtml = orphanClashPreviewHtml(kind, assetId, filename);

        let choiceHtml = '';
        if (campaigns.length > 5) {
            choiceHtml = (
                '<label class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select catalogue home:</span> ' +
                '<select class="site-health-orphan-home-select" data-asset-id="' +
                escapeHtml(assetId) + '">' +
                '<option value="">Choose…</option>' +
                campaigns.map((c) => {
                    const id = String((c && c.id) || '').trim();
                    const title = String((c && (c.title || c.id)) || id).trim() || id;
                    return (
                        '<option value="' + escapeHtml(id) + '">' +
                        escapeHtml(title) +
                        '</option>'
                    );
                }).join('') +
                '</select>' +
                '</label>'
            );
        } else {
            choiceHtml = (
                '<div class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select catalogue home:</span> ' +
                '<div class="brand-player-setting-toggle site-health-orphan-home-toggle" ' +
                'role="group" aria-label="Select catalogue home for ' + escapeHtml(filename) + '" ' +
                'data-asset-id="' + escapeHtml(assetId) + '">' +
                campaigns.map((c) => {
                    const id = String((c && c.id) || '').trim();
                    const title = String((c && (c.title || c.id)) || id).trim() || id;
                    return (
                        '<button type="button" class="brand-player-setting-btn" ' +
                        'data-campaign-id="' + escapeHtml(id) + '" ' +
                        'data-campaign-title="' + escapeHtml(title) + '" ' +
                        'aria-pressed="false">' +
                        escapeHtml(title) +
                        '</button>'
                    );
                }).join('') +
                '</div></div>'
            );
        }

        return (
            '<li class="site-health-orphan-clash" data-asset-id="' + escapeHtml(assetId) + '">' +
            '<div class="site-health-orphan-clash-head">' +
            previewHtml +
            '<div class="site-health-orphan-clash-meta">' +
            '<p class="site-health-orphan-clash-file"><strong>' + escapeHtml(filename) +
            '</strong> <span class="site-health-orphan-clash-kind">(' + escapeHtml(kind) +
            ')</span></p>' +
            '<p class="site-health-orphan-clash-used">Used in: ' + usedIn + '</p>' +
            '</div></div>' +
            choiceHtml +
            '<div class="site-health-orphan-clash-actions">' +
            '<button type="button" class="btn btn-good btn-sm site-health-orphan-home-set" ' +
            'data-asset-id="' + escapeHtml(assetId) + '" ' +
            'data-campaign-id="" data-campaign-title="" hidden disabled>' +
            'Set catalogue home' +
            '</button>' +
            '</div>' +
            '</li>'
        );
    }

    function dataContainerClassLabel(className) {
        const key = String(className || '').trim().toLowerCase();
        if (key === 'invisible') {
            return 'Not in registry';
        }
        if (key === 'unowned') {
            return 'No campaign home';
        }
        if (key === 'dangling') {
            return 'Campaign missing';
        }
        return key || 'Orphan';
    }

    function dataContainerRowHtml(item) {
        if (!item || typeof item !== 'object') {
            const text = String(item || '').trim();
            return text
                ? '<li class="site-health-data-orphan"><code>' + escapeHtml(text) + '</code></li>'
                : '';
        }
        const kind = String(item.kind || '').trim().toLowerCase() || 'container';
        const entityId = String(item.id || '').trim();
        const title = String(item.title || entityId).trim() || entityId;
        const path = String(item.path || '').trim();
        const className = String(item.class || '').trim().toLowerCase();
        const campaigns = Array.isArray(item.campaigns) ? item.campaigns : [];
        const allowDelete = item.allow_delete !== false;
        const wasHome = String(item.campaign_title || item.campaign_id || '').trim();

        let choiceHtml = '';
        if (campaigns.length > 5) {
            choiceHtml = (
                '<label class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select campaign:</span> ' +
                '<select class="site-health-data-adopt-select" data-entity-id="' +
                escapeHtml(entityId) + '" data-kind="' + escapeHtml(kind) + '">' +
                '<option value="">Choose…</option>' +
                campaigns.map((c) => {
                    const id = String((c && c.id) || '').trim();
                    const ctitle = String((c && (c.title || c.id)) || id).trim() || id;
                    return (
                        '<option value="' + escapeHtml(id) + '">' +
                        escapeHtml(ctitle) +
                        '</option>'
                    );
                }).join('') +
                '</select>' +
                '</label>'
            );
        } else if (campaigns.length) {
            choiceHtml = (
                '<div class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select campaign:</span> ' +
                '<div class="brand-player-setting-toggle site-health-data-adopt-toggle" ' +
                'role="group" aria-label="Select campaign for ' + escapeHtml(title) + '" ' +
                'data-entity-id="' + escapeHtml(entityId) + '" data-kind="' + escapeHtml(kind) + '">' +
                campaigns.map((c) => {
                    const id = String((c && c.id) || '').trim();
                    const ctitle = String((c && (c.title || c.id)) || id).trim() || id;
                    return (
                        '<button type="button" class="brand-player-setting-btn" ' +
                        'data-campaign-id="' + escapeHtml(id) + '" ' +
                        'data-campaign-title="' + escapeHtml(ctitle) + '" ' +
                        'aria-pressed="false">' +
                        escapeHtml(ctitle) +
                        '</button>'
                    );
                }).join('') +
                '</div></div>'
            );
        } else {
            choiceHtml = (
                '<p class="site-health-orphan-clash-used">No campaigns available to Adopt into.</p>'
            );
        }

        return (
            '<li class="site-health-data-orphan site-health-orphan-clash" ' +
            'data-kind="' + escapeHtml(kind) + '" data-entity-id="' + escapeHtml(entityId) + '">' +
            '<p class="site-health-orphan-clash-file"><strong>' + escapeHtml(title) +
            '</strong> <span class="site-health-orphan-clash-kind">(' + escapeHtml(kind) +
            ')</span>' +
            '<span class="site-health-orphan-clash-badge">' +
            escapeHtml(dataContainerClassLabel(className)) +
            '</span></p>' +
            '<p class="site-health-orphan-clash-used">' +
            escapeHtml(path || entityId) +
            (wasHome ? ' — was: ' + escapeHtml(wasHome) : '') +
            '</p>' +
            choiceHtml +
            '<div class="site-health-data-orphan-actions">' +
            '<button type="button" class="btn btn-good btn-sm site-health-data-adopt" ' +
            'data-kind="' + escapeHtml(kind) + '" data-entity-id="' + escapeHtml(entityId) + '" ' +
            'data-campaign-id="" data-campaign-title="" hidden disabled>' +
            'Adopt' +
            '</button>' +
            (allowDelete
                ? (
                    '<button type="button" class="btn btn-sm site-health-data-delete" ' +
                    'data-kind="' + escapeHtml(kind) + '" data-entity-id="' + escapeHtml(entityId) + '" ' +
                    'data-title="' + escapeHtml(title) + '">' +
                    'Delete…' +
                    '</button>'
                )
                : '') +
            '</div>' +
            '</li>'
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
        const isOrphanClash = findingId === 'orphan_assets_multi_campaign';
        const isDataOrphans = findingId === 'data_container_orphans';
        const treatmentLabel = canTreat
            ? escapeHtml(TREATMENT_COPY[treatmentId] || treatmentId)
            : (isOrphanClash
                ? 'Select a catalogue home on each clash row, then Set catalogue home.'
                : (isDataOrphans
                    ? 'Select a campaign on each row, then Adopt — or Delete leftovers.'
                    : 'Needs a manual step — Site health will not change this automatically.'));
        const sample = Array.isArray(finding && finding.items_sample)
            ? finding.items_sample
            : [];

        const foundBits = [];
        if (body) {
            foundBits.push('<p>' + body + '</p>');
        }
        if (isOrphanClash && sample.length) {
            const more = count > sample.length
                ? '<p class="site-health-treat-detail-more">Showing ' + sample.length +
                  ' of ' + count + ' — the rest is in Activity.</p>'
                : '';
            foundBits.push(
                '<p class="site-health-treat-detail-label">Clash detail</p>' +
                '<ul class="site-health-orphan-clash-list">' +
                sample.map(orphanClashRowHtml).join('') +
                '</ul>' +
                more
            );
        } else if (isDataOrphans && sample.length) {
            const more = count > sample.length
                ? '<p class="site-health-treat-detail-more">Showing ' + sample.length +
                  ' of ' + count + ' — the rest is in Activity.</p>'
                : '';
            foundBits.push(
                '<p class="site-health-treat-detail-label">Container detail</p>' +
                '<ul class="site-health-orphan-clash-list site-health-data-orphan-list">' +
                sample.map(dataContainerRowHtml).join('') +
                '</ul>' +
                more
            );
        } else if (sample.length) {
            const sampleItems = sample.map((item) => {
                if (item && typeof item === 'object') {
                    const label = String(item.filename || item.asset_id || item.text || '').trim();
                    return label
                        ? '<li><code>' + escapeHtml(label) + '</code></li>'
                        : '';
                }
                return '<li><code>' + escapeHtml(item) + '</code></li>';
            }).join('');
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
            (canTreat ? '' : ' is-manual') +
            (isOrphanClash || isDataOrphans ? ' is-orphan-clash' : '') + '" open>' +
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

    function selectedCampaignForOrphanRow(row) {
        if (!(row instanceof HTMLElement)) {
            return { id: '', title: '' };
        }
        const select = row.querySelector('.site-health-orphan-home-select');
        if (select instanceof HTMLSelectElement) {
            const id = String(select.value || '').trim();
            const opt = select.selectedOptions && select.selectedOptions[0]
                ? select.selectedOptions[0]
                : null;
            const title = opt ? String(opt.textContent || '').trim() : '';
            return { id: id, title: title || id };
        }
        const active = row.querySelector('.site-health-orphan-home-toggle .brand-player-setting-btn.is-active');
        if (active instanceof HTMLElement) {
            const id = String(active.getAttribute('data-campaign-id') || '').trim();
            const title = String(
                active.getAttribute('data-campaign-title') || active.textContent || ''
            ).trim();
            return { id: id, title: title || id };
        }
        return { id: '', title: '' };
    }

    function syncOrphanSetButton(row) {
        if (!(row instanceof HTMLElement)) {
            return;
        }
        const btn = row.querySelector('.site-health-orphan-home-set');
        if (!(btn instanceof HTMLButtonElement)) {
            return;
        }
        const field = row.querySelector('.site-health-orphan-clash-field');
        const label = row.querySelector('.site-health-orphan-clash-field-label');
        const selected = selectedCampaignForOrphanRow(row);
        const campaignId = selected.id;
        const campaignTitle = selected.title;
        btn.setAttribute('data-campaign-id', campaignId);
        btn.setAttribute('data-campaign-title', campaignTitle);
        if (campaignId === '') {
            btn.hidden = true;
            btn.disabled = true;
            btn.classList.add('btn-good');
            btn.textContent = 'Set catalogue home';
            if (field) {
                field.classList.add('is-needs-choice');
            }
            if (label) {
                label.textContent = 'Select catalogue home:';
            }
        } else {
            btn.hidden = false;
            btn.textContent = 'Set catalogue home';
            btn.classList.add('btn-good');
            btn.disabled = running;
            if (field) {
                field.classList.remove('is-needs-choice');
            }
            if (label) {
                label.textContent = 'Catalogue home:';
            }
        }
    }

    function removeOrphanClashFromPlan(assetId) {
        const id = String(assetId || '').trim();
        if (!id || !lastPlan || !Array.isArray(lastPlan.findings)) {
            return;
        }
        const nextFindings = [];
        lastPlan.findings.forEach((finding) => {
            if (String((finding && finding.id) || '') !== 'orphan_assets_multi_campaign') {
                nextFindings.push(finding);
                return;
            }
            const sample = Array.isArray(finding.items_sample) ? finding.items_sample : [];
            const kept = sample.filter((item) => {
                if (item && typeof item === 'object') {
                    return String(item.asset_id || '').trim() !== id;
                }
                return String(item || '').trim() !== id;
            });
            const prevCount = Number(finding.count) || sample.length;
            const nextCount = Math.max(0, prevCount - 1);
            if (nextCount === 0) {
                return;
            }
            nextFindings.push(Object.assign({}, finding, {
                items_sample: kept,
                count: nextCount,
                body: (
                    nextCount + ' file(s) are used by more than one campaign and have no catalogue home. ' +
                    'Choose which campaign should own each file below — Site health will not guess.'
                ),
            }));
        });
        lastPlan.findings = nextFindings;
        if (!nextFindings.length) {
            lastPlan.overall = 'healthy';
            setOverall('healthy');
        } else {
            const hasCritical = nextFindings.some(
                (f) => String((f && f.severity) || '').toLowerCase() === 'critical'
            );
            setOverall(hasCritical ? 'critical' : 'attention');
        }
    }

    async function assignOrphanHome(assetId, campaignId, filenameLabel, campaignTitle) {
        const asset = String(assetId || '').trim();
        const campaign = String(campaignId || '').trim();
        if (!asset || !campaign) {
            return false;
        }
        const label = String(filenameLabel || asset).trim() || asset;
        const homeTitle = String(campaignTitle || campaign).trim() || campaign;
        const confirmed = typeof window.bandpromoConfirm === 'function'
            ? await window.bandpromoConfirm({
                title: 'Set catalogue home?',
                body: (
                    'Assign “' + label + '” to “' + homeTitle + '” as its catalogue home?\n\n' +
                    'Site health will not change other campaigns’ containers — only the file’s home stamp.'
                ),
                confirmLabel: 'Set catalogue home',
                cancelLabel: 'Cancel',
            })
            : window.confirm(
                'Assign “' + label + '” to “' + homeTitle + '” as its catalogue home?'
            );
        if (!confirmed) {
            return false;
        }
        let csrfToken = '';
        if (typeof refreshAdminCsrfToken === 'function') {
            csrfToken = await refreshAdminCsrfToken();
        }
        try {
            const resp = await fetch('/biblioteca/site-health-assign-orphan-home.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    asset_id: asset,
                    campaign_id: campaign,
                    csrf_token: csrfToken,
                }),
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data || data.ok !== true) {
                setJobStatus(
                    (data && data.error) ? data.error : 'Could not set catalogue home.',
                    'error'
                );
                return false;
            }
            removeOrphanClashFromPlan(asset);
            if (previewOpen) {
                const stillClash = Array.isArray(lastPlan && lastPlan.findings)
                    && lastPlan.findings.some(
                        (f) => String((f && f.id) || '') === 'orphan_assets_multi_campaign'
                    );
                const stillFindings = Array.isArray(lastPlan && lastPlan.findings)
                    && lastPlan.findings.length > 0;
                if (!stillFindings) {
                    setPreviewMode(false);
                    setUiStage('exam');
                    if (lastPlan) {
                        renderPlan(lastPlan);
                    }
                } else if (!stillClash && findingsFixable(lastPlan).fixable === 0) {
                    setPreviewMode(true);
                } else {
                    setPreviewMode(true);
                }
            }
            if (typeof window.showAdminToast === 'function') {
                window.showAdminToast('Catalogue home set for ' + label + '.', 'success');
            }
            return true;
        } catch (err) {
            setJobStatus('Could not set catalogue home.', 'error');
            return false;
        }
    }

    function bindOrphanHomeHandlers() {
        if (!previewBodyEl) {
            return;
        }
        previewBodyEl.querySelectorAll('.site-health-orphan-clash:not(.site-health-data-orphan)').forEach((row) => {
            if (!(row instanceof HTMLElement)) {
                return;
            }
            syncOrphanSetButton(row);
            const toggle = row.querySelector('.site-health-orphan-home-toggle');
            if (toggle) {
                toggle.querySelectorAll('.brand-player-setting-btn').forEach((btn) => {
                    btn.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        toggle.querySelectorAll('.brand-player-setting-btn').forEach((other) => {
                            other.classList.remove('is-active');
                            other.setAttribute('aria-pressed', 'false');
                        });
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-pressed', 'true');
                        syncOrphanSetButton(row);
                    });
                });
            }
            const select = row.querySelector('.site-health-orphan-home-select');
            if (select instanceof HTMLSelectElement) {
                select.addEventListener('change', () => {
                    syncOrphanSetButton(row);
                });
                select.addEventListener('click', (event) => {
                    event.stopPropagation();
                });
            }
            const setBtn = row.querySelector('.site-health-orphan-home-set');
            if (setBtn instanceof HTMLButtonElement) {
                setBtn.addEventListener('click', async (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const selected = selectedCampaignForOrphanRow(row);
                    if (!selected.id) {
                        return;
                    }
                    const assetId = String(setBtn.getAttribute('data-asset-id') || '').trim();
                    const fileEl = row.querySelector('.site-health-orphan-clash-file strong');
                    const label = fileEl ? String(fileEl.textContent || '').trim() : assetId;
                    setBtn.disabled = true;
                    await assignOrphanHome(assetId, selected.id, label, selected.title);
                    if (previewBodyEl && previewBodyEl.contains(setBtn)) {
                        syncOrphanSetButton(row);
                    }
                });
            }
            const audio = row.querySelector('.site-health-orphan-clash-audio');
            if (audio instanceof HTMLAudioElement) {
                audio.addEventListener('play', () => {
                    if (!previewBodyEl) {
                        return;
                    }
                    previewBodyEl.querySelectorAll('.site-health-orphan-clash-audio').forEach((other) => {
                        if (other !== audio && !other.paused) {
                            other.pause();
                        }
                    });
                });
            }
        });
    }

    function selectedCampaignForDataRow(row) {
        if (!(row instanceof HTMLElement)) {
            return { id: '', title: '' };
        }
        const select = row.querySelector('.site-health-data-adopt-select');
        if (select instanceof HTMLSelectElement) {
            const id = String(select.value || '').trim();
            const opt = select.selectedOptions && select.selectedOptions[0]
                ? select.selectedOptions[0]
                : null;
            const title = opt ? String(opt.textContent || '').trim() : '';
            return { id: id, title: title || id };
        }
        const active = row.querySelector('.site-health-data-adopt-toggle .brand-player-setting-btn.is-active');
        if (active instanceof HTMLElement) {
            const id = String(active.getAttribute('data-campaign-id') || '').trim();
            const title = String(
                active.getAttribute('data-campaign-title') || active.textContent || ''
            ).trim();
            return { id: id, title: title || id };
        }
        return { id: '', title: '' };
    }

    function syncDataAdoptButton(row) {
        if (!(row instanceof HTMLElement)) {
            return;
        }
        const btn = row.querySelector('.site-health-data-adopt');
        if (!(btn instanceof HTMLButtonElement)) {
            return;
        }
        const field = row.querySelector('.site-health-orphan-clash-field');
        const label = row.querySelector('.site-health-orphan-clash-field-label');
        const selected = selectedCampaignForDataRow(row);
        btn.setAttribute('data-campaign-id', selected.id);
        btn.setAttribute('data-campaign-title', selected.title);
        if (selected.id === '') {
            btn.hidden = true;
            btn.disabled = true;
            btn.classList.add('btn-good');
            btn.textContent = 'Adopt';
            if (field) {
                field.classList.add('is-needs-choice');
            }
            if (label) {
                label.textContent = 'Select campaign:';
            }
        } else {
            btn.hidden = false;
            btn.textContent = 'Adopt';
            btn.classList.add('btn-good');
            btn.disabled = running;
            if (field) {
                field.classList.remove('is-needs-choice');
            }
            if (label) {
                label.textContent = 'Campaign:';
            }
        }
    }

    function removeDataOrphanFromPlan(kind, entityId) {
        const k = String(kind || '').trim().toLowerCase();
        const id = String(entityId || '').trim();
        if (!k || !id || !lastPlan || !Array.isArray(lastPlan.findings)) {
            return;
        }
        const nextFindings = [];
        lastPlan.findings.forEach((finding) => {
            if (String((finding && finding.id) || '') !== 'data_container_orphans') {
                nextFindings.push(finding);
                return;
            }
            const sample = Array.isArray(finding.items_sample) ? finding.items_sample : [];
            const kept = sample.filter((item) => {
                if (item && typeof item === 'object') {
                    return !(
                        String(item.kind || '').trim().toLowerCase() === k
                        && String(item.id || '').trim() === id
                    );
                }
                return true;
            });
            const prevCount = Number(finding.count) || sample.length;
            const nextCount = Math.max(0, prevCount - 1);
            if (nextCount === 0) {
                return;
            }
            nextFindings.push(Object.assign({}, finding, {
                items_sample: kept,
                count: nextCount,
                body: (
                    nextCount + ' playlist/gallery/page item(s) are orphaned or unowned. ' +
                    'Adopt them into a campaign, or Delete leftovers — Site health will not guess.'
                ),
            }));
        });
        lastPlan.findings = nextFindings;
        if (!nextFindings.length) {
            lastPlan.overall = 'healthy';
            setOverall('healthy');
        } else {
            const hasCritical = nextFindings.some(
                (f) => String((f && f.severity) || '').toLowerCase() === 'critical'
            );
            setOverall(hasCritical ? 'critical' : 'attention');
        }
    }

    function refreshPreviewAfterManualFix() {
        if (!previewOpen || !lastPlan) {
            return;
        }
        const stillFindings = Array.isArray(lastPlan.findings) && lastPlan.findings.length > 0;
        if (!stillFindings) {
            setPreviewMode(false);
            setUiStage('exam');
            renderPlan(lastPlan);
            return;
        }
        setPreviewMode(true);
    }

    async function adoptDataContainer(kind, entityId, titleLabel, campaignId, campaignTitle) {
        const k = String(kind || '').trim();
        const id = String(entityId || '').trim();
        const campaign = String(campaignId || '').trim();
        if (!k || !id || !campaign) {
            return false;
        }
        const label = String(titleLabel || id).trim() || id;
        const homeTitle = String(campaignTitle || campaign).trim() || campaign;
        const confirmed = typeof window.bandpromoConfirm === 'function'
            ? await window.bandpromoConfirm({
                title: 'Adopt container?',
                body: (
                    'Adopt “' + label + '” (' + k + ') into “' + homeTitle + '”?\n\n' +
                    'Site health will register it if needed and stamp that campaign as its home.'
                ),
                confirmLabel: 'Adopt',
                cancelLabel: 'Cancel',
            })
            : window.confirm('Adopt “' + label + '” into “' + homeTitle + '”?');
        if (!confirmed) {
            return false;
        }
        let csrfToken = '';
        if (typeof refreshAdminCsrfToken === 'function') {
            csrfToken = await refreshAdminCsrfToken();
        }
        try {
            const resp = await fetch('/biblioteca/site-health-data-adopt-container.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    kind: k,
                    id: id,
                    campaign_id: campaign,
                    csrf_token: csrfToken,
                }),
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data || data.ok !== true) {
                setJobStatus(
                    (data && data.error) ? data.error : 'Could not adopt container.',
                    'error'
                );
                return false;
            }
            removeDataOrphanFromPlan(k, id);
            refreshPreviewAfterManualFix();
            if (typeof window.showAdminToast === 'function') {
                window.showAdminToast('Adopted “' + label + '” into “' + homeTitle + '”.', 'success');
            }
            return true;
        } catch (err) {
            setJobStatus('Could not adopt container.', 'error');
            return false;
        }
    }

    async function deleteDataContainer(kind, entityId, titleLabel) {
        const k = String(kind || '').trim();
        const id = String(entityId || '').trim();
        if (!k || !id) {
            return false;
        }
        const label = String(titleLabel || id).trim() || id;
        const confirmed = typeof window.bandpromoConfirm === 'function'
            ? await window.bandpromoConfirm({
                title: 'Delete container?',
                body: (
                    'Permanently delete “' + label + '” (' + k + ')?\n\n' +
                    'This removes the catalogue document (and registry row when present). It cannot be undone.'
                ),
                confirmLabel: 'Delete',
                cancelLabel: 'Cancel',
                tone: 'danger',
            })
            : window.confirm('Permanently delete “' + label + '” (' + k + ')?');
        if (!confirmed) {
            return false;
        }
        let csrfToken = '';
        if (typeof refreshAdminCsrfToken === 'function') {
            csrfToken = await refreshAdminCsrfToken();
        }
        try {
            const resp = await fetch('/biblioteca/site-health-data-delete-container.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    kind: k,
                    id: id,
                    csrf_token: csrfToken,
                }),
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || !data || data.ok !== true) {
                setJobStatus(
                    (data && data.error) ? data.error : 'Could not delete container.',
                    'error'
                );
                return false;
            }
            removeDataOrphanFromPlan(k, id);
            refreshPreviewAfterManualFix();
            if (typeof window.showAdminToast === 'function') {
                window.showAdminToast('Deleted “' + label + '”.', 'success');
            }
            return true;
        } catch (err) {
            setJobStatus('Could not delete container.', 'error');
            return false;
        }
    }

    function bindDataContainerHandlers() {
        if (!previewBodyEl) {
            return;
        }
        previewBodyEl.querySelectorAll('.site-health-data-orphan').forEach((row) => {
            if (!(row instanceof HTMLElement)) {
                return;
            }
            syncDataAdoptButton(row);
            const toggle = row.querySelector('.site-health-data-adopt-toggle');
            if (toggle) {
                toggle.querySelectorAll('.brand-player-setting-btn').forEach((btn) => {
                    btn.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        toggle.querySelectorAll('.brand-player-setting-btn').forEach((other) => {
                            other.classList.remove('is-active');
                            other.setAttribute('aria-pressed', 'false');
                        });
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-pressed', 'true');
                        syncDataAdoptButton(row);
                    });
                });
            }
            const select = row.querySelector('.site-health-data-adopt-select');
            if (select instanceof HTMLSelectElement) {
                select.addEventListener('change', () => {
                    syncDataAdoptButton(row);
                });
                select.addEventListener('click', (event) => {
                    event.stopPropagation();
                });
            }
            const adoptBtn = row.querySelector('.site-health-data-adopt');
            if (adoptBtn instanceof HTMLButtonElement) {
                adoptBtn.addEventListener('click', async (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const selected = selectedCampaignForDataRow(row);
                    if (!selected.id) {
                        return;
                    }
                    const kind = String(adoptBtn.getAttribute('data-kind') || '').trim();
                    const entityId = String(adoptBtn.getAttribute('data-entity-id') || '').trim();
                    const fileEl = row.querySelector('.site-health-orphan-clash-file strong');
                    const label = fileEl ? String(fileEl.textContent || '').trim() : entityId;
                    adoptBtn.disabled = true;
                    await adoptDataContainer(kind, entityId, label, selected.id, selected.title);
                    if (previewBodyEl && previewBodyEl.contains(adoptBtn)) {
                        syncDataAdoptButton(row);
                    }
                });
            }
            const deleteBtn = row.querySelector('.site-health-data-delete');
            if (deleteBtn instanceof HTMLButtonElement) {
                deleteBtn.addEventListener('click', async (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const kind = String(deleteBtn.getAttribute('data-kind') || '').trim();
                    const entityId = String(deleteBtn.getAttribute('data-entity-id') || '').trim();
                    const label = String(deleteBtn.getAttribute('data-title') || entityId).trim();
                    deleteBtn.disabled = true;
                    await deleteDataContainer(kind, entityId, label);
                    if (previewBodyEl && previewBodyEl.contains(deleteBtn)) {
                        deleteBtn.disabled = false;
                    }
                });
            }
        });
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
        bindOrphanHomeHandlers();
        bindDataContainerHandlers();
        syncApplyEnabledFromSelection();
    }

    function setPreviewMode(open) {
        previewOpen = !!open;
        if (!previewEl) {
            syncRecommendedAction();
            syncBreadcrumb();
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
            if (uiStage === 'review') {
                setUiStage(lastPlan && (lastPlan.checked_at || (Array.isArray(lastPlan.findings) && lastPlan.findings.length))
                    ? 'exam'
                    : 'status');
            } else {
                syncBreadcrumb();
            }
            syncRecommendedAction();
            return;
        }

        const findings = Array.isArray(lastPlan.findings) ? lastPlan.findings : [];
        const fix = findingsFixable(lastPlan);
        const hasOrphanClash = findings.some(
            (f) => String((f && f.id) || '') === 'orphan_assets_multi_campaign'
        );
        const hasDataOrphans = findings.some(
            (f) => String((f && f.id) || '') === 'data_container_orphans'
        );
        const findingRows = findings.length
            ? findings.map(findingReviewRowHtml).join('')
            : '<p class="site-health-summary-empty">No findings on the current plan.</p>';

        let previewNote = (
            'Tick what you want fixed (everything useful is selected to start). ' +
            'Nothing changes until you Apply. A backup first is a good idea if you want a restore point.'
        );
        if (fix.manual > 0 && fix.fixable === 0 && (hasOrphanClash || hasDataOrphans)) {
            previewNote = (
                'Select a campaign on each manual row, then Adopt or Set. ' +
                'Apply stays off for these — Site health will not guess. ' +
                'A backup first is a good idea if you want a restore point.'
            );
        } else if (fix.manual > 0 && fix.fixable > 0 && (hasOrphanClash || hasDataOrphans)) {
            previewNote = (
                'Tick auto-fixable items for Apply. Manual rows need a campaign selected first. ' +
                'A backup first is a good idea if you want a restore point.'
            );
        }

        if (previewBodyEl) {
            previewBodyEl.innerHTML = (
                '<h3 class="site-health-treat-preview-title">Proposed treatment</h3>' +
                assuranceHtml(lastPlan) +
                '<p class="site-health-treat-preview-note">' + previewNote + '</p>' +
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
        setUiStage('review');
        bindPreviewSelectionHandlers();
        updateApplySummaryLine();
        syncRecommendedAction();
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
        // Do not let a stale finished plan overwrite the label of the job now running.
        if (!running) {
            const fromMode = examLabelFromMode(modeKey);
            if (fromMode) {
                saveExamLabel(fromMode);
            }
        }

        if (metaEl) {
            const bits = [];
            if (checkedAt) {
                bits.push('Last check: ' + checkedAt + ' UTC');
            }
            if (appVersion) {
                bits.push(appVersion);
            }
            if (isPlanStale(plan) && !running) {
                bits.push('out of date');
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
            findingsEl.hidden = true;
            if (running) {
                syncBreadcrumb();
                return;
            }
            setPreviewMode(false);
            if (uiStage !== 'status' && uiStage !== 'hub') {
                setUiStage('status');
            } else {
                syncStagePanels();
            }
            return;
        }

        const stale = isPlanStale(plan);

        // While a job is running, never present the stale-plan / auto-Quick story —
        // the operator (or auto-start) already launched a fresh check.
        if (running) {
            if (stale) {
                if (summaryEl) {
                    summaryEl.hidden = true;
                    summaryEl.classList.remove('is-stale');
                    summaryEl.innerHTML = '';
                }
                findingsEl.innerHTML = '';
                findingsEl.hidden = true;
            } else {
                renderSummary(plan);
                if (summaryEl && !summaryEl.hidden) {
                    summaryEl.classList.remove('is-stale');
                }
                if (plan.summary) {
                    findingsEl.innerHTML = '';
                    findingsEl.hidden = true;
                }
            }
            syncBreadcrumb();
            return;
        }

        if (stale) {
            // Never present hour-old Good/Bad/Ugly as current health.
            findingsEl.innerHTML = '';
            findingsEl.hidden = true;
            // Status landing may auto-start Quick; hub / exam must not claim that.
            if (uiStage === 'status') {
                if (summaryEl) {
                    summaryEl.hidden = true;
                    summaryEl.innerHTML = '';
                }
                syncStagePanels();
                syncRecommendedAction();
                return;
            }
            if (uiStage === 'hub') {
                if (summaryEl) {
                    summaryEl.hidden = true;
                    summaryEl.innerHTML = '';
                }
                syncStagePanels();
                syncRecommendedAction();
                return;
            }
            if (summaryEl) {
                summaryEl.hidden = false;
                summaryEl.classList.add('is-stale');
                summaryEl.innerHTML = (
                    '<p class="publish-status-empty site-health-stale-notice">' +
                    'Last check is more than an hour old. Run Quick or Full health check to refresh.' +
                    '</p>'
                );
            }
        } else {
            // Good / Bad / Ugly is the exam summary; findings detail lives under Bad & Ugly.
            renderSummary(plan);
            if (summaryEl && !summaryEl.hidden) {
                summaryEl.classList.remove('is-stale');
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
        }

        if (stale) {
            setPreviewMode(false);
            if (treatBtn) {
                treatBtn.hidden = true;
            }
            syncRecommendedAction();
            syncStagePanels();
            return;
        }

        // Status / Site health hubs keep their stage; only refresh badge data underneath.
        if (uiStage === 'status' || uiStage === 'hub') {
            syncRecommendedAction();
            syncStagePanels();
            return;
        }

        if (findings.length === 0) {
            setPreviewMode(false);
            if (treatBtn) {
                treatBtn.hidden = true;
            }
            if (uiStage !== 'result') {
                setUiStage('exam');
            }
            syncRecommendedAction();
            return;
        }

        if (previewOpen) {
            setPreviewMode(true);
        } else if (uiStage === 'result') {
            setUiStage('result');
            if (treatBtn) {
                treatBtn.hidden = false;
                treatBtn.disabled = false;
            }
            if (previewEl) {
                previewEl.hidden = true;
            }
            syncRecommendedAction();
        } else {
            setUiStage('exam');
            if (treatBtn) {
                treatBtn.hidden = false;
                treatBtn.disabled = false;
            }
            if (previewEl) {
                previewEl.hidden = true;
            }
            syncRecommendedAction();
        }
        syncStagePanels();
    }

    function setRunningUi(isRunning) {
        running = !!isRunning;
        if (spinnerEl) {
            spinnerEl.style.display = running ? '' : 'none';
        }
        // Busy: disable start / review / force; only Stop stays available.
        // Visibility of exam actions is owned by syncStagePanels (hidden on review/result).
        checkBtn.disabled = running;
        if (checkFullBtn) {
            checkFullBtn.disabled = running;
        }
        forceBtn.disabled = running;
        if (treatBtn) {
            treatBtn.disabled = running;
        }
        if (treatApplyBtn) {
            treatApplyBtn.disabled = running;
        }
        if (treatCancelBtn) {
            treatCancelBtn.disabled = running;
        }
        if (previewEl && running && uiStage === 'review') {
            // Keep stage as review in memory but hide the card while the job runs;
            // treat advances to result explicitly.
            previewEl.hidden = true;
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
        syncBreadcrumb();
        syncStagePanels();
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
            // Do not clear local busy while a start request is still in flight — a
            // mid-start status paint can race the lock and open a second start.
            const isRunning = !!data.running || startInFlight;
            running = isRunning;
            if (isRunning) {
                const liveMode = String((data.meta && data.meta.mode) || '').trim();
                const liveLabel = examLabelFromMode(liveMode);
                if (liveLabel) {
                    saveExamLabel(liveLabel);
                }
            }
            renderPlan(plan);
            setRunningUi(isRunning);
            if (!data.running) {
                if (isPlanStale(plan)) {
                    setOverall('outdated');
                    syncRecommendedAction();
                    // Only from Status landing — do not hijack Site health hub / Full check.
                    if (uiStage === 'status') {
                        maybeAutoStartStaleQuickCheck();
                    }
                } else {
                    const overall = String(data.overall || plan.overall || '');
                    setOverall(overall || 'unknown');
                    if (data.exit_code === 0) {
                        const metaMode = String((data.meta && data.meta.mode) || '').trim();
                        const planMode = String(plan.mode || '').trim();
                        // Prefer the job that was launched (meta.mode). Follow-up overwrites
                        // plan.mode to "followup" / check — never call Force a Quick check.
                        const jobMode = metaMode || planMode;
                        // Only keep Treatment result when already on that step.
                        // Fresh Status loads must show the exam, even if the last
                        // finished job was treat/followup.
                        if (uiStage === 'result') {
                            if (!lastTreatReport) {
                                lastTreatReport = { labels: [], treats: [], notes: [], overall: '' };
                            }
                            lastTreatReport.overall = overall;
                            setUiStage('result');
                        }
                        if (overall === 'healthy') {
                            if (uiStage !== 'result') {
                                setPreviewMode(false);
                            }
                            let doneMsg = 'Health job complete — site looks healthy.';
                            if (jobMode === 'check_full') {
                                doneMsg = 'Full check complete — site looks healthy.';
                            } else if (jobMode === 'check') {
                                doneMsg = 'Quick check complete — site looks healthy.';
                            } else if (jobMode === 'treat' || jobMode === 'followup') {
                                doneMsg = 'Treatment complete — site looks healthy.';
                            } else if (jobMode === 'force') {
                                doneMsg = 'Force rebuild complete — site looks healthy.';
                            }
                            setJobStatus(doneMsg, 'success');
                        } else if (overall === 'critical' || overall === 'attention') {
                            // Findings live in The bad / The ugly — no duplicate status nudge.
                            if (uiStage === 'result') {
                                if (jobMode === 'force') {
                                    setJobStatus(
                                        'Force rebuild finished with remaining findings.',
                                        overall === 'critical' ? 'error' : 'attention'
                                    );
                                } else if (jobMode === 'treat' || jobMode === 'followup') {
                                    setJobStatus(
                                        'Treatment finished with remaining findings.',
                                        overall === 'critical' ? 'error' : 'attention'
                                    );
                                } else {
                                    setJobStatus('');
                                }
                            } else if (uiStage !== 'review') {
                                setJobStatus('');
                            }
                        } else {
                            setJobStatus('Health job finished.', 'success');
                        }
                    } else if (data.exit_code !== null && data.exit_code !== undefined) {
                        setJobStatus('Health job exited with code ' + data.exit_code + '.', 'error');
                    }
                    syncRecommendedAction();
                }
            } else {
                const message = data.meta && data.meta.message ? String(data.meta.message) : 'Working…';
                setJobStatus(message);
                const liveMode = String((data.meta && data.meta.mode) || '').trim().toLowerCase();
                // Mid-job reload: resume the matching stepped view.
                if (liveMode === 'treat' || liveMode === 'followup') {
                    if (uiStage !== 'result') {
                        setUiStage('result');
                    } else {
                        renderTreatResultPanel('');
                    }
                } else if (
                    (liveMode === 'check' || liveMode === 'check_full' || liveMode === 'force')
                    && uiStage !== 'exam'
                    && uiStage !== 'result'
                ) {
                    setUiStage('exam');
                } else if (uiStage === 'result') {
                    renderTreatResultPanel('');
                }
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
        if (startInFlight) {
            return 'already-running';
        }
        let csrfToken = '';
        if (typeof refreshAdminCsrfToken === 'function') {
            csrfToken = await refreshAdminCsrfToken();
        }
        startInFlight = true;
        setRunningUi(true);
        let starting = 'Starting…';
        if (mode === 'check') {
            starting = 'Starting quick health check…';
            saveExamLabel('Quick check');
            setUiStage('exam');
        } else if (mode === 'check_full') {
            starting = 'Starting full health check…';
            saveExamLabel('Full check');
            setUiStage('exam');
        } else if (mode === 'treat') {
            starting = 'Starting treatment…';
            setUiStage('result');
        } else if (mode === 'force') {
            starting = 'Starting force rebuild…';
            saveExamLabel('Force rebuild');
            setUiStage('exam');
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
            const data = await resp.json().catch(() => ({}));
            const alreadyRunning = !!(data && data.running === true)
                || /already running/i.test(String((data && data.error) || ''));
            if ((!resp.ok || !data || data.ok !== true) && !alreadyRunning) {
                startInFlight = false;
                setRunningUi(false);
                setJobStatus((data && data.error) ? data.error : 'Could not start site health.', 'error');
                return 'error';
            }
            if (mode === 'treat' && !alreadyRunning) {
                previewOpen = false;
                if (previewEl) {
                    previewEl.hidden = true;
                }
                setUiStage('result');
                if (!lastTreatReport) {
                    lastTreatReport = { labels: [], treats: [], notes: [], overall: '' };
                }
                renderTreatResultPanel('');
            }
            beginPolling();
            await refreshStatus();
            startInFlight = false;
            // Keep busy if the server is still running; refreshStatus owns the flag.
            if (!running) {
                setRunningUi(!!(data && data.running));
            }
            return alreadyRunning ? 'already-running' : 'started';
        } catch (err) {
            startInFlight = false;
            setRunningUi(false);
            setJobStatus('Could not start site health.', 'error');
            return 'error';
        }
    }

    window.bandpromoStartSiteHealthQuickCheck = function bandpromoStartSiteHealthQuickCheck() {
        if (running || startInFlight) {
            return 'already-running';
        }
        if (!checkBtn) {
            return 'unavailable';
        }
        previewOpen = false;
        setUiStage('exam');
        startMode('check');
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

    async function maybeAutoStartStaleQuickCheck() {
        if (staleAutoCheckStarted || running) {
            return;
        }
        if (!isPlanStale(lastPlan)) {
            return;
        }
        // Status landing only — hub / exam keep the operator’s choice (Quick vs Full).
        if (uiStage !== 'status') {
            return;
        }
        staleAutoCheckStarted = true;
        setPreviewMode(false);
        setUiStage('exam');
        if (summaryEl) {
            summaryEl.hidden = false;
            summaryEl.classList.add('is-stale');
            summaryEl.innerHTML = (
                '<p class="publish-status-empty site-health-stale-notice">' +
                'Last check is more than an hour old. Starting a fresh Quick health check…' +
                '</p>'
            );
        }
        await startMode('check');
        // Allow a later retry if start failed (still idle + still out of date).
        if (!running && isPlanStale(lastPlan)) {
            staleAutoCheckStarted = false;
        }
    }

    function clearPostUpdateAutoStartFlags() {
        try {
            sessionStorage.removeItem('bandpromo_run_site_health_check');
            sessionStorage.removeItem('bandpromo_post_package_update');
        } catch (error) {
            // Ignore storage failures.
        }
    }

    async function maybeAutoStartAfterUpdate() {
        if (!shouldAutoStartQuickCheck()) {
            return;
        }
        // Wait for the first status paint so we do not fight a busy lock.
        await refreshStatus();
        if (running || startInFlight) {
            clearPostUpdateAutoStartFlags();
            return;
        }
        let attempts = 0;
        while (attempts < 8) {
            attempts += 1;
            const result = window.bandpromoStartSiteHealthQuickCheck();
            if (result === 'started' || result === 'already-running') {
                clearPostUpdateAutoStartFlags();
                if (typeof window.closeOperatorNotifications === 'function') {
                    window.closeOperatorNotifications();
                }
                return;
            }
            await new Promise((resolve) => setTimeout(resolve, 250));
        }
    }

    checkBtn.addEventListener('click', () => {
        previewOpen = false;
        setUiStage('exam');
        startMode('check');
    });

    if (checkFullBtn) {
        checkFullBtn.addEventListener('click', () => {
            previewOpen = false;
            setUiStage('exam');
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
                setUiStage('exam');
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
            setUiStage('exam');
            setJobStatus('');
        });
    }

    if (treatApplyBtn) {
        treatApplyBtn.addEventListener('click', async () => {
            if (isPlanStale(lastPlan)) {
                setPreviewMode(false);
                setUiStage('exam');
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
                ? selected.labels.map((line) => '• ' + line).join('\n')
                : '• (none)';
            const confirmed = typeof window.bandpromoConfirm === 'function'
                ? await window.bandpromoConfirm({
                    title: 'Apply treatment?',
                    body: (
                        'Only the ticked items will be fixed. Unticked items stay for later.\n\n' +
                        summary
                    ),
                    confirmLabel: 'Apply treatment',
                    cancelLabel: 'Not now',
                })
                : window.confirm(
                    'Apply the selected treatment now?\n\n' +
                    summary +
                    '\n\nOnly the ticked items will be fixed. Unticked items stay for later.'
                );
            if (!confirmed) {
                return;
            }
            lastTreatReport = {
                labels: selected.labels.slice(),
                treats: [],
                notes: [],
                overall: '',
            };
            startMode('treat', {
                treatmentIds: selected.treatmentIds,
                findingIds: selected.findingIds,
            });
        });
    }

    forceBtn.addEventListener('click', async () => {
        const confirmed = typeof window.bandpromoConfirm === 'function'
            ? await window.bandpromoConfirm({
                title: 'Force full rebuild?',
                body: (
                    'Force a full listener rebuild even if Check looks healthy?\n\n' +
                    'This is blocked while critical catalogue findings remain. Prefer Check → Review → Apply for missing Files rows.'
                ),
                confirmLabel: 'Force full rebuild',
                cancelLabel: 'Cancel',
            })
            : window.confirm(
                'Force a full listener rebuild even if Check looks healthy?\n\n' +
                'This is blocked while critical catalogue findings remain. Prefer Check → Review → Apply for missing Files rows.'
            );
        if (!confirmed) {
            return;
        }
        previewOpen = false;
        setUiStage('exam');
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
    document.querySelectorAll('#site-health-log-card summary a').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    });

    if (crumbStatusLink) {
        crumbStatusLink.addEventListener('click', (event) => {
            event.preventDefault();
            goBreadcrumb('status');
        });
    }
    if (enterHubBtn) {
        enterHubBtn.addEventListener('click', () => {
            goBreadcrumb('hub');
        });
    }
    if (crumbHomeBtn) {
        crumbHomeBtn.addEventListener('click', () => {
            goBreadcrumb('hub');
        });
    }
    if (breadcrumbStepsEl) {
        breadcrumbStepsEl.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('[data-site-health-crumb]');
            if (!(button instanceof HTMLElement)) {
                return;
            }
            goBreadcrumb(String(button.getAttribute('data-site-health-crumb') || ''));
        });
    }

    syncBreadcrumb();
    syncStagePanels();
    refreshStatus();
    maybeAutoStartAfterUpdate();
})();
