<?php
declare(strict_types=1);

/**
 * Thin CLI: rebuild Files → Audio index from registry after site-health Treat.
 * Not a long heal loop — index rebuild only.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/media-library-state.php';

$root = dirname(__DIR__);
bandpromo_media_files_index_rebuild_target($root, 'audio');
fwrite(STDOUT, "AUDIO_INDEX_REBUILT\n");
exit(0);
