<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/publish-status-helpers.php';

$root = dirname(__DIR__);

try {
    $result = bandpromo_playlist_publish_all_player_payloads($root);
    bandpromo_delivery_refresh_inventory_snapshot($root);
    require_once __DIR__ . '/media-library-state.php';
    bandpromo_media_files_index_rebuild_all($root);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Player playlist publish failed: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

$publishedCount = 0;
$unchangedCount = 0;
$clearedCount = 0;
foreach ($result['published'] as $entry) {
    $playlistId = (string) ($entry['playlist_id'] ?? '');
    $trackCount = (int) ($entry['track_count'] ?? 0);
    $builtAt = (string) ($entry['player_built_at'] ?? '');
    $changed = !empty($entry['changed']);
    if ($trackCount > 0) {
        if ($changed) {
            $publishedCount++;
            fwrite(STDOUT, "Published player playlist {$playlistId} ({$trackCount} track(s)) at {$builtAt}" . PHP_EOL);
        } else {
            $unchangedCount++;
            fwrite(STDOUT, "Player playlist {$playlistId} already up to date ({$trackCount} track(s))" . PHP_EOL);
        }
    } else {
        if ($changed) {
            $clearedCount++;
            fwrite(STDOUT, "Cleared player playlist payload for {$playlistId}" . PHP_EOL);
        } else {
            $unchangedCount++;
            fwrite(STDOUT, "Player playlist {$playlistId} already empty" . PHP_EOL);
        }
    }
}

$errorCount = is_array($result['errors'] ?? null) ? count($result['errors']) : 0;
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

exit(0);
