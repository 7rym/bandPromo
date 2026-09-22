<?php
declare(strict_types=1);

/**
 * Sound effects pool — brand UI audio (welcome, login, future interaction clips).
 * Three-tier like catalogue audio: original → master (ast_*) → delivery MP3 (optimal).
 * Not catalogue music: no release membership or playlist coupling.
 */
require_once __DIR__ . '/json-file-helpers.php';

function bandpromo_sfx_original_dir(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'sfx'
        . DIRECTORY_SEPARATOR . 'original';
}

function bandpromo_sfx_master_dir(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'sfx'
        . DIRECTORY_SEPARATOR . 'master';
}

function bandpromo_sfx_optimal_dir(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'sfx'
        . DIRECTORY_SEPARATOR . 'optimal';
}

function bandpromo_sfx_ensure_dir(string $root): void
{
    bandpromo_sfx_ensure_tier_dirs($root);
}

function bandpromo_sfx_ensure_tier_dirs(string $root): void
{
    // Durable product dirs only — intake is temp/media-intake (original/ is leftover reclaim).
    foreach ([
        bandpromo_sfx_master_dir($root),
        bandpromo_sfx_optimal_dir($root),
    ] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create sound-effects media directory: ' . $dir);
        }
    }
}

function bandpromo_sfx_is_audio_filename(string $filename): bool
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    return in_array($ext, ['flac', 'mp3', 'wav', 'ogg', 'm4a'], true);
}

/**
 * Legacy helper removed (T6): original-tier web path is not a public URL.
 * Prefer bandpromo_sfx_resolve_play_url / bandpromo_sfx_delivery_web_path.
 */

function bandpromo_sfx_master_web_path(string $masterFilename): string
{
    $masterFilename = basename(trim($masterFilename));
    if ($masterFilename === '') {
        return '';
    }

    return '/media/sfx/master/' . $masterFilename;
}

function bandpromo_sfx_delivery_web_path(string $assetIdOrStem): string
{
    $stem = basename(trim($assetIdOrStem));
    $stem = (string) pathinfo($stem, PATHINFO_FILENAME);
    if ($stem === '') {
        return '';
    }

    return '/media/sfx/optimal/' . $stem . '.mp3';
}

function bandpromo_sfx_master_absolute(string $root, string $assetId, string $format): string
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = trim($assetId);
    $format = strtolower(trim($format));
    if ($assetId === '' || $format === '' || !bandpromo_asset_is_asset_id($assetId)) {
        return '';
    }

    return bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR
        . bandpromo_asset_master_filename_for_ulid($assetId, $format);
}

function bandpromo_sfx_delivery_absolute(string $root, string $assetId): string
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = trim($assetId);
    if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
        return '';
    }

    return bandpromo_sfx_optimal_dir($root) . DIRECTORY_SEPARATOR . $assetId . '.mp3';
}

/**
 * Public playback URL: optimal MP3 delivery only.
 *
 * @param array<string, mixed> $asset
 */
function bandpromo_sfx_resolve_play_url(string $root, array $asset): string
{
    $assetId = trim((string) ($asset['id'] ?? ''));
    if ($assetId === '') {
        return '';
    }

    $delivery = bandpromo_sfx_delivery_absolute($root, $assetId);
    if ($delivery !== '' && is_file($delivery)) {
        return bandpromo_sfx_delivery_web_path($assetId);
    }

    return '';
}

/**
 * Resolve a stored shell path (any sfx tier) to the best public playback URL.
 */
