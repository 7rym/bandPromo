<?php
require_once __DIR__ . '/admin-api-guard.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/build-required.php';

$state = bandpromo_get_build_required_state();
echo json_encode([
    'ok' => true,
    'build_required' => !empty($state['required']),
    'build_required_state' => $state,
], JSON_UNESCAPED_UNICODE);
exit;
