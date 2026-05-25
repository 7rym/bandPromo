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

function bandpromo_format_dimension_label($label, $width, $height) {
    $width = is_numeric($width) ? (int) $width : 0;
    $height = is_numeric($height) ? (int) $height : 0;
    if ($width <= 0 || $height <= 0) {
        return '';
    }

    return $label . ': ' . $width . ' x ' . $height;
}

function bandpromo_extract_log_environment(array $entry): array {
    $data = $entry['data'] ?? null;
    if (!is_array($data)) {
        return [];
    }

    $environment = $data['environment'] ?? null;
    return is_array($environment) ? $environment : [];
}

function bandpromo_describe_log_entry(array $entry): array {
    $data = $entry['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    $activity = (string) ($entry['activity'] ?? '');
    $trackPrimary = (string) ($data['track_title'] ?? '');
    $trackSecondary = (string) ($data['track_artist'] ?? '');
    $detailParts = [];

    if (!empty($data['completion_rate'])) {
        $detailParts[] = 'completion: ' . $data['completion_rate'] . '%';
    }
    if (!empty($data['current_time'])) {
        $detailParts[] = PlaybackAnalytics::formatSeconds($data['current_time']);
    } elseif (!empty($data['duration'])) {
        $detailParts[] = PlaybackAnalytics::formatSeconds($data['duration']);
    }

    if (in_array($activity, ['login_environment', 'environment_snapshot', 'environment_changed'], true)) {
        $environment = bandpromo_extract_log_environment($entry);
        $platform = trim((string) ($environment['platform'] ?? ''));
        $orientation = trim((string) ($environment['orientation_type'] ?? ''));
        $displayMode = trim((string) ($environment['display_mode'] ?? ''));
        $viewport = bandpromo_format_dimension_label('viewport', $environment['viewport_width'] ?? null, $environment['viewport_height'] ?? null);
        $screen = bandpromo_format_dimension_label('screen', $environment['screen_width'] ?? null, $environment['screen_height'] ?? null);
        $visualViewport = bandpromo_format_dimension_label('visual', $environment['visual_viewport_width'] ?? null, $environment['visual_viewport_height'] ?? null);
        $visualScale = $environment['visual_viewport_scale'] ?? null;
        $devicePixelRatio = $environment['device_pixel_ratio'] ?? null;
        $touchPoints = $environment['touch_points'] ?? null;
        $connectionSpeed = $environment['connection_speed_mbps'] ?? null;

        $trackPrimary = $platform !== '' ? $platform : ($viewport !== '' ? $viewport : 'Environment');
        $trackSecondary = $orientation !== '' ? $orientation : $displayMode;

        if ($viewport !== '') {
            $detailParts[] = $viewport;
        }
        if ($screen !== '') {
            $detailParts[] = $screen;
        }
        if ($visualViewport !== '') {
            $detailParts[] = $visualViewport . (is_numeric($visualScale) ? ' @ ' . number_format((float) $visualScale, 2) : '');
        }
        if ($displayMode !== '') {
            $detailParts[] = 'display: ' . $displayMode;
        }
        if (is_numeric($devicePixelRatio)) {
            $detailParts[] = 'dpr: ' . rtrim(rtrim(number_format((float) $devicePixelRatio, 2, '.', ''), '0'), '.');
        }
        if (is_numeric($touchPoints)) {
            $detailParts[] = 'touch: ' . (int) $touchPoints;
        }
        if (array_key_exists('fullscreen', $environment)) {
            $detailParts[] = !empty($environment['fullscreen']) ? 'fullscreen' : 'windowed';
        }
        if (array_key_exists('standalone', $environment) && !empty($environment['standalone'])) {
            $detailParts[] = 'standalone';
        }
        if (array_key_exists('online', $environment)) {
            $detailParts[] = !empty($environment['online']) ? 'online' : 'offline';
        }
        if (is_numeric($connectionSpeed) && (float) $connectionSpeed > 0) {
            $detailParts[] = 'speed: ' . number_format((float) $connectionSpeed, 2) . ' Mbps';
        }
    }

    return [
        'track_primary' => $trackPrimary !== '' ? $trackPrimary : '—',
        'track_secondary' => $trackSecondary,
        'detail' => implode(' · ', array_values(array_filter($detailParts, static function ($part) {
            return is_string($part) && trim($part) !== '';
        }))),
    ];
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

function bandpromo_docs_root_dir(): string {
    return dirname(__DIR__);
}

function bandpromo_docs_preferred_order(): array {
    return [
        'README.md',
        'docs/FEATURES.md',
        'docs/OPERATOR-RESPONSIBILITY.md',
        'docs/SUPPORT.md',
        'docs/MEDIA-HANDLING.md',
        'docs/CHANGELOG.md',
        'docs/THIRD-PARTY-NOTICES.md',
        'docs/TRADEMARKS.md',
        'docs/ROADMAP.md',
        'docs/TODO.md',
        'docs/SECURITY-AUDIT.md',
        'docs/README_LICENSE_NOTICE.md',
        'docs/AGENTS.md',
    ];
}

function bandpromo_docs_audience_map(): array {
    return [
        'README.md' => 'shared',
        'docs/FEATURES.md' => 'operator',
        'docs/OPERATOR-RESPONSIBILITY.md' => 'operator',
        'docs/SUPPORT.md' => 'operator',
        'docs/MEDIA-HANDLING.md' => 'operator',
        'docs/CHANGELOG.md' => 'shared',
        'docs/THIRD-PARTY-NOTICES.md' => 'shared',
        'docs/TRADEMARKS.md' => 'operator',
        'docs/ROADMAP.md' => 'developer',
        'docs/TODO.md' => 'developer',
        'docs/SECURITY-AUDIT.md' => 'developer',
        'docs/README_LICENSE_NOTICE.md' => 'developer',
        'docs/AGENTS.md' => 'developer',
    ];
}

function bandpromo_docs_role_scope(string $role, string $requestedScope = ''): string {
    $role = strtolower(trim($role));
    $requestedScope = strtolower(trim($requestedScope));

    if ($role === 'developer') {
        return in_array($requestedScope, ['developer', 'operator', 'all'], true) ? $requestedScope : 'developer';
    }

    return 'operator';
}

function bandpromo_docs_entry_allowed_for_scope(array $entry, string $scope): bool {
    $audience = $entry['audience'] ?? 'operator';

    if ($scope === 'all') {
        return true;
    }

    if ($scope === 'developer') {
        return in_array($audience, ['developer', 'shared'], true);
    }

    return in_array($audience, ['operator', 'shared'], true);
}

function bandpromo_docs_extract_title(string $markdown, string $fallback): string {
    if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
        return trim((string) $matches[1]);
    }

    return $fallback;
}

function bandpromo_docs_all_entries(): array {
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }

    $root = bandpromo_docs_root_dir();
    $relativePaths = ['README.md'];
    foreach (glob($root . '/docs/*.md') ?: [] as $filePath) {
        $relativePaths[] = 'docs/' . basename($filePath);
    }

    $relativePaths = array_values(array_unique($relativePaths));
    $entries = [];
    $audienceMap = bandpromo_docs_audience_map();

    foreach ($relativePaths as $relativePath) {
        $absolutePath = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($absolutePath)) {
            continue;
        }

        $markdown = file_get_contents($absolutePath);
        if ($markdown === false) {
            continue;
        }

        $fallback = preg_replace('/\.md$/i', '', basename($relativePath)) ?: $relativePath;
        $entries[] = [
            'path' => str_replace('\\', '/', $relativePath),
            'absolute_path' => $absolutePath,
            'title' => bandpromo_docs_extract_title($markdown, $fallback),
            'audience' => $audienceMap[str_replace('\\', '/', $relativePath)] ?? 'operator',
        ];
    }

    $preferredOrder = bandpromo_docs_preferred_order();
    usort($entries, static function (array $left, array $right) use ($preferredOrder): int {
        $leftOrder = array_search($left['path'], $preferredOrder, true);
        $rightOrder = array_search($right['path'], $preferredOrder, true);

        if ($leftOrder !== false || $rightOrder !== false) {
            if ($leftOrder === false) {
                return 1;
            }
            if ($rightOrder === false) {
                return -1;
            }
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }
        }

        $titleCompare = strnatcasecmp($left['title'], $right['title']);
        if ($titleCompare !== 0) {
            return $titleCompare;
        }

        return strnatcasecmp($left['path'], $right['path']);
    });

    $catalog = $entries;
    return $catalog;
}