function bandpromo_sfx_resolve_stored_path(string $root, string $webPath): string
{
    require_once __DIR__ . '/asset-registry.php';

    $webPath = trim(str_replace('\\', '/', $webPath));
    if ($webPath === '') {
        return '';
    }
    if ($webPath !== '' && $webPath[0] !== '/') {
        $webPath = '/' . $webPath;
    }

    if (preg_match('#^/media/sfx/(original|master|optimal)/([^/]+)$#i', $webPath, $matches) === 1) {
        $tier = strtolower((string) ($matches[1] ?? ''));
        $filename = basename((string) ($matches[2] ?? ''));
        $asset = bandpromo_asset_lookup_by_original_filename($root, $filename);
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'sfx') {
            $asset = bandpromo_asset_lookup_by_master_filename($root, $filename);
        }
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'sfx') {
            $stem = (string) pathinfo($filename, PATHINFO_FILENAME);
            if (bandpromo_asset_is_asset_id($stem)) {
                $asset = bandpromo_asset_lookup_by_id($root, $stem);
            }
        }
        if (is_array($asset) && ($asset['kind'] ?? '') === 'sfx') {
            $resolved = bandpromo_sfx_resolve_play_url($root, $asset);
            if ($resolved !== '') {
                return $resolved;
            }
            // Delivery pending — do not fall through to original/master public URLs.
            return '';
        }
        if ($tier === 'original' || $tier === 'master') {
            return '';
        }
    }

    return $webPath;
}

/**
 * Copy/convert original → media/sfx/master/ast_*.{ext} and update registry master_filename.
 * WAV originals become tagged FLAC masters (same policy as catalogue audio).
 *
 * @param array<string, mixed> $asset
 * @return array{ok: bool, asset: array<string, mixed>, path?: string, warning?: string}
 */
function bandpromo_sfx_materialize_master(string $root, array $asset): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/audio-master-helpers.php';
    require_once __DIR__ . '/media-intake-helpers.php';

    $assetId = trim((string) ($asset['id'] ?? ''));
    $originalFilename = basename((string) ($asset['original_filename'] ?? ''));
    if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
        return ['ok' => false, 'asset' => $asset, 'warning' => 'Invalid sound-effect asset.'];
    }

    bandpromo_sfx_ensure_tier_dirs($root);
    $existingMaster = basename((string) ($asset['master_filename'] ?? ''));
    $existingMasterPath = $existingMaster !== ''
        ? bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $existingMaster
        : '';
    $source = $originalFilename !== ''
        ? bandpromo_intake_resolve_source($root, 'sfx', $originalFilename)
        : '';
    // PRP / masters-only installs have no original; encode delivery from the master.
    if (($source === '' || !is_file($source)) && $existingMasterPath !== '' && is_file($existingMasterPath)) {
        return ['ok' => true, 'asset' => $asset, 'path' => $existingMasterPath];
    }
    if ($originalFilename === '') {
        return ['ok' => false, 'asset' => $asset, 'warning' => 'Invalid sound-effect asset.'];
    }

    $sourceFormat = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
    $format = strtolower(trim((string) ($asset['master_format'] ?? '')));
    if ($format === '') {
        $format = $sourceFormat;
    }
    // Preferred master: WAV → FLAC; otherwise keep intake codec.
    if ($sourceFormat === 'wav' || $format === 'wav') {
        $format = 'flac';
    }
    if ($format === '') {
        return ['ok' => false, 'asset' => $asset, 'warning' => 'Missing sound-effect format.'];
    }

    if ($source === '' || !is_file($source)) {
        return ['ok' => false, 'asset' => $asset, 'warning' => 'Sound-effect original missing: ' . $originalFilename];
    }

    $masterFilename = bandpromo_asset_master_filename_for_ulid($assetId, $format);
    $dest = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;

    if (!is_file($dest)) {
        if ($sourceFormat === 'wav' && $format === 'flac') {
            $conversion = bandpromo_convert_wav_to_flac(
                $root,
                $source,
                $dest,
                'Could not convert sound-effect WAV to FLAC master'
            );
            if (empty($conversion['ok'])) {
                return [
                    'ok' => false,
                    'asset' => $asset,
                    'warning' => (string) ($conversion['warning'] ?? 'WAV-to-FLAC conversion failed.'),
                ];
            }
        } elseif (!@copy($source, $dest)) {
            return ['ok' => false, 'asset' => $asset, 'warning' => 'Could not write sound-effect master.'];
        }
    }

    // Drop stale WAV masters when the preferred master is FLAC.
    if ($format === 'flac') {
        $staleWav = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR
            . bandpromo_asset_master_filename_for_ulid($assetId, 'wav');
        if (is_file($staleWav)) {
            $staleReal = realpath($staleWav);
            $destReal = is_file($dest) ? realpath($dest) : false;
            if ($staleReal !== false && ($destReal === false || $staleReal !== $destReal)) {
                @unlink($staleWav);
            }
        }
    }

    $updated = $asset;
    if ((string) ($asset['master_filename'] ?? '') !== $masterFilename
        || (string) ($asset['master_format'] ?? '') !== $format
    ) {
        $updated = bandpromo_asset_update_entry($root, $assetId, [
            'master_filename' => $masterFilename,
            'master_format' => $format,
        ]);
    }

    if (is_file($dest)) {
        bandpromo_intake_discard_after_master($root, 'sfx', $originalFilename, $dest);
    }

    return ['ok' => true, 'asset' => $updated, 'path' => $dest];
}

