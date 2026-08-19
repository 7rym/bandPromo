(function () {
    function initBandpromoCampaignEditor() {
        const editorCard = document.getElementById('campaignEditorCard');
        const poolView = document.getElementById('campaignPoolView');
        const tracksPoolView = document.getElementById('campaignTracksPoolView');
        const poolList = document.getElementById('campaignPoolList');
        const availableEl = document.getElementById('campaignAvailableList');
        const activeEl = document.getElementById('campaignActiveList');
        const editorHint = document.getElementById('campaignEditorHint');
        const backBtn = document.getElementById('campaignEditorBackBtn');
        const toggleAddCampaignBtn = document.getElementById('toggleAddCampaignBtn');
        const addCampaignPanel = document.getElementById('addCampaignPanel');
        const addCampaignForm = document.getElementById('addCampaignForm');
        const cancelAddCampaignBtn = document.getElementById('cancelAddCampaignBtn');
        const campaignRegistryStatus = document.getElementById('campaignRegistryStatus');
        const campaignDeleteModal = document.getElementById('campaignDeleteModal');
        const campaignDeleteModalName = document.getElementById('campaignDeleteModalName');
        const campaignDeleteConfirmBtn = document.getElementById('campaignDeleteConfirmBtn');
        const campaignDeleteModePurge = document.getElementById('campaignDeleteModePurge');
        const campaignDeleteModeContainer = document.getElementById('campaignDeleteModeContainer');
        const campaignDeleteCancelBtn = document.getElementById('campaignDeleteCancelBtn');
        const campaignSettingsTitle = document.getElementById('campaignSettingsTitle');
        const campaignSettingsDate = document.getElementById('campaignSettingsDate');
        const campaignSettingsCatalogId = document.getElementById('campaignSettingsCatalogId');
        let campaignSettingsBrandId = document.getElementById('campaignSettingsBrandId');
        const campaignSettingsStatus = document.getElementById('campaignSettingsStatus');
        let campaignSettingsDescription = document.getElementById('campaignSettingsDescription');
        const campaignSettingsShortDescription = document.getElementById('campaignSettingsShortDescription');
        const campaignSettingsShortDescriptionCount = document.getElementById('campaignSettingsShortDescriptionCount');
        const campaignSettingsPosterAssetId = document.getElementById('campaignSettingsPosterAssetId');
        const campaignCoverPanel = document.getElementById('campaignCoverPanel');
        const campaignBaseBrandPreview = document.getElementById('campaignBaseBrandPreview');
        const campaignBaseBrandPreviewBody = document.getElementById('campaignBaseBrandPreviewBody');
        const campaignLongDescriptionPreview = document.getElementById('campaignLongDescriptionPreview');
        const campaignLongDescriptionPreviewBody = document.getElementById('campaignLongDescriptionPreviewBody');
        const campaignCoverPreviewShell = document.getElementById('campaignCoverPreviewShell');
        const campaignCoverPreview = document.getElementById('campaignCoverPreview');
        const campaignCoverPlaceholder = document.getElementById('campaignCoverPlaceholder');
        const campaignCoverClearBtn = document.getElementById('campaignCoverClearBtn');
        const campaignCoverOverlayActions = document.getElementById('campaignCoverOverlayActions');
        const campaignPreviewTitle = document.getElementById('campaignPreviewTitle');
        const campaignPreviewDate = document.getElementById('campaignPreviewDate');
        const campaignPreviewSummary = document.getElementById('campaignPreviewSummary');
        const campaignEditorPreviewHeading = document.getElementById('campaignEditorPreviewHeading');
        const campaignBrandingLivePreview = document.getElementById('campaignBrandingLivePreview');
        const campaignBrandingPreview = document.getElementById('campaignBrandingPreview');
        const campaignPresskitLivePreview = document.getElementById('campaignPresskitLivePreview');
        const campaignEditorPresskitPreview = document.getElementById('campaignEditorPresskitPreview');
        let campaignSettingsCredits = document.getElementById('campaignSettingsCredits');
        let campaignSettingsPressContact = document.getElementById('campaignSettingsPressContact');
        let campaignSettingsStreamBandpromo = document.getElementById('campaignSettingsStreamBandpromo');
        let campaignSettingsStreamBandpromoLabel = document.getElementById('campaignSettingsStreamBandpromoLabel');
        let campaignSettingsStreamSpotify = document.getElementById('campaignSettingsStreamSpotify');
        let campaignSettingsStreamApple = document.getElementById('campaignSettingsStreamApple');
        let campaignSettingsSocialImports = document.getElementById('campaignSettingsSocialImports');
        let campaignSettingsPressPhotos = document.getElementById('campaignSettingsPressPhotos');
        const campaignAvailableSection = document.getElementById('campaignAvailableSection');
        const campaignAssociationActiveList = document.getElementById('campaignAssociationActiveList');
        const campaignAssociationAvailableSection = document.getElementById('campaignAssociationAvailableSection');
        const campaignAssociationAvailableList = document.getElementById('campaignAssociationAvailableList');
        const campaignAssociationAvailableHeading = document.getElementById('campaignAssociationAvailableHeading');

        if (!editorCard || !poolList || !availableEl || !activeEl) {
            return;
        }
        if (editorCard.dataset.campaignEditorInitialized === 'true') {
            return;
        }
        editorCard.dataset.campaignEditorInitialized = 'true';

        const PROTECTED_CAMPAIGN_IDS = new Set(['primary', 'bandpromo-demo']);
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

        function showCampaignToast(message, type = 'warning') {
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

        const initialUrlParams = new URLSearchParams(window.location.search);
        let campaigns = [];
        let selectedCampaignId = String(
            initialUrlParams.get('campaign')
            || initialUrlParams.get('release')
            || editorCard.dataset.initialCampaign
            || 'primary'
        );
        let creatingPlaylistFromCampaign = false;
        let isEditing = false;
        let campaignEditorTab = 'base';
        let campaignBrandingPreviewToken = 0;
        let campaignBaseBrandPreviewToken = 0;
        let campaignBrandCatalog = [];
        let trackEditorLoadedCampaignId = '';
        let pendingCampaignDeleteId = '';
        let campaignSettingsSaving = false;
        let campaignSettingsSaveQueued = false;
        let pendingCampaignCoverPreviewUrl = '';
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

        const lifecycle = window.bandpromoEditorLifecycle.create({
            root: editorCard,
            poolView: poolView,
            editorView: tracksPoolView,
            saveBtn: null,
            cntab: 'campaign',
            entityParam: 'campaign',
            onShowPool: function () {
                isEditing = false;
                editorCard.classList.add('campaign-editor-is-preview');
                if (campaignAvailableSection) {
                    campaignAvailableSection.hidden = true;
                }
                if (campaignAssociationActiveList) {
                    campaignAssociationActiveList.hidden = true;
                }
                if (campaignAssociationAvailableSection) {
                    campaignAssociationAvailableSection.hidden = true;
                }
                renderCampaignPoolList();
                updateCampaignEditorHint();
                updateCampaignCoverPanel();
            },
            onShowEdit: function (campaignId) {
                isEditing = true;
                editorCard.classList.remove('campaign-editor-is-preview');
                selectedCampaignId = campaignId;
                syncCampaignEditorMode();
                syncCampaignSettingsPanel(campaignId);
                renderCampaignPoolList();
                updateCampaignEditorHint();
                updateCampaignCoverPanel();
            },
            onBeforeClose: async function () {
                if (campaignSettingsDirty()) {
                    const saved = await saveCampaignSettings();
                    if (!saved) {
                        return false;
                    }
                }
                await flushMembershipSaves();
                return true;
            },
            onAfterClose: async function () {
                syncCampaignUrl(selectedCampaignId, false);
                await loadCampaignPreview();
            },
        });

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

        function campaignCoverPreviewUrl(value, entry = null) {
            const raw = String(value || '').trim();
            if (!raw) {
                return pendingCampaignCoverPreviewUrl || '';
            }

            if (pendingCampaignCoverPreviewUrl) {
                const pendingBase = pendingCampaignCoverPreviewUrl.split('?')[0];
                const rawBase = mediaPreviewUrlFromReference(raw).split('?')[0];
                if (!rawBase || pendingBase.endsWith(raw.split('/').pop() || '') || rawBase === pendingBase) {
                    return pendingCampaignCoverPreviewUrl;
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

            const cached = campaignEntry(selectedCampaignId);
            if (cached && String(cached.poster_asset_id || '').trim() === raw) {
                const cachedUrl = String(cached.poster_preview_url || '').trim();
                if (cachedUrl) {
                    return cachedUrl;
                }
            }

            return mediaPreviewUrlFromReference(raw);
        }

        function updateCampaignCoverPreview() {
            const entry = campaignEntry(selectedCampaignId);
            const rawValue = campaignSettingsPosterAssetId instanceof HTMLInputElement
                ? String(campaignSettingsPosterAssetId.value || '').trim()
                : '';
            const previewUrl = campaignCoverPreviewUrl(rawValue, entry);

            if (campaignCoverPreview instanceof HTMLImageElement) {
                if (previewUrl) {
                    if (campaignCoverPreview.getAttribute('src') !== previewUrl) {
                        campaignCoverPreview.src = previewUrl;
                    }
                    campaignCoverPreview.style.display = 'block';
                } else {
                    campaignCoverPreview.removeAttribute('src');
                    campaignCoverPreview.style.display = 'none';
                }
            }
            if (campaignCoverPlaceholder) {
                campaignCoverPlaceholder.style.display = previewUrl ? 'none' : 'block';
            }
            if (campaignCoverPreviewShell instanceof HTMLElement) {
                campaignCoverPreviewShell.title = previewUrl ? 'Campaign cover' : 'No cover selected';
            }
            updateCampaignPosterLabel();
        }

        function setCampaignCoverValue(value) {
            if (!(campaignSettingsPosterAssetId instanceof HTMLInputElement)) {
                return;
            }
            const next = String(value || '').trim();
            pendingCampaignCoverPreviewUrl = next ? mediaPreviewUrlFromReference(next) : '';
            campaignSettingsPosterAssetId.value = next;
            campaignSettingsPosterAssetId.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function campaignTrackCount(entry) {
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

        function updateCampaignCreatePlaylistButton() {
            const button = document.getElementById('campaignCreatePlaylistBtn');
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }
            const entry = campaignEntry(selectedCampaignId);
            const hasTracks = campaignTrackCount(entry) > 0;
            button.disabled = !entry || !hasTracks || creatingPlaylistFromCampaign;
            button.textContent = creatingPlaylistFromCampaign
                ? 'Creating playlist…'
                : 'Create playlist from campaign';
        }

        function hydrateLazyCampaignControls() {
            campaignSettingsBrandId = document.getElementById('campaignSettingsBrandId');
            campaignSettingsDescription = document.getElementById('campaignSettingsDescription');
            campaignSettingsCredits = document.getElementById('campaignSettingsCredits');
            campaignSettingsPressContact = document.getElementById('campaignSettingsPressContact');
            campaignSettingsStreamBandpromo = document.getElementById('campaignSettingsStreamBandpromo');
            campaignSettingsStreamBandpromoLabel = document.getElementById('campaignSettingsStreamBandpromoLabel');
            campaignSettingsStreamSpotify = document.getElementById('campaignSettingsStreamSpotify');
            campaignSettingsStreamApple = document.getElementById('campaignSettingsStreamApple');
            campaignSettingsSocialImports = document.getElementById('campaignSettingsSocialImports');
            campaignSettingsPressPhotos = document.getElementById('campaignSettingsPressPhotos');
        }

        function populateCampaignBrandSelect() {
            if (!(campaignSettingsBrandId instanceof HTMLSelectElement)) {
                return;
            }
            const selected = String(campaignSettingsBrandId.value || campaignEntry(selectedCampaignId)?.brand_id || '');
            campaignSettingsBrandId.innerHTML = '<option value="">Base brand</option>';
            campaignBrandCatalog.forEach((brand) => {
                const id = String(brand?.id || '').trim();
                if (!id) {
                    return;
                }
                const option = document.createElement('option');
                option.value = id === 'setup-default' ? 'bandpromo-default' : id;
                option.textContent = String(brand?.title || id);
                campaignSettingsBrandId.appendChild(option);
            });
            campaignSettingsBrandId.value = selected;
        }

        function bindLazyCampaignEditorControls(section) {
            if (section === 'branding') {
                if (!(campaignSettingsBrandId instanceof HTMLSelectElement)
                    || campaignSettingsBrandId.dataset.campaignLazyBound === 'true'
                ) {
                    return;
                }
                campaignSettingsBrandId.addEventListener('change', () => {
                    refreshCampaignBrandingLivePreview();
                    refreshCampaignBaseBrandPreview();
                    saveCampaignSettings();
                });
                campaignSettingsBrandId.dataset.campaignLazyBound = 'true';
                return;
            }

            if (section !== 'presskit') {
                return;
            }
            [
                campaignSettingsDescription,
                campaignSettingsCredits,
                campaignSettingsPressContact,
                campaignSettingsStreamBandpromo,
                campaignSettingsStreamSpotify,
                campaignSettingsStreamApple,
                campaignSettingsPressPhotos,
            ].forEach((control) => {
                if (!(control instanceof HTMLElement)
                    || control.dataset.campaignLazyBound === 'true'
                ) {
                    return;
                }
                control.addEventListener('input', () => {
                    renderCampaignEditorPresskitPreview();
                });
                control.addEventListener('blur', () => {
                    saveCampaignSettings();
                });
                control.dataset.campaignLazyBound = 'true';
            });
        }

        async function ensureCampaignEditorSection(section) {
            if (section !== 'branding' && section !== 'presskit') {
                return;
            }
            const panel = document.querySelector(`[data-campaign-editor-panel="${section}"]`);
            const template = document.getElementById(
                section === 'branding' ? 'campaignBrandingEditorTemplate' : 'campaignPresskitEditorTemplate'
            );
            if (!(panel instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
                return;
            }
            if (!panel.dataset.loaded) {
                panel.replaceChildren(template.content.cloneNode(true));
                panel.dataset.loaded = 'true';
            }

            hydrateLazyCampaignControls();
            if (section === 'branding') {
                populateCampaignBrandSelect();
            }
            bindLazyCampaignEditorControls(section);
            syncCampaignSettingsPanel(selectedCampaignId);

            if (section === 'presskit') {
                try {
                    const campaignId = String(selectedCampaignId || '').trim();
                    const data = await fetchJson(
                        `/biblioteca/get-campaign-preview-section.php?campaign=${encodeURIComponent(campaignId)}&section=presskit`,
                        { cache: 'no-store' }
                    );
                    const entry = campaignEntry(campaignId);
                    const presskit = data.data && typeof data.data === 'object' ? data.data : {};
                    if (entry) {
                        entry.short_description = String(presskit.short_description || '');
                        entry.description = String(presskit.description || '');
                        entry.epk = presskit.epk && typeof presskit.epk === 'object'
                            ? presskit.epk
                            : defaultCampaignEpk();
                    }
                } catch (error) {
                    showCampaignToast(error.message || 'Could not refresh Press kit editor.', 'error');
                }
            }

            syncCampaignSettingsPanel(selectedCampaignId);
            if (section === 'branding') {
                refreshCampaignBrandingLivePreview();
            } else {
                renderCampaignEditorPresskitPreview();
            }
        }

        function setCampaignEditorTab(tabId) {
            const next = String(tabId || 'base').trim() || 'base';
            const allowed = new Set(['base', 'tracks', 'playlists', 'galleries', 'pages']);
            campaignEditorTab = allowed.has(next) ? next : 'base';
            editorCard.setAttribute('data-campaign-editor-section', campaignEditorTab);

            document.querySelectorAll('[data-campaign-editor-tab]').forEach((button) => {
                const active = String(button.getAttribute('data-campaign-editor-tab') || '') === campaignEditorTab;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-campaign-editor-panel]').forEach((panel) => {
                const active = String(panel.getAttribute('data-campaign-editor-panel') || '') === campaignEditorTab;
                panel.classList.toggle('is-active', active);
                panel.hidden = !active;
            });
            syncCampaignEditorMode();
            if (campaignEditorTab === 'tracks'
                && isEditing
                && trackEditorLoadedCampaignId !== selectedCampaignId
            ) {
                loadCampaignPreview();
            }
            if (ASSOCIATION_KINDS.includes(campaignEditorTab) && isEditing) {
                ensureAssociationEditorLoaded(campaignEditorTab);
            }
            if (campaignEditorTab === 'base' && isEditing) {
                window.requestAnimationFrame(() => autofitCampaignDescriptionField());
            }
        }

        function renderCampaignEditorPresskitPreview() {
            if (!campaignEditorPresskitPreview) {
                return;
            }
            const entry = campaignEntry(selectedCampaignId);
            if (!entry) {
                campaignEditorPresskitPreview.innerHTML = '<p class="campaign-preview-empty">No campaign selected.</p>';
                return;
            }
            const metadata = readCampaignMetadataFromForm();
            campaignEditorPresskitPreview.innerHTML = renderCampaignPreviewPressKit({
                ...entry,
                short_description: metadata.short_description,
                description: metadata.description,
                epk: metadata.epk,
            });
        }

        async function refreshCampaignBrandingLivePreview() {
            if (!isEditing || campaignEditorTab !== 'branding' || !campaignBrandingPreview) {
                return;
            }
            const brandId = campaignSettingsBrandId instanceof HTMLSelectElement
                ? String(campaignSettingsBrandId.value || '').trim()
                : '';
            const token = ++campaignBrandingPreviewToken;
            campaignBrandingPreview.innerHTML = '<p class="theme-editor-empty">Loading brand preview…</p>';

            try {
                const url = brandId
                    ? `/biblioteca/get-brand.php?brand=${encodeURIComponent(brandId)}`
                    : '/biblioteca/get-brand.php';
                const data = await fetchJson(url, { cache: 'no-store' });
                if (token !== campaignBrandingPreviewToken
                    || campaignEditorTab !== 'branding'
                    || !isEditing
                ) {
                    return;
                }
                if (window.bandpromoThemePreview?.render) {
                    window.bandpromoThemePreview.render(campaignBrandingPreview, data.document || null, {
                        styleId: 'bandpromo-campaign-brand-preview-style',
                        selector: '#campaignBrandingPreview .theme-preview-shell-chrome',
                    });
                } else {
                    campaignBrandingPreview.innerHTML = '<p class="theme-editor-empty">Brand preview is unavailable.</p>';
                }
            } catch (error) {
                if (token !== campaignBrandingPreviewToken) {
                    return;
                }
                campaignBrandingPreview.innerHTML = `<p class="theme-editor-empty text-error">${escapeHtml(error.message || 'Could not load brand preview.')}</p>`;
            }
        }

        function syncCampaignEditorMode() {
            if (!isEditing) {
                if (campaignBrandingLivePreview) {
                    campaignBrandingLivePreview.hidden = true;
                }
                if (campaignPresskitLivePreview) {
                    campaignPresskitLivePreview.hidden = true;
                }
                if (campaignEditorPreviewHeading) {
                    campaignEditorPreviewHeading.textContent = 'Preview';
                }
                if (campaignAssociationActiveList) {
                    campaignAssociationActiveList.hidden = true;
                }
                if (campaignAssociationAvailableSection) {
                    campaignAssociationAvailableSection.hidden = true;
                }
                return;
            }
            const baseActive = campaignEditorTab === 'base';
            const tracksActive = campaignEditorTab === 'tracks';
            const associationActive = ASSOCIATION_KINDS.includes(campaignEditorTab);
            const entry = campaignEntry(selectedCampaignId);

            if (campaignCoverPanel) {
                campaignCoverPanel.hidden = !baseActive || !entry;
            }
            if (activeEl) {
                activeEl.hidden = !tracksActive;
            }
            if (campaignAvailableSection) {
                campaignAvailableSection.hidden = !tracksActive;
            }
            if (campaignAssociationActiveList) {
                campaignAssociationActiveList.hidden = !associationActive;
            }
            if (campaignAssociationAvailableSection) {
                campaignAssociationAvailableSection.hidden = !associationActive;
            }
            if (associationActive) {
                const labels = ASSOCIATION_LABELS[campaignEditorTab];
                if (campaignAssociationAvailableHeading && labels) {
                    campaignAssociationAvailableHeading.textContent = labels.available;
                }
                if (campaignAssociationAvailableList && labels) {
                    campaignAssociationAvailableList.setAttribute('aria-label', labels.available);
                }
                if (campaignAssociationActiveList && labels) {
                    campaignAssociationActiveList.setAttribute('aria-label', labels.associated);
                }
                renderAssociationLists();
            }
            if (campaignEditorPreviewHeading) {
                const headings = {
                    base: 'Campaign preview',
                    tracks: 'Associated tracks',
                    playlists: 'Associated playlists',
                    galleries: 'Associated galleries',
                    pages: 'Associated pages',
                };
                campaignEditorPreviewHeading.textContent = headings[campaignEditorTab] || 'Preview';
            }
            refreshCampaignBaseBrandPreview();
            refreshCampaignLongDescriptionPreview();
        }

        function renderCampaignPreviewMeta(entry) {
            const title = String(entry?.title || 'Campaign').trim() || 'Campaign';
            const date = String(entry?.release_date || '').trim();
            const summary = String(entry?.short_description || '').trim();
            if (campaignPreviewTitle) {
                campaignPreviewTitle.textContent = title;
            }
            if (campaignPreviewDate) {
                campaignPreviewDate.textContent = date;
                campaignPreviewDate.hidden = date === '';
            }
            if (campaignPreviewSummary) {
                campaignPreviewSummary.textContent = summary;
                campaignPreviewSummary.hidden = summary === '';
            }
        }

        function updateCampaignBasePreviewFromForm() {
            if (!isEditing || campaignEditorTab !== 'base') {
                return;
            }
            const title = campaignSettingsTitle instanceof HTMLInputElement
                ? String(campaignSettingsTitle.value || '').trim()
                : '';
            const date = campaignSettingsDate instanceof HTMLInputElement
                ? String(campaignSettingsDate.value || '').trim()
                : '';
            const blurb = campaignSettingsShortDescription instanceof HTMLTextAreaElement
                ? String(campaignSettingsShortDescription.value || '').trim()
                : '';

            if (campaignPreviewTitle) {
                campaignPreviewTitle.textContent = title || 'Campaign';
            }
            if (campaignPreviewDate) {
                campaignPreviewDate.textContent = date;
                campaignPreviewDate.hidden = date === '';
            }
            if (campaignPreviewSummary) {
                campaignPreviewSummary.textContent = blurb;
                campaignPreviewSummary.hidden = blurb === '';
            }
            refreshCampaignLongDescriptionPreview();
        }

        function currentLongDescriptionMarkdown(entry = campaignEntry(selectedCampaignId)) {
            if (isEditing && campaignSettingsDescription instanceof HTMLTextAreaElement) {
                return String(campaignSettingsDescription.value || '').trim();
            }
            return String(entry?.description || '').trim();
        }

        function refreshCampaignLongDescriptionPreview() {
            if (!campaignLongDescriptionPreview || !campaignLongDescriptionPreviewBody) {
                return;
            }
            const coverVisible = !!(campaignCoverPanel && !campaignCoverPanel.hidden);
            const showUnderPreview = coverVisible && (!isEditing || campaignEditorTab === 'base');
            if (!showUnderPreview) {
                campaignLongDescriptionPreview.hidden = true;
                campaignLongDescriptionPreviewBody.innerHTML = '';
                return;
            }

            const markdown = currentLongDescriptionMarkdown();
            campaignLongDescriptionPreview.hidden = false;
            if (!markdown) {
                campaignLongDescriptionPreviewBody.innerHTML = '<p class="campaign-preview-empty">No long description yet.</p>';
                return;
            }

            const rendered = typeof window.bandpromoPlayerMarkdown?.render === 'function'
                ? window.bandpromoPlayerMarkdown.render(markdown)
                : '';
            if (rendered) {
                campaignLongDescriptionPreviewBody.innerHTML = rendered;
                return;
            }

            campaignLongDescriptionPreviewBody.innerHTML = `<p>${escapeHtml(markdown).replace(/\n/g, '<br>')}</p>`;
        }

        function renderCampaignPreviewBranding(entry) {
            const children = ownershipChildren(entry);
            const brand = children.brand;
            const brandId = String(children.brand_id || brand?.id || '').trim();
            if (!brandId) {
                return '<p class="campaign-preview-empty">No brand linked to this campaign yet.</p>';
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
                    return `<span class="campaign-preview-swatch" title="${escapeHtml(key)}" style="background:${escapeHtml(color)}"></span>`;
                })
                .filter(Boolean)
                .join('');
            const shellStyle = background
                ? ` style="background-image:url('${escapeHtml(background)}')"`
                : '';
            const logoHtml = logo
                ? `<img class="campaign-preview-brand-logo" src="${escapeHtml(logo)}" alt="">`
                : '<span class="campaign-preview-empty">No logo assigned</span>';
            return `<div class="campaign-preview-brand">
                <div class="campaign-preview-brand-shell"${shellStyle}>${logoHtml}</div>
                <div class="campaign-preview-brand-copy">
                    <h5 class="campaign-preview-brand-title">${escapeHtml(title)}</h5>
                    ${mood ? `<p class="campaign-preview-brand-mood">${escapeHtml(mood)}</p>` : ''}
                    ${swatches ? `<div class="campaign-preview-swatches">${swatches}</div>` : ''}
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

        function currentBaseBrandId(entry = campaignEntry(selectedCampaignId)) {
            if (isEditing && campaignSettingsBrandId instanceof HTMLSelectElement) {
                return String(campaignSettingsBrandId.value || '').trim();
            }
            const children = ownershipChildren(entry);
            return String(children.brand_id || entry?.brand_id || '').trim();
        }

        async function refreshCampaignBaseBrandPreview() {
            if (!campaignBaseBrandPreview || !campaignBaseBrandPreviewBody) {
                return;
            }
            const coverVisible = !!(campaignCoverPanel && !campaignCoverPanel.hidden);
            const showUnderPreview = coverVisible && (!isEditing || campaignEditorTab === 'base');
            if (!showUnderPreview) {
                campaignBaseBrandPreview.hidden = true;
                campaignBaseBrandPreviewBody.innerHTML = '';
                return;
            }

            const entry = campaignEntry(selectedCampaignId);
            const brandId = currentBaseBrandId(entry);
            const children = ownershipChildren(entry);
            const token = ++campaignBaseBrandPreviewToken;
            campaignBaseBrandPreview.hidden = false;

            if (brandId && children.brand && String(children.brand_id || children.brand.id || '') === brandId) {
                campaignBaseBrandPreviewBody.innerHTML = renderCampaignPreviewBranding(entry);
                return;
            }

            if (!brandId && children.brand && !isEditing) {
                campaignBaseBrandPreviewBody.innerHTML = renderCampaignPreviewBranding(entry);
                return;
            }

            campaignBaseBrandPreviewBody.innerHTML = '<p class="campaign-preview-empty">Loading brand preview…</p>';
            try {
                if (!brandId) {
                    campaignBaseBrandPreviewBody.innerHTML = '<p class="campaign-preview-empty">No brand linked to this campaign yet.</p>';
                    return;
                }
                const url = `/biblioteca/get-brand.php?brand=${encodeURIComponent(brandId)}`;
                const data = await fetchJson(url, { cache: 'no-store' });
                if (token !== campaignBaseBrandPreviewToken) {
                    return;
                }
                const brand = brandPreviewModelFromThemeDocument(data.document || null);
                if (!brand) {
                    campaignBaseBrandPreviewBody.innerHTML = '<p class="campaign-preview-empty">No brand linked to this campaign yet.</p>';
                    return;
                }
                campaignBaseBrandPreviewBody.innerHTML = renderCampaignPreviewBranding({
                    brand_id: brand.id,
                    ownership_children: {
                        brand_id: brand.id,
                        brand,
                    },
                });
            } catch (error) {
                if (token !== campaignBaseBrandPreviewToken) {
                    return;
                }
                campaignBaseBrandPreviewBody.innerHTML = `<p class="campaign-preview-empty text-error">${escapeHtml(error.message || 'Could not load brand preview.')}</p>`;
            }
        }

        function renderCampaignPreviewPressKit(entry) {
            const epk = normalizeCampaignEpk(entry?.epk);
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
                return '<p class="campaign-preview-empty">No press kit content yet. Open edit to fill the EPK.</p>';
            }
            return `<dl class="campaign-preview-epk">${rows.map(([label, value]) => (
                `<dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value).replace(/\n/g, '<br>')}</dd>`
            )).join('')}</dl>`;
        }

        function updateCampaignCoverPanel() {
            const entry = campaignEntry(selectedCampaignId);
            if (campaignCoverPanel) {
                campaignCoverPanel.hidden = !entry || (isEditing && campaignEditorTab !== 'base');
            }
            renderCampaignPreviewMeta(entry);
            if (entry && campaignSettingsPosterAssetId instanceof HTMLInputElement && !isEditing) {
                campaignSettingsPosterAssetId.value = String(entry.poster_asset_id || '').trim();
            }
            const canEditCover = !!(isEditing && entry && !entry.locked);
            if (campaignCoverOverlayActions instanceof HTMLElement) {
                campaignCoverOverlayActions.hidden = !isEditing;
            }
            const coverEditButtons = campaignCoverPanel
                ? campaignCoverPanel.querySelectorAll('.audio-master-cover-overlay-actions button')
                : [];
            coverEditButtons.forEach((button) => {
                if (button instanceof HTMLButtonElement) {
                    button.disabled = !canEditCover;
                }
            });
            if (activeEl) {
                activeEl.hidden = !isEditing || campaignEditorTab !== 'tracks';
            }
            updateCampaignCreatePlaylistButton();
            updateCampaignCoverPreview();
            syncCampaignEditorMode();
            refreshCampaignBaseBrandPreview();
            refreshCampaignLongDescriptionPreview();
        }

        async function createPlaylistFromCampaign() {
            const entry = campaignEntry(selectedCampaignId);
            if (!entry || campaignTrackCount(entry) <= 0) {
                showCampaignToast('Add tracks to the campaign before creating a playlist.');
                return;
            }

            creatingPlaylistFromCampaign = true;
            updateCampaignCreatePlaylistButton();
            try {
                const saved = await saveCampaignSettings({ silent: true });
                if (!saved) {
                    throw new Error('Save the campaign settings before creating a playlist.');
                }

                const data = await fetchJson('/biblioteca/manage-playlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ from_release_id: selectedCampaignId }),
                });
                if (!data.ok) {
                    throw new Error(data.error || 'Could not create playlist');
                }
                const playlistId = String(data.playlist?.id || '').trim();
                if (!playlistId) {
                    throw new Error('Playlist was created but its id is missing.');
                }
                window.location.href = `?tab=content&cntab=playlist&playlist=${encodeURIComponent(playlistId)}&edit=1&campaign=${encodeURIComponent(selectedCampaignId)}`;
            } catch (error) {
                showCampaignToast(error.message || 'Could not create playlist', 'error');
                creatingPlaylistFromCampaign = false;
                updateCampaignCreatePlaylistButton();
            }
        }

        function initCampaignCoverPicker() {
            if (!campaignCoverPanel) {
                return;
            }

            campaignCoverPanel.querySelectorAll('.media-picker-open').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (typeof window.openMediaPicker !== 'function') {
                        showCampaignToast('Media picker is not available. Reload the page.');
                        return;
                    }
                    window.openMediaPicker(
                        button.dataset.field || 'campaignSettingsPosterAssetId',
                        button.dataset.title || 'Choose campaign cover',
                        button.dataset.targets || 'illustrations,photos,special'
                    );
                });
            });

            campaignCoverClearBtn?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                setCampaignCoverValue('');
            });

            window.bandpromoCampaignCoverPicked = function bandpromoCampaignCoverPicked(path) {
                const next = String(path || '').trim();
                pendingCampaignCoverPreviewUrl = next ? mediaPreviewUrlFromReference(next) : '';
                updateCampaignCoverPreview();
            };
        }

        function updateCampaignPosterLabel() {
            const input = campaignSettingsPosterAssetId;
            const label = document.getElementById('campaignSettingsPosterAssetId_label');
            if (!(input instanceof HTMLInputElement) || !label) {
                return;
            }
            const emptyLabel = input.dataset.emptyLabel || 'No cover selected';
            const rawValue = String(input.value || '').trim();
            const fileName = rawValue.includes('/') ? rawValue.split('/').pop() : rawValue;
            label.textContent = fileName || emptyLabel;
            label.classList.toggle('empty', !fileName);
        }

        function renderCampaignSocialImports() {
            if (!campaignSettingsSocialImports) {
                return;
            }
            const profiles = [
                { label: 'Twitter / X', url: buildSocialProfileUrl('twitter', siteSharing.twitter) },
                { label: 'Facebook', url: buildSocialProfileUrl('facebook', siteSharing.facebook) },
                { label: 'Instagram', url: buildSocialProfileUrl('instagram', siteSharing.instagram) },
            ].filter((entry) => entry.url);

            if (!profiles.length) {
                campaignSettingsSocialImports.hidden = true;
                campaignSettingsSocialImports.innerHTML = '';
                return;
            }

            campaignSettingsSocialImports.hidden = false;
            campaignSettingsSocialImports.innerHTML = `
                <span class="campaign-social-inline-label">Social:</span>
                <span class="campaign-social-inline-links">${profiles.map((entry, index) => (
                    `${index > 0 ? '<span class="campaign-social-inline-sep" aria-hidden="true">·</span>' : ''}<a href="${escapeHtml(entry.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(entry.label)}</a>`
                )).join('')}</span>
            `;
        }

        function defaultCampaignEpk() {
            return {
                tagline: '',
                genre: '',
                credits: '',
                press_contact: '',
                streaming_links: [],
                press_photo_asset_ids: [],
            };
        }

        function defaultCampaignSettingsBaseline() {
            return {
                title: '',
                release_date: '',
                catalog_id: '',
                locked: false,
                short_description: '',
                description: '',
                poster_asset_id: '',
                brand_id: '',
                epk: defaultCampaignEpk(),
            };
        }

        let campaignSettingsBaseline = defaultCampaignSettingsBaseline();

        function normalizeCampaignEpk(epk) {
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

        function autofitCampaignDescriptionField() {
            if (!(campaignSettingsDescription instanceof HTMLTextAreaElement)) {
                return;
            }
            const field = campaignSettingsDescription;
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
            if (!(campaignSettingsPressPhotos instanceof HTMLTextAreaElement)) {
                return Array.isArray(campaignSettingsBaseline.epk?.press_photo_asset_ids)
                    ? campaignSettingsBaseline.epk.press_photo_asset_ids.slice()
                    : [];
            }
            return String(campaignSettingsPressPhotos.value || '')
                .split(/[,\n]+/)
                .map((assetId) => assetId.trim())
                .filter(Boolean);
        }

        function readStreamingLinksFromForm() {
            if (!(campaignSettingsStreamBandpromo instanceof HTMLInputElement)
                && !(campaignSettingsStreamSpotify instanceof HTMLInputElement)
                && !(campaignSettingsStreamApple instanceof HTMLInputElement)
            ) {
                return Array.isArray(campaignSettingsBaseline.epk?.streaming_links)
                    ? campaignSettingsBaseline.epk.streaming_links.map((link) => ({ ...link }))
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

            addLink(bandpromoSiteLabel(), campaignSettingsStreamBandpromo);
            addLink(STREAMING_PRESET_LABELS.spotify, campaignSettingsStreamSpotify);
            addLink(STREAMING_PRESET_LABELS.apple, campaignSettingsStreamApple);

            return links;
        }

        function updateCampaignShortDescriptionCount() {
            if (!(campaignSettingsShortDescription instanceof HTMLTextAreaElement)
                || !campaignSettingsShortDescriptionCount) {
                return;
            }
            campaignSettingsShortDescriptionCount.textContent = String(campaignSettingsShortDescription.value.length);
        }

        function readCampaignMetadataFromForm() {
            return {
                short_description: campaignSettingsShortDescription instanceof HTMLTextAreaElement
                    ? String(campaignSettingsShortDescription.value || '').trim()
                    : '',
                description: campaignSettingsDescription instanceof HTMLTextAreaElement
                    ? String(campaignSettingsDescription.value || '').trim()
                    : String(campaignSettingsBaseline.description || '').trim(),
                poster_asset_id: campaignSettingsPosterAssetId instanceof HTMLInputElement
                    ? String(campaignSettingsPosterAssetId.value || '').trim()
                    : '',
                brand_id: campaignSettingsBrandId instanceof HTMLSelectElement
                    ? String(campaignSettingsBrandId.value || '').trim()
                    : String(campaignSettingsBaseline.brand_id || '').trim(),
                epk: {
                    tagline: String(campaignSettingsBaseline.epk?.tagline || '').trim(),
                    genre: String(campaignSettingsBaseline.epk?.genre || '').trim(),
                    credits: campaignSettingsCredits instanceof HTMLTextAreaElement
                        ? String(campaignSettingsCredits.value || '').trim()
                        : String(campaignSettingsBaseline.epk?.credits || '').trim(),
                    press_contact: campaignSettingsPressContact instanceof HTMLInputElement
                        ? String(campaignSettingsPressContact.value || '').trim()
                        : String(campaignSettingsBaseline.epk?.press_contact || '').trim(),
                    streaming_links: readStreamingLinksFromForm(),
                    press_photo_asset_ids: readPressPhotoIdsFromForm(),
                },
            };
        }

        function readCampaignSettingsFromForm() {
            const entry = campaignEntry(selectedCampaignId);
            const titleFromInput = campaignSettingsTitle instanceof HTMLInputElement
                ? String(campaignSettingsTitle.value || '').trim()
                : '';
            const dateFromInput = campaignSettingsDate instanceof HTMLInputElement
                ? String(campaignSettingsDate.value || '').trim()
                : '';
            const catalogFromInput = campaignSettingsCatalogId instanceof HTMLInputElement
                ? String(campaignSettingsCatalogId.value || '').trim()
                : '';
            const title = titleFromInput || String(entry?.title || '').trim();
            const campaignDate = dateFromInput || normalizeCampaignDateForInput(entry?.release_date);
            const catalogId = catalogFromInput || String(entry?.catalog_id || '').trim();

            return {
                title,
                release_date: campaignDate,
                catalog_id: catalogId,
                locked: !!entry?.locked,
                ...readCampaignMetadataFromForm(),
            };
        }

        function setCampaignMetadataDisabled(disabled) {
            const controls = [
                campaignSettingsShortDescription,
                campaignSettingsDescription,
                campaignSettingsCredits,
                campaignSettingsPressContact,
                campaignSettingsStreamBandpromo,
                campaignSettingsStreamSpotify,
                campaignSettingsStreamApple,
                campaignSettingsPressPhotos,
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
        const rangeSelection = window.bandpromoRangeSelection.create({
            dataKey: 'file',
            getAvailableRows: getAvailableRows,
            getActiveRows: getActiveRows,
            onSelectionChange: function (listName) {
                if (listName === 'available') {
                    syncAvailableSelectionUi();
                    return;
                }
                syncActiveSelectionUi();
            },
        });
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
            return ASSOCIATION_KINDS.includes(campaignEditorTab) ? campaignEditorTab : '';
        }

        function associationListElement(listName) {
            return listName === 'active' ? campaignAssociationActiveList : campaignAssociationAvailableList;
        }

        function getAssociationRows(kind, listName) {
            const listEl = associationListElement(listName);
            if (!listEl) {
                return [];
            }
            return Array.from(
                listEl.querySelectorAll(`.playlist-editor-row[data-kind="${kind}"]`)
            );
        }

        const associationRangeSelection = ASSOCIATION_KINDS.reduce((acc, kind) => {
            acc[kind] = window.bandpromoRangeSelection.create({
                dataKey: 'id',
                getAvailableRows: function () {
                    return getAssociationRows(kind, 'available');
                },
                getActiveRows: function () {
                    return getAssociationRows(kind, 'active');
                },
                onSelectionChange: function () {
                    syncAssociationSelectionUi(kind);
                },
            });
            return acc;
        }, {});

        function associationRange(kind) {
            return associationRangeSelection[kind] || null;
        }

        function syncAssociationSelectionUi(kind) {
            const range = associationRange(kind);
            if (!range) {
                return;
            }
            getAssociationRows(kind, 'available').forEach((row) => {
                const id = String(row.dataset.id || '');
                const selected = range.getSelected('available').has(id);
                row.classList.toggle('playlist-editor-row-selected', selected);
                row.classList.toggle('editor-row--selected', selected);
                row.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            getAssociationRows(kind, 'active').forEach((row) => {
                const id = String(row.dataset.id || '');
                const selected = range.getSelected('active').has(id);
                row.classList.toggle('playlist-editor-row-selected', selected);
                row.classList.toggle('editor-row--selected', selected);
                row.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }

        function pruneAssociationSelection(kind) {
            const range = associationRange(kind);
            if (!range) {
                return;
            }
            ['available', 'active'].forEach((listName) => {
                const allowed = new Set(getAssociationRows(kind, listName).map((row) => String(row.dataset.id || '')).filter(Boolean));
                range.getSelected(listName).forEach((id) => {
                    if (!allowed.has(id)) {
                        range.getSelected(listName).delete(id);
                    }
                });
                const anchor = range.getAnchor(listName);
                if (anchor && !allowed.has(anchor)) {
                    range.setAnchor(listName, '');
                }
            });
            syncAssociationSelectionUi(kind);
        }

        function associationEditingEnabled(entry = campaignEntry(selectedCampaignId)) {
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

        function renderAssociationRow(item, { showRemove = false, draggable = false, canEdit = false, kind = '', listName = 'available', selected = false } = {}) {
            const id = escapeHtml(item.id || '');
            const title = escapeHtml(item.title || item.id || 'Untitled');
            const meta = item.publish_date
                ? `<span class="playlist-track-meta">${escapeHtml(item.publish_date)}</span>`
                : '';
            const removeMarkup = showRemove
                ? '<button type="button" class="player-layout-remove-btn" title="Remove from campaign" aria-label="Remove from campaign">✕</button>'
                : '';
            const dragHandle = draggable
                ? '<span class="playlist-drag-handle editor-drag-handle" title="Drag into campaign">⠿</span>'
                : '';
            const readonlyClass = canEdit ? '' : ' playlist-editor-row-readonly editor-row--readonly';
            const activeRowClass = showRemove || !draggable ? ' player-layout-row-active' : '';
            const selectedClass = selected ? ' playlist-editor-row-selected editor-row--selected' : '';
            return `<li class="playlist-editor-row editor-row${activeRowClass}${readonlyClass}${selectedClass}" draggable="${draggable ? 'true' : 'false'}" data-id="${id}" data-kind="${escapeHtml(kind)}" data-list="${escapeHtml(listName)}" aria-selected="${selected ? 'true' : 'false'}">
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
            if (!kind || !campaignAssociationActiveList || !campaignAssociationAvailableList) {
                return;
            }
            const labels = ASSOCIATION_LABELS[kind];
            const pool = associationPools[kind];
            const canEdit = associationEditingEnabled();
            const range = associationRange(kind);
            const active = sortAssociationItems(kind, pool.active);
            const available = sortAssociationItems(kind, pool.available);
            pool.active = active;
            pool.available = available;

            if (pool.loadedFor !== selectedCampaignId) {
                campaignAssociationActiveList.innerHTML = '<li class="player-layout-empty">Loading…</li>';
                campaignAssociationAvailableList.innerHTML = '<li class="player-layout-empty">Loading…</li>';
                return;
            }

            if (!active.length) {
                campaignAssociationActiveList.innerHTML = canEdit
                    ? `<li class="player-layout-empty">Drag ${labels.plural} here from ${labels.available}.</li>`
                    : `<li class="player-layout-empty">This campaign has no ${labels.plural} yet.</li>`;
            } else {
                campaignAssociationActiveList.innerHTML = active.map((item) => renderAssociationRow(item, {
                    showRemove: canEdit && item.movable !== false,
                    draggable: false,
                    canEdit,
                    kind,
                    listName: 'active',
                    selected: !!range?.getSelected('active').has(String(item.id || '')),
                })).join('');
            }

            if (!available.length) {
                const contentTab = labels.plural.charAt(0).toUpperCase() + labels.plural.slice(1);
                const emptyMessage = canEdit
                    ? (active.length
                        ? `No unassigned ${labels.plural} to add. Unassigned ${labels.plural} would appear here; every other ${labels.singular} is already owned by another campaign.`
                        : `No unassigned ${labels.plural} to add. Unassigned ${labels.plural} would appear here. Create one in Content → ${contentTab}, or unassign one from another campaign.`)
                    : `${labels.associated} are preview-only while this campaign is locked.`;
                campaignAssociationAvailableList.innerHTML = `<li class="player-layout-empty">${emptyMessage}</li>`;
            } else {
                campaignAssociationAvailableList.innerHTML = available.map((item) => renderAssociationRow(item, {
                    showRemove: false,
                    draggable: canEdit && item.movable !== false,
                    canEdit,
                    kind,
                    listName: 'available',
                    selected: !!range?.getSelected('available').has(String(item.id || '')),
                })).join('');
            }

            pruneAssociationSelection(kind);
        }

        function moveAssociationItems(kind, fromList, toList, ids) {
            if (!associationEditingEnabled()) {
                return false;
            }
            const pool = associationPools[kind];
            if (!pool || pool.loadedFor !== selectedCampaignId) {
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
                clone.release_id = toList === 'active' ? selectedCampaignId : '';
                target.push(clone);
            });
            const range = associationRange(kind);
            if (range) {
                idSet.forEach((id) => {
                    range.getSelected(fromList).delete(id);
                    range.getSelected(toList).delete(id);
                });
                const fromAnchor = range.getAnchor(fromList);
                if (fromAnchor && idSet.has(fromAnchor)) {
                    range.setAnchor(fromList, '');
                }
                const toAnchor = range.getAnchor(toList);
                if (toAnchor && idSet.has(toAnchor)) {
                    range.setAnchor(toList, '');
                }
            }
            pool.active = sortAssociationItems(kind, pool.active);
            pool.available = sortAssociationItems(kind, pool.available);
            renderAssociationLists();
            void persistCampaignAssociations(kind);
            return true;
        }

        async function ensureAssociationEditorLoaded(kind) {
            if (!ASSOCIATION_KINDS.includes(kind) || !selectedCampaignId || !isEditing) {
                return;
            }
            const pool = associationPools[kind];
            if (pool.loadedFor === selectedCampaignId) {
                renderAssociationLists();
                return;
            }
            try {
                const data = await fetchJson(
                    `/biblioteca/get-campaign-associations.php?campaign=${encodeURIComponent(selectedCampaignId)}&kind=${encodeURIComponent(kind)}`
                );
                const responseCampaignId = String(data.campaign_id || data.release_id || '');
                if (!isEditing || selectedCampaignId !== responseCampaignId || campaignEditorTab !== kind) {
                    return;
                }
                associationPools[kind] = {
                    active: Array.isArray(data.active) ? data.active.map(cloneAssociationItem) : [],
                    available: Array.isArray(data.available) ? data.available.map(cloneAssociationItem) : [],
                    loadedFor: selectedCampaignId,
                };
                renderAssociationLists();
            } catch (error) {
                if (campaignAssociationActiveList) {
                    campaignAssociationActiveList.innerHTML = `<li class="player-layout-empty" style="color:#f87171">${escapeHtml(error.message || 'Could not load associations')}</li>`;
                }
                if (campaignAssociationAvailableList) {
                    campaignAssociationAvailableList.innerHTML = '<li class="player-layout-empty"></li>';
                }
            }
        }

        async function persistCampaignAssociations(kind) {
            if (!ASSOCIATION_KINDS.includes(kind) || !associationEditingEnabled()) {
                return true;
            }
            const pool = associationPools[kind];
            if (!pool || pool.loadedFor !== selectedCampaignId) {
                return true;
            }
            const campaignId = selectedCampaignId;
            const token = ++associationsPersistToken[kind];
            const ids = pool.active.map((item) => String(item.id || '')).filter(Boolean);
            const work = (async () => {
                try {
                    const data = await fetchJson(
                        `/biblioteca/save-campaign-associations.php?campaign=${encodeURIComponent(campaignId)}`,
                        {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ kind, ids }),
                        }
                    );
                    if (token !== associationsPersistToken[kind] || campaignId !== selectedCampaignId) {
                        return true;
                    }
                    associationPools[kind] = {
                        active: Array.isArray(data.active) ? data.active.map(cloneAssociationItem) : [],
                        available: Array.isArray(data.available) ? data.available.map(cloneAssociationItem) : [],
                        loadedFor: campaignId,
                    };
                    const entry = campaignEntry(campaignId);
                    if (entry && data.ownership_children && typeof data.ownership_children === 'object') {
                        entry.ownership_children = data.ownership_children;
                    }
                    if (kind === currentAssociationKind()) {
                        renderAssociationLists();
                    }
                    renderCampaignPoolList();
                    return true;
                } catch (error) {
                    if (token !== associationsPersistToken[kind]) {
                        return false;
                    }
                    showCampaignToast(error.message || 'Could not save associations', 'error');
                    associationPools[kind] = { active: [], available: [], loadedFor: '' };
                    await ensureAssociationEditorLoaded(kind);
                    return false;
                }
            })();
            associationsPersistPromise[kind] = work;
            return work;
        }

        async function persistCampaignTracks() {
            if (!campaignTrackEditingEnabled() || trackEditorLoadedCampaignId !== selectedCampaignId) {
                return true;
            }
            const campaignId = selectedCampaignId;
            const token = ++tracksPersistToken;
            const order = activeTracks.map((track) => String(track.file || '')).filter(Boolean);
            const work = (async () => {
                try {
                    const data = await fetchJson(`/biblioteca/save-campaign-tracks.php?campaign=${encodeURIComponent(campaignId)}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(order),
                    });
                    if (token !== tracksPersistToken || campaignId !== selectedCampaignId) {
                        return true;
                    }
                    const preview = await fetchJson(`/biblioteca/get-campaign-preview.php?campaign=${encodeURIComponent(campaignId)}`);
                    if (token !== tracksPersistToken || campaignId !== selectedCampaignId) {
                        return true;
                    }
                    applyPreviewData(preview);
                    await loadCampaignRegistry();
                    if (data.warning) {
                        showCampaignToast(data.warning, 'warning');
                    }
                    return true;
                } catch (error) {
                    if (token !== tracksPersistToken) {
                        return false;
                    }
                    showCampaignToast(error.message || 'Could not save campaign tracks', 'error');
                    trackEditorLoadedCampaignId = '';
                    await loadCampaignPreview();
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

        function collectAssociationDraggedIds(kind, listName, listEl) {
            const range = associationRange(kind);
            if (!range) {
                return [];
            }
            const selected = range.getSelected(listName);
            if (!selected.size) {
                return [];
            }
            const rowIds = getAssociationRows(kind, listName)
                .map((row) => String(row.dataset.id || '').trim())
                .filter(Boolean);
            return rowIds.filter((id) => selected.has(id));
        }

        function bindAssociationDragList(listEl, listName) {
            if (!listEl) {
                return;
            }

            function clearAssociationDragUi() {
                document.querySelectorAll('.campaign-association-row-dragging').forEach((row) => {
                    row.classList.remove('campaign-association-row-dragging');
                });
                campaignAssociationActiveList?.classList.remove('is-drop-target');
                campaignAssociationAvailableList?.classList.remove('is-drop-target');
            }

            listEl.addEventListener('dragstart', (event) => {
                const kind = currentAssociationKind();
                if (!kind || !associationEditingEnabled()) {
                    event.preventDefault();
                    return;
                }
                const row = event.target instanceof HTMLElement
                    ? event.target.closest('.playlist-editor-row, .editor-row')
                    : null;
                if (!row || !listEl.contains(row) || row.getAttribute('draggable') !== 'true') {
                    return;
                }
                const id = String(row.dataset.id || '').trim();
                if (!id) {
                    event.preventDefault();
                    return;
                }
                const range = associationRange(kind);
                if (range && listName === 'available' && !range.getSelected('available').has(id)) {
                    range.setSelected('available', new Set([id]));
                    range.setAnchor('available', id);
                    range.setSelected('active', new Set());
                    range.setAnchor('active', '');
                    syncAssociationSelectionUi(kind);
                }
                associationDragKind = kind;
                associationDragSource = listName;
                const selectedIds = collectAssociationDraggedIds(kind, listName, listEl);
                associationDragIds = selectedIds.length ? selectedIds : [id];
                getAssociationRows(kind, listName).forEach((dragRow) => {
                    const dragId = String(dragRow.dataset.id || '').trim();
                    if (associationDragIds.includes(dragId)) {
                        dragRow.classList.add('campaign-association-row-dragging');
                    }
                });
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

        function validateCampaignDate(value) {
            const trimmed = String(value || '').trim();
            if (trimmed === '') {
                return 'Release date is required.';
            }
            if (!/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
                return 'Release date must use YYYY-MM-DD.';
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
                originCampaignId: String(track.originCampaignId || track.release_id || ''),
            };
        }

        function campaignEntry(campaignId) {
            return campaigns.find((entry) => entry && entry.id === campaignId) || null;
        }

        function campaignIsProtected(entryOrId) {
            const campaignId = typeof entryOrId === 'string'
                ? entryOrId
                : String(entryOrId?.id || '');
            return PROTECTED_CAMPAIGN_IDS.has(campaignId);
        }

        function campaignIsPlatformDemo(entryOrId) {
            if (typeof entryOrId === 'object' && entryOrId && typeof entryOrId.platform_demo === 'boolean') {
                return entryOrId.platform_demo;
            }
            const campaignId = typeof entryOrId === 'string'
                ? entryOrId
                : String(entryOrId?.id || '');
            return campaignId === 'bandpromo-demo';
        }

        function campaignMayChangeLock(entryOrId) {
            if (typeof entryOrId === 'object' && entryOrId && typeof entryOrId.can_change_lock === 'boolean') {
                return entryOrId.can_change_lock;
            }
            if (!campaignIsPlatformDemo(entryOrId)) {
                return true;
            }
            return isLocalDevHost;
        }

        /** @deprecated Demo is a normal locked campaign; kept for older call sites. */
        function campaignIsSystemManaged() {
            return false;
        }

        function campaignCanDelete(entry) {
            return !!entry && !campaignIsProtected(entry);
        }

        function campaignCanOpenEditor(entry) {
            return !!entry;
        }

        function campaignTrackEditingEnabled(entry = campaignEntry(selectedCampaignId)) {
            return !!(isEditing && entry && !entry.locked);
        }

        function formatCampaignDuration(seconds) {
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

            const campaignId = String(track.release_id || '').trim();
            if (campaignId && campaignId !== selectedCampaignId) {
                const campaignLabel = String(campaignEntry(campaignId)?.title || campaignId).trim();
                if (campaignLabel) {
                    parts.push(`on ${campaignLabel}`);
                }
            }

            return parts.join(' · ');
        }

        function campaignPoolMetaHtml(entry) {
            if (!entry) {
                return '';
            }

            const trackCount = Number(entry.track_count || 0);
            const tracksLabel = trackCount === 1 ? '1 track' : `${trackCount} tracks`;
            const campaignDate = escapeHtml(String(entry.release_date || '').trim());

            let line = escapeHtml(tracksLabel);
            if (campaignDate) {
                line += ` released ${campaignDate}`;
            }

            return line;
        }

        function sortCampaignEntries(list) {
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

        function syncCampaignUrl(campaignId, editing = isEditing) {
            lifecycle.syncUrl(campaignId, editing);

            // Keep Content → Catalogue tab href in sync (baked at page render otherwise
            // pins the prior campaign — often bandpromo-demo — after create/switch).
            document.querySelectorAll('a.tab-link[href*="cntab=campaign"]').forEach((link) => {
                try {
                    const href = new URL(link.getAttribute('href') || '', window.location.origin);
                    href.searchParams.set('tab', 'content');
                    href.searchParams.set('cntab', 'campaign');
                    href.searchParams.set('campaign', campaignId);
                    href.searchParams.delete('edit');
                    link.setAttribute('href', `${href.pathname}${href.search}`);
                } catch (_error) {
                    // ignore malformed hrefs
                }
            });
        }

        function setAddCampaignPanelOpen(open) {
            if (!addCampaignPanel || !toggleAddCampaignBtn) {
                return;
            }
            addCampaignPanel.hidden = !open;
            toggleAddCampaignBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggleAddCampaignBtn.classList.toggle('active', open);
            if (open) {
                const titleInput = addCampaignForm?.querySelector('input[name="title"]');
                if (titleInput instanceof HTMLInputElement) {
                    titleInput.focus();
                }
            } else if (campaignRegistryStatus) {
                campaignRegistryStatus.textContent = '';
                campaignRegistryStatus.style.color = '';
            }
        }

        function normalizeCampaignDateForInput(value) {
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

        function campaignSettingsDirty() {
            return JSON.stringify(readCampaignSettingsFromForm()) !== JSON.stringify(campaignSettingsBaseline);
        }

        function syncCampaignSettingsPanel(campaignId) {
            const entry = campaignEntry(campaignId);
            const title = String(entry?.title || campaignId || '');
            const campaignDate = normalizeCampaignDateForInput(entry?.release_date);
            const locked = !!entry?.locked;
            const metadataLocked = locked;
            const description = String(entry?.description || '').trim();
            const shortDescription = String(entry?.short_description || '').trim();
            const catalogId = String(entry?.catalog_id || '').trim();
            const posterAssetId = String(entry?.poster_asset_id || '').trim();
            const brandId = String(entry?.brand_id || '').trim();
            const epk = normalizeCampaignEpk(entry?.epk);
            const bandpromoListenUrl = streamingUrlForBandpromo(epk.streaming_links);

            campaignSettingsBaseline = {
                title,
                release_date: campaignDate,
                catalog_id: catalogId,
                locked,
                short_description: shortDescription,
                description,
                poster_asset_id: posterAssetId,
                brand_id: brandId,
                epk,
            };

            if (campaignSettingsStreamBandpromoLabel) {
                campaignSettingsStreamBandpromoLabel.textContent = bandpromoSiteLabel();
            }
            renderCampaignSocialImports();

            if (campaignSettingsTitle instanceof HTMLInputElement) {
                campaignSettingsTitle.value = title;
                campaignSettingsTitle.disabled = metadataLocked;
            }
            if (campaignSettingsDate instanceof HTMLInputElement) {
                campaignSettingsDate.value = campaignDate;
                campaignSettingsDate.disabled = metadataLocked;
                if (typeof window.bandpromoSyncIsoDateField === 'function') {
                    window.bandpromoSyncIsoDateField(campaignSettingsDate);
                }
            }
            if (campaignSettingsCatalogId instanceof HTMLInputElement) {
                campaignSettingsCatalogId.value = catalogId;
                campaignSettingsCatalogId.disabled = metadataLocked;
            }
            if (campaignSettingsBrandId instanceof HTMLSelectElement) {
                campaignSettingsBrandId.value = brandId;
                campaignSettingsBrandId.disabled = metadataLocked;
            }
            if (campaignSettingsShortDescription instanceof HTMLTextAreaElement) {
                campaignSettingsShortDescription.value = shortDescription;
                updateCampaignShortDescriptionCount();
            }
            if (campaignSettingsDescription instanceof HTMLTextAreaElement) {
                campaignSettingsDescription.value = description;
                autofitCampaignDescriptionField();
            }
            if (campaignSettingsPosterAssetId instanceof HTMLInputElement) {
                campaignSettingsPosterAssetId.value = posterAssetId;
            }
            if (campaignSettingsCredits instanceof HTMLTextAreaElement) {
                campaignSettingsCredits.value = epk.credits;
            }
            if (campaignSettingsPressContact instanceof HTMLInputElement) {
                campaignSettingsPressContact.value = resolvePressContact(epk.press_contact);
            }
            if (campaignSettingsStreamBandpromo instanceof HTMLInputElement) {
                campaignSettingsStreamBandpromo.value = bandpromoListenUrl;
            }
            if (campaignSettingsStreamSpotify instanceof HTMLInputElement) {
                campaignSettingsStreamSpotify.value = streamingUrlForLabel(epk.streaming_links, STREAMING_PRESET_LABELS.spotify);
            }
            if (campaignSettingsStreamApple instanceof HTMLInputElement) {
                campaignSettingsStreamApple.value = streamingUrlForLabel(epk.streaming_links, STREAMING_PRESET_LABELS.apple);
            }
            if (campaignSettingsPressPhotos instanceof HTMLTextAreaElement) {
                campaignSettingsPressPhotos.value = epk.press_photo_asset_ids.join(', ');
            }

            campaignSettingsBaseline = readCampaignSettingsFromForm();
            campaignSettingsBaseline.locked = locked;

            setCampaignMetadataDisabled(metadataLocked);
            updateCampaignCoverPanel();
            if (campaignSettingsStatus) {
                campaignSettingsStatus.textContent = '';
            }
        }

        function updateCampaignEditorHint() {
            const entry = campaignEntry(selectedCampaignId);
            if (!editorHint) {
                return;
            }
            if (!isEditing) {
                editorHint.textContent = 'Select a campaign from the pool to preview it. Click edit to manage tracks and press kit.';
                return;
            }
            if (entry?.locked) {
                editorHint.textContent = campaignIsPlatformDemo(entry) && !campaignMayChangeLock(entry)
                    ? 'bandPromo demo is locked. Duplicate it, or unlock on localhost to edit the PCF source.'
                    : 'This campaign is locked. Membership is preview-only until you unlock it from the campaign list.';
                return;
            }
            editorHint.textContent = 'Use the section tabs to manage tracks and associated playlists, galleries, and pages. Pages associated here appear as optional player tabs (in list order) when this campaign’s playlist is playing. Changes save as you edit.';
        }

        async function saveCampaignSettings({ silent = false } = {}) {
            if (campaignSettingsSaving) {
                campaignSettingsSaveQueued = true;
                return true;
            }
            if (!(campaignSettingsTitle instanceof HTMLInputElement)
                || !(campaignSettingsDate instanceof HTMLInputElement)) {
                return true;
            }

            const entry = campaignEntry(selectedCampaignId);
            if (!entry || entry.locked) {
                return true;
            }

            const settings = readCampaignSettingsFromForm();
            const { title, release_date: campaignDate } = settings;

            if (!title) {
                if (!silent) {
                    showCampaignToast('Campaign name is required.', 'error');
                }
                return false;
            }

            const dateError = validateCampaignDate(campaignDate);
            if (dateError) {
                if (!silent) {
                    showCampaignToast(dateError, 'error');
                }
                return false;
            }

            let pressContact = String(settings.epk?.press_contact || '').trim();
            if (pressContact !== '' && typeof window.bandpromoSiteContactNormalize === 'function') {
                const normalized = window.bandpromoSiteContactNormalize(pressContact);
                if (normalized) {
                    pressContact = normalized;
                    settings.epk.press_contact = normalized;
                    if (campaignSettingsPressContact instanceof HTMLInputElement) {
                        campaignSettingsPressContact.value = normalized;
                    }
                }
            }
            if (pressContact !== '' && typeof window.bandpromoSiteContactIsValid === 'function'
                && !window.bandpromoSiteContactIsValid(pressContact)) {
                const message = window.bandpromoSiteContactInvalidMessage?.()
                    || 'Press contact must be a valid RFC 5322 address.';
                if (!silent) {
                    showCampaignToast(message, 'error');
                }
                return false;
            }

            if (!campaignSettingsDirty()) {
                return true;
            }

            campaignSettingsSaving = true;
            campaignSettingsSaveQueued = false;

            try {
                const data = await fetchJson(`/biblioteca/manage-campaign.php?campaign=${encodeURIComponent(selectedCampaignId)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settings),
                });
                campaigns = sortCampaignEntries(Array.isArray(data.campaigns) ? data.campaigns : campaigns);
                const savedPoster = String(data.release?.poster_asset_id || settings.poster_asset_id || '').trim();
                const savedPreview = String(data.release?.poster_preview_url || '').trim();
                if (savedPreview) {
                    pendingCampaignCoverPreviewUrl = savedPreview;
                } else if (savedPoster) {
                    pendingCampaignCoverPreviewUrl = mediaPreviewUrlFromReference(savedPoster);
                }
                syncCampaignSettingsPanel(selectedCampaignId);
                renderCampaignPoolList();
                updateCampaignEditorHint();
                renderLists();
                return true;
            } catch (error) {
                if (!silent) {
                    showCampaignToast(error.message || 'Could not save campaign settings', 'error');
                }
                return false;
            } finally {
                campaignSettingsSaving = false;
                if (campaignSettingsSaveQueued) {
                    campaignSettingsSaveQueued = false;
                    saveCampaignSettings({ silent: true }).catch(() => {});
                }
            }
        }

        function closeCampaignDeleteModal() {
            pendingCampaignDeleteId = '';
            if (campaignDeleteModal) {
                campaignDeleteModal.style.display = 'none';
                campaignDeleteModal.setAttribute('aria-hidden', 'true');
            }
        }

        function selectedCampaignDeleteMode() {
            if (campaignDeleteModeContainer && campaignDeleteModeContainer.checked) {
                return 'container';
            }
            return 'purge';
        }

        function syncCampaignDeleteConfirmLabel() {
            if (!(campaignDeleteConfirmBtn instanceof HTMLButtonElement)) {
                return;
            }
            campaignDeleteConfirmBtn.textContent = selectedCampaignDeleteMode() === 'container'
                ? 'Delete campaign only'
                : 'Delete entire campaign';
        }

        function openCampaignDeleteModal(campaignId) {
            const entry = campaignEntry(campaignId);
            if (!entry || !campaignCanDelete(entry)) {
                return;
            }
            const title = String(entry.title || campaignId);
            if (!campaignDeleteModal) {
                if (!window.confirm(`Delete entire campaign "${title}"?\n\nRemoves owned brand, playlists, galleries, pages, and unused media. Shared media stays. This cannot be undone.`)) {
                    return;
                }
                deleteCampaign(campaignId, 'purge').catch((error) => showCampaignToast(error.message || 'Could not delete campaign'));
                return;
            }
            pendingCampaignDeleteId = campaignId;
            if (campaignDeleteModalName) {
                campaignDeleteModalName.textContent = title;
            }
            if (campaignDeleteModePurge) {
                campaignDeleteModePurge.checked = true;
            }
            if (campaignDeleteModeContainer) {
                campaignDeleteModeContainer.checked = false;
            }
            syncCampaignDeleteConfirmLabel();
            campaignDeleteModal.style.display = 'flex';
            campaignDeleteModal.setAttribute('aria-hidden', 'false');
            campaignDeleteConfirmBtn?.focus();
        }

        function showPoolView() {
            lifecycle.showPoolView();
        }

        function showEditView(campaignId) {
            lifecycle.showEditView(campaignId);
        }

        function renderCampaignPoolList() {
            if (!poolList) {
                return;
            }
            const registry = window.bandpromoRegistryList;
            if (!registry?.render || !registry?.row || !registry?.actionButton) {
                poolList.innerHTML = '<li class="player-layout-empty">Campaign list unavailable.</li>';
                return;
            }

            registry.render(poolList, {
                entries: campaigns,
                selectedId: selectedCampaignId,
                dataAttribute: 'campaign-id',
                emptyMessage: 'No campaigns available yet.',
                renderRow: function (entry, isSelected) {
                    const id = String(entry.id || '');
                    const title = String(entry.title || id);
                    const escapedId = registry.escapeHtml(id);
                    const actions = [];

                    if (campaignMayChangeLock(entry)) {
                        actions.push(
                            `<button type="button" class="icon-btn icon-btn--pool page-pool-lock-btn registry-btn--lock${entry.locked ? ' page-pool-lock-btn--active registry-btn--lock-active icon-btn--active' : ''}" data-campaign-id="${escapedId}" title="${entry.locked ? 'Unlock campaign (allow track edits)' : 'Lock campaign (freeze track membership)'}" aria-label="${entry.locked ? 'Unlock' : 'Lock'} ${escapeHtml(title)}" aria-pressed="${entry.locked ? 'true' : 'false'}">${entry.locked ? '🔒' : '🔓'}</button>`
                        );
                    } else if (entry.locked) {
                        actions.push('<span class="page-pool-lock-badge" title="Locked platform demo">🔒</span>');
                    }

                    if (id && id !== 'primary') {
                        actions.push(
                            registry.actionButton({
                                icon: '📦',
                                title: 'Export campaign file (.pcf)',
                                className: 'page-pool-export-btn',
                                dataAttribute: `data-campaign-id="${escapedId}"`,
                            })
                        );
                        actions.push(
                            registry.actionButton({
                                icon: '⧉',
                                title: 'Duplicate campaign (shared media)',
                                className: 'page-pool-duplicate-btn',
                                dataAttribute: `data-campaign-id="${escapedId}"`,
                            })
                        );
                    }
                    if (campaignCanOpenEditor(entry)) {
                        actions.push(
                            registry.actionButton({
                                icon: '✏️',
                                title: 'Edit campaign',
                                className: 'page-pool-edit-btn',
                                dataAttribute: `data-campaign-id="${escapedId}"`,
                            })
                        );
                    }
                    if (campaignCanDelete(entry)) {
                        actions.push(
                            registry.actionButton({
                                icon: '🗑️',
                                title: 'Delete campaign',
                                className: 'page-pool-delete-btn icon-btn--danger',
                                dataAttribute: `data-campaign-id="${escapedId}"`,
                            })
                        );
                    }

                    return registry.row({
                        id: id,
                        dataAttribute: 'data-campaign-id',
                        isSelected: isSelected,
                        icon: '💿',
                        title: title,
                        meta: campaignPoolMetaHtml(entry),
                        extraClasses: 'campaign-pool-row',
                        actions: actions,
                    });
                },
            });
        }

        function campaignPatchPayload(entry, locked) {
            const epk = normalizeCampaignEpk(entry?.epk);
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

        async function toggleCampaignLock(campaignId, locked) {
            const entry = campaignEntry(campaignId);
            if (!entry || !campaignMayChangeLock(entry)) {
                return false;
            }

            if (locked && isEditing && campaignId === selectedCampaignId) {
                await flushMembershipSaves();
            }

            try {
                const data = await fetchJson(`/biblioteca/manage-campaign.php?campaign=${encodeURIComponent(campaignId)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(campaignPatchPayload(entry, locked)),
                });
                campaigns = sortCampaignEntries(Array.isArray(data.campaigns) ? data.campaigns : campaigns);
                renderCampaignPoolList();
                if (campaignId === selectedCampaignId) {
                    syncCampaignSettingsPanel(campaignId);
                    updateCampaignEditorHint();
                    renderLists();
                }
                return true;
            } catch (error) {
                showCampaignToast(error.message || 'Could not update campaign lock');
                return false;
            }
        }

        async function loadBrandCatalog() {
            try {
                const data = await fetchJson('/biblioteca/get-brands.php');
                campaignBrandCatalog = Array.isArray(data.brands)
                    ? data.brands
                    : (Array.isArray(data.themes) ? data.themes : []);
                populateCampaignBrandSelect();
            } catch (error) {
                // Brand picker is optional until data/brands is seeded.
                campaignBrandCatalog = [];
            }
        }

        async function loadCampaignRegistry() {
            await loadBrandCatalog();
            let data;
            if (typeof window.loadReleasesCatalog === 'function') {
                const list = await window.loadReleasesCatalog();
                if (Array.isArray(list) && list.length) {
                    data = { campaigns: list };
                } else if (Array.isArray(window.bandpromoReleasesCatalog) && window.bandpromoReleasesCatalog.length) {
                    data = { campaigns: window.bandpromoReleasesCatalog };
                }
            } else if (Array.isArray(window.bandpromoReleasesCatalog) && window.bandpromoReleasesCatalog.length) {
                data = {
                    campaigns: window.bandpromoReleasesCatalog,
                };
            }
            if (!data) {
                data = await fetchJson('/biblioteca/get-campaigns.php');
            }
            campaigns = sortCampaignEntries(Array.isArray(data.campaigns) ? data.campaigns : []);
            if (!campaignEntry(selectedCampaignId)) {
                selectedCampaignId = campaigns[0]?.id || '';
            }
            renderCampaignPoolList();
        }

        async function requestCloseEditor() {
            return lifecycle.requestClose();
        }

        async function openCampaignEditor(campaignId) {
            if (!campaignId) {
                showCampaignToast('Missing campaign id.', 'error');
                return;
            }
            const entry = campaignEntry(campaignId);
            if (!entry) {
                showCampaignToast(`Could not open campaign “${campaignId}” — it is not in the catalogue yet. Refresh and try again.`, 'error');
                return;
            }
            if (isEditing && campaignId !== selectedCampaignId) {
                if (campaignSettingsDirty()) {
                    const saved = await saveCampaignSettings();
                    if (!saved) {
                        return;
                    }
                }
                await flushMembershipSaves();
            }
            if (!campaignCanOpenEditor(entry)) {
                selectedCampaignId = campaignId;
                showPoolView();
                syncCampaignUrl(campaignId, false);
                await loadCampaignPreview();
                return;
            }
            selectedCampaignId = campaignId;
            trackEditorLoadedCampaignId = '';
            resetAssociationPools();
            showEditView(campaignId);
        }

        async function selectCampaignForPreview(campaignId) {
            if (!campaignId || (campaignId === selectedCampaignId && !isEditing)) {
                return;
            }
            if (isEditing) {
                await openCampaignEditor(campaignId);
                return;
            }
            selectedCampaignId = campaignId;
            syncCampaignUrl(campaignId, false);
            renderCampaignPoolList();
            await loadCampaignPreview();
        }

        async function exportCampaignPackage(campaignId) {
            const entry = campaignEntry(campaignId);
            if (!entry || campaignId === 'primary') {
                return;
            }
            const sourceTitle = String(entry.title || campaignId).trim() || campaignId;
            showCampaignToast(`Queueing PCF export for "${sourceTitle}"…`);
            const csrfToken = typeof refreshAdminCsrfToken === 'function'
                ? await refreshAdminCsrfToken()
                : (typeof adminCsrfToken === 'string' ? adminCsrfToken : '');
            const data = await fetchJson('/biblioteca/export-campaign-package.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    release_id: campaignId,
                    csrf_token: csrfToken,
                }),
            });
            const message = data.message || 'PCF export queued.';
            showCampaignToast(message);
            if (window.confirm(`${message}\n\nOpen System → Backup, export & import to watch progress / download?`)) {
                window.location.href = String(data.jobs_url || '?tab=system&stab=backup');
            }
        }

        async function duplicateCampaignCampaign(campaignId) {
            const entry = campaignEntry(campaignId);
            if (!entry || campaignId === 'primary') {
                return;
            }
            const sourceTitle = String(entry.title || campaignId).trim() || campaignId;
            if (!window.confirm(`Duplicate "${sourceTitle}" as a new campaign?\n\nNew containers; shared media files.`)) {
                return;
            }
            const data = await fetchJson('/biblioteca/duplicate-campaign.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ release_id: campaignId }),
            });
            campaigns = sortCampaignEntries(Array.isArray(data.campaigns) ? data.campaigns : campaigns);
            renderCampaignPoolList();
            const newId = String(data.release_id || '').trim();
            showCampaignToast(data.message || 'Campaign duplicated.');
            if (newId) {
                await openCampaignEditor(newId);
            }
        }

        async function deleteCampaign(campaignId, mode = 'purge') {
            const entry = campaignEntry(campaignId);
            if (!entry || !campaignCanDelete(entry)) {
                return;
            }
            const deleteMode = mode === 'container' ? 'container' : 'purge';
            const data = await fetchJson(
                `/biblioteca/manage-campaign.php?campaign=${encodeURIComponent(campaignId)}&mode=${encodeURIComponent(deleteMode)}`,
                { method: 'DELETE' }
            );
            campaigns = Array.isArray(data.campaigns) ? data.campaigns : [];
            const purge = data.purge && typeof data.purge === 'object' ? data.purge : null;
            if (deleteMode === 'purge' && purge) {
                const assetCount = Array.isArray(purge.deleted_assets) ? purge.deleted_assets.length : 0;
                const kept = Array.isArray(purge.retained_shared_assets) ? purge.retained_shared_assets.length : 0;
                let detail = `Campaign deleted (${assetCount} media removed`;
                if (kept > 0) {
                    detail += `, ${kept} shared kept`;
                }
                detail += ').';
                showCampaignToast(detail);
            } else {
                showCampaignToast('Campaign removed. Media stayed in Files.');
            }
            if (selectedCampaignId === campaignId) {
                selectedCampaignId = campaigns[0]?.id || 'primary';
                showPoolView();
                syncCampaignUrl(selectedCampaignId, false);
                await loadCampaignPreview();
            } else {
                renderCampaignPoolList();
            }
        }

        function pruneAvailableSelection() {
            const allowed = new Set(availableTracks.map((track) => String(track.file || '')));
            rangeSelection.getSelected('available').forEach((file) => {
                if (!allowed.has(file)) {
                    rangeSelection.getSelected('available').delete(file);
                }
            });
            const anchor = rangeSelection.getAnchor('available');
            if (anchor && !allowed.has(anchor)) {
                rangeSelection.setAnchor('available', '');
            }
        }

        function pruneActiveSelection() {
            const allowed = new Set(activeTracks.map((track) => String(track.file || '')));
            rangeSelection.getSelected('active').forEach((file) => {
                if (!allowed.has(file)) {
                    rangeSelection.getSelected('active').delete(file);
                }
            });
            const anchor = rangeSelection.getAnchor('active');
            if (anchor && !allowed.has(anchor)) {
                rangeSelection.setAnchor('active', '');
            }
        }

        function getAvailableRows() {
            return Array.from(availableEl.querySelectorAll('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]'));
        }

        function getActiveRows() {
            return Array.from(activeEl.querySelectorAll('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]'));
        }

        function syncAvailableSelectionUi() {
            getAvailableRows().forEach((row) => {
                const file = String(row.dataset.file || '');
                const selected = rangeSelection.getSelected('available').has(file);
                row.classList.toggle('playlist-editor-row-selected', selected);
                row.classList.toggle('editor-row--selected', selected);
                row.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }

        function syncActiveSelectionUi() {
            getActiveRows().forEach((row) => {
                const file = String(row.dataset.file || '');
                const selected = rangeSelection.getSelected('active').has(file);
                row.classList.toggle('playlist-editor-row-selected', selected);
                row.classList.toggle('editor-row--selected', selected);
                row.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }

        function handleAvailableSelection(row, event) {
            const file = String(row.dataset.file || '').trim();
            if (!file) {
                return;
            }
            rangeSelection.setSelected('active', new Set());
            rangeSelection.setAnchor('active', '');
            syncActiveSelectionUi();
            rangeSelection.handleSelection('available', row, event);
        }

        function handleActiveSelection(row, event) {
            const file = String(row.dataset.file || '').trim();
            if (!file) {
                return;
            }
            rangeSelection.setSelected('available', new Set());
            rangeSelection.setAnchor('available', '');
            syncAvailableSelectionUi();
            rangeSelection.handleSelection('active', row, event);
        }

        function renderTrackRow(track, options) {
            const entry = campaignEntry(selectedCampaignId);
            const canEditTracks = campaignTrackEditingEnabled(entry);
            const title = escapeHtml(displayTrackTitle(track));
            const meta = escapeHtml(trackMeta(track));
            const duration = track.deliveryReady === false ? '' : formatCampaignDuration(track.duration);
            const file = escapeHtml(track.file || '');
            const selectedClass = options.selected ? ' playlist-editor-row-selected editor-row--selected' : '';
            const pendingClass = track.deliveryReady === false ? ' playlist-editor-row-pending editor-row--pending' : '';
            const demoClass = track.origin === 'bundled-placeholder' ? ' playlist-editor-row-demo editor-row--demo' : '';
            const positionMarkup = options.showPosition
                ? `<span class="playlist-track-num">${options.position}</span>`
                : '';
            const removeMarkup = options.showRemove
                ? '<button type="button" class="player-layout-remove-btn" title="Move to Available tracks" aria-label="Remove from campaign">✕</button>'
                : '';
            const rowClass = options.activeRow ? 'playlist-editor-row editor-row player-layout-row-active' : 'playlist-editor-row editor-row';
            const readonlyClass = !canEditTracks ? ' playlist-editor-row-readonly editor-row--readonly' : '';
            const draggable = canEditTracks && track.deliveryReady !== false ? 'true' : 'false';
            const dragTitle = !canEditTracks
                ? (entry?.locked ? 'Locked campaign — unlock to edit' : 'Preview only')
                : (track.deliveryReady === false
                    ? 'Delivery file not ready yet'
                    : (options.activeRow ? 'Drag to reorder' : 'Drag into campaign'));

            return `<li class="${rowClass}${pendingClass}${demoClass}${selectedClass}${readonlyClass}" draggable="${draggable}" data-file="${file}" aria-selected="${options.selected ? 'true' : 'false'}">
                ${positionMarkup}
                <span class="playlist-drag-handle editor-drag-handle" title="${dragTitle}">⠿</span>
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
            const duration = track.deliveryReady === false ? '' : formatCampaignDuration(track.duration);
            const file = escapeHtml(track.file || '');
            const pendingClass = track.deliveryReady === false ? ' playlist-editor-row-pending editor-row--pending' : '';
            const removeMarkup = canEditTracks
                ? '<button type="button" class="player-layout-remove-btn" title="Remove from campaign" aria-label="Remove from campaign">✕</button>'
                : '';

            return `<li class="playlist-editor-row editor-row campaign-associated-track-row${pendingClass}" draggable="false" data-file="${file}">
                <span class="campaign-preview-track-copy">
                    <span class="campaign-preview-track-artist">${artist}</span>
                    <strong class="campaign-preview-track-title">${title}</strong>
                </span>
                <span class="playlist-track-duration">${duration}</span>
                ${removeMarkup}
            </li>`;
        }

        function renderLists() {
            pruneAvailableSelection();
            pruneActiveSelection();

            const entry = campaignEntry(selectedCampaignId);
            const canEditTracks = campaignTrackEditingEnabled(entry);

            if (!selectedCampaignId) {
                activeEl.innerHTML = '<li class="player-layout-empty">No campaign selected.</li>';
                return;
            }

            if (!activeTracks.length) {
                activeEl.innerHTML = canEditTracks
                    ? '<li class="player-layout-empty">Drag tracks here from Available tracks.</li>'
                    : '<li class="player-layout-empty">This campaign has no tracks yet.</li>';
            } else {
                activeTracks = sortAssociatedTracks(activeTracks);
                activeEl.innerHTML = activeTracks
                    .map((track) => renderAssociatedTrackRow(track, canEditTracks))
                    .join('');
            }

            if (activeEl) {
                activeEl.hidden = !isEditing || campaignEditorTab !== 'tracks';
            }

            if (!isEditing) {
                updateCampaignCoverPanel();
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
                    selected: rangeSelection.getSelected('available').has(String(track.file || '')),
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
                    clone.release_id = selectedCampaignId;
                } else {
                    clone.release_id = String(clone.originCampaignId || clone.release_id || '');
                }
                return clone;
            });
            target.splice(safeIndex, 0, ...movedClones);

            if (fromList === 'active') {
                files.forEach((file) => rangeSelection.getSelected('active').delete(file));
                const anchor = rangeSelection.getAnchor('active');
                if (anchor && fileSet.has(anchor)) {
                    rangeSelection.setAnchor('active', '');
                }
            } else {
                files.forEach((file) => rangeSelection.getSelected('available').delete(file));
                const anchor = rangeSelection.getAnchor('available');
                if (anchor && fileSet.has(anchor)) {
                    rangeSelection.setAnchor('available', '');
                }
            }

            activeTracks = sortAssociatedTracks(activeTracks);
            renderLists();
            void persistCampaignTracks();
            return true;
        }

        function ensurePlaceholder() {
            if (!dragPlaceholder) {
                dragPlaceholder = document.createElement('li');
                dragPlaceholder.className = 'playlist-editor-placeholder editor-placeholder';
            }
            return dragPlaceholder;
        }

        function getDraggableRows(listEl) {
            if (!listEl) {
                return [];
            }
            return Array.from(listEl.querySelectorAll('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]'));
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
                if (!(child.classList.contains('playlist-editor-row') || child.classList.contains('editor-row'))) {
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
                if (!(child.classList.contains('playlist-editor-row') || child.classList.contains('editor-row'))) {
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
                return getAvailableRows().filter((row) => rangeSelection.getSelected('available').has(String(row.dataset.file || '')));
            }
            if (listName === 'active') {
                return getActiveRows().filter((row) => rangeSelection.getSelected('active').has(String(row.dataset.file || '')));
            }
            return [];
        }

        function bindDragList(listEl) {
            listEl.addEventListener('dragstart', (event) => {
                const row = event.target instanceof HTMLElement
                    ? event.target.closest('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]')
                    : null;
                if (!row || !listEl.contains(row)) {
                    return;
                }
                dragSourceRow = row;
                dragSourceList = listNameForElement(listEl);
                const sourceFile = String(row.dataset.file || '').trim();

                if (dragSourceList === 'available') {
                    if (sourceFile && !rangeSelection.getSelected('available').has(sourceFile)) {
                        rangeSelection.setSelected('available', new Set([sourceFile]));
                        rangeSelection.setAnchor('available', sourceFile);
                        syncAvailableSelectionUi();
                    }
                    draggedRows = collectDraggedRows(listEl);
                } else if (dragSourceList === 'active') {
                    if (sourceFile && !rangeSelection.getSelected('active').has(sourceFile)) {
                        rangeSelection.setSelected('active', new Set([sourceFile]));
                        rangeSelection.setAnchor('active', sourceFile);
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
            const entry = campaignEntry(selectedCampaignId);
            if (entry) {
                if (typeof data.locked === 'boolean') {
                    entry.locked = data.locked;
                }
            }

            activeTracks = Array.isArray(data.activeTracks) ? data.activeTracks.map(cloneTrack) : [];
            availableTracks = Array.isArray(data.availableTracks) ? data.availableTracks.map(cloneTrack) : [];
            trackEditorLoadedCampaignId = selectedCampaignId;

            renderCampaignPoolList();
            syncCampaignSettingsPanel(selectedCampaignId);
            updateCampaignEditorHint();
            renderLists();
            updateCampaignCoverPanel();
        }

        function applyRegistryPreview() {
            const entry = campaignEntry(selectedCampaignId);
            activeTracks = Array.isArray(entry?.preview_tracks)
                ? entry.preview_tracks.map(cloneTrack)
                : [];
            availableTracks = [];

            renderCampaignPoolList();
            renderLists();
        }

        async function loadCampaignPreview() {
            if (!isEditing) {
                applyRegistryPreview();
                return;
            }

            try {
                const data = await fetchJson(`/biblioteca/get-campaign-preview.php?campaign=${encodeURIComponent(selectedCampaignId)}`);
                applyPreviewData(data);
            } catch (error) {
                activeEl.innerHTML = '';
                availableEl.innerHTML = `<li class="player-layout-empty" style="color:#f87171">Could not load campaign preview: ${escapeHtml(error.message)}</li>`;
            }
        }

        campaignDeleteCancelBtn?.addEventListener('click', closeCampaignDeleteModal);
        campaignDeleteModePurge?.addEventListener('change', syncCampaignDeleteConfirmLabel);
        campaignDeleteModeContainer?.addEventListener('change', syncCampaignDeleteConfirmLabel);
        campaignDeleteModal?.addEventListener('click', (event) => {
            if (event.target === campaignDeleteModal) {
                closeCampaignDeleteModal();
            }
        });
        campaignDeleteConfirmBtn?.addEventListener('click', async () => {
            const campaignId = pendingCampaignDeleteId;
            if (!campaignId) {
                return;
            }
            const mode = selectedCampaignDeleteMode();
            closeCampaignDeleteModal();
            try {
                campaignDeleteConfirmBtn.disabled = true;
                await deleteCampaign(campaignId, mode);
            } catch (error) {
                showCampaignToast(error.message || 'Could not delete campaign');
            } finally {
                campaignDeleteConfirmBtn.disabled = false;
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !campaignDeleteModal || campaignDeleteModal.style.display !== 'flex') {
                return;
            }
            closeCampaignDeleteModal();
        });

        poolList.addEventListener('click', (event) => {
            const lockBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-lock-btn, .registry-btn--lock')
                : null;
            if (lockBtn) {
                event.preventDefault();
                event.stopPropagation();
                const campaignId = lockBtn.getAttribute('data-campaign-id') || '';
                const entry = campaignEntry(campaignId);
                if (!entry) {
                    return;
                }
                toggleCampaignLock(campaignId, !entry.locked);
                return;
            }

            const deleteBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-delete-btn, .registry-btn--delete')
                : null;
            if (deleteBtn) {
                event.preventDefault();
                event.stopPropagation();
                const campaignId = deleteBtn.getAttribute('data-campaign-id') || '';
                openCampaignDeleteModal(campaignId);
                return;
            }

            const duplicateBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-duplicate-btn, .registry-btn--duplicate')
                : null;
            if (duplicateBtn) {
                event.preventDefault();
                event.stopPropagation();
                const campaignId = duplicateBtn.getAttribute('data-campaign-id') || '';
                duplicateCampaignCampaign(campaignId).catch((error) => {
                    showCampaignToast(error.message || 'Could not duplicate campaign', 'error');
                });
                return;
            }

            const exportBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-export-btn')
                : null;
            if (exportBtn) {
                event.preventDefault();
                event.stopPropagation();
                const campaignId = exportBtn.getAttribute('data-campaign-id') || '';
                exportCampaignPackage(campaignId).catch((error) => {
                    showCampaignToast(error.message || 'Could not export package', 'error');
                });
                return;
            }

            const editBtn = event.target instanceof HTMLElement
                ? event.target.closest('.page-pool-edit-btn, .registry-btn--edit')
                : null;
            if (editBtn) {
                event.preventDefault();
                event.stopPropagation();
                const campaignId = editBtn.getAttribute('data-campaign-id') || '';
                openCampaignEditor(campaignId);
                return;
            }

            const row = event.target instanceof HTMLElement
                ? event.target.closest('.campaign-pool-row')
                : null;
            if (!row || !poolList.contains(row)) {
                return;
            }
            const campaignId = row.getAttribute('data-campaign-id') || '';
            if (!campaignId) {
                return;
            }
            selectCampaignForPreview(campaignId);
        });

        backBtn?.addEventListener('click', () => {
            requestCloseEditor();
        });

        campaignSettingsTitle?.addEventListener('blur', () => {
            saveCampaignSettings();
        });
        campaignSettingsTitle?.addEventListener('input', () => {
            updateCampaignBasePreviewFromForm();
        });
        campaignSettingsDate?.addEventListener('blur', () => {
            saveCampaignSettings();
        });
        campaignSettingsDate?.addEventListener('input', () => {
            updateCampaignBasePreviewFromForm();
        });
        campaignSettingsDate?.addEventListener('change', () => {
            saveCampaignSettings();
        });
        campaignSettingsCatalogId?.addEventListener('blur', () => {
            saveCampaignSettings();
        });
        campaignSettingsBrandId?.addEventListener('change', () => {
            refreshCampaignBaseBrandPreview();
            saveCampaignSettings();
        });
        campaignSettingsPosterAssetId?.addEventListener('input', () => {
            const raw = String(campaignSettingsPosterAssetId.value || '').trim();
            if (!raw) {
                pendingCampaignCoverPreviewUrl = '';
            } else if (raw.startsWith('/media/') || /^https?:\/\//i.test(raw)) {
                pendingCampaignCoverPreviewUrl = mediaPreviewUrlFromReference(raw);
            }
            updateCampaignCoverPreview();
            saveCampaignSettings();
        });
        campaignSettingsShortDescription?.addEventListener('input', () => {
            updateCampaignShortDescriptionCount();
            updateCampaignBasePreviewFromForm();
        });
        campaignSettingsDescription?.addEventListener('input', () => {
            autofitCampaignDescriptionField();
            updateCampaignBasePreviewFromForm();
        });
        [
            campaignSettingsShortDescription,
            campaignSettingsDescription,
            campaignSettingsCredits,
            campaignSettingsPressContact,
            campaignSettingsStreamBandpromo,
            campaignSettingsStreamSpotify,
            campaignSettingsStreamApple,
            campaignSettingsPressPhotos,
        ].forEach((control) => {
            control?.addEventListener('blur', () => {
                saveCampaignSettings();
            });
        });
        campaignSettingsTitle?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                campaignSettingsTitle.blur();
            }
        });
        campaignSettingsDate?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                campaignSettingsDate.blur();
            }
        });

        toggleAddCampaignBtn?.addEventListener('click', () => {
            setAddCampaignPanelOpen(addCampaignPanel?.hidden !== false);
        });

        cancelAddCampaignBtn?.addEventListener('click', () => {
            addCampaignForm?.reset();
            setAddCampaignPanelOpen(false);
        });

        addCampaignForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(addCampaignForm);
            const title = String(formData.get('title') || '').trim();
            if (!title) {
                if (campaignRegistryStatus) {
                    campaignRegistryStatus.textContent = 'Campaign name is required.';
                    campaignRegistryStatus.style.color = '#f87171';
                }
                return;
            }
            try {
                if (campaignRegistryStatus) {
                    campaignRegistryStatus.textContent = 'Creating campaign…';
                    campaignRegistryStatus.style.color = '';
                }
                const data = await fetchJson('/biblioteca/manage-campaign.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ title }),
                });
                const created = (data.release && typeof data.release === 'object') ? data.release : null;
                const newId = String(created?.id || '').trim();
                if (Array.isArray(data.campaigns) && data.campaigns.length) {
                    campaigns = sortCampaignEntries(data.campaigns);
                } else if (created && newId) {
                    const without = campaigns.filter((entry) => String(entry?.id || '') !== newId);
                    campaigns = sortCampaignEntries([created, ...without]);
                }
                if (typeof window.loadReleasesCatalog === 'function') {
                    try {
                        const catalog = await window.loadReleasesCatalog({ force: true });
                        if (Array.isArray(catalog) && catalog.length) {
                            campaigns = sortCampaignEntries(catalog);
                        }
                    } catch (_error) {
                        // Local list from create response is enough to open the editor.
                    }
                }
                if (created && newId && !campaignEntry(newId)) {
                    campaigns = sortCampaignEntries([created, ...campaigns.filter((entry) => String(entry?.id || '') !== newId)]);
                }
                addCampaignForm.reset();
                setAddCampaignPanelOpen(false);
                if (!newId || !campaignEntry(newId)) {
                    showCampaignToast('Campaign was created but could not be opened. Refresh the catalogue and select it from the pool.', 'error');
                    renderCampaignPoolList();
                    return;
                }
                await openCampaignEditor(newId);
                if (campaignRegistryStatus) {
                    campaignRegistryStatus.textContent = `Created “${String(created?.title || newId)}”.`;
                    campaignRegistryStatus.style.color = '';
                }
            } catch (error) {
                if (campaignRegistryStatus) {
                    campaignRegistryStatus.textContent = '❌ ' + (error.message || 'Could not create campaign');
                    campaignRegistryStatus.style.color = '#f87171';
                }
            }
        });

        availableEl.addEventListener('click', (event) => {
            if (suppressNextClick) {
                return;
            }
            const row = event.target instanceof HTMLElement
                ? event.target.closest('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]')
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
                const row = button.closest('.playlist-editor-row, .editor-row');
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
                ? event.target.closest('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]')
                : null;
            if (!row || !activeEl.contains(row)) {
                return;
            }
            handleActiveSelection(row, event);
        });

        bindDragList(activeEl);
        bindDragList(availableEl);
        bindAssociationDragList(campaignAssociationAvailableList, 'available');
        bindAssociationDragList(campaignAssociationActiveList, 'active');

        if (campaignAssociationAvailableList) {
            campaignAssociationAvailableList.addEventListener('click', (event) => {
                if (suppressNextClick) {
                    return;
                }
                const kind = currentAssociationKind();
                if (!kind || !associationEditingEnabled()) {
                    return;
                }
                const row = event.target instanceof HTMLElement
                    ? event.target.closest('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]')
                    : null;
                if (!row || !campaignAssociationAvailableList.contains(row)) {
                    return;
                }
                const range = associationRange(kind);
                if (!range) {
                    return;
                }
                range.setSelected('active', new Set());
                range.setAnchor('active', '');
                range.handleSelection('available', row, event);
                syncAssociationSelectionUi(kind);
            });

            campaignAssociationAvailableList.addEventListener('dblclick', (event) => {
                const kind = currentAssociationKind();
                const row = event.target instanceof HTMLElement
                    ? event.target.closest('.playlist-editor-row[draggable="true"], .editor-row[draggable="true"]')
                    : null;
                const id = String(row?.dataset.id || '').trim();
                if (!kind || !id) {
                    return;
                }
                const range = associationRange(kind);
                const selectedIds = range
                    ? getAssociationRows(kind, 'available')
                        .map((candidate) => String(candidate.dataset.id || '').trim())
                        .filter((candidateId) => range.getSelected('available').has(candidateId))
                    : [];
                moveAssociationItems(kind, 'available', 'active', selectedIds.length ? selectedIds : [id]);
            });
        }

        if (campaignAssociationActiveList) {
            campaignAssociationActiveList.addEventListener('click', (event) => {
                const button = event.target instanceof HTMLElement
                    ? event.target.closest('.player-layout-remove-btn')
                    : null;
                if (!button || !campaignAssociationActiveList.contains(button)) {
                    return;
                }
                const kind = currentAssociationKind();
                const row = button.closest('.playlist-editor-row, .editor-row');
                const id = String(row?.dataset.id || '').trim();
                if (!kind || !id) {
                    return;
                }
                moveAssociationItems(kind, 'active', 'available', [id]);
            });
        }

        initCampaignCoverPicker();

        document.querySelectorAll('[data-campaign-editor-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                setCampaignEditorTab(String(button.getAttribute('data-campaign-editor-tab') || 'base'));
            });
        });
        setCampaignEditorTab(campaignEditorTab);

        const startInEdit = initialUrlParams.get('edit') === '1';

        loadSiteSharingContext()
            .then(() => loadCampaignRegistry())
            .catch((error) => {
                poolList.innerHTML = `<li class="player-layout-empty" style="color:#f87171">${escapeHtml(error.message)}</li>`;
            })
            .finally(async () => {
                if (startInEdit) {
                    await openCampaignEditor(selectedCampaignId);
                } else {
                    showPoolView();
                    syncCampaignUrl(selectedCampaignId, false);
                    await loadCampaignPreview();
                }
            });
    }

    window.initBandpromoCampaignEditor = initBandpromoCampaignEditor;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBandpromoCampaignEditor);
    } else {
        initBandpromoCampaignEditor();
    }
})();
