<?php
declare(strict_types=1);

require_once __DIR__ . '/https.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/theme-storage.php';
require_once __DIR__ . '/asset-registry.php';

bandpromo_enforce_https();
session_write_close();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$root = dirname(__DIR__);
$decoded = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

try {
    bandpromo_theme_ensure_seeded($root);
    $brandId = bandpromo_brand_canonical_id((string) ($decoded['brand_id'] ?? ''));
    $action = strtolower(trim((string) ($decoded['action'] ?? '')));
    $assetIds = [];
    if (isset($decoded['asset_ids']) && is_array($decoded['asset_ids'])) {
        foreach ($decoded['asset_ids'] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                $assetIds[] = $candidate;
            }
        }
    }
    $singleAssetId = trim((string) ($decoded['asset_id'] ?? ''));
    if ($singleAssetId !== '') {
        $assetIds[] = $singleAssetId;
    }
    $assetIds = array_values(array_unique($assetIds));
    if ($brandId === '' || $assetIds === [] || !in_array($action, ['add', 'remove'], true)) {
        throw new InvalidArgumentException('brand_id, asset_id(s), and add/remove action are required.');
    }

    $document = bandpromo_theme_load_document($root, $brandId);
    if (!bandpromo_brand_may_edit_document($document)) {
        throw new RuntimeException('This brand is locked. Duplicate it or unlock it on localhost before changing its library.');
    }

    $library = array_fill_keys(
        is_array($document['library_asset_ids'] ?? null) ? $document['library_asset_ids'] : [],
        true
    );
    foreach ($assetIds as $assetId) {
        $asset = bandpromo_asset_lookup_by_id($root, $assetId);
        $kind = is_array($asset) ? (string) ($asset['kind'] ?? '') : '';
        if (!is_array($asset) || !in_array($kind, ['visual', 'sfx'], true)) {
            throw new InvalidArgumentException('Brand libraries accept registered Visual and Sound effect assets only.');
        }

        if ($action === 'add') {
            $library[$assetId] = true;
            continue;
        }

        foreach ((array) ($document['asset_ids'] ?? []) as $slotAssetId) {
            if ((string) $slotAssetId === $assetId) {
                throw new RuntimeException('Clear this asset from its Brand shell slot before removing it from the library.');
            }
        }
        unset($library[$assetId]);
    }

    $document['library_asset_ids'] = array_keys($library);
    bandpromo_theme_write_document($root, $document, ['allow_locked' => true]);
    $document = bandpromo_theme_load_document($root, $brandId);

    bandpromo_admin_audit_log('brand_library_' . $action, [
        'target_type' => 'brand',
        'target_id' => $brandId,
        'asset_id' => $assetIds[0],
        'asset_ids' => $assetIds,
        'count' => count($assetIds),
        'status' => 'ok',
    ]);

    echo json_encode([
        'ok' => true,
        'brand_id' => $brandId,
        'asset_id' => $assetIds[0],
        'asset_ids' => $assetIds,
        'library_asset_ids' => $document['library_asset_ids'] ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $throwable->getMessage()]);
}
