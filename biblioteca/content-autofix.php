<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/content-autofix-helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$dryRun = !empty($body['dry_run']);
$root = dirname(__DIR__);

// Apply must run as a background job (Python supervisor) so it does not depend on
// the browser tab and is not killed by web max_execution_time.
if (!$dryRun) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Apply runs in the background. Use start-catalog-repair.php (Apply repairs button).',
        'use_background' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $report = bandpromo_content_autofix_run($root, true, 'preview');

    bandpromo_admin_audit_log('content_autofix_preview', [
        'target_type' => 'content',
        'target_id' => 'platform-model',
        'status' => !empty($report['ok']) ? 'ok' : 'error',
        'data' => [
            'dry_run' => true,
            'changed_total' => (int) ($report['changed_total'] ?? 0),
            'errors' => $report['errors'] ?? [],
        ],
    ]);

    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    bandpromo_content_autofix_log_write($root, '!!!! exception: ' . $throwable->getMessage());
    $state = &bandpromo_content_autofix_log_state();
    $state['running'] = false;
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ]);
}
