<?php
declare(strict_types=1);

/**
 * Background Portable Campaign/Brand File import worker.
 *
 * Usage (CLI):
 *   php biblioteca/run-package-import-cli.php --job-id=<id> --root=<install-root>
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$jobId = '';
$root = dirname(__DIR__);
foreach ($argv as $arg) {
    if (strpos($arg, '--job-id=') === 0) {
        $jobId = substr($arg, 9);
    } elseif (strpos($arg, '--root=') === 0) {
        $root = substr($arg, 7);
    }
}

$jobId = trim($jobId);
$root = rtrim(str_replace('\\', '/', $root), '/');
if ($jobId === '' || $root === '' || !is_dir($root)) {
    fwrite(STDERR, "Usage: php run-package-import-cli.php --job-id=<id> [--root=<path>]\n");
    exit(1);
}

require_once __DIR__ . '/site-backup-portability.php';

@set_time_limit(0);
ignore_user_abort(true);

try {
    bandpromo_site_backup_dispatch_job($root, $jobId);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
