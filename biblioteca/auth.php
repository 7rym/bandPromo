<?php
/**
 * Authentication Library
 * Handles user authentication against terces file
 * File format: username:md5hash:role
 * role = 'admin' | 'developer' | 'user' (optional; missing role defaults to 'user')
 * Migration: if NO entry has role='admin' or role='developer', all authenticated users may access admin (backwards-compat mode)
 */

// Path to terces file (outside web root for security)
define('TERCES_FILE', __DIR__ . '/../data/terces');

function _normalize_terces_role(string $role): string {
    $role = strtolower(trim($role));
    return in_array($role, ['admin', 'developer', 'user'], true) ? $role : 'user';
}

function _admin_capable_roles(): array {
    return ['admin', 'developer'];
}

/**
 * Parse a single terces line into [username, hash, role]
 */
function _parse_terces_line(string $line): array {
    $parts = explode(':', trim($line), 3);
    return [
        'username' => $parts[0] ?? '',
        'hash'     => $parts[1] ?? '',
        'role'     => _normalize_terces_role($parts[2] ?? 'user'),
    ];
}

function getUserRole($username) {
    $username = trim((string) $username);
    if ($username === '' || !file_exists(TERCES_FILE)) return 'user';

    $lines = file(TERCES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return 'user';

    foreach ($lines as $line) {
        $u = _parse_terces_line($line);
        if ($u['username'] === $username) {
            return $u['role'];
        }
    }

    return 'user';
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
 * Returns true if the username may access the admin panel.
 * Migration mode: if no user has role=admin or role=developer yet, everyone who authenticated counts as admin
 * (so no one gets locked out after upgrading).
 */
function isAdminUser($username) {
    if (!file_exists(TERCES_FILE)) return false;
    $lines = file(TERCES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return false;

    $admins = [];
    foreach ($lines as $line) {
        $u = _parse_terces_line($line);
        if (in_array($u['role'], _admin_capable_roles(), true)) $admins[] = $u['username'];
    }

    // Migration mode: no privileged roles defined yet → all authenticated users are treated as admin
    if (empty($admins)) return true;

    return in_array(trim($username), $admins, true);
}

function isDeveloperUser($username) {
    return getUserRole($username) === 'developer';
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
    if (!in_array($role, ['admin', 'developer', 'user'], true)) return false;
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

function bandpromo_ensure_session_started(bool $readAndClose = false): void {
    if (!function_exists('bandpromo_configure_session_storage')) {
        require_once __DIR__ . '/https.php';
    }
    bandpromo_configure_session_storage();

    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    if ($readAndClose) {
        // Auth-only reads: load $_SESSION and release the lock immediately.
        session_start(['read_and_close' => true]);
        return;
    }

    session_start();
}

function bandpromo_is_authenticated_session(): bool {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function bandpromo_send_auth_failure(int $status, bool $json, string $message): void {
    http_response_code($status);
    if ($json) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
    exit;
}

function bandpromo_require_authenticated_session(bool $json = true, bool $readOnly = false): void {
    bandpromo_ensure_session_started($readOnly);
    if (!bandpromo_is_authenticated_session()) {
        bandpromo_send_auth_failure(401, $json, 'Unauthorized');
    }
}

function bandpromo_require_admin_session(bool $json = true, bool $readOnly = true): void {
    bandpromo_require_authenticated_session($json, $readOnly);
    $username = trim((string) ($_SESSION['username'] ?? ''));
    if ($username === '' || !isAdminUser($username)) {
        bandpromo_send_auth_failure(403, $json, 'Admin privileges required');
    }
}

