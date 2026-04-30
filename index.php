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
    <title><?php echo get_config('site.name'); ?> - Login</title>
    
    <!-- Open Graph Meta Tags -->
    <?php echo generate_og_tags(get_config('site.name') . ' - Login', 'Access ' . get_config('site.name')); ?>
    
    <!-- Twitter Card Meta Tags -->
    <?php echo generate_twitter_tags(get_config('site.name') . ' - Login', 'Access ' . get_config('site.name')); ?>
    
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
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars(get_config('site.name')); ?>">
    
    <!-- Manifest & Theme -->
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#121212">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="./biblioteca/login.css">

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
        window.appConfig = {
            name: <?php echo json_encode(get_config('site.name')); ?>,
            description: <?php echo json_encode(get_config('site.description')); ?>,
            media: <?php echo json_encode([
                'background_video' => get_config('media.background_video', '/media/special/bandPromo_background.mp4'),
                'background_image' => get_config('media.background_image', '/media/special/bandPromo_background.png'),
                'cover'            => get_config('media.cover',            '/media/special/bandPromo_cover.png'),
                'welcome_audio'    => get_config('media.welcome_audio',    '/media/special/bandPromo_welcome.flac'),
                'loggedin_audio'   => get_config('media.loggedin_audio',   '/media/special/bandPromo_loggedin.flac'),
                'logo'             => get_config('media.logo',             '/media/special/bandPromo_logo.png'),
            ]); ?>,
            social: {
                share_image: <?php echo json_encode(get_config('social.share_image', '/media/special/bandPromo_share.png')); ?>
            }
        };
    </script>

    <video id="bg-video" autoplay muted loop>
        <source src="<?php echo htmlspecialchars(get_config('media.background_video', '/media/special/bandPromo_background.mp4')); ?>" type="video/mp4">
    </video>
    <audio id="enter-audio">
        <?php
        $welcome_src = get_config('media.welcome_audio', '/media/special/bandPromo_welcome.flac');
        $welcome_ext = strtolower(pathinfo($welcome_src, PATHINFO_EXTENSION));
        $welcome_mime = ($welcome_ext === 'flac') ? 'audio/flac' : (($welcome_ext === 'ogg') ? 'audio/ogg' : 'audio/mpeg');
        ?>
        <source src="<?php echo htmlspecialchars($welcome_src); ?>" type="<?php echo $welcome_mime; ?>">
    </audio>
    <audio id="letsgo-audio">
        <?php
        $loggedin_src = get_config('media.loggedin_audio', '/media/special/bandPromo_loggedin.flac');
        $loggedin_ext = strtolower(pathinfo($loggedin_src, PATHINFO_EXTENSION));
        $loggedin_mime = ($loggedin_ext === 'flac') ? 'audio/flac' : (($loggedin_ext === 'ogg') ? 'audio/ogg' : 'audio/mpeg');
        ?>
        <source src="<?php echo htmlspecialchars($loggedin_src); ?>" type="<?php echo $loggedin_mime; ?>">
    </audio>
    
    <?php if ($authenticated): ?>
        <div id="auth-success" style="display: flex; justify-content: center; align-items: center; width: 100%; height: 100vh; position: fixed; top: 0; left: 0; background: rgba(0, 0, 0, 0.7); z-index: 1000;">
            <div style="text-align: center; color: white;">
                <h1 style="font-size: 32px; margin-bottom: 10px;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                <p style="font-size: 18px; margin-bottom: 20px;">Loading album...</p>
                <img src="<?php echo htmlspecialchars(get_config('media.cover', '/media/special/bandPromo_cover.png')); ?>" alt="<?php echo htmlspecialchars(get_config('site.name', 'bandPromo')); ?>" style="width: 100%; max-width: 600px; height: auto;">
            </div>
        </div>
        <script>
            // For authenticated users: play letsgo.mp3 and redirect
            window.addEventListener('load', function() {
                const letsgoAudio = document.getElementById('letsgo-audio');
                let redirected = false;
                
                function performRedirect() {
                    if (!redirected) {
                        redirected = true;
                        window.location.href = '<?php echo htmlspecialchars($redirect_url); ?>';
                    }
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
            <div class="logo">
                <img src="<?php echo htmlspecialchars(get_config('media.logo', '/media/special/bandPromo_logo.png')); ?>" alt="<?php echo htmlspecialchars(get_config('site.name', 'bandPromo')); ?> Logo">
            </div>
            <p id="aboutThis"><a href="#" onclick="openInfoLightbox(event)">
                <span class="about-line active">What is this?</span>
                <span class="about-line">Is it dangerous?</span>
                <span class="about-line">Is it fun?</span>
                <span class="about-line">Tell me more...</span>
            </a></p>
            <p><br></p>            
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
                    <button type="button" class="quality-btn active" data-quality="high">Maximum Quality<br>(Broadband)</button>
                    <button type="button" class="quality-btn" data-quality="low">Mobile Friendly</button>
                </div>
                <input type="hidden" id="quality-hidden" name="quality" value="high" required>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>" required>
            
            <button type="submit">Login</button>
            </form>
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
                    if (enterAudio) {
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
        
    <script src="./biblioteca/login.js"></script>
</body>
</html>
