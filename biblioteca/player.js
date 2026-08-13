// --- APPLICATION LOGIC ---

let playList = []; // Loaded from get-player-playlist.php
let currentIndex = 0;
let brandStylesById = {};
let PATH_VARIANT = 'optimal'; // Will be set by speed test (HQ or optimal), defaults to safe optimal
const IMAGE_PATH_VARIANT = 'optimal';
const TRACK_END_GUARD_EPSILON_SECONDS = 0.02;
const TRACK_END_AUTONEXT_COOLDOWN_MS = 1200;
let lastAutoNextGuardAt = 0;

function isTrackPlayable(song) {
    if (!song) {
        return false;
    }
    if (song.playable !== false) {
        return true;
    }
    return Boolean(window.BANDPROMO_IS_OPERATOR && song.embargoed === true);
}

function playerMarkdownApi() {
    return window.bandpromoPlayerMarkdown || null;
}

function escapePlayerHtml(text) {
    const api = playerMarkdownApi();
    if (api && typeof api.escapeHtml === 'function') {
        return api.escapeHtml(text);
    }
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function renderPlayerMarkdown(text, mode) {
    const api = playerMarkdownApi();
    if (!api || typeof api.render !== 'function') {
        return escapePlayerHtml(text);
    }
    return api.render(text, { mode: mode || 'default' });
}

function setPlayerMarkdownHtml(element, text, mode) {
    if (!element) {
        return;
    }
    const rendered = renderPlayerMarkdown(text, mode);
    if (rendered === '') {
        element.innerHTML = '';
        return;
    }
    element.innerHTML = rendered;
}

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

function isMediaOrAbsoluteUrl(value) {
    return /^(https?:)?\/\//i.test(value) || value.indexOf('/media/') === 0;
}

function songCoverRef(song) {
    if (!song) {
        return '';
    }
    const coverUrl = typeof song.cover_url === 'string' ? song.cover_url.trim() : '';
    if (coverUrl !== '') {
        return coverUrl;
    }
    return typeof song.cover === 'string' ? song.cover : '';
}

function coverAssetIdFromFilename(filename) {
    const stem = String(filename || '').replace(/\.[^.]+$/, '');
    if (/^ast_[0-9A-HJKMNP-TV-Z]{20}$/i.test(stem)) {
        return stem;
    }
    return '';
}

function buildCoverUrl(rawCoverPath, variant = IMAGE_PATH_VARIANT) {
    if (!rawCoverPath) return '';
    if (isMediaOrAbsoluteUrl(rawCoverPath)) {
        return rawCoverPath;
    }
    const candidates = buildCoverUrlCandidates(rawCoverPath, variant);
    return candidates[0] || '';
}

function buildCoverUrlCandidates(rawCoverPath, preferredVariant = IMAGE_PATH_VARIANT) {
    if (!rawCoverPath) return [];
    if (isMediaOrAbsoluteUrl(rawCoverPath)) {
        return [rawCoverPath];
    }
    const filename = rawCoverPath.split('\\').pop().split('/').pop();
    const stem = filename.replace(/\.[^.]+$/, '');
    const extMatch = filename.match(/\.(png|jpe?g|webp)$/i);
    const ext = extMatch ? extMatch[1].toLowerCase() : 'jpg';
    const base = window.MEDIA_IMG_BASE != null ? window.MEDIA_IMG_BASE : '..';
    const preferred = preferredVariant === 'thumb' ? 'thumb' : 'optimal';
    const assetId = coverAssetIdFromFilename(filename);
    const candidates = [];
    if (assetId) {
        const visual = preferred === 'thumb'
            ? [
                `/media/visual/delivery/${assetId}/thumb.jpg`,
                `/media/visual/delivery/${assetId}/thumb.png`,
                `/media/visual/delivery/${assetId}/card.jpg`,
                `/media/visual/delivery/${assetId}/card.png`,
            ]
            : [
                `/media/visual/delivery/${assetId}/card.jpg`,
                `/media/visual/delivery/${assetId}/card.png`,
                `/media/visual/delivery/${assetId}/thumb.jpg`,
                `/media/visual/delivery/${assetId}/thumb.png`,
            ];
        candidates.push(
            ...visual,
            `/media/visual/master/${filename}`,
            `/media/visual/master/${assetId}.png`,
            `/media/visual/master/${assetId}.jpg`,
            `/media/visual/original/${filename}`,
        );
    }
    if (preferred === 'thumb') {
        candidates.push(
            `${base}/thumb/${stem}.jpg`,
            `${base}/optimal/${stem}.jpg`,
            `${base}/original/${filename}`,
            `${base}/original/${stem}.${ext}`,
            `${base}/original/${stem}.jpg`,
            `${base}/original/${stem}.png`,
        );
    } else {
        candidates.push(
            `${base}/optimal/${stem}.jpg`,
            `${base}/original/${filename}`,
            `${base}/original/${stem}.${ext}`,
            `${base}/original/${stem}.jpg`,
            `${base}/original/${stem}.png`,
        );
    }
    return candidates.filter((url, index, list) => list.indexOf(url) === index);
}

function prefersReducedMotion() {
    return Boolean(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
}

function setImageWithFallback(image, rawCoverPath, preferredVariant = IMAGE_PATH_VARIANT) {
    if (!image) return;
    const candidates = buildCoverUrlCandidates(rawCoverPath, preferredVariant);
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

function pauseCoverVideo() {
    if (!coverVideo) {
        return;
    }
    coverVideo.pause();
}

function playCoverVideoIfReady() {
    if (!coverVideo || coverVideo.hidden) {
        return;
    }
    const src = String(coverVideo.currentSrc || coverVideo.getAttribute('src') || '').trim();
    if (src === '') {
        return;
    }
    if (document.hidden || prefersReducedMotion()) {
        return;
    }

    const attempt = () => {
        if (!coverVideo || coverVideo.hidden || document.hidden || prefersReducedMotion()) {
            return;
        }
        const playPromise = coverVideo.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(() => {
                // Autoplay may wait for a gesture; retry on the next user play.
            });
        }
    };

    if (coverVideo.readyState >= 2) {
        attempt();
        return;
    }

    coverVideo.addEventListener('canplay', attempt, { once: true });
    coverVideo.addEventListener('loadeddata', attempt, { once: true });
    attempt();
}

let currentLivingCoverUrl = '';

function songHasLivingCover(song) {
    const animatedUrl = typeof song?.animated_cover === 'string' ? song.animated_cover.trim() : '';
    return animatedUrl !== '' && !prefersReducedMotion();
}

function isAudioActivelyPlaying() {
    return !!(audioPlayer && !audioPlayer.paused && !audioPlayer.ended);
}

function showStillCoverVisual() {
    if (coverImage) {
        coverImage.hidden = false;
        coverImage.classList.remove('cover-art--suppressed');
    }
    if (coverVideo) {
        coverVideo.hidden = true;
        coverVideo.classList.remove('cover-art-video--active');
        pauseCoverVideo();
    }
    if (cardWrapper) {
        cardWrapper.classList.remove('has-living-cover');
    }
}

function revealLivingCoverFrames() {
    if (!coverVideo || coverVideo.hidden || currentLivingCoverUrl === '') {
        return;
    }
    coverVideo.removeAttribute('poster');
    coverVideo.classList.add('cover-art-video--active');
    if (coverImage) {
        coverImage.hidden = true;
        coverImage.classList.add('cover-art--suppressed');
    }
    if (cardWrapper) {
        cardWrapper.classList.add('has-living-cover');
    }
}

function showLivingCoverVisual() {
    if (!coverVideo || currentLivingCoverUrl === '') {
        showStillCoverVisual();
        return;
    }

    // Attach the video source only when we actually need to play — avoids
    // multi-MB downloads on idle that starve the single-threaded PHP server.
    if (coverVideo.dataset.src !== currentLivingCoverUrl) {
        coverVideo.dataset.src = currentLivingCoverUrl;
    }
    if (coverVideo.getAttribute('src') !== currentLivingCoverUrl) {
        coverVideo.preload = 'auto';
        coverVideo.src = currentLivingCoverUrl;
        try {
            coverVideo.load();
        } catch (error) {
            // Ignore load() failures on older engines.
        }
    }

    // Keep the still cover underneath until frames are actually painting.
    if (coverImage) {
        coverImage.hidden = false;
        coverImage.classList.remove('cover-art--suppressed');
    }
    coverVideo.hidden = false;
    coverVideo.classList.add('cover-art-video--active');
    if (cardWrapper) {
        cardWrapper.classList.add('has-living-cover');
    }

    const onPlaying = () => {
        revealLivingCoverFrames();
    };
    coverVideo.addEventListener('playing', onPlaying, { once: true });
    if (!coverVideo.paused && coverVideo.readyState >= 2 && coverVideo.currentTime > 0.01) {
        revealLivingCoverFrames();
    }

    playCoverVideoIfReady();
}

function syncCoverPlaybackVisual() {
    const song = playList[currentIndex];
    if (!songHasLivingCover(song)) {
        showStillCoverVisual();
        return;
    }

    if (isAudioActivelyPlaying() && !document.hidden) {
        showLivingCoverVisual();
        return;
    }

    showStillCoverVisual();
}

function setCoverVisual(song) {
    const animatedUrl = typeof song?.animated_cover === 'string' ? song.animated_cover.trim() : '';
    currentLivingCoverUrl = animatedUrl !== '' && !prefersReducedMotion() ? animatedUrl : '';

    setImageWithFallback(reflectionImage, songCoverRef(song));

    if (!coverImage) {
        return;
    }

    setImageWithFallback(coverImage, songCoverRef(song));

    if (coverVideo) {
        if (currentLivingCoverUrl) {
            const posterUrl = buildCoverUrl(songCoverRef(song));
            if (posterUrl) {
                coverVideo.poster = posterUrl;
            } else {
                coverVideo.removeAttribute('poster');
            }

            // Remember the URL but do not download until play (showLivingCoverVisual).
            coverVideo.preload = 'none';
            if (coverVideo.dataset.src !== currentLivingCoverUrl) {
                coverVideo.dataset.src = currentLivingCoverUrl;
                coverVideo.removeAttribute('src');
                try {
                    coverVideo.load();
                } catch (error) {
                    // Ignore load() failures on older engines.
                }
            }
        } else {
            pauseCoverVideo();
            coverVideo.removeAttribute('src');
            coverVideo.removeAttribute('poster');
            coverVideo.preload = 'metadata';
            delete coverVideo.dataset.src;
            try {
                coverVideo.load();
            } catch (error) {
                // Ignore load() failures on older engines.
            }
        }
    }

    syncCoverPlaybackVisual();
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
    const textRole = String(song?.text_role || 'lyrics').trim().toLowerCase() === 'notes'
        ? 'notes'
        : 'lyrics';
    let panelLabel = String(window.BANDPROMO_LYRICS_LABEL || 'Lyrics').trim() || 'Lyrics';
    if (textRole === 'notes') {
        const notesLabel = String(song?.notes_label || '').trim();
        panelLabel = notesLabel !== '' ? notesLabel : 'Tracklist';
    }
    lyricsButton.textContent = panelLabel;

    lyricsButton.hidden = !hasLyrics;
    lyricsButton.disabled = !hasLyrics;
    lyricsButton.setAttribute('aria-hidden', hasLyrics ? 'false' : 'true');
    if (hasLyrics) {
        lyricsButton.removeAttribute('tabindex');
        lyricsButton.removeAttribute('title');
    } else {
        lyricsButton.setAttribute('tabindex', '-1');
        lyricsButton.setAttribute(
            'title',
            textRole === 'notes' ? `${panelLabel} is unavailable for this track` : 'Lyrics are unavailable for this track'
        );
    }

    if (!hasLyrics && lyricsBox.classList.contains('active')) {
        toggleView(getPreferredPrimaryView(song));
    }
}

const audioPlayer = document.getElementById('audioPlayer');
const audioSeek = document.getElementById('audioSeek');
const audioTimeCurrent = document.getElementById('audioTimeCurrent');
const audioTimeDuration = document.getElementById('audioTimeDuration');
const coverImage = document.getElementById('coverImage');
const coverVideo = document.getElementById('coverVideo');
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
let isScrubberDragging = false;
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

    const candidates = buildCoverUrlCandidates(songCoverRef(song));
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

function getAudioDurationSeconds() {
    const live = Number(audioPlayer?.duration);
    if (Number.isFinite(live) && live > 0) {
        return live;
    }
    const expected = Number(audioPlayer?.dataset?.expectedDuration);
    if (Number.isFinite(expected) && expected > 0) {
        return expected;
    }
    const playlistDuration = Number(playList[currentIndex]?.duration);
    if (Number.isFinite(playlistDuration) && playlistDuration > 0) {
        return playlistDuration;
    }
    return 0;
}

function syncAudioScrubber(force = false) {
    if (!audioSeek || !audioTimeCurrent || !audioTimeDuration) {
        return;
    }

    const duration = getAudioDurationSeconds();
    const current = Number(audioPlayer?.currentTime) || 0;
    const hasDuration = duration > 0;

    audioSeek.disabled = !hasDuration;
    if (hasDuration && (force || Number(audioSeek.max) !== duration)) {
        audioSeek.max = String(duration);
    }
    if (!isScrubberDragging || force) {
        audioSeek.value = String(hasDuration ? Math.min(current, duration) : 0);
    }
    audioTimeCurrent.textContent = formatTime(isScrubberDragging ? Number(audioSeek.value) || 0 : current);
    audioTimeDuration.textContent = formatTime(duration);
}

function seekAudioFromScrubber() {
    if (!audioSeek || audioSeek.disabled) {
        return;
    }
    const nextTime = Number(audioSeek.value);
    if (!Number.isFinite(nextTime)) {
        return;
    }
    isUserSeeking = true;
    audioPlayer.currentTime = Math.max(0, nextTime);
    if (audioTimeCurrent) {
        audioTimeCurrent.textContent = formatTime(nextTime);
    }
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
const LOG_HOT_ACTIVITIES = new Set(['play_start', 'track_started', 'track_exited', 'session_end']);
const LOG_FLUSH_MS = 5000;
const logEventBuffer = [];
let logFlushTimer = null;

function buildLogPayload(activity, trackData = null) {
    const extraData = trackData && typeof trackData === 'object' && !Array.isArray(trackData)
        ? Object.fromEntries(Object.entries(trackData).filter(([, value]) => value !== undefined))
        : {};

    return {
        activity,
        data: {
            track_title: trackData?.title || null,
            track_artist: trackData?.artist || null,
            track_index: trackData?.index ?? null,
            current_time: trackData?.currentTime || null,
            duration: trackData?.duration || null,
            quality: PATH_VARIANT || 'unknown',
            completion_rate: trackData?.completionRate || null,
            exit_reason: trackData?.exitReason || null,
            action_source: trackData?.actionSource || null,
            ...extraData
        }
    };
}

function scheduleLogFlush() {
    if (logFlushTimer !== null) {
        return;
    }
    logFlushTimer = window.setTimeout(() => {
        flushLogBuffer().catch(() => {});
    }, LOG_FLUSH_MS);
}

async function flushLogBuffer({ useBeacon = false } = {}) {
    if (logFlushTimer !== null) {
        clearTimeout(logFlushTimer);
        logFlushTimer = null;
    }
    if (logEventBuffer.length === 0) {
        return true;
    }

    const batch = logEventBuffer.splice(0);
    const body = JSON.stringify({ events: batch });

    if (useBeacon && navigator.sendBeacon) {
        return navigator.sendBeacon(
            '/biblioteca/log.php?action=log',
            new Blob([body], { type: 'application/json' })
        );
    }

    try {
        const response = await fetch('/biblioteca/log.php?action=log', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body,
        });
        return response.ok;
    } catch (error) {
        return false;
    }
}

async function logActivity(activity, trackData = null) {
    const payload = buildLogPayload(activity, trackData);

    if (LOG_HOT_ACTIVITIES.has(activity)) {
        await flushLogBuffer();
        try {
            const response = await fetch('/biblioteca/log.php?action=log', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            if (!response.ok) {
                // Logging failed silently
            }
        } catch (error) {
            // Logging error silently
        }
        return;
    }

    logEventBuffer.push(payload);
    scheduleLogFlush();
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

function applyPlaylistBrand(brandId) {
    let resolvedBrandId = String(brandId || window.BANDPROMO_PLAYLIST_BRAND_ID || '').trim();
    if (!resolvedBrandId) {
        resolvedBrandId = String(window.BANDPROMO_ACTIVE_BRAND_ID || '').trim();
    }
    if (!resolvedBrandId) {
        return;
    }
    window.BANDPROMO_PLAYLIST_BRAND_ID = resolvedBrandId;
    const brand = brandStylesById[resolvedBrandId];
    if (!brand || typeof brand !== 'object') {
        return;
    }

    const root = document.documentElement;
    const vars = (brand.css_variables && typeof brand.css_variables === 'object')
        ? brand.css_variables
        : null;
    if (vars) {
        Object.entries(vars).forEach(([key, value]) => {
            if (typeof value !== 'string' || value.trim() === '') {
                return;
            }
            if (key === 'font-family') {
                root.style.fontFamily = value;
                return;
            }
            root.style.setProperty(key, value);
        });

        const themeMeta = document.querySelector('meta[name="theme-color"]');
        if (themeMeta && typeof vars['--bg-color'] === 'string' && vars['--bg-color'].trim() !== '') {
            themeMeta.setAttribute('content', vars['--bg-color'].trim());
        }
    }

    applyPlaylistShellMedia(brand);
}

function installShellBaseline() {
    const baseline = window.BANDPROMO_INSTALL_SHELL_MEDIA;
    if (baseline && typeof baseline === 'object') {
        return {
            logo: String(baseline.logo || '').trim(),
            background_image: String(baseline.background_image || '').trim(),
            background_video: String(baseline.background_video || '').trim(),
        };
    }
    const media = (window.appConfig && window.appConfig.media) || {};
    return {
        logo: String(media.logo || '').trim(),
        background_image: String(media.background_image || '').trim(),
        background_video: String(media.background_video || '').trim(),
    };
}

function applyPlaylistShellMedia(brand) {
    const assets = (brand && brand.assets && typeof brand.assets === 'object') ? brand.assets : {};
    const baseline = installShellBaseline();
    const next = {
        logo: String(assets.logo || '').trim() || baseline.logo,
        background_image: String(assets.background_image || '').trim() || baseline.background_image,
        background_video: String(assets.background_video || '').trim() || baseline.background_video,
    };

    window.appConfig = window.appConfig || {};
    window.appConfig.media = Object.assign({}, window.appConfig.media || {}, next);

    const logoImg = document.querySelector('.content-logo-img');
    if (logoImg && next.logo) {
        if (logoImg.getAttribute('src') !== next.logo) {
            logoImg.setAttribute('src', next.logo);
        }
        if (brand && brand.title) {
            logoImg.setAttribute('alt', String(brand.title));
        }
    }

    const bgVideo = document.getElementById('bg-video');
    if (bgVideo) {
        if (next.background_video) {
            bgVideo.setAttribute('data-src', next.background_video);
        } else {
            bgVideo.removeAttribute('data-src');
        }
    }

    if (window.bandpromoShellBackground && typeof window.bandpromoShellBackground.updateBackground === 'function') {
        window.bandpromoShellBackground.updateBackground({ force: true });
    }
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

function syncPlaylistSelectorUi(playlistId) {
    const id = String(playlistId || '').trim();
    const select = document.getElementById('playlistSelector');
    if (select && select.value !== id) {
        select.value = id;
    }

    document.querySelectorAll('[data-playlist-select]').forEach((el) => {
        const isActive = String(el.getAttribute('data-playlist-id') || '') === id;
        el.classList.toggle('is-active', isActive);
        if (el.hasAttribute('aria-pressed')) {
            el.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        }
        if (el.hasAttribute('aria-selected')) {
            el.setAttribute('aria-selected', isActive ? 'true' : 'false');
        }
        const thumb = el.querySelector('.playlist-coverflow-thumb');
        if (thumb) {
            const size = isActive ? '100' : '70';
            thumb.setAttribute('width', size);
            thumb.setAttribute('height', size);
        }
    });

    syncPlaylistSelectorLayout(true);
}

function playlistSelectorScrollers() {
    const wrap = document.getElementById('playlistSelectorWrap');
    if (!wrap) {
        return [];
    }
    return Array.from(wrap.querySelectorAll('.playlist-coverflow, .playlist-selector-buttons'));
}

function syncPlaylistSelectorLayout(scrollActiveIntoView = false) {
    playlistSelectorScrollers().forEach((scroller) => {
        scroller.classList.remove('is-overflowing');
        const overflowing = scroller.scrollWidth > scroller.clientWidth + 1;
        scroller.classList.toggle('is-overflowing', overflowing);
    });

    if (!scrollActiveIntoView) {
        return;
    }

    const active = document.querySelector('#playlistSelectorWrap [data-playlist-select].is-active');
    if (!active || typeof active.scrollIntoView !== 'function') {
        return;
    }
    const scroller = active.closest('.playlist-coverflow, .playlist-selector-buttons');
    if (!scroller || !scroller.classList.contains('is-overflowing')) {
        return;
    }
    active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
}

async function switchActivePlaylist(playlistId) {
    const id = String(playlistId || '').trim();
    if (!id || id === getActivePlaylistId()) {
        return;
    }

    window.BANDPROMO_PLAYLIST_ID = id;
    window.BANDPROMO_PLAYLIST_SLUG = playlistSlugForId(id);
    window.CONFIG_URL = `/biblioteca/get-player-playlist.php?playlist=${encodeURIComponent(id)}`;
    window.BANDPROMO_DEEP_LINK = { release: '', track: '' };
    syncPlaylistSelectorUi(id);
    if (history.replaceState) {
        history.replaceState(null, '', `/play/${encodeURIComponent(getActivePlaylistSlug())}`);
    }
    await loadConfig();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function bindPlaylistSelector() {
    const wrap = document.getElementById('playlistSelectorWrap');
    if (!wrap || wrap.dataset.bound === '1') {
        return;
    }
    wrap.dataset.bound = '1';

    const select = document.getElementById('playlistSelector');
    if (select) {
        select.addEventListener('change', async () => {
            await switchActivePlaylist(select.value);
        });
    }

    wrap.addEventListener('click', async (event) => {
        const target = event.target instanceof Element
            ? event.target.closest('[data-playlist-select]')
            : null;
        if (!target || !wrap.contains(target)) {
            return;
        }
        event.preventDefault();
        await switchActivePlaylist(target.getAttribute('data-playlist-id'));
    });

    const scheduleLayout = () => {
        window.requestAnimationFrame(() => syncPlaylistSelectorLayout(true));
    };
    scheduleLayout();
    window.addEventListener('resize', scheduleLayout);
    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(scheduleLayout);
        observer.observe(wrap);
        playlistSelectorScrollers().forEach((scroller) => observer.observe(scroller));
    }
}

// Test Download Speed and Select Quality Variant

function playerLoadErrorCopy(rawMessage) {
    const detail = String(rawMessage || '').trim();
    const lower = detail.toLowerCase();
    const isOperator = !!window.BANDPROMO_IS_OPERATOR;

    if (window.location.protocol === 'file:') {
        return {
            title: 'Open the site in a browser',
            detail: 'This player only works over the website, not as a downloaded HTML file.',
            operator: isOperator
                ? 'Local development: start the PHP site and open /play/ in your browser.'
                : '',
        };
    }

    if (lower.includes('not been published') || lower.includes('system → publish') || lower.includes('system -> publish')) {
        return {
            title: 'Music isn\'t ready yet',
            detail: 'This playlist can\'t be played right now. Please try again later.',
            operator: isOperator
                ? 'Operator: run System → Rebuild all deliverables so player playlists are published.'
                : '',
        };
    }

    if (lower.includes('no playable tracks') || lower.includes('not available yet')) {
        return {
            title: 'Nothing to play yet',
            detail: 'This playlist isn\'t available right now. Please try again later.',
            operator: isOperator
                ? 'Operator: check Content → Playlists and System → Deliverables.'
                : '',
        };
    }

    if (lower.includes('authentication') || lower.includes('http 401')) {
        return {
            title: 'Sign in required',
            detail: 'Your session expired. Sign in again to keep listening.',
            operator: '',
        };
    }

    return {
        title: 'Music isn\'t ready yet',
        detail: 'This playlist can\'t be played right now. Please try again later.',
        operator: isOperator && detail
            ? `Operator detail: ${detail}`
            : (isOperator ? 'Operator: check System → Deliverables and the build log.' : ''),
    };
}

function showPlayerLoadError(message) {
    const copy = playerLoadErrorCopy(message);
    const titleEl = document.getElementById('loading-msg-title');
    const detailEl = document.getElementById('loading-msg-detail');
    const operatorEl = document.getElementById('loading-msg-operator');

    if (titleEl) {
        titleEl.textContent = copy.title;
    }
    if (detailEl) {
        detailEl.textContent = copy.detail;
    }
    if (operatorEl) {
        if (copy.operator) {
            operatorEl.textContent = copy.operator;
            operatorEl.hidden = false;
        } else {
            operatorEl.textContent = '';
            operatorEl.hidden = true;
        }
    }

    if (loadingMsg) {
        loadingMsg.style.display = 'block';
    }
    if (mediaPlayerEl) {
        mediaPlayerEl.style.display = 'none';
    }
}

function playlistLockLabel(song) {
    if (!song || isTrackPlayable(song)) {
        return '';
    }
    if (song.lock_reason === 'delivery_pending') {
        return '<span class="playlist-track-lock">Awaiting publish build (streaming MP3 not ready)</span>';
    }
    if (song.lock_reason === 'embargoed') {
        return '<span class="playlist-track-lock">Not available yet</span>';
    }
    return '<span class="playlist-track-lock">Not available</span>';
}

function updateOperatorDeliveryNotice(summary) {
    const notice = document.getElementById('operatorDeliveryNotice');
    const noticeText = document.getElementById('operatorDeliveryNoticeText');
    if (!notice || !window.BANDPROMO_IS_OPERATOR) {
        return;
    }

    const pendingCount = Number(summary?.pending_count || 0);
    if (pendingCount <= 0) {
        notice.hidden = true;
        return;
    }

    if (noticeText) {
        noticeText.textContent = `${pendingCount} track${pendingCount === 1 ? '' : 's'} in this playlist need streaming MP3 delivery. Open System → Deliverables before listeners stream on mobile data.`;
    }
    notice.hidden = false;
}

async function loadConfig() {
    if (window.location.protocol === 'file:') {
        showPlayerLoadError('file-protocol');
        return;
    }

    try {
        const configUrl = window.CONFIG_URL;
        if (!configUrl) {
            throw new Error('Player playlist endpoint is not configured.');
        }
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
        brandStylesById = (data.brand_styles && typeof data.brand_styles === 'object') ? data.brand_styles : {};
        if (data.playlist_id) {
            window.BANDPROMO_PLAYLIST_ID = data.playlist_id;
        }
        if (data.playlist_slug) {
            window.BANDPROMO_PLAYLIST_SLUG = data.playlist_slug;
        }
        if (data.brand_id) {
            window.BANDPROMO_PLAYLIST_BRAND_ID = String(data.brand_id).trim();
        }
        updateOperatorDeliveryNotice(data.delivery_summary || null);
        applyPlaylistBrand(window.BANDPROMO_PLAYLIST_BRAND_ID);
        
        // Start player if we got data
        if (playList.length > 0) {
            currentIndex = getTrackFromUrl();
            if (currentIndex >= playList.length) {
                currentIndex = playList.findIndex((song) => isTrackPlayable(song));
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
            syncCampaignPageTabs();
            showPlayerLoadError('This playlist has no playable tracks yet.');
        }
    } catch (e) {
        console.error('Failed to load player playlist:', e);
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
    syncCoverPlaybackVisual();
    if (document.hidden && wasPlayingBeforeVisibilityHidden) {
        resumeAfterVisibilityPause = true;
    }
});

// Guard against browsers that miss the native `ended` event near the tail.
function checkExpected() {
    // Avoid auto-next checks while scrubbing and immediately after seek settles.
    if (isUserSeeking || (Date.now() - lastSeekFinishedAt) < 700) {
        return;
    }

    if (currentTrackChangeSource !== null && audioPlayer.currentTime > 0.25) {
        currentTrackChangeSource = null;
    }

    if (audioPlayer.paused || audioPlayer.ended || isChangingSong) {
        return;
    }

    const duration = Number(audioPlayer.duration);
    const now = Date.now();
    if (!Number.isFinite(duration) || duration <= 0 || (now - lastAutoNextGuardAt) < TRACK_END_AUTONEXT_COOLDOWN_MS) {
        return;
    }

    const remaining = duration - Number(audioPlayer.currentTime || 0);
    if (remaining <= TRACK_END_GUARD_EPSILON_SECONDS) {
        lastAutoNextGuardAt = now;
        logTrackExit('ended_guard', 'auto');
        currentTrackChangeSource = 'auto_next';
        pendingPlayActionSource = 'auto_next';
        triggerSongChange('next');
    }
}
audioPlayer.addEventListener('timeupdate', checkExpected);
audioPlayer.addEventListener('timeupdate', updateMediaSessionPositionState);
audioPlayer.addEventListener('timeupdate', () => syncAudioScrubber());

audioPlayer.addEventListener('seeking', () => {
    isUserSeeking = true;
});

audioPlayer.addEventListener('seeked', () => {
    isUserSeeking = false;
    lastSeekFinishedAt = Date.now();
    syncAudioScrubber(true);
});

// warn if metadata duration differs
audioPlayer.addEventListener('loadedmetadata', () => {
    const exp = parseFloat(audioPlayer.dataset.expectedDuration);
    if (!isNaN(exp) && audioPlayer.duration > exp + 1) {
        console.warn('Metadata duration', audioPlayer.duration, 'differs from expected', exp);
    }
    updateMediaSessionPositionState();
    syncAudioScrubber(true);
});

audioPlayer.addEventListener('durationchange', () => syncAudioScrubber(true));
audioPlayer.addEventListener('emptied', () => syncAudioScrubber(true));

if (audioSeek) {
    audioSeek.addEventListener('pointerdown', () => {
        isScrubberDragging = true;
    });
    audioSeek.addEventListener('pointerup', () => {
        isScrubberDragging = false;
        seekAudioFromScrubber();
    });
    audioSeek.addEventListener('pointercancel', () => {
        isScrubberDragging = false;
        syncAudioScrubber(true);
    });
    audioSeek.addEventListener('input', () => {
        isScrubberDragging = true;
        if (audioTimeCurrent) {
            audioTimeCurrent.textContent = formatTime(Number(audioSeek.value) || 0);
        }
    });
    audioSeek.addEventListener('change', () => {
        isScrubberDragging = false;
        seekAudioFromScrubber();
    });
}

audioPlayer.addEventListener('ratechange', updateMediaSessionPositionState);

// listen for errors (unsupported codec, network problems, etc.)
audioPlayer.addEventListener('error', e => {
    console.error('Audio playback error', e);
    const song = playList[currentIndex];
    const sourceFile = (song && song.file) || audioPlayer.dataset.sourceFile || '';
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
    syncCoverPlaybackVisual();
    primeNextTrackPreload(currentIndex);

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
    syncCoverPlaybackVisual();
    logTrackExit('ended', 'auto');

    currentTrackChangeSource = 'auto_next';
    pendingPlayActionSource = 'auto_next';
    // Auto-play next song when current track ends
    lastAutoNextGuardAt = Date.now();
    triggerSongChange('next');
});

function primeNextTrackPreload(startIndex) {
    const baseIndex = Number.isInteger(startIndex) ? startIndex : currentIndex;
    const nextIndex = findNextPlayableIndex(baseIndex, 'next');
    if (nextIndex < 0 || !playList[nextIndex] || !playList[nextIndex].file) {
        return;
    }
    const href = encodeURI(buildAudioUrl(playList[nextIndex].file));
    let link = document.getElementById('nextTrackAudioPreload');
    if (!(link instanceof HTMLLinkElement)) {
        link = document.createElement('link');
        link.id = 'nextTrackAudioPreload';
        link.rel = 'preload';
        link.as = 'audio';
        document.head.appendChild(link);
    }
    if (link.href !== href) {
        link.href = href;
    }
}

// helper to safely set audio source with encoding and support check
function setAudioSrc(filename) {
    // Build full path based on variant and configured media base
    const deliveryFilename = resolveAudioDeliveryFilename(filename);
    const url = buildAudioUrl(filename);
    
    // url may contain spaces or brackets; encode for use in src attribute
    const encoded = encodeURI(url);
    audioPlayer.dataset.sourceFile = filename;
    audioPlayer.dataset.deliveryFile = deliveryFilename;
    // Do not download media until Play — preload=metadata still triggers multi-MB
    // range GETs on audio.php and stalls thumbs on single-threaded PHP hosts.
    audioPlayer.preload = 'none';
    audioPlayer.src = encoded;
    hasStartedCurrentTrack = false;
    isUserSeeking = false;
    isScrubberDragging = false;
    resumeAfterVisibilityPause = false;

    // set expected duration from playlist (caller should set before calling)
    const song = playList[currentIndex];
    if (song && song.duration != null) {
        audioPlayer.dataset.expectedDuration = song.duration;
    } else {
        delete audioPlayer.dataset.expectedDuration;
    }
    syncAudioScrubber(true);

    updateMediaSessionMetadata();
    if (!audioPlayer.paused) {
        primeNextTrackPreload(currentIndex);
    }

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
        if (playList[idx] && isTrackPlayable(playList[idx])) {
            return idx;
        }
    }
    return startIndex;
}

// Function to init first song (no animation)
function initPlayer(index) {
    updateVisuals(index);
    const song = playList[index];
    if (song && isTrackPlayable(song) && song.file) {
        setAudioSrc(song.file);
    } else {
        audioPlayer.removeAttribute('src');
        audioPlayer.pause();
    }
}

// Campaign page tabs follow the current track's release_id (idle / no match → hide).
function syncCampaignPageTabs() {
    const tabs = Array.isArray(window.BANDPROMO_PLAYER_TABS) ? window.BANDPROMO_PLAYER_TABS : [];
    const song = Array.isArray(playList) && playList[currentIndex] ? playList[currentIndex] : null;
    const releaseId = song ? String(song.release_id || '').trim() : '';
    const activeBtn = document.querySelector('.content-toggle button[data-view].active');
    const activeView = activeBtn ? String(activeBtn.getAttribute('data-view') || '') : '';
    let activeHidden = false;

    tabs.forEach((tab) => {
        if (!tab || String(tab.kind || '') !== 'page') {
            return;
        }
        const view = String(tab.view || '');
        if (view === '') {
            return;
        }
        const pageRelease = String(tab.release_id || '').trim();
        const show = releaseId !== '' && pageRelease !== '' && pageRelease === releaseId;
        const btn = document.querySelector(`.content-toggle button[data-view="${view}"]`);
        const box = document.querySelector(`[data-content-box="${view}"]`);
        if (btn) {
            btn.hidden = !show;
            if (!show && view === activeView) {
                activeHidden = true;
            }
        }
        if (box) {
            box.hidden = !show;
            if (!show) {
                box.classList.remove('active');
            }
        }
    });

    if (activeHidden) {
        toggleView(getPreferredPrimaryView(song));
    }
}

// Update visuals (Text, Images, Side-covers)
function updateVisuals(index) {
    syncCampaignPageTabs();
    const song = playList[index];
    
    // Main info
    songTitle.innerText = song.title;
    artistName.innerText = song.artist;
    setPlayerMarkdownHtml(
        lyricsBox,
        hasDisplayableLyrics(song) ? song.lyrics : '',
        String(song?.text_role || 'lyrics').trim().toLowerCase() === 'notes' ? 'notes' : 'lyrics'
    );
    lyricsBox.scrollTop = 0; // Reset scroll position to top

    // Build cover path
    setCoverVisual(song);
    syncLyricsTab(song);

    // Scroll page to top
    window.scrollTo(0, 0);

    // Update ghost covers
    const prevIndex = (index - 1 + playList.length) % playList.length;
    const nextIndex = (index + 1) % playList.length;

    setImageWithFallback(prevCover, songCoverRef(playList[prevIndex]));
    setImageWithFallback(nextCover, songCoverRef(playList[nextIndex]));

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
    if (!song || !isTrackPlayable(song)) {
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
    if (newIndex < 0 || !isTrackPlayable(playList[newIndex])) {
        isChangingSong = false;
        return;
    }

    // 3. Audio and visuals first, animation second (non-blocking presentation only)
    updateVisuals(newIndex);
    applySongChange(newIndex, direction);

    const shouldAnimate = !document.hidden && !prefersReducedMotion();
    if (!shouldAnimate) {
        return;
    }

    // 4. Determine animation class based on direction
    const animClass = direction === 'next' ? 'spin-left' : 'spin-right';

    // 5. Start animation (reset class list first to re-trigger)
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
    if (!song || !isTrackPlayable(song)) {
        const playableIndex = findNextPlayableIndex(currentIndex, 'next');
        if (playableIndex >= 0 && isTrackPlayable(playList[playableIndex])) {
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

function revealActiveContentTab(button) {
    if (!(button instanceof HTMLElement) || typeof button.scrollIntoView !== 'function') {
        return;
    }
    const strip = button.closest('.content-toggle');
    if (!(strip instanceof HTMLElement) || strip.scrollWidth <= strip.clientWidth + 2) {
        return;
    }
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    button.scrollIntoView({
        inline: 'center',
        block: 'nearest',
        behavior: reducedMotion ? 'auto' : 'smooth',
    });
}

// Toggle between content views
function toggleView(view) {
    const lyricsBox = document.getElementById('lyricsBox');
    const playlistBox = document.getElementById('playlistBox');
    const playlistSelector = document.getElementById('playlistSelectorWrap');
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
        window.requestAnimationFrame(() => revealActiveContentTab(targetButton));
    }
    if (playlistSelector) {
        playlistSelector.hidden = view !== 'playlist';
        if (view === 'playlist') {
            window.requestAnimationFrame(() => syncPlaylistSelectorLayout(true));
        }
    }

    if (view === 'playlist') {
        renderPlaylist();
    }

    if (String(view).startsWith('page-')) {
        const pageBox = targetBox || document.querySelector(`[data-content-box="${view}"]`);
        if (pageBox) {
            ensurePlayerPageHydrated(pageBox);
        }
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
        const isLocked = !isTrackPlayable(song) ? 'playlist-item--locked' : '';
        const lockLabel = playlistLockLabel(song);

        const titleParts = String(song.title || '').split('\n');
        const mainTitle = escapePlayerHtml(titleParts[0] || '');
        const taleName = escapePlayerHtml(titleParts[1] || '');
        const descriptionHtml = renderPlayerMarkdown(song.description || '', 'default');

        html += `
            <div class="playlist-item ${isCurrentTrack} ${isLocked}" onclick="playTrackFromPlaylist(${index})">
                <img alt="${mainTitle}" class="playlist-track-cover" loading="lazy" decoding="async" width="70" height="70">
                <div class="playlist-track-content">
                    <h5 class="playlist-track-title">${mainTitle} <span class="playlist-track-tale">${taleName}</span></h5>
                    <div class="playlist-track-description player-markdown-host">${descriptionHtml}${lockLabel}</div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    playlistBox.innerHTML = html;
    playlistBox.querySelectorAll('.playlist-track-cover').forEach((img, index) => {
        setImageWithFallback(img, songCoverRef(playList[index]), 'thumb');
    });
}

// Play a track from the playlist
function playTrackFromPlaylist(index) {
    const song = playList[index];
    if (!song) {
        return;
    }
    if (!isTrackPlayable(song)) {
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
    openLightbox(buildCoverUrl(songCoverRef(playList[currentIndex])), 'Album Cover');
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
                const src = video.getAttribute('data-src') || video.getAttribute('src') || '';
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
            pageBox.querySelectorAll('img').forEach((img) => {
                img.style.cursor = 'pointer';
            });
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

const playerPageHydratePromises = new Map();

function ensurePlayerPageHydrated(pageBox) {
    if (!(pageBox instanceof HTMLElement)) {
        return Promise.resolve();
    }

    const pageId = String(pageBox.getAttribute('data-page-id') || '').trim();
    if (pageId === '') {
        return Promise.resolve();
    }

    if (pageBox.dataset.pageHydrated === 'true' || pageBox.dataset.pageHydrated === 'loading') {
        return playerPageHydratePromises.get(pageId) || Promise.resolve();
    }

    const existing = playerPageHydratePromises.get(pageId);
    if (existing) {
        return existing;
    }

    pageBox.dataset.pageHydrated = 'loading';

    const promise = fetch(`/biblioteca/get-player-page.php?page=${encodeURIComponent(pageId)}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })
        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            if (!ok || !data || data.ok !== true || typeof data.html !== 'string') {
                const message = (data && data.error) ? String(data.error) : 'This page could not be loaded.';
                pageBox.innerHTML = `<p class="page-paragraph">${escapePlayerHtml(message)}</p>`;
                pageBox.dataset.pageHydrated = 'error';
                return;
            }

            pageBox.innerHTML = data.html || '<p class="page-paragraph">This page is empty.</p>';
            pageBox.dataset.pageHydrated = 'true';
            bindPageLightboxes();
        })
        .catch(() => {
            pageBox.innerHTML = '<p class="page-paragraph">This page could not be loaded.</p>';
            pageBox.dataset.pageHydrated = 'error';
        });

    playerPageHydratePromises.set(pageId, promise);
    return promise;
}

// Add click listener to cover image to open lightbox
if (coverImage) {
    coverImage.addEventListener('click', openAlbumCoverLightbox);
    coverImage.style.cursor = 'pointer';
} else {
    console.error('❌ Cover image element not found!');
}

if (coverVideo) {
    coverVideo.addEventListener('click', openAlbumCoverLightbox);
    coverVideo.style.cursor = 'pointer';
}

// Remove pulse guide animation when user first interacts with controls
function removePulseGuide() {
    playBtn.classList.remove('pulse-guide');
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.updateBackground === 'function') {
        window.updateBackground();
    }

    const activeContentTab = document.querySelector('.content-toggle button[data-view].active');
    if (activeContentTab) {
        window.requestAnimationFrame(() => revealActiveContentTab(activeContentTab));
    }

    // If a page tab is the default view, hydrate it now so the panel is not stuck on “Loading…”.
    // Other page tabs hydrate on first open only (toggleView → ensurePlayerPageHydrated).
    document.querySelectorAll('[data-page-id].active[data-page-hydrated="false"]').forEach((pageBox) => {
        ensurePlayerPageHydrated(pageBox);
    });

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

    if (logFlushTimer !== null) {
        clearTimeout(logFlushTimer);
        logFlushTimer = null;
    }
    if (logEventBuffer.length > 0) {
        const batch = logEventBuffer.splice(0);
        navigator.sendBeacon(
            '/biblioteca/log.php?action=log',
            new Blob([JSON.stringify({ events: batch })], { type: 'application/json' })
        );
    }

    navigator.sendBeacon(
        '/biblioteca/log.php?action=log',
        new Blob([JSON.stringify(buildLogPayload('session_end', trackData))], { type: 'application/json' })
    );
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
    syncCoverPlaybackVisual();
});

installMediaSessionHandlers();
