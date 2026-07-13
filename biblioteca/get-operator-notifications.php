<?php
require_once __DIR__ . '/admin-api-guard.php';

require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/admin-welcome-state.php';
require_once __DIR__ . '/package-updater.php';
require_once __DIR__ . '/asset-registry.php';
require_once __DIR__ . '/publish-status-helpers.php';
require_once __DIR__ . '/catalog-repair-auto.php';

$rootDir = dirname(__DIR__);
$validationFile = is_file($rootDir . '/data/validation/playlist-validation.json')
    ? $rootDir . '/data/validation/playlist-validation.json'
    : $rootDir . '/play/playlist-validation.json';
$metadataValidation = null;

function bandpromo_filter_metadata_validation_for_notifications(string $rootDir, ?array $validation): ?array
{
    if (!is_array($validation)) {
        return null;
    }

    $tracks = is_array($validation['tracks'] ?? null) ? $validation['tracks'] : [];
    $filteredTracks = [];
    foreach ($tracks as $track) {
        if (!is_array($track)) {
            continue;
        }

        $file = basename(trim((string) ($track['file'] ?? '')));
        if ($file === '') {
            continue;
        }

        $originalPath = $rootDir . '/media/audio/original/' . $file;
        if (!is_file($originalPath)) {
            continue;
        }

        $filteredTracks[] = $track;
    }

    $validation['tracks'] = $filteredTracks;
    $summary = is_array($validation['summary'] ?? null) ? $validation['summary'] : [];
    $tracksWithWarnings = 0;
    foreach ($filteredTracks as $track) {
        $warnings = is_array($track['warnings'] ?? null) ? $track['warnings'] : [];
        if ($warnings !== []) {
            $tracksWithWarnings++;
        }
    }

    $validation['summary'] = array_merge($summary, [
        'totalTracks' => count($filteredTracks),
        'tracksWithWarnings' => $tracksWithWarnings,
        'tracksWithoutWarnings' => max(0, count($filteredTracks) - $tracksWithWarnings),
    ]);

    return $validation;
}

if (file_exists($validationFile)) {
    $validationJson = file_get_contents($validationFile);
    if ($validationJson !== false) {
        $decoded = json_decode($validationJson, true);
        if (is_array($decoded)) {
            $metadataValidation = $decoded;
            if (!isset($metadataValidation['generated_at']) && !isset($metadataValidation['checked_at'])) {
                $mtime = filemtime($validationFile);
                if ($mtime !== false) {
                    $metadataValidation['checked_at'] = gmdate('c', $mtime);
                }
            }
        }
    }
}

$metadataValidation = bandpromo_filter_metadata_validation_for_notifications($rootDir, $metadataValidation);

$uncataloguedAudioFailures = [];
$uncataloguedReconcile = bandpromo_reconcile_uncatalogued_audio_originals($rootDir);
if (!empty($uncataloguedReconcile['failed']) && is_array($uncataloguedReconcile['failed'])) {
    $uncataloguedAudioFailures = $uncataloguedReconcile['failed'];
}

$catalogRepair = bandpromo_catalog_repair_maybe_run($rootDir, $uncataloguedReconcile);
$publishStatus = bandpromo_publish_status_summary($rootDir);

$buildState = bandpromo_get_build_required_state();
$welcomeState = bandpromo_admin_welcome_state($rootDir);
$packageUpdate = bandpromo_package_check_update($rootDir);
$backgroundTasks = bandpromo_reconcile_background_tasks();

echo json_encode([
    'ok' => true,
    'build_required' => !empty($buildState['required']),
    'build_required_state' => $buildState,
    'background_tasks' => $backgroundTasks,
    'metadata_validation' => $metadataValidation,
    'publish_status' => $publishStatus,
    'catalog_repair' => $catalogRepair,
    'uncatalogued_audio_failures' => $uncataloguedAudioFailures,
    'welcome' => $welcomeState,
    'package_update' => [
        'installed_version' => $packageUpdate['installed_version'] ?? null,
        'remote_version' => $packageUpdate['remote_version'] ?? null,
        'update_available' => !empty($packageUpdate['update_available']),
        'ahead_of_published' => !empty($packageUpdate['ahead_of_published']),
        'up_to_date' => !empty($packageUpdate['up_to_date']),
        'ready' => !empty($packageUpdate['ready']),
        'manifest_error' => $packageUpdate['manifest_error'] ?? null,
        'checks' => $packageUpdate['checks'] ?? [],
        'release_notes' => $packageUpdate['release_notes'] ?? [],
        'last_update' => $packageUpdate['last_update'] ?? null,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;