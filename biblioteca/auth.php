<?php
/**
 * Authentication Library
 * Handles user authentication against terces file
 * File format: username:md5hash:role
 * role = 'admin' | 'user' (optional; missing role defaults to 'user')
 * Migration: if NO entry has role='admin', all authenticated users may access admin (backwards-compat mode)
 */

// Path to terces file (outside web root for security)
define('TERCES_FILE', __DIR__ . '/../data/terces');

/**
 * Parse a single terces line into [username, hash, role]
 */
function _parse_terces_line(string $line): array {
    $parts = explode(':', trim($line), 3);
    return [
        'username' => $parts[0] ?? '',
        'hash'     => $parts[1] ?? '',
        'role'     => $parts[2] ?? 'user',
    ];
}

/**
 * Authenticate user against terces file
 */
function authenticate($username, $password) {
    if (empty($username) || empty($password)) return false;
    if (!file_exists(TERCES_FILE)) return false;

    $lines = file(TERCES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return false;

    $passwordHash = md5($password);
    foreach ($lines as $line) {
        $u = _parse_terces_line($line);
        if ($u['username'] === trim($username) && $u['hash'] === trim($passwordHash)) {
            return true;
        }
    }
    return false;
}

/**
 * Returns true if the username is designated as admin.
 * Migration mode: if no user has role=admin yet, everyone who authenticated counts as admin
 * (so no one gets locked out after upgrading).
 */
function isAdminUser($username) {
    if (!file_exists(TERCES_FILE)) return false;
    $lines = file(TERCES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return false;

    $admins = [];
    foreach ($lines as $line) {
        $u = _parse_terces_line($line);
        if ($u['role'] === 'admin') $admins[] = $u['username'];
    }

    // Migration mode: no admins defined yet → all authenticated users are treated as admin
    if (empty($admins)) return true;

    return in_array(trim($username), $admins, true);
}

/**
 * Get list of all users with their roles
 * Returns array of ['username' => ..., 'role' => ...]
 */
function getAllUsers() {
    if (!file_exists(TERCES_FILE)) return [];
    $lines = file(TERCES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return [];

    $users = [];
    foreach ($lines as $line) {
        $u = _parse_terces_line($line);
        if (!empty($u['username'])) {
            $users[] = ['username' => $u['username'], 'role' => $u['role']];
        }
    }
    return $users;
}

/**
 * Add or update user (preserves existing role if not specified)
 */
function setUser($username, $password, $role = null) {
    if (empty($username) || empty($password)) return false;

    $username     = trim($username);
    $passwordHash = md5($password);

    $lines = [];
    if (file_exists(TERCES_FILE)) {
        $lines = file(TERCES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    // Preserve existing role if none specified
    $existingRole = 'user';
    $lines = array_filter($lines, function($line) use ($username, &$existingRole) {
        $u = _parse_terces_line($line);
        if ($u['username'] === $username) {
            $existingRole = $u['role'];
            return false;
        }
        return true;
    });

    $finalRole = $role ?? $existingRole;
    $lines[]   = "$username:$passwordHash:$finalRole";

    return file_put_contents(TERCES_FILE, implode("\n", array_values($lines)) . "\n") !== false;
}

/**
 * Set role for an existing user
 */
function setUserRole($username, $role) {
    if (!in_array($role, ['admin', 'user'], true)) return false;
    if (!file_exists(TERCES_FILE)) return false;

    $username = trim($username);
    $lines = file(TERCES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $updated = false;
    $lines = array_map(function($line) use ($username, $role, &$updated) {
        $u = _parse_terces_line($line);
        if ($u['username'] === $username) {
            $updated = true;
            return "{$u['username']}:{$u['hash']}:$role";
        }
        return $line;
    }, $lines);

    if (!$updated) return false;
    return file_put_contents(TERCES_FILE, implode("\n", $lines) . "\n") !== false;
}

/**
 * Delete user from terces file
 */
function deleteUser($username) {
    if (empty($username) || !file_exists(TERCES_FILE)) return false;

    $username = trim($username);
    $lines = file(TERCES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_filter($lines, function($line) use ($username) {
        $u = _parse_terces_line($line);
        return $u['username'] !== $username;
    });

    return file_put_contents(TERCES_FILE, implode("\n", array_values($lines)) . "\n") !== false;
}

/**
 * Change user password
 */
function changePassword($username, $newPassword) {
    return setUser($username, $newPassword);
}

