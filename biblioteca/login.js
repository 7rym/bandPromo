// Rotating about-this lines
document.addEventListener('DOMContentLoaded', function() {
    const aboutLines = document.querySelectorAll('.about-line');
    if (aboutLines.length > 0) {
        let currentLine = 0;
        
        setInterval(function() {
            // Remove active class from current line
            aboutLines[currentLine].classList.remove('active');
            
            // Move to next line
            currentLine = (currentLine + 1) % aboutLines.length;
            
            // Add active class to new line
            aboutLines[currentLine].classList.add('active');
        }, 3000);
    }
});

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

// Test Download Speed and Auto-Select Quality
async function testConnectionSpeed(forceRefresh = true) {
    const resultDiv = document.getElementById('speed-test-result');
    
    // Exit if speed test elements not present (e.g., player page)
    if (!resultDiv) return;
    
    try {
        // Check if already cached (unless force refresh)
        if (!forceRefresh) {
            const cached = sessionStorage.getItem('connection_speed');
            if (cached) {
                const data = JSON.parse(cached);
                resultDiv.textContent = `📊 ${data.speed.toFixed(2)} Mbps - Max quality available`;
                updateBackground();
                return;
            }
        } else {
            sessionStorage.removeItem('connection_speed');
        }

        resultDiv.innerHTML = '📊 Testing connection...';

        // Prefer Cloudflare for global consistency, with same-origin fallback.
        const testCandidates = [
            'https://speed.cloudflare.com/__down?bytes=10000000',
            '/biblioteca/speed-test.php?bytes=5000000'
        ];
        const measurements = [];

        // Single sample — 10 MB is large enough for a reliable measurement
        for (let i = 0; i < 1; i++) {
            try {
                const testUrl = testCandidates[i % testCandidates.length];
                const sep = testUrl.includes('?') ? '&' : '?';
                const urlWithParam = testUrl + sep + 't=' + Date.now();

                const startTime = performance.now();
                const response = await fetch(urlWithParam, { cache: 'no-store' });

                if (!response.ok) continue;

                // Always read the full body — timing headers-only gives bogus results
                const blob = await response.blob();
                const endTime = performance.now();

                const actualSize = blob.size;
                if (actualSize === 0) continue;

                const downloadTimeMs = endTime - startTime;
                if (downloadTimeMs > 50) {
                    const speedMbps = (actualSize * 8) / (downloadTimeMs / 1000) / 1000000;
                    measurements.push(speedMbps);
                }
            } catch (e) {
                console.warn('Speed test sample failed:', e);
            }
        }

        // If first round fails entirely, try each candidate once before giving up.
        if (measurements.length === 0) {
            for (const candidate of testCandidates) {
                try {
                    const sep = candidate.includes('?') ? '&' : '?';
                    const urlWithParam = candidate + sep + 'retry=' + Date.now();

                    const startTime = performance.now();
                    const response = await fetch(urlWithParam, { cache: 'no-store' });
                    if (!response.ok) continue;

                    const blob = await response.blob();
                    const endTime = performance.now();

                    const actualSize = blob.size;
                    const downloadTimeMs = endTime - startTime;
                    if (actualSize > 0 && downloadTimeMs > 50) {
                        const speedMbps = (actualSize * 8) / (downloadTimeMs / 1000) / 1000000;
                        measurements.push(speedMbps);
                        break;
                    }
                } catch (fallbackError) {
                    console.warn('Speed test candidate failed:', candidate, fallbackError);
                }
            }
        }
        
        if (measurements.length === 0) {
            resultDiv.innerHTML = '⚠️ Speed test failed, optimized mode remains selected';
            console.warn('Speed test failed for all endpoints. Keeping optimized quality selected.');
            sessionStorage.setItem('connection_speed', JSON.stringify({
                speed: 0,
                recommended: 'high'
            }));
            updateBackground();
            return;
        }
        
        // Calculate average (ignore outliers if we have enough samples)
        const avgSpeedMbps = measurements.reduce((a, b) => a + b) / measurements.length;
        
        // Determine recommendation based on file size
        // HQ Album is 687MB (5496 Mbit)
        // Threshold calculation:
        // - 5 Mbps = ~18 min (mobile data risk - entire quota)
        // - 10 Mbps = ~9 min (still risky on mobile)
        // - 20 Mbps = ~4.5 min (wifi/fiber only)
        // 20 Mbps protects mobile users from consuming entire data quota
        const SPEED_THRESHOLD_MBPS = 20;
        const recommended = avgSpeedMbps < SPEED_THRESHOLD_MBPS ? 'low' : 'high';
        
        // Cache result
        sessionStorage.setItem('connection_speed', JSON.stringify({
            speed: avgSpeedMbps,
            recommended: recommended
        }));
        
        // Display result with indicator matching 20 Mbps threshold
        // 🐌 <5 Mbps | 🟡 5-10 Mbps | ⚡ 10-20 Mbps | 🚀 ≥20 Mbps
        const speedDisplay = avgSpeedMbps.toFixed(2);
        const indicator = avgSpeedMbps >= 20 ? '🚀' : avgSpeedMbps >= 10 ? '⚡' : avgSpeedMbps >= 5 ? '🟡' : '🐌';
        resultDiv.textContent = `${indicator} ${speedDisplay} Mbps - Max quality available`;
        updateBackground();
        
    } catch (error) {
        console.error('❌ Speed test error:', error);
        if (resultDiv) {
            resultDiv.innerHTML = '⚠️ Speed test error, optimized mode remains selected';
        }
        updateBackground();
    }
}

