// Load Visuals Gallery from JSON
async function loadVisualsGallery() {
    const gallery = document.getElementById('visualsGallery');
    if (!gallery) return;

    function normalizeMediaPath(value) {
        return String(value || '').replace(/\\/g, '/');
    }

    function deriveVideoPoster(item) {
        if (item && item.poster) {
            return normalizeMediaPath(item.poster);
        }
        const src = normalizeMediaPath(item && item.src);
        if (!src) return '';
        const match = src.match(/\/media\/visual\/delivery\/(ast_[^/]+)\/standard-stream\.(mp4|webm|mov|mkv)$/i);
        if (match) {
            return `/media/visual/delivery/${match[1]}/poster.jpg`;
        }
        return '';
    }

    function normalizeGalleryItem(item) {
        if (!item || !item.src) {
            return item;
        }

        if (item.type === 'video') {
            return {
                ...item,
                src: normalizeMediaPath(item.src),
                poster: deriveVideoPoster(item),
            };
        }

        return {
            ...item,
            src: normalizeMediaPath(item.src),
        };
    }

    function renderGallery(items) {
        const normalizedItems = Array.isArray(items) ? items.map(normalizeGalleryItem) : [];

        if (normalizedItems.length === 0) {
            gallery.innerHTML = '<div class="gallery-empty">No images available</div>';
            return;
        }

        if (window._lb) window._lb.setItems(normalizedItems);

        gallery.innerHTML = normalizedItems.map((item, idx) => {
            const isVideo = item.type === 'video';
            const clickHandler = `openLightboxAt(${idx})`;

            if (isVideo) {
                const posterAttr = item.poster ? ` poster="${item.poster}"` : '';
                return `
                    <div class="gallery-item gallery-item--video" onclick="${clickHandler}">
                        <video src="${item.src}"${posterAttr} preload="metadata" muted playsinline
                               style="pointer-events:none;width:100%;height:100%;object-fit:cover;">
                        </video>
                        <div class="gallery-video-play">&#9654;</div>
                    </div>`;
            }
            return `
                <div class="gallery-item" onclick="${clickHandler}">
                    <img src="${item.src}" alt="${item.alt}" loading="lazy">
                </div>`;
        }).join('');
    }
    
    try {
        gallery.innerHTML = '<div class="gallery-loading">Loading gallery...</div>';

        if (Array.isArray(window.INITIAL_GALLERY_ITEMS)) {
            renderGallery(window.INITIAL_GALLERY_ITEMS);
            return;
        }
        
        const response = await fetch('/biblioteca/gallery.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            gallery.innerHTML = `<div class="gallery-error">${data.error}</div>`;
            return;
        }
        
        renderGallery(data.images);
        
    } catch (error) {
        gallery.innerHTML = '<div class="gallery-error">Failed to load gallery</div>';
    }
}
