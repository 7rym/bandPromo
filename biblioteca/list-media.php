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

require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/audio-master-helpers.php';

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$dirs = [
    'audio'         => bandpromo_media_target_dir('audio'),
    'illustrations' => bandpromo_media_target_dir('illustrations'),
    'photos'        => bandpromo_media_target_dir('photos'),
    'video'         => bandpromo_media_target_dir('video'),
    'special'       => bandpromo_media_target_dir('special'),
];

$target = $_GET['target'] ?? '';
if (!isset($dirs[$target])) {
    echo json_encode(['error' => 'Unknown target: ' . htmlspecialchars($target)]);
    exit;
}

$includeBundled = isset($_GET['include_bundled']) && $_GET['include_bundled'] === '1';
$includeHidden = isset($_GET['include_hidden']) && $_GET['include_hidden'] === '1';

$dir = $dirs[$target];
$files = [];

function bandpromo_audio_master_info(string $root, string $filename): array {
    $master = bandpromo_find_audio_master($root, $filename);
    if ($master['exists']) {
        return $master;
    }

    $prepared = bandpromo_prepare_audio_master_from_original($root, $filename);
    if (!empty($prepared['prepared'])) {
        return bandpromo_find_audio_master($root, $filename);
    }

    return $master;
}

if (is_dir($dir)) {
    $allFiles = [];
    foreach (new DirectoryIterator($dir) as $f) {
        if ($f->isDot() || $f->isDir()) continue;

        $filename = $f->getFilename();
        if (strcasecmp($filename, 'desktop.ini') === 0) continue;

        $allFiles[] = [
            'name'     => $filename,
            'size'     => $f->getSize(),
            'modified' => $f->getMTime(),
            'origin'   => bandpromo_media_origin($filename),
            'hidden'   => bandpromo_media_is_hidden_for_install($target, $filename),
            'original_format' => strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)),
            'audio_master' => $target === 'audio' ? bandpromo_audio_master_info($root, $filename) : null,
        ];
    }

    foreach ($allFiles as $entry) {
        if ($entry['hidden'] && !$includeHidden) {
            continue;
        }

        if ($entry['origin'] === 'bundled-placeholder' && !$includeBundled && bandpromo_media_is_effectively_hidden_for_install($target, $entry['name'])) {
            continue;
        }

        $files[] = $entry;
    }

    usort($files, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
}

echo json_encode(['files' => $files, 'dir' => str_replace($root, '', $dir)], JSON_UNESCAPED_UNICODE);
