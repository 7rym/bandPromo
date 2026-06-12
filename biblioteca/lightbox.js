/**
 * Shared Lightbox Module — biblioteca/lightbox.js
 *
 * Handles images, videos, gallery navigation, keyboard (arrows/Escape),
 * touch swipe, and click-outside-to-close.
 *
 * Usage:
 *   const lb = new Lightbox({ overlayId, imgId, vidId, prevBtnId, nextBtnId,
 *                              captionId, contentSelector });
 *   lb.setItems([{ src, name, type }]);  // 'image' or 'video'
 *   lb.open(src, altText, type);         // open a single item directly
 *   lb.openAt(idx);                      // open item from the items list
 *   lb.prev(e) / lb.next(e);            // navigate
 *   lb.close();
 *
 * The overlay element controls visibility via the 'active' CSS class.
 * Two display strategies are supported via CSS:
 *   - Opacity fade  (.lightbox CSS): display always flex, opacity toggles
 *   - Display toggle (#adminPreviewLightbox): display:none → flex via .active
 */
class Lightbox {
    constructor(config) {
        this.overlay  = document.getElementById(config.overlayId);
        this.img      = config.imgId      ? document.getElementById(config.imgId)      : null;
        this.vid      = config.vidId      ? document.getElementById(config.vidId)      : null;
        this.prevBtn  = config.prevBtnId  ? document.getElementById(config.prevBtnId)  : null;
        this.nextBtn  = config.nextBtnId  ? document.getElementById(config.nextBtnId)  : null;
        this.caption  = config.captionId  ? document.getElementById(config.captionId)  : null;
        // contentSelector: close when click lands outside this element.
        // null = close when click lands directly on overlay (backdrop click).
        this.contentSelector = config.contentSelector || null;

        this.items        = [];
        this.currentIndex = -1;

        this._setupEvents();
    }

    isOpen() {
        return !!(this.overlay && this.overlay.classList.contains('active'));
    }

    /** Replace the navigable item list (e.g. from gallery). */
    setItems(items) {
        this.items = items || [];
    }

    // ── Internal ────────────────────────────────────────────────────────────

    _load(src, altText, type, poster = '') {
        const isVid = type === 'video';

        if (this.img) {
            this.img.style.display = isVid ? 'none' : '';
            this.img.src = isVid ? '' : src;
            if (!isVid) this.img.alt = altText || '';
        }
        if (this.vid) {
            this.vid.style.display = isVid ? '' : 'none';
            if (isVid) {
                this.vid.pause();
                this.vid.currentTime = 0;
                this.vid.autoplay = true;
                this.vid.loop = true;
                this.vid.muted = false;
                if (poster) this.vid.poster = poster;
                else this.vid.removeAttribute('poster');
                this.vid.src = src;
                this.vid.load();
                const playPromise = this.vid.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(() => {});
                }
            }
            else {
                this.vid.pause();
                this.vid.currentTime = 0;
                this.vid.autoplay = false;
                this.vid.loop = false;
                this.vid.removeAttribute('poster');
                this.vid.src = '';
            }
        }
        if (this.caption) this.caption.textContent = altText || '';

        const hasNav = this.items.length > 1 && this.currentIndex >= 0;
        if (this.prevBtn) this.prevBtn.style.display = hasNav ? '' : 'none';
        if (this.nextBtn) this.nextBtn.style.display = hasNav ? '' : 'none';

        if (this.overlay) this.overlay.classList.add('active');
    }

    _itemType(item) {
        return item.type === 'video' ? 'video' : 'image';
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /** Open a single item directly (no gallery navigation). */
    open(src, altText = '', type = 'image', poster = '') {
        this.currentIndex = -1;
        this._load(src, altText, type, poster);
    }

    /** Open item at index from the items list. */
    openAt(idx) {
        const item = this.items[idx];
        if (!item) return;
        this.currentIndex = idx;
        this._load(item.src, item.name || '', this._itemType(item), item.poster || '');
    }

    prev(e) {
        if (e) e.stopPropagation();
        if (!this.items.length || this.currentIndex < 0) return;
        this.currentIndex = (this.currentIndex - 1 + this.items.length) % this.items.length;
        const item = this.items[this.currentIndex];
        this._load(item.src, item.name || '', this._itemType(item), item.poster || '');
    }

    next(e) {
        if (e) e.stopPropagation();
        if (!this.items.length || this.currentIndex < 0) return;
        this.currentIndex = (this.currentIndex + 1) % this.items.length;
        const item = this.items[this.currentIndex];
        this._load(item.src, item.name || '', this._itemType(item), item.poster || '');
    }

    close() {
        if (this.vid) {
            this.vid.pause();
            this.vid.currentTime = 0;
            this.vid.autoplay = false;
            this.vid.loop = false;
            this.vid.src = '';
        }
        if (this.img) this.img.src = '';
        if (this.overlay) this.overlay.classList.remove('active');
    }

    // ── Event wiring ─────────────────────────────────────────────────────────

    _setupEvents() {
        if (!this.overlay) return;

        // Click outside content to close
        const cs = this.contentSelector;
        this.overlay.addEventListener('click', (e) => {
            const outsideContent = cs
                ? !e.target.closest(cs)
                : e.target === this.overlay;
            if (outsideContent) this.close();
        });

        // Keyboard: Escape, Arrow keys
        document.addEventListener('keydown', (e) => {
            if (!this.isOpen()) return;
            if      (e.key === 'Escape')     this.close();
            else if (e.key === 'ArrowLeft')  this.prev(e);
            else if (e.key === 'ArrowRight') this.next(e);
        });

        // Touch swipe
        let touchStartX = null;
        this.overlay.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].clientX;
        }, { passive: true });
        this.overlay.addEventListener('touchend', (e) => {
            if (touchStartX === null) return;
            const dx = e.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(dx) < 40) return;
            if (dx < 0) this.next(null); else this.prev(null);
        }, { passive: true });
    }
}

window.Lightbox = Lightbox;
