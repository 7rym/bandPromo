<?php
require_once __DIR__ . '/admin-api-guard.php';

require_once __DIR__ . '/build-required.php';
require_once __DIR__ . '/admin-welcome-state.php';
require_once __DIR__ . '/package-updater.php';

$rootDir = dirname(__DIR__);
$validationFile = $rootDir . '/play/playlist-validation.json';
$metadataValidation = null;

if (file_exists($validationFile)) {
    $validationJson = file_get_contents($validationFile);
    if ($validationJson !== false) {
        $decoded = json_decode($validationJson, true);
        if (is_array($decoded)) {
            $metadataValidation = $decoded;
        }
    }
}

$buildState = bandpromo_get_build_required_state();
$welcomeState = bandpromo_admin_welcome_state($rootDir);
$packageUpdate = bandpromo_package_check_update($rootDir);

echo json_encode([
    'ok' => true,
    'build_required' => !empty($buildState['required']),
    'build_required_state' => $buildState,
    'metadata_validation' => $metadataValidation,
    'welcome' => $welcomeState,
    'package_update' => [
        'installed_version' => $packageUpdate['installed_version'] ?? null,
        'remote_version' => $packageUpdate['remote_version'] ?? null,
        'update_available' => !empty($packageUpdate['update_available']),
        'up_to_date' => !empty($packageUpdate['up_to_date']),
        'ready' => !empty($packageUpdate['ready']),
        'manifest_error' => $packageUpdate['manifest_error'] ?? null,
        'checks' => $packageUpdate['checks'] ?? [],
        'release_notes' => $packageUpdate['release_notes'] ?? [],
        'last_update' => $packageUpdate['last_update'] ?? null,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;