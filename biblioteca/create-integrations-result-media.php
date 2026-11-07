<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/create-integrations.php';

bandpromo_enforce_https();
session_write_close();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed';
    exit;
}

$username = trim((string) ($_SESSION['username'] ?? ''));
if ($username === '') {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not signed in.';
    exit;
}
if (!isDeveloperUser($username)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Create is developer-only for now.';
    exit;
}

$kind = trim((string) ($_GET['kind'] ?? ''));
$kinds = bandpromo_create_integrations_result_media_kinds();
if ($kind === '' || !isset($kinds[$kind])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unknown result.';
    exit;
}

$root = dirname(__DIR__);
$path = bandpromo_create_integrations_result_media_path($root, $username, $kind);
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Result media not found.';
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = (string) ($kinds[$kind]['mime'] ?? 'application/octet-stream');
if ($ext === 'jpg' || $ext === 'jpeg') {
    $mime = 'image/jpeg';
} elseif ($ext === 'png') {
    $mime = 'image/png';
} elseif ($ext === 'webp') {
    $mime = 'image/webp';
} elseif ($ext === 'gif') {
    $mime = 'image/gif';
} elseif ($ext === 'mp4') {
    $mime = 'video/mp4';
} elseif ($ext === 'webm') {
    $mime = 'video/webm';
}

$size = filesize($path);
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
if ($size !== false) {
    header('Content-Length: ' . (string) $size);
}

$fh = fopen($path, 'rb');
if ($fh === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Could not read result media.';
    exit;
}
fpassthru($fh);
fclose($fh);
