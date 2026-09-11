<?php
declare(strict_types=1);

/**
 * Visual master materialization — mirrors audio-master-helpers for the visual family.
 * M2: copy legacy intake → media/visual/original/ + media/visual/master/ast_*.{ext}.
 * Legacy buckets remain until M4.
 */
require_once __DIR__ . '/asset-registry.php';

function bandpromo_visual_unified_original_dir(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'visual'
        . DIRECTORY_SEPARATOR . 'original';
}

function bandpromo_visual_master_dir(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'visual'
        . DIRECTORY_SEPARATOR . 'master';
}

function bandpromo_visual_ensure_tier_dirs(string $root): void
{
    foreach ([bandpromo_visual_unified_original_dir($root), bandpromo_visual_master_dir($root)] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create visual media directory: ' . $dir);
        }
    }
}

function bandpromo_visual_unified_original_path(string $root, string $originalFilename): string
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return '';
    }

    return bandpromo_visual_unified_original_dir($root) . DIRECTORY_SEPARATOR . $originalFilename;
}

function bandpromo_visual_master_path(string $root, string $assetId, string $format): string
{
    $assetId = trim($assetId);
    $format = strtolower(trim($format));
    if (!bandpromo_asset_is_asset_id($assetId) || $format === '') {
        return '';
    }

    return bandpromo_visual_master_dir($root) . DIRECTORY_SEPARATOR
        . bandpromo_asset_master_filename_for_ulid($assetId, $format);
}

/**
 * Resolve on-disk working bytes for a visual asset (master only).
 * Original is download/delete/provenance — not a working copy after materialize.
 */
function bandpromo_visual_working_path(string $root, array $asset): string
{
    $assetId = trim((string) ($asset['id'] ?? ''));
    $format = strtolower(trim((string) ($asset['master_format'] ?? pathinfo(
        (string) ($asset['original_filename'] ?? ''),
        PATHINFO_EXTENSION
    ))));
    $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
    $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));

    if ($assetId !== '' && $format !== '') {
        $masterPath = bandpromo_visual_master_path($root, $assetId, $format);
        if ($masterPath !== '' && is_file($masterPath)) {
            return $masterPath;
        }
    }

    if ($masterFilename !== '' && bandpromo_asset_is_asset_id((string) pathinfo($masterFilename, PATHINFO_FILENAME))) {
        $candidate = bandpromo_visual_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    // Master missing: allow delivery/materialize to use provenance original (unified or legacy).
    if ($originalFilename !== '') {
        $unified = bandpromo_visual_unified_original_path($root, $originalFilename);
        if ($unified !== '' && is_file($unified)) {
            return $unified;
        }
        $legacy = bandpromo_visual_find_legacy_original_file($root, $originalFilename);
        if ($legacy !== '') {
            return $legacy;
        }
        require_once __DIR__ . '/asset-registry.php';
        $bucketLegacy = bandpromo_asset_visual_legacy_original_path($root, $asset);
        if ($bucketLegacy !== '' && is_file($bucketLegacy)) {
            return $bucketLegacy;
        }
    }

    return '';
}

/**
 * Copy bytes if destination missing or different size (idempotent).
 *
 * @return array{ok: bool, copied: bool, path: string, error?: string}
 */
function bandpromo_visual_copy_file_idempotent(string $source, string $dest): array
{
    $source = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $source);
    $dest = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dest);
    if (!is_file($source)) {
        return ['ok' => false, 'copied' => false, 'path' => $dest, 'error' => 'Source missing'];
    }

    $dir = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'copied' => false, 'path' => $dest, 'error' => 'Could not create destination directory'];
    }

    if (is_file($dest)) {
        $srcSize = @filesize($source);
        $dstSize = @filesize($dest);
        if ($srcSize !== false && $dstSize !== false && (int) $srcSize === (int) $dstSize) {
            return ['ok' => true, 'copied' => false, 'path' => $dest];
        }
    }

    if (!@copy($source, $dest)) {
        return ['ok' => false, 'copied' => false, 'path' => $dest, 'error' => 'Copy failed'];
    }

    return ['ok' => true, 'copied' => true, 'path' => $dest];
}

/**
 * @return list<string>
 */
function bandpromo_visual_legacy_intake_dirs(string $root): array
{
    require_once __DIR__ . '/asset-registry.php';

    $dirs = [];
    foreach (['img', 'photo', 'video', 'special'] as $bucket) {
        $dir = bandpromo_asset_visual_legacy_intake_dir($root, $bucket);
        if ($dir !== '') {
            $dirs[] = $dir;
        }
    }

    return $dirs;
}