/**
 * Ensure a portable master exists for PCF/PBF packing and Files listing.
 * Prefer original→master; if only optimal delivery remains, promote it to master.
 *
 * @param array<string, mixed> $asset
 * @return array{ok: bool, asset: array<string, mixed>, path?: string, warning?: string}
 */
function bandpromo_sfx_ensure_portable_master(string $root, array $asset): array
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = trim((string) ($asset['id'] ?? ''));
    if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId) || ($asset['kind'] ?? '') !== 'sfx') {
        return ['ok' => false, 'asset' => $asset, 'warning' => 'Invalid sound-effect asset.'];
    }

    $materialized = bandpromo_sfx_materialize_master($root, $asset);
    if (!empty($materialized['ok'])) {
        $path = (string) ($materialized['path'] ?? '');
        if ($path !== '' && is_file($path)) {
            return $materialized;
        }
        $updated = is_array($materialized['asset'] ?? null) ? $materialized['asset'] : $asset;
        $master = basename((string) ($updated['master_filename'] ?? ''));
        if ($master !== '') {
            $masterPath = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $master;
            if (is_file($masterPath)) {
                return ['ok' => true, 'asset' => $updated, 'path' => $masterPath];
            }
        }
        $asset = $updated;
    }

    $delivery = bandpromo_sfx_delivery_absolute($root, $assetId);
    if ($delivery === '' || !is_file($delivery)) {
        return [
            'ok' => false,
            'asset' => $asset,
            'warning' => (string) ($materialized['warning'] ?? 'Sound-effect master and delivery are both missing.'),
        ];
    }

    bandpromo_sfx_ensure_tier_dirs($root);
    $masterFilename = bandpromo_asset_master_filename_for_ulid($assetId, 'mp3');
    $dest = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;
    if (!is_file($dest) && !@copy($delivery, $dest)) {
        return [
            'ok' => false,
            'asset' => $asset,
            'warning' => 'Could not promote sound-effect delivery to master.',
        ];
    }

    $updated = $asset;
    if ((string) ($asset['master_filename'] ?? '') !== $masterFilename
        || strtolower((string) ($asset['master_format'] ?? '')) !== 'mp3'
    ) {
        $updated = bandpromo_asset_update_entry($root, $assetId, [
            'master_filename' => $masterFilename,
            'master_format' => 'mp3',
        ]);
    }

    return ['ok' => true, 'asset' => $updated, 'path' => $dest];
}

/**
 * Register a delivery-only sound effect that already lives under media/sfx/optimal/{ast_*}.mp3
 * (common after legacy path dual-read) so Files and PCF/PBF can see it.
 *
 * @return array<string, mixed>|null
 */
