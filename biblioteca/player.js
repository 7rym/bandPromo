// --- APPLICATION LOGIC ---

let playList = []; // Will be loaded from playlist.json
let currentIndex = 0;
let PATH_VARIANT = 'optimal'; // Will be set by speed test (HQ or optimal), defaults to safe optimal
const IMAGE_PATH_VARIANT = 'optimal';

// Path helpers — use window.MEDIA_AUDIO_BASE / window.MEDIA_IMG_BASE when set
// (new /play/ structure), otherwise fall back to old sibling-folder relative paths.
function resolveAudioDeliveryFilename(filename) {
    if (typeof filename !== 'string' || filename === '') {
        return '';
    }
    return PATH_VARIANT === 'optimal' ? filename.replace(/\.(flac|wav)$/i, '.mp3') : filename;
}

function getAudioMimeType(filename) {
    const ext = String(filename || '').split('.').pop().toLowerCase();
    const mimeMap = {
        mp3: 'audio/mpeg',
        m4a: 'audio/mp4',
        ogg: 'audio/ogg',
        wav: 'audio/wav',
        flac: 'audio/flac',
        aac: 'audio/aac'
    };
    return mimeMap[ext] || '';
}

function buildAudioUrl(filename) {
    // For optimal, supported source-audio formats resolve to generated MP3 delivery.
    const f = resolveAudioDeliveryFilename(filename);

    const params = new URLSearchParams({
        variant: PATH_VARIANT,
        file: f,
    });
    return `/biblioteca/audio.php?${params.toString()}`;
}

function buildCoverUrl(rawCoverPath, variant = IMAGE_PATH_VARIANT) {
    if (!rawCoverPath) return '';
    const filename = rawCoverPath.split('\\').pop().split('/').pop();
    const stem = filename.replace(/\.[^.]+$/, '');
    const extMatch = filename.match(/\.(png|jpe?g|webp)$/i);
    const ext = extMatch ? extMatch[1].toLowerCase() : 'jpg';
    const normalizedExt = variant === 'optimal' ? 'jpg' : ext;
    const name = `${stem}.${normalizedExt}`;
    if (window.MEDIA_IMG_BASE != null) {
        return `${window.MEDIA_IMG_BASE}/${variant}/${name}`;
    }
    return `../${variant}/${name}`;
}

function buildCoverUrlCandidates(rawCoverPath) {
    if (!rawCoverPath) return [];
    const filename = rawCoverPath.split('\\').pop().split('/').pop();
    const stem = filename.replace(/\.[^.]+$/, '');
    const extMatch = filename.match(/\.(png|jpe?g|webp)$/i);
    const ext = extMatch ? extMatch[1].toLowerCase() : 'jpg';
    const base = window.MEDIA_IMG_BASE != null ? window.MEDIA_IMG_BASE : '..';
    const candidates = [
        `${base}/optimal/${stem}.jpg`,
        `${base}/original/${filename}`,
        `${base}/original/${stem}.${ext}`,
        `${base}/original/${stem}.jpg`,
        `${base}/original/${stem}.png`,
    ];
    return candidates.filter((url, index, list) => list.indexOf(url) === index);
}

function setImageWithFallback(image, rawCoverPath) {
    if (!image) return;
    const candidates = buildCoverUrlCandidates(rawCoverPath);
    if (!candidates.length) {
        image.removeAttribute('src');
        return;
    }

    let index = 0;
    const tryNext = () => {
        if (index >= candidates.length) {
            image.removeAttribute('src');
            return;
        }
        image.onerror = () => {
            index += 1;
            tryNext();
        };
        image.src = candidates[index];
    };

    tryNext();
}

function hasDisplayableLyrics(song) {
    const lyrics = typeof song?.lyrics === 'string' ? song.lyrics.trim() : '';
    return lyrics !== '' && !lyrics.startsWith('No lyrics found.');
}

function getPreferredPrimaryView(song) {
    return hasDisplayableLyrics(song) ? 'lyrics' : 'playlist';
}

function syncLyricsTab(song) {
    const lyricsButton = document.querySelector('.content-toggle button[data-view="lyrics"]');
    if (!lyricsButton) {
        return;
    }

    const hasLyrics = hasDisplayableLyrics(song);
    lyricsButton.hidden = !hasLyrics;
    lyricsButton.disabled = !hasLyrics;
    lyricsButton.setAttribute('aria-hidden', hasLyrics ? 'false' : 'true');
    if (hasLyrics) {
        lyricsButton.removeAttribute('tabindex');
        lyricsButton.removeAttribute('title');
    } else {
        lyricsButton.setAttribute('tabindex', '-1');
        lyricsButton.setAttribute('title', 'Lyrics are unavailable for this track');
    }

    if (!hasLyrics && lyricsBox.classList.contains('active')) {
        toggleView(getPreferredPrimaryView(song));
    }
}

const audioPlayer = document.getElementById('audioPlayer');
const coverImage = document.getElementById('coverImage');
const reflectionImage = document.getElementById('reflectionImage');
const prevCover = document.getElementById('prevCover');
const nextCover = document.getElementById('nextCover');
const songTitle = document.getElementById('songTitle');
const artistName = document.getElementById('artistName');
const lyricsBox = document.getElementById('lyricsBox');
const cardWrapper = document.getElementById('cardWrapper');
const playBtn = document.getElementById('playBtn');
const loadingMsg = document.getElementById('loading-msg');
const mediaPlayerEl = document.getElementById('mediaplayer');
const lightbox = document.getElementById('lightbox');
const lightboxImage = document.getElementById('lightboxImage');
const debugPanelButton = document.getElementById('debug-panel-btn');
const debugModal = document.getElementById('debugModal');
const debugModalContent = document.getElementById('debugModalContent');
const debugModalCloseButton = document.getElementById('debugModalClose');
const debugLogoutButton = document.getElementById('debugLogoutBtn');
const debugClearCacheButton = document.getElementById('debugClearCacheBtn');
const debugDataBody = document.getElementById('debugDataBody');

// Playback state used to keep seek behavior and logging stable.
let hasStartedCurrentTrack = false;
let lastPlayLogAt = 0;
let isUserSeeking = false;
let lastSeekFinishedAt = 0;
const ANALYTICS_INACTIVITY_TIMEOUT_MS = Number.isFinite(window.BANDPROMO_ANALYTICS_INACTIVITY_TIMEOUT_MS)
    ? window.BANDPROMO_ANALYTICS_INACTIVITY_TIMEOUT_MS
    : 15 * 60 * 1000;
let analyticsSessionActive = false;
let analyticsInactivityTimerId = null;
let pendingPlayActionSource = null;
let debugRefreshIntervalId = null;
let manifestDebugCache = null;
let wasPlayingBeforeVisibilityHidden = false;
let resumeAfterVisibilityPause = false;
let currentTrackChangeSource = null;
let environmentSnapshotSignature = null;
let environmentChangeDebounceId = null;

function isStandaloneDisplayMode() {
    return window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: window-controls-overlay)').matches ||
        window.matchMedia('(display-mode: fullscreen)').matches ||
        navigator.standalone === true;
}

function getDisplayModeLabel() {
    if (window.matchMedia('(display-mode: fullscreen)').matches) {
        return 'fullscreen';
    }
    if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true) {
        return 'standalone';
    }
    if (window.matchMedia('(display-mode: window-controls-overlay)').matches) {
        return 'window-controls-overlay';
    }
    if (window.matchMedia('(display-mode: minimal-ui)').matches) {
        return 'minimal-ui';
    }
    return 'browser';
}

function isMobileWideMode() {
    return window.matchMedia('(orientation: landscape)').matches &&
        window.innerWidth <= 1024 &&
        (navigator.maxTouchPoints > 0 || window.matchMedia('(pointer: coarse)').matches);
}

function getFullscreenElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
}

