<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Writable session start so each ping refreshes the PHP session file mtime.
// read_and_close alone does not extend gc_maxlifetime on the files handler.
bandpromo_require_authenticated_session(true, false);
$_SESSION['_last_auth_ping'] = time();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
