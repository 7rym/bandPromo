<?php
/**
 * List optimized images that are safe to embed in static page content.
 * Admin-only.
 */
require_once __DIR__ . '/admin-api-guard.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$groups = [
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
$images = [];

foreach ($groups as $title => $group) {
    $items = [];
    if (is_dir($group['dir'])) {
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

            $items[] = [
                'title' => $filename,
                'value' => $group['urlPrefix'] . rawurlencode($filename),
                'thumb_url' => $group['urlPrefix'] . rawurlencode($filename),
                'group' => $title,
            ];
        }
    }

    if ($items) {
        usort($items, static fn($left, $right) => strnatcasecmp($left['title'], $right['title']));
        $images[] = [
            'title' => $title,
            'menu' => $items,
        ];
    }
}

$flatImages = [];
foreach ($images as $group) {
    if (!isset($group['menu']) || !is_array($group['menu'])) {
        continue;
    }
    foreach ($group['menu'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $flatImages[] = [
            'title' => (string) ($item['title'] ?? ''),
            'value' => (string) ($item['value'] ?? ''),
            'thumb_url' => (string) ($item['thumb_url'] ?? $item['value'] ?? ''),
            'group' => (string) ($group['title'] ?? ''),
        ];
    }
}

echo json_encode(['images' => $images, 'flat_images' => $flatImages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);