function updateMediaSessionMetadata() {
    if (!('mediaSession' in navigator) || typeof MediaMetadata !== 'function') {
        return;
    }

    const song = playList[currentIndex];
    if (!song) {
        return;
    }

    const candidates = buildCoverUrlCandidates(song.cover);
    const artwork = candidates.map((src) => ({ src, sizes: '600x600', type: 'image/jpeg' }));

    try {
        navigator.mediaSession.metadata = new MediaMetadata({
            title: song.title || song.file || 'Unknown title',
            artist: song.artist || '',
            album: song.album || '',
            artwork,
        });
    } catch (error) {
        console.warn('Unable to update media session metadata', error);
    }
}

function updateMediaSessionPlaybackState() {
    if (!('mediaSession' in navigator)) {
        return;
    }

    navigator.mediaSession.playbackState = audioPlayer.paused ? 'paused' : 'playing';
}

function updateMediaSessionPositionState() {
    if (!('mediaSession' in navigator) || typeof navigator.mediaSession.setPositionState !== 'function') {
        return;
    }

    const duration = Number(audioPlayer.duration);
    if (!Number.isFinite(duration) || duration <= 0) {
        return;
    }

    try {
        navigator.mediaSession.setPositionState({
            duration,
            playbackRate: audioPlayer.playbackRate || 1,
            position: Math.min(audioPlayer.currentTime || 0, duration),
        });
    } catch (error) {
        // Some browsers reject position updates during transitions.
    }
}

function installMediaSessionHandlers() {
    if (!('mediaSession' in navigator)) {
        return;
    }

    const session = navigator.mediaSession;
    const bind = (action, handler) => {
        try {
            session.setActionHandler(action, handler);
        } catch (error) {
            // Ignore unsupported media session actions.
        }
    };

    bind('play', () => {
        pendingPlayActionSource = 'media_session';
        resumeAfterVisibilityPause = false;
        audioPlayer.play().catch(() => {});
    });

    bind('pause', () => {
        resumeAfterVisibilityPause = false;
        wasPlayingBeforeVisibilityHidden = false;
        audioPlayer.pause();
        logActivity('play_pause', {
            ...getCurrentTrackSnapshot({ actionSource: 'media_session' })
        });
    });

    bind('previoustrack', () => {
        pendingPlayActionSource = 'media_session';
        prevSong();
    });

    bind('nexttrack', () => {
        pendingPlayActionSource = 'media_session';
        nextSong();
    });

    bind('seekbackward', (details = {}) => {
        const offset = Number(details.seekOffset) || 10;
        audioPlayer.currentTime = Math.max(0, (audioPlayer.currentTime || 0) - offset);
        updateMediaSessionPositionState();
    });

    bind('seekforward', (details = {}) => {
        const offset = Number(details.seekOffset) || 10;
        const duration = Number(audioPlayer.duration) || 0;
        if (duration > 0) {
            audioPlayer.currentTime = Math.min(duration, (audioPlayer.currentTime || 0) + offset);
        } else {
            audioPlayer.currentTime = (audioPlayer.currentTime || 0) + offset;
        }
        updateMediaSessionPositionState();
    });

    bind('seekto', (details = {}) => {
        if (typeof details.seekTime !== 'number' || !Number.isFinite(details.seekTime)) {
            return;
        }
        audioPlayer.currentTime = Math.max(0, details.seekTime);
        updateMediaSessionPositionState();
    });
}

function handleVisibilityPlaybackChange() {
    if (document.hidden) {
        wasPlayingBeforeVisibilityHidden = !audioPlayer.paused && !audioPlayer.ended;
        if (!wasPlayingBeforeVisibilityHidden) {
            resumeAfterVisibilityPause = false;
        }
        return;
    }

    const shouldResume = resumeAfterVisibilityPause;
    wasPlayingBeforeVisibilityHidden = false;
    resumeAfterVisibilityPause = false;
    if (shouldResume) {
        pendingPlayActionSource = pendingPlayActionSource || 'visibility_resume';
        audioPlayer.play().catch(() => {});
    }
}

async function requestDocumentFullscreen() {
    const element = document.documentElement;
    const requestFullscreen = element.requestFullscreen || element.webkitRequestFullscreen;
    if (!requestFullscreen) {
        return;
    }

    try {
        await requestFullscreen.call(element);
    } catch (error) {
        // Ignore blocked fullscreen requests; user interaction may be required.
    }
}

async function exitDocumentFullscreen() {
    const exitFullscreen = document.exitFullscreen || document.webkitExitFullscreen;
    if (!exitFullscreen) {
        return;
    }

    try {
        await exitFullscreen.call(document);
    } catch (error) {
        // Ignore exit failures.
    }
}

let wideModeFullscreenOwned = false;

async function syncWideModeFullscreen() {
    if (isStandaloneDisplayMode()) {
        return;
    }

    if (isMobileWideMode()) {
        if (!getFullscreenElement()) {
            await requestDocumentFullscreen();
            wideModeFullscreenOwned = !!getFullscreenElement();
        }
        return;
    }

    if (wideModeFullscreenOwned && getFullscreenElement()) {
        await exitDocumentFullscreen();
    }
    wideModeFullscreenOwned = false;
}

function formatDebugValue(value) {
    if (value === null || value === undefined || value === '') {
        return 'n/a';
    }
    return String(value);
}

function getLastCacheClearSummary() {
    const raw = sessionStorage.getItem('bandpromo_debug_last_cache_clear');
    if (!raw) {
        return 'n/a';
    }

    try {
        const data = JSON.parse(raw);
        const removedCaches = Number.isFinite(Number(data.removedCaches)) ? Number(data.removedCaches) : 0;
        const removedKeys = Array.isArray(data.removedKeys) ? data.removedKeys.join(', ') : 'none';
        const stamp = typeof data.at === 'string' && data.at !== '' ? data.at : 'unknown time';
        return `${stamp} | caches: ${removedCaches} | keys: ${removedKeys || 'none'}`;
    } catch (error) {
        return 'n/a';
    }
}

function formatBytes(bytes) {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = value;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    const precision = size >= 100 || unitIndex === 0 ? 0 : 1;
    return `${size.toFixed(precision)} ${units[unitIndex]}`;
}

