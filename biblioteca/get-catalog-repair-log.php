<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/content-autofix-helpers.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$path = bandpromo_content_autofix_log_path($root);
$content = '';
if (is_file($path)) {
    $raw = @file_get_contents($path);
    $content = is_string($raw) ? $raw : '';
    if (strlen($content) > 200000) {
        $content = substr($content, -200000);
    }
}

$running = (bool) preg_match('/^==== Repair catalogue — /m', $content)
    && !preg_match('/^==== finished /m', $content)
    && !preg_match('/!!!! /m', $content);

echo json_encode([
    'ok' => true,
    'content' => $content,
    'running' => $running,
    'mtime' => is_file($path) ? (int) filemtime($path) : 0,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
