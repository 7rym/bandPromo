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
 * Ensure media/visual/original/{original_filename} exists (copy from legacy when needed).
 *
 * @return array{ok: bool, path: string, copied: bool, error?: string}
 */
function bandpromo_visual_relocate_original(string $root, array $asset): array
{
    $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
    if ($originalFilename === '') {
        return ['ok' => false, 'path' => '', 'copied' => false, 'error' => 'Missing original_filename'];
    }

    bandpromo_visual_ensure_tier_dirs($root);
    $dest = bandpromo_visual_unified_original_path($root, $originalFilename);
    if (is_file($dest)) {
        return ['ok' => true, 'path' => $dest, 'copied' => false];
    }

    $source = bandpromo_asset_visual_legacy_original_path($root, $asset);
    if ($source === '' || !is_file($source)) {
        return ['ok' => false, 'path' => $dest, 'copied' => false, 'error' => 'Legacy original missing'];
    }

    return bandpromo_visual_copy_file_idempotent($source, $dest);
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
        $copy = bandpromo_visual_copy_file_idempotent($working, $masterPath);
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
    $result = bandpromo_visual_materialize_master($root, $asset);
    $resolved = !empty($result['ok']) && is_array($result['asset'])
        ? $result['asset']
        : bandpromo_asset_lookup_by_id($root, $assetId);

    if (is_array($resolved)) {
        bandpromo_visual_heal_empty_display_for_asset($root, $assetId);
        $fresh = bandpromo_asset_lookup_by_id($root, $assetId);
        if (is_array($fresh)) {
            return $fresh;
        }
    }

    return $resolved;
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
    $originalFilename = basename(trim((string) ($asset['original_filename'] ?? '')));
    if ($originalFilename !== '') {
        $unified = bandpromo_visual_unified_original_path($root, $originalFilename);
        if ($unified !== '' && is_file($unified)) {
            @unlink($unified);
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
