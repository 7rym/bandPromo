(function () {
    function initBandpromoReleaseEditor() {
        const editorCard = document.getElementById('releaseEditorCard');
        const poolView = document.getElementById('releasePoolView');
        const tracksPoolView = document.getElementById('releaseTracksPoolView');
        const poolList = document.getElementById('releasePoolList');
        const availableEl = document.getElementById('releaseAvailableList');
        const activeEl = document.getElementById('releaseActiveList');
        const countBadge = document.getElementById('releaseActiveCount');
        const saveBtn = document.getElementById('releaseSaveBtn');
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
        const releaseDeleteCancelBtn = document.getElementById('releaseDeleteCancelBtn');
        const releaseSettingsTitle = document.getElementById('releaseSettingsTitle');
        const releaseSettingsDate = document.getElementById('releaseSettingsDate');
        const releaseSettingsCatalogId = document.getElementById('releaseSettingsCatalogId');
        const releaseSettingsStatus = document.getElementById('releaseSettingsStatus');
        const releaseSettingsDescription = document.getElementById('releaseSettingsDescription');
        const releaseSettingsShortDescription = document.getElementById('releaseSettingsShortDescription');
        const releaseSettingsShortDescriptionCount = document.getElementById('releaseSettingsShortDescriptionCount');
        const releaseSettingsPosterAssetId = document.getElementById('releaseSettingsPosterAssetId');
        const releaseCoverPanel = document.getElementById('releaseCoverPanel');
        const releaseCoverPreviewShell = document.getElementById('releaseCoverPreviewShell');
        const releaseCoverPreview = document.getElementById('releaseCoverPreview');
        const releaseCoverPlaceholder = document.getElementById('releaseCoverPlaceholder');
        const releaseCoverClearBtn = document.getElementById('releaseCoverClearBtn');
        const releaseCoverUploadBtn = document.getElementById('releaseCoverUploadBtn');
        const releaseSettingsTagline = document.getElementById('releaseSettingsTagline');
        const releaseSettingsGenre = document.getElementById('releaseSettingsGenre');
        const releaseSettingsCredits = document.getElementById('releaseSettingsCredits');
        const releaseSettingsPressContact = document.getElementById('releaseSettingsPressContact');
        const releaseSettingsStreamBandpromo = document.getElementById('releaseSettingsStreamBandpromo');
        const releaseSettingsStreamBandpromoLabel = document.getElementById('releaseSettingsStreamBandpromoLabel');
        const releaseSettingsStreamSpotify = document.getElementById('releaseSettingsStreamSpotify');
        const releaseSettingsStreamApple = document.getElementById('releaseSettingsStreamApple');
        const releaseSettingsSocialImports = document.getElementById('releaseSettingsSocialImports');
        const releaseSettingsPressPhotos = document.getElementById('releaseSettingsPressPhotos');
        const releaseAvailableSection = document.getElementById('releaseAvailableSection');

        if (!editorCard || !poolList || !availableEl || !activeEl || !saveBtn) {
            return;
        }

        const PROTECTED_RELEASE_IDS = new Set(['primary', 'bandpromo-demo']);
        const SYSTEM_MANAGED_RELEASE_IDS = new Set(['bandpromo-demo']);

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
            const toastHost = document.getElementById('adminToastHost');
            const text = String(message || '').replace(/^❌\s*/, '').trim();
            if (!text) {
                return;
            }
            if (!toastHost) {
                window.alert(text);
                return;
            }

            const toast = document.createElement('div');
            toast.className = `admin-toast ${type}`;
            toast.textContent = text;
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
        let isEditing = false;
        let pendingReleaseDeleteId = '';
        let releaseSettingsSaving = false;
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

        const DEFAULT_PLAYER_PLAYLIST_ID = 'main';

        function defaultBandpromoListenUrl() {
            const base = String(siteSharing.siteUrl || '').trim().replace(/\/+$/, '');
            const playlistSegment = encodeURIComponent(DEFAULT_PLAYER_PLAYLIST_ID);
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
                const response = await fetch('/biblioteca/get-config.php', {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
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
            } catch (error) {
                // Keep defaults when config is unavailable.
            }
        }

        function releaseCoverPreviewUrl(value) {
            const raw = String(value || '').trim();
            if (!raw) {
                return '';
            }
            if (/^https?:\/\//i.test(raw) || raw.startsWith('/media/')) {
                return raw;
            }
            return '';
        }

        function updateReleaseCoverPreview() {
            const rawValue = releaseSettingsPosterAssetId instanceof HTMLInputElement
                ? String(releaseSettingsPosterAssetId.value || '').trim()
                : '';
            const previewUrl = releaseCoverPreviewUrl(rawValue);

            if (releaseCoverPreview instanceof HTMLImageElement) {
                if (previewUrl) {
                    releaseCoverPreview.src = previewUrl;
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
                releaseCoverPreviewShell.title = rawValue || 'No cover selected';
            }
            updateReleasePosterLabel();
        }

        function setReleaseCoverValue(value) {
            if (!(releaseSettingsPosterAssetId instanceof HTMLInputElement)) {
                return;
            }
            releaseSettingsPosterAssetId.value = String(value || '').trim();
            releaseSettingsPosterAssetId.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function updateReleaseCoverPanel() {
            const entry = releaseEntry(selectedReleaseId);
            if (releaseCoverPanel) {
                releaseCoverPanel.hidden = !entry;
            }
            if (entry && releaseSettingsPosterAssetId instanceof HTMLInputElement && !isEditing) {
                releaseSettingsPosterAssetId.value = String(entry.poster_asset_id || '').trim();
            }
            const canEditCover = !!(entry && !releaseIsSystemManaged(entry) && !entry.locked);
            const actionButtons = releaseCoverPanel
                ? releaseCoverPanel.querySelectorAll('button')
                : [];
            actionButtons.forEach((button) => {
                if (button instanceof HTMLButtonElement) {
                    button.disabled = !canEditCover;
                }
            });
            updateReleaseCoverPreview();
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
                        button.dataset.title || 'Choose release cover',
                        button.dataset.targets || 'illustrations,photos,special'
                    );
                });
            });

            releaseCoverPanel.querySelectorAll('.media-picker-clear').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    setReleaseCoverValue('');
                });
            });

            releaseCoverClearBtn?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                setReleaseCoverValue('');
            });

            releaseCoverUploadBtn?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (typeof window.openUploadModal !== 'function') {
                    showReleaseToast('Upload is not available. Reload the page.');
                    return;
                }
                window.openUploadModal('illustrations');
            });
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
                return [];
            }
            return String(releaseSettingsPressPhotos.value || '')
                .split(/[,\n]+/)
                .map((assetId) => assetId.trim())
                .filter(Boolean);
        }

        function readStreamingLinksFromForm() {
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
                    : '',
                poster_asset_id: releaseSettingsPosterAssetId instanceof HTMLInputElement
                    ? String(releaseSettingsPosterAssetId.value || '').trim()
                    : '',
                epk: {
                    tagline: releaseSettingsTagline instanceof HTMLInputElement
                        ? String(releaseSettingsTagline.value || '').trim()
                        : '',
                    genre: releaseSettingsGenre instanceof HTMLInputElement
                        ? String(releaseSettingsGenre.value || '').trim()
                        : '',
                    credits: releaseSettingsCredits instanceof HTMLTextAreaElement
                        ? String(releaseSettingsCredits.value || '').trim()
                        : '',
                    press_contact: releaseSettingsPressContact instanceof HTMLInputElement
                        ? String(releaseSettingsPressContact.value || '').trim()
                        : '',
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
                releaseSettingsTagline,
                releaseSettingsGenre,
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

        const saveUi = window.bandpromoContentSaveUi?.create(saveBtn, {
            saveLabel: '💾 Save release',
            readFingerprint() {
                return JSON.stringify(activeTracks.map((track) => String(track.file || '')).filter(Boolean));
            },
        }) || null;

        let activeTracks = [];
        let availableTracks = [];
        let dragSourceRow = null;
        let draggedRows = [];
        let dragSourceList = '';
        let dragPlaceholder = null;
        let selectedAvailable = new Set();
        let selectedActive = new Set();
        let selectionAnchorAvailable = '';
        let selectionAnchorActive = '';
        let suppressNextClick = false;

        function validateReleaseDate(value) {
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
                artist: track.artist,
                album: track.album,
                duration: track.duration,
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

        function releaseIsSystemManaged(entryOrId) {
            const releaseId = typeof entryOrId === 'string'
                ? entryOrId
                : String(entryOrId?.id || '');
            return SYSTEM_MANAGED_RELEASE_IDS.has(releaseId);
        }

        function releaseCanDelete(entry) {
            return !!entry && !releaseIsProtected(entry);
        }

        function releaseCanOpenEditor(entry) {
            return !!entry && !releaseIsSystemManaged(entry);
        }

        function releaseTrackEditingEnabled(entry = releaseEntry(selectedReleaseId)) {
            return !!(isEditing && entry && !releaseIsSystemManaged(entry) && !entry.locked);
        }

        function formatReleaseDuration(seconds) {
            const duration = Math.max(0, Number(seconds) || 0);
            if (!duration) {
                return '';
            }
            return `${Math.floor(duration / 60)}:${String(duration % 60).padStart(2, '0')}`;
        }

        function displayTrackTitle(track) {
            let title = String(track?.title || track?.file || 'Untitled').trim();
            title = title.replace(/^\d+\.\s+/, '').replace(/^\d{1,2}\s+(?=[A-Za-z])/, '');
            return title || 'Untitled';
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

        function releaseMetaLine(entry) {
            if (!entry) {
                return '';
            }
            const parts = [];
            if (releaseIsSystemManaged(entry)) {
                parts.push('demo');
            }
            const catalogId = String(entry.catalog_id || '').trim();
            if (catalogId) {
                parts.push(catalogId);
            }
            if (entry.release_date) {
                parts.push(String(entry.release_date));
            }
            const trackCount = Number(entry.track_count || 0);
            parts.push(trackCount === 1 ? '1 track' : `${trackCount} tracks`);
            return parts.join(' · ');
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
            const description = String(entry?.description || '').trim();
            const shortDescription = String(entry?.short_description || '').trim();
            const catalogId = String(entry?.catalog_id || '').trim();
            const posterAssetId = String(entry?.poster_asset_id || '').trim();
            const epk = normalizeReleaseEpk(entry?.epk);
            const systemManaged = releaseIsSystemManaged(entry);
            const bandpromoListenUrl = streamingUrlForBandpromo(epk.streaming_links)
                || defaultBandpromoListenUrl();

            if (releaseSettingsStreamBandpromoLabel) {
                releaseSettingsStreamBandpromoLabel.textContent = bandpromoSiteLabel();
            }
            renderReleaseSocialImports();

            if (releaseSettingsTitle instanceof HTMLInputElement) {
                releaseSettingsTitle.value = title;
                releaseSettingsTitle.disabled = systemManaged;
            }
            if (releaseSettingsDate instanceof HTMLInputElement) {
                releaseSettingsDate.value = releaseDate;
                releaseSettingsDate.disabled = systemManaged;
            }
            if (releaseSettingsCatalogId instanceof HTMLInputElement) {
                releaseSettingsCatalogId.value = catalogId;
                releaseSettingsCatalogId.disabled = systemManaged;
            }
            if (releaseSettingsShortDescription instanceof HTMLTextAreaElement) {
                releaseSettingsShortDescription.value = shortDescription;
                updateReleaseShortDescriptionCount();
            }
            if (releaseSettingsDescription instanceof HTMLTextAreaElement) {
                releaseSettingsDescription.value = description;
            }
            if (releaseSettingsPosterAssetId instanceof HTMLInputElement) {
                releaseSettingsPosterAssetId.value = posterAssetId;
            }
            if (releaseSettingsTagline instanceof HTMLInputElement) {
                releaseSettingsTagline.value = epk.tagline;
            }
            if (releaseSettingsGenre instanceof HTMLInputElement) {
                releaseSettingsGenre.value = epk.genre;
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

            setReleaseMetadataDisabled(systemManaged);
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
                editorHint.textContent = 'Select a release from the pool, then click edit to change its track membership.';
                return;
            }
            if (releaseIsSystemManaged(entry)) {
                editorHint.textContent = 'bandPromo demo is system-managed and preview only.';
                return;
            }
            if (entry?.locked) {
                editorHint.textContent = 'This release is locked. Track membership is preview-only until you unlock it from the release list.';
                return;
            }
            editorHint.textContent = 'Drag tracks between Available content below and this release list. Shift-click or Ctrl/Cmd-click to select multiple tracks.';
        }

        function updateSaveButtonVisibility() {
            if (!saveBtn) {
                return;
            }
            if (!releaseTrackEditingEnabled()) {
                saveBtn.hidden = true;
                return;
            }
            saveBtn.hidden = false;
            saveUi?.reconcile();
        }

        async function saveReleaseSettings({ silent = false } = {}) {
            if (releaseSettingsSaving) {
                return true;
            }
            if (!(releaseSettingsTitle instanceof HTMLInputElement)
                || !(releaseSettingsDate instanceof HTMLInputElement)) {
                return true;
            }

            const entry = releaseEntry(selectedReleaseId);
            if (!entry || releaseIsSystemManaged(entry)) {
                return true;
            }

            const settings = readReleaseSettingsFromForm();
            const { title, release_date: releaseDate } = settings;

            if (!title) {
                if (!silent && releaseSettingsStatus) {
                    releaseSettingsStatus.textContent = 'Release name is required.';
                }
                return false;
            }

            const dateError = validateReleaseDate(releaseDate);
            if (dateError) {
                if (!silent && releaseSettingsStatus) {
                    releaseSettingsStatus.textContent = dateError;
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
                if (!silent && releaseSettingsStatus) {
                    releaseSettingsStatus.textContent = message;
                }
                return false;
            }

            if (!releaseSettingsDirty()) {
                if (!silent && releaseSettingsStatus) {
                    releaseSettingsStatus.textContent = '';
                }
                return true;
            }

            releaseSettingsSaving = true;
            if (!silent && releaseSettingsStatus) {
                releaseSettingsStatus.textContent = 'Saving…';
            }

            try {
                const data = await fetchJson(`/biblioteca/manage-release.php?release=${encodeURIComponent(selectedReleaseId)}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settings),
                });
                releases = sortReleaseEntries(Array.isArray(data.releases) ? data.releases : releases);
                syncReleaseSettingsPanel(selectedReleaseId);
                renderReleasePoolList();
                updateReleaseEditorHint();
                renderLists();
                updateSaveButtonVisibility();
                if (!silent && releaseSettingsStatus) {
                    releaseSettingsStatus.textContent = 'Saved.';
                }
                return true;
            } catch (error) {
                if (!silent && releaseSettingsStatus) {
                    releaseSettingsStatus.textContent = error.message || 'Could not save release settings';
                }
                return false;
            } finally {
                releaseSettingsSaving = false;
            }
        }

        function closeReleaseDeleteModal() {
            pendingReleaseDeleteId = '';
            if (releaseDeleteModal) {
                releaseDeleteModal.style.display = 'none';
                releaseDeleteModal.setAttribute('aria-hidden', 'true');
            }
        }

        function openReleaseDeleteModal(releaseId) {
            const entry = releaseEntry(releaseId);
            if (!entry || !releaseCanDelete(entry)) {
                return;
            }
            const title = String(entry.title || releaseId);
            if (!releaseDeleteModal) {
                if (!window.confirm(`Delete release "${title}"? Its tracks will move to the primary release. This cannot be undone.`)) {
                    return;
                }
                deleteRelease(releaseId).catch((error) => showReleaseToast(error.message || 'Could not delete release'));
                return;
            }
            pendingReleaseDeleteId = releaseId;
            if (releaseDeleteModalName) {
                releaseDeleteModalName.textContent = title;
            }
            releaseDeleteModal.style.display = 'flex';
            releaseDeleteModal.setAttribute('aria-hidden', 'false');
            releaseDeleteConfirmBtn?.focus();
        }

        function showPoolView() {
            isEditing = false;
            if (poolView) {
                poolView.hidden = false;
            }
            if (tracksPoolView) {
                tracksPoolView.hidden = true;
            }
            if (releaseAvailableSection) {
                releaseAvailableSection.hidden = true;
            }
            saveUi?.reset();
            renderReleasePoolList();
            updateReleaseEditorHint();
            updateSaveButtonVisibility();
            updateReleaseCoverPanel();
        }

        function showEditView(releaseId) {
            isEditing = true;
            selectedReleaseId = releaseId;
            if (poolView) {
                poolView.hidden = true;
            }
            if (tracksPoolView) {
                tracksPoolView.hidden = false;
            }
            if (releaseAvailableSection) {
                releaseAvailableSection.hidden = false;
            }
            syncReleaseUrl(releaseId, true);
            syncReleaseSettingsPanel(releaseId);
            renderReleasePoolList();
            updateReleaseEditorHint();
            updateSaveButtonVisibility();
            updateReleaseCoverPanel();
        }

        function renderReleasePoolList() {
            if (!poolList) {
                return;
            }
            if (!releases.length) {
                poolList.innerHTML = '<li class="player-layout-empty">No releases available yet.</li>';
                return;
            }
            poolList.innerHTML = releases.map((entry) => {
                const id = String(entry.id || '');
                const selectedClass = id === selectedReleaseId ? ' playlist-editor-row-selected' : '';
                const title = escapeHtml(entry.title || id);
                const deleteBtn = releaseCanDelete(entry)
                    ? `<button type="button" class="page-pool-delete-btn" data-release-id="${escapeHtml(id)}" title="Delete release" aria-label="Delete ${title}">🗑️</button>`
                    : '';
                const editBtn = releaseCanOpenEditor(entry)
                    ? `<button type="button" class="page-pool-edit-btn" data-release-id="${escapeHtml(id)}" title="Edit release" aria-label="Edit ${title}">✏️</button>`
                    : '';
                const lockControl = releaseIsSystemManaged(entry)
                    ? ''
                    : `<button type="button" class="page-pool-lock-btn${entry.locked ? ' page-pool-lock-btn--active' : ''}" data-release-id="${escapeHtml(id)}" title="${entry.locked ? 'Unlock release (allow track edits)' : 'Lock release (freeze track membership)'}" aria-label="${entry.locked ? 'Unlock' : 'Lock'} ${title}" aria-pressed="${entry.locked ? 'true' : 'false'}">${entry.locked ? '🔒' : '🔓'}</button>`;
                return `<li class="playlist-editor-row release-pool-row page-pool-row${selectedClass}" data-release-id="${escapeHtml(id)}" aria-selected="${id === selectedReleaseId ? 'true' : 'false'}">
                    <span class="playlist-track-info">
                        <strong>💿 ${title}</strong>
                        <span class="playlist-track-meta">${escapeHtml(releaseMetaLine(entry))}</span>
                    </span>
                    <span class="page-pool-row-actions">
                        ${lockControl}
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
                epk,
            };
        }

        async function toggleReleaseLock(releaseId, locked) {
            const entry = releaseEntry(releaseId);
            if (!entry || releaseIsSystemManaged(entry)) {
                return false;
            }

            if (locked && isEditing && releaseId === selectedReleaseId && saveBtn.classList.contains('btn-amber')) {
                showReleaseToast('Save release tracks before locking this release.');
                return false;
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
                    updateSaveButtonVisibility();
                    renderLists();
                }
                return true;
            } catch (error) {
                showReleaseToast(error.message || 'Could not update release lock');
                return false;
            }
        }

        async function loadReleaseRegistry() {
            const data = await fetchJson('/biblioteca/get-releases.php');
            releases = sortReleaseEntries(Array.isArray(data.releases) ? data.releases : []);
            if (!releaseEntry(selectedReleaseId)) {
                selectedReleaseId = releases[0]?.id || 'primary';
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
            if (saveBtn.classList.contains('btn-amber')) {
                const proceed = window.confirm('You have unsaved release track changes. Leave edit mode without saving?');
                if (!proceed) {
                    return false;
                }
            }
            showPoolView();
            syncReleaseUrl(selectedReleaseId, false);
            await loadReleasePreview({ preserveSavedState: true });
            return true;
        }

        async function openReleaseEditor(releaseId) {
            if (!releaseId) {
                return;
            }
            const entry = releaseEntry(releaseId);
            if (!entry) {
                return;
            }
            if (isEditing && releaseId !== selectedReleaseId) {
                if (releaseSettingsDirty()) {
                    const saved = await saveReleaseSettings();
                    if (!saved) {
                        return;
                    }
                }
                if (saveBtn.classList.contains('btn-amber')) {
                    const proceed = window.confirm('You have unsaved release track changes. Switch releases without saving?');
                    if (!proceed) {
                        return;
                    }
                }
            }
            if (!releaseCanOpenEditor(entry)) {
                selectedReleaseId = releaseId;
                showPoolView();
                syncReleaseUrl(releaseId, false);
                await loadReleasePreview({ preserveSavedState: true });
                return;
            }
            selectedReleaseId = releaseId;
            showEditView(releaseId);
            await loadReleasePreview();
        }

        async function selectReleaseForPreview(releaseId) {
            if (!releaseId || (releaseId === selectedReleaseId && !isEditing)) {
                return;
            }
            if (isEditing) {
                await openReleaseEditor(releaseId);
                return;
            }
            if (saveBtn.classList.contains('btn-amber')) {
                const proceed = window.confirm('You have unsaved release track changes. Switch releases without saving?');
                if (!proceed) {
                    return;
                }
            }
            selectedReleaseId = releaseId;
            syncReleaseUrl(releaseId, false);
            renderReleasePoolList();
            await loadReleasePreview({ preserveSavedState: true });
        }

        async function deleteRelease(releaseId) {
            const entry = releaseEntry(releaseId);
            if (!entry || !releaseCanDelete(entry)) {
                return;
            }
            const data = await fetchJson(`/biblioteca/manage-release.php?release=${encodeURIComponent(releaseId)}`, {
                method: 'DELETE',
            });
            releases = Array.isArray(data.releases) ? data.releases : [];
            if (selectedReleaseId === releaseId) {
                selectedReleaseId = releases[0]?.id || 'primary';
                showPoolView();
                syncReleaseUrl(selectedReleaseId, false);
                await loadReleasePreview({ preserveSavedState: true });
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
                ? '<button type="button" class="player-layout-remove-btn" title="Move to Available content" aria-label="Remove from release">✕</button>'
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
                <span class="playlist-drag-handle" title="${dragTitle}">⠿</span>
                ${positionMarkup}
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

            const entry = releaseEntry(selectedReleaseId);
            const canEditTracks = releaseTrackEditingEnabled(entry);

            if (!selectedReleaseId) {
                activeEl.innerHTML = '<li class="player-layout-empty">No release selected.</li>';
                if (countBadge) {
                    countBadge.textContent = '';
                }
                return;
            }

            if (!activeTracks.length) {
                activeEl.innerHTML = canEditTracks
                    ? '<li class="player-layout-empty">Drag tracks here from Available content.</li>'
                    : '<li class="player-layout-empty">This release has no tracks yet.</li>';
            } else {
                activeEl.innerHTML = activeTracks.map((track, index) => renderTrackRow(track, {
                    showPosition: true,
                    position: index + 1,
                    showRemove: canEditTracks,
                    activeRow: true,
                    selected: selectedActive.has(String(track.file || '')),
                })).join('');
            }

            if (!isEditing) {
                if (countBadge) {
                    countBadge.textContent = activeTracks.length ? `(${activeTracks.length})` : '';
                }
                saveUi?.reconcile();
                updateSaveButtonVisibility();
                return;
            }

            if (!availableTracks.length) {
                const emptyMessage = canEditTracks
                    ? (activeTracks.length
                        ? 'All tracks for this release are already in the list above. Use ✕ to move a track back here.'
                        : 'No catalogued tracks belong to this release yet. Upload audio or assign tracks in Files → Audio.')
                    : 'Track membership is preview-only while this release is locked.';
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
            updateSaveButtonVisibility();
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
                movePlaceholder(listEl, event.clientY);
            });

            listEl.addEventListener('drop', (event) => {
                if (!draggedRows.length) {
                    return;
                }
                event.preventDefault();
                movePlaceholder(listEl, event.clientY);
                finalizeDrag();
            });

            listEl.addEventListener('dragend', () => {
                finalizeWithinListDrag(listEl);
                draggedRows.forEach((row) => row.classList.remove('dragging'));
                dragPlaceholder?.remove();
                dragSourceRow = null;
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

        function applyPreviewData(data) {
            const entry = releaseEntry(selectedReleaseId);
            if (entry) {
                if (typeof data.locked === 'boolean') {
                    entry.locked = data.locked;
                }
            }

            activeTracks = Array.isArray(data.activeTracks) ? data.activeTracks.map(cloneTrack) : [];
            availableTracks = Array.isArray(data.availableTracks) ? data.availableTracks.map(cloneTrack) : [];

            renderReleasePoolList();
            syncReleaseSettingsPanel(selectedReleaseId);
            updateReleaseEditorHint();
            renderLists();
            updateReleaseCoverPanel();
        }

        async function loadReleasePreview(options = {}) {
            const preserveSavedState = options.preserveSavedState === true;
            try {
                const data = await fetchJson(`/biblioteca/get-release-preview.php?release=${encodeURIComponent(selectedReleaseId)}`);
                applyPreviewData(data);
                if (preserveSavedState) {
                    saveUi?.markSaved();
                } else {
                    saveUi?.setBaseline();
                }
            } catch (error) {
                activeEl.innerHTML = '';
                availableEl.innerHTML = `<li class="player-layout-empty" style="color:#f87171">Could not load release preview: ${escapeHtml(error.message)}</li>`;
            }
        }

        releaseDeleteCancelBtn?.addEventListener('click', closeReleaseDeleteModal);
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
            closeReleaseDeleteModal();
            try {
                releaseDeleteConfirmBtn.disabled = true;
                await deleteRelease(releaseId);
            } catch (error) {
                showReleaseToast(error.message || 'Could not delete release');
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
        releaseSettingsDate?.addEventListener('blur', () => {
            saveReleaseSettings();
        });
        releaseSettingsDate?.addEventListener('change', () => {
            saveReleaseSettings();
        });
        releaseSettingsCatalogId?.addEventListener('blur', () => {
            saveReleaseSettings();
        });
        releaseSettingsPosterAssetId?.addEventListener('input', () => {
            updateReleaseCoverPreview();
            saveReleaseSettings();
        });
        releaseSettingsShortDescription?.addEventListener('input', () => {
            updateReleaseShortDescriptionCount();
        });
        [
            releaseSettingsShortDescription,
            releaseSettingsDescription,
            releaseSettingsTagline,
            releaseSettingsGenre,
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
                    releaseRegistryStatus.textContent = 'Release name is required.';
                    releaseRegistryStatus.style.color = '#f87171';
                }
                return;
            }
            try {
                if (releaseRegistryStatus) {
                    releaseRegistryStatus.textContent = 'Creating release…';
                    releaseRegistryStatus.style.color = '';
                }
                const data = await fetchJson('/biblioteca/manage-release.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ title }),
                });
                releases = sortReleaseEntries(Array.isArray(data.releases) ? data.releases : releases);
                const newId = data.release?.id || '';
                addReleaseForm.reset();
                setAddReleasePanelOpen(false);
                if (newId) {
                    await openReleaseEditor(newId);
                } else {
                    renderReleasePoolList();
                }
            } catch (error) {
                if (releaseRegistryStatus) {
                    releaseRegistryStatus.textContent = '❌ ' + (error.message || 'Could not create release');
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

        saveBtn.addEventListener('click', async () => {
            if (!releaseTrackEditingEnabled()) {
                return;
            }
            syncActiveOrderFromDOM();
            saveUi?.markSaving();
            const order = activeTracks.map((track) => String(track.file || '')).filter(Boolean);
            try {
                const data = await fetchJson(`/biblioteca/save-release-tracks.php?release=${encodeURIComponent(selectedReleaseId)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(order),
                });
                await loadReleasePreview({ preserveSavedState: true });
                if (data.warning) {
                    showReleaseToast(data.warning, 'warning');
                }
            } catch (error) {
                saveUi?.markFailed();
                showReleaseToast(error.message || 'Could not save release tracks');
            }
        });

        initReleaseCoverPicker();

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
                    await loadReleasePreview({ preserveSavedState: true });
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
