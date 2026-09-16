<?php
declare(strict_types=1);

/**
 * Request a cooperative stop for a background job (Repair / Refresh / Optimize).
 */

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/job-stop.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode(is_string($raw) ? $raw : '', true);
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

$job = strtolower(trim((string) ($payload['job'] ?? '')));
$allowed = ['catalog_repair', 'repair', 'build', 'full', 'optimize', 'site_health', 'site-health', 'health'];
if (!in_array($job, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown job.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (in_array($job, ['catalog_repair', 'repair'], true) && !isDeveloperUser($_SESSION['username'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Developer access required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$root = dirname(__DIR__);
if (!bandpromo_job_stop_request($root, $job)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write stop flag.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok' => true,
    'job' => $job,
    'message' => 'Stop requested. The job will finish its current step, then exit.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
