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
    || (bool) preg_match('/^EXITCODE:/m', $content)
    || (bool) preg_match('/Repair finished successfully/m', $content)
    || (bool) preg_match('/Repair stopped by operator/m', $content);
$failed = (bool) preg_match('/!!!! /m', $content)
    || (bool) preg_match('/FAILED Repair/m', $content);
$running = $locked || (
    (bool) preg_match('/^==== Repair catalogue — /m', $content)
    && !$finished
    && !$failed
);

$metaFile = $root . '/log/catalog-repair.meta.json';
$meta = [];
if (is_file($metaFile)) {
    $decoded = json_decode((string) @file_get_contents($metaFile), true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}
$heartbeatAt = (int) ($meta['heartbeat_at'] ?? $meta['updated_at'] ?? 0);
$startedAt = (int) ($meta['started_at'] ?? 0);

echo json_encode([
    'ok' => true,
    'content' => $content,
    'running' => $running,
    'locked' => $locked,
    'mtime' => is_file($path) ? (int) filemtime($path) : 0,
    'job' => [
        'stage' => trim((string) ($meta['stage'] ?? '')),
        'message' => trim((string) ($meta['message'] ?? '')),
        'started_at' => $startedAt > 0 ? $startedAt : null,
        'heartbeat_at' => $heartbeatAt > 0 ? $heartbeatAt : null,
        'heartbeat_age_s' => $heartbeatAt > 0 ? max(0, time() - $heartbeatAt) : null,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