/**
 * Cheap gate: any legacy Visual intake folder still exists on disk.
 */
function bandpromo_visual_legacy_intake_present(string $root): bool
{
    foreach (bandpromo_visual_legacy_intake_dirs($root) as $dir) {
        if (is_dir($dir)) {
            return true;
        }
    }

    return false;
}

/**
 * Find a legacy intake original for this basename (any retired bucket).
 */
function bandpromo_visual_find_legacy_original_file(string $root, string $originalFilename): string
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return '';
    }

    foreach (bandpromo_visual_legacy_intake_dirs($root) as $dir) {
        $candidate = $dir . DIRECTORY_SEPARATOR . $originalFilename;
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Ensure media/visual/original/{original_filename} exists (copy from legacy when needed).
 *
 * @return array{ok: bool, path: string, copied: bool, error?: string}
 */
function bandpromo_visual_relocate_original(string $root, array $asset): array
{
    require_once __DIR__ . '/asset-registry.php';

    $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
    if ($originalFilename === '') {
        return ['ok' => false, 'path' => '', 'copied' => false, 'error' => 'Missing original_filename'];
    }

    bandpromo_visual_ensure_tier_dirs($root);
    $dest = bandpromo_visual_unified_original_path($root, $originalFilename);
    $source = bandpromo_asset_visual_legacy_original_path($root, $asset);
    if ($source === '' || !is_file($source)) {
        $source = bandpromo_visual_find_legacy_original_file($root, $originalFilename);
    }

    $copied = false;
    if ($source !== '' && is_file($source)) {
        $sourceReal = realpath($source) ?: $source;
        $destReal = is_file($dest) ? (realpath($dest) ?: $dest) : '';
        if ($destReal === '' || $sourceReal !== $destReal) {
            if (!is_file($dest)) {
                $result = bandpromo_visual_copy_file_idempotent($source, $dest);
                if (empty($result['ok'])) {
                    return $result;
                }
                $copied = !empty($result['copied']);
            } else {
                $sourceMtime = @filemtime($source);
                $destMtime = @filemtime($dest);
                if ($sourceMtime !== false && $destMtime !== false && (int) $sourceMtime > (int) $destMtime) {
                    $result = bandpromo_visual_copy_file_idempotent($source, $dest);
                    if (empty($result['ok'])) {
                        return $result;
                    }
                    $copied = !empty($result['copied']);
                }
            }
        }

        // Drop every leftover legacy intake copy for this basename.
        if (is_file($dest)) {
            $destReal = realpath($dest) ?: $dest;
            foreach (bandpromo_visual_legacy_intake_dirs($root) as $dir) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $originalFilename;
                if (!is_file($candidate)) {
                    continue;
                }
                $candidateReal = realpath($candidate) ?: $candidate;
                if ($candidateReal !== $destReal) {
                    @unlink($candidate);
                }
            }
        }

        return ['ok' => true, 'path' => $dest, 'copied' => $copied];
    }

    if (is_file($dest)) {
        return ['ok' => true, 'path' => $dest, 'copied' => false];
    }

    // Provenance missing but durable master still present — restore original from master.
    $fromMaster = bandpromo_visual_ensure_original_from_master($root, $asset);
    if (!empty($fromMaster['ok'])) {
        return [
            'ok' => true,
            'path' => (string) ($fromMaster['path'] ?? $dest),
            'copied' => !empty($fromMaster['copied']),
        ];
    }

    return ['ok' => false, 'path' => $dest, 'copied' => false, 'error' => 'Legacy original missing'];
}

/**
 * When media/visual/original/{name} is missing but the durable master exists,
 * copy master bytes back into the original tier. Never deletes the master.
 *
 * Stills: lossless byte copy. Video masters are often remuxed MKV — restoring
 * that file as the original re-establishes download/provenance even if the
 * pre-remux intake codec is gone.
 *
 * @return array{ok: bool, path: string, copied: bool, error?: string}
 */
