<?php
declare(strict_types=1);

require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/build-required.php';

function bandpromo_asset_audio_delivery_path(string $root, string $masterFilename): string
{
    $stem = pathinfo(basename($masterFilename), PATHINFO_FILENAME);

    return $root . '/media/audio/optimal/' . $stem . '.mp3';
}

function bandpromo_asset_audio_delivery_ready(string $root, string $masterFilename): bool
{
    $masterFilename = basename(trim($masterFilename));
    if ($masterFilename === '') {
        return false;
    }

    return is_file(bandpromo_asset_audio_delivery_path($root, $masterFilename));
}

function bandpromo_publish_status_summary(string $root): array
{
    $uncatalogued = bandpromo_list_uncatalogued_audio_originals($root);
    $registry = bandpromo_asset_load_registry($root);
    $registeredAudio = 0;
    $missingDelivery = [];

    foreach ($registry['assets'] as $asset) {
        if (!is_array($asset)) {
            continue;
        }
        if (strtolower((string) ($asset['kind'] ?? 'audio')) !== 'audio') {
            continue;
        }

        $registeredAudio++;
        $masterFilename = basename(trim((string) ($asset['master_filename'] ?? '')));
        if ($masterFilename === '') {
            continue;
        }
        if (bandpromo_asset_audio_delivery_ready($root, $masterFilename)) {
            continue;
        }

        $missingDelivery[] = [
            'asset_id' => (string) ($asset['id'] ?? ''),
            'master_filename' => $masterFilename,
            'title' => trim((string) ($asset['title'] ?? '')),
        ];
    }

    $buildState = bandpromo_get_build_required_state();
    $pendingPublish = !empty($buildState['required']);
    $checks = [];

    if (count($uncatalogued) > 0) {
        $checks[] = [
            'id' => 'uncatalogued_audio',
            'severity' => 'attention',
            'label' => 'Uncatalogued audio uploads',
            'count' => count($uncatalogued),
            'detail' => 'Files in Files are not registered in the asset catalog yet.',
            'action' => 'Use Repair catalog on this tab.',
        ];
    }

    if ($missingDelivery !== []) {
        $checks[] = [
            'id' => 'missing_audio_delivery',
            'severity' => 'attention',
            'label' => 'Registered audio without delivery files',
            'count' => count($missingDelivery),
            'detail' => 'Publish-ready MP3s are missing for catalogued audio assets.',
            'action' => 'Run Publish Build when the catalog is in order.',
        ];
    }

    if ($pendingPublish) {
        $tasks = is_array($buildState['tasks'] ?? null) ? $buildState['tasks'] : [];
        $checks[] = [
            'id' => 'publish_pending',
            'severity' => 'attention',
            'label' => 'Publish work pending',
            'count' => max(1, count($tasks)),
            'detail' => $tasks !== []
                ? 'Pending: ' . implode(', ', array_map('strval', $tasks))
                : 'Site changes still need a publish run.',
            'action' => 'Run the recommended publish action above.',
        ];
    }

    $ok = $checks === [];

    return [
        'ok' => $ok,
        'generated_at' => gmdate('c'),
        'summary' => [
            'registered_audio' => $registeredAudio,
            'uncatalogued_uploads' => count($uncatalogued),
            'missing_delivery' => count($missingDelivery),
            'publish_pending' => $pendingPublish,
        ],
        'checks' => $checks,
        'samples' => [
            'uncatalogued' => array_slice($uncatalogued, 0, 5),
            'missing_delivery' => array_slice($missingDelivery, 0, 5),
        ],
    ];
}
