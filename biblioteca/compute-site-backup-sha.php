<?php
declare(strict_types=1);

/**
 * Background SHA-256 for large Ready backup/PCF/PBF archives.
 *
 * Usage (CLI):
 *   php biblioteca/compute-site-backup-sha.php --job-id=<id> --root=<install-root>
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
    fwrite(STDERR, "Usage: php compute-site-backup-sha.php --job-id=<id> [--root=<path>]\n");
    exit(1);
}

require_once __DIR__ . '/site-backup-portability.php';
require_once __DIR__ . '/http-stream.php';

@set_time_limit(0);
ignore_user_abort(true);

try {
    $job = bandpromo_site_backup_read_job($root, $jobId);
    if ($job === null) {
        exit(0);
    }
    if ((string) ($job['status'] ?? '') !== BANDPROMO_SITE_BACKUP_JOB_READY) {
        exit(0);
    }
    if (strtolower(trim((string) ($job['sha256'] ?? ''))) !== '') {
        exit(0);
    }

    $zipPath = bandpromo_site_backup_job_zip_path($root, $jobId);
    if (!is_file($zipPath) || filesize($zipPath) <= 0) {
        exit(1);
    }

    $job['sha256_pending'] = true;
    $job['progress'] = 'Checksumming archive…';
    bandpromo_site_backup_write_job($root, $job);

    $lastTouch = 0.0;
    $sha = bandpromo_transfer_sha256_file_with_progress(
        $zipPath,
        static function (int $bytesRead, int $totalBytes) use ($root, $jobId, &$lastTouch): void {
            $now = microtime(true);
            if (($now - $lastTouch) < 2.0 && $bytesRead < $totalBytes) {
                return;
            }
            $lastTouch = $now;
            $fresh = bandpromo_site_backup_read_job($root, $jobId);
            if ($fresh === null || (string) ($fresh['status'] ?? '') !== BANDPROMO_SITE_BACKUP_JOB_READY) {
                return;
            }
            $fresh['sha256_pending'] = true;
            $fresh['progress'] = 'Checksumming archive · '
                . bandpromo_transfer_format_bytes($bytesRead)
                . ' / '
                . bandpromo_transfer_format_bytes($totalBytes);
            $fresh['heartbeat_at_utc'] = gmdate('c');
            try {
                bandpromo_site_backup_write_job($root, $fresh);
            } catch (Throwable $e) {
                // Keep hashing.
            }
        }
    );

    $fresh = bandpromo_site_backup_read_job($root, $jobId);
    if ($fresh === null) {
        exit(0);
    }
    if ($sha === '') {
        $fresh['sha256_pending'] = false;
        $fresh['progress'] = '';
        $fresh['error'] = trim((string) ($fresh['error'] ?? ''));
        bandpromo_site_backup_write_job($root, $fresh);
        exit(1);
    }

    $fresh['sha256'] = $sha;
    $fresh['sha256_pending'] = false;
    $fresh['progress'] = '';
    $fresh['heartbeat_at_utc'] = gmdate('c');
    bandpromo_site_backup_write_job($root, $fresh);
    $lockPath = bandpromo_site_backup_ensure_dir($root) . '/' . $jobId . '.sha.lock';
    if (is_file($lockPath)) {
        @unlink($lockPath);
    }
    exit(0);
} catch (Throwable $e) {
    try {
        $fresh = bandpromo_site_backup_read_job($root, $jobId);
        if (is_array($fresh)) {
            $fresh['sha256_pending'] = false;
            $fresh['progress'] = '';
            bandpromo_site_backup_write_job($root, $fresh);
        }
        $lockPath = bandpromo_site_backup_ensure_dir($root) . '/' . $jobId . '.sha.lock';
        if (is_file($lockPath)) {
            @unlink($lockPath);
        }
    } catch (Throwable $ignored) {
        // Ignore.
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
