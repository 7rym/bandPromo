<?php
/**
 * CSRF Token Management Helper
 *
 * Protects against Cross-Site Request Forgery attacks
 *
 * Usage:
 *   generate_csrf_token() - Create and store in session
 *   get_csrf_token() - Retrieve from session for form (rotates if expired)
 *   validate_csrf_token($token) - Validate token from POST/request
 */

/** Max age for a CSRF token while the admin session remains valid. */
const BANDPROMO_CSRF_MAX_AGE_SECONDS = 43200; // 12 hours

/**
 * Generate a new CSRF token and store in session
 * Should be called once per login/session
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * True when the session holds a non-expired CSRF token.
 */
function bandpromo_csrf_token_is_fresh(): bool
{
    if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        return false;
    }
    if (!isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    return (time() - (int) $_SESSION['csrf_token_time']) <= BANDPROMO_CSRF_MAX_AGE_SECONDS;
}

/**
 * Get current CSRF token from session.
 * Rotates automatically when missing or older than BANDPROMO_CSRF_MAX_AGE_SECONDS
 * so admin refresh endpoints never hand out a token that validate() will reject.
 */
function get_csrf_token() {
    if (bandpromo_csrf_token_is_fresh()) {
        return $_SESSION['csrf_token'];
    }

    return regenerate_csrf_token();
}

/**
 * Validate CSRF token from request
 *
 * @param string $token Token to validate (usually from POST data)
 * @return bool True if token is valid, false otherwise
 */
function validate_csrf_token($token) {
    if (!is_string($token) || $token === '') {
        return false;
    }

    // Token must exist in session
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    // Token must match session token exactly
    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        return false;
    }

    // Token must not be older than the configured max age
    if (!bandpromo_csrf_token_is_fresh()) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }

    return true;
}

/**
 * Regenerate CSRF token after login for security
 * This invalidates any old tokens
 */
function regenerate_csrf_token() {
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
    return generate_csrf_token();
}

/**
 * Clear CSRF token on logout
 */
function clear_csrf_token() {
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
}
