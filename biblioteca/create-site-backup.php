<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/site-backup-portability.php';

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

$root = dirname(__DIR__);
$actor = trim((string) ($_SESSION['username'] ?? ''));

try {
    if (isset($payload['components'])) {
        $components = bandpromo_site_backup_normalize_components($payload['components']);
    } else {
        $type = strtolower(trim((string) ($payload['type'] ?? 'full')));
        $includeLogRaw = strtolower(trim((string) ($payload['include_log'] ?? '1')));
        $includeLog = !in_array($includeLogRaw, ['0', 'false', 'no'], true);
        if ($type === 'data') {
            $components = bandpromo_site_backup_normalize_components('data');
        } elseif ($type === 'full') {
            $components = bandpromo_site_backup_normalize_components('full');
            if (!$includeLog) {
                $components = array_values(array_filter(
                    $components,
                    static fn(string $component): bool => $component !== BANDPROMO_SITE_BACKUP_COMPONENT_LOGS
                ));
            }
        } else {
            throw new InvalidArgumentException('Unknown backup type. Use components or full/data.');
        }
    }

    $job = bandpromo_site_backup_enqueue($root, $components, $actor);

    bandpromo_admin_audit_log('site_backup_queued', [
        'backup_type' => $job['type'] ?? '',
        'components' => $job['components'] ?? [],
        'job_id' => $job['id'],
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Backup queued. It will appear below when ready.',
        'job' => $job,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        bandpromo_site_backup_dispatch_job($root, (string) $job['id']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

exit;
