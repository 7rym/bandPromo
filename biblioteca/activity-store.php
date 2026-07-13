<?php
declare(strict_types=1);

require_once __DIR__ . '/time-helpers.php';
require_once __DIR__ . '/environment-checks.php';

function bandpromo_activity_store_root(string $root): string
{
    return rtrim($root, '/\\') . '/data/analytics';
}

function bandpromo_activity_store_db_path(string $root): string
{
    return bandpromo_activity_store_root($root) . '/events.sqlite';
}

function bandpromo_activity_store_legacy_listener_dir(string $root): string
{
    return rtrim($root, '/\\') . '/log';
}

function bandpromo_activity_store_legacy_audit_dir(string $root): string
{
    return bandpromo_activity_store_legacy_listener_dir($root) . '/admin-audit';
}

function bandpromo_activity_store_ensure_directory(string $root): void
{
    $dir = bandpromo_activity_store_root($root);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, <<<'HTACCESS'
# Block direct HTTP access to analytics database files
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS
        );
    }
}

function bandpromo_activity_store_open(string $root): PDO
{
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException(bandpromo_environment_pdo_sqlite_setup_error());
    }

    bandpromo_activity_store_ensure_directory($root);
    $pdo = new PDO('sqlite:' . bandpromo_activity_store_db_path($root), null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA synchronous=NORMAL');
    bandpromo_activity_store_init_schema($pdo);

    return $pdo;
}

function bandpromo_activity_store_init_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS schema_meta (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS listener_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ts_utc INTEGER NOT NULL,
    timestamp_iso TEXT NOT NULL,
    username TEXT NOT NULL DEFAULT '',
    activity TEXT NOT NULL,
    ip TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    data_json TEXT NOT NULL DEFAULT '{}'
);

CREATE INDEX IF NOT EXISTS idx_listener_events_ts ON listener_events (ts_utc);
CREATE INDEX IF NOT EXISTS idx_listener_events_username_ts ON listener_events (username, ts_utc);
CREATE INDEX IF NOT EXISTS idx_listener_events_activity_ts ON listener_events (activity, ts_utc);

CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ts_utc INTEGER NOT NULL,
    timestamp_iso TEXT NOT NULL,
    actor TEXT NOT NULL DEFAULT '',
    action TEXT NOT NULL,
    target_type TEXT NOT NULL DEFAULT '',
    target_id TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'ok',
    ip TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    data_json TEXT NOT NULL DEFAULT '{}'
);

CREATE INDEX IF NOT EXISTS idx_audit_events_ts ON audit_events (ts_utc);
CREATE INDEX IF NOT EXISTS idx_audit_events_action_ts ON audit_events (action, ts_utc);
CREATE INDEX IF NOT EXISTS idx_audit_events_actor_ts ON audit_events (actor, ts_utc);

CREATE TABLE IF NOT EXISTS rollup_hourly (
    bucket_start_utc INTEGER NOT NULL,
    activity TEXT NOT NULL,
    event_count INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (bucket_start_utc, activity)
);

CREATE TABLE IF NOT EXISTS rollup_daily_user (
    day_key TEXT NOT NULL,
    username TEXT NOT NULL,
    sessions INTEGER NOT NULL DEFAULT 0,
    play_count INTEGER NOT NULL DEFAULT 0,
    listening_seconds INTEGER NOT NULL DEFAULT 0,
    event_count INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (day_key, username)
);

CREATE TABLE IF NOT EXISTS rollup_daily_track (
    day_key TEXT NOT NULL,
    track_key TEXT NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    artist TEXT NOT NULL DEFAULT '',
    play_count INTEGER NOT NULL DEFAULT 0,
    total_seconds INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (day_key, track_key)
);

CREATE TABLE IF NOT EXISTS rollup_daily_device (
    day_key TEXT NOT NULL,
    device TEXT NOT NULL,
    event_count INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (day_key, device)
);

CREATE TABLE IF NOT EXISTS rollup_daily_totals (
    day_key TEXT NOT NULL PRIMARY KEY,
    sessions INTEGER NOT NULL DEFAULT 0,
    play_count INTEGER NOT NULL DEFAULT 0,
    listening_seconds INTEGER NOT NULL DEFAULT 0,
    event_count INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS rollup_daily_activity (
    day_key TEXT NOT NULL,
    activity TEXT NOT NULL,
    event_count INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (day_key, activity)
);
SQL
    );

    if (bandpromo_activity_store_get_meta($pdo, 'schema_version', '') === '') {
        bandpromo_activity_store_set_meta($pdo, 'schema_version', '1');
    }
    bandpromo_activity_store_migrate_schema($pdo);
}

function bandpromo_activity_store_migrate_schema(PDO $pdo): void
{
    $version = (int) bandpromo_activity_store_get_meta($pdo, 'schema_version', '0');
    if ($version >= 2) {
        return;
    }

    bandpromo_activity_store_rebuild_daily_rollups($pdo);
    bandpromo_activity_store_set_meta($pdo, 'schema_version', '2');
}

function bandpromo_activity_store_is_unique_violation(PDOException $exception): bool
{
    if ($exception->getCode() === '23000') {
        return true;
    }

    return str_contains($exception->getMessage(), 'UNIQUE constraint failed');
}

function bandpromo_activity_store_set_meta(PDO $pdo, string $key, string $value): void
{
    $update = $pdo->prepare('UPDATE schema_meta SET value = :value WHERE key = :key');
    $update->execute(['key' => $key, 'value' => $value]);
    if ($update->rowCount() > 0) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO schema_meta (key, value) VALUES (:key, :value)');
    try {
        $insert->execute(['key' => $key, 'value' => $value]);
    } catch (PDOException $exception) {
        if (!bandpromo_activity_store_is_unique_violation($exception)) {
            throw $exception;
        }

        $update = $pdo->prepare('UPDATE schema_meta SET value = :value WHERE key = :key');
        $update->execute(['key' => $key, 'value' => $value]);
    }
}

function bandpromo_activity_store_get_meta(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT value FROM schema_meta WHERE key = :key LIMIT 1');
    $stmt->execute(['key' => $key]);
    $value = $stmt->fetchColumn();

    return is_string($value) ? $value : $default;
}

