function bandpromoAdminFormatDate(date) {
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function bandpromoAdminEscapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

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
        
        dateStartInput.value = bandpromoAdminFormatDate(startDate);
        dateEndInput.value = bandpromoAdminFormatDate(today);
        
        presetBtnsContainer.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        form.submit();
    });
});

// Auto-submit on date or activity filter change
document.querySelectorAll('input[name="date_start"], input[name="date_end"]').forEach(input => {
    input.addEventListener('change', function() {
        const form = this.closest('form');
        if (!form) {
            return;
        }
        const presetBtnsContainer = form.querySelector('.filter-preset-btns');
        if (presetBtnsContainer) {
            presetBtnsContainer.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
        }
        form.submit();
    });
});

document.querySelectorAll('.filter-bar-select[name="activity_filter"]').forEach(select => {
    select.addEventListener('change', function() {
        const form = this.closest('form');
        if (form) {
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
    
    const axisLabel = typeof adminTimeAxisLabel === 'string' ? adminTimeAxisLabel : 'UTC';
    
    try {
        const ctx = hourlyChartCanvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: `Activity (${axisLabel})`,
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
                    x: {
                        offset: true,
                        title: { display: true, text: `Hour (${axisLabel})`, color: '#aaa' },
                        ticks: { color: '#aaa', maxRotation: 0 },
                    },
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
    const today = new Date();
    const todayStr = bandpromoAdminFormatDate(today);

    const presets = {
        day:   bandpromoAdminFormatDate(new Date(today)),
        week:  bandpromoAdminFormatDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 7)),
        month: bandpromoAdminFormatDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 30)),
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
        const previewEl = document.getElementById('adminPreviewLightbox');
        if (previewEl && previewEl.classList.contains('active')) {
            if (typeof closeAdminPreview === 'function') {
                closeAdminPreview();
            }
            return;
        }
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
// Tab panel logic — Files, Settings, System
// (data vars injected by admin.php: adminActivePanel, adminActiveTab, adminContentTab, adminDateStart, adminDateEnd)
// =============================================================================
        (function () {
            const adminPrimaryTab = typeof adminActiveTab === 'string'
                ? adminActiveTab
                : (new URLSearchParams(window.location.search).get('tab') || 'welcome');
            const adminContentSubTab = typeof adminContentTab === 'string'
                ? adminContentTab
                : (new URLSearchParams(window.location.search).get('cntab') || 'release');
            const adminFilesTabActive = adminPrimaryTab === 'files';
            const adminContentTabActive = adminPrimaryTab === 'content';

            // ── Media sub-panels ──────────────────────────────────────────────
            const mediaCfg = {
                audio:          { accept: '.flac,.mp3,.wav',               target: 'audio'         },
                video:          { accept: '.mp4,.webm,.mov',               target: 'video'         },
                illustrations:  { accept: '.png,.jpg,.jpeg',               target: 'illustrations' },
                photos:         { accept: '.png,.jpg,.jpeg,.webp',         target: 'photos'        },
                special:        { accept: '.mp3,.mp4,.png,.jpg,.jpeg,.webp,.svg', target: 'special' },
            };
            window.activeMediaPanel = adminActivePanel;
            function isDeliverablesViewActive() {
                const systemTab = document.getElementById('tab-system');
                if (!systemTab?.classList.contains('active')) {
                    return false;
                }
                const stab = new URLSearchParams(window.location.search).get('stab') || 'deliverables';
                return stab === 'deliverables' || stab === 'publish';
            }

            const systemTabLink = document.querySelector('.primary-tabs .tab-link[href*="tab=system"]');
            const recommendedBuildBtn = document.getElementById('recommendedBuildBtn');
            const operatorNotificationsToggle = document.getElementById('operatorNotificationsToggle');
            const operatorNotificationsCount = document.getElementById('operatorNotificationsCount');
            const operatorNotificationsModal = document.getElementById('operatorNotificationsModal');
            const operatorNotificationsModalBody = document.getElementById('operatorNotificationsModalBody');
            const operatorNotificationsClose = document.getElementById('operatorNotificationsClose');
            const toastHost = document.getElementById('adminToastHost');
            let adminCsrf = typeof adminCsrfToken === 'string' ? adminCsrfToken : '';
            let currentBuildRequired = false;
            let currentBuildAction = 'none';
            let currentBuildReasons = [];
            let latestBuildValidation = null;
            let latestWelcomeState = null;
            let latestPackageUpdate = null;
            let packageUpdateInstallInProgress = false;
            let latestBackgroundTasks = null;
            let backgroundTaskPollTimer = null;
            let modalTarget = null;
            let modalFiles  = [];
            let mediaPickerState = null;
            let poolReleaseFilter = 'all';
            let releasesCatalog = [];
            const releaseFilterListeners = [];
            function registerReleaseFilterListener(listener) {
                if (typeof listener === 'function') {
                    releaseFilterListeners.push(listener);
                }
            }
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
                'setup-step': { label: 'Setup', itemClass: 'is-attention', summaryClass: 'status-warning' },
                'package-update': { label: 'Update available', itemClass: 'is-attention', summaryClass: 'status-warning' },
                'background-running': { label: 'In progress', itemClass: 'is-recommended', summaryClass: 'status-neutral' },
                'background-done': { label: 'Finished', itemClass: 'is-recommended', summaryClass: 'status-ok' },
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

            function isPreviewable(name, file = null, type = '') {
                if (isImage(name)) {
                    return true;
                }
                if (!isVideo(name)) {
                    return false;
                }
                if (type === 'video' && file) {
                    return videoPreviewUrl(file) !== '' || videoPosterUrl(file) !== '';
                }
                return true;
            }

            function videoPosterUrl(file) {
                return String(file?.poster_url || file?.video_meta?.poster_url || '').trim();
            }

            function videoPreviewUrl(file) {
                return String(file?.preview_url || file?.video_meta?.preview_url || '').trim();
            }

            function buildVideoThumbMarkup(type, file, safeName, basePath) {
                const poster = videoPosterUrl(file);
                const previewSrc = videoPreviewUrl(file);
                const openSrc = previewSrc || poster;
                const openHandler = openSrc
                    ? `onclick="event.stopPropagation(); openAdminPreview('${openSrc}', '${safeName}')"`
                    : '';
                if (file.delivery_running) {
                    return `<span class="media-file-thumb media-file-thumb-video is-preparing" title="Preparing in background">⏳</span>`;
                }
                if (poster) {
                    return `<img class="media-file-thumb" src="${poster}" alt="" loading="lazy" ${openHandler} title="Preview">`;
                }
                if (previewSrc) {
                    return `<video class="media-file-thumb" src="${previewSrc}" preload="metadata" muted ${openHandler} title="Preview"></video>`;
                }
                if (file.delivery_pending) {
                    return `<span class="media-file-thumb media-file-thumb-video is-preparing" title="Queued for background preparation">⏳</span>`;
                }
                return `<span class="media-file-thumb media-file-thumb-video" title="Waiting for background preparation">▶</span>`;
            }

            function buildVideoPickerMarkup(file) {
                const poster = videoPosterUrl(file);
                const previewSrc = videoPreviewUrl(file);
                if (poster) {
                    return `<img src="${poster}" alt="" loading="lazy"><span class="media-picker-tile-badge" aria-hidden="true">▶</span>`;
                }
                if (previewSrc) {
                    return `<video src="${previewSrc}" preload="metadata" muted></video><span class="media-picker-tile-badge" aria-hidden="true">▶</span>`;
                }
                return `<span class="media-picker-tile-icon">▶</span>`;
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
                return `${buildAdminUrl({ tab: 'system', stab: 'deliverables' })}#build-log-card`;
            }

            function buildRecommendedRunUrl() {
                return `${buildAdminUrl({ tab: 'system', stab: 'deliverables', run_recommended: '1' })}#build-log-card`;
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
                if (latestWelcomeState && latestWelcomeState.setup_complete === true) {
                    if (!tasks.length) {
                        return 'Check Notifications for any remaining preparation issues.';
                    }
                    return `Some preparation could not finish automatically (${tasks.join(', ')}). Check Notifications.`;
                }
                const action = 'Rebuild all deliverables';
                if (!tasks.length) {
                    return `Next: run ${action}.`;
                }

                if (tasks.length === 1) {
                    return `Next: run ${action} to ${tasks[0].charAt(0).toLowerCase() + tasks[0].slice(1)}.`;
                }

                return `Next: run ${action} to finish ${tasks.length} pending tasks.`;
            }

            function getBuildActionLabel() {
                return 'Rebuild all deliverables';
            }

            function formatBuildHintMessage(state) {
                const actionLabel = getBuildActionLabel();
                const tasks = formatBuildTaskList(state);
                if (tasks.length === 1) {
                    return `⚠ ${tasks[0]} Use ${actionLabel} when you are ready.`;
                }
                if (tasks.length > 1) {
                    return `⚠ ${tasks.length} steps are waiting. Use ${actionLabel} when you are ready.`;
                }
                return '⚠ Your latest changes may need refreshed delivery files. Rebuild all deliverables when you are ready.';
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

            function buildNotificationFromBuildState(buildState, welcome) {
                if (!buildState || buildState.required !== true) {
                    return null;
                }

                const setupComplete = !!(welcome && welcome.setup_complete === true);
                const taskDetails = formatBuildTaskList(buildState);
                const summaryDetails = taskDetails.length
                    ? taskDetails.map(text => ({ text }))
                    : [{ text: formatBuildTaskSummary(buildState) }];
                const reasons = Array.isArray(buildState.reasons) ? buildState.reasons : [];
                const afterPackageUpdate = reasons.includes('package_update');
                const action = 'full';
                const actionLabel = getBuildActionLabel();

                if (setupComplete && afterPackageUpdate) {
                    return {
                        severity: 'recommended-fix',
                        title: 'Site update installed — refresh your public site',
                        file: '',
                        checkedAt: String(buildState.updated_at || '').trim(),
                        details: [
                            { text: 'Your content and settings were preserved. Rebuild all deliverables once so listener-ready files and the site manifest match the new version.' },
                            ...(taskDetails.length ? [{ text: `Pending: ${taskDetails.join('; ')}.` }] : []),
                        ],
                        actions: [
                            { label: actionLabel, action: 'run-recommended-build' },
                            { label: 'Go to Deliverables', href: buildBuildTabUrl() },
                        ],
                    };
                }

                if (setupComplete) {
                    const taskIntro = taskDetails.length
                        ? `Pending: ${taskDetails.join('; ')}.`
                        : formatBuildTaskSummary(buildState);

                    return {
                        severity: 'recommended-fix',
                        title: 'Saved changes are not live yet',
                        file: '',
                        checkedAt: String(buildState.updated_at || '').trim(),
                        details: [
                            { text: 'Your edits are saved in admin. Rebuild all deliverables when you are ready for visitors to get the latest files.' },
                            { text: taskIntro },
                        ],
                        actions: [
                            { label: actionLabel, action: 'run-recommended-build' },
                            { label: 'Go to Deliverables', href: buildBuildTabUrl() },
                        ],
                    };
                }

                const introDetail = { text: 'You made changes that are saved in admin but may still need refreshed delivery files for visitors.' };

                return {
                    severity: 'build-step',
                    title: 'Delivery files may need a refresh',
                    file: '',
                    checkedAt: String(buildState.updated_at || '').trim(),
                    details: [introDetail, ...summaryDetails],
                    actions: [
                        { label: actionLabel, action: 'run-recommended-build' },
                        { label: 'Go to Deliverables', href: buildBuildTabUrl() },
                    ],
                };
            }

            function buildNotificationFromWelcomeItem(item) {
                if (!item || item.complete === true) {
                    return null;
                }

                const severity = String(item.severity || 'nonblocking') === 'blocking'
                    ? 'setup-step'
                    : 'recommended-fix';

                return {
                    severity,
                    title: item.label,
                    file: '',
                    checkedAt: String(item.updated_at || item.checked_at || '').trim(),
                    details: [
                        { text: String(item.next || item.detail || '').trim() },
                    ].filter((detail) => detail.text !== ''),
                    actions: [
                        { label: item.action_label || 'Open step', href: item.href || '?tab=welcome' },
                    ],
                };
            }

            function buildNotificationFromPackageUpdate(packageUpdate) {
                if (!packageUpdate || packageUpdate.update_available !== true) {
                    return null;
                }

                const installed = packageUpdate.installed_version || 'unknown';
                const remote = packageUpdate.remote_version || 'a newer release';

                return {
                    severity: 'package-update',
                    title: `Site update available (${installed} → ${remote})`,
                    file: '',
                    checkedAt: String(packageUpdate.last_update || '').trim(),
                    details: [
                        { text: 'A newer bandPromo package is published. Application files can be updated while web-config.json, .env, data/, media/, and log/ stay preserved.' },
                    ],
                    actions: [
                        { label: 'Go to Site update', href: '?tab=welcome#packageUpdateCard' },
                    ],
                };
            }

            function buildNotificationsFromBackgroundTasks(backgroundTasks) {
                const notifications = [];
                const items = backgroundTasks && Array.isArray(backgroundTasks.items) ? backgroundTasks.items : [];

                items.forEach((item) => {
                    if (!item || typeof item !== 'object') {
                        return;
                    }

                    const status = String(item.status || '').trim();
                    const files = Array.isArray(item.files) ? item.files.filter(Boolean) : [];
                    const fileLine = files.length
                        ? files.join(', ')
                        : 'video files';

                    if (status === 'running') {
                        notifications.push({
                            severity: 'background-running',
                            title: 'Preparing videos in the background',
                            file: '',
                            checkedAt: String(item.started_at || item.updated_at || '').trim(),
                            details: [
                                { text: `bandPromo is preparing ${fileLine} in the background. You can keep working — no action needed.` },
                            ],
                            actions: [],
                        });
                        return;
                    }

                    if (status === 'done') {
                        notifications.push({
                            severity: 'background-done',
                            title: 'Video preparation finished',
                            file: '',
                            checkedAt: String(item.finished_at || item.updated_at || '').trim(),
                            details: [
                                { text: `${fileLine} ${files.length === 1 ? 'is' : 'are'} ready for preview and gallery use.` },
                            ],
                            actions: [
                                { label: 'Open Files', href: '?tab=files&fpanel=video' },
                            ],
                        });
                        return;
                    }

                    if (status === 'failed') {
                        const focusFile = files[0] || '';
                        const taskId = String(item.id || '').trim();
                        notifications.push({
                            severity: 'recommended-fix',
                            title: 'Video preparation needs attention',
                            file: focusFile,
                            taskId,
                            checkedAt: String(item.finished_at || item.started_at || item.updated_at || '').trim(),
                            details: [
                                { text: String(item.error || 'bandPromo could not prepare the video file in the background.').trim() },
                                { text: 'bandPromo will retry automatically after a short pause. If this keeps failing, check that ffmpeg is available on the host or re-upload the source file.' },
                            ],
                            actions: [
                                ...(focusFile
                                    ? [{ label: 'Open video in Files', href: buildAdminUrl({ tab: 'files', fpanel: 'video', focus_file: focusFile }) }]
                                    : [{ label: 'Open Files', href: '?tab=files&fpanel=video' }]),
                                ...(taskId ? [{ label: 'Dismiss', action: 'dismiss-background-task', taskId }] : []),
                            ],
                        });
                    }
                });

                return notifications;
            }

            function backgroundTasksNeedPolling(backgroundTasks) {
                const items = backgroundTasks && Array.isArray(backgroundTasks.items) ? backgroundTasks.items : [];
                return items.some((item) => item && String(item.status || '') === 'running');
            }

            function updateBackgroundTaskPolling(backgroundTasks) {
                if (!backgroundTasksNeedPolling(backgroundTasks)) {
                    if (backgroundTaskPollTimer) {
                        clearInterval(backgroundTaskPollTimer);
                        backgroundTaskPollTimer = null;
                    }
                    return;
                }

                if (backgroundTaskPollTimer) {
                    return;
                }

                backgroundTaskPollTimer = setInterval(() => {
                    refreshBuildRequiredState({ scope: 'lite' });
                }, 4000);
            }

            function buildOperatorNotificationModel(buildState, validation, welcome, packageUpdate, backgroundTasks, uncataloguedAudioFailures) {
                const attention = [];
                const recommended = [];
                const background = [];
                const packageNotification = buildNotificationFromPackageUpdate(packageUpdate);
                const buildNotification = buildNotificationFromBuildState(buildState || {}, welcome);

                if (packageNotification) {
                    attention.push(packageNotification);
                }

                if (buildNotification) {
                    if (buildNotification.severity === 'recommended-fix') {
                        recommended.push(buildNotification);
                    } else {
                        attention.push(buildNotification);
                    }
                }

                if (welcome && welcome.setup_complete !== true && Array.isArray(welcome.checklist)) {
                    welcome.checklist.forEach((item) => {
                        const notification = buildNotificationFromWelcomeItem(item);
                        if (!notification) {
                            return;
                        }

                        if (notification.severity === 'recommended-fix') {
                            recommended.push(notification);
                        } else {
                            attention.push(notification);
                        }
                    });
                }

                const validationModel = buildValidationSummaryModel(validation);
                const validationCheckedAt = String(
                    validation?.generated_at || validation?.checked_at || ''
                ).trim();
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
                            checkedAt: validationCheckedAt,
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

                buildNotificationsFromBackgroundTasks(backgroundTasks).forEach((notification) => {
                    if (notification.severity === 'background-running' || notification.severity === 'background-done') {
                        background.push(notification);
                    } else if (notification.severity === 'recommended-fix') {
                        recommended.push(notification);
                    } else {
                        attention.push(notification);
                    }
                });

                if (Array.isArray(uncataloguedAudioFailures)) {
                    uncataloguedAudioFailures.forEach((entry) => {
                        const filename = String(entry?.filename || '').trim();
                        if (!filename) {
                            return;
                        }
                        const title = String(entry?.display_title || filename).trim() || filename;
                        const error = String(entry?.error || 'bandPromo could not register this upload automatically.').trim();
                        attention.push({
                            severity: 'recommended-fix',
                            title,
                            file: filename,
                            details: [
                                { text: error },
                                { text: 'bandPromo will retry automatic registration when you open Files or refresh notifications. If this keeps appearing, check the original file format or re-upload it.' },
                            ],
                            actions: [
                                { label: 'Open Files', href: buildAdminUrl({ tab: 'files', fpanel: 'audio', focus_file: filename }) },
                            ],
                        });
                    });
                }

                return {
                    attention,
                    recommended,
                    background,
                    attentionCount: attention.length,
                    recommendedCount: recommended.length,
                    backgroundCount: background.length,
                    totalCount: attention.length + recommended.length + background.length,
                    hasCritical: attention.some(item => item.severity === 'cannot-build' || item.severity === 'setup-step'),
                };
            }

            function formatOperatorNotificationAge(isoString) {
                const raw = String(isoString || '').trim();
                if (!raw) {
                    return '';
                }
                const date = new Date(raw);
                if (Number.isNaN(date.getTime())) {
                    return '';
                }
                const diffMs = Math.max(0, Date.now() - date.getTime());
                const diffMinutes = Math.floor(diffMs / 60000);
                if (diffMinutes < 1) {
                    return 'Checked just now';
                }
                if (diffMinutes < 60) {
                    return `Checked ${diffMinutes} min ago`;
                }
                const diffHours = Math.floor(diffMinutes / 60);
                if (diffHours < 48) {
                    return `Checked ${diffHours} h ago`;
                }
                return `Checked ${date.toLocaleString()}`;
            }

            function renderOperatorNotificationItem(item) {
                const severityConfig = operatorNotificationSeverityConfig[item.severity] || operatorNotificationSeverityConfig['recommended-fix'];
                const badgeConfig = validationSeverityConfig[item.severity] || {
                    label: severityConfig.label,
                    statusClass: severityConfig.summaryClass || 'status-neutral',
                };
                const fileLine = item.file && item.file !== item.title
                    ? `<div class="operator-notifications-item-file">File: ${bandpromoAdminEscapeHtml(item.file)}</div>`
                    : '';
                const ageLine = item.checkedAt
                    ? `<div class="operator-notifications-item-age">${bandpromoAdminEscapeHtml(formatOperatorNotificationAge(item.checkedAt))}</div>`
                    : '';
                const detailsHtml = Array.isArray(item.details) && item.details.length
                    ? `<ul class="operator-notifications-item-list">${item.details.map(detail => {
                        const text = String(detail.text || '').trim();
                        return text !== '' ? `<li>${bandpromoAdminEscapeHtml(text)}</li>` : '';
                    }).join('')}</ul>`
                    : '';
                const actionsHtml = Array.isArray(item.actions) && item.actions.length
                    ? `<div class="operator-notifications-actions">${item.actions.map(action => {
                        if (action && action.action) {
                            const taskAttr = action.taskId
                                ? ` data-operator-task-id="${bandpromoAdminEscapeHtml(action.taskId)}"`
                                : '';
                            return `<button type="button" class="operator-notifications-action" data-operator-action="${bandpromoAdminEscapeHtml(action.action)}"${taskAttr}>${bandpromoAdminEscapeHtml(action.label)}</button>`;
                        }
                        const href = String(action?.href || '?').trim() || '?';
                        return `<a class="operator-notifications-action" href="${bandpromoAdminEscapeHtml(href)}" data-operator-href="${bandpromoAdminEscapeHtml(href)}">${bandpromoAdminEscapeHtml(action.label || 'Open')}</a>`;
                    }).join('')}</div>`
                    : '';

                return `
                    <article class="operator-notifications-item ${severityConfig.itemClass}">
                        <div class="operator-notifications-item-head">
                            <div>
                                <div class="operator-notifications-item-title">${bandpromoAdminEscapeHtml(item.title)}</div>
                                ${fileLine}
                                ${ageLine}
                            </div>
                            <span class="badge audit-status-badge ${badgeConfig.statusClass}">${bandpromoAdminEscapeHtml(badgeConfig.label)}</span>
                        </div>
                        ${detailsHtml}
                        ${actionsHtml}
                    </article>
                `;
            }

            function renderOperatorNotificationSections(model) {
                if (!model || model.totalCount === 0) {
                    return '<p class="operator-notifications-empty">You are all caught up. No new notifications right now.</p>';
                }

                const sections = [
                    { title: 'Do these first', count: model.attentionCount, items: model.attention },
                    { title: 'In the background', count: model.backgroundCount, items: model.background },
                    { title: 'When you have time', count: model.recommendedCount, items: model.recommended },
                ].filter(section => section.count > 0);

                return sections.map(section => `
                    <section class="operator-notifications-section">
                        <div class="operator-notifications-section-head">
                            <h4>${bandpromoAdminEscapeHtml(section.title)}</h4>
                            <span class="operator-notifications-section-count">${section.count} ${section.count === 1 ? 'item' : 'items'}</span>
                        </div>
                        <div class="operator-notifications-list">${section.items.map(renderOperatorNotificationItem).join('')}</div>
                    </section>
                `).join('');
            }

            function renderOperatorNotifications(buildState, validation, welcome, packageUpdate, backgroundTasks, uncataloguedAudioFailures) {
                const model = buildOperatorNotificationModel(buildState, validation, welcome, packageUpdate, backgroundTasks, uncataloguedAudioFailures);
                const html = renderOperatorNotificationSections(model);

                if (operatorNotificationsModalBody) {
                    operatorNotificationsModalBody.innerHTML = html;
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

                if (operatorNotificationsToggle) {
                    operatorNotificationsToggle.classList.toggle(
                        'operator-notifications-urgent',
                        model.hasCritical || model.attentionCount > 0
                    );
                }
            }

            if (operatorNotificationsToggle && operatorNotificationsModal) {
                operatorNotificationsToggle.addEventListener('click', async () => {
                    openOperatorNotifications();
                    await refreshBuildRequiredState({ full: true });
                });
            }

            if (operatorNotificationsClose) {
                operatorNotificationsClose.addEventListener('click', closeOperatorNotifications);
            }

            document.addEventListener('click', (event) => {
                const actionLink = event.target.closest('a.operator-notifications-action[data-operator-href]');
                if (actionLink && operatorNotificationsModal && operatorNotificationsModal.contains(actionLink)) {
                    const href = String(actionLink.dataset.operatorHref || actionLink.getAttribute('href') || '').trim();
                    if (href && href !== '?' && href !== '#') {
                        event.preventDefault();
                        closeOperatorNotifications();
                        window.location.assign(href);
                        return;
                    }
                }

                const actionButton = event.target.closest('[data-operator-action]');
                if (!actionButton) {
                    return;
                }

                const action = String(actionButton.dataset.operatorAction || '').trim();
                if (action === 'run-recommended-build') {
                    event.preventDefault();
                    closeOperatorNotifications();
                    const publishViewActive = isDeliverablesViewActive();
                    if (!publishViewActive) {
                        window.location.href = buildRecommendedRunUrl();
                        return;
                    }
                    runRecommendedAction();
                    return;
                }

                if (action === 'dismiss-background-task') {
                    event.preventDefault();
                    const taskId = String(actionButton.dataset.operatorTaskId || '').trim();
                    if (!taskId) {
                        return;
                    }
                    dismissOperatorNotification('background-task', taskId);
                }
            });

            async function dismissOperatorNotification(type, id) {
                try {
                    const resp = await fetch('/biblioteca/dismiss-operator-notification.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ type, id }),
                    });
                    const data = await resp.json();
                    if (!resp.ok || data.error) {
                        throw new Error(data.error || 'Could not dismiss notification');
                    }
                    await refreshBuildRequiredState({ full: true });
                } catch (error) {
                    window.alert(error.message || 'Could not dismiss notification');
                }
            }

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
                    return `<span class="badge audit-status-badge ${statusClass} media-file-badge media-file-field-badge" title="${bandpromoAdminEscapeHtml(title)}" aria-label="${bandpromoAdminEscapeHtml(title)}">${bandpromoAdminEscapeHtml(shortLabel)}</span>`;
                }).join(' ');
            }

            function formatAudioMasterBadges(file) {
                const master = file.audio_master || {};
                const badges = [];

                if (file.in_catalog === false) {
                    badges.push('<span class="badge audit-status-badge status-warning media-file-badge" title="bandPromo is still registering this upload. Refresh the page in a moment or open Build if it stays here.">Registering…</span>');
                }

                if (audioDisplayMode === 'master' && !master.exists) {
                    const warning = String(master.prepare_warning || '').trim();
                    const title = warning !== ''
                        ? warning
                        : 'Master file is not available for this upload yet';
                    badges.push(`<span class="badge audit-status-badge status-warning media-file-badge" title="${bandpromoAdminEscapeHtml(title)}">Master pending</span>`);
                }

                badges.push(formatAudioMetadataHealthBadges(file));

                const releaseContextMarkup = formatAudioReleaseContextMarkup(file);
                if (releaseContextMarkup !== '') {
                    badges.push(releaseContextMarkup);
                }

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
                    badges.push(`<span class="badge audit-status-badge ${roleClass} media-file-badge" title="Role: ${bandpromoAdminEscapeHtml(roleLabel)}">${bandpromoAdminEscapeHtml(roleLabel)}</span>`);
                }

                if (origin && origin !== 'user-upload') {
                    const originLabel = coverOriginLabels[origin] || origin;
                    badges.push(`<span class="badge audit-status-badge status-neutral media-file-badge" title="Origin: ${bandpromoAdminEscapeHtml(originLabel)}">${bandpromoAdminEscapeHtml(originLabel)}</span>`);
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
                document.querySelectorAll('[data-media-filter-target]').forEach((select) => {
                    const target = String(select.dataset.mediaFilterTarget || '');
                    if (!target) {
                        return;
                    }
                    select.value = getMediaReferenceFilter(target);
                });
            }

            function formatAudioTrackDuration(seconds) {
                const duration = Math.max(0, Number(seconds) || 0);
                if (!duration) {
                    return '';
                }
                return `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, '0')}`;
            }

            function formatAudioListTitleLabel(title, version) {
                return combineAudioTitleParts(
                    String(title || '').trim() || 'Untitled',
                    String(version || '').trim()
                );
            }

            function formatAudioListRowBody(mediaFile) {
                const artist = String(mediaFile?.display_artist || '').trim();
                const titleLabel = formatAudioListTitleLabel(
                    mediaFile?.display_title,
                    mediaFile?.display_version
                );
                const duration = formatAudioTrackDuration(mediaFile?.display_duration);

                let body = artist !== '' ? `${artist} - ${titleLabel}` : titleLabel;
                if (duration !== '') {
                    body += ` (${duration})`;
                }

                return body;
            }

            function formatAudioListRowLabel(mediaFile) {
                const body = formatAudioListRowBody(mediaFile);
                const releaseContext = formatAudioReleaseContextPlain(mediaFile);
                if (releaseContext !== '') {
                    return `${body} ${releaseContext}`;
                }

                return body;
            }

            function buildAudioListRowLabelHtml(mediaFile) {
                return bandpromoAdminEscapeHtml(formatAudioListRowBody(mediaFile));
            }

            function formatAudioReleaseContextMarkup(file) {
                if (file?.release_orphan === true) {
                    const releaseTitle = String(file?.release_title || '').trim();
                    const orphanBadge = '<span class="badge audit-status-badge status-warning media-file-badge" title="Registered in catalog but not on any release track list yet">Orphan</span>';
                    if (releaseTitle !== '' && file?.on_release === true) {
                        return `${orphanBadge}<span class="media-file-release-context"><span class="media-file-release-name">on ${bandpromoAdminEscapeHtml(releaseTitle)}</span></span>`;
                    }
                    return orphanBadge;
                }

                const releaseDate = String(file?.release_date || '').trim();
                const releaseTitle = String(file?.release_title || '').trim();
                if (releaseDate === '' && releaseTitle === '') {
                    return '';
                }

                if (releaseDate !== '' && releaseTitle !== '') {
                    return `<span class="media-file-release-context" title="Release"><strong class="media-file-release-date">${bandpromoAdminEscapeHtml(releaseDate)}</strong> on ${bandpromoAdminEscapeHtml(releaseTitle)}</span>`;
                }

                if (releaseTitle !== '') {
                    return `<span class="media-file-release-context">${bandpromoAdminEscapeHtml(releaseTitle)}</span>`;
                }

                return `<strong class="media-file-release-date" title="Release date">${bandpromoAdminEscapeHtml(releaseDate)}</strong>`;
            }

            function formatAudioReleaseContextPlain(file) {
                if (file?.release_orphan === true) {
                    const releaseTitle = String(file?.release_title || '').trim();
                    if (releaseTitle !== '' && file?.on_release === true) {
                        return `Orphan on ${releaseTitle}`;
                    }
                    return 'Orphan';
                }

                const releaseDate = String(file?.release_date || '').trim();
                const releaseTitle = String(file?.release_title || '').trim();
                if (releaseDate !== '' && releaseTitle !== '') {
                    return `${releaseDate} on ${releaseTitle}`;
                }
                if (releaseTitle !== '') {
                    return releaseTitle;
                }
                return releaseDate;
            }

            function audioFileForDisplay(file) {
                if (!file || typeof file !== 'object') {
                    return file;
                }
                const filename = String(file.name || '').trim();
                if (!filename) {
                    return file;
                }

                const cached = audioInlineDetailCache.get(filename);
                if (!cached) {
                    return file;
                }

                const merged = { ...file };
                if (!String(merged.display_artist || '').trim() && String(cached.artist || '').trim()) {
                    merged.display_artist = String(cached.artist).trim();
                }
                if (!Number(merged.display_duration) && Number(cached.duration_seconds) > 0) {
                    merged.display_duration = Number(cached.duration_seconds);
                }
                if (String(cached.title || '').trim()) {
                    const parts = splitAudioTitleParts(String(cached.title || '').trim());
                    const baseTitle = String(parts.title || '').trim();
                    const version = String(parts.version || '').trim();
                    if (baseTitle !== '') {
                        merged.display_title = baseTitle;
                    }
                    if (version !== '') {
                        merged.display_version = version;
                    }
                }

                return merged;
            }

            function getDisplayedMediaInfo(type, file) {
                const mediaFile = file || {};
                if (type === 'audio') {
                    const label = formatAudioListRowLabel(mediaFile);
                    if (audioDisplayMode === 'master' && mediaFile.audio_master && mediaFile.audio_master.exists) {
                        return {
                            name: label,
                            subtitle: '',
                            size: Number(mediaFile.audio_master.size) || Number(mediaFile.size) || 0,
                            downloadVariant: 'master',
                            downloadAvailable: true,
                        };
                    }

                    return {
                        name: label,
                        subtitle: '',
                        size: Number(mediaFile.size) || 0,
                        downloadVariant: 'original',
                        downloadAvailable: true,
                    };
                }

                return {
                    name: String(mediaFile.name || ''),
                    subtitle: '',
                    size: Number(mediaFile.size) || 0,
                    downloadVariant: 'original',
                    downloadAvailable: true,
                };
            }

            function audioRowIsEditable(file) {
                const master = file && file.audio_master ? file.audio_master : {};
                return !!(master.editable || master.needs_materialize);
            }

            function buildAudioNameCell(display, file, type) {
                const source = audioFileForDisplay(file);
                const labelHtml = buildAudioListRowLabelHtml(source);
                return `<span class="media-file-name-wrap"><span class="media-file-name">${labelHtml}</span><span class="media-file-meta">${formatAudioMasterBadges(source)}</span></span>`;
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

                return items.map((item) => `<span class="media-file-inline-chip ${item.tone}"><span class="media-file-inline-label">${bandpromoAdminEscapeHtml(item.label)}</span>${bandpromoAdminEscapeHtml(item.value)}</span>`).join('');
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
                { key: 'artist', label: 'Artist', health: 'artist', requirement: 'required', inputType: 'text', read: (detail) => String(detail.artist || '').trim() },
                { key: 'title', label: 'Title', health: 'title', requirement: 'required', inputType: 'text', read: (detail) => String(splitAudioTitleParts(detail.title || '').title || detail.title || '').trim() },
                { key: 'version', label: 'Version', health: '', requirement: 'optional', inputType: 'text', read: (detail) => String(splitAudioTitleParts(detail.title || '').version || '').trim() },
                { key: 'album', label: 'Release', health: 'release', requirement: 'improvable', inputType: 'text', read: (detail) => String(detail.album || '').trim() },
                { key: 'tracknumber', label: 'Track', health: 'track', requirement: 'improvable', inputType: 'text', inputMode: 'numeric', read: (detail) => String(detail.suggested_tracknumber || detail.release_tracknumber || detail.tracknumber || '').trim() },
                { key: 'date', label: 'Release date', health: '', requirement: 'optional', inputType: 'text', inputMode: 'numeric', read: (detail) => String(detail.date || '').trim() },
                { key: 'genre', label: 'Genre', health: '', requirement: 'optional', inputType: 'text', read: (detail) => String(detail.genre || '').trim() },
                { key: 'bpm', label: 'BPM', health: '', requirement: 'optional', inputType: 'text', inputMode: 'numeric', read: (detail) => String(detail.bpm || '').trim() },
                { key: 'initialkey', label: 'Key', health: '', requirement: 'optional', inputType: 'text', read: (detail) => String(detail.initialkey || '').trim() },
            ];

            function quickEditChipDisplayValue(field, rawValue) {
                if (String(rawValue || '').trim() !== '') {
                    return rawValue;
                }
                if (field.requirement === 'optional') {
                    return 'Optional';
                }
                if (field.requirement === 'improvable') {
                    return 'Recommended';
                }
                return 'Missing';
            }

            function resolveQuickEditChipTone(field, rawValue, healthFields) {
                const hasValue = String(rawValue || '').trim() !== '';
                if (hasValue) {
                    return 'media-file-inline-chip-good';
                }

                const healthState = field.health && healthFields[field.health]
                    ? String(healthFields[field.health].state || '').toLowerCase()
                    : '';
                if (healthState === 'required' || field.requirement === 'required') {
                    return 'media-file-inline-chip-danger';
                }

                return 'media-file-inline-chip-amber';
            }

            function renderAudioQuickEditChip(filename, field, detail, healthFields, isSaving) {
                const safeName = filename.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                const rawValue = field.read(detail);
                const value = quickEditChipDisplayValue(field, rawValue);
                const tone = resolveQuickEditChipTone(field, rawValue, healthFields);
                const isEditing = activeAudioQuickEdit
                    && activeAudioQuickEdit.filename === filename
                    && activeAudioQuickEdit.field === field.key;

                if (isEditing) {
                    const inputMode = field.inputMode ? ` inputmode="${bandpromoAdminEscapeHtml(field.inputMode)}"` : '';
                    return `<span class="media-file-inline-chip media-file-inline-chip-editing ${tone}" onclick="event.stopPropagation()">
                        <span class="media-file-inline-label">${bandpromoAdminEscapeHtml(field.label)}</span>
                        <input class="media-file-inline-chip-input" type="${bandpromoAdminEscapeHtml(field.inputType || 'text')}" data-quick-field="${bandpromoAdminEscapeHtml(field.key)}" value="${bandpromoAdminEscapeHtml(rawValue)}"${inputMode} ${isSaving ? 'disabled' : ''} onkeydown="handleAudioQuickEditKey(event, '${safeName}', '${field.key}')">
                        <button type="button" class="media-file-inline-chip-btn" ${isSaving ? 'disabled' : ''} onclick="event.stopPropagation(); saveAudioQuickEdit('${safeName}', '${field.key}')" title="Save ${bandpromoAdminEscapeHtml(field.label)}">✓</button>
                        <button type="button" class="media-file-inline-chip-btn" ${isSaving ? 'disabled' : ''} onclick="event.stopPropagation(); cancelAudioQuickEdit('${safeName}')" title="Cancel">×</button>
                    </span>`;
                }

                return `<button type="button" class="media-file-inline-chip media-file-inline-chip-button ${tone}" ${isSaving ? 'disabled' : ''} onclick="event.stopPropagation(); editAudioQuickEditChip('${safeName}', '${field.key}')" title="Edit ${bandpromoAdminEscapeHtml(field.label)}">
                    <span class="media-file-inline-label">${bandpromoAdminEscapeHtml(field.label)}</span>${bandpromoAdminEscapeHtml(value)}
                </button>`;
            }

            function buildAudioInlineDetailMarkup(filename) {
                if (audioInlineDetailLoading.has(filename)) {
                    return '<div class="media-file-inline-details"><span class="media-file-inline-empty">Loading track tags...</span></div>';
                }

                const error = String(audioInlineDetailErrors.get(filename) || '').trim();
                if (error) {
                    return `<div class="media-file-inline-details"><span class="media-file-inline-empty">${bandpromoAdminEscapeHtml(error)}</span></div>`;
                }

                const detail = audioInlineDetailCache.get(filename);
                if (!detail) {
                    return '<div class="media-file-inline-details"><span class="media-file-inline-empty">Loading track tags...</span></div>';
                }

                const safeName = filename.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                const isSaving = audioInlineDetailSaving.has(filename);
                const health = buildAudioMetadataHealthFromDetail(detail || {}, filename);
                const healthFields = health && health.fields ? health.fields : {};
                const chips = audioQuickEditFields
                    .map((field) => renderAudioQuickEditChip(filename, field, detail, healthFields, isSaving))
                    .join('');

                return `<div class="media-file-inline-details media-file-quick-edit" data-quick-edit-file="${bandpromoAdminEscapeHtml(filename)}" onclick="event.stopPropagation()">
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
                if (openedAudioDetailFromQuery || !pendingAudioDetailFromQuery) {
                    return;
                }
                const rows = Array.isArray(files) ? files : [];
                const match = rows.find((file) => String(file.name || '') === pendingAudioDetailFromQuery);
                if (!match) {
                    return;
                }
                openedAudioDetailFromQuery = true;
                maybeApplyMediaFocusFromQuery('audio');

                if (match.audio_master && audioRowIsEditable(match)) {
                    if (pendingAudioDetailModeFromQuery === 'full') {
                        window.openAudioMasterModal(pendingAudioDetailFromQuery);
                    } else {
                        window.toggleAudioFileDetails(pendingAudioDetailFromQuery);
                    }
                    return;
                }

                showAdminToast('This audio file is listed, but its master copy is not ready for quick-edit yet. Use the full editor or upload a FLAC/MP3 master.', 'warning');
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

                const release = String(options.release || poolReleaseFilter || 'all').trim();
                if (release && release !== 'all') {
                    params.set('release', release);
                }

                const includeHidden = options.includeHidden === true;
                if (includeHidden) {
                    params.set('include_hidden', '1');
                }

                return '/biblioteca/list-media.php?' + params.toString();
            }

            function releaseFilterOptionsHtml(includeOrphans = false) {
                const releases = Array.isArray(releasesCatalog) ? releasesCatalog : [];
                let html = '<option value="all">All releases</option>';
                if (includeOrphans) {
                    html += '<option value="orphans">Orphaned files</option>';
                }
                releases.forEach((entry) => {
                    const id = String(entry?.id || '').trim();
                    if (!id) return;
                    const title = bandpromoAdminEscapeHtml(String(entry.title || id));
                    html += `<option value="${bandpromoAdminEscapeHtml(id)}">${title}</option>`;
                });
                return html;
            }

            function populateReleaseFilterSelects() {
                document.querySelectorAll('[data-media-release-filter], [data-pool-release-filter]').forEach((select) => {
                    const includeOrphans = select.closest('#panel-audio') !== null;
                    const html = releaseFilterOptionsHtml(includeOrphans);
                    const current = String(select.value || poolReleaseFilter || 'all');
                    select.innerHTML = html;
                    select.value = current;
                    if (select.value !== current) {
                        select.value = 'all';
                    }
                });
            }

            function syncReleaseFilterUi() {
                document.querySelectorAll('[data-media-release-filter], [data-pool-release-filter]').forEach((select) => {
                    select.value = poolReleaseFilter;
                });
            }

            async function loadReleasesCatalog() {
                const resp = await fetch('/biblioteca/get-releases.php', { credentials: 'same-origin' });
                const data = await resp.json();
                if (!resp.ok || !data || data.ok !== true) {
                    throw new Error(data?.error || 'Could not load releases');
                }
                releasesCatalog = Array.isArray(data.releases) ? data.releases : [];
                populateReleaseFilterSelects();
                syncReleaseFilterUi();
            }

            async function fetchMediaFiles(type, options = {}) {
                const resp = await fetch(mediaListUrl(type, options));
                const data = await resp.json();
                if (!resp.ok || data.error) {
                    throw new Error(data.error || ('Request failed: ' + resp.status));
                }
                return data.files || [];
            }

            function setAdminPreviewItems(files, type) {
                window._adminPreviewItems = files
                    .filter((file) => isPreviewable(file.name, file, type))
                    .map((file) => {
                        if (type !== 'video') {
                            return {
                                src: buildMediaPath(type, file.name),
                                name: file.name,
                                type: 'image',
                            };
                        }

                        const previewUrl = videoPreviewUrl(file);
                        const posterUrl = videoPosterUrl(file);
                        if (previewUrl) {
                            return {
                                src: previewUrl,
                                name: file.name,
                                type: 'video',
                                poster: posterUrl,
                            };
                        }
                        if (posterUrl) {
                            return {
                                src: posterUrl,
                                name: file.name,
                                type: 'image',
                            };
                        }
                        return null;
                    })
                    .filter(Boolean);
                window._adminPreviewIdx = -1;
            }

            if (systemTabLink) {
                systemTabLink.addEventListener('click', (event) => {
                    if (!currentBuildRequired) return;

                    if (isDeliverablesViewActive()) {
                        event.preventDefault();
                        const logCard = document.getElementById('build-log-card');
                        if (logCard) {
                            if (logCard.tagName === 'DETAILS') {
                                logCard.open = true;
                            }
                            logCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                        return;
                    }

                    event.preventDefault();
                    window.location.href = buildBuildTabUrl();
                });
            }

            function setBuildRequiredNudge(required, reasons, action, tasks) {
                currentBuildRequired = required === true;
                currentBuildAction = typeof action === 'string' ? action : 'none';
                currentBuildReasons = Array.isArray(reasons) ? reasons : [];
                currentBuildTasks = Array.isArray(tasks) ? tasks : [];

                refreshBuildActionCopy();

                if (!recommendedBuildBtn) return;
                if (!currentBuildRequired) {
                    recommendedBuildBtn.style.display = 'none';
                    recommendedBuildBtn.textContent = '';
                    return;
                }

                recommendedBuildBtn.style.display = '';
                recommendedBuildBtn.textContent = `⚡ Recommended: ${getBuildActionLabel()}`;
            }

            let renderPackageUpdateStatus = null;

            function resolveNotificationScope(options = {}) {
                if (options.full === true || options.scope === 'full') {
                    return 'full';
                }
                if (options.scope === 'lite') {
                    return 'lite';
                }
                if (adminPrimaryTab === 'welcome' || adminPrimaryTab === 'system') {
                    return 'full';
                }
                return 'lite';
            }

            async function refreshBuildRequiredState(options = {}) {
                const scope = resolveNotificationScope(options);
                try {
                    const resp = await fetch('/biblioteca/get-operator-notifications.php?scope=' + encodeURIComponent(scope));
                    const data = await resp.json();
                    if (!resp.ok || !data || data.ok !== true) return;

                    const state = data.build_required_state || {};
                    latestBuildValidation = data.metadata_validation || null;
                    latestWelcomeState = data.welcome || null;
                    latestPackageUpdate = data.package_update || null;
                    latestBackgroundTasks = data.background_tasks || null;
                    setBuildRequiredNudge(data.build_required === true, state.reasons || [], state.action || 'none', state.tasks || []);
                    renderOperatorNotifications(state, latestBuildValidation, latestWelcomeState, latestPackageUpdate, latestBackgroundTasks, data.uncatalogued_audio_failures || []);
                    updateBackgroundTaskPolling(latestBackgroundTasks);
                    renderPublishStatusSummary(data.publish_status || null, data.catalog_repair || null);

                    const videoTasks = Array.isArray(latestBackgroundTasks?.items)
                        ? latestBackgroundTasks.items.filter((item) => item && item.task === 'video-delivery')
                        : [];
                    const videoRunning = videoTasks.some((item) => String(item.status || '') === 'running');
                    if (adminFilesTabActive && activeMediaPanel === 'video') {
                        if (window._videoDeliveryWasRunning && !videoRunning) {
                            loadMediaList('video');
                        }
                    }
                    window._videoDeliveryWasRunning = videoRunning;

                    if (typeof renderPackageUpdateStatus === 'function' && data.package_update && !packageUpdateInstallInProgress) {
                        renderPackageUpdateStatus({
                            ok: true,
                            ...data.package_update,
                        });
                    }

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
                document.querySelectorAll('[data-media-audio-display-filter]').forEach((select) => {
                    select.value = audioDisplayMode === 'original' ? 'original' : 'master';
                });
            }

            function syncMediaListHeaderSelection(type) {
                const headerCheckbox = document.querySelector(`.media-file-select-all[data-target="${type}"]`);
                if (!headerCheckbox) {
                    return;
                }

                const rows = getMediaRows(type);
                const state = getMediaSelectionState(type);
                const visibleFiles = rows
                    .map((row) => String(row.dataset.file || ''))
                    .filter(Boolean);
                const selectedCount = visibleFiles.filter((filename) => state.selected.has(filename)).length;

                if (visibleFiles.length === 0) {
                    headerCheckbox.checked = false;
                    headerCheckbox.indeterminate = false;
                    headerCheckbox.disabled = true;
                    return;
                }

                headerCheckbox.disabled = false;
                if (selectedCount === 0) {
                    headerCheckbox.checked = false;
                    headerCheckbox.indeterminate = false;
                } else if (selectedCount === visibleFiles.length) {
                    headerCheckbox.checked = true;
                    headerCheckbox.indeterminate = false;
                } else {
                    headerCheckbox.checked = false;
                    headerCheckbox.indeterminate = true;
                }
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

                syncMediaListHeaderSelection(type);

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

            function selectAllVisibleMediaFiles(type) {
                const state = getMediaSelectionState(type);
                getMediaRows(type).forEach((row) => {
                    const filename = String(row.dataset.file || '');
                    if (filename) {
                        state.selected.add(filename);
                    }
                });
                syncMediaSelectionUi(type);
            }

            window.selectAllVisibleMediaFiles = selectAllVisibleMediaFiles;
            window.clearMediaSelection = clearMediaSelection;

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
                        const displaySource = type === 'audio' ? audioFileForDisplay(f) : f;
                        const display = getDisplayedMediaInfo(type, displaySource);
                        const selected = selection.selected.has(f.name);
                        const rowLabel = type === 'audio'
                            ? formatAudioListRowLabel(displaySource)
                            : String(display.name || f.name);
                        let thumb;
                        if (isImage(f.name)) {
                            thumb = `<img class="media-file-thumb" src="${url}" alt="" loading="lazy" onclick="event.stopPropagation(); openAdminPreview('${basePath}/${safeName}', '${safeName}')">`;
                        } else if (isVideo(f.name)) {
                            thumb = buildVideoThumbMarkup(type, f, safeName, basePath);
                        } else {
                            thumb = `<span class="media-file-icon">${extIcon(f.name)}</span>`;
                        }
                        const previewSrc = type === 'video'
                            ? (videoPreviewUrl(f) || videoPosterUrl(f))
                            : `${basePath}/${safeName}`;
                        const preview = isPreviewable(f.name, f, type)
                            ? `<button class="icon-btn media-action-btn media-action-amber" title="Preview" onclick="event.stopPropagation(); openAdminPreview('${previewSrc}', '${safeName}')">👁️</button>`
                            : (type === 'video' && (f.delivery_pending || f.delivery_running)
                                ? `<button class="icon-btn media-action-btn" title="Preparing in background" disabled>⏳</button>`
                                : '');
                        const rowIsEditableAudio = type === 'audio' && audioRowIsEditable(f);
                        const editAction = rowIsEditableAudio
                            ? `<button class="icon-btn media-action-btn media-action-good" title="Open full metadata editor" onclick="event.stopPropagation(); openAudioMasterModal('${safeName}')">✎</button>`
                            : '';
                        const downloadDisabled = type === 'audio' && display.downloadVariant === 'master' && (!f.audio_master || !f.audio_master.exists);
                        const downloadAction = `<button class="icon-btn media-action-btn media-action-good" title="Download this file" ${downloadDisabled ? 'disabled' : ''} onclick="event.stopPropagation(); submitMediaDownloadRequest('${type}', '${display.downloadVariant}', ['${safeName}'])">⬇</button>`;
                        const nameCell = type === 'audio'
                            ? buildAudioNameCell(display, displaySource, type)
                            : mediaReferenceFilterTypes.has(type)
                                ? `<span class="media-file-name-wrap"><span class="media-file-name">${bandpromoAdminEscapeHtml(display.name || f.name)}</span><span class="media-file-meta">${formatMediaReferenceBadges(type, f)}</span></span>`
                                : `<span class="media-file-name">${bandpromoAdminEscapeHtml(display.name || f.name)}</span>`;
                        const isExpandedAudio = type === 'audio' && expandedAudioFile === f.name;
                        const rowAttributes = rowIsEditableAudio
                            ? `data-editable-audio="true" tabindex="0" role="button" aria-expanded="${isExpandedAudio ? 'true' : 'false'}" title="${isExpandedAudio ? 'Collapse quick-edit' : 'Quick-edit track tags'}" onclick="toggleAudioFileDetails('${safeName}')" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggleAudioFileDetails('${safeName}'); }"`
                            : '';
                        const rowClassName = rowIsEditableAudio
                            ? `media-file-row media-file-row-clickable${selected ? ' media-file-row-selected' : ''}${isExpandedAudio ? ' media-file-row-expanded' : ''}`
                            : `media-file-row${selected ? ' media-file-row-selected' : ''}`;
                        const expandedMarkup = isExpandedAudio ? buildAudioInlineDetailMarkup(f.name) : '';
                        return `<div class="${rowClassName}" data-file="${bandpromoAdminEscapeHtml(f.name)}" ${rowAttributes}>
                            <div class="media-file-row-main">
                                <label class="media-file-select-wrap" title="Select for deletion" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="media-file-select" data-target="${bandpromoAdminEscapeHtml(type)}" data-file="${bandpromoAdminEscapeHtml(f.name)}" ${selected ? 'checked' : ''} aria-label="Select ${bandpromoAdminEscapeHtml(rowLabel)} for deletion">
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
                    if (type === 'video' && typeof refreshBuildRequiredState === 'function') {
                        refreshBuildRequiredState({ scope: 'lite' });
                    }
                } catch(e) {
                    listEl.innerHTML = `<span class="text-error">Network error</span>`;
                }
            }

            // Load active panel only when the Files tab is open.
            if (adminFilesTabActive) {
                loadMediaList(activeMediaPanel);
            }

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

            document.querySelectorAll('[data-media-filter-target]').forEach((select) => {
                select.addEventListener('change', () => {
                    const target = String(select.dataset.mediaFilterTarget || '');
                    setMediaReferenceFilter(target, String(select.value || 'all'));
                });
            });

            function setPoolReleaseFilter(nextValue) {
                poolReleaseFilter = String(nextValue || 'all').trim() || 'all';
                syncReleaseFilterUi();

                if (activeMediaPanel) {
                    loadMediaList(activeMediaPanel);
                }
                if (mediaPickerState) {
                    renderMediaPickerList(mediaPickerState.activeTarget);
                }
                releaseFilterListeners.forEach((listener) => listener());
            }

            document.querySelectorAll('[data-media-release-filter], [data-pool-release-filter]').forEach((select) => {
                select.addEventListener('change', () => {
                    setPoolReleaseFilter(String(select.value || 'all'));
                });
            });

            document.querySelectorAll('[data-audio-display-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    audioDisplayMode = audioDisplayMode === 'original' ? 'master' : 'original';
                    syncAudioDisplayToggleUi();
                    loadMediaList('audio');
                });
            });

            document.querySelectorAll('[data-media-audio-display-filter]').forEach((select) => {
                select.addEventListener('change', () => {
                    const nextMode = String(select.value || 'master').trim();
                    audioDisplayMode = nextMode === 'original' ? 'original' : 'master';
                    syncAudioDisplayToggleUi();
                    loadMediaList('audio');
                });
            });

            syncReleaseFilterUi();
            syncMediaReferenceFilterUi();
            syncAudioDisplayToggleUi();
            if (adminFilesTabActive || adminContentTabActive) {
                loadReleasesCatalog().catch(() => {
                    populateReleaseFilterSelects();
                });
            } else {
                populateReleaseFilterSelects();
            }

            document.querySelectorAll('.media-file-select-all').forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    const type = String(checkbox.dataset.target || '').trim();
                    if (!type) {
                        return;
                    }
                    if (checkbox.checked) {
                        selectAllVisibleMediaFiles(type);
                    } else {
                        clearMediaSelection(type);
                    }
                });
            });

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
            const mediaPickerCloseBtn = document.getElementById('mediaPickerCloseBtn');
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
                    const includeHidden = window.bandpromoDemoCatalogVisible === true;
                    const files = await fetchMediaFiles(target, { release: 'all', includeHidden });
                    setAdminPreviewItems(files, target);

                    if (!files.length) {
                        mediaPickerList.innerHTML = '<span class="text-muted">No files in this media group yet.</span>';
                        mediaPickerStatus.textContent = 'No files found. Upload one to use it here.';
                        return;
                    }

                    mediaPickerStatus.textContent = `${files.length} file${files.length !== 1 ? 's' : ''} available in ${mediaTypeLabels[target] || target}. Click a thumbnail to use it.`;
                    mediaPickerList.innerHTML = `<div class="media-picker-grid">${files.map((file) => {
                        const encodedName = encodeURIComponent(file.name);
                        const safeName = bandpromoAdminEscapeHtml(file.name);
                        const url = buildMediaUrl(target, file.name);
                        let mediaMarkup;

                        if (isImage(file.name)) {
                            mediaMarkup = `<img src="${url}" alt="" loading="lazy">`;
                        } else if (isVideo(file.name)) {
                            mediaMarkup = buildVideoPickerMarkup(file);
                        } else {
                            mediaMarkup = `<span class="media-picker-tile-icon">${extIcon(file.name)}</span>`;
                        }

                        const previewBtn = isPreviewable(file.name, file, target)
                            ? `<button type="button" class="icon-btn media-picker-preview media-picker-tile-preview" data-picker-target="${target}" data-filename="${encodedName}" title="Preview" aria-label="Preview ${safeName}">👁️</button>`
                            : '';

                        return `<button type="button" class="media-picker-tile" data-picker-target="${target}" data-filename="${encodedName}" title="${safeName}" aria-label="${safeName}">
                            <span class="media-picker-tile-media">${mediaMarkup}${previewBtn}</span>
                        </button>`;
                    }).join('')}</div>`;
                    mediaPickerStatus.style.color = '#aaa';
                } catch (error) {
                    mediaPickerList.innerHTML = `<span class="text-error">${bandpromoAdminEscapeHtml(error.message)}</span>`;
                    mediaPickerStatus.textContent = 'Failed to load files.';
                    mediaPickerStatus.style.color = '#f55';
                }
            }

            window.openMediaPicker = function(fieldId, title, targets) {
                const pickerModal = document.getElementById('mediaPickerModal');
                const input = document.getElementById(fieldId);
                if (!input || !pickerModal) return;

                if (pickerModal.parentElement !== document.body) {
                    document.body.appendChild(pickerModal);
                }

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

                if (mediaPickerTitle) {
                    mediaPickerTitle.textContent = mediaPickerState.title;
                }
                pickerModal.style.display = 'flex';
                pickerModal.classList.add('media-picker-modal--open');
                renderMediaPickerTabs();
                renderMediaPickerList(mediaPickerState.activeTarget);
            };

            window.closeMediaPickerModal = function() {
                if (typeof window.closeAdminPreview === 'function') {
                    window.closeAdminPreview();
                }
                const pickerModal = document.getElementById('mediaPickerModal');
                if (pickerModal) {
                    pickerModal.style.display = 'none';
                    pickerModal.classList.remove('media-picker-modal--open');
                }
                if (mediaPickerTabs) mediaPickerTabs.innerHTML = '';
                if (mediaPickerList) mediaPickerList.innerHTML = '<span class="text-muted">Choose a media type to browse files.</span>';
                if (mediaPickerStatus) mediaPickerStatus.textContent = '';
                mediaPickerState = null;
            };

            if (mediaPickerCloseBtn) {
                mediaPickerCloseBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    closeMediaPickerModal();
                });
            }

            if (mediaPickerModal) {
                mediaPickerModal.addEventListener('click', (event) => {
                    if (event.target === mediaPickerModal) {
                        closeMediaPickerModal();
                    }
                });
            }

            document.addEventListener('click', (event) => {
                const openBtn = event.target instanceof Element
                    ? event.target.closest('.media-picker-open')
                    : null;
                if (openBtn && openBtn.dataset.field) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.openMediaPicker(openBtn.dataset.field, openBtn.dataset.title, openBtn.dataset.targets || 'special');
                    return;
                }

                const clearBtn = event.target instanceof Element
                    ? event.target.closest('.media-picker-clear')
                    : null;
                if (clearBtn && clearBtn.dataset.field) {
                    event.preventDefault();
                    setPickerFieldValue(clearBtn.dataset.field, '');
                }
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
                    const previewTrigger = event.target.closest('.media-picker-preview');
                    if (previewTrigger) {
                        event.preventDefault();
                        event.stopPropagation();
                        const target = previewTrigger.dataset.pickerTarget;
                        const filename = decodeURIComponent(previewTrigger.dataset.filename || '');
                        openAdminPreview(buildMediaPath(target, filename), filename);
                        return;
                    }

                    const selectBtn = event.target.closest('.media-picker-select, .media-picker-tile');
                    if (selectBtn && mediaPickerState) {
                        const target = selectBtn.dataset.pickerTarget;
                        const filename = decodeURIComponent(selectBtn.dataset.filename || '');
                        setPickerFieldValue(mediaPickerState.fieldId, buildMediaPath(target, filename));
                        if (mediaPickerState.fieldId === 'audioMasterFieldCoverPath') {
                            syncAudioMasterCoverUi(activeAudioMasterDetail || {});
                        }
                        closeMediaPickerModal();
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
                await refreshBuildRequiredState({ full: true });
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

            function buildAudioMetadataHealthFromDetail(detail, filename = '') {
                const hasText = (value) => String(value || '').trim() !== '';
                const hasCover = Boolean((detail && detail.sidecar_cover) || (detail && detail.embedded_cover_present) || (detail && detail.current_cover));
                const hasTrack = hasText(detail && (detail.tracknumber || detail.suggested_tracknumber || detail.release_tracknumber));
                const totalTracks = Number(latestBuildValidation?.summary?.totalTracks || 0);

                return {
                    inspected: true,
                    source: 'audio_master_detail',
                    fields: {
                        cover: { label: 'Cover', state: hasCover ? 'good' : 'required' },
                        artist: { label: 'Artist', state: hasText(detail && detail.artist) ? 'good' : 'required' },
                        title: { label: 'Title', state: hasText(detail && detail.title) ? 'good' : 'required' },
                        release: { label: 'Release', state: hasText(detail && detail.album) ? 'good' : 'improvable' },
                        track: { label: 'Track', state: hasTrack ? 'good' : (totalTracks > 1 ? 'required' : 'improvable') },
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
                    audio_metadata_health: buildAudioMetadataHealthFromDetail(detail || {}, filename),
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
                    const tracknumber = String(detail.suggested_tracknumber || detail.release_tracknumber || '').trim();
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

                const normalizedSrc = String(src || '').trim();
                if (!normalizedSrc) {
                    showAdminToast('Video previews are being prepared in the background. Check Notifications for progress.', 'error');
                    return;
                }

                const items = window._adminPreviewItems || [];
                lightbox.setItems(items);
                const idx = items.findIndex(i => i.src === normalizedSrc || i.name === name);
                if (idx >= 0) {
                    lightbox.openAt(idx);
                } else {
                    const isVideoSrc = /\.(mp4|webm)(\?.*)?$/i.test(normalizedSrc);
                    lightbox.open(normalizedSrc, name, isVideoSrc ? 'video' : 'image');
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
                        deleteListEl.innerHTML = deleteFiles.map((name, index) => `<div class="modal-file-row">${index + 1}. ${bandpromoAdminEscapeHtml(name)}</div>`).join('');
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
                                    ? `${base}<br>${extras.map((line) => bandpromoAdminEscapeHtml(line)).join('<br>')}`
                                    : base;
                            } else {
                                const parts = formatDeleteReferenceParts(summary);
                                const labels = Array.isArray(data.references) ? data.references.slice(0, 6).map((reference) => `${bandpromoAdminEscapeHtml(reference.filename || '')}: ${bandpromoAdminEscapeHtml(reference.label || '')}`) : [];
                                const lines = [
                                    `Deleting ${deleteFiles.length > 1 ? 'these files' : 'this file'} will also remove ${parts.join(', ')} from the saved site data.`,
                                    labels.join('<br>'),
                                ];
                                if ((data.references || []).length > 6) {
                                    lines.push('…');
                                }
                                if (extras.length) {
                                    lines.push(...extras.map((line) => bandpromoAdminEscapeHtml(line)));
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
                        deleteStatusEl.innerHTML = `<span class="text-error">❌ Network error: ${bandpromoAdminEscapeHtml(e && e.message ? e.message : 'Request failed')}</span>`;
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
                    if (i === totalChunks - 1 && onProgress) {
                        onProgress((i + 1) / totalChunks, true);
                    }
                    const resp = await fetch('/biblioteca/upload-media.php', { method: 'POST', body: fd });
                    const data = await resp.json();
                    if (!data.ok) throw new Error(data.error || 'Chunk upload failed');
                    lastResponse = data;
                    if (onProgress && i < totalChunks - 1) {
                        onProgress((i + 1) / totalChunks, false);
                    }
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
                    let autoDeliveryRan = false;
                    let backgroundVideoStarted = false;
                    const masterWarnings = [];
                    for (let fi = 0; fi < modalFiles.length; fi++) {
                        const file = modalFiles[fi];
                        modalStatus.textContent = `⏳ Uploading ${file.name} (${fi + 1}/${modalFiles.length})…`;
                        try {
                            const uploadData = await uploadFileChunked(file, modalTarget, (p, finishing) => {
                                if (finishing) {
                                    modalStatus.textContent = `⏳ ${file.name} — finishing upload…`;
                                    return;
                                }
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
                            if (Array.isArray(uploadData?.auto_tasks) && uploadData.auto_tasks.length) {
                                autoDeliveryRan = true;
                            }
                            if (Array.isArray(uploadData?.background_tasks) && uploadData.background_tasks.some((task) => task && task.status === 'running')) {
                                backgroundVideoStarted = true;
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
                            setBuildRequiredNudge(latestBuildState.required === true, latestBuildState.reasons || [], latestBuildState.action || 'none', latestBuildState.tasks || []);
                        }
                        if (done > 0) {
                            await refreshBuildRequiredState({ full: true });
                        }

                        if (latestBuildState && latestBuildState.required) {
                            const next = formatBuildNextStep(latestBuildState);
                            const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                            const deliveryNote = backgroundVideoStarted
                                ? ' Video delivery started in the background.'
                                : (autoDeliveryRan ? ' Delivery files prepared automatically.' : '');
                            showAdminToast(`Upload complete.${masterNote}${deliveryNote} ${next}`, 'success');
                        } else {
                            const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                            const deliveryNote = backgroundVideoStarted
                                ? ' Video delivery started in the background.'
                                : (autoDeliveryRan ? ' Delivery files prepared automatically.' : '');
                            showAdminToast(`Upload complete.${masterNote}${deliveryNote}`, 'success');
                        }
                        if (masterWarnings.length) {
                            modalStatus.innerHTML += `<br><span style="color:#f0b429">⚠️ ${bandpromoAdminEscapeHtml(masterWarnings.join(' | '))}</span>`;
                        }
                    } else {
                        modalStatus.innerHTML += `<br><span style="color:#f55">❌ ${failed} failed, ✅ ${done} ok</span>`;
                        if (done > 0) {
                            await loadMediaList(modalTarget);
                        }
                        if (latestBuildState) {
                            setBuildRequiredNudge(latestBuildState.required === true, latestBuildState.reasons || [], latestBuildState.action || 'none', latestBuildState.tasks || []);
                        }
                        if (done > 0) {
                            await refreshBuildRequiredState({ full: true });
                        }

                        if (done > 0) {
                            const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                            showAdminToast(`Uploaded ${done} file(s), ${failed} failed.${masterNote}`, 'warning');
                        }
                        if (masterWarnings.length) {
                            modalStatus.innerHTML += `<br><span style="color:#f0b429">⚠️ ${bandpromoAdminEscapeHtml(masterWarnings.join(' | '))}</span>`;
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
            const cfgSiteAuthorInput = document.getElementById('cfg_site_author');
            const cfgSiteUrlInput = document.getElementById('cfg_site_url');
            const cfgSiteEmailInput = document.getElementById('cfg_site_email');
            const cfgSiteEmailAutoInput = document.getElementById('cfg_site_email_auto');

            function cfgSiteEmailAutoEnabled() {
                return cfgSiteEmailAutoInput?.value !== '0';
            }

            function setCfgSiteEmailAuto(enabled) {
                if (cfgSiteEmailAutoInput) {
                    cfgSiteEmailAutoInput.value = enabled ? '1' : '0';
                }
            }

    function refreshCfgSiteSuggestedContact() {
        if (!cfgSiteEmailAutoEnabled() || typeof window.bandpromoSiteContactDerive !== 'function') {
            return;
        }
        if (!(cfgSiteEmailInput instanceof HTMLInputElement)
            || !(cfgSiteAuthorInput instanceof HTMLInputElement)
            || !(cfgSiteUrlInput instanceof HTMLInputElement)) {
            return;
        }
        cfgSiteEmailInput.value = window.bandpromoSiteContactDerive(
            cfgSiteAuthorInput.value,
            cfgSiteUrlInput.value
        );
    }

    function canonicalizeCfgSiteContactInput() {
        if (!(cfgSiteEmailInput instanceof HTMLInputElement)
            || typeof window.bandpromoSiteContactNormalize !== 'function') {
            return;
        }
        const raw = cfgSiteEmailInput.value.trim();
        if (!raw) {
            return;
        }
        const normalized = window.bandpromoSiteContactNormalize(raw);
        if (normalized) {
            cfgSiteEmailInput.value = normalized;
        }
    }

            if (cfgSiteAuthorInput) {
                cfgSiteAuthorInput.addEventListener('input', refreshCfgSiteSuggestedContact);
            }
            if (cfgSiteUrlInput) {
                cfgSiteUrlInput.addEventListener('input', refreshCfgSiteSuggestedContact);
            }
            if (cfgSiteEmailInput) {
                cfgSiteEmailInput.addEventListener('input', () => {
                    setCfgSiteEmailAuto(false);
                });
                cfgSiteEmailInput.addEventListener('blur', canonicalizeCfgSiteContactInput);
            }

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

                    const contactValue = (cfgSiteEmailInput?.value || '').trim();
                    if (contactValue !== '' && typeof window.bandpromoSiteContactIsValid === 'function'
                        && !window.bandpromoSiteContactIsValid(contactValue)) {
                        cfgBasicsStatus.textContent = '❌ ' + (window.bandpromoSiteContactInvalidMessage?.() || 'Invalid contact format.');
                        cfgBasicsStatus.style.color = '#f55';
                        return;
                    }
                    const contactStored = contactValue !== '' && typeof window.bandpromoSiteContactNormalize === 'function'
                        ? (window.bandpromoSiteContactNormalize(contactValue) || contactValue)
                        : contactValue;

                    fullConfig.site = assignConfigFields(fullConfig.site, {
                        name: (document.getElementById('cfg_site_name')?.value || '').trim(),
                        short_name: (document.getElementById('cfg_site_short_name')?.value || '').trim(),
                        description: (document.getElementById('cfg_site_description')?.value || '').trim(),
                        url: (cfgSiteUrlInput?.value || '').trim(),
                        language: (document.getElementById('cfg_site_language')?.value || 'en').trim() || 'en',
                        author: (cfgSiteAuthorInput?.value || '').trim(),
                        email: contactStored,
                        email_auto: cfgSiteEmailAutoEnabled(),
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
                            if (fullConfig.site && typeof fullConfig.site.email_auto === 'boolean') {
                                setCfgSiteEmailAuto(fullConfig.site.email_auto);
                            }
                            if (cfgSiteEmailAutoEnabled()) {
                                refreshCfgSiteSuggestedContact();
                            }
                            cfgBasicsStatus.textContent = Array.isArray(data.auto_tasks) && data.auto_tasks.includes('manifest') ? '✅ Saved and manifest updated' : '✅ Saved';
                            cfgBasicsStatus.style.color = 'var(--success, #4ade80)';
                            const reasons = (data.build_required_state && data.build_required_state.reasons) || ['site_config_changed'];
                            const action = (data.build_required_state && data.build_required_state.action) || 'full';
                            setBuildRequiredNudge(data.build_required === true, reasons, action, (data.build_required_state && data.build_required_state.tasks) || []);
                            await refreshBuildRequiredState({ full: true });
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

            async function saveDemoCatalogVisible(visible, statusEl) {
                if (statusEl) {
                    statusEl.textContent = 'Saving…';
                    statusEl.style.color = '#aaa';
                }
                try {
                    const resp = await fetch('/biblioteca/save-demo-catalog-preference.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ visible: !!visible }),
                    });
                    const data = await resp.json();
                    if (data.ok) {
                        if (statusEl) {
                            statusEl.textContent = visible ? '✅ Demo catalog shown' : '✅ Demo catalog hidden';
                            statusEl.style.color = 'var(--success, #4ade80)';
                        }
                        return true;
                    }
                    if (statusEl) {
                        statusEl.textContent = '❌ ' + (data.error || 'Save failed');
                        statusEl.style.color = '#f55';
                    }
                    return false;
                } catch (error) {
                    if (statusEl) {
                        statusEl.textContent = '❌ Network error: ' + error.message;
                        statusEl.style.color = '#f55';
                    }
                    return false;
                }
            }

            const cfgOperatorTimeSaveBtn = document.getElementById('cfgOperatorTimeSaveBtn');
            const cfgOperatorTimeStatus = document.getElementById('cfgOperatorTimeStatus');
            const cfgOperatorTimezoneInput = document.getElementById('cfg_operator_timezone');
            const operatorTimezonePreview = document.getElementById('operatorTimezonePreview');
            const detectedTimezone = (typeof Intl !== 'undefined' && Intl.DateTimeFormat)
                ? Intl.DateTimeFormat().resolvedOptions().timeZone
                : 'UTC';

            if (operatorTimezonePreview && detectedTimezone) {
                const currentTz = cfgOperatorTimezoneInput instanceof HTMLInputElement
                    ? cfgOperatorTimezoneInput.value.trim()
                    : '';
                operatorTimezonePreview.textContent = currentTz !== '' ? currentTz : detectedTimezone;
            }

            if (cfgOperatorTimeSaveBtn) {
                cfgOperatorTimeSaveBtn.addEventListener('click', async () => {
                    if (cfgOperatorTimeStatus) {
                        cfgOperatorTimeStatus.textContent = 'Saving…';
                        cfgOperatorTimeStatus.style.color = '#aaa';
                    }

                    const selected = document.querySelector('input[name="operator_time_display"]:checked');
                    const timeDisplay = selected instanceof HTMLInputElement ? selected.value : 'utc';
                    let timezone = cfgOperatorTimezoneInput instanceof HTMLInputElement
                        ? cfgOperatorTimezoneInput.value.trim()
                        : '';
                    if (timeDisplay === 'local' && (timezone === '' || timezone === 'UTC')) {
                        timezone = detectedTimezone || 'UTC';
                    }
                    if (timezone === '') {
                        timezone = 'UTC';
                    }

                    try {
                        const resp = await fetch('/biblioteca/save-operator-prefs.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                time_display: timeDisplay,
                                timezone,
                            }),
                        });
                        const data = await resp.json();
                        if (!resp.ok || !data.ok) {
                            throw new Error(data.error || 'Save failed');
                        }
                        if (cfgOperatorTimeStatus) {
                            cfgOperatorTimeStatus.textContent = '✅ Saved. Reloading…';
                            cfgOperatorTimeStatus.style.color = '#6f6';
                        }
                        window.setTimeout(() => window.location.reload(), 500);
                    } catch (error) {
                        if (cfgOperatorTimeStatus) {
                            cfgOperatorTimeStatus.textContent = '❌ ' + error.message;
                            cfgOperatorTimeStatus.style.color = '#f55';
                        }
                    }
                });
            }

            const cfgDemoCatalogVisible = document.getElementById('cfgDemoCatalogVisible');
            const cfgDemoCatalogStatus = document.getElementById('cfgDemoCatalogStatus');
            if (cfgDemoCatalogVisible) {
                cfgDemoCatalogVisible.addEventListener('change', async () => {
                    const saved = await saveDemoCatalogVisible(cfgDemoCatalogVisible.checked, cfgDemoCatalogStatus);
                    if (saved) {
                        window.setTimeout(() => window.location.reload(), 600);
                    }
                });
            }

            const demoCatalogHideBtn = document.getElementById('demoCatalogHideBtn');
            const demoCatalogHideStatus = document.getElementById('demoCatalogHideStatus');
            if (demoCatalogHideBtn) {
                demoCatalogHideBtn.addEventListener('click', async () => {
                    const saved = await saveDemoCatalogVisible(false, demoCatalogHideStatus);
                    if (saved) {
                        window.setTimeout(() => window.location.reload(), 600);
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
                            await refreshBuildRequiredState({ full: true });
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

            (function () {
                if (!adminContentTabActive) {
                    return;
                }

                const editorCard = document.getElementById('galleryEditorCard');
                const poolView = document.getElementById('galleryPoolView');
                const itemsPoolView = document.getElementById('galleryItemsPoolView');
                const poolList = document.getElementById('galleryPoolList');
                const availableEl = document.getElementById('galleryAvailableList');
                const activeEl = document.getElementById('galleryActiveList');
                const countBadge = document.getElementById('galleryActiveCount');
                const saveBtn = document.getElementById('gallerySaveBtn');
                const editorHint = document.getElementById('galleryEditorHint');
                const backBtn = document.getElementById('galleryEditorBackBtn');
                const toggleAddGalleryBtn = document.getElementById('toggleAddGalleryBtn');
                const addGalleryPanel = document.getElementById('addGalleryPanel');
                const addGalleryForm = document.getElementById('addGalleryForm');
                const cancelAddGalleryBtn = document.getElementById('cancelAddGalleryBtn');
                const galleryRegistryStatus = document.getElementById('galleryRegistryStatus');
                if (!poolList || !availableEl || !activeEl || !saveBtn) return;

                let galleries = [];
                let selectedGalleryId = String(editorCard?.dataset.initialGallery || 'bandpromo-demo');
                let isEditing = false;
                let pendingGalleryDeleteId = '';

                const galleryDeleteModal = document.getElementById('galleryDeleteModal');
                const galleryDeleteModalName = document.getElementById('galleryDeleteModalName');
                const galleryDeleteConfirmBtn = document.getElementById('galleryDeleteConfirmBtn');
                const galleryDeleteCancelBtn = document.getElementById('galleryDeleteCancelBtn');
                const gallerySettingsTitle = document.getElementById('gallerySettingsTitle');
                const gallerySettingsStatus = document.getElementById('gallerySettingsStatus');
                let gallerySettingsBaseline = { title: '' };
                let gallerySettingsSaving = false;

                function galleryEntry(galleryId) {
                    return galleries.find((entry) => entry && entry.id === galleryId) || null;
                }

                function galleryCanDelete(entry) {
                    return entry && String(entry.id || '') !== 'bandpromo-demo';
                }

                function galleryMetaLine(entry) {
                    if (!entry) return '';
                    const kind = String(entry.kind || 'system');
                    const parts = [String(entry.id || '')];
                    if (kind === 'system') parts.push('system');
                    return parts.join(' · ');
                }

                function gallerySettingsDirty() {
                    const title = gallerySettingsTitle instanceof HTMLInputElement
                        ? String(gallerySettingsTitle.value || '').trim()
                        : '';
                    return title !== gallerySettingsBaseline.title;
                }

                function syncGallerySettingsPanel(galleryId) {
                    const entry = galleryEntry(galleryId);
                    const title = String(entry?.title || galleryId || '');
                    gallerySettingsBaseline = { title };
                    if (gallerySettingsTitle instanceof HTMLInputElement) {
                        gallerySettingsTitle.value = title;
                    }
                    if (gallerySettingsStatus) {
                        gallerySettingsStatus.textContent = '';
                    }
                }

                async function saveGallerySettings({ silent = false } = {}) {
                    if (gallerySettingsSaving) {
                        return true;
                    }
                    if (!(gallerySettingsTitle instanceof HTMLInputElement)) {
                        return true;
                    }

                    const title = String(gallerySettingsTitle.value || '').trim();
                    if (!title) {
                        if (!silent && gallerySettingsStatus) {
                            gallerySettingsStatus.textContent = 'Gallery name is required.';
                        }
                        return false;
                    }

                    if (!gallerySettingsDirty()) {
                        if (!silent && gallerySettingsStatus) {
                            gallerySettingsStatus.textContent = '';
                        }
                        return true;
                    }

                    gallerySettingsSaving = true;
                    if (!silent && gallerySettingsStatus) {
                        gallerySettingsStatus.textContent = 'Saving…';
                    }

                    try {
                        const resp = await fetch('/biblioteca/manage-gallery.php?gallery=' + encodeURIComponent(selectedGalleryId), {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ title }),
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || !data.ok) {
                            throw new Error(data.error || 'Could not save gallery details');
                        }
                        galleries = Array.isArray(data.galleries) ? data.galleries : galleries;
                        gallerySettingsBaseline = { title };
                        if (!silent && gallerySettingsStatus) {
                            gallerySettingsStatus.textContent = 'Saved.';
                        }
                        renderGalleryPoolList();
                        return true;
                    } catch (error) {
                        if (!silent && gallerySettingsStatus) {
                            gallerySettingsStatus.textContent = error.message || 'Could not save gallery details';
                        }
                        return false;
                    } finally {
                        gallerySettingsSaving = false;
                    }
                }

                function closeGalleryDeleteModal() {
                    pendingGalleryDeleteId = '';
                    if (galleryDeleteModal) {
                        galleryDeleteModal.style.display = 'none';
                        galleryDeleteModal.setAttribute('aria-hidden', 'true');
                    }
                }

                function openGalleryDeleteModal(galleryId) {
                    const entry = galleryEntry(galleryId);
                    if (!entry || !galleryCanDelete(entry)) {
                        return;
                    }
                    const title = String(entry.title || galleryId);
                    if (!galleryDeleteModal) {
                        if (!window.confirm(`Delete gallery "${title}"? Its content order will be lost. This cannot be undone.`)) {
                            return;
                        }
                        deleteGallery(galleryId).catch((error) => alert(error.message || 'Could not delete gallery'));
                        return;
                    }
                    pendingGalleryDeleteId = galleryId;
                    if (galleryDeleteModalName) {
                        galleryDeleteModalName.textContent = title;
                    }
                    galleryDeleteModal.style.display = 'flex';
                    galleryDeleteModal.setAttribute('aria-hidden', 'false');
                    galleryDeleteConfirmBtn?.focus();
                }

                function syncGalleryUrl(galleryId, editing = isEditing) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'content');
                    url.searchParams.set('cntab', 'gallery');
                    url.searchParams.set('gallery', galleryId);
                    if (editing) {
                        url.searchParams.set('edit', '1');
                    } else {
                        url.searchParams.delete('edit');
                    }
                    window.history.replaceState({}, '', url.toString());
                }

                function setAddGalleryPanelOpen(open) {
                    if (!addGalleryPanel || !toggleAddGalleryBtn) return;
                    addGalleryPanel.hidden = !open;
                    toggleAddGalleryBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    toggleAddGalleryBtn.classList.toggle('active', open);
                    if (open) {
                        const titleInput = addGalleryForm?.querySelector('input[name="title"]');
                        if (titleInput instanceof HTMLInputElement) {
                            titleInput.focus();
                        }
                    } else if (galleryRegistryStatus) {
                        galleryRegistryStatus.textContent = '';
                    }
                }

                function showPoolView() {
                    isEditing = false;
                    if (poolView) poolView.hidden = false;
                    if (itemsPoolView) itemsPoolView.hidden = true;
                    if (saveBtn) saveBtn.hidden = true;
                    if (editorHint) {
                        editorHint.textContent = 'Select a gallery from the pool, then click edit to change its content order.';
                    }
                    renderGalleryPoolList();
                }

                function showEditView(galleryId) {
                    isEditing = true;
                    selectedGalleryId = galleryId;
                    if (poolView) poolView.hidden = true;
                    if (itemsPoolView) itemsPoolView.hidden = false;
                    if (saveBtn) saveBtn.hidden = false;
                    syncGalleryUrl(galleryId, true);
                    syncGallerySettingsPanel(galleryId);
                    if (editorHint) {
                        editorHint.textContent = 'Drag to reorder. Shift-click or Ctrl/Cmd-click to select multiple items. Move selections back to Available content to remove them from the gallery.';
                    }
                    renderGalleryPoolList();
                }

                function renderGalleryPoolList() {
                    if (!poolList) return;
                    if (!galleries.length) {
                        poolList.innerHTML = '<li class="player-layout-empty">No galleries available yet.</li>';
                        return;
                    }
                    poolList.innerHTML = galleries.map((entry) => {
                        const id = String(entry.id || '');
                        const selectedClass = id === selectedGalleryId ? ' playlist-editor-row-selected' : '';
                        const title = bandpromoAdminEscapeHtml(entry.title || id);
                        const deleteBtn = galleryCanDelete(entry)
                            ? `<button type="button" class="page-pool-delete-btn" data-gallery-id="${bandpromoAdminEscapeHtml(id)}" title="Delete gallery" aria-label="Delete ${title}">🗑️</button>`
                            : '';
                        return `<li class="playlist-editor-row gallery-pool-row page-pool-row${selectedClass}" data-gallery-id="${bandpromoAdminEscapeHtml(id)}" aria-selected="${id === selectedGalleryId ? 'true' : 'false'}">
                            <span class="playlist-track-info">
                                <strong>🖼️ ${title}</strong>
                                <span class="playlist-track-meta">${bandpromoAdminEscapeHtml(galleryMetaLine(entry))}</span>
                            </span>
                            <span class="page-pool-row-actions">
                                <button type="button" class="page-pool-edit-btn" data-gallery-id="${bandpromoAdminEscapeHtml(id)}" title="Edit gallery" aria-label="Edit ${title}">✏️</button>
                                ${deleteBtn}
                            </span>
                        </li>`;
                    }).join('');
                }

                async function loadGalleryRegistry() {
                    const resp = await fetch('/biblioteca/get-galleries.php', { credentials: 'same-origin' });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok || !data.ok) {
                        throw new Error(data.error || 'Could not load galleries');
                    }
                    galleries = Array.isArray(data.galleries) ? data.galleries : [];
                    renderGalleryPoolList();
                }

                async function loadGalleryPreview(options = {}) {
                    const preserveSavedState = options.preserveSavedState === true;
                    if (!selectedGalleryId) {
                        activeItems = [];
                        renderGalleryLists();
                        return;
                    }
                    try {
                        const resp = await fetch(`/biblioteca/get-gallery.php?gallery=${encodeURIComponent(selectedGalleryId)}`, {
                            credentials: 'same-origin',
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || !data.ok) {
                            throw new Error(data.error || 'Could not load gallery');
                        }
                        activeItems = Array.isArray(data.items) ? data.items : [];
                        selectedActive.clear();
                        selectionAnchorActive = '';
                        selectedAvailable.clear();
                        selectionAnchorAvailable = '';
                        renderGalleryLists();
                        if (preserveSavedState) {
                            saveUi?.markSaved();
                        } else {
                            saveUi?.setBaseline();
                        }
                    } catch (e) {
                        activeEl.innerHTML = '<li class="player-layout-empty" style="color:#f87171">Could not load gallery: ' + bandpromoAdminEscapeHtml(e.message) + '</li>';
                    }
                }

                async function requestCloseEditor() {
                    if (gallerySettingsDirty()) {
                        const saved = await saveGallerySettings();
                        if (!saved) {
                            return false;
                        }
                    }
                    if (saveBtn.classList.contains('btn-amber')) {
                        const proceed = window.confirm('You have unsaved gallery changes. Leave edit mode without saving?');
                        if (!proceed) return false;
                    }
                    showPoolView();
                    syncGalleryUrl(selectedGalleryId, false);
                    await loadGalleryPreview({ preserveSavedState: true });
                    return true;
                }

                async function openGalleryEditor(galleryId) {
                    if (!galleryId) return;
                    if (isEditing && galleryId !== selectedGalleryId) {
                        if (gallerySettingsDirty()) {
                            const saved = await saveGallerySettings();
                            if (!saved) {
                                return;
                            }
                        }
                        if (saveBtn.classList.contains('btn-amber')) {
                            const proceed = window.confirm('You have unsaved gallery changes. Switch galleries without saving?');
                            if (!proceed) return;
                        }
                    }
                    selectedGalleryId = galleryId;
                    showEditView(galleryId);
                    await loadGalleryPreview();
                    await reloadGalleryPool();
                }

                async function selectGalleryForPreview(galleryId) {
                    if (!galleryId || (galleryId === selectedGalleryId && !isEditing)) {
                        return;
                    }
                    if (isEditing) {
                        await openGalleryEditor(galleryId);
                        return;
                    }
                    if (saveBtn.classList.contains('btn-amber')) {
                        const proceed = window.confirm('You have unsaved gallery changes. Switch galleries without saving?');
                        if (!proceed) return;
                    }
                    selectedGalleryId = galleryId;
                    syncGalleryUrl(galleryId, false);
                    renderGalleryPoolList();
                    await loadGalleryPreview({ preserveSavedState: true });
                }

                async function deleteGallery(galleryId) {
                    const entry = galleryEntry(galleryId);
                    if (!entry || !galleryCanDelete(entry)) return;
                    const resp = await fetch(`/biblioteca/manage-gallery.php?gallery=${encodeURIComponent(galleryId)}`, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok || !data.ok) {
                        throw new Error(data.error || 'Could not delete gallery');
                    }
                    galleries = Array.isArray(data.galleries) ? data.galleries : [];
                    if (selectedGalleryId === galleryId) {
                        selectedGalleryId = galleries[0]?.id || 'bandpromo-demo';
                        showPoolView();
                        syncGalleryUrl(selectedGalleryId, false);
                        await loadGalleryPreview({ preserveSavedState: true });
                    } else {
                        renderGalleryPoolList();
                    }
                }

                galleryDeleteCancelBtn?.addEventListener('click', closeGalleryDeleteModal);
                galleryDeleteModal?.addEventListener('click', (event) => {
                    if (event.target === galleryDeleteModal) {
                        closeGalleryDeleteModal();
                    }
                });
                galleryDeleteConfirmBtn?.addEventListener('click', async () => {
                    const galleryId = pendingGalleryDeleteId;
                    if (!galleryId) {
                        return;
                    }
                    closeGalleryDeleteModal();
                    try {
                        galleryDeleteConfirmBtn.disabled = true;
                        await deleteGallery(galleryId);
                    } catch (error) {
                        alert(error.message || 'Could not delete gallery');
                    } finally {
                        galleryDeleteConfirmBtn.disabled = false;
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape' || !galleryDeleteModal || galleryDeleteModal.style.display !== 'flex') {
                        return;
                    }
                    closeGalleryDeleteModal();
                });

                poolList.addEventListener('click', (event) => {
                    const deleteBtn = event.target instanceof HTMLElement
                        ? event.target.closest('.page-pool-delete-btn')
                        : null;
                    if (deleteBtn) {
                        event.preventDefault();
                        event.stopPropagation();
                        const galleryId = deleteBtn.getAttribute('data-gallery-id') || '';
                        openGalleryDeleteModal(galleryId);
                        return;
                    }

                    const editBtn = event.target instanceof HTMLElement
                        ? event.target.closest('.page-pool-edit-btn')
                        : null;
                    if (editBtn) {
                        event.preventDefault();
                        event.stopPropagation();
                        const galleryId = editBtn.getAttribute('data-gallery-id') || '';
                        openGalleryEditor(galleryId);
                        return;
                    }

                    const row = event.target instanceof HTMLElement
                        ? event.target.closest('.gallery-pool-row')
                        : null;
                    if (!row || !poolList.contains(row)) return;
                    const galleryId = row.getAttribute('data-gallery-id') || '';
                    if (!galleryId) return;
                    selectGalleryForPreview(galleryId);
                });

                backBtn?.addEventListener('click', () => {
                    requestCloseEditor();
                });

                gallerySettingsTitle?.addEventListener('blur', () => {
                    saveGallerySettings();
                });
                gallerySettingsTitle?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        gallerySettingsTitle.blur();
                    }
                });

                toggleAddGalleryBtn?.addEventListener('click', () => {
                    setAddGalleryPanelOpen(addGalleryPanel?.hidden !== false);
                });

                cancelAddGalleryBtn?.addEventListener('click', () => {
                    addGalleryForm?.reset();
                    setAddGalleryPanelOpen(false);
                });

                if (addGalleryForm) {
                    addGalleryForm.addEventListener('submit', async (event) => {
                        event.preventDefault();
                        const formData = new FormData(addGalleryForm);
                        const title = String(formData.get('title') || '').trim();
                        if (!title) {
                            if (galleryRegistryStatus) {
                                galleryRegistryStatus.textContent = 'Gallery name is required.';
                                galleryRegistryStatus.style.color = '#f87171';
                            }
                            return;
                        }
                        try {
                            if (galleryRegistryStatus) {
                                galleryRegistryStatus.textContent = 'Creating gallery…';
                                galleryRegistryStatus.style.color = '';
                            }
                            const resp = await fetch('/biblioteca/manage-gallery.php', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json; charset=utf-8' },
                                body: JSON.stringify({ title }),
                            });
                            const data = await resp.json().catch(() => ({}));
                            if (!resp.ok || !data.ok) {
                                throw new Error(data.error || 'Could not create gallery');
                            }
                            galleries = Array.isArray(data.galleries) ? data.galleries : galleries;
                            const newId = data.gallery?.id || '';
                            addGalleryForm.reset();
                            setAddGalleryPanelOpen(false);
                            if (newId) {
                                await openGalleryEditor(newId);
                            }
                        } catch (error) {
                            if (galleryRegistryStatus) {
                                galleryRegistryStatus.textContent = '❌ ' + error.message;
                                galleryRegistryStatus.style.color = '#f87171';
                            }
                        }
                    });
                }

                const saveUi = window.bandpromoContentSaveUi?.create(saveBtn, {
                    saveLabel: '💾 Save gallery',
                    readFingerprint() {
                        syncFromDOM();
                        return JSON.stringify(activeItems);
                    },
                }) || null;

                function prettifyName(filename) {
                    return filename
                        .replace(/\.[^.]+$/, '')
                        .replace(/[_-]+/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                let activeItems = [];
                let allFiles = [];
                let dragSrc = null;
                let draggedRows = [];
                let dragSourceList = '';
                let dragPlaceholder = null;
                let selectedAvailable = new Set();
                let selectedActive = new Set();
                let selectionAnchorAvailable = '';
                let selectionAnchorActive = '';
                let suppressNextClick = false;

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

                function activeSrcs() {
                    return new Set(activeItems.map((item) => item.src));
                }

                function mediaTypeLabel(type) {
                    return type === 'video' ? 'Video' : 'Photo';
                }

                function renderThumbMarkup(item, small) {
                    const sizeClass = small ? ' gallery-thumb--sm' : '';
                    const isVideo = item.type === 'video';
                    const poster = resolveVideoPoster(item);
                    if (isVideo) {
                        return poster
                            ? `<img class="gallery-thumb${sizeClass}" src="${bandpromoAdminEscapeHtml(poster)}" alt="" loading="lazy" onerror="this.style.opacity=0.2">`
                            : `<span class="gallery-thumb gallery-thumb--video${sizeClass}">▶</span>`;
                    }
                    return `<img class="gallery-thumb${sizeClass}" src="${bandpromoAdminEscapeHtml(item.src)}" alt="" loading="lazy" onerror="this.style.opacity=0.2">`;
                }

                function fileFromDataset(row) {
                    const src = row.dataset.src || '';
                    return allFiles.find((file) => file.src === src) || {
                        src,
                        name: row.dataset.name || prettifyName(src),
                        type: row.dataset.type || 'image',
                        poster: row.dataset.poster || '',
                    };
                }

                function pruneAvailableSelection() {
                    const allowed = new Set(allFiles.filter((file) => !activeSrcs().has(file.src)).map((file) => file.src));
                    selectedAvailable.forEach((src) => {
                        if (!allowed.has(src)) {
                            selectedAvailable.delete(src);
                        }
                    });
                    if (selectionAnchorAvailable && !allowed.has(selectionAnchorAvailable)) {
                        selectionAnchorAvailable = '';
                    }
                }

                function pruneActiveSelection() {
                    const allowed = new Set(activeItems.map((item) => item.src));
                    selectedActive.forEach((src) => {
                        if (!allowed.has(src)) {
                            selectedActive.delete(src);
                        }
                    });
                    if (selectionAnchorActive && !allowed.has(selectionAnchorActive)) {
                        selectionAnchorActive = '';
                    }
                }

                function getAvailableRows() {
                    return Array.from(availableEl.querySelectorAll('.gallery-pool-row'));
                }

                function getActiveRows() {
                    return Array.from(activeEl.querySelectorAll('.gallery-active-row'));
                }

                function syncAvailableSelectionUi() {
                    getAvailableRows().forEach((row) => {
                        const src = row.dataset.src || '';
                        const selected = selectedAvailable.has(src);
                        row.classList.toggle('playlist-editor-row-selected', selected);
                        row.setAttribute('aria-selected', selected ? 'true' : 'false');
                    });
                }

                function syncActiveSelectionUi() {
                    getActiveRows().forEach((row) => {
                        const src = row.dataset.src || '';
                        const selected = selectedActive.has(src);
                        row.classList.toggle('playlist-editor-row-selected', selected);
                        row.setAttribute('aria-selected', selected ? 'true' : 'false');
                    });
                }

                function selectAvailableRange(targetSrc, preserveExisting) {
                    const rows = getAvailableRows();
                    if (!rows.length) return;
                    const anchorSrc = selectionAnchorAvailable && rows.some((row) => row.dataset.src === selectionAnchorAvailable)
                        ? selectionAnchorAvailable
                        : targetSrc;
                    const anchorIndex = rows.findIndex((row) => row.dataset.src === anchorSrc);
                    const targetIndex = rows.findIndex((row) => row.dataset.src === targetSrc);
                    if (anchorIndex === -1 || targetIndex === -1) return;

                    const nextSelected = preserveExisting ? new Set(selectedAvailable) : new Set();
                    const start = Math.min(anchorIndex, targetIndex);
                    const end = Math.max(anchorIndex, targetIndex);
                    rows.slice(start, end + 1).forEach((row) => {
                        const src = row.dataset.src || '';
                        if (src) nextSelected.add(src);
                    });
                    selectedAvailable = nextSelected;
                }

                function selectActiveRange(targetSrc, preserveExisting) {
                    const rows = getActiveRows();
                    if (!rows.length) return;
                    const anchorSrc = selectionAnchorActive && rows.some((row) => row.dataset.src === selectionAnchorActive)
                        ? selectionAnchorActive
                        : targetSrc;
                    const anchorIndex = rows.findIndex((row) => row.dataset.src === anchorSrc);
                    const targetIndex = rows.findIndex((row) => row.dataset.src === targetSrc);
                    if (anchorIndex === -1 || targetIndex === -1) return;

                    const nextSelected = preserveExisting ? new Set(selectedActive) : new Set();
                    const start = Math.min(anchorIndex, targetIndex);
                    const end = Math.max(anchorIndex, targetIndex);
                    rows.slice(start, end + 1).forEach((row) => {
                        const src = row.dataset.src || '';
                        if (src) nextSelected.add(src);
                    });
                    selectedActive = nextSelected;
                }

                function handleAvailableSelection(row, event) {
                    const src = row.dataset.src || '';
                    if (!src) return;
                    selectedActive.clear();
                    selectionAnchorActive = '';
                    syncActiveSelectionUi();

                    if (event.shiftKey) {
                        selectAvailableRange(src, event.ctrlKey || event.metaKey);
                    } else if (event.ctrlKey || event.metaKey) {
                        if (selectedAvailable.has(src)) {
                            selectedAvailable.delete(src);
                        } else {
                            selectedAvailable.add(src);
                        }
                    } else {
                        selectedAvailable = new Set([src]);
                    }

                    selectionAnchorAvailable = selectedAvailable.size ? src : '';
                    syncAvailableSelectionUi();
                }

                function handleActiveSelection(row, event) {
                    const src = row.dataset.src || '';
                    if (!src) return;
                    selectedAvailable.clear();
                    selectionAnchorAvailable = '';
                    syncAvailableSelectionUi();

                    if (event.shiftKey) {
                        selectActiveRange(src, event.ctrlKey || event.metaKey);
                    } else if (event.ctrlKey || event.metaKey) {
                        if (selectedActive.has(src)) {
                            selectedActive.delete(src);
                        } else {
                            selectedActive.add(src);
                        }
                    } else {
                        selectedActive = new Set([src]);
                    }

                    selectionAnchorActive = selectedActive.size ? src : '';
                    syncActiveSelectionUi();
                }

                function renderAvailable() {
                    pruneAvailableSelection();
                    const taken = activeSrcs();
                    if (!allFiles.length) {
                        availableEl.innerHTML = '<li class="player-layout-empty">No delivery-ready photos or videos in the pool yet. Upload under Files, or check Notifications for background delivery.</li>';
                        return;
                    }
                    const available = allFiles.filter((file) => !taken.has(file.src));
                    if (available.length === 0) {
                        availableEl.innerHTML = '<li class="player-layout-empty">All available content is already in the gallery. Use ✕ on the right to move items back here.</li>';
                        return;
                    }
                    availableEl.innerHTML = available.map((file) => {
                        const poster = resolveVideoPoster(file);
                        const selectedClass = selectedAvailable.has(file.src) ? ' playlist-editor-row-selected' : '';
                        return `<li class="playlist-editor-row gallery-pool-row${selectedClass}" draggable="true" data-src="${bandpromoAdminEscapeHtml(file.src)}" data-type="${bandpromoAdminEscapeHtml(file.type || 'image')}" data-poster="${bandpromoAdminEscapeHtml(poster)}" data-name="${bandpromoAdminEscapeHtml(file.name)}" aria-selected="${selectedAvailable.has(file.src) ? 'true' : 'false'}">
                            <span class="playlist-drag-handle" title="Drag into gallery order">⠿</span>
                            ${renderThumbMarkup(file, true)}
                            <span class="playlist-track-info">
                                <strong>${bandpromoAdminEscapeHtml(file.name)}</strong>
                                <span class="playlist-track-meta">${bandpromoAdminEscapeHtml(mediaTypeLabel(file.type))}</span>
                            </span>
                        </li>`;
                    }).join('');
                }

                function renderGalleryLists() {
                    if (!selectedGalleryId) {
                        activeEl.innerHTML = '<li class="player-layout-empty">No gallery selected.</li>';
                        if (countBadge) countBadge.textContent = '';
                        saveUi?.reconcile();
                        return;
                    }
                    renderActive();
                    if (isEditing) {
                        renderAvailable();
                    }
                    if (saveBtn) saveBtn.hidden = !isEditing;
                }

                function renderActive() {
                    pruneActiveSelection();
                    if (!activeItems.length) {
                        activeEl.innerHTML = isEditing
                            ? '<li class="player-layout-empty">Drag content here from Available content.</li>'
                            : '<li class="player-layout-empty">This gallery has no content yet. Click edit to add photos and videos.</li>';
                        if (countBadge) countBadge.textContent = '';
                        saveUi?.reconcile();
                        return;
                    }

                    if (!isEditing) {
                        activeEl.innerHTML = activeItems.map((item, index) => {
                            const name = item.name || prettifyName(item.src);
                            const alt = String(item.alt || '').trim();
                            const meta = [mediaTypeLabel(item.type)];
                            if (alt) meta.push(alt);
                            return `<li class="playlist-editor-row gallery-preview-row">
                                <span class="playlist-track-num">${index + 1}</span>
                                ${renderThumbMarkup(item, true)}
                                <span class="playlist-track-info">
                                    <strong>${bandpromoAdminEscapeHtml(name)}</strong>
                                    <span class="playlist-track-meta">${bandpromoAdminEscapeHtml(meta.join(' · '))}</span>
                                </span>
                            </li>`;
                        }).join('');
                        if (countBadge) countBadge.textContent = activeItems.length ? `(${activeItems.length})` : '';
                        saveUi?.reconcile();
                        return;
                    }

                    activeEl.innerHTML = activeItems.map((item, index) => {
                        const poster = resolveVideoPoster(item);
                        const selectedClass = selectedActive.has(item.src) ? ' playlist-editor-row-selected' : '';
                        return `<li class="gallery-active-row${selectedClass}" draggable="true" data-src="${bandpromoAdminEscapeHtml(item.src)}" data-type="${bandpromoAdminEscapeHtml(item.type || 'image')}" data-poster="${bandpromoAdminEscapeHtml(poster)}" aria-selected="${selectedActive.has(item.src) ? 'true' : 'false'}">
                            <span class="playlist-drag-handle" title="Drag to reorder">⠿</span>
                            <span class="playlist-track-num">${index + 1}</span>
                            ${renderThumbMarkup(item, true)}
                            <div class="gallery-active-fields">
                                <input class="gallery-field-name" type="text" value="${bandpromoAdminEscapeHtml(item.name || '')}" placeholder="Name" aria-label="Name" draggable="false">
                                <input class="gallery-field-alt" type="text" value="${bandpromoAdminEscapeHtml(item.alt || '')}" placeholder="Alt text" aria-label="Alt text" draggable="false">
                            </div>
                            <button type="button" class="gallery-remove-btn" title="Move to Available content" aria-label="Remove from gallery">✕</button>
                        </li>`;
                    }).join('');

                    if (countBadge) countBadge.textContent = `(${activeItems.length})`;
                    saveUi?.reconcile();
                }

                function syncFromDOM() {
                    const rows = activeEl.querySelectorAll('.gallery-active-row');
                    activeItems = Array.from(rows).map((row) => ({
                        src: row.dataset.src,
                        type: row.dataset.type || 'image',
                        poster: row.dataset.poster || '',
                        name: row.querySelector('.gallery-field-name')?.value.trim() || '',
                        alt: row.querySelector('.gallery-field-alt')?.value.trim() || '',
                    })).map((item) => {
                        if (item.type !== 'video' || !item.poster) {
                            delete item.poster;
                        }
                        return item;
                    });
                }

                function buildActiveItemFromFile(file) {
                    const item = {
                        src: file.src,
                        name: file.name,
                        alt: file.name,
                        type: file.type || 'image',
                    };
                    const poster = resolveVideoPoster(file);
                    if (poster) item.poster = poster;
                    return item;
                }

                function insertActiveItems(files, index) {
                    syncFromDOM();
                    const newItems = files.map((file) => buildActiveItemFromFile(file));
                    const safeIndex = Math.max(0, Math.min(index, activeItems.length));
                    activeItems.splice(safeIndex, 0, ...newItems);
                    renderActive();
                    renderAvailable();
                }

                function removeActiveBySrcs(srcs) {
                    syncFromDOM();
                    const removeSet = new Set(srcs);
                    activeItems = activeItems.filter((item) => !removeSet.has(item.src));
                    srcs.forEach((src) => selectedActive.delete(src));
                    if (selectionAnchorActive && removeSet.has(selectionAnchorActive)) {
                        selectionAnchorActive = '';
                    }
                    renderActive();
                    renderAvailable();
                }

                function removeActiveBySrc(src) {
                    removeActiveBySrcs([src]);
                }

                function draggedSrcSet() {
                    return new Set(draggedRows.map((row) => row.dataset.src || '').filter(Boolean));
                }

                function activeInsertIndexFromPlaceholder() {
                    if (!dragPlaceholder?.parentNode) {
                        return activeItems.length;
                    }
                    const children = Array.from(dragPlaceholder.parentNode.children);
                    const placeholderIndex = children.indexOf(dragPlaceholder);
                    const moving = draggedSrcSet();
                    let index = 0;
                    for (let i = 0; i < placeholderIndex; i += 1) {
                        const child = children[i];
                        if (!child.classList.contains('gallery-active-row')) continue;
                        const src = child.dataset.src || '';
                        if (moving.has(src)) continue;
                        index += 1;
                    }
                    return index;
                }

                function ensurePlaceholder() {
                    if (!dragPlaceholder) {
                        dragPlaceholder = document.createElement('li');
                        dragPlaceholder.className = 'playlist-editor-placeholder';
                    }
                    return dragPlaceholder;
                }

                function getDraggableRows(listEl) {
                    if (!listEl) return [];
                    if (listEl === availableEl) {
                        return Array.from(listEl.querySelectorAll('.gallery-pool-row[draggable="true"]'));
                    }
                    return Array.from(listEl.querySelectorAll('.gallery-active-row[draggable="true"]'));
                }

                function listNameForElement(listEl) {
                    if (listEl === activeEl) return 'active';
                    if (listEl === availableEl) return 'available';
                    return '';
                }

                function updatePlaceholderHeight() {
                    if (!draggedRows.length) return;
                    const placeholder = ensurePlaceholder();
                    const totalHeight = draggedRows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0) + Math.max(0, draggedRows.length - 1) * 6;
                    placeholder.style.height = `${Math.max(52, Math.round(totalHeight))}px`;
                }

                function movePlaceholder(listEl, clientY) {
                    if (!draggedRows.length || !listEl) return;
                    const placeholder = ensurePlaceholder();
                    updatePlaceholderHeight();
                    const rows = getDraggableRows(listEl).filter((row) => !draggedRows.includes(row));
                    const referenceRow = rows.find((row) => {
                        const rect = row.getBoundingClientRect();
                        return clientY < rect.top + rect.height / 2;
                    });
                    if (referenceRow) {
                        listEl.insertBefore(placeholder, referenceRow);
                    } else {
                        listEl.appendChild(placeholder);
                    }
                }

                function finalizeWithinListDrag(listEl) {
                    const placeholder = ensurePlaceholder();
                    if (placeholder.parentNode === listEl && draggedRows.length) {
                        draggedRows.forEach((row) => {
                            listEl.insertBefore(row, placeholder);
                        });
                        placeholder.remove();
                    }
                }

                function finalizeDrag() {
                    if (!draggedRows.length || !dragPlaceholder?.parentNode) {
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragSrc = null;
                        draggedRows = [];
                        dragSourceList = '';
                        dragPlaceholder?.remove();
                        return;
                    }

                    const targetListName = listNameForElement(dragPlaceholder.parentNode);
                    const sourceListName = dragSourceList;
                    const movingSrcs = draggedSrcSet();
                    const insertIndex = (sourceListName === 'available' && targetListName === 'active')
                        ? activeInsertIndexFromPlaceholder()
                        : 0;

                    if (sourceListName === targetListName) {
                        if (targetListName === 'active') {
                            finalizeWithinListDrag(activeEl);
                            draggedRows.forEach((row) => row.classList.remove('dragging'));
                            syncFromDOM();
                            renderActive();
                        }
                    } else if (sourceListName === 'available' && targetListName === 'active') {
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragPlaceholder.remove();
                        const files = draggedRows
                            .map((row) => fileFromDataset(row))
                            .filter((file) => file.src && !activeSrcs().has(file.src));
                        insertActiveItems(files, insertIndex);
                    } else if (sourceListName === 'active' && targetListName === 'available') {
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragPlaceholder.remove();
                        removeActiveBySrcs(Array.from(movingSrcs));
                    } else {
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragPlaceholder.remove();
                        renderActive();
                        renderAvailable();
                    }

                    dragSrc = null;
                    draggedRows = [];
                    dragSourceList = '';
                }

                function collectDraggedRows(listEl, row) {
                    const listName = listNameForElement(listEl);
                    if (listName === 'available') {
                        return getAvailableRows().filter((candidate) => selectedAvailable.has(candidate.dataset.src || ''));
                    }
                    if (listName === 'active') {
                        return getActiveRows().filter((candidate) => selectedActive.has(candidate.dataset.src || ''));
                    }
                    return [];
                }

                function bindDragList(listEl) {
                    listEl.addEventListener('dragstart', (event) => {
                        if (!isEditing) {
                            event.preventDefault();
                            return;
                        }
                        const row = event.target.closest('.gallery-pool-row[draggable="true"], .gallery-active-row[draggable="true"]');
                        if (!row || !listEl.contains(row)) return;
                        dragSrc = row;
                        dragSourceList = listNameForElement(listEl);
                        const sourceSrc = row.dataset.src || '';

                        if (dragSourceList === 'available') {
                            if (sourceSrc && !selectedAvailable.has(sourceSrc)) {
                                selectedAvailable = new Set([sourceSrc]);
                                selectionAnchorAvailable = sourceSrc;
                                syncAvailableSelectionUi();
                            }
                            draggedRows = collectDraggedRows(listEl, row);
                        } else if (dragSourceList === 'active') {
                            if (sourceSrc && !selectedActive.has(sourceSrc)) {
                                selectedActive = new Set([sourceSrc]);
                                selectionAnchorActive = sourceSrc;
                                syncActiveSelectionUi();
                            }
                            draggedRows = collectDraggedRows(listEl, row);
                        }

                        if (!draggedRows.length) {
                            draggedRows = [row];
                        }

                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', sourceSrc);
                        window.requestAnimationFrame(() => {
                            if (!dragSrc || !draggedRows.length) return;
                            updatePlaceholderHeight();
                            listEl.insertBefore(ensurePlaceholder(), draggedRows[0]);
                            draggedRows.forEach((dragRow) => dragRow.classList.add('dragging'));
                        });
                    });

                    listEl.addEventListener('dragover', (event) => {
                        if (!draggedRows.length) return;
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        movePlaceholder(listEl, event.clientY);
                    });

                    listEl.addEventListener('drop', (event) => {
                        if (!draggedRows.length) return;
                        event.preventDefault();
                        movePlaceholder(listEl, event.clientY);
                        finalizeDrag();
                    });

                    listEl.addEventListener('dragend', () => {
                        finalizeWithinListDrag(listEl);
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragPlaceholder?.remove();
                        dragSrc = null;
                        draggedRows = [];
                        dragSourceList = '';
                        syncFromDOM();
                        syncAvailableSelectionUi();
                        syncActiveSelectionUi();
                        suppressNextClick = true;
                        window.requestAnimationFrame(() => {
                            suppressNextClick = false;
                        });
                    });
                }

                availableEl.addEventListener('click', (event) => {
                    if (!isEditing || suppressNextClick) return;
                    const row = event.target.closest('.gallery-pool-row');
                    if (!row || !availableEl.contains(row)) return;
                    handleAvailableSelection(row, event);
                });

                activeEl.addEventListener('click', (event) => {
                    if (!isEditing) return;
                    const button = event.target.closest('.gallery-remove-btn');
                    if (button && activeEl.contains(button)) {
                        const row = button.closest('.gallery-active-row');
                        if (!row) return;
                        removeActiveBySrc(row.dataset.src || '');
                        return;
                    }
                    if (suppressNextClick) return;
                    if (event.target.closest('.gallery-field-name, .gallery-field-alt')) return;
                    const row = event.target.closest('.gallery-active-row');
                    if (!row || !activeEl.contains(row)) return;
                    handleActiveSelection(row, event);
                });

                bindDragList(activeEl);
                bindDragList(availableEl);

                saveBtn.addEventListener('click', async () => {
                    syncFromDOM();
                    saveUi?.markSaving();
                    try {
                        const resp = await fetch(`/biblioteca/save-gallery.php?gallery=${encodeURIComponent(selectedGalleryId)}`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(activeItems),
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            saveUi?.markSaved();
                        } else {
                            saveUi?.markFailed();
                            throw new Error(data.error || 'Unknown error');
                        }
                    } catch (e) {
                        saveUi?.markFailed();
                    }
                });

                activeEl.addEventListener('input', (event) => {
                    if (event.target.closest('.gallery-field-name, .gallery-field-alt')) {
                        saveUi?.reconcile();
                    }
                });

                async function reloadGalleryPool() {
                    if (!isEditing) return;
                    try {
                        const [photoFiles, videoFiles] = await Promise.all([
                            fetchMediaFiles('photos'),
                            fetchMediaFiles('video'),
                        ]);
                        allFiles = [
                            ...(photoFiles || []).filter((f) => f.pool_ready !== false).map((f) => ({
                                src: '/media/photo/original/' + f.name,
                                name: prettifyName(f.name),
                                type: 'image',
                            })),
                            ...(videoFiles || []).filter((f) => f.pool_ready !== false).map((f) => ({
                                src: '/media/video/original/' + f.name,
                                name: prettifyName(f.name),
                                type: 'video',
                                poster: '/media/video/poster/' + f.name.replace(/\.[^.]+$/, '.jpg'),
                            })),
                        ];
                    } catch (e) {
                        availableEl.innerHTML = '<li class="player-layout-empty" style="color:#f87171">Failed to load media files.</li>';
                        return;
                    }
                    renderAvailable();
                }

                registerReleaseFilterListener(reloadGalleryPool);

                const urlParams = new URLSearchParams(window.location.search);
                const startInEdit = urlParams.get('edit') === '1';

                loadGalleryRegistry()
                    .catch((e) => {
                        if (poolList) {
                            poolList.innerHTML = '<li class="player-layout-empty" style="color:#f87171">' + bandpromoAdminEscapeHtml(e.message) + '</li>';
                        }
                    })
                    .finally(async () => {
                        if (startInEdit) {
                            await openGalleryEditor(selectedGalleryId);
                        } else {
                            showPoolView();
                            syncGalleryUrl(selectedGalleryId, false);
                            await loadGalleryPreview({ preserveSavedState: true });
                        }
                    });
            })();

            // ── Playlist editor ───────────────────────────────────────────────
            (function () {
                if (!adminContentTabActive) {
                    return;
                }

                const editorCard  = document.getElementById('playlistEditorCard');
                const poolView    = document.getElementById('playlistPoolView');
                const tracksPoolView = document.getElementById('playlistTracksPoolView');
                const poolList    = document.getElementById('playlistPoolList');
                const availableEl = document.getElementById('playlistAvailableList');
                const activeEl    = document.getElementById('playlistActiveList');
                const countBadge  = document.getElementById('playlistActiveCount');
                const saveBtn     = document.getElementById('playlistSaveBtn');
                const editorHint  = document.getElementById('playlistEditorHint');
                const backBtn     = document.getElementById('playlistEditorBackBtn');
                const toggleAddPlaylistBtn = document.getElementById('toggleAddPlaylistBtn');
                const addPlaylistPanel = document.getElementById('addPlaylistPanel');
                const addPlaylistForm = document.getElementById('addPlaylistForm');
                const cancelAddPlaylistBtn = document.getElementById('cancelAddPlaylistBtn');
                const playlistRegistryStatus = document.getElementById('playlistRegistryStatus');
                if (!poolList || !availableEl || !activeEl || !saveBtn) return;

                let playlists = [];
                let selectedPlaylistId = String(editorCard?.dataset.initialPlaylist || '');
                let defaultPlaylistId = 'bandpromo-demo';
                let isEditing = false;
                let pendingPlaylistDeleteId = '';

                const playlistDeleteModal = document.getElementById('playlistDeleteModal');
                const playlistDeleteModalName = document.getElementById('playlistDeleteModalName');
                const playlistDeleteConfirmBtn = document.getElementById('playlistDeleteConfirmBtn');
                const playlistDeleteCancelBtn = document.getElementById('playlistDeleteCancelBtn');
                const playlistAvailableSection = document.getElementById('playlistAvailableSection');
                const playlistSettingsTitle = document.getElementById('playlistSettingsTitle');
                const playlistSettingsPublishDate = document.getElementById('playlistSettingsPublishDate');
                const playlistSettingsSlug = document.getElementById('playlistSettingsSlug');
                const playlistSettingsSlugPreview = document.getElementById('playlistSettingsSlugPreview');
                const playlistSettingsDescription = document.getElementById('playlistSettingsDescription');
                const playlistSettingsShortDescription = document.getElementById('playlistSettingsShortDescription');
                const playlistSettingsShortDescriptionCount = document.getElementById('playlistSettingsShortDescriptionCount');
                const playlistSettingsPosterAssetId = document.getElementById('playlistSettingsPosterAssetId');
                const playlistSettingsStatus = document.getElementById('playlistSettingsStatus');
                const playlistCoverPanel = document.getElementById('playlistCoverPanel');
                const playlistCoverPreview = document.getElementById('playlistCoverPreview');
                const playlistCoverPlaceholder = document.getElementById('playlistCoverPlaceholder');
                const playlistCoverPreviewShell = document.getElementById('playlistCoverPreviewShell');
                const playlistCoverClearBtn = document.getElementById('playlistCoverClearBtn');
                let playlistSettingsBaseline = {
                    title: '',
                    publish_date: '',
                    slug: '',
                    description: '',
                    short_description: '',
                    poster_asset_id: '',
                };
                let playlistSettingsSaving = false;

                function normalizePlaylistDateForInput(value) {
                    if (typeof window.bandpromoNormalizeIsoDateInput === 'function') {
                        return window.bandpromoNormalizeIsoDateInput(value);
                    }
                    const trimmed = String(value || '').trim();
                    if (!trimmed) {
                        return '';
                    }
                    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
                        return trimmed;
                    }
                    if (/^\d{4}$/.test(trimmed)) {
                        return `${trimmed}-01-01`;
                    }
                    return '';
                }

                function updatePlaylistShortDescriptionCount() {
                    if (!(playlistSettingsShortDescription instanceof HTMLTextAreaElement) || !playlistSettingsShortDescriptionCount) {
                        return;
                    }
                    playlistSettingsShortDescriptionCount.textContent = String(playlistSettingsShortDescription.value.length);
                }

                function updatePlaylistSlugPreview() {
                    const slug = playlistSettingsSlug instanceof HTMLInputElement
                        ? String(playlistSettingsSlug.value || '').trim()
                        : '';
                    if (playlistSettingsSlugPreview) {
                        playlistSettingsSlugPreview.textContent = slug || 'your-slug';
                    }
                }

                function readPlaylistSettingsFromForm() {
                    const entry = playlistEntry(selectedPlaylistId);
                    const title = playlistSettingsTitle instanceof HTMLInputElement
                        ? String(playlistSettingsTitle.value || '').trim()
                        : String(entry?.title || '').trim();
                    const publishDate = playlistSettingsPublishDate instanceof HTMLInputElement
                        ? String(playlistSettingsPublishDate.value || '').trim()
                        : normalizePlaylistDateForInput(entry?.publish_date);
                    const slug = playlistSettingsSlug instanceof HTMLInputElement
                        ? String(playlistSettingsSlug.value || '').trim()
                        : String(entry?.slug || entry?.id || '').trim();
                    const description = playlistSettingsDescription instanceof HTMLTextAreaElement
                        ? String(playlistSettingsDescription.value || '').trim()
                        : String(entry?.description || '').trim();
                    const shortDescription = playlistSettingsShortDescription instanceof HTMLTextAreaElement
                        ? String(playlistSettingsShortDescription.value || '').trim()
                        : String(entry?.short_description || '').trim();
                    const posterAssetId = playlistSettingsPosterAssetId instanceof HTMLInputElement
                        ? String(playlistSettingsPosterAssetId.value || '').trim()
                        : String(entry?.poster_asset_id || '').trim();

                    return {
                        title,
                        publish_date: publishDate,
                        slug,
                        description,
                        short_description: shortDescription,
                        poster_asset_id: posterAssetId,
                    };
                }

                function validatePlaylistPublishDate(value) {
                    const trimmed = String(value || '').trim();
                    if (trimmed === '') {
                        return 'Publish date is required.';
                    }
                    if (!/^\d{4}(?:-\d{2}-\d{2})?$/.test(trimmed)) {
                        return 'Publish date must use YYYY or YYYY-MM-DD.';
                    }
                    return '';
                }

                function validatePlaylistSlug(value) {
                    const trimmed = String(value || '').trim();
                    if (trimmed === '') {
                        return 'Slug is required.';
                    }
                    if (!/^[a-z][a-z0-9-]{0,47}$/.test(trimmed)) {
                        return 'Slug must start with a letter and use lowercase letters, numbers, and hyphens.';
                    }
                    return '';
                }

                function playlistSettingsDirty() {
                    return JSON.stringify(readPlaylistSettingsFromForm()) !== JSON.stringify(playlistSettingsBaseline);
                }

                function playlistCoverPreviewUrl(rawValue, entryRef) {
                    const raw = String(rawValue || '').trim();
                    if (!raw) {
                        return '';
                    }
                    if (entryRef) {
                        const entryUrl = String(entryRef.poster_preview_url || '').trim();
                        if (entryUrl && String(entryRef.poster_asset_id || '').trim() === raw) {
                            return entryUrl;
                        }
                    }
                    const cached = playlistEntry(selectedPlaylistId);
                    if (cached && String(cached.poster_asset_id || '').trim() === raw) {
                        const cachedUrl = String(cached.poster_preview_url || '').trim();
                        if (cachedUrl) {
                            return cachedUrl;
                        }
                    }
                    return '';
                }

                function updatePlaylistCoverPreview() {
                    const entry = playlistEntry(selectedPlaylistId);
                    const rawValue = playlistSettingsPosterAssetId instanceof HTMLInputElement
                        ? String(playlistSettingsPosterAssetId.value || '').trim()
                        : '';
                    const previewUrl = playlistCoverPreviewUrl(rawValue, entry);

                    if (playlistCoverPreview instanceof HTMLImageElement) {
                        if (previewUrl) {
                            playlistCoverPreview.src = previewUrl;
                            playlistCoverPreview.style.display = 'block';
                        } else {
                            playlistCoverPreview.removeAttribute('src');
                            playlistCoverPreview.style.display = 'none';
                        }
                    }
                    if (playlistCoverPlaceholder) {
                        playlistCoverPlaceholder.style.display = previewUrl ? 'none' : 'block';
                    }
                    if (playlistCoverPreviewShell instanceof HTMLElement) {
                        playlistCoverPreviewShell.title = rawValue || 'No cover selected';
                    }
                }

                function setPlaylistCoverValue(value) {
                    if (!(playlistSettingsPosterAssetId instanceof HTMLInputElement)) {
                        return;
                    }
                    playlistSettingsPosterAssetId.value = String(value || '').trim();
                    playlistSettingsPosterAssetId.dispatchEvent(new Event('input', { bubbles: true }));
                }

                function updatePlaylistCoverPanel() {
                    const entry = playlistEntry(selectedPlaylistId);
                    if (playlistCoverPanel) {
                        playlistCoverPanel.hidden = !entry;
                    }
                    if (entry && playlistSettingsPosterAssetId instanceof HTMLInputElement && !isEditing) {
                        playlistSettingsPosterAssetId.value = String(entry.poster_asset_id || '').trim();
                    }
                    updatePlaylistCoverPreview();
                }

                function initPlaylistCoverPicker() {
                    if (!playlistCoverPanel) {
                        return;
                    }

                    playlistCoverPanel.querySelectorAll('.media-picker-open').forEach((button) => {
                        button.addEventListener('click', (event) => {
                            event.preventDefault();
                            event.stopPropagation();
                            if (typeof window.openMediaPicker !== 'function') {
                                if (playlistSettingsStatus) {
                                    playlistSettingsStatus.textContent = 'Media picker is not available. Reload the page.';
                                }
                                return;
                            }
                            window.openMediaPicker(
                                button.dataset.field || 'playlistSettingsPosterAssetId',
                                button.dataset.title || 'Choose playlist cover',
                                button.dataset.targets || 'illustrations,photos,special'
                            );
                        });
                    });

                    playlistCoverClearBtn?.addEventListener('click', (event) => {
                        event.preventDefault();
                        setPlaylistCoverValue('');
                        savePlaylistSettings();
                    });

                    playlistSettingsPosterAssetId?.addEventListener('input', () => {
                        updatePlaylistCoverPreview();
                        savePlaylistSettings();
                    });
                }

                function syncPlaylistSettingsPanel(playlistId) {
                    const entry = playlistEntry(playlistId);
                    const title = String(entry?.title || playlistId || '');
                    const publish = normalizePlaylistDateForInput(entry?.publish_date);
                    const slug = String(entry?.slug || entry?.id || playlistId || '').trim();
                    const description = String(entry?.description || '').trim();
                    const shortDescription = String(entry?.short_description || '').trim();
                    const posterAssetId = String(entry?.poster_asset_id || '').trim();

                    if (playlistSettingsTitle instanceof HTMLInputElement) {
                        playlistSettingsTitle.value = title;
                    }
                    if (playlistSettingsPublishDate instanceof HTMLInputElement) {
                        playlistSettingsPublishDate.value = publish;
                    }
                    if (playlistSettingsSlug instanceof HTMLInputElement) {
                        playlistSettingsSlug.value = slug;
                    }
                    if (playlistSettingsDescription instanceof HTMLTextAreaElement) {
                        playlistSettingsDescription.value = description;
                    }
                    if (playlistSettingsShortDescription instanceof HTMLTextAreaElement) {
                        playlistSettingsShortDescription.value = shortDescription;
                        updatePlaylistShortDescriptionCount();
                    }
                    if (playlistSettingsPosterAssetId instanceof HTMLInputElement) {
                        playlistSettingsPosterAssetId.value = posterAssetId;
                    }

                    playlistSettingsBaseline = readPlaylistSettingsFromForm();
                    updatePlaylistSlugPreview();
                    updatePlaylistCoverPanel();
                    if (playlistSettingsStatus) {
                        playlistSettingsStatus.textContent = '';
                    }
                }

                async function savePlaylistSettings({ silent = false } = {}) {
                    if (playlistSettingsSaving) {
                        return true;
                    }
                    if (!(playlistSettingsTitle instanceof HTMLInputElement) || !(playlistSettingsPublishDate instanceof HTMLInputElement)) {
                        return true;
                    }

                    const settings = readPlaylistSettingsFromForm();
                    const { title, publish_date: publishDate, slug, description, short_description: shortDescription, poster_asset_id: posterAssetId } = settings;

                    if (!title) {
                        if (!silent && playlistSettingsStatus) {
                            playlistSettingsStatus.textContent = 'Playlist name is required.';
                        }
                        return false;
                    }

                    const dateError = validatePlaylistPublishDate(publishDate);
                    if (dateError) {
                        if (!silent && playlistSettingsStatus) {
                            playlistSettingsStatus.textContent = dateError;
                        }
                        return false;
                    }

                    const slugError = validatePlaylistSlug(slug);
                    if (slugError) {
                        if (!silent && playlistSettingsStatus) {
                            playlistSettingsStatus.textContent = slugError;
                        }
                        return false;
                    }

                    if (!playlistSettingsDirty()) {
                        if (!silent && playlistSettingsStatus) {
                            playlistSettingsStatus.textContent = '';
                        }
                        return true;
                    }

                    playlistSettingsSaving = true;
                    if (!silent && playlistSettingsStatus) {
                        playlistSettingsStatus.textContent = 'Saving…';
                    }

                    try {
                        const resp = await fetch('/biblioteca/manage-playlist.php?playlist=' + encodeURIComponent(selectedPlaylistId), {
                            method: 'PATCH',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                title,
                                publish_date: publishDate,
                                slug,
                                description,
                                short_description: shortDescription,
                                poster_asset_id: posterAssetId,
                            }),
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || !data.ok) {
                            throw new Error(data.error || 'Could not save playlist details');
                        }
                        playlists = Array.isArray(data.playlists) ? data.playlists : playlists;
                        playlistSettingsBaseline = readPlaylistSettingsFromForm();
                        updatePlaylistCoverPanel();
                        if (!silent && playlistSettingsStatus) {
                            playlistSettingsStatus.textContent = 'Saved.';
                        }
                        renderPlaylistPoolList();
                        return true;
                    } catch (error) {
                        if (!silent && playlistSettingsStatus) {
                            playlistSettingsStatus.textContent = error.message || 'Could not save playlist details';
                        }
                        return false;
                    } finally {
                        playlistSettingsSaving = false;
                    }
                }

                function closePlaylistDeleteModal() {
                    pendingPlaylistDeleteId = '';
                    if (playlistDeleteModal) {
                        playlistDeleteModal.style.display = 'none';
                        playlistDeleteModal.setAttribute('aria-hidden', 'true');
                    }
                }

                function openPlaylistDeleteModal(playlistId) {
                    const entry = playlistEntry(playlistId);
                    if (!entry || !playlistCanDelete(entry)) {
                        return;
                    }
                    const title = String(entry.title || playlistId);
                    if (!playlistDeleteModal) {
                        if (!window.confirm(`Delete playlist "${title}"? Its track order will be lost. This cannot be undone.`)) {
                            return;
                        }
                        deletePlaylist(playlistId).catch((error) => alert(error.message || 'Could not delete playlist'));
                        return;
                    }
                    pendingPlaylistDeleteId = playlistId;
                    if (playlistDeleteModalName) {
                        playlistDeleteModalName.textContent = title;
                    }
                    playlistDeleteModal.style.display = 'flex';
                    playlistDeleteModal.setAttribute('aria-hidden', 'false');
                    playlistDeleteConfirmBtn?.focus();
                }

                function playlistEntry(playlistId) {
                    return playlists.find((entry) => entry && entry.id === playlistId) || null;
                }

                function playlistCanDelete(entry) {
                    return entry && String(entry.ownership || '') === 'operator';
                }

                function playlistPoolMetaHtml(entry) {
                    if (!entry) {
                        return '';
                    }

                    const trackCount = Number(entry.track_count || 0);
                    const tracksLabel = trackCount === 1 ? '1 track' : `${trackCount} tracks`;
                    const publishDate = bandpromoAdminEscapeHtml(String(entry.publish_date || '').trim());

                    let line = bandpromoAdminEscapeHtml(tracksLabel);
                    if (publishDate) {
                        line += ` released ${publishDate}`;
                    }

                    return line;
                }

                function syncPlaylistUrl(playlistId, editing = isEditing) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'content');
                    url.searchParams.set('cntab', 'playlist');
                    url.searchParams.set('playlist', playlistId);
                    if (editing) {
                        url.searchParams.set('edit', '1');
                    } else {
                        url.searchParams.delete('edit');
                    }
                    window.history.replaceState({}, '', url.toString());
                }

                function setAddPlaylistPanelOpen(open) {
                    if (!addPlaylistPanel || !toggleAddPlaylistBtn) return;
                    addPlaylistPanel.hidden = !open;
                    toggleAddPlaylistBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    toggleAddPlaylistBtn.classList.toggle('active', open);
                    if (open) {
                        const titleInput = addPlaylistForm?.querySelector('input[name="title"]');
                        if (titleInput instanceof HTMLInputElement) {
                            titleInput.focus();
                        }
                    } else if (playlistRegistryStatus) {
                        playlistRegistryStatus.textContent = '';
                    }
                }

                function showPoolView() {
                    isEditing = false;
                    if (poolView) poolView.hidden = false;
                    if (tracksPoolView) tracksPoolView.hidden = true;
                    if (playlistAvailableSection) playlistAvailableSection.hidden = true;
                    if (saveBtn) saveBtn.hidden = true;
                    if (editorHint) {
                        editorHint.textContent = 'Select a playlist from the pool, then click edit to change its track order.';
                    }
                    updatePlaylistCoverPanel();
                    renderPlaylistPoolList();
                }

                function showEditView(playlistId) {
                    isEditing = true;
                    selectedPlaylistId = playlistId;
                    if (poolView) poolView.hidden = true;
                    if (tracksPoolView) tracksPoolView.hidden = false;
                    if (playlistAvailableSection) playlistAvailableSection.hidden = false;
                    syncPlaylistUrl(playlistId, true);
                    syncPlaylistSettingsPanel(playlistId);
                    if (editorHint) {
                        editorHint.textContent = 'Drag to reorder. Shift-click or Ctrl/Cmd-click to select multiple tracks. Move selections back to Available content to remove them from the playlist.';
                    }
                    updatePlaylistCoverPanel();
                    renderPlaylistPoolList();
                }

                function renderPlaylistPoolList() {
                    if (!poolList) return;
                    if (!playlists.length) {
                        poolList.innerHTML = '<li class="player-layout-empty">No playlists available yet.</li>';
                        return;
                    }
                    poolList.innerHTML = playlists.map((entry) => {
                        const id = String(entry.id || '');
                        const selectedClass = id === selectedPlaylistId ? ' playlist-editor-row-selected' : '';
                        const title = bandpromoAdminEscapeHtml(entry.title || id);
                        const deleteBtn = playlistCanDelete(entry)
                            ? `<button type="button" class="page-pool-delete-btn" data-playlist-id="${bandpromoAdminEscapeHtml(id)}" title="Delete playlist" aria-label="Delete ${title}">🗑️</button>`
                            : '';
                        return `<li class="playlist-editor-row playlist-pool-row page-pool-row${selectedClass}" data-playlist-id="${bandpromoAdminEscapeHtml(id)}" aria-selected="${id === selectedPlaylistId ? 'true' : 'false'}">
                            <span class="playlist-track-info">
                                <strong>🎵 ${title}</strong>
                                <span class="playlist-track-meta">${playlistPoolMetaHtml(entry)}</span>
                            </span>
                            <span class="page-pool-row-actions">
                                <button type="button" class="page-pool-edit-btn" data-playlist-id="${bandpromoAdminEscapeHtml(id)}" title="Edit playlist" aria-label="Edit ${title}">✏️</button>
                                ${deleteBtn}
                            </span>
                        </li>`;
                    }).join('');
                }

                async function loadPlaylistRegistry() {
                    const resp = await fetch('/biblioteca/get-playlists.php', { credentials: 'same-origin' });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok || !data.ok) {
                        throw new Error(data.error || 'Could not load playlists');
                    }
                    playlists = Array.isArray(data.playlists) ? data.playlists : [];
                    defaultPlaylistId = String(data.active_playlist_id || data.demo_playlist_id || playlists[0]?.id || 'bandpromo-demo');
                    if (!selectedPlaylistId || !playlists.some((entry) => String(entry.id || '') === selectedPlaylistId)) {
                        selectedPlaylistId = defaultPlaylistId;
                    }
                    renderPlaylistPoolList();
                }

                async function requestCloseEditor() {
                    if (playlistSettingsDirty()) {
                        const saved = await savePlaylistSettings();
                        if (!saved) {
                            return false;
                        }
                    }
                    if (saveBtn.classList.contains('btn-amber')) {
                        const proceed = window.confirm('You have unsaved playlist changes. Leave edit mode without saving?');
                        if (!proceed) return false;
                    }
                    showPoolView();
                    syncPlaylistUrl(selectedPlaylistId, false);
                    await loadPlaylistPreview({ preserveSavedState: true });
                    return true;
                }

                async function openPlaylistEditor(playlistId) {
                    if (!playlistId) return;
                    if (isEditing && playlistId !== selectedPlaylistId) {
                        if (playlistSettingsDirty()) {
                            const saved = await savePlaylistSettings();
                            if (!saved) {
                                return;
                            }
                        }
                        if (saveBtn.classList.contains('btn-amber')) {
                            const proceed = window.confirm('You have unsaved playlist changes. Switch playlists without saving?');
                            if (!proceed) return;
                        }
                    }
                    selectedPlaylistId = playlistId;
                    showEditView(playlistId);
                    await loadPlaylistPreview();
                }

                async function selectPlaylistForPreview(playlistId) {
                    if (!playlistId || (playlistId === selectedPlaylistId && !isEditing)) {
                        return;
                    }
                    if (isEditing) {
                        await openPlaylistEditor(playlistId);
                        return;
                    }
                    if (saveBtn.classList.contains('btn-amber')) {
                        const proceed = window.confirm('You have unsaved playlist changes. Switch playlists without saving?');
                        if (!proceed) return;
                    }
                    selectedPlaylistId = playlistId;
                    syncPlaylistUrl(playlistId, false);
                    renderPlaylistPoolList();
                    await loadPlaylistPreview({ preserveSavedState: true });
                }

                async function deletePlaylist(playlistId) {
                    const entry = playlistEntry(playlistId);
                    if (!entry || !playlistCanDelete(entry)) return;
                    const resp = await fetch(`/biblioteca/manage-playlist.php?playlist=${encodeURIComponent(playlistId)}`, {
                        method: 'DELETE',
                        credentials: 'same-origin',
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok || !data.ok) {
                        throw new Error(data.error || 'Could not delete playlist');
                    }
                    playlists = Array.isArray(data.playlists) ? data.playlists : [];
                    if (selectedPlaylistId === playlistId) {
                        selectedPlaylistId = playlists[0]?.id || defaultPlaylistId;
                        showPoolView();
                        syncPlaylistUrl(selectedPlaylistId, false);
                        await loadPlaylistPreview({ preserveSavedState: true });
                    } else {
                        renderPlaylistPoolList();
                    }
                }

                playlistDeleteCancelBtn?.addEventListener('click', closePlaylistDeleteModal);
                playlistDeleteModal?.addEventListener('click', (event) => {
                    if (event.target === playlistDeleteModal) {
                        closePlaylistDeleteModal();
                    }
                });
                playlistDeleteConfirmBtn?.addEventListener('click', async () => {
                    const playlistId = pendingPlaylistDeleteId;
                    if (!playlistId) {
                        return;
                    }
                    closePlaylistDeleteModal();
                    try {
                        playlistDeleteConfirmBtn.disabled = true;
                        await deletePlaylist(playlistId);
                    } catch (error) {
                        alert(error.message || 'Could not delete playlist');
                    } finally {
                        playlistDeleteConfirmBtn.disabled = false;
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape' || !playlistDeleteModal || playlistDeleteModal.style.display !== 'flex') {
                        return;
                    }
                    closePlaylistDeleteModal();
                });

                poolList.addEventListener('click', (event) => {
                    const deleteBtn = event.target instanceof HTMLElement
                        ? event.target.closest('.page-pool-delete-btn')
                        : null;
                    if (deleteBtn) {
                        event.preventDefault();
                        event.stopPropagation();
                        const playlistId = deleteBtn.getAttribute('data-playlist-id') || '';
                        openPlaylistDeleteModal(playlistId);
                        return;
                    }

                    const editBtn = event.target instanceof HTMLElement
                        ? event.target.closest('.page-pool-edit-btn')
                        : null;
                    if (editBtn) {
                        event.preventDefault();
                        event.stopPropagation();
                        const playlistId = editBtn.getAttribute('data-playlist-id') || '';
                        openPlaylistEditor(playlistId);
                        return;
                    }

                    const row = event.target instanceof HTMLElement
                        ? event.target.closest('.playlist-pool-row')
                        : null;
                    if (!row || !poolList.contains(row)) return;
                    const playlistId = row.getAttribute('data-playlist-id') || '';
                    if (!playlistId) return;
                    selectPlaylistForPreview(playlistId);
                });

                backBtn?.addEventListener('click', () => {
                    requestCloseEditor();
                });

                playlistSettingsTitle?.addEventListener('blur', () => {
                    savePlaylistSettings();
                });
                playlistSettingsPublishDate?.addEventListener('blur', () => {
                    savePlaylistSettings();
                });
                playlistSettingsSlug?.addEventListener('blur', () => {
                    savePlaylistSettings();
                });
                playlistSettingsDescription?.addEventListener('blur', () => {
                    savePlaylistSettings();
                });
                playlistSettingsShortDescription?.addEventListener('blur', () => {
                    savePlaylistSettings();
                });
                playlistSettingsShortDescription?.addEventListener('input', () => {
                    updatePlaylistShortDescriptionCount();
                });
                playlistSettingsSlug?.addEventListener('input', () => {
                    updatePlaylistSlugPreview();
                });
                playlistSettingsTitle?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        playlistSettingsTitle.blur();
                    }
                });
                playlistSettingsPublishDate?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        playlistSettingsPublishDate.blur();
                    }
                });

                toggleAddPlaylistBtn?.addEventListener('click', () => {
                    setAddPlaylistPanelOpen(addPlaylistPanel?.hidden !== false);
                });

                cancelAddPlaylistBtn?.addEventListener('click', () => {
                    addPlaylistForm?.reset();
                    setAddPlaylistPanelOpen(false);
                });

                if (addPlaylistForm) {
                    addPlaylistForm.addEventListener('submit', async (event) => {
                        event.preventDefault();
                        const formData = new FormData(addPlaylistForm);
                        const title = String(formData.get('title') || '').trim();
                        if (!title) {
                            if (playlistRegistryStatus) {
                                playlistRegistryStatus.textContent = 'Playlist name is required.';
                                playlistRegistryStatus.style.color = '#f87171';
                            }
                            return;
                        }
                        try {
                            if (playlistRegistryStatus) {
                                playlistRegistryStatus.textContent = 'Creating playlist…';
                                playlistRegistryStatus.style.color = '';
                            }
                            const resp = await fetch('/biblioteca/manage-playlist.php', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json; charset=utf-8' },
                                body: JSON.stringify({ title }),
                            });
                            const data = await resp.json().catch(() => ({}));
                            if (!resp.ok || !data.ok) {
                                throw new Error(data.error || 'Could not create playlist');
                            }
                            playlists = Array.isArray(data.playlists) ? data.playlists : playlists;
                            const newId = data.playlist?.id || '';
                            addPlaylistForm.reset();
                            setAddPlaylistPanelOpen(false);
                            if (newId) {
                                await openPlaylistEditor(newId);
                            }
                        } catch (error) {
                            if (playlistRegistryStatus) {
                                playlistRegistryStatus.textContent = '❌ ' + error.message;
                                playlistRegistryStatus.style.color = '#f87171';
                            }
                        }
                    });
                }

                function playlistPreviewParams(extraParams) {
                    const params = new URLSearchParams(extraParams || {});
                    if (poolReleaseFilter && poolReleaseFilter !== 'all') {
                        params.set('release', poolReleaseFilter);
                    }
                    return params;
                }

                function playlistQueryString(extraParams) {
                    const params = playlistPreviewParams(extraParams);
                    params.set('playlist', selectedPlaylistId);
                    const query = params.toString();
                    return query ? `?${query}` : '';
                }

                const saveUi = window.bandpromoContentSaveUi?.create(saveBtn, {
                    saveLabel: '💾 Save playlist',
                    readFingerprint() {
                        return JSON.stringify(activeTracks.map((track) => String(track.file || '')).filter(Boolean));
                    },
                }) || null;

                let activeTracks = [];
                let availableTracks = [];
                let dragSrc = null;
                let draggedRows = [];
                let dragSourceList = '';
                let dragPlaceholder = null;
                let selectedAvailable = new Set();
                let selectedActive = new Set();
                let selectionAnchorAvailable = '';
                let selectionAnchorActive = '';
                let suppressNextClick = false;

                function formatPlaylistDuration(seconds) {
                    const duration = Math.max(0, Number(seconds) || 0);
                    if (!duration) return '';
                    return `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, '0')}`;
                }

                function cloneTrack(track) {
                    return {
                        file: track.file,
                        title: track.title,
                        version: track.version,
                        artist: track.artist,
                        album: track.album,
                        release_title: track.release_title,
                        duration: track.duration,
                        origin: track.origin,
                        sourceTier: track.sourceTier,
                        deliveryReady: track.deliveryReady !== false,
                    };
                }

                function splitPlaylistTrackTitleParts(value) {
                    const combined = String(value || '').trim();
                    if (!combined) {
                        return { title: '', version: '' };
                    }
                    const match = combined.match(/^(.+?)\s+\[(.+)\]$/);
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

                function combinePlaylistTrackTitleParts(title, version) {
                    const normalizedTitle = String(title || '').trim();
                    const normalizedVersion = String(version || '').trim();
                    if (!normalizedVersion) {
                        return normalizedTitle;
                    }
                    return `${normalizedTitle} [${normalizedVersion}]`;
                }

                function displayPlaylistTrackTitle(track) {
                    const rawTitle = String(track?.title || track?.file || 'Untitled').trim();
                    const versionFromField = String(track?.version || '').trim();
                    const parts = splitPlaylistTrackTitleParts(rawTitle);
                    let title = String(parts.title || rawTitle || 'Untitled').trim();
                    title = title.replace(/^\d+\.\s+/, '').replace(/^\d{1,2}\s+(?=[A-Za-z])/, '');
                    const version = versionFromField || String(parts.version || '').trim();
                    return combinePlaylistTrackTitleParts(title, version) || 'Untitled';
                }

                function trackMeta(track) {
                    if (track.deliveryReady === false) {
                        return 'Preparing delivery file — wait a moment and refresh the pool';
                    }
                    const releaseTitle = String(track.release_title || '').trim();
                    if (releaseTitle) {
                        return `from the release ${releaseTitle}`;
                    }
                    return '';
                }

                function pruneAvailableSelection() {
                    const allowed = new Set(availableTracks.map((track) => String(track.file || '')));
                    selectedAvailable.forEach((file) => {
                        if (!allowed.has(file)) {
                            selectedAvailable.delete(file);
                        }
                    });
                    if (selectionAnchorAvailable && !allowed.has(selectionAnchorAvailable)) {
                        selectionAnchorAvailable = '';
                    }
                }

                function pruneActiveSelection() {
                    const allowed = new Set(activeTracks.map((track) => String(track.file || '')));
                    selectedActive.forEach((file) => {
                        if (!allowed.has(file)) {
                            selectedActive.delete(file);
                        }
                    });
                    if (selectionAnchorActive && !allowed.has(selectionAnchorActive)) {
                        selectionAnchorActive = '';
                    }
                }

                function getAvailableRows() {
                    return Array.from(availableEl.querySelectorAll('.playlist-editor-row[draggable="true"]'));
                }

                function getActiveRows() {
                    return Array.from(activeEl.querySelectorAll('.playlist-editor-row[draggable="true"]'));
                }

                function syncAvailableSelectionUi() {
                    getAvailableRows().forEach((row) => {
                        const file = String(row.dataset.file || '');
                        const selected = selectedAvailable.has(file);
                        row.classList.toggle('playlist-editor-row-selected', selected);
                        row.setAttribute('aria-selected', selected ? 'true' : 'false');
                    });
                }

                function syncActiveSelectionUi() {
                    getActiveRows().forEach((row) => {
                        const file = String(row.dataset.file || '');
                        const selected = selectedActive.has(file);
                        row.classList.toggle('playlist-editor-row-selected', selected);
                        row.setAttribute('aria-selected', selected ? 'true' : 'false');
                    });
                }

                function selectAvailableRange(targetFile, preserveExisting) {
                    const rows = getAvailableRows();
                    if (!rows.length) return;
                    const anchorFile = selectionAnchorAvailable && rows.some((row) => String(row.dataset.file || '') === selectionAnchorAvailable)
                        ? selectionAnchorAvailable
                        : targetFile;
                    const anchorIndex = rows.findIndex((row) => String(row.dataset.file || '') === anchorFile);
                    const targetIndex = rows.findIndex((row) => String(row.dataset.file || '') === targetFile);
                    if (anchorIndex === -1 || targetIndex === -1) return;

                    const nextSelected = preserveExisting ? new Set(selectedAvailable) : new Set();
                    const start = Math.min(anchorIndex, targetIndex);
                    const end = Math.max(anchorIndex, targetIndex);
                    rows.slice(start, end + 1).forEach((row) => {
                        const file = String(row.dataset.file || '');
                        if (file) nextSelected.add(file);
                    });
                    selectedAvailable = nextSelected;
                }

                function selectActiveRange(targetFile, preserveExisting) {
                    const rows = getActiveRows();
                    if (!rows.length) return;
                    const anchorFile = selectionAnchorActive && rows.some((row) => String(row.dataset.file || '') === selectionAnchorActive)
                        ? selectionAnchorActive
                        : targetFile;
                    const anchorIndex = rows.findIndex((row) => String(row.dataset.file || '') === anchorFile);
                    const targetIndex = rows.findIndex((row) => String(row.dataset.file || '') === targetFile);
                    if (anchorIndex === -1 || targetIndex === -1) return;

                    const nextSelected = preserveExisting ? new Set(selectedActive) : new Set();
                    const start = Math.min(anchorIndex, targetIndex);
                    const end = Math.max(anchorIndex, targetIndex);
                    rows.slice(start, end + 1).forEach((row) => {
                        const file = String(row.dataset.file || '');
                        if (file) nextSelected.add(file);
                    });
                    selectedActive = nextSelected;
                }

                function handleAvailableSelection(row, event) {
                    const file = String(row.dataset.file || '').trim();
                    if (!file) return;
                    selectedActive.clear();
                    selectionAnchorActive = '';
                    syncActiveSelectionUi();

                    if (event.shiftKey) {
                        selectAvailableRange(file, event.ctrlKey || event.metaKey);
                    } else if (event.ctrlKey || event.metaKey) {
                        if (selectedAvailable.has(file)) {
                            selectedAvailable.delete(file);
                        } else {
                            selectedAvailable.add(file);
                        }
                    } else {
                        selectedAvailable = new Set([file]);
                    }

                    selectionAnchorAvailable = selectedAvailable.size ? file : '';
                    syncAvailableSelectionUi();
                }

                function handleActiveSelection(row, event) {
                    const file = String(row.dataset.file || '').trim();
                    if (!file) return;
                    selectedAvailable.clear();
                    selectionAnchorAvailable = '';
                    syncAvailableSelectionUi();

                    if (event.shiftKey) {
                        selectActiveRange(file, event.ctrlKey || event.metaKey);
                    } else if (event.ctrlKey || event.metaKey) {
                        if (selectedActive.has(file)) {
                            selectedActive.delete(file);
                        } else {
                            selectedActive.add(file);
                        }
                    } else {
                        selectedActive = new Set([file]);
                    }

                    selectionAnchorActive = selectedActive.size ? file : '';
                    syncActiveSelectionUi();
                }

                function renderTrackRow(track, options) {
                    const title = bandpromoAdminEscapeHtml(displayPlaylistTrackTitle(track));
                    const meta = bandpromoAdminEscapeHtml(trackMeta(track));
                    const duration = track.deliveryReady === false ? '' : formatPlaylistDuration(track.duration);
                    const file = bandpromoAdminEscapeHtml(track.file || '');
                    const demoClass = track.origin === 'bundled-placeholder' ? ' playlist-editor-row-demo' : '';
                    const pendingClass = track.deliveryReady === false ? ' playlist-editor-row-pending' : '';
                    const selectedClass = options.selected ? ' playlist-editor-row-selected' : '';
                    const positionMarkup = options.showPosition
                        ? `<span class="playlist-track-num">${options.position}</span>`
                        : '';
                    const removeMarkup = options.showRemove
                        ? '<button type="button" class="player-layout-remove-btn" title="Move to Available tracks" aria-label="Remove from playlist">✕</button>'
                        : '';
                    const rowClass = options.activeRow ? 'playlist-editor-row player-layout-row-active' : 'playlist-editor-row';
                    const readonlyClass = !isEditing ? ' playlist-editor-row-readonly' : '';
                    const draggable = isEditing && track.deliveryReady !== false ? 'true' : 'false';
                    const dragTitle = !isEditing
                        ? 'Preview only — click edit to reorder'
                        : (track.deliveryReady === false
                            ? 'Delivery file not ready yet'
                            : (options.activeRow ? 'Drag to reorder' : 'Drag into playlist'));

                    return `<li class="${rowClass}${demoClass}${pendingClass}${selectedClass}${readonlyClass}" draggable="${draggable}" data-file="${file}" aria-selected="${options.selected ? 'true' : 'false'}">
                        ${positionMarkup}
                        <span class="playlist-drag-handle" title="${dragTitle}">⠿</span>
                        <span class="playlist-track-info">
                            <strong>${title}</strong>
                            <span class="playlist-track-meta">${meta}</span>
                        </span>
                        <span class="playlist-track-duration">${duration}</span>
                        ${removeMarkup}
                    </li>`;
                }

                function renderLists() {
                    pruneAvailableSelection();
                    pruneActiveSelection();

                    if (!selectedPlaylistId) {
                        activeEl.innerHTML = '<li class="player-layout-empty">No playlist selected.</li>';
                        if (countBadge) countBadge.textContent = '';
                        return;
                    }

                    if (!activeTracks.length) {
                        activeEl.innerHTML = isEditing
                            ? '<li class="player-layout-empty">Drag tracks here from Available content.</li>'
                            : '<li class="player-layout-empty">This playlist has no tracks yet. Click edit to add tracks.</li>';
                    } else {
                        activeEl.innerHTML = activeTracks.map((track, index) => renderTrackRow(track, {
                            showPosition: true,
                            position: index + 1,
                            showRemove: isEditing,
                            activeRow: true,
                            selected: selectedActive.has(String(track.file || '')),
                        })).join('');
                    }

                    if (!isEditing) {
                        if (countBadge) {
                            countBadge.textContent = activeTracks.length ? `(${activeTracks.length})` : '';
                        }
                        saveUi?.reconcile();
                        return;
                    }

                    if (!availableTracks.length) {
                        const emptyMessage = activeTracks.length
                            ? 'All delivery-ready tracks are already in your playlist. Use ✕ on the right to move tracks back here.'
                            : 'No delivery-ready tracks in the pool yet. Upload audio under Files, or check Notifications for background delivery.';
                        availableEl.innerHTML = `<li class="player-layout-empty">${emptyMessage}</li>`;
                    } else {
                        availableEl.innerHTML = availableTracks.map((track) => renderTrackRow(track, {
                            showPosition: false,
                            showRemove: false,
                            activeRow: false,
                            selected: selectedAvailable.has(String(track.file || '')),
                        })).join('');
                    }

                    if (countBadge) {
                        countBadge.textContent = activeTracks.length ? `(${activeTracks.length})` : '';
                    }

                    saveUi?.reconcile();
                    applyPlaylistFocus();
                }

                function applyPlaylistFocus() {
                    if (appliedPlaylistFocusFromQuery || !pendingPlaylistFocusFromQuery) {
                        return;
                    }

                    const targetRow = [...getActiveRows(), ...getAvailableRows()]
                        .find((row) => String(row.dataset.file || '') === pendingPlaylistFocusFromQuery);
                    if (!targetRow) {
                        return;
                    }

                    appliedPlaylistFocusFromQuery = true;
                    document.querySelectorAll('#playlistActiveList .playlist-editor-row-focus, #playlistAvailableList .playlist-editor-row-focus')
                        .forEach((row) => row.classList.remove('playlist-editor-row-focus'));
                    targetRow.classList.add('playlist-editor-row-focus');
                    targetRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }

                function trackLookup() {
                    const lookup = new Map();
                    [...activeTracks, ...availableTracks].forEach((track) => {
                        const file = String(track.file || '');
                        if (file) lookup.set(file, track);
                    });
                    return lookup;
                }

                function syncActiveOrderFromDOM() {
                    const files = getActiveRows().map((row) => String(row.dataset.file || '')).filter(Boolean);
                    const lookup = trackLookup();
                    activeTracks = files.map((file) => lookup.get(file)).filter(Boolean);
                }

                function syncAvailableOrderFromDOM() {
                    const files = getAvailableRows().map((row) => String(row.dataset.file || '')).filter(Boolean);
                    const lookup = trackLookup();
                    availableTracks = files.map((file) => lookup.get(file)).filter(Boolean);
                }

                function moveTracksBetweenLists(fromList, toList, files, targetIndex) {
                    const fileSet = new Set(files.filter(Boolean));
                    if (!fileSet.size) return false;

                    const source = fromList === 'active' ? activeTracks : availableTracks;
                    const moving = source.filter((track) => fileSet.has(String(track.file || '')));
                    if (!moving.length) return false;
                    if (toList === 'active' && moving.some((track) => track.deliveryReady === false)) {
                        return false;
                    }

                    if (fromList === 'active') {
                        activeTracks = activeTracks.filter((track) => !fileSet.has(String(track.file || '')));
                    } else {
                        availableTracks = availableTracks.filter((track) => !fileSet.has(String(track.file || '')));
                    }

                    const target = toList === 'active' ? activeTracks : availableTracks;
                    const safeIndex = Math.max(0, Math.min(targetIndex, target.length));
                    target.splice(safeIndex, 0, ...moving.map(cloneTrack));

                    if (fromList === 'active') {
                        files.forEach((file) => selectedActive.delete(file));
                        if (selectionAnchorActive && fileSet.has(selectionAnchorActive)) {
                            selectionAnchorActive = '';
                        }
                    } else {
                        files.forEach((file) => selectedAvailable.delete(file));
                        if (selectionAnchorAvailable && fileSet.has(selectionAnchorAvailable)) {
                            selectionAnchorAvailable = '';
                        }
                    }

                    renderLists();
                    return true;
                }

                function ensurePlaceholder() {
                    if (!dragPlaceholder) {
                        dragPlaceholder = document.createElement('li');
                        dragPlaceholder.className = 'playlist-editor-placeholder';
                    }
                    return dragPlaceholder;
                }

                function getDraggableRows(listEl) {
                    if (!listEl) return [];
                    return Array.from(listEl.querySelectorAll('.playlist-editor-row[draggable="true"]'));
                }

                function listNameForElement(listEl) {
                    if (listEl === activeEl) return 'active';
                    if (listEl === availableEl) return 'available';
                    return '';
                }

                function draggedFileSet() {
                    return new Set(draggedRows.map((row) => String(row.dataset.file || '')).filter(Boolean));
                }

                function activeInsertIndexFromPlaceholder() {
                    if (!dragPlaceholder?.parentNode) {
                        return activeTracks.length;
                    }
                    const children = Array.from(dragPlaceholder.parentNode.children);
                    const placeholderIndex = children.indexOf(dragPlaceholder);
                    const movingFiles = draggedFileSet();
                    let index = 0;
                    for (let i = 0; i < placeholderIndex; i += 1) {
                        const child = children[i];
                        if (!child.classList.contains('playlist-editor-row')) continue;
                        const file = String(child.dataset.file || '');
                        if (movingFiles.has(file)) continue;
                        index += 1;
                    }
                    return index;
                }

                function availableInsertIndexFromPlaceholder() {
                    if (!dragPlaceholder?.parentNode) {
                        return availableTracks.length;
                    }
                    const children = Array.from(dragPlaceholder.parentNode.children);
                    const placeholderIndex = children.indexOf(dragPlaceholder);
                    const movingFiles = draggedFileSet();
                    let index = 0;
                    for (let i = 0; i < placeholderIndex; i += 1) {
                        const child = children[i];
                        if (!child.classList.contains('playlist-editor-row')) continue;
                        const file = String(child.dataset.file || '');
                        if (movingFiles.has(file)) continue;
                        index += 1;
                    }
                    return index;
                }

                function updatePlaceholderHeight() {
                    if (!draggedRows.length) return;
                    const placeholder = ensurePlaceholder();
                    const totalHeight = draggedRows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0) + Math.max(0, draggedRows.length - 1) * 6;
                    placeholder.style.height = `${Math.max(52, Math.round(totalHeight))}px`;
                }

                function movePlaceholder(listEl, clientY) {
                    if (!draggedRows.length || !listEl) return;
                    const placeholder = ensurePlaceholder();
                    updatePlaceholderHeight();
                    const rows = getDraggableRows(listEl).filter((row) => !draggedRows.includes(row));
                    const referenceRow = rows.find((row) => {
                        const rect = row.getBoundingClientRect();
                        return clientY < rect.top + rect.height / 2;
                    });
                    if (referenceRow) {
                        listEl.insertBefore(placeholder, referenceRow);
                    } else {
                        listEl.appendChild(placeholder);
                    }
                }

                function finalizeWithinListDrag(listEl) {
                    const placeholder = ensurePlaceholder();
                    if (placeholder.parentNode === listEl && draggedRows.length) {
                        draggedRows.forEach((row) => {
                            listEl.insertBefore(row, placeholder);
                        });
                        placeholder.remove();
                    }
                }

                function finalizeDrag() {
                    if (!draggedRows.length || !dragPlaceholder?.parentNode) {
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragSrc = null;
                        draggedRows = [];
                        dragSourceList = '';
                        dragPlaceholder?.remove();
                        return;
                    }

                    const targetListName = listNameForElement(dragPlaceholder.parentNode);
                    const sourceListName = dragSourceList;
                    const files = draggedRows.map((row) => String(row.dataset.file || '')).filter(Boolean);
                    const insertIndex = targetListName === 'active'
                        ? activeInsertIndexFromPlaceholder()
                        : availableInsertIndexFromPlaceholder();

                    if (!files.length || !targetListName) {
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragPlaceholder?.remove();
                        dragSrc = null;
                        draggedRows = [];
                        dragSourceList = '';
                        renderLists();
                        return;
                    }

                    if (sourceListName === targetListName) {
                        if (targetListName === 'active') {
                            finalizeWithinListDrag(activeEl);
                            draggedRows.forEach((row) => row.classList.remove('dragging'));
                            syncActiveOrderFromDOM();
                            renderLists();
                        } else {
                            finalizeWithinListDrag(availableEl);
                            draggedRows.forEach((row) => row.classList.remove('dragging'));
                            syncAvailableOrderFromDOM();
                            renderLists();
                        }
                    } else {
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragPlaceholder.remove();
                        moveTracksBetweenLists(sourceListName, targetListName, files, insertIndex);
                    }

                    dragSrc = null;
                    draggedRows = [];
                    dragSourceList = '';
                }

                function collectDraggedRows(listEl) {
                    const listName = listNameForElement(listEl);
                    if (listName === 'available') {
                        return getAvailableRows().filter((row) => selectedAvailable.has(String(row.dataset.file || '')));
                    }
                    if (listName === 'active') {
                        return getActiveRows().filter((row) => selectedActive.has(String(row.dataset.file || '')));
                    }
                    return [];
                }

                function bindDragList(listEl) {
                    listEl.addEventListener('dragstart', (event) => {
                        const row = event.target.closest('.playlist-editor-row[draggable="true"]');
                        if (!row || !listEl.contains(row)) return;
                        dragSrc = row;
                        dragSourceList = listNameForElement(listEl);
                        const sourceFile = String(row.dataset.file || '').trim();

                        if (dragSourceList === 'available') {
                            if (sourceFile && !selectedAvailable.has(sourceFile)) {
                                selectedAvailable = new Set([sourceFile]);
                                selectionAnchorAvailable = sourceFile;
                                syncAvailableSelectionUi();
                            }
                            draggedRows = collectDraggedRows(listEl);
                        } else if (dragSourceList === 'active') {
                            if (sourceFile && !selectedActive.has(sourceFile)) {
                                selectedActive = new Set([sourceFile]);
                                selectionAnchorActive = sourceFile;
                                syncActiveSelectionUi();
                            }
                            draggedRows = collectDraggedRows(listEl);
                        }

                        if (!draggedRows.length) {
                            draggedRows = [row];
                        }

                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', sourceFile);
                        window.requestAnimationFrame(() => {
                            if (!dragSrc || !draggedRows.length) return;
                            updatePlaceholderHeight();
                            listEl.insertBefore(ensurePlaceholder(), draggedRows[0]);
                            draggedRows.forEach((dragRow) => dragRow.classList.add('dragging'));
                        });
                    });

                    listEl.addEventListener('dragover', (event) => {
                        if (!draggedRows.length) return;
                        event.preventDefault();
                        event.dataTransfer.dropEffect = 'move';
                        movePlaceholder(listEl, event.clientY);
                    });

                    listEl.addEventListener('drop', (event) => {
                        if (!draggedRows.length) return;
                        event.preventDefault();
                        movePlaceholder(listEl, event.clientY);
                        finalizeDrag();
                    });

                    listEl.addEventListener('dragend', () => {
                        finalizeWithinListDrag(listEl);
                        draggedRows.forEach((row) => row.classList.remove('dragging'));
                        dragPlaceholder?.remove();
                        dragSrc = null;
                        draggedRows = [];
                        dragSourceList = '';
                        syncActiveOrderFromDOM();
                        syncAvailableOrderFromDOM();
                        syncAvailableSelectionUi();
                        syncActiveSelectionUi();
                        suppressNextClick = true;
                        window.requestAnimationFrame(() => {
                            suppressNextClick = false;
                        });
                    });
                }

                availableEl.addEventListener('click', (event) => {
                    if (suppressNextClick) return;
                    const row = event.target.closest('.playlist-editor-row[draggable="true"]');
                    if (!row || !availableEl.contains(row)) return;
                    handleAvailableSelection(row, event);
                });

                activeEl.addEventListener('click', (event) => {
                    const button = event.target.closest('.player-layout-remove-btn');
                    if (button && activeEl.contains(button)) {
                        const row = button.closest('.playlist-editor-row');
                        if (!row) return;
                        const file = String(row.dataset.file || '').trim();
                        if (!file) return;
                        moveTracksBetweenLists('active', 'available', [file], availableTracks.length);
                        return;
                    }
                    if (suppressNextClick) return;
                    const row = event.target.closest('.playlist-editor-row[draggable="true"]');
                    if (!row || !activeEl.contains(row)) return;
                    handleActiveSelection(row, event);
                });

                bindDragList(activeEl);
                bindDragList(availableEl);

                function applyPreviewData(data) {
                    const hasSplit = Array.isArray(data.activeTracks) || Array.isArray(data.availableTracks);
                    if (hasSplit) {
                        activeTracks = Array.isArray(data.activeTracks) ? data.activeTracks.map(cloneTrack) : [];
                        availableTracks = Array.isArray(data.availableTracks) ? data.availableTracks.map(cloneTrack) : [];
                    } else {
                        activeTracks = Array.isArray(data.tracks) ? data.tracks.map(cloneTrack) : [];
                        availableTracks = [];
                    }
                    syncPlaylistSettingsPanel(selectedPlaylistId);
                    renderLists();
                }

                async function reloadPlaylistPool() {
                    try {
                        const query = playlistQueryString();
                        const resp = await fetch('/biblioteca/get-playlist-preview.php' + query);
                        const data = await resp.json();
                        if (!resp.ok || data.error) {
                            throw new Error(data.error || 'Could not load playlist preview');
                        }

                        const activeFiles = new Set(activeTracks.map((track) => String(track.file || '')).filter(Boolean));
                        const available = Array.isArray(data.availableTracks) ? data.availableTracks : [];
                        availableTracks = available
                            .map(cloneTrack)
                            .filter((track) => !activeFiles.has(String(track.file || '')));

                        renderLists();
                    } catch (e) {
                        availableEl.innerHTML = '<li class="player-layout-empty" style="color:#f87171">Could not refresh available tracks: ' + bandpromoAdminEscapeHtml(e.message) + '</li>';
                    }
                }

                async function loadPlaylistPreview(options = {}) {
                    const preserveSavedState = options.preserveSavedState === true;
                    try {
                        const query = playlistQueryString();
                        const resp = await fetch('/biblioteca/get-playlist-preview.php' + query);
                        const data = await resp.json();
                        if (!resp.ok || data.error) {
                            throw new Error(data.error || 'Could not load playlist preview');
                        }
                        applyPreviewData(data);
                        if (preserveSavedState) {
                            saveUi?.markSaved();
                        } else {
                            saveUi?.setBaseline();
                        }
                    } catch (e) {
                        activeEl.innerHTML = '';
                        availableEl.innerHTML = '<li class="player-layout-empty" style="color:#f87171">Could not load playlist preview: ' + bandpromoAdminEscapeHtml(e.message) + '</li>';
                    }
                }

                saveBtn.addEventListener('click', async () => {
                    syncActiveOrderFromDOM();
                    const order = activeTracks.map((track) => String(track.file || '')).filter(Boolean);
                    if (!order.length) {
                        showAdminToast('Add at least one track before saving the playlist.', 'error');
                        return;
                    }
                    saveUi?.markSaving();
                    try {
                        const resp = await fetch('/biblioteca/save-playlist-order.php' + playlistQueryString(), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(order),
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            if (typeof data.build_required === 'boolean' && typeof setBuildRequiredNudge === 'function') {
                                const state = data.build_required_state || {};
                                setBuildRequiredNudge(
                                    data.build_required === true,
                                    state.reasons || [],
                                    state.action || 'none',
                                    state.tasks || []
                                );
                            } else if (typeof refreshBuildRequiredState === 'function') {
                                refreshBuildRequiredState({ full: true });
                            }
                            await loadPlaylistPreview({ preserveSavedState: true });
                        } else {
                            saveUi?.markFailed();
                            showAdminToast(data.error || data.warning || 'Could not save playlist.', 'error');
                        }
                    } catch (e) {
                        saveUi?.markFailed();
                        showAdminToast(e.message || 'Could not save playlist.', 'error');
                    }
                });

                registerReleaseFilterListener(reloadPlaylistPool);

                initPlaylistCoverPicker();

                const urlParams = new URLSearchParams(window.location.search);
                const startInEdit = urlParams.get('edit') === '1';

                loadPlaylistRegistry()
                    .catch((e) => {
                        if (poolList) {
                            poolList.innerHTML = '<li class="player-layout-empty" style="color:#f87171">' + bandpromoAdminEscapeHtml(e.message) + '</li>';
                        }
                    })
                    .finally(async () => {
                        if (startInEdit) {
                            await openPlaylistEditor(selectedPlaylistId);
                        } else {
                            showPoolView();
                            syncPlaylistUrl(selectedPlaylistId, false);
                            await loadPlaylistPreview({ preserveSavedState: true });
                        }
                    });
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
                                await refreshBuildRequiredState({ full: true });
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
            const buildHelpBox = document.getElementById('help-build');
            const buildSpinner = document.getElementById('buildSpinner');
            const buildLog     = document.getElementById('buildLog');
            const buildStatus  = document.getElementById('buildStatus');
            const publishStatusCard = document.getElementById('publishStatusCard');
            const publishStatusSummary = document.getElementById('publishStatusSummary');
            const publishStatusOverall = document.getElementById('publishStatusOverall');
            let pollTimer      = null;
            let currentRunMode = 'full';
            let currentBuildTasks = [];

            function runRecommendedAction() {
                if (pollTimer) return;
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
                const actionsCard = document.getElementById('publishActionsCard');
                if (actionsCard) {
                    actionsCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else if (logCard) {
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
                    buildBtn.textContent = '▶️ Rebuild all deliverables';
                }
                if (buildHelpBox) {
                    if (currentBuildRequired) {
                        const tasks = formatBuildTaskList({ tasks: currentBuildTasks });
                        const actionLabel = getBuildActionLabel();
                        const taskLine = tasks.length
                            ? `Pending now: <strong>${bandpromoAdminEscapeHtml(tasks.join(' · '))}</strong>.`
                            : 'Pending now: bandPromo still has delivery work to finish.';
                        buildHelpBox.innerHTML = `${actionLabel} is the recommended next step for the current pending work. ${taskLine} Jobs continue in the background while this log updates.`;
                    } else {
                        buildHelpBox.innerHTML = 'bandPromo usually keeps deliverables current automatically after uploads and saves. Use <strong>Rebuild all deliverables</strong> when you want the full pipeline refreshed.';
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

            function renderPublishStatusSummary(status, catalogRepair) {
                if (!publishStatusSummary || !publishStatusOverall) {
                    return;
                }

                if (!status || typeof status !== 'object') {
                    publishStatusOverall.textContent = 'Unavailable';
                    publishStatusOverall.className = 'badge audit-status-badge status-neutral';
                    publishStatusSummary.innerHTML = '<p class="publish-status-empty">Delivery status is not available right now.</p>';
                    return;
                }

                const repairStatus = catalogRepair && typeof catalogRepair === 'object' ? String(catalogRepair.status || '') : '';
                const repairMessage = catalogRepair && typeof catalogRepair === 'object' ? String(catalogRepair.message || '').trim() : '';
                const ok = status.ok === true && repairStatus !== 'running' && repairStatus !== 'warning' && repairStatus !== 'error';
                publishStatusOverall.textContent = repairStatus === 'running'
                    ? 'Preparing uploads'
                    : (ok ? 'All clear' : 'Needs attention');
                publishStatusOverall.className = `badge audit-status-badge ${ok ? 'status-ok' : 'status-warning'}`;

                const inventory = status.inventory && typeof status.inventory === 'object' ? status.inventory : null;
                const delivery = inventory && inventory.delivery ? inventory.delivery : {};
                const tiles = inventory && Array.isArray(inventory.tiles) ? inventory.tiles : [];
                const headline = inventory ? String(inventory.headline || '').trim() : '';
                const subheadline = inventory ? String(inventory.subheadline || '').trim() : '';

                let inventoryHtml = '';
                if (inventory) {
                    const audioReady = Number(delivery.audio_ready || 0);
                    const audioTotal = Number(delivery.audio_total || 0);
                    const deliveryPercent = Number(delivery.percent || 0);
                    const deliveryComplete = delivery.complete === true;

                    inventoryHtml += `
                        <div class="delivery-inventory-hero ${deliveryComplete ? 'is-complete' : ''}">
                            <div class="delivery-inventory-copy">
                                <strong>${bandpromoAdminEscapeHtml(headline || 'Your site inventory')}</strong>
                                ${subheadline ? `<span>${bandpromoAdminEscapeHtml(subheadline)}</span>` : ''}
                            </div>
                            ${audioTotal > 0 ? `
                                <div class="delivery-readiness" aria-label="Streaming readiness ${deliveryPercent} percent">
                                    <div class="delivery-readiness-ring" style="--delivery-progress:${deliveryPercent}">
                                        <span>${deliveryPercent}%</span>
                                    </div>
                                    <div class="delivery-readiness-copy">
                                        <strong>${audioReady}/${audioTotal}</strong>
                                        <span>tracks stream-ready</span>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    `;

                    if (tiles.length) {
                        const coreTileIds = new Set(['releases', 'playlists', 'tracks', 'audio']);
                        const visibleTiles = tiles.filter((tile) => {
                            const value = Number(tile.value || 0);
                            return value > 0 || coreTileIds.has(String(tile.id || ''));
                        });
                        inventoryHtml += `
                            <div class="delivery-inventory-grid">
                                ${visibleTiles.map((tile) => `
                                    <article class="delivery-stat-tile">
                                        <span class="delivery-stat-icon" aria-hidden="true">${bandpromoAdminEscapeHtml(tile.icon || '•')}</span>
                                        <span class="delivery-stat-value">${Number(tile.value || 0).toLocaleString()}</span>
                                        <span class="delivery-stat-label">${bandpromoAdminEscapeHtml(tile.label || tile.id || 'Item')}</span>
                                        ${tile.detail ? `<span class="delivery-stat-detail">${bandpromoAdminEscapeHtml(tile.detail)}</span>` : ''}
                                    </article>
                                `).join('')}
                            </div>
                        `;
                    }
                }

                const checks = Array.isArray(status.checks) ? status.checks : [];
                let checksHtml = '';
                if (repairMessage !== '') {
                    checksHtml += `<p class="publish-status-note">${bandpromoAdminEscapeHtml(repairMessage)}</p>`;
                }
                if (checks.length) {
                    checksHtml += `<div class="delivery-status-checks">${checks.map((check) => `
                        <article class="publish-status-check">
                            <strong>${bandpromoAdminEscapeHtml(check.label || check.id || 'Issue')} (${Number(check.count || 0)})</strong>
                            <p>${bandpromoAdminEscapeHtml(check.detail || '')}</p>
                            <p>${bandpromoAdminEscapeHtml(check.action || '')}</p>
                        </article>
                    `).join('')}</div>`;
                } else if (ok) {
                    checksHtml += '<p class="publish-status-empty">Listener-ready files look current. Rebuild all deliverables whenever you want extra reassurance.</p>';
                }

                publishStatusSummary.innerHTML = `
                    ${inventoryHtml}
                    ${checksHtml}
                `;
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
                        return { severity: 'recommended-fix', action: 'Add a release name in song metadata (your catalog release until a Releases editor ships)' };
                    case 'missing_track_number':
                        return {
                            severity: totalTracks > 1 ? 'fix-before-publish' : 'recommended-fix',
                            action: totalTracks > 1
                                ? 'Set the track number in song metadata so multi-track releases keep the right order'
                                : 'Set the track number in song metadata if this song belongs to a numbered release',
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
                            case 'missing_track_number':
                                action = {
                                    key: 'metadata',
                                    label: 'Fix song info',
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

            function stopPolling(success) {
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
                if (buildBtn) buildBtn.disabled = false;
                if (buildSpinner) buildSpinner.style.display = 'none';
                if (buildStatus) {
                    const successLabel = '✅ Deliverables rebuild complete!';
                    const failLabel = '❌ Deliverables rebuild failed.';
                    buildStatus.textContent = success === true ? successLabel : success === false ? failLabel : '';
                    buildStatus.style.color = success === true ? 'var(--success, #4ade80)' : '#f55';
                    buildStatus.removeAttribute('data-mode');
                }
                refreshBuildHint();
            }

            function beginBuildPolling(mode = currentRunMode) {
                currentRunMode = mode === 'optimize' ? 'optimize' : 'full';
                if (pollTimer) {
                    return;
                }
                if (buildBtn) buildBtn.disabled = true;
                if (buildSpinner) buildSpinner.style.display = 'inline';
                pollTimer = setInterval(pollLog, 1000);
                pollLog();
            }

            function attachBuildLogIfRunning() {
                if (!buildLog || pollTimer) {
                    return;
                }
                fetch('/biblioteca/get-build-log.php?mode=full')
                    .then((resp) => resp.json())
                    .then((data) => {
                        if (!data || typeof data !== 'object') {
                            return;
                        }
                        if (data.content !== undefined && buildLog) {
                            buildLog.textContent = data.content || '(empty)';
                            scrollLog();
                        }
                        if (data.is_running) {
                            beginBuildPolling(data.mode || 'full');
                        }
                    })
                    .catch(() => {
                        // Ignore — publish tab can still start builds manually.
                    });
            }

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
                        has_publish_status: !!data.publish_status,
                    });
                    if (data.content !== undefined && buildLog) {
                        buildLog.textContent = data.content || '(empty)';
                        scrollLog();
                    }

                    if (data.publish_status) {
                        renderPublishStatusSummary(data.publish_status, data.catalog_repair || null);
                    }

                    if (data.build_required_state) {
                        setBuildRequiredNudge(
                            data.build_required === true,
                            data.build_required_state.reasons || [],
                            data.build_required_state.action || 'none',
                            data.build_required_state.tasks || []
                        );
                        renderOperatorNotifications(data.build_required_state, latestBuildValidation, latestWelcomeState, latestPackageUpdate, latestBackgroundTasks);
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
                    buildSpinner.style.display = 'inline';
                    buildStatus.textContent = '';
                    buildLog.textContent = '⏳ Starting build…\n';
                    const logCard = document.getElementById('build-log-card');
                    if (logCard && logCard.tagName === 'DETAILS') {
                        logCard.open = true;
                    }

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
                            if (data.running) {
                                if (data.content && buildLog) {
                                    buildLog.textContent = data.content;
                                    scrollLog();
                                }
                                beginBuildPolling(data.mode || currentRunMode);
                                console.groupEnd();
                                return;
                            }
                            buildLog.textContent = '❌ ' + data.error;
                            if (data.debug) {
                                console.error('[build] launcher failure debug', data.debug);
                            }
                            stopPolling(false);
                            console.groupEnd();
                            return;
                        }
                        beginBuildPolling('full');
                        console.groupEnd();
                    } catch (e) {
                        console.error('[build] network/launch error', e);
                        buildLog.textContent = '❌ Network error: ' + e.message;
                        stopPolling(false);
                        console.groupEnd();
                    }
                });
            }

            attachBuildLogIfRunning();
            refreshBuildRequiredState();

            (function initContentAutofix() {
                const statusEl = document.getElementById('contentAutofixStatus');
                const reportEl = document.getElementById('contentAutofixReport');
                const previewBtn = document.getElementById('contentAutofixPreviewBtn');
                const applyBtn = document.getElementById('contentAutofixApplyBtn');
                if (!statusEl || !previewBtn || !applyBtn) {
                    return;
                }

                let latestPreview = null;

                function renderAutofixReport(report) {
                    if (!reportEl) {
                        return;
                    }
                    reportEl.replaceChildren();
                    const steps = Array.isArray(report?.steps) ? report.steps : [];
                    if (!steps.length) {
                        reportEl.hidden = true;
                        return;
                    }
                    steps.forEach((step) => {
                        const item = document.createElement('li');
                        const changed = Number(step.changed || 0);
                        const skipped = Number(step.skipped || 0);
                        const errors = Array.isArray(step.errors) ? step.errors.length : 0;
                        const suffix = errors > 0
                            ? ` · ${errors} error${errors === 1 ? '' : 's'}`
                            : changed > 0
                                ? ` · ${changed} change${changed === 1 ? '' : 's'}`
                                : skipped > 0
                                    ? ' · already up to date'
                                    : '';
                        item.textContent = `${step.label || step.id || 'Step'}${suffix}`;
                        reportEl.appendChild(item);
                    });
                    reportEl.hidden = false;
                }

                async function runContentAutofix(dryRun) {
                    previewBtn.disabled = true;
                    applyBtn.disabled = true;
                    statusEl.textContent = dryRun ? 'Checking catalog…' : 'Repairing catalog…';
                    if (reportEl) {
                        reportEl.hidden = true;
                    }
                    try {
                        const resp = await fetch('/biblioteca/content-autofix.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ dry_run: dryRun }),
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || data.error) {
                            throw new Error(data.error || 'Catalog repair failed');
                        }
                        latestPreview = data;
                        statusEl.textContent = data.message || (dryRun ? 'Preview complete.' : 'Catalog repair complete.');
                        renderAutofixReport(data);
                        applyBtn.hidden = dryRun ? Number(data.changed_total || 0) === 0 : true;
                        if (!dryRun && data.recommend_build) {
                            await refreshBuildRequiredState({ full: true });
                        }
                    } catch (error) {
                        statusEl.textContent = error.message || 'Catalog repair failed';
                    } finally {
                        previewBtn.disabled = false;
                        applyBtn.disabled = false;
                    }
                }

                previewBtn.addEventListener('click', () => runContentAutofix(true));
                applyBtn.addEventListener('click', () => {
                    if (!latestPreview || Number(latestPreview.changed_total || 0) === 0) {
                        return;
                    }
                    runContentAutofix(false);
                });
            })();

            (function initPackageUpdater() {
                const card = document.getElementById('packageUpdateCard');
                const statusEl = document.getElementById('packageUpdateStatus');
                const messageEl = document.getElementById('packageUpdateStatusMessage');
                const actionsEl = document.getElementById('packageUpdateStatusActions');
                const refreshBtn = document.getElementById('packageUpdateRefreshBtn');
                const applyBtn = document.getElementById('packageUpdateApplyBtn');

                if (!card || !statusEl || !messageEl || !refreshBtn || !applyBtn) {
                    return;
                }

                let latestStatus = null;

                function setCardMode(mode) {
                    card.classList.remove(
                        'package-update-card--quiet',
                        'package-update-card--attention',
                        'package-update-card--busy'
                    );
                    card.classList.add('package-update-card--' + mode);
                }

                function setStatusClass(className) {
                    statusEl.className = 'package-update-status' + (className ? ' ' + className : '');
                }

                function setStatusMessage(text) {
                    messageEl.textContent = String(text || '');
                }

                function syncActionButtons() {
                    const showActions = !refreshBtn.hidden || !applyBtn.hidden;
                    if (actionsEl) {
                        actionsEl.hidden = !showActions;
                    }
                }

                function formatPackageUpdateBlockedMessage(data) {
                    const checks = Array.isArray(data.checks) ? data.checks : [];
                    const failed = checks.filter((check) => check && check.ok === false);
                    if (failed.length === 0) {
                        return 'Updates are not available on this hosting setup yet. Contact your host if this persists.';
                    }
                    const first = failed[0];
                    const label = first.label || first.id || 'Hosting requirement';
                    const detail = first.detail ? ` (${first.detail})` : '';
                    return `${label} is not met${detail}. Fix this with your host before installing the update.`;
                }

                renderPackageUpdateStatus = function renderPackageUpdateCard(data) {
                    latestStatus = data;
                    refreshBtn.hidden = true;
                    applyBtn.hidden = true;

                    if (!data.ok) {
                        setCardMode('attention');
                        setStatusClass('is-error');
                        setStatusMessage(data.error || 'Could not check for updates right now.');
                        refreshBtn.hidden = false;
                        syncActionButtons();
                        return;
                    }

                    const installed = data.installed_version || '';
                    const remote = data.remote_version || '';

                    if (data.manifest_error) {
                        setCardMode('attention');
                        setStatusClass('is-warning');
                        setStatusMessage('Could not reach the update service. Try again in a few minutes.');
                        refreshBtn.hidden = false;
                    } else if (!data.ready) {
                        setCardMode('attention');
                        setStatusClass('is-warning');
                        setStatusMessage(formatPackageUpdateBlockedMessage(data));
                        refreshBtn.hidden = false;
                    } else if (data.update_available) {
                        setCardMode('attention');
                        setStatusClass('is-available');
                        const versionHint = installed && remote ? ` (${installed} → ${remote})` : '';
                        setStatusMessage(`A new version is ready${versionHint}. Your content stays safe.`);
                        applyBtn.hidden = false;
                    } else if (data.ahead_of_published || data.up_to_date) {
                        setCardMode('quiet');
                        setStatusClass('is-current');
                        setStatusMessage('Your site is up to date. Your content and settings are safe.');
                    } else {
                        setCardMode('attention');
                        setStatusClass('is-warning');
                        setStatusMessage('Update status is unclear.');
                        refreshBtn.hidden = false;
                    }

                    syncActionButtons();
                };

                async function refreshPackageUpdateStatus() {
                    refreshBtn.disabled = true;
                    applyBtn.disabled = true;
                    setCardMode('busy');
                    setStatusClass('');
                    refreshBtn.hidden = true;
                    applyBtn.hidden = true;
                    syncActionButtons();
                    setStatusMessage('Checking for updates…');

                    try {
                        if (typeof refreshBuildRequiredState === 'function') {
                            await refreshBuildRequiredState({ full: true });
                        }
                    } catch (error) {
                        renderPackageUpdateStatus({
                            ok: false,
                            error: 'Network error: ' + error.message,
                        });
                    } finally {
                        refreshBtn.disabled = false;
                        applyBtn.disabled = false;
                    }
                }

                async function applyPackageUpdate() {
                    if (!latestStatus || !latestStatus.update_available) {
                        setStatusClass('is-warning');
                        setStatusMessage('Update status is still loading. Wait a moment, then try again.');
                        refreshBtn.hidden = false;
                        syncActionButtons();
                        return;
                    }

                    const remote = latestStatus.remote_version || 'the new version';
                    const confirmed = window.confirm(
                        `Install ${remote} now?\n\nYour music, pages, and settings stay safe.`
                    );
                    if (!confirmed) {
                        return;
                    }

                    packageUpdateInstallInProgress = true;
                    refreshBtn.disabled = true;
                    applyBtn.disabled = true;
                    setCardMode('attention');
                    setStatusClass('');
                    refreshBtn.hidden = true;
                    applyBtn.hidden = true;
                    syncActionButtons();
                    setStatusMessage('Installing update… This can take a minute.');

                    try {
                        const csrfToken = await refreshAdminCsrfToken();
                        const resp = await fetch('/biblioteca/apply-package-update.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ csrf_token: csrfToken }),
                        });
                        const data = await resp.json().catch(() => ({}));

                        if (!resp.ok || !data || data.ok !== true) {
                            packageUpdateInstallInProgress = false;
                            setStatusClass('is-error');
                            let failureMessage = 'Update failed. Please try again.';
                            if (data && data.error) {
                                failureMessage = 'Update failed: ' + data.error;
                            }
                            setStatusMessage(failureMessage);
                            refreshBtn.hidden = false;
                            syncActionButtons();
                            await refreshPackageUpdateStatus();
                            return;
                        }

                        setCardMode('attention');
                        setStatusClass('is-ready');
                        setStatusMessage(data.message || 'Update installed successfully.');
                        applyBtn.hidden = true;

                        if (typeof refreshBuildRequiredState === 'function') {
                            await refreshBuildRequiredState({ full: true });
                        }
                        if (typeof refreshBuildHint === 'function') {
                            refreshBuildHint();
                        }

                        window.setTimeout(() => {
                            packageUpdateInstallInProgress = false;
                            window.location.reload();
                        }, 1800);
                    } catch (error) {
                        packageUpdateInstallInProgress = false;
                        setStatusClass('is-error');
                        setStatusMessage('Network error: ' + error.message);
                        refreshBtn.hidden = false;
                        syncActionButtons();
                    } finally {
                        refreshBtn.disabled = false;
                        applyBtn.disabled = false;
                    }
                }

                refreshBtn.addEventListener('click', () => {
                    refreshPackageUpdateStatus().catch(() => {});
                });
                applyBtn.addEventListener('click', () => {
                    applyPackageUpdate().catch(() => {});
                });

                if (latestPackageUpdate) {
                    renderPackageUpdateStatus({
                        ok: true,
                        ...latestPackageUpdate,
                    });
                } else {
                    refreshPackageUpdateStatus().catch(() => {});
                }
            })();

            (function initBackupExportTab() {
                const jobsWrap = document.getElementById('siteBackupJobsWrap');
                const jobsStatus = document.getElementById('siteBackupJobsStatus');
                const createBtn = document.getElementById('siteBackupCreateBtn');
                const createStatus = document.getElementById('siteBackupCreateStatus');
                const fullCheckbox = document.getElementById('siteBackupComponentFull');
                const componentInputs = Array.from(document.querySelectorAll('.site-backup-component-input'));
                const allComponentIds = ['platform', 'data', 'media', 'logs'];
                let backupPollTimer = null;
                let syncingFullCheckbox = false;

                function escapeHtml(value) {
                    return bandpromoAdminEscapeHtml(value);
                }

                function selectedComponents() {
                    return componentInputs
                        .filter((input) => input.checked)
                        .map((input) => String(input.getAttribute('data-component') || '').trim())
                        .filter(Boolean);
                }

                function syncFullCheckbox() {
                    if (!fullCheckbox) {
                        return;
                    }
                    syncingFullCheckbox = true;
                    const allChecked = allComponentIds.every((component) => {
                        const input = componentInputs.find((el) => el.getAttribute('data-component') === component);
                        return input ? input.checked : false;
                    });
                    fullCheckbox.checked = allChecked;
                    syncingFullCheckbox = false;
                }

                function setAllComponents(checked) {
                    componentInputs.forEach((input) => {
                        input.checked = checked;
                    });
                }

                if (fullCheckbox) {
                    fullCheckbox.addEventListener('change', () => {
                        if (syncingFullCheckbox) {
                            return;
                        }
                        setAllComponents(fullCheckbox.checked);
                    });
                }

                componentInputs.forEach((input) => {
                    input.addEventListener('change', () => {
                        syncFullCheckbox();
                    });
                });

                function statusMeta(job) {
                    const status = String((job && job.status) || '');
                    const direction = String((job && job.direction) || 'export');
                    switch (status) {
                        case 'building':
                            return {
                                label: direction === 'import' ? 'Importing…' : 'Building…',
                                className: 'status-warning',
                            };
                        case 'ready':
                            return {
                                label: direction === 'import' ? 'Imported' : 'Ready',
                                className: 'status-ok',
                            };
                        case 'failed':
                            return { label: 'Failed', className: 'status-error' };
                        default:
                            return { label: 'Queued', className: 'status-neutral' };
                    }
                }

                function renderBackupJobs(jobs) {
                    if (!jobsWrap) {
                        return;
                    }

                    if (!Array.isArray(jobs) || jobs.length === 0) {
                        jobsWrap.innerHTML = '<p id="siteBackupJobsEmpty" class="empty-msg">No backup jobs yet. Create or import one below.</p>';
                        return;
                    }

                    const rows = jobs.map((job) => {
                        const meta = statusMeta(job);
                        const errorHtml = job.status === 'failed' && job.error
                            ? `<div class="text-muted site-backup-job-error">${escapeHtml(job.error)}</div>`
                            : '';
                        const noteHtml = job.status === 'ready' && job.direction === 'import' && job.import_summary
                            ? `<div class="text-muted site-backup-job-note">${escapeHtml(job.import_summary)}</div>`
                            : '';
                        const downloadHtml = job.download_ready
                            ? `<a class="btn btn-secondary site-backup-action-btn" href="/biblioteca/download-site-backup.php?id=${encodeURIComponent(job.id)}">⬇️ Download</a>`
                            : '';
                        const deleteHtml = job.status !== 'building'
                            ? `<button type="button" class="btn btn-secondary site-backup-action-btn site-backup-delete-btn" data-backup-id="${escapeHtml(job.id)}">🗑️ Delete</button>`
                            : '';

                        return `<tr data-backup-id="${escapeHtml(job.id)}">
                            <td>${escapeHtml(job.type_label || job.type || '')}</td>
                            <td><span class="badge audit-status-badge ${meta.className}">${escapeHtml(meta.label)}</span>${noteHtml}${errorHtml}</td>
                            <td class="text-muted nowrap">${escapeHtml(job.created_at_utc || '')}</td>
                            <td class="nowrap">${escapeHtml(job.size_label || '—')}</td>
                            <td class="site-backup-job-actions">${downloadHtml}${deleteHtml}</td>
                        </tr>`;
                    }).join('');

                    jobsWrap.innerHTML = `<div class="table-scroll">
                        <table class="site-backup-jobs-table" id="siteBackupJobsTable">
                            <thead>
                                <tr>
                                    <th>Contents</th>
                                    <th>Status</th>
                                    <th>Created (UTC)</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="siteBackupJobsBody">${rows}</tbody>
                        </table>
                    </div>`;
                }

                function jobsNeedPolling(jobs) {
                    return Array.isArray(jobs) && jobs.some((job) => {
                        const status = String(job.status || '');
                        return status === 'pending' || status === 'building';
                    });
                }

                function syncBackupPolling(jobs) {
                    if (!jobsWrap) {
                        return;
                    }
                    if (jobsNeedPolling(jobs)) {
                        if (!backupPollTimer) {
                            backupPollTimer = window.setInterval(() => {
                                refreshBackupJobs().catch(() => {});
                            }, 3000);
                        }
                        return;
                    }
                    if (backupPollTimer) {
                        window.clearInterval(backupPollTimer);
                        backupPollTimer = null;
                    }
                }

                async function refreshBackupJobs() {
                    if (!jobsWrap) {
                        return [];
                    }
                    const resp = await fetch('/biblioteca/list-site-backups.php', {
                        credentials: 'same-origin',
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok || !data || data.ok !== true || !Array.isArray(data.jobs)) {
                        throw new Error((data && data.error) || 'Could not refresh backup list.');
                    }
                    renderBackupJobs(data.jobs);
                    syncBackupPolling(data.jobs);
                    return data.jobs;
                }

                async function queueBackup(statusEl, buttonEl) {
                    const components = selectedComponents();
                    if (components.length === 0) {
                        if (statusEl) {
                            statusEl.textContent = 'Select at least one component to include.';
                            statusEl.classList.add('is-error');
                        }
                        return;
                    }
                    if (buttonEl) {
                        buttonEl.disabled = true;
                    }
                    if (statusEl) {
                        statusEl.textContent = 'Queueing backup…';
                        statusEl.classList.remove('is-error');
                    }
                    try {
                        const csrfToken = await refreshAdminCsrfToken();
                        const resp = await fetch('/biblioteca/create-site-backup.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                csrf_token: csrfToken,
                                components,
                            }),
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || !data || data.ok !== true) {
                            throw new Error((data && data.error) || 'Could not queue backup.');
                        }
                        if (statusEl) {
                            statusEl.textContent = data.message || 'Backup queued.';
                        }
                        if (jobsStatus) {
                            jobsStatus.textContent = 'Building in background. This list refreshes automatically.';
                            jobsStatus.classList.remove('is-error');
                        }
                        await refreshBackupJobs();
                    } catch (error) {
                        if (statusEl) {
                            statusEl.textContent = error.message;
                            statusEl.classList.add('is-error');
                        }
                    } finally {
                        if (buttonEl) {
                            buttonEl.disabled = false;
                        }
                    }
                }

                async function deleteBackup(jobId) {
                    const confirmed = window.confirm('Delete this backup archive from the server?');
                    if (!confirmed) {
                        return;
                    }
                    if (jobsStatus) {
                        jobsStatus.textContent = 'Deleting backup…';
                        jobsStatus.classList.remove('is-error');
                    }
                    try {
                        const csrfToken = await refreshAdminCsrfToken();
                        const resp = await fetch('/biblioteca/delete-site-backup.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                csrf_token: csrfToken,
                                id: jobId,
                            }),
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || !data || data.ok !== true) {
                            throw new Error((data && data.error) || 'Could not delete backup.');
                        }
                        if (jobsStatus) {
                            jobsStatus.textContent = data.message || 'Backup deleted.';
                        }
                        renderBackupJobs(data.jobs || []);
                        syncBackupPolling(data.jobs || []);
                    } catch (error) {
                        if (jobsStatus) {
                            jobsStatus.textContent = error.message;
                            jobsStatus.classList.add('is-error');
                        }
                    }
                }

                if (jobsWrap) {
                    jobsWrap.addEventListener('click', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLElement)) {
                            return;
                        }
                        const deleteBtn = target.closest('.site-backup-delete-btn');
                        if (!deleteBtn) {
                            return;
                        }
                        const jobId = deleteBtn.getAttribute('data-backup-id');
                        if (!jobId) {
                            return;
                        }
                        deleteBackup(jobId).catch(() => {});
                    });

                    refreshBackupJobs().catch(() => {});
                }

                if (createBtn) {
                    createBtn.addEventListener('click', () => {
                        queueBackup(createStatus, createBtn).catch(() => {});
                    });
                }

                const importFile = document.getElementById('siteBackupImportFile');
                const importFilename = document.getElementById('siteBackupImportFilename');
                const importPreview = document.getElementById('siteBackupImportPreview');
                const importPreviewMeta = document.getElementById('siteBackupImportPreviewMeta');
                const importMode = document.getElementById('siteBackupImportMode');
                const importBtn = document.getElementById('siteBackupImportBtn');
                const importStatus = document.getElementById('siteBackupImportStatus');
                const importRepairUrl = document.getElementById('siteBackupImportRepairUrl');
                const importFullCheckbox = document.getElementById('siteBackupImportComponentFull');
                const importComponentInputs = Array.from(document.querySelectorAll('.site-backup-import-component-input'));
                let importStagingId = '';
                let importAvailableComponents = [];
                let syncingImportFullCheckbox = false;

                function selectedImportComponents() {
                    return importComponentInputs
                        .filter((input) => !input.disabled && input.checked)
                        .map((input) => String(input.getAttribute('data-component') || '').trim())
                        .filter(Boolean);
                }

                function syncImportFullCheckbox() {
                    if (!importFullCheckbox) {
                        return;
                    }
                    syncingImportFullCheckbox = true;
                    const enabled = importComponentInputs.filter((input) => !input.disabled);
                    const allChecked = enabled.length > 0 && enabled.every((input) => input.checked);
                    importFullCheckbox.checked = allChecked;
                    syncingImportFullCheckbox = false;
                }

                function setImportComponents(components) {
                    const selected = new Set(Array.isArray(components) ? components : []);
                    importComponentInputs.forEach((input) => {
                        const component = String(input.getAttribute('data-component') || '');
                        const available = importAvailableComponents.includes(component);
                        input.disabled = !available;
                        input.checked = available && selected.has(component);
                        input.closest('.site-backup-component-row')?.classList.toggle('is-disabled', !available);
                    });
                    if (importFullCheckbox) {
                        importFullCheckbox.disabled = importAvailableComponents.length === 0;
                    }
                    syncImportFullCheckbox();
                }

                function applyImportModeDefaults() {
                    const mode = importMode ? importMode.value : 'restore';
                    if (mode === 'migrate') {
                        const migrateDefaults = ['platform', 'data', 'media'].filter((component) => (
                            importAvailableComponents.includes(component)
                        ));
                        setImportComponents(migrateDefaults);
                        if (importRepairUrl) {
                            importRepairUrl.checked = true;
                        }
                    } else {
                        setImportComponents(importAvailableComponents);
                        if (importRepairUrl) {
                            importRepairUrl.checked = false;
                        }
                    }
                }

                if (importFullCheckbox) {
                    importFullCheckbox.addEventListener('change', () => {
                        if (syncingImportFullCheckbox) {
                            return;
                        }
                        if (importFullCheckbox.checked) {
                            setImportComponents(importAvailableComponents);
                        } else {
                            importComponentInputs.forEach((input) => {
                                if (!input.disabled) {
                                    input.checked = false;
                                }
                            });
                        }
                    });
                }

                importComponentInputs.forEach((input) => {
                    input.addEventListener('change', () => {
                        syncImportFullCheckbox();
                    });
                });

                if (importMode) {
                    importMode.addEventListener('change', () => {
                        applyImportModeDefaults();
                    });
                }

                async function inspectImportArchive(file) {
                    if (!file) {
                        return;
                    }
                    if (importStatus) {
                        importStatus.textContent = 'Inspecting archive…';
                        importStatus.classList.remove('is-error');
                    }
                    if (importPreview) {
                        importPreview.hidden = true;
                    }
                    importStagingId = '';
                    importAvailableComponents = [];

                    try {
                        const csrfToken = await refreshAdminCsrfToken();
                        const formData = new FormData();
                        formData.append('csrf_token', csrfToken);
                        formData.append('archive', file);
                        const resp = await fetch('/biblioteca/inspect-site-backup.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData,
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || !data || data.ok !== true) {
                            throw new Error((data && data.error) || 'Could not inspect archive.');
                        }

                        importStagingId = String(data.staging_id || '');
                        importAvailableComponents = Array.isArray(data.available_components)
                            ? data.available_components
                            : [];

                        if (importFilename) {
                            importFilename.textContent = data.original_filename || file.name;
                        }
                        if (importPreviewMeta) {
                            const lines = [
                                data.components_label ? `Archive: ${data.components_label}` : '',
                                data.bandpromo_version ? `Exported from bandPromo ${data.bandpromo_version}` : '',
                                data.exported_at_utc ? `Created ${data.exported_at_utc}` : '',
                                data.size_label ? `Size ${data.size_label}` : '',
                                data.same_install
                                    ? 'Matches this install identity.'
                                    : 'From another install (migrate mode recommended).',
                            ].filter(Boolean);
                            importPreviewMeta.innerHTML = lines.map((line) => escapeHtml(line)).join('<br>');
                        }
                        if (importMode) {
                            importMode.value = data.suggested_mode === 'migrate' ? 'migrate' : 'restore';
                        }
                        if (importRepairUrl) {
                            importRepairUrl.checked = Boolean(data.url_mismatch) || data.suggested_mode === 'migrate';
                        }
                        applyImportModeDefaults();
                        if (importPreview) {
                            importPreview.hidden = false;
                        }
                        if (importStatus) {
                            importStatus.textContent = 'Archive ready to import.';
                        }
                    } catch (error) {
                        if (importStatus) {
                            importStatus.textContent = error.message;
                            importStatus.classList.add('is-error');
                        }
                        if (importFilename) {
                            importFilename.textContent = '';
                        }
                    }
                }

                if (importFile) {
                    importFile.addEventListener('change', () => {
                        const file = importFile.files && importFile.files[0];
                        inspectImportArchive(file).catch(() => {});
                    });
                }

                async function queueImport() {
                    const components = selectedImportComponents();
                    if (!importStagingId) {
                        if (importStatus) {
                            importStatus.textContent = 'Choose and inspect an archive first.';
                            importStatus.classList.add('is-error');
                        }
                        return;
                    }
                    if (components.length === 0) {
                        if (importStatus) {
                            importStatus.textContent = 'Select at least one component to import.';
                            importStatus.classList.add('is-error');
                        }
                        return;
                    }

                    const mode = importMode ? importMode.value : 'restore';
                    const componentLabels = components.join(', ');
                    const confirmed = window.confirm(
                        `Import ${componentLabels} from this archive?\n\nThis overwrites matching files on this site.`
                    );
                    if (!confirmed) {
                        return;
                    }

                    if (importBtn) {
                        importBtn.disabled = true;
                    }
                    if (importStatus) {
                        importStatus.textContent = 'Queueing import…';
                        importStatus.classList.remove('is-error');
                    }

                    try {
                        const csrfToken = await refreshAdminCsrfToken();
                        const resp = await fetch('/biblioteca/import-site-backup.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                csrf_token: csrfToken,
                                staging_id: importStagingId,
                                components,
                                mode,
                                repair_site_url: importRepairUrl && importRepairUrl.checked ? '1' : '0',
                            }),
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || !data || data.ok !== true) {
                            throw new Error((data && data.error) || 'Could not queue import.');
                        }

                        importStagingId = '';
                        if (importFile) {
                            importFile.value = '';
                        }
                        if (importPreview) {
                            importPreview.hidden = true;
                        }
                        if (importFilename) {
                            importFilename.textContent = '';
                        }
                        if (importStatus) {
                            importStatus.textContent = data.message || 'Import queued.';
                        }
                        if (jobsStatus) {
                            jobsStatus.textContent = 'Import running in background. This list refreshes automatically.';
                            jobsStatus.classList.remove('is-error');
                        }
                        await refreshBackupJobs();
                    } catch (error) {
                        if (importStatus) {
                            importStatus.textContent = error.message;
                            importStatus.classList.add('is-error');
                        }
                    } finally {
                        if (importBtn) {
                            importBtn.disabled = false;
                        }
                    }
                }

                if (importBtn) {
                    importBtn.addEventListener('click', () => {
                        queueImport().catch(() => {});
                    });
                }
            })();
        })();