function bandpromo_visual_ensure_original_from_master(string $root, array $asset): array
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = trim((string) ($asset['id'] ?? ''));
    $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
    $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
    if ($originalFilename === '') {
        $originalFilename = $masterFilename;
    }
    if ($originalFilename === '') {
        return ['ok' => false, 'path' => '', 'copied' => false, 'error' => 'Missing original/master filename'];
    }

    bandpromo_visual_ensure_tier_dirs($root);
    $dest = bandpromo_visual_unified_original_path($root, $originalFilename);
    if ($dest !== '' && is_file($dest)) {
        return ['ok' => true, 'path' => $dest, 'copied' => false];
    }

    $masterPath = '';
    $format = strtolower(trim((string) ($asset['master_format'] ?? pathinfo($masterFilename, PATHINFO_EXTENSION))));
    if ($assetId !== '' && $format !== '') {
        $candidate = bandpromo_visual_master_path($root, $assetId, $format);
        if ($candidate !== '' && is_file($candidate)) {
            $masterPath = $candidate;
        }
    }
    if ($masterPath === '' && $masterFilename !== '') {
        $candidate = bandpromo_visual_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;
        if (is_file($candidate)) {
            $masterPath = $candidate;
        }
    }
    if ($masterPath === '') {
        $working = bandpromo_visual_working_path($root, $asset);
        if ($working !== '' && is_file($working)) {
            // Only use working path when it is already under master/ (not delivery).
            $masterDir = realpath(bandpromo_visual_master_dir($root));
            $workingReal = realpath($working);
            if ($masterDir !== false && $workingReal !== false
                && str_starts_with($workingReal, $masterDir . DIRECTORY_SEPARATOR)
            ) {
                $masterPath = $working;
            }
        }
    }
    if ($masterPath === '' || !is_file($masterPath)) {
        return ['ok' => false, 'path' => $dest, 'copied' => false, 'error' => 'Master missing'];
    }

    // If the registry original name differs from the master basename (e.g. .mp4 vs .mkv),
    // prefer restoring under the master basename so extension matches the bytes.
    $masterBase = basename($masterPath);
    $destExt = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
    $masterExt = strtolower((string) pathinfo($masterBase, PATHINFO_EXTENSION));
    if ($destExt !== '' && $masterExt !== '' && $destExt !== $masterExt) {
        $originalFilename = $masterBase;
        $dest = bandpromo_visual_unified_original_path($root, $originalFilename);
        if ($dest !== '' && is_file($dest)) {
            if ($assetId !== '' && basename(trim((string) ($asset['original_filename'] ?? ''))) !== $originalFilename) {
                try {
                    bandpromo_asset_update_entry($root, $assetId, ['original_filename' => $originalFilename]);
                } catch (Throwable $ignored) {
                    // Copy still succeeds; registry can heal later.
                }
            }

            return ['ok' => true, 'path' => $dest, 'copied' => false];
        }
    }

    $result = bandpromo_visual_copy_file_idempotent($masterPath, $dest);
    if (empty($result['ok'])) {
        return [
            'ok' => false,
            'path' => $dest,
            'copied' => false,
            'error' => (string) ($result['error'] ?? 'Could not restore original from master'),
        ];
    }

    if ($assetId !== '' && basename(trim((string) ($asset['original_filename'] ?? ''))) !== $originalFilename) {
        try {
            bandpromo_asset_update_entry($root, $assetId, ['original_filename' => $originalFilename]);
        } catch (Throwable $ignored) {
            // Bytes restored; pointer can heal on next write.
        }
    }

    return [
        'ok' => true,
        'path' => (string) ($result['path'] ?? $dest),
        'copied' => !empty($result['copied']),
    ];
}

/**
 * Restore missing unified originals for every registered Visual that still has a master.
 *
 * @return array{restored: list<string>, failed: list<array{asset_id: string, error: string}>, changed: int}
 */
function bandpromo_visual_restore_missing_originals_from_masters(string $root): array
{
    require_once __DIR__ . '/asset-registry.php';

    $out = [
        'restored' => [],
        'failed' => [],
        'changed' => 0,
    ];
    $registry = bandpromo_asset_load_registry($root);
    foreach ($registry['assets'] as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($originalFilename === '') {
            $originalFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
        }
        if ($originalFilename === '') {
            continue;
        }
        $unified = bandpromo_visual_unified_original_path($root, $originalFilename);
        if ($unified !== '' && is_file($unified)) {
            continue;
        }
        $result = bandpromo_visual_ensure_original_from_master($root, $asset);
        if (!empty($result['ok']) && !empty($result['copied'])) {
            $out['restored'][] = (string) $assetId;
            $out['changed']++;
        } elseif (empty($result['ok'])) {
            $out['failed'][] = [
                'asset_id' => (string) $assetId,
                'error' => (string) ($result['error'] ?? 'Could not restore original'),
            ];
        }
    }

    return $out;
}

