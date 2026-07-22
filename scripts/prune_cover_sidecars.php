<?php
declare(strict_types=1);

/**
 * CLI: prune legacy stem-named track cover sidecars when registry points at a pool file.
 * Usage: php scripts/prune_cover_sidecars.php
 */
$root = dirname(__DIR__);
require $root . '/biblioteca/audio-master-detail-helpers.php';

$deleted = bandpromo_audio_master_prune_redundant_cover_sidecars($root);
fwrite(STDOUT, (string) count($deleted) . PHP_EOL);
exit(0);
