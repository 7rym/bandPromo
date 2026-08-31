<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/http-stream.php';

$root = dirname(__DIR__);
$filename = basename(trim((string) ($_GET['file'] ?? '')));

if ($filename === '' || str_contains($filename, '..')) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Package filename is required.']);
    exit;
}

$lower = strtolower($filename);
$allowed = (bool) preg_match('/^bandpromo-[a-z0-9-]+-\d{8}-\d{6}\.(pcf|prp|pbf)$/i', $filename)
    || (bool) preg_match('/^release-package-[a-z0-9-]+-\d{8}-\d{6}\.zip$/i', $filename);
if (!$allowed) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid campaign file name.']);
    exit;
}

$path = $root . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $filename;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Package file not found.']);
    exit;
}

$sha256 = bandpromo_transfer_sha256_file($path);

bandpromo_admin_audit_log('release_package_downloaded', [
    'target_type' => 'release_package',
    'target_id' => $filename,
    'status' => 'ok',
    'data' => [
        'size_bytes' => (int) filesize($path),
        'extension' => pathinfo($filename, PATHINFO_EXTENSION),
        'sha256' => $sha256,
    ],
]);

bandpromo_http_stream_file($path, $filename, [
    'sha256' => $sha256,
    'exit' => true,
]);