function bandpromo_sfx_ensure_registered_delivery(string $root, string $assetId, string $brandId = ''): ?array
{
    require_once __DIR__ . '/asset-registry.php';

    $assetId = trim($assetId);
    if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
        return null;
    }

    $existing = bandpromo_asset_lookup_by_id($root, $assetId);
    if (is_array($existing)) {
        if (($existing['kind'] ?? '') !== 'sfx') {
            return null;
        }
        $ensured = bandpromo_sfx_ensure_portable_master($root, $existing);
        $asset = is_array($ensured['asset'] ?? null) ? $ensured['asset'] : $existing;
        if ($brandId !== '' && trim((string) ($asset['brand_id'] ?? '')) === '') {
            $asset = bandpromo_asset_update_entry($root, $assetId, ['brand_id' => $brandId]);
        }

        return $asset;
    }

    $delivery = bandpromo_sfx_delivery_absolute($root, $assetId);
    if ($delivery === '' || !is_file($delivery)) {
        return null;
    }

    bandpromo_sfx_ensure_tier_dirs($root);
    $masterFilename = bandpromo_asset_master_filename_for_ulid($assetId, 'mp3');
    $dest = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;
    if (!is_file($dest) && !@copy($delivery, $dest)) {
        return null;
    }

    if ($brandId === '') {
        $brandId = bandpromo_asset_active_brand_id($root);
    }

    $now = gmdate('c');
    $entry = [
        'id' => $assetId,
        'kind' => 'sfx',
        'media_type' => 'audio',
        'intake_bucket' => 'sfx',
        'brand_id' => $brandId,
        'role' => 'sfx',
        'original_filename' => '',
        'master_filename' => $masterFilename,
        'master_format' => 'mp3',
        'release_id' => '',
        'slug' => '',
        'display' => [],
        'tags' => ['sfx'],
        'delivery' => [
            'ready' => true,
            'audio_optimal' => true,
            'source' => 'master',
        ],
        'created_at' => $now,
    ];
    $normalized = bandpromo_asset_normalize_entry($entry);
    if ($normalized === null) {
        return null;
    }

    $registry = bandpromo_asset_load_registry($root);
    $registry['assets'][$assetId] = $normalized;
    $registry['by_master_filename'][$masterFilename] = $assetId;
    bandpromo_asset_write_registry($root, $registry);

    try {
        require_once __DIR__ . '/media-library-state.php';
        bandpromo_media_files_index_sync_file($root, 'sfx', $masterFilename);
    } catch (Throwable $throwable) {
        // Index sync is best-effort.
    }

    if ($brandId !== '') {
        try {
            require_once __DIR__ . '/brand-storage.php';
            bandpromo_brand_add_assets_to_library($root, $brandId, [$assetId]);
        } catch (Throwable $throwable) {
            // Non-fatal.
        }
    }

    return $normalized;
}

/**
 * Register brand Welcome / Logged-in delivery files that are missing from the SFX registry.
 *
 * @return int Number of assets healed
 */
function bandpromo_sfx_heal_brand_slot_deliveries(string $root): int
{
    require_once __DIR__ . '/brand-storage.php';
    require_once __DIR__ . '/asset-registry.php';

    $healed = 0;
    try {
        $registry = bandpromo_brand_load_registry($root);
    } catch (Throwable $throwable) {
        return 0;
    }

    foreach ($registry['brands'] ?? [] as $brandMeta) {
        if (!is_array($brandMeta)) {
            continue;
        }
        $brandId = trim((string) ($brandMeta['id'] ?? ''));
        if ($brandId === '') {
            continue;
        }
        try {
            $document = bandpromo_brand_load_document($root, $brandId);
        } catch (Throwable $throwable) {
            continue;
        }
        $assetIds = is_array($document['asset_ids'] ?? null) ? $document['asset_ids'] : [];
        $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
        $slotIdsChanged = false;
        foreach (['welcome_audio', 'loggedin_audio'] as $slotKey) {
            $slotId = trim((string) ($assetIds[$slotKey] ?? ''));
            if ($slotId === '') {
                $path = trim((string) ($assets[$slotKey] ?? ''));
                if ($path !== '') {
                    $slotId = bandpromo_brand_lookup_asset_id_for_path($root, $path);
                    if ($slotId === '') {
                        $stem = (string) pathinfo(basename(str_replace('\\', '/', $path)), PATHINFO_FILENAME);
                        if (bandpromo_asset_is_asset_id($stem)) {
                            $slotId = $stem;
                        }
                    }
                }
            }
            if ($slotId === '' || !bandpromo_asset_is_asset_id($slotId)) {
                continue;
            }
            $before = bandpromo_asset_lookup_by_id($root, $slotId);
            $asset = bandpromo_sfx_ensure_registered_delivery($root, $slotId, $brandId);
            if (!is_array($asset)) {
                continue;
            }
            if (!is_array($before) || ($before['kind'] ?? '') !== 'sfx') {
                $healed++;
            }
            if (trim((string) ($assetIds[$slotKey] ?? '')) === '') {
                $assetIds[$slotKey] = $slotId;
                $slotIdsChanged = true;
            }
        }
        if ($slotIdsChanged) {
            try {
                $document['asset_ids'] = bandpromo_brand_normalize_asset_ids($assetIds);
                $document['library_asset_ids'] = bandpromo_brand_merge_library_slot_assets(
                    $document['asset_ids'],
                    is_array($document['library_asset_ids'] ?? null) ? $document['library_asset_ids'] : []
                );
                bandpromo_brand_write_document($root, $document);
            } catch (Throwable $throwable) {
                // Best-effort slot heal.
            }
        }
    }

    return $healed;
}