// Auto-select quality button based on recommendation
function autoSelectQuality(recommendation) {
    const buttons = document.querySelectorAll('.quality-btn');
    const qualityInput = document.getElementById('quality-hidden');
    
    // Exit if quality elements not present (e.g., player page)
    if (!qualityInput || buttons.length === 0) return;
    
    buttons.forEach(btn => {
        if (btn.dataset.quality === recommendation) {
            btn.classList.add('active');
            qualityInput.value = recommendation;
        } else {
            btn.classList.remove('active');
        }
    });
    
    updateBackground();
}

function persistSelectedQuality(quality) {
    if (quality === 'high' || quality === 'low') {
        sessionStorage.setItem('bandpromo_selected_quality', quality);
    }
}

function showBgImage() {
    const video = document.getElementById('bg-video');
    const bgImage = window.appConfig?.media?.background_image || '';
    if (video) video.style.display = 'none';
    document.body.style.backgroundImage = bgImage ? `url('${bgImage}')` : 'none';
}

function updateBackground() {
    const video = document.getElementById('bg-video');

    // Exit if background elements not present (e.g., player page)
    if (!video) return;

    // Use speedtest result to determine background
    // Show image only if connection is slow (< 5 Mbps - 🐌)
    const connectionData = JSON.parse(sessionStorage.getItem('connection_speed') || '{}');
    const speed = connectionData.speed || 0;
    const bgVideoSource = video.querySelector('source');
    const hasVideoSource = !!bgVideoSource?.getAttribute('src');
    const hasImageSource = !!(window.appConfig?.media?.background_image);

    if (speed < 5) {
        // Slow connection: show image
        showBgImage();
    } else {
        if (!hasVideoSource) {
            if (hasImageSource) {
                showBgImage();
            } else {
                video.style.display = 'none';
                document.body.style.backgroundImage = 'none';
            }
            return;
        }

        // Fast connection (≥5 Mbps): try video, fall back to image if it fails
        video.style.display = 'block';
        document.body.style.backgroundImage = 'none';

        // If video errors (missing file etc.) fall back to image
        video.addEventListener('error', showBgImage, { once: true });
        // Also catch the case where the <source> fires error before video
        if (bgVideoSource) bgVideoSource.addEventListener('error', () => { video.load(); showBgImage(); }, { once: true });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const qualityInput = document.getElementById('quality-hidden');
    const loginForm = document.querySelector('.login-form-column form');
    const retestBtn = document.getElementById('retest-speed-btn');

    if (qualityInput) {
        qualityInput.value = 'low';
    }
    persistSelectedQuality('low');

    if (retestBtn) {
        retestBtn.addEventListener('click', function() {
            sessionStorage.removeItem('connection_speed');
            testConnectionSpeed(true);
        });
    }

    if (loginForm && qualityInput) {
        loginForm.addEventListener('submit', () => {
            qualityInput.value = 'low';
            persistSelectedQuality('low');
        });
    }
    
    updateBackground();

    ['click', 'touchend', 'pointerup'].forEach((eventName) => {
        document.addEventListener(eventName, syncWideModeFullscreen, { passive: true });
    });

    window.addEventListener('resize', syncWideModeFullscreen);
    window.addEventListener('orientationchange', syncWideModeFullscreen);
    document.addEventListener('fullscreenchange', () => {
        wideModeFullscreenOwned = isMobileWideMode() && !!getFullscreenElement();
    });

    syncWideModeFullscreen();
});