function bandpromo_docs_catalog(string $scope = 'operator'): array {
    return array_values(array_filter(
        bandpromo_docs_all_entries(),
        static fn(array $entry): bool => bandpromo_docs_entry_allowed_for_scope($entry, $scope)
    ));
}

function bandpromo_docs_normalize_relative_path(string $path): string {
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('/#.*$/', '', $path);
    $path = preg_replace('/\?.*$/', '', $path);
    $path = ltrim($path, '/');

    if ($path === '') {
        return '';
    }

    $segments = preg_split('~/+~', $path) ?: [];
    $normalized = [];
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($normalized);
            continue;
        }
        $normalized[] = $segment;
    }

    return implode('/', $normalized);
}

function bandpromo_docs_normalize_path(string $requestedPath, string $scope = 'operator'): string {
    $requestedPath = bandpromo_docs_normalize_relative_path($requestedPath);
    $allowedPaths = array_column(bandpromo_docs_catalog($scope), 'path');

    if ($requestedPath !== '' && in_array($requestedPath, $allowedPaths, true)) {
        return $requestedPath;
    }

    return in_array('README.md', $allowedPaths, true) ? 'README.md' : ($allowedPaths[0] ?? 'README.md');
}

function bandpromo_docs_get_entry(string $requestedPath, string $scope = 'operator'): ?array {
    $selectedPath = bandpromo_docs_normalize_path($requestedPath, $scope);
    foreach (bandpromo_docs_catalog($scope) as $entry) {
        if ($entry['path'] === $selectedPath) {
            return $entry;
        }
    }
    return null;
}

