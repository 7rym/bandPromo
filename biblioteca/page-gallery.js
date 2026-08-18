/**
 * Page gallery widgets (carousel snap + dots, animated wipes). Safe to call after innerHTML swaps.
 */
(function (global) {
    'use strict';

    function prefersReducedMotion() {
        return Boolean(global.matchMedia)
            && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function slideItems(track) {
        return Array.prototype.slice.call(track.querySelectorAll(':scope > .page-gallery-item'));
    }

    function nearestIndex(track, items) {
        const midpoint = track.scrollLeft + (track.clientWidth / 2);
        let best = 0;
        let bestDist = Infinity;
        items.forEach(function (item, index) {
            const center = item.offsetLeft + (item.offsetWidth / 2);
            const dist = Math.abs(center - midpoint);
            if (dist < bestDist) {
                bestDist = dist;
                best = index;
            }
        });
        return best;
    }

    function scrollToItem(track, item) {
        const trackRect = track.getBoundingClientRect();
        const itemRect = item.getBoundingClientRect();
        const delta = itemRect.left - trackRect.left - ((track.clientWidth - itemRect.width) / 2);
        if (typeof track.scrollTo === 'function') {
            track.scrollTo({
                left: track.scrollLeft + delta,
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
            });
            return;
        }
        track.scrollLeft += delta;
    }

    function syncDots(section, activeIndex) {
        section.querySelectorAll('.page-gallery-carousel-dot').forEach(function (dot, index) {
            if (index === activeIndex) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });
    }

    function autorotateIntervalMs(section) {
        const raw = parseInt(section.getAttribute('data-autorotate-ms') || '', 10);
        if (raw === 1000 || raw === 2000 || raw === 3000) {
            return raw;
        }
        return 2000;
    }

    function bindCarousel(section) {
        if (section.dataset.carouselBound === 'true') {
            return;
        }
        const track = section.querySelector('.page-gallery-grid');
        if (!track) {
            return;
        }
        const items = slideItems(track);
        if (items.length === 0) {
            return;
        }
        section.dataset.carouselBound = 'true';

        const prev = section.querySelector('.page-gallery-carousel-prev');
        const next = section.querySelector('.page-gallery-carousel-next');
        const dots = Array.prototype.slice.call(section.querySelectorAll('.page-gallery-carousel-dot'));
        const autorotate = section.getAttribute('data-autorotate') === 'true' && items.length > 1;
        const autorotateMs = autorotateIntervalMs(section);

        let timerId = null;
        let inView = false;
        let pausedByUi = false;
        let observer = null;

        function go(index) {
            const max = items.length - 1;
            let nextIndex = index;
            if (nextIndex < 0) {
                nextIndex = max;
            } else if (nextIndex > max) {
                nextIndex = 0;
            }
            scrollToItem(track, items[nextIndex]);
            syncDots(section, nextIndex);
        }

        function stopTimer() {
            if (timerId !== null) {
                global.clearInterval(timerId);
                timerId = null;
            }
        }

        function canAutorotate() {
            return autorotate
                && section.isConnected
                && inView
                && !pausedByUi
                && !document.hidden
                && !prefersReducedMotion();
        }

        function syncTimer() {
            if (!canAutorotate()) {
                stopTimer();
                return;
            }
            if (timerId !== null) {
                return;
            }
            timerId = global.setInterval(function () {
                if (!canAutorotate()) {
                    stopTimer();
                    return;
                }
                go(nearestIndex(track, items) + 1);
            }, autorotateMs);
        }

        function restartTimer() {
            stopTimer();
            syncTimer();
        }

        function teardown() {
            stopTimer();
            if (observer) {
                observer.disconnect();
                observer = null;
            }
        }

        let scrollTick = 0;
        track.addEventListener('scroll', function () {
            if (scrollTick) {
                return;
            }
            scrollTick = global.requestAnimationFrame(function () {
                scrollTick = 0;
                syncDots(section, nearestIndex(track, items));
            });
        }, { passive: true });

        if (prev) {
            prev.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                go(nearestIndex(track, items) - 1);
                restartTimer();
            });
        }
        if (next) {
            next.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                go(nearestIndex(track, items) + 1);
                restartTimer();
            });
        }
        dots.forEach(function (dot, index) {
            dot.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                go(index);
                restartTimer();
            });
        });

        track.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                go(nearestIndex(track, items) - 1);
                restartTimer();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                go(nearestIndex(track, items) + 1);
                restartTimer();
            }
        });

        if (autorotate) {
            section.addEventListener('mouseenter', function () {
                pausedByUi = true;
                syncTimer();
            });
            section.addEventListener('mouseleave', function () {
                pausedByUi = false;
                syncTimer();
            });
            section.addEventListener('focusin', function () {
                pausedByUi = true;
                syncTimer();
            });
            section.addEventListener('focusout', function (event) {
                if (section.contains(event.relatedTarget)) {
                    return;
                }
                pausedByUi = false;
                syncTimer();
            });
            document.addEventListener('visibilitychange', syncTimer);

            if (typeof IntersectionObserver === 'function') {
                observer = new IntersectionObserver(function (entries) {
                    if (!section.isConnected) {
                        teardown();
                        return;
                    }
                    entries.forEach(function (entry) {
                        inView = entry.isIntersecting && entry.intersectionRatio >= 0.35;
                    });
                    syncTimer();
                }, { threshold: [0, 0.35, 0.5, 1] });
                observer.observe(section);
            } else {
                inView = true;
                syncTimer();
            }
        }

        syncDots(section, nearestIndex(track, items));
    }

    function escapeXml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function mediaNaturalSize(figure) {
        const img = figure.querySelector('img');
        if (img && img.naturalWidth && img.naturalHeight) {
            return { w: img.naturalWidth, h: img.naturalHeight };
        }
        const video = figure.querySelector('video');
        if (video && video.videoWidth && video.videoHeight) {
            return { w: video.videoWidth, h: video.videoHeight };
        }
        return { w: 0, h: 0 };
    }

    function animatedMaxHeight() {
        return Math.min((global.innerHeight || 800) * 0.58, 560);
    }

    function fitAnimatedViewport(viewport, figure) {
        if (!viewport || !figure) {
            return;
        }
        const host = viewport.parentElement;
        const colW = (function () {
            if (!host) {
                return viewport.clientWidth || 1;
            }
            const style = global.getComputedStyle ? global.getComputedStyle(host) : null;
            const pad = style
                ? (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0)
                : 0;
            return Math.max(1, host.clientWidth - pad);
        }());
        const nat = mediaNaturalSize(figure);
        const srcW = nat.w || 16;
        const srcH = nat.h || 9;
        const maxH = animatedMaxHeight();
        let width = colW;
        let height = width * (srcH / srcW);
        if (height > maxH) {
            height = maxH;
            width = height * (srcW / srcH);
        }
        viewport.style.width = Math.round(width) + 'px';
        viewport.style.height = Math.round(Math.max(160, height)) + 'px';
    }

    function figureMediaUrl(figure) {
        const img = figure.querySelector('img');
        if (img && img.getAttribute('src')) {
            return img.getAttribute('src');
        }
        const video = figure.querySelector('video');
        if (video && video.getAttribute('poster')) {
            return video.getAttribute('poster');
        }
        return '';
    }

    function spiralPath(width, height, turns) {
        const cx = width / 2;
        const cy = height / 2;
        const maxR = Math.sqrt(cx * cx + cy * cy) * 1.08;
        const steps = turns * 48;
        let d = '';
        let i;
        for (i = 0; i <= steps; i += 1) {
            const t = (i / steps) * turns * Math.PI * 2;
            const r = (i / steps) * maxR;
            const x = cx + Math.cos(t) * r;
            const y = cy + Math.sin(t) * r;
            d += (i === 0 ? 'M' : 'L') + x.toFixed(2) + ' ' + y.toFixed(2);
        }
        return d;
    }

    function runSpiralWipe(viewport, incomingUrl, outgoingUrl, motion, duration, done) {
        const boxW = Math.max(1, Math.round(viewport.clientWidth || 1));
        const boxH = Math.max(1, Math.round(viewport.clientHeight || 1));
        const turns = 5;
        const maxR = Math.sqrt((boxW / 2) * (boxW / 2) + (boxH / 2) * (boxH / 2)) * 1.08;
        const strokeWidth = Math.max(16, (maxR / turns) * 2.2);
        const uid = 'bp-spiral-' + Math.random().toString(36).slice(2, 10);
        const fx = document.createElement('div');
        fx.className = 'page-gallery-animated-fx';
        const revealIncoming = motion === 'spiral-in';
        const href = revealIncoming ? incomingUrl : outgoingUrl;
        if (!href) {
            done();
            return;
        }
        const stroke = revealIncoming ? '#fff' : '#000';
        const bg = revealIncoming ? '#000' : '#fff';
        fx.innerHTML = '<svg viewBox="0 0 ' + boxW + ' ' + boxH + '" width="' + boxW + '" height="' + boxH + '" preserveAspectRatio="xMidYMid meet" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true">'
            + '<defs><mask id="' + uid + '">'
            + '<rect width="' + boxW + '" height="' + boxH + '" fill="' + bg + '"></rect>'
            + '<path fill="none" stroke="' + stroke + '" stroke-linecap="round" stroke-width="' + strokeWidth.toFixed(1) + '" d="' + spiralPath(boxW, boxH, turns) + '"></path>'
            + '</mask></defs>'
            + '<image href="' + escapeXml(href) + '" xlink:href="' + escapeXml(href) + '" x="0" y="0" width="' + boxW + '" height="' + boxH + '" preserveAspectRatio="xMidYMid meet" mask="url(#' + uid + ')"></image>'
            + '</svg>';
        viewport.appendChild(fx);
        const path = fx.querySelector('path');
        let length = 1;
        try {
            length = path.getTotalLength() || 1;
        } catch (error) {
            length = 1200;
        }
        path.style.strokeDasharray = String(length);
        path.style.strokeDashoffset = revealIncoming ? String(length) : '0';
        const start = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
        function tick(now) {
            const elapsed = now - start;
            const t = Math.min(1, elapsed / duration);
            path.style.strokeDashoffset = revealIncoming
                ? String(length * (1 - t))
                : String(length * t);
            if (t < 1) {
                global.requestAnimationFrame(tick);
                return;
            }
            if (fx.parentNode) {
                fx.parentNode.removeChild(fx);
            }
            done();
        }
        global.requestAnimationFrame(tick);
    }

    function runBlocksWipe(viewport, currentUrl, duration, done) {
        if (!currentUrl) {
            done();
            return;
        }
        const cols = 6;
        const rows = 4;
        const tiles = document.createElement('div');
        tiles.className = 'page-gallery-animated-tiles';
        let index = 0;
        for (index = 0; index < cols * rows; index += 1) {
            const col = index % cols;
            const row = Math.floor(index / cols);
            const cell = document.createElement('span');
            cell.style.backgroundImage = 'url("' + currentUrl.replace(/"/g, '\\"') + '")';
            cell.style.backgroundSize = (cols * 100) + '% ' + (rows * 100) + '%';
            cell.style.backgroundPosition = (cols === 1 ? 0 : (col / (cols - 1)) * 100) + '% '
                + (rows === 1 ? 0 : (row / (rows - 1)) * 100) + '%';
            const delay = Math.random() * duration * 0.55;
            cell.style.transition = 'opacity ' + Math.max(120, duration * 0.45) + 'ms ease, transform ' + Math.max(120, duration * 0.45) + 'ms ease';
            cell.style.transitionDelay = delay + 'ms';
            tiles.appendChild(cell);
        }
        viewport.appendChild(tiles);
        global.requestAnimationFrame(function () {
            Array.prototype.forEach.call(tiles.children, function (cell) {
                cell.style.opacity = '0';
                cell.style.transform = 'scale(1.06)';
            });
        });
        global.setTimeout(function () {
            if (tiles.parentNode) {
                tiles.parentNode.removeChild(tiles);
            }
            done();
        }, duration + 80);
    }

    let animatedResizeBound = false;
    function ensureAnimatedResize() {
        if (animatedResizeBound) {
            return;
        }
        animatedResizeBound = true;
        window.addEventListener('resize', function () {
            document.querySelectorAll('.page-gallery--animated').forEach(function (section) {
                const box = section.querySelector('.page-gallery-animated-viewport');
                const active = section.querySelector('.page-gallery-item.is-active');
                if (box && active) {
                    fitAnimatedViewport(box, active);
                }
            });
        });
    }

    function bindAnimated(section) {
        if (section.dataset.animatedBound === 'true') {
            return;
        }
        const viewport = section.querySelector('.page-gallery-animated-viewport');
        if (!viewport) {
            return;
        }
        const items = Array.prototype.slice.call(viewport.querySelectorAll(':scope > .page-gallery-item'));
        if (items.length === 0) {
            return;
        }
        section.dataset.animatedBound = 'true';

        let current = 0;
        items.forEach(function (item, index) {
            item.classList.toggle('is-active', index === 0);
            item.classList.remove('is-incoming');
            item.style.animation = '';
            item.style.opacity = '';
            item.style.transform = '';
        });

        function activeFigure() {
            return items[current] || items[0];
        }

        function refit() {
            fitAnimatedViewport(viewport, activeFigure());
        }

        items.forEach(function (item) {
            const img = item.querySelector('img');
            if (img && !img.complete) {
                img.addEventListener('load', refit, { once: true });
            }
        });
        refit();
        ensureAnimatedResize();

        if (items.length < 2) {
            return;
        }

        const holdMs = parseInt(section.getAttribute('data-hold-ms') || '', 10) || 3500;
        const wipeMs = parseInt(section.getAttribute('data-wipe-ms') || '', 10) || 700;
        let motion = String(section.getAttribute('data-motion') || 'blend');
        let timerId = null;
        let inView = false;
        let pausedByUi = false;
        let busy = false;
        let observer = null;

        function finishSwap(fromItem, toItem) {
            fromItem.classList.remove('is-active', 'is-incoming');
            fromItem.style.animation = '';
            fromItem.style.opacity = '';
            fromItem.style.transform = '';
            fromItem.style.zIndex = '';
            toItem.classList.add('is-active');
            toItem.classList.remove('is-incoming');
            toItem.style.animation = '';
            toItem.style.opacity = '';
            toItem.style.transform = '';
            toItem.style.zIndex = '';
            current = items.indexOf(toItem);
            busy = false;
            refit();
            syncTimer();
        }

        function goNext() {
            if (busy || items.length < 2) {
                return;
            }
            const fromItem = items[current];
            const toItem = items[(current + 1) % items.length];
            const reduced = prefersReducedMotion();
            const useMotion = reduced ? 'blend' : motion;
            const duration = reduced ? Math.min(280, wipeMs) : wipeMs;
            busy = true;
            stopTimer();
            fitAnimatedViewport(viewport, toItem);

            if (useMotion.indexOf('push-') === 0) {
                toItem.classList.add('is-incoming');
                fromItem.style.animation = 'bp-gallery-' + useMotion + '-out ' + duration + 'ms ease-in-out forwards';
                toItem.style.animation = 'bp-gallery-' + useMotion + '-in ' + duration + 'ms ease-in-out forwards';
                global.setTimeout(function () {
                    finishSwap(fromItem, toItem);
                }, duration + 30);
                return;
            }

            if (useMotion === 'blend') {
                toItem.classList.add('is-incoming');
                toItem.style.animation = 'bp-gallery-blend-in ' + duration + 'ms ease-in-out forwards';
                global.setTimeout(function () {
                    finishSwap(fromItem, toItem);
                }, duration + 30);
                return;
            }

            if (useMotion === 'blocks') {
                toItem.classList.add('is-active');
                toItem.style.zIndex = '0';
                fromItem.style.opacity = '0';
                runBlocksWipe(viewport, figureMediaUrl(fromItem), duration, function () {
                    finishSwap(fromItem, toItem);
                });
                return;
            }

            if (useMotion === 'spiral-in' || useMotion === 'spiral-out') {
                if (useMotion === 'spiral-out') {
                    toItem.classList.add('is-active');
                    toItem.style.zIndex = '0';
                    fromItem.style.opacity = '0';
                }
                runSpiralWipe(
                    viewport,
                    figureMediaUrl(toItem),
                    figureMediaUrl(fromItem),
                    useMotion,
                    duration,
                    function () {
                        finishSwap(fromItem, toItem);
                    }
                );
                return;
            }

            toItem.classList.add('is-incoming');
            global.setTimeout(function () {
                finishSwap(fromItem, toItem);
            }, 40);
        }

        function stopTimer() {
            if (timerId !== null) {
                global.clearTimeout(timerId);
                timerId = null;
            }
        }

        function canAdvance() {
            return section.isConnected
                && inView
                && !pausedByUi
                && !busy
                && !document.hidden
                && items.length > 1;
        }

        function syncTimer() {
            stopTimer();
            if (!canAdvance()) {
                return;
            }
            timerId = global.setTimeout(goNext, holdMs);
        }

        section.addEventListener('mouseenter', function () {
            pausedByUi = true;
            syncTimer();
        });
        section.addEventListener('mouseleave', function () {
            pausedByUi = false;
            syncTimer();
        });
        section.addEventListener('focusin', function () {
            pausedByUi = true;
            syncTimer();
        });
        section.addEventListener('focusout', function (event) {
            if (section.contains(event.relatedTarget)) {
                return;
            }
            pausedByUi = false;
            syncTimer();
        });
        document.addEventListener('visibilitychange', syncTimer);

        if (typeof IntersectionObserver === 'function') {
            observer = new IntersectionObserver(function (entries) {
                if (!section.isConnected) {
                    stopTimer();
                    if (observer) {
                        observer.disconnect();
                    }
                    return;
                }
                entries.forEach(function (entry) {
                    inView = entry.isIntersecting && entry.intersectionRatio >= 0.35;
                });
                syncTimer();
            }, { threshold: [0, 0.35, 0.5, 1] });
            observer.observe(section);
        } else {
            inView = true;
            syncTimer();
        }
    }

    function bindPageGalleryCarousels(root) {
        const scope = root && typeof root.querySelectorAll === 'function' ? root : document;
        scope.querySelectorAll('.page-gallery--carousel').forEach(bindCarousel);
        scope.querySelectorAll('.page-gallery--animated').forEach(bindAnimated);
    }

    global.bandpromoBindPageGalleryCarousels = bindPageGalleryCarousels;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindPageGalleryCarousels();
        });
    } else {
        bindPageGalleryCarousels();
    }
}(window));
