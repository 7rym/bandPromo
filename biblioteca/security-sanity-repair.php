<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/security-sanity-helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'POST required.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode(is_string($rawBody) ? $rawBody : '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$csrfToken = isset($payload['csrf_token']) ? (string) $payload['csrf_token'] : '';
if (!validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Session expired or invalid request token. Refresh admin and try again.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$dryRun = !empty($payload['dry_run']);
$root = dirname(__DIR__);

try {
    $report = bandpromo_security_sanity_repair($root, $dryRun);

    bandpromo_admin_audit_log('security_sanity_' . ($dryRun ? 'preview' : 'repair'), [
        'target_type' => 'install',
        'target_id' => 'protection-stubs',
        'status' => !empty($report['ok']) ? 'ok' : 'error',
        'data' => [
            'dry_run' => $dryRun,
            'changed_total' => (int) ($report['changed_total'] ?? 0),
            'errors' => $report['errors'] ?? [],
            'secure_after' => !empty($report['check']['secure']),
        ],
    ]);

    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
