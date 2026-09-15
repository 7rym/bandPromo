<?php
declare(strict_types=1);

/**
 * CLI Apply for catalogue Repair — run by scripts/catalogRepair.py in the background.
 * Not bound by web max_execution_time. Honour log/catalog-repair.stop between steps.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "content-autofix-cli.php is CLI-only.\n");
    exit(1);
}

putenv('BANDPROMO_REPAIR_CLI=1');
$_ENV['BANDPROMO_REPAIR_CLI'] = '1';

require_once __DIR__ . '/content-autofix-helpers.php';
require_once __DIR__ . '/job-stop.php';

$root = dirname(__DIR__);
@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');

try {
    $report = bandpromo_content_autofix_run($root, false, 'cli');
    $ok = !empty($report['ok']) && empty($report['errors']);
    $stopped = !empty($report['stopped']);
    if ($stopped) {
        fwrite(STDOUT, "REPAIR_STOPPED\n");
        exit(0);
    }
    if (!$ok) {
        fwrite(STDERR, 'Repair finished with errors: ' . (string) ($report['message'] ?? '') . "\n");
        exit(1);
    }
    fwrite(STDOUT, "REPAIR_OK\n");
    exit(0);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Repair failed: ' . $throwable->getMessage() . "\n");
    bandpromo_content_autofix_log_write($root, '!!!! exception: ' . $throwable->getMessage());
    exit(1);
}