/**
 * Build tagless delivery MP3 under media/sfx/optimal/{ast_*}.mp3 from the master.
 *
 * @param array<string, mixed> $asset
 * @return array{ok: bool, asset: array<string, mixed>, path?: string, warning?: string}
 */
function bandpromo_sfx_build_delivery(string $root, array $asset): array
{
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/audio-master-helpers.php';

    $assetId = trim((string) ($asset['id'] ?? ''));
    if ($assetId === '' || !bandpromo_asset_is_asset_id($assetId)) {
        return ['ok' => false, 'asset' => $asset, 'warning' => 'Invalid sound-effect asset.'];
    }

    $materialized = bandpromo_sfx_materialize_master($root, $asset);
    if (empty($materialized['ok'])) {
        return [
            'ok' => false,
            'asset' => $materialized['asset'] ?? $asset,
            'warning' => (string) ($materialized['warning'] ?? 'Master materialize failed.'),
        ];
    }
    $asset = $materialized['asset'];
    $masterPath = (string) ($materialized['path'] ?? '');
    if ($masterPath === '' || !is_file($masterPath)) {
        return ['ok' => false, 'asset' => $asset, 'warning' => 'Sound-effect master missing.'];
    }

    bandpromo_sfx_ensure_tier_dirs($root);
    $dest = bandpromo_sfx_delivery_absolute($root, $assetId);
    $ext = strtolower((string) pathinfo($masterPath, PATHINFO_EXTENSION));

    if ($ext === 'mp3') {
        if (!@copy($masterPath, $dest)) {
            return ['ok' => false, 'asset' => $asset, 'warning' => 'Could not copy MP3 delivery.'];
        }
    } else {
        $ffmpeg = bandpromo_resolve_ffmpeg_binary($root);
        if ($ffmpeg === '') {
            return ['ok' => false, 'asset' => $asset, 'warning' => 'ffmpeg is required for sound-effect delivery.'];
        }
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $command = [
            $ffmpeg,
            '-y',
            '-i',
            $masterPath,
            '-vn',
            '-map_metadata',
            '-1',
            '-c:a',
            'libmp3lame',
            '-b:a',
            '192k',
            $dest,
        ];
        $process = proc_open($command, $descriptors, $pipes, $root);
        if (!is_resource($process)) {
            return ['ok' => false, 'asset' => $asset, 'warning' => 'Could not start sound-effect delivery encode.'];
        }
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || !is_file($dest) || filesize($dest) <= 0) {
            if (is_file($dest)) {
                @unlink($dest);
            }

            return ['ok' => false, 'asset' => $asset, 'warning' => 'Sound-effect delivery encode failed.'];
        }
    }

    // Strip leftover ID3/APE on delivery MP3 (tagless like catalogue delivery).
    bandpromo_sfx_strip_delivery_tags($root, $dest);

    $updated = bandpromo_asset_update_entry($root, $assetId, [
        'delivery' => [
            'ready' => true,
            'audio_optimal' => true,
            'source' => 'master',
            'built_at' => gmdate('c'),
        ],
    ]);

    return ['ok' => true, 'asset' => $updated, 'path' => $dest];
}

