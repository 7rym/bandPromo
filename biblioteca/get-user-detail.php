<?php
/**
 * AJAX endpoint: return user detail HTML for lightbox
 * GET params: username, date_start, date_end
 */

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';

require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/admin-helpers.php';

$username   = trim($_GET['username'] ?? '');
$dateStart  = $_GET['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
$dateEnd    = $_GET['date_end']   ?? date('Y-m-d');

if (empty($username)) {
    http_response_code(400);
    exit('Username required');
}

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) $dateStart = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd))   $dateEnd   = date('Y-m-d');

$analytics = new PlaybackAnalytics();

// Stats for this user
$allStats  = $analytics->getUsersListeningStats($dateStart, $dateEnd, 1000);
$userStats = null;
foreach ($allStats as $s) {
    if ($s['username'] === $username) { $userStats = $s; break; }
}

// Raw log for this user
$rawLog        = $analytics->getRawLog($dateStart, $dateEnd, null, $username, 100);
$logEntries    = $rawLog['entries'];
$logTotal      = $rawLog['total'];
$activityTypes = $analytics->getActivityTypes($dateStart, $dateEnd);
?>
<div class="user-detail-header">
    <h3>👤 <?php echo htmlspecialchars($username); ?></h3>
    <span class="detail-period"><?php echo htmlspecialchars($dateStart); ?> → <?php echo htmlspecialchars($dateEnd); ?></span>
</div>

<?php if ($userStats): ?>
<div class="stats-grid" style="margin-bottom: 20px;">
    <?php renderStatCard('Listening Time', PlaybackAnalytics::formatSeconds($userStats['listening_time'])); ?>
    <?php renderStatCard('Tracks Played', number_format($userStats['play_count'])); ?>
    <?php renderStatCard('Sessions', number_format($userStats['sessions'])); ?>
    <?php
        $maxDevice = getMaxDevice($userStats['devices']);
        renderStatCard('Primary Device', !empty($maxDevice) ? $maxDevice : '—');
    ?>
</div>
<?php else: ?>
<p style="color:#999; margin-bottom: 20px;">No listening data for this period.</p>
<?php endif; ?>

<div class="section">
    <h4 style="margin-bottom: 10px; color: #667eea;">Activity Log
        <?php if ($logTotal > 100): ?>
            <span style="font-size:12px; color:#999; font-weight:normal;">(showing 100 of <?php echo number_format($logTotal); ?>)</span>
        <?php else: ?>
            <span style="font-size:12px; color:#999; font-weight:normal;">(<?php echo number_format($logTotal); ?> entries)</span>
        <?php endif; ?>
    </h4>

    <?php if (empty($logEntries)): ?>
        <p style="color:#999; text-align:center; padding: 20px 0;">No log entries for this period.</p>
    <?php else: ?>
    <div style="overflow-x: auto;">
        <table style="font-size:13px;">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Activity</th>
                    <th>Track</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logEntries as $entry): ?>
                <tr>
                    <td style="white-space:nowrap; color:#666;"><?php echo htmlspecialchars($entry['timestamp'] ?? ''); ?></td>
                    <td><span class="badge activity-badge"><?php echo htmlspecialchars($entry['activity'] ?? ''); ?></span></td>
                    <td>
                        <?php
                            $logRow = bandpromo_describe_log_entry($entry);
                            echo htmlspecialchars($logRow['track_primary']);
                            if (!empty($logRow['track_secondary'])) {
                                echo ' <span style="color:#999; font-size:11px;">— ' . htmlspecialchars($logRow['track_secondary']) . '</span>';
                            }
                        ?>
                    </td>
                    <td style="color:#888; font-size:12px;">
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
</div>
