<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "build-sfx-delivery-cli.php is CLI-only.\n");
    exit(1);
}

require_once __DIR__ . '/sfx-helpers.php';

$root = dirname(__DIR__);
$result = bandpromo_sfx_backfill_tiers($root);

$built = (int) ($result['deliveries'] ?? 0);
$skipped = (int) ($result['skipped'] ?? 0);
$warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : [];
$failed = count($warnings);
$handled = $built + $skipped + $failed;

echo 'Sound effects delivery: ' . $built . ' built, ' . $skipped . ' already up to date';
if ($failed > 0) {
    echo ', ' . $failed . ' warning' . ($failed === 1 ? '' : 's');
}
echo "\n";

foreach ($warnings as $warning) {
    echo '  - ' . $warning . "\n";
}

echo 'BUILD_STATS scope=media handled=' . $handled
    . ' created=' . $built
    . ' fresh=' . $skipped
    . ' failed=' . $failed
    . "\n";

if ($failed > 0) {
    echo "Sound-effects delivery finished with warnings.\n";
    // Missing delivery for some SFX should not fail the whole Publish;
    // login falls back to silence when optimal is absent.
    exit(0);
}

echo "Sound-effects delivery finished.\n";
exit(0);
