/**
 * Inert Branding Live preview — Shell surface only (parked until preview slice).
 * Mirrors user-shell classes from shell.css; does not use live #shell-bg-video id
 * (admin isolation — class .shell-bg-video-el only).
 */
(function () {
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function assetUrl(document, key) {
        const assets = document && document.assets && typeof document.assets === 'object'
            ? document.assets
            : {};
        return String(assets[key] || '').trim();
    }

    function renderMarkup(document) {
        if (!document) {
            return '<p class="brand-editor-empty">No brand selected.</p>';
        }

        const background = assetUrl(document, 'background_image');
        const backgroundVideo = assetUrl(document, 'background_video');
        const logo = assetUrl(document, 'logo');
        const living = backgroundVideo !== '';
        const stageClass = living
            ? 'play-preview-shell-stage shell-bg-video'
            : (background !== '' ? 'play-preview-shell-stage shell-bg-image' : 'play-preview-shell-stage');
        const stageStyle = background && !living
            ? ` style="background-image:url('${escapeHtml(background)}');"`
            : '';
        const posterAttr = background
            ? ` poster="${escapeHtml(background)}"`
            : '';
        const videoMarkup = living
            ? `<video class="shell-bg-video-el" src="${escapeHtml(backgroundVideo)}"${posterAttr} muted loop playsinline autoplay preload="auto" aria-hidden="true" style="display:block;"></video>`
            : '<video class="shell-bg-video-el" muted loop playsinline preload="none" aria-hidden="true" style="display:none;"></video>';
        const logoMarkup = logo
            ? `<img class="shell-logo-img" src="${escapeHtml(logo)}" alt="" loading="lazy" onerror="this.style.opacity=0.25">`
            : '<span class="play-preview-shell-logo-placeholder">No logo assigned</span>';

        return `
            <div class="play-preview-shell" inert>
                <div class="${stageClass}"${stageStyle}>
                    ${videoMarkup}
                    <div class="play-preview-shell-foreground">
                        <div class="shell-logo">
                            ${logoMarkup}
                        </div>
                        <div class="play-preview-shell-atmosphere" data-preview-focus="colours" aria-hidden="true">
                            <span class="play-preview-shell-swatch" style="background:var(--primary-color);"></span>
                            <span class="play-preview-shell-swatch" style="background:var(--secondary-color);"></span>
                            <span class="play-preview-shell-swatch" style="background:var(--text-color);"></span>
                            <span class="play-preview-shell-swatch" style="background:var(--color-surface-mid);"></span>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    function startVideos(root) {
        if (!(root instanceof HTMLElement)) {
            return;
        }
        root.querySelectorAll('video.shell-bg-video-el').forEach((video) => {
            if (video.style.display === 'none' || !video.getAttribute('src')) {
                return;
            }
            video.muted = true;
            video.loop = true;
            video.playsInline = true;
            const play = () => {
                const attempt = video.play();
                if (attempt && typeof attempt.catch === 'function') {
                    attempt.catch(function () {});
                }
            };
            if (video.readyState >= 2) {
                play();
                return;
            }
            video.addEventListener('canplay', play, { once: true });
            try {
                video.load();
            } catch (error) {}
            play();
        });
    }

    window.bandpromoPlayPreviewShell = {
        renderMarkup: renderMarkup,
        startVideos: startVideos,
    };
}());