function bandpromo_sfx_strip_delivery_tags(string $root, string $mp3Path): void
{
    if (!is_file($mp3Path)) {
        return;
    }
    $python = '';
    foreach (['py', 'python', 'python3'] as $candidate) {
        $test = shell_exec(
            (DIRECTORY_SEPARATOR === '\\' ? "where $candidate 2>nul" : "which $candidate 2>/dev/null")
        );
        if (!$test) {
            continue;
        }
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $test));
        $resolved = trim((string) ($lines[0] ?? ''));
        if ($resolved !== '') {
            $python = $resolved;
            break;
        }
    }
    if ($python === '') {
        return;
    }

    $script = <<<'PY'
import sys
from pathlib import Path
path = Path(sys.argv[1])
try:
    from mutagen.apev2 import APEv2, APENoHeaderError
    try:
        APEv2(str(path)).delete()
    except APENoHeaderError:
        pass
    except Exception:
        pass
except Exception:
    pass
try:
    from mutagen.id3 import ID3, ID3NoHeaderError
    try:
        ID3(str(path)).delete()
    except ID3NoHeaderError:
        pass
    except Exception:
        pass
except Exception:
    pass
PY;

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([$python, '-c', $script, $mp3Path], $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        return;
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($process);
}

/**
 * Delete master + delivery files for a sound-effect asset.
 *
 * @param array<string, mixed>|null $asset
 */
function bandpromo_sfx_delete_tier_files(string $root, ?array $asset): void
{
    if (!is_array($asset)) {
        return;
    }
    $assetId = trim((string) ($asset['id'] ?? ''));
    $masterFilename = basename((string) ($asset['master_filename'] ?? ''));
    if ($masterFilename !== '') {
        $path = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    if ($assetId !== '') {
        $delivery = bandpromo_sfx_delivery_absolute($root, $assetId);
        if ($delivery !== '' && is_file($delivery)) {
            @unlink($delivery);
        }
    }
}

/**
 * True when optimal delivery exists, is marked ready, and is not older than the master.
 *
 * @param array<string, mixed> $asset
 */
function bandpromo_sfx_delivery_is_fresh(string $root, array $asset): bool
{
    $assetId = trim((string) ($asset['id'] ?? ''));
    if ($assetId === '') {
        return false;
    }

    $dest = bandpromo_sfx_delivery_absolute($root, $assetId);
    if ($dest === '' || !is_file($dest) || filesize($dest) <= 0) {
        return false;
    }
    if (empty($asset['delivery']['ready']) || empty($asset['delivery']['audio_optimal'])) {
        return false;
    }

    $format = strtolower(trim((string) ($asset['master_format'] ?? '')));
    if ($format === '') {
        $format = strtolower((string) pathinfo((string) ($asset['master_filename'] ?? ''), PATHINFO_EXTENSION));
    }
    $master = $format !== ''
        ? bandpromo_sfx_master_absolute($root, $assetId, $format)
        : '';
    if ($master === '' || !is_file($master)) {
        $masterFilename = basename((string) ($asset['master_filename'] ?? ''));
        if ($masterFilename !== '') {
            $master = bandpromo_sfx_master_dir($root) . DIRECTORY_SEPARATOR . $masterFilename;
        }
    }
    if ($master === '' || !is_file($master)) {
        return false;
    }

    return filemtime($master) <= filemtime($dest);
}

/**
 * Backfill master + delivery for all registered sound effects.
 * Skips assets whose optimal delivery is already fresh (master not newer).
 *
 * @return array{masters: int, deliveries: int, skipped: int, warnings: list<string>}
 */
function bandpromo_sfx_backfill_tiers(string $root): array
{
    require_once __DIR__ . '/asset-registry.php';

    $result = ['masters' => 0, 'deliveries' => 0, 'skipped' => 0, 'warnings' => []];
    $registry = bandpromo_asset_load_registry($root);
    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset) || ($asset['kind'] ?? '') !== 'sfx') {
            continue;
        }
        if (bandpromo_sfx_delivery_is_fresh($root, $asset)) {
            $result['skipped']++;
            continue;
        }
        $built = bandpromo_sfx_build_delivery($root, $asset);
        if (!empty($built['ok'])) {
            $result['masters']++;
            $result['deliveries']++;
        } else {
            $result['warnings'][] = (string) ($built['warning'] ?? 'Sound-effect tier backfill failed.');
        }
    }

    return $result;
}