function formatTime(seconds) {
    const value = Number(seconds);
    if (!Number.isFinite(value) || value < 0) {
        return '0:00';
    }

    const totalSeconds = Math.floor(value);
    const minutes = Math.floor(totalSeconds / 60);
    const remainingSeconds = totalSeconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

function getDisplayModeLabel() {
    if (window.matchMedia('(display-mode: fullscreen)').matches) return 'fullscreen';
    if (window.matchMedia('(display-mode: standalone)').matches) return 'standalone';
    if (window.matchMedia('(display-mode: window-controls-overlay)').matches) return 'window-controls-overlay';
    if (navigator.standalone === true) return 'standalone-ios';
    return 'browser-tab';
}

function getDebugConnectionSummary() {
    const cached = sessionStorage.getItem('connection_speed');
    if (!cached) {
        return 'n/a';
    }

    try {
        const data = JSON.parse(cached);
        const speed = Number(data.speed);
        if (!Number.isFinite(speed)) {
            return 'n/a';
        }
        const recommended = data.recommended === 'high' ? 'high' : 'low';
        return `${speed.toFixed(2)} Mbps (${recommended})`;
    } catch (error) {
        return 'n/a';
    }
}

function getApproximateTransferStats() {
    const entries = performance.getEntriesByType('resource');
    let sessionTransferBytes = 0;
    let audioTransferBytes = 0;
    let requestCount = 0;

    entries.forEach((entry) => {
        if (!entry || typeof entry.name !== 'string') {
            return;
        }

        let entryUrl;
        try {
            entryUrl = new URL(entry.name, window.location.href);
        } catch (error) {
            return;
        }

        if (entryUrl.origin !== window.location.origin) {
            return;
        }

        requestCount += 1;
        const transferSize = entry.transferSize || entry.encodedBodySize || entry.decodedBodySize || 0;
        sessionTransferBytes += transferSize;

        if (entryUrl.pathname === '/biblioteca/audio.php' || entryUrl.pathname.startsWith('/media/audio/')) {
            audioTransferBytes += transferSize;
        }
    });

    return {
        sessionTransferBytes,
        audioTransferBytes,
        requestCount,
    };
}

function getAppCachePrefix() {
    const hostKey = window.location.hostname.replace(/\./g, '.').split('.')[0];
    return `${hostKey}-app-`;
}

async function getDebugCacheInfo() {
    if (!('caches' in window)) {
        return {
            appCacheNames: [],
            totalCaches: 0,
        };
    }

    try {
        const cacheNames = await caches.keys();
        const prefix = getAppCachePrefix();
        return {
            appCacheNames: cacheNames.filter((name) => name.startsWith(prefix)),
            totalCaches: cacheNames.length,
        };
    } catch (error) {
        return {
            appCacheNames: [],
            totalCaches: 0,
        };
    }
}

async function getStorageEstimateSummary() {
    if (!navigator.storage || typeof navigator.storage.estimate !== 'function') {
        return 'n/a';
    }

    try {
        const estimate = await navigator.storage.estimate();
        const used = formatBytes(estimate.usage || 0);
        const quota = formatBytes(estimate.quota || 0);
        return `${used} / ${quota}`;
    } catch (error) {
        return 'n/a';
    }
}

async function getLiveManifestSummary() {
    const now = Date.now();
    if (manifestDebugCache && (now - manifestDebugCache.fetchedAt) < 15000) {
        return manifestDebugCache.summary;
    }

    try {
        const manifestUrl = new URL('/site.webmanifest', window.location.origin);
        manifestUrl.searchParams.set('debug_manifest', String(now));
        const response = await fetch(manifestUrl.toString(), { cache: 'no-store' });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const manifest = await response.json();
        const summary = {
            orientation: manifest.orientation || 'missing',
            display: manifest.display || 'missing',
            startUrl: manifest.start_url || 'missing',
        };
        manifestDebugCache = {
            fetchedAt: now,
            summary,
        };
        return summary;
    } catch (error) {
        const summary = {
            orientation: `error: ${error.message || error}`,
            display: 'n/a',
            startUrl: 'n/a',
        };
        manifestDebugCache = {
            fetchedAt: now,
            summary,
        };
        return summary;
    }
}

async function buildDebugRows() {
    const debugInfo = window.BANDPROMO_DEBUG_INFO || {};
    const transfer = getApproximateTransferStats();
    const cacheInfo = await getDebugCacheInfo();
    const storageEstimate = await getStorageEstimateSummary();
    const manifestSummary = await getLiveManifestSummary();
    const currentTrack = playList[currentIndex] || null;
    const explicitQuality = sessionStorage.getItem('bandpromo_selected_quality') || 'n/a';
    const serviceWorkerController = navigator.serviceWorker?.controller?.scriptURL || 'none';
    const currentSource = audioPlayer.currentSrc || audioPlayer.src || 'n/a';
    const visualViewportSummary = window.visualViewport
        ? `${Math.round(window.visualViewport.width)} x ${Math.round(window.visualViewport.height)} @ ${window.visualViewport.scale.toFixed(2)}`
        : 'n/a';
    const screenOrientation = screen.orientation?.type || (window.matchMedia('(orientation: landscape)').matches ? 'landscape' : 'portrait');

    return [
        ['Version build', debugInfo.version || 'n/a'],
        ['User', debugInfo.username || 'n/a'],
        ['Role', debugInfo.role || 'n/a'],
        ['Display mode', getDisplayModeLabel()],
        ['Viewport', `${window.innerWidth} x ${window.innerHeight}`],
        ['Visual viewport', visualViewportSummary],
        ['Screen size', `${screen.width} x ${screen.height}`],
        ['Screen orientation', screenOrientation],
        ['Device pixel ratio', String(window.devicePixelRatio || 1)],
        ['Live manifest orientation', manifestSummary.orientation],
        ['Live manifest display', manifestSummary.display],
        ['Live manifest start_url', manifestSummary.startUrl],
        ['Session quality', debugInfo.sessionQuality || 'n/a'],
        ['Explicit selected quality', explicitQuality],
        ['Resolved audio variant', PATH_VARIANT],
        ['Server preferred variant', debugInfo.preferredAudioVariant || 'n/a'],
        ['Current track', currentTrack ? `${currentIndex + 1}. ${currentTrack.title || 'Untitled'}` : 'n/a'],
        ['Current artist', currentTrack?.artist || 'n/a'],
        ['Playback state', audioPlayer.paused ? 'paused' : 'playing'],
        ['Playback position', `${formatTime(audioPlayer.currentTime)} / ${formatTime(audioPlayer.duration)}`],
        ['Requested source', currentSource],
        ['Approx session transfer', formatBytes(transfer.sessionTransferBytes)],
        ['Approx audio transfer', formatBytes(transfer.audioTransferBytes)],
        ['Session resource requests', String(transfer.requestCount)],
        ['Connection test', getDebugConnectionSummary()],
        ['Storage estimate', storageEstimate],
        ['App cache buckets', cacheInfo.appCacheNames.length ? cacheInfo.appCacheNames.join(', ') : 'none'],
        ['CacheStorage entries', String(cacheInfo.totalCaches)],
        ['Last cache clear', getLastCacheClearSummary()],
        ['Service worker controller', serviceWorkerController],
        ['Online status', navigator.onLine ? 'online' : 'offline'],
        ['Page path', debugInfo.path || window.location.pathname],
        ['Origin', debugInfo.origin || window.location.origin],
        ['User agent', navigator.userAgent],
    ];
}

function renderDebugRows(rows) {
    if (!debugDataBody) {
        return;
    }

    debugDataBody.replaceChildren();

    rows.forEach(([label, value]) => {
        const labelNode = document.createElement('div');
        labelNode.className = 'debug-data-label';
        labelNode.textContent = formatDebugValue(label);

        const valueNode = document.createElement('div');
        valueNode.className = 'debug-data-value';
        valueNode.textContent = formatDebugValue(value);

        debugDataBody.append(labelNode, valueNode);
    });
}

async function refreshDebugInfo() {
    if (!debugDataBody) {
        return;
    }

    const rows = await buildDebugRows();
    renderDebugRows(rows);
}

function stopDebugRefreshLoop() {
    if (debugRefreshIntervalId !== null) {
        window.clearInterval(debugRefreshIntervalId);
        debugRefreshIntervalId = null;
    }
}

function startDebugRefreshLoop() {
    stopDebugRefreshLoop();
    debugRefreshIntervalId = window.setInterval(() => {
        refreshDebugInfo().catch(() => {});
    }, 1000);
}

function openDebugModal() {
    if (!debugModal) {
        return;
    }

    debugModal.classList.add('active');
    debugModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('debug-modal-open');
    refreshDebugInfo().catch(() => {});
    startDebugRefreshLoop();
}

function closeDebugModal() {
    if (!debugModal) {
        return;
    }

    debugModal.classList.remove('active');
    debugModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('debug-modal-open');
    stopDebugRefreshLoop();
}

function logoutFromDebugPanel() {
    window.location.href = '/?logout=1';
}

async function clearAppCache() {
    if (!confirm('Clear app cache and reload this page fresh?')) {
        return;
    }

    if (debugClearCacheButton) {
        debugClearCacheButton.disabled = true;
    }

    try {
        let removedCacheCount = 0;
        if ('caches' in window) {
            const cacheNames = await caches.keys();
            const prefix = getAppCachePrefix();
            const appCacheNames = cacheNames.filter((name) => name.startsWith(prefix));
            removedCacheCount = appCacheNames.length;
            await Promise.all(appCacheNames.map((name) => caches.delete(name)));
        }

        const removedKeys = ['connection_speed', 'bandpromo_selected_quality', 'csrf_token', 'pwa-banner-dismissed'];
        sessionStorage.removeItem('connection_speed');
        sessionStorage.removeItem('bandpromo_selected_quality');
        sessionStorage.removeItem('csrf_token');
        sessionStorage.removeItem('pwa-banner-dismissed');
        sessionStorage.setItem('bandpromo_debug_last_cache_clear', JSON.stringify({
            at: new Date().toLocaleString(),
            removedCaches: removedCacheCount,
            removedKeys,
        }));

        if (navigator.serviceWorker && typeof navigator.serviceWorker.getRegistrations === 'function') {
            const registrations = await navigator.serviceWorker.getRegistrations();
            await Promise.all(registrations.map((registration) => registration.update().catch(() => {})));
            if (navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
            }
        }

        const refreshUrl = new URL(window.location.href);
        refreshUrl.searchParams.set('debug_refresh', Date.now().toString());
        window.location.href = refreshUrl.toString();
    } catch (error) {
        alert(`Unable to clear cache: ${error.message || error}`);
        if (debugClearCacheButton) {
            debugClearCacheButton.disabled = false;
        }
        refreshDebugInfo().catch(() => {});
    }
}

// Logging Function
async function logActivity(activity, trackData = null) {
    const extraData = trackData && typeof trackData === 'object' && !Array.isArray(trackData)
        ? Object.fromEntries(Object.entries(trackData).filter(([, value]) => value !== undefined))
        : {};

    try {
        const response = await fetch('/biblioteca/log.php?action=log', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                activity: activity,
                data: {
                    track_title: trackData?.title || null,
                    track_artist: trackData?.artist || null,
                    track_index: trackData?.index ?? null,
                    current_time: trackData?.currentTime || null,
                    duration: trackData?.duration || null,
                    quality: PATH_VARIANT || 'unknown', // HQ or optimal
                    completion_rate: trackData?.completionRate || null, // For skip pattern analysis
                    exit_reason: trackData?.exitReason || null,
                    action_source: trackData?.actionSource || null,
                    ...extraData
                }
            })
        });
        if (!response.ok) {
            // Logging failed silently
        }
    } catch (error) {
        // Logging error silently
    }
}

