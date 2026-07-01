<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "build-catalog-cli.php is CLI-only.\n");
    exit(1);
}

require_once __DIR__ . '/build-catalog-helpers.php';

$root = dirname(__DIR__);
$result = bandpromo_build_catalog_run($root);
$steps = is_array($result['steps'] ?? null) ? $result['steps'] : [];

foreach ($steps as $step) {
    if (!is_array($step)) {
        continue;
    }
    $label = (string) ($step['label'] ?? $step['id'] ?? 'Step');
    $changed = (int) ($step['changed'] ?? 0);
    $skipped = (int) ($step['skipped'] ?? 0);
    $errorCount = is_array($step['errors'] ?? null) ? count($step['errors']) : 0;
    echo $label . ': ' . $changed . ' changed, ' . $skipped . ' skipped';
    if ($errorCount > 0) {
        echo ', ' . $errorCount . ' error' . ($errorCount === 1 ? '' : 's');
    }
    echo "\n";

    if ($errorCount > 0) {
        foreach ($step['errors'] as $error) {
            echo '  - ' . $error . "\n";
        }
    }
}

if (!empty($result['errors'])) {
    echo "Catalog stage finished with errors.\n";
    exit(1);
}

echo "Catalog stage finished.\n";
exit(0);