/**
 * Register or refresh a Sound effects asset (kind=sfx, role=sfx).
 *
 * @param array{brand_id?: string, build_delivery?: bool} $options
 * @return array<string, mixed>
 */
function bandpromo_asset_register_sfx(
    string $root,
    string $originalFilename,
    array $options = []
): array {
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/media-intake-helpers.php';

    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '' || !bandpromo_sfx_is_audio_filename($originalFilename)) {
        throw new InvalidArgumentException('Sound effects must be an audio file.');
    }

    bandpromo_sfx_ensure_tier_dirs($root);
    $absolute = bandpromo_intake_resolve_source($root, 'sfx', $originalFilename);
    if ($absolute === '' || !is_file($absolute)) {
        throw new RuntimeException('Sound effect file not found: ' . $originalFilename);
    }

    $existing = bandpromo_asset_lookup_by_original_filename($root, $originalFilename);
    if (is_array($existing) && ($existing['kind'] ?? '') === 'sfx') {
        $changes = [];
        if (array_key_exists('brand_id', $options)) {
            $changes['brand_id'] = trim((string) $options['brand_id']);
        }
        $asset = $changes !== []
            ? bandpromo_asset_update_entry($root, (string) $existing['id'], $changes)
            : $existing;
        $buildDelivery = !array_key_exists('build_delivery', $options) || !empty($options['build_delivery']);
        if ($buildDelivery) {
            $built = bandpromo_sfx_build_delivery($root, $asset);
            if (!empty($built['ok']) && is_array($built['asset'] ?? null)) {
                $asset = $built['asset'];
            }
        }
        $assetBrandId = trim((string) ($asset['brand_id'] ?? ''));
        if ($assetBrandId !== '') {
            try {
                require_once __DIR__ . '/brand-storage.php';
                bandpromo_brand_add_assets_to_library($root, $assetBrandId, [(string) ($asset['id'] ?? '')]);
            } catch (Throwable $throwable) {
                // Non-fatal; asset remains registered.
            }
        }

        return $asset;
    }

    $brandId = trim((string) ($options['brand_id'] ?? ''));
    if ($brandId === '') {
        $brandId = bandpromo_asset_active_brand_id($root);
    }

    $id = bandpromo_generate_asset_id();
    $sourceFormat = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
    $format = $sourceFormat === 'wav' ? 'flac' : $sourceFormat;
    $masterFilename = bandpromo_asset_master_filename_for_ulid($id, $format);
    $now = gmdate('c');
    $entry = [
        'id' => $id,
        'kind' => 'sfx',
        'media_type' => 'audio',
        'intake_bucket' => 'sfx',
        'brand_id' => $brandId,
        'role' => 'sfx',
        'original_filename' => $originalFilename,
        'master_filename' => $masterFilename,
        'master_format' => $format,
        'release_id' => '',
        'slug' => '',
        'display' => [],
        'tags' => ['sfx'],
        'delivery' => [
            'ready' => false,
            'audio_optimal' => false,
            'source' => 'master',
        ],
        'created_at' => $now,
    ];

    $normalized = bandpromo_asset_normalize_entry($entry);
    if ($normalized === null) {
        throw new RuntimeException('Could not normalize sound effect asset.');
    }

    $registry = bandpromo_asset_load_registry($root);
    $registry['assets'][$id] = $normalized;
    $registry['by_original_filename'][$originalFilename] = $id;
    $registry['by_master_filename'][$masterFilename] = $id;
    bandpromo_asset_write_registry($root, $registry);

    $buildDelivery = !array_key_exists('build_delivery', $options) || !empty($options['build_delivery']);
    if ($buildDelivery) {
        $built = bandpromo_sfx_build_delivery($root, $normalized);
        if (!empty($built['ok']) && is_array($built['asset'] ?? null)) {
            $normalized = $built['asset'];
        }
    } else {
        bandpromo_sfx_materialize_master($root, $normalized);
    }

    $asset = bandpromo_asset_lookup_by_id($root, $id) ?? $normalized;
    $assetBrandId = trim((string) ($asset['brand_id'] ?? $brandId));
    if ($assetBrandId !== '') {
        try {
            require_once __DIR__ . '/brand-storage.php';
            bandpromo_brand_add_assets_to_library($root, $assetBrandId, [(string) ($asset['id'] ?? $id)]);
        } catch (Throwable $throwable) {
            // Listing still works once the operator adds the asset to a Brand library.
        }
    }

    return $asset;
}