function getCurrentTrackSnapshot(extra = {}) {
    const duration = audioPlayer.duration || playList[currentIndex]?.duration || null;
    const currentTime = audioPlayer.currentTime || 0;
    return {
        index: currentIndex,
        title: playList[currentIndex]?.title,
        artist: playList[currentIndex]?.artist,
        currentTime: currentTime,
        duration: duration,
        completionRate: duration ? Math.round((currentTime / duration) * 100) : 0,
        ...extra
    };
}

function logTrackExit(exitReason, actionSource = null) {
    if (playList.length === 0) return;
    logActivity('track_exited', getCurrentTrackSnapshot({
        exitReason: exitReason,
        actionSource: actionSource
    }));
}

function buildEnvironmentSnapshot() {
    const visualViewport = window.visualViewport;
    const orientation = window.screen?.orientation;

    return {
        display_mode: getDisplayModeLabel(),
        viewport_width: window.innerWidth || null,
        viewport_height: window.innerHeight || null,
        visual_viewport_width: visualViewport ? Math.round(visualViewport.width) : null,
        visual_viewport_height: visualViewport ? Math.round(visualViewport.height) : null,
        visual_viewport_scale: visualViewport ? Number(visualViewport.scale.toFixed(3)) : null,
        screen_width: window.screen?.width || null,
        screen_height: window.screen?.height || null,
        orientation_type: orientation?.type || (window.matchMedia('(orientation: landscape)').matches ? 'landscape' : 'portrait'),
        orientation_angle: typeof orientation?.angle === 'number' ? orientation.angle : null,
        device_pixel_ratio: window.devicePixelRatio || 1,
        touch_points: navigator.maxTouchPoints || 0,
        coarse_pointer: window.matchMedia('(pointer: coarse)').matches,
        standalone: isStandaloneDisplayMode(),
        fullscreen: !!getFullscreenElement(),
        online: navigator.onLine,
        language: navigator.language || '',
        platform: navigator.platform || '',
        captured_at: new Date().toISOString(),
    };
}

function buildEnvironmentSignature(snapshot) {
    return JSON.stringify({
        display_mode: snapshot.display_mode,
        viewport_width: snapshot.viewport_width,
        viewport_height: snapshot.viewport_height,
        visual_viewport_width: snapshot.visual_viewport_width,
        visual_viewport_height: snapshot.visual_viewport_height,
        visual_viewport_scale: snapshot.visual_viewport_scale,
        screen_width: snapshot.screen_width,
        screen_height: snapshot.screen_height,
        orientation_type: snapshot.orientation_type,
        orientation_angle: snapshot.orientation_angle,
        device_pixel_ratio: snapshot.device_pixel_ratio,
        touch_points: snapshot.touch_points,
        coarse_pointer: snapshot.coarse_pointer,
        standalone: snapshot.standalone,
        fullscreen: snapshot.fullscreen,
        online: snapshot.online,
        language: snapshot.language,
        platform: snapshot.platform,
    });
}

function clearEnvironmentChangeDebounce() {
    if (environmentChangeDebounceId !== null) {
        window.clearTimeout(environmentChangeDebounceId);
        environmentChangeDebounceId = null;
    }
}

function logEnvironmentSnapshot(actionSource = 'player_loaded') {
    const snapshot = buildEnvironmentSnapshot();
    const signature = buildEnvironmentSignature(snapshot);

    if (environmentSnapshotSignature === null) {
        environmentSnapshotSignature = signature;
        logActivity('environment_snapshot', {
            actionSource,
            environment: snapshot,
        });
        return;
    }

    if (signature === environmentSnapshotSignature) {
        return;
    }

    environmentSnapshotSignature = signature;
    logActivity('environment_changed', {
        actionSource,
        environment: snapshot,
    });
}

function scheduleEnvironmentSnapshot(actionSource) {
    clearEnvironmentChangeDebounce();
    environmentChangeDebounceId = window.setTimeout(() => {
        environmentChangeDebounceId = null;
        logEnvironmentSnapshot(actionSource);
    }, 250);
}

function clearAnalyticsInactivityTimer() {
    if (analyticsInactivityTimerId !== null) {
        window.clearTimeout(analyticsInactivityTimerId);
        analyticsInactivityTimerId = null;
    }
}

function ensureAnalyticsSession(actionSource = 'player') {
    clearAnalyticsInactivityTimer();

    if (analyticsSessionActive || playList.length === 0) {
        return;
    }

    analyticsSessionActive = true;
    logActivity('play_start', getCurrentTrackSnapshot({ actionSource }));
}

function scheduleAnalyticsSessionEnd() {
    if (!analyticsSessionActive) {
        return;
    }

    clearAnalyticsInactivityTimer();
    analyticsInactivityTimerId = window.setTimeout(() => {
        analyticsInactivityTimerId = null;
        logSessionEnd({ actionSource: 'inactivity_timeout' });
    }, ANALYTICS_INACTIVITY_TIMEOUT_MS);
}

