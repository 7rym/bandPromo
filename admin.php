<?php
require_once __DIR__ . '/biblioteca/https.php';
require_once __DIR__ . '/biblioteca/setup-state.php';
bandpromo_enforce_https();

session_start();

require_once 'biblioteca/auth.php';
require_once 'biblioteca/admin-audit.php';
require_once 'biblioteca/build-required.php';
require_once 'biblioteca/config-loader.php';
require_once 'biblioteca/csrf.php';
require_once 'biblioteca/media-library-state.php';

function bandpromo_admin_default_theme_display_version(?string $rawVersion): string {
    $version = trim((string) $rawVersion);
    if ($version === '') {
        return '1.0';
    }

    if (preg_match('/^v\d+\.\d+\s+build\s+\d+$/i', $version)) {
        return '1.0';
    }

    return $version;
}

function bandpromo_admin_files_permanent_warning_line(bool $include_metadata_edits = false): string
{
    if ($include_metadata_edits) {
        return '<strong>⚠️ Metadata edits and file deletions are immediate and permanent. There is no undo!</strong>';
    }

    return '<strong>⚠️ File deletions are immediate and permanent. There is no undo!</strong>';
}

function bandpromo_admin_welcome_build_status(array $buildState): string {
    if (empty($buildState['required'])) {
        return 'No pending build work is currently recorded.';
    }

    $action = strtolower((string) ($buildState['action'] ?? 'none'));
    if ($action === 'optimize') {
        return 'Media optimization is pending before the latest artwork changes reach the site.';
    }

    return 'A full build is pending before the latest source changes reach the site.';
}

function bandpromo_admin_normalize_text(string $value): string {
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = strip_tags($decoded);
    $decoded = strtolower($decoded);
    $decoded = preg_replace('/\s+/u', ' ', $decoded);
    return trim((string) $decoded);
}

function bandpromo_admin_file_text(string $path): string {
    if (!is_file($path)) {
        return '';
    }

    $raw = @file_get_contents($path);
    return is_string($raw) ? $raw : '';
}

function bandpromo_admin_text_contains_any(string $haystack, array $needles): bool {
    foreach ($needles as $needle) {
        if ($needle !== '' && strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function bandpromo_admin_text_checksum(string $value): string {
    return hash('sha256', $value);
}

function bandpromo_admin_pages_match_starter_template(string $currentText, string $templatePath): bool {
    if ($currentText === '') {
        return true;
    }

    $templateText = bandpromo_admin_normalize_text(bandpromo_admin_file_text($templatePath));
    if ($templateText === '') {
        return false;
    }

    return bandpromo_admin_text_checksum($currentText) === bandpromo_admin_text_checksum($templateText);
}

function bandpromo_admin_starter_pack_files_present(string $root): bool {
    $representativePaths = [
        $root . '/media/special/bandPromo_share.png',
        $root . '/media/img/original/bandPromo_vocalist.png',
        $root . '/media/audio/original/bandPromo_the_very_first_song.flac',
    ];

    foreach ($representativePaths as $path) {
        if (!is_file($path)) {
            return false;
        }
    }

    return true;
}

function bandpromo_admin_latest_full_build_success(string $root): bool {
    $logFile = $root . '/log/build.log';
    $lockFile = $root . '/log/build.lock';

    if (!is_file($logFile) || is_file($lockFile)) {
        return false;
    }

    $content = @file_get_contents($logFile);
    if (!is_string($content) || trim($content) === '') {
        return false;
    }

    return preg_match('/\nEXITCODE:0\s*$/', $content) === 1;
}

function bandpromo_admin_runtime_files_present(string $root): bool {
    $requiredFiles = [
        $root . '/web-config.json',
        $root . '/data/bio.html',
        $root . '/data/faq.html',
        $root . '/data/terces',
    ];

    foreach ($requiredFiles as $path) {
        if (!is_file($path)) {
            return false;
        }
    }

    return true;
}

function bandpromo_admin_write_inferred_starter_pack_marker(string $root): bool {
    $markerPath = $root . '/data/default-theme-package.json';
    if (is_file($markerPath) || !bandpromo_admin_starter_pack_files_present($root)) {
        return false;
    }

    $payload = [
        'version' => 'local-source-tree',
        'display_version' => '1.0',
        'sha256' => '',
        'package_file' => '',
        'package_url' => '',
        'release_tag' => 'local-source-tree',
        'paths' => [
            'media/special/bandPromo_share.png',
            'media/img/original/bandPromo_vocalist.png',
            'media/audio/original/bandPromo_the_very_first_song.flac',
        ],
        'installed_at_utc' => gmdate('c'),
        'source' => 'inferred-from-local-files',
    ];

    return @file_put_contents(
        $markerPath,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
    ) !== false;
}

// Redirect to setup wizard if setup hasn't been completed
if (!bandpromo_is_setup_complete()) {
    header('Location: /setup.php');
    exit;
}
require_once 'biblioteca/analytics.php';

$appVersion = trim(@file_get_contents(__DIR__ . '/VERSION') ?: 'dev');
$adminCsrfToken = get_csrf_token();
$siteName  = get_config('release.identity.title', 'Admin');
$siteUrl   = rtrim((string) get_config('install.site.url', ''), '/');
$defaultThemeStatus = null;
$defaultThemeMarkerPath = __DIR__ . '/data/default-theme-package.json';
bandpromo_admin_write_inferred_starter_pack_marker(__DIR__);
if (is_file($defaultThemeMarkerPath)) {
    $defaultThemeMarker = json_decode((string) file_get_contents($defaultThemeMarkerPath), true);
    if (is_array($defaultThemeMarker)) {
        $installedAt = trim((string) ($defaultThemeMarker['installed_at_utc'] ?? ''));
        $installedLabel = '';
        if ($installedAt !== '') {
            try {
                $installedLabel = (new DateTimeImmutable($installedAt))->format('j M Y, H:i');
            } catch (Throwable $throwable) {
                $installedLabel = $installedAt;
            }
        }

        $displayVersion = trim((string) ($defaultThemeMarker['display_version'] ?? ''));
        if ($displayVersion === '') {
            $displayVersion = bandpromo_admin_default_theme_display_version((string) ($defaultThemeMarker['version'] ?? ''));
        }

        $defaultThemeStatus = [
            'version' => trim((string) ($defaultThemeMarker['version'] ?? '')), 
            'display_version' => $displayVersion,
            'installed_at' => $installedLabel,
            'path_count' => is_array($defaultThemeMarker['paths'] ?? null) ? count($defaultThemeMarker['paths']) : 0,
        ];
    }
}
$requestHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$requestHostNoPort = preg_replace('/:\\d+$/', '', $requestHost);
if ($requestHostNoPort === 'localhost') {
    $configuredHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
    if ($configuredHost === '' || $configuredHost === '127.0.0.1' || $configuredHost === 'localhost') {
        $siteUrl = 'http://' . $requestHost;
    }
}

$buildRequiredState = bandpromo_get_build_required_state();
$siteShortLabel = trim((string) get_config('release.identity.short_label', ''));
$siteDescription = trim((string) get_config('release.identity.description', ''));
$releaseCover = trim((string) get_config('release.theme.cover', ''));
$installLogo = trim((string) get_config('install.theme.logo', ''));
$supportUrl = trim((string) get_config('support.url', ''));
$hasUploadedAudio = bandpromo_media_has_visible_user_uploads('audio');
$hasUploadedIllustrations = bandpromo_media_has_visible_user_uploads('illustrations');
$hasUploadedPhotos = bandpromo_media_has_visible_user_uploads('photos');
$hasUploadedSpecial = bandpromo_media_has_visible_user_uploads('special');
$hasUploadedVisualMedia = $hasUploadedIllustrations || $hasUploadedPhotos || $hasUploadedSpecial;
$hasUploadedOwnMedia = $hasUploadedAudio || $hasUploadedVisualMedia;
$starterPackInstalled = $defaultThemeStatus !== null || bandpromo_admin_starter_pack_files_present(__DIR__);
$starterPackDetail = $starterPackInstalled
    ? 'Starter design pack ' . (($defaultThemeStatus['display_version'] ?? '') !== '' ? $defaultThemeStatus['display_version'] : '1.0') . ' is recorded for this installation.'
    : 'The starter design files are not fully available yet. Run a full build to install them.';

$defaultIdentityNames = ['bandpromo demo site', 'your site name', 'bandpromo'];
$defaultShortLabels = ['bandpromo', 'short name'];
$defaultDescriptions = [
    '',
    'a demo site for the bandpromo publishing and marketing tool',
    'site description for manifest and meta tags',
];
$identityNormalized = bandpromo_admin_normalize_text($siteName);
$shortLabelNormalized = bandpromo_admin_normalize_text($siteShortLabel);
$descriptionNormalized = bandpromo_admin_normalize_text($siteDescription);
$coverPersonalized = $releaseCover !== '' && $releaseCover !== '/media/special/bandPromo_cover.png';
$logoPersonalized = $installLogo !== '' && $installLogo !== '/media/special/bandPromo_logo.png';
$installationPersonalized =
    !in_array($identityNormalized, $defaultIdentityNames, true)
    || !in_array($shortLabelNormalized, $defaultShortLabels, true)
    || !in_array($descriptionNormalized, $defaultDescriptions, true)
    || $coverPersonalized
    || $logoPersonalized
    || $supportUrl !== '';

$bioCurrent = bandpromo_admin_normalize_text(bandpromo_admin_file_text(__DIR__ . '/data/bio.html'));
$faqCurrent = bandpromo_admin_normalize_text(bandpromo_admin_file_text(__DIR__ . '/data/faq.html'));
$pagesPublished =
    $bioCurrent !== ''
    && $faqCurrent !== ''
    && !bandpromo_admin_pages_match_starter_template($bioCurrent, __DIR__ . '/biblioteca/templates/bio.template.html')
    && !bandpromo_admin_pages_match_starter_template($faqCurrent, __DIR__ . '/biblioteca/templates/faq.template.html');

$fullBuildSucceeded = bandpromo_admin_latest_full_build_success(__DIR__);
$installationRunning = bandpromo_is_setup_complete() && bandpromo_admin_runtime_files_present(__DIR__);

$welcomeChecklist = [
    [
        'label' => 'Starter pack installed',
        'action_label' => 'Install the starter pack',
        'severity' => 'blocking',
        'complete' => $starterPackInstalled,
        'detail' => $starterPackDetail,
        'href' => '?tab=build',
        'next' => 'Open Build and run a full build so bandPromo can install the starter design files.',
    ],
    [
        'label' => 'Installation personalized',
        'action_label' => 'Personalize the installation',
        'severity' => 'nonblocking',
        'complete' => $installationPersonalized,
        'detail' => $installationPersonalized
            ? 'The site identity or theme has been changed away from the shipped starter defaults.'
            : 'The site is still using the shipped demo identity or default branding values.',
        'href' => '?tab=config',
        'next' => 'Open Config and replace the starter name, description, branding, or support details with your own.',
    ],
    [
        'label' => 'Your own media content is present',
        'action_label' => 'Upload your own media',
        'severity' => 'nonblocking',
        'complete' => $hasUploadedOwnMedia,
        'detail' => $hasUploadedOwnMedia
            ? 'Visible uploaded media is already present in this installation.'
            : 'No visible uploaded media has been detected yet.',
        'href' => '?tab=files&fpanel=audio',
        'next' => 'Open Files and upload your own audio and artwork so the site stops depending on starter media.',
    ],
    [
        'label' => 'Your own pages are published',
        'action_label' => 'Publish your own info',
        'severity' => 'nonblocking',
        'complete' => $pagesPublished,
        'detail' => $pagesPublished
            ? 'The Bio and FAQ pages no longer look like the shipped starter copy.'
            : 'Bio or FAQ still looks like starter content, so the public pages are not fully personalized yet.',
        'href' => '?tab=content&cntab=pages',
        'next' => 'Open Content -> Pages and replace the starter Bio / FAQ text with your own public copy.',
    ],
    [
        'label' => 'The full build process ran successfully',
        'action_label' => 'Run the full build',
        'severity' => 'blocking',
        'complete' => $fullBuildSucceeded,
        'detail' => $fullBuildSucceeded
            ? 'The latest full build finished successfully.'
            : 'No successful full build has been recorded yet, or the last full build failed.',
        'href' => '?tab=build',
        'next' => 'Open Build and run a full build until it completes successfully.',
    ],
    [
        'label' => 'This installation is up and running',
        'action_label' => 'Finish the installation',
        'severity' => 'blocking',
        'complete' => $installationRunning,
        'detail' => $installationRunning
            ? 'Setup is complete and the required runtime files are available.'
            : 'Setup is incomplete or required runtime files are still missing.',
        'href' => '?tab=docs&doc_scope=operator',
        'next' => 'Finish setup and make sure the required runtime files are in place before treating the install as live.',
    ],
];

$welcomeCompletedChecks = 0;
$welcomeNextSteps = [];
foreach ($welcomeChecklist as $item) {
    if (!empty($item['complete'])) {
        $welcomeCompletedChecks++;
        continue;
    }

    $welcomeNextSteps[] = [
        'label' => $item['action_label'],
        'href' => $item['href'],
        'severity' => (string) ($item['severity'] ?? 'nonblocking'),
        'description' => $item['next'],
    ];
}

if ($welcomeNextSteps === []) {
    $welcomeNextSteps[] = [
        'label' => 'Documentation',
        'href' => '?tab=docs&doc_scope=operator',
        'description' => 'You are in a good place. Use Documentation when you want the deeper explanations and workflow guides.',
    ];
}

$welcomeTotalChecks = count($welcomeChecklist);
$welcomeSetupComplete = $welcomeCompletedChecks >= $welcomeTotalChecks;
$welcomeDashboardLinks = [
    [
        'label' => 'Analytics',
        'href' => '?tab=analytics',
        'description' => 'Review listener activity, playback trends, and recent usage.',
    ],
    [
        'label' => 'Files',
        'href' => '?tab=files&fpanel=audio',
        'description' => 'Manage uploads, metadata, cover art, and media references.',
    ],
    [
        'label' => 'Content',
        'href' => '?tab=content',
        'description' => 'Edit pages, playlist order, and gallery items.',
    ],
    [
        'label' => 'Build',
        'href' => '?tab=build',
        'description' => 'Check publish status and run build tasks when needed.',
    ],
    [
        'label' => 'Open public site',
        'href' => $siteUrl !== '' ? $siteUrl : '../',
        'description' => 'Preview the live site as visitors see it.',
        'external' => true,
    ],
    [
        'label' => 'Documentation',
        'href' => '?tab=docs&doc_scope=operator',
        'description' => 'Open the operator guides when you need deeper workflow help.',
    ],
];

if ($welcomeSetupComplete) {
    $welcomePrimaryNotice = 'Setup is complete. Use this dashboard to see what still needs doing, manage content, and keep the live site up to date.';
    if (!empty($buildRequiredState['required'])) {
        $welcomePrimaryNotice .= ' ' . bandpromo_admin_welcome_build_status($buildRequiredState);
    }
} else {
    $welcomePrimaryNotice = $welcomeCompletedChecks . ' of ' . $welcomeTotalChecks . ' checks complete. Next: ' . $welcomeNextSteps[0]['description'];
}

// Ensure public media directories have world-readable permissions (0755) so the
// HTTP server can serve static files.  This is a cheap no-op after the first run.
foreach (['media', 'media/audio', 'media/audio/original', 'media/audio/optimal',
          'media/img', 'media/img/original', 'media/img/optimal',
          'media/photo', 'media/video', 'media/special'] as $_d) {
    $_p = __DIR__ . '/' . $_d;
    if (is_dir($_p)) @chmod($_p, 0755);
}
unset($_d, $_p);

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    if (!empty($_SESSION['authenticated'])) {
        bandpromo_admin_audit_log('admin_logout', [
            'status' => 'ok',
            'data' => ['from' => 'admin_panel'],
        ]);
    }
    session_destroy();
    header('Location: /admin.php');
    exit;
}

$authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'];
$login_error = '';

// Handle admin login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$authenticated) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $auditActor = trim((string) $username) !== '' ? trim((string) $username) : 'anonymous';
    
    if (empty($username) || empty($password)) {
        $login_error = 'Please enter both username and password.';
        bandpromo_admin_audit_log('admin_login_failed', [
            'actor' => $auditActor,
            'target_type' => 'auth',
            'target_id' => 'admin_login',
            'status' => 'error',
            'data' => [
                'reason' => 'missing_credentials',
                'username' => trim((string) $username),
            ],
        ]);
    } else {
        try {
            if (authenticate($username, $password)) {
                if (!isAdminUser($username)) {
                    $login_error = 'Access denied. Admin privileges required.';
                    bandpromo_admin_audit_log('admin_login_failed', [
                        'actor' => $auditActor,
                        'target_type' => 'auth',
                        'target_id' => 'admin_login',
                        'status' => 'denied',
                        'data' => [
                            'reason' => 'not_admin',
                            'username' => trim((string) $username),
                        ],
                    ]);
                } else {
                    $_SESSION['authenticated'] = true;
                    $_SESSION['username'] = htmlspecialchars($username);
                    bandpromo_admin_audit_log('admin_login', [
                        'status' => 'ok',
                        'data' => ['from' => 'admin_login_form'],
                    ]);
                    header('Location: /admin.php');
                    exit;
                }
            } else {
                $login_error = 'Invalid username or password.';
                bandpromo_admin_audit_log('admin_login_failed', [
                    'actor' => $auditActor,
                    'target_type' => 'auth',
                    'target_id' => 'admin_login',
                    'status' => 'error',
                    'data' => [
                        'reason' => 'invalid_credentials',
                        'username' => trim((string) $username),
                    ],
                ]);
            }
        } catch (Exception $e) {
            $login_error = 'Login error: ' . $e->getMessage();
            bandpromo_admin_audit_log('admin_login_failed', [
                'actor' => $auditActor,
                'target_type' => 'auth',
                'target_id' => 'admin_login',
                'status' => 'error',
                'data' => [
                    'reason' => 'exception',
                    'username' => trim((string) $username),
                    'error' => $e->getMessage(),
                ],
            ]);
        }
    }
}

// Authenticated listeners may use the player, but only admin-capable users may open the panel.
if ($authenticated) {
    $sessionUsername = trim((string) ($_SESSION['username'] ?? ''));
    if ($sessionUsername === '' || !isAdminUser($sessionUsername)) {
        ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Panel - Access Denied</title>
        <link rel="stylesheet" href="/biblioteca/admin.css">
    </head>
    <body class="login-page">
        <div class="login-container">
            <h1>Access denied</h1>
            <p class="app-version"><?php echo htmlspecialchars($appVersion ?? 'dev'); ?></p>
            <p class="subtitle">This account can listen on the site but cannot open the admin panel.</p>
            <p><a href="/play/">Return to the player</a></p>
        </div>
    </body>
    </html>
        <?php
        exit;
    }
}

// If not authenticated, show login form
if (!$authenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Panel - Login</title>
        <link rel="stylesheet" href="/biblioteca/admin.css">
    </head>
    <body class="login-page">
        <div class="login-container">
            <h1>🔐 Admin Panel</h1>
            <p class="app-version"><?php echo htmlspecialchars($appVersion ?? 'dev'); ?></p>
            <p class="subtitle"><?php echo htmlspecialchars($siteName); ?> Management</p>
            
            <?php if ($login_error): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once 'biblioteca/admin-helpers.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        if (empty($newUsername) || empty($newPassword)) {
            $error = 'Username and password are required.';
        } else {
            try {
                setUser($newUsername, $newPassword);
                bandpromo_admin_audit_log('user_added', [
                    'target_type' => 'user',
                    'target_id' => $newUsername,
                    'status' => 'ok',
                    'data' => ['role' => 'user'],
                ]);
                $message = "User '$newUsername' added successfully.";
            } catch (Exception $e) {
                bandpromo_admin_audit_log('user_added', [
                    'target_type' => 'user',
                    'target_id' => $newUsername,
                    'status' => 'error',
                    'data' => ['error' => $e->getMessage()],
                ]);
                $error = $e->getMessage();
            }
        }
    }

    elseif ($action === 'edit_user') {
        $editUsername = trim($_POST['edit_username'] ?? '');
        $editPassword = $_POST['edit_password'] ?? '';
        if (empty($editUsername) || empty($editPassword)) {
            $error = 'Username and new password are required.';
        } else {
            try {
                changePassword($editUsername, $editPassword);
                bandpromo_admin_audit_log('password_changed', [
                    'target_type' => 'user',
                    'target_id' => $editUsername,
                    'status' => 'ok',
                ]);
                $message = "Password for '$editUsername' changed successfully.";
            } catch (Exception $e) {
                bandpromo_admin_audit_log('password_changed', [
                    'target_type' => 'user',
                    'target_id' => $editUsername,
                    'status' => 'error',
                    'data' => ['error' => $e->getMessage()],
                ]);
                $error = $e->getMessage();
            }
        }
    }

    elseif ($action === 'delete_user') {
        $delUsername = trim($_POST['user_to_delete'] ?? '');
        if (empty($delUsername)) {
            $error = 'Username is required.';
        } elseif ($delUsername === $_SESSION['username']) {
            $error = 'You cannot delete your own account.';
        } else {
            try {
                deleteUser($delUsername);
                bandpromo_admin_audit_log('user_deleted', [
                    'target_type' => 'user',
                    'target_id' => $delUsername,
                    'status' => 'ok',
                ]);
                $message = "User '$delUsername' deleted successfully.";
            } catch (Exception $e) {
                bandpromo_admin_audit_log('user_deleted', [
                    'target_type' => 'user',
                    'target_id' => $delUsername,
                    'status' => 'error',
                    'data' => ['error' => $e->getMessage()],
                ]);
                $error = $e->getMessage();
            }
        }
    }

    elseif ($action === 'set_role') {
        $roleUsername = trim($_POST['role_username'] ?? '');
        $newRole      = $_POST['new_role'] ?? '';
        if (!in_array($newRole, ['admin', 'developer', 'user'])) {
            $error = 'Invalid role.';
        } elseif ($roleUsername === $_SESSION['username'] && $newRole === 'user') {
            $error = 'You cannot remove your own admin-panel access.';
        } else {
            try {
                setUserRole($roleUsername, $newRole);
                bandpromo_admin_audit_log('user_role_changed', [
                    'target_type' => 'user',
                    'target_id' => $roleUsername,
                    'status' => 'ok',
                    'data' => ['new_role' => $newRole],
                ]);
                $message = "Role for '$roleUsername' set to '$newRole'.";
            } catch (Exception $e) {
                bandpromo_admin_audit_log('user_role_changed', [
                    'target_type' => 'user',
                    'target_id' => $roleUsername,
                    'status' => 'error',
                    'data' => ['new_role' => $newRole, 'error' => $e->getMessage()],
                ]);
                $error = $e->getMessage();
            }
        }
    }
}

