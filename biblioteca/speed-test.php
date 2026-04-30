<?php
declare(strict_types=1);

// Lightweight same-origin download endpoint used by login speed test fallback.
$bytes = isset($_GET['bytes']) ? (int)$_GET['bytes'] : 5000000;
$bytes = max(1000000, min(10000000, $bytes));

header('Content-Type: application/octet-stream');
header('Content-Length: ' . (string) $bytes);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$remaining = $bytes;
$chunkSize = 65536;

while ($remaining > 0) {
    $write = min($chunkSize, $remaining);
    echo str_repeat('A', $write);
    $remaining -= $write;

    if (connection_aborted()) {
        break;
    }

    flush();
}