// Resolve track index from path deep link, legacy ?t=, or default 0
function getTrackFromUrl() {
    const deepLink = window.BANDPROMO_DEEP_LINK || {};
    const releaseSlug = String(deepLink.release || '').trim().toLowerCase();
    const trackSlug = String(deepLink.track || '').trim().toLowerCase();
    if (releaseSlug && trackSlug && Array.isArray(playList) && playList.length > 0) {
        const matchedIndex = playList.findIndex((song) => {
            return String(song.release_slug || '').toLowerCase() === releaseSlug
                && String(song.track_slug || '').toLowerCase() === trackSlug;
        });
        if (matchedIndex >= 0) {
            return matchedIndex;
        }
    }

    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const playIndex = pathParts.indexOf('play');
    if (playIndex >= 0 && pathParts.length >= playIndex + 4) {
        const pathRelease = String(pathParts[playIndex + 2] || '').toLowerCase();
        const pathTrack = String(pathParts[playIndex + 3] || '').toLowerCase();
        const matchedIndex = playList.findIndex((song) => {
            return String(song.release_slug || '').toLowerCase() === pathRelease
                && String(song.track_slug || '').toLowerCase() === pathTrack;
        });
        if (matchedIndex >= 0) {
            return matchedIndex;
        }
    }
    if (playIndex >= 0 && pathParts.length === playIndex + 3) {
        const pathTrack = String(pathParts[playIndex + 2] || '').toLowerCase();
        const matchedIndex = playList.findIndex((song) => {
            return String(song.track_slug || '').toLowerCase() === pathTrack;
        });
        if (matchedIndex >= 0) {
            return matchedIndex;
        }
    }

    const params = new URLSearchParams(window.location.search);
    const track = params.get('t');
    if (track !== null) {
        const trackNum = parseInt(track, 10);
        if (!isNaN(trackNum) && trackNum > 0) {
            return trackNum - 1;
        }
    }
    return 0;
}

function getActivePlaylistId() {
    return String(window.BANDPROMO_PLAYLIST_ID || 'bandpromo-demo');
}

function getActivePlaylistSlug() {
    return String(window.BANDPROMO_PLAYLIST_SLUG || getActivePlaylistId() || 'bandpromo-demo');
}

function playlistSlugForId(playlistId) {
    const id = String(playlistId || '').trim();
    if (!id) {
        return getActivePlaylistSlug();
    }
    const catalog = Array.isArray(window.BANDPROMO_PLAYLIST_CATALOG) ? window.BANDPROMO_PLAYLIST_CATALOG : [];
    const match = catalog.find((entry) => String(entry?.id || '') === id);
    return String(match?.slug || id);
}

function buildPlaylistPlayerUrl(_playlistId, song) {
    const playlist = encodeURIComponent(getActivePlaylistSlug());
    if (!song || !song.track_slug) {
        return `/play/${playlist}`;
    }
    return `/play/${playlist}/${encodeURIComponent(song.track_slug)}`;
}

function updatePlaylistHistory(song) {
    if (!song || !history.replaceState) {
        return;
    }
    const nextUrl = buildPlaylistPlayerUrl(getActivePlaylistId(), song);
    history.replaceState(null, '', nextUrl);
}