$users = getAllUsers();
$currentUserRole = getUserRole($_SESSION['username'] ?? '');

// Primary tab
$tab = $_GET['tab'] ?? 'welcome';
if (!in_array($tab, ['welcome', 'analytics', 'audit', 'users', 'files', 'content', 'config', 'build', 'docs'])) {
    $tab = 'welcome';
}

// Files sub-tab
$filesPanel = $_GET['fpanel'] ?? 'audio';
if (!in_array($filesPanel, ['audio', 'photos', 'video', 'illustrations', 'special'])) {
    $filesPanel = 'audio';
}

// Analytics sub-tab
$analyticsTab = $_GET['atab'] ?? 'dashboard';
if (!in_array($analyticsTab, ['dashboard', 'tracks', 'user-activities', 'listening-patterns', 'quality', 'log'])) {
    $analyticsTab = 'dashboard';
}

$editablePages = [
    'bio' => [
        'emoji' => '📝',
        'label' => 'Bio',
        'title' => 'Band Bio',
        'file' => __DIR__ . '/data/bio.html',
        'description' => 'The band bio shown on your site.',
    ],
    'faq' => [
        'emoji' => '❓',
        'label' => 'FAQ',
        'title' => 'FAQ / Info',
        'file' => __DIR__ . '/data/faq.html',
        'description' => 'The FAQ and info content shown in the site lightbox.',
    ],
];

// Content sub-tab
$contentTab = $_GET['cntab'] ?? 'playlist';
if ($contentTab === 'bio') {
    $contentTab = 'pages';
}
if (!in_array($contentTab, ['playlist', 'gallery', 'pages'])) {
    $contentTab = 'playlist';
}

$contentPage = $_GET['page'] ?? 'bio';
if (!array_key_exists($contentPage, $editablePages)) {
    $contentPage = 'bio';
}
$activeContentPage = $editablePages[$contentPage];

// Config sub-tab
$configTab = $_GET['ctab'] ?? 'basics';
if (!in_array($configTab, ['basics', 'theme', 'support', 'sharing'])) {
    $configTab = 'basics';
}

