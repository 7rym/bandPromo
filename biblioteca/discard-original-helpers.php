<?php
declare(strict_types=1);

/**
 * Shared helpers for archival original presence / discard eligibility.
 * Linked originals (master on disk) and unregistered orphan intake are reclaimable.
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
 * Intake directories that may hold disposable leftover uploads.
 *
 * @return list<array{dir:string,family:string,target:string}>
 */
function bandpromo_orphan_intake_scan_dirs(string $root): array
{
    require_once __DIR__ . '/visual-master-helpers.php';
    require_once __DIR__ . '/sfx-helpers.php';

    $root = rtrim(str_replace('\\', '/', $root), '/');

    return [
        [
            'dir' => $root . '/media/audio/original',
            'family' => 'audio',
            'target' => 'audio',
        ],
        [
            'dir' => bandpromo_sfx_original_dir($root),
            'family' => 'sfx',
            'target' => 'sfx',
        ],
        [
            'dir' => bandpromo_visual_unified_original_dir($root),
            'family' => 'visual',
            'target' => 'special',
        ],
        [
            'dir' => $root . '/media/img/original',
            'family' => 'visual',
            'target' => 'illustrations',
        ],
        [
            'dir' => $root . '/media/photo/original',
            'family' => 'visual',
            'target' => 'photos',
        ],
        [
            'dir' => $root . '/media/video/original',
            'family' => 'visual',
            'target' => 'video',
        ],
        [
            'dir' => $root . '/media/special',
            'family' => 'visual',
            'target' => 'special',
        ],
    ];
}

/**
 * True when a basename is OS junk / placeholder, not reclaimable intake.
 */
function bandpromo_orphan_intake_is_ignored_basename(string $filename): bool
{
    require_once __DIR__ . '/media-library-state.php';

    $filename = basename(trim($filename));
    if ($filename === '' || strcasecmp($filename, 'desktop.ini') === 0) {
        return true;
    }
    $low = strtolower($filename);
    if (in_array($low, ['thumbs.db', '.ds_store', '.gitkeep'], true)) {
        return true;
    }

    return bandpromo_media_is_bundled_placeholder($filename);
}

/**
 * Basenames claimed as registry original_filename (any kind).
 *
 * @return array<string, true>
 */
function bandpromo_orphan_intake_claimed_original_basenames(string $root): array
{
    require_once __DIR__ . '/asset-registry.php';

    bandpromo_asset_registry_ensure_migrated($root);
    $registry = bandpromo_asset_load_registry($root);
    $claimed = [];
    foreach (($registry['assets'] ?? []) as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $name = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($name === '') {
            continue;
        }
        $claimed[strtolower($name)] = true;
    }

    return $claimed;
}

/**
 * Unregistered files under durable original / legacy intake dirs.
 * Used by Status → Storage reclaim and discard API.
 *
 * @return list<array{
 *   filename:string,
 *   path:string,
 *   rel_path:string,
 *   bytes:int,
 *   family:string,
 *   target:string,
 *   orphan:bool
 * }>
 */
function bandpromo_list_orphan_intake_files(string $root): array
{
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $rootPrefix = $root . '/';
    $claimed = bandpromo_orphan_intake_claimed_original_basenames($root);
    $items = [];
    $seenRel = [];

    foreach (bandpromo_orphan_intake_scan_dirs($root) as $spec) {
        $dir = str_replace('\\', '/', (string) ($spec['dir'] ?? ''));
        $family = (string) ($spec['family'] ?? '');
        $target = (string) ($spec['target'] ?? '');
        if ($dir === '' || $family === '' || $target === '' || !is_dir($dir)) {
            continue;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (bandpromo_orphan_intake_is_ignored_basename($entry)) {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }
            if (isset($claimed[strtolower($entry)])) {
                // Linked registry provenance — counted via archival status when master exists.
                continue;
            }

            $norm = str_replace('\\', '/', $path);
            $rel = str_starts_with($norm, $rootPrefix)
                ? substr($norm, strlen($rootPrefix))
                : ('media/' . basename($dir) . '/' . $entry);
            $rel = ltrim(str_replace('\\', '/', $rel), '/');
            if (isset($seenRel[$rel])) {
                continue;
            }
            $seenRel[$rel] = true;

            $size = @filesize($path);
            $items[] = [
                'filename' => $entry,
                'path' => $norm,
                'rel_path' => $rel,
                'bytes' => $size === false ? 0 : (int) $size,
                'family' => $family,
                'target' => $target,
                'orphan' => true,
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        $fa = (string) ($a['family'] ?? '');
        $fb = (string) ($b['family'] ?? '');
        if ($fa !== $fb) {
            return strnatcasecmp($fa, $fb);
        }

        return strnatcasecmp((string) ($a['filename'] ?? ''), (string) ($b['filename'] ?? ''));
    });

    return $items;
}

/**
 * True when $absPath is an allowed orphan intake file (under known dirs, unregistered).
 */
function bandpromo_orphan_intake_path_is_discardable(string $root, string $absPath): bool
{
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $norm = str_replace('\\', '/', $absPath);
    if ($norm === '' || !is_file($norm)) {
        return false;
    }

    $filename = basename($norm);
    if (bandpromo_orphan_intake_is_ignored_basename($filename)) {
        return false;
    }

    $claimed = bandpromo_orphan_intake_claimed_original_basenames($root);
    if (isset($claimed[strtolower($filename)])) {
        return false;
    }

    $parent = str_replace('\\', '/', dirname($norm));
    foreach (bandpromo_orphan_intake_scan_dirs($root) as $spec) {
        $dir = rtrim(str_replace('\\', '/', (string) ($spec['dir'] ?? '')), '/');
        if ($dir !== '' && strcasecmp($parent, $dir) === 0) {
            return true;
        }
    }

    return false;
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
