<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

bandpromo_require_authenticated_session(true, true);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
