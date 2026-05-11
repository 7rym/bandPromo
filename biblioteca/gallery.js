// Load Visuals Gallery from JSON
async function loadVisualsGallery() {
    const gallery = document.getElementById('visualsGallery');
    if (!gallery) return;

    function normalizeGalleryItem(item) {
        if (!item || item.type === 'video' || !item.src) {
            return item;
        }

        const normalizedSrc = String(item.src)
            .replace(/\\/g, '/')
            .replace('/original/', '/optimal/')
            .replace(/\.(png|jpe?g|webp)$/i, '.jpg');

        return {
            ...item,
            src: normalizedSrc,
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
                return `
                    <div class="gallery-item gallery-item--video" onclick="${clickHandler}">
                        <video src="${item.src}" preload="metadata" muted playsinline
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
