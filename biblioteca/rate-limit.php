<?php
/**
 * Rate Limiting System
 * Phase 5: Protect against submission spam and brute force attacks
 * 
 * Two-tier approach:
 * 1. Per-user: Max 5 quiz submissions per minute
 * 2. Per-IP: Max 100 requests per minute
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

define('RATE_LIMIT_USER_SUBMISSIONS', 5); // Max score submissions per user per minute
define('RATE_LIMIT_IP_REQUESTS', 100); // Max total requests per IP per minute
define('RATE_LIMIT_WINDOW', 60); // Time window in seconds (1 minute)

/**
 * Check if user has exceeded submission rate limit
 * Returns: ['allowed' => bool, 'remaining' => int, 'reset_at' => timestamp]
 */
function check_submission_rate_limit() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return ['allowed' => false, 'error' => 'Not authenticated'];
    }
    
    $username = $_SESSION['username'];
    $session_key = 'rate_limit_submissions_' . $username;
    
    // Initialize or get existing rate limit data
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [
            'count' => 0,
            'start_time' => time(),
            'requests' => []
        ];
    }
    
    $rate_data = &$_SESSION[$session_key];
    $now = time();
    $window_start = $now - RATE_LIMIT_WINDOW;
    
    // Clean old requests outside the window
    $rate_data['requests'] = array_filter($rate_data['requests'], function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    $current_count = count($rate_data['requests']);
    $remaining = max(0, RATE_LIMIT_USER_SUBMISSIONS - $current_count);
    
    // Calculate when next submission will be allowed
    if ($current_count >= RATE_LIMIT_USER_SUBMISSIONS) {
        $oldest_request = min($rate_data['requests']);
        $reset_at = $oldest_request + RATE_LIMIT_WINDOW;
        
        return [
            'allowed' => false,
            'error' => 'Too many submissions',
            'remaining' => 0,
            'reset_at' => $reset_at,
            'wait_seconds' => max(0, $reset_at - $now)
        ];
    }
    
    // Add current request to history
    $rate_data['requests'][] = $now;
    $remaining = RATE_LIMIT_USER_SUBMISSIONS - count($rate_data['requests']);
    
    return [
        'allowed' => true,
        'remaining' => $remaining,
        'reset_at' => $now + RATE_LIMIT_WINDOW
    ];
}

/**
 * Check if IP has exceeded total request rate limit
 * Returns: ['allowed' => bool, 'remaining' => int, 'reset_at' => timestamp]
 */
function check_ip_rate_limit() {
    $client_ip = get_client_ip();
    $session_key = 'rate_limit_ip_' . md5($client_ip); // Hash IP for privacy in logs
    
    // Initialize or get existing rate limit data
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [
            'requests' => [],
            'start_time' => time()
        ];
    }
    
    $rate_data = &$_SESSION[$session_key];
    $now = time();
    $window_start = $now - RATE_LIMIT_WINDOW;
    
    // Clean old requests outside the window
    $rate_data['requests'] = array_filter($rate_data['requests'], function($timestamp) use ($window_start) {
        return $timestamp > $window_start;
    });
    
    $current_count = count($rate_data['requests']);
    $remaining = max(0, RATE_LIMIT_IP_REQUESTS - $current_count);
    
    // Calculate when next request will be allowed
    if ($current_count >= RATE_LIMIT_IP_REQUESTS) {
        $oldest_request = min($rate_data['requests']);
        $reset_at = $oldest_request + RATE_LIMIT_WINDOW;
        
        return [
            'allowed' => false,
            'error' => 'IP rate limit exceeded',
            'remaining' => 0,
            'reset_at' => $reset_at,
            'wait_seconds' => max(0, $reset_at - $now)
        ];
    }
    
    // Add current request to history
    $rate_data['requests'][] = $now;
    $remaining = RATE_LIMIT_IP_REQUESTS - count($rate_data['requests']);
    
    return [
        'allowed' => true,
        'remaining' => $remaining,
        'reset_at' => $now + RATE_LIMIT_WINDOW
    ];
}

/**
 * Get client IP address
 * Handles proxies and forwarded IPs
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        // Cloudflare
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Other proxies
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}

/**
 * Clear rate limit for user (called on logout or manual reset)
 */
function clear_submission_rate_limit($username = null) {
    if (!$username && isset($_SESSION['username'])) {
        $username = $_SESSION['username'];
    }
    
    if (!$username) {
        return false;
    }
    
    $session_key = 'rate_limit_submissions_' . $username;
    unset($_SESSION[$session_key]);
    return true;
}

/**
 * Clear IP rate limit (rarely needed)
 */
function clear_ip_rate_limit() {
    $client_ip = get_client_ip();
    $session_key = 'rate_limit_ip_' . md5($client_ip);
    unset($_SESSION[$session_key]);
    return true;
}
