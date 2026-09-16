/**
 * System → Status site health UI (Check / Review treatment / Apply / Force).
 *
 * Brief UI; verbose evidence stays in Activity. Treatment is never silent:
 * Review (read-only preview) before Apply.
 */
(function () {
    'use strict';

    const overallEl = document.getElementById('siteHealthOverall');
    const findingsEl = document.getElementById('siteHealthFindings');
    const previewEl = document.getElementById('siteHealthTreatPreview');
    const metaEl = document.getElementById('siteHealthMeta');
    const jobStatusEl = document.getElementById('siteHealthJobStatus');
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
        visual_register_in_place: 'Register visual masters already on disk into Files (no copy).',
        listener_delivery: 'Rebuild missing or stale stream deliverables (audio, stills, video, SFX).',
        files_index_rebuild: 'Rebuild the Files → Audio index from the registry.',
        playlists: 'Republish player playlist payloads.',
        site_chrome: 'Update share images and PWA manifest.',
        container_links: 'Refresh container links and related site chrome.',
    };

    let pollTimer = null;
    let running = false;
    let lastPlan = null;
    let previewOpen = false;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setJobStatus(text, opts) {
        if (!jobStatusEl) {
            return;
        }
        const message = String(text || '').trim();
        if (!message) {
            jobStatusEl.hidden = true;
            jobStatusEl.textContent = '';
            return;
        }
        jobStatusEl.hidden = false;
        jobStatusEl.textContent = message;
        jobStatusEl.style.color = (opts && opts.color) ? opts.color : '';
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
            cls = 'badge audit-status-badge status-warn';
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

    function setPreviewMode(open) {
        previewOpen = !!open;
        if (!previewEl) {
            return;
        }
        if (!previewOpen || !lastPlan) {
            previewEl.hidden = true;
            previewEl.innerHTML = '';
            if (treatBtn) {
                treatBtn.hidden = !(lastPlan && Array.isArray(lastPlan.findings) && lastPlan.findings.length);
                treatBtn.classList.toggle('btn-primary', !treatBtn.hidden);
            }
            if (treatApplyBtn) {
                treatApplyBtn.hidden = true;
            }
            if (treatCancelBtn) {
                treatCancelBtn.hidden = true;
            }
            return;
        }

        const lines = treatmentLines(lastPlan);
        const items = lines.map((line) => {
            const countBit = line.count ? ' (' + line.count + ')' : '';
            return '<li><strong>' + escapeHtml(line.label) + escapeHtml(countBit) + '</strong></li>';
        }).join('');
        previewEl.hidden = false;
        previewEl.innerHTML = (
            '<article class="publish-next-step publish-next-step--recommended">' +
            '<strong>Proposed treatment</strong>' +
            '<p>Read-only preview. Nothing is changed until you Apply. File lists are in Activity.</p>' +
            (items ? '<ul class="welcome-list">' + items + '</ul>' : '<p>No treatments on the current plan.</p>') +
            '</article>'
        );
        if (treatBtn) {
            treatBtn.hidden = true;
            treatBtn.classList.remove('btn-primary');
        }
        if (treatApplyBtn) {
            treatApplyBtn.hidden = lines.length === 0;
            treatApplyBtn.disabled = false;
        }
        if (treatCancelBtn) {
            treatCancelBtn.hidden = false;
            treatCancelBtn.disabled = false;
        }
    }

    function renderPlan(plan) {
        lastPlan = plan && typeof plan === 'object' ? plan : null;
        const findings = Array.isArray(plan && plan.findings) ? plan.findings : [];
        const checkedAt = plan && plan.checked_at ? String(plan.checked_at) : '';
        const appVersion = plan && plan.app_version ? String(plan.app_version) : '';
        const fingerprint = plan && plan.host_fingerprint ? String(plan.host_fingerprint) : '';

        if (metaEl) {
            const bits = [];
            if (checkedAt) {
                bits.push('Last check: ' + checkedAt + ' UTC');
            }
            if (appVersion) {
                bits.push(appVersion);
            }
            if (fingerprint) {
                bits.push('host ' + fingerprint);
            }
            metaEl.innerHTML = bits.length
                ? escapeHtml(bits.join(' · '))
                : 'Run <strong>Quick health check</strong> for a routine exam, or <strong>Full health check</strong> for a deeper read-only verify. Nothing is changed until you Apply treatment.';
        }

        if (!findingsEl) {
            return;
        }

        if (!plan || Object.keys(plan).length === 0) {
            findingsEl.innerHTML = '<p class="publish-status-empty">No check yet — start with Quick health check.</p>';
            if (running) {
                return;
            }
            setPreviewMode(false);
            if (treatBtn) {
                treatBtn.hidden = true;
                treatBtn.classList.remove('btn-primary');
            }
            return;
        }

        if (findings.length === 0) {
            findingsEl.innerHTML = '<p class="publish-status-empty">Nothing needs treatment. Listener catalogue looks healthy.</p>';
            if (running) {
                return;
            }
            setPreviewMode(false);
            if (treatBtn) {
                treatBtn.hidden = true;
                treatBtn.classList.remove('btn-primary');
            }
            return;
        }

        // Brief UI: title + count + one-line body. Concrete items live in Activity.
        const rows = findings.map((finding) => {
            const severity = String(finding.severity || 'attention');
            const title = escapeHtml(finding.title || finding.id || 'Finding');
            const body = escapeHtml(finding.body || '');
            const count = Number(finding.count || 0);
            return (
                '<article class="publish-next-step publish-next-step--' + escapeHtml(severity === 'critical' ? 'needs_fix' : 'recommended') + '">' +
                '<strong>' + title + (count ? ' (' + count + ')' : '') + '</strong>' +
                (body ? '<p>' + body + '</p>' : '') +
                '</article>'
            );
        }).join('');
        findingsEl.innerHTML = '<div class="publish-status-checks">' + rows + '</div>';

        // While a job is running, setRunningUi owns chrome (status + Stop only).
        // Do not re-enable or unhide Check / Review / Force here.
        if (running) {
            return;
        }

        if (previewOpen) {
            setPreviewMode(true);
        } else if (treatBtn) {
            treatBtn.hidden = false;
            treatBtn.disabled = false;
            treatBtn.classList.add('btn-primary');
            if (treatApplyBtn) {
                treatApplyBtn.hidden = true;
            }
            if (treatCancelBtn) {
                treatCancelBtn.hidden = true;
            }
            if (previewEl) {
                previewEl.hidden = true;
            }
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
            if (running) {
                treatApplyBtn.hidden = true;
            }
        }
        if (treatCancelBtn) {
            treatCancelBtn.disabled = running;
            if (running) {
                treatCancelBtn.hidden = true;
            }
        }
        if (previewEl && running) {
            previewEl.hidden = true;
        }
        if (stopBtn) {
            stopBtn.hidden = !running;
        }
        if (running) {
            setOverall('running');
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
                        setJobStatus(doneMsg, { color: 'var(--success, #4ade80)' });
                    } else if (overall === 'critical' || overall === 'attention') {
                        let attentionMsg = previewOpen
                            ? 'Review the proposed treatment, then Apply when you are ready.'
                            : 'Findings ready — Review treatment when you are ready. Details are in Activity.';
                        if (jobMode === 'force') {
                            attentionMsg = 'Force rebuild finished with remaining findings — see Activity.';
                        } else if (jobMode === 'treat') {
                            attentionMsg = 'Treatment finished with remaining findings — see Activity.';
                        }
                        setJobStatus(attentionMsg, { color: '#f0b429' });
                    } else {
                        setJobStatus('');
                    }
                }
            } else {
                const message = data.meta && data.meta.message ? String(data.meta.message) : 'Working…';
                setJobStatus(message);
            }
            return data;
        } catch (err) {
            setJobStatus('Could not refresh site health status.', { color: '#f55' });
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

    async function startMode(mode) {
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
        try {
            const resp = await fetch('/biblioteca/site-health-run.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mode: mode, csrf_token: csrfToken }),
            });
            const data = await resp.json();
            if (!resp.ok || !data || data.ok !== true) {
                setRunningUi(false);
                setJobStatus((data && data.error) ? data.error : 'Could not start site health.', { color: '#f55' });
                return;
            }
            if (mode === 'treat') {
                setPreviewMode(false);
            }
            beginPolling();
            await refreshStatus();
        } catch (err) {
            setRunningUi(false);
            setJobStatus('Could not start site health.', { color: '#f55' });
        }
    }

    window.bandpromoStartSiteHealthQuickCheck = function bandpromoStartSiteHealthQuickCheck() {
        if (running) {
            return 'already-running';
        }
        if (!checkBtn || checkBtn.disabled) {
            return 'unavailable';
        }
        checkBtn.click();
        return 'started';
    };

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
                setJobStatus('Run Quick or Full health check first.', { color: '#f55' });
                return;
            }
            setPreviewMode(true);
            setJobStatus('Review the proposed treatment, then Apply when you are ready. Details are in Activity.', { color: '#f0b429' });
        });
    }

    if (treatCancelBtn) {
        treatCancelBtn.addEventListener('click', () => {
            setPreviewMode(false);
            setJobStatus('Findings ready — Review treatment when you are ready. Details are in Activity.', { color: '#f0b429' });
        });
    }

    if (treatApplyBtn) {
        treatApplyBtn.addEventListener('click', () => {
            const lines = treatmentLines(lastPlan || {});
            const summary = lines.length
                ? lines.map((line) => '- ' + line.label + (line.count ? ' (' + line.count + ')' : '')).join('\n')
                : '- (no treatments listed)';
            if (!window.confirm(
                'Apply the proposed treatment now?\n\n' +
                summary +
                '\n\nThis changes the catalogue / deliverables. File details are in Activity.'
            )) {
                return;
            }
            startMode('treat');
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
                    setJobStatus((data && data.error) ? data.error : 'Could not request stop.', { color: '#f55' });
                    return;
                }
                setJobStatus('Stop requested — finishing the current step, then exiting.');
            } catch (err) {
                setJobStatus('Could not request stop.', { color: '#f55' });
            }
        });
    }

    if (copyBtn && logEl) {
        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(logEl.textContent || '');
                setJobStatus('Log copied.');
            } catch (err) {
                setJobStatus('Could not copy log.', { color: '#f55' });
            }
        });
    }

    refreshStatus();
})();
