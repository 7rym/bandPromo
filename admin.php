<?php
require_once __DIR__ . '/biblioteca/https.php';
require_once __DIR__ . '/biblioteca/setup-state.php';
bandpromo_enforce_https();

session_start();

require_once 'biblioteca/auth.php';
require_once 'biblioteca/array-helpers.php';
require_once 'biblioteca/admin-audit.php';
require_once 'biblioteca/build-required.php';
require_once 'biblioteca/config-loader.php';
require_once 'biblioteca/csrf.php';
require_once 'biblioteca/media-library-state.php';
require_once 'biblioteca/page-storage.php';
require_once 'biblioteca/page-registry.php';
require_once 'biblioteca/player-modules.php';
require_once 'biblioteca/admin-welcome-state.php';
require_once 'biblioteca/theme-storage.php';
require_once 'biblioteca/playlist-storage.php';
require_once 'biblioteca/gallery-storage.php';

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
$defaultThemeStatus = bandpromo_admin_get_default_theme_status(__DIR__);
$welcomeState = bandpromo_admin_welcome_state(__DIR__);
$welcomeChecklist = $welcomeState['checklist'];
$welcomeCompletedChecks = $welcomeState['completed_count'];
$welcomeTotalChecks = $welcomeState['total_count'];
$welcomeSetupComplete = $welcomeState['setup_complete'];
$welcomeNextSteps = $welcomeState['next_steps'];
$requestHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$requestHostNoPort = preg_replace('/:\\d+$/', '', $requestHost);
if ($requestHostNoPort === 'localhost') {
    $configuredHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
    if ($configuredHost === '' || $configuredHost === '127.0.0.1' || $configuredHost === 'localhost') {
        $siteUrl = 'http://' . $requestHost;
    }
}

$buildRequiredState = bandpromo_get_build_required_state();
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
    $welcomePrimaryNotice = '';
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
$sessionExpiredNotice = isset($_GET['session_expired']) && $_GET['session_expired'] === '1';

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

