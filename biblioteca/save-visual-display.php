<?php
/**
 * Save visual asset display fields (title / description / keywords / captured_at)
 * and write-through IPTC/XMP or Matroska tags on the master.
 */
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/media-delivery-helpers.php';
require_once __DIR__ . '/visual-master-helpers.php';
require_once __DIR__ . '/light-build-tasks.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$csrf_token = (string) ($payload['csrf_token'] ?? '');
if (!validate_csrf_token($csrf_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$assetId = trim((string) ($payload['asset_id'] ?? ''));
if (!bandpromo_asset_is_asset_id($assetId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid asset id']);
    exit;
}

$fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
$display = bandpromo_asset_normalize_visual_display([
    'title' => (string) ($fields['title'] ?? ''),
    'description' => (string) ($fields['description'] ?? ''),
    'captured_at' => (string) ($fields['captured_at'] ?? ''),
    'keywords' => $fields['keywords'] ?? [],
]);

$captured = $display['captured_at'];
if ($captured !== '' && !preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $captured)) {
    http_response_code(400);
    echo json_encode(['error' => 'Capture date must use YYYY, YYYY-MM, or YYYY-MM-DD']);
    exit;
}

session_write_close();
$root = dirname(__DIR__);

$asset = bandpromo_asset_lookup_by_id($root, $assetId);
if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
    http_response_code(404);
    echo json_encode(['error' => 'Visual asset not found']);
    exit;
}

$display['synced_at'] = gmdate('c');
$updated = bandpromo_asset_update_entry($root, $assetId, ['display' => $display]);

// Ensure video masters are MKV before tagging.
$tiered = bandpromo_visual_ensure_tiers_for_asset($root, $assetId);
if (is_array($tiered)) {
    $updated = $tiered;
}

$embed = bandpromo_run_light_json_task('scripts/visualMasterMetadata.py', [
    'action' => 'write',
    'asset_id' => $assetId,
    'display' => bandpromo_asset_read_visual_display($updated),
]);

$embedData = is_array($embed['data'] ?? null) ? $embed['data'] : [];
$embedOk = !empty($embed['ok']) && !empty($embedData['ok']);
$embedError = trim((string) ($embedData['error'] ?? $embed['error'] ?? ''));
if (!$embedOk && $embedError === '') {
    $embedError = 'Could not write master metadata';
}

$listingTitle = bandpromo_visual_listing_title($root, $updated);
$operatorTitle = bandpromo_visual_operator_title($root, $updated);
$readDisplay = bandpromo_asset_read_visual_display($updated);

bandpromo_admin_audit_log('visual_display_save', [
    'asset_id' => $assetId,
    'title' => $readDisplay['title'],
    'embed_ok' => $embedOk,
]);

echo json_encode([
    'ok' => true,
    'asset_id' => $assetId,
    'display' => $readDisplay,
    'display_title' => $listingTitle,
    'operator_title' => $operatorTitle,
    'embed_ok' => $embedOk,
    'embed_error' => $embedOk ? '' : $embedError,
    'warning' => $embedOk ? '' : $embedError,
]);
