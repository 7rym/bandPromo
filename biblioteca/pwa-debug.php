<?php
// PWA Debug Info Page
// Simple diagnostic tool to debug PWA installation issues

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWA Debug - Twisted Chronicles</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            color: #fff;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 30px;
            color: #00d4ff;
            text-align: center;
        }

        .section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #00d4ff;
        }

        .status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 4px;
        }

        .status-icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
        }

        .status.success .status-icon { color: #00ff00; }
        .status.error .status-icon { color: #ff4444; }
        .status.warning .status-icon { color: #ffaa00; }
        .status.info .status-icon { color: #00d4ff; }

        .status-text {
            flex: 1;
        }

        .status-label {
            font-weight: bold;
            color: #fff;
        }

        .status-value {
            color: #aaa;
            font-size: 14px;
        }

        button {
            background: #00d4ff;
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px 0;
            transition: background 0.3s;
        }

        button:hover {
            background: #00ffff;
        }

        button:active {
            transform: scale(0.98);
        }

        .code {
            background: rgba(0, 0, 0, 0.5);
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 10px;
            word-break: break-all;
        }

        .log {
            background: rgba(0, 0, 0, 0.7);
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }

        .log-entry {
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        .log-time {
            color: #00d4ff;
            margin-right: 10px;
        }

        .log-error {
            color: #ff4444;
        }

        .log-success {
            color: #00ff00;
        }

        .log-info {
            color: #aaa;
        }

        footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 PWA Debug Dashboard</h1>

        <!-- HTTPS Status -->
        <div class="section">
            <h2>🔒 Connection Security</h2>
            <div class="status success">
                <div class="status-icon">✓</div>
                <div class="status-text">
                    <div class="status-label">HTTPS</div>
                    <div class="status-value">Secure connection required for PWA ✓</div>
                </div>
            </div>
        </div>

        <!-- Manifest Status -->
        <div class="section">
            <h2>📋 Web App Manifest</h2>
            <div id="manifest-status"></div>
            <button onclick="checkManifest()">Validate Manifest</button>
        </div>

        <!-- Service Worker Status -->
        <div class="section">
            <h2>⚙️ Service Worker</h2>
            <div id="sw-status"></div>
            <button onclick="registerServiceWorker()">Register Service Worker</button>
            <button onclick="unregisterServiceWorker()">Unregister</button>
            <button onclick="clearCache()">Clear Cache</button>
        </div>

        <!-- Browser Capabilities -->
        <div class="section">
            <h2>🌐 Browser Support</h2>
            <div id="browser-support"></div>
        </div>

        <!-- Installation Status -->
        <div class="section">
            <h2>📱 Installation</h2>
            <div id="install-status"></div>
            <button id="install-btn" onclick="installApp()" style="display:none;">Install App</button>
        </div>

        <!-- Console Logs -->
        <div class="section">
            <h2>📝 Debug Console</h2>
            <div class="log" id="debug-log">
                <div class="log-entry"><span class="log-time">[init]</span><span class="log-info">Debug console loaded</span></div>
            </div>
            <button onclick="clearLogs()">Clear Logs</button>
        </div>

        <footer>
            PWA Debug Dashboard - Open DevTools (F12) on desktop or remote debug on mobile
        </footer>
    </div>

    <script>
        const debugLog = document.getElementById('debug-log');
        let deferredPrompt;

        // Enhanced logging
        function log(message, type = 'info') {
            const time = new Date().toLocaleTimeString('en-US', { 
                hour12: false, 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            entry.innerHTML = `<span class="log-time">[${time}]</span> ${message}`;
            debugLog.appendChild(entry);
            debugLog.scrollTop = debugLog.scrollHeight;
            console.log(`[${type.toUpperCase()}]`, message);
        }

        function clearLogs() {
            debugLog.innerHTML = '<div class="log-entry"><span class="log-time">[clear]</span><span class="log-info">Logs cleared</span></div>';
        }

        // Check Browser Capabilities
        function checkBrowserSupport() {
            const support = {
                serviceWorker: 'serviceWorker' in navigator,
                caches: 'caches' in window,
                indexedDB: !!window.indexedDB,
                fetch: !!window.fetch,
                promise: !!window.Promise
            };

            const html = Object.entries(support).map(([key, value]) => `
                <div class="status ${value ? 'success' : 'error'}">
                    <div class="status-icon">${value ? '✓' : '✗'}</div>
                    <div class="status-text">
                        <div class="status-label">${key}</div>
                        <div class="status-value">${value ? 'Supported' : 'Not supported'}</div>
                    </div>
                </div>
            `).join('');

            document.getElementById('browser-support').innerHTML = html;
            log(`Browser check: ${Object.values(support).filter(v => v).length}/${Object.keys(support).length} features supported`, 'info');
        }

        // Check Manifest
        async function checkManifest() {
            try {
                log('Fetching manifest...', 'info');
                const response = await fetch('/site.webmanifest');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const manifest = await response.json();
                log(`Manifest loaded successfully`, 'success');

                // Validate manifest
                const required = ['name', 'short_name', 'icons', 'display', 'start_url'];
                const missing = required.filter(key => !manifest[key]);

                if (missing.length > 0) {
                    log(`Missing required fields: ${missing.join(', ')}`, 'error');
                } else {
                    log(`All required manifest fields present ✓`, 'success');
                }

                // Check icons
                const iconChecks = await Promise.all(
                    manifest.icons.map(async (icon) => {
                        try {
                            const res = await fetch(icon.src);
                            return res.ok;
                        } catch {
                            return false;
                        }
                    })
                );

                const validIcons = iconChecks.filter(v => v).length;
                log(`Icons: ${validIcons}/${manifest.icons.length} accessible`, 
                    validIcons === manifest.icons.length ? 'success' : 'warning');

                // Display manifest info
                const html = `
                    <div class="status success">
                        <div class="status-icon">✓</div>
                        <div class="status-text">
                            <div class="status-label">Manifest: ${manifest.name}</div>
                            <div class="status-value">Display: ${manifest.display} | Icons: ${validIcons}/${manifest.icons.length}</div>
                        </div>
                    </div>
                    <div class="code">
                        start_url: ${manifest.start_url}<br>
                        scope: ${manifest.scope}<br>
                        theme_color: ${manifest.theme_color}
                    </div>
                `;
                document.getElementById('manifest-status').innerHTML = html;

            } catch (error) {
                log(`Manifest check failed: ${error.message}`, 'error');
                document.getElementById('manifest-status').innerHTML = `
                    <div class="status error">
                        <div class="status-icon">✗</div>
                        <div class="status-text">
                            <div class="status-label">Manifest Error</div>
                            <div class="status-value">${error.message}</div>
                        </div>
                    </div>
                `;
            }
        }

        // Service Worker Management
        async function registerServiceWorker() {
            if (!navigator.serviceWorker) {
                log('Service Worker not supported', 'error');
                return;
            }

            try {
                log('Registering service worker...', 'info');
                const registration = await navigator.serviceWorker.register('/service-worker.js');
                log(`Service Worker registered: ${registration.scope}`, 'success');
                updateServiceWorkerStatus(registration);
            } catch (error) {
                log(`Service Worker registration failed: ${error.message}`, 'error');
                updateServiceWorkerStatus(null);
            }
        }

        async function unregisterServiceWorker() {
            if (!navigator.serviceWorker) return;

            try {
                log('Unregistering service worker...', 'info');
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (let reg of registrations) {
                    await reg.unregister();
                }
                log('Service Worker unregistered', 'success');
                updateServiceWorkerStatus(null);
            } catch (error) {
                log(`Unregister failed: ${error.message}`, 'error');
            }
        }

        async function clearCache() {
            try {
                log('Clearing caches...', 'info');
                const cacheNames = await caches.keys();
                await Promise.all(cacheNames.map(name => caches.delete(name)));
                log(`Cleared ${cacheNames.length} cache(s): ${cacheNames.join(', ')}`, 'success');
            } catch (error) {
                log(`Cache clear failed: ${error.message}`, 'error');
            }
        }

        async function updateServiceWorkerStatus(registration) {
            if (!registration) {
                document.getElementById('sw-status').innerHTML = `
                    <div class="status error">
                        <div class="status-icon">✗</div>
                        <div class="status-text">
                            <div class="status-label">Service Worker</div>
                            <div class="status-value">Not registered</div>
                        </div>
                    </div>
                `;
                return;
            }

            const state = registration.active ? 'active' : (registration.installing ? 'installing' : 'waiting');
            const statusClass = state === 'active' ? 'success' : 'warning';

            // Check caches
            const cacheNames = await caches.keys();
            
            document.getElementById('sw-status').innerHTML = `
                <div class="status ${statusClass}">
                    <div class="status-icon">⚙️</div>
                    <div class="status-text">
                        <div class="status-label">Service Worker: ${state.toUpperCase()}</div>
                        <div class="status-value">Scope: ${registration.scope}</div>
                    </div>
                </div>
                <div class="status info">
                    <div class="status-icon">💾</div>
                    <div class="status-text">
                        <div class="status-label">Caches</div>
                        <div class="status-value">${cacheNames.length} cache(s): ${cacheNames.join(', ')}</div>
                    </div>
                </div>
            `;
        }

        // Installation Prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            log('Install prompt available', 'success');
            document.getElementById('install-btn').style.display = 'block';
            document.getElementById('install-status').innerHTML = `
                <div class="status success">
                    <div class="status-icon">📱</div>
                    <div class="status-text">
                        <div class="status-label">Ready to Install</div>
                        <div class="status-value">App can be installed on this device</div>
                    </div>
                </div>
            `;
        });

        window.addEventListener('appinstalled', () => {
            log('App installed successfully!', 'success');
            deferredPrompt = null;
        });

        function installApp() {
            if (!deferredPrompt) return;
            
            log('Installation prompt shown...', 'info');
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    log('User accepted installation', 'success');
                } else {
                    log('User dismissed installation', 'info');
                }
                deferredPrompt = null;
            });
        }

        // Listen for service worker messages
        if (navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                log(`Service Worker: ${event.data.message}`, 'info');
            });
        }

        // Initial checks
        window.addEventListener('load', () => {
            log('Page loaded, starting diagnostics...', 'info');
            checkBrowserSupport();
            checkManifest();
            
            // Check for existing registration
            if (navigator.serviceWorker) {
                navigator.serviceWorker.getRegistrations().then(registrations => {
                    if (registrations.length > 0) {
                        log(`Found ${registrations.length} existing registration(s)`, 'info');
                        updateServiceWorkerStatus(registrations[0]);
                    } else {
                        log('No service worker registrations found', 'info');
                        document.getElementById('sw-status').innerHTML = `
                            <div class="status warning">
                                <div class="status-icon">⚠️</div>
                                <div class="status-text">
                                    <div class="status-label">Service Worker: Not Registered</div>
                                    <div class="status-value">Click "Register Service Worker" to activate</div>
                                </div>
                            </div>
                        `;
                    }
                });
            }
        });

        // Catch all console errors
        window.addEventListener('error', (event) => {
            log(`Error: ${event.message}`, 'error');
        });

        window.addEventListener('unhandledrejection', (event) => {
            log(`Unhandled Promise Rejection: ${event.reason}`, 'error');
        });
    </script>
</body>
</html>
