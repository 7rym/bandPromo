<?php
/**
 * Shared guard for admin-only biblioteca API endpoints.
 */
require_once __DIR__ . '/auth.php';
bandpromo_require_admin_session(true);
