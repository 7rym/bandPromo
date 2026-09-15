<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/content-autofix-helpers.php';
require_once __DIR__ . '/catalog-repair-auto.php';

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

$locked = bandpromo_catalog_repair_is_locked($root);
$finished = (bool) preg_match('/^==== finished /m', $content)
    || (bool) preg_match('/^==== stopped by operator ====/m', $content)
    || (bool) preg_match('/^EXITCODE:/m', $content);
$failed = (bool) preg_match('/!!!! /m', $content);
$running = $locked || (
    (bool) preg_match('/^==== Repair catalogue — /m', $content)
    && !$finished
    && !$failed
);

echo json_encode([
    'ok' => true,
    'content' => $content,
    'running' => $running,
    'locked' => $locked,
    'mtime' => is_file($path) ? (int) filemtime($path) : 0,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
