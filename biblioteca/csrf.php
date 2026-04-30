<?php
/**
 * CSRF Token Management Helper
 * 
 * Protects against Cross-Site Request Forgery attacks
 * 
 * Usage:
 *   generate_csrf_token() - Create and store in session
 *   get_csrf_token() - Retrieve from session for form
 *   validate_csrf_token($token) - Validate token from POST/request
 */

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
 * Get current CSRF token from session
 */
function get_csrf_token() {
    if (isset($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    }
    return generate_csrf_token();
}

/**
 * Validate CSRF token from request
 * 
 * @param string $token Token to validate (usually from POST data)
 * @return bool True if token is valid, false otherwise
 */
function validate_csrf_token($token) {
    // Token must exist in session
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Token must match session token exactly
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    
    // Token must not be older than 1 hour
    $token_age = time() - $_SESSION['csrf_token_time'];
    if ($token_age > 3600) {
        // Token expired
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
?>
