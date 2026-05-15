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
                audio:          { accept: '.flac,.mp3',                    target: 'audio'         },
                video:          { accept: '.mp4,.webm,.mov',               target: 'video'         },
                illustrations:  { accept: '.png,.jpg,.jpeg',               target: 'illustrations' },
                photos:         { accept: '.png,.jpg,.jpeg,.webp',         target: 'photos'        },
                special:        { accept: '.mp3,.mp4,.png,.jpg,.jpeg,.webp,.svg', target: 'special' },
            };
            window.activeMediaPanel = adminActivePanel;
            const buildTabLink = document.querySelector('.primary-tabs .tab-link[href*="tab=build"]');
            const buildRequiredBadge = document.getElementById('buildRequiredBadge');
            const recommendedBuildBtn = document.getElementById('recommendedBuildBtn');
            const toastHost = document.getElementById('adminToastHost');
            const adminCsrf = typeof adminCsrfToken === 'string' ? adminCsrfToken : '';
            let currentBuildRequired = false;
            let currentBuildAction = 'none';
            let currentBuildReasons = [];
            let modalTarget = null;
            let modalFiles  = [];
            let mediaPickerState = null;
            let showBundledDemoAssets = false;

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

            function setBuildRequiredNudge(required, reasons, action) {
                currentBuildRequired = required === true;
                currentBuildAction = typeof action === 'string' ? action : 'none';
                currentBuildReasons = Array.isArray(reasons) ? reasons : [];
                if (!buildTabLink) return;

                buildTabLink.classList.toggle('build-required-nudge', currentBuildRequired);
                buildTabLink.classList.toggle('build-required-pulse', currentBuildRequired);

                if (currentBuildRequired) {
                    const suffix = currentBuildReasons.length ? ` (${currentBuildReasons.join(', ')})` : '';
                    const actionLabel = currentBuildAction === 'optimize' ? 'Media optimization required' : 'Full build required';
                    buildTabLink.title = `${actionLabel} to publish recent updates` + suffix;
                } else {
                    buildTabLink.removeAttribute('title');
                }

                if (!buildRequiredBadge) return;
                if (currentBuildRequired) {
                    buildRequiredBadge.style.display = 'block';
                    if (currentBuildAction === 'optimize') {
                        buildRequiredBadge.textContent = '⚠ Media optimization pending';
                    } else {
                        buildRequiredBadge.textContent = '⚠ Full rebuild pending';
                    }
                } else {
                    buildRequiredBadge.style.display = 'none';
                    buildRequiredBadge.textContent = '';
                }

                if (!recommendedBuildBtn) return;
                if (!currentBuildRequired) {
                    recommendedBuildBtn.style.display = 'none';
                    recommendedBuildBtn.textContent = '';
                    return;
                }

                recommendedBuildBtn.style.display = 'inline-block';
                if (currentBuildAction === 'optimize') {
                    recommendedBuildBtn.textContent = '⚡ Run Recommended: Optimize';
                } else {
                    recommendedBuildBtn.textContent = '⚡ Run Recommended: Full Build';
                }
            }

            async function refreshBuildRequiredState() {
                try {
                    const resp = await fetch('/biblioteca/get-build-required.php');
                    const data = await resp.json();
                    if (!data || data.ok !== true) return;

                    const state = data.build_required_state || {};
                    setBuildRequiredNudge(data.build_required === true, state.reasons || [], state.action || 'none');

                    const statusEl = document.getElementById('buildStatus');
                    if (statusEl && data.build_required === true && !statusEl.textContent) {
                        const actionLabel = (state.action === 'optimize')
                            ? '⚠ New image updates detected. Run media optimization.'
                            : '⚠ New changes detected. Run full build to publish updates.';
                        statusEl.textContent = actionLabel;
                        statusEl.style.color = '#f0b429';
                        statusEl.dataset.mode = 'nudge';
                    } else if (statusEl && data.build_required !== true && statusEl.dataset.mode === 'nudge') {
                        statusEl.textContent = '';
                        statusEl.removeAttribute('data-mode');
                    }
                } catch (e) {
                    // Keep UI usable even if this hint endpoint is temporarily unavailable.
                }
            }

            refreshBuildRequiredState();

            function fmtSize(bytes) {
                if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
                if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
                return bytes + ' B';
            }

            function formatMediaCountSummary(files) {
                const items = Array.isArray(files) ? files : [];
                const count = items.length;
                const totalBytes = items.reduce((sum, file) => sum + Math.max(0, Number(file && file.size) || 0), 0);
                const noun = count === 1 ? 'file' : 'files';
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

            async function loadMediaList(type) {
                const listEl  = document.getElementById('filelist-' + type);
                const countEl = document.getElementById(type + '-count');
                if (!listEl) return;
                try {
                    const files = await fetchMediaFiles(type);
                    if (countEl) countEl.textContent = formatMediaCountSummary(files);
                    if (!files.length) {
                        listEl.innerHTML = '<span class="text-muted">No files yet.</span>';
                        return;
                    }
                    const basePath = getMediaBasePath(type);
                    setAdminPreviewItems(files, type);
                    listEl.innerHTML = files.map(f => {
                        const safeName = f.name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                        const url = buildMediaUrl(type, f.name);
                        let thumb;
                        if (isImage(f.name)) {
                            thumb = `<img class="media-file-thumb" src="${url}" alt="" loading="lazy" onclick="openAdminPreview('${basePath}/${safeName}', '${safeName}')">`;
                        } else if (isVideo(f.name)) {
                            thumb = `<video class="media-file-thumb" src="${url}" preload="metadata" muted onclick="openAdminPreview('${basePath}/${safeName}', '${safeName}')" title="Preview"></video>`;
                        } else {
                            thumb = `<span class="media-file-icon">${extIcon(f.name)}</span>`;
                        }
                        const preview = isPreviewable(f.name)
                            ? `<button class="icon-btn" title="Preview" onclick="openAdminPreview('${basePath}/${safeName}', '${safeName}')">👁️</button>`
                            : '';
                        const details = type === 'audio'
                            ? `<button class="icon-btn" title="Track details" onclick="openAudioMasterModal('${safeName}')">✎</button>`
                            : '';
                        return `<div class="media-file-row">
                            ${thumb}
                            <span class="media-file-name">${f.name}</span>
                            <span class="media-file-size">${fmtSize(f.size)}</span>
                            ${preview}
                            ${details}
                            <button class="icon-btn danger" title="Delete" onclick="openDeleteModal('${type}', '${safeName}')">🗑️</button>
                        </div>`;
                    }).join('');
                } catch(e) {
                    listEl.innerHTML = `<span class="text-error">Network error</span>`;
                }
            }

            // Load active panel
            loadMediaList(activeMediaPanel);

            const showBundledAssetsToggleButtons = document.querySelectorAll('[data-bundled-toggle]');

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

            syncBundledToggleUi();

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
            const deleteNameEl     = document.getElementById('mediaDeleteName');
            const deleteConfirmBtn = document.getElementById('mediaDeleteConfirmBtn');
            const deleteStatusEl   = document.getElementById('mediaDeleteStatus');
            const audioMasterModal = document.getElementById('audioMasterModal');
            const audioMasterTitle = document.getElementById('audioMasterTitle');
            const audioMasterStatus = document.getElementById('audioMasterStatus');
            const audioMasterFormat = document.getElementById('audioMasterFormat');
            const audioMasterDuration = document.getElementById('audioMasterDuration');
            const audioMasterBitrate = document.getElementById('audioMasterBitrate');
            const audioMasterCover = document.getElementById('audioMasterCover');
            const audioMasterSaveBtn = document.getElementById('audioMasterSaveBtn');
            const audioMasterForm = document.getElementById('audioMasterForm');
            let deleteTarget = null;
            let deleteFile   = null;
            let activeAudioMasterFile = null;

            const audioMasterFields = {
                title: document.getElementById('audioMasterFieldTitle'),
                artist: document.getElementById('audioMasterFieldArtist'),
                album: document.getElementById('audioMasterFieldAlbum'),
                date: document.getElementById('audioMasterFieldDate'),
                tracknumber: document.getElementById('audioMasterFieldTracknumber'),
                genre: document.getElementById('audioMasterFieldGenre'),
                comment: document.getElementById('audioMasterFieldComment'),
                lyrics: document.getElementById('audioMasterFieldLyrics'),
            };

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
                if (audioMasterFormat) audioMasterFormat.textContent = String(detail.format || '—').toUpperCase();
                if (audioMasterDuration) audioMasterDuration.textContent = detail.duration_seconds ? formatDuration(detail.duration_seconds) : '—';
                if (audioMasterBitrate) audioMasterBitrate.textContent = detail.bitrate_kbps ? `${detail.bitrate_kbps} kbps` : '—';
                if (audioMasterCover) {
                    if (detail.sidecar_cover) {
                        audioMasterCover.textContent = `Sidecar: ${detail.sidecar_cover}`;
                    } else if (detail.embedded_cover_present) {
                        audioMasterCover.textContent = 'Embedded artwork';
                    } else {
                        audioMasterCover.textContent = 'No track-specific cover';
                    }
                }
            }

            function setAudioMasterFormValues(detail) {
                Object.entries(audioMasterFields).forEach(([key, input]) => {
                    if (!input) return;
                    input.value = detail && typeof detail[key] === 'string' ? detail[key] : '';
                });
            }

            async function loadAudioMasterDetails(filename) {
                if (!filename) return;
                setAudioMasterStatus('Loading…');
                if (audioMasterSaveBtn) audioMasterSaveBtn.disabled = true;
                try {
                    const resp = await fetch(`/biblioteca/get-audio-master-detail.php?filename=${encodeURIComponent(filename)}`);
                    const data = await resp.json();
                    if (!resp.ok || data.error) {
                        throw new Error(data.error || 'Could not load track details');
                    }
                    if (audioMasterTitle) audioMasterTitle.textContent = `Track details · ${filename}`;
                    setAudioMasterSummary(data);
                    setAudioMasterFormValues(data);
                    setAudioMasterStatus('Editing the canonical master copy. Run Full Build after saving.', 'success');
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
                setAudioMasterSummary({});
                setAudioMasterFormValues({});
                loadAudioMasterDetails(filename);
            };

            window.closeAudioMasterModal = function() {
                if (audioMasterModal) audioMasterModal.style.display = 'none';
                activeAudioMasterFile = null;
                if (audioMasterForm) audioMasterForm.reset();
                setAudioMasterSummary({});
                setAudioMasterStatus('');
            };

            // ── Admin media preview — powered by biblioteca/lightbox.js ──────────
            const _adminLb = new Lightbox({
                overlayId:  'adminPreviewLightbox',
                imgId:      'adminPreviewImg',
                vidId:      'adminPreviewVid',
                prevBtnId:  'adminPreviewPrev',
                nextBtnId:  'adminPreviewNext',
                captionId:  'adminPreviewCaption',
                // No contentSelector: close fires when clicking the backdrop itself
            });

            window.openAdminPreview = function(src, name) {
                const items = window._adminPreviewItems || [];
                _adminLb.setItems(items);
                const idx = items.findIndex(i => i.src === src);
                if (idx >= 0) {
                    _adminLb.openAt(idx);
                } else {
                    const ext = name.split('.').pop().toLowerCase();
                    _adminLb.open(src, name, ['mp4','mov','webm'].includes(ext) ? 'video' : 'image');
                }
            };
            window.prevAdminPreview  = (e) => _adminLb.prev(e);
            window.nextAdminPreview  = (e) => _adminLb.next(e);
            window.closeAdminPreview = ()  => _adminLb.close();

            window.openDeleteModal = function(type, filename) {
                deleteTarget = type;
                deleteFile   = filename;
                if (deleteNameEl)  deleteNameEl.textContent = filename;
                if (deleteStatusEl) deleteStatusEl.textContent = '';
                if (deleteConfirmBtn) deleteConfirmBtn.disabled = false;
                if (deleteModal) deleteModal.style.display = 'flex';
            };

            window.closeDeleteModal = function() {
                if (deleteModal) deleteModal.style.display = 'none';
                deleteTarget = null;
                deleteFile   = null;
            };

            if (deleteConfirmBtn) {
                deleteConfirmBtn.addEventListener('click', async () => {
                    if (!deleteTarget || !deleteFile) return;
                    deleteConfirmBtn.disabled = true;
                    deleteStatusEl.textContent = 'Deleting…';
                    try {
                        const resp = await fetch('/biblioteca/delete-media.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ target: deleteTarget, filename: deleteFile }),
                        });
                        const data = await resp.json();
                        if (data.ok) {
                            closeDeleteModal();
                            await loadMediaList(activeMediaPanel);
                            showAdminToast(data.message || 'File removed.', data.action === 'hidden' ? 'success' : 'success');
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

                    audioMasterSaveBtn.disabled = true;
                    setAudioMasterStatus('Saving…');

                    try {
                        const resp = await fetch('/biblioteca/save-audio-master-detail.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                filename: activeAudioMasterFile,
                                fields,
                                csrf_token: adminCsrf,
                            }),
                        });
                        const data = await resp.json();
                        if (!resp.ok || data.error) {
                            throw new Error(data.error || 'Could not save metadata');
                        }

                        const detail = data.detail || {};
                        setAudioMasterSummary(detail);
                        setAudioMasterFormValues(detail);
                        setAudioMasterStatus('Master metadata saved. Full Build is now required.', 'success');
                        if (data.build_required_state) {
                            setBuildRequiredNudge(
                                data.build_required === true,
                                data.build_required_state.reasons || [],
                                data.build_required_state.action || 'none'
                            );
                        }
                        await loadMediaList('audio');
                        showAdminToast('Audio master metadata updated. Full Build is now required.');
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
                            setBuildRequiredNudge(true, latestBuildState.reasons || [], latestBuildState.action || 'none');
                        } else if (done > 0) {
                            await refreshBuildRequiredState();
                        }

                        if (latestBuildState && latestBuildState.required) {
                            const next = latestBuildState.action === 'optimize' ? 'Next: run Optimize Media.' : 'Next: run Full Build.';
                            const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                            showAdminToast(`Upload complete.${masterNote} ${next}`, 'success');
                        } else {
                            const masterNote = masterPreparedCount > 0 ? ` Prepared ${masterPreparedCount} audio master ${masterPreparedCount === 1 ? 'copy' : 'copies'}.` : '';
                            showAdminToast(`Upload complete.${masterNote} No build step needed.`, 'success');
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
                            setBuildRequiredNudge(true, latestBuildState.reasons || [], latestBuildState.action || 'none');
                        } else if (done > 0) {
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
                            setBuildRequiredNudge(data.build_required === true, reasons, action);
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
                            cfgThemeStatus.textContent = Array.isArray(data.auto_tasks) && data.auto_tasks.includes('playlist-scan')
                                ? '✅ Saved and playlist refreshed'
                                : '✅ Saved';
                            cfgThemeStatus.style.color = 'var(--success, #4ade80)';
                            const reasons = (data.build_required_state && data.build_required_state.reasons) || ['theme_config_changed'];
                            const action = (data.build_required_state && data.build_required_state.action) || 'full';
                            setBuildRequiredNudge(data.build_required === true, reasons, action);
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
                        if (file.type === 'video') {
                            row.innerHTML =
                                `<span class="gallery-thumb gallery-thumb--video">▶</span>` +
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
                        const isVideo = item.type === 'video';
                        li.innerHTML =
                            `<span class="playlist-drag-handle" title="Drag to reorder">⠿</span>` +
                            (isVideo
                                ? `<span class="gallery-thumb gallery-thumb--video gallery-thumb--sm">▶</span>`
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
                        name: row.querySelector('.gallery-field-name').value.trim(),
                        alt:  row.querySelector('.gallery-field-alt').value.trim(),
                    }));
                }

                // ── add / remove ─────────────────────────────────────────────
                function addItem(file) {
                    syncFromDOM();
                    activeItems.push({ src: file.src, name: file.name, alt: file.name, type: file.type });
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
                activeEl.addEventListener('dragstart', (e) => {
                    dragSrc = e.target.closest('.gallery-active-row');
                    if (!dragSrc) return;
                    dragSrc.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                activeEl.addEventListener('dragend', () => {
                    activeEl.querySelectorAll('.gallery-active-row').forEach(r => r.classList.remove('dragging', 'drag-over'));
                    dragSrc = null;
                    syncFromDOM();
                });
                activeEl.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    const target = e.target.closest('.gallery-active-row');
                    if (!target || target === dragSrc) return;
                    activeEl.querySelectorAll('.gallery-active-row').forEach(r => r.classList.remove('drag-over'));
                    target.classList.add('drag-over');
                });
                activeEl.addEventListener('dragleave', (e) => {
                    const target = e.target.closest('.gallery-active-row');
                    if (target) target.classList.remove('drag-over');
                });
                activeEl.addEventListener('drop', (e) => {
                    e.preventDefault();
                    const target = e.target.closest('.gallery-active-row');
                    if (!target || target === dragSrc || !dragSrc) return;
                    target.classList.remove('drag-over');
                    const rect = target.getBoundingClientRect();
                    if (e.clientY < rect.top + rect.height / 2) {
                        activeEl.insertBefore(dragSrc, target);
                    } else {
                        activeEl.insertBefore(dragSrc, target.nextSibling);
                    }
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

                function formatPlaylistDuration(seconds) {
                    const duration = Math.max(0, Number(seconds) || 0);
                    if (!duration) return '';
                    return `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, '0')}`;
                }

                function renderPlaylistRows(tracks) {
                    const rows = Array.isArray(tracks) ? tracks : [];
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
                        return `<li class="playlist-editor-row${demoClass}" draggable="true" data-file="${escapeHtml(track.file || '')}">
                            <span class="playlist-drag-handle" title="Drag to reorder">⠿</span>
                            <span class="playlist-track-num">${index + 1}</span>
                            <span class="playlist-track-info">
                                <strong>${title}</strong>
                                <span class="playlist-track-meta">${meta}</span>
                            </span>
                            <span class="playlist-track-duration">${duration}</span>
                        </li>`;
                    }).join('');

                    if (hintEl) {
                        hintEl.textContent = showBundledDemoAssets
                            ? 'Showing current source tracks with bundled demo audio revealed.'
                            : 'Showing current source tracks with bundled demo audio suppressed when real uploads exist.';
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

                list.addEventListener('dragstart', (e) => {
                    dragSrc = e.target.closest('.playlist-editor-row');
                    if (!dragSrc) return;
                    dragSrc.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', dragSrc.dataset.file);
                });

                list.addEventListener('dragend', () => {
                    list.querySelectorAll('.playlist-editor-row').forEach(r => {
                        r.classList.remove('dragging', 'drag-over');
                    });
                    dragSrc = null;
                    renumberRows();
                });

                list.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    const target = e.target.closest('.playlist-editor-row');
                    if (!target || target === dragSrc) return;
                    list.querySelectorAll('.playlist-editor-row').forEach(r => r.classList.remove('drag-over'));
                    target.classList.add('drag-over');
                });

                list.addEventListener('dragleave', (e) => {
                    const target = e.target.closest('.playlist-editor-row');
                    if (target) target.classList.remove('drag-over');
                });

                list.addEventListener('drop', (e) => {
                    e.preventDefault();
                    const target = e.target.closest('.playlist-editor-row');
                    if (!target || target === dragSrc || !dragSrc) return;
                    target.classList.remove('drag-over');

                    // Insert dragSrc before or after target based on cursor position
                    const rect = target.getBoundingClientRect();
                    const mid  = rect.top + rect.height / 2;
                    if (e.clientY < mid) {
                        list.insertBefore(dragSrc, target);
                    } else {
                        list.insertBefore(dragSrc, target.nextSibling);
                    }
                    renumberRows();
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
                                setBuildRequiredNudge(data.build_required === true, reasons, action);
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
            const buildSpinner = document.getElementById('buildSpinner');
            const optimizeSpinner = document.getElementById('optimizeSpinner');
            const buildLog     = document.getElementById('buildLog');
            const buildStatus  = document.getElementById('buildStatus');
            let pollTimer      = null;
            let currentRunMode = 'full';

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

            if (recommendedBuildBtn) {
                recommendedBuildBtn.addEventListener('click', runRecommendedAction);
            }

            function refreshBuildHint() {
                if (!buildStatus) return;
                if (pollTimer) return;
                if (currentBuildRequired) {
                    buildStatus.textContent = currentBuildAction === 'optimize'
                        ? '⚠ New image updates detected. Run media optimization.'
                        : '⚠ New changes detected. Run full build to publish updates.';
                    buildStatus.style.color = '#f0b429';
                    buildStatus.dataset.mode = 'nudge';
                } else if (buildStatus.dataset.mode === 'nudge') {
                    buildStatus.textContent = '';
                    buildStatus.removeAttribute('data-mode');
                }
            }

            refreshBuildHint();

            function scrollLog() {
                if (buildLog) buildLog.scrollTop = buildLog.scrollHeight;
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

                    if (data.build_required_state) {
                        setBuildRequiredNudge(
                            data.build_required === true,
                            data.build_required_state.reasons || [],
                            data.build_required_state.action || 'none'
                        );
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
