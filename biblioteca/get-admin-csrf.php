<?php
require_once __DIR__ . '/https.php';
require_once __DIR__ . '/csrf.php';
bandpromo_enforce_https();

require_once __DIR__ . '/admin-api-guard.php';

echo json_encode([
    'ok' => true,
    'csrf_token' => get_csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);