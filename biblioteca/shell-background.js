/**
 * Shared login/player shell background (image + video).
 * Expects window.appConfig.media.background_image / background_video
 * and an optional #bg-video element with a <source>.
 *
 * Player may set window.appConfig.player.shell_background to "still" | "living".
 * Login leaves it unset and keeps adaptive auto behavior.
 */
(function (global) {
    'use strict';

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

    function shellBackgroundMode() {
        const mode = String(
            global.appConfig?.player?.shell_background
            || global.appConfig?.shell_background
            || ''
        ).trim().toLowerCase();
        if (mode === 'still' || mode === 'living') {
            return mode;
        }
        return 'auto';
    }

    function clearBodyBackgroundImage() {
        document.body.classList.remove('shell-bg-image');
        document.body.style.removeProperty('background-image');
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

        const bgVideoSource = video.querySelector('source');
        const hasVideoSource = !!bgVideoSource?.getAttribute('src');
        if (!hasVideoSource) {
            showBgImage();
            return;
        }

        video.style.display = 'block';
        clearBodyBackgroundImage();
        document.body.classList.add('shell-bg-video');

        const fallbackToImage = () => {
            showBgImage();
        };
        video.addEventListener('error', fallbackToImage, { once: true });
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

    function updateBackground() {
        const video = document.getElementById('bg-video');
        if (!video) {
            return;
        }

        const mode = shellBackgroundMode();
        const bgVideoSource = video.querySelector('source');
        const hasVideoSource = !!bgVideoSource?.getAttribute('src');
        const hasImageSource = !!(global.appConfig?.media?.background_image);
        const reduceMotion = prefersReducedMotion();

        if (mode === 'still') {
            showBgImage();
            return;
        }

        if (mode === 'living') {
            if (reduceMotion || !hasVideoSource) {
                showBgImage();
                return;
            }
            showBgVideo();
            return;
        }

        // Auto (login / unset): prefer still on slow connection or reduced motion.
        const speed = connectionSpeedMbps();
        if (reduceMotion || speed < 5) {
            if (hasImageSource || reduceMotion || !hasVideoSource) {
                showBgImage();
                return;
            }
        }

        if (!hasVideoSource) {
            if (hasImageSource) {
                showBgImage();
            } else {
                video.style.display = 'none';
                clearBodyBackgroundImage();
                document.body.classList.remove('shell-bg-video');
            }
            return;
        }

        showBgVideo();
    }

    global.bandpromoShellBackground = {
        showBgImage,
        updateBackground,
        shellBackgroundMode,
    };
    // Back-compat for login.js callers.
    global.showBgImage = showBgImage;
    global.updateBackground = updateBackground;
})(window);
