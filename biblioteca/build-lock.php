<?php
declare(strict_types=1);

/**
 * Shared build lock / log helpers for publish and optimize runs.
 *
 * Stall timeout is a fallback when the lock file does not carry a live PID.
 * Silent stages (catalogue) can run longer than a short stall — prefer PID
 * liveness, and keep the fallback generous enough for real hosted catalogues.
 */

/** Fallback silence window when lock PID is unknown (seconds). */
if (!defined('BANDPROMO_BUILD_LOCK_STALL_SECONDS')) {
    define('BANDPROMO_BUILD_LOCK_STALL_SECONDS', 900);
}

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

/**
 * Interpret lock payload: numeric PID, or status tokens like "running"/"preparing".
 *
 * @return array{pid: ?int, alive: ?bool, raw: string}
 */
function bandpromo_build_lock_process_state(string $lockFile): array
{
    $raw = trim((string) @file_get_contents($lockFile));
    if ($raw === '' || !ctype_digit($raw)) {
        return ['pid' => null, 'alive' => null, 'raw' => $raw];
    }

    $pid = (int) $raw;
    if ($pid <= 1) {
        return ['pid' => null, 'alive' => null, 'raw' => $raw];
    }

    $alive = null;
    if (function_exists('posix_kill')) {
        $alive = @posix_kill($pid, 0);
    } elseif (is_dir('/proc/' . $pid)) {
        $alive = true;
    } elseif (strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN') {
        $alive = null;
    } else {
        $alive = null;
    }

    return ['pid' => $pid, 'alive' => $alive, 'raw' => $raw];
}

function bandpromo_build_lock_is_stale(
    string $root,
    string $mode,
    int $stallSeconds = BANDPROMO_BUILD_LOCK_STALL_SECONDS
): bool {
    $paths = bandpromo_build_paths($root, $mode);
    if (!is_file($paths['lock'])) {
        return false;
    }

    $logContent = bandpromo_build_read_log_tail($paths['log']);
    if ($logContent !== '' && bandpromo_build_log_has_exit_code($logContent)) {
        return true;
    }

    $process = bandpromo_build_lock_process_state($paths['lock']);
    if ($process['alive'] === true) {
        // Build Python runner is still alive — silent stages must not clear the lock.
        return false;
    }
    if ($process['alive'] === false) {
        // PID recorded but process gone — orphan lock.
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

function bandpromo_build_clear_stale_lock(
    string $root,
    string $mode,
    int $stallSeconds = BANDPROMO_BUILD_LOCK_STALL_SECONDS
): bool {
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

    $label = $paths['mode'] === 'optimize' ? 'optimize' : 'refresh';
    $message = "\n[system] This {$label} did not finish (no running process was found). "
        . "It was not successful — check the log under the hood, then try again.\n";
    @file_put_contents($paths['log'], $message, FILE_APPEND);
    @unlink($paths['lock']);

    return true;
}

function bandpromo_build_lock_active(
    string $root,
    string $mode,
    int $stallSeconds = BANDPROMO_BUILD_LOCK_STALL_SECONDS
): bool {
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
