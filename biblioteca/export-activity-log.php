<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/activity-store.php';

$root = dirname(__DIR__);
$dateStart = trim((string) ($_GET['date_start'] ?? gmdate('Y-m-d', strtotime('-30 days'))));
$dateEnd = trim((string) ($_GET['date_end'] ?? gmdate('Y-m-d')));
$format = strtolower(trim((string) ($_GET['format'] ?? 'jsonl')));
if (!in_array($format, ['jsonl', 'csv'], true)) {
    $format = 'jsonl';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid date range. Use YYYY-MM-DD.']);
    exit;
}

$filename = 'bandpromo-activity-' . $dateStart . '_to_' . $dateEnd . '.' . ($format === 'csv' ? 'csv' : 'jsonl');
header('Content-Type: ' . ($format === 'csv' ? 'text/csv; charset=utf-8' : 'application/x-ndjson; charset=utf-8'));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit;
}

try {
    bandpromo_activity_store_stream_listener_export($root, $dateStart, $dateEnd, $format, $output);
} catch (Throwable $e) {
    error_log('bandPromo activity export failed: ' . $e->getMessage());
    http_response_code(500);
}

fclose($output);
