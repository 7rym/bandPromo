// Handle preset date range buttons
document.querySelectorAll('.preset-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Get the form this button belongs to
        const form = this.closest('form');
        const dateStartInput = form.querySelector('input[name="date_start"]');
        const dateEndInput = form.querySelector('input[name="date_end"]');
        const presetBtnsContainer = form.querySelector('.filter-preset-btns');
        
        // Calculate date range
        const today = new Date();
        const range = this.getAttribute('data-range');
        let startDate = new Date(today);
        
        switch(range) {
            case 'day':
                startDate = new Date(today);
                break;
            case 'week':
                startDate.setDate(today.getDate() - 7);
                break;
            case 'month':
                startDate.setDate(today.getDate() - 30);
                break;
            case 'all':
                startDate = new Date('2015-01-01');
                break;
        }
        
        const formatDate = (date) => {
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const dd = String(date.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        };
        
        dateStartInput.value = formatDate(startDate);
        dateEndInput.value = formatDate(today);
        
        presetBtnsContainer.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        form.submit();
    });
});

// Auto-submit on date input change
document.querySelectorAll('input[name="date_start"], input[name="date_end"]').forEach(input => {
    input.addEventListener('change', function() {
        const form = this.closest('form');
        if (form) {
            const presetBtnsContainer = form.querySelector('.filter-preset-btns');
            if (presetBtnsContainer) {
                presetBtnsContainer.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
            }
            form.submit();
        }
    });
});

// ===== Hourly activity chart =====
function initChart() {
    const hourlyChartCanvas = document.getElementById('hourlyChart');
    if (!hourlyChartCanvas) return;
    
    const container = hourlyChartCanvas.parentElement;
    hourlyChartCanvas.width  = container.offsetWidth;
    hourlyChartCanvas.height = container.offsetHeight;
    
    if (typeof Chart === 'undefined' || typeof hourlyDistributionData === 'undefined') return;
    
    const labels = [];
    const data   = [];
    for (let i = 0; i < 24; i++) {
        labels.push(i);
        data.push(hourlyDistributionData[i] || 0);
    }
    
    try {
        const ctx = hourlyChartCanvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Activity Count',
                    data,
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor:     'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    hoverBackgroundColor: 'rgba(118, 75, 162, 0.9)',
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top' } },
                scales: {
                    x: { offset: true, ticks: { color: '#aaa', maxRotation: 0 } },
                    y: { beginAtZero: true, min: 0 }
                }
            }
        });
    } catch (error) {
        console.error('Error creating chart:', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChart);
} else {
    initChart();
}

// ===== Mark active preset button on page load =====
function detectActivePreset() {
    const formatDate = (date) => {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    };

    const today = new Date();
    const todayStr = formatDate(today);

    const presets = {
        day:   formatDate(new Date(today)),
        week:  formatDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 7)),
        month: formatDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 30)),
        all:   '2015-01-01',
    };

    document.querySelectorAll('.filter-preset-btns').forEach(container => {
        const form = container.closest('form');
        const startInput = form.querySelector('input[name="date_start"]');
        const endInput   = form.querySelector('input[name="date_end"]');
        if (!startInput || !endInput) return;

        const currentStart = startInput.value;
        const currentEnd   = endInput.value;

        let matched = null;
        if (currentEnd === todayStr) {
            for (const [range, expectedStart] of Object.entries(presets)) {
                if (currentStart === expectedStart) { matched = range; break; }
            }
        }

        container.querySelectorAll('.preset-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.range === matched);
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', detectActivePreset);
} else {
    detectActivePreset();
}

// ===== User search filter =====
function filterUsers(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#usersList .user-item').forEach(item => {
        const name = (item.dataset.username || '').toLowerCase();
        item.style.display = name.includes(q) ? '' : 'none';
    });
}

// ===== Add/Edit user modal =====
function openUserModal(username) {
    const modal       = document.getElementById('userModal');
    const title       = document.getElementById('userModalTitle');
    const action      = document.getElementById('userModalAction');
    const uname       = document.getElementById('userModalUsername');
    const editUname   = document.getElementById('userModalEditUsername');
    const passLabel   = document.getElementById('userModalPassLabel');
    const passInput   = document.getElementById('userModalPassword');
    const form        = document.getElementById('userModalForm');

    if (username) {
        // Edit mode
        title.textContent       = '🔑 Change Password';
        action.value            = 'edit_user';
        uname.value             = username;
        uname.readOnly          = true;
        uname.classList.add('is-readonly-field');
        editUname.value         = username;
        passLabel.textContent   = 'New Password';
        passInput.name          = 'edit_password';
        passInput.value         = '';
    } else {
        // Add mode
        title.textContent       = '➕ Add User';
        action.value            = 'add_user';
        uname.value             = '';
        uname.readOnly          = false;
        uname.classList.remove('is-readonly-field');
        editUname.value         = '';
        passLabel.textContent   = 'Password';
        passInput.name          = 'new_password';
        passInput.value         = '';
    }

    modal.style.display = 'flex';
    setTimeout(() => (username ? passInput : uname).focus(), 50);
}

function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
}

// ===== Delete user =====
function deleteUser(username) {
    if (!confirm(`Delete user "${username}"? This cannot be undone.`)) return;
    document.getElementById('deleteUserTarget').value = username;
    document.getElementById('deleteUserForm').submit();
}

// ===== User detail lightbox (AJAX) =====
function openUserDetail(username) {
    const modal   = document.getElementById('userDetailModal');
    const content = document.getElementById('userDetailContent');
    content.innerHTML = '<p style="color:#999; text-align:center; padding:40px;">Loading…</p>';
    modal.style.display = 'flex';

    const params = new URLSearchParams({
        username,
        date_start: adminDateStart,
        date_end:   adminDateEnd
    });

    fetch('/biblioteca/get-user-detail.php?' + params.toString(), {
        credentials: 'same-origin'
    })
    .then(r => {
        if (!r.ok) throw new Error('Request failed: ' + r.status);
        return r.text();
    })
    .then(html => { content.innerHTML = html; })
    .catch(err => {
        content.innerHTML = `<p style="color:#dc3545; text-align:center; padding:40px;">Failed to load user data.<br><small>${err.message}</small></p>`;
    });
}

function closeUserDetail() {
    document.getElementById('userDetailModal').style.display = 'none';
}

// Close modals on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeUserModal();
        closeUserDetail();
        if (typeof closeMediaPickerModal === 'function') {
            closeMediaPickerModal();
        }
    }
});

// ── Help boxes ────────────────────────────────────────────────────────────────
function toggleHelp(key) {
    const box = document.getElementById('help-' + key);
    const btn = document.getElementById('helpBtn-' + key);
    if (!box) return;
    const opening = box.classList.contains('collapsed');
    box.classList.toggle('collapsed', !opening);
    if (btn) btn.classList.toggle('active', opening);
    localStorage.setItem('adminHelp_' + key, opening ? 'open' : 'closed');
}

// Restore help box states on page load (default: open for new users)
document.querySelectorAll('.admin-help-box').forEach(box => {
    const key   = box.id.replace('help-', '');
    const state = localStorage.getItem('adminHelp_' + key);
    const btn   = document.getElementById('helpBtn-' + key);
    if (state !== 'closed') {
        box.classList.remove('collapsed');
        if (btn) btn.classList.add('active');
    }
});

