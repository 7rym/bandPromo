<?php
/**
 * Admin audit logging helpers.
 *
 * Stores admin-only mutation records separately from listener activity logs.
 */

require_once __DIR__ . '/time-helpers.php';
require_once __DIR__ . '/activity-store.php';

function bandpromo_admin_audit_dir(): string
{
    return dirname(__DIR__) . '/log/admin-audit';
}

function bandpromo_admin_audit_ensure_dir(): string
{
    $dir = bandpromo_admin_audit_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function bandpromo_admin_audit_file_for_date(string $date): string
{
    return bandpromo_admin_audit_ensure_dir() . '/' . $date . '.log';
}

function bandpromo_admin_audit_client_ip(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return (string) $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return (string) $_SERVER['REMOTE_ADDR'];
    }
    return 'unknown';
}

function bandpromo_admin_audit_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
}

function bandpromo_admin_audit_actor(): string
{
    $username = $_SESSION['username'] ?? 'unknown';
    return trim((string) $username) !== '' ? (string) $username : 'unknown';
}

function bandpromo_admin_audit_sanitize_data(array $data): array
{
    unset($data['password'], $data['new_password'], $data['edit_password'], $data['raw'], $data['body'], $data['html']);
    $sanitized = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = $value;
            continue;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            $sanitized[$key] = $value;
            continue;
        }
        $text = trim((string) $value);
        if (strlen($text) > 500) {
            $text = substr($text, 0, 500) . '…';
        }
        $sanitized[$key] = $text;
    }
    return $sanitized;
}

function bandpromo_admin_audit_log(string $action, array $context = []): bool
{
    try {
        $overrideActor = trim((string) ($context['actor'] ?? ''));
        $overrideIp = trim((string) ($context['ip'] ?? ''));
        $overrideUserAgent = trim((string) ($context['user_agent'] ?? ''));
        $entry = [
            'timestamp' => bandpromo_utc_now_iso(),
            'timestamp_unix' => bandpromo_utc_now_unix(),
            'actor' => $overrideActor !== '' ? $overrideActor : bandpromo_admin_audit_actor(),
            'action' => trim($action),
            'target_type' => trim((string) ($context['target_type'] ?? '')),
            'target_id' => trim((string) ($context['target_id'] ?? '')),
            'status' => trim((string) ($context['status'] ?? 'ok')),
            'ip' => $overrideIp !== '' ? $overrideIp : bandpromo_admin_audit_client_ip(),
            'user_agent' => $overrideUserAgent !== '' ? $overrideUserAgent : bandpromo_admin_audit_user_agent(),
            'data' => bandpromo_admin_audit_sanitize_data(is_array($context['data'] ?? null) ? $context['data'] : []),
        ];

        return bandpromo_activity_store_append_audit(dirname(__DIR__), $entry);
    } catch (Throwable $e) {
        error_log('bandPromo admin audit error: ' . $e->getMessage());
        return false;
    }
}

function bandpromo_admin_audit_iter_dates(string $startDate, string $endDate): array
{
    $start = DateTimeImmutable::createFromFormat('Y-m-d', $startDate) ?: new DateTimeImmutable('today');
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $endDate) ?: new DateTimeImmutable('today');

    if ($end < $start) {
        [$start, $end] = [$end, $start];
    }

    $dates = [];
    for ($current = $start; $current <= $end; $current = $current->modify('+1 day')) {
        $dates[] = $current->format('Y-m-d');
    }
    return $dates;
}

function bandpromo_admin_audit_read_entries(string $startDate, string $endDate, string $action = '', string $actor = '', int $limit = 200, int $offset = 0): array
{
    try {
        return bandpromo_activity_store_fetch_audit_entries(
            dirname(__DIR__),
            $startDate,
            $endDate,
            $action,
            $actor,
            $limit,
            $offset
        );
    } catch (Throwable $e) {
        error_log('bandPromo admin audit read error: ' . $e->getMessage());
        return ['entries' => [], 'total' => 0];
    }
}

