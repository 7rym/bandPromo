<?php

function bandpromo_request_host_raw(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    return strtolower(trim((string) $host));
}

function bandpromo_request_host_without_port(): string
{
    $host = bandpromo_request_host_raw();
    if ($host === '') {
        return '';
    }

    // IPv6 host may be represented as [::1]:8000
    if (str_starts_with($host, '[')) {
        $end = strpos($host, ']');
        if ($end !== false) {
            return substr($host, 1, $end - 1);
        }
    }

    return preg_replace('/:\d+$/', '', $host);
}

function bandpromo_request_port_suffix(): string
{
    $host = bandpromo_request_host_raw();
    if ($host === '') {
        return '';
    }

    if (str_starts_with($host, '[')) {
        $end = strpos($host, ']');
        if ($end === false) {
            return '';
        }
        return substr($host, $end + 1);
    }

    if (preg_match('/:\d+$/', $host) === 1) {
        $pos = strrpos($host, ':');
        return $pos === false ? '' : substr($host, $pos);
    }

    return '';
}

function bandpromo_is_local_loopback_host(): bool
{
    $host = bandpromo_request_host_without_port();
    return $host === '127.0.0.1' || $host === '::1';
}

function bandpromo_is_local_dev_host(): bool
{
    $host = bandpromo_request_host_without_port();
    // Local development hostnames.
    return $host === 'localhost' || bandpromo_is_local_loopback_host();
}

function bandpromo_is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https';
}

/**
 * Keep PHP session files off synced folders (e.g. Google Drive worktrees).
 * Session lock/read on a synced path routinely costs multi-second waits per request.
 */
function bandpromo_configure_session_storage(): void
{
    static $configured = false;
    if ($configured || session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $configured = true;

    // Windows local/dev installs: sessions under %LOCALAPPDATA% (not the repo).
    if (DIRECTORY_SEPARATOR === '\\') {
        $base = getenv('LOCALAPPDATA');
        if (!is_string($base) || trim($base) === '') {
            $base = sys_get_temp_dir();
        }
        $path = rtrim(str_replace('\\', '/', (string) $base), '/') . '/bandPromo/php-sessions';
        if (!is_dir($path)) {
            @mkdir($path, 0700, true);
        }
        if (is_dir($path) && is_writable($path)) {
            session_save_path(str_replace('/', DIRECTORY_SEPARATOR, $path));
        }
    }
}

function bandpromo_current_scheme(): string
{
    return bandpromo_is_https_request() ? 'https' : 'http';
}

function bandpromo_current_origin(): string
{
    if (bandpromo_is_local_loopback_host()) {
        return 'http://localhost' . bandpromo_request_port_suffix();
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return bandpromo_current_scheme() . '://' . $host;
}

function bandpromo_enforce_https(): void
{
    // Must run before any session_start() on this request.
    bandpromo_configure_session_storage();

    // Canonicalize local loopback to localhost to avoid SSL upgrade traps.
    if (bandpromo_is_local_loopback_host()) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: http://localhost' . bandpromo_request_port_suffix() . $requestUri, true, 302);
        exit;
    }

    if (bandpromo_is_local_dev_host() || bandpromo_is_https_request()) {
        return;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    header('Location: https://' . $host . $requestUri, true, 301);
    exit;
}