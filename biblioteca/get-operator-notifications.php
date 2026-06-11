<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

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