// =============================================================================
// Tab panel logic — Files, Config, Build
// (data vars injected by admin.php: adminActivePanel, adminDateStart, adminDateEnd)
// =============================================================================
        (function () {

            // ── Media sub-panels ──────────────────────────────────────────────
            const mediaCfg = {
                audio:          { accept: '.flac,.mp3,.wav',               target: 'audio'         },
                video:          { accept: '.mp4,.webm,.mov',               target: 'video'         },
                illustrations:  { accept: '.png,.jpg,.jpeg',               target: 'illustrations' },
                photos:         { accept: '.png,.jpg,.jpeg,.webp',         target: 'photos'        },
                special:        { accept: '.mp3,.mp4,.png,.jpg,.jpeg,.webp,.svg', target: 'special' },
            };
            window.activeMediaPanel = adminActivePanel;
            const buildTabLink = document.querySelector('.primary-tabs .tab-link[href*="tab=build"]');
            const recommendedBuildBtn = document.getElementById('recommendedBuildBtn');
            const operatorNotificationsToggle = document.getElementById('operatorNotificationsToggle');
            const operatorNotificationsCount = document.getElementById('operatorNotificationsCount');
            const operatorNotificationsModal = document.getElementById('operatorNotificationsModal');
            const operatorNotificationsModalBody = document.getElementById('operatorNotificationsModalBody');
            const operatorNotificationsClose = document.getElementById('operatorNotificationsClose');
            const operatorNotificationsWelcomeCard = document.getElementById('operatorNotificationsWelcomeCard');
            const operatorNotificationsWelcomeOpen = document.getElementById('operatorNotificationsWelcomeOpen');
            const operatorNotificationsWelcomeStatus = document.getElementById('operatorNotificationsWelcomeStatus');
            const operatorNotificationsWelcomeSummary = document.getElementById('operatorNotificationsWelcomeSummary');
            const toastHost = document.getElementById('adminToastHost');
            let adminCsrf = typeof adminCsrfToken === 'string' ? adminCsrfToken : '';
            let currentBuildRequired = false;
            let currentBuildAction = 'none';
            let currentBuildReasons = [];
            let latestBuildValidation = null;
            let modalTarget = null;
            let modalFiles  = [];
            let mediaPickerState = null;
            let showBundledDemoAssets = false;
            let illustrationsCoverFilter = 'all';
            const mediaReferenceFilters = {
                photos: 'all',
                video: 'all',
            };
            const mediaReferenceFilterTypes = new Set(['illustrations', 'photos', 'video']);
            let audioDisplayMode = 'master';
            let expandedAudioFile = null;
            const mediaSelectionState = new Map();
            const mediaFilesState = new Map();
            const audioInlineDetailCache = new Map();
            const audioInlineDetailErrors = new Map();
            const audioInlineDetailLoading = new Set();
            const audioInlineDetailSaving = new Set();
            let activeAudioQuickEdit = null;
            const adminQueryParams = new URLSearchParams(window.location.search);
            const pendingAudioDetailFromQuery = String(adminQueryParams.get('audio_detail') || '').trim();
            const pendingAudioDetailModeFromQuery = String(adminQueryParams.get('audio_editor') || '').trim();
            const pendingMediaFocusFromQuery = String(adminQueryParams.get('focus_file') || '').trim();
            const pendingPlaylistFocusFromQuery = String(adminQueryParams.get('focus_track') || '').trim();
            const pendingBuildRunFromQuery = adminQueryParams.get('run_recommended') === '1';
            let openedAudioDetailFromQuery = false;
            let appliedMediaFocusFromQuery = false;
            let appliedPlaylistFocusFromQuery = false;
            let triggeredBuildRunFromQuery = false;
            let deleteReferencePreview = null;

            const validationSeverityConfig = {
                'cannot-build': { label: 'Blocked', statusClass: 'status-error', rank: 4 },
                'fix-before-publish': { label: 'Fix first', statusClass: 'status-warning', rank: 3 },
                'recommended-fix': { label: 'Nice to have', statusClass: 'status-neutral', rank: 2 },
                'can-be-repaired-automatically': { label: 'Can be fixed automatically', statusClass: 'status-ok', rank: 1 },
            };
            const operatorNotificationSeverityConfig = {
                'cannot-build': { label: 'Blocked', itemClass: 'is-critical', summaryClass: 'status-error' },
                'fix-before-publish': { label: 'Fix first', itemClass: 'is-attention', summaryClass: 'status-warning' },
                'recommended-fix': { label: 'Nice to have', itemClass: 'is-recommended', summaryClass: 'status-neutral' },
                'build-step': { label: 'Ready to go live', itemClass: 'is-attention', summaryClass: 'status-warning' },
            };

            const mediaTypeLabels = {
                audio: 'Audio',
                video: 'Video',
                illustrations: 'Illustrations',
                photos: 'Photos',
                special: 'Theme Assets',
            };
            const mediaPathMap = {
                audio: '/media/audio/original',
                video: '/media/video/original',
                illustrations: '/media/img/original',
                photos: '/media/photo/original',
                special: '/media/special',
            };

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function extIcon(name) {
                const ext = String(name).split('.').pop().toLowerCase();
                if (['mp3', 'flac', 'ogg', 'wav', 'aac'].includes(ext)) return '🎵';
                if (['mp4', 'mov', 'webm'].includes(ext)) return '🎬';
                if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext)) return '🖼️';
                return '📄';
            }

            function isImage(name) {
                return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(String(name).split('.').pop().toLowerCase());
            }

            function isVideo(name) {
                return ['mp4', 'mov', 'webm'].includes(String(name).split('.').pop().toLowerCase());
            }

            function isPreviewable(name) {
                return isImage(name) || isVideo(name);
            }

            function hasEditableAudioMaster(name) {
                const ext = String(name).split('.').pop().toLowerCase();
                return ['flac', 'mp3'].includes(ext);
            }

            function buildAdminUrl(params) {
                const query = new URLSearchParams();
                Object.entries(params || {}).forEach(([key, value]) => {
                    if (value === null || value === undefined || value === '') {
                        return;
                    }
                    query.set(key, value);
                });
                return `?${query.toString()}`;
            }

            function buildAudioMetadataUrl(filename) {
                return buildAdminUrl({ tab: 'files', fpanel: 'audio', focus_file: filename, audio_detail: filename });
            }

            function buildAudioFullMetadataUrl(filename) {
                return buildAdminUrl({ tab: 'files', fpanel: 'audio', focus_file: filename, audio_detail: filename, audio_editor: 'full' });
            }

            function buildAudioFilesUrl(filename) {
                return buildAdminUrl({ tab: 'files', fpanel: 'audio', focus_file: filename });
            }

            function buildPlaylistOrderUrl(filename) {
                return buildAdminUrl({ tab: 'content', cntab: 'playlist', focus_track: filename });
            }

            function buildBuildTabUrl() {
                return `${buildAdminUrl({ tab: 'build' })}#build-log-card`;
            }

            function buildRecommendedRunUrl() {
                return `${buildAdminUrl({ tab: 'build', run_recommended: '1' })}#build-log-card`;
            }

            function formatBuildTaskLabel(task) {
                const taskLabels = {
                    'playlist-scan': 'Check the playlist order',
                    'audio-delivery': 'Prepare your songs for the website',
                    'video-delivery': 'Prepare your videos for the website',
                    'image-delivery': 'Prepare your photos and artwork for the website',
                    'social-assets': 'Refresh how links look when shared on social media',
                    'manifest': 'Update the site listing fans browse',
                };

                return taskLabels[String(task || '').trim()] || 'Finish preparing your latest changes for the website';
            }

            function formatBuildTaskSummary(state) {
                const tasks = Array.isArray(state && state.tasks) ? state.tasks : [];
                if (!tasks.length) {
                    return String(state && state.action || 'none') === 'optimize'
                        ? 'New photos or artwork are saved but not yet visible on the live site.'
                        : 'Your latest changes are saved here but not yet on the public website.';
                }

                if (tasks.length === 1) {
                    return `${formatBuildTaskLabel(tasks[0])}.`;
                }

                return `${tasks.length} steps are waiting before visitors see your latest changes.`;
            }

            function formatBuildTaskList(state) {
                const tasks = Array.isArray(state && state.tasks) ? state.tasks : [];
                if (!tasks.length) {
                    return [];
                }

                return tasks.map(task => formatBuildTaskLabel(task));
            }

            function formatBuildNextStep(state) {
                const tasks = formatBuildTaskList(state);
                const action = String(state && state.action || 'full').toLowerCase() === 'optimize' ? 'Refresh Image Files' : 'Run Publish Build';
                if (!tasks.length) {
                    return `Next: run ${action}.`;
                }

                if (tasks.length === 1) {
                    return `Next: run ${action} to ${tasks[0].charAt(0).toLowerCase() + tasks[0].slice(1)}.`;
                }

                return `Next: run ${action} to finish ${tasks.length} pending tasks.`;
            }

            function getBuildActionLabel(action) {
                return String(action || 'full').toLowerCase() === 'optimize'
                    ? 'Refresh photos & artwork'
                    : 'Update the live site';
            }

            function formatBuildHintMessage(state) {
                const actionLabel = getBuildActionLabel(state && state.action || 'full');
                const tasks = formatBuildTaskList(state);
                if (tasks.length === 1) {
                    return `⚠ ${tasks[0]} Use ${actionLabel} when you are ready.`;
                }
                if (tasks.length > 1) {
                    return `⚠ ${tasks.length} steps are waiting. Use ${actionLabel} when you are ready.`;
                }
                return String(state && state.action || 'none').toLowerCase() === 'optimize'
                    ? '⚠ New photos or artwork are not live yet. Refresh them when you are ready.'
                    : '⚠ Your latest changes are not on the public site yet. Update the site when you are ready.';
            }

            function closeOperatorNotifications() {
                if (!operatorNotificationsModal || !operatorNotificationsToggle) {
                    return;
                }
                operatorNotificationsModal.style.display = 'none';
                operatorNotificationsToggle.setAttribute('aria-expanded', 'false');
            }

            function openOperatorNotifications() {
                if (!operatorNotificationsModal || !operatorNotificationsToggle) {
                    return;
                }
                operatorNotificationsModal.style.display = 'flex';
                operatorNotificationsToggle.setAttribute('aria-expanded', 'true');
                if (operatorNotificationsClose) {
                    operatorNotificationsClose.focus();
                }
            }

            window.closeOperatorNotifications = closeOperatorNotifications;
            window.openOperatorNotifications = openOperatorNotifications;

            if (operatorNotificationsModal && operatorNotificationsModal.parentElement !== document.body) {
                document.body.appendChild(operatorNotificationsModal);
            }

            function clearRecommendedRunQuery() {
                if (!window.history || typeof window.history.replaceState !== 'function') {
                    return;
                }
                window.history.replaceState({}, '', buildBuildTabUrl());
            }

            function buildNotificationFromBuildState(buildState) {
                if (!buildState || buildState.required !== true) {
                    return null;
                }

                const action = String(buildState.action || 'full').toLowerCase() === 'optimize' ? 'optimize' : 'full';
                const taskDetails = formatBuildTaskList(buildState);
                const summaryDetails = taskDetails.length
                    ? taskDetails.map(text => ({ text }))
                    : [{ text: formatBuildTaskSummary(buildState) }];
                const introDetail = action === 'optimize'
                    ? { text: 'You changed photos or artwork. Visitors will not see the new versions until you refresh them.' }
                    : { text: 'You made changes that are saved in admin but not yet on the website fans visit.' };

                return {
                    severity: 'build-step',
                    title: action === 'optimize' ? 'New images are not live yet' : 'Your site is not up to date',
                    file: '',
                    details: [introDetail, ...summaryDetails],
                    actions: [
                        { label: getBuildActionLabel(action), action: 'run-recommended-build' },
                        { label: 'Go to Update site', href: buildBuildTabUrl() },
                    ],
                };
            }

            function buildOperatorNotificationModel(buildState, validation) {
                const attention = [];
                const recommended = [];
                const buildNotification = buildNotificationFromBuildState(buildState || {});

                if (buildNotification) {
                    attention.push(buildNotification);
                }

                const validationModel = buildValidationSummaryModel(validation);
                if (validationModel && Array.isArray(validationModel.items)) {
                    validationModel.items.forEach(item => {
                        const relevantIssues = [item.primary, ...(Array.isArray(item.extras) ? item.extras : [])]
                            .filter(issue => issue && String(issue.severity || '') !== 'can-be-repaired-automatically')
                            .sort((left, right) => (validationSeverityConfig[right.severity]?.rank || 0) - (validationSeverityConfig[left.severity]?.rank || 0));

                        if (!relevantIssues.length) {
                            return;
                        }

                        const notification = {
                            severity: relevantIssues[0].severity,
                            title: item.title,
                            file: item.file,
                            details: relevantIssues.map(issue => ({
                                label: validationSeverityConfig[issue.severity]?.label || 'Needs attention',
                                text: issue.action,
                            })),
                            actions: Array.isArray(item.actions) ? item.actions : [],
                        };

                        if (notification.severity === 'recommended-fix') {
                            recommended.push(notification);
                        } else {
                            attention.push(notification);
                        }
                    });
                }

                return {
                    attention,
                    recommended,
                    attentionCount: attention.length,
                    recommendedCount: recommended.length,
                    totalCount: attention.length + recommended.length,
                    hasCritical: attention.some(item => item.severity === 'cannot-build'),
                };
            }

            function renderOperatorNotificationItem(item) {
                const severityConfig = operatorNotificationSeverityConfig[item.severity] || operatorNotificationSeverityConfig['recommended-fix'];
                const badgeConfig = validationSeverityConfig[item.severity] || {
                    label: severityConfig.label,
                    statusClass: severityConfig.summaryClass || 'status-neutral',
                };
                const fileLine = item.file && item.file !== item.title
                    ? `<div class="operator-notifications-item-file">File: ${escapeHtml(item.file)}</div>`
                    : '';
                const detailsHtml = Array.isArray(item.details) && item.details.length
                    ? `<ul class="operator-notifications-item-list">${item.details.map(detail => {
                        const text = String(detail.text || '').trim();
                        return text !== '' ? `<li>${escapeHtml(text)}</li>` : '';
                    }).join('')}</ul>`
                    : '';
                const actionsHtml = Array.isArray(item.actions) && item.actions.length
                    ? `<div class="operator-notifications-actions">${item.actions.map(action => {
                        if (action && action.action) {
                            return `<button type="button" class="operator-notifications-action" data-operator-action="${escapeHtml(action.action)}">${escapeHtml(action.label)}</button>`;
                        }
                        return `<a class="operator-notifications-action" href="${escapeHtml(action.href || '?')}">${escapeHtml(action.label || 'Open')}</a>`;
                    }).join('')}</div>`
                    : '';

                return `
                    <article class="operator-notifications-item ${severityConfig.itemClass}">
                        <div class="operator-notifications-item-head">
                            <div>
                                <div class="operator-notifications-item-title">${escapeHtml(item.title)}</div>
                                ${fileLine}
                            </div>
                            <span class="badge audit-status-badge ${badgeConfig.statusClass}">${escapeHtml(badgeConfig.label)}</span>
                        </div>
                        ${detailsHtml}
                        ${actionsHtml}
                    </article>
                `;
            }

            function renderOperatorNotificationSections(model) {
                if (!model || model.totalCount === 0) {
                    return '<p class="operator-notifications-empty">You are all caught up. Nothing needs your attention right now.</p>';
                }

                const sections = [
                    { title: 'Do these first', count: model.attentionCount, items: model.attention },
                    { title: 'When you have time', count: model.recommendedCount, items: model.recommended },
                ].filter(section => section.count > 0);

                return sections.map(section => `
                    <section class="operator-notifications-section">
                        <div class="operator-notifications-section-head">
                            <h4>${escapeHtml(section.title)}</h4>
                            <span class="operator-notifications-section-count">${section.count} ${section.count === 1 ? 'item' : 'items'}</span>
                        </div>
                        <div class="operator-notifications-list">${section.items.map(renderOperatorNotificationItem).join('')}</div>
                    </section>
                `).join('');
            }

            function renderOperatorNotifications(buildState, validation) {
                const model = buildOperatorNotificationModel(buildState, validation);
                const html = renderOperatorNotificationSections(model);

                if (operatorNotificationsModalBody) {
                    operatorNotificationsModalBody.innerHTML = html;
                }

                if (operatorNotificationsWelcomeCard && operatorNotificationsWelcomeSummary) {
                    const welcomeDashboardMode = typeof adminWelcomeDashboardMode === 'boolean' && adminWelcomeDashboardMode;
                    operatorNotificationsWelcomeCard.style.display = welcomeDashboardMode || model.totalCount > 0 ? '' : 'none';

                    if (operatorNotificationsWelcomeStatus) {
                        operatorNotificationsWelcomeStatus.textContent = model.totalCount === 0
                            ? 'Everything looks good. Open the inbox any time to double-check.'
                            : `${model.totalCount} ${model.totalCount === 1 ? 'item needs' : 'items need'} your attention. Open the inbox to see what to do next.`;
                    }

                    if (model.totalCount === 0) {
                        operatorNotificationsWelcomeSummary.textContent = 'All clear';
                        operatorNotificationsWelcomeSummary.className = 'badge audit-status-badge status-ok';
                    } else if (model.hasCritical) {
                        operatorNotificationsWelcomeSummary.textContent = `${model.attentionCount} blocked`;
                        operatorNotificationsWelcomeSummary.className = 'badge audit-status-badge status-error';
                    } else if (model.attentionCount > 0) {
                        operatorNotificationsWelcomeSummary.textContent = `${model.attentionCount} to do first`;
                        operatorNotificationsWelcomeSummary.className = 'badge audit-status-badge status-warning';
                    } else {
                        operatorNotificationsWelcomeSummary.textContent = `${model.recommendedCount} optional`;
                        operatorNotificationsWelcomeSummary.className = 'badge audit-status-badge status-neutral';
                    }
                }

                if (operatorNotificationsCount) {
                    operatorNotificationsCount.textContent = String(model.totalCount);
                    operatorNotificationsCount.className = 'operator-notifications-count';
                    if (model.totalCount === 0) {
                        operatorNotificationsCount.classList.add('is-empty');
                    } else if (model.hasCritical) {
                        operatorNotificationsCount.classList.add('is-critical');
                    } else if (model.attentionCount > 0) {
                        operatorNotificationsCount.classList.add('is-attention');
                    }
                }
            }

            if (operatorNotificationsToggle && operatorNotificationsModal) {
                operatorNotificationsToggle.addEventListener('click', () => {
                    openOperatorNotifications();
                });
            }

            if (operatorNotificationsWelcomeOpen) {
                operatorNotificationsWelcomeOpen.addEventListener('click', () => {
                    openOperatorNotifications();
                });
            }

            if (operatorNotificationsClose) {
                operatorNotificationsClose.addEventListener('click', closeOperatorNotifications);
            }

            document.addEventListener('click', (event) => {
                const actionButton = event.target.closest('[data-operator-action]');
                if (!actionButton) {
                    return;
                }

                const action = String(actionButton.dataset.operatorAction || '').trim();
                if (action === 'run-recommended-build') {
                    event.preventDefault();
                    closeOperatorNotifications();
                    const buildTabActive = document.getElementById('tab-build')?.classList.contains('active');
                    if (!buildTabActive) {
                        window.location.href = buildRecommendedRunUrl();
                        return;
                    }
                    runRecommendedAction();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || !operatorNotificationsModal || operatorNotificationsModal.style.display === 'none') {
                    return;
                }
                closeOperatorNotifications();
            });

            function formatAudioMetadataHealthBadges(file) {
                const health = file.audio_metadata_health || {};
                const fields = health.fields || {};
                const source = String(health.source || 'latest_build_validation').toLowerCase();
                const order = [
                    ['cover', 'C'],
                    ['artist', 'A'],
                    ['title', 'T'],
                    ['release', 'R'],
                    ['description', 'D'],
                    ['lyrics', 'L'],
                ];

                return order.map(([key, shortLabel]) => {
                    const field = fields[key] || {};
                    const label = String(field.label || key || '').trim();
                    const state = String(field.state || 'unknown').toLowerCase();
                    const statusClass = state === 'good'
                        ? 'status-ok'
                        : state === 'required'
                            ? 'status-error'
                            : state === 'improvable'
                                ? 'status-warning'
                                : 'status-neutral';
                    const stateLabel = state === 'good'
                        ? (source === 'audio_master_detail' ? 'ready in saved master metadata' : 'good in the latest build check')
                        : state === 'required'
                            ? (source === 'audio_master_detail' ? 'missing required data in saved master metadata' : 'missing required data in the latest build check')
                            : state === 'improvable'
                                ? (source === 'audio_master_detail' ? 'could be improved in saved master metadata' : 'could be improved in the latest build check')
                                : (source === 'audio_master_detail' ? 'not checked in saved master metadata' : 'not checked in the latest build');
                    const title = `${label}: ${stateLabel}`;
                    return `<span class="badge audit-status-badge ${statusClass} media-file-badge media-file-field-badge" title="${escapeHtml(title)}" aria-label="${escapeHtml(title)}">${escapeHtml(shortLabel)}</span>`;
                }).join(' ');
            }

            function formatAudioMasterBadges(file) {
                const master = file.audio_master || {};
                const badges = [];

                if (audioDisplayMode === 'master' && !master.exists) {
                    badges.push('<span class="badge audit-status-badge status-warning media-file-badge" title="Master file is not available for this upload yet">Master pending</span>');
                }

                badges.push(formatAudioMetadataHealthBadges(file));

                return badges.join(' ');
            }

            const coverRoleLabels = {
                'track-cover': 'Track cover',
                'release-fallback': 'Release fallback',
                illustration: 'Illustration',
            };

            const coverOriginLabels = {
                'user-upload': 'Uploaded',
                'build-extracted': 'Build extracted',
                'build-configured': 'Build configured',
                'build-sidecar-copy': 'Build copied',
                'bundled-placeholder': 'Bundled demo',
            };

            function getFileReferenceInfo(file) {
                return (file && (file.reference_info || file.cover_info)) || {};
            }

            function getMediaReferenceFilter(type) {
                if (type === 'illustrations') {
                    return illustrationsCoverFilter;
                }
                return mediaReferenceFilters[type] || 'all';
            }

            function formatCoverInfoBadges(file) {
                const info = getFileReferenceInfo(file);
                const badges = [];
                const role = String(info.role || '').trim();
                const origin = String(info.origin || '').trim();

                if (role) {
                    const roleLabel = coverRoleLabels[role] || role;
                    const roleClass = role === 'track-cover'
                        ? 'status-ok'
                        : role === 'release-fallback'
                            ? 'status-warning'
                            : 'status-neutral';
                    badges.push(`<span class="badge audit-status-badge ${roleClass} media-file-badge" title="Role: ${escapeHtml(roleLabel)}">${escapeHtml(roleLabel)}</span>`);
                }

                if (origin && origin !== 'user-upload') {
                    const originLabel = coverOriginLabels[origin] || origin;
                    badges.push(`<span class="badge audit-status-badge status-neutral media-file-badge" title="Origin: ${escapeHtml(originLabel)}">${escapeHtml(originLabel)}</span>`);
                }

                if (info.orphan === true) {
                    badges.push('<span class="badge audit-status-badge status-warning media-file-badge" title="Not referenced by playlist, gallery, or theme settings">Orphan</span>');
                }

                return badges.join(' ');
            }

            function formatMediaReferenceBadges(type, file) {
                if (type === 'illustrations') {
                    return formatCoverInfoBadges(file);
                }

                const info = getFileReferenceInfo(file);
                const references = Array.isArray(info.references) ? info.references : [];
                const badges = [];

                if (info.orphan === true) {
                    badges.push('<span class="badge audit-status-badge status-warning media-file-badge" title="Not referenced by gallery or theme settings">Orphan</span>');
                } else if (references.length) {
                    badges.push('<span class="badge audit-status-badge status-ok media-file-badge" title="Referenced by gallery or theme settings">In use</span>');
                }

                const kinds = new Set(references.map((reference) => String(reference.kind || '')));
                if (kinds.has('gallery-item')) {
                    badges.push('<span class="badge audit-status-badge status-neutral media-file-badge" title="Used by a gallery item">Gallery</span>');
                }
                if ([...kinds].some((kind) => kind.startsWith('theme-') || kind === 'share-image')) {
                    badges.push('<span class="badge audit-status-badge status-neutral media-file-badge" title="Used by theme or share settings">Theme</span>');
                }

                return badges.join(' ');
            }

            function formatCoverInfoReferenceLine(file) {
                const info = getFileReferenceInfo(file);
                const references = Array.isArray(info.references) ? info.references : [];
                if (!references.length) {
                    return info.orphan === true
                        ? '<span class="media-cover-reference-line">Not referenced</span>'
                        : '';
                }

                const labels = references
                    .map((reference) => String(reference.label || '').trim())
                    .filter(Boolean)
                    .slice(0, 3);
                const suffix = references.length > labels.length ? ` +${references.length - labels.length} more` : '';
                const text = `Used by: ${labels.join(', ')}${suffix}`;
                return `<span class="media-cover-reference-line" title="${escapeHtml(text)}">${escapeHtml(text)}</span>`;
            }

            function matchesMediaReferenceFilter(type, file) {
                const filter = getMediaReferenceFilter(type);
                if (filter === 'all') {
                    return true;
                }

                const info = getFileReferenceInfo(file);
                if (filter === 'orphans') {
                    return info.orphan === true;
                }
                if (filter === 'referenced') {
                    return Number(info.reference_count || 0) > 0;
                }
                if (type === 'illustrations' && filter === 'track-covers') {
                    return info.role === 'track-cover';
                }
                if (type === 'illustrations' && filter === 'build-generated') {
                    return ['build-extracted', 'build-configured', 'build-sidecar-copy'].includes(String(info.origin || ''));
                }

                return true;
            }

            function filterReferencedMediaFiles(type, files) {
                if (!mediaReferenceFilterTypes.has(type)) {
                    return Array.isArray(files) ? files : [];
                }
                return (Array.isArray(files) ? files : []).filter((file) => matchesMediaReferenceFilter(type, file));
            }

            function syncMediaReferenceFilterUi() {
                document.querySelectorAll('[data-cover-filter]').forEach((button) => {
                    const active = String(button.dataset.coverFilter || '') === illustrationsCoverFilter;
                    button.classList.toggle('active', active);
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                document.querySelectorAll('[data-media-ref-filter-target]').forEach((button) => {
                    const target = String(button.dataset.mediaRefFilterTarget || '');
                    const active = getMediaReferenceFilter(target) === String(button.dataset.mediaRefFilter || '');
                    button.classList.toggle('active', active);
                    button.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }

            function getDisplayedMediaInfo(type, file) {
                const mediaFile = file || {};
                if (type === 'audio' && audioDisplayMode === 'master' && mediaFile.audio_master && mediaFile.audio_master.exists) {
                    return {
                        name: String(mediaFile.audio_master.filename || mediaFile.name || ''),
                        size: Number(mediaFile.audio_master.size) || Number(mediaFile.size) || 0,
                        downloadVariant: 'master',
                        downloadAvailable: true,
                    };
                }

                return {
                    name: String(mediaFile.name || ''),
                    size: Number(mediaFile.size) || 0,
                    downloadVariant: 'original',
                    downloadAvailable: true,
                };
            }

            async function fetchAudioMasterDetailData(filename) {
                const resp = await fetch(`/biblioteca/get-audio-master-detail.php?filename=${encodeURIComponent(filename)}`);
                const data = await resp.json();
                if (!resp.ok || data.error) {
                    throw new Error(data.error || 'Could not load track details');
                }
                return data;
            }

            function getAudioQuickEditContainer(filename) {
                const list = document.getElementById('filelist-audio');
                if (!list) return null;
                return Array.from(list.querySelectorAll('.media-file-quick-edit')).find((node) => String(node.dataset.quickEditFile || '') === filename) || null;
            }

            function setAudioQuickEditStatus(filename, message, type = '') {
                const container = getAudioQuickEditContainer(filename);
                if (!container) return;
                const statusEl = container.querySelector('[data-quick-edit-status]');
                if (!statusEl) return;
                statusEl.textContent = message || '';
                statusEl.classList.remove('audio-master-status-error', 'audio-master-status-success');
                if (type === 'error') {
                    statusEl.classList.add('audio-master-status-error');
                } else if (type === 'success') {
                    statusEl.classList.add('audio-master-status-success');
                }
            }

            function buildAudioInlineReadonlyChips(detail) {
                const coverValue = detail.sidecar_cover
                    ? 'Track cover'
                    : detail.embedded_cover_present
                        ? 'Embedded cover'
                        : detail.current_cover
                            ? 'Release cover'
                            : 'Missing';
                const hasDescription = String(detail.comment || '').trim() !== '';
                const hasLyrics = String(detail.lyrics || '').trim() !== '';
                const items = [
                    { label: 'Description', value: hasDescription ? 'Ready' : 'Missing', tone: hasDescription ? 'media-file-inline-chip-good' : 'media-file-inline-chip-amber' },
                    { label: 'Lyrics', value: hasLyrics ? 'Ready' : 'Missing', tone: hasLyrics ? 'media-file-inline-chip-good' : 'media-file-inline-chip-amber' },
                    { label: 'Cover', value: coverValue, tone: coverValue === 'Missing' ? 'media-file-inline-chip-danger' : 'media-file-inline-chip-good' },
                ];

                if (!items.length) {
                    return '';
                }

                return items.map((item) => `<span class="media-file-inline-chip ${item.tone}"><span class="media-file-inline-label">${escapeHtml(item.label)}</span>${escapeHtml(item.value)}</span>`).join('');
            }

            function getAudioQuickEditInput(container, field) {
                if (!container) return null;
                return Array.from(container.querySelectorAll('[data-quick-field]'))
                    .find((input) => String(input.dataset.quickField || '') === field) || null;
            }

            function buildAudioQuickEditFieldsPayload(filename, detail, overrides = {}) {
                const cached = detail || audioInlineDetailCache.get(filename) || {};
                const titleParts = splitAudioTitleParts(cached.title || '');
                const title = String(overrides.title ?? titleParts.title ?? '').trim();
                const version = String(overrides.version ?? titleParts.version ?? '').trim();
                return {
                    artist: String(overrides.artist ?? cached.artist ?? '').trim(),
                    title: combineAudioTitleParts(title, version),
                    album: String(overrides.album ?? cached.album ?? '').trim(),
                    date: String(overrides.date ?? cached.date ?? '').trim(),
                    tracknumber: String(overrides.tracknumber ?? cached.tracknumber ?? cached.suggested_tracknumber ?? '').trim(),
                    genre: String(overrides.genre ?? cached.genre ?? '').trim(),
                    bpm: String(overrides.bpm ?? cached.bpm ?? '').trim(),
                    initialkey: String(overrides.initialkey ?? cached.initialkey ?? '').trim(),
                    comment: String(cached.comment ?? '').trim(),
                    lyrics: String(cached.lyrics ?? '').trim(),
                };
            }

            function validateAudioQuickEditFields(fields) {
                if (!String(fields.artist || '').trim()) {
                    return 'Please fill in Artist.';
                }
                if (!String(fields.title || '').trim()) {
                    return 'Please fill in Title.';
                }
                if (!String(fields.album || '').trim()) {
                    return 'Please fill in Release name.';
                }
                if (String(fields.date || '').trim() !== '' && !/^\d{4}(?:-\d{2}-\d{2})?$/.test(String(fields.date || '').trim())) {
                    return 'Release date must use YYYY or YYYY-MM-DD.';
                }
                if (String(fields.tracknumber || '').trim() !== '' && !/^\d{1,3}$/.test(String(fields.tracknumber || '').trim())) {
                    return 'Track must be 1 to 3 digits.';
                }
                if (String(fields.bpm || '').trim() !== '' && !/^\d{1,3}$/.test(String(fields.bpm || '').trim())) {
                    return 'BPM must be 1 to 3 digits.';
                }
                if (String(fields.initialkey || '').trim().length > 3) {
                    return 'Key must be 3 characters or fewer.';
                }
                return '';
            }

            const audioQuickEditFields = [
                { key: 'artist', label: 'Artist', health: 'artist', inputType: 'text', read: (detail) => String(detail.artist || '').trim() },
                { key: 'title', label: 'Title', health: 'title', inputType: 'text', read: (detail) => String(splitAudioTitleParts(detail.title || '').title || detail.title || '').trim() },
                { key: 'version', label: 'Version', health: '', inputType: 'text', read: (detail) => String(splitAudioTitleParts(detail.title || '').version || '').trim() },
                { key: 'album', label: 'Release', health: 'release', inputType: 'text', read: (detail) => String(detail.album || '').trim() },
                { key: 'tracknumber', label: 'Track', health: '', inputType: 'text', inputMode: 'numeric', read: (detail) => String(detail.suggested_tracknumber || detail.playlist_tracknumber || '').trim() },
                { key: 'date', label: 'Release date', health: '', inputType: 'text', inputMode: 'numeric', read: (detail) => String(detail.date || '').trim() },
                { key: 'genre', label: 'Genre', health: '', inputType: 'text', read: (detail) => String(detail.genre || '').trim() },
                { key: 'bpm', label: 'BPM', health: '', inputType: 'text', inputMode: 'numeric', read: (detail) => String(detail.bpm || '').trim() },
                { key: 'initialkey', label: 'Key', health: '', inputType: 'text', read: (detail) => String(detail.initialkey || '').trim() },
            ];

            function renderAudioQuickEditChip(filename, field, detail, healthFields, isSaving) {
                const safeName = filename.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                const rawValue = field.read(detail);
                const value = rawValue || 'Missing';
                const isMissing = rawValue === '';
                const isEditing = activeAudioQuickEdit
                    && activeAudioQuickEdit.filename === filename
                    && activeAudioQuickEdit.field === field.key;
                const state = field.health && healthFields[field.health] ? healthFields[field.health].state : '';
                let tone = 'media-file-inline-chip-good';
                if (state === 'required' || isMissing) {
                    tone = 'media-file-inline-chip-danger';
                } else if (state === 'improvable') {
                    tone = 'media-file-inline-chip-amber';
                }

                if (isEditing) {
                    const inputMode = field.inputMode ? ` inputmode="${escapeHtml(field.inputMode)}"` : '';
                    return `<span class="media-file-inline-chip media-file-inline-chip-editing ${tone}" onclick="event.stopPropagation()">
                        <span class="media-file-inline-label">${escapeHtml(field.label)}</span>
                        <input class="media-file-inline-chip-input" type="${escapeHtml(field.inputType || 'text')}" data-quick-field="${escapeHtml(field.key)}" value="${escapeHtml(rawValue)}"${inputMode} ${isSaving ? 'disabled' : ''} onkeydown="handleAudioQuickEditKey(event, '${safeName}', '${field.key}')">
                        <button type="button" class="media-file-inline-chip-btn" ${isSaving ? 'disabled' : ''} onclick="event.stopPropagation(); saveAudioQuickEdit('${safeName}', '${field.key}')" title="Save ${escapeHtml(field.label)}">✓</button>
                        <button type="button" class="media-file-inline-chip-btn" ${isSaving ? 'disabled' : ''} onclick="event.stopPropagation(); cancelAudioQuickEdit('${safeName}')" title="Cancel">×</button>
                    </span>`;
                }

                return `<button type="button" class="media-file-inline-chip media-file-inline-chip-button ${tone}" ${isSaving ? 'disabled' : ''} onclick="event.stopPropagation(); editAudioQuickEditChip('${safeName}', '${field.key}')" title="Edit ${escapeHtml(field.label)}">
                    <span class="media-file-inline-label">${escapeHtml(field.label)}</span>${escapeHtml(value)}
                </button>`;
            }

            function buildAudioInlineDetailMarkup(filename) {
                if (audioInlineDetailLoading.has(filename)) {
                    return '<div class="media-file-inline-details"><span class="media-file-inline-empty">Loading track tags...</span></div>';
                }

                const error = String(audioInlineDetailErrors.get(filename) || '').trim();
                if (error) {
                    return `<div class="media-file-inline-details"><span class="media-file-inline-empty">${escapeHtml(error)}</span></div>`;
                }

                const detail = audioInlineDetailCache.get(filename);
                if (!detail) {
                    return '<div class="media-file-inline-details"><span class="media-file-inline-empty">Loading track tags...</span></div>';
                }

                const safeName = filename.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                const isSaving = audioInlineDetailSaving.has(filename);
                const health = buildAudioMetadataHealthFromDetail(detail || {});
                const healthFields = health && health.fields ? health.fields : {};
                const chips = audioQuickEditFields
                    .map((field) => renderAudioQuickEditChip(filename, field, detail, healthFields, isSaving))
                    .join('');

                return `<div class="media-file-inline-details media-file-quick-edit" data-quick-edit-file="${escapeHtml(filename)}" onclick="event.stopPropagation()">
                    <p class="media-file-quick-edit-intro">Click a tag to edit it in place. Use the full editor for cover art, description, lyrics, and packaging details.</p>
                    <div class="media-file-inline-chip-list">${chips}${buildAudioInlineReadonlyChips(detail)}</div>
                    <span class="media-file-quick-edit-status status-text" data-quick-edit-status></span>
                </div>`;
            }

            window.toggleAudioFileDetails = async function(filename) {
                const nextFilename = String(filename || '').trim();
                if (!nextFilename) return;

                if (expandedAudioFile === nextFilename) {
                    expandedAudioFile = null;
                    loadMediaList('audio');
                    return;
                }

                expandedAudioFile = nextFilename;
                loadMediaList('audio');

                if (audioInlineDetailCache.has(nextFilename) || audioInlineDetailLoading.has(nextFilename)) {
                    return;
                }

                audioInlineDetailLoading.add(nextFilename);
                audioInlineDetailErrors.delete(nextFilename);
                loadMediaList('audio');

                try {
                    const detail = await fetchAudioMasterDetailData(nextFilename);
                    audioInlineDetailCache.set(nextFilename, detail);
                } catch (error) {
                    audioInlineDetailErrors.set(nextFilename, error.message || 'Could not load track tags');
                } finally {
                    audioInlineDetailLoading.delete(nextFilename);
                    if (expandedAudioFile === nextFilename) {
                        loadMediaList('audio');
                    }
                }
            };

            function maybeOpenAudioDetailFromQuery(files) {
                if (openedAudioDetailFromQuery || !pendingAudioDetailFromQuery || activeMediaPanel !== 'audio') {
                    return;
                }
                const rows = Array.isArray(files) ? files : [];
                const match = rows.find((file) => String(file.name || '') === pendingAudioDetailFromQuery);
                if (!match || !match.audio_master || match.audio_master.editable !== true) {
                    return;
                }
                openedAudioDetailFromQuery = true;
                if (pendingAudioDetailModeFromQuery === 'full') {
                    window.openAudioMasterModal(pendingAudioDetailFromQuery);
                } else {
                    window.toggleAudioFileDetails(pendingAudioDetailFromQuery);
                }
            }

            function maybeApplyMediaFocusFromQuery(type) {
                if (appliedMediaFocusFromQuery || !pendingMediaFocusFromQuery || type !== 'audio') {
                    return;
                }
                const rows = Array.from(document.querySelectorAll('#filelist-audio .media-file-row'));
                const targetRow = rows.find((row) => String(row.dataset.file || '') === pendingMediaFocusFromQuery);
                if (!targetRow) {
                    return;
                }
                appliedMediaFocusFromQuery = true;
                rows.forEach((row) => row.classList.remove('media-file-row-focus'));
                targetRow.classList.add('media-file-row-focus');
                targetRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }

            function getMediaBasePath(type) {
                return mediaPathMap[type] || '';
            }

            function buildMediaPath(type, filename) {
                return `${getMediaBasePath(type)}/${filename}`;
            }

            function buildMediaUrl(type, filename) {
                return `${getMediaBasePath(type)}/${encodeURIComponent(filename)}`;
            }

            function inferMediaTargetFromPath(path, allowedTargets) {
                const targets = Array.isArray(allowedTargets) ? allowedTargets : [];
                const match = targets.find((target) => String(path || '').startsWith(getMediaBasePath(target) + '/'));
                return match || targets[0] || 'special';
            }

            function updatePickerFieldLabel(fieldId) {
                const input = document.getElementById(fieldId);
                const label = document.getElementById(fieldId + '_label');
                if (!input || !label) return;

                const emptyLabel = input.dataset.emptyLabel || 'No file selected';
                const rawValue = String(input.value || '').trim();
                const fileName = rawValue ? rawValue.split('/').pop() : '';
                label.textContent = fileName || emptyLabel;
                label.classList.toggle('empty', !fileName);
            }

            function setPickerFieldValue(fieldId, value) {
                const input = document.getElementById(fieldId);
                if (!input) return;
                input.value = value;
                updatePickerFieldLabel(fieldId);
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }

            function mediaListUrl(type, options = {}) {
                const params = new URLSearchParams();
                params.set('target', type);

                const includeBundled = options.includeBundled === true;
                const includeHidden = options.includeHidden === true;
                if (includeBundled) {
                    params.set('include_bundled', '1');
                }
                if (includeHidden) {
                    params.set('include_hidden', '1');
                }

                return '/biblioteca/list-media.php?' + params.toString();
            }

            function syncBundledToggleUi() {
                const toggleButtons = document.querySelectorAll('[data-bundled-toggle]');
                toggleButtons.forEach((button) => {
                    button.classList.toggle('active', showBundledDemoAssets);
                    button.setAttribute('aria-pressed', showBundledDemoAssets ? 'true' : 'false');
                    button.textContent = showBundledDemoAssets ? '◉ Demo' : '◌ Demo';
                    button.title = showBundledDemoAssets ? 'Hide bundled demo assets' : 'Show bundled demo assets';
                });
            }

            async function fetchMediaFiles(type, options = {}) {
                const includeBundled = options.includeBundled === true || showBundledDemoAssets === true;
                const includeHidden = options.includeHidden === true || showBundledDemoAssets === true;
                const resp = await fetch(mediaListUrl(type, { includeBundled, includeHidden }));
                const data = await resp.json();
                if (!resp.ok || data.error) {
                    throw new Error(data.error || ('Request failed: ' + resp.status));
                }
                return data.files || [];
            }

            function setAdminPreviewItems(files, type) {
                window._adminPreviewItems = files
                    .filter((file) => isPreviewable(file.name))
                    .map((file) => ({ src: buildMediaPath(type, file.name), name: file.name }));
                window._adminPreviewIdx = -1;
            }

            if (buildTabLink) {
                buildTabLink.addEventListener('click', (event) => {
                    if (!currentBuildRequired) return;

                    const buildTabActive = document.getElementById('tab-build')?.classList.contains('active');
                    if (buildTabActive) {
                        event.preventDefault();
                        const logCard = document.getElementById('build-log-card');
                        if (logCard) {
                            logCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                        return;
                    }

                    event.preventDefault();
                    window.location.href = '?tab=build#build-log-card';
                });
            }

            function setBuildRequiredNudge(required, reasons, action, tasks) {
                currentBuildRequired = required === true;
                currentBuildAction = typeof action === 'string' ? action : 'none';
                currentBuildReasons = Array.isArray(reasons) ? reasons : [];
                currentBuildTasks = Array.isArray(tasks) ? tasks : [];
                if (!buildTabLink) return;

                buildTabLink.classList.toggle('build-required-nudge', currentBuildRequired);
                buildTabLink.classList.toggle('build-required-pulse', currentBuildRequired);

                if (currentBuildRequired) {
                    const suffix = currentBuildTasks.length
                        ? ` (${currentBuildTasks.join(', ')})`
                        : (currentBuildReasons.length ? ` (${currentBuildReasons.join(', ')})` : '');
                    const actionLabel = getBuildActionLabel(currentBuildAction);
                    buildTabLink.title = `${actionLabel} is recommended for the current pending work` + suffix;
                } else {
                    buildTabLink.removeAttribute('title');
                }

                refreshBuildActionCopy();

                if (!recommendedBuildBtn) return;
                if (!currentBuildRequired) {
                    recommendedBuildBtn.style.display = 'none';
                    recommendedBuildBtn.textContent = '';
                    return;
                }

                recommendedBuildBtn.style.display = 'inline-block';
                recommendedBuildBtn.textContent = `⚡ Recommended: ${getBuildActionLabel(currentBuildAction)}`;
            }

            async function refreshBuildRequiredState() {
                try {
                    const resp = await fetch('/biblioteca/get-operator-notifications.php');
                    const data = await resp.json();
                    if (!resp.ok || !data || data.ok !== true) return;

                    const state = data.build_required_state || {};
                    latestBuildValidation = data.metadata_validation || null;
                    setBuildRequiredNudge(data.build_required === true, state.reasons || [], state.action || 'none', state.tasks || []);
                    renderOperatorNotifications(state, latestBuildValidation);
                    renderBuildValidationSummary(latestBuildValidation);

                    const statusEl = document.getElementById('buildStatus');
                    if (statusEl && data.build_required === true && !statusEl.textContent) {
                        statusEl.textContent = formatBuildHintMessage(state);
                        statusEl.style.color = '#f0b429';
                        statusEl.dataset.mode = 'nudge';
                    } else if (statusEl && data.build_required !== true && statusEl.dataset.mode === 'nudge') {
                        statusEl.textContent = '';
                        statusEl.removeAttribute('data-mode');
                    }

                    maybeRunRecommendedActionFromQuery();
                } catch (e) {
                    // Keep UI usable even if this hint endpoint is temporarily unavailable.
                }
            }

            function fmtSize(bytes) {
                if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
                if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
                return bytes + ' B';
            }

            function formatMediaCountSummary(files, type, options = {}) {
                const items = Array.isArray(files) ? files : [];
                const count = items.length;
                const totalBytes = items.reduce((sum, file) => sum + Math.max(0, Number(getDisplayedMediaInfo(type, file).size) || 0), 0);
                const noun = count === 1 ? 'file' : 'files';
                const totalCount = Number(options.totalCount);
                if (mediaReferenceFilterTypes.has(type) && getMediaReferenceFilter(type) !== 'all' && Number.isFinite(totalCount) && totalCount !== count) {
                    return `(${count} of ${totalCount} ${totalCount === 1 ? 'file' : 'files'} shown, ${fmtSize(totalBytes)} visible)`;
                }
                return `(${count} ${noun}, ${fmtSize(totalBytes)} total)`;
            }

            function formatDuration(seconds) {
                const total = Math.max(0, Number(seconds) || 0);
                const mins = Math.floor(total / 60);
                const secs = total % 60;
                return `${mins}:${String(secs).padStart(2, '0')}`;
            }

            function showAdminToast(message, type = 'success') {
                if (!toastHost) return;

                const toast = document.createElement('div');
                toast.className = `admin-toast ${type}`;
                toast.textContent = message;
                toastHost.appendChild(toast);

                window.setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-4px)';
                    toast.style.transition = 'opacity 150ms ease, transform 150ms ease';
                    window.setTimeout(() => {
                        toast.remove();
                    }, 180);
                }, 3200);
            }

            function getMediaSelectionState(type) {
                if (!mediaSelectionState.has(type)) {
                    mediaSelectionState.set(type, {
                        selected: new Set(),
                        lastCheckboxIndex: null,
                    });
                }
                return mediaSelectionState.get(type);
            }

            function getMediaRows(type) {
                const listEl = document.getElementById('filelist-' + type);
                if (!listEl) return [];
                return Array.from(listEl.querySelectorAll('.media-file-row[data-file]'));
            }

            function pruneMediaSelection(type, files) {
                const state = getMediaSelectionState(type);
                const allowed = new Set((Array.isArray(files) ? files : []).map((file) => String(file && file.name || '')).filter(Boolean));
                state.selected.forEach((filename) => {
                    if (!allowed.has(filename)) {
                        state.selected.delete(filename);
                    }
                });
                if (state.lastCheckboxIndex !== null) {
                    const rows = getMediaRows(type);
                    if (state.lastCheckboxIndex >= rows.length) {
                        state.lastCheckboxIndex = rows.length ? rows.length - 1 : null;
                    }
                }
            }

            function getSelectedMediaFiles(type) {
                const state = getMediaSelectionState(type);
                return getMediaRows(type)
                    .map((row) => String(row.dataset.file || ''))
                    .filter((filename) => state.selected.has(filename));
            }

            function getMediaFileState(type) {
                return Array.isArray(mediaFilesState.get(type)) ? mediaFilesState.get(type) : [];
            }

            function getSelectedMediaDetails(type) {
                const filesByName = new Map(getMediaFileState(type).map((file) => [String(file && file.name || ''), file]));
                return getSelectedMediaFiles(type)
                    .map((filename) => filesByName.get(filename))
                    .filter(Boolean);
            }

            async function preflightMediaDownloadRequest(type, variant, files) {
                const selectedFiles = Array.isArray(files) ? files.filter(Boolean) : [];
                if (!type || !selectedFiles.length) {
                    return { ok: false, error: 'No files selected' };
                }

                const payload = new URLSearchParams();
                payload.set('target', type);
                payload.set('variant', variant || 'original');
                payload.set('preflight', '1');
                selectedFiles.forEach((filename) => payload.append('filenames[]', filename));

                const resp = await fetch('/biblioteca/download-media.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: payload.toString(),
                    credentials: 'same-origin',
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data || data.ok !== true) {
                    throw new Error((data && data.error) || 'Download is unavailable right now');
                }
                return data;
            }

            async function submitMediaDownloadRequest(type, variant, files) {
                const selectedFiles = Array.isArray(files) ? files.filter(Boolean) : [];
                if (!type || !selectedFiles.length) {
                    return;
                }

                try {
                    await preflightMediaDownloadRequest(type, variant, selectedFiles);
                } catch (error) {
                    showAdminToast(error.message || 'Download is unavailable right now.', 'error');
                    return;
                }

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/biblioteca/download-media.php';
                form.style.display = 'none';

                const fields = [
                    ['target', type],
                    ['variant', variant || 'original'],
                ];

                fields.forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });

                selectedFiles.forEach((filename) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'filenames[]';
                    input.value = filename;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
                window.setTimeout(() => form.remove(), 0);
            }

            window.submitMediaDownloadRequest = submitMediaDownloadRequest;

            function resolveBulkDownloadVariant(type, explicitVariant) {
                if (type === 'audio' && (explicitVariant === '' || explicitVariant === 'current')) {
                    return audioDisplayMode;
                }
                return explicitVariant || 'original';
            }

            function syncAudioDisplayToggleUi() {
                document.querySelectorAll('[data-audio-display-toggle]').forEach((button) => {
                    const showingOriginal = audioDisplayMode === 'original';
                    const mode = showingOriginal ? 'original' : 'master';
                    button.classList.remove('media-display-toggle-master', 'media-display-toggle-original');
                    button.classList.add(showingOriginal ? 'media-display-toggle-original' : 'media-display-toggle-master');
                    button.dataset.audioDisplayMode = mode;
                    button.setAttribute('aria-pressed', showingOriginal ? 'false' : 'true');
                    button.textContent = showingOriginal ? '◉ Original' : '◉ Master';
                    button.title = showingOriginal ? 'Show master files' : 'Show original files';
                });
            }

            function syncMediaSelectionUi(type) {
                const state = getMediaSelectionState(type);
                getMediaRows(type).forEach((row) => {
                    const filename = String(row.dataset.file || '');
                    const selected = state.selected.has(filename);
                    row.classList.toggle('media-file-row-selected', selected);
                    const checkbox = row.querySelector('.media-file-select');
                    if (checkbox) {
                        checkbox.checked = selected;
                    }
                });

                const bulkDeleteBtn = document.querySelector(`[data-bulk-delete-target="${type}"]`);
                if (bulkDeleteBtn) {
                    const count = state.selected.size;
                    bulkDeleteBtn.disabled = count <= 1;
                }

                const selectedDetails = getSelectedMediaDetails(type);
                document.querySelectorAll(`[data-bulk-download-target="${type}"]`).forEach((button) => {
                    const variant = resolveBulkDownloadVariant(type, String(button.dataset.downloadVariant || 'original').trim());
                    const count = selectedDetails.length;
                    const canDownloadOriginal = count > 1;
                    const canDownloadMaster = type === 'audio'
                        && count > 1
                        && selectedDetails.every((file) => file && file.audio_master && file.audio_master.exists);
                    const enabled = variant === 'master' ? canDownloadMaster : canDownloadOriginal;
                    button.disabled = !enabled;
                    if (variant === 'master' && !enabled && count > 0) {
                        button.title = 'Every selected audio file must have a prepared copy before this download is available.';
                    } else {
                        button.removeAttribute('title');
                    }
                });
            }

            function clearMediaSelection(type) {
                const state = getMediaSelectionState(type);
                state.selected.clear();
                state.lastCheckboxIndex = null;
                syncMediaSelectionUi(type);
            }

            async function refreshAdminCsrfToken() {
                const resp = await fetch('/biblioteca/get-admin-csrf.php', {
                    credentials: 'same-origin',
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data || data.ok !== true || typeof data.csrf_token !== 'string' || !data.csrf_token) {
                    throw new Error((data && data.error) || 'Could not refresh CSRF token');
                }
                adminCsrf = data.csrf_token;
                return adminCsrf;
            }

            async function loadMediaList(type) {
                const listEl  = document.getElementById('filelist-' + type);
                const countEl = document.getElementById(type + '-count');
                if (!listEl) return;
                try {
                    const allFiles = await fetchMediaFiles(type);
                    mediaFilesState.set(type, allFiles);
                    const files = filterReferencedMediaFiles(type, allFiles);
                    pruneMediaSelection(type, allFiles);
                    const selection = getMediaSelectionState(type);
                    if (countEl) {
                        countEl.textContent = formatMediaCountSummary(files, type, {
                            totalCount: mediaReferenceFilterTypes.has(type) ? allFiles.length : files.length,
                        });
                    }
                    if (!allFiles.length) {
                        listEl.innerHTML = '<span class="text-muted">No files yet.</span>';
                        syncMediaSelectionUi(type);
                        return;
                    }
                    if (!files.length) {
                        listEl.innerHTML = '<span class="text-muted">No files match the current filter.</span>';
                        syncMediaSelectionUi(type);
                        return;
                    }
                    const basePath = getMediaBasePath(type);
                    setAdminPreviewItems(files, type);
                    listEl.innerHTML = files.map(f => {
                        const safeName = f.name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                        const url = buildMediaUrl(type, f.name);
                        const display = getDisplayedMediaInfo(type, f);
                        const selected = selection.selected.has(f.name);
                        let thumb;
                        if (isImage(f.name)) {
                            thumb = `<img class="media-file-thumb" src="${url}" alt="" loading="lazy" onclick="event.stopPropagation(); openAdminPreview('${basePath}/${safeName}', '${safeName}')">`;
                        } else if (isVideo(f.name)) {
                            thumb = `<video class="media-file-thumb" src="${url}" preload="metadata" muted onclick="event.stopPropagation(); openAdminPreview('${basePath}/${safeName}', '${safeName}')" title="Preview"></video>`;
                        } else {
                            thumb = `<span class="media-file-icon">${extIcon(f.name)}</span>`;
                        }
                        const preview = isPreviewable(f.name)
                            ? `<button class="icon-btn media-action-btn media-action-amber" title="Preview" onclick="event.stopPropagation(); openAdminPreview('${basePath}/${safeName}', '${safeName}')">👁️</button>`
                            : '';
                        const rowIsEditableAudio = type === 'audio' && f.audio_master && f.audio_master.editable;
                        const editAction = rowIsEditableAudio
                            ? `<button class="icon-btn media-action-btn media-action-good" title="Open full metadata editor" onclick="event.stopPropagation(); openAudioMasterModal('${safeName}')">✎</button>`
                            : '';
                        const downloadDisabled = type === 'audio' && display.downloadVariant === 'master' && (!f.audio_master || !f.audio_master.exists);
                        const downloadAction = `<button class="icon-btn media-action-btn media-action-good" title="Download this file" ${downloadDisabled ? 'disabled' : ''} onclick="event.stopPropagation(); submitMediaDownloadRequest('${type}', '${display.downloadVariant}', ['${safeName}'])">⬇</button>`;
                        const nameCell = type === 'audio'
                            ? `<span class="media-file-name-wrap"><span class="media-file-name">${escapeHtml(display.name || f.name)}</span><span class="media-file-meta">${formatAudioMasterBadges(f)}</span></span>`
                            : mediaReferenceFilterTypes.has(type)
                                ? `<span class="media-file-name-wrap"><span class="media-file-name">${escapeHtml(display.name || f.name)}</span><span class="media-file-meta">${formatMediaReferenceBadges(type, f)}</span>${formatCoverInfoReferenceLine(f)}</span>`
                                : `<span class="media-file-name">${escapeHtml(display.name || f.name)}</span>`;
                        const isExpandedAudio = type === 'audio' && expandedAudioFile === f.name;
                        const rowAttributes = rowIsEditableAudio
                            ? `data-editable-audio="true" tabindex="0" role="button" aria-expanded="${isExpandedAudio ? 'true' : 'false'}" title="${isExpandedAudio ? 'Collapse quick-edit' : 'Quick-edit track tags'}" onclick="toggleAudioFileDetails('${safeName}')" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggleAudioFileDetails('${safeName}'); }"`
                            : '';
                        const rowClassName = rowIsEditableAudio
                            ? `media-file-row media-file-row-clickable${selected ? ' media-file-row-selected' : ''}${isExpandedAudio ? ' media-file-row-expanded' : ''}`
                            : `media-file-row${selected ? ' media-file-row-selected' : ''}`;
                        const expandedMarkup = isExpandedAudio ? buildAudioInlineDetailMarkup(f.name) : '';
                        return `<div class="${rowClassName}" data-file="${escapeHtml(f.name)}" ${rowAttributes}>
                            <div class="media-file-row-main">
                                <label class="media-file-select-wrap" title="Select for deletion" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="media-file-select" data-target="${escapeHtml(type)}" data-file="${escapeHtml(f.name)}" ${selected ? 'checked' : ''} aria-label="Select ${escapeHtml(f.name)} for deletion">
                                </label>
                                ${thumb}
                                ${nameCell}
                                <span class="media-file-size">${fmtSize(display.size)}</span>
                                <span class="media-file-actions">${preview}${editAction}${downloadAction}<button class="icon-btn media-action-btn media-action-danger" title="Delete" onclick="event.stopPropagation(); openDeleteModal('${type}', '${safeName}')">🗑️</button></span>
                            </div>
                            ${expandedMarkup}
                        </div>`;
                    }).join('');
                    syncMediaSelectionUi(type);
                    maybeApplyMediaFocusFromQuery(type);
                    maybeOpenAudioDetailFromQuery(files);
                } catch(e) {
                    listEl.innerHTML = `<span class="text-error">Network error</span>`;
                }
            }

            // Load active panel
            loadMediaList(activeMediaPanel);

            const showBundledAssetsToggleButtons = document.querySelectorAll('[data-bundled-toggle]');
            const coverFilterToggleButtons = document.querySelectorAll('[data-cover-filter]');
            const mediaRefFilterToggleButtons = document.querySelectorAll('[data-media-ref-filter-target]');

            function setMediaReferenceFilter(type, nextValue) {
                if (type === 'illustrations') {
                    const allowed = new Set(['all', 'track-covers', 'orphans', 'build-generated']);
                    illustrationsCoverFilter = allowed.has(nextValue) ? nextValue : 'all';
                } else if (type === 'photos' || type === 'video') {
                    const allowed = new Set(['all', 'referenced', 'orphans']);
                    mediaReferenceFilters[type] = allowed.has(nextValue) ? nextValue : 'all';
                } else {
                    return;
                }
                syncMediaReferenceFilterUi();
                if (activeMediaPanel === type) {
                    loadMediaList(type);
                }
            }

            coverFilterToggleButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    setMediaReferenceFilter('illustrations', String(button.dataset.coverFilter || 'all'));
                });
            });

            mediaRefFilterToggleButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const target = String(button.dataset.mediaRefFilterTarget || '');
                    setMediaReferenceFilter(target, String(button.dataset.mediaRefFilter || 'all'));
                });
            });

            function setShowBundledDemoAssets(nextValue) {
                showBundledDemoAssets = nextValue === true;
                syncBundledToggleUi();

                if (activeMediaPanel) {
                    loadMediaList(activeMediaPanel);
                }
                if (mediaPickerState) {
                    renderMediaPickerList(mediaPickerState.activeTarget);
                }
            }

            showBundledAssetsToggleButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    setShowBundledDemoAssets(!showBundledDemoAssets);
                });
            });

            document.querySelectorAll('[data-audio-display-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    audioDisplayMode = audioDisplayMode === 'original' ? 'master' : 'original';
                    syncAudioDisplayToggleUi();
                    loadMediaList('audio');
                });
            });

            syncBundledToggleUi();
            syncMediaReferenceFilterUi();
            syncAudioDisplayToggleUi();

            // Upload modal
            const modal       = document.getElementById('mediaUploadModal');
            const filesTabEl  = document.getElementById('tab-files');
            const modalTitle  = document.getElementById('mediaModalTitle');
            const modalDrop   = document.getElementById('modalDropZone');
            const modalInput  = document.getElementById('modalFileInput');
            const modalList   = document.getElementById('modalFileList');
            const modalBtn    = document.getElementById('modalUploadBtn');
            const modalStatus = document.getElementById('modalUploadStatus');
            const mediaPickerModal = document.getElementById('mediaPickerModal');
            const mediaPickerTitle = document.getElementById('mediaPickerTitle');
            const mediaPickerTabs = document.getElementById('mediaPickerTabs');
            const mediaPickerList = document.getElementById('mediaPickerList');
            const mediaPickerStatus = document.getElementById('mediaPickerStatus');
            const mediaPickerUploadBtn = document.getElementById('mediaPickerUploadBtn');
            let filesTabDragDepth = 0;

            if (mediaPickerModal && mediaPickerModal.parentElement !== document.body) {
                document.body.appendChild(mediaPickerModal);
            }

            window.openUploadModal = function(type) {
                modalTarget = type;
                const labels = { audio: 'Add Audio', video: 'Add Video', illustrations: 'Add Illustrations', photos: 'Add Photos', special: 'Add Theme Assets' };
                if (modalTitle)  modalTitle.textContent = labels[type] || 'Add Files';
                if (modalInput)  modalInput.accept = (mediaCfg[type] || {}).accept || '*';
                modalFiles = [];
                if (modalList)   { modalList.innerHTML = ''; }
                if (modalBtn)    { modalBtn.disabled = true; }
                if (modalStatus) { modalStatus.textContent = ''; }
                if (modal) modal.style.display = 'flex';
            };

            window.closeUploadModal = function() {
                if (modal) modal.style.display = 'none';
                modalTarget = null;
                modalFiles  = [];
            };

            function renderMediaPickerTabs() {
                if (!mediaPickerTabs || !mediaPickerState) return;
                mediaPickerTabs.innerHTML = mediaPickerState.targets.map((target) => {
                    const active = target === mediaPickerState.activeTarget ? ' active' : '';
                    const label = mediaTypeLabels[target] || target;
                    return `<button type="button" class="tab-link${active}" data-picker-target="${target}">${label}</button>`;
                }).join('');
            }

            async function renderMediaPickerList(target) {
                if (!mediaPickerList || !mediaPickerState) return;
                mediaPickerState.activeTarget = target;
                renderMediaPickerTabs();
                mediaPickerStatus.textContent = 'Loading…';
                mediaPickerStatus.style.color = '#aaa';
                mediaPickerList.innerHTML = '<span class="text-muted">Loading…</span>';

                try {
                    const files = await fetchMediaFiles(target);
                    setAdminPreviewItems(files, target);

                    if (!files.length) {
                        mediaPickerList.innerHTML = '<span class="text-muted">No files in this media group yet.</span>';
                        mediaPickerStatus.textContent = 'No files found. Upload one to use it here.';
                        return;
                    }

                    mediaPickerStatus.textContent = `${files.length} file${files.length !== 1 ? 's' : ''} available in ${mediaTypeLabels[target] || target}.`;
                    mediaPickerList.innerHTML = files.map((file) => {
                        const encodedName = encodeURIComponent(file.name);
                        const safeName = escapeHtml(file.name);
                        const url = buildMediaUrl(target, file.name);
                        let thumb;

                        if (isImage(file.name)) {
                            thumb = `<img class="media-file-thumb media-picker-preview" src="${url}" alt="" loading="lazy" data-picker-target="${target}" data-filename="${encodedName}">`;
                        } else if (isVideo(file.name)) {
                            thumb = `<video class="media-file-thumb media-picker-preview" src="${url}" preload="metadata" muted data-picker-target="${target}" data-filename="${encodedName}"></video>`;
                        } else {
                            thumb = `<span class="media-file-icon">${extIcon(file.name)}</span>`;
                        }

                        const preview = isPreviewable(file.name)
                            ? `<button type="button" class="icon-btn media-picker-preview" data-picker-target="${target}" data-filename="${encodedName}">👁️</button>`
                            : '';

                        return `<div class="media-file-row media-picker-row">
                            ${thumb}
                            <span class="media-file-name">${safeName}</span>
                            <span class="media-file-size">${fmtSize(file.size)}</span>
                            ${preview}
                            <button type="button" class="icon-btn media-picker-select" data-picker-target="${target}" data-filename="${encodedName}">Use this</button>
                        </div>`;
                    }).join('');
                    mediaPickerStatus.style.color = '#aaa';
                } catch (error) {
                    mediaPickerList.innerHTML = `<span class="text-error">${escapeHtml(error.message)}</span>`;
                    mediaPickerStatus.textContent = 'Failed to load files.';
                    mediaPickerStatus.style.color = '#f55';
                }
            }

            window.openMediaPicker = function(fieldId, title, targets) {
                const input = document.getElementById(fieldId);
                if (!input || !mediaPickerModal) return;

                const allowedTargets = String(targets || '')
                    .split(',')
                    .map((value) => value.trim())
                    .filter(Boolean);

                mediaPickerState = {
                    fieldId,
                    title: title || 'Choose file',
                    targets: allowedTargets.length ? allowedTargets : ['special'],
                    activeTarget: inferMediaTargetFromPath(input.value, allowedTargets),
                };

                mediaPickerTitle.textContent = mediaPickerState.title;
                mediaPickerModal.style.display = 'flex';
                syncBundledToggleUi();
                renderMediaPickerTabs();
                renderMediaPickerList(mediaPickerState.activeTarget);
            };

            window.closeMediaPickerModal = function() {
                if (mediaPickerModal) mediaPickerModal.style.display = 'none';
                if (mediaPickerTabs) mediaPickerTabs.innerHTML = '';
                if (mediaPickerList) mediaPickerList.innerHTML = '<span class="text-muted">Choose a media type to browse files.</span>';
                if (mediaPickerStatus) mediaPickerStatus.textContent = '';
                mediaPickerState = null;
            };

            document.querySelectorAll('.media-picker-open').forEach((button) => {
                button.addEventListener('click', () => {
                    openMediaPicker(button.dataset.field, button.dataset.title, button.dataset.targets || 'special');
                });
            });

            document.querySelectorAll('.media-picker-clear').forEach((button) => {
                button.addEventListener('click', () => {
                    setPickerFieldValue(button.dataset.field, '');
                });
            });

            if (mediaPickerTabs) {
                mediaPickerTabs.addEventListener('click', (event) => {
                    const tab = event.target.closest('[data-picker-target]');
                    if (!tab || !mediaPickerState) return;
                    renderMediaPickerList(tab.dataset.pickerTarget);
                });
            }

            if (mediaPickerList) {
                mediaPickerList.addEventListener('click', (event) => {
                    const selectBtn = event.target.closest('.media-picker-select');
                    if (selectBtn && mediaPickerState) {
                        const target = selectBtn.dataset.pickerTarget;
                        const filename = decodeURIComponent(selectBtn.dataset.filename || '');
                        setPickerFieldValue(mediaPickerState.fieldId, buildMediaPath(target, filename));
                        closeMediaPickerModal();
                        return;
                    }

                    const previewTrigger = event.target.closest('.media-picker-preview');
                    if (previewTrigger) {
                        const target = previewTrigger.dataset.pickerTarget;
                        const filename = decodeURIComponent(previewTrigger.dataset.filename || '');
                        openAdminPreview(buildMediaPath(target, filename), filename);
                    }
                });
            }

            if (mediaPickerUploadBtn) {
                mediaPickerUploadBtn.addEventListener('click', () => {
                    if (!mediaPickerState) return;
                    const target = mediaPickerState.activeTarget || mediaPickerState.targets[0] || 'special';
                    closeMediaPickerModal();
                    openUploadModal(target);
                });
            }

            document.querySelectorAll('input[type="hidden"][data-empty-label]').forEach((input) => {
                updatePickerFieldLabel(input.id);
            });

            function updateModalList(files) {
                modalFiles = Array.from(files);
                modalBtn.disabled = !modalFiles.length;
                modalList.innerHTML = modalFiles.map((f, i) =>
                    `<div class="modal-file-row">${i+1}. ${f.name} <span class="media-file-size">(${fmtSize(f.size)})</span></div>`
                ).join('');
            }

            function eventHasFiles(event) {
                const types = event && event.dataTransfer && event.dataTransfer.types;
                if (!types) return false;
                return Array.from(types).includes('Files');
            }

            function setFilesTabDragActive(active) {
                if (!filesTabEl) return;
                filesTabEl.classList.toggle('drag-upload-active', active === true);
            }

            if (filesTabEl) {
                filesTabEl.addEventListener('dragenter', (event) => {
                    if (!eventHasFiles(event)) return;
                    event.preventDefault();
                    filesTabDragDepth += 1;
                    setFilesTabDragActive(true);
                    if (modal && modal.style.display !== 'flex') {
                        openUploadModal(activeMediaPanel);
                        if (modalStatus) {
                            modalStatus.textContent = 'Drop files to add them to upload list.';
                        }
                    }
                });

                filesTabEl.addEventListener('dragover', (event) => {
                    if (!eventHasFiles(event)) return;
                    event.preventDefault();
                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = 'copy';
                    }
                });

                filesTabEl.addEventListener('dragleave', (event) => {
                    if (!eventHasFiles(event)) return;
                    event.preventDefault();
                    filesTabDragDepth = Math.max(0, filesTabDragDepth - 1);
                    if (filesTabDragDepth === 0) {
                        setFilesTabDragActive(false);
                    }
                });

                filesTabEl.addEventListener('drop', (event) => {
                    if (!eventHasFiles(event)) return;
                    event.preventDefault();
                    filesTabDragDepth = 0;
                    setFilesTabDragActive(false);
                    const droppedFiles = event.dataTransfer ? event.dataTransfer.files : null;
                    if (!droppedFiles || !droppedFiles.length) return;

                    if (!modalTarget) {
                        openUploadModal(activeMediaPanel);
                    }
                    updateModalList(droppedFiles);
                    if (modalStatus) {
                        modalStatus.textContent = `${droppedFiles.length} file(s) ready. Click Upload.`;
                        modalStatus.style.color = '#aaa';
                    }
                });
            }

            // ── Delete modal ──────────────────────────────────────────────────
            const deleteModal      = document.getElementById('mediaDeleteModal');
            const deleteTitleEl    = document.getElementById('mediaDeleteTitle');
            const deleteNameEl     = document.getElementById('mediaDeleteName');
            const deleteListEl     = document.getElementById('mediaDeleteList');
            const deleteHintEl     = document.getElementById('mediaDeleteHint');
            const deleteConfirmBtn = document.getElementById('mediaDeleteConfirmBtn');
            const deleteStatusEl   = document.getElementById('mediaDeleteStatus');
            const audioMasterModal = document.getElementById('audioMasterModal');
            const audioMasterTitle = document.getElementById('audioMasterTitle');
            const audioMasterStatus = document.getElementById('audioMasterStatus');
            const audioMasterFormat = document.getElementById('audioMasterFormat');
            const audioMasterTracknumber = document.getElementById('audioMasterTracknumber');
            const audioMasterDuration = document.getElementById('audioMasterDuration');
            const audioMasterBitrate = document.getElementById('audioMasterBitrate');
            const audioMasterSampleRate = document.getElementById('audioMasterSampleRate');
            const audioMasterBitDepth = document.getElementById('audioMasterBitDepth');
            const audioMasterFilesize = document.getElementById('audioMasterFilesize');
            const audioMasterSaveBtn = document.getElementById('audioMasterSaveBtn');
            const audioMasterCoverPreviewShell = document.getElementById('audioMasterCoverPreviewShell');
            const audioMasterCoverPreview = document.getElementById('audioMasterCoverPreview');
            const audioMasterCoverPlaceholder = document.getElementById('audioMasterCoverPlaceholder');
            const audioMasterCoverPath = document.getElementById('audioMasterFieldCoverPath');
            const audioMasterCoverClearBtn = document.getElementById('audioMasterCoverClearBtn');
            const audioMasterDescriptionCount = document.getElementById('audioMasterDescriptionCount');
            const audioMasterVersionField = document.getElementById('audioMasterFieldVersion');
            const audioMasterForm = document.getElementById('audioMasterForm');
            let deleteTarget = null;
            let deleteFiles  = [];
            let activeAudioMasterFile = null;
            let activeAudioMasterDetail = null;
            let audioMasterCoverMode = 'preserve';

            const audioMasterFields = {
                title: document.getElementById('audioMasterFieldTitle'),
                artist: document.getElementById('audioMasterFieldArtist'),
                album: document.getElementById('audioMasterFieldAlbum'),
                date: document.getElementById('audioMasterFieldDate'),
                bpm: document.getElementById('audioMasterFieldBpm'),
                initialkey: document.getElementById('audioMasterFieldInitialkey'),
                genre: document.getElementById('audioMasterFieldGenre'),
                comment: document.getElementById('audioMasterFieldComment'),
                lyrics: document.getElementById('audioMasterFieldLyrics'),
            };

            function audioMasterCoverPreviewUrl(detail) {
                const selected = audioMasterCoverPath ? String(audioMasterCoverPath.value || '').trim() : '';
                if (selected) {
                    return selected;
                }
                if (detail && detail.sidecar_cover_url) {
                    return String(detail.sidecar_cover_url);
                }
                if (detail && detail.sidecar_cover) {
                    return `/media/img/original/${encodeURIComponent(detail.sidecar_cover)}`;
                }
                return detail && detail.current_cover_url ? String(detail.current_cover_url) : '';
            }

            function setAudioMasterCoverMode(mode, options = {}) {
                audioMasterCoverMode = mode === 'set' || mode === 'clear' ? mode : 'preserve';
                if (audioMasterCoverPath) {
                    audioMasterCoverPath.dataset.emptyLabel = audioMasterCoverMode === 'clear'
                        ? 'Configured release cover will be used after save'
                        : 'No new cover selected';
                }
            }

            function normalizeAudioMasterDateValue(value) {
                const normalized = String(value || '').trim();
                return /^\d{4}-\d{2}-\d{2}$/.test(normalized) ? normalized : '';
            }

            function splitAudioTitleParts(value) {
                const combined = String(value || '').trim();
                if (!combined) {
                    return { title: '', version: '' };
                }
                const match = combined.match(/^(.*?)(?:\s*\[([^\[\]]+)\])$/);
                if (!match) {
                    return { title: combined, version: '' };
                }
                const baseTitle = String(match[1] || '').trim();
                const version = String(match[2] || '').trim();
                if (!baseTitle || !version) {
                    return { title: combined, version: '' };
                }
                return { title: baseTitle, version };
            }

            function combineAudioTitleParts(title, version) {
                const normalizedTitle = String(title || '').trim();
                const normalizedVersion = String(version || '').trim();
                if (!normalizedVersion) {
                    return normalizedTitle;
                }
                return `${normalizedTitle} [${normalizedVersion}]`;
            }

            function validateAudioMasterFields(fields) {
                const requiredOrder = [
                    ['album', 'Release name'],
                    ['date', 'Release date'],
                    ['artist', 'Artist'],
                    ['title', 'Title'],
                ];
                const missing = requiredOrder.find(([key]) => String(fields[key] || '').trim() === '');
                if (missing) {
                    return `Please fill in ${missing[1]}.`;
                }
                if (String(fields.bpm || '').trim() !== '' && !/^\d{1,3}$/.test(String(fields.bpm || '').trim())) {
                    return 'BPM must be 1 to 3 digits.';
                }
                if (String(fields.initialkey || '').trim().length > 3) {
                    return 'Key must be 3 characters or fewer.';
                }
                return '';
            }

            async function persistAudioMasterMetadata(filename, fields, options = {}) {
                const coverPath = String(options.cover_path || '').trim();
                const coverMode = String(options.cover_mode || 'preserve');

                const saveMetadata = async (csrfToken) => {
                    const resp = await fetch('/biblioteca/save-audio-master-detail.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            filename,
                            fields,
                            cover_path: coverPath,
                            cover_mode: coverMode,
                            csrf_token: csrfToken,
                        }),
                    });
                    const data = await resp.json().catch(() => ({}));
                    return { resp, data };
                };

                let { resp, data } = await saveMetadata(adminCsrf);
                if (resp.status === 403 && data && data.error === 'Invalid CSRF token') {
                    const freshToken = await refreshAdminCsrfToken();
                    ({ resp, data } = await saveMetadata(freshToken));
                }

                if (!resp.ok || data.error) {
                    throw new Error(data.error || 'Could not save metadata');
                }

                return data;
            }

            async function handleAudioMetadataSaveResult(filename, data, options = {}) {
                const detail = data.detail || {};
                audioInlineDetailCache.set(filename, detail);
                audioInlineDetailErrors.delete(filename);

                if (options.updateModal !== false && activeAudioMasterFile === filename) {
                    if (audioMasterTitle) audioMasterTitle.textContent = buildAudioMasterHeading(detail);
                    setAudioMasterCoverMode('preserve');
                    if (audioMasterCoverPath) {
                        setPickerFieldValue('audioMasterFieldCoverPath', '');
                    }
                    setAudioMasterSummary(detail);
                    setAudioMasterFormValues(detail);
                    const successMessage = data.no_change
                        ? 'No changes to save.'
                        : data.warning
                            ? data.warning
                            : (Array.isArray(data.auto_tasks) && data.auto_tasks.includes('playlist-scan')
                                ? 'Track details saved. Validation refreshed.'
                                : 'Track details saved.');
                    setAudioMasterStatus(successMessage, data.warning ? 'error' : 'success');
                }

                if (data.build_required_state) {
                    setBuildRequiredNudge(
                        data.build_required === true,
                        data.build_required_state.reasons || [],
                        data.build_required_state.action || 'none',
                        data.build_required_state.tasks || []
                    );
                }
                await refreshBuildRequiredState();
                updateAudioFileRowMetadata(filename, detail);
                if (expandedAudioFile === filename) {
                    loadMediaList('audio');
                }

                if (options.showToast !== false) {
                    showAdminToast(data.no_change
                        ? 'No changes were saved.'
                        : Array.isArray(data.auto_tasks) && data.auto_tasks.includes('playlist-scan')
                            ? 'Track details updated and validation refreshed.'
                            : 'Track details updated.');
                }

                return detail;
            }

            window.editAudioQuickEditChip = function(filename, field) {
                const nextFilename = String(filename || '').trim();
                const nextField = String(field || '').trim();
                if (!nextFilename || !nextField || audioInlineDetailSaving.has(nextFilename)) {
                    return;
                }
                activeAudioQuickEdit = { filename: nextFilename, field: nextField };
                loadMediaList('audio');
                window.setTimeout(() => {
                    const container = getAudioQuickEditContainer(nextFilename);
                    const input = getAudioQuickEditInput(container, nextField);
                    if (input) {
                        input.focus();
                        if (typeof input.select === 'function') {
                            input.select();
                        }
                    }
                }, 0);
            };

            window.handleAudioQuickEditKey = function(event, filename, field) {
                event.stopPropagation();
                if (event.key === 'Enter') {
                    event.preventDefault();
                    window.saveAudioQuickEdit(filename, field);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    window.cancelAudioQuickEdit(filename);
                }
            };

            window.saveAudioQuickEdit = async function(filename, field) {
                const nextFilename = String(filename || '').trim();
                const nextField = String(field || '').trim();
                if (!nextFilename || audioInlineDetailSaving.has(nextFilename)) {
                    return;
                }

                const container = getAudioQuickEditContainer(nextFilename);
                const input = getAudioQuickEditInput(container, nextField);
                if (!input) {
                    return;
                }
                const overrides = { [nextField]: String(input.value || '') };
                const fields = buildAudioQuickEditFieldsPayload(nextFilename, null, overrides);
                const validationError = validateAudioQuickEditFields(fields);
                if (validationError) {
                    setAudioQuickEditStatus(nextFilename, validationError, 'error');
                    return;
                }

                audioInlineDetailSaving.add(nextFilename);
                setAudioQuickEditStatus(nextFilename, 'Saving…');
                loadMediaList('audio');

                try {
                    const data = await persistAudioMasterMetadata(nextFilename, fields, { cover_mode: 'preserve' });
                    activeAudioQuickEdit = null;
                    await handleAudioMetadataSaveResult(nextFilename, data, { updateModal: activeAudioMasterFile === nextFilename });
                    const successMessage = data.no_change
                        ? 'No changes to save.'
                        : data.warning
                            ? data.warning
                            : (Array.isArray(data.auto_tasks) && data.auto_tasks.includes('playlist-scan')
                                ? 'Tags saved. Validation refreshed.'
                                : 'Tags saved.');
                    setAudioQuickEditStatus(nextFilename, successMessage, data.warning ? 'error' : 'success');
                } catch (error) {
                    setAudioQuickEditStatus(nextFilename, error.message || 'Could not save tags', 'error');
                } finally {
                    audioInlineDetailSaving.delete(nextFilename);
                    if (expandedAudioFile === nextFilename) {
                        loadMediaList('audio');
                    }
                }
            };

            window.cancelAudioQuickEdit = function(filename) {
                const nextFilename = String(filename || '').trim();
                if (!nextFilename) {
                    return;
                }
                if (activeAudioQuickEdit && activeAudioQuickEdit.filename === nextFilename) {
                    activeAudioQuickEdit = null;
                }
                setAudioQuickEditStatus(nextFilename, '');
                if (expandedAudioFile === nextFilename) {
                    loadMediaList('audio');
                }
            };

            function buildAudioMasterHeading(detail) {
                const artist = String(detail && detail.artist || '').trim();
                const title = String(detail && detail.title || '').trim();
                const release = String(detail && detail.album || '').trim();

                if (artist && title) {
                    return `${artist} · ${title}`;
                }
                if (title) {
                    return title;
                }
                if (release) {
                    return `Track details · ${release}`;
                }
                return 'Track details';
            }

            function updateAudioMasterDescriptionCounter() {
                if (!audioMasterDescriptionCount || !audioMasterFields.comment) return;
                audioMasterDescriptionCount.textContent = String((audioMasterFields.comment.value || '').length);
            }

            function buildAudioMetadataHealthFromDetail(detail) {
                const hasText = (value) => String(value || '').trim() !== '';
                const hasCover = Boolean((detail && detail.sidecar_cover) || (detail && detail.embedded_cover_present) || (detail && detail.current_cover));
                return {
                    inspected: true,
                    source: 'audio_master_detail',
                    fields: {
                        cover: { label: 'Cover', state: hasCover ? 'good' : 'required' },
                        artist: { label: 'Artist', state: hasText(detail && detail.artist) ? 'good' : 'required' },
                        title: { label: 'Title', state: hasText(detail && detail.title) ? 'good' : 'required' },
                        release: { label: 'Release', state: hasText(detail && detail.album) ? 'good' : 'improvable' },
                        description: { label: 'Description', state: hasText(detail && detail.comment) ? 'good' : 'improvable' },
                        lyrics: { label: 'Lyrics', state: hasText(detail && detail.lyrics) ? 'good' : 'improvable' },
                    },
                };
            }

            function updateAudioFileRowMetadata(filename, detail) {
                const list = document.getElementById('filelist-audio');
                if (!list || !filename) return;
                audioInlineDetailCache.set(filename, detail || {});
                audioInlineDetailErrors.delete(filename);
                const row = Array.from(list.querySelectorAll('.media-file-row')).find((candidate) => String(candidate.dataset.file || '') === filename);
                if (!row) return;
                const meta = row.querySelector('.media-file-meta');
                if (!meta) return;

                meta.innerHTML = formatAudioMasterBadges({
                    name: filename,
                    original_format: String(filename).split('.').pop() || '',
                    audio_master: {
                        exists: true,
                        editable: true,
                        format: detail && detail.format ? detail.format : String(filename).split('.').pop() || '',
                    },
                    audio_metadata_health: buildAudioMetadataHealthFromDetail(detail || {}),
                });
            }

            function syncAudioMasterCoverUi(detail) {
                const data = detail || activeAudioMasterDetail || {};
                const currentBuildCover = String(data.current_cover || '').trim();
                const sidecarCover = String(data.sidecar_cover || '').trim();
                const coverParts = [];

                coverParts.push(currentBuildCover
                    ? 'Current cover is ready'
                    : 'No current cover yet');

                if (sidecarCover) {
                    coverParts.push('A track-specific image will override the release cover after save');
                } else if (data.embedded_cover_present) {
                    coverParts.push('Artwork is already embedded in the track');
                } else {
                    coverParts.push('The release cover is currently being used');
                }

                if (audioMasterCoverPreviewShell) {
                    audioMasterCoverPreviewShell.title = coverParts.join(' · ');
                }

                const previewUrl = audioMasterCoverPreviewUrl(data);
                if (audioMasterCoverPreview) {
                    if (previewUrl) {
                        audioMasterCoverPreview.src = previewUrl;
                        audioMasterCoverPreview.style.display = 'block';
                    } else {
                        audioMasterCoverPreview.removeAttribute('src');
                        audioMasterCoverPreview.style.display = 'none';
                    }
                }
                if (audioMasterCoverPlaceholder) {
                    audioMasterCoverPlaceholder.style.display = previewUrl ? 'none' : 'block';
                }
            }

            function setAudioMasterStatus(message, type = '') {
                if (!audioMasterStatus) return;
                audioMasterStatus.textContent = message || '';
                audioMasterStatus.classList.remove('audio-master-status-error', 'audio-master-status-success');
                if (type === 'error') {
                    audioMasterStatus.classList.add('audio-master-status-error');
                } else if (type === 'success') {
                    audioMasterStatus.classList.add('audio-master-status-success');
                }
            }

            function setAudioMasterSummary(detail) {
                activeAudioMasterDetail = detail || {};
                if (audioMasterTracknumber) {
                    const tracknumber = String(detail.playlist_tracknumber || detail.suggested_tracknumber || '').trim();
                    audioMasterTracknumber.textContent = tracknumber || '—';
                }
                if (audioMasterFormat) audioMasterFormat.textContent = String(detail.format || '—').toUpperCase();
                if (audioMasterDuration) audioMasterDuration.textContent = detail.duration_seconds ? formatDuration(detail.duration_seconds) : '—';
                if (audioMasterBitrate) audioMasterBitrate.textContent = detail.bitrate_kbps ? `${detail.bitrate_kbps} kbps` : '—';
                if (audioMasterSampleRate) audioMasterSampleRate.textContent = detail.sample_rate_hz ? `${detail.sample_rate_hz} Hz` : '—';
                if (audioMasterBitDepth) audioMasterBitDepth.textContent = detail.bit_depth ? `${detail.bit_depth}-bit` : '—';
                if (audioMasterFilesize) audioMasterFilesize.textContent = detail.file_size_bytes ? fmtSize(detail.file_size_bytes) : '—';
                syncAudioMasterCoverUi(detail);
            }

            function setAudioMasterFormValues(detail) {
                const titleParts = splitAudioTitleParts(detail && typeof detail.title === 'string' ? detail.title : '');
                if (audioMasterFields.title) {
                    audioMasterFields.title.value = titleParts.title;
                }
                if (audioMasterVersionField) {
                    audioMasterVersionField.value = titleParts.version;
                }
                Object.entries(audioMasterFields).forEach(([key, input]) => {
                    if (!input || key === 'title') return;
                    if (key === 'date') {
                        input.value = normalizeAudioMasterDateValue(detail && typeof detail[key] === 'string' ? detail[key] : '');
                        return;
                    }
                    input.value = detail && typeof detail[key] === 'string' ? detail[key] : '';
                });
                updateAudioMasterDescriptionCounter();
            }

            async function loadAudioMasterDetails(filename) {
                if (!filename) return;
                setAudioMasterStatus('Loading…');
                if (audioMasterSaveBtn) audioMasterSaveBtn.disabled = true;
                setAudioMasterCoverMode('preserve');
                if (audioMasterCoverPath) {
                    setPickerFieldValue('audioMasterFieldCoverPath', '');
                }
                try {
                    const data = await fetchAudioMasterDetailData(filename);
                    audioInlineDetailCache.set(filename, data);
                    audioInlineDetailErrors.delete(filename);
                    if (audioMasterTitle) audioMasterTitle.textContent = buildAudioMasterHeading(data);
                    setAudioMasterSummary(data);
                    setAudioMasterFormValues(data);
                    setAudioMasterStatus('Ready to edit.', 'success');
                    if (audioMasterSaveBtn) audioMasterSaveBtn.disabled = false;
                } catch (error) {
                    setAudioMasterSummary({});
                    setAudioMasterFormValues({});
                    setAudioMasterStatus(error.message || 'Could not load track details', 'error');
                }
            }

            window.openAudioMasterModal = function(filename) {
                activeAudioMasterFile = filename;
                if (audioMasterModal) audioMasterModal.style.display = 'flex';
                setAudioMasterCoverMode('preserve');
                if (audioMasterCoverPath) {
                    setPickerFieldValue('audioMasterFieldCoverPath', '');
                }
                setAudioMasterSummary({});
                setAudioMasterFormValues({});
                loadAudioMasterDetails(filename);
            };

            window.closeAudioMasterModal = function() {
                if (audioMasterModal) audioMasterModal.style.display = 'none';
                activeAudioMasterFile = null;
                activeAudioMasterDetail = null;
                setAudioMasterCoverMode('preserve');
                if (audioMasterCoverPath) {
                    setPickerFieldValue('audioMasterFieldCoverPath', '');
                }
                if (audioMasterForm) audioMasterForm.reset();
                setAudioMasterSummary({});
                setAudioMasterStatus('');
            };

            if (audioMasterCoverPath) {
                audioMasterCoverPath.addEventListener('input', () => {
                    if (audioMasterCoverMode !== 'clear') {
                        setAudioMasterCoverMode(String(audioMasterCoverPath.value || '').trim() !== '' ? 'set' : 'preserve', { refreshLabel: false });
                    }
                    syncAudioMasterCoverUi(activeAudioMasterDetail || {});
                });
            }

            if (audioMasterCoverClearBtn) {
                audioMasterCoverClearBtn.addEventListener('click', () => {
                    if (audioMasterCoverPath) {
                        audioMasterCoverPath.value = '';
                    }
                    setAudioMasterCoverMode('clear');
                    syncAudioMasterCoverUi(activeAudioMasterDetail || {});
                });
            }

            if (audioMasterFields.comment) {
                audioMasterFields.comment.addEventListener('input', updateAudioMasterDescriptionCounter);
            }

            // ── Admin media preview — powered by biblioteca/lightbox.js ──────────
            let _adminLb = null;

            function getAdminLightbox() {
                if (_adminLb) {
                    return _adminLb;
                }

                const overlay = document.getElementById('adminPreviewLightbox');
                if (!overlay || typeof Lightbox !== 'function') {
                    return null;
                }

                _adminLb = new Lightbox({
                    overlayId:  'adminPreviewLightbox',
                    imgId:      'adminPreviewImg',
                    vidId:      'adminPreviewVid',
                    prevBtnId:  'adminPreviewPrev',
                    nextBtnId:  'adminPreviewNext',
                    captionId:  'adminPreviewCaption',
                    // No contentSelector: close fires when clicking the backdrop itself
                });

                return _adminLb;
            }

            window.openAdminPreview = function(src, name) {
                const lightbox = getAdminLightbox();
                if (!lightbox) return;

                const items = window._adminPreviewItems || [];
                lightbox.setItems(items);
                const idx = items.findIndex(i => i.src === src);
                if (idx >= 0) {
                    lightbox.openAt(idx);
                } else {
                    const ext = name.split('.').pop().toLowerCase();
                    lightbox.open(src, name, ['mp4','mov','webm'].includes(ext) ? 'video' : 'image');
                }
            };
            window.prevAdminPreview  = (e) => {
                const lightbox = getAdminLightbox();
                if (lightbox) lightbox.prev(e);
            };
            window.nextAdminPreview  = (e) => {
                const lightbox = getAdminLightbox();
                if (lightbox) lightbox.next(e);
            };
            window.closeAdminPreview = ()  => {
                const lightbox = getAdminLightbox();
                if (lightbox) lightbox.close();
            };

            document.querySelectorAll('[data-bulk-delete-target]').forEach((button) => {
                const target = String(button.dataset.bulkDeleteTarget || '').trim();
                syncMediaSelectionUi(target);
                button.addEventListener('click', () => {
                    const files = getSelectedMediaFiles(target);
                    if (!files.length) return;
                    openDeleteModal(target, files);
                });
            });

            document.querySelectorAll('[data-bulk-download-target]').forEach((button) => {
                const target = String(button.dataset.bulkDownloadTarget || '').trim();
                syncMediaSelectionUi(target);
                button.addEventListener('click', () => {
                    const files = getSelectedMediaFiles(target);
                    if (files.length <= 1) return;
                    submitMediaDownloadRequest(target, resolveBulkDownloadVariant(target, String(button.dataset.downloadVariant || 'original').trim()), files);
                });
            });

            document.querySelectorAll('.media-file-list').forEach((listEl) => {
                listEl.addEventListener('click', (event) => {
                    const checkbox = event.target.closest('.media-file-select');
                    if (!checkbox) return;

                    const type = String(checkbox.dataset.target || '').trim();
                    const filename = String(checkbox.dataset.file || '').trim();
                    if (!type || !filename) return;

                    const state = getMediaSelectionState(type);
                    const rows = getMediaRows(type);
                    const clickedIndex = rows.findIndex((row) => String(row.dataset.file || '') === filename);
                    const shouldSelect = checkbox.checked;

                    if (event.shiftKey && state.lastCheckboxIndex !== null && clickedIndex >= 0) {
                        const start = Math.min(state.lastCheckboxIndex, clickedIndex);
                        const end = Math.max(state.lastCheckboxIndex, clickedIndex);
                        rows.slice(start, end + 1).forEach((row) => {
                            const rowFilename = String(row.dataset.file || '');
                            if (!rowFilename) return;
                            if (shouldSelect) {
                                state.selected.add(rowFilename);
                            } else {
                                state.selected.delete(rowFilename);
                            }
                        });
                    } else if (shouldSelect) {
                        state.selected.add(filename);
                    } else {
                        state.selected.delete(filename);
                    }

                    state.lastCheckboxIndex = clickedIndex >= 0 ? clickedIndex : state.lastCheckboxIndex;
                    syncMediaSelectionUi(type);
                }, true);
            });

            function formatDeleteReferenceParts(summary) {
                const parts = [];
                if (summary.playlist_tracks) parts.push(`${summary.playlist_tracks} playlist entr${summary.playlist_tracks === 1 ? 'y' : 'ies'}`);
                if (summary.playlist_covers) parts.push(`${summary.playlist_covers} playlist cover reference${summary.playlist_covers === 1 ? '' : 's'}`);
                if (summary.gallery_items) parts.push(`${summary.gallery_items} gallery item${summary.gallery_items === 1 ? '' : 's'}`);
                if (summary.theme_assets) parts.push(`${summary.theme_assets} theme setting reference${summary.theme_assets === 1 ? '' : 's'}`);
                if (summary.release_fallbacks) parts.push(`${summary.release_fallbacks} release fallback reference${summary.release_fallbacks === 1 ? '' : 's'}`);
                return parts;
            }

            function buildMediaReferenceDeleteExtraHint(data, filenames, target) {
                if (!mediaReferenceFilterTypes.has(target)) {
                    return [];
                }

                const files = Array.isArray(data.files) ? data.files : [];
                const selected = new Set((Array.isArray(filenames) ? filenames : []).map((name) => String(name || '')));
                const selectedFiles = files.filter((entry) => selected.has(String(entry.filename || '')));
                const extras = [];
                const themeKinds = new Set(['theme-cover', 'theme-background', 'theme-background-video', 'share-image']);

                const hasThemeRefs = selectedFiles.some((entry) => (
                    Array.isArray(entry.references) && entry.references.some((reference) => themeKinds.has(String(reference.kind || '')))
                ));
                if (hasThemeRefs) {
                    extras.push('Theme or share-image settings still point at this file and will not be cleared automatically.');
                }

                const regenerableOrphans = selectedFiles.filter((entry) => {
                    const info = entry.reference_info || entry.cover_info || {};
                    return info.regenerable === true && Number((entry.reference_summary || {}).total || 0) === 0;
                });
                if (regenerableOrphans.length) {
                    extras.push('Build-generated cover files can be recreated on the next image refresh if they are still needed.');
                }

                return extras;
            }

            window.openDeleteModal = function(type, filename) {
                deleteTarget = type;
                deleteFiles  = Array.isArray(filename) ? filename.filter(Boolean) : [filename].filter(Boolean);
                deleteReferencePreview = null;
                if (deleteTitleEl) {
                    deleteTitleEl.textContent = deleteFiles.length > 1 ? 'Delete selected files?' : 'Delete file?';
                }
                if (deleteNameEl) {
                    deleteNameEl.textContent = deleteFiles.length > 1
                        ? `${deleteFiles.length} files selected`
                        : (deleteFiles[0] || '');
                }
                if (deleteListEl) {
                    if (deleteFiles.length > 1) {
                        deleteListEl.style.display = 'block';
                        deleteListEl.innerHTML = deleteFiles.map((name, index) => `<div class="modal-file-row">${index + 1}. ${escapeHtml(name)}</div>`).join('');
                    } else {
                        deleteListEl.style.display = 'none';
                        deleteListEl.innerHTML = '';
                    }
                }
                if (deleteHintEl) {
                    deleteHintEl.textContent = mediaReferenceFilterTypes.has(deleteTarget)
                        ? (deleteFiles.length > 1
                            ? 'Checking whether these files are still referenced…'
                            : 'Checking whether this file is still referenced…')
                        : (deleteFiles.length > 1
                            ? 'Checking whether these files are still used in the playlist or gallery…'
                            : 'Checking whether this file is still used in the playlist or gallery…');
                }
                if (deleteStatusEl) deleteStatusEl.textContent = '';
                if (deleteConfirmBtn) deleteConfirmBtn.disabled = true;
                if (deleteModal) deleteModal.style.display = 'flex';

                (async () => {
                    try {
                        const payload = deleteFiles.length > 1
                            ? { target: deleteTarget, filenames: deleteFiles, mode: 'preview' }
                            : { target: deleteTarget, filename: deleteFiles[0], mode: 'preview' };
                        const resp = await fetch('/biblioteca/delete-media.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const data = await resp.json();
                        if (!resp.ok || !data.ok) {
                            throw new Error(data.error || ('Request failed: ' + resp.status));
                        }

                        deleteReferencePreview = data;
                        const summary = data.reference_summary || {};
                        const total = Number(summary.total || 0);
                        if (deleteHintEl) {
                            const extras = buildMediaReferenceDeleteExtraHint(data, deleteFiles, deleteTarget);
                            if (!total) {
                                const base = deleteFiles.length > 1
                                    ? 'These deletions are immediate and cannot be undone. No playlist, gallery, or theme references will be changed.'
                                    : 'This cannot be undone. No playlist, gallery, or theme references will be changed.';
                                deleteHintEl.innerHTML = extras.length
                                    ? `${base}<br>${extras.map((line) => escapeHtml(line)).join('<br>')}`
                                    : base;
                            } else {
                                const parts = formatDeleteReferenceParts(summary);
                                const labels = Array.isArray(data.references) ? data.references.slice(0, 6).map((reference) => `${escapeHtml(reference.filename || '')}: ${escapeHtml(reference.label || '')}`) : [];
                                const lines = [
                                    `Deleting ${deleteFiles.length > 1 ? 'these files' : 'this file'} will also remove ${parts.join(', ')} from the saved site data.`,
                                    labels.join('<br>'),
                                ];
                                if ((data.references || []).length > 6) {
                                    lines.push('…');
                                }
                                if (extras.length) {
                                    lines.push(...extras.map((line) => escapeHtml(line)));
                                }
                                deleteHintEl.innerHTML = lines.filter(Boolean).join('<br>');
                            }
                        }
                    } catch (error) {
                        if (deleteHintEl) {
                            deleteHintEl.textContent = 'Could not inspect playlist/gallery references first. Deleting will still try to remove related references automatically.';
                        }
                    } finally {
                        if (deleteConfirmBtn) deleteConfirmBtn.disabled = false;
                    }
                })();
            };

            window.closeDeleteModal = function() {
                if (deleteModal) deleteModal.style.display = 'none';
                deleteTarget = null;
                deleteFiles  = [];
                deleteReferencePreview = null;
            };

            if (deleteConfirmBtn) {
                deleteConfirmBtn.addEventListener('click', async () => {
                    if (!deleteTarget || !deleteFiles.length) return;
                    deleteConfirmBtn.disabled = true;
                    deleteStatusEl.textContent = 'Deleting…';
                    try {
                        const resp = await fetch('/biblioteca/delete-media.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(deleteFiles.length > 1
                                ? { target: deleteTarget, filenames: deleteFiles, detach_references: true }
                                : { target: deleteTarget, filename: deleteFiles[0], detach_references: true }),
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            clearMediaSelection(deleteTarget);
                            closeDeleteModal();
                            await loadMediaList(activeMediaPanel);
                            const toastType = data.failed_count ? 'warning' : 'success';
                            showAdminToast(data.message || 'File removed.', toastType);
                        } else {
                            deleteStatusEl.innerHTML = `<span class="text-error">❌ ${data.error || 'Failed'}</span>`;
                            deleteConfirmBtn.disabled = false;
                        }
                    } catch(e) {
                        deleteStatusEl.innerHTML = `<span class="text-error">❌ Network error</span>`;
                        deleteConfirmBtn.disabled = false;
                    }
                });
            }

            if (audioMasterSaveBtn) {
                audioMasterSaveBtn.addEventListener('click', async () => {
                    if (!activeAudioMasterFile) return;

                    const fields = {};
                    Object.entries(audioMasterFields).forEach(([key, input]) => {
                        fields[key] = input ? String(input.value || '').trim() : '';
                    });
                    fields.title = combineAudioTitleParts(fields.title, audioMasterVersionField ? audioMasterVersionField.value : '');

                    const validationError = validateAudioMasterFields(fields);
                    if (validationError) {
                        setAudioMasterStatus(validationError, 'error');
                        return;
                    }

                    audioMasterSaveBtn.disabled = true;
                    setAudioMasterStatus('Saving…');

                    try {
                        const data = await persistAudioMasterMetadata(activeAudioMasterFile, fields, {
                            cover_path: audioMasterCoverPath ? String(audioMasterCoverPath.value || '').trim() : '',
                            cover_mode: audioMasterCoverMode,
                        });
                        await handleAudioMetadataSaveResult(activeAudioMasterFile, data);
                    } catch (error) {
                        setAudioMasterStatus(error.message || 'Could not save metadata', 'error');
                    } finally {
                        audioMasterSaveBtn.disabled = false;
                    }
                });
            }

            if (modalDrop) {
                modalDrop.addEventListener('click', () => modalInput.click());
                modalInput.addEventListener('change', () => updateModalList(modalInput.files));
                modalDrop.addEventListener('dragover', e => {
                    e.preventDefault();
                    modalDrop.classList.add('drag-over');
                });
                modalDrop.addEventListener('dragleave', () => {
                    modalDrop.classList.remove('drag-over');
                });
                modalDrop.addEventListener('drop', e => {
                    e.preventDefault();
                    modalDrop.classList.remove('drag-over');
                    updateModalList(e.dataTransfer.files);
                });
            }

            const ADMIN_CHUNK_SIZE = 2 * 1024 * 1024; // 2 MB per chunk

            async function uploadFileChunked(file, target, onProgress) {
                const totalChunks = Math.ceil(file.size / ADMIN_CHUNK_SIZE);
                let lastResponse = null;
                for (let i = 0; i < totalChunks; i++) {
                    const start = i * ADMIN_CHUNK_SIZE;
                    const chunk = file.slice(start, start + ADMIN_CHUNK_SIZE);
                    const fd = new FormData();
                    fd.append('chunk', chunk, file.name);
                    fd.append('filename', file.name);
                    fd.append('chunk_index', i);
                    fd.append('total_chunks', totalChunks);
                    fd.append('target', target);
                    const resp = await fetch('/biblioteca/upload-media.php', { method: 'POST', body: fd });
                    const data = await resp.json();
                    if (!data.ok) throw new Error(data.error || 'Chunk upload failed');
                    lastResponse = data;
                    if (onProgress) onProgress((i + 1) / totalChunks);
                }
                return lastResponse;
            }

            if (modalBtn) {
                modalBtn.addEventListener('click', async () => {
                    if (!modalFiles.length || !modalTarget) return;
                    modalBtn.disabled = true;
                    let done = 0, failed = 0;
                    let latestBuildState = null;
                    let masterPreparedCount = 0;
                    let autoImageDeliveryRan = false;
                    const masterWarnings = [];
                    for (let fi = 0; fi < modalFiles.length; fi++) {
                        const file = modalFiles[fi];
                        modalStatus.textContent = `⏳ Uploading ${file.name} (${fi + 1}/${modalFiles.length})…`;
                        try {
                            const uploadData = await uploadFileChunked(file, modalTarget, (p) => {
                                const pct = Math.round((fi + p) / modalFiles.length * 100);
                                modalStatus.textContent = `⏳ ${file.name} — ${pct}%`;
                            });
                            if (uploadData && uploadData.build_required_state) {
                                latestBuildState = uploadData.build_required_state;
                            }
                            if (uploadData && uploadData.master_prepared) {
                                masterPreparedCount += 1;
                            }
                            if (uploadData && uploadData.master_warning) {
                                masterWarnings.push(`${file.name}: ${uploadData.master_warning}`);
                            }
                            if (Array.isArray(uploadData?.auto_tasks) && uploadData.auto_tasks.includes('image-delivery')) {
                                autoImageDeliveryRan = true;
                            }
                            done++;
                        } catch(e) {
                            failed++;
                            modalStatus.innerHTML += `<br><span style="color:#f55">❌ ${file.name}: ${e.message}</span>`;
                        }
                    }
                    if (failed === 0) {
                        modalStatus.innerHTML = `<span style="color:#4ade80">✅ ${done} file${done !== 1 ? 's' : ''} uploaded</span>`;
                        modalFiles = [];
                        modalList.innerHTML = '';
                        await loadMediaList(modalTarget);
                        if (latestBuildState) {
                            setBuildRequiredNudge(true, latestBuildState.reasons || [], latestBuildState.action || 'none', latestBuildState.tasks || []);
                        }
                        if (done > 0) {
                            await refreshBuildRequiredState();
                        }

                        if (latestBuildState && latestBuildState.required) {
                            const next = formatBuildNextStep(latestBuildState);
                            const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                            const imageNote = autoImageDeliveryRan
                                ? ' Image files refreshed automatically.'
                                : '';
                            showAdminToast(`Upload complete.${masterNote}${imageNote} ${next}`, 'success');
                        } else {
                            const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                            const imageNote = autoImageDeliveryRan
                                ? ' Image files refreshed automatically.'
                                : '';
                            showAdminToast(`Upload complete.${masterNote}${imageNote} No build step needed.`, 'success');
                        }
                        if (masterWarnings.length) {
                            modalStatus.innerHTML += `<br><span style="color:#f0b429">⚠️ ${escapeHtml(masterWarnings.join(' | '))}</span>`;
                        }
                    } else {
                        modalStatus.innerHTML += `<br><span style="color:#f55">❌ ${failed} failed, ✅ ${done} ok</span>`;
                        if (done > 0) {
                            await loadMediaList(modalTarget);
                        }
                        if (latestBuildState) {
                            setBuildRequiredNudge(true, latestBuildState.reasons || [], latestBuildState.action || 'none', latestBuildState.tasks || []);
                        }
                        if (done > 0) {
                            await refreshBuildRequiredState();
                        }

                        if (done > 0) {
                            const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                            showAdminToast(`Uploaded ${done} file(s), ${failed} failed.${masterNote}`, 'warning');
                        }
                        if (masterWarnings.length) {
                            modalStatus.innerHTML += `<br><span style="color:#f0b429">⚠️ ${escapeHtml(masterWarnings.join(' | '))}</span>`;
                        }
                    }
                    refreshBuildHint();
                    modalBtn.disabled = !modalFiles.length;
                });
            }

            function parseAdminConfigSource(sourceField) {
                try {
                    const parsed = JSON.parse((sourceField && sourceField.value) || '{}');
                    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
                } catch (error) {
                    throw new Error('Stored config could not be read');
                }
            }

            function assignConfigFields(target, fields) {
                const next = target && typeof target === 'object' && !Array.isArray(target)
                    ? { ...target }
                    : {};

                Object.entries(fields).forEach(([key, value]) => {
                    next[key] = value;
                });

                return next;
            }

            // ── Basics: site branch form ────────────────────────────────────
            const cfgBasicsFullSource = document.getElementById('cfgBasicsFullSource');
            const cfgBasicsSaveBtn = document.getElementById('cfgBasicsSaveBtn');
            const cfgBasicsStatus = document.getElementById('cfgBasicsStatus');
            if (cfgBasicsSaveBtn) {
                cfgBasicsSaveBtn.addEventListener('click', async () => {
                    cfgBasicsStatus.textContent = 'Saving…';
                    cfgBasicsStatus.style.color = '#aaa';

                    let fullConfig;
                    try {
                        fullConfig = parseAdminConfigSource(cfgBasicsFullSource);
                    } catch (e) {
                        cfgBasicsStatus.textContent = '❌ ' + e.message;
                        cfgBasicsStatus.style.color = '#f55';
                        return;
                    }

                    fullConfig.site = assignConfigFields(fullConfig.site, {
                        name: (document.getElementById('cfg_site_name')?.value || '').trim(),
                        short_name: (document.getElementById('cfg_site_short_name')?.value || '').trim(),
                        description: (document.getElementById('cfg_site_description')?.value || '').trim(),
                        url: (document.getElementById('cfg_site_url')?.value || '').trim(),
                        language: (document.getElementById('cfg_site_language')?.value || '').trim(),
                        author: (document.getElementById('cfg_site_author')?.value || '').trim(),
                    });

                    try {
                        const payload = JSON.stringify(fullConfig, null, 4);
                        const resp = await fetch('/biblioteca/save-config-raw.php?branch=site', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: payload,
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            cfgBasicsFullSource.value = payload;
                            cfgBasicsStatus.textContent = Array.isArray(data.auto_tasks) && data.auto_tasks.includes('manifest') ? '✅ Saved and manifest updated' : '✅ Saved';
                            cfgBasicsStatus.style.color = 'var(--success, #4ade80)';
                            const reasons = (data.build_required_state && data.build_required_state.reasons) || ['site_config_changed'];
                            const action = (data.build_required_state && data.build_required_state.action) || 'full';
                            setBuildRequiredNudge(data.build_required === true, reasons, action, (data.build_required_state && data.build_required_state.tasks) || []);
                            await refreshBuildRequiredState();
                            refreshBuildHint();
                        } else {
                            cfgBasicsStatus.textContent = '❌ ' + (data.error || 'Unknown error');
                            cfgBasicsStatus.style.color = '#f55';
                        }
                    } catch (e) {
                        cfgBasicsStatus.textContent = '❌ Network error: ' + e.message;
                        cfgBasicsStatus.style.color = '#f55';
                    }
                });
            }

            // ── Theme: media branch form ────────────────────────────────────
            const cfgThemeFullSource = document.getElementById('cfgThemeFullSource');
            const cfgThemeSaveBtn = document.getElementById('cfgThemeSaveBtn');
            const cfgThemeStatus = document.getElementById('cfgThemeStatus');
            if (cfgThemeSaveBtn) {
                cfgThemeSaveBtn.addEventListener('click', async () => {
                    cfgThemeStatus.textContent = 'Saving…';
                    cfgThemeStatus.style.color = '#aaa';

                    let fullConfig;
                    try {
                        fullConfig = parseAdminConfigSource(cfgThemeFullSource);
                    } catch (e) {
                        cfgThemeStatus.textContent = '❌ ' + e.message;
                        cfgThemeStatus.style.color = '#f55';
                        return;
                    }

                    fullConfig.media = assignConfigFields(fullConfig.media, {
                        logo: (document.getElementById('cfg_theme_logo')?.value || '').trim(),
                        cover: (document.getElementById('cfg_theme_cover')?.value || '').trim(),
                        background_image: (document.getElementById('cfg_theme_background_image')?.value || '').trim(),
                        background_video: (document.getElementById('cfg_theme_background_video')?.value || '').trim(),
                        welcome_audio: (document.getElementById('cfg_theme_welcome_audio')?.value || '').trim(),
                        loggedin_audio: (document.getElementById('cfg_theme_loggedin_audio')?.value || '').trim(),
                    });

                    try {
                        const payload = JSON.stringify(fullConfig, null, 4);
                        const resp = await fetch('/biblioteca/save-config-raw.php?branch=media', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: payload,
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            cfgThemeFullSource.value = payload;
                            cfgThemeStatus.textContent = Array.isArray(data.auto_tasks) && data.auto_tasks.includes('playlist-scan') && data.auto_tasks.includes('image-delivery')
                                ? '✅ Saved, playlist refreshed, and image files updated'
                                : Array.isArray(data.auto_tasks) && data.auto_tasks.includes('playlist-scan')
                                    ? '✅ Saved and playlist refreshed'
                                    : '✅ Saved';
                            cfgThemeStatus.style.color = 'var(--success, #4ade80)';
                            const reasons = (data.build_required_state && data.build_required_state.reasons) || ['theme_config_changed'];
                            const action = (data.build_required_state && data.build_required_state.action) || 'full';
                            setBuildRequiredNudge(data.build_required === true, reasons, action, (data.build_required_state && data.build_required_state.tasks) || []);
                            await refreshBuildRequiredState();
                            refreshBuildHint();
                        } else {
                            cfgThemeStatus.textContent = '❌ ' + (data.error || 'Unknown error');
                            cfgThemeStatus.style.color = '#f55';
                        }
                    } catch (e) {
                        cfgThemeStatus.textContent = '❌ Network error: ' + e.message;
                        cfgThemeStatus.style.color = '#f55';
                    }
                });
            }

            // ── Pages editor ──────────────────────────────────────────────────
            const pageEditor  = document.getElementById('pageEditor');
            const pageSaveBtn = document.getElementById('pageSaveBtn');
            const pageStatus  = document.getElementById('pageStatus');
            let pageRichTextEditor = null;

            function flattenPageImageList(items) {
                const flat = [];
                (items || []).forEach((item) => {
                    if (Array.isArray(item.menu)) {
                        item.menu.forEach((child) => {
                            flat.push({
                                title: `${item.title}: ${child.title}`,
                                alt: child.title,
                                value: child.value,
                            });
                        });
                        return;
                    }

                    if (item && item.value) {
                        flat.push({ title: item.title, alt: item.title, value: item.value });
                    }
                });
                return flat;
            }

            async function fetchPageImageList() {
                const resp = await fetch('/biblioteca/list-page-images.php');
                const data = await resp.json();

                if (!resp.ok) {
                    throw new Error(data.error || 'Could not load content images');
                }

                return Array.isArray(data.images) ? data.images : [];
            }

            function openPageImageDialog(editor, callback, items) {
                if (!editor || !items.length) {
                    throw new Error('No optimized content images available yet');
                }

                editor.windowManager.open({
                    title: 'Choose content image',
                    body: {
                        type: 'panel',
                        items: [
                            {
                                type: 'selectbox',
                                name: 'src',
                                label: 'Optimized image',
                                items: items.map((item) => ({ text: item.title, value: item.value })),
                            },
                        ],
                    },
                    initialData: { src: items[0].value },
                    buttons: [
                        { type: 'cancel', text: 'Cancel' },
                        { type: 'submit', text: 'Use image', primary: true },
                    ],
                    onSubmit(api) {
                        const data = api.getData();
                        const selected = items.find((item) => item.value === data.src) || items[0];
                        callback(selected.value, { alt: selected.alt || selected.title });
                        api.close();
                    },
                });
            }

            async function initPageRichTextEditor() {
                if (!pageEditor || typeof tinymce === 'undefined') return null;
                if (pageRichTextEditor) return pageRichTextEditor;

                const imageList = await fetchPageImageList();
                const flatImageList = flattenPageImageList(imageList);

                await tinymce.init({
                    target: pageEditor,
                    license_key: 'gpl',
                    base_url: '/vendor/tinymce/js/tinymce',
                    suffix: '.min',
                    menubar: false,
                    branding: false,
                    promotion: false,
                    min_height: 520,
                    plugins: 'autolink code image link lists autoresize',
                    toolbar: 'undo redo | blocks | bold italic | bullist numlist blockquote | link image | code',
                    block_unsupported_drop: true,
                    automatic_uploads: false,
                    image_uploadtab: false,
                    paste_data_images: false,
                    convert_urls: false,
                    image_list: imageList,
                    file_picker_types: 'image',
                    images_file_types: 'jpg,jpeg,png,webp',
                    valid_elements: 'p,br,strong/b,em/i,h2,h3,h4,blockquote,ul,ol,li,a[href|target|rel],img[src|alt|title],hr',
                    link_target_list: [
                        { title: 'Same tab', value: '' },
                        { title: 'New tab', value: '_blank' },
                    ],
                    file_picker_callback(callback, value, meta) {
                        if (meta.filetype !== 'image') return;
                        openPageImageDialog(pageRichTextEditor, callback, flatImageList);
                    },
                    setup(editor) {
                        editor.on('init', () => {
                            pageRichTextEditor = editor;
                        });
                    },
                });

                pageRichTextEditor = tinymce.get('pageEditor');
                return pageRichTextEditor;
            }

            if (pageEditor && typeof tinymce !== 'undefined') {
                initPageRichTextEditor().catch((error) => {
                    pageStatus.textContent = `Source editor fallback: ${error.message}`;
                    pageStatus.style.color = '#f55';
                });
            }

            if (pageSaveBtn) {
                pageSaveBtn.addEventListener('click', async () => {
                    const pageKey = pageEditor?.dataset.pageKey || 'bio';
                    const pageLabel = pageEditor?.dataset.pageLabel || 'Page';

                    pageStatus.textContent = 'Saving…';
                    pageStatus.style.color = '#aaa';
                    pageSaveBtn.disabled = true;
                    try {
                        const activePageEditor = typeof tinymce !== 'undefined' ? tinymce.get('pageEditor') : null;
                        const pageContent = activePageEditor
                            ? activePageEditor.getContent({ format: 'html' })
                            : pageEditor.value;

                        const resp = await fetch(`/biblioteca/save-page.php?page=${encodeURIComponent(pageKey)}`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                            body: pageContent,
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            if (data.sanitized) {
                                pageStatus.textContent = `${pageLabel} saved with safety cleanup`;
                                pageStatus.style.color = '#fbbf24';
                            } else {
                                pageStatus.textContent = `${pageLabel} saved`;
                                pageStatus.style.color = 'var(--success, #4ade80)';
                            }

                            if (activePageEditor && typeof data.html === 'string') {
                                activePageEditor.setContent(data.html, { format: 'html' });
                            } else if (typeof data.html === 'string') {
                                pageEditor.value = data.html;
                            }
                        } else {
                            pageStatus.textContent = '❌ ' + (data.error || 'Unknown error');
                            pageStatus.style.color = '#f55';
                        }
                    } catch (e) {
                        pageStatus.textContent = '❌ Network error: ' + e.message;
                        pageStatus.style.color = '#f55';
                    } finally {
                        pageSaveBtn.disabled = false;
                    }
                });
            }

            (function () {
                const editorEl    = document.getElementById('galleryEditor');
                const availableEl = document.getElementById('galleryAvailableList');
                const activeEl    = document.getElementById('galleryActiveList');
                const countBadge  = document.getElementById('galleryActiveCount');
                const saveBtn     = document.getElementById('gallerySaveBtn');
                const statusEl    = document.getElementById('galleryStatus');
                if (!editorEl || !saveBtn) return;

                // ── helpers ──────────────────────────────────────────────────
                function escHtml(str) {
                    return String(str ?? '')
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                }

                function prettifyName(filename) {
                    return filename
                        .replace(/\.[^.]+$/, '')
                        .replace(/[_-]+/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                // ── state ────────────────────────────────────────────────────
                let activeItems = [];
                try { activeItems = JSON.parse(editorEl.dataset.initial || '[]'); } catch (e) { activeItems = []; }

                let allFiles = []; // { src, name, type }

                function videoPosterPathFromSrc(src) {
                    const normalized = String(src || '').replace(/\\/g, '/');
                    const pathOnly = normalized.split('?')[0];
                    const fileName = pathOnly.substring(pathOnly.lastIndexOf('/') + 1);
                    if (!/\.(mp4|webm|mov)$/i.test(fileName)) return '';
                    return '/media/video/poster/' + fileName.replace(/\.[^.]+$/i, '.jpg');
                }

                function resolveVideoPoster(item) {
                    if (!item || item.type !== 'video') return '';
                    return item.poster || videoPosterPathFromSrc(item.src);
                }

                function activeSrcs() { return new Set(activeItems.map(i => i.src)); }

                // ── render: available panel ──────────────────────────────────
                function renderAvailable() {
                    const taken = activeSrcs();
                    const available = allFiles.filter(f => !taken.has(f.src));
                    if (available.length === 0) {
                        availableEl.innerHTML = '<p class="hint">All uploaded media is already in the gallery.</p>';
                        return;
                    }
                    availableEl.innerHTML = '';
                    available.forEach(file => {
                        const row = document.createElement('div');
                        row.className = 'gallery-available-row';
                        row.dataset.src = file.src;
                        row.dataset.poster = file.poster || '';
                        if (file.type === 'video') {
                            const poster = resolveVideoPoster(file);
                            row.innerHTML =
                                (poster
                                    ? `<img class="gallery-thumb" src="${escHtml(poster)}" alt="${escHtml(file.name)}" loading="lazy" onerror="this.style.opacity=0.2">`
                                    : `<span class="gallery-thumb gallery-thumb--video">▶</span>`) +
                                `<span class="gallery-available-name">${escHtml(file.name)}</span>` +
                                `<button class="btn btn-sm gallery-add-btn" title="Add to gallery">＋</button>`;
                        } else {
                            row.innerHTML =
                                `<img class="gallery-thumb" src="${escHtml(file.src)}" alt="${escHtml(file.name)}" loading="lazy" onerror="this.style.opacity=0.2">` +
                                `<span class="gallery-available-name">${escHtml(file.name)}</span>` +
                                `<button class="btn btn-sm gallery-add-btn" title="Add to gallery">＋</button>`;
                        }
                        row.querySelector('.gallery-add-btn').addEventListener('click', () => addItem(file));
                        availableEl.appendChild(row);
                    });
                }

                // ── render: active list ──────────────────────────────────────
                function renderActive() {
                    activeEl.innerHTML = '';
                    activeItems.forEach((item, idx) => {
                        const li = document.createElement('li');
                        li.className = 'gallery-active-row';
                        li.draggable = true;
                        li.dataset.src  = item.src;
                        li.dataset.type = item.type || 'image';
                        li.dataset.poster = resolveVideoPoster(item);
                        const isVideo = item.type === 'video';
                        const poster = resolveVideoPoster(item);
                        li.innerHTML =
                            `<span class="playlist-drag-handle" title="Drag to reorder">⠿</span>` +
                            (isVideo
                                ? (poster
                                    ? `<img class="gallery-thumb gallery-thumb--sm" src="${escHtml(poster)}" alt="${escHtml(item.alt || item.name || '')}" loading="lazy" onerror="this.style.opacity=0.2">`
                                    : `<span class="gallery-thumb gallery-thumb--video gallery-thumb--sm">▶</span>`)
                                : `<img class="gallery-thumb gallery-thumb--sm" src="${escHtml(item.src)}" alt="${escHtml(item.alt || '')}" loading="lazy" onerror="this.style.opacity=0.2">`) +
                            `<div class="gallery-active-fields">` +
                            `<input class="gallery-field-name" type="text" value="${escHtml(item.name || '')}" placeholder="Name" aria-label="Name">` +
                            `<input class="gallery-field-alt"  type="text" value="${escHtml(item.alt  || '')}" placeholder="Alt text" aria-label="Alt text">` +
                            `</div>` +
                            `<button class="gallery-remove-btn" title="Remove from gallery">✕</button>`;
                        activeEl.appendChild(li);
                    });
                    if (countBadge) countBadge.textContent = activeItems.length ? `(${activeItems.length})` : '';
                }

                // ── sync DOM order → activeItems (called before mutations) ───
                function syncFromDOM() {
                    const rows = activeEl.querySelectorAll('.gallery-active-row');
                    activeItems = Array.from(rows).map(row => ({
                        src:  row.dataset.src,
                        type: row.dataset.type || 'image',
                        poster: row.dataset.poster || '',
                        name: row.querySelector('.gallery-field-name').value.trim(),
                        alt:  row.querySelector('.gallery-field-alt').value.trim(),
                    })).map(item => {
                        if (item.type !== 'video' || !item.poster) {
                            delete item.poster;
                        }
                        return item;
                    });
                }

                // ── add / remove ─────────────────────────────────────────────
                function addItem(file) {
                    syncFromDOM();
                    const item = { src: file.src, name: file.name, alt: file.name, type: file.type };
                    const poster = resolveVideoPoster(file);
                    if (poster) item.poster = poster;
                    activeItems.push(item);
                    renderActive();
                    renderAvailable();
                }

                // ── delegated: remove button ─────────────────────────────────
                activeEl.addEventListener('click', (e) => {
                    const btn = e.target.closest('.gallery-remove-btn');
                    if (!btn) return;
                    syncFromDOM();
                    const row = btn.closest('.gallery-active-row');
                    activeItems = activeItems.filter(i => i.src !== row.dataset.src);
                    renderActive();
                    renderAvailable();
                });

                // ── drag and drop (delegated on activeEl) ────────────────────
                let dragSrc = null;
                let galleryDragPlaceholder = null;

                function getGalleryRows() {
                    return Array.from(activeEl.querySelectorAll('.gallery-active-row'));
                }

                function ensureGalleryPlaceholder() {
                    if (!galleryDragPlaceholder) {
                        galleryDragPlaceholder = document.createElement('li');
                        galleryDragPlaceholder.className = 'gallery-editor-placeholder';
                    }
                    return galleryDragPlaceholder;
                }

                function updateGalleryPlaceholderHeight() {
                    if (!dragSrc) return;
                    const placeholder = ensureGalleryPlaceholder();
                    const height = Math.max(52, Math.round(dragSrc.getBoundingClientRect().height));
                    placeholder.style.height = `${height}px`;
                }

                function moveGalleryPlaceholder(clientY) {
                    if (!dragSrc) return;
                    const placeholder = ensureGalleryPlaceholder();
                    updateGalleryPlaceholderHeight();

                    const candidateRows = getGalleryRows().filter((row) => row !== dragSrc);
                    const referenceRow = candidateRows.find((row) => {
                        const rect = row.getBoundingClientRect();
                        return clientY < rect.top + rect.height / 2;
                    });

                    if (referenceRow) {
                        activeEl.insertBefore(placeholder, referenceRow);
                    } else {
                        activeEl.appendChild(placeholder);
                    }
                }

                function finalizeGalleryDrag() {
                    const placeholder = ensureGalleryPlaceholder();
                    if (placeholder.parentNode === activeEl && dragSrc) {
                        activeEl.insertBefore(dragSrc, placeholder);
                        placeholder.remove();
                    }
                }

                activeEl.addEventListener('dragstart', (e) => {
                    dragSrc = e.target.closest('.gallery-active-row');
                    if (!dragSrc) return;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', dragSrc.dataset.src || '');
                    window.requestAnimationFrame(() => {
                        if (!dragSrc) return;
                        updateGalleryPlaceholderHeight();
                        activeEl.insertBefore(ensureGalleryPlaceholder(), dragSrc);
                        dragSrc.classList.add('dragging');
                    });
                });
                activeEl.addEventListener('dragend', () => {
                    finalizeGalleryDrag();
                    activeEl.querySelectorAll('.gallery-active-row').forEach((row) => row.classList.remove('dragging', 'drag-over'));
                    dragSrc = null;
                    syncFromDOM();
                });
                activeEl.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (!dragSrc) return;
                    moveGalleryPlaceholder(e.clientY);
                });
                activeEl.addEventListener('drop', (e) => {
                    e.preventDefault();
                    if (!dragSrc) return;
                    moveGalleryPlaceholder(e.clientY);
                    finalizeGalleryDrag();
                    syncFromDOM();
                });

                // ── save ─────────────────────────────────────────────────────
                saveBtn.addEventListener('click', async () => {
                    syncFromDOM();
                    statusEl.textContent = 'Saving…';
                    statusEl.style.color = '#aaa';
                    try {
                        const resp = await fetch('/biblioteca/save-gallery.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(activeItems),
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            statusEl.textContent = `✅ Saved ${data.count} item${data.count !== 1 ? 's' : ''}`;
                            statusEl.style.color = 'var(--success, #4ade80)';
                        } else {
                            statusEl.textContent = '❌ ' + (data.error || 'Unknown error');
                            statusEl.style.color = '#f55';
                        }
                    } catch (e) {
                        statusEl.textContent = '❌ Network error: ' + e.message;
                        statusEl.style.color = '#f55';
                    }
                });

                // ── fetch available media and initialise ─────────────────────
                (async function init() {
                    try {
                        const [photoFiles, videoFiles] = await Promise.all([
                            fetchMediaFiles('photos'),
                            fetchMediaFiles('video'),
                        ]);
                        allFiles = [
                            ...(photoFiles || []).map(f => ({
                                src: '/media/photo/original/' + f.name,
                                name: prettifyName(f.name),
                                type: 'image',
                            })),
                            ...(videoFiles || []).map(f => ({
                                src: '/media/video/original/' + f.name,
                                name: prettifyName(f.name),
                                type: 'video',
                                poster: '/media/video/poster/' + f.name.replace(/\.[^.]+$/, '.jpg'),
                            })),
                        ];
                    } catch (e) {
                        availableEl.innerHTML = '<p class="hint" style="color:#f55">Failed to load media files.</p>';
                    }
                    renderActive();
                    renderAvailable();
                })();
            })();

            // ── Playlist editor ───────────────────────────────────────────────
            (function () {
                const list       = document.getElementById('playlistEditor');
                const saveBtn    = document.getElementById('playlistSaveBtn');
                const statusEl   = document.getElementById('playlistStatus');
                const hintEl     = document.getElementById('playlistPreviewHint');
                if (!list || !saveBtn) return;

                let dragSrc = null;
                let draggedRows = [];
                let selectedFiles = new Set();
                let selectionAnchor = '';
                let dragPlaceholder = null;
                let suppressNextClick = false;

                function formatPlaylistDuration(seconds) {
                    const duration = Math.max(0, Number(seconds) || 0);
                    if (!duration) return '';
                    return `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, '0')}`;
                }

                function renderPlaylistRows(tracks) {
                    const rows = Array.isArray(tracks) ? tracks : [];
                    const allowedFiles = new Set(rows.map((track) => String(track && track.file || '')).filter(Boolean));
                    selectedFiles.forEach((filename) => {
                        if (!allowedFiles.has(filename)) {
                            selectedFiles.delete(filename);
                        }
                    });
                    if (selectionAnchor && !allowedFiles.has(selectionAnchor)) {
                        selectionAnchor = '';
                    }
                    if (!rows.length) {
                        list.innerHTML = '';
                        if (hintEl) {
                            hintEl.textContent = 'No current source tracks found. Upload audio or enable Demo to inspect bundled tracks.';
                        }
                        return;
                    }

                    list.innerHTML = rows.map((track, index) => {
                        const title = escapeHtml(track.title || track.file || 'Untitled');
                        const artist = escapeHtml(track.artist || '');
                        const album = escapeHtml(track.album || '');
                        const meta = album ? `${artist} — ${album}` : artist;
                        const duration = formatPlaylistDuration(track.duration);
                        const demoClass = track.origin === 'bundled-placeholder' ? ' playlist-editor-row-demo' : '';
                        const selectedClass = selectedFiles.has(String(track.file || '')) ? ' playlist-editor-row-selected' : '';
                        return `<li class="playlist-editor-row${demoClass}${selectedClass}" draggable="true" data-file="${escapeHtml(track.file || '')}" aria-selected="${selectedFiles.has(String(track.file || '')) ? 'true' : 'false'}">
                            <span class="playlist-drag-handle" title="Drag to reorder">⠿</span>
                            <span class="playlist-track-num">${index + 1}</span>
                            <span class="playlist-track-info">
                                <strong>${title}</strong>
                                <span class="playlist-track-meta">${meta}</span>
                            </span>
                            <span class="playlist-track-duration">${duration}</span>
                        </li>`;
                    }).join('');

                    if (!appliedPlaylistFocusFromQuery && pendingPlaylistFocusFromQuery) {
                        const targetRow = Array.from(list.querySelectorAll('.playlist-editor-row')).find((row) => String(row.dataset.file || '') === pendingPlaylistFocusFromQuery);
                        if (targetRow) {
                            appliedPlaylistFocusFromQuery = true;
                            list.querySelectorAll('.playlist-editor-row').forEach((row) => row.classList.remove('playlist-editor-row-focus'));
                            targetRow.classList.add('playlist-editor-row-focus');
                            targetRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
                            if (statusEl) {
                                statusEl.textContent = `Focused track: ${pendingPlaylistFocusFromQuery}`;
                                statusEl.style.color = 'var(--muted)';
                            }
                        }
                    }

                    if (hintEl) {
                        hintEl.textContent = showBundledDemoAssets
                            ? 'Showing current source tracks with bundled demo audio revealed.'
                            : 'Showing current source tracks with bundled demo audio suppressed when real uploads exist.';
                    }
                }

                function getPlaylistRows() {
                    return Array.from(list.querySelectorAll('.playlist-editor-row'));
                }

                function syncPlaylistSelectionUi() {
                    getPlaylistRows().forEach((row) => {
                        const selected = selectedFiles.has(String(row.dataset.file || ''));
                        row.classList.toggle('playlist-editor-row-selected', selected);
                        row.setAttribute('aria-selected', selected ? 'true' : 'false');
                    });
                }

                function selectPlaylistRange(targetFile, preserveExisting) {
                    const rows = getPlaylistRows();
                    if (!rows.length) return;
                    const anchorFile = selectionAnchor && rows.some((row) => String(row.dataset.file || '') === selectionAnchor)
                        ? selectionAnchor
                        : targetFile;
                    const anchorIndex = rows.findIndex((row) => String(row.dataset.file || '') === anchorFile);
                    const targetIndex = rows.findIndex((row) => String(row.dataset.file || '') === targetFile);
                    if (anchorIndex < 0 || targetIndex < 0) {
                        return;
                    }

                    const nextSelected = preserveExisting ? new Set(selectedFiles) : new Set();
                    const start = Math.min(anchorIndex, targetIndex);
                    const end = Math.max(anchorIndex, targetIndex);
                    rows.slice(start, end + 1).forEach((row) => nextSelected.add(String(row.dataset.file || '')));
                    selectedFiles = nextSelected;
                }

                function handlePlaylistSelection(row, event) {
                    const file = String(row.dataset.file || '').trim();
                    if (!file) return;

                    if (event.shiftKey) {
                        selectPlaylistRange(file, event.ctrlKey || event.metaKey);
                    } else if (event.ctrlKey || event.metaKey) {
                        if (selectedFiles.has(file)) {
                            selectedFiles.delete(file);
                        } else {
                            selectedFiles.add(file);
                        }
                    } else {
                        selectedFiles = new Set([file]);
                    }

                    selectionAnchor = selectedFiles.size ? file : '';
                    syncPlaylistSelectionUi();
                }

                function ensurePlaylistPlaceholder() {
                    if (!dragPlaceholder) {
                        dragPlaceholder = document.createElement('li');
                        dragPlaceholder.className = 'playlist-editor-placeholder';
                    }
                    return dragPlaceholder;
                }

                function updatePlaylistPlaceholderHeight() {
                    if (!draggedRows.length) return;
                    const placeholder = ensurePlaylistPlaceholder();
                    const totalHeight = draggedRows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0) + Math.max(0, draggedRows.length - 1) * 6;
                    placeholder.style.height = `${Math.max(52, Math.round(totalHeight))}px`;
                }

                function movePlaylistPlaceholder(clientY) {
                    if (!draggedRows.length) return;
                    const placeholder = ensurePlaylistPlaceholder();
                    updatePlaylistPlaceholderHeight();

                    const candidateRows = getPlaylistRows().filter((row) => !draggedRows.includes(row));
                    const referenceRow = candidateRows.find((row) => {
                        const rect = row.getBoundingClientRect();
                        return clientY < rect.top + rect.height / 2;
                    });

                    if (referenceRow) {
                        list.insertBefore(placeholder, referenceRow);
                    } else {
                        list.appendChild(placeholder);
                    }
                }

                function finalizePlaylistDrag() {
                    const placeholder = ensurePlaylistPlaceholder();
                    if (placeholder.parentNode === list && draggedRows.length) {
                        draggedRows.forEach((row) => {
                            list.insertBefore(row, placeholder);
                        });
                        placeholder.remove();
                    }
                }

                async function loadPlaylistPreview() {
                    if (hintEl) {
                        hintEl.textContent = 'Loading current source tracks…';
                    }
                    saveBtn.disabled = true;
                    try {
                        const params = new URLSearchParams();
                        if (showBundledDemoAssets) {
                            params.set('include_bundled', '1');
                        }
                        const query = params.toString();
                        const resp = await fetch('/biblioteca/get-playlist-preview.php' + (query ? `?${query}` : ''));
                        const data = await resp.json();
                        if (!resp.ok || data.error) {
                            throw new Error(data.error || 'Could not load playlist preview');
                        }
                        renderPlaylistRows(data.tracks || []);
                        saveBtn.disabled = false;
                    } catch (e) {
                        list.innerHTML = '';
                        if (hintEl) {
                            hintEl.textContent = 'Could not load playlist preview: ' + e.message;
                        }
                    }
                }

                list.addEventListener('click', (event) => {
                    if (suppressNextClick) {
                        return;
                    }
                    const row = event.target.closest('.playlist-editor-row');
                    if (!row) return;
                    handlePlaylistSelection(row, event);
                });

                list.addEventListener('dragstart', (e) => {
                    dragSrc = e.target.closest('.playlist-editor-row');
                    if (!dragSrc) return;
                    const sourceFile = String(dragSrc.dataset.file || '').trim();
                    if (sourceFile && !selectedFiles.has(sourceFile)) {
                        selectedFiles = new Set([sourceFile]);
                        selectionAnchor = sourceFile;
                        syncPlaylistSelectionUi();
                    }
                    draggedRows = getPlaylistRows().filter((row) => selectedFiles.has(String(row.dataset.file || '')));
                    if (!draggedRows.length) {
                        draggedRows = [dragSrc];
                    }
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', dragSrc.dataset.file);
                    window.requestAnimationFrame(() => {
                        if (!dragSrc || !draggedRows.length) {
                            return;
                        }
                        updatePlaylistPlaceholderHeight();
                        list.insertBefore(ensurePlaylistPlaceholder(), draggedRows[0]);
                        draggedRows.forEach((row) => row.classList.add('dragging'));
                    });
                });

                list.addEventListener('dragend', () => {
                    finalizePlaylistDrag();
                    list.querySelectorAll('.playlist-editor-row').forEach((row) => {
                        row.classList.remove('dragging');
                    });
                    dragSrc = null;
                    draggedRows = [];
                    renumberRows();
                    syncPlaylistSelectionUi();
                    suppressNextClick = true;
                    window.requestAnimationFrame(() => {
                        suppressNextClick = false;
                    });
                });

                list.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (!dragSrc) return;
                    movePlaylistPlaceholder(e.clientY);
                });

                list.addEventListener('drop', (e) => {
                    e.preventDefault();
                    if (!dragSrc) return;
                    movePlaylistPlaceholder(e.clientY);
                    finalizePlaylistDrag();
                    renumberRows();
                    syncPlaylistSelectionUi();
                });

                function renumberRows() {
                    list.querySelectorAll('.playlist-editor-row').forEach((row, i) => {
                        const numEl = row.querySelector('.playlist-track-num');
                        if (numEl) numEl.textContent = i + 1;
                    });
                }

                saveBtn.addEventListener('click', async () => {
                    statusEl.textContent = 'Saving…';
                    statusEl.style.color = '#aaa';
                    const order = Array.from(list.querySelectorAll('.playlist-editor-row'))
                        .map(r => r.dataset.file)
                        .filter(Boolean);
                    try {
                        const resp = await fetch('/biblioteca/save-playlist-order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(order),
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            const warn = data.warning ? ` (${data.warning})` : '';
                            statusEl.textContent = `✅ Saved ${data.count} track${data.count !== 1 ? 's' : ''}${warn}`;
                            statusEl.style.color = 'var(--success, #4ade80)';
                        } else {
                            statusEl.textContent = '❌ ' + (data.error || 'Unknown error');
                            statusEl.style.color = '#f55';
                        }
                    } catch (e) {
                        statusEl.textContent = '❌ Network error: ' + e.message;
                        statusEl.style.color = '#f55';
                    }
                });

                loadPlaylistPreview();

                const previousSetShowBundledDemoAssets = setShowBundledDemoAssets;
                setShowBundledDemoAssets = function(nextValue) {
                    previousSetShowBundledDemoAssets(nextValue);
                    loadPlaylistPreview();
                };
            })();

            // ── Support: link/widget branch form ────────────────────────────
            (function () {
                const cfgSupportFullSource = document.getElementById('cfgSupportFullSource');
                const cfgSupportSaveBtn = document.getElementById('cfgSupportSaveBtn');
                const cfgSupportStatus = document.getElementById('cfgSupportStatus');
                if (!cfgSupportSaveBtn) return;

                cfgSupportSaveBtn.addEventListener('click', async () => {
                    cfgSupportStatus.textContent = 'Saving…';
                    cfgSupportStatus.style.color = '#aaa';

                    let fullConfig;
                    try {
                        fullConfig = parseAdminConfigSource(cfgSupportFullSource);
                    } catch (e) {
                        cfgSupportStatus.textContent = '❌ ' + e.message;
                        cfgSupportStatus.style.color = '#f55';
                        return;
                    }

                    fullConfig.support = assignConfigFields(fullConfig.support, {
                        enabled: !!document.getElementById('cfg_support_enabled')?.checked,
                        mode: (document.getElementById('cfg_support_mode')?.value || 'link').trim(),
                        label: (document.getElementById('cfg_support_label')?.value || '').trim(),
                        url: (document.getElementById('cfg_support_url')?.value || '').trim(),
                        kofi_page_id: (document.getElementById('cfg_support_kofi_page_id')?.value || '').trim(),
                        button_background_color: (document.getElementById('cfg_support_button_background_color')?.value || '').trim(),
                        button_text_color: (document.getElementById('cfg_support_button_text_color')?.value || '').trim(),
                    });

                    try {
                        const payload = JSON.stringify(fullConfig, null, 4);
                        const resp = await fetch('/biblioteca/save-config-raw.php?branch=support', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: payload,
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            cfgSupportFullSource.value = payload;
                            cfgSupportStatus.textContent = '✅ Saved';
                            cfgSupportStatus.style.color = 'var(--success, #4ade80)';
                        } else {
                            cfgSupportStatus.textContent = '❌ ' + (data.error || 'Unknown error');
                            cfgSupportStatus.style.color = '#f55';
                        }
                    } catch (e) {
                        cfgSupportStatus.textContent = '❌ Network error: ' + e.message;
                        cfgSupportStatus.style.color = '#f55';
                    }
                });
            })();

            // ── Social form ───────────────────────────────────────────────────
            (function () {
                const fields = {
                    site_name:   document.getElementById('soc_site_name'),
                    site_desc:   document.getElementById('soc_site_desc'),
                    site_url:    document.getElementById('soc_site_url'),
                    twitter:     document.getElementById('soc_twitter'),
                    facebook:    document.getElementById('soc_facebook'),
                    instagram:   document.getElementById('soc_instagram'),
                    share_image: document.getElementById('soc_share_image'),
                    keywords:    document.getElementById('soc_keywords'),
                    categories:  document.getElementById('soc_categories'),
                };
                if (!fields.site_name) return; // not on sharing tab

                function updatePreviews() {
                    const name  = fields.site_name.value;
                    const desc  = fields.site_desc.value;
                    const url   = fields.site_url.value;
                    const img   = fields.share_image.value;
                    let domain = url;
                    try { domain = new URL(url).hostname; } catch (e) {}

                    document.getElementById('prevOgImage').src         = img;
                    document.getElementById('prevTwImage').src         = img;
                    document.getElementById('prevOgDomain').textContent = domain.toUpperCase();
                    document.getElementById('prevOgTitle').textContent  = name;
                    document.getElementById('prevOgDesc').textContent   = desc;
                    document.getElementById('prevTwTitle').textContent  = name;
                    document.getElementById('prevTwDesc').textContent   = desc;
                    document.getElementById('prevTwDomain').textContent = '🔗 ' + domain;
                }

                Object.values(fields).forEach(el => { if (el) el.addEventListener('input', updatePreviews); });

                const saveBtn = document.getElementById('socialSaveBtn');
                const status  = document.getElementById('socialStatus');
                if (saveBtn) {
                    saveBtn.addEventListener('click', async () => {
                        status.textContent = 'Saving…';
                        status.style.color = '#aaa';
                        try {
                            const resp = await fetch('/biblioteca/save-social.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    site:   { name: fields.site_name.value, description: fields.site_desc.value, url: fields.site_url.value },
                                    social: {
                                        twitter: fields.twitter.value,
                                        facebook: fields.facebook.value,
                                        instagram: fields.instagram.value,
                                        share_image: fields.share_image.value,
                                        keywords: fields.keywords.value,
                                        categories: fields.categories.value,
                                    },
                                }),
                            });
                            const data = await resp.json();
                            if (data.ok) {
                                status.textContent = Array.isArray(data.auto_tasks) ? '✅ Saved and share assets updated' : '✅ Saved';
                                status.style.color = 'var(--success, #4ade80)';
                                const reasons = (data.build_required_state && data.build_required_state.reasons) || ['social_config_changed'];
                                const action = (data.build_required_state && data.build_required_state.action) || 'full';
                                setBuildRequiredNudge(data.build_required === true, reasons, action, (data.build_required_state && data.build_required_state.tasks) || []);
                                await refreshBuildRequiredState();
                                refreshBuildHint();
                            } else {
                                status.textContent = '❌ ' + (data.error || 'Unknown error');
                                status.style.color = '#f55';
                            }
                        } catch (e) {
                            status.textContent = '❌ ' + e.message;
                            status.style.color = '#f55';
                        }
                    });
                }
            })();

            // ── Build ─────────────────────────────────────────────────────────
            const buildBtn     = document.getElementById('buildBtn');
            const optimizeBtn  = document.getElementById('optimizeBtn');
            const buildHelpBox = document.getElementById('help-build');
            const buildSpinner = document.getElementById('buildSpinner');
            const optimizeSpinner = document.getElementById('optimizeSpinner');
            const buildLog     = document.getElementById('buildLog');
            const buildStatus  = document.getElementById('buildStatus');
            const buildValidationCard = document.getElementById('buildValidationCard');
            const buildValidationSummary = document.getElementById('buildValidationSummary');
            const buildValidationOverall = document.getElementById('buildValidationOverall');
            let pollTimer      = null;
            let currentRunMode = 'full';
            let currentBuildTasks = [];

            function runRecommendedAction() {
                if (pollTimer) return;
                if (currentBuildAction === 'optimize' && optimizeBtn && !optimizeBtn.disabled) {
                    optimizeBtn.click();
                    return;
                }
                if (buildBtn && !buildBtn.disabled) {
                    buildBtn.click();
                }
            }

            function maybeRunRecommendedActionFromQuery() {
                if (!pendingBuildRunFromQuery || triggeredBuildRunFromQuery) {
                    return;
                }

                triggeredBuildRunFromQuery = true;
                clearRecommendedRunQuery();

                const logCard = document.getElementById('build-log-card');
                if (logCard) {
                    logCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                if (currentBuildRequired) {
                    runRecommendedAction();
                }
            }

            if (recommendedBuildBtn) {
                recommendedBuildBtn.addEventListener('click', runRecommendedAction);
            }

            function refreshBuildActionCopy() {
                if (buildBtn) {
                    buildBtn.textContent = '▶️ Run Publish Build';
                }
                if (optimizeBtn) {
                    optimizeBtn.textContent = '🖼️ Refresh Image Files';
                }
                if (buildHelpBox) {
                    if (currentBuildRequired) {
                        const tasks = formatBuildTaskList({ tasks: currentBuildTasks });
                        const actionLabel = getBuildActionLabel(currentBuildAction);
                        const taskLine = tasks.length
                            ? `Pending now: <strong>${escapeHtml(tasks.join(' · '))}</strong>.`
                            : 'Pending now: bandPromo still has publish work to finish.';
                        buildHelpBox.innerHTML = `${actionLabel} is the recommended next step for the current pending work. ${taskLine} Jobs continue in the background while this log updates.`;
                    } else {
                        buildHelpBox.innerHTML = 'Use <strong>Refresh Image Files</strong> when only publish-ready photo, illustration, or theme-image files need to be regenerated. Use <strong>Run Publish Build</strong> when audio, video, validation, playlist, manifest, or other heavier publish steps are still pending. Jobs continue in the background while this log updates.';
                    }
                }
            }

            function refreshBuildHint() {
                if (!buildStatus) return;
                if (pollTimer) return;
                if (currentBuildRequired) {
                    buildStatus.textContent = formatBuildHintMessage({
                        action: currentBuildAction,
                        tasks: currentBuildTasks,
                        reasons: currentBuildReasons,
                    });
                    buildStatus.style.color = '#f0b429';
                    buildStatus.dataset.mode = 'nudge';
                } else if (buildStatus.dataset.mode === 'nudge') {
                    buildStatus.textContent = '';
                    buildStatus.removeAttribute('data-mode');
                }
            }

            refreshBuildHint();
            refreshBuildActionCopy();

            function scrollLog() {
                if (buildLog) buildLog.scrollTop = buildLog.scrollHeight;
            }

            function setBuildValidationVisibility(visible) {
                if (!buildValidationCard) return;
                buildValidationCard.style.display = visible ? '' : 'none';
            }

            function humanizeValidationCode(code) {
                return String(code || '')
                    .replace(/[_\-]+/g, ' ')
                    .trim()
                    .replace(/\b\w/g, char => char.toUpperCase());
            }

            function classifyValidationWarning(code, track, context) {
                const totalTracks = Number(context.totalTracks || 0);
                const coverSource = String((track && track.coverSource) || '').toLowerCase();
                const hasApprovedFallbackCover = !!(track && track.cover && ['configured', 'embedded', 'sidecar'].includes(coverSource));

                switch (String(code || '').toLowerCase()) {
                    case 'missing_title_tag':
                        return { severity: 'fix-before-publish', action: 'Add the song title so fans know what they are listening to' };
                    case 'missing_artist_tag':
                        return { severity: 'fix-before-publish', action: 'Add the artist name' };
                    case 'missing_album_tag':
                        return { severity: 'recommended-fix', action: 'Add the album or release name (helpful for fans)' };
                    case 'missing_track_number':
                        return {
                            severity: totalTracks > 1 ? 'fix-before-publish' : 'recommended-fix',
                            action: totalTracks > 1
                                ? 'Set the track number so the playlist stays in the right order'
                                : 'Set the track number if this song belongs to a numbered release',
                        };
                    case 'missing_lyrics':
                        return { severity: 'recommended-fix', action: 'Add lyrics if you want them to show on the site' };
                    case 'missing_cover_art':
                        return hasApprovedFallbackCover
                            ? { severity: 'can-be-repaired-automatically', action: 'Cover art can be added automatically when you update the site' }
                            : { severity: 'fix-before-publish', action: 'Add cover art so this track looks complete on the site' };
                    default:
                        return {
                            severity: 'recommended-fix',
                            action: 'Open the song in Files and fill in any missing information',
                        };
                }
            }

            function buildValidationSummaryModel(validation) {
                if (!validation || typeof validation !== 'object') {
                    return null;
                }

                const tracks = Array.isArray(validation.tracks) ? validation.tracks : [];
                const summary = validation.summary || {};
                const unsupported = Array.isArray(validation.unsupportedSourceFiles) ? validation.unsupportedSourceFiles : [];
                const items = [];
                const counts = {
                    'cannot-build': 0,
                    'fix-before-publish': 0,
                    'recommended-fix': 0,
                    'can-be-repaired-automatically': 0,
                };

                unsupported.forEach(file => {
                    counts['cannot-build'] += 1;
                    items.push({
                        title: 'This file cannot be used',
                        file: String(file || ''),
                        primary: {
                            severity: 'cannot-build',
                            action: 'Replace it with an MP3 or FLAC file, or remove it from your audio folder',
                        },
                        extras: [],
                        actions: [
                            {
                                label: 'Open your files',
                                href: buildAudioFilesUrl(String(file || '')),
                            }
                        ],
                    });
                });

                tracks.forEach(track => {
                    const warnings = Array.isArray(track.warnings) ? track.warnings : [];
                    if (!warnings.length) {
                        return;
                    }

                    const classified = warnings.map(code => classifyValidationWarning(code, track, {
                        totalTracks: summary.totalTracks ?? tracks.length,
                    }));

                    classified.forEach(issue => {
                        counts[issue.severity] = (counts[issue.severity] || 0) + 1;
                    });

                    classified.sort((left, right) => {
                        return (validationSeverityConfig[right.severity]?.rank || 0) - (validationSeverityConfig[left.severity]?.rank || 0);
                    });

                    const actions = [];
                    const actionKeys = new Set();
                    warnings.forEach(code => {
                        let action = null;
                        switch (String(code || '').toLowerCase()) {
                            case 'missing_title_tag':
                            case 'missing_artist_tag':
                            case 'missing_album_tag':
                                action = {
                                    key: 'metadata',
                                    label: 'Fix song info',
                                    href: buildAudioMetadataUrl(String(track.file || '')),
                                };
                                break;
                            case 'missing_track_number':
                                action = {
                                    key: 'metadata-track',
                                    label: 'Set track order',
                                    href: buildAudioMetadataUrl(String(track.file || '')),
                                };
                                break;
                            case 'missing_lyrics':
                                action = {
                                    key: 'metadata-full',
                                    label: 'Edit song details',
                                    href: buildAudioFullMetadataUrl(String(track.file || '')),
                                };
                                break;
                            case 'missing_cover_art':
                                action = {
                                    key: 'files',
                                    label: 'Open your files',
                                    href: buildAudioFilesUrl(String(track.file || '')),
                                };
                                break;
                            default:
                                action = {
                                    key: 'files',
                                    label: 'Open your files',
                                    href: buildAudioFilesUrl(String(track.file || '')),
                                };
                                break;
                        }

                        if (action && !actionKeys.has(action.key) && action.href !== '?') {
                            actionKeys.add(action.key);
                            actions.push({ label: action.label, href: action.href });
                        }
                    });

                    items.push({
                        title: String(track.title || track.file || 'Untitled track'),
                        file: String(track.file || ''),
                        primary: classified[0],
                        extras: classified.slice(1),
                        actions,
                    });
                });

                const overallSeverity = Object.keys(validationSeverityConfig)
                    .sort((left, right) => validationSeverityConfig[right].rank - validationSeverityConfig[left].rank)
                    .find(key => counts[key] > 0) || 'can-be-repaired-automatically';

                return {
                    totalTracks: Number(summary.totalTracks ?? tracks.length),
                    tracksWithWarnings: Number(summary.tracksWithWarnings ?? items.length),
                    tracksWithoutWarnings: Number(summary.tracksWithoutWarnings ?? Math.max(0, tracks.length - items.length)),
                    items,
                    counts,
                    overallSeverity,
                };
            }

            function renderBuildValidationSummary(validation) {
                if (!buildValidationSummary || !buildValidationOverall) {
                    return;
                }

                const model = buildValidationSummaryModel(validation);
                if (!model) {
                    setBuildValidationVisibility(false);
                    buildValidationSummary.innerHTML = '';
                    buildValidationOverall.textContent = 'No validation data';
                    buildValidationOverall.className = 'badge audit-status-badge status-neutral';
                    return;
                }

                setBuildValidationVisibility(true);

                const overallConfig = model.items.length
                    ? validationSeverityConfig[model.overallSeverity]
                    : { label: 'No validation issues', statusClass: 'status-ok' };
                buildValidationOverall.textContent = overallConfig.label;
                buildValidationOverall.className = `badge audit-status-badge ${overallConfig.statusClass}`;

                const metrics = [
                    `<span class="build-validation-metric">${model.totalTracks} tracks checked</span>`,
                    `<span class="build-validation-metric">${model.tracksWithWarnings} tracks need attention</span>`,
                    `<span class="build-validation-metric">${model.tracksWithoutWarnings} tracks clear</span>`,
                ];

                Object.entries(validationSeverityConfig).forEach(([key, config]) => {
                    if (model.counts[key] > 0) {
                        metrics.push(`<span class="build-validation-metric">${escapeHtml(config.label)}: ${model.counts[key]}</span>`);
                    }
                });

                if (!model.items.length) {
                    buildValidationSummary.innerHTML = `
                        <div class="build-validation-metrics">${metrics.join('')}</div>
                        <p class="build-validation-empty">No current metadata validation issues were found in the checked tracks.</p>
                    `;
                    return;
                }

                const listHtml = model.items.map(item => {
                    const primaryConfig = validationSeverityConfig[item.primary.severity] || validationSeverityConfig['recommended-fix'];
                    const actions = [item.primary, ...item.extras].map(issue => {
                        const config = validationSeverityConfig[issue.severity] || validationSeverityConfig['recommended-fix'];
                        return `<li><strong>${escapeHtml(config.label)}:</strong> ${escapeHtml(issue.action)}</li>`;
                    }).join('');

                    const fileLine = item.file && item.file !== item.title
                        ? `<div class="build-validation-item-file">${escapeHtml(item.file)}</div>`
                        : '';
                    const actionLinks = Array.isArray(item.actions) && item.actions.length
                        ? `<div class="build-validation-item-links">${item.actions.map(action => `<a class="build-validation-link" href="${escapeHtml(action.href)}">${escapeHtml(action.label)}</a>`).join('')}</div>`
                        : '';

                    return `
                        <article class="build-validation-item">
                            <div class="build-validation-item-head">
                                <div>
                                    <div class="build-validation-item-title">${escapeHtml(item.title)}</div>
                                    ${fileLine}
                                </div>
                                <span class="badge audit-status-badge ${primaryConfig.statusClass}">${escapeHtml(primaryConfig.label)}</span>
                            </div>
                            <ul class="build-validation-item-actions">${actions}</ul>
                            ${actionLinks}
                        </article>
                    `;
                }).join('');

                buildValidationSummary.innerHTML = `
                    <div class="build-validation-metrics">${metrics.join('')}</div>
                    <div class="build-validation-list">${listHtml}</div>
                `;
            }

            function stopPolling(success) {
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
                if (buildBtn) buildBtn.disabled = false;
                if (optimizeBtn) optimizeBtn.disabled = false;
                if (buildSpinner) buildSpinner.style.display = 'none';
                if (optimizeSpinner) optimizeSpinner.style.display = 'none';
                if (buildStatus) {
                    const successLabel = currentRunMode === 'optimize' ? '✅ Media optimization complete!' : '✅ Build complete!';
                    const failLabel = currentRunMode === 'optimize' ? '❌ Media optimization failed.' : '❌ Build failed.';
                    buildStatus.textContent = success === true ? successLabel : success === false ? failLabel : '';
                    buildStatus.style.color = success === true ? 'var(--success, #4ade80)' : '#f55';
                    buildStatus.removeAttribute('data-mode');
                }
                refreshBuildHint();
            }

            function renderMetadataValidation(validation) {
                if (!validation || typeof validation !== 'object') {
                    return '';
                }

                const summary = validation.summary || {};
                const tracks = Array.isArray(validation.tracks) ? validation.tracks : [];
                const lines = [];

                lines.push('');
                lines.push('--- Metadata Validation ---');
                lines.push(`Total tracks: ${summary.totalTracks ?? tracks.length}`);
                lines.push(`Tracks with warnings: ${summary.tracksWithWarnings ?? 0}`);
                lines.push(`Tracks without warnings: ${summary.tracksWithoutWarnings ?? 0}`);

                const unsupported = Array.isArray(validation.unsupportedSourceFiles) ? validation.unsupportedSourceFiles : [];
                if (unsupported.length) {
                    lines.push(`Unsupported source files: ${unsupported.join(', ')}`);
                }

                if (tracks.length) {
                    lines.push('');
                    lines.push('Per-track details:');
                    tracks.forEach(track => {
                        const name = track.file || track.title || '(unknown)';
                        const coverSource = track.coverSource || 'missing';
                        const warnings = Array.isArray(track.warnings) && track.warnings.length
                            ? track.warnings.join(', ')
                            : 'none';
                        lines.push(`- ${name}`);
                        lines.push(`  cover: ${coverSource}${track.cover ? ` (${track.cover})` : ''}`);
                        lines.push(`  warnings: ${warnings}`);
                    });
                }

                return lines.join('\n');
            }

            refreshBuildRequiredState();

            async function pollLog() {
                try {
                    const resp = await fetch('/biblioteca/get-build-log.php?mode=' + encodeURIComponent(currentRunMode));
                    const data = await resp.json();
                    console.debug('[build] pollLog', {
                        status: resp.status,
                        mode: data.mode,
                        is_running: data.is_running,
                        success: data.success,
                        exit_code: data.exit_code,
                        has_validation: !!data.metadata_validation,
                    });
                    if (data.content !== undefined && buildLog) {
                        let output = data.content || '(empty)';
                        if (!data.is_running && data.metadata_validation) {
                            output += renderMetadataValidation(data.metadata_validation);
                        }
                        buildLog.textContent = output;
                        scrollLog();
                    }

                    if (data.is_running) {
                        setBuildValidationVisibility(false);
                    } else {
                        latestBuildValidation = data.metadata_validation || null;
                        renderBuildValidationSummary(data.metadata_validation);
                    }

                    if (data.build_required_state) {
                        setBuildRequiredNudge(
                            data.build_required === true,
                            data.build_required_state.reasons || [],
                            data.build_required_state.action || 'none',
                            data.build_required_state.tasks || []
                        );
                        renderOperatorNotifications(data.build_required_state, latestBuildValidation);
                        refreshBuildHint();
                    }

                    if (!data.is_running) {
                        stopPolling(data.success === true);
                    }
                } catch (e) {
                    // network hiccup — keep polling
                }
            }

            if (buildBtn) {
                buildBtn.addEventListener('click', async () => {
                    currentRunMode = 'full';
                    console.groupCollapsed('[build] Start button clicked');
                    buildBtn.disabled  = true;
                    if (optimizeBtn) optimizeBtn.disabled = true;
                    buildSpinner.style.display = 'inline';
                    if (optimizeSpinner) optimizeSpinner.style.display = 'none';
                    buildStatus.textContent = '';
                    buildLog.textContent = '⏳ Starting build…\n';

                    try {
                        const resp = await fetch('/biblioteca/build.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ mode: 'full' }),
                        });
                        const raw = await resp.text();
                        let data = null;
                        try {
                            data = JSON.parse(raw);
                        } catch (parseErr) {
                            console.error('[build] build.php returned non-JSON response', {
                                status: resp.status,
                                raw,
                                parseError: parseErr,
                            });
                            buildLog.textContent = '❌ Invalid response from build endpoint';
                            stopPolling(false);
                            console.groupEnd();
                            return;
                        }

                        console.debug('[build] build.php response', {
                            status: resp.status,
                            ok: data?.ok,
                            error: data?.error,
                            debug: data?.debug,
                        });

                        if (data.error) {
                            buildLog.textContent = '❌ ' + data.error;
                            if (data.debug) {
                                console.error('[build] launcher failure debug', data.debug);
                            }
                            stopPolling(false);
                            console.groupEnd();
                            return;
                        }
                        pollTimer = setInterval(pollLog, 1000);
                        console.groupEnd();
                    } catch (e) {
                        console.error('[build] network/launch error', e);
                        buildLog.textContent = '❌ Network error: ' + e.message;
                        stopPolling(false);
                        console.groupEnd();
                    }
                });
            }

            if (optimizeBtn) {
                optimizeBtn.addEventListener('click', async () => {
                    currentRunMode = 'optimize';
                    console.groupCollapsed('[optimize] Start button clicked');
                    optimizeBtn.disabled = true;
                    if (buildBtn) buildBtn.disabled = true;
                    optimizeSpinner.style.display = 'inline';
                    if (buildSpinner) buildSpinner.style.display = 'none';
                    buildStatus.textContent = '';
                    buildLog.textContent = '⏳ Starting media optimization…\n';

                    try {
                        const resp = await fetch('/biblioteca/build.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ mode: 'optimize' }),
                        });
                        const raw = await resp.text();
                        let data = null;
                        try {
                            data = JSON.parse(raw);
                        } catch (parseErr) {
                            console.error('[optimize] build.php returned non-JSON response', {
                                status: resp.status,
                                raw,
                                parseError: parseErr,
                            });
                            buildLog.textContent = '❌ Invalid response from optimize endpoint';
                            stopPolling(false);
                            console.groupEnd();
                            return;
                        }

                        console.debug('[optimize] build.php response', {
                            status: resp.status,
                            ok: data?.ok,
                            error: data?.error,
                            debug: data?.debug,
                        });

                        if (data.error) {
                            buildLog.textContent = '❌ ' + data.error;
                            if (data.debug) {
                                console.error('[optimize] launcher failure debug', data.debug);
                            }
                            stopPolling(false);
                            console.groupEnd();
                            return;
                        }
                        pollTimer = setInterval(pollLog, 1000);
                        console.groupEnd();
                    } catch (e) {
                        console.error('[optimize] network/launch error', e);
                        buildLog.textContent = '❌ Network error: ' + e.message;
                        stopPolling(false);
                        console.groupEnd();
                    }
                });
            }
        })();
