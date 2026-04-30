<?php
require_once __DIR__ . '/biblioteca/https.php';
require_once __DIR__ . '/biblioteca/setup-state.php';
bandpromo_enforce_https();

session_start();

require_once 'biblioteca/auth.php';

// Redirect to setup wizard if setup hasn't been completed
if (!bandpromo_is_setup_complete()) {
    header('Location: /setup.php');
    exit;
}
require_once 'biblioteca/analytics.php';

$appVersion = trim(@file_get_contents(__DIR__ . '/VERSION') ?: 'dev');
$_siteCfg  = json_decode(@file_get_contents(__DIR__ . '/web-config.json') ?: '{}', true);
$siteName  = $_siteCfg['site']['name'] ?? 'Admin';
$siteUrl   = rtrim($_siteCfg['site']['url'] ?? '', '/');
$requestHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
$requestHostNoPort = preg_replace('/:\\d+$/', '', $requestHost);
if ($requestHostNoPort === 'localhost') {
    $configuredHost = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
    if ($configuredHost === '' || $configuredHost === '127.0.0.1' || $configuredHost === 'localhost') {
        $siteUrl = 'http://' . $requestHost;
    }
}
unset($_siteCfg);

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
    
    if (empty($username) || empty($password)) {
        $login_error = 'Please enter both username and password.';
    } else {
        try {
            if (authenticate($username, $password)) {
                if (!isAdminUser($username)) {
                    $login_error = 'Access denied. Admin privileges required.';
                } else {
                    $_SESSION['authenticated'] = true;
                    $_SESSION['username'] = htmlspecialchars($username);
                    header('Location: /admin.php');
                    exit;
                }
            } else {
                $login_error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $login_error = 'Login error: ' . $e->getMessage();
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
                $message = "User '$newUsername' added successfully.";
            } catch (Exception $e) {
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
                $message = "Password for '$editUsername' changed successfully.";
            } catch (Exception $e) {
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
                $message = "User '$delUsername' deleted successfully.";
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }

    elseif ($action === 'set_role') {
        $roleUsername = trim($_POST['role_username'] ?? '');
        $newRole      = $_POST['new_role'] ?? '';
        if (!in_array($newRole, ['admin', 'user'])) {
            $error = 'Invalid role.';
        } elseif ($roleUsername === $_SESSION['username'] && $newRole !== 'admin') {
            $error = 'You cannot remove your own admin role.';
        } else {
            try {
                setUserRole($roleUsername, $newRole);
                $message = "Role for '$roleUsername' set to '$newRole'.";
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$users = getAllUsers();

// Load helper functions
require_once 'biblioteca/admin-helpers.php';

// Primary tab
$tab = $_GET['tab'] ?? 'analytics';
if (!in_array($tab, ['analytics', 'users', 'files', 'content', 'config', 'build'])) {
    $tab = 'analytics';
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

// Content sub-tab
$contentTab = $_GET['cntab'] ?? 'playlist';
if (!in_array($contentTab, ['playlist', 'gallery', 'bio'])) {
    $contentTab = 'playlist';
}

// Config sub-tab
$configTab = $_GET['ctab'] ?? 'basics';
if (!in_array($configTab, ['basics', 'sharing'])) {
    $configTab = 'basics';
}

// Date range
$dateStart = $_GET['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
$dateEnd   = $_GET['date_end']   ?? date('Y-m-d');

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
    <script src="biblioteca/lightbox.js?v=<?php echo filemtime(__DIR__ . '/biblioteca/lightbox.js'); ?>"></script>
</head>
<body>
    <div class="container">
        <h1>🔐 Admin Panel</h1>
        <p class="app-version">using bandPromo <?php echo htmlspecialchars($appVersion); ?></p>
        <div id="buildRequiredBadge" class="build-required-badge" style="display:none;"></div>
        <div class="user-badge">Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
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
            <?php renderTabLink('analytics', $tab, '📊', 'Analytics'); ?>
            <?php renderTabLink('users',     $tab, '👥', 'Users'); ?>
            <?php renderTabLink('files',     $tab, '📁', 'Files'); ?>
            <?php renderTabLink('content',   $tab, '📄', 'Content'); ?>
            <?php renderTabLink('config',    $tab, '⚙️', 'Config'); ?>
            <?php renderTabLink('build',     $tab, '🔨', 'Build'); ?>
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
            <div class="stats-grid">
                <?php renderStatCard('Total Plays',    number_format($platformStats['total_plays'])); ?>
                <?php renderStatCard('Listening Time', PlaybackAnalytics::formatHours($platformStats['total_listening_time']), 'hours'); ?>
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
                <?php renderStatCard('Original Listen Time', PlaybackAnalytics::formatHours($qualityStats['original_listening_time']), 'hours'); ?>
                <?php renderStatCard('Optimized Listen Time', PlaybackAnalytics::formatHours($qualityStats['lq_listening_time']), 'hours'); ?>
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
                Admin accounts only — these are the people who can log into this panel. <strong>Adding a user</strong> gives them full admin access immediately. <strong>Deleting a user</strong> is permanent and cannot be undone — they will be logged out on their next request.
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
                            <span class="role-badge <?php echo $urole === 'admin' ? 'role-admin' : 'role-user'; ?>">
                                <?php echo $urole === 'admin' ? '🛡️ Admin' : '👤 User'; ?>
                            </span>
                        </span>
                        <span class="user-actions">
                            <?php if ($uname !== $_SESSION['username']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="role_username" value="<?php echo htmlspecialchars($uname); ?>">
                                <input type="hidden" name="new_role" value="<?php echo $urole === 'admin' ? 'user' : 'admin'; ?>">
                                <button type="submit" class="icon-btn" title="Toggle role">
                                    <?php echo $urole === 'admin' ? '⬇️' : '⬆️'; ?>
                                </button>
                            </form>
                            <?php endif; ?>
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
                    'special'       => ['✨', 'System'],
                ];
                foreach ($filePanels as $fp => [$emoji, $label]):
                    $active = $fp === $filesPanel ? 'active' : '';
                    $url = '?tab=files&fpanel=' . urlencode($fp);
                ?>
                <a href="<?php echo $url; ?>" class="tab-link <?php echo $active; ?>">
                    <?php echo htmlspecialchars($emoji . ' ' . $label); ?>
                </a>
                <?php endforeach; ?>
                <a href="#" class="tab-link btn btn-primary" style="margin-left:auto" onclick="event.preventDefault();openUploadModal('<?php echo htmlspecialchars($filesPanel); ?>')">+ Add files</a>
                <button class="help-toggle-btn collapsed" id="helpBtn-files" onclick="toggleHelp('files')" title="Show/hide help">ⓘ</button>
            </div>
            <div class="admin-help-box collapsed" id="help-files">
                <?php if ($filesPanel === 'audio'): ?>
                    Drop your songs here (FLAC or MP3). Keep your original quality files; the system creates the web-ready versions for you.
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
                    This is for system assets (share image, icons, and similar files).
                    <br><strong>After upload:</strong> usually no build needed.
                <?php endif; ?>
                <br><small>Tip: you can drag files straight into this page to open the upload window automatically.</small>
                <br><small>⚠️ Deleting a file is immediate and permanent. There is no undo.</small>
            </div>

            <!-- Audio -->
            <div class="media-panel card" id="panel-audio" <?php echo $filesPanel !== 'audio' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="audio-count" class="media-count"></span>
                </div>
                <div id="filelist-audio" class="media-file-list"><span class="text-muted">Loading…</span></div>
            </div>

            <!-- Video -->
            <div class="media-panel card" id="panel-video" <?php echo $filesPanel !== 'video' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="video-count" class="media-count"></span>
                </div>
                <div id="filelist-video" class="media-file-list"><span class="text-muted">Loading…</span></div>
            </div>

            <!-- Illustrations -->
            <div class="media-panel card" id="panel-illustrations" <?php echo $filesPanel !== 'illustrations' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="illustrations-count" class="media-count"></span>
                </div>
                <div id="filelist-illustrations" class="media-file-list"><span class="text-muted">Loading…</span></div>
            </div>

            <!-- Photos -->
            <div class="media-panel card" id="panel-photos" <?php echo $filesPanel !== 'photos' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="photos-count" class="media-count"></span>
                </div>
                <div id="filelist-photos" class="media-file-list"><span class="text-muted">Loading…</span></div>
            </div>

            <!-- Special -->
            <div class="media-panel card" id="panel-special" <?php echo $filesPanel !== 'special' ? 'style="display:none"' : ''; ?>>
                <div class="media-panel-header">
                    <span id="special-count" class="media-count"></span>
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
                    'bio'      => ['📝', 'Bio'],
                ];
                foreach ($cntTabs as $ct => [$emoji, $label]):
                    $active = $ct === $contentTab ? 'active' : '';
                    $url = '?tab=content&cntab=' . urlencode($ct);
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
                <?php elseif ($contentTab === 'bio'): ?>
                    The band bio displayed on your site. Standard HTML is supported — use <code>&lt;p&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;a&gt;</code> and similar tags. Changes are applied immediately after saving — no build required.
                <?php endif; ?>
            </div>

            <!-- ── PLAYLIST ─────────────────────────────────────────────── -->
            <?php if ($contentTab === 'playlist'): ?>
            <?php
                $pl_file = __DIR__ . '/play/playlist.json';
                $pl_error = null;
                $pl_tracks = [];
                if (!file_exists($pl_file)) {
                    $pl_error = 'No playlist found. Run a build first to generate it from your audio files.';
                } else {
                    $pl_raw = file_get_contents($pl_file);
                    $pl_tracks = json_decode($pl_raw ?: '[]', true);
                    if (!is_array($pl_tracks)) {
                        $pl_error = 'play/playlist.json could not be parsed.';
                        $pl_tracks = [];
                    }
                }
            ?>
            <div class="card">
                <h3>🎵 Playlist Order</h3>
                <p class="card-note">
                    Drag tracks to reorder them. Changes take effect immediately — no build required.
                    The new order is also saved so future builds will preserve it.
                </p>
                <?php if ($pl_error): ?>
                    <p class="hint" style="color:#f87171;"><?php echo htmlspecialchars($pl_error); ?></p>
                <?php else: ?>
                <ol class="playlist-editor" id="playlistEditor">
                    <?php foreach ($pl_tracks as $i => $track): ?>
                    <li class="playlist-editor-row" draggable="true" data-file="<?php echo htmlspecialchars($track['file'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="playlist-drag-handle" title="Drag to reorder">⠿</span>
                        <span class="playlist-track-num"><?php echo $i + 1; ?></span>
                        <span class="playlist-track-info">
                            <strong><?php echo htmlspecialchars($track['title'] ?? $track['file'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="playlist-track-meta"><?php echo htmlspecialchars($track['artist'] ?? '', ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($track['album'])): ?> &mdash; <?php echo htmlspecialchars($track['album'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></span>
                        </span>
                        <span class="playlist-track-duration"><?php
                            $dur = (int)($track['duration'] ?? 0);
                            echo $dur > 0 ? sprintf('%d:%02d', intdiv($dur, 60), $dur % 60) : '';
                        ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <div class="card-actions">
                    <button id="playlistSaveBtn" class="btn btn-primary">💾 Save order</button>
                    <span id="playlistStatus" class="status-text"></span>
                </div>
                <?php endif; ?>
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

            <!-- ── BIO ─────────────────────────────────────────────────────── -->
            <?php elseif ($contentTab === 'bio'): ?>
            <div class="card">
                <h3>📝 Band Bio</h3>
                <p class="card-note">
                    Edit the HTML content of the band bio.
                    Use standard HTML tags. Changes apply immediately.
                </p>
                <textarea id="bioEditor" class="code-editor" spellcheck="false" style="height:520px"><?php
                $bio_file = __DIR__ . '/data/bio.html';
                if (!file_exists($bio_file)) {
                    http_response_code(500);
                    echo htmlspecialchars('Missing required runtime file: data/bio.html. Run setup to seed templates.');
                } else {
                    echo htmlspecialchars(file_get_contents($bio_file) ?: '');
                }
                ?></textarea>
                <div class="card-actions">
                    <button id="bioSaveBtn" class="btn btn-primary">💾 Save bio</button>
                    <span id="bioStatus" class="status-text"></span>
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
                    The main site configuration in JSON format. <strong>Save validates the JSON</strong> — a missing comma or bracket will be caught before anything is written. Most changes need a <a href="?tab=build">Build</a> to take effect. If sections are missing, use the <strong>Repair</strong> link to restore them from the config template.
                <?php elseif ($configTab === 'sharing'): ?>
                    Controls how your site appears when shared on Facebook, X (Twitter), and other platforms. The preview cards below update live as you type. Make sure the <strong>share image path</strong> points to an existing file in the System panel.
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
                ⚠️ <strong>Incomplete config:</strong> missing sections:
                <code><?= htmlspecialchars(implode(', ', $missingSections)) ?></code>.
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
                file_put_contents($cfgCurrentPath, json_encode($cfgRepaired, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                header('Location: ?tab=config&ctab=basics'); exit;
            }
            ?>
            <div class="card">
                <h3>⚙️ Site Configuration</h3>
                <p class="card-note">
                    Edit <code>web-config.json</code> directly. Changes take effect after the next build (for manifest/player) or immediately (for sharing metadata).
                </p>
                <textarea id="cfgEditor" class="code-editor" spellcheck="false" style="height:480px"><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/web-config.json') ?: '{}'); ?></textarea>
                <div class="card-actions">
                    <button id="cfgSaveBtn" class="btn btn-primary">💾 Save config</button>
                    <span id="cfgStatus" class="status-text"></span>
                </div>
            </div>

            <!-- ── SHARING ─────────────────────────────────────────────────── -->
            <?php elseif ($configTab === 'sharing'): ?>
            <?php
            $cfg = json_decode(file_get_contents(__DIR__ . '/web-config.json') ?: '{}', true) ?? [];
            $ogTitle   = $cfg['site']['name']          ?? 'bandPromo';
            $ogDesc    = $cfg['site']['description']   ?? '';
            $ogImage   = $cfg['social']['share_image'] ?? '/media/special/bandPromo_share.png';
            $ogUrl     = $cfg['site']['url']           ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $twitter   = $cfg['social']['twitter']     ?? '';
            $facebook  = $cfg['social']['facebook']    ?? '';
            $instagram = $cfg['social']['instagram']   ?? '';
            $ogDomain  = parse_url($ogUrl, PHP_URL_HOST) ?: $ogUrl;
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
                        <label for="soc_share_image">Share image path</label>
                        <input type="text" id="soc_share_image" value="<?php echo htmlspecialchars($ogImage); ?>">
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

    <div id="adminToastHost" class="admin-toast-host" aria-live="polite" aria-atomic="true"></div>

    <script>
        const hourlyDistributionData = <?php echo json_encode($platformStats['hourly_distribution'] ?? []); ?>;
        const adminDateStart = <?php echo json_encode($dateStart); ?>;
        const adminDateEnd   = <?php echo json_encode($dateEnd); ?>;
        const adminActivePanel = <?php echo json_encode($filesPanel); ?>;
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
