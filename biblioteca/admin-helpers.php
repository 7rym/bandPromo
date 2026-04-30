<?php
/**
 * Admin Panel Helper Functions
 * Provides reusable components for admin.php
 * 
 * SECURITY: This file should not be accessed directly
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('Access Denied');
}

// Verify session is active and user is authenticated
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    die('Unauthorized');
}

/**
 * Render filter bar with date inputs and preset buttons
 *
 * @param string $tabName   Primary tab identifier
 * @param string $dateStart Start date (YYYY-MM-DD)
 * @param string $dateEnd   End date (YYYY-MM-DD)
 * @param string $atab      Optional analytics sub-tab identifier
 */
function renderFilterBar($tabName, $dateStart, $dateEnd, $atab = '') {
    ?>
    <div class="filter-bar">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tabName); ?>">
            <?php if (!empty($atab)): ?>
                <input type="hidden" name="atab" value="<?php echo htmlspecialchars($atab); ?>">
            <?php endif; ?>
            <label>Start</label>
            <input type="date" name="date_start" value="<?php echo htmlspecialchars($dateStart); ?>">
            <label>End</label>
            <input type="date" name="date_end" value="<?php echo htmlspecialchars($dateEnd); ?>">
            <div class="filter-preset-btns">
                <button type="button" class="preset-btn" data-range="day">Day</button>
                <button type="button" class="preset-btn" data-range="week">Week</button>
                <button type="button" class="preset-btn" data-range="month">Month</button>
                <button type="button" class="preset-btn" data-range="all">All Time</button>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Render an analytics sub-tab link (preserves date range)
 *
 * @param string $atab      Sub-tab identifier
 * @param string $current   Current active sub-tab
 * @param string $emoji     Emoji prefix
 * @param string $label     Display label
 * @param string $tab       Primary tab (always 'analytics')
 * @param string $dateStart Current date start
 * @param string $dateEnd   Current date end
 */
function renderSubTabLink($atab, $current, $emoji, $label, $tab, $dateStart, $dateEnd) {
    $active = $atab === $current ? 'active' : '';
    $url = '?tab=' . urlencode($tab)
         . '&atab=' . urlencode($atab)
         . '&date_start=' . urlencode($dateStart)
         . '&date_end=' . urlencode($dateEnd);
    ?>
    <a href="<?php echo $url; ?>" class="tab-link <?php echo $active; ?>">
        <?php echo htmlspecialchars($emoji . ' ' . $label); ?>
    </a>
    <?php
}

/**
 * Render a single stat card
 * 
 * @param string $title Card title
 * @param mixed $value The value to display
 * @param string $unit Optional unit text below value
 */
function renderStatCard($title, $value, $unit = '') {
    ?>
    <div class="stat-card">
        <h3><?php echo htmlspecialchars($title); ?></h3>
        <div class="value"><?php echo htmlspecialchars((string)$value); ?></div>
        <?php if (!empty($unit)): ?>
            <div class="unit"><?php echo htmlspecialchars($unit); ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render stat cards grid from array
 * 
 * @param array $stats Array of [title, value, unit] tuples
 */
function renderStatsGrid($stats) {
    ?>
    <div class="stats-grid">
        <?php foreach ($stats as $stat): ?>
            <div class="stat-card">
                <h3><?php echo htmlspecialchars($stat['title']); ?></h3>
                <div class="value"><?php echo htmlspecialchars((string)$stat['value']); ?></div>
                <?php if (!empty($stat['unit'])): ?>
                    <div class="unit"><?php echo htmlspecialchars($stat['unit']); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render empty state message in table row
 * 
 * @param int $colspan Number of columns to span
 * @param string $message Message to display
 */
function renderEmptyState($colspan, $message) {
    ?>
    <tr>
        <td colspan="<?php echo (int)$colspan; ?>" style="text-align: center; padding: 20px; color: #999;">
            <?php echo htmlspecialchars($message); ?>
        </td>
    </tr>
    <?php
}

/**
 * Render table header row
 * 
 * @param array $headers Array of column headers
 */
function renderTableHeader($headers) {
    ?>
    <thead>
        <tr>
            <?php foreach ($headers as $header): ?>
                <th><?php echo htmlspecialchars($header); ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <?php
}

/**
 * Get badge HTML for device type
 * 
 * @param string $device Device name
 * @return string HTML badge
 */
function getDeviceBadge($device) {
    return '<span class="badge device">' . htmlspecialchars($device) . '</span>';
}

/**
 * Format percentage with max device quality calculation
 * 
 * @param int $original Original quality count
 * @param int $lq Low quality count
 * @return string Formatted percentage or dash
 */
function formatQualityPercentage($original, $lq) {
    $total = $original + $lq;
    return $total > 0 ? round(($original / $total) * 100, 1) . '%' : '-';
}

/**
 * Get max device from devices array
 * 
 * @param array $devices Device counts keyed by device name
 * @return string Device name or empty string
 */
function getMaxDevice($devices) {
    $maxDevice = '';
    $maxCount = 0;
    foreach ($devices as $device => $count) {
        if ($count > $maxCount) {
            $maxCount = $count;
            $maxDevice = $device;
        }
    }
    return $maxDevice;
}

/**
 * Format and display metric with commas
 * 
 * @param int $value Number to format
 * @return string Formatted number
 */
function formatMetric($value) {
    return number_format($value);
}

/**
 * Render tab link with active state
 * 
 * @param string $tabId Tab identifier
 * @param string $currentTab Current active tab
 * @param string $emoji Emoji to use
 * @param string $label Tab label
 */
function renderTabLink($tabId, $currentTab, $emoji, $label) {
    $active = $tabId === $currentTab ? 'active' : '';
    ?>
    <a href="?tab=<?php echo htmlspecialchars($tabId); ?>" class="tab-link <?php echo $active; ?>">
        <?php echo htmlspecialchars($emoji . ' ' . $label); ?>
    </a>
    <?php
}

/**
 * Render section title
 * 
 * @param string $title Section title
 */
function renderSectionTitle($title) {
    ?>
    <div class="section">
        <h2><?php echo htmlspecialchars($title); ?></h2>
    <?php
}

/**
 * Close section div
 */
function endSection() {
    ?>
    </div>
    <?php
}
