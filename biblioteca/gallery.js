// Load Visuals Gallery from JSON
async function loadVisualsGallery() {
    const gallery = document.getElementById('visualsGallery');
    if (!gallery) return;
    
    try {
        gallery.innerHTML = '<div class="gallery-loading">Loading gallery...</div>';
        
        const response = await fetch('/biblioteca/gallery.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.error) {
            gallery.innerHTML = `<div class="gallery-error">${data.error}</div>`;
            return;
        }
        
        if (!data.images || data.images.length === 0) {
            gallery.innerHTML = '<div class="gallery-empty">No images available</div>';
            return;
        }
        
        // Register items with the shared lightbox
        if (window._lb) window._lb.setItems(data.images);

        // Render gallery items
        gallery.innerHTML = data.images.map((item, idx) => {
            const isVideo = item.type === 'video';
            const clickHandler = isVideo
                ? `openLightboxAt(${idx})`
                : `openLightboxAt(${idx})`;

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
        
    } catch (error) {
        gallery.innerHTML = '<div class="gallery-error">Failed to load gallery</div>';
    }
}