function bindPlaylistSelector() {
    const selector = document.getElementById('playlistSelector');
    if (!selector || selector.dataset.bound === '1') {
        return;
    }
    selector.dataset.bound = '1';
    selector.addEventListener('change', async () => {
        const playlistId = String(selector.value || '').trim();
        if (!playlistId || playlistId === getActivePlaylistId()) {
            return;
        }
        window.BANDPROMO_PLAYLIST_ID = playlistId;
        window.BANDPROMO_PLAYLIST_SLUG = playlistSlugForId(playlistId);
        window.CONFIG_URL = `/biblioteca/get-player-playlist.php?playlist=${encodeURIComponent(playlistId)}`;
        window.BANDPROMO_DEEP_LINK = { release: '', track: '' };
        if (history.replaceState) {
            history.replaceState(null, '', `/play/${encodeURIComponent(getActivePlaylistSlug())}`);
        }
        await loadConfig();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Test Download Speed and Select Quality Variant

function showPlayerLoadError(message) {
    if (loadingMsg) {
        loadingMsg.style.display = 'block';
    }
    if (mediaPlayerEl) {
        mediaPlayerEl.style.display = 'none';
    }
    if (songTitle) songTitle.innerText = 'Error';
    if (artistName) artistName.innerText = 'Check setup';
    if (lyricsBox) lyricsBox.innerText = message || 'Could not load playlist.';
}

async function loadConfig() {
    if (window.location.protocol === 'file:') {
        showPlayerLoadError('Open http://localhost:8000/play/ after starting the PHP dev server.');
        return;
    }

    try {
        const configUrl = window.CONFIG_URL || `../${PATH_VARIANT}/playlist.json`;
        const response = await fetch(configUrl);
        if (!response.ok) {
            let detail = `HTTP ${response.status}`;
            try {
                const errorPayload = await response.json();
                if (errorPayload && typeof errorPayload.error === 'string' && errorPayload.error.trim() !== '') {
                    detail = errorPayload.error.trim();
                }
            } catch (parseError) {
                // Keep status-only detail when the body is not JSON.
            }
            throw new Error(detail);
        }
        const data = await response.json();
        playList = Array.isArray(data) ? data : (Array.isArray(data.tracks) ? data.tracks : []);
        if (data.playlist_id) {
            window.BANDPROMO_PLAYLIST_ID = data.playlist_id;
        }
        if (data.playlist_slug) {
            window.BANDPROMO_PLAYLIST_SLUG = data.playlist_slug;
        }
        
        // Start player if we got data
        if (playList.length > 0) {
            currentIndex = getTrackFromUrl();
            if (currentIndex >= playList.length) {
                currentIndex = playList.findIndex((song) => song.playable !== false);
                if (currentIndex < 0) {
                    currentIndex = 0;
                }
            }
            initPlayer(currentIndex);
            renderPlaylist();
            if (playList[currentIndex]) {
                updatePlaylistHistory(playList[currentIndex]);
            }
            bindPlaylistSelector();
        } else {
            showPlayerLoadError('This playlist has no playable tracks yet.');
        }
    } catch (e) {
        console.error("Failed to load playlist.json:", e);
        showPlayerLoadError(e && e.message ? e.message : 'Could not load playlist.');
    }
}

// Setup for Visualizer
const canvas = document.getElementById('analyzer');
const ctx = canvas.getContext('2d');

// Init canvas size
function resizeCanvas() {
    canvas.width = canvas.parentElement.clientWidth;
    canvas.height = canvas.parentElement.clientHeight;
}
window.addEventListener('resize', resizeCanvas);
resizeCanvas();

// Visualizer Loop
function drawVisualizer() {
    requestAnimationFrame(drawVisualizer);
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    const bars = 20;
    const barWidth = canvas.width / bars;
    const isPlaying = !audioPlayer.paused;
    const time = Date.now() / 150; // Speed of movement

    for (let i = 0; i < bars; i++) {
        // Generate height: 
        // If playing: Combine sine waves and random noise
        // If paused: Low, breathing movement
        
        let height;
        
        if (isPlaying) {
            // Simulating "Beat" using modulo on time, plus random
            const wave = Math.sin(time + i * 0.5) * 20; // Base wave
            const noise = Math.random() * 30; // "Hi-hats" and noise
            height = 20 + wave + noise;
            height = Math.max(5, height); // Min height
        } else {
            // Calm "breathing" movement when paused
            height = 10 + Math.sin(time/4 + i * 0.5) * 5;
        }

        // Color
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim();
        ctx.globalAlpha = 0.3; // Transparent
        
        // Draw bar (from bottom up)
        const x = i * barWidth;
        const y = canvas.height - height;
        
        // Rounded tops
        ctx.beginPath();
        ctx.roundRect(x + 2, y, barWidth - 4, height, [5, 5, 0, 0]);
        ctx.fill();
    }
}
// Start animation
drawVisualizer();

// Update button text automatically based on playback status
audioPlayer.onplay = () => playBtn.innerText = "Pause";
audioPlayer.onpause = () => playBtn.innerText = "Play";

audioPlayer.addEventListener('pause', () => {
    scheduleAnalyticsSessionEnd();
    updateMediaSessionPlaybackState();
    if (document.hidden && wasPlayingBeforeVisibilityHidden) {
        resumeAfterVisibilityPause = true;
    }
});

// guard using expected duration from config
function checkExpected() {
    // Avoid auto-next checks while scrubbing and immediately after seek settles.
    if (isUserSeeking || (Date.now() - lastSeekFinishedAt) < 700) {
        return;
    }

    if (currentTrackChangeSource !== null && audioPlayer.currentTime > 0.25) {
        currentTrackChangeSource = null;
    }

    const exp = parseFloat(audioPlayer.dataset.expectedDuration);
    if (!isNaN(exp)) {
        if (audioPlayer.currentTime >= exp - 0.5) {
            // jump to next song once we hit or go past the configured length
            nextSong();
        }
    }
}
audioPlayer.addEventListener('timeupdate', checkExpected);
audioPlayer.addEventListener('timeupdate', updateMediaSessionPositionState);

audioPlayer.addEventListener('seeking', () => {
    isUserSeeking = true;
});

audioPlayer.addEventListener('seeked', () => {
    isUserSeeking = false;
    lastSeekFinishedAt = Date.now();
});

// warn if metadata duration differs
audioPlayer.addEventListener('loadedmetadata', () => {
    const exp = parseFloat(audioPlayer.dataset.expectedDuration);
    if (!isNaN(exp) && audioPlayer.duration > exp + 1) {
        console.warn('Metadata duration', audioPlayer.duration, 'differs from expected', exp);
    }
    updateMediaSessionPositionState();
});

audioPlayer.addEventListener('ratechange', updateMediaSessionPositionState);

// listen for errors (unsupported codec, network problems, etc.)
audioPlayer.addEventListener('error', e => {
    console.error('Audio playback error', e);
    const song = playList[currentIndex];
    const sourceFile = (song && song.file) || audioPlayer.dataset.sourceFile || '';
    if (!audioVariantFallbackAttempted && PATH_VARIANT === 'optimal' && sourceFile) {
        PATH_VARIANT = 'original';
        setAudioSrc(sourceFile);
        audioVariantFallbackAttempted = true;
        audioPlayer.play().catch(() => {});
        return;
    }
    // give user some visible feedback if it happens during interaction
    const deliveryFile = audioPlayer.dataset.deliveryFile || sourceFile || '';
    if (deliveryFile) {
        const prefix = currentTrackChangeSource === 'auto_next'
            ? 'Playback stopped while switching to the next track.'
            : 'Playback stopped unexpectedly.';
        alert(prefix + ' Streamed file: ' + deliveryFile + '.');
    }
    currentTrackChangeSource = null;
});

// Log when track starts playing (or resumes from pause)
audioPlayer.addEventListener('play', () => {
    // Remove pulse guide when music actually starts (any source)
    removePulseGuide();
    resumeAfterVisibilityPause = false;
    updateMediaSessionPlaybackState();
    updateMediaSessionPositionState();

    const actionSource = pendingPlayActionSource || 'player';
    pendingPlayActionSource = null;
    ensureAnalyticsSession(actionSource);

    // Some browsers fire repeated play events around seek/buffer transitions.
    // Debounce to prevent noisy duplicate track_resumed logs.
    const now = Date.now();
    if ((now - lastPlayLogAt) < 800) {
        return;
    }
    lastPlayLogAt = now;

    const isStart = !hasStartedCurrentTrack || audioPlayer.currentTime < 0.5;
    if (isStart) {
        hasStartedCurrentTrack = true;
    }

    logActivity(isStart ? 'track_started' : 'track_resumed', {
        index: currentIndex,
        title: playList[currentIndex]?.title,
        artist: playList[currentIndex]?.artist,
        currentTime: audioPlayer.currentTime
    });
});

// Log when track ends naturally (not skipped)
audioPlayer.addEventListener('ended', () => {
    resumeAfterVisibilityPause = false;
    wasPlayingBeforeVisibilityHidden = false;
    updateMediaSessionPlaybackState();
    logTrackExit('ended', 'auto');

    currentTrackChangeSource = 'auto_next';
    pendingPlayActionSource = 'auto_next';
    // Auto-play next song when current track ends
    triggerSongChange('next');
});

let audioVariantFallbackAttempted = false;

// helper to safely set audio source with encoding and support check
function setAudioSrc(filename) {
    audioVariantFallbackAttempted = false;
    // Build full path based on variant and configured media base
    const deliveryFilename = resolveAudioDeliveryFilename(filename);
    const url = buildAudioUrl(filename);
    
    // url may contain spaces or brackets; encode for use in src attribute
    const encoded = encodeURI(url);
    audioPlayer.dataset.sourceFile = filename;
    audioPlayer.dataset.deliveryFile = deliveryFilename;
    audioPlayer.src = encoded;
    hasStartedCurrentTrack = false;
    isUserSeeking = false;
    resumeAfterVisibilityPause = false;

    // set expected duration from playlist (caller should set before calling)
    const song = playList[currentIndex];
    if (song && song.duration != null) {
        audioPlayer.dataset.expectedDuration = song.duration;
    }

    updateMediaSessionMetadata();

    // Detect unsupported formats against the actual delivery file, not the source filename.
    const mime = getAudioMimeType(deliveryFilename);
    if (mime && audioPlayer.canPlayType && audioPlayer.canPlayType(mime) === '') {
        console.warn('Browser cannot play format', mime, '(track ' + url + ')');
        alert('Sorry - your device does not support playing ' + deliveryFilename + ' in the current delivery format.');
    }
}

function findNextPlayableIndex(startIndex, direction) {
    const len = playList.length;
    if (len === 0) {
        return -1;
    }
    for (let step = 1; step <= len; step += 1) {
        const idx = direction === 'next'
            ? (startIndex + step) % len
            : (startIndex - step + len) % len;
        if (playList[idx] && playList[idx].playable !== false) {
            return idx;
        }
    }
    return startIndex;
}

// Function to init first song (no animation)
function initPlayer(index) {
    updateVisuals(index);
    const song = playList[index];
    if (song && song.playable !== false && song.file) {
        setAudioSrc(song.file);
    } else {
        audioPlayer.removeAttribute('src');
        audioPlayer.pause();
    }
}

// Update visuals (Text, Images, Side-covers)
function updateVisuals(index) {
    const song = playList[index];
    
    // Main info
    songTitle.innerText = song.title;
    artistName.innerText = song.artist;
    lyricsBox.innerText = hasDisplayableLyrics(song) ? song.lyrics : '';
    lyricsBox.scrollTop = 0; // Reset scroll position to top

    // Build cover path
    setImageWithFallback(coverImage, song.cover);
    setImageWithFallback(reflectionImage, song.cover);
    syncLyricsTab(song);

    // Scroll page to top
    window.scrollTo(0, 0);

    // Update ghost covers
    const prevIndex = (index - 1 + playList.length) % playList.length;
    const nextIndex = (index + 1) % playList.length;

    setImageWithFallback(prevCover, playList[prevIndex].cover);
    setImageWithFallback(nextCover, playList[nextIndex].cover);

    // Update playlist view if it's currently visible
    const playlistBox = document.getElementById('playlistBox');
    if (playlistBox.classList.contains('active')) {
        renderPlaylist();
    }
}

// Guard: prevents double/triple firing from checkExpected + ended + pause events
let isChangingSong = false;

function applySongChange(newIndex, direction) {
    const song = playList[newIndex];
    if (!song || song.playable === false) {
        isChangingSong = false;
        return;
    }
    currentIndex = newIndex;
    audioPlayer.autoplay = true;
    setAudioSrc(song.file);
    pendingPlayActionSource = pendingPlayActionSource || (direction === 'next' ? 'auto_next' : 'auto_prev');
    currentTrackChangeSource = pendingPlayActionSource;

    const playlistItems = document.querySelectorAll('.playlist-item');
    playlistItems.forEach((item, index) => {
        if (index === newIndex) {
            item.classList.add('current');
        } else {
            item.classList.remove('current');
        }
    });

    audioPlayer.play().catch(() => {
        // Hidden/background transitions can still be blocked by the browser.
    });
    updatePlaylistHistory(playList[currentIndex]);
    isChangingSong = false;
}

// Main function for song change with animation
function triggerSongChange(direction) {
    isChangingSong = true;
    // 1. Pause current song immediately
    audioPlayer.pause();

    // 2. Calculate next index (skip embargoed / locked tracks)
    let newIndex;
    if (direction === 'next') {
        newIndex = findNextPlayableIndex(currentIndex, 'next');
    } else {
        newIndex = findNextPlayableIndex(currentIndex, 'prev');
    }
    if (newIndex < 0 || playList[newIndex]?.playable === false) {
        isChangingSong = false;
        return;
    }

    if (document.hidden && direction === 'next') {
        updateVisuals(newIndex);
        applySongChange(newIndex, direction);
        return;
    }

    // 3. Determine animation class based on direction
    const animClass = direction === 'next' ? 'spin-left' : 'spin-right';

    // 4. Start animation (reset class list first to re-trigger)
    // Main card
    cardWrapper.classList.remove('spin-right', 'spin-left');
    void cardWrapper.offsetWidth; // Force reflow
    cardWrapper.classList.add(animClass);

    // Side cards (images) - Fade out/in animation
    prevCover.classList.remove('side-anim');
    nextCover.classList.remove('side-anim');
    void prevCover.offsetWidth; // Force reflow for side cards
    prevCover.classList.add('side-anim');
    nextCover.classList.add('side-anim');

    // 5. Halfway through animation (400ms): Swap visual content
    setTimeout(() => {
        updateVisuals(newIndex);
    }, 400);

    // 6. When animation is done (800ms): Load new audio and play
    setTimeout(() => {
        applySongChange(newIndex, direction);
    }, 800);
}

function nextSong() {
    if (isChangingSong) return;
    logTrackExit('next_click', 'button');
    pendingPlayActionSource = 'button';
    triggerSongChange('next');
}

function prevSong() {
    if (isChangingSong) return;
    logTrackExit('prev_click', 'button');
    pendingPlayActionSource = 'button';
    triggerSongChange('prev');
}

function togglePlay() {
    if (playList.length === 0) return;
    const song = playList[currentIndex];
    if (!song || song.playable === false) {
        const playableIndex = findNextPlayableIndex(currentIndex, 'next');
        if (playableIndex >= 0 && playList[playableIndex]?.playable !== false) {
            currentIndex = playableIndex;
            initPlayer(currentIndex);
            renderPlaylist();
            updatePlaylistHistory(playList[currentIndex]);
        }
        return;
    }
    if (audioPlayer.paused) {
        pendingPlayActionSource = 'button';
        resumeAfterVisibilityPause = false;
        audioPlayer.play().catch(e => console.error(e));
    } else {
        resumeAfterVisibilityPause = false;
        wasPlayingBeforeVisibilityHidden = false;
        audioPlayer.pause();
        logActivity('play_pause', {
            ...getCurrentTrackSnapshot({ actionSource: 'button' })
        });
    }
    
    // Remove focus from button to clear :focus styling
    document.getElementById('playBtn').blur();
}

// Toggle between content views
function toggleView(view) {
    const lyricsBox = document.getElementById('lyricsBox');
    const playlistBox = document.getElementById('playlistBox');
    const buttons = document.querySelectorAll('.content-toggle button[data-view]');
    const contentBoxes = document.querySelectorAll('[data-content-box]');

    if (view === 'lyrics' && !hasDisplayableLyrics(playList[currentIndex])) {
        view = getPreferredPrimaryView(playList[currentIndex]);
    }

    if (lyricsBox) lyricsBox.classList.remove('active');
    if (playlistBox) playlistBox.classList.remove('active');
    contentBoxes.forEach((box) => box.classList.remove('active'));
    buttons.forEach((btn) => btn.classList.remove('active'));

    const targetBox = document.querySelector(`[data-content-box="${view}"]`);
    const targetButton = document.querySelector(`.content-toggle button[data-view="${view}"]`);

    if (targetBox) {
        targetBox.classList.add('active');
    }
    if (targetButton) {
        targetButton.classList.add('active');
    }

    if (view === 'playlist') {
        renderPlaylist();
    }
}

// Render the playlist
function renderPlaylist() {
    const playlistBox = document.getElementById('playlistBox');
    if (playList.length === 0) {
        playlistBox.innerHTML = '<p style="text-align: center; color: #aaa;">Playlist is empty</p>';
        return;
    }

    let html = '<div class="playlist-tracks">';
    playList.forEach((song, index) => {
        const isCurrentTrack = index === currentIndex ? 'current' : '';
        const isLocked = song.playable === false ? 'playlist-item--locked' : '';
        const lockLabel = song.playable === false ? '<span class="playlist-track-lock">Not available yet</span>' : '';
        
        const coverCandidates = buildCoverUrlCandidates(song.cover);
        const coverPath = coverCandidates[0] || '';
        
        // Parse title to extract track number and tale
        const titleParts = song.title.split('\n');
        const mainTitle = titleParts[0] || '';
        const taleName = titleParts[1] || '';
        
        html += `
            <div class="playlist-item ${isCurrentTrack} ${isLocked}" onclick="playTrackFromPlaylist(${index})">
                <img src="${coverPath}" alt="${mainTitle}" class="playlist-track-cover">
                <div class="playlist-track-content">
                    <h5 class="playlist-track-title">${mainTitle} <span class="playlist-track-tale">${taleName}</span></h5>
                    <p class="playlist-track-description">${song.description || ''}${lockLabel}</p>
                </div>
            </div>
        `;
    });
    html += '</div>';
    playlistBox.innerHTML = html;
    playlistBox.querySelectorAll('.playlist-track-cover').forEach((img, index) => {
        setImageWithFallback(img, playList[index]?.cover);
    });
}

// Play a track from the playlist
function playTrackFromPlaylist(index) {
    const song = playList[index];
    if (!song) {
        return;
    }
    if (song.playable === false) {
        return;
    }
    if (index === currentIndex) {
        // If clicking the current track, just toggle play/pause
        togglePlay();
    } else {
        // Log the track being interrupted before switching
        if (!audioPlayer.paused && audioPlayer.currentTime > 0) {
            logTrackExit('playlist_select', 'playlist');
        }
        currentIndex = index;
        updateVisuals(currentIndex);
        setAudioSrc(playList[currentIndex].file);
        pendingPlayActionSource = 'playlist';
        audioPlayer.play().catch(e => {
            // Autoplay error silently
        });
        logActivity('track_selected_from_playlist', {
            ...getCurrentTrackSnapshot({ actionSource: 'playlist' })
        });
    }
    updatePlaylistHistory(playList[currentIndex]);
    // Update the playlist view to show which track is now current
    renderPlaylist();

    // flip back to lyrics view so user sees the cover/info
    toggleView(getPreferredPrimaryView(playList[currentIndex]));

    // scroll the whole page to top so the cover is visible
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Lightbox — powered by biblioteca/lightbox.js ─────────────────────────
const _lb = window._lb = new Lightbox({
    overlayId:       'lightbox',
    imgId:           'lightboxImage',
    vidId:           'lightboxVideo',
    prevBtnId:       'lightboxPrev',
    nextBtnId:       'lightboxNext',
    contentSelector: '.lightbox-content',
});

function openLightbox(src, altText = '', type = 'image') {
    if (!lightbox || !lightboxImage) { console.error('❌ Lightbox elements not found!'); return; }
    _lb.open(src, altText, type);
}
function openLightboxAt(idx) { _lb.openAt(idx); }
function prevLightbox(e)     { _lb.prev(e); }
function nextLightbox(e)     { _lb.next(e); }
function closeLightbox()     { _lb.close(); }

// Expose to global scope for onclick attributes and gallery.js
window.openLightbox   = openLightbox;
window.openLightboxAt = openLightboxAt;
window.prevLightbox   = prevLightbox;
window.nextLightbox   = nextLightbox;
window.closeLightbox  = closeLightbox;

// Wrapper for opening album cover from playlist
function openAlbumCoverLightbox() {
    if (playList.length === 0) { console.error('❌ Playlist is empty!'); return; }
    openLightbox(buildCoverUrl(playList[currentIndex].cover), 'Album Cover');
}

function bindPageGalleryLightboxes() {
    document.querySelectorAll('.page-gallery').forEach((gallerySection) => {
        if (gallerySection.dataset.lightboxBound === 'true') {
            return;
        }

        gallerySection.dataset.lightboxBound = 'true';
        const items = [];

        gallerySection.querySelectorAll('.page-gallery-item').forEach((figure) => {
            const isVideo = figure.classList.contains('page-gallery-item--video');
            const video = figure.querySelector('video');
            const img = figure.querySelector('img');
            const caption = figure.querySelector('figcaption');
            const label = caption?.textContent?.trim() || '';

            if (isVideo && video) {
                const src = video.getAttribute('src') || '';
                if (!src) {
                    return;
                }
                items.push({
                    src,
                    type: 'video',
                    poster: video.getAttribute('poster') || '',
                    name: label,
                    alt: label,
                });
                return;
            }

            if (img) {
                const src = img.getAttribute('src') || '';
                if (!src) {
                    return;
                }
                items.push({
                    src,
                    type: 'image',
                    name: label || img.getAttribute('alt') || 'Gallery image',
                    alt: img.getAttribute('alt') || label || 'Gallery image',
                });
            }
        });

        if (items.length === 0) {
            return;
        }

        gallerySection.querySelectorAll('.page-gallery-item').forEach((figure, index) => {
            if (!items[index]) {
                return;
            }

            figure.style.cursor = 'pointer';
            const openItem = () => {
                _lb.setItems(items);
                _lb.openAt(index);
            };

            figure.addEventListener('click', openItem);
            figure.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openItem();
                }
            });
        });
    });
}

function bindPageLightboxes() {
    bindPageGalleryLightboxes();

    document.querySelectorAll('[data-page-id]').forEach((pageBox) => {
        if (pageBox.dataset.lightboxBound === 'true') {
            return;
        }

        pageBox.dataset.lightboxBound = 'true';
        pageBox.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLImageElement)) {
                return;
            }

            if (target.closest('.page-gallery-item')) {
                return;
            }

            const src = target.getAttribute('src');
            if (!src) {
                return;
            }

            openLightbox(src, target.getAttribute('alt') || 'Page image');
        });

        pageBox.querySelectorAll('img').forEach((img) => {
            img.style.cursor = 'pointer';
        });
    });
}



