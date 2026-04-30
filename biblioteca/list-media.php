<?php
/**
 * List media files for a given target directory.
 * Query param: ?target=audio|cover|photos|video
 * Returns: { files: [{name, size, modified}] }
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
$dirs = [
    'audio'         => $root . '/media/audio/original',
    'illustrations' => $root . '/media/img/original',
    'photos'        => $root . '/media/photo/original',
    'video'         => $root . '/media/video/original',
    'special'       => $root . '/media/special',
];

$target = $_GET['target'] ?? '';
if (!isset($dirs[$target])) {
    echo json_encode(['error' => 'Unknown target: ' . htmlspecialchars($target)]);
    exit;
}

$dir = $dirs[$target];
$files = [];

if (is_dir($dir)) {
    foreach (new DirectoryIterator($dir) as $f) {
        if ($f->isDot() || $f->isDir()) continue;

        $filename = $f->getFilename();
        if (strcasecmp($filename, 'desktop.ini') === 0) continue;

        $files[] = [
            'name'     => $filename,
            'size'     => $f->getSize(),
            'modified' => $f->getMTime(),
        ];
    }
    usort($files, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
}

echo json_encode(['files' => $files, 'dir' => str_replace($root, '', $dir)], JSON_UNESCAPED_UNICODE);
