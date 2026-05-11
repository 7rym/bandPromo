<?php
require_once __DIR__ . '/biblioteca/https.php';
require_once __DIR__ . '/biblioteca/setup-state.php';
bandpromo_enforce_https();

session_start();

// Redirect to setup wizard if setup hasn't been completed
if (!bandpromo_is_setup_complete()) {
    header('Location: /setup.php');
    exit;
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    session_destroy();
    header('Location: /');
    exit;
}

// Initialize error message
$error = null;
$authenticated = false;
$redirect_url = '';
$appVersion = 'dev';
$origin = bandpromo_current_origin();
$versionFile = __DIR__ . '/VERSION';
if (file_exists($versionFile)) {
    $rawVersion = trim((string) file_get_contents($versionFile));
    if ($rawVersion !== '') {
        $appVersion = $rawVersion;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $quality = isset($_POST['quality']) ? trim($_POST['quality']) : '';
    
    // Basic validation
    if (empty($username) || empty($password) || empty($quality)) {
        $error = 'Please fill in all fields.';
    } else {
        // Use authentication library
        require_once 'biblioteca/auth.php';
        
        try {
            if (authenticate($username, $password)) {
                // Set authentication flag in session
                $_SESSION['authenticated'] = true;
                $_SESSION['username'] = htmlspecialchars($username);
                $_SESSION['quality'] = $quality;
                $_SESSION['login_time'] = time(); // Track login time for possible timeout
                
                // Always redirect to the canonical player
                $redirect_url = '/play/';
                
                // Mark as authenticated so we can show redirect screen
                $authenticated = true;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'Authentication error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    require_once 'biblioteca/share-tools.php';
    require_once 'biblioteca/csrf.php';
    
    // Generate CSRF token for form
    $csrf_token = generate_csrf_token();
    
    echo generate_standard_meta_tags();
    ?>
    <title><?php echo get_config('release.identity.title'); ?> - Login</title>
    
    <!-- Open Graph Meta Tags -->
    <?php echo generate_og_tags(get_config('release.identity.title') . ' - Login', 'Access ' . get_config('release.identity.title')); ?>
    
    <!-- Twitter Card Meta Tags -->
    <?php echo generate_twitter_tags(get_config('release.identity.title') . ' - Login', 'Access ' . get_config('release.identity.title')); ?>
    
    <!-- Favicon & Icons -->
    <!-- favicon.ico is auto-discovered in root, no link needed -->
    <link rel="icon" type="image/png" sizes="16x16" href="/media/icons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/media/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/media/icons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="/media/icons/favicon.svg">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="/media/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars(get_config('release.identity.title')); ?>">
    
    <!-- Manifest & Theme -->
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#121212">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="./biblioteca/login.css?v=<?php echo rawurlencode($appVersion); ?>">

</head>
<body>
    <!-- PWA Install Banner -->
    <div id="pwa-banner" class="pwa-banner" style="display: none;">
        <div class="pwa-banner-content">
            <div class="pwa-banner-text">
                <span class="pwa-banner-icon">📱</span>
                <span class="pwa-banner-message" id="pwa-banner-text">Get the app totally free?<br>Install for quick access!</span>
            </div>
            <div class="pwa-banner-actions">
                <button id="pwa-install-btn" class="pwa-banner-btn-install">Install</button>
                <button id="pwa-dismiss-btn" class="pwa-banner-btn-dismiss">×</button>
            </div>
        </div>
    </div>

    <!-- Global config for JavaScript -->
    <script>
        <?php
        $backgroundVideo = get_config_nonempty('release.theme.background_video', null);
        $backgroundImage = get_config_nonempty('release.theme.background_image', null);
        $welcomeAudio = get_config_nonempty('install.theme.welcome_audio', null);
        $loggedinAudio = get_config_nonempty('install.theme.loggedin_audio', null);
        ?>
        window.appConfig = {
            name: <?php echo json_encode(get_config('release.identity.title')); ?>,
            description: <?php echo json_encode(get_config('release.identity.description')); ?>,
            media: <?php echo json_encode([
                'background_video' => $backgroundVideo,
                'background_image' => $backgroundImage,
                'cover'            => get_config('release.theme.cover',            '/media/special/bandPromo_cover.png'),
                'welcome_audio'    => $welcomeAudio,
                'loggedin_audio'   => $loggedinAudio,
                'logo'             => get_config('install.brand.logo',             '/media/special/bandPromo_logo.png'),
            ]); ?>,
            social: {
                share_image: <?php echo json_encode(get_config('release.brand.poster', '/media/special/bandPromo_share.png')); ?>
            }
        };
    </script>

    <video id="bg-video" autoplay muted loop<?php echo $backgroundVideo ? '' : ' style="display:none"'; ?>>
        <?php if ($backgroundVideo): ?>
        <source src="<?php echo htmlspecialchars($backgroundVideo); ?>" type="video/mp4">
        <?php endif; ?>
    </video>
    <audio id="enter-audio">
        <?php if ($welcomeAudio): ?>
        <?php
        $welcome_ext = strtolower(pathinfo($welcomeAudio, PATHINFO_EXTENSION));
        $welcome_mime = ($welcome_ext === 'flac') ? 'audio/flac' : (($welcome_ext === 'ogg') ? 'audio/ogg' : 'audio/mpeg');
        ?>
        <source src="<?php echo htmlspecialchars($welcomeAudio); ?>" type="<?php echo $welcome_mime; ?>">
        <?php endif; ?>
    </audio>
    <audio id="letsgo-audio">
        <?php if ($loggedinAudio): ?>
        <?php
        $loggedin_ext = strtolower(pathinfo($loggedinAudio, PATHINFO_EXTENSION));
        $loggedin_mime = ($loggedin_ext === 'flac') ? 'audio/flac' : (($loggedin_ext === 'ogg') ? 'audio/ogg' : 'audio/mpeg');
        ?>
        <source src="<?php echo htmlspecialchars($loggedinAudio); ?>" type="<?php echo $loggedin_mime; ?>">
        <?php endif; ?>
    </audio>
    
    <?php if ($authenticated): ?>
        <div id="auth-success" style="display: flex; justify-content: center; align-items: center; width: 100%; height: 100vh; position: fixed; top: 0; left: 0; background: rgba(0, 0, 0, 0.7); z-index: 1000;">
            <div style="text-align: center; color: white;">
                <h1 style="font-size: 32px; margin-bottom: 10px;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p style="font-size: 18px; margin-bottom: 20px;">Loading album...</p>
                <img src="<?php echo htmlspecialchars(get_config('release.theme.cover', '/media/special/bandPromo_cover.png')); ?>" alt="<?php echo htmlspecialchars(get_config('release.identity.title', 'bandPromo')); ?>" style="width: 100%; max-width: 600px; height: auto;">
            </div>
        </div>
        <script>
            // For authenticated users: play letsgo.mp3 and redirect
            window.addEventListener('load', function() {
                const letsgoAudio = document.getElementById('letsgo-audio');
                const hasLetsgoSource = !!letsgoAudio?.querySelector('source')?.getAttribute('src');
                let redirected = false;
                
                function performRedirect() {
                    if (!redirected) {
                        redirected = true;
                        window.location.href = '<?php echo htmlspecialchars($redirect_url); ?>';
                    }
                }

                if (!hasLetsgoSource) {
                    performRedirect();
                    return;
                }
                
                letsgoAudio.play().catch(function(error) {
                    console.log('Audio playback error:', error);
                    // If audio fails, redirect after 1 second
                    setTimeout(performRedirect, 1000);
                });
                
                // Redirect when audio ends
                letsgoAudio.addEventListener('ended', performRedirect);
                
                // Safety timeout: redirect after 20 seconds max
                setTimeout(performRedirect, 20000);
            });
        </script>
    <?php else: ?>
        <div class="login-container">
            <div class="login-side-column">
                <div class="logo">
                    <img src="<?php echo htmlspecialchars(get_config('install.brand.logo', '/media/special/bandPromo_logo.png')); ?>" alt="<?php echo htmlspecialchars(get_config('release.identity.title', 'bandPromo')); ?> Logo">
                </div>
                <p id="aboutThis"><a href="#" onclick="openInfoLightbox(event)">
                    <span class="about-line active">What is this?</span>
                    <span class="about-line">Is it dangerous?</span>
                    <span class="about-line">Is it fun?</span>
                    <span class="about-line">Tell me more...</span>
                </a></p>
            </div>
            <div class="login-form-column">
                <?php if ($error): ?>
                    <div id="error-message" class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Enter your username"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                </div>
                
                <div class="quality-group">
                    <div id="speed-test-result" style="text-align: center; font-size: 12px; color: #fff; margin-bottom: 4px; min-height: 20px;">
                        Testing connection speed...
                    </div>
                    <div style="text-align: center; margin-bottom: 10px;">
                        <button type="button" id="retest-speed-btn" style="background: none; border: none; color: #aaa; font-size: 11px; cursor: pointer; text-decoration: underline; padding: 0;">Re-test connection</button>
                    </div>
                    <div class="quality-options">
                        <button type="button" class="quality-btn" data-quality="high">Maximum Quality<br>(Broadband)</button>
                        <button type="button" class="quality-btn active" data-quality="low">Optimized<br>(Mobile Friendly)</button>
                    </div>
                    <input type="hidden" id="quality-hidden" name="quality" value="low" required>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>" required>
                
                <button type="submit">Login</button>
                </form>
            </div>
        </div>
        <?php
        // Load and display info lightbox from configuration
        require_once 'biblioteca/info-display.php';
        ?>
        <script>
            // For unauthenticated users: play enter.mp3 on first interaction
            let audioPlayed = false;
            
            // Check if error message exists - if so, don't play audio
            const errorMessage = document.querySelector('.error-message');
            if (errorMessage && errorMessage.textContent.trim()) {
                audioPlayed = true;
            }
            
            document.addEventListener('click', function playEnterAudioOnce() {
                if (!audioPlayed) {
                    const enterAudio = document.getElementById('enter-audio');
                    const hasEnterSource = !!enterAudio?.querySelector('source')?.getAttribute('src');
                    if (enterAudio && hasEnterSource) {
                        enterAudio.currentTime = 0;
                        enterAudio.play().catch(function(error) {
                            console.log('Audio playback error:', error);
                        });
                    }
                    audioPlayed = true;
                    document.removeEventListener('click', playEnterAudioOnce);
                }
            }, true);
        </script>
        <?php endif; ?>
        
        <script>
            // Make CSRF token available to JavaScript
            window.csrfToken = <?php echo json_encode($csrf_token); ?>;
            // Store in sessionStorage for use on other pages after login
            sessionStorage.setItem('csrf_token', window.csrfToken);
        </script>
        
        <!-- Service Worker Registration for PWA -->
        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/service-worker.js?v=<?php echo rawurlencode($appVersion); ?>', {
                    updateViaCache: 'none'
                }).catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
            }
        </script>
        
    <script src="./biblioteca/login.js?v=<?php echo rawurlencode($appVersion); ?>"></script>
</body>
</html>
