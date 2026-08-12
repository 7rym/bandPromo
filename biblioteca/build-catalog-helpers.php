<?php
declare(strict_types=1);

require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/audio-master-helpers.php';
require_once __DIR__ . '/content-autofix-helpers.php';

function bandpromo_build_catalog_register_uncatalogued(string $root): array
{
    $result = [
        'changed' => 0,
        'skipped' => 0,
        'errors' => [],
        'items' => [],
    ];

    foreach (bandpromo_list_uncatalogued_audio_originals($root) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $filename = basename(trim((string) ($item['filename'] ?? '')));
        if ($filename === '') {
            continue;
        }

        $prepared = bandpromo_materialize_audio_master_from_original($root, $filename);
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

function bandpromo_build_catalog_run(string $root): array
{
    $steps = [];
    $errors = [];

    try {
        bandpromo_asset_registry_ensure_migrated($root);
    } catch (Throwable $throwable) {
        return [
            'ok' => false,
            'steps' => [],
            'errors' => [$throwable->getMessage()],
        ];
    }

    $register = bandpromo_build_catalog_register_uncatalogued($root);
    $steps[] = array_merge([
        'id' => 'register_uncatalogued',
        'label' => 'Register uncatalogued audio uploads',
    ], $register);

    $materialize = bandpromo_content_autofix_materialize_audio_masters($root, false);
    $steps[] = $materialize;

    $canonical = bandpromo_content_autofix_canonicalize_master_filenames($root, false);
    $steps[] = $canonical;

    $visualMasters = bandpromo_content_autofix_materialize_visual_masters($root, false);
    $steps[] = $visualMasters;

    require_once __DIR__ . '/gallery-storage.php';
    try {
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
