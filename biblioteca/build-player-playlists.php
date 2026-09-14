<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/publish-status-helpers.php';

$root = dirname(__DIR__);

@ini_set('output_buffering', '0');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$progress = static function (string $message): void {
    fwrite(STDOUT, $message . PHP_EOL);
    flush();
};

$progress('Publishing static player JSON for every playlist in the registry...');

try {
    $result = bandpromo_playlist_publish_all_player_payloads($root, $progress);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Player playlist publish failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$publishedCount = 0;
$unchangedCount = 0;
$clearedCount = 0;
$coversHealed = (int) ($result['covers_healed'] ?? 0);
$payloadsChanged = (int) ($result['payloads_changed'] ?? 0);

foreach ($result['published'] as $entry) {
    $trackCount = (int) ($entry['track_count'] ?? 0);
    $changed = !empty($entry['changed']);
    if ($trackCount > 0) {
        if ($changed) {
            $publishedCount++;
        } else {
            $unchangedCount++;
        }
    } else {
        if ($changed) {
            $clearedCount++;
        } else {
            $unchangedCount++;
        }
    }
}

$errorCount = is_array($result['errors'] ?? null) ? count($result['errors']) : 0;

$markerDir = $root . '/log';
if (!is_dir($markerDir)) {
    @mkdir($markerDir, 0750, true);
}
$marker = [
    'covers_healed' => $coversHealed,
    'covers_extracted' => 0,
    'payloads_changed' => $payloadsChanged,
    'published' => $publishedCount,
    'unchanged' => $unchangedCount,
    'cleared' => $clearedCount,
    'errors' => $errorCount,
    'written_at' => gmdate('c'),
];
@file_put_contents(
    $markerDir . '/build-playlist-stage.json',
    json_encode($marker, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

$progress('Refreshing delivery inventory snapshot...');
$inventoryStarted = microtime(true);
try {
    bandpromo_delivery_refresh_inventory_snapshot($root);
    $progress(sprintf('  inventory snapshot done in %.1fs', microtime(true) - $inventoryStarted));
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Inventory snapshot failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

if ($payloadsChanged > 0 || $coversHealed > 0) {
    $progress('Rebuilding media Files index (payloads or covers changed)...');
    $indexStarted = microtime(true);
    try {
        require_once __DIR__ . '/media-library-state.php';
        bandpromo_media_files_index_rebuild_all($root);
        $progress(sprintf('  media Files index done in %.1fs', microtime(true) - $indexStarted));
    } catch (Throwable $throwable) {
        fwrite(STDERR, 'Media Files index rebuild failed: ' . $throwable->getMessage() . PHP_EOL);
        exit(1);
    }
} else {
    $progress('Skipping media Files index rebuild — no player payloads or covers changed.');
}

fwrite(
    STDOUT,
    'BUILD_STATS scope=playlist handled=' . ($publishedCount + $unchangedCount + $clearedCount)
    . ' created=' . ($publishedCount + $clearedCount)
    . ' fresh=' . $unchangedCount
    . ' failed=' . $errorCount
    . PHP_EOL
);

if ($result['errors'] !== []) {
    foreach ($result['errors'] as $error) {
        $playlistId = (string) ($error['playlist_id'] ?? '');
        $message = (string) ($error['error'] ?? 'Unknown error');
        fwrite(STDERR, "Failed to publish player playlist {$playlistId}: {$message}" . PHP_EOL);
    }
    exit(1);
}

$progress('Player playlist payloads published for all playlists.');
exit(0);