function bandpromo_activity_store_ensure_ready(string $root): PDO
{
    static $connections = [];
    if (isset($connections[$root])) {
        return $connections[$root];
    }

    $pdo = bandpromo_activity_store_open($root);
    try {
        bandpromo_activity_store_migrate_legacy_files($root, $pdo);
    } catch (Throwable $e) {
        error_log('bandPromo activity log migration failed: ' . $e->getMessage());
    }
    bandpromo_activity_store_maybe_run_maintenance($root, $pdo);
    $connections[$root] = $pdo;

    return $pdo;
}

function bandpromo_activity_store_operator_status_message(array $status): string
{
    if (!empty($status['ok'])) {
        return '';
    }

    $raw = trim((string) ($status['message'] ?? ''));
    if (stripos($raw, 'pdo_sqlite') !== false || stripos($raw, 'PDO SQLite') !== false) {
        return 'This server is missing PHP SQLite support (pdo_sqlite). Ask your hosting provider to enable it so listener activity and analytics can be stored.';
    }
    if (stripos($raw, 'syntax error') !== false && stripos($raw, 'SQLSTATE') !== false) {
        return 'bandPromo could not initialize the local activity database on this server. Contact support with the technical message below if it persists after reloading admin.';
    }
    if ($raw === 'Legacy JSON log files are still present and need import.') {
        return 'bandPromo is still upgrading older activity log files to the new local database. Reload this page in a moment; if the message stays, contact support before deleting anything in log/.';
    }
    if (str_starts_with($raw, 'Legacy listener log could not be parsed:') || str_starts_with($raw, 'Legacy audit log could not be parsed:')) {
        return 'bandPromo found older activity log files that could not be read cleanly. Analytics may be incomplete until those files are repaired or removed with support help.';
    }
    if ($raw === 'Listener log import verification failed.' || $raw === 'Admin audit log import verification failed.') {
        return 'bandPromo could not verify the upgrade of older activity logs into the new local database. Your original log files were kept; retry by reloading admin or contact support.';
    }

    return $raw !== ''
        ? $raw
        : 'Activity logging needs attention on this server.';
}

function bandpromo_activity_store_migration_status(string $root): array
{
    try {
        $pdo = bandpromo_activity_store_open($root);
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => $e->getMessage(),
        ];
    }

    $failed = bandpromo_activity_store_get_meta($pdo, 'legacy_import_failed', '');
    if ($failed !== '') {
        return [
            'ok' => false,
            'message' => $failed,
        ];
    }

    $legacyListener = bandpromo_activity_store_find_legacy_listener_files($root);
    $legacyAudit = bandpromo_activity_store_find_legacy_audit_files($root);
    if (
        bandpromo_activity_store_get_meta($pdo, 'legacy_import_completed_at', '') !== ''
        && ($legacyListener !== [] || $legacyAudit !== [])
    ) {
        bandpromo_activity_store_delete_legacy_daily_files(array_merge($legacyListener, $legacyAudit));
        $legacyListener = bandpromo_activity_store_find_legacy_listener_files($root);
        $legacyAudit = bandpromo_activity_store_find_legacy_audit_files($root);
    }

    if ($legacyListener !== [] || $legacyAudit !== []) {
        return [
            'ok' => false,
            'message' => 'Legacy JSON log files are still present and need import.',
            'legacy_listener_files' => count($legacyListener),
            'legacy_audit_files' => count($legacyAudit),
        ];
    }

    return [
        'ok' => true,
        'message' => '',
        'imported_at' => bandpromo_activity_store_get_meta($pdo, 'legacy_import_completed_at', ''),
    ];
}

function bandpromo_activity_store_find_legacy_listener_files(string $root): array
{
    $dir = bandpromo_activity_store_legacy_listener_dir($root);
    if (!is_dir($dir)) {
        return [];
    }

    $files = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}\.log$/', $entry)) {
            continue;
        }
        $files[] = $dir . '/' . $entry;
    }
    sort($files, SORT_STRING);

    return $files;
}

function bandpromo_activity_store_find_legacy_audit_files(string $root): array
{
    $dir = bandpromo_activity_store_legacy_audit_dir($root);
    if (!is_dir($dir)) {
        return [];
    }

    $files = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}\.log$/', $entry)) {
            continue;
        }
        $files[] = $dir . '/' . $entry;
    }
    sort($files, SORT_STRING);

    return $files;
}