/**
 * One-shot: when any legacy Visual intake folder exists, relocate registered originals
 * into media/visual/original/ and remove leftover copies. Used by Publish + Site update.
 *
 * @return array{
 *   ran: bool,
 *   checked: int,
 *   relocated: int,
 *   folders_removed: int,
 *   warnings: list<string>,
 *   message: string
 * }
 */
function bandpromo_visual_relocate_all_legacy_originals(string $root): array
{
    require_once __DIR__ . '/asset-registry.php';

    if (!bandpromo_visual_legacy_intake_present($root)) {
        return [
            'ran' => false,
            'checked' => 0,
            'relocated' => 0,
            'folders_removed' => 0,
            'warnings' => [],
            'message' => 'No legacy Visual intake folders present.',
        ];
    }

    $checked = 0;
    $relocated = 0;
    $warnings = [];
    $registry = bandpromo_asset_load_registry($root);
    foreach (($registry['assets'] ?? []) as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
        if ($originalFilename === '') {
            continue;
        }
        $checked++;
        $hadLegacy = bandpromo_visual_find_legacy_original_file($root, $originalFilename) !== '';
        if (!$hadLegacy) {
            continue;
        }
        $result = bandpromo_visual_relocate_original($root, $asset);
        if (empty($result['ok'])) {
            $warnings[] = $originalFilename . ': ' . (string) ($result['error'] ?? 'relocate failed');
            continue;
        }
        if (bandpromo_visual_find_legacy_original_file($root, $originalFilename) === '') {
            $relocated++;
        }
    }

    $foldersRemoved = 0;
    foreach (bandpromo_visual_legacy_intake_dirs($root) as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $empty = true;
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || strcasecmp($entry, 'desktop.ini') === 0) {
                continue;
            }
            $empty = false;
            break;
        }
        if (!$empty) {
            continue;
        }
        if (@rmdir($dir)) {
            $foldersRemoved++;
            $parent = dirname($dir);
            $parentBase = strtolower(basename($parent));
            if (in_array($parentBase, ['img', 'photo', 'video', 'special'], true) && is_dir($parent)) {
                $parentEmpty = true;
                foreach (scandir($parent) ?: [] as $entry) {
                    if ($entry === '.' || $entry === '..' || strcasecmp($entry, 'desktop.ini') === 0) {
                        continue;
                    }
                    $parentEmpty = false;
                    break;
                }
                if ($parentEmpty) {
                    @rmdir($parent);
                }
            }
        }
    }

    return [
        'ran' => true,
        'checked' => $checked,
        'relocated' => $relocated,
        'folders_removed' => $foldersRemoved,
        'warnings' => $warnings,
        'message' => $relocated > 0
            ? ('Relocated ' . $relocated . ' Visual original(s) out of legacy intake.')
            : 'Legacy Visual intake folders checked; nothing left to relocate.',
    ];
}

/**
 * Ensure media/visual/master/ast_*.{ext} exists and registry master_filename is canonical.
 *
 * @return array{ok: bool, asset: ?array, copied: bool, changed_registry: bool, error?: string}
 */
