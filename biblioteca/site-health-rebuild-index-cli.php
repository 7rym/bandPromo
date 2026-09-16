<?php
declare(strict_types=1);

/**
 * Thin CLI: rebuild Files indexes for given targets after site-health Treat.
 * Usage: php site-health-rebuild-index-cli.php audio
 *        php site-health-rebuild-index-cli.php illustrations photos video
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/media-library-state.php';

$root = dirname(__DIR__);
$targets = array_slice($argv, 1);
if ($targets === []) {
    $targets = ['audio'];
}

$allowed = ['audio', 'illustrations', 'photos', 'video', 'sfx', 'special'];
foreach ($targets as $target) {
    $target = strtolower(trim((string) $target));
    if (!in_array($target, $allowed, true)) {
        fwrite(STDERR, "Unknown target: {$target}\n");
        exit(1);
    }
    bandpromo_media_files_index_rebuild_target($root, $target);
    fwrite(STDOUT, 'INDEX_REBUILT:' . $target . "\n");
}

exit(0);
