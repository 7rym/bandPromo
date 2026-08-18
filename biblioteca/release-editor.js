(function () {
    function initBandpromoReleaseEditor() {
        const editorCard = document.getElementById('releaseEditorCard');
        const poolView = document.getElementById('releasePoolView');
        const tracksPoolView = document.getElementById('releaseTracksPoolView');
        const poolList = document.getElementById('releasePoolList');
        const availableEl = document.getElementById('releaseAvailableList');
        const activeEl = document.getElementById('releaseActiveList');
        const editorHint = document.getElementById('releaseEditorHint');
        const backBtn = document.getElementById('releaseEditorBackBtn');
        const toggleAddReleaseBtn = document.getElementById('toggleAddReleaseBtn');
        const addReleasePanel = document.getElementById('addReleasePanel');
        const addReleaseForm = document.getElementById('addReleaseForm');
        const cancelAddReleaseBtn = document.getElementById('cancelAddReleaseBtn');
        const releaseRegistryStatus = document.getElementById('releaseRegistryStatus');
        const releaseDeleteModal = document.getElementById('releaseDeleteModal');
        const releaseDeleteModalName = document.getElementById('releaseDeleteModalName');
        const releaseDeleteConfirmBtn = document.getElementById('releaseDeleteConfirmBtn');
        const releaseDeleteModePurge = document.getElementById('releaseDeleteModePurge');
        const releaseDeleteModeContainer = document.getElementById('releaseDeleteModeContainer');
        const releaseDeleteCancelBtn = document.getElementById('releaseDeleteCancelBtn');
        const releaseSettingsTitle = document.getElementById('releaseSettingsTitle');
        const releaseSettingsDate = document.getElementById('releaseSettingsDate');
        const releaseSettingsCatalogId = document.getElementById('releaseSettingsCatalogId');
        let releaseSettingsBrandId = document.getElementById('releaseSettingsBrandId');
        const releaseSettingsStatus = document.getElementById('releaseSettingsStatus');
        let releaseSettingsDescription = document.getElementById('releaseSettingsDescription');
        const releaseSettingsShortDescription = document.getElementById('releaseSettingsShortDescription');
        const releaseSettingsShortDescriptionCount = document.getElementById('releaseSettingsShortDescriptionCount');
        const releaseSettingsPosterAssetId = document.getElementById('releaseSettingsPosterAssetId');
        const releaseCoverPanel = document.getElementById('releaseCoverPanel');
        const releaseBaseBrandPreview = document.getElementById('releaseBaseBrandPreview');
        const releaseBaseBrandPreviewBody = document.getElementById('releaseBaseBrandPreviewBody');
        const releaseLongDescriptionPreview = document.getElementById('releaseLongDescriptionPreview');
        const releaseLongDescriptionPreviewBody = document.getElementById('releaseLongDescriptionPreviewBody');
        const releaseCoverPreviewShell = document.getElementById('releaseCoverPreviewShell');
        const releaseCoverPreview = document.getElementById('releaseCoverPreview');
        const releaseCoverPlaceholder = document.getElementById('releaseCoverPlaceholder');
        const releaseCoverClearBtn = document.getElementById('releaseCoverClearBtn');
        const releaseCoverOverlayActions = document.getElementById('releaseCoverOverlayActions');
        const releasePreviewTitle = document.getElementById('releasePreviewTitle');
        const releasePreviewDate = document.getElementById('releasePreviewDate');
        const releasePreviewSummary = document.getElementById('releasePreviewSummary');
        const releaseEditorPreviewHeading = document.getElementById('releaseEditorPreviewHeading');
        const releaseBrandingLivePreview = document.getElementById('releaseBrandingLivePreview');
        const releaseBrandingPreview = document.getElementById('releaseBrandingPreview');
        const releasePresskitLivePreview = document.getElementById('releasePresskitLivePreview');
        const releaseEditorPresskitPreview = document.getElementById('releaseEditorPresskitPreview');
        let releaseSettingsCredits = document.getElementById('releaseSettingsCredits');
        let releaseSettingsPressContact = document.getElementById('releaseSettingsPressContact');
        let releaseSettingsStreamBandpromo = document.getElementById('releaseSettingsStreamBandpromo');
        let releaseSettingsStreamBandpromoLabel = document.getElementById('releaseSettingsStreamBandpromoLabel');
        let releaseSettingsStreamSpotify = document.getElementById('releaseSettingsStreamSpotify');
        let releaseSettingsStreamApple = document.getElementById('releaseSettingsStreamApple');
        let releaseSettingsSocialImports = document.getElementById('releaseSettingsSocialImports');
        let releaseSettingsPressPhotos = document.getElementById('releaseSettingsPressPhotos');
        const releaseAvailableSection = document.getElementById('releaseAvailableSection');
        const releaseAssociationActiveList = document.getElementById('releaseAssociationActiveList');
        const releaseAssociationAvailableSection = document.getElementById('releaseAssociationAvailableSection');
        const releaseAssociationAvailableList = document.getElementById('releaseAssociationAvailableList');
        const releaseAssociationAvailableHeading = document.getElementById('releaseAssociationAvailableHeading');

        if (!editorCard || !poolList || !availableEl || !activeEl) {
            return;
        }

        const PROTECTED_RELEASE_IDS = new Set(['primary', 'bandpromo-demo']);
        const isLocalDevHost = window.BANDPROMO_LOCAL_DEV === true;
        const ASSOCIATION_KINDS = ['playlists', 'galleries', 'pages'];
        const ASSOCIATION_LABELS = {
            playlists: { singular: 'playlist', plural: 'playlists', available: 'Available playlists', associated: 'Associated playlists' },
            galleries: { singular: 'gallery', plural: 'galleries', available: 'Available galleries', associated: 'Associated galleries' },
            pages: { singular: 'page', plural: 'pages', available: 'Available pages', associated: 'Associated pages' },
        };

        function localEscapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        const escapeHtml = typeof window.bandpromoAdminEscapeHtml === 'function'
            ? window.bandpromoAdminEscapeHtml
            : localEscapeHtml;

        function showReleaseToast(message, type = 'warning') {
            const text = String(message || '').replace(/^❌\s*/, '').trim();
            if (!text) {
                return;
            }
            if (typeof window.bandpromoShowAdminToast === 'function') {
                window.bandpromoShowAdminToast(text, type);
                return;
            }

            const toastHost = document.getElementById('adminToastHost');
            if (!toastHost) {
                window.alert(text);
                return;
            }

            const kind = String(type || 'warning').trim().toLowerCase() || 'warning';
            const toast = document.createElement('div');
            toast.className = `admin-toast ${kind}`;
            toast.setAttribute('role', kind === 'error' || kind === 'warning' ? 'alert' : 'status');

            const messageEl = document.createElement('div');
            messageEl.className = 'admin-toast-message';
            messageEl.textContent = text;
            toast.appendChild(messageEl);

            const dismissToast = () => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-4px)';
                toast.style.transition = 'opacity 150ms ease, transform 150ms ease';
                window.setTimeout(() => toast.remove(), 180);
            };
            const dismissBtn = document.createElement('button');
            dismissBtn.type = 'button';
            dismissBtn.className = 'admin-toast-dismiss';
            dismissBtn.setAttribute('aria-label', 'Dismiss notification');
            dismissBtn.textContent = '×';
            dismissBtn.addEventListener('click', dismissToast);
            toast.appendChild(dismissBtn);
            toastHost.appendChild(toast);
        }

        async function fetchJson(url, options) {
            const response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options || {}));
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.ok === false || data.error) {
                throw new Error(data.error || 'Request failed');
            }
            return data;
        }

        let releases = [];
        let selectedReleaseId = String(editorCard.dataset.initialRelease || 'primary');
        let creatingPlaylistFromRelease = false;
        let isEditing = false;
        let releaseEditorTab = 'base';
        let releaseBrandingPreviewToken = 0;
        let releaseBaseBrandPreviewToken = 0;
        let releaseBrandCatalog = [];
        let trackEditorLoadedReleaseId = '';
        let pendingReleaseDeleteId = '';
        let releaseSettingsSaving = false;
        let releaseSettingsSaveQueued = false;
        let pendingReleaseCoverPreviewUrl = '';
        let siteSharing = {
            siteName: 'bandPromo',
            siteUrl: '',
            siteContact: '',
            twitter: '',
            facebook: '',
            instagram: '',
        };

        const STREAMING_PRESET_LABELS = {
            spotify: 'Spotify',
            apple: 'Apple Music',
        };

        function bandpromoSiteLabel() {
            const name = String(siteSharing.siteName || '').trim();
            return name || 'bandPromo';
        }

        let defaultPlayerPlaylistId = 'bandpromo-demo';
        let defaultPlayerPlaylistSlug = 'bandpromo-demo';

        function defaultBandpromoListenUrl() {
            const base = String(siteSharing.siteUrl || '').trim().replace(/\/+$/, '');
            const playlistSegment = encodeURIComponent(defaultPlayerPlaylistSlug || defaultPlayerPlaylistId);
            return base ? `${base}/play/${playlistSegment}` : `/play/${playlistSegment}`;
        }

        function resolvePressContact(stored) {
            const value = String(stored || '').trim();
            if (value) {
                return value;
            }
            return String(siteSharing.siteContact || '').trim();
        }

        function buildSocialProfileUrl(platform, handle) {
            const raw = String(handle || '').trim();
            if (!raw) {
                return '';
            }
            if (/^https?:\/\//i.test(raw)) {
                return raw;
            }
            if (platform === 'twitter') {
                const user = raw.replace(/^@+/, '');
                return user ? `https://twitter.com/${encodeURIComponent(user)}` : '';
            }
            if (platform === 'instagram') {
                const user = raw.replace(/^@+/, '');
                return user ? `https://instagram.com/${encodeURIComponent(user)}` : '';
            }
            if (platform === 'facebook') {
                return `https://facebook.com/${encodeURIComponent(raw.replace(/^@+/, ''))}`;
            }
            return '';
        }

        async function loadSiteSharingContext() {
            try {
                const embedded = window.BANDPROMO_SITE_SHARING;
                if (embedded && typeof embedded === 'object') {
                    siteSharing = {
                        siteName: String(embedded.siteName || 'bandPromo').trim() || 'bandPromo',
                        siteUrl: String(embedded.siteUrl || '').trim(),
                        siteContact: String(embedded.siteContact || '').trim(),
                        twitter: String(embedded.twitter || '').trim(),
                        facebook: String(embedded.facebook || '').trim(),
                        instagram: String(embedded.instagram || '').trim(),
                    };
                    const playlistId = String(embedded.defaultPlaylistId || '').trim();
                    if (playlistId !== '') {
                        defaultPlayerPlaylistId = playlistId;
                    }
                    const playlistSlug = String(embedded.defaultPlaylistSlug || '').trim();
                    if (playlistSlug !== '') {
                        defaultPlayerPlaylistSlug = playlistSlug;
                    }
                    return;
                }
                const response = await fetch('/biblioteca/get-config.php', {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    return;
                }
                const config = await response.json();
                const site = config?.site && typeof config.site === 'object' ? config.site : {};
                const social = config?.social && typeof config.social === 'object' ? config.social : {};
                siteSharing = {
                    siteName: String(site.name || site.short_name || 'bandPromo').trim() || 'bandPromo',
                    siteUrl: String(site.url || '').trim(),
                    siteContact: String(site.email || '').trim(),
                    twitter: String(social.twitter || '').trim(),
                    facebook: String(social.facebook || '').trim(),
                    instagram: String(social.instagram || '').trim(),
                };
                try {
                    const playlistResponse = await fetch('/biblioteca/get-playlists.php', {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });
                    if (playlistResponse.ok) {
                        const playlistData = await playlistResponse.json();
                        if (playlistData?.ok) {
                            defaultPlayerPlaylistId = String(
                                playlistData.active_playlist_id
                                || playlistData.demo_playlist_id
                                || 'bandpromo-demo'
                            );
                            const playlists = Array.isArray(playlistData.playlists) ? playlistData.playlists : [];
                            const activeEntry = playlists.find(
                                (entry) => String(entry?.id || '') === defaultPlayerPlaylistId
                            );
                            defaultPlayerPlaylistSlug = String(
                                activeEntry?.slug || defaultPlayerPlaylistId || 'bandpromo-demo'
                            );
                        }
                    }
                } catch (playlistError) {
                    // Keep default playlist id when registry is unavailable.
                }
            } catch (error) {
                // Keep defaults when config is unavailable.
            }
        }

        function mediaPreviewUrlFromReference(value) {
            const raw = String(value || '').trim().replace(/\\/g, '/');
            if (!raw) {
                return '';
            }
            if (/^https?:\/\//i.test(raw)) {
                return raw;
            }

            const deliveryCardFromAssetId = (id) => {
                const assetId = String(id || '').trim();
                if (!/^ast_[0-9A-HJKMNP-TV-Z]{20}$/i.test(assetId)) {
                    return '';
                }
                return `/media/visual/delivery/${encodeURIComponent(assetId)}/card.jpg`;
            };

            if (raw.startsWith('/media/')) {
                const intakeOriginal = raw.match(/^\/media\/(?:img|photo|visual)\/original\/([^/?#]+)$/i);
                if (intakeOriginal) {
                    const stem = String(intakeOriginal[1] || '').replace(/\.[^.]+$/, '');
                    const card = deliveryCardFromAssetId(stem);
                    if (card) {
                        return card;
                    }
                    // Do not paint multi-MB intake originals in Catalogue chrome.
                    return '';
                }
                const parts = raw.split('/');
                const file = parts.pop() || '';
                return `${parts.join('/')}/${encodeURIComponent(file)}`;
            }

            const basename = raw.includes('/') ? raw.split('/').pop() : raw;
            if (!basename) {
                return '';
            }
            const stem = basename.replace(/\.[^.]+$/, '');
            const card = deliveryCardFromAssetId(stem) || deliveryCardFromAssetId(basename);
            if (card) {
                return card;
            }
            // No bare-filename guess into /media/img/original — wait for server poster_preview_url.
            return '';
        }

        function releaseCoverPreviewUrl(value, entry = null) {
            const raw = String(value || '').trim();
            if (!raw) {
                return pendingReleaseCoverPreviewUrl || '';
            }

            if (pendingReleaseCoverPreviewUrl) {
                const pendingBase = pendingReleaseCoverPreviewUrl.split('?')[0];
                const rawBase = mediaPreviewUrlFromReference(raw).split('?')[0];
                if (!rawBase || pendingBase.endsWith(raw.split('/').pop() || '') || rawBase === pendingBase) {
                    return pendingReleaseCoverPreviewUrl;
                }
            }

            if (/^https?:\/\//i.test(raw) || raw.startsWith('/media/')) {
                return mediaPreviewUrlFromReference(raw);
            }

            const entryRef = entry && String(entry.poster_asset_id || '').trim() === raw
                ? String(entry.poster_preview_url || '').trim()
                : '';
            if (entryRef) {
                return entryRef;
            }

            const cached = releaseEntry(selectedReleaseId);
            if (cached && String(cached.poster_asset_id || '').trim() === raw) {
                const cachedUrl = String(cached.poster_preview_url || '').trim();
                if (cachedUrl) {
                    return cachedUrl;
                }
            }

            return mediaPreviewUrlFromReference(raw);
        }

        function updateReleaseCoverPreview() {
            const entry = releaseEntry(selectedReleaseId);
            const rawValue = releaseSettingsPosterAssetId instanceof HTMLInputElement
                ? String(releaseSettingsPosterAssetId.value || '').trim()
                : '';
            const previewUrl = releaseCoverPreviewUrl(rawValue, entry);

            if (releaseCoverPreview instanceof HTMLImageElement) {
                if (previewUrl) {
                    if (releaseCoverPreview.getAttribute('src') !== previewUrl) {
                        releaseCoverPreview.src = previewUrl;
                    }
                    releaseCoverPreview.style.display = 'block';
                } else {
                    releaseCoverPreview.removeAttribute('src');
                    releaseCoverPreview.style.display = 'none';
                }
            }
            if (releaseCoverPlaceholder) {
                releaseCoverPlaceholder.style.display = previewUrl ? 'none' : 'block';
            }
            if (releaseCoverPreviewShell instanceof HTMLElement) {
                releaseCoverPreviewShell.title = previewUrl ? 'Campaign cover' : 'No cover selected';
            }
            updateReleasePosterLabel();
        }

        function setReleaseCoverValue(value) {
            if (!(releaseSettingsPosterAssetId instanceof HTMLInputElement)) {
                return;
            }
            const next = String(value || '').trim();
            pendingReleaseCoverPreviewUrl = next ? mediaPreviewUrlFromReference(next) : '';
            releaseSettingsPosterAssetId.value = next;
            releaseSettingsPosterAssetId.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function releaseTrackCount(entry) {
            if (!entry) {
                return 0;
            }
            const fromEntry = Number(entry.track_count || 0);
            if (fromEntry > 0) {
                return fromEntry;
            }
            return Array.isArray(activeTracks) ? activeTracks.length : 0;
        }

        function ownershipChildren(entry) {
            const children = entry && entry.ownership_children && typeof entry.ownership_children === 'object'
                ? entry.ownership_children
                : {};
            return {
                playlists: Array.isArray(children.playlists) ? children.playlists : [],
                galleries: Array.isArray(children.galleries) ? children.galleries : [],
                pages: Array.isArray(children.pages) ? children.pages : [],
                brand_id: String(children.brand_id || entry?.brand_id || '').trim(),
                brand: children.brand && typeof children.brand === 'object' ? children.brand : null,
            };
        }

        function updateReleaseCreatePlaylistButton() {
            const button = document.getElementById('releaseCreatePlaylistBtn');
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }
            const entry = releaseEntry(selectedReleaseId);
            const hasTracks = releaseTrackCount(entry) > 0;
            button.disabled = !entry || !hasTracks || creatingPlaylistFromRelease;
            button.textContent = creatingPlaylistFromRelease
                ? 'Creating playlist…'
                : 'Create playlist from campaign';
        }

        function hydrateLazyReleaseControls() {
            releaseSettingsBrandId = document.getElementById('releaseSettingsBrandId');
            releaseSettingsDescription = document.getElementById('releaseSettingsDescription');
            releaseSettingsCredits = document.getElementById('releaseSettingsCredits');
            releaseSettingsPressContact = document.getElementById('releaseSettingsPressContact');
            releaseSettingsStreamBandpromo = document.getElementById('releaseSettingsStreamBandpromo');
            releaseSettingsStreamBandpromoLabel = document.getElementById('releaseSettingsStreamBandpromoLabel');
            releaseSettingsStreamSpotify = document.getElementById('releaseSettingsStreamSpotify');
            releaseSettingsStreamApple = document.getElementById('releaseSettingsStreamApple');
            releaseSettingsSocialImports = document.getElementById('releaseSettingsSocialImports');
            releaseSettingsPressPhotos = document.getElementById('releaseSettingsPressPhotos');
        }

        function populateReleaseBrandSelect() {
            if (!(releaseSettingsBrandId instanceof HTMLSelectElement)) {
                return;
            }
            const selected = String(releaseSettingsBrandId.value || releaseEntry(selectedReleaseId)?.brand_id || '');
            releaseSettingsBrandId.innerHTML = '<option value="">Base brand</option>';
            releaseBrandCatalog.forEach((brand) => {
                const id = String(brand?.id || '').trim();
                if (!id) {
                    return;
                }
                const option = document.createElement('option');
                option.value = id === 'setup-default' ? 'bandpromo-default' : id;
                option.textContent = String(brand?.title || id);
                releaseSettingsBrandId.appendChild(option);
            });
            releaseSettingsBrandId.value = selected;
        }

        function bindLazyReleaseEditorControls(section) {
            if (section === 'branding') {
                if (!(releaseSettingsBrandId instanceof HTMLSelectElement)
                    || releaseSettingsBrandId.dataset.releaseLazyBound === 'true'
                ) {
                    return;
                }
                releaseSettingsBrandId.addEventListener('change', () => {
                    refreshReleaseBrandingLivePreview();
                    refreshReleaseBaseBrandPreview();
                    saveReleaseSettings();
                });
                releaseSettingsBrandId.dataset.releaseLazyBound = 'true';
                return;
            }

            if (section !== 'presskit') {
                return;
            }
            [
                releaseSettingsDescription,
                releaseSettingsCredits,
                releaseSettingsPressContact,
                releaseSettingsStreamBandpromo,
                releaseSettingsStreamSpotify,
                releaseSettingsStreamApple,
                releaseSettingsPressPhotos,
            ].forEach((control) => {
                if (!(control instanceof HTMLElement)
                    || control.dataset.releaseLazyBound === 'true'
                ) {
                    return;
                }
                control.addEventListener('input', () => {
                    renderReleaseEditorPresskitPreview();
                });
                control.addEventListener('blur', () => {
                    saveReleaseSettings();
                });
                control.dataset.releaseLazyBound = 'true';
            });
        }

        async function ensureReleaseEditorSection(section) {
            if (section !== 'branding' && section !== 'presskit') {
                return;
            }
            const panel = document.querySelector(`[data-release-editor-panel="${section}"]`);
            const template = document.getElementById(
                section === 'branding' ? 'releaseBrandingEditorTemplate' : 'releasePresskitEditorTemplate'
            );
            if (!(panel instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
                return;
            }
            if (!panel.dataset.loaded) {
                panel.replaceChildren(template.content.cloneNode(true));
                panel.dataset.loaded = 'true';
            }

            hydrateLazyReleaseControls();
            if (section === 'branding') {
                populateReleaseBrandSelect();
            }
            bindLazyReleaseEditorControls(section);
            syncReleaseSettingsPanel(selectedReleaseId);

            if (section === 'presskit') {
                try {
                    const releaseId = String(selectedReleaseId || '').trim();
                    const data = await fetchJson(
                        `/biblioteca/get-release-preview-section.php?release=${encodeURIComponent(releaseId)}&section=presskit`,
                        { cache: 'no-store' }
                    );
                    const entry = releaseEntry(releaseId);
                    const presskit = data.data && typeof data.data === 'object' ? data.data : {};
                    if (entry) {
                        entry.short_description = String(presskit.short_description || '');
                        entry.description = String(presskit.description || '');
                        entry.epk = presskit.epk && typeof presskit.epk === 'object'
                            ? presskit.epk
                            : defaultReleaseEpk();
                    }
                } catch (error) {
                    showReleaseToast(error.message || 'Could not refresh Press kit editor.', 'error');
                }
            }

            syncReleaseSettingsPanel(selectedReleaseId);
            if (section === 'branding') {
                refreshReleaseBrandingLivePreview();
            } else {
                renderReleaseEditorPresskitPreview();
            }
        }

        function setReleaseEditorTab(tabId) {
            const next = String(tabId || 'base').trim() || 'base';
            const allowed = new Set(['base', 'tracks', 'playlists', 'galleries', 'pages']);
            releaseEditorTab = allowed.has(next) ? next : 'base';
            editorCard.setAttribute('data-release-editor-section', releaseEditorTab);

            document.querySelectorAll('[data-release-editor-tab]').forEach((button) => {
                const active = String(button.getAttribute('data-release-editor-tab') || '') === releaseEditorTab;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-release-editor-panel]').forEach((panel) => {
                const active = String(panel.getAttribute('data-release-editor-panel') || '') === releaseEditorTab;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            });
            syncReleaseEditorMode();
            if (releaseEditorTab === 'tracks'
                && isEditing
                && trackEditorLoadedReleaseId !== selectedReleaseId
            ) {
                loadReleasePreview();
            }
            if (ASSOCIATION_KINDS.includes(releaseEditorTab) && isEditing) {
                ensureAssociationEditorLoaded(releaseEditorTab);
            }
            if (releaseEditorTab === 'base' && isEditing) {
                window.requestAnimationFrame(() => autofitReleaseDescriptionField());
            }
        }

        function renderReleaseEditorPresskitPreview() {
            if (!releaseEditorPresskitPreview) {
                return;
            }
            const entry = releaseEntry(selectedReleaseId);
            if (!entry) {
                releaseEditorPresskitPreview.innerHTML = '<p class="release-preview-empty">No campaign selected.</p>';
                return;
            }
            const metadata = readReleaseMetadataFromForm();
            releaseEditorPresskitPreview.innerHTML = renderReleasePreviewPressKit({
                ...entry,
                short_description: metadata.short_description,
                description: metadata.description,
                epk: metadata.epk,
            });
        }

        async function refreshReleaseBrandingLivePreview() {
            if (!isEditing || releaseEditorTab !== 'branding' || !releaseBrandingPreview) {
                return;
            }
            const brandId = releaseSettingsBrandId instanceof HTMLSelectElement
                ? String(releaseSettingsBrandId.value || '').trim()
                : '';
            const token = ++releaseBrandingPreviewToken;
            releaseBrandingPreview.innerHTML = '<p class="theme-editor-empty">Loading brand preview…</p>';

            try {
                const url = brandId
                    ? `/biblioteca/get-theme.php?theme=${encodeURIComponent(brandId)}`
                    : '/biblioteca/get-theme.php';
                const data = await fetchJson(url, { cache: 'no-store' });
                if (token !== releaseBrandingPreviewToken
                    || releaseEditorTab !== 'branding'
                    || !isEditing
                ) {
                    return;
                }
                if (window.bandpromoThemePreview?.render) {
                    window.bandpromoThemePreview.render(releaseBrandingPreview, data.document || null, {
                        styleId: 'bandpromo-release-brand-preview-style',
                        selector: '#releaseBrandingPreview .theme-preview-shell-chrome',
                    });
                } else {
                    releaseBrandingPreview.innerHTML = '<p class="theme-editor-empty">Brand preview is unavailable.</p>';
                }
            } catch (error) {
                if (token !== releaseBrandingPreviewToken) {
                    return;
                }
                releaseBrandingPreview.innerHTML = `<p class="theme-editor-empty text-error">${escapeHtml(error.message || 'Could not load brand preview.')}</p>`;
            }
        }

        function syncReleaseEditorMode() {
            if (!isEditing) {
                if (releaseBrandingLivePreview) {
                    releaseBrandingLivePreview.hidden = true;
                }
                if (releasePresskitLivePreview) {
                    releasePresskitLivePreview.hidden = true;
                }
                if (releaseEditorPreviewHeading) {
                    releaseEditorPreviewHeading.textContent = 'Preview';
                }
                if (releaseAssociationActiveList) {
                    releaseAssociationActiveList.hidden = true;
                }
                if (releaseAssociationAvailableSection) {
                    releaseAssociationAvailableSection.hidden = true;
                }
                return;
            }
            const baseActive = releaseEditorTab === 'base';
            const tracksActive = releaseEditorTab === 'tracks';
            const associationActive = ASSOCIATION_KINDS.includes(releaseEditorTab);
            const entry = releaseEntry(selectedReleaseId);

            if (releaseCoverPanel) {
                releaseCoverPanel.hidden = !baseActive || !entry;
            }
            if (activeEl) {
                activeEl.hidden = !tracksActive;
            }
            if (releaseAvailableSection) {
                releaseAvailableSection.hidden = !tracksActive;
            }
            if (releaseAssociationActiveList) {
                releaseAssociationActiveList.hidden = !associationActive;
            }
            if (releaseAssociationAvailableSection) {
                releaseAssociationAvailableSection.hidden = !associationActive;
            }
            if (associationActive) {
                const labels = ASSOCIATION_LABELS[releaseEditorTab];
                if (releaseAssociationAvailableHeading && labels) {
                    releaseAssociationAvailableHeading.textContent = labels.available;
                }
                if (releaseAssociationAvailableList && labels) {
                    releaseAssociationAvailableList.setAttribute('aria-label', labels.available);
                }
                if (releaseAssociationActiveList && labels) {
                    releaseAssociationActiveList.setAttribute('aria-label', labels.associated);
                }
                renderAssociationLists();
            }
            if (releaseEditorPreviewHeading) {
                const headings = {
                    base: 'Campaign preview',
                    tracks: 'Associated tracks',
                    playlists: 'Associated playlists',
                    galleries: 'Associated galleries',
                    pages: 'Associated pages',
                };
                releaseEditorPreviewHeading.textContent = headings[releaseEditorTab] || 'Preview';
            }
            refreshReleaseBaseBrandPreview();
            refreshReleaseLongDescriptionPreview();
        }

        function renderReleasePreviewMeta(entry) {
            const title = String(entry?.title || 'Campaign').trim() || 'Campaign';
            const date = String(entry?.release_date || '').trim();
            const summary = String(entry?.short_description || '').trim();
            if (releasePreviewTitle) {
                releasePreviewTitle.textContent = title;
            }
            if (releasePreviewDate) {
                releasePreviewDate.textContent = date;
                releasePreviewDate.hidden = date === '';
            }
            if (releasePreviewSummary) {
                releasePreviewSummary.textContent = summary;
                releasePreviewSummary.hidden = summary === '';
            }
        }

        function updateReleaseBasePreviewFromForm() {
            if (!isEditing || releaseEditorTab !== 'base') {
                return;
            }
            const title = releaseSettingsTitle instanceof HTMLInputElement
                ? String(releaseSettingsTitle.value || '').trim()
                : '';
            const date = releaseSettingsDate instanceof HTMLInputElement
                ? String(releaseSettingsDate.value || '').trim()
                : '';
            const blurb = releaseSettingsShortDescription instanceof HTMLTextAreaElement
                ? String(releaseSettingsShortDescription.value || '').trim()
                : '';

            if (releasePreviewTitle) {
                releasePreviewTitle.textContent = title || 'Campaign';
            }
            if (releasePreviewDate) {
                releasePreviewDate.textContent = date;
                releasePreviewDate.hidden = date === '';
            }
            if (releasePreviewSummary) {
                releasePreviewSummary.textContent = blurb;
                releasePreviewSummary.hidden = blurb === '';
            }
            refreshReleaseLongDescriptionPreview();
        }

        function currentLongDescriptionMarkdown(entry = releaseEntry(selectedReleaseId)) {
            if (isEditing && releaseSettingsDescription instanceof HTMLTextAreaElement) {
                return String(releaseSettingsDescription.value || '').trim();
            }
            return String(entry?.description || '').trim();
        }

        function refreshReleaseLongDescriptionPreview() {
            if (!releaseLongDescriptionPreview || !releaseLongDescriptionPreviewBody) {
                return;
            }
            const coverVisible = !!(releaseCoverPanel && !releaseCoverPanel.hidden);
            const showUnderPreview = coverVisible && (!isEditing || releaseEditorTab === 'base');
            if (!showUnderPreview) {
                releaseLongDescriptionPreview.hidden = true;
                releaseLongDescriptionPreviewBody.innerHTML = '';
                return;
            }

            const markdown = currentLongDescriptionMarkdown();
            releaseLongDescriptionPreview.hidden = false;
            if (!markdown) {
                releaseLongDescriptionPreviewBody.innerHTML = '<p class="release-preview-empty">No long description yet.</p>';
                return;
            }

            const rendered = typeof window.bandpromoPlayerMarkdown?.render === 'function'
                ? window.bandpromoPlayerMarkdown.render(markdown)
                : '';
            if (rendered) {
                releaseLongDescriptionPreviewBody.innerHTML = rendered;
                return;
            }

            releaseLongDescriptionPreviewBody.innerHTML = `<p>${escapeHtml(markdown).replace(/\n/g, '<br>')}</p>`;
        }

        function renderReleasePreviewBranding(entry) {
            const children = ownershipChildren(entry);
            const brand = children.brand;
            const brandId = String(children.brand_id || brand?.id || '').trim();
            if (!brandId) {
                return '<p class="release-preview-empty">No brand linked to this campaign yet.</p>';
            }
            const title = String(brand?.title || brandId).trim() || brandId;
            const mood = String(brand?.mood || '').trim();
            const logo = String(brand?.logo || '').trim();
            const background = String(brand?.background_image || '').trim();
            const tokens = brand?.tokens && typeof brand.tokens === 'object' ? brand.tokens : {};
            const swatches = ['primary', 'secondary', 'background', 'text']
                .map((key) => {
                    const color = String(tokens[key] || '').trim();
                    if (!color) {
                        return '';
                    }
                    return `<span class="release-preview-swatch" title="${escapeHtml(key)}" style="background:${escapeHtml(color)}"></span>`;
                })
                .filter(Boolean)
                .join('');
            const shellStyle = background
                ? ` style="background-image:url('${escapeHtml(background)}')"`
                : '';
            const logoHtml = logo
                ? `<img class="release-preview-brand-logo" src="${escapeHtml(logo)}" alt="">`
                : '<span class="release-preview-empty">No logo assigned</span>';
            return `<div class="release-preview-brand">
                <div class="release-preview-brand-shell"${shellStyle}>${logoHtml}</div>
                <div class="release-preview-brand-copy">
                    <h5 class="release-preview-brand-title">${escapeHtml(title)}</h5>
                    ${mood ? `<p class="release-preview-brand-mood">${escapeHtml(mood)}</p>` : ''}
                    ${swatches ? `<div class="release-preview-swatches">${swatches}</div>` : ''}
                </div>
            </div>`;
        }

        function brandPreviewModelFromThemeDocument(document) {
            if (!document || typeof document !== 'object') {
                return null;
            }
            const id = String(document.id || '').trim();
            if (!id) {
                return null;
            }
            const tokens = document.tokens && typeof document.tokens === 'object' ? document.tokens : {};
            const colors = tokens.color && typeof tokens.color === 'object' ? tokens.color : {};
            const assets = document.assets && typeof document.assets === 'object' ? document.assets : {};
            return {
                id,
                title: String(document.title || id).trim() || id,
                mood: String(document.mood || '').trim(),
                logo: String(assets.logo || '').trim(),
                background_image: String(assets.background_image || '').trim(),
                tokens: {
                    primary: String(colors.primary || '').trim(),
                    secondary: String(colors.secondary || '').trim(),
                    background: String(colors.background || '').trim(),
                    text: String(colors.text || '').trim(),
                },
            };
        }

        function currentBaseBrandId(entry = releaseEntry(selectedReleaseId)) {
            if (isEditing && releaseSettingsBrandId instanceof HTMLSelectElement) {
                return String(releaseSettingsBrandId.value || '').trim();
            }
            const children = ownershipChildren(entry);
            return String(children.brand_id || entry?.brand_id || '').trim();
        }

        async function refreshReleaseBaseBrandPreview() {
            if (!releaseBaseBrandPreview || !releaseBaseBrandPreviewBody) {
                return;
            }
            const coverVisible = !!(releaseCoverPanel && !releaseCoverPanel.hidden);
            const showUnderPreview = coverVisible && (!isEditing || releaseEditorTab === 'base');
            if (!showUnderPreview) {
                releaseBaseBrandPreview.hidden = true;
                releaseBaseBrandPreviewBody.innerHTML = '';
                return;
            }

            const entry = releaseEntry(selectedReleaseId);
            const brandId = currentBaseBrandId(entry);
            const children = ownershipChildren(entry);
            const token = ++releaseBaseBrandPreviewToken;
            releaseBaseBrandPreview.hidden = false;

            if (brandId && children.brand && String(children.brand_id || children.brand.id || '') === brandId) {
                releaseBaseBrandPreviewBody.innerHTML = renderReleasePreviewBranding(entry);
                return;
            }

            if (!brandId && children.brand && !isEditing) {
                releaseBaseBrandPreviewBody.innerHTML = renderReleasePreviewBranding(entry);
                return;
            }

            releaseBaseBrandPreviewBody.innerHTML = '<p class="release-preview-empty">Loading brand preview…</p>';
            try {
                if (!brandId) {
                    releaseBaseBrandPreviewBody.innerHTML = '<p class="release-preview-empty">No brand linked to this campaign yet.</p>';
                    return;
                }
                const url = `/biblioteca/get-theme.php?theme=${encodeURIComponent(brandId)}`;
                const data = await fetchJson(url, { cache: 'no-store' });
                if (token !== releaseBaseBrandPreviewToken) {
                    return;
                }
                const brand = brandPreviewModelFromThemeDocument(data.document || null);
                if (!brand) {
                    releaseBaseBrandPreviewBody.innerHTML = '<p class="release-preview-empty">No brand linked to this campaign yet.</p>';
                    return;
                }
                releaseBaseBrandPreviewBody.innerHTML = renderReleasePreviewBranding({
                    brand_id: brand.id,
                    ownership_children: {
                        brand_id: brand.id,
                        brand,
                    },
                });
            } catch (error) {
                if (token !== releaseBaseBrandPreviewToken) {
                    return;
                }
                releaseBaseBrandPreviewBody.innerHTML = `<p class="release-preview-empty text-error">${escapeHtml(error.message || 'Could not load brand preview.')}</p>`;
            }
        }

        function renderReleasePreviewPressKit(entry) {
            const epk = normalizeReleaseEpk(entry?.epk);
            const description = String(entry?.description || '').trim();
            const shortDescription = String(entry?.short_description || '').trim();
            const rows = [];
            if (shortDescription) {
                rows.push(['Summary', shortDescription]);
            }
            if (description && description !== shortDescription) {
                rows.push(['Description', description]);
            }
            if (epk.credits) {
                rows.push(['Credits', epk.credits]);
            }
            if (epk.press_contact) {
                rows.push(['Press contact', epk.press_contact]);
            }
            if (Array.isArray(epk.streaming_links) && epk.streaming_links.length) {
                rows.push(['Enjoy here', epk.streaming_links.map((link) => `${link.label}: ${link.url}`).join('\n')]);
            }
            if (Array.isArray(epk.press_photo_asset_ids) && epk.press_photo_asset_ids.length) {
                rows.push(['Press photos', `${epk.press_photo_asset_ids.length} asset${epk.press_photo_asset_ids.length === 1 ? '' : 's'}`]);
            }
            if (!rows.length) {
                return '<p class="release-preview-empty">No press kit content yet. Open edit to fill the EPK.</p>';
            }
            return `<dl class="release-preview-epk">${rows.map(([label, value]) => (
                `<dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value).replace(/\n/g, '<br>')}</dd>`
            )).join('')}</dl>`;
        }

        function updateReleaseCoverPanel() {
            const entry = releaseEntry(selectedReleaseId);
            if (releaseCoverPanel) {
                releaseCoverPanel.hidden = !entry || (isEditing && releaseEditorTab !== 'base');
            }
            renderReleasePreviewMeta(entry);
            if (entry && releaseSettingsPosterAssetId instanceof HTMLInputElement && !isEditing) {
                releaseSettingsPosterAssetId.value = String(entry.poster_asset_id || '').trim();
            }
            const canEditCover = !!(isEditing && entry && !entry.locked);
            if (releaseCoverOverlayActions instanceof HTMLElement) {
                releaseCoverOverlayActions.hidden = !isEditing;
            }
            const coverEditButtons = releaseCoverPanel
                ? releaseCoverPanel.querySelectorAll('.audio-master-cover-overlay-actions button')
                : [];
            coverEditButtons.forEach((button) => {
                if (button instanceof HTMLButtonElement) {
                    button.disabled = !canEditCover;
                }
            });
            if (activeEl) {
                activeEl.hidden = !isEditing || releaseEditorTab !== 'tracks';
            }
            updateReleaseCreatePlaylistButton();
            updateReleaseCoverPreview();
            syncReleaseEditorMode();
            refreshReleaseBaseBrandPreview();
            refreshReleaseLongDescriptionPreview();
        }

        async function createPlaylistFromRelease() {
            const entry = releaseEntry(selectedReleaseId);
            if (!entry || releaseTrackCount(entry) <= 0) {
                showReleaseToast('Add tracks to the campaign before creating a playlist.');
                return;
            }

            creatingPlaylistFromRelease = true;
            updateReleaseCreatePlaylistButton();
            try {
                const saved = await saveReleaseSettings({ silent: true });
                if (!saved) {
                    throw new Error('Save the campaign settings before creating a playlist.');
                }

                const data = await fetchJson('/biblioteca/manage-playlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ from_release_id: selectedReleaseId }),
                });
                if (!data.ok) {
                    throw new Error(data.error || 'Could not create playlist');
                }
                const playlistId = String(data.playlist?.id || '').trim();
                if (!playlistId) {
                    throw new Error('Playlist was created but its id is missing.');
                }
                window.location.href = `?tab=content&cntab=playlist&playlist=${encodeURIComponent(playlistId)}&edit=1&release=${encodeURIComponent(selectedReleaseId)}`;
            } catch (error) {
                showReleaseToast(error.message || 'Could not create playlist', 'error');
                creatingPlaylistFromRelease = false;
                updateReleaseCreatePlaylistButton();
            }
        }

        function initReleaseCoverPicker() {
            if (!releaseCoverPanel) {
                return;
            }

            releaseCoverPanel.querySelectorAll('.media-picker-open').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof window.openMediaPicker !== 'function') {
                        showReleaseToast('Media picker is not available. Reload the page.');
                        return;
                    }
                    window.openMediaPicker(
                        button.dataset.field || 'releaseSettingsPosterAssetId',
                        button.dataset.title || 'Choose campaign cover',
                        button.dataset.targets || 'illustrations,photos,special'
                    );
                });
            });

            releaseCoverClearBtn?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                setReleaseCoverValue('');
            });

            window.bandpromoReleaseCoverPicked = function bandpromoReleaseCoverPicked(path) {
                const next = String(path || '').trim();
                pendingReleaseCoverPreviewUrl = next ? mediaPreviewUrlFromReference(next) : '';
                updateReleaseCoverPreview();
            };
        }

        function updateReleasePosterLabel() {
            const input = releaseSettingsPosterAssetId;
            const label = document.getElementById('releaseSettingsPosterAssetId_label');
            if (!(input instanceof HTMLInputElement) || !label) {
                return;
            }
            const emptyLabel = input.dataset.emptyLabel || 'No cover selected';
            const rawValue = String(input.value || '').trim();
            const fileName = rawValue.includes('/') ? rawValue.split('/').pop() : rawValue;
            label.textContent = fileName || emptyLabel;
            label.classList.toggle('empty', !fileName);
        }

        function renderReleaseSocialImports() {
            if (!releaseSettingsSocialImports) {
                return;
            }
            const profiles = [
                { label: 'Twitter / X', url: buildSocialProfileUrl('twitter', siteSharing.twitter) },
                { label: 'Facebook', url: buildSocialProfileUrl('facebook', siteSharing.facebook) },
                { label: 'Instagram', url: buildSocialProfileUrl('instagram', siteSharing.instagram) },
            ].filter((entry) => entry.url);

            if (!profiles.length) {
                releaseSettingsSocialImports.hidden = true;
                releaseSettingsSocialImports.innerHTML = '';
                return;
            }

            releaseSettingsSocialImports.hidden = false;
            releaseSettingsSocialImports.innerHTML = `
                <span class="release-social-inline-label">Social:</span>
                <span class="release-social-inline-links">${profiles.map((entry, index) => (
                    `${index > 0 ? '<span class="release-social-inline-sep" aria-hidden="true">·</span>' : ''}<a href="${escapeHtml(entry.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(entry.label)}</a>`
                )).join('')}</span>
            `;
        }

        function defaultReleaseEpk() {
            return {
                tagline: '',
                genre: '',
                credits: '',
                press_contact: '',
                streaming_links: [],
                press_photo_asset_ids: [],
            };
        }

        function defaultReleaseSettingsBaseline() {
            return {
                title: '',
                release_date: '',
                catalog_id: '',
                locked: false,
                short_description: '',
                description: '',
                poster_asset_id: '',
                brand_id: '',
                epk: defaultReleaseEpk(),
            };
        }

        let releaseSettingsBaseline = defaultReleaseSettingsBaseline();

        function normalizeReleaseEpk(epk) {
            const source = epk && typeof epk === 'object' ? epk : {};
            return {
                tagline: String(source.tagline || '').trim(),
                genre: String(source.genre || '').trim(),
                credits: String(source.credits || '').trim(),
                press_contact: String(source.press_contact || '').trim(),
                streaming_links: Array.isArray(source.streaming_links)
                    ? source.streaming_links.map((link) => ({
                        label: String(link?.label || '').trim(),
                        url: String(link?.url || '').trim(),
                    })).filter((link) => link.label && link.url)
                    : [],
                press_photo_asset_ids: Array.isArray(source.press_photo_asset_ids)
                    ? source.press_photo_asset_ids.map((assetId) => String(assetId || '').trim()).filter(Boolean)
                    : [],
            };
        }

        function autofitReleaseDescriptionField() {
            if (!(releaseSettingsDescription instanceof HTMLTextAreaElement)) {
                return;
            }
            const field = releaseSettingsDescription;
            field.style.height = 'auto';
            field.style.height = `${Math.max(field.scrollHeight, 104)}px`;
        }

        function streamingUrlForLabel(links, label) {
            const needle = String(label || '').trim().toLowerCase();
            const match = (Array.isArray(links) ? links : []).find((link) => (
                String(link?.label || '').trim().toLowerCase() === needle
            ));
            return match ? String(match.url || '').trim() : '';
        }

        function streamingUrlForBandpromo(links) {
            const direct = streamingUrlForLabel(links, bandpromoSiteLabel());
            if (direct) {
                return direct;
            }
            return (Array.isArray(links) ? links : []).find((link) => {
                const label = String(link?.label || '').trim().toLowerCase();
                return label === 'bandpromo' || label === 'this site' || label === 'site';
            })?.url || '';
        }

        function readPressPhotoIdsFromForm() {
            if (!(releaseSettingsPressPhotos instanceof HTMLTextAreaElement)) {
                return Array.isArray(releaseSettingsBaseline.epk?.press_photo_asset_ids)
                    ? releaseSettingsBaseline.epk.press_photo_asset_ids.slice()
                    : [];
            }
            return String(releaseSettingsPressPhotos.value || '')
                .split(/[,\n]+/)
                .map((assetId) => assetId.trim())
                .filter(Boolean);
        }

        function readStreamingLinksFromForm() {
            if (!(releaseSettingsStreamBandpromo instanceof HTMLInputElement)
                && !(releaseSettingsStreamSpotify instanceof HTMLInputElement)
                && !(releaseSettingsStreamApple instanceof HTMLInputElement)
            ) {
                return Array.isArray(releaseSettingsBaseline.epk?.streaming_links)
                    ? releaseSettingsBaseline.epk.streaming_links.map((link) => ({ ...link }))
                    : [];
            }
            const links = [];
            const addLink = (label, input) => {
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }
                const url = String(input.value || '').trim();
                if (url) {
                    links.push({ label, url });
                }
            };

            addLink(bandpromoSiteLabel(), releaseSettingsStreamBandpromo);
            addLink(STREAMING_PRESET_LABELS.spotify, releaseSettingsStreamSpotify);
            addLink(STREAMING_PRESET_LABELS.apple, releaseSettingsStreamApple);

            return links;
        }

        function updateReleaseShortDescriptionCount() {
            if (!(releaseSettingsShortDescription instanceof HTMLTextAreaElement)
                || !releaseSettingsShortDescriptionCount) {
                return;
            }
            releaseSettingsShortDescriptionCount.textContent = String(releaseSettingsShortDescription.value.length);
        }

        function readReleaseMetadataFromForm() {
            return {
                short_description: releaseSettingsShortDescription instanceof HTMLTextAreaElement
                    ? String(releaseSettingsShortDescription.value || '').trim()
                    : '',
                description: releaseSettingsDescription instanceof HTMLTextAreaElement
                    ? String(releaseSettingsDescription.value || '').trim()
                    : String(releaseSettingsBaseline.description || '').trim(),
                poster_asset_id: releaseSettingsPosterAssetId instanceof HTMLInputElement
                    ? String(releaseSettingsPosterAssetId.value || '').trim()
                    : '',
                brand_id: releaseSettingsBrandId instanceof HTMLSelectElement
                    ? String(releaseSettingsBrandId.value || '').trim()
                    : String(releaseSettingsBaseline.brand_id || '').trim(),
                epk: {
                    tagline: String(releaseSettingsBaseline.epk?.tagline || '').trim(),
                    genre: String(releaseSettingsBaseline.epk?.genre || '').trim(),
                    credits: releaseSettingsCredits instanceof HTMLTextAreaElement
                        ? String(releaseSettingsCredits.value || '').trim()
                        : String(releaseSettingsBaseline.epk?.credits || '').trim(),
                    press_contact: releaseSettingsPressContact instanceof HTMLInputElement
                        ? String(releaseSettingsPressContact.value || '').trim()
                        : String(releaseSettingsBaseline.epk?.press_contact || '').trim(),
                    streaming_links: readStreamingLinksFromForm(),
                    press_photo_asset_ids: readPressPhotoIdsFromForm(),
                },
            };
        }

        function readReleaseSettingsFromForm() {
            const entry = releaseEntry(selectedReleaseId);
            const titleFromInput = releaseSettingsTitle instanceof HTMLInputElement
                ? String(releaseSettingsTitle.value || '').trim()
                : '';
            const dateFromInput = releaseSettingsDate instanceof HTMLInputElement
                ? String(releaseSettingsDate.value || '').trim()
                : '';
            const catalogFromInput = releaseSettingsCatalogId instanceof HTMLInputElement
                ? String(releaseSettingsCatalogId.value || '').trim()
                : '';
            const title = titleFromInput || String(entry?.title || '').trim();
            const releaseDate = dateFromInput || normalizeReleaseDateForInput(entry?.release_date);
            const catalogId = catalogFromInput || String(entry?.catalog_id || '').trim();

            return {
                title,
                release_date: releaseDate,
                catalog_id: catalogId,
                locked: !!entry?.locked,
                ...readReleaseMetadataFromForm(),
            };
        }

        function setReleaseMetadataDisabled(disabled) {
            const controls = [
                releaseSettingsShortDescription,
                releaseSettingsDescription,
                releaseSettingsCredits,
                releaseSettingsPressContact,
                releaseSettingsStreamBandpromo,
                releaseSettingsStreamSpotify,
                releaseSettingsStreamApple,
                releaseSettingsPressPhotos,
            ];
            controls.forEach((control) => {
                if (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement) {
                    control.disabled = disabled;
                }
            });
        }

        let activeTracks = [];
        let availableTracks = [];
        let associationPools = {
            playlists: { active: [], available: [], loadedFor: '' },
            galleries: { active: [], available: [], loadedFor: '' },
            pages: { active: [], available: [], loadedFor: '' },
        };
        let associationDragKind = '';
        let associationDragIds = [];
        let associationDragSource = '';
        let tracksPersistToken = 0;
        let tracksPersistPromise = Promise.resolve();
        let associationsPersistToken = {
            playlists: 0,
            galleries: 0,
            pages: 0,
        };
        let associationsPersistPromise = {
            playlists: Promise.resolve(),
            galleries: Promise.resolve(),
            pages: Promise.resolve(),
        };
        let dragSourceRow = null;
        let draggedRows = [];
        let dragSourceList = '';
        let dragPlaceholder = null;
        let selectedAvailable = new Set();
        let selectedActive = new Set();
        let selectionAnchorAvailable = '';
        let selectionAnchorActive = '';
        let suppressNextClick = false;

        function cloneAssociationItem(item) {
            return {
                id: String(item?.id || '').trim(),
                title: String(item?.title || '').trim(),
                publish_date: String(item?.publish_date || '').trim(),
                release_id: String(item?.release_id || '').trim(),
                movable: item?.movable !== false,
            };
        }

        function resetAssociationPools() {
            ASSOCIATION_KINDS.forEach((kind) => {
                associationPools[kind] = { active: [], available: [], loadedFor: '' };
            });
        }

        function currentAssociationKind() {
            return ASSOCIATION_KINDS.includes(releaseEditorTab) ? releaseEditorTab : '';
        }

        function associationEditingEnabled(entry = releaseEntry(selectedReleaseId)) {
            return !!(isEditing && entry && !entry.locked);
        }

        function sortAssociationItems(kind, items) {
            const rows = (Array.isArray(items) ? items : []).map(cloneAssociationItem).filter((item) => item.id);
            if (kind === 'playlists') {
                rows.sort((left, right) => {
                    const dateCompare = String(right.publish_date || '').localeCompare(String(left.publish_date || ''));
                    if (dateCompare !== 0) {
                        return dateCompare;
                    }
                    return String(left.title || '').localeCompare(String(right.title || ''), undefined, { sensitivity: 'base' });
                });
                return rows;
            }
            rows.sort((left, right) => String(left.title || '').localeCompare(
                String(right.title || ''),
                undefined,
                { sensitivity: 'base' }
            ));
            return rows;
        }

        function renderAssociationRow(item, { showRemove = false, draggable = false, canEdit = false } = {}) {
            const id = escapeHtml(item.id || '');
            const title = escapeHtml(item.title || item.id || 'Untitled');
            const meta = item.publish_date
                ? `<span class="playlist-track-meta">${escapeHtml(item.publish_date)}</span>`
                : '';
            const removeMarkup = showRemove
                ? '<button type="button" class="player-layout-remove-btn" title="Remove from campaign" aria-label="Remove from campaign">✕</button>'
                : '';
            const dragHandle = draggable
                ? '<span class="playlist-drag-handle" title="Drag into release">⠿</span>'
                : '';
            const readonlyClass = canEdit ? '' : ' playlist-editor-row-readonly';
            const activeRowClass = showRemove || !draggable ? ' player-layout-row-active' : '';
            return `<li class="playlist-editor-row${activeRowClass}${readonlyClass}" draggable="${draggable ? 'true' : 'false'}" data-id="${id}">
                ${dragHandle}
                <span class="playlist-track-info">
                    <strong>${title}</strong>
                    ${meta}
                </span>
                ${removeMarkup}
            </li>`;
        }

        function renderAssociationLists() {
            const kind = currentAssociationKind();
            if (!kind || !releaseAssociationActiveList || !releaseAssociationAvailableList) {
                return;
            }
            const labels = ASSOCIATION_LABELS[kind];
            const pool = associationPools[kind];
            const canEdit = associationEditingEnabled();
            const active = sortAssociationItems(kind, pool.active);
            const available = sortAssociationItems(kind, pool.available);
            pool.active = active;
            pool.available = available;

            if (pool.loadedFor !== selectedReleaseId) {
                releaseAssociationActiveList.innerHTML = '<li class="player-layout-empty">Loading…</li>';
                releaseAssociationAvailableList.innerHTML = '<li class="player-layout-empty">Loading…</li>';
                return;
            }

            if (!active.length) {
                releaseAssociationActiveList.innerHTML = canEdit
                    ? `<li class="player-layout-empty">Drag ${labels.plural} here from ${labels.available}.</li>`
                    : `<li class="player-layout-empty">This release has no ${labels.plural} yet.</li>`;
            } else {
                releaseAssociationActiveList.innerHTML = active.map((item) => renderAssociationRow(item, {
                    showRemove: canEdit && item.movable !== false,
                    draggable: false,
                    canEdit,
                })).join('');
            }

            if (!available.length) {
                const contentTab = labels.plural.charAt(0).toUpperCase() + labels.plural.slice(1);
                const emptyMessage = canEdit
                    ? (active.length
                        ? `No unassigned ${labels.plural} to add. Unassigned ${labels.plural} would appear here; every other ${labels.singular} is already owned by another campaign.`
                        : `No unassigned ${labels.plural} to add. Unassigned ${labels.plural} would appear here. Create one in Content → ${contentTab}, or unassign one from another campaign.`)
                    : `${labels.associated} are preview-only while this campaign is locked.`;
                releaseAssociationAvailableList.innerHTML = `<li class="player-layout-empty">${emptyMessage}</li>`;
            } else {
                releaseAssociationAvailableList.innerHTML = available.map((item) => renderAssociationRow(item, {
                    showRemove: false,
                    draggable: canEdit && item.movable !== false,
                    canEdit,
                })).join('');
            }

        }

        function moveAssociationItems(kind, fromList, toList, ids) {
            if (!associationEditingEnabled()) {
                return false;
            }
            const pool = associationPools[kind];
            if (!pool || pool.loadedFor !== selectedReleaseId) {
                return false;
            }
            const idSet = new Set((ids || []).map((id) => String(id || '').trim()).filter(Boolean));
            if (!idSet.size) {
                return false;
            }
            const source = fromList === 'active' ? pool.active : pool.available;
            const moving = source.filter((item) => idSet.has(String(item.id || '')) && item.movable !== false);
            if (!moving.length) {
                return false;
            }
            if (fromList === 'active') {
                pool.active = pool.active.filter((item) => !idSet.has(String(item.id || '')));
            } else {
                pool.available = pool.available.filter((item) => !idSet.has(String(item.id || '')));
            }
            const target = toList === 'active' ? pool.active : pool.available;
            moving.forEach((item) => {
                const clone = cloneAssociationItem(item);
                clone.release_id = toList === 'active' ? selectedReleaseId : '';
                target.push(clone);
            });
            pool.active = sortAssociationItems(kind, pool.active);
            pool.available = sortAssociationItems(kind, pool.available);
            renderAssociationLists();
            void persistReleaseAssociations(kind);
            return true;
        }

        async function ensureAssociationEditorLoaded(kind) {
            if (!ASSOCIATION_KINDS.includes(kind) || !selectedReleaseId || !isEditing) {
                return;
            }
            const pool = associationPools[kind];
            if (pool.loadedFor === selectedReleaseId) {
                renderAssociationLists();
                return;
            }
            try {
                const data = await fetchJson(
                    `/biblioteca/get-release-associations.php?release=${encodeURIComponent(selectedReleaseId)}&kind=${encodeURIComponent(kind)}`
                );
                if (!isEditing || selectedReleaseId !== String(data.release_id || '') || releaseEditorTab !== kind) {
                    return;
                }
                associationPools[kind] = {
                    active: Array.isArray(data.active) ? data.active.map(cloneAssociationItem) : [],
                    available: Array.isArray(data.available) ? data.available.map(cloneAssociationItem) : [],
                    loadedFor: selectedReleaseId,
                };
                renderAssociationLists();
            } catch (error) {
                if (releaseAssociationActiveList) {
                    releaseAssociationActiveList.innerHTML = `<li class="player-layout-empty" style="color:#f87171">${escapeHtml(error.message || 'Could not load associations')}</li>`;
                }
                if (releaseAssociationAvailableList) {
                    releaseAssociationAvailableList.innerHTML = '<li class="player-layout-empty"></li>';
                }
            }
        }

        async function persistReleaseAssociations(kind) {
            if (!ASSOCIATION_KINDS.includes(kind) || !associationEditingEnabled()) {
                return true;
            }
            const pool = associationPools[kind];
            if (!pool || pool.loadedFor !== selectedReleaseId) {
                return true;
            }
            const releaseId = selectedReleaseId;
            const token = ++associationsPersistToken[kind];
            const ids = pool.active.map((item) => String(item.id || '')).filter(Boolean);
            const work = (async () => {
                try {
                    const data = await fetchJson(
                        `/biblioteca/save-release-associations.php?release=${encodeURIComponent(releaseId)}`,
                        {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ kind, ids }),
                        }
                    );
                    if (token !== associationsPersistToken[kind] || releaseId !== selectedReleaseId) {
                        return true;
                    }
                    associationPools[kind] = {
                        active: Array.isArray(data.active) ? data.active.map(cloneAssociationItem) : [],
                        available: Array.isArray(data.available) ? data.available.map(cloneAssociationItem) : [],
                        loadedFor: releaseId,
                    };
                    const entry = releaseEntry(releaseId);
                    if (entry && data.ownership_children && typeof data.ownership_children === 'object') {
                        entry.ownership_children = data.ownership_children;
                    }
                    if (kind === currentAssociationKind()) {
                        renderAssociationLists();
                    }
                    renderReleasePoolList();
                    return true;
                } catch (error) {
                    if (token !== associationsPersistToken[kind]) {
                        return false;
                    }
                    showReleaseToast(error.message || 'Could not save associations', 'error');
                    associationPools[kind] = { active: [], available: [], loadedFor: '' };
                    await ensureAssociationEditorLoaded(kind);
                    return false;
                }
            })();
            associationsPersistPromise[kind] = work;
            return work;
        }

        async function persistReleaseTracks() {
            if (!releaseTrackEditingEnabled() || trackEditorLoadedReleaseId !== selectedReleaseId) {
                return true;
            }
            const releaseId = selectedReleaseId;
            const token = ++tracksPersistToken;
            const order = activeTracks.map((track) => String(track.file || '')).filter(Boolean);
            const work = (async () => {
                try {
                    const data = await fetchJson(`/biblioteca/save-release-tracks.php?release=${encodeURIComponent(releaseId)}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(order),
                    });
                    if (token !== tracksPersistToken || releaseId !== selectedReleaseId) {
                        return true;
                    }
                    const preview = await fetchJson(`/biblioteca/get-release-preview.php?release=${encodeURIComponent(releaseId)}`);
                    if (token !== tracksPersistToken || releaseId !== selectedReleaseId) {
                        return true;
                    }
                    applyPreviewData(preview);
                    await loadReleaseRegistry();
                    if (data.warning) {
                        showReleaseToast(data.warning, 'warning');
                    }
                    return true;
                } catch (error) {
                    if (token !== tracksPersistToken) {
                        return false;
                    }
                    showReleaseToast(error.message || 'Could not save campaign tracks', 'error');
                    trackEditorLoadedReleaseId = '';
                    await loadReleasePreview();
                    return false;
                }
            })();
            tracksPersistPromise = work;
            return work;
        }

        async function flushMembershipSaves() {
            await tracksPersistPromise;
            await Promise.all(ASSOCIATION_KINDS.map((kind) => associationsPersistPromise[kind]));
        }

        function bindAssociationDragList(listEl, listName) {
            if (!listEl) {
                return;
            }

            function clearAssociationDragUi() {
                document.querySelectorAll('.release-association-row-dragging').forEach((row) => {
                    row.classList.remove('release-association-row-dragging');
                });
                releaseAssociationActiveList?.classList.remove('is-drop-target');
                releaseAssociationAvailableList?.classList.remove('is-drop-target');
            }

            listEl.addEventListener('dragstart', (event) => {
                const kind = currentAssociationKind();
                if (!kind || !associationEditingEnabled()) {
                    event.preventDefault();
                    return;
                }
                const row = event.target instanceof HTMLElement
                    ? event.target.closest('.playlist-editor-row')
                    : null;
                if (!row || !listEl.contains(row) || row.getAttribute('draggable') !== 'true') {
                    return;
                }
                const id = String(row.dataset.id || '').trim();
                if (!id) {
                    event.preventDefault();
                    return;
                }
                associationDragKind = kind;
                associationDragSource = listName;
                associationDragIds = [id];
                row.classList.add('release-association-row-dragging');
                try {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', id);
                    event.dataTransfer.setData('application/x-bandpromo-association', id);
                } catch (error) {
                    // Some hosts reject custom MIME types; text/plain is enough.
                }
            });

            listEl.addEventListener('dragover', (event) => {
                if (!associationDragIds.length || associationDragKind !== currentAssociationKind()) {
                    return;
                }
                const canDropHere = listName === 'active' && associationDragSource === 'available';
                if (!canDropHere) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                event.dataTransfer.dropEffect = 'move';
                listEl.classList.add('is-drop-target');
            });

            listEl.addEventListener('dragleave', (event) => {
                const related = event.relatedTarget instanceof Node ? event.relatedTarget : null;
                if (related && listEl.contains(related)) {
                    return;
                }
                listEl.classList.remove('is-drop-target');
            });

            listEl.addEventListener('drop', (event) => {
                const kind = associationDragKind || currentAssociationKind();
                const ids = associationDragIds.slice();
                const from = associationDragSource;
                listEl.classList.remove('is-drop-target');
                if (!ids.length || kind !== currentAssociationKind()) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();
                if (listName === 'active' && from === 'available') {
                    moveAssociationItems(kind, 'available', 'active', ids);
                }
                clearAssociationDragUi();
                associationDragKind = '';
                associationDragIds = [];
                associationDragSource = '';
            });

            listEl.addEventListener('dragend', () => {
                clearAssociationDragUi();
                associationDragKind = '';
                associationDragIds = [];
                associationDragSource = '';
            });
        }

        function validateReleaseDate(value) {
            const trimmed = String(value || '').trim();
            if (trimmed === '') {
                return 'Campaign date is required.';
            }
            if (!/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
                return 'Campaign date must use YYYY-MM-DD.';
            }
            return '';
        }

        function cloneTrack(track) {
            return {
                file: track.file,
                asset_id: track.asset_id,
                title: track.title,
                version: track.version,
                artist: track.artist,
                album: track.album,
                duration: track.duration,
                release_date: String(track.release_date || ''),
                origin: track.origin,
                sourceTier: track.sourceTier,
                deliveryReady: track.deliveryReady !== false,
                release_id: String(track.release_id || ''),
                originReleaseId: String(track.originReleaseId || track.release_id || ''),
            };
        }

        function releaseEntry(releaseId) {
            return releases.find((entry) => entry && entry.id === releaseId) || null;
        }

        function releaseIsProtected(entryOrId) {
            const releaseId = typeof entryOrId === 'string'
                ? entryOrId
                : String(entryOrId?.id || '');
            return PROTECTED_RELEASE_IDS.has(releaseId);
        }

        function releaseIsPlatformDemo(entryOrId) {
            if (typeof entryOrId === 'object' && entryOrId && typeof entryOrId.platform_demo === 'boolean') {
                return entryOrId.platform_demo;
            }
            const releaseId = typeof entryOrId === 'string'
                ? entryOrId
                : String(entryOrId?.id || '');
            return releaseId === 'bandpromo-demo';
        }

        function releaseMayChangeLock(entryOrId) {
            if (typeof entryOrId === 'object' && entryOrId && typeof entryOrId.can_change_lock === 'boolean') {
                return entryOrId.can_change_lock;
            }
            if (!releaseIsPlatformDemo(entryOrId)) {
                return true;
            }
            return isLocalDevHost;
        }

        /** @deprecated Demo is a normal locked release; kept for older call sites. */
        function releaseIsSystemManaged() {
            return false;
        }

        function releaseCanDelete(entry) {
            return !!entry && !releaseIsProtected(entry);
        }

        function releaseCanOpenEditor(entry) {
            return !!entry;
        }

        function releaseTrackEditingEnabled(entry = releaseEntry(selectedReleaseId)) {
            return !!(isEditing && entry && !entry.locked);
        }

        function formatReleaseDuration(seconds) {
            const duration = Math.max(0, Number(seconds) || 0);
            if (!duration) {
                return '';
            }
            return `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, '0')}`;
        }

        function splitTrackTitleParts(value) {
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

        function combineTrackTitleParts(title, version) {
            const normalizedTitle = String(title || '').trim();
            const normalizedVersion = String(version || '').trim();
            if (!normalizedVersion) {
                return normalizedTitle;
            }
            return `${normalizedTitle} [${normalizedVersion}]`;
        }

        function displayTrackTitle(track) {
            const rawTitle = String(track?.title || track?.file || 'Untitled').trim();
            const versionFromField = String(track?.version || '').trim();
            const parts = splitTrackTitleParts(rawTitle);
            let title = String(parts.title || rawTitle || 'Untitled').trim();
            title = title.replace(/^\d+\.\s+/, '').replace(/^\d{1,2}\s+(?=[A-Za-z])/, '');
            const version = versionFromField || String(parts.version || '').trim();
            return combineTrackTitleParts(title, version) || 'Untitled';
        }

        function trackMeta(track) {
            const artist = String(track.artist || '').trim();
            const parts = [];

            if (artist) {
                parts.push(artist);
            }

            const releaseId = String(track.release_id || '').trim();
            if (releaseId && releaseId !== selectedReleaseId) {
                const releaseLabel = String(releaseEntry(releaseId)?.title || releaseId).trim();
                if (releaseLabel) {
                    parts.push(`on ${releaseLabel}`);
                }
            }

            return parts.join(' · ');
        }

        function releasePoolMetaHtml(entry) {
            if (!entry) {
                return '';
            }

            const trackCount = Number(entry.track_count || 0);
            const tracksLabel = trackCount === 1 ? '1 track' : `${trackCount} tracks`;
            const releaseDate = escapeHtml(String(entry.release_date || '').trim());

            let line = escapeHtml(tracksLabel);
            if (releaseDate) {
                line += ` released ${releaseDate}`;
            }

            return line;
        }

        function sortReleaseEntries(list) {
            return [...list].sort((left, right) => {
                const leftDate = String(left?.release_date || '');
                const rightDate = String(right?.release_date || '');
                const dateCompare = rightDate.localeCompare(leftDate);
                if (dateCompare !== 0) {
                    return dateCompare;
                }
                return String(left?.title || left?.id || '').localeCompare(
                    String(right?.title || right?.id || ''),
                    undefined,
                    { sensitivity: 'base' }
                );
            });
        }

        function syncReleaseUrl(releaseId, editing = isEditing) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'content');
            url.searchParams.set('cntab', 'release');
            url.searchParams.set('release', releaseId);
            if (editing) {
                url.searchParams.set('edit', '1');
            } else {
                url.searchParams.delete('edit');
            }
            window.history.replaceState({}, '', url.toString());

            // Keep Content → Catalogue tab href in sync (baked at page render otherwise
            // pins the prior release — often bandpromo-demo — after create/switch).
            document.querySelectorAll('a.tab-link[href*="cntab=release"]').forEach((link) => {
                try {
                    const href = new URL(link.getAttribute('href') || '', window.location.origin);
                    href.searchParams.set('tab', 'content');
                    href.searchParams.set('cntab', 'release');
                    href.searchParams.set('release', releaseId);
                    href.searchParams.delete('edit');
                    link.setAttribute('href', `${href.pathname}${href.search}`);
                } catch (_error) {
                    // ignore malformed hrefs
                }
            });
        }

        function setAddReleasePanelOpen(open) {
            if (!addReleasePanel || !toggleAddReleaseBtn) {
                return;
            }
            addReleasePanel.hidden = !open;
            toggleAddReleaseBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggleAddReleaseBtn.classList.toggle('active', open);
            if (open) {
                const titleInput = addReleaseForm?.querySelector('input[name="title"]');
                if (titleInput instanceof HTMLInputElement) {
                    titleInput.focus();
                }
            } else if (releaseRegistryStatus) {
                releaseRegistryStatus.textContent = '';
                releaseRegistryStatus.style.color = '';
            }
        }

        function normalizeReleaseDateForInput(value) {
            if (typeof window.bandpromoNormalizeIsoDateInput === 'function') {
                return window.bandpromoNormalizeIsoDateInput(value);
            }
            const trimmed = String(value || '').trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
                return trimmed;
            }
            if (/^\d{4}$/.test(trimmed)) {
                return `${trimmed}-01-01`;
            }
            return '';
        }

        function releaseSettingsDirty() {
            return JSON.stringify(readReleaseSettingsFromForm()) !== JSON.stringify(releaseSettingsBaseline);
        }

        function syncReleaseSettingsPanel(releaseId) {
            const entry = releaseEntry(releaseId);
            const title = String(entry?.title || releaseId || '');
            const releaseDate = normalizeReleaseDateForInput(entry?.release_date);
            const locked = !!entry?.locked;
            const metadataLocked = locked;
            const description = String(entry?.description || '').trim();
            const shortDescription = String(entry?.short_description || '').trim();
            const catalogId = String(entry?.catalog_id || '').trim();
            const posterAssetId = String(entry?.poster_asset_id || '').trim();
            const brandId = String(entry?.brand_id || '').trim();
            const epk = normalizeReleaseEpk(entry?.epk);
            const bandpromoListenUrl = streamingUrlForBandpromo(epk.streaming_links);

            releaseSettingsBaseline = {
                title,
                release_date: releaseDate,
                catalog_id: catalogId,
                locked,
                short_description: shortDescription,
                description,
                poster_asset_id: posterAssetId,
                brand_id: brandId,
                epk,
            };

            if (releaseSettingsStreamBandpromoLabel) {
                releaseSettingsStreamBandpromoLabel.textContent = bandpromoSiteLabel();
            }
            renderReleaseSocialImports();

            if (releaseSettingsTitle instanceof HTMLInputElement) {
                releaseSettingsTitle.value = title;
                releaseSettingsTitle.disabled = metadataLocked;
            }
            if (releaseSettingsDate instanceof HTMLInputElement) {
                releaseSettingsDate.value = releaseDate;
                releaseSettingsDate.disabled = metadataLocked;
                if (typeof window.bandpromoSyncIsoDateField === 'function') {
                    window.bandpromoSyncIsoDateField(releaseSettingsDate);
                }
            }
            if (releaseSettingsCatalogId instanceof HTMLInputElement) {
                releaseSettingsCatalogId.value = catalogId;
                releaseSettingsCatalogId.disabled = metadataLocked;
            }
            if (releaseSettingsBrandId instanceof HTMLSelectElement) {
                releaseSettingsBrandId.value = brandId;
                releaseSettingsBrandId.disabled = metadataLocked;
            }
            if (releaseSettingsShortDescription instanceof HTMLTextAreaElement) {
                releaseSettingsShortDescription.value = shortDescription;
                updateReleaseShortDescriptionCount();
            }
            if (releaseSettingsDescription instanceof HTMLTextAreaElement) {
                releaseSettingsDescription.value = description;
                autofitReleaseDescriptionField();
            }
            if (releaseSettingsPosterAssetId instanceof HTMLInputElement) {
                releaseSettingsPosterAssetId.value = posterAssetId;
            }
            if (releaseSettingsCredits instanceof HTMLTextAreaElement) {
                releaseSettingsCredits.value = epk.credits;
            }
            if (releaseSettingsPressContact instanceof HTMLInputElement) {
                releaseSettingsPressContact.value = resolvePressContact(epk.press_contact);
            }
            if (releaseSettingsStreamBandpromo instanceof HTMLInputElement) {
                releaseSettingsStreamBandpromo.value = bandpromoListenUrl;
            }
            if (releaseSettingsStreamSpotify instanceof HTMLInputElement) {
                releaseSettingsStreamSpotify.value = streamingUrlForLabel(epk.streaming_links, STREAMING_PRESET_LABELS.spotify);
            }
            if (releaseSettingsStreamApple instanceof HTMLInputElement) {
                releaseSettingsStreamApple.value = streamingUrlForLabel(epk.streaming_links, STREAMING_PRESET_LABELS.apple);
            }
            if (releaseSettingsPressPhotos instanceof HTMLTextAreaElement) {
                releaseSettingsPressPhotos.value = epk.press_photo_asset_ids.join(', ');
            }

            releaseSettingsBaseline = readReleaseSettingsFromForm();
            releaseSettingsBaseline.locked = locked;

            setReleaseMetadataDisabled(metadataLocked);
            updateReleaseCoverPanel();
            if (releaseSettingsStatus) {
                releaseSettingsStatus.textContent = '';
            }
        }

        function updateReleaseEditorHint() {
            const entry = releaseEntry(selectedReleaseId);
            if (!editorHint) {
                return;
            }
            if (!isEditing) {
                editorHint.textContent = 'Select a campaign from the pool to preview it. Click edit to manage tracks and press kit.';
                return;
            }
            if (entry?.locked) {
                editorHint.textContent = releaseIsPlatformDemo(entry) && !releaseMayChangeLock(entry)
                    ? 'bandPromo demo is locked. Duplicate it, or unlock on localhost to edit the PCF source.'
                    : 'This campaign is locked. Membership is preview-only until you unlock it from the campaign list.';
                return;
            }
            editorHint.textContent = 'Use the section tabs to manage tracks and associated playlists, galleries, and pages. Pages associated here appear as optional player tabs (in list order) when this campaign’s playlist is playing. Changes save as you edit.';
        }

        async function saveReleaseSettings({ silent = false } = {}) {
            if (releaseSettingsSaving) {
                releaseSettingsSaveQueued = true;
                return true;
            }
            if (!(releaseSettingsTitle instanceof HTMLInputElement)
                || !(releaseSettingsDate instanceof HTMLInputElement)) {
                return true;
            }

            const entry = releaseEntry(selectedReleaseId);
            if (!entry || entry.locked) {
                return true;
            }

            const settings = readReleaseSettingsFromForm();
            const { title, release_date: releaseDate } = settings;

            if (!title) {
                if (!silent) {
                    showReleaseToast('Campaign name is required.', 'error');
                }
                return false;
            }

            const dateError = validateReleaseDate(releaseDate);
            if (dateError) {
                if (!silent) {
                    showReleaseToast(dateError, 'error');
                }
                return false;
            }

            let pressContact = String(settings.epk?.press_contact || '').trim();
            if (pressContact !== '' && typeof window.bandpromoSiteContactNormalize === 'function') {
                const normalized = window.bandpromoSiteContactNormalize(pressContact);
                if (normalized) {
                    pressContact = normalized;
                    settings.epk.press_contact = normalized;
                    if (releaseSettingsPressContact instanceof HTMLInputElement) {
                        releaseSettingsPressContact.value = normalized;
                    }
                }
            }
            if (pressContact !== '' && typeof window.bandpromoSiteContactIsValid === 'function'
                && !window.bandpromoSiteContactIsValid(pressContact)) {
                const message = window.bandpromoSiteContactInvalidMessage?.()
                    || 'Press contact must be a valid RFC 5322 address.';
                if (!silent) {
                    showReleaseToast(message, 'error');
                }
                return false;
            }

            if (!releaseSettingsDirty()) {
                return true;
            }

            releaseSettingsSaving = true;
            releaseSettingsSaveQueued = false;

            try {
                const data = await fetchJson(`/biblioteca/manage-release.php?release=${encodeURIComponent(selectedReleaseId)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settings),
                });
                releases = sortReleaseEntries(Array.isArray(data.releases) ? data.releases : releases);
                const savedPoster = String(data.release?.poster_asset_id || settings.poster_asset_id || '').trim();
                const savedPreview = String(data.release?.poster_preview_url || '').trim();
                if (savedPreview) {
                    pendingReleaseCoverPreviewUrl = savedPreview;
                } else if (savedPoster) {
                    pendingReleaseCoverPreviewUrl = mediaPreviewUrlFromReference(savedPoster);
                }
                syncReleaseSettingsPanel(selectedReleaseId);
                renderReleasePoolList();
                updateReleaseEditorHint();
                renderLists();
                return true;
            } catch (error) {
                if (!silent) {
                    showReleaseToast(error.message || 'Could not save campaign settings', 'error');
                }
                return false;
            } finally {
                releaseSettingsSaving = false;
                if (releaseSettingsSaveQueued) {
                    releaseSettingsSaveQueued = false;
                    saveReleaseSettings({ silent: true }).catch(() => {});
                }
            }
        }

        function closeReleaseDeleteModal() {
            pendingReleaseDeleteId = '';
            if (releaseDeleteModal) {
                releaseDeleteModal.style.display = 'none';
                releaseDeleteModal.setAttribute('aria-hidden', 'true');
            }
        }

        function selectedReleaseDeleteMode() {
            if (releaseDeleteModeContainer && releaseDeleteModeContainer.checked) {
                return 'container';
            }
            return 'purge';
        }

        function syncReleaseDeleteConfirmLabel() {
            if (!(releaseDeleteConfirmBtn instanceof HTMLButtonElement)) {
                return;
            }
            releaseDeleteConfirmBtn.textContent = selectedReleaseDeleteMode() === 'container'
                ? 'Delete campaign only'
                : 'Delete entire campaign';
        }

        function openReleaseDeleteModal(releaseId) {
            const entry = releaseEntry(releaseId);
            if (!entry || !releaseCanDelete(entry)) {
                return;
            }
            const title = String(entry.title || releaseId);
            if (!releaseDeleteModal) {
                if (!window.confirm(`Delete entire campaign "${title}"?\n\nRemoves owned brand, playlists, galleries, pages, and unused media. Shared media stays. This cannot be undone.`)) {
                    return;
                }
                deleteRelease(releaseId, 'purge').catch((error) => showReleaseToast(error.message || 'Could not delete campaign'));
                return;
            }
            pendingReleaseDeleteId = releaseId;
            if (releaseDeleteModalName) {
                releaseDeleteModalName.textContent = title;
            }
            if (releaseDeleteModePurge) {
                releaseDeleteModePurge.checked = true;
            }
            if (releaseDeleteModeContainer) {
                releaseDeleteModeContainer.checked = false;
            }
            syncReleaseDeleteConfirmLabel();
            releaseDeleteModal.style.display = 'flex';
            releaseDeleteModal.setAttribute('aria-hidden', 'false');
            releaseDeleteConfirmBtn?.focus();
        }

        function showPoolView() {
            isEditing = false;
            editorCard.classList.add('release-editor-is-preview');
            if (poolView) {
                poolView.hidden = false;
            }
            if (tracksPoolView) {
                tracksPoolView.hidden = true;
            }
            if (releaseAvailableSection) {
                releaseAvailableSection.hidden = true;
            }
            if (releaseAssociationActiveList) {
                releaseAssociationActiveList.hidden = true;
            }
            if (releaseAssociationAvailableSection) {
                releaseAssociationAvailableSection.hidden = true;
            }
            renderReleasePoolList();
            updateReleaseEditorHint();
            updateReleaseCoverPanel();
        }

        function showEditView(releaseId) {
            isEditing = true;
            editorCard.classList.remove('release-editor-is-preview');
            selectedReleaseId = releaseId;
            if (poolView) {
                poolView.hidden = true;
            }
            if (tracksPoolView) {
                tracksPoolView.hidden = false;
            }
            syncReleaseEditorMode();
            syncReleaseUrl(releaseId, true);
            syncReleaseSettingsPanel(releaseId);
            renderReleasePoolList();
            updateReleaseEditorHint();
            updateReleaseCoverPanel();
        }

        function renderReleasePoolList() {
            if (!poolList) {
                return;
            }
            if (!releases.length) {
                poolList.innerHTML = '<li class="player-layout-empty">No campaigns available yet.</li>';
                return;
            }
            poolList.innerHTML = releases.map((entry) => {
                const id = String(entry.id || '');
                const selectedClass = id === selectedReleaseId ? ' playlist-editor-row-selected' : '';
                const title = escapeHtml(entry.title || id);
                const deleteBtn = releaseCanDelete(entry)
                    ? `<button type="button" class="icon-btn icon-btn--pool icon-btn--danger page-pool-delete-btn" data-release-id="${escapeHtml(id)}" title="Delete campaign" aria-label="Delete ${title}">🗑️</button>`
                    : '';
                const duplicateBtn = id && id !== 'primary'
                    ? `<button type="button" class="icon-btn icon-btn--pool page-pool-duplicate-btn" data-release-id="${escapeHtml(id)}" title="Duplicate campaign (shared media)" aria-label="Duplicate ${title}">⧉</button>`
                    : '';
                const exportBtn = id && id !== 'primary'
                    ? `<button type="button" class="icon-btn icon-btn--pool page-pool-export-btn" data-release-id="${escapeHtml(id)}" title="Export campaign file (.pcf)" aria-label="Export ${title}">📦</button>`
                    : '';
                const editBtn = releaseCanOpenEditor(entry)
                    ? `<button type="button" class="icon-btn icon-btn--pool page-pool-edit-btn" data-release-id="${escapeHtml(id)}" title="Edit campaign" aria-label="Edit ${title}">✏️</button>`
                    : '';
                const lockControl = releaseMayChangeLock(entry)
                    ? `<button type="button" class="icon-btn icon-btn--pool page-pool-lock-btn${entry.locked ? ' page-pool-lock-btn--active icon-btn--active' : ''}" data-release-id="${escapeHtml(id)}" title="${entry.locked ? 'Unlock campaign (allow track edits)' : 'Lock campaign (freeze track membership)'}" aria-label="${entry.locked ? 'Unlock' : 'Lock'} ${title}" aria-pressed="${entry.locked ? 'true' : 'false'}">${entry.locked ? '🔒' : '🔓'}</button>`
                    : (entry.locked
                        ? `<span class="page-pool-lock-badge" title="Locked platform demo">🔒</span>`
                        : '');
                return `<li class="playlist-editor-row release-pool-row page-pool-row${selectedClass}" data-release-id="${escapeHtml(id)}" aria-selected="${id === selectedReleaseId ? 'true' : 'false'}">
                    <span class="playlist-track-info">
                        <strong>💿 ${title}</strong>
                        <span class="playlist-track-meta">${releasePoolMetaHtml(entry)}</span>
                    </span>
                    <span class="page-pool-row-actions">
                        ${lockControl}
                        ${exportBtn}
                        ${duplicateBtn}
                        ${editBtn}
                        ${deleteBtn}
                    </span>
                </li>`;
            }).join('');
        }

        function releasePatchPayload(entry, locked) {
            const epk = normalizeReleaseEpk(entry?.epk);
            return {
                title: String(entry?.title || '').trim(),
                release_date: String(entry?.release_date || '').trim(),
                catalog_id: String(entry?.catalog_id || '').trim(),
                locked,
                short_description: String(entry?.short_description || '').trim(),
                description: String(entry?.description || '').trim(),
                poster_asset_id: String(entry?.poster_asset_id || '').trim(),
                brand_id: String(entry?.brand_id || '').trim(),
                epk,
            };
        }

        async function toggleReleaseLock(releaseId, locked) {
            const entry = releaseEntry(releaseId);
            if (!entry || !releaseMayChangeLock(entry)) {
                return false;
            }

            if (locked && isEditing && releaseId === selectedReleaseId) {
                await flushMembershipSaves();
            }

            try {
                const data = await fetchJson(`/biblioteca/manage-release.php?release=${encodeURIComponent(releaseId)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(releasePatchPayload(entry, locked)),
                });
                releases = sortReleaseEntries(Array.isArray(data.releases) ? data.releases : releases);
                renderReleasePoolList();
                if (releaseId === selectedReleaseId) {
                    syncReleaseSettingsPanel(releaseId);
                    updateReleaseEditorHint();
                    renderLists();
                }
                return true;
            } catch (error) {
                showReleaseToast(error.message || 'Could not update release lock');
                return false;
            }
        }

        async function loadBrandCatalog() {
            try {
                const data = await fetchJson('/biblioteca/get-themes.php');
                releaseBrandCatalog = Array.isArray(data.themes) ? data.themes : [];
                populateReleaseBrandSelect();
            } catch (error) {
                // Brand picker is optional until data/brands is seeded.
                releaseBrandCatalog = [];
            }
        }

        async function loadReleaseRegistry() {
            await loadBrandCatalog();
            let data;
            if (typeof window.loadReleasesCatalog === 'function') {
                const list = await window.loadReleasesCatalog();
                data = {
                    releases: Array.isArray(list) ? list : (window.bandpromoReleasesCatalog || []),
                };
            } else if (Array.isArray(window.bandpromoReleasesCatalog) && window.bandpromoReleasesCatalog.length) {
                data = {
                    releases: window.bandpromoReleasesCatalog,
                };
            } else {
                data = await fetchJson('/biblioteca/get-releases.php');
            }
            releases = sortReleaseEntries(Array.isArray(data.releases) ? data.releases : []);
            if (!releaseEntry(selectedReleaseId)) {
                selectedReleaseId = releases[0]?.id || '';
            }
            renderReleasePoolList();
        }

        async function requestCloseEditor() {
            if (releaseSettingsDirty()) {
                const saved = await saveReleaseSettings();
                if (!saved) {
                    return false;
                }
            }
            await flushMembershipSaves();
            showPoolView();
            syncReleaseUrl(selectedReleaseId, false);
            await loadReleasePreview();
            return true;
        }

        async function openReleaseEditor(releaseId) {
            if (!releaseId) {
                showReleaseToast('Missing release id.', 'error');
                return;
            }
            const entry = releaseEntry(releaseId);
            if (!entry) {
                showReleaseToast(`Could not open release “${releaseId}” — it is not in the catalogue yet. Refresh and try again.`, 'error');
                return;
            }
            if (isEditing && releaseId !== selectedReleaseId) {
                if (releaseSettingsDirty()) {
                    const saved = await saveReleaseSettings();
                    if (!saved) {
                        return;
                    }
                }
                await flushMembershipSaves();
            }
            if (!releaseCanOpenEditor(entry)) {
                selectedReleaseId = releaseId;
                showPoolView();
                syncReleaseUrl(releaseId, false);
                await loadReleasePreview();
                return;
            }
            selectedReleaseId = releaseId;
            trackEditorLoadedReleaseId = '';
            resetAssociationPools();
            showEditView(releaseId);
        }

        async function selectReleaseForPreview(releaseId) {
            if (!releaseId || (releaseId === selectedReleaseId && !isEditing)) {
                return;
            }
            if (isEditing) {
                await openReleaseEditor(releaseId);
                return;
            }
            selectedReleaseId = releaseId;
            syncReleaseUrl(releaseId, false);
            renderReleasePoolList();
            await loadReleasePreview();
        }

        async function exportReleasePackage(releaseId) {
            const entry = releaseEntry(releaseId);
            if (!entry || releaseId === 'primary') {
                return;
            }
            const sourceTitle = String(entry.title || releaseId).trim() || releaseId;
            showReleaseToast(`Queueing PCF export for "${sourceTitle}"…`);
            const csrfToken = typeof refreshAdminCsrfToken === 'function'
                ? await refreshAdminCsrfToken()
                : (typeof adminCsrfToken === 'string' ? adminCsrfToken : '');
            const data = await fetchJson('/biblioteca/export-release-package.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    release_id: releaseId,
                    csrf_token: csrfToken,
                }),
            });
            const message = data.message || 'PCF export queued.';
            showReleaseToast(message);
            if (window.confirm(`${message}\n\nOpen System → Backup, export & import to watch progress / download?`)) {
                window.location.href = String(data.jobs_url || '?tab=system&stab=backup');
            }
        }

        async function duplicateReleaseCampaign(releaseId) {
            const entry = releaseEntry(releaseId);
            if (!entry || releaseId === 'primary') {
                return;
            }
            const sourceTitle = String(entry.title || releaseId).trim() || releaseId;
            if (!window.confirm(`Duplicate "${sourceTitle}" as a new campaign?\n\nNew containers; shared media files.`)) {
                return;
            }
            const data = await fetchJson('/biblioteca/duplicate-release-campaign.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ release_id: releaseId }),
            });
            releases = sortReleaseEntries(Array.isArray(data.releases) ? data.releases : releases);
            renderReleasePoolList();
            const newId = String(data.release_id || '').trim();
            showReleaseToast(data.message || 'Campaign duplicated.');
            if (newId) {
                await openReleaseEditor(newId);
            }
        }

        async function deleteRelease(releaseId, mode = 'purge') {
            const entry = releaseEntry(releaseId);
            if (!entry || !releaseCanDelete(entry)) {
                return;
            }
            const deleteMode = mode === 'container' ? 'container' : 'purge';
            const data = await fetchJson(
                `/biblioteca/manage-release.php?release=${encodeURIComponent(releaseId)}&mode=${encodeURIComponent(deleteMode)}`,
                { method: 'DELETE' }
            );
            releases = Array.isArray(data.releases) ? data.releases : [];
            const purge = data.purge && typeof data.purge === 'object' ? data.purge : null;
            if (deleteMode === 'purge' && purge) {
                const assetCount = Array.isArray(purge.deleted_assets) ? purge.deleted_assets.length : 0;
                const kept = Array.isArray(purge.retained_shared_assets) ? purge.retained_shared_assets.length : 0;
                let detail = `Campaign deleted (${assetCount} media removed`;
                if (kept > 0) {
                    detail += `, ${kept} shared kept`;
                }
                detail += ').';
                showReleaseToast(detail);
            } else {
                showReleaseToast('Release removed. Media stayed in Files.');
            }
            if (selectedReleaseId === releaseId) {
                selectedReleaseId = releases[0]?.id || 'primary';
                showPoolView();
                syncReleaseUrl(selectedReleaseId, false);
                await loadReleasePreview();
            } else {
                renderReleasePoolList();
            }
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
            if (!rows.length) {
                return;
            }
            const anchorFile = selectionAnchorAvailable && rows.some((row) => String(row.dataset.file || '') === selectionAnchorAvailable)
                ? selectionAnchorAvailable
                : targetFile;
            const anchorIndex = rows.findIndex((row) => String(row.dataset.file || '') === anchorFile);
            const targetIndex = rows.findIndex((row) => String(row.dataset.file || '') === targetFile);
            if (anchorIndex === -1 || targetIndex === -1) {
                return;
            }

            const nextSelected = preserveExisting ? new Set(selectedAvailable) : new Set();
            const start = Math.min(anchorIndex, targetIndex);
            const end = Math.max(anchorIndex, targetIndex);
            rows.slice(start, end + 1).forEach((row) => {
                const file = String(row.dataset.file || '');
                if (file) {
                    nextSelected.add(file);
                }
            });
            selectedAvailable = nextSelected;
        }

        function selectActiveRange(targetFile, preserveExisting) {
            const rows = getActiveRows();
            if (!rows.length) {
                return;
            }
            const anchorFile = selectionAnchorActive && rows.some((row) => String(row.dataset.file || '') === selectionAnchorActive)
                ? selectionAnchorActive
                : targetFile;
            const anchorIndex = rows.findIndex((row) => String(row.dataset.file || '') === anchorFile);
            const targetIndex = rows.findIndex((row) => String(row.dataset.file || '') === targetFile);
            if (anchorIndex === -1 || targetIndex === -1) {
                return;
            }

            const nextSelected = preserveExisting ? new Set(selectedActive) : new Set();
            const start = Math.min(anchorIndex, targetIndex);
            const end = Math.max(anchorIndex, targetIndex);
            rows.slice(start, end + 1).forEach((row) => {
                const file = String(row.dataset.file || '');
                if (file) {
                    nextSelected.add(file);
                }
            });
            selectedActive = nextSelected;
        }

        function handleAvailableSelection(row, event) {
            const file = String(row.dataset.file || '').trim();
            if (!file) {
                return;
            }
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
            if (!file) {
                return;
            }
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
            const entry = releaseEntry(selectedReleaseId);
            const canEditTracks = releaseTrackEditingEnabled(entry);
            const title = escapeHtml(displayTrackTitle(track));
            const meta = escapeHtml(trackMeta(track));
            const duration = track.deliveryReady === false ? '' : formatReleaseDuration(track.duration);
            const file = escapeHtml(track.file || '');
            const selectedClass = options.selected ? ' playlist-editor-row-selected' : '';
            const pendingClass = track.deliveryReady === false ? ' playlist-editor-row-pending' : '';
            const demoClass = track.origin === 'bundled-placeholder' ? ' playlist-editor-row-demo' : '';
            const positionMarkup = options.showPosition
                ? `<span class="playlist-track-num">${options.position}</span>`
                : '';
            const removeMarkup = options.showRemove
                ? '<button type="button" class="player-layout-remove-btn" title="Move to Available tracks" aria-label="Remove from campaign">✕</button>'
                : '';
            const rowClass = options.activeRow ? 'playlist-editor-row player-layout-row-active' : 'playlist-editor-row';
            const readonlyClass = !canEditTracks ? ' playlist-editor-row-readonly' : '';
            const draggable = canEditTracks && track.deliveryReady !== false ? 'true' : 'false';
            const dragTitle = !canEditTracks
                ? (entry?.locked ? 'Locked release — unlock to edit' : 'Preview only')
                : (track.deliveryReady === false
                    ? 'Delivery file not ready yet'
                    : (options.activeRow ? 'Drag to reorder' : 'Drag into release'));

            return `<li class="${rowClass}${pendingClass}${demoClass}${selectedClass}${readonlyClass}" draggable="${draggable}" data-file="${file}" aria-selected="${options.selected ? 'true' : 'false'}">
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

        function sortAssociatedTracks(tracks) {
            return (Array.isArray(tracks) ? tracks : []).slice().sort((left, right) => {
                const dateCompare = String(right.release_date || '').localeCompare(String(left.release_date || ''));
                if (dateCompare !== 0) {
                    return dateCompare;
                }
                const artistCompare = String(left.artist || '').localeCompare(
                    String(right.artist || ''),
                    undefined,
                    { sensitivity: 'base' }
                );
                if (artistCompare !== 0) {
                    return artistCompare;
                }
                return displayTrackTitle(left).localeCompare(
                    displayTrackTitle(right),
                    undefined,
                    { sensitivity: 'base' }
                );
            });
        }

        function renderAssociatedTrackRow(track, canEditTracks) {
            const title = escapeHtml(displayTrackTitle(track));
            const artist = escapeHtml(String(track.artist || '').trim() || 'Unknown artist');
            const duration = track.deliveryReady === false ? '' : formatReleaseDuration(track.duration);
            const file = escapeHtml(track.file || '');
            const pendingClass = track.deliveryReady === false ? ' playlist-editor-row-pending' : '';
            const removeMarkup = canEditTracks
                ? '<button type="button" class="player-layout-remove-btn" title="Remove from campaign" aria-label="Remove from campaign">✕</button>'
                : '';

            return `<li class="playlist-editor-row release-associated-track-row${pendingClass}" draggable="false" data-file="${file}">
                <span class="release-preview-track-copy">
                    <span class="release-preview-track-artist">${artist}</span>
                    <strong class="release-preview-track-title">${title}</strong>
                </span>
                <span class="playlist-track-duration">${duration}</span>
                ${removeMarkup}
            </li>`;
        }

        function renderLists() {
            pruneAvailableSelection();
            pruneActiveSelection();

            const entry = releaseEntry(selectedReleaseId);
            const canEditTracks = releaseTrackEditingEnabled(entry);

            if (!selectedReleaseId) {
                activeEl.innerHTML = '<li class="player-layout-empty">No campaign selected.</li>';
                return;
            }

            if (!activeTracks.length) {
                activeEl.innerHTML = canEditTracks
                    ? '<li class="player-layout-empty">Drag tracks here from Available tracks.</li>'
                    : '<li class="player-layout-empty">This release has no tracks yet.</li>';
            } else {
                activeTracks = sortAssociatedTracks(activeTracks);
                activeEl.innerHTML = activeTracks
                    .map((track) => renderAssociatedTrackRow(track, canEditTracks))
                    .join('');
            }

            if (activeEl) {
                activeEl.hidden = !isEditing || releaseEditorTab !== 'tracks';
            }

            if (!isEditing) {
                updateReleaseCoverPanel();
                return;
            }

            if (!availableTracks.length) {
                const emptyMessage = canEditTracks
                    ? (activeTracks.length
                        ? 'No unassigned tracks to add. Orphans would appear here; every other track is already owned by another campaign.'
                        : 'No unassigned tracks to add. Orphans would appear here. Upload audio in Files → Audio, or unassign a track from another campaign.')
                    : 'Track membership is preview-only while this campaign is locked.';
                availableEl.innerHTML = `<li class="player-layout-empty">${emptyMessage}</li>`;
            } else {
                availableEl.innerHTML = availableTracks.map((track) => renderTrackRow(track, {
                    showPosition: false,
                    showRemove: false,
                    activeRow: false,
                    selected: selectedAvailable.has(String(track.file || '')),
                })).join('');
            }

        }

        function trackLookup() {
            const lookup = new Map();
            [...activeTracks, ...availableTracks].forEach((track) => {
                const file = String(track.file || '');
                if (file) {
                    lookup.set(file, track);
                }
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
            if (!fileSet.size) {
                return false;
            }

            const source = fromList === 'active' ? activeTracks : availableTracks;
            const moving = source.filter((track) => fileSet.has(String(track.file || '')));
            if (!moving.length) {
                return false;
            }
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
            const movedClones = moving.map((track) => {
                const clone = cloneTrack(track);
                if (toList === 'active') {
                    clone.release_id = selectedReleaseId;
                } else {
                    clone.release_id = String(clone.originReleaseId || clone.release_id || '');
                }
                return clone;
            });
            target.splice(safeIndex, 0, ...movedClones);

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

            activeTracks = sortAssociatedTracks(activeTracks);
            renderLists();
            void persistReleaseTracks();
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
            if (!listEl) {
                return [];
            }
            return Array.from(listEl.querySelectorAll('.playlist-editor-row[draggable="true"]'));
        }

        function listNameForElement(listEl) {
            if (listEl === activeEl) {
                return 'active';
            }
            if (listEl === availableEl) {
                return 'available';
            }
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
                if (!child.classList.contains('playlist-editor-row')) {
                    continue;
                }
                const file = String(child.dataset.file || '');
                if (movingFiles.has(file)) {
                    continue;
                }
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
                if (!child.classList.contains('playlist-editor-row')) {
                    continue;
                }
                const file = String(child.dataset.file || '');
                if (movingFiles.has(file)) {
                    continue;
                }
                index += 1;
            }
            return index;
        }

        function updatePlaceholderHeight() {
            if (!draggedRows.length) {
                return;
            }
            const placeholder = ensurePlaceholder();
            const totalHeight = draggedRows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0)
                + Math.max(0, draggedRows.length - 1) * 6;
            placeholder.style.height = `${Math.max(52, Math.round(totalHeight))}px`;
        }

        function movePlaceholder(listEl, clientY) {
            if (!draggedRows.length || !listEl) {
                return;
            }
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
                dragSourceRow = null;
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
                dragSourceRow = null;
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

            dragSourceRow = null;
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
                const row = event.target instanceof HTMLElement
                    ? event.target.closest('.playlist-editor-row[draggable="true"]')
                    : null;
                if (!row || !listEl.contains(row)) {
                    return;
                }
                dragSourceRow = row;
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
                    if (!dragSourceRow || !draggedRows.length) {
                        return;
                    }
                    updatePlaceholderHeight();
                    listEl.insertBefore(ensurePlaceholder(), draggedRows[0]);
                    draggedRows.forEach((dragRow) => dragRow.classList.add('dragging'));
                });
            });

            listEl.addEventListener('dragover', (event) => {
                if (!draggedRows.length) {
                    return;
                }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                if (listEl === activeEl) {
                    activeEl.classList.add('is-drop-target');
                }
                movePlaceholder(listEl, event.clientY);
            });

            listEl.addEventListener('drop', (event) => {
                if (!draggedRows.length) {
                    return;
                }
                event.preventDefault();
                activeEl.classList.remove('is-drop-target');
                movePlaceholder(listEl, event.clientY);
                finalizeDrag();
            });

            listEl.addEventListener('dragend', () => {
                activeEl.classList.remove('is-drop-target');
                finalizeWithinListDrag(listEl);
                draggedRows.forEach((row) => row.classList.remove('dragging'));
                dragPlaceholder?.remove();
                dragSourceRow = null;
                draggedRows = [];
                dragSourceList = '';
                syncAvailableOrderFromDOM();
                syncAvailableSelectionUi();
                syncActiveSelectionUi();
                suppressNextClick = true;
                window.requestAnimationFrame(() => {
                    suppressNextClick = false;
                });
            });
        }

        function applyPreviewData(data) {
            const entry = releaseEntry(selectedReleaseId);
            if (entry) {
                if (typeof data.locked === 'boolean') {
                    entry.locked = data.locked;
                }
            }

            activeTracks = Array.isArray(data.activeTracks) ? data.activeTracks.map(cloneTrack) : [];
            availableTracks = Array.isArray(data.availableTracks) ? data.availableTracks.map(cloneTrack) : [];
            trackEditorLoadedReleaseId = selectedReleaseId;

            renderReleasePoolList();
            syncReleaseSettingsPanel(selectedReleaseId);
            updateReleaseEditorHint();
            renderLists();
            updateReleaseCoverPanel();
        }

        function applyRegistryPreview() {
            const entry = releaseEntry(selectedReleaseId);
            activeTracks = Array.isArray(entry?.preview_tracks)
                ? entry.preview_tracks.map(cloneTrack)
                : [];
            availableTracks = [];

            renderReleasePoolList();
            renderLists();
        }

        async function loadReleasePreview() {
            if (!isEditing) {
                applyRegistryPreview();
                return;
            }

            try {
                const data = await fetchJson(`/biblioteca/get-release-preview.php?release=${encodeURIComponent(selectedReleaseId)}`);
                applyPreviewData(data);
            } catch (error) {
                activeEl.innerHTML = '';
                availableEl.innerHTML = `<li class="player-layout-empty" style="color:#f87171">Could not load release preview: ${escapeHtml(error.message)}</li>`;
            }
        }

        releaseDeleteCancelBtn?.addEventListener('click', closeReleaseDeleteModal);
        releaseDeleteModePurge?.addEventListener('change', syncReleaseDeleteConfirmLabel);
        releaseDeleteModeContainer?.addEventListener('change', syncReleaseDeleteConfirmLabel);
        releaseDeleteModal?.addEventListener('click', (event) => {
            if (event.target === releaseDeleteModal) {
                closeReleaseDeleteModal();
            }
        });
        releaseDeleteConfirmBtn?.addEventListener('click', async () => {
            const releaseId = pendingReleaseDeleteId;
            if (!releaseId) {
                return;
            }
            const mode = selectedReleaseDeleteMode();
            closeReleaseDeleteModal();
            try {
                releaseDeleteConfirmBtn.disabled = true;
                await deleteRelease(releaseId, mode);
            } catch (error) {
                showReleaseToast(error.message || 'Could not delete campaign');
            } finally {
                releaseDeleteConfirmBtn.disabled = false;
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !releaseDeleteModal || releaseDeleteModal.style.display !== 'flex') {
                return;
            }
            closeReleaseDeleteModal();
        });

        poolList.addEventListener('click', (event) => {
            const lockBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-lock-btn')
                : null;
            if (lockBtn) {
                event.preventDefault();
                event.stopPropagation();
                const releaseId = lockBtn.getAttribute('data-release-id') || '';
                const entry = releaseEntry(releaseId);
                if (!entry) {
                    return;
                }
                toggleReleaseLock(releaseId, !entry.locked);
                return;
            }

            const deleteBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-delete-btn')
                : null;
            if (deleteBtn) {
                event.preventDefault();
                event.stopPropagation();
                const releaseId = deleteBtn.getAttribute('data-release-id') || '';
                openReleaseDeleteModal(releaseId);
                return;
            }

            const duplicateBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-duplicate-btn')
                : null;
            if (duplicateBtn) {
                event.preventDefault();
                event.stopPropagation();
                const releaseId = duplicateBtn.getAttribute('data-release-id') || '';
                duplicateReleaseCampaign(releaseId).catch((error) => {
                    showReleaseToast(error.message || 'Could not duplicate campaign', 'error');
                });
                return;
            }

            const exportBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-export-btn')
                : null;
            if (exportBtn) {
                event.preventDefault();
                event.stopPropagation();
                const releaseId = exportBtn.getAttribute('data-release-id') || '';
                exportReleasePackage(releaseId).catch((error) => {
                    showReleaseToast(error.message || 'Could not export package', 'error');
                });
                return;
            }

            const editBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-edit-btn')
                : null;
            if (editBtn) {
                event.preventDefault();
                event.stopPropagation();
                const releaseId = editBtn.getAttribute('data-release-id') || '';
                openReleaseEditor(releaseId);
                return;
            }

            const row = event.target instanceof HTMLElement
                ? event.target.closest('.release-pool-row')
                : null;
            if (!row || !poolList.contains(row)) {
                return;
            }
            const releaseId = row.getAttribute('data-release-id') || '';
            if (!releaseId) {
                return;
            }
            selectReleaseForPreview(releaseId);
        });

        backBtn?.addEventListener('click', () => {
            requestCloseEditor();
        });

        releaseSettingsTitle?.addEventListener('blur', () => {
            saveReleaseSettings();
        });
        releaseSettingsTitle?.addEventListener('input', () => {
            updateReleaseBasePreviewFromForm();
        });
        releaseSettingsDate?.addEventListener('blur', () => {
            saveReleaseSettings();
        });
        releaseSettingsDate?.addEventListener('input', () => {
            updateReleaseBasePreviewFromForm();
        });
        releaseSettingsDate?.addEventListener('change', () => {
            saveReleaseSettings();
        });
        releaseSettingsCatalogId?.addEventListener('blur', () => {
            saveReleaseSettings();
        });
        releaseSettingsBrandId?.addEventListener('change', () => {
            refreshReleaseBaseBrandPreview();
            saveReleaseSettings();
        });
        releaseSettingsPosterAssetId?.addEventListener('input', () => {
            const raw = String(releaseSettingsPosterAssetId.value || '').trim();
            if (!raw) {
                pendingReleaseCoverPreviewUrl = '';
            } else if (raw.startsWith('/media/') || /^https?:\/\//i.test(raw)) {
                pendingReleaseCoverPreviewUrl = mediaPreviewUrlFromReference(raw);
            }
            updateReleaseCoverPreview();
            saveReleaseSettings();
        });
        releaseSettingsShortDescription?.addEventListener('input', () => {
            updateReleaseShortDescriptionCount();
            updateReleaseBasePreviewFromForm();
        });
        releaseSettingsDescription?.addEventListener('input', () => {
            autofitReleaseDescriptionField();
            updateReleaseBasePreviewFromForm();
        });
        [
            releaseSettingsShortDescription,
            releaseSettingsDescription,
            releaseSettingsCredits,
            releaseSettingsPressContact,
            releaseSettingsStreamBandpromo,
            releaseSettingsStreamSpotify,
            releaseSettingsStreamApple,
            releaseSettingsPressPhotos,
        ].forEach((control) => {
            control?.addEventListener('blur', () => {
                saveReleaseSettings();
            });
        });
        releaseSettingsTitle?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                releaseSettingsTitle.blur();
            }
        });
        releaseSettingsDate?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                releaseSettingsDate.blur();
            }
        });

        toggleAddReleaseBtn?.addEventListener('click', () => {
            setAddReleasePanelOpen(addReleasePanel?.hidden !== false);
        });

        cancelAddReleaseBtn?.addEventListener('click', () => {
            addReleaseForm?.reset();
            setAddReleasePanelOpen(false);
        });

        addReleaseForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(addReleaseForm);
            const title = String(formData.get('title') || '').trim();
            if (!title) {
                if (releaseRegistryStatus) {
                    releaseRegistryStatus.textContent = 'Campaign name is required.';
                    releaseRegistryStatus.style.color = '#f87171';
                }
                return;
            }
            try {
                if (releaseRegistryStatus) {
                    releaseRegistryStatus.textContent = 'Creating campaign…';
                    releaseRegistryStatus.style.color = '';
                }
                const data = await fetchJson('/biblioteca/manage-release.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ title }),
                });
                const created = (data.release && typeof data.release === 'object') ? data.release : null;
                const newId = String(created?.id || '').trim();
                if (Array.isArray(data.releases) && data.releases.length) {
                    releases = sortReleaseEntries(data.releases);
                } else if (created && newId) {
                    const without = releases.filter((entry) => String(entry?.id || '') !== newId);
                    releases = sortReleaseEntries([created, ...without]);
                }
                if (typeof window.loadReleasesCatalog === 'function') {
                    try {
                        const catalog = await window.loadReleasesCatalog({ force: true });
                        if (Array.isArray(catalog) && catalog.length) {
                            releases = sortReleaseEntries(catalog);
                        }
                    } catch (_error) {
                        // Local list from create response is enough to open the editor.
                    }
                }
                if (created && newId && !releaseEntry(newId)) {
                    releases = sortReleaseEntries([created, ...releases.filter((entry) => String(entry?.id || '') !== newId)]);
                }
                addReleaseForm.reset();
                setAddReleasePanelOpen(false);
                if (!newId || !releaseEntry(newId)) {
                    showReleaseToast('Campaign was created but could not be opened. Refresh the catalogue and select it from the pool.', 'error');
                    renderReleasePoolList();
                    return;
                }
                await openReleaseEditor(newId);
                if (releaseRegistryStatus) {
                    releaseRegistryStatus.textContent = `Created “${String(created?.title || newId)}”.`;
                    releaseRegistryStatus.style.color = '';
                }
            } catch (error) {
                if (releaseRegistryStatus) {
                    releaseRegistryStatus.textContent = '❌ ' + (error.message || 'Could not create campaign');
                    releaseRegistryStatus.style.color = '#f87171';
                }
            }
        });

        availableEl.addEventListener('click', (event) => {
            if (suppressNextClick) {
                return;
            }
            const row = event.target instanceof HTMLElement
                ? event.target.closest('.playlist-editor-row[draggable="true"]')
                : null;
            if (!row || !availableEl.contains(row)) {
                return;
            }
            handleAvailableSelection(row, event);
        });

        activeEl.addEventListener('click', (event) => {
            const button = event.target instanceof HTMLElement
                ? event.target.closest('.player-layout-remove-btn')
                : null;
            if (button && activeEl.contains(button)) {
                const row = button.closest('.playlist-editor-row');
                if (!row) {
                    return;
                }
                const file = String(row.dataset.file || '').trim();
                if (!file) {
                    return;
                }
                moveTracksBetweenLists('active', 'available', [file], availableTracks.length);
                return;
            }
            if (suppressNextClick) {
                return;
            }
            const row = event.target instanceof HTMLElement
                ? event.target.closest('.playlist-editor-row[draggable="true"]')
                : null;
            if (!row || !activeEl.contains(row)) {
                return;
            }
            handleActiveSelection(row, event);
        });

        bindDragList(activeEl);
        bindDragList(availableEl);
        bindAssociationDragList(releaseAssociationAvailableList, 'available');
        bindAssociationDragList(releaseAssociationActiveList, 'active');

        if (releaseAssociationAvailableList) {
            releaseAssociationAvailableList.addEventListener('dblclick', (event) => {
                const kind = currentAssociationKind();
                const row = event.target instanceof HTMLElement
                    ? event.target.closest('.playlist-editor-row[draggable="true"]')
                    : null;
                const id = String(row?.dataset.id || '').trim();
                if (!kind || !id) {
                    return;
                }
                moveAssociationItems(kind, 'available', 'active', [id]);
            });
        }

        if (releaseAssociationActiveList) {
            releaseAssociationActiveList.addEventListener('click', (event) => {
                const button = event.target instanceof HTMLElement
                    ? event.target.closest('.player-layout-remove-btn')
                    : null;
                if (!button || !releaseAssociationActiveList.contains(button)) {
                    return;
                }
                const kind = currentAssociationKind();
                const row = button.closest('.playlist-editor-row');
                const id = String(row?.dataset.id || '').trim();
                if (!kind || !id) {
                    return;
                }
                moveAssociationItems(kind, 'active', 'available', [id]);
            });
        }

        initReleaseCoverPicker();

        document.querySelectorAll('[data-release-editor-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                setReleaseEditorTab(String(button.getAttribute('data-release-editor-tab') || 'base'));
            });
        });
        setReleaseEditorTab(releaseEditorTab);

        const urlParams = new URLSearchParams(window.location.search);
        const startInEdit = urlParams.get('edit') === '1';

        loadSiteSharingContext()
            .then(() => loadReleaseRegistry())
            .catch((error) => {
                poolList.innerHTML = `<li class="player-layout-empty" style="color:#f87171">${escapeHtml(error.message)}</li>`;
            })
            .finally(async () => {
                if (startInEdit) {
                    await openReleaseEditor(selectedReleaseId);
                } else {
                    showPoolView();
                    syncReleaseUrl(selectedReleaseId, false);
                    await loadReleasePreview();
                }
            });
    }

    window.initBandpromoReleaseEditor = initBandpromoReleaseEditor;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBandpromoReleaseEditor);
    } else {
        initBandpromoReleaseEditor();
    }
})();