function bandpromo_activity_store_delete_legacy_daily_files(array $files): void
{
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function bandpromo_activity_store_migrate_legacy_files(string $root, PDO $pdo): void
{
    $listenerFiles = bandpromo_activity_store_find_legacy_listener_files($root);
    $auditFiles = bandpromo_activity_store_find_legacy_audit_files($root);
    if ($listenerFiles === [] && $auditFiles === []) {
        return;
    }

    if (bandpromo_activity_store_get_meta($pdo, 'legacy_import_completed_at', '') !== '') {
        // Import already finished on a prior request; daily JSONL files should be gone.
        // Remove any orphans left behind (for example a failed unlink or dev-only debris).
        bandpromo_activity_store_delete_legacy_daily_files(array_merge($listenerFiles, $auditFiles));
        return;
    }

    $listenerSourceCount = 0;
    $auditSourceCount = 0;

    try {
        $pdo->beginTransaction();

        $listenerDbBefore = (int) $pdo->query('SELECT COUNT(*) FROM listener_events')->fetchColumn();
        $auditDbBefore = (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();

        $insertListener = $pdo->prepare(
            'INSERT INTO listener_events (ts_utc, timestamp_iso, username, activity, ip, user_agent, data_json)
             VALUES (:ts_utc, :timestamp_iso, :username, :activity, :ip, :user_agent, :data_json)'
        );
        foreach ($listenerFiles as $file) {
            $listenerSourceCount += bandpromo_activity_store_import_listener_file($file, $insertListener);
        }

        $insertAudit = $pdo->prepare(
            'INSERT INTO audit_events (ts_utc, timestamp_iso, actor, action, target_type, target_id, status, ip, user_agent, data_json)
             VALUES (:ts_utc, :timestamp_iso, :actor, :action, :target_type, :target_id, :status, :ip, :user_agent, :data_json)'
        );
        foreach ($auditFiles as $file) {
            $auditSourceCount += bandpromo_activity_store_import_audit_file($file, $insertAudit);
        }

        $listenerDbAfter = (int) $pdo->query('SELECT COUNT(*) FROM listener_events')->fetchColumn();
        $auditDbAfter = (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();

        if ($listenerDbAfter - $listenerDbBefore < $listenerSourceCount) {
            throw new RuntimeException('Listener log import verification failed.');
        }
        if ($auditDbAfter - $auditDbBefore < $auditSourceCount) {
            throw new RuntimeException('Admin audit log import verification failed.');
        }

        bandpromo_activity_store_rebuild_hourly_rollups($pdo);
        bandpromo_activity_store_rebuild_daily_rollups($pdo);
        bandpromo_activity_store_set_meta($pdo, 'legacy_import_completed_at', bandpromo_utc_now_iso());
        bandpromo_activity_store_set_meta($pdo, 'legacy_import_listener_rows', (string) $listenerSourceCount);
        bandpromo_activity_store_set_meta($pdo, 'legacy_import_audit_rows', (string) $auditSourceCount);
        bandpromo_activity_store_set_meta($pdo, 'legacy_import_failed', '');

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        bandpromo_activity_store_set_meta($pdo, 'legacy_import_failed', $e->getMessage());
        error_log('bandPromo activity log migration failed: ' . $e->getMessage());
    }

    foreach (array_merge($listenerFiles, $auditFiles) as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function bandpromo_activity_store_strip_utf8_bom(string $line): string
{
    if (str_starts_with($line, "\xEF\xBB\xBF")) {
        return substr($line, 3);
    }

    return $line;
}

function bandpromo_activity_store_import_listener_file(string $file, PDOStatement $insert): int
{
    $count = 0;
    $skippedNonEmpty = 0;
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not read legacy listener log: ' . $file);
    }

    try {
        while (($line = fgets($handle)) !== false) {
            $line = bandpromo_activity_store_strip_utf8_bom(trim($line));
            if ($line === '' || str_starts_with($line, 'LOG_STARTED:')) {
                continue;
            }
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                $skippedNonEmpty++;
                continue;
            }
            $normalized = bandpromo_activity_store_normalize_listener_entry($entry);
            if ($normalized === null) {
                $skippedNonEmpty++;
                continue;
            }
            $insert->execute($normalized);
            $count++;
        }
    } finally {
        fclose($handle);
    }

    if ($count === 0 && $skippedNonEmpty > 0) {
        throw new RuntimeException('Legacy listener log could not be parsed: ' . $file);
    }

    return $count;
}

function bandpromo_activity_store_import_audit_file(string $file, PDOStatement $insert): int
{
    $count = 0;
    $skippedNonEmpty = 0;
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not read legacy audit log: ' . $file);
    }

    try {
        while (($line = fgets($handle)) !== false) {
            $line = bandpromo_activity_store_strip_utf8_bom(trim($line));
            if ($line === '') {
                continue;
            }
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                $skippedNonEmpty++;
                continue;
            }
            $normalized = bandpromo_activity_store_normalize_audit_entry($entry);
            if ($normalized === null) {
                $skippedNonEmpty++;
                continue;
            }
            $insert->execute($normalized);
            $count++;
        }
    } finally {
        fclose($handle);
    }

    if ($count === 0 && $skippedNonEmpty > 0) {
        throw new RuntimeException('Legacy audit log could not be parsed: ' . $file);
    }

    return $count;
}

function bandpromo_activity_store_normalize_listener_entry(array $entry): ?array
{
    $ts = bandpromo_entry_unix_timestamp($entry);
    if ($ts <= 0) {
        return null;
    }

    $timestampIso = trim((string) ($entry['timestamp'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $timestampIso)) {
        $timestampIso = gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];

    return [
        'ts_utc' => $ts,
        'timestamp_iso' => $timestampIso,
        'username' => trim((string) ($entry['username'] ?? 'unknown')),
        'activity' => trim((string) ($entry['activity'] ?? '')),
        'ip' => trim((string) ($entry['ip'] ?? 'unknown')),
        'user_agent' => substr(trim((string) ($entry['user_agent'] ?? 'unknown')), 0, 255),
        'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ];
}

function bandpromo_activity_store_normalize_audit_entry(array $entry): ?array
{
    $ts = bandpromo_entry_unix_timestamp($entry);
    if ($ts <= 0) {
        return null;
    }

    $timestampIso = trim((string) ($entry['timestamp'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $timestampIso)) {
        $timestampIso = gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];

    return [
        'ts_utc' => $ts,
        'timestamp_iso' => $timestampIso,
        'actor' => trim((string) ($entry['actor'] ?? 'unknown')),
        'action' => trim((string) ($entry['action'] ?? '')),
        'target_type' => trim((string) ($entry['target_type'] ?? '')),
        'target_id' => trim((string) ($entry['target_id'] ?? '')),
        'status' => trim((string) ($entry['status'] ?? 'ok')),
        'ip' => trim((string) ($entry['ip'] ?? 'unknown')),
        'user_agent' => substr(trim((string) ($entry['user_agent'] ?? 'unknown')), 0, 255),
        'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ];
}

function bandpromo_activity_store_listener_row_to_entry(array $row): array
{
    $data = json_decode((string) ($row['data_json'] ?? '{}'), true);

    return [
        'timestamp' => (string) ($row['timestamp_iso'] ?? ''),
        'timestamp_unix' => (int) ($row['ts_utc'] ?? 0),
        'username' => (string) ($row['username'] ?? ''),
        'activity' => (string) ($row['activity'] ?? ''),
        'ip' => (string) ($row['ip'] ?? ''),
        'user_agent' => (string) ($row['user_agent'] ?? ''),
        'data' => is_array($data) ? $data : [],
    ];
}

function bandpromo_activity_store_audit_row_to_entry(array $row): array
{
    $data = json_decode((string) ($row['data_json'] ?? '{}'), true);

    return [
        'timestamp' => (string) ($row['timestamp_iso'] ?? ''),
        'timestamp_unix' => (int) ($row['ts_utc'] ?? 0),
        'actor' => (string) ($row['actor'] ?? ''),
        'action' => (string) ($row['action'] ?? ''),
        'target_type' => (string) ($row['target_type'] ?? ''),
        'target_id' => (string) ($row['target_id'] ?? ''),
        'status' => (string) ($row['status'] ?? 'ok'),
        'ip' => (string) ($row['ip'] ?? ''),
        'user_agent' => (string) ($row['user_agent'] ?? ''),
        'data' => is_array($data) ? $data : [],
    ];
}

function bandpromo_activity_store_append_listener(string $root, array $entry): bool
{
    $normalized = bandpromo_activity_store_normalize_listener_entry($entry);
    if ($normalized === null || $normalized['activity'] === '') {
        return false;
    }

    $pdo = bandpromo_activity_store_ensure_ready($root);
    $stmt = $pdo->prepare(
        'INSERT INTO listener_events (ts_utc, timestamp_iso, username, activity, ip, user_agent, data_json)
         VALUES (:ts_utc, :timestamp_iso, :username, :activity, :ip, :user_agent, :data_json)'
    );
    $stmt->execute($normalized);
    bandpromo_activity_store_increment_hourly_rollup($pdo, (int) $normalized['ts_utc'], (string) $normalized['activity']);
    $data = json_decode((string) $normalized['data_json'], true);
    bandpromo_activity_store_apply_daily_rollups(
        $pdo,
        $normalized,
        is_array($data) ? $data : []
    );

    return true;
}

function bandpromo_activity_store_append_audit(string $root, array $entry): bool
{
    $normalized = bandpromo_activity_store_normalize_audit_entry($entry);
    if ($normalized === null || $normalized['action'] === '') {
        return false;
    }

    $pdo = bandpromo_activity_store_ensure_ready($root);
    $stmt = $pdo->prepare(
        'INSERT INTO audit_events (ts_utc, timestamp_iso, actor, action, target_type, target_id, status, ip, user_agent, data_json)
         VALUES (:ts_utc, :timestamp_iso, :actor, :action, :target_type, :target_id, :status, :ip, :user_agent, :data_json)'
    );
    $stmt->execute($normalized);

    return true;
}

function bandpromo_activity_store_increment_hourly_rollup(PDO $pdo, int $tsUtc, string $activity): void
{
    $bucket = intdiv($tsUtc, 3600) * 3600;
    $update = $pdo->prepare(
        'UPDATE rollup_hourly SET event_count = event_count + 1
         WHERE bucket_start_utc = :bucket AND activity = :activity'
    );
    $update->execute([
        'bucket' => $bucket,
        'activity' => $activity,
    ]);
    if ($update->rowCount() > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO rollup_hourly (bucket_start_utc, activity, event_count)
         VALUES (:bucket, :activity, 1)'
    );
    try {
        $insert->execute([
            'bucket' => $bucket,
            'activity' => $activity,
        ]);
    } catch (PDOException $exception) {
        if (!bandpromo_activity_store_is_unique_violation($exception)) {
            throw $exception;
        }

        $update->execute([
            'bucket' => $bucket,
            'activity' => $activity,
        ]);
    }
}

function bandpromo_activity_store_rebuild_hourly_rollups(PDO $pdo): void
{
    $pdo->exec('DELETE FROM rollup_hourly');
    $pdo->exec(
        'INSERT INTO rollup_hourly (bucket_start_utc, activity, event_count)
         SELECT (ts_utc / 3600) * 3600 AS bucket_start_utc, activity, COUNT(*) AS event_count
         FROM listener_events
         GROUP BY bucket_start_utc, activity'
    );
}

function bandpromo_activity_store_fetch_listener_entries(
    string $root,
    ?string $dateStart,
    ?string $dateEnd,
    ?string $username = null,
    ?string $activity = null
): array {
    if ($dateStart === null) {
        $dateStart = gmdate('Y-m-d');
    }
    if ($dateEnd === null) {
        $dateEnd = $dateStart;
    }

    $bounds = bandpromo_utc_date_range_bounds($dateStart, $dateEnd);
    $pdo = bandpromo_activity_store_ensure_ready($root);

    $sql = 'SELECT ts_utc, timestamp_iso, username, activity, ip, user_agent, data_json
            FROM listener_events
            WHERE ts_utc >= :start_ts AND ts_utc <= :end_ts';
    $params = [
        'start_ts' => $bounds['start_unix'],
        'end_ts' => $bounds['end_unix'],
    ];

    if ($username !== null && $username !== '') {
        $sql .= ' AND username = :username';
        $params['username'] = $username;
    }
    if ($activity !== null && $activity !== '') {
        $sql .= ' AND activity = :activity';
        $params['activity'] = $activity;
    }

    $sql .= ' ORDER BY ts_utc ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $entries = [];
    while ($row = $stmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $entries[] = bandpromo_activity_store_listener_row_to_entry($row);
    }

    return $entries;
}

function bandpromo_activity_store_fetch_audit_entries(
    string $root,
    string $dateStart,
    string $dateEnd,
    string $action = '',
    string $actor = '',
    int $limit = 200,
    int $offset = 0
): array {
    $bounds = bandpromo_utc_date_range_bounds($dateStart, $dateEnd);
    $pdo = bandpromo_activity_store_ensure_ready($root);

    $where = ['ts_utc >= :start_ts', 'ts_utc <= :end_ts'];
    $params = [
        'start_ts' => $bounds['start_unix'],
        'end_ts' => $bounds['end_unix'],
    ];
    if (trim($action) !== '') {
        $where[] = 'action = :action';
        $params['action'] = trim($action);
    }
    if (trim($actor) !== '') {
        $where[] = 'actor = :actor';
        $params['actor'] = trim($actor);
    }

    $sql = 'SELECT COUNT(*) FROM audit_events WHERE ' . implode(' AND ', $where);
    $countStmt = $pdo->prepare($sql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = 'SELECT ts_utc, timestamp_iso, actor, action, target_type, target_id, status, ip, user_agent, data_json
            FROM audit_events
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ts_utc DESC
            LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    $entries = [];
    while ($row = $stmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $entries[] = bandpromo_activity_store_audit_row_to_entry($row);
    }

    return [
        'entries' => $entries,
        'total' => $total,
    ];
}

function bandpromo_activity_store_hourly_distribution(
    string $root,
    string $dateStart,
    string $dateEnd
): array {
    $bounds = bandpromo_utc_date_range_bounds($dateStart, $dateEnd);
    $pdo = bandpromo_activity_store_ensure_ready($root);
    $timezone = bandpromo_analytics_bucket_timezone();

    if ($timezone === 'UTC') {
        $stmt = $pdo->prepare(
            'SELECT bucket_start_utc, SUM(event_count) AS event_count
             FROM rollup_hourly
             WHERE bucket_start_utc >= :start_ts AND bucket_start_utc <= :end_ts
             GROUP BY bucket_start_utc'
        );
        $stmt->execute([
            'start_ts' => $bounds['start_unix'],
            'end_ts' => $bounds['end_unix'],
        ]);

        $distribution = array_fill(0, 24, 0);
        while ($row = $stmt->fetch()) {
            if (!is_array($row)) {
                continue;
            }
            $hour = (int) gmdate('G', (int) ($row['bucket_start_utc'] ?? 0));
            $distribution[$hour] += (int) ($row['event_count'] ?? 0);
        }
        ksort($distribution);

        return bandpromo_activity_store_hourly_distribution_fill($distribution);
    }

    $entries = bandpromo_activity_store_fetch_listener_entries($root, $dateStart, $dateEnd);
    $distribution = array_fill(0, 24, 0);
    foreach ($entries as $entry) {
        $ts = bandpromo_entry_unix_timestamp($entry);
        if ($ts <= 0) {
            continue;
        }
        $hour = bandpromo_admin_hour_from_unix($ts, $timezone);
        $distribution[$hour] = ($distribution[$hour] ?? 0) + 1;
    }
    ksort($distribution);

    return bandpromo_activity_store_hourly_distribution_fill($distribution);
}

function bandpromo_activity_store_hourly_distribution_fill(array $distribution): array
{
    for ($i = 0; $i < 24; $i++) {
        if (!isset($distribution[$i])) {
            $distribution[$i] = 0;
        }
    }
    ksort($distribution);

    return $distribution;
}

function bandpromo_activity_store_distinct_listener_activities(
    string $root,
    string $dateStart,
    string $dateEnd
): array {
    $bounds = bandpromo_utc_date_range_bounds($dateStart, $dateEnd);
    $pdo = bandpromo_activity_store_ensure_ready($root);
    $stmt = $pdo->prepare(
        'SELECT DISTINCT activity FROM listener_events
         WHERE ts_utc >= :start_ts AND ts_utc <= :end_ts
         ORDER BY activity ASC'
    );
    $stmt->execute([
        'start_ts' => $bounds['start_unix'],
        'end_ts' => $bounds['end_unix'],
    ]);

    $types = [];
    while ($activity = $stmt->fetchColumn()) {
        if (is_string($activity) && $activity !== '') {
            $types[] = $activity;
        }
    }

    return $types;
}

function bandpromo_activity_store_distinct_audit_actions(
    string $root,
    string $dateStart,
    string $dateEnd
): array {
    $bounds = bandpromo_utc_date_range_bounds($dateStart, $dateEnd);
    $pdo = bandpromo_activity_store_ensure_ready($root);
    $stmt = $pdo->prepare(
        'SELECT DISTINCT action FROM audit_events
         WHERE ts_utc >= :start_ts AND ts_utc <= :end_ts
         ORDER BY action ASC'
    );
    $stmt->execute([
        'start_ts' => $bounds['start_unix'],
        'end_ts' => $bounds['end_unix'],
    ]);

    $types = [];
    while ($action = $stmt->fetchColumn()) {
        if (is_string($action) && $action !== '') {
            $types[] = $action;
        }
    }

    return $types;
}

function bandpromo_activity_store_distinct_audit_actors(
    string $root,
    string $dateStart,
    string $dateEnd
): array {
    $bounds = bandpromo_utc_date_range_bounds($dateStart, $dateEnd);
    $pdo = bandpromo_activity_store_ensure_ready($root);
    $stmt = $pdo->prepare(
        'SELECT DISTINCT actor FROM audit_events
         WHERE ts_utc >= :start_ts AND ts_utc <= :end_ts
         ORDER BY actor ASC'
    );
    $stmt->execute([
        'start_ts' => $bounds['start_unix'],
        'end_ts' => $bounds['end_unix'],
    ]);

    $actors = [];
    while ($actor = $stmt->fetchColumn()) {
        if (is_string($actor) && $actor !== '') {
            $actors[] = $actor;
        }
    }

    return $actors;
}

function bandpromo_activity_store_raw_retention_days(): int
{
    return 90;
}

function bandpromo_activity_store_day_key(int $tsUtc): string
{
    return bandpromo_admin_day_from_unix($tsUtc, bandpromo_analytics_bucket_timezone());
}

function bandpromo_activity_store_is_track_exit_activity(string $activity): bool
{
    return $activity === 'track_exited'
        || in_array($activity, ['track_ended', 'track_change_next', 'track_change_prev', 'track_interrupted'], true);
}

function bandpromo_activity_store_is_track_progress_activity(string $activity, array $data): bool
{
    if (bandpromo_activity_store_is_track_exit_activity($activity)) {
        return true;
    }

    return $activity === 'session_end'
        && trim((string) ($data['track_title'] ?? '')) !== ''
        && isset($data['current_time']);
}

function bandpromo_activity_store_listening_seconds(string $activity, array $data): int
{
    if (!bandpromo_activity_store_is_track_progress_activity($activity, $data)) {
        return 0;
    }

    if ((int) ($data['completion_rate'] ?? 0) < 5) {
        return 0;
    }

    return (int) ($data['current_time'] ?? 0);
}

function bandpromo_activity_store_device_type(string $userAgent): string
{
    if (preg_match('/iPhone/i', $userAgent)) {
        return 'iPhone';
    }
    if (preg_match('/iPad/i', $userAgent)) {
        return 'iPad';
    }
    if (preg_match('/Android/i', $userAgent)) {
        return 'Android';
    }
    if (preg_match('/Windows Phone/i', $userAgent)) {
        return 'Windows Phone';
    }
    if (preg_match('/BlackBerry/i', $userAgent)) {
        return 'BlackBerry';
    }
    if (preg_match('/Windows|Win64|Win32/i', $userAgent)) {
        return 'Windows';
    }
    if (preg_match('/Macintosh|Mac OS/i', $userAgent)) {
        return 'macOS';
    }
    if (preg_match('/Linux/i', $userAgent)) {
        return 'Linux';
    }

    return 'Unknown';
}

function bandpromo_activity_store_track_key(array $data): string
{
    $title = strtolower(trim((string) ($data['track_title'] ?? '')));
    $artist = strtolower(trim((string) ($data['track_artist'] ?? '')));

    return $title . '|' . $artist;
}

function bandpromo_activity_store_apply_daily_rollups(PDO $pdo, array $normalized, array $data): void
{
    $dayKey = bandpromo_activity_store_day_key((int) $normalized['ts_utc']);
    $activity = (string) $normalized['activity'];
    $username = (string) $normalized['username'];
    $listeningSeconds = bandpromo_activity_store_listening_seconds($activity, $data);
    $sessionDelta = $activity === 'play_start' ? 1 : 0;
    $playDelta = $activity === 'track_started' ? 1 : 0;

    bandpromo_activity_store_upsert_daily_activity($pdo, $dayKey, $activity, 1);
    bandpromo_activity_store_upsert_daily_totals(
        $pdo,
        $dayKey,
        1,
        $sessionDelta,
        $playDelta,
        $listeningSeconds
    );
    bandpromo_activity_store_upsert_daily_user(
        $pdo,
        $dayKey,
        $username,
        1,
        $sessionDelta,
        $playDelta,
        $listeningSeconds
    );

    $userAgent = (string) ($normalized['user_agent'] ?? '');
    if ($userAgent !== '') {
        bandpromo_activity_store_upsert_daily_device(
            $pdo,
            $dayKey,
            bandpromo_activity_store_device_type($userAgent),
            1
        );
    }

    if (
        bandpromo_activity_store_is_track_progress_activity($activity, $data)
        && trim((string) ($data['track_title'] ?? '')) !== ''
        && (int) ($data['completion_rate'] ?? 0) >= 5
    ) {
        bandpromo_activity_store_upsert_daily_track(
            $pdo,
            $dayKey,
            bandpromo_activity_store_track_key($data),
            (string) ($data['track_title'] ?? ''),
            (string) ($data['track_artist'] ?? 'Unknown'),
            1,
            $listeningSeconds
        );
    }
}

function bandpromo_activity_store_upsert_daily_activity(PDO $pdo, string $dayKey, string $activity, int $delta): void
{
    $update = $pdo->prepare(
        'UPDATE rollup_daily_activity SET event_count = event_count + :delta
         WHERE day_key = :day_key AND activity = :activity'
    );
    $update->execute([
        'delta' => $delta,
        'day_key' => $dayKey,
        'activity' => $activity,
    ]);
    if ($update->rowCount() > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO rollup_daily_activity (day_key, activity, event_count)
         VALUES (:day_key, :activity, :delta)'
    );
    try {
        $insert->execute([
            'day_key' => $dayKey,
            'activity' => $activity,
            'delta' => $delta,
        ]);
    } catch (PDOException $exception) {
        if (!bandpromo_activity_store_is_unique_violation($exception)) {
            throw $exception;
        }
        $update->execute([
            'delta' => $delta,
            'day_key' => $dayKey,
            'activity' => $activity,
        ]);
    }
}

function bandpromo_activity_store_upsert_daily_totals(
    PDO $pdo,
    string $dayKey,
    int $eventDelta,
    int $sessionDelta,
    int $playDelta,
    int $listeningDelta
): void {
    $update = $pdo->prepare(
        'UPDATE rollup_daily_totals
         SET event_count = event_count + :event_delta,
             sessions = sessions + :session_delta,
             play_count = play_count + :play_delta,
             listening_seconds = listening_seconds + :listening_delta
         WHERE day_key = :day_key'
    );
    $update->execute([
        'event_delta' => $eventDelta,
        'session_delta' => $sessionDelta,
        'play_delta' => $playDelta,
        'listening_delta' => $listeningDelta,
        'day_key' => $dayKey,
    ]);
    if ($update->rowCount() > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO rollup_daily_totals (day_key, event_count, sessions, play_count, listening_seconds)
         VALUES (:day_key, :event_delta, :session_delta, :play_delta, :listening_delta)'
    );
    try {
        $insert->execute([
            'day_key' => $dayKey,
            'event_delta' => $eventDelta,
            'session_delta' => $sessionDelta,
            'play_delta' => $playDelta,
            'listening_delta' => $listeningDelta,
        ]);
    } catch (PDOException $exception) {
        if (!bandpromo_activity_store_is_unique_violation($exception)) {
            throw $exception;
        }
        $update->execute([
            'event_delta' => $eventDelta,
            'session_delta' => $sessionDelta,
            'play_delta' => $playDelta,
            'listening_delta' => $listeningDelta,
            'day_key' => $dayKey,
        ]);
    }
}

function bandpromo_activity_store_upsert_daily_user(
    PDO $pdo,
    string $dayKey,
    string $username,
    int $eventDelta,
    int $sessionDelta,
    int $playDelta,
    int $listeningDelta
): void {
    $update = $pdo->prepare(
        'UPDATE rollup_daily_user
         SET event_count = event_count + :event_delta,
             sessions = sessions + :session_delta,
             play_count = play_count + :play_delta,
             listening_seconds = listening_seconds + :listening_delta
         WHERE day_key = :day_key AND username = :username'
    );
    $update->execute([
        'event_delta' => $eventDelta,
        'session_delta' => $sessionDelta,
        'play_delta' => $playDelta,
        'listening_delta' => $listeningDelta,
        'day_key' => $dayKey,
        'username' => $username,
    ]);
    if ($update->rowCount() > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO rollup_daily_user (day_key, username, event_count, sessions, play_count, listening_seconds)
         VALUES (:day_key, :username, :event_delta, :session_delta, :play_delta, :listening_delta)'
    );
    try {
        $insert->execute([
            'day_key' => $dayKey,
            'username' => $username,
            'event_delta' => $eventDelta,
            'session_delta' => $sessionDelta,
            'play_delta' => $playDelta,
            'listening_delta' => $listeningDelta,
        ]);
    } catch (PDOException $exception) {
        if (!bandpromo_activity_store_is_unique_violation($exception)) {
            throw $exception;
        }
        $update->execute([
            'event_delta' => $eventDelta,
            'session_delta' => $sessionDelta,
            'play_delta' => $playDelta,
            'listening_delta' => $listeningDelta,
            'day_key' => $dayKey,
            'username' => $username,
        ]);
    }
}

function bandpromo_activity_store_upsert_daily_device(PDO $pdo, string $dayKey, string $device, int $delta): void
{
    $update = $pdo->prepare(
        'UPDATE rollup_daily_device SET event_count = event_count + :delta
         WHERE day_key = :day_key AND device = :device'
    );
    $update->execute([
        'delta' => $delta,
        'day_key' => $dayKey,
        'device' => $device,
    ]);
    if ($update->rowCount() > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO rollup_daily_device (day_key, device, event_count)
         VALUES (:day_key, :device, :delta)'
    );
    try {
        $insert->execute([
            'day_key' => $dayKey,
            'device' => $device,
            'delta' => $delta,
        ]);
    } catch (PDOException $exception) {
        if (!bandpromo_activity_store_is_unique_violation($exception)) {
            throw $exception;
        }
        $update->execute([
            'delta' => $delta,
            'day_key' => $dayKey,
            'device' => $device,
        ]);
    }
}

function bandpromo_activity_store_upsert_daily_track(
    PDO $pdo,
    string $dayKey,
    string $trackKey,
    string $title,
    string $artist,
    int $playDelta,
    int $secondsDelta
): void {
    $update = $pdo->prepare(
        'UPDATE rollup_daily_track
         SET play_count = play_count + :play_delta,
             total_seconds = total_seconds + :seconds_delta,
             title = CASE WHEN title = \'\' THEN :title ELSE title END,
             artist = CASE WHEN artist = \'\' OR artist = \'Unknown\' THEN :artist ELSE artist END
         WHERE day_key = :day_key AND track_key = :track_key'
    );
    $update->execute([
        'play_delta' => $playDelta,
        'seconds_delta' => $secondsDelta,
        'title' => $title,
        'artist' => $artist !== '' ? $artist : 'Unknown',
        'day_key' => $dayKey,
        'track_key' => $trackKey,
    ]);
    if ($update->rowCount() > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO rollup_daily_track (day_key, track_key, title, artist, play_count, total_seconds)
         VALUES (:day_key, :track_key, :title, :artist, :play_delta, :seconds_delta)'
    );
    try {
        $insert->execute([
            'day_key' => $dayKey,
            'track_key' => $trackKey,
            'title' => $title,
            'artist' => $artist !== '' ? $artist : 'Unknown',
            'play_delta' => $playDelta,
            'seconds_delta' => $secondsDelta,
        ]);
    } catch (PDOException $exception) {
        if (!bandpromo_activity_store_is_unique_violation($exception)) {
            throw $exception;
        }
        $update->execute([
            'play_delta' => $playDelta,
            'seconds_delta' => $secondsDelta,
            'title' => $title,
            'artist' => $artist !== '' ? $artist : 'Unknown',
            'day_key' => $dayKey,
            'track_key' => $trackKey,
        ]);
    }
}

function bandpromo_activity_store_rebuild_daily_rollups(PDO $pdo): void
{
    $pdo->exec('DELETE FROM rollup_daily_user');
    $pdo->exec('DELETE FROM rollup_daily_track');
    $pdo->exec('DELETE FROM rollup_daily_device');
    $pdo->exec('DELETE FROM rollup_daily_totals');
    $pdo->exec('DELETE FROM rollup_daily_activity');

    $stmt = $pdo->query(
        'SELECT ts_utc, username, activity, user_agent, data_json
         FROM listener_events
         ORDER BY ts_utc ASC, id ASC'
    );
    if ($stmt === false) {
        return;
    }

    while ($row = $stmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $data = json_decode((string) ($row['data_json'] ?? '{}'), true);
        bandpromo_activity_store_apply_daily_rollups(
            $pdo,
            [
                'ts_utc' => (int) ($row['ts_utc'] ?? 0),
                'username' => (string) ($row['username'] ?? ''),
                'activity' => (string) ($row['activity'] ?? ''),
                'user_agent' => (string) ($row['user_agent'] ?? ''),
                'data_json' => (string) ($row['data_json'] ?? '{}'),
            ],
            is_array($data) ? $data : []
        );
    }
}

function bandpromo_activity_store_day_range_keys(string $dateStart, string $dateEnd): array
{
    $bounds = bandpromo_utc_date_range_bounds($dateStart, $dateEnd);
    $timezone = bandpromo_analytics_bucket_timezone();
    $keys = [];
    $cursor = $bounds['start_unix'];
    while ($cursor <= $bounds['end_unix']) {
        $keys[] = bandpromo_admin_day_from_unix($cursor, $timezone);
        $cursor += 86400;
    }

    return array_values(array_unique($keys));
}

function bandpromo_activity_store_platform_stats(string $root, string $dateStart, string $dateEnd): array
{
    $dayKeys = bandpromo_activity_store_day_range_keys($dateStart, $dateEnd);
    if ($dayKeys === []) {
        return [
            'total_plays' => 0,
            'total_listening_time' => 0,
            'unique_users' => 0,
            'total_sessions' => 0,
            'device_breakdown' => [],
            'hourly_distribution' => [],
            'daily_distribution' => [],
            'activity_types' => [],
        ];
    }

    $pdo = bandpromo_activity_store_ensure_ready($root);
    $placeholders = implode(',', array_fill(0, count($dayKeys), '?'));

    $totalsStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(sessions), 0) AS sessions,
                COALESCE(SUM(play_count), 0) AS play_count,
                COALESCE(SUM(listening_seconds), 0) AS listening_seconds
         FROM rollup_daily_totals
         WHERE day_key IN ($placeholders)"
    );
    $totalsStmt->execute($dayKeys);
    $totals = $totalsStmt->fetch() ?: [];

    $usersStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT username) AS unique_users
         FROM rollup_daily_user
         WHERE day_key IN ($placeholders)"
    );
    $usersStmt->execute($dayKeys);
    $uniqueUsers = (int) ($usersStmt->fetchColumn() ?: 0);

    $deviceStmt = $pdo->prepare(
        "SELECT device, SUM(event_count) AS event_count
         FROM rollup_daily_device
         WHERE day_key IN ($placeholders)
         GROUP BY device
         ORDER BY event_count DESC"
    );
    $deviceStmt->execute($dayKeys);
    $deviceBreakdown = [];
    while ($row = $deviceStmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $deviceBreakdown[(string) $row['device']] = (int) ($row['event_count'] ?? 0);
    }

    $dailyStmt = $pdo->prepare(
        "SELECT day_key, play_count
         FROM rollup_daily_totals
         WHERE day_key IN ($placeholders)
         ORDER BY day_key ASC"
    );
    $dailyStmt->execute($dayKeys);
    $dailyDistribution = [];
    while ($row = $dailyStmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $dailyDistribution[(string) $row['day_key']] = (int) ($row['play_count'] ?? 0);
    }

    $activityStmt = $pdo->prepare(
        "SELECT activity, SUM(event_count) AS event_count
         FROM rollup_daily_activity
         WHERE day_key IN ($placeholders)
         GROUP BY activity
         ORDER BY event_count DESC"
    );
    $activityStmt->execute($dayKeys);
    $activityTypes = [];
    while ($row = $activityStmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $activityTypes[(string) $row['activity']] = (int) ($row['event_count'] ?? 0);
    }

    try {
        $hourlyDistribution = bandpromo_activity_store_hourly_distribution($root, $dateStart, $dateEnd);
    } catch (Throwable $e) {
        error_log('bandPromo analytics hourly distribution error: ' . $e->getMessage());
        $hourlyDistribution = [];
    }

    return [
        'total_plays' => (int) ($totals['play_count'] ?? 0),
        'total_listening_time' => (int) ($totals['listening_seconds'] ?? 0),
        'unique_users' => $uniqueUsers,
        'total_sessions' => (int) ($totals['sessions'] ?? 0),
        'device_breakdown' => $deviceBreakdown,
        'quality_estimate' => [],
        'hourly_distribution' => $hourlyDistribution,
        'daily_distribution' => $dailyDistribution,
        'activity_types' => $activityTypes,
    ];
}

function bandpromo_activity_store_users_listening_stats(
    string $root,
    string $dateStart,
    string $dateEnd,
    int $limit = 100
): array {
    $dayKeys = bandpromo_activity_store_day_range_keys($dateStart, $dateEnd);
    if ($dayKeys === []) {
        return [];
    }

    $pdo = bandpromo_activity_store_ensure_ready($root);
    $placeholders = implode(',', array_fill(0, count($dayKeys), '?'));
    $stmt = $pdo->prepare(
        "SELECT username,
                SUM(listening_seconds) AS listening_time,
                SUM(play_count) AS play_count,
                SUM(sessions) AS sessions
         FROM rollup_daily_user
         WHERE day_key IN ($placeholders)
         GROUP BY username
         ORDER BY listening_time DESC, play_count DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute($dayKeys);

    $users = [];
    while ($row = $stmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $users[] = [
            'username' => (string) ($row['username'] ?? ''),
            'listening_time' => (int) ($row['listening_time'] ?? 0),
            'play_count' => (int) ($row['play_count'] ?? 0),
            'sessions' => (int) ($row['sessions'] ?? 0),
            'last_activity' => '',
            'first_activity' => '',
            'devices' => [],
        ];
    }

    return $users;
}

function bandpromo_activity_store_top_tracks(
    string $root,
    string $dateStart,
    string $dateEnd,
    int $limit = 50
): array {
    $dayKeys = bandpromo_activity_store_day_range_keys($dateStart, $dateEnd);
    if ($dayKeys === []) {
        return [];
    }

    $pdo = bandpromo_activity_store_ensure_ready($root);
    $placeholders = implode(',', array_fill(0, count($dayKeys), '?'));
    $stmt = $pdo->prepare(
        "SELECT title, artist, track_key,
                SUM(play_count) AS play_count,
                SUM(total_seconds) AS total_time
         FROM rollup_daily_track
         WHERE day_key IN ($placeholders)
         GROUP BY track_key
         ORDER BY play_count DESC, total_time DESC
         LIMIT " . max(1, $limit)
    );
    $stmt->execute($dayKeys);

    $tracks = [];
    while ($row = $stmt->fetch()) {
        if (!is_array($row)) {
            continue;
        }
        $playCount = (int) ($row['play_count'] ?? 0);
        $totalTime = (int) ($row['total_time'] ?? 0);
        $tracks[] = [
            'title' => (string) ($row['title'] ?? ''),
            'artist' => (string) ($row['artist'] ?? 'Unknown'),
            'play_count' => $playCount,
            'total_time' => $totalTime,
            'unique_users' => 0,
            'avg_time' => $playCount > 0 ? round($totalTime / $playCount, 1) : 0,
        ];
    }

    return $tracks;
}

function bandpromo_activity_store_maybe_run_maintenance(string $root, PDO $pdo): void
{
    $lastRun = (int) bandpromo_activity_store_get_meta($pdo, 'maintenance_last_run_utc', '0');
    $now = time();
    if ($lastRun > 0 && ($now - $lastRun) < 86400) {
        return;
    }

    try {
        bandpromo_activity_store_run_retention($pdo);
        bandpromo_activity_store_set_meta($pdo, 'maintenance_last_run_utc', (string) $now);
        bandpromo_activity_store_set_meta($pdo, 'retention_raw_days', (string) bandpromo_activity_store_raw_retention_days());
    } catch (Throwable $e) {
        error_log('bandPromo activity store maintenance failed: ' . $e->getMessage());
    }
}

function bandpromo_activity_store_run_retention(PDO $pdo): void
{
    $cutoff = time() - (bandpromo_activity_store_raw_retention_days() * 86400);
    $stmt = $pdo->prepare('DELETE FROM listener_events WHERE ts_utc < :cutoff');
    $stmt->execute(['cutoff' => $cutoff]);
}

function bandpromo_activity_store_stream_listener_export(
    string $root,
    string $dateStart,
    string $dateEnd,
    string $format,
    $output
): int {
    $entries = bandpromo_activity_store_fetch_listener_entries($root, $dateStart, $dateEnd);
    $count = 0;
    $format = strtolower($format) === 'csv' ? 'csv' : 'jsonl';

    if ($format === 'csv') {
        fputcsv($output, ['timestamp', 'username', 'activity', 'ip', 'user_agent', 'data_json']);
    }

    foreach ($entries as $entry) {
        if ($format === 'csv') {
            fputcsv($output, [
                (string) ($entry['timestamp'] ?? ''),
                (string) ($entry['username'] ?? ''),
                (string) ($entry['activity'] ?? ''),
                (string) ($entry['ip'] ?? ''),
                (string) ($entry['user_agent'] ?? ''),
                json_encode($entry['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            ]);
        } else {
            fwrite($output, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }
        $count++;
    }

    return $count;
}
