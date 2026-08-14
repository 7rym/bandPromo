<?php

function bandpromo_download_token_dir(string $root): string
{
    return $root . '/data/download_tokens';
}

function bandpromo_download_token_ensure_dir(string $root): void
{
    $dir = bandpromo_download_token_dir($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create download token directory');
    }
}

function bandpromo_download_token_path(string $root, string $token): string
{
    $safe = preg_replace('/[^a-f0-9]/', '', strtolower(trim($token)));
    if ($safe === '') {
        return '';
    }

    return bandpromo_download_token_dir($root) . '/' . $safe . '.json';
}

function bandpromo_download_status_path(string $root, string $token): string
{
    $tokenPath = bandpromo_download_token_path($root, $token);
    if ($tokenPath === '') {
        return '';
    }

    return substr($tokenPath, 0, -5) . '.status.json';
}

function bandpromo_download_status_write(
    string $root,
    string $token,
    string $username,
    string $state,
    string $downloadName = ''
): bool {
    $path = bandpromo_download_status_path($root, $token);
    if ($path === '') {
        return false;
    }

    $payload = [
        'username' => trim($username),
        'state' => trim($state),
        'download_name' => basename(trim($downloadName)),
        'updated_at' => gmdate('c'),
        'expires_at' => time() + 600,
    ];
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $encoded !== false && file_put_contents($path, $encoded, LOCK_EX) !== false;
}

function bandpromo_download_status_read(string $root, string $token): ?array
{
    $path = bandpromo_download_status_path($root, $token);
    if ($path === '' || !is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
        @unlink($path);
        return null;
    }

    return $payload;
}

function bandpromo_download_token_purge_expired(string $root): void
{
    $dir = bandpromo_download_token_dir($root);
    if (!is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir . '/' . $entry;
        if (!is_file($path)) {
            continue;
        }

        $raw = file_get_contents($path);
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
            @unlink($path);
        }
    }
}

function bandpromo_download_token_issue(
    string $root,
    string $username,
    string $target,
    string $variant,
    array $files
): string {
    bandpromo_download_token_ensure_dir($root);
    bandpromo_download_token_purge_expired($root);

    $token = bin2hex(random_bytes(16));
    $payload = [
        'username' => trim($username),
        'target' => trim($target),
        'variant' => trim($variant),
        'files' => array_values(array_filter(array_map(static function ($value) {
            return basename((string) $value);
        }, $files))),
        'expires_at' => time() + 300,
        'issued_at' => gmdate('c'),
    ];

    $path = bandpromo_download_token_path($root, $token);
    if ($path === '') {
        throw new RuntimeException('Could not build download token path');
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($path, $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Could not store download token');
    }
    if (!bandpromo_download_status_write($root, $token, $username, 'pending')) {
        @unlink($path);
        throw new RuntimeException('Could not store download status');
    }

    return $token;
}

function bandpromo_download_token_consume(string $root, string $token): ?array
{
    $path = bandpromo_download_token_path($root, $token);
    if ($path === '' || !is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    @unlink($path);
    if ($raw === false) {
        return null;
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        return null;
    }

    if ((int) ($payload['expires_at'] ?? 0) < time()) {
        return null;
    }

    return $payload;
}