function bandpromo_docs_url(string $docPath, string $scope = ''): string {
    $url = '?tab=docs&doc=' . rawurlencode($docPath);
    if ($scope !== '') {
        $url .= '&doc_scope=' . rawurlencode($scope);
    }
    return $url;
}

function bandpromo_docs_resolve_markdown_target(string $href, string $currentDocPath, string $scope): string {
    if ($href === '' || preg_match('~^(https?:)?//|^mailto:|^#~i', $href)) {
        return '';
    }

    $href = bandpromo_docs_normalize_relative_path($href);
    if ($href === '') {
        return '';
    }

    if (!str_ends_with(strtolower($href), '.md')) {
        return '';
    }

    if (!str_contains($href, '/')) {
        $currentDir = dirname($currentDocPath);
        $href = $currentDir === '.' ? $href : $currentDir . '/' . $href;
    }

    $resolvedPath = bandpromo_docs_normalize_path($href, 'all');
    foreach (bandpromo_docs_all_entries() as $entry) {
        if ($entry['path'] === $resolvedPath && bandpromo_docs_entry_allowed_for_scope($entry, $scope)) {
            return $resolvedPath;
        }
    }

    return '';
}

function bandpromo_docs_render_link(string $label, string $href, string $currentDocPath, string $scope): string {
    $internalTarget = bandpromo_docs_resolve_markdown_target($href, $currentDocPath, $scope);
    if ($internalTarget !== '') {
        return '<a class="docs-inline-link" href="' . htmlspecialchars(bandpromo_docs_url($internalTarget, $scope), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    if (preg_match('~^(https?:)?//|^mailto:~i', $href)) {
        return '<a class="docs-inline-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
}

function bandpromo_docs_render_inline(string $text, string $currentDocPath, string $scope): string {
    $placeholders = [];

    $text = preg_replace_callback('/`([^`]+)`/', static function (array $matches) use (&$placeholders): string {
        $token = 'DOCINLINECODE' . count($placeholders);
        $placeholders[] = [
            'token' => $token,
            'html' => '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>',
        ];
        return $token;
    }, $text);

    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $matches) use (&$placeholders, $currentDocPath, $scope): string {
        $token = 'DOCINLINELINK' . count($placeholders);
        $placeholders[] = [
            'token' => $token,
            'html' => bandpromo_docs_render_link($matches[1], trim((string) $matches[2]), $currentDocPath, $scope),
        ];
        return $token;
    }, $text);

    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped);

    foreach ($placeholders as $placeholder) {
        $escaped = str_replace($placeholder['token'], $placeholder['html'], $escaped);
    }

    return $escaped;
}

