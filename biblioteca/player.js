// --- APPLICATION LOGIC ---

let playList = []; // Will be loaded from playlist.json
let currentIndex = 0;
let PATH_VARIANT = 'optimal'; // Will be set by speed test (HQ or optimal), defaults to safe optimal
const IMAGE_PATH_VARIANT = 'optimal';

// Path helpers — use window.MEDIA_AUDIO_BASE / window.MEDIA_IMG_BASE when set
// (new /play/ structure), otherwise fall back to old sibling-folder relative paths.
function buildAudioUrl(filename) {
    // For optimal, swap .flac → .mp3 (optimized audio is always MP3)
    const f = PATH_VARIANT === 'optimal' ? filename.replace(/\.flac$/i, '.mp3') : filename;

    const params = new URLSearchParams({
        variant: PATH_VARIANT,
        file: f,
    });
    return `/biblioteca/audio.php?${params.toString()}`;
}

function buildCoverUrl(rawCoverPath) {
    if (!rawCoverPath) return '';
    const filename = rawCoverPath.split('\\').pop().split('/').pop();
    const name = filename.replace(/\.(png|jpe?g|webp)$/i, '.jpg');
    if (window.MEDIA_IMG_BASE != null) {
        return `${window.MEDIA_IMG_BASE}/${IMAGE_PATH_VARIANT}/${name}`;
    }
    return `../${IMAGE_PATH_VARIANT}/${name}`;
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
const lightbox = document.getElementById('lightbox');
const lightboxImage = document.getElementById('lightboxImage');

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

function isStandaloneDisplayMode() {
    return window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: window-controls-overlay)').matches ||
        window.matchMedia('(display-mode: fullscreen)').matches ||
        navigator.standalone === true;
}

function isMobileWideMode() {
    return window.matchMedia('(orientation: landscape)').matches &&
        window.innerWidth <= 1024 &&
        (navigator.maxTouchPoints > 0 || window.matchMedia('(pointer: coarse)').matches);
}

