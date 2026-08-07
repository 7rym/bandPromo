<?php
/**
 * Shared guard for admin-only biblioteca API endpoints.
 */
require_once __DIR__ . '/auth.php';
// read_and_close: authenticate without holding a session write lock for the request body.
bandpromo_require_admin_session(true, true);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