function bandpromo_visual_materialize_master(string $root, array $asset): array
{
    $assetId = trim((string) ($asset['id'] ?? ''));
    if (!bandpromo_asset_is_asset_id($assetId)) {
        return ['ok' => false, 'asset' => null, 'copied' => false, 'changed_registry' => false, 'error' => 'Invalid asset id'];
    }

    $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
    $mediaType = strtolower(trim((string) ($asset['media_type'] ?? '')));
    $format = strtolower(trim((string) ($asset['master_format'] ?? '')));
    if ($format === '') {
        $format = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
    }
    if ($mediaType === 'video') {
        $format = 'mkv';
    }
    if ($format === '') {
        return ['ok' => false, 'asset' => null, 'copied' => false, 'changed_registry' => false, 'error' => 'Missing master format'];
    }

    $canonicalMaster = bandpromo_asset_master_filename_for_ulid($assetId, $format);
    $working = bandpromo_visual_working_path($root, $asset);
    if ($working === '') {
        $relocated = bandpromo_visual_relocate_original($root, $asset);
        if (empty($relocated['ok'])) {
            return [
                'ok' => false,
                'asset' => null,
                'copied' => false,
                'changed_registry' => false,
                'error' => (string) ($relocated['error'] ?? 'No source bytes'),
            ];
        }
        $working = (string) $relocated['path'];
    }

    // Prefer unified original as master source when available (unless working is already the MKV master).
    $unified = bandpromo_visual_unified_original_path($root, $originalFilename);
    $masterPathTarget = bandpromo_visual_master_path($root, $assetId, $format);
    $workingIsCanonicalMaster = ($masterPathTarget !== '' && $working !== '' && realpath($working) !== false
        && realpath($masterPathTarget) !== false
        && realpath($working) === realpath($masterPathTarget));
    if (!$workingIsCanonicalMaster && $unified !== '' && is_file($unified)) {
        $working = $unified;
    } elseif (!$workingIsCanonicalMaster) {
        bandpromo_visual_relocate_original($root, $asset);
        if (is_file($unified)) {
            $working = $unified;
        }
    }

    bandpromo_visual_ensure_tier_dirs($root);
    $masterPath = $masterPathTarget;

    $copied = false;
    if ($mediaType === 'video') {
        $alreadyMkv = is_file($masterPath)
            && basename(trim((string) ($asset['master_filename'] ?? ''))) === $canonicalMaster
            && strtolower(trim((string) ($asset['master_format'] ?? ''))) === 'mkv';
        if ($alreadyMkv) {
            $copied = false;
        } else {
            $remux = bandpromo_visual_remux_video_master_to_mkv($root, $working, $masterPath);
            if (empty($remux['ok'])) {
                return [
                    'ok' => false,
                    'asset' => null,
                    'copied' => false,
                    'changed_registry' => false,
                    'error' => (string) ($remux['error'] ?? 'Video master remux failed'),
                ];
            }
            $copied = !empty($remux['copied']);
        }
        // Drop stale non-MKV masters left from earlier materializations.
        foreach (['mp4', 'mov', 'webm', 'm4v'] as $staleExt) {
            $stale = bandpromo_visual_master_path($root, $assetId, $staleExt);
            if ($stale !== '' && is_file($stale)) {
                $staleReal = realpath($stale);
                $masterReal = is_file($masterPath) ? realpath($masterPath) : false;
                if ($staleReal !== false && ($masterReal === false || $staleReal !== $masterReal)) {
                    @unlink($stale);
                }
            }
        }
    } else {
        $sourceForMaster = ($unified !== '' && is_file($unified)) ? $unified : $working;
        if ($sourceForMaster === '') {
            return [
                'ok' => false,
                'asset' => null,
                'copied' => false,
                'changed_registry' => false,
                'error' => 'No source bytes',
            ];
        }
        $copy = bandpromo_visual_copy_file_idempotent($sourceForMaster, $masterPath);
        if (empty($copy['ok'])) {
            return [
                'ok' => false,
                'asset' => null,
                'copied' => false,
                'changed_registry' => false,
                'error' => (string) ($copy['error'] ?? 'Master copy failed'),
            ];
        }
        $copied = !empty($copy['copied']);
    }

    $changedRegistry = false;
    $currentMaster = basename(trim((string) ($asset['master_filename'] ?? '')));
    if ($currentMaster !== $canonicalMaster || strtolower(trim((string) ($asset['master_format'] ?? ''))) !== $format) {
        $asset = bandpromo_asset_update_entry($root, $assetId, [
            'master_filename' => $canonicalMaster,
            'master_format' => $format,
        ]);
        $changedRegistry = true;
    }

    return [
        'ok' => true,
        'asset' => $asset,
        'copied' => $copied,
        'changed_registry' => $changedRegistry,
    ];
}

/**
 * Remux video intake/master to Matroska with stream copy (no re-encode).
 */
