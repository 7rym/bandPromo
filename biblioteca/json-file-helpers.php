<?php

function bandpromo_json_read_array_file(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function bandpromo_json_write_file(string $file, $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        return false;
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    return file_put_contents($file, $encoded, LOCK_EX) !== false;
}
