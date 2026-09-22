<?php
declare(strict_types=1);

/**
 * Storage report helpers for System → Status → Storage.
 * Disk use + reclaimable archival originals (masters must exist).
 */

require_once __DIR__ . '/environment-report-helpers.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/discard-original-helpers.php';
require_once __DIR__ . '/demo-catalog-state.php';
require_once __DIR__ . '/sfx-helpers.php';

/**
 * Recursively sum file sizes under $dir with soft caps.
 *
 * @return array{bytes:int,files:int,partial:bool}
 */
function bandpromo_storage_dir_size(string $dir, float $deadline, int $maxFiles = 80000): array
{
    $bytes = 0;
    $files = 0;
    $partial = false;

    if (!is_dir($dir)) {
        return ['bytes' => 0, 'files' => 0, 'partial' => false];
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $fileInfo) {
            if (microtime(true) >= $deadline || $files >= $maxFiles) {
                $partial = true;
                break;
            }
            if (!$fileInfo->isFile()) {
                continue;
            }
            $files++;
            $bytes += (int) $fileInfo->getSize();
        }
    } catch (Throwable $throwable) {
        $partial = true;
    }

    return ['bytes' => $bytes, 'files' => $files, 'partial' => $partial];
}

/**
 * Discard API target for a registry asset.
 */
function bandpromo_storage_discard_target_for_asset(array $asset): string
{
    $kind = strtolower(trim((string) ($asset['kind'] ?? '')));
    if ($kind === 'audio') {
        return 'audio';
    }
    if ($kind === 'sfx') {
        return 'sfx';
    }
    if ($kind === 'visual') {
        $target = bandpromo_asset_files_index_target_for_intake_bucket(
            (string) ($asset['intake_bucket'] ?? '')
        );
        if ($target === '') {
            return 'special';
        }

        return $target;
    }

    return '';
}

/**
 * Family key for operator summary (audio / visual / sfx).
 */
function bandpromo_storage_family_for_asset(array $asset): string
{
    $kind = strtolower(trim((string) ($asset['kind'] ?? '')));
    if ($kind === 'audio' || $kind === 'sfx') {
        return $kind;
    }
    if ($kind === 'visual') {
        return 'visual';
    }

    return '';
}

/**
 * @return array<string, mixed>
 */
