<?php
declare(strict_types=1);

require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/content-autofix-helpers.php';

function bandpromo_build_catalog_inventory_only(): bool
{
    $flag = strtolower(trim((string) getenv('BANDPROMO_CATALOG_INVENTORY_ONLY')));

    return $flag === '1' || $flag === 'true' || $flag === 'yes';
}

function bandpromo_build_catalog_register_uncatalogued(string $root): array
{
    $result = [
        'changed' => 0,
        'skipped' => 0,
        'errors' => [],
        'items' => [],
    ];

    if (bandpromo_build_catalog_inventory_only()) {
        $pendingMasters = bandpromo_list_uncatalogued_audio_masters($root);
        $pendingOriginals = bandpromo_list_uncatalogued_audio_originals($root);
        foreach ($pendingMasters as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['master_filename'] ?? ''));
            if ($name === '') {
                continue;
            }
            $result['skipped']++;
            $result['items'][] = $name;
        }
        foreach ($pendingOriginals as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = basename(trim((string) ($item['filename'] ?? '')));
            if ($name === '') {
                continue;
            }
            $result['skipped']++;
            $result['items'][] = $name;
        }

        return $result;
    }

    $masterReconcile = bandpromo_reconcile_uncatalogued_audio_masters($root);
    foreach (($masterReconcile['fixed'] ?? []) as $fixedName) {
        if (!is_string($fixedName) || trim($fixedName) === '') {
            continue;
        }
        $result['changed']++;
        $result['items'][] = $fixedName;
    }
    foreach (($masterReconcile['failed'] ?? []) as $failure) {
        if (!is_array($failure)) {
            continue;
        }
        $filename = trim((string) ($failure['filename'] ?? 'audio'));
        $error = trim((string) ($failure['error'] ?? 'Could not register audio master'));
        $result['errors'][] = $filename . ': ' . $error;
    }

    foreach (bandpromo_list_uncatalogued_audio_originals($root) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $filename = basename(trim((string) ($item['filename'] ?? '')));
        if ($filename === '') {
            continue;
        }

        $prepared = bandpromo_materialize_audio_master_from_original($root, $filename, false);
        if (!empty($prepared['prepared'])) {
            $result['changed']++;
            $result['items'][] = $filename;
            continue;
        }

        if (!empty($prepared['attempted']) && !empty($prepared['warning'])) {
            $result['errors'][] = $filename . ': ' . (string) $prepared['warning'];
            continue;
        }

        $result['skipped']++;
    }

    return $result;
}

function bandpromo_build_catalog_run(string $root, ?callable $onProgress = null): array
{
    $steps = [];
    $errors = [];
    $inventoryOnly = bandpromo_build_catalog_inventory_only();
    $progress = static function (string $message) use ($onProgress): void {
        if ($onProgress === null) {
            return;
        }
        $onProgress($message);
    };

    try {
        $progress($inventoryOnly ? 'Reading asset registry (inventory-only)...' : 'Ensuring asset registry...');
        bandpromo_asset_registry_ensure_migrated($root, false);
    } catch (Throwable $throwable) {
        return [
            'ok' => false,
            'steps' => [],
            'errors' => [$throwable->getMessage()],
        ];
    }

    $progress($inventoryOnly
        ? 'Listing uncatalogued audio masters and uploads (no writes)...'
        : 'Registering uncatalogued audio masters and uploads...');
    $register = bandpromo_build_catalog_register_uncatalogued($root);
    $steps[] = array_merge([
        'id' => 'register_uncatalogued',
        'label' => $inventoryOnly
            ? 'Inventory uncatalogued audio masters and uploads'
            : 'Register uncatalogued audio masters and uploads',
    ], $register);

    if ($inventoryOnly) {
        if (($register['skipped'] ?? 0) > 0) {
            $progress('Inventory found ' . (int) $register['skipped'] . ' item(s) needing Repair Apply.');
        }
        return [
            'ok' => true,
            'steps' => $steps,
            'errors' => [],
            'inventory_only' => true,
        ];
    }

    $progress('Materialising audio masters...');
    $materialize = bandpromo_content_autofix_materialize_audio_masters($root, false);
    $steps[] = $materialize;

    $progress('Canonicalising master filenames...');
    $canonical = bandpromo_content_autofix_canonicalize_master_filenames($root, false);
    $steps[] = $canonical;

    $progress('Materialising visual masters...');
    $visualMasters = bandpromo_content_autofix_materialize_visual_masters($root, false);
    $steps[] = $visualMasters;

    require_once __DIR__ . '/gallery-storage.php';
    try {
        $progress('Syncing gallery visual asset refs...');
        bandpromo_gallery_ensure_seeded($root);
        $gallerySync = bandpromo_content_autofix_sync_gallery_asset_ids($root, false);
        $steps[] = $gallerySync;
    } catch (Throwable $throwable) {
        $steps[] = [
            'id' => 'gallery_asset_id_sync',
            'label' => 'Sync gallery visual asset refs',
            'changed' => 0,
            'skipped' => 0,
            'errors' => [$throwable->getMessage()],
            'items' => [],
        ];
    }

    foreach ($steps as $step) {
        if (!is_array($step['errors'] ?? null)) {
            continue;
        }
        foreach ($step['errors'] as $error) {
            $errors[] = (string) $error;
        }
    }

    return [
        'ok' => $errors === [],
        'steps' => $steps,
        'errors' => $errors,
    ];
}

function bandpromo_build_catalog_finalize_audio_upload(string $root, string $originalFilename): void
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return;
    }

    bandpromo_asset_registry_ensure_migrated($root);
    bandpromo_materialize_audio_master_from_original($root, $originalFilename);
    bandpromo_content_autofix_canonicalize_master_filenames($root, false);
}
