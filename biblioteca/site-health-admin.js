/**
 * System → Status site health UI (Check / Treat / Force).
 */
(function () {
    'use strict';

    const overallEl = document.getElementById('siteHealthOverall');
    const findingsEl = document.getElementById('siteHealthFindings');
    const metaEl = document.getElementById('siteHealthMeta');
    const jobStatusEl = document.getElementById('siteHealthJobStatus');
    const logEl = document.getElementById('siteHealthLog');
    const spinnerEl = document.getElementById('siteHealthSpinner');
    const checkBtn = document.getElementById('siteHealthCheckBtn');
    const treatBtn = document.getElementById('siteHealthTreatBtn');
    const forceBtn = document.getElementById('siteHealthForceBtn');
    const stopBtn = document.getElementById('siteHealthStopBtn');
    const copyBtn = document.getElementById('siteHealthLogCopyBtn');

    if (!overallEl || !checkBtn) {
        return;
    }

    let pollTimer = null;
    let running = false;

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

    function renderPlan(plan) {
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
                : 'Run <strong>Check site health</strong> for a read-only exam. Nothing is changed until you Treat.';
        }

        if (!findingsEl) {
            return;
        }

        if (!plan || Object.keys(plan).length === 0) {
            findingsEl.innerHTML = '<p class="publish-status-empty">No check yet — start with Check site health.</p>';
            treatBtn.hidden = true;
            return;
        }

        if (findings.length === 0) {
            findingsEl.innerHTML = '<p class="publish-status-empty">Nothing needs treatment. Listener catalogue looks healthy.</p>';
            treatBtn.hidden = true;
            return;
        }

        treatBtn.hidden = false;
        const rows = findings.map((finding) => {
            const severity = String(finding.severity || 'attention');
            const title = escapeHtml(finding.title || finding.id || 'Finding');
            const body = escapeHtml(finding.body || '');
            const count = Number(finding.count || 0);
            const sample = Array.isArray(finding.items_sample) ? finding.items_sample : [];
            const sampleHtml = sample.length
                ? '<ul class="welcome-list">' + sample.slice(0, 8).map((item) => '<li>' + escapeHtml(item) + '</li>').join('') + '</ul>'
                : '';
            return (
                '<article class="publish-next-step publish-next-step--' + escapeHtml(severity === 'critical' ? 'needs_fix' : 'recommended') + '">' +
                '<strong>' + title + (count ? ' (' + count + ')' : '') + '</strong>' +
                (body ? '<p>' + body + '</p>' : '') +
                sampleHtml +
                '</article>'
            );
        }).join('');
        findingsEl.innerHTML = '<div class="publish-status-checks">' + rows + '</div>';
    }

    function setRunningUi(isRunning) {
        running = !!isRunning;
        if (spinnerEl) {
            spinnerEl.style.display = running ? '' : 'none';
        }
        checkBtn.disabled = running;
        forceBtn.disabled = running;
        if (treatBtn) {
            treatBtn.disabled = running;
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
            renderPlan(plan);
            setRunningUi(!!data.running);
            if (!data.running) {
                setOverall(data.overall || plan.overall || 'unknown');
                if (data.exit_code === 0) {
                    const overall = String(data.overall || plan.overall || '');
                    if (overall === 'healthy') {
                        setJobStatus('Check complete — site looks healthy.', { color: 'var(--success, #4ade80)' });
                    } else if (overall === 'critical' || overall === 'attention') {
                        setJobStatus('Findings ready — review and Treat when you are ready.', { color: '#f0b429' });
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
        setJobStatus(mode === 'check' ? 'Starting check…' : (mode === 'treat' ? 'Starting treatment…' : 'Starting force rebuild…'));
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
            beginPolling();
            await refreshStatus();
        } catch (err) {
            setRunningUi(false);
            setJobStatus('Could not start site health.', { color: '#f55' });
        }
    }

    checkBtn.addEventListener('click', () => {
        startMode('check');
    });

    if (treatBtn) {
        treatBtn.addEventListener('click', () => {
            if (!window.confirm('Apply recommended treatments from the latest Check?\n\nThis can register masters into Files and rebuild indexes. Delivery Force is separate.')) {
                return;
            }
            startMode('treat');
        });
    }

    forceBtn.addEventListener('click', () => {
        if (!window.confirm('Force a full listener rebuild even if Check looks healthy?\n\nThis is blocked while critical catalogue findings remain. Prefer Check → Treat for missing Files rows.')) {
            return;
        }
        startMode('force');
    });

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