require_once __DIR__ . '/biblioteca/config-repair-helpers.php';
if ($authenticated) {
    try {
        $configRepairResult = bandpromo_config_repair_structure(__DIR__);
        if (!empty($configRepairResult['repaired'])) {
            bandpromo_admin_audit_log('config_structure_repaired', [
                'target_type' => 'config',
                'target_id' => 'web-config.json',
                'status' => 'ok',
                'data' => [
                    'added_sections' => $configRepairResult['added_sections'] ?? [],
                ],
            ]);
        }
    } catch (Throwable $throwable) {
        error_log('Config auto-repair failed: ' . $throwable->getMessage());
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
            
            <?php if ($sessionExpiredNotice): ?>
                <div class="error">Your session expired. Please log in again.</div>
            <?php endif; ?>

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

// Legacy tab redirects
$requestedTab = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
if (in_array($requestedTab, ['config', 'build', 'audit'], true)) {
    $redirectQuery = $_GET;
    if ($requestedTab === 'config') {
        $redirectQuery['tab'] = 'settings';
    } else {
        $redirectQuery['tab'] = 'system';
        $redirectQuery['stab'] = $requestedTab === 'audit' ? 'audit' : 'publish';
    }
    header('Location: /admin.php?' . http_build_query($redirectQuery));
    exit;
}

// Primary tab
$tab = $_GET['tab'] ?? 'welcome';
if (!in_array($tab, ['welcome', 'analytics', 'users', 'files', 'content', 'settings', 'system', 'docs'], true)) {
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

$editablePages = bandpromo_page_admin_pages_map(__DIR__);
if ($editablePages === []) {
    bandpromo_page_seed_registry_if_missing(__DIR__);
    $editablePages = bandpromo_page_admin_pages_map(__DIR__);
}

// Content sub-tab
$contentTab = $_GET['cntab'] ?? 'release';
if ($contentTab === 'bio') {
    $contentTab = 'pages';
}
if (!in_array($contentTab, ['release', 'playlist', 'gallery', 'pages', 'themes', 'player'], true)) {
    $contentTab = 'release';
}

$pageTabEntries = bandpromo_page_admin_tab_entries(__DIR__);
$contentTheme = isset($_GET['theme']) ? bandpromo_theme_normalize_id((string) $_GET['theme']) : '';
if ($contentTheme === '') {
    try {
        bandpromo_theme_ensure_seeded(__DIR__);
        $contentTheme = bandpromo_theme_active_id(__DIR__);
    } catch (Throwable $throwable) {
        $contentTheme = BANDPROMO_THEME_DEFAULT_ID;
    }
}
$contentPlaylist = isset($_GET['playlist']) ? bandpromo_playlist_normalize_id((string) $_GET['playlist']) : '';
if ($contentPlaylist === '') {
    try {
        bandpromo_playlist_ensure_seeded(__DIR__);
        $contentPlaylist = bandpromo_playlist_default_active_id(__DIR__);
    } catch (Throwable $throwable) {
        $contentPlaylist = BANDPROMO_PLAYLIST_DEMO_ID;
    }
}
$contentRelease = isset($_GET['release']) ? bandpromo_release_normalize_id((string) $_GET['release']) : '';
if ($contentRelease === '') {
    try {
        bandpromo_release_ensure_seeded(__DIR__);
        $contentRelease = BANDPROMO_RELEASE_DEFAULT_ID;
    } catch (Throwable $throwable) {
        $contentRelease = BANDPROMO_RELEASE_DEFAULT_ID;
    }
}
$contentGallery = isset($_GET['gallery']) ? bandpromo_gallery_normalize_id((string) $_GET['gallery']) : '';
if ($contentGallery === '') {
    try {
        bandpromo_gallery_ensure_seeded(__DIR__);
        $contentGallery = BANDPROMO_GALLERY_DEFAULT_ID;
    } catch (Throwable $throwable) {
        $contentGallery = BANDPROMO_GALLERY_DEFAULT_ID;
    }
}
$contentPage = isset($_GET['page']) ? bandpromo_page_normalize_id((string) $_GET['page']) : 'faq';
if (!is_string($contentPage) || !array_key_exists($contentPage, $editablePages)) {
    $contentPage = array_key_exists('faq', $editablePages) ? 'faq' : (array_key_first($editablePages) ?: 'faq');
}
$activeContentPage = $editablePages[$contentPage];
$activePageIsLoginOnly = ($activeContentPage['surface'] ?? '') === 'login';
$playerLayoutState = bandpromo_player_layout_admin_state(__DIR__);

// Settings sub-tab
$configTab = $_GET['ctab'] ?? 'basics';
if (!in_array($configTab, ['basics', 'theme', 'support', 'sharing'], true)) {
    $configTab = 'basics';
}

// System sub-tab
$systemTab = $_GET['stab'] ?? 'publish';
if (!in_array($systemTab, ['publish', 'audit'], true)) {
    $systemTab = 'publish';
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
if ($tab === 'system' && $systemTab === 'audit') {
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
    <?php echo bandpromo_theme_render_css(__DIR__); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php if ($tab === 'content' && in_array($contentTab, ['pages', 'player', 'release', 'playlist', 'gallery', 'themes'], true)): ?>
    <link rel="stylesheet" href="biblioteca/page-content.css?v=<?php echo filemtime(__DIR__ . '/biblioteca/page-content.css'); ?>">
    <?php endif; ?>
    <?php if ($tab === 'content' && in_array($contentTab, ['pages', 'release', 'playlist', 'gallery', 'themes'], true)): ?>
    <link rel="stylesheet" href="biblioteca/page-editor.css?v=<?php echo filemtime(__DIR__ . '/biblioteca/page-editor.css'); ?>">
    <?php endif; ?>
    <?php if ($tab === 'content' && $contentTab === 'themes'): ?>
    <link rel="stylesheet" href="biblioteca/theme-editor.css?v=<?php echo filemtime(__DIR__ . '/biblioteca/theme-editor.css'); ?>">
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
                <span class="operator-notifications-label">Notifications</span>
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
            <?php renderTabLink('users',     $tab, '👥', 'Users'); ?>
            <?php renderTabLink('files',     $tab, '📁', 'Files'); ?>
            <?php renderTabLink('content',   $tab, '📄', 'Content'); ?>
            <?php renderTabLink('settings',  $tab, '⚙️', 'Settings'); ?>
            <?php renderTabLink('system',    $tab, '🛠️', 'System'); ?>
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
                    This page is your dashboard. Use <strong>Notifications</strong> in the header for open tasks and background activity, then jump to <strong>Files</strong> or <strong>Content</strong> to work on them. Use <strong>Site update</strong> below when a newer published package is available.
                <?php else: ?>
                    Use this page as your setup checklist while bandPromo is still getting the installation ready. bandPromo decides as much as it can on its own, then points you to the next incomplete step. Open <strong>Notifications</strong> in the header for the same checklist items plus any published site update. Jump to <strong>Settings</strong> for identity and branding, <strong>Files</strong> for uploads and metadata, <strong>Content</strong> for pages and playlist shaping, <strong>System → Publish</strong> during setup, and <strong>Documentation</strong> for deeper explanations.
                <?php endif; ?>
            </div>

            <div class="card welcome-card<?php echo $welcomeSetupComplete ? ' welcome-card-dashboard' : ''; ?>">
                <?php if ($welcomeSetupComplete): ?>
                    <h2>📊 <?php echo htmlspecialchars($siteName); ?> dashboard</h2>
                <?php else: ?>
                    <h2>🌍 Welcome to bandPromo</h2>
                <?php endif; ?>

                <?php if ($welcomePrimaryNotice !== ''): ?>
                <div class="welcome-callout<?php echo $welcomeSetupComplete ? ' welcome-callout-dashboard' : ''; ?>">
                    <?php echo htmlspecialchars($welcomePrimaryNotice); ?>
                </div>
                <?php endif; ?>

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

            <div class="card package-update-card" id="packageUpdateCard">
                <h2>⬆️ Site update</h2>
                <p class="package-update-lead">
                    Check for published bandPromo packages and install a newer release without Git, SSH, or manual file uploads.
                    Your site content stays safe: <strong>web-config.json</strong>, <strong>.env</strong>, <strong>data/</strong>, <strong>media/</strong>, and <strong>log/</strong> are preserved.
                </p>

                <div class="package-update-status" id="packageUpdateStatus">Checking for updates…</div>

                <ul class="package-update-checks" id="packageUpdateChecks" hidden></ul>
                <ul class="package-update-notes" id="packageUpdateNotes" hidden></ul>

                <div class="package-update-actions">
                    <button type="button" class="btn" id="packageUpdateRefreshBtn">Check again</button>
                    <button type="button" class="btn btn-primary" id="packageUpdateApplyBtn" hidden>Download and install update</button>
                </div>

                <p class="package-update-footnote" id="packageUpdateFootnote" hidden></p>
            </div>
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
                    <br><strong>After upload:</strong> bandPromo prepares delivery files automatically. Tracks appear in Content pools only after they are ready. Check <strong>Notifications</strong> only if preparation fails.
                <?php elseif ($filesPanel === 'photos'): ?>
                    Drop band and promo photos here (PNG, JPG, WEBP). Use your best quality images. Unreferenced uploads are flagged as orphans so you can clean them up safely.
                    <br><strong>After upload:</strong> bandPromo prepares publish-ready photo files automatically.
                <?php elseif ($filesPanel === 'video'): ?>
                    Drop videos here (MP4, WEBM, MOV). bandPromo keeps the original here and prepares a publish-ready MP4 automatically after upload. Delivery and poster generation run in the background for all video types. Videos appear in Content pools only after delivery is ready.
                    <br><strong>After upload:</strong> check <strong>Notifications</strong> for background progress or any preparation failure.
                <?php elseif ($filesPanel === 'illustrations'): ?>
                    Drop artwork, track covers, and illustrations here (PNG, JPG, JPEG). Rows show whether each file is a track cover, release fallback, or general artwork, plus where it is referenced.
                    <br><strong>After upload:</strong> bandPromo prepares publish-ready artwork automatically.
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
                </div>
                <div class="media-file-row media-file-list-header" data-media-list-header="audio">
                    <div class="media-file-row-main">
                        <label class="media-file-select-wrap" title="Select or clear all visible files">
                            <input type="checkbox" class="media-file-select-all" data-target="audio" aria-label="Select all visible files">
                        </label>
                        <span class="media-file-list-header-thumb" aria-hidden="true"></span>
                        <div class="media-file-list-header-filters">
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter audio by source</span>
                                <select class="media-filter-select" data-media-audio-display-filter aria-label="Filter audio by source">
                                    <option value="master">Master files</option>
                                    <option value="original">Original files</option>
                                </select>
                            </label>
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter by release</span>
                                <select class="media-filter-select" data-media-release-filter aria-label="Filter by release">
                                    <option value="all">All releases</option>
                                </select>
                            </label>
                        </div>
                        <span class="media-file-size media-file-list-header-size" aria-hidden="true">&nbsp;</span>
                        <span class="media-file-actions">
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('audio')" aria-label="Upload audio files" title="Upload audio files"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="audio" data-download-variant="current" disabled aria-label="Download selected audio files" title="Download selected audio files"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn media-bulk-delete-btn" data-bulk-delete-target="audio" disabled aria-label="Delete selected audio files" title="Delete selected audio files"><span class="media-labeled-action-icon" aria-hidden="true">🗑️</span><span>Delete</span></button>
                        </span>
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
                </div>
                <div class="media-file-row media-file-list-header" data-media-list-header="video">
                    <div class="media-file-row-main">
                        <label class="media-file-select-wrap" title="Select or clear all visible files">
                            <input type="checkbox" class="media-file-select-all" data-target="video" aria-label="Select all visible files">
                        </label>
                        <span class="media-file-list-header-thumb" aria-hidden="true"></span>
                        <div class="media-file-list-header-filters">
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter video files</span>
                                <select class="media-filter-select" data-media-filter-target="video" aria-label="Filter video files">
                                    <option value="all">All files</option>
                                    <option value="referenced">In use</option>
                                    <option value="orphans">Orphans</option>
                                </select>
                            </label>
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter by release</span>
                                <select class="media-filter-select" data-media-release-filter aria-label="Filter by release">
                                    <option value="all">All releases</option>
                                </select>
                            </label>
                        </div>
                        <span class="media-file-size media-file-list-header-size" aria-hidden="true">&nbsp;</span>
                        <span class="media-file-actions">
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('video')" aria-label="Upload video files" title="Upload video files"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="video" data-download-variant="original" disabled aria-label="Download selected video files" title="Download selected video files"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn media-bulk-delete-btn" data-bulk-delete-target="video" disabled aria-label="Delete selected video files" title="Delete selected video files"><span class="media-labeled-action-icon" aria-hidden="true">🗑️</span><span>Delete</span></button>
                        </span>
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
                </div>
                <div class="media-file-row media-file-list-header" data-media-list-header="illustrations">
                    <div class="media-file-row-main">
                        <label class="media-file-select-wrap" title="Select or clear all visible files">
                            <input type="checkbox" class="media-file-select-all" data-target="illustrations" aria-label="Select all visible files">
                        </label>
                        <span class="media-file-list-header-thumb" aria-hidden="true"></span>
                        <div class="media-file-list-header-filters">
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter illustration files</span>
                                <select class="media-filter-select" data-media-filter-target="illustrations" aria-label="Filter illustration files">
                                    <option value="all">All files</option>
                                    <option value="track-covers">Track covers</option>
                                    <option value="orphans">Orphans</option>
                                    <option value="build-generated">Build-generated</option>
                                </select>
                            </label>
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter by release</span>
                                <select class="media-filter-select" data-media-release-filter aria-label="Filter by release">
                                    <option value="all">All releases</option>
                                </select>
                            </label>
                        </div>
                        <span class="media-file-size media-file-list-header-size" aria-hidden="true">&nbsp;</span>
                        <span class="media-file-actions">
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('illustrations')" aria-label="Upload illustration files" title="Upload illustration files"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="illustrations" data-download-variant="original" disabled aria-label="Download selected illustration files" title="Download selected illustration files"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn media-bulk-delete-btn" data-bulk-delete-target="illustrations" disabled aria-label="Delete selected illustration files" title="Delete selected illustration files"><span class="media-labeled-action-icon" aria-hidden="true">🗑️</span><span>Delete</span></button>
                        </span>
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
                </div>
                <div class="media-file-row media-file-list-header" data-media-list-header="photos">
                    <div class="media-file-row-main">
                        <label class="media-file-select-wrap" title="Select or clear all visible files">
                            <input type="checkbox" class="media-file-select-all" data-target="photos" aria-label="Select all visible files">
                        </label>
                        <span class="media-file-list-header-thumb" aria-hidden="true"></span>
                        <div class="media-file-list-header-filters">
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter photo files</span>
                                <select class="media-filter-select" data-media-filter-target="photos" aria-label="Filter photo files">
                                    <option value="all">All files</option>
                                    <option value="referenced">In use</option>
                                    <option value="orphans">Orphans</option>
                                </select>
                            </label>
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter by release</span>
                                <select class="media-filter-select" data-media-release-filter aria-label="Filter by release">
                                    <option value="all">All releases</option>
                                </select>
                            </label>
                        </div>
                        <span class="media-file-size media-file-list-header-size" aria-hidden="true">&nbsp;</span>
                        <span class="media-file-actions">
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('photos')" aria-label="Upload photo files" title="Upload photo files"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="photos" data-download-variant="original" disabled aria-label="Download selected photo files" title="Download selected photo files"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn media-bulk-delete-btn" data-bulk-delete-target="photos" disabled aria-label="Delete selected photo files" title="Delete selected photo files"><span class="media-labeled-action-icon" aria-hidden="true">🗑️</span><span>Delete</span></button>
                        </span>
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
                </div>
                <div class="media-file-row media-file-list-header" data-media-list-header="special">
                    <div class="media-file-row-main">
                        <label class="media-file-select-wrap" title="Select or clear all visible files">
                            <input type="checkbox" class="media-file-select-all" data-target="special" aria-label="Select all visible files">
                        </label>
                        <span class="media-file-list-header-thumb" aria-hidden="true"></span>
                        <div class="media-file-list-header-filters">
                            <label class="media-filter-label">
                                <span class="visually-hidden">Filter by release</span>
                                <select class="media-filter-select" data-media-release-filter aria-label="Filter by release">
                                    <option value="all">All releases</option>
                                </select>
                            </label>
                        </div>
                        <span class="media-file-size media-file-list-header-size" aria-hidden="true">&nbsp;</span>
                        <span class="media-file-actions">
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('special')" aria-label="Upload theme files" title="Upload theme files"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="special" data-download-variant="original" disabled aria-label="Download selected theme files" title="Download selected theme files"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                            <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn media-bulk-delete-btn" data-bulk-delete-target="special" disabled aria-label="Delete selected theme files" title="Delete selected theme files"><span class="media-labeled-action-icon" aria-hidden="true">🗑️</span><span>Delete</span></button>
                        </span>
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
                    'release'  => ['💿', 'Catalog'],
                    'playlist' => ['🎵', 'Playlists'],
                    'gallery'  => ['🖼️', 'Galleries'],
                    'pages'    => ['📝', 'Pages'],
                    'themes'   => ['🎨', 'Themes'],
                    'player'   => ['🎛️', 'Player'],
                ];
                foreach ($cntTabs as $ct => [$emoji, $label]):
                    $active = $ct === $contentTab ? 'active' : '';
                    $url = '?tab=content&cntab=' . urlencode($ct);
                    if ($ct === 'pages') {
                        $url .= '&page=' . urlencode($contentPage);
                    }
                    if ($ct === 'themes') {
                        $url .= '&theme=' . urlencode($contentTheme);
                    }
                    if ($ct === 'playlist') {
                        $url .= '&playlist=' . urlencode($contentPlaylist);
                    }
                    if ($ct === 'release') {
                        $url .= '&release=' . urlencode($contentRelease);
                    }
                    if ($ct === 'gallery') {
                        $url .= '&gallery=' . urlencode($contentGallery);
                    }
                ?>
                <a href="<?php echo $url; ?>" class="tab-link <?php echo $active; ?>">
                    <?php echo htmlspecialchars($emoji . ' ' . $label); ?>
                </a>
                <?php endforeach; ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-content" onclick="toggleHelp('content')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-content">
                <?php if ($contentTab === 'release'): ?>
                    Pick a release from the pool to preview its track membership on the right. Click edit to open the audio pool and assign tracks. Every track must belong to exactly one release.
                <?php elseif ($contentTab === 'playlist'): ?>
                    Pick a playlist from the pool to preview its track order on the right. Click edit to open the track pool and reorder. Add new playlists from the pool header.
                <?php elseif ($contentTab === 'gallery'): ?>
                    Pick a gallery from the pool to preview its content order on the right. Click edit to open the media pool and reorder. Add new galleries from the pool header.
                <?php elseif ($contentTab === 'pages'): ?>
                    Use the page pool on the left to pick a page, preview it on the right, and click Edit to open the block editor. Add new pages from the pool header.
                <?php elseif ($contentTab === 'themes'): ?>
                    Pick a theme from the pool to preview it on the right. Click edit to open the token editor on the left. Duplicate Setup Default to create an editable copy.
                <?php elseif ($contentTab === 'player'): ?>
                    Drag content from the pool into the player layout on the right. Reorder optional items like the playlist editor. Playlist and Lyrics always stay first.
                <?php endif; ?>
            </div>

            <?php
            $poolReleaseFilterHtml = '<div class="player-layout-pool-head-slot player-layout-pool-filters">'
                . '<label class="media-filter-label player-layout-pool-filter-label">'
                . '<span class="visually-hidden">Filter by release</span>'
                . '<select class="media-filter-select player-layout-pool-filter" data-pool-release-filter aria-label="Filter by release">'
                . '<option value="all">All releases</option>'
                . '</select>'
                . '</label>'
                . '</div>';
            $poolHeadSpacerHtml = '<div class="player-layout-pool-head-slot" aria-hidden="true"></div>';
            ?>

            <!-- ── RELEASE ──────────────────────────────────────────────── -->
            <?php if ($contentTab === 'release'): ?>
            <div class="card content-editor-card" id="releaseEditorCard"
                 data-initial-release="<?php echo htmlspecialchars($contentRelease, ENT_QUOTES, 'UTF-8'); ?>">
                <h3>💿 Catalog</h3>
                <p class="card-note">
                    Pick a release from the pool to preview its catalog tracks. Use the edit button to open metadata and track assignment.
                    Available tracks appear below the release track list while editing. Use Shift-click or Ctrl/Cmd-click to select multiple tracks.
                </p>

                <div class="player-layout-editor playlist-editor-layout" id="releaseEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel playlist-editor-left-panel">
                            <div id="releasePoolView">
                                <div class="player-layout-col-head player-layout-col-head--pool">
                                    <h4 class="player-layout-col-title">Available content</h4>
                                    <div class="player-layout-pool-head-slot player-layout-pool-actions">
                                        <button type="button" class="player-layout-pool-action page-editor-add-btn" id="toggleAddReleaseBtn" aria-expanded="false" aria-label="Add release" title="Add release">
                                            <span class="player-layout-pool-action-icon" aria-hidden="true">＋</span>
                                            <span>Add release</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="add-page-panel" id="addReleasePanel" hidden>
                                        <form id="addReleaseForm" class="add-page-form">
                                            <label class="add-page-field">
                                                <span>Release name</span>
                                                <input type="text" name="title" placeholder="Summer EP" required>
                                            </label>
                                            <div class="add-page-actions">
                                                <button type="submit" class="btn btn-primary">Create release</button>
                                                <button type="button" class="btn" id="cancelAddReleaseBtn">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                    <p id="releaseRegistryStatus" class="status-text page-pool-status"></p>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list page-pool-list" id="releasePoolList" aria-label="Releases"></ol>
                                </div>
                            </div>

                            <div id="releaseTracksPoolView" class="page-editor-view" hidden>
                                <div class="player-layout-col-head player-layout-col-head--pool page-editor-view-head content-editor-view-head">
                                    <div class="content-editor-head-name">
                                        <input type="text" class="content-editor-name-input" id="releaseSettingsTitle" maxlength="120" autocomplete="off" placeholder="Release name" aria-label="Release name">
                                    </div>
                                    <span class="status-text release-settings-status content-editor-name-status" id="releaseSettingsStatus"></span>
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="releaseEditorBackBtn" title="Back to catalog">← Catalog</button>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="playlist-settings-panel" id="releaseSettingsPanel">
                                        <div class="playlist-settings-fields release-catalog-meta-fields">
                                            <label class="playlist-settings-field release-catalog-meta-field--date">
                                                <span>Release date</span>
                                                <div class="date-input-shell">
                                                    <span class="date-input-icon" aria-hidden="true">📅</span>
                                                    <input type="date" id="releaseSettingsDate" autocomplete="off">
                                                </div>
                                            </label>
                                            <label class="playlist-settings-field release-catalog-meta-field--id">
                                                <span>Catalog ID</span>
                                                <input type="text" id="releaseSettingsCatalogId" maxlength="80" autocomplete="off" placeholder="CD001, EP002, CD Mute 142…" aria-label="Catalog ID">
                                            </label>
                                            <p class="hint release-catalog-meta-hint">Your own release reference — any scheme you use in physical or digital catalogs.</p>
                                        </div>
                                        <div class="playlist-settings-fields release-epk-fields">
                                            <h4 class="release-epk-heading">Catalog &amp; press (EPK)</h4>
                                            <p class="hint release-epk-hint">Releases are your catalog truth and press hub. Playlists are listening campaigns — they can span releases but do not own track metadata.</p>
                                            <label class="playlist-settings-field playlist-settings-field--wide">
                                                <span>Description</span>
                                                <textarea id="releaseSettingsDescription" rows="3" maxlength="4000" placeholder="Press blurb or release summary" autocomplete="off"></textarea>
                                            </label>
                                            <label class="playlist-settings-field playlist-settings-field--wide">
                                                <span>Short description</span>
                                                <textarea id="releaseSettingsShortDescription" rows="2" maxlength="300" placeholder="One-liner for cards and summaries" autocomplete="off"></textarea>
                                                <div class="field-note release-short-description-note"><span id="releaseSettingsShortDescriptionCount">0</span>/300 characters</div>
                                            </label>
                                            <label class="playlist-settings-field playlist-settings-field--wide">
                                                <span>Tagline</span>
                                                <input type="text" id="releaseSettingsTagline" maxlength="160" autocomplete="off">
                                            </label>
                                            <label class="playlist-settings-field">
                                                <span>Genre</span>
                                                <input type="text" id="releaseSettingsGenre" maxlength="120" autocomplete="off">
                                            </label>
                                            <label class="playlist-settings-field playlist-settings-field--wide">
                                                <span>Credits</span>
                                                <textarea id="releaseSettingsCredits" rows="3" maxlength="4000" autocomplete="off"></textarea>
                                            </label>
                                            <label class="playlist-settings-field playlist-settings-field--wide">
                                                <span>Press contact</span>
                                                <input type="text" id="releaseSettingsPressContact" maxlength="240" placeholder="7rym &lt;7rym@7rym.net&gt;" autocomplete="off">
                                                <p class="hint">RFC 5322 format: <code>Name &lt;email@domain&gt;</code> — stored as you type for press kits.</p>
                                            </label>
                                            <div class="playlist-settings-field playlist-settings-field--wide release-enjoy-fields">
                                                <h4 class="release-epk-heading release-enjoy-heading">Enjoy here</h4>
                                                <p class="hint release-enjoy-hint">Links to your <strong>player playlist</strong> (not the site homepage, and not a release-only queue — releases are catalog/EPK). Default is the active player playlist (<code>/play/{playlist-id}</code>); point at a campaign playlist if you created one for this release. Social profiles come from <a href="?tab=settings&amp;ctab=sharing">Settings → Sharing</a>.</p>
                                                <div class="release-streaming-grid">
                                                    <label class="playlist-settings-field">
                                                        <span id="releaseSettingsStreamBandpromoLabel">bandPromo</span>
                                                        <input type="text" id="releaseSettingsStreamBandpromo" inputmode="url" placeholder="https://yoursite.com/play/bandpromo-demo" autocomplete="off">
                                                    </label>
                                                    <label class="playlist-settings-field">
                                                        <span>Spotify</span>
                                                        <input type="text" id="releaseSettingsStreamSpotify" inputmode="url" placeholder="https://open.spotify.com/…" autocomplete="off">
                                                    </label>
                                                    <label class="playlist-settings-field">
                                                        <span>Apple Music</span>
                                                        <input type="text" id="releaseSettingsStreamApple" inputmode="url" placeholder="https://music.apple.com/…" autocomplete="off">
                                                    </label>
                                                </div>
                                                <div id="releaseSettingsSocialImports" class="release-social-inline" hidden></div>
                                            </div>
                                            <label class="playlist-settings-field playlist-settings-field--wide">
                                                <span>Press photo assets</span>
                                                <textarea id="releaseSettingsPressPhotos" rows="2" placeholder="ast_…, ast_… (comma or line separated)" autocomplete="off" spellcheck="false"></textarea>
                                                <p class="hint">Supplementary press-kit images, separate from the release cover. Multi-asset picker ships with the unified Visual pool.</p>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="player-layout-col player-layout-col--active">
                        <div class="player-layout-panel">
                            <div class="player-layout-col-head player-layout-col-head--active">
                                <h4 class="player-layout-col-title">
                                    Release <span class="player-layout-count" id="releaseActiveCount"></span>
                                </h4>
                                <div class="player-layout-save-row">
                                    <button type="button" id="releaseSaveBtn" class="btn" hidden>💾 Save release</button>
                                </div>
                            </div>
                            <div class="player-layout-panel-body release-editor-active-body">
                                <div id="releaseCoverPanel" class="release-cover-panel" hidden>
                                    <input type="hidden" id="releaseSettingsPosterAssetId" data-empty-label="No cover selected">
                                    <span id="releaseSettingsPosterAssetId_label" class="visually-hidden" aria-hidden="true">No cover selected</span>
                                    <div class="audio-master-cover-layout release-cover-layout">
                                        <div class="audio-master-cover-preview-shell">
                                            <div class="audio-master-cover-preview" id="releaseCoverPreviewShell">
                                                <div class="audio-master-cover-overlay-actions" id="releaseCoverOverlayActions">
                                                    <button type="button" class="icon-btn media-picker-open audio-master-cover-action" data-field="releaseSettingsPosterAssetId" data-title="Choose release cover" data-targets="illustrations,photos,special" title="Choose cover" aria-label="Choose release cover">✎</button>
                                                    <button type="button" class="icon-btn audio-master-cover-action" id="releaseCoverClearBtn" title="Clear cover" aria-label="Clear cover">↺</button>
                                                </div>
                                                <img id="releaseCoverPreview" alt="Release cover preview" style="display:none;">
                                                <span id="releaseCoverPlaceholder">No cover selected</span>
                                            </div>
                                        </div>
                                        <div class="release-cover-meta">
                                            <h4 class="release-cover-heading">Release cover</h4>
                                            <p class="hint">Album, EP, or single artwork. Press photos stay in the metadata column.</p>
                                            <div class="release-cover-actions">
                                                <button type="button" class="btn" id="releaseCreatePlaylistBtn">Create playlist from release</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="hint player-layout-hint" id="releaseEditorHint">Select a release from the pool, then click edit to change its track membership.</p>
                                <ol class="playlist-editor player-layout-list" id="releaseActiveList" aria-label="Release tracks">
                                    <li class="player-layout-empty">No release selected.</li>
                                </ol>
                                <div id="releaseAvailableSection" class="release-available-section" hidden>
                                    <div class="player-layout-col-head player-layout-col-head--pool release-available-head">
                                        <h4 class="player-layout-col-title">Available content</h4>
                                        <?php echo $poolHeadSpacerHtml; ?>
                                    </div>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list release-available-list" id="releaseAvailableList" aria-label="Available tracks">
                                        <li class="player-layout-empty">Loading tracks…</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── PLAYLIST ─────────────────────────────────────────────── -->
            <?php elseif ($contentTab === 'playlist'): ?>
            <div class="card content-editor-card" id="playlistEditorCard"
                 data-initial-playlist="<?php echo htmlspecialchars($contentPlaylist, ENT_QUOTES, 'UTF-8'); ?>">
                <h3>🎵 Playlists</h3>
                <p class="card-note">
                    Pick a playlist from the pool to preview its track order on the right. Use the edit button to open the track pool and reorder.
                    Use Shift-click or Ctrl/Cmd-click to select multiple tracks. Saving updates the selected playlist immediately.
                </p>

                <div class="player-layout-editor playlist-editor-layout" id="playlistEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel playlist-editor-left-panel">
                            <div id="playlistPoolView">
                                <div class="player-layout-col-head player-layout-col-head--pool">
                                    <h4 class="player-layout-col-title">Available content</h4>
                                    <div class="player-layout-pool-head-slot player-layout-pool-actions">
                                        <button type="button" class="player-layout-pool-action page-editor-add-btn" id="toggleAddPlaylistBtn" aria-expanded="false" aria-label="Add playlist" title="Add playlist">
                                            <span class="player-layout-pool-action-icon" aria-hidden="true">＋</span>
                                            <span>Add playlist</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="add-page-panel" id="addPlaylistPanel" hidden>
                                        <form id="addPlaylistForm" class="add-page-form">
                                            <label class="add-page-field">
                                                <span>Playlist name</span>
                                                <input type="text" name="title" placeholder="Summer singles" required>
                                            </label>
                                            <div class="add-page-actions">
                                                <button type="submit" class="btn btn-primary">Create playlist</button>
                                                <button type="button" class="btn" id="cancelAddPlaylistBtn">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                    <p id="playlistRegistryStatus" class="status-text page-pool-status"></p>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list page-pool-list" id="playlistPoolList" aria-label="Playlists"></ol>
                                </div>
                            </div>

                            <div id="playlistTracksPoolView" class="page-editor-view" hidden>
                                <div class="player-layout-col-head player-layout-col-head--pool page-editor-view-head content-editor-view-head">
                                    <div class="content-editor-head-name">
                                        <input type="text" class="content-editor-name-input" id="playlistSettingsTitle" maxlength="120" autocomplete="off" placeholder="Playlist name" aria-label="Playlist name">
                                    </div>
                                    <span class="status-text playlist-settings-status content-editor-name-status" id="playlistSettingsStatus"></span>
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="playlistEditorBackBtn" title="Back to playlist list">← Playlists</button>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="playlist-settings-panel" id="playlistSettingsPanel">
                                        <div class="playlist-settings-fields release-catalog-meta-fields">
                                            <label class="playlist-settings-field release-catalog-meta-field--date">
                                                <span>Publish date</span>
                                                <div class="date-input-shell">
                                                    <span class="date-input-icon" aria-hidden="true">📅</span>
                                                    <input type="date" id="playlistSettingsPublishDate" autocomplete="off">
                                                </div>
                                            </label>
                                            <label class="playlist-settings-field release-catalog-meta-field--id">
                                                <span>Slug</span>
                                                <input type="text" id="playlistSettingsSlug" maxlength="48" autocomplete="off" placeholder="summer-singles" aria-label="Playlist slug" pattern="[a-z][a-z0-9-]*">
                                            </label>
                                            <p class="hint release-catalog-meta-hint">Public player URL: <code>/play/<span id="playlistSettingsSlugPreview">your-slug</span></code></p>
                                        </div>
                                        <div class="playlist-settings-fields">
                                            <label class="playlist-settings-field playlist-settings-field--wide">
                                                <span>Description</span>
                                                <textarea id="playlistSettingsDescription" rows="3" maxlength="4000" placeholder="Campaign summary or listening notes" autocomplete="off"></textarea>
                                            </label>
                                            <label class="playlist-settings-field playlist-settings-field--wide">
                                                <span>Short description</span>
                                                <textarea id="playlistSettingsShortDescription" rows="2" maxlength="300" placeholder="One-liner for cards and summaries" autocomplete="off"></textarea>
                                                <div class="field-note release-short-description-note"><span id="playlistSettingsShortDescriptionCount">0</span>/300 characters</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="player-layout-col player-layout-col--active">
                        <div class="player-layout-panel">
                            <div class="player-layout-col-head player-layout-col-head--active">
                                <h4 class="player-layout-col-title">
                                    Playlist <span class="player-layout-count" id="playlistActiveCount"></span>
                                </h4>
                                <div class="player-layout-save-row">
                                    <button type="button" id="playlistSaveBtn" class="btn" hidden>💾 Save playlist</button>
                                </div>
                            </div>
                            <div class="player-layout-panel-body playlist-editor-active-body">
                                <div id="playlistCoverPanel" class="release-cover-panel" hidden>
                                    <input type="hidden" id="playlistSettingsPosterAssetId" data-empty-label="No cover selected">
                                    <span id="playlistSettingsPosterAssetId_label" class="visually-hidden" aria-hidden="true">No cover selected</span>
                                    <div class="audio-master-cover-layout release-cover-layout">
                                        <div class="audio-master-cover-preview-shell">
                                            <div class="audio-master-cover-preview" id="playlistCoverPreviewShell">
                                                <div class="audio-master-cover-overlay-actions" id="playlistCoverOverlayActions">
                                                    <button type="button" class="icon-btn media-picker-open audio-master-cover-action" data-field="playlistSettingsPosterAssetId" data-title="Choose playlist cover" data-targets="illustrations,photos,special" title="Choose cover" aria-label="Choose playlist cover">✎</button>
                                                    <button type="button" class="icon-btn audio-master-cover-action" id="playlistCoverClearBtn" title="Clear cover" aria-label="Clear cover">↺</button>
                                                </div>
                                                <img id="playlistCoverPreview" alt="Playlist cover preview" style="display:none;">
                                                <span id="playlistCoverPlaceholder">No cover selected</span>
                                            </div>
                                        </div>
                                        <div class="release-cover-meta">
                                            <h4 class="release-cover-heading">Playlist cover</h4>
                                            <p class="hint">Artwork shown on the player playlist view and share cards.</p>
                                        </div>
                                    </div>
                                </div>
                                <p class="hint player-layout-hint" id="playlistEditorHint">Select a playlist from the pool, then click edit to change its track order.</p>
                                <ol class="playlist-editor player-layout-list" id="playlistActiveList" aria-label="Playlist order">
                                    <li class="player-layout-empty">No playlist selected.</li>
                                </ol>
                                <div id="playlistAvailableSection" class="release-available-section" hidden>
                                    <div class="player-layout-col-head player-layout-col-head--pool release-available-head">
                                        <h4 class="player-layout-col-title">Available content</h4>
                                        <?php echo $poolReleaseFilterHtml; ?>
                                    </div>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list release-available-list" id="playlistAvailableList" aria-label="Available tracks">
                                        <li class="player-layout-empty">Loading tracks…</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── GALLERY ────────────────────────────────────────────────── -->
            <?php elseif ($contentTab === 'gallery'): ?>
            <?php
                require_once __DIR__ . '/biblioteca/gallery-helpers.php';
                require_once __DIR__ . '/biblioteca/gallery-storage.php';
                $galleryError = null;
                try {
                    bandpromo_gallery_ensure_seeded(__DIR__);
                } catch (Throwable $throwable) {
                    $galleryError = $throwable->getMessage();
                }
            ?>
            <?php if ($galleryError): ?>
            <div class="card" style="border-color:#f87171">
                <p class="card-note" style="color:#f87171"><?php echo htmlspecialchars($galleryError); ?></p>
            </div>
            <?php else: ?>
            <div class="card content-editor-card" id="galleryEditorCard"
                 data-initial-gallery="<?php echo htmlspecialchars($contentGallery, ENT_QUOTES, 'UTF-8'); ?>">
                <h3>🖼️ Galleries</h3>
                <p class="card-note">
                    Pick a gallery from the pool to preview its content order on the right. Use the edit button to open the media pool and reorder.
                    Use Shift-click or Ctrl/Cmd-click to select multiple items. Name and alt text can be edited inline in edit mode. No build required.
                </p>

                <div class="player-layout-editor playlist-editor-layout" id="galleryEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel playlist-editor-left-panel">
                            <div id="galleryPoolView">
                                <div class="player-layout-col-head player-layout-col-head--pool">
                                    <h4 class="player-layout-col-title">Available content</h4>
                                    <div class="player-layout-pool-head-slot player-layout-pool-actions">
                                        <button type="button" class="player-layout-pool-action page-editor-add-btn" id="toggleAddGalleryBtn" aria-expanded="false" aria-label="Add gallery" title="Add gallery">
                                            <span class="player-layout-pool-action-icon" aria-hidden="true">＋</span>
                                            <span>Add gallery</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="add-page-panel" id="addGalleryPanel" hidden>
                                        <form id="addGalleryForm" class="add-page-form">
                                            <label class="add-page-field">
                                                <span>Gallery name</span>
                                                <input type="text" name="title" placeholder="Live photos" required>
                                            </label>
                                            <div class="add-page-actions">
                                                <button type="submit" class="btn btn-primary">Create gallery</button>
                                                <button type="button" class="btn" id="cancelAddGalleryBtn">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                    <p id="galleryRegistryStatus" class="status-text page-pool-status"></p>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list page-pool-list" id="galleryPoolList" aria-label="Galleries"></ol>
                                </div>
                            </div>

                            <div id="galleryItemsPoolView" class="page-editor-view" hidden>
                                <div class="player-layout-col-head player-layout-col-head--pool page-editor-view-head content-editor-view-head">
                                    <div class="content-editor-head-name">
                                        <input type="text" class="content-editor-name-input" id="gallerySettingsTitle" maxlength="120" autocomplete="off" placeholder="Gallery name" aria-label="Gallery name">
                                    </div>
                                    <span class="status-text playlist-settings-status content-editor-name-status" id="gallerySettingsStatus"></span>
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="galleryEditorBackBtn" title="Back to gallery list">← Galleries</button>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="player-layout-col-head player-layout-col-head--pool" style="height:auto;min-height:0;padding-top:0">
                                        <h4 class="player-layout-col-title">Available content</h4>
                                        <?php echo $poolReleaseFilterHtml; ?>
                                    </div>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list gallery-pool-list" id="galleryAvailableList" aria-label="Available content">
                                        <li class="player-layout-empty">Loading media…</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="player-layout-col player-layout-col--active">
                        <div class="player-layout-panel">
                            <div class="player-layout-col-head player-layout-col-head--active">
                                <h4 class="player-layout-col-title">
                                    Gallery order <span class="player-layout-count" id="galleryActiveCount"></span>
                                </h4>
                                <div class="player-layout-save-row">
                                    <button type="button" id="gallerySaveBtn" class="btn" hidden>💾 Save gallery</button>
                                </div>
                            </div>
                            <div class="player-layout-panel-body">
                                <p class="hint player-layout-hint" id="galleryEditorHint">Select a gallery from the pool, then click edit to change its content order.</p>
                                <ol class="playlist-editor player-layout-list gallery-active-list" id="galleryActiveList" aria-label="Gallery order">
                                    <li class="player-layout-empty">No gallery selected.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── PAGES ───────────────────────────────────────────────────── -->
            <?php elseif ($contentTab === 'pages'): ?>
            <?php
                $pagePoolData = [];
                foreach ($pageTabEntries as $tabEntry) {
                    $pageKey = (string) ($tabEntry['id'] ?? '');
                    if ($pageKey === '' || !isset($editablePages[$pageKey])) {
                        continue;
                    }
                    $pageSpec = $editablePages[$pageKey];
                    $pagePoolData[] = [
                        'id' => $pageKey,
                        'emoji' => $pageSpec['emoji'],
                        'label' => $pageSpec['label'],
                        'title' => $pageSpec['title'],
                        'required' => !empty($pageSpec['required']),
                        'surface' => $pageSpec['surface'] ?? 'player',
                        'show_in_player' => !empty($pageSpec['show_in_player']),
                    ];
                }
            ?>
            <div class="card content-editor-card" id="pageEditorRoot"
                 data-initial-page="<?php echo htmlspecialchars($contentPage, ENT_QUOTES, 'UTF-8'); ?>"
                 data-pages="<?php echo htmlspecialchars(json_encode($pagePoolData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
                <h3>📄 Pages</h3>
                <p class="card-note">
                    Pick a page from the pool to preview it on the right. Use the edit button to open the block editor on the left.
                    FAQ is required for the login info lightbox; other pages are optional and can appear in the player when enabled under <strong>Content → Player</strong>.
                </p>

                <div class="player-layout-editor page-editor-layout" id="pageEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel page-editor-left-panel">
                            <div id="pagePoolView">
                                <div class="player-layout-col-head player-layout-col-head--pool">
                                    <h4 class="player-layout-col-title">Available content</h4>
                                    <div class="player-layout-pool-head-slot player-layout-pool-actions">
                                        <button type="button" class="player-layout-pool-action page-editor-add-btn" id="toggleAddPageBtn" aria-expanded="false" aria-label="Add page" title="Add page">
                                            <span class="player-layout-pool-action-icon" aria-hidden="true">＋</span>
                                            <span>Add page</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="add-page-panel" id="addPagePanel" hidden>
                                        <form id="addPageForm" class="add-page-form">
                                            <label class="add-page-field">
                                                <span>Page name</span>
                                                <input type="text" name="title" placeholder="Tour dates" required>
                                            </label>
                                            <div class="add-page-actions">
                                                <button type="submit" class="btn btn-primary">Create page</button>
                                                <button type="button" class="btn" id="cancelAddPageBtn">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                    <p id="pageRegistryStatus" class="status-text page-pool-status"></p>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list page-pool-list" id="pagePoolList" aria-label="Available content"></ol>
                                </div>
                            </div>

                            <div id="pageEditorView" class="page-editor-view" hidden>
                                <div class="player-layout-col-head page-editor-view-head content-editor-view-head">
                                    <div class="content-editor-head-name">
                                        <input type="text" class="content-editor-name-input" id="pageTitleInput" value="<?php echo htmlspecialchars($activeContentPage['title'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="120" placeholder="Page name" aria-label="Page name">
                                    </div>
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="pageEditorBackBtn" title="Back to page list">← Pages</button>
                                </div>
                                <div class="player-layout-panel-body page-editor-view-body">
                                    <div class="page-editor-meta">
                                        <label class="page-meta-field" id="pageLabelFieldWrap"<?php echo $activePageIsLoginOnly ? ' hidden' : ''; ?>>
                                            <span>Player tab</span>
                                            <input type="text" id="pageLabelInput" value="<?php echo htmlspecialchars($activeContentPage['label'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="32">
                                        </label>
                                    </div>
                                    <p class="hint page-editor-hint">Build with blocks, change their order, and watch your live preview update while you edit your content.</p>
                                    <div class="page-editor-panel-head">
                                        <h4 class="player-layout-col-title">Page blocks</h4>
                                        <div class="page-editor-toolbar">
                                            <button type="button" class="btn btn-primary" data-action="add-block" data-block-type="text">+ Text</button>
                                            <button type="button" class="btn btn-primary" data-action="add-block" data-block-type="picture">+ Picture</button>
                                            <button type="button" class="btn btn-primary" data-action="add-block" data-block-type="picture_richtext">+ Picture + text</button>
                                            <button type="button" class="btn btn-primary" data-action="add-block" data-block-type="gallery">+ Gallery</button>
                                            <button type="button" class="btn btn-primary" data-action="add-block" data-block-type="list">+ List</button>
                                        </div>
                                    </div>
                                    <div class="page-editor-blocks" id="pageEditorBlocks">
                                        <p class="page-editor-empty">Loading page blocks…</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="player-layout-col player-layout-col--active">
                        <div class="player-layout-panel page-editor-preview-panel">
                            <div class="player-layout-col-head player-layout-col-head--active">
                                <h4 class="player-layout-col-title">Live preview</h4>
                                <div class="player-layout-save-row">
                                    <button id="pageSaveBtn" class="btn" hidden>💾 Save changes</button>
                                </div>
                            </div>
                            <div class="player-layout-panel-body page-editor-preview-body">
                                <div class="page-editor-preview-frame" id="pageEditorPreview">
                                    <p class="page-editor-empty">Loading preview…</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="page-image-picker-modal" id="pageImagePickerModal" aria-hidden="true">
                    <div class="page-image-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="pageImagePickerTitle">
                        <h4 id="pageImagePickerTitle">Choose content image</h4>
                        <p class="hint">Pick from optimized illustrations and photos already prepared for page content.</p>
                        <div class="page-image-picker-grid" id="pageImagePickerGrid"></div>
                        <div class="page-image-picker-actions">
                            <button type="button" class="btn" id="pageImagePickerCancelBtn">Cancel</button>
                            <button type="button" class="btn btn-primary" id="pageImagePickerApplyBtn">Use image</button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-overlay" id="pageUnsavedModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="pageUnsavedModalTitle">
                    <h3 id="pageUnsavedModalTitle">Unsaved changes</h3>
                    <p class="card-note">This page has changes that are not saved yet. What would you like to do?</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-primary" id="pageUnsavedSaveBtn">Save &amp; continue</button>
                        <button type="button" class="btn btn-danger-outline" id="pageUnsavedDiscardBtn">Leave without saving</button>
                        <button type="button" class="btn" id="pageUnsavedCancelBtn">Keep editing</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="pageDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="pageDeleteModalTitle">
                    <h3 id="pageDeleteModalTitle">Delete page?</h3>
                    <p class="card-note">You are about to permanently delete <strong id="pageDeleteModalName"></strong> and all of its content. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-primary icon-btn danger" id="pageDeleteConfirmBtn">Delete page</button>
                        <button type="button" class="btn" id="pageDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="pageBlockDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="pageBlockDeleteModalTitle">
                    <h3 id="pageBlockDeleteModalTitle">Delete block?</h3>
                    <p class="card-note">Delete the <strong id="pageBlockDeleteModalName"></strong> block? This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-primary icon-btn danger" id="pageBlockDeleteConfirmBtn">Delete block</button>
                        <button type="button" class="btn" id="pageBlockDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <?php elseif ($contentTab === 'themes'): ?>
            <div class="card content-editor-card" id="themeEditorRoot"
                 data-initial-theme="<?php echo htmlspecialchars($contentTheme, ENT_QUOTES, 'UTF-8'); ?>">
                <h3>🎨 Themes</h3>
                <p class="card-note">
                    Pick a theme from the pool to preview how its tokens look on the right. Use the edit button to change colors, typography, and layout variables.
                    Setup Default stays locked; duplicate it to create an editable copy. Brand asset paths still live under <a href="?tab=settings&ctab=theme">Settings → Theme</a> during migration.
                </p>

                <div class="player-layout-editor theme-editor-layout playlist-editor-layout" id="themeEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel playlist-editor-left-panel">
                            <div id="themePoolView">
                                <div class="player-layout-col-head player-layout-col-head--pool">
                                    <h4 class="player-layout-col-title">Available content</h4>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <p id="themeRegistryStatus" class="status-text page-pool-status"></p>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list page-pool-list theme-pool-list" id="themePoolList" aria-label="Themes"></ol>
                                </div>
                            </div>

                            <div id="themeEditorView" class="page-editor-view" hidden>
                                <div class="player-layout-col-head player-layout-col-head--pool page-editor-view-head theme-editor-view-head content-editor-view-head">
                                    <div class="theme-editor-head-name content-editor-head-name">
                                        <input type="text" class="theme-editor-name-input content-editor-name-input" id="themeSettingsTitle" maxlength="120" autocomplete="off" placeholder="Theme name" aria-label="Theme name">
                                        <span class="theme-editor-head-badges" id="themeEditorHeadBadges"></span>
                                    </div>
                                    <span class="status-text theme-editor-name-status content-editor-name-status" id="themeSettingsStatus"></span>
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="themeEditorBackBtn" title="Back to theme list">← Themes</button>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body theme-editor-view-body">
                                    <div class="theme-editor-form" id="themeEditorForm">
                                        <p class="theme-editor-locked-note">Loading theme…</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="player-layout-col player-layout-col--active">
                        <div class="player-layout-panel theme-editor-preview-panel">
                            <div class="player-layout-col-head player-layout-col-head--active">
                                <h4 class="player-layout-col-title">Live preview</h4>
                                <div class="player-layout-save-row theme-editor-actions">
                                    <button type="button" id="themeSetActiveBtn" class="btn" hidden>★ Set active</button>
                                    <button type="button" id="themeSaveBtn" class="btn" hidden>💾 Save theme</button>
                                </div>
                            </div>
                            <div class="player-layout-panel-body theme-editor-preview-body">
                                <p class="hint player-layout-hint" id="themeEditorHint">Select a theme from the pool, then click edit to change its tokens.</p>
                                <div class="theme-editor-preview-frame" id="themeEditorPreview">
                                    <p class="theme-editor-empty">No theme selected.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($contentTab === 'player'): ?>
            <div class="card content-editor-card" id="playerLayoutCard"
                 data-layout="<?php echo htmlspecialchars(json_encode($playerLayoutState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">
                <h3>🎛️ Player layout</h3>
                <p class="card-note">
                    Drag content from the pool into the player layout, or back to hide it. Reorder optional items on the right.
                    Use Shift-click or Ctrl/Cmd-click to select multiple items and move them together.
                    Playlist and Lyrics always stay first.
                </p>

                <div class="player-layout-editor" id="playerLayoutEditor">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel">
                            <div class="player-layout-col-head player-layout-col-head--pool">
                                <h4 class="player-layout-col-title">Available content</h4>
                                <?php echo $poolHeadSpacerHtml; ?>
                            </div>
                            <div class="player-layout-panel-body">
                                <ol class="playlist-editor player-layout-list player-layout-pool-list" id="playerLayoutAvailableList" aria-label="Available content"></ol>
                            </div>
                        </div>
                    </div>

                    <div class="player-layout-col player-layout-col--active">
                        <div class="player-layout-panel">
                            <div class="player-layout-col-head player-layout-col-head--active">
                                <h4 class="player-layout-col-title">
                                    Player layout <span class="player-layout-count" id="playerLayoutActiveCount"></span>
                                </h4>
                                <div class="player-layout-save-row">
                                    <button type="button" class="btn" id="savePlayerLayoutBtn" hidden>💾 Save player layout</button>
                                </div>
                            </div>
                            <div class="player-layout-panel-body">
                                <p class="hint player-layout-hint">Playlist and Lyrics are fixed at the top. Drag to reorder optional content. Shift-click or Ctrl/Cmd-click to select multiple items. Move selections back to Available content to remove them from the player.</p>
                                <ol class="playlist-editor player-layout-list" id="playerLayoutActiveList" aria-label="Player layout"></ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>

            <div class="modal-overlay" id="releaseDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="releaseDeleteModalTitle">
                    <h3 id="releaseDeleteModalTitle">Delete release?</h3>
                    <p class="card-note">You are about to permanently delete <strong id="releaseDeleteModalName"></strong>. Its tracks will move to the primary release. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-primary icon-btn danger" id="releaseDeleteConfirmBtn">Delete release</button>
                        <button type="button" class="btn" id="releaseDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="playlistDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="playlistDeleteModalTitle">
                    <h3 id="playlistDeleteModalTitle">Delete playlist?</h3>
                    <p class="card-note">You are about to permanently delete <strong id="playlistDeleteModalName"></strong>. Its track order will be lost. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-primary icon-btn danger" id="playlistDeleteConfirmBtn">Delete playlist</button>
                        <button type="button" class="btn" id="playlistDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="galleryDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="galleryDeleteModalTitle">
                    <h3 id="galleryDeleteModalTitle">Delete gallery?</h3>
                    <p class="card-note">You are about to permanently delete <strong id="galleryDeleteModalName"></strong>. Its content order will be lost. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-primary icon-btn danger" id="galleryDeleteConfirmBtn">Delete gallery</button>
                        <button type="button" class="btn" id="galleryDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="themeDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="themeDeleteModalTitle">
                    <h3 id="themeDeleteModalTitle">Delete theme?</h3>
                    <p class="card-note">You are about to permanently delete <strong id="themeDeleteModalName"></strong>. Its color and typography settings will be lost. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-primary icon-btn danger" id="themeDeleteConfirmBtn">Delete theme</button>
                        <button type="button" class="btn" id="themeDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== SETTINGS TAB ===================== -->
        <div class="tab-content <?php echo $tab === 'settings' ? 'active' : ''; ?>">

            <!-- Settings sub-tab navigation -->
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
                    $url = '?tab=settings&ctab=' . urlencode($ct);
                ?>
                <a href="<?php echo $url; ?>" class="tab-link <?php echo $active; ?>">
                    <?php echo htmlspecialchars($emoji . ' ' . $label); ?>
                </a>
                <?php endforeach; ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-settings" onclick="toggleHelp('settings')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-settings">
                <?php if ($configTab === 'basics'): ?>
                    Basics is the place for your public site title, URL, description, author, and contact. Contact is suggested from author + site URL until you edit it manually. <strong>Save validates only the basics fields</strong>, then writes them back into the full config. If internal config sections are missing, use the <strong>Repair</strong> link to restore them from the config template.
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
            $cfgFull = bandpromo_load_runtime_config_raw();
            $cfgSite = $cfgFull['site'] ?? [];
            $cfgSiteEmailAuto = !array_key_exists('email_auto', $cfgSite) || $cfgSite['email_auto'] !== false;
            require_once __DIR__ . '/biblioteca/site-contact.php';
            $cfgSiteContact = trim((string) ($cfgSite['email'] ?? ''));
            if ($cfgSiteContact === '' || $cfgSiteEmailAuto) {
                $cfgSiteContact = bandpromo_site_contact_derive(
                    (string) ($cfgSite['author'] ?? ''),
                    (string) ($cfgSite['url'] ?? '')
                );
            }
            ?>
            <div class="card">
                <h3>⚙️ Site Basics</h3>
                <p class="card-note">
                    Edit the everyday public site details here without touching theme media paths or sharing settings. Contact uses RFC 5322 format (for example <code>7rym &lt;7rym@7rym.net&gt;</code>), is suggested from author + URL until you change it, and is canonicalized on save for future mail features.
                </p>
                <input type="hidden" id="cfg_site_language" value="en">
                <input type="hidden" id="cfg_site_email_auto" value="<?php echo $cfgSiteEmailAuto ? '1' : '0'; ?>">
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
                        <label for="cfg_site_author">Author / owner</label>
                        <input type="text" id="cfg_site_author" value="<?php echo htmlspecialchars((string) ($cfgSite['author'] ?? '')); ?>" placeholder="Artist, band, label, or project owner">
                    </div>
                    <div class="form-group">
                        <label for="cfg_site_email">Contact</label>
                        <input type="text" id="cfg_site_email" value="<?php echo htmlspecialchars($cfgSiteContact); ?>" placeholder="7rym &lt;7rym@7rym.net&gt;" autocomplete="email">
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

        </div>

        <!-- ===================== SYSTEM TAB ===================== -->
        <div id="tab-system" class="tab-content <?php echo $tab === 'system' ? 'active' : ''; ?>">
            <div class="tabs sub-tabs">
                <a href="?tab=system&amp;stab=publish" class="tab-link <?php echo $systemTab === 'publish' ? 'active' : ''; ?>">🚀 Publish</a>
                <a href="?tab=system&amp;stab=audit" class="tab-link <?php echo $systemTab === 'audit' ? 'active' : ''; ?>">🛡️ Audit</a>
                <?php if ($systemTab === 'publish'): ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-build" onclick="toggleHelp('build')" title="Show/hide help">ⓘ</button>
                <?php else: ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-audit" onclick="toggleHelp('audit')" title="Show/hide help">ⓘ</button>
                <?php endif; ?>
            </div>

            <?php if ($systemTab === 'publish'): ?>
            <div class="admin-help-box collapsed" id="help-build">
                <strong>Run Publish Build</strong> regenerates delivery files and player artifacts from your current Content setup. It checks site settings first, then runs the Python publish pipeline. It does <strong>not</strong> repair the asset catalog automatically — use <strong>Repair catalog</strong> in Publish actions when uploads need masters or registry fixes.<br><br>
                <strong>Publish status</strong> is site-wide: catalog registration, delivery coverage for registered audio, and pending publish work. Track metadata quality for a specific playlist belongs in Content → Playlists or Files → Audio.<br><br>
                Use <strong>Refresh Image Files</strong> when only publish-ready photos, illustrations, or theme images need to be regenerated.
            </div>

            <div id="publishStatusCard" class="card publish-status-card">
                <div class="build-validation-head">
                    <h3>📊 Publish status</h3>
                    <span id="publishStatusOverall" class="badge audit-status-badge status-neutral">Checking…</span>
                </div>
                <div id="publishStatusSummary" class="publish-status-summary"></div>
            </div>

            <div id="publishActionsCard" class="card publish-actions-card">
                <div class="build-validation-head">
                    <h3>⚡ Publish actions</h3>
                </div>
                <div class="publish-actions-toolbar">
                    <button type="button" id="buildBtn" class="btn">▶️ Run Publish Build</button>
                    <button type="button" id="optimizeBtn" class="btn">🖼️ Refresh Image Files</button>
                    <button type="button" id="contentAutofixPreviewBtn" class="btn">🛠️ Repair catalog</button>
                    <button type="button" id="recommendedBuildBtn" class="btn" style="display:none"></button>
                    <button type="button" id="contentAutofixApplyBtn" class="btn" hidden>Apply repairs</button>
                </div>
                <p id="contentAutofixStatus" class="build-log-status publish-action-status"></p>
                <ul id="contentAutofixReport" class="content-autofix-report" hidden></ul>
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
            <?php else: ?>
            <div class="admin-help-box collapsed" id="help-audit">
                Separate admin audit trail for management actions only. Use this to trace who changed users, content, settings, files, and publish runs, without mixing those records into listener activity analytics.
            </div>

            <form method="GET" class="filter-bar">
                <input type="hidden" name="tab" value="system">
                <input type="hidden" name="stab" value="audit">
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
            <?php endif; ?>
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
    </script>
    <script>
        window.BANDPROMO_SESSION_AUTH = {
            enabled: true,
            loginUrl: '/admin.php',
            pingUrl: '/biblioteca/session-check.php',
            pingIntervalMs: 300000,
        };
    </script>
    <script src="biblioteca/site-contact.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/site-contact.js'); ?>"></script>
    <script src="biblioteca/session-auth.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/session-auth.js'); ?>"></script>
    <?php if ($tab === 'content'): ?>
    <script src="biblioteca/content-save-ui.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/content-save-ui.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($tab === 'content' && $contentTab === 'pages'): ?>
    <script src="biblioteca/page-editor.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/page-editor.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($tab === 'content' && $contentTab === 'release'): ?>
    <script src="biblioteca/release-editor.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/release-editor.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($tab === 'content' && $contentTab === 'themes'): ?>
    <script src="biblioteca/theme-editor.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/theme-editor.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($tab === 'content' && in_array($contentTab, ['pages', 'player'], true)): ?>
    <script src="biblioteca/content-admin.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/content-admin.js'); ?>"></script>
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
