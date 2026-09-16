<?php
declare(strict_types=1);

/**
 * Cooperative stop flags for long background jobs (Repair / Publish / Optimize).
 * Workers finish the current step/stage, then exit cleanly — never kill mid-write.
 */

function bandpromo_job_stop_path(string $root, string $job): string
{
    $root = rtrim($root, '/\\');
    $job = strtolower(trim($job));
    $map = [
        'catalog_repair' => 'catalog-repair.stop',
        'repair' => 'catalog-repair.stop',
        'build' => 'build.stop',
        'full' => 'build.stop',
        'optimize' => 'optimize.stop',
        'site_health' => 'site-health.stop',
        'site-health' => 'site-health.stop',
        'health' => 'site-health.stop',
    ];
    $file = $map[$job] ?? '';
    if ($file === '') {
        return '';
    }

    return $root . '/log/' . $file;
}

function bandpromo_job_stop_clear(string $root, string $job): void
{
    $path = bandpromo_job_stop_path($root, $job);
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
}

function bandpromo_job_stop_request(string $root, string $job): bool
{
    $path = bandpromo_job_stop_path($root, $job);
    if ($path === '') {
        return false;
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }

    return @file_put_contents($path, (string) time() . "\n") !== false;
}

function bandpromo_job_stop_requested(string $root, string $job): bool
{
    $path = bandpromo_job_stop_path($root, $job);
    if ($path === '' || !is_file($path)) {
        return false;
    }
    // Stale stop flags older than 2 hours are ignored.
    $age = time() - (int) @filemtime($path);
    if ($age > 7200) {
        @unlink($path);

        return false;
    }

    return true;
}