function bandpromo_admin_audit_get_action_types(string $startDate, string $endDate): array
{
    try {
        return bandpromo_activity_store_distinct_audit_actions(dirname(__DIR__), $startDate, $endDate);
    } catch (Throwable $e) {
        error_log('bandPromo admin audit action lookup error: ' . $e->getMessage());
        return [];
    }
}

function bandpromo_admin_audit_get_actors(string $startDate, string $endDate): array
{
    try {
        return bandpromo_activity_store_distinct_audit_actors(dirname(__DIR__), $startDate, $endDate);
    } catch (Throwable $e) {
        error_log('bandPromo admin audit actor lookup error: ' . $e->getMessage());
        return [];
    }
}

function bandpromo_admin_audit_format_detail(array $entry): string
{
    $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
    $action = trim((string) ($entry['action'] ?? ''));

    switch ($action) {
        case 'admin_login':
            return 'Signed in to admin panel';

        case 'admin_logout':
            return 'Signed out from admin panel';

        case 'admin_login_failed':
            $reason = trim((string) ($data['reason'] ?? 'unknown'));
            $username = trim((string) ($data['username'] ?? ''));
            $parts = [];
            if ($username !== '') {
                $parts[] = 'username: ' . $username;
            }
            $parts[] = 'reason: ' . str_replace('_', ' ', $reason);
            return implode(' | ', $parts);

        case 'user_added':
            $role = trim((string) ($data['role'] ?? 'user'));
            return 'Created admin user with role ' . $role;

        case 'password_changed':
            return 'Password updated';

        case 'user_deleted':
            return 'Deleted admin user';

        case 'user_role_changed':
            $newRole = trim((string) ($data['new_role'] ?? ''));
            return $newRole !== '' ? 'Role changed to ' . $newRole : 'Role changed';

        case 'page_saved':
            $parts = [];
            if (array_key_exists('sanitized', $data)) {
                $parts[] = !empty($data['sanitized']) ? 'sanitized' : 'no sanitization needed';
            }
            if (isset($data['input_bytes'])) {
                $parts[] = 'input ' . number_format((int) $data['input_bytes']) . ' B';
            }
            if (isset($data['saved_bytes'])) {
                $parts[] = 'saved ' . number_format((int) $data['saved_bytes']) . ' B';
            }
            return !empty($parts) ? implode(' | ', $parts) : 'Page content saved';

        case 'build_started':
            $mode = trim((string) ($data['mode'] ?? ($entry['target_id'] ?? 'full')));
            return ucfirst($mode) . ' build started';

        case 'build_completed':
            $mode = trim((string) ($data['mode'] ?? ($entry['target_id'] ?? 'full')));
            $parts = [ucfirst($mode) . ' build finished'];
            if (isset($data['duration_seconds'])) {
                $parts[] = 'duration ' . number_format((int) $data['duration_seconds']) . ' s';
            }
            return implode(' | ', $parts);

        case 'build_failed':
            $mode = trim((string) ($data['mode'] ?? ($entry['target_id'] ?? 'full')));
            $parts = [ucfirst($mode) . ' build failed'];
            if (isset($data['exit_code'])) {
                $parts[] = 'exit code ' . (int) $data['exit_code'];
            }
            if (isset($data['duration_seconds'])) {
                $parts[] = 'duration ' . number_format((int) $data['duration_seconds']) . ' s';
            }
            return implode(' | ', $parts);
    }

    if (empty($data)) {
        return '—';
    }

    $parts = [];
    foreach ($data as $key => $value) {
        $label = str_replace('_', ' ', (string) $key);
        if (is_array($value)) {
            $parts[] = $label . ': ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            continue;
        }
        if (is_bool($value)) {
            $parts[] = $label . ': ' . ($value ? 'yes' : 'no');
            continue;
        }
        if ($value === null || $value === '') {
            continue;
        }
        $parts[] = $label . ': ' . $value;
    }

    return !empty($parts)
        ? implode(' | ', $parts)
        : htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}