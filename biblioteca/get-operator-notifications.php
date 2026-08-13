<?php
require_once __DIR__ . '/admin-api-guard.php';

require_once __DIR__ . '/playlist-storage.php';
require_once __DIR__ . '/auto-build-tasks.php';
require_once __DIR__ . '/admin-welcome-state.php';
require_once __DIR__ . '/package-updater.php';
require_once __DIR__ . '/publish-status-helpers.php';
require_once __DIR__ . '/catalog-repair-auto.php';

$rootDir = dirname(__DIR__);
$scope = strtolower(trim((string) ($_GET['scope'] ?? 'lite')));
if (!in_array($scope, ['lite', 'full'], true)) {
    $scope = 'lite';
}
$includeInventory = isset($_GET['inventory']) && (string) $_GET['inventory'] === '1';

$validationFile = bandpromo_playlist_validation_report_path($rootDir);
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

        require_once __DIR__ . '/audio-master-helpers.php';
        $master = bandpromo_find_audio_master($rootDir, $file);
        if (empty($master['exists'])) {
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

/**
 * Read-only catalog repair status for Notifications — never starts repair/Python from this endpoint.
 */
function bandpromo_notifications_catalog_repair_snapshot(string $root): array
{
    if (bandpromo_catalog_repair_is_locked($root)) {
        return [
            'status' => 'running',
            'message' => 'bandPromo is preparing uploads in the background.',
        ];
    }

    $state = bandpromo_catalog_repair_load_state($root);
    $errors = is_array($state['last_errors'] ?? null) ? $state['last_errors'] : [];
    if ($errors !== []) {
        return [
            'status' => 'warning',
            'message' => 'bandPromo could not finish preparing every upload automatically.',
            'errors' => array_slice($errors, 0, 5),
        ];
    }

    return [
        'status' => 'idle',
        'message' => '',
    ];
}

$buildState = bandpromo_get_build_required_state();
// Finalize/prune only — never auto-spawn video jobs from Notifications polling.
$backgroundTasks = bandpromo_reconcile_background_tasks(false);
$packageForceRefresh = isset($_GET['force_package']) && (string) $_GET['force_package'] === '1';
// Package/GitHub status is Welcome-only. Other tabs must not pay for remote checks.
$includePackage = $packageForceRefresh
    || (isset($_GET['include_package']) && (string) $_GET['include_package'] === '1');
$packageUpdate = $includePackage
    ? bandpromo_package_check_update_cached($rootDir, 900, $packageForceRefresh)
    : [
        'installed_version' => bandpromo_package_read_installed_version($rootDir),
        'remote_version' => null,
        'update_available' => false,
        'ahead_of_published' => false,
        'up_to_date' => false,
        'ready' => false,
        'checks' => [],
        'manifest_error' => null,
        'release_notes' => [],
        'last_update' => null,
        'skipped_until_welcome' => true,
    ];
$welcomeSetupComplete = bandpromo_admin_welcome_setup_is_complete($rootDir);

if ($scope === 'lite') {
    echo json_encode([
        'ok' => true,
        'scope' => 'lite',
        'build_required' => !empty($buildState['required']),
        'build_required_state' => $buildState,
        'background_tasks' => $backgroundTasks,
        'welcome' => [
            'setup_complete' => $welcomeSetupComplete,
            'setup_latched' => $welcomeSetupComplete,
            'completed_count' => 0,
            'total_count' => 0,
        ],
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
            'skipped_on_localhost' => !empty($packageUpdate['skipped_on_localhost']),
            'skip_reason' => $packageUpdate['skip_reason'] ?? null,
            'skipped_until_welcome' => !empty($packageUpdate['skipped_until_welcome']),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($validationFile !== null && file_exists($validationFile)) {
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

// Read-only only — catalog repair / uncatalogued materialize must not run here.
$catalogRepair = bandpromo_notifications_catalog_repair_snapshot($rootDir);
$uncataloguedAudioFailures = [];

$publishStatus = bandpromo_publish_status_summary($rootDir, [
    'include_inventory' => $includeInventory,
    'include_uncatalogued_scan' => false,
]);

echo json_encode([
    'ok' => true,
    'scope' => 'full',
    'build_required' => !empty($buildState['required']),
    'build_required_state' => $buildState,
    'background_tasks' => $backgroundTasks,
    'metadata_validation' => $metadataValidation,
    'publish_status' => $publishStatus,
    'catalog_repair' => $catalogRepair,
    'uncatalogued_audio_failures' => $uncataloguedAudioFailures,
    'welcome' => [
        'setup_complete' => $welcomeSetupComplete,
        'setup_latched' => $welcomeSetupComplete,
        'completed_count' => 0,
        'total_count' => 0,
    ],
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
        'skipped_on_localhost' => !empty($packageUpdate['skipped_on_localhost']),
        'skip_reason' => $packageUpdate['skip_reason'] ?? null,
        'skipped_until_welcome' => !empty($packageUpdate['skipped_until_welcome']),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
