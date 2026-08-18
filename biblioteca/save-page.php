<?php
/**
 * Save editable page content as JSON block documents. Admin-only.
 */

require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/page-storage.php';

session_write_close();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$root = dirname(__DIR__);
$pageKey = isset($_GET['page']) ? bandpromo_page_normalize_id((string) $_GET['page']) : 'bio';
if (!bandpromo_page_is_allowed_id($pageKey, $root)) {
    http_response_code(400);
    bandpromo_admin_audit_log('page_saved', [
        'target_type' => 'page',
        'target_id' => $pageKey,
        'status' => 'error',
        'data' => ['error' => 'Unknown page'],
    ]);
    echo json_encode(['error' => 'Unknown page']);
    exit;
}

$body = file_get_contents('php://input');
if ($body === false) {
    echo json_encode(['error' => 'Could not read request body']);
    exit;
}

$decoded = json_decode($body, true);
if (!is_array($decoded) || !isset($decoded['document']) || !is_array($decoded['document'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Page saves must send a JSON document payload.']);
    exit;
}

try {
    $documentPayload = $decoded['document'];
    $meta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [];
    if (array_key_exists('title', $meta)) {
        $documentPayload['title'] = (string) $meta['title'];
    }

    $result = bandpromo_page_save_document($root, $documentPayload);
    $document = $result['document'];
    $html = $result['html'];
    $registryEntry = null;
    $registryChanges = [];

    if (array_key_exists('title', $meta)) {
        $registryChanges['title'] = (string) $meta['title'];
    }
    if (array_key_exists('label', $meta)) {
        $registryChanges['label'] = (string) $meta['label'];
    }

    if ($registryChanges !== []) {
        require_once __DIR__ . '/page-registry.php';
        $registryEntry = bandpromo_page_update_registry_entry($root, $pageKey, $registryChanges);
    }

    bandpromo_admin_audit_log('page_saved', [
        'target_type' => 'page',
        'target_id' => $pageKey,
        'status' => 'ok',
        'data' => [
            'format' => 'json_blocks',
            'block_count' => count($document['blocks'] ?? []),
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'format' => 'json_blocks',
        'page' => $pageKey,
        'document' => $document,
        'html' => $html,
        'registry' => is_array($registryEntry) ? [
            'title' => (string) ($registryEntry['title'] ?? ''),
            'label' => (string) ($registryEntry['label'] ?? ''),
            'surface' => (string) ($registryEntry['surface'] ?? 'player'),
            'required' => !empty($registryEntry['required']),
            'show_in_player' => !empty($registryEntry['show_in_player']),
        ] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    bandpromo_admin_audit_log('page_saved', [
        'target_type' => 'page',
        'target_id' => $pageKey,
        'status' => 'error',
        'data' => ['error' => $exception->getMessage(), 'format' => 'json_blocks'],
    ]);
    echo json_encode(['error' => $exception->getMessage()]);
} catch (Throwable $throwable) {
    http_response_code(500);
    bandpromo_admin_audit_log('page_saved', [
        'target_type' => 'page',
        'target_id' => $pageKey,
        'status' => 'error',
        'data' => ['error' => $throwable->getMessage(), 'format' => 'json_blocks'],
    ]);
    echo json_encode(['error' => $throwable->getMessage()]);
}
