<?php
require_once __DIR__ . '/biblioteca/https.php';
require_once __DIR__ . '/biblioteca/setup-state.php';
bandpromo_enforce_https();
bandpromo_configure_session_storage();

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
require_once 'biblioteca/demo-catalog-state.php';
require_once 'biblioteca/theme-storage.php';
require_once 'biblioteca/playlist-storage.php';
require_once 'biblioteca/gallery-storage.php';
require_once 'biblioteca/player-markdown.php';

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

function bandpromo_admin_render_package_update_card(): void
{
    ?>
            <div class="card package-update-card package-update-card--quiet" id="packageUpdateCard">
                <div class="package-update-status" id="packageUpdateStatus">
                    <span class="package-update-status-message" id="packageUpdateStatusMessage">Checking for updates…</span>
                    <span class="package-update-status-actions" id="packageUpdateStatusActions">
                        <button type="button" class="btn btn-sm" id="packageUpdateRefreshBtn" hidden>Check again</button>
                        <button type="button" class="btn btn-primary btn-sm" id="packageUpdateApplyBtn" hidden>Install update</button>
                    </span>
                </div>
            </div>
    <?php
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
bandpromo_demo_release_ensure_preferences(__DIR__);
$demoCatalogShouldSuggestHide = bandpromo_demo_catalog_should_suggest_hide(__DIR__);
$demoCatalogVisible = bandpromo_demo_catalog_is_visible(__DIR__);
$demoReleaseId = bandpromo_demo_release_id(__DIR__);
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
        'icon' => '📊',
        'href' => '?tab=analytics',
        'description' => 'Listener stats and trends',
    ],
    [
        'label' => 'Files',
        'icon' => '📁',
        'href' => '?tab=files&fpanel=audio',
        'description' => 'Uploads and cover art',
    ],
    [
        'label' => 'Content',
        'icon' => '✏️',
        'href' => '?tab=content',
        'description' => 'Pages, playlists, galleries',
    ],
    [
        'label' => 'Public site',
        'icon' => '🌐',
        'href' => $siteUrl !== '' ? $siteUrl : '../',
        'description' => 'Preview as visitors see it',
        'external' => true,
    ],
    [
        'label' => 'Guides',
        'icon' => '📖',
        'href' => '?tab=docs&doc_scope=operator',
        'description' => 'Operator help',
    ],
];

