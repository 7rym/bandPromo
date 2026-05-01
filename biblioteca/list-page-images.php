<?php
/**
 * List optimized images that are safe to embed in static page content.
 * Admin-only.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

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

echo json_encode(['images' => $images], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);