/**
 * Copy special-folder shell audio into Sound effects and rewrite brand/config refs.
 *
 * @return array{copied: int, rewritten: int}
 */
function bandpromo_sfx_migrate_from_special(string $root): array
{
    require_once __DIR__ . '/media-library-state.php';
    require_once __DIR__ . '/asset-registry.php';
    require_once __DIR__ . '/brand-storage.php';

    $result = ['copied' => 0, 'rewritten' => 0];
    bandpromo_sfx_ensure_tier_dirs($root);

    $specialDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'special';
    $sfxDir = bandpromo_sfx_original_dir($root);

    if (is_dir($specialDir)) {
        foreach (scandir($specialDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || strcasecmp($entry, 'desktop.ini') === 0) {
                continue;
            }
            if (!bandpromo_sfx_is_audio_filename($entry)) {
                continue;
            }
            $source = $specialDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($source)) {
                continue;
            }
            $dest = $sfxDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($dest)) {
                if (!@copy($source, $dest)) {
                    continue;
                }
                $result['copied']++;
            }
            bandpromo_media_files_index_sync_file($root, 'sfx', $entry);
            try {
                bandpromo_asset_register_sfx($root, $entry, ['build_delivery' => true]);
            } catch (Throwable $throwable) {
                // Registry optional for migrate success.
            }
        }
    }

    try {
        bandpromo_sfx_backfill_tiers($root);
    } catch (Throwable $throwable) {
        // Best-effort tier backfill.
    }

    try {
        $registry = bandpromo_brand_load_registry($root);
        foreach ($registry['brands'] ?? [] as $brandMeta) {
            if (!is_array($brandMeta)) {
                continue;
            }
            $brandId = trim((string) ($brandMeta['id'] ?? ''));
            if ($brandId === '') {
                continue;
            }
            try {
                $document = bandpromo_brand_load_document($root, $brandId);
            } catch (Throwable $throwable) {
                continue;
            }
            $assets = is_array($document['assets'] ?? null) ? $document['assets'] : [];
            $changed = false;
            foreach (['welcome_audio', 'loggedin_audio'] as $key) {
                $current = trim((string) ($assets[$key] ?? ''));
                if ($current === '') {
                    continue;
                }
                $next = bandpromo_sfx_resolve_stored_path($root, $current);
                if ($next !== '' && $next !== $current) {
                    $assets[$key] = $next;
                    $changed = true;
                    $result['rewritten']++;
                }
            }
            if ($changed) {
                $document['assets'] = $assets;
                bandpromo_brand_write_document($root, $document);
            }
        }
    } catch (Throwable $throwable) {
        // Brand rewrite best-effort.
    }

    $configPath = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'web-config.json';
    if (is_file($configPath)) {
        $config = bandpromo_json_read_array_file($configPath);
        if (is_array($config)) {
            $paths = [
                ['install', 'theme', 'welcome_audio'],
                ['install', 'theme', 'loggedin_audio'],
                ['media', 'welcome_audio'],
                ['media', 'loggedin_audio'],
            ];
            $changed = false;
            foreach ($paths as $segments) {
                $cursor = &$config;
                $ok = true;
                foreach ($segments as $i => $segment) {
                    if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                        $ok = false;
                        break;
                    }
                    if ($i === count($segments) - 1) {
                        $current = trim((string) $cursor[$segment]);
                        if ($current === '') {
                            break;
                        }
                        $next = bandpromo_sfx_resolve_stored_path(
                            $root,
                            $current
                        );
                        if ($next !== '' && $next !== $current) {
                            $cursor[$segment] = $next;
                            $changed = true;
                            $result['rewritten']++;
                        }
                        break;
                    }
                    $cursor = &$cursor[$segment];
                }
                unset($cursor);
                if (!$ok) {
                    continue;
                }
            }
            if ($changed) {
                bandpromo_json_write_file($configPath, $config);
            }
        }
    }

    return $result;
}
