<?php
/**
 * List delivery-ready images for page picture blocks.
 * Prefers Visual registry card/thumb variants; falls back to legacy optimal trees.
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

$addItem = static function (string $title, string $value, string $thumbUrl, string $group) use (&$images, &$flatImages, &$seen): void {
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
    ];
    $flatImages[] = $item;
};

// Prefer registered visual images with card delivery (dual-read).
$registry = bandpromo_asset_load_registry($root);
foreach ($registry['assets'] as $asset) {
    if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
        continue;
    }
    if (($asset['media_type'] ?? '') !== 'image') {
        continue;
    }
    $assetId = (string) ($asset['id'] ?? '');
    $filename = basename((string) ($asset['original_filename'] ?? ''));
    if ($assetId === '' || $filename === '') {
        continue;
    }
    $bucket = (string) ($asset['intake_bucket'] ?? '');
    $cardUrl = bandpromo_visual_resolve_url($root, $assetId, 'card', $bucket);
    $thumbUrl = bandpromo_visual_resolve_url($root, $assetId, 'thumb', $bucket);
    if ($cardUrl === '') {
        continue;
    }
    $group = $bucket === 'photo' ? 'Photos' : ($bucket === 'special' ? 'Brand assets' : 'Illustrations');
    $addItem($filename, $cardUrl, $thumbUrl !== '' ? $thumbUrl : $cardUrl, $group);
}

// Legacy optimal trees for unregistered files.
$legacyGroups = [
    'Illustrations' => [
        'dir' => $root . '/media/img/optimal',
        'urlPrefix' => '/media/img/optimal/',
    ],
    'Photos' => [
        'dir' => $root . '/media/photo/optimal',
        'urlPrefix' => '/media/photo/optimal/',
    ],
];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

foreach ($legacyGroups as $title => $group) {
    if (!is_dir($group['dir'])) {
        continue;
    }
    foreach (new DirectoryIterator($group['dir']) as $file) {
        if ($file->isDot() || !$file->isFile()) {
            continue;
        }
        $filename = $file->getFilename();
        if (strcasecmp($filename, 'desktop.ini') === 0) {
            continue;
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }
        $url = $group['urlPrefix'] . rawurlencode($filename);
        $addItem($filename, $url, $url, $title);
    }
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
