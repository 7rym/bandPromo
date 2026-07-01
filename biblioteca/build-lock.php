<?php
declare(strict_types=1);

/**
 * Shared build lock / log helpers for publish and optimize runs.
 */

function bandpromo_build_paths(string $root, string $mode): array
{
    $mode = in_array($mode, ['full', 'optimize'], true) ? $mode : 'full';
    $suffix = $mode === 'optimize' ? 'optimize' : 'build';

    return [
        'mode' => $mode,
        'log' => $root . '/log/' . $suffix . '.log',
        'lock' => $root . '/log/' . $suffix . '.lock',
        'meta' => $root . '/log/' . $suffix . '.meta.json',
    ];
}

function bandpromo_build_log_has_exit_code(string $content): bool
{
    return preg_match('/\nEXITCODE:\-?\d+\s*$/', $content) === 1;
}

function bandpromo_build_read_log_tail(string $logFile, int $maxBytes = 65536): string
{
    if (!is_file($logFile)) {
        return '';
    }

    $size = (int) @filesize($logFile);
    if ($size <= 0) {
        return '';
    }

    if ($size <= $maxBytes) {
        $content = @file_get_contents($logFile);
        return is_string($content) ? $content : '';
    }

    $handle = @fopen($logFile, 'rb');
    if ($handle === false) {
        return '';
    }

    fseek($handle, -$maxBytes, SEEK_END);
    $content = stream_get_contents($handle);
    fclose($handle);

    return is_string($content) ? $content : '';
}

function bandpromo_build_lock_is_stale(string $root, string $mode, int $stallSeconds = 90): bool
{
    $paths = bandpromo_build_paths($root, $mode);
    if (!is_file($paths['lock'])) {
        return false;
    }

    $logContent = bandpromo_build_read_log_tail($paths['log']);
    if ($logContent !== '' && bandpromo_build_log_has_exit_code($logContent)) {
        return true;
    }

    $now = time();
    $logMtime = is_file($paths['log']) ? (int) @filemtime($paths['log']) : 0;
    $lockMtime = (int) @filemtime($paths['lock']);

    $metaStartedAt = 0;
    if (is_file($paths['meta'])) {
        $meta = json_decode((string) @file_get_contents($paths['meta']), true);
        if (is_array($meta)) {
            $metaStartedAt = (int) ($meta['started_at'] ?? 0);
        }
    }

    $lastActivity = max($logMtime, $lockMtime, $metaStartedAt);
    if ($lastActivity <= 0) {
        return true;
    }

    return ($now - $lastActivity) >= $stallSeconds;
}

function bandpromo_build_clear_stale_lock(string $root, string $mode, int $stallSeconds = 90): bool
{
    $paths = bandpromo_build_paths($root, $mode);
    if (!is_file($paths['lock'])) {
        return false;
    }

    if (!bandpromo_build_lock_is_stale($root, $mode, $stallSeconds)) {
        return false;
    }

    $logContent = bandpromo_build_read_log_tail($paths['log']);
    if ($logContent !== '' && bandpromo_build_log_has_exit_code($logContent)) {
        @unlink($paths['lock']);
        return true;
    }

    $label = $paths['mode'] === 'optimize' ? 'optimize' : 'build';
    $message = "\n[system] Cleared stale {$label} lock — no active output was detected. You can start again.\n";
    @file_put_contents($paths['log'], $message, FILE_APPEND);
    @unlink($paths['lock']);

    return true;
}

function bandpromo_build_lock_active(string $root, string $mode, int $stallSeconds = 90): bool
{
    bandpromo_build_clear_stale_lock($root, $mode, $stallSeconds);

    $paths = bandpromo_build_paths($root, $mode);
    if (!is_file($paths['lock'])) {
        return false;
    }

    $logContent = bandpromo_build_read_log_tail($paths['log']);
    if ($logContent !== '' && bandpromo_build_log_has_exit_code($logContent)) {
        @unlink($paths['lock']);
        return false;
    }

    return true;
}
