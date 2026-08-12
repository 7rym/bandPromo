/**
 * Shared login/player shell background (image + video).
 * Expects window.appConfig.media.background_image / background_video
 * and an optional #bg-video element (source may be deferred via data-src).
 *
 * Prefer living when a background_video is assigned; otherwise still.
 * Reduced-motion and slow-connection fall back to still image.
 * No operator Still|Living toggle — assignment is the intent.
 *
 * Living video is deferred until after window.load (idle) so still image paints
 * first and multi-MB MP4 ranges do not contend with covers/thumbs.
 */
(function (global) {
    'use strict';

    let livingAttachScheduled = false;
    let livingAttached = false;

    function prefersReducedMotion() {
        try {
            return global.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (error) {
            return false;
        }
    }

    function connectionSpeedMbps() {
        try {
            const connectionData = JSON.parse(global.sessionStorage.getItem('connection_speed') || '{}');
            return Number(connectionData.speed) || 0;
        } catch (error) {
            return 0;
        }
    }

    function clearBodyBackgroundImage() {
        document.body.classList.remove('shell-bg-image');
        document.body.style.removeProperty('background-image');
    }

    function resolveBackgroundVideoUrl(video) {
        const fromConfig = String(global.appConfig?.media?.background_video || '').trim();
        if (fromConfig) {
            return fromConfig;
        }
        if (!(video instanceof HTMLVideoElement)) {
            return '';
        }
        const dataSrc = String(video.getAttribute('data-src') || '').trim();
        if (dataSrc) {
            return dataSrc;
        }
        const source = video.querySelector('source');
        return String(source?.getAttribute('src') || source?.getAttribute('data-src') || '').trim();
    }

    function ensureVideoSource(video) {
        if (!(video instanceof HTMLVideoElement)) {
            return false;
        }
        const url = resolveBackgroundVideoUrl(video);
        if (!url) {
            return false;
        }

        let source = video.querySelector('source');
        if (!source) {
            source = document.createElement('source');
            source.type = 'video/mp4';
            video.appendChild(source);
        }

        const currentSrc = String(source.getAttribute('src') || '').trim();
        if (currentSrc !== url) {
            source.setAttribute('src', url);
            source.removeAttribute('data-src');
            video.removeAttribute('data-src');
            try {
                video.load();
            } catch (error) {
                // Ignore reload failures.
            }
        }
        return true;
    }

    function showBgImage() {
        const video = document.getElementById('bg-video');
        const bgImage = global.appConfig?.media?.background_image || '';
        if (video) {
            try {
                video.pause();
            } catch (error) {
                // Ignore pause failures.
            }
            video.style.display = 'none';
            video.removeAttribute('autoplay');
        }
        document.body.classList.remove('shell-bg-video');
        if (bgImage) {
            document.body.classList.add('shell-bg-image');
            document.body.style.backgroundImage = `url('${bgImage}')`;
        } else {
            clearBodyBackgroundImage();
        }
    }

    function showBgVideo() {
        const video = document.getElementById('bg-video');
        if (!video) {
            showBgImage();
            return;
        }

        if (!ensureVideoSource(video)) {
            showBgImage();
            return;
        }

        livingAttached = true;
        video.style.display = 'block';
        clearBodyBackgroundImage();
        document.body.classList.add('shell-bg-video');

        const fallbackToImage = () => {
            showBgImage();
        };
        video.addEventListener('error', fallbackToImage, { once: true });
        const bgVideoSource = video.querySelector('source');
        if (bgVideoSource) {
            bgVideoSource.addEventListener('error', () => {
                try {
                    video.load();
                } catch (error) {
                    // Ignore reload failures.
                }
                fallbackToImage();
            }, { once: true });
        }

        const playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(() => {
                // Autoplay can fail until a gesture; keep video visible for retry.
            });
        }
    }

    function scheduleLivingBackgroundAttach() {
        if (livingAttachScheduled || livingAttached) {
            return;
        }
        livingAttachScheduled = true;

        const run = () => {
            if (prefersReducedMotion()) {
                return;
            }
            const speed = connectionSpeedMbps();
            if (speed > 0 && speed < 5) {
                return;
            }
            showBgVideo();
        };

        const afterLoad = () => {
            if (typeof global.requestIdleCallback === 'function') {
                global.requestIdleCallback(run, { timeout: 3000 });
                return;
            }
            global.setTimeout(run, 500);
        };

        if (document.readyState === 'complete') {
            afterLoad();
            return;
        }
        global.addEventListener('load', afterLoad, { once: true });
    }

    function resetLivingAttach() {
        livingAttachScheduled = false;
        livingAttached = false;
        const video = document.getElementById('bg-video');
        if (!(video instanceof HTMLVideoElement)) {
            return;
        }
        try {
            video.pause();
        } catch (error) {
            // Ignore pause failures.
        }
        video.style.display = 'none';
        video.removeAttribute('autoplay');
        document.body.classList.remove('shell-bg-video');
    }

    function updateBackground(options) {
        const force = !!(options && options.force);
        if (force) {
            resetLivingAttach();
        }

        const video = document.getElementById('bg-video');
        if (!video) {
            const hasImageSource = !!(global.appConfig?.media?.background_image);
            if (hasImageSource) {
                showBgImage();
            }
            return;
        }

        const hasVideoSource = !!resolveBackgroundVideoUrl(video);
        const hasImageSource = !!(global.appConfig?.media?.background_image);
        const reduceMotion = prefersReducedMotion();

        // Always paint still first when available — living MP4 attaches after load.
        if (reduceMotion || !hasVideoSource) {
            if (hasImageSource || reduceMotion || !hasVideoSource) {
                showBgImage();
            }
            return;
        }

        // Prefer living when assigned (still paints first; video attaches idle).
        const speed = connectionSpeedMbps();
        if (speed > 0 && speed < 5) {
            if (hasImageSource || !hasVideoSource) {
                showBgImage();
                return;
            }
        }

        showBgImage();
        scheduleLivingBackgroundAttach();
    }

    global.bandpromoShellBackground = {
        showBgImage,
        showBgVideo,
        updateBackground,
        scheduleLivingBackgroundAttach,
        resetLivingAttach,
    };
    // Back-compat for login.js callers.
    global.showBgImage = showBgImage;
    global.updateBackground = updateBackground;
})(window);
