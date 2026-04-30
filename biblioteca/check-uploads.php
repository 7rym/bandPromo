<?php
/**
 * Returns a list of already-uploaded media files in original-quality directories.
 * Used by setup.php to let the user reuse existing uploads.
 * No auth required — only returns filenames/sizes, not file contents.
 */
session_start();

header('Content-Type: application/json');

$root     = dirname(__DIR__);
$audioDirs = [
    'audio' => $root . '/media/audio/original',
    'image' => $root . '/media/img/original',
];

$files = [];
foreach ($audioDirs as $type => $dir) {
    if (!is_dir($dir)) continue;
    $extensions = $type === 'audio' ? ['flac', 'mp3'] : ['png', 'jpg', 'jpeg'];
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions)) continue;
        $files[] = [
            'name' => $item,
            'type' => $type,
            'size' => filesize($dir . '/' . $item),
        ];
    }
}

echo json_encode(['ok' => true, 'files' => $files]);
