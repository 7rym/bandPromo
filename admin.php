<?php
require_once __DIR__ . '/biblioteca/https.php';
require_once __DIR__ . '/biblioteca/setup-state.php';
bandpromo_enforce_https();

session_start();

require_once 'biblioteca/auth.php';
require_once 'biblioteca/admin-audit.php';
require_once 'biblioteca/config-loader.php';
require_once 'biblioteca/csrf.php';
require_once 'biblioteca/media-library-state.php';

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
$requestHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$requestHostNoPort = preg_replace('/:\\d+$/', '', $requestHost);
if ($requestHostNoPort === 'localhost') {
    $configuredHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
    if ($configuredHost === '' || $configuredHost === '127.0.0.1' || $configuredHost === 'localhost') {
        $siteUrl = 'http://' . $requestHost;
    }
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
        <div id="buildRequiredBadge" class="build-required-badge" style="display:none;"></div>
        <div class="user-badge">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            <?php if ($currentUserRole !== ''): ?>
                <span class="role-badge <?php echo $currentUserRole === 'developer' ? 'role-developer' : ($currentUserRole === 'admin' ? 'role-admin' : 'role-user'); ?>"><?php echo htmlspecialchars(ucfirst($currentUserRole)); ?></span>
            <?php endif; ?>
            &nbsp;·&nbsp;<a href="<?php echo htmlspecialchars($siteUrl ?: '/'); ?>" rel="noopener">Open site ↗</a>
            &nbsp;·&nbsp;<a href="/admin.php?logout=1">Logout</a>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Primary Tab Navigation -->
        <div class="tabs primary-tabs">
            <?php renderTabLink('welcome',   $tab, '🌍', 'Welcome'); ?>
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
                <button class="help-toggle-btn collapsed" id="helpBtn-welcome" onclick="toggleHelp('welcome')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-welcome">
                Welcome is the operator-facing overview. Use it to remember the bigger purpose of the platform: you are not just filling out forms, you are building a durable home base for music, audience connection, and long-term operator control.
            </div>

            <div class="card">
                <h2>🌍 Welcome to bandPromo</h2>
                <p class="card-note">
                    bandPromo is built for operators who want more than another profile on someone else's platform. It gives you a professional, self-hosted home base where your presentation, your audience relationship, and your support paths stay under your control.
                </p>
            </div>

            <div class="card-grid two-up">
                <div class="card">
                    <h3>What You Are Building</h3>
                    <p>
                        You are building a place where music, identity, story, private access, release context, and supporter pathways can live together. Instead of scattering your audience across services that each control one piece of the relationship, bandPromo helps you bring those pieces back into one operator-owned experience.
                    </p>
                </div>

                <div class="card">
                    <h3>Why It Matters</h3>
                    <p>
                        Smaller artists and operators often lose both attention and control on larger platforms. bandPromo exists to push in the opposite direction: clearer presentation, closer fan connection, better reuse of your own work, and more freedom to decide how public, private, promotional, or supporter-focused each experience should be.
                    </p>
                </div>

                <div class="card">
                    <h3>How It Can Create Value</h3>
                    <p>
                        The platform helps you turn a site into a working music operation: release pages, private listening, supporter links, premium or registered access paths, promotion workflows, and future service layers that can support better campaigns, better operator tooling, and stronger direct audience relationships.
                    </p>
                </div>

                <div class="card">
                    <h3>Why Operators Can Trust It</h3>
                    <p>
                        bandPromo is designed to stay out of the riskiest roles by default. It does not aim to become your payment processor, rights authority, or legal decision-maker. Support flows stay operator-owned, and responsibilities for rights, payouts, compliance, and external provider terms stay with the operator. That keeps the platform safer, clearer, and easier to grow responsibly.
                    </p>
                </div>
            </div>

            <div class="card">
                <h3>You Are Part of a Bigger Shift</h3>
                <p>
                    bandPromo is not trying to copy the largest platforms. It is part of a larger movement toward artist-owned presence, direct audience connection, reusable promotion tools, and healthier operator control. Every install that is run well helps prove that music sites can be more independent, more durable, and more human-centered than the platform-first model.
                </p>
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
                                    $t = $entry['data']['track_title']  ?? '';
                                    $a = $entry['data']['track_artist'] ?? '';
                                    if ($t) { echo htmlspecialchars($t); if ($a) echo ' <span style="color:#999;font-size:11px;">— ' . htmlspecialchars($a) . '</span>'; }
                                    else echo '—';
                                ?>
                            </td>
                            <td class="text-muted">
                                <?php
                                    $parts = [];
                                    if (!empty($entry['data']['completion_rate'])) $parts[] = 'completion: ' . $entry['data']['completion_rate'] . '%';
                                    if (!empty($entry['data']['current_time']))    $parts[] = PlaybackAnalytics::formatSeconds($entry['data']['current_time']);
                                    elseif (!empty($entry['data']['duration']))    $parts[] = PlaybackAnalytics::formatSeconds($entry['data']['duration']);
                                    echo htmlspecialchars(implode(' · ', $parts));
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
                    Drop your songs here (FLAC, MP3, or WAV). Keep your original quality files; the system creates the web-ready versions for you.
                    <br><strong>Working copy:</strong> bandPromo also prepares a separate audio master copy after upload so future repair tools can work without touching the preserved original.
                    <br><strong>After upload:</strong> run <strong>Full Build</strong>.
                <?php elseif ($filesPanel === 'photos'): ?>
                    Drop band and promo photos here (PNG, JPG, WEBP). Use your best quality images.
                    <br><strong>After upload:</strong> run <strong>Optimize Media</strong>.
                <?php elseif ($filesPanel === 'video'): ?>
                    Drop videos here (MP4, WEBM, MOV). They are used directly from this folder.
                    <br><strong>After upload:</strong> no build needed.
                <?php elseif ($filesPanel === 'illustrations'): ?>
                    Drop artwork and illustrations here (PNG, JPG, JPEG).
                    <br><strong>After upload:</strong> run <strong>Optimize Media</strong>.
                <?php elseif ($filesPanel === 'special'): ?>
                    This is for theme assets such as share images, icons, logos, and similar install-specific design files.
                    <br><strong>After upload:</strong> usually no build needed.
                <?php endif; ?>
                <br><small>Tip: you can drag files straight into this page to open the upload window automatically.</small>
                <br><small>⚠️ Deleting a file is immediate and permanent. There is no undo.</small>
            </div>

            <!-- Audio -->
            <div class="media-panel card" id="panel-audio" <?php echo $filesPanel !== 'audio' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="audio-count" class="media-count"></span>
                    <div class="media-panel-actions">
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                        <button type="button" class="btn btn-primary" onclick="openUploadModal('audio')">+ Add files</button>
                    </div>
                </div>
                <div id="filelist-audio" class="media-file-list"><span class="text-muted">Loading…</span></div>
            </div>

            <!-- Video -->
            <div class="media-panel card" id="panel-video" <?php echo $filesPanel !== 'video' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="video-count" class="media-count"></span>
                    <div class="media-panel-actions">
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                    <button type="button" class="btn btn-primary" onclick="openUploadModal('video')">+ Add files</button>
                    </div>
                </div>
                <div id="filelist-video" class="media-file-list"><span class="text-muted">Loading…</span></div>
            </div>

            <!-- Illustrations -->
            <div class="media-panel card" id="panel-illustrations" <?php echo $filesPanel !== 'illustrations' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="illustrations-count" class="media-count"></span>
                    <div class="media-panel-actions">
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                    <button type="button" class="btn btn-primary" onclick="openUploadModal('illustrations')">+ Add files</button>
                    </div>
                </div>
                <div id="filelist-illustrations" class="media-file-list"><span class="text-muted">Loading…</span></div>
            </div>

            <!-- Photos -->
            <div class="media-panel card" id="panel-photos" <?php echo $filesPanel !== 'photos' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="photos-count" class="media-count"></span>
                    <div class="media-panel-actions">
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                    <button type="button" class="btn btn-primary" onclick="openUploadModal('photos')">+ Add files</button>
                    </div>
                </div>
                <div id="filelist-photos" class="media-file-list"><span class="text-muted">Loading…</span></div>
            </div>

            <!-- Special -->
            <div class="media-panel card" id="panel-special" <?php echo $filesPanel !== 'special' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="special-count" class="media-count"></span>
                    <div class="media-panel-actions">
                        <button type="button" class="btn bundled-demo-toggle" data-bundled-toggle aria-pressed="false" title="Show bundled demo assets">◌ Demo</button>
                    <button type="button" class="btn btn-primary" onclick="openUploadModal('special')">+ Add files</button>
                    </div>
                </div>
                <div id="filelist-special" class="media-file-list"><span class="text-muted">Loading…</span></div>
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
                    <h3>Delete file?</h3>
                    <p id="mediaDeleteName" class="delete-confirm-name"></p>
                    <p class="text-muted">This cannot be undone.</p>
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
                    Drag tracks to reorder them. This editor previews the current source audio set, even before the next full build.
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
                <button id="buildBtn" class="subtab-action">▶️ Full Build</button>
                <button id="optimizeBtn" class="subtab-action">🖼️ Optimize Media</button>
                <button id="recommendedBuildBtn" class="subtab-action" style="display:none"></button>
                <button class="help-toggle-btn collapsed" id="helpBtn-build" onclick="toggleHelp('build')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-build">
                Use <strong>Optimize Media</strong> after photo/illustration updates when you only need refreshed optimized images. Use <strong>Full Build</strong> after audio uploads, cover changes tied to tracks, or web-config edits. Jobs continue in the background while this log updates.
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
            <p class="card-note" id="audioMasterSubtitle">Edit the working metadata stored in the audio master. Originals stay untouched.</p>

            <div class="audio-master-summary" id="audioMasterSummary">
                <div class="audio-master-stat">
                    <span class="audio-master-stat-label">Format</span>
                    <strong id="audioMasterFormat">—</strong>
                </div>
                <div class="audio-master-stat">
                    <span class="audio-master-stat-label">Duration</span>
                    <strong id="audioMasterDuration">—</strong>
                </div>
                <div class="audio-master-stat">
                    <span class="audio-master-stat-label">Bitrate</span>
                    <strong id="audioMasterBitrate">—</strong>
                </div>
                <div class="audio-master-stat">
                    <span class="audio-master-stat-label">Cover</span>
                    <strong id="audioMasterCover">—</strong>
                </div>
            </div>

            <form id="audioMasterForm">
                <div class="audio-master-form-grid">
                    <div class="form-group">
                        <label for="audioMasterFieldTitle">Title</label>
                        <input type="text" id="audioMasterFieldTitle" name="title" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldArtist">Artist</label>
                        <input type="text" id="audioMasterFieldArtist" name="artist" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldAlbum">Album</label>
                        <input type="text" id="audioMasterFieldAlbum" name="album" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldDate">Year / Date</label>
                        <input type="text" id="audioMasterFieldDate" name="date" autocomplete="off" placeholder="2026 or 2026-05-15">
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldTracknumber">Track no.</label>
                        <input type="text" id="audioMasterFieldTracknumber" name="tracknumber" autocomplete="off" placeholder="1">
                    </div>
                    <div class="form-group">
                        <label for="audioMasterFieldGenre">Genre</label>
                        <input type="text" id="audioMasterFieldGenre" name="genre" autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <label for="audioMasterFieldComment">Comment / Description</label>
                    <textarea id="audioMasterFieldComment" name="comment" rows="5"></textarea>
                </div>
                <div class="form-group">
                    <label for="audioMasterFieldLyrics">Lyrics</label>
                    <textarea id="audioMasterFieldLyrics" name="lyrics" rows="10"></textarea>
                </div>
            </form>

            <div class="modal-actions">
                <button type="button" id="audioMasterSaveBtn" class="btn btn-primary">Save metadata</button>
                <span id="audioMasterStatus" class="status-text"></span>
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
