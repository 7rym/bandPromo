<?php
declare(strict_types=1);

/**
 * Discard archival original uploads while keeping master + delivery.
 * Called from System → Status → Storage only — never from Site health Treat.
 *
 * POST JSON:
 *   { target, filename } or { target, filenames: [...] }
 *   optional asset_id
 */
require_once __DIR__ . '/admin-audit.php';
require_once __DIR__ . '/admin-api-guard.php';
require_once __DIR__ . '/media-library-state.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/visual-master-helpers.php';
require_once __DIR__ . '/sfx-helpers.php';
require_once __DIR__ . '/demo-catalog-state.php';
require_once __DIR__ . '/discard-original-helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$root = dirname(__DIR__);
bandpromo_asset_registry_ensure_migrated($root);

$target = trim((string) ($body['target'] ?? ''));
$allowed = ['audio', 'illustrations', 'photos', 'video', 'special', 'sfx'];
if (!in_array($target, $allowed, true)) {
    echo json_encode(['error' => 'Unknown target']);
    exit;
}

$requested = [];
if (isset($body['filenames']) && is_array($body['filenames'])) {
    $requested = $body['filenames'];
} elseif (isset($body['filename'])) {
    $requested = [$body['filename']];
}
$requested = array_values(array_filter(array_map(static function ($name): string {
    return basename(trim((string) $name));
}, $requested), static fn(string $name): bool => $name !== ''));

if ($requested === []) {
    echo json_encode(['error' => 'No filename provided']);
    exit;
}

/**
 * @return array{ok:bool,filename?:string,error?:string,bytes_freed?:int,paths_removed?:list<string>,has_original?:bool,has_master?:bool}
 */
function bandpromo_discard_original_one(string $root, string $target, string $safe, string $assetIdHint = ''): array
{
    $safe = basename(trim($safe));
    if ($safe === '' || strpbrk($safe, '/\\') !== false) {
        return ['ok' => false, 'filename' => $safe, 'error' => 'Invalid filename'];
    }

    $asset = null;
    if ($assetIdHint !== '' && bandpromo_asset_is_asset_id($assetIdHint)) {
        $asset = bandpromo_asset_lookup_by_id($root, $assetIdHint);
    }
    if (!is_array($asset)) {
        $asset = bandpromo_asset_lookup_by_master_filename($root, $safe)
            ?? bandpromo_asset_lookup_by_original_filename($root, $safe);
    }
    if (!is_array($asset)) {
        return [
            'ok' => false,
            'filename' => $safe,
            'error' => 'Register this file first — discard archival upload needs a master.',
        ];
    }

    $assetId = trim((string) ($asset['id'] ?? ''));
    $originalName = basename(trim((string) ($asset['original_filename'] ?? '')));
    if ($originalName === '') {
        $originalName = $safe;
    }
    $masterName = basename(trim((string) ($asset['master_filename'] ?? '')));

    $status = bandpromo_media_archival_status($root, $target, [
        'name' => $safe,
        'original_filename' => $originalName,
        'audio_master' => ['exists' => false, 'filename' => $masterName],
    ], $asset);

    if (empty($status['has_master'])) {
        return [
            'ok' => false,
            'filename' => $safe,
            'error' => 'A master file must exist before the archival upload can be discarded.',
            'has_master' => false,
            'has_original' => !empty($status['has_original']),
        ];
    }

    $demoAssetSet = bandpromo_demo_campaign_asset_set($root);
    $lockCandidates = array_values(array_unique(array_filter([
        $target . '|' . $safe,
        $originalName !== '' ? $target . '|' . $originalName : '',
        $assetId !== '' ? $target . '|' . $assetId : '',
        $masterName !== '' ? $target . '|' . $masterName : '',
    ])));
    foreach ($lockCandidates as $lockKey) {
        if (bandpromo_asset_is_in_locked_release($root, $lockKey, $demoAssetSet)) {
            return [
                'ok' => false,
                'filename' => $safe,
                'error' => 'This file belongs to the locked demo release. Unlock the demo release on localhost before discarding its archival upload.',
            ];
        }
    }

    if (empty($status['has_original'])) {
        return [
            'ok' => false,
            'filename' => $safe,
            'error' => 'No archival upload is on disk for this file.',
            'has_original' => false,
            'has_master' => true,
            'bytes_freed' => 0,
        ];
    }

    $existing = [];
    foreach (bandpromo_discard_original_candidate_paths($root, $target, $originalName) as $path) {
        if (!is_file($path)) {
            continue;
        }
        $existing[] = [
            'path' => $path,
            'size' => (int) filesize($path),
        ];
    }

    $removed = [];
    $bytesFreed = 0;
    foreach ($existing as $row) {
        $path = (string) ($row['path'] ?? '');
        if ($path === '') {
            continue;
        }
        if (@unlink($path)) {
            $removed[] = $path;
            $bytesFreed += (int) ($row['size'] ?? 0);
        }
    }

    if ($removed === []) {
        return [
            'ok' => false,
            'filename' => $safe,
            'error' => 'Could not remove the archival upload from disk.',
            'has_original' => true,
            'has_master' => true,
        ];
    }

    $indexTarget = $target;
    if ($target === 'special') {
        $indexTarget = bandpromo_asset_files_index_target_for_intake_bucket(
            (string) ($asset['intake_bucket'] ?? 'img')
        );
        if ($indexTarget === '') {
            $indexTarget = 'illustrations';
        }
    }
    bandpromo_media_files_index_sync_file($root, $indexTarget, $masterName !== '' ? $masterName : $safe, [
        'persist' => true,
    ]);

    bandpromo_admin_audit_log('media_discard_original', [
        'target' => $target,
        'asset_id' => $assetId,
        'original_filename' => $originalName,
        'bytes_freed' => $bytesFreed,
        'paths' => array_map(static function (string $path) use ($root): string {
            $norm = str_replace('\\', '/', $path);
            $rootNorm = rtrim(str_replace('\\', '/', $root), '/') . '/';
            if (str_starts_with($norm, $rootNorm)) {
                return substr($norm, strlen($rootNorm));
            }

            return basename($norm);
        }, $removed),
    ]);

    return [
        'ok' => true,
        'filename' => $safe,
        'asset_id' => $assetId,
        'original_filename' => $originalName,
        'bytes_freed' => $bytesFreed,
        'paths_removed' => $removed,
        'has_original' => false,
        'has_master' => true,
    ];
}

$assetIdHint = trim((string) ($body['asset_id'] ?? ''));
$results = [];
$freed = 0;
$okCount = 0;
foreach ($requested as $filename) {
    $result = bandpromo_discard_original_one($root, $target, $filename, $assetIdHint);
    $results[] = $result;
    if (!empty($result['ok'])) {
        $okCount++;
        $freed += (int) ($result['bytes_freed'] ?? 0);
    }
}

$failed = array_values(array_filter($results, static fn(array $row): bool => empty($row['ok'])));

echo json_encode([
    'ok' => $failed === [],
    'discarded' => $okCount,
    'bytes_freed' => $freed,
    'results' => $results,
    'error' => $failed === []
        ? ''
        : (string) ($failed[0]['error'] ?? 'Could not discard archival upload'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
