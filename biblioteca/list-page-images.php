<?php
/**
 * List delivery-ready images for page picture blocks.
 * Visual registry card/thumb variants only.
 * Admin-only.
 */
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/media-delivery-helpers.php';
require_once __DIR__ . '/media-library-state.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
bandpromo_asset_registry_ensure_migrated($root);

$images = [];
$flatImages = [];
$seen = [];

$addItem = static function (
    string $title,
    string $value,
    string $thumbUrl,
    string $group,
    string $assetId = ''
) use (&$images, &$flatImages, &$seen): void {
    $value = trim($value);
    if ($value === '' || isset($seen[$value])) {
        return;
    }
    $seen[$value] = true;
    $item = [
        'title' => $title,
        'value' => $value,
        'thumb_url' => $thumbUrl !== '' ? $thumbUrl : $value,
        'group' => $group,
        'asset_id' => $assetId,
    ];
    $flatImages[] = $item;
};

$registry = bandpromo_asset_load_registry($root);
foreach ($registry['assets'] as $asset) {
    if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
        continue;
    }
    if (($asset['media_type'] ?? '') !== 'image') {
        continue;
    }
    $assetId = (string) ($asset['id'] ?? '');
    $filename = basename((string) ($asset['original_filename'] ?? $asset['master_filename'] ?? ''));
    if ($assetId === '') {
        continue;
    }
    $bucket = (string) ($asset['intake_bucket'] ?? '');
    $cardUrl = bandpromo_visual_resolve_url($root, $assetId, 'card', $bucket, false);
    $thumbUrl = bandpromo_visual_resolve_url($root, $assetId, 'thumb', $bucket, false);
    if ($cardUrl === '' || !str_starts_with($cardUrl, '/media/visual/delivery/')) {
        continue;
    }
    $group = $bucket === 'photo' ? 'Photos' : ($bucket === 'special' ? 'Brand assets' : 'Illustrations');
    $label = $filename !== '' ? $filename : $assetId;
    $addItem($label, $cardUrl, $thumbUrl !== '' ? $thumbUrl : $cardUrl, $group, $assetId);
}

usort($flatImages, static fn(array $left, array $right): int => strnatcasecmp(
    (string) ($left['title'] ?? ''),
    (string) ($right['title'] ?? '')
));

$grouped = [];
foreach ($flatImages as $item) {
    $groupTitle = (string) ($item['group'] ?? 'Images');
    if (!isset($grouped[$groupTitle])) {
        $grouped[$groupTitle] = [
            'title' => $groupTitle,
            'menu' => [],
        ];
    }
    $grouped[$groupTitle]['menu'][] = $item;
}

$images = array_values($grouped);

echo json_encode(['images' => $images, 'flat_images' => $flatImages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
