<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/release-storage.php';
require_once __DIR__ . '/light-build-tasks.php';
require_once __DIR__ . '/build-required.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'POST') {
        $body = file_get_contents('php://input');
        $payload = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        $title = (string) ($payload['title'] ?? '');
        $preferredId = (string) ($payload['id'] ?? '');
        $entry = bandpromo_release_create($root, $title, $preferredId);

        bandpromo_admin_audit_log('release_created', [
            'target_type' => 'release',
            'target_id' => (string) ($entry['id'] ?? ''),
            'status' => 'ok',
            'data' => ['title' => (string) ($entry['title'] ?? '')],
        ]);

        echo json_encode([
            'ok' => true,
            'release' => $entry,
            'releases' => bandpromo_release_admin_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'PATCH') {
        $body = file_get_contents('php://input');
        $payload = json_decode(is_string($body) ? $body : '', true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload.');
        }

        $releaseId = bandpromo_release_normalize_id((string) ($_GET['release'] ?? ($payload['id'] ?? '')));
        if ($releaseId === '') {
            throw new InvalidArgumentException('Release id is required.');
        }

        $entry = bandpromo_release_update_details($root, $releaseId, $payload);

        bandpromo_admin_audit_log('release_updated', [
            'target_type' => 'release',
            'target_id' => $releaseId,
            'status' => 'ok',
            'data' => [
                'title' => (string) ($entry['title'] ?? ''),
                'release_date' => (string) ($entry['release_date'] ?? ''),
                'locked' => !empty($entry['locked']),
                'has_epk' => trim((string) ($entry['description'] ?? '')) !== ''
                    || trim((string) ($entry['epk']['credits'] ?? '')) !== ''
                    || trim((string) ($entry['epk']['press_contact'] ?? '')) !== ''
                    || !empty($entry['epk']['streaming_links'])
                    || !empty($entry['epk']['press_photo_asset_ids']),
            ],
        ]);

        $buildState = bandpromo_mark_build_required('release_metadata_changed');

        $response = [
            'ok' => true,
            'release' => $entry,
            'releases' => bandpromo_release_admin_registry_entries($root),
            'build_required' => true,
            'build_required_state' => $buildState,
        ];

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'DELETE') {
        $releaseId = bandpromo_release_normalize_id((string) ($_GET['release'] ?? ''));
        if ($releaseId === '') {
            throw new InvalidArgumentException('Release id is required.');
        }

        bandpromo_release_delete($root, $releaseId);

        bandpromo_admin_audit_log('release_deleted', [
            'target_type' => 'release',
            'target_id' => $releaseId,
            'status' => 'ok',
        ]);

        echo json_encode([
            'ok' => true,
            'deleted' => $releaseId,
            'releases' => bandpromo_release_admin_registry_entries($root),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST, PATCH, or DELETE required']);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