function bandpromo_visual_remux_video_master_to_mkv(string $root, string $sourcePath, string $destPath): array
{
    $sourcePath = (string) $sourcePath;
    $destPath = (string) $destPath;
    if ($sourcePath === '' || !is_file($sourcePath)) {
        return ['ok' => false, 'copied' => false, 'error' => 'Video source missing for MKV remux'];
    }
    if ($destPath === '') {
        return ['ok' => false, 'copied' => false, 'error' => 'Video master destination missing'];
    }

    $sourceReal = realpath($sourcePath) ?: $sourcePath;
    $destReal = is_file($destPath) ? (realpath($destPath) ?: $destPath) : $destPath;
    if (is_file($destPath) && $sourceReal === $destReal) {
        return ['ok' => true, 'copied' => false];
    }

    // Same-bytes short-circuit when already MKV and destinations match size+mtime closely.
    $sourceExt = strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION));
    if ($sourceExt === 'mkv' && is_file($destPath)
        && filesize($sourcePath) === filesize($destPath)
        && abs((int) filemtime($sourcePath) - (int) filemtime($destPath)) < 2
    ) {
        return ['ok' => true, 'copied' => false];
    }

    require_once __DIR__ . '/audio-master-helpers.php';
    $ffmpeg = bandpromo_resolve_ffmpeg_binary($root);
    if ($ffmpeg === '') {
        return [
            'ok' => false,
            'copied' => false,
            'error' => 'ffmpeg is required to remux video masters to MKV. Install ffmpeg or place it in scripts/bin/.',
        ];
    }

    $destDir = dirname($destPath);
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return ['ok' => false, 'copied' => false, 'error' => 'Could not create visual master directory'];
    }

    $tmpPath = $destPath . '.tmp.' . bin2hex(random_bytes(4)) . '.mkv';
    $cmd = [
        $ffmpeg,
        '-y',
        '-hide_banner',
        '-loglevel', 'error',
        '-i', $sourcePath,
        '-map', '0',
        '-c', 'copy',
        '-sn',
        $tmpPath,
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $process = proc_open($cmd, $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        return ['ok' => false, 'copied' => false, 'error' => 'Could not start ffmpeg for video master remux'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);

    if ($code !== 0 || !is_file($tmpPath) || filesize($tmpPath) < 1) {
        @unlink($tmpPath);
        $detail = trim((string) $stderr);
        if ($detail === '') {
            $detail = trim((string) $stdout);
        }

        return [
            'ok' => false,
            'copied' => false,
            'error' => 'ffmpeg remux to MKV failed'
                . ($detail !== '' ? ': ' . $detail : ''),
        ];
    }

    if (is_file($destPath)) {
        @unlink($destPath);
    }
    if (!@rename($tmpPath, $destPath)) {
        if (!@copy($tmpPath, $destPath)) {
            @unlink($tmpPath);

            return ['ok' => false, 'copied' => false, 'error' => 'Could not finalize MKV master'];
        }
        @unlink($tmpPath);
    }

    return ['ok' => true, 'copied' => true];
}

/**
 * After visual register/upload: relocate original + materialize master.
 * Heals empty registry display from master embeds (EXIF/XMP or Matroska tags).
 */
function bandpromo_visual_ensure_tiers_for_asset(string $root, string $assetId): ?array
{
    $asset = bandpromo_asset_lookup_by_id($root, $assetId);
    if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
        return null;
    }

    bandpromo_visual_relocate_original($root, $asset);
    $asset = bandpromo_asset_lookup_by_id($root, $assetId) ?? $asset;
    bandpromo_visual_ensure_original_from_master($root, $asset);
    $asset = bandpromo_asset_lookup_by_id($root, $assetId) ?? $asset;
    $result = bandpromo_visual_materialize_master($root, $asset);
    $resolved = !empty($result['ok']) && is_array($result['asset'])
        ? $result['asset']
        : bandpromo_asset_lookup_by_id($root, $assetId);

    if (is_array($resolved)) {
        // Master materialize may have created/updated the master — restore original again if still missing.
        bandpromo_visual_ensure_original_from_master($root, $resolved);
        bandpromo_visual_heal_empty_display_for_asset($root, $assetId);
        $fresh = bandpromo_asset_lookup_by_id($root, $assetId);
        if (is_array($fresh)) {
            return $fresh;
        }
    }

    return $resolved;
}

/**
 * Invent a human title from the original filename stem (not ast_{ULID}).
 */
function bandpromo_visual_invent_title_from_asset(array $asset): string
{
    foreach (['original_filename', 'master_filename'] as $field) {
        $raw = basename(trim((string) ($asset[$field] ?? '')));
        if ($raw === '') {
            continue;
        }
        $stem = pathinfo($raw, PATHINFO_FILENAME);
        $stem = is_string($stem) ? trim($stem) : '';
        if ($stem === '' || bandpromo_asset_is_asset_id($stem)) {
            continue;
        }
        $cleaned = trim((string) preg_replace('/[_\-]+/', ' ', $stem));
        if ($cleaned !== '') {
            return $cleaned;
        }
    }

    return strtolower(trim((string) ($asset['media_type'] ?? ''))) === 'video'
        ? 'Untitled video'
        : 'Untitled image';
}