function bandpromo_docs_render_markdown(string $markdown, string $currentDocPath, string $scope): string {
    $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
    $html = [];
    $paragraphLines = [];
    $listItems = [];
    $listType = '';
    $quoteLines = [];
    $codeLines = [];
    $inCodeBlock = false;

    $flushParagraph = static function () use (&$html, &$paragraphLines, $currentDocPath, $scope): void {
        if (empty($paragraphLines)) {
            return;
        }
        $html[] = '<p>' . bandpromo_docs_render_inline(trim(implode(' ', $paragraphLines)), $currentDocPath, $scope) . '</p>';
        $paragraphLines = [];
    };

    $flushList = static function () use (&$html, &$listItems, &$listType): void {
        if (empty($listItems) || $listType === '') {
            $listItems = [];
            $listType = '';
            return;
        }
        $html[] = '<' . $listType . ' class="docs-list">' . implode('', $listItems) . '</' . $listType . '>';
        $listItems = [];
        $listType = '';
    };

    $flushQuote = static function () use (&$html, &$quoteLines, $currentDocPath, $scope): void {
        if (empty($quoteLines)) {
            return;
        }
        $html[] = '<blockquote>' . bandpromo_docs_render_inline(trim(implode(' ', $quoteLines)), $currentDocPath, $scope) . '</blockquote>';
        $quoteLines = [];
    };

    $flushCode = static function () use (&$html, &$codeLines): void {
        if (empty($codeLines)) {
            return;
        }
        $html[] = '<pre class="docs-code-block"><code>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES, 'UTF-8') . '</code></pre>';
        $codeLines = [];
    };

    foreach ($lines as $line) {
        if ($inCodeBlock) {
            if (preg_match('/^```/', $line) === 1) {
                $flushCode();
                $inCodeBlock = false;
            } else {
                $codeLines[] = $line;
            }
            continue;
        }

        if (preg_match('/^```/', $line) === 1) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $inCodeBlock = true;
            continue;
        }

        if (trim($line) === '') {
            $flushParagraph();
            $flushList();
            $flushQuote();
            continue;
        }

        if (preg_match('/^\s{0,3}(#{1,6})\s+(.+)$/', $line, $matches) === 1) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $level = strlen($matches[1]);
            $html[] = '<h' . $level . '>' . bandpromo_docs_render_inline(trim((string) $matches[2]), $currentDocPath, $scope) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^\s{0,3}([-*]){3,}\s*$/', $line) === 1) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $html[] = '<hr>';
            continue;
        }

        if (preg_match('/^\s*>\s?(.*)$/', $line, $matches) === 1) {
            $flushParagraph();
            $flushList();
            $quoteLines[] = trim((string) $matches[1]);
            continue;
        }

        if (preg_match('/^\s*([-*]|\d+\.)\s+(.+)$/', $line, $matches) === 1) {
            $flushParagraph();
            $flushQuote();
            $nextListType = preg_match('/^\d+\.$/', $matches[1]) === 1 ? 'ol' : 'ul';
            if ($listType !== '' && $listType !== $nextListType) {
                $flushList();
            }
            $listType = $nextListType;

            $itemText = trim((string) $matches[2]);
            if (preg_match('/^\[(x| )\]\s+(.+)$/i', $itemText, $taskMatches) === 1) {
                $checked = strtolower((string) $taskMatches[1]) === 'x';
                $listItems[] = '<li class="docs-task-item"><span class="docs-task-check ' . ($checked ? 'checked' : 'unchecked') . '">' . ($checked ? '&#10003;' : '&#9633;') . '</span><span>' . bandpromo_docs_render_inline(trim((string) $taskMatches[2]), $currentDocPath, $scope) . '</span></li>';
            } else {
                $listItems[] = '<li>' . bandpromo_docs_render_inline($itemText, $currentDocPath, $scope) . '</li>';
            }
            continue;
        }

        $flushList();
        $flushQuote();
        $paragraphLines[] = trim($line);
    }

    if ($inCodeBlock) {
        $flushCode();
    }

    $flushParagraph();
    $flushList();
    $flushQuote();

    return implode("\n", $html);
}

function bandpromo_docs_render_selected(string $requestedPath, string $scope): array {
    $entry = bandpromo_docs_get_entry($requestedPath, $scope);
    if (!$entry) {
        return [
            'entry' => [
                'path' => bandpromo_docs_normalize_path('', $scope),
                'absolute_path' => bandpromo_docs_root_dir() . '/README.md',
                'title' => 'Documentation',
            ],
            'html' => '<p class="empty-msg">Documentation file not found.</p>',
        ];
    }

    $markdown = file_get_contents($entry['absolute_path']);
    if ($markdown === false) {
        return [
            'entry' => $entry,
            'html' => '<p class="empty-msg">Unable to read this document.</p>',
        ];
    }

    return [
        'entry' => $entry,
        'html' => bandpromo_docs_render_markdown($markdown, $entry['path'], $scope),
    ];
}
