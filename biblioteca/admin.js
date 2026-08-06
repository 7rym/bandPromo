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
                visual:         { accept: '.png,.jpg,.jpeg,.webp,.mp4,.webm,.mov', target: 'visual' },
                special:        { accept: '.mp4,.webm,.mov,.png,.jpg,.jpeg,.webp,.svg,.gif', target: 'special' },
                sfx:            { accept: '.flac,.mp3,.wav,.ogg,.m4a', target: 'sfx' },
            };
            const VISUAL_INTAKE_BUCKETS = ['illustrations', 'photos', 'video'];
            function normalizeFilesPanel(panel) {
                const value = String(panel || '').trim();
                return VISUAL_INTAKE_BUCKETS.includes(value) ? 'visual' : (value || 'audio');
            }
            window.activeMediaPanel = normalizeFilesPanel(adminActivePanel);
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
            let poolBrandFilter = 'all';
            let poolNameFilters = {
                audio: '',
                visual: '',
                special: '',
                sfx: '',
            };
            let releasesCatalog = [];
            const releaseFilterListeners = [];
            function registerReleaseFilterListener(listener) {
                if (typeof listener === 'function') {
                    releaseFilterListeners.push(listener);
                }
            }
            let poolTypeFilters = {
                visual: 'all',
                special: 'all',
                sfx: 'all',
            };
            let poolBrandFilters = {
                visual: 'all',
                special: 'all',
                sfx: 'all',
            };
            let brandFilterCatalog = [];
            let brandFilterCatalogLoaded = false;
            let poolViewModes = {
                visual: (() => {
                    try {
                        const stored = String(window.localStorage.getItem('bandpromo_visual_pool_view') || '').trim();
                        return stored === 'list' ? 'list' : 'grid';
                    } catch (error) {
                        return 'grid';
                    }
                })(),
                special: (() => {
                    try {
                        const stored = String(window.localStorage.getItem('bandpromo_brand_pool_view') || '').trim();
                        return stored === 'list' ? 'list' : 'grid';
                    } catch (error) {
                        return 'grid';
                    }
                })(),
                sfx: 'list',
            };
            let activePoolAsset = { panel: null, key: null };
            const mediaReferenceFilters = {
                visual: 'all',
                special: 'all',
                sfx: 'all',
                photos: 'all',
                video: 'all',
            };
            const mediaReferenceFilterTypes = new Set(['visual', 'illustrations', 'photos', 'video']);
            const poolPanelTypes = new Set(['visual', 'special', 'sfx']);
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
                visual: 'Visual',
                illustrations: 'Illustrations',
                photos: 'Photos',
                special: 'Brand assets',
                sfx: 'Sound effects',
            };
            const mediaPathMap = {
                audio: '/media/audio/original',
                video: '/media/video/original',
                illustrations: '/media/img/original',
                photos: '/media/photo/original',
                special: '/media/special',
                sfx: '/media/sfx/original',
                // Registry intake aliases (visual pool / asset.intake_bucket).
                img: '/media/img/original',
                photo: '/media/photo/original',
                images: '/media/img/original',
            };

            function normalizeMediaPathType(type) {
                const raw = String(type || '').trim().toLowerCase();
                if (raw === 'img' || raw === 'images' || raw === 'image') {
                    return 'illustrations';
                }
                if (raw === 'photo') {
                    return 'photos';
                }
                return raw;
            }

            function getMediaBasePath(type) {
                const normalized = normalizeMediaPathType(type);
                return mediaPathMap[normalized] || mediaPathMap[type] || '';
            }

            function buildMediaPath(type, filename) {
                const base = getMediaBasePath(type);
                const safeName = String(filename || '').replace(/^\/+/, '');
                if (!base) {
                    // Never emit "/filename" — that breaks cover preview + poster save.
                    return safeName ? `/media/img/original/${safeName}` : '';
                }
                return `${base}/${safeName}`;
            }

            function buildMediaUrl(type, filename) {
                const base = getMediaBasePath(type);
                const safeName = encodeURIComponent(String(filename || ''));
                if (!base) {
                    return safeName ? `/media/img/original/${safeName}` : '';
                }
                return `${base}/${safeName}`;
            }

            function resolveFileIntakeBucket(file, panelType = '') {
                if (panelType === 'visual' || VISUAL_INTAKE_BUCKETS.includes(panelType)) {
                    const bucket = String(file?.intake_bucket || '').trim();
                    if (VISUAL_INTAKE_BUCKETS.includes(bucket)) {
                        return bucket;
                    }
                    // Registry buckets (img/photo) must map to Files path types.
                    const normalized = normalizeMediaPathType(bucket);
                    if (VISUAL_INTAKE_BUCKETS.includes(normalized)) {
                        return normalized;
                    }
                    return isVideo(file?.name) ? 'video' : 'illustrations';
                }
                return panelType || String(file?.intake_bucket || '').trim();
            }

            function mediaFileSelectionKey(panelType, fileOrName, intakeBucket = '') {
                if (panelType !== 'visual') {
                    return typeof fileOrName === 'string'
                        ? String(fileOrName || '')
                        : String(fileOrName?.name || '');
                }
                if (typeof fileOrName === 'object' && fileOrName) {
                    const bucket = resolveFileIntakeBucket(fileOrName, 'visual');
                    return `${bucket}::${fileOrName.name}`;
                }
                const bucket = String(intakeBucket || 'illustrations').trim() || 'illustrations';
                return `${bucket}::${fileOrName}`;
            }

            function parseMediaSelectionKey(panelType, key) {
                const raw = String(key || '');
                if (panelType !== 'visual') {
                    return { bucket: panelType, name: raw };
                }
                const sep = raw.indexOf('::');
                if (sep < 0) {
                    return { bucket: 'illustrations', name: raw };
                }
                return {
                    bucket: raw.slice(0, sep) || 'illustrations',
                    name: raw.slice(sep + 2),
                };
            }

            function groupSelectionKeysByBucket(panelType, keys) {
                const groups = new Map();
                (Array.isArray(keys) ? keys : []).forEach((key) => {
                    const parts = parseMediaSelectionKey(panelType, key);
                    if (!parts.name) {
                        return;
                    }
                    if (!groups.has(parts.bucket)) {
                        groups.set(parts.bucket, []);
                    }
                    groups.get(parts.bucket).push(parts.name);
                });
                return groups;
            }

            function selectionDisplayName(panelType, key) {
                const parsed = parseMediaSelectionKey(panelType, key);
                const rawName = parsed.name || String(key || '');
                if (panelType === 'audio') {
                    const file = (getMediaFileState('audio') || []).find((entry) => String(entry?.name || '') === rawName);
                    if (file) {
                        return formatAudioListRowBody(audioFileForDisplay(file)) || 'Untitled';
                    }
                    return 'Untitled';
                }
                if (poolPanelTypes.has(panelType)) {
                    const file = findPoolAssetByKey(panelType, key);
                    if (file) {
                        return poolAssetHeadline(panelType, file);
                    }
                    return panelType === 'special' ? 'Brand asset' : (panelType === 'sfx' ? 'Sound effect' : 'Visual asset');
                }
                return rawName;
            }

            function isVisualMediaRow(panelType, file = null) {
                if (panelType === 'video') {
                    return true;
                }
                if (panelType === 'visual') {
                    return String(file?.media_type || '') === 'video' || isVideo(file?.name);
                }
                return false;
            }

            function extIcon(name) {
                const ext = String(name).split('.').pop().toLowerCase();
                if (['mp3', 'flac', 'ogg', 'wav', 'aac'].includes(ext)) return '🎵';
                if (['mp4', 'mov', 'webm'].includes(ext)) return '🎬';
                if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext)) return '🖼️';
                return '📄';
            }

            function isImage(name) {
                return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'avif'].includes(String(name).split('.').pop().toLowerCase());
            }

            function isVideo(name) {
                return ['mp4', 'mov', 'webm', 'm4v', 'ogv'].includes(String(name).split('.').pop().toLowerCase());
            }

            function isAudio(name) {
                return ['mp3', 'flac', 'ogg', 'wav', 'aac', 'm4a', 'aif', 'aiff'].includes(String(name).split('.').pop().toLowerCase());
            }

            function isPreviewable(name, file = null, type = '') {
                if (isImage(name)) {
                    return true;
                }
                if (!isVideo(name)) {
                    return false;
                }
                if (isVisualMediaRow(type, file) && file) {
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

            function consumePostPackageUpdateFlash() {
                const storageKey = 'bandpromo_post_package_update';
                let payload = null;
                try {
                    const raw = sessionStorage.getItem(storageKey);
                    if (raw) {
                        payload = JSON.parse(raw);
                        sessionStorage.removeItem(storageKey);
                    }
                } catch (error) {
                    try {
                        sessionStorage.removeItem(storageKey);
                    } catch (storageError) {
                        // Ignore storage failures.
                    }
                }

                if (!payload || typeof payload !== 'object') {
                    return null;
                }

                return String(payload.version || '').trim();
            }

            function showPostPackageUpdateToast(installedVersion, runResult) {
                if (typeof showAdminToast !== 'function') {
                    return;
                }

                const version = String(installedVersion || '').trim();
                const versionPrefix = version
                    ? `Site update to ${version} installed. `
                    : 'Site update installed. ';

                if (runResult === 'started' || runResult === 'already-running') {
                    showAdminToast(
                        versionPrefix + 'Rebuilding deliverables for your public site…',
                        'success'
                    );
                    return;
                }

                showAdminToast(
                    versionPrefix + 'Click Rebuild all deliverables to refresh your public site.',
                    'success'
                );
            }

            function rememberPostPackageUpdateFollowUp(installedVersion) {
                try {
                    sessionStorage.setItem('bandpromo_post_package_update', JSON.stringify({
                        version: String(installedVersion || '').trim(),
                        at: Date.now(),
                    }));
                } catch (error) {
                    // Redirect still works when storage is unavailable.
                }
            }

            function shouldRunRecommendedBuildAfterPackageUpdate(postUpdate) {
                const followUp = postUpdate && typeof postUpdate === 'object'
                    ? String(postUpdate.follow_up || '').trim()
                    : '';
                return followUp === 'open_build_tab' || followUp === 'run_recommended_build';
            }

            function clearRecommendedRunQuery() {
                if (!window.history || typeof window.history.replaceState !== 'function') {
                    return;
                }
                window.history.replaceState({}, '', buildBuildTabUrl());
            }

            function openBuildLogCard() {
                const logCard = document.getElementById('build-log-card');
                if (!logCard) {
                    return;
                }
                if (logCard.tagName === 'DETAILS') {
                    logCard.open = true;
                }
                logCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

                if (afterPackageUpdate) {
                    return {
                        severity: 'fix-before-publish',
                        title: 'Site update installed — rebuild deliverables',
                        file: '',
                        checkedAt: String(buildState.updated_at || '').trim(),
                        details: [
                            { text: 'Your content and settings were preserved. Rebuilding deliverables refreshes listener-ready files and the site manifest for the new version.' },
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
                    const lastError = String(buildState.last_error || '').trim();
                    const details = [
                        { text: lastError !== ''
                            ? 'Automatic preparation after upload did not finish. Fix the issue below, then retry from Deliverables if needed.'
                            : 'Your edits are saved in admin. Rebuild all deliverables when you are ready for visitors to get the latest files.' },
                        { text: taskIntro },
                    ];
                    if (lastError !== '') {
                        details.push({ text: lastError });
                    }

                    return {
                        severity: 'recommended-fix',
                        title: lastError !== ''
                            ? 'Upload preparation needs attention'
                            : 'Saved changes are not live yet',
                        file: '',
                        checkedAt: String(buildState.updated_at || '').trim(),
                        details,
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
                        { label: 'Open Dashboard → Site update', href: '?tab=welcome#packageUpdateCard' },
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
                        const taskId = String(item.id || '').trim();
                        const taskName = String(item.task || '').trim();
                        const isAudio = taskName === 'audio-delivery';
                        notifications.push({
                            severity: 'background-running',
                            title: isAudio
                                ? 'Your track is preparing'
                                : 'Your video is preparing',
                            file: '',
                            taskId,
                            checkedAt: String(item.started_at || item.updated_at || '').trim(),
                            details: [
                                {
                                    text: isAudio
                                        ? `bandPromo is preparing streaming files for ${fileLine}. You can keep working — no action needed.`
                                        : `bandPromo is preparing ${fileLine} for the website. You can keep working — no action needed.`,
                                },
                                { text: 'If this never finishes and Site update stays stuck, stop retrying to unlock updates.' },
                            ],
                            actions: [
                                { label: 'Stop retrying (unlock updates)', action: 'force-stop-video-delivery' },
                            ],
                        });
                        return;
                    }

                    if (status === 'done') {
                        const taskName = String(item.task || '').trim();
                        const isAudio = taskName === 'audio-delivery';
                        notifications.push({
                            severity: 'background-done',
                            title: isAudio ? 'Track preparation finished' : 'Video preparation finished',
                            file: '',
                            checkedAt: String(item.finished_at || item.updated_at || '').trim(),
                            details: [
                                {
                                    text: isAudio
                                        ? `${fileLine} ${files.length === 1 ? 'is' : 'are'} ready for listeners.`
                                        : `${fileLine} ${files.length === 1 ? 'is' : 'are'} ready for preview and gallery use.`,
                                },
                            ],
                            actions: [
                                {
                                    label: isAudio ? 'Open Files' : 'Open Files',
                                    href: isAudio ? '?tab=files&fpanel=audio' : '?tab=files&fpanel=visual',
                                },
                            ],
                        });
                        return;
                    }

                    if (status === 'failed') {
                        const focusFile = files[0] || '';
                        const taskId = String(item.id || '').trim();
                        const forceStopped = item.force_stopped === true
                            || /force-stopped|Force-stopped/i.test(String(item.error || ''));
                        notifications.push({
                            severity: 'recommended-fix',
                            title: forceStopped ? 'Video preparation paused' : 'Video preparation needs attention',
                            file: focusFile,
                            taskId,
                            checkedAt: String(item.finished_at || item.started_at || item.updated_at || '').trim(),
                            details: [
                                { text: String(item.error || 'bandPromo could not prepare the video file in the background.').trim() },
                                {
                                    text: forceStopped
                                        ? 'Auto-retry is paused for about an hour so you can install Site updates or Publish. It will resume later, or after a host refresh of stuck videos.'
                                        : 'bandPromo pauses auto-retry briefly after failures. If the loop returns, use Stop retrying to unlock Site update.',
                                },
                            ],
                            actions: [
                                ...(focusFile
                                    ? [{ label: 'Open video in Files', href: buildAdminUrl({ tab: 'files', fpanel: 'visual', focus_file: focusFile }) }]
                                    : [{ label: 'Open Files', href: '?tab=files&fpanel=visual' }]),
                                ...(taskId ? [{ label: 'Stop retrying', action: 'force-stop-video-delivery', taskId }] : []),
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
                }, 8000);
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

                // Setup checklist stays on Welcome only — never mirror it into Notifications.
                // Live inbox = package update, publish follow-up, validation, background prep.

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
                    await refreshBuildRequiredState({
                        full: true,
                        inventory: isDeliverablesViewActive(),
                    });
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
                    return;
                }

                if (action === 'force-stop-video-delivery') {
                    event.preventDefault();
                    const taskId = String(actionButton.dataset.operatorTaskId || '').trim();
                    dismissOperatorNotification('force-stop-video-delivery', taskId);
                }
            });

            async function dismissOperatorNotification(type, id) {
                try {
                    const resp = await fetch('/biblioteca/dismiss-operator-notification.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ type, id: id || '' }),
                    });
                    const data = await resp.json();
                    if (!resp.ok || data.error) {
                        throw new Error(data.error || 'Could not dismiss notification');
                    }
                    if (type === 'force-stop-video-delivery') {
                        showAdminToast('Video preparation stopped. Site update can continue.', 'success');
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

                if (!master.exists) {
                    const warning = String(master.prepare_warning || '').trim();
                    const title = warning !== ''
                        ? warning
                        : 'Master file is not available for this upload yet';
                    badges.push(`<span class="badge audit-status-badge status-warning media-file-badge" title="${bandpromoAdminEscapeHtml(title)}">Master pending</span>`);
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
                    return mediaReferenceFilters.visual || 'all';
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
                    badges.push('<span class="badge audit-status-badge status-warning media-file-badge" title="Not used on a track, gallery, page, or brand slot">Unused</span>');
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
                const inUse = Number(info.reference_count || 0) > 0 || (references.length > 0 && info.orphan !== true);

                if (inUse) {
                    badges.push('<span class="badge audit-status-badge status-ok media-file-badge" title="Used on a track, gallery, page, or brand slot">In use</span>');
                } else {
                    badges.push('<span class="badge audit-status-badge status-warning media-file-badge" title="Not used — safe to delete if you do not need it">Unused</span>');
                }

                return badges.join(' ');
            }

            function matchesMediaReferenceFilter(type, file) {
                const filter = getMediaReferenceFilter(type);
                if (filter === 'all') {
                    return true;
                }

                const info = getFileReferenceInfo(file);
                if (filter === 'orphans' || filter === 'unused') {
                    return info.orphan === true || Number(info.reference_count || 0) <= 0;
                }
                if (filter === 'referenced') {
                    return Number(info.reference_count || 0) > 0;
                }

                return true;
            }

            function mediaNameFilterNeedle(type) {
                return String(poolNameFilters[type] || '').trim().toLowerCase();
            }

            function mediaOperatorSearchHaystack(type, file) {
                const parts = [];
                if (type === 'audio') {
                    parts.push(formatAudioListRowBody(file));
                    parts.push(file?.display_title);
                    parts.push(file?.display_artist);
                    parts.push(file?.display_version);
                    parts.push(file?.release_title);
                    return parts.filter(Boolean).join(' ').toLowerCase();
                }

                parts.push(poolAssetHeadline(type, file));
                parts.push(...poolAssetReferenceLines(file));
                parts.push(file?.release_title);
                parts.push(file?.brand_title);
                return parts.filter(Boolean).join(' ').toLowerCase();
            }

            function matchesMediaSearch(type, file, needle) {
                const query = String(needle || '').trim().toLowerCase();
                if (query === '') {
                    return true;
                }
                return mediaOperatorSearchHaystack(type, file).includes(query);
            }

            function matchesMediaNameFilter(type, file) {
                return matchesMediaSearch(type, file, mediaNameFilterNeedle(type));
            }

            function filterReferencedMediaFiles(type, files) {
                const list = Array.isArray(files) ? files : [];
                let filtered = list;
                if (poolPanelTypes.has(type)) {
                    const typeFilter = poolTypeFilters[type] || 'all';
                    if (typeFilter !== 'all') {
                        filtered = filtered.filter((file) => poolAssetKind(type, file) === typeFilter);
                    }
                }
                filtered = filtered.filter((file) => matchesMediaNameFilter(type, file));
                return filtered;
            }

            function syncMediaBrandFilterUi() {
                populateBrandFilterSelects();
                syncBrandFilterUi();
            }

            async function ensureBrandFilterCatalog() {
                if (brandFilterCatalogLoaded) {
                    return brandFilterCatalog;
                }
                try {
                    const response = await fetch('/biblioteca/get-themes.php', { credentials: 'same-origin' });
                    const data = await response.json();
                    if (!response.ok || !data || data.ok === false) {
                        throw new Error((data && data.error) || 'Could not load brands');
                    }
                    brandFilterCatalog = Array.isArray(data.themes) ? data.themes : [];
                } catch (error) {
                    brandFilterCatalog = [];
                }
                brandFilterCatalogLoaded = true;
                syncMediaBrandFilterUi();
                return brandFilterCatalog;
            }

            function normalizePoolBrandFilter(value) {
                const next = String(value || 'all').trim() || 'all';
                if (next === 'all' || next === 'orphans') {
                    return next;
                }
                return next;
            }

            function brandFilterOptionsHtml() {
                const options = [
                    '<option value="all">All files</option>',
                    '<option value="orphans">Orphans</option>',
                ];
                const brands = Array.isArray(brandFilterCatalog) ? brandFilterCatalog.slice() : [];
                brands.sort((left, right) => String(left?.title || left?.name || left?.id || '').localeCompare(
                    String(right?.title || right?.name || right?.id || ''),
                    undefined,
                    { sensitivity: 'base' }
                ));
                brands.forEach((brand) => {
                    const id = String(brand?.id || '').trim();
                    if (!id) {
                        return;
                    }
                    const label = String(brand?.title || brand?.name || id).trim() || id;
                    options.push(`<option value="${bandpromoAdminEscapeHtml(id)}">${bandpromoAdminEscapeHtml(label)}</option>`);
                });
                return options.join('');
            }

            function populateBrandFilterSelects() {
                document.querySelectorAll('[data-media-brand-filter]').forEach((select) => {
                    const current = normalizePoolBrandFilter(select.value || poolBrandFilter || 'all');
                    select.innerHTML = brandFilterOptionsHtml();
                    const known = current === 'all'
                        || current === 'orphans'
                        || brandFilterCatalog.some((brand) => String(brand?.id || '') === current);
                    const allowed = known ? current : 'all';
                    select.value = allowed;
                    if (select.value !== allowed) {
                        select.value = 'all';
                    }
                });
            }

            function syncBrandFilterUi() {
                document.querySelectorAll('[data-media-brand-filter]').forEach((select) => {
                    const value = normalizePoolBrandFilter(poolBrandFilter);
                    if (![...select.options].some((option) => option.value === value)) {
                        populateBrandFilterSelects();
                    }
                    select.value = value;
                    if (select.value !== value) {
                        select.value = 'all';
                    }
                });
            }

            function setPoolBrandFilter(nextValue) {
                poolBrandFilter = normalizePoolBrandFilter(nextValue);
                // Keep legacy per-panel map in sync for any remaining callers.
                poolBrandFilters.special = poolBrandFilter;
                poolBrandFilters.sfx = poolBrandFilter;
                syncBrandFilterUi();
                if (activeMediaPanel === 'special' || activeMediaPanel === 'sfx') {
                    loadMediaList(activeMediaPanel);
                }
                if (mediaPickerState
                    && (mediaPickerState.activeTarget === 'special' || mediaPickerState.activeTarget === 'sfx')
                ) {
                    renderMediaPickerList(mediaPickerState.activeTarget);
                }
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

            function formatBrandContextMarkup(file) {
                if (file?.brand_orphan === true || String(file?.brand_id || '').trim() === '') {
                    return '<span class="media-file-release-content">Orphan</span>';
                }

                const brandTitle = String(file?.brand_title || '').trim();
                if (brandTitle === '') {
                    return '';
                }

                return `<span class="media-file-release-content">${bandpromoAdminEscapeHtml(brandTitle)}</span>`;
            }

            function formatAudioReleaseContextMarkup(file) {
                if (file?.release_orphan === true) {
                    return '<span class="media-file-release-content">Orphan</span>';
                }

                const releaseDate = String(file?.release_date || '').trim();
                const releaseTitle = String(file?.release_title || '').trim();
                if (releaseDate === '' && releaseTitle === '') {
                    return '';
                }

                if (releaseDate !== '' && releaseTitle !== '') {
                    return `<span class="media-file-release-content">${bandpromoAdminEscapeHtml(releaseDate)} · ${bandpromoAdminEscapeHtml(releaseTitle)}</span>`;
                }

                if (releaseTitle !== '') {
                    return `<span class="media-file-release-content">${bandpromoAdminEscapeHtml(releaseTitle)}</span>`;
                }

                return `<span class="media-file-release-content">${bandpromoAdminEscapeHtml(releaseDate)}</span>`;
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
                if (String(cached.title || '').trim() || String(cached.version || '').trim()) {
                    const parts = audioMasterTitlePartsFromDetail(cached);
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
                    if (mediaFile.audio_master && mediaFile.audio_master.exists) {
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
                        downloadVariant: 'master',
                        downloadAvailable: false,
                    };
                }

                return {
                    name: poolPanelTypes.has(type)
                        ? poolAssetHeadline(type, mediaFile)
                        : 'Media asset',
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
                const releaseHtml = formatAudioReleaseContextMarkup(source);
                const releaseTrail = releaseHtml !== '' ? ` ${releaseHtml}` : '';
                return `<span class="media-file-name-wrap"><span class="media-file-name"><strong class="media-file-name-text">${labelHtml}</strong>${releaseTrail}</span><span class="media-file-meta">${formatAudioMasterBadges(source)}</span></span>`;
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
                const titleParts = audioMasterTitlePartsFromDetail(cached);
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
                { key: 'title', label: 'Title', health: 'title', requirement: 'required', inputType: 'text', read: (detail) => String(audioMasterTitlePartsFromDetail(detail).title || '').trim() },
                { key: 'version', label: 'Version', health: '', requirement: 'optional', inputType: 'text', read: (detail) => String(audioMasterTitlePartsFromDetail(detail).version || '').trim() },
                { key: 'album', label: 'Release', health: 'release', requirement: 'optional', inputType: 'text', read: (detail) => String(detail.album || '').trim() },
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
                if (appliedMediaFocusFromQuery || !pendingMediaFocusFromQuery) {
                    return;
                }
                if (type !== 'audio' && type !== 'visual' && type !== 'special' && type !== 'sfx') {
                    return;
                }
                const listEl = document.getElementById('filelist-' + type);
                if (!listEl) {
                    return;
                }
                const rows = Array.from(listEl.querySelectorAll('.media-file-row[data-file], .visual-pool-card[data-file]'));
                const targetRow = rows.find((row) => {
                    const key = String(row.dataset.file || '');
                    if (key === pendingMediaFocusFromQuery) {
                        return true;
                    }
                    if (type === 'visual') {
                        return selectionDisplayName('visual', key) === pendingMediaFocusFromQuery;
                    }
                    return false;
                });
                if (!targetRow) {
                    return;
                }
                appliedMediaFocusFromQuery = true;
                rows.forEach((row) => {
                    row.classList.remove('media-file-row-focus');
                    row.classList.remove('visual-pool-card-focus');
                });
                targetRow.classList.add('media-file-row-focus');
                targetRow.classList.add('visual-pool-card-focus');
                targetRow.scrollIntoView({ block: 'center', behavior: 'smooth' });
                if (type === 'visual' || type === 'special' || type === 'sfx') {
                    openPoolAssetModal(type, String(targetRow.dataset.file || ''));
                }
            }

            function inferMediaTargetFromPath(path, allowedTargets) {
                const targets = Array.isArray(allowedTargets) ? allowedTargets : [];
                if (targets.includes('visual')) {
                    const raw = String(path || '');
                    if (
                        raw.startsWith('/media/img/')
                        || raw.startsWith('/media/photo/')
                        || raw.startsWith('/media/video/')
                    ) {
                        return 'visual';
                    }
                }
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

                if (type === 'special' || type === 'sfx') {
                    const brand = String(options.brand || poolBrandFilter || 'all').trim();
                    if (brand && brand !== 'all') {
                        params.set('brand', brand);
                    }
                } else {
                    const release = String(options.release || poolReleaseFilter || 'all').trim();
                    if (release && release !== 'all') {
                        params.set('release', release);
                    }
                }

                const includeHidden = options.includeHidden === true;
                if (includeHidden) {
                    params.set('include_hidden', '1');
                }

                return '/biblioteca/list-media.php?' + params.toString();
            }

            function releaseFilterOptionsHtml() {
                const options = [
                    '<option value="all">All files</option>',
                    '<option value="orphans">Orphans</option>',
                ];
                const releases = Array.isArray(releasesCatalog) ? releasesCatalog.slice() : [];
                releases.sort((left, right) => {
                    const leftDate = String(left?.release_date || '');
                    const rightDate = String(right?.release_date || '');
                    if (leftDate !== rightDate) {
                        return rightDate.localeCompare(leftDate);
                    }
                    return String(left?.title || left?.id || '').localeCompare(
                        String(right?.title || right?.id || ''),
                        undefined,
                        { sensitivity: 'base' }
                    );
                });
                releases.forEach((release) => {
                    const id = String(release?.id || '').trim();
                    if (!id) {
                        return;
                    }
                    const title = String(release?.title || id).trim() || id;
                    const date = String(release?.release_date || '').trim();
                    const label = date !== '' ? `${date} · ${title}` : title;
                    options.push(`<option value="${bandpromoAdminEscapeHtml(id)}">${bandpromoAdminEscapeHtml(label)}</option>`);
                });
                return options.join('');
            }

            function normalizePoolReleaseFilter(value) {
                const next = String(value || 'all').trim() || 'all';
                if (next === 'all' || next === 'orphans') {
                    return next;
                }
                // Legacy aggregate option — treat as all and let operators pick a release.
                if (next === 'releases') {
                    return 'all';
                }
                return next;
            }

            function populateReleaseFilterSelects() {
                document.querySelectorAll('[data-media-release-filter], [data-pool-release-filter]').forEach((select) => {
                    const current = normalizePoolReleaseFilter(select.value || poolReleaseFilter || 'all');
                    select.innerHTML = releaseFilterOptionsHtml();
                    const known = current === 'all'
                        || current === 'orphans'
                        || releasesCatalog.some((release) => String(release?.id || '') === current);
                    const allowed = known ? current : 'all';
                    select.value = allowed;
                    if (select.value !== allowed) {
                        select.value = 'all';
                    }
                });
            }

            function syncReleaseFilterUi() {
                document.querySelectorAll('[data-media-release-filter], [data-pool-release-filter]').forEach((select) => {
                    const value = normalizePoolReleaseFilter(poolReleaseFilter);
                    if (![...select.options].some((option) => option.value === value)) {
                        populateReleaseFilterSelects();
                    }
                    select.value = value;
                    if (select.value !== value) {
                        select.value = 'all';
                    }
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
                        const pathType = resolveFileIntakeBucket(file, type) || type;
                        const caption = type === 'audio'
                            ? (formatAudioListRowBody(audioFileForDisplay(file)) || 'Untitled')
                            : (poolPanelTypes.has(type)
                                ? poolAssetHeadline(type, file)
                                : 'Preview');
                        if (!isVisualMediaRow(type, file)) {
                            return {
                                src: buildMediaPath(pathType, file.name),
                                name: caption,
                                fileKey: file.name,
                                type: 'image',
                            };
                        }

                        const previewUrl = videoPreviewUrl(file);
                        const posterUrl = videoPosterUrl(file);
                        if (previewUrl) {
                            return {
                                src: previewUrl,
                                name: caption,
                                fileKey: file.name,
                                type: 'video',
                                poster: posterUrl,
                            };
                        }
                        if (posterUrl) {
                            return {
                                src: posterUrl,
                                name: caption,
                                fileKey: file.name,
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
                // Default lite everywhere — full only when explicitly requested (bell / Deliverables).
                return 'lite';
            }

            function buildOperatorNotificationsUrl(scope, options = {}) {
                const params = new URLSearchParams();
                params.set('scope', scope);
                if (scope === 'full' && (options.inventory === true || isDeliverablesViewActive())) {
                    params.set('inventory', '1');
                }
                if (options.forcePackage === true) {
                    params.set('force_package', '1');
                }
                return '/biblioteca/get-operator-notifications.php?' + params.toString();
            }

            async function refreshBuildRequiredState(options = {}) {
                const scope = resolveNotificationScope(options);
                try {
                    const resp = await fetch(buildOperatorNotificationsUrl(scope, options));
                    const data = await resp.json();
                    if (!resp.ok || !data || data.ok !== true) return;

                    const state = data.build_required_state || {};
                    if (Object.prototype.hasOwnProperty.call(data, 'metadata_validation')) {
                        latestBuildValidation = data.metadata_validation || null;
                    }
                    // Lite polls omit welcome — never wipe cached setup_complete with null.
                    if (data.welcome) {
                        latestWelcomeState = data.welcome;
                    }
                    if (data.package_update) {
                        latestPackageUpdate = data.package_update;
                    }
                    latestBackgroundTasks = data.background_tasks || null;
                    setBuildRequiredNudge(data.build_required === true, state.reasons || [], state.action || 'none', state.tasks || []);
                    renderOperatorNotifications(state, latestBuildValidation, latestWelcomeState, latestPackageUpdate, latestBackgroundTasks, data.uncatalogued_audio_failures || []);
                    updateBackgroundTaskPolling(latestBackgroundTasks);
                    if (Object.prototype.hasOwnProperty.call(data, 'publish_status') || Object.prototype.hasOwnProperty.call(data, 'catalog_repair')) {
                        renderPublishStatusSummary(data.publish_status || null, data.catalog_repair || null);
                    }

                    const videoTasks = Array.isArray(latestBackgroundTasks?.items)
                        ? latestBackgroundTasks.items.filter((item) => item && item.task === 'video-delivery')
                        : [];
                    const videoRunning = videoTasks.some((item) => String(item.status || '') === 'running');
                    if (adminFilesTabActive && activeMediaPanel === 'visual') {
                        if (window._videoDeliveryWasRunning && !videoRunning) {
                            loadMediaList('visual');
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
                if (Number.isFinite(totalCount) && totalCount !== count) {
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

                const kind = String(type || 'success').trim().toLowerCase() || 'success';
                const text = String(message || '').trim();
                if (text === '') {
                    return;
                }

                const toast = document.createElement('div');
                toast.className = `admin-toast ${kind}`;
                toast.setAttribute('role', kind === 'error' || kind === 'warning' ? 'alert' : 'status');

                const messageEl = document.createElement('div');
                messageEl.className = 'admin-toast-message';
                messageEl.textContent = text;
                toast.appendChild(messageEl);

                const isSticky = kind === 'warning' || kind === 'error';
                let hideTimer = null;
                const dismissToast = () => {
                    if (hideTimer) {
                        window.clearTimeout(hideTimer);
                        hideTimer = null;
                    }
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-4px)';
                    toast.style.transition = 'opacity 150ms ease, transform 150ms ease';
                    window.setTimeout(() => {
                        toast.remove();
                    }, 180);
                };

                if (isSticky) {
                    const dismissBtn = document.createElement('button');
                    dismissBtn.type = 'button';
                    dismissBtn.className = 'admin-toast-dismiss';
                    dismissBtn.setAttribute('aria-label', 'Dismiss notification');
                    dismissBtn.textContent = '×';
                    dismissBtn.addEventListener('click', dismissToast);
                    toast.appendChild(dismissBtn);
                }

                toastHost.appendChild(toast);

                // Success: brief confirmation. Warning/error: stay long enough to read, plus dismiss.
                const durationMs = isSticky
                    ? Math.min(20000, Math.max(10000, 80 * text.length))
                    : 4500;
                hideTimer = window.setTimeout(dismissToast, durationMs);
            }
            window.bandpromoShowAdminToast = showAdminToast;

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
                return Array.from(listEl.querySelectorAll('.media-file-row[data-file], .visual-pool-card[data-file]'));
            }

            function pruneMediaSelection(type, files) {
                const state = getMediaSelectionState(type);
                const allowed = new Set(
                    (Array.isArray(files) ? files : [])
                        .map((file) => mediaFileSelectionKey(type, file))
                        .filter(Boolean)
                );
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
                const filesByKey = new Map(
                    getMediaFileState(type).map((file) => [mediaFileSelectionKey(type, file), file])
                );
                return getSelectedMediaFiles(type)
                    .map((key) => filesByKey.get(key))
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

            async function submitMediaDownloadRequestForBucket(type, variant, files) {
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

            async function submitMediaDownloadRequest(type, variant, files) {
                const selectedFiles = Array.isArray(files) ? files.filter(Boolean) : [];
                if (!type || !selectedFiles.length) {
                    return;
                }

                if (type === 'visual') {
                    const groups = groupSelectionKeysByBucket('visual', selectedFiles);
                    for (const [bucket, names] of groups.entries()) {
                        await submitMediaDownloadRequestForBucket(bucket, variant, names);
                    }
                    return;
                }

                await submitMediaDownloadRequestForBucket(type, variant, selectedFiles);
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
                    row.classList.toggle('visual-pool-card-selected', selected);
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

            function poolAssetKind(panelType, file) {
                const declared = String(file?.media_type || '').trim();
                if (declared === 'video' || declared === 'audio' || declared === 'image') {
                    return declared;
                }
                if (isVideo(file?.name)) {
                    return 'video';
                }
                if (isAudio(file?.name)) {
                    return 'audio';
                }
                if (isImage(file?.name)) {
                    return 'image';
                }
                return panelType === 'special' ? 'other' : 'image';
            }

            function visualAssetKind(file) {
                return poolAssetKind('visual', file);
            }

            function poolAssetKindLabel(kind, panelType = '') {
                if (panelType === 'special') {
                    if (kind === 'video') return 'Living';
                    if (kind === 'audio') return 'Audio';
                    if (kind === 'image') return 'Still';
                    return 'File';
                }
                if (kind === 'video') return 'Video';
                if (kind === 'audio') return 'Audio';
                if (kind === 'image') return 'Image';
                return 'File';
            }

            function poolAssetHeadline(panelType, file) {
                const kind = poolAssetKind(panelType, file);
                const info = getFileReferenceInfo(file);
                const references = Array.isArray(info.references) ? info.references : [];
                const kinds = new Set(references.map((reference) => String(reference.kind || '')));
                const role = String(info.role || '').trim();

                if (panelType === 'sfx') {
                    if (kinds.has('welcome-audio')) {
                        return 'Welcome sound';
                    }
                    if (kinds.has('loggedin-audio')) {
                        return 'Logged-in sound';
                    }
                    if (info.orphan === true || Number(info.reference_count || 0) === 0) {
                        return 'Unused sound effect';
                    }
                    return 'Sound effect in use';
                }

                if (panelType === 'special') {
                    if (role === 'brand-logo' || kinds.has('brand-logo')) {
                        return 'Logo';
                    }
                    if (role === 'share-image' || kinds.has('share-image')) {
                        return 'Share image';
                    }
                    if (role === 'theme-cover' || kinds.has('theme-cover')) {
                        return 'Cover image';
                    }
                    if (role === 'theme-background' || kinds.has('theme-background')) {
                        return 'Still background';
                    }
                    if (role === 'theme-background-video' || kinds.has('theme-background-video')) {
                        return 'Living background';
                    }
                    if (info.orphan === true || Number(info.reference_count || 0) === 0) {
                        return 'Unused brand asset';
                    }
                    return 'Brand asset in use';
                }

                if (info.role === 'track-cover' || kinds.has('track-cover')) {
                    return 'Track cover';
                }
                if (kinds.has('track-living-cover')) {
                    return 'Living cover';
                }
                if (kinds.has('gallery-item')) {
                    return kind === 'video' ? 'Gallery video' : 'Gallery image';
                }
                if ([...kinds].some((value) => value.startsWith('theme-') || value === 'share-image' || value === 'brand-logo')) {
                    return 'Brand media';
                }
                if (info.orphan === true || Number(info.reference_count || 0) === 0) {
                    return kind === 'video' ? 'Unused video' : 'Unused image';
                }
                return kind === 'video' ? 'Video in use' : 'Image in use';
            }

            function visualAssetHeadline(file) {
                return poolAssetHeadline('visual', file);
            }

            function poolAssetStatusPills(file) {
                const info = getFileReferenceInfo(file);
                const pills = [];
                if (file.delivery_running) {
                    pills.push({ text: 'Preparing', className: 'is-warning' });
                } else if (file.delivery_pending) {
                    pills.push({ text: 'Queued', className: 'is-warning' });
                } else if (Number(info.reference_count || 0) > 0 || (info.orphan !== true && Array.isArray(info.references) && info.references.length > 0)) {
                    pills.push({ text: 'In use', className: 'is-ok' });
                } else {
                    // Catalogue "Orphans" means no release — never reuse that word for unused assets.
                    pills.push({ text: 'Unused', className: 'is-warning' });
                }
                return pills;
            }

            function visualAssetStatusPills(file) {
                return poolAssetStatusPills(file);
            }

            function visualHoverPreviewAllowed() {
                try {
                    return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                } catch (error) {
                    return true;
                }
            }

            function ensureVisualHoverVideoSource(video) {
                if (!(video instanceof HTMLVideoElement)) {
                    return;
                }
                const pendingSrc = String(video.dataset.src || '').trim();
                if (pendingSrc && !video.getAttribute('src')) {
                    video.src = pendingSrc;
                }
                video.muted = true;
                video.defaultMuted = true;
                video.loop = true;
                video.playsInline = true;
                video.setAttribute('muted', '');
                video.setAttribute('loop', '');
                video.setAttribute('playsinline', '');
            }

            function playVisualHoverVideo(video) {
                if (!(video instanceof HTMLVideoElement) || !visualHoverPreviewAllowed()) {
                    return;
                }
                ensureVisualHoverVideoSource(video);
                const playPromise = video.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(() => {});
                }
            }

            function stopVisualHoverVideo(video) {
                if (!(video instanceof HTMLVideoElement)) {
                    return;
                }
                try {
                    video.pause();
                } catch (error) {
                    // Ignore pause failures on detached nodes.
                }
                try {
                    if (video.readyState >= 1) {
                        video.currentTime = 0;
                    }
                } catch (error) {
                    // Ignore seek failures before metadata is ready.
                }
            }

            function setVisualHoverPreviewActive(host, active) {
                if (!(host instanceof Element)) {
                    return;
                }
                const video = host.querySelector('video[data-src], video.visual-pool-card-video, video.visual-asset-loop');
                host.classList.toggle('is-playing-preview', active === true);
                if (active) {
                    playVisualHoverVideo(video);
                } else {
                    stopVisualHoverVideo(video);
                }
            }

            function poolAssetThumbInnerHtml(panelType, file, pathType) {
                const kind = poolAssetKind(panelType, file);
                const url = buildMediaUrl(pathType, file.name);
                const poster = videoPosterUrl(file);
                const preview = videoPreviewUrl(file);
                if (kind === 'image') {
                    return `<img src="${url}" alt="" loading="lazy">`;
                }
                if (kind === 'audio') {
                    return `<span class="visual-pool-card-thumb-placeholder is-audio" title="${panelType === 'sfx' ? 'Sound effect' : 'Audio brand asset'}">♪</span>`;
                }
                if (kind === 'video') {
                    if (file.delivery_running || (file.delivery_pending && !poster && !preview)) {
                        return `<span class="visual-pool-card-thumb-placeholder is-preparing" title="Preparing in background">⏳</span>`;
                    }
                    if (preview) {
                        if (poster) {
                            return `<img class="visual-pool-card-still" src="${poster}" alt="" loading="lazy"><video class="visual-pool-card-video" data-src="${preview}" poster="${poster}" muted loop playsinline preload="none"></video>`;
                        }
                        return `<video class="visual-pool-card-video visual-pool-card-video--solo" src="${preview}" muted loop playsinline preload="metadata"></video>`;
                    }
                    if (poster) {
                        return `<img class="visual-pool-card-still" src="${poster}" alt="" loading="lazy">`;
                    }
                    // Brand video in special is served directly from media/special (no delivery pipeline).
                    if (panelType === 'special') {
                        return `<video class="visual-pool-card-video visual-pool-card-video--solo" src="${url}" muted loop playsinline preload="metadata"></video>`;
                    }
                    return `<span class="visual-pool-card-thumb-placeholder" title="Video waiting for preparation">▶</span>`;
                }
                return `<span class="visual-pool-card-thumb-placeholder" title="File">📄</span>`;
            }

            function visualAssetThumbInnerHtml(file, pathType) {
                return poolAssetThumbInnerHtml('visual', file, pathType);
            }

            function poolAssetReferenceLines(file) {
                const info = getFileReferenceInfo(file);
                const references = Array.isArray(info.references) ? info.references : [];
                if (!references.length) {
                    if (info.role === 'track-cover') {
                        return ['Assigned as a track cover'];
                    }
                    return [];
                }
                const kindLabels = {
                    'track-cover': 'Track cover',
                    'track-living-cover': 'Living cover',
                    'gallery-item': 'Gallery',
                    'theme-cover': 'Brand cover',
                    'theme-background': 'Still background',
                    'theme-background-video': 'Living background',
                    'share-image': 'Share image',
                    'brand-logo': 'Logo',
                    'welcome-audio': 'Welcome audio',
                    'loggedin-audio': 'Logged-in audio',
                    'release-fallback': 'Release fallback',
                };
                return references.slice(0, 8).map((reference) => {
                    const kind = kindLabels[String(reference.kind || '')] || String(reference.kind || 'Reference');
                    const label = String(reference.label || '').trim();
                    return label ? `${kind}: ${label}` : kind;
                });
            }

            function visualAssetReferenceLines(file) {
                return poolAssetReferenceLines(file);
            }

            function syncPoolTypeFilterUi(panelType = null) {
                const panels = panelType ? [panelType] : ['visual', 'special', 'sfx'];
                panels.forEach((panel) => {
                    const current = poolTypeFilters[panel] || 'all';
                    document.querySelectorAll(`[data-pool-type-filter][data-pool-panel="${panel}"]`).forEach((el) => {
                        const value = String(el.getAttribute('data-pool-type-filter') || el.value || 'all');
                        const active = value === current;
                        el.classList.toggle('is-active', active);
                        if (el.tagName === 'BUTTON') {
                            el.setAttribute('aria-pressed', active ? 'true' : 'false');
                        } else {
                            el.value = current;
                        }
                    });
                });
            }

            function syncPoolViewUi(panelType = null) {
                const panels = panelType ? [panelType] : ['visual', 'special', 'sfx'];
                panels.forEach((panel) => {
                    if (panel === 'sfx') {
                        poolViewModes.sfx = 'list';
                    }
                    const mode = panel === 'sfx'
                        ? 'list'
                        : (poolViewModes[panel] === 'list' ? 'list' : 'grid');
                    const listEl = document.getElementById('filelist-' + panel);
                    if (listEl) {
                        listEl.classList.toggle('visual-pool-list--grid', mode === 'grid');
                        listEl.classList.toggle('visual-pool-list--list', mode === 'list');
                        listEl.dataset.visualLayout = mode;
                    }
                    document.querySelectorAll(`[data-pool-view][data-pool-panel="${panel}"]`).forEach((button) => {
                        const value = String(button.getAttribute('data-pool-view') || 'grid');
                        const active = value === mode;
                        button.classList.toggle('is-active', active);
                        button.setAttribute('aria-pressed', active ? 'true' : 'false');
                    });
                });
            }

            function setPoolViewMode(panelType, nextValue) {
                if (!poolPanelTypes.has(panelType)) {
                    return;
                }
                poolViewModes[panelType] = panelType === 'sfx'
                    ? 'list'
                    : (nextValue === 'list' ? 'list' : 'grid');
                if (panelType !== 'sfx') {
                    const storageKey = panelType === 'special'
                        ? 'bandpromo_brand_pool_view'
                        : 'bandpromo_visual_pool_view';
                    try {
                        window.localStorage.setItem(storageKey, poolViewModes[panelType]);
                    } catch (error) {
                        // Ignore storage failures; view still works for this session.
                    }
                }
                syncPoolViewUi(panelType);
            }

            function buildPoolCardMarkup(panelType, file, selection) {
                const pathType = resolveFileIntakeBucket(file, panelType) || (panelType === 'visual' ? 'illustrations' : panelType);
                const selectionKey = mediaFileSelectionKey(panelType, file);
                const safeKey = selectionKey.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                const kind = poolAssetKind(panelType, file);
                const selected = selection.selected.has(selectionKey);
                const headline = poolAssetHeadline(panelType, file);
                const sizeLabel = fmtSize(Number(file.size) || 0);
                const pills = poolAssetStatusPills(file);
                const releaseTrail = (panelType === 'special' || panelType === 'sfx')
                    ? formatBrandContextMarkup(file)
                    : formatAudioReleaseContextMarkup(file);
                const titleHtml = `<span class="media-file-name visual-pool-card-meta-title"><strong class="media-file-name-text">${bandpromoAdminEscapeHtml(headline)}</strong>${releaseTrail !== '' ? ` ${releaseTrail}` : ''}</span>`;
                const statusHtml = pills.length
                    ? `<span class="visual-pool-status-stack">${pills.map((pill) =>
                        `<span class="visual-pool-status-pill ${pill.className || ''}">${bandpromoAdminEscapeHtml(pill.text)}</span>`
                    ).join('')}</span>`
                    : '';
                const typeLabel = poolAssetKindLabel(kind, panelType);
                const selectLabel = `Select ${typeLabel.toLowerCase()}`;
                return `<article class="visual-pool-card${selected ? ' media-file-row-selected visual-pool-card-selected' : ''}" data-file="${bandpromoAdminEscapeHtml(selectionKey)}" data-intake-bucket="${bandpromoAdminEscapeHtml(pathType)}" data-media-type="${kind}">
                    <label class="media-file-select-wrap visual-pool-card-select" title="${selectLabel}" onclick="event.stopPropagation()">
                        <input type="checkbox" class="media-file-select" data-target="${bandpromoAdminEscapeHtml(panelType)}" data-file="${bandpromoAdminEscapeHtml(selectionKey)}" ${selected ? 'checked' : ''} aria-label="${selectLabel}">
                    </label>
                    <button type="button" class="visual-pool-card-thumb" data-pool-open="${bandpromoAdminEscapeHtml(selectionKey)}" data-pool-panel="${bandpromoAdminEscapeHtml(panelType)}" aria-label="Open ${typeLabel.toLowerCase()} details">
                        ${poolAssetThumbInnerHtml(panelType, file, pathType)}
                        ${statusHtml}
                        <span class="visual-pool-type-badge">${typeLabel}</span>
                    </button>
                    <div class="visual-pool-card-meta">
                        <div class="visual-pool-card-meta-copy">
                            ${titleHtml}
                            <span class="visual-pool-card-meta-sub">${typeLabel} · ${bandpromoAdminEscapeHtml(sizeLabel)}</span>
                            <span class="media-file-meta">${formatMediaReferenceBadges(panelType, file)}</span>
                        </div>
                        <div class="visual-pool-card-actions">
                            <button type="button" class="icon-btn media-action-btn media-action-good" title="Download" onclick="event.stopPropagation(); submitMediaDownloadRequest('${pathType}', 'original', ['${String(file.name).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'])">⬇</button>
                            <button type="button" class="icon-btn media-action-btn media-action-danger" title="Delete" onclick="event.stopPropagation(); openDeleteModal('${panelType}', '${safeKey}')">🗑️</button>
                        </div>
                    </div>
                </article>`;
            }

            function buildVisualPoolCardMarkup(file, selection) {
                return buildPoolCardMarkup('visual', file, selection);
            }

            function renderPoolList(panelType, files, selection) {
                const listEl = document.getElementById('filelist-' + panelType);
                if (!listEl) {
                    return;
                }
                syncPoolViewUi(panelType);
                setAdminPreviewItems(files, panelType);
                listEl.innerHTML = files.map((file) => buildPoolCardMarkup(panelType, file, selection)).join('');
            }

            function renderVisualPoolList(files, selection) {
                renderPoolList('visual', files, selection);
            }

            function findPoolAssetByKey(panelType, selectionKey) {
                const key = String(selectionKey || '');
                return getMediaFileState(panelType).find((file) => mediaFileSelectionKey(panelType, file) === key) || null;
            }

            function findVisualAssetByKey(selectionKey) {
                return findPoolAssetByKey('visual', selectionKey);
            }

            function openPoolAssetModal(panelType, selectionKey) {
                if (!poolPanelTypes.has(panelType)) {
                    return;
                }
                const file = findPoolAssetByKey(panelType, selectionKey);
                const modal = document.getElementById('poolAssetModal');
                const previewEl = document.getElementById('poolAssetPreview');
                const titleEl = document.getElementById('poolAssetTitle');
                const badgesEl = document.getElementById('poolAssetBadges');
                const detailsEl = document.getElementById('poolAssetDetails');
                const downloadBtn = document.getElementById('poolAssetDownloadBtn');
                const deleteBtn = document.getElementById('poolAssetDeleteBtn');
                if (!file || !modal || !previewEl || !titleEl || !badgesEl || !detailsEl) {
                    return;
                }

                activePoolAsset = {
                    panel: panelType,
                    key: mediaFileSelectionKey(panelType, file),
                };
                const pathType = resolveFileIntakeBucket(file, panelType) || (panelType === 'visual' ? 'illustrations' : panelType);
                const kind = poolAssetKind(panelType, file);
                const info = getFileReferenceInfo(file);
                const referenceLines = poolAssetReferenceLines(file);
                const typeLabel = poolAssetKindLabel(kind, panelType);

                titleEl.textContent = poolAssetHeadline(panelType, file);
                badgesEl.innerHTML = [
                    `<span class="badge audit-status-badge status-neutral media-file-badge">${typeLabel}</span>`,
                    formatMediaReferenceBadges(panelType, file),
                ].filter(Boolean).join(' ');

                if (kind === 'video') {
                    const previewUrl = videoPreviewUrl(file) || (panelType === 'special' ? buildMediaUrl(pathType, file.name) : '');
                    const posterUrl = videoPosterUrl(file);
                    if (previewUrl) {
                        if (posterUrl) {
                            previewEl.classList.add('visual-asset-modal-preview--video');
                            previewEl.innerHTML = `<img class="visual-asset-still" src="${posterUrl}" alt=""><video class="visual-asset-loop" data-src="${previewUrl}" poster="${posterUrl}" muted loop playsinline preload="metadata"></video>`;
                        } else {
                            previewEl.classList.add('visual-asset-modal-preview--video');
                            previewEl.innerHTML = `<video class="visual-asset-loop visual-asset-loop--solo" src="${previewUrl}" muted loop playsinline preload="metadata"></video>`;
                        }
                        ensureVisualHoverVideoSource(previewEl.querySelector('video.visual-asset-loop'));
                    } else if (posterUrl) {
                        previewEl.classList.remove('visual-asset-modal-preview--video');
                        previewEl.innerHTML = `<img src="${posterUrl}" alt="">`;
                    } else {
                        previewEl.classList.remove('visual-asset-modal-preview--video');
                        previewEl.innerHTML = `<span class="text-muted">${file.delivery_running || file.delivery_pending ? 'Video is still preparing for preview.' : 'No preview is ready yet.'}</span>`;
                    }
                } else if (kind === 'audio') {
                    previewEl.classList.remove('visual-asset-modal-preview--video');
                    previewEl.innerHTML = `<div class="visual-pool-card-thumb-placeholder is-audio is-modal" title="Audio brand asset">♪</div><audio controls src="${buildMediaUrl(pathType, file.name)}" preload="metadata"></audio>`;
                } else if (kind === 'image') {
                    previewEl.classList.remove('visual-asset-modal-preview--video');
                    previewEl.innerHTML = `<img src="${buildMediaUrl(pathType, file.name)}" alt="">`;
                } else {
                    previewEl.classList.remove('visual-asset-modal-preview--video');
                    previewEl.innerHTML = `<span class="text-muted">No preview for this file type.</span>`;
                }

                const detailRows = [
                    ['Type', typeLabel],
                    ['Size', fmtSize(Number(file.size) || 0)],
                    [(panelType === 'special' || panelType === 'sfx') ? 'Brand' : 'Catalogue',
                        (panelType === 'special' || panelType === 'sfx')
                            ? (file.brand_orphan === true || String(file.brand_id || '').trim() === ''
                                ? 'Orphan'
                                : (String(file.brand_title || '').trim() || 'Linked brand'))
                            : (file.release_orphan === true
                                ? 'Orphan'
                                : (String(file.release_title || '').trim() || 'Linked release'))],
                    ['Usage', info.orphan === true
                        ? 'Not referenced'
                        : (Number(info.reference_count || 0) > 0 ? 'In use' : 'Not referenced')],
                ];
                if (kind === 'video' && panelType === 'visual') {
                    let delivery = 'Ready';
                    if (file.delivery_running) delivery = 'Preparing in background';
                    else if (file.delivery_pending) delivery = 'Queued for preparation';
                    else if (!videoPreviewUrl(file) && !videoPosterUrl(file)) delivery = 'Waiting for preparation';
                    detailRows.push(['Delivery', delivery]);
                }
                if (referenceLines.length) {
                    detailRows.push(['References', referenceLines.join('<br>')]);
                }

                detailsEl.innerHTML = detailRows.map(([label, value]) => {
                    const isHtml = label === 'References';
                    return `<dt>${bandpromoAdminEscapeHtml(label)}</dt><dd>${isHtml ? value : bandpromoAdminEscapeHtml(String(value))}</dd>`;
                }).join('');

                if (downloadBtn) {
                    downloadBtn.onclick = () => {
                        submitMediaDownloadRequest(pathType, 'original', [file.name]);
                    };
                }
                if (deleteBtn) {
                    deleteBtn.onclick = () => {
                        closePoolAssetModal();
                        openDeleteModal(panelType, selectionKey);
                    };
                }

                modal.style.display = 'flex';
            }

            function openVisualAssetModal(selectionKey) {
                openPoolAssetModal('visual', selectionKey);
            }

            window.openPoolAssetModal = openPoolAssetModal;
            window.openVisualAssetModal = openVisualAssetModal;

            window.closePoolAssetModal = function() {
                const modal = document.getElementById('poolAssetModal');
                const previewEl = document.getElementById('poolAssetPreview');
                if (previewEl) {
                    setVisualHoverPreviewActive(previewEl, false);
                    previewEl.classList.remove('visual-asset-modal-preview--video');
                    previewEl.querySelectorAll('video').forEach((video) => {
                        stopVisualHoverVideo(video);
                    });
                    previewEl.querySelectorAll('audio').forEach((audio) => {
                        try {
                            audio.pause();
                        } catch (error) {
                            // Ignore pause failures.
                        }
                    });
                    previewEl.innerHTML = '';
                }
                if (modal) {
                    modal.style.display = 'none';
                }
                activePoolAsset = { panel: null, key: null };
            };
            window.closeVisualAssetModal = window.closePoolAssetModal;

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
                    if (poolPanelTypes.has(type)) {
                        await ensureBrandFilterCatalog();
                    }
                    const allFiles = await fetchMediaFiles(type);
                    mediaFilesState.set(type, allFiles);
                    const files = filterReferencedMediaFiles(type, allFiles);
                    pruneMediaSelection(type, allFiles);
                    const selection = getMediaSelectionState(type);
                    if (countEl) {
                        countEl.textContent = formatMediaCountSummary(files, type, {
                            totalCount: files.length !== allFiles.length ? allFiles.length : files.length,
                        });
                    }
                    if (!allFiles.length) {
                        listEl.innerHTML = '<span class="text-muted">No files yet.</span>';
                        syncMediaSelectionUi(type);
                        return;
                    }
                    if (!files.length) {
                        const livingFilter = type === 'special' && (poolTypeFilters.special || 'all') === 'video';
                        listEl.innerHTML = livingFilter
                            ? '<span class="text-muted">No living Brand assets yet. Upload a video here, or assign living video from <a href="?tab=files&amp;fpanel=visual">Files → Visual</a> in Content → Branding → Shell media.</span>'
                            : '<span class="text-muted">No files match the current filter.</span>';
                        syncMediaSelectionUi(type);
                        return;
                    }

                    if (poolPanelTypes.has(type)) {
                        renderPoolList(type, files, selection);
                        syncMediaSelectionUi(type);
                        maybeApplyMediaFocusFromQuery(type);
                        if (type === 'visual' && typeof refreshBuildRequiredState === 'function') {
                            refreshBuildRequiredState({ scope: 'lite' });
                        }
                        return;
                    }

                    setAdminPreviewItems(files, type);
                    listEl.innerHTML = files.map(f => {
                        const pathType = resolveFileIntakeBucket(f, type) || type;
                        const basePath = getMediaBasePath(pathType);
                        const selectionKey = mediaFileSelectionKey(type, f);
                        const safeName = f.name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                        const safeKey = selectionKey.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                        const url = buildMediaUrl(pathType, f.name);
                        const displaySource = type === 'audio' ? audioFileForDisplay(f) : f;
                        const display = getDisplayedMediaInfo(type, displaySource);
                        const selected = selection.selected.has(selectionKey);
                        const rowLabel = type === 'audio'
                            ? formatAudioListRowLabel(displaySource)
                            : String(display.name || f.name);
                        const rowIsVideo = isVisualMediaRow(type, f);
                        let thumb;
                        if (isImage(f.name)) {
                            thumb = `<img class="media-file-thumb" src="${url}" alt="" loading="lazy" onclick="event.stopPropagation(); openAdminPreview('${basePath}/${safeName}', '${safeName}')">`;
                        } else if (isVideo(f.name)) {
                            thumb = buildVideoThumbMarkup(type, f, safeName, basePath);
                        } else {
                            thumb = `<span class="media-file-icon">${extIcon(f.name)}</span>`;
                        }
                        const previewSrc = rowIsVideo
                            ? (videoPreviewUrl(f) || videoPosterUrl(f))
                            : `${basePath}/${safeName}`;
                        const preview = isPreviewable(f.name, f, type)
                            ? `<button class="icon-btn media-action-btn media-action-amber" title="Preview" onclick="event.stopPropagation(); openAdminPreview('${previewSrc}', '${safeName}')">👁️</button>`
                            : (rowIsVideo && (f.delivery_pending || f.delivery_running)
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
                        return `<div class="${rowClassName}" data-file="${bandpromoAdminEscapeHtml(selectionKey)}" data-intake-bucket="${bandpromoAdminEscapeHtml(pathType)}" ${rowAttributes}>
                            <div class="media-file-row-main">
                                <label class="media-file-select-wrap" title="Select for deletion" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="media-file-select" data-target="${bandpromoAdminEscapeHtml(type)}" data-file="${bandpromoAdminEscapeHtml(selectionKey)}" ${selected ? 'checked' : ''} aria-label="Select ${bandpromoAdminEscapeHtml(rowLabel)} for deletion">
                                </label>
                                ${thumb}
                                ${nameCell}
                                <span class="media-file-size">${fmtSize(display.size)}</span>
                                <span class="media-file-actions">${preview}${editAction}${downloadAction}<button class="icon-btn media-action-btn media-action-danger" title="Delete" onclick="event.stopPropagation(); openDeleteModal('${type}', '${safeKey}')">🗑️</button></span>
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
                if (type === 'visual') {
                    const allowed = new Set(['all', 'referenced', 'unused', 'orphans']);
                    mediaReferenceFilters.visual = allowed.has(nextValue) ? nextValue : 'all';
                } else if (type === 'illustrations' || type === 'photos' || type === 'video') {
                    // Legacy targets fold into Visual; keep unused synonym for orphans.
                    const allowed = new Set(['all', 'referenced', 'unused', 'orphans']);
                    mediaReferenceFilters[type === 'illustrations' ? 'visual' : type] = allowed.has(nextValue)
                        ? nextValue
                        : 'all';
                } else {
                    return;
                }
                syncMediaReferenceFilterUi();
                if (activeMediaPanel === type || (type === 'illustrations' && activeMediaPanel === 'visual')) {
                    loadMediaList(activeMediaPanel === 'visual' ? 'visual' : type);
                }
            }

            document.querySelectorAll('[data-media-filter-target]').forEach((select) => {
                select.addEventListener('change', () => {
                    const target = String(select.dataset.mediaFilterTarget || '');
                    setMediaReferenceFilter(target, String(select.value || 'all'));
                });
            });

            document.querySelectorAll('[data-media-brand-filter]').forEach((select) => {
                select.addEventListener('change', () => {
                    setPoolBrandFilter(String(select.value || 'all'));
                });
            });

            function setPoolTypeFilter(panelType, nextValue) {
                if (!poolPanelTypes.has(panelType)) {
                    return;
                }
                const allowed = panelType === 'special'
                    ? new Set(['all', 'image', 'video'])
                    : (panelType === 'sfx' ? new Set(['all', 'audio']) : new Set(['all', 'image', 'video']));
                poolTypeFilters[panelType] = allowed.has(nextValue) ? nextValue : 'all';
                syncPoolTypeFilterUi(panelType);
                if (activeMediaPanel === panelType) {
                    loadMediaList(panelType);
                }
            }

            document.querySelectorAll('[data-pool-type-filter]').forEach((el) => {
                const handler = () => {
                    const panel = String(el.getAttribute('data-pool-panel') || 'visual');
                    setPoolTypeFilter(panel, String(el.getAttribute('data-pool-type-filter') || el.value || 'all'));
                };
                if (el.tagName === 'BUTTON') {
                    el.addEventListener('click', handler);
                } else {
                    el.addEventListener('change', handler);
                }
            });

            document.querySelectorAll('[data-pool-view]').forEach((button) => {
                button.addEventListener('click', () => {
                    const panel = String(button.getAttribute('data-pool-panel') || 'visual');
                    setPoolViewMode(panel, String(button.getAttribute('data-pool-view') || 'grid'));
                });
            });

            syncPoolTypeFilterUi();
            syncPoolViewUi();

            function bindPoolListInteractions(panelType) {
                const listEl = document.getElementById('filelist-' + panelType);
                if (!listEl) {
                    return;
                }
                listEl.addEventListener('click', (event) => {
                    const openBtn = event.target.closest('[data-pool-open]');
                    if (!openBtn || !listEl.contains(openBtn)) {
                        return;
                    }
                    event.preventDefault();
                    const panel = String(openBtn.getAttribute('data-pool-panel') || panelType);
                    openPoolAssetModal(panel, String(openBtn.getAttribute('data-pool-open') || ''));
                });

                listEl.addEventListener('mouseover', (event) => {
                    const thumb = event.target.closest('.visual-pool-card-thumb');
                    if (!thumb || !listEl.contains(thumb) || !thumb.querySelector('video.visual-pool-card-video')) {
                        return;
                    }
                    const from = event.relatedTarget instanceof Node ? event.relatedTarget : null;
                    if (from && thumb.contains(from)) {
                        return;
                    }
                    setVisualHoverPreviewActive(thumb, true);
                });

                listEl.addEventListener('mouseout', (event) => {
                    const thumb = event.target.closest('.visual-pool-card-thumb');
                    if (!thumb || !listEl.contains(thumb)) {
                        return;
                    }
                    const to = event.relatedTarget instanceof Node ? event.relatedTarget : null;
                    if (to && thumb.contains(to)) {
                        return;
                    }
                    setVisualHoverPreviewActive(thumb, false);
                });

                listEl.addEventListener('focusin', (event) => {
                    const thumb = event.target.closest('.visual-pool-card-thumb');
                    if (!thumb || !thumb.querySelector('video.visual-pool-card-video')) {
                        return;
                    }
                    setVisualHoverPreviewActive(thumb, true);
                });

                listEl.addEventListener('focusout', (event) => {
                    const thumb = event.target.closest('.visual-pool-card-thumb');
                    if (!thumb) {
                        return;
                    }
                    if (event.relatedTarget && thumb.contains(event.relatedTarget)) {
                        return;
                    }
                    setVisualHoverPreviewActive(thumb, false);
                });
            }

            bindPoolListInteractions('visual');
            bindPoolListInteractions('special');
            bindPoolListInteractions('sfx');

            const poolAssetPreviewEl = document.getElementById('poolAssetPreview');
            if (poolAssetPreviewEl) {
                if (!poolAssetPreviewEl.hasAttribute('tabindex')) {
                    poolAssetPreviewEl.setAttribute('tabindex', '0');
                }
                poolAssetPreviewEl.addEventListener('mouseenter', () => {
                    if (!poolAssetPreviewEl.querySelector('video.visual-asset-loop')) {
                        return;
                    }
                    setVisualHoverPreviewActive(poolAssetPreviewEl, true);
                });
                poolAssetPreviewEl.addEventListener('mouseleave', () => {
                    setVisualHoverPreviewActive(poolAssetPreviewEl, false);
                });
                poolAssetPreviewEl.addEventListener('focusin', () => {
                    if (!poolAssetPreviewEl.querySelector('video.visual-asset-loop')) {
                        return;
                    }
                    setVisualHoverPreviewActive(poolAssetPreviewEl, true);
                });
                poolAssetPreviewEl.addEventListener('focusout', (event) => {
                    if (event.relatedTarget && poolAssetPreviewEl.contains(event.relatedTarget)) {
                        return;
                    }
                    setVisualHoverPreviewActive(poolAssetPreviewEl, false);
                });
            }

            function setPoolReleaseFilter(nextValue) {
                poolReleaseFilter = normalizePoolReleaseFilter(nextValue);
                syncReleaseFilterUi();

                if (activeMediaPanel) {
                    loadMediaList(activeMediaPanel);
                }
                if (mediaPickerState) {
                    renderMediaPickerList(mediaPickerState.activeTarget);
                }
                releaseFilterListeners.forEach((listener) => listener());
            }

            function setPoolNameFilter(panelType, nextValue) {
                const key = String(panelType || '').trim();
                if (!Object.prototype.hasOwnProperty.call(poolNameFilters, key)) {
                    return;
                }
                poolNameFilters[key] = String(nextValue || '');
                if (activeMediaPanel === key) {
                    loadMediaList(key);
                }
            }

            document.querySelectorAll('[data-media-release-filter], [data-pool-release-filter]').forEach((select) => {
                select.addEventListener('change', () => {
                    setPoolReleaseFilter(String(select.value || 'all'));
                });
            });

            document.querySelectorAll('[data-media-name-filter]').forEach((input) => {
                input.addEventListener('input', () => {
                    const panel = String(input.getAttribute('data-media-name-filter') || '').trim();
                    setPoolNameFilter(panel, String(input.value || ''));
                });
            });

            syncReleaseFilterUi();
            syncBrandFilterUi();
            syncMediaReferenceFilterUi();
            syncPoolViewUi();
            if (adminFilesTabActive || adminContentTabActive) {
                Promise.all([
                    loadReleasesCatalog().catch(() => {
                        populateReleaseFilterSelects();
                    }),
                    ensureBrandFilterCatalog().catch(() => {
                        populateBrandFilterSelects();
                    }),
                ]);
            } else {
                populateReleaseFilterSelects();
                populateBrandFilterSelects();
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
                const labels = {
                    audio: 'Add Audio',
                    video: 'Add Video',
                    visual: 'Add Visual Files',
                    illustrations: 'Add Illustrations',
                    photos: 'Add Photos',
                    special: 'Add Brand Assets',
                    sfx: 'Add Sound Effects',
                };
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

            function syncMediaPickerOwnershipFilters(target) {
                const usesBrand = target === 'special' || target === 'sfx';
                document.querySelectorAll('#mediaPickerToolbar [data-picker-filter="release"]').forEach((el) => {
                    el.hidden = usesBrand;
                });
                document.querySelectorAll('#mediaPickerToolbar [data-picker-filter="brand"]').forEach((el) => {
                    el.hidden = !usesBrand;
                });
            }

            async function renderMediaPickerList(target) {
                if (!mediaPickerList || !mediaPickerState) return;
                mediaPickerState.activeTarget = target;
                renderMediaPickerTabs();
                syncMediaPickerOwnershipFilters(target);
                mediaPickerStatus.textContent = 'Loading…';
                mediaPickerStatus.style.color = '#aaa';
                mediaPickerList.innerHTML = '<span class="text-muted">Loading…</span>';

                try {
                    if (target === 'special' || target === 'sfx') {
                        await ensureBrandFilterCatalog();
                    }
                    const includeHidden = window.bandpromoDemoCatalogVisible === true;
                    let files = await fetchMediaFiles(target, { includeHidden });
                    if (target === 'visual' && Array.isArray(mediaPickerState.visualBuckets) && mediaPickerState.visualBuckets.length) {
                        const allowed = new Set(mediaPickerState.visualBuckets);
                        files = files.filter((file) => allowed.has(resolveFileIntakeBucket(file, 'visual')));
                    }
                    const pickerSearchEl = document.getElementById('mediaPickerSearch');
                    const pickerNeedle = pickerSearchEl ? String(pickerSearchEl.value || '') : '';
                    files = files.filter((file) => matchesMediaSearch(target, file, pickerNeedle));
                    setAdminPreviewItems(files, target);

                    if (!files.length) {
                        mediaPickerList.innerHTML = '<span class="text-muted">No matching assets in this media group.</span>';
                        mediaPickerStatus.textContent = 'No matching assets. Try another filter or upload one.';
                        return;
                    }

                    mediaPickerStatus.textContent = `${files.length} asset${files.length !== 1 ? 's' : ''} available in ${mediaTypeLabels[target] || target}. Click a thumbnail to use it.`;
                    mediaPickerList.innerHTML = `<div class="media-picker-grid">${files.map((file) => {
                        const pathType = resolveFileIntakeBucket(file, target) || target;
                        const encodedName = encodeURIComponent(file.name);
                        const label = target === 'audio'
                            ? (formatAudioListRowBody(audioFileForDisplay(file)) || 'Untitled')
                            : (poolPanelTypes.has(target)
                                ? poolAssetHeadline(target, file)
                                : 'Asset');
                        const safeLabel = bandpromoAdminEscapeHtml(label);
                        const url = buildMediaUrl(pathType, file.name);
                        const notReady = target === 'visual' && file.pool_ready === false;
                        const reason = notReady
                            ? String(file.pool_ready_reason || 'Delivery variants not ready yet')
                            : '';
                        let mediaMarkup;

                        if (isImage(file.name)) {
                            mediaMarkup = `<img src="${url}" alt="" loading="lazy">`;
                        } else if (isVideo(file.name)) {
                            mediaMarkup = buildVideoPickerMarkup(file);
                        } else {
                            mediaMarkup = `<span class="media-picker-tile-icon">${extIcon(file.name)}</span>`;
                        }

                        const previewBtn = !notReady && isPreviewable(file.name, file, target)
                            ? `<button type="button" class="icon-btn media-picker-preview media-picker-tile-preview" data-picker-target="${pathType}" data-filename="${encodedName}" title="Preview" aria-label="Preview ${safeLabel}">👁️</button>`
                            : '';

                        // Use a div tile (not <button>) so the preview control can stay a nested
                        // button without HTML reparsing that scatters labels across the grid.
                        if (notReady) {
                            return `<div class="media-picker-tile is-disabled" aria-disabled="true" title="${bandpromoAdminEscapeHtml(reason)}" aria-label="${safeLabel}: ${bandpromoAdminEscapeHtml(reason)}">
                                <span class="media-picker-tile-media">${mediaMarkup}</span>
                                <span class="media-picker-tile-note">${bandpromoAdminEscapeHtml(reason)}</span>
                            </div>`;
                        }

                        return `<div class="media-picker-tile" role="button" tabindex="0" data-picker-target="${pathType}" data-filename="${encodedName}" data-asset-id="${bandpromoAdminEscapeHtml(String(file.asset_id || '').trim())}" title="${safeLabel}" aria-label="${safeLabel}">
                            <span class="media-picker-tile-media">${mediaMarkup}${previewBtn}</span>
                            <span class="media-picker-tile-label">${safeLabel}</span>
                        </div>`;
                    }).join('')}</div>`;
                    mediaPickerStatus.style.color = '#aaa';
                } catch (error) {
                    mediaPickerList.innerHTML = `<span class="text-error">${bandpromoAdminEscapeHtml(error.message)}</span>`;
                    mediaPickerStatus.textContent = 'Failed to load files.';
                    mediaPickerStatus.style.color = '#f55';
                }
            }

            function normalizeMediaPickerTargets(targets) {
                const allowedTargets = Array.isArray(targets) ? targets.filter(Boolean) : [];
                const visualHits = allowedTargets.filter((target) => VISUAL_INTAKE_BUCKETS.includes(target));
                const otherHits = allowedTargets.filter((target) => !VISUAL_INTAKE_BUCKETS.includes(target));
                if (!visualHits.length) {
                    return {
                        targets: allowedTargets.length ? allowedTargets : ['special'],
                        visualBuckets: null,
                    };
                }
                return {
                    targets: ['visual', ...otherHits],
                    visualBuckets: visualHits,
                };
            }

            window.openMediaPicker = function(fieldId, title, targets) {
                const pickerModal = document.getElementById('mediaPickerModal');
                const input = document.getElementById(fieldId);
                if (!input || !pickerModal) return;

                if (pickerModal.parentElement !== document.body) {
                    document.body.appendChild(pickerModal);
                }

                const rawTargets = String(targets || '')
                    .split(',')
                    .map((value) => value.trim())
                    .filter(Boolean);
                const normalized = normalizeMediaPickerTargets(rawTargets);

                mediaPickerState = {
                    fieldId,
                    title: title || 'Choose file',
                    targets: normalized.targets,
                    visualBuckets: normalized.visualBuckets,
                    activeTarget: inferMediaTargetFromPath(input.value, normalized.targets),
                };

                if (mediaPickerTitle) {
                    mediaPickerTitle.textContent = mediaPickerState.title;
                }
                const pickerSearchEl = document.getElementById('mediaPickerSearch');
                if (pickerSearchEl) {
                    pickerSearchEl.value = '';
                }
                populateReleaseFilterSelects();
                syncReleaseFilterUi();
                populateBrandFilterSelects();
                syncBrandFilterUi();
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

            const mediaPickerSearch = document.getElementById('mediaPickerSearch');
            if (mediaPickerSearch) {
                mediaPickerSearch.addEventListener('input', () => {
                    if (!mediaPickerState) {
                        return;
                    }
                    renderMediaPickerList(mediaPickerState.activeTarget);
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
                        if (selectBtn.classList.contains('is-disabled') || selectBtn.getAttribute('aria-disabled') === 'true') {
                            return;
                        }
                        const target = selectBtn.dataset.pickerTarget;
                        const filename = decodeURIComponent(selectBtn.dataset.filename || '');
                        const selectedPath = buildMediaPath(target, filename);
                        const selectedAssetId = String(selectBtn.dataset.assetId || '').trim();
                        if (mediaPickerState.fieldId === 'audioMasterFieldLivingCoverPath') {
                            applyAudioMasterLivingCoverSelection(selectedPath);
                        } else if (
                            String(mediaPickerState.fieldId || '').startsWith('theme_asset_')
                            && typeof window.bandpromoShellMediaPicked === 'function'
                        ) {
                            const shellKey = String(mediaPickerState.fieldId).slice('theme_asset_'.length);
                            window.bandpromoShellMediaPicked(shellKey, selectedPath, selectedAssetId);
                        } else {
                            setPickerFieldValue(mediaPickerState.fieldId, selectedPath);
                            if (mediaPickerState.fieldId === 'audioMasterFieldCoverPath') {
                                syncAudioMasterCoverUi(activeAudioMasterDetail || {});
                            }
                            if (mediaPickerState.fieldId === 'releaseSettingsPosterAssetId'
                                && typeof window.bandpromoReleaseCoverPicked === 'function') {
                                window.bandpromoReleaseCoverPicked(selectedPath);
                            }
                            if (mediaPickerState.fieldId === 'playlistSettingsPosterAssetId'
                                && typeof window.bandpromoPlaylistCoverPicked === 'function') {
                                window.bandpromoPlaylistCoverPicked(selectedPath);
                            }
                        }
                        closeMediaPickerModal();
                    }
                });

                mediaPickerList.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }
                    const tile = event.target.closest('.media-picker-tile[role="button"]');
                    if (!tile || !mediaPickerList.contains(tile)) {
                        return;
                    }
                    event.preventDefault();
                    tile.click();
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
            const audioMasterLivingCoverPath = document.getElementById('audioMasterFieldLivingCoverPath');
            const audioMasterLivingCoverClearBtn = document.getElementById('audioMasterLivingCoverClearBtn');
            const audioMasterLivingCoverPreview = document.getElementById('audioMasterLivingCoverPreview');
            const audioMasterLivingCoverPreviewShell = document.getElementById('audioMasterLivingCoverPreviewShell');
            const audioMasterLivingCoverPlaceholder = document.getElementById('audioMasterLivingCoverPlaceholder');
            const audioMasterLivingCoverStatus = document.getElementById('audioMasterLivingCoverStatus');
            const audioMasterDescriptionCount = document.getElementById('audioMasterDescriptionCount');
            const audioMasterVersionField = document.getElementById('audioMasterFieldVersion');
            const audioMasterForm = document.getElementById('audioMasterForm');
            const audioMasterNotesLabelWrap = document.getElementById('audioMasterNotesLabelWrap');
            const audioMasterNotesLabelField = document.getElementById('audioMasterFieldNotesLabel');
            const audioMasterTextRoleButtons = Array.from(
                document.querySelectorAll('.audio-master-text-role-btn[data-text-role]')
            );
            let audioMasterTextRole = 'lyrics';
            let deleteTarget = null;
            let deleteFiles  = [];
            let activeAudioMasterFile = null;
            let activeAudioMasterDetail = null;
            let audioMasterCoverMode = 'preserve';
            let audioMasterLivingCoverMode = 'preserve';

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

            function setAudioMasterLivingCoverMode(mode, options = {}) {
                audioMasterLivingCoverMode = mode === 'set' || mode === 'clear' ? mode : 'preserve';
                if (audioMasterLivingCoverPath) {
                    audioMasterLivingCoverPath.dataset.emptyLabel = audioMasterLivingCoverMode === 'clear'
                        ? 'Living cover will be removed after save'
                        : 'No living cover assigned';
                }
            }

            function audioMasterLivingCoverBasename(pathOrName) {
                const raw = String(pathOrName || '').trim().replace(/\\/g, '/');
                if (!raw) {
                    return '';
                }
                return raw.split('/').pop() || '';
            }

            function audioMasterLivingCoverStoragePath(filename) {
                const safe = audioMasterLivingCoverBasename(filename);
                return safe ? `/media/video/original/${safe}` : '';
            }

            function audioMasterLivingCoverPreviewFromPicker(filename) {
                const safe = audioMasterLivingCoverBasename(filename);
                if (!safe) {
                    return '';
                }
                const items = Array.isArray(window._adminPreviewItems) ? window._adminPreviewItems : [];
                const match = items.find((item) => audioMasterLivingCoverBasename(item?.name) === safe);
                if (!match) {
                    return '';
                }
                if (match.type === 'video' && match.src) {
                    return String(match.src);
                }
                if (match.poster) {
                    return String(match.poster);
                }
                if (match.src) {
                    return String(match.src);
                }
                return '';
            }

            function audioMasterLivingCoverPreviewUrl(detail) {
                if (audioMasterLivingCoverMode === 'clear') {
                    return '';
                }

                const selected = audioMasterLivingCoverPath ? String(audioMasterLivingCoverPath.value || '').trim() : '';
                const selectedName = audioMasterLivingCoverBasename(selected);
                const data = detail || {};
                const assignedName = audioMasterLivingCoverBasename(data.living_cover || '');

                if (selectedName) {
                    const pickerPreview = audioMasterLivingCoverPreviewFromPicker(selectedName);
                    if (pickerPreview) {
                        return pickerPreview;
                    }
                    if (selectedName === assignedName && data.living_cover_preview_url) {
                        return String(data.living_cover_preview_url);
                    }
                    // Prefer absolute original path so the <video> element can load it.
                    return selected.startsWith('/') ? selected : `/${selected.replace(/^\/*/, '')}`;
                }

                if (data.living_cover_preview_url) {
                    return String(data.living_cover_preview_url);
                }
                return '';
            }

            function applyAudioMasterLivingCoverSelection(path, options = {}) {
                const storagePath = audioMasterLivingCoverStoragePath(path);
                const filename = audioMasterLivingCoverBasename(storagePath);
                const previewUrl = audioMasterLivingCoverPreviewFromPicker(filename)
                    || (storagePath ? storagePath : '');

                setAudioMasterLivingCoverMode(filename ? 'set' : 'preserve');
                if (audioMasterLivingCoverPath) {
                    // Avoid input-handler races: set value directly, then sync once.
                    audioMasterLivingCoverPath.value = storagePath;
                    updatePickerFieldLabel('audioMasterFieldLivingCoverPath');
                }

                activeAudioMasterDetail = {
                    ...(activeAudioMasterDetail || {}),
                    living_cover: filename,
                    living_cover_preview_url: previewUrl,
                    living_cover_delivery_ready: previewUrl.includes('/media/video/optimal/'),
                    living_cover_delivery_pending: filename !== '' && !previewUrl.includes('/media/video/optimal/'),
                };

                if (options.sync !== false) {
                    syncAudioMasterLivingCoverUi(activeAudioMasterDetail);
                }
            }

            function syncAudioMasterLivingCoverUi(detail) {
                const data = detail || activeAudioMasterDetail || {};
                const previewUrl = audioMasterLivingCoverPreviewUrl(data);
                const statusParts = [];
                const hasAssigned = String(data.living_cover || '').trim() !== ''
                    || (audioMasterLivingCoverPath && String(audioMasterLivingCoverPath.value || '').trim() !== '');

                if (audioMasterLivingCoverMode === 'clear') {
                    statusParts.push('Removed when you save.');
                } else if (hasAssigned && !previewUrl) {
                    statusParts.push('Publish required before preview and player loop.');
                } else if (hasAssigned && data.living_cover_delivery_pending) {
                    statusParts.push('Publish required before player loop.');
                }

                if (audioMasterLivingCoverPreviewShell) {
                    const tooltipParts = ['Optional silent loop on the player flip-card cover.'];
                    if (audioMasterLivingCoverMode === 'clear') {
                        tooltipParts.push('Living cover will be removed when you save.');
                    } else if (hasAssigned && data.living_cover_delivery_ready) {
                        tooltipParts.push('Delivery MP4 is ready.');
                    } else if (hasAssigned) {
                        tooltipParts.push('Waiting for video delivery.');
                    }
                    audioMasterLivingCoverPreviewShell.title = tooltipParts.join(' ');
                }

                if (audioMasterLivingCoverStatus) {
                    audioMasterLivingCoverStatus.textContent = statusParts.join(' ');
                }

                if (!audioMasterLivingCoverPreview) {
                    return;
                }

                const previewLooksLikeVideo = /\.(mp4|webm|mov)(\?|$)/i.test(previewUrl)
                    || previewUrl.includes('/media/video/optimal/')
                    || previewUrl.includes('/media/video/original/');

                if (previewUrl && audioMasterLivingCoverMode !== 'clear') {
                    if (previewLooksLikeVideo) {
                        if (audioMasterLivingCoverPreview.dataset.src !== previewUrl) {
                            audioMasterLivingCoverPreview.dataset.src = previewUrl;
                            audioMasterLivingCoverPreview.src = previewUrl;
                        }
                        audioMasterLivingCoverPreview.style.display = 'block';
                        audioMasterLivingCoverPreview.play().catch(() => {});
                        if (audioMasterLivingCoverPreviewShell) {
                            audioMasterLivingCoverPreviewShell.style.backgroundImage = '';
                        }
                    } else {
                        audioMasterLivingCoverPreview.pause();
                        audioMasterLivingCoverPreview.removeAttribute('src');
                        delete audioMasterLivingCoverPreview.dataset.src;
                        audioMasterLivingCoverPreview.style.display = 'none';
                        if (audioMasterLivingCoverPreviewShell) {
                            audioMasterLivingCoverPreviewShell.style.backgroundImage = `url("${previewUrl}")`;
                            audioMasterLivingCoverPreviewShell.style.backgroundSize = 'cover';
                            audioMasterLivingCoverPreviewShell.style.backgroundPosition = 'center';
                        }
                    }
                } else {
                    audioMasterLivingCoverPreview.pause();
                    audioMasterLivingCoverPreview.removeAttribute('src');
                    delete audioMasterLivingCoverPreview.dataset.src;
                    audioMasterLivingCoverPreview.style.display = 'none';
                    if (audioMasterLivingCoverPreviewShell) {
                        audioMasterLivingCoverPreviewShell.style.backgroundImage = '';
                    }
                }

                if (audioMasterLivingCoverPlaceholder) {
                    const showPlaceholder = audioMasterLivingCoverMode === 'clear'
                        || !previewUrl;
                    audioMasterLivingCoverPlaceholder.style.display = showPlaceholder ? 'block' : 'none';
                    audioMasterLivingCoverPlaceholder.textContent = audioMasterLivingCoverMode === 'clear'
                        ? 'Will use still cover only'
                        : 'No living cover';
                }
            }

            function normalizeAudioMasterDateValue(value) {
                const normalized = String(value || '').trim();
                if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
                    return normalized;
                }
                if (/^\d{4}$/.test(normalized)) {
                    return normalized;
                }
                return '';
            }

            function splitAudioTitleParts(value) {
                const combined = String(value || '').trim();
                if (!combined) {
                    return { title: '', version: '' };
                }
                // Legacy registry GET mashed version onto a second line.
                const newlineParts = combined.split(/\r\n|\r|\n/).map((part) => part.trim()).filter(Boolean);
                if (newlineParts.length === 2) {
                    return { title: newlineParts[0], version: newlineParts[1] };
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

            function audioMasterTitlePartsFromDetail(detail) {
                const parts = splitAudioTitleParts(detail && typeof detail.title === 'string' ? detail.title : '');
                const explicitVersion = String(detail && detail.version || '').trim();
                return {
                    title: parts.title,
                    version: explicitVersion || parts.version,
                };
            }

            function validateAudioMasterFields(fields) {
                const requiredOrder = [
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
                const livingCoverPath = String(options.living_cover_path || '').trim();
                const livingCoverMode = String(options.living_cover_mode || 'preserve');

                const saveMetadata = async (csrfToken) => {
                    const resp = await fetch('/biblioteca/save-audio-master-detail.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            filename,
                            fields,
                            cover_path: coverPath,
                            cover_mode: coverMode,
                            living_cover_path: livingCoverPath,
                            living_cover_mode: livingCoverMode,
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
                    setAudioMasterLivingCoverMode('preserve');
                    if (audioMasterLivingCoverPath) {
                        const assignedLivingCover = String(detail.living_cover || '').trim();
                        // Set without firing the input handler so mode stays 'preserve'.
                        audioMasterLivingCoverPath.value = audioMasterLivingCoverStoragePath(assignedLivingCover);
                        updatePickerFieldLabel('audioMasterFieldLivingCoverPath');
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
                    const toastMessage = data.no_change
                        ? 'No changes were saved.'
                        : (data.warning
                            ? data.warning
                            : (Array.isArray(data.auto_tasks) && data.auto_tasks.includes('playlist-scan')
                                ? 'Track details updated and validation refreshed.'
                                : 'Track details updated.'));
                    showAdminToast(toastMessage, data.warning ? 'warning' : 'success');
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
                const title = String(audioMasterTitlePartsFromDetail(detail || {}).title || '').trim();
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

            function syncAudioMasterTextPanelUi() {
                const role = audioMasterTextRole === 'notes' ? 'notes' : 'lyrics';
                audioMasterTextRole = role;
                audioMasterTextRoleButtons.forEach((btn) => {
                    const isActive = String(btn.getAttribute('data-text-role') || '') === role;
                    btn.classList.toggle('is-active', isActive);
                    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
                if (audioMasterFields.lyrics) {
                    audioMasterFields.lyrics.setAttribute('aria-label', role === 'notes' ? 'Notes' : 'Lyrics');
                }
                if (audioMasterNotesLabelWrap) {
                    audioMasterNotesLabelWrap.hidden = role !== 'notes';
                }
            }

            function setAudioMasterTextRole(role, options = {}) {
                audioMasterTextRole = String(role || '').trim().toLowerCase() === 'notes' ? 'notes' : 'lyrics';
                if (audioMasterTextRole !== 'notes' && audioMasterNotesLabelField && options.clearLabel) {
                    audioMasterNotesLabelField.value = '';
                }
                syncAudioMasterTextPanelUi();
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
                    if (!previewUrl) {
                        audioMasterCoverPlaceholder.textContent = 'No cover';
                    }
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
                syncAudioMasterLivingCoverUi(detail);
            }

            function setAudioMasterFormValues(detail) {
                const titleParts = audioMasterTitlePartsFromDetail(detail || {});
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
                        const native = input.closest('.iso-date-field')?.querySelector('.iso-date-picker-native');
                        if (native instanceof HTMLInputElement) {
                            if (/^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
                                native.value = input.value;
                            } else if (/^\d{4}$/.test(input.value)) {
                                native.value = `${input.value}-01-01`;
                            } else {
                                native.value = '';
                            }
                        }
                        return;
                    }
                    input.value = detail && typeof detail[key] === 'string' ? detail[key] : '';
                });
                setAudioMasterTextRole(detail && detail.text_role, { clearLabel: false });
                if (audioMasterNotesLabelField) {
                    audioMasterNotesLabelField.value = detail && typeof detail.notes_label === 'string'
                        ? detail.notes_label
                        : '';
                }
                syncAudioMasterTextPanelUi();
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
                setAudioMasterLivingCoverMode('preserve');
                if (audioMasterLivingCoverPath) {
                    audioMasterLivingCoverPath.value = '';
                    updatePickerFieldLabel('audioMasterFieldLivingCoverPath');
                }
                try {
                    const data = await fetchAudioMasterDetailData(filename);
                    audioInlineDetailCache.set(filename, data);
                    audioInlineDetailErrors.delete(filename);
                    if (audioMasterTitle) audioMasterTitle.textContent = buildAudioMasterHeading(data);
                    setAudioMasterLivingCoverMode('preserve');
                    if (audioMasterLivingCoverPath) {
                        const assignedLivingCover = String(data.living_cover || '').trim();
                        audioMasterLivingCoverPath.value = audioMasterLivingCoverStoragePath(assignedLivingCover);
                        updatePickerFieldLabel('audioMasterFieldLivingCoverPath');
                    }
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
                setAudioMasterLivingCoverMode('preserve');
                if (audioMasterLivingCoverPath) {
                    setPickerFieldValue('audioMasterFieldLivingCoverPath', '');
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
                setAudioMasterLivingCoverMode('preserve');
                if (audioMasterLivingCoverPath) {
                    setPickerFieldValue('audioMasterFieldLivingCoverPath', '');
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

            if (audioMasterLivingCoverPath) {
                audioMasterLivingCoverPath.addEventListener('input', () => {
                    // Selection path goes through applyAudioMasterLivingCoverSelection.
                    // Keep this for any other callers that mutate the hidden input.
                    if (audioMasterLivingCoverMode === 'clear') {
                        syncAudioMasterLivingCoverUi(activeAudioMasterDetail || {});
                        return;
                    }
                    const value = String(audioMasterLivingCoverPath.value || '').trim();
                    if (value !== '') {
                        applyAudioMasterLivingCoverSelection(value);
                        return;
                    }
                    setAudioMasterLivingCoverMode('preserve');
                    syncAudioMasterLivingCoverUi(activeAudioMasterDetail || {});
                });
            }

            if (audioMasterLivingCoverClearBtn) {
                audioMasterLivingCoverClearBtn.addEventListener('click', () => {
                    if (audioMasterLivingCoverPath) {
                        audioMasterLivingCoverPath.value = '';
                        updatePickerFieldLabel('audioMasterFieldLivingCoverPath');
                    }
                    setAudioMasterLivingCoverMode('clear');
                    activeAudioMasterDetail = {
                        ...(activeAudioMasterDetail || {}),
                        living_cover: '',
                        living_cover_preview_url: '',
                        living_cover_delivery_ready: false,
                        living_cover_delivery_pending: false,
                    };
                    syncAudioMasterLivingCoverUi(activeAudioMasterDetail);
                });
            }

            if (audioMasterFields.comment) {
                audioMasterFields.comment.addEventListener('input', updateAudioMasterDescriptionCounter);
            }

            audioMasterTextRoleButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    setAudioMasterTextRole(btn.getAttribute('data-text-role'), { clearLabel: true });
                });
            });
            syncAudioMasterTextPanelUi();

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
                const idx = items.findIndex((item) =>
                    item.src === normalizedSrc
                    || item.fileKey === name
                    || item.name === name
                );
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

            document.querySelectorAll('.media-file-list, #filelist-visual').forEach((listEl) => {
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
                const selected = new Set(
                    (Array.isArray(filenames) ? filenames : [])
                        .map((name) => selectionDisplayName(target, name))
                        .filter(Boolean)
                );
                const selectedFiles = files.filter((entry) => selected.has(String(entry.filename || '')));
                const extras = [];
                const themeKinds = new Set(['theme-cover', 'theme-background', 'theme-background-video', 'share-image']);

                const hasThemeRefs = selectedFiles.some((entry) => (
                    Array.isArray(entry.references) && entry.references.some((reference) => themeKinds.has(String(reference.kind || '')))
                ));
                if (hasThemeRefs) {
                    extras.push('Branding or share-image settings still point at this file and will not be cleared automatically.');
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

            function emptyDeleteReferenceSummary() {
                return {
                    total: 0,
                    playlist_tracks: 0,
                    playlist_covers: 0,
                    gallery_items: 0,
                    theme_assets: 0,
                    release_fallbacks: 0,
                };
            }

            function mergeDeleteReferenceSummaries(left, right) {
                const a = left && typeof left === 'object' ? left : {};
                const b = right && typeof right === 'object' ? right : {};
                const merged = emptyDeleteReferenceSummary();
                Object.keys(merged).forEach((key) => {
                    merged[key] = Math.max(0, Number(a[key] || 0)) + Math.max(0, Number(b[key] || 0));
                });
                return merged;
            }

            async function requestDeleteMediaOperation(panelType, selectionKeys, options = {}) {
                const mode = options.mode === 'preview' ? 'preview' : 'delete';
                const detachReferences = options.detach_references === true;
                const keys = (Array.isArray(selectionKeys) ? selectionKeys : []).filter(Boolean);
                if (!panelType || !keys.length) {
                    throw new Error('No files selected');
                }

                const groups = panelType === 'visual'
                    ? groupSelectionKeysByBucket('visual', keys)
                    : new Map([[panelType, keys.map((key) => parseMediaSelectionKey(panelType, key).name)]]);

                const merged = {
                    ok: true,
                    reference_summary: emptyDeleteReferenceSummary(),
                    references: [],
                    files: [],
                    failed_count: 0,
                    message: '',
                    deleted: [],
                };

                for (const [bucket, names] of groups.entries()) {
                    const payload = names.length > 1
                        ? {
                            target: bucket,
                            filenames: names,
                            mode: mode === 'preview' ? 'preview' : undefined,
                            detach_references: detachReferences,
                        }
                        : {
                            target: bucket,
                            filename: names[0],
                            mode: mode === 'preview' ? 'preview' : undefined,
                            detach_references: detachReferences,
                        };
                    if (mode !== 'preview') {
                        delete payload.mode;
                        payload.detach_references = true;
                    }

                    const resp = await fetch('/biblioteca/delete-media.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok || !data || data.ok !== true) {
                        throw new Error((data && data.error) || ('Request failed: ' + resp.status));
                    }

                    merged.reference_summary = mergeDeleteReferenceSummaries(
                        merged.reference_summary,
                        data.reference_summary || {}
                    );
                    if (Array.isArray(data.references)) {
                        merged.references.push(...data.references);
                    }
                    if (Array.isArray(data.files)) {
                        merged.files.push(...data.files);
                    }
                    merged.failed_count += Number(data.failed_count || 0);
                    if (data.message) {
                        merged.message = data.message;
                    }
                    if (Array.isArray(data.deleted)) {
                        merged.deleted.push(...data.deleted);
                    }
                }

                if (!merged.message) {
                    merged.message = mode === 'preview'
                        ? 'Preview ready'
                        : (merged.failed_count ? 'Some files could not be deleted.' : 'File removed.');
                }
                return merged;
            }

            window.openDeleteModal = function(type, filename) {
                deleteTarget = type;
                deleteFiles  = Array.isArray(filename) ? filename.filter(Boolean) : [filename].filter(Boolean);
                deleteReferencePreview = null;
                const displayNames = deleteFiles.map((key) => selectionDisplayName(deleteTarget, key));
                const poolFriendly = poolPanelTypes.has(deleteTarget);
                if (deleteTitleEl) {
                    deleteTitleEl.textContent = deleteFiles.length > 1
                        ? (poolFriendly
                            ? (deleteTarget === 'special'
                                ? 'Delete selected brand assets?'
                                : (deleteTarget === 'sfx' ? 'Delete selected sound effects?' : 'Delete selected visuals?'))
                            : 'Delete selected files?')
                        : (poolFriendly
                            ? (deleteTarget === 'special'
                                ? 'Delete this brand asset?'
                                : (deleteTarget === 'sfx' ? 'Delete this sound effect?' : 'Delete this visual?'))
                            : 'Delete file?');
                }
                if (deleteNameEl) {
                    if (poolFriendly) {
                        const plural = deleteTarget === 'special'
                            ? 'brand assets'
                            : (deleteTarget === 'sfx' ? 'sound effects' : 'visuals');
                        deleteNameEl.textContent = deleteFiles.length > 1
                            ? `${deleteFiles.length} ${plural} selected`
                            : poolAssetHeadline(deleteTarget, findPoolAssetByKey(deleteTarget, deleteFiles[0]) || { media_type: deleteTarget === 'sfx' ? 'audio' : 'image' });
                    } else {
                        deleteNameEl.textContent = deleteFiles.length > 1
                            ? `${deleteFiles.length} files selected`
                            : (displayNames[0] || '');
                    }
                }
                if (deleteListEl) {
                    if (deleteFiles.length > 1 && !poolFriendly) {
                        deleteListEl.style.display = 'block';
                        deleteListEl.innerHTML = displayNames.map((name, index) => `<div class="modal-file-row">${index + 1}. ${bandpromoAdminEscapeHtml(name)}</div>`).join('');
                    } else if (deleteFiles.length > 1 && poolFriendly) {
                        deleteListEl.style.display = 'block';
                        deleteListEl.innerHTML = deleteFiles.map((key, index) => {
                            const file = findPoolAssetByKey(deleteTarget, key);
                            const label = file ? poolAssetHeadline(deleteTarget, file) : (deleteTarget === 'special' ? 'Brand asset' : 'Visual asset');
                            const kind = file ? poolAssetKind(deleteTarget, file) : 'image';
                            return `<div class="modal-file-row">${index + 1}. ${bandpromoAdminEscapeHtml(label)} <span class="text-muted">(${bandpromoAdminEscapeHtml(poolAssetKindLabel(kind, deleteTarget).toLowerCase())})</span></div>`;
                        }).join('');
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
                        const data = await requestDeleteMediaOperation(deleteTarget, deleteFiles, { mode: 'preview' });
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
                        const data = await requestDeleteMediaOperation(deleteTarget, deleteFiles, {
                            detach_references: true,
                        });
                        clearMediaSelection(deleteTarget);
                        closeDeleteModal();
                        await loadMediaList(activeMediaPanel);
                        const toastType = data.failed_count ? 'warning' : 'success';
                        showAdminToast(data.message || 'File removed.', toastType);
                    } catch(e) {
                        deleteStatusEl.innerHTML = `<span class="text-error">❌ ${bandpromoAdminEscapeHtml(e && e.message ? e.message : 'Request failed')}</span>`;
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
                    fields.text_role = audioMasterTextRole === 'notes' ? 'notes' : 'lyrics';
                    fields.notes_label = fields.text_role === 'notes' && audioMasterNotesLabelField
                        ? String(audioMasterNotesLabelField.value || '').trim()
                        : '';

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
                            living_cover_path: audioMasterLivingCoverPath ? String(audioMasterLivingCoverPath.value || '').trim() : '',
                            living_cover_mode: audioMasterLivingCoverMode,
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
                    let autoDeliveryFailed = false;
                    let uploadWarnings = [];
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
                            if (uploadData && uploadData.display_warning) {
                                masterWarnings.push(`${file.name}: ${uploadData.display_warning}`);
                            }
                            if (uploadData && typeof uploadData.warning === 'string' && uploadData.warning.trim() !== '') {
                                uploadWarnings.push(uploadData.warning.trim());
                                autoDeliveryFailed = true;
                            }
                            if (Array.isArray(uploadData?.auto_tasks) && uploadData.auto_tasks.includes('audio-delivery')) {
                                autoDeliveryRan = true;
                            }
                            if (Array.isArray(uploadData?.delivery_missing) && uploadData.delivery_missing.length) {
                                autoDeliveryFailed = true;
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

                        const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                        const deliveryNote = backgroundVideoStarted
                            ? ' Video delivery started in the background.'
                            : (autoDeliveryRan
                                ? ' Delivery files prepared automatically.'
                                : (autoDeliveryFailed
                                    ? ' Automatic delivery did not finish — check Notifications.'
                                    : ''));
                        const uniqueUploadWarnings = [...new Set(uploadWarnings)];
                        if (latestBuildState && latestBuildState.required) {
                            const next = formatBuildNextStep(latestBuildState);
                            const toastKind = autoDeliveryFailed || masterWarnings.length ? 'warning' : 'success';
                            showAdminToast(`Upload complete.${masterNote}${deliveryNote} ${next}`, toastKind);
                        } else {
                            const toastKind = autoDeliveryFailed || masterWarnings.length ? 'warning' : 'success';
                            showAdminToast(`Upload complete.${masterNote}${deliveryNote}`, toastKind);
                        }
                        if (masterWarnings.length || uniqueUploadWarnings.length) {
                            const combined = [...masterWarnings, ...uniqueUploadWarnings];
                            modalStatus.innerHTML += `<br><span style="color:#f0b429">⚠️ ${bandpromoAdminEscapeHtml(combined.join(' | '))}</span>`;
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
                            ? `<button type="button" class="icon-btn icon-btn--pool icon-btn--danger page-pool-delete-btn" data-gallery-id="${bandpromoAdminEscapeHtml(id)}" title="Delete gallery" aria-label="Delete ${title}">🗑️</button>`
                            : '';
                        return `<li class="playlist-editor-row gallery-pool-row page-pool-row${selectedClass}" data-gallery-id="${bandpromoAdminEscapeHtml(id)}" aria-selected="${id === selectedGalleryId ? 'true' : 'false'}">
                            <span class="playlist-track-info">
                                <strong>🖼️ ${title}</strong>
                                <span class="playlist-track-meta">${bandpromoAdminEscapeHtml(galleryMetaLine(entry))}</span>
                            </span>
                            <span class="page-pool-row-actions">
                                <button type="button" class="icon-btn icon-btn--pool page-pool-edit-btn" data-gallery-id="${bandpromoAdminEscapeHtml(id)}" title="Edit gallery" aria-label="Edit ${title}">✏️</button>
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
                let galleryPendingFiles = [];
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
                    if (!allFiles.length && !galleryPendingFiles.length) {
                        availableEl.innerHTML = '<li class="player-layout-empty">No delivery-ready visuals in the pool yet. Upload under Files → Visual, or check Notifications for missing delivery variants.</li>';
                        return;
                    }
                    const available = allFiles.filter((file) => !taken.has(file.src));
                    const pendingRows = galleryPendingFiles.map((file) => {
                        const reason = file.pool_ready_reason || 'Delivery variants not ready yet';
                        return `<li class="playlist-editor-row gallery-pool-row playlist-editor-row-pending" draggable="false" data-src="${bandpromoAdminEscapeHtml(file.src)}" data-type="${bandpromoAdminEscapeHtml(file.type || 'image')}" aria-disabled="true" title="${bandpromoAdminEscapeHtml(reason)}">
                            ${renderThumbMarkup(file, true)}
                            <span class="playlist-track-info">
                                <strong>${bandpromoAdminEscapeHtml(file.name)}</strong>
                                <span class="playlist-track-meta">${bandpromoAdminEscapeHtml(reason)}</span>
                            </span>
                        </li>`;
                    }).join('');
                    if (available.length === 0 && !pendingRows) {
                        availableEl.innerHTML = '<li class="player-layout-empty">All available content is already in the gallery. Use ✕ on the right to move items back here.</li>';
                        return;
                    }
                    const readyRows = available.map((file) => {
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
                    availableEl.innerHTML = readyRows + pendingRows;
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
                            <button type="button" class="player-layout-remove-btn gallery-remove-btn" title="Move to Available content" aria-label="Remove from gallery">✕</button>
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
                        const visualFiles = await fetchMediaFiles('visual');
                        const ready = [];
                        const pending = [];
                        (visualFiles || []).forEach((file) => {
                            const kind = String(file.media_type || '').trim() === 'video' ? 'video' : 'image';
                            const bucket = resolveFileIntakeBucket(file, 'visual') || (kind === 'video' ? 'video' : 'photos');
                            const deliverySrc = kind === 'video'
                                ? String(file.stream_url || file.preview_url || '').trim()
                                : String(file.card_url || file.thumb_url || '').trim();
                            const legacySrc = kind === 'video'
                                ? `/media/video/original/${file.name}`
                                : (bucket === 'photos'
                                    ? `/media/photo/original/${file.name}`
                                    : `/media/img/original/${file.name}`);
                            const entry = {
                                src: deliverySrc || legacySrc,
                                name: prettifyName(file.name),
                                type: kind,
                                poster: kind === 'video'
                                    ? (file.poster_url || (`/media/video/poster/${String(file.name).replace(/\.[^.]+$/, '.jpg')}`))
                                    : '',
                                pool_ready: file.pool_ready !== false,
                                pool_ready_reason: String(file.pool_ready_reason || '').trim(),
                            };
                            if (entry.pool_ready) {
                                ready.push(entry);
                            } else {
                                pending.push(entry);
                            }
                        });
                        allFiles = ready;
                        galleryPendingFiles = pending;
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
                const releaseFilterId = String(new URLSearchParams(window.location.search).get('release') || '').trim();

                const playlistDeleteModal = document.getElementById('playlistDeleteModal');
                const playlistDeleteModalName = document.getElementById('playlistDeleteModalName');
                const playlistDeleteConfirmBtn = document.getElementById('playlistDeleteConfirmBtn');
                const playlistDeleteCancelBtn = document.getElementById('playlistDeleteCancelBtn');
                const playlistAvailableSection = document.getElementById('playlistAvailableSection');
                const playlistSettingsTitle = document.getElementById('playlistSettingsTitle');
                const playlistSettingsPublishDate = document.getElementById('playlistSettingsPublishDate');
                const playlistSettingsPackageType = document.getElementById('playlistSettingsPackageType');
                const playlistSettingsPlayOrder = document.getElementById('playlistSettingsPlayOrder');
                const playlistSettingsSetAsDefault = document.getElementById('playlistSettingsSetAsDefault');
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
                let playlistPackageTypeDefaults = {
                    single: 'stored',
                    ep: 'stored',
                    album: 'stored',
                    show: 'reverse',
                    podcast: 'reverse',
                    live: 'stored',
                    compilation: 'stored',
                    other: 'stored',
                };
                let playlistSettingsBaseline = {
                    title: '',
                    publish_date: '',
                    package_type: 'other',
                    play_order: 'stored',
                    set_as_default: false,
                    slug: '',
                    description: '',
                    short_description: '',
                    poster_asset_id: '',
                };
                let playlistSettingsSaving = false;
                let playlistSettingsSaveQueued = false;
                let pendingPlaylistCoverPreviewUrl = '';
                let suppressPlaylistPackageTypeOrderSync = false;

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

                function defaultPlayOrderForPackageType(packageType) {
                    const key = String(packageType || 'other').trim().toLowerCase();
                    return playlistPackageTypeDefaults[key] === 'reverse' ? 'reverse' : 'stored';
                }

                function readPlaylistSettingsFromForm() {
                    const entry = playlistEntry(selectedPlaylistId);
                    const title = playlistSettingsTitle instanceof HTMLInputElement
                        ? String(playlistSettingsTitle.value || '').trim()
                        : String(entry?.title || '').trim();
                    const publishDate = playlistSettingsPublishDate instanceof HTMLInputElement
                        ? String(playlistSettingsPublishDate.value || '').trim()
                        : normalizePlaylistDateForInput(entry?.publish_date);
                    const packageType = playlistSettingsPackageType instanceof HTMLSelectElement
                        ? String(playlistSettingsPackageType.value || 'other').trim().toLowerCase()
                        : String(entry?.package_type || 'other').trim().toLowerCase() || 'other';
                    const playOrder = playlistSettingsPlayOrder instanceof HTMLSelectElement
                        ? (String(playlistSettingsPlayOrder.value || '').trim().toLowerCase() === 'reverse' ? 'reverse' : 'stored')
                        : (String(entry?.play_order || 'stored').trim().toLowerCase() === 'reverse' ? 'reverse' : 'stored');
                    const setAsDefault = playlistSettingsSetAsDefault instanceof HTMLInputElement
                        ? Boolean(playlistSettingsSetAsDefault.checked)
                        : Boolean(entry?.is_default);
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
                        package_type: packageType || 'other',
                        play_order: playOrder,
                        set_as_default: setAsDefault,
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

                function playlistMediaPreviewUrlFromReference(value) {
                    const raw = String(value || '').trim().replace(/\\/g, '/');
                    if (!raw) {
                        return '';
                    }
                    if (/^https?:\/\//i.test(raw)) {
                        return raw;
                    }
                    if (raw.startsWith('/media/')) {
                        const parts = raw.split('/');
                        const file = parts.pop() || '';
                        return `${parts.join('/')}/${encodeURIComponent(file)}`;
                    }

                    const basename = raw.includes('/') ? raw.split('/').pop() : raw;
                    if (!basename) {
                        return '';
                    }
                    // Fallback guesses for bare filenames / asset refs before server preview URL arrives.
                    return `/media/img/original/${encodeURIComponent(basename)}`;
                }

                function playlistCoverPreviewUrl(rawValue, entryRef) {
                    const raw = String(rawValue || '').trim();
                    if (!raw) {
                        return pendingPlaylistCoverPreviewUrl || '';
                    }

                    if (pendingPlaylistCoverPreviewUrl) {
                        const pendingBase = pendingPlaylistCoverPreviewUrl.split('?')[0];
                        const rawBase = playlistMediaPreviewUrlFromReference(raw).split('?')[0];
                        const rawFile = raw.split('/').pop() || '';
                        if (!rawBase || pendingBase.endsWith(rawFile) || rawBase === pendingBase) {
                            return pendingPlaylistCoverPreviewUrl;
                        }
                    }

                    if (/^https?:\/\//i.test(raw) || raw.startsWith('/media/')) {
                        return playlistMediaPreviewUrlFromReference(raw);
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
                    return playlistMediaPreviewUrlFromReference(raw);
                }

                function updatePlaylistCoverPreview() {
                    const entry = playlistEntry(selectedPlaylistId);
                    const rawValue = playlistSettingsPosterAssetId instanceof HTMLInputElement
                        ? String(playlistSettingsPosterAssetId.value || '').trim()
                        : '';
                    const previewUrl = playlistCoverPreviewUrl(rawValue, entry);

                    if (playlistCoverPreview instanceof HTMLImageElement) {
                        if (previewUrl) {
                            if (playlistCoverPreview.getAttribute('src') !== previewUrl) {
                                playlistCoverPreview.src = previewUrl;
                            }
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
                        playlistCoverPreviewShell.title = previewUrl ? 'Playlist cover' : 'No cover selected';
                    }
                }

                function setPlaylistCoverValue(value) {
                    if (!(playlistSettingsPosterAssetId instanceof HTMLInputElement)) {
                        return;
                    }
                    const next = String(value || '').trim();
                    pendingPlaylistCoverPreviewUrl = next ? playlistMediaPreviewUrlFromReference(next) : '';
                    playlistSettingsPosterAssetId.value = next;
                    playlistSettingsPosterAssetId.dispatchEvent(new Event('input', { bubbles: true }));
                }

                function updatePlaylistCoverPanel() {
                    const entry = playlistEntry(selectedPlaylistId);
                    if (playlistCoverPanel) {
                        playlistCoverPanel.hidden = !entry;
                    }
                    if (entry && playlistSettingsPosterAssetId instanceof HTMLInputElement && !playlistSettingsDirty()) {
                        const poster = String(entry.poster_asset_id || '').trim();
                        playlistSettingsPosterAssetId.value = poster;
                        if (!pendingPlaylistCoverPreviewUrl) {
                            const preview = String(entry.poster_preview_url || '').trim();
                            pendingPlaylistCoverPreviewUrl = preview || (poster ? playlistMediaPreviewUrlFromReference(poster) : '');
                        }
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

                    window.bandpromoPlaylistCoverPicked = function bandpromoPlaylistCoverPicked(path) {
                        const next = String(path || '').trim();
                        pendingPlaylistCoverPreviewUrl = next ? playlistMediaPreviewUrlFromReference(next) : '';
                        updatePlaylistCoverPreview();
                    };
                }

                function syncPlaylistSettingsPanel(playlistId) {
                    const entry = playlistEntry(playlistId);
                    const title = String(entry?.title || playlistId || '');
                    const publish = normalizePlaylistDateForInput(entry?.publish_date);
                    const packageType = String(entry?.package_type || 'other').trim().toLowerCase() || 'other';
                    const playOrder = String(entry?.play_order || defaultPlayOrderForPackageType(packageType)).trim().toLowerCase() === 'reverse'
                        ? 'reverse'
                        : 'stored';
                    const setAsDefault = Boolean(entry?.is_default);
                    const slug = String(entry?.slug || entry?.id || playlistId || '').trim();
                    const description = String(entry?.description || '').trim();
                    const shortDescription = String(entry?.short_description || '').trim();
                    const posterAssetId = String(entry?.poster_asset_id || '').trim();

                    suppressPlaylistPackageTypeOrderSync = true;
                    if (playlistSettingsTitle instanceof HTMLInputElement) {
                        playlistSettingsTitle.value = title;
                    }
                    if (playlistSettingsPublishDate instanceof HTMLInputElement) {
                        playlistSettingsPublishDate.value = publish;
                        if (typeof window.bandpromoSyncIsoDateField === 'function') {
                            window.bandpromoSyncIsoDateField(playlistSettingsPublishDate);
                        }
                    }
                    if (playlistSettingsPackageType instanceof HTMLSelectElement) {
                        playlistSettingsPackageType.value = packageType;
                        if (playlistSettingsPackageType.value !== packageType) {
                            playlistSettingsPackageType.value = 'other';
                        }
                    }
                    if (playlistSettingsPlayOrder instanceof HTMLSelectElement) {
                        playlistSettingsPlayOrder.value = playOrder;
                    }
                    if (playlistSettingsSetAsDefault instanceof HTMLInputElement) {
                        playlistSettingsSetAsDefault.checked = setAsDefault;
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
                    suppressPlaylistPackageTypeOrderSync = false;
                    pendingPlaylistCoverPreviewUrl = posterAssetId
                        ? (String(entry?.poster_preview_url || '').trim() || playlistMediaPreviewUrlFromReference(posterAssetId))
                        : '';

                    playlistSettingsBaseline = readPlaylistSettingsFromForm();
                    updatePlaylistSlugPreview();
                    updatePlaylistCoverPanel();
                    if (playlistSettingsStatus) {
                        playlistSettingsStatus.textContent = '';
                    }
                }

                async function savePlaylistSettings({ silent = false } = {}) {
                    if (playlistSettingsSaving) {
                        playlistSettingsSaveQueued = true;
                        return true;
                    }
                    if (!(playlistSettingsTitle instanceof HTMLInputElement) || !(playlistSettingsPublishDate instanceof HTMLInputElement)) {
                        return true;
                    }

                    const settings = readPlaylistSettingsFromForm();
                    const {
                        title,
                        publish_date: publishDate,
                        package_type: packageType,
                        play_order: playOrder,
                        set_as_default: setAsDefault,
                        slug,
                        description,
                        short_description: shortDescription,
                        poster_asset_id: posterAssetId,
                    } = settings;

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
                    playlistSettingsSaveQueued = false;
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
                                package_type: packageType,
                                play_order: playOrder,
                                set_as_default: setAsDefault,
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

                        const savedEntry = playlistEntry(selectedPlaylistId);
                        const savedPoster = String(savedEntry?.poster_asset_id || posterAssetId || '').trim();
                        const savedPreview = String(savedEntry?.poster_preview_url || '').trim();
                        if (playlistSettingsPosterAssetId instanceof HTMLInputElement) {
                            playlistSettingsPosterAssetId.value = savedPoster;
                        }
                        if (savedPreview) {
                            pendingPlaylistCoverPreviewUrl = savedPreview;
                        } else if (savedPoster) {
                            pendingPlaylistCoverPreviewUrl = playlistMediaPreviewUrlFromReference(savedPoster);
                        } else {
                            pendingPlaylistCoverPreviewUrl = '';
                        }

                        syncPlaylistSettingsPanel(selectedPlaylistId);
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
                        if (playlistSettingsSaveQueued) {
                            playlistSettingsSaveQueued = false;
                            savePlaylistSettings({ silent: true }).catch(() => {});
                        }
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
                    const publishDate = String(entry.publish_date || '').trim();
                    const releaseTitle = String(entry.release_title || '').trim();
                    const packageLabel = String(entry.package_type_label || '').trim();
                    const parts = [];

                    if (publishDate && releaseTitle) {
                        parts.push(
                            `Published ${bandpromoAdminEscapeHtml(publishDate)} from the release "${bandpromoAdminEscapeHtml(releaseTitle)}"`
                        );
                    } else if (publishDate) {
                        parts.push(`Published ${bandpromoAdminEscapeHtml(publishDate)}`);
                    } else if (releaseTitle) {
                        parts.push(`From the release "${bandpromoAdminEscapeHtml(releaseTitle)}"`);
                    }

                    parts.push(`(${bandpromoAdminEscapeHtml(tracksLabel)})`);

                    let line = parts.join(' ');
                    if (packageLabel) {
                        line += ` · ${bandpromoAdminEscapeHtml(packageLabel)}`;
                    }
                    if (entry.is_default) {
                        line += ' · Default';
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
                    const visible = releaseFilterId
                        ? playlists.filter((entry) => String(entry.release_id || '').trim() === releaseFilterId)
                        : playlists;
                    if (!visible.length) {
                        poolList.innerHTML = releaseFilterId
                            ? '<li class="player-layout-empty">No playlists for this release yet.</li>'
                            : '<li class="player-layout-empty">No playlists available yet.</li>';
                        return;
                    }
                    poolList.innerHTML = visible.map((entry) => {
                        const id = String(entry.id || '');
                        const selectedClass = id === selectedPlaylistId ? ' playlist-editor-row-selected' : '';
                        const title = bandpromoAdminEscapeHtml(entry.title || id);
                        const deleteBtn = playlistCanDelete(entry)
                            ? `<button type="button" class="icon-btn icon-btn--pool icon-btn--danger page-pool-delete-btn" data-playlist-id="${bandpromoAdminEscapeHtml(id)}" title="Delete playlist" aria-label="Delete ${title}">🗑️</button>`
                            : '';
                        return `<li class="playlist-editor-row playlist-pool-row page-pool-row${selectedClass}" data-playlist-id="${bandpromoAdminEscapeHtml(id)}" aria-selected="${id === selectedPlaylistId ? 'true' : 'false'}">
                            <span class="playlist-track-info">
                                <strong>🎵 ${title}</strong>
                                <span class="playlist-track-meta">${playlistPoolMetaHtml(entry)}</span>
                            </span>
                            <span class="page-pool-row-actions">
                                <button type="button" class="icon-btn icon-btn--pool page-pool-edit-btn" data-playlist-id="${bandpromoAdminEscapeHtml(id)}" title="Edit playlist" aria-label="Edit ${title}">✏️</button>
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
                    if (Array.isArray(data.package_types) && data.package_types.length) {
                        const nextDefaults = {};
                        data.package_types.forEach((row) => {
                            const id = String(row?.id || '').trim().toLowerCase();
                            if (!id) {
                                return;
                            }
                            nextDefaults[id] = String(row?.default_play_order || '').trim().toLowerCase() === 'reverse'
                                ? 'reverse'
                                : 'stored';
                        });
                        if (Object.keys(nextDefaults).length) {
                            playlistPackageTypeDefaults = nextDefaults;
                        }
                    }
                    if (releaseFilterId) {
                        const scoped = playlists.filter((entry) => String(entry.release_id || '').trim() === releaseFilterId);
                        defaultPlaylistId = String(scoped[0]?.id || playlists[0]?.id || 'bandpromo-demo');
                    } else {
                        defaultPlaylistId = String(
                            data.default_playlist_id
                            || data.active_playlist_id
                            || data.demo_playlist_id
                            || playlists[0]?.id
                            || 'bandpromo-demo'
                        );
                    }
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
                playlistSettingsPackageType?.addEventListener('change', () => {
                    if (!suppressPlaylistPackageTypeOrderSync && playlistSettingsPlayOrder instanceof HTMLSelectElement) {
                        playlistSettingsPlayOrder.value = defaultPlayOrderForPackageType(
                            playlistSettingsPackageType instanceof HTMLSelectElement
                                ? playlistSettingsPackageType.value
                                : 'other'
                        );
                    }
                    savePlaylistSettings();
                });
                playlistSettingsPlayOrder?.addEventListener('change', () => {
                    savePlaylistSettings();
                });
                playlistSettingsSetAsDefault?.addEventListener('change', () => {
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
                            if (data.warning) {
                                showAdminToast(data.warning, 'warning');
                            } else if (data.player_built_at) {
                                showAdminToast('Playlist saved and ready for the player.', 'success');
                            } else {
                                showAdminToast('Playlist saved.', 'success');
                            }
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
                        mode: 'link',
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
                if (pollTimer) {
                    return 'already-running';
                }
                if (buildBtn && !buildBtn.disabled) {
                    buildBtn.click();
                    return 'started';
                }
                return 'unavailable';
            }

            function maybeRunRecommendedActionFromQuery() {
                if (!pendingBuildRunFromQuery || triggeredBuildRunFromQuery) {
                    return;
                }

                triggeredBuildRunFromQuery = true;
                clearRecommendedRunQuery();
                const postUpdateVersion = consumePostPackageUpdateFlash();
                openBuildLogCard();

                // run_recommended=1 is an explicit follow-up (Site update or Notifications).
                // Always start Rebuild all deliverables — do not gate on build-required
                // state, which can read false after redirect and skip the rebuild while
                // the toast still claimed it was running.
                const runResult = runRecommendedAction();
                if (postUpdateVersion !== null) {
                    showPostPackageUpdateToast(postUpdateVersion, runResult);
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
                        const afterPackageUpdate = currentBuildReasons.includes('package_update');
                        const intro = afterPackageUpdate
                            ? 'Site update preserved your content. Rebuilding deliverables is the normal next step so listener-ready files match the new version.'
                            : `${actionLabel} is the recommended next step for the current pending work.`;
                        buildHelpBox.innerHTML = `${intro} ${taskLine} Jobs continue in the background while this log updates.`;
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
            refreshBuildRequiredState(
                isDeliverablesViewActive()
                    ? { full: true, inventory: true }
                    : { scope: 'lite' }
            ).finally(() => {
                // If the notifications fetch fails, still honor run_recommended=1
                // (Site update follow-up must not depend on that endpoint succeeding).
                maybeRunRecommendedActionFromQuery();
            });

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
                        // Keep Check again visible so a stale quiet-state cache cannot trap operators.
                        refreshBtn.hidden = false;
                    } else if (data.ahead_of_published || data.up_to_date) {
                        setCardMode('quiet');
                        setStatusClass('is-current');
                        setStatusMessage('Your site is up to date. Your content and settings are safe.');
                        // Always offer Check again — notifications cache for 15 minutes and used to
                        // hide this control when quiet, so freshly published tester builds looked missing.
                        refreshBtn.hidden = false;
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
                        // Always hit GitHub for an explicit Check again — do not reuse the
                        // 15-minute notifications cache (that made fresh tester builds look missing).
                        const resp = await fetch('/biblioteca/check-package-update.php', {
                            credentials: 'same-origin',
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || !data || data.ok !== true) {
                            renderPackageUpdateStatus({
                                ok: false,
                                error: (data && data.error) ? data.error : 'Could not check for updates right now.',
                            });
                            return;
                        }

                        latestPackageUpdate = data;
                        latestStatus = data;
                        renderPackageUpdateStatus({
                            ok: true,
                            ...data,
                        });

                        if (typeof refreshBuildRequiredState === 'function') {
                            await refreshBuildRequiredState({ full: true, forcePackage: true });
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
                        const followUpBuild = shouldRunRecommendedBuildAfterPackageUpdate(data.post_update);
                        setStatusMessage(
                            followUpBuild
                                ? (data.message || 'Update installed successfully.') + ' Opening Deliverables…'
                                : (data.message || 'Update installed successfully.')
                        );
                        applyBtn.hidden = true;

                        if (typeof refreshBuildRequiredState === 'function') {
                            await refreshBuildRequiredState({ full: true });
                        }
                        if (typeof refreshBuildHint === 'function') {
                            refreshBuildHint();
                        }

                        window.setTimeout(() => {
                            packageUpdateInstallInProgress = false;
                            if (followUpBuild) {
                                rememberPostPackageUpdateFollowUp(data.installed_version);
                                window.location.href = buildRecommendedRunUrl();
                                return;
                            }
                            window.location.reload();
                        }, followUpBuild ? 1000 : 1800);
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

                // Always hit GitHub on Welcome load. Notifications may still be serving a
                // 15-minute "up to date" cache from before the latest tester release.
                refreshPackageUpdateStatus().catch(() => {
                    if (latestPackageUpdate) {
                        renderPackageUpdateStatus({
                            ok: true,
                            ...latestPackageUpdate,
                        });
                    }
                });
            })();

            (function initSecuritySanityCheck() {
                const card = document.getElementById('securitySanityCard');
                const overallEl = document.getElementById('securitySanityOverall');
                const messageEl = document.getElementById('securitySanityMessage');
                const statusEl = document.getElementById('securitySanityStatus');
                const reportEl = document.getElementById('securitySanityReport');
                const checkBtn = document.getElementById('securitySanityCheckBtn');
                const previewBtn = document.getElementById('securitySanityPreviewBtn');
                const repairBtn = document.getElementById('securitySanityRepairBtn');

                if (!card || !overallEl || !messageEl || !statusEl || !reportEl || !checkBtn || !previewBtn || !repairBtn) {
                    return;
                }

                let latestCheck = null;
                let latestPreview = null;

                function setBusy(busy) {
                    checkBtn.disabled = busy;
                    previewBtn.disabled = busy;
                    repairBtn.disabled = busy;
                }

                function setOverall(status, label) {
                    overallEl.className = 'badge audit-status-badge ' + status;
                    overallEl.textContent = label;
                }

                function statusClassForCheck(status) {
                    if (status === 'ok') {
                        return 'is-ok';
                    }
                    if (status === 'template_missing' || status === 'unreadable' || status === 'invalid' || status === 'error') {
                        return 'is-error';
                    }
                    return 'is-issue';
                }

                function renderCheckReport(report) {
                    latestCheck = report;
                    reportEl.replaceChildren();
                    const checks = Array.isArray(report?.checks) ? report.checks : [];
                    if (!checks.length) {
                        reportEl.hidden = true;
                        return;
                    }

                    checks.forEach((item) => {
                        const li = document.createElement('li');
                        li.className = 'security-sanity-report-item ' + statusClassForCheck(String(item.status || ''));

                        const title = document.createElement('div');
                        title.className = 'security-sanity-report-title';
                        const label = document.createElement('strong');
                        label.textContent = item.label || item.id || 'Check';
                        const badge = document.createElement('span');
                        badge.className = 'badge audit-status-badge ' + (
                            item.status === 'ok' ? 'status-ok'
                                : (item.status === 'missing' || item.status === 'empty' || item.status === 'drifted') ? 'status-warning'
                                    : 'status-error'
                        );
                        badge.textContent = String(item.status || 'unknown');
                        title.appendChild(label);
                        title.appendChild(badge);

                        const path = document.createElement('div');
                        path.className = 'security-sanity-report-path';
                        path.textContent = item.path || '';

                        const detail = document.createElement('p');
                        detail.className = 'security-sanity-report-detail';
                        detail.textContent = item.detail || '';

                        li.appendChild(title);
                        if (item.path) {
                            li.appendChild(path);
                        }
                        if (item.detail) {
                            li.appendChild(detail);
                        }
                        reportEl.appendChild(li);
                    });
                    reportEl.hidden = false;
                }

                function applyCheckUi(report) {
                    const secure = !!(report && report.secure);
                    const repairable = Number(report?.repairable_count || 0);
                    messageEl.textContent = report?.message || '';
                    if (secure) {
                        setOverall('status-ok', 'Secure');
                        previewBtn.hidden = true;
                        repairBtn.hidden = true;
                    } else if (repairable > 0) {
                        setOverall('status-warning', 'Needs repair');
                        previewBtn.hidden = false;
                        repairBtn.hidden = false;
                    } else {
                        setOverall('status-error', 'Needs attention');
                        previewBtn.hidden = true;
                        repairBtn.hidden = true;
                    }
                    renderCheckReport(report || {});
                }

                async function runCheck() {
                    setBusy(true);
                    statusEl.hidden = false;
                    statusEl.textContent = 'Checking install protection…';
                    latestPreview = null;
                    try {
                        const resp = await fetch('/biblioteca/security-sanity-check.php', {
                            method: 'GET',
                            credentials: 'same-origin',
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || data.ok !== true) {
                            throw new Error(data.error || 'Security check failed');
                        }
                        applyCheckUi(data);
                        statusEl.textContent = data.message || 'Check complete.';
                    } catch (error) {
                        setOverall('status-error', 'Check failed');
                        statusEl.textContent = error.message || 'Security check failed';
                        previewBtn.hidden = true;
                        repairBtn.hidden = true;
                    } finally {
                        setBusy(false);
                    }
                }

                async function runRepair(dryRun) {
                    setBusy(true);
                    statusEl.hidden = false;
                    statusEl.textContent = dryRun ? 'Previewing repair…' : 'Repairing managed stubs…';
                    try {
                        const csrfToken = await refreshAdminCsrfToken();
                        const resp = await fetch('/biblioteca/security-sanity-repair.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                dry_run: dryRun,
                                csrf_token: csrfToken,
                            }),
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok || data.ok === false) {
                            throw new Error(data.error || (Array.isArray(data.errors) && data.errors[0]) || 'Repair failed');
                        }
                        latestPreview = dryRun ? data : null;
                        if (data.check) {
                            applyCheckUi(data.check);
                        }
                        statusEl.textContent = data.message || (dryRun ? 'Preview complete.' : 'Repair complete.');
                        if (dryRun) {
                            repairBtn.hidden = Number(data.changed_total || 0) === 0;
                            previewBtn.hidden = false;
                        } else {
                            previewBtn.hidden = Number(data.check?.repairable_count || 0) === 0;
                            repairBtn.hidden = Number(data.check?.repairable_count || 0) === 0;
                        }
                    } catch (error) {
                        statusEl.textContent = error.message || 'Repair failed';
                    } finally {
                        setBusy(false);
                    }
                }

                checkBtn.addEventListener('click', () => {
                    runCheck().catch(() => {});
                });
                previewBtn.addEventListener('click', () => {
                    runRepair(true).catch(() => {});
                });
                repairBtn.addEventListener('click', () => {
                    const count = Number(latestCheck?.repairable_count || latestPreview?.changed_total || 0);
                    if (count <= 0) {
                        return;
                    }
                    const confirmed = window.confirm(
                        'Repair managed Apache/PHP protection stubs from templates?\n\n'
                        + 'Missing and drifted managed files will be overwritten. web-config.json is never changed here.'
                    );
                    if (!confirmed) {
                        return;
                    }
                    runRepair(false).catch(() => {});
                });

                runCheck().catch(() => {});
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
                            ? `<button type="button" class="btn btn-danger-outline site-backup-action-btn site-backup-delete-btn" data-backup-id="${escapeHtml(job.id)}">🗑️ Delete</button>`
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

                const releasePackageImportInput = document.getElementById('releasePackageImportInput');
                const releasePackageImportBtn = document.getElementById('releasePackageImportBtn');
                const releasePackageImportStatus = document.getElementById('releasePackageImportStatus');
                const releasePackageImportFilename = document.getElementById('releasePackageImportFilename');

                if (releasePackageImportInput instanceof HTMLInputElement) {
                    releasePackageImportInput.addEventListener('change', () => {
                        const file = releasePackageImportInput.files && releasePackageImportInput.files[0];
                        if (releasePackageImportFilename) {
                            releasePackageImportFilename.textContent = file ? file.name : '';
                        }
                        if (releasePackageImportStatus) {
                            releasePackageImportStatus.textContent = '';
                            releasePackageImportStatus.classList.remove('is-error');
                        }
                    });
                }

                async function importReleasePackage() {
                    if (!(releasePackageImportInput instanceof HTMLInputElement) || !releasePackageImportInput.files?.length) {
                        if (releasePackageImportStatus) {
                            releasePackageImportStatus.textContent = 'Choose a release package ZIP first.';
                            releasePackageImportStatus.classList.add('is-error');
                        }
                        return;
                    }
                    if (releasePackageImportStatus) {
                        releasePackageImportStatus.textContent = 'Importing…';
                        releasePackageImportStatus.classList.remove('is-error');
                    }
                    if (releasePackageImportBtn instanceof HTMLButtonElement) {
                        releasePackageImportBtn.disabled = true;
                    }
                    try {
                        const csrfToken = typeof refreshAdminCsrfToken === 'function'
                            ? await refreshAdminCsrfToken()
                            : (typeof adminCsrfToken === 'string' ? adminCsrfToken : '');
                        const formData = new FormData();
                        formData.append('package', releasePackageImportInput.files[0]);
                        if (csrfToken) {
                            formData.append('csrf_token', csrfToken);
                        }
                        const response = await fetch('/biblioteca/import-release-package.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData,
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || data.ok === false) {
                            throw new Error(data.error || 'Import failed');
                        }
                        const releaseId = String(data.release_id || '').trim();
                        const message = data.message || 'Release package imported.';
                        if (releasePackageImportStatus) {
                            releasePackageImportStatus.textContent = message;
                        }
                        if (releaseId && window.confirm(`${message}\n\nOpen it in Content → Catalogue?`)) {
                            window.location.href = `?tab=content&cntab=release&release=${encodeURIComponent(releaseId)}&edit=1`;
                        }
                    } catch (error) {
                        if (releasePackageImportStatus) {
                            releasePackageImportStatus.textContent = error.message || 'Import failed';
                            releasePackageImportStatus.classList.add('is-error');
                        }
                    } finally {
                        if (releasePackageImportBtn instanceof HTMLButtonElement) {
                            releasePackageImportBtn.disabled = false;
                        }
                    }
                }

                if (releasePackageImportBtn) {
                    releasePackageImportBtn.addEventListener('click', () => {
                        importReleasePackage().catch(() => {});
                    });
                }
            })();
        })();