function bandpromo_visual_invent_captured_at_from_path(string $path): string
{
    if ($path === '' || !is_file($path)) {
        return '';
    }
    $mtime = @filemtime($path);
    if ($mtime === false || $mtime < 1) {
        return '';
    }

    return gmdate('Y-m-d', $mtime);
}

/**
 * Fill empty registry title/captured_at without touching master bytes.
 * Bulk Apply uses this so shared-host PHP does not remux every video via ffmpeg.
 *
 * @return list<string> Healed asset ids
 */
function bandpromo_visual_invent_empty_registry_displays(string $root): array
{
    $registry = bandpromo_asset_load_registry($root);
    $assets = is_array($registry['assets'] ?? null) ? $registry['assets'] : [];
    $healed = [];
    $now = gmdate('Y-m-d\TH:i:s\Z');

    foreach ($assets as $assetId => $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'visual') {
            continue;
        }
        $display = bandpromo_asset_read_visual_display($asset);
        $changed = false;
        if ($display['title'] === '') {
            $display['title'] = bandpromo_visual_invent_title_from_asset($asset);
            $changed = true;
        }
        if ($display['captured_at'] === '') {
            $path = bandpromo_visual_working_path($root, $asset);
            $invented = bandpromo_visual_invent_captured_at_from_path($path);
            if ($invented !== '') {
                $display['captured_at'] = $invented;
                $changed = true;
            }
        }
        if (!$changed) {
            continue;
        }
        $display['synced_at'] = $now;
        $assets[$assetId]['display'] = $display;
        $healed[] = (string) $assetId;
    }

    if ($healed === []) {
        return [];
    }

    $registry['assets'] = $assets;
    bandpromo_asset_write_registry($root, $registry);

    return $healed;
}

/**
 * Best-effort: fill empty display fields from the visual master embeds for one asset.
 */
function bandpromo_visual_heal_empty_display_for_asset(string $root, string $assetId): void
{
    $assetId = trim($assetId);
    if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
        return;
    }

    try {
        require_once __DIR__ . '/light-build-tasks.php';
        bandpromo_run_light_json_task('scripts/visualMasterMetadata.py', [
            'action' => 'heal_empty',
            'asset_id' => $assetId,
        ]);
    } catch (Throwable $throwable) {
        // Heal is best-effort; materialize already succeeded.
    }
}

/**
 * Remove unified original + master files for a visual asset (best-effort).
 */
function bandpromo_visual_delete_tier_files(string $root, array $asset): void
{
    require_once __DIR__ . '/asset-registry.php';

    $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
    if ($originalFilename !== '') {
        $unified = bandpromo_visual_unified_original_path($root, $originalFilename);
        $paths = [];
        if ($unified !== '') {
            $paths[] = $unified;
        }
        $legacy = bandpromo_asset_visual_legacy_original_path($root, $asset);
        if ($legacy !== '') {
            $paths[] = $legacy;
        }
        foreach (['img', 'photo', 'video', 'special'] as $bucket) {
            $dir = bandpromo_asset_visual_legacy_intake_dir($root, $bucket);
            if ($dir !== '') {
                $paths[] = $dir . DIRECTORY_SEPARATOR . $originalFilename;
            }
        }
        $seen = [];
        foreach ($paths as $path) {
            $real = is_file($path) ? (realpath($path) ?: $path) : '';
            if ($real === '' || isset($seen[$real])) {
                continue;
            }
            $seen[$real] = true;
            @unlink($path);
        }
    }

    $assetId = trim((string) ($asset['id'] ?? ''));
    $format = strtolower(trim((string) ($asset['master_format'] ?? pathinfo($originalFilename, PATHINFO_EXTENSION))));
    if ($assetId !== '' && $format !== '') {
        $masterPath = bandpromo_visual_master_path($root, $assetId, $format);
        if ($masterPath !== '' && is_file($masterPath)) {
            @unlink($masterPath);
        }
    }

    $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
    if ($masterFilename !== '' && bandpromo_asset_is_asset_id((string) pathinfo($masterFilename, PATHINFO_FILENAME))) {
        $candidate = bandpromo_visual_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    }
}