function getFullscreenElement() {
    return document.fullscreenElement || document.webkitFullscreenElement || null;
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

// Debug Logout Function
function debugLogout() {
    if (confirm('Logout and reset session? (Debug)')) {
        window.location.href = '/?logout=1';
    }
}

// Logging Function
async function logActivity(activity, trackData = null) {
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
                    action_source: trackData?.actionSource || null
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

// Get track number from URL parameter
function getTrackFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const track = params.get('t');
    if (track !== null) {
        const trackNum = parseInt(track, 10);
        if (!isNaN(trackNum) && trackNum > 0) {
            return trackNum - 1; // Convert to 0-based index (t=1 means index 0)
        }
    }
    return 0; // Default to first track
}

// Test Download Speed and Select Quality Variant

async function loadConfig() {
    try {
        const configUrl = window.CONFIG_URL || `../${PATH_VARIANT}/playlist.json`;
        const response = await fetch(configUrl);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        playList = data;
        
        // Start player if we got data
        if (playList.length > 0) {
            currentIndex = getTrackFromUrl(); // Get track from URL or default to 0
            // Ensure the index is valid
            if (currentIndex >= playList.length) {
                currentIndex = 0;
            }
            initPlayer(currentIndex);
            renderPlaylist(); // Initialize playlist view
        } else {
            alert("Config file is empty!");
        }
    } catch (e) {
        console.error("Failed to load playlist.json:", e);
        loadingMsg.style.display = "block";
        songTitle.innerText = "Error";
        artistName.innerText = "Check Console";
        lyricsBox.innerText = "Could not load playlist.";
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
});

// guard using expected duration from config
function checkExpected() {
    // Avoid auto-next checks while scrubbing and immediately after seek settles.
    if (isUserSeeking || (Date.now() - lastSeekFinishedAt) < 700) {
        return;
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
});

// listen for errors (unsupported codec, network problems, etc.)
audioPlayer.addEventListener('error', e => {
    console.error('Audio playback error', e);
    // give user some visible feedback if it happens during interaction
    const track = playList[currentIndex] && playList[currentIndex].file;
    if (track) {
        alert('Unable to play ' + track + '. Your device may not support the file format or the URL may be invalid.');
    }
});

// Log when track starts playing (or resumes from pause)
audioPlayer.addEventListener('play', () => {
    // Remove pulse guide when music actually starts (any source)
    removePulseGuide();

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
    logTrackExit('ended', 'auto');
    
    // Auto-play next song when current track ends
    triggerSongChange('next');
});

// helper to safely set audio source with encoding and support check
function setAudioSrc(filename) {
    // Build full path based on variant and configured media base
    const url = buildAudioUrl(filename);
    
    // url may contain spaces or brackets; encode for use in src attribute
    const encoded = encodeURI(url);
    audioPlayer.src = encoded;
    hasStartedCurrentTrack = false;
    isUserSeeking = false;

    // set expected duration from playlist (caller should set before calling)
    const song = playList[currentIndex];
    if (song && song.duration != null) {
        audioPlayer.dataset.expectedDuration = song.duration;
    }

    // detect unsupported formats (iPhones notoriously do not support FLAC)
    const ext = filename.split('.').pop().toLowerCase();
    const mimeMap = {
        mp3: 'audio/mpeg',
        m4a: 'audio/mp4',
        ogg: 'audio/ogg',
        wav: 'audio/wav',
        flac: 'audio/flac',
        aac: 'audio/aac'
    };
    const mime = mimeMap[ext];
    if (mime && audioPlayer.canPlayType && audioPlayer.canPlayType(mime) === '') {
        console.warn('Browser cannot play format', mime, '(track ' + url + ')');
        // give the user feedback; in production you might swap to an MP3 fallback
        alert('Sorry – your device does not support playing "' + url + '".\n' +
              'Tell the person that gave you this link that you have an (probably expensive, but) useless device...');
    }
}

// Function to init first song (no animation)
function initPlayer(index) {
    updateVisuals(index);
    setAudioSrc(playList[index].file);
}

// Update visuals (Text, Images, Side-covers)
function updateVisuals(index) {
    const song = playList[index];
    
    // Build cover path
    const coverPath = buildCoverUrl(song.cover);
    
    // Main info
    songTitle.innerText = song.title;
    artistName.innerText = song.artist;
    lyricsBox.innerText = song.lyrics;
    lyricsBox.scrollTop = 0; // Reset scroll position to top
    coverImage.src = coverPath;
    reflectionImage.src = coverPath;

    // Scroll page to top
    window.scrollTo(0, 0);

    // Update ghost covers
    const prevIndex = (index - 1 + playList.length) % playList.length;
    const nextIndex = (index + 1) % playList.length;

    const prevCoverPath = buildCoverUrl(playList[prevIndex].cover);
    prevCover.src = prevCoverPath;

    const nextCoverPath = buildCoverUrl(playList[nextIndex].cover);
    nextCover.src = nextCoverPath;

    // Update playlist view if it's currently visible
    const playlistBox = document.getElementById('playlistBox');
    if (playlistBox.classList.contains('active')) {
        renderPlaylist();
    }
}

// Guard: prevents double/triple firing from checkExpected + ended + pause events
let isChangingSong = false;

// Main function for song change with animation
function triggerSongChange(direction) {
    isChangingSong = true;
    // 1. Pause current song immediately
    audioPlayer.pause();

    // 2. Calculate next index
    let newIndex;
    if (direction === 'next') {
        newIndex = (currentIndex + 1) % playList.length;
    } else {
        newIndex = (currentIndex - 1 + playList.length) % playList.length;
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
        currentIndex = newIndex;
        setAudioSrc(playList[currentIndex].file);
        pendingPlayActionSource = pendingPlayActionSource || (direction === 'next' ? 'auto_next' : 'auto_prev');
        
        // Update playlist highlight: remove 'current' from all items, add to new one
        const playlistItems = document.querySelectorAll('.playlist-item');
        playlistItems.forEach((item, index) => {
            if (index === newIndex) {
                item.classList.add('current');
            } else {
                item.classList.remove('current');
            }
        });
        
        // Auto play
        audioPlayer.play().catch(e => {
            // Autoplay blocked by browser before user interaction
        });
        isChangingSong = false;
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
    if (audioPlayer.paused) {
        pendingPlayActionSource = 'button';
        audioPlayer.play().catch(e => console.error(e));
    } else {
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
    const bioBox = document.getElementById('bioBox');
    const galleryBox = document.getElementById('galleryBox');
    const buttons = document.querySelectorAll('.content-toggle button');
    const buttonByView = {
        lyrics: buttons[0],
        playlist: buttons[1],
        bio: buttons[2],
        gallery: buttons[3]
    };

    // Remove active class from all boxes and buttons
    lyricsBox.classList.remove('active');
    playlistBox.classList.remove('active');
    bioBox.classList.remove('active');
    galleryBox.classList.remove('active');
    buttons.forEach(btn => btn.classList.remove('active'));

    if (view === 'lyrics') {
        lyricsBox.classList.add('active');
        if (buttonByView.lyrics) {
            buttonByView.lyrics.classList.add('active');
        }
    } else if (view === 'playlist') {
        playlistBox.classList.add('active');
        if (buttonByView.playlist) {
            buttonByView.playlist.classList.add('active');
        }
        renderPlaylist(); // Ensure playlist is rendered when switching to it
    } else if (view === 'bio') {
        bioBox.classList.add('active');
        if (buttonByView.bio) {
            buttonByView.bio.classList.add('active');
        }
    } else if (view === 'gallery') {
        galleryBox.classList.add('active');
        if (buttonByView.gallery) {
            buttonByView.gallery.classList.add('active');
        }
        // Load gallery if not already loaded
        if (document.getElementById('visualsGallery').children.length === 0) {
            loadVisualsGallery();
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
        
        // Build cover path
        const coverPath = buildCoverUrl(song.cover);
        
        // Parse title to extract track number and tale
        const titleParts = song.title.split('\n');
        const mainTitle = titleParts[0] || '';
        const taleName = titleParts[1] || '';
        
        html += `
            <div class="playlist-item ${isCurrentTrack}" onclick="playTrackFromPlaylist(${index})">
                <img src="${coverPath}" alt="${mainTitle}" class="playlist-track-cover">
                <div class="playlist-track-content">
                    <h5 class="playlist-track-title">${mainTitle} <span class="playlist-track-tale">${taleName}</span></h5>
                    <p class="playlist-track-description">${song.description || ''}</p>
                </div>
            </div>
        `;
    });
    html += '</div>';
    playlistBox.innerHTML = html;
}

// Play a track from the playlist
function playTrackFromPlaylist(index) {
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
    // Update the playlist view to show which track is now current
    renderPlaylist();

    // flip back to lyrics view so user sees the cover/info
    toggleView('lyrics');

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

function bindBioLightbox() {
    const bioBox = document.getElementById('bioBox');
    if (!bioBox || bioBox.dataset.lightboxBound === 'true') {
        return;
    }

    bioBox.dataset.lightboxBound = 'true';
    bioBox.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLImageElement)) {
            return;
        }

        const src = target.getAttribute('src');
        if (!src) {
            return;
        }

        openLightbox(src, target.getAttribute('alt') || 'Bio image');
    });

    bioBox.querySelectorAll('img').forEach((img) => {
        img.style.cursor = 'pointer';
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
    bindBioLightbox();

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