// Date range
$dateStart = $_GET['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
$dateEnd   = $_GET['date_end']   ?? date('Y-m-d');

$auditActionFilter = trim((string) ($_GET['audit_action'] ?? ''));
$auditUserFilter = trim((string) ($_GET['audit_user'] ?? ''));

$auditEntries = ['entries' => [], 'total' => 0];
$auditActions = [];
$auditActors = [];
$documentationScope = 'operator';
$documentationCatalog = [];
$documentationView = null;
if ($tab === 'audit') {
    $auditEntries = bandpromo_admin_audit_read_entries($dateStart, $dateEnd, $auditActionFilter, $auditUserFilter, 200, 0);
    $auditActions = bandpromo_admin_audit_get_action_types($dateStart, $dateEnd);
    $auditActors = bandpromo_admin_audit_get_actors($dateStart, $dateEnd);
}
if ($tab === 'docs') {
    $documentationScope = bandpromo_docs_role_scope($currentUserRole, (string) ($_GET['doc_scope'] ?? ''));
    $documentationCatalog = bandpromo_docs_catalog($documentationScope);
    $documentationView = bandpromo_docs_render_selected((string) ($_GET['doc'] ?? ''), $documentationScope);
}

// Initialize analytics engine
$analytics = new PlaybackAnalytics();

// Always load platform stats (needed for dashboard chart)
$platformStats = $analytics->getPlatformStats($dateStart, $dateEnd);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="biblioteca/admin.css?v=<?php echo filemtime(__DIR__ . '/biblioteca/admin.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php if ($tab === 'content' && $contentTab === 'pages'): ?>
    <script src="/vendor/tinymce/js/tinymce/tinymce.min.js"></script>
    <?php endif; ?>
    <script src="biblioteca/lightbox.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/lightbox.js'); ?>"></script>
</head>
<body>
    <div class="container">
        <h1>🔐 Admin Panel</h1>
        <p class="app-version">using bandPromo <?php echo htmlspecialchars($appVersion); ?></p>
        <div class="admin-header-bar">
            <div class="user-badge">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <?php if ($currentUserRole !== ''): ?>
                    <span class="role-badge <?php echo $currentUserRole === 'developer' ? 'role-developer' : ($currentUserRole === 'admin' ? 'role-admin' : 'role-user'); ?>"><?php echo htmlspecialchars(ucfirst($currentUserRole)); ?></span>
                <?php endif; ?>
                &nbsp;·&nbsp;<a href="<?php echo htmlspecialchars($siteUrl ?: '/'); ?>" rel="noopener">Open site ↗</a>
                &nbsp;·&nbsp;<a href="/admin.php?logout=1">Logout</a>
            </div>
            <button type="button" id="operatorNotificationsToggle" class="operator-notifications-toggle" aria-expanded="false" aria-controls="operatorNotificationsModal">
                <span class="operator-notifications-icon">🔔</span>
                <span class="operator-notifications-label">Needs attention</span>
                <span id="operatorNotificationsCount" class="operator-notifications-count is-empty">0</span>
            </button>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Primary Tab Navigation -->
        <div class="tabs primary-tabs">
            <?php renderTabLink('welcome',   $tab, $welcomeSetupComplete ? '📊' : '🌍', $welcomeSetupComplete ? 'Dashboard' : 'Welcome'); ?>
            <?php renderTabLink('analytics', $tab, '📊', 'Analytics'); ?>
            <?php renderTabLink('audit',     $tab, '🛡️', 'Audit'); ?>
            <?php renderTabLink('users',     $tab, '👥', 'Users'); ?>
            <?php renderTabLink('files',     $tab, '📁', 'Files'); ?>
            <?php renderTabLink('content',   $tab, '📄', 'Content'); ?>
            <?php renderTabLink('config',    $tab, '⚙️', 'Config'); ?>
            <?php renderTabLink('build',     $tab, '🔨', 'Build'); ?>
            <?php renderTabLink('docs',      $tab, '📚', 'Documentation'); ?>
        </div>

        <!-- ===================== WELCOME TAB ===================== -->
        <div class="tab-content <?php echo $tab === 'welcome' ? 'active' : ''; ?>">
            <div class="tabs sub-tabs">
                <div class="subtab-help-hint-wrap">
                    <span class="subtab-help-hint">Click here to toggle help texts -&gt;</span>
                    <button class="help-toggle-btn collapsed" id="helpBtn-welcome" onclick="toggleHelp('welcome')" title="Show/hide help">ⓘ</button>
                </div>
            </div>
            <div class="admin-help-box collapsed" id="help-welcome">
                <?php if ($welcomeSetupComplete): ?>
                    This page is your dashboard now that setup is complete. Use <strong>Needs attention</strong> in the header or the summary card below to see what still needs doing, then jump to <strong>Files</strong>, <strong>Content</strong>, or <strong>Update site</strong> to fix it. The completed setup checklist stays available below if you want to review what was finished during installation.
                <?php else: ?>
                    Use this page as your setup checklist while bandPromo is still getting the installation ready. bandPromo decides as much as it can on its own, then points you to the next incomplete step: <strong>Config</strong> for identity and branding, <strong>Files</strong> for uploads and metadata, <strong>Content</strong> for Bio / FAQ and playlist shaping, <strong>Build</strong> for publish state, and <strong>Documentation</strong> for deeper explanations.
                <?php endif; ?>
            </div>

            <div class="card welcome-card<?php echo $welcomeSetupComplete ? ' welcome-card-dashboard' : ''; ?>">
                <?php if ($welcomeSetupComplete): ?>
                    <h2>📊 <?php echo htmlspecialchars($siteName); ?> dashboard</h2>
                <?php else: ?>
                    <h2>🌍 Welcome to bandPromo</h2>
                <?php endif; ?>

                <div class="welcome-callout<?php echo $welcomeSetupComplete ? ' welcome-callout-dashboard' : ''; ?>">
                    <?php echo htmlspecialchars($welcomePrimaryNotice); ?>
                </div>

                <div id="operatorNotificationsWelcomeCard" class="welcome-section operator-notifications-welcome"<?php echo $welcomeSetupComplete ? '' : ' style="display:none"'; ?>>
                    <div class="operator-notifications-section-head">
                        <h3>What needs your attention</h3>
                        <span id="operatorNotificationsWelcomeSummary" class="badge audit-status-badge status-neutral">All clear</span>
                    </div>
                    <p id="operatorNotificationsWelcomeStatus" class="card-note">Loading…</p>
                    <button type="button" id="operatorNotificationsWelcomeOpen" class="btn operator-notifications-open-btn">See what to do next</button>
                </div>

                <?php if ($welcomeSetupComplete): ?>
                    <div class="welcome-section">
                        <h3>Quick actions</h3>
                        <ul class="welcome-dashboard-links">
                            <?php foreach ($welcomeDashboardLinks as $link): ?>
                                <li>
                                    <a class="welcome-dashboard-link" href="<?php echo htmlspecialchars($link['href']); ?>"<?php echo !empty($link['external']) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <strong><?php echo htmlspecialchars($link['label']); ?></strong>
                                        <span><?php echo htmlspecialchars($link['description']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <details class="welcome-setup-archive">
                        <summary>Setup checklist (<?php echo (int) $welcomeCompletedChecks; ?> of <?php echo (int) $welcomeTotalChecks; ?> complete)</summary>
                        <ul class="welcome-checklist">
                            <?php foreach ($welcomeChecklist as $check): ?>
                                <li>
                                    <?php $checkSeverityClass = !empty($check['complete']) ? 'is-complete' : ('is-' . htmlspecialchars((string) ($check['severity'] ?? 'nonblocking'))); ?>
                                    <span class="welcome-check-icon <?php echo $checkSeverityClass; ?>"><?php echo !empty($check['complete']) ? '✔' : '○'; ?></span>
                                    <div class="welcome-check-body">
                                        <a class="welcome-link <?php echo $checkSeverityClass; ?>" href="<?php echo htmlspecialchars($check['href']); ?>"><strong><?php echo htmlspecialchars($check['label']); ?></strong></a>
                                        <span><?php echo htmlspecialchars($check['detail']); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php else: ?>
                    <div class="welcome-grid">
                        <div class="welcome-section">
                            <h3>Checklist</h3>
                            <ul class="welcome-checklist">
                                <?php foreach ($welcomeChecklist as $check): ?>
                                    <li>
                                        <?php $checkSeverityClass = !empty($check['complete']) ? 'is-complete' : ('is-' . htmlspecialchars((string) ($check['severity'] ?? 'nonblocking'))); ?>
                                        <span class="welcome-check-icon <?php echo $checkSeverityClass; ?>"><?php echo !empty($check['complete']) ? '✔' : '○'; ?></span>
                                        <div class="welcome-check-body">
                                            <a class="welcome-link <?php echo $checkSeverityClass; ?>" href="<?php echo htmlspecialchars($check['href']); ?>"><strong><?php echo htmlspecialchars($check['label']); ?></strong></a>
                                            <span><?php echo htmlspecialchars($check['detail']); ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="welcome-section">
                            <h3>What to do next</h3>
                            <ol class="welcome-list welcome-list-numbered">
                                <?php foreach ($welcomeNextSteps as $step): ?>
                                    <li>
                                        <a class="welcome-link is-<?php echo htmlspecialchars((string) ($step['severity'] ?? 'nonblocking')); ?>" href="<?php echo htmlspecialchars($step['href']); ?>"><strong><?php echo htmlspecialchars($step['label']); ?></strong></a>
                                        <span><?php echo htmlspecialchars($step['description']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== AUDIT TAB ===================== -->
        <div class="tab-content <?php echo $tab === 'audit' ? 'active' : ''; ?>">
            <div class="tabs sub-tabs">
                <button class="help-toggle-btn collapsed" id="helpBtn-audit" onclick="toggleHelp('audit')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-audit">
                Separate admin audit trail for management actions only. Use this to trace who changed users, content, config, files, and builds, without mixing those records into listener activity analytics.
            </div>

            <form method="GET" class="filter-bar">
                <input type="hidden" name="tab" value="audit">
                <label>Start</label>
                <input type="date" name="date_start" value="<?php echo htmlspecialchars($dateStart); ?>">
                <label>End</label>
                <input type="date" name="date_end" value="<?php echo htmlspecialchars($dateEnd); ?>">
                <label>Action</label>
                <select name="audit_action" onchange="this.form.submit()">
                    <option value="">All actions</option>
                    <?php foreach ($auditActions as $auditAction): ?>
                    <option value="<?php echo htmlspecialchars($auditAction); ?>" <?php echo $auditActionFilter === $auditAction ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($auditAction); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label>User</label>
                <select name="audit_user" onchange="this.form.submit()">
                    <option value="">All admins</option>
                    <?php foreach ($auditActors as $auditActor): ?>
                    <option value="<?php echo htmlspecialchars($auditActor); ?>" <?php echo $auditUserFilter === $auditActor ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($auditActor); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="text-muted">
                    <?php echo $auditEntries['total'] > 200 ? 'Showing 200 of ' . number_format($auditEntries['total']) : number_format($auditEntries['total']); ?> records
                </span>
            </form>

            <?php if (empty($auditEntries['entries'])): ?>
                <p class="empty-msg">No admin audit records found for the selected filters.</p>
            <?php else: ?>
            <div class="table-scroll">
                <table style="font-size:13px;">
                    <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>Status</th><th>Detail</th></tr></thead>
                    <tbody>
                        <?php foreach ($auditEntries['entries'] as $entry): ?>
                        <tr>
                            <td class="text-muted nowrap"><?php echo htmlspecialchars($entry['timestamp'] ?? ''); ?></td>
                            <td><strong><?php echo htmlspecialchars($entry['actor'] ?? ''); ?></strong></td>
                            <td><span class="badge activity-badge"><?php echo htmlspecialchars($entry['action'] ?? ''); ?></span></td>
                            <td>
                                <?php
                                    $targetType = trim((string) ($entry['target_type'] ?? ''));
                                    $targetId = trim((string) ($entry['target_id'] ?? ''));
                                    echo htmlspecialchars($targetType !== '' ? $targetType : '—');
                                    if ($targetId !== '') {
                                        echo '<span style="color:#999;font-size:11px;"> · ' . htmlspecialchars($targetId) . '</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php
                                    $status = trim((string) ($entry['status'] ?? ''));
                                    $statusKey = strtolower($status);
                                    $statusClass = 'status-neutral';
                                    if (in_array($statusKey, ['ok', 'success'], true)) {
                                        $statusClass = 'status-ok';
                                    } elseif (in_array($statusKey, ['error', 'failed', 'failure'], true)) {
                                        $statusClass = 'status-error';
                                    } elseif (in_array($statusKey, ['denied', 'warning'], true)) {
                                        $statusClass = 'status-warning';
                                    }
                                ?>
                                <span class="badge audit-status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status !== '' ? $status : '—'); ?></span>
                            </td>
                            <td class="text-muted">
                                <?php
                                    echo htmlspecialchars(bandpromo_admin_audit_format_detail($entry));
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ===================== ANALYTICS TAB ===================== -->
        <div class="tab-content <?php echo $tab === 'analytics' ? 'active' : ''; ?>">

            <!-- Analytics sub-tab navigation -->
            <div class="tabs sub-tabs">
                <?php renderSubTabLink('dashboard',         $analyticsTab, '📊', 'Dash',        $tab, $dateStart, $dateEnd); ?>
                <?php renderSubTabLink('tracks',            $analyticsTab, '🎵', 'Hitlist',     $tab, $dateStart, $dateEnd); ?>
                <?php renderSubTabLink('user-activities',   $analyticsTab, '👤', 'Activities',  $tab, $dateStart, $dateEnd); ?>
                <?php renderSubTabLink('listening-patterns',$analyticsTab, '🧩', 'Patterns',    $tab, $dateStart, $dateEnd); ?>
                <?php renderSubTabLink('quality',           $analyticsTab, '⚡', 'Quality',     $tab, $dateStart, $dateEnd); ?>
                <?php renderSubTabLink('log',               $analyticsTab, '📋', 'Log',         $tab, $dateStart, $dateEnd); ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-analytics" onclick="toggleHelp('analytics')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-analytics">
                <strong>Dash</strong> gives a quick overview of platform stats. The other tabs show detailed reports — <strong>Hitlist</strong> ranks your most-played songs, <strong>Patterns</strong> shows where people stop or skip, and <strong>Log</strong> shows raw activity entries.
            </div>

            <?php if ($analyticsTab === 'dashboard'): ?>
            <?php renderFilterBar('analytics', $dateStart, $dateEnd); ?>
            <?php [$dashboardListeningValue, $dashboardListeningUnit] = PlaybackAnalytics::formatTimeStat($platformStats['total_listening_time']); ?>
            <div class="stats-grid">
                <?php renderStatCard('Total Plays',    number_format($platformStats['total_plays'])); ?>
                <?php renderStatCard('Listening Time', $dashboardListeningValue, $dashboardListeningUnit); ?>
                <?php renderStatCard('Active Users',   number_format((int)$platformStats['unique_users'])); ?>
                <?php renderStatCard('Play Sessions',  number_format($platformStats['total_sessions'])); ?>
            </div>
            <div class="section">
                <h2>Device Breakdown</h2>
                <table>
                    <thead><tr><th>Device Type</th><th>Count</th><th>Percentage</th></tr></thead>
                    <tbody>
                        <?php
                            $totalEvents = array_sum($platformStats['device_breakdown']);
                            arsort($platformStats['device_breakdown']);
                            foreach ($platformStats['device_breakdown'] as $device => $count):
                                $pct = $totalEvents > 0 ? round(($count / $totalEvents) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?php echo getDeviceBadge($device); ?></td>
                            <td><?php echo number_format($count); ?></td>
                            <td><?php echo $pct; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="section">
                <h2>Hourly Activity Distribution</h2>
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>

            <!-- Top Tracks -->
            <?php elseif ($analyticsTab === 'tracks'): ?>
            <?php $topTracks = $analytics->getTopTracks($dateStart, $dateEnd, 50); ?>
            <?php renderFilterBar('analytics', $dateStart, $dateEnd, 'tracks'); ?>
            <table>
                <thead><tr><th>Rank</th><th>Track Title</th><th>Artist</th><th>Plays</th><th>Total Time</th><th>Avg Time</th><th>Unique Users</th></tr></thead>
                <tbody>
                    <?php if (empty($topTracks)): ?>
                        <?php renderEmptyState(7, 'No track data available'); ?>
                    <?php else: ?>
                        <?php foreach ($topTracks as $i => $track): ?>
                        <tr>
                            <td><strong>#<?php echo $i + 1; ?></strong></td>
                            <td><?php echo htmlspecialchars($track['title']); ?></td>
                            <td><?php echo htmlspecialchars($track['artist']); ?></td>
                            <td><?php echo number_format($track['play_count']); ?></td>
                            <td><?php echo PlaybackAnalytics::formatSeconds($track['total_time']); ?></td>
                            <td><?php echo PlaybackAnalytics::formatSeconds($track['avg_time']); ?></td>
                            <td><?php echo number_format((int)$track['unique_users']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- User Activities -->
            <?php elseif ($analyticsTab === 'user-activities'): ?>
            <?php $allUserStats = $analytics->getUsersListeningStats($dateStart, $dateEnd, 100); ?>
            <?php renderFilterBar('analytics', $dateStart, $dateEnd, 'user-activities'); ?>
            <table>
                <thead><tr><th>Username</th><th>Listening Time</th><th>Plays</th><th>Sessions</th><th>Primary Device</th><th>Last Activity</th></tr></thead>
                <tbody>
                    <?php if (empty($allUserStats)): ?>
                        <?php renderEmptyState(6, 'No user data available for the selected period'); ?>
                    <?php else: ?>
                        <?php foreach ($allUserStats as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo PlaybackAnalytics::formatSeconds($user['listening_time']); ?></td>
                            <td><?php echo number_format($user['play_count']); ?></td>
                            <td><?php echo number_format($user['sessions']); ?></td>
                            <td><?php $md = getMaxDevice($user['devices']); echo !empty($md) ? getDeviceBadge($md) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($user['last_activity']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Listening Patterns -->
            <?php elseif ($analyticsTab === 'listening-patterns'): ?>
            <?php $completionRates = $analytics->getCompletionRates($dateStart, $dateEnd, 100); ?>
            <?php renderFilterBar('analytics', $dateStart, $dateEnd, 'listening-patterns'); ?>
            <p class="hint">Tracks sorted by playlist position. Shows where listeners stop or skip.</p>
            <table>
                <thead><tr><th>#</th><th>Track Title</th><th>Artist</th><th>Total Plays</th><th>Avg Completion</th><th>Skips (≤90%)</th><th>Completion %</th></tr></thead>
                <tbody>
                    <?php if (empty($completionRates)): ?>
                        <?php renderEmptyState(7, 'No completion data available'); ?>
                    <?php else: ?>
                        <?php foreach ($completionRates as $track): ?>
                        <tr>
                            <td class="text-muted"><?php echo $track['track_index'] !== PHP_INT_MAX ? $track['track_index'] + 1 : '&mdash;'; ?></td>
                            <td><?php echo htmlspecialchars($track['title']); ?></td>
                            <td><?php echo htmlspecialchars($track['artist']); ?></td>
                            <td><?php echo number_format($track['total_plays']); ?></td>
                            <td><?php echo $track['avg_completion']; ?>%</td>
                            <td><?php echo number_format($track['skipped_count']); ?></td>
                            <td>
                                <div class="completion-bar-wrap">
                                    <div class="completion-bar" style="width:<?php echo min(100, $track['avg_completion']); ?>%; background:<?php echo $track['avg_completion'] > 80 ? '#28a745' : ($track['avg_completion'] > 50 ? '#ffc107' : '#dc3545'); ?>;">
                                        <?php echo $track['avg_completion'] > 10 ? round($track['avg_completion']) . '%' : ''; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Quality -->
            <?php elseif ($analyticsTab === 'quality'): ?>
            <?php $qualityStats = $analytics->getQualityStats($dateStart, $dateEnd); ?>
            <?php renderFilterBar('analytics', $dateStart, $dateEnd, 'quality'); ?>
            <?php
                $total   = $qualityStats['real_data_entries'] + $qualityStats['inferred_entries'];
                $realPct = $total > 0 ? round($qualityStats['real_data_entries'] / $total * 100) : 0;
                [$originalListenValue, $originalListenUnit] = PlaybackAnalytics::formatTimeStat($qualityStats['original_listening_time']);
                [$optimizedListenValue, $optimizedListenUnit] = PlaybackAnalytics::formatTimeStat($qualityStats['lq_listening_time']);
            ?>
            <p class="hint">
                Quality sourced from player logs (direct).
                <?php if ($qualityStats['inferred_entries'] > 0): ?>
                    <?php echo $realPct; ?>% of entries have real quality data;
                    <?php echo 100 - $realPct; ?>% fall back to device-type inference (legacy logs).
                <?php else: ?>
                    All entries have real quality data.
                <?php endif; ?>
            </p>
            <div class="stats-grid">
                <?php renderStatCard('Original', number_format($qualityStats['original'])); ?>
                <?php renderStatCard('Optimized', number_format($qualityStats['lq'])); ?>
                <?php renderStatCard('Original Listen Time', $originalListenValue, $originalListenUnit); ?>
                <?php renderStatCard('Optimized Listen Time', $optimizedListenValue, $optimizedListenUnit); ?>
            </div>
            <div class="section">
                <h2>Quality Breakdown by Device</h2>
                <table>
                    <thead><tr><th>Device</th><th>Original</th><th>Optimized</th><th>Original %</th></tr></thead>
                    <tbody>
                        <?php foreach ($qualityStats['by_device'] as $device => $counts): ?>
                        <tr>
                            <td><?php echo getDeviceBadge($device); ?></td>
                            <td><?php echo number_format($counts['original']); ?></td>
                            <td><?php echo number_format($counts['lq']); ?></td>
                            <td><?php echo formatQualityPercentage($counts['original'], $counts['lq']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Log -->
            <?php elseif ($analyticsTab === 'log'): ?>
            <?php
                $logActivityFilter = $_GET['activity_filter'] ?? '';
                $activityTypes     = $analytics->getActivityTypes($dateStart, $dateEnd);
                $rawLog            = $analytics->getRawLog($dateStart, $dateEnd, $logActivityFilter ?: null, null, 200);
                $logEntries        = $rawLog['entries'];
                $logTotal          = $rawLog['total'];
            ?>
            <?php renderFilterBar('analytics', $dateStart, $dateEnd, 'log'); ?>

            <!-- Activity type filter -->
            <form method="GET" class="filter-bar">
                <input type="hidden" name="tab"        value="analytics">
                <input type="hidden" name="atab"       value="log">
                <input type="hidden" name="date_start" value="<?php echo htmlspecialchars($dateStart); ?>">
                <input type="hidden" name="date_end"   value="<?php echo htmlspecialchars($dateEnd); ?>">
                <label>Activity</label>
                <select name="activity_filter" onchange="this.form.submit()">
                    <option value="">All activities</option>
                    <?php foreach ($activityTypes as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $logActivityFilter === $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="text-muted">
                    <?php echo $logTotal > 200 ? 'Showing 200 of ' . number_format($logTotal) : number_format($logTotal); ?> entries
                </span>
            </form>

            <?php if (empty($logEntries)): ?>
                <p class="empty-msg">No log entries found.</p>
            <?php else: ?>
            <div class="table-scroll">
                <table style="font-size:13px;">
                    <thead><tr><th>Time</th><th>User</th><th>Activity</th><th>Track</th><th>Detail</th></tr></thead>
                    <tbody>
                        <?php foreach ($logEntries as $entry): ?>
                        <tr>
                            <td class="text-muted nowrap"><?php echo htmlspecialchars($entry['timestamp'] ?? ''); ?></td>
                            <td><strong><?php echo htmlspecialchars($entry['username'] ?? ''); ?></strong></td>
                            <td><span class="badge activity-badge"><?php echo htmlspecialchars($entry['activity'] ?? ''); ?></span></td>
                            <td>
                                <?php
                                    $logRow = bandpromo_describe_log_entry($entry);
                                    echo htmlspecialchars($logRow['track_primary']);
                                    if (!empty($logRow['track_secondary'])) {
                                        echo ' <span style="color:#999;font-size:11px;">— ' . htmlspecialchars($logRow['track_secondary']) . '</span>';
                                    }
                                ?>
                            </td>
                            <td class="text-muted">
                                <?php
                                    echo htmlspecialchars($logRow['detail']);
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        </div>

        <!-- ===================== USERS TAB ===================== -->
        <div class="tab-content <?php echo $tab === 'users' ? 'active' : ''; ?>">
            <div class="tabs sub-tabs">
                <button class="help-toggle-btn collapsed" id="helpBtn-users" onclick="toggleHelp('users')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-users">
                <strong>Admin</strong> users handle day-to-day operation. <strong>Developer</strong> users can access the same panel but also see developer-only documentation. <strong>User</strong> accounts cannot open this admin panel.
            </div>

            <div class="users-toolbar">
                <h2>👥 Users (<?php echo count($users); ?>)</h2>
                <button class="btn-primary" onclick="openUserModal()">➕ Add User</button>
            </div>

            <!-- Search filter -->
            <div class="filter-bar">
                <label>Search</label>
                <input type="text" id="userSearch" placeholder="Filter by username…" oninput="filterUsers(this.value)" style="max-width:260px;">
            </div>

            <div class="users-list" id="usersList">
                <?php if (empty($users)): ?>
                    <p class="empty-msg">No users found.</p>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <?php $uname = $u['username']; $urole = $u['role']; ?>
                    <div class="user-item <?php echo ($uname === $_SESSION['username']) ? 'current' : ''; ?>" data-username="<?php echo htmlspecialchars($uname); ?>">
                        <span class="user-name" onclick="openUserDetail('<?php echo htmlspecialchars($uname, ENT_QUOTES); ?>')" title="View details">
                            <?php echo htmlspecialchars($uname); ?>
                            <?php if ($uname === $_SESSION['username']): ?><strong class="you-badge">(You)</strong><?php endif; ?>
                            <span class="role-badge <?php echo $urole === 'developer' ? 'role-developer' : ($urole === 'admin' ? 'role-admin' : 'role-user'); ?>">
                                <?php echo $urole === 'developer' ? '💻 Developer' : ($urole === 'admin' ? '🛡️ Admin' : '👤 User'); ?>
                            </span>
                        </span>
                        <span class="user-actions">
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="role_username" value="<?php echo htmlspecialchars($uname); ?>">
                                <select name="new_role" class="user-role-select" aria-label="Role for <?php echo htmlspecialchars($uname); ?>">
                                    <option value="admin" <?php echo $urole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="developer" <?php echo $urole === 'developer' ? 'selected' : ''; ?>>Developer</option>
                                    <option value="user" <?php echo $urole === 'user' ? 'selected' : ''; ?> <?php echo $uname === $_SESSION['username'] ? 'disabled' : ''; ?>>User</option>
                                </select>
                                <button type="submit" class="icon-btn" title="Apply role">💾</button>
                            </form>
                            <button class="icon-btn" title="Change password" onclick="openUserModal('<?php echo htmlspecialchars($uname, ENT_QUOTES); ?>')">🔑</button>
                            <?php if ($uname !== $_SESSION['username']): ?>
                            <button class="icon-btn danger" title="Delete user" onclick="deleteUser('<?php echo htmlspecialchars($uname, ENT_QUOTES); ?>')">🗑️</button>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===================== FILES TAB ===================== -->
        <div id="tab-files" class="tab-content <?php echo $tab === 'files' ? 'active' : ''; ?>">

            <!-- Files sub-tab navigation -->
            <div class="tabs sub-tabs">
                <?php
                $filePanels = [
                    'audio'         => ['🎵', 'Audio'],
                    'photos'        => ['📷', 'Photo'],
                    'video'         => ['🎬', 'Video'],
                    'illustrations' => ['🖼️', 'Illustrations'],
                    'special'       => ['✨', 'Theme'],
                ];
                foreach ($filePanels as $fp => [$emoji, $label]):
                    $active = $fp === $filesPanel ? 'active' : '';
                    $url = '?tab=files&fpanel=' . urlencode($fp);
                ?>
                <a href="<?php echo $url; ?>" class="tab-link <?php echo $active; ?>">
                    <?php echo htmlspecialchars($emoji . ' ' . $label); ?>
                </a>
                <?php endforeach; ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-files" onclick="toggleHelp('files')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-files">
                <?php if ($filesPanel === 'audio'): ?>
                    <strong>Your files are safe here.</strong>😁
                    <br>bandPromo always keeps the original file you add, so you can work without worrying about losing it.
                    <br>At the same time, bandPromo prepares the versions needed to give visitors a smoother, better listening experience.
                    <br><strong>This list shows what is ready and what still needs a little attention.</strong>
                    <br>Green means good, amber means it could be improved, and red means required data is missing and the build can be blocked.
                    <br>Better track details mean clearer pages, better playback information, and a more polished experience for everyone, so click a track to show editable metadata tags.
                    <br>Click a tag bullet to edit short fields such as artist, title, version, release, track, release date, genre, BPM, or key. Use the pencil button for cover art, description, lyrics, and packaging details.
                    <br>
                <?php elseif ($filesPanel === 'photos'): ?>
                    Drop band and promo photos here (PNG, JPG, WEBP). Use your best quality images. Unreferenced uploads are flagged as orphans so you can clean them up safely.
                    <br><strong>After upload:</strong> use <strong>Refresh Image Files</strong>.
                <?php elseif ($filesPanel === 'video'): ?>
                    Drop videos here (MP4, WEBM, MOV). bandPromo keeps the original here and prepares a publish-ready MP4 during the full build. Unreferenced uploads are flagged as orphans so you can clean them up safely.
                    <br><strong>After upload:</strong> use <strong>Run Publish Build</strong> so the publish-ready video files can be refreshed.
                <?php elseif ($filesPanel === 'illustrations'): ?>
                    Drop artwork, track covers, and illustrations here (PNG, JPG, JPEG). Rows show whether each file is a track cover, release fallback, or general artwork, plus where it is referenced.
                    <br><strong>After upload:</strong> use <strong>Refresh Image Files</strong>.
                <?php elseif ($filesPanel === 'special'): ?>
                    This is for theme assets such as share images, icons, logos, and similar install-specific design files.
                    <br><strong>After upload:</strong> usually no build needed.
                <?php endif; ?>
            </div>

            <!-- Audio -->
            <div class="media-panel card" id="panel-audio" <?php echo $filesPanel !== 'audio' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            <?php echo bandpromo_admin_files_permanent_warning_line(true); ?>
                            <br>Drag and drop audio files here to add them directly. Click a track row to quick-edit common tags, or use the pencil button for the full metadata editor.
                            <br>Select multiple files for group download or deletion.
                        </span>
                    </div>
                    <div class="media-panel-actions">
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                        <button type="button" class="btn bundled-demo-toggle media-display-toggle" data-audio-display-toggle data-audio-display-mode="master" aria-pressed="true" title="Show original files">◉ Master</button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-bulk-download-btn" data-bulk-download-target="audio" data-download-variant="current" disabled aria-label="Download selected audio files" title="Download selected audio files">⬇</button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-bulk-delete-btn" data-bulk-delete-target="audio" disabled aria-label="Delete selected audio files" title="Delete selected audio files">🗑️</button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn" onclick="openUploadModal('audio')" aria-label="Add audio files" title="Add audio files">＋</button>
                    </div>
                </div>
                <div id="filelist-audio" class="media-file-list"><span class="text-muted">Loading…</span></div>
                <div class="media-panel-footer"><span id="audio-count" class="media-count"></span></div>
            </div>

            <!-- Video -->
            <div class="media-panel card" id="panel-video" <?php echo $filesPanel !== 'video' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            <?php echo bandpromo_admin_files_permanent_warning_line(); ?>
                            <br>Drag and drop video files here to add them directly. Rows show whether each file is used by the gallery or theme background video. Use the filters to review orphans.
                        </span>
                    </div>
                    <div class="media-panel-actions">
                        <button type="button" class="btn media-ref-filter-toggle active" data-media-ref-filter-target="video" data-media-ref-filter="all" aria-pressed="true" title="Show all video files">All</button>
                        <button type="button" class="btn media-ref-filter-toggle" data-media-ref-filter-target="video" data-media-ref-filter="referenced" aria-pressed="false" title="Show referenced video files only">In use</button>
                        <button type="button" class="btn media-ref-filter-toggle" data-media-ref-filter-target="video" data-media-ref-filter="orphans" aria-pressed="false" title="Show unreferenced video files only">Orphans</button>
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-bulk-download-btn" data-bulk-download-target="video" data-download-variant="original" disabled aria-label="Download selected video files" title="Download selected video files">⬇</button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-bulk-delete-btn" data-bulk-delete-target="video" disabled aria-label="Delete selected video files" title="Delete selected video files">🗑️</button>
                    <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn" onclick="openUploadModal('video')" aria-label="Add video files" title="Add video files">＋</button>
                    </div>
                </div>
                <div id="filelist-video" class="media-file-list"><span class="text-muted">Loading…</span></div>
                <div class="media-panel-footer"><span id="video-count" class="media-count"></span></div>
            </div>

            <!-- Illustrations -->
            <div class="media-panel card" id="panel-illustrations" <?php echo $filesPanel !== 'illustrations' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            <?php echo bandpromo_admin_files_permanent_warning_line(); ?>
                            <br>Drag and drop artwork here to add track covers and illustrations. Use the filters to review track covers, build-generated files, or orphans.
                        </span>
                    </div>
                    <div class="media-panel-actions">
                        <button type="button" class="btn cover-filter-toggle active" data-cover-filter="all" aria-pressed="true" title="Show all illustration files">All</button>
                        <button type="button" class="btn cover-filter-toggle" data-cover-filter="track-covers" aria-pressed="false" title="Show track cover files only">Covers</button>
                        <button type="button" class="btn cover-filter-toggle" data-cover-filter="orphans" aria-pressed="false" title="Show unreferenced files only">Orphans</button>
                        <button type="button" class="btn cover-filter-toggle" data-cover-filter="build-generated" aria-pressed="false" title="Show build-generated cover files only">Built</button>
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-bulk-download-btn" data-bulk-download-target="illustrations" data-download-variant="original" disabled aria-label="Download selected illustration files" title="Download selected illustration files">⬇</button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-bulk-delete-btn" data-bulk-delete-target="illustrations" disabled aria-label="Delete selected illustration files" title="Delete selected illustration files">🗑️</button>
                    <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn" onclick="openUploadModal('illustrations')" aria-label="Add illustration files" title="Add illustration files">＋</button>
                    </div>
                </div>
                <div id="filelist-illustrations" class="media-file-list"><span class="text-muted">Loading…</span></div>
                <div class="media-panel-footer"><span id="illustrations-count" class="media-count"></span></div>
            </div>

            <!-- Photos -->
            <div class="media-panel card" id="panel-photos" <?php echo $filesPanel !== 'photos' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            <?php echo bandpromo_admin_files_permanent_warning_line(); ?>
                            <br>Drag and drop photo files here to add them directly. Rows show whether each file is used by the gallery or theme settings. Use the filters to review orphans.
                        </span>
                    </div>
                    <div class="media-panel-actions">
                        <button type="button" class="btn media-ref-filter-toggle active" data-media-ref-filter-target="photos" data-media-ref-filter="all" aria-pressed="true" title="Show all photo files">All</button>
                        <button type="button" class="btn media-ref-filter-toggle" data-media-ref-filter-target="photos" data-media-ref-filter="referenced" aria-pressed="false" title="Show referenced photo files only">In use</button>
                        <button type="button" class="btn media-ref-filter-toggle" data-media-ref-filter-target="photos" data-media-ref-filter="orphans" aria-pressed="false" title="Show unreferenced photo files only">Orphans</button>
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-bulk-download-btn" data-bulk-download-target="photos" data-download-variant="original" disabled aria-label="Download selected photo files" title="Download selected photo files">⬇</button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-bulk-delete-btn" data-bulk-delete-target="photos" disabled aria-label="Delete selected photo files" title="Delete selected photo files">🗑️</button>
                    <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn" onclick="openUploadModal('photos')" aria-label="Add photo files" title="Add photo files">＋</button>
                    </div>
                </div>
                <div id="filelist-photos" class="media-file-list"><span class="text-muted">Loading…</span></div>
                <div class="media-panel-footer"><span id="photos-count" class="media-count"></span></div>
            </div>

            <!-- Special -->
            <div class="media-panel card" id="panel-special" <?php echo $filesPanel !== 'special' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            <?php echo bandpromo_admin_files_permanent_warning_line(); ?>
                            <br>Drag and drop theme files here to add them directly. Select multiple files for group download or deletion.
                        </span>
                    </div>
                    <div class="media-panel-actions">
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-bulk-download-btn" data-bulk-download-target="special" data-download-variant="original" disabled aria-label="Download selected theme files" title="Download selected theme files">⬇</button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-bulk-delete-btn" data-bulk-delete-target="special" disabled aria-label="Delete selected theme files" title="Delete selected theme files">🗑️</button>
                    <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn" onclick="openUploadModal('special')" aria-label="Add theme files" title="Add theme files">＋</button>
                    </div>
                </div>
                <div id="filelist-special" class="media-file-list"><span class="text-muted">Loading…</span></div>
                <div class="media-panel-footer"><span id="special-count" class="media-count"></span></div>
            </div>

            <!-- Upload modal (shared) -->
            <div id="mediaUploadModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeUploadModal()">
                <div class="modal-box">
                    <button class="modal-close" onclick="closeUploadModal()">✕</button>
                    <h3 id="mediaModalTitle">Add files</h3>
                    <div class="drop-zone" id="modalDropZone">
                        Drop files here, or <strong>click to choose</strong>
                        <input type="file" id="modalFileInput" multiple style="display:none">
                    </div>
                    <div id="modalFileList" class="modal-file-list"></div>
                    <div class="modal-actions">
                        <button id="modalUploadBtn" class="btn btn-primary" disabled>⬆️ Upload</button>
                        <span id="modalUploadStatus" class="status-text"></span>
                    </div>
                </div>
            </div>

            <!-- Delete confirm modal -->
            <div id="mediaDeleteModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeDeleteModal()">
                <div class="modal-box">
                    <button class="modal-close" onclick="closeDeleteModal()">✕</button>
                    <h3 id="mediaDeleteTitle">Delete file?</h3>
                    <p id="mediaDeleteName" class="delete-confirm-name"></p>
                    <div id="mediaDeleteList" class="modal-file-list" style="display:none"></div>
                    <p id="mediaDeleteHint" class="text-muted">This cannot be undone.</p>
                    <div class="modal-actions">
                        <button id="mediaDeleteConfirmBtn" class="btn btn-primary icon-btn danger">Delete</button>
                        <button class="btn" onclick="closeDeleteModal()">Cancel</button>
                        <span id="mediaDeleteStatus" class="status-text"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== CONTENT TAB ===================== -->
        <div class="tab-content <?php echo $tab === 'content' ? 'active' : ''; ?>">

            <!-- Content sub-tab navigation -->
            <div class="tabs sub-tabs">
                <?php
                $cntTabs = [
                    'playlist' => ['🎵', 'Playlist'],
                    'gallery'  => ['🖼️', 'Gallery'],
                    'pages'    => ['📝', 'Pages'],
                ];
                foreach ($cntTabs as $ct => [$emoji, $label]):
                    $active = $ct === $contentTab ? 'active' : '';
                    $url = '?tab=content&cntab=' . urlencode($ct);
                    if ($ct === 'pages') {
                        $url .= '&page=' . urlencode($contentPage);
                    }
                ?>
                <a href="<?php echo $url; ?>" class="tab-link <?php echo $active; ?>">
                    <?php echo htmlspecialchars($emoji . ' ' . $label); ?>
                </a>
                <?php endforeach; ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-content" onclick="toggleHelp('content')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-content">
                <?php if ($contentTab === 'playlist'): ?>
                    Your playlist is built automatically from your audio files and their ID3 tags (title, artist, album). Upload audio via the <a href="?tab=files&fpanel=audio">Files → Audio</a> tab, then run a <a href="?tab=build">Build</a> to regenerate the playlist.
                <?php elseif ($contentTab === 'gallery'): ?>
                    A JSON list of images shown in the site's gallery section. Each item needs a <code>src</code> path, <code>name</code>, and <code>alt</code> text. The preview below updates as you type — fix any red errors before saving.
                <?php elseif ($contentTab === 'pages'): ?>
                    Edit your public text pages with rich text tools or source view. Only safe formatting and optimized local content images are kept when you save, and changes apply immediately without a build.
                <?php endif; ?>
            </div>

            <!-- ── PLAYLIST ─────────────────────────────────────────────── -->
            <?php if ($contentTab === 'playlist'): ?>
            <div class="card">
                <div class="media-panel-header">
                    <h3 style="margin:0;">🎵 Playlist Order</h3>
                    <div class="media-panel-actions">
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                    </div>
                </div>
                <p class="card-note">
                    Drag tracks into the open insertion gap to reorder them. Use Shift-click or Ctrl/Cmd-click to select multiple tracks and move them together.
                    Saving still preserves the order for future builds, while the currently published playlist stays on its last built state until you rebuild.
                </p>
                <p id="playlistPreviewHint" class="hint">Loading current source tracks…</p>
                <ol class="playlist-editor" id="playlistEditor"></ol>
                <div class="card-actions">
                    <button id="playlistSaveBtn" class="btn btn-primary">💾 Save order</button>
                    <span id="playlistStatus" class="status-text"></span>
                </div>
            </div>

            <!-- ── GALLERY ────────────────────────────────────────────────── -->
            <?php elseif ($contentTab === 'gallery'): ?>
            <?php
                $gf = __DIR__ . '/data/gallery.json';
                $galleryError = null;
                $galleryItems = [];
                if (!file_exists($gf)) {
                    $galleryError = 'Missing required runtime file: data/gallery.json. Run setup to seed templates.';
                } else {
                    $raw = file_get_contents($gf);
                    if ($raw === false) {
                        $galleryError = 'Could not read data/gallery.json.';
                    } else {
                        $galleryItems = json_decode($raw, true);
                        if (!is_array($galleryItems)) {
                            $galleryError = 'data/gallery.json is not a valid JSON array.';
                            $galleryItems = [];
                        }
                    }
                }
            ?>
            <?php if ($galleryError): ?>
            <div class="card" style="border-color:#f87171">
                <p class="card-note" style="color:#f87171"><?php echo htmlspecialchars($galleryError); ?></p>
            </div>
            <?php else: ?>
            <div class="card">
                <h3>🖼️ Gallery</h3>
                <p class="card-note">
                    Pick photos and videos from your uploaded media. Drag active items to reorder.
                    Name and alt text can be edited inline. Changes apply immediately — no build required.
                </p>
                <div class="gallery-editor" id="galleryEditor"
                     data-initial="<?php echo htmlspecialchars(json_encode($galleryItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="gallery-editor-col gallery-editor-available">
                        <h4 class="gallery-editor-col-title">Available media</h4>
                        <div id="galleryAvailableList" class="gallery-available-list">
                            <p class="hint">Loading…</p>
                        </div>
                    </div>
                    <div class="gallery-editor-col gallery-editor-active">
                        <h4 class="gallery-editor-col-title">
                            Gallery <span id="galleryActiveCount" class="gallery-count-badge"></span>
                        </h4>
                        <ol id="galleryActiveList" class="gallery-active-list"></ol>
                        <div class="card-actions">
                            <button id="gallerySaveBtn" class="btn btn-primary">💾 Save gallery</button>
                            <span id="galleryStatus" class="status-text"></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── PAGES ───────────────────────────────────────────────────── -->
            <?php elseif ($contentTab === 'pages'): ?>
            <div class="card">
                <div class="tabs sub-tabs" style="margin-bottom:18px;">
                    <?php foreach ($editablePages as $pageKey => $pageSpec): ?>
                    <?php
                        $pageUrl = '?tab=content&cntab=pages&page=' . urlencode($pageKey);
                        $pageActive = $pageKey === $contentPage ? 'active' : '';
                    ?>
                    <a href="<?php echo $pageUrl; ?>" class="tab-link <?php echo $pageActive; ?>">
                        <?php echo htmlspecialchars($pageSpec['emoji'] . ' ' . $pageSpec['label']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <h3><?php echo htmlspecialchars($activeContentPage['emoji'] . ' ' . $activeContentPage['title']); ?></h3>
                <p class="card-note">
                    <?php echo htmlspecialchars($activeContentPage['description']); ?> Edit it with rich text tools or source view.
                    Only safe formatting and optimized local content images are kept when you save.
                </p>
                <textarea id="pageEditor" class="code-editor" spellcheck="false" style="height:520px"
                          data-page-key="<?php echo htmlspecialchars($contentPage, ENT_QUOTES, 'UTF-8'); ?>"
                          data-page-label="<?php echo htmlspecialchars($activeContentPage['label'], ENT_QUOTES, 'UTF-8'); ?>"><?php
                $pageFile = $activeContentPage['file'];
                if (!file_exists($pageFile)) {
                    http_response_code(500);
                    echo htmlspecialchars('Missing required runtime file: data/' . $contentPage . '.html. Run setup to seed templates.');
                } else {
                    echo htmlspecialchars(file_get_contents($pageFile) ?: '');
                }
                ?></textarea>
                <p class="field-note">
                    Inserted content images are limited to optimized files from your local media library.
                </p>
                <div class="card-actions">
                    <button id="pageSaveBtn" class="btn btn-primary">💾 Save <?php echo htmlspecialchars(strtolower($activeContentPage['label'])); ?></button>
                    <span id="pageStatus" class="status-text"></span>
                </div>
            </div>

            <?php endif; ?>
        </div>

        <!-- ===================== CONFIG TAB ===================== -->
        <div class="tab-content <?php echo $tab === 'config' ? 'active' : ''; ?>">

            <!-- Config sub-tab navigation -->
            <div class="tabs sub-tabs">
                <?php
                $cfgTabs = [
                    'basics'  => ['⚙️', 'Basics'],
                    'theme'   => ['🎨', 'Theme'],
                    'support' => ['💛', 'Support'],
                    'sharing' => ['🔗', 'Sharing'],
                ];
                foreach ($cfgTabs as $ct => [$emoji, $label]):
                    $active = $ct === $configTab ? 'active' : '';
                    $url = '?tab=config&ctab=' . urlencode($ct);
                ?>
                <a href="<?php echo $url; ?>" class="tab-link <?php echo $active; ?>">
                    <?php echo htmlspecialchars($emoji . ' ' . $label); ?>
                </a>
                <?php endforeach; ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-config" onclick="toggleHelp('config')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-config">
                <?php if ($configTab === 'basics'): ?>
                    Basics is the place for your public site title, URL, description, language, and author details. <strong>Save validates only the basics fields</strong>, then writes them back into the full config. If internal config sections are missing, use the <strong>Repair</strong> link to restore them from the config template.
                <?php elseif ($configTab === 'theme'): ?>
                    Theme is the place for visible presentation assets such as the logo, primary cover image, welcome audio, and background media. <strong>Save validates only the theme fields</strong>, then writes them back into the full config. Most path-only changes apply immediately; changing the primary cover image may still queue follow-up image optimization.
                <?php elseif ($configTab === 'support'): ?>
                    Support is where you decide whether the public player should show a support call-to-action at all, where it should send visitors, and how visible it should be. Use a simple link button when you want the safest, most portable setup. Use the Ko-fi widget only when you intentionally want Ko-fi's hosted script and overlay behavior on your site. bandPromo does not verify payments or memberships here in v0.7; it only controls presentation.
                <?php elseif ($configTab === 'sharing'): ?>
                    Controls how your site appears when shared on Facebook, X (Twitter), and other platforms, and also holds the lightweight SEO/manifest fields used for keywords and categories. The preview cards below update live as you type. Make sure the <strong>share image path</strong> points to an existing file in the Theme panel.
                <?php endif; ?>
            </div>

            <!-- ── BASICS ──────────────────────────────────────────────────── -->
            <?php if ($configTab === 'basics'): ?>
            <?php
            $cfgExamplePath = __DIR__ . '/biblioteca/templates/web-config.template.json';
            $cfgCurrentPath = __DIR__ . '/web-config.json';
            if (file_exists($cfgExamplePath) && file_exists($cfgCurrentPath)) {
                $cfgExample = json_decode(file_get_contents($cfgExamplePath), true) ?? [];
                $cfgCurrent = json_decode(file_get_contents($cfgCurrentPath), true) ?? [];
                $missingSections = array_diff(array_keys($cfgExample), array_keys($cfgCurrent));
            } else {
                $missingSections = [];
            }
            ?>
            <?php if (!empty($missingSections)): ?>
            <div style="background:rgba(244,67,54,.1);border:1px solid rgba(244,67,54,.35);color:#f87171;
                        border-radius:8px;padding:14px 18px;margin-bottom:16px;font-size:13px;">
                ⚠️ <strong>Incomplete config:</strong> one or more internal config sections are missing.
                <a href="?tab=config&amp;ctab=basics&amp;repair=1" style="color:#f87171;text-decoration:underline;">Repair now</a>
            </div>
            <?php endif; ?>
            <?php
            // Repair action: deep-merge example into current config
            if (isset($_GET['repair']) && !empty($cfgExample) && !empty($cfgCurrent)) {
                function admin_deep_merge(array $base, array $overlay): array {
                    foreach ($overlay as $k => $v) {
                        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                            $base[$k] = admin_deep_merge($base[$k], $v);
                        } else { $base[$k] = $v; }
                    }
                    return $base;
                }
                $cfgRepaired = admin_deep_merge($cfgExample, $cfgCurrent);
                bandpromo_sync_scoped_config_fields($cfgRepaired, ['site', 'social', 'media']);
                file_put_contents($cfgCurrentPath, json_encode($cfgRepaired, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                header('Location: ?tab=config&ctab=basics'); exit;
            }
            $cfgFull = bandpromo_load_runtime_config_raw();
            $cfgSite = $cfgFull['site'] ?? [];
            ?>
            <div class="card">
                <h3>⚙️ Site Basics</h3>
                <p class="card-note">
                    Edit the everyday public site details here without touching theme media paths or sharing settings.
                </p>
                <div class="config-form-grid">
                    <div class="form-group">
                        <label for="cfg_site_name">Site title</label>
                        <input type="text" id="cfg_site_name" value="<?php echo htmlspecialchars((string) ($cfgSite['name'] ?? '')); ?>" placeholder="Your release or site title">
                    </div>
                    <div class="form-group">
                        <label for="cfg_site_short_name">Short label</label>
                        <input type="text" id="cfg_site_short_name" value="<?php echo htmlspecialchars((string) ($cfgSite['short_name'] ?? '')); ?>" placeholder="Short name for app labels and compact views">
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_site_description">Description</label>
                        <textarea id="cfg_site_description" class="config-form-textarea" rows="4" placeholder="A short public description used in previews and meta tags"><?php echo htmlspecialchars((string) ($cfgSite['description'] ?? '')); ?></textarea>
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_site_url">Site URL</label>
                        <input type="text" id="cfg_site_url" value="<?php echo htmlspecialchars((string) ($cfgSite['url'] ?? '')); ?>" placeholder="https://example.com">
                    </div>
                    <div class="form-group">
                        <label for="cfg_site_language">Language code</label>
                        <input type="text" id="cfg_site_language" value="<?php echo htmlspecialchars((string) ($cfgSite['language'] ?? '')); ?>" placeholder="en">
                    </div>
                    <div class="form-group">
                        <label for="cfg_site_author">Author / owner</label>
                        <input type="text" id="cfg_site_author" value="<?php echo htmlspecialchars((string) ($cfgSite['author'] ?? '')); ?>" placeholder="Artist, band, label, or project owner">
                    </div>
                </div>
                <textarea id="cfgBasicsFullSource" style="display:none"><?php echo htmlspecialchars(json_encode($cfgFull, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?></textarea>
                <div class="card-actions">
                    <button id="cfgBasicsSaveBtn" class="btn btn-primary">💾 Save basics</button>
                    <span id="cfgBasicsStatus" class="status-text"></span>
                </div>
            </div>

            <!-- ── THEME ───────────────────────────────────────────────────── -->
            <?php elseif ($configTab === 'theme'): ?>
            <?php
            $cfgFull = bandpromo_load_runtime_config_raw();
            $cfgTheme = $cfgFull['media'] ?? [];
            $themeLogoPath = (string) ($cfgTheme['logo'] ?? '');
            $themeCoverPath = (string) ($cfgTheme['cover'] ?? '');
            $themeBackgroundImagePath = (string) ($cfgTheme['background_image'] ?? '');
            $themeBackgroundVideoPath = (string) ($cfgTheme['background_video'] ?? '');
            $themeWelcomeAudioPath = (string) ($cfgTheme['welcome_audio'] ?? '');
            $themeLoggedinAudioPath = (string) ($cfgTheme['loggedin_audio'] ?? '');
            $themeLogoLabel = $themeLogoPath !== '' ? basename(str_replace('\\', '/', $themeLogoPath)) : 'No logo selected';
            $themeCoverLabel = $themeCoverPath !== '' ? basename(str_replace('\\', '/', $themeCoverPath)) : 'No cover selected';
            $themeBackgroundImageLabel = $themeBackgroundImagePath !== '' ? basename(str_replace('\\', '/', $themeBackgroundImagePath)) : 'No background image selected';
            $themeBackgroundVideoLabel = $themeBackgroundVideoPath !== '' ? basename(str_replace('\\', '/', $themeBackgroundVideoPath)) : 'No background video selected';
            $themeWelcomeAudioLabel = $themeWelcomeAudioPath !== '' ? basename(str_replace('\\', '/', $themeWelcomeAudioPath)) : 'No welcome audio selected';
            $themeLoggedinAudioLabel = $themeLoggedinAudioPath !== '' ? basename(str_replace('\\', '/', $themeLoggedinAudioPath)) : 'No logged-in audio selected';
            ?>
            <div class="card">
                <h3>🎨 Theme / Media Presentation</h3>
                <p class="card-note">
                    Choose the visible presentation assets here, such as logo, primary cover image, welcome audio, and background media, without touching internal file paths. Most reference changes apply immediately; the primary cover image is the main field that can still require follow-up delivery refresh.
                </p>
                <div class="config-form-grid">
                    <div class="form-group config-form-full">
                        <label for="cfg_theme_logo_picker">Logo</label>
                        <input type="hidden" id="cfg_theme_logo" value="<?php echo htmlspecialchars($themeLogoPath); ?>" data-empty-label="No logo selected">
                        <div class="asset-picker-control" id="cfg_theme_logo_picker">
                            <span id="cfg_theme_logo_label" class="asset-picker-value<?php echo $themeLogoPath === '' ? ' empty' : ''; ?>"><?php echo htmlspecialchars($themeLogoLabel); ?></span>
                            <div class="asset-picker-actions">
                                <button type="button" class="icon-btn media-picker-open" data-field="cfg_theme_logo" data-title="Choose logo" data-targets="special">Choose file</button>
                            </div>
                        </div>
                        <div class="field-note">Pick from uploaded theme assets. The internal storage path stays hidden.</div>
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_theme_cover_picker">Primary cover</label>
                        <input type="hidden" id="cfg_theme_cover" value="<?php echo htmlspecialchars($themeCoverPath); ?>" data-empty-label="No cover selected">
                        <div class="asset-picker-control" id="cfg_theme_cover_picker">
                            <span id="cfg_theme_cover_label" class="asset-picker-value<?php echo $themeCoverPath === '' ? ' empty' : ''; ?>"><?php echo htmlspecialchars($themeCoverLabel); ?></span>
                            <div class="asset-picker-actions">
                                <button type="button" class="icon-btn media-picker-open" data-field="cfg_theme_cover" data-title="Choose primary cover" data-targets="special,illustrations,photos">Choose file</button>
                            </div>
                        </div>
                        <div class="field-note">Changing the primary cover can trigger a follow-up image refresh for delivery assets.</div>
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_theme_background_image_picker">Background image</label>
                        <input type="hidden" id="cfg_theme_background_image" value="<?php echo htmlspecialchars($themeBackgroundImagePath); ?>" data-empty-label="No background image selected">
                        <div class="asset-picker-control" id="cfg_theme_background_image_picker">
                            <span id="cfg_theme_background_image_label" class="asset-picker-value<?php echo $themeBackgroundImagePath === '' ? ' empty' : ''; ?>"><?php echo htmlspecialchars($themeBackgroundImageLabel); ?></span>
                            <div class="asset-picker-actions">
                                <button type="button" class="icon-btn media-picker-open" data-field="cfg_theme_background_image" data-title="Choose background image" data-targets="special,illustrations,photos">Choose file</button>
                                <button type="button" class="icon-btn media-picker-clear" data-field="cfg_theme_background_image">Clear</button>
                            </div>
                        </div>
                        <div class="field-note">Pick a still background from your uploaded theme, illustration, or photo assets, or clear it to run with no background image.</div>
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_theme_background_video_picker">Background video</label>
                        <input type="hidden" id="cfg_theme_background_video" value="<?php echo htmlspecialchars($themeBackgroundVideoPath); ?>" data-empty-label="No background video selected">
                        <div class="asset-picker-control" id="cfg_theme_background_video_picker">
                            <span id="cfg_theme_background_video_label" class="asset-picker-value<?php echo $themeBackgroundVideoPath === '' ? ' empty' : ''; ?>"><?php echo htmlspecialchars($themeBackgroundVideoLabel); ?></span>
                            <div class="asset-picker-actions">
                                <button type="button" class="icon-btn media-picker-open" data-field="cfg_theme_background_video" data-title="Choose background video" data-targets="special,video">Choose file</button>
                                <button type="button" class="icon-btn media-picker-clear" data-field="cfg_theme_background_video">Clear</button>
                            </div>
                        </div>
                        <div class="field-note">Pick a motion background from theme assets or uploaded videos, or clear it to disable background video entirely.</div>
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_theme_welcome_audio_picker">Welcome audio</label>
                        <input type="hidden" id="cfg_theme_welcome_audio" value="<?php echo htmlspecialchars($themeWelcomeAudioPath); ?>" data-empty-label="No welcome audio selected">
                        <div class="asset-picker-control" id="cfg_theme_welcome_audio_picker">
                            <span id="cfg_theme_welcome_audio_label" class="asset-picker-value<?php echo $themeWelcomeAudioPath === '' ? ' empty' : ''; ?>"><?php echo htmlspecialchars($themeWelcomeAudioLabel); ?></span>
                            <div class="asset-picker-actions">
                                <button type="button" class="icon-btn media-picker-open" data-field="cfg_theme_welcome_audio" data-title="Choose welcome audio" data-targets="special,audio">Choose file</button>
                                <button type="button" class="icon-btn media-picker-clear" data-field="cfg_theme_welcome_audio">Clear</button>
                            </div>
                        </div>
                        <div class="field-note">Use a short intro sound from theme assets or your uploaded audio library, or clear it to disable the intro sound.</div>
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_theme_loggedin_audio_picker">Logged-in audio</label>
                        <input type="hidden" id="cfg_theme_loggedin_audio" value="<?php echo htmlspecialchars($themeLoggedinAudioPath); ?>" data-empty-label="No logged-in audio selected">
                        <div class="asset-picker-control" id="cfg_theme_loggedin_audio_picker">
                            <span id="cfg_theme_loggedin_audio_label" class="asset-picker-value<?php echo $themeLoggedinAudioPath === '' ? ' empty' : ''; ?>"><?php echo htmlspecialchars($themeLoggedinAudioLabel); ?></span>
                            <div class="asset-picker-actions">
                                <button type="button" class="icon-btn media-picker-open" data-field="cfg_theme_loggedin_audio" data-title="Choose logged-in audio" data-targets="special,audio">Choose file</button>
                                <button type="button" class="icon-btn media-picker-clear" data-field="cfg_theme_loggedin_audio">Clear</button>
                            </div>
                        </div>
                        <div class="field-note">Choose the sound or loop used once visitors are inside the site, or clear it to skip that sound entirely.</div>
                    </div>
                </div>
                <textarea id="cfgThemeFullSource" style="display:none"><?php echo htmlspecialchars(json_encode($cfgFull, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?></textarea>
                <div class="card-actions">
                    <button id="cfgThemeSaveBtn" class="btn btn-primary">💾 Save theme</button>
                    <span id="cfgThemeStatus" class="status-text"></span>
                </div>
            </div>

            <!-- ── SUPPORT ─────────────────────────────────────────────────── -->
            <?php elseif ($configTab === 'support'): ?>
            <?php
            $cfgFull = bandpromo_load_runtime_config_raw();
            $cfgSupport = $cfgFull['support'] ?? [];
            $supportEnabled = !empty($cfgSupport['enabled']);
            $supportMode = (string) ($cfgSupport['mode'] ?? 'link');
            $supportLabel = (string) ($cfgSupport['label'] ?? 'Support');
            $supportUrl = (string) ($cfgSupport['url'] ?? '');
            $supportKofiPageId = (string) ($cfgSupport['kofi_page_id'] ?? '');
            $supportButtonBackground = (string) ($cfgSupport['button_background_color'] ?? '#323842');
            $supportButtonTextColor = (string) ($cfgSupport['button_text_color'] ?? '#ffffff');
            ?>
            <div class="card">
                <h3>💛 Support Links and Widgets</h3>
                <p class="card-note">
                    Choose how, or whether, visitors are invited to support you from the player page. This does not make bandPromo the payment flow. It only controls the public call-to-action that points people to your operator-owned support destination.
                </p>
                <div class="config-form-grid">
                    <div class="form-group config-form-full">
                        <label for="cfg_support_enabled" style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" id="cfg_support_enabled" <?php echo $supportEnabled ? 'checked' : ''; ?>>
                            <span>Show a public support call-to-action on the player page</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="cfg_support_mode">Display mode</label>
                        <select id="cfg_support_mode">
                            <option value="link" <?php echo $supportMode === 'link' ? 'selected' : ''; ?>>Link button</option>
                            <option value="floating_widget" <?php echo $supportMode === 'floating_widget' ? 'selected' : ''; ?>>Ko-fi floating widget</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cfg_support_label">Button text</label>
                        <input type="text" id="cfg_support_label" value="<?php echo htmlspecialchars($supportLabel); ?>" placeholder="Support">
                        <div class="field-note">Write the short public message visitors should see, such as Support, Tip Jar, Back This Release, or Join on Ko-fi.</div>
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_support_url">Direct support URL</label>
                        <input type="text" id="cfg_support_url" value="<?php echo htmlspecialchars($supportUrl); ?>" placeholder="https://example.com/support-or-membership">
                        <div class="field-note">Use this for the exact destination you control, such as a Ko-fi page, Patreon page, Stripe payment link, merch page, or membership landing page. In Link button mode, this is the safest and clearest setup. Leave it empty only if you want bandPromo to build a Ko-fi URL from the handle below.</div>
                    </div>
                    <div class="form-group config-form-full">
                        <label for="cfg_support_kofi_page_id">Ko-fi page ID / handle</label>
                        <input type="text" id="cfg_support_kofi_page_id" value="<?php echo htmlspecialchars($supportKofiPageId); ?>" placeholder="yourhandle">
                        <div class="field-note">Only needed for Ko-fi-specific behavior. The floating widget uses this handle directly, and Link button mode can use it as a fallback when you do not enter a full direct URL.</div>
                    </div>
                    <div class="form-group">
                        <label for="cfg_support_button_background_color">Button background color</label>
                        <input type="text" id="cfg_support_button_background_color" value="<?php echo htmlspecialchars($supportButtonBackground); ?>" placeholder="#323842">
                        <div class="field-note">Choose a color that stands out without looking unrelated to the rest of your site.</div>
                    </div>
                    <div class="form-group">
                        <label for="cfg_support_button_text_color">Button text color</label>
                        <input type="text" id="cfg_support_button_text_color" value="<?php echo htmlspecialchars($supportButtonTextColor); ?>" placeholder="#ffffff">
                        <div class="field-note">Make sure the text stays readable against the button background.</div>
                    </div>
                </div>
                <textarea id="cfgSupportFullSource" style="display:none"><?php echo htmlspecialchars(json_encode($cfgFull, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?></textarea>
                <div class="card-actions">
                    <button id="cfgSupportSaveBtn" class="btn btn-primary">💾 Save support settings</button>
                    <span id="cfgSupportStatus" class="status-text"></span>
                </div>
            </div>

            <!-- ── SHARING ─────────────────────────────────────────────────── -->
            <?php elseif ($configTab === 'sharing'): ?>
            <?php
            require_once __DIR__ . '/biblioteca/config-loader.php';
            $ogTitle   = get_config('release.identity.title', 'bandPromo');
            $ogDesc    = get_config('release.identity.description', '');
            $ogImage   = get_config('release.brand.poster', '/media/special/bandPromo_share.png');
            $ogUrl     = get_config('install.site.url', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $twitter   = get_config('install.social.twitter', '');
            $facebook  = get_config('install.social.facebook', '');
            $instagram = get_config('install.social.instagram', '');
            $keywords  = get_config('release.social.keywords', '');
            $categoriesRaw = get_config('release.social.categories', ['entertainment']);
            $categories = is_array($categoriesRaw) ? implode(', ', $categoriesRaw) : (string) $categoriesRaw;
            $ogDomain  = parse_url($ogUrl, PHP_URL_HOST) ?: $ogUrl;
            $ogImageLabel = $ogImage !== '' ? basename(str_replace('\\', '/', $ogImage)) : 'No share image selected';
            ?>

            <!-- ── Social metadata form ───────────────────────────────────── -->
            <div class="card">
                <h3 class="section-label">Social metadata</h3>
                <div class="social-form-grid">
                    <div class="form-group">
                        <label for="soc_site_name">Site name <span class="hint">(og:title)</span></label>
                        <input type="text" id="soc_site_name" value="<?php echo htmlspecialchars($ogTitle); ?>">
                    </div>
                    <div class="form-group">
                        <label for="soc_site_url">Site URL</label>
                        <input type="text" id="soc_site_url" value="<?php echo htmlspecialchars($ogUrl); ?>">
                    </div>
                    <div class="form-group social-form-full">
                        <label for="soc_site_desc">Description <span class="hint">(og:description)</span></label>
                        <input type="text" id="soc_site_desc" value="<?php echo htmlspecialchars($ogDesc); ?>">
                    </div>
                    <div class="form-group">
                        <label for="soc_twitter">Twitter / X handle</label>
                        <input type="text" id="soc_twitter" value="<?php echo htmlspecialchars($twitter); ?>" placeholder="@handle">
                    </div>
                    <div class="form-group">
                        <label for="soc_facebook">Facebook</label>
                        <input type="text" id="soc_facebook" value="<?php echo htmlspecialchars($facebook); ?>">
                    </div>
                    <div class="form-group">
                        <label for="soc_instagram">Instagram</label>
                        <input type="text" id="soc_instagram" value="<?php echo htmlspecialchars($instagram); ?>">
                    </div>
                    <div class="form-group social-form-full">
                        <label for="soc_share_image_picker">Share image</label>
                        <input type="hidden" id="soc_share_image" value="<?php echo htmlspecialchars($ogImage); ?>" data-empty-label="No share image selected">
                        <div class="asset-picker-control" id="soc_share_image_picker">
                            <span id="soc_share_image_label" class="asset-picker-value<?php echo $ogImage === '' ? ' empty' : ''; ?>"><?php echo htmlspecialchars($ogImageLabel); ?></span>
                            <div class="asset-picker-actions">
                                <button type="button" class="icon-btn media-picker-open" data-field="soc_share_image" data-title="Choose share image" data-targets="special,illustrations,photos">Choose file</button>
                            </div>
                        </div>
                        <div class="field-note">Pick the image used when people share your page. The storage path stays internal.</div>
                    </div>
                    <div class="form-group social-form-full">
                        <label for="soc_keywords">Keywords <span class="hint">(SEO meta keywords)</span></label>
                        <input type="text" id="soc_keywords" value="<?php echo htmlspecialchars($keywords); ?>" placeholder="music, artist, portfolio">
                    </div>
                    <div class="form-group social-form-full">
                        <label for="soc_categories">Categories <span class="hint">(manifest categories, comma separated)</span></label>
                        <input type="text" id="soc_categories" value="<?php echo htmlspecialchars($categories); ?>" placeholder="music, entertainment">
                    </div>
                </div>
                <div class="card-actions">
                    <button id="socialSaveBtn" class="btn btn-primary">Save</button>
                    <span id="socialStatus" class="status-text"></span>
                </div>
            </div>

            <!-- ── Previews ───────────────────────────────────────────────── -->
            <div class="share-previews">
                <!-- Facebook / OG preview -->
                <div class="share-preview-col">
                    <h3 class="section-label">Facebook / Open Graph</h3>
                    <div style="background:#18191a;border-radius:8px;overflow:hidden;border:1px solid #3a3b3c;font-family:Helvetica,Arial,sans-serif;max-width:480px">
                        <img id="prevOgImage" src="<?php echo htmlspecialchars($ogImage); ?>" alt="share preview"
                             style="width:100%;aspect-ratio:1.91;object-fit:cover;display:block"
                             onerror="this.style.background='#333';this.style.minHeight='200px'">
                        <div style="padding:10px 12px;background:#242526;border-top:1px solid #3a3b3c">
                            <div id="prevOgDomain" style="font-size:11px;color:#b0b3b8;text-transform:uppercase;margin-bottom:2px"><?php echo htmlspecialchars($ogDomain); ?></div>
                            <div id="prevOgTitle" style="font-size:15px;font-weight:600;color:#e4e6eb;line-height:1.3;margin-bottom:2px"><?php echo htmlspecialchars($ogTitle); ?></div>
                            <div id="prevOgDesc" style="font-size:13px;color:#b0b3b8;line-height:1.4"><?php echo htmlspecialchars($ogDesc); ?></div>
                        </div>
                    </div>
                </div>

                <!-- X / Twitter card preview -->
                <div class="share-preview-col">
                    <h3 class="section-label">X (Twitter) Card</h3>
                    <div style="background:#000;border-radius:14px;overflow:hidden;border:1px solid #2f3336;font-family:'Helvetica Neue',Arial,sans-serif;max-width:480px">
                        <img id="prevTwImage" src="<?php echo htmlspecialchars($ogImage); ?>" alt="share preview"
                             style="width:100%;aspect-ratio:1.91;object-fit:cover;display:block"
                             onerror="this.style.background='#333';this.style.minHeight='200px'">
                        <div style="padding:10px 12px">
                            <div id="prevTwTitle" style="font-size:14px;font-weight:700;color:#e7e9ea;margin-bottom:2px"><?php echo htmlspecialchars($ogTitle); ?></div>
                            <div id="prevTwDesc" style="font-size:13px;color:#71767b;margin-bottom:4px"><?php echo htmlspecialchars($ogDesc); ?></div>
                            <div id="prevTwDomain" style="font-size:13px;color:#71767b">🔗 <?php echo htmlspecialchars($ogDomain); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>

            <div id="mediaPickerModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeMediaPickerModal()">
                <div class="modal-box modal-wide">
                    <button class="modal-close" onclick="closeMediaPickerModal()">✕</button>
                    <h3 id="mediaPickerTitle">Choose file</h3>
                    <p class="card-note" id="mediaPickerHint">Pick an uploaded file. The internal storage path stays hidden from operators.</p>
                    <div id="mediaPickerTabs" class="tabs sub-tabs media-picker-tabs"></div>
                    <div id="mediaPickerList" class="media-file-list media-picker-list"><span class="text-muted">Choose a media type to browse files.</span></div>
                    <div class="modal-actions">
                        <button type="button" id="mediaPickerUploadBtn" class="icon-btn">Upload new file</button>
                        <span id="mediaPickerStatus" class="status-text"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== BUILD TAB ===================== -->
        <div id="tab-build" class="tab-content <?php echo $tab === 'build' ? 'active' : ''; ?>">
            <div class="tabs sub-tabs">
                <button id="buildBtn" class="subtab-action">▶️ Run Publish Build</button>
                <button id="optimizeBtn" class="subtab-action">🖼️ Refresh Image Files</button>
                <button id="recommendedBuildBtn" class="subtab-action" style="display:none"></button>
                <button class="help-toggle-btn collapsed" id="helpBtn-build" onclick="toggleHelp('build')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-build">
                Use <strong>Refresh Image Files</strong> when only publish-ready photo, illustration, or theme-image files need to be regenerated. Use <strong>Run Publish Build</strong> when audio, validation, playlist, manifest, or other heavier publish steps are still pending. Jobs continue in the background while this log updates.
            </div>

            <div id="buildValidationCard" class="card build-validation-card" style="display:none">
                <div class="build-validation-head">
                    <h3>🩺 Validation Summary</h3>
                    <span id="buildValidationOverall" class="badge audit-status-badge status-neutral">No validation data</span>
                </div>
                <div id="buildValidationSummary" class="build-validation-summary"></div>
            </div>

            <div id="build-log-card" class="card">
                <div class="build-log-head">
                    <h3>📋 Build Log</h3>
                    <div class="build-log-meta">
                        <span id="buildSpinner" class="build-log-spinner" style="display:none">⏳ Building…</span>
                        <span id="optimizeSpinner" class="build-log-spinner" style="display:none">⏳ Optimizing…</span>
                        <span id="buildStatus" class="build-log-status"></span>
                    </div>
                </div>
                <pre id="buildLog" class="build-log">No build output yet.</pre>
            </div>
        </div>

        <!-- ===================== DOCUMENTATION TAB ===================== -->
        <div id="tab-docs" class="tab-content <?php echo $tab === 'docs' ? 'active' : ''; ?>">
            <div class="tabs sub-tabs">
                <button class="help-toggle-btn collapsed" id="helpBtn-docs" onclick="toggleHelp('docs')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-docs">
                Operators see operator-safe documentation only. Developers can switch between operator docs, developer docs, and a combined view when they need both perspectives.
            </div>

            <?php if ($currentUserRole === 'developer'): ?>
            <div class="docs-scope-switch">
                <a href="?tab=docs&doc_scope=developer" class="docs-scope-link <?php echo $documentationScope === 'developer' ? 'active' : ''; ?>">Developer Docs</a>
                <a href="?tab=docs&doc_scope=operator" class="docs-scope-link <?php echo $documentationScope === 'operator' ? 'active' : ''; ?>">Operator Docs</a>
                <a href="?tab=docs&doc_scope=all" class="docs-scope-link <?php echo $documentationScope === 'all' ? 'active' : ''; ?>">All Docs</a>
            </div>
            <?php else: ?>
            <div class="docs-scope-note">Showing operator-safe documentation for this role.</div>
            <?php endif; ?>

            <div class="docs-browser">
                <aside class="docs-nav-panel">
                    <div class="docs-nav-panel-head">
                        <h3>Available Documentation</h3>
                        <span class="docs-nav-count"><?php echo number_format(count($documentationCatalog)); ?> files</span>
                    </div>
                    <div class="docs-nav-list">
                        <?php foreach ($documentationCatalog as $docEntry): ?>
                        <a href="<?php echo htmlspecialchars(bandpromo_docs_url($docEntry['path'], $documentationScope)); ?>" class="docs-nav-link <?php echo (($documentationView['entry']['path'] ?? 'README.md') === $docEntry['path']) ? 'active' : ''; ?>">
                            <span class="docs-nav-title"><?php echo htmlspecialchars($docEntry['title']); ?></span>
                            <span class="docs-nav-path"><?php echo htmlspecialchars($docEntry['path']); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <section class="docs-content-panel">
                    <div class="docs-content-head">
                        <div>
                            <h3><?php echo htmlspecialchars($documentationView['entry']['title'] ?? 'Documentation'); ?></h3>
                            <div class="docs-content-path"><?php echo htmlspecialchars($documentationView['entry']['path'] ?? 'README.md'); ?></div>
                        </div>
                    </div>
                    <div class="docs-markdown">
                        <?php echo $documentationView['html'] ?? '<p class="empty-msg">No documentation available.</p>'; ?>
                    </div>
                </section>
            </div>
        </div>



    </div><!-- .container -->

    <!-- ===== ADD / EDIT USER MODAL ===== -->
    <div id="userModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeUserModal()">
        <div class="modal-box">
            <button class="modal-close" onclick="closeUserModal()">✕</button>
            <h3 id="userModalTitle">Add User</h3>
            <form method="POST" id="userModalForm">
                <input type="hidden" name="action" id="userModalAction" value="add_user">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="new_username" id="userModalUsername" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label id="userModalPassLabel">Password</label>
                    <input type="password" name="new_password" id="userModalPassword" required autocomplete="new-password">
                </div>
                <!-- Hidden field for edit action -->
                <input type="hidden" name="edit_username" id="userModalEditUsername" value="">
                <button type="submit" class="btn-primary" style="width:100%;">Save</button>
            </form>
        </div>
    </div>

    <!-- Delete user hidden form -->
    <form method="POST" id="deleteUserForm" style="display:none;">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_to_delete" id="deleteUserTarget">
    </form>

    <!-- ===== USER DETAIL LIGHTBOX ===== -->
    <div id="userDetailModal" class="modal-overlay" style="display:none;" onclick="if(event.target===this)closeUserDetail()">
        <div class="modal-box modal-wide">
            <button class="modal-close" onclick="closeUserDetail()">✕</button>
            <div id="userDetailContent"><p class="empty-msg">Loading…</p></div>
        </div>
    </div>

    <div id="audioMasterModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeAudioMasterModal()">
        <div class="modal-box modal-wide">
            <button class="modal-close" onclick="closeAudioMasterModal()">✕</button>
            <h3 id="audioMasterTitle">Track details</h3>
            <p class="card-note" id="audioMasterSubtitle">Update the track details shown on the site.</p>

            <div class="audio-master-cover-layout">
                <div class="audio-master-cover-preview-shell">
                    <div class="audio-master-cover-preview" id="audioMasterCoverPreviewShell">
                        <div class="audio-master-cover-overlay-actions">
                            <button type="button" class="icon-btn media-picker-open audio-master-cover-action" data-field="audioMasterFieldCoverPath" data-title="Choose track cover" data-targets="illustrations,photos,special" title="Choose cover" aria-label="Choose cover">✎</button>
                            <button type="button" class="icon-btn audio-master-cover-action" id="audioMasterCoverClearBtn" title="Use release cover" aria-label="Use release cover">↺</button>
                        </div>
                        <img id="audioMasterCoverPreview" alt="Track cover preview" style="display:none;">
                        <span id="audioMasterCoverPlaceholder">No cover available</span>
                    </div>
                </div>
                <div class="audio-master-summary" id="audioMasterSummary">
                    <div class="audio-master-stat audio-master-stat-compact">
                        <span class="audio-master-stat-label">Track #</span>
                        <strong id="audioMasterTracknumber">—</strong>
                    </div>
                    <div class="audio-master-stat">
                        <span class="audio-master-stat-label">Duration</span>
                        <strong id="audioMasterDuration">—</strong>
                    </div>
                    <div class="audio-master-stat">
                        <span class="audio-master-stat-label">Format</span>
                        <strong id="audioMasterFormat">—</strong>
                    </div>
                    <div class="audio-master-stat">
                        <span class="audio-master-stat-label">Bitrate</span>
                        <strong id="audioMasterBitrate">—</strong>
                    </div>
                    <div class="audio-master-stat">
                        <span class="audio-master-stat-label">Sample rate</span>
                        <strong id="audioMasterSampleRate">—</strong>
                    </div>
                    <div class="audio-master-stat">
                        <span class="audio-master-stat-label">Bit depth</span>
                        <strong id="audioMasterBitDepth">—</strong>
                    </div>
                    <div class="audio-master-stat">
                        <span class="audio-master-stat-label">Filesize</span>
                        <strong id="audioMasterFilesize">—</strong>
                    </div>
                </div>
            </div>

            <input type="hidden" id="audioMasterFieldCoverPath" name="cover_path" data-empty-label="No new cover selected">

            <form id="audioMasterForm">
                <div class="audio-master-form-grid audio-master-form-grid-compact">
                    <div class="form-group">
                        <label for="audioMasterFieldAlbum">* Release name</label>
                        <input type="text" id="audioMasterFieldAlbum" name="album" autocomplete="off" required>
                    </div>
                    <div class="form-group form-group-date">
                        <label for="audioMasterFieldDate">* Release date</label>
                        <div class="date-input-shell">
                            <span class="date-input-icon" aria-hidden="true">📅</span>
                            <input type="date" id="audioMasterFieldDate" name="date" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldGenre">Genre</label>
                        <input type="text" id="audioMasterFieldGenre" name="genre" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldBpm">BPM</label>
                        <input type="text" id="audioMasterFieldBpm" name="bpm" autocomplete="off" inputmode="numeric" pattern="[0-9]{0,3}" maxlength="3" placeholder="128">
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldInitialkey">Key</label>
                        <input type="text" id="audioMasterFieldInitialkey" name="initialkey" autocomplete="off" maxlength="3" placeholder="8A">
                    </div>
                </div>
                <div class="audio-master-form-grid audio-master-form-grid-secondary">
                    <div class="form-group">
                        <label for="audioMasterFieldArtist">* Artist</label>
                        <input type="text" id="audioMasterFieldArtist" name="artist" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldTitle">* Title</label>
                        <input type="text" id="audioMasterFieldTitle" name="title" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldVersion">Version</label>
                        <input type="text" id="audioMasterFieldVersion" name="version" autocomplete="off" placeholder="Radio Edit">
                    </div>
                </div>
                <div class="form-group">
                    <label for="audioMasterFieldComment">Track description</label>
                    <textarea id="audioMasterFieldComment" name="comment" rows="4" maxlength="300"></textarea>
                    <div class="field-note audio-master-description-note"><span id="audioMasterDescriptionCount">0</span>/300 characters</div>
                </div>
                <div class="form-group">
                    <label for="audioMasterFieldLyrics">Lyrics</label>
                    <textarea id="audioMasterFieldLyrics" name="lyrics" rows="8"></textarea>
                </div>
            </form>

            <div class="modal-actions">
                <button type="button" id="audioMasterSaveBtn" class="btn btn-primary">Save metadata</button>
                <span id="audioMasterStatus" class="status-text"></span>
            </div>
        </div>
    </div>

    <div id="operatorNotificationsModal" class="modal-overlay operator-notifications-modal" style="display:none" onclick="if(event.target===this)closeOperatorNotifications()">
        <div class="modal-box modal-wide" role="dialog" aria-modal="true" aria-labelledby="operatorNotificationsModalTitle">
            <button type="button" class="modal-close" id="operatorNotificationsClose" aria-label="Close">✕</button>
            <h3 id="operatorNotificationsModalTitle">What needs your attention</h3>
            <p class="card-note">This is your to-do list for the site. Each item explains what is wrong and what to do next. Small fixes happen automatically — only things that need you show up here.</p>
            <div id="operatorNotificationsModalBody" class="operator-notifications-body">
                <p class="operator-notifications-empty">Loading…</p>
            </div>
        </div>
    </div>

    <div id="adminToastHost" class="admin-toast-host" aria-live="polite" aria-atomic="true"></div>

    <script>
        const hourlyDistributionData = <?php echo json_encode($platformStats['hourly_distribution'] ?? []); ?>;
        const adminDateStart = <?php echo json_encode($dateStart); ?>;
        const adminDateEnd   = <?php echo json_encode($dateEnd); ?>;
        const adminActivePanel = <?php echo json_encode($filesPanel); ?>;
        const adminCsrfToken = <?php echo json_encode($adminCsrfToken); ?>;
        const adminWelcomeDashboardMode = <?php echo $welcomeSetupComplete ? 'true' : 'false'; ?>;
    </script>
    <script src="biblioteca/admin.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/admin.js'); ?>"></script>

    <!-- Admin media preview lightbox -->
    <div id="adminPreviewLightbox">
        <button onclick="closeAdminPreview()" id="adminPreviewClose">✕</button>
        <button id="adminPreviewPrev" onclick="prevAdminPreview(event)">&#8249;</button>
        <img id="adminPreviewImg" src="" alt="">
        <video id="adminPreviewVid" controls style="display:none;"></video>
        <button id="adminPreviewNext" onclick="nextAdminPreview(event)">&#8250;</button>
        <span id="adminPreviewCaption"></span>
    </div>
</body>
</html>