function bandpromo_storage_collect_report(string $root, bool $includeDeveloperSample = false): array
{
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $deadline = microtime(true) + 4.0;

    $diskTotal = @disk_total_space($root);
    $diskFree = @disk_free_space($root);
    $disk = [
        'total_bytes' => is_float($diskTotal) || is_int($diskTotal) ? (int) $diskTotal : null,
        'free_bytes' => is_float($diskFree) || is_int($diskFree) ? (int) $diskFree : null,
        'total_label' => bandpromo_environment_format_bytes($diskTotal),
        'free_label' => bandpromo_environment_format_bytes($diskFree),
        'note' => 'Account/install visibility — may be a quota, not the whole server disk.',
    ];

    $tierDirs = [
        'audio' => [
            'original' => $root . '/media/audio/original',
            'master' => $root . '/media/audio/master',
            'delivery' => $root . '/media/audio/optimal',
        ],
        'visual' => [
            'original' => $root . '/media/visual/original',
            'master' => $root . '/media/visual/master',
            'delivery' => $root . '/media/visual/delivery',
        ],
        'sfx' => [
            'original' => bandpromo_sfx_original_dir($root),
            'master' => bandpromo_sfx_master_dir($root),
            'delivery' => bandpromo_sfx_optimal_dir($root),
        ],
    ];

    $mediaByFamily = [];
    $mediaBytes = 0;
    $partial = false;
    foreach ($tierDirs as $family => $tiers) {
        $familyRow = [
            'original_bytes' => 0,
            'master_bytes' => 0,
            'delivery_bytes' => 0,
            'total_bytes' => 0,
        ];
        foreach ($tiers as $tier => $path) {
            $size = bandpromo_storage_dir_size($path, $deadline);
            if (!empty($size['partial'])) {
                $partial = true;
            }
            $key = $tier . '_bytes';
            $familyRow[$key] = (int) $size['bytes'];
            $familyRow['total_bytes'] += (int) $size['bytes'];
            $mediaBytes += (int) $size['bytes'];
        }
        $mediaByFamily[$family] = $familyRow;
    }

    // Leftover legacy intake (img/photo/video/special) if still present.
    $legacyBytes = 0;
    foreach (['img', 'photo', 'video', 'special'] as $legacy) {
        $size = bandpromo_storage_dir_size($root . '/media/' . $legacy, $deadline);
        if (!empty($size['partial'])) {
            $partial = true;
        }
        $legacyBytes += (int) $size['bytes'];
    }
    $mediaBytes += $legacyBytes;

    $iconsSize = bandpromo_storage_dir_size($root . '/media/icons', $deadline);
    if (!empty($iconsSize['partial'])) {
        $partial = true;
    }
    $mediaBytes += (int) $iconsSize['bytes'];

    $backupsSize = bandpromo_storage_dir_size($root . '/backups', $deadline);
    if (!empty($backupsSize['partial'])) {
        $partial = true;
    }
    $dataSize = bandpromo_storage_dir_size($root . '/data', $deadline);
    if (!empty($dataSize['partial'])) {
        $partial = true;
    }

    $install = [
        'media_bytes' => $mediaBytes,
        'media_label' => bandpromo_environment_format_bytes($mediaBytes),
        'media' => $mediaByFamily,
        'media_legacy_bytes' => $legacyBytes,
        'media_icons_bytes' => (int) $iconsSize['bytes'],
        'backups_bytes' => (int) $backupsSize['bytes'],
        'backups_label' => bandpromo_environment_format_bytes($backupsSize['bytes']),
        'data_bytes' => (int) $dataSize['bytes'],
        'data_label' => bandpromo_environment_format_bytes($dataSize['bytes']),
        'approx_total_bytes' => $mediaBytes + (int) $backupsSize['bytes'] + (int) $dataSize['bytes'],
        'approx_total_label' => bandpromo_environment_format_bytes(
            $mediaBytes + (int) $backupsSize['bytes'] + (int) $dataSize['bytes']
        ),
        'partial' => $partial,
    ];

    bandpromo_asset_registry_ensure_migrated($root);
    $registry = bandpromo_asset_load_registry($root);
    $demoAssetSet = bandpromo_demo_campaign_asset_set($root);

    $byFamily = [
        'audio' => ['count' => 0, 'bytes' => 0],
        'visual' => ['count' => 0, 'bytes' => 0],
        'sfx' => ['count' => 0, 'bytes' => 0],
    ];
    $items = [];
    $excludedLocked = 0;
    $sample = [];

    foreach (($registry['assets'] ?? []) as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $family = bandpromo_storage_family_for_asset($asset);
        $target = bandpromo_storage_discard_target_for_asset($asset);
        if ($family === '' || $target === '') {
            continue;
        }

        $assetId = trim((string) ($asset['id'] ?? ''));
        $originalName = basename(trim((string) ($asset['original_filename'] ?? '')));
        $masterName = basename(trim((string) ($asset['master_filename'] ?? '')));
        $safe = $masterName !== '' ? $masterName : $originalName;
        if ($safe === '') {
            continue;
        }

        $status = bandpromo_media_archival_status($root, $target, [
            'name' => $safe,
            'original_filename' => $originalName !== '' ? $originalName : $safe,
            'audio_master' => [
                'exists' => false,
                'filename' => $masterName,
            ],
        ], $asset);

        if (empty($status['has_original']) || empty($status['has_master'])) {
            continue;
        }

        $lockCandidates = array_values(array_unique(array_filter([
            $target . '|' . $safe,
            $originalName !== '' ? $target . '|' . $originalName : '',
            $assetId !== '' ? $target . '|' . $assetId : '',
            $masterName !== '' ? $target . '|' . $masterName : '',
            $assetId,
        ])));
        $locked = false;
        foreach ($lockCandidates as $lockKey) {
            if (bandpromo_asset_is_in_locked_release($root, $lockKey, $demoAssetSet)) {
                $locked = true;
                break;
            }
        }
        if ($locked) {
            $excludedLocked++;
            continue;
        }

        $bytes = (int) ($status['original_bytes'] ?? 0);
        $byFamily[$family]['count']++;
        $byFamily[$family]['bytes'] += $bytes;
        $row = [
            'target' => $target,
            'filename' => $safe,
            'asset_id' => $assetId,
            'family' => $family,
            'bytes' => $bytes,
        ];
        $items[] = $row;
        if ($includeDeveloperSample && count($sample) < 8) {
            $sample[] = [
                'family' => $family,
                'filename' => $originalName !== '' ? $originalName : $safe,
                'bytes' => $bytes,
                'bytes_label' => bandpromo_environment_format_bytes($bytes),
            ];
        }
    }

    $totalCount = 0;
    $totalBytes = 0;
    foreach ($byFamily as $row) {
        $totalCount += (int) $row['count'];
        $totalBytes += (int) $row['bytes'];
    }

    $reclaimable = [
        'count' => $totalCount,
        'bytes' => $totalBytes,
        'bytes_label' => bandpromo_environment_format_bytes($totalBytes),
        'by_family' => $byFamily,
        'excluded_locked_demo' => $excludedLocked,
        'items' => $items,
        'note' => 'Only archival uploads with a master on disk. Locked demo originals are excluded.',
    ];
    if ($includeDeveloperSample) {
        $reclaimable['sample'] = $sample;
    }

    return [
        'disk' => $disk,
        'install' => $install,
        'reclaimable' => $reclaimable,
        'generated_at' => gmdate('c'),
    ];
}
