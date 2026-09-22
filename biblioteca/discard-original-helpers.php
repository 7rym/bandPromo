<?php
declare(strict_types=1);

/**
 * Shared helpers for archival original presence / discard eligibility.
 */

/**
 * Candidate absolute paths for an archival original (product + legacy dual-read).
 *
 * @return list<string>
 */
function bandpromo_discard_original_candidate_paths(
    string $root,
    string $target,
    string $originalFilename
): array {
    require_once __DIR__ . '/visual-master-helpers.php';
    require_once __DIR__ . '/sfx-helpers.php';

    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return [];
    }

    $paths = [];
    if ($target === 'audio') {
        $paths[] = $root . '/media/audio/original/' . $originalFilename;
    } elseif ($target === 'sfx') {
        $paths[] = bandpromo_sfx_original_dir($root) . DIRECTORY_SEPARATOR . $originalFilename;
    } else {
        $paths[] = bandpromo_visual_unified_original_path($root, $originalFilename);
        $paths[] = $root . '/media/img/original/' . $originalFilename;
        $paths[] = $root . '/media/photo/original/' . $originalFilename;
        $paths[] = $root . '/media/video/original/' . $originalFilename;
        $paths[] = $root . '/media/special/' . $originalFilename;
    }

    $out = [];
    $seen = [];
    foreach ($paths as $path) {
        if ($path === '' || isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;
        $out[] = $path;
    }

    return $out;
}

/**
 * @return array{has_original:bool,has_master:bool,original_bytes:int,can_discard_original:bool}
 */
function bandpromo_media_archival_status(
    string $root,
    string $target,
    array $entry,
    ?array $asset = null
): array {
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/visual-master-helpers.php';
    require_once __DIR__ . '/sfx-helpers.php';

    $filename = basename(trim((string) ($entry['name'] ?? '')));
    $originalName = basename(trim((string) ($entry['original_filename'] ?? '')));
    if ($originalName === '') {
        $originalName = $filename;
    }
    $masterName = '';
    if (is_array($asset)) {
        $masterName = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($originalName === $filename || $originalName === '') {
            $fromAsset = basename(trim((string) ($asset['original_filename'] ?? '')));
            if ($fromAsset !== '') {
                $originalName = $fromAsset;
            }
        }
    } elseif ($target === 'audio' && is_array($entry['audio_master'] ?? null)) {
        $masterName = basename(trim((string) ($entry['audio_master']['filename'] ?? '')));
    }

    $hasMaster = false;
    if ($target === 'audio') {
        if ($masterName !== '' && is_file($root . '/media/audio/master/' . $masterName)) {
            $hasMaster = true;
        } elseif (!empty($entry['audio_master']['exists'])) {
            $hasMaster = true;
        }
    } elseif ($target === 'sfx') {
        if (is_array($asset) && ($asset['kind'] ?? '') === 'sfx') {
            $m = basename(trim((string) ($asset['master_filename'] ?? '')));
            if ($m !== '' && is_file(bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $m)) {
                $hasMaster = true;
            }
        }
    } elseif (is_array($asset) && ($asset['kind'] ?? '') === 'visual') {
        $working = bandpromo_visual_working_path($root, $asset);
        $hasMaster = $working !== '' && is_file($working);
    }

    $originalBytes = 0;
    $hasOriginal = false;
    foreach (bandpromo_discard_original_candidate_paths($root, $target, $originalName) as $path) {
        if (!is_file($path)) {
            continue;
        }
        $hasOriginal = true;
        $originalBytes += (int) filesize($path);
    }

    return [
        'has_original' => $hasOriginal,
        'has_master' => $hasMaster,
        'original_bytes' => $originalBytes,
        'can_discard_original' => $hasOriginal && $hasMaster,
    ];
}
