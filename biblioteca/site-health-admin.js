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
        audio_register_in_place: 'Add songs already on the server into Files (no re-upload).',
        visual_register_in_place: 'Add pictures or videos already on the server into Files (no re-upload).',
        sfx_register_in_place: 'Add sound effects already on the server into Files (no re-upload).',
        listener_delivery: 'Build missing or outdated streaming audio and artwork for the player.',
        audio_fill_display_from_tags: 'Fill empty track title and artist from the file\'s own tags.',
        audio_extract_covers: 'Pull embedded cover art from tracks and link it in Files.',
        media_janitor_prune: 'Clear the selected leftovers. Your originals and masters stay safe.',
        data_janitor_prune: 'Clear leftover temporary and junk files from site data storage.',
        data_container_relink: 'Reconnect playlists, galleries, and pages that already belong to a campaign.',
        dedupe_retarget_and_remove: 'Keep one copy of duplicate masters and remove unused clones.',
        files_index_rebuild: 'Refresh the Files → Audio list so it matches what is catalogued.',
        playlists: 'Refresh player playlists so they match the catalogue.',
        site_chrome: 'Update share images and home-screen / install icons.',
        container_links: 'Refresh campaign links after site data changed.',
        sfx_delivery: 'Build missing or outdated sound-effect play files.',
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
            return 'Set a campaign home for orphan media already used in campaigns.';
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
            ? ('<p class="site-health-treat-detail-label">Requested:</p>' +
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
            ? ('<p class="site-health-treat-detail-label">Applied:</p>' +
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
            detail += ' — ' + findings + ' item' + (findings === 1 ? '' : 's') + ' still need attention.';
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
                'Choose Yes or Skip for each item (everything useful starts as Yes), then Apply.' +
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
                    'These need your choice on each row — pick a campaign (or Delete), Include, then Apply. ' +
                    'Nothing is auto-guessed.' +
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

    function campaignChoiceButtonsHtml(campaigns, extraClass) {
        const chipClass = String(extraClass || '').trim();
        return campaigns.map((c) => {
            const id = String((c && c.id) || '').trim();
            const title = String((c && (c.title || c.id)) || id).trim() || id;
            return (
                '<button type="button" class="brand-player-setting-btn site-health-choice-chip' +
                (chipClass ? ' ' + chipClass : '') + '" ' +
                'data-campaign-id="' + escapeHtml(id) + '" ' +
                'data-campaign-title="' + escapeHtml(title) + '" ' +
                'aria-pressed="false">' +
                escapeHtml(title) +
                '</button>'
            );
        }).join('');
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
                '<div class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select catalogue home:</span> ' +
                '<select class="site-health-choice-select site-health-orphan-home-select">' +
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
                '</select></div>'
            );
        } else {
            choiceHtml = (
                '<div class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select catalogue home:</span> ' +
                '<div class="site-health-choice-chips site-health-choice-toggle" ' +
                'role="group" aria-label="Select catalogue home for ' + escapeHtml(filename) + '">' +
                campaignChoiceButtonsHtml(campaigns) +
                '</div></div>'
            );
        }

        return (
            '<li class="site-health-orphan-clash site-health-manual-choice-row" ' +
            'data-manual-action="assign_orphan_home" ' +
            'data-asset-id="' + escapeHtml(assetId) + '" data-filename="' + escapeHtml(filename) + '">' +
            '<div class="site-health-orphan-clash-head">' +
            previewHtml +
            '<div class="site-health-orphan-clash-meta">' +
            '<p class="site-health-orphan-clash-file"><strong>' + escapeHtml(filename) +
            '</strong> <span class="site-health-orphan-clash-kind">(' + escapeHtml(kind) +
            ')</span></p>' +
            '<p class="site-health-orphan-clash-used">Used in: ' + usedIn + '</p>' +
            '</div></div>' +
            choiceHtml +
            '</li>'
        );
    }

    function dataContainerClassLabel(className) {
        const key = String(className || '').trim().toLowerCase();
        if (key === 'invisible') {
            return 'Not listed yet';
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

        const deleteChip = allowDelete
            ? (
                '<button type="button" class="brand-player-setting-btn site-health-choice-chip ' +
                'site-health-choice-delete" data-action="delete" aria-pressed="false">' +
                'Delete' +
                '</button>'
            )
            : '';
        const deleteWithOr = allowDelete
            ? ('<span class="site-health-choice-or">or</span>' + deleteChip)
            : '';

        let choiceHtml = '';
        if (campaigns.length > 5) {
            choiceHtml = (
                '<div class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select campaign:</span> ' +
                '<select class="site-health-choice-select site-health-data-adopt-select">' +
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
                (allowDelete
                    ? ('<span class="site-health-choice-or">or</span>' +
                       '<div class="site-health-choice-chips site-health-choice-toggle site-health-choice-toggle--delete-only" ' +
                       'role="group" aria-label="Or delete ' + escapeHtml(title) + '">' +
                       deleteChip + '</div>')
                    : '') +
                '</div>'
            );
        } else if (campaigns.length) {
            choiceHtml = (
                '<div class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select campaign:</span> ' +
                '<div class="site-health-choice-chips site-health-choice-toggle" ' +
                'role="group" aria-label="Select campaign for ' + escapeHtml(title) + '">' +
                campaignChoiceButtonsHtml(campaigns) +
                deleteWithOr +
                '</div></div>'
            );
        } else if (allowDelete) {
            choiceHtml = (
                '<div class="site-health-orphan-clash-field is-needs-choice">' +
                '<span class="site-health-orphan-clash-field-label">Select action:</span> ' +
                '<div class="site-health-choice-chips site-health-choice-toggle" ' +
                'role="group" aria-label="Select action for ' + escapeHtml(title) + '">' +
                deleteChip +
                '</div></div>'
            );
        } else {
            choiceHtml = (
                '<p class="site-health-orphan-clash-used">No campaigns available to Adopt into.</p>'
            );
        }

        return (
            '<li class="site-health-data-orphan site-health-orphan-clash site-health-manual-choice-row" ' +
            'data-manual-action="data_container" ' +
            'data-entity-kind="' + escapeHtml(kind) + '" data-entity-id="' + escapeHtml(entityId) + '" ' +
            'data-entity-title="' + escapeHtml(title) + '">' +
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
        const isManualChoice = isOrphanClash || isDataOrphans;
        const treatmentLabel = canTreat
            ? escapeHtml(TREATMENT_COPY[treatmentId] || treatmentId)
            : (isManualChoice
                ? ''
                : 'Needs a manual step — Site health will not change this automatically.');
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
                '<p class="site-health-treat-detail-label">Clash detail:</p>' +
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
                '<p class="site-health-treat-detail-label">Container detail:</p>' +
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
                '<p class="site-health-treat-detail-label">Item names (optional):</p>' +
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
            ? treatIncludeToggleHtml({
                findingId: findingId,
                treatmentId: treatmentId,
                included: true,
                disabled: false,
            })
            : (isManualChoice
                ? treatIncludeToggleHtml({
                    findingId: findingId,
                    treatmentId: '',
                    included: false,
                    disabled: true,
                    manual: true,
                })
                : (
                    '<span class="site-health-treat-select site-health-treat-select--manual" ' +
                    'title="Not auto-fixed by Apply">Manual</span>'
                ));

        return (
            '<details class="site-health-treat-finding ' + tone +
            (canTreat ? '' : ' is-manual') +
            (isManualChoice ? ' is-orphan-clash site-health-manual-finding' : '') + '" open>' +
            '<summary>' +
            selectHtml +
            '<span class="site-health-treat-finding-title">' + title +
            (count ? ' (' + count + ')' : '') + '</span>' +
            '<span class="site-health-treat-finding-hint">Details</span>' +
            '</summary>' +
            '<div class="site-health-treat-finding-body">' +
            '<p><strong>Found this:</strong></p>' +
            foundBits.join('') +
            (treatmentLabel
                ? ('<p class="site-health-treat-suggested"><strong>Suggested treatment:</strong> ' +
                   treatmentLabel + '</p>')
                : '') +
            '</div>' +
            '</details>'
        );
    }

    function treatIncludeToggleHtml(opts) {
        const findingId = escapeHtml(String((opts && opts.findingId) || ''));
        const treatmentId = escapeHtml(String((opts && opts.treatmentId) || ''));
        const included = !!(opts && opts.included);
        const disabled = !!(opts && opts.disabled);
        const manual = !!(opts && opts.manual);
        const title = disabled
            ? ' title="Choose an option on every row first"'
            : '';
        return (
            '<div class="site-health-treat-select site-health-treat-include' +
            (disabled ? ' is-disabled' : '') +
            '" onclick="event.stopPropagation()"' + title + '>' +
            '<span class="site-health-treat-include-label">Include:</span>' +
            '<div class="site-health-choice-toggle site-health-treat-include-toggle" ' +
            'role="group" aria-label="Include in Apply">' +
            '<button type="button" class="brand-player-setting-btn' +
            (included ? ' is-active' : '') + '"' +
            ' data-include="1" aria-pressed="' + (included ? 'true' : 'false') + '"' +
            (disabled ? ' disabled' : '') + '>Yes</button>' +
            '<button type="button" class="brand-player-setting-btn' +
            (!included ? ' is-active' : '') + '"' +
            ' data-include="0" aria-pressed="' + (!included ? 'true' : 'false') + '"' +
            (disabled ? ' disabled' : '') + '>Skip</button>' +
            '</div>' +
            '<input type="checkbox" class="site-health-treat-check' +
            (manual ? ' site-health-manual-check' : '') + '"' +
            (included ? ' checked' : '') +
            (disabled ? ' disabled' : '') +
            ' data-finding-id="' + findingId + '"' +
            (treatmentId
                ? (' data-treatment-id="' + treatmentId + '"')
                : (' data-manual-kind="' + findingId + '"')) +
            ' hidden>' +
            '</div>'
        );
    }

    function setTreatIncludeState(wrap, included, disabled) {
        if (!(wrap instanceof HTMLElement)) {
            return;
        }
        const check = wrap.querySelector('.site-health-treat-check');
        const buttons = wrap.querySelectorAll('[data-include]');
        const nextIncluded = !!included;
        const nextDisabled = !!disabled;
        if (check instanceof HTMLInputElement) {
            check.checked = nextIncluded && !nextDisabled;
            check.disabled = nextDisabled;
        }
        Array.prototype.forEach.call(buttons, (btn) => {
            if (!(btn instanceof HTMLButtonElement)) {
                return;
            }
            const isYes = String(btn.getAttribute('data-include') || '') === '1';
            const active = nextIncluded ? isYes : !isYes;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.disabled = nextDisabled;
        });
        wrap.classList.toggle('is-disabled', nextDisabled);
        wrap.title = nextDisabled ? 'Choose an option on every row first' : '';
    }

    async function getAdminCsrfToken() {
        if (typeof window.refreshAdminCsrfToken === 'function') {
            return await window.refreshAdminCsrfToken();
        }
        if (typeof refreshAdminCsrfToken === 'function') {
            return await refreshAdminCsrfToken();
        }
        if (typeof adminCsrfToken === 'string' && adminCsrfToken) {
            return adminCsrfToken;
        }
        const respCsrf = await fetch('/biblioteca/get-admin-csrf.php', {
            credentials: 'same-origin',
        });
        const dataCsrf = await respCsrf.json().catch(() => ({}));
        if (respCsrf.ok && dataCsrf && typeof dataCsrf.csrf_token === 'string' && dataCsrf.csrf_token) {
            return dataCsrf.csrf_token;
        }
        return '';
    }

    async function postJson(url, body) {
        let csrfToken = '';
        try {
            csrfToken = await getAdminCsrfToken();
        } catch (err) {
            throw new Error(
                err && err.message
                    ? String(err.message)
                    : 'Could not refresh CSRF token'
            );
        }
        if (!csrfToken) {
            throw new Error('Session expired or invalid request token. Refresh admin and try again.');
        }
        const payload = Object.assign({}, body || {}, { csrf_token: csrfToken });
        const resp = await fetch('/' + String(url || '').replace(/^\//, ''), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        let data = {};
        try {
            data = await resp.json();
        } catch (err) {
            data = {};
        }
        if (!resp.ok) {
            const errMsg = data && data.error
                ? String(data.error)
                : ('Request failed (' + resp.status + ').');
            throw new Error(errMsg);
        }
        return data;
    }

    function selectedChoiceForManualRow(row) {
        if (!(row instanceof HTMLElement)) {
            return { kind: '', id: '', title: '' };
        }
        const active = row.querySelector('.site-health-choice-toggle .brand-player-setting-btn.is-active');
        if (active instanceof HTMLElement) {
            if (active.classList.contains('site-health-choice-delete')) {
                return { kind: 'delete', id: '', title: '' };
            }
            const id = String(active.getAttribute('data-campaign-id') || '').trim();
            const title = String(
                active.getAttribute('data-campaign-title') || active.textContent || ''
            ).trim();
            return { kind: 'campaign', id: id, title: title || id };
        }
        const select = row.querySelector('.site-health-choice-select');
        if (select instanceof HTMLSelectElement) {
            const id = String(select.value || '').trim();
            if (!id) {
                return { kind: '', id: '', title: '' };
            }
            const opt = select.selectedOptions && select.selectedOptions[0]
                ? select.selectedOptions[0]
                : null;
            const title = opt ? String(opt.textContent || '').trim() : '';
            return { kind: 'campaign', id: id, title: title || id };
        }
        return { kind: '', id: '', title: '' };
    }

    function syncManualRowFieldState(row) {
        if (!(row instanceof HTMLElement)) {
            return;
        }
        const field = row.querySelector('.site-health-orphan-clash-field');
        if (!(field instanceof HTMLElement)) {
            return;
        }
        const selected = selectedChoiceForManualRow(row);
        const hasChoice = selected.kind === 'delete'
            || (selected.kind === 'campaign' && !!selected.id);
        field.classList.toggle('is-needs-choice', !hasChoice);
        field.classList.toggle('is-chosen', hasChoice);
    }

    function syncManualFindingChecks() {
        if (!previewBodyEl) {
            return;
        }
        Array.prototype.forEach.call(
            previewBodyEl.querySelectorAll('.site-health-manual-finding'),
            (finding) => {
                if (!(finding instanceof HTMLElement)) {
                    return;
                }
                const wrap = finding.querySelector('.site-health-treat-include');
                const check = finding.querySelector('.site-health-treat-check');
                if (!(check instanceof HTMLInputElement)) {
                    return;
                }
                const rows = finding.querySelectorAll('.site-health-manual-choice-row');
                let ready = rows.length > 0;
                Array.prototype.forEach.call(rows, (row) => {
                    syncManualRowFieldState(row);
                    const selected = selectedChoiceForManualRow(row);
                    if (selected.kind === 'delete') {
                        return;
                    }
                    if (selected.kind === 'campaign' && selected.id) {
                        return;
                    }
                    ready = false;
                });
                const wasDisabled = check.disabled;
                const keepIncluded = ready && (wasDisabled || check.checked);
                setTreatIncludeState(
                    wrap || check.closest('.site-health-treat-select'),
                    keepIncluded,
                    !ready
                );
            }
        );
        syncApplyEnabledFromSelection();
    }

    function collectManualActionsFromPreview() {
        if (!previewBodyEl) {
            return [];
        }
        const actions = [];
        Array.prototype.forEach.call(
            previewBodyEl.querySelectorAll('.site-health-manual-finding'),
            (finding) => {
                if (!(finding instanceof HTMLElement)) {
                    return;
                }
                const check = finding.querySelector('.site-health-treat-check');
                if (!(check instanceof HTMLInputElement) || !check.checked || check.disabled) {
                    return;
                }
                Array.prototype.forEach.call(
                    finding.querySelectorAll('.site-health-manual-choice-row'),
                    (row) => {
                        if (!(row instanceof HTMLElement)) {
                            return;
                        }
                        const selected = selectedChoiceForManualRow(row);
                        const action = String(row.getAttribute('data-manual-action') || '').trim();
                        if (action === 'assign_orphan_home') {
                            if (selected.kind !== 'campaign' || !selected.id) {
                                return;
                            }
                            actions.push({
                                type: 'assign_orphan_home',
                                assetId: String(row.getAttribute('data-asset-id') || '').trim(),
                                campaignId: selected.id,
                                campaignTitle: selected.title,
                                filename: String(row.getAttribute('data-filename') || '').trim()
                            });
                            return;
                        }
                        if (action === 'data_container') {
                            const kind = String(row.getAttribute('data-entity-kind') || '').trim();
                            const entityId = String(row.getAttribute('data-entity-id') || '').trim();
                            const title = String(row.getAttribute('data-entity-title') || '').trim();
                            if (selected.kind === 'delete') {
                                actions.push({
                                    type: 'delete_container',
                                    kind: kind,
                                    entityId: entityId,
                                    title: title
                                });
                                return;
                            }
                            if (selected.kind === 'campaign' && selected.id) {
                                actions.push({
                                    type: 'adopt_container',
                                    kind: kind,
                                    entityId: entityId,
                                    title: title,
                                    campaignId: selected.id,
                                    campaignTitle: selected.title
                                });
                            }
                        }
                    }
                );
            }
        );
        return actions;
    }

    function manualActionLabel(action) {
        const a = action || {};
        const kind = String(a.kind || '').trim().toLowerCase();
        const kindSuffix = kind ? ' (' + kind + ')' : '';
        if (a.type === 'assign_orphan_home') {
            return 'Set catalogue home: ' + (a.filename || a.assetId || 'file') +
                ' → ' + (a.campaignTitle || a.campaignId || 'campaign');
        }
        if (a.type === 'adopt_container') {
            return 'Adopt ' + (a.title || a.entityId || 'container') + kindSuffix +
                ' into ' + (a.campaignTitle || a.campaignId || 'campaign');
        }
        if (a.type === 'delete_container') {
            return 'Delete ' + (a.title || a.entityId || 'container') + kindSuffix;
        }
        return 'Manual action';
    }

    async function executeAssignOrphanHome(assetId, campaignId) {
        const aid = String(assetId || '').trim();
        const cid = String(campaignId || '').trim();
        if (!aid || !cid) {
            throw new Error('Missing asset or campaign for catalogue-home assignment.');
        }
        const payload = await postJson('biblioteca/site-health-assign-orphan-home.php', {
            asset_id: aid,
            campaign_id: cid
        });
        if (!payload || !payload.ok) {
            throw new Error(
                payload && payload.error
                    ? String(payload.error)
                    : 'Could not set catalogue home.'
            );
        }
        return payload;
    }

    async function executeAdoptContainer(kind, entityId, campaignId) {
        const k = String(kind || '').trim();
        const eid = String(entityId || '').trim();
        const cid = String(campaignId || '').trim();
        if (!k || !eid || !cid) {
            throw new Error('Missing container or campaign for adopt.');
        }
        const payload = await postJson('biblioteca/site-health-data-adopt-container.php', {
            kind: k,
            id: eid,
            campaign_id: cid
        });
        if (!payload || !payload.ok) {
            throw new Error(
                payload && payload.error
                    ? String(payload.error)
                    : 'Could not adopt container.'
            );
        }
        return payload;
    }

    async function executeDeleteContainer(kind, entityId) {
        const k = String(kind || '').trim();
        const eid = String(entityId || '').trim();
        if (!k || !eid) {
            throw new Error('Missing container for delete.');
        }
        const payload = await postJson('biblioteca/site-health-data-delete-container.php', {
            kind: k,
            id: eid
        });
        if (!payload || !payload.ok) {
            throw new Error(
                payload && payload.error
                    ? String(payload.error)
                    : 'Could not delete container.'
            );
        }
        return payload;
    }

    async function runManualActions(actions) {
        const list = Array.isArray(actions) ? actions : [];
        let okCount = 0;
        const errors = [];
        for (let i = 0; i < list.length; i += 1) {
            const action = list[i] || {};
            try {
                if (action.type === 'assign_orphan_home') {
                    await executeAssignOrphanHome(action.assetId, action.campaignId);
                } else if (action.type === 'adopt_container') {
                    await executeAdoptContainer(action.kind, action.entityId, action.campaignId);
                } else if (action.type === 'delete_container') {
                    await executeDeleteContainer(action.kind, action.entityId);
                } else {
                    throw new Error('Unknown manual action.');
                }
                okCount += 1;
            } catch (err) {
                errors.push(err && err.message ? String(err.message) : String(err));
            }
        }
        return { okCount: okCount, errors: errors, total: list.length };
    }

    function bindManualChoiceHandlers() {
        if (!previewBodyEl || previewBodyEl.getAttribute('data-manual-choice-bound') === '1') {
            return;
        }
        previewBodyEl.setAttribute('data-manual-choice-bound', '1');
        previewBodyEl.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            const btn = target.closest('.site-health-choice-toggle .brand-player-setting-btn');
            if (!(btn instanceof HTMLElement) || !previewBodyEl.contains(btn)) {
                return;
            }
            event.preventDefault();
            const toggle = btn.closest('.site-health-choice-toggle');
            if (!(toggle instanceof HTMLElement)) {
                return;
            }
            const row = btn.closest('.site-health-manual-choice-row');
            // Selecting Delete beside a dropdown clears the select; selecting a chip
            // clears sibling Delete-only toggles in the same row.
            if (row instanceof HTMLElement) {
                const select = row.querySelector('.site-health-choice-select');
                if (select instanceof HTMLSelectElement && btn.classList.contains('site-health-choice-delete')) {
                    select.value = '';
                }
                Array.prototype.forEach.call(
                    row.querySelectorAll('.site-health-choice-toggle .brand-player-setting-btn'),
                    (el) => {
                        if (el === btn) {
                            return;
                        }
                        el.classList.remove('is-active');
                        el.setAttribute('aria-pressed', 'false');
                    }
                );
            } else {
                Array.prototype.forEach.call(
                    toggle.querySelectorAll('.brand-player-setting-btn'),
                    (el) => {
                        el.classList.remove('is-active');
                        el.setAttribute('aria-pressed', 'false');
                    }
                );
            }
            btn.classList.add('is-active');
            btn.setAttribute('aria-pressed', 'true');
            syncManualFindingChecks();
        });
        previewBodyEl.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLSelectElement)) {
                return;
            }
            if (!target.classList.contains('site-health-choice-select')) {
                return;
            }
            const row = target.closest('.site-health-manual-choice-row');
            if (row instanceof HTMLElement) {
                Array.prototype.forEach.call(
                    row.querySelectorAll('.site-health-choice-toggle .brand-player-setting-btn'),
                    (el) => {
                        el.classList.remove('is-active');
                        el.setAttribute('aria-pressed', 'false');
                    }
                );
            }
            syncManualFindingChecks();
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
            // Manual Include rows have no treatment id — Apply runs them via PHP helpers.
            if (!tid || seen[tid]) {
                return;
            }
            if (fid) {
                findingIds.push(fid);
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
        const manualActions = collectManualActionsFromPreview();
        treatApplyBtn.disabled = selected.treatmentIds.length === 0 && manualActions.length === 0;
        updateApplySummaryLine();
        syncRecommendedAction();
    }

    function bindPreviewSelectionHandlers() {
        if (!previewBodyEl) {
            return;
        }
        // Rebinding after innerHTML — clear one-shot choice binder flag.
        previewBodyEl.removeAttribute('data-manual-choice-bound');
        previewBodyEl.querySelectorAll('.site-health-treat-include-toggle [data-include]').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const button = event.currentTarget;
                if (!(button instanceof HTMLButtonElement) || button.disabled) {
                    return;
                }
                const wrap = button.closest('.site-health-treat-include');
                const include = String(button.getAttribute('data-include') || '') === '1';
                setTreatIncludeState(wrap, include, false);
                syncApplyEnabledFromSelection();
            });
        });
        previewBodyEl.querySelectorAll('.site-health-treat-check').forEach((box) => {
            box.addEventListener('change', syncApplyEnabledFromSelection);
        });
        bindManualChoiceHandlers();
        syncManualFindingChecks();
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
        const findingRows = findings.length
            ? findings.map(findingReviewRowHtml).join('')
            : '<p class="site-health-summary-empty">No findings on the current plan.</p>';

        // Grey note stays a side hint (backup). Amber assurance carries the how-to guidance.
        let previewNote = (
            'A backup first is a good idea if you want a restore point.'
        );
        if (fix.all) {
            previewNote = (
                'Choose Yes or Skip for each item (everything useful starts as Yes). ' +
                'Nothing changes until you Apply. A backup first is a good idea if you want a restore point.'
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
        const manualActions = collectManualActionsFromPreview();
        const bits = selected.labels.slice();
        manualActions.forEach((action) => {
            bits.push(manualActionLabel(action));
        });
        if (!bits.length) {
            el.innerHTML = '<strong>Apply will run:</strong> nothing selected.';
            return;
        }
        el.innerHTML = (
            '<strong>Apply will run:</strong> ' +
            escapeHtml(bits.join(' · '))
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
                : 'Run <strong>Quick health check</strong> for an everyday check, or <strong>Full health check</strong> for a slower, more thorough pass. Nothing changes until you Apply treatment.';
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
                findingsEl.innerHTML = '<p class="publish-status-empty">Nothing needs treatment. The catalogue looks healthy.</p>';
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
        try {
            csrfToken = await getAdminCsrfToken();
        } catch (err) {
            csrfToken = '';
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
            const manualActions = collectManualActionsFromPreview();
            if (!selected.treatmentIds.length && !manualActions.length) {
                setJobStatus('Select at least one finding to apply.', 'error');
                return;
            }
            const summaryBits = selected.labels.map((line) => '• ' + line);
            manualActions.forEach((action) => {
                summaryBits.push('• ' + manualActionLabel(action));
            });
            const summary = summaryBits.length ? summaryBits.join('\n') : '• (none)';
            const confirmed = typeof window.bandpromoConfirm === 'function'
                ? await window.bandpromoConfirm({
                    title: 'Apply treatment?',
                    body: (
                        'Only items set to Yes will be fixed. Skipped items stay for later.\n\n' +
                        summary
                    ),
                    confirmLabel: 'Apply treatment',
                    cancelLabel: 'Not now',
                    tone: 'good',
                })
                : window.confirm(
                    'Apply the selected treatment now?\n\n' +
                    summary +
                    '\n\nOnly items set to Yes will be fixed. Skipped items stay for later.'
                );
            if (!confirmed) {
                return;
            }
            const reportLabels = selected.labels.slice();
            manualActions.forEach((action) => {
                reportLabels.push(manualActionLabel(action));
            });
            lastTreatReport = {
                labels: reportLabels,
                treats: [],
                notes: [],
                overall: '',
            };
            if (manualActions.length) {
                setJobStatus('Applying chosen manual fixes…');
                setRunningUi(true);
                const manualResult = await runManualActions(manualActions);
                if (manualResult.errors.length) {
                    setRunningUi(false);
                    setJobStatus(
                        'Manual fixes partly failed: ' + manualResult.errors.join(' · '),
                        'error'
                    );
                    return;
                }
                lastTreatReport.notes.push(
                    'Manual: ' + manualResult.okCount + ' of ' + manualResult.total + ' applied.'
                );
            }
            if (selected.treatmentIds.length) {
                startMode('treat', {
                    treatmentIds: selected.treatmentIds,
                    findingIds: selected.findingIds,
                });
                return;
            }
            // Manual-only: close review and re-check so findings refresh.
            previewOpen = false;
            if (previewEl) {
                previewEl.hidden = true;
            }
            setUiStage('result');
            renderTreatResultPanel('Manual choices applied.');
            setJobStatus('Manual fixes applied — running a follow-up quick check…', 'success');
            startMode('check');
        });
    }

    forceBtn.addEventListener('click', async () => {
        let body = (
            'Rebuild every player-ready stream, artwork file, and playlist for this install so nothing is skipped.\n\n'
            + 'On a large catalogue this can take a while. Prefer Check → Review → Apply when artwork or streaming files are missing. '
            + 'Force stays unavailable while a health check still shows serious problems.'
        );
        if (window.bandpromoIsDeveloper) {
            body += '\n\nDeveloper: rebuilds all delivery variants and share / install icons end to end.';
        }
        const confirmed = typeof window.bandpromoConfirm === 'function'
            ? await window.bandpromoConfirm({
                title: 'Force full rebuild?',
                body: body,
                confirmLabel: 'Force full rebuild',
                cancelLabel: 'Cancel',
                tone: 'quiet',
            })
            : window.confirm('Force full rebuild?\n\n' + body);
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
            try {
                csrfToken = await getAdminCsrfToken();
            } catch (err) {
                csrfToken = '';
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
