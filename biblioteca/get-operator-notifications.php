<?php
require_once __DIR__ . '/admin-api-guard.php';

require_once __DIR__ . '/build-required.php';

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

echo json_encode([
    'ok' => true,
    'build_required' => !empty($buildState['required']),
    'build_required_state' => $buildState,
    'metadata_validation' => $metadataValidation,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;