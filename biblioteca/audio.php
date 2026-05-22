<?php
require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

// Audio streaming can involve overlapping range requests; release the session lock
// immediately so parallel browser requests do not block each other.
session_write_close();

ignore_user_abort(true);
set_time_limit(0);

while (ob_get_level() > 0) {
    ob_end_clean();
}

$variant = isset($_GET['variant']) ? strtolower(trim((string) $_GET['variant'])) : 'optimal';
if (!in_array($variant, ['original', 'optimal'], true)) {
    http_response_code(400);
    exit('Invalid variant');
}

$fileParam = isset($_GET['file']) ? (string) $_GET['file'] : '';
$fileName = basename(str_replace('\\', '/', $fileParam));
if ($fileName === '' || $fileName === '.' || $fileName === '..') {
    http_response_code(400);
    exit('Invalid filename');
}

$audioDir = dirname(__DIR__) . '/media/audio/' . $variant;
$filePath = $audioDir . '/' . $fileName;
if (!is_file($filePath)) {
    http_response_code(404);
    exit('Not found');
}

$size = filesize($filePath);
if ($size === false) {
    http_response_code(500);
    exit('File error');
}

$mimeMap = [
    'mp3' => 'audio/mpeg',
    'flac' => 'audio/flac',
    'ogg' => 'audio/ogg',
    'wav' => 'audio/wav',
    'm4a' => 'audio/mp4',
    'aac' => 'audio/aac',
];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$contentType = $mimeMap[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$start = 0;
$end = $size - 1;
$statusCode = 200;

if (isset($_SERVER['HTTP_RANGE'])) {
    $rangeHeader = trim((string) $_SERVER['HTTP_RANGE']);
    $rangeValue = preg_replace('/^bytes=/i', '', $rangeHeader);
    $hasMultipleRanges = is_string($rangeValue) && strpos($rangeValue, ',') !== false;

    if (!$hasMultipleRanges && preg_match('/^bytes=(\d*)-(\d*)$/i', $rangeHeader, $m)) {
        if ($m[1] !== '') {
            $start = (int) $m[1];
        }
        if ($m[2] !== '') {
            $end = (int) $m[2];
        }

        if ($m[1] === '' && $m[2] !== '') {
            $suffixLen = (int) $m[2];
            if ($suffixLen > 0) {
                $start = max(0, $size - $suffixLen);
                $end = $size - 1;
            }
        }

        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }

        $end = min($end, $size - 1);
        $statusCode = 206;
    }
}

$length = ($end - $start) + 1;
if ($statusCode === 206) {
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . $length);

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

$fp = fopen($filePath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Open error');
}

fseek($fp, $start);
$buffer = 8192;
$remaining = $length;
while (!feof($fp) && $remaining > 0) {
    $read = ($remaining > $buffer) ? $buffer : $remaining;
    $chunk = fread($fp, $read);
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
    if (connection_aborted()) {
        break;
    }
    flush();
}

fclose($fp);