// Info Lightbox functions
function openInfoLightbox(event) {
    event.preventDefault();
    const lightbox = document.getElementById('infoLightbox');
    if (lightbox) {
        lightbox.classList.add('active');
    }
}

function closeInfoLightbox() {
    const lightbox = document.getElementById('infoLightbox');
    if (lightbox) {
        lightbox.classList.remove('active');
    }
}

// Close lightbox when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const infoLightbox = document.getElementById('infoLightbox');
    if (infoLightbox) {
        infoLightbox.addEventListener('click', function(event) {
            if (event.target === this) {
                closeInfoLightbox();
            }
        });
    }
});

// Run speed test after full page load (all resources downloaded)
window.addEventListener('load', function() {
    testConnectionSpeed(true);
});

// PWA Install Banner
(function() {
    const banner = document.getElementById('pwa-banner');
    const bannerText = document.getElementById('pwa-banner-text');
    let deferredPrompt = null;
    
    if (!banner) return; // Banner not on this page
    
    // Get app name from config (set in index.php)
    const appName = (window.appConfig && window.appConfig.name) || 'our app';
    
    // Update banner text with app name
    if (bannerText) {
        bannerText.innerHTML = `Get the app totally free?<br>Install <strong>${appName}</strong> for quick access!`;
    }
    
    // Check if app is already installed
    function isAppInstalled() {
        // Check if running in PWA mode (fullscreen or standalone display)
        return isStandaloneDisplayMode();
    }
    
    // Check if dismissal was remembered
    function isDismissed() {
        const dismissTime = sessionStorage.getItem('pwa-banner-dismissed');
        if (!dismissTime) return false;
        // Show again after 24 hours
        return Date.now() - parseInt(dismissTime) < 86400000;
    }
    
    // Show banner if conditions are met
    function showBanner() {
        if (isAppInstalled()) {
            return; // App already installed, don't show
        }
        if (isDismissed()) {
            return; // User dismissed, don't show again yet
        }
        if (deferredPrompt) {
            banner.style.display = 'block';
        }
    }
    
    // Dismiss banner
    function dismissBanner() {
        sessionStorage.setItem('pwa-banner-dismissed', Date.now());
        banner.style.display = 'none';
    }
    
    // Listen for install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // Show banner on next tick
        setTimeout(() => {
            if (!isAppInstalled() && !isDismissed()) {
                banner.style.display = 'block';
            }
        }, 1000);
    });
    
    // Handle app installed event
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        dismissBanner();
    });
    
    // Install button handler
    const installBtn = document.getElementById('pwa-install-btn');
    if (installBtn) {
        installBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!deferredPrompt) return;
            
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    // User accepted, banner will disappear on appinstalled event
                } else {
                    // User dismissed, hide banner
                    dismissBanner();
                }
                deferredPrompt = null;
            }).catch(err => {
                // Silently handle error
                deferredPrompt = null;
            });
        });
    }
    
    // Dismiss button handler
    const dismissBtn = document.getElementById('pwa-dismiss-btn');
    if (dismissBtn) {
        dismissBtn.addEventListener('click', (e) => {
            e.preventDefault();
            dismissBanner();
        });
    }
    
    // Initial check on page load
    document.addEventListener('DOMContentLoaded', () => {
        showBanner();
    });
    
    // Also check if page is already loaded (in case DOMContentLoaded already fired)
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        showBanner();
    }
})();