// Add click listener to cover image to open lightbox
if (coverImage) {
    coverImage.addEventListener('click', openAlbumCoverLightbox);
    coverImage.style.cursor = 'pointer';
} else {
    console.error('❌ Cover image element not found!');
}

// Remove pulse guide animation when user first interacts with controls
function removePulseGuide() {
    playBtn.classList.remove('pulse-guide');
}

document.addEventListener('DOMContentLoaded', function() {
    bindPageLightboxes();

    if (debugPanelButton) {
        debugPanelButton.addEventListener('click', openDebugModal);
    }

    if (debugModalCloseButton) {
        debugModalCloseButton.addEventListener('click', closeDebugModal);
    }

    if (debugLogoutButton) {
        debugLogoutButton.addEventListener('click', logoutFromDebugPanel);
    }

    if (debugClearCacheButton) {
        debugClearCacheButton.addEventListener('click', () => {
            clearAppCache().catch(() => {});
        });
    }

    if (debugModal) {
        debugModal.addEventListener('click', (event) => {
            if (event.target === debugModal) {
                closeDebugModal();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && debugModal?.classList.contains('active')) {
            closeDebugModal();
        }
    });

    ['click', 'touchend', 'pointerup'].forEach((eventName) => {
        document.addEventListener(eventName, syncWideModeFullscreen, { passive: true });
    });

    window.addEventListener('resize', syncWideModeFullscreen);
    window.addEventListener('orientationchange', syncWideModeFullscreen);
    document.addEventListener('fullscreenchange', () => {
        wideModeFullscreenOwned = isMobileWideMode() && !!getFullscreenElement();
    });

    syncWideModeFullscreen();

    // Add pulse guide to play button after initial load
    setTimeout(() => {
        playBtn.classList.add('pulse-guide');
    }, 500);
});

// Set quality based on login speed test result (cached in sessionStorage)
function determineQuality() {
    const explicitQuality = sessionStorage.getItem('bandpromo_selected_quality');
    if (explicitQuality === 'high') {
        PATH_VARIANT = 'original';
        return;
    }
    if (explicitQuality === 'low') {
        PATH_VARIANT = 'optimal';
        return;
    }

    if (window.BANDPROMO_PREFERRED_AUDIO_VARIANT === 'original' || window.BANDPROMO_PREFERRED_AUDIO_VARIANT === 'optimal') {
        PATH_VARIANT = window.BANDPROMO_PREFERRED_AUDIO_VARIANT;
        return;
    }

    // Check if speed test was run on login page
    const cached = sessionStorage.getItem('connection_speed');
    if (cached) {
        const data = JSON.parse(cached);
        // HQ album is 687MB. 20 Mbps = ~4.5 min (wifi/fiber)
        // Protects mobile users from consuming entire data quota
        const SPEED_THRESHOLD_MBPS = 20;
        PATH_VARIANT = data.speed < SPEED_THRESHOLD_MBPS ? 'optimal' : 'original';
    } else {
        // No speed test result: default to optimal for safety
        PATH_VARIANT = 'optimal';
    }
}

// Start by determining quality, then loading config
(async () => {
    determineQuality();
    loadConfig();
    logEnvironmentSnapshot('player_loaded');
})();

// Log session_end when the page unloads or when analytics inactivity ends the current session.
// Only include track progress if audio is actively playing at shutdown.
function buildSessionEndData(actionSource, includeTrackProgress) {
    if (!includeTrackProgress) {
        return { actionSource };
    }

    const isActivelyPlaying = !audioPlayer.paused && !audioPlayer.ended && audioPlayer.currentTime > 0;
    if (!isActivelyPlaying) {
        return { actionSource };
    }

    return getCurrentTrackSnapshot({ actionSource });
}

function logSessionEnd({ actionSource = 'pagehide', useBeacon = false } = {}) {
    if (!analyticsSessionActive || playList.length === 0) return;

    analyticsSessionActive = false;
    clearAnalyticsInactivityTimer();

    const trackData = buildSessionEndData(actionSource, true);

    if (!useBeacon) {
        logActivity('session_end', trackData);
        return;
    }

    const payload = JSON.stringify({
        activity: 'session_end',
        data: {
            track_title: trackData.title || null,
            track_artist: trackData.artist || null,
            track_index: trackData.index ?? null,
            current_time: trackData.currentTime || null,
            duration: trackData.duration || null,
            quality: PATH_VARIANT || 'unknown',
            completion_rate: trackData.completionRate || null,
            action_source: trackData.actionSource || null,
            exit_reason: null
        }
    });
    navigator.sendBeacon('/biblioteca/log.php?action=log', new Blob([payload], { type: 'application/json' }));
}

window.addEventListener('beforeunload', () => logSessionEnd({ actionSource: 'pagehide', useBeacon: true }));
window.addEventListener('pagehide', () => logSessionEnd({ actionSource: 'pagehide', useBeacon: true }));
window.addEventListener('resize', () => scheduleEnvironmentSnapshot('resize'));
window.addEventListener('orientationchange', () => scheduleEnvironmentSnapshot('orientationchange'));
window.addEventListener('fullscreenchange', () => scheduleEnvironmentSnapshot('fullscreenchange'));
window.addEventListener('pageshow', () => scheduleEnvironmentSnapshot('pageshow'));
window.addEventListener('online', () => scheduleEnvironmentSnapshot('online'));
window.addEventListener('offline', () => scheduleEnvironmentSnapshot('offline'));
window.visualViewport?.addEventListener('resize', () => scheduleEnvironmentSnapshot('visualviewport_resize'));
document.addEventListener('visibilitychange', handleVisibilityPlaybackChange);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        scheduleEnvironmentSnapshot('visibility_return');
    }
});

installMediaSessionHandlers();
