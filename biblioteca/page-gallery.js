/**
 * Page gallery widgets (carousel snap + dots). Safe to call after innerHTML swaps.
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

    function bindPageGalleryCarousels(root) {
        const scope = root && typeof root.querySelectorAll === 'function' ? root : document;
        scope.querySelectorAll('.page-gallery--carousel').forEach(bindCarousel);
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