$welcomeChecklist = [];
$welcomeCompletedChecks = 0;
$welcomeTotalChecks = 0;
$welcomeSetupComplete = false;
$welcomeNextSteps = [];
$welcomePrimaryNotice = '';

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
// Structural config repair can write web-config.json — only on Settings, never on
// read-only Catalogue/Files navigations.
if ($authenticated && (string) ($_GET['tab'] ?? '') === 'settings') {
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
require_once 'biblioteca/time-helpers.php';
require_once 'biblioteca/activity-store.php';

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

// Auth + CSRF are done. Release the session lock before heavy I/O and HTML
// so parallel admin API fetches are not serialized behind this page render.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
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
        $redirectQuery['stab'] = $requestedTab === 'audit' ? 'audit' : 'deliverables';
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
// Legacy Illustrations / Photos / Video tabs → unified Visual pool.
if (in_array($filesPanel, ['photos', 'video', 'illustrations'], true)) {
    $filesPanel = 'visual';
}
if (!in_array($filesPanel, ['audio', 'visual', 'sfx', 'special'], true)) {
    $filesPanel = 'audio';
}

if ($tab === 'welcome') {
    $welcomeState = bandpromo_admin_welcome_state(__DIR__);
} else {
    // Non-Welcome tabs: latch file only — never run the install checklist scanner.
    $welcomeState = [
        'checklist' => [],
        'completed_count' => 0,
        'total_count' => 0,
        'setup_complete' => bandpromo_admin_welcome_setup_is_complete(__DIR__),
        'setup_latched' => bandpromo_admin_welcome_setup_is_complete(__DIR__),
        'next_steps' => [],
    ];
}
$welcomeChecklist = $welcomeState['checklist'];
$welcomeCompletedChecks = $welcomeState['completed_count'];
$welcomeTotalChecks = $welcomeState['total_count'];
$welcomeSetupComplete = $welcomeState['setup_complete'];
$welcomeNextSteps = $welcomeState['next_steps'];
if ($welcomeSetupComplete) {
    $welcomePrimaryNotice = '';
} elseif ($welcomeNextSteps !== []) {
    $welcomePrimaryNotice = $welcomeCompletedChecks . ' of ' . $welcomeTotalChecks . ' checks complete. Next: ' . $welcomeNextSteps[0]['description'];
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
if ($tab === 'content') {
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
    if ($contentPlaylist !== '' && !bandpromo_demo_catalog_entity_is_visible(__DIR__, $contentPlaylist)) {
        $contentPlaylist = '';
    }
    if ($contentPlaylist === '') {
        try {
            bandpromo_playlist_ensure_seeded(__DIR__);
            $contentPlaylist = bandpromo_playlist_default_active_id(__DIR__);
        } catch (Throwable $throwable) {
            $contentPlaylist = bandpromo_demo_catalog_is_visible(__DIR__) ? BANDPROMO_PLAYLIST_DEMO_ID : '';
        }
    }
    $contentRelease = isset($_GET['release']) ? bandpromo_release_normalize_id((string) $_GET['release']) : '';
    if ($contentRelease !== '' && !bandpromo_demo_catalog_entity_is_visible(__DIR__, $contentRelease)) {
        $contentRelease = '';
    }
    if ($contentRelease === '') {
        try {
            bandpromo_release_ensure_seeded(__DIR__);
            $contentRelease = BANDPROMO_RELEASE_DEFAULT_ID;
        } catch (Throwable $throwable) {
            $contentRelease = BANDPROMO_RELEASE_DEFAULT_ID;
        }
    }
    $contentGallery = isset($_GET['gallery']) ? bandpromo_gallery_normalize_id((string) $_GET['gallery']) : '';
    if ($contentGallery !== '' && !bandpromo_demo_catalog_entity_is_visible(__DIR__, $contentGallery)) {
        $contentGallery = '';
    }
    if ($contentGallery === '') {
        try {
            bandpromo_gallery_ensure_seeded(__DIR__);
            $contentGallery = bandpromo_gallery_default_admin_content_id(__DIR__);
            if ($contentGallery === '') {
                $contentGallery = BANDPROMO_GALLERY_DEMO_ID;
            }
        } catch (Throwable $throwable) {
            $contentGallery = bandpromo_demo_catalog_is_visible(__DIR__) ? BANDPROMO_GALLERY_DEMO_ID : '';
        }
    }
    $contentPage = isset($_GET['page']) ? bandpromo_page_normalize_id((string) $_GET['page']) : 'faq';
    if (!is_string($contentPage) || !array_key_exists($contentPage, $editablePages)) {
        $contentPage = array_key_exists('faq', $editablePages) ? 'faq' : (array_key_first($editablePages) ?: 'faq');
    }
    $playerLayoutState = $contentTab === 'player'
        ? bandpromo_player_layout_admin_state(__DIR__)
        : ['locked' => [], 'active' => [], 'available' => []];
} else {
    $contentTheme = BANDPROMO_THEME_DEFAULT_ID;
    $contentPlaylist = bandpromo_demo_catalog_is_visible(__DIR__) ? BANDPROMO_PLAYLIST_DEMO_ID : '';
    $contentRelease = BANDPROMO_RELEASE_DEFAULT_ID;
    $contentGallery = bandpromo_demo_catalog_is_visible(__DIR__) ? BANDPROMO_GALLERY_DEMO_ID : '';
    $contentPage = array_key_exists('faq', $editablePages) ? 'faq' : (array_key_first($editablePages) ?: 'faq');
    $playerLayoutState = ['locked' => [], 'active' => [], 'available' => []];
}
$activeContentPage = $editablePages[$contentPage];
$activePageIsLoginOnly = ($activeContentPage['surface'] ?? '') === 'login';

// Settings sub-tab
$configTab = $_GET['ctab'] ?? 'basics';
if ($tab === 'settings' && $configTab === 'theme') {
    header('Location: /admin.php?tab=content&cntab=themes');
    exit;
}
if (!in_array($configTab, ['basics', 'support', 'sharing'], true)) {
    $configTab = 'basics';
}

// System sub-tab
$allowedSystemTabs = ['deliverables', 'publish', 'audit', 'backup', 'security'];
$systemTab = $_GET['stab'] ?? 'deliverables';
if ($systemTab === 'activity') {
    $systemTab = 'backup';
}
if ($systemTab === 'publish') {
    $redirectQuery = $_GET;
    $redirectQuery['stab'] = 'deliverables';
    header('Location: /admin.php?' . http_build_query($redirectQuery));
    exit;
}
if (!in_array($systemTab, $allowedSystemTabs, true)) {
    $systemTab = 'deliverables';
}

$siteBackupStatus = null;
$siteBackupJobs = [];
if ($tab === 'system' && $systemTab === 'backup') {
    require_once __DIR__ . '/biblioteca/site-backup-portability.php';
    bandpromo_site_backup_process_pending(__DIR__);
    $siteBackupStatus = bandpromo_site_backup_status(__DIR__);
    $siteBackupJobs = $siteBackupStatus['jobs'] ?? [];
}

// Date range (ISO YYYY-MM-DD)
$dateStart = bandpromo_admin_normalize_date_param((string) ($_GET['date_start'] ?? ''), date('Y-m-d', strtotime('-30 days')));
$dateEnd   = bandpromo_admin_normalize_date_param((string) ($_GET['date_end'] ?? ''), date('Y-m-d'));

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

// Initialize analytics only when the Analytics tab is active.
$analytics = null;
$platformStats = [
    'total_plays' => 0,
    'total_listening_time' => 0,
    'unique_users' => 0,
    'total_sessions' => 0,
    'device_breakdown' => [],
    'quality_estimate' => [],
    'hourly_distribution' => [],
    'daily_distribution' => [],
    'activity_types' => [],
];
$activityStoreStatus = ['ok' => true];
if ($tab === 'analytics') {
    $analytics = new PlaybackAnalytics();
    try {
        $platformStats = $analytics->getPlatformStats($dateStart, $dateEnd);
    } catch (Throwable $e) {
        error_log('bandPromo admin analytics init error: ' . $e->getMessage());
    }
    $activityStoreStatus = bandpromo_activity_store_migration_status(__DIR__);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="biblioteca/admin.css?v=<?php echo filemtime(__DIR__ . '/biblioteca/admin.css'); ?>">
    <?php echo bandpromo_theme_render_css(__DIR__); ?>
    <?php if ($tab === 'analytics'): ?>
    <script src="vendor/chart.js/chart.umd.min.js?v=<?php echo filemtime(__DIR__ . '/vendor/chart.js/chart.umd.min.js'); ?>"></script>
    <?php endif; ?>
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
        <?php if (empty($activityStoreStatus['ok'])): ?>
            <?php $activityStoreOperatorMessage = bandpromo_activity_store_operator_status_message($activityStoreStatus); ?>
            <div class="message error">
                <?php echo htmlspecialchars($activityStoreOperatorMessage); ?>
            </div>
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
                    This page is your dashboard. Use <strong>Notifications</strong> in the header for live tasks (track/video preparation, Site update, validation), then jump to <strong>Files</strong> or <strong>Content</strong> to work on them.
                <?php else: ?>
                    Use this page as your setup checklist while bandPromo is still getting the installation ready. bandPromo decides as much as it can on its own, then points you to the next incomplete step here on Welcome. <strong>Notifications</strong> is for live work only (media preparation, Site update, publish follow-ups) — not a second copy of this checklist. Jump to <strong>Settings</strong> for site basics and branding, <strong>Files</strong> for uploads and metadata, <strong>Content</strong> for pages and playlist shaping, <strong>System → Deliverables</strong> during setup, and <strong>Documentation</strong> for deeper explanations.
                <?php endif; ?>
            </div>

            <?php if ($welcomeSetupComplete): ?>
            <?php
            $catalogHealth = ['needs_attention' => false, 'reasons' => [], 'href' => '?tab=system&stab=deliverables#catalog-repair'];
            if ($tab === 'welcome') {
                require_once __DIR__ . '/biblioteca/asset-registry.php';
                $catalogHealth = bandpromo_asset_registry_health_snapshot(__DIR__);
            }
            ?>
            <?php if (!empty($catalogHealth['needs_attention'])): ?>
            <div class="card welcome-catalog-repair-card" id="welcomeCatalogRepairCard">
                <h2>🔧 Catalog needs a repair pass</h2>
                <p class="card-note">
                    bandPromo found registry housekeeping that does not run on every page load (so Content stays fast). Preview and apply repairs under System → Deliverables — this does not publish the public site by itself.
                </p>
                <?php if (!empty($catalogHealth['reasons']) && is_array($catalogHealth['reasons'])): ?>
                <ul class="welcome-list">
                    <?php foreach ($catalogHealth['reasons'] as $reason): ?>
                    <li><?php echo htmlspecialchars((string) $reason); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="card-actions">
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars((string) ($catalogHealth['href'] ?? '?tab=system&amp;stab=deliverables#catalog-repair')); ?>">Open Repair catalog</a>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($demoCatalogShouldSuggestHide): ?>
            <div class="card welcome-demo-catalog-card" id="welcomeDemoCatalogCard">
                <h2>🎭 bandPromo demo catalog</h2>
                <p class="card-note">
                    You have a release with a track on a playlist. You can hide the shipped <strong>bandPromo demo</strong> release and its campaign playlists, galleries, pages, and owned Audio/Visual media from the player and content editors. Brand assets and Sound effects stay visible. Demo files stay on disk and continue to build normally. If you later delete that catalog, the demo is shown again automatically.
                </p>
                <div class="card-actions">
                    <button type="button" class="btn btn-primary" id="demoCatalogHideBtn">Hide demo catalog</button>
                    <a class="btn" href="?tab=settings&amp;ctab=basics">Open Settings</a>
                    <span id="demoCatalogHideStatus" class="status-text"></span>
                </div>
            </div>
            <?php endif; ?>

            <?php bandpromo_admin_render_package_update_card(); ?>

            <div class="card welcome-card welcome-card-dashboard">
                <h3 class="welcome-dashboard-heading">Quick actions</h3>
                <ul class="welcome-dashboard-links">
                    <?php foreach ($welcomeDashboardLinks as $link): ?>
                        <li>
                            <a class="welcome-dashboard-link" href="<?php echo htmlspecialchars($link['href']); ?>"<?php echo !empty($link['external']) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?> title="<?php echo htmlspecialchars($link['description']); ?>">
                                <span class="welcome-dashboard-link-icon" aria-hidden="true"><?php echo htmlspecialchars($link['icon'] ?? '•'); ?></span>
                                <span class="welcome-dashboard-link-body">
                                    <strong><?php echo htmlspecialchars($link['label']); ?></strong>
                                    <span><?php echo htmlspecialchars($link['description']); ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($welcomeNextSteps !== []): ?>
            <div class="card welcome-card welcome-card-dashboard">
                <h3 class="welcome-dashboard-heading">What to do next</h3>
                <ol class="welcome-list welcome-list-numbered">
                    <?php foreach ($welcomeNextSteps as $step): ?>
                        <li>
                            <a class="welcome-link is-<?php echo htmlspecialchars((string) ($step['severity'] ?? 'nonblocking')); ?>" href="<?php echo htmlspecialchars($step['href']); ?>"><strong><?php echo htmlspecialchars($step['label']); ?></strong></a>
                            <span><?php echo htmlspecialchars($step['description']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="card welcome-card">
                <h2>🌍 Welcome to bandPromo</h2>

                <?php if ($welcomePrimaryNotice !== ''): ?>
                <div class="welcome-callout">
                    <?php echo htmlspecialchars($welcomePrimaryNotice); ?>
                </div>
                <?php endif; ?>

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
            </div>

            <?php if ($demoCatalogShouldSuggestHide): ?>
            <div class="card welcome-demo-catalog-card" id="welcomeDemoCatalogCard">
                <h2>🎭 bandPromo demo catalog</h2>
                <p class="card-note">
                    You have a release with a track on a playlist. You can hide the shipped <strong>bandPromo demo</strong> release and its campaign playlists, galleries, pages, and owned Audio/Visual media from the player and content editors. Brand assets and Sound effects stay visible. Demo files stay on disk and continue to build normally. If you later delete that catalog, the demo is shown again automatically.
                </p>
                <div class="card-actions">
                    <button type="button" class="btn btn-primary" id="demoCatalogHideBtn">Hide demo catalog</button>
                    <a class="btn" href="?tab=settings&amp;ctab=basics">Open Settings</a>
                    <span id="demoCatalogHideStatus" class="status-text"></span>
                </div>
            </div>
            <?php endif; ?>

            <?php bandpromo_admin_render_package_update_card(); ?>
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
                <strong>Dash</strong> gives a quick overview of platform stats. The other tabs show detailed reports — <strong>Hitlist</strong> ranks your most-played songs, <strong>Patterns</strong> shows where people stop or skip, and <strong>Log</strong> shows raw activity entries. All timestamps are stored in UTC; choose UTC or local display in Settings → Basics.
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
                <p class="hint">Activity by hour (<?php echo htmlspecialchars(bandpromo_admin_time_axis_label()); ?>). <?php echo htmlspecialchars(bandpromo_admin_time_policy_note()); ?></p>
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
            <?php
                $logEntrySummary = ($logTotal > 200 ? 'Showing 200 of ' . number_format($logTotal) : number_format($logTotal)) . ' entries';
                renderFilterBar('analytics', $dateStart, $dateEnd, 'log', [
                    'activity_types' => $activityTypes,
                    'activity_filter' => $logActivityFilter,
                    'entry_summary' => $logEntrySummary,
                    'export_links' => [
                        [
                            'label' => 'Export JSONL',
                            'href' => 'biblioteca/export-activity-log.php?format=jsonl&date_start=' . rawurlencode($dateStart) . '&date_end=' . rawurlencode($dateEnd),
                        ],
                        [
                            'label' => 'Export CSV',
                            'href' => 'biblioteca/export-activity-log.php?format=csv&date_start=' . rawurlencode($dateStart) . '&date_end=' . rawurlencode($dateEnd),
                        ],
                    ],
                ]);
            ?>

            <?php if (empty($logEntries)): ?>
                <p class="empty-msg">No log entries found.</p>
            <?php else: ?>
            <div class="table-scroll">
                <table style="font-size:13px;">
                    <thead><tr><th>Time</th><th>User</th><th>Activity</th><th>Track</th><th>Detail</th></tr></thead>
                    <tbody>
                        <?php foreach ($logEntries as $entry): ?>
                        <tr>
                            <td class="text-muted nowrap"><?php echo htmlspecialchars(bandpromo_admin_format_timestamp($entry)); ?></td>
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
                <button class="btn btn-primary" onclick="openUserModal()">➕ Add User</button>
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
                            <button class="icon-btn icon-btn--danger" title="Delete user" onclick="deleteUser('<?php echo htmlspecialchars($uname, ENT_QUOTES); ?>')">🗑️</button>
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
                    'audio'   => ['🎵', 'Audio'],
                    'visual'  => ['🎨', 'Visual'],
                    'sfx'     => ['🔊', 'Sound effects'],
                    'special' => ['✨', 'Brand assets'],
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
                    Work with <strong>master</strong> audio files only. Filter by catalogue release or search by registered title. Green / amber / red badges show metadata health — click a row for quick tags, or the pencil for the full editor.
                    <br><strong>After upload:</strong> bandPromo prepares delivery automatically. Tracks appear in Content pools when ready.
                <?php elseif ($filesPanel === 'visual'): ?>
                    Still images and video for covers, galleries, pages, and Brand shells (logo / still / living). Catalogue lists every campaign that plays the file — including Base-brand fallback when a Brand left that slot empty. Files in a Brand library that no campaign plays show that Brand, not Orphan. Orphans are not used by any campaign and are not in a Brand library.
                    <br><strong>After upload:</strong> delivery variants prepare automatically — check Notifications if a video stalls.
                <?php elseif ($filesPanel === 'sfx'): ?>
                    Short brand UI clips (welcome, login, and similar). These belong to brands — assign them under Content → Branding. Not release tracks.
                <?php elseif ($filesPanel === 'special'): ?>
                    Brand visuals: logos, share covers, and still/living backgrounds. These belong to brands. Shell audio belongs under Sound effects.
                <?php endif; ?>
            </div>

            <!-- Audio -->
            <div class="media-panel card" id="panel-audio" <?php echo $filesPanel !== 'audio' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            <?php echo bandpromo_admin_files_permanent_warning_line(true); ?>
                        </span>
                    </div>
                </div>
                <div class="audio-pool-toolbar" data-media-list-header="audio">
                    <div class="audio-pool-toolbar-main">
                        <label class="media-filter-label">
                            <span class="visually-hidden">Filter by catalogue</span>
                            <select class="media-filter-select" data-media-release-filter aria-label="Filter by catalogue">
                                <option value="all">All files</option>
                                <option value="orphans">Orphans</option>
                            </select>
                        </label>
                        <label class="media-filter-label audio-pool-toolbar-search">
                            <span class="visually-hidden">Filter by title</span>
                            <input type="search" class="media-filter-input" data-media-name-filter="audio" placeholder="Filter by title…" autocomplete="off" aria-label="Filter audio by title">
                        </label>
                    </div>
                    <div class="audio-pool-toolbar-actions media-file-actions">
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('audio')" aria-label="Upload audio files" title="Upload audio files"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="audio" data-download-variant="master" disabled aria-label="Download selected audio files" title="Download selected audio files"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn media-bulk-delete-btn" data-bulk-delete-target="audio" disabled aria-label="Delete selected audio files" title="Delete selected audio files"><span class="media-labeled-action-icon" aria-hidden="true">🗑️</span><span>Delete</span></button>
                    </div>
                </div>
                <div class="media-file-col-headers" data-audio-sort-headers role="row">
                    <div class="media-file-select-toggle" role="group" aria-label="Select visible tracks">
                        <button type="button" class="audio-select-chip" data-media-select-mode="all" data-target="audio" aria-pressed="false" title="Select all visible tracks" aria-label="Select all visible tracks">☑</button>
                        <button type="button" class="audio-select-chip" data-media-select-mode="none" data-target="audio" aria-pressed="true" title="Clear selection" aria-label="Clear selection">☐</button>
                    </div>
                    <button type="button" class="media-file-col-sort" data-audio-sort="track" aria-pressed="false">Track</button>
                    <button type="button" class="media-file-col-sort" data-audio-sort="date" aria-pressed="true">Date</button>
                    <button type="button" class="media-file-col-sort" data-audio-sort="release" aria-pressed="false">Release</button>
                    <button type="button" class="media-file-col-sort media-file-col-sort--size" data-audio-sort="size" aria-pressed="false">Size</button>
                    <span class="media-file-actions media-file-col-headers-actions" aria-hidden="true"></span>
                </div>
                <div id="filelist-audio" class="media-file-list"><span class="text-muted">Loading…</span></div>
                <div class="media-panel-footer"><span id="audio-count" class="media-count"></span></div>
            </div>

            <!-- Visual pool (images + video) -->
            <div class="media-panel card" id="panel-visual" data-pool-layout="grid" data-pool-thumb-size="medium" <?php echo $filesPanel !== 'visual' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            Catalogue is the campaign that uses this file (gallery, cover, poster, page, or Brand shell: logo and backgrounds). Empty Brand slots inherit the Base brand. Files in a Brand library that no campaign plays show that Brand, not Orphan. Orphans are not used by any campaign and are not in a Brand library.
                            <br>
                            <?php echo bandpromo_admin_files_permanent_warning_line(true); ?>
                        </span>
                    </div>
                </div>
                <div class="audio-pool-toolbar visual-pool-toolbar" data-media-list-header="visual">
                    <div class="audio-pool-toolbar-main visual-pool-toolbar-main">
                        <div class="visual-filter-chip-group" role="group" aria-label="Filter by media type">
                            <button type="button" class="visual-filter-chip is-active" data-pool-type-filter="all" data-pool-panel="visual" aria-pressed="true">All</button>
                            <button type="button" class="visual-filter-chip" data-pool-type-filter="image" data-pool-panel="visual" aria-pressed="false">Images</button>
                            <button type="button" class="visual-filter-chip" data-pool-type-filter="video" data-pool-panel="visual" aria-pressed="false">Video</button>
                        </div>
                        <label class="media-filter-label">
                            <span class="visually-hidden">Filter by catalogue</span>
                            <select class="media-filter-select" data-media-release-filter aria-label="Filter by catalogue">
                                <option value="all">All files</option>
                                <option value="orphans">Orphans</option>
                            </select>
                        </label>
                        <label class="media-filter-label audio-pool-toolbar-search">
                            <span class="visually-hidden">Filter by title</span>
                            <input type="search" class="media-filter-input" data-media-name-filter="visual" placeholder="Filter by title…" autocomplete="off" aria-label="Filter visual assets by title">
                        </label>
                        <div class="visual-view-toggle" role="group" aria-label="Visual pool layout">
                            <button type="button" class="visual-view-btn is-active" data-pool-view="grid" data-pool-panel="visual" aria-pressed="true" title="Grid view">Grid</button>
                            <button type="button" class="visual-view-btn" data-pool-view="list" data-pool-panel="visual" aria-pressed="false" title="List view">List</button>
                        </div>
                        <div class="visual-view-toggle visual-thumb-size-toggle" role="group" aria-label="List thumbnail size">
                            <button type="button" class="visual-view-btn" data-pool-thumb-size="small" aria-pressed="false" title="Small list thumbnails (70×70)">S</button>
                            <button type="button" class="visual-view-btn is-active" data-pool-thumb-size="medium" aria-pressed="true" title="Medium list thumbnails (100×100)">M</button>
                            <button type="button" class="visual-view-btn" data-pool-thumb-size="large" aria-pressed="false" title="Large list thumbnails (125×125)">L</button>
                        </div>
                    </div>
                    <div class="audio-pool-toolbar-actions visual-pool-toolbar-actions media-file-actions">
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('visual')" aria-label="Upload visual files" title="Upload images or videos"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="visual" data-download-variant="original" disabled aria-label="Download selected files" title="Download selected files"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn media-bulk-delete-btn" data-bulk-delete-target="visual" disabled aria-label="Delete selected files" title="Delete selected files"><span class="media-labeled-action-icon" aria-hidden="true">🗑️</span><span>Delete</span></button>
                    </div>
                </div>
                <div class="media-file-col-headers visual-pool-col-headers" data-pool-list-headers="visual" role="row">
                    <div class="media-file-select-toggle" role="group" aria-label="Select visible visual assets">
                        <button type="button" class="audio-select-chip" data-media-select-mode="all" data-target="visual" aria-pressed="false" title="Select all visible files" aria-label="Select all visible files">☑</button>
                        <button type="button" class="audio-select-chip" data-media-select-mode="none" data-target="visual" aria-pressed="true" title="Clear selection" aria-label="Clear selection">☐</button>
                    </div>
                    <button type="button" class="media-file-col-sort visual-pool-col-head visual-pool-col-head--title" data-pool-sort="title" data-pool-panel="visual" aria-pressed="true">Title</button>
                    <button type="button" class="media-file-col-sort visual-pool-col-head visual-pool-col-head--context" data-pool-sort="context" data-pool-panel="visual" aria-pressed="false">Catalogue</button>
                    <button type="button" class="media-file-col-sort visual-pool-col-head visual-pool-col-head--dims" data-pool-sort="dims" data-pool-panel="visual" aria-pressed="false">Dimensions</button>
                    <button type="button" class="media-file-col-sort media-file-col-sort--size visual-pool-col-head visual-pool-col-head--size" data-pool-sort="size" data-pool-panel="visual" aria-pressed="false">Size</button>
                    <span class="media-file-actions media-file-col-headers-actions" aria-hidden="true"></span>
                </div>
                <div id="filelist-visual" class="visual-pool-list visual-pool-list--grid" data-visual-layout="grid"><span class="text-muted">Loading…</span></div>
                <div class="media-panel-footer"><span id="visual-count" class="media-count"></span></div>
            </div>

            <!-- Sound effects (brand UI audio) -->
            <div class="media-panel card" id="panel-sfx" data-pool-layout="list" data-pool-thumb-size="medium" <?php echo $filesPanel !== 'sfx' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            <?php echo bandpromo_admin_files_permanent_warning_line(false); ?>
                        </span>
                    </div>
                </div>
                <div class="audio-pool-toolbar visual-pool-toolbar" data-media-list-header="sfx">
                    <div class="audio-pool-toolbar-main visual-pool-toolbar-main">
                        <label class="media-filter-label">
                            <span class="visually-hidden">Filter by brand</span>
                            <select class="media-filter-select" data-media-brand-filter aria-label="Filter by brand">
                                <option value="all">All files</option>
                                <option value="orphans">Orphans</option>
                            </select>
                        </label>
                        <label class="media-filter-label audio-pool-toolbar-search">
                            <span class="visually-hidden">Filter by title</span>
                            <input type="search" class="media-filter-input" data-media-name-filter="sfx" placeholder="Filter by title…" autocomplete="off" aria-label="Filter sound effects by title">
                        </label>
                    </div>
                    <div class="audio-pool-toolbar-actions visual-pool-toolbar-actions media-file-actions">
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('sfx')" aria-label="Upload sound effects" title="Upload sound effects"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="sfx" data-download-variant="original" disabled aria-label="Download selected sound effects" title="Download selected sound effects"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn media-bulk-delete-btn" data-bulk-delete-target="sfx" disabled aria-label="Delete selected sound effects" title="Delete selected sound effects"><span class="media-labeled-action-icon" aria-hidden="true">🗑️</span><span>Delete</span></button>
                    </div>
                </div>
                <div class="media-file-col-headers visual-pool-col-headers" data-pool-list-headers="sfx" role="row">
                    <div class="media-file-select-toggle" role="group" aria-label="Select visible sound effects">
                        <button type="button" class="audio-select-chip" data-media-select-mode="all" data-target="sfx" aria-pressed="false" title="Select all visible files" aria-label="Select all visible files">☑</button>
                        <button type="button" class="audio-select-chip" data-media-select-mode="none" data-target="sfx" aria-pressed="true" title="Clear selection" aria-label="Clear selection">☐</button>
                    </div>
                    <button type="button" class="media-file-col-sort visual-pool-col-head visual-pool-col-head--title" data-pool-sort="title" data-pool-panel="sfx" aria-pressed="true">Title</button>
                    <button type="button" class="media-file-col-sort visual-pool-col-head visual-pool-col-head--context" data-pool-sort="context" data-pool-panel="sfx" aria-pressed="false">Brand</button>
                    <button type="button" class="media-file-col-sort media-file-col-sort--size visual-pool-col-head visual-pool-col-head--size" data-pool-sort="size" data-pool-panel="sfx" aria-pressed="false">Size</button>
                    <span class="media-file-actions media-file-col-headers-actions" aria-hidden="true"></span>
                </div>
                <div id="filelist-sfx" class="visual-pool-list visual-pool-list--list" data-visual-layout="list"><span class="text-muted">Loading…</span></div>
                <div class="media-panel-footer"><span id="sfx-count" class="media-count"></span></div>
            </div>

            <!-- Brand assets (legacy special intake) -->
            <div class="media-panel card" id="panel-special" data-pool-layout="grid" data-pool-thumb-size="medium" <?php echo $filesPanel !== 'special' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <div class="media-panel-summary">
                        <span class="media-panel-intro">
                            Removing an asset only removes it from this Brand library. Delete the global file permanently from Visual or Sound effects.
                        </span>
                    </div>
                </div>
                <div class="audio-pool-toolbar visual-pool-toolbar" data-media-list-header="special">
                    <div class="audio-pool-toolbar-main visual-pool-toolbar-main">
                        <div class="visual-filter-chip-group" role="group" aria-label="Filter by media type">
                            <button type="button" class="visual-filter-chip is-active" data-pool-type-filter="all" data-pool-panel="special" aria-pressed="true">All</button>
                            <button type="button" class="visual-filter-chip" data-pool-type-filter="image" data-pool-panel="special" aria-pressed="false">Still</button>
                            <button type="button" class="visual-filter-chip" data-pool-type-filter="video" data-pool-panel="special" aria-pressed="false">Living</button>
                            <button type="button" class="visual-filter-chip" data-pool-type-filter="audio" data-pool-panel="special" aria-pressed="false">Sound effects</button>
                        </div>
                        <label class="media-filter-label">
                            <span class="visually-hidden">Filter by brand</span>
                            <select class="media-filter-select" data-media-brand-filter aria-label="Filter by brand">
                                <option value="all">All files</option>
                                <option value="orphans">Orphans</option>
                            </select>
                        </label>
                        <label class="media-filter-label audio-pool-toolbar-search">
                            <span class="visually-hidden">Filter by title</span>
                            <input type="search" class="media-filter-input" data-media-name-filter="special" placeholder="Filter by title…" autocomplete="off" aria-label="Filter brand assets by title">
                        </label>
                        <div class="visual-view-toggle" role="group" aria-label="Brand assets layout">
                            <button type="button" class="visual-view-btn is-active" data-pool-view="grid" data-pool-panel="special" aria-pressed="true" title="Grid view">Grid</button>
                            <button type="button" class="visual-view-btn" data-pool-view="list" data-pool-panel="special" aria-pressed="false" title="List view">List</button>
                        </div>
                        <div class="visual-view-toggle visual-thumb-size-toggle" role="group" aria-label="List thumbnail size">
                            <button type="button" class="visual-view-btn" data-pool-thumb-size="small" aria-pressed="false" title="Small list thumbnails (70×70)">S</button>
                            <button type="button" class="visual-view-btn is-active" data-pool-thumb-size="medium" aria-pressed="true" title="Medium list thumbnails (100×100)">M</button>
                            <button type="button" class="visual-view-btn" data-pool-thumb-size="large" aria-pressed="false" title="Large list thumbnails (125×125)">L</button>
                        </div>
                    </div>
                    <div class="audio-pool-toolbar-actions visual-pool-toolbar-actions media-file-actions">
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openUploadModal('special')" aria-label="Upload brand assets" title="Upload brand assets"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Upload</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn" onclick="openBrandLibraryPicker()" aria-label="Add existing asset to selected brand" title="Add existing asset to selected brand"><span class="media-labeled-action-icon" aria-hidden="true">＋</span><span>Add existing</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-good media-group-action-btn media-labeled-action-btn media-bulk-download-btn" data-bulk-download-target="special" data-download-variant="original" disabled aria-label="Download selected brand assets" title="Download selected brand assets"><span class="media-labeled-action-icon" aria-hidden="true">⬇</span><span>Download</span></button>
                        <button type="button" class="icon-btn media-action-btn media-action-danger media-group-action-btn media-labeled-action-btn" data-bulk-remove-target="special" disabled aria-label="Remove selected assets from this Brand library" title="Remove selected assets from this Brand library"><span class="media-labeled-action-icon" aria-hidden="true">−</span><span>Remove</span></button>
                    </div>
                </div>
                <div class="media-file-col-headers visual-pool-col-headers" data-pool-list-headers="special" role="row">
                    <div class="media-file-select-toggle" role="group" aria-label="Select visible brand assets">
                        <button type="button" class="audio-select-chip" data-media-select-mode="all" data-target="special" aria-pressed="false" title="Select all visible files" aria-label="Select all visible files">☑</button>
                        <button type="button" class="audio-select-chip" data-media-select-mode="none" data-target="special" aria-pressed="true" title="Clear selection" aria-label="Clear selection">☐</button>
                    </div>
                    <button type="button" class="media-file-col-sort visual-pool-col-head visual-pool-col-head--title" data-pool-sort="title" data-pool-panel="special" aria-pressed="true">Title</button>
                    <button type="button" class="media-file-col-sort visual-pool-col-head visual-pool-col-head--context" data-pool-sort="context" data-pool-panel="special" aria-pressed="false">Warehouse</button>
                    <button type="button" class="media-file-col-sort visual-pool-col-head visual-pool-col-head--dims" data-pool-sort="dims" data-pool-panel="special" aria-pressed="false">Dimensions</button>
                    <button type="button" class="media-file-col-sort media-file-col-sort--size visual-pool-col-head visual-pool-col-head--size" data-pool-sort="size" data-pool-panel="special" aria-pressed="false">Size</button>
                    <span class="media-file-actions media-file-col-headers-actions" aria-hidden="true"></span>
                </div>
                <input type="hidden" id="brandLibraryPickerField" value="">
                <div id="filelist-special" class="visual-pool-list visual-pool-list--grid" data-visual-layout="grid"><span class="text-muted">Loading…</span></div>
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
                        <button id="mediaDeleteConfirmBtn" class="btn btn-danger">Delete</button>
                        <button class="btn" onclick="closeDeleteModal()">Cancel</button>
                        <span id="mediaDeleteStatus" class="status-text"></span>
                    </div>
                </div>
            </div>

            <!-- Shared Visual / Brand assets drilldown -->
            <div id="poolAssetModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closePoolAssetModal()">
                <div class="modal-box visual-asset-modal-box">
                    <button type="button" class="modal-close" onclick="closePoolAssetModal()" aria-label="Close">✕</button>
                    <div class="visual-asset-modal-layout">
                        <div class="visual-asset-modal-preview" id="poolAssetPreview"></div>
                        <div class="visual-asset-modal-side">
                            <h3 id="poolAssetTitle">Asset</h3>
                            <div id="poolAssetBadges" class="visual-asset-badges"></div>
                            <form id="poolAssetDisplayForm" class="visual-asset-display-form" hidden>
                                <label>
                                    <span>Title</span>
                                    <input type="text" id="poolAssetDisplayTitle" name="title" maxlength="200" autocomplete="off">
                                </label>
                                <label>
                                    <span>Description</span>
                                    <textarea id="poolAssetDisplayDescription" name="description" rows="3" maxlength="2000"></textarea>
                                </label>
                                <label>
                                    <span>Keywords</span>
                                    <input type="text" id="poolAssetDisplayKeywords" name="keywords" maxlength="500" placeholder="Comma-separated" autocomplete="off">
                                </label>
                                <label>
                                    <span>Captured</span>
                                    <input type="text" id="poolAssetDisplayCapturedAt" name="captured_at" maxlength="10" placeholder="YYYY-MM-DD" autocomplete="off">
                                </label>
                                <p id="poolAssetDisplayStatus" class="visual-asset-display-status text-muted" hidden></p>
                                <div class="visual-asset-display-actions">
                                    <button type="submit" class="btn btn-primary" id="poolAssetDisplaySaveBtn">Save details</button>
                                </div>
                            </form>
                            <dl id="poolAssetDetails" class="visual-asset-details"></dl>
                            <div class="modal-actions visual-asset-modal-actions">
                                <button type="button" class="btn btn-primary" id="poolAssetDownloadBtn">Download</button>
                                <button type="button" class="btn btn-danger" id="poolAssetDeleteBtn">Delete</button>
                                <button type="button" class="btn" onclick="closePoolAssetModal()">Close</button>
                            </div>
                        </div>
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
                    'release'  => ['💿', 'Catalogue'],
                    'playlist' => ['🎵', 'Playlists'],
                    'gallery'  => ['🖼️', 'Galleries'],
                    'pages'    => ['📝', 'Pages'],
                    'themes'   => ['🎨', 'Branding'],
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
                    The catalogue lists your releases. Each release is a campaign umbrella: masters, branding, EPK, galleries, pages, and the playlists that package those tracks. New audio uploads create a master and usable title/artist immediately — assign tracks here without waiting for a full rebuild. Open Playlists, Galleries, and Pages from the Release editor section tabs while editing, or use the Content sub-tabs for the full editors. Shift/Ctrl-click selects multiple tracks.
                <?php elseif ($contentTab === 'playlist'): ?>
                    Playlists reuse release-owned tracks for streaming (album order, single package, tour set). They do not own masters. Prefer creating them from the Release hub. Saving a playlist prepares any missing delivery files and refreshes the player payload for that playlist — you do not need Rebuild all deliverables for a normal add-track loop. Shift/Ctrl-click selects multiple tracks.
                <?php elseif ($contentTab === 'gallery'): ?>
                    Galleries are owned by a release. Preview from the pool; edit to reorder. Shift/Ctrl-click multi-select; name and alt edit inline. No build required.
                <?php elseif ($contentTab === 'pages'): ?>
                    Campaign pages (for example Bio) are owned by a release. FAQ is required for the login info lightbox. Today, other pages appear in the player only when enabled under Content → Player (site-wide). Target: also show pages associated to the playing track’s release.
                <?php elseif ($contentTab === 'themes'): ?>
                    Branding has two layers. <strong>Base</strong> is the site default for login and shell media (logo, backgrounds, welcome sounds). A <strong>release brand</strong> (set on the Catalogue release) can override player colors and fonts when that release’s playlist is playing — tracks do not carry their own brand. Shell media still follow Base until per-release shell override ships. Duplicate <em>bandPromo Default</em> to customize (it stays locked). Saving Base writes shell paths into site config.
                <?php elseif ($contentTab === 'player'): ?>
                    Choose Still or Living for the player shell background, then arrange layout tabs. Playlist and Lyrics always stay first. Optional pages here are global (always on). Assign still/living media under Content → Branding. Shift/Ctrl-click moves multiple items together.
                <?php endif; ?>
            </div>

            <?php
            $poolReleaseFilterHtml = '<div class="player-layout-pool-head-slot player-layout-pool-filters">'
                . '<label class="media-filter-label player-layout-pool-filter-label">'
                . '<span class="visually-hidden">Filter by catalogue</span>'
                . '<select class="media-filter-select player-layout-pool-filter" data-pool-release-filter aria-label="Filter by catalogue">'
                . '<option value="all">All files</option>'
                . '<option value="orphans">Orphans</option>'
                . '</select>'
                . '</label>'
                . '</div>';
            $poolHeadSpacerHtml = '<div class="player-layout-pool-head-slot" aria-hidden="true"></div>';
            ?>

            <!-- ── RELEASE ──────────────────────────────────────────────── -->
            <?php if ($contentTab === 'release'): ?>
            <div class="card content-editor-card" id="releaseEditorCard"
                 data-initial-release="<?php echo htmlspecialchars($contentRelease, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="release-editor-card-head">
                    <h2>💿 Catalogue</h2>
                </div>

                <div class="player-layout-editor playlist-editor-layout" id="releaseEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel content-editor-left-panel">
                            <div id="releasePoolView">
                                <div class="player-layout-col-head player-layout-col-head--pool">
                                    <h4 class="player-layout-col-title">Registered releases</h4>
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
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="releaseEditorBackBtn" title="Back to catalogue">← Back</button>
                                    <h3 class="release-editor-header-title">Release editor</h3>
                                </div>
                                <div class="release-preview-tabs release-editor-section-tabs release-editor-section-tabs--header" role="tablist" aria-label="Release editor sections">
                                    <button type="button" class="release-preview-tab is-active" role="tab" aria-selected="true" data-release-editor-tab="base">Base info</button>
                                    <button type="button" class="release-preview-tab" role="tab" aria-selected="false" data-release-editor-tab="tracks">Tracks</button>
                                    <button type="button" class="release-preview-tab" role="tab" aria-selected="false" data-release-editor-tab="playlists">Playlists</button>
                                    <button type="button" class="release-preview-tab" role="tab" aria-selected="false" data-release-editor-tab="galleries">Galleries</button>
                                    <button type="button" class="release-preview-tab" role="tab" aria-selected="false" data-release-editor-tab="pages">Pages</button>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="playlist-settings-panel" id="releaseSettingsPanel">
                                        <div class="release-editor-section-panel is-active" data-release-editor-panel="base" role="tabpanel">
                                        <div class="playlist-settings-fields release-catalog-meta-fields">
                                            <label class="playlist-settings-field release-editor-form-row release-editor-form-row--title">
                                                <span class="release-editor-form-label">Title</span>
                                                <span class="release-editor-title-control">
                                                    <input type="text" class="content-editor-name-input" id="releaseSettingsTitle" maxlength="120" autocomplete="off" placeholder="Release name" aria-label="Release name">
                                                </span>
                                            </label>
                                            <label class="playlist-settings-field release-catalog-meta-field--date release-editor-form-row">
                                                <span class="release-editor-form-label">Release date</span>
                                                <?php bandpromo_admin_render_iso_date_field('release_date', '', 'releaseSettingsDate', [
                                                    'variant' => 'form',
                                                    'required' => true,
                                                    'allow_year_only' => true,
                                                ]); ?>
                                            </label>
                                            <label class="playlist-settings-field release-editor-form-row">
                                                <span class="release-editor-form-label">Press contact</span>
                                                <input type="text" id="releaseSettingsPressContact" maxlength="240" placeholder="Name &lt;email@example.com&gt;" autocomplete="off">
                                            </label>
                                            <label class="playlist-settings-field release-editor-form-row">
                                                <span class="release-editor-form-label">Branding</span>
                                                <select id="releaseSettingsBrandId" aria-label="Release brand">
                                                    <option value="">Base brand</option>
                                                </select>
                                            </label>
                                            <label class="playlist-settings-field release-editor-form-row release-editor-form-row--textarea">
                                                <span class="release-editor-form-label">Blurb</span>
                                                <span class="release-editor-field-stack">
                                                    <textarea id="releaseSettingsShortDescription" rows="4" maxlength="300" placeholder="Short release summary" autocomplete="off"></textarea>
                                                    <span class="field-note release-short-description-note"><span id="releaseSettingsShortDescriptionCount">0</span>/300 characters</span>
                                                </span>
                                            </label>
                                            <label class="playlist-settings-field release-editor-form-row release-editor-form-row--textarea">
                                                <span class="release-editor-form-label">Long description <span class="markdown-help-inline">(Markdown <?php echo bandpromo_admin_markdown_help_trigger(); ?>)</span></span>
                                                <span class="release-editor-field-stack">
                                                    <textarea id="releaseSettingsDescription" class="release-settings-description-autofit" rows="4" maxlength="4000" placeholder="Long release description" autocomplete="off"></textarea>
                                                </span>
                                            </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="player-layout-col player-layout-col--active">
                        <div class="player-layout-panel">
                            <div class="player-layout-col-head player-layout-col-head--active">
                                <h3 class="player-layout-col-title" id="releaseEditorPreviewHeading">Preview</h3>
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
                                            <h4 class="release-cover-heading" id="releasePreviewTitle">Release</h4>
                                            <p class="release-preview-date" id="releasePreviewDate"></p>
                                            <p class="release-preview-summary" id="releasePreviewSummary"></p>
                                        </div>
                                    </div>
                                    <div id="releaseBaseBrandPreview" class="release-base-brand-preview" hidden>
                                        <h5 class="release-base-brand-preview-heading">Brand preview</h5>
                                        <div id="releaseBaseBrandPreviewBody" class="release-base-brand-preview-body"></div>
                                    </div>
                                    <div id="releaseLongDescriptionPreview" class="release-long-description-preview" hidden>
                                        <h5 class="release-base-brand-preview-heading">Long description preview</h5>
                                        <div id="releaseLongDescriptionPreviewBody" class="release-long-description-preview-body"></div>
                                    </div>
                                </div>
                                <ul class="release-preview-tracks release-associated-tracks" id="releaseActiveList" aria-label="Associated tracks" hidden>
                                    <li class="player-layout-empty">No release selected.</li>
                                </ul>
                                <ol class="playlist-editor player-layout-list release-associated-tracks" id="releaseAssociationActiveList" aria-label="Associated items" hidden>
                                    <li class="player-layout-empty">No release selected.</li>
                                </ol>
                                <div id="releaseAvailableSection" class="content-pool-section" hidden>
                                    <div class="player-layout-col-head player-layout-col-head--pool content-pool-head">
                                        <h4 class="player-layout-col-title" id="releaseAvailableHeading">Available tracks</h4>
                                        <?php echo $poolHeadSpacerHtml; ?>
                                    </div>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list content-pool-list" id="releaseAvailableList" aria-label="Available tracks">
                                        <li class="player-layout-empty">Loading tracks…</li>
                                    </ol>
                                </div>
                                <div id="releaseAssociationAvailableSection" class="content-pool-section" hidden>
                                    <div class="player-layout-col-head player-layout-col-head--pool content-pool-head">
                                        <h4 class="player-layout-col-title" id="releaseAssociationAvailableHeading">Available playlists</h4>
                                        <?php echo $poolHeadSpacerHtml; ?>
                                    </div>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list content-pool-list" id="releaseAssociationAvailableList" aria-label="Available items">
                                        <li class="player-layout-empty">Loading…</li>
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

                <div class="player-layout-editor playlist-editor-layout" id="playlistEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel content-editor-left-panel">
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
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="playlistEditorBackBtn" title="Back to playlist list">← Back</button>
                                    <div class="content-editor-head-name">
                                        <input type="text" class="content-editor-name-input" id="playlistSettingsTitle" maxlength="120" autocomplete="off" placeholder="Playlist name" aria-label="Playlist name">
                                    </div>
                                    <span class="status-text playlist-settings-status content-editor-name-status" id="playlistSettingsStatus"></span>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="playlist-settings-panel" id="playlistSettingsPanel">
                                        <div class="playlist-settings-fields release-catalog-meta-fields">
                                            <label class="playlist-settings-field release-catalog-meta-field--date">
                                                <span>Publish date</span>
                                                <?php bandpromo_admin_render_iso_date_field('publish_date', '', 'playlistSettingsPublishDate', [
                                                    'variant' => 'form',
                                                    'required' => true,
                                                    'allow_year_only' => true,
                                                ]); ?>
                                            </label>
                                            <p class="hint">Playlist promotion uses this <strong>UTC calendar day</strong>; track playability still follows each release date.</p>
                                            <label class="playlist-settings-field">
                                                <span>Package type</span>
                                                <select id="playlistSettingsPackageType" aria-label="Playlist package type">
                                                    <option value="single">Single</option>
                                                    <option value="ep">EP</option>
                                                    <option value="album">Album</option>
                                                    <option value="show">Show</option>
                                                    <option value="podcast">Podcast</option>
                                                    <option value="live">Live</option>
                                                    <option value="compilation">Compilation</option>
                                                    <option value="other" selected>Other</option>
                                                </select>
                                            </label>
                                            <label class="playlist-settings-field">
                                                <span>Player track order</span>
                                                <select id="playlistSettingsPlayOrder" aria-label="Player track order">
                                                    <option value="stored">As listed (first track first)</option>
                                                    <option value="reverse">Newest first (reverse list)</option>
                                                </select>
                                            </label>
                                            <p class="hint">Shows and podcasts default to newest first so you can append episodes at the bottom of the edit list.</p>
                                            <label class="playlist-settings-field playlist-settings-field--wide playlist-settings-default-flag">
                                                <span class="playlist-settings-checkbox-row">
                                                    <input type="checkbox" id="playlistSettingsSetAsDefault">
                                                    <span>Default playlist for the player</span>
                                                </span>
                                            </label>
                                            <p class="hint">When set, the player opens this playlist first (if it is public and has tracks). Otherwise the latest publish date wins.</p>
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
                                                <?php echo bandpromo_admin_markdown_help_note('Markdown supported'); ?>
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
                                <div id="playlistAvailableSection" class="content-pool-section" hidden>
                                    <div class="player-layout-col-head player-layout-col-head--pool content-pool-head">
                                        <h4 class="player-layout-col-title">Available content</h4>
                                        <?php echo $poolReleaseFilterHtml; ?>
                                    </div>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list content-pool-list" id="playlistAvailableList" aria-label="Available tracks">
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

                <div class="player-layout-editor playlist-editor-layout" id="galleryEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel content-editor-left-panel">
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
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="galleryEditorBackBtn" title="Back to gallery list">← Back</button>
                                    <div class="content-editor-head-name">
                                        <input type="text" class="content-editor-name-input" id="gallerySettingsTitle" maxlength="120" autocomplete="off" placeholder="Gallery name" aria-label="Gallery name">
                                    </div>
                                    <span class="status-text playlist-settings-status content-editor-name-status" id="gallerySettingsStatus"></span>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <div class="player-layout-col-head player-layout-col-head--pool gallery-available-toolbar" style="height:auto;min-height:0;padding-top:0">
                                        <h4 class="player-layout-col-title">Available media</h4>
                                        <?php echo $poolReleaseFilterHtml; ?>
                                    </div>
                                    <div class="gallery-picker-toolbar">
                                        <input type="search" id="galleryAvailableSearch" class="gallery-available-search" placeholder="Search title, keywords…" aria-label="Search available media" autocomplete="off">
                                        <div class="gallery-picker-actions">
                                            <button type="button" class="btn btn-primary btn-sm" id="galleryAddSelectedBtn">Add selected</button>
                                            <button type="button" class="btn btn-sm" id="galleryRemoveSelectedBtn">Remove selected</button>
                                        </div>
                                    </div>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list gallery-pool-list" id="galleryAvailableList" aria-label="Available media">
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
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="pageEditorBackBtn" title="Back to page list">← Back</button>
                                    <div class="content-editor-head-name">
                                        <input type="text" class="content-editor-name-input" id="pageTitleInput" value="<?php echo htmlspecialchars($activeContentPage['title'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="120" placeholder="Page name" aria-label="Page name">
                                    </div>
                                </div>
                                <div class="player-layout-panel-body page-editor-view-body">
                                    <div class="page-editor-meta">
                                        <label class="page-meta-field" id="pageLabelFieldWrap"<?php echo $activePageIsLoginOnly ? ' hidden' : ''; ?>>
                                            <span>Player tab</span>
                                            <input type="text" id="pageLabelInput" value="<?php echo htmlspecialchars($activeContentPage['label'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="32">
                                        </label>
                                        <label class="page-meta-field page-meta-field--wide">
                                            <span>Short description</span>
                                            <textarea id="pageSettingsShortDescription" rows="2" maxlength="300" placeholder="One-liner for cards and summaries" autocomplete="off"></textarea>
                                            <div class="field-note release-short-description-note"><span id="pageSettingsShortDescriptionCount">0</span>/300 characters</div>
                                        </label>
                                        <label class="page-meta-field page-meta-field--wide">
                                            <span>Description</span>
                                            <textarea id="pageSettingsDescription" rows="3" maxlength="4000" placeholder="Summary for this page" autocomplete="off"></textarea>
                                        </label>
                                        <label class="page-meta-field page-meta-field--wide">
                                            <span>Share image</span>
                                            <input type="hidden" id="pageSettingsPosterAssetId" data-empty-label="No share image selected">
                                            <div class="asset-picker-row">
                                                <span id="pageSettingsPosterAssetId_label" class="asset-picker-value empty">No share image selected</span>
                                                <button type="button" class="icon-btn media-picker-open audio-master-cover-action" data-field="pageSettingsPosterAssetId" data-title="Choose share image" data-targets="illustrations,photos,special" title="Choose share image" aria-label="Choose share image">✎</button>
                                            </div>
                                            <p class="hint">Stored for when public sharing ships in v0.9. OG tags are not wired yet.</p>
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
                        <button type="button" class="btn btn-danger" id="pageDeleteConfirmBtn">Delete page</button>
                        <button type="button" class="btn" id="pageDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="pageBlockDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="pageBlockDeleteModalTitle">
                    <h3 id="pageBlockDeleteModalTitle">Delete block?</h3>
                    <p class="card-note">Delete the <strong id="pageBlockDeleteModalName"></strong> block? This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-danger" id="pageBlockDeleteConfirmBtn">Delete block</button>
                        <button type="button" class="btn" id="pageBlockDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <?php elseif ($contentTab === 'themes'): ?>
            <div class="card content-editor-card" id="themeEditorRoot"
                 data-initial-theme="<?php echo htmlspecialchars($contentTheme, ENT_QUOTES, 'UTF-8'); ?>">
                <h3>🎨 Branding</h3>

                <div class="player-layout-editor theme-editor-layout playlist-editor-layout" id="themeEditorLayout">
                    <div class="player-layout-col player-layout-col--pool">
                        <div class="player-layout-panel content-editor-left-panel">
                            <div id="themePoolView">
                                <div class="player-layout-col-head player-layout-col-head--pool">
                                    <h4 class="player-layout-col-title">Available content</h4>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body">
                                    <p id="themeRegistryStatus" class="status-text page-pool-status"></p>
                                    <ol class="playlist-editor player-layout-list player-layout-pool-list page-pool-list theme-pool-list" id="themePoolList" aria-label="Brands"></ol>
                                </div>
                            </div>

                            <div id="themeEditorView" class="page-editor-view" hidden>
                                <div class="player-layout-col-head player-layout-col-head--pool page-editor-view-head theme-editor-view-head content-editor-view-head">
                                    <button type="button" class="btn page-editor-back-btn content-editor-back-btn" id="themeEditorBackBtn" title="Back to brand list">← Back</button>
                                    <div class="theme-editor-head-name content-editor-head-name">
                                        <input type="text" class="theme-editor-name-input content-editor-name-input" id="themeSettingsTitle" maxlength="120" autocomplete="off" placeholder="Brand name" aria-label="Brand name">
                                        <span class="theme-editor-head-badges" id="themeEditorHeadBadges"></span>
                                    </div>
                                    <span class="status-text theme-editor-name-status content-editor-name-status" id="themeSettingsStatus"></span>
                                </div>
                                <div class="player-layout-panel-body page-pool-panel-body theme-editor-view-body">
                                    <div class="theme-editor-form" id="themeEditorForm">
                                        <p class="theme-editor-locked-note">Loading brand…</p>
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
                                    <button type="button" id="themeSetActiveBtn" class="btn" hidden>★ Set as base</button>
                                    <button type="button" id="themeSaveBtn" class="btn" hidden>💾 Save brand</button>
                                </div>
                            </div>
                            <div class="player-layout-panel-body theme-editor-preview-body">
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
                    <p class="card-note">You are about to permanently delete <strong id="releaseDeleteModalName"></strong>. Its tracks will leave this release and stay in your audio library. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-danger" id="releaseDeleteConfirmBtn">Delete release</button>
                        <button type="button" class="btn" id="releaseDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="playlistDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="playlistDeleteModalTitle">
                    <h3 id="playlistDeleteModalTitle">Delete playlist?</h3>
                    <p class="card-note">You are about to permanently delete <strong id="playlistDeleteModalName"></strong>. Its track order will be lost. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-danger" id="playlistDeleteConfirmBtn">Delete playlist</button>
                        <button type="button" class="btn" id="playlistDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="galleryDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="galleryDeleteModalTitle">
                    <h3 id="galleryDeleteModalTitle">Delete gallery?</h3>
                    <p class="card-note">You are about to permanently delete <strong id="galleryDeleteModalName"></strong>. Its content order will be lost. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-danger" id="galleryDeleteConfirmBtn">Delete gallery</button>
                        <button type="button" class="btn" id="galleryDeleteCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="modal-overlay" id="themeDeleteModal" style="display:none;" aria-hidden="true">
                <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="themeDeleteModalTitle">
                    <h3 id="themeDeleteModalTitle">Delete theme?</h3>
                    <p class="card-note">You are about to permanently delete <strong id="themeDeleteModalName"></strong>. Its color and typography settings will be lost. This cannot be undone.</p>
                    <div class="page-unsaved-actions">
                        <button type="button" class="btn btn-danger" id="themeDeleteConfirmBtn">Delete theme</button>
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
                    Basics is the place for your public site title, URL, description, author, and contact. Contact is suggested from author + site URL until you edit it manually. <strong>Save validates only the basics fields</strong>, then writes them back into the full config. If internal config sections are missing, use the <strong>Repair</strong> link to restore them from the config template. Use <strong>Demo catalog</strong> below to hide or restore the shipped bandPromo demo release and its campaign media (Brand assets / Sound effects stay visible).
                <?php elseif ($configTab === 'support'): ?>
                    Support is where you decide whether the public player should show a support call-to-action at all, where it should send visitors, and how visible it should be. Use a simple link button when you want the safest, most portable setup. Use the Ko-fi widget only when you intentionally want Ko-fi's hosted script and overlay behavior on your site. bandPromo does not verify payments or memberships here in v0.7; it only controls presentation.
                <?php elseif ($configTab === 'sharing'): ?>
                    Controls how your site appears when shared on Facebook, X (Twitter), and other platforms, and also holds the lightweight SEO/manifest fields used for keywords and categories. The preview cards below update live as you type. Edit the <strong>poster / share cover</strong> under <a href="?tab=content&amp;cntab=themes">Content → Branding</a> on the base brand.
                <?php endif; ?>
            </div>

            <!-- ── BASICS ──────────────────────────────────────────────────── -->
            <?php if ($tab === 'settings' && $configTab === 'basics'): ?>
            <?php
            $cfgFull = bandpromo_load_runtime_config_raw();
            $cfgSite = $cfgFull['site'] ?? [];
            $operatorPrefs = bandpromo_operator_prefs($cfgFull);
            $operatorTimeDisplay = $operatorPrefs['time_display'];
            $operatorTimezone = $operatorPrefs['timezone'];
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

            <div class="card">
                <h3>🕐 Admin time display</h3>
                <p class="card-note">
                    <?php echo htmlspecialchars(bandpromo_admin_time_policy_note()); ?>
                    Choose how timestamps appear in Analytics, Audit, and activity logs. Storage always stays UTC.
                </p>
                <div class="operator-time-options">
                    <label class="config-checkbox-row">
                        <input type="radio" name="operator_time_display" value="utc"<?php echo $operatorTimeDisplay === 'utc' ? ' checked' : ''; ?>>
                        <span>UTC (recommended for worldwide audiences)</span>
                    </label>
                    <label class="config-checkbox-row">
                        <input type="radio" name="operator_time_display" value="local"<?php echo $operatorTimeDisplay === 'local' ? ' checked' : ''; ?>>
                        <span>My local time (<code id="operatorTimezonePreview"><?php echo htmlspecialchars($operatorTimezone); ?></code>)</span>
                    </label>
                </div>
                <input type="hidden" id="cfg_operator_timezone" value="<?php echo htmlspecialchars($operatorTimezone); ?>">
                <div class="card-actions">
                    <button type="button" id="cfgOperatorTimeSaveBtn" class="btn btn-primary">💾 Save time display</button>
                    <span id="cfgOperatorTimeStatus" class="status-text"></span>
                </div>
            </div>

            <div class="card">
                <h3>🎭 Demo catalog</h3>
                <p class="card-note">
                    Hide is available after you have an operator-created release with a track and a playlist that exposes that track. When hidden, the shipped <strong>bandPromo demo</strong> release and its campaign playlists, galleries, pages, and owned Audio/Visual media are removed from the player, content editors, and media pickers. Brand assets and Sound effects stay visible. Files remain on disk and publish builds still process them. If you later delete that operator catalog, the demo is shown again automatically.
                </p>
                <label class="config-checkbox-row">
                    <input type="checkbox" id="cfgDemoCatalogVisible"<?php echo $demoCatalogVisible ? ' checked' : ''; ?>>
                    <span>Show bandPromo demo catalog</span>
                </label>
                <div class="card-actions">
                    <span id="cfgDemoCatalogStatus" class="status-text"></span>
                </div>
            </div>

            <!-- ── SUPPORT ─────────────────────────────────────────────────── -->
            <?php elseif ($tab === 'settings' && $configTab === 'support'): ?>
            <?php
            $cfgFull = bandpromo_load_runtime_config_raw();
            $cfgSupport = $cfgFull['support'] ?? [];
            $supportEnabled = !empty($cfgSupport['enabled']);
            $supportLabel = (string) ($cfgSupport['label'] ?? 'Support');
            $supportUrl = (string) ($cfgSupport['url'] ?? '');
            $supportKofiPageId = (string) ($cfgSupport['kofi_page_id'] ?? '');
            $supportButtonBackground = (string) ($cfgSupport['button_background_color'] ?? '#323842');
            $supportButtonTextColor = (string) ($cfgSupport['button_text_color'] ?? '#ffffff');
            ?>
            <div class="card">
                <h3>💛 Support link</h3>
                <p class="card-note">
                    Choose whether visitors are invited to support you from the player. bandPromo keeps the call-to-action safely inside the player rail and sends visitors to your operator-owned destination.
                </p>
                <div class="config-form-grid">
                    <div class="form-group config-form-full">
                        <label for="cfg_support_enabled" style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" id="cfg_support_enabled" <?php echo $supportEnabled ? 'checked' : ''; ?>>
                            <span>Show a public support call-to-action on the player page</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="cfg_support_label">Button text</label>
                        <input type="text" id="cfg_support_label" value="<?php echo htmlspecialchars($supportLabel); ?>" maxlength="80" placeholder="Support">
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
                        <div class="field-note">Optional fallback when no direct URL is entered; bandPromo links to your Ko-fi page.</div>
                    </div>
                    <div class="form-group">
                        <label for="cfg_support_button_background_color">Button background color</label>
                        <input type="text" id="cfg_support_button_background_color" value="<?php echo htmlspecialchars($supportButtonBackground); ?>" placeholder="#323842">
                        <div class="field-note">Choose a color that stands out without looking unrelated to the rest of your site.</div>
                    </div>
                    <div class="form-group">
                        <label for="cfg_support_button_text_color">Button text color</label>
                        <input type="text" id="cfg_support_button_text_color" value="<?php echo htmlspecialchars($supportButtonTextColor); ?>" placeholder="#ffffff">
                        <div class="field-note">Saving requires at least 4.5:1 contrast against the button background.</div>
                    </div>
                </div>
                <textarea id="cfgSupportFullSource" style="display:none"><?php echo htmlspecialchars(json_encode($cfgFull, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?></textarea>
                <div class="card-actions">
                    <button id="cfgSupportSaveBtn" class="btn btn-primary">💾 Save support settings</button>
                    <span id="cfgSupportStatus" class="status-text"></span>
                </div>
            </div>

            <!-- ── SHARING ─────────────────────────────────────────────────── -->
            <?php elseif ($tab === 'settings' && $configTab === 'sharing'): ?>
            <?php
            require_once __DIR__ . '/biblioteca/config-loader.php';
            $ogTitle   = get_config('release.identity.title', 'bandPromo');
            $ogDesc    = get_config('release.identity.description', '');
            $ogImage   = get_config('release.brand.poster', '');
            if ($ogImage === '') {
                require_once __DIR__ . '/biblioteca/brand-storage.php';
                $ogImage = bandpromo_brand_resolve_active_shell_slot(__DIR__, 'poster');
            }
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
                        <label>Share image</label>
                        <input type="hidden" id="soc_share_image" value="<?php echo htmlspecialchars($ogImage); ?>">
                        <div class="asset-picker-control" id="soc_share_image_picker">
                            <span id="soc_share_image_label" class="asset-picker-value<?php echo $ogImage === '' ? ' empty' : ''; ?>"><?php echo htmlspecialchars($ogImageLabel); ?></span>
                        </div>
                        <div class="field-note">Poster / share cover is edited under <a href="?tab=content&amp;cntab=themes">Content → Branding</a> (base brand Shell media). Saving that brand updates the image used in these previews.</div>
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
                <a href="?tab=system&amp;stab=deliverables" class="tab-link <?php echo $systemTab === 'deliverables' ? 'active' : ''; ?>">📦 Deliverables</a>
                <a href="?tab=system&amp;stab=audit" class="tab-link <?php echo $systemTab === 'audit' ? 'active' : ''; ?>">🛡️ Audit</a>
                <a href="?tab=system&amp;stab=backup" class="tab-link <?php echo $systemTab === 'backup' ? 'active' : ''; ?>">💾 Backup, export &amp; import</a>
                <a href="?tab=system&amp;stab=security" class="tab-link <?php echo $systemTab === 'security' ? 'active' : ''; ?>">🔒 Security</a>
                <?php if ($systemTab === 'deliverables'): ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-build" onclick="toggleHelp('build')" title="Show/hide help">ⓘ</button>
                <?php elseif ($systemTab === 'audit'): ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-audit" onclick="toggleHelp('audit')" title="Show/hide help">ⓘ</button>
                <?php elseif ($systemTab === 'security'): ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-security" onclick="toggleHelp('security')" title="Show/hide help">ⓘ</button>
                <?php else: ?>
                <button class="help-toggle-btn collapsed" id="helpBtn-backup-export" onclick="toggleHelp('backup-export')" title="Show/hide help">ⓘ</button>
                <?php endif; ?>
            </div>

            <?php if ($systemTab === 'deliverables'): ?>
            <div class="admin-help-box collapsed" id="help-build">
                bandPromo usually keeps listener-ready files current automatically after uploads and saves. This page shows delivery health and lets you rebuild everything when you want extra reassurance.<br><br>
                <strong>Delivery status</strong> summarizes your catalog and whether streaming files are ready.<br><br>
                Use <strong>Repair catalog</strong> when Welcome suggests registry housekeeping, then <strong>Rebuild all deliverables</strong> when you want the full pipeline refreshed.
            </div>

            <div id="publishStatusCard" class="card publish-status-card">
                <div class="build-validation-head">
                    <h3>📊 Delivery status</h3>
                    <span id="publishStatusOverall" class="badge audit-status-badge status-neutral">Checking…</span>
                </div>
                <div id="publishStatusSummary" class="publish-status-summary"></div>
            </div>

            <div id="catalog-repair" class="card catalog-repair-card">
                <div class="build-validation-head">
                    <h3>🔧 Repair catalog</h3>
                </div>
                <p class="card-note">
                    Heal registry links, legacy master names, missing visual delivery metadata, and related catalog housekeeping. Preview first; apply only when you want those repairs written. This does not publish listener-facing files by itself — use Rebuild below when delivery still needs a refresh.
                </p>
                <div class="publish-actions-toolbar">
                    <button type="button" id="contentAutofixPreviewBtn" class="btn">Preview repairs</button>
                    <button type="button" id="contentAutofixApplyBtn" class="btn btn-primary" hidden>Apply repairs</button>
                </div>
                <p id="contentAutofixStatus" class="build-log-status publish-action-status" hidden></p>
                <ul id="contentAutofixReport" class="welcome-list" hidden></ul>
            </div>

            <div id="publishActionsCard" class="card publish-actions-card">
                <div class="build-validation-head">
                    <h3>⚡ When you want reassurance</h3>
                </div>
                <div class="publish-actions-toolbar">
                    <button type="button" id="buildBtn" class="btn btn-primary">▶️ Rebuild all deliverables</button>
                    <button type="button" id="recommendedBuildBtn" class="btn" style="display:none"></button>
                </div>
            </div>

            <details id="build-log-card" class="card deliverables-log-card">
                <summary class="deliverables-log-summary">
                    <span>📋 Build log</span>
                    <span class="build-log-meta">
                        <span id="buildSpinner" class="build-log-spinner" style="display:none">⏳ Building…</span>
                        <span id="buildStatus" class="build-log-status"></span>
                    </span>
                </summary>
                <pre id="buildLog" class="build-log">No build output yet.</pre>
            </details>
            <?php elseif ($systemTab === 'audit'): ?>
            <div class="admin-help-box collapsed" id="help-audit">
                Separate admin audit trail for management actions only. Use this to trace who changed users, content, settings, files, and publish runs, without mixing those records into listener activity analytics.
            </div>

            <form method="GET" class="filter-bar filter-bar-form">
                <input type="hidden" name="tab" value="system">
                <input type="hidden" name="stab" value="audit">
                <div class="filter-bar-dates">
                    <?php bandpromo_admin_render_iso_date_field('date_start', $dateStart, 'audit-date-start'); ?>
                    <span class="filter-bar-date-sep" aria-hidden="true">&#8594;</span>
                    <?php bandpromo_admin_render_iso_date_field('date_end', $dateEnd, 'audit-date-end'); ?>
                </div>
                <label class="filter-bar-extra-label" for="audit-action-filter">Action</label>
                <select name="audit_action" id="audit-action-filter" class="filter-bar-select" onchange="this.form.submit()">
                    <option value="">All actions</option>
                    <?php foreach ($auditActions as $auditAction): ?>
                    <option value="<?php echo htmlspecialchars($auditAction); ?>" <?php echo $auditActionFilter === $auditAction ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($auditAction); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label class="filter-bar-extra-label" for="audit-user-filter">User</label>
                <select name="audit_user" id="audit-user-filter" class="filter-bar-select" onchange="this.form.submit()">
                    <option value="">All admins</option>
                    <?php foreach ($auditActors as $auditActor): ?>
                    <option value="<?php echo htmlspecialchars($auditActor); ?>" <?php echo $auditUserFilter === $auditActor ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($auditActor); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="filter-bar-meta">
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
                            <td class="text-muted nowrap"><?php echo htmlspecialchars(bandpromo_admin_format_timestamp($entry)); ?></td>
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
            <?php elseif ($systemTab === 'backup'): ?>
            <div class="admin-help-box collapsed" id="help-backup-export">
                Create full-site backups, import a site ZIP, or import a single <strong>release package</strong> (one campaign: masters, branding, playlists, galleries, pages). Large <code>media/</code> archives can take several minutes. Jobs stay in <code>backups/</code> until you download or delete them. After import, open <strong>Deliverables</strong> if you want to refresh listener-ready files. See <code>docs/PORTABILITY.md</code>.
            </div>

            <div class="card site-backup-card">
                <h3>📦 Jobs</h3>
                <p class="card-note">
                    Queued export and import jobs for this install. Leave this tab open while a job runs.
                </p>
                <div id="siteBackupJobsWrap" class="site-backup-jobs-wrap">
                    <?php if (empty($siteBackupJobs)): ?>
                    <p id="siteBackupJobsEmpty" class="empty-msg">No backup jobs yet. Create or import one below.</p>
                    <?php else: ?>
                    <div class="table-scroll">
                        <table class="site-backup-jobs-table" id="siteBackupJobsTable">
                            <thead>
                                <tr>
                                    <th>Contents</th>
                                    <th>Status</th>
                                    <th>Created (UTC)</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="siteBackupJobsBody">
                                <?php foreach ($siteBackupJobs as $backupJob): ?>
                                <?php
                                    $jobStatus = (string) ($backupJob['status'] ?? '');
                                    $jobDirection = (string) ($backupJob['direction'] ?? 'export');
                                    $statusClass = 'status-neutral';
                                    $statusLabel = 'Queued';
                                    if ($jobStatus === 'building') {
                                        $statusClass = 'status-warning';
                                        $statusLabel = $jobDirection === 'import' ? 'Importing…' : 'Building…';
                                    } elseif ($jobStatus === 'ready') {
                                        $statusClass = 'status-ok';
                                        $statusLabel = $jobDirection === 'import' ? 'Imported' : 'Ready';
                                    } elseif ($jobStatus === 'failed') {
                                        $statusClass = 'status-error';
                                        $statusLabel = 'Failed';
                                    }
                                ?>
                                <tr data-backup-id="<?php echo htmlspecialchars((string) ($backupJob['id'] ?? '')); ?>">
                                    <td><?php echo htmlspecialchars((string) ($backupJob['type_label'] ?? '')); ?></td>
                                    <td>
                                        <span class="badge audit-status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                        <?php if ($jobStatus === 'ready' && $jobDirection === 'import' && trim((string) ($backupJob['import_summary'] ?? '')) !== ''): ?>
                                        <div class="text-muted site-backup-job-note"><?php echo htmlspecialchars((string) $backupJob['import_summary']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($jobStatus === 'failed' && trim((string) ($backupJob['error'] ?? '')) !== ''): ?>
                                        <div class="text-muted site-backup-job-error"><?php echo htmlspecialchars((string) $backupJob['error']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted nowrap"><?php echo htmlspecialchars((string) ($backupJob['created_at_utc'] ?? '')); ?></td>
                                    <td class="nowrap"><?php echo htmlspecialchars((string) ($backupJob['size_label'] ?? '—')); ?></td>
                                    <td class="site-backup-job-actions">
                                        <?php if (!empty($backupJob['download_ready'])): ?>
                                        <a class="btn btn-secondary site-backup-action-btn" href="/biblioteca/download-site-backup.php?id=<?php echo urlencode((string) ($backupJob['id'] ?? '')); ?>">⬇️ Download</a>
                                        <?php endif; ?>
                                        <?php if ($jobStatus !== 'building'): ?>
                                        <button type="button" class="btn btn-danger-outline site-backup-action-btn site-backup-delete-btn" data-backup-id="<?php echo htmlspecialchars((string) ($backupJob['id'] ?? '')); ?>">🗑️ Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <p id="siteBackupJobsStatus" class="status-text"></p>
            </div>

            <div class="backup-action-grid">
            <div class="card site-backup-card backup-builder-card">
                <h3>📦 Create backup</h3>
                <p class="card-note backup-builder-note">
                    Select components. <strong>Full</strong> checks all. Archives stay in <code>backups/</code> until downloaded or deleted.
                </p>
                <?php if (empty($siteBackupStatus['zip_available'])): ?>
                <p class="status-text is-error">ZipArchive is not available on this host.</p>
                <?php else: ?>
                <div class="site-backup-component-grid" id="siteBackupComponentGrid">
                    <label class="site-backup-component-row site-backup-component-row--full">
                        <input type="checkbox" id="siteBackupComponentFull" checked>
                        <span class="site-backup-component-label">
                            <strong>Full</strong>
                            <span class="site-backup-component-hint">platform, data, media, logs</span>
                        </span>
                    </label>
                    <div class="site-backup-component-subgrid">
                        <label class="site-backup-component-row">
                            <input type="checkbox" id="siteBackupComponentPlatform" class="site-backup-component-input" data-component="platform" checked>
                            <span class="site-backup-component-label">
                                <strong>Platform</strong>
                                <span class="site-backup-component-hint"><code>web-config.json</code><?php if (!empty($siteBackupStatus['has_env'])): ?>, <code>.env</code><?php endif; ?></span>
                            </span>
                        </label>
                        <label class="site-backup-component-row">
                            <input type="checkbox" id="siteBackupComponentData" class="site-backup-component-input" data-component="data" checked>
                            <span class="site-backup-component-label">
                                <strong>Data</strong>
                                <span class="site-backup-component-hint"><code>data/</code><?php echo !empty($siteBackupStatus['has_data']) ? '' : ' (missing)'; ?></span>
                            </span>
                        </label>
                        <label class="site-backup-component-row">
                            <input type="checkbox" id="siteBackupComponentMedia" class="site-backup-component-input" data-component="media" checked>
                            <span class="site-backup-component-label">
                                <strong>Media</strong>
                                <span class="site-backup-component-hint"><code>media/</code><?php echo !empty($siteBackupStatus['has_media']) ? '' : ' (missing)'; ?></span>
                            </span>
                        </label>
                        <label class="site-backup-component-row">
                            <input type="checkbox" id="siteBackupComponentLogs" class="site-backup-component-input" data-component="logs" checked>
                            <span class="site-backup-component-label">
                                <strong>Logs</strong>
                                <span class="site-backup-component-hint"><code>log/</code><?php echo !empty($siteBackupStatus['has_log']) ? '' : ' (missing)'; ?></span>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="card-actions site-backup-actions backup-builder-actions">
                    <button type="button" class="btn" id="siteBackupCreateBtn">▶️ Create backup</button>
                </div>
                <p id="siteBackupCreateStatus" class="status-text backup-export-panel-status"></p>
                <?php endif; ?>
            </div>

            <div class="card site-backup-card backup-import-card">
                <h3>📥 Import backup</h3>
                <p class="card-note backup-builder-note">
                    Upload a bandPromo ZIP from this site or another install. Inspect, then restore or migrate.
                </p>
                <?php if (empty($siteBackupStatus['zip_available'])): ?>
                <p class="status-text is-error">ZipArchive is not available on this host.</p>
                <?php else: ?>
                <div class="site-backup-import-upload">
                    <label class="btn btn-secondary site-backup-import-file-label" for="siteBackupImportFile">
                        📂 Choose archive…
                    </label>
                    <input type="file" id="siteBackupImportFile" accept=".zip,application/zip" hidden>
                    <span id="siteBackupImportFilename" class="site-backup-import-filename text-muted"></span>
                </div>
                <div id="siteBackupImportPreview" class="site-backup-import-preview" hidden>
                    <div id="siteBackupImportPreviewMeta" class="site-backup-import-preview-meta text-muted"></div>
                    <div class="site-backup-import-mode-row">
                        <label class="site-backup-import-mode-label" for="siteBackupImportMode">Import mode</label>
                        <select id="siteBackupImportMode" class="filter-bar-select site-backup-import-mode-select">
                            <option value="restore">Restore (same install)</option>
                            <option value="migrate">Import from another site</option>
                        </select>
                    </div>
                    <div class="site-backup-component-grid" id="siteBackupImportComponentGrid">
                        <label class="site-backup-component-row site-backup-component-row--full">
                            <input type="checkbox" id="siteBackupImportComponentFull">
                            <span class="site-backup-component-label">
                                <strong>Full</strong>
                                <span class="site-backup-component-hint">all components in archive</span>
                            </span>
                        </label>
                        <div class="site-backup-component-subgrid">
                            <label class="site-backup-component-row">
                                <input type="checkbox" id="siteBackupImportComponentPlatform" class="site-backup-import-component-input" data-component="platform">
                                <span class="site-backup-component-label">
                                    <strong>Platform</strong>
                                    <span class="site-backup-component-hint"><code>web-config.json</code></span>
                                </span>
                            </label>
                            <label class="site-backup-component-row">
                                <input type="checkbox" id="siteBackupImportComponentData" class="site-backup-import-component-input" data-component="data">
                                <span class="site-backup-component-label">
                                    <strong>Data</strong>
                                    <span class="site-backup-component-hint"><code>data/</code></span>
                                </span>
                            </label>
                            <label class="site-backup-component-row">
                                <input type="checkbox" id="siteBackupImportComponentMedia" class="site-backup-import-component-input" data-component="media">
                                <span class="site-backup-component-label">
                                    <strong>Media</strong>
                                    <span class="site-backup-component-hint"><code>media/</code></span>
                                </span>
                            </label>
                            <label class="site-backup-component-row">
                                <input type="checkbox" id="siteBackupImportComponentLogs" class="site-backup-import-component-input" data-component="logs">
                                <span class="site-backup-component-label">
                                    <strong>Logs</strong>
                                    <span class="site-backup-component-hint"><code>log/</code></span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <label class="site-backup-import-repair-row">
                        <input type="checkbox" id="siteBackupImportRepairUrl">
                        <span>Update site URL to this host after import</span>
                    </label>
                    <div class="card-actions site-backup-actions backup-builder-actions">
                        <button type="button" class="btn" id="siteBackupImportBtn">📥 Import selected</button>
                    </div>
                </div>
                <p id="siteBackupImportStatus" class="status-text backup-export-panel-status"></p>
                <?php endif; ?>
            </div>
            </div>

            <div class="card site-backup-card" id="releasePackageExportCard">
                <h3>💿 Export portable release package</h3>
                <p class="card-note">
                    Queue one campaign as a <code>.prp</code> archive (masters, brand, playlists, galleries, pages, registry subset).
                    Large packages build in the background like site backups — download from the job list below when Ready.
                </p>
                <?php if (empty($siteBackupStatus['zip_available'])): ?>
                <p class="empty-msg">ZipArchive is required to export release packages on this host.</p>
                <?php else: ?>
                <div class="form-row">
                    <label for="releasePackageExportSelect">Release campaign</label>
                    <select id="releasePackageExportSelect">
                        <option value="">Loading releases…</option>
                    </select>
                </div>
                <div class="card-actions site-backup-actions backup-builder-actions">
                    <button type="button" class="btn" id="releasePackageExportBtn">📦 Queue .prp export</button>
                </div>
                <p id="releasePackageExportStatus" class="status-text backup-export-panel-status"></p>
                <?php endif; ?>
            </div>

            <div class="card site-backup-card" id="releasePackageImportCard">
                <h3>💿 Import portable release package</h3>
                <p class="card-note">
                    Import one campaign as a <code>.prp</code> (or <code>.zip</code>) file: masters, brand, playlists, galleries, pages, and registry subset.
                    IDs are kept. If the release already exists, choose how to resolve the collision.
                </p>
                <?php if (empty($siteBackupStatus['zip_available'])): ?>
                <p class="empty-msg">ZipArchive is required to import release packages on this host.</p>
                <?php else: ?>
                <div class="site-backup-import-file-row">
                    <label class="btn btn-secondary site-backup-import-file-label" for="releasePackageImportInput">
                        Choose .prp…
                    </label>
                    <input type="file" id="releasePackageImportInput" accept=".prp,.zip,application/zip" hidden>
                    <span id="releasePackageImportFilename" class="site-backup-import-filename text-muted"></span>
                </div>
                <div class="form-row" style="margin-top:0.75rem;">
                    <label for="releasePackageImportCollision">If release already exists</label>
                    <select id="releasePackageImportCollision" name="collision">
                        <option value="refuse" selected>Refuse (keep local; report conflict)</option>
                        <option value="overwrite">Overwrite local campaign</option>
                        <option value="skip">Skip import</option>
                        <option value="allocate">Import as new release id</option>
                    </select>
                </div>
                <div class="card-actions site-backup-actions backup-builder-actions">
                    <button type="button" class="btn" id="releasePackageImportBtn">📥 Import release package</button>
                </div>
                <p id="releasePackageImportStatus" class="status-text backup-export-panel-status"></p>
                <?php endif; ?>
            </div>
            <?php elseif ($systemTab === 'security'): ?>
            <div class="admin-help-box collapsed" id="help-security">
                Verifies that this install still has the managed Apache/PHP protection stubs bandPromo expects
                (<code>.htaccess</code>, <code>.user.ini</code>, and deny-all rules under <code>data/</code>, <code>log/</code>, <code>backups/</code>, and <code>media/</code>).
                <br><br>
                <strong>Check</strong> only reports. <strong>Repair</strong> recreates missing or drifted managed stubs from
                <code>biblioteca/templates/runtime/</code>. Custom edits to those managed files will be overwritten.
                Site config (<code>web-config.json</code>) is checked for presence/validity but is never overwritten here.
            </div>

            <div id="securitySanityCard" class="card security-sanity-card">
                <div class="build-validation-head">
                    <h3>🔒 Host protection</h3>
                    <span id="securitySanityOverall" class="badge audit-status-badge status-neutral">Not checked</span>
                </div>
                <p id="securitySanityMessage" class="card-note">
                    Run a security sanity check to verify managed protection files on this install.
                </p>
                <div class="publish-actions-toolbar security-sanity-actions">
                    <button type="button" id="securitySanityCheckBtn" class="btn btn-primary">🔍 Check install</button>
                    <button type="button" id="securitySanityPreviewBtn" class="btn" hidden>👀 Preview repair</button>
                    <button type="button" id="securitySanityRepairBtn" class="btn btn-amber" hidden>🩹 Repair managed stubs</button>
                </div>
                <p id="securitySanityStatus" class="build-log-status publish-action-status" hidden></p>
                <ul id="securitySanityReport" class="security-sanity-report" hidden></ul>
            </div>
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
                <button type="submit" class="btn btn-primary" style="width:100%;">Save</button>
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
        <div class="modal-box modal-wide audio-master-modal">
            <button class="modal-close" onclick="closeAudioMasterModal()">✕</button>
            <header class="audio-master-modal-header">
                <h3 id="audioMasterTitle">Track details</h3>
                <span id="audioMasterStatus" class="status-text playlist-settings-status--head visual-asset-display-status">Close to save</span>
            </header>

            <div class="audio-master-modal-body">
                <div class="audio-master-hero audio-master-cover-layout">
                    <div class="audio-master-cover-duo">
                        <div class="audio-master-cover-card">
                            <span class="audio-master-cover-card-label">Still cover</span>
                            <div class="audio-master-cover-preview-shell">
                                <div class="audio-master-cover-preview" id="audioMasterCoverPreviewShell">
                                    <div class="audio-master-cover-overlay-actions">
                                        <button type="button" class="icon-btn media-picker-open audio-master-cover-action" data-field="audioMasterFieldCoverPath" data-title="Choose track cover" data-targets="illustrations,photos,special" title="Choose cover" aria-label="Choose cover">✎</button>
                                        <button type="button" class="icon-btn audio-master-cover-action" id="audioMasterCoverClearBtn" title="Use release cover" aria-label="Use release cover">↺</button>
                                    </div>
                                    <img id="audioMasterCoverPreview" alt="Track cover preview" style="display:none;">
                                    <span id="audioMasterCoverPlaceholder" class="audio-master-cover-placeholder">No cover</span>
                                </div>
                            </div>
                        </div>
                        <div class="audio-master-cover-card">
                            <span class="audio-master-cover-card-label">Living cover</span>
                            <div class="audio-master-cover-preview-shell">
                                <div class="audio-master-cover-preview" id="audioMasterLivingCoverPreviewShell">
                                    <div class="audio-master-cover-overlay-actions">
                                        <button type="button" class="icon-btn media-picker-open audio-master-cover-action" data-field="audioMasterFieldLivingCoverPath" data-title="Choose living cover video" data-targets="video" title="Choose living cover" aria-label="Choose living cover">✎</button>
                                        <button type="button" class="icon-btn audio-master-cover-action" id="audioMasterLivingCoverClearBtn" title="Clear living cover" aria-label="Clear living cover">↺</button>
                                    </div>
                                    <video id="audioMasterLivingCoverPreview" class="audio-master-living-cover-preview" muted loop playsinline preload="metadata" style="display:none;"></video>
                                    <span id="audioMasterLivingCoverPlaceholder" class="audio-master-cover-placeholder">No living cover</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="audio-master-hero-meta release-cover-meta">
                        <p class="field-note audio-master-cover-duo-status" id="audioMasterLivingCoverStatus"></p>
                        <p class="audio-master-summary-caption release-preview-date">Master audio asset</p>
                        <div class="audio-master-listen-bar" id="audioMasterListenBar" hidden>
                            <audio id="audioMasterListenPlayer" class="audio-master-listen-player" controls preload="metadata" controlsList="nodownload" title="Listen to this track"></audio>
                        </div>
                        <div class="audio-master-summary audio-master-summary-compact" id="audioMasterSummary">
                            <div class="audio-master-stat audio-master-stat-compact audio-master-stat-release">
                                <span class="audio-master-stat-label">In release</span>
                                <strong id="audioMasterReleaseName">—</strong>
                            </div>
                            <div class="audio-master-stat audio-master-stat-compact">
                                <span class="audio-master-stat-label">Duration</span>
                                <strong id="audioMasterDuration">—</strong>
                            </div>
                            <div class="audio-master-stat audio-master-stat-compact">
                                <span class="audio-master-stat-label">Format</span>
                                <strong id="audioMasterFormat">—</strong>
                            </div>
                            <div class="audio-master-stat audio-master-stat-compact">
                                <span class="audio-master-stat-label">Bitrate</span>
                                <strong id="audioMasterBitrate">—</strong>
                            </div>
                            <div class="audio-master-stat audio-master-stat-compact">
                                <span class="audio-master-stat-label">Sample rate</span>
                                <strong id="audioMasterSampleRate">—</strong>
                            </div>
                            <div class="audio-master-stat audio-master-stat-compact">
                                <span class="audio-master-stat-label">Bit depth</span>
                                <strong id="audioMasterBitDepth">—</strong>
                            </div>
                            <div class="audio-master-stat audio-master-stat-compact">
                                <span class="audio-master-stat-label">Filesize</span>
                                <strong id="audioMasterFilesize">—</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="audioMasterFieldCoverPath" name="cover_path" data-empty-label="No new cover selected">
                <input type="hidden" id="audioMasterFieldLivingCoverPath" data-empty-label="No living cover assigned">

                <form id="audioMasterForm" class="audio-master-form">
                    <div class="audio-master-form-grid audio-master-form-grid-compact">
                        <label class="playlist-settings-field form-group-date" for="audioMasterFieldDate">
                            <span>* Release date</span>
                            <div class="date-input-shell iso-date-field">
                                <input type="text" class="iso-date-input" id="audioMasterFieldDate" name="date" inputmode="numeric" placeholder="YYYY-MM-DD" pattern="^\d{4}(-\d{2}-\d{2})?$" title="ISO date: YYYY or YYYY-MM-DD" autocomplete="off" spellcheck="false" maxlength="10" required>
                                <input type="date" class="iso-date-picker-native" tabindex="-1" aria-hidden="true">
                                <button type="button" class="iso-date-picker-btn" title="Open calendar" aria-label="Pick date">📅</button>
                            </div>
                        </label>
                        <label class="playlist-settings-field" for="audioMasterFieldGenre">
                            <span>Genre</span>
                            <input type="text" id="audioMasterFieldGenre" name="genre" autocomplete="off">
                        </label>
                        <label class="playlist-settings-field" for="audioMasterFieldBpm">
                            <span>BPM</span>
                            <input type="text" id="audioMasterFieldBpm" name="bpm" autocomplete="off" inputmode="numeric" pattern="[0-9]{0,3}" maxlength="3" placeholder="128">
                        </label>
                        <label class="playlist-settings-field" for="audioMasterFieldInitialkey">
                            <span>Key</span>
                            <input type="text" id="audioMasterFieldInitialkey" name="initialkey" autocomplete="off" maxlength="3" placeholder="8A">
                        </label>
                    </div>
                    <div class="audio-master-form-grid audio-master-form-grid-secondary">
                        <label class="playlist-settings-field" for="audioMasterFieldArtist">
                            <span>* Artist</span>
                            <input type="text" id="audioMasterFieldArtist" name="artist" autocomplete="off" required>
                        </label>
                        <label class="playlist-settings-field" for="audioMasterFieldTitle">
                            <span>* Title</span>
                            <input type="text" id="audioMasterFieldTitle" name="title" autocomplete="off" required>
                        </label>
                        <label class="playlist-settings-field" for="audioMasterFieldVersion">
                            <span>Version</span>
                            <input type="text" id="audioMasterFieldVersion" name="version" autocomplete="off" placeholder="Radio Edit">
                        </label>
                    </div>
                    <label class="playlist-settings-field playlist-settings-field--wide audio-master-description-group" for="audioMasterFieldComment">
                        <span>Track description / blurb</span>
                        <textarea id="audioMasterFieldComment" name="comment" rows="2" maxlength="300"></textarea>
                        <div class="audio-master-description-meta">
                            <?php echo bandpromo_admin_markdown_help_note('Markdown in playlist view'); ?>
                            <span class="field-note"><span id="audioMasterDescriptionCount">0</span>/300</span>
                        </div>
                    </label>
                    <div class="audio-master-text-panel">
                        <div class="audio-master-text-panel-header">
                            <div class="audio-master-text-role-toggle" role="group" aria-label="Text panel type">
                                <button type="button" class="audio-master-text-role-btn is-active" data-text-role="lyrics" id="audioMasterTextRoleLyrics" aria-pressed="true">Lyrics</button>
                                <button type="button" class="audio-master-text-role-btn" data-text-role="notes" id="audioMasterTextRoleNotes" aria-pressed="false">Notes</button>
                            </div>
                            <label class="audio-master-notes-label-wrap" id="audioMasterNotesLabelWrap" for="audioMasterFieldNotesLabel" hidden title="Shown on the player nav while this track plays. Default: Tracklist.">
                                <span>Player tab label</span>
                                <input type="text" id="audioMasterFieldNotesLabel" name="notes_label" maxlength="24" autocomplete="off" placeholder="Tracklist">
                            </label>
                        </div>
                        <textarea id="audioMasterFieldLyrics" name="lyrics" rows="10" aria-label="Lyrics"></textarea>
                        <div class="field-note audio-master-text-panel-note markdown-help-note">
                            <span class="markdown-help-note-label">Restricted Markdown</span>
                            <?php echo bandpromo_admin_markdown_help_trigger(); ?>
                            <span class="markdown-help-note-extra">Lyrics keep line breaks; Notes use paragraphs.</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-actions audio-master-modal-actions">
                <button type="button" class="btn btn-danger-outline" id="audioMasterAbortBtn" title="Close without saving">Abort</button>
                <button type="button" class="btn btn-primary" id="audioMasterDoneBtn" title="Save changes and close">Done</button>
            </div>
        </div>
    </div>

    <div id="markdownHelpModal" class="modal-overlay markdown-help-modal" style="display:none" onclick="if(event.target===this)closeMarkdownHelpModal()">
        <div class="modal-box markdown-help-modal-box" role="dialog" aria-modal="true" aria-labelledby="markdownHelpModalTitle">
            <button type="button" class="modal-close" id="markdownHelpModalClose" aria-label="Close">✕</button>
            <h3 id="markdownHelpModalTitle">Formatting with Markdown</h3>
            <p class="card-note">These long text fields use a small set of plain-text shortcuts. What you type is stored as text; the player turns it into safe formatting when shown.</p>
            <div class="markdown-help-body">
                <h4>What you can use</h4>
                <dl class="markdown-help-examples">
                    <div>
                        <dt><code>**bold**</code> or <code>*italic*</code></dt>
                        <dd>Emphasis</dd>
                    </div>
                    <div>
                        <dt><code># Heading</code> … <code>###### Small heading</code></dt>
                        <dd>Headings</dd>
                    </div>
                    <div>
                        <dt><code>- item</code> or <code>1. item</code></dt>
                        <dd>Lists</dd>
                    </div>
                    <div>
                        <dt><code>[label](https://example.com)</code></dt>
                        <dd>Links (http, https, or mailto)</dd>
                    </div>
                    <div>
                        <dt><code>`code`</code></dt>
                        <dd>Inline code</dd>
                    </div>
                    <div>
                        <dt><code>&gt; quoted line</code></dt>
                        <dd>Block quote</dd>
                    </div>
                </dl>
                <h4>Lyrics vs Notes</h4>
                <p>In the track editor, <strong>Lyrics</strong> keeps single line breaks (good for verse lines). <strong>Notes</strong> uses normal paragraphs (good for tracklists and cue sheets).</p>
                <h4>What stays plain</h4>
                <p>Short descriptions, titles, and page body blocks do not use Markdown. Page text still uses the toolbar editor.</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-primary" id="markdownHelpModalDone">Got it</button>
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
        const adminActiveTab = <?php echo json_encode($tab); ?>;
        const adminContentTab = <?php echo json_encode($contentTab); ?>;
        const adminCsrfToken = <?php echo json_encode($adminCsrfToken); ?>;
        const adminTimeDisplay = <?php echo json_encode(bandpromo_admin_time_display_mode()); ?>;
        const adminTimeAxisLabel = <?php echo json_encode(bandpromo_admin_time_axis_label()); ?>;
        const adminOperatorTimezone = <?php echo json_encode(bandpromo_admin_timezone()); ?>;
        window.bandpromoDemoCatalogVisible = <?php echo json_encode((bool) $demoCatalogVisible); ?>;
        window.bandpromoDemoReleaseId = <?php echo json_encode((string) $demoReleaseId); ?>;
        window.BANDPROMO_LOCAL_DEV = <?php echo json_encode(bandpromo_is_local_dev_host()); ?>;
        window.BANDPROMO_SITE_SHARING = <?php
            $sharePlaylistId = (string) ($contentPlaylist ?? '');
            if ($sharePlaylistId === '') {
                $sharePlaylistId = 'bandpromo-demo';
            }
            $sharePlaylistSlug = $sharePlaylistId;
            try {
                if (function_exists('bandpromo_playlist_public_slug')) {
                    $sharePlaylistSlug = bandpromo_playlist_public_slug(__DIR__, $sharePlaylistId);
                }
            } catch (Throwable $sharePlaylistError) {
                $sharePlaylistSlug = $sharePlaylistId;
            }
            echo json_encode([
                'siteName' => (string) get_config('site.name', get_config('release.identity.title', 'bandPromo')),
                'siteUrl' => (string) $siteUrl,
                'siteContact' => (string) get_config('site.email', get_config('install.site.email', '')),
                'twitter' => (string) get_config('social.twitter', get_config('install.social.twitter', '')),
                'facebook' => (string) get_config('social.facebook', get_config('install.social.facebook', '')),
                'instagram' => (string) get_config('social.instagram', get_config('install.social.instagram', '')),
                'defaultPlaylistId' => $sharePlaylistId,
                'defaultPlaylistSlug' => $sharePlaylistSlug !== '' ? $sharePlaylistSlug : $sharePlaylistId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;
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
    <?php if ($tab === 'content' && $contentTab === 'themes'): ?>
    <script src="biblioteca/theme-preview.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/theme-preview.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($tab === 'content' && $contentTab === 'pages'): ?>
    <script src="biblioteca/page-editor.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/page-editor.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($tab === 'content' && $contentTab === 'release'): ?>
    <script src="biblioteca/player-markdown.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/player-markdown.js'); ?>"></script>
    <script src="biblioteca/iso-date.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/iso-date.js'); ?>"></script>
    <script src="biblioteca/release-editor.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/release-editor.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($tab === 'content' && $contentTab === 'themes'): ?>
    <script src="biblioteca/theme-editor.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/theme-editor.js'); ?>"></script>
    <?php endif; ?>
    <?php if ($tab === 'content' && in_array($contentTab, ['pages', 'player'], true)): ?>
    <script src="biblioteca/content-admin.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/content-admin.js'); ?>"></script>
    <?php endif; ?>

    <div id="mediaPickerModal" class="modal-overlay" style="display:none">
        <div class="modal-box modal-wide media-picker-modal-box">
            <div class="media-picker-modal-header">
                <button type="button" class="modal-close" id="mediaPickerCloseBtn" aria-label="Close">✕</button>
                <h3 id="mediaPickerTitle">Choose file</h3>
                <p class="card-note" id="mediaPickerHint">Pick an uploaded asset. Storage paths stay hidden.</p>
                <div id="mediaPickerTabs" class="tabs media-picker-tabs"></div>
                <div class="media-picker-toolbar" id="mediaPickerToolbar">
                    <label class="media-filter-label" data-picker-filter="release">
                        <span class="visually-hidden">Filter by catalogue</span>
                        <select class="media-filter-select" data-media-release-filter aria-label="Filter by catalogue">
                            <option value="all">All files</option>
                            <option value="orphans">Orphans</option>
                        </select>
                    </label>
                    <label class="media-filter-label" data-picker-filter="brand" hidden>
                        <span class="visually-hidden">Filter by brand</span>
                        <select class="media-filter-select" data-media-brand-filter aria-label="Filter by brand">
                            <option value="all">All files</option>
                            <option value="orphans">Orphans</option>
                        </select>
                    </label>
                    <label class="media-filter-label media-filter-label--grow">
                        <span class="visually-hidden">Filter by title or reference</span>
                        <input type="search" class="media-filter-input" id="mediaPickerSearch" placeholder="Filter by title or reference…" autocomplete="off" aria-label="Filter by title or reference">
                    </label>
                </div>
            </div>
            <div id="mediaPickerList" class="media-file-list media-picker-list"><span class="text-muted">Choose a media type to browse files.</span></div>
            <div class="modal-actions media-picker-modal-actions">
                <button type="button" id="mediaPickerUploadBtn" class="icon-btn">Upload new file</button>
                <span id="mediaPickerStatus" class="status-text"></span>
                <button type="button" class="btn btn-primary" id="mediaPickerConfirmBtn" hidden disabled>Add selected</button>
            </div>
        </div>
    </div>

    <script src="biblioteca/iso-date.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/iso-date.js'); ?>"></script>
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
